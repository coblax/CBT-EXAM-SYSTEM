<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-audience-service.php';

if (!class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';
    }
}

final class ExamAudienceServiceTest extends TestCase
{
    public function test_normalizes_target_values(): void
    {
        self::assertSame(['Islam', 'Katolik'], CBT_Exam_Audience_Service::normalize_target_values(['islam', 'Katholik', 'islam'], 'agama'));
        self::assertSame(['Laki-laki', 'Perempuan'], CBT_Exam_Audience_Service::normalize_target_values('L|P|male', 'gender'));
    }

    public function test_legacy_exam_without_advanced_filters_still_allows_matching_class(): void
    {
        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student(
            [
                'target_kelas' => 'XI TKJ 1',
                'restrict_to_subject_choice' => 0,
            ],
            71,
            [
                'kode_kelas' => 'XI TKJ 1',
                'agama' => '',
                'jenis_kelamin' => '',
            ]
        );

        self::assertTrue($result['allowed']);
        self::assertSame('ok', $result['reason']);
    }

    public function test_returns_specific_reason_for_agama_mismatch(): void
    {
        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student(
            [
                'target_kelas' => 'XI TKJ 1',
                'target_agama' => 'Islam',
            ],
            71,
            [
                'kode_kelas' => 'XI TKJ 1',
                'agama' => 'Kristen',
                'jenis_kelamin' => 'Perempuan',
            ]
        );

        self::assertFalse($result['allowed']);
        self::assertSame('agama_mismatch', $result['reason']);
    }

    public function test_returns_specific_reason_for_gender_mismatch(): void
    {
        $result = CBT_Exam_Audience_Service::evaluate_exam_for_student(
            [
                'target_kelas' => 'XI TKJ 1',
                'target_jenis_kelamin' => 'Laki-laki',
            ],
            71,
            [
                'kode_kelas' => 'XI TKJ 1',
                'agama' => 'Islam',
                'jenis_kelamin' => 'Perempuan',
            ]
        );

        self::assertFalse($result['allowed']);
        self::assertSame('gender_mismatch', $result['reason']);
    }

    public function test_diagnose_student_exams_reports_status_schedule_audience_and_attempt_reasons(): void
    {
        \cbt_test_register_user([
            'ID' => 71,
            'user_login' => 'siswa.diagnosa',
            'user_email' => 'siswa@example.test',
            'display_name' => 'Siswa Diagnosa',
            'roles' => ['siswa_cbt'],
        ]);
        \update_user_meta(71, 'kode_kelas', 'XI TKJ 1');
        \update_user_meta(71, 'agama', 'Islam');
        \update_user_meta(71, 'jenis_kelamin', 'Laki-laki');
        $previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new ExamAudienceDiagnosisFakeWpdb();
        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-04-29 08:00:00');

        try {
            $diagnosis = CBT_Exam_Audience_Service::diagnose_student_exams(71);
        } finally {
            if ($previousWpdb === null) {
                unset($GLOBALS['wpdb']);
            } else {
                $GLOBALS['wpdb'] = $previousWpdb;
            }
            unset($GLOBALS['cbt_test_current_time_timestamp']);
        }

        self::assertTrue($diagnosis['student']['is_student']);
        self::assertSame(7, $diagnosis['summary']['total']);
        self::assertSame(2, $diagnosis['summary']['can_start']);
        self::assertSame(1, $diagnosis['summary']['in_progress']);
        self::assertSame(1, $diagnosis['summary']['completed']);

        $byId = [];
        foreach ($diagnosis['items'] as $item) {
            $byId[(int) $item['exam_id']] = $item;
        }

        self::assertSame('ok', $byId[1]['primary_reason']);
        self::assertTrue($byId[1]['can_start_now']);
        self::assertSame('exam_not_published', $byId[2]['primary_reason']);
        self::assertSame('exam_not_started', $byId[3]['primary_reason']);
        self::assertSame('class_mismatch', $byId[4]['primary_reason']);
        self::assertSame('subject_choice_mismatch', $byId[5]['primary_reason']);
        self::assertSame('attempt_in_progress', $byId[6]['primary_reason']);
        self::assertTrue($byId[6]['can_start_now']);
        self::assertSame('attempt_completed', $byId[7]['primary_reason']);
        self::assertFalse($byId[7]['can_start_now']);
        self::assertArrayNotHasKey(8, $byId);
    }

    public function test_diagnose_student_exams_handles_non_student_user(): void
    {
        \cbt_test_register_user([
            'ID' => 88,
            'user_login' => 'guru.diagnosa',
            'user_email' => 'guru@example.test',
            'display_name' => 'Guru Diagnosa',
            'roles' => ['guru_cbt'],
        ]);

        $diagnosis = CBT_Exam_Audience_Service::diagnose_student_exams(88);

        self::assertFalse($diagnosis['student']['is_student']);
        self::assertSame([], $diagnosis['items']);
        self::assertStringContainsString('bukan role siswa', $diagnosis['message']);
    }
}

final class ExamAudienceDiagnosisFakeWpdb extends wpdb
{
    public function __construct()
    {
        $this->prefix = 'wp_';
    }

    public function prepare($query, ...$args)
    {
        return [
            'query' => (string) $query,
            'args' => $args,
        ];
    }

    public function get_results($query, $output = null): array
    {
        $sql = is_array($query) ? (string) ($query['query'] ?? '') : (string) $query;
        if (str_contains($sql, 'FROM wp_cbt_exams')) {
            return [
                $this->exam(1, 'Eligible', 'published', 'XI TKJ 1', 10, '2026-04-29 07:00:00', '2026-04-29 10:00:00'),
                $this->exam(2, 'Draft', 'draft', 'XI TKJ 1', 10, '2026-04-29 07:00:00', '2026-04-29 10:00:00'),
                $this->exam(3, 'Belum Mulai', 'published', 'XI TKJ 1', 10, '2026-04-29 09:00:00', '2026-04-29 12:00:00'),
                $this->exam(4, 'Kelas Lain', 'published', 'XII TKJ 1', 10, '2026-04-29 07:00:00', '2026-04-29 10:00:00'),
                $this->exam(5, 'Mapel Pilihan', 'published', 'XI TKJ 1', 50, '2026-04-29 07:00:00', '2026-04-29 10:00:00', 1),
                $this->exam(6, 'Sedang Berjalan', 'published', 'XI TKJ 1', 10, '2026-04-29 07:00:00', '2026-04-29 10:00:00'),
                $this->exam(7, 'Selesai', 'published', 'XI TKJ 1', 10, '2026-04-29 07:00:00', '2026-04-29 10:00:00'),
                $this->exam(8, 'Bank Soal - Produktif', 'draft', 'XI TKJ 1', 10, '2026-04-29 07:00:00', '2026-04-29 10:00:00'),
            ];
        }
        if (str_contains($sql, 'FROM wp_cbt_attempts')) {
            return [
                [
                    'id' => 901,
                    'exam_id' => 6,
                    'status' => 'in_progress',
                    'started_at' => '2026-04-29 07:30:00',
                    'finished_at' => null,
                ],
                [
                    'id' => 900,
                    'exam_id' => 6,
                    'status' => 'completed',
                    'started_at' => '2026-04-29 07:00:00',
                    'finished_at' => '2026-04-29 07:20:00',
                ],
                [
                    'id' => 902,
                    'exam_id' => 7,
                    'status' => 'completed',
                    'started_at' => '2026-04-29 07:00:00',
                    'finished_at' => '2026-04-29 07:20:00',
                ],
            ];
        }

        return [];
    }

    public function get_var($query)
    {
        return 0;
    }

    /**
     * @return array<string,mixed>
     */
    private function exam(int $id, string $title, string $status, string $kelas, int $subjectId, string $startsAt, string $endsAt, int $restrict = 0): array
    {
        return [
            'id' => $id,
            'subject_id' => $subjectId,
            'title' => $title,
            'status' => $status,
            'target_kelas' => $kelas,
            'target_agama' => '',
            'target_jenis_kelamin' => '',
            'restrict_to_subject_choice' => $restrict,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => 120,
            'subject_name' => 'Produktif',
            'subject_code' => 'TKJ',
        ];
    }
}
