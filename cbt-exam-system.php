<?php
/**
 * Plugin Name: CBT Exam System
 * Description: Computer Based Test plugin with WordPress admin CRUD, REST API, and JWT auth.
 * Version: 2.0.0
 * Author: COBLAX
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CBT_EXAM_SYSTEM_VERSION', '2.0.0');
define('CBT_EXAM_SYSTEM_PATH', plugin_dir_path(__FILE__));
define('CBT_EXAM_SYSTEM_URL', plugin_dir_url(__FILE__));

require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-activator.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-deactivator.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-cache.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-runtime.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-active-attempt-index.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-student-profile-cache.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-student-cohort-index-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-login-auth-snapshot-cache.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-login-readiness-warm-queue-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-login-snapshot-metrics-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-start-attempt-metrics-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-entry-flow-metrics-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-start-attempt-idempotency-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-start-attempt-opening-state-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-login-snapshot-freshness-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-adaptive-load-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-exam-availability-cache.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-exam-availability-auto-warm-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-exam-preflight-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-exam-question-delivery-cache.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-snapshot-auto-heal-queue-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-start-attempt-gate-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-attempt-runtime-snapshot-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-question-submission-context-cache.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-security-live-counters.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-security-event-ingest.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-live-proctoring-presence.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-live-attempt-roster-index.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-ui-state.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-security-log.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-incident-report.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-auth.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-frontend.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-update-release-helper.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exams-helper.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exams-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exams-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exams-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-test-hub-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-test-hub-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-test-hub-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-questions-helper.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-questions-sync-helper.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-questions-import-helper.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-questions-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-questions-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-questions-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-results-helper.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-results-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-results-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-results-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-introduction-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-analytics-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-analytics-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-assets.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-branding-settings.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-setup-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-setup-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-setup-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-security-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-security-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-security-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-developer-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-developer-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-developer-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-report-exam-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-report-exam-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-report-exam-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exam-cards-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exam-cards-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exam-cards-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-cache-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-cache-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-cache-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-update-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-update-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-update-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-tokens-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-tokens-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-tokens-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-subjects-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-subjects-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-subjects-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-users-service.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-users-page.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-users-actions.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-menu.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-rest.php';

register_activation_hook(__FILE__, ['CBT_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['CBT_Deactivator', 'deactivate']);

add_action('plugins_loaded', static function () {
    $autoload = CBT_EXAM_SYSTEM_PATH . 'vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    CBT_Activator::maybe_upgrade();
    CBT_Runtime::init();
    CBT_Student_Profile_Cache::init();
    CBT_Student_Cohort_Index_Service::init();
    CBT_Login_Auth_Snapshot_Cache::init();
    CBT_Login_Readiness_Warm_Queue_Service::init();
    CBT_Start_Attempt_Metrics_Service::init();
    CBT_Entry_Flow_Metrics_Service::init();
    CBT_Login_Snapshot_Freshness_Service::init();
    CBT_Adaptive_Load_Service::init();
    CBT_Exam_Availability_Auto_Warm_Service::init();
    CBT_Exam_Preflight_Service::init();
    CBT_Snapshot_Auto_Heal_Queue_Service::init();
    CBT_Security_Event_Ingest::init();
    CBT_Admin::init();
    CBT_Frontend::init();
    CBT_REST::init();
});

add_action('wp_head', static function () {
    if (is_admin()) {
        return;
    }

    $capture_flag = isset($_GET['figma_capture']) ? sanitize_text_field(wp_unslash($_GET['figma_capture'])) : '';
    if ($capture_flag !== '1') {
        return;
    }

    echo '<script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>' . "\n";
}, 1);

/**
 * Prioritas endpoint ujian:
 * - login / start_attempt(token) / get_questions diprioritaskan saat jam ujian.
 * - submit_answer boleh defer scoring saat sistem sibuk agar bootstrap ujian tetap lancar.
 */
if (!function_exists('cbt_exam_is_priority_peak_time')) {
    function cbt_exam_is_priority_peak_time(): bool
    {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);

        // Default jam ujian aktif: Senin-Sabtu, 07:00-17:30
        $dayOfWeek = (int) $now->format('N'); // 1=Mon ... 7=Sun
        if ($dayOfWeek >= 7) {
            return false;
        }

        $minutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
        return $minutes >= (7 * 60) && $minutes <= (17 * 60 + 30);
    }
}

add_filter('cbt_exam_priority_window_seconds', static function (int $seconds, string $source): int {
    $source = strtolower(trim($source));

    if (cbt_exam_is_priority_peak_time()) {
        // Saat jam ujian, perpanjang window supaya login/token/get_questions lebih diprioritaskan.
        if (in_array($source, ['login', 'start_attempt', 'questions', 'exams'], true)) {
            return 26;
        }
        return 20;
    }

    // Di luar jam ujian tetap aktif tapi lebih pendek.
    return max(10, min(16, $seconds));
}, 20, 2);

add_filter('cbt_submit_priority_mode_enabled', static function (bool $enabled): bool {
    // Selalu aktifkan mode prioritas submit; ketat/ringan diatur oleh window + load threshold.
    return true;
}, 20, 1);

add_filter('cbt_submit_defer_load_threshold', static function (float $threshold): float {
    if (cbt_exam_is_priority_peak_time()) {
        // Saat jam ujian, submit mulai didefer jika load 1m sudah relatif tinggi.
        return 1.4;
    }

    // Di luar jam ujian, defer hanya saat load lebih tinggi.
    return max(2.8, $threshold);
}, 20, 1);
