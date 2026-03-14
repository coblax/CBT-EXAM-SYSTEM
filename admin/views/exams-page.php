        <div class="wrap">
            <h1>CBT Exams</h1>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <div class="cbt-tab-buttons" id="cbt-exam-page-tabs" role="tablist" aria-label="Navigasi halaman exam">
                <button type="button" class="button<?php echo $active_exam_page_panel === 'cbt-exam-builder-panel' ? ' cbt-active' : ''; ?>" data-target="cbt-exam-builder-panel" role="tab" aria-selected="<?php echo $active_exam_page_panel === 'cbt-exam-builder-panel' ? 'true' : 'false'; ?>">
                    Form Exam
                </button>
                <button type="button" class="button<?php echo $active_exam_page_panel === 'cbt-exam-list-panel' ? ' cbt-active' : ''; ?>" data-target="cbt-exam-list-panel" role="tab" aria-selected="<?php echo $active_exam_page_panel === 'cbt-exam-list-panel' ? 'true' : 'false'; ?>">
                    Daftar Exam
                </button>
            </div>

            <div id="cbt-exam-builder-panel" class="cbt-exam-page-panel<?php echo $active_exam_page_panel === 'cbt-exam-builder-panel' ? ' cbt-active' : ''; ?>" role="tabpanel">
                <?php if (empty($subjects)): ?>
                    <div class="notice notice-warning"><p>Belum ada mapel. Buat mapel terlebih dahulu di menu Subjects.</p></div>
                <?php else: ?>
                    <h2><?php echo $editing_exam ? 'Edit Exam' : 'Buat Exam Baru'; ?></h2>
                    <p class="description">Flow: pilih mapel, atur jadwal, tentukan kelas peserta, lalu pilih soal yang dipakai untuk exam.</p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-exam-builder-form">
                        <?php wp_nonce_field('cbt_save_exam'); ?>
                        <input type="hidden" name="action" value="cbt_save_exam" />
                        <input type="hidden" name="id" value="<?php echo (int) ($editing_exam['id'] ?? 0); ?>" />
                        <input type="hidden" id="cbt-exam-builder-state-key" value="<?php echo esc_attr($builder_state_key); ?>" />
                        <input type="hidden" id="cbt-exam-builder-reset-keys" value="<?php echo esc_attr(wp_json_encode(array_values(array_unique($builder_state_reset_keys)))); ?>" />
                        <input type="hidden" id="cbt-exam-selected-defaults" value="<?php echo esc_attr(wp_json_encode(array_values(array_unique($selected_question_ids)))); ?>" />
                        <input type="hidden" id="cbt-exam-builder-ajax-nonce" value="<?php echo esc_attr(wp_create_nonce('cbt_exam_builder_state')); ?>" />
                        <input type="hidden" id="cbt-exam-builder-question-mode" value="<?php echo esc_attr($builder_question_mode); ?>" />

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
                                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cbt-exams')); ?>">Batal Edit</a>
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
                                        <input required type="datetime-local" id="cbt-exam-starts-at" name="starts_at" value="<?php echo esc_attr(self::to_datetime_local((string) ($editing_exam['starts_at'] ?? ''))); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-exam-ends-at">Selesai</label></th>
                                    <td>
                                        <input required type="datetime-local" id="cbt-exam-ends-at" name="ends_at" value="<?php echo esc_attr(self::to_datetime_local((string) ($editing_exam['ends_at'] ?? ''))); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-exam-target-kelas">Kelas Peserta</label></th>
                                    <td>
                                        <div style="margin-bottom:8px;">
                                            <button type="button" class="button button-secondary button-small" id="cbt-kelas-check-all">Pilih Semua</button>
                                            <button type="button" class="button button-secondary button-small" id="cbt-kelas-uncheck-all">Kosongkan</button>
                                        </div>
                                        <div id="cbt-kelas-checklist" tabindex="-1" style="min-width:360px; max-height:220px; overflow:auto; border:1px solid #ccd0d4; padding:8px; background:#fff;">
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
                                <tr>
                                    <th><label for="cbt-exam-randomize">Acak Soal</label></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="cbt-exam-randomize" name="randomize_questions" value="1" <?php checked((int) ($editing_exam['randomize_questions'] ?? 0), 1); ?> />
                                            Acak urutan soal untuk siswa
                                        </label>
                                    </td>
                                </tr>
                                <tr>
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
                            <div class="cbt-exam-question-shell">
                                <div class="cbt-exam-question-header">
                                    <?php if ($editing_exam): ?>
                                        <div class="cbt-exam-question-mode-bar">
                                            <span class="cbt-exam-question-mode-label">
                                                <?php echo esc_html($is_edit_selected_mode ? 'Mode: Soal Terpilih' : 'Mode: Tambah Soal'); ?>
                                            </span>
                                            <?php if ($is_edit_selected_mode): ?>
                                                <a href="<?php echo esc_url($add_questions_url); ?>" class="button button-secondary cbt-exam-question-nav-link">Tambah Soal</a>
                                            <?php else: ?>
                                                <a href="<?php echo esc_url($selected_questions_url); ?>" class="button cbt-exam-question-nav-link">Kembali ke Soal Terpilih</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <p class="description cbt-exam-question-description">
                                        <?php if ($is_edit_selected_mode): ?>
                                            Tampilkan soal yang saat ini sudah dipakai exam. Hilangkan centang untuk melepas soal dari exam, atau buka mode Tambah Soal untuk mengambil soal baru.
                                        <?php elseif ($is_edit_add_mode): ?>
                                            Tampilkan soal yang belum dipakai exam. Centang soal yang ingin ditambahkan ke exam.
                                        <?php else: ?>
                                            Soal akan disalin dari bank soal yang tersedia (lintas mapel). Saat exam disimpan ulang, daftar soal exam akan disinkronkan dari pilihan ini.
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="cbt-exam-question-filter-panel">
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
                                            <label for="cbt-exam-question-source-filter">Sumber</label>
                                            <select id="cbt-exam-question-source-filter">
                                                <option value="">Semua sumber</option>
                                                <?php foreach ($source_exam_options as $source_exam_id => $source_exam_title): ?>
                                                    <option value="<?php echo (int) $source_exam_id; ?>" <?php selected($builder_question_source, $source_exam_id); ?>><?php echo esc_html($source_exam_title); ?></option>
                                                <?php endforeach; ?>
                                            </select>
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
                                        <button type="button" class="button button-primary" id="cbt-exam-apply-filters">Terapkan</button>
                                        <button type="button" class="button" id="cbt-exam-reset-filters">Reset</button>
                                    </div>
                                </div>

                                <div class="cbt-exam-question-status-bar">
                                    <div class="cbt-exam-question-bulk-actions">
                                        <button type="button" class="button button-secondary" id="cbt-exam-select-visible"><?php echo esc_html($is_edit_selected_mode ? 'Pilih Ulang Visible' : 'Select Visible'); ?></button>
                                        <button type="button" class="button button-secondary" id="cbt-exam-unselect-visible"><?php echo esc_html($is_edit_selected_mode ? 'Lepas Visible' : 'Unselect Visible'); ?></button>
                                    </div>
                                    <span id="cbt-exam-selected-count"><?php echo esc_html(sprintf('%d dipilih total / %d terlihat', count($selected_question_ids), count($source_questions))); ?></span>
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
                                    <table class="widefat striped" style="margin:0;">
                                        <thead>
                                        <tr>
                                            <th style="width:38px;"><input type="checkbox" id="cbt-exam-select-all-visible" /></th>
                                            <th>ID</th>
                                            <th>Mapel</th>
                                            <th>Sumber</th>
                                            <th>Tipe</th>
                                            <th>Soal</th>
                                            <th>Poin</th>
                                            <th>Aksi</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($source_questions)): ?>
                                            <tr>
                                                <td colspan="8">
                                                    <?php if ($is_edit_selected_mode): ?>
                                                        Belum ada soal yang dipilih untuk exam ini. Klik tombol Tambah Soal untuk memilih soal.
                                                    <?php elseif ($is_edit_add_mode): ?>
                                                        Semua soal yang sesuai filter sudah dipilih untuk exam ini.
                                                    <?php else: ?>
                                                        Belum ada soal tersedia. Isi dulu di menu CBT Questions.
                                                    <?php endif; ?>
                                                </td>
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
                                                $source_options = $source_options_map[$source_question_id] ?? [];
                                                $source_correct_keys = [];
                                                foreach ($source_options as $source_option) {
                                                    if ((int) ($source_option['is_correct'] ?? 0) === 1) {
                                                        $source_correct_keys[] = strtoupper((string) ($source_option['option_key'] ?? ''));
                                                    }
                                                }
                                                ?>
                                                <tr class="cbt-exam-question-row"
                                                    data-subject-id="<?php echo (int) $source_subject_id; ?>"
                                                    data-source-id="<?php echo (int) $source_exam_id; ?>"
                                                    data-type="<?php echo esc_attr($source_type); ?>"
                                                    data-search="<?php echo esc_attr($question_plain); ?>">
                                                    <td><input type="checkbox" class="cbt-exam-question-checkbox" name="source_question_ids[]" value="<?php echo (int) $source_question_id; ?>" <?php checked($is_checked); ?> /></td>
                                                    <td><?php echo (int) $source_question_id; ?></td>
                                                    <td><?php echo esc_html((string) ($source_question['subject_name'] ?? '-')); ?></td>
                                                    <td><?php echo esc_html((string) ($source_question['exam_title'] ?? '-')); ?></td>
                                                    <td><?php echo esc_html($source_type_label); ?></td>
                                                    <td><?php echo esc_html($question_preview); ?></td>
                                                    <td><?php echo esc_html((string) ($source_question['points'] ?? '1')); ?></td>
                                                    <td>
                                                        <button type="button" class="button-link cbt-quick-view-btn" data-qid="<?php echo (int) $source_question_id; ?>">Lihat Cepat</button>
                                                        <div id="cbt-quick-view-content-<?php echo (int) $source_question_id; ?>" style="display:none;">
                                                            <p><strong>Subject:</strong> <?php echo esc_html((string) ($source_question['subject_name'] ?? '-')); ?></p>
                                                            <p><strong>Sumber:</strong> <?php echo esc_html((string) ($source_question['exam_title'] ?? '-')); ?></p>
                                                            <p><strong>Tipe:</strong> <?php echo esc_html($source_type_label); ?></p>
                                                            <p><strong>Poin:</strong> <?php echo esc_html((string) ($source_question['points'] ?? '1')); ?></p>
                                                            <hr />
                                                            <div><?php echo wp_kses_post((string) ($source_question['question_text'] ?? '')); ?></div>
                                                            <?php if (!empty($source_options)): ?>
                                                                <hr />
                                                                <p><strong>Opsi:</strong></p>
                                                                <ol style="margin-left:18px;">
                                                                    <?php foreach ($source_options as $source_option): ?>
                                                                        <?php $option_key = strtoupper((string) ($source_option['option_key'] ?? '')); ?>
                                                                        <li>
                                                                            <strong><?php echo esc_html($option_key); ?>.</strong>
                                                                            <?php echo wp_kses_post((string) ($source_option['option_text'] ?? '')); ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ol>
                                                                <?php if (!empty($source_correct_keys)): ?>
                                                                    <p><strong>Kunci:</strong> <?php echo esc_html(implode(', ', $source_correct_keys)); ?></p>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
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
                                <div class="cbt-exam-panel-nav" aria-label="Navigasi pilih soal">
                                    <p id="cbt-exam-question-submit-help" class="cbt-exam-panel-nav-note">Perlu ubah judul, jadwal, atau deskripsi? Kembali ke detail exam atau langsung simpan exam dari sini.</p>
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
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:8px 0 12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="cbt-exams" />
                    <label for="cbt-exam-per-page">Per halaman</label>
                    <select id="cbt-exam-per-page" name="cbt_exam_per_page">
                        <?php foreach ([20, 40, 60, 80, 100] as $exam_per_page_option): ?>
                            <option value="<?php echo (int) $exam_per_page_option; ?>" <?php selected($exam_per_page, $exam_per_page_option); ?>>
                                <?php echo esc_html((string) $exam_per_page_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-secondary">Terapkan</button>
                </form>
                <table class="widefat striped">
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
                        <tr><td colspan="10">No exams found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                            <?php
                            $kelas_list = self::split_target_kelas_csv((string) ($exam['target_kelas'] ?? ''));
                            $kelas_display = !empty($kelas_list) ? implode(', ', $kelas_list) : 'Semua kelas';
                            $schedule_parts = [];
                            if (!empty($exam['starts_at'])) {
                                $schedule_parts[] = 'Mulai: ' . (string) $exam['starts_at'];
                            }
                            if (!empty($exam['ends_at'])) {
                                $schedule_parts[] = 'Selesai: ' . (string) $exam['ends_at'];
                            }
                            $schedule_display = !empty($schedule_parts) ? implode(' | ', $schedule_parts) : '-';
                            ?>
                            <tr>
                                <td><?php echo (int) $exam['id']; ?></td>
                                <td><?php echo esc_html((string) ($exam['subject_name'] ?? '-')); ?></td>
                                <td><?php echo esc_html((string) ($exam['title'] ?? '')); ?></td>
                                <td><?php echo esc_html($kelas_display); ?></td>
                                <td><?php echo esc_html((string) ($exam['status'] ?? 'draft')); ?></td>
                                <td><?php echo esc_html($schedule_display); ?></td>
                                <td><?php echo esc_html((string) ((int) ($exam['duration_minutes'] ?? 0))); ?> menit</td>
                                <td><?php echo esc_html((string) ((int) ($exam['question_count'] ?? 0))); ?></td>
                                <td>
                                    <?php
                                    $attempt_total = (int) ($exam['attempt_total'] ?? 0);
                                    $attempt_in_progress = (int) ($exam['attempt_in_progress'] ?? 0);
                                    $attempt_completed = (int) ($exam['attempt_completed'] ?? 0);
                                    ?>
                                    Total: <?php echo esc_html((string) $attempt_total); ?><br />
                                    Ongoing: <?php echo esc_html((string) $attempt_in_progress); ?><br />
                                    Selesai: <?php echo esc_html((string) $attempt_completed); ?>
                                </td>
                                <td>
                                    <a
                                        href="<?php echo esc_url(add_query_arg(['page' => 'cbt-exams', 'preview_exam_id' => (int) $exam['id'], 'cbt_exam_per_page' => $exam_per_page, 'cbt_exam_paged' => $exam_current_page], admin_url('admin.php'))); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >Lihat Soal</a>
                                    |
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'cbt-results', 'cbt_exam_id' => (int) $exam['id']], admin_url('admin.php'))); ?>">Results</a>
                                    |
                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'cbt-exams', 'edit' => (int) $exam['id'], 'cbt_exam_per_page' => $exam_per_page, 'cbt_exam_paged' => $exam_current_page], admin_url('admin.php'))); ?>">Edit</a>
                                    |
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'cbt_delete_exam', 'id' => (int) $exam['id'], 'cbt_exam_per_page' => $exam_per_page, 'cbt_exam_paged' => $exam_current_page], admin_url('admin-post.php')), 'cbt_delete_exam_' . (int) $exam['id'])); ?>" onclick="return confirm('Delete this exam?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            <?php
            $exam_pagination_links = [];
            if ($exam_total_pages > 1) {
                $exam_pagination_links = paginate_links([
                    'base' => add_query_arg(
                        [
                            'page' => 'cbt-exams',
                            'cbt_exam_per_page' => $exam_per_page,
                            'cbt_exam_paged' => '%#%',
                        ],
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
            <style>
                .cbt-tab-buttons {
                    display: flex;
                    gap: 8px;
                    margin: 12px 0;
                    flex-wrap: wrap;
                }
                .cbt-tab-buttons .button.cbt-active {
                    background: #2271b1;
                    color: #fff;
                    border-color: #2271b1;
                }
                .cbt-exam-page-panel,
                .cbt-exam-builder-panel {
                    display: none;
                }
                .cbt-exam-page-panel.cbt-active,
                .cbt-exam-builder-panel.cbt-active {
                    display: block;
                }
                .cbt-exam-flow-bar {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin: 12px 0 6px;
                    flex-wrap: wrap;
                }
                .cbt-exam-flow-tabs,
                .cbt-exam-flow-actions {
                    margin: 0;
                    align-items: center;
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
                    margin: 12px 0 16px;
                    flex-wrap: wrap;
                }
                .cbt-exam-flow-actions {
                    margin: 0;
                }
                .cbt-exam-flow-help {
                    margin: 0 0 16px;
                }
                .cbt-exam-detail-validation-notice {
                    margin: 14px 0 0;
                }
                .cbt-exam-detail-validation-notice[hidden] {
                    display: none !important;
                }
                .cbt-exam-panel-nav {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px 16px;
                    margin-top: 16px;
                    padding: 14px 16px;
                    border: 1px solid #dcdcde;
                    border-radius: 10px;
                    background: #ffffff;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
                    flex-wrap: wrap;
                }
                .cbt-exam-panel-nav-note {
                    margin: 0;
                    color: #50575e;
                }
                .cbt-exam-panel-nav-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
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
                    border-radius: 14px;
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
                .cbt-exam-question-shell {
                    margin-top: 12px;
                    padding: 18px;
                    border: 1px solid #dcdcde;
                    border-radius: 10px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
                }
                .cbt-exam-question-header {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    margin-bottom: 16px;
                }
                .cbt-exam-question-description {
                    margin: 0;
                    max-width: 920px;
                }
                .cbt-exam-question-mode-bar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    margin: 0;
                    flex-wrap: wrap;
                }
                .cbt-exam-question-mode-label {
                    display: inline-flex;
                    align-items: center;
                    min-height: 34px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: #e8f1fa;
                    color: #0a4b78;
                    font-weight: 600;
                }
                .cbt-exam-question-filter-panel {
                    display: flex;
                    align-items: flex-end;
                    justify-content: space-between;
                    gap: 14px 16px;
                    padding: 14px 16px;
                    margin: 0 0 12px;
                    border: 1px solid #dcdcde;
                    border-radius: 8px;
                    background: #ffffff;
                    flex-wrap: wrap;
                }
                .cbt-exam-question-filter-grid {
                    display: grid;
                    grid-template-columns: minmax(280px, 2.4fr) minmax(150px, 1fr) minmax(200px, 1.2fr) minmax(120px, 0.7fr);
                    gap: 12px;
                    flex: 1 1 760px;
                }
                .cbt-exam-question-field {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    min-width: 0;
                }
                .cbt-exam-question-field label {
                    font-weight: 500;
                    margin: 0;
                    color: #1d2327;
                }
                .cbt-exam-question-field input,
                .cbt-exam-question-field select {
                    width: 100%;
                    max-width: none;
                }
                .cbt-exam-question-filter-actions {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex: 0 0 auto;
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
                #cbt-exam-selected-count {
                    display: inline-flex;
                    align-items: center;
                    min-height: 36px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: #f0f6fc;
                    color: #0a4b78;
                    font-weight: 600;
                }
                .cbt-exam-question-pagination-wrap {
                    margin-bottom: 10px;
                }
                .cbt-exam-question-table-wrap {
                    max-height: 380px;
                    overflow: auto;
                    border: 1px solid #ccd0d4;
                    border-radius: 8px;
                    background: #fff;
                }
                .cbt-exam-question-table-wrap table thead th {
                    position: sticky;
                    top: 0;
                    z-index: 1;
                    background: #f6f7f7;
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
                    font-weight: 500;
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
                    min-width: 34px;
                    height: 34px;
                    padding: 0 12px;
                    border: 1px solid #c3c4c7;
                    border-radius: 6px;
                    background: #fff;
                    color: #1d2327;
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
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
                    color: #fff;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
                }
                .cbt-admin-pagination-links .page-numbers.prev,
                .cbt-admin-pagination-links .page-numbers.next {
                    padding: 0 14px;
                    font-weight: 600;
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
                    .cbt-exam-question-filter-grid {
                        grid-template-columns: repeat(2, minmax(220px, 1fr));
                    }
                }
                @media (max-width: 782px) {
                    .cbt-exam-flow-bar {
                        align-items: stretch;
                    }
                    .cbt-exam-flow-tabs,
                    .cbt-exam-flow-actions {
                        width: 100%;
                    }
                    .cbt-exam-flow-arrow {
                        min-width: 18px;
                    }
                    .cbt-exam-builder-actions .button {
                        width: 100%;
                        justify-content: center;
                    }
                    .cbt-exam-panel-nav-actions {
                        width: 100%;
                        margin-left: 0;
                    }
                    .cbt-exam-panel-nav-actions .button {
                        width: 100%;
                        justify-content: center;
                    }
                    .cbt-exam-flow-actions .cbt-exam-flow-arrow {
                        width: 100%;
                        justify-content: flex-start;
                    }
                    .cbt-exam-question-shell {
                        padding: 14px;
                    }
                    .cbt-exam-save-progress-overlay {
                        padding: 16px;
                    }
                    .cbt-exam-save-progress-card {
                        padding: 18px 16px;
                    }
                    .cbt-exam-save-progress-meta {
                        align-items: flex-start;
                    }
                    .cbt-exam-question-filter-panel,
                    .cbt-exam-question-status-bar {
                        align-items: stretch;
                    }
                    .cbt-exam-question-mode-bar {
                        align-items: stretch;
                    }
                    .cbt-exam-question-mode-bar .button {
                        width: 100%;
                        justify-content: center;
                    }
                    .cbt-exam-question-filter-grid {
                        grid-template-columns: 1fr;
                    }
                    .cbt-exam-question-filter-actions,
                    .cbt-exam-question-bulk-actions {
                        width: 100%;
                    }
                    .cbt-exam-question-filter-actions .button,
                    .cbt-exam-question-bulk-actions .button,
                    #cbt-exam-selected-count {
                        width: 100%;
                        margin-left: 0;
                        justify-content: center;
                    }
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

                const activatePageTab = initTabs('cbt-exam-page-tabs', '.cbt-exam-page-panel');
                const searchInput = document.getElementById('cbt-exam-question-search');
                const typeFilter = document.getElementById('cbt-exam-question-type-filter');
                const sourceFilter = document.getElementById('cbt-exam-question-source-filter');
                const perPageFilter = document.getElementById('cbt-exam-question-per-page');
                const applyFiltersBtn = document.getElementById('cbt-exam-apply-filters');
                const resetFiltersBtn = document.getElementById('cbt-exam-reset-filters');
                const selectedCountEl = document.getElementById('cbt-exam-selected-count');
                const selectAllVisible = document.getElementById('cbt-exam-select-all-visible');
                const selectVisibleBtn = document.getElementById('cbt-exam-select-visible');
                const unselectVisibleBtn = document.getElementById('cbt-exam-unselect-visible');
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
                const rows = Array.from(document.querySelectorAll('.cbt-exam-question-row'));
                const quickViewButtons = Array.from(document.querySelectorAll('.cbt-quick-view-btn'));
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
                const ajaxNonceInput = document.getElementById('cbt-exam-builder-ajax-nonce');
                const questionModeInput = document.getElementById('cbt-exam-builder-question-mode');
                const questionMode = questionModeInput ? String(questionModeInput.value || 'catalog') : 'catalog';
                const questionModeNavLinks = Array.from(document.querySelectorAll('.cbt-exam-question-nav-link'));
                const questionPaginationLinks = Array.from(document.querySelectorAll('.cbt-exam-question-pagination-wrap a.page-numbers'));
                const builderNavButtons = Array.from(document.querySelectorAll('.cbt-exam-builder-nav-btn'));
                const submitExamButtons = Array.from(document.querySelectorAll('.cbt-exam-submit-btn'));
                const saveProgressOverlay = document.getElementById('cbt-exam-save-progress-overlay');
                const saveProgressTitle = document.getElementById('cbt-exam-save-progress-title');
                const saveProgressMessage = document.getElementById('cbt-exam-save-progress-message');
                const saveProgressPhase = document.getElementById('cbt-exam-save-progress-phase');
                const saveProgressStats = document.getElementById('cbt-exam-save-progress-stats');
                const saveProgressFill = document.getElementById('cbt-exam-save-progress-fill');
                const saveProgressPercent = document.getElementById('cbt-exam-save-progress-percent');
                const ajaxNonce = ajaxNonceInput ? String(ajaxNonceInput.value || '') : '';
                let isFinalFormSubmit = false;
                let isExamSaveRunning = false;
                let selectionSyncTimer = null;
                const defaultQuestionSubmitHelpText = questionSubmitHelp ? String(questionSubmitHelp.textContent || '').trim() : '';

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
                            return validateExamDetailsBeforeQuestionStep();
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
                        return parsedState && typeof parsedState === 'object' ? parsedState : null;
                    } catch (error) {
                        return null;
                    }
                };

                const initialBuilderState = loadBuilderState();
                const initialSelectedIds = initialBuilderState && Array.isArray(initialBuilderState.selectedQuestionIds)
                    ? initialBuilderState.selectedQuestionIds
                    : defaultSelectedQuestionIds;
                const selectedQuestionIds = new Set(
                    (Array.isArray(initialSelectedIds) ? initialSelectedIds : [])
                        .map((item) => parseInt(String(item), 10))
                        .filter((item) => Number.isInteger(item) && item > 0)
                );

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
                            selectedQuestionIds: Array.from(selectedQuestionIds),
                            draft: collectFormDraft(),
                        }));
                    } catch (error) {
                        // Ignore storage quota or access errors in admin UI.
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

                function navigateBuilderPanel(targetId) {
                    const normalizedTargetId = String(targetId || '');
                    if (normalizedTargetId === '') {
                        return;
                    }

                    saveBuilderState();

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

                function closeQuickView() {
                    if (!quickViewModal) return;
                    quickViewModal.style.display = 'none';
                    if (quickViewBody) {
                        quickViewBody.innerHTML = '';
                    }
                }

                if (quickViewButtons.length > 0 && quickViewModal && quickViewBody) {
                    quickViewButtons.forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const qid = String(btn.getAttribute('data-qid') || '');
                            const source = document.getElementById(`cbt-quick-view-content-${qid}`);
                            if (!source) return;
                            if (quickViewTitle) {
                                quickViewTitle.textContent = `Preview Soal #${qid}`;
                            }
                            quickViewBody.innerHTML = source.innerHTML;
                            quickViewModal.style.display = 'block';
                        });
                    });

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
                    rows.forEach((row) => {
                        const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                        if (!checkbox) {
                            return;
                        }
                        const questionId = parseInt(String(checkbox.value || ''), 10);
                        checkbox.checked = Number.isInteger(questionId) && selectedQuestionIds.has(questionId);
                    });

                    syncQuestionModeVisibility();
                }

                function syncQuestionModeVisibility() {
                    rows.forEach((row) => {
                        const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                        if (!checkbox) {
                            return;
                        }
                        if (questionMode === 'selected') {
                            row.style.display = checkbox.checked ? '' : 'none';
                        } else if (questionMode === 'add') {
                            row.style.display = checkbox.checked ? 'none' : '';
                        } else {
                            row.style.display = '';
                        }
                    });
                }

                function getVisibleRows() {
                    return rows.filter((row) => row.style.display !== 'none');
                }

                function syncCounter() {
                    const visibleRows = getVisibleRows();
                    const visibleChecked = visibleRows.filter((row) => {
                        const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                        return !!checkbox && checkbox.checked;
                    }).length;

                    if (selectedCountEl) {
                        selectedCountEl.textContent = `${selectedQuestionIds.size} dipilih total / ${visibleRows.length} terlihat`;
                    }

                    if (selectAllVisible) {
                        selectAllVisible.checked = visibleRows.length > 0 && visibleChecked === visibleRows.length;
                        selectAllVisible.indeterminate = visibleChecked > 0 && visibleChecked < visibleRows.length;
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

                rows.forEach((row) => {
                    const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                    if (!checkbox) return;
                    checkbox.addEventListener('change', () => {
                        const questionId = parseInt(String(checkbox.value || ''), 10);
                        upsertQuestionSelection(questionId, checkbox.checked);
                        syncQuestionModeVisibility();
                        syncCounter();
                        saveBuilderState();
                        queueSelectedStateSync();
                    });
                });

                if (selectAllVisible) {
                    selectAllVisible.addEventListener('change', () => {
                        const visibleRows = getVisibleRows();
                        visibleRows.forEach((row) => {
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            if (checkbox) {
                                const questionId = parseInt(String(checkbox.value || ''), 10);
                                checkbox.checked = selectAllVisible.checked;
                                upsertQuestionSelection(questionId, selectAllVisible.checked);
                            }
                        });
                        syncQuestionModeVisibility();
                        syncCounter();
                        saveBuilderState();
                        queueSelectedStateSync();
                    });
                }

                if (selectVisibleBtn) {
                    selectVisibleBtn.addEventListener('click', () => {
                        const visibleRows = getVisibleRows();
                        visibleRows.forEach((row) => {
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            if (checkbox) {
                                const questionId = parseInt(String(checkbox.value || ''), 10);
                                checkbox.checked = true;
                                upsertQuestionSelection(questionId, true);
                            }
                        });
                        syncQuestionModeVisibility();
                        syncCounter();
                        saveBuilderState();
                        queueSelectedStateSync();
                    });
                }

                if (unselectVisibleBtn) {
                    unselectVisibleBtn.addEventListener('click', () => {
                        const visibleRows = getVisibleRows();
                        visibleRows.forEach((row) => {
                            const checkbox = row.querySelector('.cbt-exam-question-checkbox');
                            if (checkbox) {
                                const questionId = parseInt(String(checkbox.value || ''), 10);
                                checkbox.checked = false;
                                upsertQuestionSelection(questionId, false);
                            }
                        });
                        syncQuestionModeVisibility();
                        syncCounter();
                        saveBuilderState();
                        queueSelectedStateSync();
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

                function buildQuestionCatalogUrl(resetFilters = false) {
                    const url = new URL(window.location.href);
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
                        const selectedPerPage = String(perPageFilter?.value || '150');

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

                        if (selectedPerPage !== '150') {
                            url.searchParams.set('cbt_exam_question_per_page', selectedPerPage);
                        } else {
                            url.searchParams.delete('cbt_exam_question_per_page');
                        }

                        url.searchParams.delete('cbt_exam_question_paged');
                    }

                    return url;
                }

                async function navigateToQuestionUrl(targetUrl) {
                    saveBuilderState();
                    await flushSelectedStateSync();
                    window.location.href = targetUrl.toString();
                }

                async function navigateQuestionCatalog(resetFilters = false) {
                    await navigateToQuestionUrl(buildQuestionCatalogUrl(resetFilters));
                }

                if (applyFiltersBtn) {
                    applyFiltersBtn.addEventListener('click', () => {
                        navigateQuestionCatalog(false);
                    });
                }
                if (resetFiltersBtn) {
                    resetFiltersBtn.addEventListener('click', () => {
                        navigateQuestionCatalog(true);
                    });
                }
                if (searchInput) {
                    searchInput.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            navigateQuestionCatalog(false);
                        }
                    });
                }

                questionModeNavLinks.forEach((link) => {
                    link.addEventListener('click', (event) => {
                        const href = String(link.getAttribute('href') || '');
                        if (href === '') {
                            return;
                        }

                        event.preventDefault();
                        navigateToQuestionUrl(new URL(href, window.location.origin));
                    });
                });

                questionPaginationLinks.forEach((link) => {
                    link.addEventListener('click', (event) => {
                        const href = String(link.getAttribute('href') || '');
                        if (href === '') {
                            return;
                        }

                        event.preventDefault();
                        navigateToQuestionUrl(new URL(href, window.location.origin));
                    });
                });

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

                syncQuestionCheckboxesFromState();
                syncCounter();
                saveBuilderState();
            })();
        </script>
