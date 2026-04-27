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

        public static function validate_short_answer_definition(string $question_text, array $values, array $context = []): string
        {
            if (!empty(self::find_duplicate_short_answer_input_keys($question_text))) {
                return 'Placeholder Short Answer tidak boleh duplikat.';
            }

            $input_keys = self::resolve_short_answer_input_keys($question_text);
            if (empty($input_keys)) {
                return 'Short Answer wajib memakai placeholder [INPUT_1] s.d. [INPUT_8] pada teks soal.';
            }

            $normalized_values = [];
            foreach ($values as $value) {
                $value = sanitize_text_field(trim((string) $value));
                if ($value === '') {
                    continue;
                }
                $normalized_values[] = $value;
            }

            if (count($input_keys) !== count($normalized_values)) {
                return 'Jumlah placeholder Short Answer harus sama dengan jumlah jawaban valid.';
            }

            $provided_keys = isset($context['provided_keys']) && is_array($context['provided_keys'])
                ? array_values(array_filter(array_map(static function ($key): string {
                    return self::normalize_short_answer_input_token((string) $key);
                }, $context['provided_keys']), static fn(string $key): bool => $key !== ''))
                : [];

            if (!empty($provided_keys)) {
                sort($provided_keys);
                $expected_keys = $input_keys;
                sort($expected_keys);
                if ($provided_keys !== $expected_keys) {
                    return 'Key placeholder Short Answer harus cocok dengan key jawaban yang diisi.';
                }
            }

            return '';
        }

        /**
         * @return string[]
         */
        public static function resolve_short_answer_input_keys(string $question_text): array
        {
            $keys = [];
            foreach (self::extract_short_answer_input_key_tokens($question_text) as $token) {
                if (!in_array($token, $keys, true)) {
                    $keys[] = $token;
                }
            }

            return $keys;
        }

        /**
         * @return string[]
         */
        public static function find_duplicate_short_answer_input_keys(string $question_text): array
        {
            $counts = [];
            foreach (self::extract_short_answer_input_key_tokens($question_text) as $token) {
                $counts[$token] = (int) ($counts[$token] ?? 0) + 1;
            }

            $duplicates = [];
            foreach ($counts as $token => $count) {
                if ($count > 1) {
                    $duplicates[] = (string) $token;
                }
            }

            return $duplicates;
        }

        public static function validate_true_false_matrix_items(array $items, array $context = []): string
        {
            $provided_indexes = isset($context['provided_indexes']) && is_array($context['provided_indexes'])
                ? array_values(array_unique(array_map('intval', $context['provided_indexes'])))
                : [];

            if (!empty($provided_indexes)) {
                sort($provided_indexes);
                if ($provided_indexes !== range(1, count($provided_indexes))) {
                    return 'Pernyataan True/False Matrix harus diisi berurutan tanpa nomor yang loncat.';
                }
            }

            $source_rows = isset($context['source_rows']) && is_array($context['source_rows'])
                ? $context['source_rows']
                : [];
            foreach ($source_rows as $source_row) {
                if (!is_array($source_row)) {
                    continue;
                }

                $row_index = isset($source_row['index']) ? (int) $source_row['index'] : 0;
                if ($row_index <= 0) {
                    continue;
                }

                $text = sanitize_text_field(trim((string) ($source_row['text'] ?? $source_row['statement'] ?? '')));
                if ($text === '') {
                    return 'Pernyataan True/False Matrix tidak boleh kosong.';
                }
            }

            $normalized_items = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $text = sanitize_text_field(trim((string) ($item['text'] ?? $item['statement'] ?? $item['pernyataan'] ?? '')));
                if ($text === '') {
                    continue;
                }

                $answer = strtolower(trim((string) ($item['answer'] ?? $item['correct'] ?? 'true')));
                $normalized_items[] = [
                    'text' => $text,
                    'answer' => in_array($answer, ['false', '0', 'f', 'no', 'tidak', 'salah'], true) ? 'false' : 'true',
                ];
            }

            if (count($normalized_items) < 2) {
                return 'True/False Matrix minimal harus punya 2 pernyataan.';
            }

            if (!empty(self::find_duplicate_true_false_matrix_statement_indexes($normalized_items))) {
                return 'True/False Matrix tidak boleh punya pernyataan duplikat.';
            }

            return '';
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
                    $text = self::sanitize_editor_html(trim((string) $candidate));
                    if (!self::has_non_empty_html_content($text)) {
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
    
                $text = self::sanitize_editor_html(
                    trim((string) ($candidate['text'] ?? $candidate['statement'] ?? $candidate['pernyataan'] ?? ''))
                );
                if (!self::has_non_empty_html_content($text)) {
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

        /**
         * @param array<int, array<string, mixed>> $items
         * @return int[]
         */
        public static function find_duplicate_true_false_matrix_statement_indexes(array $items): array
        {
            $signatures = [];
            $duplicate_indexes = [];

            foreach ($items as $idx => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $text = (string) ($item['text'] ?? $item['statement'] ?? $item['pernyataan'] ?? '');
                $signature = self::normalize_true_false_matrix_statement_compare_value($text);
                if ($signature === '') {
                    continue;
                }

                if (isset($signatures[$signature])) {
                    $duplicate_indexes[] = (int) $idx + 1;
                    continue;
                }

                $signatures[$signature] = (int) $idx + 1;
            }

            return $duplicate_indexes;
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

        public static function validate_choice_options(string $question_type, array $options, array $context = []): string
        {
            $question_type = in_array($question_type, ['multiple_choice', 'multiple_answer'], true)
                ? $question_type
                : 'multiple_choice';
            $label = $question_type === 'multiple_answer' ? 'Multiple Answer' : 'Multiple Choice';

            $option_count = count($options);
            $minimum_count = 3;
            $maximum_count = $question_type === 'multiple_answer' ? 12 : 5;
            if ($option_count < $minimum_count) {
                return $label . ' minimal harus punya 3 pilihan.';
            }
            if ($option_count > $maximum_count) {
                return $label . ' maksimal ' . $maximum_count . ' pilihan.';
            }

            if (!empty($context['has_empty_correct_reference'])) {
                return $question_type === 'multiple_answer'
                    ? 'Multiple Answer tidak boleh menandai jawaban benar pada pilihan yang kosong.'
                    : 'Jawaban benar Multiple Choice tidak boleh menunjuk pilihan kosong.';
            }

            $correct_count = 0;
            foreach ($options as $option) {
                if (is_array($option) && (int) ($option['is_correct'] ?? 0) === 1) {
                    $correct_count++;
                }
            }

            if ($question_type === 'multiple_choice' && $correct_count !== 1) {
                return 'Multiple Choice harus memiliki tepat 1 jawaban benar.';
            }
            if ($question_type === 'multiple_answer' && $correct_count < 1) {
                return 'Multiple Answer harus memiliki minimal 1 jawaban benar.';
            }

            if (!empty(self::find_duplicate_option_indexes($options))) {
                return $label . ' tidak boleh punya pilihan duplikat.';
            }

            return '';
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

        public static function get_question_type_label(string $question_type): string
        {
            switch ($question_type) {
                case 'multiple_choice':
                    return 'Multiple Choice';
                case 'multiple_answer':
                    return 'Multiple Answer';
                case 'true_false':
                    return 'True/False';
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

        public static function get_admin_student_preview_css(): string
        {
            return <<<'CSS'
.cbt-admin-student-preview-list {
    display: grid;
    gap: 16px;
}
.cbt-admin-student-preview-card {
    border: 1px solid #d9e4f2;
    border-radius: 22px;
    background:
        radial-gradient(circle at top right, rgba(34, 197, 94, 0.08), transparent 26%),
        linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    padding: 18px;
    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
}
.cbt-admin-student-preview-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}
.cbt-admin-student-preview-head-main {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    min-width: 0;
    flex: 1;
}
.cbt-admin-student-preview-kicker {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid #d9e4f2;
    background: #ffffff;
    color: #2a5d9f;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.cbt-admin-student-preview-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 0;
}
.cbt-admin-student-preview-chip {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid #d8e3ee;
    background: #ffffff;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
}
.cbt-admin-student-preview-chip--type {
    background: #f5f9ff;
    color: #14478a;
    border-color: #c9dbef;
}
.cbt-admin-student-preview-chip--points {
    background: #fff7e6;
    color: #935500;
    border-color: #f1d7a3;
}
.cbt-admin-student-preview-chip--source {
    background: #edf8ff;
    color: #0b6092;
    border-color: #bfe0f4;
}
.cbt-admin-student-preview-chip--accent {
    background: #ecfbf4;
    color: #0f7a56;
    border-color: #9adfc5;
}
.cbt-admin-student-preview-meta {
    display: grid;
    flex-basis: 100%;
    gap: 4px;
    margin-top: 4px;
    color: #5b6b7b;
    font-size: 13px;
    line-height: 1.55;
}
.cbt-admin-student-preview-actions {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}
.cbt-admin-student-preview-actions .button {
    min-height: 32px;
    border-radius: 999px;
    padding: 0 12px;
}
.cbt-admin-student-preview-note {
    margin-top: 14px;
    padding: 11px 13px;
    border: 1px solid #d9e6f7;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.84);
    color: #516377;
    font-size: 12px;
    line-height: 1.6;
}
.cbt-admin-student-preview-body {
    display: grid;
    gap: 14px;
    margin-top: 14px;
}
.cbt-admin-student-preview-question,
.cbt-admin-student-preview-section {
    border: 1px solid #dbe5ef;
    border-radius: 16px;
    background: #ffffff;
    padding: 16px 18px;
}
.cbt-admin-student-preview-section--explanation {
    background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    border-left: 4px solid #bfd8f4;
}
.cbt-admin-student-preview-section-title {
    display: block;
    margin-bottom: 12px;
    color: #0f172a;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.01em;
}
.cbt-admin-student-preview-richtext {
    color: #1f2937;
    font-size: 14px;
    line-height: 1.72;
}
.cbt-admin-student-preview-richtext > :first-child {
    margin-top: 0;
}
.cbt-admin-student-preview-richtext > :last-child {
    margin-bottom: 0;
}
.cbt-admin-student-preview-richtext :where(ul, ol) {
    margin: 0.55em 0 0.55em 1.2em;
}
.cbt-admin-student-preview-richtext :where(table) {
    margin: 0.55em 0;
    border-collapse: collapse;
    border-spacing: 0;
    background: #ffffff;
    border: 1px solid #d6deea;
    max-width: 100%;
}
.cbt-admin-student-preview-richtext :where(th, td) {
    border: 1px solid #d6deea;
    padding: 8px 10px;
    vertical-align: top;
}
.cbt-admin-student-preview-richtext :where(th) {
    background: #f8fbff;
    color: #0f172a;
    font-weight: 700;
}
.cbt-admin-student-preview-richtext :where(img) {
    max-width: 100%;
    height: auto;
}
.cbt-admin-student-preview-richtext :where(figure) {
    margin: 0.75em 0;
}
.cbt-admin-student-preview-richtext .cbt-rich-spacer {
    display: block;
    height: 0.95em;
}
.cbt-admin-student-preview-richtext > img,
.cbt-admin-student-preview-richtext figure.cbt-pasted-image-block > img,
.cbt-admin-student-preview-richtext p > img:only-child,
.cbt-admin-student-preview-richtext div > img:only-child {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 0.55em 0;
    border-radius: 8px;
}
.cbt-admin-student-preview-options {
    display: grid;
    gap: 10px;
}
.cbt-admin-student-preview-option {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid #dbe5ef;
    border-radius: 14px;
    background: #ffffff;
}
.cbt-admin-student-preview-option.is-correct {
    border-color: #9adfc5;
    background: #ecfbf4;
}
.cbt-admin-student-preview-option-main {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    flex: 1;
    min-width: 0;
}
.cbt-admin-student-preview-option-key {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    border-radius: 999px;
    background: #eef4ff;
    color: #1e3a6f;
    font-size: 12px;
    font-weight: 800;
    flex-shrink: 0;
}
.cbt-admin-student-preview-option-text {
    min-width: 0;
}
.cbt-admin-student-preview-option-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
}
.cbt-admin-student-preview-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0 9px;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    white-space: nowrap;
}
.cbt-admin-student-preview-badge--key {
    background: #eafbf4;
    color: #0f7a56;
    border-color: #9adfc5;
}
.cbt-admin-student-preview-chip-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.cbt-admin-student-preview-answer-chip {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: 0 11px;
    border-radius: 12px;
    border: 1px solid #d0def2;
    background: #eef4ff;
    color: #1e3a6f;
    font-size: 12px;
    font-weight: 700;
}
.cbt-admin-student-preview-matrix {
    display: grid;
    gap: 10px;
}
.cbt-admin-student-preview-matrix-row {
    display: grid;
    gap: 10px;
    padding: 13px 14px;
    border: 1px solid #dbe5ef;
    border-radius: 14px;
    background: #ffffff;
}
.cbt-admin-student-preview-matrix-answer {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    min-height: 26px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid #d0def2;
    background: #f8fbff;
    color: #33526e;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.cbt-admin-student-preview-empty {
    color: #64748b;
    font-size: 13px;
}
@media (max-width: 782px) {
    .cbt-admin-student-preview-card {
        padding: 15px;
        border-radius: 18px;
    }
    .cbt-admin-student-preview-head {
        flex-direction: column;
    }
    .cbt-admin-student-preview-actions {
        width: 100%;
        justify-content: flex-start;
    }
}
CSS;
        }

        /**
         * @param array<string,mixed> $question
         * @param array<int,array<string,mixed>> $options
         * @param array<string,mixed> $question_detail
         * @param array<string,mixed> $context
         */
        public static function render_admin_student_preview_card(
            array $question,
            array $options = [],
            array $question_detail = [],
            array $context = []
        ): string {
            $question_type = (string) ($question['question_type'] ?? '');
            $type_label = trim((string) ($context['type_label'] ?? self::get_question_type_label($question_type)));
            $eyebrow = trim((string) ($context['eyebrow'] ?? 'Soal'));
            $actions_html = trim((string) ($context['actions_html'] ?? ''));
            $note_text = trim((string) ($context['note_text'] ?? ''));
            $answer_mode = sanitize_key((string) ($context['answer_mode'] ?? 'teacher'));
            $show_answer_key = $answer_mode !== 'student' && ($context['show_answer_key'] ?? true) !== false;
            $meta_lines = [];
            foreach ((array) ($context['meta_lines'] ?? []) as $meta_line) {
                if (!is_scalar($meta_line)) {
                    continue;
                }
                $meta_line = trim((string) $meta_line);
                if ($meta_line === '') {
                    continue;
                }
                $meta_lines[] = $meta_line;
            }

            $extra_chips = [];
            foreach ((array) ($context['extra_chips'] ?? []) as $chip) {
                if (!is_array($chip)) {
                    continue;
                }
                $chip_label = trim((string) ($chip['label'] ?? ''));
                if ($chip_label === '') {
                    continue;
                }
                $chip_tone = sanitize_html_class((string) ($chip['tone'] ?? ''));
                $extra_chips[] = [
                    'label' => $chip_label,
                    'tone' => $chip_tone,
                ];
            }

            $points_label = trim((string) ($context['points_label'] ?? ''));
            if ($points_label === '') {
                $points_value = (float) ($question['points'] ?? 0);
                $points_text = rtrim(rtrim(number_format($points_value, 2, '.', ''), '0'), '.');
                if ($points_text === '') {
                    $points_text = '0';
                }
                $points_label = 'Poin ' . $points_text;
            }

            $options = self::normalize_admin_student_preview_options($question, $options, $question_detail);

            ob_start();
            ?>
            <div class="cbt-admin-student-preview">
                <article class="cbt-admin-student-preview-card">
                    <header class="cbt-admin-student-preview-head">
                        <div class="cbt-admin-student-preview-head-main">
                            <?php if ($eyebrow !== ''): ?>
                                <span class="cbt-admin-student-preview-kicker"><?php echo esc_html($eyebrow); ?></span>
                            <?php endif; ?>
                            <div class="cbt-admin-student-preview-chip-row">
                                <span class="cbt-admin-student-preview-chip cbt-admin-student-preview-chip--type"><?php echo esc_html($type_label); ?></span>
                                <span class="cbt-admin-student-preview-chip cbt-admin-student-preview-chip--points"><?php echo esc_html($points_label); ?></span>
                                <?php foreach ($extra_chips as $extra_chip): ?>
                                    <span class="cbt-admin-student-preview-chip<?php echo $extra_chip['tone'] !== '' ? ' cbt-admin-student-preview-chip--' . esc_attr($extra_chip['tone']) : ''; ?>">
                                        <?php echo esc_html($extra_chip['label']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!empty($meta_lines)): ?>
                                <div class="cbt-admin-student-preview-meta">
                                    <?php foreach ($meta_lines as $meta_line): ?>
                                        <div><?php echo esc_html($meta_line); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($actions_html !== ''): ?>
                            <div class="cbt-admin-student-preview-actions">
                                <?php echo $actions_html; ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <?php if ($note_text !== ''): ?>
                        <div class="cbt-admin-student-preview-note"><?php echo esc_html($note_text); ?></div>
                    <?php endif; ?>

                    <div class="cbt-admin-student-preview-body">
                        <div class="cbt-admin-student-preview-question cbt-admin-student-preview-richtext">
                            <?php echo self::render_editor_html((string) ($question['question_text'] ?? '')); ?>
                        </div>

                        <?php echo self::render_admin_student_preview_answer_section($question, $options, $question_detail, $show_answer_key); ?>

                        <?php if ($show_answer_key && self::has_non_empty_html_content((string) ($question['explanation'] ?? ''))): ?>
                            <section class="cbt-admin-student-preview-section cbt-admin-student-preview-section--explanation">
                                <strong class="cbt-admin-student-preview-section-title">Pembahasan</strong>
                                <div class="cbt-admin-student-preview-richtext">
                                    <?php echo self::render_editor_html((string) ($question['explanation'] ?? '')); ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
            <?php

            return trim((string) ob_get_clean());
        }

        /**
         * @param array<string,mixed> $question
         * @param array<int,array<string,mixed>> $options
         * @param array<string,mixed> $question_detail
         * @return array<int,array<string,mixed>>
         */
        private static function normalize_admin_student_preview_options(
            array $question,
            array $options,
            array $question_detail
        ): array {
            $question_type = (string) ($question['question_type'] ?? '');
            if ($question_type !== 'true_false' || empty($options)) {
                return $options;
            }

            foreach ($options as $option) {
                if ((int) ($option['is_correct'] ?? 0) === 1) {
                    return $options;
                }
            }

            if (!isset($question_detail['correct_value'])) {
                return $options;
            }

            $expected = (int) $question_detail['correct_value'];
            foreach ($options as $idx => $option) {
                $option_value = self::normalize_true_false_value((string) ($option['option_text'] ?? ''));
                if ($option_value === $expected) {
                    $options[$idx]['is_correct'] = 1;
                }
            }

            return $options;
        }

        /**
         * @param array<string,mixed> $question
         * @param array<int,array<string,mixed>> $options
         * @param array<string,mixed> $question_detail
         */
        private static function render_admin_student_preview_answer_section(
            array $question,
            array $options,
            array $question_detail,
            bool $show_answer_key = true
        ): string {
            $question_type = (string) ($question['question_type'] ?? '');

            if (!empty($options)) {
                return self::render_admin_student_preview_options($options, $show_answer_key);
            }

            if (!$show_answer_key && $question_type !== 'true_false_matrix') {
                return '';
            }

            if ($question_type === 'short_answer') {
                $short_answers = self::normalize_short_answer_values(
                    (string) ($question_detail['correct_text'] ?? ($question['correct_text'] ?? ''))
                );

                if (empty($short_answers)) {
                    return '';
                }

                ob_start();
                ?>
                <section class="cbt-admin-student-preview-section">
                    <strong class="cbt-admin-student-preview-section-title">Jawaban Valid</strong>
                    <div class="cbt-admin-student-preview-chip-list">
                        <?php foreach ($short_answers as $short_answer): ?>
                            <span class="cbt-admin-student-preview-answer-chip"><?php echo esc_html($short_answer); ?></span>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php
                return trim((string) ob_get_clean());
            }

            if ($question_type === 'true_false') {
                $correct_value = isset($question_detail['correct_value']) && (int) $question_detail['correct_value'] === 0
                    ? 'False'
                    : 'True';

                return self::render_admin_student_preview_chip_section('Kunci Jawaban', [$correct_value]);
            }

            if ($question_type === 'true_false_matrix') {
                $matrix_rows = self::normalize_true_false_matrix_config((string) ($question['correct_text'] ?? ''));
                if (empty($matrix_rows)) {
                    return '';
                }

                ob_start();
                ?>
                <section class="cbt-admin-student-preview-section">
                    <strong class="cbt-admin-student-preview-section-title"><?php echo esc_html($show_answer_key ? 'Pernyataan dan Kunci' : 'Pernyataan'); ?></strong>
                    <div class="cbt-admin-student-preview-matrix">
                        <?php foreach ($matrix_rows as $matrix_row): ?>
                            <div class="cbt-admin-student-preview-matrix-row">
                                <div class="cbt-admin-student-preview-richtext"><?php echo self::render_editor_html((string) ($matrix_row['text'] ?? '')); ?></div>
                                <?php if ($show_answer_key): ?>
                                    <span class="cbt-admin-student-preview-matrix-answer">
                                        <?php echo ((string) ($matrix_row['answer'] ?? 'true') === 'false') ? 'Kunci Salah' : 'Kunci Benar'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php
                return trim((string) ob_get_clean());
            }

            if ($question_type === 'essay') {
                $essay_rubric = (string) ($question_detail['rubric_text'] ?? ($question['correct_text'] ?? ''));
                if (!self::has_non_empty_html_content($essay_rubric)) {
                    return '';
                }

                return self::render_admin_student_preview_richtext_section('Acuan Jawaban', $essay_rubric);
            }

            $fallback_answer = (string) ($question['correct_text'] ?? '');
            if (self::has_non_empty_html_content($fallback_answer)) {
                return self::render_admin_student_preview_richtext_section('Kunci Jawaban', $fallback_answer);
            }

            return '';
        }

        /**
         * @param array<int,array<string,mixed>> $options
         */
        private static function render_admin_student_preview_options(array $options, bool $show_answer_key = true): string
        {
            ob_start();
            ?>
            <section class="cbt-admin-student-preview-section">
                <strong class="cbt-admin-student-preview-section-title">Opsi Jawaban</strong>
                <div class="cbt-admin-student-preview-options">
                    <?php foreach ($options as $index => $option): ?>
                        <?php
                        $is_correct = $show_answer_key && (int) ($option['is_correct'] ?? 0) === 1;
                        $option_key = strtoupper(trim((string) ($option['option_key'] ?? '')));
                        if ($option_key === '') {
                            $option_key = chr(65 + ($index % 26));
                        }
                        ?>
                        <div class="cbt-admin-student-preview-option<?php echo $is_correct ? ' is-correct' : ''; ?>">
                            <div class="cbt-admin-student-preview-option-main">
                                <span class="cbt-admin-student-preview-option-key"><?php echo esc_html($option_key); ?></span>
                                <div class="cbt-admin-student-preview-option-text cbt-admin-student-preview-richtext">
                                    <?php echo self::render_editor_html((string) ($option['option_text'] ?? '')); ?>
                                </div>
                            </div>
                            <div class="cbt-admin-student-preview-option-badges">
                                <?php if ($is_correct): ?>
                                    <span class="cbt-admin-student-preview-badge cbt-admin-student-preview-badge--key">Kunci</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php
            return trim((string) ob_get_clean());
        }

        /**
         * @param string[] $items
         */
        private static function render_admin_student_preview_chip_section(string $title, array $items): string
        {
            $items = array_values(array_filter(array_map(static function ($item): string {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $items), static function (string $item): bool {
                return $item !== '';
            }));

            if (empty($items)) {
                return '';
            }

            ob_start();
            ?>
            <section class="cbt-admin-student-preview-section">
                <strong class="cbt-admin-student-preview-section-title"><?php echo esc_html($title); ?></strong>
                <div class="cbt-admin-student-preview-chip-list">
                    <?php foreach ($items as $item): ?>
                        <span class="cbt-admin-student-preview-answer-chip"><?php echo esc_html($item); ?></span>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php
            return trim((string) ob_get_clean());
        }

        private static function render_admin_student_preview_richtext_section(string $title, string $html): string
        {
            ob_start();
            ?>
            <section class="cbt-admin-student-preview-section">
                <strong class="cbt-admin-student-preview-section-title"><?php echo esc_html($title); ?></strong>
                <div class="cbt-admin-student-preview-richtext">
                    <?php echo self::render_editor_html($html); ?>
                </div>
            </section>
            <?php
            return trim((string) ob_get_clean());
        }

        public static function sanitize_editor_html(string $html): string
        {
            $allowed_protocols = array_values(array_unique(array_merge(
                wp_allowed_protocols(),
                ['data']
            )));

            return wp_kses(
                (string) $html,
                self::get_editor_allowed_html(),
                $allowed_protocols
            );
        }

        public static function sanitize_lightweight_math_html(string $html): string
        {
            $raw_html = (string) $html;
            if ($raw_html === '') {
                return '';
            }

            $fragments = [];
            $cursor = 0;
            $pattern = '/<(span|div)\b[\s\S]*?<\/\1>/i';

            if (preg_match_all($pattern, $raw_html, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $markup = isset($match[0]) ? (string) $match[0] : '';
                    $match_start = isset($match[1]) ? (int) $match[1] : 0;

                    if ($match_start > $cursor) {
                        $fragments[] = self::sanitize_lightweight_math_text_fragment(substr($raw_html, $cursor, $match_start - $cursor));
                    }

                    $wrapper_html = self::sanitize_lightweight_math_wrapper_markup($markup);
                    if ($wrapper_html !== '') {
                        $fragments[] = $wrapper_html;
                    } else {
                        $fragments[] = self::sanitize_lightweight_math_text_fragment($markup);
                    }

                    $cursor = $match_start + strlen($markup);
                }
            }

            if ($cursor < strlen($raw_html)) {
                $fragments[] = self::sanitize_lightweight_math_text_fragment(substr($raw_html, $cursor));
            }

            return trim(implode('', $fragments));
        }

        private static function sanitize_lightweight_math_text_fragment(string $fragment): string
        {
            if ($fragment === '') {
                return '';
            }

            $normalized = preg_replace('/<br\s*\/?>/i', "\n", $fragment);
            $normalized = is_string($normalized) ? $normalized : $fragment;
            $text = wp_strip_all_tags($normalized, false);

            return nl2br(esc_html($text), false);
        }

        private static function sanitize_lightweight_math_wrapper_markup(string $markup): string
        {
            if ($markup === '') {
                return '';
            }

            if (!preg_match('/^<(span|div)\b/i', $markup, $tag_match)) {
                return '';
            }

            if (!preg_match('/\bclass=(["\'])(.*?)\1/i', $markup, $class_match)) {
                return '';
            }

            $class_tokens = preg_split('/\s+/', trim((string) ($class_match[2] ?? '')));
            $class_tokens = is_array($class_tokens) ? array_values(array_filter(array_map('trim', $class_tokens))) : [];
            if (empty($class_tokens) || !in_array('cbt-math', $class_tokens, true)) {
                return '';
            }

            if (!preg_match('/\bdata-cbt-math=(["\'])(.*?)\1/i', $markup, $source_match)) {
                return '';
            }

            $source = trim(html_entity_decode((string) ($source_match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($source === '') {
                return '';
            }

            $display_mode = 'inline';
            if (preg_match('/\bdata-cbt-math-display=(["\'])(.*?)\1/i', $markup, $display_match)) {
                $display_mode = strtolower(trim((string) ($display_match[2] ?? ''))) === 'block' ? 'block' : 'inline';
            }

            $tag_name = strtolower((string) ($tag_match[1] ?? 'span')) === 'div' || $display_mode === 'block' ? 'div' : 'span';
            $class_name = $display_mode === 'block' ? 'cbt-math cbt-math-block' : 'cbt-math';

            return sprintf(
                '<%1$s class="%2$s" data-cbt-math="%3$s" data-cbt-math-display="%4$s">%5$s</%1$s>',
                $tag_name,
                esc_attr($class_name),
                esc_attr($source),
                esc_attr($display_mode),
                esc_html($source)
            );
        }

        /**
         * @return array<string,array<string,bool>>
         */
        private static function get_editor_allowed_html(): array
        {
            $allowed_html = wp_kses_allowed_html('post');
            foreach (['span', 'div', 'p', 'table', 'figure', 'figcaption', 'td', 'th', 'ul', 'ol', 'li'] as $tag_name) {
                if (!isset($allowed_html[$tag_name]) || !is_array($allowed_html[$tag_name])) {
                    $allowed_html[$tag_name] = [];
                }

                $allowed_html[$tag_name]['style'] = true;
                if (in_array($tag_name, ['span', 'div'], true)) {
                    $allowed_html[$tag_name]['class'] = true;
                }
            }

            if (!isset($allowed_html['img']) || !is_array($allowed_html['img'])) {
                $allowed_html['img'] = [];
            }
            $allowed_html['img']['style'] = true;

            foreach (['span', 'div'] as $tag_name) {
                if (!isset($allowed_html[$tag_name]) || !is_array($allowed_html[$tag_name])) {
                    $allowed_html[$tag_name] = [];
                }

                $allowed_html[$tag_name]['class'] = true;
                $allowed_html[$tag_name]['data-cbt-math'] = true;
                $allowed_html[$tag_name]['data-cbt-math-display'] = true;
            }

            return $allowed_html;
        }

        public static function render_editor_html(string $html): string
        {
            return self::normalize_rendered_rich_html(self::sanitize_editor_html($html));
        }

        private static function normalize_rendered_rich_html(string $html): string
        {
            $html = (string) $html;
            if ($html === '') {
                return '';
            }

            $spacer_markup = '<div class="cbt-rich-spacer" aria-hidden="true"></div>';
            $html = str_replace(["\r\n", "\r"], "\n", $html);
            $html = (string) preg_replace('/<p\b[^>]*>\s*(?:&nbsp;|&#160;|<br\s*\/?>|\s)*<\/p>/i', $spacer_markup, $html);
            $html = (string) preg_replace(
                '/(?:\s*<div class="cbt-rich-spacer" aria-hidden="true"><\/div>\s*){2,}/i',
                $spacer_markup,
                $html
            );
            $html = (string) preg_replace(
                '/^(?:\s*<div class="cbt-rich-spacer" aria-hidden="true"><\/div>\s*)+/i',
                '',
                $html
            );
            $html = (string) preg_replace(
                '/(?:\s*<div class="cbt-rich-spacer" aria-hidden="true"><\/div>\s*)+$/i',
                '',
                $html
            );

            $has_explicit_line_break_markup = preg_match(
                '/<(?:br|p|div|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|blockquote|pre|figure|figcaption|h[1-6]|hr)\b/i',
                $html
            ) === 1;

            if (!$has_explicit_line_break_markup && strpos($html, "\n") !== false) {
                $html = (string) preg_replace("/\n\s*\n+/", "\n", $html);
                $html = str_replace("\n", '<br />', $html);
            }

            return $html;
        }

        public static function parse_options(string $options_raw): array
        {
            return self::parse_option_entries_from_raw($options_raw, false);
        }

        public static function has_empty_correct_option_reference(string $options_raw): bool
        {
            foreach (self::parse_option_entries_from_raw($options_raw, true) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if ((int) ($item['is_correct'] ?? 0) !== 1) {
                    continue;
                }

                if (!self::has_non_empty_option_content((string) ($item['option_text'] ?? ''))) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @param array<int, array<string, mixed>> $options
         * @return int[]
         */
        public static function find_duplicate_option_indexes(array $options): array
        {
            $signatures = [];
            $duplicate_indexes = [];

            foreach ($options as $idx => $option) {
                if (!is_array($option)) {
                    continue;
                }

                $signature = self::normalize_option_compare_signature((string) ($option['option_text'] ?? ''));
                if ($signature === '') {
                    continue;
                }

                if (isset($signatures[$signature])) {
                    $duplicate_indexes[] = (int) $idx + 1;
                    continue;
                }

                $signatures[$signature] = (int) $idx + 1;
            }

            return $duplicate_indexes;
        }

        public static function has_non_empty_html_content(string $html): bool
        {
            return self::has_non_empty_option_content($html);
        }

        public static function normalize_optional_rich_text(string $html): ?string
        {
            $html = self::sanitize_editor_html($html);
            return self::has_non_empty_option_content($html) ? $html : null;
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
         * @return string[]
         */
        private static function extract_short_answer_input_key_tokens(string $question_text): array
        {
            $plain = wp_strip_all_tags((string) $question_text);
            $tokens = [];

            if (preg_match_all('/\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/i', $plain, $matches)) {
                foreach ((array) ($matches[1] ?? []) as $token) {
                    $normalized = self::normalize_short_answer_input_token((string) $token);
                    if ($normalized !== '') {
                        $tokens[] = $normalized;
                    }
                }
            }

            return $tokens;
        }

        private static function normalize_short_answer_input_token(string $token): string
        {
            $token = strtoupper(trim($token));
            if ($token === '') {
                return '';
            }

            if (is_numeric($token)) {
                $idx = (int) $token;
                if ($idx >= 1 && $idx <= 8) {
                    $token = chr(64 + $idx);
                }
            }

            return preg_match('/^[A-H]$/', $token) === 1 ? $token : '';
        }

        private static function normalize_option_compare_signature(string $html): string
        {
            $html = self::render_editor_html($html);
            if ($html === '') {
                return '';
            }

            $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (function_exists('mb_strtolower')) {
                $text = mb_strtolower($text, 'UTF-8');
            } else {
                $text = strtolower($text);
            }
            $text = preg_replace('/\s+/u', ' ', trim($text));
            $text = is_string($text) ? preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $text) : '';
            $text = $text === null ? '' : $text;

            $image_sources = [];
            if (preg_match_all('/<img\b[^>]*\bsrc=(["\'])(.*?)\1/i', $html, $matches)) {
                foreach ((array) ($matches[2] ?? []) as $src) {
                    $src = trim((string) $src);
                    if ($src === '') {
                        continue;
                    }
                    $image_sources[] = strtolower($src);
                }
            }

            $parts = [];
            if ($text !== '') {
                $parts[] = 'text:' . $text;
            }
            if (!empty($image_sources)) {
                $parts[] = 'img:' . implode('|', $image_sources);
            }

            return implode("\n", $parts);
        }

        /**
         * @return array<int, array{option_text:string,is_correct:int}>
         */
        private static function parse_option_entries_from_raw(string $options_raw, bool $preserve_empty): array
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

                        $text = isset($entry['option_text']) ? self::sanitize_editor_html((string) $entry['option_text']) : '';
                        $is_correct = !empty($entry['is_correct']) ? 1 : 0;

                        if ($preserve_empty || self::has_non_empty_option_content($text)) {
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
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }

                $parts = array_map('trim', explode('|', $line));
                $text = isset($parts[0]) ? self::sanitize_editor_html((string) $parts[0]) : '';
                $is_correct = isset($parts[1]) && $parts[1] === '1' ? 1 : 0;

                if ($preserve_empty || self::has_non_empty_option_content($text)) {
                    $items[] = [
                        'option_text' => $text,
                        'is_correct' => $is_correct,
                    ];
                }
            }

            return $items;
        }

        public static function normalize_true_false_matrix_statement_compare_value(string $value): string
        {
            $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = trim($value);
            if ($value === '') {
                return '';
            }

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
