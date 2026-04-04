<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

final class AdminExamsSnapshotContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useFakeDeliveryRedis();
        $GLOBALS['wpdb'] = new AdminExamsSnapshotContextFakeWpdb();
    }

    public function test_build_page_context_includes_snapshot_rows_for_admin_snapshot_tab(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Question_Delivery_Cache::get_exam_payload(77, static function (int $examId): array {
            return [
                [
                    'id' => 901,
                    'exam_id' => $examId,
                    'question_text' => 'Soal Redis Siap',
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'options' => [
                        ['id' => 1, 'option_key' => 'A', 'option_text' => 'A'],
                    ],
                ],
            ];
        });

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
        ]);

        self::assertTrue($context['can_manage_exam_snapshots']);
        self::assertSame('cbt-exam-snapshot-panel', $context['active_exam_page_panel']);
        self::assertSame(0, $context['exam_snapshot_filter_state']['exam_id']);
        self::assertCount(2, $context['exam_snapshot_exam_options']);
        self::assertCount(2, $context['exam_snapshot_rows']);
        self::assertSame(2, $context['exam_snapshot_total']);
        self::assertSame('Ujian Matematika', $context['exam_snapshot_rows'][0]['title']);
        self::assertSame('ready', $context['exam_snapshot_rows'][0]['snapshot_status']);
        self::assertSame([901], $context['exam_snapshot_rows'][0]['preview_question_ids']);
        self::assertSame('Ujian Biologi', $context['exam_snapshot_rows'][1]['title']);
        self::assertSame('miss', $context['exam_snapshot_rows'][1]['snapshot_status']);
    }

    public function test_build_page_context_honors_snapshot_preview_page_request_per_exam(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Question_Delivery_Cache::get_exam_payload(77, static function (int $examId): array {
            $items = [];
            for ($index = 0; $index < 9; $index++) {
                $items[] = [
                    'id' => 901 + $index,
                    'exam_id' => $examId,
                    'question_text' => 'Soal Redis #' . ($index + 1),
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'options' => [
                        ['id' => 1 + $index, 'option_key' => 'A', 'option_text' => 'A'],
                    ],
                ];
            }

            return $items;
        });

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_page_77' => '2',
        ]);

        self::assertSame([77 => 2], $context['exam_snapshot_preview_pages']);
        self::assertSame(2, $context['exam_snapshot_rows'][0]['preview_current_page']);
        self::assertSame(2, $context['exam_snapshot_rows'][0]['preview_total_pages']);
        self::assertSame(7, $context['exam_snapshot_rows'][0]['preview_per_page']);
        self::assertSame([908, 909], $context['exam_snapshot_rows'][0]['preview_question_ids']);
        self::assertTrue($context['exam_snapshot_rows'][0]['preview_is_expanded']);
    }

    public function test_build_page_context_filters_snapshot_rows_by_selected_exam_dropdown(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_exam_id' => '54',
        ]);

        self::assertSame(54, $context['exam_snapshot_filter_state']['exam_id']);
        self::assertCount(2, $context['exam_snapshot_exam_options']);
        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame(1, $context['exam_snapshot_total']);
        self::assertSame('Ujian Biologi', $context['exam_snapshot_rows'][0]['title']);
    }

    public function test_build_page_context_falls_back_to_list_panel_for_non_admin_snapshot_request(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = false;
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_exams'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
        ]);

        self::assertFalse($context['can_manage_exam_snapshots']);
        self::assertSame('cbt-exam-list-panel', $context['active_exam_page_panel']);
        self::assertSame([], $context['exam_snapshot_rows']);
    }

    private function useFakeDeliveryRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Exam_Question_Delivery_Cache::class);

        $redisProperty = $reflection->getProperty('delivery_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('delivery_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('delivery_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}

final class AdminExamsSnapshotContextFakeWpdb
{
    public string $prefix = 'wp_';
    public string $usermeta = 'wp_usermeta';

    public function prepare(string $query, ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_int($arg) || is_float($arg)
                ? (string) $arg
                : "'" . str_replace("'", "\\'", (string) $arg) . "'";
            $query = preg_replace('/%[dfs]/', $replacement, $query, 1) ?? $query;
        }

        return $query;
    }

    public function esc_like(string $text): string
    {
        return $text;
    }

    /**
     * @param string $prepared
     * @return array<int,mixed>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'FROM wp_cbt_subjects') !== false) {
            return [
                ['id' => 3, 'name' => 'Matematika', 'code' => 'MAT'],
                ['id' => 4, 'name' => 'Biologi', 'code' => 'BIO'],
            ];
        }

        if (strpos($query, 'GROUP BY e.status') !== false) {
            return [
                ['status' => 'published', 'total' => 2],
            ];
        }

        if (strpos($query, 'COALESCE(qc.question_count, 0) AS question_count') !== false) {
            return [
                [
                    'id' => 77,
                    'title' => 'Ujian Matematika',
                    'subject_name' => 'Matematika',
                    'status' => 'published',
                    'question_count' => 12,
                    'attempt_total' => 0,
                    'attempt_in_progress' => 0,
                    'attempt_completed' => 0,
                    'target_kelas' => 'XI-A',
                ],
                [
                    'id' => 54,
                    'title' => 'Ujian Biologi',
                    'subject_name' => 'Biologi',
                    'status' => 'published',
                    'question_count' => 8,
                    'attempt_total' => 0,
                    'attempt_in_progress' => 0,
                    'attempt_completed' => 0,
                    'target_kelas' => 'XI-B',
                ],
            ];
        }

        if (strpos($query, 'SELECT e.id, e.title, e.status, s.name AS subject_name') !== false) {
            if (strpos($query, 'e.id = 54') !== false) {
                return [
                    ['id' => 54, 'title' => 'Ujian Biologi', 'status' => 'published', 'subject_name' => 'Biologi'],
                ];
            }

            return [
                ['id' => 77, 'title' => 'Ujian Matematika', 'status' => 'published', 'subject_name' => 'Matematika'],
                ['id' => 54, 'title' => 'Ujian Biologi', 'status' => 'published', 'subject_name' => 'Biologi'],
            ];
        }

        if (strpos($query, 'SELECT q.exam_id AS target_exam_id') !== false) {
            return [];
        }

        return [];
    }

    /**
     * @param string $prepared
     */
    public function get_var($prepared)
    {
        $query = (string) $prepared;

        if (strpos($query, 'COUNT(*) FROM wp_cbt_exams e') !== false) {
            return 2;
        }

        return 0;
    }

    /**
     * @param string $prepared
     * @return array<int,string>
     */
    public function get_col($prepared): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT e.target_kelas FROM wp_cbt_exams e') !== false) {
            return ['XI-A', 'XI-B'];
        }

        return [];
    }
}
