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
     * @param array<int,array<string,mixed>> $security_logs
     * @param array<string,array{label:string,severity:string,message:string}> $security_log_event_definitions
     */
    public static function render_security_log_history_table_region(array $security_logs, array $security_log_event_definitions = []): void
    {
        ?>
        <div class="cbt-setup-security-log-table-shell">
            <table class="widefat striped cbt-setup-security-log-table">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" data-security-log-select-all /></th>
                        <th>Waktu</th>
                        <th>Siswa</th>
                        <th>Exam</th>
                        <th>Attempt</th>
                        <th>Event</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody id="cbt-setup-security-log-tbody">
                    <?php if (empty($security_logs)): ?>
                        <tr data-security-log-empty-default>
                            <td colspan="7" class="cbt-setup-security-log-empty">Belum ada histori security log yang tercatat.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($security_logs as $security_log): ?>
                            <?php
                            $security_log_id = (int) ($security_log['id'] ?? 0);
                            $severity = sanitize_key((string) ($security_log['severity'] ?? 'info'));
                            if (!in_array($severity, ['warning', 'critical', 'info'], true)) {
                                $severity = 'info';
                            }
                            $event_type = sanitize_key((string) ($security_log['event_type'] ?? ''));
                            $device_type = sanitize_key((string) ($security_log['device_type'] ?? 'unknown'));
                            if (!in_array($device_type, ['desktop', 'mobile', 'tablet', 'server', 'unknown'], true)) {
                                $device_type = 'unknown';
                            }
                            $device_label = trim((string) ($security_log['device_label'] ?? 'Unknown'));
                            if ($device_label === '') {
                                $device_label = 'Unknown';
                            }
                            $device_summary = trim((string) ($security_log['device_summary'] ?? $device_label));
                            $student_kelas = sanitize_text_field((string) ($security_log['student_kode_kelas'] ?? ''));
                            $student_ruang = sanitize_text_field((string) ($security_log['student_kode_ruang'] ?? ''));
                            $student_name = (string) ($security_log['student_name'] ?? '-');
                            $security_log_context = isset($security_log['context']) && is_array($security_log['context'])
                                ? $security_log['context']
                                : [];
                            $security_log_json_context = $security_log_context;
                            $security_log_json_payload = [
                                'attempt_id' => (int) ($security_log['attempt_id'] ?? 0),
                                'event_type' => $event_type,
                            ];
                            $security_log_native_app = sanitize_key((string) ($security_log_context['native_app'] ?? ''));

                            if ($security_log_native_app !== '') {
                                $security_log_json_payload['native_app'] = $security_log_native_app;

                                if (!empty($security_log_context['native_version'])) {
                                    $security_log_json_payload['native_version'] = (string) $security_log_context['native_version'];
                                }
                                if (!empty($security_log_context['warning_code'])) {
                                    $security_log_json_payload['warning_code'] = (string) $security_log_context['warning_code'];
                                }
                                if (!empty($security_log_context['warning_message'])) {
                                    $security_log_json_payload['warning_message'] = (string) $security_log_context['warning_message'];
                                }
                                if (!empty($security_log_context['occurred_at_client'])) {
                                    $security_log_json_payload['occurred_at_client'] = (string) $security_log_context['occurred_at_client'];
                                }

                                unset(
                                    $security_log_json_context['native_app'],
                                    $security_log_json_context['native_version'],
                                    $security_log_json_context['warning_code'],
                                    $security_log_json_context['warning_message'],
                                    $security_log_json_context['occurred_at_client']
                                );
                            }

                            $security_log_json_payload['context'] = !empty($security_log_json_context)
                                ? $security_log_json_context
                                : new stdClass();
                            $security_log_json_pretty = wp_json_encode(
                                $security_log_json_payload,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                            );
                            if (!is_string($security_log_json_pretty) || $security_log_json_pretty === '') {
                                $security_log_json_pretty = '{}';
                            }
                            ?>
                            <tr
                                data-security-log-row
                                data-log-severity="<?php echo esc_attr($severity); ?>"
                                data-log-event="<?php echo esc_attr($event_type); ?>"
                                data-log-device="<?php echo esc_attr($device_type); ?>"
                                data-log-device-label="<?php echo esc_attr($device_label); ?>"
                                data-log-kelas="<?php echo esc_attr($student_kelas); ?>"
                                data-log-ruang="<?php echo esc_attr($student_ruang); ?>"
                                data-log-student-name="<?php echo esc_attr(function_exists('mb_strtolower') ? mb_strtolower($student_name, 'UTF-8') : strtolower($student_name)); ?>"
                                data-log-attempt="<?php echo esc_attr((string) ((int) ($security_log['attempt_id'] ?? 0))); ?>"
                            >
                                <td class="check-column">
                                    <input type="checkbox" name="selected_log_ids[]" value="<?php echo esc_attr((string) $security_log_id); ?>" data-security-log-select />
                                </td>
                                <td><?php echo esc_html((string) ($security_log['occurred_at'] ?? '-')); ?></td>
                                <td>
                                    <div class="cbt-setup-security-log-student">
                                        <div class="cbt-setup-security-log-student-name"><?php echo esc_html($student_name); ?></div>
                                        <?php if (!empty($security_log['student_kode_kelas']) || !empty($security_log['student_kode_ruang'])): ?>
                                            <div class="cbt-setup-security-log-student-meta">
                                                <?php if ($student_kelas !== ''): ?>
                                                    <span class="is-kelas"><strong>K:</strong> <?php echo esc_html($student_kelas); ?></span>
                                                <?php endif; ?>
                                                <?php if ($student_ruang !== ''): ?>
                                                    <span class="is-ruang"><strong>R:</strong> <?php echo esc_html($student_ruang); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html((string) ($security_log['exam_title'] ?? '-')); ?></td>
                                <td><span class="cbt-setup-security-log-attempt">#<?php echo esc_html((string) ((int) ($security_log['attempt_id'] ?? 0))); ?></span></td>
                                <td>
                                    <div class="cbt-setup-security-log-event">
                                        <div class="cbt-setup-security-log-event-badges">
                                            <span class="cbt-setup-security-log-badge is-<?php echo esc_attr($severity); ?>"><?php echo esc_html($severity); ?></span>
                                            <span class="cbt-setup-security-log-badge is-device-<?php echo esc_attr($device_type); ?>"><?php echo esc_html($device_label); ?></span>
                                        </div>
                                        <strong><?php echo esc_html((string) ($security_log['event_label'] ?? ($security_log_event_definitions[$event_type]['label'] ?? $security_log['event_type'] ?? 'Event'))); ?></strong>
                                        <span class="cbt-setup-security-log-event-meta"><?php echo esc_html($device_summary); ?></span>
                                    </div>
                                </td>
                                <td class="cbt-setup-security-log-detail">
                                    <p class="cbt-setup-security-log-detail-copy"><?php echo esc_html((string) ($security_log['message_display'] ?? $security_log['message'] ?? '-')); ?></p>
                                    <details class="cbt-setup-security-log-json">
                                        <summary>JSON</summary>
                                        <pre class="cbt-setup-security-log-json-pre"><?php echo esc_html($security_log_json_pretty); ?></pre>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="cbt-setup-security-log-filter-empty" hidden>
                            <td colspan="7" class="cbt-setup-security-log-empty">Tidak ada histori log yang cocok dengan filter saat ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $status_snapshot
     */
    public static function render_security_log_redis_monitor_panel(array $status_snapshot): void
    {
        $feature_enabled = !empty($status_snapshot['feature_enabled']);
        $available = !empty($status_snapshot['available']);
        $stream_supported = !empty($status_snapshot['stream_supported']);
        $worker_scheduled = !empty($status_snapshot['worker_scheduled']);
        $backlog_count = max(0, (int) ($status_snapshot['backlog_count'] ?? 0));
        $dead_letter_count = max(0, (int) ($status_snapshot['dead_letter_count'] ?? 0));
        $oldest_pending_age_seconds = max(0, (int) ($status_snapshot['oldest_pending_age_seconds'] ?? 0));
        $last_stream_id = sanitize_text_field((string) ($status_snapshot['last_stream_id'] ?? ''));
        $last_enqueue_at = sanitize_text_field((string) ($status_snapshot['last_enqueue_at'] ?? ''));
        $last_enqueue_status = sanitize_key((string) ($status_snapshot['last_enqueue_status'] ?? ''));
        $last_enqueue_error = sanitize_text_field((string) ($status_snapshot['last_enqueue_error'] ?? ''));
        $last_flush_at = sanitize_text_field((string) ($status_snapshot['last_flush_at'] ?? ''));
        $last_flush_status = sanitize_key((string) ($status_snapshot['last_flush_status'] ?? ''));
        $last_flush_result = sanitize_text_field((string) ($status_snapshot['last_flush_result'] ?? ''));
        $next_flush_at = sanitize_text_field((string) ($status_snapshot['next_flush_at'] ?? ''));
        $live_label = sanitize_text_field((string) ($status_snapshot['live_label'] ?? 'Live MySQL fallback'));
        $ingest_label = sanitize_text_field((string) ($status_snapshot['ingest_label'] ?? 'Ingest direct MySQL'));
        $persist_label = sanitize_text_field((string) ($status_snapshot['persist_label'] ?? 'Persist direct MySQL'));
        $status_label = sanitize_text_field((string) ($status_snapshot['status_label'] ?? ''));
        $can_run_actions = $feature_enabled && $available;
        $disabled_reason = $feature_enabled
            ? ($available ? '' : 'Redis ingest tidak tersedia saat ini.')
            : 'Feature flag Redis-first ingest masih nonaktif.';
        $monitor_tone = 'healthy';
        if ($feature_enabled && !$available) {
            $monitor_tone = 'critical';
        } elseif (!$feature_enabled || $dead_letter_count > 0 || $backlog_count > 0) {
            $monitor_tone = 'warning';
        }
        $helper_text = $feature_enabled && $available
            ? 'Redis-first aktif. Audit permanen menyusul lewat batch flush.'
            : 'Mode fallback aktif. Event tetap aman ditulis ke MySQL langsung.';
        $last_result = $last_enqueue_error !== ''
            ? $last_enqueue_error
            : ($last_flush_result !== '' ? $last_flush_result : '-');
        $diagnostics_json = wp_json_encode($status_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($diagnostics_json) || $diagnostics_json === '') {
            $diagnostics_json = '{}';
        }
        ?>
        <section class="cbt-setup-security-log-monitor is-<?php echo esc_attr($monitor_tone); ?>" data-security-log-monitor>
            <div class="cbt-setup-security-log-monitor-top">
                <div>
                    <h3>Redis Monitor</h3>
                    <p data-security-log-monitor-helper><?php echo esc_html($helper_text); ?></p>
                </div>
                <div class="cbt-setup-security-log-monitor-actions">
                    <button type="button" class="button cbt-admin-btn--secondary" data-security-log-monitor-action="refresh_monitor">Refresh Monitor</button>
                    <button type="button" class="button cbt-admin-btn--success" data-security-log-monitor-action="micro_drain"<?php echo $can_run_actions ? '' : ' disabled'; ?>>Run Micro-Drain</button>
                    <button type="button" class="button button-primary cbt-admin-btn--warning" data-security-log-monitor-action="flush_now"<?php echo $can_run_actions ? '' : ' disabled'; ?>>Force Flush Now</button>
                    <button type="button" class="button cbt-admin-btn--danger" data-security-log-monitor-action="clear_live_state">Clear Live Roster</button>
                    <button type="button" class="button cbt-admin-btn--ghost" data-security-log-monitor-action="copy_diagnostics">Copy Diagnostics</button>
                </div>
            </div>
            <div class="cbt-setup-security-log-monitor-grid">
                <div class="cbt-setup-security-log-monitor-card">
                    <h4>Mode</h4>
                    <dl class="cbt-setup-security-log-monitor-list">
                        <div><dt>Live</dt><dd data-security-log-monitor-field="live_label"><?php echo esc_html($live_label); ?></dd></div>
                        <div><dt>Ingest</dt><dd data-security-log-monitor-field="ingest_label"><?php echo esc_html($ingest_label); ?></dd></div>
                        <div><dt>Persist</dt><dd data-security-log-monitor-field="persist_label"><?php echo esc_html($persist_label); ?></dd></div>
                    </dl>
                </div>
                <div class="cbt-setup-security-log-monitor-card">
                    <h4>Health</h4>
                    <dl class="cbt-setup-security-log-monitor-list">
                        <div><dt>Feature Flag</dt><dd data-security-log-monitor-field="feature_enabled"><?php echo esc_html($feature_enabled ? 'On' : 'Off'); ?></dd></div>
                        <div><dt>Redis Available</dt><dd data-security-log-monitor-field="available"><?php echo esc_html($available ? 'Yes' : 'No'); ?></dd></div>
                        <div><dt>Stream Supported</dt><dd data-security-log-monitor-field="stream_supported"><?php echo esc_html($stream_supported ? 'Yes' : 'No'); ?></dd></div>
                        <div><dt>Worker Scheduled</dt><dd data-security-log-monitor-field="worker_scheduled"><?php echo esc_html($worker_scheduled ? 'Yes' : 'No'); ?></dd></div>
                    </dl>
                </div>
                <div class="cbt-setup-security-log-monitor-card">
                    <h4>Queue</h4>
                    <dl class="cbt-setup-security-log-monitor-list">
                        <div><dt>Backlog</dt><dd data-security-log-monitor-field="backlog_count"><?php echo esc_html((string) $backlog_count); ?></dd></div>
                        <div><dt>Oldest Pending</dt><dd data-security-log-monitor-field="oldest_pending"><?php echo esc_html(self::format_duration_seconds($oldest_pending_age_seconds)); ?></dd></div>
                        <div><dt>Dead Letter</dt><dd data-security-log-monitor-field="dead_letter_count"><?php echo esc_html((string) $dead_letter_count); ?></dd></div>
                        <div><dt>Last Stream ID</dt><dd data-security-log-monitor-field="last_stream_id"><?php echo esc_html($last_stream_id !== '' ? $last_stream_id : '-'); ?></dd></div>
                    </dl>
                </div>
                <div class="cbt-setup-security-log-monitor-card">
                    <h4>Activity</h4>
                    <dl class="cbt-setup-security-log-monitor-list">
                        <div><dt>Last Enqueue</dt><dd data-security-log-monitor-field="last_enqueue"><?php echo esc_html(self::format_activity_value($last_enqueue_at, $last_enqueue_status)); ?></dd></div>
                        <div><dt>Last Flush</dt><dd data-security-log-monitor-field="last_flush"><?php echo esc_html(self::format_activity_value($last_flush_at, $last_flush_status)); ?></dd></div>
                        <div><dt>Next Flush</dt><dd data-security-log-monitor-field="next_flush_at"><?php echo esc_html($next_flush_at !== '' ? $next_flush_at : '-'); ?></dd></div>
                        <div><dt>Last Error/Result</dt><dd data-security-log-monitor-field="last_result"><?php echo esc_html($last_result); ?></dd></div>
                    </dl>
                </div>
            </div>
            <div class="cbt-setup-security-log-monitor-footer">
                <span class="cbt-setup-security-log-monitor-status" data-security-log-monitor-status><?php echo esc_html($status_label !== '' ? $status_label : 'Status monitor siap.'); ?></span>
                <span class="cbt-setup-security-log-monitor-disabled"<?php echo $disabled_reason !== '' ? '' : ' hidden'; ?> data-security-log-monitor-disabled-reason><?php echo esc_html($disabled_reason); ?></span>
            </div>
            <pre class="cbt-setup-security-log-monitor-diagnostics" data-security-log-monitor-diagnostics hidden><?php echo esc_html($diagnostics_json); ?></pre>
        </section>
        <?php
    }

    /**
     * @param array<int,array<string,mixed>> $groups
     */
    public static function render_security_log_live_roster_panel(array $groups): void
    {
        $active_total = 0;
        $exam_options = [];
        $kelas_options = [];
        $ruang_options = [];
        foreach ($groups as $group) {
            $active_total += max(0, (int) ($group['active_total'] ?? 0));
            $exam_title = trim((string) ($group['exam_title'] ?? '')) !== ''
                ? (string) $group['exam_title']
                : 'Exam #' . (int) ($group['exam_id'] ?? 0);
            $kelas_label = trim((string) ($group['kelas_label'] ?? '')) !== ''
                ? (string) $group['kelas_label']
                : 'Tanpa Kelas';
            $ruang_label = trim((string) ($group['ruang_label'] ?? '')) !== ''
                ? (string) $group['ruang_label']
                : 'Tanpa Ruang';

            $exam_options[$exam_title] = $exam_title;
            $kelas_options[$kelas_label] = $kelas_label;
            $ruang_options[$ruang_label] = $ruang_label;
        }
        natcasesort($exam_options);
        natcasesort($kelas_options);
        natcasesort($ruang_options);
        ?>
        <section class="cbt-setup-security-log-roster" data-security-log-live-roster data-security-log-roster-page-size="4">
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
                <div class="cbt-setup-security-log-roster-toolbar">
                    <div class="cbt-setup-security-log-roster-filter-grid">
                        <label class="cbt-setup-security-log-roster-filter-field">
                            <span>Cari</span>
                            <input
                                type="search"
                                value=""
                                placeholder="Cari siswa, login, atau attempt"
                                autocomplete="off"
                                data-security-log-roster-filter="search"
                            />
                        </label>

                        <label class="cbt-setup-security-log-roster-filter-field">
                            <span>Status Live</span>
                            <select data-security-log-roster-filter="presence">
                                <option value="all">Semua status</option>
                                <option value="online">Online</option>
                                <option value="stale">Stale</option>
                                <option value="offline">Offline</option>
                            </select>
                        </label>

                        <label class="cbt-setup-security-log-roster-filter-field">
                            <span>Risk</span>
                            <select data-security-log-roster-filter="risk">
                                <option value="all">Semua risk</option>
                                <option value="safe">Normal</option>
                                <option value="watch">Watch</option>
                                <option value="high-risk">High Risk</option>
                            </select>
                        </label>

                        <label class="cbt-setup-security-log-roster-filter-field">
                            <span>Exam</span>
                            <select data-security-log-roster-filter="exam">
                                <option value="all">Semua exam</option>
                                <?php foreach ($exam_options as $exam_option): ?>
                                    <option value="<?php echo esc_attr($exam_option); ?>"><?php echo esc_html($exam_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="cbt-setup-security-log-roster-filter-field">
                            <span>Kelas</span>
                            <select data-security-log-roster-filter="kelas">
                                <option value="all">Semua kelas</option>
                                <?php foreach ($kelas_options as $kelas_option): ?>
                                    <option value="<?php echo esc_attr($kelas_option); ?>"><?php echo esc_html($kelas_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="cbt-setup-security-log-roster-filter-field">
                            <span>Ruang</span>
                            <select data-security-log-roster-filter="ruang">
                                <option value="all">Semua ruang</option>
                                <?php foreach ($ruang_options as $ruang_option): ?>
                                    <option value="<?php echo esc_attr($ruang_option); ?>"><?php echo esc_html($ruang_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="cbt-setup-security-log-roster-toolbar-footer">
                        <div class="cbt-setup-security-log-roster-summary" data-security-log-roster-summary>
                            Menampilkan <?php echo esc_html((string) count($groups)); ?> grup roster live.
                        </div>
                        <div class="cbt-setup-security-log-roster-pagination" data-security-log-roster-pagination>
                            <button type="button" class="button button-secondary button-small" data-security-log-roster-page-prev>Prev</button>
                            <span class="cbt-setup-security-log-roster-page-label" data-security-log-roster-page-label>Halaman 1 / 1</span>
                            <button type="button" class="button button-secondary button-small" data-security-log-roster-page-next>Next</button>
                        </div>
                    </div>
                </div>

                <div class="cbt-setup-security-log-roster-empty" data-security-log-roster-filter-empty hidden>Tidak ada roster live yang cocok dengan filter saat ini.</div>

                <div class="cbt-setup-security-log-roster-groups" data-security-log-roster-groups>
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
                        <article
                            class="cbt-setup-security-log-roster-group"
                            data-security-log-roster-group
                            data-security-log-roster-exam="<?php echo esc_attr($exam_title); ?>"
                            data-security-log-roster-kelas="<?php echo esc_attr($kelas_label); ?>"
                            data-security-log-roster-ruang="<?php echo esc_attr($ruang_label); ?>"
                        >
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
                                    <button class="button button-small cbt-admin-btn--warning" type="submit">Reset Login</button>
                                </form>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Paksa attempt ini selesai sekarang? Attempt tidak bisa dilanjutkan lagi oleh siswa.');">
                                    <?php wp_nonce_field('cbt_force_complete_attempt_' . $attempt_id); ?>
                                    <input type="hidden" name="action" value="cbt_force_complete_attempt" />
                                    <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt_id; ?>" />
                                    <input type="hidden" name="return_page" value="cbt-security" />
                                    <input type="hidden" name="return_hash" value="security-log" />
                                    <button class="button button-primary button-small cbt-admin-btn--danger" type="submit">Force Complete</button>
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
        $risk_filter_value = $risk_tone !== '' ? $risk_tone : 'safe';
        ?>
        <div
            class="cbt-setup-security-log-roster-row"
            data-security-log-roster-row
            data-security-log-roster-presence="<?php echo esc_attr($presence_status); ?>"
            data-security-log-roster-risk="<?php echo esc_attr($risk_filter_value); ?>"
            data-security-log-roster-student-name="<?php echo esc_attr($student_name); ?>"
            data-security-log-roster-student-login="<?php echo esc_attr($student_login); ?>"
            data-security-log-roster-attempt="<?php echo esc_attr((string) $attempt_id); ?>"
        >
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

    private static function format_duration_seconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds <= 0) {
            return '0 detik';
        }

        if ($seconds < 60) {
            return $seconds . ' detik';
        }

        $minutes = (int) floor($seconds / 60);
        $remaining_seconds = $seconds % 60;
        if ($minutes < 60) {
            return $remaining_seconds > 0
                ? sprintf('%d m %d dtk', $minutes, $remaining_seconds)
                : sprintf('%d menit', $minutes);
        }

        $hours = (int) floor($minutes / 60);
        $remaining_minutes = $minutes % 60;

        return $remaining_minutes > 0
            ? sprintf('%d j %d m', $hours, $remaining_minutes)
            : sprintf('%d jam', $hours);
    }

    private static function format_activity_value(string $timestamp, string $status): string
    {
        $timestamp = trim($timestamp);
        $status = trim($status);
        if ($timestamp === '' && $status === '') {
            return '-';
        }

        if ($timestamp === '') {
            return strtoupper($status);
        }

        if ($status === '') {
            return $timestamp;
        }

        return $timestamp . ' • ' . strtoupper($status);
    }
}
