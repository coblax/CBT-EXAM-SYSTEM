        <?php if (!empty($error_message)): ?>
            <div class="wrap">
                <h1>Preview Soal Exam</h1>
                <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
                <p><a class="button" href="<?php echo esc_url($back_url); ?>">Kembali ke Daftar Exam</a></p>
            </div>
        <?php else: ?>
        <div class="wrap cbt-admin-exam-preview-wrap">
            <div class="cbt-admin-exam-preview-topbar">
                <div>
                    <h1>Preview Soal Exam</h1>
                    <p class="description">Validasi cepat isi soal dan kunci jawaban sebelum exam dijalankan.</p>
                </div>
                <div class="cbt-admin-exam-preview-actions">
                    <?php
                    $print_question_modes = [
                        [
                            'mode' => CBT_Admin_Exams_Service::EXAM_QUESTION_PRINT_MODE_STUDENT,
                            'label' => 'Print Soal Siswa',
                            'class' => 'button button-primary',
                        ],
                        [
                            'mode' => CBT_Admin_Exams_Service::EXAM_QUESTION_PRINT_MODE_TEACHER,
                            'label' => 'Print Soal + Kunci',
                            'class' => 'button',
                        ],
                    ];
                    $print_questions_disabled = empty($questions);
                    ?>
                    <?php foreach ($print_question_modes as $print_question_mode): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank" class="cbt-admin-exam-preview-print-form">
                            <?php wp_nonce_field('cbt_print_exam_questions'); ?>
                            <input type="hidden" name="action" value="cbt_print_exam_questions" />
                            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam_id); ?>" />
                            <input type="hidden" name="cbt_exam_question_print_mode" value="<?php echo esc_attr((string) ($print_question_mode['mode'] ?? '')); ?>" />
                            <input type="hidden" name="cbt_exam_per_page" value="<?php echo esc_attr((string) ((int) ($exam_list_state['per_page'] ?? 20))); ?>" />
                            <input type="hidden" name="cbt_exam_paged" value="<?php echo esc_attr((string) ((int) ($exam_list_state['paged'] ?? 1))); ?>" />
                            <input type="hidden" name="cbt_exam_search" value="<?php echo esc_attr((string) ($exam_list_state['search'] ?? '')); ?>" />
                            <input type="hidden" name="cbt_exam_status" value="<?php echo esc_attr((string) ($exam_list_state['status'] ?? '')); ?>" />
                            <input type="hidden" name="cbt_exam_subject" value="<?php echo esc_attr((string) ((int) ($exam_list_state['subject_id'] ?? 0))); ?>" />
                            <input type="hidden" name="cbt_exam_kelas" value="<?php echo esc_attr((string) ($exam_list_state['kelas'] ?? '')); ?>" />
                            <button type="submit" class="<?php echo esc_attr((string) ($print_question_mode['class'] ?? 'button')); ?>" <?php echo $print_questions_disabled ? 'disabled="disabled"' : ''; ?>>
                                <?php echo esc_html((string) ($print_question_mode['label'] ?? 'Print Soal')); ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                    <a class="button" href="<?php echo esc_url($back_url); ?>">Kembali ke Daftar Exam</a>
                </div>
            </div>

            <section class="cbt-admin-exam-preview-meta">
                <div class="cbt-admin-exam-preview-meta-item cbt-admin-exam-preview-meta-main">
                    <div class="cbt-admin-meta-kicker">Judul Exam</div>
                    <div class="cbt-admin-meta-title"><?php echo esc_html((string) ($exam['title'] ?? '')); ?></div>
                    <div class="cbt-admin-meta-subtitle">
                        <?php echo esc_html((string) ($exam['subject_name'] ?? '-')); ?>
                        | Status: <?php echo esc_html((string) ($exam['status'] ?? 'draft')); ?>
                    </div>
                </div>
                <div class="cbt-admin-exam-preview-meta-item">
                    <div class="cbt-admin-meta-kicker">Jumlah Soal</div>
                    <div class="cbt-admin-meta-value"><?php echo esc_html((string) $question_count); ?></div>
                </div>
                <div class="cbt-admin-exam-preview-meta-item">
                    <div class="cbt-admin-meta-kicker">Total Poin</div>
                    <div class="cbt-admin-meta-value"><?php echo esc_html(number_format($total_points, 2)); ?></div>
                </div>
                <div class="cbt-admin-exam-preview-meta-item">
                    <div class="cbt-admin-meta-kicker">Jadwal</div>
                    <div class="cbt-admin-meta-inline"><?php echo esc_html($schedule_text); ?></div>
                </div>
            </section>

            <?php if (!empty($question_type_counts)): ?>
                <div class="cbt-admin-exam-preview-type-chips">
                    <?php foreach ($question_type_counts as $question_type => $question_type_count): ?>
                        <?php
                        if ((int) $question_type_count <= 0) {
                            continue;
                        }
                        $type_label = (string) ($question_type_labels[$question_type] ?? ucwords(str_replace('_', ' ', $question_type)));
                        ?>
                        <span class="cbt-admin-type-chip">
                            <?php echo esc_html($type_label); ?>: <?php echo esc_html((string) $question_type_count); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($questions)): ?>
                <div class="notice notice-warning"><p>Exam ini belum memiliki soal.</p></div>
            <?php else: ?>
                <div class="cbt-admin-review-list cbt-admin-student-preview-list">
                    <?php foreach ($questions as $index => $question): ?>
                        <?php
                        $question_id = (int) ($question['id'] ?? 0);
                        $question_type = (string) ($question['question_type'] ?? '');
                        $type_label = (string) ($question_type_labels[$question_type] ?? ucwords(str_replace('_', ' ', $question_type)));
                        $options = (array) ($options_map[$question_id] ?? []);
                        $question_detail = CBT_Admin_Questions_Helper::get_question_type_detail($question_id, $question_type);
                        $source_question_id = (int) ($question['source_question_id'] ?? 0);
                        $source_exam_title = (string) ($question['source_exam_title'] ?? '');
                        $is_bank_backed = $source_question_id > 0 && stripos($source_exam_title, 'Bank Soal - ') === 0;
                        $edit_question_url = '';
                        $edit_question_label = 'Edit Soal';
                        $edit_question_hint = '';
                        if ($can_manage_questions && $question_id > 0) {
                            $edit_target_id = $is_bank_backed ? $source_question_id : $question_id;
                            $edit_question_url = add_query_arg(
                                [
                                    'page' => 'cbt-question-bank',
                                    'edit' => $edit_target_id,
                                ],
                                admin_url('admin.php')
                            );
                            if ($is_bank_backed) {
                                $edit_question_label = 'Edit Sumber Bank';
                                $edit_question_hint = 'Row exam ini adalah turunan operasional. Perubahan diarahkan ke soal master di Bank Soal agar sinkronisasi tetap satu arah.';
                            }
                        }
                        $preview_meta_lines = [];
                        $preview_subject_name = trim((string) ($question['subject_name'] ?? ''));
                        if ($preview_subject_name !== '') {
                            $preview_meta_lines[] = 'Mapel: ' . $preview_subject_name;
                        }
                        if ($source_exam_title !== '') {
                            $preview_meta_lines[] = 'Sumber: ' . $source_exam_title;
                        }
                        $preview_extra_chips = [];
                        if ($is_bank_backed) {
                            $preview_extra_chips[] = [
                                'label' => 'Bank-backed',
                                'tone' => 'source',
                            ];
                        }
                        $preview_actions_html = '';
                        if ($edit_question_url !== '') {
                            $preview_actions_html = sprintf(
                                '<a class="button button-secondary" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                                esc_url($edit_question_url),
                                esc_html($edit_question_label)
                            );
                        }
                        ?>
                        <?php echo CBT_Admin_Questions_Helper::render_admin_student_preview_card(
                            $question,
                            $options,
                            $question_detail,
                            [
                                'eyebrow' => 'Soal ' . (string) ($index + 1),
                                'type_label' => $type_label,
                                'meta_lines' => $preview_meta_lines,
                                'extra_chips' => $preview_extra_chips,
                                'note_text' => $edit_question_hint,
                                'actions_html' => $preview_actions_html,
                            ]
                        ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <style>
            .cbt-admin-exam-preview-wrap {
                max-width: 1180px;
            }
            .cbt-admin-exam-preview-topbar {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 14px;
                flex-wrap: wrap;
            }
            .cbt-admin-exam-preview-actions {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
            }
            .cbt-admin-exam-preview-print-form {
                display: inline-flex;
                margin: 0;
            }
            .cbt-admin-exam-preview-meta {
                display: grid;
                grid-template-columns: 2fr repeat(3, minmax(160px, 1fr));
                gap: 10px;
                margin-bottom: 10px;
            }
            .cbt-admin-exam-preview-meta-item {
                border: 1px solid #d9e4f2;
                border-radius: 12px;
                background: #f8fbff;
                padding: 12px 14px;
            }
            .cbt-admin-exam-preview-meta-main {
                background: linear-gradient(120deg, #eaf4ff, #f4f9ff);
            }
            .cbt-admin-meta-kicker {
                font-size: 11px;
                line-height: 1.2;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #5f6b7a;
                font-weight: 700;
            }
            .cbt-admin-meta-title {
                margin-top: 6px;
                font-size: 19px;
                line-height: 1.35;
                color: #0f172a;
                font-weight: 700;
            }
            .cbt-admin-meta-subtitle {
                margin-top: 4px;
                font-size: 13px;
                color: #475569;
            }
            .cbt-admin-meta-value {
                margin-top: 6px;
                font-size: 24px;
                line-height: 1;
                font-weight: 800;
                color: #0f4c81;
            }
            .cbt-admin-meta-inline {
                margin-top: 6px;
                font-size: 13px;
                line-height: 1.45;
                color: #334155;
            }
            .cbt-admin-exam-preview-type-chips {
                margin-bottom: 12px;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .cbt-admin-type-chip {
                display: inline-flex;
                align-items: center;
                border: 1px solid #c9dbef;
                border-radius: 999px;
                background: #f4f9ff;
                color: #124e81;
                font-size: 12px;
                font-weight: 700;
                line-height: 1;
                padding: 6px 11px;
            }
            .cbt-admin-review-list {
                display: grid;
                gap: 12px;
            }
            .cbt-admin-review-item {
                border: 1px solid #d9e4f2;
                border-radius: 12px;
                background: #fff;
                padding: 14px;
                box-shadow: 0 8px 22px rgba(15, 76, 129, 0.08);
            }
            .cbt-admin-review-item-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 10px;
            }
            .cbt-admin-review-item-actions {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                flex-wrap: wrap;
                gap: 8px;
            }
            .cbt-admin-review-item-head h3 {
                margin: 0;
                font-size: 16px;
                line-height: 1.2;
                color: #0f172a;
            }
            .cbt-admin-review-type {
                margin: 5px 0 0;
                font-size: 12px;
                color: #607085;
                font-weight: 600;
            }
            .cbt-admin-review-source-note {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 7px;
            }
            .cbt-admin-review-source-chip {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 999px;
                background: #edf5ff;
                color: #1958b7;
                font-size: 11px;
                line-height: 1.2;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }
            .cbt-admin-review-source-text {
                color: #526172;
                font-size: 12px;
                line-height: 1.45;
            }
            .cbt-admin-review-points {
                display: inline-flex;
                align-items: center;
                border: 1px solid #bcd0e8;
                border-radius: 999px;
                background: #f7fbff;
                color: #145187;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                padding: 5px 10px;
                white-space: nowrap;
            }
            .cbt-admin-review-edit-btn {
                min-height: 30px;
                border-color: #bfd3e9 !important;
                color: #145187 !important;
                background: #f7fbff !important;
            }
            .cbt-admin-review-edit-btn:hover {
                border-color: #8fbdea !important;
                background: #edf6ff !important;
                color: #0f4c81 !important;
            }
            .cbt-admin-review-edit-hint {
                margin-bottom: 12px;
                padding: 10px 12px;
                border-radius: 10px;
                background: #f7fbff;
                border: 1px solid #d8e6fb;
                color: #4f6175;
                font-size: 12px;
                line-height: 1.55;
            }
            .cbt-admin-review-question {
                font-size: 15px;
                line-height: 1.65;
                color: #0f172a;
                font-weight: 600;
            }
            .cbt-admin-review-item :where(table) {
                margin: 0.45em 0;
                border-collapse: collapse;
                border-spacing: 0;
                background: #fff;
                border: 1px solid #d6deea;
            }
            .cbt-admin-review-item :where(th, td) {
                border: 1px solid #d6deea;
                padding: 8px 10px;
                vertical-align: top;
            }
            .cbt-admin-review-item :where(th) {
                background: #f8fbff;
                color: #0f172a;
                font-weight: 700;
            }
            .cbt-admin-review-options {
                margin-top: 12px;
                display: grid;
                gap: 8px;
            }
            .cbt-admin-review-option {
                border: 1px solid #d9e4f2;
                border-radius: 10px;
                background: #fff;
                padding: 10px 12px;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
            }
            .cbt-admin-review-option.is-correct {
                border-color: #8dd5bc;
                background: #ecfbf4;
            }
            .cbt-admin-review-option-main {
                display: flex;
                align-items: flex-start;
                gap: 9px;
                min-width: 0;
            }
            .cbt-admin-option-key {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 24px;
                height: 24px;
                border-radius: 8px;
                background: #e9f3ff;
                color: #145187;
                font-size: 12px;
                font-weight: 800;
                line-height: 1;
                padding: 0 6px;
            }
            .cbt-admin-option-label {
                line-height: 1.55;
                color: #1e293b;
            }
            .cbt-admin-review-badge {
                display: inline-flex;
                align-items: center;
                border: 1px solid #8dd5bc;
                border-radius: 999px;
                background: #eafbf4;
                color: #0f7a56;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: 0.02em;
                text-transform: uppercase;
                line-height: 1;
                padding: 5px 8px;
                white-space: nowrap;
            }
            .cbt-admin-review-short-answer,
            .cbt-admin-review-essay {
                margin-top: 12px;
                display: grid;
                gap: 7px;
            }
            .cbt-admin-review-short-answer strong,
            .cbt-admin-review-essay strong {
                font-size: 12px;
                color: #334155;
            }
            .cbt-admin-review-chip-list {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .cbt-admin-review-chip {
                display: inline-flex;
                align-items: center;
                border: 1px solid #d0def2;
                border-radius: 8px;
                background: #eef4ff;
                color: #1e3a6f;
                font-size: 12px;
                line-height: 1.2;
                padding: 4px 8px;
            }
            .cbt-admin-review-text {
                border: 1px solid #d6deea;
                border-radius: 10px;
                background: #f8fafc;
                padding: 10px 12px;
                font-size: 14px;
                line-height: 1.6;
                color: #1f2937;
            }
            .cbt-admin-review-empty {
                color: #64748b;
                font-size: 13px;
            }
            .cbt-admin-review-explanation {
                margin-top: 12px;
                border-left: 3px solid #bfd8f4;
                background: #f7fbff;
                border-radius: 8px;
                padding: 10px 12px;
                font-size: 13px;
                line-height: 1.55;
                color: #334155;
            }
            @media (max-width: 1024px) {
                .cbt-admin-exam-preview-meta {
                    grid-template-columns: repeat(2, minmax(160px, 1fr));
                }
                .cbt-admin-exam-preview-meta-main {
                    grid-column: 1 / -1;
                }
            }
            @media (max-width: 782px) {
                .cbt-admin-exam-preview-meta {
                    grid-template-columns: 1fr;
                }
            }
            <?php echo CBT_Admin_Questions_Helper::get_admin_student_preview_css(); ?>
        </style>
        <?php endif; ?>
