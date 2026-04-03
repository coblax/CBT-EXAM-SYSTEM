<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Results_Helper
{
    private static function local_datetime_to_timestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, $timezone);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->getTimestamp();
            }
        }

        try {
            $parsed = new DateTimeImmutable($value, $timezone);
            return $parsed->getTimestamp();
        } catch (Throwable $throwable) {
            return null;
        }
    }

    public static function calculate_attempt_remaining_seconds(string $started_at, int $duration_minutes, string $status = 'in_progress'): int
    {
        if ($status !== 'in_progress') {
            return 0;
        }

        $duration_minutes = max(1, $duration_minutes);
        $started_at_ts = self::local_datetime_to_timestamp($started_at);
        if ($started_at_ts === null) {
            return $duration_minutes * MINUTE_IN_SECONDS;
        }

        return max(0, ($started_at_ts + ($duration_minutes * MINUTE_IN_SECONDS)) - time());
    }

    public static function format_attempt_remaining_label(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = (int) floor($seconds / HOUR_IN_SECONDS);
        $minutes = (int) floor(($seconds % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
        $remaining_seconds = $seconds % MINUTE_IN_SECONDS;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remaining_seconds);
    }

    /**
     * @param array<int,array<string,mixed>> $attempts
     * @return array<int,array<int,array<string,mixed>>>
     */
    public static function build_attempt_answer_progress_map(
        array $attempts,
        string $question_table,
        string $answer_table,
        string $option_table
    ): array {
        global $wpdb;

        if (empty($attempts)) {
            return [];
        }

        $attempt_ids = [];
        $exam_ids = [];
        foreach ($attempts as $attempt_row) {
            $attempt = (array) $attempt_row;
            $attempt_id = (int) ($attempt['id'] ?? 0);
            $exam_id = (int) ($attempt['exam_id'] ?? 0);
            if ($attempt_id > 0) {
                $attempt_ids[$attempt_id] = $attempt_id;
            }
            if ($exam_id > 0) {
                $exam_ids[$exam_id] = $exam_id;
            }
        }

        if (empty($attempt_ids) || empty($exam_ids)) {
            return [];
        }

        $exam_ids_sql = implode(',', array_values($exam_ids));
        $question_short_answer_table = $wpdb->prefix . 'cbt_question_short_answer';
        $question_rows = $wpdb->get_results(
            "SELECT q.id, q.exam_id, q.question_type, q.points, q.correct_text,
                    qsa.correct_text AS short_answer_correct_text,
                    COALESCE(q.is_active, 1) AS is_active
             FROM {$question_table} q
             LEFT JOIN {$question_short_answer_table} qsa ON qsa.question_id = q.id
             WHERE q.exam_id IN ({$exam_ids_sql})
             ORDER BY q.exam_id ASC, q.id ASC",
            ARRAY_A
        );

        $questions_by_exam = [];
        $questions_by_id = [];
        $all_question_ids = [];
        foreach ((array) $question_rows as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            $exam_id = (int) ($question['exam_id'] ?? 0);
            if ($question_id <= 0 || $exam_id <= 0) {
                continue;
            }
            if (!isset($questions_by_exam[$exam_id])) {
                $questions_by_exam[$exam_id] = [];
            }
            $questions_by_exam[$exam_id][] = $question;
            $questions_by_id[$question_id] = $question;
            $all_question_ids[$question_id] = $question_id;
        }

        $missing_attempt_question_ids = [];
        foreach ($attempts as $attempt_row) {
            $attempt_question_ids = self::normalize_attempt_question_order_ids((string) (((array) $attempt_row)['question_order'] ?? ''));
            foreach ($attempt_question_ids as $question_id) {
                if (!isset($questions_by_id[$question_id])) {
                    $missing_attempt_question_ids[$question_id] = $question_id;
                }
            }
        }

        if (!empty($missing_attempt_question_ids)) {
            $missing_question_ids_sql = implode(',', array_values($missing_attempt_question_ids));
            $missing_question_rows = $wpdb->get_results(
                "SELECT q.id, q.exam_id, q.question_type, q.points, q.correct_text,
                        qsa.correct_text AS short_answer_correct_text,
                        COALESCE(q.is_active, 1) AS is_active
                 FROM {$question_table} q
                 LEFT JOIN {$question_short_answer_table} qsa ON qsa.question_id = q.id
                 WHERE q.exam_id IN ({$exam_ids_sql})
                   AND q.id IN ({$missing_question_ids_sql})
                 ORDER BY q.exam_id ASC, q.id ASC",
                ARRAY_A
            );

            foreach ((array) $missing_question_rows as $question_row) {
                $question = (array) $question_row;
                $question_id = (int) ($question['id'] ?? 0);
                $exam_id = (int) ($question['exam_id'] ?? 0);
                if ($question_id <= 0 || $exam_id <= 0 || isset($questions_by_id[$question_id])) {
                    continue;
                }
                if (!isset($questions_by_exam[$exam_id])) {
                    $questions_by_exam[$exam_id] = [];
                }
                $questions_by_exam[$exam_id][] = $question;
                $questions_by_id[$question_id] = $question;
                $all_question_ids[$question_id] = $question_id;
            }
        }

        $option_labels_by_question = [];
        $correct_option_ids_by_question = [];
        if (!empty($all_question_ids)) {
            $question_ids_sql = implode(',', array_values($all_question_ids));
            $option_rows = $wpdb->get_results(
                "SELECT id, question_id, option_key, option_text, is_correct
                 FROM {$option_table}
                 WHERE question_id IN ({$question_ids_sql})
                 ORDER BY question_id ASC, id ASC",
                ARRAY_A
            );

            foreach ((array) $option_rows as $option_row) {
                $option = (array) $option_row;
                $question_id = (int) ($option['question_id'] ?? 0);
                $option_id = (int) ($option['id'] ?? 0);
                if ($question_id <= 0 || $option_id <= 0) {
                    continue;
                }
                if (!isset($option_labels_by_question[$question_id])) {
                    $option_labels_by_question[$question_id] = [];
                }

                $option_labels_by_question[$question_id][$option_id] = self::format_attempt_option_label(
                    (string) ($option['option_key'] ?? ''),
                    (string) ($option['option_text'] ?? '')
                );

                if ((int) ($option['is_correct'] ?? 0) === 1) {
                    if (!isset($correct_option_ids_by_question[$question_id])) {
                        $correct_option_ids_by_question[$question_id] = [];
                    }
                    $correct_option_ids_by_question[$question_id][$option_id] = $option_id;
                }
            }
        }

        $attempt_ids_sql = implode(',', array_values($attempt_ids));
        $answer_rows = $wpdb->get_results(
            "SELECT attempt_id, question_id, selected_option_ids, answer_text, is_correct, score_awarded
             FROM {$answer_table}
             WHERE attempt_id IN ({$attempt_ids_sql})",
            ARRAY_A
        );

        $answers_by_attempt = [];
        foreach ((array) $answer_rows as $answer_row) {
            $answer = (array) $answer_row;
            $attempt_id = (int) ($answer['attempt_id'] ?? 0);
            $question_id = (int) ($answer['question_id'] ?? 0);
            if ($attempt_id <= 0 || $question_id <= 0) {
                continue;
            }
            if (!isset($answers_by_attempt[$attempt_id])) {
                $answers_by_attempt[$attempt_id] = [];
            }
            $answers_by_attempt[$attempt_id][$question_id] = $answer;
        }

        $progress_map = [];
        foreach ($attempts as $attempt_row) {
            $attempt = (array) $attempt_row;
            $attempt_id = (int) ($attempt['id'] ?? 0);
            $exam_id = (int) ($attempt['exam_id'] ?? 0);
            if ($attempt_id <= 0 || $exam_id <= 0) {
                continue;
            }

            $attempt_question_order = (string) ($attempt['question_order'] ?? '');
            $exam_questions = (array) ($questions_by_exam[$exam_id] ?? []);
            $all_exam_question_ids = array_values(array_filter(array_map('intval', array_column($exam_questions, 'id')), static function ($id): bool {
                return $id > 0;
            }));
            $all_exam_question_ids = array_values(array_unique($all_exam_question_ids));
            $ordered_question_ids = self::resolve_attempt_question_ids($exam_questions, $attempt_question_order);
            $attempt_answers = (array) ($answers_by_attempt[$attempt_id] ?? []);
            $historical_question_ids = self::build_attempt_historical_question_sequence(
                $all_exam_question_ids,
                $attempt_question_order,
                array_keys($attempt_answers)
            );
            $question_number_map = [];
            foreach ($historical_question_ids as $historical_index => $historical_question_id) {
                $historical_question_id = (int) $historical_question_id;
                if ($historical_question_id <= 0 || isset($question_number_map[$historical_question_id])) {
                    continue;
                }

                $question_number_map[$historical_question_id] = $historical_index + 1;
            }

            $items = [];
            foreach ($ordered_question_ids as $index => $question_id) {
                $question = (array) ($questions_by_id[$question_id] ?? []);
                if (empty($question)) {
                    continue;
                }

                $answer_row = $answers_by_attempt[$attempt_id][$question_id] ?? null;
                $items[] = self::build_attempt_answer_progress_item(
                    $question,
                    is_array($answer_row) ? $answer_row : null,
                    (array) ($option_labels_by_question[$question_id] ?? []),
                    (array) ($correct_option_ids_by_question[$question_id] ?? []),
                    (int) ($question_number_map[$question_id] ?? ($index + 1))
                );
            }

            $archived_items = [];
            foreach ($historical_question_ids as $index => $question_id) {
                $question = (array) ($questions_by_id[$question_id] ?? []);
                if (empty($question)) {
                    $answer_row = $attempt_answers[$question_id] ?? null;
                    $archived_items[] = self::build_missing_attempt_answer_progress_item(
                        $question_id,
                        is_array($answer_row) ? $answer_row : null,
                        (int) ($question_number_map[$question_id] ?? ($index + 1))
                    );
                    continue;
                }

                if ((int) ($question['is_active'] ?? 1) === 1) {
                    continue;
                }

                $answer_row = $attempt_answers[$question_id] ?? null;
                $archived_items[] = self::build_attempt_answer_progress_item(
                    $question,
                    is_array($answer_row) ? $answer_row : null,
                    (array) ($option_labels_by_question[$question_id] ?? []),
                    (array) ($correct_option_ids_by_question[$question_id] ?? []),
                    (int) ($question_number_map[$question_id] ?? ($index + 1)),
                    true
                );
            }

            $progress_map[$attempt_id] = [
                'active_items' => $items,
                'archived_items' => $archived_items,
            ];
        }

        return $progress_map;
    }

    /**
     * @param array<string,mixed> $question
     * @param array<string,mixed>|null $answer_row
     * @param array<int,string> $option_labels
     * @param array<int,int> $correct_option_ids
     * @return array<string,mixed>
     */
    private static function build_attempt_answer_progress_item(
        array $question,
        ?array $answer_row,
        array $option_labels,
        array $correct_option_ids,
        int $question_number,
        bool $is_archived = false
    ): array {
        $question_id = (int) ($question['id'] ?? 0);
        $status = 'unanswered';
        $short_answer_slots = [];
        $selected_option_ids = [];
        $matrix_submission = [];
        $answer_text = is_array($answer_row) ? (string) ($answer_row['answer_text'] ?? '') : '';
        $points = max(0, (float) ($question['points'] ?? 0));
        $score_awarded = is_array($answer_row) ? max(0, (float) ($answer_row['score_awarded'] ?? 0)) : 0.0;
        $question_type = (string) ($question['question_type'] ?? '');
        if (is_array($answer_row)) {
            if (
                $question_type === 'essay' &&
                self::is_reviewed_essay_answer_row($answer_row)
            ) {
                $status = 'graded';
            } elseif (
                array_key_exists('is_correct', $answer_row) &&
                $answer_row['is_correct'] !== null &&
                $answer_row['is_correct'] !== ''
            ) {
                $status = ((int) $answer_row['is_correct'] === 1) ? 'correct' : 'wrong';
            } else {
                $status = 'manual';
            }
        }

        if (is_array($answer_row)) {
            $selected_option_ids = CBT_Admin_Questions_Helper::decode_attempt_selected_option_ids((string) ($answer_row['selected_option_ids'] ?? ''));
        }

        if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false'], true)) {
            sort($selected_option_ids);

            $normalized_correct_option_ids = array_values(array_unique(array_map('intval', $correct_option_ids)));
            sort($normalized_correct_option_ids);

            if (!is_array($answer_row) || empty($selected_option_ids)) {
                $status = 'unanswered';
                $score_awarded = 0.0;
            } elseif (!empty($normalized_correct_option_ids)) {
                $status = ($selected_option_ids === $normalized_correct_option_ids) ? 'correct' : 'wrong';
                $score_awarded = ($status === 'correct') ? $points : 0.0;
            }
        }

        if ($question_type === 'short_answer') {
            $correct_raw = trim((string) ($question['short_answer_correct_text'] ?? ''));
            if ($correct_raw === '') {
                $correct_raw = (string) ($question['correct_text'] ?? '');
            }
            $expected_values = CBT_Admin_Questions_Helper::normalize_short_answer_values($correct_raw);
            $expected_input_count = max(1, count($expected_values));
            $points *= $expected_input_count;
            $short_answer_slots = CBT_Admin_Questions_Helper::build_short_answer_progress_slots($question, $answer_row);
            if (is_array($answer_row) && !empty($short_answer_slots)) {
                $slot_count = count($short_answer_slots);
                $filled_count = 0;
                $correct_count = 0;
                foreach ($short_answer_slots as $slot_row) {
                    $slot = (array) $slot_row;
                    $slot_status = (string) ($slot['status'] ?? 'empty');
                    if ($slot_status !== 'empty') {
                        $filled_count++;
                    }
                    if ($slot_status === 'correct') {
                        $correct_count++;
                    }
                }

                if ($filled_count <= 0) {
                    $status = 'unanswered';
                    $score_awarded = 0.0;
                } elseif ($filled_count === $slot_count && $correct_count === $slot_count) {
                    $status = 'correct';
                    $score_awarded = min($points, max(0, (float) ($question['points'] ?? 0)) * $correct_count);
                } else {
                    $status = 'wrong';
                    $score_awarded = min($points, max(0, (float) ($question['points'] ?? 0)) * $correct_count);
                }
            } else {
                $status = 'unanswered';
                $score_awarded = 0.0;
            }
        }

        if ($question_type === 'true_false_matrix' && is_array($answer_row)) {
            $matrix_items = CBT_Admin_Questions_Helper::normalize_true_false_matrix_config((string) ($question['correct_text'] ?? ''));
            $matrix_submission = self::normalize_attempt_true_false_matrix_submission($answer_text, count($matrix_items));

            if (!empty($matrix_items)) {
                $answered_count = 0;
                $matched_count = 0;

                foreach ($matrix_items as $idx => $matrix_item_row) {
                    $matrix_item = (array) $matrix_item_row;
                    $key = (string) ($idx + 1);
                    $submitted = (string) ($matrix_submission[$key] ?? '');
                    $expected = ((string) ($matrix_item['answer'] ?? 'true') === 'false') ? 'false' : 'true';

                    if ($submitted === '') {
                        continue;
                    }

                    $answered_count++;
                    if ($submitted === $expected) {
                        $matched_count++;
                    }
                }

                if ($answered_count <= 0) {
                    $status = 'unanswered';
                    $score_awarded = 0.0;
                } elseif ($matched_count === count($matrix_items)) {
                    $status = 'correct';
                    $score_awarded = $points;
                } elseif ($matched_count >= 0) {
                    $status = 'wrong';
                    $score_awarded = count($matrix_items) > 0
                        ? min($points, $points * ((float) $matched_count / (float) count($matrix_items)))
                        : 0.0;
                }
            }
        }

        return [
            'question_id' => $question_id,
            'question_number' => $question_number,
            'status' => $status,
            'question_type' => $question_type,
            'is_archived' => $is_archived ? 1 : 0,
            'is_deleted_missing' => 0,
            'points' => $points,
            'points_unavailable' => 0,
            'score_awarded' => $score_awarded,
            'selected_option_ids' => $selected_option_ids,
            'answer_text' => $answer_text,
            'answer_preview' => CBT_Admin_Questions_Helper::build_attempt_answer_preview(
                (string) ($question['question_type'] ?? ''),
                $answer_row,
                $option_labels
            ),
            'short_answer_slots' => $short_answer_slots,
            'true_false_matrix_submission' => $matrix_submission,
            'detail_note' => '',
        ];
    }

    /**
     * @param array<string,mixed>|null $answer_row
     * @return array<string,mixed>
     */
    private static function build_missing_attempt_answer_progress_item(
        int $question_id,
        ?array $answer_row,
        int $question_number
    ): array {
        $answer_row = is_array($answer_row) ? $answer_row : [];
        $selected_option_ids = CBT_Admin_Questions_Helper::decode_attempt_selected_option_ids((string) ($answer_row['selected_option_ids'] ?? ''));
        $answer_text = is_scalar($answer_row['answer_text'] ?? null) ? (string) ($answer_row['answer_text'] ?? '') : '';
        $has_answer_content = !empty($selected_option_ids) || trim($answer_text) !== '';
        $score_awarded = max(0, (float) ($answer_row['score_awarded'] ?? 0));
        if (!$has_answer_content && $score_awarded <= 0 && !array_key_exists('is_correct', $answer_row)) {
            $status = 'unanswered';
        } elseif (
            array_key_exists('is_correct', $answer_row) &&
            $answer_row['is_correct'] !== null &&
            $answer_row['is_correct'] !== ''
        ) {
            $status = ((int) $answer_row['is_correct'] === 1) ? 'correct' : 'wrong';
        } elseif ($has_answer_content || $score_awarded > 0) {
            $status = 'manual';
        } else {
            $status = 'unanswered';
        }

        return [
            'question_id' => $question_id,
            'question_number' => $question_number,
            'status' => $status,
            'question_type' => 'deleted_missing',
            'is_archived' => 1,
            'is_deleted_missing' => 1,
            'points' => null,
            'points_unavailable' => 1,
            'score_awarded' => $score_awarded,
            'selected_option_ids' => $selected_option_ids,
            'answer_text' => $answer_text,
            'answer_preview' => self::build_missing_attempt_answer_preview($answer_row, $selected_option_ids),
            'short_answer_slots' => [],
            'true_false_matrix_submission' => [],
            'detail_note' => 'Soal ini sudah tidak ada lagi di database exam. Riwayat ditampilkan dari data attempt yang masih tersimpan.',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function normalize_attempt_true_false_matrix_submission(string $raw, int $max_items = 20): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        $normalized = [];
        $is_list = array_keys($decoded) === range(0, count($decoded) - 1);
        if ($is_list) {
            foreach ($decoded as $idx => $value) {
                if (count($normalized) >= $max_items) {
                    break;
                }

                $normalized_value = self::normalize_attempt_true_false_value($value);
                if ($normalized_value === null) {
                    continue;
                }

                $normalized[(string) ($idx + 1)] = $normalized_value;
            }

            return $normalized;
        }

        foreach ($decoded as $key => $value) {
            if (count($normalized) >= $max_items) {
                break;
            }

            $index = absint((string) $key);
            if ($index <= 0) {
                continue;
            }

            $normalized_value = self::normalize_attempt_true_false_value($value);
            if ($normalized_value === null) {
                continue;
            }

            $normalized[(string) $index] = $normalized_value;
        }

        ksort($normalized, SORT_NATURAL);

        return $normalized;
    }

    private static function normalize_attempt_true_false_value($value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (!is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['false', '0', 'f', 'no', 'n', 'tidak', 'salah'], true)) {
            return 'false';
        }

        if (in_array($normalized, ['true', '1', 't', 'yes', 'y', 'ya', 'benar'], true)) {
            return 'true';
        }

        return null;
    }

    /**
     * @return array{label:string,badge_class:string,row_class:string}
     */
    private static function get_attempt_answer_status_meta(string $status, bool $is_archived = false): array
    {
        $label = 'Belum dijawab';
        $tone = 'is-unanswered';
        if ($status === 'correct') {
            $label = 'Benar';
            $tone = 'is-correct';
        } elseif ($status === 'wrong') {
            $label = 'Salah';
            $tone = 'is-wrong';
        } elseif ($status === 'graded') {
            $label = 'Sudah dinilai';
            $tone = 'is-graded';
        } elseif ($status === 'manual') {
            $label = 'Menunggu nilai';
            $tone = 'is-manual';
        }

        $badge_class = 'cbt-attempt-answer-status-badge ' . $tone;
        $row_class = 'cbt-attempt-answer-detail-item ' . $tone;
        if ($is_archived) {
            $badge_class .= ' is-archived';
            $row_class .= ' is-archived';
        }

        return [
            'label' => $label,
            'badge_class' => $badge_class,
            'row_class' => $row_class,
        ];
    }

    private static function format_attempt_question_type_label(string $question_type): string
    {
        switch ($question_type) {
            case 'deleted_missing':
                return 'Soal Dihapus';
            case 'multiple_choice':
                return 'Multiple Choice';
            case 'multiple_answer':
                return 'Multiple Answer';
            case 'true_false':
                return 'True / False';
            case 'true_false_matrix':
                return 'TF Matrix';
            case 'short_answer':
                return 'Short Answer';
            case 'essay':
                return 'Essay';
            default:
                return ucwords(str_replace('_', ' ', $question_type));
        }
    }

    private static function render_attempt_answer_progress_value_html(array $progress_item): string
    {
        $short_answer_slots = isset($progress_item['short_answer_slots']) && is_array($progress_item['short_answer_slots'])
            ? array_values((array) $progress_item['short_answer_slots'])
            : [];
        $progress_preview = trim((string) ($progress_item['answer_preview'] ?? ''));
        if ($progress_preview === '') {
            $progress_preview = '-';
        }

        ob_start();
        if (!empty($short_answer_slots)) {
            ?>
            <div class="cbt-attempt-answer-slot-group cbt-attempt-answer-slot-group--table">
                <?php foreach ($short_answer_slots as $short_answer_slot): ?>
                    <?php
                    $slot = (array) $short_answer_slot;
                    $slot_status = (string) ($slot['status'] ?? 'empty');
                    $slot_class = 'cbt-attempt-answer-slot';
                    if ($slot_status === 'correct') {
                        $slot_class .= ' is-correct';
                    } elseif ($slot_status === 'wrong') {
                        $slot_class .= ' is-wrong';
                    } else {
                        $slot_class .= ' is-empty';
                    }
                    $slot_label = trim((string) ($slot['label'] ?? 'INPUT'));
                    if ($slot_label === '') {
                        $slot_label = 'INPUT';
                    }
                    $slot_value = trim((string) ($slot['value'] ?? ''));
                    if ($slot_value === '') {
                        $slot_value = '-';
                    }
                    $slot_correct_value = trim((string) ($slot['correct_value'] ?? ''));
                    $slot_title = $slot_label . ': ' . $slot_value;
                    if ($slot_correct_value !== '') {
                        $slot_title .= ' (Kunci: ' . $slot_correct_value . ')';
                    }
                    ?>
                    <span class="<?php echo esc_attr($slot_class); ?>" title="<?php echo esc_attr($slot_title); ?>">
                        <span class="cbt-attempt-answer-slot-key"><?php echo esc_html($slot_label); ?></span>
                        <span class="cbt-attempt-answer-slot-val"><?php echo esc_html($slot_value); ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php
        } else {
            ?>
            <div class="cbt-attempt-answer-value-text" title="<?php echo esc_attr($progress_preview); ?>"><?php echo esc_html($progress_preview); ?></div>
            <?php
        }
        $detail_note = trim((string) ($progress_item['detail_note'] ?? ''));
        if ($detail_note !== '') {
            ?>
            <div class="cbt-attempt-answer-value-note"><?php echo esc_html($detail_note); ?></div>
            <?php
        }

        return (string) ob_get_clean();
    }

    public static function render_attempt_answer_progress_table_html(
        array $progress_items,
        string $title,
        string $note = '',
        bool $is_archived = false
    ): string {
        if (empty($progress_items)) {
            return '';
        }

        $status_counts = [
            'correct' => 0,
            'wrong' => 0,
            'graded' => 0,
            'manual' => 0,
            'unanswered' => 0,
        ];
        foreach ($progress_items as $progress_item_row) {
            $progress_item = (array) $progress_item_row;
            $status = (string) ($progress_item['status'] ?? 'unanswered');
            if (!array_key_exists($status, $status_counts)) {
                $status = 'unanswered';
            }
            $status_counts[$status]++;
        }

        ob_start();
        ?>
        <section class="cbt-attempt-answer-detail-section<?php echo $is_archived ? ' is-archived' : ''; ?>">
            <div class="cbt-attempt-answer-detail-head">
                <div class="cbt-attempt-answer-detail-copy">
                    <strong class="cbt-attempt-answer-detail-title"><?php echo esc_html($title); ?></strong>
                    <?php if ($note !== ''): ?>
                        <span class="cbt-attempt-answer-detail-note"><?php echo esc_html($note); ?></span>
                    <?php endif; ?>
                </div>
                <div class="cbt-attempt-answer-detail-head-side">
                    <div class="cbt-attempt-answer-detail-summary">
                        <span class="cbt-attempt-answer-detail-summary-chip is-correct"><?php echo esc_html(sprintf('Benar %d', $status_counts['correct'])); ?></span>
                        <span class="cbt-attempt-answer-detail-summary-chip is-wrong"><?php echo esc_html(sprintf('Salah %d', $status_counts['wrong'])); ?></span>
                        <span class="cbt-attempt-answer-detail-summary-chip is-graded"><?php echo esc_html(sprintf('Dinilai %d', $status_counts['graded'])); ?></span>
                        <span class="cbt-attempt-answer-detail-summary-chip is-manual"><?php echo esc_html(sprintf('Manual %d', $status_counts['manual'])); ?></span>
                        <span class="cbt-attempt-answer-detail-summary-chip is-unanswered"><?php echo esc_html(sprintf('Belum %d', $status_counts['unanswered'])); ?></span>
                    </div>
                    <span class="cbt-attempt-answer-detail-count"><?php echo esc_html(sprintf('%d soal', count($progress_items))); ?></span>
                </div>
            </div>
            <div class="cbt-attempt-answer-detail-table-wrap">
                <table class="cbt-attempt-answer-detail-table">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>Status</th>
                        <th>Jenis</th>
                        <th>Bobot</th>
                        <th>Skor</th>
                        <th>Isian Jawaban</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($progress_items as $progress_item_row): ?>
                        <?php
                        $progress_item = (array) $progress_item_row;
                        $status_meta = self::get_attempt_answer_status_meta((string) ($progress_item['status'] ?? 'unanswered'), $is_archived);
                        $question_number = (int) ($progress_item['question_number'] ?? 0);
                        $question_type_label = self::format_attempt_question_type_label((string) ($progress_item['question_type'] ?? ''));
                        $points_unavailable = !empty($progress_item['points_unavailable']);
                        $points = $points_unavailable ? null : max(0, (float) ($progress_item['points'] ?? 0));
                        $score_awarded = max(0, (float) ($progress_item['score_awarded'] ?? 0));
                        ?>
                        <tr class="<?php echo esc_attr($status_meta['row_class']); ?>">
                            <td>
                                <span class="cbt-attempt-answer-number"><?php echo esc_html((string) $question_number); ?></span>
                            </td>
                            <td>
                                <span class="<?php echo esc_attr($status_meta['badge_class']); ?>">
                                    <?php echo esc_html($status_meta['label']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="cbt-attempt-answer-type-badge"><?php echo esc_html($question_type_label); ?></span>
                            </td>
                            <td>
                                <?php if ($points_unavailable): ?>
                                    <span aria-label="<?php echo esc_attr__('Bobot tidak tersedia', 'cbt-exam-system'); ?>">&mdash;</span>
                                <?php else: ?>
                                    <?php echo esc_html(number_format_i18n((float) $points, 2)); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(number_format_i18n($score_awarded, 2)); ?></td>
                            <td>
                                <?php echo self::render_attempt_answer_progress_value_html($progress_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<int,array<string,mixed>> $progress_items
     * @return array{question_count:int,answer_count:int,total_points:float,answered_points:float,earned_points:float}
     */
    public static function summarize_attempt_answer_progress_items(array $progress_items): array
    {
        $summary = [
            'question_count' => 0,
            'answer_count' => 0,
            'total_points' => 0.0,
            'answered_points' => 0.0,
            'earned_points' => 0.0,
        ];

        foreach ($progress_items as $progress_item_row) {
            $progress_item = (array) $progress_item_row;
            $status = (string) ($progress_item['status'] ?? 'unanswered');
            $points = max(0, (float) ($progress_item['points'] ?? 0));
            $score_awarded = max(0, (float) ($progress_item['score_awarded'] ?? 0));

            $summary['question_count']++;
            $summary['total_points'] += $points;

            if ($status === 'unanswered') {
                continue;
            }

            $summary['answer_count']++;
            $summary['answered_points'] += $points;

            if ($score_awarded > 0) {
                $summary['earned_points'] += min($score_awarded, $points > 0 ? $points : $score_awarded);
            } elseif ($status === 'correct') {
                $summary['earned_points'] += $points;
            }
        }

        return $summary;
    }

    /**
     * @param array<int,int> $candidate_question_ids
     * @return int[]
     */
    private static function build_attempt_question_sequence(array $candidate_question_ids, string $question_order_raw): array
    {
        $candidate_question_ids = array_values(array_unique(array_filter(array_map('intval', $candidate_question_ids), static function ($question_id): bool {
            return $question_id > 0;
        })));
        if (empty($candidate_question_ids)) {
            return [];
        }

        $decoded = self::normalize_attempt_question_order_ids($question_order_raw);
        if (empty($decoded)) {
            return $candidate_question_ids;
        }

        $allowed = array_fill_keys($candidate_question_ids, true);
        $ordered = [];
        $ordered_lookup = [];
        foreach ($decoded as $candidate) {
            $question_id = (int) $candidate;
            if ($question_id <= 0 || !isset($allowed[$question_id]) || isset($ordered_lookup[$question_id])) {
                continue;
            }

            $ordered[] = $question_id;
            $ordered_lookup[$question_id] = true;
        }

        return !empty($ordered) ? $ordered : $candidate_question_ids;
    }

    /**
     * @param array<int,int> $candidate_question_ids
     * @param array<int,int|string> $fallback_question_ids
     * @return int[]
     */
    private static function build_attempt_historical_question_sequence(
        array $candidate_question_ids,
        string $question_order_raw,
        array $fallback_question_ids = []
    ): array {
        $decoded = self::normalize_attempt_question_order_ids($question_order_raw);
        $ordered = [];
        $ordered_lookup = [];

        foreach ($decoded as $question_id) {
            $question_id = (int) $question_id;
            if ($question_id <= 0 || isset($ordered_lookup[$question_id])) {
                continue;
            }
            $ordered[] = $question_id;
            $ordered_lookup[$question_id] = true;
        }

        foreach ($candidate_question_ids as $question_id) {
            $question_id = (int) $question_id;
            if ($question_id <= 0 || isset($ordered_lookup[$question_id])) {
                continue;
            }
            $ordered[] = $question_id;
            $ordered_lookup[$question_id] = true;
        }

        foreach ($fallback_question_ids as $question_id) {
            $question_id = (int) $question_id;
            if ($question_id <= 0 || isset($ordered_lookup[$question_id])) {
                continue;
            }
            $ordered[] = $question_id;
            $ordered_lookup[$question_id] = true;
        }

        return $ordered;
    }

    /**
     * @param array<int,array<string,mixed>> $question_rows
     * @return int[]
     */
    private static function resolve_attempt_question_ids(array $question_rows, string $question_order_raw): array
    {
        $all_question_ids = array_values(array_filter(array_map('intval', array_column($question_rows, 'id')), static function ($id): bool {
            return $id > 0;
        }));
        $all_question_ids = array_values(array_unique($all_question_ids));

        if (empty($all_question_ids)) {
            return [];
        }

        $active_question_ids = [];
        foreach ($question_rows as $question_row) {
            $question = (array) $question_row;
            $question_id = (int) ($question['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            if ((int) ($question['is_active'] ?? 1) === 1) {
                $active_question_ids[] = $question_id;
            }
        }
        $active_question_ids = array_values(array_unique($active_question_ids));
        $preferred_question_ids = !empty($active_question_ids) ? $active_question_ids : $all_question_ids;

        return self::build_attempt_question_sequence($preferred_question_ids, $question_order_raw);
    }

    /**
     * @return int[]
     */
    private static function normalize_attempt_question_order_ids(string $question_order_raw): array
    {
        $decoded = json_decode($question_order_raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static function ($question_id): bool {
            return $question_id > 0;
        })));
    }

    /**
     * @param array<string,mixed> $answer_row
     * @param array<int,int> $selected_option_ids
     */
    private static function build_missing_attempt_answer_preview(array $answer_row, array $selected_option_ids): string
    {
        if (!empty($selected_option_ids)) {
            return sprintf('Pilihan tersimpan (%d opsi)', count($selected_option_ids));
        }

        $answer_text = trim((string) ($answer_row['answer_text'] ?? ''));
        if ($answer_text !== '') {
            return (string) wp_trim_words(wp_strip_all_tags($answer_text), 10, '...');
        }

        if (
            array_key_exists('is_correct', $answer_row) &&
            $answer_row['is_correct'] !== null &&
            $answer_row['is_correct'] !== ''
        ) {
            return 'Jawaban historis tersimpan';
        }

        return 'Belum dijawab';
    }

    private static function format_attempt_option_label(string $option_key, string $option_text): string
    {
        $key = strtoupper(trim(sanitize_text_field($option_key)));
        $text = trim(wp_strip_all_tags($option_text));
        if ($text !== '') {
            $text = (string) wp_trim_words($text, 5, '...');
        }

        if ($key !== '' && $text !== '') {
            return $key . ' - ' . $text;
        }

        if ($key !== '') {
            return $key;
        }

        if ($text !== '') {
            return $text;
        }

        return '-';
    }

    private static function is_reviewed_essay_answer_row(array $answer_row): bool
    {
        return array_key_exists('is_correct', $answer_row)
            && $answer_row['is_correct'] !== null
            && $answer_row['is_correct'] !== '';
    }

}
