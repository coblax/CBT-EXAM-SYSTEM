<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
final class ExamAvailabilityAutoWarmServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapServiceScaffold();
        $this->useFakeAvailabilityRedis();

        cbt_test_register_user([
            'ID' => 71,
            'display_name' => 'Salsa',
            'user_login' => 'salsa',
            'user_email' => 'salsa@example.com',
            'roles' => ['student'],
        ]);
        update_user_meta(71, 'kode_kelas', 'XI-A');
        update_user_meta(71, 'kode_ruang', 'R1');

        cbt_test_register_user([
            'ID' => 72,
            'display_name' => 'Bimo',
            'user_login' => 'bimo',
            'user_email' => 'bimo@example.com',
            'roles' => ['siswa_cbt'],
        ]);
        update_user_meta(72, 'kode_kelas', 'XI-A');
        update_user_meta(72, 'kode_ruang', 'R2');

        cbt_test_register_user([
            'ID' => 73,
            'display_name' => 'Dina',
            'user_login' => 'dina',
            'user_email' => 'dina@example.com',
            'roles' => ['student'],
        ]);
        update_user_meta(73, 'kode_kelas', 'XI-B');
        update_user_meta(73, 'kode_ruang', 'R3');
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_creates_active_session_and_batch_prepares_target_students(): void
    {
        $result = CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertTrue($result['success']);
        $state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        self::assertTrue($state['active']);
        self::assertSame(77, $state['exam_id']);
        self::assertSame(2, $state['target_student_count']);
        self::assertSame(2, $state['prepared_count']);
        self::assertGreaterThan(0, count($this->storedAvailabilitySnapshotKeysFor(71)));
        self::assertGreaterThan(0, count($this->storedAvailabilitySnapshotKeysFor(72)));
        self::assertSame([], $this->storedAvailabilitySnapshotKeysFor(73));
    }

    #[RunInSeparateProcess]
    public function test_start_for_different_exam_is_rejected_while_other_session_is_active(): void
    {
        CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        $result = CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 54,
            'title' => 'Ujian Biologi',
            'status' => 'published',
            'target_kelas' => 'XI-B',
        ]);

        self::assertFalse($result['success']);
        self::assertStringContainsString('exam lain masih aktif', $result['message']);
        self::assertSame(77, CBT_Exam_Availability_Auto_Warm_Service::get_state()['exam_id']);
    }

    #[RunInSeparateProcess]
    public function test_tick_and_stop_update_operational_state_and_expire_after_window(): void
    {
        CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        $tickState = CBT_Exam_Availability_Auto_Warm_Service::tick();
        self::assertTrue($tickState['active']);
        self::assertSame('active', $tickState['status']);
        self::assertNotSame('', $tickState['last_tick_at']);

        $stopResult = CBT_Exam_Availability_Auto_Warm_Service::stop_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);
        self::assertTrue($stopResult['success']);
        self::assertFalse(CBT_Exam_Availability_Auto_Warm_Service::get_state()['active']);
        self::assertSame('stopped', CBT_Exam_Availability_Auto_Warm_Service::get_state()['status']);

        CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774355401;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:30:01';

        $expiredState = CBT_Exam_Availability_Auto_Warm_Service::tick();
        self::assertFalse($expiredState['active']);
        self::assertSame('expired', $expiredState['status']);
        self::assertStringContainsString('berakhir', $expiredState['last_message']);
    }

    private function bootstrapServiceScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-availability-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-availability-auto-warm-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static function build_student_exam_availability_snapshot_payload(int $user_id): array
    {
        $user = get_user_by('id', $user_id);
        $display_name = $user instanceof WP_User ? (string) ($user->display_name ?: $user->user_login) : '';
        $username = $user instanceof WP_User ? (string) $user->user_login : '';

        return [
            'items' => [
                [
                    'id' => 77,
                    'title' => 'Ujian Matematika',
                    'target_kelas' => 'XI-A',
                    'starts_at' => '',
                    'ends_at' => '',
                    'availability_reason' => 'ok',
                    'is_available_now' => 1,
                ],
            ],
            'current_user' => [
                'user_id' => $user_id,
                'display_name' => $display_name,
                'username' => $username,
                'kode_kelas' => (string) get_user_meta($user_id, 'kode_kelas', true),
                'kode_ruang' => (string) get_user_meta($user_id, 'kode_ruang', true),
            ],
        ];
    }
}
PHP);
        }
    }

    private function useFakeAvailabilityRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Availability_Cache::class);

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

    /**
     * @return array<int,string>
     */
    private function storedAvailabilitySnapshotKeysFor(int $userId): array
    {
        $prefix = 'cbt_exam_availability:student:user:' . $userId . ':';
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($prefix): bool {
            return is_string($key) && strpos($key, $prefix) === 0;
        }));
    }
}
