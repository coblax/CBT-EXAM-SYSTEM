<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use ReflectionClass;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-availability-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-start-attempt-snapshot-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-gate-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-session-snapshot-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-attempt-question-contract-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-question-submission-context-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-auth-snapshot-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-plugin-redis-reset-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

final class AdminExamsSnapshotContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_transient('cbt_exam_operational_stats_global');
        delete_transient('cbt_exam_operational_stats_user_0');
        delete_transient('cbt_exam_operational_stats_user_1');
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
        $this->useFakeAttemptSessionRedis();
        $this->useFakeAttemptContractRedis();
        $this->useFakeRuntimeRedis();
        cbt_test_register_user([
            'ID' => 71,
            'display_name' => 'Salsa',
            'user_login' => 'salsa',
            'user_email' => 'salsa@example.com',
            'user_pass' => 'secret',
            'roles' => ['student'],
        ]);
        update_user_meta(71, 'kode_kelas', 'XI-A');
        update_user_meta(71, 'kode_ruang', 'R1');
        update_user_meta(71, 'agama', 'Islam');
        update_user_meta(71, 'jenis_kelamin', 'Perempuan');
        update_user_meta(71, 'nisn', '71001');
        cbt_test_register_user([
            'ID' => 72,
            'display_name' => 'Bimo',
            'user_login' => 'bimo',
            'user_email' => 'bimo@example.com',
            'user_pass' => 'secret-2',
            'roles' => ['siswa_cbt'],
        ]);
        update_user_meta(72, 'kode_kelas', 'XI-B');
        update_user_meta(72, 'kode_ruang', 'R2');
        $GLOBALS['wpdb'] = new AdminExamsSnapshotContextFakeWpdb();
    }

    public function test_build_page_context_includes_operational_stats_cards_for_admin(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_redis_storage']['cbt_profile:user:71'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_login_auth:user:71'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_exam_availability:student:user:71:prepared'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_attempt_session:attempt:501'] = '{"ok":true}';
        $GLOBALS['cbt_test_redis_storage']['cbt_attempt_contract:attempt:501'] = '{"ok":true}';
        update_option('cbt_exam_preflight_jobs', [
            77 => [
                'exam_id' => 77,
                'status' => 'active',
                'exam_title' => 'Ujian Matematika',
            ],
        ]);
        update_option('cbt_exam_preflight_global_runner', [
            'active_exam_id' => 77,
            'queue_exam_ids' => [54],
        ]);
        update_option('cbt_exam_availability_auto_warm_state', [
            'active' => true,
            'status' => 'active',
            'exam_id' => 77,
            'exam_title' => 'Ujian Matematika',
        ]);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
        ]);

        self::assertNotEmpty($context['exam_operational_stats']);
        self::assertSame(20, $context['exam_operational_stats']['refreshed_every_seconds']);
        self::assertCount(6, $context['exam_operational_stats']['cards']);
        self::assertSame('Redis RAM', $context['exam_operational_stats']['cards'][0]['label']);
        self::assertSame('CBT Redis Keys', $context['exam_operational_stats']['cards'][1]['label']);
        self::assertSame('Active Attempts', $context['exam_operational_stats']['cards'][2]['label']);
        self::assertSame('Start Queue', $context['exam_operational_stats']['cards'][3]['label']);
        self::assertSame('Warm Jobs', $context['exam_operational_stats']['cards'][4]['label']);
        self::assertSame('User Snapshots', $context['exam_operational_stats']['cards'][5]['label']);
        self::assertSame('5', (string) $context['exam_operational_stats']['cards'][1]['value']);
        self::assertSame('1', (string) $context['exam_operational_stats']['cards'][2]['value']);
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
        \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [901],
                'question_number_map' => [901 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        \CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(71, static function (): array {
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
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });
        \CBT_Student_Profile_Cache::warm_snapshot(71);
        \CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'test');

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
        ]);

        self::assertTrue($context['can_manage_exam_snapshots']);
        self::assertSame('cbt-exam-snapshot-panel', $context['active_exam_page_panel']);
        self::assertSame(\CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT, $context['exam_snapshot_tab']);
        self::assertSame(0, $context['exam_snapshot_filter_state']['exam_id']);
        self::assertSame([], $context['exam_snapshot_filter_state']['exam_ids']);
        self::assertCount(2, $context['exam_snapshot_exam_options']);
        self::assertSame([], $context['exam_snapshot_rows']);
        self::assertSame(0, $context['exam_snapshot_total']);
        self::assertSame([], $context['student_snapshot_rows']);
        self::assertSame(0, $context['student_snapshot_total']);
        self::assertSame(1, $context['student_snapshot_current_page']);
        self::assertSame(1, $context['student_snapshot_total_pages']);
        self::assertSame(25, $context['student_snapshot_per_page']);
        self::assertSame([], $context['student_snapshot_kelas_options']);
        self::assertSame([], $context['student_snapshot_ruang_options']);
    }

    public function test_build_page_context_marks_prepared_snapshot_as_auto_warm_only_when_session_is_still_active_for_student(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(71, static function (): array {
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
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });

        update_option('cbt_exam_availability_auto_warm_state', [
            'active' => true,
            'status' => 'active',
            'session_id' => 'warm-77-abc',
            'exam_id' => 77,
            'exam_title' => 'Ujian Matematika',
            'target_student_ids' => [71],
            'target_student_count' => 1,
            'prepared_student_ids' => [71],
            'prepared_count' => 1,
            'cursor' => 0,
            'started_at' => '2026-04-04 12:00:00',
            'stop_after_ts' => 1775305800,
            'stop_after_at' => '2026-04-04 12:30:00',
            'last_tick_at' => '2026-04-04 12:01:00',
            'last_success_count' => 1,
            'last_failure_count' => 0,
            'last_skip_count' => 0,
            'last_message' => 'Batch awal auto-warm memproses 1 siswa.',
        ]);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'students',
        ]);

        self::assertSame('AUTO-WARM', $context['student_snapshot_rows'][1]['availability_status_label']);
    }

    public function test_build_page_context_exam_monitor_enqueues_version_changed_availability_once(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(71, static function (): array {
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
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });
        \CBT_Cache::invalidate_user(71);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
        ]);
        $contextSecond = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
        ]);

        $salsaRow = null;
        foreach ((array) $context['student_snapshot_rows'] as $studentRow) {
            if ((int) ($studentRow['user_id'] ?? 0) === 71) {
                $salsaRow = $studentRow;
                break;
            }
        }

        self::assertIsArray($salsaRow);
        self::assertSame('QUEUED REWARM', $salsaRow['availability_status_label']);
        self::assertSame('queued_rewarm', $salsaRow['availability']['repair_status']);
        self::assertSame(1, $context['availability_rewarm_queue']['queued_count']);
        self::assertSame(1, $contextSecond['availability_rewarm_queue']['queued_count']);
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
        \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [901, 902, 903, 904, 905, 906, 907, 908, 909],
                'question_number_map' => [901 => 1, 902 => 2, 903 => 3, 904 => 4, 905 => 5, 906 => 6, 907 => 7, 908 => 8, 909 => 9],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_snapshot_page_77' => '2',
        ]);

        self::assertSame(77, $context['exam_snapshot_filter_state']['exam_id']);
        self::assertSame([77], $context['exam_snapshot_filter_state']['exam_ids']);
        self::assertSame([77 => 2], $context['exam_snapshot_preview_pages']);
        self::assertSame(2, $context['exam_snapshot_rows'][0]['preview_current_page']);
        self::assertSame(2, $context['exam_snapshot_rows'][0]['preview_total_pages']);
        self::assertSame(7, $context['exam_snapshot_rows'][0]['preview_per_page']);
        self::assertSame([908, 909], $context['exam_snapshot_rows'][0]['preview_question_ids']);
        self::assertTrue($context['exam_snapshot_rows'][0]['preview_is_expanded']);
    }

    public function test_build_page_context_includes_exam_readiness_for_selected_exam(): void
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
        \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [901],
                'question_number_map' => [901 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        \CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(71, static function (): array {
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
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });
        \CBT_Student_Profile_Cache::warm_snapshot(71);

        for ($index = 0; $index < 11; $index++) {
            $user_id = 200 + $index;
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
        }

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_exam_readiness_paged' => '2',
        ]);

        self::assertSame(2, $context['exam_readiness_page']);
        self::assertSame([77 => 2], $context['exam_readiness_pages']);
        self::assertCount(1, $context['exam_snapshot_rows']);
        $readiness = $context['exam_snapshot_rows'][0]['readiness'];
        self::assertSame('PERLU PERHATIAN', $readiness['overall_label']);
        self::assertTrue($readiness['question_snapshot_ready']);
        self::assertTrue($readiness['start_snapshot_ready']);
        self::assertSame(12, $readiness['target_student_count']);
        self::assertSame(1, $readiness['profile_ready_count']);
        self::assertSame(11, $readiness['profile_missing_count']);
        self::assertSame(1, $readiness['availability_ready_count']);
        self::assertSame(0, $readiness['availability_auto_warm_count']);
        self::assertSame(11, $readiness['availability_missing_count']);
        self::assertSame(11, $readiness['problem_total']);
        self::assertSame(2, $readiness['problem_page']);
        self::assertSame(2, $readiness['problem_total_pages']);
        self::assertCount(1, $readiness['problem_students']);
        $preflight = $context['exam_snapshot_rows'][0]['preflight'];
        self::assertSame('NONAKTIF', $preflight['status_label']);
        self::assertFalse($preflight['can_start']);
        self::assertFalse($preflight['rest_warm_ready']);
        self::assertSame(12, $preflight['target_student_count']);
        self::assertSame('READY', $preflight['stage_question_label']);
        self::assertSame('READY', $preflight['stage_start_snapshot_label']);
        self::assertSame('BELUM', $preflight['stage_submission_context_label']);
        self::assertSame('BELUM', $preflight['stage_profiles_label']);
        self::assertSame('BELUM', $preflight['stage_login_snapshot_label']);
        self::assertSame(0, $preflight['login_snapshot_ready_count']);
        self::assertSame(12, $preflight['login_snapshot_missing_count']);
    }

    public function test_build_page_context_includes_active_preflight_context_for_selected_exam(): void
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
                    'options' => [],
                ],
            ];
        });
        \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [901],
                'question_number_map' => [901 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        update_option('cbt_exam_preflight_state', [
            'active' => true,
            'status' => 'active',
            'session_id' => 'preflight-77-123',
            'exam_id' => 77,
            'exam_title' => 'Ujian Matematika',
            'target_student_ids' => [71],
            'target_student_count' => 1,
            'profile_cursor' => 1,
            'profile_success_count' => 1,
            'profile_failure_count' => 0,
            'login_snapshot_success_count' => 1,
            'login_snapshot_failure_count' => 0,
            'submission_context_question_count' => 2,
            'submission_context_ready_count' => 1,
            'submission_context_missing_count' => 1,
            'submission_context_invalid_count' => 0,
            'question_snapshot_ready' => true,
            'start_snapshot_ready' => true,
            'submission_context_ready' => false,
            'auto_warm_started' => true,
            'exam_status' => 'published',
            'target_kelas_csv' => 'XI-A',
            'started_at' => '2026-04-04 12:00:00',
            'finished_at' => '',
            'last_tick_at' => '2026-04-04 12:01:00',
            'last_message' => 'Batch awal one-click memproses 1 siswa.',
            'stage_question' => 'ready',
            'stage_start_snapshot' => 'ready',
            'stage_submission_context' => 'warning',
            'stage_profiles' => 'active',
            'stage_login_snapshot' => 'active',
            'stage_auto_warm' => 'ready',
        ]);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        $preflight = $context['exam_snapshot_rows'][0]['preflight'];
        self::assertSame('AKTIF', $preflight['status_label']);
        self::assertSame('preflight-77-123', $preflight['session_id']);
        self::assertSame(1, $preflight['profile_success_count']);
        self::assertSame(1, $preflight['profile_processed_count']);
        self::assertSame(1, $preflight['login_snapshot_ready_count']);
        self::assertSame(0, $preflight['login_snapshot_missing_count']);
        self::assertSame('READY', $preflight['stage_start_snapshot_label']);
        self::assertSame('SELESAI DENGAN CATATAN', $preflight['stage_submission_context_label']);
        self::assertSame(2, $preflight['submission_context_question_count']);
        self::assertSame(1, $preflight['submission_context_ready_count']);
        self::assertSame(1, $preflight['submission_context_missing_count']);
        self::assertSame('AKTIF', $preflight['stage_profiles_label']);
        self::assertSame('AKTIF', $preflight['stage_login_snapshot_label']);
    }

    public function test_build_page_context_includes_queued_preflight_context_for_second_exam(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Question_Delivery_Cache::get_exam_payload(54, static function (int $examId): array {
            return [
                [
                    'id' => 954,
                    'exam_id' => $examId,
                    'question_text' => 'Soal Biologi',
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'options' => [],
                ],
            ];
        });
        \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(54, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [954],
                'question_number_map' => [954 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        update_option('cbt_exam_preflight_jobs', [
            77 => [
                'active' => true,
                'status' => 'active',
                'session_id' => 'preflight-77-123',
                'exam_id' => 77,
                'exam_title' => 'Ujian Matematika',
                'exam_status' => 'published',
                'target_kelas_csv' => 'XI-A',
                'target_student_ids' => [71],
                'target_student_count' => 1,
                'started_at' => '2026-04-04 12:00:00',
                'last_tick_at' => '2026-04-04 12:01:00',
                'stage_question' => 'ready',
                'stage_start_snapshot' => 'ready',
                'stage_submission_context' => 'ready',
                'stage_profiles' => 'active',
                'stage_login_snapshot' => 'pending',
                'stage_auto_warm' => 'pending',
            ],
            54 => [
                'active' => false,
                'status' => 'queued',
                'session_id' => 'preflight-54-123',
                'exam_id' => 54,
                'exam_title' => 'Ujian Biologi',
                'exam_status' => 'published',
                'target_kelas_csv' => 'XI-B',
                'target_student_ids' => [72],
                'target_student_count' => 1,
                'started_at' => '2026-04-04 12:02:00',
                'last_tick_at' => '2026-04-04 12:02:00',
                'queue_position' => 1,
                'profiles_pending_count' => 1,
                'login_pending_count' => 1,
                'availability_pending_count' => 1,
                'stage_question' => 'ready',
                'stage_start_snapshot' => 'ready',
                'stage_submission_context' => 'ready',
                'stage_profiles' => 'queued',
                'stage_login_snapshot' => 'queued',
                'stage_auto_warm' => 'queued',
            ],
        ]);
        update_option('cbt_exam_preflight_global_runner', [
            'active_exam_id' => 77,
            'active_exam_title' => 'Ujian Matematika',
            'active_layer' => 'profiles',
            'queue_exam_ids' => [54],
            'session_id' => 'preflight-77-123',
        ]);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_exam_id' => '54',
        ]);

        $preflight = $context['exam_snapshot_rows'][0]['preflight'];
        self::assertSame('MENUNGGU', $preflight['status_label']);
        self::assertSame('READY', $preflight['stage_question_label']);
        self::assertSame('READY', $preflight['stage_start_snapshot_label']);
        self::assertSame('READY', $preflight['stage_submission_context_label']);
        self::assertSame('MENUNGGU', $preflight['stage_profiles_label']);
        self::assertSame('MENUNGGU', $preflight['stage_login_snapshot_label']);
        self::assertSame('MENUNGGU', $preflight['stage_auto_warm_label']);
        self::assertSame(1, $preflight['queue_position']);
        self::assertSame(1, $preflight['queue_total']);
        self::assertSame(77, $preflight['global_runner_exam_id']);
        self::assertSame('Ujian Matematika', $preflight['global_runner_exam_title']);
        self::assertFalse($preflight['can_start']);
    }

    public function test_build_page_context_marks_selected_exam_not_ready_when_question_snapshot_missing(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_exam_id' => '54',
        ]);

        self::assertCount(1, $context['exam_snapshot_rows']);
        $readiness = $context['exam_snapshot_rows'][0]['readiness'];
        self::assertSame('BELUM SIAP', $readiness['overall_label']);
        self::assertContains('Snapshot Soal belum READY.', $readiness['blockers']);
        self::assertContains('Start Snapshot belum READY.', $readiness['blockers']);
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
        self::assertArrayHasKey('auto_warm', $context['exam_snapshot_rows'][0]);
        self::assertSame('NONAKTIF', $context['exam_snapshot_rows'][0]['auto_warm']['status_label']);
    }

    public function test_build_page_context_ignores_exam_list_filters_for_snapshot_panel(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_exam_id' => '54',
            'cbt_exam_search' => 'Matematika',
            'cbt_exam_status' => 'closed',
            'cbt_exam_subject' => '3',
            'cbt_exam_kelas' => 'XI-A',
            'cbt_exam_per_page' => '100',
        ]);

        self::assertCount(2, $context['exam_snapshot_exam_options']);
        self::assertSame([
            [
                'label' => 'Exam',
                'value' => 'Ujian Biologi',
            ],
        ], $context['exam_snapshot_active_filters']);
        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame('Ujian Biologi', $context['exam_snapshot_rows'][0]['title']);
    }

    public function test_build_page_context_supports_multiple_selected_snapshot_exams_for_monitor_tab(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
        ]);

        self::assertSame(77, $context['exam_snapshot_filter_state']['exam_id']);
        self::assertSame([77, 54], $context['exam_snapshot_filter_state']['exam_ids']);
        self::assertSame(2, $context['exam_snapshot_total']);
        self::assertCount(2, $context['exam_snapshot_rows']);
    }

    public function test_build_page_context_builds_bulk_preflight_context_for_multiple_selected_exams(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
        ]);

        self::assertSame(77, $context['exam_snapshot_filter_state']['exam_id']);
        self::assertSame([77, 54], $context['exam_snapshot_filter_state']['exam_ids']);
        self::assertSame(2, $context['exam_snapshot_total']);
        self::assertSame([], $context['exam_snapshot_rows']);
        self::assertSame(2, $context['bulk_preflight']['selected_exam_total']);
        self::assertSame(10, $context['bulk_preflight']['limit_max_exams']);
        self::assertTrue($context['bulk_preflight']['can_start_bulk']);
        self::assertCount(2, $context['bulk_preflight']['rows']);
        self::assertSame(77, (int) ($context['bulk_preflight']['rows'][0]['exam_id'] ?? 0));
        self::assertSame(54, (int) ($context['bulk_preflight']['rows'][1]['exam_id'] ?? 0));
    }

    public function test_build_page_context_keeps_exam_readiness_page_state_per_exam(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        for ($index = 0; $index < 11; $index++) {
            $xi_a_user_id = 400 + $index;
            cbt_test_register_user([
                'ID' => $xi_a_user_id,
                'display_name' => 'XI-A Extra ' . $index,
                'user_login' => 'xia_extra_' . $index,
                'user_email' => 'xia_extra_' . $index . '@example.com',
                'user_pass' => 'pass-' . $index,
                'roles' => ['student'],
            ]);
            update_user_meta($xi_a_user_id, 'kode_kelas', 'XI-A');
            update_user_meta($xi_a_user_id, 'kode_ruang', 'R1');

            $xi_b_user_id = 500 + $index;
            cbt_test_register_user([
                'ID' => $xi_b_user_id,
                'display_name' => 'XI-B Extra ' . $index,
                'user_login' => 'xib_extra_' . $index,
                'user_email' => 'xib_extra_' . $index . '@example.com',
                'user_pass' => 'pass-b-' . $index,
                'roles' => ['student'],
            ]);
            update_user_meta($xi_b_user_id, 'kode_kelas', 'XI-B');
            update_user_meta($xi_b_user_id, 'kode_ruang', 'R2');
        }

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
            'cbt_exam_readiness_page_77' => '2',
        ]);

        self::assertSame([77 => 2], $context['exam_readiness_pages']);
        self::assertCount(2, $context['exam_snapshot_rows']);
        self::assertSame(2, $context['exam_snapshot_rows'][0]['readiness']['problem_page']);
        self::assertSame(1, $context['exam_snapshot_rows'][1]['readiness']['problem_page']);
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
        self::assertSame([], $context['student_snapshot_rows']);
    }

    public function test_build_page_context_filters_student_snapshot_rows_by_search(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'students',
            'cbt_student_snapshot_q' => 'bimo',
            'cbt_student_snapshot_kelas' => 'XI-B',
            'cbt_student_snapshot_ruang' => 'R2',
            'cbt_student_snapshot_paged' => '9',
        ]);

        self::assertSame(\CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR, $context['exam_snapshot_tab']);
        self::assertSame('bimo', $context['student_snapshot_filter_state']['search']);
        self::assertSame('XI-B', $context['student_snapshot_filter_state']['kelas']);
        self::assertSame('R2', $context['student_snapshot_filter_state']['ruang']);
        self::assertSame(9, $context['student_snapshot_filter_state']['paged']);
        self::assertSame(1, $context['student_snapshot_total']);
        self::assertCount(1, $context['student_snapshot_rows']);
        self::assertSame(1, $context['student_snapshot_current_page']);
        self::assertSame(1, $context['student_snapshot_total_pages']);
        self::assertSame('Bimo', $context['student_snapshot_rows'][0]['display_name']);
        self::assertSame([
            [
                'label' => 'Cari Siswa',
                'value' => 'bimo',
            ],
            [
                'label' => 'Kelas',
                'value' => 'XI-B',
            ],
            [
                'label' => 'Ruang',
                'value' => 'R2',
            ],
        ], $context['student_snapshot_active_filters']);
    }

    public function test_build_page_context_filters_exam_monitor_rows_by_status(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(71, static function (): array {
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
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
            'cbt_student_snapshot_status' => 'ready',
        ]);

        self::assertSame('ready', $context['student_snapshot_filter_state']['status']);
        self::assertCount(1, $context['student_snapshot_rows']);
        self::assertSame('Salsa', $context['student_snapshot_rows'][0]['display_name']);
        self::assertSame('READY', $context['student_snapshot_rows'][0]['availability_status_label']);
        self::assertContains([
            'label' => 'Status Snapshot',
            'value' => 'READY',
        ], $context['student_snapshot_active_filters']);
    }

    public function test_build_page_context_filters_exam_monitor_rows_by_negated_status(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Availability_Cache::warm_prepared_student_snapshot(71, static function (): array {
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
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'username' => 'salsa',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                ],
            ];
        });

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
            'cbt_student_snapshot_status' => '!ready',
        ]);

        self::assertSame('!ready', $context['student_snapshot_filter_state']['status']);
        self::assertCount(1, $context['student_snapshot_rows']);
        self::assertSame('Bimo', $context['student_snapshot_rows'][0]['display_name']);
        self::assertSame('MISS', $context['student_snapshot_rows'][0]['availability_status_label']);
        self::assertContains([
            'label' => 'Status Snapshot',
            'value' => '! READY',
        ], $context['student_snapshot_active_filters']);
    }

    public function test_build_page_context_filters_login_monitor_rows_by_status(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'context_login_ready');

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR,
            'cbt_student_snapshot_status' => 'ready',
        ]);

        self::assertSame(\CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR, $context['exam_snapshot_tab']);
        self::assertSame('ready', $context['student_snapshot_filter_state']['status']);
        self::assertCount(1, $context['student_snapshot_rows']);
        self::assertSame('Salsa', $context['student_snapshot_rows'][0]['display_name']);
        self::assertSame('READY', $context['student_snapshot_rows'][0]['login_status_label']);
        self::assertContains([
            'label' => 'Status Snapshot',
            'value' => 'READY',
        ], $context['student_snapshot_active_filters']);
    }

    public function test_build_page_context_filters_login_monitor_rows_by_negated_status(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'context_login_ready');

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR,
            'cbt_student_snapshot_status' => '!ready',
        ]);

        self::assertSame(\CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR, $context['exam_snapshot_tab']);
        self::assertSame('!ready', $context['student_snapshot_filter_state']['status']);
        self::assertCount(1, $context['student_snapshot_rows']);
        self::assertSame('Bimo', $context['student_snapshot_rows'][0]['display_name']);
        self::assertSame('MISS', $context['student_snapshot_rows'][0]['login_status_label']);
        self::assertContains([
            'label' => 'Status Snapshot',
            'value' => '! READY',
        ], $context['student_snapshot_active_filters']);
    }

    public function test_build_page_context_accepts_login_monitor_as_student_snapshot_tab(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'context_login_tab');

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'login_monitor',
            'cbt_student_snapshot_q' => 'salsa',
            'cbt_student_snapshot_kelas' => 'XI-A',
            'cbt_student_snapshot_ruang' => 'R1',
            'cbt_student_snapshot_paged' => '3',
        ]);

        self::assertSame(\CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR, $context['exam_snapshot_tab']);
        self::assertTrue(\CBT_Admin_Exams_Service::is_exam_snapshot_student_tab('login_monitor'));
        self::assertStringContainsString('cbt_exam_snapshot_tab=login_monitor', (string) $context['student_snapshot_reset_url']);
        self::assertCount(1, $context['student_snapshot_rows']);
        self::assertSame('READY', $context['student_snapshot_rows'][0]['login_status_label']);
        self::assertSame('context_login_tab', $context['student_snapshot_rows'][0]['login']['snapshot_source']);
        self::assertContains('login:salsa', $context['student_snapshot_rows'][0]['login']['identifiers']);
    }

    public function test_build_page_context_accepts_submission_context_monitor_as_exam_snapshot_tab(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Question_Submission_Context_Cache::warm_exam_snapshots(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'submission_context_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertSame(\CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR, $context['exam_snapshot_tab']);
        self::assertTrue(\CBT_Admin_Exams_Service::is_exam_snapshot_exam_tab('submission_context_monitor'));
        self::assertStringContainsString('cbt_exam_snapshot_tab=submission_context_monitor', (string) $context['exam_snapshot_reset_url']);
        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame('READY', $context['exam_snapshot_rows'][0]['submission_context_status_label']);
        self::assertSame(2, $context['exam_snapshot_rows'][0]['submission_context']['question_count']);
        self::assertSame(2, $context['exam_snapshot_rows'][0]['submission_context']['ready_count']);
        self::assertCount(2, $context['exam_snapshot_rows'][0]['submission_context']['preview_items']);
    }

    public function test_build_page_context_builds_session_runtime_monitor_rows_for_selected_exam(): void
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
                    'options' => [],
                ],
            ];
        });
        \CBT_Attempt_Session_Snapshot_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'started_at' => '2026-04-04 07:00:00',
            'duration_minutes' => 90,
            'extra_time_minutes' => 5,
            'question_count' => 8,
            'question_order_signature' => 'runtime-sig-501',
            'show_student_result' => 1,
            'enable_calculator' => 1,
        ]);
        \CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'question_order_ids' => [901],
            'question_number_map' => [901 => 1],
            'question_order_signature' => 'runtime-sig-501',
            'question_manifest' => [['id' => 901, 'question_number' => 1]],
            'option_order_map' => [],
        ]);
        $GLOBALS['cbt_test_start_attempt_gate_now'] = 1000.0;
        for ($index = 1; $index <= 50; $index++) {
            \CBT_Start_Attempt_Gate_Service::evaluate_request(77, 9000 + $index);
        }
        \CBT_Start_Attempt_Gate_Service::evaluate_request(77, 71);
        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'session_runtime_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertSame(\CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR, $context['exam_snapshot_tab']);
        self::assertTrue(\CBT_Admin_Exams_Service::is_exam_snapshot_exam_tab('session_runtime_monitor'));
        self::assertStringContainsString('cbt_exam_snapshot_tab=session_runtime_monitor', (string) $context['exam_snapshot_reset_url']);
        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame(1, $context['exam_snapshot_rows'][0]['session_runtime']['attempt_total']);
        self::assertSame('GATED', $context['exam_snapshot_rows'][0]['session_runtime']['start_gate']['status_label']);
        self::assertSame(1, $context['exam_snapshot_rows'][0]['session_runtime']['start_gate']['queue_depth']);
        self::assertSame(0, $context['exam_snapshot_rows'][0]['session_runtime']['redis_first_count']);
        self::assertSame(1, $context['exam_snapshot_rows'][0]['session_runtime']['legacy_count']);
        self::assertSame(1, $context['exam_snapshot_rows'][0]['session_runtime']['session_ready_count']);
        self::assertSame(1, $context['exam_snapshot_rows'][0]['session_runtime']['contract_ready_count']);
        self::assertSame(0, $context['exam_snapshot_rows'][0]['session_runtime']['runtime_ready_count']);
        self::assertNotEmpty($context['exam_snapshot_rows'][0]['session_runtime']['fallback_breakdown']);
        self::assertContains('1 runtime miss', $context['exam_snapshot_rows'][0]['session_runtime']['issue_flags']);
        self::assertCount(1, $context['exam_snapshot_rows'][0]['session_runtime']['rows']);
        self::assertSame('READY', $context['exam_snapshot_rows'][0]['session_runtime']['rows'][0]['session_status_label']);
        self::assertSame('READY', $context['exam_snapshot_rows'][0]['session_runtime']['rows'][0]['contract_status_label']);
        self::assertSame('MISS', $context['exam_snapshot_rows'][0]['session_runtime']['rows'][0]['runtime_answers_status_label']);
        self::assertSame('LEGACY runtime', $context['exam_snapshot_rows'][0]['session_runtime']['rows'][0]['fallback_mode']);
        self::assertSame('runtime miss', $context['exam_snapshot_rows'][0]['session_runtime']['rows'][0]['issue_summary']);
    }

    public function test_build_page_context_filters_session_runtime_rows_by_status(): void
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
                    'options' => [],
                ],
            ];
        });
        \CBT_Attempt_Session_Snapshot_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'started_at' => '2026-04-04 07:00:00',
            'duration_minutes' => 90,
            'extra_time_minutes' => 5,
            'question_count' => 8,
            'question_order_signature' => 'runtime-sig-501',
        ]);
        \CBT_Attempt_Question_Contract_Cache::write_attempt_snapshot(501, [
            'attempt_id' => 501,
            'exam_id' => 77,
            'student_id' => 71,
            'status' => 'in_progress',
            'question_order_ids' => [901],
            'question_number_map' => [901 => 1],
            'question_order_signature' => 'runtime-sig-501',
            'question_manifest' => [['id' => 901, 'question_number' => 1]],
            'option_order_map' => [],
        ]);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'session_runtime_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
            'cbt_student_snapshot_status' => 'runtime_miss',
        ]);

        self::assertSame('runtime_miss', $context['student_snapshot_filter_state']['status']);
        self::assertContains([
            'label' => 'Status Snapshot',
            'value' => 'RUNTIME MISS',
        ], $context['student_snapshot_active_filters']);
        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame(1, $context['exam_snapshot_rows'][0]['session_runtime']['attempt_total']);
        self::assertCount(1, $context['exam_snapshot_rows'][0]['session_runtime']['rows']);
        self::assertSame('MISS', $context['exam_snapshot_rows'][0]['session_runtime']['rows'][0]['runtime_answers_status_label']);
    }

    public function test_build_page_context_normalizes_session_runtime_selection_to_single_exam(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'session_runtime_monitor',
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
        ]);

        self::assertSame([77], array_values(array_map('intval', (array) ($context['exam_snapshot_filter_state']['exam_ids'] ?? []))));
        self::assertSame(77, (int) ($context['exam_snapshot_filter_state']['exam_id'] ?? 0));
        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame(77, (int) ($context['exam_snapshot_rows'][0]['exam_id'] ?? 0));
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

    private function useFakeStartSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Exam_Start_Attempt_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('start_snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('start_snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('start_snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeAvailabilityRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Exam_Availability_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeStartAttemptGateRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Start_Attempt_Gate_Service::class);

        $redisProperty = $reflection->getProperty('gate_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('gate_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('gate_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeProfileRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeLoginSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Login_Auth_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeSubmissionContextRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Question_Submission_Context_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeAttemptSessionRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Attempt_Session_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeAttemptContractRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Attempt_Question_Contract_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeRuntimeRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
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
                    'target_kelas' => 'XI-A',
                    'question_count' => 12,
                    'attempt_total' => 0,
                    'attempt_in_progress' => 0,
                    'attempt_completed' => 0,
                ],
                [
                    'id' => 54,
                    'title' => 'Ujian Biologi',
                    'subject_name' => 'Biologi',
                    'status' => 'published',
                    'target_kelas' => 'XI-B',
                    'question_count' => 8,
                    'attempt_total' => 0,
                    'attempt_in_progress' => 0,
                    'attempt_completed' => 0,
                    'target_kelas' => 'XI-B',
                ],
            ];
        }

        if (strpos($query, 'SELECT e.id, e.title, e.status, e.target_kelas, s.name AS subject_name') !== false) {
            if (strpos($query, 'e.id = 77') !== false || strpos($query, 'e.id IN (77)') !== false) {
                return [
                    ['id' => 77, 'title' => 'Ujian Matematika', 'status' => 'published', 'subject_name' => 'Matematika', 'target_kelas' => 'XI-A', 'duration_minutes' => 90, 'show_student_result' => 1, 'enable_calculator' => 1, 'starts_at' => '', 'ends_at' => ''],
                ];
            }

            if (strpos($query, 'e.id = 54') !== false || strpos($query, 'e.id IN (54)') !== false) {
                return [
                    ['id' => 54, 'title' => 'Ujian Biologi', 'status' => 'published', 'subject_name' => 'Biologi', 'target_kelas' => 'XI-B', 'duration_minutes' => 60, 'show_student_result' => 1, 'enable_calculator' => 1, 'starts_at' => '', 'ends_at' => ''],
                ];
            }

            return [
                ['id' => 77, 'title' => 'Ujian Matematika', 'status' => 'published', 'subject_name' => 'Matematika', 'target_kelas' => 'XI-A', 'duration_minutes' => 90, 'show_student_result' => 1, 'enable_calculator' => 1, 'starts_at' => '', 'ends_at' => ''],
                ['id' => 54, 'title' => 'Ujian Biologi', 'status' => 'published', 'subject_name' => 'Biologi', 'target_kelas' => 'XI-B', 'duration_minutes' => 60, 'show_student_result' => 1, 'enable_calculator' => 1, 'starts_at' => '', 'ends_at' => ''],
            ];
        }

        if (strpos($query, 'SELECT q.exam_id AS target_exam_id') !== false) {
            return [];
        }

        if (strpos($query, 'FROM wp_cbt_attempts') !== false && strpos($query, "status = 'in_progress'") !== false) {
            if (strpos($query, 'exam_id = 77') !== false) {
                return [
                    [
                        'id' => 501,
                        'exam_id' => 77,
                        'student_id' => 71,
                        'status' => 'in_progress',
                        'started_at' => '2026-04-04 07:00:00',
                        'extra_time_minutes' => 5,
                    ],
                ];
            }

            return [];
        }

        if (strpos($query, 'FROM wp_cbt_questions') !== false && strpos($query, 'WHERE exam_id = 77') !== false) {
            return [
                ['id' => 901, 'question_type' => 'multiple_choice'],
                ['id' => 902, 'question_type' => 'short_answer'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_questions q') !== false && strpos($query, 'WHERE q.id IN (901,902)') !== false) {
            return [
                ['id' => 901, 'exam_id' => 77, 'question_type' => 'multiple_choice', 'points' => 5, 'correct_text' => '', 'true_false_correct_value' => null, 'short_answer_correct_text' => null],
                ['id' => 902, 'exam_id' => 77, 'question_type' => 'short_answer', 'points' => 3, 'correct_text' => '', 'true_false_correct_value' => null, 'short_answer_correct_text' => 'Jakarta'],
            ];
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false && strpos($query, 'WHERE question_id IN (901)') !== false) {
            return [
                ['id' => 1, 'question_id' => 901, 'option_text' => 'A', 'is_correct' => 1],
                ['id' => 2, 'question_id' => 901, 'option_text' => 'B', 'is_correct' => 0],
            ];
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

        if (strpos($query, 'COUNT(*) FROM wp_cbt_attempts a INNER JOIN wp_cbt_exams e') !== false && strpos($query, "a.status = 'in_progress'") !== false) {
            return 1;
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

        if (strpos($query, 'SELECT e.id FROM wp_cbt_exams e') !== false) {
            return ['77', '54'];
        }

        return [];
    }
}
