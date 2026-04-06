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
    private const STATUS_ACTIVE = 'active';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_COMPLETED_WITH_WARNINGS = 'completed_with_warnings';
    private const STATUS_FAILED = 'failed';
    private const STATUS_INACTIVE = 'inactive';
    private const PROFILE_BATCH_SIZE = 50;

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
            $exam_title = self::resolve_exam_title($exam_row);
            $state = self::get_state();
            $active_exam_id = (int) ($state['exam_id'] ?? 0);
            $is_same_exam_state = $exam_id > 0 && !empty($state['active']) && $active_exam_id === $exam_id;

            if (!empty($state['active']) && $active_exam_id > 0 && !$is_same_exam_state) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        'One-click pra ujian exam lain masih aktif: %s.',
                        (string) ($state['exam_title'] ?? ('Exam #' . $active_exam_id))
                    ),
                    'state' => $state,
                ];
            }

            $eligibility = self::evaluate_exam_eligibility($exam_row);
            if (empty($eligibility['can_start'])) {
                $failed_state = self::build_failure_state($exam_row, (string) ($eligibility['message'] ?? 'Exam belum memenuhi syarat one-click pra ujian.'));
                self::save_state($failed_state);
                self::clear_tick_event();

                return [
                    'success' => false,
                    'message' => (string) ($failed_state['last_message'] ?? 'Exam belum memenuhi syarat one-click pra ujian.'),
                    'state' => $failed_state,
                ];
            }

            $target_student_ids = array_values(array_map('intval', (array) ($eligibility['target_student_ids'] ?? [])));
            if ($is_same_exam_state) {
                $state['active'] = true;
                $state['status'] = self::STATUS_ACTIVE;
                $state['exam_id'] = $exam_id;
                $state['exam_title'] = $exam_title;
                $state['exam_status'] = sanitize_key((string) ($exam_row['status'] ?? ''));
                $state['target_kelas_csv'] = sanitize_text_field((string) ($exam_row['target_kelas'] ?? ''));
                $state['target_student_ids'] = $target_student_ids;
                $state['target_student_count'] = count($target_student_ids);
                $state['finished_at'] = '';
                $state['last_message'] = 'Melanjutkan one-click pra ujian untuk exam ini.';
                if ((string) ($state['started_at'] ?? '') === '') {
                    $state['started_at'] = current_time('mysql');
                }
            } else {
                $state = self::build_state([
                    'active' => true,
                    'status' => self::STATUS_ACTIVE,
                    'session_id' => self::generate_session_id($exam_id),
                    'exam_id' => $exam_id,
                    'exam_title' => $exam_title,
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
                    'started_at' => current_time('mysql'),
                    'finished_at' => '',
                    'last_tick_at' => '',
                    'last_message' => 'Menyiapkan one-click pra ujian.',
                    'stage_question' => 'pending',
                    'stage_start_snapshot' => 'pending',
                    'stage_submission_context' => 'pending',
                    'stage_profiles' => 'pending',
                    'stage_login_snapshot' => 'pending',
                    'stage_auto_warm' => 'pending',
                ]);
            }

            $state = self::ensure_question_snapshot_ready($state, $exam_row);
            if ((string) ($state['status'] ?? '') === self::STATUS_FAILED) {
                self::save_state($state);
                self::clear_tick_event();

                return [
                    'success' => false,
                    'message' => (string) ($state['last_message'] ?? 'One-click pra ujian gagal saat menyiapkan Snapshot Soal.'),
                    'state' => $state,
                ];
            }

            $state = self::ensure_start_snapshot_ready($state, $exam_row);
            if ((string) ($state['status'] ?? '') === self::STATUS_FAILED) {
                self::save_state($state);
                self::clear_tick_event();

                return [
                    'success' => false,
                    'message' => (string) ($state['last_message'] ?? 'One-click pra ujian gagal saat menyiapkan Start Snapshot.'),
                    'state' => $state,
                ];
            }

            $state = self::ensure_submission_context_ready($state, $exam_row);
            $state = self::run_snapshot_batch($state, $is_same_exam_state ? 'resume' : 'start');
            if (empty($state['active'])) {
                $state = self::ensure_auto_warm_started($state, $exam_row);
                if ((string) ($state['status'] ?? '') === self::STATUS_FAILED) {
                    self::save_state($state);
                    self::clear_tick_event();

                    return [
                        'success' => false,
                        'message' => (string) ($state['last_message'] ?? 'One-click pra ujian gagal saat memulai Auto-Warm Availability.'),
                        'state' => $state,
                    ];
                }
            }
            self::save_state($state);

            if (!empty($state['active'])) {
                self::ensure_tick_event();
            } else {
                self::clear_tick_event();
            }

            return [
                'success' => true,
                'message' => (string) ($state['last_message'] ?? 'One-click pra ujian dijalankan.'),
                'state' => $state,
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
            $state = self::get_state();
            if (empty($state['active'])) {
                self::clear_tick_event();
                return $state;
            }

            $state = self::run_snapshot_batch($state, 'tick');
            if (empty($state['active'])) {
                $exam_row = self::build_exam_row_from_state($state);
                $state = self::ensure_auto_warm_started($state, $exam_row);
            }
            self::save_state($state);

            if (!empty($state['active'])) {
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
     * @return array<string,mixed>
     */
    public static function get_state(): array
    {
        $state = get_option(self::OPTION_STATE, []);
        return self::build_state(is_array($state) ? $state : []);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function get_exam_panel_context(array $exam_row, bool $question_snapshot_ready = false, bool $start_snapshot_ready = false): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $exam_title = self::resolve_exam_title($exam_row);
        $state = self::get_state();
        $active_exam_id = (int) ($state['exam_id'] ?? 0);
        $is_same_exam_state = $exam_id > 0 && $active_exam_id === $exam_id;
        $has_blocking_preflight = !empty($state['active']) && !$is_same_exam_state && $active_exam_id > 0;
        $target_kelas = CBT_Exam_Availability_Auto_Warm_Service::get_target_kelas_for_exam($exam_row);
        $resolved_target_student_ids = CBT_Exam_Availability_Auto_Warm_Service::get_target_student_ids_for_exam($exam_row);
        $target_student_count = $is_same_exam_state
            ? max(0, (int) ($state['target_student_count'] ?? count($resolved_target_student_ids)))
            : count($resolved_target_student_ids);
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
        $profile_success_count = $is_same_exam_state ? max(0, (int) ($state['profile_success_count'] ?? 0)) : 0;
        $profile_failure_count = $is_same_exam_state ? max(0, (int) ($state['profile_failure_count'] ?? 0)) : 0;
        $profile_processed_count = $profile_success_count + $profile_failure_count;
        $login_snapshot_success_count = $is_same_exam_state ? max(0, (int) ($state['login_snapshot_success_count'] ?? 0)) : 0;
        $login_snapshot_failure_count = $is_same_exam_state ? max(0, (int) ($state['login_snapshot_failure_count'] ?? 0)) : 0;
        $login_snapshot_missing_count = max(0, $target_student_count - $login_snapshot_success_count);
        $submission_context_question_count = $is_same_exam_state ? max(0, (int) ($state['submission_context_question_count'] ?? 0)) : 0;
        $submission_context_ready_count = $is_same_exam_state ? max(0, (int) ($state['submission_context_ready_count'] ?? 0)) : 0;
        $submission_context_missing_count = $is_same_exam_state ? max(0, (int) ($state['submission_context_missing_count'] ?? 0)) : 0;
        $submission_context_invalid_count = $is_same_exam_state ? max(0, (int) ($state['submission_context_invalid_count'] ?? 0)) : 0;
        $profile_cursor = $is_same_exam_state ? max(0, (int) ($state['profile_cursor'] ?? 0)) : 0;
        $can_start = $exam_id > 0
            && !$has_blocking_preflight
            && $blocking_auto_warm_exam_id <= 0
            && $exam_status === 'published'
            && !empty($target_kelas)
            && $target_student_count > 0
            && $question_cache_ready
            && $start_cache_ready
            && $availability_cache_ready
            && $profile_cache_ready
            && $rest_warm_ready
            && $start_warm_ready;

        $message = '';
        if ($has_blocking_preflight) {
            $message = sprintf(
                'One-click pra ujian saat ini aktif untuk exam lain: %s.',
                (string) ($state['exam_title'] ?? ('Exam #' . $active_exam_id))
            );
        } elseif ($blocking_auto_warm_exam_id > 0) {
            $message = sprintf('Auto-Warm Availability aktif untuk exam lain: %s.', $blocking_auto_warm_exam_title);
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
            $message = 'Belum ada siswa target yang cocok dengan target_kelas exam ini.';
        } elseif ($is_same_exam_state && (string) ($state['last_message'] ?? '') !== '') {
            $message = (string) $state['last_message'];
        } else {
            $message = 'One-click memakai blocker dan warning kesiapan yang sama, lalu menyiapkan Snapshot Soal, Start Snapshot, Submission Context, Snapshot Profil, Login Snapshot, dan Auto-Warm Availability dari satu tombol.';
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

        $auto_warm_stage = 'pending';
        if ($is_same_exam_state && (string) ($state['stage_auto_warm'] ?? '') === 'failed') {
            $auto_warm_stage = 'failed';
        } elseif (!empty($state['auto_warm_started']) || $blocking_auto_warm_exam_id === 0) {
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
            'blocking_exam_id' => $has_blocking_preflight ? $active_exam_id : 0,
            'blocking_exam_title' => $has_blocking_preflight ? (string) ($state['exam_title'] ?? ('Exam #' . $active_exam_id)) : '',
            'blocking_auto_warm_exam_id' => $blocking_auto_warm_exam_id,
            'blocking_auto_warm_exam_title' => $blocking_auto_warm_exam_title,
        ];
    }

    private static function maybe_restore_tick_event(): void
    {
        $state = self::get_state();
        if (!empty($state['active'])) {
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
        $status = sanitize_key((string) ($state['status'] ?? self::STATUS_INACTIVE));
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_WARNINGS, self::STATUS_FAILED, self::STATUS_INACTIVE], true)) {
            $status = self::STATUS_INACTIVE;
        }

        return [
            'active' => !empty($state['active']),
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

        $auto_warm_state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        if (!empty($auto_warm_state['active']) && (int) ($auto_warm_state['exam_id'] ?? 0) > 0 && (int) ($auto_warm_state['exam_id'] ?? 0) !== $exam_id) {
            return [
                'can_start' => false,
                'message' => sprintf(
                    'Auto-Warm Availability aktif untuk exam lain: %s.',
                    (string) ($auto_warm_state['exam_title'] ?? ('Exam #' . (int) ($auto_warm_state['exam_id'] ?? 0)))
                ),
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

        foreach ($batch_student_ids as $user_id) {
            $user_id = absint($user_id);
            if ($user_id <= 0) {
                $failure_count++;
                $login_failure_count++;
                continue;
            }

            try {
                CBT_Student_Profile_Cache::warm_snapshot($user_id);
                $diagnostics = CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id);
                if (!empty($diagnostics['snapshot_valid']) && (string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
                    $success_count++;
                } else {
                    $failure_count++;
                }
            } catch (Throwable $throwable) {
                $failure_count++;
            }

            try {
                CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot($user_id, 'preflight');
                $login_diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
                if (!empty($login_diagnostics['snapshot_valid']) && (string) ($login_diagnostics['snapshot_status'] ?? '') === 'ready') {
                    $login_success_count++;
                } else {
                    $login_failure_count++;
                }
            } catch (Throwable $throwable) {
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
