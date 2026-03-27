<?php

declare(strict_types=1);

/**
 * @return never
 */
function recovery_fixture_fail(string $message, array $context = [], int $exit_code = 1): void
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
function recovery_fixture_success(array $payload): void
{
    $payload['ok'] = true;
    $encoded = function_exists('wp_json_encode')
        ? wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo (string) $encoded . PHP_EOL;
    exit(0);
}

function recovery_fixture_bootstrap_wordpress(): void
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

    recovery_fixture_fail('WordPress bootstrap tidak ditemukan. Pastikan helper dijalankan dari dalam repo plugin CBT.');
}

function recovery_fixture_read_payload(array $argv): array
{
    if (!isset($argv[2])) {
        return [];
    }

    $decoded = json_decode((string) $argv[2], true);
    return is_array($decoded) ? $decoded : [];
}

function recovery_fixture_require_class(string $class_name): void
{
    if (class_exists($class_name, false)) {
        return;
    }

    $plugin_bootstrap = dirname(__DIR__, 3) . '/cbt-exam-system.php';
    if (is_file($plugin_bootstrap)) {
        require_once $plugin_bootstrap;
    }

    if (!class_exists($class_name, false)) {
        recovery_fixture_fail('Class yang dibutuhkan tidak tersedia setelah WordPress dibootstrap.', [
            'class_name' => $class_name,
        ]);
    }
}

/**
 * @return array{user_id:int,username:string,password:string,exam_id:int,exam_title:string,target_kelas:string,status:string,starts_at:string,ends_at:string}
 */
function recovery_fixture_get_fixture(): array
{
    recovery_fixture_require_class(CBT_Admin_Maintenance_Service::class);

    $username = CBT_Admin_Maintenance_Service::get_seed_special_student_username();
    $password = CBT_Admin_Maintenance_Service::get_seed_special_student_password();
    $exam_title = CBT_Admin_Maintenance_Service::get_seed_recovery_fixture_exam_title();

    $user = get_user_by('login', $username);
    if (!$user instanceof WP_User) {
        recovery_fixture_fail(
            'Akun seed recovery belum ditemukan. Jalankan Bulk Test Data lebih dulu agar akun coblax / 223611 tersedia.',
            ['expected_username' => $username]
        );
    }

    global $wpdb;
    $exam_table = $wpdb->prefix . 'cbt_exams';
    $exam = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, title, target_kelas, status, starts_at, ends_at
             FROM {$exam_table}
             WHERE title = %s
             ORDER BY id ASC
             LIMIT 1",
            $exam_title
        ),
        ARRAY_A
    );

    if (!$exam) {
        recovery_fixture_fail(
            'Fixture recovery belum ditemukan. Jalankan ulang Bulk Test Data agar exam TEST Recovery Fixture dibuat dengan struktur terbaru.',
            ['expected_exam_title' => $exam_title]
        );
    }

    return [
        'user_id' => (int) $user->ID,
        'username' => $username,
        'password' => $password,
        'exam_id' => (int) ($exam['id'] ?? 0),
        'exam_title' => (string) ($exam['title'] ?? ''),
        'target_kelas' => (string) ($exam['target_kelas'] ?? ''),
        'status' => (string) ($exam['status'] ?? ''),
        'starts_at' => (string) ($exam['starts_at'] ?? ''),
        'ends_at' => (string) ($exam['ends_at'] ?? ''),
    ];
}

/**
 * @return array<string,mixed>|null
 */
function recovery_fixture_get_latest_attempt(array $fixture): ?array
{
    global $wpdb;
    $attempt_table = $wpdb->prefix . 'cbt_attempts';
    $attempt = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$attempt_table}
             WHERE exam_id = %d
               AND student_id = %d
             ORDER BY id DESC
             LIMIT 1",
            (int) $fixture['exam_id'],
            (int) $fixture['user_id']
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
        'started_at' => (string) ($attempt['started_at'] ?? ''),
        'finished_at' => (string) ($attempt['finished_at'] ?? ''),
        'question_order' => is_array($question_order) ? array_values(array_map('intval', $question_order)) : [],
        'option_order' => is_array($option_order) ? $option_order : [],
        'ui_state' => $attempt_id > 0 ? CBT_UI_State::get_attempt_state((int) $fixture['user_id'], $attempt_id) : null,
    ];
}

/**
 * @return array<string,mixed>
 */
function recovery_fixture_reset(array $fixture): array
{
    global $wpdb;

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
                (int) $fixture['exam_id'],
                (int) $fixture['user_id']
            )
        )
    )));

    foreach ($attempt_ids as $attempt_id) {
        CBT_UI_State::clear_attempt_state((int) $fixture['user_id'], $attempt_id);
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
            'exam_id' => (int) $fixture['exam_id'],
            'student_id' => (int) $fixture['user_id'],
        ],
        ['%d', '%d']
    );

    $wpdb->delete(
        $attempt_table,
        [
            'exam_id' => (int) $fixture['exam_id'],
            'student_id' => (int) $fixture['user_id'],
        ],
        ['%d', '%d']
    );

    CBT_Auth::clear_login_session((int) $fixture['user_id']);
    CBT_UI_State::clear_preferences((int) $fixture['user_id']);
    CBT_Cache::invalidate_user((int) $fixture['user_id']);
    CBT_Cache::invalidate_exam((int) $fixture['exam_id']);
    CBT_Cache::invalidate_catalog();
    CBT_Cache::invalidate_analytics();
    CBT_Cache::invalidate_analytics_exam((int) $fixture['exam_id']);

    return [
        'fixture' => $fixture,
        'deleted_attempt_ids' => $attempt_ids,
        'deleted_attempt_count' => count($attempt_ids),
    ];
}

/**
 * @return array<string,mixed>
 */
function recovery_fixture_invalidate_non_attempt_cache(array $fixture): array
{
    CBT_Cache::invalidate_catalog();
    CBT_Cache::invalidate_analytics();
    CBT_Cache::invalidate_analytics_exam((int) $fixture['exam_id']);

    return [
        'fixture' => $fixture,
        'invalidated' => [
            'catalog',
            'analytics',
            'analytics_exam:' . (int) $fixture['exam_id'],
        ],
    ];
}

/**
 * @return array<string,mixed>
 */
function recovery_fixture_invalidate_admin_side_cache(array $fixture): array
{
    CBT_Cache::invalidate_catalog();
    CBT_Cache::invalidate_exam((int) $fixture['exam_id']);
    CBT_Cache::invalidate_user((int) $fixture['user_id']);
    CBT_Cache::invalidate_analytics();
    CBT_Cache::invalidate_analytics_exam((int) $fixture['exam_id']);

    return [
        'fixture' => $fixture,
        'invalidated' => [
            'catalog',
            'exam:' . (int) $fixture['exam_id'],
            'user:' . (int) $fixture['user_id'],
            'analytics',
            'analytics_exam:' . (int) $fixture['exam_id'],
        ],
    ];
}

/**
 * @return array<string,mixed>
 */
function recovery_fixture_save_remote_state(array $fixture, array $payload): array
{
    $attempt_id = isset($payload['attempt_id']) ? absint((int) $payload['attempt_id']) : 0;
    if ($attempt_id <= 0) {
        recovery_fixture_fail('attempt_id wajib diisi untuk save_remote_state.');
    }

    $latest_attempt = recovery_fixture_get_latest_attempt($fixture);
    if (!$latest_attempt || (int) ($latest_attempt['id'] ?? 0) !== $attempt_id) {
        recovery_fixture_fail('Attempt recovery tidak ditemukan atau bukan latest attempt untuk fixture ini.', [
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

    $saved = CBT_UI_State::save_attempt_state(
        (int) $fixture['user_id'],
        $attempt_id,
        [
            'current_index' => $current_index,
            'doubtful_question_ids' => array_values(array_unique($doubtful_question_ids)),
        ]
    );

    return [
        'fixture' => $fixture,
        'attempt' => recovery_fixture_get_latest_attempt($fixture),
        'saved_state' => $saved,
    ];
}

/**
 * @return array<string,mixed>
 */
function recovery_fixture_get_global_token_meta(): array
{
    recovery_fixture_require_class(CBT_Auth::class);

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
function recovery_fixture_save_global_token(array $payload): array
{
    recovery_fixture_require_class(CBT_Auth::class);

    $token = strtoupper(trim((string) ($payload['token'] ?? '')));
    $refresh_minutes = max(1, (int) ($payload['refresh_minutes'] ?? 15));
    $frontend_auto_apply = array_key_exists('frontend_auto_apply', $payload)
        ? !empty($payload['frontend_auto_apply'])
        : null;
    $regenerate = !empty($payload['regenerate']);

    CBT_Auth::save_global_exam_token_settings($token, $refresh_minutes, $regenerate, $frontend_auto_apply);

    return [
        'token_meta' => recovery_fixture_get_global_token_meta(),
    ];
}

recovery_fixture_bootstrap_wordpress();

$action = isset($argv[1]) ? trim((string) $argv[1]) : 'fixture';
$payload = recovery_fixture_read_payload($argv);
$fixture = recovery_fixture_get_fixture();

switch ($action) {
    case 'fixture':
        recovery_fixture_success([
            'fixture' => $fixture,
        ]);
        break;

    case 'reset':
        recovery_fixture_success(recovery_fixture_reset($fixture));
        break;

    case 'latest_attempt':
        recovery_fixture_success([
            'fixture' => $fixture,
            'attempt' => recovery_fixture_get_latest_attempt($fixture),
        ]);
        break;

    case 'invalidate_non_attempt_cache':
        recovery_fixture_success(recovery_fixture_invalidate_non_attempt_cache($fixture));
        break;

    case 'invalidate_admin_side_cache':
        recovery_fixture_success(recovery_fixture_invalidate_admin_side_cache($fixture));
        break;

    case 'save_remote_state':
        recovery_fixture_success(recovery_fixture_save_remote_state($fixture, $payload));
        break;

    case 'global_token':
        recovery_fixture_success([
            'fixture' => $fixture,
            'token_meta' => recovery_fixture_get_global_token_meta(),
        ]);
        break;

    case 'set_global_token':
        recovery_fixture_success(recovery_fixture_save_global_token($payload));
        break;

    default:
        recovery_fixture_fail('Action helper recovery tidak dikenali.', [
            'action' => $action,
        ]);
}
