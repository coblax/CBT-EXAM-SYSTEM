<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class LiveAttemptRosterIndexTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_sync_attempt_groups_live_rows_and_derived_counters(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-attempt-roster-index.php';
        $this->useFakeRosterRedis();

        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-04-03 05:58:00');

        CBT_Live_Attempt_Roster_Index::sync_attempt(
            [
                'id' => 7,
                'exam_id' => 22,
                'student_id' => 12,
                'status' => 'in_progress',
            ],
            [
                'teacher_id' => 91,
                'exam_title' => 'TEST Security Fixture',
                'student_name' => 'Siswa Test 0002',
                'student_login' => 'test_siswa_0002',
                'student_kode_kelas' => 'KELAS_TEST_02',
                'student_kode_ruang' => 'RUANG_TEST_01',
                'last_seen_at' => '2026-04-03 05:57:45',
                'connection_status' => 'degraded',
                'visibility_state' => 'hidden',
                'has_focus' => 0,
                'pending_sync_count' => 3,
                'heartbeat_lost_active' => 1,
                'risk_tone' => 'watch',
                'risk_score' => 8.5,
            ]
        );

        CBT_Live_Attempt_Roster_Index::sync_attempt(
            [
                'id' => 8,
                'exam_id' => 22,
                'student_id' => 13,
                'status' => 'in_progress',
            ],
            [
                'teacher_id' => 91,
                'exam_title' => 'TEST Security Fixture',
                'student_name' => 'COBLAX',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'KELAS_TEST_02',
                'student_kode_ruang' => 'RUANG_TEST_01',
                'last_seen_at' => '2026-04-03 05:56:20',
                'connection_status' => 'online',
                'visibility_state' => 'visible',
                'has_focus' => 1,
                'pending_sync_count' => 0,
                'heartbeat_lost_active' => 0,
            ]
        );

        $groups = CBT_Live_Attempt_Roster_Index::get_grouped_payloads([
            'teacher_id' => 91,
        ]);

        self::assertCount(1, $groups);
        self::assertSame('TEST Security Fixture', $groups[0]['exam_title']);
        self::assertSame('KELAS_TEST_02', $groups[0]['kelas_label']);
        self::assertSame('RUANG_TEST_01', $groups[0]['ruang_label']);
        self::assertSame(2, $groups[0]['active_total']);
        self::assertSame(1, $groups[0]['online_total']);
        self::assertSame(0, $groups[0]['stale_total']);
        self::assertSame(1, $groups[0]['offline_total']);
        self::assertSame(1, $groups[0]['watch_total']);
        self::assertSame(0, $groups[0]['high_risk_total']);
        self::assertSame(1, $groups[0]['hidden_total']);
        self::assertSame(1, $groups[0]['focus_off_total']);
        self::assertSame(1, $groups[0]['heartbeat_total']);
        self::assertCount(2, $groups[0]['attempts']);
        self::assertSame(7, $groups[0]['attempts'][0]['attempt_id']);
        self::assertSame('online', $groups[0]['attempts'][0]['presence_status']);
        self::assertSame('watch', $groups[0]['attempts'][0]['risk_tone']);
        self::assertSame(8.5, $groups[0]['attempts'][0]['risk_score']);
        self::assertSame('offline', $groups[0]['attempts'][1]['presence_status']);
    }

    #[RunInSeparateProcess]
    public function test_sync_risk_summary_and_clear_attempt_keep_group_membership_consistent(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-attempt-roster-index.php';
        $this->useFakeRosterRedis();

        CBT_Live_Attempt_Roster_Index::sync_attempt(
            [
                'id' => 9,
                'exam_id' => 55,
                'student_id' => 21,
                'status' => 'in_progress',
            ],
            [
                'teacher_id' => 77,
                'exam_title' => 'Exam Live Roster',
                'student_name' => 'Alpha',
                'student_login' => 'alpha',
                'student_kode_kelas' => 'KELAS_A',
                'student_kode_ruang' => 'RUANG_1',
                'last_seen_at' => '2026-04-03 05:58:00',
            ]
        );

        CBT_Live_Attempt_Roster_Index::sync_attempt(
            [
                'id' => 10,
                'exam_id' => 55,
                'student_id' => 22,
                'status' => 'in_progress',
            ],
            [
                'teacher_id' => 77,
                'exam_title' => 'Exam Live Roster',
                'student_name' => 'Beta',
                'student_login' => 'beta',
                'student_kode_kelas' => 'KELAS_A',
                'student_kode_ruang' => 'RUANG_1',
                'last_seen_at' => '2026-04-03 05:58:01',
                'risk_tone' => 'watch',
                'risk_score' => 6.0,
            ]
        );

        CBT_Live_Attempt_Roster_Index::sync_risk_summary(9, 'high-risk', 12.5);

        $groups = CBT_Live_Attempt_Roster_Index::get_grouped_payloads(['teacher_id' => 77]);
        self::assertSame(2, $groups[0]['watch_total']);
        self::assertSame(1, $groups[0]['high_risk_total']);

        CBT_Live_Attempt_Roster_Index::clear_attempt(10);

        $groups = CBT_Live_Attempt_Roster_Index::get_grouped_payloads(['teacher_id' => 77]);
        self::assertCount(1, $groups);
        self::assertSame(1, $groups[0]['active_total']);
        self::assertSame(1, $groups[0]['watch_total']);
        self::assertSame(1, $groups[0]['high_risk_total']);
        self::assertSame(9, $groups[0]['attempts'][0]['attempt_id']);

        CBT_Live_Attempt_Roster_Index::clear_attempt(9);

        self::assertSame([], CBT_Live_Attempt_Roster_Index::get_grouped_payloads(['teacher_id' => 77]));
    }

    private function useFakeRosterRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Live_Attempt_Roster_Index::class);

        $redisProperty = $reflection->getProperty('roster_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('roster_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('roster_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}
