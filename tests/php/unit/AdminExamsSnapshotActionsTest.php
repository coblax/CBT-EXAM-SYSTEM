<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
final class AdminExamsSnapshotActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_exam_delivery_snapshot_redirects_back_to_snapshot_tab(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
        ];

        try {
            CBT_Admin_Exams_Service::handle_warm_exam_delivery_snapshot();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([77], CBT_REST::$warmedExamIds);
        self::assertStringContainsString('page=cbt-exams', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_status=published', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_exam_id=77', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=2', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+soal+exam+%2377+siap.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_warm_bulk_exam_delivery_snapshots_warms_filtered_exams_only(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();

        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        $_POST = [
            'cbt_exam_status' => 'published',
        ];

        try {
            CBT_Admin_Exams_Service::handle_warm_bulk_exam_delivery_snapshots();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([77, 54], CBT_REST::$warmedExamIds);
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_status=published', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+menyiapkan+2+snapshot+soal.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clear_exam_delivery_snapshot_removes_snapshot_and_redirects_back(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 977,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });

        $_POST = [
            'exam_id' => '77',
            'cbt_exam_status' => 'published',
            'cbt_exam_snapshot_page_77' => '3',
        ];

        try {
            CBT_Admin_Exams_Service::handle_clear_exam_delivery_snapshot();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_exam_snapshot_page_77=3', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Snapshot+soal+exam+%2377+berhasil+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_clear_bulk_exam_delivery_snapshots_clears_filtered_exams_only(): void
    {
        $this->bootstrapSnapshotActionScaffold();
        $this->useFakeDeliveryRedis();

        global $wpdb;
        $wpdb = new AdminExamsSnapshotActionsFakeWpdb();

        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 977,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload(54, static function (int $examId): array {
            return [
                [
                    'id' => 954,
                    'exam_id' => $examId,
                    'question_text' => 'Snapshot exam ' . $examId,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });

        $_POST = [
            'cbt_exam_status' => 'published',
        ];

        try {
            CBT_Admin_Exams_Service::handle_clear_bulk_exam_delivery_snapshots();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedExamSnapshotKeysFor(54));
        self::assertStringContainsString('cbt_exam_panel=snapshot', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Berhasil+membersihkan+snapshot+soal+untuk+2+exam.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    private function bootstrapSnapshotActionScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';

        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $warmedExamIds = [];

    public static function warm_exam_question_delivery_snapshot(int $exam_id): void
    {
        self::$warmedExamIds[] = $exam_id;
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload($exam_id, static function (int $target_exam_id): array {
            return [
                [
                    'id' => 900 + $target_exam_id,
                    'exam_id' => $target_exam_id,
                    'question_text' => 'Snapshot exam ' . $target_exam_id,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [],
                ],
            ];
        });
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';
    }

    private function useFakeDeliveryRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Question_Delivery_Cache::class);

        $redisProperty = $reflection->getProperty('delivery_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('delivery_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('delivery_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    /**
     * @return array<int,string>
     */
    private function storedExamSnapshotKeysFor(int $examId): array
    {
        $prefix = 'cbt_exam_delivery:exam:' . $examId . ':';
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($prefix): bool {
            return is_string($key) && strpos($key, $prefix) === 0;
        }));
    }
}

final class AdminExamsSnapshotActionsFakeWpdb
{
    public string $prefix = 'wp_';

    public function prepare(string $query, ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_int($arg) || is_float($arg)
                ? (string) $arg
                : "'" . str_replace("'", "\\'", (string) $arg) . "'";
            $query = preg_replace('/%[dfs]/', $replacement, $query, 1) ?? $query;
        }

        return $query;
    }

    public function esc_like(string $text): string
    {
        return $text;
    }

    /**
     * @param string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT e.id, e.title, e.status, s.name AS subject_name') !== false) {
            return [
                ['id' => 77, 'title' => 'Ujian Matematika', 'status' => 'published', 'subject_name' => 'Matematika'],
                ['id' => 54, 'title' => 'Ujian Biologi', 'status' => 'published', 'subject_name' => 'Biologi'],
            ];
        }

        return [];
    }
}
