<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Exam_Availability_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-exam-availability-cache.php';
}

if (!class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-exam-availability-auto-warm-service.php';
}

if (!class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-exam-start-attempt-snapshot-cache.php';
}

if (!class_exists('CBT_Start_Attempt_Gate_Service')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-start-attempt-gate-service.php';
}

if (!class_exists('CBT_Attempt_Session_Snapshot_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-attempt-session-snapshot-cache.php';
}

if (!class_exists('CBT_Attempt_Question_Contract_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-attempt-question-contract-cache.php';
}

if (!class_exists('CBT_Attempt_Runtime_Snapshot_Service')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-attempt-runtime-snapshot-service.php';
}

if (!class_exists('CBT_Exam_Preflight_Service')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-exam-preflight-service.php';
}

if (!class_exists('CBT_Student_Profile_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-student-profile-cache.php';
}

if (!class_exists('CBT_Login_Auth_Snapshot_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-login-auth-snapshot-cache.php';
}

if (!class_exists('CBT_Question_Submission_Context_Cache')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-question-submission-context-cache.php';
}

if (!class_exists('CBT_Plugin_Redis_Reset_Service')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-plugin-redis-reset-service.php';
}

if (!class_exists('CBT_Live_Proctoring_Presence')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-live-proctoring-presence.php';
}

if (!class_exists('CBT_Runtime')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-runtime.php';
}

final class CBT_Admin_Exams_Service
{
    private const TEST_REDIRECT_SIGNAL = '__cbt_admin_exams_redirect__';
    private const SNAPSHOT_PREVIEW_PER_PAGE = 7;
    private const STUDENT_SNAPSHOT_PER_PAGE = 25;
    private const EXAM_READINESS_PROBLEM_PER_PAGE = 10;
    private const HERO_OPERATIONAL_STATS_TTL = 20;
    public const SNAPSHOT_TAB_PREFLIGHT = 'preflight';
    public const SNAPSHOT_TAB_QUESTION_MONITOR = 'question_monitor';
    public const SNAPSHOT_TAB_START_MONITOR = 'start_monitor';
    public const SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR = 'submission_context_monitor';
    public const SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR = 'session_runtime_monitor';
    public const SNAPSHOT_TAB_EXAM_MONITOR = 'exam_monitor';
    public const SNAPSHOT_TAB_PROFILE_MONITOR = 'profile_monitor';
    public const SNAPSHOT_TAB_LOGIN_MONITOR = 'login_monitor';
    public const SNAPSHOT_TAB_QUESTIONS = 'questions';
    public const SNAPSHOT_TAB_STUDENTS = 'students';

    public static function can_manage_exams(): bool
    {
        return self::is_admin_scope() || current_user_can('cbt_manage_exams');
    }

    public static function can_manage_exam_snapshots(): bool
    {
        return current_user_can('manage_options');
    }

    public static function is_admin_scope(): bool
    {
        return current_user_can('manage_options') || current_user_can('cbt_manage_system');
    }

    private static function is_bank_exam_title(string $exam_title): bool
    {
        return stripos($exam_title, 'Bank Soal - ') === 0;
    }

    /**
     * @return array{slug:string,label:string,class:string,is_mixed:bool}
     */
    private static function build_exam_topology_descriptor(int $bank_backed_count, int $legacy_count): array
    {
        if ($bank_backed_count > 0 && $legacy_count > 0) {
            return [
                'slug' => 'mixed',
                'label' => 'Mixed',
                'class' => 'mixed',
                'is_mixed' => true,
            ];
        }

        if ($bank_backed_count > 0) {
            return [
                'slug' => 'bank',
                'label' => 'Bank-backed',
                'class' => 'bank',
                'is_mixed' => false,
            ];
        }

        if ($legacy_count > 0) {
            return [
                'slug' => 'legacy',
                'label' => 'Legacy',
                'class' => 'legacy',
                'is_mixed' => false,
            ];
        }

        return [
            'slug' => 'empty',
            'label' => 'Belum Ada Soal',
            'class' => 'empty',
            'is_mixed' => false,
        ];
    }

    /**
     * @param array<string,mixed> $summary
     * @return string[]
     */
    private static function build_exam_sync_notice_parts(array $summary): array
    {
        $parts = [];

        $synced_question_count = max(0, (int) ($summary['synced_question_count'] ?? 0));
        if ($synced_question_count > 0) {
            $parts[] = $synced_question_count . ' soal tersinkron';
        }

        $updated_existing_count = max(0, (int) ($summary['updated_existing_count'] ?? 0));
        $created_new_count = max(0, (int) ($summary['created_new_count'] ?? 0));
        $linked_legacy_match_count = max(0, (int) ($summary['linked_legacy_match_count'] ?? 0));
        $archived_question_count = max(0, (int) ($summary['archived_question_count'] ?? 0));
        $deleted_question_count = max(0, (int) ($summary['deleted_question_count'] ?? 0));
        $preserved_attempt_history_count = max(0, (int) ($summary['preserved_attempt_history_count'] ?? 0));
        $legacy_active_count = max(0, (int) ($summary['legacy_active_count'] ?? 0));

        if ($updated_existing_count > 0) {
            $parts[] = 'Update ' . $updated_existing_count;
        }
        if ($created_new_count > 0) {
            $parts[] = 'Baru ' . $created_new_count;
        }
        if ($linked_legacy_match_count > 0) {
            $parts[] = 'Relink legacy ' . $linked_legacy_match_count;
        }
        if ($archived_question_count > 0) {
            $parts[] = 'Arsip ' . $archived_question_count;
        }
        if ($deleted_question_count > 0) {
            $parts[] = 'Hapus ' . $deleted_question_count;
        }
        if ($preserved_attempt_history_count > 0) {
            $parts[] = 'Preserve history ' . $preserved_attempt_history_count;
        }
        if ($legacy_active_count > 0) {
            $parts[] = 'Masih legacy ' . $legacy_active_count;
        }

        return $parts;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private static function build_exam_sync_notice_message(bool $is_update, array $summary): string
    {
        $base_message = $is_update ? 'Exam updated' : 'Exam created';
        $parts = self::build_exam_sync_notice_parts($summary);
        if (empty($parts)) {
            return $base_message;
        }

        return $base_message . ' - ' . implode(' | ', $parts);
    }

    /**
     * @param array<int,int> $exam_ids
     * @return array<int,array<string,mixed>>
     */
    private static function build_exam_topology_map(array $exam_ids): array
    {
        global $wpdb;

        $exam_ids = array_values(array_unique(array_filter(array_map('absint', $exam_ids))));
        if (empty($exam_ids)) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $placeholders = implode(',', array_fill(0, count($exam_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.exam_id AS target_exam_id,
                        q.id AS target_question_id,
                        q.source_question_id,
                        COALESCE(source_exam.title, '') AS source_exam_title
                 FROM {$question_table} q
                 LEFT JOIN {$question_table} source_question ON source_question.id = q.source_question_id
                 LEFT JOIN {$exam_table} source_exam ON source_exam.id = source_question.exam_id
                 WHERE q.exam_id IN ({$placeholders})
                   AND COALESCE(q.is_active, 1) = 1",
                ...$exam_ids
            ),
            ARRAY_A
        );

        $map = [];
        foreach ($exam_ids as $exam_id) {
            $map[$exam_id] = [
                'topology_slug' => 'empty',
                'topology_label' => 'Belum Ada Soal',
                'topology_class' => 'empty',
                'topology_is_mixed' => false,
                'total_active_questions' => 0,
                'bank_backed_question_count' => 0,
                'legacy_question_count' => 0,
                'topology_summary_text' => 'Belum ada soal aktif',
            ];
        }

        foreach ((array) $rows as $row) {
            $target_exam_id = (int) ($row['target_exam_id'] ?? 0);
            if ($target_exam_id <= 0 || !isset($map[$target_exam_id])) {
                continue;
            }

            $map[$target_exam_id]['total_active_questions']++;
            $source_question_id = (int) ($row['source_question_id'] ?? 0);
            $source_exam_title = (string) ($row['source_exam_title'] ?? '');
            if ($source_question_id > 0 && self::is_bank_exam_title($source_exam_title)) {
                $map[$target_exam_id]['bank_backed_question_count']++;
            } else {
                $map[$target_exam_id]['legacy_question_count']++;
            }
        }

        foreach ($map as $exam_id => $summary) {
            $descriptor = self::build_exam_topology_descriptor(
                (int) ($summary['bank_backed_question_count'] ?? 0),
                (int) ($summary['legacy_question_count'] ?? 0)
            );
            $map[$exam_id]['topology_slug'] = $descriptor['slug'];
            $map[$exam_id]['topology_label'] = $descriptor['label'];
            $map[$exam_id]['topology_class'] = $descriptor['class'];
            $map[$exam_id]['topology_is_mixed'] = $descriptor['is_mixed'];

            $summary_parts = [];
            if ((int) ($summary['bank_backed_question_count'] ?? 0) > 0) {
                $summary_parts[] = 'Bank ' . (int) $summary['bank_backed_question_count'];
            }
            if ((int) ($summary['legacy_question_count'] ?? 0) > 0) {
                $summary_parts[] = 'Legacy ' . (int) $summary['legacy_question_count'];
            }
            $map[$exam_id]['topology_summary_text'] = !empty($summary_parts)
                ? implode(' | ', $summary_parts)
                : 'Belum ada soal aktif';
        }

        return $map;
    }

    private static function question_sync_signature(array $snapshot): string
    {
        $options_signature = [];
        foreach ((array) ($snapshot['options'] ?? []) as $index => $option_row) {
            $option = (array) $option_row;
            $option_key = trim((string) ($option['option_key'] ?? ''));
            $options_signature[] = [
                'match_key' => $option_key !== '' ? $option_key : '__idx_' . $index,
                'option_text' => (string) ($option['option_text'] ?? ''),
                'is_correct' => ((int) ($option['is_correct'] ?? 0) === 1) ? 1 : 0,
            ];
        }

        return md5((string) wp_json_encode([
            'question_text' => (string) ($snapshot['question_text'] ?? ''),
            'question_type' => (string) ($snapshot['question_type'] ?? ''),
            'points' => round((float) ($snapshot['points'] ?? 0), 2),
            'correct_text' => (string) ($snapshot['correct_text'] ?? ''),
            'explanation' => (string) ($snapshot['explanation'] ?? ''),
            'normalized_detail_text' => (string) ($snapshot['normalized_detail_text'] ?? ''),
            'options' => $options_signature,
        ]));
    }

    private static function question_snapshots_are_sync_equivalent(array $left, array $right): bool
    {
        return self::question_sync_signature($left) === self::question_sync_signature($right);
    }

    /**
     * @param array<int,array<string,mixed>> $question_rows
     * @param array<int,array<string,mixed>> $selected_source_meta_map
     * @return array<int,array<string,mixed>>
     */
    private static function enrich_source_question_rows(array $question_rows, array $selected_source_meta_map = []): array
    {
        foreach ($question_rows as &$question_row) {
            $source_question_id = (int) ($question_row['id'] ?? 0);
            $source_exam_title = (string) ($question_row['exam_title'] ?? '');
            $default_lineage_kind = self::is_bank_exam_title($source_exam_title) ? 'bank' : 'legacy';
            $default_lineage_label = $default_lineage_kind === 'bank' ? 'Bank Soal' : 'Legacy Source';
            $default_lineage_class = $default_lineage_kind === 'bank' ? 'bank' : 'legacy';
            $default_lineage_hint = $default_lineage_kind === 'bank'
                ? 'Root source berada di Bank Soal.'
                : 'Source masih berasal dari exam induk lama.';
            $default_source_context_label = $default_lineage_kind === 'bank' ? 'Bank Soal' : 'Exam Sumber';
            $selected_meta = $selected_source_meta_map[$source_question_id] ?? null;

            $question_row['lineage_kind'] = is_array($selected_meta)
                ? (string) ($selected_meta['lineage_kind'] ?? $default_lineage_kind)
                : $default_lineage_kind;
            $question_row['lineage_label'] = is_array($selected_meta)
                ? (string) ($selected_meta['lineage_label'] ?? $default_lineage_label)
                : $default_lineage_label;
            $question_row['lineage_class'] = is_array($selected_meta)
                ? (string) ($selected_meta['lineage_class'] ?? $default_lineage_class)
                : $default_lineage_class;
            $question_row['lineage_hint'] = is_array($selected_meta)
                ? (string) ($selected_meta['lineage_hint'] ?? $default_lineage_hint)
                : $default_lineage_hint;
            $question_row['source_context_label'] = is_array($selected_meta)
                ? (string) ($selected_meta['source_context_label'] ?? $default_source_context_label)
                : $default_source_context_label;
            $question_row['source_context_display'] = $question_row['source_context_label'] . ' · ' . $source_exam_title;
        }
        unset($question_row);

        return $question_rows;
    }

    /**
     * @param int[] $source_question_ids
     * @return array<int,array<string,mixed>>
     */
    private static function build_selected_source_question_rows(array $source_question_ids): array
    {
        global $wpdb;

        $source_question_ids = array_values(array_unique(array_filter(array_map('absint', $source_question_ids))));
        if (empty($source_question_ids)) {
            return [];
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $placeholders = implode(',', array_fill(0, count($source_question_ids), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.id,
                        q.exam_id,
                        q.question_text,
                        q.question_type,
                        q.points,
                        e.title AS exam_title,
                        e.subject_id,
                        s.name AS subject_name
                 FROM {$question_table} q
                 INNER JOIN {$exam_table} e ON e.id = q.exam_id
                 LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                 WHERE q.id IN ({$placeholders})",
                ...$source_question_ids
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $rows_by_id = [];
        foreach ((array) $rows as $row) {
            $question_id = (int) ($row['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            $rows_by_id[$question_id] = $row;
        }

        $ordered_rows = [];
        foreach ($source_question_ids as $question_id) {
            if (isset($rows_by_id[$question_id])) {
                $ordered_rows[] = $rows_by_id[$question_id];
            }
        }

        return $ordered_rows;
    }

    /**
     * @param int[] $question_ids
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function build_question_options_map(array $question_ids): array
    {
        global $wpdb;

        $question_ids = array_values(array_unique(array_filter(array_map('absint', $question_ids))));
        if (empty($question_ids)) {
            return [];
        }

        $option_table = $wpdb->prefix . 'cbt_options';
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $option_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_id, option_key, option_text, is_correct
                 FROM {$option_table}
                 WHERE question_id IN ({$placeholders})
                 ORDER BY id ASC",
                ...$question_ids
            ),
            ARRAY_A
        );

        $options_map = [];
        foreach ((array) $option_rows as $option_row) {
            $question_id = (int) ($option_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            if (!isset($options_map[$question_id])) {
                $options_map[$question_id] = [];
            }
            $options_map[$question_id][] = $option_row;
        }

        return $options_map;
    }

    /**
     * @param array<int,array<string,mixed>> $question_rows
     * @param array<int,array<int,array<string,mixed>>> $options_map
     * @return array<int,string>
     */
    private static function build_question_preview_html_map(array $question_rows, array $options_map = []): array
    {
        $preview_map = [];

        foreach ($question_rows as $question_row) {
            $question_id = (int) ($question_row['id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }

            $question_type = (string) ($question_row['question_type'] ?? '');
            $question_type_label = (string) ($question_row['question_type_label'] ?? CBT_Admin_Questions_Helper::get_question_type_label($question_type));
            $question_options = $options_map[$question_id] ?? [];
            $question_detail = CBT_Admin_Questions_Helper::get_question_type_detail($question_id, $question_type);
            $meta_lines = [];
            $subject_name = trim((string) ($question_row['subject_name'] ?? ''));
            if ($subject_name !== '') {
                $meta_lines[] = 'Mapel: ' . $subject_name;
            }

            $source_context_label = trim((string) ($question_row['source_context_label'] ?? 'Sumber'));
            $source_context_display = trim((string) ($question_row['source_context_display'] ?? ($question_row['exam_title'] ?? '')));
            if ($source_context_display !== '') {
                $meta_lines[] = ($source_context_label !== '' ? $source_context_label : 'Sumber') . ': ' . $source_context_display;
            }

            $extra_chips = [];
            $lineage_label = trim((string) ($question_row['lineage_label'] ?? ''));
            if ($lineage_label !== '') {
                $extra_chips[] = [
                    'label' => $lineage_label,
                    'tone' => 'source',
                ];
            }

            $actions_html = '';
            $edit_url = trim((string) ($question_row['edit_url'] ?? ''));
            if ($edit_url !== '') {
                $actions_html = sprintf(
                    '<a class="button button-secondary" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url($edit_url),
                    esc_html__('Edit Soal', 'cbt-exam-system')
                );
            }

            $preview_map[$question_id] = CBT_Admin_Questions_Helper::render_admin_student_preview_card(
                $question_row,
                $question_options,
                $question_detail,
                [
                    'eyebrow' => 'Soal #' . $question_id,
                    'type_label' => $question_type_label,
                    'meta_lines' => $meta_lines,
                    'extra_chips' => $extra_chips,
                    'note_text' => (string) ($question_row['lineage_hint'] ?? ''),
                    'actions_html' => $actions_html,
                ]
            );
        }

        return $preview_map;
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_exam_lineage_context(int $exam_id): array
    {
        global $wpdb;

        $default = [
            'topology_slug' => 'empty',
            'topology_label' => 'Belum Ada Soal',
            'topology_class' => 'empty',
            'topology_is_mixed' => false,
            'total_active_questions' => 0,
            'bank_backed_question_count' => 0,
            'legacy_question_count' => 0,
            'linked_legacy_count' => 0,
            'source_meta_map' => [],
        ];

        if ($exam_id <= 0) {
            return $default;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.id,
                        q.source_question_id,
                        COALESCE(source_exam.title, '') AS source_exam_title
                 FROM {$question_table} q
                 LEFT JOIN {$question_table} source_question ON source_question.id = q.source_question_id
                 LEFT JOIN {$exam_table} source_exam ON source_exam.id = source_question.exam_id
                 WHERE q.exam_id = %d
                   AND COALESCE(q.is_active, 1) = 1
                 ORDER BY q.id ASC",
                $exam_id
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return $default;
        }

        $summary = $default;
        $source_snapshot_cache = [];
        $target_snapshot_cache = [];

        foreach ((array) $rows as $row) {
            $target_question_id = (int) ($row['id'] ?? 0);
            if ($target_question_id <= 0) {
                continue;
            }

            $summary['total_active_questions']++;
            $source_question_id = (int) ($row['source_question_id'] ?? 0);
            $source_exam_title = (string) ($row['source_exam_title'] ?? '');
            $source_key = $source_question_id > 0 ? $source_question_id : $target_question_id;

            if ($source_question_id > 0 && self::is_bank_exam_title($source_exam_title)) {
                $summary['bank_backed_question_count']++;
                if (!isset($source_snapshot_cache[$source_question_id])) {
                    $source_snapshot_cache[$source_question_id] = CBT_Admin_Questions_Sync_Helper::get_question_sync_snapshot($source_question_id);
                }
                if (!isset($target_snapshot_cache[$target_question_id])) {
                    $target_snapshot_cache[$target_question_id] = CBT_Admin_Questions_Sync_Helper::get_question_sync_snapshot($target_question_id);
                }

                $source_snapshot = (array) ($source_snapshot_cache[$source_question_id] ?? []);
                $target_snapshot = (array) ($target_snapshot_cache[$target_question_id] ?? []);
                $is_linked_legacy = !empty($source_snapshot)
                    && !empty($target_snapshot)
                    && !self::question_snapshots_are_sync_equivalent($target_snapshot, $source_snapshot)
                    && CBT_Admin_Questions_Sync_Helper::question_snapshots_are_legacy_descendant_match($target_snapshot, $source_snapshot);

                if ($is_linked_legacy) {
                    $summary['linked_legacy_count']++;
                }

                if (!isset($summary['source_meta_map'][$source_key])) {
                    $summary['source_meta_map'][$source_key] = [
                        'lineage_kind' => $is_linked_legacy ? 'linked' : 'bank',
                        'lineage_label' => $is_linked_legacy ? 'Legacy Descendant Linked' : 'Bank Soal',
                        'lineage_class' => $is_linked_legacy ? 'linked' : 'bank',
                        'lineage_hint' => $is_linked_legacy
                            ? 'Soal exam lama sudah dipautkan ke source bank ini.'
                            : 'Root source berada di Bank Soal.',
                        'source_context_label' => 'Bank Soal',
                    ];
                } elseif ($is_linked_legacy) {
                    $summary['source_meta_map'][$source_key]['lineage_kind'] = 'linked';
                    $summary['source_meta_map'][$source_key]['lineage_label'] = 'Legacy Descendant Linked';
                    $summary['source_meta_map'][$source_key]['lineage_class'] = 'linked';
                    $summary['source_meta_map'][$source_key]['lineage_hint'] = 'Soal exam lama sudah dipautkan ke source bank ini.';
                }

                continue;
            }

            $summary['legacy_question_count']++;
            if (!isset($summary['source_meta_map'][$source_key])) {
                $summary['source_meta_map'][$source_key] = [
                    'lineage_kind' => 'legacy',
                    'lineage_label' => 'Legacy Source',
                    'lineage_class' => 'legacy',
                    'lineage_hint' => 'Source masih berasal dari exam induk lama.',
                    'source_context_label' => 'Exam Sumber',
                ];
            }
        }

        $descriptor = self::build_exam_topology_descriptor(
            (int) $summary['bank_backed_question_count'],
            (int) $summary['legacy_question_count']
        );
        $summary['topology_slug'] = $descriptor['slug'];
        $summary['topology_label'] = $descriptor['label'];
        $summary['topology_class'] = $descriptor['class'];
        $summary['topology_is_mixed'] = $descriptor['is_mixed'];

        return $summary;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $option_table = $wpdb->prefix . 'cbt_options';
        $is_admin_scope = self::is_admin_scope();
        $can_manage_exam_snapshots = self::can_manage_exam_snapshots();
        $current_user_id = get_current_user_id();

        $subjects = $wpdb->get_results(
            "SELECT id, name, code
             FROM {$subject_table}
             ORDER BY name ASC",
            ARRAY_A
        );
        $exam_status_labels = [
            'draft' => 'Draft',
            'published' => 'Published',
            'closed' => 'Closed',
        ];
        $exam_list_state = self::get_exam_list_state_from_request($query);
        $exam_snapshot_filter_state = self::get_exam_snapshot_filter_state_from_request($query);
        $exam_snapshot_preview_pages = self::get_exam_snapshot_preview_pages_from_request($query);
        $exam_readiness_page = self::get_exam_readiness_page_from_request($query);
        $exam_snapshot_tab = self::get_exam_snapshot_tab_from_request($query);
        $student_snapshot_filter_state = self::get_student_snapshot_filter_state_from_request($query);
        $valid_subject_ids = array_map('intval', wp_list_pluck((array) $subjects, 'id'));
        if ($exam_list_state['subject_id'] > 0 && !in_array($exam_list_state['subject_id'], $valid_subject_ids, true)) {
            $exam_list_state['subject_id'] = 0;
        }

        $editing_id = isset($query['edit']) ? absint(wp_unslash((string) $query['edit'])) : 0;
        $editing_exam = null;
        $blocked_bank_exam_error = '';
        $blocked_bank_exam_context = [
            'is_blocked' => false,
            'exam_id' => 0,
            'title' => '',
            'questions_url' => add_query_arg(['page' => 'cbt-question-bank'], admin_url('admin.php')),
            'list_url' => add_query_arg(['page' => 'cbt-exams', 'cbt_exam_panel' => 'list'], admin_url('admin.php')),
        ];
        if ($editing_id > 0) {
            if ($is_admin_scope) {
                $editing_exam = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM {$exam_table} WHERE id = %d", $editing_id),
                    ARRAY_A
                );
            } else {
                $editing_exam = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$exam_table} WHERE id = %d AND created_by = %d",
                        $editing_id,
                        $current_user_id
                    ),
                    ARRAY_A
                );
            }

            if ($editing_exam && self::is_bank_exam_title((string) ($editing_exam['title'] ?? ''))) {
                $blocked_bank_exam_context = [
                    'is_blocked' => true,
                    'exam_id' => (int) ($editing_exam['id'] ?? 0),
                    'title' => (string) ($editing_exam['title'] ?? ''),
                    'questions_url' => add_query_arg(
                        [
                            'page' => 'cbt-question-bank',
                            'subject_id' => (int) ($editing_exam['subject_id'] ?? 0),
                        ],
                        admin_url('admin.php')
                    ),
                    'list_url' => add_query_arg(['page' => 'cbt-exams', 'cbt_exam_panel' => 'list'], admin_url('admin.php')),
                ];
                $editing_exam = null;
                $editing_id = 0;
                $blocked_bank_exam_error = 'Bank Soal dikelola dari menu CBT Questions, bukan dari CBT Exams.';
                $requested_exam_page_panel = 'list';
            }
        }

        $selected_subject_id = isset($query['subject_id']) ? absint(wp_unslash((string) $query['subject_id'])) : 0;
        if ($editing_exam && isset($editing_exam['subject_id'])) {
            $selected_subject_id = (int) $editing_exam['subject_id'];
        }
        if ($selected_subject_id <= 0 && !empty($subjects)) {
            $selected_subject_id = (int) $subjects[0]['id'];
        }

        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $requested_exam_page_panel = isset($query['cbt_exam_panel'])
            ? sanitize_key((string) wp_unslash((string) $query['cbt_exam_panel']))
            : '';
        if (!in_array($requested_exam_page_panel, ['builder', 'list', 'snapshot'], true)) {
            $requested_exam_page_panel = '';
        }
        if ($requested_exam_page_panel === 'snapshot' && !$can_manage_exam_snapshots) {
            $requested_exam_page_panel = 'list';
        }
        if ($blocked_bank_exam_error !== '') {
            $error = $blocked_bank_exam_error;
            $requested_exam_page_panel = 'list';
        }
        if ($editing_id > 0 && !$editing_exam) {
            $error = $error !== '' ? $error : 'Exam tidak ditemukan atau tidak bisa diakses.';
        }

        $question_type_labels = [
            'multiple_choice' => 'Multiple Choice',
            'multiple_answer' => 'Multiple Answer',
            'true_false' => 'True/False',
            'true_false_matrix' => 'True/False Matrix',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
        ];
        $builder_question_search = isset($query['cbt_exam_question_search'])
            ? sanitize_text_field(wp_unslash((string) $query['cbt_exam_question_search']))
            : '';
        $builder_question_type = isset($query['cbt_exam_question_type'])
            ? sanitize_key((string) wp_unslash((string) $query['cbt_exam_question_type']))
            : '';
        if ($builder_question_type !== '' && !isset($question_type_labels[$builder_question_type])) {
            $builder_question_type = '';
        }
        $builder_question_source = isset($query['cbt_exam_question_source']) ? absint(wp_unslash((string) $query['cbt_exam_question_source'])) : 0;
        $builder_question_per_page = isset($query['cbt_exam_question_per_page'])
            ? self::normalize_exam_builder_question_per_page(absint(wp_unslash((string) $query['cbt_exam_question_per_page'])))
            : 50;
        $builder_question_current_page = isset($query['cbt_exam_question_paged'])
            ? max(1, absint(wp_unslash((string) $query['cbt_exam_question_paged'])))
            : 1;
        $builder_question_panel_requested = isset($query['cbt_exam_question_panel']);
        $builder_question_mode = isset($query['cbt_exam_question_mode'])
            ? sanitize_key((string) wp_unslash((string) $query['cbt_exam_question_mode']))
            : ($editing_exam ? 'selected' : 'catalog');
        $saved_exam_id = isset($query['cbt_saved_exam_id']) ? absint(wp_unslash((string) $query['cbt_saved_exam_id'])) : 0;
        $builder_state_key = 'cbt_exam_builder_' . ($editing_id > 0 ? 'edit_' . $editing_id : 'new');
        $builder_state_reset_keys = [];
        if ($saved_exam_id > 0) {
            $builder_state_reset_keys[] = 'cbt_exam_builder_new';
            $builder_state_reset_keys[] = 'cbt_exam_builder_edit_' . $saved_exam_id;
        }
        $builder_question_mode = 'catalog';
        $should_load_question_catalog = (
            $builder_question_panel_requested
            || $builder_question_current_page > 1
            || $builder_question_search !== ''
            || $builder_question_type !== ''
            || $builder_question_source > 0
            || $builder_question_per_page !== 50
            || ($error !== '' && preg_match('/soal/i', $error))
        );

        $initial_selected_question_ids = [];
        if ($editing_exam) {
            $initial_selected_question_ids = array_map(
                'intval',
                (array) $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT COALESCE(source_question_id, id) FROM {$question_table} WHERE exam_id = %d AND COALESCE(is_active, 1) = 1 ORDER BY id ASC",
                        (int) $editing_exam['id']
                    )
                )
            );
        }
        $selected_question_ids = self::get_exam_builder_selected_question_ids($builder_state_key, $current_user_id, $initial_selected_question_ids);
        $catalog_excluded_question_ids = array_values(array_unique(array_filter(array_map('absint', $selected_question_ids))));
        $catalog_excluded_where_sql = '';
        $catalog_excluded_where_params = [];
        if (!empty($catalog_excluded_question_ids)) {
            $catalog_excluded_where_sql = 'q.id NOT IN (' . implode(',', array_fill(0, count($catalog_excluded_question_ids), '%d')) . ')';
            $catalog_excluded_where_params = $catalog_excluded_question_ids;
        }

        $source_exam_options = [];
        $source_options_map = [];
        $source_total_questions = 0;
        $source_question_total_pages = 1;
        $source_questions = [];
        $source_catalog_scope = 'bank';
        $source_filter_label = 'Bank Soal';
        $source_filter_all_label = 'Semua bank soal';
        $source_filter_help_text = 'Filter berdasarkan bank soal per mapel.';
        if ($should_load_question_catalog) {
            $source_active_clause = 'COALESCE(q.is_active, 1) = 1';
            $bank_source_where_parts = [
                $source_active_clause,
                $wpdb->prepare('e.title LIKE %s', 'Bank Soal - %'),
            ];
            $selected_source_where_parts = [
                $source_active_clause,
                '(q.source_question_id IS NULL OR q.source_question_id = 0)',
            ];
            if (!$is_admin_scope) {
                $created_by_clause = $wpdb->prepare('e.created_by = %d', $current_user_id);
                $bank_source_where_parts[] = $created_by_clause;
                $selected_source_where_parts[] = $created_by_clause;
            }
            $bank_source_where = implode(' AND ', $bank_source_where_parts);
            $selected_source_where = !empty($selected_source_where_parts) ? implode(' AND ', $selected_source_where_parts) : '1=1';
            $source_catalog_from_sql = "FROM {$question_table} q
                 INNER JOIN {$exam_table} e ON e.id = q.exam_id
                 LEFT JOIN {$subject_table} s ON s.id = e.subject_id";
            $bank_source_total = (int) $wpdb->get_var(
                "SELECT COUNT(*) {$source_catalog_from_sql} WHERE {$bank_source_where}"
            );
            $catalog_source_where = $bank_source_total > 0 ? $bank_source_where : $selected_source_where;
            if ($bank_source_total <= 0) {
                $source_catalog_scope = 'legacy';
                $source_filter_label = 'Exam Sumber';
                $source_filter_all_label = 'Semua exam sumber';
                $source_filter_help_text = 'Belum ada bank soal terpisah. Sumber saat ini memakai exam induk yang menyimpan soal asli.';
            }
            $catalog_query_where_parts = [$catalog_source_where];
            $catalog_query_params = [];
            if ($catalog_excluded_where_sql !== '') {
                $catalog_query_where_parts[] = $catalog_excluded_where_sql;
                $catalog_query_params = array_merge($catalog_query_params, $catalog_excluded_where_params);
            }
            $catalog_query_where = implode(' AND ', $catalog_query_where_parts);
            $source_exam_rows_sql = "SELECT DISTINCT e.id AS exam_id, e.title AS exam_title
                 {$source_catalog_from_sql}
                 WHERE {$catalog_query_where}
                 ORDER BY e.title ASC";
            $source_exam_rows = !empty($catalog_query_params)
                ? $wpdb->get_results($wpdb->prepare($source_exam_rows_sql, ...$catalog_query_params), ARRAY_A)
                : $wpdb->get_results($source_exam_rows_sql, ARRAY_A);
            foreach ((array) $source_exam_rows as $source_exam_row) {
                $source_exam_id = (int) ($source_exam_row['exam_id'] ?? 0);
                $source_exam_title = (string) ($source_exam_row['exam_title'] ?? '');
                if ($source_exam_id <= 0 || $source_exam_title === '') {
                    continue;
                }
                $source_exam_options[$source_exam_id] = $source_exam_title;
            }
            if ($builder_question_source > 0 && !isset($source_exam_options[$builder_question_source])) {
                $builder_question_source = 0;
            }

            $catalog_base_where = $catalog_query_where;
            $source_query_where_parts = [$catalog_base_where];
            $source_query_params = $catalog_query_params;
            if ($builder_question_search !== '') {
                $source_query_where_parts[] = 'q.question_text LIKE %s';
                $source_query_params[] = '%' . $wpdb->esc_like($builder_question_search) . '%';
            }
            if ($builder_question_type !== '') {
                $source_query_where_parts[] = 'q.question_type = %s';
                $source_query_params[] = $builder_question_type;
            }
            if ($builder_question_source > 0) {
                $source_query_where_parts[] = 'q.exam_id = %d';
                $source_query_params[] = $builder_question_source;
            }
            $source_query_where = implode(' AND ', $source_query_where_parts);
            $source_total_sql = "SELECT COUNT(*) {$source_catalog_from_sql} WHERE {$source_query_where}";
            $source_total_questions = !empty($source_query_params)
                ? (int) $wpdb->get_var($wpdb->prepare($source_total_sql, ...$source_query_params))
                : (int) $wpdb->get_var($source_total_sql);
            $source_question_total_pages = max(1, (int) ceil($source_total_questions / $builder_question_per_page));
            if ($source_total_questions > 0 && $builder_question_current_page > $source_question_total_pages) {
                $builder_question_current_page = $source_question_total_pages;
            }
            $source_question_offset = ($builder_question_current_page - 1) * $builder_question_per_page;
            $source_question_limit = (int) $builder_question_per_page;
            $source_question_offset = (int) $source_question_offset;
            $source_question_sql = "SELECT q.id, q.exam_id, q.question_text, q.question_type, q.points, e.title AS exam_title, e.subject_id, s.name AS subject_name
                 {$source_catalog_from_sql}
                 WHERE {$source_query_where}
                 ORDER BY s.name ASC, q.id DESC
                 LIMIT {$source_question_limit} OFFSET {$source_question_offset}";
            $source_questions = !empty($source_query_params)
                ? $wpdb->get_results($wpdb->prepare($source_question_sql, ...$source_query_params), ARRAY_A)
                : $wpdb->get_results($source_question_sql, ARRAY_A);
            $source_question_ids = array_values(array_unique(array_map('intval', wp_list_pluck($source_questions, 'id'))));
            if (!empty($source_question_ids)) {
                $placeholders = implode(',', array_fill(0, count($source_question_ids), '%d'));
                $source_option_rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT question_id, option_key, option_text, is_correct
                         FROM {$option_table}
                         WHERE question_id IN ({$placeholders})
                         ORDER BY id ASC",
                        ...$source_question_ids
                    ),
                    ARRAY_A
                );
                foreach ((array) $source_option_rows as $source_option_row) {
                    $question_id = (int) ($source_option_row['question_id'] ?? 0);
                    if ($question_id <= 0) {
                        continue;
                    }
                    if (!isset($source_options_map[$question_id])) {
                        $source_options_map[$question_id] = [];
                    }
                    $source_options_map[$question_id][] = $source_option_row;
                }
            }
        }

        $exam_list_base_where_parts = [
            $wpdb->prepare('e.title NOT LIKE %s', 'Bank Soal - %'),
        ];
        if (!$is_admin_scope) {
            $exam_list_base_where_parts[] = $wpdb->prepare('e.created_by = %d', $current_user_id);
        }
        $exam_list_base_where = ' WHERE ' . implode(' AND ', $exam_list_base_where_parts);
        $exam_target_kelas_rows = $wpdb->get_col("SELECT e.target_kelas FROM {$exam_table} e {$exam_list_base_where}");
        $exam_list_kelas_options = [];
        foreach ((array) $exam_target_kelas_rows as $exam_target_kelas_raw) {
            foreach (self::split_target_kelas_csv((string) $exam_target_kelas_raw) as $exam_target_kelas_value) {
                $exam_list_kelas_options[$exam_target_kelas_value] = $exam_target_kelas_value;
            }
        }
        if ($exam_list_state['kelas'] !== '') {
            $exam_list_kelas_options[$exam_list_state['kelas']] = $exam_list_state['kelas'];
        }
        $exam_list_kelas_options = array_values($exam_list_kelas_options);
        sort($exam_list_kelas_options, SORT_NATURAL | SORT_FLAG_CASE);

        $exam_list_where_parts = $exam_list_base_where_parts;
        $exam_list_where_params = [];
        if ($exam_list_state['search'] !== '') {
            $exam_search_like = '%' . $wpdb->esc_like($exam_list_state['search']) . '%';
            $exam_list_where_parts[] = '(e.title LIKE %s OR COALESCE(e.description, \'\') LIKE %s)';
            $exam_list_where_params[] = $exam_search_like;
            $exam_list_where_params[] = $exam_search_like;
        }
        if ($exam_list_state['status'] !== '') {
            $exam_list_where_parts[] = 'e.status = %s';
            $exam_list_where_params[] = $exam_list_state['status'];
        }
        if ($exam_list_state['subject_id'] > 0) {
            $exam_list_where_parts[] = 'e.subject_id = %d';
            $exam_list_where_params[] = $exam_list_state['subject_id'];
        }
        if ($exam_list_state['kelas'] !== '') {
            $exam_list_where_parts[] = "(COALESCE(NULLIF(TRIM(e.target_kelas), ''), '') = '' OR FIND_IN_SET(%s, REPLACE(REPLACE(REPLACE(UPPER(COALESCE(e.target_kelas, '')), ', ', ','), ';', ','), '|', ',')) > 0)";
            $exam_list_where_params[] = $exam_list_state['kelas'];
        }
        $exam_list_where = ' WHERE ' . implode(' AND ', $exam_list_where_parts);
        $exam_per_page = $exam_list_state['per_page'];
        $exam_current_page = $exam_list_state['paged'];
        $total_exams_sql = "SELECT COUNT(*) FROM {$exam_table} e {$exam_list_where}";
        $total_exams = !empty($exam_list_where_params)
            ? (int) $wpdb->get_var($wpdb->prepare($total_exams_sql, ...$exam_list_where_params))
            : (int) $wpdb->get_var($total_exams_sql);
        $exam_total_pages = max(1, (int) ceil($total_exams / $exam_per_page));
        if ($total_exams > 0 && $exam_current_page > $exam_total_pages) {
            $exam_current_page = $exam_total_pages;
        }
        $exam_list_state['paged'] = $exam_current_page;
        $exam_offset = ($exam_current_page - 1) * $exam_per_page;

        $exam_limit = (int) $exam_per_page;
        $exam_offset = (int) $exam_offset;
        $question_count_subquery = "SELECT exam_id, COUNT(*) AS question_count
             FROM {$question_table}
             WHERE COALESCE(is_active, 1) = 1
             GROUP BY exam_id";
        $attempt_count_subquery = "SELECT exam_id,
                    COUNT(*) AS attempt_total,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS attempt_in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS attempt_completed
             FROM {$attempt_table}
             GROUP BY exam_id";
        $exam_query_sql = "SELECT e.*,
                    s.name AS subject_name,
                    COALESCE(qc.question_count, 0) AS question_count,
                    COALESCE(ac.attempt_total, 0) AS attempt_total,
                    COALESCE(ac.attempt_in_progress, 0) AS attempt_in_progress,
                    COALESCE(ac.attempt_completed, 0) AS attempt_completed
             FROM {$exam_table} e
             LEFT JOIN {$subject_table} s ON s.id = e.subject_id
             LEFT JOIN ({$question_count_subquery}) qc ON qc.exam_id = e.id
             LEFT JOIN ({$attempt_count_subquery}) ac ON ac.exam_id = e.id
             {$exam_list_where}
             ORDER BY e.id DESC
             LIMIT {$exam_limit} OFFSET {$exam_offset}";
        $exams = !empty($exam_list_where_params)
            ? $wpdb->get_results($wpdb->prepare($exam_query_sql, ...$exam_list_where_params), ARRAY_A)
            : $wpdb->get_results($exam_query_sql, ARRAY_A);
        $exam_status_totals = [
            'draft' => 0,
            'published' => 0,
            'closed' => 0,
        ];
        $exam_status_rows_sql = "SELECT e.status, COUNT(*) AS total
             FROM {$exam_table} e
             {$exam_list_where}
             GROUP BY e.status";
        $exam_status_rows = !empty($exam_list_where_params)
            ? $wpdb->get_results($wpdb->prepare($exam_status_rows_sql, ...$exam_list_where_params), ARRAY_A)
            : $wpdb->get_results($exam_status_rows_sql, ARRAY_A);
        foreach ((array) $exam_status_rows as $exam_status_row) {
            $status_key = sanitize_key((string) ($exam_status_row['status'] ?? ''));
            if (!array_key_exists($status_key, $exam_status_totals)) {
                continue;
            }
            $exam_status_totals[$status_key] = (int) ($exam_status_row['total'] ?? 0);
        }
        $exam_list_base_args = self::add_exam_list_state_args(
            [
                'page' => 'cbt-exams',
                'cbt_exam_panel' => 'list',
            ],
            $exam_list_state,
            false
        );
        $exam_list_state_args = self::add_exam_list_state_args(
            [
                'page' => 'cbt-exams',
                'cbt_exam_panel' => 'list',
            ],
            $exam_list_state
        );

        $kelas_options = self::get_distinct_user_meta_values('kode_kelas');
        $editing_target_kelas_values = [];
        if ($editing_exam && isset($editing_exam['target_kelas'])) {
            $editing_target_kelas_values = self::split_target_kelas_csv((string) $editing_exam['target_kelas']);
        }
        foreach ($editing_target_kelas_values as $kelas_value) {
            if (!in_array($kelas_value, $kelas_options, true)) {
                $kelas_options[] = $kelas_value;
            }
        }
        sort($kelas_options, SORT_NATURAL | SORT_FLAG_CASE);
        $selected_subject_label = 'Belum dipilih';
        foreach ((array) $subjects as $subject) {
            $subject_id = (int) ($subject['id'] ?? 0);
            if ($subject_id !== $selected_subject_id) {
                continue;
            }
            $subject_name = (string) ($subject['name'] ?? '');
            $subject_code = trim((string) ($subject['code'] ?? ''));
            $selected_subject_label = $subject_name !== ''
                ? $subject_name . ($subject_code !== '' ? ' (' . $subject_code . ')' : '')
                : 'Belum dipilih';
            break;
        }
        $exam_filter_subject_label = '';
        if ($exam_list_state['subject_id'] > 0) {
            foreach ((array) $subjects as $subject) {
                $subject_id = (int) ($subject['id'] ?? 0);
                if ($subject_id !== $exam_list_state['subject_id']) {
                    continue;
                }
                $subject_name = (string) ($subject['name'] ?? '');
                $subject_code = trim((string) ($subject['code'] ?? ''));
                $exam_filter_subject_label = $subject_name !== ''
                    ? $subject_name . ($subject_code !== '' ? ' (' . $subject_code . ')' : '')
                    : '';
                break;
            }
        }
        $exam_active_filters = [];
        if ($exam_list_state['search'] !== '') {
            $exam_active_filters[] = [
                'label' => 'Cari',
                'value' => $exam_list_state['search'],
            ];
        }
        if ($exam_filter_subject_label !== '') {
            $exam_active_filters[] = [
                'label' => 'Mapel',
                'value' => $exam_filter_subject_label,
            ];
        }
        if ($exam_list_state['status'] !== '' && isset($exam_status_labels[$exam_list_state['status']])) {
            $exam_active_filters[] = [
                'label' => 'Status',
                'value' => $exam_status_labels[$exam_list_state['status']],
            ];
        }
        if ($exam_list_state['kelas'] !== '') {
            $exam_active_filters[] = [
                'label' => 'Kelas',
                'value' => $exam_list_state['kelas'],
            ];
        }
        $exam_list_reset_url = add_query_arg(
            [
                'page' => 'cbt-exams',
                'cbt_exam_panel' => 'list',
                'cbt_exam_per_page' => $exam_per_page,
            ],
            admin_url('admin.php')
        );
        $exam_snapshot_reset_tab = self::is_exam_snapshot_exam_tab($exam_snapshot_tab)
            ? $exam_snapshot_tab
            : self::SNAPSHOT_TAB_PREFLIGHT;
        $exam_snapshot_reset_url = add_query_arg(
            self::add_exam_snapshot_tab_args(
                [
                    'page' => 'cbt-exams',
                    'cbt_exam_panel' => 'snapshot',
                    'cbt_exam_per_page' => $exam_per_page,
                ],
                $exam_snapshot_reset_tab
            ),
            admin_url('admin.php')
        );
        $student_snapshot_reset_tab = self::is_exam_snapshot_student_tab($exam_snapshot_tab)
            ? $exam_snapshot_tab
            : self::SNAPSHOT_TAB_EXAM_MONITOR;
        $student_snapshot_reset_url = add_query_arg(
            self::add_exam_readiness_page_args(
                self::add_exam_snapshot_tab_args(
                    self::add_exam_snapshot_preview_page_args(
                        self::add_exam_snapshot_filter_state_args(
                            self::add_exam_list_state_args(
                                [
                                    'page' => 'cbt-exams',
                                    'cbt_exam_panel' => 'snapshot',
                                ],
                                $exam_list_state,
                                false
                            ),
                            $exam_snapshot_filter_state
                        ),
                        $exam_snapshot_preview_pages
                    ),
                    $student_snapshot_reset_tab
                ),
                $exam_readiness_page
            ),
            admin_url('admin.php')
        );
        $exam_snapshot_exam_options = $can_manage_exam_snapshots
            ? self::build_exam_snapshot_exam_options($is_admin_scope, $current_user_id)
            : [];
        $valid_snapshot_exam_ids = array_map('intval', wp_list_pluck((array) $exam_snapshot_exam_options, 'id'));
        $selected_snapshot_exam_ids = array_values(array_filter(array_map('intval', (array) ($exam_snapshot_filter_state['exam_ids'] ?? []))));
        if (!empty($selected_snapshot_exam_ids)) {
            $selected_snapshot_exam_ids = array_values(array_intersect($selected_snapshot_exam_ids, $valid_snapshot_exam_ids));
        }
        $selected_snapshot_exam_ids = self::normalize_snapshot_exam_selection_for_tab($selected_snapshot_exam_ids, $exam_snapshot_tab);
        $exam_snapshot_filter_state['exam_ids'] = $selected_snapshot_exam_ids;
        $exam_snapshot_filter_state['exam_id'] = !empty($selected_snapshot_exam_ids) ? (int) $selected_snapshot_exam_ids[0] : 0;
        $exam_readiness_pages = self::get_exam_readiness_pages_from_request($query, $selected_snapshot_exam_ids);
        $is_bulk_preflight_mode = $can_manage_exam_snapshots
            && $exam_snapshot_tab === self::SNAPSHOT_TAB_PREFLIGHT
            && count($selected_snapshot_exam_ids) > 1;
        $bulk_preflight = $is_bulk_preflight_mode
            ? self::build_bulk_preflight_context($is_admin_scope, $current_user_id, $selected_snapshot_exam_ids)
            : [];
        $exam_snapshot_rows = ($can_manage_exam_snapshots && !$is_bulk_preflight_mode)
            ? self::build_filtered_exam_snapshot_rows(
                $is_admin_scope,
                $current_user_id,
                $exam_snapshot_preview_pages,
                $selected_snapshot_exam_ids,
                $exam_readiness_pages,
                $exam_readiness_page,
                $exam_snapshot_tab,
                $student_snapshot_filter_state
            )
            : [];
        $exam_snapshot_total = $is_bulk_preflight_mode
            ? count((array) ($bulk_preflight['rows'] ?? []))
            : count($exam_snapshot_rows);
        foreach ($exam_snapshot_rows as $snapshot_row) {
            $snapshot_exam_id = (int) ($snapshot_row['exam_id'] ?? 0);
            $snapshot_problem_page = max(1, (int) ($snapshot_row['readiness']['problem_page'] ?? 1));
            if ($snapshot_exam_id <= 0) {
                continue;
            }

            if ($snapshot_problem_page > 1) {
                $exam_readiness_pages[$snapshot_exam_id] = $snapshot_problem_page;
            } else {
                unset($exam_readiness_pages[$snapshot_exam_id]);
            }
        }
        if (!empty($selected_snapshot_exam_ids)) {
            $primary_snapshot_exam_id = (int) $selected_snapshot_exam_ids[0];
            if ($primary_snapshot_exam_id > 0 && !empty($exam_readiness_pages[$primary_snapshot_exam_id])) {
                $exam_readiness_page = max(1, (int) $exam_readiness_pages[$primary_snapshot_exam_id]);
            } elseif (!empty($exam_snapshot_rows[0]['readiness']['problem_page'])) {
                $exam_readiness_page = max(1, (int) $exam_snapshot_rows[0]['readiness']['problem_page']);
            }
        }
        $exam_snapshot_reset_url = add_query_arg(
            self::add_exam_readiness_page_args(
                self::add_exam_snapshot_tab_args(
                    [
                        'page' => 'cbt-exams',
                        'cbt_exam_panel' => 'snapshot',
                        'cbt_exam_per_page' => $exam_per_page,
                    ],
                    $exam_snapshot_reset_tab
                ),
                $exam_readiness_pages,
                $exam_readiness_page
            ),
            admin_url('admin.php')
        );
        $student_snapshot_reset_url = add_query_arg(
            self::add_exam_readiness_page_args(
                self::add_exam_snapshot_tab_args(
                    self::add_exam_snapshot_preview_page_args(
                        self::add_exam_snapshot_filter_state_args(
                            self::add_exam_list_state_args(
                                [
                                    'page' => 'cbt-exams',
                                    'cbt_exam_panel' => 'snapshot',
                                ],
                                $exam_list_state,
                                false
                            ),
                            $exam_snapshot_filter_state
                        ),
                        $exam_snapshot_preview_pages
                    ),
                    $student_snapshot_reset_tab
                ),
                $exam_readiness_pages,
                $exam_readiness_page
            ),
            admin_url('admin.php')
        );
        $exam_snapshot_active_filters = [];
        if (!empty($selected_snapshot_exam_ids)) {
            $selected_snapshot_exam_titles = [];
            foreach ($exam_snapshot_exam_options as $snapshot_exam_option) {
                $snapshot_exam_option_id = (int) ($snapshot_exam_option['id'] ?? 0);
                if (!in_array($snapshot_exam_option_id, $selected_snapshot_exam_ids, true)) {
                    continue;
                }

                $selected_snapshot_exam_titles[] = (string) ($snapshot_exam_option['title'] ?? ('Exam #' . $snapshot_exam_option_id));
            }

            $exam_snapshot_active_filters[] = [
                'label' => 'Exam',
                'value' => count($selected_snapshot_exam_titles) <= 1
                    ? (string) ($selected_snapshot_exam_titles[0] ?? ('Exam #' . (int) $exam_snapshot_filter_state['exam_id']))
                    : (count($selected_snapshot_exam_titles) . ' exam dipilih'),
            ];
        }
        $student_snapshot_filter_options = ['kelas' => [], 'ruang' => []];
        if ($can_manage_exam_snapshots) {
            if (self::is_exam_snapshot_student_tab($exam_snapshot_tab)) {
                $student_snapshot_filter_options = self::build_snapshot_student_filter_options();
            } elseif ($exam_snapshot_tab === self::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR) {
                $student_snapshot_filter_options = self::build_session_runtime_filter_options($selected_snapshot_exam_ids);
            }
        }
        $student_snapshot_kelas_options = array_values(array_map('strval', (array) ($student_snapshot_filter_options['kelas'] ?? [])));
        $student_snapshot_ruang_options = array_values(array_map('strval', (array) ($student_snapshot_filter_options['ruang'] ?? [])));
        $student_snapshot_status_options = self::get_student_snapshot_status_filter_options($exam_snapshot_tab);
        $valid_student_snapshot_kelas_options = $student_snapshot_kelas_options;
        $valid_student_snapshot_ruang_options = $student_snapshot_ruang_options;
        $valid_student_snapshot_status_options = array_values(array_filter(array_map('strval', wp_list_pluck($student_snapshot_status_options, 'value'))));
        if (($student_snapshot_filter_state['kelas'] ?? '') !== '' && !in_array((string) $student_snapshot_filter_state['kelas'], $valid_student_snapshot_kelas_options, true)) {
            $student_snapshot_filter_state['kelas'] = '';
        }
        if (($student_snapshot_filter_state['ruang'] ?? '') !== '' && !in_array((string) $student_snapshot_filter_state['ruang'], $valid_student_snapshot_ruang_options, true)) {
            $student_snapshot_filter_state['ruang'] = '';
        }
        if (($student_snapshot_filter_state['status'] ?? '') !== '' && !in_array((string) $student_snapshot_filter_state['status'], $valid_student_snapshot_status_options, true)) {
            $student_snapshot_filter_state['status'] = '';
        }
        $student_snapshot_table = ($can_manage_exam_snapshots && self::is_exam_snapshot_student_tab($exam_snapshot_tab))
            ? self::build_snapshot_student_table_context($student_snapshot_filter_state, $exam_snapshot_tab)
            : [
                'items' => [],
                'total' => 0,
                'total_pages' => 1,
                'page' => 1,
                'per_page' => self::STUDENT_SNAPSHOT_PER_PAGE,
            ];
        $availability_rewarm_queue = $can_manage_exam_snapshots && class_exists('CBT_Exam_Availability_Auto_Warm_Service')
            ? CBT_Exam_Availability_Auto_Warm_Service::get_rewarm_queue_state()
            : [];
        $student_snapshot_rows = (array) ($student_snapshot_table['items'] ?? []);
        $student_snapshot_total = max(0, (int) ($student_snapshot_table['total'] ?? 0));
        $student_snapshot_total_pages = max(1, (int) ($student_snapshot_table['total_pages'] ?? 1));
        $student_snapshot_current_page = max(1, (int) ($student_snapshot_table['page'] ?? 1));
        $student_snapshot_per_page = max(1, (int) ($student_snapshot_table['per_page'] ?? self::STUDENT_SNAPSHOT_PER_PAGE));
        $student_snapshot_active_filters = [];
        if (($student_snapshot_filter_state['search'] ?? '') !== '') {
            $student_snapshot_active_filters[] = [
                'label' => 'Cari Siswa',
                'value' => (string) $student_snapshot_filter_state['search'],
            ];
        }
        if (($student_snapshot_filter_state['kelas'] ?? '') !== '') {
            $student_snapshot_active_filters[] = [
                'label' => 'Kelas',
                'value' => (string) $student_snapshot_filter_state['kelas'],
            ];
        }
        if (($student_snapshot_filter_state['ruang'] ?? '') !== '') {
            $student_snapshot_active_filters[] = [
                'label' => 'Ruang',
                'value' => (string) $student_snapshot_filter_state['ruang'],
            ];
        }
        if (($student_snapshot_filter_state['status'] ?? '') !== '') {
            $status_filter_value = (string) $student_snapshot_filter_state['status'];
            $status_filter_label = $status_filter_value;
            foreach ($student_snapshot_status_options as $status_option) {
                if ((string) ($status_option['value'] ?? '') !== $status_filter_value) {
                    continue;
                }

                $status_filter_label = (string) ($status_option['label'] ?? $status_filter_value);
                break;
            }

            $student_snapshot_active_filters[] = [
                'label' => 'Status Snapshot',
                'value' => $status_filter_label,
            ];
        }
        $selected_question_total = count($selected_question_ids);
        $editing_exam_lineage_context = $editing_exam
            ? self::build_exam_lineage_context((int) ($editing_exam['id'] ?? 0))
            : [
                'topology_slug' => 'empty',
                'topology_label' => 'Belum Ada Soal',
                'topology_class' => 'empty',
                'topology_is_mixed' => false,
                'total_active_questions' => 0,
                'bank_backed_question_count' => 0,
                'legacy_question_count' => 0,
                'linked_legacy_count' => 0,
                'source_meta_map' => [],
            ];
        $selected_lineage_bank_backed_count = (int) ($editing_exam_lineage_context['bank_backed_question_count'] ?? 0);
        $selected_lineage_legacy_count = (int) ($editing_exam_lineage_context['legacy_question_count'] ?? 0);
        $selected_lineage_linked_legacy_count = (int) ($editing_exam_lineage_context['linked_legacy_count'] ?? 0);
        $selected_lineage_total_active = (int) ($editing_exam_lineage_context['total_active_questions'] ?? 0);
        $selected_lineage_topology_label = (string) ($editing_exam_lineage_context['topology_label'] ?? 'Belum Ada Soal');
        $selected_lineage_topology_class = (string) ($editing_exam_lineage_context['topology_class'] ?? 'empty');
        $selected_lineage_is_mixed = !empty($editing_exam_lineage_context['topology_is_mixed']);
        $builder_source_context_label = $source_catalog_scope === 'bank' ? 'Bank Soal Aktif' : 'Fallback Legacy';
        $builder_source_context_value = $source_catalog_scope === 'bank'
            ? 'Katalog soal saat ini mengambil root question dari Bank Soal.'
            : 'Katalog saat ini memakai exam sumber lama karena Bank Soal terpisah belum tersedia.';
        $selected_lineage_summary_items = [];
        if ($editing_exam) {
            $selected_lineage_summary_items[] = [
                'label' => 'Topology',
                'value' => $selected_lineage_topology_label,
                'class' => 'topology-' . sanitize_html_class($selected_lineage_topology_class),
            ];
            $selected_lineage_summary_items[] = [
                'label' => 'Bank-backed',
                'value' => (string) $selected_lineage_bank_backed_count,
                'class' => 'bank',
            ];
            $selected_lineage_summary_items[] = [
                'label' => 'Legacy',
                'value' => (string) $selected_lineage_legacy_count,
                'class' => 'legacy',
            ];
            if ($selected_lineage_linked_legacy_count > 0) {
                $selected_lineage_summary_items[] = [
                    'label' => 'Linked Legacy',
                    'value' => (string) $selected_lineage_linked_legacy_count,
                    'class' => 'linked',
                ];
            }
        }
        $initial_target_kelas_count = count($editing_target_kelas_values);
        $initial_schedule_summary = 'Belum diatur';
        $summary_starts_at = trim((string) ($editing_exam['starts_at'] ?? ''));
        $summary_ends_at = trim((string) ($editing_exam['ends_at'] ?? ''));
        $summary_starts_at_ts = $summary_starts_at !== '' ? strtotime($summary_starts_at) : false;
        $summary_ends_at_ts = $summary_ends_at !== '' ? strtotime($summary_ends_at) : false;
        if ($summary_starts_at_ts !== false && $summary_ends_at_ts !== false) {
            $initial_schedule_summary = wp_date('d M H:i', $summary_starts_at_ts) . ' -> ' . wp_date('d M H:i', $summary_ends_at_ts);
        } elseif ($summary_starts_at_ts !== false) {
            $initial_schedule_summary = 'Mulai ' . wp_date('d M H:i', $summary_starts_at_ts);
        } elseif ($summary_ends_at_ts !== false) {
            $initial_schedule_summary = 'Selesai ' . wp_date('d M H:i', $summary_ends_at_ts);
        }
        $has_builder_question_navigation = $builder_question_panel_requested
            || $builder_question_current_page > 1
            || $builder_question_search !== ''
            || $builder_question_type !== ''
            || $builder_question_source > 0
            || $builder_question_per_page !== 50;
        $active_exam_page_panel = 'cbt-exam-builder-panel';
        if ($requested_exam_page_panel === 'list') {
            $active_exam_page_panel = 'cbt-exam-list-panel';
        } elseif ($requested_exam_page_panel === 'snapshot' && $can_manage_exam_snapshots) {
            $active_exam_page_panel = 'cbt-exam-snapshot-panel';
        }
        if (
            $requested_exam_page_panel === ''
            && $notice !== ''
            && !$editing_exam
            && $error === ''
            && !empty($exams)
            && !$has_builder_question_navigation
        ) {
            $active_exam_page_panel = 'cbt-exam-list-panel';
        }
        $active_exam_builder_panel = $has_builder_question_navigation ? 'cbt-exam-questions-panel' : 'cbt-exam-details-panel';
        if ($error !== '' && preg_match('/soal/i', $error)) {
            $active_exam_builder_panel = 'cbt-exam-questions-panel';
        }
        $builder_question_query_args = [
            'page' => 'cbt-exams',
            'cbt_exam_per_page' => $exam_per_page,
            'cbt_exam_paged' => $exam_current_page,
            'cbt_exam_question_panel' => 1,
            'cbt_exam_question_per_page' => $builder_question_per_page,
        ];
        if ($editing_id > 0) {
            $builder_question_query_args['edit'] = $editing_id;
        } elseif ($selected_subject_id > 0) {
            $builder_question_query_args['subject_id'] = $selected_subject_id;
        }
        if ($builder_question_search !== '') {
            $builder_question_query_args['cbt_exam_question_search'] = $builder_question_search;
        }
        if ($builder_question_type !== '') {
            $builder_question_query_args['cbt_exam_question_type'] = $builder_question_type;
        }
        if ($builder_question_source > 0) {
            $builder_question_query_args['cbt_exam_question_source'] = $builder_question_source;
        }
        $source_question_pagination_links = [];
        if ($should_load_question_catalog && $source_question_total_pages > 1) {
            $source_question_pagination_links = paginate_links([
                'base' => add_query_arg(
                    array_merge($builder_question_query_args, ['cbt_exam_question_paged' => '%#%']),
                    admin_url('admin.php')
                ),
                'format' => '',
                'current' => $builder_question_current_page,
                'total' => $source_question_total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type' => 'array',
                'end_size' => 1,
                'mid_size' => 1,
            ]);
        }
        $load_question_catalog_url = add_query_arg(
            array_merge(
                $builder_question_query_args,
                [
                    'cbt_exam_question_panel' => 1,
                    'cbt_exam_question_paged' => 1,
                ]
            ),
            admin_url('admin.php')
        );
        $reset_question_catalog_args = $builder_question_query_args;
        unset(
            $reset_question_catalog_args['cbt_exam_question_search'],
            $reset_question_catalog_args['cbt_exam_question_type'],
            $reset_question_catalog_args['cbt_exam_question_source'],
            $reset_question_catalog_args['cbt_exam_question_paged'],
            $reset_question_catalog_args['cbt_exam_question_per_page']
        );
        $reset_question_catalog_url = add_query_arg($reset_question_catalog_args, admin_url('admin.php'));

        $selected_source_meta_map = isset($editing_exam_lineage_context['source_meta_map']) && is_array($editing_exam_lineage_context['source_meta_map'])
            ? (array) $editing_exam_lineage_context['source_meta_map']
            : [];
        if (!empty($source_questions)) {
            $source_questions = self::enrich_source_question_rows($source_questions, $selected_source_meta_map);
        }

        $selected_sidebar_question_ids = array_values(array_unique(array_merge($initial_selected_question_ids, $selected_question_ids)));
        $selected_sidebar_questions = self::build_selected_source_question_rows($selected_sidebar_question_ids);
        if (!empty($selected_sidebar_questions)) {
            $selected_sidebar_questions = self::enrich_source_question_rows($selected_sidebar_questions, $selected_source_meta_map);
            foreach ($selected_sidebar_questions as &$selected_sidebar_question) {
                $sidebar_question_id = (int) ($selected_sidebar_question['id'] ?? 0);
                $sidebar_question_type = (string) ($selected_sidebar_question['question_type'] ?? '');
                $selected_sidebar_question['question_type_label'] = (string) ($question_type_labels[$sidebar_question_type] ?? $sidebar_question_type);
                $selected_sidebar_question['question_preview'] = wp_trim_words(
                    (string) wp_strip_all_tags((string) ($selected_sidebar_question['question_text'] ?? '')),
                    16
                );
                $selected_sidebar_question['edit_url'] = $sidebar_question_id > 0
                    ? add_query_arg(
                        [
                            'page' => 'cbt-question-bank',
                            'edit' => $sidebar_question_id,
                        ],
                        admin_url('admin.php')
                    )
                    : '';
            }
            unset($selected_sidebar_question);
        }
        $selected_sidebar_question_map = [];
        foreach ((array) $selected_sidebar_questions as $selected_sidebar_question) {
            $sidebar_question_id = (int) ($selected_sidebar_question['id'] ?? 0);
            if ($sidebar_question_id <= 0) {
                continue;
            }
            $selected_sidebar_question_map[$sidebar_question_id] = [
                'id' => $sidebar_question_id,
                'exam_id' => (int) ($selected_sidebar_question['exam_id'] ?? 0),
                'exam_title' => (string) ($selected_sidebar_question['exam_title'] ?? ''),
                'edit_url' => add_query_arg(
                    [
                        'page' => 'cbt-question-bank',
                        'edit' => $sidebar_question_id,
                    ],
                    admin_url('admin.php')
                ),
                'subject_name' => (string) ($selected_sidebar_question['subject_name'] ?? ''),
                'question_type' => (string) ($selected_sidebar_question['question_type'] ?? ''),
                'question_type_label' => (string) ($selected_sidebar_question['question_type_label'] ?? ''),
                'question_preview' => (string) ($selected_sidebar_question['question_preview'] ?? ''),
                'points' => (string) ($selected_sidebar_question['points'] ?? '1'),
                'lineage_label' => (string) ($selected_sidebar_question['lineage_label'] ?? 'Source'),
                'lineage_class' => (string) ($selected_sidebar_question['lineage_class'] ?? 'default'),
                'source_context_label' => (string) ($selected_sidebar_question['source_context_label'] ?? ''),
                'source_context_display' => (string) ($selected_sidebar_question['source_context_display'] ?? ''),
                'lineage_hint' => (string) ($selected_sidebar_question['lineage_hint'] ?? ''),
            ];
        }
        $selected_sidebar_preview_map = [];
        if (!empty($selected_sidebar_questions)) {
            $selected_sidebar_options_map = self::build_question_options_map(array_keys($selected_sidebar_question_map));
            $selected_sidebar_preview_map = self::build_question_preview_html_map($selected_sidebar_questions, $selected_sidebar_options_map);
        }

        if (!empty($exams)) {
            $exam_topology_map = self::build_exam_topology_map(array_map('intval', wp_list_pluck((array) $exams, 'id')));
            foreach ($exams as &$exam) {
                $exam_id = (int) ($exam['id'] ?? 0);
                $topology = $exam_topology_map[$exam_id] ?? [];
                if (!empty($topology)) {
                    $exam = array_merge($exam, $topology);
                }
            }
            unset($exam);
        }
        $exam_operational_stats = $can_manage_exam_snapshots
            ? self::build_exam_operational_stats_context($is_admin_scope, $current_user_id)
            : [];

        return get_defined_vars();
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_exam_operational_stats_context(bool $is_admin_scope, int $current_user_id): array
    {
        $transient_key = 'cbt_exam_operational_stats_' . ($is_admin_scope ? 'global' : ('user_' . $current_user_id));
        $cached = get_transient($transient_key);
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $where_parts = [
            $wpdb->prepare('e.title NOT LIKE %s', 'Bank Soal - %'),
        ];
        $where_params = [];
        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $where_params[] = $current_user_id;
        }

        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);
        $exam_ids_sql = "SELECT e.id FROM {$exam_table} e {$where_sql} ORDER BY e.id DESC";
        $prepared_exam_ids_sql = !empty($where_params) ? $wpdb->prepare($exam_ids_sql, ...$where_params) : $exam_ids_sql;
        $exam_ids = array_values(array_filter(array_map('intval', (array) $wpdb->get_col($prepared_exam_ids_sql))));

        $attempt_where_parts = $where_parts;
        $attempt_where_parts[] = "a.status = 'in_progress'";
        $attempt_where_sql = ' WHERE ' . implode(' AND ', $attempt_where_parts);
        $active_attempt_sql = "SELECT COUNT(*) FROM {$attempt_table} a INNER JOIN {$exam_table} e ON e.id = a.exam_id {$attempt_where_sql}";
        $prepared_active_attempt_sql = !empty($where_params) ? $wpdb->prepare($active_attempt_sql, ...$where_params) : $active_attempt_sql;
        $active_attempt_count = (int) $wpdb->get_var($prepared_active_attempt_sql);

        $redis_diagnostics = class_exists('CBT_Plugin_Redis_Reset_Service')
            ? CBT_Plugin_Redis_Reset_Service::get_plugin_diagnostics()
            : [
                'redis_available' => false,
                'memory_used_human' => '-',
                'memory_peak_human' => '-',
                'connected_clients' => 0,
                'total_keys' => 0,
                'database_summaries' => [],
                'prefix_counts' => [],
            ];
        $gate_diagnostics = class_exists('CBT_Start_Attempt_Gate_Service')
            ? CBT_Start_Attempt_Gate_Service::get_global_diagnostics($exam_ids)
            : [
                'status_label' => 'DISABLED',
                'status_tone' => 'warning',
                'queue_depth_total' => 0,
                'release_rate_label' => '-',
                'oldest_wait_seconds' => 0,
            ];
        $preflight_jobs = class_exists('CBT_Exam_Preflight_Service')
            ? CBT_Exam_Preflight_Service::get_jobs_state()
            : [];
        $preflight_runner = class_exists('CBT_Exam_Preflight_Service')
            ? CBT_Exam_Preflight_Service::get_global_runner_state()
            : ['active_exam_id' => 0, 'queue_exam_ids' => []];
        $auto_warm_state = class_exists('CBT_Exam_Availability_Auto_Warm_Service')
            ? CBT_Exam_Availability_Auto_Warm_Service::get_state()
            : ['active' => false, 'exam_id' => 0];

        $tracked_exam_lookup = [];
        foreach ($exam_ids as $exam_id) {
            $tracked_exam_lookup[$exam_id] = true;
        }

        $preflight_active_count = 0;
        $preflight_queued_count = 0;
        foreach ((array) $preflight_jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $job_exam_id = (int) ($job['exam_id'] ?? 0);
            if (!empty($tracked_exam_lookup) && $job_exam_id > 0 && !isset($tracked_exam_lookup[$job_exam_id])) {
                continue;
            }

            $job_status = sanitize_key((string) ($job['status'] ?? 'inactive'));
            if ($job_status === 'active') {
                $preflight_active_count++;
            } elseif ($job_status === 'queued') {
                $preflight_queued_count++;
            }
        }

        $auto_warm_active = !empty($auto_warm_state['active'])
            && (
                empty($tracked_exam_lookup)
                || isset($tracked_exam_lookup[(int) ($auto_warm_state['exam_id'] ?? 0)])
            );

        $prefix_counts = is_array($redis_diagnostics['prefix_counts'] ?? null) ? $redis_diagnostics['prefix_counts'] : [];
        $database_summaries = array_values(array_filter((array) ($redis_diagnostics['database_summaries'] ?? []), static function ($summary): bool {
            return is_array($summary);
        }));
        $database_summary_bits = [];
        foreach (array_slice($database_summaries, 0, 2) as $summary) {
            $database_summary_bits[] = 'DB' . (int) ($summary['database'] ?? 0) . ' ' . number_format_i18n((int) ($summary['key_count'] ?? 0));
        }
        if (count($database_summaries) > 2) {
            $database_summary_bits[] = '+' . (count($database_summaries) - 2) . ' DB';
        }

        $session_snapshot_count = max(0, (int) ($prefix_counts['attempt_session'] ?? 0));
        $contract_snapshot_count = max(0, (int) ($prefix_counts['attempt_contract'] ?? 0));
        $active_attempt_index_count = max(0, (int) ($prefix_counts['active_attempt'] ?? 0));
        $profile_snapshot_count = max(0, (int) ($prefix_counts['profile'] ?? 0));
        $login_snapshot_count = max(0, (int) ($prefix_counts['login'] ?? 0));
        $availability_snapshot_count = max(0, (int) ($prefix_counts['exam_availability'] ?? 0));

        $cards = [
            [
                'label' => 'Redis RAM',
                'value' => (string) ($redis_diagnostics['memory_used_human'] ?? '-'),
                'meta' => trim(implode(' · ', array_filter([
                    (($redis_diagnostics['memory_peak_human'] ?? '-') !== '-') ? ('Peak ' . (string) $redis_diagnostics['memory_peak_human']) : '',
                    ((int) ($redis_diagnostics['connected_clients'] ?? 0) > 0) ? ('Clients ' . number_format_i18n((int) $redis_diagnostics['connected_clients'])) : '',
                ]))),
                'hint' => 'Global Redis instance',
                'tone' => !empty($redis_diagnostics['redis_available']) ? 'success' : 'warning',
            ],
            [
                'label' => 'CBT Redis Keys',
                'value' => number_format_i18n((int) ($redis_diagnostics['total_keys'] ?? 0)),
                'meta' => !empty($database_summary_bits) ? implode(' · ', $database_summary_bits) : 'Belum ada key CBT',
                'hint' => 'Plugin only',
                'tone' => 'neutral',
            ],
            [
                'label' => 'Active Attempts',
                'value' => number_format_i18n($active_attempt_count),
                'meta' => trim(implode(' · ', array_filter([
                    $session_snapshot_count > 0 ? ('Session ' . number_format_i18n($session_snapshot_count)) : '',
                    $contract_snapshot_count > 0 ? ('Contract ' . number_format_i18n($contract_snapshot_count)) : '',
                    $active_attempt_index_count > 0 ? ('Index ' . number_format_i18n($active_attempt_index_count)) : '',
                ]))),
                'hint' => 'Runtime live',
                'tone' => $active_attempt_count > 0 ? 'success' : 'neutral',
            ],
            [
                'label' => 'Start Queue',
                'value' => number_format_i18n((int) ($gate_diagnostics['queue_depth_total'] ?? 0)),
                'meta' => trim(implode(' · ', array_filter([
                    (string) ($gate_diagnostics['status_label'] ?? 'DISABLED'),
                    (string) ($gate_diagnostics['release_rate_label'] ?? '-'),
                    ((int) ($gate_diagnostics['oldest_wait_seconds'] ?? 0) > 0) ? ('Oldest ' . number_format_i18n((int) $gate_diagnostics['oldest_wait_seconds']) . 's') : '',
                ]))),
                'hint' => 'Per exam gate',
                'tone' => sanitize_key((string) ($gate_diagnostics['status_tone'] ?? 'warning')),
            ],
            [
                'label' => 'Warm Jobs',
                'value' => number_format_i18n($preflight_active_count + $preflight_queued_count + ($auto_warm_active ? 1 : 0)),
                'meta' => trim(implode(' · ', array_filter([
                    'Preflight ' . number_format_i18n($preflight_active_count) . ' aktif',
                    $preflight_queued_count > 0 ? ('Antrean ' . number_format_i18n($preflight_queued_count)) : '',
                    $auto_warm_active ? 'Auto-Warm aktif' : 'Auto-Warm idle',
                ]))),
                'hint' => 'Pra-ujian',
                'tone' => ($preflight_active_count + $preflight_queued_count + ($auto_warm_active ? 1 : 0)) > 0 ? 'success' : 'neutral',
            ],
            [
                'label' => 'User Snapshots',
                'value' => number_format_i18n($profile_snapshot_count + $login_snapshot_count + $availability_snapshot_count),
                'meta' => trim(implode(' · ', array_filter([
                    $profile_snapshot_count > 0 ? ('Profile ' . number_format_i18n($profile_snapshot_count)) : '',
                    $login_snapshot_count > 0 ? ('Login ' . number_format_i18n($login_snapshot_count)) : '',
                    $availability_snapshot_count > 0 ? ('Availability ' . number_format_i18n($availability_snapshot_count)) : '',
                ]))),
                'hint' => 'Redis CBT only',
                'tone' => 'neutral',
            ],
        ];

        $context = [
            'cards' => $cards,
            'refreshed_every_seconds' => self::HERO_OPERATIONAL_STATS_TTL,
        ];

        set_transient($transient_key, $context, self::HERO_OPERATIONAL_STATS_TTL);

        return $context;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_preview_context(array $query): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $exam_id = isset($query['preview_exam_id']) ? absint(wp_unslash((string) $query['preview_exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($query);
        $back_url = add_query_arg(
            self::add_exam_list_state_args(
                [
                    'page' => 'cbt-exams',
                    'cbt_exam_panel' => 'list',
                ],
                $exam_list_state
            ),
            admin_url('admin.php')
        );

        $exam = null;
        $questions = [];
        $options_map = [];
        $question_type_labels = [
            'multiple_choice' => 'Multiple Choice',
            'multiple_answer' => 'Multiple Answer',
            'true_false' => 'True/False',
            'true_false_matrix' => 'True/False Matrix',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
        ];
        $can_manage_questions = current_user_can('cbt_manage_questions');
        $question_type_counts = [];
        $total_points = 0.0;
        $question_count = 0;
        $schedule_text = '-';
        $error_message = '';

        if ($exam_id <= 0) {
            $error_message = 'Exam tidak ditemukan atau tidak bisa diakses.';
            return get_defined_vars();
        }

        if ($is_admin_scope) {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT e.*, s.name AS subject_name
                     FROM {$exam_table} e
                     LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                     WHERE e.id = %d
                     LIMIT 1",
                    $exam_id
                ),
                ARRAY_A
            );
        } else {
            $exam = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT e.*, s.name AS subject_name
                     FROM {$exam_table} e
                     LEFT JOIN {$subject_table} s ON s.id = e.subject_id
                     WHERE e.id = %d AND e.created_by = %d
                     LIMIT 1",
                    $exam_id,
                    $current_user_id
                ),
                ARRAY_A
            );
        }

        if (!$exam) {
            $error_message = 'Exam tidak ditemukan atau tidak bisa diakses.';
            return get_defined_vars();
        }

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.id,
                        q.question_text,
                        q.question_type,
                        q.points,
                        q.explanation,
                        q.correct_text,
                        q.source_question_id,
                        COALESCE(source_exam.title, '') AS source_exam_title
                 FROM {$question_table} q
                 LEFT JOIN {$question_table} source_question ON source_question.id = q.source_question_id
                 LEFT JOIN {$exam_table} source_exam ON source_exam.id = source_question.exam_id
                 WHERE q.exam_id = %d
                   AND COALESCE(q.is_active, 1) = 1
                 ORDER BY q.id ASC",
                $exam_id
            ),
            ARRAY_A
        );

        $question_ids = array_values(array_filter(array_map('intval', wp_list_pluck((array) $questions, 'id')), static function ($id): bool {
            return $id > 0;
        }));
        if (!empty($question_ids)) {
            $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
            $option_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, question_id, option_key, option_text, is_correct
                     FROM {$option_table}
                     WHERE question_id IN ({$placeholders})
                     ORDER BY question_id ASC, id ASC",
                    ...$question_ids
                ),
                ARRAY_A
            );

            foreach ((array) $option_rows as $option_row) {
                $question_id = (int) ($option_row['question_id'] ?? 0);
                if ($question_id <= 0) {
                    continue;
                }
                if (!isset($options_map[$question_id])) {
                    $options_map[$question_id] = [];
                }
                $options_map[$question_id][] = $option_row;
            }
        }

        foreach ((array) $questions as $question_row) {
            $type = (string) ($question_row['question_type'] ?? '');
            if (!isset($question_type_counts[$type])) {
                $question_type_counts[$type] = 0;
            }
            $question_type_counts[$type] += 1;
            $total_points += (float) ($question_row['points'] ?? 0);
        }

        $question_count = count((array) $questions);
        $schedule_parts = [];
        if (!empty($exam['starts_at'])) {
            $schedule_parts[] = 'Mulai: ' . (string) $exam['starts_at'];
        }
        if (!empty($exam['ends_at'])) {
            $schedule_parts[] = 'Selesai: ' . (string) $exam['ends_at'];
        }
        $schedule_text = !empty($schedule_parts) ? implode(' | ', $schedule_parts) : '-';

        return get_defined_vars();
    }

    public static function handle_save_exam(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_save_exam');

        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $payload = self::normalize_exam_save_payload($_POST);
        if (is_wp_error($payload)) {
            self::redirect_exam_with_error($payload->get_error_message(), (int) ($payload->get_error_data() ?? 0));
        }

        $save_result = self::upsert_exam_record_from_payload($payload, $is_admin_scope, $current_user_id);
        if (is_wp_error($save_result)) {
            self::redirect_exam_with_error($save_result->get_error_message(), (int) ($payload['id'] ?? 0));
        }

        $saved_exam_id = (int) ($save_result['exam_id'] ?? 0);
        $source_question_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($payload['source_question_ids'] ?? [])))));
        $synced_questions = null;
        $sync_summary = [];
        if (!empty($source_question_ids)) {
            $sync_result = self::sync_exam_questions_from_sources(
                $saved_exam_id,
                $source_question_ids,
                $is_admin_scope,
                $current_user_id
            );
            if (is_wp_error($sync_result)) {
                self::redirect_exam_with_error($sync_result->get_error_message(), $saved_exam_id);
            }

            $sync_summary = is_array($sync_result) ? $sync_result : [];
            $synced_questions = max(0, (int) ($sync_summary['synced_question_count'] ?? 0));
        }

        $finalize_result = self::finalize_exam_save_operation(
            $saved_exam_id,
            (int) ($payload['id'] ?? 0),
            $synced_questions,
            $current_user_id,
            $_POST,
            $sync_summary
        );

        wp_safe_redirect((string) ($finalize_result['redirect_url'] ?? admin_url('admin.php?page=cbt-exams')));
        exit;
    }

    public static function handle_delete_exam(): void
    {
        if (!self::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        $id = isset($_GET['id']) ? absint(wp_unslash((string) $_GET['id'])) : 0;
        check_admin_referer('cbt_delete_exam_' . $id);
        $exam_list_state = self::get_exam_list_state_from_request($_GET);

        if ($id > 0) {
            global $wpdb;

            $exam_title = (string) $wpdb->get_var(
                $wpdb->prepare("SELECT title FROM {$wpdb->prefix}cbt_exams WHERE id = %d", $id)
            );
            if (self::is_bank_exam_title($exam_title)) {
                wp_safe_redirect(add_query_arg(
                    self::add_exam_list_state_args(
                        [
                            'page' => 'cbt-exams',
                            'cbt_exam_panel' => 'list',
                            'cbt_err' => 'Exam bank soal tidak boleh dihapus dari menu ini.',
                        ],
                        $exam_list_state
                    ),
                    admin_url('admin.php')
                ));
                exit;
            }

            if (!self::is_admin_scope()) {
                $owned_exam = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cbt_exams WHERE id = %d AND created_by = %d",
                    $id,
                    get_current_user_id()
                ));
                if ($owned_exam === 0) {
                    wp_die('Unauthorized exam delete.');
                }
            }

            $wpdb->delete($wpdb->prefix . 'cbt_exams', ['id' => $id], ['%d']);
        }

        if ($id > 0) {
            CBT_Cache::invalidate_catalog();
            CBT_Cache::invalidate_exam($id);
        }

        wp_safe_redirect(add_query_arg(
            self::add_exam_list_state_args(
                [
                    'page' => 'cbt-exams',
                    'cbt_exam_panel' => 'list',
                    'cbt_msg' => 'Exam deleted',
                ],
                $exam_list_state
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public static function handle_warm_exam_delivery_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_exam_delivery_snapshot');

        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if ($exam_id <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Exam wajib dipilih untuk menyiapkan snapshot soal.',
            ]);
        }

        if (
            !class_exists('CBT_REST')
            || !class_exists('CBT_Exam_Question_Delivery_Cache')
            || !class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')
        ) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Runtime snapshot exam belum tersedia lengkap di environment ini.',
            ]);
        }

        CBT_REST::warm_exam_question_delivery_snapshot($exam_id);
        CBT_REST::warm_exam_start_attempt_snapshot($exam_id);
        $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics($exam_id);
        $start_diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id);
        if (!empty($diagnostics['snapshot_valid']) && !empty($start_diagnostics['snapshot_valid'])) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => sprintf(
                    'Snapshot exam #%d siap. Soal: %d item · Start: %d item.',
                    $exam_id,
                    (int) ($diagnostics['snapshot_item_count'] ?? 0),
                    (int) ($start_diagnostics['snapshot_item_count'] ?? 0)
                ),
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Snapshot exam #%d belum valid. %s %s',
                $exam_id,
                (string) ($diagnostics['snapshot_message'] ?? ''),
                (string) ($start_diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_warm_bulk_exam_delivery_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_bulk_exam_delivery_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (
            !class_exists('CBT_REST')
            || !class_exists('CBT_Exam_Question_Delivery_Cache')
            || !class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')
        ) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Runtime snapshot exam belum tersedia lengkap di environment ini.',
            ]);
        }

        $rows = self::get_filtered_exam_snapshot_exams(self::is_admin_scope(), get_current_user_id());
        if (empty($rows)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada exam yang tersedia untuk disiapkan snapshot-nya.',
            ]);
        }

        $success_count = 0;
        $failure_count = 0;

        foreach ($rows as $row) {
            $exam_id = (int) ($row['id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            CBT_REST::warm_exam_question_delivery_snapshot($exam_id);
            CBT_REST::warm_exam_start_attempt_snapshot($exam_id);
            $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics($exam_id);
            $start_diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id);
            if (!empty($diagnostics['snapshot_valid']) && !empty($start_diagnostics['snapshot_valid'])) {
                $success_count++;
            } else {
                $failure_count++;
            }
        }

        if ($success_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => sprintf(
                    'Gagal menyiapkan snapshot exam untuk %d exam yang dipilih.',
                    max(0, $failure_count)
                ),
            ]);
        }

        $message = sprintf('Berhasil menyiapkan %d snapshot exam.', $success_count);
        if ($failure_count > 0) {
            $message .= ' Gagal: ' . $failure_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_clear_exam_delivery_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_exam_delivery_snapshot');

        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if ($exam_id <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Exam wajib dipilih untuk membersihkan snapshot soal.',
            ]);
        }

        if (!class_exists('CBT_Exam_Question_Delivery_Cache') || !class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Runtime snapshot exam belum tersedia lengkap di environment ini.',
            ]);
        }

        $deleted_count = CBT_Exam_Question_Delivery_Cache::clear_exam_payload($exam_id)
            + CBT_Exam_Start_Attempt_Snapshot_Cache::clear_exam_snapshot($exam_id);
        $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics($exam_id);
        $start_diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id);

        if ($deleted_count > 0 || (($diagnostics['snapshot_status'] ?? '') === 'miss' && ($start_diagnostics['snapshot_status'] ?? '') === 'miss')) {
            $message = $deleted_count > 0
                ? sprintf('Snapshot exam #%d berhasil dibersihkan. Keys: %d.', $exam_id, $deleted_count)
                : sprintf('Snapshot exam #%d sudah kosong.', $exam_id);
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => $message,
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Snapshot exam #%d belum berhasil dibersihkan. %s %s',
                $exam_id,
                (string) ($diagnostics['snapshot_message'] ?? ''),
                (string) ($start_diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_clear_bulk_exam_delivery_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_bulk_exam_delivery_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!class_exists('CBT_Exam_Question_Delivery_Cache') || !class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Runtime snapshot exam belum tersedia lengkap di environment ini.',
            ]);
        }

        $rows = self::get_filtered_exam_snapshot_exams(self::is_admin_scope(), get_current_user_id());
        if (empty($rows)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada exam yang tersedia untuk dibersihkan snapshot-nya.',
            ]);
        }

        $cleared_exam_count = 0;
        $empty_exam_count = 0;
        $deleted_key_count = 0;

        foreach ($rows as $row) {
            $exam_id = (int) ($row['id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $deleted_count = CBT_Exam_Question_Delivery_Cache::clear_exam_payload($exam_id)
                + CBT_Exam_Start_Attempt_Snapshot_Cache::clear_exam_snapshot($exam_id);
            $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics($exam_id);
            $start_diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id);

            if ($deleted_count > 0) {
                $cleared_exam_count++;
                $deleted_key_count += $deleted_count;
                continue;
            }

            if (($diagnostics['snapshot_status'] ?? '') === 'miss' && ($start_diagnostics['snapshot_status'] ?? '') === 'miss') {
                $empty_exam_count++;
            }
        }

        if ($cleared_exam_count <= 0 && $empty_exam_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada snapshot exam yang berhasil dibersihkan pada exam yang dipilih.',
            ]);
        }

        $message = sprintf('Berhasil membersihkan snapshot exam untuk %d exam.', $cleared_exam_count);
        if ($deleted_key_count > 0) {
            $message .= ' Keys: ' . $deleted_key_count . '.';
        }
        if ($empty_exam_count > 0) {
            $message .= ' Sudah kosong: ' . $empty_exam_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_refresh_attempt_runtime_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_refresh_attempt_runtime_snapshot');

        $attempt_id = isset($_POST['attempt_id']) ? absint(wp_unslash((string) $_POST['attempt_id'])) : 0;
        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);

        if ($attempt_id <= 0 || $exam_id <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Attempt dan exam wajib dipilih untuk refresh runtime snapshot.',
            ]);
        }

        if (!class_exists('CBT_Attempt_Runtime_Snapshot_Service')) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Helper runtime snapshot belum tersedia di environment ini.',
            ]);
        }

        $result = CBT_Attempt_Runtime_Snapshot_Service::rebuild_attempt_snapshots($attempt_id, $exam_id);
        if (!empty($result['ok'])) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => sprintf(
                    'Runtime snapshot attempt #%d berhasil direfresh. %s',
                    $attempt_id,
                    (string) ($result['message'] ?? '')
                ),
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Runtime snapshot attempt #%d gagal direfresh. %s',
                $attempt_id,
                (string) ($result['message'] ?? '')
            ),
        ]);
    }

    public static function handle_warm_exam_submission_context_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_exam_submission_context_snapshot');

        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if ($exam_id <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Exam wajib dipilih untuk menyiapkan submission context.',
            ]);
        }

        if (!class_exists('CBT_REST') || !class_exists('CBT_Question_Submission_Context_Cache')) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Runtime submission context belum tersedia lengkap di environment ini.',
            ]);
        }

        CBT_REST::warm_exam_submission_context_snapshot($exam_id);
        $diagnostics = CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics($exam_id);
        if ((string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => sprintf(
                    'Submission context exam #%d siap. READY %d/%d.',
                    $exam_id,
                    (int) ($diagnostics['ready_count'] ?? 0),
                    (int) ($diagnostics['question_count'] ?? 0)
                ),
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Submission context exam #%d belum penuh. READY %d/%d · MISS %d · INVALID %d. %s',
                $exam_id,
                (int) ($diagnostics['ready_count'] ?? 0),
                (int) ($diagnostics['question_count'] ?? 0),
                (int) ($diagnostics['missing_count'] ?? 0),
                (int) ($diagnostics['invalid_count'] ?? 0),
                (string) ($diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_warm_bulk_exam_submission_context_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_bulk_exam_submission_context_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!class_exists('CBT_REST') || !class_exists('CBT_Question_Submission_Context_Cache')) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Runtime submission context belum tersedia lengkap di environment ini.',
            ]);
        }

        $rows = self::get_filtered_exam_snapshot_exams(self::is_admin_scope(), get_current_user_id());
        if (empty($rows)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada exam yang tersedia untuk disiapkan submission context-nya.',
            ]);
        }

        $success_count = 0;
        $failure_count = 0;

        foreach ($rows as $row) {
            $exam_id = (int) ($row['id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            CBT_REST::warm_exam_submission_context_snapshot($exam_id);
            $diagnostics = CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics($exam_id);
            if ((string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
                $success_count++;
            } else {
                $failure_count++;
            }
        }

        if ($success_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => sprintf(
                    'Gagal menyiapkan submission context untuk %d exam yang dipilih.',
                    max(0, $failure_count)
                ),
            ]);
        }

        $message = sprintf('Berhasil menyiapkan submission context untuk %d exam.', $success_count);
        if ($failure_count > 0) {
            $message .= ' Belum penuh: ' . $failure_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_clear_exam_submission_context_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_exam_submission_context_snapshot');

        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if ($exam_id <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Exam wajib dipilih untuk membersihkan submission context.',
            ]);
        }

        if (!class_exists('CBT_Question_Submission_Context_Cache')) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Runtime submission context belum tersedia di environment ini.',
            ]);
        }

        $result = CBT_Question_Submission_Context_Cache::clear_exam_snapshots($exam_id);
        $diagnostics = CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics($exam_id);
        if ((int) ($result['deleted_keys'] ?? 0) > 0 || in_array((string) ($diagnostics['snapshot_status'] ?? ''), ['miss', 'idle'], true)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => (int) ($result['deleted_keys'] ?? 0) > 0
                    ? sprintf(
                        'Submission context exam #%d dibersihkan. Keys terhapus %d.',
                        $exam_id,
                        (int) ($result['deleted_keys'] ?? 0)
                    )
                    : sprintf('Submission context exam #%d sudah kosong.', $exam_id),
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Submission context exam #%d belum berhasil dibersihkan. %s',
                $exam_id,
                (string) ($diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_clear_bulk_exam_submission_context_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_bulk_exam_submission_context_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!class_exists('CBT_Question_Submission_Context_Cache')) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Runtime submission context belum tersedia di environment ini.',
            ]);
        }

        $rows = self::get_filtered_exam_snapshot_exams(self::is_admin_scope(), get_current_user_id());
        if (empty($rows)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada exam yang tersedia untuk dibersihkan submission context-nya.',
            ]);
        }

        $cleared_exam_count = 0;
        $empty_exam_count = 0;
        $deleted_key_count = 0;

        foreach ($rows as $row) {
            $exam_id = (int) ($row['id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $result = CBT_Question_Submission_Context_Cache::clear_exam_snapshots($exam_id);
            $diagnostics = CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics($exam_id);

            if ((int) ($result['deleted_keys'] ?? 0) > 0) {
                $cleared_exam_count++;
                $deleted_key_count += (int) ($result['deleted_keys'] ?? 0);
                continue;
            }

            if (in_array((string) ($diagnostics['snapshot_status'] ?? ''), ['miss', 'idle'], true)) {
                $empty_exam_count++;
            }
        }

        if ($cleared_exam_count <= 0 && $empty_exam_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada submission context yang berhasil dibersihkan pada exam yang dipilih.',
            ]);
        }

        $message = sprintf('Berhasil membersihkan submission context untuk %d exam.', $cleared_exam_count);
        if ($deleted_key_count > 0) {
            $message .= ' Keys: ' . $deleted_key_count . '.';
        }
        if ($empty_exam_count > 0) {
            $message .= ' Sudah kosong: ' . $empty_exam_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_start_exam_availability_auto_warm(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_start_exam_availability_auto_warm');

        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $exam_row = self::get_snapshot_exam_row_by_id($exam_id, self::is_admin_scope(), get_current_user_id());
        if (!is_array($exam_row)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Exam snapshot tidak ditemukan atau tidak tersedia.',
            ]);
        }

        $result = CBT_Exam_Availability_Auto_Warm_Service::start_for_exam($exam_row);
        self::redirect_exam_snapshot_page($exam_list_state, [
            !empty($result['success']) ? 'cbt_msg' : 'cbt_err' => (string) ($result['message'] ?? 'Gagal memulai auto-warm availability.'),
        ]);
    }

    public static function handle_stop_exam_availability_auto_warm(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_stop_exam_availability_auto_warm');

        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $exam_row = self::get_snapshot_exam_row_by_id($exam_id, self::is_admin_scope(), get_current_user_id());
        if (!is_array($exam_row)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Exam snapshot tidak ditemukan atau tidak tersedia.',
            ]);
        }

        $result = CBT_Exam_Availability_Auto_Warm_Service::stop_for_exam($exam_row);
        self::redirect_exam_snapshot_page($exam_list_state, [
            !empty($result['success']) ? 'cbt_msg' : 'cbt_err' => (string) ($result['message'] ?? 'Gagal menghentikan auto-warm availability.'),
        ]);
    }

    public static function handle_start_exam_preflight(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_start_exam_preflight');

        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $exam_row = self::get_snapshot_exam_row_by_id($exam_id, self::is_admin_scope(), get_current_user_id());
        if (!is_array($exam_row)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Exam snapshot tidak ditemukan atau tidak tersedia.',
            ]);
        }

        $result = CBT_Exam_Preflight_Service::start_for_exam($exam_row);
        self::redirect_exam_snapshot_page($exam_list_state, [
            !empty($result['success']) ? 'cbt_msg' : 'cbt_err' => (string) ($result['message'] ?? 'Gagal menjalankan one-click pra ujian.'),
        ]);
    }

    public static function handle_start_bulk_exam_preflight(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_start_bulk_exam_preflight');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $snapshot_filter_state = self::get_exam_snapshot_filter_state_from_request($_POST);
        $selected_exam_ids = array_values(array_filter(array_map('intval', (array) ($snapshot_filter_state['exam_ids'] ?? []))));

        if (count($selected_exam_ids) < 2) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Bulk One-Click membutuhkan minimal 2 exam yang dipilih.',
            ]);
        }

        if (count($selected_exam_ids) > 10) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Bulk One-Click dibatasi maksimal 10 exam per run.',
            ]);
        }

        $rows = self::get_filtered_exam_snapshot_exams(self::is_admin_scope(), get_current_user_id(), $selected_exam_ids);
        $row_map = [];
        foreach ($rows as $row) {
            $row_exam_id = (int) ($row['id'] ?? 0);
            if ($row_exam_id > 0) {
                $row_map[$row_exam_id] = $row;
            }
        }

        $ordered_rows = [];
        foreach ($selected_exam_ids as $selected_exam_id) {
            if (!isset($row_map[$selected_exam_id])) {
                self::redirect_exam_snapshot_page($exam_list_state, [
                    'cbt_err' => 'Ada exam pada bulk selection yang tidak ditemukan atau tidak tersedia.',
                ]);
            }

            $ordered_rows[] = $row_map[$selected_exam_id];
        }

        $counts = [
            'active' => 0,
            'queued' => 0,
            'completed' => 0,
            'completed_with_warnings' => 0,
            'failed' => 0,
            'other' => 0,
        ];

        foreach ($ordered_rows as $exam_row) {
            $result = CBT_Exam_Preflight_Service::start_for_exam($exam_row);
            $state = isset($result['state']) && is_array($result['state']) ? $result['state'] : [];
            $status = sanitize_key((string) ($state['status'] ?? ''));

            if (!$result['success']) {
                $status = 'failed';
            }

            if (!isset($counts[$status])) {
                $status = 'other';
            }

            $counts[$status]++;
        }

        $processed_total = count($ordered_rows);
        $message = sprintf(
            'Bulk one-click memproses %1$d exam: aktif %2$d, antre %3$d, selesai %4$d, selesai dengan catatan %5$d, gagal %6$d.',
            $processed_total,
            $counts['active'],
            $counts['queued'],
            $counts['completed'],
            $counts['completed_with_warnings'],
            $counts['failed']
        );

        self::redirect_exam_snapshot_page($exam_list_state, [
            ($counts['failed'] >= $processed_total) ? 'cbt_err' : 'cbt_msg' => $message,
        ]);
    }

    public static function handle_clean_bulk_exam_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clean_bulk_exam_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $snapshot_filter_state = self::get_exam_snapshot_filter_state_from_request($_POST);
        $selected_exam_ids = array_values(array_filter(array_map('intval', (array) ($snapshot_filter_state['exam_ids'] ?? []))));

        if (count($selected_exam_ids) < 2) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Bulk clean snapshot membutuhkan minimal 2 exam yang dipilih.',
            ]);
        }

        if (count($selected_exam_ids) > 10) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Bulk clean snapshot dibatasi maksimal 10 exam per run.',
            ]);
        }

        $rows = self::get_filtered_exam_snapshot_exams(self::is_admin_scope(), get_current_user_id(), $selected_exam_ids);
        $row_map = [];
        foreach ($rows as $row) {
            $row_exam_id = (int) ($row['id'] ?? 0);
            if ($row_exam_id > 0) {
                $row_map[$row_exam_id] = $row;
            }
        }

        $ordered_rows = [];
        foreach ($selected_exam_ids as $selected_exam_id) {
            if (!isset($row_map[$selected_exam_id])) {
                self::redirect_exam_snapshot_page($exam_list_state, [
                    'cbt_err' => 'Ada exam pada bulk selection yang tidak ditemukan atau tidak tersedia.',
                ]);
            }

            $ordered_rows[] = $row_map[$selected_exam_id];
        }

        $success_count = 0;
        $failure_count = 0;
        $failure_messages = [];

        foreach ($ordered_rows as $exam_row) {
            $result = self::clean_exam_snapshot_stack($exam_row);
            if (!empty($result['success'])) {
                $success_count++;
                continue;
            }

            $failure_count++;
            if (count($failure_messages) < 3) {
                $exam_title = trim((string) ($exam_row['title'] ?? ''));
                $exam_label = $exam_title !== '' ? $exam_title : ('Exam #' . (int) ($exam_row['id'] ?? 0));
                $failure_messages[] = $exam_label . ': ' . trim((string) ($result['message'] ?? 'Gagal membersihkan snapshot.'));
            }
        }

        if ($success_count <= 0) {
            $message = sprintf(
                'Bulk clean snapshot gagal untuk %d exam terpilih.',
                count($ordered_rows)
            );
            if (!empty($failure_messages)) {
                $message .= ' ' . implode(' · ', $failure_messages);
            }

            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => $message,
            ]);
        }

        $message = sprintf(
            'Bulk clean snapshot memproses %1$d exam: berhasil %2$d, gagal %3$d.',
            count($ordered_rows),
            $success_count,
            $failure_count
        );
        if (!empty($failure_messages)) {
            $message .= ' ' . implode(' · ', $failure_messages);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            ($failure_count >= count($ordered_rows)) ? 'cbt_err' : 'cbt_msg' => $message,
        ]);
    }

    public static function handle_clean_exam_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clean_exam_snapshots');

        $exam_id = isset($_POST['exam_id']) ? absint(wp_unslash((string) $_POST['exam_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $exam_row = self::get_snapshot_exam_row_by_id($exam_id, self::is_admin_scope(), get_current_user_id());
        if (!is_array($exam_row)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Exam snapshot tidak ditemukan atau tidak tersedia.',
            ]);
        }

        $result = self::clean_exam_snapshot_stack($exam_row);
        self::redirect_exam_snapshot_page($exam_list_state, [
            !empty($result['success']) ? 'cbt_msg' : 'cbt_err' => (string) ($result['message'] ?? 'Gagal membersihkan snapshot pra ujian.'),
        ]);
    }

    public static function handle_hard_reset_cbt_redis(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_hard_reset_cbt_redis');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!class_exists('CBT_Plugin_Redis_Reset_Service')) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Helper reset Redis CBT belum tersedia di environment ini.',
            ]);
        }

        $result = CBT_Plugin_Redis_Reset_Service::reset_all_plugin_keys();
        $message_key = !empty($result['success']) ? 'cbt_msg' : 'cbt_err';
        $message = (string) ($result['message'] ?? 'Gagal membersihkan Redis CBT.');
        $database_parts = [];
        foreach ((array) ($result['databases'] ?? []) as $database_summary) {
            $database = max(0, (int) ($database_summary['database'] ?? 0));
            $deleted_keys = max(0, (int) ($database_summary['deleted_keys'] ?? 0));
            $database_parts[] = 'DB ' . $database . ': ' . $deleted_keys . ' key';
        }
        if (!empty($database_parts)) {
            $message .= ' ' . implode(' · ', $database_parts) . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            $message_key => $message,
        ]);
    }

    public static function handle_warm_student_exam_availability_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_student_exam_availability_snapshot');

        $user_id = isset($_POST['user_id']) ? absint(wp_unslash((string) $_POST['user_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!self::is_valid_snapshot_student_user_id($user_id)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Siswa wajib dipilih untuk menyiapkan snapshot ketersediaan exam.',
            ]);
        }

        $snapshot = self::warm_student_exam_availability_snapshot_internal($user_id);
        $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
        if (!empty($diagnostics['snapshot_valid'])) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => sprintf(
                    'Snapshot ketersediaan siswa #%d siap. Items: %d.',
                    $user_id,
                    (int) ($diagnostics['item_count'] ?? count((array) ($snapshot['items'] ?? [])))
                ),
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Snapshot ketersediaan siswa #%d belum valid. %s',
                $user_id,
                (string) ($diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_clear_student_exam_availability_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_student_exam_availability_snapshot');

        $user_id = isset($_POST['user_id']) ? absint(wp_unslash((string) $_POST['user_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!self::is_valid_snapshot_student_user_id($user_id)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Siswa wajib dipilih untuk membersihkan snapshot ketersediaan exam.',
            ]);
        }

        $deleted_count = CBT_Exam_Availability_Cache::clear_student_snapshot($user_id);
        $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
        if ($deleted_count > 0 || ($diagnostics['snapshot_status'] ?? '') === 'miss') {
            $message = $deleted_count > 0
                ? sprintf('Snapshot ketersediaan siswa #%d berhasil dibersihkan. Keys: %d.', $user_id, $deleted_count)
                : sprintf('Snapshot ketersediaan siswa #%d sudah kosong.', $user_id);
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => $message,
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Snapshot ketersediaan siswa #%d belum berhasil dibersihkan. %s',
                $user_id,
                (string) ($diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_warm_bulk_student_exam_availability_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_bulk_student_exam_availability_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $student_snapshot_filter_state = self::get_student_snapshot_filter_state_from_request($_POST);
        $users = self::get_filtered_snapshot_student_users($student_snapshot_filter_state);
        if (empty($users)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada siswa yang cocok dengan filter saat ini untuk disiapkan snapshot availability-nya.',
            ]);
        }

        $success_count = 0;
        $failure_count = 0;
        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            self::warm_student_exam_availability_snapshot_internal($user_id);
            $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
            if (!empty($diagnostics['snapshot_valid'])) {
                $success_count++;
            } else {
                $failure_count++;
            }
        }

        if ($success_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => sprintf('Gagal menyiapkan snapshot availability untuk %d siswa yang terfilter.', max(0, $failure_count)),
            ]);
        }

        $message = sprintf('Berhasil menyiapkan %d snapshot availability.', $success_count);
        if ($failure_count > 0) {
            $message .= ' Gagal: ' . $failure_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_clear_bulk_student_exam_availability_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_bulk_student_exam_availability_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $student_snapshot_filter_state = self::get_student_snapshot_filter_state_from_request($_POST);
        $users = self::get_filtered_snapshot_student_users($student_snapshot_filter_state);
        if (empty($users)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada siswa yang cocok dengan filter saat ini untuk dibersihkan snapshot availability-nya.',
            ]);
        }

        $cleared_count = 0;
        $empty_count = 0;
        $deleted_key_count = 0;
        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            $deleted_count = CBT_Exam_Availability_Cache::clear_student_snapshot($user_id);
            $diagnostics = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
            if ($deleted_count > 0) {
                $cleared_count++;
                $deleted_key_count += $deleted_count;
            } elseif (($diagnostics['snapshot_status'] ?? '') === 'miss') {
                $empty_count++;
            }
        }

        if ($cleared_count <= 0 && $empty_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada snapshot availability yang berhasil dibersihkan pada filter aktif.',
            ]);
        }

        $message = sprintf('Berhasil membersihkan snapshot availability untuk %d siswa.', $cleared_count);
        if ($deleted_key_count > 0) {
            $message .= ' Keys: ' . $deleted_key_count . '.';
        }
        if ($empty_count > 0) {
            $message .= ' Sudah kosong: ' . $empty_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_warm_student_profile_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_student_profile_snapshot');

        $user_id = isset($_POST['user_id']) ? absint(wp_unslash((string) $_POST['user_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!self::is_valid_snapshot_student_user_id($user_id)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Siswa wajib dipilih untuk menyiapkan snapshot profil.',
            ]);
        }

        CBT_Student_Profile_Cache::warm_snapshot($user_id);
        $diagnostics = CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id);
        if (!empty($diagnostics['snapshot_valid'])) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => sprintf('Snapshot profil siswa #%d siap.', $user_id),
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Snapshot profil siswa #%d belum valid. %s',
                $user_id,
                (string) ($diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_clear_student_profile_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_student_profile_snapshot');

        $user_id = isset($_POST['user_id']) ? absint(wp_unslash((string) $_POST['user_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!self::is_valid_snapshot_student_user_id($user_id)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Siswa wajib dipilih untuk membersihkan snapshot profil.',
            ]);
        }

        $deleted_count = CBT_Student_Profile_Cache::clear_snapshot($user_id);
        $diagnostics = CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id);
        if ($deleted_count > 0 || ($diagnostics['snapshot_status'] ?? '') === 'miss') {
            $message = $deleted_count > 0
                ? sprintf('Snapshot profil siswa #%d berhasil dibersihkan.', $user_id)
                : sprintf('Snapshot profil siswa #%d sudah kosong.', $user_id);
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => $message,
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Snapshot profil siswa #%d belum berhasil dibersihkan. %s',
                $user_id,
                (string) ($diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_warm_bulk_student_profile_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_bulk_student_profile_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $student_snapshot_filter_state = self::get_student_snapshot_filter_state_from_request($_POST);
        $users = self::get_filtered_snapshot_student_users($student_snapshot_filter_state);
        if (empty($users)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada siswa yang cocok dengan filter saat ini untuk disiapkan snapshot profilnya.',
            ]);
        }

        $success_count = 0;
        $failure_count = 0;
        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            CBT_Student_Profile_Cache::warm_snapshot($user_id);
            $diagnostics = CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id);
            if (!empty($diagnostics['snapshot_valid'])) {
                $success_count++;
            } else {
                $failure_count++;
            }
        }

        if ($success_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => sprintf('Gagal menyiapkan snapshot profil untuk %d siswa yang terfilter.', max(0, $failure_count)),
            ]);
        }

        $message = sprintf('Berhasil menyiapkan %d snapshot profil.', $success_count);
        if ($failure_count > 0) {
            $message .= ' Gagal: ' . $failure_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_clear_bulk_student_profile_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_bulk_student_profile_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $student_snapshot_filter_state = self::get_student_snapshot_filter_state_from_request($_POST);
        $users = self::get_filtered_snapshot_student_users($student_snapshot_filter_state);
        if (empty($users)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada siswa yang cocok dengan filter saat ini untuk dibersihkan snapshot profilnya.',
            ]);
        }

        $cleared_count = 0;
        $empty_count = 0;
        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            $deleted_count = CBT_Student_Profile_Cache::clear_snapshot($user_id);
            $diagnostics = CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id);
            if ($deleted_count > 0) {
                $cleared_count++;
            } elseif (($diagnostics['snapshot_status'] ?? '') === 'miss') {
                $empty_count++;
            }
        }

        if ($cleared_count <= 0 && $empty_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada snapshot profil yang berhasil dibersihkan pada filter aktif.',
            ]);
        }

        $message = sprintf('Berhasil membersihkan snapshot profil untuk %d siswa.', $cleared_count);
        if ($empty_count > 0) {
            $message .= ' Sudah kosong: ' . $empty_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_warm_student_login_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_student_login_snapshot');

        $user_id = isset($_POST['user_id']) ? absint(wp_unslash((string) $_POST['user_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!self::is_valid_snapshot_student_user_id($user_id)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Siswa wajib dipilih untuk menyiapkan login snapshot.',
            ]);
        }

        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot($user_id, 'snapshot_page');
        $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
        if ((string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => sprintf(
                    'Login snapshot siswa #%d siap. TTL %d detik.',
                    $user_id,
                    max(0, (int) ($diagnostics['ttl_seconds'] ?? 0))
                ),
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Login snapshot siswa #%d belum valid. %s',
                $user_id,
                (string) ($diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_clear_student_login_snapshot(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_student_login_snapshot');

        $user_id = isset($_POST['user_id']) ? absint(wp_unslash((string) $_POST['user_id'])) : 0;
        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        if (!self::is_valid_snapshot_student_user_id($user_id)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Siswa wajib dipilih untuk membersihkan login snapshot.',
            ]);
        }

        $deleted_count = CBT_Login_Auth_Snapshot_Cache::clear_user_snapshot($user_id);
        $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
        if ($deleted_count > 0 || ($diagnostics['snapshot_status'] ?? '') === 'miss') {
            $message = $deleted_count > 0
                ? sprintf('Login snapshot siswa #%d berhasil dibersihkan. Keys: %d.', $user_id, $deleted_count)
                : sprintf('Login snapshot siswa #%d sudah kosong.', $user_id);
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_msg' => $message,
            ]);
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_err' => sprintf(
                'Login snapshot siswa #%d belum berhasil dibersihkan. %s',
                $user_id,
                (string) ($diagnostics['snapshot_message'] ?? '')
            ),
        ]);
    }

    public static function handle_warm_bulk_student_login_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_warm_bulk_student_login_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $student_snapshot_filter_state = self::get_student_snapshot_filter_state_from_request($_POST);
        $users = self::get_filtered_snapshot_student_users($student_snapshot_filter_state);
        if (empty($users)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada siswa yang cocok dengan filter saat ini untuk disiapkan login snapshot-nya.',
            ]);
        }

        $success_count = 0;
        $failure_count = 0;
        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot($user_id, 'snapshot_page_bulk');
            $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
            if ((string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
                $success_count++;
            } else {
                $failure_count++;
            }
        }

        if ($success_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => sprintf('Gagal menyiapkan login snapshot untuk %d siswa yang terfilter.', max(0, $failure_count)),
            ]);
        }

        $message = sprintf('Berhasil menyiapkan %d login snapshot.', $success_count);
        if ($failure_count > 0) {
            $message .= ' Gagal: ' . $failure_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    public static function handle_clear_bulk_student_login_snapshots(): void
    {
        if (!self::can_manage_exam_snapshots()) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cbt_clear_bulk_student_login_snapshots');

        $exam_list_state = self::get_exam_list_state_from_request($_POST);
        $student_snapshot_filter_state = self::get_student_snapshot_filter_state_from_request($_POST);
        $users = self::get_filtered_snapshot_student_users($student_snapshot_filter_state);
        if (empty($users)) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada siswa yang cocok dengan filter saat ini untuk dibersihkan login snapshot-nya.',
            ]);
        }

        $cleared_count = 0;
        $empty_count = 0;
        $deleted_key_count = 0;
        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            $deleted_count = CBT_Login_Auth_Snapshot_Cache::clear_user_snapshot($user_id);
            $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
            if ($deleted_count > 0) {
                $cleared_count++;
                $deleted_key_count += $deleted_count;
            } elseif (($diagnostics['snapshot_status'] ?? '') === 'miss') {
                $empty_count++;
            }
        }

        if ($cleared_count <= 0 && $empty_count <= 0) {
            self::redirect_exam_snapshot_page($exam_list_state, [
                'cbt_err' => 'Tidak ada login snapshot yang berhasil dibersihkan pada filter aktif.',
            ]);
        }

        $message = sprintf('Berhasil membersihkan login snapshot untuk %d siswa.', $cleared_count);
        if ($deleted_key_count > 0) {
            $message .= ' Keys: ' . $deleted_key_count . '.';
        }
        if ($empty_count > 0) {
            $message .= ' Sudah kosong: ' . $empty_count . '.';
        }

        self::redirect_exam_snapshot_page($exam_list_state, [
            'cbt_msg' => $message,
        ]);
    }

    private static function warm_student_exam_availability_snapshot_internal(int $user_id): array
    {
        if (!class_exists('CBT_REST') || !method_exists('CBT_REST', 'build_student_exam_availability_snapshot_payload')) {
            return ['items' => [], 'current_user' => null];
        }

        return CBT_Exam_Availability_Cache::warm_student_snapshot(
            $user_id,
            static function () use ($user_id): array {
                return CBT_REST::build_student_exam_availability_snapshot_payload($user_id);
            }
        );
    }

    private static function is_valid_snapshot_student_user_id(int $user_id): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $user = get_user_by('id', $user_id);

        return $user instanceof WP_User && self::is_snapshot_student_user($user);
    }

    /**
     * @param int[] $source_question_ids
     * @return int|WP_Error
     */
    public static function sync_exam_questions_from_sources_for_internal_use(
        int $exam_id,
        array $source_question_ids,
        int $current_user_id = 0
    ) {
        $current_user_id = $current_user_id > 0 ? $current_user_id : get_current_user_id();

        $sync_result = self::sync_exam_questions_from_sources(
            $exam_id,
            $source_question_ids,
            true,
            $current_user_id
        );
        if (is_wp_error($sync_result)) {
            return $sync_result;
        }

        return max(0, (int) ($sync_result['synced_question_count'] ?? 0));
    }

    public static function handle_sync_exam_builder_selection(): void
    {
        if (!self::can_manage_exams()) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('cbt_exam_builder_state', 'nonce');

        $builder_state_key = isset($_POST['builder_state_key'])
            ? sanitize_key((string) wp_unslash($_POST['builder_state_key']))
            : '';
        if ($builder_state_key === '') {
            wp_send_json_error(['message' => 'Builder state key tidak valid.'], 400);
        }

        $selected_question_ids = isset($_POST['selected_question_ids']) && is_array($_POST['selected_question_ids'])
            ? array_map('absint', (array) wp_unslash($_POST['selected_question_ids']))
            : [];
        $selected_question_ids = array_values(array_unique(array_filter($selected_question_ids)));

        self::save_exam_builder_selected_question_ids($builder_state_key, get_current_user_id(), $selected_question_ids);

        wp_send_json_success([
            'selected_question_ids' => $selected_question_ids,
        ]);
    }

    public static function handle_clear_exam_builder_selection(): void
    {
        if (!self::can_manage_exams()) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('cbt_exam_builder_state', 'nonce');

        $builder_state_key = isset($_POST['builder_state_key'])
            ? sanitize_key((string) wp_unslash($_POST['builder_state_key']))
            : '';
        if ($builder_state_key === '') {
            wp_send_json_error(['message' => 'Builder state key tidak valid.'], 400);
        }

        self::clear_exam_builder_selection_state($builder_state_key, get_current_user_id());

        wp_send_json_success([
            'cleared' => true,
            'builder_state_key' => $builder_state_key,
        ]);
    }

    public static function handle_start_exam_save_progress(): void
    {
        if (!self::can_manage_exams()) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_admin_referer('cbt_save_exam');
        check_ajax_referer('cbt_exam_builder_state', 'nonce');

        $is_admin_scope = self::is_admin_scope();
        $current_user_id = get_current_user_id();
        $payload = self::normalize_exam_save_payload($_POST);
        if (is_wp_error($payload)) {
            wp_send_json_error(['message' => $payload->get_error_message()], 400);
        }

        $save_result = self::upsert_exam_record_from_payload($payload, $is_admin_scope, $current_user_id);
        if (is_wp_error($save_result)) {
            wp_send_json_error(['message' => $save_result->get_error_message()], 400);
        }

        global $wpdb;

        $question_table = $wpdb->prefix . 'cbt_questions';
        $saved_exam_id = (int) ($save_result['exam_id'] ?? 0);
        $token = strtolower((string) wp_generate_password(24, false, false));
        $selected_source_question_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($payload['source_question_ids'] ?? [])))));
        $existing_exam_question_ids = array_values(array_unique(array_filter(array_map(
            'absint',
            (array) $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$question_table} WHERE exam_id = %d ORDER BY id ASC",
                    $saved_exam_id
                )
            )
        ))));

        $state = [
            'user_id' => $current_user_id,
            'exam_id' => $saved_exam_id,
            'original_exam_id' => (int) ($payload['id'] ?? 0),
            'selected_source_question_ids' => $selected_source_question_ids,
            'total_sources' => count($selected_source_question_ids),
            'processed_sources' => 0,
            'updated_existing_count' => 0,
            'created_new_count' => 0,
            'linked_legacy_match_count' => 0,
            'preserved_attempt_history_count' => 0,
            'removed_question_count' => 0,
            'archived_question_count' => 0,
            'deleted_question_count' => 0,
            'legacy_active_count' => 0,
            'matched_existing_question_ids' => [],
            'existing_exam_question_ids' => $existing_exam_question_ids,
            'is_admin_scope' => $is_admin_scope ? 1 : 0,
            'current_user_id' => $current_user_id,
            'phase' => !empty($selected_source_question_ids) ? 'sync' : 'finalize',
            'message' => !empty($selected_source_question_ids)
                ? 'Menyiapkan sinkronisasi soal exam.'
                : 'Merapikan data exam.',
            'started_at' => time(),
        ];

        $state = self::continue_exam_save_progress_state($state);
        if (is_wp_error($state)) {
            wp_send_json_error(['message' => $state->get_error_message()], 500);
        }

        $response = self::build_exam_save_progress_payload($state);
        $response['token'] = $token;
        if (!empty($response['complete'])) {
            wp_send_json_success($response);
        }

        $saved = set_transient(self::get_exam_save_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$saved) {
            wp_send_json_error(['message' => 'Gagal menyimpan state progress update exam.'], 500);
        }

        wp_send_json_success($response);
    }

    public static function handle_continue_exam_save_progress(): void
    {
        if (!self::can_manage_exams()) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('cbt_exam_builder_state', 'nonce');

        $token = isset($_POST['token']) ? sanitize_key((string) wp_unslash($_POST['token'])) : '';
        $state = self::get_exam_save_progress_state_for_current_user($token);
        if (!is_array($state)) {
            wp_send_json_error(['message' => 'Sesi progress update exam tidak ditemukan atau sudah berakhir.'], 404);
        }

        $state = self::continue_exam_save_progress_state($state);
        if (is_wp_error($state)) {
            self::clear_exam_save_progress_state($token);
            wp_send_json_error(['message' => $state->get_error_message()], 500);
        }

        $response = self::build_exam_save_progress_payload($state);
        $response['token'] = $token;

        if (!empty($response['complete'])) {
            self::clear_exam_save_progress_state($token);
            wp_send_json_success($response);
        }

        $saved = set_transient(self::get_exam_save_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$saved) {
            self::clear_exam_save_progress_state($token);
            wp_send_json_error(['message' => 'Gagal menyimpan lanjutan progress update exam.'], 500);
        }

        wp_send_json_success($response);
    }

    /**
     * @param array<string,mixed> $request
     * @return array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string}
     */
    public static function get_exam_list_state_from_request(array $request): array
    {
        $status = isset($request['cbt_exam_status'])
            ? sanitize_key((string) wp_unslash((string) $request['cbt_exam_status']))
            : '';
        if (!in_array($status, ['draft', 'published', 'closed'], true)) {
            $status = '';
        }

        return [
            'per_page' => self::normalize_standard_list_per_page(
                isset($request['cbt_exam_per_page']) ? absint(wp_unslash((string) $request['cbt_exam_per_page'])) : 20
            ),
            'paged' => isset($request['cbt_exam_paged']) ? max(1, absint(wp_unslash((string) $request['cbt_exam_paged']))) : 1,
            'search' => isset($request['cbt_exam_search'])
                ? sanitize_text_field((string) wp_unslash((string) $request['cbt_exam_search']))
                : '',
            'status' => $status,
            'subject_id' => isset($request['cbt_exam_subject']) ? absint(wp_unslash((string) $request['cbt_exam_subject'])) : 0,
            'kelas' => isset($request['cbt_exam_kelas'])
                ? strtoupper(sanitize_text_field((string) wp_unslash((string) $request['cbt_exam_kelas'])))
                : '',
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $state
     * @return array<string,mixed>
     */
    public static function add_exam_list_state_args(array $args, array $state, bool $include_paged = true): array
    {
        $args['cbt_exam_per_page'] = self::normalize_standard_list_per_page((int) ($state['per_page'] ?? 20));
        if ($include_paged) {
            $args['cbt_exam_paged'] = max(1, (int) ($state['paged'] ?? 1));
        }
        if (($state['search'] ?? '') !== '') {
            $args['cbt_exam_search'] = (string) $state['search'];
        }
        if (($state['status'] ?? '') !== '') {
            $args['cbt_exam_status'] = (string) $state['status'];
        }
        if (!empty($state['subject_id'])) {
            $args['cbt_exam_subject'] = (int) $state['subject_id'];
        }
        if (($state['kelas'] ?? '') !== '') {
            $args['cbt_exam_kelas'] = (string) $state['kelas'];
        }

        return $args;
    }

    /**
     * @param array<string,mixed> $request
     * @return array{exam_id:int,exam_ids:array<int,int>}
     */
    private static function get_exam_snapshot_filter_state_from_request(array $request): array
    {
        $exam_ids = [];

        if (isset($request['cbt_exam_snapshot_exam_ids']) && is_array($request['cbt_exam_snapshot_exam_ids'])) {
            $exam_ids = array_map(
                'absint',
                array_map(
                    static function ($value): string {
                        return (string) wp_unslash((string) $value);
                    },
                    (array) $request['cbt_exam_snapshot_exam_ids']
                )
            );
        }

        if (isset($request['cbt_exam_snapshot_exam_id'])) {
            $exam_ids[] = absint(wp_unslash((string) $request['cbt_exam_snapshot_exam_id']));
        }

        $exam_ids = array_values(array_unique(array_filter($exam_ids, static function ($exam_id): bool {
            return (int) $exam_id > 0;
        })));

        return [
            'exam_id' => !empty($exam_ids) ? (int) $exam_ids[0] : 0,
            'exam_ids' => $exam_ids,
        ];
    }

    /**
     * @param array<int,int> $exam_ids
     * @return array<int,int>
     */
    private static function normalize_snapshot_exam_selection_for_tab(array $exam_ids, string $tab): array
    {
        $exam_ids = array_values(array_unique(array_filter(array_map('intval', $exam_ids))));
        if (self::sanitize_exam_snapshot_tab($tab) === self::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR && !empty($exam_ids)) {
            return [(int) $exam_ids[0]];
        }

        return $exam_ids;
    }

    /**
     * @param array<string,mixed> $args
     * @param array{exam_id:int,exam_ids?:array<int,int>} $state
     * @return array<string,mixed>
     */
    public static function add_exam_snapshot_filter_state_args(array $args, array $state): array
    {
        $exam_ids = array_values(array_filter(array_map('intval', (array) ($state['exam_ids'] ?? []))));
        if (empty($exam_ids) && !empty($state['exam_id'])) {
            $exam_ids[] = (int) $state['exam_id'];
        }

        if (!empty($exam_ids)) {
            $args['cbt_exam_snapshot_exam_ids'] = $exam_ids;
            if (count($exam_ids) === 1) {
                $args['cbt_exam_snapshot_exam_id'] = (int) $exam_ids[0];
            } else {
                unset($args['cbt_exam_snapshot_exam_id']);
            }
        } else {
            unset($args['cbt_exam_snapshot_exam_ids'], $args['cbt_exam_snapshot_exam_id']);
        }

        return $args;
    }

    /**
     * @param array<string,mixed> $request
     */
    private static function get_exam_readiness_page_from_request(array $request): int
    {
        return isset($request['cbt_exam_readiness_paged'])
            ? max(1, absint(wp_unslash((string) $request['cbt_exam_readiness_paged'])))
            : 1;
    }

    /**
     * @param array<string,mixed> $request
     * @param array<int,int> $selected_exam_ids
     * @return array<int,int>
     */
    private static function get_exam_readiness_pages_from_request(array $request, array $selected_exam_ids = []): array
    {
        $pages = [];

        foreach ($request as $key => $value) {
            if (!is_string($key) || !preg_match('/^cbt_exam_readiness_page_(\d+)$/', $key, $matches)) {
                continue;
            }

            $exam_id = isset($matches[1]) ? absint($matches[1]) : 0;
            $page = max(1, absint(wp_unslash((string) $value)));
            if ($exam_id <= 0 || $page <= 1) {
                continue;
            }

            $pages[$exam_id] = $page;
        }

        if (!empty($pages)) {
            return $pages;
        }

        $legacy_page = self::get_exam_readiness_page_from_request($request);
        if ($legacy_page <= 1) {
            return [];
        }

        $selected_exam_ids = array_values(array_filter(array_map('intval', $selected_exam_ids)));
        if (empty($selected_exam_ids)) {
            return [];
        }

        foreach ($selected_exam_ids as $exam_id) {
            if ($exam_id > 0) {
                $pages[$exam_id] = $legacy_page;
            }
        }

        return $pages;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function add_exam_readiness_page_args(array $args, $page_or_pages, int $legacy_page = 1): array
    {
        $pages = [];

        if (is_array($page_or_pages)) {
            foreach ($page_or_pages as $exam_id => $page) {
                $exam_id = absint($exam_id);
                $page = max(1, (int) $page);
                if ($exam_id <= 0 || $page <= 1) {
                    continue;
                }

                $pages[$exam_id] = $page;
            }
        } else {
            $legacy_page = max(1, (int) $page_or_pages);
        }

        foreach (array_keys($args) as $key) {
            if (is_string($key) && str_starts_with($key, 'cbt_exam_readiness_page_')) {
                unset($args[$key]);
            }
        }

        foreach ($pages as $exam_id => $page) {
            $args['cbt_exam_readiness_page_' . $exam_id] = $page;
        }

        $legacy_page = max(1, $legacy_page);
        if ($legacy_page > 1) {
            $args['cbt_exam_readiness_paged'] = $legacy_page;
        } else {
            unset($args['cbt_exam_readiness_paged']);
        }

        return $args;
    }

    /**
     * @param array<string,mixed> $request
     * @return array{search:string,kelas:string,ruang:string,status:string,paged:int,per_page:int}
     */
    private static function get_student_snapshot_filter_state_from_request(array $request): array
    {
        return [
            'search' => isset($request['cbt_student_snapshot_q'])
                ? sanitize_text_field((string) wp_unslash((string) $request['cbt_student_snapshot_q']))
                : '',
            'kelas' => isset($request['cbt_student_snapshot_kelas'])
                ? self::normalize_snapshot_student_meta_filter((string) wp_unslash((string) $request['cbt_student_snapshot_kelas']))
                : '',
            'ruang' => isset($request['cbt_student_snapshot_ruang'])
                ? self::normalize_snapshot_student_meta_filter((string) wp_unslash((string) $request['cbt_student_snapshot_ruang']))
                : '',
            'status' => isset($request['cbt_student_snapshot_status'])
                ? self::normalize_student_snapshot_status_filter((string) wp_unslash((string) $request['cbt_student_snapshot_status']))
                : '',
            'paged' => isset($request['cbt_student_snapshot_paged'])
                ? max(1, absint(wp_unslash((string) $request['cbt_student_snapshot_paged'])))
                : 1,
            'per_page' => self::STUDENT_SNAPSHOT_PER_PAGE,
        ];
    }

    /**
     * @param array<string,mixed> $args
     * @param array{search:string,kelas:string,ruang:string,status:string,paged:int,per_page:int} $state
     * @return array<string,mixed>
     */
    public static function add_student_snapshot_filter_state_args(array $args, array $state, bool $include_paged = true): array
    {
        if (($state['search'] ?? '') !== '') {
            $args['cbt_student_snapshot_q'] = (string) $state['search'];
        }
        if (($state['kelas'] ?? '') !== '') {
            $args['cbt_student_snapshot_kelas'] = (string) $state['kelas'];
        }
        if (($state['ruang'] ?? '') !== '') {
            $args['cbt_student_snapshot_ruang'] = (string) $state['ruang'];
        }
        if (($state['status'] ?? '') !== '') {
            $args['cbt_student_snapshot_status'] = (string) $state['status'];
        }
        if ($include_paged) {
            $args['cbt_student_snapshot_paged'] = max(1, (int) ($state['paged'] ?? 1));
        }

        return $args;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function add_exam_snapshot_tab_args(array $args, string $tab): array
    {
        $sanitized_tab = self::sanitize_exam_snapshot_tab($tab);
        if ($sanitized_tab === self::SNAPSHOT_TAB_PREFLIGHT) {
            unset($args['cbt_exam_snapshot_tab']);
        } else {
            $args['cbt_exam_snapshot_tab'] = $sanitized_tab;
        }

        return $args;
    }

    public static function is_exam_snapshot_exam_tab(string $tab): bool
    {
        return in_array(
            self::sanitize_exam_snapshot_tab($tab),
            [
                self::SNAPSHOT_TAB_PREFLIGHT,
                self::SNAPSHOT_TAB_QUESTION_MONITOR,
                self::SNAPSHOT_TAB_START_MONITOR,
                self::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR,
                self::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR,
            ],
            true
        );
    }

    public static function is_exam_snapshot_student_tab(string $tab): bool
    {
        return in_array(
            self::sanitize_exam_snapshot_tab($tab),
            [
                self::SNAPSHOT_TAB_EXAM_MONITOR,
                self::SNAPSHOT_TAB_PROFILE_MONITOR,
                self::SNAPSHOT_TAB_LOGIN_MONITOR,
            ],
            true
        );
    }

    /**
     * @param array{search:string,kelas:string,ruang:string,status:string,paged:int,per_page:int} $state
     * @return array{items:array<int,array<string,mixed>>,total:int,total_pages:int,page:int,per_page:int}
     */
    private static function build_snapshot_student_table_context(array $state, string $mode = self::SNAPSHOT_TAB_EXAM_MONITOR): array
    {
        $page = max(1, (int) ($state['paged'] ?? 1));
        $per_page = self::STUDENT_SNAPSHOT_PER_PAGE;
        $status_filter = self::normalize_student_snapshot_status_filter((string) ($state['status'] ?? ''));
        $negate_status_filter = $status_filter !== '' && str_starts_with($status_filter, '!');
        $status_filter_value = $negate_status_filter ? substr($status_filter, 1) : $status_filter;
        $users = self::get_filtered_snapshot_student_users($state);
        $rows = [];

        if ($status_filter_value === '') {
            $total = count($users);
            $total_pages = max(1, (int) ceil($total / $per_page));
            if ($total > 0 && $page > $total_pages) {
                $page = $total_pages;
            }

            $page_users = array_slice($users, ($page - 1) * $per_page, $per_page);
            foreach ($page_users as $user) {
                if ($user instanceof WP_User) {
                    $rows[] = self::build_snapshot_student_row($user, $mode);
                }
            }
        } else {
            $filtered_rows = [];
            foreach ($users as $user) {
                if (!$user instanceof WP_User) {
                    continue;
                }

                $row = self::build_snapshot_student_row($user, $mode);
                $row_status_filter_value = self::get_student_snapshot_row_status_filter_slug($row, $mode);
                $is_matching_status = $row_status_filter_value === $status_filter_value;
                if ((!$negate_status_filter && !$is_matching_status) || ($negate_status_filter && $is_matching_status)) {
                    continue;
                }

                $filtered_rows[] = $row;
            }

            $total = count($filtered_rows);
            $total_pages = max(1, (int) ceil($total / $per_page));
            if ($total > 0 && $page > $total_pages) {
                $page = $total_pages;
            } elseif ($total === 0) {
                $page = 1;
            }

            $rows = $total > 0
                ? array_slice($filtered_rows, ($page - 1) * $per_page, $per_page)
                : [];
        }

        return [
            'items' => $rows,
            'total' => $total,
            'total_pages' => $total_pages,
            'page' => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    private static function get_student_snapshot_status_filter_options(string $mode): array
    {
        $normalized_mode = self::sanitize_exam_snapshot_tab($mode);
        $options = [];

        if ($normalized_mode === self::SNAPSHOT_TAB_EXAM_MONITOR) {
            $options = [
                ['value' => 'ready', 'label' => 'READY'],
                ['value' => 'auto_warm', 'label' => 'AUTO-WARM'],
                ['value' => 'queued_rewarm', 'label' => 'QUEUED REWARM'],
                ['value' => 'miss', 'label' => 'MISS'],
                ['value' => 'invalid', 'label' => 'INVALID'],
                ['value' => 'unavailable', 'label' => 'UNAVAILABLE'],
            ];
        } elseif ($normalized_mode === self::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR) {
            $options = [
                ['value' => 'ready', 'label' => 'READY'],
                ['value' => 'session_miss', 'label' => 'SESSION MISS'],
                ['value' => 'contract_miss', 'label' => 'CONTRACT MISS'],
                ['value' => 'runtime_miss', 'label' => 'RUNTIME MISS'],
                ['value' => 'stale', 'label' => 'STALE LAST SEEN'],
                ['value' => 'low_remaining', 'label' => 'LOW REMAINING'],
            ];
        } elseif (in_array($normalized_mode, [self::SNAPSHOT_TAB_PROFILE_MONITOR, self::SNAPSHOT_TAB_LOGIN_MONITOR], true)) {
            $options = [
                ['value' => 'ready', 'label' => 'READY'],
                ['value' => 'warning', 'label' => 'WARNING'],
                ['value' => 'miss', 'label' => 'MISS'],
                ['value' => 'invalid', 'label' => 'INVALID'],
                ['value' => 'unavailable', 'label' => 'UNAVAILABLE'],
                ['value' => 'idle', 'label' => 'IDLE'],
            ];
        }

        if (empty($options)) {
            return [];
        }

        $negative_options = [];
        foreach ($options as $option) {
            $option_value = (string) ($option['value'] ?? '');
            $option_label = (string) ($option['label'] ?? $option_value);
            if ($option_value === '') {
                continue;
            }

            $negative_options[] = [
                'value' => '!' . $option_value,
                'label' => '! ' . $option_label,
            ];
        }

        return array_merge($options, $negative_options);
    }

    private static function normalize_student_snapshot_status_filter(string $value): string
    {
        $value = trim($value);
        $is_negated = str_starts_with($value, '!');
        if ($is_negated) {
            $value = ltrim(substr($value, 1));
        }

        $value = strtolower($value);
        $value = str_replace([' ', '-'], '_', $value);
        $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
        if ($value === '') {
            return '';
        }

        return $is_negated ? '!' . $value : $value;
    }

    private static function get_student_snapshot_row_status_filter_slug(array $row, string $mode): string
    {
        $normalized_mode = self::sanitize_exam_snapshot_tab($mode);

        if ($normalized_mode === self::SNAPSHOT_TAB_EXAM_MONITOR) {
            return self::normalize_student_snapshot_status_filter((string) ($row['availability_status_label'] ?? 'miss'));
        }

        if ($normalized_mode === self::SNAPSHOT_TAB_PROFILE_MONITOR) {
            return self::normalize_student_snapshot_status_filter((string) ($row['profile_status_label'] ?? 'miss'));
        }

        if ($normalized_mode === self::SNAPSHOT_TAB_LOGIN_MONITOR) {
            return self::normalize_student_snapshot_status_filter((string) ($row['login_status_label'] ?? 'miss'));
        }

        return '';
    }

    /**
     * @return WP_User[]
     */
    private static function get_filtered_snapshot_student_users(array $state = []): array
    {
        $search = trim((string) ($state['search'] ?? ''));
        $kelas = self::normalize_snapshot_student_meta_filter((string) ($state['kelas'] ?? ''));
        $ruang = self::normalize_snapshot_student_meta_filter((string) ($state['ruang'] ?? ''));
        $users = get_users(['number' => 0]);
        if (!is_array($users)) {
            return [];
        }

        $filtered = [];
        foreach ($users as $user) {
            if (!$user instanceof WP_User || !self::is_snapshot_student_user($user)) {
                continue;
            }

            $user_id = (int) $user->ID;
            $kode_kelas = self::normalize_snapshot_student_meta_filter((string) get_user_meta($user_id, 'kode_kelas', true));
            $kode_ruang = self::normalize_snapshot_student_meta_filter((string) get_user_meta($user_id, 'kode_ruang', true));
            if ($kelas !== '' && $kode_kelas !== $kelas) {
                continue;
            }
            if ($ruang !== '' && $kode_ruang !== $ruang) {
                continue;
            }
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    (string) $user->display_name,
                    (string) $user->user_login,
                    (string) $user->user_email,
                    $kode_kelas,
                    $kode_ruang,
                ]));
                if (strpos($haystack, strtolower($search)) === false) {
                    continue;
                }
            }

            $filtered[] = $user;
        }

        usort($filtered, static function (WP_User $left, WP_User $right): int {
            $left_label = trim((string) ($left->display_name ?: $left->user_login));
            $right_label = trim((string) ($right->display_name ?: $right->user_login));
            $compare = strnatcasecmp($left_label, $right_label);
            if ($compare !== 0) {
                return $compare;
            }

            return ((int) $left->ID <=> (int) $right->ID);
        });

        return $filtered;
    }

    /**
     * @param array<int,int> $selected_exam_ids
     * @return array{kelas:string[],ruang:string[]}
     */
    private static function build_session_runtime_filter_options(array $selected_exam_ids = []): array
    {
        global $wpdb;

        $selected_exam_ids = array_values(array_filter(array_map('intval', $selected_exam_ids)));
        if (empty($selected_exam_ids)) {
            return ['kelas' => [], 'ruang' => []];
        }

        $exam_id = (int) $selected_exam_ids[0];
        if ($exam_id <= 0) {
            return ['kelas' => [], 'ruang' => []];
        }

        $attempt_rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT student_id
                 FROM {$wpdb->prefix}cbt_attempts
                 WHERE exam_id = %d
                   AND status = 'in_progress'
                 ORDER BY id DESC",
                $exam_id
            ),
            ARRAY_A
        );

        $student_ids = array_values(array_unique(array_filter(array_map('intval', wp_list_pluck($attempt_rows, 'student_id')))));
        if (empty($student_ids)) {
            return ['kelas' => [], 'ruang' => []];
        }

        if (function_exists('cache_users')) {
            cache_users($student_ids);
        }
        if (function_exists('update_meta_cache')) {
            update_meta_cache('user', $student_ids);
        }

        $kelas_options = [];
        $ruang_options = [];
        foreach ($student_ids as $student_id) {
            $kode_kelas = self::normalize_snapshot_student_meta_filter((string) get_user_meta($student_id, 'kode_kelas', true));
            $kode_ruang = self::normalize_snapshot_student_meta_filter((string) get_user_meta($student_id, 'kode_ruang', true));
            if ($kode_kelas !== '') {
                $kelas_options[$kode_kelas] = $kode_kelas;
            }
            if ($kode_ruang !== '') {
                $ruang_options[$kode_ruang] = $kode_ruang;
            }
        }

        natcasesort($kelas_options);
        natcasesort($ruang_options);

        return [
            'kelas' => array_values($kelas_options),
            'ruang' => array_values($ruang_options),
        ];
    }

    /**
     * @return array{kelas:string[],ruang:string[]}
     */
    private static function build_snapshot_student_filter_options(): array
    {
        $users = self::get_filtered_snapshot_student_users();
        $kelas_options = [];
        $ruang_options = [];

        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            $kode_kelas = self::normalize_snapshot_student_meta_filter((string) get_user_meta($user_id, 'kode_kelas', true));
            $kode_ruang = self::normalize_snapshot_student_meta_filter((string) get_user_meta($user_id, 'kode_ruang', true));
            if ($kode_kelas !== '') {
                $kelas_options[$kode_kelas] = $kode_kelas;
            }
            if ($kode_ruang !== '') {
                $ruang_options[$kode_ruang] = $kode_ruang;
            }
        }

        natcasesort($kelas_options);
        natcasesort($ruang_options);

        return [
            'kelas' => array_values($kelas_options),
            'ruang' => array_values($ruang_options),
        ];
    }

    private static function normalize_snapshot_student_meta_filter(string $value): string
    {
        return strtoupper(trim(sanitize_text_field($value)));
    }

    private static function is_snapshot_student_user(WP_User $user): bool
    {
        $roles = isset($user->roles) && is_array($user->roles) ? array_map('strtolower', $user->roles) : [];

        foreach (['student', 'siswa', 'siswa_cbt', 'subscriber'] as $student_role) {
            if (in_array($student_role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_snapshot_student_row(WP_User $user, string $mode = self::SNAPSHOT_TAB_EXAM_MONITOR): array
    {
        $user_id = (int) $user->ID;
        $kode_kelas = self::normalize_snapshot_student_meta_filter((string) get_user_meta($user_id, 'kode_kelas', true));
        $kode_ruang = self::normalize_snapshot_student_meta_filter((string) get_user_meta($user_id, 'kode_ruang', true));
        $availability = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
        if ($mode === self::SNAPSHOT_TAB_EXAM_MONITOR) {
            $availability = self::maybe_prepare_admin_availability_snapshot($user_id, $availability);
        }
        $profile = CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id);
        $login = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
        $availability_status_meta = self::build_availability_snapshot_status_meta($availability, $user_id);
        $profile_status_meta = self::build_snapshot_status_meta((string) ($profile['snapshot_status'] ?? 'miss'));
        $login_status_meta = self::build_snapshot_status_meta((string) ($login['snapshot_status'] ?? 'miss'));

        return [
            'user_id' => $user_id,
            'display_name' => trim((string) ($user->display_name ?: $user->user_login)),
            'user_login' => (string) $user->user_login,
            'user_email' => (string) $user->user_email,
            'kode_kelas' => $kode_kelas,
            'kode_ruang' => $kode_ruang,
            'availability' => $availability,
            'availability_status_label' => $availability_status_meta['label'],
            'availability_status_tone' => $availability_status_meta['tone'],
            'profile' => $profile,
            'profile_status_label' => $profile_status_meta['label'],
            'profile_status_tone' => $profile_status_meta['tone'],
            'login' => $login,
            'login_status_label' => $login_status_meta['label'],
            'login_status_tone' => $login_status_meta['tone'],
        ];
    }

    /**
     * @param array<string,mixed> $availability
     * @return array{label:string,tone:string}
     */
    private static function build_availability_snapshot_status_meta(array $availability, int $user_id): array
    {
        $status = (string) ($availability['snapshot_status'] ?? 'miss');
        $source = sanitize_key((string) ($availability['snapshot_source'] ?? ''));
        $repair_status = sanitize_key((string) ($availability['repair_status'] ?? ''));
        if ($repair_status === 'queued_rewarm') {
            return ['label' => 'QUEUED REWARM', 'tone' => 'warning'];
        }

        if (
            $source === 'prepared'
            && sanitize_key($status) === 'ready'
            && class_exists('CBT_Exam_Availability_Auto_Warm_Service')
            && CBT_Exam_Availability_Auto_Warm_Service::is_active_for_student($user_id)
        ) {
            return ['label' => 'AUTO-WARM', 'tone' => 'success'];
        }

        return self::build_snapshot_status_meta($status);
    }

    /**
     * @param array<string,mixed> $availability
     * @return array<string,mixed>
     */
    private static function maybe_prepare_admin_availability_snapshot(int $user_id, array $availability): array
    {
        $snapshot_status = sanitize_key((string) ($availability['snapshot_status'] ?? 'miss'));
        $miss_reason = sanitize_key((string) ($availability['snapshot_miss_reason'] ?? ''));
        if ($snapshot_status !== 'miss') {
            return $availability;
        }

        if ($miss_reason === 'minute_rollover') {
            $repair = CBT_Exam_Availability_Cache::maybe_auto_heal_minute_rollover($user_id);
            if (!empty($repair['success'])) {
                return CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
            }
        }

        if (
            $miss_reason === 'version_changed'
            && class_exists('CBT_Exam_Availability_Auto_Warm_Service')
            && method_exists('CBT_Exam_Availability_Auto_Warm_Service', 'enqueue_rewarm_users')
        ) {
            CBT_Exam_Availability_Auto_Warm_Service::enqueue_rewarm_users([$user_id], 'version_changed', 'admin');
            return CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
        }

        return $availability;
    }

    /**
     * @return array{label:string,tone:string}
     */
    private static function build_snapshot_status_meta(string $status): array
    {
        switch (sanitize_key($status)) {
            case 'ready':
                return ['label' => 'READY', 'tone' => 'success'];
            case 'warning':
                return ['label' => 'WARNING', 'tone' => 'warning'];
            case 'invalid':
                return ['label' => 'INVALID', 'tone' => 'error'];
            case 'unavailable':
                return ['label' => 'UNAVAILABLE', 'tone' => 'error'];
            case 'idle':
                return ['label' => 'IDLE', 'tone' => 'warning'];
            case 'miss':
            default:
                return ['label' => 'MISS', 'tone' => 'warning'];
        }
    }

    /**
     * @param array<string,mixed> $request
     */
    private static function get_exam_snapshot_tab_from_request(array $request): string
    {
        return self::sanitize_exam_snapshot_tab(
            isset($request['cbt_exam_snapshot_tab'])
                ? (string) wp_unslash((string) $request['cbt_exam_snapshot_tab'])
                : ''
        );
    }

    private static function sanitize_exam_snapshot_tab(string $tab): string
    {
        $tab = sanitize_key($tab);

        if ($tab === self::SNAPSHOT_TAB_QUESTIONS || $tab === '') {
            return self::SNAPSHOT_TAB_PREFLIGHT;
        }

        if ($tab === self::SNAPSHOT_TAB_STUDENTS) {
            return self::SNAPSHOT_TAB_EXAM_MONITOR;
        }

        if (
            in_array(
                $tab,
                [
                    self::SNAPSHOT_TAB_PREFLIGHT,
                    self::SNAPSHOT_TAB_QUESTION_MONITOR,
                    self::SNAPSHOT_TAB_START_MONITOR,
                    self::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR,
                    self::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR,
                    self::SNAPSHOT_TAB_EXAM_MONITOR,
                    self::SNAPSHOT_TAB_PROFILE_MONITOR,
                    self::SNAPSHOT_TAB_LOGIN_MONITOR,
                ],
                true
            )
        ) {
            return $tab;
        }

        return self::SNAPSHOT_TAB_PREFLIGHT;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function build_filtered_exam_snapshot_rows(
        bool $is_admin_scope,
        int $current_user_id,
        array $preview_pages = [],
        array $selected_exam_ids = [],
        array $readiness_pages = [],
        int $legacy_readiness_page = 1,
        string $tab = self::SNAPSHOT_TAB_PREFLIGHT,
        array $student_snapshot_filter_state = []
    ): array
    {
        $selected_exam_ids = array_values(array_filter(array_map('intval', $selected_exam_ids)));
        if (empty($selected_exam_ids)) {
            return [];
        }

        $rows = self::get_filtered_exam_snapshot_exams($is_admin_scope, $current_user_id, $selected_exam_ids);
        $snapshot_rows = [];

        foreach ($rows as $row) {
            $exam_id = (int) ($row['id'] ?? 0);
            $row_readiness_page = max(1, (int) ($readiness_pages[$exam_id] ?? $legacy_readiness_page));
            $snapshot_rows[] = self::build_exam_snapshot_row($row, $preview_pages, $row_readiness_page, $tab, $student_snapshot_filter_state);
        }

        return $snapshot_rows;
    }

    /**
     * @param array<int,int> $selected_exam_ids
     * @return array<string,mixed>
     */
    private static function build_bulk_preflight_context(bool $is_admin_scope, int $current_user_id, array $selected_exam_ids): array
    {
        $selected_exam_ids = array_values(array_filter(array_map('intval', $selected_exam_ids)));
        $rows = self::get_filtered_exam_snapshot_exams($is_admin_scope, $current_user_id, $selected_exam_ids);
        $row_map = [];

        foreach ($rows as $row) {
            $exam_id = (int) ($row['id'] ?? 0);
            if ($exam_id > 0) {
                $row_map[$exam_id] = $row;
            }
        }

        $compact_rows = [];
        $status_counts = [
            'queued' => 0,
            'completed' => 0,
            'completed_with_warnings' => 0,
            'failed' => 0,
        ];

        foreach ($selected_exam_ids as $exam_id) {
            if (!isset($row_map[$exam_id])) {
                continue;
            }

            $compact_row = self::build_bulk_preflight_row($row_map[$exam_id]);
            $compact_rows[] = $compact_row;

            $status_key = sanitize_key((string) ($compact_row['preflight_status'] ?? 'inactive'));
            if (isset($status_counts[$status_key])) {
                $status_counts[$status_key]++;
            }
        }

        $runner = CBT_Exam_Preflight_Service::get_global_runner_state();

        return [
            'selected_exam_ids' => $selected_exam_ids,
            'selected_exam_total' => count($selected_exam_ids),
            'queued_exam_total' => $status_counts['queued'],
            'active_exam_id' => (int) ($runner['active_exam_id'] ?? 0),
            'completed_count' => $status_counts['completed'],
            'completed_with_warnings_count' => $status_counts['completed_with_warnings'],
            'failed_count' => $status_counts['failed'],
            'can_start_bulk' => count($selected_exam_ids) >= 2 && count($selected_exam_ids) <= 10 && count($compact_rows) === count($selected_exam_ids),
            'limit_max_exams' => 10,
            'rows' => $compact_rows,
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function build_bulk_preflight_row(array $exam_row): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $question_ready = false;
        $start_ready = false;
        $question_count = 0;
        $start_count = 0;
        $submission_question_count = 0;
        $submission_ready_count = 0;
        $submission_invalid_count = 0;

        if ($exam_id > 0 && class_exists('CBT_Exam_Question_Delivery_Cache')) {
            $question_diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics($exam_id, 1, 1);
            $question_ready = sanitize_key((string) ($question_diagnostics['snapshot_status'] ?? '')) === 'ready';
            $question_count = max(0, (int) ($question_diagnostics['snapshot_item_count'] ?? 0));
        }

        if ($exam_id > 0 && class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
            $start_diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id);
            $start_ready = sanitize_key((string) ($start_diagnostics['snapshot_status'] ?? '')) === 'ready';
            $start_count = max(0, (int) ($start_diagnostics['snapshot_item_count'] ?? 0));
        }

        if ($exam_id > 0 && class_exists('CBT_Question_Submission_Context_Cache')) {
            $submission_diagnostics = CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics($exam_id);
            $submission_question_count = max(0, (int) ($submission_diagnostics['question_count'] ?? 0));
            $submission_ready_count = max(0, (int) ($submission_diagnostics['ready_count'] ?? 0));
            $submission_invalid_count = max(0, (int) ($submission_diagnostics['invalid_count'] ?? 0));
        }

        $preflight = CBT_Exam_Preflight_Service::get_exam_panel_context($exam_row, $question_ready, $start_ready);

        return [
            'exam_id' => $exam_id,
            'title' => trim((string) ($exam_row['title'] ?? '')) !== '' ? (string) ($exam_row['title'] ?? '') : ('Exam #' . $exam_id),
            'subject_name' => trim((string) ($exam_row['subject_name'] ?? '')),
            'status' => trim((string) ($exam_row['status'] ?? '')),
            'queue_position' => max(0, (int) ($preflight['queue_position'] ?? 0)),
            'preflight_status' => sanitize_key((string) ($preflight['status'] ?? 'inactive')),
            'preflight_status_label' => (string) ($preflight['status_label'] ?? 'NONAKTIF'),
            'preflight_status_tone' => (string) ($preflight['status_tone'] ?? 'warning'),
            'target_student_count' => max(0, (int) ($preflight['target_student_count'] ?? 0)),
            'last_message' => trim((string) ($preflight['last_message'] ?? '')),
            'started_at' => trim((string) ($preflight['started_at'] ?? '')),
            'finished_at' => trim((string) ($preflight['finished_at'] ?? '')),
            'last_tick_at' => trim((string) ($preflight['last_tick_at'] ?? '')),
            'stage_question_label' => (string) ($preflight['stage_question_label'] ?? 'BELUM'),
            'stage_question_tone' => (string) ($preflight['stage_question_tone'] ?? 'warning'),
            'stage_start_snapshot_label' => (string) ($preflight['stage_start_snapshot_label'] ?? 'BELUM'),
            'stage_start_snapshot_tone' => (string) ($preflight['stage_start_snapshot_tone'] ?? 'warning'),
            'stage_submission_context_label' => (string) ($preflight['stage_submission_context_label'] ?? 'BELUM'),
            'stage_submission_context_tone' => (string) ($preflight['stage_submission_context_tone'] ?? 'warning'),
            'stage_profiles_label' => (string) ($preflight['stage_profiles_label'] ?? 'BELUM'),
            'stage_profiles_tone' => (string) ($preflight['stage_profiles_tone'] ?? 'warning'),
            'stage_login_snapshot_label' => (string) ($preflight['stage_login_snapshot_label'] ?? 'BELUM'),
            'stage_login_snapshot_tone' => (string) ($preflight['stage_login_snapshot_tone'] ?? 'warning'),
            'stage_auto_warm_label' => (string) ($preflight['stage_auto_warm_label'] ?? 'BELUM'),
            'stage_auto_warm_tone' => (string) ($preflight['stage_auto_warm_tone'] ?? 'warning'),
            'question_stage_summary' => 'Total ' . $question_count . ' soal',
            'start_stage_summary' => 'Total ' . $start_count . ' item',
            'submission_stage_summary' => 'Siap ' . $submission_ready_count . '/' . $submission_question_count . ' · Invalid ' . $submission_invalid_count,
            'profiles_stage_summary' => 'Siap ' . max(0, (int) ($preflight['profile_success_count'] ?? 0)) . '/' . max(0, (int) ($preflight['target_student_count'] ?? 0)),
            'login_stage_summary' => 'Siap ' . max(0, (int) ($preflight['login_snapshot_ready_count'] ?? 0)) . '/' . max(0, (int) ($preflight['target_student_count'] ?? 0)),
            'availability_stage_summary' => 'Siap ' . max(0, (int) ($preflight['availability_ready_count'] ?? 0)) . '/' . max(0, (int) ($preflight['target_student_count'] ?? 0)),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function get_filtered_exam_snapshot_exams(bool $is_admin_scope, int $current_user_id, array $selected_exam_ids = []): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $subject_table = $wpdb->prefix . 'cbt_subjects';

        $where_parts = [
            $wpdb->prepare('e.title NOT LIKE %s', 'Bank Soal - %'),
        ];
        $where_params = [];

        if (!$is_admin_scope) {
            $where_parts[] = 'e.created_by = %d';
            $where_params[] = $current_user_id;
        }

        $selected_exam_ids = array_values(array_filter(array_map('intval', $selected_exam_ids)));
        if (!empty($selected_exam_ids)) {
            $placeholders = implode(', ', array_fill(0, count($selected_exam_ids), '%d'));
            $where_parts[] = 'e.id IN (' . $placeholders . ')';
            foreach ($selected_exam_ids as $selected_exam_id) {
                $where_params[] = $selected_exam_id;
            }
        }

        $where_sql = ' WHERE ' . implode(' AND ', $where_parts);
        $sql = "SELECT e.id, e.title, e.status, e.target_kelas, s.name AS subject_name,
                    e.duration_minutes, e.show_student_result, e.enable_calculator, e.starts_at, e.ends_at
             FROM {$exam_table} e
             LEFT JOIN {$subject_table} s ON s.id = e.subject_id
             {$where_sql}
             ORDER BY e.id DESC";

        $prepared = !empty($where_params) ? $wpdb->prepare($sql, ...$where_params) : $sql;
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_snapshot_exam_row_by_id(int $exam_id, bool $is_admin_scope, int $current_user_id): ?array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return null;
        }

        $rows = self::get_filtered_exam_snapshot_exams($is_admin_scope, $current_user_id, [$exam_id]);
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $exam_id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<int,array{id:int,title:string,subject_name:string,status:string,target_kelas_list:array<int,string>}>
     */
    private static function build_exam_snapshot_exam_options(bool $is_admin_scope, int $current_user_id): array
    {
        $rows = self::get_filtered_exam_snapshot_exams($is_admin_scope, $current_user_id, []);
        $options = [];

        foreach ($rows as $row) {
            $exam_id = (int) ($row['id'] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $options[] = [
                'id' => $exam_id,
                'title' => $title !== '' ? $title : ('Exam #' . $exam_id),
                'subject_name' => trim((string) ($row['subject_name'] ?? '')),
                'status' => trim((string) ($row['status'] ?? '')),
                'target_kelas_list' => self::split_target_kelas_csv((string) ($row['target_kelas'] ?? '')),
            ];
        }

        return $options;
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function build_exam_snapshot_row(
        array $exam_row,
        array $preview_pages = [],
        int $readiness_page = 1,
        string $tab = self::SNAPSHOT_TAB_PREFLIGHT,
        array $student_snapshot_filter_state = []
    ): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $normalized_tab = self::sanitize_exam_snapshot_tab($tab);
        $is_preflight_tab = $normalized_tab === self::SNAPSHOT_TAB_PREFLIGHT;
        $preview_page = max(1, (int) ($preview_pages[$exam_id] ?? 1));
        $empty_auto_warm_context = [
            'status' => 'inactive',
            'status_label' => 'NONAKTIF',
            'status_tone' => 'warning',
            'last_message' => '',
        ];
        $empty_preflight_context = [
            'status' => 'inactive',
            'status_label' => 'NONAKTIF',
            'status_tone' => 'warning',
            'last_message' => '',
        ];
        $empty_readiness_context = [
            'overall_status' => 'idle',
            'overall_label' => 'BELUM DIPERIKSA',
            'overall_tone' => 'warning',
            'blockers' => [],
            'warnings' => [],
            'target_kelas' => [],
            'target_student_count' => 0,
            'profile_ready_count' => 0,
            'profile_missing_count' => 0,
            'availability_ready_count' => 0,
            'availability_auto_warm_count' => 0,
            'availability_missing_count' => 0,
            'question_snapshot_ready' => false,
            'start_snapshot_ready' => false,
            'auto_warm_status' => 'inactive',
            'token_enabled' => false,
            'token_frontend_auto_apply' => false,
            'token_label' => 'OFF',
            'starts_at' => '',
            'ends_at' => '',
            'schedule_label' => '-',
            'duration_minutes' => 0,
            'show_student_result' => 0,
            'enable_calculator' => 0,
            'problem_students' => [],
            'problem_total' => 0,
            'problem_page' => max(1, $readiness_page),
            'problem_total_pages' => 1,
        ];
        $auto_warm_context = $is_preflight_tab
            ? CBT_Exam_Availability_Auto_Warm_Service::get_exam_panel_context($exam_row)
            : $empty_auto_warm_context;
        $preflight_context = $is_preflight_tab
            ? CBT_Exam_Preflight_Service::get_exam_panel_context($exam_row)
            : $empty_preflight_context;
        $session_runtime_context = $normalized_tab === self::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR
            ? self::build_session_runtime_context($exam_row, $student_snapshot_filter_state)
            : [
                'attempt_total' => 0,
                'attempt_total_overall' => 0,
                'visible_count' => 0,
                'rows_total' => 0,
                'rows_total_pages' => 1,
                'rows_current_page' => 1,
                'rows_per_page' => self::STUDENT_SNAPSHOT_PER_PAGE,
                'filters_applied' => false,
                'empty_message' => '',
                'rows' => [],
            ];
        $fallback = [
            'exam_id' => $exam_id,
            'title' => (string) ($exam_row['title'] ?? ''),
            'subject_name' => (string) ($exam_row['subject_name'] ?? ''),
            'status' => (string) ($exam_row['status'] ?? ''),
            'snapshot_status' => 'unavailable',
            'snapshot_status_label' => 'UNAVAILABLE',
            'snapshot_status_tone' => 'error',
            'snapshot_message' => 'Helper snapshot soal belum tersedia.',
            'snapshot_valid' => false,
            'snapshot_exists' => false,
            'revision_meta' => [
                'exam_id' => $exam_id,
                'version' => 1,
                'invalidated_at' => '',
                'signature' => '',
            ],
            'snapshot_item_count' => 0,
            'snapshot_payload_bytes' => 0,
            'snapshot_ttl_seconds' => -2,
            'start_snapshot_status' => 'unavailable',
            'start_snapshot_status_label' => 'UNAVAILABLE',
            'start_snapshot_status_tone' => 'error',
            'start_snapshot_message' => 'Helper start snapshot belum tersedia.',
            'start_snapshot_valid' => false,
            'start_snapshot_exists' => false,
            'start_snapshot_item_count' => 0,
            'start_snapshot_payload_bytes' => 0,
            'start_snapshot_ttl_seconds' => -2,
            'start_snapshot_storage_key' => '',
            'start_snapshot_redis_available' => false,
            'start_snapshot_redis_error' => '',
            'start_snapshot_redis_host' => '',
            'start_snapshot_redis_database' => 0,
            'start_snapshot_revision_meta' => [
                'exam_id' => $exam_id,
                'version' => 1,
                'invalidated_at' => '',
                'signature' => '',
            ],
            'submission_context' => [
                'exam_id' => $exam_id,
                'redis_available' => false,
                'redis_error' => '',
                'redis_host' => '',
                'redis_database' => 0,
                'question_count' => 0,
                'ready_count' => 0,
                'missing_count' => 0,
                'invalid_count' => 0,
                'payload_bytes_total' => 0,
                'preview_items' => [],
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_message' => 'Helper submission context belum tersedia.',
            ],
            'submission_context_status' => 'unavailable',
            'submission_context_status_label' => 'UNAVAILABLE',
            'submission_context_status_tone' => 'error',
            'preview_current_page' => $preview_page,
            'preview_total_pages' => 1,
            'preview_per_page' => self::SNAPSHOT_PREVIEW_PER_PAGE,
            'preview_is_expanded' => $preview_page > 1,
            'storage_key' => '',
            'redis_available' => false,
            'redis_error' => '',
            'redis_host' => '',
            'redis_database' => 0,
            'preview_question_ids' => [],
            'preview_items' => [],
            'auto_warm' => $auto_warm_context,
            'preflight' => $preflight_context,
            'readiness' => $empty_readiness_context,
            'session_runtime' => $session_runtime_context,
        ];

        if (
            $exam_id <= 0
            || !class_exists('CBT_Exam_Question_Delivery_Cache')
            || !class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')
        ) {
            return $fallback;
        }

        $diagnostics = CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics(
            $exam_id,
            $preview_page,
            self::SNAPSHOT_PREVIEW_PER_PAGE
        );
        $status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? 'unavailable'));
        $tone = 'warning';
        if ($status === 'ready') {
            $tone = 'success';
        } elseif ($status === 'invalid' || $status === 'unavailable') {
            $tone = 'error';
        }
        $start_diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id);
        $start_status = sanitize_key((string) ($start_diagnostics['snapshot_status'] ?? 'unavailable'));
        $start_tone = 'warning';
        if ($start_status === 'ready') {
            $start_tone = 'success';
        } elseif ($start_status === 'invalid' || $start_status === 'unavailable') {
            $start_tone = 'error';
        }
        $submission_context_diagnostics = class_exists('CBT_Question_Submission_Context_Cache')
            ? CBT_Question_Submission_Context_Cache::get_exam_snapshot_diagnostics($exam_id)
            : $fallback['submission_context'];
        $submission_context_status = sanitize_key((string) ($submission_context_diagnostics['snapshot_status'] ?? 'unavailable'));
        $submission_context_tone = 'warning';
        if ($submission_context_status === 'ready') {
            $submission_context_tone = 'success';
        } elseif ($submission_context_status === 'invalid' || $submission_context_status === 'unavailable') {
            $submission_context_tone = 'error';
        }
        $resolved_preflight_context = $is_preflight_tab
            ? CBT_Exam_Preflight_Service::get_exam_panel_context($exam_row, $status === 'ready', $start_status === 'ready')
            : $empty_preflight_context;
        $resolved_readiness_context = $is_preflight_tab
            ? self::build_exam_readiness_context($exam_row, $status === 'ready', $start_status === 'ready', $auto_warm_context, $readiness_page)
            : $empty_readiness_context;

        return array_merge($fallback, $diagnostics, [
            'exam_id' => $exam_id,
            'title' => (string) ($exam_row['title'] ?? ''),
            'subject_name' => (string) ($exam_row['subject_name'] ?? ''),
            'status' => (string) ($exam_row['status'] ?? ''),
            'target_kelas' => (string) ($exam_row['target_kelas'] ?? ''),
            'snapshot_status' => $status,
            'snapshot_status_label' => strtoupper($status),
            'snapshot_status_tone' => $tone,
            'preview_is_expanded' => ((int) ($diagnostics['preview_current_page'] ?? 1) > 1),
            'snapshot_message' => (string) ($diagnostics['snapshot_message'] ?? $fallback['snapshot_message']),
            'auto_warm' => $auto_warm_context,
            'start_snapshot_status' => $start_status,
            'start_snapshot_status_label' => strtoupper($start_status),
            'start_snapshot_status_tone' => $start_tone,
            'start_snapshot_message' => (string) ($start_diagnostics['snapshot_message'] ?? $fallback['start_snapshot_message']),
            'start_snapshot_valid' => !empty($start_diagnostics['snapshot_valid']),
            'start_snapshot_exists' => !empty($start_diagnostics['snapshot_exists']),
            'start_snapshot_item_count' => (int) ($start_diagnostics['snapshot_item_count'] ?? 0),
            'start_snapshot_payload_bytes' => (int) ($start_diagnostics['snapshot_payload_bytes'] ?? 0),
            'start_snapshot_ttl_seconds' => (int) ($start_diagnostics['snapshot_ttl_seconds'] ?? -2),
            'start_snapshot_storage_key' => (string) ($start_diagnostics['storage_key'] ?? ''),
            'start_snapshot_redis_available' => !empty($start_diagnostics['redis_available']),
            'start_snapshot_redis_error' => (string) ($start_diagnostics['redis_error'] ?? ''),
            'start_snapshot_redis_host' => (string) ($start_diagnostics['redis_host'] ?? ''),
            'start_snapshot_redis_database' => (int) ($start_diagnostics['redis_database'] ?? 0),
            'start_snapshot_revision_meta' => is_array($start_diagnostics['revision_meta'] ?? null)
                ? $start_diagnostics['revision_meta']
                : $fallback['start_snapshot_revision_meta'],
            'submission_context' => $submission_context_diagnostics,
            'submission_context_status' => $submission_context_status,
            'submission_context_status_label' => strtoupper($submission_context_status),
            'submission_context_status_tone' => $submission_context_tone,
            'preflight' => $resolved_preflight_context,
            'readiness' => $resolved_readiness_context,
            'session_runtime' => $session_runtime_context,
        ]);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @param array<string,mixed> $auto_warm_context
     * @return array<string,mixed>
     */
    private static function build_exam_readiness_context(array $exam_row, bool $question_snapshot_ready, bool $start_snapshot_ready, array $auto_warm_context, int $page = 1): array
    {
        $target_kelas = self::split_target_kelas_csv((string) ($exam_row['target_kelas'] ?? ''));
        $target_students = self::get_snapshot_target_students_for_exam($exam_row);
        $target_student_count = count($target_students);
        $page = max(1, $page);
        $starts_at = trim((string) ($exam_row['starts_at'] ?? ''));
        $ends_at = trim((string) ($exam_row['ends_at'] ?? ''));
        $duration_minutes = max(0, (int) ($exam_row['duration_minutes'] ?? 0));
        $show_student_result = ((int) ($exam_row['show_student_result'] ?? 0) === 1) ? 1 : 0;
        $enable_calculator = ((int) ($exam_row['enable_calculator'] ?? 0) === 1) ? 1 : 0;
        $exam_status = sanitize_key((string) ($exam_row['status'] ?? ''));
        $profile_ready_count = 0;
        $profile_missing_count = 0;
        $availability_ready_count = 0;
        $availability_auto_warm_count = 0;
        $availability_missing_count = 0;
        $problem_students = [];

        foreach ($target_students as $student) {
            $user_id = (int) ($student['user_id'] ?? 0);
            if ($user_id <= 0) {
                continue;
            }

            $availability = CBT_Exam_Availability_Cache::get_student_snapshot_diagnostics($user_id);
            $profile = CBT_Student_Profile_Cache::get_snapshot_diagnostics($user_id);
            $availability_meta = self::build_availability_snapshot_status_meta($availability, $user_id);
            $profile_meta = self::build_snapshot_status_meta((string) ($profile['snapshot_status'] ?? 'miss'));
            $availability_label = (string) ($availability_meta['label'] ?? 'MISS');
            $availability_miss_reason_label = trim((string) ($availability['snapshot_miss_reason_label'] ?? ''));
            $profile_label = (string) ($profile_meta['label'] ?? 'MISS');
            $is_profile_ready = $profile_label === 'READY';
            $is_availability_ready = in_array($availability_label, ['READY', 'AUTO-WARM'], true);

            if ($is_profile_ready) {
                $profile_ready_count++;
            } else {
                $profile_missing_count++;
            }

            if ($availability_label === 'AUTO-WARM') {
                $availability_auto_warm_count++;
            } elseif ($availability_label === 'READY') {
                $availability_ready_count++;
            } else {
                $availability_missing_count++;
            }

            if ($is_profile_ready && $is_availability_ready) {
                continue;
            }

            $reasons = [];
            if (!$is_profile_ready) {
                $reasons[] = 'Profil ' . $profile_label;
            }
            if (!$is_availability_ready) {
                $availability_reason_copy = 'Availability ' . $availability_label;
                if ($availability_label === 'MISS' && $availability_miss_reason_label !== '') {
                    $availability_reason_copy .= ' (' . $availability_miss_reason_label . ')';
                }
                $reasons[] = $availability_reason_copy;
            }

            $problem_students[] = [
                'user_id' => $user_id,
                'display_name' => (string) ($student['display_name'] ?? ('Siswa #' . $user_id)),
                'kode_kelas' => (string) ($student['kode_kelas'] ?? ''),
                'kode_ruang' => (string) ($student['kode_ruang'] ?? ''),
                'profile_status_label' => $profile_label,
                'profile_status_tone' => (string) ($profile_meta['tone'] ?? 'warning'),
                'availability_status_label' => $availability_label,
                'availability_status_tone' => (string) ($availability_meta['tone'] ?? 'warning'),
                'reason' => implode(' · ', $reasons),
            ];
        }

        $problem_total = count($problem_students);
        $problem_total_pages = max(1, (int) ceil($problem_total / self::EXAM_READINESS_PROBLEM_PER_PAGE));
        if ($problem_total > 0 && $page > $problem_total_pages) {
            $page = $problem_total_pages;
        } elseif ($problem_total === 0) {
            $page = 1;
        }
        $problem_page_items = array_slice($problem_students, ($page - 1) * self::EXAM_READINESS_PROBLEM_PER_PAGE, self::EXAM_READINESS_PROBLEM_PER_PAGE);

        $token_context = self::get_exam_readiness_token_context();
        $blockers = [];
        $warnings = [];

        if ($exam_status !== 'published') {
            $blockers[] = 'Exam masih belum berstatus published.';
        }
        if (empty($target_kelas)) {
            $blockers[] = 'Target kelas pada exam ini belum diatur.';
        }
        if ($target_student_count <= 0) {
            $blockers[] = 'Belum ada siswa target yang cocok dengan target_kelas exam ini.';
        }
        if (!$question_snapshot_ready) {
            $blockers[] = 'Snapshot Soal belum READY.';
        }
        if (!$start_snapshot_ready) {
            $blockers[] = 'Start Snapshot belum READY.';
        }

        if ($profile_missing_count > 0) {
            $warnings[] = sprintf('%d siswa target belum memiliki Snapshot Profil READY.', $profile_missing_count);
        }
        if ($availability_missing_count > 0) {
            $warnings[] = sprintf('%d siswa target belum memiliki Katalog Exam Siswa READY/AUTO-WARM.', $availability_missing_count);
        }

        $auto_warm_status = sanitize_key((string) ($auto_warm_context['status'] ?? 'inactive'));
        if ($auto_warm_status !== 'active') {
            $auto_warm_message = trim((string) ($auto_warm_context['last_message'] ?? ''));
            if ($auto_warm_message === '') {
                $auto_warm_message = 'Auto-Warm Availability belum aktif untuk exam ini.';
            }
            $warnings[] = $auto_warm_message;
        }

        if (!empty($token_context['enabled']) && empty($token_context['frontend_auto_apply'])) {
            $warnings[] = 'Token global aktif, tetapi frontend auto-apply masih nonaktif.';
        }
        if ($starts_at === '' && $ends_at === '') {
            $warnings[] = 'Jadwal exam belum diatur.';
        }

        $overall_status = 'ready';
        $overall_label = 'SIAP';
        $overall_tone = 'success';
        if (!empty($blockers)) {
            $overall_status = 'blocked';
            $overall_label = 'BELUM SIAP';
            $overall_tone = 'error';
        } elseif (!empty($warnings)) {
            $overall_status = 'warning';
            $overall_label = 'PERLU PERHATIAN';
            $overall_tone = 'warning';
        }

        return [
            'overall_status' => $overall_status,
            'overall_label' => $overall_label,
            'overall_tone' => $overall_tone,
            'blockers' => array_values($blockers),
            'warnings' => array_values($warnings),
            'target_kelas' => array_values($target_kelas),
            'target_student_count' => $target_student_count,
            'profile_ready_count' => $profile_ready_count,
            'profile_missing_count' => $profile_missing_count,
            'availability_ready_count' => $availability_ready_count,
            'availability_auto_warm_count' => $availability_auto_warm_count,
            'availability_missing_count' => $availability_missing_count,
            'question_snapshot_ready' => $question_snapshot_ready,
            'start_snapshot_ready' => $start_snapshot_ready,
            'auto_warm_status' => $auto_warm_status,
            'token_enabled' => !empty($token_context['enabled']),
            'token_frontend_auto_apply' => !empty($token_context['frontend_auto_apply']),
            'token_label' => (string) ($token_context['label'] ?? 'OFF'),
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'schedule_label' => self::build_exam_readiness_schedule_label($starts_at, $ends_at),
            'duration_minutes' => $duration_minutes,
            'show_student_result' => $show_student_result,
            'enable_calculator' => $enable_calculator,
            'problem_students' => $problem_page_items,
            'problem_total' => $problem_total,
            'problem_page' => $page,
            'problem_total_pages' => $problem_total_pages,
            'problem_per_page' => self::EXAM_READINESS_PROBLEM_PER_PAGE,
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<int,array<string,mixed>>
     */
    private static function get_snapshot_target_students_for_exam(array $exam_row): array
    {
        $target_kelas = self::split_target_kelas_csv((string) ($exam_row['target_kelas'] ?? ''));
        if (empty($target_kelas)) {
            return [];
        }

        $kelas_map = array_fill_keys($target_kelas, true);
        $students = [];
        foreach (self::get_filtered_snapshot_student_users() as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            $kode_kelas = self::normalize_snapshot_student_meta_filter((string) get_user_meta($user_id, 'kode_kelas', true));
            if ($kode_kelas === '' || !isset($kelas_map[$kode_kelas])) {
                continue;
            }

            $students[] = [
                'user_id' => $user_id,
                'display_name' => trim((string) ($user->display_name ?: $user->user_login)),
                'user_login' => (string) $user->user_login,
                'user_email' => (string) $user->user_email,
                'kode_kelas' => $kode_kelas,
                'kode_ruang' => self::normalize_snapshot_student_meta_filter((string) get_user_meta($user_id, 'kode_ruang', true)),
            ];
        }

        return $students;
    }

    /**
     * @return array{enabled:bool,frontend_auto_apply:bool,label:string}
     */
    private static function get_exam_readiness_token_context(): array
    {
        $token = '';
        $frontend_auto_apply = false;

        if (class_exists('CBT_Auth') && method_exists('CBT_Auth', 'get_global_exam_token')) {
            $token_meta = CBT_Auth::get_global_exam_token(false);
            $token = strtoupper(trim((string) ($token_meta['token'] ?? '')));
            $frontend_auto_apply = ((int) ($token_meta['frontend_auto_apply'] ?? 0) === 1);
        } else {
            $token = strtoupper(trim((string) get_option('cbt_global_exam_token_value', '')));
            $frontend_auto_apply = ((int) get_option('cbt_global_exam_token_frontend_auto_apply', 0) === 1);
        }

        $enabled = $token !== '';
        $label = 'OFF';
        if ($enabled && $frontend_auto_apply) {
            $label = 'OTOMATIS';
        } elseif ($enabled) {
            $label = 'MANUAL';
        }

        return [
            'enabled' => $enabled,
            'frontend_auto_apply' => $frontend_auto_apply,
            'label' => $label,
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function build_session_runtime_context(array $exam_row, array $filter_state = []): array
    {
        global $wpdb;

        $exam_id = (int) ($exam_row['id'] ?? 0);
        $exam_duration_minutes = max(0, (int) ($exam_row['duration_minutes'] ?? 0));
        $start_gate = class_exists('CBT_Start_Attempt_Gate_Service')
            ? CBT_Start_Attempt_Gate_Service::get_exam_diagnostics($exam_id)
            : [
                'redis_available' => false,
                'redis_error' => '',
                'status_label' => 'DISABLED',
                'status_tone' => 'warning',
                'status_slug' => 'disabled',
                'queue_depth' => 0,
                'bucket_tokens' => 50.0,
                'gate_capacity' => 50,
                'gate_window_seconds' => 5,
                'release_rate_label' => '50 / 5 detik',
                'oldest_wait_seconds' => 0,
            ];
        $empty_context = [
            'attempt_total' => 0,
            'attempt_total_overall' => 0,
            'visible_count' => 0,
            'rows_total' => 0,
            'rows_total_pages' => 1,
            'rows_current_page' => 1,
            'rows_per_page' => self::STUDENT_SNAPSHOT_PER_PAGE,
            'filters_applied' => false,
            'empty_message' => 'Belum ada attempt siswa yang sedang `in_progress` untuk exam ini.',
            'start_gate' => $start_gate,
            'delivery_snapshot' => [],
            'delivery_status_label' => 'UNAVAILABLE',
            'delivery_status_tone' => 'error',
            'redis_first_count' => 0,
            'legacy_count' => 0,
            'session_ready_count' => 0,
            'session_nonready_count' => 0,
            'contract_ready_count' => 0,
            'contract_nonready_count' => 0,
            'runtime_ready_count' => 0,
            'runtime_missing_count' => 0,
            'stale_last_seen_count' => 0,
            'low_remaining_count' => 0,
            'fallback_breakdown' => [],
            'issue_flags' => [],
            'rows' => [],
        ];
        if ($exam_id <= 0) {
            return $empty_context;
        }

        $attempt_rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, exam_id, student_id, status, started_at, extra_time_minutes
                 FROM {$wpdb->prefix}cbt_attempts
                 WHERE exam_id = %d
                   AND status = 'in_progress'
                 ORDER BY id DESC",
                $exam_id
            ),
            ARRAY_A
        );
        if (empty($attempt_rows)) {
            $empty_context['delivery_status_label'] = 'MISS';
            $empty_context['delivery_status_tone'] = 'warning';

            return $empty_context;
        }

        $search = strtolower(trim((string) ($filter_state['search'] ?? '')));
        $kelas_filter = self::normalize_snapshot_student_meta_filter((string) ($filter_state['kelas'] ?? ''));
        $ruang_filter = self::normalize_snapshot_student_meta_filter((string) ($filter_state['ruang'] ?? ''));
        $status_filter = self::normalize_student_snapshot_status_filter((string) ($filter_state['status'] ?? ''));
        $negate_status_filter = $status_filter !== '' && str_starts_with($status_filter, '!');
        $status_filter_value = $negate_status_filter ? substr($status_filter, 1) : $status_filter;
        $page = max(1, (int) ($filter_state['paged'] ?? 1));
        $per_page = self::STUDENT_SNAPSHOT_PER_PAGE;
        $filters_applied = ($search !== '' || $kelas_filter !== '' || $ruang_filter !== '' || $status_filter_value !== '');
        $attempt_total_overall = count($attempt_rows);

        $student_ids = array_values(array_unique(array_filter(array_map('intval', wp_list_pluck($attempt_rows, 'student_id')))));
        if (!empty($student_ids)) {
            if (function_exists('cache_users')) {
                cache_users($student_ids);
            }
            if (function_exists('update_meta_cache')) {
                update_meta_cache('user', $student_ids);
            }
        }

        $filtered_attempt_rows = [];
        foreach ($attempt_rows as $attempt_row) {
            $attempt = (array) $attempt_row;
            $student_id = (int) ($attempt['student_id'] ?? 0);
            if ($student_id <= 0) {
                continue;
            }

            $user = get_user_by('id', $student_id);
            $display_name = $user instanceof WP_User
                ? trim((string) ($user->display_name !== '' ? $user->display_name : $user->user_login))
                : ('Siswa #' . $student_id);
            $user_login = $user instanceof WP_User ? (string) $user->user_login : '';
            $kode_kelas = self::normalize_snapshot_student_meta_filter((string) get_user_meta($student_id, 'kode_kelas', true));
            $kode_ruang = self::normalize_snapshot_student_meta_filter((string) get_user_meta($student_id, 'kode_ruang', true));

            if ($kelas_filter !== '' && $kode_kelas !== $kelas_filter) {
                continue;
            }
            if ($ruang_filter !== '' && $kode_ruang !== $ruang_filter) {
                continue;
            }
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $display_name,
                    $user_login,
                    $kode_kelas,
                    $kode_ruang,
                    (string) $student_id,
                ]));
                if (!str_contains($haystack, $search)) {
                    continue;
                }
            }

            $attempt['display_name'] = $display_name;
            $attempt['user_login'] = $user_login;
            $attempt['kode_kelas'] = $kode_kelas;
            $attempt['kode_ruang'] = $kode_ruang;
            $filtered_attempt_rows[] = $attempt;
        }

        $delivery_diagnostics = class_exists('CBT_Exam_Question_Delivery_Cache')
            ? CBT_Exam_Question_Delivery_Cache::get_exam_payload_diagnostics($exam_id, 1, 1)
            : [
                'snapshot_status' => 'unavailable',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'storage_key' => '',
            ];
        $delivery_status_meta = self::build_snapshot_status_meta((string) ($delivery_diagnostics['snapshot_status'] ?? 'miss'));

        $rows = [];
        if ($status_filter_value !== '') {
            $filtered_attempt_ids = array_values(array_filter(array_map('intval', wp_list_pluck($filtered_attempt_rows, 'id'))));
            $presence_payloads = class_exists('CBT_Live_Proctoring_Presence')
                ? CBT_Live_Proctoring_Presence::get_attempt_payloads($filtered_attempt_ids)
                : [];

            $candidate_rows = [];
            foreach ($filtered_attempt_rows as $attempt) {
                $row = self::build_session_runtime_row_context($attempt, $exam_duration_minutes, $presence_payloads, $delivery_diagnostics);
                if (!self::matches_session_runtime_status_filter($row, $status_filter_value, $negate_status_filter)) {
                    continue;
                }

                $candidate_rows[] = $row;
            }

            $filtered_total = count($candidate_rows);
            $total_pages = max(1, (int) ceil($filtered_total / $per_page));
            if ($filtered_total > 0 && $page > $total_pages) {
                $page = $total_pages;
            } elseif ($filtered_total <= 0) {
                $page = 1;
            }

            $rows = $filtered_total > 0
                ? array_slice($candidate_rows, ($page - 1) * $per_page, $per_page)
                : [];
        } else {
            $filtered_total = count($filtered_attempt_rows);
            $total_pages = max(1, (int) ceil($filtered_total / $per_page));
            if ($filtered_total > 0 && $page > $total_pages) {
                $page = $total_pages;
            } elseif ($filtered_total <= 0) {
                $page = 1;
            }

            $page_attempt_rows = $filtered_total > 0
                ? array_slice($filtered_attempt_rows, ($page - 1) * $per_page, $per_page)
                : [];
            $page_attempt_ids = array_values(array_filter(array_map('intval', wp_list_pluck($page_attempt_rows, 'id'))));
            $presence_payloads = class_exists('CBT_Live_Proctoring_Presence')
                ? CBT_Live_Proctoring_Presence::get_attempt_payloads($page_attempt_ids)
                : [];

            foreach ($page_attempt_rows as $attempt) {
                $rows[] = self::build_session_runtime_row_context($attempt, $exam_duration_minutes, $presence_payloads, $delivery_diagnostics);
            }
        }

        $redis_first_count = 0;
        $legacy_count = 0;
        $session_ready_count = 0;
        $session_nonready_count = 0;
        $contract_ready_count = 0;
        $contract_nonready_count = 0;
        $runtime_ready_count = 0;
        $runtime_missing_count = 0;
        $stale_last_seen_count = 0;
        $low_remaining_count = 0;
        $fallback_breakdown = [];
        $session_status_counts = [];
        $contract_status_counts = [];
        foreach ($rows as $row) {
            $session_snapshot_status = sanitize_key((string) ($row['session_status_key'] ?? 'miss'));
            $contract_snapshot_status = sanitize_key((string) ($row['contract_status_key'] ?? 'miss'));
            $runtime_answers_status = sanitize_key((string) ($row['runtime_answers_status_key'] ?? 'miss'));
            $fallback_mode = trim((string) ($row['fallback_mode'] ?? 'LEGACY'));
            $last_seen_is_stale = !empty($row['last_seen_is_stale']);
            $low_remaining = !empty($row['low_remaining']);

            if ($fallback_mode === 'REDIS-FIRST') {
                $redis_first_count++;
            } else {
                $legacy_count++;
            }

            if ($session_snapshot_status === 'ready') {
                $session_ready_count++;
            } else {
                $session_nonready_count++;
                $session_status_counts[$session_snapshot_status] = (int) ($session_status_counts[$session_snapshot_status] ?? 0) + 1;
            }

            if ($contract_snapshot_status === 'ready') {
                $contract_ready_count++;
            } else {
                $contract_nonready_count++;
                $contract_status_counts[$contract_snapshot_status] = (int) ($contract_status_counts[$contract_snapshot_status] ?? 0) + 1;
            }

            if ($runtime_answers_status === 'ready') {
                $runtime_ready_count++;
            } else {
                $runtime_missing_count++;
            }

            if ($last_seen_is_stale) {
                $stale_last_seen_count++;
            }

            if ($low_remaining) {
                $low_remaining_count++;
            }

            $fallback_breakdown[$fallback_mode] = (int) ($fallback_breakdown[$fallback_mode] ?? 0) + 1;
        }

        ksort($fallback_breakdown);
        $fallback_breakdown_items = [];
        if (isset($fallback_breakdown['REDIS-FIRST'])) {
            $fallback_breakdown_items[] = [
                'label' => 'REDIS-FIRST',
                'count' => (int) $fallback_breakdown['REDIS-FIRST'],
            ];
            unset($fallback_breakdown['REDIS-FIRST']);
        }
        foreach ($fallback_breakdown as $label => $count) {
            $fallback_breakdown_items[] = [
                'label' => (string) $label,
                'count' => (int) $count,
            ];
        }

        $issue_flags = [];
        $delivery_snapshot_status = sanitize_key((string) ($delivery_diagnostics['snapshot_status'] ?? 'miss'));
        if ($delivery_snapshot_status !== 'ready') {
            $issue_flags[] = 'Delivery snapshot ' . $delivery_snapshot_status;
        }
        foreach (['miss', 'invalid', 'unavailable'] as $status_key) {
            $count = (int) ($session_status_counts[$status_key] ?? 0);
            if ($count > 0) {
                $issue_flags[] = sprintf('%d session %s', $count, $status_key);
            }
        }
        foreach (['miss', 'invalid', 'unavailable'] as $status_key) {
            $count = (int) ($contract_status_counts[$status_key] ?? 0);
            if ($count > 0) {
                $issue_flags[] = sprintf('%d contract %s', $count, $status_key);
            }
        }
        if ($runtime_missing_count > 0) {
            $issue_flags[] = sprintf('%d runtime miss', $runtime_missing_count);
        }
        if ($stale_last_seen_count > 0) {
            $issue_flags[] = sprintf('%d stale last seen', $stale_last_seen_count);
        }

        $empty_message = $filtered_total > 0
            ? ''
            : ($attempt_total_overall > 0 && $filters_applied
                ? 'Tidak ada attempt aktif yang cocok dengan filter saat ini.'
                : 'Belum ada attempt siswa yang sedang `in_progress` untuk exam ini.');

        return [
            'attempt_total' => $filtered_total,
            'attempt_total_overall' => $attempt_total_overall,
            'visible_count' => count($rows),
            'rows_total' => $filtered_total,
            'rows_total_pages' => $total_pages,
            'rows_current_page' => $page,
            'rows_per_page' => $per_page,
            'filters_applied' => $filters_applied,
            'empty_message' => $empty_message,
            'start_gate' => $start_gate,
            'delivery_snapshot' => $delivery_diagnostics,
            'delivery_status_label' => (string) $delivery_status_meta['label'],
            'delivery_status_tone' => (string) $delivery_status_meta['tone'],
            'redis_first_count' => $redis_first_count,
            'legacy_count' => $legacy_count,
            'session_ready_count' => $session_ready_count,
            'session_nonready_count' => $session_nonready_count,
            'contract_ready_count' => $contract_ready_count,
            'contract_nonready_count' => $contract_nonready_count,
            'runtime_ready_count' => $runtime_ready_count,
            'runtime_missing_count' => $runtime_missing_count,
            'stale_last_seen_count' => $stale_last_seen_count,
            'low_remaining_count' => $low_remaining_count,
            'fallback_breakdown' => $fallback_breakdown_items,
            'issue_flags' => $issue_flags,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<int,array<string,mixed>> $presence_payloads
     * @param array<string,mixed> $delivery_diagnostics
     * @return array<string,mixed>
     */
    private static function build_session_runtime_row_context(array $attempt, int $exam_duration_minutes, array $presence_payloads, array $delivery_diagnostics): array
    {
        $attempt_id = (int) ($attempt['id'] ?? 0);
        $student_id = (int) ($attempt['student_id'] ?? 0);
        $display_name = (string) ($attempt['display_name'] ?? ('Siswa #' . $student_id));
        $user_login = (string) ($attempt['user_login'] ?? '');
        $kode_kelas = (string) ($attempt['kode_kelas'] ?? '');
        $kode_ruang = (string) ($attempt['kode_ruang'] ?? '');

        $session_snapshot = class_exists('CBT_Attempt_Session_Snapshot_Cache')
            ? CBT_Attempt_Session_Snapshot_Cache::get_attempt_snapshot_diagnostics($attempt_id)
            : [
                'snapshot_status' => 'unavailable',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'storage_key' => '',
                'question_count' => 0,
                'question_order_signature' => '',
            ];
        $contract_snapshot = class_exists('CBT_Attempt_Question_Contract_Cache')
            ? CBT_Attempt_Question_Contract_Cache::get_attempt_snapshot_diagnostics($attempt_id)
            : [
                'snapshot_status' => 'unavailable',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'storage_key' => '',
                'question_count' => 0,
                'question_order_signature' => '',
            ];
        $session_status_meta = self::build_snapshot_status_meta((string) ($session_snapshot['snapshot_status'] ?? 'miss'));
        $contract_status_meta = self::build_snapshot_status_meta((string) ($contract_snapshot['snapshot_status'] ?? 'miss'));

        $runtime_ready = class_exists('CBT_Runtime') && CBT_Runtime::is_ready() && CBT_Runtime::has_attempt_state($attempt_id);
        $runtime_answers_status_label = $runtime_ready ? 'READY' : 'MISS';
        $runtime_answers_status_tone = $runtime_ready ? 'success' : 'warning';
        $presence = isset($presence_payloads[$attempt_id]) && is_array($presence_payloads[$attempt_id])
            ? $presence_payloads[$attempt_id]
            : [];

        $duration_minutes = max(
            0,
            (int) ($session_snapshot['duration_minutes'] ?? 0),
            $exam_duration_minutes
        );
        $extra_time_minutes = max(0, (int) ($attempt['extra_time_minutes'] ?? 0));
        $remaining_seconds = self::build_session_runtime_remaining_seconds(
            (string) ($attempt['started_at'] ?? ''),
            $duration_minutes + $extra_time_minutes
        );
        $session_snapshot_status = sanitize_key((string) ($session_snapshot['snapshot_status'] ?? 'miss'));
        $contract_snapshot_status = sanitize_key((string) ($contract_snapshot['snapshot_status'] ?? 'miss'));
        $delivery_snapshot_status = sanitize_key((string) ($delivery_diagnostics['snapshot_status'] ?? 'miss'));
        $last_seen_is_stale = self::is_session_runtime_last_seen_stale((string) ($presence['last_seen_at'] ?? ''));
        $low_remaining = $remaining_seconds > 0 && $remaining_seconds <= (5 * MINUTE_IN_SECONDS);
        $fallback_mode = self::build_session_runtime_fallback_mode(
            $session_snapshot_status,
            $contract_snapshot_status,
            $delivery_snapshot_status,
            $runtime_ready
        );
        $issue_reasons = self::build_session_runtime_issue_reasons(
            $session_snapshot_status,
            $contract_snapshot_status,
            $delivery_snapshot_status,
            $runtime_ready,
            $last_seen_is_stale,
            $low_remaining
        );

        return [
            'attempt_id' => $attempt_id,
            'student_id' => $student_id,
            'display_name' => $display_name,
            'user_login' => $user_login,
            'kode_kelas' => $kode_kelas,
            'kode_ruang' => $kode_ruang,
            'status' => (string) ($attempt['status'] ?? 'in_progress'),
            'session_snapshot' => $session_snapshot,
            'session_status_key' => $session_snapshot_status,
            'session_status_label' => (string) $session_status_meta['label'],
            'session_status_tone' => (string) $session_status_meta['tone'],
            'contract_snapshot' => $contract_snapshot,
            'contract_status_key' => $contract_snapshot_status,
            'contract_status_label' => (string) $contract_status_meta['label'],
            'contract_status_tone' => (string) $contract_status_meta['tone'],
            'runtime_answers_status_key' => $runtime_ready ? 'ready' : 'miss',
            'runtime_answers_status_label' => $runtime_answers_status_label,
            'runtime_answers_status_tone' => $runtime_answers_status_tone,
            'last_seen_at' => (string) ($presence['last_seen_at'] ?? ''),
            'last_seen_is_stale' => $last_seen_is_stale,
            'remaining_seconds' => $remaining_seconds,
            'remaining_label' => self::format_session_runtime_remaining($remaining_seconds),
            'low_remaining' => $low_remaining,
            'fallback_mode' => $fallback_mode,
            'issue_summary' => !empty($issue_reasons) ? implode(' · ', $issue_reasons) : 'Healthy',
        ];
    }

    private static function matches_session_runtime_status_filter(array $row, string $status_filter_value, bool $negate_status_filter = false): bool
    {
        $status_filter_value = self::normalize_student_snapshot_status_filter($status_filter_value);
        $session_ready = sanitize_key((string) ($row['session_status_key'] ?? 'miss')) === 'ready';
        $contract_ready = sanitize_key((string) ($row['contract_status_key'] ?? 'miss')) === 'ready';
        $runtime_ready = sanitize_key((string) ($row['runtime_answers_status_key'] ?? 'miss')) === 'ready';
        $last_seen_is_stale = !empty($row['last_seen_is_stale']);
        $low_remaining = !empty($row['low_remaining']);

        switch ($status_filter_value) {
            case 'ready':
                $matches = $session_ready && $contract_ready && $runtime_ready && !$last_seen_is_stale && !$low_remaining;
                break;
            case 'session_miss':
                $matches = !$session_ready;
                break;
            case 'contract_miss':
                $matches = !$contract_ready;
                break;
            case 'runtime_miss':
                $matches = !$runtime_ready;
                break;
            case 'stale':
                $matches = $last_seen_is_stale;
                break;
            case 'low_remaining':
                $matches = $low_remaining;
                break;
            default:
                $matches = true;
                break;
        }

        return $negate_status_filter ? !$matches : $matches;
    }

    private static function build_session_runtime_fallback_mode(string $session_status, string $contract_status, string $delivery_status, bool $runtime_ready): string
    {
        $fallbacks = [];
        if (sanitize_key($session_status) !== 'ready') {
            $fallbacks[] = 'session';
        }
        if (sanitize_key($contract_status) !== 'ready') {
            $fallbacks[] = 'contract';
        }
        if (sanitize_key($delivery_status) !== 'ready') {
            $fallbacks[] = 'delivery';
        }
        if (!$runtime_ready) {
            $fallbacks[] = 'runtime';
        }

        if (empty($fallbacks)) {
            return 'REDIS-FIRST';
        }

        return 'LEGACY ' . implode(' + ', $fallbacks);
    }

    /**
     * @return string[]
     */
    private static function build_session_runtime_issue_reasons(
        string $session_status,
        string $contract_status,
        string $delivery_status,
        bool $runtime_ready,
        bool $last_seen_is_stale,
        bool $low_remaining
    ): array {
        $reasons = [];
        $session_status = sanitize_key($session_status);
        $contract_status = sanitize_key($contract_status);
        $delivery_status = sanitize_key($delivery_status);

        if ($session_status !== 'ready') {
            $reasons[] = 'session ' . ($session_status !== '' ? $session_status : 'miss');
        }
        if ($contract_status !== 'ready') {
            $reasons[] = 'contract ' . ($contract_status !== '' ? $contract_status : 'miss');
        }
        if ($delivery_status !== 'ready') {
            $reasons[] = 'delivery ' . ($delivery_status !== '' ? $delivery_status : 'miss');
        }
        if (!$runtime_ready) {
            $reasons[] = 'runtime miss';
        }
        if ($last_seen_is_stale) {
            $reasons[] = 'stale last seen';
        }
        if ($low_remaining) {
            $reasons[] = 'low remaining';
        }

        return $reasons;
    }

    private static function is_session_runtime_last_seen_stale(string $last_seen_at): bool
    {
        $last_seen_at = trim($last_seen_at);
        if ($last_seen_at === '') {
            return false;
        }

        $last_seen_ts = strtotime($last_seen_at);
        if ($last_seen_ts === false || $last_seen_ts <= 0) {
            return false;
        }

        return (time() - $last_seen_ts) > 30;
    }

    private static function build_session_runtime_remaining_seconds(string $started_at, int $duration_minutes): int
    {
        $started_at = trim($started_at);
        if ($duration_minutes <= 0) {
            return 0;
        }

        $started_at_ts = strtotime($started_at);
        if ($started_at_ts === false || $started_at_ts <= 0) {
            return max(0, $duration_minutes * MINUTE_IN_SECONDS);
        }

        return max(0, ($started_at_ts + ($duration_minutes * MINUTE_IN_SECONDS)) - time());
    }

    private static function format_session_runtime_remaining(int $remaining_seconds): string
    {
        $remaining_seconds = max(0, $remaining_seconds);
        $hours = (int) floor($remaining_seconds / HOUR_IN_SECONDS);
        $minutes = (int) floor(($remaining_seconds % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
        $seconds = $remaining_seconds % MINUTE_IN_SECONDS;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    private static function build_exam_readiness_schedule_label(string $starts_at, string $ends_at): string
    {
        $starts_at = trim($starts_at);
        $ends_at = trim($ends_at);

        if ($starts_at !== '' && $ends_at !== '') {
            return $starts_at . ' -> ' . $ends_at;
        }
        if ($starts_at !== '') {
            return 'Mulai ' . $starts_at;
        }
        if ($ends_at !== '') {
            return 'Selesai ' . $ends_at;
        }

        return 'Belum diatur';
    }

    /**
     * @param array<string,mixed> $request
     * @return array<int,int>
     */
    private static function get_exam_snapshot_preview_pages_from_request(array $request): array
    {
        $pages = [];

        foreach ($request as $key => $value) {
            $raw_key = is_scalar($key) ? (string) $key : '';
            if (!preg_match('/^cbt_exam_snapshot_page_(\d+)$/', $raw_key, $matches)) {
                continue;
            }

            $exam_id = absint($matches[1] ?? 0);
            if ($exam_id <= 0) {
                continue;
            }

            $page_value = max(1, absint(wp_unslash((string) $value)));
            $pages[$exam_id] = $page_value;
        }

        return $pages;
    }

    public static function normalize_standard_list_per_page(int $requested): int
    {
        $allowed = [20, 40, 60, 80, 100];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 20;
    }

    public static function normalize_exam_builder_question_per_page(int $requested): int
    {
        $allowed = [50, 100, 150, 300];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 50;
    }

    /**
     * @return string[]
     */
    public static function get_distinct_user_meta_values(string $meta_key): array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->usermeta}
             WHERE meta_key = %s
               AND meta_value IS NOT NULL
               AND TRIM(meta_value) <> ''
             ORDER BY meta_value ASC",
            $meta_key
        );

        $rows = $wpdb->get_col($query);
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map('sanitize_text_field', $rows), static function ($value) {
            return $value !== '';
        }));
    }

    public static function get_exam_builder_selection_transient_key(string $builder_state_key, int $user_id): string
    {
        return 'cbt_exam_sel_' . md5($user_id . '|' . $builder_state_key);
    }

    /**
     * @param int[] $fallback_selected_ids
     * @return int[]
     */
    public static function get_exam_builder_selected_question_ids(string $builder_state_key, int $user_id, array $fallback_selected_ids = []): array
    {
        if ($builder_state_key === '' || $user_id <= 0) {
            return array_values(array_unique(array_filter(array_map('absint', $fallback_selected_ids))));
        }

        $saved_selected_ids = get_transient(self::get_exam_builder_selection_transient_key($builder_state_key, $user_id));
        if (!is_array($saved_selected_ids)) {
            return array_values(array_unique(array_filter(array_map('absint', $fallback_selected_ids))));
        }

        return array_values(array_unique(array_filter(array_map('absint', $saved_selected_ids))));
    }

    /**
     * @param int[] $selected_question_ids
     */
    public static function save_exam_builder_selected_question_ids(string $builder_state_key, int $user_id, array $selected_question_ids): void
    {
        if ($builder_state_key === '' || $user_id <= 0) {
            return;
        }

        set_transient(
            self::get_exam_builder_selection_transient_key($builder_state_key, $user_id),
            array_values(array_unique(array_filter(array_map('absint', $selected_question_ids)))),
            12 * HOUR_IN_SECONDS
        );
    }

    public static function clear_exam_builder_selection_state(string $builder_state_key, int $user_id): void
    {
        if ($builder_state_key === '' || $user_id <= 0) {
            return;
        }

        delete_transient(self::get_exam_builder_selection_transient_key($builder_state_key, $user_id));
    }

    /**
     * @return string[]
     */
    public static function split_target_kelas_csv($raw): array
    {
        $parts = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_scalar($item)) {
                    continue;
                }
                $parts[] = trim((string) $item);
            }
        } else {
            $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', (string) $raw);
            $parts = array_map('trim', explode(',', $raw));
        }
        $items = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $normalized = strtoupper(sanitize_text_field($part));
            if ($normalized === '') {
                continue;
            }
            $items[$normalized] = $normalized;
        }

        return array_values($items);
    }

    public static function normalize_target_kelas_csv($raw): string
    {
        return implode(',', self::split_target_kelas_csv($raw));
    }

    private static function get_exam_save_progress_state_key(string $token): string
    {
        return 'cbt_exam_save_progress_' . $token;
    }

    private static function clear_exam_save_progress_state(string $token): void
    {
        if ($token === '') {
            return;
        }

        delete_transient(self::get_exam_save_progress_state_key($token));
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function get_exam_save_progress_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_exam_save_progress_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>|WP_Error
     */
    private static function continue_exam_save_progress_state(array $state)
    {
        $phase = sanitize_key((string) ($state['phase'] ?? 'sync'));
        if ($phase === 'sync') {
            return self::run_exam_save_sync_batch($state);
        }

        if ($phase === 'cleanup') {
            $exam_id = (int) ($state['exam_id'] ?? 0);
            $existing_exam_question_ids = array_values(array_unique(array_filter(array_map(
                'absint',
                (array) ($state['existing_exam_question_ids'] ?? [])
            ))));
            $matched_existing_question_ids = array_values(array_unique(array_filter(array_map(
                'absint',
                (array) ($state['matched_existing_question_ids'] ?? [])
            ))));
            $stale_question_ids = array_values(array_diff($existing_exam_question_ids, $matched_existing_question_ids));
            if (!empty($stale_question_ids)) {
                $cleanup_result = self::archive_or_delete_exam_questions($exam_id, $stale_question_ids);
                if (is_wp_error($cleanup_result)) {
                    return $cleanup_result;
                }
                $state['removed_question_count'] = (int) ($cleanup_result['removed_question_count'] ?? count($stale_question_ids));
                $state['archived_question_count'] = (int) ($cleanup_result['archived_question_count'] ?? 0);
                $state['deleted_question_count'] = (int) ($cleanup_result['deleted_question_count'] ?? 0);
            }

            $state['phase'] = 'finalize';
            $state['message'] = 'Merapikan cache exam, attempt, dan data builder.';
            return $state;
        }

        if ($phase === 'finalize') {
            $saved_exam_id = (int) ($state['exam_id'] ?? 0);
            $original_exam_id = (int) ($state['original_exam_id'] ?? 0);
            $current_user_id = (int) ($state['current_user_id'] ?? 0);
            $total_sources = max(0, (int) ($state['total_sources'] ?? 0));
            $sync_summary = [
                'synced_question_count' => $saved_exam_id > 0 ? self::count_active_exam_questions($saved_exam_id) : 0,
                'updated_existing_count' => max(0, (int) ($state['updated_existing_count'] ?? 0)),
                'created_new_count' => max(0, (int) ($state['created_new_count'] ?? 0)),
                'linked_legacy_match_count' => max(0, (int) ($state['linked_legacy_match_count'] ?? 0)),
                'removed_question_count' => max(0, (int) ($state['removed_question_count'] ?? 0)),
                'archived_question_count' => max(0, (int) ($state['archived_question_count'] ?? 0)),
                'deleted_question_count' => max(0, (int) ($state['deleted_question_count'] ?? 0)),
                'preserved_attempt_history_count' => max(0, (int) ($state['preserved_attempt_history_count'] ?? 0)),
            ];
            $synced_questions = $total_sources > 0 ? (int) $sync_summary['synced_question_count'] : null;
            $finalize_result = self::finalize_exam_save_operation(
                $saved_exam_id,
                $original_exam_id,
                $synced_questions,
                $current_user_id,
                $_POST,
                $sync_summary
            );

            $state['legacy_active_count'] = max(0, (int) ($finalize_result['legacy_active_count'] ?? 0));

            $state['phase'] = 'complete';
            $state['message'] = (string) ($finalize_result['message'] ?? 'Exam berhasil disimpan.');
            $state['redirect_url'] = (string) ($finalize_result['redirect_url'] ?? '');

            return $state;
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>|WP_Error
     */
    private static function run_exam_save_sync_batch(array $state)
    {
        global $wpdb;

        $exam_id = (int) ($state['exam_id'] ?? 0);
        $current_user_id = (int) ($state['current_user_id'] ?? 0);
        $is_admin_scope = !empty($state['is_admin_scope']);
        $source_question_ids = array_values(array_unique(array_filter(array_map(
            'absint',
            (array) ($state['selected_source_question_ids'] ?? [])
        ))));
        $total_sources = count($source_question_ids);
        $processed_sources = max(0, min($total_sources, (int) ($state['processed_sources'] ?? 0)));

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam', 'Exam tidak valid untuk diproses.');
        }
        if ($total_sources <= 0 || $processed_sources >= $total_sources) {
            $state['phase'] = 'cleanup';
            $state['message'] = 'Meninjau soal lama yang tidak lagi dipakai.';
            return $state;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $batch_size = self::get_exam_save_progress_chunk_size();
        $max_batch_seconds = self::get_exam_save_progress_max_batch_seconds();
        $preserve_attempt_history = self::exam_has_attempt_records($exam_id);
        $batch_source_ids = array_slice($source_question_ids, $processed_sources, $batch_size);
        if (empty($batch_source_ids)) {
            $state['phase'] = 'cleanup';
            $state['message'] = 'Meninjau soal lama yang tidak lagi dipakai.';
            return $state;
        }

        $source_placeholders = implode(',', array_fill(0, count($batch_source_ids), '%d'));
        $source_query_params = $batch_source_ids;
        $source_sql = "SELECT q.*
                       FROM {$question_table} q
                       INNER JOIN {$exam_table} e ON e.id = q.exam_id
                       WHERE q.id IN ({$source_placeholders})";
        if (!$is_admin_scope) {
            $source_sql .= ' AND e.created_by = %d';
            $source_query_params[] = $current_user_id;
        }
        $source_rows = $wpdb->get_results($wpdb->prepare($source_sql, ...$source_query_params), ARRAY_A);
        $source_by_id = [];
        foreach ((array) $source_rows as $source_row) {
            $source_by_id[(int) ($source_row['id'] ?? 0)] = $source_row;
        }

        $ordered_sources = [];
        foreach ($batch_source_ids as $source_question_id) {
            if (isset($source_by_id[$source_question_id])) {
                $ordered_sources[] = $source_by_id[$source_question_id];
            }
        }
        if (empty($ordered_sources) || count($ordered_sources) !== count($batch_source_ids)) {
            return new WP_Error('invalid_questions', 'Soal sumber tidak ditemukan saat proses update exam.');
        }

        $ordered_source_ids = array_map(static function ($row): int {
            return (int) ($row['id'] ?? 0);
        }, $ordered_sources);
        $option_placeholders = implode(',', array_fill(0, count($ordered_source_ids), '%d'));
        $option_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$option_table} WHERE question_id IN ({$option_placeholders}) ORDER BY id ASC",
                ...$ordered_source_ids
            ),
            ARRAY_A
        );
        $options_by_question = [];
        foreach ((array) $option_rows as $option_row) {
            $question_id = (int) ($option_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            if (!isset($options_by_question[$question_id])) {
                $options_by_question[$question_id] = [];
            }
            $options_by_question[$question_id][] = $option_row;
        }

        $detail_by_question = [];
        foreach ($ordered_sources as $source_row) {
            $source_question_id = (int) ($source_row['id'] ?? 0);
            $source_question_type = (string) ($source_row['question_type'] ?? '');
            $detail_by_question[$source_question_id] = CBT_Admin_Questions_Helper::get_question_type_detail($source_question_id, $source_question_type);
        }

        $existing_exam_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, source_question_id, COALESCE(is_active, 1) AS is_active
                 FROM {$question_table}
                 WHERE exam_id = %d
                 ORDER BY COALESCE(is_active, 1) DESC, id ASC",
                $exam_id
            ),
            ARRAY_A
        );
        $matched_existing_lookup = array_fill_keys(
            array_values(array_unique(array_filter(array_map('absint', (array) ($state['matched_existing_question_ids'] ?? []))))),
            true
        );
        $existing_by_source = [];
        $candidate_question_ids = [];
        foreach ((array) $existing_exam_rows as $existing_exam_row) {
            $existing_question_id = (int) ($existing_exam_row['id'] ?? 0);
            if ($existing_question_id <= 0 || isset($matched_existing_lookup[$existing_question_id])) {
                continue;
            }

            $is_active_existing = (int) ($existing_exam_row['is_active'] ?? 1) === 1;
            if ($preserve_attempt_history && !$is_active_existing) {
                continue;
            }

            $existing_source_question_id = (int) ($existing_exam_row['source_question_id'] ?? 0);
            if ($existing_source_question_id > 0) {
                if (!isset($existing_by_source[$existing_source_question_id])) {
                    $existing_by_source[$existing_source_question_id] = [];
                }
                $existing_by_source[$existing_source_question_id][] = $existing_question_id;
            }

            $candidate_question_ids[] = $existing_question_id;
        }

        $existing_snapshots = [];
        $processed_this_batch = 0;
        $batch_started_at = microtime(true);
        $now = current_time('mysql');
        foreach ($ordered_sources as $source_row) {
            $source_question_id = (int) ($source_row['id'] ?? 0);
            $source_snapshot = self::build_exam_sync_source_snapshot(
                $source_row,
                $options_by_question[$source_question_id] ?? [],
                $detail_by_question[$source_question_id] ?? []
            );

            $matched_question_id = 0;
            $matched_via_legacy_descendant = false;
            if (isset($existing_by_source[$source_question_id])) {
                while (!empty($existing_by_source[$source_question_id])) {
                    $candidate_question_id = (int) array_shift($existing_by_source[$source_question_id]);
                    if ($candidate_question_id > 0 && !isset($matched_existing_lookup[$candidate_question_id])) {
                        $matched_question_id = $candidate_question_id;
                        break;
                    }
                }
            }

            if ($matched_question_id <= 0) {
                foreach ($candidate_question_ids as $candidate_question_id) {
                    $candidate_question_id = (int) $candidate_question_id;
                    if ($candidate_question_id <= 0 || isset($matched_existing_lookup[$candidate_question_id])) {
                        continue;
                    }
                    if (!isset($existing_snapshots[$candidate_question_id])) {
                        $existing_snapshots[$candidate_question_id] = CBT_Admin_Questions_Sync_Helper::get_question_sync_snapshot($candidate_question_id);
                    }
                    $candidate_snapshot = (array) ($existing_snapshots[$candidate_question_id] ?? []);
                    if (empty($candidate_snapshot)) {
                        continue;
                    }

                    if (CBT_Admin_Questions_Sync_Helper::question_snapshots_are_legacy_descendant_match($candidate_snapshot, $source_snapshot)) {
                        $matched_question_id = $candidate_question_id;
                        $matched_via_legacy_descendant = true;
                        break;
                    }
                }
            }

            if ($matched_question_id > 0) {
                $target_snapshot = (array) ($existing_snapshots[$matched_question_id] ?? []);
                $updated_exam_id = $preserve_attempt_history
                    ? self::link_existing_exam_question_to_source(
                        $matched_question_id,
                        $source_question_id,
                        $target_snapshot
                    )
                    : CBT_Admin_Questions_Sync_Helper::apply_source_snapshot_to_question(
                        $matched_question_id,
                        $source_question_id,
                        $source_snapshot,
                        $target_snapshot
                    );
                if ($updated_exam_id <= 0) {
                    return new WP_Error('update_failed', 'Gagal menyinkronkan salah satu soal exam yang sudah ada.');
                }

                $matched_existing_lookup[$matched_question_id] = true;
                $state['matched_existing_question_ids'][$matched_question_id] = $matched_question_id;
                $state['updated_existing_count'] = ((int) ($state['updated_existing_count'] ?? 0)) + 1;
                if ($matched_via_legacy_descendant) {
                    $state['linked_legacy_match_count'] = ((int) ($state['linked_legacy_match_count'] ?? 0)) + 1;
                }
                if ($preserve_attempt_history) {
                    $state['preserved_attempt_history_count'] = ((int) ($state['preserved_attempt_history_count'] ?? 0)) + 1;
                }
            } else {
                $question_type = (string) ($source_snapshot['question_type'] ?? 'multiple_choice');
                $inserted_question = $wpdb->insert(
                    $question_table,
                    [
                        'exam_id' => $exam_id,
                        'source_question_id' => $source_question_id,
                        'is_active' => 1,
                        'question_text' => (string) ($source_snapshot['question_text'] ?? ''),
                        'question_type' => $question_type,
                        'points' => (float) ($source_snapshot['points'] ?? 1),
                        'correct_text' => (string) ($source_snapshot['correct_text'] ?? ''),
                        'explanation' => (string) ($source_snapshot['explanation'] ?? ''),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    ['%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
                );
                if (!$inserted_question) {
                    return new WP_Error('insert_failed', 'Gagal menyalin salah satu soal ke exam.');
                }

                $new_question_id = (int) $wpdb->insert_id;
                foreach ((array) ($source_snapshot['options'] ?? []) as $source_option) {
                    $wpdb->insert(
                        $option_table,
                        [
                            'question_id' => $new_question_id,
                            'option_key' => (string) ($source_option['option_key'] ?? ''),
                            'option_text' => (string) ($source_option['option_text'] ?? ''),
                            'is_correct' => (int) ($source_option['is_correct'] ?? 0),
                            'created_at' => $now,
                        ],
                        ['%d', '%s', '%s', '%d', '%s']
                    );
                }

                CBT_Admin_Questions_Helper::save_question_type_detail(
                    $new_question_id,
                    $question_type,
                    (string) ($source_snapshot['normalized_detail_text'] ?? '')
                );
                $matched_existing_lookup[$new_question_id] = true;
                $state['matched_existing_question_ids'][$new_question_id] = $new_question_id;
                $state['created_new_count'] = ((int) ($state['created_new_count'] ?? 0)) + 1;
            }

            $processed_this_batch++;
            if ($processed_this_batch >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                break;
            }
        }

        $state['processed_sources'] = min($total_sources, $processed_sources + $processed_this_batch);
        if ((int) $state['processed_sources'] >= $total_sources) {
            $state['phase'] = 'cleanup';
            $state['message'] = 'Meninjau soal lama yang tidak lagi dipakai.';
        } else {
            $state['phase'] = 'sync';
            $state['message'] = sprintf(
                'Menyinkronkan soal %d dari %d.',
                (int) $state['processed_sources'],
                $total_sources
            );
        }

        return $state;
    }

    /**
     * @param array<string,mixed> $source_row
     * @param array<int,array<string,mixed>> $source_options
     * @param array<string,mixed> $source_detail
     * @return array<string,mixed>
     */
    private static function build_exam_sync_source_snapshot(array $source_row, array $source_options, array $source_detail): array
    {
        $question_type = (string) ($source_row['question_type'] ?? 'multiple_choice');
        $detail_text = '';
        if ($question_type === 'true_false') {
            $detail_value = isset($source_detail['correct_value'])
                ? (int) $source_detail['correct_value']
                : CBT_Admin_Questions_Helper::normalize_true_false_value((string) ($source_row['correct_text'] ?? ''));
            $detail_text = ($detail_value === 0) ? 'false' : 'true';
        } elseif ($question_type === 'short_answer') {
            $detail_text = (string) ($source_detail['correct_text'] ?? ($source_row['correct_text'] ?? ''));
        } elseif ($question_type === 'essay') {
            $detail_text = (string) ($source_detail['rubric_text'] ?? ($source_row['correct_text'] ?? ''));
        }

        return [
            'question_text' => (string) ($source_row['question_text'] ?? ''),
            'question_type' => $question_type,
            'points' => (float) ($source_row['points'] ?? 1),
            'correct_text' => (string) ($source_row['correct_text'] ?? ''),
            'explanation' => (string) ($source_row['explanation'] ?? ''),
            'normalized_detail_text' => $detail_text,
            'options' => $source_options,
        ];
    }

    /**
     * @param int[] $source_question_ids
     * @return array<string,int>|WP_Error
     */
    private static function sync_exam_questions_from_sources(
        int $exam_id,
        array $source_question_ids,
        bool $is_admin_scope,
        int $current_user_id
    ) {
        global $wpdb;

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam', 'Exam tidak valid.');
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $option_table = $wpdb->prefix . 'cbt_options';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $preserve_attempt_history = self::exam_has_attempt_records($exam_id);
        $source_question_ids = array_values(array_unique(array_filter(array_map('absint', $source_question_ids))));

        if (empty($source_question_ids)) {
            return new WP_Error('empty_questions', 'Pilih minimal 1 soal.');
        }

        $source_placeholders = implode(',', array_fill(0, count($source_question_ids), '%d'));
        $query_params = $source_question_ids;

        $source_sql = "SELECT q.*
                       FROM {$question_table} q
                       INNER JOIN {$exam_table} e ON e.id = q.exam_id
                       WHERE q.id IN ({$source_placeholders})";

        if (!$is_admin_scope) {
            $source_sql .= ' AND e.created_by = %d';
            $query_params[] = $current_user_id;
        }

        $source_rows = $wpdb->get_results($wpdb->prepare($source_sql, ...$query_params), ARRAY_A);
        $source_by_id = [];
        foreach ((array) $source_rows as $source_row) {
            $source_by_id[(int) ($source_row['id'] ?? 0)] = $source_row;
        }

        $ordered_sources = [];
        foreach ($source_question_ids as $source_question_id) {
            if (isset($source_by_id[$source_question_id])) {
                $ordered_sources[] = $source_by_id[$source_question_id];
            }
        }

        if (empty($ordered_sources)) {
            return new WP_Error('invalid_questions', 'Soal sumber tidak ditemukan.');
        }

        $ordered_source_ids = array_map(static function ($row): int {
            return (int) ($row['id'] ?? 0);
        }, $ordered_sources);

        $option_placeholders = implode(',', array_fill(0, count($ordered_source_ids), '%d'));
        $option_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$option_table} WHERE question_id IN ({$option_placeholders}) ORDER BY id ASC",
                ...$ordered_source_ids
            ),
            ARRAY_A
        );

        $options_by_question = [];
        foreach ((array) $option_rows as $option_row) {
            $question_id = (int) ($option_row['question_id'] ?? 0);
            if ($question_id <= 0) {
                continue;
            }
            if (!isset($options_by_question[$question_id])) {
                $options_by_question[$question_id] = [];
            }
            $options_by_question[$question_id][] = $option_row;
        }

        $detail_by_question = [];
        foreach ($ordered_sources as $source_row) {
            $source_question_id = (int) ($source_row['id'] ?? 0);
            $source_question_type = (string) ($source_row['question_type'] ?? '');
            $detail_by_question[$source_question_id] = CBT_Admin_Questions_Helper::get_question_type_detail($source_question_id, $source_question_type);
        }

        $existing_exam_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, source_question_id, COALESCE(is_active, 1) AS is_active
                 FROM {$question_table}
                 WHERE exam_id = %d
                 ORDER BY COALESCE(is_active, 1) DESC, id ASC",
                $exam_id
            ),
            ARRAY_A
        );

        $existing_by_source = [];
        $existing_snapshots = [];
        $unmatched_existing_ids = [];
        foreach ((array) $existing_exam_rows as $existing_exam_row) {
            $existing_question_id = (int) ($existing_exam_row['id'] ?? 0);
            if ($existing_question_id <= 0) {
                continue;
            }

            $is_active_existing = (int) ($existing_exam_row['is_active'] ?? 1) === 1;
            if ($preserve_attempt_history && !$is_active_existing) {
                continue;
            }

            $existing_source_question_id = (int) ($existing_exam_row['source_question_id'] ?? 0);
            if ($existing_source_question_id > 0) {
                if (!isset($existing_by_source[$existing_source_question_id])) {
                    $existing_by_source[$existing_source_question_id] = [];
                }
                $existing_by_source[$existing_source_question_id][] = $existing_question_id;
            }

            $existing_snapshots[$existing_question_id] = CBT_Admin_Questions_Sync_Helper::get_question_sync_snapshot($existing_question_id);
            $unmatched_existing_ids[$existing_question_id] = $existing_question_id;
        }

        $sync_summary = [
            'synced_question_count' => count($ordered_sources),
            'updated_existing_count' => 0,
            'created_new_count' => 0,
            'linked_legacy_match_count' => 0,
            'removed_question_count' => 0,
            'archived_question_count' => 0,
            'deleted_question_count' => 0,
            'preserved_attempt_history_count' => 0,
        ];
        $now = current_time('mysql');

        foreach ($ordered_sources as $source_row) {
            $source_question_id = (int) ($source_row['id'] ?? 0);
            $question_type = (string) ($source_row['question_type'] ?? 'multiple_choice');
            $source_options = $options_by_question[$source_question_id] ?? [];
            $source_detail = $detail_by_question[$source_question_id] ?? [];
            $detail_text = '';
            if ($question_type === 'true_false') {
                $detail_value = isset($source_detail['correct_value'])
                    ? (int) $source_detail['correct_value']
                    : CBT_Admin_Questions_Helper::normalize_true_false_value((string) ($source_row['correct_text'] ?? ''));
                $detail_text = ($detail_value === 0) ? 'false' : 'true';
            } elseif ($question_type === 'short_answer') {
                $detail_text = (string) ($source_detail['correct_text'] ?? ($source_row['correct_text'] ?? ''));
            } elseif ($question_type === 'essay') {
                $detail_text = (string) ($source_detail['rubric_text'] ?? ($source_row['correct_text'] ?? ''));
            }

            $source_snapshot = [
                'question_text' => (string) ($source_row['question_text'] ?? ''),
                'question_type' => $question_type,
                'points' => (float) ($source_row['points'] ?? 1),
                'correct_text' => (string) ($source_row['correct_text'] ?? ''),
                'explanation' => (string) ($source_row['explanation'] ?? ''),
                'normalized_detail_text' => $detail_text,
                'options' => $source_options,
            ];

            $matched_question_id = 0;
            $matched_via_legacy_descendant = false;
            if (isset($existing_by_source[$source_question_id]) && !empty($existing_by_source[$source_question_id])) {
                $matched_question_id = (int) array_shift($existing_by_source[$source_question_id]);
            }

            if ($matched_question_id <= 0 && !empty($unmatched_existing_ids)) {
                foreach ($unmatched_existing_ids as $candidate_question_id) {
                    $candidate_snapshot = (array) ($existing_snapshots[$candidate_question_id] ?? []);
                    if (empty($candidate_snapshot)) {
                        continue;
                    }

                    if (CBT_Admin_Questions_Sync_Helper::question_snapshots_are_legacy_descendant_match($candidate_snapshot, $source_snapshot)) {
                        $matched_question_id = (int) $candidate_question_id;
                        $matched_via_legacy_descendant = true;
                        break;
                    }
                }
            }

            if ($matched_question_id > 0) {
                $target_snapshot = (array) ($existing_snapshots[$matched_question_id] ?? []);
                $updated_exam_id = $preserve_attempt_history
                    ? self::link_existing_exam_question_to_source(
                        $matched_question_id,
                        $source_question_id,
                        $target_snapshot
                    )
                    : CBT_Admin_Questions_Sync_Helper::apply_source_snapshot_to_question(
                        $matched_question_id,
                        $source_question_id,
                        $source_snapshot,
                        $target_snapshot
                    );
                if ($updated_exam_id <= 0) {
                    return new WP_Error('update_failed', 'Gagal menyinkronkan salah satu soal exam yang sudah ada.');
                }

                unset($unmatched_existing_ids[$matched_question_id]);
                $sync_summary['updated_existing_count']++;
                if ($matched_via_legacy_descendant) {
                    $sync_summary['linked_legacy_match_count']++;
                }
                if ($preserve_attempt_history) {
                    $sync_summary['preserved_attempt_history_count']++;
                }
                continue;
            }

            $inserted_question = $wpdb->insert(
                $question_table,
                [
                    'exam_id' => $exam_id,
                    'source_question_id' => $source_question_id,
                    'is_active' => 1,
                    'question_text' => (string) ($source_row['question_text'] ?? ''),
                    'question_type' => $question_type,
                    'points' => (float) ($source_row['points'] ?? 1),
                    'correct_text' => (string) ($source_row['correct_text'] ?? ''),
                    'explanation' => (string) ($source_row['explanation'] ?? ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
            );

            if (!$inserted_question) {
                return new WP_Error('insert_failed', 'Gagal menyalin salah satu soal ke exam.');
            }

            $new_question_id = (int) $wpdb->insert_id;
            foreach ($source_options as $source_option) {
                $wpdb->insert(
                    $option_table,
                    [
                        'question_id' => $new_question_id,
                        'option_key' => (string) ($source_option['option_key'] ?? ''),
                        'option_text' => (string) ($source_option['option_text'] ?? ''),
                        'is_correct' => (int) ($source_option['is_correct'] ?? 0),
                        'created_at' => $now,
                    ],
                    ['%d', '%s', '%s', '%d', '%s']
                );
            }

            CBT_Admin_Questions_Helper::save_question_type_detail($new_question_id, $question_type, $detail_text);
            $sync_summary['created_new_count']++;
        }

        if (!empty($unmatched_existing_ids)) {
            $stale_question_ids = array_values(array_filter(array_map('absint', array_values($unmatched_existing_ids))));
            if (!empty($stale_question_ids)) {
                $cleanup_result = self::archive_or_delete_exam_questions($exam_id, $stale_question_ids);
                if (is_wp_error($cleanup_result)) {
                    return $cleanup_result;
                }
                $sync_summary['removed_question_count'] = (int) ($cleanup_result['removed_question_count'] ?? 0);
                $sync_summary['archived_question_count'] = (int) ($cleanup_result['archived_question_count'] ?? 0);
                $sync_summary['deleted_question_count'] = (int) ($cleanup_result['deleted_question_count'] ?? 0);
            }
        }

        $sync_summary['synced_question_count'] = self::count_active_exam_questions($exam_id);

        return $sync_summary;
    }

    private static function count_active_exam_questions(int $exam_id): int
    {
        global $wpdb;

        if ($exam_id <= 0) {
            return 0;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$question_table}
                 WHERE exam_id = %d
                   AND COALESCE(is_active, 1) = 1",
                $exam_id
            )
        );
    }

    private static function clear_exam_attempt_states(int $exam_id): void
    {
        global $wpdb;

        if ($exam_id <= 0) {
            return;
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id
                 FROM {$attempt_table}
                 WHERE exam_id = %d",
                $exam_id
            )
        );

        $attempt_ids = array_values(array_unique(array_filter(array_map('absint', (array) $attempt_ids))));
        if (empty($attempt_ids)) {
            return;
        }

        CBT_Cache::invalidate_attempts($attempt_ids);
        CBT_UI_State::clear_attempt_states_by_attempt_ids($attempt_ids);
        if (class_exists('CBT_Runtime')) {
            foreach ($attempt_ids as $attempt_id) {
                CBT_Runtime::clear_attempt_runtime((int) $attempt_id);
            }
        }
    }

    private static function exam_has_attempt_records(int $exam_id): bool
    {
        global $wpdb;

        if ($exam_id <= 0) {
            return false;
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$attempt_table}
                 WHERE exam_id = %d",
                $exam_id
            )
        );

        return $attempt_count > 0;
    }

    private static function link_existing_exam_question_to_source(
        int $target_question_id,
        int $source_question_id,
        array $target_snapshot = []
    ): int {
        global $wpdb;

        if ($target_question_id <= 0 || $source_question_id <= 0) {
            return 0;
        }

        if (empty($target_snapshot)) {
            $target_snapshot = CBT_Admin_Questions_Sync_Helper::get_question_sync_snapshot($target_question_id);
        }
        if (empty($target_snapshot)) {
            return 0;
        }

        $question_table = $wpdb->prefix . 'cbt_questions';
        $updated = $wpdb->update(
            $question_table,
            [
                'source_question_id' => $source_question_id,
                'is_active' => 1,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $target_question_id],
            ['%d', '%d', '%s'],
            ['%d']
        );
        if ($updated === false) {
            return 0;
        }

        return (int) ($target_snapshot['exam_id'] ?? 0);
    }

    /**
     * @param array<int,int> $question_ids
     * @return array<string,int>|WP_Error
     */
    private static function archive_or_delete_exam_questions(int $exam_id, array $question_ids)
    {
        global $wpdb;

        $question_table = $wpdb->prefix . 'cbt_questions';
        $question_ids = array_values(array_unique(array_filter(array_map('absint', $question_ids))));
        if ($exam_id <= 0 || empty($question_ids)) {
            return [
                'removed_question_count' => 0,
                'archived_question_count' => 0,
                'deleted_question_count' => 0,
            ];
        }

        $protected_ids = self::find_historical_question_ids($exam_id, $question_ids);
        $archive_ids = array_values(array_intersect($question_ids, $protected_ids));
        $delete_ids = array_values(array_diff($question_ids, $archive_ids));
        $now = current_time('mysql');

        if (!empty($archive_ids)) {
            $archive_placeholders = implode(',', array_fill(0, count($archive_ids), '%d'));
            $archive_result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$question_table}
                     SET is_active = 0,
                         updated_at = %s
                     WHERE exam_id = %d
                       AND id IN ({$archive_placeholders})",
                    $now,
                    $exam_id,
                    ...$archive_ids
                )
            );
            if ($archive_result === false) {
                return new WP_Error('archive_removed_failed', 'Gagal mengarsipkan soal exam yang sudah dipakai attempt.');
            }
        }

        if (!empty($delete_ids)) {
            $delete_placeholders = implode(',', array_fill(0, count($delete_ids), '%d'));
            $deleted_removed = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$question_table}
                     WHERE exam_id = %d
                       AND id IN ({$delete_placeholders})",
                    $exam_id,
                    ...$delete_ids
                )
            );
            if ($deleted_removed === false) {
                return new WP_Error('delete_removed_failed', 'Gagal menghapus soal exam yang sudah tidak dipakai.');
            }
        }

        return [
            'removed_question_count' => count($question_ids),
            'archived_question_count' => count($archive_ids),
            'deleted_question_count' => count($delete_ids),
        ];
    }

    /**
     * @param array<int,int> $question_ids
     * @return int[]
     */
    private static function find_historical_question_ids(int $exam_id, array $question_ids): array
    {
        global $wpdb;

        $answer_table = $wpdb->prefix . 'cbt_answers';
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $question_ids = array_values(array_unique(array_filter(array_map('absint', $question_ids))));
        if ($exam_id <= 0 || empty($question_ids)) {
            return [];
        }

        $protected = [];
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $answered_question_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT question_id
                 FROM {$answer_table}
                 WHERE question_id IN ({$placeholders})",
                ...$question_ids
            )
        );
        foreach ((array) $answered_question_ids as $answered_question_id) {
            $answered_question_id = (int) $answered_question_id;
            if ($answered_question_id > 0) {
                $protected[$answered_question_id] = $answered_question_id;
            }
        }

        $attempt_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_order
                 FROM {$attempt_table}
                 WHERE exam_id = %d
                   AND question_order IS NOT NULL
                   AND question_order <> ''",
                $exam_id
            ),
            ARRAY_A
        );
        $candidate_lookup = array_fill_keys($question_ids, true);
        foreach ((array) $attempt_rows as $attempt_row) {
            $decoded = json_decode((string) ($attempt_row['question_order'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $candidate_question_id) {
                $candidate_question_id = (int) $candidate_question_id;
                if ($candidate_question_id > 0 && isset($candidate_lookup[$candidate_question_id])) {
                    $protected[$candidate_question_id] = $candidate_question_id;
                }
            }
        }

        return array_values($protected);
    }

    private static function get_exam_save_progress_chunk_size(): int
    {
        $batch_size = (int) apply_filters('cbt_exam_save_progress_chunk_size', 25);
        if ($batch_size < 5) {
            return 5;
        }
        if ($batch_size > 100) {
            return 100;
        }

        return $batch_size;
    }

    private static function get_exam_save_progress_max_batch_seconds(): float
    {
        $seconds = (float) apply_filters('cbt_exam_save_progress_max_batch_seconds', 2.5);
        if ($seconds < 1.0) {
            return 1.0;
        }
        if ($seconds > 8.0) {
            return 8.0;
        }

        return $seconds;
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>|WP_Error
     */
    private static function normalize_exam_save_payload(array $request)
    {
        $id = isset($request['id']) ? absint(wp_unslash((string) $request['id'])) : 0;
        $subject_id = isset($request['subject_id']) ? absint(wp_unslash((string) $request['subject_id'])) : 0;
        $title = isset($request['title']) ? sanitize_text_field(wp_unslash((string) $request['title'])) : '';
        $description = isset($request['description']) ? wp_kses_post(wp_unslash((string) $request['description'])) : '';
        $duration = max(1, isset($request['duration_minutes']) ? absint(wp_unslash((string) $request['duration_minutes'])) : 60);
        $kkm_raw = isset($request['kkm_percentage']) ? trim((string) wp_unslash((string) $request['kkm_percentage'])) : '75';
        if ($kkm_raw === '') {
            $kkm_raw = '75';
        }
        if (!is_numeric($kkm_raw)) {
            return new WP_Error('invalid_kkm', 'KKM harus berupa angka persentase.', $id);
        }
        $kkm_percentage = round((float) $kkm_raw, 2);
        $randomize = isset($request['randomize_questions']) ? 1 : 0;
        $randomize_options = isset($request['randomize_options']) ? 1 : 0;
        $show_student_result = isset($request['show_student_result']) ? 1 : 0;
        $enable_calculator = isset($request['enable_calculator']) ? 1 : 0;
        $status = isset($request['status']) ? sanitize_text_field(wp_unslash((string) $request['status'])) : 'draft';
        $allowed_statuses = ['draft', 'published', 'closed'];
        if (!in_array($status, $allowed_statuses, true)) {
            $status = 'draft';
        }
        $starts_at = isset($request['starts_at']) ? self::from_datetime_local((string) wp_unslash((string) $request['starts_at'])) : null;
        $ends_at = isset($request['ends_at']) ? self::from_datetime_local((string) wp_unslash((string) $request['ends_at'])) : null;
        $target_kelas_raw = isset($request['target_kelas']) ? wp_unslash($request['target_kelas']) : '';
        $target_kelas = self::normalize_target_kelas_csv($target_kelas_raw);
        $raw_source_question_ids = isset($request['source_question_ids']) && is_array($request['source_question_ids'])
            ? wp_unslash($request['source_question_ids'])
            : [];
        $source_question_ids = array_values(array_unique(array_filter(array_map('absint', $raw_source_question_ids))));

        if ($subject_id <= 0) {
            return new WP_Error('invalid_subject', 'Mapel wajib dipilih.', $id);
        }
        if ($title === '') {
            return new WP_Error('invalid_title', 'Judul exam wajib diisi.', $id);
        }
        if ($kkm_percentage < 0 || $kkm_percentage > 100) {
            return new WP_Error('invalid_kkm', 'KKM harus berada pada rentang 0 sampai 100.', $id);
        }
        if ($starts_at !== null && $ends_at !== null && strtotime($ends_at) < strtotime($starts_at)) {
            return new WP_Error('invalid_schedule', 'Waktu selesai tidak boleh lebih awal dari waktu mulai.', $id);
        }
        if ($id <= 0 && empty($source_question_ids)) {
            return new WP_Error('invalid_questions', 'Pilih minimal 1 soal untuk exam baru.', $id);
        }

        return [
            'id' => $id,
            'subject_id' => $subject_id,
            'title' => $title,
            'description' => $description,
            'duration' => $duration,
            'kkm_percentage' => $kkm_percentage,
            'randomize' => $randomize,
            'randomize_options' => $randomize_options,
            'show_student_result' => $show_student_result,
            'enable_calculator' => $enable_calculator,
            'status' => $status,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'target_kelas' => $target_kelas,
            'source_question_ids' => $source_question_ids,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|WP_Error
     */
    private static function upsert_exam_record_from_payload(array $payload, bool $is_admin_scope, int $current_user_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_exams';
        $id = (int) ($payload['id'] ?? 0);
        $source_question_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($payload['source_question_ids'] ?? [])))));
        $existing_question_count = 0;
        if ($id > 0 && empty($source_question_ids)) {
            $existing_question_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cbt_questions WHERE exam_id = %d AND COALESCE(is_active, 1) = 1",
                    $id
                )
            );
        }

        $data = [
            'subject_id' => (int) ($payload['subject_id'] ?? 0),
            'title' => (string) ($payload['title'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'duration_minutes' => (int) ($payload['duration'] ?? 60),
            'kkm_percentage' => (float) ($payload['kkm_percentage'] ?? 75.0),
            'total_questions' => $existing_question_count,
            'randomize_questions' => (int) ($payload['randomize'] ?? 0),
            'randomize_options' => (int) ($payload['randomize_options'] ?? 0),
            'show_student_result' => (int) ($payload['show_student_result'] ?? 1),
            'enable_calculator' => (int) ($payload['enable_calculator'] ?? 1),
            'status' => (string) ($payload['status'] ?? 'draft'),
            'starts_at' => $payload['starts_at'] ?? null,
            'ends_at' => $payload['ends_at'] ?? null,
            'target_kelas' => (string) ($payload['target_kelas'] ?? ''),
            'updated_at' => current_time('mysql'),
        ];

        $saved_exam_id = $id;
        if ($id > 0) {
            $existing_exam_title = (string) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT title FROM {$table} WHERE id = %d LIMIT 1",
                    $id
                )
            );
            if (self::is_bank_exam_title($existing_exam_title)) {
                return new WP_Error('bank_exam_locked', 'Bank Soal dikelola dari CBT Questions, bukan dari CBT Exams.');
            }

            if (!$is_admin_scope) {
                $owned_exam = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE id = %d AND created_by = %d",
                    $id,
                    $current_user_id
                ));
                if ($owned_exam === 0) {
                    return new WP_Error('unauthorized_exam', 'Unauthorized exam update.');
                }
            }

            $duplicate_exam_id = self::find_duplicate_exam_id_by_subject_and_title(
                (int) ($payload['subject_id'] ?? 0),
                (string) ($payload['title'] ?? ''),
                $id
            );
            if ($duplicate_exam_id > 0) {
                return new WP_Error('exam_duplicate', 'Judul exam sudah terdaftar pada mapel ini.');
            }

            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => $id],
                ['%d', '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            if ($updated === false) {
                return new WP_Error('update_failed', 'Gagal mengupdate exam.');
            }
        } else {
            $duplicate_exam_id = self::find_duplicate_exam_id_by_subject_and_title(
                (int) ($payload['subject_id'] ?? 0),
                (string) ($payload['title'] ?? '')
            );
            if ($duplicate_exam_id > 0) {
                return new WP_Error('exam_duplicate', 'Judul exam sudah terdaftar pada mapel ini.');
            }

            $data['created_by'] = $current_user_id;
            $data['created_at'] = current_time('mysql');

            $inserted = $wpdb->insert(
                $table,
                $data,
                ['%d', '%s', '%s', '%d', '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
            );
            if (!$inserted) {
                return new WP_Error('insert_failed', 'Gagal membuat exam.');
            }

            $saved_exam_id = (int) $wpdb->insert_id;
        }

        return [
            'exam_id' => $saved_exam_id,
            'existing_question_count' => $existing_question_count,
            'is_update' => $id > 0,
        ];
    }

    private static function find_duplicate_exam_id_by_subject_and_title(int $subject_id, string $title, int $exclude_id = 0): int
    {
        global $wpdb;

        $title = trim($title);
        if ($subject_id <= 0 || $title === '') {
            return 0;
        }

        $table = $wpdb->prefix . 'cbt_exams';
        if ($exclude_id > 0) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$table}
                     WHERE subject_id = %d
                       AND title = %s
                       AND id <> %d
                     ORDER BY id ASC
                     LIMIT 1",
                    $subject_id,
                    $title,
                    $exclude_id
                )
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE subject_id = %d
                   AND title = %s
                 ORDER BY id ASC
                 LIMIT 1",
                $subject_id,
                $title
            )
        );
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,string>
     */
    private static function finalize_exam_save_operation(
        int $saved_exam_id,
        int $original_exam_id,
        ?int $synced_questions,
        int $current_user_id,
        array $request,
        array $sync_summary = []
    ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'cbt_exams';
        if ($saved_exam_id > 0 && $synced_questions !== null) {
            $wpdb->update(
                $table,
                [
                    'total_questions' => $synced_questions,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $saved_exam_id],
                ['%d', '%s'],
                ['%d']
            );
            self::clear_exam_attempt_states($saved_exam_id);
        }

        $legacy_active_count = 0;
        if ($saved_exam_id > 0) {
            $lineage_context = self::build_exam_lineage_context($saved_exam_id);
            $legacy_active_count = max(0, (int) ($lineage_context['legacy_question_count'] ?? 0));
        }
        if (!empty($sync_summary) || $legacy_active_count > 0) {
            $sync_summary['legacy_active_count'] = $legacy_active_count;
        }

        $message = self::build_exam_sync_notice_message($original_exam_id > 0, $sync_summary);
        $exam_list_state = self::get_exam_list_state_from_request($request);

        CBT_Cache::invalidate_catalog();
        CBT_Cache::invalidate_exam($saved_exam_id);
        if (class_exists('CBT_REST') && method_exists('CBT_REST', 'warm_exam_question_delivery_snapshot')) {
            CBT_REST::warm_exam_question_delivery_snapshot($saved_exam_id);
        }
        if (class_exists('CBT_REST') && method_exists('CBT_REST', 'warm_exam_start_attempt_snapshot')) {
            CBT_REST::warm_exam_start_attempt_snapshot($saved_exam_id);
        }
        self::clear_exam_builder_selection_state('cbt_exam_builder_new', $current_user_id);
        self::clear_exam_builder_selection_state('cbt_exam_builder_edit_' . $saved_exam_id, $current_user_id);

        return [
            'message' => $message,
            'redirect_url' => add_query_arg(
                self::add_exam_list_state_args(
                    [
                        'page' => 'cbt-exams',
                        'cbt_exam_panel' => 'list',
                        'cbt_msg' => $message,
                        'cbt_saved_exam_id' => (int) $saved_exam_id,
                    ],
                    $exam_list_state
                ),
                admin_url('admin.php')
            ),
            'legacy_active_count' => $legacy_active_count,
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function clean_exam_snapshot_stack(array $exam_row): array
    {
        $exam_id = (int) ($exam_row['id'] ?? 0);
        $exam_title = trim((string) ($exam_row['title'] ?? ''));
        $exam_label = $exam_title !== '' ? $exam_title : ('Exam #' . $exam_id);
        if ($exam_id <= 0) {
            return [
                'success' => false,
                'message' => 'Exam belum dipilih untuk membersihkan snapshot pra ujian.',
            ];
        }

        $preflight_state = CBT_Exam_Preflight_Service::get_state();
        $preflight_jobs = method_exists('CBT_Exam_Preflight_Service', 'get_jobs_state')
            ? (array) CBT_Exam_Preflight_Service::get_jobs_state()
            : [];
        $auto_warm_state = CBT_Exam_Availability_Auto_Warm_Service::get_state();
        $active_preflight_exam_id = (int) ($preflight_state['exam_id'] ?? 0);
        $active_auto_warm_exam_id = (int) ($auto_warm_state['exam_id'] ?? 0);

        $same_exam_preflight_active = false;
        if (isset($preflight_jobs[$exam_id]) && is_array($preflight_jobs[$exam_id])) {
            $same_exam_preflight_active = in_array(
                sanitize_key((string) ($preflight_jobs[$exam_id]['status'] ?? 'inactive')),
                ['active', 'queued'],
                true
            );
        } elseif (!empty($preflight_state['active']) && $active_preflight_exam_id === $exam_id) {
            $same_exam_preflight_active = true;
        }

        $preflight_stopped = false;
        if ($same_exam_preflight_active) {
            $stop_result = CBT_Exam_Preflight_Service::stop_for_exam($exam_row);
            if (empty($stop_result['success'])) {
                return [
                    'success' => false,
                    'message' => (string) ($stop_result['message'] ?? 'Gagal menghentikan one-click pra ujian sebelum clean.'),
                ];
            }
            $preflight_stopped = true;
        }

        $auto_warm_stopped = false;
        if (!empty($auto_warm_state['active']) && $active_auto_warm_exam_id === $exam_id) {
            $stop_result = CBT_Exam_Availability_Auto_Warm_Service::stop_for_exam($exam_row);
            if (empty($stop_result['success'])) {
                return [
                    'success' => false,
                    'message' => (string) ($stop_result['message'] ?? 'Gagal menghentikan auto-warm availability sebelum clean.'),
                ];
            }
            $auto_warm_stopped = true;
        }

        $question_deleted_keys = CBT_Exam_Question_Delivery_Cache::clear_exam_payload($exam_id);
        $start_deleted_keys = CBT_Exam_Start_Attempt_Snapshot_Cache::clear_exam_snapshot($exam_id);
        $submission_result = CBT_Question_Submission_Context_Cache::clear_exam_snapshots($exam_id);
        $submission_deleted_keys = max(0, (int) ($submission_result['deleted_keys'] ?? 0));
        $submission_question_count = max(0, (int) ($submission_result['question_count'] ?? 0));

        $message = sprintf(
            'Snapshot pra ujian untuk %s berhasil dibersihkan. Soal key %d · Start key %d · Submission key %d (%d soal). Snapshot siswa tetap dipertahankan dan bisa dibersihkan dari panel monitoring siswa bila diperlukan.',
            $exam_label,
            $question_deleted_keys,
            $start_deleted_keys,
            $submission_deleted_keys,
            $submission_question_count
        );

        $post_actions = [];
        if ($preflight_stopped) {
            $post_actions[] = 'One-click dihentikan dulu.';
        }
        if ($auto_warm_stopped) {
            $post_actions[] = 'Auto-warm dihentikan dulu.';
        }

        $preflight_state_cleared = false;
        if (isset($preflight_jobs[$exam_id]) || (int) ($preflight_state['exam_id'] ?? 0) === $exam_id) {
            $clear_state_result = CBT_Exam_Preflight_Service::clear_state_for_exam($exam_row);
            if (empty($clear_state_result['success'])) {
                return [
                    'success' => false,
                    'message' => (string) ($clear_state_result['message'] ?? 'Gagal membersihkan state one-click pra ujian setelah clean.'),
                ];
            }
            $preflight_state_cleared = true;
        }

        $auto_warm_state_cleared = false;
        if ((int) ($auto_warm_state['exam_id'] ?? 0) === $exam_id) {
            $clear_state_result = CBT_Exam_Availability_Auto_Warm_Service::clear_state_for_exam($exam_row);
            if (empty($clear_state_result['success'])) {
                return [
                    'success' => false,
                    'message' => (string) ($clear_state_result['message'] ?? 'Gagal membersihkan state auto-warm setelah clean.'),
                ];
            }
            $auto_warm_state_cleared = true;
        }

        if ($preflight_state_cleared) {
            $post_actions[] = 'State one-click direset.';
        }
        if ($auto_warm_state_cleared) {
            $post_actions[] = 'State auto-warm direset.';
        }
        if (!empty($post_actions)) {
            $message .= ' ' . implode(' ', $post_actions);
        }

        return [
            'success' => true,
            'message' => $message,
            'exam_id' => $exam_id,
            'target_student_count' => count(self::get_snapshot_target_students_for_exam($exam_row)),
            'question_deleted_keys' => $question_deleted_keys,
            'start_deleted_keys' => $start_deleted_keys,
            'submission_deleted_keys' => $submission_deleted_keys,
            'submission_question_count' => $submission_question_count,
            'profile_deleted_keys' => 0,
            'profile_cleared_count' => 0,
            'login_deleted_keys' => 0,
            'availability_deleted_keys' => 0,
            'availability_cleared_count' => 0,
            'preflight_stopped' => $preflight_stopped,
            'auto_warm_stopped' => $auto_warm_stopped,
            'preflight_state_cleared' => $preflight_state_cleared,
            'auto_warm_state_cleared' => $auto_warm_state_cleared,
        ];
    }

    private static function redirect_exam_with_error(string $message, int $edit_id = 0): void
    {
        $args = self::add_exam_list_state_args(
            [
                'page' => 'cbt-exams',
                'cbt_err' => $message,
            ],
            self::get_exam_list_state_from_request($_POST)
        );
        if ($edit_id > 0) {
            $args['edit'] = $edit_id;
        }

        self::redirect_to_url(add_query_arg($args, admin_url('admin.php')));
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array<string,mixed> $extra_args
     */
    private static function redirect_exam_snapshot_page(array $exam_list_state, array $extra_args = []): void
    {
        $preview_request = array_merge((array) $_GET, (array) $_POST, (array) $_REQUEST);
        $snapshot_filter_state = self::get_exam_snapshot_filter_state_from_request($preview_request);
        $snapshot_tab = self::get_exam_snapshot_tab_from_request($preview_request);
        $snapshot_filter_state['exam_ids'] = self::normalize_snapshot_exam_selection_for_tab(
            (array) ($snapshot_filter_state['exam_ids'] ?? []),
            $snapshot_tab
        );
        $snapshot_filter_state['exam_id'] = !empty($snapshot_filter_state['exam_ids']) ? (int) $snapshot_filter_state['exam_ids'][0] : 0;
        $selected_exam_ids = array_values(array_filter(array_map('intval', (array) ($snapshot_filter_state['exam_ids'] ?? []))));
        if (empty($selected_exam_ids) && !empty($snapshot_filter_state['exam_id'])) {
            $selected_exam_ids[] = (int) $snapshot_filter_state['exam_id'];
        }
        $exam_readiness_pages = self::get_exam_readiness_pages_from_request($preview_request, $selected_exam_ids);
        $args = self::add_exam_readiness_page_args(
            self::add_exam_snapshot_preview_page_args(
                self::add_student_snapshot_filter_state_args(
                    self::add_exam_snapshot_tab_args(
                        self::add_exam_snapshot_filter_state_args(
                            self::add_exam_list_state_args(
                                array_merge(
                                    [
                                        'page' => 'cbt-exams',
                                        'cbt_exam_panel' => 'snapshot',
                                    ],
                                    $extra_args
                                ),
                                $exam_list_state
                            ),
                            $snapshot_filter_state
                        ),
                        $snapshot_tab
                    ),
                    self::get_student_snapshot_filter_state_from_request($preview_request)
                ),
                self::get_exam_snapshot_preview_pages_from_request($preview_request)
            ),
            $exam_readiness_pages,
            self::get_exam_readiness_page_from_request($preview_request)
        );

        self::redirect_to_url(add_query_arg($args, admin_url('admin.php')));
    }

    /**
     * @param array<string,mixed> $args
     * @param array<int,int> $preview_pages
     * @return array<string,mixed>
     */
    public static function add_exam_snapshot_preview_page_args(array $args, array $preview_pages): array
    {
        foreach ($preview_pages as $exam_id => $page) {
            $exam_id = absint($exam_id);
            if ($exam_id <= 0) {
                continue;
            }

            $args['cbt_exam_snapshot_page_' . $exam_id] = max(1, (int) $page);
        }

        return $args;
    }

    private static function redirect_to_url(string $url): void
    {
        wp_safe_redirect($url);
        if (defined('PHPUNIT_COMPOSER_INSTALL')) {
            throw new RuntimeException(self::TEST_REDIRECT_SIGNAL);
        }
        exit;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_exam_save_progress_payload(array $state): array
    {
        $phase = sanitize_key((string) ($state['phase'] ?? 'sync'));
        $phase_labels = [
            'sync' => 'Sinkron Soal',
            'cleanup' => 'Bersihkan Soal Lama',
            'finalize' => 'Finalize Exam',
            'complete' => 'Selesai',
        ];
        $total_sources = max(0, (int) ($state['total_sources'] ?? 0));
        $processed_sources = max(0, min($total_sources, (int) ($state['processed_sources'] ?? 0)));
        if ($total_sources > 0) {
            if ($phase === 'sync') {
                $percent = 8 + (((float) $processed_sources / (float) $total_sources) * 84);
            } elseif ($phase === 'cleanup') {
                $percent = 94.0;
            } elseif ($phase === 'finalize') {
                $percent = 98.0;
            } else {
                $percent = 100.0;
            }
        } else {
            $percent = ($phase === 'complete') ? 100.0 : 92.0;
        }

        $stats = [];
        if ($total_sources > 0) {
            $stats[] = sprintf('%d/%d soal', $processed_sources, $total_sources);
        }
        $updated_existing_count = max(0, (int) ($state['updated_existing_count'] ?? 0));
        $created_new_count = max(0, (int) ($state['created_new_count'] ?? 0));
        $linked_legacy_match_count = max(0, (int) ($state['linked_legacy_match_count'] ?? 0));
        $removed_question_count = max(0, (int) ($state['removed_question_count'] ?? 0));
        $archived_question_count = max(0, (int) ($state['archived_question_count'] ?? 0));
        $deleted_question_count = max(0, (int) ($state['deleted_question_count'] ?? 0));
        $preserved_attempt_history_count = max(0, (int) ($state['preserved_attempt_history_count'] ?? 0));
        $legacy_active_count = max(0, (int) ($state['legacy_active_count'] ?? 0));
        if ($updated_existing_count > 0) {
            $stats[] = 'Update: ' . $updated_existing_count;
        }
        if ($created_new_count > 0) {
            $stats[] = 'Tambah baru: ' . $created_new_count;
        }
        if ($linked_legacy_match_count > 0) {
            $stats[] = 'Relink legacy: ' . $linked_legacy_match_count;
        }
        if ($removed_question_count > 0) {
            $stats[] = 'Dibersihkan: ' . $removed_question_count;
        }
        if ($archived_question_count > 0) {
            $stats[] = 'Arsip: ' . $archived_question_count;
        }
        if ($deleted_question_count > 0) {
            $stats[] = 'Hapus: ' . $deleted_question_count;
        }
        if ($preserved_attempt_history_count > 0) {
            $stats[] = 'Preserve: ' . $preserved_attempt_history_count;
        }
        if ($legacy_active_count > 0) {
            $stats[] = 'Masih legacy: ' . $legacy_active_count;
        }

        return [
            'phase' => $phase,
            'phase_label' => $phase_labels[$phase] ?? 'Memproses Exam',
            'message' => (string) ($state['message'] ?? 'Memproses exam.'),
            'processed_questions' => $processed_sources,
            'total_questions' => $total_sources,
            'updated_existing_count' => $updated_existing_count,
            'created_new_count' => $created_new_count,
            'linked_legacy_match_count' => $linked_legacy_match_count,
            'removed_question_count' => $removed_question_count,
            'archived_question_count' => $archived_question_count,
            'deleted_question_count' => $deleted_question_count,
            'preserved_attempt_history_count' => $preserved_attempt_history_count,
            'legacy_active_count' => $legacy_active_count,
            'percent' => round(min(100.0, max(0.0, $percent)), 2),
            'complete' => ($phase === 'complete'),
            'redirect_url' => (string) ($state['redirect_url'] ?? ''),
            'stats' => implode(' | ', $stats),
        ];
    }

    public static function to_datetime_local(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timezone = wp_timezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        if (!$dt) {
            $timestamp = strtotime($value);
            if (!$timestamp) {
                return '';
            }
            $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        }

        if (!$dt) {
            return '';
        }

        return $dt->format('Y-m-d\TH:i');
    }

    public static function from_datetime_local(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timezone = wp_timezone();
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, $timezone);
        if (!$dt) {
            $timestamp = strtotime($value);
            if (!$timestamp) {
                return null;
            }
            $dt = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        }

        if (!$dt) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }
}
