<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class FinishExamIdempotencyAndRecoveryTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_finish_exam_returns_completed_response(): void
    {
        $this->bootstrapFinishExamScaffold();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 5;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['wpdb'] = new FinishExamFakeWpdb([
            42 => ['id' => 42, 'exam_id' => 8, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'finished_at' => null, 'score' => 0, 'max_score' => 0, 'question_order' => '[]', 'option_order' => '{}', 'extra_time_minutes' => 0, 'duration_seconds' => 0],
        ], [
            8 => ['id' => 8, 'duration_minutes' => 60, 'kkm_percentage' => 75.0, 'show_student_result' => 1, 'randomize_questions' => 0],
        ]);

        $request = new WP_REST_Request(['attempt_id' => 42], [], [], '/cbt/v1/finish_exam', 'POST');
        $response = CBT_REST::finish_exam($request);

        self::assertIsArray($response);
        self::assertSame(42, $response['attempt_id']);
        self::assertSame('completed', $response['status']);
        self::assertArrayHasKey('finished_at', $response);
    }

    #[RunInSeparateProcess]
    public function test_finish_exam_idempotent_on_already_completed(): void
    {
        $this->bootstrapFinishExamScaffold();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 5;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['wpdb'] = new FinishExamFakeWpdb([
            42 => ['id' => 42, 'exam_id' => 8, 'student_id' => 5, 'status' => 'completed', 'started_at' => '2026-03-24 10:00:00', 'finished_at' => '2026-03-24 11:00:00', 'score' => 80, 'max_score' => 100, 'question_order' => '[]', 'option_order' => '{}', 'extra_time_minutes' => 0, 'duration_seconds' => 3600],
        ], [
            8 => ['id' => 8, 'duration_minutes' => 60, 'kkm_percentage' => 75.0, 'show_student_result' => 1, 'randomize_questions' => 0],
        ]);

        $request = new WP_REST_Request(['attempt_id' => 42], [], [], '/cbt/v1/finish_exam', 'POST');
        $response = CBT_REST::finish_exam($request);

        self::assertIsArray($response);
        self::assertSame(42, $response['attempt_id']);
        self::assertSame('completed', $response['status']);
        self::assertSame(80.0, round((float) $response['score'], 2));
    }

    #[RunInSeparateProcess]
    public function test_finish_exam_rejects_wrong_student(): void
    {
        $this->bootstrapFinishExamScaffold();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 99;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['wpdb'] = new FinishExamFakeWpdb([
            42 => ['id' => 42, 'exam_id' => 8, 'student_id' => 5, 'status' => 'in_progress', 'started_at' => '2026-03-24 10:00:00', 'finished_at' => null, 'score' => 0, 'max_score' => 0, 'question_order' => '[]', 'option_order' => '{}', 'extra_time_minutes' => 0, 'duration_seconds' => 0],
        ], []);

        $request = new WP_REST_Request(['attempt_id' => 42], [], [], '/cbt/v1/finish_exam', 'POST');
        $response = CBT_REST::finish_exam($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('forbidden', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_finish_exam_rejects_non_student_role(): void
    {
        $this->bootstrapFinishExamScaffold();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 5;
        $GLOBALS['cbt_test_rest_auth_role'] = 'guru';
        $GLOBALS['wpdb'] = new FinishExamFakeWpdb([], []);

        $request = new WP_REST_Request(['attempt_id' => 42], [], [], '/cbt/v1/finish_exam', 'POST');
        $response = CBT_REST::finish_exam($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('forbidden', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_finish_exam_rejects_invalid_attempt_id(): void
    {
        $this->bootstrapFinishExamScaffold();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 5;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['wpdb'] = new FinishExamFakeWpdb([], []);

        $request = new WP_REST_Request(['attempt_id' => 0], [], [], '/cbt/v1/finish_exam', 'POST');
        $response = CBT_REST::finish_exam($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('invalid_payload', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_finish_exam_restricted_result_hides_score(): void
    {
        $this->bootstrapFinishExamScaffold();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 5;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['wpdb'] = new FinishExamFakeWpdb([
            42 => ['id' => 42, 'exam_id' => 8, 'student_id' => 5, 'status' => 'completed', 'started_at' => '2026-03-24 10:00:00', 'finished_at' => '2026-03-24 11:00:00', 'score' => 80, 'max_score' => 100, 'question_order' => '[]', 'option_order' => '{}', 'extra_time_minutes' => 0, 'duration_seconds' => 3600],
        ], [
            8 => ['id' => 8, 'duration_minutes' => 60, 'kkm_percentage' => 75.0, 'show_student_result' => 0, 'randomize_questions' => 0],
        ]);

        $request = new WP_REST_Request(['attempt_id' => 42], [], [], '/cbt/v1/finish_exam', 'POST');
        $response = CBT_REST::finish_exam($request);

        self::assertIsArray($response);
        self::assertSame('completed', $response['status']);
        self::assertArrayNotHasKey('score', $response);
        self::assertArrayNotHasKey('max_score', $response);
        self::assertArrayNotHasKey('percentage', $response);
    }

    private function bootstrapFinishExamScaffold(): void
    {
        if (!class_exists('CBT_Auth')) {
            eval(<<<'PHP'
class CBT_Auth {
    public static function current_user_id(\WP_REST_Request $r): int { return (int) ($GLOBALS['cbt_test_rest_auth_user_id'] ?? 0); }
    public static function current_user_role(\WP_REST_Request $r): string { return (string) ($GLOBALS['cbt_test_rest_auth_role'] ?? ''); }
}
PHP);
        }
        if (!class_exists('CBT_Runtime')) {
            eval(<<<'PHP'
class CBT_Runtime {
    public static function is_ready(): bool { return false; }
    public static function is_buffer_enabled(): bool { return false; }
    public static function acquire_finish_lock(int $id): string { return ''; }
    public static function release_finish_lock(int $id, string $t): void {}
    public static function flush_attempt(int $id, bool $f = false): array { return ['flushed' => 0]; }
    public static function clear_attempt_runtime(int $id): void {}
    public static function get_attempt_option_order(int $id, ?bool &$f = null): array { $f = false; return []; }
}
PHP);
        }
        if (!class_exists('CBT_UI_State')) {
            eval(<<<'PHP'
class CBT_UI_State {
    public static function clear_attempt_state(int $user_id, int $attempt_id): void {}
}
PHP);
        }
        if (!class_exists('CBT_Active_Attempt_Index')) {
            eval(<<<'PHP'
class CBT_Active_Attempt_Index {
    public static function clear_active_attempt(int $user_id, int $exam_id, int $attempt_id = 0): void {}
}
PHP);
        }
        if (!class_exists('CBT_Attempt_Session_Snapshot_Cache')) {
            eval(<<<'PHP'
class CBT_Attempt_Session_Snapshot_Cache {
    public static function update_attempt_status(int $attempt_id, string $status): void {}
}
PHP);
        }
        if (!class_exists('CBT_Attempt_Question_Contract_Cache')) {
            eval(<<<'PHP'
class CBT_Attempt_Question_Contract_Cache {
    public static function update_attempt_status(int $attempt_id, string $status): void {}
}
PHP);
        }
        if (!class_exists('CBT_Cache')) {
            require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        }
        if (!class_exists('CBT_Admin_Questions_Helper')) {
            require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
        }
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-scoring.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-finish-exam.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-shared.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
    }
}

final class FinishExamFakeWpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
    public string $last_error = '';

    private array $attempts;
    private array $exams;

    public function __construct(array $attempts, array $exams)
    {
        $this->attempts = $attempts;
        $this->exams = $exams;
    }

    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        return ['query' => $query, 'args' => $args];
    }

    public function get_row($prepared, $output = null): ?array
    {
        $args = is_array($prepared) ? ($prepared['args'] ?? []) : [];
        $query = is_array($prepared) ? ($prepared['query'] ?? '') : '';
        $id = (int) ($args[0] ?? 0);

        if (stripos($query, 'cbt_attempts') !== false) {
            return $this->attempts[$id] ?? null;
        }
        if (stripos($query, 'cbt_exams') !== false) {
            return $this->exams[$id] ?? null;
        }
        return null;
    }

    public function get_var($prepared): ?string
    {
        $args = is_array($prepared) ? ($prepared['args'] ?? []) : [];
        $query = is_array($prepared) ? ($prepared['query'] ?? '') : '';
        $id = (int) ($args[0] ?? 0);

        if (stripos($query, 'show_student_result') !== false) {
            return (string) ($this->exams[$id]['show_student_result'] ?? '1');
        }
        if (stripos($query, 'kkm_percentage') !== false) {
            return (string) ($this->exams[$id]['kkm_percentage'] ?? '75');
        }
        return '0';
    }

    public function get_results($prepared, $output = null): array
    {
        return [];
    }

    public function get_col($prepared): array
    {
        return [];
    }

    public function update(string $table, array $data, array $where, $format = null, $where_format = null): int
    {
        $id = (int) ($where['id'] ?? 0);
        if ($id > 0 && isset($this->attempts[$id])) {
            $this->attempts[$id] = array_merge($this->attempts[$id], $data);
        }
        return 1;
    }

    public function query(string $query): int
    {
        return 0;
    }
}
