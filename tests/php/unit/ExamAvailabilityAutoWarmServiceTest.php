<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
final class ExamAvailabilityAutoWarmServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['cbt_test_availability_initial_burst_seconds'] = 60.0;
        $GLOBALS['cbt_test_availability_initial_burst_max_batches'] = 2;
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
        self::assertCount(1, CBT_REST::$batchAvailabilityPayloadRequests);
        self::assertSame([71, 72], CBT_REST::$batchAvailabilityPayloadRequests[0]);
        self::assertCount(1, (array) ($GLOBALS['cbt_test_redis_pipeline_batches'] ?? []));
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_runs_two_initial_bursts_for_large_target_batches(): void
    {
        for ($index = 0; $index < 158; $index++) {
            $user_id = 80 + $index;
            cbt_test_register_user([
                'ID' => $user_id,
                'display_name' => 'XI-A Burst ' . $index,
                'user_login' => 'xiburst_' . $index,
                'user_email' => 'xiburst_' . $index . '@example.com',
                'roles' => ['student'],
            ]);
            update_user_meta($user_id, 'kode_kelas', 'XI-A');
            update_user_meta($user_id, 'kode_ruang', 'RB');
        }

        $result = CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertTrue($result['success']);
        $state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        self::assertSame(160, $state['target_student_count']);
        self::assertSame(160, $state['prepared_count']);
        self::assertCount(2, CBT_REST::$batchAvailabilityPayloadRequests);
        self::assertCount(150, CBT_REST::$batchAvailabilityPayloadRequests[0]);
        self::assertCount(10, CBT_REST::$batchAvailabilityPayloadRequests[1]);
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_can_expand_initial_burst_when_budget_allows_more_batches(): void
    {
        $GLOBALS['cbt_test_availability_initial_burst_max_batches'] = 3;

        for ($index = 0; $index < 308; $index++) {
            $user_id = 200 + $index;
            cbt_test_register_user([
                'ID' => $user_id,
                'display_name' => 'XI-A Adaptive ' . $index,
                'user_login' => 'xiadaptive_' . $index,
                'user_email' => 'xiadaptive_' . $index . '@example.com',
                'roles' => ['student'],
            ]);
            update_user_meta($user_id, 'kode_kelas', 'XI-A');
            update_user_meta($user_id, 'kode_ruang', 'RA');
        }

        $result = CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertTrue($result['success']);
        $state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        self::assertSame(310, $state['target_student_count']);
        self::assertSame(310, $state['prepared_count']);
        self::assertCount(3, CBT_REST::$batchAvailabilityPayloadRequests);
        self::assertCount(150, CBT_REST::$batchAvailabilityPayloadRequests[0]);
        self::assertCount(150, CBT_REST::$batchAvailabilityPayloadRequests[1]);
        self::assertCount(10, CBT_REST::$batchAvailabilityPayloadRequests[2]);
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_reuses_preflight_target_snapshot_when_provided(): void
    {
        $result = CBT_Exam_Availability_Auto_Warm_Service::start_for_exam(
            [
                'id' => 77,
                'title' => 'Ujian Matematika',
                'status' => 'published',
                'target_kelas' => 'XI-A',
            ],
            [
                'exam_id' => 77,
                'target_student_ids' => [72],
                'target_source' => 'preflight_snapshot',
                'source_preflight_state' => 'preflight-77-xyz',
            ]
        );

        self::assertTrue($result['success']);
        $state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        self::assertTrue($state['active']);
        self::assertSame(77, $state['exam_id']);
        self::assertSame([72], array_values($state['target_student_ids']));
        self::assertSame(1, $state['target_student_count']);
        self::assertSame('preflight_snapshot', $state['target_source']);
        self::assertSame('preflight-77-xyz', $state['source_preflight_state']);
        self::assertGreaterThan(0, count($this->storedAvailabilitySnapshotKeysFor(72)));
        self::assertSame([], $this->storedAvailabilitySnapshotKeysFor(71));
        self::assertCount(1, CBT_REST::$batchAvailabilityPayloadRequests);
        self::assertSame([72], CBT_REST::$batchAvailabilityPayloadRequests[0]);
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
    public function test_enqueue_rewarm_users_dedupes_and_repairs_queue_when_idle(): void
    {
        CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(71, static function (): array {
            return [
                'items' => [
                    [
                        'id' => 77,
                        'title' => 'Ujian Matematika',
                        'status' => 'published',
                        'target_kelas' => 'XI-A',
                        'question_count' => 10,
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                    ],
                ],
                'current_user' => [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });
        CBT_Cache::invalidate_user(71);

        $first = CBT_Exam_Availability_Auto_Warm_Service::enqueue_rewarm_users([71], 'version_changed', 'admin');
        $second = CBT_Exam_Availability_Auto_Warm_Service::enqueue_rewarm_users([71], 'version_changed', 'admin');

        self::assertSame(1, $first['enqueued_count']);
        self::assertSame(0, $first['updated_count']);
        self::assertSame(0, $first['rejected_count']);
        self::assertSame(0, $second['enqueued_count']);
        self::assertSame(1, $second['updated_count']);
        self::assertSame(1, CBT_Exam_Availability_Auto_Warm_Service::get_rewarm_queue_state()['queued_count']);

        CBT_Exam_Availability_Auto_Warm_Service::tick();

        $queueState = CBT_Exam_Availability_Auto_Warm_Service::get_rewarm_queue_state();
        $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(71);

        self::assertSame(0, $queueState['queued_count']);
        self::assertSame(1, $queueState['last_success_count']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame('prepared', $diagnostics['snapshot_source']);
        self::assertSame('repaired', $diagnostics['repair_status']);
    }

    #[RunInSeparateProcess]
    public function test_rewarm_queue_waits_while_exam_auto_warm_is_active(): void
    {
        CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(71, static function (): array {
            return [
                'items' => [
                    [
                        'id' => 77,
                        'title' => 'Ujian Matematika',
                        'status' => 'published',
                        'target_kelas' => 'XI-A',
                        'question_count' => 10,
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                    ],
                ],
                'current_user' => [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });
        CBT_Cache::invalidate_user(71);
        CBT_Exam_Availability_Auto_Warm_Service::enqueue_rewarm_users([71], 'version_changed', 'admin');

        CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        CBT_Exam_Availability_Auto_Warm_Service::tick();

        $queueState = CBT_Exam_Availability_Auto_Warm_Service::get_rewarm_queue_state();
        self::assertSame(1, $queueState['queued_count']);
        self::assertSame(0, $queueState['last_success_count']);
        self::assertSame('', $queueState['last_tick_at']);
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
    public static array $batchAvailabilityPayloadRequests = [];

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

    public static function build_batch_student_exam_availability_snapshot_payloads(array $user_ids): array
    {
        $user_ids = array_values(array_filter(array_map('intval', $user_ids)));
        self::$batchAvailabilityPayloadRequests[] = $user_ids;
        $payloads = [];

        foreach ($user_ids as $user_id) {
            $payloads[$user_id] = self::build_student_exam_availability_snapshot_payload($user_id);
        }

        return $payloads;
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
