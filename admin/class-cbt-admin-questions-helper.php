<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Questions_Helper
{
        private static function allowed_question_page_slugs(): array
        {
            return [
                'cbt-question-bank',
                'cbt-questions-mc',
                'cbt-questions-ma',
                'cbt-questions-tf',
                'cbt-questions-sa',
                'cbt-questions-essay',
            ];
        }

        public static function normalize_question_page_slug($raw_page_slug): string
        {
            $page_slug = sanitize_key((string) $raw_page_slug);
            if (!in_array($page_slug, self::allowed_question_page_slugs(), true)) {
                return 'cbt-question-bank';
            }
            return $page_slug;
        }

        public static function forced_question_type_for_page(string $page_slug): string
        {
            switch ($page_slug) {
                case 'cbt-questions-mc':
                    return 'multiple_choice';
                case 'cbt-questions-ma':
                    return 'multiple_answer';
                case 'cbt-questions-tf':
                    return 'true_false';
                case 'cbt-questions-sa':
                    return 'short_answer';
                case 'cbt-questions-essay':
                    return 'essay';
                default:
                    return '';
            }
        }

        public static function build_attempt_answer_preview(string $question_type, ?array $answer_row, array $option_labels): string
        {
            if (!is_array($answer_row)) {
                return 'Belum dijawab';
            }
    
            $answer_text = trim((string) ($answer_row['answer_text'] ?? ''));
            if (in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false'], true)) {
                $selected_ids = self::decode_attempt_selected_option_ids((string) ($answer_row['selected_option_ids'] ?? ''));
                $labels = [];
                foreach ($selected_ids as $option_id) {
                    if (isset($option_labels[$option_id])) {
                        $labels[] = (string) $option_labels[$option_id];
                    }
                }
                if (!empty($labels)) {
                    return implode(', ', $labels);
                }
                if ($answer_text !== '') {
                    return (string) wp_trim_words(wp_strip_all_tags($answer_text), 8, '...');
                }
                return 'Terjawab';
            }
    
            if ($question_type === 'short_answer') {
                $values = self::normalize_short_answer_values($answer_text);
                if (empty($values) && $answer_text !== '') {
                    $values = [sanitize_text_field($answer_text)];
                }
                if (!empty($values)) {
                    $preview_values = array_map(static function (string $value): string {
                        return (string) wp_trim_words(wp_strip_all_tags($value), 4, '...');
                    }, $values);
                    return implode(' | ', $preview_values);
                }
                return 'Terjawab';
            }
    
            if ($answer_text === '') {
                return 'Terjawab';
            }
    
            return (string) wp_trim_words(wp_strip_all_tags($answer_text), 10, '...');
        }

        public static function build_short_answer_progress_slots(array $question, ?array $answer_row): array
        {
            $answer_text = '';
            if (is_array($answer_row)) {
                $answer_text = (string) ($answer_row['answer_text'] ?? '');
            }
    
            $submitted_values = self::normalize_short_answer_values($answer_text);
            if (empty($submitted_values) && trim($answer_text) !== '') {
                $submitted_values = [sanitize_text_field(trim($answer_text))];
            }
    
            $correct_raw = trim((string) ($question['short_answer_correct_text'] ?? ''));
            if ($correct_raw === '') {
                $correct_raw = (string) ($question['correct_text'] ?? '');
            }
            $correct_values = self::normalize_short_answer_values($correct_raw);
    
            $slot_count = max(count($correct_values), count($submitted_values));
            if ($slot_count <= 0) {
                return [];
            }
    
            $slots = [];
            for ($idx = 0; $idx < $slot_count; $idx++) {
                $submitted = isset($submitted_values[$idx]) ? sanitize_text_field(trim((string) $submitted_values[$idx])) : '';
                $correct = isset($correct_values[$idx]) ? sanitize_text_field(trim((string) $correct_values[$idx])) : '';
                $status = 'empty';
                if ($submitted !== '') {
                    $status = (
                        $correct !== '' &&
                        self::normalize_short_answer_compare_value($submitted) === self::normalize_short_answer_compare_value($correct)
                    ) ? 'correct' : 'wrong';
                }
    
                $slots[] = [
                    'label' => (string) ($idx + 1),
                    'value' => $submitted,
                    'correct_value' => $correct,
                    'status' => $status,
                ];
            }
    
            return $slots;
        }

        public static function normalize_short_answer_compare_value(string $value): string
        {
            $value = strtolower(trim((string) $value));
            $value = preg_replace('/\s+/', ' ', $value);
            return $value === null ? '' : $value;
        }

        public static function normalize_short_answer_values(string $raw): array
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

        public static function normalize_short_answer_payload(string $raw): string
        {
            $values = self::normalize_short_answer_values($raw);
            if (empty($values)) {
                return '';
            }
    
            return (string) wp_json_encode($values);
        }

        public static function normalize_true_false_matrix_config(string $raw): array
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
    
            if (empty($candidates)) {
                $lines = preg_split('/\r\n|\r|\n/', $raw);
                foreach ((array) $lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = explode('|', $line, 2);
                    $text = trim((string) ($parts[0] ?? ''));
                    $answer = trim((string) ($parts[1] ?? 'true'));
                    $candidates[] = [
                        'text' => $text,
                        'answer' => $answer,
                    ];
                }
            }
    
            $normalized = [];
            foreach ((array) $candidates as $candidate) {
                if (count($normalized) >= 10) {
                    break;
                }
    
                if (is_string($candidate) || is_numeric($candidate)) {
                    $text = sanitize_text_field(trim((string) $candidate));
                    if ($text === '') {
                        continue;
                    }
                    $normalized[] = [
                        'text' => $text,
                        'answer' => 'true',
                    ];
                    continue;
                }
    
                if (!is_array($candidate)) {
                    continue;
                }
    
                $text = sanitize_text_field(
                    trim((string) ($candidate['text'] ?? $candidate['statement'] ?? $candidate['pernyataan'] ?? ''))
                );
                if ($text === '') {
                    continue;
                }
    
                $answer_source = $candidate['answer'] ?? $candidate['correct'] ?? 'true';
                if (is_bool($answer_source)) {
                    $answer_raw = $answer_source ? 'true' : 'false';
                } else {
                    $answer_raw = strtolower(trim((string) $answer_source));
                }
                $answer = in_array($answer_raw, ['false', '0', 'f', 'no', 'tidak', 'salah'], true)
                    ? 'false'
                    : 'true';
    
                $normalized[] = [
                    'text' => $text,
                    'answer' => $answer,
                ];
            }
    
            return $normalized;
        }

        public static function normalize_true_false_matrix_payload(string $raw): string
        {
            $items = self::normalize_true_false_matrix_config($raw);
            if (empty($items)) {
                return '';
            }
    
            return (string) wp_json_encode([
                'statements' => $items,
            ]);
        }

        public static function ensure_subject_question_bank_exam(int $subject_id, bool $is_admin_scope, int $current_user_id): int
        {
            if ($subject_id <= 0) {
                return 0;
            }
    
            global $wpdb;
            $exam_table = $wpdb->prefix . 'cbt_exams';
            $subject_table = $wpdb->prefix . 'cbt_subjects';
            $bank_title_like = 'Bank Soal - %';
    
            if ($is_admin_scope) {
                $exam_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$exam_table} WHERE subject_id = %d AND title LIKE %s ORDER BY id DESC LIMIT 1",
                        $subject_id,
                        $bank_title_like
                    )
                );
            } else {
                $exam_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$exam_table} WHERE subject_id = %d AND created_by = %d AND title LIKE %s ORDER BY id DESC LIMIT 1",
                        $subject_id,
                        $current_user_id,
                        $bank_title_like
                    )
                );
            }
    
            if ($exam_id > 0) {
                return $exam_id;
            }
    
            $subject_name = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT name FROM {$subject_table} WHERE id = %d LIMIT 1", $subject_id)
            );
            if ($subject_name === '') {
                return 0;
            }
    
            $creator_id = $current_user_id > 0 ? $current_user_id : get_current_user_id();
            if ($creator_id <= 0) {
                return 0;
            }
    
            $inserted = $wpdb->insert(
                $exam_table,
                [
                    'subject_id' => $subject_id,
                    'title' => 'Bank Soal - ' . $subject_name,
                    'description' => 'Penampung bank soal per mapel.',
                    'duration_minutes' => 60,
                    'total_questions' => 0,
                    'randomize_questions' => 0,
                    'status' => 'draft',
                    'created_by' => $creator_id,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s']
            );
    
            if ($inserted === false) {
                return 0;
            }
    
            return (int) $wpdb->insert_id;
        }

        public static function question_type_detail_tables(): array
        {
            global $wpdb;
    
            return [
                'multiple_choice' => $wpdb->prefix . 'cbt_question_multiple_choice',
                'multiple_answer' => $wpdb->prefix . 'cbt_question_multiple_answer',
                'true_false' => $wpdb->prefix . 'cbt_question_true_false',
                'short_answer' => $wpdb->prefix . 'cbt_question_short_answer',
                'essay' => $wpdb->prefix . 'cbt_question_essay',
            ];
        }

        public static function save_question_type_detail(int $question_id, string $question_type, string $correct_text): void
        {
            global $wpdb;
    
            if ($question_id <= 0) {
                return;
            }
    
            $tables = self::question_type_detail_tables();
            foreach ($tables as $table) {
                $wpdb->delete($table, ['question_id' => $question_id], ['%d']);
            }
    
            $now = current_time('mysql');
    
            if ($question_type === 'multiple_choice' && isset($tables['multiple_choice'])) {
                $wpdb->insert(
                    $tables['multiple_choice'],
                    [
                        'question_id' => $question_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%s', '%s']
                );
                return;
            }
    
            if ($question_type === 'multiple_answer' && isset($tables['multiple_answer'])) {
                $wpdb->insert(
                    $tables['multiple_answer'],
                    [
                        'question_id' => $question_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%s', '%s']
                );
                return;
            }
    
            if ($question_type === 'true_false' && isset($tables['true_false'])) {
                $wpdb->insert(
                    $tables['true_false'],
                    [
                        'question_id' => $question_id,
                        'correct_value' => self::normalize_true_false_value($correct_text),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%d', '%s', '%s']
                );
                return;
            }
    
            if ($question_type === 'short_answer' && isset($tables['short_answer'])) {
                $normalized = self::normalize_short_answer_payload($correct_text);
                $wpdb->insert(
                    $tables['short_answer'],
                    [
                        'question_id' => $question_id,
                        'correct_text' => $normalized !== '' ? $normalized : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%s', '%s', '%s']
                );
                return;
            }
    
            if ($question_type === 'essay' && isset($tables['essay'])) {
                $normalized = trim($correct_text);
                $wpdb->insert(
                    $tables['essay'],
                    [
                        'question_id' => $question_id,
                        'rubric_text' => $normalized !== '' ? $normalized : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%s', '%s', '%s']
                );
            }
        }

        public static function get_question_type_detail(int $question_id, string $question_type): array
        {
            global $wpdb;
    
            if ($question_id <= 0) {
                return [];
            }
    
            $tables = self::question_type_detail_tables();
            if (isset($tables[$question_type])) {
                $detail = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM {$tables[$question_type]} WHERE question_id = %d", $question_id),
                    ARRAY_A
                );
                if (is_array($detail) && !empty($detail)) {
                    if ($question_type === 'short_answer') {
                        $values = self::normalize_short_answer_values((string) ($detail['correct_text'] ?? ''));
                        $detail['correct_text'] = !empty($values) ? (string) wp_json_encode($values) : '';
                        $detail['correct_answers'] = $values;
                    }
                    return $detail;
                }
            }
    
            // Backward compatibility for older data that has not been migrated yet.
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
    
                return [
                    'question_id' => $question_id,
                    'correct_value' => self::normalize_true_false_value($legacy_value),
                ];
            }
    
            if ($question_type === 'short_answer') {
                $legacy_text = (string) $wpdb->get_var(
                    $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
                );
                $values = self::normalize_short_answer_values($legacy_text);
                return [
                    'question_id' => $question_id,
                    'correct_text' => !empty($values) ? (string) wp_json_encode($values) : '',
                    'correct_answers' => $values,
                ];
            }
    
            if ($question_type === 'essay') {
                $legacy_text = (string) $wpdb->get_var(
                    $wpdb->prepare("SELECT correct_text FROM {$wpdb->prefix}cbt_questions WHERE id = %d", $question_id)
                );
                return [
                    'question_id' => $question_id,
                    'rubric_text' => $legacy_text,
                ];
            }
    
            return [];
        }

        public static function normalize_true_false_value(string $value): int
        {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['false', '0', 'f', 'no', 'n', 'tidak', 'salah'], true)) {
                return 0;
            }
            return 1;
        }

        public static function parse_options(string $options_raw): array
        {
            $items = [];
    
            $raw_trimmed = trim($options_raw);
            if ($raw_trimmed !== '' && ($raw_trimmed[0] ?? '') === '[') {
                $decoded = json_decode($raw_trimmed, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }
    
                        $text = isset($entry['option_text']) ? wp_kses_post((string) $entry['option_text']) : '';
                        $is_correct = !empty($entry['is_correct']) ? 1 : 0;
    
                        if (self::has_non_empty_option_content($text)) {
                            $items[] = [
                                'option_text' => $text,
                                'is_correct' => $is_correct,
                            ];
                        }
                    }
                    return $items;
                }
            }
    
            $lines = preg_split('/\r\n|\r|\n/', $options_raw);
    
            foreach ((array) $lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
    
                $parts = array_map('trim', explode('|', $line));
                $text = isset($parts[0]) ? wp_kses_post((string) $parts[0]) : '';
                $is_correct = isset($parts[1]) && $parts[1] === '1' ? 1 : 0;
    
                if (self::has_non_empty_option_content($text)) {
                    $items[] = [
                        'option_text' => $text,
                        'is_correct' => $is_correct,
                    ];
                }
            }
    
            return $items;
        }

        private static function has_non_empty_option_content(string $html): bool
        {
            $trimmed = trim($html);
            if ($trimmed === '') {
                return false;
            }
    
            if (preg_match('/<img\b/i', $trimmed)) {
                return true;
            }
    
            $text = str_replace('&nbsp;', ' ', $trimmed);
            $text = wp_strip_all_tags($text);
            return trim($text) !== '';
        }

        /**
         * @return int[]
         */
        public static function decode_attempt_selected_option_ids(string $raw): array
        {
            $raw = trim($raw);
            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $ids = array_values(array_filter(array_map('intval', $decoded), static function ($id): bool {
                    return $id > 0;
                }));

                return array_values(array_unique($ids));
            }

            if (is_numeric($raw)) {
                $id = (int) $raw;

                return $id > 0 ? [$id] : [];
            }

            return [];
        }

}
