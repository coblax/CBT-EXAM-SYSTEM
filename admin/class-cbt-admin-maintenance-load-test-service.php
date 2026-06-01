<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-common.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-seed-service.php';

if (!class_exists('CBT_User_Password_Secret')) {
    require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-user-password-secret.php';
}

final class CBT_Admin_Maintenance_Load_Test_Service
{
    private const USER_META_PLAIN_PASSWORD = 'cbt_plain_password';
    private const LOAD_TEST_JOBS_OPTION = 'cbt_load_test_jobs';
    private const LOAD_TEST_RUNTIME_DIRECTORY = 'cbt-load-test';
    private const LOAD_TEST_MAX_JOB_HISTORY = 24;
    private const LOAD_TEST_CANCELLED_EXIT_CODE = 143;

    /**
     * @return array<string,mixed>
     */
    public static function build_load_test_status_context(): array
    {
        $jobs = self::sync_load_test_jobs();
        $running_job_count = 0;
        $latest_running_exam = '';

        foreach ($jobs as $job) {
            $job = self::normalize_load_test_job((array) $job);
            if (!in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)) {
                continue;
            }

            $running_job_count++;
            if ($latest_running_exam === '') {
                $latest_running_exam = (string) ($job['exam_title'] ?? 'Exam');
            }
        }

        return [
            'load_test_jobs' => $jobs,
            'load_test_running_count' => $running_job_count,
            'load_test_latest_running_exam' => $latest_running_exam,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function build_load_test_context(): array
    {
        $status_context = self::build_load_test_status_context();
        $runtime = self::get_load_test_runtime_snapshot();
        $student_pool = self::get_load_test_student_pool();
        $exam_catalog = self::get_load_test_exam_catalog();
        $scenarios = self::get_load_test_scenarios();
        $profile_presets = self::get_load_test_profile_presets();
        $default_profile_key = self::get_load_test_default_profile_key();
        $default_profile = self::normalize_load_test_profile([
            'profile_preset' => $default_profile_key,
        ]);
        $jobs = isset($status_context['load_test_jobs']) && is_array($status_context['load_test_jobs'])
            ? (array) $status_context['load_test_jobs']
            : [];

        return array_merge($status_context, [
            'load_test_runtime' => $runtime,
            'load_test_student_pool' => $student_pool,
            'load_test_exam_catalog' => $exam_catalog,
            'load_test_scenarios' => $scenarios,
            'load_test_shapes' => self::get_load_test_shapes(),
            'load_test_profile_presets' => $profile_presets,
            'load_test_default_profile_key' => $default_profile_key,
            'load_test_default_profile' => $default_profile,
            'load_test_base_url_default' => self::get_load_test_base_url_default(),
            'load_test_jobs_markup' => CBT_Admin_Maintenance_Load_Test_Presenter::render_jobs_markup($jobs),
        ]);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function get_load_test_scenarios(): array
    {
        return [
            'login_only' => [
                'label' => 'Login Only',
                'description' => 'Login siswa lalu selesai tanpa memanggil daftar exam, start attempt, atau submit jawaban.',
                'endpoint_summary' => 'Endpoint: login. Submit: tidak. Finish: tidak.',
                'requests_exam_list' => 0,
                'starts_attempt' => 0,
                'reads_questions' => 0,
                'submits_answers' => 0,
                'batch_submit' => 0,
                'finishes_exam' => 0,
            ],
            'auth_exams_only' => [
                'label' => 'Auth + Exams',
                'description' => 'Login siswa lalu ambil daftar exam yang tersedia untuk menguji auth dan endpoint katalog exam.',
                'endpoint_summary' => 'Endpoint: login -> exams. Submit: tidak. Finish: tidak.',
                'requests_exam_list' => 1,
                'starts_attempt' => 0,
                'reads_questions' => 0,
                'submits_answers' => 0,
                'batch_submit' => 0,
                'finishes_exam' => 0,
            ],
            'start_attempt_only' => [
                'label' => 'Start Attempt Only',
                'description' => 'Login, resolve exam, lalu start attempt untuk menguji pembukaan sesi ujian tanpa membaca soal.',
                'endpoint_summary' => 'Endpoint: login -> start_attempt. Submit: tidak. Finish: tidak.',
                'requests_exam_list' => 0,
                'starts_attempt' => 1,
                'reads_questions' => 0,
                'submits_answers' => 0,
                'batch_submit' => 0,
                'finishes_exam' => 0,
            ],
            'read_questions_only' => [
                'label' => 'Read Questions Only',
                'description' => 'Login, start attempt, lalu ambil daftar soal tanpa submit jawaban atau finish exam.',
                'endpoint_summary' => 'Endpoint: login -> start_attempt -> questions. Submit: tidak. Finish: tidak.',
                'requests_exam_list' => 0,
                'starts_attempt' => 1,
                'reads_questions' => 1,
                'submits_answers' => 0,
                'batch_submit' => 0,
                'finishes_exam' => 0,
            ],
            'submit_sequential_only' => [
                'label' => 'Submit Sequential Only',
                'description' => 'Login, start attempt, ambil soal, lalu submit jawaban satu per satu tanpa finish exam.',
                'endpoint_summary' => 'Endpoint: login -> start_attempt -> questions -> submit_answer. Submit: satu-satu. Finish: tidak.',
                'requests_exam_list' => 0,
                'starts_attempt' => 1,
                'reads_questions' => 1,
                'submits_answers' => 1,
                'batch_submit' => 0,
                'finishes_exam' => 0,
            ],
            'submit_batch_only' => [
                'label' => 'Submit Batch Only',
                'description' => 'Login, start attempt, ambil soal, lalu submit jawaban per batch tanpa finish exam.',
                'endpoint_summary' => 'Endpoint: login -> start_attempt -> questions -> submit_answers_batch. Submit: batch. Finish: tidak.',
                'requests_exam_list' => 0,
                'starts_attempt' => 1,
                'reads_questions' => 1,
                'submits_answers' => 1,
                'batch_submit' => 1,
                'finishes_exam' => 0,
            ],
            'full_exam_finish_sequential' => [
                'label' => 'Full Exam + Finish (Sequential)',
                'description' => 'Login, start attempt, ambil soal, submit satu per satu, lalu finish exam untuk simulasi alur penuh tanpa batch submit.',
                'endpoint_summary' => 'Endpoint: login -> start_attempt -> questions -> submit_answer -> finish_exam. Submit: satu-satu. Finish: ya.',
                'requests_exam_list' => 0,
                'starts_attempt' => 1,
                'reads_questions' => 1,
                'submits_answers' => 1,
                'batch_submit' => 0,
                'finishes_exam' => 1,
            ],
            'full_exam_finish_batch' => [
                'label' => 'Full Exam + Finish (Batch)',
                'description' => 'Login, start attempt, ambil soal, submit per batch, lalu finish exam. Ini mode paling dekat dengan runner load test saat ini.',
                'endpoint_summary' => 'Endpoint: login -> start_attempt -> questions -> submit_answers_batch -> finish_exam. Submit: batch. Finish: ya.',
                'requests_exam_list' => 0,
                'starts_attempt' => 1,
                'reads_questions' => 1,
                'submits_answers' => 1,
                'batch_submit' => 1,
                'finishes_exam' => 1,
            ],
        ];
    }

    private static function get_load_test_default_scenario_key(): string
    {
        return 'full_exam_finish_batch';
    }

    /**
     * @return array<string,array<string,string>>
     */
    private static function get_load_test_shapes(): array
    {
        return [
            'flat_iterations' => [
                'label' => 'Flat',
                'description' => 'Model lama berbasis VUs tetap dan jumlah iteration per virtual user.',
                'endpoint_hint' => 'Cocok untuk baseline cepat dan perbandingan dengan histori lama.',
            ],
            'ramping_vus' => [
                'label' => 'Ramping',
                'description' => 'Concurrency user naik bertahap dari warmup ke peak, bertahan, lalu turun kembali.',
                'endpoint_hint' => 'Lebih mirip trafik ujian nyata karena beban tidak meledak sekaligus.',
            ],
        ];
    }

    private static function get_load_test_default_load_shape(): string
    {
        return 'flat_iterations';
    }

    /**
     * @return array<string,string>
     */
    private static function get_load_test_shape_meta(string $load_shape): array
    {
        $shapes = self::get_load_test_shapes();
        if (!isset($shapes[$load_shape])) {
            $load_shape = self::get_load_test_default_load_shape();
        }

        $shape = (array) ($shapes[$load_shape] ?? []);
        $shape['key'] = $load_shape;

        return $shape;
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_load_test_default_ramping_profile(): array
    {
        return [
            'peak_vus' => 50,
            'warmup_duration' => '1m',
            'ramp_up_duration' => '2m',
            'steady_duration' => '5m',
            'ramp_down_duration' => '1m',
            'ramp_steps' => 2,
        ];
    }

    private static function get_load_test_profile_presets(): array
    {
        return [
            'smoke_50' => [
                'label' => 'Smoke 50',
                'description' => 'Baseline cepat dengan concurrency datar untuk validasi dasar runner.',
                'load_shape' => 'flat_iterations',
                'vus' => 50,
                'iterations' => 1,
                'peak_vus' => 50,
                'warmup_duration' => '1m',
                'ramp_up_duration' => '2m',
                'steady_duration' => '5m',
                'ramp_down_duration' => '1m',
                'ramp_steps' => 2,
                'questions_per_user' => 20,
                'session_start_spread_ms' => 4000,
                'post_start_spread_ms' => 1000,
                'scenario_key' => 'full_exam_finish_batch',
                'max_duration' => '20m',
            ],
            'load_200' => [
                'label' => 'Load 200',
                'description' => 'Profile flat menengah untuk membandingkan hasil dengan setup historis.',
                'load_shape' => 'flat_iterations',
                'vus' => 200,
                'iterations' => 1,
                'peak_vus' => 200,
                'warmup_duration' => '1m',
                'ramp_up_duration' => '3m',
                'steady_duration' => '6m',
                'ramp_down_duration' => '2m',
                'ramp_steps' => 3,
                'questions_per_user' => 40,
                'session_start_spread_ms' => 10000,
                'post_start_spread_ms' => 3000,
                'scenario_key' => 'full_exam_finish_batch',
                'max_duration' => '30m',
            ],
            'load_500' => [
                'label' => 'Load 500',
                'description' => 'Profile flat besar untuk menguji limit throughput dengan bentuk beban datar.',
                'load_shape' => 'flat_iterations',
                'vus' => 500,
                'iterations' => 1,
                'peak_vus' => 500,
                'warmup_duration' => '1m',
                'ramp_up_duration' => '4m',
                'steady_duration' => '8m',
                'ramp_down_duration' => '2m',
                'ramp_steps' => 3,
                'questions_per_user' => 60,
                'session_start_spread_ms' => 30000,
                'post_start_spread_ms' => 10000,
                'scenario_key' => 'full_exam_finish_batch',
                'max_duration' => '40m',
            ],
            'load_1000' => [
                'label' => 'Load 1000',
                'description' => 'Profile flat tertinggi untuk stress test legacy berbasis spike datar.',
                'load_shape' => 'flat_iterations',
                'vus' => 1000,
                'iterations' => 1,
                'peak_vus' => 1000,
                'warmup_duration' => '1m',
                'ramp_up_duration' => '5m',
                'steady_duration' => '10m',
                'ramp_down_duration' => '3m',
                'ramp_steps' => 4,
                'questions_per_user' => 80,
                'session_start_spread_ms' => 90000,
                'post_start_spread_ms' => 30000,
                'scenario_key' => 'full_exam_finish_batch',
                'max_duration' => '45m',
            ],
            'ramp_smoke_50' => [
                'label' => 'Ramp Smoke 50',
                'description' => 'Beban naik bertahap sampai 50 user aktif, ditahan 5 menit, lalu diturunkan lagi. Cocok untuk uji realistis skala kecil.',
                'load_shape' => 'ramping_vus',
                'vus' => 50,
                'iterations' => 1,
                'peak_vus' => 50,
                'warmup_duration' => '1m',
                'ramp_up_duration' => '2m',
                'steady_duration' => '5m',
                'ramp_down_duration' => '1m',
                'ramp_steps' => 2,
                'questions_per_user' => 20,
                'session_start_spread_ms' => 1000,
                'post_start_spread_ms' => 250,
                'scenario_key' => 'full_exam_finish_batch',
                'max_duration' => '10m',
            ],
            'ramp_load_200' => [
                'label' => 'Ramp Load 200',
                'description' => 'Beban naik bertahap sampai 200 user aktif, ditahan 6 menit, lalu diturunkan perlahan. Cocok untuk simulasi ujian nyata skala menengah.',
                'load_shape' => 'ramping_vus',
                'vus' => 200,
                'iterations' => 1,
                'peak_vus' => 200,
                'warmup_duration' => '1m',
                'ramp_up_duration' => '3m',
                'steady_duration' => '6m',
                'ramp_down_duration' => '2m',
                'ramp_steps' => 3,
                'questions_per_user' => 40,
                'session_start_spread_ms' => 2000,
                'post_start_spread_ms' => 500,
                'scenario_key' => 'full_exam_finish_batch',
                'max_duration' => '14m',
            ],
            'ramp_load_500' => [
                'label' => 'Ramp Load 500',
                'description' => 'Beban naik bertahap sampai 500 user aktif, ditahan 8 menit, lalu diturunkan perlahan. Cocok untuk simulasi padat menjelang jam ujian.',
                'load_shape' => 'ramping_vus',
                'vus' => 500,
                'iterations' => 1,
                'peak_vus' => 500,
                'warmup_duration' => '1m',
                'ramp_up_duration' => '4m',
                'steady_duration' => '8m',
                'ramp_down_duration' => '2m',
                'ramp_steps' => 3,
                'questions_per_user' => 60,
                'session_start_spread_ms' => 3000,
                'post_start_spread_ms' => 1000,
                'scenario_key' => 'full_exam_finish_batch',
                'max_duration' => '17m',
            ],
            'ramp_load_1000' => [
                'label' => 'Ramp Load 1000',
                'description' => 'Beban naik bertahap sampai 1000 user aktif, ditahan 10 menit, lalu diturunkan bertahap. Cocok untuk stress test realistis skala besar.',
                'load_shape' => 'ramping_vus',
                'vus' => 1000,
                'iterations' => 1,
                'peak_vus' => 1000,
                'warmup_duration' => '1m',
                'ramp_up_duration' => '5m',
                'steady_duration' => '10m',
                'ramp_down_duration' => '3m',
                'ramp_steps' => 4,
                'questions_per_user' => 80,
                'session_start_spread_ms' => 5000,
                'post_start_spread_ms' => 1500,
                'scenario_key' => 'full_exam_finish_batch',
                'max_duration' => '21m',
            ],
        ];
    }

    private static function get_load_test_default_profile_key(): string
    {
        return 'smoke_50';
    }

    private static function normalize_load_test_duration_value($value, string $fallback): string
    {
        $normalized = trim(sanitize_text_field((string) $value));
        if (!preg_match('/^\d+[smh]$/', $normalized)) {
            return $fallback;
        }

        $seconds = self::convert_load_test_duration_to_seconds($normalized);
        if ($seconds < 0 || $seconds > 43200) {
            return $fallback;
        }

        return $normalized;
    }

    private static function convert_load_test_duration_to_seconds(string $duration): int
    {
        if (!preg_match('/^(\d+)([smh])$/', $duration, $matches)) {
            return -1;
        }

        $value = (int) $matches[1];
        switch ($matches[2]) {
            case 'h':
                return $value * 3600;
            case 'm':
                return $value * 60;
            case 's':
            default:
                return $value;
        }
    }

    private static function convert_seconds_to_load_test_duration(int $seconds): string
    {
        return max(0, $seconds) . 's';
    }

    private static function convert_seconds_to_compact_load_test_duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds > 0 && $seconds % 3600 === 0) {
            return (string) intdiv($seconds, 3600) . 'h';
        }
        if ($seconds > 0 && $seconds % 60 === 0) {
            return (string) intdiv($seconds, 60) . 'm';
        }

        return self::convert_seconds_to_load_test_duration($seconds);
    }

    /**
     * @return array<int,array{target:int,duration:string}>
     */
    private static function compile_load_test_ramping_stages(
        int $peak_vus,
        string $warmup_duration,
        string $ramp_up_duration,
        string $steady_duration,
        string $ramp_down_duration,
        int $ramp_steps
    ): array {
        $stages = [];
        $peak_vus = max(1, min(5000, $peak_vus));

        $warmup_seconds = self::convert_load_test_duration_to_seconds($warmup_duration);
        if ($warmup_seconds > 0) {
            $stages[] = [
                'target' => max(1, (int) ceil($peak_vus * 0.15)),
                'duration' => self::convert_seconds_to_load_test_duration($warmup_seconds),
            ];
        }

        $ramp_up_seconds = self::convert_load_test_duration_to_seconds($ramp_up_duration);
        $ramp_steps = max(1, $ramp_steps);
        if ($ramp_up_seconds > 0) {
            $base_seconds = intdiv($ramp_up_seconds, $ramp_steps);
            $remainder_seconds = $ramp_up_seconds % $ramp_steps;

            for ($step = 1; $step <= $ramp_steps; $step++) {
                $duration_seconds = $base_seconds + ($step <= $remainder_seconds ? 1 : 0);
                if ($duration_seconds <= 0) {
                    continue;
                }

                $stages[] = [
                    'target' => max(1, min($peak_vus, (int) round(($peak_vus * $step) / $ramp_steps))),
                    'duration' => self::convert_seconds_to_load_test_duration($duration_seconds),
                ];
            }
        }

        $steady_seconds = self::convert_load_test_duration_to_seconds($steady_duration);
        if ($steady_seconds > 0) {
            $stages[] = [
                'target' => $peak_vus,
                'duration' => self::convert_seconds_to_load_test_duration($steady_seconds),
            ];
        }

        $ramp_down_seconds = self::convert_load_test_duration_to_seconds($ramp_down_duration);
        if ($ramp_down_seconds > 0) {
            $stages[] = [
                'target' => 0,
                'duration' => self::convert_seconds_to_load_test_duration($ramp_down_seconds),
            ];
        }

        return $stages;
    }

    private static function summarize_load_test_shape(
        string $load_shape,
        int $vus,
        int $iterations,
        string $warmup_duration,
        string $ramp_up_duration,
        string $steady_duration,
        string $ramp_down_duration
    ): string {
        if ($load_shape === 'ramping_vus') {
            return sprintf(
                'Ramping: %s warmup · %s ramp-up · %s steady · %s ramp-down',
                $warmup_duration,
                $ramp_up_duration,
                $steady_duration,
                $ramp_down_duration
            );
        }

        return sprintf('Flat: %d VUs x %d iteration', max(1, $vus), max(1, $iterations));
    }

    private static function format_load_test_total_duration_label(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining_seconds = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . 'j';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . 'm';
        }
        if ($remaining_seconds > 0 || empty($parts)) {
            $parts[] = $remaining_seconds . 'd';
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<int,array{target:int,duration:string}> $stages
     */
    private static function calculate_load_test_stages_total_seconds(array $stages): int
    {
        $total_seconds = 0;
        foreach ($stages as $stage) {
            $duration = isset($stage['duration']) ? (string) $stage['duration'] : '';
            $seconds = self::convert_load_test_duration_to_seconds($duration);
            if ($seconds > 0) {
                $total_seconds += $seconds;
            }
        }

        return $total_seconds;
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_load_test_scenario_meta(string $scenario_key): array
    {
        $scenarios = self::get_load_test_scenarios();
        if (!isset($scenarios[$scenario_key])) {
            $scenario_key = self::get_load_test_default_scenario_key();
        }

        $scenario = (array) ($scenarios[$scenario_key] ?? []);
        $scenario['key'] = $scenario_key;

        return $scenario;
    }

    /**
     * @return array{submit_mode:string,enable_batch_submit:int}
     */
    private static function get_load_test_legacy_flags_for_scenario(string $scenario_key): array
    {
        $scenario = self::get_load_test_scenario_meta($scenario_key);
        $reads_questions = !empty($scenario['reads_questions']);
        $submits_answers = !empty($scenario['submits_answers']);

        if (!$reads_questions || !$submits_answers) {
            return [
                'submit_mode' => 'none',
                'enable_batch_submit' => 0,
            ];
        }

        return [
            'submit_mode' => 'all',
            'enable_batch_submit' => !empty($scenario['batch_submit']) ? 1 : 0,
        ];
    }

    private static function map_legacy_load_test_scenario_key(string $submit_mode, int $enable_batch_submit): string
    {
        if ($submit_mode === 'none') {
            return 'read_questions_only';
        }

        return $enable_batch_submit === 1
            ? 'full_exam_finish_batch'
            : 'full_exam_finish_sequential';
    }

    public static function normalize_load_test_profile(array $request): array
    {
        $presets = self::get_load_test_profile_presets();
        $profile_key = isset($request['profile_preset'])
            ? sanitize_key((string) wp_unslash($request['profile_preset']))
            : self::get_load_test_default_profile_key();
        if (!isset($presets[$profile_key])) {
            $profile_key = self::get_load_test_default_profile_key();
        }

        $base = $presets[$profile_key];
        $default_ramping = self::get_load_test_default_ramping_profile();
        $legacy_submit_mode = isset($request['submit_mode'])
            ? sanitize_key((string) wp_unslash($request['submit_mode']))
            : (string) ($base['submit_mode'] ?? 'all');
        if (!in_array($legacy_submit_mode, ['all', 'none'], true)) {
            $legacy_submit_mode = (string) ($base['submit_mode'] ?? 'all');
        }

        if (array_key_exists('enable_batch_submit', $request)) {
            $raw_enable_batch_submit = wp_unslash($request['enable_batch_submit']);
            $legacy_enable_batch_submit = in_array(
                strtolower(trim((string) $raw_enable_batch_submit)),
                ['1', 'true', 'yes', 'on'],
                true
            ) ? 1 : 0;
        } else {
            $legacy_enable_batch_submit = (int) ($base['enable_batch_submit'] ?? 1);
        }
        $legacy_enable_batch_submit = $legacy_enable_batch_submit === 1 ? 1 : 0;

        $has_legacy_submit_mode = array_key_exists('submit_mode', $request);
        $has_legacy_batch_submit = array_key_exists('enable_batch_submit', $request);

        $scenario_key = isset($request['scenario_key'])
            ? sanitize_key((string) wp_unslash($request['scenario_key']))
            : '';
        if ($scenario_key === '') {
            if ($has_legacy_submit_mode || $has_legacy_batch_submit) {
                $scenario_key = self::map_legacy_load_test_scenario_key($legacy_submit_mode, $legacy_enable_batch_submit);
            } else {
                $scenario_key = isset($base['scenario_key']) && is_string($base['scenario_key']) && $base['scenario_key'] !== ''
                    ? sanitize_key((string) $base['scenario_key'])
                    : self::map_legacy_load_test_scenario_key($legacy_submit_mode, $legacy_enable_batch_submit);
            }
        }

        $scenario = self::get_load_test_scenario_meta($scenario_key);
        $scenario_key = (string) ($scenario['key'] ?? self::get_load_test_default_scenario_key());
        $legacy_flags = self::get_load_test_legacy_flags_for_scenario($scenario_key);
        $load_shape = sanitize_key((string) ($base['load_shape'] ?? self::get_load_test_default_load_shape()));
        $shape = self::get_load_test_shape_meta($load_shape);
        $load_shape = (string) ($shape['key'] ?? self::get_load_test_default_load_shape());
        $allow_ramping_override = $load_shape === 'ramping_vus';

        $vus = max(1, min(5000, isset($request['vus']) ? absint(wp_unslash($request['vus'])) : (int) ($base['vus'] ?? 50)));
        $iterations = max(1, min(100, isset($request['iterations']) ? absint(wp_unslash($request['iterations'])) : (int) ($base['iterations'] ?? 1)));
        $peak_vus = max(
            1,
            min(
                5000,
                $allow_ramping_override && isset($request['peak_vus'])
                    ? absint(wp_unslash($request['peak_vus']))
                    : (int) ($base['peak_vus'] ?? $default_ramping['peak_vus'] ?? $vus)
            )
        );
        $warmup_duration = self::normalize_load_test_duration_value(
            $allow_ramping_override && isset($request['warmup_duration']) ? wp_unslash($request['warmup_duration']) : ($base['warmup_duration'] ?? $default_ramping['warmup_duration']),
            (string) ($base['warmup_duration'] ?? $default_ramping['warmup_duration'])
        );
        $ramp_up_duration = self::normalize_load_test_duration_value(
            $allow_ramping_override && isset($request['ramp_up_duration']) ? wp_unslash($request['ramp_up_duration']) : ($base['ramp_up_duration'] ?? $default_ramping['ramp_up_duration']),
            (string) ($base['ramp_up_duration'] ?? $default_ramping['ramp_up_duration'])
        );
        $steady_duration = self::normalize_load_test_duration_value(
            $allow_ramping_override && isset($request['steady_duration']) ? wp_unslash($request['steady_duration']) : ($base['steady_duration'] ?? $default_ramping['steady_duration']),
            (string) ($base['steady_duration'] ?? $default_ramping['steady_duration'])
        );
        $ramp_down_duration = self::normalize_load_test_duration_value(
            $allow_ramping_override && isset($request['ramp_down_duration']) ? wp_unslash($request['ramp_down_duration']) : ($base['ramp_down_duration'] ?? $default_ramping['ramp_down_duration']),
            (string) ($base['ramp_down_duration'] ?? $default_ramping['ramp_down_duration'])
        );
        $ramp_steps = max(
            1,
            min(
                12,
                $allow_ramping_override && isset($request['ramp_steps'])
                    ? absint(wp_unslash($request['ramp_steps']))
                    : (int) ($base['ramp_steps'] ?? $default_ramping['ramp_steps'])
            )
        );
        $compiled_stages = $load_shape === 'ramping_vus'
            ? self::compile_load_test_ramping_stages($peak_vus, $warmup_duration, $ramp_up_duration, $steady_duration, $ramp_down_duration, $ramp_steps)
            : [];
        $stages_total_seconds = self::calculate_load_test_stages_total_seconds($compiled_stages);
        $effective_vus = $load_shape === 'ramping_vus' ? $peak_vus : $vus;
        $max_duration = $load_shape === 'ramping_vus'
            ? self::convert_seconds_to_compact_load_test_duration(min(43320, $stages_total_seconds + 120))
            : sanitize_text_field((string) ($base['max_duration'] ?? '45m'));
        $stage_summary = self::summarize_load_test_shape(
            $load_shape,
            $vus,
            $iterations,
            $warmup_duration,
            $ramp_up_duration,
            $steady_duration,
            $ramp_down_duration
        );

        return [
            'profile_preset' => $profile_key,
            'profile_label' => (string) ($base['label'] ?? ucfirst(str_replace('_', ' ', $profile_key))),
            'profile_description' => sanitize_text_field((string) ($base['description'] ?? '')),
            'load_shape' => $load_shape,
            'load_shape_label' => sanitize_text_field((string) ($shape['label'] ?? $load_shape)),
            'load_shape_description' => sanitize_text_field((string) ($shape['description'] ?? '')),
            'load_shape_endpoint_hint' => sanitize_text_field((string) ($shape['endpoint_hint'] ?? '')),
            'vus' => $vus,
            'iterations' => $iterations,
            'peak_vus' => $peak_vus,
            'warmup_duration' => $warmup_duration,
            'ramp_up_duration' => $ramp_up_duration,
            'steady_duration' => $steady_duration,
            'ramp_down_duration' => $ramp_down_duration,
            'ramp_steps' => $ramp_steps,
            'compiled_stages' => $compiled_stages,
            'stage_summary' => $stage_summary,
            'effective_vus' => $effective_vus,
            'estimated_duration_seconds' => $load_shape === 'ramping_vus' ? $stages_total_seconds : 0,
            'estimated_duration_label' => $load_shape === 'ramping_vus'
                ? self::format_load_test_total_duration_label($stages_total_seconds)
                : sanitize_text_field((string) ($base['max_duration'] ?? '45m')),
            'questions_per_user' => max(0, min(500, isset($request['questions_per_user']) ? absint(wp_unslash($request['questions_per_user'])) : (int) ($base['questions_per_user'] ?? 0))),
            'session_start_spread_ms' => max(0, min(600000, isset($request['session_start_spread_ms']) ? absint(wp_unslash($request['session_start_spread_ms'])) : (int) ($base['session_start_spread_ms'] ?? 0))),
            'post_start_spread_ms' => max(0, min(600000, isset($request['post_start_spread_ms']) ? absint(wp_unslash($request['post_start_spread_ms'])) : (int) ($base['post_start_spread_ms'] ?? 0))),
            'scenario_key' => $scenario_key,
            'scenario_label' => sanitize_text_field((string) ($scenario['label'] ?? $scenario_key)),
            'scenario_description' => sanitize_text_field((string) ($scenario['description'] ?? '')),
            'scenario_endpoint_summary' => sanitize_text_field((string) ($scenario['endpoint_summary'] ?? '')),
            'scenario_requests_exam_list' => !empty($scenario['requests_exam_list']) ? 1 : 0,
            'scenario_starts_attempt' => !empty($scenario['starts_attempt']) ? 1 : 0,
            'scenario_reads_questions' => !empty($scenario['reads_questions']) ? 1 : 0,
            'scenario_submits_answers' => !empty($scenario['submits_answers']) ? 1 : 0,
            'scenario_batch_submit' => !empty($scenario['batch_submit']) ? 1 : 0,
            'scenario_finishes_exam' => !empty($scenario['finishes_exam']) ? 1 : 0,
            'submit_mode' => $legacy_flags['submit_mode'],
            'enable_batch_submit' => $legacy_flags['enable_batch_submit'],
            'max_duration' => $max_duration,
        ];
    }

    public static function get_load_test_base_url_default(): string
    {
        return untrailingslashit(home_url('/'));
    }

    private static function normalize_load_test_base_url(array $request): string
    {
        $raw = isset($request['base_url'])
            ? trim((string) wp_unslash($request['base_url']))
            : self::get_load_test_base_url_default();
        $sanitized = untrailingslashit(esc_url_raw($raw));
        if ($sanitized === '' || !preg_match('#^https?://#i', $sanitized)) {
            return self::get_load_test_base_url_default();
        }

        return $sanitized;
    }

    private static function normalize_load_test_token_override(string $token): string
    {
        return CBT_Auth::normalize_exam_token_input($token);
    }

    private static function get_load_test_student_pool(): array
    {
        $users = get_users([
            'role__in' => ['siswa_cbt', 'subscriber', 'student'],
            'orderby' => 'login',
            'order' => 'ASC',
            'number' => -1,
        ]);

        $rows = [];
        $total_count = 0;
        $missing_password_count = 0;
        $reserved_excluded_count = 0;

        foreach ((array) $users as $user) {
            if (!($user instanceof WP_User)) {
                continue;
            }

            if (self::is_reserved_load_test_student($user)) {
                $reserved_excluded_count++;
                continue;
            }

            $total_count++;
            $plain_password = CBT_User_Password_Secret::get_user_plain_password((int) $user->ID);
            if ($plain_password === '') {
                $missing_password_count++;
                continue;
            }

            $nisn = preg_replace('/\D+/', '', (string) get_user_meta($user->ID, 'nisn', true));
            $email = sanitize_email((string) $user->user_email);
            if (!is_email($email) && $nisn !== '') {
                $email = sanitize_email($nisn . '@student.sch.id');
            }
            if (!is_email($email)) {
                $email = sanitize_email((string) $user->user_login . '@example.local');
            }

            $rows[] = [
                'name' => sanitize_text_field((string) $user->display_name),
                'email' => $email,
                'nisn' => $nisn,
                'username' => sanitize_user((string) $user->user_login, true),
                'password' => $plain_password,
                'role' => 'siswa',
                'kode_kelas' => sanitize_text_field((string) get_user_meta($user->ID, 'kode_kelas', true)),
                'kode_ruang' => sanitize_text_field((string) get_user_meta($user->ID, 'kode_ruang', true)),
                'agama' => sanitize_text_field((string) get_user_meta($user->ID, 'agama', true)),
                'jenis_kelamin' => CBT_Admin_Users_Service::normalize_supported_jenis_kelamin((string) get_user_meta($user->ID, 'jenis_kelamin', true)),
                'foto' => esc_url_raw((string) get_user_meta($user->ID, 'foto', true)),
                'identifier' => sanitize_user((string) $user->user_login, true),
            ];
        }

        return [
            'rows' => $rows,
            'total_count' => $total_count,
            'valid_count' => count($rows),
            'missing_password_count' => $missing_password_count,
            'reserved_excluded_count' => $reserved_excluded_count,
        ];
    }

    /**
     * @return string[]
     */
    private static function get_reserved_load_test_student_usernames(): array
    {
        $usernames = [];

        if (
            class_exists('CBT_Admin_Maintenance_Seed_Service')
            && method_exists('CBT_Admin_Maintenance_Seed_Service', 'get_seed_special_student_username')
        ) {
            $seed_username = sanitize_user((string) CBT_Admin_Maintenance_Seed_Service::get_seed_special_student_username(), true);
            if ($seed_username !== '') {
                $usernames[] = strtolower($seed_username);
            }
        }

        return array_values(array_unique(array_filter($usernames, static function ($username) {
            return is_string($username) && $username !== '';
        })));
    }

    private static function is_reserved_load_test_student(WP_User $user): bool
    {
        $reserved_usernames = self::get_reserved_load_test_student_usernames();
        if (empty($reserved_usernames)) {
            return false;
        }

        $username = strtolower(sanitize_user((string) $user->user_login, true));
        if ($username === '') {
            return false;
        }

        return in_array($username, $reserved_usernames, true);
    }

    private static function get_load_test_runtime_snapshot(): array
    {
        $exec_available = function_exists('exec');
        $proc_open_available = function_exists('proc_open');
        $shell_exec_available = function_exists('shell_exec');
        $shell_available = $exec_available || $proc_open_available || $shell_exec_available;
        $k6_path = self::detect_load_test_k6_path();
        $k6_install_mode = 'missing';
        if ($k6_path !== '') {
            $k6_install_mode = (strpos($k6_path, '/snap/') === 0) ? 'snap' : 'native';
        }
        $runner_home_meta = self::get_load_test_runner_home_meta($k6_path);

        $upload = wp_upload_dir();
        $runtime_root = '';
        $runtime_root_exists = false;
        $runtime_root_writable = false;
        if (is_array($upload) && empty($upload['error']) && !empty($upload['basedir'])) {
            $runtime_root = trailingslashit((string) $upload['basedir']) . self::LOAD_TEST_RUNTIME_DIRECTORY;
            $runtime_root_exists = is_dir($runtime_root);
            $runtime_root_writable = $runtime_root_exists
                ? is_writable($runtime_root)
                : is_writable((string) $upload['basedir']);
        }

        return [
            'shell_available' => $shell_available,
            'exec_available' => $exec_available,
            'proc_open_available' => $proc_open_available,
            'shell_exec_available' => $shell_exec_available,
            'k6_path' => $k6_path,
            'k6_install_mode' => $k6_install_mode,
            'runner_home' => (string) ($runner_home_meta['path'] ?? ''),
            'runner_home_supported' => !empty($runner_home_meta['supported']),
            'runner_home_detected' => (string) ($runner_home_meta['detected'] ?? ''),
            'base_url' => self::get_load_test_base_url_default(),
            'runtime_root' => $runtime_root,
            'runtime_root_exists' => $runtime_root_exists,
            'runtime_root_writable' => $runtime_root_writable,
            'global_token_meta' => CBT_Auth::get_global_exam_token(true),
        ];
    }

    private static function detect_load_test_k6_path(): string
    {
        $native_candidates = [
            '/usr/local/bin/k6',
            '/usr/bin/k6',
        ];
        $snap_candidates = [
            '/snap/bin/k6',
        ];
        if (function_exists('shell_exec')) {
            $command_path = trim((string) shell_exec('command -v k6 2>/dev/null'));
            if ($command_path !== '') {
                if (strpos($command_path, '/snap/') === 0) {
                    $snap_candidates[] = $command_path;
                } else {
                    $native_candidates[] = $command_path;
                }
            }
        }

        $candidates = array_values(array_unique(array_merge($native_candidates, $snap_candidates)));

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private static function get_load_test_runner_home_meta(string $k6_path): array
    {
        $detected = [];

        $env_home = trim((string) getenv('HOME'));
        if ($env_home !== '') {
            $detected[] = $env_home;
        }

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $pw = posix_getpwuid(posix_geteuid());
            if (is_array($pw) && !empty($pw['dir'])) {
                $detected[] = (string) $pw['dir'];
            }
        }

        $detected = array_values(array_unique(array_filter(array_map(static function ($path): string {
            return wp_normalize_path((string) $path);
        }, $detected))));

        $is_snap = strpos($k6_path, '/snap/') === 0;
        if ($is_snap) {
            foreach ($detected as $path) {
                if (strpos($path, '/home/') === 0) {
                    return [
                        'path' => $path,
                        'supported' => true,
                        'detected' => $path,
                    ];
                }
            }

            return [
                'path' => '',
                'supported' => false,
                'detected' => !empty($detected) ? (string) $detected[0] : '',
            ];
        }

        return [
            'path' => !empty($detected) ? (string) $detected[0] : '',
            'supported' => true,
            'detected' => !empty($detected) ? (string) $detected[0] : '',
        ];
    }

    private static function get_load_test_exam_catalog(): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.id,
                        e.title,
                        e.status,
                        e.starts_at,
                        e.ends_at,
                        e.duration_minutes,
                        e.kkm_percentage,
                        e.target_kelas,
                        s.name AS subject_name,
                        COUNT(q.id) AS question_count
                 FROM {$exam_table} e
                 LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                 LEFT JOIN {$question_table} q
                   ON q.exam_id = e.id
                  AND COALESCE(q.is_active, 1) = 1
                 WHERE e.title NOT LIKE %s
                 GROUP BY e.id, e.title, e.status, e.starts_at, e.ends_at, e.duration_minutes, e.kkm_percentage, e.target_kelas, s.name
                 ORDER BY
                     CASE WHEN e.starts_at IS NULL THEN 1 ELSE 0 END ASC,
                     e.starts_at ASC,
                     e.id ASC",
                'Bank Soal - %'
            ),
            ARRAY_A
        );

        $catalog = [
            'all' => [],
            'eligible' => [],
            'invalid' => [],
        ];
        foreach ((array) $rows as $row) {
            $exam = self::normalize_load_test_exam_catalog_row((array) $row);
            $catalog['all'][] = $exam;
            if (!empty($exam['eligible'])) {
                $catalog['eligible'][] = $exam;
            } else {
                $catalog['invalid'][] = $exam;
            }
        }

        return $catalog;
    }

    private static function normalize_load_test_exam_catalog_row(array $row): array
    {
        $status = sanitize_key((string) ($row['status'] ?? 'draft'));
        $starts_at = trim((string) ($row['starts_at'] ?? ''));
        $ends_at = trim((string) ($row['ends_at'] ?? ''));
        $question_count = max(0, (int) ($row['question_count'] ?? 0));
        $now = current_time('mysql');
        $within_schedule = (
            ($starts_at === '' || $starts_at <= $now) &&
            ($ends_at === '' || $ends_at >= $now)
        );

        $reasons = [];
        if ($status !== 'published') {
            $reasons[] = 'Status bukan published';
        }
        if ($starts_at !== '' && $starts_at > $now) {
            $reasons[] = 'Jadwal belum mulai';
        }
        if ($ends_at !== '' && $ends_at < $now) {
            $reasons[] = 'Jadwal sudah berakhir';
        }
        if ($question_count <= 0) {
            $reasons[] = 'Belum ada soal aktif';
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => sanitize_text_field((string) ($row['title'] ?? 'Exam')),
            'subject_name' => sanitize_text_field((string) ($row['subject_name'] ?? 'Tanpa Subject')),
            'status' => $status,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'duration_minutes' => max(1, (int) ($row['duration_minutes'] ?? 0)),
            'kkm_percentage' => self::normalize_maintenance_kkm_percentage((float) ($row['kkm_percentage'] ?? 75.0)),
            'target_kelas' => self::normalize_target_kelas_csv((string) ($row['target_kelas'] ?? '')),
            'target_kelas_list' => self::split_target_kelas_csv((string) ($row['target_kelas'] ?? '')),
            'question_count' => $question_count,
            'within_schedule' => $within_schedule,
            'eligible' => empty($reasons),
            'reject_reasons' => $reasons,
            'schedule_label' => self::format_load_test_exam_schedule_label($starts_at, $ends_at),
        ];
    }

    private static function format_load_test_exam_schedule_label(string $starts_at, string $ends_at): string
    {
        $parts = [];
        if ($starts_at !== '') {
            $parts[] = 'Mulai ' . $starts_at;
        }
        if ($ends_at !== '') {
            $parts[] = 'Selesai ' . $ends_at;
        }

        return !empty($parts) ? implode(' | ', $parts) : 'Tanpa batas jadwal';
    }

    private static function normalize_maintenance_kkm_percentage(float $value): float
    {
        if (!is_finite($value)) {
            return 75.0;
        }

        return round(min(100.0, max(0.0, $value)), 2);
    }

    private static function get_load_test_jobs_option_map(): array
    {
        $raw = get_option(self::LOAD_TEST_JOBS_OPTION, []);
        if (!is_array($raw)) {
            return [];
        }

        $jobs = [];
        foreach ($raw as $job_id => $job) {
            if (!is_array($job)) {
                continue;
            }

            $normalized = self::normalize_load_test_job($job);
            if ($normalized['id'] === '') {
                $normalized['id'] = sanitize_key((string) $job_id);
            }
            if ($normalized['id'] === '') {
                continue;
            }
            $jobs[$normalized['id']] = $normalized;
        }

        return $jobs;
    }

    private static function save_load_test_jobs_option_map(array $jobs): bool
    {
        $jobs = self::prune_load_test_jobs_option_map($jobs);
        return update_option(self::LOAD_TEST_JOBS_OPTION, $jobs, false);
    }

    private static function prune_load_test_jobs_option_map(array $jobs): array
    {
        uasort($jobs, static function (array $left, array $right): int {
            $left_time = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $right_time = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            return $right_time <=> $left_time;
        });

        $pruned = [];
        $completed_kept = 0;
        foreach ($jobs as $job_id => $job) {
            $status = (string) ($job['status'] ?? 'queued');
            $is_active = in_array($status, ['queued', 'running'], true);
            if ($is_active || $completed_kept < self::LOAD_TEST_MAX_JOB_HISTORY) {
                $pruned[$job_id] = $job;
                if (!$is_active) {
                    $completed_kept++;
                }
            }
        }

        return $pruned;
    }

    public static function normalize_load_test_job(array $job): array
    {
        return [
            'id' => sanitize_key((string) ($job['id'] ?? '')),
            'user_id' => max(0, (int) ($job['user_id'] ?? 0)),
            'status' => sanitize_key((string) ($job['status'] ?? 'queued')),
            'pid' => max(0, (int) ($job['pid'] ?? 0)),
            'exam_id' => max(0, (int) ($job['exam_id'] ?? 0)),
            'exam_title' => sanitize_text_field((string) ($job['exam_title'] ?? 'Exam')),
            'subject_name' => sanitize_text_field((string) ($job['subject_name'] ?? '')),
            'workspace' => isset($job['workspace']) ? wp_normalize_path((string) $job['workspace']) : '',
            'created_at' => sanitize_text_field((string) ($job['created_at'] ?? '')),
            'started_at' => sanitize_text_field((string) ($job['started_at'] ?? '')),
            'finished_at' => sanitize_text_field((string) ($job['finished_at'] ?? '')),
            'exit_code' => (!array_key_exists('exit_code', $job) || $job['exit_code'] === null || $job['exit_code'] === '') ? null : (int) $job['exit_code'],
            'base_url' => sanitize_text_field((string) ($job['base_url'] ?? '')),
            'token_source' => sanitize_text_field((string) ($job['token_source'] ?? 'global')),
            'manual_token' => sanitize_text_field((string) ($job['manual_token'] ?? '')),
            'student_count' => max(0, (int) ($job['student_count'] ?? 0)),
            'profile' => isset($job['profile']) && is_array($job['profile'])
                ? self::normalize_load_test_profile((array) $job['profile'])
                : self::normalize_load_test_profile([]),
            'command_preview' => sanitize_textarea_field((string) ($job['command_preview'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($job['notes'] ?? '')),
        ];
    }

    private static function sync_load_test_jobs(): array
    {
        $jobs = self::get_load_test_jobs_option_map();
        if (empty($jobs)) {
            return [];
        }

        $changed = false;
        foreach ($jobs as $job_id => $job) {
            $synced = self::sync_single_load_test_job($job);
            if ($synced !== $job) {
                $jobs[$job_id] = $synced;
                $changed = true;
            }
        }

        if ($changed) {
            self::save_load_test_jobs_option_map($jobs);
        }

        uasort($jobs, static function (array $left, array $right): int {
            $left_time = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $right_time = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            return $right_time <=> $left_time;
        });

        return $jobs;
    }

    private static function sync_single_load_test_job(array $job): array
    {
        $job = self::normalize_load_test_job($job);
        $workspace = (string) ($job['workspace'] ?? '');
        if ($workspace === '' || !is_dir($workspace)) {
            return $job;
        }

        $exit_code_path = wp_normalize_path($workspace . '/exit_code.txt');
        $summary_path = wp_normalize_path($workspace . '/summary.json');
        $pid = (int) ($job['pid'] ?? 0);
        $status = (string) ($job['status'] ?? 'queued');
        $process_running = ($pid > 0) ? self::is_load_test_process_running($pid) : false;
        $exit_code = null;

        if (is_file($exit_code_path)) {
            $raw_exit_code = trim((string) file_get_contents($exit_code_path));
            if ($raw_exit_code !== '' && preg_match('/^-?\d+$/', $raw_exit_code)) {
                $exit_code = (int) $raw_exit_code;
            }
        }

        if ($status === 'cancelled') {
            if ($job['finished_at'] === '') {
                $job['finished_at'] = current_time('mysql');
            }
            if (!$process_running) {
                $job['pid'] = 0;
                if ($job['exit_code'] === null) {
                    $job['exit_code'] = self::LOAD_TEST_CANCELLED_EXIT_CODE;
                }
            }
            if ($exit_code !== null) {
                $job['exit_code'] = $exit_code;
            }

            return $job;
        }

        if ($exit_code !== null) {
            $job['exit_code'] = $exit_code;
            $job['status'] = ($exit_code === 0 && is_file($summary_path)) ? 'success' : 'failed';
            if ($job['finished_at'] === '') {
                $job['finished_at'] = current_time('mysql');
            }
            if ($job['status'] === 'failed') {
                $stderr_tail = self::read_load_test_log_tail($job, 'stderr', 20);
                if (
                    $stderr_tail !== '' &&
                    preg_match('/cannot create user data directory|home directories outside of \\/home/i', $stderr_tail)
                ) {
                    $job['notes'] = trim('Runner k6 gagal start karena binary k6 berasal dari Snap dan user PHP ini tidak memiliki HOME yang valid di bawah /home. Install k6 native/non-snap atau konfigurasi Snap home terlebih dahulu.');
                }
            }

            return $job;
        }

        if ($process_running) {
            if ($status !== 'running') {
                $job['status'] = 'running';
            }

            return $job;
        }

        if (in_array($status, ['queued', 'running'], true)) {
            $job['status'] = is_file($summary_path) ? 'success' : 'failed';
            if ($job['status'] === 'success') {
                $job['exit_code'] = 0;
            }
            if ($job['finished_at'] === '') {
                $job['finished_at'] = current_time('mysql');
            }
            if ($job['status'] === 'failed') {
                $stderr_tail = self::read_load_test_log_tail($job, 'stderr', 20);
                if (
                    $stderr_tail !== '' &&
                    preg_match('/cannot create user data directory|home directories outside of \\/home/i', $stderr_tail)
                ) {
                    $job['notes'] = trim('Runner k6 gagal start karena binary k6 berasal dari Snap dan user PHP ini tidak memiliki HOME yang valid di bawah /home. Install k6 native/non-snap atau konfigurasi Snap home terlebih dahulu.');
                }
            }
        }

        return $job;
    }

    private static function is_load_test_process_running(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $exit_code = 1;
        exec('kill -0 ' . (int) $pid . ' >/dev/null 2>&1', $output, $exit_code);
        return $exit_code === 0;
    }

    public static function get_load_test_job_artifacts(array $job): array
    {
        $workspace = wp_normalize_path((string) ($job['workspace'] ?? ''));
        if ($workspace === '') {
            return [];
        }

        return [
            'summary' => [
                'label' => 'Summary JSON',
                'path' => $workspace . '/summary.json',
                'content_type' => 'application/json; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-summary.json'),
            ],
            'stdout' => [
                'label' => 'Stdout Log',
                'path' => $workspace . '/stdout.log',
                'content_type' => 'text/plain; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-stdout.log'),
            ],
            'stderr' => [
                'label' => 'Stderr Log',
                'path' => $workspace . '/stderr.log',
                'content_type' => 'text/plain; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-stderr.log'),
            ],
            'students' => [
                'label' => 'students.json',
                'path' => $workspace . '/students.json',
                'content_type' => 'application/json; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-students.json'),
            ],
            'config' => [
                'label' => 'Config JSON',
                'path' => $workspace . '/config.json',
                'content_type' => 'application/json; charset=utf-8',
                'filename' => sanitize_file_name((string) ($job['id'] ?? 'load-test') . '-config.json'),
            ],
        ];
    }

    public static function read_load_test_job_summary(array $job): array
    {
        $artifacts = self::get_load_test_job_artifacts($job);
        $summary_path = isset($artifacts['summary']['path']) ? (string) $artifacts['summary']['path'] : '';
        if ($summary_path === '' || !is_file($summary_path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($summary_path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $metrics = isset($decoded['metrics']) && is_array($decoded['metrics'])
            ? (array) $decoded['metrics']
            : [];

        return [
            'http_req_failed_rate' => self::extract_load_test_metric_value($metrics, 'http_req_failed', 'rate'),
            'http_req_duration_p95' => self::extract_load_test_metric_value($metrics, 'http_req_duration', 'p(95)'),
            'session_success_rate' => self::extract_load_test_metric_value($metrics, 'exam_session_success', 'rate'),
            'login_success_rate' => self::extract_load_test_metric_value($metrics, 'cbt_stage_login_success', 'rate'),
            'get_exams_success_rate' => self::extract_load_test_metric_value($metrics, 'cbt_stage_get_exams_success', 'rate'),
            'start_attempt_success_rate' => self::extract_load_test_metric_value($metrics, 'cbt_stage_start_attempt_success', 'rate'),
            'get_questions_success_rate' => self::extract_load_test_metric_value($metrics, 'cbt_stage_get_questions_success', 'rate'),
            'submit_single_success_rate' => self::extract_load_test_metric_value($metrics, 'cbt_stage_submit_single_success', 'rate'),
            'submit_batch_success_rate' => self::extract_load_test_metric_value($metrics, 'cbt_stage_submit_batch_success', 'rate'),
            'finish_exam_success_rate' => self::extract_load_test_metric_value($metrics, 'cbt_stage_finish_exam_success', 'rate'),
            'iterations' => self::extract_load_test_metric_value($metrics, 'iterations', 'count'),
        ];
    }

    private static function extract_load_test_metric_value(array $metrics, string $metric_key, string $value_key): ?float
    {
        if (!isset($metrics[$metric_key]) || !is_array($metrics[$metric_key])) {
            return null;
        }

        $metric = (array) $metrics[$metric_key];
        $values = isset($metric['values']) && is_array($metric['values']) ? (array) $metric['values'] : [];
        if (!isset($values[$value_key])) {
            return null;
        }

        $value = $values[$value_key];
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    public static function read_load_test_log_tail(array $job, string $artifact_key, int $line_limit = 8): string
    {
        $artifacts = self::get_load_test_job_artifacts($job);
        $path = isset($artifacts[$artifact_key]['path']) ? (string) $artifacts[$artifact_key]['path'] : '';
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || empty($lines)) {
            return '';
        }

        return trim(implode("\n", array_slice($lines, -1 * max(1, $line_limit))));
    }

    public static function get_load_test_status_meta(string $status): array
    {
        switch ($status) {
            case 'running':
                return ['label' => 'Running', 'tone' => 'running'];
            case 'success':
                return ['label' => 'Success', 'tone' => 'done'];
            case 'failed':
                return ['label' => 'Failed', 'tone' => 'danger'];
            case 'cancelled':
                return ['label' => 'Cancelled', 'tone' => 'idle'];
            case 'queued':
            default:
                return ['label' => 'Queued', 'tone' => 'idle'];
        }
    }

    public static function format_load_test_datetime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date('d M Y, H:i:s', $timestamp);
    }

    public static function get_load_test_job_selection_label(array $job): string
    {
        $job = self::normalize_load_test_job($job);
        $status_meta = self::get_load_test_status_meta((string) ($job['status'] ?? 'queued'));
        $run_at = self::format_load_test_datetime((string) (($job['started_at'] ?? '') !== '' ? $job['started_at'] : ($job['created_at'] ?? '')));
        $scenario_label = sanitize_text_field((string) (($job['profile']['scenario_label'] ?? '')));
        $load_shape_label = sanitize_text_field((string) (($job['profile']['load_shape_label'] ?? '')));
        $shape_value = (string) (
            (($job['profile']['load_shape'] ?? '') === 'ramping_vus')
                ? max(0, (int) (($job['profile']['peak_vus'] ?? 0)))
                : max(0, (int) (($job['profile']['vus'] ?? 0)))
        );

        $parts = [
            (string) ($job['exam_title'] ?? 'Exam'),
        ];
        if ($scenario_label !== '') {
            $parts[] = $scenario_label;
        }
        if ($load_shape_label !== '' && $shape_value !== '0') {
            $parts[] = $load_shape_label . ' ' . $shape_value;
        }
        $parts[] = (string) ($status_meta['label'] ?? 'Queued');
        $parts[] = $run_at;

        return trim(implode(' · ', $parts));
    }

    private static function build_load_test_command_preview(
        array $profile,
        string $base_url,
        int $exam_id,
        string $exam_token,
        string $k6_path,
        string $workspace
    ): string {
        $command_parts = [
            'cd ' . escapeshellarg($workspace),
            'BASE_URL=' . escapeshellarg($base_url),
            'EXAM_ID=' . (int) $exam_id,
        ];
        if ($exam_token !== '') {
            $command_parts[] = 'EXAM_TOKEN=' . escapeshellarg($exam_token);
        }
        $command_parts[] = 'LOAD_SHAPE=' . escapeshellarg((string) ($profile['load_shape'] ?? self::get_load_test_default_load_shape()));
        if ((string) ($profile['load_shape'] ?? '') === 'ramping_vus') {
            $command_parts[] = 'PEAK_VUS=' . (int) ($profile['peak_vus'] ?? 0);
            $command_parts[] = 'WARMUP_DURATION=' . escapeshellarg((string) ($profile['warmup_duration'] ?? '1m'));
            $command_parts[] = 'RAMP_UP_DURATION=' . escapeshellarg((string) ($profile['ramp_up_duration'] ?? '2m'));
            $command_parts[] = 'STEADY_DURATION=' . escapeshellarg((string) ($profile['steady_duration'] ?? '5m'));
            $command_parts[] = 'RAMP_DOWN_DURATION=' . escapeshellarg((string) ($profile['ramp_down_duration'] ?? '1m'));
            $command_parts[] = 'RAMP_STEPS=' . (int) ($profile['ramp_steps'] ?? 2);
        } else {
            $command_parts[] = 'VUS=' . (int) ($profile['vus'] ?? 0);
            $command_parts[] = 'ITERATIONS=' . (int) ($profile['iterations'] ?? 1);
        }
        $command_parts[] = 'QUESTIONS_PER_USER=' . (int) ($profile['questions_per_user'] ?? 0);
        $command_parts[] = 'SESSION_START_SPREAD_MS=' . (int) ($profile['session_start_spread_ms'] ?? 0);
        $command_parts[] = 'POST_START_SPREAD_MS=' . (int) ($profile['post_start_spread_ms'] ?? 0);
        $command_parts[] = 'SCENARIO_KEY=' . escapeshellarg((string) ($profile['scenario_key'] ?? self::get_load_test_default_scenario_key()));
        $command_parts[] = 'MAX_DURATION=' . escapeshellarg((string) ($profile['max_duration'] ?? '45m'));
        $command_parts[] = escapeshellarg($k6_path) . ' run --summary-export summary.json cbt_exam_1000_users.js';

        return implode(" \\\n  ", $command_parts);
    }

    private static function validate_load_test_profile(array $profile): ?string
    {
        $load_shape = (string) ($profile['load_shape'] ?? self::get_load_test_default_load_shape());
        if ($load_shape !== 'ramping_vus') {
            if ((int) ($profile['vus'] ?? 0) < 1) {
                return 'VUs minimal 1 untuk profile flat.';
            }
            if ((int) ($profile['iterations'] ?? 0) < 1) {
                return 'Iterations minimal 1 untuk profile flat.';
            }

            return null;
        }

        if ((int) ($profile['peak_vus'] ?? 0) < 1) {
            return 'Peak VUs minimal 1 untuk profile ramping.';
        }
        if ((int) ($profile['ramp_steps'] ?? 0) < 1) {
            return 'Ramp steps minimal 1 untuk profile ramping.';
        }
        if (self::convert_load_test_duration_to_seconds((string) ($profile['steady_duration'] ?? '0s')) <= 0) {
            return 'Steady duration wajib lebih dari 0 detik untuk profile ramping.';
        }
        if (empty($profile['compiled_stages']) || !is_array($profile['compiled_stages'])) {
            return 'Profile ramping tidak menghasilkan stage k6 yang valid.';
        }

        return null;
    }

    private static function ensure_load_test_runtime_root()
    {
        $upload = wp_upload_dir();
        if (!is_array($upload) || !empty($upload['error']) || empty($upload['basedir'])) {
            return new WP_Error('load_test_upload_missing', 'Folder uploads WordPress tidak tersedia untuk runtime load test.');
        }

        $runtime_root = trailingslashit((string) $upload['basedir']) . self::LOAD_TEST_RUNTIME_DIRECTORY;
        if (!wp_mkdir_p($runtime_root) || !is_dir($runtime_root)) {
            return new WP_Error('load_test_runtime_root_failed', 'Gagal membuat folder runtime load test di uploads.');
        }

        self::protect_load_test_runtime_root($runtime_root);

        return wp_normalize_path($runtime_root);
    }

    private static function protect_load_test_runtime_root(string $runtime_root): void
    {
        $runtime_root = wp_normalize_path($runtime_root);
        if ($runtime_root === '' || !is_dir($runtime_root)) {
            return;
        }

        $index_file = $runtime_root . '/index.php';
        if (!is_file($index_file)) {
            @file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }

        $htaccess_file = $runtime_root . '/.htaccess';
        if (!is_file($htaccess_file)) {
            @file_put_contents(
                $htaccess_file,
                "Order Deny,Allow\nDeny from all\n"
            );
        }
    }

    private static function build_load_test_job_id(int $exam_id): string
    {
        return sanitize_key(
            'lt'
            . gmdate('YmdHis')
            . 'e'
            . max(0, $exam_id)
            . strtolower((string) wp_generate_password(6, false, false))
        );
    }

    private static function prepare_load_test_job_workspace(
        string $runtime_root,
        string $job_id,
        array $student_rows,
        array $exam,
        array $profile,
        string $base_url,
        string $resolved_token,
        string $token_source,
        string $k6_path
    ) {
        $workspace = wp_normalize_path(trailingslashit($runtime_root) . $job_id);
        if (!wp_mkdir_p($workspace) || !is_dir($workspace)) {
            return new WP_Error('load_test_workspace_failed', 'Gagal membuat workspace job load test.');
        }

        @chmod($workspace, 0700);
        $script_source = wp_normalize_path(CBT_EXAM_SYSTEM_PATH . 'performance/load-test/k6/cbt_exam_1000_users.js');
        if (!is_file($script_source)) {
            return new WP_Error('load_test_script_missing', 'Script k6 cbt_exam_1000_users.js tidak ditemukan.');
        }

        $script_target = $workspace . '/cbt_exam_1000_users.js';
        if (!@copy($script_source, $script_target)) {
            return new WP_Error('load_test_script_copy_failed', 'Gagal menyalin script k6 ke workspace runtime.');
        }

        $students_payload = [];
        foreach ($student_rows as $student_row) {
            $students_payload[] = [
                'identifier' => (string) ($student_row['identifier'] ?? ''),
                'password' => (string) ($student_row['password'] ?? ''),
            ];
        }

        $students_json = wp_json_encode($students_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($students_json) || $students_json === '') {
            return new WP_Error('load_test_students_json_failed', 'Gagal membuat students.json untuk runner load test.');
        }

        if (@file_put_contents($workspace . '/students.json', $students_json) === false) {
            return new WP_Error('load_test_students_write_failed', 'Gagal menulis students.json ke workspace load test.');
        }

        $command_preview = self::build_load_test_command_preview(
            $profile,
            $base_url,
            (int) ($exam['id'] ?? 0),
            $resolved_token,
            $k6_path,
            $workspace
        );

        $config = [
            'job_id' => $job_id,
            'created_at' => current_time('mysql'),
            'exam' => [
                'id' => (int) ($exam['id'] ?? 0),
                'title' => (string) ($exam['title'] ?? 'Exam'),
                'subject_name' => (string) ($exam['subject_name'] ?? ''),
            ],
            'profile' => $profile,
            'scenario' => [
                'key' => (string) ($profile['scenario_key'] ?? self::get_load_test_default_scenario_key()),
                'label' => (string) ($profile['scenario_label'] ?? ''),
                'description' => (string) ($profile['scenario_description'] ?? ''),
                'endpoint_summary' => (string) ($profile['scenario_endpoint_summary'] ?? ''),
            ],
            'base_url' => $base_url,
            'exam_token' => $resolved_token,
            'token_source' => $token_source,
            'k6_path' => $k6_path,
            'student_count' => count($student_rows),
            'command_preview' => $command_preview,
        ];

        $config_json = wp_json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($config_json) || @file_put_contents($workspace . '/config.json', $config_json) === false) {
            return new WP_Error('load_test_config_failed', 'Gagal menulis config.json untuk runner load test.');
        }

        $run_script = self::build_load_test_runner_script(
            $workspace,
            $profile,
            $base_url,
            (int) ($exam['id'] ?? 0),
            $resolved_token,
            $k6_path
        );
        if (@file_put_contents($workspace . '/run.sh', $run_script) === false) {
            return new WP_Error('load_test_runner_script_failed', 'Gagal menulis run.sh untuk background runner.');
        }
        @chmod($workspace . '/run.sh', 0700);

        return [
            'workspace' => $workspace,
            'command_preview' => $command_preview,
        ];
    }

    private static function build_load_test_runner_script(
        string $workspace,
        array $profile,
        string $base_url,
        int $exam_id,
        string $resolved_token,
        string $k6_path
    ): string {
        $runner_home_meta = self::get_load_test_runner_home_meta($k6_path);
        $home_dir = (string) ($runner_home_meta['path'] ?? '');

        $lines = [
            '#!/bin/sh',
            'cd ' . escapeshellarg($workspace) . ' || exit 1',
            'umask 077',
            ': > stdout.log',
            ': > stderr.log',
            'rm -f exit_code.txt',
            'export PATH=' . escapeshellarg('/snap/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'),
            'export BASE_URL=' . escapeshellarg($base_url),
            'export EXAM_ID=' . escapeshellarg((string) $exam_id),
            'export LOAD_SHAPE=' . escapeshellarg((string) ($profile['load_shape'] ?? self::get_load_test_default_load_shape())),
            'export VUS=' . escapeshellarg((string) ((int) ($profile['vus'] ?? 0))),
            'export ITERATIONS=' . escapeshellarg((string) ((int) ($profile['iterations'] ?? 1))),
            'export PEAK_VUS=' . escapeshellarg((string) ((int) ($profile['peak_vus'] ?? 0))),
            'export WARMUP_DURATION=' . escapeshellarg((string) ($profile['warmup_duration'] ?? '1m')),
            'export RAMP_UP_DURATION=' . escapeshellarg((string) ($profile['ramp_up_duration'] ?? '2m')),
            'export STEADY_DURATION=' . escapeshellarg((string) ($profile['steady_duration'] ?? '5m')),
            'export RAMP_DOWN_DURATION=' . escapeshellarg((string) ($profile['ramp_down_duration'] ?? '1m')),
            'export RAMP_STEPS=' . escapeshellarg((string) ((int) ($profile['ramp_steps'] ?? 2))),
            'export QUESTIONS_PER_USER=' . escapeshellarg((string) ((int) ($profile['questions_per_user'] ?? 0))),
            'export SESSION_START_SPREAD_MS=' . escapeshellarg((string) ((int) ($profile['session_start_spread_ms'] ?? 0))),
            'export POST_START_SPREAD_MS=' . escapeshellarg((string) ((int) ($profile['post_start_spread_ms'] ?? 0))),
            'export SCENARIO_KEY=' . escapeshellarg((string) ($profile['scenario_key'] ?? self::get_load_test_default_scenario_key())),
            'export MAX_DURATION=' . escapeshellarg((string) ($profile['max_duration'] ?? '45m')),
            'export STRICT_EXAM_ID=' . escapeshellarg('1'),
            'export SKIP_EXAMS_REQUEST=' . escapeshellarg('1'),
        ];

        if ($home_dir !== '') {
            array_splice($lines, 6, 0, ['export HOME=' . escapeshellarg($home_dir)]);
        }

        if ($resolved_token !== '') {
            $lines[] = 'export EXAM_TOKEN=' . escapeshellarg($resolved_token);
        }

        $lines[] = escapeshellarg($k6_path) . ' run --summary-export summary.json cbt_exam_1000_users.js >> stdout.log 2>> stderr.log';
        $lines[] = 'status=$?';
        $lines[] = 'printf \'%s\' "$status" > exit_code.txt';
        $lines[] = 'exit "$status"';

        return implode("\n", $lines) . "\n";
    }

    private static function spawn_load_test_process(string $run_script_path): int
    {
        if (!function_exists('exec')) {
            return 0;
        }

        $output = [];
        $exit_code = 1;
        exec('nohup sh ' . escapeshellarg($run_script_path) . ' >/dev/null 2>&1 & echo $!', $output, $exit_code);
        if ($exit_code !== 0 || empty($output)) {
            return 0;
        }

        return max(0, (int) trim((string) end($output)));
    }

    private static function terminate_load_test_process(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $terminated = false;
        if (function_exists('posix_kill')) {
            $sigterm = defined('SIGTERM') ? (int) constant('SIGTERM') : 15;
            $sigkill = defined('SIGKILL') ? (int) constant('SIGKILL') : 9;

            $terminated = @posix_kill($pid, $sigterm);
            usleep(250000);
            if (@posix_kill($pid, 0)) {
                @posix_kill($pid, $sigkill);
            }
            return $terminated;
        }

        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $exit_code = 1;
        exec('kill ' . (int) $pid . ' >/dev/null 2>&1', $output, $exit_code);
        $terminated = ($exit_code === 0);
        usleep(250000);
        exec('kill -9 ' . (int) $pid . ' >/dev/null 2>&1', $output, $exit_code);

        return $terminated;
    }

    private static function delete_load_test_workspace(string $workspace): bool
    {
        $workspace = wp_normalize_path(trim($workspace));
        if ($workspace === '') {
            return true;
        }
        if (!file_exists($workspace)) {
            return true;
        }

        $upload = wp_upload_dir();
        if (!is_array($upload) || !empty($upload['error']) || empty($upload['basedir'])) {
            return false;
        }

        $runtime_root = wp_normalize_path(trailingslashit((string) $upload['basedir']) . self::LOAD_TEST_RUNTIME_DIRECTORY);
        $runtime_root = rtrim($runtime_root, '/');
        if ($runtime_root === '' || strpos($workspace, $runtime_root . '/') !== 0) {
            return false;
        }

        if (is_file($workspace)) {
            return @unlink($workspace);
        }

        $entries = @scandir($workspace);
        if (!is_array($entries)) {
            return false;
        }

        $all_removed = true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = wp_normalize_path($workspace . '/' . $entry);
            if (is_dir($child)) {
                if (!self::delete_load_test_workspace($child)) {
                    $all_removed = false;
                }
                continue;
            }

            if (file_exists($child) && !@unlink($child)) {
                $all_removed = false;
            }
        }

        if (!@rmdir($workspace)) {
            $all_removed = false;
        }

        return $all_removed;
    }

    private static function clear_load_test_runtime_root_contents(): array
    {
        $upload = wp_upload_dir();
        if (!is_array($upload) || !empty($upload['error']) || empty($upload['basedir'])) {
            return ['removed' => 0, 'failed' => 0];
        }

        $runtime_root = wp_normalize_path(trailingslashit((string) $upload['basedir']) . self::LOAD_TEST_RUNTIME_DIRECTORY);
        if ($runtime_root === '' || !is_dir($runtime_root)) {
            return ['removed' => 0, 'failed' => 0];
        }

        $entries = @scandir($runtime_root);
        if (!is_array($entries)) {
            return ['removed' => 0, 'failed' => 0];
        }

        $removed = 0;
        $failed = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php' || $entry === '.htaccess') {
                continue;
            }

            $child = wp_normalize_path($runtime_root . '/' . $entry);
            if (self::delete_load_test_workspace($child)) {
                $removed++;
            } else {
                $failed++;
            }
        }

        return [
            'removed' => $removed,
            'failed' => $failed,
        ];
    }

    private static function get_load_test_job_by_id(string $job_id): ?array
    {
        $job_id = sanitize_key($job_id);
        if ($job_id === '') {
            return null;
        }

        $jobs = self::sync_load_test_jobs();
        return isset($jobs[$job_id]) ? (array) $jobs[$job_id] : null;
    }

    public static function handle_start_load_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_start_load_test');
        CBT_Admin_Maintenance_Common::prepare_runtime_for_bulk_user_import();

        $student_pool = self::get_load_test_student_pool();
        if (empty($student_pool['rows'])) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Belum ada bulk students dengan password plain-text yang siap dipakai untuk load test.', 'load');
        }

        $runtime = self::get_load_test_runtime_snapshot();
        if (empty($runtime['shell_available']) || empty($runtime['exec_available'])) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Shell PHP untuk background runner belum tersedia. Pastikan fungsi exec aktif.', 'load');
        }
        if ((string) ($runtime['k6_path'] ?? '') === '') {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Binary k6 tidak ditemukan pada server ini.', 'load');
        }
        if (
            (string) ($runtime['k6_install_mode'] ?? '') === 'snap' &&
            empty($runtime['runner_home_supported'])
        ) {
            $detected_home = (string) ($runtime['runner_home_detected'] ?? '');
            $detected_copy = $detected_home !== '' ? ' HOME terdeteksi: ' . $detected_home . '.' : '';
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                null,
                'Binary k6 yang terdeteksi berasal dari Snap, tetapi user PHP ini tidak punya HOME valid di bawah /home sehingga runner admin akan gagal start.' . $detected_copy . ' Install k6 native/non-snap atau konfigurasi Snap home terlebih dahulu.',
                'load'
            );
        }

        $selected_exam_ids = isset($_POST['exam_ids']) && is_array($_POST['exam_ids'])
            ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['exam_ids'])))))
            : [];
        if (empty($selected_exam_ids)) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Pilih minimal satu exam aktif untuk memulai load test.', 'load');
        }

        $catalog = self::get_load_test_exam_catalog();
        $eligible_map = [];
        foreach ((array) $catalog['eligible'] as $exam_row) {
            $eligible_map[(int) ($exam_row['id'] ?? 0)] = (array) $exam_row;
        }

        $selected_exams = [];
        $invalid_exams = [];
        foreach ($selected_exam_ids as $exam_id) {
            if (!isset($eligible_map[$exam_id])) {
                $invalid_exams[] = '#' . $exam_id;
                continue;
            }
            $selected_exams[$exam_id] = $eligible_map[$exam_id];
        }
        if (!empty($invalid_exams)) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                null,
                'Ada exam yang tidak valid untuk load test: ' . implode(', ', $invalid_exams) . '. Hanya exam published, aktif, dan punya soal yang bisa dijalankan.',
                'load'
            );
        }

        $profile = self::normalize_load_test_profile($_POST);
        $profile_error = self::validate_load_test_profile($profile);
        if ($profile_error !== null) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, $profile_error, 'load');
        }
        $base_url = self::normalize_load_test_base_url($_POST);
        $manual_token = self::normalize_load_test_token_override(
            isset($_POST['manual_exam_token']) ? (string) wp_unslash($_POST['manual_exam_token']) : ''
        );
        $resolved_token = $manual_token !== ''
            ? $manual_token
            : self::normalize_load_test_token_override((string) (($runtime['global_token_meta']['token'] ?? '')));
        $token_source = $manual_token !== '' ? 'manual' : 'global';

        $runtime_root = self::ensure_load_test_runtime_root();
        if (is_wp_error($runtime_root)) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, $runtime_root->get_error_message(), 'load');
        }

        $jobs = self::get_load_test_jobs_option_map();
        $started_count = 0;
        $failed_labels = [];
        foreach ($selected_exams as $exam) {
            $job_id = self::build_load_test_job_id((int) ($exam['id'] ?? 0));
            $workspace_result = self::prepare_load_test_job_workspace(
                (string) $runtime_root,
                $job_id,
                (array) $student_pool['rows'],
                $exam,
                $profile,
                $base_url,
                $resolved_token,
                $token_source,
                (string) ($runtime['k6_path'] ?? '')
            );

            if (is_wp_error($workspace_result)) {
                $failed_labels[] = (string) ($exam['title'] ?? ('Exam #' . (int) ($exam['id'] ?? 0)));
                continue;
            }

            $workspace_data = is_array($workspace_result) ? $workspace_result : [];
            $job = self::normalize_load_test_job([
                'id' => $job_id,
                'user_id' => get_current_user_id(),
                'status' => 'queued',
                'pid' => 0,
                'exam_id' => (int) ($exam['id'] ?? 0),
                'exam_title' => (string) ($exam['title'] ?? 'Exam'),
                'subject_name' => (string) ($exam['subject_name'] ?? ''),
                'workspace' => (string) ($workspace_data['workspace'] ?? ''),
                'created_at' => current_time('mysql'),
                'started_at' => current_time('mysql'),
                'base_url' => $base_url,
                'token_source' => $token_source,
                'manual_token' => $manual_token,
                'student_count' => (int) ($student_pool['valid_count'] ?? 0),
                'profile' => $profile,
                'command_preview' => (string) ($workspace_data['command_preview'] ?? ''),
                'notes' => trim(
                    (((int) ($student_pool['valid_count'] ?? 0) < (int) ($profile['effective_vus'] ?? $profile['vus'] ?? 0))
                        ? 'Jumlah siswa bulk lebih kecil dari target VUs, akun akan di-reuse oleh script k6. '
                        : '')
                    . (((string) ($runtime['k6_install_mode'] ?? '') === 'snap')
                        ? 'Runner memakai binary Snap; untuk hasil paling stabil disarankan install k6 native/non-snap.'
                        : '')
                ),
            ]);

            $pid = self::spawn_load_test_process((string) ($job['workspace'] ?? '') . '/run.sh');
            if ($pid <= 0) {
                $job['status'] = 'failed';
                $job['finished_at'] = current_time('mysql');
                $job['notes'] = trim((string) $job['notes'] . ' Gagal menjalankan background runner shell.');
                $jobs[$job_id] = $job;
                $failed_labels[] = (string) ($exam['title'] ?? ('Exam #' . (int) ($exam['id'] ?? 0)));
                continue;
            }

            $job['status'] = 'running';
            $job['pid'] = $pid;
            $jobs[$job_id] = $job;
            $started_count++;
        }

        self::save_load_test_jobs_option_map($jobs);
        if ($started_count <= 0) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                null,
                'Tidak ada job load test yang berhasil dimulai. Periksa ketersediaan runner k6 dan izin tulis uploads.',
                'load'
            );
        }

        $message = sprintf('%d job load test dimulai.', $started_count);
        if (!empty($failed_labels)) {
            $message .= ' Sebagian exam gagal start: ' . implode(', ', $failed_labels) . '.';
        }
        if ((int) ($student_pool['valid_count'] ?? 0) < (int) ($profile['effective_vus'] ?? $profile['vus'] ?? 0)) {
            $message .= ' User siswa lebih sedikit dari target VUs, jadi script akan me-reuse akun.';
        }

        CBT_Admin_Maintenance_Common::redirect_maintenance_page($message, null, 'load');
    }

    public static function handle_cancel_load_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $job_id = isset($_POST['job_id']) ? sanitize_key((string) wp_unslash($_POST['job_id'])) : '';
        check_admin_referer('cbt_cancel_load_test_' . $job_id);

        $jobs = self::sync_load_test_jobs();
        if ($job_id === '' || !isset($jobs[$job_id])) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Job load test tidak ditemukan.', 'load');
        }

        $job = self::normalize_load_test_job((array) $jobs[$job_id]);
        $current_status = (string) ($job['status'] ?? 'queued');
        if (!in_array($current_status, ['queued', 'running'], true)) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                'Job load test ini sudah tidak aktif lagi.',
                null,
                'load'
            );
        }

        $pid = (int) ($job['pid'] ?? 0);
        $terminate_result = false;
        if ($pid > 0) {
            $terminate_result = self::terminate_load_test_process($pid);
        }

        $job['status'] = 'cancelled';
        $job['finished_at'] = current_time('mysql');
        $job['pid'] = 0;
        if ($job['exit_code'] === null) {
            $job['exit_code'] = self::LOAD_TEST_CANCELLED_EXIT_CODE;
        }

        $notes = trim((string) ($job['notes'] ?? ''));
        $cancel_note = $terminate_result
            ? 'Dibatalkan dari CBT Maintenance. Sinyal stop berhasil dikirim ke runner.'
            : 'Dibatalkan dari CBT Maintenance. Runner ditandai cancelled meski sinyal stop tidak terkonfirmasi.';
        $job['notes'] = trim($notes . ' ' . $cancel_note);
        $jobs[$job_id] = $job;
        self::save_load_test_jobs_option_map($jobs);

        $message = $terminate_result || $pid <= 0
            ? 'Job load test berhasil dibatalkan.'
            : 'Job load test ditandai cancelled, tetapi sinyal stop ke runner tidak terkonfirmasi.';
        CBT_Admin_Maintenance_Common::redirect_maintenance_page($message, null, 'load');
    }

    public static function handle_delete_load_test_job(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $job_id = isset($_POST['job_id']) ? sanitize_key((string) wp_unslash($_POST['job_id'])) : '';
        check_admin_referer('cbt_delete_load_test_job_' . $job_id);

        $jobs = self::get_load_test_jobs_option_map();
        if ($job_id === '' || !isset($jobs[$job_id])) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Hasil load test tidak ditemukan.', 'load');
        }

        $job = self::normalize_load_test_job((array) $jobs[$job_id]);
        $pid = (int) ($job['pid'] ?? 0);
        if ($pid > 0 && in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)) {
            self::terminate_load_test_process($pid);
        }

        $workspace_removed = self::delete_load_test_workspace((string) ($job['workspace'] ?? ''));
        unset($jobs[$job_id]);
        self::save_load_test_jobs_option_map($jobs);

        $message = 'Hasil load test berhasil dihapus.';
        if (!$workspace_removed) {
            $message .= ' Workspace job perlu dibersihkan manual.';
        }

        CBT_Admin_Maintenance_Common::redirect_maintenance_page($message, null, 'load');
    }

    public static function handle_clear_load_test_jobs(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_load_test_jobs');

        $jobs = self::sync_load_test_jobs();
        $removed_jobs = 0;
        $stopped_jobs = 0;
        $workspace_failures = 0;

        foreach ($jobs as $job) {
            $job = self::normalize_load_test_job((array) $job);
            if ($job['id'] === '') {
                continue;
            }

            $pid = (int) ($job['pid'] ?? 0);
            if ($pid > 0 && in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)) {
                self::terminate_load_test_process($pid);
                $stopped_jobs++;
            }

            if (!self::delete_load_test_workspace((string) ($job['workspace'] ?? ''))) {
                $workspace_failures++;
            }
            $removed_jobs++;
        }

        delete_option(self::LOAD_TEST_JOBS_OPTION);

        $runtime_cleanup = self::clear_load_test_runtime_root_contents();
        $runtime_removed = (int) ($runtime_cleanup['removed'] ?? 0);
        $runtime_failed = (int) ($runtime_cleanup['failed'] ?? 0);

        if ($removed_jobs <= 0 && $runtime_removed <= 0 && $runtime_failed <= 0) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(null, 'Tidak ada histori load test yang perlu dihapus.', 'load');
        }

        $message_parts = [];
        if ($removed_jobs > 0) {
            $message_parts[] = sprintf('%d histori load test dihapus.', $removed_jobs);
        }
        if ($stopped_jobs > 0) {
            $message_parts[] = sprintf('%d job aktif dihentikan.', $stopped_jobs);
        }
        if ($runtime_removed > 0) {
            $message_parts[] = sprintf('%d workspace sisa dibersihkan.', $runtime_removed);
        }

        $message = !empty($message_parts)
            ? implode(' ', $message_parts)
            : 'Histori load test berhasil dibersihkan.';

        $needs_manual_cleanup = $runtime_failed > 0 || ($workspace_failures > 0 && $runtime_removed <= 0);
        if ($needs_manual_cleanup) {
            $message .= ' Sebagian workspace masih perlu dibersihkan manual.';
        }

        CBT_Admin_Maintenance_Common::redirect_maintenance_page($message, null, 'load');
    }

    public static function handle_download_load_test_artifact(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $job_id = isset($_GET['job_id']) ? sanitize_key((string) wp_unslash($_GET['job_id'])) : '';
        $artifact_key = isset($_GET['artifact']) ? sanitize_key((string) wp_unslash($_GET['artifact'])) : '';
        check_admin_referer('cbt_download_load_test_artifact_' . $job_id . '_' . $artifact_key);

        $job = self::get_load_test_job_by_id($job_id);
        if (!is_array($job)) {
            wp_die('Job load test tidak ditemukan.');
        }

        $artifacts = self::get_load_test_job_artifacts($job);
        if (!isset($artifacts[$artifact_key]) || !is_array($artifacts[$artifact_key])) {
            wp_die('Artifact load test tidak valid.');
        }

        $artifact = (array) $artifacts[$artifact_key];
        $path = isset($artifact['path']) ? wp_normalize_path((string) $artifact['path']) : '';
        if ($path === '' || !is_file($path)) {
            wp_die('File artifact belum tersedia.');
        }

        nocache_headers();
        header('Content-Type: ' . (string) ($artifact['content_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . (string) ($artifact['filename'] ?? basename($path)) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    public static function handle_export_load_test_students_json(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_export_load_test_students_json');
        $student_pool = self::get_load_test_student_pool();
        if (empty($student_pool['rows'])) {
            wp_die('Belum ada bulk students valid yang bisa diexport.');
        }

        $payload = [];
        foreach ((array) $student_pool['rows'] as $row) {
            $payload[] = [
                'identifier' => (string) ($row['identifier'] ?? ''),
                'password' => (string) ($row['password'] ?? ''),
            ];
        }

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            wp_die('Gagal membuat students.json.');
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt-load-test-students.json"');
        echo $json;
        exit;
    }

    public static function handle_export_load_test_students_csv(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_export_load_test_students_csv');
        $student_pool = self::get_load_test_student_pool();
        if (empty($student_pool['rows'])) {
            wp_die('Belum ada bulk students valid yang bisa diexport.');
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cbt-load-test-students.csv"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            wp_die('Gagal menulis file CSV.');
        }

        fputcsv($output, ['name', 'email', 'nisn', 'username', 'password', 'role', 'kode_kelas', 'kode_ruang', 'agama', 'jenis_kelamin', 'foto']);
        foreach ((array) $student_pool['rows'] as $row) {
            fputcsv($output, [
                (string) ($row['name'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['nisn'] ?? ''),
                (string) ($row['username'] ?? ''),
                (string) ($row['password'] ?? ''),
                'siswa',
                (string) ($row['kode_kelas'] ?? ''),
                (string) ($row['kode_ruang'] ?? ''),
                (string) ($row['agama'] ?? ''),
                (string) ($row['jenis_kelamin'] ?? ''),
                (string) ($row['foto'] ?? ''),
            ]);
        }
        fclose($output);
        exit;
    }

    public static function handle_export_load_test_students_xlsx(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_export_load_test_students_xlsx');
        $student_pool = self::get_load_test_student_pool();
        if (empty($student_pool['rows'])) {
            wp_die('Belum ada bulk students valid yang bisa diexport.');
        }
        if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') || !class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx')) {
            wp_die('Library XLSX belum terpasang. Jalankan composer install pada plugin CBT.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [
            ['name', 'email', 'nisn', 'username', 'password', 'role', 'kode_kelas', 'kode_ruang', 'agama', 'jenis_kelamin', 'foto'],
        ];
        foreach ((array) $student_pool['rows'] as $row) {
            $rows[] = [
                (string) ($row['name'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['nisn'] ?? ''),
                (string) ($row['username'] ?? ''),
                (string) ($row['password'] ?? ''),
                'siswa',
                (string) ($row['kode_kelas'] ?? ''),
                (string) ($row['kode_ruang'] ?? ''),
                (string) ($row['agama'] ?? ''),
                (string) ($row['jenis_kelamin'] ?? ''),
                (string) ($row['foto'] ?? ''),
            ];
        }
        $sheet->fromArray($rows, null, 'A1');

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="cbt-load-test-students.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public static function handle_load_test_jobs_ajax(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('cbt_load_test_jobs', 'nonce');
        $jobs = self::sync_load_test_jobs();
        $running_count = 0;
        foreach ($jobs as $job) {
            if (in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)) {
                $running_count++;
            }
        }

        wp_send_json_success([
            'html' => CBT_Admin_Maintenance_Load_Test_Presenter::render_jobs_markup($jobs),
            'running_count' => $running_count,
            'job_count' => count($jobs),
            'refreshed_at' => current_time('mysql'),
        ]);
    }

    private static function split_target_kelas_csv($raw): array
    {
        return CBT_Admin_Exams_Service::split_target_kelas_csv($raw);
    }

    private static function normalize_target_kelas_csv($raw): string
    {
        return CBT_Admin_Exams_Service::normalize_target_kelas_csv($raw);
    }

}
