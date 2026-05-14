<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Finish_Exam_Routes
{
    public static function finish_exam(WP_REST_Request $request)
    {
        global $wpdb;

        $attempt_id = (int) $request->get_param('attempt_id');
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);

        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can finish exam', ['status' => 403]);
        }

        if ($attempt_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id is required', ['status' => 400]);
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';

        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, finished_at, score, max_score, duration_seconds
                 FROM {$attempt_table}
                 WHERE id = %d",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt not found', ['status' => 404]);
        }

        if ((int) $attempt['student_id'] !== $user_id) {
            return new WP_Error('forbidden', 'You cannot finish this attempt', ['status' => 403]);
        }

        if ((string) ($attempt['status'] ?? '') === 'completed') {
            return rest_ensure_response(self::build_idempotent_finish_exam_response($attempt_id, $attempt));
        }

        if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
            return new WP_Error('attempt_closed', 'Attempt already finished', ['status' => 400]);
        }

        $finish_token = CBT_Runtime::acquire_finish_lock($attempt_id);
        $fallback_finish_lock = false;
        if ($finish_token === '') {
            $fallback_finish_lock = CBT_Cache::acquire_lock('finish_attempt:' . $attempt_id, 45, [
                'type' => 'finish_attempt',
                'attempt_id' => $attempt_id,
            ]);
            if (!$fallback_finish_lock) {
                return new WP_Error('attempt_finish_locked', 'Finalisasi ujian sedang diproses. Coba lagi beberapa detik.', ['status' => 429]);
            }
        }

        try {
            $completion_result = self::finalize_attempt_completion($attempt_id);
            if (is_wp_error($completion_result)) {
                return $completion_result;
            }

            return rest_ensure_response($completion_result);
        } finally {
            if ($finish_token !== '') {
                CBT_Runtime::release_finish_lock($attempt_id, $finish_token);
            }
            if ($fallback_finish_lock) {
                CBT_Cache::release_lock('finish_attempt:' . $attempt_id);
            }
        }
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        global $wpdb;

        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id is required', ['status' => 400]);
        }

        $defer_invalidation = !empty($options['defer_invalidation']);

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, question_order, option_order, score, max_score, started_at, duration_seconds, extra_time_minutes, created_at
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt not found', ['status' => 404]);
        }

        $duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            self::get_exam_duration_minutes((int) ($attempt['exam_id'] ?? 0))
        );
        if ($duration_minutes > 0) {
            self::ensure_runtime_attempt_state($attempt, $duration_minutes);
        }
        $flush_result = CBT_Runtime::flush_attempt($attempt_id, true);
        if (
            is_array($flush_result) &&
            (int) ($flush_result['runtime_used'] ?? 0) === 1 &&
            (int) ($flush_result['pending_count'] ?? 0) > 0
        ) {
            return new WP_Error(
                'runtime_flush_pending',
                'Finalisasi ujian menunggu sinkronisasi jawaban. Coba lagi beberapa detik.',
                [
                    'status' => 409,
                    'pending_count' => (int) ($flush_result['pending_count'] ?? 0),
                    'flushed' => (int) ($flush_result['flushed'] ?? 0),
                ]
            );
        }

        $score_snapshot = self::build_attempt_score_snapshot($attempt);
        $score = (float) ($score_snapshot['score'] ?? 0.0);
        $max_score = (float) ($score_snapshot['max_score'] ?? 0.0);
        $percentage = (float) ($score_snapshot['percentage'] ?? 0.0);
        $review_summary = isset($score_snapshot['review_summary']) && is_array($score_snapshot['review_summary'])
            ? (array) $score_snapshot['review_summary']
            : [];
        $kkm_percentage = self::get_exam_kkm_percentage((int) ($attempt['exam_id'] ?? 0));
        $show_student_result = self::get_exam_show_student_result((int) ($attempt['exam_id'] ?? 0));
        $pass_meta = self::build_result_pass_meta($score, $max_score, $kkm_percentage);
        $finished_at = is_string($finished_at) && $finished_at !== '' ? $finished_at : current_time('mysql');

        $duration_seconds = self::calculate_attempt_duration_seconds($attempt, $finished_at);

        $updated = $wpdb->update(
            $attempt_table,
            [
                'status' => 'completed',
                'score' => $score,
                'max_score' => $max_score,
                'finished_at' => $finished_at,
                'duration_seconds' => $duration_seconds,
                'updated_at' => $finished_at,
            ],
            ['id' => $attempt_id],
            ['%s', '%f', '%f', '%s', '%d', '%s'],
            ['%d']
        );
        if ($updated === false) {
            return new WP_Error('db_failed', 'Failed to finish exam', ['status' => 500]);
        }

        if (class_exists('CBT_Attempt_Session_Snapshot_Cache')) {
            CBT_Attempt_Session_Snapshot_Cache::update_attempt_status($attempt_id, 'completed');
        }
        if (class_exists('CBT_Attempt_Question_Contract_Cache')) {
            CBT_Attempt_Question_Contract_Cache::update_attempt_status($attempt_id, 'completed');
        }

        $student_id = (int) ($attempt['student_id'] ?? 0);
        CBT_Runtime::clear_attempt_runtime($attempt_id);
        if (class_exists('CBT_Active_Attempt_Index')) {
            CBT_Active_Attempt_Index::clear_active_attempt(
                $student_id,
                (int) ($attempt['exam_id'] ?? 0),
                $attempt_id
            );
        }
        if (!$defer_invalidation) {
            CBT_Cache::invalidate_attempt($attempt_id);
            CBT_Cache::invalidate_analytics();
            CBT_Cache::invalidate_analytics_exam((int) ($attempt['exam_id'] ?? 0));
            if ($student_id > 0) {
                CBT_Cache::invalidate_user($student_id);
                CBT_UI_State::clear_attempt_state($student_id, $attempt_id);
            }
        }

        $response = [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
            'show_student_result' => $show_student_result,
            'result_view_mode' => ($show_student_result === 1 ? 'full' : 'restricted'),
            'submission_summary' => self::build_result_submission_summary($review_summary),
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'finished_at' => $finished_at,
            'kkm_percentage' => $pass_meta['kkm_percentage'],
            'passing_score' => $pass_meta['passing_score'],
            'is_passed' => $pass_meta['is_passed'],
            'pass_label' => $pass_meta['pass_label'],
            'result_tone' => $pass_meta['result_tone'],
        ];

        if ($show_student_result !== 1) {
            unset(
                $response['score'],
                $response['max_score'],
                $response['percentage'],
                $response['kkm_percentage'],
                $response['passing_score'],
                $response['is_passed'],
                $response['pass_label'],
                $response['result_tone']
            );
        }

        return $response;
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function calculate_attempt_duration_seconds(array $attempt, string $finished_at): int
    {
        $finished_ts = self::local_datetime_to_timestamp($finished_at) ?? time();
        $started_ts = self::local_datetime_to_timestamp((string) ($attempt['started_at'] ?? ''));

        if ($started_ts === null) {
            $started_ts = self::local_datetime_to_timestamp((string) ($attempt['created_at'] ?? ''));
        }

        if ($started_ts === null) {
            return max(0, (int) ($attempt['duration_seconds'] ?? 0));
        }

        return max(0, $finished_ts - $started_ts);
    }

    /**
     * @param array<string,mixed> $attempt
     * @return array<string,mixed>
     */
    private static function build_idempotent_finish_exam_response(int $attempt_id, array $attempt): array
    {
        $score = is_finite((float) ($attempt['score'] ?? 0))
            ? round((float) ($attempt['score'] ?? 0), 2)
            : 0.0;
        $max_score = is_finite((float) ($attempt['max_score'] ?? 0))
            ? round(max(0.0, (float) ($attempt['max_score'] ?? 0)), 2)
            : 0.0;
        $percentage = ($max_score > 0)
            ? round(($score / $max_score) * 100, 2)
            : 0.0;
        $show_student_result = self::get_exam_show_student_result((int) ($attempt['exam_id'] ?? 0));
        $kkm_percentage = self::get_exam_kkm_percentage((int) ($attempt['exam_id'] ?? 0));
        $pass_meta = self::build_result_pass_meta($score, $max_score, $kkm_percentage);

        $response = [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
            'show_student_result' => $show_student_result,
            'result_view_mode' => ($show_student_result === 1 ? 'full' : 'restricted'),
            'submission_summary' => [
                'total_questions' => 0,
                'answered_questions' => 0,
                'pending_manual_questions' => 0,
            ],
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'finished_at' => (string) ($attempt['finished_at'] ?? ''),
            'kkm_percentage' => $pass_meta['kkm_percentage'],
            'passing_score' => $pass_meta['passing_score'],
            'is_passed' => $pass_meta['is_passed'],
            'pass_label' => $pass_meta['pass_label'],
            'result_tone' => $pass_meta['result_tone'],
        ];

        if ($show_student_result !== 1) {
            unset(
                $response['score'],
                $response['max_score'],
                $response['percentage'],
                $response['kkm_percentage'],
                $response['passing_score'],
                $response['is_passed'],
                $response['pass_label'],
                $response['result_tone']
            );
        }

        return $response;
    }
}
