<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

if (!class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
    require_once __DIR__ . '/class-cbt-exam-availability-auto-warm-service.php';
}

if (!class_exists('CBT_Exam_Question_Delivery_Cache')) {
    require_once __DIR__ . '/class-cbt-exam-question-delivery-cache.php';
}

if (!class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
    require_once __DIR__ . '/class-cbt-exam-start-attempt-snapshot-cache.php';
}

if (!class_exists('CBT_Student_Profile_Cache')) {
    require_once __DIR__ . '/class-cbt-student-profile-cache.php';
}

if (!class_exists('CBT_Login_Auth_Snapshot_Cache')) {
    require_once __DIR__ . '/class-cbt-login-auth-snapshot-cache.php';
}

if (!class_exists('CBT_Question_Submission_Context_Cache')) {
    require_once __DIR__ . '/class-cbt-question-submission-context-cache.php';
}

final class CBT_Exam_Preflight_Service
{
    public const CRON_HOOK = 'cbt_exam_preflight_tick';

    private const CRON_SCHEDULE = 'cbt_exam_preflight_every_minute';
    private const LOCK_KEY = 'exam_preflight';
    private const LOCK_TTL = 45;
    private const OPTION_STATE = 'cbt_exam_preflight_state';
    private const OPTION_JOBS = 'cbt_exam_preflight_jobs';
    private const OPTION_RUNNER = 'cbt_exam_preflight_global_runner';
    private const STATUS_ACTIVE = 'active';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_COMPLETED_WITH_WARNINGS = 'completed_with_warnings';
    private const STATUS_FAILED = 'failed';
    private const STATUS_INACTIVE = 'inactive';
    private const STATUS_QUEUED = 'queued';
    private const STATUS_STOPPED = 'stopped';
    private const GLOBAL_LAYER_PROFILES = 'profiles';
    private const GLOBAL_LAYER_LOGIN = 'login';
    private const GLOBAL_LAYER_AVAILABILITY = 'availability';
    private const GLOBAL_LAYER_PARALLEL = 'parallel';
    private const MAX_NONTERMINAL_EXAMS = 10;
    private const PROFILE_BATCH_SIZE = 150;
    private const PROFILE_LOGIN_INITIAL_BURST_SECONDS = 8.0;
    private const PROFILE_LOGIN_INITIAL_BURST_MAX_BATCHES = 12;

    public static function init(): void
    {
        if (function_exists('add_filter')) {
            add_filter('cron_schedules', [self::class, 'register_cron_schedule']);
        }

        if (function_exists('add_action')) {
            add_action(self::CRON_HOOK, [self::class, 'handle_cron_tick']);
        }

        self::maybe_restore_tick_event();
    }

    public static function activate(): void
    {
        self::maybe_restore_tick_event();
    }

    public static function deactivate(): void
    {
        self::clear_tick_event();
    }

    /**
     * @param array<string,array<string,mixed>> $schedules
     * @return array<string,array<string,mixed>>
     */
    public static function register_cron_schedule(array $schedules): array
    {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => MINUTE_IN_SECONDS,
                'display' => 'CBT Exam Preflight Every Minute',
            ];
        }

        return $schedules;
    }

    public static function handle_cron_tick(): void
    {
        self::tick();
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function start_for_exam(array $exam_row): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'start',
            'exam_id' => (int) ($exam_row['id'] ?? 0),
        ])) {
            return [
                'success' => false,
                'message' => 'One-click pra ujian sedang diproses proses lain. Coba lagi beberapa saat lagi.',
                'state' => self::get_state(),
            ];
        }

        try {
            $exam_id = (int) ($exam_row['id'] ?? 0);
            $jobs = self::get_jobs_state();
            $runner = self::get_global_runner_state();
            $existing_job = ($exam_id > 0 && isset($jobs[$exam_id]) && is_array($jobs[$exam_id]))
                ? self::build_state($jobs[$exam_id])
                : null;

            if (is_array($existing_job) && in_array((string) ($existing_job['status'] ?? ''), [self::STATUS_ACTIVE, self::STATUS_QUEUED], true)) {
                self::sync_legacy_state($existing_job, $runner);
                return [
                    'success' => true,
                    'message' => (string) ($existing_job['last_message'] ?? 'One-click pra ujian exam ini sudah aktif atau sedang antre.'),
                    'state' => $existing_job,
                ];
            }

            $eligibility = self::evaluate_exam_eligibility($exam_row);
            if (empty($eligibility['can_start'])) {
                $failed_state = self::build_failure_state($exam_row, (string) ($eligibility['message'] ?? 'Exam belum memenuhi syarat one-click pra ujian.'));
                if ($exam_id > 0) {
                    $jobs[$exam_id] = $failed_state;
                    self::save_jobs_state($jobs);
                }
                self::sync_legacy_state($failed_state, $runner);
                if (!self::has_pending_work($jobs, $runner)) {
                    self::clear_tick_event();
                }

                return [
                    'success' => false,
                    'message' => (string) ($failed_state['last_message'] ?? 'Exam belum memenuhi syarat one-click pra ujian.'),
                    'state' => $failed_state,
                ];
            }

            if ($exam_id > 0 && !isset($jobs[$exam_id]) && self::count_nonterminal_jobs($jobs) >= self::MAX_NONTERMINAL_EXAMS) {
                return [
                    'success' => false,
                    'message' => 'Antrean smart one-click penuh. Selesaikan atau bersihkan sebagian job dulu.',
                    'state' => self::get_state(),
                ];
            }

            $job = self::prepare_job_for_start($exam_row, $eligibility, $existing_job);
            $jobs[$exam_id] = $job;

            if ((string) ($job['status'] ?? '') === self::STATUS_FAILED) {
                self::save_jobs_state($jobs);
                self::sync_legacy_state($job, $runner);
                if (!self::has_pending_work($jobs, $runner)) {
                    self::clear_tick_event();
                }

                return [
                    'success' => false,
                    'message' => (string) ($job['last_message'] ?? 'One-click pra ujian gagal saat menyiapkan snapshot exam-local.'),
                    'state' => $job,
                ];
            }

            if (self::job_has_pending_global_work($job)) {
                if ((int) ($runner['active_exam_id'] ?? 0) > 0 && (int) ($runner['active_exam_id'] ?? 0) !== $exam_id) {
                    $job = self::mark_job_queued($job, $runner);
                    $jobs[$exam_id] = $job;
                    $runner = self::enqueue_runner_exam($runner, $exam_id);
                } else {
                    $runner['active_exam_id'] = $exam_id;
                    $runner['active_exam_title'] = (string) ($job['exam_title'] ?? self::resolve_exam_title($exam_row));
                    $runner['session_id'] = (string) ($job['session_id'] ?? self::generate_session_id($exam_id));
                    $job['status'] = self::STATUS_ACTIVE;
                    $job['active'] = true;
                    $jobs[$exam_id] = $job;
                    [$jobs, $runner] = self::advance_global_runner($jobs, $runner, 'start');
                    $job = $jobs[$exam_id] ?? $job;
                }
            } else {
                $job = self::finalize_completed_job($job);
                $jobs[$exam_id] = $job;
            }

            self::save_jobs_state($jobs);
            self::save_global_runner_state($runner);
            self::sync_legacy_state($job, $runner);

            if (self::has_pending_work($jobs, $runner)) {
                self::ensure_tick_event();
            } else {
                self::clear_tick_event();
            }

            return [
                'success' => true,
                'message' => (string) ($job['last_message'] ?? 'One-click pra ujian dijalankan.'),
                'state' => $job,
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick(): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'tick',
        ])) {
            return self::get_state();
        }

        try {
            $jobs = self::get_jobs_state();
            $runner = self::get_global_runner_state();
            if (!self::has_pending_work($jobs, $runner)) {
                self::clear_tick_event();
                return self::get_state();
            }

            [$jobs, $runner] = self::advance_global_runner($jobs, $runner, 'tick');
            self::save_jobs_state($jobs);
            self::save_global_runner_state($runner);
            $state = self::resolve_legacy_state($jobs, $runner);
            self::save_state($state);

            if (self::has_pending_work($jobs, $runner)) {
                self::ensure_tick_event();
            } else {
                self::clear_tick_event();
            }

            return $state;
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function stop_for_exam(array $exam_row): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'stop',
            'exam_id' => (int) ($exam_row['id'] ?? 0),
        ])) {
            return [
                'success' => false,
                'message' => 'One-click pra ujian sedang diproses proses lain. Coba lagi beberapa saat lagi.',
                'state' => self::get_state(),
            ];
        }

        try {
            $exam_id = (int) ($exam_row['id'] ?? 0);
            $jobs = self::get_jobs_state();
            $runner = self::get_global_runner_state();
            $legacy_state = self::get_state();
            $job = ($exam_id > 0 && isset($jobs[$exam_id]) && is_array($jobs[$exam_id]))
                ? self::build_state($jobs[$exam_id])
                : null;

            if (!is_array($job) || !in_array((string) ($job['status'] ?? ''), [self::STATUS_ACTIVE, self::STATUS_QUEUED], true)) {
                if ((int) ($runner['active_exam_id'] ?? 0) === 0
                    && empty($runner['queue_exam_ids'])
                    && !empty($legacy_state['active'])
                    && (int) ($legacy_state['exam_id'] ?? 0) === $exam_id) {
                    $legacy_stopped = self::mark_job_stopped($legacy_state, 'One-click pra ujian dihentikan manual.');
                    self::save_state($legacy_stopped);
                    self::clear_tick_event();

                    return [
                        'success' => true,
                        'message' => (string) ($legacy_stopped['last_message'] ?? 'One-click pra ujian dihentikan.'),
                        'state' => $legacy_stopped,
                    ];
                }

                if ((int) ($runner['active_exam_id'] ?? 0) > 0 && (int) ($runner['active_exam_id'] ?? 0) !== $exam_id) {
                    return [
                        'success' => false,
                        'message' => sprintf(
                            'One-click pra ujian saat ini aktif untuk exam lain: %s.',
                            (string) ($runner['active_exam_title'] ?? ('Exam #' . (int) ($runner['active_exam_id'] ?? 0)))
                        ),
                        'state' => self::get_state(),
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Belum ada sesi one-click pra ujian yang aktif atau antre untuk exam ini.',
                    'state' => self::get_state(),
                ];
            }

            if (in_array($exam_id, (array) ($runner['queue_exam_ids'] ?? []), true) && (int) ($runner['active_exam_id'] ?? 0) !== $exam_id) {
                $runner = self::dequeue_runner_exam($runner, $exam_id);
                $job = self::mark_job_stopped($job, 'One-click pra ujian dibatalkan dari antrean.');
                $jobs[$exam_id] = $job;
                self::save_jobs_state($jobs);
                self::save_global_runner_state($runner);
                self::sync_legacy_state($job, $runner);

                return [
                    'success' => true,
                    'message' => (string) ($job['last_message'] ?? 'One-click pra ujian dibatalkan dari antrean.'),
                    'state' => $job,
                ];
            }

            if ((int) ($runner['active_exam_id'] ?? 0) !== $exam_id) {
                return [
                    'success' => false,
                    'message' => 'Exam ini tidak sedang menjadi owner global runner.',
                    'state' => self::get_state(),
                ];
            }

            $exam_state_row = self::build_exam_row_from_state($job);
            $auto_warm_state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
            if (!empty($auto_warm_state['active']) && (int) ($auto_warm_state['exam_id'] ?? 0) === $exam_id) {
                CBT_Exam_Availability_Auto_Warm_Service::stop_for_exam($exam_state_row);
            }

            $job = self::mark_job_stopped($job, 'One-click pra ujian dihentikan manual.');
            $jobs[$exam_id] = $job;
            $runner = self::release_runner_owner($runner, $exam_id);
            [$jobs, $runner] = self::advance_global_runner($jobs, $runner, 'resume');
            self::save_jobs_state($jobs);
            self::save_global_runner_state($runner);
            self::sync_legacy_state($job, $runner);

            if (!self::has_pending_work($jobs, $runner)) {
                self::clear_tick_event();
            } else {
                self::ensure_tick_event();
            }

            return [
                'success' => true,
                'message' => (string) ($job['last_message'] ?? 'One-click pra ujian dihentikan.'),
                'state' => $job,
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function clear_state_for_exam(array $exam_row): array
    {
        if (!CBT_Cache::acquire_lock(self::LOCK_KEY, self::LOCK_TTL, [
            'source' => 'clear_state',
            'exam_id' => (int) ($exam_row['id'] ?? 0),
        ])) {
            return [
                'success' => false,
                'message' => 'State one-click pra ujian sedang diproses proses lain. Coba lagi beberapa saat lagi.',
                'state' => self::get_state(),
            ];
        }

        try {
            $exam_id = (int) ($exam_row['id'] ?? 0);
            $jobs = self::get_jobs_state();
            $runner = self::get_global_runner_state();
            $legacy_state = self::get_state();
            $legacy_matches_exam = $exam_id > 0 && (int) ($legacy_state['exam_id'] ?? 0) === $exam_id;
            if ($exam_id <= 0 || (!isset($jobs[$exam_id]) && !$legacy_matches_exam)) {
                return [
                    'success' => true,
                    'message' => 'State one-click pra ujian untuk exam ini sudah kosong.',
                    'state' => self::get_state(),
                ];
            }

            if (isset($jobs[$exam_id])) {
                unset($jobs[$exam_id]);
            }
            $runner = self::dequeue_runner_exam($runner, $exam_id);
            $runner = self::release_runner_owner($runner, $exam_id);
            self::save_jobs_state($jobs);
            self::save_global_runner_state($runner);
            if ($legacy_matches_exam && empty($jobs) && (int) ($runner['active_exam_id'] ?? 0) === 0 && empty($runner['queue_exam_ids'])) {
                self::save_state(self::build_state([]));
            } else {
                self::save_state(self::resolve_legacy_state($jobs, $runner));
            }

            if (!self::has_pending_work($jobs, $runner)) {
                self::clear_tick_event();
            }

            return [
                'success' => true,
                'message' => 'State one-click pra ujian dibersihkan.',
                'state' => self::get_state(),
            ];
        } finally {
            CBT_Cache::release_lock(self::LOCK_KEY);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_state(): array
    {
        $state = get_option(self::OPTION_STATE, []);
        return self::build_state(is_array($state) ? $state : []);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_jobs_state(): array
    {
        $jobs = get_option(self::OPTION_JOBS, []);
        if (!is_array($jobs)) {
            return [];
        }

        $normalized = [];
        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $normalized_job = self::build_state($job);
            $exam_id = (int) ($normalized_job['exam_id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $normalized[$exam_id] = $normalized_job;
        }

        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     */
    private static function save_jobs_state(array $jobs): void
    {
        $normalized = [];
        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $normalized_job = self::build_state($job);
            $exam_id = (int) ($normalized_job['exam_id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $normalized[$exam_id] = $normalized_job;
        }

        update_option(self::OPTION_JOBS, $normalized);
    }

    /**
     * @return array{
     *   active_exam_id:int,
     *   active_exam_title:string,
     *   active_layer:string,
     *   queue_exam_ids:array<int,int>,
     *   cursor:int,
     *   session_id:string,
     *   last_tick_at:string
     * }
     */
    public static function get_global_runner_state(): array
    {
        $runner = get_option(self::OPTION_RUNNER, []);
        return self::build_global_runner_state(is_array($runner) ? $runner : []);
    }

    /**
     * @param array<string,mixed> $runner
     */
    private static function save_global_runner_state(array $runner): void
    {
        update_option(self::OPTION_RUNNER, self::build_global_runner_state($runner));
    }

    /**
     * @param array<string,mixed> $runner
     * @return array{
     *   active_exam_id:int,
     *   active_exam_title:string,
     *   active_layer:string,
     *   queue_exam_ids:array<int,int>,
     *   cursor:int,
     *   session_id:string,
     *   last_tick_at:string
     * }
     */
    private static function build_global_runner_state(array $runner): array
    {
        $queue_exam_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($runner['queue_exam_ids'] ?? [])))));

        return [
            'active_exam_id' => absint($runner['active_exam_id'] ?? 0),
            'active_exam_title' => sanitize_text_field((string) ($runner['active_exam_title'] ?? '')),
            'active_layer' => sanitize_key((string) ($runner['active_layer'] ?? '')),
            'queue_exam_ids' => $queue_exam_ids,
            'cursor' => max(0, (int) ($runner['cursor'] ?? 0)),
            'session_id' => sanitize_text_field((string) ($runner['session_id'] ?? '')),
            'last_tick_at' => sanitize_text_field((string) ($runner['last_tick_at'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function get_exam_panel_context(array $exam_row, bool $question_snapshot_ready = false, bool $start_snapshot_ready = false): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $exam_title = self::resolve_exam_title($exam_row);
        $jobs = self::get_jobs_state();
        $runner = self::get_global_runner_state();
        $legacy_state = self::get_state();
        $state = ($exam_id > 0 && isset($jobs[$exam_id]) && is_array($jobs[$exam_id]))
            ? self::build_state($jobs[$exam_id])
            : (((int) ($legacy_state['exam_id'] ?? 0) === $exam_id) ? $legacy_state : self::build_state([]));
        $is_same_exam_state = $exam_id > 0 && (int) ($state['exam_id'] ?? 0) === $exam_id;
        $active_runner_exam_id = (int) ($runner['active_exam_id'] ?? 0);
        $has_active_runner_other_exam = $active_runner_exam_id > 0 && $active_runner_exam_id !== $exam_id;
        $target_kelas = CBT_Exam_Availability_Auto_Warm_Service::get_target_kelas_for_exam($exam_row);
        $resolved_target_student_count = method_exists('CBT_Exam_Availability_Auto_Warm_Service', 'count_target_students_for_exam')
            ? CBT_Exam_Availability_Auto_Warm_Service::count_target_students_for_exam($exam_row, false)
            : count(CBT_Exam_Availability_Auto_Warm_Service::get_target_student_ids_for_exam($exam_row, false));
        $target_student_count = $is_same_exam_state
            ? max(0, (int) ($state['target_student_count'] ?? $resolved_target_student_count))
            : $resolved_target_student_count;
        $question_cache_ready = class_exists('CBT_Exam_Question_Delivery_Cache')
            && method_exists('CBT_Exam_Question_Delivery_Cache', 'is_available')
            && CBT_Exam_Question_Delivery_Cache::is_available();
        $start_cache_ready = class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')
            && method_exists('CBT_Exam_Start_Attempt_Snapshot_Cache', 'is_available')
            && CBT_Exam_Start_Attempt_Snapshot_Cache::is_available();
        $availability_cache_ready = class_exists('CBT_Exam_Availability_Cache')
            && method_exists('CBT_Exam_Availability_Cache', 'is_available')
            && CBT_Exam_Availability_Cache::is_available();
        $profile_cache_ready = class_exists('CBT_Student_Profile_Cache')
            && method_exists('CBT_Student_Profile_Cache', 'is_available')
            && CBT_Student_Profile_Cache::is_available();
        $submission_context_cache_ready = class_exists('CBT_Question_Submission_Context_Cache')
            && method_exists('CBT_Question_Submission_Context_Cache', 'is_available')
            && CBT_Question_Submission_Context_Cache::is_available();
        $rest_warm_ready = class_exists('CBT_REST') && method_exists('CBT_REST', 'warm_exam_question_delivery_snapshot');
        $start_warm_ready = class_exists('CBT_REST') && method_exists('CBT_REST', 'warm_exam_start_attempt_snapshot');
        $submission_context_warm_ready = class_exists('CBT_REST') && method_exists('CBT_REST', 'warm_exam_submission_context_snapshot');
        $auto_warm_state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        $blocking_auto_warm_exam_id = (!empty($auto_warm_state['active']) && (int) ($auto_warm_state['exam_id'] ?? 0) > 0 && (int) ($auto_warm_state['exam_id'] ?? 0) !== $exam_id)
            ? (int) $auto_warm_state['exam_id']
            : 0;
        $blocking_auto_warm_exam_title = $blocking_auto_warm_exam_id > 0
            ? (string) ($auto_warm_state['exam_title'] ?? ('Exam #' . $blocking_auto_warm_exam_id))
            : '';
        $exam_status = sanitize_key((string) ($exam_row['status'] ?? ''));
        $status_key = $is_same_exam_state
            ? sanitize_key((string) ($state['status'] ?? self::STATUS_INACTIVE))
            : self::STATUS_INACTIVE;
        $status_meta = self::build_status_meta($status_key);
        $profile_success_count = $is_same_exam_state ? max(0, (int) ($state['profiles_ready_count'] ?? ($state['profile_success_count'] ?? 0))) : 0;
        $profile_failure_count = $is_same_exam_state ? max(0, (int) ($state['profiles_failure_count'] ?? ($state['profile_failure_count'] ?? 0))) : 0;
        $profile_processed_count = $profile_success_count + $profile_failure_count;
        $login_snapshot_success_count = $is_same_exam_state ? max(0, (int) ($state['login_ready_count'] ?? ($state['login_snapshot_success_count'] ?? 0))) : 0;
        $login_snapshot_failure_count = $is_same_exam_state ? max(0, (int) ($state['login_failure_count'] ?? ($state['login_snapshot_failure_count'] ?? 0))) : 0;
        $login_snapshot_missing_count = max(0, $target_student_count - $login_snapshot_success_count);
        $submission_context_question_count = $is_same_exam_state ? max(0, (int) ($state['submission_context_question_count'] ?? 0)) : 0;
        $submission_context_ready_count = $is_same_exam_state ? max(0, (int) ($state['submission_context_ready_count'] ?? 0)) : 0;
        $submission_context_missing_count = $is_same_exam_state ? max(0, (int) ($state['submission_context_missing_count'] ?? 0)) : 0;
        $submission_context_invalid_count = $is_same_exam_state ? max(0, (int) ($state['submission_context_invalid_count'] ?? 0)) : 0;
        $profile_cursor = $is_same_exam_state ? max(0, (int) ($state['profile_cursor'] ?? 0)) : 0;
        $queue_position = self::queue_position_for_exam($exam_id, $runner);
        $queue_total = count((array) ($runner['queue_exam_ids'] ?? []));
        $queued_exam_titles = [];
        foreach ((array) ($runner['queue_exam_ids'] ?? []) as $queued_exam_id) {
            $queued_exam_id = absint($queued_exam_id);
            if ($queued_exam_id <= 0) {
                continue;
            }

            $queued_job = isset($jobs[$queued_exam_id]) && is_array($jobs[$queued_exam_id]) ? self::build_state($jobs[$queued_exam_id]) : [];
            $queued_exam_titles[] = (string) ($queued_job['exam_title'] ?? ('Exam #' . $queued_exam_id));
        }
        $same_exam_nonterminal = $is_same_exam_state && in_array($status_key, [self::STATUS_ACTIVE, self::STATUS_QUEUED], true);
        $queue_is_full_for_new_exam = !$same_exam_nonterminal && self::count_nonterminal_jobs($jobs) >= self::MAX_NONTERMINAL_EXAMS;
        $can_start = $exam_id > 0
            && $exam_status === 'published'
            && !empty($target_kelas)
            && $target_student_count > 0
            && $question_cache_ready
            && $start_cache_ready
            && $availability_cache_ready
            && $profile_cache_ready
            && $rest_warm_ready
            && $start_warm_ready;
        if ($same_exam_nonterminal || $queue_is_full_for_new_exam) {
            $can_start = false;
        }

        $message = '';
        if ($same_exam_nonterminal && (string) ($state['last_message'] ?? '') !== '') {
            $message = (string) $state['last_message'];
        } elseif ($queue_is_full_for_new_exam) {
            $message = 'Antrean smart one-click penuh. Selesaikan atau bersihkan sebagian job dulu.';
        } elseif ($exam_id <= 0) {
            $message = 'Pilih exam dulu untuk menjalankan one-click pra ujian.';
        } elseif (!$question_cache_ready) {
            $message = 'Redis snapshot soal belum siap di environment ini.';
        } elseif (!$start_cache_ready) {
            $message = 'Redis start snapshot belum siap di environment ini.';
        } elseif (!$availability_cache_ready) {
            $message = 'Redis availability belum siap di environment ini.';
        } elseif (!$profile_cache_ready) {
            $message = 'Redis snapshot profil belum siap di environment ini.';
        } elseif (!$rest_warm_ready) {
            $message = 'Helper warm snapshot soal belum tersedia.';
        } elseif (!$start_warm_ready) {
            $message = 'Helper warm start snapshot belum tersedia.';
        } elseif ($exam_status !== 'published') {
            $message = 'One-click pra ujian hanya tersedia untuk exam berstatus published.';
        } elseif (empty($target_kelas)) {
            $message = 'One-click pra ujian membutuhkan target kelas pada exam ini.';
        } elseif ($target_student_count <= 0) {
            $message = self::should_defer_student_cohort_canonical_scan()
                ? 'Student Cohort Index masih building; target siswa belum dihitung detail agar halaman snapshot tetap cepat.'
                : 'Belum ada siswa target yang cocok dengan target_kelas exam ini.';
        } else {
            $message = 'One-click memakai blocker dan warning kesiapan yang sama, lalu menyiapkan Snapshot Soal, Start Snapshot, Submission Context, Snapshot Profil, Login Snapshot, dan Auto-Warm Availability dengan mode global paralel batch 150.';
        }

        $question_stage = $question_snapshot_ready ? 'ready' : 'pending';
        if ($is_same_exam_state && (string) ($state['stage_question'] ?? '') === 'failed') {
            $question_stage = 'failed';
        }
        $start_snapshot_stage = $start_snapshot_ready ? 'ready' : 'pending';
        if ($is_same_exam_state && (string) ($state['stage_start_snapshot'] ?? '') === 'failed') {
            $start_snapshot_stage = 'failed';
        }
        $submission_context_stage = $is_same_exam_state ? sanitize_key((string) ($state['stage_submission_context'] ?? 'pending')) : 'pending';

        $auto_warm_stage = $is_same_exam_state
            ? sanitize_key((string) ($state['stage_auto_warm'] ?? 'pending'))
            : 'pending';
        if (!$is_same_exam_state && $blocking_auto_warm_exam_id === 0) {
            $auto_warm_context = CBT_Exam_Availability_Auto_Warm_Service::get_exam_panel_context($exam_row);
            $auto_warm_status = sanitize_key((string) ($auto_warm_context['status'] ?? 'inactive'));
            $auto_warm_stage = $auto_warm_status === 'active' ? 'ready' : $auto_warm_status;
        }

        return [
            'enabled' => $exam_id > 0,
            'status' => $status_key,
            'status_label' => $status_meta['label'],
            'status_tone' => $status_meta['tone'],
            'session_id' => $is_same_exam_state ? (string) ($state['session_id'] ?? '') : '',
            'exam_id' => $exam_id,
            'exam_title' => $exam_title,
            'target_kelas' => $target_kelas,
            'target_student_count' => $target_student_count,
            'profile_success_count' => $profile_success_count,
            'profile_failure_count' => $profile_failure_count,
            'profile_processed_count' => $profile_processed_count,
            'login_snapshot_success_count' => $login_snapshot_success_count,
            'login_snapshot_failure_count' => $login_snapshot_failure_count,
            'login_snapshot_ready_count' => $login_snapshot_success_count,
            'login_snapshot_missing_count' => $login_snapshot_missing_count,
            'profiles_reuse_count' => $is_same_exam_state ? max(0, (int) ($state['profiles_reuse_count'] ?? 0)) : 0,
            'profiles_pending_count' => $is_same_exam_state ? max(0, (int) ($state['profiles_pending_count'] ?? 0)) : $target_student_count,
            'login_reuse_count' => $is_same_exam_state ? max(0, (int) ($state['login_reuse_count'] ?? 0)) : 0,
            'login_pending_count' => $is_same_exam_state ? max(0, (int) ($state['login_pending_count'] ?? 0)) : $target_student_count,
            'availability_ready_count' => $is_same_exam_state ? max(0, (int) ($state['availability_ready_count'] ?? 0)) : 0,
            'availability_reuse_count' => $is_same_exam_state ? max(0, (int) ($state['availability_reuse_count'] ?? 0)) : 0,
            'availability_pending_count' => $is_same_exam_state ? max(0, (int) ($state['availability_pending_count'] ?? 0)) : $target_student_count,
            'availability_failure_count' => $is_same_exam_state ? max(0, (int) ($state['availability_failure_count'] ?? 0)) : 0,
            'submission_context_question_count' => $submission_context_question_count,
            'submission_context_ready_count' => $submission_context_ready_count,
            'submission_context_missing_count' => $submission_context_missing_count,
            'submission_context_invalid_count' => $submission_context_invalid_count,
            'profile_cursor' => $profile_cursor,
            'question_snapshot_ready' => $question_snapshot_ready || !empty($state['question_snapshot_ready']),
            'start_snapshot_ready' => $start_snapshot_ready || !empty($state['start_snapshot_ready']),
            'submission_context_ready' => !empty($state['submission_context_ready']),
            'auto_warm_started' => !empty($state['auto_warm_started']),
            'started_at' => $is_same_exam_state ? (string) ($state['started_at'] ?? '') : '',
            'finished_at' => $is_same_exam_state ? (string) ($state['finished_at'] ?? '') : '',
            'last_tick_at' => $is_same_exam_state ? (string) ($state['last_tick_at'] ?? '') : '',
            'last_message' => $message,
            'stage_question' => $question_stage,
            'stage_question_label' => self::build_stage_meta($question_stage, $question_stage === 'ready' ? 'READY' : null)['label'],
            'stage_question_tone' => self::build_stage_meta($question_stage, $question_stage === 'ready' ? 'READY' : null)['tone'],
            'stage_start_snapshot' => $start_snapshot_stage,
            'stage_start_snapshot_label' => self::build_stage_meta($start_snapshot_stage, $start_snapshot_stage === 'ready' ? 'READY' : null)['label'],
            'stage_start_snapshot_tone' => self::build_stage_meta($start_snapshot_stage, $start_snapshot_stage === 'ready' ? 'READY' : null)['tone'],
            'stage_submission_context' => $submission_context_stage,
            'stage_submission_context_label' => self::build_stage_meta($submission_context_stage, $submission_context_stage === 'ready' ? 'READY' : null)['label'],
            'stage_submission_context_tone' => self::build_stage_meta($submission_context_stage, $submission_context_stage === 'ready' ? 'READY' : null)['tone'],
            'stage_profiles' => $is_same_exam_state ? sanitize_key((string) ($state['stage_profiles'] ?? 'pending')) : 'pending',
            'stage_profiles_label' => self::build_stage_meta($is_same_exam_state ? sanitize_key((string) ($state['stage_profiles'] ?? 'pending')) : 'pending')['label'],
            'stage_profiles_tone' => self::build_stage_meta($is_same_exam_state ? sanitize_key((string) ($state['stage_profiles'] ?? 'pending')) : 'pending')['tone'],
            'stage_login_snapshot' => $is_same_exam_state ? sanitize_key((string) ($state['stage_login_snapshot'] ?? 'pending')) : 'pending',
            'stage_login_snapshot_label' => self::build_stage_meta($is_same_exam_state ? sanitize_key((string) ($state['stage_login_snapshot'] ?? 'pending')) : 'pending')['label'],
            'stage_login_snapshot_tone' => self::build_stage_meta($is_same_exam_state ? sanitize_key((string) ($state['stage_login_snapshot'] ?? 'pending')) : 'pending')['tone'],
            'stage_auto_warm' => $auto_warm_stage,
            'stage_auto_warm_label' => self::build_stage_meta($auto_warm_stage, $auto_warm_stage === 'ready' ? 'AKTIF' : null)['label'],
            'stage_auto_warm_tone' => self::build_stage_meta($auto_warm_stage, $auto_warm_stage === 'ready' ? 'AKTIF' : null)['tone'],
            'can_start' => $can_start,
            'question_cache_ready' => $question_cache_ready,
            'start_cache_ready' => $start_cache_ready,
            'availability_cache_ready' => $availability_cache_ready,
            'profile_cache_ready' => $profile_cache_ready,
            'submission_context_cache_ready' => $submission_context_cache_ready,
            'login_snapshot_cache_ready' => CBT_Login_Auth_Snapshot_Cache::is_available(),
            'rest_warm_ready' => $rest_warm_ready,
            'start_warm_ready' => $start_warm_ready,
            'submission_context_warm_ready' => $submission_context_warm_ready,
            'blocking_exam_id' => $has_active_runner_other_exam ? $active_runner_exam_id : 0,
            'blocking_exam_title' => $has_active_runner_other_exam ? (string) ($runner['active_exam_title'] ?? ('Exam #' . $active_runner_exam_id)) : '',
            'blocking_auto_warm_exam_id' => $blocking_auto_warm_exam_id,
            'blocking_auto_warm_exam_title' => $blocking_auto_warm_exam_title,
            'queue_position' => $queue_position,
            'queue_total' => $queue_total,
            'queued_exam_titles' => $queued_exam_titles,
            'global_runner_exam_id' => $active_runner_exam_id,
            'global_runner_exam_title' => (string) ($runner['active_exam_title'] ?? ''),
            'global_mode' => self::GLOBAL_LAYER_PARALLEL,
            'global_mode_label' => 'PARALEL',
            'global_batch_size' => self::PROFILE_BATCH_SIZE,
            'active_global_layer' => (string) ($runner['active_layer'] ?? ''),
        ];
    }

    private static function maybe_restore_tick_event(): void
    {
        $state = self::get_state();
        $runner = self::get_global_runner_state();
        if (!empty($state['active']) || !empty($runner['active_exam_id']) || !empty($runner['queue_exam_ids'])) {
            self::ensure_tick_event();
            return;
        }

        self::clear_tick_event();
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_state(array $state): array
    {
        $target_student_ids = array_values(array_filter(array_map('absint', (array) ($state['target_student_ids'] ?? []))));
        $profiles_pending_user_ids = array_values(array_filter(array_map('absint', (array) ($state['profiles_pending_user_ids'] ?? []))));
        $login_pending_user_ids = array_values(array_filter(array_map('absint', (array) ($state['login_pending_user_ids'] ?? []))));
        $availability_pending_user_ids = array_values(array_filter(array_map('absint', (array) ($state['availability_pending_user_ids'] ?? []))));
        $status = sanitize_key((string) ($state['status'] ?? self::STATUS_INACTIVE));
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_WARNINGS, self::STATUS_FAILED, self::STATUS_INACTIVE, self::STATUS_QUEUED, self::STATUS_STOPPED], true)) {
            $status = self::STATUS_INACTIVE;
        }

        return [
            'active' => !empty($state['active']) || $status === self::STATUS_ACTIVE,
            'status' => $status,
            'session_id' => sanitize_text_field((string) ($state['session_id'] ?? '')),
            'exam_id' => absint($state['exam_id'] ?? 0),
            'exam_title' => sanitize_text_field((string) ($state['exam_title'] ?? '')),
            'exam_status' => sanitize_key((string) ($state['exam_status'] ?? '')),
            'target_kelas_csv' => sanitize_text_field((string) ($state['target_kelas_csv'] ?? '')),
            'target_student_ids' => $target_student_ids,
            'target_student_count' => max(0, (int) ($state['target_student_count'] ?? count($target_student_ids))),
            'profile_cursor' => max(0, (int) ($state['profile_cursor'] ?? 0)),
            'profile_success_count' => max(0, (int) ($state['profile_success_count'] ?? 0)),
            'profile_failure_count' => max(0, (int) ($state['profile_failure_count'] ?? 0)),
            'login_snapshot_success_count' => max(0, (int) ($state['login_snapshot_success_count'] ?? 0)),
            'login_snapshot_failure_count' => max(0, (int) ($state['login_snapshot_failure_count'] ?? 0)),
            'submission_context_question_count' => max(0, (int) ($state['submission_context_question_count'] ?? 0)),
            'submission_context_ready_count' => max(0, (int) ($state['submission_context_ready_count'] ?? 0)),
            'submission_context_missing_count' => max(0, (int) ($state['submission_context_missing_count'] ?? 0)),
            'submission_context_invalid_count' => max(0, (int) ($state['submission_context_invalid_count'] ?? 0)),
            'question_snapshot_ready' => !empty($state['question_snapshot_ready']),
            'start_snapshot_ready' => !empty($state['start_snapshot_ready']),
            'submission_context_ready' => !empty($state['submission_context_ready']),
            'auto_warm_started' => !empty($state['auto_warm_started']),
            'started_at' => sanitize_text_field((string) ($state['started_at'] ?? '')),
            'finished_at' => sanitize_text_field((string) ($state['finished_at'] ?? '')),
            'last_tick_at' => sanitize_text_field((string) ($state['last_tick_at'] ?? '')),
            'last_message' => sanitize_text_field((string) ($state['last_message'] ?? '')),
            'stage_question' => sanitize_key((string) ($state['stage_question'] ?? 'pending')),
            'stage_start_snapshot' => sanitize_key((string) ($state['stage_start_snapshot'] ?? 'pending')),
            'stage_submission_context' => sanitize_key((string) ($state['stage_submission_context'] ?? 'pending')),
            'stage_profiles' => sanitize_key((string) ($state['stage_profiles'] ?? 'pending')),
            'stage_login_snapshot' => sanitize_key((string) ($state['stage_login_snapshot'] ?? 'pending')),
            'stage_auto_warm' => sanitize_key((string) ($state['stage_auto_warm'] ?? 'pending')),
            'profiles_ready_count' => max(0, (int) ($state['profiles_ready_count'] ?? (int) ($state['profile_success_count'] ?? 0))),
            'profiles_reuse_count' => max(0, (int) ($state['profiles_reuse_count'] ?? 0)),
            'profiles_pending_count' => max(0, (int) ($state['profiles_pending_count'] ?? count($profiles_pending_user_ids))),
            'profiles_failure_count' => max(0, (int) ($state['profiles_failure_count'] ?? (int) ($state['profile_failure_count'] ?? 0))),
            'profiles_pending_user_ids' => $profiles_pending_user_ids,
            'login_ready_count' => max(0, (int) ($state['login_ready_count'] ?? (int) ($state['login_snapshot_ready_count'] ?? (int) ($state['login_snapshot_success_count'] ?? 0)))),
            'login_reuse_count' => max(0, (int) ($state['login_reuse_count'] ?? 0)),
            'login_pending_count' => max(0, (int) ($state['login_pending_count'] ?? count($login_pending_user_ids))),
            'login_failure_count' => max(0, (int) ($state['login_failure_count'] ?? (int) ($state['login_snapshot_failure_count'] ?? 0))),
            'login_pending_user_ids' => $login_pending_user_ids,
            'availability_ready_count' => max(0, (int) ($state['availability_ready_count'] ?? 0)),
            'availability_reuse_count' => max(0, (int) ($state['availability_reuse_count'] ?? 0)),
            'availability_pending_count' => max(0, (int) ($state['availability_pending_count'] ?? count($availability_pending_user_ids))),
            'availability_failure_count' => max(0, (int) ($state['availability_failure_count'] ?? 0)),
            'availability_pending_user_ids' => $availability_pending_user_ids,
            'queue_position' => max(0, (int) ($state['queue_position'] ?? 0)),
            'active_global_layer' => sanitize_key((string) ($state['active_global_layer'] ?? '')),
            'global_runner_exam_id' => absint($state['global_runner_exam_id'] ?? 0),
            'global_runner_exam_title' => sanitize_text_field((string) ($state['global_runner_exam_title'] ?? '')),
            'queue_total' => max(0, (int) ($state['queue_total'] ?? 0)),
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function save_state(array $state): void
    {
        update_option(self::OPTION_STATE, self::build_state($state));
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array{can_start:bool,message:string,target_student_ids:int[]}
     */
    private static function evaluate_exam_eligibility(array $exam_row): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        if ($exam_id <= 0) {
            return [
                'can_start' => false,
                'message' => 'Exam belum dipilih untuk one-click pra ujian.',
                'target_student_ids' => [],
            ];
        }

        if (!class_exists('CBT_REST') || !method_exists('CBT_REST', 'warm_exam_question_delivery_snapshot')) {
            return [
                'can_start' => false,
                'message' => 'Helper warm snapshot soal belum tersedia.',
                'target_student_ids' => [],
            ];
        }

        if (!method_exists('CBT_REST', 'warm_exam_start_attempt_snapshot')) {
            return [
                'can_start' => false,
                'message' => 'Helper warm start snapshot belum tersedia.',
                'target_student_ids' => [],
            ];
        }

        if (!CBT_Exam_Question_Delivery_Cache::is_available()) {
            return [
                'can_start' => false,
                'message' => 'Redis snapshot soal belum siap di environment ini.',
                'target_student_ids' => [],
            ];
        }

        if (!CBT_Exam_Start_Attempt_Snapshot_Cache::is_available()) {
            return [
                'can_start' => false,
                'message' => 'Redis start snapshot belum siap di environment ini.',
                'target_student_ids' => [],
            ];
        }

        if (!CBT_Exam_Availability_Cache::is_available()) {
            return [
                'can_start' => false,
                'message' => 'Redis availability belum siap di environment ini.',
                'target_student_ids' => [],
            ];
        }

        if (!CBT_Student_Profile_Cache::is_available()) {
            return [
                'can_start' => false,
                'message' => 'Redis snapshot profil belum siap di environment ini.',
                'target_student_ids' => [],
            ];
        }

        $status = sanitize_key((string) ($exam_row['status'] ?? ''));
        if ($status !== 'published') {
            return [
                'can_start' => false,
                'message' => 'One-click pra ujian hanya tersedia untuk exam berstatus published.',
                'target_student_ids' => [],
            ];
        }

        $target_kelas = CBT_Exam_Availability_Auto_Warm_Service::get_target_kelas_for_exam($exam_row);
        if (empty($target_kelas)) {
            return [
                'can_start' => false,
                'message' => 'Target kelas pada exam ini belum diatur.',
                'target_student_ids' => [],
            ];
        }

        $target_student_ids = CBT_Exam_Availability_Auto_Warm_Service::get_target_student_ids_for_exam($exam_row);
        if (empty($target_student_ids)) {
            return [
                'can_start' => false,
                'message' => 'Belum ada siswa target yang cocok dengan target_kelas exam ini.',
                'target_student_ids' => [],
            ];
        }

        return [
            'can_start' => true,
            'message' => 'Exam siap untuk one-click pra ujian.',
            'target_student_ids' => $target_student_ids,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function ensure_question_snapshot_ready(array $state, array $exam_row): array
    {
        $state = self::build_state($state);
        $exam_id = (int) ($exam_row['id'] ?? 0);
        if ($exam_id <= 0) {
            $state['active'] = false;
            $state['status'] = self::STATUS_FAILED;
            $state['finished_at'] = current_time('mysql');
            $state['stage_question'] = 'failed';
            $state['last_message'] = 'One-click pra ujian gagal: exam belum dipilih.';
            return $state;
        }

        CBT_REST::warm_exam_question_delivery_snapshot($exam_id);
        $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics($exam_id);
        if (!empty($diagnostics['snapshot_valid']) && (string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
            $state['question_snapshot_ready'] = true;
            $state['stage_question'] = 'ready';
            return $state;
        }

        $state['active'] = false;
        $state['status'] = self::STATUS_FAILED;
        $state['finished_at'] = current_time('mysql');
        $state['question_snapshot_ready'] = false;
        $state['stage_question'] = 'failed';
        $state['last_message'] = 'One-click pra ujian gagal: Snapshot Soal belum READY setelah dipanaskan.';

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function mark_inactive_state(array $state, string $status, string $message): array
    {
        $state = self::build_state($state);
        $state['active'] = false;
        $state['status'] = $status;
        $state['finished_at'] = current_time('mysql');
        $state['last_tick_at'] = current_time('mysql');
        $state['last_message'] = $message;

        if ($status === self::STATUS_STOPPED) {
            if ((string) ($state['stage_profiles'] ?? '') === 'active') {
                $state['stage_profiles'] = 'stopped';
            }
            if ((string) ($state['stage_login_snapshot'] ?? '') === 'active') {
                $state['stage_login_snapshot'] = 'stopped';
            }
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function run_initial_profile_login_burst(array $state): array
    {
        $deadline = microtime(true) + self::initial_profile_login_burst_seconds();
        $max_batches = self::initial_profile_login_burst_max_batches();
        $burst_count = 0;

        do {
            if (empty($state['active'])) {
                break;
            }

            $state = self::run_snapshot_batch($state, 'start');
            $burst_count++;
        } while (
            !empty($state['active'])
            && $burst_count < $max_batches
            && microtime(true) < $deadline
        );

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function ensure_start_snapshot_ready(array $state, array $exam_row): array
    {
        $state = self::build_state($state);
        $exam_id = (int) ($exam_row['id'] ?? 0);
        if ($exam_id <= 0) {
            $state['active'] = false;
            $state['status'] = self::STATUS_FAILED;
            $state['finished_at'] = current_time('mysql');
            $state['stage_start_snapshot'] = 'failed';
            $state['last_message'] = 'One-click pra ujian gagal: exam belum dipilih untuk Start Snapshot.';
            return $state;
        }

        CBT_REST::warm_exam_start_attempt_snapshot($exam_id);
        $diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id);
        if (!empty($diagnostics['snapshot_valid']) && (string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
            $state['start_snapshot_ready'] = true;
            $state['stage_start_snapshot'] = 'ready';
            return $state;
        }

        $state['active'] = false;
        $state['status'] = self::STATUS_FAILED;
        $state['finished_at'] = current_time('mysql');
        $state['start_snapshot_ready'] = false;
        $state['stage_start_snapshot'] = 'failed';
        $state['last_message'] = 'One-click pra ujian gagal: Start Snapshot belum READY setelah dipanaskan.';

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function ensure_submission_context_ready(array $state, array $exam_row): array
    {
        $state = self::build_state($state);
        $exam_id = (int) ($exam_row['id'] ?? 0);
        if ($exam_id <= 0) {
            $state['submission_context_ready'] = false;
            $state['submission_context_question_count'] = 0;
            $state['submission_context_ready_count'] = 0;
            $state['submission_context_missing_count'] = 0;
            $state['submission_context_invalid_count'] = 0;
            $state['stage_submission_context'] = 'warning';
            return $state;
        }

        if (class_exists('CBT_REST') && method_exists('CBT_REST', 'warm_exam_submission_context_snapshot')) {
            CBT_REST::warm_exam_submission_context_snapshot($exam_id);
        }

        $diagnostics = class_exists('CBT_Question_Submission_Context_Cache')
            ? CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics($exam_id)
            : [
                'question_count' => 0,
                'ready_count' => 0,
                'missing_count' => 0,
                'invalid_count' => 0,
                'snapshot_status' => 'unavailable',
            ];

        $question_count = max(0, (int) ($diagnostics['question_count'] ?? 0));
        $ready_count = max(0, (int) ($diagnostics['ready_count'] ?? 0));
        $missing_count = max(0, (int) ($diagnostics['missing_count'] ?? 0));
        $invalid_count = max(0, (int) ($diagnostics['invalid_count'] ?? 0));
        $snapshot_status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? 'miss'));

        $state['submission_context_question_count'] = $question_count;
        $state['submission_context_ready_count'] = $ready_count;
        $state['submission_context_missing_count'] = $missing_count;
        $state['submission_context_invalid_count'] = $invalid_count;
        $state['submission_context_ready'] = ($question_count > 0 && $ready_count === $question_count);

        if ($state['submission_context_ready']) {
            $state['stage_submission_context'] = 'ready';
            return $state;
        }

        $state['stage_submission_context'] = in_array($snapshot_status, ['unavailable', 'idle', 'warning', 'invalid', 'miss'], true)
            ? 'warning'
            : 'warning';

        if ($state['last_message'] === '' || strpos((string) $state['last_message'], 'Submission Context') === false) {
            $state['last_message'] = sprintf(
                'Submission Context parsial. READY %d/%d · MISS %d · INVALID %d. One-click tetap melanjutkan tahap berikutnya.',
                $ready_count,
                $question_count,
                $missing_count,
                $invalid_count
            );
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function ensure_auto_warm_started(array $state, array $exam_row): array
    {
        $state = self::build_state($state);
        $result = CBT_Exam_Availability_Auto_Warm_Service::start_for_exam($exam_row);
        if (!empty($result['success'])) {
            $state['auto_warm_started'] = true;
            $state['stage_auto_warm'] = 'ready';
            return $state;
        }

        $state['active'] = false;
        $state['status'] = self::STATUS_FAILED;
        $state['finished_at'] = current_time('mysql');
        $state['auto_warm_started'] = false;
        $state['stage_auto_warm'] = 'failed';
        $state['last_message'] = (string) ($result['message'] ?? 'One-click pra ujian gagal saat memulai Auto-Warm Availability.');

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function run_snapshot_batch(array $state, string $source): array
    {
        $state = self::build_state($state);
        $target_student_ids = array_values(array_filter(array_map('absint', (array) ($state['target_student_ids'] ?? []))));
        $total_targets = count($target_student_ids);
        if ($total_targets <= 0) {
            $state['active'] = false;
            $state['status'] = self::STATUS_FAILED;
            $state['finished_at'] = current_time('mysql');
            $state['stage_profiles'] = 'failed';
            $state['last_message'] = 'One-click pra ujian gagal: siswa target tidak ditemukan.';
            return $state;
        }

        $cursor = min(max(0, (int) ($state['profile_cursor'] ?? 0)), $total_targets);
        $batch_student_ids = array_slice($target_student_ids, $cursor, self::PROFILE_BATCH_SIZE);
        $success_count = max(0, (int) ($state['profile_success_count'] ?? 0));
        $failure_count = max(0, (int) ($state['profile_failure_count'] ?? 0));
        $login_success_count = max(0, (int) ($state['login_snapshot_success_count'] ?? 0));
        $login_failure_count = max(0, (int) ($state['login_snapshot_failure_count'] ?? 0));
        $profile_snapshots_by_user = [];
        try {
            $profile_results = CBT_Student_Profile_Cache::warm_snapshot_results($batch_student_ids);
        } catch (Throwable $throwable) {
            $profile_results = [];
        }

        foreach ($batch_student_ids as $user_id) {
            $user_id = absint($user_id);
            if ($user_id <= 0) {
                $failure_count++;
                continue;
            }

            $profile_result = $profile_results[$user_id] ?? null;
            $profile_snapshot = is_array($profile_result['snapshot'] ?? null) ? $profile_result['snapshot'] : [];
            if (!empty($profile_snapshot)) {
                $profile_snapshots_by_user[$user_id] = $profile_snapshot;
            }

            if (!empty($profile_result['ready'])) {
                $success_count++;
            } else {
                $failure_count++;
            }
        }

        try {
            $login_results = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_results($batch_student_ids, 'preflight', $profile_snapshots_by_user);
        } catch (Throwable $throwable) {
            $login_results = [];
        }

        foreach ($batch_student_ids as $user_id) {
            $user_id = absint($user_id);
            if ($user_id <= 0) {
                $login_failure_count++;
                continue;
            }

            $login_result = $login_results[$user_id] ?? null;
            if (!empty($login_result['ready'])) {
                $login_success_count++;
            } else {
                $login_failure_count++;
            }
        }

        $processed_count = $success_count + $failure_count;
        $next_cursor = $cursor + count($batch_student_ids);
        $state['profile_success_count'] = $success_count;
        $state['profile_failure_count'] = $failure_count;
        $state['login_snapshot_success_count'] = $login_success_count;
        $state['login_snapshot_failure_count'] = $login_failure_count;
        $state['profile_cursor'] = min($next_cursor, $total_targets);
        $state['last_tick_at'] = current_time('mysql');
        $submission_context_warning = sanitize_key((string) ($state['stage_submission_context'] ?? 'pending')) === 'warning';
        $submission_context_question_count = max(0, (int) ($state['submission_context_question_count'] ?? 0));
        $submission_context_ready_count = max(0, (int) ($state['submission_context_ready_count'] ?? 0));
        $submission_context_missing_count = max(0, (int) ($state['submission_context_missing_count'] ?? 0));
        $submission_context_invalid_count = max(0, (int) ($state['submission_context_invalid_count'] ?? 0));

        if ($processed_count >= $total_targets) {
            $state['active'] = false;
            $state['finished_at'] = current_time('mysql');
            $has_any_warning = ($failure_count > 0 || $login_failure_count > 0 || $submission_context_warning);
            if ($has_any_warning) {
                $state['status'] = self::STATUS_COMPLETED_WITH_WARNINGS;
                $state['stage_profiles'] = $failure_count > 0 ? 'warning' : 'ready';
                $state['stage_login_snapshot'] = $login_failure_count > 0 ? 'warning' : 'ready';
                $warning_message = sprintf(
                    'One-click pra ujian selesai dengan catatan. Profil siap %d/%d. Login snapshot siap %d/%d. Gagal profil %d. Gagal login snapshot %d.',
                    $success_count,
                    $total_targets,
                    $login_success_count,
                    $total_targets,
                    $failure_count,
                    $login_failure_count
                );
                if ($submission_context_warning) {
                    $warning_message .= sprintf(
                        ' Submission Context READY %d/%d. MISS %d. INVALID %d.',
                        $submission_context_ready_count,
                        $submission_context_question_count,
                        $submission_context_missing_count,
                        $submission_context_invalid_count
                    );
                }
                $state['last_message'] = $warning_message;
            } else {
                $state['status'] = self::STATUS_COMPLETED;
                $state['stage_profiles'] = 'ready';
                $state['stage_login_snapshot'] = 'ready';
                $success_message = sprintf(
                    'One-click pra ujian selesai. Profil siap %d/%d. Login snapshot siap %d/%d.',
                    $success_count,
                    $total_targets,
                    $login_success_count,
                    $total_targets
                );
                if ($submission_context_question_count > 0) {
                    $success_message .= sprintf(
                        ' Submission Context READY %d/%d.',
                        $submission_context_ready_count,
                        $submission_context_question_count
                    );
                }
                $state['last_message'] = $success_message;
            }

            return $state;
        }

        $state['active'] = true;
        $state['status'] = self::STATUS_ACTIVE;
        $state['stage_profiles'] = 'active';
        $state['stage_login_snapshot'] = 'active';
        $state['last_message'] = sprintf(
            '%s one-click memproses %d siswa. Profil siap %d/%d. Login snapshot siap %d/%d. Gagal profil %d. Gagal login snapshot %d.',
            $source === 'start' ? 'Batch awal' : 'Tick',
            count($batch_student_ids),
            $success_count,
            $total_targets,
            $login_success_count,
            $total_targets,
            $failure_count,
            $login_failure_count
        );

        return $state;
    }

    /**
     * @param int[] $user_ids
     */
    private static function prime_user_snapshot_batch_caches(array $user_ids): void
    {
        $user_ids = array_values(array_filter(array_map('absint', $user_ids)));
        if (empty($user_ids)) {
            return;
        }

        if (function_exists('cache_users')) {
            cache_users($user_ids);
        }

        if (function_exists('update_meta_cache')) {
            update_meta_cache('user', $user_ids);
        }
    }

    private static function initial_profile_login_burst_seconds(): float
    {
        $seconds = isset($GLOBALS['cbt_test_preflight_initial_burst_seconds'])
            ? (float) $GLOBALS['cbt_test_preflight_initial_burst_seconds']
            : self::PROFILE_LOGIN_INITIAL_BURST_SECONDS;

        return max(0.0, $seconds);
    }

    private static function initial_profile_login_burst_max_batches(): int
    {
        $max_batches = isset($GLOBALS['cbt_test_preflight_initial_burst_max_batches'])
            ? (int) $GLOBALS['cbt_test_preflight_initial_burst_max_batches']
            : self::PROFILE_LOGIN_INITIAL_BURST_MAX_BATCHES;

        return max(1, $max_batches);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function build_failure_state(array $exam_row, string $message): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $target_student_ids = $exam_id > 0
            ? CBT_Exam_Availability_Auto_Warm_Service::get_target_student_ids_for_exam($exam_row)
            : [];

        return self::build_state([
            'active' => false,
            'status' => self::STATUS_FAILED,
            'session_id' => $exam_id > 0 ? self::generate_session_id($exam_id) : '',
            'exam_id' => $exam_id,
            'exam_title' => self::resolve_exam_title($exam_row),
            'exam_status' => sanitize_key((string) ($exam_row['status'] ?? '')),
            'target_kelas_csv' => sanitize_text_field((string) ($exam_row['target_kelas'] ?? '')),
            'target_student_ids' => $target_student_ids,
            'target_student_count' => count($target_student_ids),
            'profile_cursor' => 0,
            'profile_success_count' => 0,
            'profile_failure_count' => 0,
            'login_snapshot_success_count' => 0,
            'login_snapshot_failure_count' => 0,
            'question_snapshot_ready' => false,
            'start_snapshot_ready' => false,
            'auto_warm_started' => false,
            'started_at' => current_time('mysql'),
            'finished_at' => current_time('mysql'),
            'last_tick_at' => current_time('mysql'),
            'last_message' => $message,
            'stage_question' => 'pending',
            'stage_start_snapshot' => 'pending',
            'stage_profiles' => 'pending',
            'stage_login_snapshot' => 'pending',
            'stage_auto_warm' => 'pending',
        ]);
    }

    private static function should_defer_student_cohort_canonical_scan(): bool
    {
        if (!class_exists('CBT_Student_Cohort_Index_Service') || !method_exists('CBT_Student_Cohort_Index_Service', 'get_health_summary')) {
            return false;
        }

        $summary = CBT_Student_Cohort_Index_Service::get_health_summary();
        return !empty($summary['available']) && empty($summary['ready']);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_exam_row_from_state(array $state): array
    {
        $state = self::build_state($state);

        return [
            'id' => (int) ($state['exam_id'] ?? 0),
            'title' => (string) ($state['exam_title'] ?? ''),
            'status' => (string) ($state['exam_status'] ?? ''),
            'target_kelas' => (string) ($state['target_kelas_csv'] ?? ''),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     * @param array<string,mixed> $runner
     */
    private static function has_pending_work(array $jobs, array $runner): bool
    {
        $runner = self::build_global_runner_state($runner);
        if ((int) ($runner['active_exam_id'] ?? 0) > 0 || !empty($runner['queue_exam_ids'])) {
            return true;
        }

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $status = sanitize_key((string) ($job['status'] ?? self::STATUS_INACTIVE));
            if (in_array($status, [self::STATUS_ACTIVE, self::STATUS_QUEUED], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     */
    private static function count_nonterminal_jobs(array $jobs): int
    {
        $count = 0;
        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $status = sanitize_key((string) ($job['status'] ?? self::STATUS_INACTIVE));
            if (in_array($status, [self::STATUS_ACTIVE, self::STATUS_QUEUED], true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string,mixed> $runner
     */
    private static function queue_position_for_exam(int $exam_id, array $runner): int
    {
        if ($exam_id <= 0) {
            return 0;
        }

        $queue_exam_ids = array_values(array_filter(array_map('absint', (array) ($runner['queue_exam_ids'] ?? []))));
        $position = array_search($exam_id, $queue_exam_ids, true);
        if ($position === false) {
            return 0;
        }

        return ((int) $position) + 1;
    }

    /**
     * @param array<string,mixed> $runner
     * @return array<string,mixed>
     */
    private static function enqueue_runner_exam(array $runner, int $exam_id): array
    {
        $runner = self::build_global_runner_state($runner);
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return $runner;
        }

        if ((int) ($runner['active_exam_id'] ?? 0) === $exam_id) {
            return $runner;
        }

        $queue_exam_ids = array_values(array_filter(array_map('absint', (array) ($runner['queue_exam_ids'] ?? []))));
        if (!in_array($exam_id, $queue_exam_ids, true)) {
            $queue_exam_ids[] = $exam_id;
        }

        $runner['queue_exam_ids'] = $queue_exam_ids;

        return self::build_global_runner_state($runner);
    }

    /**
     * @param array<string,mixed> $runner
     * @return array<string,mixed>
     */
    private static function dequeue_runner_exam(array $runner, int $exam_id): array
    {
        $runner = self::build_global_runner_state($runner);
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return $runner;
        }

        $runner['queue_exam_ids'] = array_values(array_filter(
            array_map('absint', (array) ($runner['queue_exam_ids'] ?? [])),
            static function (int $queued_exam_id) use ($exam_id): bool {
                return $queued_exam_id !== $exam_id;
            }
        ));

        return self::build_global_runner_state($runner);
    }

    /**
     * @param array<string,mixed> $runner
     * @return array<string,mixed>
     */
    private static function release_runner_owner(array $runner, int $exam_id): array
    {
        $runner = self::build_global_runner_state($runner);
        if ((int) ($runner['active_exam_id'] ?? 0) !== absint($exam_id)) {
            return $runner;
        }

        $runner['active_exam_id'] = 0;
        $runner['active_exam_title'] = '';
        $runner['active_layer'] = '';
        $runner['session_id'] = '';
        $runner['cursor'] = 0;
        $runner['last_tick_at'] = current_time('mysql');

        return self::build_global_runner_state($runner);
    }

    /**
     * @param array<string,mixed> $job
     * @param array<string,mixed> $runner
     */
    private static function sync_legacy_state(array $job, array $runner): void
    {
        $jobs = self::get_jobs_state();
        $normalized_job = self::build_state($job);
        $exam_id = (int) ($normalized_job['exam_id'] ?? 0);
        if ($exam_id > 0) {
            $jobs[$exam_id] = $normalized_job;
        }

        self::save_state(self::resolve_legacy_state($jobs, $runner));
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     * @param array<string,mixed> $runner
     * @return array<string,mixed>
     */
    private static function resolve_legacy_state(array $jobs, array $runner): array
    {
        $runner = self::build_global_runner_state($runner);
        $active_exam_id = (int) ($runner['active_exam_id'] ?? 0);
        if ($active_exam_id > 0 && isset($jobs[$active_exam_id]) && is_array($jobs[$active_exam_id])) {
            return self::decorate_legacy_state($jobs[$active_exam_id], $runner);
        }

        foreach ((array) ($runner['queue_exam_ids'] ?? []) as $queued_exam_id) {
            $queued_exam_id = absint($queued_exam_id);
            if ($queued_exam_id > 0 && isset($jobs[$queued_exam_id]) && is_array($jobs[$queued_exam_id])) {
                return self::decorate_legacy_state($jobs[$queued_exam_id], $runner);
            }
        }

        $latest_job = null;
        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            if (!is_array($latest_job)) {
                $latest_job = $job;
                continue;
            }

            $job_tick = trim((string) ($job['last_tick_at'] ?? ''));
            $latest_tick = trim((string) ($latest_job['last_tick_at'] ?? ''));
            if ($job_tick !== '' && ($latest_tick === '' || strcmp($job_tick, $latest_tick) >= 0)) {
                $latest_job = $job;
            }
        }

        if (is_array($latest_job)) {
            return self::decorate_legacy_state($latest_job, $runner);
        }

        return self::build_state([]);
    }

    /**
     * @param array<string,mixed> $job
     * @param array<string,mixed> $runner
     * @return array<string,mixed>
     */
    private static function decorate_legacy_state(array $job, array $runner): array
    {
        $job = self::build_state($job);
        $runner = self::build_global_runner_state($runner);
        $exam_id = (int) ($job['exam_id'] ?? 0);
        $job['queue_position'] = self::queue_position_for_exam($exam_id, $runner);
        $job['queue_total'] = count((array) ($runner['queue_exam_ids'] ?? []));
        $job['global_runner_exam_id'] = (int) ($runner['active_exam_id'] ?? 0);
        $job['global_runner_exam_title'] = (string) ($runner['active_exam_title'] ?? '');
        $job['active_global_layer'] = (int) ($runner['active_exam_id'] ?? 0) === $exam_id
            ? (string) ($runner['active_layer'] ?? '')
            : '';
        $job['active'] = sanitize_key((string) ($job['status'] ?? self::STATUS_INACTIVE)) === self::STATUS_ACTIVE;

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @param array{can_start:bool,message:string,target_student_ids:int[]} $eligibility
     * @param array<string,mixed>|null $existing_job
     * @return array<string,mixed>
     */
    private static function prepare_job_for_start(array $exam_row, array $eligibility, ?array $existing_job): array
    {
        $job = self::initialize_job_state($exam_row, $eligibility);
        $job = self::ensure_question_snapshot_ready($job, $exam_row);
        if ((string) ($job['status'] ?? '') === self::STATUS_FAILED) {
            return $job;
        }

        $job = self::ensure_start_snapshot_ready($job, $exam_row);
        if ((string) ($job['status'] ?? '') === self::STATUS_FAILED) {
            return $job;
        }

        $job = self::ensure_submission_context_ready($job, $exam_row);
        $job = self::refresh_job_global_diff($job);
        $job['last_tick_at'] = current_time('mysql');

        if (!self::job_has_pending_global_work($job)) {
            return self::finalize_completed_job($job);
        }

        $job = self::apply_global_stage_statuses($job, 'pending');
        if (is_array($existing_job) && trim((string) ($existing_job['last_message'] ?? '')) !== '') {
            $job['last_message'] = (string) $existing_job['last_message'];
        } else {
            $job['last_message'] = 'Snapshot exam-local sudah siap. Mode global paralel mulai menyiapkan Snapshot Profil, Login Snapshot, dan Auto-Warm Availability untuk peserta exam ini.';
        }

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @param array{can_start:bool,message:string,target_student_ids:int[]} $eligibility
     * @return array<string,mixed>
     */
    private static function initialize_job_state(array $exam_row, array $eligibility): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $target_student_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($eligibility['target_student_ids'] ?? [])))));
        $now = current_time('mysql');

        return self::build_state([
            'active' => true,
            'status' => self::STATUS_ACTIVE,
            'session_id' => self::generate_session_id($exam_id),
            'exam_id' => $exam_id,
            'exam_title' => self::resolve_exam_title($exam_row),
            'exam_status' => sanitize_key((string) ($exam_row['status'] ?? '')),
            'target_kelas_csv' => sanitize_text_field((string) ($exam_row['target_kelas'] ?? '')),
            'target_student_ids' => $target_student_ids,
            'target_student_count' => count($target_student_ids),
            'profile_cursor' => 0,
            'profile_success_count' => 0,
            'profile_failure_count' => 0,
            'login_snapshot_success_count' => 0,
            'login_snapshot_failure_count' => 0,
            'submission_context_question_count' => 0,
            'submission_context_ready_count' => 0,
            'submission_context_missing_count' => 0,
            'submission_context_invalid_count' => 0,
            'question_snapshot_ready' => false,
            'start_snapshot_ready' => false,
            'submission_context_ready' => false,
            'auto_warm_started' => false,
            'started_at' => $now,
            'finished_at' => '',
            'last_tick_at' => $now,
            'last_message' => 'One-click pra ujian dimulai.',
            'stage_question' => 'pending',
            'stage_start_snapshot' => 'pending',
            'stage_submission_context' => 'pending',
            'stage_profiles' => 'pending',
            'stage_login_snapshot' => 'pending',
            'stage_auto_warm' => 'pending',
            'profiles_ready_count' => 0,
            'profiles_reuse_count' => 0,
            'profiles_pending_count' => count($target_student_ids),
            'profiles_failure_count' => 0,
            'profiles_pending_user_ids' => $target_student_ids,
            'login_ready_count' => 0,
            'login_reuse_count' => 0,
            'login_pending_count' => count($target_student_ids),
            'login_failure_count' => 0,
            'login_pending_user_ids' => $target_student_ids,
            'availability_ready_count' => 0,
            'availability_reuse_count' => 0,
            'availability_pending_count' => count($target_student_ids),
            'availability_failure_count' => 0,
            'availability_pending_user_ids' => $target_student_ids,
        ]);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function refresh_job_global_diff(array $job): array
    {
        $job = self::refresh_profiles_diff($job);
        $job = self::refresh_login_diff($job);
        $job = self::refresh_availability_diff($job);

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function refresh_profiles_diff(array $job): array
    {
        $job = self::build_state($job);
        $target_student_ids = array_values(array_filter(array_map('absint', (array) ($job['target_student_ids'] ?? []))));
        if (empty($target_student_ids)) {
            $job['profiles_ready_count'] = 0;
            $job['profiles_reuse_count'] = 0;
            $job['profiles_pending_count'] = 0;
            $job['profiles_failure_count'] = 0;
            $job['profiles_pending_user_ids'] = [];
            $job['profile_success_count'] = 0;
            $job['profile_failure_count'] = 0;

            return $job;
        }

        $ready_count = 0;
        $failure_count = 0;
        $pending_user_ids = [];

        foreach ($target_student_ids as $user_id) {
            try {
                $diagnostics = CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id);
                $snapshot_status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? 'miss'));
                if ($snapshot_status === 'ready') {
                    $ready_count++;
                } elseif (in_array($snapshot_status, ['invalid', 'unavailable'], true)) {
                    $failure_count++;
                } else {
                    $pending_user_ids[] = $user_id;
                }
            } catch (Throwable $throwable) {
                $failure_count++;
            }
        }

        $job['profiles_ready_count'] = $ready_count;
        $job['profiles_reuse_count'] = $ready_count;
        $job['profiles_pending_count'] = count($pending_user_ids);
        $job['profiles_failure_count'] = $failure_count;
        $job['profiles_pending_user_ids'] = $pending_user_ids;
        $job['profile_success_count'] = $ready_count;
        $job['profile_failure_count'] = $failure_count;

        return $job;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function refresh_login_diff(array $job): array
    {
        $job = self::build_state($job);
        $target_student_ids = array_values(array_filter(array_map('absint', (array) ($job['target_student_ids'] ?? []))));
        if (empty($target_student_ids)) {
            $job['login_ready_count'] = 0;
            $job['login_reuse_count'] = 0;
            $job['login_pending_count'] = 0;
            $job['login_failure_count'] = 0;
            $job['login_pending_user_ids'] = [];
            $job['login_snapshot_success_count'] = 0;
            $job['login_snapshot_failure_count'] = 0;

            return $job;
        }

        $ready_count = 0;
        $failure_count = 0;
        $pending_user_ids = [];

        foreach ($target_student_ids as $user_id) {
            try {
                $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
                $snapshot_status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? 'miss'));
                if ($snapshot_status === 'ready') {
                    $ready_count++;
                } elseif (in_array($snapshot_status, ['invalid', 'unavailable'], true)) {
                    $failure_count++;
                } else {
                    $pending_user_ids[] = $user_id;
                }
            } catch (Throwable $throwable) {
                $failure_count++;
            }
        }

        $job['login_ready_count'] = $ready_count;
        $job['login_reuse_count'] = $ready_count;
        $job['login_pending_count'] = count($pending_user_ids);
        $job['login_failure_count'] = $failure_count;
        $job['login_pending_user_ids'] = $pending_user_ids;
        $job['login_snapshot_success_count'] = $ready_count;
        $job['login_snapshot_failure_count'] = $failure_count;

        return $job;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function refresh_availability_diff(array $job): array
    {
        $job = self::build_state($job);
        $target_student_ids = array_values(array_filter(array_map('absint', (array) ($job['target_student_ids'] ?? []))));
        if (empty($target_student_ids)) {
            $job['availability_ready_count'] = 0;
            $job['availability_reuse_count'] = 0;
            $job['availability_pending_count'] = 0;
            $job['availability_failure_count'] = 0;
            $job['availability_pending_user_ids'] = [];

            return $job;
        }

        $ready_count = 0;
        $failure_count = 0;
        $pending_user_ids = [];

        foreach ($target_student_ids as $user_id) {
            try {
                if (CBT_Exam_Availability_Cache::has_current_prepared_snapshot($user_id)) {
                    $ready_count++;
                } else {
                    $pending_user_ids[] = $user_id;
                }
            } catch (Throwable $throwable) {
                $failure_count++;
            }
        }

        $job['availability_ready_count'] = $ready_count;
        $job['availability_reuse_count'] = $ready_count;
        $job['availability_pending_count'] = count($pending_user_ids);
        $job['availability_failure_count'] = $failure_count;
        $job['availability_pending_user_ids'] = $pending_user_ids;

        return $job;
    }

    /**
     * @param array<string,mixed> $job
     */
    private static function job_has_pending_global_work(array $job): bool
    {
        $job = self::build_state($job);

        return max(0, (int) ($job['profiles_pending_count'] ?? 0)) > 0
            || max(0, (int) ($job['login_pending_count'] ?? 0)) > 0
            || max(0, (int) ($job['availability_pending_count'] ?? 0)) > 0;
    }

    /**
     * @param array<string,mixed> $job
     * @param array<string,mixed> $runner
     * @return array<string,mixed>
     */
    private static function mark_job_queued(array $job, array $runner): array
    {
        $job = self::build_state($job);
        $job['active'] = false;
        $job['status'] = self::STATUS_QUEUED;
        $job['queue_position'] = count((array) ($runner['queue_exam_ids'] ?? [])) + 1;
        $job['queue_total'] = count((array) ($runner['queue_exam_ids'] ?? [])) + 1;
        $job['active_global_layer'] = '';
        $job['last_tick_at'] = current_time('mysql');
        $job = self::apply_global_stage_statuses($job, 'queued');
        $job['last_message'] = 'Snapshot exam-local sudah siap. Mode global paralel menunggu giliran setelah exam aktif selesai.';

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function mark_job_stopped(array $job, string $message): array
    {
        $job = self::build_state($job);
        $job['active'] = false;
        $job['status'] = self::STATUS_STOPPED;
        $job['finished_at'] = current_time('mysql');
        $job['last_tick_at'] = current_time('mysql');
        $job['queue_position'] = 0;
        $job['active_global_layer'] = '';
        if (in_array((string) ($job['stage_profiles'] ?? ''), ['active', 'queued', 'pending'], true)
            && max(0, (int) ($job['profiles_pending_count'] ?? 0)) > 0) {
            $job['stage_profiles'] = 'stopped';
        }
        if (in_array((string) ($job['stage_login_snapshot'] ?? ''), ['active', 'queued', 'pending'], true)
            && max(0, (int) ($job['login_pending_count'] ?? 0)) > 0) {
            $job['stage_login_snapshot'] = 'stopped';
        }
        if (in_array((string) ($job['stage_auto_warm'] ?? ''), ['active', 'queued', 'pending'], true)
            && max(0, (int) ($job['availability_pending_count'] ?? 0)) > 0) {
            $job['stage_auto_warm'] = 'stopped';
        }
        $job['last_message'] = $message;

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function finalize_completed_job(array $job): array
    {
        $job = self::build_state($job);
        $job['active'] = false;
        $job['queue_position'] = 0;
        $job['active_global_layer'] = '';
        $job['finished_at'] = current_time('mysql');
        $job['last_tick_at'] = current_time('mysql');
        $job = self::apply_global_stage_statuses($job, 'complete');

        $has_warning = sanitize_key((string) ($job['stage_submission_context'] ?? '')) === 'warning'
            || max(0, (int) ($job['profiles_failure_count'] ?? 0)) > 0
            || max(0, (int) ($job['login_failure_count'] ?? 0)) > 0
            || max(0, (int) ($job['availability_failure_count'] ?? 0)) > 0;

        $job['status'] = $has_warning ? self::STATUS_COMPLETED_WITH_WARNINGS : self::STATUS_COMPLETED;
        if ($has_warning) {
            $job['last_message'] = sprintf(
                'One-click pra ujian selesai dengan catatan. Profil siap %d/%d. Login siap %d/%d. Availability siap %d/%d.',
                max(0, (int) ($job['profiles_ready_count'] ?? 0)),
                max(0, (int) ($job['target_student_count'] ?? 0)),
                max(0, (int) ($job['login_ready_count'] ?? 0)),
                max(0, (int) ($job['target_student_count'] ?? 0)),
                max(0, (int) ($job['availability_ready_count'] ?? 0)),
                max(0, (int) ($job['target_student_count'] ?? 0))
            );
        } else {
            $job['last_message'] = 'One-click pra ujian selesai. Snapshot exam-local dan layer global siap dipakai.';
        }

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function apply_global_stage_statuses(array $job, string $mode, string $active_layer = ''): array
    {
        $job = self::build_state($job);
        $mode = sanitize_key($mode);
        $active_layer = sanitize_key($active_layer);

        $job['stage_profiles'] = self::resolve_global_stage_status(
            max(0, (int) ($job['profiles_pending_count'] ?? 0)),
            max(0, (int) ($job['profiles_failure_count'] ?? 0)),
            $mode,
            $active_layer === self::GLOBAL_LAYER_PROFILES
        );
        $job['stage_login_snapshot'] = self::resolve_global_stage_status(
            max(0, (int) ($job['login_pending_count'] ?? 0)),
            max(0, (int) ($job['login_failure_count'] ?? 0)),
            $mode,
            $active_layer === self::GLOBAL_LAYER_LOGIN
        );
        $job['stage_auto_warm'] = self::resolve_global_stage_status(
            max(0, (int) ($job['availability_pending_count'] ?? 0)),
            max(0, (int) ($job['availability_failure_count'] ?? 0)),
            $mode,
            $active_layer === self::GLOBAL_LAYER_AVAILABILITY
        );

        return self::build_state($job);
    }

    private static function resolve_global_stage_status(int $pending_count, int $failure_count, string $mode, bool $is_active_layer): string
    {
        if ($pending_count <= 0) {
            return $failure_count > 0 ? 'warning' : 'ready';
        }

        if ($mode === 'queued') {
            return self::STATUS_QUEUED;
        }

        if ($mode === 'active' && $is_active_layer) {
            return self::STATUS_ACTIVE;
        }

        return 'pending';
    }

    /**
     * @param array<int,array<string,mixed>> $jobs
     * @param array<string,mixed> $runner
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>}
     */
    private static function advance_global_runner(array $jobs, array $runner, string $source): array
    {
        $runner = self::build_global_runner_state($runner);
        $source = sanitize_key($source);
        $guard = 0;

        while ($guard < (self::MAX_NONTERMINAL_EXAMS + 5)) {
            $guard++;
            $active_exam_id = (int) ($runner['active_exam_id'] ?? 0);

            if ($active_exam_id <= 0) {
                $queue_exam_ids = array_values(array_filter(array_map('absint', (array) ($runner['queue_exam_ids'] ?? []))));
                if (empty($queue_exam_ids)) {
                    $runner['active_layer'] = '';
                    $runner['active_exam_title'] = '';
                    $runner['session_id'] = '';
                    $runner['cursor'] = 0;

                    return [$jobs, self::build_global_runner_state($runner)];
                }

                $next_exam_id = array_shift($queue_exam_ids);
                $runner['queue_exam_ids'] = $queue_exam_ids;
                if ($next_exam_id <= 0 || !isset($jobs[$next_exam_id]) || !is_array($jobs[$next_exam_id])) {
                    continue;
                }

                $job = self::refresh_job_global_diff($jobs[$next_exam_id]);
                if (!self::job_has_pending_global_work($job)) {
                    $jobs[$next_exam_id] = self::finalize_completed_job($job);
                    continue;
                }

                $runner['active_exam_id'] = $next_exam_id;
                $runner['active_exam_title'] = (string) ($job['exam_title'] ?? ('Exam #' . $next_exam_id));
                $runner['session_id'] = (string) ($job['session_id'] ?? self::generate_session_id($next_exam_id));
                $runner['last_tick_at'] = current_time('mysql');
                $job['active'] = true;
                $job['status'] = self::STATUS_ACTIVE;
                $job['queue_position'] = 0;
                $job['queue_total'] = count((array) ($runner['queue_exam_ids'] ?? []));
                $jobs[$next_exam_id] = self::build_state($job);
                $active_exam_id = $next_exam_id;
            }

            if (!isset($jobs[$active_exam_id]) || !is_array($jobs[$active_exam_id])) {
                $runner = self::release_runner_owner($runner, $active_exam_id);
                continue;
            }

            $job = self::build_state($jobs[$active_exam_id]);
            if (!self::job_has_pending_global_work($job)) {
                $jobs[$active_exam_id] = self::finalize_completed_job($job);
                $runner = self::release_runner_owner($runner, $active_exam_id);
                continue;
            }

            if (!self::job_has_pending_global_work($job)) {
                $jobs[$active_exam_id] = self::finalize_completed_job($job);
                $runner = self::release_runner_owner($runner, $active_exam_id);
                continue;
            }

            $runner['active_layer'] = self::GLOBAL_LAYER_PARALLEL;
            $runner['last_tick_at'] = current_time('mysql');
            $job['status'] = self::STATUS_ACTIVE;
            $job['active'] = true;
            $job['active_global_layer'] = self::GLOBAL_LAYER_PARALLEL;
            $job = self::run_parallel_global_lanes($job, $source);

            $jobs[$active_exam_id] = self::build_state($job);
            $runner = self::build_global_runner_state($runner);

            $updated_job = self::build_state($jobs[$active_exam_id]);
            if (!self::job_has_pending_global_work($updated_job)) {
                $jobs[$active_exam_id] = self::finalize_completed_job($updated_job);
                $runner = self::release_runner_owner($runner, $active_exam_id);
                continue;
            }

            return [$jobs, $runner];
        }

        return [$jobs, self::build_global_runner_state($runner)];
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function run_parallel_global_lanes(array $job, string $source): array
    {
        $job = self::build_state($job);
        $source = sanitize_key($source);

        if (max(0, (int) ($job['profiles_pending_count'] ?? 0)) > 0) {
            $job = $source === 'start'
                ? self::run_profiles_burst($job)
                : self::run_profiles_batch($job);
        }

        if (max(0, (int) ($job['login_pending_count'] ?? 0)) > 0) {
            $job = $source === 'start'
                ? self::run_login_burst($job)
                : self::run_login_batch($job);
        }

        if (max(0, (int) ($job['availability_pending_count'] ?? 0)) > 0) {
            $job = self::run_availability_step($job);
        }

        $job['active'] = true;
        $job['status'] = self::STATUS_ACTIVE;
        $job['active_global_layer'] = self::GLOBAL_LAYER_PARALLEL;
        $job['last_tick_at'] = current_time('mysql');
        $job = self::apply_parallel_stage_statuses($job);
        $job['last_message'] = self::build_parallel_progress_message($job, $source, (string) ($job['last_message'] ?? ''));

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     */
    private static function next_pending_global_layer(array $job): string
    {
        $job = self::build_state($job);
        if (max(0, (int) ($job['profiles_pending_count'] ?? 0)) > 0) {
            return self::GLOBAL_LAYER_PROFILES;
        }

        if (max(0, (int) ($job['login_pending_count'] ?? 0)) > 0) {
            return self::GLOBAL_LAYER_LOGIN;
        }

        if (max(0, (int) ($job['availability_pending_count'] ?? 0)) > 0) {
            return self::GLOBAL_LAYER_AVAILABILITY;
        }

        return '';
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function run_profiles_burst(array $job): array
    {
        $deadline = microtime(true) + self::initial_profile_login_burst_seconds();
        $max_batches = self::initial_profile_login_burst_max_batches();
        $burst_count = 0;

        do {
            $job = self::run_profiles_batch($job);
            $burst_count++;
        } while (
            max(0, (int) ($job['profiles_pending_count'] ?? 0)) > 0
            && $burst_count < $max_batches
            && microtime(true) < $deadline
        );

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function run_profiles_batch(array $job): array
    {
        $job = self::build_state($job);
        $pending_user_ids = array_values(array_filter(array_map('absint', (array) ($job['profiles_pending_user_ids'] ?? []))));
        if (empty($pending_user_ids)) {
            return self::apply_global_stage_statuses($job, 'complete');
        }

        $batch_user_ids = array_slice($pending_user_ids, 0, self::PROFILE_BATCH_SIZE);
        $remaining_user_ids = array_slice($pending_user_ids, count($batch_user_ids));
        $success_count = 0;
        $failure_count = 0;

        try {
            $results = CBT_Student_Profile_Cache::warm_snapshot_results($batch_user_ids);
        } catch (Throwable $throwable) {
            $results = [];
        }

        foreach ($batch_user_ids as $user_id) {
            if (!empty($results[$user_id]['ready'])) {
                $success_count++;
            } else {
                $failure_count++;
            }
        }

        $job['profiles_pending_user_ids'] = $remaining_user_ids;
        $job['profiles_pending_count'] = count($remaining_user_ids);
        $job['profiles_ready_count'] = max(0, (int) ($job['profiles_ready_count'] ?? 0)) + $success_count;
        $job['profiles_failure_count'] = max(0, (int) ($job['profiles_failure_count'] ?? 0)) + $failure_count;
        $job['profile_success_count'] = $job['profiles_ready_count'];
        $job['profile_failure_count'] = $job['profiles_failure_count'];
        $job['last_tick_at'] = current_time('mysql');
        $job = self::apply_global_stage_statuses($job, 'active', self::GLOBAL_LAYER_PROFILES);
        $job['last_message'] = sprintf(
            'Snapshot exam-local siap. Profil %d/%d siap. Reuse %d · Pending %d · Gagal %d.',
            max(0, (int) ($job['profiles_ready_count'] ?? 0)),
            max(0, (int) ($job['target_student_count'] ?? 0)),
            max(0, (int) ($job['profiles_reuse_count'] ?? 0)),
            max(0, (int) ($job['profiles_pending_count'] ?? 0)),
            max(0, (int) ($job['profiles_failure_count'] ?? 0))
        );

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function run_login_burst(array $job): array
    {
        $deadline = microtime(true) + self::initial_profile_login_burst_seconds();
        $max_batches = self::initial_profile_login_burst_max_batches();
        $burst_count = 0;

        do {
            $job = self::run_login_batch($job);
            $burst_count++;
        } while (
            max(0, (int) ($job['login_pending_count'] ?? 0)) > 0
            && $burst_count < $max_batches
            && microtime(true) < $deadline
        );

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function run_login_batch(array $job): array
    {
        $job = self::build_state($job);
        $pending_user_ids = array_values(array_filter(array_map('absint', (array) ($job['login_pending_user_ids'] ?? []))));
        if (empty($pending_user_ids)) {
            return self::apply_global_stage_statuses($job, 'complete');
        }

        $batch_user_ids = array_slice($pending_user_ids, 0, self::PROFILE_BATCH_SIZE);
        $remaining_user_ids = array_slice($pending_user_ids, count($batch_user_ids));
        $success_count = 0;
        $failure_count = 0;
        try {
            $results = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_results($batch_user_ids, 'preflight');
        } catch (Throwable $throwable) {
            $results = [];
        }

        foreach ($batch_user_ids as $user_id) {
            if (!empty($results[$user_id]['ready'])) {
                $success_count++;
            } else {
                $failure_count++;
            }
        }

        $job['login_pending_user_ids'] = $remaining_user_ids;
        $job['login_pending_count'] = count($remaining_user_ids);
        $job['login_ready_count'] = max(0, (int) ($job['login_ready_count'] ?? 0)) + $success_count;
        $job['login_failure_count'] = max(0, (int) ($job['login_failure_count'] ?? 0)) + $failure_count;
        $job['login_snapshot_success_count'] = $job['login_ready_count'];
        $job['login_snapshot_failure_count'] = $job['login_failure_count'];
        $job['last_tick_at'] = current_time('mysql');
        $job = self::apply_global_stage_statuses($job, 'active', self::GLOBAL_LAYER_LOGIN);
        $job['last_message'] = sprintf(
            'Snapshot exam-local siap. Login %d/%d siap. Reuse %d · Pending %d · Gagal %d.',
            max(0, (int) ($job['login_ready_count'] ?? 0)),
            max(0, (int) ($job['target_student_count'] ?? 0)),
            max(0, (int) ($job['login_reuse_count'] ?? 0)),
            max(0, (int) ($job['login_pending_count'] ?? 0)),
            max(0, (int) ($job['login_failure_count'] ?? 0))
        );

        return self::build_state($job);
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function apply_parallel_stage_statuses(array $job): array
    {
        $job = self::build_state($job);
        $job['stage_profiles'] = self::resolve_parallel_stage_status(
            max(0, (int) ($job['profiles_pending_count'] ?? 0)),
            max(0, (int) ($job['profiles_failure_count'] ?? 0)),
            (string) ($job['stage_profiles'] ?? 'pending')
        );
        $job['stage_login_snapshot'] = self::resolve_parallel_stage_status(
            max(0, (int) ($job['login_pending_count'] ?? 0)),
            max(0, (int) ($job['login_failure_count'] ?? 0)),
            (string) ($job['stage_login_snapshot'] ?? 'pending')
        );
        $job['stage_auto_warm'] = self::resolve_parallel_stage_status(
            max(0, (int) ($job['availability_pending_count'] ?? 0)),
            max(0, (int) ($job['availability_failure_count'] ?? 0)),
            (string) ($job['stage_auto_warm'] ?? 'pending')
        );

        return self::build_state($job);
    }

    private static function resolve_parallel_stage_status(int $pending_count, int $failure_count, string $current_stage): string
    {
        $current_stage = sanitize_key($current_stage);
        if ($pending_count <= 0) {
            return $failure_count > 0 ? 'warning' : 'ready';
        }

        if ($current_stage === self::STATUS_QUEUED) {
            return self::STATUS_QUEUED;
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * @param array<string,mixed> $job
     */
    private static function build_parallel_progress_message(array $job, string $source, string $lead_message = ''): string
    {
        $job = self::build_state($job);
        $source = sanitize_key($source);
        $lead_message = trim($lead_message);
        $summary = sprintf(
            '%s mode paralel Aggressive 150+. Profil %d/%d siap (pending %d, gagal %d). Login snapshot %d/%d siap (pending %d, gagal %d). Availability %d/%d siap (pending %d, gagal %d).',
            $source === 'start' ? 'Burst awal' : 'Tick',
            max(0, (int) ($job['profiles_ready_count'] ?? 0)),
            max(0, (int) ($job['target_student_count'] ?? 0)),
            max(0, (int) ($job['profiles_pending_count'] ?? 0)),
            max(0, (int) ($job['profiles_failure_count'] ?? 0)),
            max(0, (int) ($job['login_ready_count'] ?? 0)),
            max(0, (int) ($job['target_student_count'] ?? 0)),
            max(0, (int) ($job['login_pending_count'] ?? 0)),
            max(0, (int) ($job['login_failure_count'] ?? 0)),
            max(0, (int) ($job['availability_ready_count'] ?? 0)),
            max(0, (int) ($job['target_student_count'] ?? 0)),
            max(0, (int) ($job['availability_pending_count'] ?? 0)),
            max(0, (int) ($job['availability_failure_count'] ?? 0))
        );

        if ($lead_message === '') {
            return $summary;
        }

        return $lead_message . ' ' . $summary;
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function run_availability_step(array $job): array
    {
        $job = self::refresh_availability_diff($job);
        $job['last_tick_at'] = current_time('mysql');

        if (max(0, (int) ($job['availability_pending_count'] ?? 0)) <= 0) {
            $auto_warm_state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
            if (!empty($auto_warm_state['active']) && (int) ($auto_warm_state['exam_id'] ?? 0) === (int) ($job['exam_id'] ?? 0)) {
                CBT_Exam_Availability_Auto_Warm_Service::stop_for_exam(self::build_exam_row_from_state($job));
            }
            $job = self::apply_global_stage_statuses($job, 'complete');
            $job['last_message'] = sprintf(
                'Availability %d/%d siap. Layer global exam ini sudah lengkap.',
                max(0, (int) ($job['availability_ready_count'] ?? 0)),
                max(0, (int) ($job['target_student_count'] ?? 0))
            );

            return self::build_state($job);
        }

        $exam_row = self::build_exam_row_from_state($job);
        $auto_warm_state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        $auto_warm_exam_id = (int) ($auto_warm_state['exam_id'] ?? 0);
        if (!empty($auto_warm_state['active']) && $auto_warm_exam_id > 0 && $auto_warm_exam_id !== (int) ($job['exam_id'] ?? 0)) {
            $job = self::apply_global_stage_statuses($job, 'active', self::GLOBAL_LAYER_AVAILABILITY);
            $job['stage_auto_warm'] = self::STATUS_QUEUED;
            $job['last_message'] = sprintf(
                'Availability menunggu auto-warm exam lain selesai: %s.',
                (string) ($auto_warm_state['exam_title'] ?? ('Exam #' . $auto_warm_exam_id))
            );

            return self::build_state($job);
        }

        if (!empty($auto_warm_state['active']) && $auto_warm_exam_id === (int) ($job['exam_id'] ?? 0)) {
            $job['auto_warm_started'] = true;
            $job = self::apply_global_stage_statuses($job, 'active', self::GLOBAL_LAYER_AVAILABILITY);
            $job['last_message'] = sprintf(
                'Availability %d/%d siap. Menunggu auto-warm menyelesaikan %d siswa tersisa.',
                max(0, (int) ($job['availability_ready_count'] ?? 0)),
                max(0, (int) ($job['target_student_count'] ?? 0)),
                max(0, (int) ($job['availability_pending_count'] ?? 0))
            );

            return self::build_state($job);
        }

        $result = CBT_Exam_Availability_Auto_Warm_Service::start_for_exam($exam_row);
        if (empty($result['success'])) {
            $job['availability_failure_count'] = max(0, (int) ($job['availability_failure_count'] ?? 0)) + max(0, (int) ($job['availability_pending_count'] ?? 0));
            $job['availability_pending_count'] = 0;
            $job['availability_pending_user_ids'] = [];
            $job['auto_warm_started'] = false;
            $job = self::apply_global_stage_statuses($job, 'complete');
            $job['last_message'] = (string) ($result['message'] ?? 'Gagal memulai auto-warm availability.');

            return self::build_state($job);
        }

        $job['auto_warm_started'] = true;
        $job = self::refresh_availability_diff($job);
        if (max(0, (int) ($job['availability_pending_count'] ?? 0)) <= 0) {
            CBT_Exam_Availability_Auto_Warm_Service::stop_for_exam($exam_row);
            $job = self::apply_global_stage_statuses($job, 'complete');
            $job['last_message'] = sprintf(
                'Availability %d/%d siap. Auto-warm selesai di batch awal.',
                max(0, (int) ($job['availability_ready_count'] ?? 0)),
                max(0, (int) ($job['target_student_count'] ?? 0))
            );

            return self::build_state($job);
        }

        $job = self::apply_global_stage_statuses($job, 'active', self::GLOBAL_LAYER_AVAILABILITY);
        $job['last_message'] = sprintf(
            'Availability %d/%d siap. Auto-warm aktif untuk %d siswa tersisa.',
            max(0, (int) ($job['availability_ready_count'] ?? 0)),
            max(0, (int) ($job['target_student_count'] ?? 0)),
            max(0, (int) ($job['availability_pending_count'] ?? 0))
        );

        return self::build_state($job);
    }

    private static function resolve_exam_title(array $exam_row): string
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $title = trim((string) ($exam_row['title'] ?? ''));

        return $title !== '' ? $title : ($exam_id > 0 ? 'Exam #' . $exam_id : 'Exam belum dipilih');
    }

    /**
     * @return array{label:string,tone:string}
     */
    private static function build_status_meta(string $status): array
    {
        switch (sanitize_key($status)) {
            case self::STATUS_ACTIVE:
                return ['label' => 'AKTIF', 'tone' => 'success'];
            case self::STATUS_QUEUED:
                return ['label' => 'MENUNGGU', 'tone' => 'warning'];
            case self::STATUS_STOPPED:
                return ['label' => 'BERHENTI', 'tone' => 'warning'];
            case self::STATUS_COMPLETED:
                return ['label' => 'SELESAI', 'tone' => 'success'];
            case self::STATUS_COMPLETED_WITH_WARNINGS:
                return ['label' => 'SELESAI DENGAN CATATAN', 'tone' => 'warning'];
            case self::STATUS_FAILED:
                return ['label' => 'GAGAL', 'tone' => 'error'];
            case self::STATUS_INACTIVE:
            default:
                return ['label' => 'NONAKTIF', 'tone' => 'warning'];
        }
    }

    /**
     * @return array{label:string,tone:string}
     */
    private static function build_stage_meta(string $status, ?string $readyLabel = null): array
    {
        switch (sanitize_key($status)) {
            case 'ready':
                return ['label' => $readyLabel !== null ? $readyLabel : 'SIAP', 'tone' => 'success'];
            case self::STATUS_QUEUED:
            case 'queued':
                return ['label' => 'MENUNGGU', 'tone' => 'warning'];
            case 'warning':
                return ['label' => 'SELESAI DENGAN CATATAN', 'tone' => 'warning'];
            case 'active':
                return ['label' => 'AKTIF', 'tone' => 'success'];
            case 'failed':
                return ['label' => 'GAGAL', 'tone' => 'error'];
            case 'stopped':
                return ['label' => 'BERHENTI', 'tone' => 'warning'];
            case 'expired':
                return ['label' => 'EXPIRED', 'tone' => 'warning'];
            case 'completed':
                return ['label' => 'SELESAI', 'tone' => 'success'];
            case 'completed_with_warnings':
                return ['label' => 'SELESAI DENGAN CATATAN', 'tone' => 'warning'];
            case 'inactive':
            case 'pending':
            default:
                return ['label' => 'BELUM', 'tone' => 'warning'];
        }
    }

    private static function generate_session_id(int $exam_id): string
    {
        return 'preflight-' . $exam_id . '-' . (int) current_time('timestamp') . '-' . wp_rand(100, 999);
    }

    private static function ensure_tick_event(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
    }

    private static function clear_tick_event(): void
    {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
