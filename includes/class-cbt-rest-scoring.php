<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Scoring_Helpers
{
    /**
     * @param array<int,array<string,mixed>> $answers
     * @return array<string,mixed>|WP_Error
     */
    private static function submit_answers_batch_internal(int $attempt_id, array $answers, int $user_id, string $role)
    {
        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can submit answers', ['status' => 403]);
        }

        if ($attempt_id <= 0 || empty($answers)) {
            return new WP_Error('invalid_payload', 'attempt_id and answers are required', ['status' => 400]);
        }

        $attempt = self::get_attempt_for_submission($attempt_id, $user_id, false);
        if (is_wp_error($attempt)) {
            return $attempt;
        }

        $question_ids = [];
        foreach ($answers as $answer_row) {
            if (!is_array($answer_row)) {
                continue;
            }

            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id > 0) {
                $question_ids[] = $question_id;
            }
        }
        $question_context_map = self::get_cached_question_submission_contexts($question_ids);

        $prepared_entries = [];
        foreach ($answers as $answer_row) {
            if (!is_array($answer_row)) {
                return new WP_Error('invalid_payload', 'Each answer item must be an object', ['status' => 400]);
            }

            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                return new WP_Error('invalid_payload', 'question_id is required for each answer item', ['status' => 400]);
            }

            $prepared = self::prepare_submission_entry(
                $attempt,
                $question_id,
                $answer_row['answer'] ?? null,
                is_array($question_context_map[$question_id] ?? null) ? $question_context_map[$question_id] : null
            );
            if (is_wp_error($prepared)) {
                return $prepared;
            }

            $prepared_entries[$question_id] = $prepared;
        }

        $prepared_entries = array_values($prepared_entries);
        if (empty($prepared_entries)) {
            return new WP_Error('invalid_payload', 'No valid answers submitted', ['status' => 400]);
        }

        $duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            self::get_exam_duration_minutes((int) ($attempt['exam_id'] ?? 0))
        );
        $runtime_used = false;
        $buffered = 0;
        $flushed = 0;
        $pending_count = 0;

        if (CBT_Runtime::is_ready()) {
            $buffer_result = CBT_Runtime::buffer_entries($attempt, $duration_minutes, $prepared_entries);
            $runtime_used = !empty($buffer_result['runtime_used']);
            $buffered = (int) ($buffer_result['buffered'] ?? 0);
            $flushed = (int) ($buffer_result['flushed'] ?? 0);
            $pending_count = (int) ($buffer_result['pending_count'] ?? 0);
        }

        if (!$runtime_used) {
            if (CBT_Runtime::is_buffer_enabled() && !CBT_Runtime::fallback_to_db_enabled()) {
                return new WP_Error('runtime_buffer_unavailable', 'Redis runtime CBT tidak siap dan fallback DB dimatikan.', ['status' => 503]);
            }

            $persisted = CBT_Runtime::persist_entries_to_db($attempt_id, $prepared_entries);
            if (is_wp_error($persisted)) {
                return $persisted;
            }

            CBT_Cache::invalidate_attempt($attempt_id);
            $flushed = count($prepared_entries);
        }

        return [
            'attempt_id' => $attempt_id,
            'accepted_count' => count($prepared_entries),
            'buffered' => $buffered,
            'flushed' => $flushed,
            'pending_count' => $pending_count,
            'runtime_used' => $runtime_used ? 1 : 0,
            'items' => array_map([self::class, 'format_submission_response_item'], $prepared_entries),
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function get_attempt_for_submission(int $attempt_id, int $user_id, bool $ensure_runtime_state = true)
    {
        global $wpdb;

        $runtime_attempt = self::get_live_runtime_attempt_envelope($attempt_id, $user_id);
        if (is_array($runtime_attempt)) {
            return $runtime_attempt;
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, extra_time_minutes
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt atau soal tidak ditemukan pada exam ini', ['status' => 404]);
        }

        if ((int) ($attempt['student_id'] ?? 0) !== $user_id) {
            return new WP_Error('forbidden', 'You cannot submit answer for this attempt', ['status' => 403]);
        }

        if ((string) ($attempt['status'] ?? '') !== 'in_progress') {
            return new WP_Error('attempt_closed', 'Attempt already finished', ['status' => 400]);
        }

        $duration_minutes = self::resolve_attempt_duration_minutes(
            $attempt,
            self::get_exam_duration_minutes((int) ($attempt['exam_id'] ?? 0))
        );
        if ($ensure_runtime_state && $duration_minutes > 0) {
            self::ensure_runtime_attempt_state($attempt, $duration_minutes);
        }

        return $attempt;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function get_attempt_for_submit_flow_metric(int $attempt_id, int $user_id)
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, finished_at
                 FROM {$attempt_table}
                 WHERE id = %d
                 LIMIT 1",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return new WP_Error('not_found', 'Attempt tidak ditemukan.', ['status' => 404]);
        }

        if ((int) ($attempt['student_id'] ?? 0) !== $user_id) {
            return new WP_Error('forbidden', 'You cannot log submit telemetry for this attempt', ['status' => 403]);
        }

        if (!in_array((string) ($attempt['status'] ?? ''), ['in_progress', 'completed'], true)) {
            return new WP_Error('invalid_submit_flow_metric_attempt_status', 'Status attempt tidak didukung untuk telemetry submit.', ['status' => 400]);
        }

        return $attempt;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function prepare_submission_entry(array $attempt, int $question_id, $answer_input, ?array $question_context = null)
    {
        $question_context = is_array($question_context) ? $question_context : self::get_cached_question_submission_context($question_id);
        if (!$question_context || (int) ($question_context['exam_id'] ?? 0) !== (int) ($attempt['exam_id'] ?? 0)) {
            return new WP_Error('not_found', 'Attempt atau soal tidak ditemukan pada exam ini', ['status' => 404]);
        }

        $now = current_time('mysql');

        if (self::is_empty_answer_submission($answer_input)) {
            return [
                'question_id' => $question_id,
                'selected_option_ids' => '',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0.0,
                'answered_at' => $now,
                'clear' => 1,
                'deferred' => 0,
                'answer' => null,
            ];
        }

        $question_type = (string) ($question_context['question_type'] ?? '');
        $deferred_scoring = self::should_defer_submit_scoring();
        $can_defer_scoring = $deferred_scoring && $question_type !== 'ordering';
        if ($can_defer_scoring) {
            $normalized_storage = self::normalize_submission_for_storage($question_type, $answer_input);
            $evaluated = [
                'selected_option_ids' => $normalized_storage['selected_option_ids'],
                'answer_text' => $normalized_storage['answer_text'],
                'is_correct' => null,
                'score_awarded' => 0.0,
            ];
        } else {
            $evaluated = self::evaluate_answer_from_submission_context($question_context, $answer_input);
        }

        return [
            'question_id' => $question_id,
            'selected_option_ids' => (string) ($evaluated['selected_option_ids'] ?? ''),
            'answer_text' => (string) ($evaluated['answer_text'] ?? ''),
            'is_correct' => $evaluated['is_correct'] ?? null,
            'score_awarded' => (float) ($evaluated['score_awarded'] ?? 0),
            'answered_at' => $now,
            'clear' => 0,
            'deferred' => $can_defer_scoring ? 1 : 0,
            'answer' => self::normalize_runtime_answer_value($answer_input),
        ];
    }

    /**
     * @param mixed $answer_input
     * @return mixed
     */
    private static function normalize_runtime_answer_value($answer_input)
    {
        if ($answer_input === null || is_scalar($answer_input)) {
            return $answer_input;
        }

        return is_array($answer_input) ? $answer_input : null;
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,mixed>
     */
    private static function format_submission_response_item(array $entry): array
    {
        return [
            'question_id' => (int) ($entry['question_id'] ?? 0),
            'is_correct' => array_key_exists('is_correct', $entry) ? $entry['is_correct'] : null,
            'score_awarded' => (float) ($entry['score_awarded'] ?? 0),
            'deferred' => !empty($entry['deferred']) ? 1 : 0,
            'cleared' => !empty($entry['clear']) ? 1 : 0,
        ];
    }

    private static function build_attempt_score_snapshot(array $attempt): array
    {
        $review_items = self::build_attempt_review_items($attempt);
        self::sync_attempt_auto_scores((int) ($attempt['id'] ?? 0), $review_items);

        $review_summary = self::summarize_review_items($review_items);
        $review_score = 0.0;
        $review_max_score = 0.0;
        foreach ($review_items as $item_row) {
            $item = (array) $item_row;
            $review_score += max(0.0, (float) ($item['score_awarded'] ?? 0));
            $review_max_score += max(0.0, (float) ($item['points'] ?? 0));
        }

        $review_score = round($review_score, 2);
        $review_max_score = round($review_max_score, 2);
        $stored_score = (float) ($attempt['score'] ?? 0);
        $stored_max_score = (float) ($attempt['max_score'] ?? 0);
        $has_score_drift = (
            abs($stored_score - $review_score) > 0.0001 ||
            abs($stored_max_score - $review_max_score) > 0.0001
        );

        return [
            'review_items' => $review_items,
            'review_summary' => $review_summary,
            'score' => $review_score,
            'max_score' => $review_max_score,
            'percentage' => ($review_max_score > 0)
                ? round(($review_score / $review_max_score) * 100, 2)
                : 0,
            'has_score_drift' => $has_score_drift ? 1 : 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_result_payload(array $attempt): array
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $answer_table = $wpdb->prefix . 'cbt_answers';

        $exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT e.id, e.title, e.duration_minutes, e.kkm_percentage, e.show_student_result, e.subject_id, s.name AS subject_name, s.code AS subject_code
                 FROM {$exam_table} e
                 LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                 WHERE e.id = %d",
                (int) ($attempt['exam_id'] ?? 0)
            ),
            ARRAY_A
        );

        $score_snapshot = self::build_attempt_score_snapshot($attempt);
        $review_items = (array) ($score_snapshot['review_items'] ?? []);
        $review_summary = (array) ($score_snapshot['review_summary'] ?? []);
        $review_score = (float) ($score_snapshot['score'] ?? 0.0);
        $review_max_score = (float) ($score_snapshot['max_score'] ?? 0.0);
        $has_score_drift = !empty($score_snapshot['has_score_drift']);

        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, q.question_text, q.question_type, q.points
                 FROM {$answer_table} a
                 INNER JOIN {$question_table} q ON q.id = a.question_id
                 WHERE a.attempt_id = %d
                 ORDER BY a.question_id ASC",
                (int) ($attempt['id'] ?? 0)
            ),
            ARRAY_A
        );

        $attempt['score'] = $review_score;
        $attempt['max_score'] = $review_max_score;
        if ((string) ($attempt['status'] ?? '') === 'completed' && $has_score_drift) {
            $wpdb->update(
                $attempt_table,
                [
                    'score' => $review_score,
                    'max_score' => $review_max_score,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => (int) ($attempt['id'] ?? 0)],
                ['%f', '%f', '%s'],
                ['%d']
            );
        }

        $percentage = ($review_max_score > 0)
            ? round(($review_score / $review_max_score) * 100, 2)
            : 0;
        $kkm_percentage = self::normalize_exam_kkm_percentage((float) ($exam['kkm_percentage'] ?? 75.0));
        if (is_array($exam)) {
            $exam['kkm_percentage'] = $kkm_percentage;
            $exam['show_student_result'] = self::normalize_show_student_result($exam['show_student_result'] ?? 1);
        }
        $pass_meta = self::build_result_pass_meta($review_score, $review_max_score, $kkm_percentage);
        $show_student_result = self::normalize_show_student_result($exam['show_student_result'] ?? 1);
        $submission_summary = self::build_result_submission_summary($review_summary);

        return [
            'attempt' => $attempt,
            'exam' => $exam,
            'show_student_result' => $show_student_result,
            'result_view_mode' => 'full',
            'submission_summary' => $submission_summary,
            'percentage' => $percentage,
            'kkm_percentage' => $pass_meta['kkm_percentage'],
            'passing_score' => $pass_meta['passing_score'],
            'is_passed' => $pass_meta['is_passed'],
            'pass_label' => $pass_meta['pass_label'],
            'result_tone' => $pass_meta['result_tone'],
            'answers' => $answers ?: [],
            'review_items' => $review_items,
            'review_summary' => $review_summary,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function build_restricted_student_result_payload(array $payload): array
    {
        $attempt = isset($payload['attempt']) && is_array($payload['attempt']) ? (array) $payload['attempt'] : [];
        $exam = isset($payload['exam']) && is_array($payload['exam']) ? (array) $payload['exam'] : [];
        $submission_summary = self::build_result_submission_summary(
            isset($payload['submission_summary']) && is_array($payload['submission_summary'])
                ? (array) $payload['submission_summary']
                : (isset($payload['review_summary']) && is_array($payload['review_summary']) ? (array) $payload['review_summary'] : [])
        );

        return [
            'attempt' => [
                'id' => (int) ($attempt['id'] ?? 0),
                'exam_id' => (int) ($attempt['exam_id'] ?? 0),
                'student_id' => (int) ($attempt['student_id'] ?? 0),
                'status' => (string) ($attempt['status'] ?? ''),
                'started_at' => (string) ($attempt['started_at'] ?? ''),
                'finished_at' => (string) ($attempt['finished_at'] ?? ''),
            ],
            'exam' => [
                'id' => (int) ($exam['id'] ?? 0),
                'title' => (string) ($exam['title'] ?? ''),
                'duration_minutes' => (int) ($exam['duration_minutes'] ?? 0),
                'show_student_result' => 0,
            ],
            'show_student_result' => 0,
            'result_view_mode' => 'restricted',
            'submission_summary' => $submission_summary,
        ];
    }

    /**
     * @param array<string,mixed> $summary
     * @return array{total_questions:int,answered_questions:int,pending_manual_questions:int}
     */
    private static function build_result_submission_summary(array $summary): array
    {
        $total_questions = max(0, (int) ($summary['total_questions'] ?? 0));
        $explicit_answered_questions = array_key_exists('answered_questions', $summary)
            ? max(0, (int) ($summary['answered_questions'] ?? 0))
            : null;
        $correct_questions = max(0, (int) ($summary['correct_questions'] ?? 0));
        $wrong_questions = max(0, (int) ($summary['wrong_questions'] ?? 0));
        $graded_questions = max(0, (int) ($summary['graded_questions'] ?? 0));
        $manual_questions = max(0, (int) ($summary['manual_questions'] ?? $summary['pending_manual_questions'] ?? 0));
        $unanswered_questions = max(0, (int) ($summary['unanswered_questions'] ?? 0));

        if ($total_questions <= 0) {
            $total_questions = max(0, $correct_questions + $wrong_questions + $graded_questions + $manual_questions + $unanswered_questions);
        }

        $answered_questions = $explicit_answered_questions !== null
            ? max(0, min($total_questions, $explicit_answered_questions))
            : max(0, min($total_questions, $correct_questions + $wrong_questions + $graded_questions + $manual_questions));

        return [
            'total_questions' => $total_questions,
            'answered_questions' => $answered_questions,
            'pending_manual_questions' => $manual_questions,
        ];
    }

    private static function is_essay_answer_reviewed(?array $answer_row): bool
    {
        if (!is_array($answer_row)) {
            return false;
        }

        return array_key_exists('is_correct', $answer_row)
            && $answer_row['is_correct'] !== null
            && $answer_row['is_correct'] !== '';
    }

    private static function get_exam_show_student_result(int $exam_id): int
    {
        global $wpdb;
        static $show_result_cache = [];

        if ($exam_id <= 0) {
            return 1;
        }

        if (array_key_exists($exam_id, $show_result_cache)) {
            return (int) $show_result_cache[$exam_id];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT show_student_result FROM {$exam_table} WHERE id = %d LIMIT 1",
                $exam_id
            )
        );

        $show_result_cache[$exam_id] = self::normalize_show_student_result($value);

        return (int) $show_result_cache[$exam_id];
    }

    private static function get_exam_kkm_percentage(int $exam_id): float
    {
        global $wpdb;
        static $kkm_cache = [];

        if ($exam_id <= 0) {
            return 75.0;
        }

        if (array_key_exists($exam_id, $kkm_cache)) {
            return (float) $kkm_cache[$exam_id];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT kkm_percentage FROM {$exam_table} WHERE id = %d LIMIT 1",
                $exam_id
            )
        );

        $kkm_cache[$exam_id] = self::normalize_exam_kkm_percentage((float) $value);

        return (float) $kkm_cache[$exam_id];
    }

    private static function normalize_exam_kkm_percentage(float $value): float
    {
        if (!is_finite($value)) {
            return 75.0;
        }

        return round(min(100.0, max(0.0, $value)), 2);
    }

    /**
     * @param mixed $value
     */
    private static function normalize_show_student_result($value): int
    {
        return ((int) $value === 0) ? 0 : 1;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_enable_calculator($value): int
    {
        return ((int) $value === 0) ? 0 : 1;
    }

    private static function calculate_result_passing_score(float $max_score, float $kkm_percentage): float
    {
        if (!is_finite($max_score) || $max_score <= 0) {
            return 0.0;
        }

        return round(max(0.0, $max_score) * (self::normalize_exam_kkm_percentage($kkm_percentage) / 100), 2);
    }

    /**
     * @return array{kkm_percentage:float,passing_score:float,is_passed:int,pass_label:string,result_tone:string}
     */
    private static function build_result_pass_meta(float $score, float $max_score, float $kkm_percentage): array
    {
        $normalized_kkm = self::normalize_exam_kkm_percentage($kkm_percentage);
        $safe_score = is_finite($score) ? round($score, 2) : 0.0;
        $safe_max_score = is_finite($max_score) ? round(max(0.0, $max_score), 2) : 0.0;
        $passing_score = self::calculate_result_passing_score($safe_max_score, $normalized_kkm);
        $is_passed = $safe_max_score > 0
            ? (($safe_score + 0.0001) >= $passing_score)
            : ($normalized_kkm <= 0.0);

        return [
            'kkm_percentage' => $normalized_kkm,
            'passing_score' => $passing_score,
            'is_passed' => $is_passed ? 1 : 0,
            'pass_label' => $is_passed ? 'LULUS' : 'TIDAK LULUS',
            'result_tone' => $is_passed ? 'pass' : 'fail',
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private static function build_attempt_review_items(array $attempt): array
    {
        global $wpdb;
        static $exam_review_cache = [];
        static $question_review_cache = [];
        static $option_review_cache = [];

        $attempt_id = (int) ($attempt['id'] ?? 0);
        $exam_id = (int) ($attempt['exam_id'] ?? 0);
        if ($attempt_id <= 0 || $exam_id <= 0) {
            return [];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $answer_table = $wpdb->prefix . 'cbt_answers';
        if (!array_key_exists($exam_id, $exam_review_cache)) {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, randomize_questions
                     FROM {$exam_table}
                     WHERE id = %d
                     LIMIT 1",
                    $exam_id
                ),
                ARRAY_A
            );
            if (!is_array($exam)) {
                $exam = [
                    'id' => $exam_id,
                    'randomize_questions' => 0,
                ];
            }
            $exam_review_cache[$exam_id] = $exam;
        }
        $exam = (array) $exam_review_cache[$exam_id];

        if (!array_key_exists($exam_id, $question_review_cache)) {
            $question_review_cache[$exam_id] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, question_text, question_type, points, correct_text, explanation, COALESCE(is_active, 1) AS is_active
                     FROM {$question_table}
                     WHERE exam_id = %d
                     ORDER BY id ASC",
                    $exam_id
                ),
                ARRAY_A
            );
        }
        $questions = is_array($question_review_cache[$exam_id]) ? (array) $question_review_cache[$exam_id] : [];

        $attempt_question_order_ids = self::resolve_attempt_snapshot_question_order_ids($attempt);

        $attempt_question_order_json = wp_json_encode($attempt_question_order_ids);
        $attempt_question_order = is_string($attempt_question_order_json)
            ? $attempt_question_order_json
            : (string) ($attempt['question_order'] ?? '');
        $questions = self::append_missing_attempt_review_questions($questions, $exam_id, $attempt_question_order);
        $review_question_contract = self::resolve_attempt_question_order_contract($questions, $attempt, $exam);
        $canonical_question_order_ids = isset($review_question_contract['canonical_question_order_ids']) && is_array($review_question_contract['canonical_question_order_ids'])
            ? array_values(array_filter(array_map('intval', $review_question_contract['canonical_question_order_ids']), static function (int $question_id): bool {
                return $question_id > 0;
            }))
            : [];
        $display_number_map = isset($review_question_contract['display_number_map']) && is_array($review_question_contract['display_number_map'])
            ? $review_question_contract['display_number_map']
            : [];
        $canonical_question_order_json = wp_json_encode(!empty($canonical_question_order_ids) ? $canonical_question_order_ids : $attempt_question_order_ids);
        $questions = self::order_questions_by_attempt_sequence(
            $questions,
            is_string($canonical_question_order_json) ? $canonical_question_order_json : $attempt_question_order
        );

        $attempt_option_order_map = self::normalize_attempt_option_order_map($attempt['option_order'] ?? '');
        if (CBT_Runtime::is_ready() && (string) ($attempt['status'] ?? '') === 'in_progress') {
            $runtime_attempt_option_order_map = CBT_Runtime::get_attempt_option_order($attempt_id, $runtime_attempt_option_order_found);
            if (!$runtime_attempt_option_order_found) {
                $runtime_attempt_option_order_map = [];
            }
            $attempt_option_order_map = self::merge_attempt_option_order_maps(
                $attempt_option_order_map,
                $runtime_attempt_option_order_map
            );
        }

        if (!is_array($questions) || empty($questions)) {
            return [];
        }
        $question_ids = array_values(array_filter(array_map('intval', array_column($questions, 'id')), static function ($id): bool {
            return $id > 0;
        }));
        $submission_contexts = self::get_cached_question_submission_contexts($question_ids);

        $options_by_question = [];
        if (!empty($question_ids)) {
            if (!array_key_exists($exam_id, $option_review_cache) || !is_array($option_review_cache[$exam_id])) {
                $option_review_cache[$exam_id] = [];
            }

            $missing_option_question_ids = array_values(array_filter(array_diff(
                $question_ids,
                array_map('intval', array_keys((array) $option_review_cache[$exam_id]))
            ), static function (int $question_id): bool {
                return $question_id > 0;
            }));
            if (!empty($missing_option_question_ids)) {
                $ids_sql = implode(',', $missing_option_question_ids);
                $option_rows = $wpdb->get_results(
                    "SELECT id, question_id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id IN ({$ids_sql})
                     ORDER BY question_id ASC, id ASC",
                    ARRAY_A
                );

                foreach ((array) $option_rows as $option_row) {
                    $question_id = (int) ($option_row['question_id'] ?? 0);
                    if ($question_id <= 0) {
                        continue;
                    }

                    if (!isset($option_review_cache[$exam_id][$question_id])) {
                        $option_review_cache[$exam_id][$question_id] = [];
                    }

                    $option_review_cache[$exam_id][$question_id][] = [
                        'id' => (int) ($option_row['id'] ?? 0),
                        'question_id' => $question_id,
                        'option_key' => (string) ($option_row['option_key'] ?? ''),
                        'option_text' => (string) ($option_row['option_text'] ?? ''),
                        'is_correct' => (int) ($option_row['is_correct'] ?? 0),
                    ];
                }
            }

            foreach ($question_ids as $question_id) {
                if ($question_id <= 0) {
                    continue;
                }

                $options_by_question[$question_id] = isset($option_review_cache[$exam_id][$question_id]) && is_array($option_review_cache[$exam_id][$question_id])
                    ? array_values((array) $option_review_cache[$exam_id][$question_id])
                    : [];
            }
        }

        foreach ($options_by_question as $question_id => $option_rows) {
            $question_id = (int) $question_id;
            if ($question_id <= 0 || !isset($attempt_option_order_map[$question_id])) {
                continue;
            }

            $ordered_option_rows = self::order_question_options_by_attempt_sequence(
                is_array($option_rows) ? $option_rows : [],
                (array) $attempt_option_order_map[$question_id]
            );
            foreach ($ordered_option_rows as $option_index => $option_row) {
                $option = (array) $option_row;
                $option['option_key'] = self::build_display_option_key($option_index);
                $ordered_option_rows[$option_index] = $option;
            }
            $options_by_question[$question_id] = $ordered_option_rows;
        }

        $answer_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_id, selected_option_ids, answer_text, is_correct, score_awarded, answered_at, updated_at
                 FROM {$answer_table}
                 WHERE attempt_id = %d",
                $attempt_id
            ),
            ARRAY_A
        );

        $answers_by_question = [];
        foreach ((array) $answer_rows as $answer_row) {
            $question_id = (int) ($answer_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            $answers_by_question[$question_id] = (array) $answer_row;
        }

        $review_items = [];
        foreach ($questions as $index => $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_type = (string) ($question['question_type'] ?? '');
            $options = (array) ($options_by_question[$question_id] ?? []);
            $submission_context = is_array($submission_contexts[$question_id] ?? null) ? $submission_contexts[$question_id] : null;
            $answer_row = $answers_by_question[$question_id] ?? null;
            $is_answered = is_array($answer_row);

            $selected_option_ids = $is_answered
                ? (
                    $question_type === 'ordering'
                        ? self::decode_ordered_selected_option_ids($answer_row['selected_option_ids'] ?? null)
                        : self::decode_selected_option_ids($answer_row['selected_option_ids'] ?? null)
                )
                : [];
            if ($question_type !== 'ordering') {
                sort($selected_option_ids);
            }

            $correct_option_ids = $question_type === 'ordering'
                ? (
                    is_array($submission_context['ordering_correct_option_ids'] ?? null)
                        ? self::decode_ordered_selected_option_ids($submission_context['ordering_correct_option_ids'])
                        : []
                )
                : (
                    is_array($submission_context['correct_option_ids'] ?? null)
                        ? array_values(array_unique(array_map('intval', $submission_context['correct_option_ids'])))
                        : []
                );
            $has_submission_correct_option_ids = !empty($correct_option_ids);
            $options_with_state = [];
            foreach ($options as $option_row) {
                $option = (array) $option_row;
                $option_id = (int) ($option['id'] ?? 0);
                $is_correct_option = ((int) ($option['is_correct'] ?? 0) === 1);
                if ($is_correct_option && $option_id > 0 && !$has_submission_correct_option_ids) {
                    $correct_option_ids[] = $option_id;
                }

                $options_with_state[] = [
                    'id' => $option_id,
                    'option_key' => (string) ($option['option_key'] ?? ''),
                    'option_text' => (string) ($option['option_text'] ?? ''),
                    'is_correct' => $is_correct_option ? 1 : 0,
                    'is_selected' => in_array($option_id, $selected_option_ids, true) ? 1 : 0,
                ];
            }

            $question_detail = [];
            if (
                $question_type === 'true_false'
                && !empty($options_with_state)
                && isset($submission_context['true_false_correct_value'])
                && $submission_context['true_false_correct_value'] !== null
            ) {
                $expected_true_false = (int) $submission_context['true_false_correct_value'];
                foreach ($options_with_state as $opt_idx => $option_payload) {
                    $option_value = self::normalize_true_false_value((string) ($option_payload['option_text'] ?? ''), true);
                    if ($option_value !== null && $option_value === $expected_true_false) {
                        $option_id = (int) ($option_payload['id'] ?? 0);
                        if ($option_id > 0) {
                            $correct_option_ids[] = $option_id;
                        }
                        $options_with_state[$opt_idx]['is_correct'] = 1;
                    }
                }
            } elseif ($question_type === 'essay') {
                $question_detail = self::get_question_type_detail($question_id, $question_type);
            }
            $correct_option_ids = array_values(array_unique($correct_option_ids));
            if ($question_type !== 'ordering') {
                sort($correct_option_ids);
            }

            $is_correct = null;
            $answer_text = '';
            $score_awarded = 0.0;
            if ($is_answered) {
                if (
                    array_key_exists('is_correct', $answer_row) &&
                    $answer_row['is_correct'] !== null &&
                    $answer_row['is_correct'] !== ''
                ) {
                    $is_correct = ((int) $answer_row['is_correct'] === 1) ? 1 : 0;
                }
                $answer_text = (string) ($answer_row['answer_text'] ?? '');
                $score_awarded = (float) ($answer_row['score_awarded'] ?? 0);
            }

            if (
                $is_answered &&
                in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'ordering'], true)
            ) {
                $answer_input_for_eval = null;
                if ($question_type === 'multiple_answer' || $question_type === 'ordering') {
                    $answer_input_for_eval = $selected_option_ids;
                } elseif ($question_type === 'true_false_matrix') {
                    $answer_input_for_eval = $answer_text;
                } elseif (!empty($selected_option_ids)) {
                    $answer_input_for_eval = (int) $selected_option_ids[0];
                } elseif (trim($answer_text) !== '') {
                    $answer_input_for_eval = $answer_text;
                }

                $deferred_eval = self::evaluate_answer_against_current_question(
                    $question,
                    $options,
                    $answer_input_for_eval,
                    $question_detail,
                    $submission_context
                );
                $evaluated_is_correct = $deferred_eval['is_correct'] ?? null;
                $is_correct = ($evaluated_is_correct === null || $evaluated_is_correct === '')
                    ? null
                    : (((int) $evaluated_is_correct === 1) ? 1 : 0);
                $score_awarded = (float) ($deferred_eval['score_awarded'] ?? 0);
            }

            $submitted_short_answers = [];
            $correct_short_answers = [];
            $short_answer_input_keys = [];
            $true_false_matrix_rows = [];
            $ordering_rows = [];
            $essay_rubric = '';
            $question_max_points = (float) ($question['points'] ?? 0);
            if ($question_type === 'short_answer') {
                $submitted_short_answers = self::extract_short_answer_submission_values($answer_text);
                $correct_short_answers = is_array($submission_context['short_answer_values'] ?? null)
                    ? array_values($submission_context['short_answer_values'])
                    : self::normalize_short_answer_values((string) ($question['correct_text'] ?? ''));
                $short_answer_input_keys = self::resolve_short_answer_input_keys(
                    (string) ($question['question_text'] ?? ''),
                    $correct_short_answers
                );
                $input_count = count($correct_short_answers);
                if ($input_count <= 0) {
                    $input_count = 1;
                }
                $question_max_points *= $input_count;
                if ($is_answered) {
                    $short_answer_eval = self::evaluate_answer_against_current_question(
                        $question,
                        [],
                        $answer_text,
                        [],
                        $submission_context
                    );
                    $evaluated_is_correct = $short_answer_eval['is_correct'] ?? null;
                    $is_correct = ($evaluated_is_correct === null || $evaluated_is_correct === '')
                        ? null
                        : (((int) $evaluated_is_correct === 1) ? 1 : 0);
                    $score_awarded = (float) ($short_answer_eval['score_awarded'] ?? 0);
                }
            } elseif ($question_type === 'true_false_matrix') {
                $matrix_config = self::normalize_true_false_matrix_config((string) ($question['correct_text'] ?? ''));
                $matrix_items = array_map(static function (array $row, int $idx): array {
                    return [
                        'key' => (string) ($idx + 1),
                        'text' => (string) ($row['text'] ?? ''),
                        'answer' => (string) ($row['answer'] ?? 'true'),
                    ];
                }, $matrix_config, array_keys($matrix_config));
                if (isset($attempt_option_order_map[$question_id])) {
                    $matrix_items = self::order_true_false_matrix_items_by_attempt_sequence(
                        $matrix_items,
                        (array) $attempt_option_order_map[$question_id]
                    );
                }
                $submitted_matrix = self::normalize_true_false_matrix_submission($answer_text, count($matrix_items));
                foreach ($matrix_items as $idx => $row) {
                    $key = trim((string) ($row['key'] ?? ($idx + 1)));
                    if ($key === '') {
                        $key = (string) ($idx + 1);
                    }
                    $submitted_value = (string) ($submitted_matrix[$key] ?? '');
                    $correct_value = ((string) ($row['answer'] ?? 'true') === 'false') ? 'false' : 'true';
                    $true_false_matrix_rows[] = [
                        'key' => $key,
                        'text' => (string) ($row['text'] ?? ''),
                        'submitted' => ($submitted_value === 'true') ? 'Benar' : (($submitted_value === 'false') ? 'Salah' : '-'),
                        'correct' => ($correct_value === 'true') ? 'Benar' : 'Salah',
                        'is_match' => ($submitted_value !== '' && $submitted_value === $correct_value) ? 1 : 0,
                    ];
                }
            } elseif ($question_type === 'ordering') {
                $options_by_id = [];
                foreach ($options as $option_row) {
                    $option = (array) $option_row;
                    $option_id = (int) ($option['id'] ?? 0);
                    if ($option_id > 0) {
                        $options_by_id[$option_id] = $option;
                    }
                }

                $row_count = max(count($correct_option_ids), count($selected_option_ids));
                for ($ordering_idx = 0; $ordering_idx < $row_count; $ordering_idx++) {
                    $submitted_option_id = (int) ($selected_option_ids[$ordering_idx] ?? 0);
                    $correct_option_id = (int) ($correct_option_ids[$ordering_idx] ?? 0);
                    $submitted_option = $submitted_option_id > 0 && isset($options_by_id[$submitted_option_id])
                        ? $options_by_id[$submitted_option_id]
                        : [];
                    $correct_option = $correct_option_id > 0 && isset($options_by_id[$correct_option_id])
                        ? $options_by_id[$correct_option_id]
                        : [];
                    $ordering_rows[] = [
                        'position' => $ordering_idx + 1,
                        'submitted_option_id' => $submitted_option_id,
                        'submitted_text' => (string) ($submitted_option['option_text'] ?? ''),
                        'correct_option_id' => $correct_option_id,
                        'correct_text' => (string) ($correct_option['option_text'] ?? ''),
                        'is_match' => ($submitted_option_id > 0 && $submitted_option_id === $correct_option_id) ? 1 : 0,
                    ];
                }
            } elseif ($question_type === 'essay') {
                $essay_rubric = (string) ($question_detail['rubric_text'] ?? ($question['correct_text'] ?? ''));
            }

            $status = 'unanswered';
            if ($is_answered) {
                if ($question_type === 'essay' && self::is_essay_answer_reviewed($answer_row)) {
                    $status = 'graded';
                } elseif ($is_correct === 1) {
                    $status = 'correct';
                } elseif ($is_correct === 0) {
                    $status = 'wrong';
                } else {
                    $status = 'manual';
                }
            }

            $review_items[] = [
                'question_id' => $question_id,
                'question_number' => (int) ($display_number_map[$question_id] ?? 0),
                'question_type' => $question_type,
                'is_active' => (int) ($question['is_active'] ?? 1),
                'question_text' => (string) ($question['question_text'] ?? ''),
                'points' => $question_max_points,
                'explanation' => (string) ($question['explanation'] ?? ''),
                'status' => $status,
                'is_answered' => $is_answered ? 1 : 0,
                'is_correct' => $is_correct,
                'score_awarded' => $score_awarded,
                'selected_option_ids' => $selected_option_ids,
                'correct_option_ids' => $correct_option_ids,
                'answer_text' => $answer_text,
                'submitted_short_answers' => $submitted_short_answers,
                'correct_short_answers' => $correct_short_answers,
                'short_answer_input_keys' => $short_answer_input_keys,
                'true_false_matrix_rows' => $true_false_matrix_rows,
                'ordering_rows' => $ordering_rows,
                'essay_rubric' => $essay_rubric,
                'options' => $options_with_state,
            ];
        }

        return $review_items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function build_attempt_archived_review_items(array $attempt): array
    {
        $review_items = self::build_attempt_review_items($attempt);
        if (empty($review_items)) {
            return [];
        }

        return array_values(array_filter($review_items, static function ($item_row): bool {
            $item = (array) $item_row;
            return ((int) ($item['is_active'] ?? 1) !== 1);
        }));
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function append_missing_attempt_review_questions(array $questions, int $exam_id, string $question_order_raw): array
    {
        global $wpdb;

        $question_order_ids = self::normalize_question_order_ids($question_order_raw);
        if ($exam_id <= 0 || empty($question_order_ids)) {
            return $questions;
        }

        $known_question_ids = [];
        foreach ($questions as $question_row) {
            $question_id = (int) ($question_row['id'] ?? 0);
            if ($question_id > 0) {
                $known_question_ids[$question_id] = true;
            }
        }

        $missing_question_ids = [];
        foreach ($question_order_ids as $question_id) {
            if (!isset($known_question_ids[$question_id])) {
                $missing_question_ids[] = $question_id;
            }
        }

        if (empty($missing_question_ids)) {
            return $questions;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $placeholders = implode(',', array_fill(0, count($missing_question_ids), '%d'));
        $extra_questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, question_text, question_type, points, correct_text, explanation, COALESCE(is_active, 1) AS is_active
                 FROM {$question_table}
                 WHERE exam_id = %d
                   AND id IN ({$placeholders})
                 ORDER BY id ASC",
                $exam_id,
                ...$missing_question_ids
            ),
            ARRAY_A
        );

        if (!is_array($extra_questions) || empty($extra_questions)) {
            return $questions;
        }

        return array_merge($questions, $extra_questions);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,array<string,mixed>>
     */
    private static function order_questions_by_attempt_sequence(array $questions, string $question_order_raw): array
    {
        $question_order_ids = [];
        $decoded = json_decode($question_order_raw, true);
        if (is_array($decoded)) {
            $question_order_ids = array_values(array_filter(array_map('intval', $decoded), static function ($id): bool {
                return $id > 0;
            }));
        }

        if (empty($question_order_ids)) {
            $active_questions = array_values(array_filter($questions, static function ($question_row): bool {
                $question = (array) $question_row;
                return (int) ($question['is_active'] ?? 1) === 1;
            }));

            return !empty($active_questions) ? $active_questions : $questions;
        }

        $by_id = [];
        foreach ($questions as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            $by_id[$question_id] = $question;
        }

        $ordered = [];
        foreach ($question_order_ids as $question_id) {
            if (!isset($by_id[$question_id])) {
                continue;
            }
            $ordered[] = $by_id[$question_id];
            unset($by_id[$question_id]);
        }

        if (!empty($by_id)) {
            foreach ($by_id as $remaining_question) {
                $ordered[] = $remaining_question;
            }
        }

        if (!empty($ordered)) {
            return $ordered;
        }

        $active_questions = array_values(array_filter($questions, static function ($question_row): bool {
            $question = (array) $question_row;
            return (int) ($question['is_active'] ?? 1) === 1;
        }));

        return !empty($active_questions) ? $active_questions : $questions;
    }

    /**
     * @return int[]
     */
    private static function decode_selected_option_ids($raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $raw_text = trim((string) $raw);
            if ($raw_text === '') {
                return [];
            }

            $decoded = json_decode($raw_text, true);
            if (is_array($decoded)) {
                $values = $decoded;
            } elseif (is_numeric($raw_text)) {
                $values = [(int) $raw_text];
            } else {
                return [];
            }
        }

        $ids = array_values(array_filter(array_map('intval', (array) $values), static function ($id): bool {
            return $id > 0;
        }));
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /**
     * @return int[]
     */
    private static function decode_ordered_selected_option_ids($raw): array
    {
        $normalized = self::normalize_ordering_selected_option_ids($raw);
        return $normalized['ids'];
    }

    /**
     * @param mixed $raw
     * @return array{ids: int[], invalid: bool}
     */
    private static function normalize_ordering_selected_option_ids($raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $raw_text = trim((string) $raw);
            if ($raw_text === '') {
                return [
                    'ids' => [],
                    'invalid' => false,
                ];
            }

            $decoded = json_decode($raw_text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $values = $decoded;
            } elseif (preg_match('/^\d+$/', $raw_text)) {
                $values = [(int) $raw_text];
            } else {
                return [
                    'ids' => [],
                    'invalid' => true,
                ];
            }
        }

        $ids = [];
        $seen = [];
        $invalid = false;
        foreach ((array) $values as $value) {
            if (is_int($value)) {
                $id = $value;
            } elseif (is_float($value) && floor($value) === $value) {
                $id = (int) $value;
            } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
                $id = (int) trim($value);
            } else {
                $invalid = true;
                continue;
            }

            if ($id <= 0 || isset($seen[$id])) {
                $invalid = true;
                continue;
            }

            $seen[$id] = true;
            $ids[] = $id;
        }

        return [
            'ids' => $ids,
            'invalid' => $invalid,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $review_items
     * @return array<string,int>
     */
    private static function summarize_review_items(array $review_items): array
    {
        $summary = [
            'total_questions' => count($review_items),
            'answered_questions' => 0,
            'correct_questions' => 0,
            'wrong_questions' => 0,
            'graded_questions' => 0,
            'manual_questions' => 0,
            'unanswered_questions' => 0,
        ];

        foreach ($review_items as $item_row) {
            $item = (array) $item_row;
            $status = (string) ($item['status'] ?? 'unanswered');
            if ($status !== 'unanswered') {
                $summary['answered_questions']++;
            }

            if ($status === 'correct') {
                $summary['correct_questions']++;
            } elseif ($status === 'wrong') {
                $summary['wrong_questions']++;
            } elseif ($status === 'graded') {
                $summary['graded_questions']++;
            } elseif ($status === 'manual') {
                $summary['manual_questions']++;
            } else {
                $summary['unanswered_questions']++;
            }
        }

        return $summary;
    }

    /**
     * Persist auto-gradable item scores after deferred mode so admin views do not stay in "manual".
     *
     * @param array<int,array<string,mixed>> $review_items
     */
    private static function sync_attempt_auto_scores(int $attempt_id, array $review_items): void
    {
        global $wpdb;

        if ($attempt_id <= 0 || empty($review_items)) {
            return;
        }

        $auto_graded_types = ['multiple_choice', 'multiple_answer', 'true_false', 'true_false_matrix', 'short_answer', 'ordering'];
        $answer_table = $wpdb->prefix . 'cbt_answers';
        $now = current_time('mysql');

        foreach ($review_items as $item_row) {
            $item = (array) $item_row;
            $question_id = (int) ($item['question_id'] ?? 0);
            $question_type = (string) ($item['question_type'] ?? '');
            $is_answered = ((int) ($item['is_answered'] ?? 0) === 1);

            if (
                $question_id <= 0 ||
                !$is_answered ||
                !in_array($question_type, $auto_graded_types, true)
            ) {
                continue;
            }

            $is_correct_raw = $item['is_correct'] ?? null;
            $is_correct_value = ($is_correct_raw === null || $is_correct_raw === '')
                ? -1
                : (((int) $is_correct_raw === 1) ? 1 : 0);
            $score_awarded = max(0.0, (float) ($item['score_awarded'] ?? 0));

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$answer_table}
                     SET is_correct = NULLIF(%d, -1),
                         score_awarded = %f,
                         updated_at = %s
                     WHERE attempt_id = %d
                       AND question_id = %d",
                    $is_correct_value,
                    $score_awarded,
                    $now,
                    $attempt_id,
                    $question_id
                )
            );
        }
    }

    private static function calculate_exam_max_score(int $exam_id): float
    {
        global $wpdb;

        if ($exam_id <= 0) {
            return 0.0;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $question_short_answer_table = $wpdb->prefix . 'cbt_question_short_answer';

        $question_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.question_type, q.points, q.correct_text,
                        qsa.correct_text AS short_answer_correct_text
                 FROM {$question_table} q
                 LEFT JOIN {$question_short_answer_table} qsa ON qsa.question_id = q.id
                 WHERE q.exam_id = %d",
                $exam_id
            ),
            ARRAY_A
        );

        $max_score = 0.0;
        foreach ((array) $question_rows as $question_row) {
            $question = (array) $question_row;
            $question_type = (string) ($question['question_type'] ?? '');
            $base_points = max(0.0, (float) ($question['points'] ?? 0));

            if ($question_type !== 'short_answer') {
                $max_score += $base_points;
                continue;
            }

            $short_answer_raw = trim((string) ($question['short_answer_correct_text'] ?? ''));
            if ($short_answer_raw === '') {
                $short_answer_raw = (string) ($question['correct_text'] ?? '');
            }

            $input_count = count(self::normalize_short_answer_values($short_answer_raw));
            if ($input_count <= 0) {
                $input_count = 1;
            }

            $max_score += ($base_points * $input_count);
        }

        return $max_score;
    }

    private static function is_empty_answer_submission($answer_input): bool
    {
        if ($answer_input === null) {
            return true;
        }

        if (is_string($answer_input)) {
            return trim($answer_input) === '';
        }

        if (is_array($answer_input)) {
            if (empty($answer_input)) {
                return true;
            }

            foreach ($answer_input as $value) {
                if (!self::is_empty_answer_submission($value)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private static function normalize_submission_for_storage(string $question_type, $answer_input): array
    {
        $selected_option_ids = [];
        $answer_text = null;
        $preserve_selected_order = false;

        if ($question_type === 'multiple_choice' || $question_type === 'true_false') {
            $selected_option_ids = self::decode_selected_option_ids($answer_input);
            if (count($selected_option_ids) > 1) {
                $selected_option_ids = [(int) $selected_option_ids[0]];
            }
        } elseif ($question_type === 'multiple_answer') {
            $selected_option_ids = self::decode_selected_option_ids($answer_input);
        } elseif ($question_type === 'ordering') {
            $ordering_submission = self::normalize_ordering_selected_option_ids($answer_input);
            $selected_option_ids = $ordering_submission['ids'];
            $preserve_selected_order = true;
        } elseif ($question_type === 'true_false_matrix') {
            $normalized_matrix = self::normalize_true_false_matrix_submission($answer_input);
            if (!empty($normalized_matrix)) {
                $answer_text = wp_json_encode($normalized_matrix);
            }
        } elseif ($question_type === 'short_answer') {
            $submitted_values = self::extract_short_answer_submission_values($answer_input);
            if (count($submitted_values) > 1) {
                $encoded = wp_json_encode($submitted_values);
                if (is_string($encoded) && $encoded !== '') {
                    $answer_text = $encoded;
                }
            } else {
                $single_value = trim((string) ($submitted_values[0] ?? ''));
                if ($single_value !== '') {
                    $answer_text = $single_value;
                }
            }
        } elseif ($question_type === 'essay') {
            if (is_scalar($answer_input)) {
                $essay_text = trim((string) $answer_input);
                if ($essay_text !== '') {
                    $answer_text = $essay_text;
                }
            }
        } else {
            if (is_scalar($answer_input)) {
                $generic_text = trim((string) $answer_input);
                if ($generic_text !== '') {
                    $answer_text = $generic_text;
                }
            } elseif (is_array($answer_input)) {
                $encoded_generic = wp_json_encode($answer_input);
                if (is_string($encoded_generic) && $encoded_generic !== '' && $encoded_generic !== '[]' && $encoded_generic !== '{}') {
                    $answer_text = $encoded_generic;
                }
            }
        }

        if ($preserve_selected_order) {
            $selected_option_ids = self::decode_ordered_selected_option_ids($selected_option_ids);
        } else {
            $selected_option_ids = array_values(array_unique(array_filter(array_map('intval', $selected_option_ids), static function ($id): bool {
                return $id > 0;
            })));
            sort($selected_option_ids);
        }

        return [
            'selected_option_ids' => !empty($selected_option_ids) ? wp_json_encode($selected_option_ids) : null,
            'answer_text' => $answer_text,
        ];
    }

    /**
     * @param array<string,mixed> $question
     * @param array<int,array<string,mixed>> $options
     * @param array<string,mixed> $question_detail
     * @return array<string,mixed>
     */
    private static function build_submission_context_from_evaluation_inputs(array $question, array $options = [], array $question_detail = []): array
    {
        $question_type = (string) ($question['question_type'] ?? '');
        $correct_option_ids = [];
        $ordering_correct_option_ids = [];
        $true_false_option_value_by_id = [];

        foreach ($options as $option_row) {
            $option = (array) $option_row;
            $option_id = (int) ($option['id'] ?? 0);
            if ($option_id <= 0) {
                continue;
            }

            if ((int) ($option['is_correct'] ?? 0) === 1) {
                $correct_option_ids[] = $option_id;
            }

            if ($question_type === 'ordering') {
                $ordering_correct_option_ids[] = $option_id;
            }

            if ($question_type === 'true_false') {
                $normalized_option_value = self::normalize_true_false_value((string) ($option['option_text'] ?? ''), true);
                if ($normalized_option_value !== null) {
                    $true_false_option_value_by_id[(string) $option_id] = $normalized_option_value;
                }
            }
        }

        $correct_option_ids = array_values(array_unique($correct_option_ids));
        sort($correct_option_ids);

        if ($question_type === 'ordering' && isset($question_detail['correct_option_ids']) && is_array($question_detail['correct_option_ids'])) {
            $ordering_correct_option_ids = self::decode_ordered_selected_option_ids($question_detail['correct_option_ids']);
        } elseif ($question_type === 'ordering') {
            $ordering_correct_option_ids = self::decode_ordered_selected_option_ids($ordering_correct_option_ids);
        }

        $true_false_correct_value = null;
        if ($question_type === 'true_false') {
            if (array_key_exists('correct_value', $question_detail) && $question_detail['correct_value'] !== null && $question_detail['correct_value'] !== '') {
                $true_false_correct_value = ((int) $question_detail['correct_value'] === 1) ? 1 : 0;
            } elseif (array_key_exists('true_false_correct_value', $question) && $question['true_false_correct_value'] !== null && $question['true_false_correct_value'] !== '') {
                $true_false_correct_value = ((int) $question['true_false_correct_value'] === 1) ? 1 : 0;
            } else {
                $legacy_true_false = self::normalize_true_false_value((string) ($question['correct_text'] ?? ''), true);
                if ($legacy_true_false !== null) {
                    $true_false_correct_value = $legacy_true_false;
                }
            }
        }

        $short_answer_values = [];
        if ($question_type === 'short_answer') {
            $short_answer_values = self::normalize_short_answer_values((string) ($question_detail['correct_text'] ?? ($question['correct_text'] ?? '')));
        }

        $true_false_matrix_answers = [];
        if ($question_type === 'true_false_matrix') {
            $matrix_items = self::normalize_true_false_matrix_config((string) ($question_detail['correct_text'] ?? ($question['correct_text'] ?? '')));
            foreach ($matrix_items as $idx => $item) {
                $true_false_matrix_answers[(string) ($idx + 1)] = ((string) ($item['answer'] ?? 'true') === 'false') ? 'false' : 'true';
            }
        }

        return [
            'id' => (int) ($question['id'] ?? 0),
            'exam_id' => (int) ($question['exam_id'] ?? 0),
            'question_type' => $question_type,
            'points' => (float) ($question['points'] ?? 0),
            'correct_option_ids' => $correct_option_ids,
            'ordering_correct_option_ids' => $ordering_correct_option_ids,
            'true_false_correct_value' => $true_false_correct_value,
            'true_false_option_value_by_id' => $true_false_option_value_by_id,
            'short_answer_values' => $short_answer_values,
            'true_false_matrix_answers' => $true_false_matrix_answers,
        ];
    }

    /**
     * @param array<string,mixed> $question_context
     * @return array<string,mixed>
     */
    private static function evaluate_answer_from_submission_context(array $question_context, $answer_input): array
    {
        $type = (string) ($question_context['question_type'] ?? '');
        $points = max(0.0, (float) ($question_context['points'] ?? 0));
        $correct_option_ids = isset($question_context['correct_option_ids']) && is_array($question_context['correct_option_ids'])
            ? array_values(array_unique(array_map('intval', $question_context['correct_option_ids'])))
            : [];
        sort($correct_option_ids);

        $true_false_option_value_by_id = isset($question_context['true_false_option_value_by_id']) && is_array($question_context['true_false_option_value_by_id'])
            ? $question_context['true_false_option_value_by_id']
            : [];
        $short_answer_values = isset($question_context['short_answer_values']) && is_array($question_context['short_answer_values'])
            ? array_values($question_context['short_answer_values'])
            : [];
        $ordering_correct_option_ids = isset($question_context['ordering_correct_option_ids']) && is_array($question_context['ordering_correct_option_ids'])
            ? self::decode_ordered_selected_option_ids($question_context['ordering_correct_option_ids'])
            : [];
        $true_false_matrix_answers = isset($question_context['true_false_matrix_answers']) && is_array($question_context['true_false_matrix_answers'])
            ? $question_context['true_false_matrix_answers']
            : [];

        $selected_option_ids = [];
        $answer_text = '';
        $is_correct = null;
        $score = 0.0;

        switch ($type) {
            case 'multiple_choice':
                if (is_numeric($answer_input)) {
                    $selected_option_ids = [(int) $answer_input];
                }

                sort($selected_option_ids);
                $is_correct = ($selected_option_ids === $correct_option_ids) ? 1 : 0;
                $score = $is_correct ? $points : 0.0;
                break;

            case 'true_false':
                $selected_true_false = null;

                if (is_numeric($answer_input)) {
                    $selected_option_ids = [(int) $answer_input];
                    $selected_true_false = $true_false_option_value_by_id[(string) ((int) $answer_input)] ?? null;
                    $selected_true_false = $selected_true_false === null ? null : (int) $selected_true_false;
                } elseif (is_string($answer_input)) {
                    $selected_true_false = self::normalize_true_false_value($answer_input, true);
                    if ($selected_true_false !== null) {
                        foreach ($true_false_option_value_by_id as $option_id => $option_value) {
                            if ((int) $option_value === $selected_true_false) {
                                $selected_option_ids = [(int) $option_id];
                                break;
                            }
                        }
                    }
                }

                sort($selected_option_ids);

                $correct_true_false = array_key_exists('true_false_correct_value', $question_context)
                    && $question_context['true_false_correct_value'] !== null
                    && $question_context['true_false_correct_value'] !== ''
                    ? (((int) $question_context['true_false_correct_value'] === 1) ? 1 : 0)
                    : null;

                if ($correct_true_false === null) {
                    $is_correct = ($selected_option_ids === $correct_option_ids) ? 1 : 0;
                } else {
                    $is_correct = ($selected_true_false !== null && $selected_true_false === $correct_true_false) ? 1 : 0;
                }

                $score = $is_correct ? $points : 0.0;
                break;

            case 'multiple_answer':
                if (is_array($answer_input)) {
                    $selected_option_ids = array_map('intval', $answer_input);
                } elseif (is_string($answer_input)) {
                    $decoded = json_decode($answer_input, true);
                    if (is_array($decoded)) {
                        $selected_option_ids = array_map('intval', $decoded);
                    }
                }

                $selected_option_ids = array_values(array_unique($selected_option_ids));
                sort($selected_option_ids);

                $is_correct = ($selected_option_ids === $correct_option_ids) ? 1 : 0;
                $score = $is_correct ? $points : 0.0;
                break;

            case 'ordering':
                $ordering_submission = self::normalize_ordering_selected_option_ids($answer_input);
                $selected_option_ids = $ordering_submission['ids'];
                $correct_lookup = array_fill_keys($ordering_correct_option_ids, true);
                $has_foreign_option = false;
                foreach ($selected_option_ids as $selected_option_id) {
                    if (!isset($correct_lookup[$selected_option_id])) {
                        $has_foreign_option = true;
                        break;
                    }
                }
                $is_correct = (
                    empty($ordering_submission['invalid']) &&
                    !empty($ordering_correct_option_ids) &&
                    !$has_foreign_option &&
                    count($selected_option_ids) === count($ordering_correct_option_ids) &&
                    $selected_option_ids === $ordering_correct_option_ids
                ) ? 1 : 0;
                $score = $is_correct ? $points : 0.0;
                break;

            case 'true_false_matrix':
                $submitted_map = self::normalize_true_false_matrix_submission($answer_input, count($true_false_matrix_answers));
                $answer_text = !empty($submitted_map) ? (string) wp_json_encode($submitted_map) : '';

                $total_items = count($true_false_matrix_answers);
                $matched_items = 0;
                foreach ($true_false_matrix_answers as $key => $correct_value) {
                    $submitted_value = (string) ($submitted_map[(string) $key] ?? '');
                    if ($submitted_value !== '' && $submitted_value === (string) $correct_value) {
                        $matched_items++;
                    }
                }

                $is_correct = ($total_items > 0 && $matched_items === $total_items) ? 1 : 0;
                $score = ($total_items > 0)
                    ? ($points * ((float) $matched_items / (float) $total_items))
                    : 0.0;
                if ($score < 0) {
                    $score = 0.0;
                }
                if ($score > $points) {
                    $score = $points;
                }
                break;

            case 'short_answer':
                $submitted_values = self::extract_short_answer_submission_values($answer_input);
                $correct_input_count = 0;
                $expected_input_count = count($short_answer_values);
                $max_short_answer_score = $points * max(1, $expected_input_count);

                foreach ($short_answer_values as $idx => $candidate) {
                    $submitted = (string) ($submitted_values[$idx] ?? '');
                    if (
                        self::normalize_short_answer_compare_text($submitted) ===
                        self::normalize_short_answer_compare_text((string) $candidate)
                    ) {
                        $correct_input_count++;
                    }
                }

                $is_correct = (
                    $expected_input_count > 0 &&
                    count($submitted_values) === $expected_input_count &&
                    $correct_input_count === $expected_input_count
                ) ? 1 : 0;
                $answer_text = (count($submitted_values) > 1)
                    ? (string) wp_json_encode($submitted_values)
                    : (string) ($submitted_values[0] ?? '');
                $score = min($max_short_answer_score, $points * $correct_input_count);
                break;

            case 'essay':
                $answer_text = is_scalar($answer_input) ? trim((string) $answer_input) : '';
                $is_correct = null;
                $score = 0.0;
                break;

            default:
                $answer_text = is_scalar($answer_input) ? trim((string) $answer_input) : '';
                $is_correct = 0;
                $score = 0.0;
                break;
        }

        return [
            'selected_option_ids' => !empty($selected_option_ids) ? wp_json_encode($selected_option_ids) : null,
            'answer_text' => $answer_text ?: null,
            'is_correct' => $is_correct,
            'score_awarded' => $score,
        ];
    }

    private static function evaluate_answer(array $question, array $options, $answer_input, array $question_detail = []): array
    {
        $question_context = self::build_submission_context_from_evaluation_inputs($question, $options, $question_detail);
        return self::evaluate_answer_from_submission_context($question_context, $answer_input);
    }

    public static function evaluate_answer_against_current_question(
        array $question,
        array $options,
        $answer_input,
        array $question_detail = [],
        ?array $submission_context = null
    ): array {
        if (is_array($submission_context) && !empty($submission_context)) {
            return self::evaluate_answer_from_submission_context($submission_context, $answer_input);
        }

        $question_id = (int) ($question['id'] ?? 0);
        if ($question_id > 0) {
            $cached_context = self::get_cached_question_submission_context($question_id);
            if (is_array($cached_context) && !empty($cached_context)) {
                return self::evaluate_answer_from_submission_context($cached_context, $answer_input);
            }
        }

        if (empty($question_detail)) {
            $question_type = (string) ($question['question_type'] ?? '');
            if ($question_id > 0 && $question_type !== '') {
                $question_detail = self::get_question_type_detail($question_id, $question_type);
            }
        }

        return self::evaluate_answer($question, $options, $answer_input, $question_detail);
    }

    private static function get_question_type_detail(int $question_id, string $question_type): array
    {
        global $wpdb;

        if ($question_id <= 0) {
            return [];
        }

        $table_map = [
            'multiple_choice' => $wpdb->prefix . 'cbt_question_multiple_choice',
            'multiple_answer' => $wpdb->prefix . 'cbt_question_multiple_answer',
            'true_false' => $wpdb->prefix . 'cbt_question_true_false',
            'short_answer' => $wpdb->prefix . 'cbt_question_short_answer',
            'essay' => $wpdb->prefix . 'cbt_question_essay',
            'ordering' => $wpdb->prefix . 'cbt_question_ordering',
        ];

        if (isset($table_map[$question_type])) {
            $detail = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_map[$question_type]} WHERE question_id = %d", $question_id),
                ARRAY_A
            );
            if (is_array($detail) && !empty($detail)) {
                if ($question_type === 'ordering') {
                    $detail['correct_option_ids'] = self::get_ordering_correct_option_ids($question_id);
                }
                return $detail;
            }
        }

        // Backward compatibility for data created before per-type detail tables.
        if ($question_type === 'short_answer') {
            $legacy_correct_text = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
            );
            return ['question_id' => $question_id, 'correct_text' => $legacy_correct_text];
        }

        if ($question_type === 'true_false') {
            $legacy_value = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
            );

            if (trim($legacy_value) === '') {
                $legacy_value = (string) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT option_text
                         FROM {$wpdb->prefix}cbt_options
                         WHERE question_id = %d AND is_correct = 1
                         ORDER BY id ASC
                         LIMIT 1",
                        $question_id
                    )
                );
            }

            $normalized = self::normalize_true_false_value($legacy_value, true);
            if ($normalized !== null) {
                return ['question_id' => $question_id, 'correct_value' => $normalized];
            }
        }

        return [];
    }

    /**
     * @return int[]
     */
    private static function get_ordering_correct_option_ids(int $question_id): array
    {
        global $wpdb;

        if ($question_id <= 0) {
            return [];
        }

        $ordering_items_table = $wpdb->prefix . 'cbt_question_ordering_items';
        $option_table = $wpdb->prefix . 'cbt_options';
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT oi.option_id
                 FROM {$ordering_items_table} oi
                 INNER JOIN {$option_table} o ON o.id = oi.option_id AND o.question_id = oi.question_id
                 WHERE oi.question_id = %d
                 ORDER BY oi.correct_position ASC, oi.option_id ASC",
                $question_id
            )
        );

        $ids = array_values(array_filter(array_map('intval', (array) $rows), static function (int $option_id): bool {
            return $option_id > 0;
        }));
        if (!empty($ids)) {
            return array_values(array_unique($ids));
        }

        $fallback_rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM {$option_table}
                 WHERE question_id = %d
                 ORDER BY id ASC",
                $question_id
            )
        );

        return array_values(array_unique(array_filter(array_map('intval', (array) $fallback_rows), static function (int $option_id): bool {
            return $option_id > 0;
        })));
    }

    private static function normalize_true_false_value(string $value, bool $strict = false): ?int
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['true', '1', 't', 'yes', 'y', 'ya', 'benar'], true)) {
            return 1;
        }
        if (in_array($normalized, ['false', '0', 'f', 'no', 'n', 'tidak', 'salah'], true)) {
            return 0;
        }
        return $strict ? null : 1;
    }

    /**
     * @return array<int,array{text:string,answer:string}>
     */
    private static function normalize_true_false_matrix_config(string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $candidates = [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            if (isset($decoded['statements']) && is_array($decoded['statements'])) {
                $candidates = $decoded['statements'];
            } else {
                $is_list = !empty($decoded) && array_keys($decoded) === range(0, count($decoded) - 1);
                if ($is_list) {
                    $candidates = $decoded;
                }
            }
        }

        $normalized = [];
        foreach ((array) $candidates as $candidate) {
            if (count($normalized) >= 20) {
                break;
            }

            if (!is_array($candidate)) {
                $text = CBT_Admin_Questions_Helper::sanitize_editor_html(trim((string) $candidate));
                if (!CBT_Admin_Questions_Helper::has_non_empty_html_content($text)) {
                    continue;
                }
                $normalized[] = [
                    'text' => $text,
                    'answer' => 'true',
                ];
                continue;
            }

            $text = CBT_Admin_Questions_Helper::sanitize_editor_html(
                trim((string) ($candidate['text'] ?? $candidate['statement'] ?? $candidate['pernyataan'] ?? ''))
            );
            if (!CBT_Admin_Questions_Helper::has_non_empty_html_content($text)) {
                continue;
            }

            $answer_source = $candidate['answer'] ?? $candidate['correct'] ?? 'true';
            if (is_bool($answer_source)) {
                $answer_raw = $answer_source ? 'true' : 'false';
            } else {
                $answer_raw = (string) $answer_source;
            }
            $answer_normalized = self::normalize_true_false_value($answer_raw, true);
            $normalized[] = [
                'text' => $text,
                'answer' => ($answer_normalized === 0) ? 'false' : 'true',
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string,string>
     */
    private static function normalize_true_false_matrix_submission($answer_input, int $max_items = 20): array
    {
        if (is_scalar($answer_input)) {
            $raw = trim((string) $answer_input);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::normalize_true_false_matrix_submission($decoded, $max_items);
            }
            return [];
        }

        if (!is_array($answer_input) || empty($answer_input)) {
            return [];
        }

        $normalized = [];
        $is_list = array_keys($answer_input) === range(0, count($answer_input) - 1);
        if ($is_list) {
            foreach ($answer_input as $idx => $value) {
                if (count($normalized) >= $max_items) {
                    break;
                }

                $bool_value = null;
                if (is_bool($value)) {
                    $bool_value = $value ? 1 : 0;
                } else {
                    $bool_value = self::normalize_true_false_value((string) $value, true);
                }
                if ($bool_value === null) {
                    continue;
                }
                $normalized[(string) ($idx + 1)] = ($bool_value === 1) ? 'true' : 'false';
            }
        } else {
            foreach ($answer_input as $key => $value) {
                if (count($normalized) >= $max_items) {
                    break;
                }

                $key_text = trim((string) $key);
                if ($key_text === '' || !preg_match('/^\d+$/', $key_text)) {
                    continue;
                }
                $key_number = (int) $key_text;
                if ($key_number <= 0) {
                    continue;
                }

                $bool_value = null;
                if (is_bool($value)) {
                    $bool_value = $value ? 1 : 0;
                } else {
                    $bool_value = self::normalize_true_false_value((string) $value, true);
                }
                if ($bool_value === null) {
                    continue;
                }
                $normalized[(string) $key_number] = ($bool_value === 1) ? 'true' : 'false';
            }
        }

        if (!empty($normalized)) {
            uksort($normalized, static function ($a, $b): int {
                return ((int) $a) <=> ((int) $b);
            });
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    private static function normalize_short_answer_values(string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $values = [];
        if (($raw[0] ?? '') === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_scalar($item)) {
                        continue;
                    }
                    $values[] = (string) $item;
                }
            }
        }

        if (empty($values)) {
            $parts = preg_split('/\|\||\r\n|\r|\n|;/', $raw);
            if (!is_array($parts) || empty($parts)) {
                $parts = [$raw];
            }
            foreach ($parts as $part) {
                if (!is_scalar($part)) {
                    continue;
                }
                $values[] = (string) $part;
            }
        }

        $normalized = [];
        foreach ($values as $value) {
            $value = sanitize_text_field(trim((string) $value));
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
            if (count($normalized) >= 8) {
                break;
            }
        }

        return $normalized;
    }

    private static function normalize_short_answer_compare_text(string $value): string
    {
        $value = trim((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = is_string($value) ? preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $value) : '';
        return $value === null ? '' : $value;
    }

    /**
     * @return string[]
     */
    private static function extract_short_answer_submission_values($answer_input): array
    {
        if (is_scalar($answer_input)) {
            $raw = trim((string) $answer_input);
            if ($raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::extract_short_answer_submission_values($decoded);
            }
            return [sanitize_text_field($raw)];
        }

        if (!is_array($answer_input)) {
            return [];
        }

        $values = [];
        $ordered_keys = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        $keyed_values = [];
        $has_keyed_input = false;
        $highest_key_index = 0;
        foreach ($ordered_keys as $key) {
            $candidates = [
                $key,
                strtoupper($key),
                'input_' . $key,
                'INPUT_' . strtoupper($key),
                'jawaban_' . $key,
                'JAWABAN_' . strtoupper($key),
            ];
            foreach ($candidates as $candidate_key) {
                if (!array_key_exists($candidate_key, $answer_input)) {
                    continue;
                }
                $has_keyed_input = true;
                $key_index = (ord($key) - 96);
                if ($key_index > $highest_key_index) {
                    $highest_key_index = $key_index;
                }
                $raw = trim((string) $answer_input[$candidate_key]);
                if ($raw !== '') {
                    $keyed_values[$key_index] = sanitize_text_field($raw);
                }
                continue 2;
            }
        }

        if ($has_keyed_input) {
            $max_key_index = min(8, max($highest_key_index, count($keyed_values)));
            for ($i = 1; $i <= $max_key_index; $i++) {
                $values[] = isset($keyed_values[$i]) ? (string) $keyed_values[$i] : '';
            }

            if (!empty($values)) {
                return $values;
            }
        }

        if (!empty($values)) {
            return array_slice($values, 0, 8);
        }

        foreach ($answer_input as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $raw = trim((string) $value);
            if ($raw === '') {
                continue;
            }
            $values[] = sanitize_text_field($raw);
            if (count($values) >= 8) {
                break;
            }
        }

        return $values;
    }

    private static function normalize_frontend_question_text(string $question_text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $question_text);

        // Remove blank paragraphs often produced by editor/autop conversion.
        $normalized = (string) preg_replace('/<p\b[^>]*>\s*(?:&nbsp;|&#160;|<br(?:\s+[^>]*)?\s*\/?>)*\s*<\/p>/i', '', $normalized);

        $has_explicit_line_break_markup = preg_match('/<(?:br|p|div|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|blockquote|pre|figure|figcaption|h[1-6]|hr)\b/i', $normalized) === 1;
        if (!$has_explicit_line_break_markup) {
            // For plain text questions, compress repeated blank lines.
            $normalized = (string) preg_replace('/\n[ \t\x{00A0}]*(?:\n[ \t\x{00A0}]*)+/u', "\n", $normalized);
        }

        return $normalized;
    }

    /**
     * @param string[] $correct_values
     * @return string[]
     */
    private static function resolve_short_answer_input_keys(string $question_text, array $correct_values): array
    {
        $plain = wp_strip_all_tags($question_text);
        $keys = [];
        if (preg_match_all('/\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/i', $plain, $matches)) {
            foreach ((array) ($matches[1] ?? []) as $token) {
                $token = strtoupper(trim((string) $token));
                if ($token === '') {
                    continue;
                }
                if (is_numeric($token)) {
                    $idx = (int) $token;
                    if ($idx >= 1 && $idx <= 8) {
                        $token = chr(64 + $idx);
                    }
                }
                if (!preg_match('/^[A-H]$/', $token)) {
                    continue;
                }
                if (!in_array($token, $keys, true)) {
                    $keys[] = $token;
                }
            }
        }

        if (!empty($keys)) {
            return $keys;
        }

        $count = count($correct_values);
        if ($count <= 0) {
            $count = 1;
        }
        $count = min(8, $count);
        $resolved = [];
        for ($i = 1; $i <= $count; $i++) {
            $resolved[] = chr(64 + $i);
        }
        return $resolved;
    }
}
