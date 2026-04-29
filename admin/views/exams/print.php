        <?php
        $document_title = isset($document_title) && is_string($document_title)
            ? $document_title
            : 'Print Soal Exam';
        $print_mode = isset($print_mode) && is_string($print_mode)
            ? $print_mode
            : CBT_Admin_Exams_Service::EXAM_QUESTION_PRINT_MODE_STUDENT;
        $show_answer_key = !empty($show_answer_key);
        $print_mode_label = isset($print_mode_label) && is_string($print_mode_label)
            ? $print_mode_label
            : ($show_answer_key ? 'Lembar Guru + Kunci' : 'Lembar Siswa');
        $print_back_url = isset($print_back_url) && is_string($print_back_url)
            ? $print_back_url
            : admin_url('admin.php?page=cbt-exams');
        $printed_at = isset($printed_at) && is_string($printed_at) ? $printed_at : current_time('d M Y H:i');
        $subject_label = trim((string) ($exam['subject_name'] ?? ''));
        $exam_title = trim((string) ($exam['title'] ?? 'Exam'));
        ?>
        <!doctype html>
        <html lang="id">
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title><?php echo esc_html($document_title); ?></title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 12mm;
                }
                * {
                    box-sizing: border-box;
                }
                body {
                    margin: 0;
                    font-family: Arial, Helvetica, sans-serif;
                    color: #111827;
                    background: #fff;
                    font-size: 12px;
                    line-height: 1.45;
                }
                .no-print {
                    padding: 12px 14px;
                    border-bottom: 1px solid #dbe5f2;
                    background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .no-print .button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 36px;
                    border: 1px solid #1d4ed8;
                    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                    color: #fff;
                    padding: 0 14px;
                    border-radius: 12px;
                    text-decoration: none;
                    cursor: pointer;
                    font-size: 12px;
                    font-weight: 700;
                    line-height: 1.15;
                    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.22);
                }
                .no-print .button-secondary {
                    border-color: #cbd5e1;
                    background: #fff;
                    color: #0f172a;
                    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
                }
                .questions-print-wrap {
                    padding: 16px 18px 14px;
                }
                .questions-print-header {
                    display: grid;
                    gap: 8px;
                    margin-bottom: 14px;
                    padding-bottom: 10px;
                    border-bottom: 3px solid #111827;
                }
                .questions-print-title {
                    margin: 0;
                    font-size: 22px;
                    line-height: 1.2;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                }
                .questions-print-subtitle {
                    color: #475569;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                }
                .questions-print-meta {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 5px 16px;
                    margin-top: 6px;
                    font-size: 12px;
                }
                .questions-print-meta-row {
                    display: grid;
                    grid-template-columns: 34mm 6px minmax(0, 1fr);
                    gap: 3px;
                    align-items: start;
                    min-width: 0;
                }
                .questions-print-meta-label {
                    font-weight: 700;
                }
                .questions-print-meta-separator {
                    text-align: center;
                    font-weight: 700;
                }
                .questions-print-meta-value {
                    word-break: break-word;
                }
                .questions-print-mode-note {
                    margin: 8px 0 0;
                    padding: 7px 9px;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    background: #f8fafc;
                    color: #334155;
                    font-size: 11px;
                    font-weight: 700;
                }
                .questions-print-list {
                    display: grid;
                    gap: 10px;
                }
                .cbt-admin-student-preview-card {
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    background: #fff;
                    overflow: hidden;
                }
                .cbt-admin-student-preview-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 10px;
                    padding: 9px 10px;
                    border-bottom: 1px solid #dbe5f2;
                    background: #f8fafc;
                }
                .cbt-admin-student-preview-head-main {
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 6px;
                    min-width: 0;
                }
                .cbt-admin-student-preview-kicker {
                    display: inline-flex;
                    width: fit-content;
                    border: 1px solid #bfdbfe;
                    border-radius: 999px;
                    padding: 4px 9px;
                    color: #1d4ed8;
                    background: #eff6ff;
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .cbt-admin-student-preview-chip-row,
                .cbt-admin-student-preview-meta {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 5px;
                }
                .cbt-admin-student-preview-chip {
                    display: inline-flex;
                    align-items: center;
                    min-height: 22px;
                    border: 1px solid #cbd5e1;
                    border-radius: 999px;
                    padding: 3px 8px;
                    color: #0f172a;
                    background: #fff;
                    font-size: 11px;
                    font-weight: 700;
                }
                .cbt-admin-student-preview-chip--type {
                    border-color: #bfdbfe;
                    color: #1e40af;
                    background: #eff6ff;
                }
                .cbt-admin-student-preview-chip--points {
                    border-color: #fed7aa;
                    color: #9a3412;
                    background: #fff7ed;
                }
                .cbt-admin-student-preview-chip--source {
                    border-color: #bae6fd;
                    color: #075985;
                    background: #f0f9ff;
                }
                .cbt-admin-student-preview-meta {
                    flex-basis: 100%;
                    color: #475569;
                    font-size: 11px;
                }
                .cbt-admin-student-preview-body {
                    display: grid;
                    gap: 9px;
                    padding: 10px;
                }
                .cbt-admin-student-preview-question {
                    font-size: 13px;
                    font-weight: 700;
                    color: #111827;
                }
                .cbt-admin-student-preview-richtext {
                    min-width: 0;
                    overflow-wrap: anywhere;
                }
                .cbt-admin-student-preview-richtext :where(p, ul, ol, table, figure, blockquote) {
                    margin-top: 0;
                    margin-bottom: 0.42em;
                }
                .cbt-admin-student-preview-richtext :where(p, ul, ol, table, figure, blockquote):last-child {
                    margin-bottom: 0;
                }
                .cbt-admin-student-preview-richtext table {
                    width: 100%;
                    border-collapse: collapse;
                    page-break-inside: auto;
                }
                .cbt-admin-student-preview-richtext th,
                .cbt-admin-student-preview-richtext td {
                    border: 1px solid #cbd5e1;
                    padding: 5px 6px;
                    vertical-align: top;
                }
                .cbt-admin-student-preview-richtext img {
                    max-width: 100%;
                    height: auto;
                }
                .cbt-admin-student-preview-section {
                    display: grid;
                    gap: 6px;
                }
                .cbt-admin-student-preview-section-title {
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                    color: #334155;
                }
                .cbt-admin-student-preview-options,
                .cbt-admin-student-preview-matrix,
                .cbt-admin-student-preview-chip-list {
                    display: grid;
                    gap: 6px;
                }
                .cbt-admin-student-preview-option {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 8px;
                    border: 1px solid #dbe5f2;
                    border-radius: 8px;
                    padding: 7px 8px;
                    background: #fff;
                }
                .cbt-admin-student-preview-option.is-correct {
                    border-color: #86efac;
                    background: #f0fdf4;
                }
                .cbt-admin-student-preview-option-main {
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                    min-width: 0;
                }
                .cbt-admin-student-preview-option-key {
                    width: 22px;
                    height: 22px;
                    min-width: 22px;
                    border: 1px solid #bfdbfe;
                    border-radius: 999px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    color: #1d4ed8;
                    background: #eff6ff;
                    font-size: 11px;
                    font-weight: 800;
                    line-height: 1;
                }
                .cbt-admin-student-preview-option-badges {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    flex: 0 0 auto;
                }
                .cbt-admin-student-preview-badge,
                .cbt-admin-student-preview-answer-chip,
                .cbt-admin-student-preview-matrix-answer {
                    display: inline-flex;
                    align-items: center;
                    width: fit-content;
                    border: 1px solid #86efac;
                    border-radius: 999px;
                    padding: 3px 7px;
                    color: #166534;
                    background: #f0fdf4;
                    font-size: 10px;
                    font-weight: 800;
                    white-space: nowrap;
                }
                .cbt-admin-student-preview-matrix-row {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) auto;
                    gap: 8px;
                    align-items: start;
                    border: 1px solid #dbe5f2;
                    border-radius: 8px;
                    padding: 7px 8px;
                }
                .cbt-admin-student-preview-section--explanation {
                    border-top: 1px dashed #cbd5e1;
                    padding-top: 8px;
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    .questions-print-wrap {
                        padding: 0;
                    }
                    .cbt-admin-student-preview-card {
                        break-inside: auto;
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button type="button" class="button" onclick="window.print()">Print / Save PDF</button>
                <a class="button button-secondary" href="<?php echo esc_url($print_back_url); ?>">Kembali ke Preview</a>
            </div>
            <main class="questions-print-wrap">
                <header class="questions-print-header">
                    <div>
                        <h1 class="questions-print-title"><?php echo esc_html($exam_title !== '' ? $exam_title : 'Exam'); ?></h1>
                        <div class="questions-print-subtitle"><?php echo esc_html($print_mode_label); ?></div>
                    </div>
                    <div class="questions-print-meta">
                        <div class="questions-print-meta-row">
                            <span class="questions-print-meta-label">Mata Pelajaran</span>
                            <span class="questions-print-meta-separator">:</span>
                            <span class="questions-print-meta-value"><?php echo esc_html($subject_label !== '' ? $subject_label : '-'); ?></span>
                        </div>
                        <div class="questions-print-meta-row">
                            <span class="questions-print-meta-label">Jumlah Soal</span>
                            <span class="questions-print-meta-separator">:</span>
                            <span class="questions-print-meta-value"><?php echo esc_html((string) $question_count); ?></span>
                        </div>
                        <div class="questions-print-meta-row">
                            <span class="questions-print-meta-label">Total Poin</span>
                            <span class="questions-print-meta-separator">:</span>
                            <span class="questions-print-meta-value"><?php echo esc_html(number_format((float) $total_points, 2)); ?></span>
                        </div>
                        <div class="questions-print-meta-row">
                            <span class="questions-print-meta-label">Jadwal</span>
                            <span class="questions-print-meta-separator">:</span>
                            <span class="questions-print-meta-value"><?php echo esc_html((string) $schedule_text); ?></span>
                        </div>
                        <div class="questions-print-meta-row">
                            <span class="questions-print-meta-label">Dicetak</span>
                            <span class="questions-print-meta-separator">:</span>
                            <span class="questions-print-meta-value"><?php echo esc_html($printed_at); ?></span>
                        </div>
                    </div>
                    <div class="questions-print-mode-note">
                        <?php echo esc_html($show_answer_key ? 'Mode guru: kunci jawaban, acuan jawaban, dan pembahasan ikut dicetak.' : 'Mode siswa: kunci jawaban dan pembahasan disembunyikan.'); ?>
                    </div>
                </header>

                <section class="questions-print-list">
                    <?php foreach ((array) $questions as $index => $question): ?>
                        <?php
                        $question_id = (int) ($question['id'] ?? 0);
                        $question_type = (string) ($question['question_type'] ?? '');
                        $type_label = (string) ($question_type_labels[$question_type] ?? ucwords(str_replace('_', ' ', $question_type)));
                        $options = (array) ($options_map[$question_id] ?? []);
                        $question_detail = CBT_Admin_Questions_Helper::get_question_type_detail($question_id, $question_type);
                        $source_question_id = (int) ($question['source_question_id'] ?? 0);
                        $source_exam_title = (string) ($question['source_exam_title'] ?? '');
                        $is_bank_backed = $source_question_id > 0 && stripos($source_exam_title, 'Bank Soal - ') === 0;
                        $print_meta_lines = [];
                        $print_extra_chips = [];

                        if ($show_answer_key && $source_exam_title !== '') {
                            $print_meta_lines[] = 'Sumber: ' . $source_exam_title;
                        }
                        if ($show_answer_key && $is_bank_backed) {
                            $print_extra_chips[] = [
                                'label' => 'Bank-backed',
                                'tone' => 'source',
                            ];
                        }
                        ?>
                        <?php echo CBT_Admin_Questions_Helper::render_admin_student_preview_card(
                            $question,
                            $options,
                            $question_detail,
                            [
                                'answer_mode' => $print_mode,
                                'eyebrow' => 'Soal ' . (string) ($index + 1),
                                'extra_chips' => $print_extra_chips,
                                'meta_lines' => $print_meta_lines,
                                'show_answer_key' => $show_answer_key,
                                'type_label' => $type_label,
                            ]
                        ); ?>
                    <?php endforeach; ?>
                </section>
            </main>
            <script>
                window.addEventListener('load', function () {
                    window.setTimeout(function () {
                        window.print();
                    }, 350);
                });
            </script>
        </body>
        </html>
        <?php
