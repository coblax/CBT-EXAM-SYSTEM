<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestLiveProfileSnapshotTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_build_exams_payload_uses_profile_snapshot_for_student_class_and_current_user_payload(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

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
        $this->useFakeRedisClient();

        CBT_Student_Profile_Cache::get_snapshot(7);

        update_user_meta(7, 'kode_kelas', 'XII-Z');
        update_user_meta(7, 'kode_ruang', 'R9');
        update_user_meta(7, 'agama', 'Kristen');
        update_user_meta(7, 'foto', 'https://example.com/aulia-updated.jpg');

        global $wpdb;
        $wpdb = new RestLiveProfileSnapshotFakeWpdb();

        $method = new ReflectionMethod('CBT_REST', 'build_exams_payload');
        $method->setAccessible(true);

        $payload = $method->invoke(null, 7, 'siswa');

        self::assertIsArray($payload);
        self::assertSame('XI-A', $payload['current_user']['kode_kelas']);
        self::assertSame('R1', $payload['current_user']['kode_ruang']);
        self::assertSame('Islam', $payload['current_user']['agama']);
        self::assertSame('https://example.com/aulia.jpg', $payload['current_user']['foto']);
        self::assertSame(1, $payload['items'][0]['is_class_allowed']);
        self::assertSame('ok', $payload['items'][0]['availability_reason']);
    }

    private function useFakeRedisClient(): void
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
}

final class RestLiveProfileSnapshotFakeWpdb
{
    public string $prefix = 'wp_';

    /** @return array<string,mixed> */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;

        if (strpos($query, 'FROM wp_cbt_exams e') !== false) {
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
            return [];
        }

        return [];
    }
}
