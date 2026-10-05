<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminExamsAttemptStateClearTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_saving_exam_keeps_runtime_and_ui_state_of_in_progress_attempts(): void
    {
        eval(<<<'PHP'
class CBT_Runtime {
    public static array $cleared = [];
    public static function clear_attempt_runtime(int $attempt_id): void { self::$cleared[] = $attempt_id; }
}
class CBT_UI_State {
    public static array $cleared = [];
    public static function clear_attempt_states_by_attempt_ids(array $attempt_ids): void { self::$cleared = array_merge(self::$cleared, $attempt_ids); }
}
PHP);
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-audience-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';

            public function prepare(string $query, ...$args): string
            {
                return $query;
            }

            public function get_results($query, $output = null): array
            {
                return [
                    ['id' => '501', 'status' => 'in_progress'],
                    ['id' => '502', 'status' => 'completed'],
                    ['id' => '503', 'status' => 'abandoned'],
                ];
            }
        };

        $method = new ReflectionMethod(CBT_Admin_Exams_Service::class, 'clear_exam_attempt_states');
        $method->setAccessible(true);
        $method->invoke(null, 77);

        self::assertSame([502, 503], CBT_Runtime::$cleared, 'Runtime attempt in_progress (buffer jawaban) tidak boleh dihapus.');
        self::assertSame([502, 503], CBT_UI_State::$cleared, 'Tanda ragu-ragu attempt in_progress tidak boleh dihapus.');
    }
}
