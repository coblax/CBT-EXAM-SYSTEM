<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class PasswordRuntimeProtectionSourceTest extends TestCase
{
    public function test_password_meta_access_goes_through_secret_helper(): void
    {
        $root = dirname(__DIR__, 3);
        $files = [
            $root . '/admin/class-cbt-admin-users-service.php',
            $root . '/admin/class-cbt-admin.php',
            $root . '/admin/class-cbt-admin-exam-cards-service.php',
            $root . '/admin/class-cbt-admin-maintenance-load-test-service.php',
        ];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            self::assertDoesNotMatchRegularExpression(
                '/update_user_meta\s*\([^;]*USER_META_PLAIN_PASSWORD/s',
                $source,
                basename($file) . ' must not write cbt_plain_password directly.'
            );
            self::assertDoesNotMatchRegularExpression(
                '/get_user_meta\s*\([^;]*USER_META_PLAIN_PASSWORD/s',
                $source,
                basename($file) . ' must not read cbt_plain_password directly.'
            );
        }

        self::assertStringContainsString(
            'CBT_User_Password_Secret::store_user_plain_password',
            (string) file_get_contents($root . '/admin/class-cbt-admin-users-service.php')
        );
        self::assertStringContainsString(
            'CBT_User_Password_Secret::get_user_plain_password',
            (string) file_get_contents($root . '/admin/class-cbt-admin-exam-cards-service.php')
        );
        self::assertStringContainsString(
            'CBT_User_Password_Secret::get_user_plain_password',
            (string) file_get_contents($root . '/admin/class-cbt-admin-maintenance-load-test-service.php')
        );
    }

    public function test_nginx_runtime_upload_protection_is_documented(): void
    {
        $doc = (string) file_get_contents(dirname(__DIR__, 3) . '/INSTALL-NGINX-PHP-FPM.md');

        self::assertStringContainsString('^/wp-content/uploads/cbt-runtime/', $doc);
        self::assertStringContainsString('deny all;', $doc);
        self::assertStringContainsString('^/wp-content/uploads/cbt-user-import-photos/.*\\.php$', $doc);
        self::assertStringContainsString('bukan asset publik', strtolower($doc));
    }
}
