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

    #[RunInSeparateProcess]
    public function test_browser_security_event_accepts_browser_supported_signals_and_enriches_context(): void
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

        $response = \CBT_REST::security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'print_attempt',
                'context' => [
                    'source' => 'print_shortcut',
                    'blocked' => 1,
                ],
            ],
            [
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ]
        ));

        self::assertIsArray($response);
        self::assertSame(1, $response['logged']);
        self::assertSame('print_attempt', $response['event_type']);
        self::assertCount(1, CBT_Security_Log::$recordedEvents);
        self::assertSame('print_attempt', CBT_Security_Log::$recordedEvents[0]['event_type']);
        self::assertSame('print_shortcut', CBT_Security_Log::$recordedEvents[0]['context']['source']);
        self::assertSame(1, CBT_Security_Log::$recordedEvents[0]['context']['blocked']);
        self::assertSame('desktop', CBT_Security_Log::$recordedEvents[0]['context']['device_type']);
        self::assertSame('windows', CBT_Security_Log::$recordedEvents[0]['context']['device_platform']);
    }

    #[RunInSeparateProcess]
    public function test_browser_security_event_accepts_shared_window_blur_signal(): void
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

        $response = \CBT_REST::security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'window_blur',
                'context' => [
                    'source' => 'blur',
                ],
            ],
            [
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ]
        ));

        self::assertIsArray($response);
        self::assertSame(1, $response['logged']);
        self::assertSame('window_blur', $response['event_type']);
        self::assertCount(1, CBT_Security_Log::$recordedEvents);
        self::assertSame('window_blur', CBT_Security_Log::$recordedEvents[0]['event_type']);
    }

    #[RunInSeparateProcess]
    public function test_browser_security_event_updates_live_presence_when_context_contains_focus_signals(): void
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
        CBT_Live_Proctoring_Presence::$updates = [];

        $response = \CBT_REST::security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'window_blur',
                'context' => [
                    'connection_status' => 'online',
                    'has_focus' => 0,
                    'heartbeat_lost_active' => 1,
                    'pending_sync_count' => 2,
                    'visibility_state' => 'hidden',
                ],
            ]
        ));

        self::assertIsArray($response);
        self::assertSame(1, $response['logged']);
        self::assertCount(1, CBT_Live_Proctoring_Presence::$updates);
        self::assertSame(114, CBT_Live_Proctoring_Presence::$updates[0]['attempt']['id']);
        self::assertSame('online', CBT_Live_Proctoring_Presence::$updates[0]['presence']['connection_status']);
        self::assertSame('hidden', CBT_Live_Proctoring_Presence::$updates[0]['presence']['visibility_state']);
        self::assertSame(0, CBT_Live_Proctoring_Presence::$updates[0]['presence']['has_focus']);
        self::assertSame(2, CBT_Live_Proctoring_Presence::$updates[0]['presence']['pending_sync_count']);
        self::assertSame(1, CBT_Live_Proctoring_Presence::$updates[0]['presence']['heartbeat_lost_active']);
    }

    #[RunInSeparateProcess]
    public function test_browser_security_event_accepts_new_browser_only_signals(): void
    {
        $this->bootstrapNativeRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $eventTypes = [
            'devtools_shortcut_blocked',
            'view_source_blocked',
            'save_page_blocked',
            'screenshot_key_detected',
            'heartbeat_lost',
        ];

        foreach ($eventTypes as $eventType) {
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
            CBT_Security_Log::$recordedEvents = [];

            $response = \CBT_REST::security_event(new \WP_REST_Request(
                [],
                [
                    'attempt_id' => 114,
                    'event_type' => $eventType,
                    'context' => [
                        'source' => 'session_heartbeat',
                    ],
                ],
                [
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ]
            ));

            self::assertIsArray($response);
            self::assertSame(1, $response['logged']);
            self::assertSame($eventType, $response['event_type']);
            self::assertCount(1, CBT_Security_Log::$recordedEvents);
            self::assertSame($eventType, CBT_Security_Log::$recordedEvents[0]['event_type']);
        }
    }

    #[RunInSeparateProcess]
    public function test_browser_security_event_rejects_native_only_signal_with_native_endpoint_error(): void
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

        $response = \CBT_REST::security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'task_manager_blocked',
            ]
        ));

        self::assertTrue(is_wp_error($response));
        self::assertSame('native_event_requires_native_endpoint', $response->get_error_code());
        self::assertSame([], CBT_Security_Log::$recordedEvents);
    }

    #[RunInSeparateProcess]
    public function test_browser_security_event_rejects_server_derived_repeat_signal(): void
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

        $response = \CBT_REST::security_event(new \WP_REST_Request(
            [],
            [
                'attempt_id' => 114,
                'event_type' => 'fullscreen_exit_repeat',
            ]
        ));

        self::assertTrue(is_wp_error($response));
        self::assertSame('invalid_event_type', $response->get_error_code());
        self::assertSame([], CBT_Security_Log::$recordedEvents);
    }

    #[RunInSeparateProcess]
    public function test_native_security_event_rejects_browser_only_signals(): void
    {
        $this->bootstrapNativeRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $eventTypes = [
            'devtools_shortcut_blocked',
            'view_source_blocked',
            'save_page_blocked',
            'screenshot_key_detected',
            'heartbeat_lost',
        ];

        foreach ($eventTypes as $eventType) {
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
            CBT_Security_Log::$recordedEvents = [];

            $response = \CBT_REST::native_security_event(new \WP_REST_Request(
                [],
                [
                    'attempt_id' => 114,
                    'event_type' => $eventType,
                    'native_app' => 'windows_cefsharp',
                ]
            ));

            self::assertTrue(is_wp_error($response));
            self::assertSame('invalid_native_event_type', $response->get_error_code());
            self::assertSame([], CBT_Security_Log::$recordedEvents);
        }
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
            'window_blur' => ['label' => 'Fokus window berpindah', 'severity' => 'warning', 'message' => ''],
            'print_attempt' => ['label' => 'Percobaan print', 'severity' => 'warning', 'message' => ''],
            'screenshot_key_detected' => ['label' => 'Tombol screenshot terdeteksi', 'severity' => 'warning', 'message' => ''],
            'context_menu_blocked' => ['label' => 'Context menu diblok', 'severity' => 'warning', 'message' => ''],
            'devtools_shortcut_blocked' => ['label' => 'Shortcut DevTools diblok', 'severity' => 'warning', 'message' => ''],
            'view_source_blocked' => ['label' => 'View source diblok', 'severity' => 'warning', 'message' => ''],
            'save_page_blocked' => ['label' => 'Simpan halaman diblok', 'severity' => 'warning', 'message' => ''],
            'heartbeat_lost' => ['label' => 'Heartbeat session hilang', 'severity' => 'warning', 'message' => ''],
            'fullscreen_exit_repeat' => ['label' => 'Keluar fullscreen berulang', 'severity' => 'critical', 'message' => ''],
            'task_manager_blocked' => ['label' => 'Task Manager diblok', 'severity' => 'warning', 'message' => ''],
            'session_revoked' => ['label' => 'Sesi dicabut', 'severity' => 'critical', 'message' => ''],
        ];
    }

    public static function browser_supported_event_definitions(): array
    {
        return [
            'tab_hidden' => ['label' => 'Pindah tab / aplikasi', 'severity' => 'warning', 'message' => ''],
            'window_blur' => ['label' => 'Fokus window berpindah', 'severity' => 'warning', 'message' => ''],
            'print_attempt' => ['label' => 'Percobaan print', 'severity' => 'warning', 'message' => ''],
            'screenshot_key_detected' => ['label' => 'Tombol screenshot terdeteksi', 'severity' => 'warning', 'message' => ''],
            'context_menu_blocked' => ['label' => 'Context menu diblok', 'severity' => 'warning', 'message' => ''],
            'devtools_shortcut_blocked' => ['label' => 'Shortcut DevTools diblok', 'severity' => 'warning', 'message' => ''],
            'view_source_blocked' => ['label' => 'View source diblok', 'severity' => 'warning', 'message' => ''],
            'save_page_blocked' => ['label' => 'Simpan halaman diblok', 'severity' => 'warning', 'message' => ''],
            'heartbeat_lost' => ['label' => 'Heartbeat session hilang', 'severity' => 'warning', 'message' => ''],
        ];
    }

    public static function native_supported_event_definitions(): array
    {
        return [
            'tab_hidden' => ['label' => 'Pindah tab / aplikasi', 'severity' => 'warning', 'message' => ''],
            'window_blur' => ['label' => 'Fokus window berpindah', 'severity' => 'warning', 'message' => ''],
            'task_manager_blocked' => ['label' => 'Task Manager diblok', 'severity' => 'warning', 'message' => ''],
        ];
    }

    public static function native_supported_event_definitions_for_app(string $native_app): array
    {
        if ($native_app === 'windows_cefsharp') {
            return [
                'tab_hidden' => ['label' => 'Pindah tab / aplikasi', 'severity' => 'warning', 'message' => ''],
                'window_blur' => ['label' => 'Fokus window berpindah', 'severity' => 'warning', 'message' => ''],
                'task_manager_blocked' => ['label' => 'Task Manager diblok', 'severity' => 'warning', 'message' => ''],
            ];
        }

        return [
            'tab_hidden' => ['label' => 'Pindah tab / aplikasi', 'severity' => 'warning', 'message' => ''],
            'window_blur' => ['label' => 'Fokus window berpindah', 'severity' => 'warning', 'message' => ''],
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

    public static function record_attempt_event_for_context(array $attempt, string $event_type, array $context = []): bool
    {
        return self::record_attempt_event((int) ($attempt['id'] ?? 0), $event_type, $context);
    }
}
PHP);
        }

        if (!class_exists('CBT_Live_Proctoring_Presence')) {
            eval(<<<'PHP'
class CBT_Live_Proctoring_Presence
{
    public static array $updates = [];

    public static function is_available(): bool
    {
        return true;
    }

    public static function update_attempt_presence(array $attempt, array $presence): void
    {
        self::$updates[] = [
            'attempt' => $attempt,
            'presence' => $presence,
        ];
    }
}
PHP);
        }

if (!class_exists('CBT_Runtime')) {
    eval(<<<'PHP'
class CBT_Runtime
{
    public static function is_ready(): bool
    {
        return false;
    }
}
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

        public function get_var(string $query)
        {
            if (str_contains($query, 'duration_minutes')) {
                return 60;
            }

            return 0;
        }
    }
}
