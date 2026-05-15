<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestQuestionDeliverySnapshotTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_get_questions_student_without_attempt_is_rejected_before_question_payload_hydration(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        $response = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
        ], [], [], '/cbt/v1/questions', 'GET'));

        self::assertTrue(is_wp_error($response));
        self::assertSame('attempt_required', $response->get_error_code());
        $errorData = $response->get_error_data();
        self::assertIsArray($errorData);
        self::assertSame(400, (int) ($errorData['status'] ?? 0));
        self::assertSame(1, $wpdb->examGetRowCalls);
        self::assertSame(0, $wpdb->attemptGetRowCalls);
        self::assertSame(0, $wpdb->questionHydrateCalls);
        self::assertSame(0, $wpdb->optionHydrateCalls);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_student_completed_attempt_is_rejected_before_question_payload_hydration(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();
        $wpdb->attemptStatus = 'completed';

        $response = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
        ], [], [], '/cbt/v1/questions', 'GET'));

        self::assertTrue(is_wp_error($response));
        self::assertSame('attempt_closed', $response->get_error_code());
        $errorData = $response->get_error_data();
        self::assertIsArray($errorData);
        self::assertSame(400, (int) ($errorData['status'] ?? 0));
        self::assertSame(1, $wpdb->examGetRowCalls);
        self::assertSame(1, $wpdb->attemptGetRowCalls);
        self::assertSame(0, $wpdb->questionHydrateCalls);
        self::assertSame(0, $wpdb->optionHydrateCalls);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_teacher_without_attempt_can_preview_exam_questions(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 5;
        $GLOBALS['cbt_test_rest_auth_role'] = 'teacher';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        $response = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
        ], [], [], '/cbt/v1/questions', 'GET'));

        self::assertFalse(is_wp_error($response));
        $payload = $response instanceof WP_REST_Response ? $response->get_data() : (array) $response;
        self::assertCount(1, (array) ($payload['items'] ?? []));
        self::assertSame('Jakarta', $payload['items'][0]['correct_text'] ?? null);
        self::assertSame(1, $wpdb->examGetRowCalls);
        self::assertSame(0, $wpdb->attemptGetRowCalls);
        self::assertSame(1, $wpdb->questionHydrateCalls);
        self::assertSame(1, $wpdb->optionHydrateCalls);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_student_attempt_uses_redis_delivery_snapshot_without_rehydrating_question_payload_from_db(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->useDeliveryFakeRedis();
        $this->useAttemptContractFakeRedis();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        CBT_REST::warm_exam_question_delivery_snapshot(55);
        self::assertSame(1, $wpdb->questionHydrateCalls);
        self::assertSame(1, $wpdb->optionHydrateCalls);

        $response = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
        ], [], [], '/cbt/v1/questions', 'GET'));

        self::assertFalse(is_wp_error($response));
        self::assertSame(1, $wpdb->questionHydrateCalls);
        self::assertSame(1, $wpdb->optionHydrateCalls);
        self::assertSame(1, $wpdb->attemptGetRowCalls);
        self::assertSame(1, $wpdb->examGetRowCalls);
        self::assertSame(1, $response['total_questions']);
        self::assertSame([201], $response['question_order_ids']);
        self::assertNotSame('', (string) $response['question_order_signature']);
        self::assertSame(55, $response['question_revision']['exam_id']);
        self::assertArrayNotHasKey('correct_text', $response['items'][0]);
        self::assertSame('Ibu Kota Indonesia?', $response['items'][0]['question_text']);
        self::assertSame(9002, $response['items'][0]['existing_answer']);
        self::assertArrayHasKey('cbt_attempt_contract:attempt:77', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
    }

    #[RunInSeparateProcess]
    public function test_refresh_exam_question_snapshots_after_question_updates_patches_ready_snapshots(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->useDeliveryFakeRedis();
        $this->useStartSnapshotFakeRedis();
        $this->setRuntimeRedisUnavailable();

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        CBT_REST::warm_exam_question_delivery_snapshot(55);
        CBT_REST::warm_exam_start_attempt_snapshot(55);
        self::assertSame(2, $wpdb->questionHydrateCalls);
        self::assertSame(2, $wpdb->optionHydrateCalls);

        $wpdb->questionText = 'Ibu Kota Nusantara?';
        $wpdb->questionUpdatedAt = '2026-04-03 05:10:00';
        $wpdb->optionBText = 'Nusantara';

        $result = CBT_REST::refresh_exam_question_snapshots_after_question_updates(55, [201]);

        self::assertTrue($result['success']);
        self::assertSame('partial', $result['mode']);
        self::assertSame('patched', $result['reason']);
        self::assertSame([201], $result['question_ids']);
        self::assertSame(3, $wpdb->questionHydrateCalls);
        self::assertSame(3, $wpdb->optionHydrateCalls);

        $deliveryProducerCalls = 0;
        $items = CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, static function () use (&$deliveryProducerCalls): array {
            $deliveryProducerCalls++;
            return [];
        });
        self::assertSame(0, $deliveryProducerCalls);
        self::assertSame('Ibu Kota Nusantara?', $items[0]['question_text'] ?? '');
        self::assertSame('Nusantara', $items[0]['options'][1]['option_text'] ?? '');

        $startProducerCalls = 0;
        $startSnapshot = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, static function () use (&$startProducerCalls): array {
            $startProducerCalls++;
            return [];
        });
        self::assertSame(0, $startProducerCalls);
        self::assertSame([201], $startSnapshot['question_ids']);
        self::assertSame(1, $startSnapshot['question_count']);
        self::assertSame('2026-04-03 05:10:00', $startSnapshot['question_manifest'][0]['updated_at'] ?? '');
        self::assertSame(['9001', '9002'], $startSnapshot['option_randomization_tokens_by_question'][201] ?? []);
    }

    #[RunInSeparateProcess]
    public function test_refresh_exam_question_snapshots_after_question_updates_patches_multiple_questions_with_one_revision_bump(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->useDeliveryFakeRedis();
        $this->useStartSnapshotFakeRedis();
        $this->setRuntimeRedisUnavailable();

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();
        $wpdb->includeSecondQuestion = true;

        CBT_REST::warm_exam_question_delivery_snapshot(55);
        CBT_REST::warm_exam_start_attempt_snapshot(55);
        $beforeRevision = CBT_Cache::get_exam_revision_meta(55);

        $wpdb->questionText = 'Ibu Kota Nusantara?';
        $wpdb->questionUpdatedAt = '2026-04-03 05:10:00';
        $wpdb->optionBText = 'Nusantara';
        $wpdb->secondQuestionText = 'Warna langit siang?';
        $wpdb->secondQuestionUpdatedAt = '2026-04-03 05:11:00';
        $wpdb->secondOptionBText = 'Biru';

        $result = CBT_REST::refresh_exam_question_snapshots_after_question_updates(55, [201, 202]);

        self::assertTrue($result['success']);
        self::assertSame('partial', $result['mode']);
        self::assertSame([201, 202], $result['question_ids']);
        self::assertSame(3, $wpdb->questionHydrateCalls);
        self::assertSame(3, $wpdb->optionHydrateCalls);

        $afterRevision = CBT_Cache::get_exam_revision_meta(55);
        self::assertSame(((int) $beforeRevision['version']) + 1, (int) $afterRevision['version']);

        $items = CBT_Exam_Question_Delivery_Cache::get_exam_payload(55, static function (): array {
            return [];
        });
        self::assertSame(['Ibu Kota Nusantara?', 'Warna langit siang?'], array_column($items, 'question_text'));
        self::assertSame('Nusantara', $items[0]['options'][1]['option_text'] ?? '');
        self::assertSame('Biru', $items[1]['options'][1]['option_text'] ?? '');

        $startSnapshot = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot(55, static function (): array {
            return [];
        });
        self::assertSame([201, 202], $startSnapshot['question_ids']);
        self::assertSame(2, $startSnapshot['question_count']);
        self::assertSame([201 => 1, 202 => 2], $startSnapshot['question_number_map']);
        self::assertSame(['2026-04-03 05:10:00', '2026-04-03 05:11:00'], array_column($startSnapshot['question_manifest'], 'updated_at'));
        self::assertSame(['9001', '9002'], $startSnapshot['option_randomization_tokens_by_question'][201] ?? []);
        self::assertSame(['9011', '9012'], $startSnapshot['option_randomization_tokens_by_question'][202] ?? []);
    }

    #[RunInSeparateProcess]
    public function test_refresh_exam_question_snapshots_after_question_updates_falls_back_when_snapshot_missing(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->useDeliveryFakeRedis();
        $this->useStartSnapshotFakeRedis();
        $this->setRuntimeRedisUnavailable();

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        $result = CBT_REST::refresh_exam_question_snapshots_after_question_updates(55, [201]);

        self::assertTrue($result['success']);
        self::assertSame('full_rebuild', $result['mode']);
        self::assertSame('delivery_v2_index_miss', $result['reason']);
        self::assertSame(2, $wpdb->questionHydrateCalls);
        self::assertSame(2, $wpdb->optionHydrateCalls);
    }

    #[RunInSeparateProcess]
    public function test_refresh_exam_question_snapshots_after_question_updates_falls_back_when_lock_busy(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->useDeliveryFakeRedis();
        $this->useStartSnapshotFakeRedis();
        $this->setRuntimeRedisUnavailable();

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        self::assertTrue(CBT_Cache::acquire_lock('partial_question_snapshot:exam:55', 15, [
            'type' => 'test_lock_busy',
        ]));
        try {
            $result = CBT_REST::refresh_exam_question_snapshots_after_question_updates(55, [201]);
        } finally {
            CBT_Cache::release_lock('partial_question_snapshot:exam:55');
        }

        self::assertTrue($result['success']);
        self::assertSame('full_rebuild', $result['mode']);
        self::assertSame('lock_busy', $result['reason']);
        self::assertSame(2, $wpdb->questionHydrateCalls);
        self::assertSame(2, $wpdb->optionHydrateCalls);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_response_includes_etag_and_private_cache_headers(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->useDeliveryFakeRedis();
        $this->useAttemptContractFakeRedis();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        CBT_REST::warm_exam_question_delivery_snapshot(55);

        $response = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
            'offset' => 0,
            'limit' => 1,
        ], [], [], '/cbt/v1/questions', 'GET'));

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(200, $response->get_status());
        $headers = $response->get_headers();
        self::assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/', (string) ($headers['ETag'] ?? ''));
        self::assertSame('private, no-cache, must-revalidate', $headers['Cache-Control'] ?? null);
        self::assertSame('Authorization, Cookie', $headers['Vary'] ?? null);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_uses_raw_v2_response_when_delivery_blobs_and_contract_are_ready(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->useDeliveryFakeRedis();
        $this->useAttemptContractFakeRedis();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        CBT_REST::warm_exam_question_delivery_snapshot(55);

        $request = new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
            'offset' => 0,
            'limit' => 1,
        ], [], [], '/cbt/v1/questions', 'GET');

        $first = CBT_REST::get_questions($request);
        self::assertFalse(is_wp_error($first));
        self::assertArrayHasKey('cbt_attempt_contract:attempt:77', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        $second = CBT_REST::get_questions($request);

        self::assertInstanceOf(CBT_Raw_JSON_REST_Response::class, $second);
        self::assertSame('v2-raw', ($second->get_headers())['X-CBT-Questions-Storage'] ?? null);
        $decoded = json_decode($second->get_raw_json(), true);
        self::assertIsArray($decoded);
        self::assertSame('Ibu Kota Indonesia?', $decoded['items'][0]['question_text'] ?? null);
        self::assertSame(9002, $decoded['items'][0]['existing_answer'] ?? null);
        self::assertSame($decoded, $second->get_data());
        self::assertSame(1, $wpdb->questionHydrateCalls);
        self::assertSame(1, $wpdb->optionHydrateCalls);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_matching_if_none_match_returns_304_without_body(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->useDeliveryFakeRedis();
        $this->useAttemptContractFakeRedis();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        CBT_REST::warm_exam_question_delivery_snapshot(55);

        $first = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
            'offset' => 0,
            'limit' => 1,
        ], [], [], '/cbt/v1/questions', 'GET'));
        self::assertInstanceOf(WP_REST_Response::class, $first);
        $etag = (string) (($first->get_headers())['ETag'] ?? '');
        self::assertNotSame('', $etag);

        $second = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
            'offset' => 0,
            'limit' => 1,
        ], [], [
            'If-None-Match' => 'W/' . $etag . ', "unused"',
        ], '/cbt/v1/questions', 'GET'));

        self::assertInstanceOf(WP_REST_Response::class, $second);
        self::assertSame(304, $second->get_status());
        self::assertNull($second->get_data());
        self::assertSame($etag, ($second->get_headers())['ETag'] ?? null);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_etag_changes_when_existing_answers_change(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->useDeliveryFakeRedis();
        $this->useAttemptContractFakeRedis();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        CBT_REST::warm_exam_question_delivery_snapshot(55);

        $first = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
            'offset' => 0,
            'limit' => 1,
        ], [], [], '/cbt/v1/questions', 'GET'));
        self::assertInstanceOf(WP_REST_Response::class, $first);
        $firstEtag = (string) (($first->get_headers())['ETag'] ?? '');
        self::assertNotSame('', $firstEtag);

        $wpdb->selectedOptionIds = '[9001]';

        $second = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
            'offset' => 0,
            'limit' => 1,
        ], [], [], '/cbt/v1/questions', 'GET'));
        self::assertInstanceOf(WP_REST_Response::class, $second);
        $secondEtag = (string) (($second->get_headers())['ETag'] ?? '');

        self::assertNotSame('', $secondEtag);
        self::assertNotSame($firstEtag, $secondEtag);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_unauthorized_request_does_not_return_304(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->useDeliveryFakeRedis();
        $this->useAttemptContractFakeRedis();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();
        $wpdb->attemptStatus = 'completed';

        $response = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
            'offset' => 0,
            'limit' => 1,
        ], [], [
            'If-None-Match' => '*',
        ], '/cbt/v1/questions', 'GET'));

        self::assertTrue(is_wp_error($response));
        self::assertSame('attempt_closed', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_get_questions_bootstrap_light_student_attempt_sanitizes_question_payload(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->useAttemptContractFakeRedis();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        $response = CBT_REST::get_questions(new WP_REST_Request([
            'exam_id' => 55,
            'attempt_id' => 77,
            'offset' => 0,
            'limit' => 1,
            'bootstrap_light' => 1,
        ], [], [], '/cbt/v1/questions', 'GET'));

        self::assertFalse(is_wp_error($response));
        $payload = $response instanceof WP_REST_Response ? $response->get_data() : (array) $response;
        self::assertCount(1, (array) ($payload['items'] ?? []));
        self::assertArrayNotHasKey('correct_text', $payload['items'][0]);
        self::assertArrayNotHasKey('short_answer_correct_text', $payload['items'][0]);
        self::assertSame('Ibu Kota Indonesia?', $payload['items'][0]['question_text']);
    }

    #[RunInSeparateProcess]
    public function test_get_questions_bootstrap_light_returns_retryable_busy_when_contract_lock_is_active(): void
    {
        $this->bootstrapRestDeliverySnapshotScaffold();
        $this->registerStudentFixture();
        $this->useAttemptContractFakeRedis();
        $this->setRuntimeRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';

        global $wpdb;
        $wpdb = new RestQuestionDeliverySnapshotFakeWpdb();

        $lock_key = 'attempt_bootstrap:contract:77';
        self::assertTrue(CBT_Cache::acquire_lock($lock_key, 10, [
            'type' => 'test_question_contract_bootstrap',
        ]));

        try {
            $response = CBT_REST::get_questions(new WP_REST_Request([
                'exam_id' => 55,
                'attempt_id' => 77,
                'offset' => 0,
                'limit' => 1,
                'bootstrap_light' => 1,
            ], [], [], '/cbt/v1/questions', 'GET'));
        } finally {
            CBT_Cache::release_lock($lock_key);
        }

        self::assertTrue(is_wp_error($response));
        self::assertSame('question_bootstrap_busy', $response->get_error_code());
        $error_data = $response->get_error_data();
        self::assertIsArray($error_data);
        self::assertSame(429, (int) ($error_data['status'] ?? 0));
        self::assertSame(1000, (int) ($error_data['retry_after_ms'] ?? 0));
        self::assertSame(0, $wpdb->questionHydrateCalls);
        self::assertSame(0, $wpdb->optionHydrateCalls);
    }

    private function bootstrapRestDeliverySnapshotScaffold(): void
    {
        if (!class_exists('CBT_Auth')) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static function current_user_id(\WP_REST_Request $request): int
    {
        return (int) ($GLOBALS['cbt_test_rest_auth_user_id'] ?? 0);
    }

    public static function current_user_role(\WP_REST_Request $request): string
    {
        return (string) ($GLOBALS['cbt_test_rest_auth_role'] ?? 'student');
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-question-contract-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
    }

    private function registerStudentFixture(): void
    {
        cbt_test_register_user([
            'ID' => 7,
            'display_name' => 'Siswa Satu',
            'roles' => ['student'],
            'user_email' => 'student@example.com',
            'user_login' => 'student01',
            'user_pass' => 'secret',
        ]);

        update_user_meta(7, 'kode_kelas', 'XI-A');
    }

    private function useDeliveryFakeRedis(): void
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

    private function useStartSnapshotFakeRedis(): void
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

    private function setRuntimeRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }

    private function useAttemptContractFakeRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Attempt_Question_Contract_Cache::class);

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

final class RestQuestionDeliverySnapshotFakeWpdb
{
    public string $prefix = 'wp_';

    public int $examGetRowCalls = 0;
    public int $attemptGetRowCalls = 0;
    public int $questionHydrateCalls = 0;
    public int $optionHydrateCalls = 0;
    public int $answerQueryCalls = 0;
    public string $attemptStatus = 'in_progress';
    public string $selectedOptionIds = '[9002]';
    public string $questionText = 'Ibu Kota Indonesia?';
    public string $questionUpdatedAt = '2026-04-03 05:01:00';
    public string $optionAText = 'Bandung';
    public string $optionBText = 'Jakarta';
    public bool $includeSecondQuestion = false;
    public string $secondQuestionText = 'Warna bendera Indonesia?';
    public string $secondQuestionUpdatedAt = '2026-04-03 05:02:00';
    public string $secondOptionAText = 'Merah Putih';
    public string $secondOptionBText = 'Biru';

    /** @return array<string,mixed> */
    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_row($prepared, $output = null): ?array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'FROM wp_cbt_exams') !== false) {
            $this->examGetRowCalls++;

            return [
                'id' => 55,
                'duration_minutes' => 60,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'status' => 'published',
                'starts_at' => '2026-04-03 05:00:00',
                'ends_at' => '2026-04-03 08:00:00',
                'target_kelas' => '',
            ];
        }

        if (strpos($query, 'FROM wp_cbt_attempts') !== false) {
            $this->attemptGetRowCalls++;
            $attemptId = isset($args[0]) ? (int) $args[0] : 0;
            if ($attemptId !== 77) {
                return null;
            }

            return [
                'id' => 77,
                'exam_id' => 55,
                'student_id' => 7,
                'status' => $this->attemptStatus,
                'question_order' => '[201]',
                'option_order' => '',
                'score' => 0,
                'max_score' => 0,
                'started_at' => '2026-04-03 05:30:00',
                'extra_time_minutes' => 0,
            ];
        }

        return null;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'FROM wp_cbt_questions q') !== false) {
            $this->questionHydrateCalls++;

            $rows = [
                [
                    'id' => 201,
                    'exam_id' => 55,
                    'question_text' => $this->questionText,
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'correct_text' => 'Jakarta',
                    'created_at' => '2026-04-03 05:00:00',
                    'updated_at' => $this->questionUpdatedAt,
                    'is_active' => 1,
                    'short_answer_correct_text' => null,
                ],
            ];
            if ($this->includeSecondQuestion) {
                $rows[] = [
                    'id' => 202,
                    'exam_id' => 55,
                    'question_text' => $this->secondQuestionText,
                    'question_type' => 'multiple_choice',
                    'points' => 4,
                    'correct_text' => 'Merah Putih',
                    'created_at' => '2026-04-03 05:00:30',
                    'updated_at' => $this->secondQuestionUpdatedAt,
                    'is_active' => 1,
                    'short_answer_correct_text' => null,
                ];
            }

            if (strpos($query, 'q.id IN') !== false) {
                $requestedQuestionIds = array_values(array_filter(array_map('intval', array_slice($args, 1)), static function (int $questionId): bool {
                    return $questionId > 0;
                }));
                if (!empty($requestedQuestionIds)) {
                    $requestedLookup = array_fill_keys($requestedQuestionIds, true);
                    $rows = array_values(array_filter($rows, static function (array $row) use ($requestedLookup): bool {
                        return isset($requestedLookup[(int) ($row['id'] ?? 0)]);
                    }));
                }
            }

            return $rows;
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false) {
            $this->optionHydrateCalls++;

            $rows = [
                ['id' => 9001, 'question_id' => 201, 'option_key' => 'A', 'option_text' => $this->optionAText],
                ['id' => 9002, 'question_id' => 201, 'option_key' => 'B', 'option_text' => $this->optionBText],
            ];
            if ($this->includeSecondQuestion) {
                $rows[] = ['id' => 9011, 'question_id' => 202, 'option_key' => 'A', 'option_text' => $this->secondOptionAText];
                $rows[] = ['id' => 9012, 'question_id' => 202, 'option_key' => 'B', 'option_text' => $this->secondOptionBText];
            }

            if (preg_match('/question_id IN \(([^)]+)\)/', $query, $matches) === 1) {
                $requestedQuestionIds = array_values(array_filter(array_map('intval', explode(',', (string) $matches[1])), static function (int $questionId): bool {
                    return $questionId > 0;
                }));
                if (!empty($requestedQuestionIds)) {
                    $requestedLookup = array_fill_keys($requestedQuestionIds, true);
                    $rows = array_values(array_filter($rows, static function (array $row) use ($requestedLookup): bool {
                        return isset($requestedLookup[(int) ($row['question_id'] ?? 0)]);
                    }));
                }
            }

            return $rows;
        }

        if (strpos($query, 'FROM wp_cbt_answers') !== false) {
            $this->answerQueryCalls++;

            return [
                [
                    'question_id' => 201,
                    'selected_option_ids' => $this->selectedOptionIds,
                    'answer_text' => '',
                    'answered_at' => '2026-04-03 05:35:00',
                    'updated_at' => '2026-04-03 05:35:00',
                ],
            ];
        }

        return [];
    }

    public function update($table, $data, $where, $format = null, $whereFormat = null): int
    {
        return 1;
    }
}
