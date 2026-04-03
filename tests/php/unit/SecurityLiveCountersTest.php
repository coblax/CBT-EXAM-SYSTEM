<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class SecurityLiveCountersTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_promote_event_rewrites_live_summary_without_changing_event_total(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-live-counters.php';
        $this->useFakeLiveRedis();

        CBT_Security_Live_Counters::record_event(
            [
                'id' => 114,
                'exam_id' => 501,
                'student_id' => 71,
                'status' => 'in_progress',
            ],
            'page_leave',
            5.0,
            'Meninggalkan halaman',
            '2026-03-24 12:00:00',
            [
                'teacher_id' => 7,
                'student_name' => 'Coblax Student',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
                'last_device_type' => 'desktop',
                'last_device_label' => 'Desktop',
                'last_device_summary' => 'Desktop • Windows',
            ]
        );

        CBT_Security_Live_Counters::promote_event(
            114,
            'page_leave',
            5.0,
            'page_refresh',
            0.5,
            'Refresh halaman'
        );

        $payloads = CBT_Security_Live_Counters::get_active_attempt_payloads();

        self::assertCount(1, $payloads);
        self::assertSame(0.5, $payloads[0]['risk_score']);
        self::assertSame(1, $payloads[0]['event_total']);
        self::assertArrayNotHasKey('page_leave', $payloads[0]['event_counts']);
        self::assertArrayHasKey('page_refresh', $payloads[0]['event_counts']);
        self::assertSame(1, $payloads[0]['event_counts']['page_refresh']['count']);
    }

    #[RunInSeparateProcess]
    public function test_clear_attempt_runtime_clears_live_security_counter_state(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-live-counters.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
        $this->useFakeLiveRedis();
        $this->useFakeRuntimeRedis();

        CBT_Security_Live_Counters::record_event(
            [
                'id' => 114,
                'exam_id' => 501,
                'student_id' => 71,
                'status' => 'in_progress',
            ],
            'window_blur',
            2.0,
            'Fokus window berpindah',
            '2026-03-24 12:00:00',
            [
                'teacher_id' => 7,
                'student_name' => 'Coblax Student',
                'student_login' => 'coblax',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
                'last_device_type' => 'desktop',
                'last_device_label' => 'Desktop',
                'last_device_summary' => 'Desktop • Windows',
            ]
        );

        self::assertCount(1, CBT_Security_Live_Counters::get_active_attempt_payloads());

        CBT_Runtime::clear_attempt_runtime(114);

        self::assertSame([], CBT_Security_Live_Counters::get_active_attempt_payloads());
    }

    #[RunInSeparateProcess]
    public function test_clear_all_clears_every_active_live_counter_attempt(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-live-counters.php';
        $this->useFakeLiveRedis();

        CBT_Security_Live_Counters::record_event(
            [
                'id' => 114,
                'exam_id' => 501,
                'student_id' => 71,
                'status' => 'in_progress',
            ],
            'window_blur',
            2.0,
            'Fokus window berpindah',
            '2026-03-24 12:00:00',
            [
                'teacher_id' => 7,
                'student_name' => 'Student One',
                'student_login' => 'student_one',
                'student_kode_kelas' => 'X-A',
                'student_kode_ruang' => 'R1',
                'exam_title' => 'Security Fixture',
            ]
        );

        CBT_Security_Live_Counters::record_event(
            [
                'id' => 115,
                'exam_id' => 501,
                'student_id' => 72,
                'status' => 'in_progress',
            ],
            'clipboard_blocked',
            8.0,
            'Clipboard diblokir',
            '2026-03-24 12:01:00',
            [
                'teacher_id' => 7,
                'student_name' => 'Student Two',
                'student_login' => 'student_two',
                'student_kode_kelas' => 'X-B',
                'student_kode_ruang' => 'R2',
                'exam_title' => 'Security Fixture',
            ]
        );

        self::assertCount(2, CBT_Security_Live_Counters::get_active_attempt_payloads());

        CBT_Security_Live_Counters::clear_all();

        self::assertSame([], CBT_Security_Live_Counters::get_active_attempt_payloads());
    }

    private function useFakeLiveRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Security_Live_Counters::class);

        $redisProperty = $reflection->getProperty('live_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('live_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('live_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeRuntimeRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}
