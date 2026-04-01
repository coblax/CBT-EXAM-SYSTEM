<?php

declare(strict_types=1);

/**
 * @return never
 */
function e2e_fixture_fail(string $message, array $context = [], int $exit_code = 1): void
{
    $payload = ['ok' => false, 'message' => $message];
    if (!empty($context)) {
        $payload['context'] = $context;
    }

    $encoded = function_exists('wp_json_encode')
        ? wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    fwrite(STDERR, (string) $encoded . PHP_EOL);
    exit($exit_code);
}

/**
 * @return never
 */
function e2e_fixture_success(array $payload): void
{
    $payload['ok'] = true;
    $encoded = function_exists('wp_json_encode')
        ? wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo (string) $encoded . PHP_EOL;
    exit(0);
}

function e2e_fixture_bootstrap_wordpress(): void
{
    $scan = __DIR__;
    while ($scan !== dirname($scan)) {
        $candidate = $scan . '/wp-load.php';
        if (is_file($candidate)) {
            require_once $candidate;
            return;
        }
        $scan = dirname($scan);
    }

    e2e_fixture_fail('WordPress bootstrap tidak ditemukan. Pastikan helper E2E dijalankan dari repo plugin CBT.');
}

function e2e_fixture_read_payload(array $argv): array
{
    if (!isset($argv[2])) {
        return [];
    }

    $decoded = json_decode((string) $argv[2], true);
    return is_array($decoded) ? $decoded : [];
}

function e2e_fixture_require_class(string $class_name): void
{
    if (class_exists($class_name, false)) {
        return;
    }

    $plugin_bootstrap = dirname(__DIR__, 3) . '/cbt-exam-system.php';
    if (is_file($plugin_bootstrap)) {
        require_once $plugin_bootstrap;
    }

    if (!class_exists($class_name, false)) {
        e2e_fixture_fail('Class yang dibutuhkan tidak tersedia setelah WordPress dibootstrap.', [
            'class_name' => $class_name,
        ]);
    }
}

function e2e_fixture_bootstrap_classes(): void
{
    e2e_fixture_require_class(CBT_Admin_Maintenance_Service::class);
    e2e_fixture_require_class(CBT_Admin_Security_Service::class);
    e2e_fixture_require_class(CBT_Admin_Exams_Service::class);
    e2e_fixture_require_class(CBT_Auth::class);
    e2e_fixture_require_class(CBT_UI_State::class);
    e2e_fixture_require_class(CBT_Cache::class);
    e2e_fixture_require_class(CBT_Security_Log::class);
}

function e2e_fixture_resolve_user_role(WP_User $user): string
{
    $roles = array_values(array_map('strval', (array) $user->roles));

    if (in_array('administrator', $roles, true) || in_array('admin_cbt', $roles, true)) {
        return 'admin';
    }

    if (
        in_array('guru_cbt', $roles, true) ||
        in_array('teacher', $roles, true) ||
        in_array('editor', $roles, true)
    ) {
        return 'guru';
    }

    if (
        in_array('siswa_cbt', $roles, true) ||
        in_array('student', $roles, true) ||
        in_array('subscriber', $roles, true)
    ) {
        return 'siswa';
    }

    return isset($roles[0]) && $roles[0] !== '' ? $roles[0] : 'siswa';
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_build_user_fixture(string $user_key): array
{
    $normalized = sanitize_key($user_key);

    switch ($normalized) {
        case 'secondary_student':
            $username = 'test_siswa_0002';
            $password = CBT_Admin_Maintenance_Service::get_seed_default_password();
            break;

        case 'admin':
        case 'admin_seed':
            $username = CBT_Admin_Maintenance_Service::get_seed_special_admin_username();
            $password = CBT_Admin_Maintenance_Service::get_seed_special_admin_password();
            break;

        case 'primary':
        case 'primary_student':
        default:
            $normalized = 'primary_student';
            $username = CBT_Admin_Maintenance_Service::get_seed_special_student_username();
            $password = CBT_Admin_Maintenance_Service::get_seed_special_student_password();
            break;
    }

    $user = get_user_by('login', $username);
    if (!$user instanceof WP_User) {
        e2e_fixture_fail('User seed yang diminta belum ditemukan. Jalankan ulang Bulk Test Data agar fixture terbaru dibuat.', [
            'user_key' => $normalized,
            'expected_username' => $username,
        ]);
    }

    return [
        'user_key' => $normalized,
        'user_id' => (int) $user->ID,
        'username' => $username,
        'password' => $password,
        'role' => e2e_fixture_resolve_user_role($user),
        'display_name' => (string) $user->display_name,
        'kode_kelas' => (string) get_user_meta((int) $user->ID, 'kode_kelas', true),
        'kode_ruang' => (string) get_user_meta((int) $user->ID, 'kode_ruang', true),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_get_exam_fixture(string $fixture_key): array
{
    $normalized = sanitize_key($fixture_key);
    $expected_title = CBT_Admin_Maintenance_Service::get_seed_fixture_exam_title($normalized);
    if ($expected_title === '') {
        e2e_fixture_fail('Fixture exam tidak dikenali.', [
            'fixture_key' => $normalized,
        ]);
    }

    global $wpdb;
    $exam_table = $wpdb->prefix . 'cbt_exams';
    $subject_table = $wpdb->prefix . 'cbt_subjects';
    $exam = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT e.id, e.subject_id, e.title, e.description, e.target_kelas, e.status, e.starts_at, e.ends_at,
                    e.duration_minutes, e.kkm_percentage, e.total_questions, e.randomize_questions, e.randomize_options,
                    e.show_student_result, e.enable_calculator, s.name AS subject_name, s.code AS subject_code
             FROM {$exam_table} e
             LEFT JOIN {$subject_table} s ON s.id = e.subject_id
             WHERE e.title = %s
             ORDER BY e.id ASC
             LIMIT 1",
            $expected_title
        ),
        ARRAY_A
    );

    if (!$exam) {
        e2e_fixture_fail('Fixture exam belum ditemukan. Jalankan ulang Bulk Test Data agar exam flow check terbaru dibuat.', [
            'fixture_key' => $normalized,
            'expected_exam_title' => $expected_title,
        ]);
    }

    return [
        'fixture_key' => $normalized,
        'exam_id' => (int) ($exam['id'] ?? 0),
        'subject_id' => (int) ($exam['subject_id'] ?? 0),
        'title' => (string) ($exam['title'] ?? ''),
        'description' => (string) ($exam['description'] ?? ''),
        'target_kelas' => (string) ($exam['target_kelas'] ?? ''),
        'status' => (string) ($exam['status'] ?? ''),
        'starts_at' => (string) ($exam['starts_at'] ?? ''),
        'ends_at' => (string) ($exam['ends_at'] ?? ''),
        'duration_minutes' => (int) ($exam['duration_minutes'] ?? 0),
        'kkm_percentage' => (float) ($exam['kkm_percentage'] ?? 0),
        'total_questions' => (int) ($exam['total_questions'] ?? 0),
        'randomize_questions' => (int) ($exam['randomize_questions'] ?? 0),
        'randomize_options' => (int) ($exam['randomize_options'] ?? 0),
        'show_student_result' => (int) ($exam['show_student_result'] ?? 1),
        'enable_calculator' => (int) ($exam['enable_calculator'] ?? 0),
        'subject_name' => (string) ($exam['subject_name'] ?? ''),
        'subject_code' => (string) ($exam['subject_code'] ?? ''),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_build_fixture(string $fixture_key, string $user_key = 'primary_student'): array
{
    return [
        'fixture_key' => sanitize_key($fixture_key),
        'exam' => e2e_fixture_get_exam_fixture($fixture_key),
        'user' => e2e_fixture_build_user_fixture($user_key),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_build_catalog(): array
{
    $catalog = [
        'users' => [
            'primary_student' => e2e_fixture_build_user_fixture('primary_student'),
            'secondary_student' => e2e_fixture_build_user_fixture('secondary_student'),
            'admin_seed' => e2e_fixture_build_user_fixture('admin_seed'),
        ],
        'fixtures' => [],
    ];

    foreach (CBT_Admin_Maintenance_Service::get_seed_flow_check_fixture_exam_titles() as $fixture_key => $title) {
        $catalog['fixtures'][$fixture_key] = e2e_fixture_get_exam_fixture((string) $fixture_key);
    }

    return $catalog;
}

/**
 * @return array<string,mixed>|null
 */
function e2e_fixture_get_latest_attempt(array $fixture): ?array
{
    global $wpdb;
    $attempt_table = $wpdb->prefix . 'cbt_attempts';
    $exam = isset($fixture['exam']) && is_array($fixture['exam']) ? (array) $fixture['exam'] : [];
    $user = isset($fixture['user']) && is_array($fixture['user']) ? (array) $fixture['user'] : [];
    $attempt = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$attempt_table}
             WHERE exam_id = %d
               AND student_id = %d
             ORDER BY id DESC
             LIMIT 1",
            (int) ($exam['exam_id'] ?? 0),
            (int) ($user['user_id'] ?? 0)
        ),
        ARRAY_A
    );

    if (!$attempt) {
        return null;
    }

    $attempt_id = (int) ($attempt['id'] ?? 0);
    $question_order = json_decode((string) ($attempt['question_order'] ?? ''), true);
    $option_order = json_decode((string) ($attempt['option_order'] ?? ''), true);

    return [
        'id' => $attempt_id,
        'exam_id' => (int) ($attempt['exam_id'] ?? 0),
        'student_id' => (int) ($attempt['student_id'] ?? 0),
        'status' => (string) ($attempt['status'] ?? ''),
        'score' => (float) ($attempt['score'] ?? 0),
        'max_score' => (float) ($attempt['max_score'] ?? 0),
        'started_at' => (string) ($attempt['started_at'] ?? ''),
        'finished_at' => (string) ($attempt['finished_at'] ?? ''),
        'duration_seconds' => (int) ($attempt['duration_seconds'] ?? 0),
        'extra_time_minutes' => (int) ($attempt['extra_time_minutes'] ?? 0),
        'question_order' => is_array($question_order) ? array_values(array_map('intval', $question_order)) : [],
        'option_order' => is_array($option_order) ? $option_order : [],
        'ui_state' => $attempt_id > 0 ? CBT_UI_State::get_attempt_state((int) ($user['user_id'] ?? 0), $attempt_id) : null,
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function e2e_fixture_get_attempt_answer_rows(int $attempt_id): array
{
    if ($attempt_id <= 0) {
        return [];
    }

    global $wpdb;
    $answer_table = $wpdb->prefix . 'cbt_answers';
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, attempt_id, question_id, selected_option_ids, answer_text, is_correct, score_awarded, answered_at
             FROM {$answer_table}
             WHERE attempt_id = %d
             ORDER BY id ASC",
            $attempt_id
        ),
        ARRAY_A
    );

    $merged_rows = [];
    foreach ((array) $rows as $row) {
        $question_id = (int) ($row['question_id'] ?? 0);
        if ($question_id <= 0) {
            continue;
        }

        $merged_rows[$question_id] = [
            'id' => (int) ($row['id'] ?? 0),
            'attempt_id' => (int) ($row['attempt_id'] ?? $attempt_id),
            'question_id' => $question_id,
            'selected_option_ids' => (string) ($row['selected_option_ids'] ?? ''),
            'answer_text' => (string) ($row['answer_text'] ?? ''),
            'is_correct' => $row['is_correct'] ?? null,
            'score_awarded' => (float) ($row['score_awarded'] ?? 0),
            'answered_at' => (string) ($row['answered_at'] ?? ''),
            'source' => 'database',
        ];
    }

    if (class_exists(CBT_Runtime::class, false) && method_exists(CBT_Runtime::class, 'get_existing_answers_map')) {
        $runtime_state_found = false;
        $runtime_answers = CBT_Runtime::get_existing_answers_map($attempt_id, $runtime_state_found);
        foreach ((array) $runtime_answers as $question_id => $entry) {
            $question_id = (int) $question_id;
            $entry = is_array($entry) ? $entry : [];
            if ($question_id <= 0 || !empty($entry['clear'])) {
                continue;
            }

            $has_answer_value = false;
            if (!empty($entry['selected_option_ids']) || !empty($entry['answer_text'])) {
                $has_answer_value = true;
            } elseif (array_key_exists('answer', $entry) && $entry['answer'] !== null && $entry['answer'] !== '') {
                $has_answer_value = true;
            }

            if (!$has_answer_value) {
                continue;
            }

            $merged_rows[$question_id] = [
                'id' => isset($merged_rows[$question_id]['id']) ? (int) $merged_rows[$question_id]['id'] : 0,
                'attempt_id' => $attempt_id,
                'question_id' => $question_id,
                'selected_option_ids' => (string) ($entry['selected_option_ids'] ?? ''),
                'answer_text' => (string) ($entry['answer_text'] ?? ''),
                'is_correct' => $entry['is_correct'] ?? null,
                'score_awarded' => (float) ($entry['score_awarded'] ?? 0),
                'answered_at' => (string) ($entry['answered_at'] ?? ''),
                'source' => 'runtime',
            ];
        }
    }

    return array_values($merged_rows);
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_reset(array $fixture): array
{
    global $wpdb;

    $exam = isset($fixture['exam']) && is_array($fixture['exam']) ? (array) $fixture['exam'] : [];
    $user = isset($fixture['user']) && is_array($fixture['user']) ? (array) $fixture['user'] : [];

    $attempt_table = $wpdb->prefix . 'cbt_attempts';
    $security_log_table = $wpdb->prefix . 'cbt_security_logs';
    $incident_table = $wpdb->prefix . 'cbt_exam_incidents';
    $attempt_ids = array_values(array_filter(array_map(
        'intval',
        (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM {$attempt_table}
                 WHERE exam_id = %d
                   AND student_id = %d",
                (int) ($exam['exam_id'] ?? 0),
                (int) ($user['user_id'] ?? 0)
            )
        )
    )));

    foreach ($attempt_ids as $attempt_id) {
        CBT_UI_State::clear_attempt_state((int) ($user['user_id'] ?? 0), $attempt_id);
        CBT_Cache::invalidate_attempt($attempt_id);
        if (class_exists(CBT_Runtime::class, false) && method_exists(CBT_Runtime::class, 'is_ready') && CBT_Runtime::is_ready()) {
            CBT_Runtime::clear_attempt_runtime($attempt_id);
        }
    }

    if (!empty($attempt_ids)) {
        $attempt_ids_sql = implode(',', array_map('intval', $attempt_ids));
        $wpdb->query("DELETE FROM {$security_log_table} WHERE attempt_id IN ({$attempt_ids_sql})");
    }

    $wpdb->delete(
        $incident_table,
        [
            'exam_id' => (int) ($exam['exam_id'] ?? 0),
            'student_id' => (int) ($user['user_id'] ?? 0),
        ],
        ['%d', '%d']
    );

    $wpdb->delete(
        $attempt_table,
        [
            'exam_id' => (int) ($exam['exam_id'] ?? 0),
            'student_id' => (int) ($user['user_id'] ?? 0),
        ],
        ['%d', '%d']
    );

    CBT_Auth::clear_login_session((int) ($user['user_id'] ?? 0));
    CBT_UI_State::clear_preferences((int) ($user['user_id'] ?? 0));
    CBT_Cache::invalidate_user((int) ($user['user_id'] ?? 0));
    CBT_Cache::invalidate_exam((int) ($exam['exam_id'] ?? 0));
    CBT_Cache::invalidate_catalog();
    CBT_Cache::invalidate_analytics();
    CBT_Cache::invalidate_analytics_exam((int) ($exam['exam_id'] ?? 0));

    return [
        'fixture' => $fixture,
        'deleted_attempt_ids' => $attempt_ids,
        'deleted_attempt_count' => count($attempt_ids),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_invalidate_non_attempt_cache(array $fixture): array
{
    $exam = isset($fixture['exam']) && is_array($fixture['exam']) ? (array) $fixture['exam'] : [];

    CBT_Cache::invalidate_catalog();
    CBT_Cache::invalidate_analytics();
    CBT_Cache::invalidate_analytics_exam((int) ($exam['exam_id'] ?? 0));

    return [
        'fixture' => $fixture,
        'invalidated' => [
            'catalog',
            'analytics',
            'analytics_exam:' . (int) ($exam['exam_id'] ?? 0),
        ],
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_invalidate_admin_side_cache(array $fixture): array
{
    $exam = isset($fixture['exam']) && is_array($fixture['exam']) ? (array) $fixture['exam'] : [];
    $user = isset($fixture['user']) && is_array($fixture['user']) ? (array) $fixture['user'] : [];

    CBT_Cache::invalidate_catalog();
    CBT_Cache::invalidate_exam((int) ($exam['exam_id'] ?? 0));
    CBT_Cache::invalidate_user((int) ($user['user_id'] ?? 0));
    CBT_Cache::invalidate_analytics();
    CBT_Cache::invalidate_analytics_exam((int) ($exam['exam_id'] ?? 0));

    return [
        'fixture' => $fixture,
        'invalidated' => [
            'catalog',
            'exam:' . (int) ($exam['exam_id'] ?? 0),
            'user:' . (int) ($user['user_id'] ?? 0),
            'analytics',
            'analytics_exam:' . (int) ($exam['exam_id'] ?? 0),
        ],
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_save_remote_state(array $fixture, array $payload): array
{
    $attempt_id = isset($payload['attempt_id']) ? absint((int) $payload['attempt_id']) : 0;
    if ($attempt_id <= 0) {
        e2e_fixture_fail('attempt_id wajib diisi untuk save_remote_state.');
    }

    $latest_attempt = e2e_fixture_get_latest_attempt($fixture);
    if (!$latest_attempt || (int) ($latest_attempt['id'] ?? 0) !== $attempt_id) {
        e2e_fixture_fail('Attempt fixture tidak ditemukan atau bukan latest attempt untuk fixture ini.', [
            'attempt_id' => $attempt_id,
        ]);
    }

    $current_index = max(0, (int) ($payload['current_index'] ?? 0));
    $doubtful_question_ids = [];
    foreach ((array) ($payload['doubtful_question_ids'] ?? []) as $question_id) {
        $question_id = (int) $question_id;
        if ($question_id > 0) {
            $doubtful_question_ids[] = $question_id;
        }
    }

    $user = isset($fixture['user']) && is_array($fixture['user']) ? (array) $fixture['user'] : [];
    $saved = CBT_UI_State::save_attempt_state(
        (int) ($user['user_id'] ?? 0),
        $attempt_id,
        [
            'current_index' => $current_index,
            'doubtful_question_ids' => array_values(array_unique($doubtful_question_ids)),
        ]
    );

    return [
        'fixture' => $fixture,
        'attempt' => e2e_fixture_get_latest_attempt($fixture),
        'saved_state' => $saved,
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_get_global_token_meta(): array
{
    $token_meta = CBT_Auth::get_global_exam_token(true);

    return [
        'token' => strtoupper(trim((string) ($token_meta['token'] ?? ''))),
        'refresh_minutes' => (int) ($token_meta['refresh_minutes'] ?? 0),
        'generated_at' => (int) ($token_meta['generated_at'] ?? 0),
        'next_refresh_at' => (int) ($token_meta['next_refresh_at'] ?? 0),
        'remaining_seconds' => (int) ($token_meta['remaining_seconds'] ?? 0),
        'frontend_auto_apply' => (int) ($token_meta['frontend_auto_apply'] ?? 0),
        'requires_token' => trim((string) ($token_meta['token'] ?? '')) !== '' ? 1 : 0,
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_save_global_token(array $payload): array
{
    $token = strtoupper(trim((string) ($payload['token'] ?? '')));
    $refresh_minutes = max(1, (int) ($payload['refresh_minutes'] ?? 15));
    $frontend_auto_apply = array_key_exists('frontend_auto_apply', $payload)
        ? !empty($payload['frontend_auto_apply'])
        : null;
    $regenerate = !empty($payload['regenerate']);

    CBT_Auth::save_global_exam_token_settings($token, $refresh_minutes, $regenerate, $frontend_auto_apply);

    return [
        'token_meta' => e2e_fixture_get_global_token_meta(),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_set_security_config(array $payload): array
{
    $settings = CBT_Admin_Security_Service::get_security_settings();
    $next = [
        'force_fullscreen' => array_key_exists('force_fullscreen', $payload)
            ? (!empty($payload['force_fullscreen']) ? 1 : 0)
            : (int) ($settings['force_fullscreen'] ?? 0),
        'block_copy_paste' => array_key_exists('block_copy_paste', $payload)
            ? (!empty($payload['block_copy_paste']) ? 1 : 0)
            : (int) ($settings['block_copy_paste'] ?? 0),
        'block_browser_inspection_shortcuts' => array_key_exists('block_browser_inspection_shortcuts', $payload)
            ? (!empty($payload['block_browser_inspection_shortcuts']) ? 1 : 0)
            : (int) ($settings['block_browser_inspection_shortcuts'] ?? 0),
        'log_security_events' => array_key_exists('log_security_events', $payload)
            ? (!empty($payload['log_security_events']) ? 1 : 0)
            : (int) ($settings['log_security_events'] ?? 0),
        'detect_idle_during_exam' => array_key_exists('detect_idle_during_exam', $payload)
            ? (!empty($payload['detect_idle_during_exam']) ? 1 : 0)
            : (int) ($settings['detect_idle_during_exam'] ?? 1),
        'detect_heartbeat_lost' => array_key_exists('detect_heartbeat_lost', $payload)
            ? (!empty($payload['detect_heartbeat_lost']) ? 1 : 0)
            : (int) ($settings['detect_heartbeat_lost'] ?? 0),
        'idle_threshold_minutes' => array_key_exists('idle_threshold_minutes', $payload)
            ? max(1, (int) $payload['idle_threshold_minutes'])
            : max(1, (int) ($settings['idle_threshold_minutes'] ?? 5)),
    ];

    update_option(CBT_Admin_Security_Service::security_option_key(), $next, false);

    return [
        'security' => CBT_Admin_Security_Service::get_security_settings(),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_update_exam(array $fixture, array $payload): array
{
    global $wpdb;

    $exam = isset($fixture['exam']) && is_array($fixture['exam']) ? (array) $fixture['exam'] : [];
    $exam_id = (int) ($exam['exam_id'] ?? 0);
    if ($exam_id <= 0) {
        e2e_fixture_fail('Exam fixture tidak valid untuk update_exam.');
    }

    $updates = [];
    $formats = [];

    if (array_key_exists('duration_minutes', $payload)) {
        $updates['duration_minutes'] = max(1, (int) $payload['duration_minutes']);
        $formats[] = '%d';
    }
    if (array_key_exists('kkm_percentage', $payload)) {
        $updates['kkm_percentage'] = max(0, min(100, (float) $payload['kkm_percentage']));
        $formats[] = '%f';
    }
    if (array_key_exists('randomize_questions', $payload)) {
        $updates['randomize_questions'] = !empty($payload['randomize_questions']) ? 1 : 0;
        $formats[] = '%d';
    }
    if (array_key_exists('randomize_options', $payload)) {
        $updates['randomize_options'] = !empty($payload['randomize_options']) ? 1 : 0;
        $formats[] = '%d';
    }
    if (array_key_exists('show_student_result', $payload)) {
        $updates['show_student_result'] = !empty($payload['show_student_result']) ? 1 : 0;
        $formats[] = '%d';
    }
    if (array_key_exists('enable_calculator', $payload)) {
        $updates['enable_calculator'] = !empty($payload['enable_calculator']) ? 1 : 0;
        $formats[] = '%d';
    }
    if (array_key_exists('status', $payload)) {
        $updates['status'] = sanitize_key((string) $payload['status']);
        $formats[] = '%s';
    }
    if (array_key_exists('starts_at', $payload)) {
        $updates['starts_at'] = $payload['starts_at'] ? sanitize_text_field((string) $payload['starts_at']) : null;
        $formats[] = '%s';
    }
    if (array_key_exists('ends_at', $payload)) {
        $updates['ends_at'] = $payload['ends_at'] ? sanitize_text_field((string) $payload['ends_at']) : null;
        $formats[] = '%s';
    }

    if (empty($updates)) {
        return [
            'fixture' => e2e_fixture_build_fixture((string) ($fixture['fixture_key'] ?? ''), (string) (($fixture['user']['user_key'] ?? 'primary_student'))),
            'updated' => false,
        ];
    }

    $updates['updated_at'] = current_time('mysql');
    $formats[] = '%s';

    $wpdb->update(
        $wpdb->prefix . 'cbt_exams',
        $updates,
        ['id' => $exam_id],
        $formats,
        ['%d']
    );

    CBT_Cache::invalidate_exam($exam_id);
    CBT_Cache::invalidate_catalog();

    return [
        'fixture' => e2e_fixture_build_fixture((string) ($fixture['fixture_key'] ?? ''), (string) (($fixture['user']['user_key'] ?? 'primary_student'))),
        'updated' => true,
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_shift_latest_attempt_start(array $fixture, array $payload): array
{
    global $wpdb;

    $attempt = e2e_fixture_get_latest_attempt($fixture);
    if (!$attempt) {
        e2e_fixture_fail('Latest attempt tidak ditemukan untuk shift_latest_attempt_start.');
    }

    $attempt_id = (int) ($attempt['id'] ?? 0);
    $exam = isset($fixture['exam']) && is_array($fixture['exam']) ? (array) $fixture['exam'] : [];
    $remaining_seconds = isset($payload['remaining_seconds']) ? max(0, (int) $payload['remaining_seconds']) : null;
    $extra_minutes = isset($payload['extra_time_minutes']) ? max(0, (int) $payload['extra_time_minutes']) : (int) ($attempt['extra_time_minutes'] ?? 0);
    $duration_minutes = max(1, (int) ($exam['duration_minutes'] ?? 60)) + $extra_minutes;
    $now = time();

    if ($remaining_seconds !== null) {
        $started_at_ts = $now - max(0, (($duration_minutes * MINUTE_IN_SECONDS) - $remaining_seconds));
    } else {
        $shift_seconds = isset($payload['shift_seconds']) ? (int) $payload['shift_seconds'] : 0;
        $existing_started = strtotime((string) ($attempt['started_at'] ?? '')) ?: $now;
        $started_at_ts = max(0, $existing_started - $shift_seconds);
    }

    $wpdb->update(
        $wpdb->prefix . 'cbt_attempts',
        [
            'started_at' => wp_date('Y-m-d H:i:s', $started_at_ts, wp_timezone()),
            'extra_time_minutes' => $extra_minutes,
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $attempt_id],
        ['%s', '%d', '%s'],
        ['%d']
    );

    CBT_Cache::invalidate_attempt($attempt_id);

    return [
        'attempt' => e2e_fixture_get_latest_attempt($fixture),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_get_recent_security_logs(array $payload): array
{
    $limit = max(1, min(50, (int) ($payload['limit'] ?? 20)));
    $teacher_id = max(0, (int) ($payload['teacher_id'] ?? 0));

    return [
        'logs' => CBT_Security_Log::get_recent_logs($limit, [
            'teacher_id' => $teacher_id,
        ]),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_get_must_watch_attempts(array $payload): array
{
    $limit = max(1, min(20, (int) ($payload['limit'] ?? 5)));
    $teacher_id = max(0, (int) ($payload['teacher_id'] ?? 0));

    return [
        'attempts' => CBT_Security_Log::get_must_watch_attempts($limit, [
            'teacher_id' => $teacher_id,
        ]),
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_clear_security_logs(array $payload): array
{
    global $wpdb;

    $limit_attempt_id = isset($payload['attempt_id']) ? max(0, (int) $payload['attempt_id']) : 0;
    if ($limit_attempt_id > 0) {
        $deleted = $wpdb->delete($wpdb->prefix . 'cbt_security_logs', ['attempt_id' => $limit_attempt_id], ['%d']);
    } else {
        $deleted = $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}cbt_security_logs");
    }

    return [
        'deleted' => $deleted === false ? 0 : (int) $deleted,
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_sync_subject_bank_questions_to_fixture(array $fixture, array $payload): array
{
    global $wpdb;

    $exam = isset($fixture['exam']) && is_array($fixture['exam']) ? (array) $fixture['exam'] : [];
    $subject_id = isset($payload['subject_id']) ? max(0, (int) $payload['subject_id']) : (int) ($exam['subject_id'] ?? 0);
    if ($subject_id <= 0) {
        e2e_fixture_fail('subject_id tidak valid untuk sync_subject_bank_questions_to_fixture.');
    }

    $bank_exam_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id
             FROM {$wpdb->prefix}cbt_exams
             WHERE subject_id = %d
               AND title LIKE %s
             ORDER BY id DESC
             LIMIT 1",
            $subject_id,
            'Bank Soal - %'
        )
    );

    if ($bank_exam_id <= 0) {
        e2e_fixture_fail('Bank exam subject belum ditemukan untuk sync import preview.', [
            'subject_id' => $subject_id,
        ]);
    }

    $question_ids = array_values(array_filter(array_map(
        'intval',
        (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM {$wpdb->prefix}cbt_questions
                 WHERE exam_id = %d
                   AND COALESCE(is_active, 1) = 1
                 ORDER BY id ASC",
                $bank_exam_id
            )
        )
    )));

    if (empty($question_ids)) {
        e2e_fixture_fail('Belum ada question di bank exam subject yang dipilih.', [
            'subject_id' => $subject_id,
            'bank_exam_id' => $bank_exam_id,
        ]);
    }

    $admin = e2e_fixture_build_user_fixture('admin_seed');
    $sync_result = CBT_Admin_Exams_Service::sync_exam_questions_from_sources_for_internal_use(
        (int) ($exam['exam_id'] ?? 0),
        $question_ids,
        (int) ($admin['user_id'] ?? 0)
    );

    if (is_wp_error($sync_result)) {
        e2e_fixture_fail('Sinkronisasi bank questions ke fixture gagal.', [
            'error' => $sync_result->get_error_message(),
            'fixture_key' => (string) ($fixture['fixture_key'] ?? ''),
        ]);
    }

    $wpdb->update(
        $wpdb->prefix . 'cbt_exams',
        [
            'total_questions' => count($question_ids),
            'updated_at' => current_time('mysql'),
        ],
        ['id' => (int) ($exam['exam_id'] ?? 0)],
        ['%d', '%s'],
        ['%d']
    );

    CBT_Cache::invalidate_exam((int) ($exam['exam_id'] ?? 0));
    CBT_Cache::invalidate_catalog();

    return [
        'synced_count' => (int) $sync_result,
        'question_ids' => $question_ids,
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_get_exam_questions(array $fixture): array
{
    global $wpdb;

    $exam = isset($fixture['exam']) && is_array($fixture['exam']) ? (array) $fixture['exam'] : [];
    $exam_id = (int) ($exam['exam_id'] ?? 0);
    if ($exam_id <= 0) {
        return ['questions' => []];
    }

    $question_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, exam_id, question_type, question_text, explanation, points, source_question_id
             FROM {$wpdb->prefix}cbt_questions
             WHERE exam_id = %d
               AND COALESCE(is_active, 1) = 1
             ORDER BY id ASC",
            $exam_id
        ),
        ARRAY_A
    );

    $option_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, question_id, option_key, option_text, is_correct
             FROM {$wpdb->prefix}cbt_options
             WHERE question_id IN (
                 SELECT id FROM {$wpdb->prefix}cbt_questions WHERE exam_id = %d
             )
             ORDER BY question_id ASC, id ASC",
            $exam_id
        ),
        ARRAY_A
    );

    $options_by_question = [];
    foreach ((array) $option_rows as $row) {
        $question_id = (int) ($row['question_id'] ?? 0);
        if ($question_id <= 0) {
            continue;
        }
        if (!isset($options_by_question[$question_id])) {
            $options_by_question[$question_id] = [];
        }
        $options_by_question[$question_id][] = $row;
    }

    $questions = [];
    foreach ((array) $question_rows as $row) {
        $question_id = (int) ($row['id'] ?? 0);
        $row['options'] = isset($options_by_question[$question_id]) ? $options_by_question[$question_id] : [];
        $questions[] = $row;
    }

    return [
        'questions' => $questions,
    ];
}

/**
 * @return array<string,mixed>
 */
function e2e_fixture_age_login_session(array $payload): array
{
    $user_key = isset($payload['user_key']) ? sanitize_key((string) $payload['user_key']) : 'primary_student';
    $user = e2e_fixture_build_user_fixture($user_key);
    $user_id = (int) ($user['user_id'] ?? 0);
    if ($user_id <= 0) {
        e2e_fixture_fail('User fixture tidak valid untuk age_login_session.', [
            'user_key' => $user_key,
        ]);
    }

    $seconds_ago = max(0, (int) ($payload['seconds_ago'] ?? 120));
    $session_key = (string) get_user_meta($user_id, 'cbt_active_login_session', true);
    if ($session_key === '') {
        $session_key = CBT_Auth::reset_login_session($user_id);
    }

    update_user_meta($user_id, 'cbt_active_login_session_touched_at', time() - $seconds_ago);

    return [
        'user' => $user,
        'session_key' => $session_key,
        'touched_at' => (int) get_user_meta($user_id, 'cbt_active_login_session_touched_at', true),
    ];
}

e2e_fixture_bootstrap_wordpress();
e2e_fixture_bootstrap_classes();

$action = isset($argv[1]) ? trim((string) $argv[1]) : 'catalog';
$payload = e2e_fixture_read_payload($argv);
$fixture_key = isset($payload['fixture_key']) ? sanitize_key((string) $payload['fixture_key']) : 'recovery_persistence';
$user_key = isset($payload['user_key']) ? sanitize_key((string) $payload['user_key']) : 'primary_student';
$fixture = e2e_fixture_build_fixture($fixture_key, $user_key);

switch ($action) {
    case 'catalog':
        e2e_fixture_success([
            'catalog' => e2e_fixture_build_catalog(),
        ]);
        break;

    case 'fixture':
        e2e_fixture_success([
            'fixture' => $fixture,
        ]);
        break;

    case 'reset':
        e2e_fixture_success(e2e_fixture_reset($fixture));
        break;

    case 'latest_attempt':
        e2e_fixture_success([
            'fixture' => $fixture,
            'attempt' => e2e_fixture_get_latest_attempt($fixture),
        ]);
        break;

    case 'attempt_answers':
        $attempt_id = isset($payload['attempt_id']) ? max(0, (int) $payload['attempt_id']) : 0;
        if ($attempt_id <= 0) {
            $latest_attempt = e2e_fixture_get_latest_attempt($fixture);
            $attempt_id = (int) ($latest_attempt['id'] ?? 0);
        }
        e2e_fixture_success([
            'fixture' => $fixture,
            'attempt_id' => $attempt_id,
            'answers' => e2e_fixture_get_attempt_answer_rows($attempt_id),
        ]);
        break;

    case 'invalidate_non_attempt_cache':
        e2e_fixture_success(e2e_fixture_invalidate_non_attempt_cache($fixture));
        break;

    case 'invalidate_admin_side_cache':
        e2e_fixture_success(e2e_fixture_invalidate_admin_side_cache($fixture));
        break;

    case 'save_remote_state':
        e2e_fixture_success(e2e_fixture_save_remote_state($fixture, $payload));
        break;

    case 'global_token':
        e2e_fixture_success([
            'fixture' => $fixture,
            'token_meta' => e2e_fixture_get_global_token_meta(),
        ]);
        break;

    case 'set_global_token':
        e2e_fixture_success(e2e_fixture_save_global_token($payload));
        break;

    case 'set_security':
        e2e_fixture_success(e2e_fixture_set_security_config($payload));
        break;

    case 'update_exam':
        e2e_fixture_success(e2e_fixture_update_exam($fixture, $payload));
        break;

    case 'shift_latest_attempt_start':
        e2e_fixture_success(e2e_fixture_shift_latest_attempt_start($fixture, $payload));
        break;

    case 'recent_security_logs':
        e2e_fixture_success(e2e_fixture_get_recent_security_logs($payload));
        break;

    case 'must_watch_attempts':
        e2e_fixture_success(e2e_fixture_get_must_watch_attempts($payload));
        break;

    case 'clear_security_logs':
        e2e_fixture_success(e2e_fixture_clear_security_logs($payload));
        break;

    case 'sync_subject_bank_questions_to_fixture':
        e2e_fixture_success(e2e_fixture_sync_subject_bank_questions_to_fixture($fixture, $payload));
        break;

    case 'exam_questions':
        e2e_fixture_success(e2e_fixture_get_exam_questions($fixture));
        break;

    case 'age_login_session':
        e2e_fixture_success(e2e_fixture_age_login_session($payload));
        break;

    default:
        e2e_fixture_fail('Action helper E2E tidak dikenali.', [
            'action' => $action,
        ]);
}
