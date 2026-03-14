<?php
/**
 * Plugin Name: CBT Exam System
 * Description: Computer Based Test plugin with WordPress admin CRUD, REST API, and JWT auth.
 * Version: 1.6.3
 * Author: COBLAX
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CBT_EXAM_SYSTEM_VERSION', '1.6.3');
define('CBT_EXAM_SYSTEM_PATH', plugin_dir_path(__FILE__));
define('CBT_EXAM_SYSTEM_URL', plugin_dir_url(__FILE__));

require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-activator.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-deactivator.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-cache.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-runtime.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-ui-state.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-security-log.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-auth.php';
require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-frontend.php';
require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exams-helper.php';
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
