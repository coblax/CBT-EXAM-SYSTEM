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

final class RestSessionPresenceSnapshotTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_get_session_updates_live_presence_without_changing_response_shape(): void
    {
        $this->bootstrapRestSessionScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestSessionPresenceFakeWpdb([
            114 => [
                'id' => 114,
                'exam_id' => 16,
                'student_id' => 7,
                'status' => 'in_progress',
                'started_at' => '2026-03-26 21:00:00',
                'extra_time_minutes' => 0,
                'question_order' => '[101,102]',
                'option_order' => '{}',
                'exam_duration_minutes' => 60,
            ],
        ]);

        $response = CBT_REST::get_session(new WP_REST_Request([
            'attempt_id' => 114,
            'presence_connection_status' => 'degraded',
            'presence_visibility_state' => 'hidden',
            'presence_has_focus' => 0,
            'presence_pending_sync_count' => 2,
            'presence_heartbeat_lost_active' => 1,
        ]));

        self::assertIsArray($response);
        self::assertSame(
            [
                'ok',
                'user_id',
                'role',
                'question_revision',
                'question_count',
                'question_order_signature',
                'attempt_timer',
                'show_student_result',
                'enable_calculator',
            ],
            array_keys($response)
        );
        self::assertSame(2, $response['question_count']);
        self::assertCount(1, CBT_Live_Proctoring_Presence::$updates);
        self::assertSame(114, CBT_Live_Proctoring_Presence::$updates[0]['attempt']['id']);
        self::assertSame(
            [
                'connection_status' => 'degraded',
                'visibility_state' => 'hidden',
                'has_focus' => 0,
                'pending_sync_count' => 2,
                'heartbeat_lost_active' => 1,
            ],
            CBT_Live_Proctoring_Presence::$updates[0]['presence']
        );
    }

    #[RunInSeparateProcess]
    public function test_get_session_without_presence_params_keeps_presence_snapshot_untouched(): void
    {
        $this->bootstrapRestSessionScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestSessionPresenceFakeWpdb([
            114 => [
                'id' => 114,
                'exam_id' => 16,
                'student_id' => 7,
                'status' => 'in_progress',
                'started_at' => '2026-03-26 21:00:00',
                'extra_time_minutes' => 0,
                'question_order' => '[101]',
                'option_order' => '{}',
                'exam_duration_minutes' => 60,
            ],
        ]);

        $response = CBT_REST::get_session(new WP_REST_Request([
            'attempt_id' => 114,
        ]));

        self::assertIsArray($response);
        self::assertSame(1, $response['question_count']);
        self::assertSame([], CBT_Live_Proctoring_Presence::$updates);
    }

    private function bootstrapRestSessionScaffold(): void
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

        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache
{
    public static function get_exam_revision_meta(int $exam_id)
    {
        return null;
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
    }
}

if (!class_exists('RestSessionPresenceFakeWpdb')) {
    class RestSessionPresenceFakeWpdb extends wpdb
    {
        /** @var array<int,array<string,mixed>> */
        private array $attemptRows;

        /** @param array<int,array<string,mixed>> $attemptRows */
        public function __construct(array $attemptRows)
        {
            $this->attemptRows = $attemptRows;
        }

        public function prepare(string $query, ...$args): string
        {
            foreach ($args as $arg) {
                $query = preg_replace('/%d/', (string) (int) $arg, $query, 1) ?? $query;
            }

            return $query;
        }

        public function get_row(string $query, $output = ARRAY_A)
        {
            if (str_contains($query, 'FROM wp_cbt_attempts a')) {
                if (preg_match('/WHERE a.id = (\d+)/', $query, $matches)) {
                    $attemptId = (int) ($matches[1] ?? 0);
                    return $this->attemptRows[$attemptId] ?? null;
                }
            }

            return null;
        }

        public function get_var(string $query)
        {
            return 0;
        }
    }
}
