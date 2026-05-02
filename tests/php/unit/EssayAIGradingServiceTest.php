<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

final class EssayAIGradingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-essay-ai-grading-service.php';
    }

    public function test_grade_answer_clamps_score_and_omits_student_identity_from_prompt(): void
    {
        $endpoint = 'https://api.openai.com/v1/responses';
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => wp_json_encode([
                                    'suggested_score' => 9,
                                    'confidence' => 0.5,
                                    'feedback_internal' => 'Jawaban menyebut konsep utama, tetapi kurang detail.',
                                    'rubric_breakdown' => ['Konsep utama ada', 'Detail proses kurang'],
                                    'flags' => [],
                                    'needs_manual_review' => false,
                                ]),
                            ],
                        ],
                    ],
                ],
            ]),
        ];

        $result = CBT_Essay_AI_Grading_Service::grade_answer([
            'answer_id' => 10,
            'attempt_id' => 20,
            'exam_id' => 30,
            'question_id' => 40,
            'question_text' => 'Jelaskan fotosintesis.',
            'rubric_text' => 'Wajib menyebut cahaya, klorofil, CO2, air, dan glukosa.',
            'answer_text' => 'Fotosintesis membutuhkan cahaya dan klorofil.',
            'max_points' => 5,
            'student_name' => 'Ani Siswa',
            'student_nisn' => '123456',
        ], [
            'provider' => 'openai',
            'endpoint' => $endpoint,
            'model' => 'mock-model',
            'api_key' => 'test-key',
            'timeout' => 10,
        ]);

        self::assertTrue((bool) ($result['ok'] ?? false));
        self::assertSame(5.0, $result['suggested_score']);
        self::assertSame(1, $result['needs_manual_review']);
        self::assertContains('confidence_rendah', $result['flags']);

        $request = $GLOBALS['cbt_test_wp_remote_post_log'][0] ?? [];
        $body = json_decode((string) (($request['args']['body'] ?? '') ?: ''), true);
        self::assertSame('Bearer test-key', $request['args']['headers']['Authorization'] ?? '');
        self::assertSame(['type' => 'json_object'], $body['text']['format'] ?? []);
        self::assertArrayNotHasKey('messages', $body);
        self::assertStringContainsString('Balas hanya JSON valid', (string) ($body['instructions'] ?? ''));
        $prompt = (string) ($body['input'][0]['content'][0]['text'] ?? '');
        self::assertStringContainsString('Jelaskan fotosintesis.', $prompt);
        self::assertStringContainsString('Fotosintesis membutuhkan cahaya', $prompt);
        self::assertStringNotContainsString('Ani Siswa', $prompt);
        self::assertStringNotContainsString('123456', $prompt);
    }

    public function test_normalize_settings_defaults_to_gemini_flash_lite_and_supports_openai_explicitly(): void
    {
        $gemini = CBT_Essay_AI_Grading_Service::normalize_settings([
            'enabled' => true,
            'api_key' => 'gemini-key',
        ]);

        self::assertSame('gemini', $gemini['provider']);
        self::assertSame('gemini-2.5-flash-lite', $gemini['model']);
        self::assertTrue((bool) $gemini['configured']);
        self::assertTrue((bool) $gemini['gemini_has_api_key']);
        self::assertFalse((bool) $gemini['openai_has_api_key']);
        self::assertStringContainsString('gemini-2.5-flash-lite:generateContent', (string) $gemini['gemini_endpoint']);

        $openai = CBT_Essay_AI_Grading_Service::normalize_settings([
            'provider' => 'openai',
            'enabled' => true,
            'api_key' => 'openai-key',
        ]);

        self::assertSame('openai', $openai['provider']);
        self::assertSame('gpt-5.4-mini', $openai['model']);
        self::assertSame('https://api.openai.com/v1/responses', $openai['endpoint']);
        self::assertTrue((bool) $openai['configured']);
        self::assertFalse((bool) $openai['gemini_has_api_key']);
        self::assertTrue((bool) $openai['openai_has_api_key']);

        $openai_chat_completions = CBT_Essay_AI_Grading_Service::normalize_settings([
            'provider' => 'openai',
            'enabled' => true,
            'api_key' => 'openai-key',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
        ]);

        self::assertSame('https://api.openai.com/v1/chat/completions', $openai_chat_completions['endpoint']);

        $explicit_gemini = CBT_Essay_AI_Grading_Service::normalize_settings([
            'provider' => 'gemini',
            'enabled' => true,
            'endpoint' => '',
            'model' => '',
            'api_key' => 'gemini-key',
        ]);

        self::assertSame('gemini', $explicit_gemini['provider']);
        self::assertSame('gemini-2.5-flash-lite', $explicit_gemini['model']);
        self::assertTrue((bool) $explicit_gemini['configured']);
        self::assertStringContainsString('gemini-2.5-flash-lite:generateContent', (string) $explicit_gemini['gemini_endpoint']);
    }

    public function test_save_settings_keeps_gemini_and_openai_api_keys_separate(): void
    {
        $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings'] = [
            'provider' => 'gemini',
            'enabled' => true,
            'model' => 'gemini-2.5-flash-lite',
            'gemini_api_key' => 'gemini-old',
            'openai_api_key' => 'openai-old',
            'timeout' => 10,
            'batch_limit' => 2,
        ];

        $openai = CBT_Essay_AI_Grading_Service::save_settings([
            'provider' => 'openai',
            'enabled' => true,
            'endpoint' => 'https://api.openai.com/v1/responses',
            'model' => 'gpt-5.4-mini',
            'api_key' => 'openai-new',
            'timeout' => 12,
            'batch_limit' => 3,
        ]);

        self::assertSame('openai-new', $openai['api_key']);
        self::assertSame('gemini-old', $openai['gemini_api_key']);
        self::assertSame('openai-new', $openai['openai_api_key']);
        self::assertSame('gemini-old', $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings']['gemini_api_key']);
        self::assertSame('openai-new', $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings']['openai_api_key']);
        self::assertArrayNotHasKey('api_key', $GLOBALS['cbt_test_wp_options']['cbt_essay_ai_grading_settings']);

        $gemini = CBT_Essay_AI_Grading_Service::save_settings([
            'provider' => 'gemini',
            'enabled' => true,
            'model' => 'gemini-2.5-flash-lite',
            'clear_api_key' => true,
            'timeout' => 12,
            'batch_limit' => 3,
        ]);

        self::assertSame('', $gemini['api_key']);
        self::assertSame('', $gemini['gemini_api_key']);
        self::assertSame('openai-new', $gemini['openai_api_key']);
        self::assertFalse((bool) $gemini['has_api_key']);
        self::assertTrue((bool) $gemini['openai_has_api_key']);
    }

    public function test_grade_answer_gemini_uses_generate_content_payload_and_parses_response(): void
    {
        $endpoint = CBT_Essay_AI_Grading_Service::build_gemini_endpoint_hint('gemini-2.0-flash');
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => wp_json_encode([
                                        'suggested_score' => 3.75,
                                        'confidence' => 0.92,
                                        'feedback_internal' => 'Jawaban sesuai rubrik inti.',
                                        'rubric_breakdown' => ['Konsep benar'],
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

        $result = CBT_Essay_AI_Grading_Service::grade_answer([
            'answer_id' => 12,
            'attempt_id' => 22,
            'exam_id' => 32,
            'question_id' => 42,
            'question_text' => 'Jelaskan demokrasi.',
            'rubric_text' => 'Wajib menyebut partisipasi rakyat dan pemilihan.',
            'answer_text' => 'Demokrasi melibatkan rakyat dalam pemilihan.',
            'max_points' => 4,
            'student_name' => 'Budi',
            'student_nisn' => '654321',
        ], [
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'gemini-key',
            'timeout' => 10,
        ]);

        self::assertTrue((bool) ($result['ok'] ?? false));
        self::assertSame(3.75, $result['suggested_score']);
        self::assertSame(0, $result['needs_manual_review']);

        $request = $GLOBALS['cbt_test_wp_remote_post_log'][0] ?? [];
        $headers = (array) ($request['args']['headers'] ?? []);
        $body = json_decode((string) (($request['args']['body'] ?? '') ?: ''), true);
        self::assertSame('gemini-key', $headers['x-goog-api-key'] ?? '');
        self::assertArrayNotHasKey('Authorization', $headers);
        self::assertSame('application/json', $body['generationConfig']['response_mime_type'] ?? '');
        self::assertSame(0, $body['generationConfig']['temperature'] ?? null);
        self::assertStringContainsString('Balas hanya JSON valid', (string) ($body['system_instruction']['parts'][0]['text'] ?? ''));
        $prompt = (string) ($body['contents'][0]['parts'][0]['text'] ?? '');
        self::assertStringContainsString('Jelaskan demokrasi.', $prompt);
        self::assertStringNotContainsString('Budi', $prompt);
        self::assertStringNotContainsString('654321', $prompt);
    }

    public function test_grade_answer_openai_chat_completions_uses_legacy_messages_payload(): void
    {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => wp_json_encode([
                                'suggested_score' => 4,
                                'confidence' => 0.9,
                                'feedback_internal' => 'Jawaban sesuai rubrik.',
                                'rubric_breakdown' => ['Sesuai'],
                                'flags' => [],
                                'needs_manual_review' => false,
                            ]),
                        ],
                    ],
                ],
            ]),
        ];

        $result = CBT_Essay_AI_Grading_Service::grade_answer([
            'answer_id' => 14,
            'attempt_id' => 24,
            'exam_id' => 34,
            'question_id' => 44,
            'question_text' => 'Jelaskan musyawarah.',
            'rubric_text' => 'Wajib menyebut keputusan bersama.',
            'answer_text' => 'Musyawarah adalah mengambil keputusan bersama.',
            'max_points' => 4,
        ], [
            'provider' => 'openai',
            'endpoint' => $endpoint,
            'model' => 'mock-model',
            'api_key' => 'test-key',
            'timeout' => 10,
        ]);

        self::assertTrue((bool) ($result['ok'] ?? false));
        self::assertSame(4.0, $result['suggested_score']);

        $request = $GLOBALS['cbt_test_wp_remote_post_log'][0] ?? [];
        $body = json_decode((string) (($request['args']['body'] ?? '') ?: ''), true);
        self::assertSame(['type' => 'json_object'], $body['response_format'] ?? []);
        self::assertArrayHasKey('messages', $body);
        self::assertArrayNotHasKey('instructions', $body);
        self::assertStringContainsString('Balas hanya JSON valid', (string) ($body['messages'][0]['content'] ?? ''));
        self::assertStringContainsString('Jelaskan musyawarah.', (string) ($body['messages'][1]['content'] ?? ''));
    }

    public function test_gemini_model_list_filters_generate_content_models(): void
    {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models?key=gemini-key&pageSize=1000';
        $GLOBALS['cbt_test_wp_remote_get_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash-lite',
                        'displayName' => 'Gemini 2.5 Flash Lite',
                        'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                    ],
                    [
                        'name' => 'models/text-embedding-004',
                        'displayName' => 'Text Embedding 004',
                        'supportedGenerationMethods' => ['embedContent'],
                    ],
                    [
                        'name' => 'models/gemini-live-2.5-flash',
                        'displayName' => 'Gemini Live 2.5 Flash',
                        'supportedGenerationMethods' => ['bidiGenerateContent'],
                    ],
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'displayName' => 'Gemini 2.5 Flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ]),
        ];

        $result = CBT_Essay_AI_Grading_Service::get_gemini_model_options_result([
            'api_key' => 'gemini-key',
            'timeout' => 10,
        ], true);

        self::assertSame('ready', $result['status']);
        self::assertSame('api', $result['source']);
        self::assertArrayHasKey('gemini-2.5-flash-lite', $result['options']);
        self::assertArrayHasKey('gemini-2.5-flash', $result['options']);
        self::assertArrayNotHasKey('text-embedding-004', $result['options']);
        self::assertArrayNotHasKey('gemini-live-2.5-flash', $result['options']);
    }

    public function test_openai_model_list_filters_text_response_models(): void
    {
        $endpoint = 'https://api.openai.com/v1/models';
        $GLOBALS['cbt_test_wp_remote_get_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'object' => 'list',
                'data' => [
                    ['id' => 'gpt-5.4-mini', 'object' => 'model'],
                    ['id' => 'gpt-5.4', 'object' => 'model'],
                    ['id' => 'o4-mini', 'object' => 'model'],
                    ['id' => 'text-embedding-3-small', 'object' => 'model'],
                    ['id' => 'gpt-realtime-mini', 'object' => 'model'],
                    ['id' => 'gpt-4o-transcribe', 'object' => 'model'],
                    ['id' => 'omni-moderation-latest', 'object' => 'model'],
                    ['id' => 'dall-e-3', 'object' => 'model'],
                ],
            ]),
        ];

        $result = CBT_Essay_AI_Grading_Service::get_openai_model_options_result([
            'api_key' => 'openai-key',
            'endpoint' => 'https://api.openai.com/v1/responses',
            'timeout' => 10,
        ], true);

        self::assertSame('ready', $result['status']);
        self::assertSame('api', $result['source']);
        self::assertArrayHasKey('gpt-5.4-mini', $result['options']);
        self::assertArrayHasKey('gpt-5.4', $result['options']);
        self::assertArrayHasKey('o4-mini', $result['options']);
        self::assertArrayNotHasKey('text-embedding-3-small', $result['options']);
        self::assertArrayNotHasKey('gpt-realtime-mini', $result['options']);
        self::assertArrayNotHasKey('gpt-4o-transcribe', $result['options']);
        self::assertArrayNotHasKey('omni-moderation-latest', $result['options']);
        self::assertArrayNotHasKey('dall-e-3', $result['options']);
    }

    public function test_grade_answer_invalid_json_fails_safely(): void
    {
        $endpoint = 'https://ai.example.test/v1/chat/completions-invalid';
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'bukan json',
                        ],
                    ],
                ],
            ]),
        ];

        $result = CBT_Essay_AI_Grading_Service::grade_answer([
            'answer_id' => 11,
            'attempt_id' => 21,
            'exam_id' => 31,
            'question_id' => 41,
            'question_text' => 'Jelaskan ekosistem.',
            'rubric_text' => 'Wajib menyebut produsen, konsumen, dan dekomposer.',
            'answer_text' => 'Ekosistem terdiri dari makhluk hidup dan lingkungan.',
            'max_points' => 4,
        ], [
            'provider' => 'openai',
            'endpoint' => $endpoint,
            'model' => 'mock-model',
            'api_key' => 'test-key',
            'timeout' => 10,
        ]);

        self::assertFalse((bool) ($result['ok'] ?? false));
        self::assertSame('invalid_json', $result['error_code']);
    }

    public function test_grade_answer_gemini_empty_response_fails_safely(): void
    {
        $endpoint = CBT_Essay_AI_Grading_Service::build_gemini_endpoint_hint('gemini-2.0-flash');
        $GLOBALS['cbt_test_wp_remote_post_map'][$endpoint] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'candidates' => [],
            ]),
        ];

        $result = CBT_Essay_AI_Grading_Service::grade_answer([
            'answer_id' => 13,
            'attempt_id' => 23,
            'exam_id' => 33,
            'question_id' => 43,
            'question_text' => 'Jelaskan koperasi.',
            'rubric_text' => 'Wajib menyebut asas kekeluargaan.',
            'answer_text' => 'Koperasi berdasarkan asas kekeluargaan.',
            'max_points' => 4,
        ], [
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'gemini-key',
            'timeout' => 10,
        ]);

        self::assertFalse((bool) ($result['ok'] ?? false));
        self::assertSame('invalid_json', $result['error_code']);
    }

    public function test_attach_suggestions_marks_fresh_and_stale_rows(): void
    {
        $row = [
            'answer_id' => 501,
            'attempt_id' => 601,
            'exam_id' => 701,
            'question_id' => 801,
            'question_text' => 'Jelaskan rantai makanan.',
            'rubric_text' => 'Urutan produsen ke konsumen harus jelas.',
            'answer_text' => 'Rumput dimakan belalang, lalu dimakan katak.',
            'max_points' => 6,
        ];

        global $wpdb;
        $wpdb = new EssayAISuggestionFakeWpdb([
            [
                'answer_id' => 501,
                'attempt_id' => 601,
                'exam_id' => 701,
                'question_id' => 801,
                'status' => 'success',
                'content_hash' => CBT_Essay_AI_Grading_Service::build_content_hash($row),
                'suggested_score' => 4.5,
                'confidence' => 0.8,
                'feedback_internal' => 'Alur jawaban sudah benar.',
                'rubric_breakdown' => wp_json_encode(['Produsen ada', 'Konsumen ada']),
                'flags' => wp_json_encode([]),
                'needs_manual_review' => 0,
                'updated_at' => '2026-03-24 12:00:00',
                'provider_model' => 'mock-model',
            ],
        ]);

        $fresh = CBT_Essay_AI_Grading_Service::attach_suggestions_to_rows([$row]);
        self::assertSame('success', $fresh[0]['ai_suggestion']['status']);
        self::assertTrue((bool) $fresh[0]['ai_suggestion']['fresh']);
        self::assertSame(4.5, $fresh[0]['ai_suggestion']['suggested_score']);

        $row['answer_text'] = 'Jawaban sudah diubah setelah AI dibuat.';
        $stale = CBT_Essay_AI_Grading_Service::attach_suggestions_to_rows([$row]);
        self::assertSame('stale', $stale[0]['ai_suggestion']['status']);
        self::assertFalse((bool) $stale[0]['ai_suggestion']['fresh']);
    }
}

final class EssayAISuggestionFakeWpdb
{
    public string $prefix = 'wp_';

    /** @var array<int,array<string,mixed>> */
    private array $suggestions;

    /**
     * @param array<int,array<string,mixed>> $suggestions
     */
    public function __construct(array $suggestions)
    {
        $this->suggestions = $suggestions;
    }

    /**
     * @return array{query:string,args:array<int,mixed>}
     */
    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        return [
            'query' => $query,
            'args' => array_values($args),
        ];
    }

    /**
     * @param array<string,mixed>|string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (!str_contains($query, 'wp_cbt_essay_ai_suggestions')) {
            return [];
        }

        return $this->suggestions;
    }
}
