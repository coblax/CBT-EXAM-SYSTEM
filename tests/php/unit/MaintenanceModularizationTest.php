<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

if (!class_exists('wpdb')) {
    eval(<<<'PHP'
class wpdb
{
    public string $prefix = 'wp_';

    public function get_charset_collate(): string
    {
        return '';
    }
}
PHP);
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path): string
    {
        $path = is_scalar($path) ? (string) $path : '';
        return str_replace('\\', '/', $path);
    }
}

if (!function_exists('wpautop')) {
    function wpautop($text): string
    {
        $text = is_scalar($text) ? trim((string) $text) : '';
        if ($text === '') {
            return '';
        }

        return '<p>' . $text . '</p>';
    }
}

final class MaintenanceModularizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-ui-state.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-branding-settings.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-maintenance-service.php';

        global $wpdb;
        $wpdb = new MaintenanceModularizationFakeWpdb();
    }

    #[RunInSeparateProcess]
    public function test_reset_service_build_reset_context_reports_running_progress(): void
    {
        set_transient('cbt_reset_progress_token123', [
            'user_id' => 1,
            'phase' => 'tables',
            'total_units' => 10,
            'processed_units' => 4,
            'deleted_user_count' => 2,
            'failed_tables' => ['wp_cbt_questions'],
        ]);

        $notice = '';
        $error = '';
        $context = \CBT_Admin_Maintenance_Reset_Service::build_reset_context([
            'cbt_reset_progress_token' => 'token123',
        ], $notice, $error);

        self::assertSame('token123', $context['reset_progress_token']);
        self::assertTrue($context['reset_progress_is_running']);
        self::assertSame('Mengosongkan tabel CBT', $context['reset_progress_phase_label']);
        self::assertSame('4 / 10', $context['reset_progress_summary_label']);
        self::assertStringContainsString('cbt_reset_progress_token=token123', (string) $context['reset_progress_continue_url']);
    }

    #[RunInSeparateProcess]
    public function test_seed_service_build_seed_context_exposes_selected_preset_metadata(): void
    {
        $notice = '';
        $error = '';
        $context = \CBT_Admin_Maintenance_Seed_Service::build_seed_context([
            'cbt_seed_preset' => 'medium',
        ], $notice, $error);

        self::assertSame('medium', $context['selected_seed_preset']);
        self::assertSame('Medium', $context['selected_seed_preset_data']['label']);
        self::assertSame(5, $context['selected_seed_preset_data']['subjects']);
        self::assertSame(17, $context['selected_seed_preset_data']['exams']);
        self::assertSame(750, $context['selected_seed_preset_data']['questions']);
        self::assertSame('GENERATE TEST DATA', $context['test_data_seed_confirm_phrase']);
        self::assertSame('Skills39', $context['test_data_seed_default_password']);
        self::assertNotSame('{}', $context['seed_presets_json']);
    }

    #[RunInSeparateProcess]
    public function test_seed_service_keeps_exam_total_constant_across_presets(): void
    {
        $notice = '';
        $error = '';
        $context = \CBT_Admin_Maintenance_Seed_Service::build_seed_context([], $notice, $error);

        self::assertSame(5, $context['seed_presets']['small']['subjects']);
        self::assertSame(5, $context['seed_presets']['medium']['subjects']);
        self::assertSame(5, $context['seed_presets']['large']['subjects']);
        self::assertSame(17, $context['seed_presets']['small']['exams']);
        self::assertSame(17, $context['seed_presets']['medium']['exams']);
        self::assertSame(17, $context['seed_presets']['large']['exams']);
        self::assertSame(200, $context['seed_presets']['small']['questions']);
        self::assertSame(750, $context['seed_presets']['medium']['questions']);
        self::assertSame(2500, $context['seed_presets']['large']['questions']);
    }

    #[RunInSeparateProcess]
    public function test_seed_service_dynamic_exam_targets_all_available_classes(): void
    {
        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Seed_Service::class);
        $method = $service->getMethod('build_test_data_seed_exam_entry');
        $method->setAccessible(true);

        $entry = $method->invoke(
            null,
            12,
            [
                ['id' => 7, 'name' => 'Matematika', 'image_bucket' => 'math'],
            ],
            ['KELAS_TEST_01', 'KELAS_TEST_02', 'KELAS_TEST_03'],
            'medium',
            99,
            [
                'profile' => 'mixed',
                'label' => 'MIXED',
                'suffix' => '[MIXED]',
                'question_type' => '',
            ]
        );

        self::assertSame('KELAS_TEST_01,KELAS_TEST_02,KELAS_TEST_03', $entry['target_kelas']);
    }

    #[RunInSeparateProcess]
    public function test_seed_service_sync_source_selection_uses_half_of_subject_bank_questions(): void
    {
        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Seed_Service::class);
        $method = $service->getMethod('build_test_data_seed_sync_source_question_ids_for_exam');
        $method->setAccessible(true);

        $selected_ids = $method->invoke(
            null,
            [
                'id' => 77,
                'subject_id' => 3,
            ],
            [
                ['id' => 77, 'subject_id' => 3],
                ['id' => 78, 'subject_id' => 3],
                ['id' => 79, 'subject_id' => 4],
            ],
            [
                77 => [1, 2],
            ],
            [
                3 => [10, 11, 12, 13, 14, 15, 16, 17],
                4 => [30, 31, 32, 33],
            ]
        );

        self::assertCount(4, $selected_ids);
        self::assertSame([10, 11, 12, 13], array_values($selected_ids));
    }

    #[RunInSeparateProcess]
    public function test_seed_service_distributes_bank_questions_evenly_per_subject_for_medium_preset(): void
    {
        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Seed_Service::class);
        $presetsMethod = $service->getMethod('test_data_seed_presets');
        $presetsMethod->setAccessible(true);
        $resolveExamMethod = $service->getMethod('resolve_test_data_seed_bank_question_exam_entry');
        $resolveExamMethod->setAccessible(true);

        $preset = $presetsMethod->invoke(null)['medium'];
        $exams = [
            ['id' => 1, 'subject_id' => 1],
            ['id' => 2, 'subject_id' => 2],
            ['id' => 3, 'subject_id' => 3],
            ['id' => 4, 'subject_id' => 4],
            ['id' => 5, 'subject_id' => 5],
            ['id' => 6, 'subject_id' => 1],
            ['id' => 7, 'subject_id' => 2],
            ['id' => 8, 'subject_id' => 3],
            ['id' => 9, 'subject_id' => 4],
            ['id' => 10, 'subject_id' => 5],
            ['id' => 11, 'subject_id' => 1],
            ['id' => 12, 'subject_id' => 2],
            ['id' => 13, 'subject_id' => 3],
            ['id' => 14, 'subject_id' => 4],
            ['id' => 15, 'subject_id' => 5],
            ['id' => 16, 'subject_id' => 1],
            ['id' => 17, 'subject_id' => 2],
        ];

        $subjectCounts = [];
        for ($index = 0; $index < (int) $preset['questions']; $index++) {
            $examEntry = $resolveExamMethod->invoke(null, $index, $exams);
            $subjectId = (int) ($examEntry['subject_id'] ?? 0);
            if ($subjectId <= 0) {
                continue;
            }

            $subjectCounts[$subjectId] = ($subjectCounts[$subjectId] ?? 0) + 1;
        }

        ksort($subjectCounts);
        self::assertSame([
            1 => 150,
            2 => 150,
            3 => 150,
            4 => 150,
            5 => 150,
        ], $subjectCounts);
    }

    #[RunInSeparateProcess]
    public function test_seed_service_rich_recipe_is_deterministic_and_keeps_majority_rich_with_image_span(): void
    {
        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Seed_Service::class);
        $method = $service->getMethod('resolve_test_data_seed_rich_recipe');
        $method->setAccessible(true);

        $firstRecipe = $method->invoke(null, 'multiple_choice', 7);
        $secondRecipe = $method->invoke(null, 'multiple_choice', 7);

        self::assertSame($firstRecipe, $secondRecipe);

        $richCount = 0;
        $imageCounts = [];
        for ($questionNumber = 1; $questionNumber <= 10; $questionNumber++) {
            $recipe = $method->invoke(null, 'multiple_choice', $questionNumber);
            if (!empty($recipe['rich'])) {
                $richCount++;
            }
            $imageCounts[(int) ($recipe['image_count_total'] ?? 0)] = true;
        }

        ksort($imageCounts);
        self::assertSame(7, $richCount);
        self::assertSame([0, 1, 2, 3], array_values(array_map('intval', array_keys($imageCounts))));
    }

    #[RunInSeparateProcess]
    public function test_seed_service_decorates_multiple_choice_row_with_lists_equation_and_rich_options(): void
    {
        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Seed_Service::class);
        $recipeMethod = $service->getMethod('resolve_test_data_seed_rich_recipe');
        $recipeMethod->setAccessible(true);
        $decorateMethod = $service->getMethod('decorate_test_data_seed_question_row_with_rich_content');
        $decorateMethod->setAccessible(true);

        $sourceMap = [];
        $recipe = $recipeMethod->invoke(null, 'multiple_choice', 10);
        $rowArgs = [
            [
                'question_type' => 'multiple_choice',
                'question_text' => 'Soal MC plain untuk rich content.',
                'explanation' => 'Pembahasan MC plain.',
            ],
            10,
            'TEST Subject 01',
            'biology',
            $recipe,
            [
                'left' => 17,
                'right' => 6,
                'correct' => 23,
                'plain_options' => ['21', '23', '24', '27'],
                'correct_keys' => ['B'],
            ],
            &$sourceMap
        ];
        $row = $decorateMethod->invokeArgs(null, $rowArgs);

        self::assertStringContainsString('<ol>', (string) $row['question_text']);
        self::assertStringContainsString('data-cbt-math=', (string) $row['question_text']);
        self::assertStringContainsString('<table', (string) $row['question_text']);
        self::assertStringContainsString('data-cbt-math=', (string) $row['explanation']);

        $optionsPayload = json_decode((string) ($row['options_payload'] ?? ''), true);
        self::assertIsArray($optionsPayload);
        self::assertCount(4, $optionsPayload);
        self::assertStringContainsString('class="cbt-math"', (string) $optionsPayload[0]['option_text']);
        self::assertStringContainsString('<ul>', (string) $optionsPayload[0]['option_text']);
        self::assertStringContainsString('<table', (string) $optionsPayload[0]['option_text']);
    }

    #[RunInSeparateProcess]
    public function test_seed_service_decorates_non_objective_rows_without_breaking_answer_contracts(): void
    {
        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Seed_Service::class);
        $recipeMethod = $service->getMethod('resolve_test_data_seed_rich_recipe');
        $recipeMethod->setAccessible(true);
        $decorateMethod = $service->getMethod('decorate_test_data_seed_question_row_with_rich_content');
        $decorateMethod->setAccessible(true);

        $sourceMap = [];
        $shortAnswerRecipe = $recipeMethod->invoke(null, 'short_answer', 10);
        $shortAnswerArgs = [
            [
                'question_type' => 'short_answer',
                'question_text' => 'Lengkapi [INPUT_1] dan [INPUT_2].',
                'correct_text' => 'jakarta||bandung',
                'explanation' => 'Pembahasan short answer.',
            ],
            10,
            'TEST Subject 01',
            'biology',
            $shortAnswerRecipe,
            [
                'input_count' => 2,
                'left' => 8,
                'right' => 4,
            ],
            &$sourceMap
        ];
        $shortAnswerRow = $decorateMethod->invokeArgs(null, $shortAnswerArgs);

        self::assertSame('jakarta||bandung', $shortAnswerRow['correct_text']);
        self::assertStringContainsString('<ol>', (string) $shortAnswerRow['question_text']);
        self::assertStringContainsString('data-cbt-math=', (string) $shortAnswerRow['question_text']);

        $essayRecipe = $recipeMethod->invoke(null, 'essay', 10);
        $essayArgs = [
            [
                'question_type' => 'essay',
                'question_text' => 'Jelaskan topik utama.',
                'correct_text' => 'Rubrik plain.',
                'explanation' => 'Pembahasan essay plain.',
            ],
            10,
            'TEST Subject 01',
            'biology',
            $essayRecipe,
            [],
            &$sourceMap
        ];
        $essayRow = $decorateMethod->invokeArgs(null, $essayArgs);

        self::assertStringContainsString('<ol>', (string) $essayRow['correct_text']);
        self::assertStringContainsString('data-cbt-math=', (string) $essayRow['correct_text']);

        $tfmRecipe = $recipeMethod->invoke(null, 'true_false_matrix', 10);
        $tfmArgs = [
            [
                'question_type' => 'true_false_matrix',
                'question_text' => 'Tentukan benar atau salah.',
                'correct_text' => "Pernyataan pertama|true\nPernyataan kedua|false\nPernyataan ketiga|true",
                'explanation' => 'Pembahasan tfm plain.',
            ],
            10,
            'TEST Subject 01',
            'biology',
            $tfmRecipe,
            ['base' => 9],
            &$sourceMap
        ];
        $tfmRow = $decorateMethod->invokeArgs(null, $tfmArgs);

        self::assertStringContainsString('Pernyataan 1.', (string) $tfmRow['correct_text']);
        self::assertStringContainsString('class="cbt-math"', (string) $tfmRow['correct_text']);
        self::assertStringContainsString('|true', (string) $tfmRow['correct_text']);
    }

    #[RunInSeparateProcess]
    public function test_load_test_presenter_builds_selection_and_card_view_models(): void
    {
        $view = \CBT_Admin_Maintenance_Load_Test_Presenter::build_jobs_view([
            [
                'id' => 'job_001',
                'status' => 'running',
                'exam_title' => 'TRY OUT MAT',
                'subject_name' => 'Matematika',
                'created_at' => '2026-03-24 12:00:00',
                'started_at' => '2026-03-24 12:01:00',
                'profile' => [
                    'vus' => 40,
                    'iterations' => 2,
                    'questions_per_user' => 10,
                ],
            ],
        ]);

        self::assertSame('job_001', $view['selected_job_id']);
        self::assertSame(1, $view['running_job_count']);
        self::assertCount(1, $view['job_options']);
        self::assertCount(1, $view['job_cards']);
        self::assertSame('TRY OUT MAT', $view['job_cards'][0]['job']['exam_title']);
    }

    #[RunInSeparateProcess]
    public function test_normalize_load_test_profile_accepts_explicit_scenario_key(): void
    {
        $profile = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
            'profile_preset' => 'smoke_50',
            'scenario_key' => 'submit_batch_only',
            'questions_per_user' => 25,
        ]);

        self::assertSame('submit_batch_only', $profile['scenario_key']);
        self::assertSame('Submit Batch Only', $profile['scenario_label']);
        self::assertSame('flat_iterations', $profile['load_shape']);
        self::assertSame('Flat', $profile['load_shape_label']);
        self::assertSame(1, $profile['scenario_reads_questions']);
        self::assertSame(1, $profile['scenario_submits_answers']);
        self::assertSame(0, $profile['scenario_finishes_exam']);
        self::assertSame('all', $profile['submit_mode']);
        self::assertSame(1, $profile['enable_batch_submit']);
        self::assertSame(25, $profile['questions_per_user']);
    }

    #[RunInSeparateProcess]
    public function test_normalize_load_test_profile_compiles_ramping_stages(): void
    {
        $profile = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
            'profile_preset' => 'ramp_load_500',
        ]);

        self::assertSame('ramping_vus', $profile['load_shape']);
        self::assertSame('Ramping', $profile['load_shape_label']);
        self::assertSame(500, $profile['peak_vus']);
        self::assertSame(500, $profile['effective_vus']);
        self::assertSame('17m', $profile['max_duration']);
        self::assertSame('Ramping: 1m warmup · 4m ramp-up · 8m steady · 2m ramp-down', $profile['stage_summary']);
        self::assertCount(6, $profile['compiled_stages']);
        self::assertSame(['target' => 75, 'duration' => '60s'], $profile['compiled_stages'][0]);
        self::assertSame(['target' => 167, 'duration' => '80s'], $profile['compiled_stages'][1]);
        self::assertSame(['target' => 333, 'duration' => '80s'], $profile['compiled_stages'][2]);
        self::assertSame(['target' => 500, 'duration' => '80s'], $profile['compiled_stages'][3]);
        self::assertSame(['target' => 500, 'duration' => '480s'], $profile['compiled_stages'][4]);
        self::assertSame(['target' => 0, 'duration' => '120s'], $profile['compiled_stages'][5]);
    }

    #[RunInSeparateProcess]
    public function test_normalize_load_test_profile_locks_load_shape_to_selected_preset(): void
    {
        $flatProfile = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
            'profile_preset' => 'smoke_50',
            'load_shape' => 'ramping_vus',
        ]);
        self::assertSame('flat_iterations', $flatProfile['load_shape']);

        $rampingProfile = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
            'profile_preset' => 'ramp_load_200',
            'load_shape' => 'flat_iterations',
        ]);
        self::assertSame('ramping_vus', $rampingProfile['load_shape']);
    }

    #[RunInSeparateProcess]
    public function test_normalize_load_test_profile_ignores_ramping_overrides_for_flat_preset(): void
    {
        $profile = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
            'profile_preset' => 'smoke_50',
            'peak_vus' => 999,
            'warmup_duration' => '9m',
            'ramp_up_duration' => '9m',
            'steady_duration' => '9m',
            'ramp_down_duration' => '9m',
            'ramp_steps' => 9,
        ]);

        self::assertSame('flat_iterations', $profile['load_shape']);
        self::assertSame(50, $profile['peak_vus']);
        self::assertSame('1m', $profile['warmup_duration']);
        self::assertSame('2m', $profile['ramp_up_duration']);
        self::assertSame('5m', $profile['steady_duration']);
        self::assertSame('1m', $profile['ramp_down_duration']);
        self::assertSame(2, $profile['ramp_steps']);
    }

    #[RunInSeparateProcess]
    public function test_normalize_load_test_profile_maps_legacy_flags_to_scenario_keys(): void
    {
        $readOnly = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
            'submit_mode' => 'none',
            'enable_batch_submit' => '0',
        ]);
        self::assertSame('read_questions_only', $readOnly['scenario_key']);

        $sequential = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
            'submit_mode' => 'all',
            'enable_batch_submit' => '0',
        ]);
        self::assertSame('full_exam_finish_sequential', $sequential['scenario_key']);

        $batch = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
            'submit_mode' => 'all',
            'enable_batch_submit' => '1',
        ]);
        self::assertSame('full_exam_finish_batch', $batch['scenario_key']);
    }

    #[RunInSeparateProcess]
    public function test_normalize_load_test_job_backfills_scenario_for_legacy_profiles(): void
    {
        $job = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_job([
            'id' => 'job_legacy',
            'exam_title' => 'LEGACY',
            'profile' => [
                'submit_mode' => 'all',
                'enable_batch_submit' => 0,
                'vus' => 25,
                'iterations' => 2,
                'questions_per_user' => 15,
            ],
        ]);

        self::assertSame('full_exam_finish_sequential', $job['profile']['scenario_key']);
        self::assertSame('Full Exam + Finish (Sequential)', $job['profile']['scenario_label']);
        self::assertSame('flat_iterations', $job['profile']['load_shape']);
        self::assertSame(25, $job['profile']['effective_vus']);
    }

    #[RunInSeparateProcess]
    public function test_load_test_workspace_config_and_command_preview_include_scenario_key(): void
    {
        $method = new \ReflectionMethod(\CBT_Admin_Maintenance_Load_Test_Service::class, 'prepare_load_test_job_workspace');
        $method->setAccessible(true);

        $runtimeRoot = sys_get_temp_dir() . '/cbt-load-test-' . uniqid('', true);
        mkdir($runtimeRoot, 0777, true);

        try {
            $profile = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
                'profile_preset' => 'smoke_50',
                'scenario_key' => 'submit_sequential_only',
                'questions_per_user' => 12,
            ]);

            $result = $method->invoke(
                null,
                $runtimeRoot,
                'jobscenario001',
                [
                    ['identifier' => 'siswa01', 'password' => 'secret'],
                ],
                [
                    'id' => 99,
                    'title' => 'TRY OUT SCI',
                    'subject_name' => 'Science',
                ],
                $profile,
                'http://localhost',
                'TOKEN123',
                'manual',
                'k6'
            );

            self::assertIsArray($result);
            self::assertStringContainsString("LOAD_SHAPE='flat_iterations'", (string) $result['command_preview']);
            self::assertStringContainsString("SCENARIO_KEY='submit_sequential_only'", (string) $result['command_preview']);

            $configPath = (string) $result['workspace'] . '/config.json';
            self::assertFileExists($configPath);

            $config = json_decode((string) file_get_contents($configPath), true);
            self::assertIsArray($config);
            self::assertSame('submit_sequential_only', $config['scenario']['key']);
            self::assertSame('Submit Sequential Only', $config['scenario']['label']);
            self::assertSame('flat_iterations', $config['profile']['load_shape']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($runtimeRoot, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $entry) {
                    if ($entry->isDir()) {
                        @rmdir($entry->getPathname());
                    } else {
                        @unlink($entry->getPathname());
                    }
                }
                @rmdir($runtimeRoot);
            }
        }
    }

    #[RunInSeparateProcess]
    public function test_load_test_workspace_config_and_command_preview_include_ramping_shape_fields(): void
    {
        $method = new \ReflectionMethod(\CBT_Admin_Maintenance_Load_Test_Service::class, 'prepare_load_test_job_workspace');
        $method->setAccessible(true);

        $runtimeRoot = sys_get_temp_dir() . '/cbt-load-test-ramp-' . uniqid('', true);
        mkdir($runtimeRoot, 0777, true);

        try {
            $profile = \CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_profile([
                'profile_preset' => 'ramp_load_200',
                'scenario_key' => 'full_exam_finish_batch',
            ]);

            $result = $method->invoke(
                null,
                $runtimeRoot,
                'jobramp001',
                [
                    ['identifier' => 'siswa01', 'password' => 'secret'],
                ],
                [
                    'id' => 77,
                    'title' => 'TRY OUT ENG',
                    'subject_name' => 'English',
                ],
                $profile,
                'http://localhost',
                'TOKEN123',
                'manual',
                'k6'
            );

            self::assertIsArray($result);
            self::assertStringContainsString("LOAD_SHAPE='ramping_vus'", (string) $result['command_preview']);
            self::assertStringContainsString('PEAK_VUS=200', (string) $result['command_preview']);
            self::assertStringContainsString("RAMP_UP_DURATION='3m'", (string) $result['command_preview']);

            $configPath = (string) $result['workspace'] . '/config.json';
            self::assertFileExists($configPath);

            $config = json_decode((string) file_get_contents($configPath), true);
            self::assertIsArray($config);
            self::assertSame('ramping_vus', $config['profile']['load_shape']);
            self::assertSame(200, $config['profile']['peak_vus']);
            self::assertSame('Ramping: 1m warmup · 3m ramp-up · 6m steady · 2m ramp-down', $config['profile']['stage_summary']);
            self::assertCount(6, $config['profile']['compiled_stages']);
        } finally {
            if (is_dir($runtimeRoot)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($runtimeRoot, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $entry) {
                    if ($entry->isDir()) {
                        @rmdir($entry->getPathname());
                    } else {
                        @unlink($entry->getPathname());
                    }
                }
                @rmdir($runtimeRoot);
            }
        }
    }

    #[RunInSeparateProcess]
    public function test_build_page_context_returns_shell_context_for_default_reset_tab(): void
    {
        $context = \CBT_Admin_Maintenance_Service::build_page_context([
            'cbt_seed_preset' => 'small',
        ]);

        self::assertSame('reset', $context['active_maintenance_tab']);
        self::assertArrayHasKey('active_tab_context', $context);
        self::assertArrayHasKey('reset_progress_status_label', $context);
        self::assertArrayHasKey('seed_progress_preset_label', $context);
        self::assertArrayHasKey('load_test_job_count', $context);
        self::assertArrayHasKey('hero_live_value', $context);
        self::assertArrayHasKey('reset_progress_status_label', $context['active_tab_context']);
        self::assertArrayNotHasKey('seed_presets_json', $context);
        self::assertArrayNotHasKey('load_test_exam_catalog', $context);
    }

    #[RunInSeparateProcess]
    public function test_build_page_context_load_tab_scopes_load_data_to_active_tab_context(): void
    {
        $context = \CBT_Admin_Maintenance_Service::build_page_context([
            'cbt_maintenance_tab' => 'load',
        ]);

        self::assertSame('load', $context['active_maintenance_tab']);
        self::assertArrayHasKey('load_test_exam_catalog', $context['active_tab_context']);
        self::assertArrayHasKey('load_test_runtime', $context['active_tab_context']);
        self::assertArrayNotHasKey('load_test_exam_catalog', $context);
    }

    #[RunInSeparateProcess]
    public function test_maintenance_service_is_now_thin_facade_without_internal_bridges(): void
    {
        $maintenance = new \ReflectionClass(\CBT_Admin_Maintenance_Service::class);
        $maintenance_method_names = array_map(
            static fn(\ReflectionMethod $method): string => $method->getName(),
            $maintenance->getMethods()
        );

        foreach ($maintenance_method_names as $method_name) {
            self::assertStringStartsNotWith('internal_', $method_name);
        }

        self::assertTrue($maintenance->hasMethod('handle_reset_database'));
        self::assertTrue($maintenance->hasMethod('handle_generate_test_dataset'));
        self::assertTrue($maintenance->hasMethod('handle_start_load_test'));

        self::assertTrue((new \ReflectionClass(\CBT_Admin_Maintenance_Reset_Service::class))->hasMethod('handle_reset_database'));
        self::assertTrue((new \ReflectionClass(\CBT_Admin_Maintenance_Seed_Service::class))->hasMethod('handle_generate_test_dataset'));
        self::assertTrue((new \ReflectionClass(\CBT_Admin_Maintenance_Load_Test_Service::class))->hasMethod('handle_start_load_test'));
        self::assertTrue((new \ReflectionClass(\CBT_Admin_Maintenance_Load_Test_Service::class))->hasMethod('handle_load_test_jobs_ajax'));
    }
}

final class MaintenanceModularizationFakeWpdb extends \wpdb
{
    /**
     * @param mixed ...$args
     * @return array{query:string,args:array<int,mixed>}
     */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /**
     * @param array{query:string,args:array<int,mixed>}|string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = ARRAY_A): array
    {
        return [];
    }
}
