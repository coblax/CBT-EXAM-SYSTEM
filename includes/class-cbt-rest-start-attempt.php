<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Start_Attempt_Routes
{
    public static function start_attempt(WP_REST_Request $request)
    {
        global $wpdb;
        self::mark_priority_window('start_attempt');
        $request_started_at = microtime(true);

        $exam_id = (int) $request->get_param('exam_id');
        $exam_token_input = (string) $request->get_param('exam_token');
        $queue_ticket = sanitize_text_field((string) $request->get_param('queue_ticket'));
        $queue_ticket = preg_replace('/[^a-zA-Z0-9\\-]/', '', $queue_ticket) ?: '';
        $resume_only = ((int) $request->get_param('resume_only') === 1);
        if ($exam_token_input === '') {
            $exam_token_input = (string) $request->get_param('token');
        }
        $exam_token_input = CBT_Auth::normalize_exam_token_input($exam_token_input);
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);

        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can start an attempt', ['status' => 403]);
        }

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam_id', 'exam_id is required', ['status' => 400]);
        }

        $idempotency_key = class_exists('CBT_Start_Attempt_Idempotency_Service')
            ? CBT_Start_Attempt_Idempotency_Service::sanitize_key((string) $request->get_param('idempotency_key'))
            : '';
        $idempotency_claim = null;
        $finalize_start_attempt_response = static function ($response, ?string $replay_state = null) use (&$idempotency_claim) {
            if (!is_array($idempotency_claim) || !class_exists('CBT_Start_Attempt_Idempotency_Service')) {
                return $response;
            }

            if ($replay_state === null || $replay_state === '') {
                CBT_Start_Attempt_Idempotency_Service::abandon($idempotency_claim);
            } else {
                CBT_Start_Attempt_Idempotency_Service::complete($idempotency_claim, $replay_state, $response);
            }

            $idempotency_claim = null;
            return $response;
        };

        if ($idempotency_key !== '' && class_exists('CBT_Start_Attempt_Idempotency_Service')) {
            $idempotency_begin = CBT_Start_Attempt_Idempotency_Service::begin($user_id, $exam_id, $idempotency_key, $queue_ticket);
            $idempotency_mode = sanitize_key((string) ($idempotency_begin['mode'] ?? ''));

            if ($idempotency_mode === 'replay') {
                $idempotency_record = is_array($idempotency_begin['record'] ?? null) ? $idempotency_begin['record'] : [];
                $idempotency_state = sanitize_key((string) ($idempotency_record['state'] ?? ''));
                self::record_start_attempt_phase(
                    'idempotency_hit_' . ($idempotency_state !== '' ? $idempotency_state : 'unknown'),
                    $exam_id,
                    $user_id,
                    [
                        'attempt_id' => (int) ($idempotency_record['attempt_id'] ?? 0),
                        'queue_ticket' => (string) ($idempotency_record['queue_ticket'] ?? ''),
                        'duration_ms' => self::measure_elapsed_ms($request_started_at),
                    ]
                );

                return $idempotency_begin['response'] ?? null;
            }

            if ($idempotency_mode === 'processing') {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'attempt_creating', 'lock_owner_active', [
                    'queue_ticket' => $queue_ticket,
                    'retry_after_ms' => 1500,
                ]);
                self::record_start_attempt_phase('idempotency_processing_hit', $exam_id, $user_id, [
                    'duration_ms' => self::measure_elapsed_ms($request_started_at),
                ]);
                $processingError = new WP_Error(
                    'attempt_lock_active',
                    'Permintaan mulai ujian sedang diproses. Coba lagi beberapa detik.',
                    ['status' => 429, 'retry_after_ms' => 1500]
                );
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $processingError,
                    ['error_code' => 'attempt_lock_active'],
                    'terminal_error'
                );

                return $processingError;
            }

            if ($idempotency_mode === 'claimed' && is_array($idempotency_begin['claim'] ?? null)) {
                $idempotency_claim = $idempotency_begin['claim'];
            }
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';

        $exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, subject_id, title, created_by, status, starts_at, ends_at, duration_minutes, randomize_questions, randomize_options, show_student_result, enable_calculator, target_kelas, target_agama, target_jenis_kelamin, restrict_to_subject_choice
                 FROM {$exam_table}
                 WHERE id = %d",
                $exam_id
            ),
            ARRAY_A
        );
        if (!$exam) {
            self::write_start_attempt_opening_state($exam_id, $user_id, 'terminal_error', 'not_found');
            return $finalize_start_attempt_response(new WP_Error('not_found', 'Exam not found', ['status' => 404]), 'terminal_error');
        }

        $audience = self::evaluate_student_exam_audience($exam, $user_id, self::get_live_user_profile($user_id));
        if (empty($audience['allowed'])) {
            $opening_reason = sanitize_key((string) ($audience['reason'] ?? 'forbidden'));
            self::write_start_attempt_opening_state($exam_id, $user_id, 'terminal_error', $opening_reason);
            return $finalize_start_attempt_response(new WP_Error(
                'forbidden',
                'Exam tidak tersedia untuk akun Anda.',
                [
                    'status' => 403,
                    'opening_reason' => $opening_reason,
                    'suggestion' => 'Kembali ke daftar exam untuk memilih exam lain yang sesuai.',
                    'return_to_exam_list_suggestion' => 'Kembali ke daftar exam untuk memilih exam lain yang sesuai.',
                ]
            ), 'terminal_error');
        }

        $expected_token = null;
        $resolve_expected_token = static function () use (&$expected_token): string {
            if (is_string($expected_token)) {
                return $expected_token;
            }

            $global_token_meta = CBT_Auth::get_global_exam_token(true);
            $expected_token = CBT_Auth::normalize_exam_token_input((string) ($global_token_meta['token'] ?? ''));
            return $expected_token;
        };
        $validate_token_submission = static function (string $submitted_token) use ($resolve_expected_token) {
            $expected_token = $resolve_expected_token();
            if (
                $expected_token !== '' &&
                $submitted_token === '' &&
                CBT_Auth::is_frontend_auto_exam_token_enabled()
            ) {
                $submitted_token = $expected_token;
            }

            return self::validate_exam_token_or_error($expected_token, $submitted_token);
        };

        $resume_lookup_started_at = microtime(true);
        self::write_start_attempt_opening_state($exam_id, $user_id, 'resume_lookup', 'resume_index_miss', [
            'queue_ticket' => $queue_ticket,
            'retry_after_ms' => 1500,
        ]);
        $resume_candidate = self::resolve_resumable_attempt_candidate($exam_id, $user_id, $attempt_table);
        self::record_start_attempt_phase('start_attempt_resume_lookup', $exam_id, $user_id, [
            'duration_ms' => self::measure_elapsed_ms($resume_lookup_started_at),
            'resume_source' => (string) ($resume_candidate['source'] ?? 'none'),
            'attempt_id' => (int) (($resume_candidate['attempt']['id'] ?? 0)),
        ]);
        if (is_array($resume_candidate['attempt'] ?? null)) {
            self::write_start_attempt_opening_state($exam_id, $user_id, 'ready', 'attempt_ready', [
                'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                'resume_source' => (string) ($resume_candidate['source'] ?? ''),
            ]);
            self::record_start_attempt_resolution(
                (string) ($resume_candidate['source'] ?? '') === 'db' ? 'resume_from_db' : 'resume_from_index',
                $exam_id,
                $user_id,
                [
                    'attempt_id' => (int) (($resume_candidate['attempt']['id'] ?? 0)),
                ]
            );

            $expired_attempt_response = self::maybe_build_expired_attempt_finalizing_response(
                (array) $resume_candidate['attempt'],
                $exam,
                $exam_id,
                $user_id
            );
            if ($expired_attempt_response !== null) {
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $expired_attempt_response,
                    [
                        'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                        'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                    ],
                    'pending'
                );
                return $finalize_start_attempt_response($expired_attempt_response, 'pending');
            }

            $response = self::build_resumed_attempt_response(
                (array) $resume_candidate['attempt'],
                $exam,
                $exam_id,
                $user_id,
                $attempt_table,
                $resume_only,
                $exam_token_input,
                $validate_token_submission
            );
            self::record_start_attempt_response_ready_phase(
                'start_attempt_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $response,
                [
                    'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                    'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                ],
                'resumed'
            );
            return $finalize_start_attempt_response($response, 'resumed');
        }

        if ($resume_only) {
            self::write_start_attempt_opening_state($exam_id, $user_id, 'resume_lookup', 'resume_db_miss', [
                'retry_after_ms' => 1500,
            ]);
            return $finalize_start_attempt_response(new WP_Error(
                'attempt_not_found',
                'Tidak ada attempt ujian aktif untuk dilanjutkan.',
                ['status' => 404]
            ));
        }

        $now = current_time('mysql');
        $within_schedule = (
            (empty($exam['starts_at']) || (string) $exam['starts_at'] <= $now) &&
            (empty($exam['ends_at']) || (string) $exam['ends_at'] >= $now)
        );
        if ((string) ($exam['status'] ?? 'draft') !== 'published' || !$within_schedule) {
            $inactive_details = self::describe_start_attempt_inactive_exam($exam, $now);
            $inactive_reason = (string) ($inactive_details['opening_reason'] ?? 'exam_not_active');
            $inactive_error = self::build_start_attempt_inactive_exam_error($exam, $now);
            self::write_start_attempt_opening_state($exam_id, $user_id, 'terminal_error', $inactive_reason);
            self::record_start_attempt_resolution('terminal_error', $exam_id, $user_id, [
                'error_code' => 'forbidden',
                'inactive_error_code' => $inactive_reason,
            ]);
            self::record_start_attempt_response_ready_phase(
                'start_attempt_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $inactive_error,
                [
                    'error_code' => 'forbidden',
                    'inactive_error_code' => $inactive_reason,
                ],
                'terminal_error'
            );
            return $finalize_start_attempt_response($inactive_error, 'terminal_error');
        }

        $token_check = $validate_token_submission($exam_token_input);
        if (is_wp_error($token_check)) {
            self::write_start_attempt_opening_state(
                $exam_id,
                $user_id,
                'terminal_error',
                self::map_start_attempt_terminal_reason((string) $token_check->get_error_code())
            );
            self::record_start_attempt_resolution('terminal_error', $exam_id, $user_id, [
                'error_code' => (string) $token_check->get_error_code(),
            ]);
            self::record_start_attempt_response_ready_phase(
                'start_attempt_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $token_check,
                ['error_code' => (string) $token_check->get_error_code()],
                'terminal_error'
            );
            return $finalize_start_attempt_response($token_check, 'terminal_error');
        }

        if (class_exists('CBT_Start_Attempt_Gate_Service')) {
            $gate_evaluation_started_at = microtime(true);
            $gate_result = CBT_Start_Attempt_Gate_Service::evaluate_request($exam_id, $user_id, $queue_ticket);
            self::record_start_attempt_phase('start_attempt_gate_evaluation', $exam_id, $user_id, [
                'duration_ms' => self::measure_elapsed_ms($gate_evaluation_started_at),
                'queue_mode' => (string) ($gate_result['mode'] ?? ''),
                'queue_ticket' => (string) ($gate_result['queue_ticket'] ?? ''),
            ]);
            if ((string) ($gate_result['mode'] ?? '') === 'queued') {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'queue_waiting', 'queue_admission_wait', [
                    'queue_ticket' => (string) ($gate_result['queue_ticket'] ?? ''),
                    'retry_after_ms' => (int) ($gate_result['poll_after_ms'] ?? 0),
                    'estimated_wait_seconds' => (int) ($gate_result['estimated_wait_seconds'] ?? 0),
                    'queue_ticket_created_at' => (float) ($gate_result['queue_ticket_created_at'] ?? 0),
                ]);
                self::record_start_attempt_resolution('queued_new_start', $exam_id, $user_id, [
                    'queue_ticket' => (string) ($gate_result['queue_ticket'] ?? ''),
                    'queue_position' => (int) ($gate_result['queue_position'] ?? 0),
                ]);
                $response = self::build_start_attempt_gate_queue_response($gate_result);
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $response,
                    ['queue_ticket' => (string) ($gate_result['queue_ticket'] ?? '')],
                    'queued'
                );
                return $finalize_start_attempt_response($response, 'queued');
            }
        }

        $lock_key = 'start_attempt:user:' . $user_id . ':exam:' . $exam_id;
        self::write_start_attempt_opening_state($exam_id, $user_id, 'attempt_creating', 'attempt_insert_in_progress', [
            'queue_ticket' => $queue_ticket,
            'retry_after_ms' => 1500,
        ]);
        if (!CBT_Cache::acquire_lock($lock_key, 15, [
            'type' => 'start_attempt',
            'user_id' => $user_id,
            'exam_id' => $exam_id,
        ])) {
            $resume_candidate = self::resolve_resumable_attempt_candidate($exam_id, $user_id, $attempt_table);
            if (is_array($resume_candidate['attempt'] ?? null)) {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'ready', 'attempt_ready', [
                    'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                    'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                ]);
                self::record_start_attempt_resolution('lock_conflict_resumed', $exam_id, $user_id, [
                    'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                    'attempt_id' => (int) (($resume_candidate['attempt']['id'] ?? 0)),
                ]);
                $expired_attempt_response = self::maybe_build_expired_attempt_finalizing_response(
                    (array) $resume_candidate['attempt'],
                    $exam,
                    $exam_id,
                    $user_id
                );
                if ($expired_attempt_response !== null) {
                    self::record_start_attempt_response_ready_phase(
                        'start_attempt_response_ready',
                        $exam_id,
                        $user_id,
                        $request_started_at,
                        $expired_attempt_response,
                        [
                            'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                            'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                        ],
                        'pending'
                    );
                    return $finalize_start_attempt_response($expired_attempt_response, 'pending');
                }
                $response = self::build_resumed_attempt_response(
                    (array) $resume_candidate['attempt'],
                    $exam,
                    $exam_id,
                    $user_id,
                    $attempt_table,
                    $resume_only,
                    $exam_token_input,
                    $validate_token_submission
                );
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $response,
                    [
                        'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                        'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                    ],
                    'resumed'
                );
                return $finalize_start_attempt_response($response, 'resumed');
            }

            self::write_start_attempt_opening_state($exam_id, $user_id, 'attempt_creating', 'lock_owner_active', [
                'queue_ticket' => $queue_ticket,
                'retry_after_ms' => 1500,
            ]);
            self::record_start_attempt_resolution('lock_conflict_retryable', $exam_id, $user_id);
            $lockError = new WP_Error(
                'attempt_lock_active',
                'Permintaan mulai ujian sedang diproses. Coba lagi beberapa detik.',
                ['status' => 429, 'retry_after_ms' => 1500]
            );
            self::record_start_attempt_response_ready_phase(
                'start_attempt_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $lockError,
                ['error_code' => 'attempt_lock_active'],
                'terminal_error'
            );
            return $finalize_start_attempt_response(new WP_Error(
                'attempt_lock_active',
                'Permintaan mulai ujian sedang diproses. Coba lagi beberapa detik.',
                ['status' => 429, 'retry_after_ms' => 1500]
            ));
        }

        try {
            self::write_start_attempt_opening_state($exam_id, $user_id, 'attempt_creating', 'attempt_insert_in_progress', [
                'queue_ticket' => $queue_ticket,
                'retry_after_ms' => 1500,
            ]);
            $resume_candidate = self::resolve_resumable_attempt_candidate($exam_id, $user_id, $attempt_table);
            if (is_array($resume_candidate['attempt'] ?? null)) {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'ready', 'attempt_ready', [
                    'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                    'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                ]);
                self::record_start_attempt_resolution(
                    (string) ($resume_candidate['source'] ?? '') === 'db' ? 'resume_from_db' : 'resume_from_index',
                    $exam_id,
                    $user_id,
                    [
                        'attempt_id' => (int) (($resume_candidate['attempt']['id'] ?? 0)),
                    ]
                );
                $expired_attempt_response = self::maybe_build_expired_attempt_finalizing_response(
                    (array) $resume_candidate['attempt'],
                    $exam,
                    $exam_id,
                    $user_id
                );
                if ($expired_attempt_response !== null) {
                    self::record_start_attempt_response_ready_phase(
                        'start_attempt_response_ready',
                        $exam_id,
                        $user_id,
                        $request_started_at,
                        $expired_attempt_response,
                        [
                            'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                            'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                        ],
                        'pending'
                    );
                    return $finalize_start_attempt_response($expired_attempt_response, 'pending');
                }
                $response = self::build_resumed_attempt_response(
                    (array) $resume_candidate['attempt'],
                    $exam,
                    $exam_id,
                    $user_id,
                    $attempt_table,
                    $resume_only,
                    $exam_token_input,
                    $validate_token_submission
                );
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $response,
                    [
                        'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? 0),
                        'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                    ],
                    'resumed'
                );
                return $finalize_start_attempt_response($response, 'resumed');
            }

            $latest_attempt = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, status, started_at, finished_at, question_order, option_order, extra_time_minutes
                     FROM {$attempt_table}
                     WHERE exam_id = %d AND student_id = %d AND status IN ('in_progress', 'completed')
                     ORDER BY FIELD(status, 'in_progress', 'completed'), id DESC
                     LIMIT 1",
                    $exam_id,
                    $user_id
                ),
                ARRAY_A
            );

            if ($latest_attempt && (string) ($latest_attempt['status'] ?? '') === 'in_progress') {
                $latest_attempt['exam_id'] = $exam_id;
                $latest_attempt['student_id'] = $user_id;
                self::write_start_attempt_opening_state($exam_id, $user_id, 'ready', 'attempt_ready', [
                    'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                    'resume_source' => 'db',
                ]);

                self::record_start_attempt_resolution('resume_from_db', $exam_id, $user_id, [
                    'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                ]);
                $expired_attempt_response = self::maybe_build_expired_attempt_finalizing_response(
                    $latest_attempt,
                    $exam,
                    $exam_id,
                    $user_id
                );
                if ($expired_attempt_response !== null) {
                    self::record_start_attempt_response_ready_phase(
                        'start_attempt_response_ready',
                        $exam_id,
                        $user_id,
                        $request_started_at,
                        $expired_attempt_response,
                        [
                            'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                            'resume_source' => 'db',
                        ],
                        'pending'
                    );
                    return $finalize_start_attempt_response($expired_attempt_response, 'pending');
                }
                $response = self::build_resumed_attempt_response(
                    $latest_attempt,
                    $exam,
                    $exam_id,
                    $user_id,
                    $attempt_table,
                    $resume_only,
                    $exam_token_input,
                    $validate_token_submission
                );
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $response,
                    [
                        'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                        'resume_source' => 'db',
                    ],
                    'resumed'
                );
                return $finalize_start_attempt_response($response, 'resumed');
            }

            if ($latest_attempt && (string) ($latest_attempt['status'] ?? '') === 'completed') {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'completed', 'attempt_completed', [
                    'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                ]);
                self::record_start_attempt_resolution('completed', $exam_id, $user_id, [
                    'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                ]);
                $completedError = new WP_Error(
                    'attempt_already_completed',
                    'Anda sudah menyelesaikan ujian ini. Hubungi pengawas/admin untuk reset jika perlu mengulang.',
                    [
                        'status' => 403,
                        'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                        'finished_at' => (string) ($latest_attempt['finished_at'] ?? ''),
                    ]
                );
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $completedError,
                    ['attempt_id' => (int) ($latest_attempt['id'] ?? 0)],
                    'completed'
                );
                return $finalize_start_attempt_response(new WP_Error(
                    'attempt_already_completed',
                    'Anda sudah menyelesaikan ujian ini. Hubungi pengawas/admin untuk reset jika perlu mengulang.',
                    [
                        'status' => 403,
                        'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
                        'finished_at' => (string) ($latest_attempt['finished_at'] ?? ''),
                    ]
                ), 'completed');
            }

            $question_ids = [];
            $question_number_map = [];
            $option_order = '{}';
            $used_start_snapshot = false;
            $start_snapshot = [];
            $start_attempt_question_manifest = [];
            $start_snapshot_started_at = microtime(true);

            try {
                $start_snapshot = self::get_cached_exam_start_attempt_snapshot($exam_id);
                $snapshot_question_ids = array_values(array_filter(array_map('intval', (array) ($start_snapshot['question_ids'] ?? [])), static function (int $question_id): bool {
                    return $question_id > 0;
                }));
                if (!empty($snapshot_question_ids)) {
                    $question_ids = $snapshot_question_ids;
                    if ((int) ($exam['randomize_questions'] ?? 0) === 1) {
                        shuffle($question_ids);
                    }
                    $question_number_map = self::build_attempt_question_number_map($question_ids, $question_ids);
                    $option_order = self::encode_attempt_option_order_map(
                        self::build_attempt_option_order_map_from_snapshot_tokens(
                            isset($start_snapshot['option_randomization_tokens_by_question']) && is_array($start_snapshot['option_randomization_tokens_by_question'])
                                ? $start_snapshot['option_randomization_tokens_by_question']
                                : [],
                            (int) ($exam['randomize_options'] ?? 0) === 1
                        )
                    );
                    $start_attempt_question_manifest = self::build_lightweight_question_manifest_from_start_snapshot(
                        $start_snapshot,
                        $question_ids,
                        $question_number_map
                    );
                    $used_start_snapshot = true;
                }
            } catch (Throwable $throwable) {
                $used_start_snapshot = false;
            }

            if (!$used_start_snapshot) {
                $questions = self::get_cached_exam_question_payload($exam_id);
                $question_ids = self::extract_question_ids_from_payload($questions);
                $option_order = self::encode_attempt_option_order_map(
                    self::build_attempt_option_order_map(
                        $questions,
                        (int) ($exam['randomize_options'] ?? 0) === 1
                    )
                );
                if ((int) $exam['randomize_questions'] === 1 && !empty($question_ids)) {
                    shuffle($question_ids);
                }
                $question_number_map = self::build_attempt_question_number_map($question_ids, $question_ids);
                $start_attempt_question_manifest = self::build_question_manifest(
                    self::apply_question_numbers_to_payload($questions, $question_number_map)
                );
            }

            if (empty($start_attempt_question_manifest)) {
                $start_attempt_question_manifest = self::build_minimal_question_manifest_from_order(
                    $question_ids,
                    $question_number_map
                );
            }

            self::record_start_attempt_phase('start_attempt_start_snapshot', $exam_id, $user_id, [
                'duration_ms' => self::measure_elapsed_ms($start_snapshot_started_at),
                'used_start_snapshot' => $used_start_snapshot ? 1 : 0,
                'question_count' => count($question_ids),
            ]);

            $question_order = wp_json_encode($question_ids);
            if (!is_string($question_order)) {
                $question_order = '[]';
            }

            $attempt_insert_started_at = microtime(true);
            $inserted = $wpdb->insert(
                $attempt_table,
                [
                    'exam_id' => $exam_id,
                    'student_id' => $user_id,
                    'status' => 'in_progress',
                    'question_order' => $question_order,
                    'option_order' => $option_order,
                    'started_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            self::record_start_attempt_phase('start_attempt_attempt_insert', $exam_id, $user_id, [
                'duration_ms' => self::measure_elapsed_ms($attempt_insert_started_at),
                'ok' => $inserted ? 1 : 0,
            ]);

            if (!$inserted) {
                self::record_start_attempt_resolution('terminal_error', $exam_id, $user_id, [
                    'error_code' => 'db_insert_failed',
                ]);
                $insertError = new WP_Error('db_insert_failed', 'Failed to start attempt', ['status' => 500]);
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $insertError,
                    ['error_code' => 'db_insert_failed'],
                    'terminal_error'
                );
                return $finalize_start_attempt_response(new WP_Error('db_insert_failed', 'Failed to start attempt', ['status' => 500]));
            }

            $created_attempt_id = (int) $wpdb->insert_id;
            $created_attempt = [
                'id' => $created_attempt_id,
                'exam_id' => $exam_id,
                'student_id' => $user_id,
                'status' => 'in_progress',
                'started_at' => $now,
                'question_order' => $question_order,
                'option_order' => $option_order,
                'extra_time_minutes' => 0,
            ];
            CBT_Cache::invalidate_user($user_id);
            CBT_Cache::invalidate_attempt($created_attempt_id);
            self::sync_active_attempt_index($created_attempt);
            self::write_start_attempt_opening_state($exam_id, $user_id, 'attempt_created', 'entry_snapshot_pending', [
                'attempt_id' => $created_attempt_id,
                'retry_after_ms' => 1000,
            ]);
            $attempt_timer = self::build_attempt_timer_payload([
                'id' => $created_attempt_id,
                'status' => 'in_progress',
                'started_at' => $now,
                'extra_time_minutes' => 0,
            ], (int) ($exam['duration_minutes'] ?? 0));
            $question_order_signature = self::build_attempt_question_order_signature(
                $question_ids,
                $question_number_map
            );
            $minimal_snapshot_started_at = microtime(true);
            self::write_minimal_attempt_entry_snapshots(
                $created_attempt,
                $exam,
                $question_ids,
                $question_number_map,
                self::normalize_attempt_option_order_map($option_order),
                $start_attempt_question_manifest
            );
            self::record_start_attempt_phase('start_attempt_minimal_entry_snapshots', $exam_id, $user_id, [
                'attempt_id' => $created_attempt_id,
                'duration_ms' => self::measure_elapsed_ms($minimal_snapshot_started_at),
                'question_count' => count($question_ids),
                'used_start_snapshot' => $used_start_snapshot ? 1 : 0,
            ]);
            self::write_start_attempt_opening_state($exam_id, $user_id, 'bootstrap_session', 'session_snapshot_pending', [
                'attempt_id' => $created_attempt_id,
                'retry_after_ms' => 1000,
            ]);
            $response_payload = self::append_adaptive_load_payload([
                'attempt_id' => $created_attempt_id,
                'status' => 'started',
                'duration_minutes' => (int) $exam['duration_minutes'],
                'extra_time_minutes' => 0,
                'started_at' => $now,
                'remaining_seconds' => (int) ($attempt_timer['remaining_seconds'] ?? max(0, ((int) $exam['duration_minutes']) * MINUTE_IN_SECONDS)),
                'server_now' => (string) ($attempt_timer['server_now'] ?? current_time('mysql')),
                'question_revision' => CBT_Cache::get_exam_revision_meta($exam_id),
                'question_order_signature' => $question_order_signature,
                'opening_state' => 'ready',
            ]);
            self::write_start_attempt_opening_state($exam_id, $user_id, 'ready', 'attempt_ready', [
                'attempt_id' => $created_attempt_id,
                'retry_after_ms' => 0,
            ]);

            self::record_start_attempt_resolution('started', $exam_id, $user_id, [
                'attempt_id' => $created_attempt_id,
                'used_start_snapshot' => $used_start_snapshot ? 1 : 0,
            ]);
            self::record_start_attempt_phase('start_attempt_response_ready', $exam_id, $user_id, [
                'attempt_id' => $created_attempt_id,
                'duration_ms' => self::measure_elapsed_ms($request_started_at),
                'elapsed_ms' => self::measure_elapsed_ms($request_started_at),
                'used_start_snapshot' => $used_start_snapshot ? 1 : 0,
                'result_status' => 'started',
            ]);

            self::run_after_start_attempt_response(
                'start_attempt_deferred_roster_sync',
                $exam_id,
                $user_id,
                static function () use ($created_attempt, $exam): void {
                    self::sync_live_attempt_roster($created_attempt, [
                        'teacher_id' => (int) ($exam['created_by'] ?? 0),
                        'exam_title' => (string) ($exam['title'] ?? ''),
                    ]);
                },
                [
                    'attempt_id' => $created_attempt_id,
                ]
            );

            self::run_after_start_attempt_response(
                'start_attempt_deferred_runtime_snapshots',
                $exam_id,
                $user_id,
                static function () use ($created_attempt, $exam, $question_ids, $question_number_map, $option_order, $start_attempt_question_manifest): void {
                    self::sync_attempt_runtime_snapshots(
                        $created_attempt,
                        $exam,
                        $question_ids,
                        $question_number_map,
                        self::normalize_attempt_option_order_map($option_order),
                        $start_attempt_question_manifest
                    );
                },
                [
                    'attempt_id' => $created_attempt_id,
                    'question_count' => count($question_ids),
                ]
            );

            return $finalize_start_attempt_response(rest_ensure_response($response_payload), 'started');
        } finally {
            CBT_Cache::release_lock($lock_key);
            if (is_array($idempotency_claim)) {
                $finalize_start_attempt_response(null);
            }
        }
    }

    public static function start_attempt_status(WP_REST_Request $request)
    {
        global $wpdb;
        self::mark_priority_window('start_attempt_status');
        $request_started_at = microtime(true);

        $exam_id = (int) $request->get_param('exam_id');
        $exam_token_input = (string) $request->get_param('exam_token');
        $queue_ticket = sanitize_text_field((string) $request->get_param('queue_ticket'));
        $queue_ticket = preg_replace('/[^a-zA-Z0-9\\-]/', '', $queue_ticket) ?: '';
        $resume_only = ((int) $request->get_param('resume_only') === 1);
        if ($exam_token_input === '') {
            $exam_token_input = (string) $request->get_param('token');
        }
        $exam_token_input = CBT_Auth::normalize_exam_token_input($exam_token_input);
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);

        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can check start attempt status', ['status' => 403]);
        }

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam_id', 'exam_id is required', ['status' => 400]);
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';

        $exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, subject_id, title, created_by, status, starts_at, ends_at, duration_minutes, randomize_questions, randomize_options, show_student_result, enable_calculator, target_kelas, target_agama, target_jenis_kelamin, restrict_to_subject_choice
                 FROM {$exam_table}
                 WHERE id = %d",
                $exam_id
            ),
            ARRAY_A
        );
        if (!$exam) {
            self::write_start_attempt_opening_state($exam_id, $user_id, 'terminal_error', 'not_found');
            return self::build_start_attempt_terminal_status_response(
                'not_found',
                'Exam tidak ditemukan.',
                404
            );
        }

        $audience = self::evaluate_student_exam_audience($exam, $user_id, self::get_live_user_profile($user_id));
        if (empty($audience['allowed'])) {
            $opening_reason = sanitize_key((string) ($audience['reason'] ?? 'forbidden'));
            self::write_start_attempt_opening_state($exam_id, $user_id, 'terminal_error', $opening_reason);
            return self::build_start_attempt_terminal_status_response(
                'forbidden',
                'Exam tidak tersedia untuk akun Anda.',
                403,
                [
                    'opening_reason' => $opening_reason,
                    'suggestion' => 'Kembali ke daftar exam untuk memilih exam lain yang sesuai.',
                    'return_to_exam_list_suggestion' => 'Kembali ke daftar exam untuk memilih exam lain yang sesuai.',
                ]
            );
        }

        $expected_token = null;
        $resolve_expected_token = static function () use (&$expected_token): string {
            if (is_string($expected_token)) {
                return $expected_token;
            }

            $global_token_meta = CBT_Auth::get_global_exam_token(true);
            $expected_token = CBT_Auth::normalize_exam_token_input((string) ($global_token_meta['token'] ?? ''));
            return $expected_token;
        };
        $validate_token_submission = static function (string $submitted_token) use ($resolve_expected_token) {
            $expected_token = $resolve_expected_token();
            if (
                $expected_token !== '' &&
                $submitted_token === '' &&
                CBT_Auth::is_frontend_auto_exam_token_enabled()
            ) {
                $submitted_token = $expected_token;
            }

            return self::validate_exam_token_or_error($expected_token, $submitted_token);
        };

        $existing_opening_state = class_exists('CBT_Start_Attempt_Opening_State_Service')
            ? CBT_Start_Attempt_Opening_State_Service::get_state($user_id, $exam_id)
            : null;
        if (
            $queue_ticket === ''
            && !is_array($existing_opening_state)
        ) {
            self::write_start_attempt_opening_state($exam_id, $user_id, 'resume_lookup', 'resume_index_miss', [
                'retry_after_ms' => 1500,
            ]);
        }

        $resume_candidate = self::resolve_resumable_attempt_candidate_for_status($exam_id, $user_id, $attempt_table);
        if (is_array($resume_candidate['attempt'] ?? null)) {
            $expired_attempt_response = self::maybe_build_expired_attempt_finalizing_response(
                (array) $resume_candidate['attempt'],
                $exam,
                $exam_id,
                $user_id
            );
            if ($expired_attempt_response !== null) {
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_status_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $expired_attempt_response,
                    [
                        'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? ((array) $resume_candidate['attempt'])['attempt_id'] ?? 0),
                    ],
                    'pending'
                );

                return $expired_attempt_response;
            }

            $response = self::build_lightweight_resumed_status_response(
                (array) $resume_candidate['attempt'],
                $exam,
                $exam_id,
                $user_id,
                $resume_only,
                $exam_token_input,
                $validate_token_submission
            );

            if (!is_wp_error($response)) {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'ready', 'attempt_ready', [
                    'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? ((array) $resume_candidate['attempt'])['attempt_id'] ?? 0),
                    'resume_source' => (string) ($resume_candidate['source'] ?? ''),
                ]);
                self::record_start_attempt_phase('start_attempt_status_resume_from_index_light', $exam_id, $user_id, [
                    'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? ((array) $resume_candidate['attempt'])['attempt_id'] ?? 0),
                    'duration_ms' => self::measure_elapsed_ms($request_started_at),
                    'result_status' => 'resumed',
                ]);
            }

            self::record_start_attempt_response_ready_phase(
                'start_attempt_status_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $response,
                [
                    'attempt_id' => (int) (((array) $resume_candidate['attempt'])['id'] ?? ((array) $resume_candidate['attempt'])['attempt_id'] ?? 0),
                ],
                'resumed'
            );

            return is_wp_error($response)
                ? self::build_start_attempt_terminal_status_from_error($response)
                : $response;
        }

        $latest_attempt = self::get_latest_attempt_status_candidate($exam_id, $user_id, $attempt_table);

        if ($latest_attempt && (string) ($latest_attempt['status'] ?? '') === 'in_progress') {
            $latest_attempt['exam_id'] = $exam_id;
            $latest_attempt['student_id'] = $user_id;
            $expired_attempt_response = self::maybe_build_expired_attempt_finalizing_response(
                $latest_attempt,
                $exam,
                $exam_id,
                $user_id
            );
            if ($expired_attempt_response !== null) {
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_status_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $expired_attempt_response,
                    [
                        'attempt_id' => (int) ($latest_attempt['id'] ?? $latest_attempt['attempt_id'] ?? 0),
                    ],
                    'pending'
                );

                return $expired_attempt_response;
            }

            $response = self::build_lightweight_resumed_status_response(
                $latest_attempt,
                $exam,
                $exam_id,
                $user_id,
                $resume_only,
                $exam_token_input,
                $validate_token_submission
            );

            if (!is_wp_error($response)) {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'ready', 'attempt_ready', [
                    'attempt_id' => (int) ($latest_attempt['id'] ?? $latest_attempt['attempt_id'] ?? 0),
                    'resume_source' => 'db',
                ]);
                self::record_start_attempt_phase('start_attempt_status_resume_from_db_light', $exam_id, $user_id, [
                    'attempt_id' => (int) ($latest_attempt['id'] ?? $latest_attempt['attempt_id'] ?? 0),
                    'duration_ms' => self::measure_elapsed_ms($request_started_at),
                    'result_status' => 'resumed',
                ]);
            }

            self::record_start_attempt_response_ready_phase(
                'start_attempt_status_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $response,
                [
                    'attempt_id' => (int) ($latest_attempt['id'] ?? $latest_attempt['attempt_id'] ?? 0),
                ],
                'resumed'
            );

            return is_wp_error($response)
                ? self::build_start_attempt_terminal_status_from_error($response)
                : $response;
        }

        if ($latest_attempt && (string) ($latest_attempt['status'] ?? '') === 'completed') {
            self::write_start_attempt_opening_state($exam_id, $user_id, 'completed', 'attempt_completed', [
                'attempt_id' => (int) ($latest_attempt['id'] ?? 0),
            ]);
            $response = self::build_start_attempt_completed_status_response($latest_attempt);
            self::record_start_attempt_response_ready_phase(
                'start_attempt_status_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $response,
                ['attempt_id' => (int) ($latest_attempt['id'] ?? 0)],
                'completed'
            );
            return $response;
        }

        $now = current_time('mysql');
        $within_schedule = (
            (empty($exam['starts_at']) || (string) $exam['starts_at'] <= $now) &&
            (empty($exam['ends_at']) || (string) $exam['ends_at'] >= $now)
        );
        if (!$resume_only && ((string) ($exam['status'] ?? 'draft') !== 'published' || !$within_schedule)) {
            $inactive_details = self::describe_start_attempt_inactive_exam($exam, $now);
            $inactive_reason = (string) ($inactive_details['opening_reason'] ?? 'exam_not_active');
            self::write_start_attempt_opening_state($exam_id, $user_id, 'terminal_error', $inactive_reason, []);
            $response = self::build_start_attempt_terminal_status_response(
                'forbidden',
                (string) ($inactive_details['message'] ?? 'Exam sedang tidak aktif.'),
                403,
                $inactive_details
            );
            self::record_start_attempt_response_ready_phase(
                'start_attempt_status_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $response,
                [
                    'error_code' => 'forbidden',
                    'inactive_error_code' => $inactive_reason,
                ],
                'terminal_error'
            );
            return $response;
        }

        if (!$resume_only && $queue_ticket === '') {
            $token_check = $validate_token_submission($exam_token_input);
            if (is_wp_error($token_check)) {
                self::write_start_attempt_opening_state(
                    $exam_id,
                    $user_id,
                    'terminal_error',
                    self::map_start_attempt_terminal_reason((string) $token_check->get_error_code()),
                    []
                );
                $response = self::build_start_attempt_terminal_status_from_error($token_check);
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_status_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $response,
                    ['error_code' => (string) $token_check->get_error_code()],
                    'terminal_error'
                );
                return $response;
            }
        }

        if ($queue_ticket !== '' && class_exists('CBT_Start_Attempt_Gate_Service')) {
            $gate_result = CBT_Start_Attempt_Gate_Service::get_ticket_status($exam_id, $user_id, $queue_ticket);
            $gate_mode = sanitize_key((string) ($gate_result['mode'] ?? ''));
            if ($gate_mode === 'queued' || $gate_mode === 'admitted') {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'queue_waiting', 'queue_admission_wait', [
                    'queue_ticket' => (string) ($gate_result['queue_ticket'] ?? ''),
                    'retry_after_ms' => (int) ($gate_result['poll_after_ms'] ?? 0),
                    'estimated_wait_seconds' => (int) ($gate_result['estimated_wait_seconds'] ?? 0),
                    'queue_ticket_created_at' => (float) ($gate_result['queue_ticket_created_at'] ?? 0),
                ]);
                $response = self::build_start_attempt_status_gate_response($gate_result);
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_status_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $response,
                    ['queue_ticket' => (string) ($gate_result['queue_ticket'] ?? '')],
                    $gate_mode
                );
                return $response;
            }

            if ($gate_mode === 'disabled') {
                self::write_start_attempt_opening_state($exam_id, $user_id, 'queue_waiting', 'queue_admission_wait', [
                    'queue_ticket' => $queue_ticket,
                    'retry_after_ms' => 1500,
                ]);
                $response = self::build_start_attempt_pending_status_response(
                    'gate_disabled',
                    'Status antrean belum tersedia karena Redis gate tidak aktif.',
                    [
                        'exam_id' => $exam_id,
                        'user_id' => $user_id,
                        'opening_state' => 'queue_waiting',
                        'opening_reason' => 'queue_admission_wait',
                        'queue_ticket' => $queue_ticket,
                        'retry_after_ms' => 1500,
                    ]
                );
                self::record_start_attempt_response_ready_phase(
                    'start_attempt_status_response_ready',
                    $exam_id,
                    $user_id,
                    $request_started_at,
                    $response,
                    ['error_code' => 'gate_disabled'],
                    'pending'
                );
                return $response;
            }

            self::write_start_attempt_opening_state($exam_id, $user_id, 'queue_waiting', 'queue_admission_wait', [
                'queue_ticket' => $queue_ticket,
                'retry_after_ms' => 1500,
            ]);
            $response = self::build_start_attempt_pending_status_response(
                'queue_ticket_not_found',
                'Tiket antrean tidak ditemukan atau sudah kedaluwarsa.',
                [
                    'exam_id' => $exam_id,
                    'user_id' => $user_id,
                    'opening_state' => 'queue_waiting',
                    'opening_reason' => 'queue_admission_wait',
                    'queue_ticket' => $queue_ticket,
                    'retry_after_ms' => 1500,
                ]
            );
            self::record_start_attempt_response_ready_phase(
                'start_attempt_status_response_ready',
                $exam_id,
                $user_id,
                $request_started_at,
                $response,
                ['error_code' => 'queue_ticket_not_found'],
                'pending'
            );
            return $response;
        }

        $pending_context = self::get_start_attempt_pending_context($exam_id, $user_id, [
            'opening_state' => $queue_ticket !== '' ? 'queue_waiting' : ($resume_only ? 'resume_lookup' : 'attempt_creating'),
            'opening_reason' => $queue_ticket !== '' ? 'queue_admission_wait' : ($resume_only ? 'resume_db_miss' : 'attempt_insert_in_progress'),
            'queue_ticket' => $queue_ticket,
            'retry_after_ms' => 1500,
        ]);
        if (
            $resume_only
            && $queue_ticket === ''
            && (string) ($pending_context['opening_state'] ?? '') === 'resume_lookup'
        ) {
            $pending_context['opening_reason'] = 'resume_db_miss';
        }
        $written_pending_state = self::write_start_attempt_opening_state(
            $exam_id,
            $user_id,
            (string) ($pending_context['opening_state'] ?? 'resume_lookup'),
            (string) ($pending_context['opening_reason'] ?? 'resume_db_miss'),
            [
                'attempt_id' => (int) ($pending_context['attempt_id'] ?? 0),
                'queue_ticket' => (string) ($pending_context['queue_ticket'] ?? $queue_ticket),
                'resume_source' => (string) ($pending_context['resume_source'] ?? ''),
                'retry_after_ms' => (int) ($pending_context['retry_after_ms'] ?? 1500),
                'last_stage_at' => (int) ($pending_context['last_stage_at'] ?? 0),
            ]
        );
        if (is_array($written_pending_state)) {
            $pending_context = array_merge($pending_context, $written_pending_state);
        }
        $response = self::build_start_attempt_pending_status_response(
            $resume_only ? 'attempt_pending' : 'start_pending',
            $resume_only
                ? 'Attempt aktif belum ditemukan. Status sesi masih kami pantau.'
                : 'Sesi ujian belum siap. Coba refresh status lagi.',
            [
                'exam_id' => $exam_id,
                'user_id' => $user_id,
                'opening_state' => (string) ($pending_context['opening_state'] ?? ''),
                'opening_reason' => (string) ($pending_context['opening_reason'] ?? ''),
                'attempt_id' => (int) ($pending_context['attempt_id'] ?? 0),
                'queue_ticket' => (string) ($pending_context['queue_ticket'] ?? $queue_ticket),
                'resume_source' => (string) ($pending_context['resume_source'] ?? ''),
                'last_stage_at' => (int) ($pending_context['last_stage_at'] ?? 0),
                'wait_age_seconds' => (int) ($pending_context['wait_age_seconds'] ?? 0),
                'retry_after_ms' => (int) ($pending_context['retry_after_ms'] ?? 1500),
            ]
        );
        self::record_start_attempt_response_ready_phase(
            'start_attempt_status_response_ready',
            $exam_id,
            $user_id,
            $request_started_at,
            $response,
            ['error_code' => $resume_only ? 'attempt_pending' : 'start_pending'],
            'pending'
        );
        return $response;
    }

    /**
     * @param array<string,mixed> $gate_result
     * @return array<string,mixed>|WP_REST_Response
     */
    private static function build_start_attempt_gate_queue_response(array $gate_result)
    {
        $payload = self::append_adaptive_load_payload([
            'status' => 'queued',
            'queue_ticket' => (string) ($gate_result['queue_ticket'] ?? ''),
            'queue_position' => max(1, (int) ($gate_result['queue_position'] ?? 0)),
            'poll_after_ms' => max(250, (int) ($gate_result['poll_after_ms'] ?? 1000)),
            'estimated_wait_seconds' => max(1, (int) ($gate_result['estimated_wait_seconds'] ?? 1)),
            'gate_capacity' => max(1, (int) ($gate_result['gate_capacity'] ?? 50)),
            'gate_window_seconds' => max(1, (int) ($gate_result['gate_window_seconds'] ?? 5)),
            'queue_ticket_created_at' => max(0, (float) ($gate_result['queue_ticket_created_at'] ?? 0)),
        ]);

        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response($payload, 202);
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $gate_result
     * @return array<string,mixed>|WP_REST_Response
     */
    private static function build_start_attempt_status_gate_response(array $gate_result)
    {
        $mode = sanitize_key((string) ($gate_result['mode'] ?? ''));
        if ($mode === 'queued') {
            return self::build_start_attempt_gate_queue_response($gate_result);
        }

        return rest_ensure_response(self::append_adaptive_load_payload([
            'status' => 'admitted',
            'queue_ticket' => (string) ($gate_result['queue_ticket'] ?? ''),
            'queue_position' => max(0, (int) ($gate_result['queue_position'] ?? 0)),
            'estimated_wait_seconds' => max(0, (int) ($gate_result['estimated_wait_seconds'] ?? 0)),
            'poll_after_ms' => max(250, (int) ($gate_result['poll_after_ms'] ?? 1000)),
            'gate_capacity' => max(1, (int) ($gate_result['gate_capacity'] ?? 50)),
            'gate_window_seconds' => max(1, (int) ($gate_result['gate_window_seconds'] ?? 5)),
            'queue_ticket_created_at' => max(0, (float) ($gate_result['queue_ticket_created_at'] ?? 0)),
        ]));
    }

    /**
     * @return array{attempt:?array,source:string}
     */
    private static function resolve_resumable_attempt_candidate(int $exam_id, int $user_id, string $attempt_table): array
    {
        $indexed_attempt = self::get_active_attempt_from_index($user_id, $exam_id, $attempt_table);
        if (is_array($indexed_attempt)) {
            return [
                'attempt' => $indexed_attempt,
                'source' => 'index',
            ];
        }

        $latest_active_attempt = self::get_latest_active_attempt_candidate($exam_id, $user_id, $attempt_table);
        if (is_array($latest_active_attempt)) {
            return [
                'attempt' => self::hydrate_attempt_identity($latest_active_attempt, $exam_id, $user_id),
                'source' => 'db',
            ];
        }

        return [
            'attempt' => null,
            'source' => 'none',
        ];
    }

    /**
     * @return array{attempt:?array,source:string}
     */
    private static function resolve_resumable_attempt_candidate_for_status(int $exam_id, int $user_id, string $attempt_table): array
    {
        $indexed_attempt = self::get_active_attempt_from_index_read_only($user_id, $exam_id, $attempt_table);
        if (is_array($indexed_attempt)) {
            return [
                'attempt' => $indexed_attempt,
                'source' => 'index',
            ];
        }

        $latest_active_attempt = self::get_latest_active_attempt_candidate($exam_id, $user_id, $attempt_table);
        if (is_array($latest_active_attempt)) {
            return [
                'attempt' => self::hydrate_attempt_identity($latest_active_attempt, $exam_id, $user_id),
                'source' => 'db',
            ];
        }

        return [
            'attempt' => null,
            'source' => 'none',
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_latest_active_attempt_candidate(int $exam_id, int $user_id, string $attempt_table): ?array
    {
        global $wpdb;

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status, started_at, finished_at, question_order, option_order, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE exam_id = %d AND student_id = %d AND status = 'in_progress'
                 ORDER BY id DESC
                 LIMIT 1",
                $exam_id,
                $user_id
            ),
            ARRAY_A
        );

        return is_array($attempt) ? $attempt : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_latest_attempt_status_candidate(int $exam_id, int $user_id, string $attempt_table): ?array
    {
        global $wpdb;

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status, started_at, finished_at, question_order, option_order, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE exam_id = %d AND student_id = %d AND status IN ('in_progress', 'completed')
                 ORDER BY FIELD(status, 'in_progress', 'completed'), id DESC
                 LIMIT 1",
                $exam_id,
                $user_id
            ),
            ARRAY_A
        );

        return is_array($attempt) ? $attempt : null;
    }

    /**
     * @param mixed $response
     * @return array<string,mixed>
     */
    private static function get_start_attempt_response_payload($response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'get_data')) {
            $data = $response->get_data();
            return is_array($data) ? $data : [];
        }

        return [];
    }

    private static function map_start_attempt_terminal_reason(string $error_code): string
    {
        $error_code = sanitize_key($error_code);
        if (in_array($error_code, ['token_invalid', 'token_required', 'token_required_local', 'token_invalid_length'], true)) {
            return 'token_invalid';
        }
        if (in_array($error_code, ['exam_not_published', 'exam_not_started', 'exam_ended', 'exam_not_active'], true)) {
            return $error_code;
        }
        if (in_array($error_code, ['not_found', 'invalid_exam_id'], true)) {
            return 'not_found';
        }
        if ($error_code === 'attempt_already_completed') {
            return 'attempt_completed';
        }

        return 'forbidden';
    }

    /**
     * @param array<string,mixed> $exam
     * @return array<string,mixed>
     */
    private static function describe_start_attempt_inactive_exam(array $exam, string $now): array
    {
        $exam_status = sanitize_key((string) ($exam['status'] ?? 'draft'));
        $starts_at = (string) ($exam['starts_at'] ?? '');
        $ends_at = (string) ($exam['ends_at'] ?? '');

        $code = 'exam_not_active';
        $message = 'Exam sedang tidak aktif. Kembali ke daftar exam untuk memeriksa status exam terbaru.';
        $suggestion = 'Kembali ke daftar exam untuk memeriksa status exam terbaru.';

        if ($exam_status !== 'published') {
            $code = 'exam_not_published';
            $message = 'Exam belum dipublikasikan. Kembali ke daftar exam untuk memeriksa status terbaru atau hubungi admin.';
            $suggestion = 'Kembali ke daftar exam untuk memeriksa status terbaru atau hubungi admin.';
        } elseif ($starts_at !== '' && $starts_at > $now) {
            $code = 'exam_not_started';
            $message = 'Exam belum dimulai. Kembali ke daftar exam untuk melihat jadwal terbaru.';
            $suggestion = 'Kembali ke daftar exam untuk melihat jadwal terbaru.';
        } elseif ($ends_at !== '' && $ends_at < $now) {
            $code = 'exam_ended';
            $message = 'Jadwal exam sudah berakhir. Kembali ke daftar exam untuk memeriksa status hasil atau pilih exam lain.';
            $suggestion = 'Kembali ke daftar exam untuk memeriksa status hasil atau pilih exam lain.';
        }

        return [
            'error_code' => $code,
            'opening_reason' => $code,
            'message' => $message,
            'suggestion' => $suggestion,
            'return_to_exam_list_suggestion' => $suggestion,
            'exam_status' => $exam_status,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
        ];
    }

    /**
     * @param array<string,mixed> $exam
     */
    private static function build_start_attempt_inactive_exam_error(array $exam, string $now): WP_Error
    {
        $details = self::describe_start_attempt_inactive_exam($exam, $now);
        return new WP_Error(
            'forbidden',
            (string) ($details['message'] ?? 'Exam sedang tidak aktif.'),
            [
                'status' => 403,
                'opening_reason' => (string) ($details['opening_reason'] ?? 'exam_not_active'),
                'suggestion' => (string) ($details['suggestion'] ?? ''),
                'return_to_exam_list_suggestion' => (string) ($details['return_to_exam_list_suggestion'] ?? ''),
                'exam_status' => (string) ($details['exam_status'] ?? ''),
                'starts_at' => (string) ($details['starts_at'] ?? ''),
                'ends_at' => (string) ($details['ends_at'] ?? ''),
            ]
        );
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private static function write_start_attempt_opening_state(
        int $exam_id,
        int $user_id,
        string $opening_state,
        string $opening_reason,
        array $context = []
    ): ?array {
        if (!class_exists('CBT_Start_Attempt_Opening_State_Service')) {
            return null;
        }

        $previous = CBT_Start_Attempt_Opening_State_Service::get_state($user_id, $exam_id);
        if (
            self::should_ignore_opening_state_downgrade(
                is_array($previous) ? $previous : null,
                $opening_state
            )
        ) {
            self::record_start_attempt_phase('opening_state_downgrade_ignored', $exam_id, $user_id, array_merge([
                'opening_state' => sanitize_key($opening_state),
                'opening_reason' => sanitize_key($opening_reason),
                'previous_opening_state' => sanitize_key((string) ($previous['opening_state'] ?? '')),
                'previous_opening_reason' => sanitize_key((string) ($previous['opening_reason'] ?? '')),
                'attempt_id' => (int) ($previous['attempt_id'] ?? 0),
            ], $context));

            return $previous;
        }

        $record = CBT_Start_Attempt_Opening_State_Service::write_state($user_id, $exam_id, $opening_state, $opening_reason, $context);
        if (!is_array($record)) {
            return null;
        }

        self::record_start_attempt_phase('opening_state_written', $exam_id, $user_id, array_merge([
            'opening_state' => (string) ($record['opening_state'] ?? ''),
            'opening_reason' => (string) ($record['opening_reason'] ?? ''),
            'attempt_id' => (int) ($record['attempt_id'] ?? 0),
            'queue_ticket' => (string) ($record['queue_ticket'] ?? ''),
            'wait_age_seconds' => (int) ($record['wait_age_seconds'] ?? 0),
        ], $context));

        $previous_state = sanitize_key((string) ($previous['opening_state'] ?? ''));
        $current_state = sanitize_key((string) ($record['opening_state'] ?? ''));
        if ($current_state === 'ready' && $previous_state !== 'ready') {
            self::record_start_attempt_phase('opening_state_promoted_ready', $exam_id, $user_id, [
                'attempt_id' => (int) ($record['attempt_id'] ?? 0),
            ]);
        } elseif ($current_state === 'bootstrap_session') {
            self::record_start_attempt_phase('opening_state_pending_bootstrap_session', $exam_id, $user_id, [
                'attempt_id' => (int) ($record['attempt_id'] ?? 0),
            ]);
        } elseif ($current_state === 'bootstrap_questions') {
            self::record_start_attempt_phase('opening_state_pending_bootstrap_questions', $exam_id, $user_id, [
                'attempt_id' => (int) ($record['attempt_id'] ?? 0),
            ]);
        }

        return $record;
    }

    /**
     * @param array<string,mixed>|null $previous
     */
    private static function should_ignore_opening_state_downgrade(?array $previous, string $next_state): bool
    {
        if (!is_array($previous) || (int) ($previous['attempt_id'] ?? 0) <= 0) {
            return false;
        }

        $previous_state = sanitize_key((string) ($previous['opening_state'] ?? ''));
        $next_state = sanitize_key($next_state);
        if ($next_state === '' || in_array($next_state, ['completed', 'terminal_error'], true)) {
            return false;
        }

        $previous_rank = self::opening_state_rank($previous_state);
        $next_rank = self::opening_state_rank($next_state);

        return $previous_rank >= self::opening_state_rank('attempt_created')
            && $next_rank >= 0
            && $next_rank < $previous_rank;
    }

    private static function opening_state_rank(string $opening_state): int
    {
        static $rank = [
            'resume_lookup' => 10,
            'queue_waiting' => 20,
            'attempt_creating' => 30,
            'attempt_created' => 40,
            'bootstrap_session' => 50,
            'bootstrap_questions' => 60,
            'ready' => 70,
            'attempt_finalizing' => 75,
            'completed' => 80,
            'terminal_error' => 80,
        ];

        $opening_state = sanitize_key($opening_state);
        return array_key_exists($opening_state, $rank) ? $rank[$opening_state] : -1;
    }

    /**
     * @param array<string,mixed> $fallback
     * @return array<string,mixed>
     */
    private static function get_start_attempt_pending_context(int $exam_id, int $user_id, array $fallback = []): array
    {
        if (!class_exists('CBT_Start_Attempt_Opening_State_Service')) {
            return [
                'opening_state' => sanitize_key((string) ($fallback['opening_state'] ?? '')),
                'opening_reason' => sanitize_key((string) ($fallback['opening_reason'] ?? '')),
                'attempt_id' => max(0, (int) ($fallback['attempt_id'] ?? 0)),
                'queue_ticket' => (string) ($fallback['queue_ticket'] ?? ''),
                'resume_source' => sanitize_key((string) ($fallback['resume_source'] ?? '')),
                'retry_after_ms' => max(0, (int) ($fallback['retry_after_ms'] ?? 0)),
                'last_stage_at' => max(0, (int) ($fallback['last_stage_at'] ?? 0)),
                'wait_age_seconds' => max(0, (int) ($fallback['wait_age_seconds'] ?? 0)),
            ];
        }

        return CBT_Start_Attempt_Opening_State_Service::build_pending_context($user_id, $exam_id, $fallback);
    }

    /**
     * @param array<string,mixed> $attempt
     * @return array<string,mixed>
     */
    private static function hydrate_attempt_identity(array $attempt, int $exam_id, int $user_id): array
    {
        $attempt['exam_id'] = $exam_id;
        $attempt['student_id'] = $user_id;

        return $attempt;
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function record_start_attempt_resolution(string $resolution, int $exam_id, int $user_id, array $context = []): void
    {
        if (!function_exists('do_action')) {
            return;
        }

        do_action('cbt_start_attempt_resolution', array_merge([
            'resolution' => sanitize_key($resolution),
            'exam_id' => $exam_id,
            'user_id' => $user_id,
        ], $context));
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function record_start_attempt_phase(string $phase, int $exam_id, int $user_id, array $context = []): void
    {
        if (!function_exists('do_action')) {
            return;
        }

        if (
            !isset($context['duration_ms'])
            && isset($context['elapsed_ms'])
            && is_numeric($context['elapsed_ms'])
        ) {
            $context['duration_ms'] = (float) $context['elapsed_ms'];
        }

        if (isset($context['result_status'])) {
            $context['result_status'] = sanitize_key((string) $context['result_status']);
        }

        do_action('cbt_start_attempt_phase', array_merge([
            'phase' => sanitize_key($phase),
            'exam_id' => $exam_id,
            'user_id' => $user_id,
            'recorded_at' => microtime(true),
        ], $context));
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function run_after_start_attempt_response(
        string $phase,
        int $exam_id,
        int $user_id,
        callable $job,
        array $context = []
    ): void {
        self::$deferred_start_attempt_jobs[] = [
            'phase' => sanitize_key($phase),
            'exam_id' => $exam_id,
            'user_id' => $user_id,
            'context' => $context,
            'job' => $job,
        ];

        if (self::$deferred_start_attempt_shutdown_registered) {
            return;
        }

        self::$deferred_start_attempt_shutdown_registered = true;
        register_shutdown_function(static function (): void {
            CBT_REST::flush_deferred_start_attempt_jobs();
        });
    }

    public static function flush_deferred_start_attempt_jobs(): void
    {
        if (self::$deferred_start_attempt_jobs_flushing) {
            return;
        }

        self::$deferred_start_attempt_jobs_flushing = true;

        try {
            self::maybe_finish_start_attempt_response();

            while (!empty(self::$deferred_start_attempt_jobs)) {
                $jobs = self::$deferred_start_attempt_jobs;
                self::$deferred_start_attempt_jobs = [];

                foreach ($jobs as $job) {
                    $started_at = microtime(true);

                    try {
                        ($job['job'])();
                        self::record_start_attempt_phase(
                            (string) ($job['phase'] ?? ''),
                            (int) ($job['exam_id'] ?? 0),
                            (int) ($job['user_id'] ?? 0),
                            array_merge(
                                is_array($job['context'] ?? null) ? $job['context'] : [],
                                [
                                    'ok' => 1,
                                    'elapsed_ms' => self::measure_elapsed_ms($started_at),
                                ]
                            )
                        );
                    } catch (Throwable $throwable) {
                        self::record_start_attempt_phase(
                            (string) ($job['phase'] ?? ''),
                            (int) ($job['exam_id'] ?? 0),
                            (int) ($job['user_id'] ?? 0),
                            array_merge(
                                is_array($job['context'] ?? null) ? $job['context'] : [],
                                [
                                    'ok' => 0,
                                    'elapsed_ms' => self::measure_elapsed_ms($started_at),
                                    'error_class' => get_class($throwable),
                                    'error_message' => $throwable->getMessage(),
                                ]
                            )
                        );
                    }
                }
            }
        } finally {
            self::$deferred_start_attempt_jobs_flushing = false;
        }
    }

    private static function maybe_finish_start_attempt_response(): void
    {
        if (self::$deferred_start_attempt_response_finished) {
            return;
        }

        self::$deferred_start_attempt_response_finished = true;

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }

        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
    }

    private static function measure_elapsed_ms(float $started_at): float
    {
        return round(max(0, (microtime(true) - $started_at) * 1000), 3);
    }

    /**
     * @param mixed $response
     */
    private static function detect_start_attempt_result_status($response, string $fallback = 'terminal_error'): string
    {
        $fallback = sanitize_key($fallback);

        if (is_wp_error($response)) {
            return $response->get_error_code() === 'attempt_already_completed' ? 'completed' : ($fallback !== '' ? $fallback : 'terminal_error');
        }

        $payload = [];
        if (is_array($response)) {
            $payload = $response;
        } elseif (is_object($response) && method_exists($response, 'get_data')) {
            $data = $response->get_data();
            $payload = is_array($data) ? $data : [];
        }

        $status = sanitize_key((string) ($payload['status'] ?? ''));
        if ($status === '') {
            return $fallback !== '' ? $fallback : 'terminal_error';
        }

        return $status;
    }

    /**
     * @param mixed $response
     */
    private static function record_start_attempt_response_ready_phase(
        string $phase,
        int $exam_id,
        int $user_id,
        float $request_started_at,
        $response,
        array $context = [],
        string $fallback_status = 'terminal_error'
    ): string {
        $result_status = self::detect_start_attempt_result_status($response, $fallback_status);
        self::record_start_attempt_phase($phase, $exam_id, $user_id, array_merge(
            $context,
            [
                'duration_ms' => self::measure_elapsed_ms($request_started_at),
                'elapsed_ms' => self::measure_elapsed_ms($request_started_at),
                'result_status' => $result_status,
            ]
        ));

        return $result_status;
    }

    /**
     * @return array<string,mixed>|WP_REST_Response
     */
    private static function build_start_attempt_completed_status_response(array $attempt)
    {
        return rest_ensure_response(self::append_adaptive_load_payload([
            'status' => 'completed',
            'attempt_id' => (int) ($attempt['id'] ?? 0),
            'finished_at' => (string) ($attempt['finished_at'] ?? ''),
        ]));
    }

    /**
     * @return array<string,mixed>|WP_REST_Response
     */
    private static function build_start_attempt_pending_status_response(string $error_code = '', string $error_message = '', array $context = [])
    {
        $retry_after_ms = max(
            0,
            (int) ($context['retry_after_ms'] ?? 1500)
        );
        $payload = [
            'status' => 'pending',
            'error_code' => sanitize_key($error_code),
            'error_message' => (string) $error_message,
            'http_status' => 200,
            'retry_after_ms' => $retry_after_ms > 0 ? $retry_after_ms : 1500,
        ];

        $exam_id = max(0, (int) ($context['exam_id'] ?? 0));
        $user_id = max(0, (int) ($context['user_id'] ?? 0));
        if ($exam_id > 0 && $user_id > 0) {
            $pending_context = self::get_start_attempt_pending_context($exam_id, $user_id, array_merge($context, [
                'retry_after_ms' => $payload['retry_after_ms'],
            ]));
            $payload['opening_state'] = (string) ($pending_context['opening_state'] ?? '');
            $payload['opening_reason'] = (string) ($pending_context['opening_reason'] ?? '');
            $payload['attempt_id'] = max(0, (int) ($pending_context['attempt_id'] ?? 0));
            $payload['queue_ticket'] = (string) ($pending_context['queue_ticket'] ?? '');
            $payload['last_stage_at'] = max(0, (int) ($pending_context['last_stage_at'] ?? 0));
            $payload['wait_age_seconds'] = max(0, (int) ($pending_context['wait_age_seconds'] ?? 0));
            if (!empty($pending_context['resume_source'])) {
                $payload['resume_source'] = (string) $pending_context['resume_source'];
            }
        }

        if (array_key_exists('finalize_pending', $context)) {
            $payload['finalize_pending'] = !empty($context['finalize_pending']) ? 1 : 0;
        }
        if (array_key_exists('finalize_poll_after_ms', $context)) {
            $payload['finalize_poll_after_ms'] = max(0, (int) ($context['finalize_poll_after_ms'] ?? 0));
        }
        if (!empty($context['expired_at'])) {
            $payload['expired_at'] = (string) $context['expired_at'];
        }

        return rest_ensure_response(self::append_adaptive_load_payload($payload));
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $exam
     * @return array<string,mixed>|null
     */
    private static function maybe_build_expired_attempt_finalizing_response(
        array $attempt,
        array $exam,
        int $exam_id,
        int $user_id
    ) {
        if (!class_exists('CBT_Expired_Attempt_Finalize_Service')) {
            return null;
        }

        $resolved_duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            (int) ($exam['duration_minutes'] ?? 0)
        );
        $finalize_state = CBT_Expired_Attempt_Finalize_Service::maybe_schedule_for_attempt(
            $attempt,
            $resolved_duration_minutes,
            max(0, (int) ($exam['created_by'] ?? 0))
        );
        if (empty($finalize_state['finalize_pending'])) {
            return null;
        }

        $attempt_id = (int) ($attempt['id'] ?? $attempt['attempt_id'] ?? 0);
        $retry_after_ms = max(
            250,
            (int) ($finalize_state['finalize_poll_after_ms'] ?? CBT_Expired_Attempt_Finalize_Service::get_default_poll_after_ms())
        );
        self::write_start_attempt_opening_state($exam_id, $user_id, 'attempt_finalizing', 'attempt_finalizing', [
            'attempt_id' => $attempt_id,
            'retry_after_ms' => $retry_after_ms,
            'ttl_seconds' => max(90, (int) ceil($retry_after_ms / 1000) + 90),
        ]);

        return self::build_start_attempt_pending_status_response(
            'attempt_finalizing',
            'Waktu ujian habis. Hasil sedang disinkronkan.',
            [
                'exam_id' => $exam_id,
                'user_id' => $user_id,
                'opening_state' => 'attempt_finalizing',
                'opening_reason' => 'attempt_finalizing',
                'attempt_id' => $attempt_id,
                'retry_after_ms' => $retry_after_ms,
                'finalize_pending' => 1,
                'finalize_poll_after_ms' => $retry_after_ms,
                'expired_at' => (string) ($finalize_state['expired_at'] ?? ''),
            ]
        );
    }

    /**
     * @return array<string,mixed>|WP_REST_Response
     */
    private static function build_start_attempt_terminal_status_response(string $error_code, string $error_message, int $http_status = 400, array $extra = [])
    {
        $payload = [
            'status' => 'terminal_error',
            'error_code' => sanitize_key($error_code),
            'error_message' => (string) $error_message,
            'http_status' => max(400, $http_status),
        ];

        $allowed_extra_keys = [
            'opening_state',
            'opening_reason',
            'suggestion',
            'return_to_exam_list_suggestion',
            'exam_status',
            'starts_at',
            'ends_at',
        ];
        foreach ($allowed_extra_keys as $key) {
            if (!array_key_exists($key, $extra)) {
                continue;
            }
            $payload[$key] = is_scalar($extra[$key]) ? (string) $extra[$key] : '';
        }

        if (!isset($payload['opening_state'])) {
            $payload['opening_state'] = 'terminal_error';
        }
        if (!isset($payload['opening_reason'])) {
            $payload['opening_reason'] = sanitize_key($error_code);
        }

        return rest_ensure_response(self::append_adaptive_load_payload($payload));
    }

    /**
     * @return array<string,mixed>|WP_REST_Response
     */
    private static function build_start_attempt_terminal_status_from_error(WP_Error $error)
    {
        $error_data = $error->get_error_data();
        $http_status = is_array($error_data) ? (int) ($error_data['status'] ?? 400) : 400;
        $extra = [];
        if (is_array($error_data)) {
            foreach (['opening_reason', 'suggestion', 'return_to_exam_list_suggestion', 'exam_status', 'starts_at', 'ends_at'] as $key) {
                if (array_key_exists($key, $error_data)) {
                    $extra[$key] = $error_data[$key];
                }
            }
        }
        return self::build_start_attempt_terminal_status_response(
            (string) $error->get_error_code(),
            (string) $error->get_error_message(),
            $http_status,
            $extra
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function append_adaptive_load_payload(array $payload): array
    {
        if (class_exists('CBT_Adaptive_Load_Service')) {
            $payload['adaptive_load'] = CBT_Adaptive_Load_Service::get_frontend_payload();
        }

        return $payload;
    }

    private static function validate_exam_token_or_error(string $expected_token, string $submitted_token)
    {
        $expected_token = CBT_Auth::normalize_exam_token_input($expected_token);
        $submitted_token = CBT_Auth::normalize_exam_token_input($submitted_token);

        if ($expected_token === '') {
            return true;
        }

        if ($submitted_token === '') {
            return new WP_Error('token_required', 'Token ujian wajib diisi', ['status' => 400]);
        }

        if (!hash_equals($expected_token, $submitted_token)) {
            return new WP_Error('token_invalid', 'Token ujian tidak valid', ['status' => 403]);
        }

        return true;
    }

    /**
     * @param callable(string):mixed $validate_token_submission
     * @return array<string,mixed>|WP_Error
     */
    private static function build_resumed_attempt_response(
        array $attempt,
        array $exam,
        int $exam_id,
        int $user_id,
        string $attempt_table,
        bool $resume_only,
        string $exam_token_input,
        callable $validate_token_submission
    ) {
        if (!$resume_only) {
            $token_check = $validate_token_submission($exam_token_input);
            if (is_wp_error($token_check)) {
                return $token_check;
            }
        }

        $attempt_id = (int) ($attempt['id'] ?? $attempt['attempt_id'] ?? 0);
        if ($attempt_id <= 0) {
            return new WP_Error('attempt_not_found', 'Tidak ada attempt ujian aktif untuk dilanjutkan.', ['status' => 404]);
        }

        $resolved_duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            (int) ($exam['duration_minutes'] ?? 0)
        );
        $runtime_attempt = [
            'id' => $attempt_id,
            'exam_id' => $exam_id,
            'student_id' => $user_id,
            'status' => (string) ($attempt['status'] ?? 'in_progress'),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'question_order' => (string) ($attempt['question_order'] ?? ''),
            'option_order' => (string) ($attempt['option_order'] ?? ''),
            'extra_time_minutes' => (int) ($attempt['extra_time_minutes'] ?? 0),
        ];

        self::ensure_runtime_attempt_state($runtime_attempt, $resolved_duration_minutes);
        self::sync_active_attempt_index($runtime_attempt);
        self::sync_live_attempt_roster($runtime_attempt, [
            'teacher_id' => (int) ($exam['created_by'] ?? 0),
            'exam_title' => (string) ($exam['title'] ?? ''),
        ]);
        $attempt_contract = self::build_attempt_runtime_snapshot_contract(
            $runtime_attempt,
            $exam,
            [],
            $attempt_table
        );
        self::sync_attempt_runtime_snapshots(
            $runtime_attempt,
            $exam,
            (array) ($attempt_contract['question_order_ids'] ?? []),
            (array) ($attempt_contract['question_number_map'] ?? []),
            (array) ($attempt_contract['option_order_map'] ?? []),
            (array) ($attempt_contract['question_manifest'] ?? [])
        );

        $attempt_timer = self::build_attempt_timer_payload([
            'id' => $attempt_id,
            'status' => (string) ($attempt['status'] ?? 'in_progress'),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'extra_time_minutes' => (int) ($attempt['extra_time_minutes'] ?? 0),
        ], (int) ($exam['duration_minutes'] ?? 0));
        $attempt_sync_meta = self::build_attempt_question_sync_meta($runtime_attempt, $exam, $attempt_table);

        return rest_ensure_response(self::append_adaptive_load_payload([
            'attempt_id' => $attempt_id,
            'status' => 'resumed',
            'duration_minutes' => $resolved_duration_minutes,
            'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? 0)),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'remaining_seconds' => (int) ($attempt_timer['remaining_seconds'] ?? max(0, $resolved_duration_minutes * MINUTE_IN_SECONDS)),
            'server_now' => (string) ($attempt_timer['server_now'] ?? current_time('mysql')),
            'question_revision' => CBT_Cache::get_exam_revision_meta($exam_id),
            'question_order_signature' => (string) ($attempt_sync_meta['question_order_signature'] ?? ''),
        ]));
    }

    /**
     * @param callable(string):mixed $validate_token_submission
     * @return array<string,mixed>|WP_Error
     */
    private static function build_lightweight_resumed_status_response(
        array $attempt,
        array $exam,
        int $exam_id,
        int $user_id,
        bool $resume_only,
        string $exam_token_input,
        callable $validate_token_submission
    ) {
        if (!$resume_only) {
            $token_check = $validate_token_submission($exam_token_input);
            if (is_wp_error($token_check)) {
                return $token_check;
            }
        }

        $attempt_id = (int) ($attempt['id'] ?? $attempt['attempt_id'] ?? 0);
        if ($attempt_id <= 0) {
            return new WP_Error('attempt_not_found', 'Tidak ada attempt ujian aktif untuk dilanjutkan.', ['status' => 404]);
        }

        $resolved_duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            (int) ($exam['duration_minutes'] ?? 0)
        );
        $attempt_timer = self::build_attempt_timer_payload([
            'id' => $attempt_id,
            'status' => (string) ($attempt['status'] ?? 'in_progress'),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'extra_time_minutes' => (int) ($attempt['extra_time_minutes'] ?? 0),
            'duration_minutes' => $resolved_duration_minutes,
        ], (int) ($exam['duration_minutes'] ?? 0));

        return rest_ensure_response(self::append_adaptive_load_payload([
            'attempt_id' => $attempt_id,
            'status' => 'resumed',
            'duration_minutes' => $resolved_duration_minutes,
            'extra_time_minutes' => max(0, (int) ($attempt['extra_time_minutes'] ?? 0)),
            'started_at' => (string) ($attempt['started_at'] ?? ''),
            'remaining_seconds' => (int) ($attempt_timer['remaining_seconds'] ?? max(0, $resolved_duration_minutes * MINUTE_IN_SECONDS)),
            'server_now' => (string) ($attempt_timer['server_now'] ?? current_time('mysql')),
            'question_revision' => CBT_Cache::get_exam_revision_meta($exam_id),
            'question_order_signature' => self::build_attempt_question_order_signature_from_attempt($attempt),
        ]));
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_active_attempt_from_index(int $user_id, int $exam_id, string $attempt_table): ?array
    {
        global $wpdb;

        if (
            $user_id <= 0
            || $exam_id <= 0
            || !class_exists('CBT_Active_Attempt_Index')
        ) {
            return null;
        }

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id($user_id, $exam_id);
        if ($attempt_id <= 0) {
            return null;
        }

        $runtime_attempt = self::get_live_runtime_attempt_envelope($attempt_id, $user_id);
        if (
            is_array($runtime_attempt)
            && (int) ($runtime_attempt['exam_id'] ?? 0) === $exam_id
            && (string) ($runtime_attempt['status'] ?? '') === 'in_progress'
        ) {
            CBT_Active_Attempt_Index::set_active_attempt($runtime_attempt);
            return $runtime_attempt;
        }

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, finished_at, question_order, option_order, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );

        if (
            !is_array($attempt)
            || (int) ($attempt['student_id'] ?? 0) !== $user_id
            || (int) ($attempt['exam_id'] ?? 0) !== $exam_id
            || (string) ($attempt['status'] ?? '') !== 'in_progress'
        ) {
            CBT_Active_Attempt_Index::clear_active_attempt($user_id, $exam_id, $attempt_id);
            return null;
        }

        CBT_Active_Attempt_Index::set_active_attempt($attempt);
        return $attempt;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_active_attempt_from_index_read_only(int $user_id, int $exam_id, string $attempt_table): ?array
    {
        global $wpdb;

        if (
            $user_id <= 0
            || $exam_id <= 0
            || !class_exists('CBT_Active_Attempt_Index')
        ) {
            return null;
        }

        $attempt_id = CBT_Active_Attempt_Index::get_active_attempt_id($user_id, $exam_id);
        if ($attempt_id <= 0) {
            return null;
        }

        $runtime_attempt = self::get_live_runtime_attempt_envelope($attempt_id, $user_id);
        if (
            is_array($runtime_attempt)
            && (int) ($runtime_attempt['exam_id'] ?? 0) === $exam_id
            && (string) ($runtime_attempt['status'] ?? '') === 'in_progress'
        ) {
            return $runtime_attempt;
        }

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, finished_at, question_order, option_order, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );

        if (
            !is_array($attempt)
            || (int) ($attempt['student_id'] ?? 0) !== $user_id
            || (int) ($attempt['exam_id'] ?? 0) !== $exam_id
            || (string) ($attempt['status'] ?? '') !== 'in_progress'
        ) {
            return null;
        }

        return $attempt;
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function sync_active_attempt_index(array $attempt): void
    {
        if (!class_exists('CBT_Active_Attempt_Index')) {
            return;
        }

        CBT_Active_Attempt_Index::set_active_attempt($attempt);
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $context
     */
    private static function sync_live_attempt_roster(array $attempt, array $context = []): void
    {
        if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
            return;
        }

        CBT_Live_Attempt_Roster_Index::sync_attempt($attempt, $context);
    }
}
