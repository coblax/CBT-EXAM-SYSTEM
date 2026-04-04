<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Exams_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Exams_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        $preview_exam_id = isset($_GET['preview_exam_id']) ? absint(wp_unslash((string) $_GET['preview_exam_id'])) : 0;
        if ($preview_exam_id > 0) {
            $context = CBT_Admin_Exams_Service::build_preview_context($_GET);
            extract($context, EXTR_SKIP);

            require CBT_EXAM_SYSTEM_PATH . 'admin/views/exams/preview.php';
            return;
        }

        $context = CBT_Admin_Exams_Service::build_page_context($_GET);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/exams/page.php';
    }

    /**
     * @param array{
     *   subjects:array<int,array<string,mixed>>,
     *   exam_status_labels:array<string,string>,
     *   exam_list_state:array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string},
     *   exam_list_kelas_options:array<int,string>,
     *   exam_per_page:int,
     *   exam_active_filters:array<int,array{label:string,value:string}>,
     *   exam_snapshot_filter_state:array{exam_id:int},
     *   exam_snapshot_exam_options:array<int,array{id:int,title:string}>,
     *   exam_snapshot_total:int,
     *   exam_snapshot_rows:array<int,array<string,mixed>>,
     *   exam_snapshot_preview_pages:array<int,int>,
     *   exam_snapshot_reset_url:string
     * } $args
     */
    public static function render_snapshot_panel(array $args): void
    {
        $subjects = isset($args['subjects']) && is_array($args['subjects']) ? $args['subjects'] : [];
        $exam_status_labels = isset($args['exam_status_labels']) && is_array($args['exam_status_labels']) ? $args['exam_status_labels'] : [];
        $exam_list_state = isset($args['exam_list_state']) && is_array($args['exam_list_state']) ? $args['exam_list_state'] : [
            'per_page' => 20,
            'paged' => 1,
            'search' => '',
            'status' => '',
            'subject_id' => 0,
            'kelas' => '',
        ];
        $exam_list_kelas_options = isset($args['exam_list_kelas_options']) && is_array($args['exam_list_kelas_options']) ? $args['exam_list_kelas_options'] : [];
        $exam_per_page = max(20, (int) ($args['exam_per_page'] ?? 20));
        $exam_active_filters = isset($args['exam_active_filters']) && is_array($args['exam_active_filters']) ? $args['exam_active_filters'] : [];
        $exam_snapshot_filter_state = isset($args['exam_snapshot_filter_state']) && is_array($args['exam_snapshot_filter_state'])
            ? $args['exam_snapshot_filter_state']
            : ['exam_id' => 0];
        $exam_snapshot_exam_options = isset($args['exam_snapshot_exam_options']) && is_array($args['exam_snapshot_exam_options'])
            ? $args['exam_snapshot_exam_options']
            : [];
        $exam_snapshot_total = max(0, (int) ($args['exam_snapshot_total'] ?? 0));
        $exam_snapshot_rows = isset($args['exam_snapshot_rows']) && is_array($args['exam_snapshot_rows']) ? $args['exam_snapshot_rows'] : [];
        $exam_snapshot_preview_pages = isset($args['exam_snapshot_preview_pages']) && is_array($args['exam_snapshot_preview_pages'])
            ? $args['exam_snapshot_preview_pages']
            : [];
        $exam_snapshot_reset_url = (string) ($args['exam_snapshot_reset_url'] ?? admin_url('admin.php?page=cbt-exams&cbt_exam_panel=snapshot'));
        ?>
        <div class="cbt-exam-snapshot-shell">
            <h2>Snapshot Soal</h2>
            <p class="description cbt-exam-list-description">Pantau kesiapan snapshot Redis untuk delivery payload siswa, lalu siapkan satu exam atau seluruh hasil filter sebelum ujian dimulai.</p>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-exam-list-toolbar" id="cbt-exam-snapshot-filter-form">
                <input type="hidden" name="page" value="cbt-exams" />
                <input type="hidden" name="cbt_exam_panel" value="snapshot" />
                <div class="cbt-exam-list-toolbar-grid">
                    <div class="cbt-exam-list-toolbar-field cbt-exam-list-toolbar-field--search">
                        <label for="cbt-exam-snapshot-exam">Exam</label>
                        <select id="cbt-exam-snapshot-exam" name="cbt_exam_snapshot_exam_id">
                            <option value="0">Semua exam</option>
                            <?php foreach ($exam_snapshot_exam_options as $exam_snapshot_exam_option): ?>
                                <?php $snapshot_exam_option_id = (int) ($exam_snapshot_exam_option['id'] ?? 0); ?>
                                <option value="<?php echo (int) $snapshot_exam_option_id; ?>" <?php echo ((int) ($exam_snapshot_filter_state['exam_id'] ?? 0) === $snapshot_exam_option_id) ? 'selected="selected"' : ''; ?>>
                                    <?php echo esc_html((string) ($exam_snapshot_exam_option['title'] ?? ('Exam #' . $snapshot_exam_option_id))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cbt-exam-list-toolbar-field">
                        <label for="cbt-exam-snapshot-subject">Mapel</label>
                        <select id="cbt-exam-snapshot-subject" name="cbt_exam_subject">
                            <option value="0">Semua mapel</option>
                            <?php foreach ($subjects as $subject): ?>
                                <?php
                                $subject_id = (int) ($subject['id'] ?? 0);
                                $subject_name = (string) ($subject['name'] ?? '');
                                $subject_code = trim((string) ($subject['code'] ?? ''));
                                $subject_label = $subject_name !== ''
                                    ? $subject_name . ($subject_code !== '' ? ' (' . $subject_code . ')' : '')
                                    : 'Mapel';
                                ?>
                                <option value="<?php echo (int) $subject_id; ?>" <?php echo ((int) ($exam_list_state['subject_id'] ?? 0) === $subject_id) ? 'selected="selected"' : ''; ?>>
                                    <?php echo esc_html($subject_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cbt-exam-list-toolbar-field">
                        <label for="cbt-exam-snapshot-status">Status</label>
                        <select id="cbt-exam-snapshot-status" name="cbt_exam_status">
                            <option value="">Semua status</option>
                            <?php foreach ($exam_status_labels as $status_key => $status_label): ?>
                                <option value="<?php echo esc_attr((string) $status_key); ?>" <?php echo ((string) ($exam_list_state['status'] ?? '') === (string) $status_key) ? 'selected="selected"' : ''; ?>>
                                    <?php echo esc_html((string) $status_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cbt-exam-list-toolbar-field">
                        <label for="cbt-exam-snapshot-kelas">Kelas</label>
                        <select id="cbt-exam-snapshot-kelas" name="cbt_exam_kelas">
                            <option value="">Semua kelas</option>
                            <?php foreach ($exam_list_kelas_options as $kelas_option): ?>
                                <option value="<?php echo esc_attr((string) $kelas_option); ?>" <?php echo ((string) ($exam_list_state['kelas'] ?? '') === (string) $kelas_option) ? 'selected="selected"' : ''; ?>>
                                    <?php echo esc_html((string) $kelas_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cbt-exam-list-toolbar-field">
                        <label for="cbt-exam-snapshot-per-page">Per halaman</label>
                        <select id="cbt-exam-snapshot-per-page" name="cbt_exam_per_page">
                            <?php foreach ([20, 40, 60, 80, 100] as $per_page_option): ?>
                                <option value="<?php echo (int) $per_page_option; ?>" <?php echo ((int) $exam_per_page === (int) $per_page_option) ? 'selected="selected"' : ''; ?>>
                                    <?php echo esc_html((string) $per_page_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cbt-exam-list-toolbar-actions">
                        <a href="<?php echo esc_url($exam_snapshot_reset_url); ?>" class="button cbt-exam-list-reset">Reset</a>
                    </div>
                </div>
            </form>

            <div class="cbt-exam-snapshot-actions-bar">
                <div class="cbt-exam-snapshot-actions-copy">
                    <strong><?php echo esc_html(sprintf('%d exam terfilter', $exam_snapshot_total)); ?></strong>
                    <span>Bulk warm hanya memproses exam yang cocok dengan filter aktif saat ini.</span>
                </div>
                <div class="cbt-exam-snapshot-bulk-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                        <?php wp_nonce_field('cbt_warm_bulk_exam_delivery_snapshots'); ?>
                        <input type="hidden" name="action" value="cbt_warm_bulk_exam_delivery_snapshots" />
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                        <button type="submit" class="button button-primary" <?php echo empty($exam_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Siapkan Semua Snapshot</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-bulk-form">
                        <?php wp_nonce_field('cbt_clear_bulk_exam_delivery_snapshots'); ?>
                        <input type="hidden" name="action" value="cbt_clear_bulk_exam_delivery_snapshots" />
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($exam_snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($exam_snapshot_preview_pages); ?>
                        <button type="submit" class="button" <?php echo empty($exam_snapshot_rows) ? 'disabled="disabled"' : ''; ?>>Bersihkan Semua Snapshot</button>
                    </form>
                </div>
            </div>

            <?php if (!empty($exam_active_filters)): ?>
                <div class="cbt-exam-list-active-filters" aria-label="Filter snapshot aktif">
                    <span class="cbt-exam-list-active-summary"><?php echo esc_html(sprintf('%d exam cocok', $exam_snapshot_total)); ?></span>
                    <?php foreach ($exam_active_filters as $active_filter): ?>
                        <span class="cbt-exam-list-active-chip">
                            <strong><?php echo esc_html((string) ($active_filter['label'] ?? 'Filter')); ?></strong>
                            <span><?php echo esc_html((string) ($active_filter['value'] ?? '')); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="cbt-exam-list-table-wrap">
                <table class="widefat striped cbt-exam-snapshot-table">
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Ringkasan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exam_snapshot_rows)): ?>
                            <tr>
                                <td colspan="3"><?php echo !empty($exam_active_filters) ? 'Tidak ada exam yang cocok dengan filter saat ini.' : 'Belum ada exam yang bisa diperiksa snapshot-nya.'; ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($exam_snapshot_rows as $row): ?>
                                <?php self::render_snapshot_row($row, $exam_list_state, $exam_snapshot_filter_state, $exam_snapshot_preview_pages); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $row
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     */
    private static function render_snapshot_row(array $row, array $exam_list_state, array $snapshot_filter_state = ['exam_id' => 0], array $preview_pages = []): void
    {
        $exam_id = (int) ($row['exam_id'] ?? $row['id'] ?? 0);
        $title = trim((string) ($row['title'] ?? '')) !== '' ? (string) $row['title'] : ('Exam #' . $exam_id);
        $subject_name = trim((string) ($row['subject_name'] ?? ''));
        $exam_status = trim((string) ($row['status'] ?? ''));
        $snapshot_status_label = trim((string) ($row['snapshot_status_label'] ?? 'MISS')) !== ''
            ? (string) $row['snapshot_status_label']
            : 'MISS';
        $snapshot_status_tone = sanitize_html_class((string) ($row['snapshot_status_tone'] ?? 'warning'), 'warning');
        $revision_meta = is_array($row['revision_meta'] ?? null) ? $row['revision_meta'] : [];
        $revision_version = max(1, (int) ($revision_meta['version'] ?? 1));
        $invalidated_at = trim((string) ($revision_meta['invalidated_at'] ?? ''));
        $signature = trim((string) ($revision_meta['signature'] ?? ''));
        $storage_key = trim((string) ($row['storage_key'] ?? ''));
        $preview_question_ids = array_values(array_filter(array_map('intval', (array) ($row['preview_question_ids'] ?? []))));
        $preview_items = array_values(array_filter((array) ($row['preview_items'] ?? []), static function ($item): bool {
            return is_array($item);
        }));
        $ttl_seconds = (int) ($row['snapshot_ttl_seconds'] ?? -2);
        $ttl_label = $ttl_seconds >= 0 ? $ttl_seconds . 's' : 'N/A';
        $payload_bytes = max(0, (int) ($row['snapshot_payload_bytes'] ?? 0));
        $payload_bytes_label = $payload_bytes > 0 ? number_format_i18n($payload_bytes) . ' bytes' : '0 bytes';
        $snapshot_message = trim((string) ($row['snapshot_message'] ?? ''));
        $redis_host = trim((string) ($row['redis_host'] ?? ''));
        $redis_database = (int) ($row['redis_database'] ?? 0);
        $redis_error = trim((string) ($row['redis_error'] ?? ''));
        $snapshot_item_count = max(0, (int) ($row['snapshot_item_count'] ?? 0));
        $preview_current_page = max(1, (int) ($row['preview_current_page'] ?? 1));
        $preview_total_pages = max(1, (int) ($row['preview_total_pages'] ?? 1));
        $preview_per_page = max(1, (int) ($row['preview_per_page'] ?? 7));
        $preview_is_expanded = !empty($row['preview_is_expanded']);
        $preview_start = $snapshot_item_count > 0 ? ((($preview_current_page - 1) * $preview_per_page) + 1) : 0;
        $preview_end = $snapshot_item_count > 0 ? min($snapshot_item_count, $preview_start + count($preview_items) - 1) : 0;
        $preview_range_label = $snapshot_item_count > 0
            ? sprintf('%d-%d dari %d', $preview_start, $preview_end, $snapshot_item_count)
            : '0 dari 0';
        $preview_summary_label = $snapshot_item_count > 0
            ? 'Preview Soal (' . $preview_range_label . ')'
            : 'Preview Soal';
        ?>
        <tr class="cbt-exam-snapshot-summary-row" data-cbt-exam-snapshot-summary-row="<?php echo esc_attr((string) $exam_id); ?>">
            <td class="cbt-exam-snapshot-exam-cell">
                <div class="cbt-exam-snapshot-title"><?php echo esc_html($title); ?></div>
                <div class="cbt-exam-snapshot-meta">
                    <?php if ($subject_name !== ''): ?>
                        <span><strong>Mapel:</strong> <?php echo esc_html($subject_name); ?></span>
                    <?php endif; ?>
                    <?php if ($exam_status !== ''): ?>
                        <span><strong>Status Exam:</strong> <?php echo esc_html($exam_status); ?></span>
                    <?php endif; ?>
                    <span><strong>Exam ID:</strong> <?php echo esc_html((string) $exam_id); ?></span>
                </div>
                <div class="cbt-exam-snapshot-detail-grid">
                    <span><strong>Storage Key:</strong> <code><?php echo esc_html($storage_key !== '' ? $storage_key : '-'); ?></code></span>
                    <span><strong>Host Redis:</strong> <code><?php echo esc_html($redis_host !== '' ? $redis_host : '-'); ?></code></span>
                    <span><strong>Database Redis:</strong> <?php echo esc_html((string) $redis_database); ?></span>
                    <span><strong>Invalidated At:</strong> <code><?php echo esc_html($invalidated_at !== '' ? $invalidated_at : '-'); ?></code></span>
                    <span><strong>Signature:</strong> <code><?php echo esc_html($signature !== '' ? $signature : '-'); ?></code></span>
                    <span><strong>Question IDs:</strong> <code><?php echo esc_html(!empty($preview_question_ids) ? implode(', ', $preview_question_ids) : '-'); ?></code></span>
                    <?php if ($redis_error !== ''): ?>
                        <span><strong>Error Redis:</strong> <code><?php echo esc_html($redis_error); ?></code></span>
                    <?php endif; ?>
                </div>
                <?php if ($snapshot_message !== ''): ?>
                    <p class="cbt-exam-snapshot-note"><?php echo esc_html($snapshot_message); ?></p>
                <?php endif; ?>
            </td>
            <td class="cbt-exam-snapshot-summary-cell">
                <div class="cbt-exam-snapshot-summary-grid">
                    <div class="cbt-exam-snapshot-summary-card cbt-exam-snapshot-summary-card--status">
                        <span class="cbt-exam-snapshot-summary-label">Status</span>
                        <span class="cbt-exam-snapshot-status is-<?php echo esc_attr($snapshot_status_tone); ?>"><?php echo esc_html($snapshot_status_label); ?></span>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Revision</span>
                        <div class="cbt-exam-snapshot-summary-stack">
                            <strong>v<?php echo esc_html((string) $revision_version); ?></strong>
                            <span><?php echo esc_html($signature !== '' ? $signature : '-'); ?></span>
                        </div>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Jumlah Soal</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html((string) $snapshot_item_count); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">TTL</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($ttl_label); ?></strong>
                    </div>
                    <div class="cbt-exam-snapshot-summary-card">
                        <span class="cbt-exam-snapshot-summary-label">Ukuran Payload</span>
                        <strong class="cbt-exam-snapshot-summary-value"><?php echo esc_html($payload_bytes_label); ?></strong>
                    </div>
                </div>
            </td>
            <td>
                <div class="cbt-exam-snapshot-row-actions">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_warm_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_warm_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <button type="submit" class="button button-secondary">Siapkan Snapshot Soal</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-exam-snapshot-row-form">
                        <?php wp_nonce_field('cbt_clear_exam_delivery_snapshot'); ?>
                        <input type="hidden" name="action" value="cbt_clear_exam_delivery_snapshot" />
                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                        <?php self::render_exam_list_state_hidden_fields($exam_list_state); ?>
                        <?php self::render_snapshot_filter_state_hidden_fields($snapshot_filter_state); ?>
                        <?php self::render_snapshot_preview_page_hidden_fields($preview_pages); ?>
                        <button type="submit" class="button">Bersihkan Snapshot Soal</button>
                    </form>
                </div>
            </td>
        </tr>
        <tr class="cbt-exam-snapshot-preview-row" data-cbt-exam-snapshot-preview-row="<?php echo esc_attr((string) $exam_id); ?>">
            <td colspan="3" class="cbt-exam-snapshot-preview-row-cell">
                <details class="cbt-exam-snapshot-preview-dropdown" data-cbt-exam-snapshot-preview-dropdown="<?php echo esc_attr((string) $exam_id); ?>" <?php echo $preview_is_expanded ? 'open="open"' : ''; ?>>
                    <summary class="cbt-exam-snapshot-preview-summary">
                        <span class="cbt-exam-snapshot-preview-summary-title"><?php echo esc_html($preview_summary_label); ?></span>
                        <span class="cbt-exam-snapshot-preview-summary-meta">
                            <span><?php echo esc_html('Hal. ' . $preview_current_page . ' / ' . $preview_total_pages); ?></span>
                            <span><?php echo esc_html($snapshot_item_count . ' soal'); ?></span>
                        </span>
                    </summary>
                    <div class="cbt-exam-snapshot-preview-body">
                        <?php if (empty($preview_items)): ?>
                            <p class="cbt-exam-snapshot-note">Belum ada preview soal pada snapshot ini.</p>
                        <?php else: ?>
                            <div class="cbt-exam-snapshot-preview-list">
                                <?php foreach ($preview_items as $preview_item): ?>
                                    <?php
                                    $preview_question_id = (int) ($preview_item['id'] ?? 0);
                                    $preview_question_type = (string) ($preview_item['question_type'] ?? '');
                                    $preview_points = (float) ($preview_item['points'] ?? 0);
                                    $preview_text = (string) ($preview_item['question_text_excerpt'] ?? '');
                                    $preview_option_count = (int) ($preview_item['option_count'] ?? 0);
                                    ?>
                                    <article class="cbt-exam-snapshot-preview-item">
                                        <div class="cbt-exam-snapshot-preview-head">
                                            <span class="cbt-exam-snapshot-preview-pill">Question #<?php echo esc_html((string) $preview_question_id); ?></span>
                                            <span class="cbt-exam-snapshot-preview-pill"><?php echo esc_html($preview_question_type !== '' ? $preview_question_type : 'unknown'); ?></span>
                                            <span class="cbt-exam-snapshot-preview-pill">Poin <?php echo esc_html(number_format_i18n($preview_points, 2)); ?></span>
                                            <span class="cbt-exam-snapshot-preview-pill">Opsi <?php echo esc_html((string) $preview_option_count); ?></span>
                                        </div>
                                        <div class="cbt-exam-snapshot-preview-text"><?php echo esc_html($preview_text !== '' ? $preview_text : '(teks soal kosong)'); ?></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($preview_total_pages > 1): ?>
                            <div class="cbt-exam-snapshot-preview-pagination" aria-label="<?php echo esc_attr('Pagination preview exam ' . $exam_id); ?>">
                                <?php if ($preview_current_page > 1): ?>
                                    <a class="button button-secondary" href="<?php echo esc_url(self::build_snapshot_preview_page_url($exam_id, $preview_current_page - 1, $exam_list_state, $snapshot_filter_state, $preview_pages)); ?>">Sebelumnya</a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" aria-disabled="true">Sebelumnya</span>
                                <?php endif; ?>

                                <span class="cbt-exam-snapshot-preview-pagination-state">
                                    <?php echo esc_html('Halaman ' . $preview_current_page . ' dari ' . $preview_total_pages); ?>
                                </span>

                                <?php if ($preview_current_page < $preview_total_pages): ?>
                                    <a class="button button-secondary" href="<?php echo esc_url(self::build_snapshot_preview_page_url($exam_id, $preview_current_page + 1, $exam_list_state, $snapshot_filter_state, $preview_pages)); ?>">Berikutnya</a>
                                <?php else: ?>
                                    <span class="button button-secondary disabled" aria-disabled="true">Berikutnya</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            </td>
        </tr>
        <?php
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     */
    private static function render_exam_list_state_hidden_fields(array $exam_list_state): void
    {
        ?>
        <input type="hidden" name="cbt_exam_per_page" value="<?php echo esc_attr((string) ((int) ($exam_list_state['per_page'] ?? 20))); ?>" />
        <input type="hidden" name="cbt_exam_paged" value="<?php echo esc_attr((string) ((int) ($exam_list_state['paged'] ?? 1))); ?>" />
        <input type="hidden" name="cbt_exam_search" value="<?php echo esc_attr((string) ($exam_list_state['search'] ?? '')); ?>" />
        <input type="hidden" name="cbt_exam_status" value="<?php echo esc_attr((string) ($exam_list_state['status'] ?? '')); ?>" />
        <input type="hidden" name="cbt_exam_subject" value="<?php echo esc_attr((string) ((int) ($exam_list_state['subject_id'] ?? 0))); ?>" />
        <input type="hidden" name="cbt_exam_kelas" value="<?php echo esc_attr((string) ($exam_list_state['kelas'] ?? '')); ?>" />
        <?php
    }

    /**
     * @param array<int,int> $preview_pages
     */
    private static function render_snapshot_preview_page_hidden_fields(array $preview_pages): void
    {
        foreach ($preview_pages as $exam_id => $page) {
            $exam_id = absint($exam_id);
            if ($exam_id <= 0) {
                continue;
            }
            ?>
            <input type="hidden" name="<?php echo esc_attr('cbt_exam_snapshot_page_' . $exam_id); ?>" value="<?php echo esc_attr((string) max(1, (int) $page)); ?>" />
            <?php
        }
    }

    /**
     * @param array{exam_id:int} $snapshot_filter_state
     */
    private static function render_snapshot_filter_state_hidden_fields(array $snapshot_filter_state): void
    {
        if (!empty($snapshot_filter_state['exam_id'])) {
            ?>
            <input type="hidden" name="cbt_exam_snapshot_exam_id" value="<?php echo esc_attr((string) ((int) $snapshot_filter_state['exam_id'])); ?>" />
            <?php
        }
    }

    /**
     * @param array{per_page:int,paged:int,search:string,status:string,subject_id:int,kelas:string} $exam_list_state
     * @param array{exam_id:int} $snapshot_filter_state
     * @param array<int,int> $preview_pages
     */
    private static function build_snapshot_preview_page_url(int $exam_id, int $page, array $exam_list_state, array $snapshot_filter_state, array $preview_pages): string
    {
        $args = CBT_Admin_Exams_Service::add_exam_snapshot_filter_state_args(
            CBT_Admin_Exams_Service::add_exam_list_state_args(
                [
                    'page' => 'cbt-exams',
                    'cbt_exam_panel' => 'snapshot',
                ],
                $exam_list_state
            ),
            $snapshot_filter_state
        );

        foreach ($preview_pages as $target_exam_id => $target_page) {
            $target_exam_id = absint($target_exam_id);
            if ($target_exam_id <= 0) {
                continue;
            }

            $args['cbt_exam_snapshot_page_' . $target_exam_id] = max(1, (int) $target_page);
        }

        if ($exam_id > 0) {
            $args['cbt_exam_snapshot_page_' . $exam_id] = max(1, $page);
        }

        return add_query_arg($args, admin_url('admin.php'));
    }
}
