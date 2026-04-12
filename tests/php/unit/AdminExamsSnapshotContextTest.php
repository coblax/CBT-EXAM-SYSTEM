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
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-readiness-warm-queue-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-snapshot-metrics-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-start-attempt-metrics-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-entry-flow-metrics-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-adaptive-load-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-plugin-redis-reset-service.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';

if (!class_exists('\CBT_REST')) {
    class CBT_REST_AutoHeal_Question_Test_Double
    {
        public static array $warmedExamIds = [];
        public static array $warmedStartExamIds = [];

        public static function warm_exam_question_delivery_snapshot(int $exam_id): void
        {
            self::$warmedExamIds[] = $exam_id;
            \CBT_Exam_Question_Delivery_Cache::warm_exam_payload($exam_id, static function (int $target_exam_id): array {
                return [
                    [
                        'id' => 900 + $target_exam_id,
                        'exam_id' => $target_exam_id,
                        'question_text' => 'Snapshot exam ' . $target_exam_id,
                        'question_type' => 'multiple_choice',
                        'points' => 1,
                        'options' => [],
                    ],
                ];
            });
        }

        public static function warm_exam_start_attempt_snapshot(int $exam_id): void
        {
            self::$warmedStartExamIds[] = $exam_id;
            \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot($exam_id, static function (int $target_exam_id): array {
                return [
                    'exam_id' => $target_exam_id,
                    'question_ids' => [900 + $target_exam_id],
                    'question_count' => 1,
                    'question_number_map' => [900 + $target_exam_id => 1],
                    'randomize_questions' => 0,
                    'randomize_options' => 0,
                    'duration_minutes' => 75,
                    'show_student_result' => 0,
                    'enable_calculator' => 1,
                    'option_randomization_tokens_by_question' => [],
                ];
            });
        }

        public static function warm_exam_submission_context_snapshot(int $exam_id): void
        {
            \CBT_Question_Submission_Context_Cache::warm_exam_snapshots($exam_id);
        }
    }

    class_alias(CBT_REST_AutoHeal_Question_Test_Double::class, 'CBT_REST');
}

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

final class AdminExamsSnapshotContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_transient('cbt_exam_operational_stats_global');
        delete_transient('cbt_exam_operational_stats_user_0');
        delete_transient('cbt_exam_operational_stats_user_1');
        delete_option('cbt_adaptive_load_state');
        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeStartAttemptGateRedis();
        $this->useFakeAvailabilityRedis();
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
        $this->useFakeLoginMetricsRedis();
        $this->useFakeStartAttemptMetricsRedis();
        $this->useFakeEntryFlowMetricsRedis();
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
        update_option('cbt_login_snapshot_freshness_state', [
            'window_exam_count' => 2,
            'last_tick_at' => '2026-04-08 09:05:00',
            'last_refreshed_user_count' => 6,
            'last_refreshed_success_count' => 5,
            'last_message' => 'Freshness login snapshot memeriksa 2 exam window. Refresh 6 siswa (5 sukses), skip 0 exam.',
        ]);
        \CBT_Login_Snapshot_Metrics_Service::record_snapshot_success();
        \CBT_Login_Snapshot_Metrics_Service::record_snapshot_success();
        \CBT_Login_Snapshot_Metrics_Service::record_canonical_success('expired_or_evicted');
        for ($index = 0; $index < 12; $index++) {
            \CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_response_ready', 3200);
            \CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_status_response_ready', 1400);
            \CBT_Start_Attempt_Metrics_Service::record_phase('start_attempt_start_snapshot', 15000);
            \CBT_Start_Attempt_Metrics_Service::record_resolution('started');
            \CBT_Start_Attempt_Metrics_Service::record_resolution('resume_from_index');
            \CBT_Start_Attempt_Metrics_Service::record_resolution('queued_new_start');
            \CBT_Entry_Flow_Metrics_Service::record_flow('login_to_exam_list', 1800);
            \CBT_Entry_Flow_Metrics_Service::record_flow('start_to_first_question', 10000);
            \CBT_Entry_Flow_Metrics_Service::record_flow('resume_to_first_question', 8000);
            \CBT_Entry_Flow_Metrics_Service::record_phase('first_window_ready_ms', 10000);
            \CBT_Entry_Flow_Metrics_Service::record_phase('attempt_acquire_ms', 2600);
        }
        \CBT_Start_Attempt_Metrics_Service::record_resolution('lock_conflict_retryable');

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
        ]);

        self::assertNotEmpty($context['exam_operational_stats']);
        self::assertSame(10, $context['exam_operational_stats']['refreshed_every_seconds']);
        self::assertCount(11, $context['exam_operational_stats']['cards']);
        self::assertSame('Redis RAM', $context['exam_operational_stats']['cards'][0]['label']);
        self::assertSame('CBT Redis Keys', $context['exam_operational_stats']['cards'][1]['label']);
        self::assertSame('Active Attempts', $context['exam_operational_stats']['cards'][2]['label']);
        self::assertSame('Start Queue', $context['exam_operational_stats']['cards'][3]['label']);
        self::assertSame('Start Attempt', $context['exam_operational_stats']['cards'][4]['label']);
        self::assertSame('Entry Flow', $context['exam_operational_stats']['cards'][5]['label']);
        self::assertSame('Auto-Heal Queue', $context['exam_operational_stats']['cards'][6]['label']);
        self::assertSame('Adaptive Load', $context['exam_operational_stats']['cards'][7]['label']);
        self::assertSame('Warm Jobs', $context['exam_operational_stats']['cards'][8]['label']);
        self::assertSame('Login Snapshot Health', $context['exam_operational_stats']['cards'][9]['label']);
        self::assertSame('User Snapshots', $context['exam_operational_stats']['cards'][10]['label']);
        self::assertSame('5', (string) $context['exam_operational_stats']['cards'][1]['value']);
        self::assertSame('1', (string) $context['exam_operational_stats']['cards'][2]['value']);
        self::assertSame('4,000 ms', (string) $context['exam_operational_stats']['cards'][4]['value']);
        self::assertStringContainsString('Status 2,000 ms', (string) $context['exam_operational_stats']['cards'][4]['meta']);
        self::assertStringContainsString('Top start_attempt_start_snapshot 15,000 ms', (string) $context['exam_operational_stats']['cards'][4]['hint']);
        self::assertSame('10,000 ms', (string) $context['exam_operational_stats']['cards'][5]['value']);
        self::assertStringContainsString('Login 2,000 ms', (string) $context['exam_operational_stats']['cards'][5]['meta']);
        self::assertStringContainsString('Resume 8,000 ms', (string) $context['exam_operational_stats']['cards'][5]['meta']);
        self::assertStringContainsString('Top first_window_ready_ms 10,000 ms', (string) $context['exam_operational_stats']['cards'][5]['hint']);
        self::assertSame('NORMAL', (string) $context['exam_operational_stats']['cards'][7]['value']);
        self::assertStringContainsString('Heartbeat 20 detik', (string) $context['exam_operational_stats']['cards'][7]['hint']);
        self::assertSame('66.7%', (string) $context['exam_operational_stats']['cards'][9]['value']);
        self::assertStringContainsString('Snapshot 2', (string) $context['exam_operational_stats']['cards'][9]['meta']);
        self::assertStringContainsString('Fallback 1', (string) $context['exam_operational_stats']['cards'][9]['meta']);
        self::assertStringContainsString('Freshness 2', (string) $context['exam_operational_stats']['cards'][9]['meta']);
        self::assertNotEmpty($context['login_snapshot_health_context']);
        self::assertSame('66.7%', (string) $context['login_snapshot_health_context']['hit_rate_label']);
        self::assertSame(2, (int) $context['login_snapshot_health_context']['freshness_window_jobs']);
        self::assertNotEmpty($context['login_readiness_warm_queue_context']);
        self::assertSame('IDLE', (string) $context['login_readiness_warm_queue_context']['status_label']);
        self::assertSame(0, (int) $context['login_readiness_warm_queue_context']['target_count']);
        self::assertNotEmpty($context['adaptive_load_context']);
        self::assertSame('NORMAL', (string) $context['adaptive_load_context']['level_label']);
        self::assertSame(10, (int) $context['adaptive_load_context']['admin_snapshot_refresh_seconds']);
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
        self::assertSame('queued_auto_heal', $salsaRow['availability']['repair_status']);
        self::assertSame(1, $context['availability_rewarm_queue']['queued_count']);
        self::assertSame(1, $contextSecond['availability_rewarm_queue']['queued_count']);
    }

    public function test_build_page_context_profile_monitor_auto_heals_eligible_profile_miss_inline(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Student_Profile_Cache::warm_snapshot(71);
        \CBT_Student_Profile_Cache::handle_user_meta_change(2, 71, 'agama', 'Islam');

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR,
        ]);

        $salsaRow = null;
        foreach ((array) $context['student_snapshot_rows'] as $studentRow) {
            if ((int) ($studentRow['user_id'] ?? 0) === 71) {
                $salsaRow = $studentRow;
                break;
            }
        }

        self::assertIsArray($salsaRow);
        self::assertSame('READY', $salsaRow['profile_status_label']);
        self::assertSame('ready', $salsaRow['profile']['snapshot_status']);
        self::assertSame('auto_healed', $salsaRow['profile']['repair_status']);
        self::assertSame('Dipulihkan otomatis dari usermeta', $salsaRow['profile']['repair_message']);
        self::assertNotSame('', (string) ($GLOBALS['cbt_test_redis_storage']['cbt_profile:user:71'] ?? ''));
    }

    public function test_build_page_context_login_monitor_auto_heals_whitelisted_login_miss_inline(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'context_login_heal');
        \CBT_Login_Auth_Snapshot_Cache::handle_password_reset(get_user_by('id', 71), 'baru');

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR,
        ]);

        $salsaRow = null;
        foreach ((array) $context['student_snapshot_rows'] as $studentRow) {
            if ((int) ($studentRow['user_id'] ?? 0) === 71) {
                $salsaRow = $studentRow;
                break;
            }
        }

        self::assertIsArray($salsaRow);
        self::assertSame('READY', $salsaRow['login_status_label']);
        self::assertSame('ready', $salsaRow['login']['snapshot_status']);
        self::assertSame('auto_healed', $salsaRow['login']['repair_status']);
        self::assertSame('Dipulihkan otomatis dari data login canonical', $salsaRow['login']['repair_message']);
        self::assertNotSame('', (string) ($GLOBALS['cbt_test_redis_storage']['cbt_login_auth:user:71'] ?? ''));
    }

    public function test_build_page_context_exam_monitor_does_not_auto_heal_profile_miss_inline(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Student_Profile_Cache::warm_snapshot(71);
        \CBT_Student_Profile_Cache::handle_user_meta_change(2, 71, 'agama', 'Islam');

        $context = \CBT_Admin_Exams_Service::build_page_context([
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
        self::assertSame('MISS', $salsaRow['profile_status_label']);
        self::assertSame('miss', $salsaRow['profile']['snapshot_status']);
        self::assertSame('meta_changed', $salsaRow['profile']['snapshot_miss_reason']);
        self::assertSame('queued_auto_heal', (string) ($salsaRow['profile']['repair_status'] ?? ''));
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

    public function test_build_page_context_question_monitor_exposes_question_snapshot_miss_reason(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
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

        \CBT_Exam_Question_Delivery_Cache::clear_exam_payload(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR,
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame('miss', $context['exam_snapshot_rows'][0]['snapshot_status']);
        self::assertSame('manual_clear', $context['exam_snapshot_rows'][0]['snapshot_miss_reason']);
        self::assertSame('Dibersihkan manual', $context['exam_snapshot_rows'][0]['snapshot_miss_reason_label']);
    }

    public function test_build_page_context_start_monitor_exposes_start_snapshot_miss_reason(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [901],
                'question_count' => 1,
                'question_number_map' => [901 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });

        \CBT_Exam_Start_Attempt_Snapshot_Cache::clear_exam_snapshot(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR,
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame('miss', $context['exam_snapshot_rows'][0]['start_snapshot_status']);
        self::assertSame('manual_clear', $context['exam_snapshot_rows'][0]['start_snapshot_miss_reason']);
        self::assertSame('Dibersihkan manual', $context['exam_snapshot_rows'][0]['start_snapshot_miss_reason_label']);
    }

    public function test_build_page_context_start_monitor_auto_heals_eligible_miss_for_single_exam(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [901],
                'question_count' => 1,
                'question_number_map' => [901 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        \CBT_Cache::invalidate_exam(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR,
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame('ready', $context['exam_snapshot_rows'][0]['start_snapshot_status']);
        self::assertSame('READY', $context['exam_snapshot_rows'][0]['start_snapshot_status_label']);
        self::assertSame('auto_healed', $context['exam_snapshot_rows'][0]['start_snapshot_repair_status']);
        self::assertSame('Dipulihkan otomatis dari revision exam terbaru', $context['exam_snapshot_rows'][0]['start_snapshot_repair_message']);
    }

    public function test_build_page_context_start_monitor_does_not_auto_heal_multi_exam_context(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot(77, static function (int $examId): array {
            return [
                'exam_id' => $examId,
                'question_ids' => [901],
                'question_count' => 1,
                'question_number_map' => [901 => 1],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 60,
                'show_student_result' => 0,
                'enable_calculator' => 0,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        \CBT_Cache::invalidate_exam(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR,
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
        ]);

        $row77 = null;
        foreach ((array) $context['exam_snapshot_rows'] as $row) {
            if ((int) ($row['exam_id'] ?? 0) === 77) {
                $row77 = $row;
                break;
            }
        }

        self::assertIsArray($row77);
        self::assertSame('miss', $row77['start_snapshot_status']);
        self::assertSame('revision_changed', $row77['start_snapshot_miss_reason']);
        self::assertSame('queued_auto_heal', (string) ($row77['start_snapshot_repair_status'] ?? ''));
    }

    public function test_build_page_context_question_monitor_auto_heals_eligible_miss_for_single_exam(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
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
        \CBT_Cache::invalidate_exam(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR,
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertCount(1, $context['exam_snapshot_rows']);
        self::assertSame('ready', $context['exam_snapshot_rows'][0]['snapshot_status']);
        self::assertSame('READY', $context['exam_snapshot_rows'][0]['snapshot_status_label']);
        self::assertSame('auto_healed', $context['exam_snapshot_rows'][0]['repair_status']);
        self::assertSame('Dipulihkan otomatis dari revision exam terbaru', $context['exam_snapshot_rows'][0]['repair_message']);
    }

    public function test_build_page_context_question_monitor_does_not_auto_heal_multi_exam_context(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Exam_Question_Delivery_Cache::warm_exam_payload(77, static function (int $examId): array {
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
        \CBT_Cache::invalidate_exam(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR,
            'cbt_exam_snapshot_exam_ids' => ['77', '54'],
        ]);

        $row77 = null;
        foreach ((array) $context['exam_snapshot_rows'] as $row) {
            if ((int) ($row['exam_id'] ?? 0) === 77) {
                $row77 = $row;
                break;
            }
        }

        self::assertIsArray($row77);
        self::assertSame('miss', $row77['snapshot_status']);
        self::assertSame('revision_changed', $row77['snapshot_miss_reason']);
        self::assertSame('queued_auto_heal', (string) ($row77['repair_status'] ?? ''));
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
        self::assertTrue($preflight['can_start']);
        self::assertTrue($preflight['rest_warm_ready']);
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

    public function test_build_page_context_submission_context_monitor_exposes_submit_snapshot_miss_reason(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Question_Submission_Context_Cache::warm_exam_snapshots(77);
        \CBT_Question_Submission_Context_Cache::clear_exam_snapshots(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'submission_context_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertSame('MISS', $context['exam_snapshot_rows'][0]['submission_context_status_label']);
        self::assertSame('manual_clear', $context['exam_snapshot_rows'][0]['submission_context']['snapshot_miss_reason']);
        self::assertSame('Dibersihkan manual', $context['exam_snapshot_rows'][0]['submission_context']['snapshot_miss_reason_label']);
    }

    public function test_build_page_context_submission_context_monitor_auto_heals_whitelisted_submit_snapshot_reason_inline(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        \CBT_Question_Submission_Context_Cache::warm_exam_snapshots(77);
        \CBT_Cache::invalidate_exam(77);

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'submission_context_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertSame('READY', $context['exam_snapshot_rows'][0]['submission_context_status_label']);
        self::assertSame('auto_healed', $context['exam_snapshot_rows'][0]['submission_context']['repair_status']);
    }

    public function test_build_page_context_submission_context_monitor_keeps_not_prepared_as_miss(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        $context = \CBT_Admin_Exams_Service::build_page_context([
            'cbt_exam_panel' => 'snapshot',
            'cbt_exam_snapshot_tab' => 'submission_context_monitor',
            'cbt_exam_snapshot_exam_id' => '77',
        ]);

        self::assertSame('MISS', $context['exam_snapshot_rows'][0]['submission_context_status_label']);
        self::assertSame('not_prepared', $context['exam_snapshot_rows'][0]['submission_context']['snapshot_miss_reason']);
        self::assertSame('', $context['exam_snapshot_rows'][0]['submission_context']['repair_status']);
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

    private function useFakeLoginMetricsRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Login_Snapshot_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeStartAttemptMetricsRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Start_Attempt_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeEntryFlowMetricsRedis(): void
    {
        $reflection = new ReflectionClass(\CBT_Entry_Flow_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
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
