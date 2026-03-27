<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

if (!class_exists('wpdb')) {
    eval(<<<'PHP'
class wpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';

    public function get_charset_collate(): string
    {
        return '';
    }
}
PHP);
}

final class RestNativeSecurityEventTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_native_security_event_accepts_existing_cbt_event_and_canonicalizes_source(): void
    {
        $this->bootstrapNativeRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestNativeSecurityEventFakeWpdb([
            114 => [
                'id' => 114,
                'exam_id' => 16,
                'student_id' => 7,
                'status' => 'in_progress',
                'started_at' => '2026-03-26 21:00:00',
                'extra_time_minutes' => 0,
            ],
        ]);

        $response = \CBT_REST::native_security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'tab_hidden',
                'native_app' => 'windows_cefsharp',
                'native_version' => '1.0.0',
                'warning_code' => 'task_switch',
                'warning_message' => 'Window ujian kehilangan fokus karena task switch',
                'occurred_at_client' => '2026-03-26T21:31:02+07:00',
                'context' => [
                    'device_platform' => 'windows',
                    'device_type' => 'desktop',
                    'has_focus' => 0,
                ],
            ]
        ));

        self::assertIsArray($response);
        self::assertSame(1, $response['logged']);
        self::assertSame('tab_hidden', $response['event_type']);
        self::assertCount(1, CBT_Security_Log::$recordedEvents);
        self::assertSame('tab_hidden', CBT_Security_Log::$recordedEvents[0]['event_type']);
        self::assertSame('windows_cefsharp_shell', CBT_Security_Log::$recordedEvents[0]['context']['source']);
        self::assertSame('windows_cefsharp', CBT_Security_Log::$recordedEvents[0]['context']['native_app']);
        self::assertSame('task_switch', CBT_Security_Log::$recordedEvents[0]['context']['warning_code']);
    }

    #[RunInSeparateProcess]
    public function test_native_security_event_rejects_invalid_native_app_and_unsupported_event_type(): void
    {
        $this->bootstrapNativeRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestNativeSecurityEventFakeWpdb([
            114 => [
                'id' => 114,
                'exam_id' => 16,
                'student_id' => 7,
                'status' => 'in_progress',
                'started_at' => '2026-03-26 21:00:00',
                'extra_time_minutes' => 0,
            ],
        ]);

        $invalidApp = \CBT_REST::native_security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'tab_hidden',
                'native_app' => 'unknown_shell',
            ]
        ));
        self::assertTrue(is_wp_error($invalidApp));
        self::assertSame('invalid_native_app', $invalidApp->get_error_code());

        $unsupportedEvent = \CBT_REST::native_security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'session_revoked',
                'native_app' => 'windows_cefsharp',
            ]
        ));
        self::assertTrue(is_wp_error($unsupportedEvent));
        self::assertSame('invalid_native_event_type', $unsupportedEvent->get_error_code());

        $windowsOnlyOnAndroid = \CBT_REST::native_security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'task_manager_blocked',
                'native_app' => 'android_webview',
            ]
        ));
        self::assertTrue(is_wp_error($windowsOnlyOnAndroid));
        self::assertSame('invalid_native_event_type', $windowsOnlyOnAndroid->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_native_security_event_accepts_windows_specific_event_for_windows_app(): void
    {
        $this->bootstrapNativeRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestNativeSecurityEventFakeWpdb([
            114 => [
                'id' => 114,
                'exam_id' => 16,
                'student_id' => 7,
                'status' => 'in_progress',
                'started_at' => '2026-03-26 21:00:00',
                'extra_time_minutes' => 0,
            ],
        ]);

        $response = \CBT_REST::native_security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'task_manager_blocked',
                'native_app' => 'windows_cefsharp',
                'warning_code' => 'task_manager_detected',
                'warning_message' => 'TEST WARNING: Task Manager tidak diizinkan selama ujian',
            ]
        ));

        self::assertIsArray($response);
        self::assertSame(1, $response['logged']);
        self::assertSame('task_manager_blocked', $response['event_type']);
        self::assertCount(1, CBT_Security_Log::$recordedEvents);
        self::assertSame('task_manager_blocked', CBT_Security_Log::$recordedEvents[0]['event_type']);
    }

    private function bootstrapNativeRestScaffold(): void
    {
        if (!class_exists('CBT_Auth')) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static function current_user_id(\WP_REST_Request $request): int
    {
        return 7;
    }

    public static function current_user_role(\WP_REST_Request $request): string
    {
        return 'student';
    }
}
PHP);
        }

        if (!class_exists('CBT_Security_Log')) {
            eval(<<<'PHP'
class CBT_Security_Log
{
    public static array $recordedEvents = [];

    public static function is_logging_enabled(): bool
    {
        return true;
    }

    public static function event_definitions(): array
    {
        return [
            'tab_hidden' => ['label' => 'Pindah tab / aplikasi', 'severity' => 'warning', 'message' => ''],
            'task_manager_blocked' => ['label' => 'Task Manager diblok', 'severity' => 'warning', 'message' => ''],
            'session_revoked' => ['label' => 'Sesi dicabut', 'severity' => 'critical', 'message' => ''],
        ];
    }

    public static function native_supported_event_definitions(): array
    {
        return [
            'tab_hidden' => ['label' => 'Pindah tab / aplikasi', 'severity' => 'warning', 'message' => ''],
            'task_manager_blocked' => ['label' => 'Task Manager diblok', 'severity' => 'warning', 'message' => ''],
        ];
    }

    public static function native_supported_event_definitions_for_app(string $native_app): array
    {
        if ($native_app === 'windows_cefsharp') {
            return [
                'tab_hidden' => ['label' => 'Pindah tab / aplikasi', 'severity' => 'warning', 'message' => ''],
                'task_manager_blocked' => ['label' => 'Task Manager diblok', 'severity' => 'warning', 'message' => ''],
            ];
        }

        return [
            'tab_hidden' => ['label' => 'Pindah tab / aplikasi', 'severity' => 'warning', 'message' => ''],
        ];
    }

    public static function is_native_event_type(string $event_type): bool
    {
        return strpos((string) $event_type, 'native_') === 0
            || in_array((string) $event_type, ['task_manager_blocked'], true);
    }

    public static function native_app_labels(): array
    {
        return [
            'android_webview' => 'Android WebView',
            'windows_cefsharp' => 'Windows CEFSharp',
        ];
    }

    public static function native_app_source(string $native_app): string
    {
        return $native_app === 'windows_cefsharp' ? 'windows_cefsharp_shell' : 'android_webview_shell';
    }

    public static function record_attempt_event(int $attempt_id, string $event_type, array $context = []): bool
    {
        self::$recordedEvents[] = [
            'attempt_id' => $attempt_id,
            'event_type' => $event_type,
            'context' => $context,
        ];

        return true;
    }
}
PHP);
        }

        if (!class_exists('CBT_Runtime')) {
            eval(<<<'PHP'
class CBT_Runtime {}
PHP);
        }

        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache {}
PHP);
        }
    }
}

if (!class_exists('RestNativeSecurityEventFakeWpdb')) {
    class RestNativeSecurityEventFakeWpdb extends wpdb
    {
        /** @var array<int,array<string,mixed>> */
        private array $attemptsById;

        /** @param array<int,array<string,mixed>> $attemptsById */
        public function __construct(array $attemptsById)
        {
            $this->attemptsById = $attemptsById;
        }

        public function prepare(string $query, ...$args): string
        {
            $attemptId = (int) ($args[0] ?? 0);
            return str_replace('%d', (string) $attemptId, $query);
        }

        public function get_row(string $query, $output = ARRAY_A)
        {
            if (preg_match('/WHERE id = (\d+)/', $query, $matches)) {
                $attemptId = (int) ($matches[1] ?? 0);
                return $this->attemptsById[$attemptId] ?? null;
            }

            return null;
        }
    }
}
