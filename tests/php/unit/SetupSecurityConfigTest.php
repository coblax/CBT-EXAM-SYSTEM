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

        self::assertSame(1, $settings['detect_idle_during_exam']);
        self::assertSame(5, $settings['idle_threshold_minutes']);
        self::assertSame($settings, \CBT_Admin_Setup_Service::get_security_settings());
    }

    #[RunInSeparateProcess]
    public function test_security_settings_normalize_explicit_idle_configuration(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';

        update_option('cbt_setup_security', [
            'detect_idle_during_exam' => 0,
            'idle_threshold_minutes' => '9',
            'log_security_events' => 1,
        ]);

        $settings = \CBT_Admin_Security_Service::get_security_settings();

        self::assertSame(0, $settings['detect_idle_during_exam']);
        self::assertSame(9, $settings['idle_threshold_minutes']);
        self::assertSame(1, $settings['log_security_events']);
        self::assertSame($settings, \CBT_Admin_Setup_Service::get_security_settings());
    }
}
