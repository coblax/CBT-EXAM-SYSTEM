<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestExamAvailabilitySnapshotTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_get_exams_student_uses_redis_snapshot_and_keeps_token_overlay_live_on_snapshot_hits(): void
    {
        $this->bootstrapRestSnapshotScaffold();
        $this->registerStudentFixture();
        $this->useExamAvailabilityFakeRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = [
            'token' => '',
            'refresh_minutes' => 15,
            'generated_at' => 1774353600,
            'next_refresh_at' => 1774354500,
            'frontend_auto_apply' => 0,
        ];

        global $wpdb;
        $wpdb = new RestExamAvailabilitySnapshotFakeWpdb();

        $first = CBT_REST::get_exams(new WP_REST_Request([], [], [], '/cbt/v1/exams', 'GET'));

        self::assertFalse(is_wp_error($first));
        self::assertSame(1, $wpdb->examQueryCount);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $first['items'][0]['requires_token']);
        self::assertSame(88, $first['items'][0]['latest_attempt_id']);
        self::assertSame('in_progress', $first['items'][0]['latest_attempt_status']);

        $GLOBALS['cbt_test_global_exam_token_meta'] = [
            'token' => 'ABCD1',
            'refresh_minutes' => 30,
            'generated_at' => 1774353600,
            'next_refresh_at' => 1774355400,
            'frontend_auto_apply' => 1,
        ];

        $second = CBT_REST::get_exams(new WP_REST_Request([], [], [], '/cbt/v1/exams', 'GET'));

        self::assertFalse(is_wp_error($second));
        self::assertSame(1, $wpdb->examQueryCount);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(1, $second['items'][0]['requires_token']);
        self::assertSame(1, $second['items'][0]['token_frontend_auto_apply']);
        self::assertSame(0, $second['items'][0]['token_input_required']);
        self::assertSame('ABCD1', $second['items'][0]['token_auto_value']);
        self::assertSame(30, $second['items'][0]['token_refresh_minutes']);
        self::assertSame(1774355400, $second['items'][0]['token_next_refresh_at']);
        self::assertNotSame([], $this->storedSnapshotKeys());
    }

    #[RunInSeparateProcess]
    public function test_get_exams_student_falls_back_to_existing_short_ttl_cache_when_snapshot_redis_is_unavailable(): void
    {
        $this->bootstrapRestSnapshotScaffold();
        $this->registerStudentFixture();
        $this->setExamAvailabilityRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = [
            'token' => '',
            'refresh_minutes' => 15,
            'generated_at' => 1774353600,
            'next_refresh_at' => 1774354500,
            'frontend_auto_apply' => 0,
        ];

        global $wpdb;
        $wpdb = new RestExamAvailabilitySnapshotFakeWpdb();

        $first = CBT_REST::get_exams(new WP_REST_Request([], [], [], '/cbt/v1/exams', 'GET'));
        $second = CBT_REST::get_exams(new WP_REST_Request([], [], [], '/cbt/v1/exams', 'GET'));

        self::assertFalse(is_wp_error($first));
        self::assertFalse(is_wp_error($second));
        self::assertSame(1, $wpdb->examQueryCount);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame([], $this->storedSnapshotKeys());
        self::assertSame(88, $second['items'][0]['latest_attempt_id']);
    }

    #[RunInSeparateProcess]
    public function test_get_exams_student_prefers_prepared_snapshot_before_minute_bucket_snapshot(): void
    {
        $this->bootstrapRestSnapshotScaffold();
        $this->registerStudentFixture();
        $this->useExamAvailabilityFakeRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = [
            'token' => 'AUTO1',
            'refresh_minutes' => 20,
            'generated_at' => 1774353600,
            'next_refresh_at' => 1774354800,
            'frontend_auto_apply' => 1,
        ];

        \CBT_Exam_Availability_Cache::warm_student_snapshot(7, static function (): array {
            return [
                'items' => [
                    [
                        'id' => 15,
                        'title' => 'Minute Snapshot',
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                        'latest_attempt_id' => 11,
                    ],
                ],
                'current_user' => [
                    'user_id' => 7,
                    'display_name' => 'Aulia',
                    'username' => 'aulia',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });
        \CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(7, static function (): array {
            return [
                'items' => [
                    [
                        'id' => 99,
                        'title' => 'Prepared Snapshot',
                        'availability_reason' => 'ok',
                        'is_available_now' => 1,
                        'latest_attempt_id' => 222,
                    ],
                ],
                'current_user' => [
                    'user_id' => 7,
                    'display_name' => 'Aulia',
                    'username' => 'aulia',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });

        global $wpdb;
        $wpdb = new RestExamAvailabilitySnapshotFakeWpdb();

        $response = CBT_REST::get_exams(new WP_REST_Request([], [], [], '/cbt/v1/exams', 'GET'));

        self::assertFalse(is_wp_error($response));
        self::assertSame(0, $wpdb->examQueryCount);
        self::assertSame(0, $wpdb->latestAttemptQueryCount);
        self::assertSame(99, $response['items'][0]['id']);
        self::assertSame('Prepared Snapshot', $response['items'][0]['title']);
        self::assertSame(1, $response['items'][0]['requires_token']);
        self::assertSame('AUTO1', $response['items'][0]['token_auto_value']);
        self::assertSame('prepared', \CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics(7)['snapshot_source']);
    }

    #[RunInSeparateProcess]
    public function test_get_exams_student_prefers_in_progress_attempt_even_when_completed_attempt_has_higher_id(): void
    {
        $this->bootstrapRestSnapshotScaffold();
        $this->registerStudentFixture();
        $this->setExamAvailabilityRedisUnavailable();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = [
            'token' => '',
            'refresh_minutes' => 15,
            'generated_at' => 1774353600,
            'next_refresh_at' => 1774354500,
            'frontend_auto_apply' => 0,
        ];

        $fakeWpdb = new RestExamAvailabilitySnapshotFakeWpdb();
        $fakeWpdb->attemptRows = [
            [
                'exam_id' => 15,
                'id' => 99,
                'status' => 'in_progress',
                'score' => 0,
                'max_score' => 100,
                'started_at' => '2026-03-24 11:05:00',
                'finished_at' => '',
            ],
            [
                'exam_id' => 15,
                'id' => 120,
                'status' => 'completed',
                'score' => 75,
                'max_score' => 100,
                'started_at' => '2026-03-24 09:00:00',
                'finished_at' => '2026-03-24 10:30:00',
            ],
        ];

        global $wpdb;
        $wpdb = $fakeWpdb;

        $response = CBT_REST::get_exams(new WP_REST_Request([], [], [], '/cbt/v1/exams', 'GET'));

        self::assertFalse(is_wp_error($response));
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(99, $response['items'][0]['latest_attempt_id']);
        self::assertSame('in_progress', $response['items'][0]['latest_attempt_status']);
        self::assertSame('', $response['items'][0]['latest_attempt_finished_at']);
    }

    private function bootstrapRestSnapshotScaffold(): void
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

    public static function get_global_exam_token(bool $auto_rotate = true): array
    {
        $meta = $GLOBALS['cbt_test_global_exam_token_meta'] ?? [];
        return is_array($meta) ? $meta : ['token' => ''];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-availability-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
    }

    private function registerStudentFixture(): void
    {
        cbt_test_register_user([
            'ID' => 7,
            'display_name' => 'Aulia',
            'roles' => ['student'],
            'user_email' => 'aulia@example.com',
            'user_login' => 'aulia',
            'user_pass' => 'secret',
        ]);

        update_user_meta(7, 'kode_kelas', 'XI-A');
        update_user_meta(7, 'kode_ruang', 'R1');
        update_user_meta(7, 'agama', 'Islam');
        update_user_meta(7, 'foto', 'https://example.com/aulia.jpg');
    }

    private function useExamAvailabilityFakeRedis(): void
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

    private function setExamAvailabilityRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Availability_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }

    /**
     * @return array<int,string>
     */
    private function storedSnapshotKeys(): array
    {
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key): bool {
            return is_string($key) && strpos($key, 'cbt_exam_availability:') === 0;
        }));
    }
}

final class RestExamAvailabilitySnapshotFakeWpdb
{
    public string $prefix = 'wp_';
    public int $examQueryCount = 0;
    public int $latestAttemptQueryCount = 0;
    /** @var array<int,array<string,mixed>> */
    public array $attemptRows = [
        [
            'exam_id' => 15,
            'id' => 88,
            'status' => 'in_progress',
            'score' => 0,
            'max_score' => 100,
            'started_at' => '2026-03-24 11:00:00',
            'finished_at' => '',
        ],
    ];

    /**
     * @return array<string,mixed>
     */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /**
     * @param array<string,mixed>|string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;

        if (strpos($query, 'FROM wp_cbt_exams e') !== false) {
            $this->examQueryCount++;

            return [[
                'id' => 15,
                'subject_id' => 1,
                'title' => 'Matematika',
                'duration_minutes' => 90,
                'kkm_percentage' => 75.0,
                'total_questions' => 10,
                'randomize_questions' => 0,
                'show_student_result' => 1,
                'enable_calculator' => 1,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'target_kelas' => 'XI-A',
                'created_by' => 1,
                'created_at' => '2026-03-20 10:00:00',
                'updated_at' => '2026-03-20 10:00:00',
                'subject_name' => 'Matematika',
                'subject_code' => 'MAT',
                'question_count' => 10,
            ]];
        }

        if (strpos($query, 'FROM wp_cbt_attempts a') !== false) {
            $this->latestAttemptQueryCount++;

            return $this->attemptRows;
        }

        return [];
    }
}
