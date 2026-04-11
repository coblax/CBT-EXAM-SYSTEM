
        <div class="wrap cbt-exams-page">
            <div class="cbt-exams-shell">
                <section class="cbt-exams-hero">
                    <div class="cbt-exams-hero-copy">
                        <span class="cbt-exams-kicker">Assessment</span>
                        <h1>CBT Exams</h1>
                        <p>Kelola builder exam, jadwal pelaksanaan, kelas peserta, dan pemilihan soal dari satu halaman kerja yang lebih rapi. Alur create dan update tetap sama, hanya tampilan admin-nya dibuat lebih modern dan mudah discan.</p>
                    </div>
                    <div class="cbt-exams-hero-stats">
                        <article class="cbt-exams-hero-stat">
                            <span>Total Exams</span>
                            <strong><?php echo esc_html((string) $total_exams); ?></strong>
                        </article>
                        <article class="cbt-exams-hero-stat">
                            <span>Published</span>
                            <strong><?php echo esc_html((string) $exam_status_totals['published']); ?></strong>
                        </article>
                        <article class="cbt-exams-hero-stat">
                            <span>Draft</span>
                            <strong><?php echo esc_html((string) $exam_status_totals['draft']); ?></strong>
                        </article>
                        <article class="cbt-exams-hero-stat">
                            <span>Soal Dipilih</span>
                            <strong><?php echo esc_html((string) $selected_question_total); ?></strong>
                        </article>
                    </div>
                </section>
                <?php if (!empty($exam_operational_stats['cards']) && is_array($exam_operational_stats['cards'])): ?>
                    <section class="cbt-exams-operational-strip" aria-label="Operational overview">
                        <div class="cbt-exams-operational-head">
                            <div>
                                <span class="cbt-exams-operational-kicker">Operational</span>
                                <p>Diperbarui sekitar tiap <?php echo esc_html((string) max(1, (int) ($exam_operational_stats['refreshed_every_seconds'] ?? 20))); ?> detik untuk memberi ringkasan kondisi Redis dan runtime CBT saat ini.</p>
                            </div>
                        </div>
                        <div class="cbt-exams-operational-grid">
                            <?php foreach ((array) $exam_operational_stats['cards'] as $operational_card): ?>
                                <?php
                                if (!is_array($operational_card)) {
                                    continue;
                                }
                                $operational_tone = sanitize_html_class((string) ($operational_card['tone'] ?? 'neutral'), 'neutral');
                                $operational_meta = trim((string) ($operational_card['meta'] ?? ''));
                                $operational_hint = trim((string) ($operational_card['hint'] ?? ''));
                                ?>
                                <article class="cbt-exams-operational-card is-<?php echo esc_attr($operational_tone); ?>">
                                    <span class="cbt-exams-operational-card-label"><?php echo esc_html((string) ($operational_card['label'] ?? '-')); ?></span>
                                    <strong><?php echo esc_html((string) ($operational_card['value'] ?? '-')); ?></strong>
                                    <?php if ($operational_meta !== ''): ?>
                                        <small><?php echo esc_html($operational_meta); ?></small>
                                    <?php endif; ?>
                                    <?php if ($operational_hint !== ''): ?>
                                        <span class="cbt-exams-operational-card-hint"><?php echo esc_html($operational_hint); ?></span>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($blocked_bank_exam_context['is_blocked'])): ?>
                <section class="cbt-exam-bank-guard-card">
                    <div class="cbt-exam-bank-guard-copy">
                        <span class="cbt-exam-bank-guard-kicker">Bank Guard</span>
                        <h2>Bank Soal tidak diedit dari menu ini</h2>
                        <p><?php echo esc_html((string) ($blocked_bank_exam_context['title'] ?? 'Bank Soal')); ?> berfungsi sebagai container source soal. Kelola root question-nya dari `CBT Questions`, lalu kembali ke daftar exam untuk mengelola exam siswa.</p>
                    </div>
                    <div class="cbt-exam-bank-guard-actions">
                        <a class="button button-primary" href="<?php echo esc_url((string) ($blocked_bank_exam_context['questions_url'] ?? admin_url('admin.php?page=cbt-question-bank'))); ?>">Buka CBT Questions</a>
                        <a class="button" href="<?php echo esc_url((string) ($blocked_bank_exam_context['list_url'] ?? admin_url('admin.php?page=cbt-exams&cbt_exam_panel=list'))); ?>">Kembali ke Daftar Exam</a>
                    </div>
                </section>
            <?php endif; ?>

            <div class="cbt-tab-buttons cbt-exams-page-tabs" id="cbt-exam-page-tabs" role="tablist" aria-label="Navigasi halaman exam">
                <button type="button" class="button cbt-exams-page-tab<?php echo $active_exam_page_panel === 'cbt-exam-builder-panel' ? ' cbt-active' : ''; ?>" data-target="cbt-exam-builder-panel" role="tab" aria-selected="<?php echo $active_exam_page_panel === 'cbt-exam-builder-panel' ? 'true' : 'false'; ?>">
                    <span class="cbt-exams-page-tab-label">Form Exam</span>
                    <small><?php echo esc_html($editing_exam ? 'Edit exam aktif' : 'Buat exam baru atau revisi draft'); ?></small>
                </button>
                <button type="button" class="button cbt-exams-page-tab<?php echo $active_exam_page_panel === 'cbt-exam-list-panel' ? ' cbt-active' : ''; ?>" data-target="cbt-exam-list-panel" role="tab" aria-selected="<?php echo $active_exam_page_panel === 'cbt-exam-list-panel' ? 'true' : 'false'; ?>">
                    <span class="cbt-exams-page-tab-label">Daftar Exam</span>
                    <small>Monitoring, hasil, dan aksi cepat</small>
                </button>
                <?php if (!empty($can_manage_exam_snapshots)): ?>
                    <button type="button" class="button cbt-exams-page-tab<?php echo $active_exam_page_panel === 'cbt-exam-snapshot-panel' ? ' cbt-active' : ''; ?>" data-target="cbt-exam-snapshot-panel" role="tab" aria-selected="<?php echo $active_exam_page_panel === 'cbt-exam-snapshot-panel' ? 'true' : 'false'; ?>">
                        <span class="cbt-exams-page-tab-label">Snapshot</span>
                        <small>Monitoring snapshot Redis operasional</small>
                    </button>
                <?php endif; ?>
            </div>

            <div id="cbt-exam-builder-panel" class="cbt-exam-page-panel<?php echo $active_exam_page_panel === 'cbt-exam-builder-panel' ? ' cbt-active' : ''; ?>" role="tabpanel">
                <?php if (empty($subjects)): ?>
                    <div class="notice notice-warning"><p>Belum ada mapel. Buat mapel terlebih dahulu di menu CBT Subjects.</p></div>
                <?php else: ?>
                    <h2><?php echo $editing_exam ? 'Edit Exam' : 'Buat Exam Baru'; ?></h2>
                    <p class="description">Flow: pilih mapel, atur jadwal, tentukan kelas peserta, lalu pilih soal yang dipakai untuk exam.</p>
                    <div class="cbt-exam-builder-meta">
                        <span class="cbt-exam-builder-meta-pill">Mapel aktif: <?php echo esc_html($selected_subject_label); ?></span>
                        <span class="cbt-exam-builder-meta-pill">Soal draft: <?php echo esc_html((string) $selected_question_total); ?></span>
                        <span class="cbt-exam-builder-meta-pill"><?php echo esc_html($editing_exam ? 'Edit mode' : 'Create mode'); ?></span>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-exam-builder-form">
                        <?php wp_nonce_field('cbt_save_exam'); ?>
                        <input type="hidden" name="action" value="cbt_save_exam" />
                        <input type="hidden" name="id" value="<?php echo (int) ($editing_exam['id'] ?? 0); ?>" />
                        <input type="hidden" name="cbt_exam_per_page" value="<?php echo (int) $exam_per_page; ?>" />
                        <input type="hidden" name="cbt_exam_paged" value="<?php echo (int) $exam_current_page; ?>" />
                        <input type="hidden" name="cbt_exam_search" value="<?php echo esc_attr($exam_list_state['search']); ?>" />
                        <input type="hidden" name="cbt_exam_status" value="<?php echo esc_attr($exam_list_state['status']); ?>" />
                        <input type="hidden" name="cbt_exam_subject" value="<?php echo (int) $exam_list_state['subject_id']; ?>" />
                        <input type="hidden" name="cbt_exam_kelas" value="<?php echo esc_attr($exam_list_state['kelas']); ?>" />
                        <input type="hidden" id="cbt-exam-builder-state-key" value="<?php echo esc_attr($builder_state_key); ?>" />
                        <input type="hidden" id="cbt-exam-builder-reset-keys" value="<?php echo esc_attr(wp_json_encode(array_values(array_unique($builder_state_reset_keys)))); ?>" />
                        <input type="hidden" id="cbt-exam-selected-defaults" value="<?php echo esc_attr(wp_json_encode(array_values(array_unique($selected_question_ids)))); ?>" />
                        <input type="hidden" id="cbt-exam-selected-initials" value="<?php echo esc_attr(wp_json_encode(array_values(array_unique($initial_selected_question_ids)))); ?>" />
                        <input type="hidden" id="cbt-exam-selected-details" value="<?php echo esc_attr(wp_json_encode($selected_sidebar_question_map)); ?>" />
                        <script type="application/json" id="cbt-exam-selected-previews"><?php echo wp_json_encode($selected_sidebar_preview_map); ?></script>
                        <input type="hidden" id="cbt-exam-builder-ajax-nonce" value="<?php echo esc_attr(wp_create_nonce('cbt_exam_builder_state')); ?>" />
                        <input type="hidden" id="cbt-exam-builder-question-mode" value="<?php echo esc_attr($builder_question_mode); ?>" />
                        <input type="hidden" id="cbt-exam-builder-context-fingerprint" value="<?php echo esc_attr(wp_json_encode([
                            'id' => (int) ($editing_exam['id'] ?? 0),
                            'title' => (string) ($editing_exam['title'] ?? ''),
                            'subject_id' => (int) ($editing_exam['subject_id'] ?? $selected_subject_id),
                            'status' => (string) ($editing_exam['status'] ?? 'draft'),
                            'duration_minutes' => (int) ($editing_exam['duration_minutes'] ?? 60),
                            'kkm_percentage' => (float) ($editing_exam['kkm_percentage'] ?? 75),
                            'show_student_result' => (int) ($editing_exam['show_student_result'] ?? 1),
                            'enable_calculator' => (int) ($editing_exam['enable_calculator'] ?? 1),
                            'starts_at' => (string) ($editing_exam['starts_at'] ?? ''),
                            'ends_at' => (string) ($editing_exam['ends_at'] ?? ''),
                            'target_kelas' => (string) ($editing_exam['target_kelas'] ?? ''),
                            'updated_at' => (string) ($editing_exam['updated_at'] ?? ''),
                            'mode' => $editing_exam ? 'edit' : 'new',
                        ])); ?>" />

                        <div id="cbt-exam-save-progress-overlay" class="cbt-exam-save-progress-overlay" hidden aria-hidden="true" style="display:none;">
                            <div class="cbt-exam-save-progress-card">
                                <h3 id="cbt-exam-save-progress-title">Menyimpan Exam</h3>
                                <p id="cbt-exam-save-progress-message">Menyiapkan proses sinkronisasi exam.</p>
                                <div class="cbt-exam-save-progress-meta">
                                    <span id="cbt-exam-save-progress-phase">Menyiapkan proses</span>
                                    <span id="cbt-exam-save-progress-stats"></span>
                                </div>
                                <div class="cbt-exam-save-progress-bar" aria-hidden="true">
                                    <div id="cbt-exam-save-progress-fill" class="cbt-exam-save-progress-fill"></div>
                                </div>
                                <div id="cbt-exam-save-progress-percent" class="cbt-exam-save-progress-percent">0%</div>
                                <p class="description cbt-exam-save-progress-help">Jangan tutup halaman ini selama progress berjalan.</p>
                            </div>
                        </div>

                        <div class="cbt-exam-flow-bar">
                            <div class="cbt-tab-buttons cbt-exam-flow-tabs" id="cbt-exam-builder-tabs" role="tablist" aria-label="Navigasi builder exam">
                                <button type="button" class="button<?php echo $active_exam_builder_panel === 'cbt-exam-details-panel' ? ' cbt-active' : ''; ?>" data-target="cbt-exam-details-panel" role="tab" aria-selected="<?php echo $active_exam_builder_panel === 'cbt-exam-details-panel' ? 'true' : 'false'; ?>">
                                    Detail Exam
                                </button>
                                <span class="cbt-exam-flow-arrow" aria-hidden="true">&rarr;</span>
                                <button type="button" id="cbt-exam-builder-tab-questions" class="button<?php echo $active_exam_builder_panel === 'cbt-exam-questions-panel' ? ' cbt-active' : ''; ?>" data-target="cbt-exam-questions-panel" role="tab" aria-selected="<?php echo $active_exam_builder_panel === 'cbt-exam-questions-panel' ? 'true' : 'false'; ?>">
                                    Pilih Soal
                                </button>
                            </div>

                            <div class="cbt-exam-builder-actions cbt-exam-flow-actions">
                                <span class="cbt-exam-flow-arrow" aria-hidden="true">&rarr;</span>
                                <button type="submit" class="button button-primary cbt-exam-submit-btn"><?php echo esc_html($editing_exam ? 'Update Exam' : 'Create Exam'); ?></button>
                                <?php if ($editing_exam): ?>
                                    <a class="button" id="cbt-exam-cancel-edit" href="<?php echo esc_url(admin_url('admin.php?page=cbt-exams')); ?>">Batal Edit</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="description cbt-exam-flow-help">Alur: isi detail exam, lanjut pilih soal, lalu simpan exam.</p>

                        <div id="cbt-exam-details-panel" class="cbt-exam-builder-panel<?php echo $active_exam_builder_panel === 'cbt-exam-details-panel' ? ' cbt-active' : ''; ?>" role="tabpanel">
                            <table class="form-table" role="presentation">
                                <tr>
                                    <th><label for="cbt-exam-subject-id">Mapel</label></th>
                                    <td>
                                        <select required id="cbt-exam-subject-id" name="subject_id">
                                            <option value="">Select subject</option>
                                            <?php foreach ($subjects as $subject): ?>
                                                <option value="<?php echo (int) $subject['id']; ?>" <?php selected($selected_subject_id, (int) $subject['id']); ?>>
                                                    <?php echo esc_html((string) $subject['name'] . (!empty($subject['code']) ? ' (' . $subject['code'] . ')' : '')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-exam-title">Judul Exam</label></th>
                                    <td>
                                        <input required type="text" id="cbt-exam-title" name="title" class="regular-text" value="<?php echo esc_attr((string) ($editing_exam['title'] ?? '')); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-exam-duration">Durasi (menit)</label></th>
                                    <td>
                                        <input required type="number" id="cbt-exam-duration" name="duration_minutes" min="1" value="<?php echo esc_attr((string) ((int) ($editing_exam['duration_minutes'] ?? 60))); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-exam-kkm">KKM (%)</label></th>
                                    <td>
                                        <input required type="number" id="cbt-exam-kkm" name="kkm_percentage" min="0" max="100" step="0.01" value="<?php echo esc_attr((string) ((float) ($editing_exam['kkm_percentage'] ?? 75))); ?>" />
                                        <p class="description">Kriteria Ketuntasan Minimal. Isi persentase benar minimum untuk lulus, misalnya <code>75</code>, <code>80</code>, atau <code>61.5</code>.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-exam-status">Status</label></th>
                                    <td>
                                        <?php $editing_status = (string) ($editing_exam['status'] ?? 'draft'); ?>
                                        <select required id="cbt-exam-status" name="status">
                                            <option value="draft" <?php selected($editing_status, 'draft'); ?>>Draft</option>
                                            <option value="published" <?php selected($editing_status, 'published'); ?>>Published</option>
                                            <option value="closed" <?php selected($editing_status, 'closed'); ?>>Closed</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-exam-starts-at">Mulai</label></th>
                                    <td>
                                        <input required type="datetime-local" id="cbt-exam-starts-at" name="starts_at" value="<?php echo esc_attr(CBT_Admin_Exams_Service::to_datetime_local((string) ($editing_exam['starts_at'] ?? ''))); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-exam-ends-at">Selesai</label></th>
                                    <td>
                                        <input required type="datetime-local" id="cbt-exam-ends-at" name="ends_at" value="<?php echo esc_attr(CBT_Admin_Exams_Service::to_datetime_local((string) ($editing_exam['ends_at'] ?? ''))); ?>" />
                                    </td>
                                </tr>
                                <tr class="cbt-exam-detail-row cbt-exam-detail-row--stacked">
                                    <th><label for="cbt-exam-target-kelas">Kelas Peserta</label></th>
                                    <td>
                                        <div style="margin-bottom:8px;">
                                            <button type="button" class="button button-secondary button-small" id="cbt-kelas-check-all">Pilih Semua</button>
                                            <button type="button" class="button button-secondary button-small" id="cbt-kelas-uncheck-all">Kosongkan</button>
                                        </div>
                                        <div id="cbt-kelas-checklist" data-field-label="Kelas Peserta" tabindex="-1" style="min-width:360px; max-height:220px; overflow:auto; border:1px solid #ccd0d4; padding:8px; background:#fff;">
                                            <?php if (empty($kelas_options)): ?>
                                                <em>Belum ada data kelas dari CBT Users.</em>
                                            <?php else: ?>
                                                <?php foreach ($kelas_options as $kelas_option): ?>
                                                    <label style="display:block; margin: 0 0 6px;">
                                                        <input
                                                            type="checkbox"
                                                            class="cbt-kelas-checkbox"
                                                            name="target_kelas[]"
                                                            value="<?php echo esc_attr($kelas_option); ?>"
                                                            <?php checked(in_array($kelas_option, $editing_target_kelas_values, true)); ?>
                                                        />
                                                        <?php echo esc_html($kelas_option); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                        <p class="description">Pilih minimal satu kelas peserta sebelum lanjut ke pilih soal.</p>
                                    </td>
                                </tr>
                                <tr class="cbt-exam-detail-row cbt-exam-detail-row--toggle">
                                    <th><label for="cbt-exam-randomize">Acak Soal</label></th>
                                    <td>
                                        <label class="cbt-exam-inline-toggle">
                                            <input type="checkbox" id="cbt-exam-randomize" name="randomize_questions" value="1" <?php checked((int) ($editing_exam['randomize_questions'] ?? 0), 1); ?> />
                                            Acak urutan soal untuk siswa
                                        </label>
                                    </td>
                                </tr>
                                <tr class="cbt-exam-detail-row cbt-exam-detail-row--toggle">
                                    <th><label for="cbt-exam-randomize-options">Acak Opsi Jawaban</label></th>
                                    <td>
                                        <label class="cbt-exam-inline-toggle">
                                            <input type="checkbox" id="cbt-exam-randomize-options" name="randomize_options" value="1" <?php checked((int) ($editing_exam['randomize_options'] ?? 0), 1); ?> />
                                            Acak urutan opsi untuk Multiple Choice, Multiple Answer, dan TF Matrix
                                        </label>
                                    </td>
                                </tr>
                                <tr class="cbt-exam-detail-row cbt-exam-detail-row--toggle">
                                    <th><label for="cbt-exam-show-student-result">Lihat Nilai & Review</label></th>
                                    <td>
                                        <label class="cbt-exam-inline-toggle">
                                            <input type="checkbox" id="cbt-exam-show-student-result" name="show_student_result" value="1" <?php checked((int) ($editing_exam['show_student_result'] ?? 1), 1); ?> />
                                            Tampilkan nilai dan review jawaban ke siswa setelah ujian selesai
                                        </label>
                                    </td>
                                </tr>
                                <tr class="cbt-exam-detail-row cbt-exam-detail-row--toggle">
                                    <th><label for="cbt-exam-enable-calculator">Aktifkan Kalkulator</label></th>
                                    <td>
                                        <label class="cbt-exam-inline-toggle">
                                            <input type="checkbox" id="cbt-exam-enable-calculator" name="enable_calculator" value="1" <?php checked((int) ($editing_exam['enable_calculator'] ?? 1), 1); ?> />
                                            Izinkan siswa membuka kalkulator saat mengerjakan exam
                                        </label>
                                    </td>
                                </tr>
                                <tr class="cbt-exam-detail-row cbt-exam-detail-row--textarea">
                                    <th><label for="cbt-exam-description">Deskripsi</label></th>
                                    <td>
                                        <textarea id="cbt-exam-description" name="description" rows="4" class="large-text"><?php echo esc_textarea((string) ($editing_exam['description'] ?? '')); ?></textarea>
                                    </td>
                                </tr>
                            </table>
                            <div id="cbt-exam-detail-validation-notice" class="notice notice-warning inline cbt-exam-detail-validation-notice" hidden>
                                <p>Lengkapi field wajib di Detail Exam sebelum lanjut ke Pilih Soal.</p>
                            </div>
                            <div class="cbt-exam-panel-nav" aria-label="Navigasi detail exam">
                                <p class="cbt-exam-panel-nav-note">Detail exam sudah diisi? Lanjut ke pemilihan soal.</p>
                                <div class="cbt-exam-panel-nav-actions">
                                    <button type="button" id="cbt-exam-builder-next-btn" class="button button-primary cbt-exam-builder-nav-btn" data-target-panel="cbt-exam-questions-panel">Next: Pilih Soal</button>
                                </div>
                            </div>
                        </div>

                        <div id="cbt-exam-questions-panel" class="cbt-exam-builder-panel<?php echo $active_exam_builder_panel === 'cbt-exam-questions-panel' ? ' cbt-active' : ''; ?>" role="tabpanel">
                            <h3>Pilih Soal Exam</h3>
                            <?php
                            $pending_added_ids = array_values(array_diff($selected_question_ids, $initial_selected_question_ids));
                            $pending_removed_ids = array_values(array_diff($initial_selected_question_ids, $selected_question_ids));
                            $selected_sidebar_status_text = (!empty($pending_added_ids) || !empty($pending_removed_ids))
                                ? 'Belum disimpan'
                                : 'Sinkron dengan exam';
                            ?>
                            <div class="cbt-exam-question-shell">
                                <div class="cbt-exam-question-workspace">
                                    <div class="cbt-exam-question-catalog" data-cbt-exam-question-catalog="1" data-cbt-question-catalog-loaded="<?php echo $should_load_question_catalog ? '1' : '0'; ?>">
                                        <div class="cbt-exam-question-header">
                                            <p class="description cbt-exam-question-description">
                                                Tambahkan soal dari katalog di kiri, review hasil draft di sidebar kanan, lalu commit final saat klik <strong><?php echo esc_html($editing_exam ? 'Update Exam' : 'Create Exam'); ?></strong>.
                                            </p>
                                        </div>

                                        <?php if ($should_load_question_catalog): ?>
                                            <div class="cbt-exam-question-filter-panel">
                                                <div class="cbt-exam-question-lineage-bar">
                                                    <span class="cbt-exam-question-lineage-pill cbt-exam-question-lineage-pill--<?php echo esc_attr(sanitize_html_class($source_catalog_scope)); ?>">
                                                        <?php echo esc_html($source_catalog_scope === 'bank' ? 'Katalog: Bank Soal' : 'Fallback: Exam Sumber'); ?>
                                                    </span>
                                                    <?php if ($editing_exam): ?>
                                                        <span class="cbt-exam-question-lineage-pill cbt-exam-question-lineage-pill--<?php echo esc_attr(sanitize_html_class($selected_lineage_topology_class)); ?>">
                                                            <?php echo esc_html('Draft Exam: ' . $selected_lineage_topology_label); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="cbt-exam-question-filter-grid">
                                                    <div class="cbt-exam-question-field cbt-exam-question-field-search">
                                                        <label for="cbt-exam-question-search">Cari Soal</label>
                                                        <input type="text" id="cbt-exam-question-search" class="regular-text" placeholder="Ketik kata kunci soal..." value="<?php echo esc_attr($builder_question_search); ?>" />
                                                    </div>
                                                    <div class="cbt-exam-question-field">
                                                        <label for="cbt-exam-question-type-filter">Tipe</label>
                                                        <select id="cbt-exam-question-type-filter">
                                                            <option value="">Semua tipe</option>
                                                            <?php foreach ($question_type_labels as $question_type => $question_type_label): ?>
                                                                <option value="<?php echo esc_attr($question_type); ?>" <?php selected($builder_question_type, $question_type); ?>><?php echo esc_html($question_type_label); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="cbt-exam-question-field">
                                                        <label for="cbt-exam-question-source-filter"><?php echo esc_html($source_filter_label); ?></label>
                                                        <select id="cbt-exam-question-source-filter">
                                                            <option value=""><?php echo esc_html($source_filter_all_label); ?></option>
                                                            <?php foreach ($source_exam_options as $source_exam_id => $source_exam_title): ?>
                                                                <option value="<?php echo (int) $source_exam_id; ?>" <?php selected($builder_question_source, $source_exam_id); ?>><?php echo esc_html($source_exam_title); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php if ($source_filter_help_text !== ''): ?>
                                                            <p class="description"><?php echo esc_html($source_filter_help_text); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="cbt-exam-question-field">
                                                        <label for="cbt-exam-question-per-page">Per halaman</label>
                                                        <select id="cbt-exam-question-per-page">
                                                            <?php foreach ([50, 100, 150, 300] as $builder_question_per_page_option): ?>
                                                                <option value="<?php echo (int) $builder_question_per_page_option; ?>" <?php selected($builder_question_per_page, $builder_question_per_page_option); ?>>
                                                                    <?php echo esc_html((string) $builder_question_per_page_option); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="cbt-exam-question-filter-actions">
                                                    <a href="<?php echo esc_url($reset_question_catalog_url); ?>" class="button cbt-exam-question-filter-reset" id="cbt-exam-reset-filters">Reset</a>
                                                </div>
                                            </div>

                                            <div class="cbt-exam-question-status-bar">
                                                <div class="cbt-exam-question-bulk-actions">
                                                    <button type="button" class="button button-secondary" id="cbt-exam-select-visible">Tambah Semua yang Terlihat</button>
                                                    <span class="cbt-exam-question-bulk-help">Hanya menambah soal yang tampil di katalog saat ini.</span>
                                                </div>
                                                <span id="cbt-exam-selected-count"><?php echo esc_html(sprintf('%d soal di draft | %d terlihat di katalog', count($selected_question_ids), count($source_questions))); ?></span>
                                            </div>

                                            <div class="tablenav top cbt-admin-pagination-wrap cbt-exam-question-pagination-wrap">
                                                <div class="tablenav-pages cbt-admin-pagination" style="float:none; margin:0;">
                                                    <span class="displaying-num cbt-admin-total">
                                                        <?php echo esc_html(sprintf('Total soal: %d | Halaman %d/%d', $source_total_questions, $builder_question_current_page, $source_question_total_pages)); ?>
                                                    </span>
                                                    <?php if (!empty($source_question_pagination_links)): ?>
                                                        <span class="pagination-links cbt-admin-pagination-links">
                                                            <?php foreach ($source_question_pagination_links as $source_question_pagination_link): ?>
                                                                <?php echo wp_kses_post($source_question_pagination_link); ?>
                                                            <?php endforeach; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="cbt-exam-question-table-wrap">
                                                <table class="widefat striped cbt-exam-question-table" style="margin:0;">
                                                    <thead>
                                                    <tr>
                                                        <th style="width:118px;">Draft / Identitas</th>
                                                        <th style="width:124px;">Sumber / Tipe</th>
                                                        <th>Soal</th>
                                                        <th style="width:58px;">Poin</th>
                                                        <th style="width:78px;">Aksi</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php if (empty($source_questions)): ?>
                                                        <tr>
                                                            <td colspan="5">Belum ada soal tersedia. Isi dulu di menu CBT Questions atau ubah filter katalog.</td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($source_questions as $source_question): ?>
                                                            <?php
                                                            $source_question_id = (int) ($source_question['id'] ?? 0);
                                                            $source_subject_id = (int) ($source_question['subject_id'] ?? 0);
                                                            $source_exam_id = (int) ($source_question['exam_id'] ?? 0);
                                                            $source_type = (string) ($source_question['question_type'] ?? '');
                                                            $source_type_label = (string) ($question_type_labels[$source_type] ?? $source_type);
                                                            $question_plain = strtolower(trim(wp_strip_all_tags((string) ($source_question['question_text'] ?? ''))));
                                                            $question_preview = wp_trim_words((string) wp_strip_all_tags((string) ($source_question['question_text'] ?? '')), 16);
                                                            $is_checked = in_array($source_question_id, $selected_question_ids, true);
                                                            $source_question_edit_url = add_query_arg(
                                                                [
                                                                    'page' => 'cbt-question-bank',
                                                                    'edit' => $source_question_id,
                                                                ],
                                                                admin_url('admin.php')
                                                            );
                                                            $source_options = $source_options_map[$source_question_id] ?? [];
                                                            $source_question_detail = CBT_Admin_Questions_Helper::get_question_type_detail($source_question_id, $source_type);
                                                            $source_preview_meta_lines = [];
                                                            $source_subject_name = trim((string) ($source_question['subject_name'] ?? ''));
                                                            if ($source_subject_name !== '') {
                                                                $source_preview_meta_lines[] = 'Mapel: ' . $source_subject_name;
                                                            }
                                                            $source_context_display = trim((string) ($source_question['source_context_display'] ?? ($source_question['exam_title'] ?? '')));
                                                            $source_context_label = trim((string) ($source_question['source_context_label'] ?? 'Sumber'));
                                                            if ($source_context_display !== '') {
                                                                $source_preview_meta_lines[] = ($source_context_label !== '' ? $source_context_label : 'Sumber') . ': ' . $source_context_display;
                                                            }
                                                            $source_preview_extra_chips = [];
                                                            $source_lineage_label = trim((string) ($source_question['lineage_label'] ?? ''));
                                                            if ($source_lineage_label !== '') {
                                                                $source_preview_extra_chips[] = [
                                                                    'label' => $source_lineage_label,
                                                                    'tone' => 'source',
                                                                ];
                                                            }
                                                            $source_preview_actions = sprintf(
                                                                '<a class="button button-secondary" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                                                                esc_url($source_question_edit_url),
                                                                esc_html__('Edit Soal', 'cbt-exam-system')
                                                            );
                                                            ?>
                                                            <tr class="cbt-exam-question-row<?php echo $is_checked ? ' is-selected' : ''; ?>"
                                                                data-question-id="<?php echo (int) $source_question_id; ?>"
                                                                data-subject-id="<?php echo (int) $source_subject_id; ?>"
                                                                data-subject-name="<?php echo esc_attr((string) ($source_question['subject_name'] ?? '-')); ?>"
                                                                data-source-id="<?php echo (int) $source_exam_id; ?>"
                                                                data-type="<?php echo esc_attr($source_type); ?>"
                                                                data-type-label="<?php echo esc_attr($source_type_label); ?>"
                                                                data-points="<?php echo esc_attr((string) ($source_question['points'] ?? '1')); ?>"
                                                                data-preview="<?php echo esc_attr($question_preview); ?>"
                                                                data-lineage-label="<?php echo esc_attr((string) ($source_question['lineage_label'] ?? 'Source')); ?>"
                                                                data-lineage-class="<?php echo esc_attr((string) ($source_question['lineage_class'] ?? 'default')); ?>"
                                                                data-lineage-hint="<?php echo esc_attr((string) ($source_question['lineage_hint'] ?? '')); ?>"
                                                                data-source-context-label="<?php echo esc_attr((string) ($source_question['source_context_label'] ?? '')); ?>"
                                                                data-source-context-display="<?php echo esc_attr((string) ($source_question['source_context_display'] ?? ($source_question['exam_title'] ?? '-'))); ?>"
                                                                data-exam-title="<?php echo esc_attr((string) ($source_question['exam_title'] ?? '-')); ?>"
                                                                data-edit-url="<?php echo esc_attr($source_question_edit_url); ?>"
                                                                data-search="<?php echo esc_attr($question_plain); ?>">
                                                                <td>
                                                                    <div class="cbt-exam-question-identity-cell">
                                                                        <input type="checkbox" class="cbt-exam-question-checkbox" name="source_question_ids[]" value="<?php echo (int) $source_question_id; ?>" <?php checked($is_checked); ?> />
                                                                        <div class="cbt-exam-question-identity-copy">
                                                                            <span class="cbt-exam-question-identity-id">#<?php echo (int) $source_question_id; ?></span>
                                                                            <strong><?php echo esc_html((string) ($source_question['subject_name'] ?? '-')); ?></strong>
                                                                        </div>
                                                                        <button type="button" class="button button-secondary cbt-exam-question-pick-btn" data-question-id="<?php echo (int) $source_question_id; ?>">
                                                                            <?php echo esc_html($is_checked ? 'Sudah Dipilih' : 'Tambah'); ?>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="cbt-exam-question-source-cell cbt-exam-question-source-cell--stacked">
                                                                        <span class="cbt-exam-question-source-name"><?php echo esc_html((string) ($source_question['exam_title'] ?? '-')); ?></span>
                                                                        <span class="cbt-exam-question-source-type"><?php echo esc_html($source_type_label); ?></span>
                                                                        <div class="cbt-exam-question-source-meta">
                                                                            <span class="cbt-exam-question-lineage-badge cbt-exam-question-lineage-badge--<?php echo esc_attr(sanitize_html_class((string) ($source_question['lineage_class'] ?? 'default'))); ?>">
                                                                                <?php echo esc_html((string) ($source_question['lineage_label'] ?? 'Source')); ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td><?php echo esc_html($question_preview); ?></td>
                                                                <td><?php echo esc_html((string) ($source_question['points'] ?? '1')); ?></td>
                                                                <td>
                                                                    <div class="cbt-exam-question-actions">
                                                                        <button type="button" class="cbt-exam-question-action cbt-exam-question-action--preview cbt-quick-view-btn" data-qid="<?php echo (int) $source_question_id; ?>">
                                                                            <span class="cbt-exam-question-action__label">Lihat</span>
                                                                        </button>
                                                                    </div>
                                                                    <div id="cbt-quick-view-content-<?php echo (int) $source_question_id; ?>" style="display:none;">
                                                                        <?php echo CBT_Admin_Questions_Helper::render_admin_student_preview_card(
                                                                            $source_question,
                                                                            $source_options,
                                                                            $source_question_detail,
                                                                            [
                                                                                'eyebrow' => 'Soal #' . (int) $source_question_id,
                                                                                'type_label' => $source_type_label,
                                                                                'meta_lines' => $source_preview_meta_lines,
                                                                                'extra_chips' => $source_preview_extra_chips,
                                                                                'note_text' => (string) ($source_question['lineage_hint'] ?? ''),
                                                                                'actions_html' => $source_preview_actions,
                                                                            ]
                                                                        ); ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="tablenav bottom cbt-admin-pagination-wrap cbt-exam-question-pagination-wrap" style="margin-top:10px;">
                                                <div class="tablenav-pages cbt-admin-pagination" style="float:none; margin:0;">
                                                    <span class="displaying-num cbt-admin-total">
                                                        <?php echo esc_html(sprintf('Total soal: %d | Halaman %d/%d', $source_total_questions, $builder_question_current_page, $source_question_total_pages)); ?>
                                                    </span>
                                                    <?php if (!empty($source_question_pagination_links)): ?>
                                                        <span class="pagination-links cbt-admin-pagination-links">
                                                            <?php foreach ($source_question_pagination_links as $source_question_pagination_link): ?>
                                                                <?php echo wp_kses_post($source_question_pagination_link); ?>
                                                            <?php endforeach; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="cbt-exam-question-lazy-placeholder">
                                                <p><strong>Daftar bank soal belum dimuat.</strong> Halaman edit exam dibuat lebih ringan dulu, lalu katalog baru diambil saat Anda membuka langkah ini.</p>
                                                <p class="description">Saat ini ada <?php echo esc_html((string) count($selected_question_ids)); ?> soal tersimpan di draft pilihan.</p>
                                                <div class="cbt-exam-question-filter-actions">
                                                    <a href="<?php echo esc_url($load_question_catalog_url); ?>" class="button button-primary cbt-exam-question-nav-link">Muat Daftar Soal</a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <aside class="cbt-exam-question-sidebar" id="cbt-exam-selected-sidebar" aria-label="Sidebar soal terpilih">
                                        <div class="cbt-exam-selected-sidebar-header">
                                            <div class="cbt-exam-selected-sidebar-copy">
                                                <span class="cbt-exam-selected-sidebar-kicker">Soal Terpilih</span>
                                                <h4>Review draft exam sebelum disimpan</h4>
                                                <p>Tambahkan dari katalog, lepas dari sidebar, lalu simpan saat komposisi sudah final.</p>
                                            </div>
                                            <div class="cbt-exam-selected-sidebar-state">
                                                <span class="cbt-exam-selected-sidebar-dirty<?php echo (!empty($pending_added_ids) || !empty($pending_removed_ids)) ? ' is-dirty' : ''; ?>" id="cbt-exam-selected-dirty-state"><?php echo esc_html($selected_sidebar_status_text); ?></span>
                                            </div>
                                        </div>

                                        <div class="cbt-exam-selected-sidebar-summary">
                                            <article class="cbt-exam-selected-sidebar-chip">
                                                <span>Total Draft</span>
                                                <strong id="cbt-exam-selected-total"><?php echo esc_html((string) count($selected_question_ids)); ?></strong>
                                            </article>
                                            <article class="cbt-exam-selected-sidebar-chip cbt-exam-selected-sidebar-chip--added">
                                                <span>Ditambahkan</span>
                                                <strong id="cbt-exam-selected-added"><?php echo esc_html((string) count($pending_added_ids)); ?></strong>
                                            </article>
                                            <article class="cbt-exam-selected-sidebar-chip cbt-exam-selected-sidebar-chip--removed">
                                                <span>Akan Dilepas</span>
                                                <strong id="cbt-exam-selected-removed"><?php echo esc_html((string) count($pending_removed_ids)); ?></strong>
                                            </article>
                                        </div>

                                        <p class="cbt-exam-selected-sidebar-help" id="cbt-exam-selected-sidebar-help">
                                            Perubahan di sidebar masih berupa draft. Perubahan baru diterapkan ke exam saat klik <strong><?php echo esc_html($editing_exam ? 'Update Exam' : 'Create Exam'); ?></strong>.
                                        </p>

                                        <div class="cbt-exam-selected-sidebar-toolbar">
                                            <label class="screen-reader-text" for="cbt-exam-selected-search">Cari soal terpilih</label>
                                            <input
                                                type="search"
                                                id="cbt-exam-selected-search"
                                                class="cbt-exam-selected-sidebar-search"
                                                placeholder="Cari soal draft..."
                                                autocomplete="off"
                                            />
                                            <span class="cbt-exam-selected-sidebar-filter-count" id="cbt-exam-selected-filter-count" hidden></span>
                                        </div>

                                        <div class="cbt-exam-selected-sidebar-list" id="cbt-exam-selected-sidebar-list">
                                            <?php if (empty($selected_sidebar_questions)): ?>
                                                <div class="cbt-exam-selected-sidebar-empty">
                                                    <strong>Belum ada soal di draft exam.</strong>
                                                    <span>Pilih soal dari katalog di kiri untuk mulai menyusun exam.</span>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ((array) $selected_sidebar_questions as $selected_sidebar_question): ?>
                                                    <?php
                                                    $selected_sidebar_question_id = (int) ($selected_sidebar_question['id'] ?? 0);
                                                    $selected_sidebar_is_initial = in_array($selected_sidebar_question_id, $initial_selected_question_ids, true);
                                                    $selected_sidebar_is_selected = in_array($selected_sidebar_question_id, $selected_question_ids, true);
                                                    $selected_sidebar_status = $selected_sidebar_is_selected
                                                        ? ($selected_sidebar_is_initial ? 'existing' : 'new')
                                                        : 'removed';
                                                    $selected_sidebar_status_label = $selected_sidebar_status === 'new'
                                                        ? 'Baru'
                                                        : ($selected_sidebar_status === 'removed' ? 'Akan dilepas' : 'Existing');
                                                    ?>
                                                    <article class="cbt-exam-selected-item cbt-exam-selected-item--<?php echo esc_attr($selected_sidebar_status); ?>">
                                                        <div class="cbt-exam-selected-item-order"><?php echo esc_html((string) $selected_sidebar_question_id); ?></div>
                                                        <div class="cbt-exam-selected-item-body">
                                                            <div class="cbt-exam-selected-item-topline">
                                                                <span class="cbt-exam-selected-item-type"><?php echo esc_html((string) ($selected_sidebar_question['question_type_label'] ?? ($selected_sidebar_question['question_type'] ?? '-'))); ?></span>
                                                                <span class="cbt-exam-selected-item-status cbt-exam-selected-item-status--<?php echo esc_attr($selected_sidebar_status); ?>"><?php echo esc_html($selected_sidebar_status_label); ?></span>
                                                            </div>
                                                            <strong class="cbt-exam-selected-item-preview"><?php echo esc_html((string) ($selected_sidebar_question['question_preview'] ?? 'Preview soal belum tersedia.')); ?></strong>
                                                            <div class="cbt-exam-selected-item-meta">
                                                                <span class="cbt-exam-question-lineage-badge cbt-exam-question-lineage-badge--<?php echo esc_attr(sanitize_html_class((string) ($selected_sidebar_question['lineage_class'] ?? 'default'))); ?>">
                                                                    <?php echo esc_html((string) ($selected_sidebar_question['lineage_label'] ?? 'Source')); ?>
                                                                </span>
                                                                <small><?php echo esc_html((string) ($selected_sidebar_question['source_context_display'] ?? ($selected_sidebar_question['exam_title'] ?? '-'))); ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="cbt-exam-selected-item-actions">
                                                            <a href="<?php echo esc_url((string) ($selected_sidebar_question['edit_url'] ?? add_query_arg(['page' => 'cbt-question-bank', 'edit' => $selected_sidebar_question_id], admin_url('admin.php')))); ?>" class="button button-small cbt-exam-selected-item-action cbt-exam-selected-item-action--edit" target="_blank" rel="noopener noreferrer">
                                                                Edit
                                                            </a>
                                                            <button type="button" class="button button-small cbt-exam-selected-item-action cbt-exam-selected-item-action--preview" data-sidebar-preview="<?php echo (int) $selected_sidebar_question_id; ?>">
                                                                Lihat
                                                            </button>
                                                            <button type="button" class="button button-small cbt-exam-selected-item-action" data-sidebar-action="<?php echo esc_attr($selected_sidebar_status); ?>" data-question-id="<?php echo (int) $selected_sidebar_question_id; ?>">
                                                                <?php echo esc_html($selected_sidebar_status === 'existing' ? 'Lepas' : 'Batalkan'); ?>
                                                            </button>
                                                        </div>
                                                    </article>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </aside>
                                </div>
                                <div class="cbt-exam-selection-confirm-modal" id="cbt-exam-selection-confirm-modal" hidden aria-hidden="true">
                                    <div class="cbt-exam-selection-confirm-backdrop" data-cbt-modal-close="1"></div>
                                    <div class="cbt-exam-selection-confirm-card" role="dialog" aria-modal="true" aria-labelledby="cbt-exam-selection-confirm-title">
                                        <h4 id="cbt-exam-selection-confirm-title">Lepas soal dari draft exam?</h4>
                                        <p id="cbt-exam-selection-confirm-message">Soal hanya dilepas dari draft exam saat ini. Perubahan baru benar-benar berlaku setelah Anda menyimpan exam.</p>
                                        <p class="cbt-exam-selection-confirm-help">Jika exam sudah punya history attempt, sistem tetap menjaga data historis sesuai aturan sinkronisasi yang ada.</p>
                                        <div class="cbt-exam-selection-confirm-actions">
                                            <button type="button" class="button" id="cbt-exam-selection-confirm-cancel">Batal</button>
                                            <button type="button" class="button button-primary" id="cbt-exam-selection-confirm-submit">Ya, lepas dari draft</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="cbt-exam-panel-nav" aria-label="Navigasi pilih soal">
                                    <p id="cbt-exam-question-submit-help" class="cbt-exam-panel-nav-note">Perlu ubah detail exam? Kembali ke langkah sebelumnya. Komposisi soal di sidebar masih berupa draft sampai Anda menyimpan exam.</p>
                                    <div class="cbt-exam-panel-nav-actions">
                                        <button type="button" class="button cbt-exam-builder-nav-btn" data-target-panel="cbt-exam-details-panel">Prev: Detail Exam</button>
                                        <button type="submit" class="button button-primary cbt-exam-submit-btn"><?php echo esc_html($editing_exam ? 'Update Exam' : 'Create Exam'); ?></button>
                                    </div>
                                </div>
                            </div>
                            <div id="cbt-exam-quickview-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:100000;">
                                <div style="max-width:760px; margin:6vh auto; background:#fff; border-radius:4px; overflow:hidden;">
                                    <div style="padding:10px 12px; border-bottom:1px solid #dcdcde; display:flex; justify-content:space-between; align-items:center;">
                                        <strong id="cbt-exam-quickview-title">Preview Soal</strong>
                                        <button type="button" class="button button-secondary" id="cbt-exam-quickview-close-top">Tutup</button>
                                    </div>
                                    <div id="cbt-exam-quickview-body" style="padding:12px; max-height:65vh; overflow:auto;"></div>
                                    <div style="padding:10px 12px; border-top:1px solid #dcdcde;">
                                        <button type="button" class="button" id="cbt-exam-quickview-close-bottom">Tutup</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div id="cbt-exam-list-panel" class="cbt-exam-page-panel<?php echo $active_exam_page_panel === 'cbt-exam-list-panel' ? ' cbt-active' : ''; ?>" role="tabpanel">
                <h2>Daftar Exam</h2>
                <p class="description cbt-exam-list-description">Pantau exam yang sudah dibuat, lihat monitoring attempt, lalu buka hasil atau edit langsung dari daftar ini.</p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-exam-list-toolbar" id="cbt-exam-list-filter-form">
                    <input type="hidden" name="page" value="cbt-exams" />
                    <input type="hidden" name="cbt_exam_panel" value="list" />
                    <div class="cbt-exam-list-toolbar-grid">
                        <div class="cbt-exam-list-toolbar-field cbt-exam-list-toolbar-field--search">
                            <label for="cbt-exam-search">Cari Exam</label>
                            <input
                                type="search"
                                id="cbt-exam-search"
                                name="cbt_exam_search"
                                value="<?php echo esc_attr($exam_list_state['search']); ?>"
                                placeholder="Cari judul atau deskripsi exam"
                            />
                        </div>
                        <div class="cbt-exam-list-toolbar-field">
                            <label for="cbt-exam-filter-subject">Mapel</label>
                            <select id="cbt-exam-filter-subject" name="cbt_exam_subject">
                                <option value="0">Semua mapel</option>
                                <?php foreach ((array) $subjects as $exam_filter_subject): ?>
                                    <?php
                                    $exam_filter_subject_id = (int) ($exam_filter_subject['id'] ?? 0);
                                    $exam_filter_subject_name = (string) ($exam_filter_subject['name'] ?? '');
                                    $exam_filter_subject_code = trim((string) ($exam_filter_subject['code'] ?? ''));
                                    $exam_filter_subject_label = $exam_filter_subject_name !== ''
                                        ? $exam_filter_subject_name . ($exam_filter_subject_code !== '' ? ' (' . $exam_filter_subject_code . ')' : '')
                                        : 'Mapel';
                                    ?>
                                    <option value="<?php echo (int) $exam_filter_subject_id; ?>" <?php selected($exam_list_state['subject_id'], $exam_filter_subject_id); ?>>
                                        <?php echo esc_html($exam_filter_subject_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-field">
                            <label for="cbt-exam-filter-status">Status</label>
                            <select id="cbt-exam-filter-status" name="cbt_exam_status">
                                <option value="">Semua status</option>
                                <?php foreach ($exam_status_labels as $exam_status_key => $exam_status_label): ?>
                                    <option value="<?php echo esc_attr($exam_status_key); ?>" <?php selected($exam_list_state['status'], $exam_status_key); ?>>
                                        <?php echo esc_html($exam_status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-field">
                            <label for="cbt-exam-filter-kelas">Kelas</label>
                            <select id="cbt-exam-filter-kelas" name="cbt_exam_kelas">
                                <option value="">Semua kelas</option>
                                <?php foreach ($exam_list_kelas_options as $exam_filter_kelas): ?>
                                    <option value="<?php echo esc_attr($exam_filter_kelas); ?>" <?php selected($exam_list_state['kelas'], $exam_filter_kelas); ?>>
                                        <?php echo esc_html($exam_filter_kelas); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-field">
                            <label for="cbt-exam-per-page">Per halaman</label>
                            <select id="cbt-exam-per-page" name="cbt_exam_per_page">
                                <?php foreach ([20, 40, 60, 80, 100] as $exam_per_page_option): ?>
                                    <option value="<?php echo (int) $exam_per_page_option; ?>" <?php selected($exam_per_page, $exam_per_page_option); ?>>
                                        <?php echo esc_html((string) $exam_per_page_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-exam-list-toolbar-actions">
                            <a href="<?php echo esc_url($exam_list_reset_url); ?>" class="button cbt-exam-list-reset">Reset</a>
                        </div>
                    </div>
                </form>
                <?php if (!empty($exam_active_filters)): ?>
                    <div class="cbt-exam-list-active-filters" aria-label="Filter exam aktif">
                        <span class="cbt-exam-list-active-summary"><?php echo esc_html(sprintf('%d exam cocok', $total_exams)); ?></span>
                        <?php foreach ($exam_active_filters as $exam_active_filter): ?>
                            <span class="cbt-exam-list-active-chip">
                                <strong><?php echo esc_html((string) $exam_active_filter['label']); ?></strong>
                                <span><?php echo esc_html((string) $exam_active_filter['value']); ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="cbt-exam-list-table-wrap">
                <table class="widefat striped cbt-exam-list-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mapel</th>
                        <th>Judul</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Jadwal</th>
                        <th>Durasi</th>
                        <th>Soal</th>
                        <th>Monitoring</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($exams)): ?>
                        <tr><td colspan="10"><?php echo !empty($exam_active_filters) ? 'Tidak ada exam yang cocok dengan filter saat ini.' : 'Belum ada exam yang tampil.'; ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                            <?php
                            $kelas_list = CBT_Admin_Exams_Service::split_target_kelas_csv((string) ($exam['target_kelas'] ?? ''));
                            $kelas_display = !empty($kelas_list) ? implode(', ', $kelas_list) : 'Semua kelas';
                            $schedule_parts = [];
                            if (!empty($exam['starts_at'])) {
                                $schedule_parts[] = 'Mulai: ' . (string) $exam['starts_at'];
                            }
                            if (!empty($exam['ends_at'])) {
                                $schedule_parts[] = 'Selesai: ' . (string) $exam['ends_at'];
                            }
                            $schedule_display = !empty($schedule_parts) ? implode(' | ', $schedule_parts) : '-';
                            $status_value = (string) ($exam['status'] ?? 'draft');
                            $status_class = sanitize_html_class($status_value);
                            $randomize_questions_enabled = !empty($exam['randomize_questions']);
                            $randomize_options_enabled = !empty($exam['randomize_options']);
                            $show_student_result_enabled = !array_key_exists('show_student_result', $exam) || !empty($exam['show_student_result']);
                            $enable_calculator_enabled = !array_key_exists('enable_calculator', $exam) || !empty($exam['enable_calculator']);
                            ?>
                            <tr>
                                <td><?php echo (int) $exam['id']; ?></td>
                                <td><?php echo esc_html((string) ($exam['subject_name'] ?? '-')); ?></td>
                                <td>
                                    <div class="cbt-exam-list-title-cell">
                                        <strong><?php echo esc_html((string) ($exam['title'] ?? '')); ?></strong>
                                        <div class="cbt-exam-list-topology">
                                            <span class="cbt-exam-list-topology-badge cbt-exam-list-topology-badge--<?php echo esc_attr(sanitize_html_class((string) ($exam['topology_class'] ?? 'empty'))); ?>">
                                                <?php echo esc_html((string) ($exam['topology_label'] ?? 'Belum Ada Soal')); ?>
                                            </span>
                                            <small><?php echo esc_html((string) ($exam['topology_summary_text'] ?? 'Belum ada soal aktif')); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($kelas_display); ?></td>
                                <td>
                                    <div class="cbt-exam-status-stack">
                                        <span class="cbt-exam-status-pill cbt-exam-status-pill--<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_value); ?></span>
                                        <div class="cbt-exam-status-flags">
                                            <span class="cbt-exam-status-flag cbt-exam-status-flag--question<?php echo $randomize_questions_enabled ? ' is-active' : ' is-inactive'; ?>">
                                                <?php echo esc_html($randomize_questions_enabled ? 'Acak Soal On' : 'Acak Soal Off'); ?>
                                            </span>
                                            <span class="cbt-exam-status-flag cbt-exam-status-flag--option<?php echo $randomize_options_enabled ? ' is-active' : ' is-inactive'; ?>">
                                                <?php echo esc_html($randomize_options_enabled ? 'Acak Opsi On' : 'Acak Opsi Off'); ?>
                                            </span>
                                            <span class="cbt-exam-status-flag cbt-exam-status-flag--result<?php echo $show_student_result_enabled ? ' is-active' : ' is-inactive'; ?>">
                                                <?php echo esc_html($show_student_result_enabled ? 'Hasil Siswa On' : 'Hasil Siswa Off'); ?>
                                            </span>
                                            <span class="cbt-exam-status-flag cbt-exam-status-flag--calc<?php echo $enable_calculator_enabled ? ' is-active' : ' is-inactive'; ?>">
                                                <?php echo esc_html($enable_calculator_enabled ? 'Kalkulator On' : 'Kalkulator Off'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html($schedule_display); ?></td>
                                <td><?php echo esc_html((string) ((int) ($exam['duration_minutes'] ?? 0))); ?> menit</td>
                                <td><?php echo esc_html((string) ((int) ($exam['question_count'] ?? 0))); ?></td>
                                <td>
                                    <?php
                                    $attempt_total = (int) ($exam['attempt_total'] ?? 0);
                                    $attempt_in_progress = (int) ($exam['attempt_in_progress'] ?? 0);
                                    $attempt_completed = (int) ($exam['attempt_completed'] ?? 0);
                                    ?>
                                    <div class="cbt-exam-monitoring-stack">
                                        <span><strong><?php echo esc_html((string) $attempt_total); ?></strong> total</span>
                                        <span><strong><?php echo esc_html((string) $attempt_in_progress); ?></strong> ongoing</span>
                                        <span><strong><?php echo esc_html((string) $attempt_completed); ?></strong> selesai</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="cbt-exam-row-actions">
                                        <a
                                            class="cbt-exam-row-action cbt-exam-row-action--preview"
                                            href="<?php echo esc_url(add_query_arg(array_merge($exam_list_state_args, ['preview_exam_id' => (int) $exam['id']]), admin_url('admin.php'))); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >Lihat Soal</a>
                                        <a
                                            class="cbt-exam-row-action cbt-exam-row-action--results"
                                            href="<?php echo esc_url(add_query_arg(['page' => 'cbt-results', 'cbt_exam_id' => (int) $exam['id']], admin_url('admin.php'))); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            title="Buka Results di tab baru"
                                        >Results</a>
                                        <a class="cbt-exam-row-action cbt-exam-row-action--edit" href="<?php echo esc_url(add_query_arg(CBT_Admin_Exams_Service::add_exam_list_state_args(['page' => 'cbt-exams', 'edit' => (int) $exam['id']], $exam_list_state), admin_url('admin.php'))); ?>">Edit</a>
                                        <a class="cbt-exam-row-action cbt-exam-row-action--delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(CBT_Admin_Exams_Service::add_exam_list_state_args(['action' => 'cbt_delete_exam', 'id' => (int) $exam['id'], 'cbt_exam_panel' => 'list'], $exam_list_state), admin_url('admin-post.php')), 'cbt_delete_exam_' . (int) $exam['id'])); ?>" onclick="return confirm('Delete this exam?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            <?php
            $exam_pagination_links = [];
            if ($exam_total_pages > 1) {
                $exam_pagination_links = paginate_links([
                    'base' => add_query_arg(
                        array_merge($exam_list_base_args, ['cbt_exam_paged' => '%#%']),
                        admin_url('admin.php')
                    ),
                    'format' => '',
                    'current' => $exam_current_page,
                    'total' => $exam_total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'type' => 'array',
                    'end_size' => 1,
                    'mid_size' => 1,
                ]);
            }
            ?>
                <div class="tablenav bottom cbt-admin-pagination-wrap" style="margin-top:10px;">
                    <div class="tablenav-pages cbt-admin-pagination" style="float:none; margin:0;">
                        <span class="displaying-num cbt-admin-total"><?php echo esc_html(sprintf('Total exam: %d', $total_exams)); ?></span>
                        <?php if (!empty($exam_pagination_links)): ?>
                            <span class="pagination-links cbt-admin-pagination-links">
                                <?php foreach ($exam_pagination_links as $exam_pagination_link): ?>
                                    <?php echo wp_kses_post($exam_pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($can_manage_exam_snapshots)): ?>
                <div
                    id="cbt-exam-snapshot-panel"
                    class="cbt-exam-page-panel<?php echo $active_exam_page_panel === 'cbt-exam-snapshot-panel' ? ' cbt-active' : ''; ?>"
                    role="tabpanel"
                    data-cbt-snapshot-tab="<?php echo esc_attr((string) ($exam_snapshot_tab ?? CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT)); ?>"
                    data-cbt-snapshot-auto-refresh-seconds="<?php echo esc_attr(((string) ($exam_snapshot_tab ?? CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT)) === CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT ? (string) max(1, (int) (($adaptive_load_context['admin_snapshot_refresh_seconds'] ?? 10))) : '0'); ?>"
                >
                    <div id="cbt-exam-clean-progress-overlay" class="cbt-exam-save-progress-overlay cbt-exam-clean-progress-overlay" hidden aria-hidden="true" style="display:none;">
                        <div class="cbt-exam-save-progress-card cbt-exam-clean-progress-card">
                            <h3 id="cbt-exam-clean-progress-title">Membersihkan Snapshot Pra Ujian</h3>
                            <p id="cbt-exam-clean-progress-message">Menyiapkan proses clean snapshot untuk exam terpilih.</p>
                            <div class="cbt-exam-save-progress-meta">
                                <span id="cbt-exam-clean-progress-phase">Menyiapkan proses</span>
                                <span id="cbt-exam-clean-progress-stats"></span>
                            </div>
                            <div class="cbt-exam-save-progress-bar" aria-hidden="true">
                                <div id="cbt-exam-clean-progress-fill" class="cbt-exam-save-progress-fill cbt-exam-clean-progress-fill"></div>
                            </div>
                            <div id="cbt-exam-clean-progress-percent" class="cbt-exam-save-progress-percent">0%</div>
                            <p class="description cbt-exam-save-progress-help">Jawaban, nilai, attempt, dan sesi login tetap aman. Jangan tutup halaman ini selama proses clean berjalan.</p>
                        </div>
                    </div>
                    <div id="cbt-exam-bulk-progress-overlay" class="cbt-exam-save-progress-overlay cbt-exam-bulk-progress-overlay" hidden aria-hidden="true" style="display:none;">
                        <div class="cbt-exam-save-progress-card cbt-exam-bulk-progress-card">
                            <h3 id="cbt-exam-bulk-progress-title">Menjalankan Bulk One-Click</h3>
                            <p id="cbt-exam-bulk-progress-message">Menyiapkan antrean bulk pra ujian untuk exam terpilih.</p>
                            <div class="cbt-exam-save-progress-meta">
                                <span id="cbt-exam-bulk-progress-phase">Menyiapkan proses</span>
                                <span id="cbt-exam-bulk-progress-stats"></span>
                            </div>
                            <div class="cbt-exam-save-progress-bar" aria-hidden="true">
                                <div id="cbt-exam-bulk-progress-fill" class="cbt-exam-save-progress-fill cbt-exam-bulk-progress-fill"></div>
                            </div>
                            <div id="cbt-exam-bulk-progress-percent" class="cbt-exam-save-progress-percent">0%</div>
                            <p class="description cbt-exam-save-progress-help">Bulk One-Click akan memasukkan exam terpilih ke antrean preflight yang sama. Jangan tutup halaman ini selama proses berjalan.</p>
                        </div>
                    </div>
                    <?php CBT_Admin_Exams_Page::render_snapshot_panel([
                        'subjects' => $subjects,
                        'exam_status_labels' => $exam_status_labels,
                        'exam_list_state' => $exam_list_state,
                        'exam_list_kelas_options' => $exam_list_kelas_options,
                        'exam_per_page' => $exam_per_page,
                        'exam_active_filters' => $exam_snapshot_active_filters ?? $exam_active_filters,
                        'exam_snapshot_tab' => $exam_snapshot_tab ?? CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
                        'exam_snapshot_filter_state' => $exam_snapshot_filter_state ?? ['exam_id' => 0],
                        'exam_snapshot_exam_options' => $exam_snapshot_exam_options ?? [],
                        'exam_snapshot_total' => $exam_snapshot_total,
                        'exam_snapshot_rows' => $exam_snapshot_rows,
                        'exam_snapshot_preview_pages' => $exam_snapshot_preview_pages ?? [],
                        'exam_readiness_page' => $exam_readiness_page ?? 1,
                        'exam_readiness_pages' => $exam_readiness_pages ?? [],
                        'bulk_preflight' => $bulk_preflight ?? [],
                        'exam_snapshot_reset_url' => $exam_snapshot_reset_url,
                        'student_snapshot_filter_state' => $student_snapshot_filter_state ?? ['search' => '', 'kelas' => '', 'ruang' => '', 'status' => '', 'paged' => 1, 'per_page' => 25],
                        'student_snapshot_kelas_options' => $student_snapshot_kelas_options ?? [],
                        'student_snapshot_ruang_options' => $student_snapshot_ruang_options ?? [],
                        'student_snapshot_status_options' => $student_snapshot_status_options ?? [],
                        'student_snapshot_rows' => $student_snapshot_rows ?? [],
                        'student_snapshot_total' => $student_snapshot_total ?? 0,
                        'student_snapshot_total_pages' => $student_snapshot_total_pages ?? 1,
                        'student_snapshot_current_page' => $student_snapshot_current_page ?? 1,
                        'student_snapshot_per_page' => $student_snapshot_per_page ?? 25,
                        'student_snapshot_active_filters' => $student_snapshot_active_filters ?? [],
                        'student_snapshot_reset_url' => $student_snapshot_reset_url ?? $exam_snapshot_reset_url,
                        'login_snapshot_health_context' => $login_snapshot_health_context ?? [],
                        'adaptive_load_context' => $adaptive_load_context ?? [],
                    ]); ?>
                </div>
            <?php endif; ?>
        </div>
            <style>
                #wpbody-content {
                    padding-bottom: 88px;
                    box-sizing: border-box;
                }
                #wpfooter {
                    position: static;
                    margin-top: 24px;
                }
                .cbt-exams-page {
                    max-width: 1220px;
                }
                .cbt-exams-shell {
                    display: grid;
                    gap: 18px;
                    margin-top: 18px;
                }
                .cbt-exam-bank-guard-card {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 18px;
                    padding: 18px 22px;
                    border: 1px solid #dbe6f1;
                    border-radius: 20px;
                    background:
                        radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 36%),
                        linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.05);
                }
                .cbt-exam-bank-guard-copy {
                    max-width: 760px;
                }
                .cbt-exam-bank-guard-kicker {
                    display: inline-flex;
                    align-items: center;
                    min-height: 28px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: #e8f1ff;
                    color: #0f4fa8;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-exam-bank-guard-copy h2 {
                    margin: 12px 0 8px;
                    font-size: 22px;
                    line-height: 1.2;
                }
                .cbt-exam-bank-guard-copy p {
                    margin: 0;
                    color: #475569;
                    line-height: 1.65;
                }
                .cbt-exam-bank-guard-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                    justify-content: flex-end;
                }
                .cbt-exams-hero {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 18px;
                    padding: 24px 28px;
                    border: 1px solid #d7dbe2;
                    border-radius: 24px;
                    background:
                        radial-gradient(circle at top right, rgba(59, 130, 246, 0.14), transparent 34%),
                        linear-gradient(135deg, #ffffff 0%, #f6f9fc 100%);
                    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
                }
                .cbt-exams-hero-copy {
                    max-width: 720px;
                }
                .cbt-exams-kicker {
                    display: inline-flex;
                    align-items: center;
                    min-height: 28px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: #e8f1ff;
                    color: #0f4fa8;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.06em;
                    text-transform: uppercase;
                }
                .cbt-exams-hero h1 {
                    margin: 12px 0 8px;
                    font-size: 30px;
                    line-height: 1.15;
                }
                .cbt-exams-hero p {
                    margin: 0;
                    color: #475569;
                    font-size: 14px;
                    line-height: 1.65;
                }
                .cbt-exams-hero-stats {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(150px, 1fr));
                    gap: 12px;
                    min-width: 340px;
                }
                .cbt-exams-hero-stat {
                    padding: 16px 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 18px;
                    background: rgba(255, 255, 255, 0.9);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
                }
                .cbt-exams-hero-stat span {
                    display: block;
                    margin-bottom: 6px;
                    color: #64748b;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-exams-hero-stat strong {
                    display: block;
                    color: #0f172a;
                    font-size: 24px;
                    line-height: 1.1;
                }
                .cbt-exams-operational-strip {
                    display: grid;
                    gap: 12px;
                    padding: 14px 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 20px;
                    background: #fbfdff;
                    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
                }
                .cbt-exams-operational-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                }
                .cbt-exams-operational-kicker {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 0 10px;
                    border-radius: 999px;
                    background: #eef5ff;
                    color: #0f4fa8;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .cbt-exams-operational-head p {
                    margin: 8px 0 0;
                    color: #64748b;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-exams-operational-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                    gap: 10px;
                }
                .cbt-exams-operational-card {
                    display: grid;
                    gap: 4px;
                    min-height: 88px;
                    padding: 12px 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: #ffffff;
                }
                .cbt-exams-operational-card.is-success {
                    border-color: #cce7d8;
                    background: #f6fffa;
                }
                .cbt-exams-operational-card.is-warning {
                    border-color: #f5d7aa;
                    background: #fffaf2;
                }
                .cbt-exams-operational-card-label {
                    color: #64748b;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-exams-operational-card strong {
                    color: #0f172a;
                    font-size: 22px;
                    line-height: 1.1;
                }
                .cbt-exams-operational-card small {
                    color: #475569;
                    font-size: 12px;
                    line-height: 1.45;
                }
                .cbt-exams-operational-card-hint {
                    display: inline-flex;
                    align-items: center;
                    width: fit-content;
                    min-height: 22px;
                    padding: 0 8px;
                    border-radius: 999px;
                    background: #f1f5f9;
                    color: #475569;
                    font-size: 11px;
                    font-weight: 600;
                }
                .cbt-exams-page .notice {
                    margin: 0;
                }
                .cbt-tab-buttons {
                    display: flex;
                    gap: 12px;
                    margin: 0;
                    flex-wrap: wrap;
                }
                .cbt-exams-page-tabs {
                    padding: 6px;
                    border: 1px solid #d9e2ec;
                    border-radius: 20px;
                    background: #f8fbff;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
                }
                .cbt-exams-page-tab.button {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 4px;
                    min-width: 220px;
                    min-height: 68px;
                    padding: 14px 18px;
                    border: 0;
                    border-radius: 16px;
                    background: transparent;
                    color: #334155;
                    box-shadow: none;
                    text-align: left;
                }
                .cbt-exams-page-tab.button:hover,
                .cbt-exams-page-tab.button:focus {
                    background: rgba(255, 255, 255, 0.72);
                    color: #0f172a;
                }
                .cbt-exams-page-tab.button.cbt-active {
                    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
                    color: #ffffff;
                    box-shadow: 0 10px 20px rgba(19, 94, 150, 0.22);
                }
                .cbt-exams-page-tab-label {
                    font-size: 15px;
                    font-weight: 700;
                    line-height: 1.2;
                }
                .cbt-exams-page-tab small {
                    display: block;
                    font-size: 12px;
                    line-height: 1.45;
                    opacity: 0.84;
                }
                .cbt-exam-page-panel,
                .cbt-exam-builder-panel {
                    display: none;
                }
                .cbt-exam-page-panel.cbt-active,
                .cbt-exam-builder-panel.cbt-active {
                    display: block;
                }
                .cbt-exam-page-panel {
                    padding: 24px;
                    border: 1px solid #dcdcde;
                    border-radius: 22px;
                    background: #ffffff;
                    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
                }
                .cbt-exam-page-panel > h2 {
                    margin: 0 0 8px;
                    font-size: 28px;
                    line-height: 1.15;
                }
                .cbt-exam-page-panel > .description {
                    margin: 0;
                    color: #64748b;
                    line-height: 1.6;
                }
                .cbt-exam-snapshot-shell {
                    display: grid;
                    gap: 14px;
                }
                .cbt-exam-snapshot-subtabs {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                    gap: 8px;
                }
                .cbt-exam-snapshot-subtab {
                    display: inline-flex;
                    flex-direction: column;
                    align-items: flex-start;
                    justify-content: center;
                    gap: 3px;
                    min-height: 52px;
                    min-width: 0;
                    padding: 10px 14px;
                    border: 1px solid #c9d8ea;
                    border-radius: 14px;
                    background: #f8fbff;
                    color: #274c77;
                    text-decoration: none;
                    text-align: left;
                    transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
                }
                .cbt-exam-snapshot-subtab-label {
                    font-size: 13px;
                    font-weight: 700;
                    line-height: 1.2;
                }
                .cbt-exam-snapshot-subtab small {
                    display: block;
                    max-width: 100%;
                    overflow: hidden;
                    font-size: 11px;
                    line-height: 1.3;
                    opacity: 0.84;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                .cbt-exam-snapshot-subtab:hover,
                .cbt-exam-snapshot-subtab:focus {
                    border-color: #2271b1;
                    background: #ffffff;
                    color: #135e96;
                    box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
                    outline: none;
                }
                .cbt-exam-snapshot-subtab.is-active {
                    border-color: #2271b1;
                    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
                    color: #ffffff;
                    box-shadow: 0 8px 18px rgba(34, 113, 177, 0.16);
                }
                .cbt-exam-snapshot-section {
                    display: grid;
                    gap: 16px;
                }
                .cbt-exam-snapshot-adaptive-banner {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    padding: 16px 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 18px;
                    background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
                }
                .cbt-exam-snapshot-adaptive-banner.is-warning {
                    border-color: #f4d27a;
                    background: linear-gradient(135deg, #fff9e8 0%, #ffffff 100%);
                }
                .cbt-exam-snapshot-adaptive-banner.is-error {
                    border-color: #f1b6b6;
                    background: linear-gradient(135deg, #fff2f2 0%, #ffffff 100%);
                }
                .cbt-exam-snapshot-adaptive-copy {
                    display: grid;
                    gap: 6px;
                }
                .cbt-exam-snapshot-adaptive-head {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-snapshot-adaptive-kicker {
                    display: inline-flex;
                    align-items: center;
                    padding: 4px 10px;
                    border-radius: 999px;
                    background: #eaf3ff;
                    color: #135e96;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.06em;
                    text-transform: uppercase;
                }
                .cbt-exam-snapshot-adaptive-head strong {
                    color: #0f172a;
                    font-size: 16px;
                }
                .cbt-exam-snapshot-adaptive-head small {
                    color: #64748b;
                    font-weight: 600;
                }
                .cbt-exam-snapshot-adaptive-meta {
                    display: flex;
                    align-items: center;
                    gap: 10px 14px;
                    flex-wrap: wrap;
                    color: #475569;
                    font-size: 12px;
                }
                .cbt-exam-snapshot-adaptive-signals {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                    margin-top: 2px;
                }
                .cbt-exam-snapshot-adaptive-signals span {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    min-height: 26px;
                    padding: 0 10px;
                    border: 1px solid #dbe6f1;
                    border-radius: 999px;
                    background: rgba(255, 255, 255, 0.76);
                    color: #334155;
                    font-size: 11px;
                    line-height: 1;
                }
                .cbt-exam-snapshot-adaptive-signals strong {
                    color: #0f172a;
                    font-weight: 700;
                }
                .cbt-exam-snapshot-adaptive-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                    justify-content: flex-end;
                }
                .cbt-exam-snapshot-adaptive-actions form {
                    margin: 0;
                }
                .cbt-exam-snapshot-section[hidden] {
                    display: none !important;
                }
                .cbt-exam-snapshot-section-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                }
                .cbt-exam-snapshot-section-head h3 {
                    margin: 0 0 6px;
                    color: #0f172a;
                    font-size: 20px;
                    line-height: 1.2;
                }
                .cbt-exam-snapshot-monitor-list {
                    display: grid;
                    gap: 18px;
                }
                .cbt-exam-snapshot-monitor-card {
                    display: grid;
                    gap: 16px;
                    padding: 20px;
                    border: 1px solid #dbe6f1;
                    border-radius: 22px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
                }
                .cbt-exam-snapshot-monitor-card-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                }
                .cbt-exam-snapshot-card {
                    display: grid;
                    gap: 14px;
                    padding: 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 20px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
                }
                .cbt-exam-snapshot-card-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 14px 18px;
                    flex-wrap: wrap;
                }
                .cbt-exam-snapshot-card-head h4 {
                    margin: 0 0 8px;
                    color: #0f172a;
                    font-size: 18px;
                    line-height: 1.3;
                }
                .cbt-exam-snapshot-card-head .cbt-exam-snapshot-status {
                    flex: 0 0 auto;
                    align-self: flex-start;
                }
                .cbt-exam-snapshot-empty-state {
                    padding: 20px 22px;
                    border: 1px dashed #c9d8ea;
                    border-radius: 18px;
                    background: #f8fbff;
                    color: #475569;
                }
                .cbt-exam-snapshot-actions-bar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                    padding: 16px 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 18px;
                    background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
                }
                .cbt-exam-snapshot-actions-copy {
                    display: grid;
                    gap: 4px;
                    color: #475569;
                }
                .cbt-exam-snapshot-actions-copy strong {
                    color: #0f172a;
                    font-size: 15px;
                }
                .cbt-exam-snapshot-actions-bar--quiet {
                    border-style: dashed;
                    background: #fcfdff;
                }
                .cbt-exam-snapshot-bulk-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-snapshot-bulk-form {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .cbt-exam-snapshot-table th,
                .cbt-exam-snapshot-table td {
                    vertical-align: top;
                }
                .cbt-exam-snapshot-exam-cell {
                    min-width: 460px;
                }
                .cbt-exam-snapshot-summary-cell {
                    min-width: 320px;
                }
                .cbt-exam-snapshot-title {
                    margin-bottom: 8px;
                    color: #0f172a;
                    font-size: 16px;
                    font-weight: 700;
                    line-height: 1.35;
                }
                .cbt-exam-snapshot-meta {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px 14px;
                    margin-bottom: 12px;
                    color: #475569;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-exam-snapshot-detail-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 8px 14px;
                    margin-bottom: 12px;
                    color: #475569;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-detail-grid {
                    gap: 12px 16px;
                    margin: 2px 0 0;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-detail-grid > span {
                    display: flex;
                    align-items: flex-start;
                    flex-wrap: wrap;
                    gap: 8px;
                    padding: 10px 12px;
                    border: 1px solid #e3edf8;
                    border-radius: 14px;
                    background: rgba(255, 255, 255, 0.82);
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-detail-grid > span > strong {
                    color: #334155;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-detail-grid .cbt-exam-snapshot-status {
                    min-width: 92px;
                    min-height: 30px;
                    padding-inline: 12px;
                    font-size: 11px;
                }
                .cbt-exam-snapshot-detail-grid code {
                    font-size: 11px;
                }
                .cbt-exam-snapshot-note {
                    margin: 0 0 12px;
                    color: #475569;
                    font-size: 12px;
                    line-height: 1.6;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-note {
                    margin: 0;
                }
                .cbt-exam-snapshot-summary-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 10px;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-summary-grid {
                    gap: 12px;
                    margin-top: 2px;
                }
                .cbt-exam-snapshot-summary-grid--start {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 8px;
                }
                .cbt-exam-snapshot-summary-grid--runtime {
                    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                    gap: 8px;
                }
                .cbt-exam-snapshot-summary-card {
                    display: grid;
                    gap: 6px;
                    padding: 12px 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: #f8fbff;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-summary-card {
                    min-height: 84px;
                    align-content: start;
                    padding: 14px 16px;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-summary-grid--start .cbt-exam-snapshot-summary-card {
                    min-height: 64px;
                    gap: 4px;
                    padding: 10px 12px;
                    border-radius: 14px;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-summary-grid--runtime .cbt-exam-snapshot-summary-card {
                    min-height: 68px;
                    gap: 4px;
                    padding: 10px 12px;
                    border-radius: 14px;
                }
                .cbt-exam-snapshot-summary-card--status {
                    grid-column: 1 / -1;
                }
                .cbt-exam-snapshot-summary-label {
                    color: #64748b;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-exam-snapshot-summary-value {
                    color: #0f172a;
                    font-size: 16px;
                    font-weight: 700;
                    line-height: 1.3;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-summary-value {
                    word-break: break-word;
                }
                .cbt-exam-snapshot-summary-grid--start .cbt-exam-snapshot-summary-value {
                    font-size: 14px;
                    line-height: 1.25;
                }
                .cbt-exam-snapshot-summary-grid--runtime .cbt-exam-snapshot-summary-value {
                    font-size: 14px;
                    line-height: 1.25;
                }
                .cbt-exam-snapshot-monitor-card--compact-start .cbt-exam-snapshot-meta {
                    margin-bottom: 8px;
                }
                .cbt-exam-snapshot-monitor-card--compact-start .cbt-exam-snapshot-summary-stack {
                    gap: 4px;
                    font-size: 11px;
                    line-height: 1.35;
                }
                .cbt-exam-snapshot-monitor-card--compact-start .cbt-exam-snapshot-summary-stack strong {
                    font-size: 13px;
                }
                .cbt-exam-snapshot-summary-grid--runtime .cbt-exam-readiness-summary-meta {
                    font-size: 11px;
                    line-height: 1.45;
                }
                .cbt-exam-snapshot-preview-row td {
                    padding-top: 0;
                    border-top: none;
                }
                .cbt-exam-snapshot-preflight-row td {
                    padding-top: 0;
                    border-top: none;
                }
                .cbt-exam-snapshot-preview-row-cell {
                    padding: 0 12px 14px;
                    background: #ffffff;
                }
                .cbt-exam-snapshot-preflight-row-cell {
                    padding: 0 12px 14px;
                    background: #ffffff;
                }
                .cbt-exam-snapshot-auto-warm-row td {
                    padding-top: 0;
                    border-top: none;
                }
                .cbt-exam-snapshot-auto-warm-row-cell {
                    padding: 0 12px 14px;
                    background: #ffffff;
                }
                .cbt-exam-snapshot-preview-row.is-loading {
                    opacity: 0.7;
                    transition: opacity 0.15s ease;
                }
                .cbt-exam-snapshot-preview-row.is-loading .cbt-exam-snapshot-preview-dropdown {
                    pointer-events: none;
                }
                .cbt-exam-snapshot-preview-dropdown {
                    border: 1px solid #d6e4ff;
                    border-radius: 16px;
                    background: linear-gradient(180deg, #fbfdff 0%, #f4f8ff 100%);
                    overflow: hidden;
                }
                .cbt-exam-snapshot-preview-summary {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 14px 16px;
                    cursor: pointer;
                    list-style: none;
                    color: #163a74;
                    font-weight: 700;
                }
                .cbt-exam-snapshot-preview-summary::-webkit-details-marker {
                    display: none;
                }
                .cbt-exam-snapshot-preview-summary-title {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }
                .cbt-exam-snapshot-preview-summary-title::before {
                    content: '';
                    width: 8px;
                    height: 8px;
                    border-right: 2px solid #2563eb;
                    border-bottom: 2px solid #2563eb;
                    transform: rotate(45deg);
                    transition: transform 0.18s ease;
                    margin-top: -3px;
                }
                .cbt-exam-snapshot-preview-dropdown[open] .cbt-exam-snapshot-preview-summary-title::before {
                    transform: rotate(225deg);
                    margin-top: 3px;
                }
                .cbt-exam-snapshot-preview-summary-meta {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                    color: #52709a;
                    font-size: 12px;
                    font-weight: 600;
                }
                .cbt-exam-snapshot-preview-body {
                    padding: 0 16px 16px;
                    border-top: 1px solid #e3edff;
                }
                .cbt-exam-snapshot-preview-list {
                    display: grid;
                    gap: 10px;
                    margin-top: 14px;
                    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                }
                .cbt-exam-snapshot-preview-item {
                    padding: 12px 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: #f8fbff;
                }
                .cbt-exam-snapshot-preview-head {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-bottom: 8px;
                }
                .cbt-exam-snapshot-preview-pill {
                    display: inline-flex;
                    align-items: center;
                    min-height: 28px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: #ffffff;
                    border: 1px solid #d7e3f1;
                    color: #0f4fa8;
                    font-size: 12px;
                    font-weight: 600;
                }
                .cbt-exam-snapshot-preview-text {
                    color: #334155;
                    font-size: 13px;
                    line-height: 1.6;
                }
                .cbt-exam-snapshot-preview-pagination {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    margin-top: 14px;
                    flex-wrap: wrap;
                }
                .cbt-exam-snapshot-preview-pagination-state {
                    color: #35527d;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-exam-snapshot-status {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 32px;
                    min-width: 108px;
                    padding: 0 14px;
                    border-radius: 999px;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .cbt-exam-snapshot-status.is-success {
                    background: #e9f8ef;
                    color: #166534;
                }
                .cbt-exam-snapshot-status.is-warning {
                    background: #fff4d6;
                    color: #92400e;
                }
                .cbt-exam-snapshot-status.is-error {
                    background: #fee2e2;
                    color: #b91c1c;
                }
                .cbt-exam-snapshot-queue-panel {
                    display: grid;
                    gap: 10px;
                    margin: 0 0 12px;
                    padding: 12px 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: #f8fbff;
                }
                .cbt-exam-snapshot-queue-panel-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-snapshot-queue-panel-copy {
                    display: grid;
                    gap: 4px;
                }
                .cbt-exam-snapshot-queue-panel-copy strong {
                    color: #0f172a;
                    font-size: 13px;
                }
                .cbt-exam-snapshot-queue-panel-copy span {
                    color: #475569;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-exam-snapshot-queue-stats {
                    display: grid;
                    gap: 8px;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
                .cbt-exam-snapshot-queue-stat {
                    display: grid;
                    gap: 4px;
                    padding: 10px 12px;
                    border: 1px solid #dbe6f1;
                    border-radius: 14px;
                    background: rgba(255, 255, 255, 0.92);
                    min-height: 64px;
                    align-content: start;
                }
                .cbt-exam-snapshot-queue-stat .cbt-exam-snapshot-summary-value {
                    font-size: 14px;
                    line-height: 1.25;
                }
                .cbt-exam-snapshot-summary-stack {
                    display: grid;
                    gap: 6px;
                    color: #475569;
                    font-size: 12px;
                    line-height: 1.4;
                }
                .cbt-exam-snapshot-summary-stack strong {
                    color: #0f172a;
                    font-size: 14px;
                }
                .cbt-exam-snapshot-row-form {
                    display: flex;
                    align-items: center;
                    justify-content: flex-start;
                }
                .cbt-exam-snapshot-note--queue {
                    margin: 0;
                    font-size: 11px;
                    line-height: 1.5;
                }
                .cbt-exam-snapshot-row-actions {
                    display: grid;
                    gap: 8px;
                }
                .cbt-exam-snapshot-card .cbt-exam-snapshot-row-actions {
                    padding-top: 2px;
                }
                .cbt-exam-auto-warm-panel {
                    display: grid;
                    gap: 10px;
                    margin-top: 0;
                    padding: 12px;
                    border: 1px solid #dbe6f1;
                    border-radius: 14px;
                    background: #f8fbff;
                }
                .cbt-exam-auto-warm-panel-head {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-auto-warm-panel-head strong {
                    color: #0f172a;
                    font-size: 13px;
                }
                .cbt-exam-auto-warm-panel-grid {
                    display: grid;
                    gap: 6px 12px;
                    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                    color: #475569;
                    font-size: 12px;
                }
                .cbt-exam-auto-warm-tech {
                    border-top: 1px dashed #d6e4ff;
                    padding-top: 10px;
                }
                .cbt-exam-auto-warm-tech summary {
                    cursor: pointer;
                    color: #163a74;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-exam-auto-warm-tech-body {
                    display: grid;
                    gap: 6px 12px;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    margin-top: 10px;
                    color: #475569;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-exam-auto-warm-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: nowrap;
                }
                .cbt-exam-auto-warm-actions .cbt-exam-snapshot-row-form {
                    display: inline-flex;
                    flex: 0 0 auto;
                }
                .cbt-exam-preflight-panel {
                    display: grid;
                    gap: 12px;
                    padding: 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: #ffffff;
                }
                .cbt-exam-preflight-panel-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-exam-preflight-panel-head strong {
                    color: #0f172a;
                    font-size: 14px;
                }
                .cbt-exam-preflight-summary-grid {
                    display: grid;
                    gap: 10px;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                }
                .cbt-exam-preflight-summary-grid .cbt-exam-readiness-summary-card {
                    gap: 4px;
                    padding: 10px 12px;
                }
                .cbt-exam-preflight-summary-grid .cbt-exam-snapshot-summary-value {
                    font-size: 15px;
                }
                .cbt-exam-readiness-target-row {
                    display: grid;
                    gap: 12px;
                    grid-template-columns: minmax(160px, 220px) minmax(0, 1fr);
                    align-items: stretch;
                }
                .cbt-exam-readiness-target-count,
                .cbt-exam-readiness-target-classes {
                    display: grid;
                    gap: 6px;
                    padding: 12px;
                    border: 1px solid #dbe6f1;
                    border-radius: 14px;
                    background: #f8fbff;
                }
                .cbt-exam-readiness-target-count .cbt-exam-snapshot-summary-value {
                    font-size: 18px;
                }
                .cbt-exam-readiness-target-list {
                    color: #475569;
                    font-size: 12px;
                    font-weight: 600;
                    line-height: 1.55;
                    word-break: break-word;
                }
                .cbt-exam-preflight-stage-grid {
                    display: grid;
                    gap: 10px;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                }
                .cbt-exam-preflight-stage-card {
                    display: grid;
                    gap: 8px;
                    padding: 12px;
                    border: 1px solid #dbe6f1;
                    border-radius: 14px;
                    background: #f8fbff;
                    align-content: start;
                    min-height: 124px;
                }
                .cbt-exam-preflight-stage-card-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-exam-preflight-stage-summary {
                    color: #0f172a;
                    font-size: 14px;
                    font-weight: 700;
                    line-height: 1.45;
                }
                .cbt-exam-preflight-stage-meta {
                    color: #64748b;
                    font-size: 12px;
                    line-height: 1.55;
                }
                .cbt-exam-preflight-actions {
                    display: flex;
                    align-items: center;
                    justify-content: flex-start;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-readiness-panel {
                    display: grid;
                    gap: 12px;
                    padding: 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: #ffffff;
                }
                .cbt-exam-readiness-panel-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-exam-readiness-panel-head strong {
                    color: #0f172a;
                    font-size: 14px;
                }
                .cbt-exam-readiness-summary-grid {
                    display: grid;
                    gap: 10px;
                    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                }
                .cbt-exam-readiness-summary-card {
                    display: grid;
                    gap: 6px;
                    padding: 12px;
                    border: 1px solid #dbe6f1;
                    border-radius: 14px;
                    background: #f8fbff;
                }
                .cbt-exam-readiness-summary-meta {
                    color: #64748b;
                    font-size: 11px;
                    font-weight: 600;
                    line-height: 1.45;
                }
                .cbt-exam-readiness-flags {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-exam-readiness-alerts {
                    display: grid;
                    gap: 12px;
                    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                }
                .cbt-exam-readiness-alert-group {
                    display: grid;
                    gap: 8px;
                    padding: 12px;
                    border: 1px solid #dbe6f1;
                    border-radius: 14px;
                    background: #f8fbff;
                }
                .cbt-exam-readiness-alert-group strong {
                    color: #0f172a;
                    font-size: 13px;
                }
                .cbt-exam-readiness-alert-list {
                    margin: 0;
                    padding-left: 18px;
                    color: #475569;
                }
                .cbt-exam-readiness-alert-list li {
                    margin: 0 0 6px;
                    line-height: 1.5;
                }
                .cbt-exam-readiness-actions {
                    display: flex;
                    align-items: center;
                    justify-content: flex-start;
                }
                .cbt-exam-readiness-problem-section {
                    display: grid;
                    gap: 10px;
                    padding-top: 8px;
                    border-top: 1px dashed #dbe6f1;
                }
                .cbt-exam-readiness-problem-head {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-readiness-problem-head strong {
                    color: #0f172a;
                    font-size: 13px;
                }
                .cbt-exam-readiness-problem-table th,
                .cbt-exam-readiness-problem-table td {
                    vertical-align: top;
                }
                .cbt-exam-readiness-pagination {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-readiness-pagination-state {
                    color: #35527d;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-exam-snapshot-student-toolbar {
                    padding: 16px 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 18px;
                    background: #ffffff;
                }
                .cbt-exam-snapshot-student-toolbar-grid {
                    display: grid;
                    grid-template-columns: minmax(260px, 1.4fr) minmax(180px, 0.7fr) minmax(180px, 0.7fr) auto;
                    gap: 14px;
                    align-items: end;
                }
                .cbt-exam-snapshot-student-search {
                    display: grid;
                    gap: 8px;
                }
                .cbt-exam-snapshot-student-field {
                    display: grid;
                    gap: 8px;
                }
                .cbt-exam-snapshot-student-search label {
                    color: #334155;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-exam-snapshot-student-field label {
                    color: #334155;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-exam-snapshot-student-search input {
                    width: 100%;
                }
                .cbt-exam-snapshot-student-field select {
                    width: 100%;
                }
                .cbt-exam-snapshot-student-toolbar-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-student-snapshot-table th,
                .cbt-student-snapshot-table td {
                    vertical-align: top;
                }
                .cbt-student-snapshot-table {
                    width: 100%;
                    table-layout: fixed;
                }
                .cbt-student-snapshot-table--single {
                    border-collapse: separate;
                    border-spacing: 0;
                }
                .cbt-student-snapshot-table--single thead th {
                    background: #f8fbff;
                }
                .cbt-student-snapshot-col--user {
                    width: 15%;
                }
                .cbt-student-snapshot-col--availability {
                    width: 40%;
                }
                .cbt-student-snapshot-col--profile {
                    width: 32%;
                }
                .cbt-student-snapshot-col--login {
                    width: 32%;
                }
                .cbt-student-snapshot-col--actions {
                    width: 13%;
                }
                .cbt-student-snapshot-user-cell {
                    min-width: 0;
                }
                .cbt-student-snapshot-status-cell {
                    min-width: 0;
                }
                .cbt-student-snapshot-user-name {
                    margin-bottom: 8px;
                    color: #0f172a;
                    font-size: 15px;
                    font-weight: 700;
                }
                .cbt-student-snapshot-user-meta {
                    display: grid;
                    gap: 6px;
                    color: #475569;
                    font-size: 11px;
                    line-height: 1.5;
                }
                .cbt-student-snapshot-card {
                    display: grid;
                    gap: 8px;
                    padding: 10px 12px;
                    border: 1px solid #dbe6f1;
                    border-radius: 14px;
                    background: #f8fbff;
                }
                .cbt-student-snapshot-card-head {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-student-snapshot-mini-meta {
                    color: #475569;
                    font-size: 11px;
                    font-weight: 600;
                }
                .cbt-student-snapshot-compact-copy {
                    color: #35527d;
                    font-size: 12px;
                    line-height: 1.45;
                }
                .cbt-student-snapshot-preview-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                }
                .cbt-student-snapshot-preview-list--expanded {
                    margin-top: 0;
                }
                .cbt-student-snapshot-profile-top {
                    display: grid;
                    grid-template-columns: 56px minmax(0, 1fr);
                    gap: 10px;
                    align-items: start;
                }
                .cbt-student-snapshot-photo {
                    display: block;
                    width: 56px;
                    height: 56px;
                    border-radius: 16px;
                    object-fit: cover;
                    border: 1px solid #d7e3f1;
                    background: #ffffff;
                }
                .cbt-student-snapshot-preview-pill {
                    display: inline-flex;
                    align-items: center;
                    min-height: 26px;
                    padding: 0 10px;
                    border-radius: 999px;
                    background: #ffffff;
                    border: 1px solid #d7e3f1;
                    color: #0f4fa8;
                    font-size: 11px;
                    font-weight: 600;
                }
                .cbt-student-snapshot-preview-pill--muted {
                    color: #35527d;
                    background: #fdfefe;
                }
                .cbt-student-snapshot-preview-expand {
                    margin-top: 8px;
                }
                .cbt-student-snapshot-preview-expand summary {
                    display: inline-flex;
                    list-style: none;
                    cursor: pointer;
                }
                .cbt-student-snapshot-preview-expand summary::-webkit-details-marker {
                    display: none;
                }
                .cbt-student-snapshot-preview-expand-body {
                    display: grid;
                    gap: 8px;
                    margin-top: 8px;
                }
                .cbt-student-snapshot-preview-expand-label {
                    color: #35527d;
                    font-size: 12px;
                    font-weight: 600;
                }
                .cbt-student-snapshot-tech {
                    border-top: 1px dashed #d7e3f1;
                    padding-top: 8px;
                }
                .cbt-student-snapshot-tech summary {
                    cursor: pointer;
                    color: #35527d;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-student-snapshot-tech-body {
                    display: grid;
                    gap: 6px;
                    margin-top: 8px;
                    color: #475569;
                    font-size: 12px;
                }
                .cbt-student-snapshot-storage-key {
                    display: block;
                    overflow-wrap: anywhere;
                    word-break: break-word;
                    white-space: normal;
                    color: #1e3a5f;
                    font-size: 11px;
                    line-height: 1.45;
                }
                .cbt-student-snapshot-row-actions {
                    display: grid;
                    gap: 6px;
                    min-width: 0;
                }
                .cbt-student-snapshot-actions-cell {
                    min-width: 0;
                    overflow: hidden;
                }
                .cbt-student-snapshot-actions-cell .button {
                    display: block;
                    width: 100%;
                    max-width: 100%;
                    box-sizing: border-box;
                    text-align: center;
                    padding-left: 10px;
                    padding-right: 10px;
                    white-space: normal;
                }
                .cbt-exam-snapshot-student-pagination {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-snapshot-student-pagination-state {
                    color: #35527d;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-exam-builder-meta {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin: 18px 0 0;
                    flex-wrap: wrap;
                }
                .cbt-exam-builder-meta-pill {
                    display: inline-flex;
                    align-items: center;
                    min-height: 34px;
                    padding: 0 14px;
                    border-radius: 999px;
                    background: #eef5ff;
                    color: #0f4fa8;
                    font-size: 13px;
                    font-weight: 600;
                }
                .cbt-exam-flow-bar {
                    display: flex;
                    align-items: center;
                    justify-content: flex-start;
                    gap: 8px;
                    margin: 18px -24px 12px;
                    padding: 0 24px 14px;
                    border: 0;
                    border-bottom: 1px solid #dbe6f1;
                    border-radius: 0;
                    background: transparent;
                    flex-wrap: nowrap;
                    overflow-x: auto;
                    scrollbar-width: thin;
                }
                .cbt-exam-flow-tabs {
                    display: flex;
                    align-items: center;
                    margin: 0;
                    flex-wrap: nowrap;
                    min-width: 0;
                    flex: 1 1 0;
                }
                .cbt-exam-flow-tabs .button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    flex: 1 1 0;
                    min-height: 42px;
                    padding: 0 16px;
                    border: 1px solid #c7d2e0;
                    border-radius: 14px;
                    background: #ffffff;
                    color: #334155;
                    box-shadow: none;
                    font-weight: 600;
                }
                .cbt-exam-flow-tabs .button.cbt-active {
                    border-color: #2271b1;
                    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
                    color: #ffffff;
                    box-shadow: 0 8px 18px rgba(34, 113, 177, 0.18);
                }
                .cbt-exam-flow-arrow {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 24px;
                    color: #2271b1;
                    font-size: 18px;
                    font-weight: 700;
                    line-height: 1;
                }
                .cbt-exam-builder-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin: 0;
                    flex-wrap: wrap;
                }
                .cbt-exam-flow-actions {
                    flex: 1 1 0;
                    flex-wrap: nowrap;
                }
                .cbt-exam-flow-actions .cbt-exam-submit-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    flex: 1 1 0;
                    min-height: 42px;
                    padding: 0 16px;
                    border-width: 1px;
                    border-style: solid;
                    border-radius: 14px;
                    font-weight: 600;
                    line-height: 1;
                    min-width: 0;
                    box-shadow: none;
                }
                .cbt-exam-flow-actions .cbt-exam-submit-btn.button-primary {
                    border-color: #2271b1;
                    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
                    color: #ffffff;
                    box-shadow: 0 8px 18px rgba(34, 113, 177, 0.18);
                }
                .cbt-exam-flow-actions .cbt-exam-submit-btn.button-primary:hover,
                .cbt-exam-flow-actions .cbt-exam-submit-btn.button-primary:focus {
                    border-color: #135e96;
                    background: linear-gradient(135deg, #1d67a2 0%, #0f568a 100%);
                    color: #ffffff;
                }
                .cbt-exam-flow-actions .cbt-exam-submit-btn[disabled],
                .cbt-exam-flow-actions .cbt-exam-submit-btn[aria-disabled="true"] {
                    border-color: #d0d7de;
                    background: #f8fafc;
                    color: #9aa5b1;
                    box-shadow: none;
                }
                .cbt-exam-flow-help {
                    margin: 0;
                    color: #64748b;
                    line-height: 1.55;
                }
                #cbt-exam-details-panel .form-table {
                    width: 100%;
                    margin: 0;
                    border-collapse: separate;
                    border-spacing: 0 16px;
                }
                #cbt-exam-details-panel .form-table tr {
                    display: grid;
                    grid-template-columns: minmax(140px, 188px) minmax(0, 1fr);
                    gap: 10px 12px;
                    align-items: flex-start;
                }
                #cbt-exam-details-panel .form-table th,
                #cbt-exam-details-panel .form-table td {
                    padding: 0;
                }
                #cbt-exam-details-panel .form-table th {
                    display: flex;
                    align-items: flex-start;
                    color: #0f172a;
                    font-size: 14px;
                    font-weight: 700;
                    line-height: 1.5;
                    min-height: 0;
                    min-width: 0;
                    padding-top: 12px;
                }
                #cbt-exam-details-panel .form-table th label {
                    display: block;
                    max-width: 100%;
                    color: inherit;
                    font-weight: inherit;
                    overflow-wrap: anywhere;
                }
                #cbt-exam-details-panel .form-table td {
                    display: grid;
                    gap: 8px;
                }
                #cbt-exam-details-panel .form-table td > input[type="text"],
                #cbt-exam-details-panel .form-table td > input[type="number"],
                #cbt-exam-details-panel .form-table td > input[type="datetime-local"],
                #cbt-exam-details-panel .form-table td > select,
                #cbt-exam-details-panel .form-table td > textarea,
                .cbt-exam-question-field input,
                .cbt-exam-question-field select,
                .cbt-exam-list-toolbar input[type="search"],
                .cbt-exam-list-toolbar select {
                    width: 100%;
                    max-width: none;
                    min-height: 48px;
                    margin: 0;
                    border: 1px solid #c7d2e0;
                    border-radius: 14px;
                    background-color: #fbfdff;
                    color: #0f172a;
                    padding: 0 14px;
                    transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
                    box-sizing: border-box;
                }
                #cbt-exam-details-panel .form-table td > input[type="text"]:focus,
                #cbt-exam-details-panel .form-table td > input[type="number"]:focus,
                #cbt-exam-details-panel .form-table td > input[type="datetime-local"]:focus,
                #cbt-exam-details-panel .form-table td > select:focus,
                #cbt-exam-details-panel .form-table td > textarea:focus,
                .cbt-exam-question-field input:focus,
                .cbt-exam-question-field select:focus,
                .cbt-exam-list-toolbar input[type="search"]:focus,
                .cbt-exam-list-toolbar select:focus {
                    border-color: #2271b1;
                    background: #ffffff;
                    box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
                    outline: none;
                }
                #cbt-exam-details-panel .form-table td > textarea {
                    min-height: 132px;
                    padding: 14px;
                    resize: vertical;
                }
                #cbt-exam-details-panel .form-table td > select,
                .cbt-exam-question-field select,
                .cbt-exam-list-toolbar select {
                    appearance: none;
                    background-image:
                        linear-gradient(45deg, transparent 50%, #64748b 50%),
                        linear-gradient(135deg, #64748b 50%, transparent 50%);
                    background-position:
                        calc(100% - 19px) calc(50% - 4px),
                        calc(100% - 13px) calc(50% - 4px);
                    background-size: 6px 6px, 6px 6px;
                    background-repeat: no-repeat;
                    padding-right: 40px;
                }
                #cbt-exam-details-panel .form-table .description,
                .cbt-exam-list-description {
                    margin: 0;
                    color: #64748b;
                    font-size: 13px;
                    line-height: 1.55;
                }
                #cbt-exam-details-panel .form-table .description {
                    padding-left: 2px;
                }
                #cbt-exam-details-panel .form-table tr.cbt-exam-detail-row--stacked {
                    align-items: flex-start;
                }
                #cbt-exam-details-panel .form-table tr.cbt-exam-detail-row--stacked th {
                    align-items: flex-start;
                    min-height: 0;
                    padding-top: 12px;
                }
                #cbt-exam-details-panel .form-table tr.cbt-exam-detail-row--toggle {
                    align-items: flex-start;
                }
                #cbt-exam-details-panel .form-table tr.cbt-exam-detail-row--toggle th {
                    align-items: flex-start;
                    min-height: 0;
                    padding-top: 4px;
                }
                #cbt-exam-details-panel .form-table tr.cbt-exam-detail-row--toggle td {
                    min-height: 0;
                    align-items: flex-start;
                }
                .cbt-exam-inline-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    min-height: 48px;
                    width: 100%;
                    padding: 0 14px;
                    border: 1px solid #c7d2e0;
                    border-radius: 14px;
                    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                    box-sizing: border-box;
                    color: #0f172a;
                    font-weight: 500;
                }
                .cbt-exam-inline-toggle input[type="checkbox"] {
                    margin: 0;
                }
                #cbt-exam-details-panel .form-table tr.cbt-exam-detail-row--textarea {
                    align-items: flex-start;
                }
                #cbt-exam-details-panel .form-table tr.cbt-exam-detail-row--textarea th {
                    align-items: flex-start;
                    min-height: 0;
                    padding-top: 4px;
                }
                #cbt-kelas-checklist {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 10px;
                    min-width: 0 !important;
                    max-height: 240px !important;
                    overflow: auto;
                    border: 1px solid #d9e2ec !important;
                    border-radius: 16px;
                    padding: 14px !important;
                    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%) !important;
                }
                #cbt-kelas-checklist label {
                    display: flex !important;
                    align-items: center;
                    gap: 10px;
                    margin: 0 !important;
                    padding: 12px 14px;
                    border: 1px solid #e2e8f0;
                    border-radius: 14px;
                    background: #ffffff;
                }
                #cbt-kelas-checklist input[type="checkbox"] {
                    margin: 0;
                }
                #cbt-kelas-checklist em {
                    color: #64748b;
                }
                .cbt-exam-detail-validation-notice {
                    margin: 12px 0 0;
                }
                .cbt-exam-detail-validation-notice[hidden] {
                    display: none !important;
                }
                .cbt-exam-panel-nav {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px 16px;
                    margin-top: 18px;
                    padding: 16px 18px;
                    border: 1px solid #d9e2ec;
                    border-radius: 16px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
                    flex-wrap: wrap;
                }
                .cbt-exam-panel-nav-note {
                    margin: 0;
                    color: #475569;
                }
                .cbt-exam-panel-nav-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-left: auto;
                    flex-wrap: wrap;
                }
                .cbt-exam-save-progress-overlay {
                    position: fixed;
                    inset: 0;
                    z-index: 100100;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    background: rgba(15, 23, 42, 0.36);
                    backdrop-filter: blur(2px);
                }
                .cbt-exam-save-progress-overlay[hidden] {
                    display: none !important;
                }
                .cbt-exam-save-progress-card {
                    width: min(520px, 100%);
                    padding: 22px 22px 18px;
                    border-radius: 18px;
                    background: #ffffff;
                    box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
                }
                .cbt-exam-save-progress-card h3 {
                    margin: 0 0 10px;
                    font-size: 22px;
                }
                .cbt-exam-save-progress-card p {
                    margin: 0;
                }
                .cbt-exam-save-progress-meta {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    margin: 14px 0 10px;
                    color: #1d2327;
                    font-weight: 500;
                    flex-wrap: wrap;
                }
                .cbt-exam-save-progress-bar {
                    height: 12px;
                    border-radius: 999px;
                    background: #e5edf5;
                    overflow: hidden;
                }
                .cbt-exam-save-progress-fill {
                    width: 0;
                    height: 100%;
                    border-radius: inherit;
                    background: linear-gradient(90deg, #2271b1 0%, #135e96 100%);
                    transition: width 0.25s ease;
                }
                .cbt-exam-save-progress-percent {
                    margin-top: 10px;
                    color: #0a4b78;
                    font-size: 18px;
                    font-weight: 700;
                }
                .cbt-exam-save-progress-help {
                    margin-top: 8px;
                }
                .cbt-exam-clean-progress-fill {
                    background: linear-gradient(90deg, #166534 0%, #15803d 100%);
                }
                .cbt-exam-question-shell {
                    margin-top: 12px;
                    padding: 20px;
                    border: 1px solid #d9e2ec;
                    border-radius: 18px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
                }
                .cbt-exam-question-catalog {
                    position: relative;
                }
                .cbt-exam-question-catalog.is-loading {
                    opacity: 0.58;
                    pointer-events: none;
                    transition: opacity 160ms ease;
                }
                .cbt-exam-question-header {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 16px;
                    margin-bottom: 16px;
                    flex-wrap: wrap;
                }
                .cbt-exam-question-header h3 {
                    margin: 0 0 6px;
                    font-size: 22px;
                    line-height: 1.2;
                }
                .cbt-exam-question-description {
                    margin: 0;
                    max-width: 920px;
                }
                .cbt-exam-builder-summary {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                    gap: 12px;
                    margin: 0 0 16px;
                }
                .cbt-exam-builder-summary-item {
                    padding: 14px 16px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.94);
                }
                .cbt-exam-builder-summary-item span {
                    display: block;
                    margin-bottom: 6px;
                    color: #64748b;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-exam-builder-summary-item strong {
                    display: block;
                    color: #0f172a;
                    font-size: 14px;
                    line-height: 1.5;
                }
                .cbt-exam-builder-summary-item small {
                    display: block;
                    margin-top: 4px;
                    color: #64748b;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-exam-lineage-summary {
                    display: flex;
                    align-items: stretch;
                    gap: 10px;
                    margin: -4px 0 16px;
                    flex-wrap: wrap;
                }
                .cbt-exam-lineage-summary-card {
                    min-width: 140px;
                    padding: 12px 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 14px;
                    background: #ffffff;
                    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
                }
                .cbt-exam-lineage-summary-card span {
                    display: block;
                    margin-bottom: 4px;
                    color: #64748b;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .cbt-exam-lineage-summary-card strong {
                    display: block;
                    color: #0f172a;
                    font-size: 14px;
                    line-height: 1.4;
                }
                .cbt-exam-lineage-summary-card--bank strong,
                .cbt-exam-lineage-summary-card--topology-bank strong {
                    color: #0f4fa8;
                }
                .cbt-exam-lineage-summary-card--legacy strong,
                .cbt-exam-lineage-summary-card--topology-legacy strong {
                    color: #92400e;
                }
                .cbt-exam-lineage-summary-card--linked strong {
                    color: #7c3aed;
                }
                .cbt-exam-lineage-summary-card--topology-mixed strong {
                    color: #be185d;
                }
                .cbt-exam-question-lazy-placeholder {
                    margin: 0 0 12px;
                    padding: 18px;
                    border: 1px dashed #9fb2c7;
                    border-radius: 14px;
                    background: #f8fbff;
                }
                .cbt-exam-question-lazy-placeholder p {
                    margin-top: 0;
                }
                .cbt-exam-question-lazy-placeholder .cbt-exam-question-filter-actions {
                    margin-top: 12px;
                }
                .cbt-exam-question-mode-bar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 18px;
                    margin: 0 0 6px;
                    padding: 16px 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 18px;
                    background:
                        radial-gradient(circle at top right, rgba(34, 113, 177, 0.08), transparent 38%),
                        linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: 0 14px 28px rgba(15, 23, 42, 0.04);
                    flex-wrap: wrap;
                }
                .cbt-exam-question-mode-copy {
                    display: grid;
                    gap: 8px;
                    flex: 1 1 420px;
                    min-width: 260px;
                }
                .cbt-exam-question-mode-kicker {
                    display: inline-flex;
                    align-items: center;
                    width: fit-content;
                    min-height: 24px;
                    padding: 0 10px;
                    border-radius: 999px;
                    background: #e8f1ff;
                    color: #0f4fa8;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .cbt-exam-question-mode-head {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-question-mode-label {
                    display: inline-flex;
                    align-items: center;
                    min-height: 38px;
                    padding: 0 16px;
                    border-radius: 999px;
                    background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
                    border: 1px solid #bfdbfe;
                    color: #0f4fa8;
                    font-weight: 700;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
                }
                .cbt-exam-question-mode-note {
                    margin: 0;
                    color: #546476;
                    font-size: 13px;
                    line-height: 1.65;
                    max-width: 780px;
                }
                .cbt-exam-question-mode-actions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-exam-question-mode-actions .cbt-exam-question-nav-link {
                    min-height: 42px;
                    padding: 0 16px;
                    border-radius: 14px;
                    font-weight: 700;
                    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
                }
                .cbt-exam-question-nav-link--primary {
                    border-color: #2271b1;
                    background: linear-gradient(135deg, #2271b1 0%, #1d5f92 100%);
                    color: #ffffff;
                }
                .cbt-exam-question-nav-link--primary:hover,
                .cbt-exam-question-nav-link--primary:focus {
                    border-color: #1b5f90;
                    background: linear-gradient(135deg, #1f6aa6 0%, #184f79 100%);
                    color: #ffffff;
                }
                .cbt-exam-question-nav-link--secondary {
                    border-color: #c9d7e6;
                    background: #ffffff;
                    color: #1e3a5f;
                }
                .cbt-exam-question-nav-link--secondary:hover,
                .cbt-exam-question-nav-link--secondary:focus {
                    border-color: #2271b1;
                    color: #0f4fa8;
                }
                .cbt-exam-question-filter-panel {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) auto;
                    grid-template-areas:
                        "lineage actions"
                        "filters filters";
                    align-items: start;
                    gap: 10px 14px;
                    padding: 14px 16px;
                    margin: 0 0 10px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: #ffffff;
                }
                .cbt-exam-question-lineage-bar {
                    grid-area: lineage;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                    min-width: 0;
                }
                .cbt-exam-question-lineage-pill {
                    display: inline-flex;
                    align-items: center;
                    min-height: 28px;
                    padding: 0 11px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 700;
                }
                .cbt-exam-question-lineage-pill--bank {
                    background: #e8f1ff;
                    color: #0f4fa8;
                }
                .cbt-exam-question-lineage-pill--legacy {
                    background: #fff7ed;
                    color: #b45309;
                }
                .cbt-exam-question-lineage-pill--mixed {
                    background: #fdf2f8;
                    color: #be185d;
                }
                .cbt-exam-question-lineage-pill--empty {
                    background: #f1f5f9;
                    color: #475569;
                }
                .cbt-exam-question-filter-grid {
                    grid-area: filters;
                    display: grid;
                    grid-template-columns: minmax(0, 1.8fr) repeat(3, minmax(0, 1fr));
                    gap: 10px;
                    width: 100%;
                    min-width: 0;
                }
                .cbt-exam-question-field {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                    min-width: 0;
                }
                .cbt-exam-question-filter-grid input,
                .cbt-exam-question-filter-grid select {
                    width: 100%;
                    max-width: 100%;
                    box-sizing: border-box;
                    min-width: 0;
                }
                .cbt-exam-question-field-search {
                    grid-column: auto;
                }
                .cbt-exam-question-field label {
                    font-weight: 600;
                    margin: 0;
                    color: #0f172a;
                    font-size: 12px;
                    line-height: 1.2;
                }
                .cbt-exam-question-field .description {
                    display: none;
                }
                .cbt-exam-question-filter-actions {
                    grid-area: actions;
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 8px;
                    min-width: fit-content;
                }
                .cbt-exam-question-filter-actions .cbt-exam-question-filter-reset {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 42px;
                    padding: 0 16px;
                    border-radius: 12px;
                    border: 1px solid #c9d8ea;
                    background: #ffffff;
                    color: #0b4f7d;
                    font-weight: 700;
                    text-decoration: none;
                    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
                    transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease, background-color 140ms ease;
                }
                .cbt-exam-question-filter-actions .cbt-exam-question-filter-reset:hover,
                .cbt-exam-question-filter-actions .cbt-exam-question-filter-reset:focus {
                    transform: translateY(-1px);
                    border-color: #9bb8d6;
                    background: #f8fbff;
                    color: #0a3f67;
                    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.08);
                }
                .cbt-exam-question-status-bar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    margin: 0 0 12px;
                    flex-wrap: wrap;
                }
                .cbt-exam-question-bulk-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-exam-question-bulk-help {
                    color: #64748b;
                    font-size: 12px;
                    line-height: 1.5;
                }
                #cbt-exam-selected-count {
                    display: inline-flex;
                    align-items: center;
                    min-height: 38px;
                    padding: 0 14px;
                    border-radius: 999px;
                    background: #f0f6fc;
                    color: #0a4b78;
                    font-weight: 700;
                }
                .cbt-exam-question-pagination-wrap {
                    margin-bottom: 10px;
                }
                .cbt-exam-question-table-wrap,
                .cbt-exam-list-table-wrap {
                    overflow: auto;
                    border: 1px solid #d9e2ec;
                    border-radius: 18px;
                    background: #ffffff;
                }
                .cbt-exam-question-table-wrap {
                    max-height: 420px;
                }
                .cbt-exam-question-table-wrap table,
                .cbt-exam-list-table {
                    margin: 0;
                }
                .cbt-exam-question-table-wrap table thead th,
                .cbt-exam-list-table thead th {
                    position: sticky;
                    top: 0;
                    z-index: 1;
                    background: #f8fbff;
                }
                #cbt-exam-quickview-body :where(table) {
                    margin: 0.45em 0;
                    border-collapse: collapse;
                    border-spacing: 0;
                    background: #fff;
                    border: 1px solid #d6deea;
                }
                #cbt-exam-quickview-body :where(th, td) {
                    border: 1px solid #d6deea;
                    padding: 8px 10px;
                    vertical-align: top;
                }
                #cbt-exam-quickview-body :where(th) {
                    background: #f8fbff;
                    color: #0f172a;
                    font-weight: 700;
                }
                .cbt-exam-question-actions {
                    display: grid;
                    gap: 5px;
                    justify-items: stretch;
                }
                .cbt-exam-question-action {
                    appearance: none;
                    -webkit-appearance: none;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 28px;
                    padding: 0 8px;
                    border: 1px solid #d7e4f5;
                    border-radius: 999px;
                    background: #ffffff;
                    color: #1e3a5f;
                    font-size: 10px;
                    font-weight: 700;
                    line-height: 1;
                    text-decoration: none;
                    cursor: pointer;
                    width: 100%;
                    box-sizing: border-box;
                    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.05);
                    transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease, background-color 120ms ease, color 120ms ease;
                }
                .cbt-exam-question-action:hover,
                .cbt-exam-question-action:focus {
                    border-color: #2271b1;
                    color: #0f4fa8;
                    background: #f8fbff;
                    box-shadow: 0 10px 20px rgba(34, 113, 177, 0.12);
                    outline: none;
                    transform: translateY(-1px);
                }
                .cbt-exam-question-action--edit {
                    border-color: #bfdbfe;
                    background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
                    color: #1d4ed8;
                }
                .cbt-exam-question-action--preview {
                    border-color: #dbe4f0;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    color: #334155;
                }
                .cbt-exam-question-action__label {
                    display: inline-block;
                }
                .cbt-exam-question-source-cell {
                    display: grid;
                    gap: 6px;
                }
                .cbt-exam-question-source-cell--stacked {
                    gap: 5px;
                }
                .cbt-exam-question-source-name {
                    display: block;
                    color: #0f172a;
                    font-weight: 600;
                    line-height: 1.3;
                    font-size: 11px;
                }
                .cbt-exam-question-source-type {
                    display: inline-flex;
                    align-items: center;
                    width: fit-content;
                    min-height: 18px;
                    padding: 0 6px;
                    border-radius: 999px;
                    background: #f1f5f9;
                    color: #475569;
                    font-size: 9px;
                    font-weight: 700;
                    letter-spacing: 0.03em;
                    text-transform: uppercase;
                }
                .cbt-exam-question-source-meta {
                    display: grid;
                    gap: 4px;
                }
                .cbt-exam-question-source-meta small {
                    display: block;
                    color: #64748b;
                    line-height: 1.45;
                }
                .cbt-exam-question-lineage-badge {
                    display: inline-flex;
                    align-items: center;
                    min-height: 20px;
                    padding: 0 7px;
                    border-radius: 999px;
                    font-size: 9px;
                    font-weight: 700;
                    width: fit-content;
                }
                .cbt-exam-question-lineage-badge--bank {
                    background: #dbeafe;
                    color: #1d4ed8;
                }
                .cbt-exam-question-lineage-badge--legacy {
                    background: #ffedd5;
                    color: #b45309;
                }
                .cbt-exam-question-lineage-badge--linked {
                    background: #ede9fe;
                    color: #6d28d9;
                }
                .cbt-exam-question-workspace {
                    display: grid;
                    grid-template-columns: minmax(0, 1.72fr) minmax(320px, 420px);
                    gap: 18px;
                    align-items: start;
                }
                .cbt-exam-question-header {
                    display: grid;
                    gap: 10px;
                    margin-bottom: 16px;
                }
                .cbt-exam-question-guide {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-exam-question-guide span {
                    display: inline-flex;
                    align-items: center;
                    min-height: 32px;
                    padding: 0 12px;
                    border: 1px solid #dbe6f1;
                    border-radius: 999px;
                    background: rgba(255, 255, 255, 0.96);
                    color: #475569;
                    font-size: 12px;
                    line-height: 1.45;
                }
                .cbt-exam-question-guide strong {
                    color: #0f172a;
                }
                .cbt-exam-question-table {
                    width: 100%;
                    min-width: 0;
                    table-layout: fixed;
                }
                .cbt-exam-question-table .cbt-exam-question-checkbox {
                    position: absolute;
                    opacity: 0;
                    pointer-events: none;
                }
                .cbt-exam-question-table th,
                .cbt-exam-question-table td {
                    vertical-align: top;
                    overflow-wrap: anywhere;
                    word-break: break-word;
                }
                .cbt-exam-question-table thead th {
                    white-space: nowrap;
                    overflow-wrap: normal;
                    word-break: normal;
                }
                .cbt-exam-question-row.is-selected {
                    background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
                }
                .cbt-exam-question-row.is-pending-remove {
                    opacity: 0.72;
                }
                .cbt-exam-question-row td:first-child {
                    min-width: 0;
                }
                .cbt-exam-question-identity-cell {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 5px;
                }
                .cbt-exam-question-pick-btn {
                    width: 100%;
                    min-width: 0;
                    min-height: 30px;
                    padding: 0 8px;
                    border-radius: 999px;
                    font-weight: 700;
                    font-size: 11px;
                }
                .cbt-exam-question-pick-btn.is-selected,
                .cbt-exam-question-pick-btn[disabled] {
                    border-color: #dbe6f1;
                    background: #f8fafc;
                    color: #7b8794;
                    box-shadow: none;
                }
                .cbt-exam-question-identity-copy {
                    display: grid;
                    gap: 4px;
                    min-width: 0;
                }
                .cbt-exam-question-identity-copy strong {
                    display: block;
                    color: #0f172a;
                    line-height: 1.3;
                    font-size: 11px;
                }
                .cbt-exam-question-identity-id {
                    display: inline-flex;
                    align-items: center;
                    width: fit-content;
                    min-height: 18px;
                    padding: 0 6px;
                    border-radius: 999px;
                    background: #eef2f7;
                    color: #475569;
                    font-size: 9px;
                    font-weight: 700;
                    letter-spacing: 0.03em;
                }
                .cbt-exam-question-table-wrap {
                    max-height: 560px;
                }
                .cbt-exam-question-sidebar {
                    position: sticky;
                    top: 22px;
                    display: grid;
                    gap: 14px;
                    padding: 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 20px;
                    background:
                        radial-gradient(circle at top right, rgba(34, 113, 177, 0.08), transparent 34%),
                        linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
                }
                .cbt-exam-selected-sidebar-header {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                }
                .cbt-exam-selected-sidebar-copy {
                    display: grid;
                    gap: 6px;
                }
                .cbt-exam-selected-sidebar-copy h4 {
                    margin: 0;
                    font-size: 18px;
                    line-height: 1.25;
                }
                .cbt-exam-selected-sidebar-copy p {
                    margin: 0;
                    color: #64748b;
                    line-height: 1.55;
                }
                .cbt-exam-selected-sidebar-kicker {
                    display: inline-flex;
                    align-items: center;
                    width: fit-content;
                    min-height: 24px;
                    padding: 0 10px;
                    border-radius: 999px;
                    background: #e8f1ff;
                    color: #0f4fa8;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .cbt-exam-selected-sidebar-dirty {
                    display: inline-flex;
                    align-items: center;
                    min-height: 32px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: #eef2f7;
                    color: #475569;
                    font-size: 12px;
                    font-weight: 700;
                    white-space: nowrap;
                }
                .cbt-exam-selected-sidebar-dirty.is-dirty {
                    background: #fff7ed;
                    color: #b45309;
                }
                .cbt-exam-selected-sidebar-summary {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 10px;
                }
                .cbt-exam-selected-sidebar-chip {
                    padding: 12px 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.96);
                }
                .cbt-exam-selected-sidebar-chip span {
                    display: block;
                    margin-bottom: 4px;
                    color: #64748b;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-exam-selected-sidebar-chip strong {
                    display: block;
                    color: #0f172a;
                    font-size: 18px;
                    line-height: 1.1;
                }
                .cbt-exam-selected-sidebar-chip--added strong {
                    color: #1d4ed8;
                }
                .cbt-exam-selected-sidebar-chip--removed strong {
                    color: #b45309;
                }
                .cbt-exam-selected-sidebar-help {
                    margin: 0;
                    padding: 12px 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.88);
                    color: #475569;
                    line-height: 1.6;
                }
                .cbt-exam-selected-sidebar-toolbar {
                    display: grid;
                    gap: 8px;
                }
                .cbt-exam-selected-sidebar-search {
                    width: 100%;
                    min-height: 40px;
                    padding: 0 14px;
                    border: 1px solid #c9d8ea;
                    border-radius: 14px;
                    background: #ffffff;
                    box-sizing: border-box;
                }
                .cbt-exam-selected-sidebar-search:focus {
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px #2271b1;
                    outline: none;
                }
                .cbt-exam-selected-sidebar-filter-count {
                    display: inline-flex;
                    align-items: center;
                    width: fit-content;
                    min-height: 22px;
                    padding: 0 9px;
                    border-radius: 999px;
                    background: #eef2f7;
                    color: #475569;
                    font-size: 11px;
                    font-weight: 700;
                }
                .cbt-exam-selected-sidebar-list {
                    display: grid;
                    gap: 10px;
                    max-height: 560px;
                    overflow: auto;
                    padding-right: 4px;
                }
                .cbt-exam-selected-sidebar-empty {
                    display: grid;
                    gap: 6px;
                    padding: 18px;
                    border: 1px dashed #bcccdc;
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.84);
                    color: #64748b;
                }
                .cbt-exam-selected-item {
                    display: grid;
                    grid-template-columns: auto minmax(0, 1fr) auto;
                    gap: 12px;
                    padding: 14px;
                    border: 1px solid #dbe6f1;
                    border-radius: 18px;
                    background: rgba(255, 255, 255, 0.98);
                    transition: border-color 140ms ease, box-shadow 140ms ease, opacity 140ms ease;
                }
                .cbt-exam-selected-item--new {
                    border-color: #bfdbfe;
                    box-shadow: inset 0 1px 0 rgba(219, 234, 254, 0.7);
                }
                .cbt-exam-selected-item--removed {
                    opacity: 0.72;
                    background: linear-gradient(180deg, #fffaf5 0%, #fff7ed 100%);
                    border-color: #fed7aa;
                }
                .cbt-exam-selected-item-order {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 34px;
                    height: 34px;
                    border-radius: 12px;
                    background: #e8f1ff;
                    color: #0f4fa8;
                    font-weight: 700;
                }
                .cbt-exam-selected-item-body {
                    display: grid;
                    gap: 8px;
                    min-width: 0;
                }
                .cbt-exam-selected-item-topline {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-exam-selected-item-type {
                    color: #0f172a;
                    font-size: 12px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                }
                .cbt-exam-selected-item-status {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 0 10px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 700;
                }
                .cbt-exam-selected-item-status--existing {
                    background: #eef2f7;
                    color: #475569;
                }
                .cbt-exam-selected-item-status--new {
                    background: #dbeafe;
                    color: #1d4ed8;
                }
                .cbt-exam-selected-item-status--removed {
                    background: #ffedd5;
                    color: #b45309;
                }
                .cbt-exam-selected-item-preview {
                    display: block;
                    color: #0f172a;
                    line-height: 1.55;
                    overflow-wrap: anywhere;
                }
                .cbt-exam-selected-item-meta {
                    display: grid;
                    gap: 4px;
                }
                .cbt-exam-selected-item-meta small {
                    color: #64748b;
                    line-height: 1.45;
                }
                .cbt-exam-selected-item-actions {
                    display: grid;
                    gap: 8px;
                    align-items: flex-start;
                }
                .cbt-exam-selected-item-action {
                    min-height: 34px;
                    padding: 0 14px;
                    border-radius: 999px;
                    font-weight: 700;
                    width: 100%;
                    justify-content: center;
                }
                .cbt-exam-selected-item-action--edit {
                    border-color: #bfdbfe;
                    background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
                    color: #1d4ed8;
                }
                .cbt-exam-selected-item-action--preview {
                    border-color: #dbe6f1;
                    background: #ffffff;
                    color: #1e3a5f;
                }
                .cbt-exam-selection-confirm-modal[hidden] {
                    display: none !important;
                }
                .cbt-exam-selection-confirm-modal {
                    position: fixed;
                    inset: 0;
                    z-index: 100090;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }
                .cbt-exam-selection-confirm-backdrop {
                    position: absolute;
                    inset: 0;
                    background: rgba(15, 23, 42, 0.48);
                }
                .cbt-exam-selection-confirm-card {
                    position: relative;
                    width: min(480px, 100%);
                    padding: 22px;
                    border-radius: 20px;
                    background: #ffffff;
                    box-shadow: 0 22px 44px rgba(15, 23, 42, 0.18);
                }
                .cbt-exam-selection-confirm-card h4 {
                    margin: 0 0 10px;
                    font-size: 22px;
                    line-height: 1.2;
                }
                .cbt-exam-selection-confirm-card p {
                    margin: 0;
                    color: #475569;
                    line-height: 1.65;
                }
                .cbt-exam-selection-confirm-help {
                    margin-top: 10px !important;
                    font-size: 12px;
                }
                .cbt-exam-selection-confirm-actions {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 10px;
                    margin-top: 18px;
                    flex-wrap: wrap;
                }
                .cbt-exam-list-description {
                    margin: 0 0 16px;
                }
                .cbt-exam-list-toolbar {
                    display: grid;
                    gap: 14px;
                    margin: 0 0 16px;
                    padding: 16px 18px;
                    border: 1px solid #dbe6f1;
                    border-radius: 16px;
                    background: linear-gradient(180deg, #fbfdff 0%, #f5f9ff 100%);
                    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
                }
                .cbt-exam-list-toolbar-grid {
                    display: grid;
                    grid-template-columns: minmax(240px, 1.5fr) repeat(4, minmax(150px, 1fr)) auto;
                    gap: 12px;
                    align-items: end;
                }
                .cbt-exam-list-toolbar-field {
                    display: grid;
                    gap: 6px;
                    min-width: 0;
                }
                .cbt-exam-list-toolbar-field--search {
                    min-width: min(100%, 280px);
                }
                #cbt-exam-snapshot-filter-form .cbt-exam-list-toolbar-grid {
                    grid-template-columns: minmax(0, 1fr) auto;
                }
                #cbt-exam-snapshot-filter-form .cbt-exam-list-toolbar-field--search {
                    min-width: 0;
                }
                #cbt-exam-snapshot-filter-form .cbt-exam-snapshot-picker {
                    width: 100%;
                }
                #cbt-exam-snapshot-filter-form .cbt-exam-list-toolbar-actions {
                    align-self: end;
                }
                .cbt-exam-snapshot-picker {
                    border: 1px solid #c7d2e0;
                    border-radius: 14px;
                    background: #fbfdff;
                    overflow: hidden;
                }
                .cbt-exam-snapshot-picker[open] {
                    border-color: #2271b1;
                    background: #ffffff;
                    box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
                }
                .cbt-exam-snapshot-picker > summary {
                    list-style: none;
                    cursor: pointer;
                    min-height: 48px;
                    padding: 10px 14px;
                }
                .cbt-exam-snapshot-picker > summary::-webkit-details-marker {
                    display: none;
                }
                .cbt-exam-snapshot-picker-summary {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                }
                .cbt-exam-snapshot-picker-copy {
                    display: grid;
                    gap: 2px;
                    min-width: 0;
                }
                .cbt-exam-snapshot-picker-copy strong {
                    color: #0f172a;
                    font-size: 13px;
                }
                .cbt-exam-snapshot-picker-copy span {
                    color: #64748b;
                    font-size: 11px;
                    line-height: 1.45;
                }
                .cbt-exam-snapshot-picker-caret {
                    flex: 0 0 auto;
                    width: 10px;
                    height: 10px;
                    border-right: 2px solid #64748b;
                    border-bottom: 2px solid #64748b;
                    transform: rotate(45deg);
                    transition: transform 140ms ease;
                }
                .cbt-exam-snapshot-picker[open] .cbt-exam-snapshot-picker-caret {
                    transform: rotate(-135deg);
                }
                .cbt-exam-snapshot-picker-menu {
                    display: grid;
                    gap: 8px;
                    max-height: 300px;
                    padding: 0 14px 14px;
                    overflow: auto;
                    border-top: 1px solid #e2e8f0;
                }
                .cbt-exam-snapshot-picker-option {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    padding: 10px 12px;
                    border: 1px solid #d7e3f0;
                    border-radius: 14px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    cursor: pointer;
                    transition: border-color 140ms ease, box-shadow 140ms ease, transform 140ms ease;
                }
                .cbt-exam-snapshot-picker-option:hover,
                .cbt-exam-snapshot-picker-option:focus-within {
                    border-color: #93c5fd;
                    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.08);
                    transform: translateY(-1px);
                }
                .cbt-exam-snapshot-picker-option input[type="checkbox"] {
                    margin-top: 2px;
                }
                .cbt-exam-snapshot-exam-copy {
                    display: grid;
                    gap: 3px;
                    color: #475569;
                    line-height: 1.4;
                }
                .cbt-exam-snapshot-exam-copy strong {
                    color: #0f172a;
                    font-size: 13px;
                }
                .cbt-exam-snapshot-picker-empty {
                    padding: 14px 0 4px;
                    color: #64748b;
                    font-size: 12px;
                    line-height: 1.6;
                }
                .cbt-exam-list-toolbar-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    justify-content: flex-end;
                    flex-wrap: wrap;
                }
                .cbt-exam-list-toolbar-actions .button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 48px;
                    padding: 0 20px;
                    border-radius: 14px;
                    border: 1px solid #1d5f99;
                    background: linear-gradient(180deg, #2f7ab9 0%, #1f68a6 100%);
                    color: #ffffff;
                    font-weight: 600;
                    text-decoration: none;
                    box-shadow: 0 10px 22px rgba(34, 113, 177, 0.18);
                    transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease, background-color 140ms ease;
                    white-space: nowrap;
                }
                .cbt-exam-list-toolbar-actions .button:hover,
                .cbt-exam-list-toolbar-actions .button:focus {
                    transform: translateY(-1px);
                    border-color: #174d7c;
                    background: linear-gradient(180deg, #337fbe 0%, #1c629c 100%);
                    color: #ffffff;
                    box-shadow: 0 14px 28px rgba(34, 113, 177, 0.2);
                }
                .cbt-exam-list-toolbar-actions .cbt-exam-list-reset {
                    border-color: #d1dbe8;
                    background: #ffffff;
                    color: #33506b;
                    box-shadow: none;
                }
                .cbt-exam-list-toolbar-actions .cbt-exam-list-reset:hover,
                .cbt-exam-list-toolbar-actions .cbt-exam-list-reset:focus {
                    border-color: #a8bfd9;
                    background: #f8fbff;
                    color: #113b68;
                    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
                }
                .cbt-exam-list-toolbar label {
                    display: block;
                    margin: 0 0 6px;
                    color: #0f172a;
                    font-weight: 600;
                }
                .cbt-exam-list-active-filters {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                    margin: -4px 0 16px;
                }
                .cbt-exam-list-active-summary,
                .cbt-exam-list-active-chip {
                    display: inline-flex;
                    align-items: center;
                    min-height: 32px;
                    padding: 0 12px;
                    border-radius: 999px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .cbt-exam-list-active-summary {
                    background: #e8f1ff;
                    color: #0f4fa8;
                }
                .cbt-exam-list-active-chip {
                    gap: 6px;
                    border: 1px solid #dbe6f1;
                    background: #ffffff;
                    color: #475569;
                }
                .cbt-exam-list-active-chip strong {
                    color: #0f172a;
                }
                .cbt-exam-list-title-cell {
                    display: grid;
                    gap: 6px;
                    min-width: 220px;
                }
                .cbt-exam-list-title-cell strong {
                    display: block;
                    color: #0f172a;
                    line-height: 1.5;
                }
                .cbt-exam-list-topology {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-exam-list-topology small {
                    color: #64748b;
                    line-height: 1.45;
                }
                .cbt-exam-list-topology-badge {
                    display: inline-flex;
                    align-items: center;
                    min-height: 26px;
                    padding: 0 10px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.02em;
                }
                .cbt-exam-list-topology-badge--bank {
                    background: #dbeafe;
                    color: #1d4ed8;
                }
                .cbt-exam-list-topology-badge--legacy {
                    background: #ffedd5;
                    color: #b45309;
                }
                .cbt-exam-list-topology-badge--mixed {
                    background: #fdf2f8;
                    color: #be185d;
                }
                .cbt-exam-list-topology-badge--empty {
                    background: #f1f5f9;
                    color: #475569;
                }
                .cbt-exam-status-pill {
                    display: inline-flex;
                    align-items: center;
                    min-height: 30px;
                    padding: 0 12px;
                    border-radius: 999px;
                    font-size: 12px;
                    font-weight: 700;
                    text-transform: capitalize;
                }
                .cbt-exam-status-pill--draft {
                    background: #fff7ed;
                    color: #b45309;
                }
                .cbt-exam-status-pill--published {
                    background: #ecfdf5;
                    color: #047857;
                }
                .cbt-exam-status-pill--closed {
                    background: #eff6ff;
                    color: #1d4ed8;
                }
                .cbt-exam-status-stack {
                    display: grid;
                    gap: 8px;
                    align-content: start;
                }
                .cbt-exam-status-flags {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                }
                .cbt-exam-status-flag {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 0 10px;
                    border: 1px solid transparent;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 700;
                    line-height: 1;
                    white-space: nowrap;
                }
                .cbt-exam-status-flag--question.is-active {
                    background: #dbeafe;
                    border-color: #bfdbfe;
                    color: #1d4ed8;
                }
                .cbt-exam-status-flag--question.is-inactive {
                    background: #eff6ff;
                    border-color: #dbeafe;
                    color: #64748b;
                }
                .cbt-exam-status-flag--option.is-active {
                    background: #fef3c7;
                    border-color: #fde68a;
                    color: #b45309;
                }
                .cbt-exam-status-flag--option.is-inactive {
                    background: #fff7ed;
                    border-color: #fed7aa;
                    color: #78716c;
                }
                .cbt-exam-monitoring-stack {
                    display: grid;
                    gap: 4px;
                    color: #475569;
                    font-size: 13px;
                    line-height: 1.45;
                }
                .cbt-exam-monitoring-stack strong {
                    color: #0f172a;
                }
                .cbt-exam-row-actions {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(84px, 1fr));
                    gap: 8px;
                    min-width: 196px;
                }
                .cbt-exam-row-action {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 34px;
                    padding: 0 12px;
                    border: 1px solid #d9e2ec;
                    border-radius: 12px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    color: #0f4fa8;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 600;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
                    transition: transform 120ms ease, border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease, color 120ms ease;
                }
                .cbt-exam-row-action:hover,
                .cbt-exam-row-action:focus {
                    border-color: #a8c7e6;
                    background: #ffffff;
                    color: #0b3d91;
                    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
                    transform: translateY(-1px);
                    outline: none;
                }
                .cbt-exam-row-action--preview {
                    background: linear-gradient(180deg, #f8fbff 0%, #edf5ff 100%);
                }
                .cbt-exam-row-action--results {
                    background: linear-gradient(180deg, #f7fffb 0%, #ebfbf3 100%);
                    color: #0f766e;
                }
                .cbt-exam-row-action--results:hover,
                .cbt-exam-row-action--results:focus {
                    border-color: #99f6e4;
                    color: #115e59;
                }
                .cbt-exam-row-action--edit {
                    background: linear-gradient(180deg, #fffdf7 0%, #fef7e6 100%);
                    color: #b45309;
                }
                .cbt-exam-row-action--edit:hover,
                .cbt-exam-row-action--edit:focus {
                    border-color: #f7d79a;
                    color: #92400e;
                }
                .cbt-exam-row-action--delete {
                    background: linear-gradient(180deg, #fff8f8 0%, #feecec 100%);
                    color: #b91c1c;
                }
                .cbt-exam-row-action--delete:hover,
                .cbt-exam-row-action--delete:focus {
                    border-color: #f1b5b5;
                    color: #991b1b;
                }
                .cbt-admin-pagination-wrap {
                    clear: both;
                }
                .cbt-admin-pagination {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-admin-pagination .cbt-admin-total {
                    font-size: 14px;
                    line-height: 1.4;
                    color: #1d2327;
                    font-weight: 600;
                }
                .cbt-admin-pagination-links {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-admin-pagination-links .page-numbers {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 36px;
                    height: 36px;
                    padding: 0 12px;
                    border: 1px solid #c7d2e0;
                    border-radius: 10px;
                    background: #ffffff;
                    color: #1d2327;
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 600;
                    transition: all 0.2s ease;
                    box-sizing: border-box;
                }
                .cbt-admin-pagination-links .page-numbers:hover,
                .cbt-admin-pagination-links .page-numbers:focus {
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px rgba(34, 113, 177, 0.15);
                    color: #0a4b78;
                    outline: none;
                }
                .cbt-admin-pagination-links .page-numbers.current {
                    border-color: #2271b1;
                    background: #2271b1;
                    color: #ffffff;
                    box-shadow: 0 8px 16px rgba(34, 113, 177, 0.18);
                }
                .cbt-admin-pagination-links .page-numbers.prev,
                .cbt-admin-pagination-links .page-numbers.next {
                    padding: 0 14px;
                    font-weight: 700;
                }
                .cbt-admin-pagination-links .page-numbers.dots {
                    border-color: transparent;
                    background: transparent;
                    color: #646970;
                    min-width: auto;
                    padding: 0 4px;
                    box-shadow: none;
                }
                @media (max-width: 1180px) {
                    .cbt-exams-hero {
                        flex-direction: column;
                    }
                    .cbt-exams-hero-stats,
                    .cbt-exam-builder-summary,
                    .cbt-exam-question-filter-grid {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                    .cbt-exam-question-workspace {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-question-sidebar {
                        position: static;
                    }
                }
                @media (max-width: 860px) {
                    .cbt-exam-list-toolbar-grid {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                    .cbt-exam-snapshot-queue-stats,
                    .cbt-exam-snapshot-summary-grid--start,
                    .cbt-exam-snapshot-summary-grid--runtime {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                    #cbt-exam-snapshot-filter-form .cbt-exam-list-toolbar-grid {
                        grid-template-columns: minmax(0, 1fr) auto;
                    }
                    .cbt-exam-readiness-target-row {
                        grid-template-columns: 1fr;
                    }
                    #cbt-exam-details-panel .form-table tr {
                        grid-template-columns: 1fr;
                    }
                    #cbt-exam-details-panel .form-table th {
                        padding-top: 0;
                    }
                    .cbt-exam-selected-sidebar-summary {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-selected-item {
                        grid-template-columns: auto minmax(0, 1fr);
                    }
                    .cbt-exam-selected-item-actions {
                        grid-column: 2;
                    }
                }
                @media (max-width: 782px) {
                    .cbt-exams-page {
                        margin-right: 10px;
                    }
                    .cbt-exams-hero,
                    .cbt-exam-page-panel {
                        padding: 20px;
                    }
                    .cbt-exam-flow-bar {
                        margin-left: -20px;
                        margin-right: -20px;
                        padding-left: 20px;
                        padding-right: 20px;
                    }
                    .cbt-exams-page-tab.button {
                        min-width: 0;
                        width: 100%;
                    }
                    .cbt-exams-hero-stats,
                    .cbt-exam-builder-summary,
                    .cbt-exam-question-filter-grid {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-flow-bar,
                    .cbt-exam-question-filter-panel,
                    .cbt-exam-question-status-bar,
                    .cbt-exam-list-toolbar {
                        align-items: stretch;
                    }
                    .cbt-exam-question-filter-panel {
                        grid-template-columns: 1fr;
                        grid-template-areas:
                            "lineage"
                            "actions"
                            "filters";
                    }
                    .cbt-exam-flow-tabs,
                    .cbt-exam-flow-actions,
                    .cbt-exam-panel-nav-actions,
                    .cbt-exam-question-filter-actions,
                    .cbt-exam-question-bulk-actions {
                        width: 100%;
                    }
                    .cbt-exam-flow-bar,
                    .cbt-exam-flow-tabs,
                    .cbt-exam-flow-actions {
                        flex-wrap: wrap;
                    }
                    .cbt-exam-flow-tabs .button,
                    .cbt-exam-builder-actions .button,
                    .cbt-exam-panel-nav-actions .button,
                    .cbt-exam-question-filter-actions .button,
                    .cbt-exam-question-bulk-actions .button,
                    #cbt-exam-selected-count {
                        width: 100%;
                        justify-content: center;
                    }
                    .cbt-exam-flow-actions .cbt-exam-flow-arrow {
                        width: 100%;
                        justify-content: flex-start;
                    }
                    .cbt-exam-row-actions {
                        grid-template-columns: 1fr;
                        min-width: 0;
                    }
                    .cbt-exam-list-toolbar-grid {
                        grid-template-columns: 1fr;
                    }
                    #cbt-exam-snapshot-filter-form .cbt-exam-list-toolbar-grid {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-list-toolbar-actions {
                        width: 100%;
                        justify-content: stretch;
                    }
                    .cbt-exam-list-toolbar-actions .button {
                        flex: 1 1 auto;
                    }
                    .cbt-exam-snapshot-actions-bar {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    .cbt-exam-snapshot-subtab {
                        min-width: 0;
                        width: 100%;
                    }
                    .cbt-exam-snapshot-monitor-card {
                        padding: 16px;
                    }
                    .cbt-exam-snapshot-monitor-card-head {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    .cbt-exam-snapshot-bulk-actions {
                        width: 100%;
                        flex-direction: column;
                        align-items: stretch;
                    }
                    .cbt-exam-snapshot-bulk-form {
                        width: 100%;
                    }
                    .cbt-exam-snapshot-bulk-form .button {
                        width: 100%;
                    }
                    .cbt-exam-snapshot-row-actions .button {
                        width: 100%;
                        text-align: center;
                    }
                    .cbt-exam-snapshot-exam-cell {
                        min-width: 0;
                    }
                    .cbt-exam-snapshot-summary-grid {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-snapshot-queue-stats {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-snapshot-detail-grid {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-snapshot-student-toolbar-grid {
                        grid-template-columns: 1fr;
                    }
                    .cbt-student-snapshot-profile-top {
                        grid-template-columns: 1fr;
                    }
                    .cbt-student-snapshot-photo {
                        width: 64px;
                        height: 64px;
                    }
                    .cbt-exam-snapshot-student-toolbar-actions {
                        width: 100%;
                    }
                    .cbt-exam-snapshot-student-toolbar-actions .button {
                        flex: 1 1 auto;
                    }
                    .cbt-student-snapshot-row-actions .button {
                        width: 100%;
                        text-align: center;
                    }
                    .cbt-student-snapshot-table {
                        table-layout: auto;
                    }
                    .cbt-exam-snapshot-student-pagination {
                        align-items: stretch;
                    }
                    .cbt-exam-snapshot-student-pagination .button {
                        width: 100%;
                        text-align: center;
                    }
                    .cbt-exam-snapshot-preview-row-cell {
                        padding-left: 8px;
                        padding-right: 8px;
                    }
                    .cbt-exam-snapshot-preview-summary {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    .cbt-exam-snapshot-preview-pagination {
                        align-items: stretch;
                    }
                    .cbt-exam-snapshot-preview-pagination .button {
                        width: 100%;
                        text-align: center;
                    }
                    .cbt-exam-snapshot-summary-grid--start,
                    .cbt-exam-snapshot-summary-grid--runtime {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-question-shell {
                        padding: 16px;
                    }
                    .cbt-exam-question-field-search {
                        grid-column: auto;
                    }
                    .cbt-exam-question-guide {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    .cbt-exam-selected-sidebar-header,
                    .cbt-exam-selection-confirm-actions {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    .cbt-exam-save-progress-overlay {
                        padding: 16px;
                    }
                    .cbt-exam-save-progress-card {
                        padding: 18px 16px;
                    }
                    .cbt-exam-save-progress-meta,
                    .cbt-admin-pagination {
                        align-items: flex-start;
                    }
                    .cbt-admin-pagination-links .page-numbers {
                        min-width: 32px;
                        height: 32px;
                        padding: 0 10px;
                        font-size: 13px;
                    }
                }
                <?php echo CBT_Admin_Questions_Helper::get_admin_student_preview_css(); ?>
            </style>
        </div>
        <script>
            (function () {
                function initTabs(containerId, panelSelector, options = {}) {
                    const container = document.getElementById(containerId);
                    if (!container) {
                        return null;
                    }

                    const buttons = Array.from(container.querySelectorAll('button[data-target]'));
                    const panels = Array.from(document.querySelectorAll(panelSelector));
                    const beforeActivate = options && typeof options.beforeActivate === 'function'
                        ? options.beforeActivate
                        : null;
                    const afterActivate = options && typeof options.afterActivate === 'function'
                        ? options.afterActivate
                        : null;
                    if (buttons.length === 0 || panels.length === 0) {
                        return null;
                    }

                    const setActive = (targetId) => {
                        buttons.forEach((button) => {
                            const isActive = button.getAttribute('data-target') === targetId;
                            button.classList.toggle('cbt-active', isActive);
                            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        });

                        panels.forEach((panel) => {
                            const isActive = panel.id === targetId;
                            panel.classList.toggle('cbt-active', isActive);
                            panel.hidden = !isActive;
                        });
                    };

                    const activate = (targetId, activateOptions = {}) => {
                        const normalizedTargetId = String(targetId || '');
                        const skipBeforeActivate = !!(activateOptions && activateOptions.skipBeforeActivate);
                        if (!skipBeforeActivate && beforeActivate && beforeActivate(normalizedTargetId) === false) {
                            return false;
                        }

                        setActive(normalizedTargetId);
                        if (afterActivate) {
                            afterActivate(normalizedTargetId, buttons);
                        }
                        return true;
                    };

                    buttons.forEach((button) => {
                        button.addEventListener('click', () => {
                            activate(String(button.getAttribute('data-target') || ''));
                        });
                    });

                    const activeButton = buttons.find((button) => button.classList.contains('cbt-active')) || buttons[0];
                    if (activeButton) {
                        setActive(String(activeButton.getAttribute('data-target') || ''));
                    }

                    return activate;
                }

                const activatePageTab = initTabs('cbt-exam-page-tabs', '.cbt-exam-page-panel', {
                    afterActivate(targetId) {
                        if (typeof window.URL !== 'function' || !window.history || typeof window.history.replaceState !== 'function') {
                            handleSnapshotAutoRefreshState();
                            return;
                        }

                        let panelValue = 'builder';
                        if (targetId === 'cbt-exam-list-panel') {
                            panelValue = 'list';
                        } else if (targetId === 'cbt-exam-snapshot-panel') {
                            panelValue = 'snapshot';
                        }

                        const nextUrl = new URL(window.location.href);
                        nextUrl.searchParams.set('cbt_exam_panel', panelValue);
                        window.history.replaceState({}, '', nextUrl.toString());
                        handleSnapshotAutoRefreshState();
                    },
                });
                const page = document.querySelector('.cbt-exams-page');
                const snapshotPanel = document.getElementById('cbt-exam-snapshot-panel');
                const examListFilterForm = document.getElementById('cbt-exam-list-filter-form');
                const examListSearchInput = document.getElementById('cbt-exam-search');
                const examSnapshotFilterForm = document.getElementById('cbt-exam-snapshot-filter-form');
                const examListAutoSubmitFields = examListFilterForm
                    ? Array.from(examListFilterForm.querySelectorAll('select[name="cbt_exam_subject"], select[name="cbt_exam_status"], select[name="cbt_exam_kelas"], select[name="cbt_exam_per_page"]'))
                    : [];
                const examSnapshotAutoSubmitFields = examSnapshotFilterForm
                    ? Array.from(examSnapshotFilterForm.querySelectorAll('select[name="cbt_exam_subject"], select[name="cbt_exam_status"], select[name="cbt_exam_kelas"], select[name="cbt_exam_per_page"]'))
                    : [];
                const examSnapshotPickerLabel = examSnapshotFilterForm
                    ? examSnapshotFilterForm.querySelector('[data-cbt-exam-snapshot-picker-label]')
                    : null;
                const examSnapshotPickerMeta = examSnapshotFilterForm
                    ? examSnapshotFilterForm.querySelector('[data-cbt-exam-snapshot-picker-meta]')
                    : null;
                const examSnapshotPickerDetails = examSnapshotFilterForm
                    ? examSnapshotFilterForm.querySelector('[data-cbt-exam-snapshot-picker]')
                    : null;
                const examSnapshotPickerCheckboxes = examSnapshotFilterForm
                    ? Array.from(examSnapshotFilterForm.querySelectorAll('[data-cbt-exam-snapshot-checkbox]'))
                    : [];
                const form = document.getElementById('cbt-exam-builder-form');
                const examDetailsPanel = document.getElementById('cbt-exam-details-panel');
                const detailValidationNotice = document.getElementById('cbt-exam-detail-validation-notice');
                const kelasChecklist = document.getElementById('cbt-kelas-checklist');
                const questionTabButton = document.getElementById('cbt-exam-builder-tab-questions');
                const nextQuestionButton = document.getElementById('cbt-exam-builder-next-btn');
                const questionSubmitHelp = document.getElementById('cbt-exam-question-submit-help');
                const stateKeyInput = document.getElementById('cbt-exam-builder-state-key');
                const resetKeysInput = document.getElementById('cbt-exam-builder-reset-keys');
                const selectedDefaultsInput = document.getElementById('cbt-exam-selected-defaults');
                const selectedInitialsInput = document.getElementById('cbt-exam-selected-initials');
                const selectedDetailsInput = document.getElementById('cbt-exam-selected-details');
                const selectedPreviewsScript = document.getElementById('cbt-exam-selected-previews');
                const builderContextFingerprintInput = document.getElementById('cbt-exam-builder-context-fingerprint');
                const quickViewModal = document.getElementById('cbt-exam-quickview-modal');
                const quickViewBody = document.getElementById('cbt-exam-quickview-body');
                const quickViewTitle = document.getElementById('cbt-exam-quickview-title');
                const quickViewCloseTop = document.getElementById('cbt-exam-quickview-close-top');
                const quickViewCloseBottom = document.getElementById('cbt-exam-quickview-close-bottom');
                const kelasCheckboxes = Array.from(document.querySelectorAll('.cbt-kelas-checkbox'));
                const subjectInput = document.getElementById('cbt-exam-subject-id');
                const titleInput = document.getElementById('cbt-exam-title');
                const durationInput = document.getElementById('cbt-exam-duration');
                const statusInput = form ? form.querySelector('select[name="status"]') : null;
                const startsAtInput = document.getElementById('cbt-exam-starts-at');
                const endsAtInput = document.getElementById('cbt-exam-ends-at');
                const randomizeInput = document.getElementById('cbt-exam-randomize');
                const descriptionInput = document.getElementById('cbt-exam-description');
                const builderStateKey = stateKeyInput ? String(stateKeyInput.value || '') : '';
                const currentBuilderContextFingerprint = builderContextFingerprintInput
                    ? String(builderContextFingerprintInput.value || '')
                    : '';
                const ajaxNonceInput = document.getElementById('cbt-exam-builder-ajax-nonce');
                const questionModeInput = document.getElementById('cbt-exam-builder-question-mode');
                const builderNavButtons = Array.from(document.querySelectorAll('.cbt-exam-builder-nav-btn'));
                const submitExamButtons = Array.from(document.querySelectorAll('.cbt-exam-submit-btn'));
                const saveProgressOverlay = document.getElementById('cbt-exam-save-progress-overlay');
                const saveProgressTitle = document.getElementById('cbt-exam-save-progress-title');
                const saveProgressMessage = document.getElementById('cbt-exam-save-progress-message');
                const saveProgressPhase = document.getElementById('cbt-exam-save-progress-phase');
                const saveProgressStats = document.getElementById('cbt-exam-save-progress-stats');
                const saveProgressFill = document.getElementById('cbt-exam-save-progress-fill');
                const saveProgressPercent = document.getElementById('cbt-exam-save-progress-percent');
                const cleanProgressOverlay = document.getElementById('cbt-exam-clean-progress-overlay');
                const cleanProgressTitle = document.getElementById('cbt-exam-clean-progress-title');
                const cleanProgressMessage = document.getElementById('cbt-exam-clean-progress-message');
                const cleanProgressPhase = document.getElementById('cbt-exam-clean-progress-phase');
                const cleanProgressStats = document.getElementById('cbt-exam-clean-progress-stats');
                const cleanProgressFill = document.getElementById('cbt-exam-clean-progress-fill');
                const cleanProgressPercent = document.getElementById('cbt-exam-clean-progress-percent');
                const bulkProgressOverlay = document.getElementById('cbt-exam-bulk-progress-overlay');
                const bulkProgressTitle = document.getElementById('cbt-exam-bulk-progress-title');
                const bulkProgressMessage = document.getElementById('cbt-exam-bulk-progress-message');
                const bulkProgressPhase = document.getElementById('cbt-exam-bulk-progress-phase');
                const bulkProgressStats = document.getElementById('cbt-exam-bulk-progress-stats');
                const bulkProgressFill = document.getElementById('cbt-exam-bulk-progress-fill');
                const bulkProgressPercent = document.getElementById('cbt-exam-bulk-progress-percent');
                const selectedSidebarList = document.getElementById('cbt-exam-selected-sidebar-list');
                const selectedSidebarTotal = document.getElementById('cbt-exam-selected-total');
                const selectedSidebarAdded = document.getElementById('cbt-exam-selected-added');
                const selectedSidebarRemoved = document.getElementById('cbt-exam-selected-removed');
                const selectedSidebarDirtyState = document.getElementById('cbt-exam-selected-dirty-state');
                const selectedSidebarSearchInput = document.getElementById('cbt-exam-selected-search');
                const selectedSidebarFilterCount = document.getElementById('cbt-exam-selected-filter-count');
                const selectionConfirmModal = document.getElementById('cbt-exam-selection-confirm-modal');
                const selectionConfirmMessage = document.getElementById('cbt-exam-selection-confirm-message');
                const selectionConfirmSubmit = document.getElementById('cbt-exam-selection-confirm-submit');
                const selectionConfirmCancel = document.getElementById('cbt-exam-selection-confirm-cancel');
                const cancelEditLink = document.getElementById('cbt-exam-cancel-edit');
                const ajaxNonce = ajaxNonceInput ? String(ajaxNonceInput.value || '') : '';
                let isFinalFormSubmit = false;
                let isExamSaveRunning = false;
                let isSnapshotCleanRunning = false;
                let isBulkPreflightRunning = false;
                let isAdaptiveFinalizeRunning = false;
                let examListFilterTimer = null;
                let examSnapshotFilterTimer = null;
                let snapshotAutoRefreshTimer = null;
                let snapshotCleanProgressTimer = null;
                let bulkProgressTimer = null;
                let isPageNavigating = false;
                let questionFilterTimer = 0;
                let questionCatalogRequestSeq = 0;
                let selectionSyncTimer = null;
                let pendingSelectionConfirmAction = null;
                let selectedSidebarSearchTerm = '';
                const defaultQuestionSubmitHelpText = questionSubmitHelp ? String(questionSubmitHelp.textContent || '').trim() : '';
                const snapshotAutoRefreshSeconds = snapshotPanel
                    ? Math.max(0, Number(snapshotPanel.getAttribute('data-cbt-snapshot-auto-refresh-seconds') || '0') || 0)
                    : 0;

                function clearSnapshotAutoRefreshTimer() {
                    if (snapshotAutoRefreshTimer) {
                        window.clearTimeout(snapshotAutoRefreshTimer);
                        snapshotAutoRefreshTimer = null;
                    }
                }

                function clearSnapshotCleanProgressTimer() {
                    if (snapshotCleanProgressTimer) {
                        window.clearInterval(snapshotCleanProgressTimer);
                        snapshotCleanProgressTimer = null;
                    }
                }

                function clearBulkProgressTimer() {
                    if (bulkProgressTimer) {
                        window.clearInterval(bulkProgressTimer);
                        bulkProgressTimer = null;
                    }
                }

                function isSnapshotFilterInteractionActive() {
                    if (!examSnapshotFilterForm) {
                        return false;
                    }

                    if (examSnapshotPickerDetails instanceof HTMLDetailsElement && examSnapshotPickerDetails.open) {
                        return true;
                    }

                    const activeElement = document.activeElement;
                    return activeElement instanceof HTMLElement && examSnapshotFilterForm.contains(activeElement);
                }

                function isPreflightSnapshotAutoRefreshActive() {
                    if (!snapshotPanel || snapshotAutoRefreshSeconds <= 0 || isPageNavigating) {
                        return false;
                    }

                    if (!snapshotPanel.classList.contains('cbt-active') || document.hidden) {
                        return false;
                    }

                    if (isSnapshotFilterInteractionActive()) {
                        return false;
                    }

                    return String(snapshotPanel.getAttribute('data-cbt-snapshot-tab') || '') === 'preflight';
                }

                function scheduleSnapshotAutoRefresh() {
                    clearSnapshotAutoRefreshTimer();

                    if (!isPreflightSnapshotAutoRefreshActive()) {
                        return;
                    }

                    snapshotAutoRefreshTimer = window.setTimeout(() => {
                        if (!isPreflightSnapshotAutoRefreshActive()) {
                            clearSnapshotAutoRefreshTimer();
                            return;
                        }

                        isPageNavigating = true;
                        window.location.reload();
                    }, snapshotAutoRefreshSeconds * 1000);
                }

                function handleSnapshotAutoRefreshState() {
                    if (isPreflightSnapshotAutoRefreshActive()) {
                        scheduleSnapshotAutoRefresh();
                        return;
                    }

                    clearSnapshotAutoRefreshTimer();
                }

                function getQuestionCatalogPanel() {
                    return page ? page.querySelector('[data-cbt-exam-question-catalog]') : null;
                }

                function isQuestionCatalogLoaded() {
                    const panel = getQuestionCatalogPanel();
                    return !!(panel && panel.getAttribute('data-cbt-question-catalog-loaded') === '1');
                }

                function getQuestionMode() {
                    return questionModeInput ? String(questionModeInput.value || 'catalog') : 'catalog';
                }

                function setQuestionMode(nextMode) {
                    if (questionModeInput) {
                        questionModeInput.value = String(nextMode || 'catalog');
                    }
                }

                function getQuestionSearchInput(panel = getQuestionCatalogPanel()) {
                    return panel ? panel.querySelector('#cbt-exam-question-search') : null;
                }

                function getQuestionTypeFilter(panel = getQuestionCatalogPanel()) {
                    return panel ? panel.querySelector('#cbt-exam-question-type-filter') : null;
                }

                function getQuestionSourceFilter(panel = getQuestionCatalogPanel()) {
                    return panel ? panel.querySelector('#cbt-exam-question-source-filter') : null;
                }

                function getQuestionPerPageFilter(panel = getQuestionCatalogPanel()) {
                    return panel ? panel.querySelector('#cbt-exam-question-per-page') : null;
                }

                function getQuestionResetButton(panel = getQuestionCatalogPanel()) {
                    return panel ? panel.querySelector('#cbt-exam-reset-filters') : null;
                }

                function getQuestionSelectedCountEl(panel = getQuestionCatalogPanel()) {
                    return panel ? panel.querySelector('#cbt-exam-selected-count') : null;
                }

                function getQuestionSelectAllVisible(panel = getQuestionCatalogPanel()) {
                    return panel ? panel.querySelector('#cbt-exam-select-all-visible') : null;
                }

                function getQuestionPickButtons(panel = getQuestionCatalogPanel()) {
                    return panel ? Array.from(panel.querySelectorAll('.cbt-exam-question-pick-btn')) : [];
                }

                function getQuestionSelectVisibleBtn(panel = getQuestionCatalogPanel()) {
                    return panel ? panel.querySelector('#cbt-exam-select-visible') : null;
                }

                function getQuestionRows(panel = getQuestionCatalogPanel()) {
                    return panel ? Array.from(panel.querySelectorAll('.cbt-exam-question-row')) : [];
                }

                function getQuestionQuickViewButtons(panel = getQuestionCatalogPanel()) {
                    return panel ? Array.from(panel.querySelectorAll('.cbt-quick-view-btn')) : [];
                }

                function getQuestionModeNavLinks(panel = getQuestionCatalogPanel()) {
                    return panel ? Array.from(panel.querySelectorAll('.cbt-exam-question-nav-link')) : [];
                }

                function getQuestionPaginationLinks(panel = getQuestionCatalogPanel()) {
                    return panel ? Array.from(panel.querySelectorAll('.cbt-exam-question-pagination-wrap a.page-numbers')) : [];
                }

                function getSummarySubjectEl() {
                    return document.getElementById('cbt-exam-summary-subject');
                }

                function getSummaryScheduleEl() {
                    return document.getElementById('cbt-exam-summary-schedule');
                }

                function getSummaryKelasEl() {
                    return document.getElementById('cbt-exam-summary-kelas');
                }

                function getSummarySelectedEl() {
                    return document.getElementById('cbt-exam-summary-selected');
                }

                function submitExamListFilters() {
                    if (!examListFilterForm) {
                        return;
                    }

                    if (examListFilterTimer) {
                        window.clearTimeout(examListFilterTimer);
                        examListFilterTimer = null;
                    }

                    if (typeof examListFilterForm.requestSubmit === 'function') {
                        examListFilterForm.requestSubmit();
                        return;
                    }

                    examListFilterForm.submit();
                }

                function queueExamListFilters(delay = 0) {
                    if (!examListFilterForm) {
                        return;
                    }

                    if (examListFilterTimer) {
                        window.clearTimeout(examListFilterTimer);
                    }

                    examListFilterTimer = window.setTimeout(() => {
                        submitExamListFilters();
                    }, Math.max(0, Number(delay) || 0));
                }

                function submitExamSnapshotFilters() {
                    if (!examSnapshotFilterForm) {
                        return;
                    }

                    if (examSnapshotFilterTimer) {
                        window.clearTimeout(examSnapshotFilterTimer);
                        examSnapshotFilterTimer = null;
                    }

                    if (typeof examSnapshotFilterForm.requestSubmit === 'function') {
                        examSnapshotFilterForm.requestSubmit();
                        return;
                    }

                    examSnapshotFilterForm.submit();
                }

                function queueExamSnapshotFilters(delay = 0) {
                    if (!examSnapshotFilterForm) {
                        return;
                    }

                    if (examSnapshotFilterTimer) {
                        window.clearTimeout(examSnapshotFilterTimer);
                    }

                    examSnapshotFilterTimer = window.setTimeout(() => {
                        submitExamSnapshotFilters();
                    }, Math.max(0, Number(delay) || 0));
                }

                function updateExamSnapshotPickerSummary() {
                    if (!examSnapshotPickerLabel || !examSnapshotPickerMeta) {
                        return;
                    }

                    const checkedBoxes = examSnapshotPickerCheckboxes.filter((checkbox) => checkbox instanceof HTMLInputElement && checkbox.checked);
                    const checkedTitles = checkedBoxes.map((checkbox) => {
                        const option = checkbox.closest('.cbt-exam-snapshot-picker-option');
                        const titleEl = option ? option.querySelector('strong') : null;
                        return titleEl ? String(titleEl.textContent || '').trim() : '';
                    }).filter(Boolean);

                    if (checkedBoxes.length <= 0) {
                        examSnapshotPickerLabel.textContent = 'Pilih satu atau beberapa exam';
                        examSnapshotPickerMeta.textContent = 'Buka daftar exam aktif lalu centang exam yang ingin ditampilkan.';
                        return;
                    }

                    examSnapshotPickerLabel.textContent = checkedBoxes.length === 1
                        ? '1 exam dipilih'
                        : (checkedBoxes.length + ' exam dipilih');

                    if (checkedBoxes.length === 1) {
                        examSnapshotPickerMeta.textContent = checkedTitles[0] || '1 exam dipilih';
                        return;
                    }

                    let summary = checkedTitles.slice(0, 2).join(', ');
                    if (checkedBoxes.length > 2) {
                        summary += ' +' + (checkedBoxes.length - 2) + ' lainnya';
                    }
                    examSnapshotPickerMeta.textContent = summary;
                }

                async function refreshExamSnapshotPreview(link) {
                    if (!(link instanceof HTMLAnchorElement)) {
                        return;
                    }

                    const previewRow = link.closest('.cbt-exam-snapshot-preview-row');
                    if (!previewRow) {
                        window.location.href = link.href;
                        return;
                    }

                    const examId = String(previewRow.getAttribute('data-cbt-exam-snapshot-preview-row') || '').trim();
                    if (!examId) {
                        window.location.href = link.href;
                        return;
                    }

                    const summaryRow = document.querySelector('[data-cbt-exam-snapshot-summary-row="' + examId + '"]');
                    if (!summaryRow) {
                        window.location.href = link.href;
                        return;
                    }

                    previewRow.classList.add('is-loading');
                    previewRow.setAttribute('aria-busy', 'true');

                    try {
                        const response = await window.fetch(link.href, {
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'CBTExamSnapshotPreview',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }

                        const html = await response.text();
                        const parser = new window.DOMParser();
                        const nextDocument = parser.parseFromString(html, 'text/html');
                        const nextSummaryRow = nextDocument.querySelector('[data-cbt-exam-snapshot-summary-row="' + examId + '"]');
                        const nextPreviewRow = nextDocument.querySelector('[data-cbt-exam-snapshot-preview-row="' + examId + '"]');

                        if (!nextSummaryRow || !nextPreviewRow) {
                            throw new Error('snapshot rows missing');
                        }

                        const importedSummaryRow = document.importNode(nextSummaryRow, true);
                        const importedPreviewRow = document.importNode(nextPreviewRow, true);

                        summaryRow.replaceWith(importedSummaryRow);
                        previewRow.replaceWith(importedPreviewRow);

                        if (window.history && typeof window.history.replaceState === 'function') {
                            window.history.replaceState({}, '', link.href);
                        }
                    } catch (error) {
                        window.location.href = link.href;
                        return;
                    }
                }

                document.addEventListener('click', (event) => {
                    const target = event.target instanceof Element ? event.target.closest('.cbt-exam-snapshot-preview-pagination a') : null;
                    if (!(target instanceof HTMLAnchorElement)) {
                        return;
                    }

                    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                        return;
                    }

                    event.preventDefault();
                    refreshExamSnapshotPreview(target);
                });

                if (examListFilterForm) {
                    examListAutoSubmitFields.forEach((field) => {
                        field.addEventListener('change', () => {
                            submitExamListFilters();
                        });
                    });

                    if (examListSearchInput) {
                        examListSearchInput.addEventListener('input', () => {
                            queueExamListFilters(450);
                        });
                        examListSearchInput.addEventListener('search', () => {
                            submitExamListFilters();
                        });
                        examListSearchInput.addEventListener('keydown', (event) => {
                            if (event.key !== 'Enter') {
                                return;
                            }

                            event.preventDefault();
                            submitExamListFilters();
                        });
                    }
                }

                if (examSnapshotFilterForm) {
                    examSnapshotAutoSubmitFields.forEach((field) => {
                        field.addEventListener('change', () => {
                            submitExamSnapshotFilters();
                        });
                    });
                    examSnapshotPickerCheckboxes.forEach((checkbox) => {
                        checkbox.addEventListener('change', updateExamSnapshotPickerSummary);
                    });
                    examSnapshotFilterForm.addEventListener('focusin', () => {
                        handleSnapshotAutoRefreshState();
                    });
                    examSnapshotFilterForm.addEventListener('focusout', () => {
                        window.setTimeout(() => {
                            handleSnapshotAutoRefreshState();
                        }, 0);
                    });
                    if (examSnapshotPickerDetails instanceof HTMLDetailsElement) {
                        examSnapshotPickerDetails.addEventListener('toggle', () => {
                            handleSnapshotAutoRefreshState();
                        });
                    }
                    updateExamSnapshotPickerSummary();
                }

                document.addEventListener('submit', (event) => {
                    if (!(event.target instanceof HTMLFormElement)) {
                        return;
                    }

                    isPageNavigating = true;
                    clearSnapshotAutoRefreshTimer();
                }, true);

                window.addEventListener('beforeunload', () => {
                    isPageNavigating = true;
                    clearSnapshotAutoRefreshTimer();
                    clearSnapshotCleanProgressTimer();
                });

                document.addEventListener('visibilitychange', () => {
                    handleSnapshotAutoRefreshState();
                });

                handleSnapshotAutoRefreshState();

                function setExamDetailValidationNotice(isVisible, message = '') {
                    if (!detailValidationNotice) {
                        return;
                    }

                    const messageEl = detailValidationNotice.querySelector('p');
                    if (messageEl && message !== '') {
                        messageEl.textContent = message;
                    }
                    detailValidationNotice.hidden = !isVisible;
                }

                function getExamDetailFieldLabel(field) {
                    if (!field) {
                        return 'field wajib';
                    }

                    const explicitFieldLabel = String(field.getAttribute('data-field-label') || '').trim();
                    if (explicitFieldLabel !== '') {
                        return explicitFieldLabel;
                    }

                    const fieldId = String(field.id || '');
                    if (fieldId !== '') {
                        const explicitLabel = document.querySelector(`label[for="${fieldId}"]`);
                        if (explicitLabel) {
                            return String(explicitLabel.textContent || '').trim() || 'field wajib';
                        }
                    }

                    const row = field.closest('tr');
                    if (row) {
                        const header = row.querySelector('th');
                        if (header) {
                            return String(header.textContent || '').trim() || 'field wajib';
                        }
                    }

                    return 'field wajib';
                }

                function formatExamSummaryDateTime(value) {
                    const normalizedValue = String(value || '').trim();
                    if (normalizedValue === '') {
                        return '';
                    }

                    const normalizedReadableValue = normalizedValue.replace('T', ' ');
                    const parsedDate = new Date(normalizedValue);
                    if (Number.isNaN(parsedDate.getTime())) {
                        return normalizedReadableValue;
                    }

                    try {
                        return parsedDate.toLocaleString('id-ID', {
                            day: '2-digit',
                            month: 'short',
                            hour: '2-digit',
                            minute: '2-digit',
                        });
                    } catch (error) {
                        return normalizedReadableValue;
                    }
                }

                function syncExamBuilderSummary() {
                    const summarySubjectEl = getSummarySubjectEl();
                    const summaryScheduleEl = getSummaryScheduleEl();
                    const summaryKelasEl = getSummaryKelasEl();
                    const summarySelectedEl = getSummarySelectedEl();

                    if (summarySubjectEl && subjectInput) {
                        const selectedOption = subjectInput.options && subjectInput.selectedIndex >= 0
                            ? subjectInput.options[subjectInput.selectedIndex]
                            : null;
                        const selectedSubjectLabel = selectedOption && String(subjectInput.value || '').trim() !== ''
                            ? String(selectedOption.textContent || '').trim()
                            : 'Belum dipilih';
                        summarySubjectEl.textContent = selectedSubjectLabel || 'Belum dipilih';
                    }

                    if (summaryScheduleEl) {
                        const startsAtLabel = formatExamSummaryDateTime(startsAtInput ? startsAtInput.value : '');
                        const endsAtLabel = formatExamSummaryDateTime(endsAtInput ? endsAtInput.value : '');
                        if (startsAtLabel !== '' && endsAtLabel !== '') {
                            summaryScheduleEl.textContent = `${startsAtLabel} -> ${endsAtLabel}`;
                        } else if (startsAtLabel !== '') {
                            summaryScheduleEl.textContent = `Mulai ${startsAtLabel}`;
                        } else if (endsAtLabel !== '') {
                            summaryScheduleEl.textContent = `Selesai ${endsAtLabel}`;
                        } else {
                            summaryScheduleEl.textContent = 'Belum diatur';
                        }
                    }

                    if (summaryKelasEl) {
                        const selectedKelasCount = kelasCheckboxes.filter((checkbox) => checkbox.checked).length;
                        summaryKelasEl.textContent = selectedKelasCount > 0
                            ? `${selectedKelasCount} kelas dipilih`
                            : 'Belum dipilih';
                    }

                    if (summarySelectedEl) {
                        summarySelectedEl.textContent = `${selectedQuestionIds.size} soal`;
                    }
                }

                function getInvalidExamDetailField() {
                    return getExamDetailValidationState().field;
                }

                function getExamDetailValidationState() {
                    if (!examDetailsPanel) {
                        return { field: null, message: '' };
                    }

                    if (subjectInput && String(subjectInput.value || '').trim() === '') {
                        return {
                            field: subjectInput,
                            message: 'Pilih Mapel terlebih dahulu sebelum lanjut ke Pilih Soal.',
                        };
                    }

                    if (titleInput && String(titleInput.value || '').trim() === '') {
                        return {
                            field: titleInput,
                            message: 'Lengkapi Judul Exam terlebih dahulu sebelum lanjut ke Pilih Soal.',
                        };
                    }

                    const durationValue = durationInput ? Number.parseInt(String(durationInput.value || ''), 10) : 0;
                    if (!Number.isInteger(durationValue) || durationValue < 1) {
                        return {
                            field: durationInput,
                            message: 'Isi Durasi (menit) dengan nilai minimal 1 sebelum lanjut ke Pilih Soal.',
                        };
                    }

                    if (statusInput && String(statusInput.value || '').trim() === '') {
                        return {
                            field: statusInput,
                            message: 'Pilih Status exam terlebih dahulu sebelum lanjut ke Pilih Soal.',
                        };
                    }

                    const startsAtValue = startsAtInput ? String(startsAtInput.value || '').trim() : '';
                    if (startsAtValue === '') {
                        return {
                            field: startsAtInput,
                            message: 'Lengkapi waktu Mulai terlebih dahulu sebelum lanjut ke Pilih Soal.',
                        };
                    }

                    const endsAtValue = endsAtInput ? String(endsAtInput.value || '').trim() : '';
                    if (endsAtValue === '') {
                        return {
                            field: endsAtInput,
                            message: 'Lengkapi waktu Selesai terlebih dahulu sebelum lanjut ke Pilih Soal.',
                        };
                    }

                    const startsAtTimestamp = Date.parse(startsAtValue);
                    const endsAtTimestamp = Date.parse(endsAtValue);
                    if (Number.isFinite(startsAtTimestamp) && Number.isFinite(endsAtTimestamp) && endsAtTimestamp <= startsAtTimestamp) {
                        return {
                            field: endsAtInput,
                            message: 'Waktu Selesai harus setelah waktu Mulai.',
                        };
                    }

                    if (kelasCheckboxes.length === 0) {
                        return {
                            field: kelasChecklist,
                            message: 'Belum ada data Kelas Peserta. Isi CBT Users dulu sebelum lanjut ke Pilih Soal.',
                        };
                    }

                    if (!kelasCheckboxes.some((checkbox) => checkbox.checked)) {
                        return {
                            field: kelasChecklist,
                            message: 'Pilih minimal satu Kelas Peserta sebelum lanjut ke Pilih Soal.',
                        };
                    }

                    return { field: null, message: '' };
                }

                function validateExamDetailsBeforeQuestionStep() {
                    const validationState = getExamDetailValidationState();
                    const invalidField = validationState.field;
                    if (!invalidField) {
                        setExamDetailValidationNotice(false);
                        return true;
                    }

                    const fieldLabel = getExamDetailFieldLabel(invalidField);
                    const validationMessage = typeof validationState.message === 'string' && validationState.message !== ''
                        ? validationState.message
                        : `Lengkapi ${fieldLabel} terlebih dahulu sebelum lanjut ke Pilih Soal.`;
                    setExamDetailValidationNotice(true, validationMessage);

                    if (typeof invalidField.reportValidity === 'function') {
                        invalidField.reportValidity();
                    }
                    if (typeof invalidField.focus === 'function') {
                        invalidField.focus();
                    }
                    if (typeof invalidField.scrollIntoView === 'function') {
                        invalidField.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                        });
                    }

                    return false;
                }

                const activateBuilderTab = initTabs('cbt-exam-builder-tabs', '.cbt-exam-builder-panel', {
                    beforeActivate(targetId) {
                        if (targetId === 'cbt-exam-questions-panel') {
                            if (!validateExamDetailsBeforeQuestionStep()) {
                                return false;
                            }
                            if (!isQuestionCatalogLoaded()) {
                                navigateQuestionCatalog(false);
                                return false;
                            }
                            return true;
                        }

                        if (targetId === 'cbt-exam-details-panel') {
                            setExamDetailValidationNotice(false);
                        }

                        return true;
                    },
                });

                function parseJsonValue(rawValue, fallbackValue) {
                    try {
                        const parsed = JSON.parse(String(rawValue || ''));
                        return parsed ?? fallbackValue;
                    } catch (error) {
                        return fallbackValue;
                    }
                }

                const resetStateKeys = parseJsonValue(resetKeysInput ? resetKeysInput.value : '[]', []);
                if (Array.isArray(resetStateKeys) && typeof window.sessionStorage !== 'undefined') {
                    resetStateKeys.forEach((key) => {
                        const normalizedKey = String(key || '').trim();
                        if (normalizedKey !== '') {
                            window.sessionStorage.removeItem(normalizedKey);
                        }
                    });
                }

                const defaultSelectedQuestionIds = parseJsonValue(selectedDefaultsInput ? selectedDefaultsInput.value : '[]', []);
                const loadBuilderState = () => {
                    if (builderStateKey === '' || typeof window.sessionStorage === 'undefined') {
                        return null;
                    }
                    try {
                        const rawState = window.sessionStorage.getItem(builderStateKey);
                        if (!rawState) {
                            return null;
                        }
                        const parsedState = JSON.parse(rawState);
                        if (!parsedState || typeof parsedState !== 'object') {
                            return null;
                        }

                        const savedFingerprint = typeof parsedState.contextFingerprint === 'string'
                            ? parsedState.contextFingerprint
                            : '';
                        if (currentBuilderContextFingerprint !== '' && savedFingerprint !== currentBuilderContextFingerprint) {
                            window.sessionStorage.removeItem(builderStateKey);
                            return null;
                        }

                        return parsedState;
                    } catch (error) {
                        return null;
                    }
                };

                const initialBuilderState = loadBuilderState();
                const initialSelectedIds = initialBuilderState && Array.isArray(initialBuilderState.selectedQuestionIds)
                    ? initialBuilderState.selectedQuestionIds
                    : defaultSelectedQuestionIds;
                const initialServerSelectedIds = parseJsonValue(selectedInitialsInput ? selectedInitialsInput.value : '[]', []);
                const initialSelectedQuestionIdOrder = (Array.isArray(initialServerSelectedIds) ? initialServerSelectedIds : [])
                    .map((item) => parseInt(String(item), 10))
                    .filter((item) => Number.isInteger(item) && item > 0);
                const initialSelectedQuestionIdSet = new Set(initialSelectedQuestionIdOrder);
                const selectedQuestionIds = new Set(
                    (Array.isArray(initialSelectedIds) ? initialSelectedIds : [])
                        .map((item) => parseInt(String(item), 10))
                        .filter((item) => Number.isInteger(item) && item > 0)
                );
                const questionDetailsSeed = parseJsonValue(selectedDetailsInput ? selectedDetailsInput.value : '{}', {});
                const questionDetailsById = {};

                function normalizeQuestionDetail(questionId, detail = {}) {
                    const normalizedId = parseInt(String(questionId || detail.id || ''), 10);
                    if (!Number.isInteger(normalizedId) || normalizedId <= 0) {
                        return null;
                    }

                    return {
                        id: normalizedId,
                        exam_id: parseInt(String(detail.exam_id || 0), 10) || 0,
                        exam_title: String(detail.exam_title || ''),
                        edit_url: String(detail.edit_url || ''),
                        subject_name: String(detail.subject_name || ''),
                        question_type: String(detail.question_type || ''),
                        question_type_label: String(detail.question_type_label || detail.question_type || ''),
                        question_preview: String(detail.question_preview || ''),
                        points: String(detail.points || '1'),
                        lineage_label: String(detail.lineage_label || 'Source'),
                        lineage_class: String(detail.lineage_class || 'default'),
                        source_context_label: String(detail.source_context_label || ''),
                        source_context_display: String(detail.source_context_display || detail.exam_title || ''),
                        lineage_hint: String(detail.lineage_hint || ''),
                    };
                }

                function escapeHtml(value) {
                    return String(value == null ? '' : value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                if (questionDetailsSeed && typeof questionDetailsSeed === 'object') {
                    Object.entries(questionDetailsSeed).forEach(([rawQuestionId, detail]) => {
                        const normalized = normalizeQuestionDetail(rawQuestionId, detail);
                        if (normalized) {
                            questionDetailsById[normalized.id] = normalized;
                        }
                    });
                }
                const questionPreviewHtmlMap = parseJsonValue(selectedPreviewsScript ? selectedPreviewsScript.textContent : '{}', {});

                function getPendingAddedQuestionIds() {
                    return Array.from(selectedQuestionIds).filter((questionId) => !initialSelectedQuestionIdSet.has(questionId));
                }

                function getPendingRemovedQuestionIds() {
                    return initialSelectedQuestionIdOrder.filter((questionId) => !selectedQuestionIds.has(questionId));
                }

                function getSelectedSidebarQuestionOrder() {
                    const currentSelectedIds = Array.from(selectedQuestionIds);
                    const pendingRemovedIds = getPendingRemovedQuestionIds();

                    return currentSelectedIds.concat(
                        pendingRemovedIds.filter((questionId) => !selectedQuestionIds.has(questionId))
                    );
                }

                function upsertQuestionDetail(detail) {
                    const normalized = normalizeQuestionDetail(detail && detail.id ? detail.id : 0, detail || {});
                    if (!normalized) {
                        return;
                    }
                    questionDetailsById[normalized.id] = normalized;
                }

                function upsertQuestionPreviewHtml(questionId, previewHtml) {
                    const normalizedId = parseInt(String(questionId || ''), 10);
                    if (!Number.isInteger(normalizedId) || normalizedId <= 0) {
                        return;
                    }

                    const normalizedHtml = String(previewHtml || '').trim();
                    if (normalizedHtml === '') {
                        return;
                    }

                    questionPreviewHtmlMap[normalizedId] = normalizedHtml;
                }

                function hydrateQuestionDetailsFromPanel(panel = getQuestionCatalogPanel()) {
                    getQuestionRows(panel).forEach((row) => {
                        const questionId = parseInt(String(row.getAttribute('data-question-id') || ''), 10);
                        if (!Number.isInteger(questionId) || questionId <= 0) {
                            return;
                        }

                        upsertQuestionDetail({
                            id: questionId,
                            exam_id: parseInt(String(row.getAttribute('data-source-id') || ''), 10) || 0,
                            exam_title: String(row.getAttribute('data-exam-title') || ''),
                            edit_url: String(row.getAttribute('data-edit-url') || ''),
                            subject_name: String(row.getAttribute('data-subject-name') || ''),
                            question_type: String(row.getAttribute('data-type') || ''),
                            question_type_label: String(row.getAttribute('data-type-label') || ''),
                            question_preview: String(row.getAttribute('data-preview') || ''),
                            points: String(row.getAttribute('data-points') || '1'),
                            lineage_label: String(row.getAttribute('data-lineage-label') || 'Source'),
                            lineage_class: String(row.getAttribute('data-lineage-class') || 'default'),
                            source_context_label: String(row.getAttribute('data-source-context-label') || ''),
                            source_context_display: String(row.getAttribute('data-source-context-display') || ''),
                            lineage_hint: String(row.getAttribute('data-lineage-hint') || ''),
                        });

                        const previewSource = panel.querySelector(`#cbt-quick-view-content-${questionId}`);
                        if (previewSource) {
                            upsertQuestionPreviewHtml(questionId, previewSource.innerHTML);
                        }
                    });
                }

                function setButtonDisabledState(button, isDisabled, disabledReason = '') {
                    if (!button) {
                        return;
                    }

                    button.disabled = !!isDisabled;
                    button.setAttribute('aria-disabled', isDisabled ? 'true' : 'false');
                    if (disabledReason !== '') {
                        button.setAttribute('title', disabledReason);
                    } else {
                        button.removeAttribute('title');
                    }
                }

                function closeSelectionConfirmModal() {
                    if (!selectionConfirmModal) {
                        pendingSelectionConfirmAction = null;
                        return;
                    }

                    selectionConfirmModal.hidden = true;
                    selectionConfirmModal.setAttribute('aria-hidden', 'true');
                    pendingSelectionConfirmAction = null;
                }

                function openSelectionConfirmModal(message, onConfirm) {
                    if (!selectionConfirmModal || typeof onConfirm !== 'function') {
                        return;
                    }

                    pendingSelectionConfirmAction = onConfirm;
                    if (selectionConfirmMessage) {
                        selectionConfirmMessage.textContent = String(message || 'Soal hanya dilepas dari draft exam saat ini. Perubahan baru benar-benar berlaku setelah Anda menyimpan exam.');
                    }
                    selectionConfirmModal.hidden = false;
                    selectionConfirmModal.setAttribute('aria-hidden', 'false');
                }

                function getQuestionDetailById(questionId) {
                    if (Object.prototype.hasOwnProperty.call(questionDetailsById, questionId)) {
                        return questionDetailsById[questionId];
                    }

                    return normalizeQuestionDetail(questionId, {
                        id: questionId,
                        question_preview: `Soal #${questionId}`,
                        question_type_label: 'Soal',
                        lineage_label: 'Source',
                        lineage_class: 'default',
                    });
                }

                function renderSelectedSidebar() {
                    if (!selectedSidebarList) {
                        return;
                    }

                    const pendingAddedIds = getPendingAddedQuestionIds();
                    const pendingRemovedIds = getPendingRemovedQuestionIds();
                    const sidebarQuestionIds = getSelectedSidebarQuestionOrder();
                    const hasPendingChanges = pendingAddedIds.length > 0 || pendingRemovedIds.length > 0;
                    const normalizedSearchTerm = String(selectedSidebarSearchTerm || '').trim().toLowerCase();

                    if (selectedSidebarTotal) {
                        selectedSidebarTotal.textContent = String(selectedQuestionIds.size);
                    }
                    if (selectedSidebarAdded) {
                        selectedSidebarAdded.textContent = String(pendingAddedIds.length);
                    }
                    if (selectedSidebarRemoved) {
                        selectedSidebarRemoved.textContent = String(pendingRemovedIds.length);
                    }
                    if (selectedSidebarDirtyState) {
                        selectedSidebarDirtyState.textContent = hasPendingChanges ? 'Belum disimpan' : 'Sinkron dengan exam';
                        selectedSidebarDirtyState.classList.toggle('is-dirty', hasPendingChanges);
                    }
                    if (selectedSidebarFilterCount) {
                        selectedSidebarFilterCount.hidden = normalizedSearchTerm === '';
                    }

                    if (sidebarQuestionIds.length === 0) {
                        if (selectedSidebarFilterCount) {
                            selectedSidebarFilterCount.textContent = '';
                            selectedSidebarFilterCount.hidden = true;
                        }
                        selectedSidebarList.innerHTML = `
                            <div class="cbt-exam-selected-sidebar-empty">
                                <strong>Belum ada soal di draft exam.</strong>
                                <span>Pilih soal dari katalog di kiri untuk mulai menyusun exam.</span>
                            </div>
                        `;
                        return;
                    }

                    const sidebarItems = sidebarQuestionIds.map((questionId, index) => {
                        const detail = getQuestionDetailById(questionId) || {};
                        const isSelected = selectedQuestionIds.has(questionId);
                        const isInitial = initialSelectedQuestionIdSet.has(questionId);
                        const status = isSelected ? (isInitial ? 'existing' : 'new') : 'removed';
                        const statusLabel = status === 'new'
                            ? 'Baru'
                            : (status === 'removed' ? 'Akan dilepas' : 'Existing');
                        const sequence = isSelected
                            ? String(index + 1)
                            : String(initialSelectedQuestionIdOrder.indexOf(questionId) + 1 || '');
                        const actionLabel = status === 'existing'
                            ? 'Lepas'
                            : 'Batalkan';
                        const preview = detail.question_preview && detail.question_preview !== ''
                            ? detail.question_preview
                            : `Soal #${questionId}`;
                        const sourceDisplay = detail.source_context_display && detail.source_context_display !== ''
                            ? detail.source_context_display
                            : (detail.exam_title || '-');
                        const searchHaystack = [
                            String(questionId),
                            preview,
                            detail.question_type_label || detail.question_type || '',
                            sourceDisplay,
                            detail.subject_name || '',
                            detail.exam_title || '',
                            detail.lineage_label || '',
                            statusLabel,
                        ].join(' ').toLowerCase();
                        const matchesSearch = normalizedSearchTerm === '' || searchHaystack.includes(normalizedSearchTerm);
                        const previewHtml = escapeHtml(preview);
                        const typeLabelHtml = escapeHtml(detail.question_type_label || detail.question_type || 'Soal');
                        const statusLabelHtml = escapeHtml(statusLabel);
                        const lineageLabelHtml = escapeHtml(detail.lineage_label || 'Source');
                        const sourceDisplayHtml = escapeHtml(sourceDisplay);
                        const editUrl = detail.edit_url && detail.edit_url !== ''
                            ? String(detail.edit_url)
                            : '';
                        const safeEditUrl = editUrl !== ''
                            ? escapeHtml(editUrl)
                            : '';

                        return {
                            matchesSearch,
                            html: `
                            <article class="cbt-exam-selected-item cbt-exam-selected-item--${status}">
                                <div class="cbt-exam-selected-item-order">${sequence}</div>
                                <div class="cbt-exam-selected-item-body">
                                    <div class="cbt-exam-selected-item-topline">
                                        <span class="cbt-exam-selected-item-type">${typeLabelHtml}</span>
                                        <span class="cbt-exam-selected-item-status cbt-exam-selected-item-status--${status}">${statusLabelHtml}</span>
                                    </div>
                                    <strong class="cbt-exam-selected-item-preview">${previewHtml}</strong>
                                    <div class="cbt-exam-selected-item-meta">
                                        <span class="cbt-exam-question-lineage-badge cbt-exam-question-lineage-badge--${detail.lineage_class || 'default'}">${lineageLabelHtml}</span>
                                        <small>${sourceDisplayHtml}</small>
                                    </div>
                                </div>
                                <div class="cbt-exam-selected-item-actions">
                                    ${safeEditUrl !== '' ? `
                                    <a href="${safeEditUrl}" class="button button-small cbt-exam-selected-item-action cbt-exam-selected-item-action--edit" target="_blank" rel="noopener noreferrer">
                                        Edit
                                    </a>
                                    ` : ''}
                                    <button type="button" class="button button-small cbt-exam-selected-item-action cbt-exam-selected-item-action--preview" data-sidebar-preview="${questionId}">
                                        Lihat
                                    </button>
                                    <button type="button" class="button button-small cbt-exam-selected-item-action" data-sidebar-action="${status}" data-question-id="${questionId}">
                                        ${actionLabel}
                                    </button>
                                </div>
                            </article>
                        `,
                        };
                    });

                    const visibleSidebarItems = sidebarItems.filter((item) => item.matchesSearch);
                    if (selectedSidebarFilterCount) {
                        selectedSidebarFilterCount.textContent = `${visibleSidebarItems.length} cocok`;
                        selectedSidebarFilterCount.hidden = normalizedSearchTerm === '';
                    }

                    if (visibleSidebarItems.length === 0) {
                        selectedSidebarList.innerHTML = `
                            <div class="cbt-exam-selected-sidebar-empty">
                                <strong>Tidak ada soal yang cocok.</strong>
                                <span>Coba kata kunci lain untuk mencari soal di draft exam.</span>
                            </div>
                        `;
                        return;
                    }

                    const sidebarHtml = visibleSidebarItems.map((item) => item.html).join('');

                    selectedSidebarList.innerHTML = sidebarHtml;
                }

                function getExamBuilderActionState() {
                    const detailValidationState = getExamDetailValidationState();
                    const detailsReady = !detailValidationState.field;
                    const hasSelectedQuestions = selectedQuestionIds.size > 0;
                    const questionStepReason = !detailsReady
                        ? (detailValidationState.message || 'Lengkapi detail exam terlebih dahulu sebelum lanjut ke Pilih Soal.')
                        : '';
                    const submitReason = !detailsReady
                        ? questionStepReason
                        : (!hasSelectedQuestions ? 'Pilih minimal 1 soal sebelum menyimpan exam.' : '');

                    return {
                        detailsReady,
                        hasSelectedQuestions,
                        questionStepReason,
                        submitReason,
                        canOpenQuestionStep: detailsReady && !isExamSaveRunning,
                        canSubmitExam: detailsReady && hasSelectedQuestions && !isExamSaveRunning,
                    };
                }

                function syncExamBuilderActionState() {
                    const actionState = getExamBuilderActionState();
                    syncExamBuilderSummary();
                    renderSelectedSidebar();

                    setButtonDisabledState(questionTabButton, !actionState.canOpenQuestionStep, actionState.questionStepReason);
                    setButtonDisabledState(nextQuestionButton, !actionState.canOpenQuestionStep, actionState.questionStepReason);
                    submitExamButtons.forEach((button) => {
                        setButtonDisabledState(button, !actionState.canSubmitExam, actionState.submitReason);
                    });

                    if (questionSubmitHelp) {
                        if (!actionState.detailsReady) {
                            questionSubmitHelp.textContent = actionState.questionStepReason;
                        } else if (!actionState.hasSelectedQuestions) {
                            questionSubmitHelp.textContent = 'Pilih minimal 1 soal untuk mengaktifkan tombol Create/Update Exam.';
                        } else {
                            questionSubmitHelp.textContent = defaultQuestionSubmitHelpText;
                        }
                    }

                    if (detailValidationNotice && !detailValidationNotice.hidden) {
                        if (actionState.detailsReady) {
                            setExamDetailValidationNotice(false);
                        } else {
                            setExamDetailValidationNotice(true, actionState.questionStepReason);
                        }
                    }

                    getQuestionPickButtons().forEach((button) => {
                        const isSelected = button.classList.contains('is-selected');
                        setButtonDisabledState(button, isExamSaveRunning || isSelected, isSelected ? 'Soal ini sudah masuk ke draft exam.' : '');
                    });
                    const selectVisibleBtn = getQuestionSelectVisibleBtn();
                    const visibleCatalogRows = getVisibleRows();
                    setButtonDisabledState(
                        selectVisibleBtn,
                        isExamSaveRunning || visibleCatalogRows.length === 0,
                        visibleCatalogRows.length === 0 ? 'Tidak ada soal tersisa pada halaman katalog ini.' : ''
                    );
                    if (selectedSidebarList) {
                        selectedSidebarList.querySelectorAll('.cbt-exam-selected-item-action').forEach((button) => {
                            setButtonDisabledState(button, isExamSaveRunning);
                        });
                    }
                    if (selectionConfirmSubmit) {
                        setButtonDisabledState(selectionConfirmSubmit, isExamSaveRunning);
                    }
                    if (selectionConfirmCancel) {
                        setButtonDisabledState(selectionConfirmCancel, isExamSaveRunning);
                    }
                }

                function collectFormDraft() {
                    return {
                        subjectId: subjectInput ? String(subjectInput.value || '') : '',
                        title: titleInput ? String(titleInput.value || '') : '',
                        duration: durationInput ? String(durationInput.value || '') : '',
                        status: statusInput ? String(statusInput.value || '') : '',
                        startsAt: startsAtInput ? String(startsAtInput.value || '') : '',
                        endsAt: endsAtInput ? String(endsAtInput.value || '') : '',
                        randomize: !!(randomizeInput && randomizeInput.checked),
                        description: descriptionInput ? String(descriptionInput.value || '') : '',
                        targetKelas: kelasCheckboxes
                            .filter((checkbox) => checkbox.checked)
                            .map((checkbox) => String(checkbox.value || ''))
                            .filter((value) => value !== ''),
                    };
                }

                function applyFormDraft(draft) {
                    if (!draft || typeof draft !== 'object') {
                        return;
                    }

                    if (subjectInput && typeof draft.subjectId === 'string' && draft.subjectId !== '') {
                        subjectInput.value = draft.subjectId;
                    }
                    if (titleInput && typeof draft.title === 'string') {
                        titleInput.value = draft.title;
                    }
                    if (durationInput && typeof draft.duration === 'string' && draft.duration !== '') {
                        durationInput.value = draft.duration;
                    }
                    if (statusInput && typeof draft.status === 'string' && draft.status !== '') {
                        statusInput.value = draft.status;
                    }
                    if (startsAtInput && typeof draft.startsAt === 'string') {
                        startsAtInput.value = draft.startsAt;
                    }
                    if (endsAtInput && typeof draft.endsAt === 'string') {
                        endsAtInput.value = draft.endsAt;
                    }
                    if (randomizeInput) {
                        randomizeInput.checked = !!draft.randomize;
                    }
                    if (descriptionInput && typeof draft.description === 'string') {
                        descriptionInput.value = draft.description;
                    }
                    if (Array.isArray(draft.targetKelas) && kelasCheckboxes.length > 0) {
                        const selectedKelas = new Set(draft.targetKelas.map((value) => String(value || '')));
                        kelasCheckboxes.forEach((checkbox) => {
                            checkbox.checked = selectedKelas.has(String(checkbox.value || ''));
                        });
                    }
                }

                function saveBuilderState() {
                    if (builderStateKey === '' || typeof window.sessionStorage === 'undefined') {
                        return;
                    }
                    try {
                        window.sessionStorage.setItem(builderStateKey, JSON.stringify({
                            contextFingerprint: currentBuilderContextFingerprint,
                            selectedQuestionIds: Array.from(selectedQuestionIds),
                            draft: collectFormDraft(),
                        }));
                    } catch (error) {
                        // Ignore storage quota or access errors in admin UI.
                    }
                }

                function clearBuilderStateLocally() {
                    if (typeof window.sessionStorage === 'undefined') {
                        return;
                    }

                    try {
                        if (builderStateKey !== '') {
                            window.sessionStorage.removeItem(builderStateKey);
                        }
                        if (Array.isArray(resetStateKeys)) {
                            resetStateKeys.forEach((key) => {
                                const normalizedKey = String(key || '').trim();
                                if (normalizedKey !== '') {
                                    window.sessionStorage.removeItem(normalizedKey);
                                }
                            });
                        }
                    } catch (error) {
                        // Ignore storage errors in admin UI.
                    }
                }

                function saveSelectedStateToServer() {
                    if (builderStateKey === '' || ajaxNonce === '' || typeof window.fetch !== 'function' || typeof window.ajaxurl === 'undefined') {
                        return Promise.resolve();
                    }

                    const payload = new URLSearchParams();
                    payload.set('action', 'cbt_sync_exam_builder_selection');
                    payload.set('nonce', ajaxNonce);
                    payload.set('builder_state_key', builderStateKey);
                    Array.from(selectedQuestionIds).forEach((questionId) => {
                        payload.append('selected_question_ids[]', String(questionId));
                    });

                    return window.fetch(window.ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        },
                        body: payload.toString(),
                    }).catch(() => null);
                }

                function clearSelectedStateOnServer() {
                    if (builderStateKey === '' || ajaxNonce === '' || typeof window.fetch !== 'function' || typeof window.ajaxurl === 'undefined') {
                        return Promise.resolve();
                    }

                    const payload = new URLSearchParams();
                    payload.set('action', 'cbt_clear_exam_builder_selection');
                    payload.set('nonce', ajaxNonce);
                    payload.set('builder_state_key', builderStateKey);

                    return window.fetch(window.ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        },
                        body: payload.toString(),
                    }).catch(() => null);
                }

                function queueSelectedStateSync() {
                    if (selectionSyncTimer) {
                        window.clearTimeout(selectionSyncTimer);
                    }
                    selectionSyncTimer = window.setTimeout(() => {
                        selectionSyncTimer = null;
                        saveSelectedStateToServer();
                    }, 180);
                }

                function flushSelectedStateSync() {
                    if (selectionSyncTimer) {
                        window.clearTimeout(selectionSyncTimer);
                        selectionSyncTimer = null;
                    }

                    return saveSelectedStateToServer();
                }

                function syncSelectedQuestionInputsToForm() {
                    if (!form) {
                        return;
                    }

                    form.querySelectorAll('.cbt-dynamic-source-question-input').forEach((input) => input.remove());
                    selectedQuestionIds.forEach((questionId) => {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'source_question_ids[]';
                        hiddenInput.value = String(questionId);
                        hiddenInput.className = 'cbt-dynamic-source-question-input';
                        form.appendChild(hiddenInput);
                    });
                }

                function buildExamSaveFormData(actionName) {
                    if (!form) {
                        return null;
                    }

                    const formData = new window.FormData(form);
                    formData.set('action', actionName);
                    formData.set('nonce', ajaxNonce);
                    formData.delete('source_question_ids[]');
                    selectedQuestionIds.forEach((questionId) => {
                        formData.append('source_question_ids[]', String(questionId));
                    });

                    return formData;
                }

                function toggleExamSaveOverlay(isVisible) {
                    if (!saveProgressOverlay) {
                        return;
                    }

                    saveProgressOverlay.hidden = !isVisible;
                    saveProgressOverlay.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
                    saveProgressOverlay.style.display = isVisible ? 'flex' : 'none';
                }

                toggleExamSaveOverlay(false);

                function toggleSnapshotCleanOverlay(isVisible) {
                    if (!cleanProgressOverlay) {
                        return;
                    }

                    cleanProgressOverlay.hidden = !isVisible;
                    cleanProgressOverlay.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
                    cleanProgressOverlay.style.display = isVisible ? 'flex' : 'none';
                }

                toggleSnapshotCleanOverlay(false);

                function toggleBulkPreflightOverlay(isVisible) {
                    if (!bulkProgressOverlay) {
                        return;
                    }

                    bulkProgressOverlay.hidden = !isVisible;
                    bulkProgressOverlay.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
                    bulkProgressOverlay.style.display = isVisible ? 'flex' : 'none';
                }

                toggleBulkPreflightOverlay(false);

                function navigateBuilderPanel(targetId) {
                    const normalizedTargetId = String(targetId || '');
                    if (normalizedTargetId === '') {
                        return;
                    }

                    saveBuilderState();

                    if (normalizedTargetId === 'cbt-exam-questions-panel' && !isQuestionCatalogLoaded()) {
                        navigateQuestionCatalog(false);
                        return;
                    }

                    if (typeof activatePageTab === 'function') {
                        activatePageTab('cbt-exam-builder-panel');
                    }
                    if (typeof activateBuilderTab === 'function') {
                        const activated = activateBuilderTab(normalizedTargetId);
                        if (activated === false) {
                            return;
                        }
                    }

                    const targetPanel = document.getElementById(normalizedTargetId);
                    if (targetPanel && typeof targetPanel.scrollIntoView === 'function') {
                        window.setTimeout(() => {
                            targetPanel.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start',
                            });
                        }, 20);
                    }
                }

                function updateExamSaveProgressUi(payload) {
                    const progressData = payload && typeof payload === 'object' ? payload : {};
                    const isUpdateMode = !!(form && form.querySelector('input[name="id"]') && String(form.querySelector('input[name="id"]').value || '') !== '0');
                    const titleText = isUpdateMode ? 'Update Exam Berjalan' : 'Create Exam Berjalan';
                    const messageText = typeof progressData.message === 'string' && progressData.message !== ''
                        ? progressData.message
                        : 'Menyiapkan proses sinkronisasi exam.';
                    const phaseText = typeof progressData.phase_label === 'string' && progressData.phase_label !== ''
                        ? progressData.phase_label
                        : 'Menyiapkan proses';
                    const statsText = typeof progressData.stats === 'string' ? progressData.stats : '';
                    const percentValue = Number.isFinite(Number(progressData.percent)) ? Math.max(0, Math.min(100, Number(progressData.percent))) : 0;

                    if (saveProgressTitle) {
                        saveProgressTitle.textContent = titleText;
                    }
                    if (saveProgressMessage) {
                        saveProgressMessage.textContent = messageText;
                    }
                    if (saveProgressPhase) {
                        saveProgressPhase.textContent = phaseText;
                    }
                    if (saveProgressStats) {
                        saveProgressStats.textContent = statsText;
                    }
                    if (saveProgressFill) {
                        saveProgressFill.style.width = `${percentValue}%`;
                    }
                    if (saveProgressPercent) {
                        saveProgressPercent.textContent = `${Math.round(percentValue)}%`;
                    }
                }

                function updateSnapshotCleanProgressUi(payload) {
                    const progressData = payload && typeof payload === 'object' ? payload : {};
                    const titleText = typeof progressData.title === 'string' && progressData.title !== ''
                        ? progressData.title
                        : 'Membersihkan Snapshot Pra Ujian';
                    const messageText = typeof progressData.message === 'string' && progressData.message !== ''
                        ? progressData.message
                        : 'Menyiapkan proses clean snapshot untuk exam terpilih.';
                    const phaseText = typeof progressData.phase === 'string' && progressData.phase !== ''
                        ? progressData.phase
                        : 'Menyiapkan proses';
                    const statsText = typeof progressData.stats === 'string' ? progressData.stats : '';
                    const percentValue = Number.isFinite(Number(progressData.percent)) ? Math.max(0, Math.min(100, Number(progressData.percent))) : 0;

                    if (cleanProgressTitle) {
                        cleanProgressTitle.textContent = titleText;
                    }
                    if (cleanProgressMessage) {
                        cleanProgressMessage.textContent = messageText;
                    }
                    if (cleanProgressPhase) {
                        cleanProgressPhase.textContent = phaseText;
                    }
                    if (cleanProgressStats) {
                        cleanProgressStats.textContent = statsText;
                    }
                    if (cleanProgressFill) {
                        cleanProgressFill.style.width = `${percentValue}%`;
                    }
                    if (cleanProgressPercent) {
                        cleanProgressPercent.textContent = `${Math.round(percentValue)}%`;
                    }
                }

                function updateBulkPreflightProgressUi(payload) {
                    const progressData = payload && typeof payload === 'object' ? payload : {};
                    const titleText = typeof progressData.title === 'string' && progressData.title !== ''
                        ? progressData.title
                        : 'Menjalankan Bulk One-Click';
                    const messageText = typeof progressData.message === 'string' && progressData.message !== ''
                        ? progressData.message
                        : 'Menyiapkan antrean bulk pra ujian untuk exam terpilih.';
                    const phaseText = typeof progressData.phase === 'string' && progressData.phase !== ''
                        ? progressData.phase
                        : 'Menyiapkan proses';
                    const statsText = typeof progressData.stats === 'string' ? progressData.stats : '';
                    const percentValue = Number.isFinite(Number(progressData.percent)) ? Math.max(0, Math.min(100, Number(progressData.percent))) : 0;

                    if (bulkProgressTitle) {
                        bulkProgressTitle.textContent = titleText;
                    }
                    if (bulkProgressMessage) {
                        bulkProgressMessage.textContent = messageText;
                    }
                    if (bulkProgressPhase) {
                        bulkProgressPhase.textContent = phaseText;
                    }
                    if (bulkProgressStats) {
                        bulkProgressStats.textContent = statsText;
                    }
                    if (bulkProgressFill) {
                        bulkProgressFill.style.width = `${percentValue}%`;
                    }
                    if (bulkProgressPercent) {
                        bulkProgressPercent.textContent = `${Math.round(percentValue)}%`;
                    }
                }

                function startSnapshotCleanProgress(formElement) {
                    if (!formElement || !cleanProgressOverlay) {
                        return;
                    }

                    clearSnapshotCleanProgressTimer();
                    isSnapshotCleanRunning = true;
                    isPageNavigating = true;
                    clearSnapshotAutoRefreshTimer();

                    const examTitle = String(formElement.getAttribute('data-cbt-clean-exam-title') || '').trim() || 'Exam terpilih';
                    const targetCountValue = Number.parseInt(String(formElement.getAttribute('data-cbt-clean-target-count') || '0'), 10);
                    const targetCount = Number.isInteger(targetCountValue) ? Math.max(0, targetCountValue) : 0;
                    const statsLabel = targetCount > 0
                        ? `${targetCount} siswa target · snapshot siswa dipertahankan`
                        : 'Clean aman per exam';
                    const steps = [
                        {
                            percent: 8,
                            phase: 'Menyiapkan clean',
                            message: `Memvalidasi exam dan memastikan clean aman untuk ${examTitle}.`,
                        },
                        {
                            percent: 24,
                            phase: 'Menghentikan proses warm',
                            message: 'Menghentikan one-click atau auto-warm yang masih aktif untuk exam ini sebelum snapshot dibersihkan.',
                        },
                        {
                            percent: 48,
                            phase: 'Membersihkan snapshot exam',
                            message: 'Menghapus Snapshot Soal, Start Snapshot, dan Submission Context untuk exam terpilih.',
                        },
                        {
                            percent: 74,
                            phase: 'Menjaga snapshot siswa',
                            message: 'Snapshot Profil, Login, dan Availability siswa tetap dipertahankan agar exam lain tidak ikut terdampak.',
                        },
                        {
                            percent: 92,
                            phase: 'Mereset state exam',
                            message: 'Mereset state one-click atau auto-warm untuk exam ini bila sebelumnya aktif.',
                        },
                    ];

                    toggleSnapshotCleanOverlay(true);
                    updateSnapshotCleanProgressUi({
                        title: 'Membersihkan Snapshot Pra Ujian',
                        message: steps[0].message,
                        phase: steps[0].phase,
                        percent: steps[0].percent,
                        stats: statsLabel,
                    });

                    const submitButton = formElement.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    let stepIndex = 0;
                    snapshotCleanProgressTimer = window.setInterval(() => {
                        if (stepIndex >= steps.length - 1) {
                            clearSnapshotCleanProgressTimer();
                            return;
                        }

                        stepIndex += 1;
                        const step = steps[stepIndex];
                        updateSnapshotCleanProgressUi({
                            title: 'Membersihkan Snapshot Pra Ujian',
                            message: step.message,
                            phase: step.phase,
                            percent: step.percent,
                            stats: statsLabel,
                        });
                    }, 720);
                }

                function startAdaptiveFinalizeProgress(formElement) {
                    if (!formElement || !cleanProgressOverlay) {
                        return;
                    }

                    clearSnapshotCleanProgressTimer();
                    isAdaptiveFinalizeRunning = true;
                    isPageNavigating = true;
                    clearSnapshotAutoRefreshTimer();

                    const activeAttemptValue = Number.parseInt(String(formElement.getAttribute('data-cbt-adaptive-active-attempt-count') || '0'), 10);
                    const activeAttemptCount = Number.isInteger(activeAttemptValue) ? Math.max(0, activeAttemptValue) : 0;
                    const statsLabel = activeAttemptCount > 0
                        ? `${activeAttemptCount} attempt aktif terdeteksi · hanya expired yang ditutup`
                        : 'Tidak ada attempt aktif pada indikator Adaptive Load';
                    const steps = [
                        {
                            percent: 10,
                            phase: 'Menyiapkan pemeriksaan',
                            message: 'Memeriksa attempt in_progress yang boleh ditutup dari panel Adaptive Load.',
                        },
                        {
                            percent: 32,
                            phase: 'Memfilter attempt aman',
                            message: 'Attempt yang masih punya sisa waktu atau masih berada di window jadwal tidak akan disentuh.',
                        },
                        {
                            percent: 58,
                            phase: 'Menutup attempt expired',
                            message: 'Server menutup attempt yang expired atau sudah berada di luar window jadwal.',
                        },
                        {
                            percent: 82,
                            phase: 'Menghitung ulang indikator',
                            message: 'Adaptive Load akan dievaluasi ulang setelah cleanup selesai.',
                        },
                        {
                            percent: 94,
                            phase: 'Memuat ulang panel',
                            message: 'Halaman akan kembali ke Snapshot dengan indikator terbaru.',
                        },
                    ];

                    toggleSnapshotCleanOverlay(true);
                    updateSnapshotCleanProgressUi({
                        title: 'Menutup Attempt Expired',
                        message: steps[0].message,
                        phase: steps[0].phase,
                        percent: steps[0].percent,
                        stats: statsLabel,
                    });

                    const submitButton = formElement.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Memproses...';
                    }

                    let stepIndex = 0;
                    snapshotCleanProgressTimer = window.setInterval(() => {
                        if (stepIndex >= steps.length - 1) {
                            clearSnapshotCleanProgressTimer();
                            return;
                        }

                        stepIndex += 1;
                        const step = steps[stepIndex];
                        updateSnapshotCleanProgressUi({
                            title: 'Menutup Attempt Expired',
                            message: step.message,
                            phase: step.phase,
                            percent: step.percent,
                            stats: statsLabel,
                        });
                    }, 700);
                }

                function startBulkPreflightProgress(formElement) {
                    if (!formElement || !bulkProgressOverlay) {
                        return;
                    }

                    clearBulkProgressTimer();
                    isBulkPreflightRunning = true;
                    isPageNavigating = true;
                    clearSnapshotAutoRefreshTimer();

                    const selectedTotalValue = Number.parseInt(String(formElement.getAttribute('data-cbt-bulk-selected-total') || '0'), 10);
                    const queuedTotalValue = Number.parseInt(String(formElement.getAttribute('data-cbt-bulk-queued-total') || '0'), 10);
                    const completedTotalValue = Number.parseInt(String(formElement.getAttribute('data-cbt-bulk-completed-total') || '0'), 10);
                    const selectedTotal = Number.isInteger(selectedTotalValue) ? Math.max(0, selectedTotalValue) : 0;
                    const queuedTotal = Number.isInteger(queuedTotalValue) ? Math.max(0, queuedTotalValue) : 0;
                    const completedTotal = Number.isInteger(completedTotalValue) ? Math.max(0, completedTotalValue) : 0;
                    const statsLabel = `${selectedTotal} exam dipilih · antrean ${queuedTotal} · selesai ${completedTotal}`;
                    const steps = [
                        {
                            percent: 10,
                            phase: 'Memvalidasi pilihan',
                            message: 'Memastikan daftar exam yang dipilih siap dimasukkan ke bulk preflight.',
                        },
                        {
                            percent: 28,
                            phase: 'Menyusun antrean',
                            message: 'Menyusun urutan bulk one-click agar exam aktif tetap jalan dan exam lain masuk antrean dengan aman.',
                        },
                        {
                            percent: 52,
                            phase: 'Menjalankan layer lokal',
                            message: 'Snapshot Soal, Start Snapshot, dan Submission Context akan diprioritaskan lebih dulu untuk exam yang bisa langsung diproses.',
                        },
                        {
                            percent: 78,
                            phase: 'Menjaga runner global',
                            message: 'Snapshot Profil, Login, dan Availability tetap dijalankan serial agar tidak bentrok antar exam.',
                        },
                        {
                            percent: 94,
                            phase: 'Mengarahkan ke monitor',
                            message: 'Halaman akan kembali ke panel bulk agar progres antrean bisa dipantau langsung.',
                        },
                    ];

                    toggleBulkPreflightOverlay(true);
                    updateBulkPreflightProgressUi({
                        title: 'Menjalankan Bulk One-Click',
                        message: steps[0].message,
                        phase: steps[0].phase,
                        percent: steps[0].percent,
                        stats: statsLabel,
                    });

                    const submitButton = formElement.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    let stepIndex = 0;
                    bulkProgressTimer = window.setInterval(() => {
                        if (stepIndex >= steps.length - 1) {
                            clearBulkProgressTimer();
                            return;
                        }

                        stepIndex += 1;
                        const step = steps[stepIndex];
                        updateBulkPreflightProgressUi({
                            title: 'Menjalankan Bulk One-Click',
                            message: step.message,
                            phase: step.phase,
                            percent: step.percent,
                            stats: statsLabel,
                        });
                    }, 760);
                }

                function fallbackSubmitExamForm() {
                    if (!form) {
                        return;
                    }

                    syncSelectedQuestionInputsToForm();
                    saveBuilderState();
                    isFinalFormSubmit = true;
                    form.submit();
                }

                function handleExamSaveProgressError(message) {
                    toggleExamSaveOverlay(false);
                    isExamSaveRunning = false;
                    syncExamBuilderActionState();
                    window.alert(message);
                }

                async function postExamSaveProgress(actionName, extraParams = {}) {
                    if (!form || ajaxNonce === '' || typeof window.fetch !== 'function' || typeof window.ajaxurl === 'undefined' || typeof window.FormData === 'undefined') {
                        throw new Error('Progress update exam tidak didukung browser ini.');
                    }

                    let requestBody;
                    const requestHeaders = {};
                    if (actionName === 'cbt_continue_exam_save_progress') {
                        const params = new URLSearchParams();
                        params.set('action', actionName);
                        params.set('nonce', ajaxNonce);
                        Object.entries(extraParams).forEach(([key, value]) => {
                            params.set(key, String(value));
                        });
                        requestBody = params.toString();
                        requestHeaders['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
                    } else {
                        const formData = buildExamSaveFormData(actionName);
                        if (!formData) {
                            throw new Error('Form exam tidak ditemukan.');
                        }
                        Object.entries(extraParams).forEach(([key, value]) => {
                            formData.set(key, String(value));
                        });
                        requestBody = formData;
                    }

                    const response = await window.fetch(window.ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: requestHeaders,
                        body: requestBody,
                    });
                    const result = await response.json().catch(() => null);
                    if (!result || typeof result !== 'object') {
                        throw new Error('Respons progress update exam tidak valid.');
                    }
                    if (!result.success) {
                        const errorMessage = result.data && typeof result.data.message === 'string' && result.data.message !== ''
                            ? result.data.message
                            : 'Progress update exam gagal diproses.';
                        throw new Error(errorMessage);
                    }

                    return result.data && typeof result.data === 'object' ? result.data : {};
                }

                async function continueExamSaveProgress(token) {
                    let progressToken = String(token || '');
                    while (progressToken !== '') {
                        await new Promise((resolve) => window.setTimeout(resolve, 320));
                        const payload = await postExamSaveProgress('cbt_continue_exam_save_progress', { token: progressToken });
                        updateExamSaveProgressUi(payload);
                        if (payload && payload.complete) {
                            isExamSaveRunning = false;
                            syncExamBuilderActionState();
                            const redirectUrl = typeof payload.redirect_url === 'string' ? payload.redirect_url : '';
                            if (redirectUrl !== '') {
                                window.location.href = redirectUrl;
                            } else {
                                toggleExamSaveOverlay(false);
                            }
                            return true;
                        }

                        progressToken = typeof payload.token === 'string' ? payload.token : progressToken;
                    }

                    throw new Error('Token progress update exam tidak valid.');
                }

                async function startExamSaveProgress() {
                    const payload = await postExamSaveProgress('cbt_start_exam_save_progress');
                    updateExamSaveProgressUi(payload);
                    if (payload && payload.complete) {
                        isExamSaveRunning = false;
                        syncExamBuilderActionState();
                        const redirectUrl = typeof payload.redirect_url === 'string' ? payload.redirect_url : '';
                        if (redirectUrl !== '') {
                            window.location.href = redirectUrl;
                        } else {
                            toggleExamSaveOverlay(false);
                        }
                        return true;
                    }

                    const token = typeof payload.token === 'string' ? payload.token : '';
                    if (token === '') {
                        throw new Error('Token progress update exam tidak tersedia.');
                    }

                    return continueExamSaveProgress(token);
                }

                if (initialBuilderState && initialBuilderState.draft) {
                    applyFormDraft(initialBuilderState.draft);
                }

                function openQuestionQuickView(questionId, fallbackTitle = '') {
                    if (!quickViewModal || !quickViewBody) {
                        return;
                    }

                    const normalizedId = parseInt(String(questionId || ''), 10);
                    if (!Number.isInteger(normalizedId) || normalizedId <= 0) {
                        return;
                    }

                    const currentPanel = getQuestionCatalogPanel();
                    const panelSource = currentPanel ? currentPanel.querySelector(`#cbt-quick-view-content-${normalizedId}`) : null;
                    const previewHtml = panelSource
                        ? panelSource.innerHTML
                        : String(questionPreviewHtmlMap[normalizedId] || '');

                    if (previewHtml.trim() === '') {
                        return;
                    }

                    if (quickViewTitle) {
                        quickViewTitle.textContent = fallbackTitle !== ''
                            ? fallbackTitle
                            : `Preview Soal #${normalizedId}`;
                    }
                    quickViewBody.innerHTML = previewHtml;
                    quickViewModal.style.display = 'block';
                }

                function closeQuickView() {
                    if (!quickViewModal) return;
                    quickViewModal.style.display = 'none';
                    if (quickViewBody) {
                        quickViewBody.innerHTML = '';
                    }
                }

                if (quickViewModal && quickViewBody) {
                    if (quickViewCloseTop) {
                        quickViewCloseTop.addEventListener('click', closeQuickView);
                    }
                    if (quickViewCloseBottom) {
                        quickViewCloseBottom.addEventListener('click', closeQuickView);
                    }
                    quickViewModal.addEventListener('click', (event) => {
                        if (event.target === quickViewModal) {
                            closeQuickView();
                        }
                    });
                }

                if (selectionConfirmModal) {
                    if (selectionConfirmCancel) {
                        selectionConfirmCancel.addEventListener('click', closeSelectionConfirmModal);
                    }
                    if (selectionConfirmSubmit) {
                        selectionConfirmSubmit.addEventListener('click', () => {
                            if (typeof pendingSelectionConfirmAction === 'function') {
                                pendingSelectionConfirmAction();
                            }
                        });
                    }
                    selectionConfirmModal.addEventListener('click', (event) => {
                        const target = event.target instanceof HTMLElement ? event.target : null;
                        if (!target) {
                            return;
                        }
                        if (target === selectionConfirmModal || target.hasAttribute('data-cbt-modal-close')) {
                            closeSelectionConfirmModal();
                        }
                    });
                }

                const kelasCheckAllBtn = document.getElementById('cbt-kelas-check-all');
                const kelasUncheckAllBtn = document.getElementById('cbt-kelas-uncheck-all');
                if (kelasCheckAllBtn && kelasCheckboxes.length > 0) {
                    kelasCheckAllBtn.addEventListener('click', () => {
                        kelasCheckboxes.forEach((item) => {
                            item.checked = true;
                        });
                        saveBuilderState();
                        syncExamBuilderActionState();
                    });
                }
                if (kelasUncheckAllBtn && kelasCheckboxes.length > 0) {
                    kelasUncheckAllBtn.addEventListener('click', () => {
                        kelasCheckboxes.forEach((item) => {
                            item.checked = false;
                        });
                        saveBuilderState();
                        syncExamBuilderActionState();
                    });
                }

                function syncQuestionCheckboxesFromState() {
                    getQuestionRows().forEach((row) => {
                        const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                        const pickButton = row.querySelector('.cbt-exam-question-pick-btn');
                        if (!checkbox) {
                            return;
                        }
                        const questionId = parseInt(String(checkbox.value || ''), 10);
                        const isSelected = Number.isInteger(questionId) && selectedQuestionIds.has(questionId);
                        const isPendingRemove = initialSelectedQuestionIdSet.has(questionId) && !isSelected;
                        checkbox.checked = isSelected;
                        row.classList.toggle('is-selected', isSelected);
                        row.classList.toggle('is-pending-remove', isPendingRemove);
                        if (pickButton) {
                            pickButton.textContent = isSelected
                                ? 'Sudah Dipilih'
                                : (isPendingRemove ? 'Batalkan Lepas' : 'Tambah');
                            pickButton.classList.toggle('is-selected', isSelected);
                            setButtonDisabledState(
                                pickButton,
                                isExamSaveRunning || isSelected,
                                isSelected ? 'Soal ini sudah masuk ke draft exam.' : ''
                            );
                        }
                    });

                    syncQuestionModeVisibility();
                }

                function syncQuestionModeVisibility() {
                    getQuestionRows().forEach((row) => {
                        const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                        const questionId = parseInt(String(checkbox?.value || ''), 10);
                        const isSelected = Number.isInteger(questionId) && selectedQuestionIds.has(questionId);
                        row.style.display = isSelected ? 'none' : '';
                    });
                }

                function getVisibleRows() {
                    return getQuestionRows().filter((row) => row.style.display !== 'none');
                }

                function syncCounter() {
                    syncQuestionCheckboxesFromState();

                    const visibleRows = getVisibleRows();
                    const selectedCountEl = getQuestionSelectedCountEl();

                    if (selectedCountEl) {
                        selectedCountEl.textContent = `${selectedQuestionIds.size} soal di draft | ${visibleRows.length} terlihat di katalog`;
                    }

                    syncExamBuilderActionState();
                }

                function upsertQuestionSelection(questionId, shouldSelect) {
                    if (!Number.isInteger(questionId) || questionId <= 0) {
                        return;
                    }
                    if (shouldSelect) {
                        selectedQuestionIds.add(questionId);
                    } else {
                        selectedQuestionIds.delete(questionId);
                    }
                }

                function bindQuestionCatalogSelection(panel = getQuestionCatalogPanel()) {
                    if (!panel) {
                        return;
                    }

                    hydrateQuestionDetailsFromPanel(panel);

                    getQuestionPickButtons(panel).forEach((button) => {
                        if (!button || button.dataset.cbtBound === '1') {
                            return;
                        }

                        button.dataset.cbtBound = '1';
                        button.addEventListener('click', () => {
                            const questionId = parseInt(String(button.getAttribute('data-question-id') || ''), 10);
                            if (!Number.isInteger(questionId) || questionId <= 0) {
                                return;
                            }
                            upsertQuestionSelection(questionId, true);
                            syncCounter();
                            saveBuilderState();
                            queueSelectedStateSync();
                        });
                    });

                    const selectVisibleBtn = getQuestionSelectVisibleBtn(panel);
                    if (selectVisibleBtn && selectVisibleBtn.dataset.cbtBound !== '1') {
                        selectVisibleBtn.dataset.cbtBound = '1';
                        selectVisibleBtn.addEventListener('click', async () => {
                            const visibleRows = getVisibleRows();
                            let addedCount = 0;
                            visibleRows.forEach((row) => {
                                const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                                if (checkbox) {
                                    const questionId = parseInt(String(checkbox.value || ''), 10);
                                    if (Number.isInteger(questionId) && questionId > 0 && !selectedQuestionIds.has(questionId)) {
                                        upsertQuestionSelection(questionId, true);
                                        addedCount += 1;
                                    }
                                }
                            });

                            if (addedCount <= 0) {
                                syncCounter();
                                return;
                            }

                            syncCounter();
                            saveBuilderState();
                            const nextUrl = buildQuestionCatalogUrl(false, panel, { preservePage: true });
                            if (supportsQuestionCatalogPartialRefresh) {
                                await refreshQuestionCatalogPanel(nextUrl);
                                return;
                            }
                            await navigateToQuestionUrl(nextUrl);
                        });
                    }

                    if (selectedSidebarList && selectedSidebarList.dataset.cbtBound !== '1') {
                        selectedSidebarList.dataset.cbtBound = '1';
                        selectedSidebarList.addEventListener('click', (event) => {
                            const previewTarget = event.target instanceof HTMLElement
                                ? event.target.closest('[data-sidebar-preview]')
                                : null;
                            if (previewTarget) {
                                const questionId = parseInt(String(previewTarget.getAttribute('data-sidebar-preview') || ''), 10);
                                if (Number.isInteger(questionId) && questionId > 0) {
                                    openQuestionQuickView(questionId, `Preview Soal #${questionId}`);
                                }
                                return;
                            }

                            const target = event.target instanceof HTMLElement
                                ? event.target.closest('[data-sidebar-action][data-question-id]')
                                : null;
                            if (!target) {
                                return;
                            }

                            const questionId = parseInt(String(target.getAttribute('data-question-id') || ''), 10);
                            if (!Number.isInteger(questionId) || questionId <= 0) {
                                return;
                            }

                            const action = String(target.getAttribute('data-sidebar-action') || '');
                            if (action === 'existing') {
                                openSelectionConfirmModal(
                                    'Soal hanya dilepas dari draft exam saat ini. Perubahan baru benar-benar berlaku setelah Anda menyimpan exam.',
                                    () => {
                                        upsertQuestionSelection(questionId, false);
                                        syncCounter();
                                        saveBuilderState();
                                        queueSelectedStateSync();
                                        closeSelectionConfirmModal();
                                    }
                                );
                                return;
                            }

                            if (action === 'new') {
                                upsertQuestionSelection(questionId, false);
                            } else if (action === 'removed') {
                                upsertQuestionSelection(questionId, true);
                            } else {
                                return;
                            }

                            syncCounter();
                            saveBuilderState();
                            queueSelectedStateSync();
                        });
                    }

                    if (selectedSidebarSearchInput && selectedSidebarSearchInput.dataset.cbtBound !== '1') {
                        selectedSidebarSearchInput.dataset.cbtBound = '1';
                        selectedSidebarSearchInput.addEventListener('input', () => {
                            selectedSidebarSearchTerm = String(selectedSidebarSearchInput.value || '');
                            renderSelectedSidebar();
                        });
                    }
                }

                function bindQuestionQuickView(panel = getQuestionCatalogPanel()) {
                    if (!panel || !quickViewModal || !quickViewBody) {
                        return;
                    }

                    getQuestionQuickViewButtons(panel).forEach((button) => {
                        if (!button || button.dataset.cbtBound === '1') {
                            return;
                        }

                        button.dataset.cbtBound = '1';
                        button.addEventListener('click', () => {
                            const qid = String(button.getAttribute('data-qid') || '');
                            openQuestionQuickView(qid, `Preview Soal #${qid}`);
                        });
                    });
                }

                const draftWatchers = [
                    subjectInput,
                    titleInput,
                    durationInput,
                    statusInput,
                    startsAtInput,
                    endsAtInput,
                    randomizeInput,
                    descriptionInput,
                ];
                draftWatchers.forEach((field) => {
                    if (!field) {
                        return;
                    }
                    const eventName = field.tagName === 'SELECT' || field.type === 'checkbox' ? 'change' : 'input';
                    field.addEventListener(eventName, () => {
                        saveBuilderState();
                        syncExamBuilderActionState();
                    });
                });
                kelasCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', () => {
                        saveBuilderState();
                        syncExamBuilderActionState();
                    });
                });

                const supportsQuestionCatalogPartialRefresh = !!(window.fetch && window.DOMParser);

                function buildQuestionCatalogUrl(resetFilters = false, panel = getQuestionCatalogPanel(), options = {}) {
                    const url = new URL(window.location.href);
                    const preservePage = !!(options && options.preservePage);
                    const questionMode = getQuestionMode();
                    const searchInput = getQuestionSearchInput(panel);
                    const typeFilter = getQuestionTypeFilter(panel);
                    const sourceFilter = getQuestionSourceFilter(panel);
                    const perPageFilter = getQuestionPerPageFilter(panel);

                    url.searchParams.set('page', 'cbt-exams');
                    url.searchParams.set('cbt_exam_question_panel', '1');
                    url.searchParams.delete('cbt_msg');
                    url.searchParams.delete('cbt_err');
                    url.searchParams.delete('cbt_saved_exam_id');
                    if (questionMode !== '' && questionMode !== 'catalog') {
                        url.searchParams.set('cbt_exam_question_mode', questionMode);
                    } else {
                        url.searchParams.delete('cbt_exam_question_mode');
                    }

                    if (resetFilters) {
                        url.searchParams.delete('cbt_exam_question_search');
                        url.searchParams.delete('cbt_exam_question_type');
                        url.searchParams.delete('cbt_exam_question_source');
                        url.searchParams.delete('cbt_exam_question_paged');
                        url.searchParams.delete('cbt_exam_question_per_page');
                    } else {
                        const keyword = String(searchInput?.value || '').trim();
                        const selectedType = String(typeFilter?.value || '');
                        const selectedSource = String(sourceFilter?.value || '');
                        const selectedPerPage = String(perPageFilter?.value || '50');

                        if (keyword !== '') {
                            url.searchParams.set('cbt_exam_question_search', keyword);
                        } else {
                            url.searchParams.delete('cbt_exam_question_search');
                        }

                        if (selectedType !== '') {
                            url.searchParams.set('cbt_exam_question_type', selectedType);
                        } else {
                            url.searchParams.delete('cbt_exam_question_type');
                        }

                        if (selectedSource !== '') {
                            url.searchParams.set('cbt_exam_question_source', selectedSource);
                        } else {
                            url.searchParams.delete('cbt_exam_question_source');
                        }

                        if (selectedPerPage !== '50') {
                            url.searchParams.set('cbt_exam_question_per_page', selectedPerPage);
                        } else {
                            url.searchParams.delete('cbt_exam_question_per_page');
                        }

                        if (preservePage) {
                            const currentPaged = String(new URL(window.location.href).searchParams.get('cbt_exam_question_paged') || '').trim();
                            if (currentPaged !== '') {
                                url.searchParams.set('cbt_exam_question_paged', currentPaged);
                            } else {
                                url.searchParams.delete('cbt_exam_question_paged');
                            }
                        } else {
                            url.searchParams.delete('cbt_exam_question_paged');
                        }
                    }

                    return url;
                }

                function setQuestionCatalogPanelLoading(panel, isLoading) {
                    if (!panel) {
                        return;
                    }

                    panel.classList.toggle('is-loading', isLoading);
                    panel.setAttribute('aria-busy', isLoading ? 'true' : 'false');
                }

                function captureQuestionCatalogFocus(panel) {
                    const activeElement = document.activeElement;
                    if (!panel || !activeElement || !panel.contains(activeElement) || !activeElement.id) {
                        return null;
                    }

                    const focusState = {
                        id: String(activeElement.id || '')
                    };
                    if (typeof activeElement.selectionStart === 'number') {
                        focusState.selectionStart = activeElement.selectionStart;
                        focusState.selectionEnd = typeof activeElement.selectionEnd === 'number'
                            ? activeElement.selectionEnd
                            : activeElement.selectionStart;
                    }

                    return focusState;
                }

                function restoreQuestionCatalogFocus(focusState) {
                    if (!focusState || !focusState.id) {
                        return;
                    }

                    const nextField = document.getElementById(focusState.id);
                    if (!nextField) {
                        return;
                    }

                    if (typeof nextField.focus === 'function') {
                        nextField.focus({ preventScroll: true });
                    }
                    if (
                        typeof focusState.selectionStart === 'number' &&
                        typeof nextField.setSelectionRange === 'function'
                    ) {
                        try {
                            nextField.setSelectionRange(focusState.selectionStart, focusState.selectionEnd);
                        } catch (error) {
                        }
                    }
                }

                function updateQuestionCatalogHistory(nextUrl) {
                    if (!window.history || typeof window.history.replaceState !== 'function') {
                        return;
                    }

                    window.history.replaceState({}, '', nextUrl.toString());
                }

                async function navigateToQuestionUrl(targetUrl) {
                    saveBuilderState();
                    await flushSelectedStateSync();
                    window.location.assign(targetUrl.toString());
                }

                async function navigateQuestionCatalog(resetFilters = false) {
                    await navigateToQuestionUrl(buildQuestionCatalogUrl(resetFilters));
                }

                async function refreshQuestionCatalogPanel(nextUrl) {
                    const currentPanel = getQuestionCatalogPanel();
                    if (!currentPanel || !supportsQuestionCatalogPartialRefresh) {
                        await navigateToQuestionUrl(nextUrl);
                        return;
                    }

                    questionCatalogRequestSeq += 1;
                    const requestSeq = questionCatalogRequestSeq;
                    const focusState = captureQuestionCatalogFocus(currentPanel);

                    window.clearTimeout(questionFilterTimer);
                    questionFilterTimer = 0;
                    closeQuickView();
                    saveBuilderState();
                    setQuestionCatalogPanelLoading(currentPanel, true);

                    try {
                        await flushSelectedStateSync();
                        const response = await window.fetch(nextUrl.toString(), {
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (!response.ok) {
                            await navigateToQuestionUrl(nextUrl);
                            return;
                        }

                        const html = await response.text();
                        if (requestSeq !== questionCatalogRequestSeq) {
                            return;
                        }

                        const parsed = new DOMParser().parseFromString(html, 'text/html');
                        const nextPanel = parsed.querySelector('[data-cbt-exam-question-catalog]');
                        if (!nextPanel) {
                            await navigateToQuestionUrl(nextUrl);
                            return;
                        }

                        currentPanel.setAttribute(
                            'data-cbt-question-catalog-loaded',
                            nextPanel.getAttribute('data-cbt-question-catalog-loaded') === '1' ? '1' : '0'
                        );
                        currentPanel.innerHTML = nextPanel.innerHTML;

                        const nextQuestionModeInput = parsed.getElementById('cbt-exam-builder-question-mode');
                        if (nextQuestionModeInput) {
                            setQuestionMode(String(nextQuestionModeInput.value || 'catalog'));
                        } else {
                            setQuestionMode(nextUrl.searchParams.get('cbt_exam_question_mode') || 'catalog');
                        }

                        updateQuestionCatalogHistory(nextUrl);
                        bindQuestionCatalogPanel();
                        syncExamBuilderSummary();
                        syncQuestionCheckboxesFromState();
                        syncCounter();
                        saveBuilderState();
                        restoreQuestionCatalogFocus(focusState);
                    } catch (error) {
                        if (requestSeq === questionCatalogRequestSeq) {
                            await navigateToQuestionUrl(nextUrl);
                        }
                    } finally {
                        if (requestSeq === questionCatalogRequestSeq) {
                            setQuestionCatalogPanelLoading(getQuestionCatalogPanel(), false);
                        }
                    }
                }

                function submitQuestionCatalogFilters(resetFilters = false) {
                    const nextUrl = buildQuestionCatalogUrl(resetFilters);
                    if (supportsQuestionCatalogPartialRefresh) {
                        refreshQuestionCatalogPanel(nextUrl);
                        return;
                    }

                    navigateToQuestionUrl(nextUrl);
                }

                function bindQuestionCatalogPanel(panel = getQuestionCatalogPanel()) {
                    if (!panel) {
                        return;
                    }

                    const searchInput = getQuestionSearchInput(panel);
                    const typeFilter = getQuestionTypeFilter(panel);
                    const sourceFilter = getQuestionSourceFilter(panel);
                    const perPageFilter = getQuestionPerPageFilter(panel);
                    const resetFiltersBtn = getQuestionResetButton(panel);

                    [typeFilter, sourceFilter, perPageFilter].forEach((field) => {
                        if (!field || field.dataset.cbtAutoBound === '1') {
                            return;
                        }

                        field.dataset.cbtAutoBound = '1';
                        field.addEventListener('change', () => {
                            window.clearTimeout(questionFilterTimer);
                            submitQuestionCatalogFilters(false);
                        });
                    });

                    if (searchInput && searchInput.dataset.cbtAutoBound !== '1') {
                        searchInput.dataset.cbtAutoBound = '1';
                        searchInput.addEventListener('input', () => {
                            window.clearTimeout(questionFilterTimer);
                            questionFilterTimer = window.setTimeout(() => {
                                submitQuestionCatalogFilters(false);
                            }, 280);
                        });
                        searchInput.addEventListener('search', () => {
                            window.clearTimeout(questionFilterTimer);
                            submitQuestionCatalogFilters(false);
                        });
                        searchInput.addEventListener('keydown', (event) => {
                            if (event.key !== 'Enter') {
                                return;
                            }

                            event.preventDefault();
                            window.clearTimeout(questionFilterTimer);
                            submitQuestionCatalogFilters(false);
                        });
                    }

                    if (resetFiltersBtn && resetFiltersBtn.dataset.cbtAsyncBound !== '1') {
                        resetFiltersBtn.dataset.cbtAsyncBound = '1';
                        resetFiltersBtn.addEventListener('click', (event) => {
                            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }

                            event.preventDefault();
                            window.clearTimeout(questionFilterTimer);
                            if (supportsQuestionCatalogPartialRefresh) {
                                refreshQuestionCatalogPanel(new URL(resetFiltersBtn.getAttribute('href') || window.location.href, window.location.href));
                                return;
                            }
                            navigateToQuestionUrl(new URL(resetFiltersBtn.getAttribute('href') || window.location.href, window.location.href));
                        });
                    }

                    getQuestionModeNavLinks(panel).forEach((link) => {
                        if (!link || link.dataset.cbtAsyncBound === '1') {
                            return;
                        }

                        link.dataset.cbtAsyncBound = '1';
                        link.addEventListener('click', (event) => {
                            const href = String(link.getAttribute('href') || '');
                            if (href === '' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }

                            event.preventDefault();
                            if (supportsQuestionCatalogPartialRefresh) {
                                refreshQuestionCatalogPanel(new URL(href, window.location.href));
                                return;
                            }
                            navigateToQuestionUrl(new URL(href, window.location.href));
                        });
                    });

                    getQuestionPaginationLinks(panel).forEach((link) => {
                        if (!link || link.dataset.cbtAsyncBound === '1') {
                            return;
                        }

                        link.dataset.cbtAsyncBound = '1';
                        link.addEventListener('click', (event) => {
                            const href = String(link.getAttribute('href') || '');
                            if (href === '' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }

                            event.preventDefault();
                            if (supportsQuestionCatalogPartialRefresh) {
                                refreshQuestionCatalogPanel(new URL(href, window.location.href));
                                return;
                            }
                            navigateToQuestionUrl(new URL(href, window.location.href));
                        });
                    });

                    bindQuestionCatalogSelection(panel);
                    bindQuestionQuickView(panel);
                    syncQuestionCheckboxesFromState();
                    syncCounter();
                }

                builderNavButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        navigateBuilderPanel(String(button.getAttribute('data-target-panel') || ''));
                    });
                });

                if (form) {
                    form.addEventListener('submit', (event) => {
                        if (isFinalFormSubmit || isExamSaveRunning) {
                            return;
                        }

                        event.preventDefault();

                        if (!validateExamDetailsBeforeQuestionStep()) {
                            if (typeof activatePageTab === 'function') {
                                activatePageTab('cbt-exam-builder-panel');
                            }
                            if (typeof activateBuilderTab === 'function') {
                                activateBuilderTab('cbt-exam-details-panel', { skipBeforeActivate: true });
                            }
                            syncExamBuilderActionState();
                            return;
                        }

                        if (selectedQuestionIds.size === 0) {
                            if (typeof activatePageTab === 'function') {
                                activatePageTab('cbt-exam-builder-panel');
                            }
                            if (typeof activateBuilderTab === 'function') {
                                activateBuilderTab('cbt-exam-questions-panel', { skipBeforeActivate: true });
                            }
                            window.alert('Pilih minimal 1 soal untuk exam.');
                            return;
                        }

                        saveBuilderState();
                        flushSelectedStateSync().finally(async () => {
                            const canUseProgressSave = !!(
                                saveProgressOverlay
                                && ajaxNonce !== ''
                                && typeof window.fetch === 'function'
                                && typeof window.ajaxurl !== 'undefined'
                                && typeof window.FormData !== 'undefined'
                            );

                            if (!canUseProgressSave) {
                                fallbackSubmitExamForm();
                                return;
                            }

                            isExamSaveRunning = true;
                            syncExamBuilderActionState();
                            toggleExamSaveOverlay(true);
                            updateExamSaveProgressUi({
                                phase_label: 'Menyiapkan proses',
                                message: 'Mengirim data exam ke server.',
                                percent: 2,
                                stats: `${selectedQuestionIds.size} soal dipilih`,
                            });

                            try {
                                await startExamSaveProgress();
                            } catch (error) {
                                const message = error instanceof Error && error.message !== ''
                                    ? error.message
                                    : 'Update exam gagal diproses.';
                                handleExamSaveProgressError(message);
                            }
                        });
                    });
                }

                Array.from(document.querySelectorAll('[data-cbt-preflight-clean-form="1"]')).forEach((cleanForm) => {
                    cleanForm.addEventListener('submit', (event) => {
                        if (event.defaultPrevented || isSnapshotCleanRunning) {
                            return;
                        }

                        startSnapshotCleanProgress(cleanForm);
                    });
                });

                Array.from(document.querySelectorAll('[data-cbt-adaptive-finalize-form="1"]')).forEach((adaptiveFinalizeForm) => {
                    adaptiveFinalizeForm.addEventListener('submit', (event) => {
                        if (event.defaultPrevented || isAdaptiveFinalizeRunning) {
                            return;
                        }

                        startAdaptiveFinalizeProgress(adaptiveFinalizeForm);
                    });
                });

                Array.from(document.querySelectorAll('[data-cbt-bulk-preflight-form="1"]')).forEach((bulkForm) => {
                    bulkForm.addEventListener('submit', (event) => {
                        if (event.defaultPrevented || isBulkPreflightRunning) {
                            return;
                        }

                        startBulkPreflightProgress(bulkForm);
                    });
                });

                if (cancelEditLink) {
                    cancelEditLink.addEventListener('click', (event) => {
                        const href = String(cancelEditLink.getAttribute('href') || '');
                        if (href === '') {
                            return;
                        }

                        event.preventDefault();

                        selectedQuestionIds.clear();
                        initialSelectedQuestionIdOrder.forEach((questionId) => {
                            if (Number.isInteger(questionId) && questionId > 0) {
                                selectedQuestionIds.add(questionId);
                            }
                        });

                        selectedSidebarSearchTerm = '';
                        if (selectedSidebarSearchInput) {
                            selectedSidebarSearchInput.value = '';
                        }

                        clearBuilderStateLocally();
                        syncCounter();

                        Promise.resolve(clearSelectedStateOnServer()).finally(() => {
                            window.location.assign(href);
                        });
                    });
                }

                bindQuestionCatalogPanel();
                saveBuilderState();
            })();
        </script>
