<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Questions_Sync_Helper
{
        private static function is_bank_exam_title(string $exam_title): bool
        {
            return stripos($exam_title, 'Bank Soal - ') === 0;
        }

        public static function is_bank_question_snapshot(array $snapshot): bool
        {
            return self::is_bank_exam_title((string) ($snapshot['exam_title'] ?? ''));
        }

        public static function get_question_sync_snapshot(int $question_id): array
        {
            global $wpdb;
    
            if ($question_id <= 0) {
                return [];
            }
    
            $question_table = $wpdb->prefix . 'cbt_questions';
            $exam_table = $wpdb->prefix . 'cbt_exams';
            $option_table = $wpdb->prefix . 'cbt_options';
    
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT q.*, e.subject_id, e.title AS exam_title
                     FROM {$question_table} q
                     INNER JOIN {$exam_table} e ON e.id = q.exam_id
                     WHERE q.id = %d
                     LIMIT 1",
                    $question_id
                ),
                ARRAY_A
            );
            if (!is_array($row) || empty($row)) {
                return [];
            }
    
            $options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id = %d
                     ORDER BY id ASC",
                    $question_id
                ),
                ARRAY_A
            );
    
            $question_type = (string) ($row['question_type'] ?? '');
            $detail = CBT_Admin_Questions_Helper::get_question_type_detail($question_id, $question_type);
            $normalized_detail_text = '';
            if ($question_type === 'true_false') {
                $detail_value = array_key_exists('correct_value', $detail)
                    ? (int) $detail['correct_value']
                    : CBT_Admin_Questions_Helper::normalize_true_false_value((string) ($row['correct_text'] ?? ''));
                $normalized_detail_text = ($detail_value === 0) ? 'false' : 'true';
            } elseif ($question_type === 'short_answer') {
                $normalized_detail_text = (string) ($detail['correct_text'] ?? ($row['correct_text'] ?? ''));
            } elseif ($question_type === 'essay') {
                $normalized_detail_text = (string) ($detail['rubric_text'] ?? ($row['correct_text'] ?? ''));
            } elseif ($question_type === 'true_false_matrix') {
                $normalized_detail_text = (string) ($row['correct_text'] ?? '');
            } elseif (in_array($question_type, ['matching', 'cloze_dropdown', 'categorization', 'table_completion'], true)) {
                $normalized_detail_text = (string) ($row['correct_text'] ?? '');
            }
            if ($question_type === 'ordering') {
                $options = self::order_options_by_correct_option_ids(
                    is_array($options) ? $options : [],
                    (array) ($detail['correct_option_ids'] ?? [])
                );
            }
    
            return [
                'question_id' => (int) ($row['id'] ?? 0),
                'exam_id' => (int) ($row['exam_id'] ?? 0),
                'subject_id' => (int) ($row['subject_id'] ?? 0),
                'exam_title' => (string) ($row['exam_title'] ?? ''),
                'source_question_id' => (int) ($row['source_question_id'] ?? 0),
                'question_text' => (string) ($row['question_text'] ?? ''),
                'question_type' => $question_type,
                'points' => (float) ($row['points'] ?? 0),
                'correct_text' => (string) ($row['correct_text'] ?? ''),
                'explanation' => (string) ($row['explanation'] ?? ''),
                'normalized_detail_text' => $normalized_detail_text,
                'options' => is_array($options) ? $options : [],
                'question_detail' => $detail,
            ];
        }

        private static function order_options_by_correct_option_ids(array $options, array $correct_option_ids): array
        {
            $options_by_id = [];
            foreach ($options as $option) {
                $option_array = (array) $option;
                $option_id = (int) ($option_array['id'] ?? 0);
                if ($option_id > 0) {
                    $options_by_id[$option_id] = $option;
                }
            }

            $ordered = [];
            $used = [];
            foreach ($correct_option_ids as $option_id) {
                $option_id = absint($option_id);
                if ($option_id <= 0 || isset($used[$option_id]) || !isset($options_by_id[$option_id])) {
                    continue;
                }

                $ordered[] = $options_by_id[$option_id];
                $used[$option_id] = true;
            }

            foreach ($options as $option) {
                $option_array = (array) $option;
                $option_id = (int) ($option_array['id'] ?? 0);
                if ($option_id <= 0 || isset($used[$option_id])) {
                    continue;
                }

                $ordered[] = $option;
            }

            return $ordered;
        }

        private static function normalize_question_sync_options(array $options): array
        {
            $normalized = [];
    
            foreach ($options as $index => $option_row) {
                $option = (array) $option_row;
                $option_key = trim((string) ($option['option_key'] ?? ''));
                $normalized[] = [
                    'id' => (int) ($option['id'] ?? 0),
                    'option_key' => $option_key,
                    'match_key' => $option_key !== '' ? $option_key : '__idx_' . $index,
                    'option_text' => (string) ($option['option_text'] ?? ''),
                    'is_correct' => ((int) ($option['is_correct'] ?? 0) === 1) ? 1 : 0,
                ];
            }
    
            return $normalized;
        }

        private static function option_sync_signature(array $options): array
        {
            $signature = [];
            foreach (self::normalize_question_sync_options($options) as $option) {
                $signature[] = [
                    'match_key' => (string) ($option['match_key'] ?? ''),
                    'option_text' => (string) ($option['option_text'] ?? ''),
                    'is_correct' => (int) ($option['is_correct'] ?? 0),
                ];
            }
    
            return $signature;
        }

        private static function question_snapshots_are_sync_equivalent(array $left, array $right): bool
        {
            return
                (string) ($left['question_text'] ?? '') === (string) ($right['question_text'] ?? '') &&
                (string) ($left['question_type'] ?? '') === (string) ($right['question_type'] ?? '') &&
                round((float) ($left['points'] ?? 0), 2) === round((float) ($right['points'] ?? 0), 2) &&
                (string) ($left['correct_text'] ?? '') === (string) ($right['correct_text'] ?? '') &&
                (string) ($left['explanation'] ?? '') === (string) ($right['explanation'] ?? '') &&
                (string) ($left['normalized_detail_text'] ?? '') === (string) ($right['normalized_detail_text'] ?? '') &&
                self::option_sync_signature((array) ($left['options'] ?? [])) === self::option_sync_signature((array) ($right['options'] ?? []));
        }

        public static function question_snapshots_are_legacy_descendant_match(array $candidate, array $source): bool
        {
            if (self::question_snapshots_are_sync_equivalent($candidate, $source)) {
                return true;
            }
    
            return
                (string) ($candidate['question_type'] ?? '') === (string) ($source['question_type'] ?? '') &&
                round((float) ($candidate['points'] ?? 0), 2) === round((float) ($source['points'] ?? 0), 2) &&
                (string) ($candidate['correct_text'] ?? '') === (string) ($source['correct_text'] ?? '') &&
                (string) ($candidate['explanation'] ?? '') === (string) ($source['explanation'] ?? '') &&
                (string) ($candidate['normalized_detail_text'] ?? '') === (string) ($source['normalized_detail_text'] ?? '') &&
                self::option_sync_signature((array) ($candidate['options'] ?? [])) === self::option_sync_signature((array) ($source['options'] ?? [])) &&
                self::question_texts_look_related(
                    (string) ($candidate['question_text'] ?? ''),
                    (string) ($source['question_text'] ?? '')
                );
        }

        private static function question_texts_look_related(string $left, string $right): bool
        {
            $left = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($left))));
            $right = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($right))));
            if ($left === '' || $right === '') {
                return false;
            }
    
            if ($left === $right) {
                return true;
            }
    
            if (strpos($left, $right) !== false || strpos($right, $left) !== false) {
                return true;
            }
    
            similar_text($left, $right, $percent);
            return $percent >= 82.0;
        }

        private static function collect_descendant_question_ids_for_source(int $source_question_id, array $reference_snapshot): array
        {
            global $wpdb;
    
            if ($source_question_id <= 0 || empty($reference_snapshot)) {
                return [];
            }
    
            $question_table = $wpdb->prefix . 'cbt_questions';
            $direct_descendant_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$question_table}
                     WHERE source_question_id = %d
                       AND id <> %d",
                    $source_question_id,
                    $source_question_id
                )
            );
    
            $target_question_ids = [];
            foreach ((array) $direct_descendant_ids as $target_question_id) {
                $target_question_id = (int) $target_question_id;
                if ($target_question_id > 0) {
                    $target_question_ids[$target_question_id] = $target_question_id;
                }
            }
    
            foreach (self::find_legacy_descendant_question_ids($source_question_id, $reference_snapshot) as $target_question_id) {
                $target_question_id = (int) $target_question_id;
                if ($target_question_id > 0) {
                    $target_question_ids[$target_question_id] = $target_question_id;
                }
            }
    
            return array_values($target_question_ids);
        }

        public static function propagate_bank_question_update(int $source_question_id, array $before_snapshot, array $after_snapshot): array
        {
            global $wpdb;
    
            if ($source_question_id <= 0 || empty($before_snapshot) || empty($after_snapshot)) {
                return [];
            }
    
            $affected_exam_ids = [];
            foreach (self::collect_descendant_question_ids_for_source($source_question_id, $before_snapshot) as $target_question_id) {
                $target_snapshot = self::get_question_sync_snapshot($target_question_id);
                if (empty($target_snapshot)) {
                    continue;
                }
    
                $affected_exam_id = self::apply_source_snapshot_to_question(
                    $target_question_id,
                    $source_question_id,
                    $after_snapshot,
                    $target_snapshot
                );
                if ($affected_exam_id > 0) {
                    $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
                }
            }
    
            return array_values($affected_exam_ids);
        }

        public static function run_question_source_backfill(): array
        {
            global $wpdb;
    
            $question_table = $wpdb->prefix . 'cbt_questions';
            $exam_table = $wpdb->prefix . 'cbt_exams';
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$question_table}", 0);
            if (!is_array($columns) || !in_array('source_question_id', $columns, true)) {
                return [
                    'scanned_sources' => 0,
                    'updated_questions' => 0,
                    'new_links' => 0,
                    'affected_exams' => 0,
                    'error' => 'Kolom source_question_id belum tersedia. Muat ulang plugin atau jalankan upgrade terlebih dahulu.',
                ];
            }
    
            $bank_question_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT q.id
                     FROM {$question_table} q
                     INNER JOIN {$exam_table} e ON e.id = q.exam_id
                     WHERE e.title LIKE %s
                     ORDER BY q.id ASC",
                    'Bank Soal - %'
                )
            );
    
            $claimed_question_ids = [];
            $updated_question_ids = [];
            $new_link_ids = [];
            $affected_exam_ids = [];
            $scanned_sources = 0;
    
            foreach ((array) $bank_question_ids as $source_question_id) {
                $source_question_id = (int) $source_question_id;
                if ($source_question_id <= 0) {
                    continue;
                }
    
                $source_snapshot = self::get_question_sync_snapshot($source_question_id);
                if (empty($source_snapshot) || !self::is_bank_question_snapshot($source_snapshot)) {
                    continue;
                }
    
                $scanned_sources++;
                foreach (self::collect_descendant_question_ids_for_source($source_question_id, $source_snapshot) as $target_question_id) {
                    $target_question_id = (int) $target_question_id;
                    if ($target_question_id <= 0 || isset($claimed_question_ids[$target_question_id])) {
                        continue;
                    }
    
                    $target_snapshot = self::get_question_sync_snapshot($target_question_id);
                    if (empty($target_snapshot)) {
                        continue;
                    }
    
                    $affected_exam_id = self::apply_source_snapshot_to_question(
                        $target_question_id,
                        $source_question_id,
                        $source_snapshot,
                        $target_snapshot
                    );
                    if ($affected_exam_id <= 0) {
                        continue;
                    }
    
                    $claimed_question_ids[$target_question_id] = true;
                    $updated_question_ids[$target_question_id] = $target_question_id;
                    if ((int) ($target_snapshot['source_question_id'] ?? 0) !== $source_question_id) {
                        $new_link_ids[$target_question_id] = $target_question_id;
                    }
                    $affected_exam_ids[$affected_exam_id] = $affected_exam_id;
                }
            }
    
            if (!empty($affected_exam_ids)) {
                CBT_Cache::invalidate_exams(array_values($affected_exam_ids));
                if (class_exists('CBT_REST') && method_exists('CBT_REST', 'warm_exam_question_delivery_snapshot')) {
                    foreach (array_values($affected_exam_ids) as $affected_exam_id) {
                        CBT_REST::warm_exam_question_delivery_snapshot((int) $affected_exam_id);
                        if (method_exists('CBT_REST', 'warm_exam_start_attempt_snapshot')) {
                            CBT_REST::warm_exam_start_attempt_snapshot((int) $affected_exam_id);
                        }
                    }
                }
            }
    
            return [
                'scanned_sources' => $scanned_sources,
                'updated_questions' => count($updated_question_ids),
                'new_links' => count($new_link_ids),
                'affected_exams' => count($affected_exam_ids),
            ];
        }

        private static function find_legacy_descendant_question_ids(int $source_question_id, array $before_snapshot): array
        {
            global $wpdb;
    
            $source_exam_id = (int) ($before_snapshot['exam_id'] ?? 0);
            $subject_id = (int) ($before_snapshot['subject_id'] ?? 0);
            if ($source_question_id <= 0 || $source_exam_id <= 0 || $subject_id <= 0) {
                return [];
            }
    
            $question_table = $wpdb->prefix . 'cbt_questions';
            $exam_table = $wpdb->prefix . 'cbt_exams';
            $candidate_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT q.id
                     FROM {$question_table} q
                     INNER JOIN {$exam_table} e ON e.id = q.exam_id
                     WHERE e.subject_id = %d
                       AND e.title NOT LIKE %s
                       AND q.exam_id <> %d
                       AND q.id <> %d
                       AND (q.source_question_id IS NULL OR q.source_question_id = 0)
                       AND q.question_type = %s
                       AND q.points = %f
                       AND COALESCE(q.correct_text, '') = %s
                       AND COALESCE(q.explanation, '') = %s",
                    $subject_id,
                    'Bank Soal - %',
                    $source_exam_id,
                    $source_question_id,
                    (string) ($before_snapshot['question_type'] ?? ''),
                    (float) ($before_snapshot['points'] ?? 0),
                    (string) ($before_snapshot['correct_text'] ?? ''),
                    (string) ($before_snapshot['explanation'] ?? '')
                )
            );
    
            $matched_ids = [];
            foreach ((array) $candidate_ids as $candidate_id) {
                $candidate_id = (int) $candidate_id;
                if ($candidate_id <= 0) {
                    continue;
                }
    
                $candidate_snapshot = self::get_question_sync_snapshot($candidate_id);
                if (empty($candidate_snapshot)) {
                    continue;
                }
    
                if (!self::question_snapshots_are_legacy_descendant_match($candidate_snapshot, $before_snapshot)) {
                    continue;
                }
    
                $matched_ids[] = $candidate_id;
            }
    
            return $matched_ids;
        }

        public static function apply_source_snapshot_to_question(
            int $target_question_id,
            int $source_question_id,
            array $source_snapshot,
            array $target_snapshot = []
        ): int {
            global $wpdb;
    
            if ($target_question_id <= 0 || $source_question_id <= 0 || empty($source_snapshot)) {
                return 0;
            }
    
            if (empty($target_snapshot)) {
                $target_snapshot = self::get_question_sync_snapshot($target_question_id);
            }
            if (empty($target_snapshot)) {
                return 0;
            }
    
            $question_table = $wpdb->prefix . 'cbt_questions';
            $now = current_time('mysql');
            $question_type = (string) ($source_snapshot['question_type'] ?? 'multiple_choice');
            $correct_text = (string) ($source_snapshot['correct_text'] ?? '');
            $explanation = (string) ($source_snapshot['explanation'] ?? '');
            $old_question_type = (string) ($target_snapshot['question_type'] ?? '');
    
            $updated = $wpdb->update(
                $question_table,
                [
                    'source_question_id' => $source_question_id,
                    'is_active' => 1,
                    'question_text' => (string) ($source_snapshot['question_text'] ?? ''),
                    'question_type' => $question_type,
                    'points' => (float) ($source_snapshot['points'] ?? 0),
                    'correct_text' => $correct_text !== '' ? $correct_text : null,
                    'explanation' => $explanation !== '' ? $explanation : null,
                    'updated_at' => $now,
                ],
                ['id' => $target_question_id],
                ['%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s'],
                ['%d']
            );
            if ($updated === false) {
                return 0;
            }
    
            $option_id_map = self::sync_question_options_from_snapshot(
                $target_question_id,
                (array) ($source_snapshot['options'] ?? []),
                (array) ($target_snapshot['options'] ?? [])
            );
    
            $detail_context = [];
            if ($question_type === 'ordering') {
                $detail_context['ordered_option_ids'] = self::resolve_synced_option_ids_in_snapshot_order(
                    $target_question_id,
                    (array) ($source_snapshot['options'] ?? [])
                );
            }
            if ($question_type === 'matching') {
                $source_options = array_values((array) ($source_snapshot['options'] ?? []));
                $target_option_ids = self::resolve_synced_option_ids_in_snapshot_order($target_question_id, $source_options);
                $source_to_target_option_ids = [];
                foreach ($source_options as $idx => $source_option) {
                    $source_option_id = (int) (((array) $source_option)['id'] ?? 0);
                    $target_option_id = (int) ($target_option_ids[$idx] ?? 0);
                    if ($source_option_id > 0 && $target_option_id > 0) {
                        $source_to_target_option_ids[$source_option_id] = $target_option_id;
                    }
                }
                $matching_items = [];
                foreach ((array) (($source_snapshot['question_detail']['items'] ?? []) ?: []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $source_option_id = (int) ($item['correct_option_id'] ?? 0);
                    $target_option_id = (int) ($source_to_target_option_ids[$source_option_id] ?? 0);
                    if ($target_option_id <= 0) {
                        continue;
                    }
                    $matching_items[] = [
                        'position' => (int) ($item['item_position'] ?? $item['position'] ?? (count($matching_items) + 1)),
                        'item_key' => (string) ($item['item_key'] ?? (count($matching_items) + 1)),
                        'prompt_text' => (string) ($item['prompt_text'] ?? ''),
                        'correct_option_id' => $target_option_id,
                    ];
                }
                $detail_context['matching_items'] = $matching_items;
            }
            if ($question_type === 'cloze_dropdown') {
                $detail_context['cloze_blanks'] = (array) (($source_snapshot['question_detail']['blanks'] ?? []) ?: []);
            }
            if ($question_type === 'categorization') {
                $source_options = array_values((array) ($source_snapshot['options'] ?? []));
                $target_option_ids = self::resolve_synced_option_ids_in_snapshot_order($target_question_id, $source_options);
                $source_to_target_option_ids = [];
                foreach ($source_options as $idx => $source_option) {
                    $source_option_id = (int) (((array) $source_option)['id'] ?? 0);
                    $target_option_id = (int) ($target_option_ids[$idx] ?? 0);
                    if ($source_option_id > 0 && $target_option_id > 0) {
                        $source_to_target_option_ids[$source_option_id] = $target_option_id;
                    }
                }
                $categorization_items = [];
                foreach ((array) (($source_snapshot['question_detail']['items'] ?? []) ?: []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $source_option_id = (int) ($item['correct_option_id'] ?? 0);
                    $target_option_id = (int) ($source_to_target_option_ids[$source_option_id] ?? 0);
                    if ($target_option_id <= 0) {
                        continue;
                    }
                    $categorization_items[] = [
                        'position' => (int) ($item['item_position'] ?? $item['position'] ?? (count($categorization_items) + 1)),
                        'item_key' => (string) ($item['item_key'] ?? (count($categorization_items) + 1)),
                        'item_text' => (string) ($item['item_text'] ?? ''),
                        'correct_option_id' => $target_option_id,
                    ];
                }
                $detail_context['categorization_items'] = $categorization_items;
            }
            if ($question_type === 'table_completion') {
                $detail_context['table_completion'] = [
                    'row_count' => (int) ($source_snapshot['question_detail']['row_count'] ?? 2),
                    'column_count' => (int) ($source_snapshot['question_detail']['column_count'] ?? 2),
                    'cells' => (array) (($source_snapshot['question_detail']['cells'] ?? []) ?: []),
                ];
            }

            CBT_Admin_Questions_Helper::save_question_type_detail(
                $target_question_id,
                $question_type,
                (string) ($source_snapshot['normalized_detail_text'] ?? ''),
                $detail_context
            );
    
            if ($old_question_type !== $question_type) {
                self::clear_question_answer_records($target_question_id, true);
                if (class_exists('CBT_Runtime')) {
                    CBT_Runtime::remap_active_attempt_answers_for_question($target_question_id, [], true);
                }
            } elseif (self::question_type_uses_choice_options($question_type)) {
                $preserve_option_order = $question_type === 'ordering';
                self::remap_question_answer_option_ids($target_question_id, $option_id_map, $preserve_option_order);
                if (class_exists('CBT_Runtime')) {
                    CBT_Runtime::remap_active_attempt_answers_for_question($target_question_id, $option_id_map, false, $preserve_option_order);
                }
            }
    
            return (int) ($target_snapshot['exam_id'] ?? 0);
        }

        private static function sync_question_options_from_snapshot(int $question_id, array $desired_options, array $existing_options = []): array
        {
            global $wpdb;
    
            $option_table = $wpdb->prefix . 'cbt_options';
            if ($question_id <= 0) {
                return [];
            }
    
            if (empty($existing_options)) {
                $existing_options = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, option_key, option_text, is_correct
                         FROM {$option_table}
                         WHERE question_id = %d
                         ORDER BY id ASC",
                        $question_id
                    ),
                    ARRAY_A
                );
            }
    
            $normalized_existing = self::normalize_question_sync_options((array) $existing_options);
            $normalized_desired = self::normalize_question_sync_options($desired_options);
    
            $existing_ids_by_match_key = [];
            foreach ($normalized_existing as $existing_option) {
                $existing_id = (int) ($existing_option['id'] ?? 0);
                $match_key = (string) ($existing_option['match_key'] ?? '');
                if ($existing_id <= 0 || $match_key === '') {
                    continue;
                }
    
                $existing_ids_by_match_key[$match_key] = $existing_id;
            }
    
            $new_ids_by_match_key = [];
            $used_existing_ids = [];
            $now = current_time('mysql');
    
            foreach ($normalized_desired as $desired_option) {
                $match_key = (string) ($desired_option['match_key'] ?? '');
                $existing_id = $match_key !== '' && isset($existing_ids_by_match_key[$match_key])
                    ? (int) $existing_ids_by_match_key[$match_key]
                    : 0;
    
                if ($existing_id > 0) {
                    $wpdb->update(
                        $option_table,
                        [
                            'option_key' => (string) ($desired_option['option_key'] ?? ''),
                            'option_text' => (string) ($desired_option['option_text'] ?? ''),
                            'is_correct' => (int) ($desired_option['is_correct'] ?? 0),
                        ],
                        ['id' => $existing_id],
                        ['%s', '%s', '%d'],
                        ['%d']
                    );
                    $used_existing_ids[$existing_id] = true;
                    $new_ids_by_match_key[$match_key] = $existing_id;
                    continue;
                }
    
                $inserted = $wpdb->insert(
                    $option_table,
                    [
                        'question_id' => $question_id,
                        'option_key' => (string) ($desired_option['option_key'] ?? ''),
                        'option_text' => (string) ($desired_option['option_text'] ?? ''),
                        'is_correct' => (int) ($desired_option['is_correct'] ?? 0),
                        'created_at' => $now,
                    ],
                    ['%d', '%s', '%s', '%d', '%s']
                );
                if (!$inserted) {
                    continue;
                }
    
                $new_option_id = (int) $wpdb->insert_id;
                if ($new_option_id > 0) {
                    $used_existing_ids[$new_option_id] = true;
                    $new_ids_by_match_key[$match_key] = $new_option_id;
                }
            }
    
            foreach ($normalized_existing as $existing_option) {
                $existing_id = (int) ($existing_option['id'] ?? 0);
                if ($existing_id <= 0 || isset($used_existing_ids[$existing_id])) {
                    continue;
                }
                $wpdb->delete($option_table, ['id' => $existing_id], ['%d']);
            }
    
            $old_to_new = [];
            foreach ($existing_ids_by_match_key as $match_key => $old_option_id) {
                $new_option_id = (int) ($new_ids_by_match_key[$match_key] ?? 0);
                if ($old_option_id > 0 && $new_option_id > 0) {
                    $old_to_new[$old_option_id] = $new_option_id;
                }
            }
    
            return $old_to_new;
        }

        private static function question_type_uses_choice_options(string $question_type): bool
        {
            return in_array($question_type, ['multiple_choice', 'multiple_answer', 'true_false', 'ordering'], true);
        }

        private static function resolve_synced_option_ids_in_snapshot_order(int $question_id, array $desired_options): array
        {
            global $wpdb;

            if ($question_id <= 0 || empty($desired_options)) {
                return [];
            }

            $option_table = $wpdb->prefix . 'cbt_options';
            $existing_options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id = %d
                     ORDER BY id ASC",
                    $question_id
                ),
                ARRAY_A
            );

            $ids_by_match_key = [];
            foreach (self::normalize_question_sync_options((array) $existing_options) as $existing_option) {
                $option_id = (int) ($existing_option['id'] ?? 0);
                $match_key = (string) ($existing_option['match_key'] ?? '');
                if ($option_id > 0 && $match_key !== '') {
                    $ids_by_match_key[$match_key] = $option_id;
                }
            }

            $ordered_ids = [];
            foreach (self::normalize_question_sync_options($desired_options) as $desired_option) {
                $match_key = (string) ($desired_option['match_key'] ?? '');
                $option_id = $match_key !== '' ? (int) ($ids_by_match_key[$match_key] ?? 0) : 0;
                if ($option_id > 0) {
                    $ordered_ids[] = $option_id;
                }
            }

            return array_values(array_unique($ordered_ids));
        }

        private static function remap_question_answer_option_ids(int $question_id, array $option_id_map, bool $preserve_order = false): void
        {
            global $wpdb;
    
            if ($question_id <= 0) {
                return;
            }
    
            $answer_table = $wpdb->prefix . 'cbt_answers';
            $answer_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, attempt_id, selected_option_ids
                     FROM {$answer_table}
                     WHERE question_id = %d
                       AND selected_option_ids IS NOT NULL
                       AND selected_option_ids <> ''",
                    $question_id
                ),
                ARRAY_A
            );
    
            $affected_attempt_ids = [];
            foreach ((array) $answer_rows as $answer_row) {
                $answer_id = (int) ($answer_row['id'] ?? 0);
                if ($answer_id <= 0) {
                    continue;
                }
    
                $existing_option_ids = json_decode((string) ($answer_row['selected_option_ids'] ?? ''), true);
                if (!is_array($existing_option_ids)) {
                    $existing_option_ids = [];
                }
                $existing_option_ids = array_values(array_unique(array_filter(array_map('intval', $existing_option_ids), static function (int $option_id): bool {
                    return $option_id > 0;
                })));
                if (!$preserve_order) {
                    sort($existing_option_ids);
                }
    
                $remapped_option_ids = [];
                $seen_remapped_option_ids = [];
                foreach ($existing_option_ids as $existing_option_id) {
                    $existing_option_id = (int) $existing_option_id;
                    $new_option_id = isset($option_id_map[$existing_option_id]) ? (int) $option_id_map[$existing_option_id] : 0;
                    if ($new_option_id > 0 && !isset($seen_remapped_option_ids[$new_option_id])) {
                        $seen_remapped_option_ids[$new_option_id] = true;
                        $remapped_option_ids[] = $new_option_id;
                    }
                }
    
                $remapped_option_ids = array_values($remapped_option_ids);
                if (!$preserve_order) {
                    sort($remapped_option_ids);
                }
                if ($remapped_option_ids === $existing_option_ids) {
                    continue;
                }
                $selected_option_ids = !empty($remapped_option_ids) ? wp_json_encode($remapped_option_ids) : null;
    
                $wpdb->update(
                    $answer_table,
                    [
                        'selected_option_ids' => $selected_option_ids,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $answer_id],
                    ['%s', '%s'],
                    ['%d']
                );
                $attempt_id = (int) ($answer_row['attempt_id'] ?? 0);
                if ($attempt_id > 0) {
                    $affected_attempt_ids[$attempt_id] = $attempt_id;
                }
            }
    
            if (!empty($affected_attempt_ids)) {
                CBT_Cache::invalidate_attempts(array_values($affected_attempt_ids));
            }
        }

        private static function clear_question_answer_records(int $question_id, bool $clear_answer_text): void
        {
            global $wpdb;
    
            if ($question_id <= 0) {
                return;
            }
    
            $answer_table = $wpdb->prefix . 'cbt_answers';
            $affected_attempt_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT attempt_id
                     FROM {$answer_table}
                     WHERE question_id = %d",
                    $question_id
                )
            );
            $fields = [
                'selected_option_ids' => null,
                'is_correct' => null,
                'score_awarded' => 0,
                'updated_at' => current_time('mysql'),
            ];
            $formats = ['%s', '%d', '%f', '%s'];
    
            if ($clear_answer_text) {
                $fields['answer_text'] = null;
                array_splice($formats, 1, 0, ['%s']);
            }
    
            $wpdb->update(
                $answer_table,
                $fields,
                ['question_id' => $question_id],
                $formats,
                ['%d']
            );
    
            if (!empty($affected_attempt_ids)) {
                CBT_Cache::invalidate_attempts(array_map('intval', (array) $affected_attempt_ids));
            }
        }

}
