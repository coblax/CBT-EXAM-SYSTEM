<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-live-attempt-roster-index.php';
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
     * @param array<int,array<string,mixed>> $groups
     */
    public static function render_security_log_live_roster_panel(array $groups): void
    {
        $active_total = 0;
        foreach ($groups as $group) {
            $active_total += max(0, (int) ($group['active_total'] ?? 0));
        }
        ?>
        <section class="cbt-setup-security-log-roster" data-security-log-live-roster>
            <div class="cbt-setup-security-log-roster-header">
                <div>
                    <h3>Live Roster</h3>
                    <p>Attempt aktif yang sedang berjalan, dikelompokkan berdasarkan exam, kelas, dan ruang untuk monitoring cepat pengawas.</p>
                </div>
                <span class="cbt-setup-card-chip"><?php echo esc_html(sprintf('%d attempt aktif', $active_total)); ?></span>
            </div>

            <?php if (empty($groups)): ?>
                <div class="cbt-setup-security-log-roster-empty">Belum ada attempt aktif yang masuk roster live saat ini.</div>
            <?php else: ?>
                <div class="cbt-setup-security-log-roster-groups">
                    <?php foreach ($groups as $group): ?>
                        <?php
                        $exam_title = trim((string) ($group['exam_title'] ?? '')) !== ''
                            ? (string) $group['exam_title']
                            : 'Exam #' . (int) ($group['exam_id'] ?? 0);
                        $kelas_label = trim((string) ($group['kelas_label'] ?? '')) !== ''
                            ? (string) $group['kelas_label']
                            : 'Tanpa Kelas';
                        $ruang_label = trim((string) ($group['ruang_label'] ?? '')) !== ''
                            ? (string) $group['ruang_label']
                            : 'Tanpa Ruang';
                        $rows = isset($group['attempts']) && is_array($group['attempts']) ? $group['attempts'] : [];
                        ?>
                        <article class="cbt-setup-security-log-roster-group" data-security-log-roster-group>
                            <div class="cbt-setup-security-log-roster-group-top">
                                <div class="cbt-setup-security-log-roster-group-copy">
                                    <div class="cbt-setup-security-log-roster-group-title"><?php echo esc_html($exam_title); ?></div>
                                    <div class="cbt-setup-security-log-roster-group-meta">
                                        <span><strong>Kelas:</strong> <?php echo esc_html($kelas_label); ?></span>
                                        <span><strong>Ruang:</strong> <?php echo esc_html($ruang_label); ?></span>
                                    </div>
                                </div>
                                <div class="cbt-setup-security-log-roster-group-counters">
                                    <span class="cbt-setup-security-log-watch-indicator">Aktif <?php echo esc_html((string) max(0, (int) ($group['active_total'] ?? 0))); ?></span>
                                    <span class="cbt-setup-security-log-watch-indicator is-presence">Online <?php echo esc_html((string) max(0, (int) ($group['online_total'] ?? 0))); ?></span>
                                    <?php if ((int) ($group['stale_total'] ?? 0) > 0): ?>
                                        <span class="cbt-setup-security-log-watch-indicator is-roster-stale">Stale <?php echo esc_html((string) max(0, (int) ($group['stale_total'] ?? 0))); ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) ($group['offline_total'] ?? 0) > 0): ?>
                                        <span class="cbt-setup-security-log-watch-indicator is-roster-offline">Offline <?php echo esc_html((string) max(0, (int) ($group['offline_total'] ?? 0))); ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) ($group['watch_total'] ?? 0) > 0): ?>
                                        <span class="cbt-setup-security-log-watch-indicator is-roster-watch">Watch <?php echo esc_html((string) max(0, (int) ($group['watch_total'] ?? 0))); ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) ($group['high_risk_total'] ?? 0) > 0): ?>
                                        <span class="cbt-setup-security-log-watch-indicator is-roster-risk">High Risk <?php echo esc_html((string) max(0, (int) ($group['high_risk_total'] ?? 0))); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="cbt-setup-security-log-roster-list">
                                <?php foreach ($rows as $row): ?>
                                    <?php self::render_security_log_live_roster_row($row); ?>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
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
                    <p>Attempt aktif dengan status live terakhir dan pelanggaran dominan yang sudah cukup untuk diprioritaskan pengawas.</p>
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
                        $risk_score = max(0, (float) ($must_watch_attempt['risk_score'] ?? 0));
                        $risk_score_display = CBT_Security_Log::format_risk_score($risk_score);
                        $risk_score_raw = CBT_Security_Log::format_risk_score_raw($risk_score);
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
                        $presence_status = sanitize_key((string) ($must_watch_attempt['presence_status'] ?? ''));
                        if (!in_array($presence_status, ['online', 'stale', 'offline'], true)) {
                            $presence_status = '';
                        }
                        $presence_status_label = $presence_status === 'online'
                            ? 'Online'
                            : ($presence_status === 'stale' ? 'Stale' : ($presence_status === 'offline' ? 'Offline' : ''));
                        $presence_last_seen_at = trim((string) ($must_watch_attempt['presence_last_seen_at'] ?? ''));
                        $presence_connection_status = strtolower(trim((string) ($must_watch_attempt['presence_connection_status'] ?? '')));
                        $presence_visibility_state = strtolower(trim((string) ($must_watch_attempt['presence_visibility_state'] ?? '')));
                        $presence_has_focus = array_key_exists('presence_has_focus', $must_watch_attempt)
                            && $must_watch_attempt['presence_has_focus'] !== null
                            ? (int) $must_watch_attempt['presence_has_focus']
                            : -1;
                        $presence_pending_sync_count = max(0, (int) ($must_watch_attempt['presence_pending_sync_count'] ?? 0));
                        $presence_heartbeat_lost_active = !empty($must_watch_attempt['presence_heartbeat_lost_active']);
                        $presence_indicators = [];
                        if ($presence_pending_sync_count > 0) {
                            $presence_indicators[] = 'Sync ' . $presence_pending_sync_count;
                        }
                        if ($presence_visibility_state === 'hidden') {
                            $presence_indicators[] = 'Tab Hidden';
                        }
                        if ($presence_status !== '' && $presence_has_focus === 0) {
                            $presence_indicators[] = 'Focus Off';
                        }
                        if ($presence_heartbeat_lost_active) {
                            $presence_indicators[] = 'Heartbeat Lost';
                        }
                        if ($presence_connection_status !== '' && $presence_connection_status !== 'online') {
                            $presence_indicators[] = 'Conn ' . strtoupper(str_replace('_', ' ', $presence_connection_status));
                        }
                        $top_indicators = array_values(array_filter(array_map('strval', (array) ($must_watch_attempt['top_indicators'] ?? []))));
                        $has_live_group = $presence_last_seen_at !== '' || !empty($presence_indicators);
                        $has_history_group = !empty($top_indicators);
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
                            data-sort-score="<?php echo esc_attr($risk_score_raw); ?>"
                            data-sort-last-at="<?php echo esc_attr($last_event_at); ?>"
                            title="Klik untuk fokus ke histori log attempt ini."
                        >
                            <div class="cbt-setup-security-log-watch-item-top">
                                <div class="cbt-setup-security-log-watch-item-student">
                                    <strong><?php echo esc_html($student_name); ?></strong>
                                </div>
                                <div class="cbt-setup-security-log-watch-item-side">
                                    <span class="cbt-setup-security-log-badge is-<?php echo esc_attr($risk_tone); ?>"><?php echo esc_html($risk_label); ?></span>
                                    <span class="cbt-setup-security-log-badge is-score"><?php echo esc_html('Skor ' . $risk_score_display); ?></span>
                                    <span class="cbt-setup-security-log-badge is-device-<?php echo esc_attr($last_device_type); ?>"><?php echo esc_html($last_device_label); ?></span>
                                    <?php if ($presence_status !== ''): ?>
                                        <span class="cbt-setup-security-log-badge is-presence-<?php echo esc_attr($presence_status); ?>"><?php echo esc_html($presence_status_label); ?></span>
                                    <?php endif; ?>
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

                            <?php if ($has_live_group || $has_history_group): ?>
                                <div class="cbt-setup-security-log-watch-item-groups">
                                    <?php if ($has_live_group): ?>
                                        <section class="cbt-setup-security-log-watch-item-group is-live" aria-label="Status Live">
                                            <div class="cbt-setup-security-log-watch-item-group-label">Status Live</div>
                                            <?php if ($presence_last_seen_at !== ''): ?>
                                                <div class="cbt-setup-security-log-watch-item-group-meta">
                                                    <span><strong>Terlihat terakhir:</strong> <?php echo esc_html($presence_last_seen_at); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($presence_indicators)): ?>
                                                <div class="cbt-setup-security-log-watch-item-presence-indicators">
                                                    <?php foreach ($presence_indicators as $presence_indicator): ?>
                                                        <span class="cbt-setup-security-log-watch-indicator is-presence"><?php echo esc_html($presence_indicator); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </section>
                                    <?php endif; ?>

                                    <?php if ($has_history_group): ?>
                                        <section class="cbt-setup-security-log-watch-item-group is-history" aria-label="Pelanggaran Dominan">
                                            <div class="cbt-setup-security-log-watch-item-group-label">Pelanggaran Dominan</div>
                                            <div class="cbt-setup-security-log-watch-item-indicators">
                                                <?php foreach ($top_indicators as $indicator): ?>
                                                    <span class="cbt-setup-security-log-watch-indicator"><?php echo esc_html($indicator); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </section>
                                    <?php endif; ?>
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

    /**
     * @param array<string,mixed> $row
     */
    private static function render_security_log_live_roster_row(array $row): void
    {
        $attempt_id = (int) ($row['attempt_id'] ?? 0);
        $exam_id = (int) ($row['exam_id'] ?? 0);
        $student_name = trim((string) ($row['student_name'] ?? '')) !== ''
            ? (string) $row['student_name']
            : (trim((string) ($row['student_login'] ?? '')) !== '' ? (string) $row['student_login'] : 'Siswa');
        $student_login = trim((string) ($row['student_login'] ?? ''));
        $presence_status = sanitize_key((string) ($row['presence_status'] ?? ''));
        if (!in_array($presence_status, ['online', 'stale', 'offline'], true)) {
            $presence_status = '';
        }
        $presence_status_label = $presence_status === 'online'
            ? 'Online'
            : ($presence_status === 'stale' ? 'Stale' : ($presence_status === 'offline' ? 'Offline' : ''));
        $presence_last_seen_at = trim((string) ($row['last_seen_at'] ?? ''));
        $presence_connection_status = strtolower(trim((string) ($row['connection_status'] ?? '')));
        $presence_visibility_state = strtolower(trim((string) ($row['visibility_state'] ?? '')));
        $presence_has_focus = array_key_exists('has_focus', $row) ? (int) ($row['has_focus'] ?? 0) : -1;
        $presence_pending_sync_count = max(0, (int) ($row['pending_sync_count'] ?? 0));
        $presence_heartbeat_lost_active = !empty($row['heartbeat_lost_active']);
        $risk_tone = sanitize_key((string) ($row['risk_tone'] ?? ''));
        if (!in_array($risk_tone, ['watch', 'high-risk'], true)) {
            $risk_tone = '';
        }
        $risk_label = $risk_tone === 'high-risk' ? 'High Risk' : ($risk_tone === 'watch' ? 'Watch' : '');
        $risk_score = max(0, (float) ($row['risk_score'] ?? 0));
        $presence_indicators = self::build_roster_presence_indicators([
            'presence_connection_status' => $presence_connection_status,
            'presence_visibility_state' => $presence_visibility_state,
            'presence_has_focus' => $presence_has_focus,
            'presence_pending_sync_count' => $presence_pending_sync_count,
            'presence_heartbeat_lost_active' => $presence_heartbeat_lost_active ? 1 : 0,
            'presence_status' => $presence_status,
        ]);
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
        <div class="cbt-setup-security-log-roster-row" data-security-log-roster-row>
            <div class="cbt-setup-security-log-roster-row-top">
                <div class="cbt-setup-security-log-roster-row-copy">
                    <strong><?php echo esc_html($student_name); ?></strong>
                    <?php if ($student_login !== ''): ?>
                        <span><?php echo esc_html($student_login); ?></span>
                    <?php endif; ?>
                </div>
                <div class="cbt-setup-security-log-roster-row-side">
                    <?php if ($presence_status !== ''): ?>
                        <span class="cbt-setup-security-log-badge is-presence-<?php echo esc_attr($presence_status); ?>"><?php echo esc_html($presence_status_label); ?></span>
                    <?php endif; ?>
                    <?php if ($risk_label !== ''): ?>
                        <span class="cbt-setup-security-log-badge is-<?php echo esc_attr($risk_tone); ?>"><?php echo esc_html($risk_label); ?></span>
                    <?php endif; ?>
                    <?php if ($risk_score > 0): ?>
                        <span class="cbt-setup-security-log-badge is-score"><?php echo esc_html('Skor ' . CBT_Security_Log::format_risk_score($risk_score)); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cbt-setup-security-log-roster-row-meta">
                <span><strong>Attempt:</strong> <?php echo esc_html('#' . $attempt_id); ?></span>
                <?php if ($presence_last_seen_at !== ''): ?>
                    <span><strong>Seen:</strong> <?php echo esc_html($presence_last_seen_at); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($presence_indicators)): ?>
                <div class="cbt-setup-security-log-roster-row-indicators">
                    <?php foreach ($presence_indicators as $indicator): ?>
                        <span class="cbt-setup-security-log-watch-indicator"><?php echo esc_html($indicator); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="cbt-setup-security-log-roster-row-actions">
                <a class="button button-secondary button-small" href="<?php echo esc_url($results_url); ?>" target="_blank" rel="noopener noreferrer">Buka Results</a>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    private static function build_roster_presence_indicators(array $row): array
    {
        $indicators = [];
        $pending_sync_count = max(0, (int) ($row['presence_pending_sync_count'] ?? 0));
        if ($pending_sync_count > 0) {
            $indicators[] = 'Sync ' . $pending_sync_count;
        }

        if ((string) ($row['presence_visibility_state'] ?? '') === 'hidden') {
            $indicators[] = 'Hidden';
        }

        if ((string) ($row['presence_status'] ?? '') !== '' && (int) ($row['presence_has_focus'] ?? 1) === 0) {
            $indicators[] = 'Focus Off';
        }

        if (!empty($row['presence_heartbeat_lost_active'])) {
            $indicators[] = 'Heartbeat';
        }

        $connection_status = (string) ($row['presence_connection_status'] ?? '');
        if ($connection_status !== '' && $connection_status !== 'online') {
            $indicators[] = 'Conn ' . strtoupper(str_replace('_', ' ', $connection_status));
        }

        return $indicators;
    }
}
