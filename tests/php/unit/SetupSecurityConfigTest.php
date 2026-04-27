<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class SetupSecurityConfigTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_security_settings_default_idle_detection_to_enabled_with_five_minutes(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';

        $settings = \CBT_Admin_Security_Service::get_security_settings();

        self::assertSame(0, $settings['block_browser_inspection_shortcuts']);
        self::assertSame(1, $settings['detect_idle_during_exam']);
        self::assertSame(0, $settings['detect_heartbeat_lost']);
        self::assertSame(5, $settings['idle_threshold_minutes']);
        self::assertSame(0, $settings['detect_screenshot_keys']);
        self::assertSame(0, $settings['show_exam_watermark']);
        self::assertSame(0.07, $settings['exam_watermark_opacity']);
        self::assertSame(0, $settings['security_redis_first_ingest']);
        self::assertSame(0, $settings['restrict_student_user_agent']);
        self::assertSame(['CBXExamLockAndroid'], $settings['allowed_user_agents']);
        self::assertSame($settings, \CBT_Admin_Setup_Service::get_security_settings());
    }

    #[RunInSeparateProcess]
    public function test_security_settings_normalize_explicit_idle_configuration(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';

        update_option('cbt_setup_security', [
            'block_browser_inspection_shortcuts' => 1,
            'detect_idle_during_exam' => 0,
            'detect_heartbeat_lost' => 1,
            'detect_screenshot_keys' => 1,
            'idle_threshold_minutes' => '9',
            'log_security_events' => 1,
            'show_exam_watermark' => 1,
            'exam_watermark_opacity' => '0.2',
            'security_redis_first_ingest' => 1,
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => "lab-browser\ncbxexamlockandroid\nLAB-BROWSER\n",
        ]);

        $settings = \CBT_Admin_Security_Service::get_security_settings();

        self::assertSame(1, $settings['block_browser_inspection_shortcuts']);
        self::assertSame(0, $settings['detect_idle_during_exam']);
        self::assertSame(1, $settings['detect_heartbeat_lost']);
        self::assertSame(1, $settings['detect_screenshot_keys']);
        self::assertSame(9, $settings['idle_threshold_minutes']);
        self::assertSame(1, $settings['log_security_events']);
        self::assertSame(1, $settings['show_exam_watermark']);
        self::assertSame(0.12, $settings['exam_watermark_opacity']);
        self::assertSame(1, $settings['security_redis_first_ingest']);
        self::assertSame(1, $settings['restrict_student_user_agent']);
        self::assertSame(['CBXExamLockAndroid', 'lab-browser'], $settings['allowed_user_agents']);
        self::assertSame($settings, \CBT_Admin_Setup_Service::get_security_settings());
    }

    #[RunInSeparateProcess]
    public function test_security_settings_accept_comma_decimal_watermark_opacity(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';

        update_option('cbt_setup_security', [
            'show_exam_watermark' => 1,
            'exam_watermark_opacity' => '0,09',
        ]);

        $settings = \CBT_Admin_Security_Service::get_security_settings();

        self::assertSame(1, $settings['show_exam_watermark']);
        self::assertSame(0.09, $settings['exam_watermark_opacity']);
    }
}
