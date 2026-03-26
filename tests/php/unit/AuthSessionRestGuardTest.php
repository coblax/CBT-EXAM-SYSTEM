<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AuthSessionRestGuardTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_finish_exam_rejects_attempts_owned_by_another_user(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new AuthSessionRestGuardFakeWpdb([
            55 => [
                'id' => 55,
                'exam_id' => 9,
                'student_id' => 99,
                'status' => 'in_progress',
                'started_at' => '2026-03-24 11:00:00',
            ],
        ]);

        $result = \CBT_REST::finish_exam(new \WP_REST_Request([
            'attempt_id' => 55,
        ]));

        self::assertTrue(is_wp_error($result));
        self::assertSame('forbidden', $result->get_error_code());
        self::assertSame(['status' => 403], $result->get_error_data());
    }

    private function bootstrapRestScaffold(): void
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

        if (!class_exists('CBT_Runtime')) {
            eval(<<<'PHP'
class CBT_Runtime
{
    public static function acquire_finish_lock(int $attempt_id): string
    {
        return '';
    }

    public static function release_finish_lock(int $attempt_id, string $token): void
    {
    }
}
PHP);
        }

        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache
{
    public static function acquire_lock(string $key, int $ttl = 0, array $context = []): bool
    {
        return false;
    }

    public static function release_lock(string $key): void
    {
    }
}
PHP);
        }
    }
}

final class AuthSessionRestGuardFakeWpdb
{
    public string $prefix = 'wp_';

    /** @param array<int,array<string,mixed>> $attemptRows */
    public function __construct(private array $attemptRows)
    {
    }

    /** @return array<string,mixed> */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_row($prepared, $output = null): ?array
    {
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $attemptId = isset($args[0]) ? (int) $args[0] : 0;

        return $this->attemptRows[$attemptId] ?? null;
    }
}
