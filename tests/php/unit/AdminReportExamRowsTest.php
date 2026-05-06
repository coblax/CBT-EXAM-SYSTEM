<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminReportExamRowsTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_exam_report_rows_use_completed_score_fallback_answer_score_and_absent_students(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-report-exam-service.php';

        cbt_test_register_user(['ID' => 101, 'user_login' => 's101', 'display_name' => 'Andi', 'roles' => ['siswa_cbt']]);
        cbt_test_register_user(['ID' => 102, 'user_login' => 's102', 'display_name' => 'Budi', 'roles' => ['siswa_cbt']]);
        cbt_test_register_user(['ID' => 103, 'user_login' => 's103', 'display_name' => 'Cici', 'roles' => ['siswa_cbt']]);
        $GLOBALS['cbt_test_wp_user_meta'][101] = ['kode_kelas' => ['XI-A'], 'nisn' => ['NISN101']];
        $GLOBALS['cbt_test_wp_user_meta'][102] = ['kode_kelas' => ['XI-A'], 'nisn' => ['NISN102']];
        $GLOBALS['cbt_test_wp_user_meta'][103] = ['kode_kelas' => ['XI-A'], 'nisn' => ['NISN103']];

        global $wpdb;
        $wpdb = new AdminReportExamRowsFakeWpdb();

        $rows = CBT_Admin_Report_Exam_Service::get_exam_report_rows(44, 'XI-A', true, 1);

        self::assertCount(3, $rows);
        self::assertSame('Andi', $rows[0]['nama']);
        self::assertSame('60.00', $rows[0]['nilai_display']);
        self::assertTrue($rows[0]['is_present']);

        self::assertSame('Budi', $rows[1]['nama']);
        self::assertSame('50.00', $rows[1]['nilai_display']);
        self::assertTrue($rows[1]['is_present']);

        self::assertSame('Cici', $rows[2]['nama']);
        self::assertSame('Belum ujian', $rows[2]['nilai_display']);
        self::assertFalse($rows[2]['is_present']);
    }

    #[RunInSeparateProcess]
    public function test_exam_report_rows_append_present_student_outside_registered_target_list(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-report-exam-service.php';

        cbt_test_register_user(['ID' => 101, 'user_login' => 's101', 'display_name' => 'Andi', 'roles' => ['siswa_cbt']]);
        $GLOBALS['cbt_test_wp_user_meta'][101] = ['kode_kelas' => ['XI-A'], 'nisn' => ['NISN101']];

        global $wpdb;
        $wpdb = new AdminReportExamRowsFakeWpdb(true);

        $rows = CBT_Admin_Report_Exam_Service::get_exam_report_rows(44, 'XI-A', true, 1);

        self::assertCount(2, $rows);
        self::assertSame('Andi', $rows[0]['nama']);
        self::assertSame('Dedi Extra', $rows[1]['nama']);
        self::assertSame('75.00', $rows[1]['nilai_display']);
        self::assertTrue($rows[1]['is_present']);
    }
}

final class AdminReportExamRowsFakeWpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';

    private bool $includeExtraAttempt;

    public function __construct(bool $includeExtraAttempt = false)
    {
        $this->includeExtraAttempt = $includeExtraAttempt;
    }

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

        if (strpos($query, 'FROM wp_cbt_exams e') !== false) {
            $examId = isset($args[0]) ? (int) $args[0] : 0;
            if ($examId === 44) {
                return [
                    'id' => 44,
                    'title' => 'Report Fixture',
                    'starts_at' => '2026-04-20 08:00:00',
                    'target_kelas' => 'XI-A',
                    'subject_name' => 'QA',
                ];
            }
        }

        return null;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;

        if (strpos($query, 'FROM wp_cbt_attempts a') === false) {
            return [];
        }

        $rows = [
            [
                'id' => 501,
                'student_id' => 101,
                'status' => 'completed',
                'score' => 60,
                'max_score' => 100,
                'student_name' => 'Andi',
                'student_kelas' => 'XI-A',
                'student_nisn' => 'NISN101',
                'answer_score_awarded' => 10,
                'exam_total_points' => 100,
            ],
        ];

        if (!$this->includeExtraAttempt) {
            $rows[] = [
                'id' => 502,
                'student_id' => 102,
                'status' => 'in_progress',
                'score' => null,
                'max_score' => 0,
                'student_name' => 'Budi',
                'student_kelas' => 'XI-A',
                'student_nisn' => 'NISN102',
                'answer_score_awarded' => 30,
                'exam_total_points' => 60,
            ];
        }

        if ($this->includeExtraAttempt) {
            $rows[] = [
                'id' => 503,
                'student_id' => 104,
                'status' => 'completed',
                'score' => 45,
                'max_score' => 60,
                'student_name' => 'Dedi Extra',
                'student_kelas' => 'XI-A',
                'student_nisn' => 'NISN104',
                'answer_score_awarded' => 45,
                'exam_total_points' => 60,
            ];
        }

        return $rows;
    }
}
