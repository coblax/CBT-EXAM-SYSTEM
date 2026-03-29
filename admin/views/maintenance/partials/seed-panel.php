<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div
    data-seed-panel
    data-seed-presets="<?php echo esc_attr($seed_presets_json); ?>"
    data-seed-question-type-labels="<?php echo esc_attr(wp_json_encode($seed_question_type_labels)); ?>"
    data-seed-exam-profile-labels="<?php echo esc_attr(wp_json_encode($seed_exam_profile_labels)); ?>"
>
    <?php if (is_array($seed_progress_state)): ?>
        <section class="cbt-maintenance-card cbt-maintenance-card--seed">
            <div class="cbt-maintenance-card-header">
                <div>
                    <h2>Progress Generate Data Uji</h2>
                    <p>Generator berjalan bertahap: reset penuh CBT, lalu membuat subject, user, Bank Soal, exam uji, root question, dan sinkronisasi soal ke exam.</p>
                </div>
                <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($seed_progress_status_tone); ?>">
                    <?php echo esc_html($seed_progress_status_label); ?>
                </span>
            </div>
            <div class="cbt-maintenance-progress-track" aria-hidden="true">
                <div class="cbt-maintenance-progress-fill cbt-maintenance-progress-fill--seed" style="width: <?php echo esc_attr((string) $seed_progress_percent); ?>%;"></div>
            </div>
            <?php if ($seed_progress_activity_detail !== ''): ?>
                <div class="cbt-maintenance-progress-activity">
                    <span class="cbt-maintenance-progress-activity-label">Aktivitas Saat Ini</span>
                    <strong><?php echo esc_html($seed_progress_activity_detail); ?></strong>
                </div>
            <?php endif; ?>
            <div class="cbt-maintenance-progress-meta">
                <div class="cbt-maintenance-stat">
                    <span class="cbt-maintenance-stat-label">Progress</span>
                    <strong><?php echo esc_html((string) $seed_progress_processed . ' / ' . (string) $seed_progress_total); ?></strong>
                </div>
                <div class="cbt-maintenance-stat">
                    <span class="cbt-maintenance-stat-label">Preset</span>
                    <strong><?php echo esc_html($seed_progress_preset_label); ?></strong>
                </div>
                <div class="cbt-maintenance-stat">
                    <span class="cbt-maintenance-stat-label">User Sinkron</span>
                    <strong><?php echo esc_html((string) $seed_progress_synced_users); ?></strong>
                </div>
                <div class="cbt-maintenance-stat">
                    <span class="cbt-maintenance-stat-label">Bank Question</span>
                    <strong><?php echo esc_html((string) $seed_progress_created_questions); ?></strong>
                </div>
                <div class="cbt-maintenance-stat">
                    <span class="cbt-maintenance-stat-label">Soal Ujian Sync</span>
                    <strong><?php echo esc_html((string) $seed_progress_synced_exam_questions); ?></strong>
                </div>
            </div>
            <p class="cbt-maintenance-progress-note">
                Tahap saat ini: <strong><?php echo esc_html($seed_progress_phase_label); ?></strong>.
                Dataset ini memakai password default <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_default_password); ?></span>.
                Akun test khusus: <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_special_username); ?></span> / <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_special_password); ?></span>.
                <?php if ($seed_progress_is_running): ?>
                    Batch berikutnya akan dilanjutkan otomatis.
                    <script>
                        if (!window.__cbtMaintenanceAutoContinue) {
                            window.__cbtMaintenanceAutoContinue = true;
                            window.setTimeout(function () {
                                window.location.href = <?php echo wp_json_encode($seed_progress_continue_url); ?>;
                            }, 350);
                        }
                    </script>
                <?php else: ?>
                    Generator data uji selesai diproses.
                <?php endif; ?>
            </p>
            <div class="cbt-maintenance-summary-grid" style="margin-top:16px;">
                <div class="cbt-maintenance-summary-item">
                    <span>User Lama Terhapus</span>
                    <strong><?php echo esc_html((string) $seed_progress_deleted_users); ?></strong>
                </div>
                <div class="cbt-maintenance-summary-item">
                    <span>Gagal Hapus User</span>
                    <strong><?php echo esc_html((string) $seed_progress_failed_user_deletes); ?></strong>
                </div>
                <div class="cbt-maintenance-summary-item">
                    <span>Persentase</span>
                    <strong><?php echo esc_html(number_format($seed_progress_percent, 2)); ?>%</strong>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="cbt-maintenance-card cbt-maintenance-card--seed">
        <div class="cbt-maintenance-card-header">
            <div>
                <h2>Generate Data Uji CBT</h2>
                <p>Fitur ini akan menjalankan reset penuh CBT terlebih dulu, lalu membuat dataset baru dengan topologi Bank Soal per mapel dan exam uji yang menerima salinan soal tersinkron.</p>
            </div>
            <span class="cbt-maintenance-chip cbt-maintenance-chip--running">Test Seeder</span>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Generator akan reset penuh CBT lalu membuat dataset uji baru. Lanjutkan?');" style="margin-top:18px;">
            <?php wp_nonce_field('cbt_generate_test_dataset'); ?>
            <input type="hidden" name="action" value="cbt_generate_test_dataset" />
            <input type="hidden" name="cbt_maintenance_tab" value="seed" data-maintenance-tab-input />

            <div class="cbt-maintenance-field-grid">
                <div class="cbt-maintenance-field">
                    <label for="cbt-seed-preset">Preset dataset</label>
                    <div class="cbt-maintenance-select-wrap">
                        <select id="cbt-seed-preset" name="preset">
                            <?php foreach ($seed_presets as $preset_key => $preset_meta): ?>
                                <option value="<?php echo esc_attr($preset_key); ?>" <?php selected($selected_seed_preset, $preset_key); ?>>
                                    <?php echo esc_html((string) ($preset_meta['label'] ?? ucfirst($preset_key))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="description"><code>Small</code> cocok untuk cek fungsi cepat, <code>Medium</code> untuk staging realistis, <code>Large</code> untuk pengujian beban awal.</p>
                </div>
                <div class="cbt-maintenance-field">
                    <label for="cbt-seed-confirm-phrase">Konfirmasi wajib</label>
                    <input
                        type="text"
                        id="cbt-seed-confirm-phrase"
                        name="confirm_phrase"
                        placeholder="Ketik: <?php echo esc_attr($test_data_seed_confirm_phrase); ?>"
                        autocomplete="off"
                        required
                    />
                    <p class="description">Ketik persis <code><?php echo esc_html($test_data_seed_confirm_phrase); ?></code> untuk memulai reset penuh lalu generate dataset baru.</p>
                </div>
            </div>

            <div class="cbt-maintenance-summary-grid" id="cbt-seed-summary-grid">
                <div class="cbt-maintenance-summary-item">
                    <span>Subject</span>
                    <strong data-seed-summary="subjects"><?php echo esc_html((string) ($selected_seed_preset_data['subjects'] ?? 0)); ?></strong>
                </div>
                <div class="cbt-maintenance-summary-item">
                    <span>Exam</span>
                    <strong data-seed-summary="exams"><?php echo esc_html((string) ($selected_seed_preset_data['exams'] ?? 0)); ?></strong>
                </div>
                <div class="cbt-maintenance-summary-item">
                    <span>Bank Question</span>
                    <strong data-seed-summary="questions"><?php echo esc_html((string) ($selected_seed_preset_data['questions'] ?? 0)); ?></strong>
                </div>
                <div class="cbt-maintenance-summary-item">
                    <span>Siswa</span>
                    <strong data-seed-summary="students"><?php echo esc_html((string) ($selected_seed_preset_data['students'] ?? 0)); ?></strong>
                </div>
                <div class="cbt-maintenance-summary-item">
                    <span>Guru</span>
                    <strong data-seed-summary="teachers"><?php echo esc_html((string) ($selected_seed_preset_data['teachers'] ?? 0)); ?></strong>
                </div>
                <div class="cbt-maintenance-summary-item">
                    <span>Kelas / Ruang</span>
                    <strong>
                        <span data-seed-summary="classes"><?php echo esc_html((string) ($selected_seed_preset_data['classes'] ?? 0)); ?></span>
                        /
                        <span data-seed-summary="rooms"><?php echo esc_html((string) ($selected_seed_preset_data['rooms'] ?? 0)); ?></span>
                    </strong>
                </div>
            </div>

            <p class="cbt-maintenance-summary-note">
                Preset ini
                <strong data-seed-summary-label><?php echo esc_html((string) ($selected_seed_preset_data['label'] ?? 'Small')); ?></strong>
                akan membuat root question di Bank Soal per mapel, lalu menyinkronkannya ke exam uji. User login dibuat dengan password default
                <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_default_password); ?></span>.
                Akun test khusus yang selalu dibuat: <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_special_username); ?></span> / <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_special_password); ?></span>.
                Short answer bulk memakai placeholder inline <span class="cbt-maintenance-inline-code">[INPUT_1]</span> sampai <span class="cbt-maintenance-inline-code">[INPUT_8]</span>, dan jumlah input selalu sama dengan jumlah jawaban yang disimpan.
                Rich content bulk memakai sample image internal plugin, lalu menyimpan gambar seperti import soal: prioritas ke uploads WordPress dan fallback ke base64 bila upload gagal.
                Tabel HTML dipakai di stem soal, dan option <code>multiple_choice</code> / <code>multiple_answer</code> bisa membawa gambar serta tabel ringkas yang compact.
            </p>

            <div class="cbt-maintenance-question-breakdown">
                <span class="cbt-maintenance-question-breakdown-title">Komposisi Bank Question</span>
                <p class="cbt-maintenance-question-breakdown-copy" data-seed-question-summary-text>
                    <?php echo esc_html($selected_seed_question_type_summary); ?>
                </p>
                <div class="cbt-maintenance-question-chip-list" id="cbt-seed-question-breakdown">
                    <?php foreach ($selected_seed_question_type_counts as $question_type => $question_count): ?>
                        <?php if ((int) $question_count <= 0) { continue; } ?>
                        <span class="cbt-maintenance-question-chip">
                            <?php echo esc_html((string) ($seed_question_type_labels[$question_type] ?? $question_type) . ': ' . number_format_i18n((int) $question_count)); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="cbt-maintenance-question-breakdown">
                <span class="cbt-maintenance-question-breakdown-title">Komposisi Profil Exam</span>
                <p class="cbt-maintenance-question-breakdown-copy" data-seed-exam-profile-summary-text>
                    <?php echo esc_html($selected_seed_exam_profile_summary); ?>
                </p>
                <div class="cbt-maintenance-question-chip-list" id="cbt-seed-exam-profile-breakdown">
                    <?php foreach ($selected_seed_exam_profile_counts as $profile_key => $profile_count): ?>
                        <?php if ((int) $profile_count <= 0) { continue; } ?>
                        <span class="cbt-maintenance-question-chip">
                            <?php echo esc_html((string) ($seed_exam_profile_labels[$profile_key] ?? $profile_key) . ': ' . number_format_i18n((int) $profile_count)); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="cbt-maintenance-actions">
                <p class="cbt-maintenance-actions-copy">Gunakan hanya pada staging atau lingkungan uji, karena aksi ini destruktif dan akan membersihkan seluruh data CBT saat ini sebelum membuat Bank Soal baru dan exam uji tersinkron.</p>
                <button type="submit" class="button button-primary button-large" <?php disabled($seed_progress_is_running); ?>>
                    Reset &amp; Generate Data Uji
                </button>
            </div>
        </form>
    </section>
</div>
