<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if (is_array($reset_progress_state)): ?>
    <section class="cbt-maintenance-card">
        <div class="cbt-maintenance-card-header">
            <div>
                <h2>Progress Reset Database</h2>
                <p>Progress reset ditampilkan real-time per batch sampai seluruh proses selesai.</p>
            </div>
            <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($reset_progress_status_tone); ?>">
                <?php echo esc_html($reset_progress_status_label); ?>
            </span>
        </div>
        <div class="cbt-maintenance-progress-track" aria-hidden="true">
            <div class="cbt-maintenance-progress-fill" style="width: <?php echo esc_attr((string) $reset_progress_percent); ?>%;"></div>
        </div>
        <div class="cbt-maintenance-progress-meta">
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Progress</span>
                <strong><?php echo esc_html((string) $reset_progress_processed . ' / ' . (string) $reset_progress_total); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Persentase</span>
                <strong><?php echo esc_html(number_format($reset_progress_percent, 2)); ?>%</strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">User Terhapus</span>
                <strong><?php echo esc_html((string) $reset_progress_deleted_users); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Tabel Gagal</span>
                <strong><?php echo esc_html((string) $reset_progress_failed_tables); ?></strong>
            </div>
        </div>
        <p class="cbt-maintenance-progress-note">
            Tahap saat ini: <strong><?php echo esc_html($reset_progress_phase_label); ?></strong>.
            <?php if ($reset_progress_is_running): ?>
                Memproses batch berikutnya secara otomatis.
                <script>
                    if (!window.__cbtMaintenanceAutoContinue) {
                        window.__cbtMaintenanceAutoContinue = true;
                        window.setTimeout(function () {
                            window.location.href = <?php echo wp_json_encode($reset_progress_continue_url); ?>;
                        }, 350);
                    }
                </script>
            <?php else: ?>
                Reset database selesai diproses.
            <?php endif; ?>
        </p>
    </section>
<?php endif; ?>

<section class="cbt-maintenance-card cbt-maintenance-card--danger">
    <div class="cbt-maintenance-card-header">
        <div>
            <h2>Reset Database CBT</h2>
            <p>Reset ini akan menghapus seluruh data CBT plugin secara permanen, termasuk struktur Bank Soal, dan tidak bisa dibatalkan.</p>
        </div>
        <span class="cbt-maintenance-chip cbt-maintenance-chip--danger">Danger Zone</span>
    </div>

    <div class="cbt-maintenance-alert">
        <strong>Peringatan:</strong> semua data tabel plugin CBT akan dikosongkan, termasuk subjects, exam ujian, Bank Soal, questions, attempts, answers, options, hasil, dan pengaturan token global.
    </div>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Yakin reset data CBT? Aksi ini tidak bisa dibatalkan.');" style="margin-top:18px;">
        <?php wp_nonce_field('cbt_reset_database'); ?>
        <input type="hidden" name="action" value="cbt_reset_database" />
        <input type="hidden" name="cbt_maintenance_tab" value="reset" data-maintenance-tab-input />

        <div class="cbt-maintenance-field-grid">
            <div class="cbt-maintenance-field">
                <label>Reset tabel CBT</label>
                <p class="cbt-maintenance-reset-copy">Progress reset akan ditampilkan otomatis sampai proses selesai. Setelah reset, Bank Soal tidak dibuat otomatis; struktur itu akan muncul lagi saat create question baru, import question, atau generate bulk test data.</p>
            </div>
            <div class="cbt-maintenance-field">
                <label for="cbt-reset-confirm-phrase">Konfirmasi wajib</label>
                <input
                    type="text"
                    id="cbt-reset-confirm-phrase"
                    name="confirm_phrase"
                    placeholder="Ketik: RESET CBT"
                    autocomplete="off"
                    required
                />
                <p class="description">Ketik persis <code>RESET CBT</code> untuk melanjutkan.</p>
            </div>
        </div>

        <div class="cbt-maintenance-actions">
            <p class="cbt-maintenance-actions-copy">Pastikan Anda sudah memahami dampaknya sebelum menjalankan reset penuh database CBT, termasuk penghapusan seluruh Bank Soal yang ada.</p>
            <button type="submit" class="button button-primary button-large">Reset Database CBT</button>
        </div>
    </form>
</section>
