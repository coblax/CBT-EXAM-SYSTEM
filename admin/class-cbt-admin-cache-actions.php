<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Login_Auth_Snapshot_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-login-auth-snapshot-cache.php';
}

if (!class_exists('CBT_Question_Submission_Context_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-question-submission-context-cache.php';
}

final class CBT_Admin_Cache_Actions
{
    private const TEST_REDIRECT_SIGNAL = '__cbt_admin_cache_redirect__';

    public static function handle_cache_action(): void
    {
        if (!CBT_Admin_Cache_Service::can_manage_cache()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_cache_action');

        $operation = isset($_POST['operation']) ? sanitize_key((string) wp_unslash($_POST['operation'])) : '';
        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $user_id = isset($_POST['user_id']) ? absint(wp_unslash((string) $_POST['user_id'])) : 0;
        $attempt_id = isset($_POST['attempt_id']) ? absint(wp_unslash((string) $_POST['attempt_id'])) : 0;
        $lock_key = isset($_POST['lock_key']) ? sanitize_text_field((string) wp_unslash($_POST['lock_key'])) : '';

        switch ($operation) {
            case 'bootstrap_redis':
                $result = CBT_Admin_Cache_Service::bootstrap_redis_wordpress();
                self::redirect_cache_page($result['message'] ?? null, $result['error'] ?? null);
                break;
            case 'rollback_redis':
                $result = CBT_Admin_Cache_Service::rollback_redis_wordpress();
                self::redirect_cache_page($result['message'] ?? null, $result['error'] ?? null);
                break;
            case 'invalidate_all':
                CBT_Cache::invalidate_all();
                self::redirect_cache_page('Semua namespace cache CBT berhasil di-invalidate.');
                break;
            case 'invalidate_catalog':
                CBT_Cache::invalidate_catalog();
                self::redirect_cache_page('Namespace catalog berhasil di-invalidate.');
                break;
            case 'invalidate_exam':
                if ($exam_id <= 0) {
                    self::redirect_cache_page(null, 'Exam ID tidak valid.');
                }
                CBT_Cache::invalidate_exam($exam_id);
                self::redirect_cache_page('Namespace exam berhasil di-invalidate.');
                break;
            case 'invalidate_user':
                if ($user_id <= 0) {
                    self::redirect_cache_page(null, 'User ID tidak valid.');
                }
                CBT_Cache::invalidate_user($user_id);
                self::redirect_cache_page('Namespace user berhasil di-invalidate.');
                break;
            case 'warm_login_snapshot_exam':
                if ($exam_id <= 0) {
                    self::redirect_cache_page(null, 'Exam ID tidak valid.');
                }
                $exam_row = self::get_cache_exam_row_by_id($exam_id);
                if (!is_array($exam_row)) {
                    self::redirect_cache_page(null, 'Exam tidak ditemukan.');
                }
                $result = CBT_Login_Auth_Snapshot_Cache::warm_exam_target_snapshots($exam_row, 'cache_exam');
                self::redirect_cache_page(sprintf(
                    'Warm login snapshot exam #%d selesai. READY %d/%d. Gagal %d.',
                    $exam_id,
                    (int) ($result['ready_count'] ?? 0),
                    (int) ($result['target_student_count'] ?? 0),
                    (int) ($result['failure_count'] ?? 0)
                ));
                break;
            case 'clear_login_snapshot_exam':
                if ($exam_id <= 0) {
                    self::redirect_cache_page(null, 'Exam ID tidak valid.');
                }
                $exam_row = self::get_cache_exam_row_by_id($exam_id);
                if (!is_array($exam_row)) {
                    self::redirect_cache_page(null, 'Exam tidak ditemukan.');
                }
                $result = CBT_Login_Auth_Snapshot_Cache::clear_exam_target_snapshots($exam_row);
                self::redirect_cache_page(sprintf(
                    'Login snapshot exam #%d dibersihkan untuk %d siswa. Keys terhapus %d.',
                    $exam_id,
                    (int) ($result['target_student_count'] ?? 0),
                    (int) ($result['deleted_keys'] ?? 0)
                ));
                break;
            case 'warm_submission_context_exam':
                if ($exam_id <= 0) {
                    self::redirect_cache_page(null, 'Exam ID tidak valid.');
                }
                $diagnostics = CBT_Question_Submission_Context_Cache::warm_exam_snapshots($exam_id);
                if ((string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
                    self::redirect_cache_page(sprintf(
                        'Submission context exam #%d siap. READY %d/%d.',
                        $exam_id,
                        (int) ($diagnostics['ready_count'] ?? 0),
                        (int) ($diagnostics['question_count'] ?? 0)
                    ));
                }
                self::redirect_cache_page(null, sprintf(
                    'Submission context exam #%d belum penuh. READY %d/%d · MISS %d · INVALID %d.',
                    $exam_id,
                    (int) ($diagnostics['ready_count'] ?? 0),
                    (int) ($diagnostics['question_count'] ?? 0),
                    (int) ($diagnostics['missing_count'] ?? 0),
                    (int) ($diagnostics['invalid_count'] ?? 0)
                ));
                break;
            case 'clear_submission_context_exam':
                if ($exam_id <= 0) {
                    self::redirect_cache_page(null, 'Exam ID tidak valid.');
                }
                $result = CBT_Question_Submission_Context_Cache::clear_exam_snapshots($exam_id);
                self::redirect_cache_page(sprintf(
                    'Submission context exam #%d dibersihkan. Soal aktif %d. Keys terhapus %d.',
                    $exam_id,
                    (int) ($result['question_count'] ?? 0),
                    (int) ($result['deleted_keys'] ?? 0)
                ));
                break;
            case 'warm_login_snapshot_user':
                if ($user_id <= 0) {
                    self::redirect_cache_page(null, 'User ID tidak valid.');
                }
                CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot($user_id, 'cache_user');
                $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
                if ((string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
                    self::redirect_cache_page(sprintf(
                        'Login snapshot siswa #%d siap. TTL %d detik.',
                        $user_id,
                        (int) ($diagnostics['ttl_seconds'] ?? 0)
                    ));
                }
                self::redirect_cache_page(null, sprintf(
                    'Login snapshot siswa #%d belum valid. %s',
                    $user_id,
                    (string) ($diagnostics['snapshot_message'] ?? '')
                ));
                break;
            case 'clear_login_snapshot_user':
                if ($user_id <= 0) {
                    self::redirect_cache_page(null, 'User ID tidak valid.');
                }
                $deleted = CBT_Login_Auth_Snapshot_Cache::clear_user_snapshot($user_id);
                self::redirect_cache_page(sprintf(
                    'Login snapshot siswa #%d dibersihkan. Keys terhapus %d.',
                    $user_id,
                    $deleted
                ));
                break;
            case 'invalidate_attempt':
                if ($attempt_id <= 0) {
                    self::redirect_cache_page(null, 'Attempt ID tidak valid.');
                }
                CBT_Cache::invalidate_attempt($attempt_id);
                self::redirect_cache_page('Namespace attempt berhasil di-invalidate.');
                break;
            case 'prune_old_namespaces':
                $pruned = CBT_Cache::prune_old_namespaces();
                self::redirect_cache_page(sprintf('Namespace lama yang dibersihkan dari registry: %d.', $pruned));
                break;
            case 'clear_all_ui_state':
                CBT_UI_State::clear_all();
                self::redirect_cache_page('Semua UI state CBT berhasil dibersihkan.');
                break;
            case 'clear_attempt_ui_state':
                if ($attempt_id <= 0) {
                    self::redirect_cache_page(null, 'Attempt ID tidak valid.');
                }
                CBT_UI_State::clear_attempt_state_by_attempt_id($attempt_id);
                self::redirect_cache_page('UI state attempt berhasil dibersihkan.');
                break;
            case 'clear_ui_preferences':
                if ($user_id <= 0) {
                    self::redirect_cache_page(null, 'User ID tidak valid.');
                }
                CBT_UI_State::clear_preferences($user_id);
                self::redirect_cache_page('UI preferences user berhasil dibersihkan.');
                break;
            case 'release_stale_locks':
                $released = CBT_Cache::release_stale_locks();
                self::redirect_cache_page(sprintf('Stale lock yang dilepas: %d.', $released));
                break;
            case 'release_lock':
                if ($lock_key === '') {
                    self::redirect_cache_page(null, 'Lock key tidak valid.');
                }
                CBT_Cache::release_lock($lock_key);
                self::redirect_cache_page('Lock CBT berhasil dilepas.');
                break;
            default:
                self::redirect_cache_page(null, 'Operasi cache tidak dikenali.');
        }
    }

    private static function redirect_cache_page(?string $message = null, ?string $error = null): void
    {
        $redirect_args = ['page' => 'cbt-cache'];
        if ($message !== null && $message !== '') {
            $redirect_args['cbt_msg'] = $message;
        }
        if ($error !== null && $error !== '') {
            $redirect_args['cbt_err'] = $error;
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            throw new RuntimeException(self::TEST_REDIRECT_SIGNAL);
        }
        exit;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_cache_exam_row_by_id(int $exam_id): ?array
    {
        global $wpdb;

        $exam_id = absint($exam_id);
        if ($exam_id <= 0 || !is_object($wpdb)) {
            return null;
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $sql = "SELECT e.id, e.title, e.status, e.target_kelas, s.name AS subject_name
            FROM {$exam_table} e
            LEFT JOIN {$subject_table} s ON s.id = e.subject_id
            WHERE e.id = %d
            LIMIT 1";
        $prepared = method_exists($wpdb, 'prepare') ? $wpdb->prepare($sql, $exam_id) : $sql;
        $rows = method_exists($wpdb, 'get_results') ? $wpdb->get_results($prepared, ARRAY_A) : [];
        if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }
}
