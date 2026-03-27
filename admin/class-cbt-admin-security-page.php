<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Security_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Security_Service::can_manage_exams()) {
            wp_die('Unauthorized');
        }

        $context = CBT_Admin_Security_Service::build_page_context($_GET);
        $cbt_admin_view_mode = 'security';

        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/security/page.php';
    }

    /**
     * @param array<int,array<string,mixed>> $must_watch_attempts
     */
    public static function render_security_log_must_watch_panel(array $must_watch_attempts): void
    {
        $must_watch_total = count($must_watch_attempts);
        ?>
        <section class="cbt-setup-security-log-watch">
            <div class="cbt-setup-security-log-watch-header">
                <div>
                    <h3>Must Watch</h3>
                    <p>Attempt aktif dengan indikator kecurangan yang sudah cukup untuk diprioritaskan pengawas.</p>
                </div>
                <div class="cbt-setup-security-log-watch-head-actions">
                    <div class="cbt-setup-security-log-watch-sort" role="group" aria-label="Urutkan Must Watch">
                        <button type="button" class="cbt-setup-security-log-watch-sort-button is-active" data-security-log-watch-sort="auto" aria-pressed="true">Auto</button>
                        <button type="button" class="cbt-setup-security-log-watch-sort-button" data-security-log-watch-sort="score" aria-pressed="false">Skor tertinggi</button>
                        <button type="button" class="cbt-setup-security-log-watch-sort-button" data-security-log-watch-sort="recent" aria-pressed="false">Terbaru</button>
                    </div>
                    <span class="cbt-setup-card-chip"><?php echo esc_html(sprintf('%d aktif diawasi', $must_watch_total)); ?></span>
                </div>
            </div>

            <?php if (empty($must_watch_attempts)): ?>
                <div class="cbt-setup-security-log-watch-empty">Belum ada attempt aktif yang masuk Must Watch.</div>
            <?php else: ?>
                <div class="cbt-setup-security-log-watch-list">
                    <?php foreach ($must_watch_attempts as $must_watch_index => $must_watch_attempt): ?>
                        <?php
                        $attempt_id = (int) ($must_watch_attempt['attempt_id'] ?? 0);
                        $exam_id = (int) ($must_watch_attempt['exam_id'] ?? 0);
                        $student_name = (string) ($must_watch_attempt['student_name'] ?? '-');
                        $student_login = trim((string) ($must_watch_attempt['student_login'] ?? ''));
                        $student_kelas = sanitize_text_field((string) ($must_watch_attempt['student_kode_kelas'] ?? ''));
                        $student_ruang = sanitize_text_field((string) ($must_watch_attempt['student_kode_ruang'] ?? ''));
                        $exam_title = trim((string) ($must_watch_attempt['exam_title'] ?? '')) !== ''
                            ? (string) $must_watch_attempt['exam_title']
                            : ($exam_id > 0 ? ('Exam #' . $exam_id) : '-');
                        $risk_score = max(0, (int) ($must_watch_attempt['risk_score'] ?? 0));
                        $risk_tone = sanitize_key((string) ($must_watch_attempt['risk_tone'] ?? 'watch'));
                        if (!in_array($risk_tone, ['watch', 'high-risk'], true)) {
                            $risk_tone = 'watch';
                        }
                        $risk_label = trim((string) ($must_watch_attempt['risk_label'] ?? 'Must Watch'));
                        if ($risk_label === '') {
                            $risk_label = $risk_tone === 'high-risk' ? 'High Risk' : 'Must Watch';
                        }
                        $primary_event_type = sanitize_key((string) ($must_watch_attempt['primary_event_type'] ?? ''));
                        $primary_event_label = trim((string) ($must_watch_attempt['primary_event_label'] ?? ''));
                        $last_event_at = (string) ($must_watch_attempt['last_event_at'] ?? '-');
                        $last_device_type = sanitize_key((string) ($must_watch_attempt['last_device_type'] ?? 'unknown'));
                        if (!in_array($last_device_type, ['desktop', 'mobile', 'tablet', 'server', 'unknown'], true)) {
                            $last_device_type = 'unknown';
                        }
                        $last_device_label = trim((string) ($must_watch_attempt['last_device_label'] ?? 'Unknown'));
                        if ($last_device_label === '') {
                            $last_device_label = 'Unknown';
                        }
                        $last_device_summary = trim((string) ($must_watch_attempt['last_device_summary'] ?? $last_device_label));
                        $top_indicators = array_values(array_filter(array_map('strval', (array) ($must_watch_attempt['top_indicators'] ?? []))));
                        $results_args = [
                            'page' => 'cbt-results',
                            'cbt_attempt_status' => 'in_progress',
                        ];
                        if ($exam_id > 0) {
                            $results_args['cbt_exam_id'] = $exam_id;
                        }
                        if ($student_login !== '') {
                            $results_args['cbt_student_q'] = $student_login;
                        }
                        $results_url = add_query_arg($results_args, admin_url('admin.php'));
                        ?>
                        <article
                            class="cbt-setup-security-log-watch-item is-<?php echo esc_attr($risk_tone); ?>"
                            data-security-log-focus-card
                            data-focus-attempt="<?php echo esc_attr((string) $attempt_id); ?>"
                            data-focus-student="<?php echo esc_attr($student_name); ?>"
                            data-focus-kelas="<?php echo esc_attr($student_kelas); ?>"
                            data-focus-ruang="<?php echo esc_attr($student_ruang); ?>"
                            data-focus-event="<?php echo esc_attr($primary_event_type); ?>"
                            data-focus-event-label="<?php echo esc_attr($primary_event_label); ?>"
                            data-sort-order="<?php echo esc_attr((string) ((int) $must_watch_index)); ?>"
                            data-sort-score="<?php echo esc_attr((string) $risk_score); ?>"
                            data-sort-last-at="<?php echo esc_attr($last_event_at); ?>"
                            title="Klik untuk fokus ke histori log attempt ini."
                        >
                            <div class="cbt-setup-security-log-watch-item-top">
                                <div class="cbt-setup-security-log-watch-item-student">
                                    <strong><?php echo esc_html($student_name); ?></strong>
                                </div>
                                <div class="cbt-setup-security-log-watch-item-side">
                                    <span class="cbt-setup-security-log-badge is-<?php echo esc_attr($risk_tone); ?>"><?php echo esc_html($risk_label); ?></span>
                                    <span class="cbt-setup-security-log-badge is-score"><?php echo esc_html('Skor ' . $risk_score); ?></span>
                                    <span class="cbt-setup-security-log-badge is-device-<?php echo esc_attr($last_device_type); ?>"><?php echo esc_html($last_device_label); ?></span>
                                </div>
                            </div>

                            <?php if ($student_kelas !== '' || $student_ruang !== ''): ?>
                                <div class="cbt-setup-security-log-student-meta">
                                    <?php if ($student_kelas !== ''): ?>
                                        <span class="is-kelas"><strong>K:</strong> <?php echo esc_html($student_kelas); ?></span>
                                    <?php endif; ?>
                                    <?php if ($student_ruang !== ''): ?>
                                        <span class="is-ruang"><strong>R:</strong> <?php echo esc_html($student_ruang); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="cbt-setup-security-log-watch-item-copy">
                                <div class="cbt-setup-security-log-watch-item-summary"><strong>Exam:</strong> <?php echo esc_html($exam_title); ?></div>
                                <div class="cbt-setup-security-log-watch-item-meta">
                                    <span><strong>Attempt:</strong> <?php echo esc_html('#' . $attempt_id); ?></span>
                                    <span><strong>Terakhir:</strong> <?php echo esc_html($last_event_at); ?></span>
                                </div>
                                <div class="cbt-setup-security-log-watch-item-device"><?php echo esc_html($last_device_summary); ?></div>
                            </div>

                            <?php if (!empty($top_indicators)): ?>
                                <div class="cbt-setup-security-log-watch-item-indicators">
                                    <?php foreach ($top_indicators as $indicator): ?>
                                        <span class="cbt-setup-security-log-watch-indicator"><?php echo esc_html($indicator); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="cbt-setup-security-log-watch-item-actions">
                                <a class="button button-secondary button-small" href="<?php echo esc_url($results_url); ?>" target="_blank" rel="noopener noreferrer">Buka Results</a>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Reset login siswa ini dari panel Must Watch? Semua browser aktif user ini akan diminta login ulang.');">
                                    <?php wp_nonce_field('cbt_reset_user_login_' . $attempt_id); ?>
                                    <input type="hidden" name="action" value="cbt_reset_user_login" />
                                    <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt_id; ?>" />
                                    <input type="hidden" name="return_page" value="cbt-security" />
                                    <input type="hidden" name="return_hash" value="security-log" />
                                    <button class="button button-small" type="submit">Reset Login</button>
                                </form>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Paksa attempt ini selesai sekarang? Attempt tidak bisa dilanjutkan lagi oleh siswa.');">
                                    <?php wp_nonce_field('cbt_force_complete_attempt_' . $attempt_id); ?>
                                    <input type="hidden" name="action" value="cbt_force_complete_attempt" />
                                    <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt_id; ?>" />
                                    <input type="hidden" name="return_page" value="cbt-security" />
                                    <input type="hidden" name="return_hash" value="security-log" />
                                    <button class="button button-primary button-small" type="submit">Force Complete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }
}
