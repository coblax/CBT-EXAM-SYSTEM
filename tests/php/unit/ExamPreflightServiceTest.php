<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ExamPreflightServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['cbt_test_preflight_initial_burst_seconds'] = 60.0;
        $GLOBALS['cbt_test_preflight_initial_burst_max_batches'] = 3;
        $GLOBALS['cbt_test_availability_initial_burst_seconds'] = 60.0;
        $GLOBALS['cbt_test_availability_initial_burst_max_batches'] = 3;
        $this->bootstrapServiceScaffold();
        global $wpdb;
        $wpdb = new ExamPreflightSubmissionContextFakeWpdb();
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeSubmissionContextRedis();

        for ($index = 0; $index < 152; $index++) {
            $user_id = 701 + $index;
            cbt_test_register_user([
                'ID' => $user_id,
                'display_name' => 'XI-A Student ' . $index,
                'user_login' => 'xia_' . $index,
                'user_email' => 'xia_' . $index . '@example.com',
                'user_pass' => 'pass-' . $index,
                'roles' => ['student'],
            ]);
            update_user_meta($user_id, 'kode_kelas', 'XI-A');
            update_user_meta($user_id, 'kode_ruang', 'R1');
            update_user_meta($user_id, 'agama', 'Islam');
        }

        cbt_test_register_user([
            'ID' => 901,
            'display_name' => 'XI-B Student',
            'user_login' => 'xib_1',
            'user_email' => 'xib_1@example.com',
            'user_pass' => 'pass-xib',
            'roles' => ['student'],
        ]);
        update_user_meta(901, 'kode_kelas', 'XI-B');
        update_user_meta(901, 'kode_ruang', 'R2');
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_warms_question_snapshot_and_batches_profile_plus_login_snapshot(): void
    {
        $this->registerAdditionalXiAStudents(300);

        $result = CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertTrue($result['success']);
        $state = CBT_Exam_Preflight_Service::get_state();
        self::assertTrue($state['active']);
        self::assertSame('active', $state['status']);
        self::assertSame(77, $state['exam_id']);
        self::assertTrue($state['question_snapshot_ready']);
        self::assertTrue($state['start_snapshot_ready']);
        self::assertTrue($state['submission_context_ready']);
        self::assertSame('ready', $state['stage_question']);
        self::assertSame('ready', $state['stage_start_snapshot']);
        self::assertSame('ready', $state['stage_submission_context']);
        self::assertSame('active', $state['stage_profiles']);
        self::assertSame('active', $state['stage_login_snapshot']);
        self::assertSame('active', $state['stage_auto_warm']);
        self::assertSame(452, $state['target_student_count']);
        self::assertSame('canonical_fallback', $state['target_source']);
        self::assertNotSame('', (string) $state['target_snapshot_created_at']);
        self::assertSame('XI-A', (string) $state['target_kelas_signature']);
        self::assertSame(1, $state['submission_context_question_count']);
        self::assertSame(1, $state['submission_context_ready_count']);
        self::assertSame(0, $state['submission_context_missing_count']);
        self::assertSame(0, $state['submission_context_invalid_count']);
        self::assertTrue($state['auto_warm_started']);
        self::assertSame(450, $state['profile_success_count']);
        self::assertSame(450, $state['login_snapshot_success_count']);
        self::assertSame(0, $state['profile_failure_count']);
        self::assertGreaterThan(0, count($this->storedExamSnapshotKeysFor(77)));
        self::assertGreaterThan(0, count($this->storedStartSnapshotKeysFor(77)));
        self::assertGreaterThan(0, count($this->storedSubmissionContextKeysFor(77)));
        self::assertNotSame('', $this->storedProfileSnapshotPayloadFor(701));
        self::assertNotSame('', $this->storedLoginSnapshotPayloadFor(701));
        self::assertGreaterThan(0, count((array) ($GLOBALS['cbt_test_redis_pipeline_batches'] ?? [])));
        self::assertStringContainsString('mode paralel Aggressive 150+', (string) $state['last_message']);

        $auto_warm_state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        self::assertTrue($auto_warm_state['active']);
        self::assertSame(77, $auto_warm_state['exam_id']);
        self::assertSame('canonical_fallback', $auto_warm_state['target_source']);
        self::assertSame((string) $state['session_id'], (string) $auto_warm_state['source_preflight_state']);
        self::assertSame((array) $state['target_student_ids'], (array) $auto_warm_state['target_student_ids']);
    }

    #[RunInSeparateProcess]
    public function test_tick_marks_session_completed_with_warnings_when_remaining_profile_batch_fails(): void
    {
        $this->registerAdditionalXiAStudents(300);

        CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        $this->useUnavailableProfileRedis();
        $state = CBT_Exam_Preflight_Service::tick();

        self::assertTrue($state['active']);
        self::assertSame('active', $state['status']);
        self::assertSame('ready', $state['stage_submission_context']);
        self::assertSame('warning', $state['stage_profiles']);
        self::assertSame('ready', $state['stage_login_snapshot']);
        self::assertSame('active', $state['stage_auto_warm']);
        self::assertSame(450, $state['profile_success_count']);
        self::assertSame(452, $state['login_snapshot_success_count']);
        self::assertSame(2, $state['profile_failure_count']);
        self::assertStringContainsString('mode paralel Aggressive 150+', (string) $state['last_message']);

        $auto_warm_state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        self::assertTrue($auto_warm_state['active']);
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_with_small_target_finishes_in_initial_burst_and_starts_auto_warm(): void
    {
        $result = CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 54,
            'title' => 'Ujian Biologi',
            'status' => 'published',
            'target_kelas' => 'XI-B',
        ]);

        self::assertTrue($result['success']);
        $state = CBT_Exam_Preflight_Service::get_state();
        self::assertFalse($state['active']);
        self::assertSame('completed', $state['status']);
        self::assertTrue($state['auto_warm_started']);
        self::assertSame(1, $state['target_student_count']);
        self::assertSame('canonical_fallback', $state['target_source']);
        self::assertSame(1, $state['profile_success_count']);
        self::assertSame(1, $state['login_snapshot_success_count']);
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_can_finish_large_target_in_initial_burst_when_budget_allows_more_batches(): void
    {
        $GLOBALS['cbt_test_preflight_initial_burst_max_batches'] = 4;
        $GLOBALS['cbt_test_availability_initial_burst_max_batches'] = 4;
        $this->registerAdditionalXiAStudents(300);

        $result = CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertTrue($result['success']);
        $state = CBT_Exam_Preflight_Service::get_state();
        self::assertFalse($state['active']);
        self::assertSame('completed', $state['status']);
        self::assertTrue($state['auto_warm_started']);
        self::assertSame(452, $state['profile_success_count']);
        self::assertSame(452, $state['login_snapshot_success_count']);
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_remains_allowed_when_other_auto_warm_exam_is_active(): void
    {
        $this->registerAdditionalXiAStudents(300);

        CBT_Exam_Availability_Auto_Warm_Service::start_for_exam([
            'id' => 54,
            'title' => 'Ujian Biologi',
            'status' => 'published',
            'target_kelas' => 'XI-B',
        ]);

        $result = CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertTrue($result['success']);
        self::assertSame(77, CBT_Exam_Preflight_Service::get_state()['exam_id']);
        self::assertTrue(CBT_Exam_Preflight_Service::get_state()['question_snapshot_ready']);
        self::assertTrue(CBT_Exam_Availability_Auto_Warm_Service::get_state()['active']);
        self::assertSame(54, CBT_Exam_Availability_Auto_Warm_Service::get_state()['exam_id']);
        self::assertSame('queued', CBT_Exam_Preflight_Service::get_state()['stage_auto_warm']);
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_marks_submission_context_as_warning_without_blocking_when_cache_is_unavailable(): void
    {
        $this->registerAdditionalXiAStudents(300);
        $this->useUnavailableSubmissionContextRedis();

        $result = CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertTrue($result['success']);
        $state = CBT_Exam_Preflight_Service::get_state();
        self::assertTrue($state['active']);
        self::assertSame('warning', $state['stage_submission_context']);
        self::assertFalse($state['submission_context_ready']);
        self::assertSame(1, $state['submission_context_question_count']);
        self::assertSame(0, $state['submission_context_ready_count']);
        self::assertSame(1, $state['submission_context_missing_count']);
        self::assertSame(0, $state['submission_context_invalid_count']);
        self::assertSame('ready', $state['stage_question']);
        self::assertSame('ready', $state['stage_start_snapshot']);
        self::assertSame('active', $state['stage_profiles']);
        self::assertSame('active', $state['stage_login_snapshot']);
        self::assertSame('active', $state['stage_auto_warm']);
    }

    #[RunInSeparateProcess]
    public function test_stop_for_exam_marks_active_session_as_stopped(): void
    {
        $this->registerAdditionalXiAStudents(300);

        CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        $result = CBT_Exam_Preflight_Service::stop_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertTrue($result['success']);
        self::assertStringContainsString('dihentikan manual', (string) ($result['message'] ?? ''));

        $state = CBT_Exam_Preflight_Service::get_state();
        self::assertFalse($state['active']);
        self::assertSame('stopped', $state['status']);
        self::assertSame('stopped', $state['stage_profiles']);
        self::assertSame('stopped', $state['stage_login_snapshot']);
    }

    #[RunInSeparateProcess]
    public function test_stop_for_exam_is_rejected_when_other_exam_is_active(): void
    {
        $this->registerAdditionalXiAStudents(300);

        CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        $result = CBT_Exam_Preflight_Service::stop_for_exam([
            'id' => 54,
            'title' => 'Ujian Biologi',
            'status' => 'published',
            'target_kelas' => 'XI-B',
        ]);

        self::assertFalse($result['success']);
        self::assertStringContainsString('exam lain', (string) ($result['message'] ?? ''));
        self::assertTrue(CBT_Exam_Preflight_Service::get_state()['active']);
    }

    #[RunInSeparateProcess]
    public function test_start_for_exam_queues_second_exam_while_first_exam_owns_global_runner(): void
    {
        $this->registerAdditionalXiAStudents(300);

        CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        $result = CBT_Exam_Preflight_Service::start_for_exam([
            'id' => 54,
            'title' => 'Ujian Biologi',
            'status' => 'published',
            'target_kelas' => 'XI-B',
        ]);

        self::assertTrue($result['success']);

        $jobs = CBT_Exam_Preflight_Service::get_jobs_state();
        self::assertSame('active', $jobs[77]['status']);
        self::assertSame('queued', $jobs[54]['status']);
        self::assertSame('ready', $jobs[54]['stage_question']);
        self::assertSame('ready', $jobs[54]['stage_start_snapshot']);
        self::assertSame('ready', $jobs[54]['stage_submission_context']);
        self::assertSame('queued', $jobs[54]['stage_profiles']);
        self::assertSame('queued', $jobs[54]['stage_login_snapshot']);
        self::assertSame('queued', $jobs[54]['stage_auto_warm']);
        self::assertSame(1, $jobs[54]['queue_position']);

        $runner = CBT_Exam_Preflight_Service::get_global_runner_state();
        self::assertSame(77, $runner['active_exam_id']);
        self::assertSame([54], $runner['queue_exam_ids']);
    }

    private function registerAdditionalXiAStudents(int $count): void
    {
        $count = max(0, $count);
        for ($index = 0; $index < $count; $index++) {
            $user_id = 1000 + $index;
            cbt_test_register_user([
                'ID' => $user_id,
                'display_name' => 'XI-A Extra ' . $index,
                'user_login' => 'xia_extra_' . $index,
                'user_email' => 'xia_extra_' . $index . '@example.com',
                'user_pass' => 'pass-extra-' . $index,
                'roles' => ['student'],
            ]);
            update_user_meta($user_id, 'kode_kelas', 'XI-A');
            update_user_meta($user_id, 'kode_ruang', 'R1');
            update_user_meta($user_id, 'agama', 'Islam');
        }
    }

    private function bootstrapServiceScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-availability-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-start-attempt-snapshot-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-question-submission-context-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-auth-snapshot-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-availability-auto-warm-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-preflight-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $warmedExamIds = [];
    public static array $warmedStartExamIds = [];
    public static array $warmedSubmissionContextExamIds = [];
    public static array $batchAvailabilityPayloadRequests = [];

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

    public static function warm_exam_start_attempt_snapshot(int $exam_id): void
    {
        self::$warmedStartExamIds[] = $exam_id;
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot($exam_id, static function (int $target_exam_id): array {
            return [
                'exam_id' => $target_exam_id,
                'question_ids' => [900 + $target_exam_id],
                'question_number_map' => [900 + $target_exam_id => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
    }

    public static function warm_exam_submission_context_snapshot(int $exam_id): void
    {
        self::$warmedSubmissionContextExamIds[] = $exam_id;
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots($exam_id);
    }

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

    private function useFakeStartSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Start_Attempt_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('start_snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('start_snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('start_snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
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

    private function useUnavailableProfileRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, null);

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Redis profile down');
    }

    private function useUnavailableSubmissionContextRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Question_Submission_Context_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, null);

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Redis submission context down');
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

    /**
     * @return array<int,string>
     */
    private function storedStartSnapshotKeysFor(int $examId): array
    {
        $prefix = 'cbt_exam_start_attempt:exam:' . $examId . ':';
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($prefix): bool {
            return is_string($key) && strpos($key, $prefix) === 0;
        }));
    }

    private function storedProfileSnapshotPayloadFor(int $userId): string
    {
        return (string) ($GLOBALS['cbt_test_redis_storage']['cbt_profile:user:' . $userId] ?? '');
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

final class ExamPreflightSubmissionContextFakeWpdb
{
    public string $prefix = 'wp_';

    /** @param string $query */
    public function get_results($query, $output = null): array
    {
        $query = (string) $query;

        if (strpos($query, 'FROM wp_cbt_questions') !== false && strpos($query, 'WHERE exam_id = 77') !== false) {
            return [
                ['id' => 977, 'question_type' => 'multiple_choice'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions') !== false && strpos($query, 'WHERE exam_id = 54') !== false) {
            return [
                ['id' => 954, 'question_type' => 'multiple_choice'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions q') !== false && strpos($query, 'WHERE q.id IN (977)') !== false) {
            return [
                [
                    'id' => 977,
                    'exam_id' => 77,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'correct_text' => '',
                    'true_false_correct_value' => null,
                    'short_answer_correct_text' => null,
                ],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions q') !== false && strpos($query, 'WHERE q.id IN (954)') !== false) {
            return [
                [
                    'id' => 954,
                    'exam_id' => 54,
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'correct_text' => '',
                    'true_false_correct_value' => null,
                    'short_answer_correct_text' => null,
                ],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false && strpos($query, 'WHERE question_id IN (977)') !== false) {
            return [
                ['id' => 9901, 'question_id' => 977, 'option_text' => 'A', 'is_correct' => 1],
                ['id' => 9902, 'question_id' => 977, 'option_text' => 'B', 'is_correct' => 0],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false && strpos($query, 'WHERE question_id IN (954)') !== false) {
            return [
                ['id' => 9541, 'question_id' => 954, 'option_text' => 'A', 'is_correct' => 1],
                ['id' => 9542, 'question_id' => 954, 'option_text' => 'B', 'is_correct' => 0],
            ];
        }

        return [];
    }
}
