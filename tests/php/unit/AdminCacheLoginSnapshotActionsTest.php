<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-auth-snapshot-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-question-submission-context-cache.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-cache-service.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-cache-actions.php';

final class AdminCacheLoginSnapshotActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        cbt_test_register_user([
            'ID' => 71,
            'display_name' => 'Salsa',
            'roles' => ['student'],
            'user_email' => 'salsa@example.com',
            'user_login' => 'salsa',
            'user_pass' => 'secret',
        ]);
        update_user_meta(71, 'kode_kelas', 'XI-A');
        update_user_meta(71, 'kode_ruang', 'R1');
        update_user_meta(71, 'agama', 'Islam');
        update_user_meta(71, 'nisn', '71001');

        cbt_test_register_user([
            'ID' => 72,
            'display_name' => 'Bimo',
            'roles' => ['student'],
            'user_email' => 'bimo@example.com',
            'user_login' => 'bimo',
            'user_pass' => 'secret-2',
        ]);
        update_user_meta(72, 'kode_kelas', 'XI-A');
        update_user_meta(72, 'kode_ruang', 'R2');
        update_user_meta(72, 'agama', 'Islam');
        update_user_meta(72, 'nisn', '72001');

        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        $GLOBALS['wpdb'] = new AdminCacheLoginSnapshotActionsFakeWpdb();
    }

    public function test_handle_cache_action_warms_and_clears_login_snapshot_by_exam(): void
    {
        $_POST = [
            'operation' => 'warm_login_snapshot_exam',
            'exam_id' => '77',
        ];

        $this->invokeCacheActionExpectRedirect();

        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(72));
        self::assertStringContainsString('page=cbt-cache', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('Warm+login+snapshot+exam+%2377+selesai.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'operation' => 'clear_login_snapshot_exam',
            'exam_id' => '77',
        ];

        $this->invokeCacheActionExpectRedirect();

        self::assertSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertSame('', $this->storedLoginSnapshotPayloadFor(72));
        self::assertStringContainsString('Login+snapshot+exam+%2377+dibersihkan', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    public function test_handle_cache_action_warms_and_clears_login_snapshot_by_user(): void
    {
        $_POST = [
            'operation' => 'warm_login_snapshot_user',
            'user_id' => '71',
        ];

        $this->invokeCacheActionExpectRedirect();

        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertStringContainsString('Login+snapshot+siswa+%2371+siap.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'operation' => 'clear_login_snapshot_user',
            'user_id' => '71',
        ];

        $this->invokeCacheActionExpectRedirect();

        self::assertSame('', $this->storedLoginSnapshotPayloadFor(71));
        self::assertStringContainsString('Login+snapshot+siswa+%2371+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    public function test_handle_cache_action_warms_and_clears_submission_context_by_exam(): void
    {
        $_POST = [
            'operation' => 'warm_submission_context_exam',
            'exam_id' => '77',
        ];

        $this->invokeCacheActionExpectRedirect();

        self::assertNotSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertStringContainsString('page=cbt-cache', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('Submission+context+exam+%2377+siap.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $_POST = [
            'operation' => 'clear_submission_context_exam',
            'exam_id' => '77',
        ];

        $this->invokeCacheActionExpectRedirect();

        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertStringContainsString('Submission+context+exam+%2377+dibersihkan.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    private function invokeCacheActionExpectRedirect(): void
    {
        try {
            CBT_Admin_Cache_Actions::handle_cache_action();
            self::fail('Expected cache redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_cache_redirect__', $runtimeException->getMessage());
        }
    }

    private function useFakeProfileRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeLoginSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Login_Auth_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeSubmissionContextRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Question_Submission_Context_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function storedLoginSnapshotPayloadFor(int $userId): string
    {
        return (string) ($GLOBALS['cbt_test_redis_storage']['cbt_login_auth:user:' . $userId] ?? '');
    }

    /**
     * @return array<int,string>
     */
    private function storedSubmissionContextKeysFor(int $examId): array
    {
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($examId): bool {
            return is_string($key)
                && strpos($key, 'cbt_submit_context:') === 0
                && strpos($key, ':exam:' . $examId . ':') !== false;
        }));
    }
}

final class AdminCacheLoginSnapshotActionsFakeWpdb
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

    /**
     * @param string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = (string) $prepared;
        if (strpos($query, 'WHERE e.id = 77') !== false) {
            return [
                [
                    'id' => 77,
                    'title' => 'Ujian Matematika',
                    'status' => 'published',
                    'target_kelas' => 'XI-A',
                    'subject_name' => 'Matematika',
                ],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions') !== false && strpos($query, 'WHERE exam_id = 77') !== false) {
            return [
                ['id' => 977, 'question_type' => 'multiple_choice'],
                ['id' => 978, 'question_type' => 'short_answer'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions q') !== false && strpos($query, 'WHERE q.id IN (977,978)') !== false) {
            return [
                ['id' => 977, 'exam_id' => 77, 'question_type' => 'multiple_choice', 'points' => 1, 'correct_text' => '', 'true_false_correct_value' => null, 'short_answer_correct_text' => null],
                ['id' => 978, 'exam_id' => 77, 'question_type' => 'short_answer', 'points' => 1, 'correct_text' => '', 'true_false_correct_value' => null, 'short_answer_correct_text' => 'Jakarta'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false && strpos($query, 'WHERE question_id IN (977)') !== false) {
            return [
                ['id' => 9101, 'question_id' => 977, 'option_text' => 'A', 'is_correct' => 1],
                ['id' => 9102, 'question_id' => 977, 'option_text' => 'B', 'is_correct' => 0],
            ];
        }

        return [];
    }
}
