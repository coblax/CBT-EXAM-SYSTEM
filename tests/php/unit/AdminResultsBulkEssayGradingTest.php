<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminResultsBulkEssayGradingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_user_caps'] = [];
        $GLOBALS['cbt_test_current_user_id'] = 7;
        $GLOBALS['cbt_test_last_redirect'] = null;
        $GLOBALS['cbt_test_last_ajax_response'] = null;
        $GLOBALS['cbt_test_wp_options'] = [];
        $GLOBALS['cbt_test_wp_transients'] = [];
        $GLOBALS['cbt_test_wp_remote_get_map'] = [];
        $GLOBALS['cbt_test_wp_remote_post_map'] = [];
        $GLOBALS['cbt_test_wp_remote_post_log'] = [];
    }

    #[RunInSeparateProcess]
    public function test_teacher_scope_limits_essay_questions_to_owned_exam(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 7;

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan proses fotosintesis pada daun.',
                    'points' => 5,
                    'rubric_text' => 'Menyebut cahaya, klorofil, CO2, air, dan glukosa.',
                ],
            ],
        ]);

        $questions = CBT_Admin_Results_Service::get_exam_essay_questions(44, false, 7);

        self::assertCount(1, $questions);
        self::assertSame(55, $questions[0]['id']);
        self::assertStringContainsString('ex.created_by = %d', $wpdb->lastPreparedQuery);
        self::assertContains(7, $wpdb->lastPreparedArgs);
    }

    #[RunInSeparateProcess]
    public function test_workspace_returns_completed_attempts_only_and_marks_empty_answer(): void
    {
        $this->bootstrapResultsService();

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'answers' => [
                [
                    'answer_id' => 901,
                    'answer_text' => 'Karena terjadi perubahan energi.',
                    'is_correct' => 0,
                    'score_awarded' => 2.5,
                    'answer_updated_at' => '2026-04-30 10:00:00',
                    'attempt_id' => 301,
                    'student_id' => 81,
                    'exam_id' => 44,
                    'attempt_score' => 20,
                    'finished_at' => '2026-04-30 10:10:00',
                    'question_id' => 55,
                    'points' => 5,
                    'question_text' => 'Jelaskan proses fotosintesis.',
                    'rubric_text' => 'Rubrik singkat',
                    'exam_title' => 'Biologi',
                    'student_name' => 'Ani Siswa',
                    'student_username' => 'ani',
                    'student_kelas' => 'X IPA 1',
                    'student_nisn' => '123',
                ],
                [
                    'answer_id' => 0,
                    'answer_text' => '',
                    'is_correct' => null,
                    'score_awarded' => null,
                    'attempt_id' => 302,
                    'student_id' => 82,
                    'exam_id' => 44,
                    'finished_at' => '2026-04-30 10:15:00',
                    'question_id' => 55,
                    'points' => 5,
                    'question_text' => 'Jelaskan proses fotosintesis.',
                    'rubric_text' => '',
                    'exam_title' => 'Biologi',
                    'student_name' => 'Budi Siswa',
                    'student_username' => 'budi',
                    'student_kelas' => 'X IPA 1',
                    'student_nisn' => '124',
                ],
            ],
        ]);

        $rows = CBT_Admin_Results_Service::get_student_answers_for_essay_question(44, 55, [
            'is_admin_scope' => true,
            'current_user_id' => 7,
        ]);

        self::assertCount(2, $rows);
        self::assertSame('graded', $rows[0]['status_key']);
        self::assertSame('empty', $rows[1]['status_key']);
        self::assertNotEmpty(array_filter($wpdb->preparedQueries, static function (string $query): bool {
            return str_contains($query, "a.status = 'completed'");
        }));
    }

    #[RunInSeparateProcess]
    public function test_bulk_grade_updates_changed_scores_recalculates_attempts_and_invalidates_cache(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 7;

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan proses fotosintesis.',
                    'points' => 5,
                    'rubric_text' => '',
                ],
            ],
            'bulk_answer_rows' => [
                901 => [
                    'answer_id' => 901,
                    'attempt_id' => 301,
                    'student_id' => 81,
                    'exam_id' => 44,
                    'score_awarded' => 2.0,
                    'points' => 5,
                ],
                902 => [
                    'answer_id' => 902,
                    'attempt_id' => 302,
                    'student_id' => 82,
                    'exam_id' => 44,
                    'score_awarded' => 3.0,
                    'points' => 5,
                ],
            ],
            'attempt_sums' => [
                301 => 4.5,
            ],
        ]);

        $_POST = [
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '55',
            'cbt_essay_kelas' => 'X IPA 1',
            'cbt_essay_q' => 'Ani',
            'essay_scores' => [
                '901' => '4.5',
                '902' => '3.0',
            ],
        ];

        try {
            CBT_Admin_Results_Service::handle_bulk_grade_essay();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        self::assertCount(2, $wpdb->updates);
        self::assertSame('wp_cbt_answers', $wpdb->updates[0]['table']);
        self::assertSame(901, $wpdb->updates[0]['where']['id']);
        self::assertSame(4.5, $wpdb->updates[0]['data']['score_awarded']);
        self::assertSame('wp_cbt_attempts', $wpdb->updates[1]['table']);
        self::assertSame(301, $wpdb->updates[1]['where']['id']);
        self::assertSame(4.5, $wpdb->updates[1]['data']['score']);
        self::assertSame(['START TRANSACTION', 'COMMIT'], $wpdb->queries);
        self::assertSame([[301]], CBT_Cache::$invalidatedAttemptsBatches);
        self::assertSame([[81]], CBT_Cache::$invalidatedUsersBatches);
        self::assertSame([44], CBT_Cache::$invalidatedAnalyticsExamIds);
        self::assertSame(1, CBT_Cache::$invalidateAnalyticsCalls);

        $redirect = (string) ($GLOBALS['cbt_test_last_redirect'] ?? '');
        self::assertStringContainsString('cbt_results_tab=essay', $redirect);
        self::assertStringContainsString('cbt_essay_exam_id=44', $redirect);
        self::assertStringContainsString('cbt_essay_question_id=55', $redirect);
    }

    #[RunInSeparateProcess]
    public function test_bulk_grade_rejects_invalid_score_without_updates(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan proses fotosintesis.',
                    'points' => 5,
                    'rubric_text' => '',
                ],
            ],
            'bulk_answer_rows' => [
                901 => [
                    'answer_id' => 901,
                    'attempt_id' => 301,
                    'student_id' => 81,
                    'exam_id' => 44,
                    'score_awarded' => 2.0,
                    'points' => 5,
                ],
            ],
        ]);

        $_POST = [
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '55',
            'essay_scores' => [
                '901' => '9.0',
            ],
        ];

        try {
            CBT_Admin_Results_Service::handle_bulk_grade_essay();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([], $wpdb->updates);
        self::assertStringContainsString('nilai+essay+invalid', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_bulk_grade_rejects_unauthorized_user(): void
    {
        $this->bootstrapResultsService();

        $_POST = [
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '55',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized');

        CBT_Admin_Results_Service::handle_bulk_grade_essay();
    }

    #[RunInSeparateProcess]
    public function test_exam_scope_ai_auto_applies_ungraded_answers_only_across_questions(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 7;
        $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings'] = [
            'provider' => 'gemini',
            'enabled' => true,
            'model' => 'gemini-2.5-flash-lite',
            'api_key' => 'gemini-key',
            'timeout' => 10,
            'batch_limit' => 20,
        ];

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan fotosintesis.',
                    'points' => 5,
                    'rubric_text' => 'Menyebut cahaya dan klorofil.',
                ],
                [
                    'id' => 56,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan demokrasi.',
                    'points' => 4,
                    'rubric_text' => 'Menyebut partisipasi rakyat.',
                ],
            ],
            'answers_by_question' => [
                55 => [
                    [
                        'answer_id' => 901,
                        'answer_text' => 'Fotosintesis memakai cahaya dan klorofil.',
                        'is_correct' => null,
                        'score_awarded' => 0.0,
                        'attempt_id' => 301,
                        'student_id' => 81,
                        'exam_id' => 44,
                        'question_id' => 55,
                        'points' => 5,
                        'question_text' => 'Jelaskan fotosintesis.',
                        'rubric_text' => 'Menyebut cahaya dan klorofil.',
                    ],
                    [
                        'answer_id' => 903,
                        'answer_text' => 'Sudah diberi nilai manual nol.',
                        'is_correct' => 0,
                        'score_awarded' => 0.0,
                        'attempt_id' => 303,
                        'student_id' => 83,
                        'exam_id' => 44,
                        'question_id' => 55,
                        'points' => 5,
                        'question_text' => 'Jelaskan fotosintesis.',
                        'rubric_text' => 'Menyebut cahaya dan klorofil.',
                    ],
                ],
                56 => [
                    [
                        'answer_id' => 902,
                        'answer_text' => 'Demokrasi melibatkan rakyat dalam pemilihan.',
                        'is_correct' => null,
                        'score_awarded' => 0.0,
                        'attempt_id' => 302,
                        'student_id' => 82,
                        'exam_id' => 44,
                        'question_id' => 56,
                        'points' => 4,
                        'question_text' => 'Jelaskan demokrasi.',
                        'rubric_text' => 'Menyebut partisipasi rakyat.',
                    ],
                ],
            ],
            'attempt_sums' => [
                301 => 4.25,
                302 => 3.0,
            ],
        ]);

        $endpoint = CBT_Essay_AI_Grading_Service::build_gemini_endpoint_hint('gemini-2.5-flash-lite');
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = static function ($url, array $args): array {
            $body = json_decode((string) ($args['body'] ?? ''), true);
            $prompt = (string) ($body['contents'][0]['parts'][0]['text'] ?? '');
            $score = str_contains($prompt, 'demokrasi') ? 3.0 : 4.25;
            $confidence = str_contains($prompt, 'demokrasi') ? 0.4 : 0.9;

            return [
                'response' => ['code' => 200],
                'body' => wp_json_encode([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => wp_json_encode([
                                            'suggested_score' => $score,
                                            'confidence' => $confidence,
                                            'feedback_internal' => 'Cukup sesuai rubrik.',
                                            'rubric_breakdown' => ['Sesuai rubrik inti'],
                                            'flags' => [],
                                            'needs_manual_review' => $confidence < 0.65,
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ];
        };

        $_POST = [
            'nonce' => 'ok',
            'scope' => 'exam',
            'auto_apply' => '1',
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '0',
            'cbt_essay_kelas' => 'X IPA 1',
            'cbt_essay_q' => '',
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_start_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $startPayload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertSame('exam', $startPayload['scope']);
        self::assertTrue((bool) $startPayload['auto_apply']);
        self::assertSame(2, $startPayload['total']);

        $_POST = [
            'nonce' => 'ok',
            'token' => (string) ($startPayload['token'] ?? ''),
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_tick_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $tickPayload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertTrue((bool) $tickPayload['complete']);
        self::assertTrue((bool) $tickPayload['auto_apply_done']);
        self::assertSame(2, $tickPayload['success_count']);
        self::assertSame(2, $tickPayload['applied_count']);
        self::assertSame(0, $tickPayload['auto_apply_skipped_count']);
        self::assertCount(2, $GLOBALS['cbt_test_wp_remote_post_log']);

        $answerUpdates = array_values(array_filter($wpdb->updates, static function (array $update): bool {
            return $update['table'] === 'wp_cbt_answers';
        }));
        self::assertCount(2, $answerUpdates);
        self::assertSame([901, 902], array_column(array_column($answerUpdates, 'where'), 'id'));
        self::assertSame(4.25, $answerUpdates[0]['data']['score_awarded']);
        self::assertSame(3.0, $answerUpdates[1]['data']['score_awarded']);
        self::assertNotContains(903, array_column(array_column($answerUpdates, 'where'), 'id'));
        self::assertSame([[301, 302]], CBT_Cache::$invalidatedAttemptsBatches);
        self::assertSame([[81, 82]], CBT_Cache::$invalidatedUsersBatches);
        self::assertSame([44], CBT_Cache::$invalidatedAnalyticsExamIds);
    }

    #[RunInSeparateProcess]
    public function test_question_scope_ai_still_creates_recommendations_without_auto_apply(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings'] = [
            'provider' => 'gemini',
            'enabled' => true,
            'model' => 'gemini-2.5-flash-lite',
            'api_key' => 'gemini-key',
            'timeout' => 10,
            'batch_limit' => 20,
        ];

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan fotosintesis.',
                    'points' => 5,
                    'rubric_text' => 'Menyebut cahaya dan klorofil.',
                ],
            ],
            'answers' => [
                [
                    'answer_id' => 901,
                    'answer_text' => 'Fotosintesis memakai cahaya dan klorofil.',
                    'is_correct' => null,
                    'score_awarded' => 0.0,
                    'attempt_id' => 301,
                    'student_id' => 81,
                    'exam_id' => 44,
                    'question_id' => 55,
                    'points' => 5,
                    'question_text' => 'Jelaskan fotosintesis.',
                    'rubric_text' => 'Menyebut cahaya dan klorofil.',
                ],
            ],
        ]);

        $endpoint = CBT_Essay_AI_Grading_Service::build_gemini_endpoint_hint('gemini-2.5-flash-lite');
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => wp_json_encode([
                                        'suggested_score' => 4,
                                        'confidence' => 0.9,
                                        'feedback_internal' => 'Sesuai.',
                                        'rubric_breakdown' => ['Sesuai'],
                                        'flags' => [],
                                        'needs_manual_review' => false,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        $_POST = [
            'nonce' => 'ok',
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '55',
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_start_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $startPayload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        $_POST = [
            'nonce' => 'ok',
            'token' => (string) ($startPayload['token'] ?? ''),
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_tick_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $tickPayload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertTrue((bool) $tickPayload['complete']);
        self::assertFalse((bool) $tickPayload['auto_apply']);
        self::assertSame(0, $tickPayload['applied_count']);
        self::assertSame([], array_values(array_filter($wpdb->updates, static function (array $update): bool {
            return $update['table'] === 'wp_cbt_answers';
        })));
        self::assertArrayHasKey(901, $wpdb->suggestionRows);
    }

    #[RunInSeparateProcess]
    public function test_question_scope_ai_retry_failed_only_targets_fresh_failed_suggestions_without_auto_apply(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings'] = [
            'provider' => 'gemini',
            'enabled' => true,
            'model' => 'gemini-2.5-flash-lite',
            'api_key' => 'gemini-key',
            'timeout' => 10,
            'batch_limit' => 20,
        ];

        $failedFresh = [
            'answer_id' => 901,
            'answer_text' => 'Fotosintesis memakai cahaya dan klorofil.',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 301,
            'student_id' => 81,
            'exam_id' => 44,
            'question_id' => 55,
            'points' => 5,
            'question_text' => 'Jelaskan fotosintesis.',
            'rubric_text' => 'Menyebut cahaya dan klorofil.',
        ];
        $successFresh = [
            'answer_id' => 902,
            'answer_text' => 'Fotosintesis menghasilkan glukosa.',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 302,
            'student_id' => 82,
            'exam_id' => 44,
            'question_id' => 55,
            'points' => 5,
            'question_text' => 'Jelaskan fotosintesis.',
            'rubric_text' => 'Menyebut cahaya dan klorofil.',
        ];
        $failedStale = [
            'answer_id' => 903,
            'answer_text' => 'Jawaban sudah diubah setelah AI gagal.',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 303,
            'student_id' => 83,
            'exam_id' => 44,
            'question_id' => 55,
            'points' => 5,
            'question_text' => 'Jelaskan fotosintesis.',
            'rubric_text' => 'Menyebut cahaya dan klorofil.',
        ];
        $notProcessed = [
            'answer_id' => 904,
            'answer_text' => 'Belum pernah diproses AI.',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 304,
            'student_id' => 84,
            'exam_id' => 44,
            'question_id' => 55,
            'points' => 5,
            'question_text' => 'Jelaskan fotosintesis.',
            'rubric_text' => 'Menyebut cahaya dan klorofil.',
        ];
        $emptyAnswer = [
            'answer_id' => 905,
            'answer_text' => '',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 305,
            'student_id' => 85,
            'exam_id' => 44,
            'question_id' => 55,
            'points' => 5,
            'question_text' => 'Jelaskan fotosintesis.',
            'rubric_text' => 'Menyebut cahaya dan klorofil.',
        ];

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan fotosintesis.',
                    'points' => 5,
                    'rubric_text' => 'Menyebut cahaya dan klorofil.',
                ],
            ],
            'answers_by_question' => [
                55 => [$failedFresh, $successFresh, $failedStale, $notProcessed, $emptyAnswer],
            ],
            'suggestions' => [
                901 => [
                    'id' => 901,
                    'answer_id' => 901,
                    'attempt_id' => 301,
                    'exam_id' => 44,
                    'question_id' => 55,
                    'status' => 'failed',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash($failedFresh),
                    'error_message' => 'Request AI sebelumnya gagal.',
                    'updated_at' => '2026-03-24 10:00:00',
                ],
                902 => [
                    'id' => 902,
                    'answer_id' => 902,
                    'attempt_id' => 302,
                    'exam_id' => 44,
                    'question_id' => 55,
                    'status' => 'success',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash($successFresh),
                    'suggested_score' => 4.0,
                    'confidence' => 0.9,
                    'needs_manual_review' => 0,
                    'updated_at' => '2026-03-24 10:00:00',
                ],
                903 => [
                    'id' => 903,
                    'answer_id' => 903,
                    'attempt_id' => 303,
                    'exam_id' => 44,
                    'question_id' => 55,
                    'status' => 'failed',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash(array_merge($failedStale, [
                        'answer_text' => 'Jawaban lama.',
                    ])),
                    'error_message' => 'Gagal lama, tetapi konten sudah berubah.',
                    'updated_at' => '2026-03-24 10:00:00',
                ],
                905 => [
                    'id' => 905,
                    'answer_id' => 905,
                    'attempt_id' => 305,
                    'exam_id' => 44,
                    'question_id' => 55,
                    'status' => 'failed',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash($emptyAnswer),
                    'error_message' => 'Jawaban kosong tidak boleh diproses.',
                    'updated_at' => '2026-03-24 10:00:00',
                ],
            ],
        ]);

        $endpoint = CBT_Essay_AI_Grading_Service::build_gemini_endpoint_hint('gemini-2.5-flash-lite');
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => wp_json_encode([
                                        'suggested_score' => 4.5,
                                        'confidence' => 0.88,
                                        'feedback_internal' => 'Sudah sesuai rubrik.',
                                        'rubric_breakdown' => ['Sesuai'],
                                        'flags' => [],
                                        'needs_manual_review' => false,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        $_POST = [
            'nonce' => 'ok',
            'retry_mode' => 'failed_only',
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '55',
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_start_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $startPayload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertSame('failed_only', $startPayload['retry_mode']);
        self::assertSame(1, $startPayload['total']);
        self::assertStringContainsString('rekomendasi AI gagal', (string) $startPayload['message']);

        $_POST = [
            'nonce' => 'ok',
            'token' => (string) ($startPayload['token'] ?? ''),
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_tick_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $tickPayload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertTrue((bool) $tickPayload['complete']);
        self::assertFalse((bool) $tickPayload['auto_apply']);
        self::assertSame(1, $tickPayload['success_count']);
        self::assertSame(0, $tickPayload['applied_count']);
        self::assertCount(1, $GLOBALS['cbt_test_wp_remote_post_log']);
        self::assertSame('success', $wpdb->suggestionRows[901]['status']);
        self::assertSame('success', $wpdb->suggestionRows[902]['status']);
        self::assertSame('failed', $wpdb->suggestionRows[903]['status']);
        self::assertSame([], array_values(array_filter($wpdb->updates, static function (array $update): bool {
            return $update['table'] === 'wp_cbt_answers';
        })));
    }

    #[RunInSeparateProcess]
    public function test_exam_scope_ai_retry_failed_only_auto_applies_successful_ungraded_answers_only(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 7;
        $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings'] = [
            'provider' => 'gemini',
            'enabled' => true,
            'model' => 'gemini-2.5-flash-lite',
            'api_key' => 'gemini-key',
            'timeout' => 10,
            'batch_limit' => 20,
        ];

        $failedPhoto = [
            'answer_id' => 911,
            'answer_text' => 'Fotosintesis memakai cahaya dan klorofil.',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 311,
            'student_id' => 91,
            'exam_id' => 44,
            'question_id' => 55,
            'points' => 5,
            'question_text' => 'Jelaskan fotosintesis.',
            'rubric_text' => 'Menyebut cahaya dan klorofil.',
        ];
        $manualZero = [
            'answer_id' => 912,
            'answer_text' => 'Sudah dinilai manual nol.',
            'is_correct' => 0,
            'score_awarded' => 0.0,
            'attempt_id' => 312,
            'student_id' => 92,
            'exam_id' => 44,
            'question_id' => 55,
            'points' => 5,
            'question_text' => 'Jelaskan fotosintesis.',
            'rubric_text' => 'Menyebut cahaya dan klorofil.',
        ];
        $failedDemocracy = [
            'answer_id' => 913,
            'answer_text' => 'Demokrasi melibatkan rakyat dalam pemilihan.',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 313,
            'student_id' => 93,
            'exam_id' => 44,
            'question_id' => 56,
            'points' => 4,
            'question_text' => 'Jelaskan demokrasi.',
            'rubric_text' => 'Menyebut partisipasi rakyat.',
        ];
        $successFresh = [
            'answer_id' => 914,
            'answer_text' => 'Rakyat ikut memilih pemimpin.',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 314,
            'student_id' => 94,
            'exam_id' => 44,
            'question_id' => 56,
            'points' => 4,
            'question_text' => 'Jelaskan demokrasi.',
            'rubric_text' => 'Menyebut partisipasi rakyat.',
        ];
        $failedStale = [
            'answer_id' => 915,
            'answer_text' => 'Jawaban demokrasi sudah diperbarui.',
            'is_correct' => null,
            'score_awarded' => 0.0,
            'attempt_id' => 315,
            'student_id' => 95,
            'exam_id' => 44,
            'question_id' => 56,
            'points' => 4,
            'question_text' => 'Jelaskan demokrasi.',
            'rubric_text' => 'Menyebut partisipasi rakyat.',
        ];

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan fotosintesis.',
                    'points' => 5,
                    'rubric_text' => 'Menyebut cahaya dan klorofil.',
                ],
                [
                    'id' => 56,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan demokrasi.',
                    'points' => 4,
                    'rubric_text' => 'Menyebut partisipasi rakyat.',
                ],
            ],
            'answers_by_question' => [
                55 => [$failedPhoto, $manualZero],
                56 => [$failedDemocracy, $successFresh, $failedStale],
            ],
            'suggestions' => [
                911 => [
                    'id' => 911,
                    'answer_id' => 911,
                    'attempt_id' => 311,
                    'exam_id' => 44,
                    'question_id' => 55,
                    'status' => 'failed',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash($failedPhoto),
                    'error_message' => 'Request AI sebelumnya gagal.',
                    'updated_at' => '2026-03-24 10:00:00',
                ],
                912 => [
                    'id' => 912,
                    'answer_id' => 912,
                    'attempt_id' => 312,
                    'exam_id' => 44,
                    'question_id' => 55,
                    'status' => 'failed',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash($manualZero),
                    'error_message' => 'Tidak boleh menimpa nilai manual.',
                    'updated_at' => '2026-03-24 10:00:00',
                ],
                913 => [
                    'id' => 913,
                    'answer_id' => 913,
                    'attempt_id' => 313,
                    'exam_id' => 44,
                    'question_id' => 56,
                    'status' => 'failed',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash($failedDemocracy),
                    'error_message' => 'Request AI sebelumnya gagal.',
                    'updated_at' => '2026-03-24 10:00:00',
                ],
                914 => [
                    'id' => 914,
                    'answer_id' => 914,
                    'attempt_id' => 314,
                    'exam_id' => 44,
                    'question_id' => 56,
                    'status' => 'success',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash($successFresh),
                    'suggested_score' => 3.5,
                    'confidence' => 0.92,
                    'needs_manual_review' => 0,
                    'updated_at' => '2026-03-24 10:00:00',
                ],
                915 => [
                    'id' => 915,
                    'answer_id' => 915,
                    'attempt_id' => 315,
                    'exam_id' => 44,
                    'question_id' => 56,
                    'status' => 'failed',
                    'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash(array_merge($failedStale, [
                        'answer_text' => 'Jawaban lama demokrasi.',
                    ])),
                    'error_message' => 'Konten sudah stale.',
                    'updated_at' => '2026-03-24 10:00:00',
                ],
            ],
            'attempt_sums' => [
                311 => 4.75,
                313 => 2.25,
            ],
        ]);

        $endpoint = CBT_Essay_AI_Grading_Service::build_gemini_endpoint_hint('gemini-2.5-flash-lite');
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = static function ($url, array $args): array {
            $body = json_decode((string) ($args['body'] ?? ''), true);
            $prompt = (string) ($body['contents'][0]['parts'][0]['text'] ?? '');
            $isDemocracy = str_contains($prompt, 'Demokrasi melibatkan rakyat');

            return [
                'response' => ['code' => 200],
                'body' => wp_json_encode([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => wp_json_encode([
                                            'suggested_score' => $isDemocracy ? 2.25 : 4.75,
                                            'confidence' => $isDemocracy ? 0.42 : 0.91,
                                            'feedback_internal' => 'Retry berhasil.',
                                            'rubric_breakdown' => ['Sesuai rubrik inti'],
                                            'flags' => [],
                                            'needs_manual_review' => $isDemocracy,
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ];
        };

        $_POST = [
            'nonce' => 'ok',
            'scope' => 'exam',
            'auto_apply' => '1',
            'retry_mode' => 'failed_only',
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '0',
            'cbt_essay_kelas' => 'X IPA 1',
            'cbt_essay_q' => 'Ani',
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_start_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $startPayload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertSame('exam', $startPayload['scope']);
        self::assertSame('failed_only', $startPayload['retry_mode']);
        self::assertTrue((bool) $startPayload['auto_apply']);
        self::assertSame(2, $startPayload['total']);
        self::assertNotEmpty(array_filter($wpdb->preparedQueries, static function (string $query): bool {
            return str_contains($query, 'kelas_meta.meta_value = %s')
                && str_contains($query, 'u.display_name LIKE %s');
        }));

        $_POST = [
            'nonce' => 'ok',
            'token' => (string) ($startPayload['token'] ?? ''),
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_tick_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $tickPayload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertTrue((bool) $tickPayload['complete']);
        self::assertTrue((bool) $tickPayload['auto_apply_done']);
        self::assertSame(2, $tickPayload['success_count']);
        self::assertSame(2, $tickPayload['applied_count']);
        self::assertSame(0, $tickPayload['auto_apply_skipped_count']);
        self::assertCount(2, $GLOBALS['cbt_test_wp_remote_post_log']);
        self::assertSame('success', $wpdb->suggestionRows[911]['status']);
        self::assertSame('success', $wpdb->suggestionRows[913]['status']);
        self::assertSame(1, $wpdb->suggestionRows[913]['needs_manual_review']);
        self::assertSame('failed', $wpdb->suggestionRows[912]['status']);
        self::assertSame('success', $wpdb->suggestionRows[914]['status']);
        self::assertSame('failed', $wpdb->suggestionRows[915]['status']);

        $answerUpdates = array_values(array_filter($wpdb->updates, static function (array $update): bool {
            return $update['table'] === 'wp_cbt_answers';
        }));
        self::assertCount(2, $answerUpdates);
        self::assertSame([911, 913], array_column(array_column($answerUpdates, 'where'), 'id'));
        self::assertSame(4.75, $answerUpdates[0]['data']['score_awarded']);
        self::assertSame(2.25, $answerUpdates[1]['data']['score_awarded']);
        self::assertNotContains(912, array_column(array_column($answerUpdates, 'where'), 'id'));
        self::assertSame([[311, 313]], CBT_Cache::$invalidatedAttemptsBatches);
        self::assertSame([[91, 93]], CBT_Cache::$invalidatedUsersBatches);
        self::assertSame([44], CBT_Cache::$invalidatedAnalyticsExamIds);
    }

    #[RunInSeparateProcess]
    public function test_gemini_model_refresh_ajax_returns_listmodels_items(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings'] = [
            'provider' => 'gemini',
            'enabled' => true,
            'model' => 'gemini-2.5-flash-lite',
            'api_key' => 'gemini-key',
            'timeout' => 10,
            'batch_limit' => 3,
        ];

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models?key=gemini-key&pageSize=1000';
        $GLOBALS['cbt_test_wp_remote_get_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash-lite',
                        'displayName' => 'Gemini 2.5 Flash Lite',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                    [
                        'name' => 'models/text-embedding-004',
                        'displayName' => 'Text Embedding 004',
                        'supportedGenerationMethods' => ['embedContent'],
                    ],
                ],
            ]),
        ];

        $_POST = [
            'nonce' => 'ok',
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_models_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $payload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertSame('api', $payload['source']);
        self::assertSame(1, $payload['total']);
        self::assertSame('gemini-2.5-flash-lite', $payload['items'][0]['id'] ?? null);
        self::assertStringContainsString('Gemini 2.5 Flash Lite', (string) ($payload['items'][0]['label'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_openai_model_refresh_ajax_returns_text_model_items(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings'] = [
            'provider' => 'openai',
            'enabled' => true,
            'endpoint' => 'https://api.openai.com/v1/responses',
            'model' => 'gpt-5.4-mini',
            'api_key' => 'openai-key',
            'timeout' => 10,
            'batch_limit' => 3,
        ];

        $GLOBALS['cbt_test_wp_remote_get_map']['https://api.openai.com/v1/models'] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'object' => 'list',
                'data' => [
                    ['id' => 'gpt-5.4-mini', 'object' => 'model'],
                    ['id' => 'gpt-5.4', 'object' => 'model'],
                    ['id' => 'text-embedding-3-small', 'object' => 'model'],
                ],
            ]),
        ];

        $_POST = [
            'nonce' => 'ok',
            'provider' => 'openai',
        ];

        try {
            CBT_Admin_Results_Service::handle_essay_ai_models_ajax();
            self::fail('Expected ajax signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $payload = (array) ($GLOBALS['cbt_test_last_ajax_response']['payload'] ?? []);
        self::assertSame('openai', $payload['provider']);
        self::assertSame('api', $payload['source']);
        self::assertSame(2, $payload['total']);
        self::assertSame('gpt-5.4-mini', $payload['items'][0]['id'] ?? null);
        self::assertSame('gpt-5.4', $payload['items'][1]['id'] ?? null);
    }

    private function bootstrapResultsService(): void
    {
        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache
{
    public static array $invalidatedAttemptsBatches = [];
    public static array $invalidatedUsersBatches = [];
    public static array $invalidatedAnalyticsExamIds = [];
    public static int $invalidateAnalyticsCalls = 0;

    public static function invalidate_attempts(array $attempt_ids): void
    {
        self::$invalidatedAttemptsBatches[] = array_values(array_map('intval', $attempt_ids));
    }

    public static function invalidate_users(array $user_ids): void
    {
        self::$invalidatedUsersBatches[] = array_values(array_map('intval', $user_ids));
    }

    public static function invalidate_analytics(): void
    {
        self::$invalidateAnalyticsCalls++;
    }

    public static function invalidate_analytics_exam(int $exam_id): void
    {
        self::$invalidatedAnalyticsExamIds[] = $exam_id;
    }
}
PHP);
        }

        CBT_Cache::$invalidatedAttemptsBatches = [];
        CBT_Cache::$invalidatedUsersBatches = [];
        CBT_Cache::$invalidatedAnalyticsExamIds = [];
        CBT_Cache::$invalidateAnalyticsCalls = 0;

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';
    }
}

final class AdminResultsBulkEssayFakeWpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
    public string $lastPreparedQuery = '';

    /** @var array<int,mixed> */
    public array $lastPreparedArgs = [];

    /** @var list<string> */
    public array $preparedQueries = [];

    /** @var list<string> */
    public array $queries = [];

    /** @var list<array<string,mixed>> */
    public array $updates = [];

    /** @var array<int,array<string,mixed>> */
    public array $suggestionRows = [];

    /** @var array<int,array<string,mixed>> */
    private array $questionRows;

    /** @var array<int,array<string,mixed>> */
    private array $answerRows;

    /** @var array<int,array<int,array<string,mixed>>> */
    private array $answersByQuestion;

    /** @var array<int,array<string,mixed>> */
    private array $bulkAnswerRows;

    /** @var array<int,array<string,mixed>> */
    private array $autoApplyAnswerRows;

    /** @var array<int,float> */
    private array $attemptSums;

    /**
     * @param array<string,mixed> $fixtures
     */
    public function __construct(array $fixtures = [])
    {
        $this->questionRows = array_values((array) ($fixtures['questions'] ?? []));
        $this->answerRows = array_values((array) ($fixtures['answers'] ?? []));
        $this->answersByQuestion = (array) ($fixtures['answers_by_question'] ?? []);
        $this->bulkAnswerRows = (array) ($fixtures['bulk_answer_rows'] ?? []);
        $this->autoApplyAnswerRows = (array) ($fixtures['auto_apply_answer_rows'] ?? []);
        $this->suggestionRows = (array) ($fixtures['suggestions'] ?? []);
        $this->attemptSums = (array) ($fixtures['attempt_sums'] ?? []);

        foreach ($this->answerRows as $answerRow) {
            $answerId = (int) ($answerRow['answer_id'] ?? 0);
            if ($answerId > 0 && !isset($this->autoApplyAnswerRows[$answerId])) {
                $this->autoApplyAnswerRows[$answerId] = $answerRow;
            }
        }
        foreach ($this->answersByQuestion as $questionRows) {
            foreach ((array) $questionRows as $answerRow) {
                $answerId = (int) ($answerRow['answer_id'] ?? 0);
                if ($answerId > 0 && !isset($this->autoApplyAnswerRows[$answerId])) {
                    $this->autoApplyAnswerRows[$answerId] = $answerRow;
                }
            }
        }
    }

    /**
     * @return array{query:string,args:array<int,mixed>}
     */
    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $this->lastPreparedQuery = $query;
        $this->lastPreparedArgs = array_values($args);
        $this->preparedQueries[] = $query;

        return [
            'query' => $query,
            'args' => array_values($args),
        ];
    }

    public function esc_like(string $text): string
    {
        return addslashes($text);
    }

    /**
     * @param array<string,mixed>|string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? array_values((array) ($prepared['args'] ?? [])) : [];

        if (str_contains($query, 'FROM wp_cbt_essay_ai_suggestions')) {
            $rows = [];
            foreach ($args as $answerId) {
                $answerId = (int) $answerId;
                if (isset($this->suggestionRows[$answerId])) {
                    $rows[] = $this->suggestionRows[$answerId];
                }
            }

            return $rows;
        }

        if (str_contains($query, 'FROM wp_cbt_questions q') && str_contains($query, "q.question_type = 'essay'")) {
            return $this->questionRows;
        }

        if (str_contains($query, 'LEFT JOIN wp_cbt_answers ans') && str_contains($query, "a.status = 'completed'")) {
            $questionId = isset($args[1]) ? (int) $args[1] : 0;
            if ($questionId > 0 && isset($this->answersByQuestion[$questionId])) {
                return array_values((array) $this->answersByQuestion[$questionId]);
            }

            return $this->answerRows;
        }

        if (str_contains($query, 'FROM wp_cbt_answers ans') && str_contains($query, 'COALESCE(q.is_active, 1) = 1')) {
            $rows = [];
            foreach ($args as $arg) {
                $answerId = (int) $arg;
                if (isset($this->autoApplyAnswerRows[$answerId])) {
                    $rows[] = $this->autoApplyAnswerRows[$answerId];
                }
            }

            return $rows;
        }

        if (str_contains($query, 'FROM wp_cbt_answers ans') && str_contains($query, "att.status = 'completed'")) {
            $requestedIds = array_slice($args, 0, max(0, count($args) - 2));
            $rows = [];
            foreach ($requestedIds as $answerId) {
                $answerId = (int) $answerId;
                if (isset($this->bulkAnswerRows[$answerId])) {
                    $rows[] = $this->bulkAnswerRows[$answerId];
                }
            }

            return $rows;
        }

        return [];
    }

    /**
     * @param array<string,mixed>|string $prepared
     */
    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? array_values((array) ($prepared['args'] ?? [])) : [];
        if (str_contains($query, 'SELECT id FROM wp_cbt_essay_ai_suggestions')) {
            $answerId = isset($args[0]) ? (int) $args[0] : 0;

            return isset($this->suggestionRows[$answerId]) ? (int) ($this->suggestionRows[$answerId]['id'] ?? $answerId) : 0;
        }

        $attemptId = isset($args[0]) ? (int) $args[0] : 0;

        return $this->attemptSums[$attemptId] ?? 0.0;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     * @param array<int,string>|null $format
     * @param array<int,string>|null $where_format
     */
    public function update(string $table, array $data, array $where, ?array $format = null, ?array $where_format = null)
    {
        $this->updates[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
        ];

        if ($table === 'wp_cbt_essay_ai_suggestions') {
            $answerId = (int) ($data['answer_id'] ?? 0);
            if ($answerId > 0) {
                $this->suggestionRows[$answerId] = array_merge($this->suggestionRows[$answerId] ?? [], $data);
            }
        }

        return 1;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(string $table, array $data, ?array $format = null)
    {
        if ($table === 'wp_cbt_essay_ai_suggestions') {
            $answerId = (int) ($data['answer_id'] ?? 0);
            if ($answerId > 0) {
                $data['id'] = $data['id'] ?? $answerId;
                $this->suggestionRows[$answerId] = $data;
            }
        }

        return 1;
    }

    public function query($query)
    {
        $this->queries[] = is_array($query) ? (string) ($query['query'] ?? '') : (string) $query;

        return true;
    }
}
