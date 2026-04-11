<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestQuestionDeliverySnapshotTest extends TestCase
{
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
                'status' => 'in_progress',
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

        if (strpos($query, 'FROM wp_cbt_questions q') !== false) {
            $this->questionHydrateCalls++;

            return [
                [
                    'id' => 201,
                    'exam_id' => 55,
                    'question_text' => 'Ibu Kota Indonesia?',
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'correct_text' => 'Jakarta',
                    'created_at' => '2026-04-03 05:00:00',
                    'updated_at' => '2026-04-03 05:01:00',
                    'is_active' => 1,
                    'short_answer_correct_text' => null,
                ],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false) {
            $this->optionHydrateCalls++;

            return [
                ['id' => 9001, 'question_id' => 201, 'option_key' => 'A', 'option_text' => 'Bandung'],
                ['id' => 9002, 'question_id' => 201, 'option_key' => 'B', 'option_text' => 'Jakarta'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_answers') !== false) {
            $this->answerQueryCalls++;

            return [
                [
                    'question_id' => 201,
                    'selected_option_ids' => '[9002]',
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
