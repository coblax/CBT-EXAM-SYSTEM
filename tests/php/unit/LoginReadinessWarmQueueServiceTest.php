<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

if (!class_exists('wpdb')) {
    class wpdb
    {
    }
}

final class LoginReadinessWarmQueueServiceTest extends TestCase
{
    private LoginReadinessWarmQueueFakeWpdb $fakeWpdb;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->fakeWpdb = new LoginReadinessWarmQueueFakeWpdb();
        $wpdb = $this->fakeWpdb;

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-auth-snapshot-cache.php';

        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();

        cbt_test_register_user([
            'ID' => 11,
            'display_name' => 'Salsa',
            'user_login' => 'salsa',
            'user_email' => 'salsa@example.com',
            'user_pass' => 'secret-11',
            'roles' => ['student'],
        ]);
        update_user_meta(11, 'kode_kelas', 'XI-A');
        update_user_meta(11, 'kode_ruang', 'R1');
        update_user_meta(11, 'nisn', '11001');
        update_user_meta(11, 'agama', 'Islam');

        cbt_test_register_user([
            'ID' => 12,
            'display_name' => 'Bimo',
            'user_login' => 'bimo',
            'user_email' => 'bimo@example.com',
            'user_pass' => 'secret-12',
            'roles' => ['student'],
        ]);
        update_user_meta(12, 'kode_kelas', 'XI-A');
        update_user_meta(12, 'kode_ruang', 'R2');
        update_user_meta(12, 'nisn', '12001');
        update_user_meta(12, 'agama', 'Islam');

        cbt_test_register_user([
            'ID' => 13,
            'display_name' => 'Dina',
            'user_login' => 'dina',
            'user_email' => 'dina@example.com',
            'user_pass' => 'secret-13',
            'roles' => ['student'],
        ]);
        update_user_meta(13, 'kode_kelas', 'XI-B');
        update_user_meta(13, 'kode_ruang', 'R3');
        update_user_meta(13, 'nisn', '13001');
        update_user_meta(13, 'agama', 'Islam');
    }

    protected function tearDown(): void
    {
        if (class_exists('CBT_Login_Readiness_Warm_Queue_Service')) {
            CBT_Login_Readiness_Warm_Queue_Service::deactivate();
        }

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    public function test_start_and_tick_process_canonical_fallback_queue_until_completed(): void
    {
        $this->bootstrapQueueService();

        $start = CBT_Login_Readiness_Warm_Queue_Service::start([
            'kelas' => 'XI-A',
        ], 'unit_test');

        self::assertTrue($start['success']);
        self::assertStringContainsString('Canonical Fallback', (string) $start['message']);

        $state = CBT_Login_Readiness_Warm_Queue_Service::get_state();
        self::assertTrue($state['active']);
        self::assertSame('active', $state['status']);
        self::assertSame('canonical_fallback', $state['source']);
        self::assertSame([11, 12], array_values($state['target_user_ids']));
        self::assertSame(2, $state['target_count']);

        $firstTick = CBT_Login_Readiness_Warm_Queue_Service::tick(1);
        self::assertTrue($firstTick['active']);
        self::assertSame(1, $firstTick['cursor']);
        self::assertSame(1, $firstTick['ready_count']);
        self::assertSame(0, $firstTick['failure_count']);

        $finalState = CBT_Login_Readiness_Warm_Queue_Service::tick(1);
        self::assertFalse($finalState['active']);
        self::assertSame('completed', $finalState['status']);
        self::assertSame(2, $finalState['cursor']);
        self::assertSame(2, $finalState['ready_count']);
        self::assertSame(0, $finalState['failure_count']);
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(11));
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(12));
    }

    #[RunInSeparateProcess]
    public function test_start_rejects_duplicate_active_queue(): void
    {
        $this->bootstrapQueueService();

        $first = CBT_Login_Readiness_Warm_Queue_Service::start([
            'kelas' => 'XI-A',
            'ruang' => 'R1',
        ], 'unit_test');
        $second = CBT_Login_Readiness_Warm_Queue_Service::start([
            'kelas' => 'XI-B',
        ], 'unit_test');

        self::assertTrue($first['success']);
        self::assertFalse($second['success']);
        self::assertStringContainsString('Queue sedang berjalan', (string) $second['message']);
        self::assertSame([11], array_values((array) ($second['state']['target_user_ids'] ?? [])));
    }

    #[RunInSeparateProcess]
    public function test_start_rejects_empty_target_and_exam_filter_respects_target_kelas(): void
    {
        $this->bootstrapQueueService();

        $empty = CBT_Login_Readiness_Warm_Queue_Service::start([
            'kelas' => 'XII-Z',
        ], 'unit_test');
        self::assertFalse($empty['success']);
        self::assertStringContainsString('minimal satu siswa target', (string) $empty['message']);

        $examMismatch = CBT_Login_Readiness_Warm_Queue_Service::start([
            'exam_id' => 77,
            'kelas' => 'XI-B',
        ], 'unit_test');
        self::assertFalse($examMismatch['success']);
        self::assertStringContainsString('exam/filter ini', (string) $examMismatch['message']);
    }

    #[RunInSeparateProcess]
    public function test_start_is_blocked_when_cohort_index_is_building(): void
    {
        if (!class_exists('CBT_Student_Cohort_Index_Service')) {
            eval(<<<'PHP'
class CBT_Student_Cohort_Index_Service
{
    public static function reset_availability_cache(): void
    {
    }

    public static function get_health_summary(): array
    {
        return [
            'available' => true,
            'ready' => false,
            'status' => 'building',
            'label' => 'Building',
        ];
    }

    public static function get_filter_options(): array
    {
        return [
            'ready' => false,
            'fallback_required' => true,
            'kelas' => [],
            'ruang' => [],
        ];
    }
}
PHP);
        }

        $this->bootstrapQueueService();

        $result = CBT_Login_Readiness_Warm_Queue_Service::start([
            'kelas' => 'XI-A',
        ], 'unit_test');

        self::assertFalse($result['success']);
        self::assertStringContainsString('masih building', (string) $result['message']);

        $panel = CBT_Login_Readiness_Warm_Queue_Service::get_panel_context();
        self::assertSame('building', (string) ($panel['cohort_summary']['status'] ?? ''));
        self::assertSame([], array_values((array) ($panel['kelas_options'] ?? [])));
        self::assertSame([], array_values((array) ($panel['ruang_options'] ?? [])));
    }

    private function bootstrapQueueService(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-readiness-warm-queue-service.php';
    }

    private function storedLoginSnapshotPayloadFor(int $userId): string
    {
        return (string) ($GLOBALS['cbt_test_redis_storage']['cbt_login_auth:user:' . $userId] ?? '');
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
}

final class LoginReadinessWarmQueueFakeWpdb extends wpdb
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

    public function get_row($prepared, $output = null): ?array
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT id, title, status, target_kelas FROM wp_cbt_exams WHERE id = 77') !== false) {
            return ['id' => 77, 'title' => 'Ujian Matematika', 'status' => 'published', 'target_kelas' => 'XI-A'];
        }

        if (strpos($query, 'SELECT id, title, status, target_kelas FROM wp_cbt_exams WHERE id = 54') !== false) {
            return ['id' => 54, 'title' => 'Ujian Biologi', 'status' => 'published', 'target_kelas' => 'XI-B'];
        }

        return null;
    }
}
