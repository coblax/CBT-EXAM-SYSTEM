<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_constant_override = !empty($resolved_source['is_constant_override']);
$is_dev_mode = !empty($resolved_source['is_dev']);
$is_stable_mode = (string) ($resolved_source['mode'] ?? '') === 'stable';
$active_dev_url = (string) ($resolved_source['dev_server_url'] ?? '');
$saved_dev_url = (string) ($settings['dev_server_url'] ?? '');
$checked_at = !empty($dev_server_health['checked_at']) ? (int) $dev_server_health['checked_at'] : 0;
$checked_at_label = $checked_at > 0 ? wp_date('d M Y H:i:s', $checked_at) : '-';
$health_status = (string) ($dev_server_health['status'] ?? 'unknown');
$health_message = (string) ($dev_server_health['message'] ?? '');
$health_tone = $health_status === 'ok' ? 'success' : ($health_status === 'failed' ? 'error' : 'warning');
$manifest_exists = !empty($build_manifest['exists']);

$storage_prefix = (string) ($storage_debug_config['prefix'] ?? 'cbt_exam_frontend_');
$auth_session_key = (string) ($storage_debug_config['auth_session_key'] ?? 'cbt_exam_frontend_auth_v1');
$attempt_ui_prefix = (string) ($storage_debug_config['attempt_ui_prefix'] ?? 'cbt_exam_frontend_attempt_ui_v1_');
$doubtful_prefix = (string) ($storage_debug_config['doubtful_prefix'] ?? 'cbt_exam_frontend_doubtful_v1_');
$question_cache_session_prefix = (string) ($storage_debug_config['question_cache_session_prefix'] ?? 'cbt_exam_frontend_question_cache_v2_');
$question_cache_meta_prefix = (string) ($storage_debug_config['question_cache_meta_prefix'] ?? 'cbt_exam_frontend_question_cache_meta_v2_');
$question_cache_item_prefix = (string) ($storage_debug_config['question_cache_item_prefix'] ?? 'cbt_exam_frontend_question_cache_item_v2_');
$indexed_db_name = (string) ($storage_debug_config['indexed_db_name'] ?? 'cbt_exam_frontend_cache_v2');
$diagnostics_requests_key = (string) ($storage_debug_config['diagnostics_requests_key'] ?? 'cbt_exam_frontend_debug_rest_v1');
$diagnostics_snapshot_key = (string) ($storage_debug_config['diagnostics_snapshot_key'] ?? 'cbt_exam_frontend_debug_snapshot_v1');
$diagnostics_sync_key = (string) ($storage_debug_config['diagnostics_sync_key'] ?? 'cbt_exam_frontend_debug_sync_v1');
$diagnostics_timeline_key = (string) ($storage_debug_config['diagnostics_timeline_key'] ?? 'cbt_exam_frontend_debug_timeline_v1');
$diagnostics_scenario_key = (string) ($storage_debug_config['diagnostics_scenario_key'] ?? 'cbt_exam_frontend_debug_scenarios_v1');
$diagnostics_errors_key = (string) ($storage_debug_config['diagnostics_errors_key'] ?? 'cbt_exam_frontend_debug_errors_v1');
$diagnostics_state_key = (string) ($storage_debug_config['diagnostics_state_key'] ?? 'cbt_exam_frontend_debug_state_v1');
$diagnostics_render_stats_key = (string) ($storage_debug_config['diagnostics_render_stats_key'] ?? 'cbt_exam_frontend_debug_render_stats_v1');
$diagnostics_action_trail_key = (string) ($storage_debug_config['diagnostics_action_trail_key'] ?? 'cbt_exam_frontend_debug_action_trail_v1');
$diagnostics_command_key = (string) ($storage_debug_config['diagnostics_command_key'] ?? 'cbt_exam_frontend_debug_command_v1');
$diagnostics_max_entries = (int) ($storage_debug_config['diagnostics_max_entries'] ?? 50);
$diagnostics_timeline_max_entries = (int) ($storage_debug_config['diagnostics_timeline_max_entries'] ?? 150);

$launcher_available = !empty($dev_server_launcher['available']);
$launcher_can_autostart = !empty($dev_server_launcher['can_autostart']);
$launcher_running = !empty($dev_server_launcher['running']);
$launcher_reason = (string) ($dev_server_launcher['reason'] ?? '');
$launcher_wrapper_path = (string) ($dev_server_launcher['wrapper_path'] ?? '');
$launcher_log_file = (string) ($dev_server_launcher['log_file'] ?? '');
$launcher_pid_file = (string) ($dev_server_launcher['pid_file'] ?? '');

$build_watch_available = !empty($build_watch_launcher['available']);
$build_watch_can_autostart = !empty($build_watch_launcher['can_autostart']);
$build_watch_running = !empty($build_watch_launcher['running']);
$build_watch_reason = (string) ($build_watch_launcher['reason'] ?? '');
$build_watch_wrapper_path = (string) ($build_watch_launcher['wrapper_path'] ?? '');
$build_watch_log_file = (string) ($build_watch_launcher['log_file'] ?? '');
$build_watch_pid_file = (string) ($build_watch_launcher['pid_file'] ?? '');

$frontend_debug_enabled = !empty($frontend_debug['enabled']);
$frontend_debug_status_label = (string) ($frontend_debug['status_label'] ?? ($frontend_debug_enabled ? 'ACTIVE' : 'INACTIVE'));
$frontend_debug_reason = (string) ($frontend_debug['reason'] ?? '');
$frontend_debug_audience = (string) ($frontend_debug['audience'] ?? 'manage_options only');

$frontend_diagnostics_enabled = !empty($frontend_diagnostics['enabled']);
$frontend_diagnostics_status_label = (string) ($frontend_diagnostics['status_label'] ?? ($frontend_diagnostics_enabled ? 'ACTIVE' : 'INACTIVE'));
$frontend_diagnostics_reason = (string) ($frontend_diagnostics['reason'] ?? '');
$frontend_diagnostics_audience = (string) ($frontend_diagnostics['audience'] ?? 'Browser admin saat ini');
$hero_live_value = $is_constant_override
    ? 'Override'
    : ($is_dev_mode ? 'Dev Mode' : ($is_stable_mode ? 'Stable Test' : 'Production'));
$live_health_label = $is_dev_mode ? strtoupper($health_status) : 'STATIC BUILD';
$live_launcher_label = 'OFF';
if ($is_stable_mode) {
    $live_launcher_label = $build_watch_available ? ($build_watch_running ? 'RUNNING' : ($build_watch_can_autostart ? 'READY' : 'BLOCKED')) : 'UNAVAILABLE';
} elseif ($is_dev_mode) {
    $live_launcher_label = $launcher_available ? ($launcher_running ? 'RUNNING' : ($launcher_can_autostart ? 'READY' : 'SKIPPED')) : 'UNAVAILABLE';
}
?>
<div class="wrap cbt-developer-page">
    <?php if ($notice !== ''): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <?php if ($is_constant_override): ?>
        <div class="notice notice-warning">
            <p><strong>Constant Override aktif.</strong> Frontend sekarang dipaksa memakai dev server dari <code>CBT_EXAM_FRONTEND_DEV_SERVER</code>. Pengaturan admin di bawah hanya informasional sampai constant dilepas.</p>
        </div>
    <?php endif; ?>

    <style>
        .cbt-developer-page .cbt-dev-shell { display:grid; gap:20px; }
        .cbt-developer-page .cbt-dev-hero { display:flex; justify-content:space-between; align-items:stretch; gap:24px; padding:24px; border:1px solid #dcdcde; border-radius:22px; background:linear-gradient(135deg,#ffffff 0%,#f8fbff 48%,#eef5ff 100%); box-shadow:0 18px 42px rgba(15,23,42,.06); }
        .cbt-developer-page .cbt-dev-hero-copy { display:grid; align-content:start; gap:10px; max-width:760px; }
        .cbt-developer-page .cbt-dev-kicker { display:inline-flex; align-items:center; width:max-content; padding:8px 12px; border-radius:999px; background:#fff1e8; color:#c2410c; font-size:12px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        .cbt-developer-page .cbt-dev-hero-copy h1 { margin:0; font-size:46px; line-height:1.05; color:#0f172a; }
        .cbt-developer-page .cbt-dev-hero-copy p { margin:0; max-width:720px; color:#334155; font-size:18px; line-height:1.7; }
        .cbt-developer-page .cbt-dev-live-panel { min-width:320px; max-width:340px; padding:18px; border:1px solid #dbe4f0; border-radius:22px; background:rgba(255,255,255,.94); box-shadow:0 16px 36px rgba(15,23,42,.08); }
        .cbt-developer-page .cbt-dev-live-label { display:block; margin-bottom:12px; color:#64748b; font-size:12px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        .cbt-developer-page .cbt-dev-live-value { display:flex; align-items:center; justify-content:center; min-height:64px; margin-bottom:14px; border-radius:18px; background:#0f172a; color:#fff; font-size:20px; font-weight:800; text-align:center; }
        .cbt-developer-page .cbt-dev-live-meta { display:grid; gap:10px; }
        .cbt-developer-page .cbt-dev-live-meta-item { display:flex; justify-content:space-between; gap:16px; color:#1e293b; }
        .cbt-developer-page .cbt-dev-live-meta-item span { color:#475569; }
        .cbt-developer-page .cbt-dev-grid { display:grid; grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr); gap:20px; margin-top:20px; }
        .cbt-developer-page .cbt-dev-tabs { display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; }
        .cbt-developer-page .cbt-dev-tab-button { border:1px solid #cbd5e1; background:#fff; color:#1e293b; border-radius:999px; padding:10px 16px; font-weight:700; cursor:pointer; }
        .cbt-developer-page .cbt-dev-tab-button.is-active { background:#1d4ed8; border-color:#1d4ed8; color:#fff; box-shadow:0 4px 14px rgba(29,78,216,.18); }
        .cbt-developer-page .cbt-dev-tab-panel { display:none; margin-top:20px; }
        .cbt-developer-page .cbt-dev-tab-panel.is-active { display:block; }
        .cbt-developer-page .cbt-dev-card { background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:20px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .cbt-developer-page .cbt-dev-card-full { grid-column:1 / -1; }
        .cbt-developer-page .cbt-dev-card h2 { margin:0 0 6px; font-size:18px; }
        .cbt-developer-page .cbt-dev-card p.cbt-dev-card-subtitle { margin:0 0 18px; color:#646970; }
        .cbt-developer-page .cbt-dev-stack { display:grid; gap:16px; }
        .cbt-developer-page .cbt-dev-field { display:grid; gap:8px; }
        .cbt-developer-page .cbt-dev-field label { font-weight:600; }
        .cbt-developer-page .cbt-dev-mode-row { display:flex; flex-wrap:wrap; gap:16px; }
        .cbt-developer-page .cbt-dev-mode-option { display:flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #dcdcde; border-radius:10px; background:#f9f9fb; }
        .cbt-developer-page .cbt-dev-url-input { width:100%; max-width:520px; }
        .cbt-developer-page .cbt-dev-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
        .cbt-developer-page .cbt-dev-status-list { display:grid; gap:12px; }
        .cbt-developer-page .cbt-dev-status-item { padding:12px 14px; border-radius:10px; background:#f6f7f7; }
        .cbt-developer-page .cbt-dev-status-item strong { display:block; margin-bottom:4px; }
        .cbt-developer-page .cbt-dev-inline-form { display:flex; flex-wrap:wrap; gap:10px; align-items:end; }
        .cbt-developer-page .cbt-dev-inline-form input[type="number"] { width:160px; }
        .cbt-developer-page .cbt-dev-inline-form .cbt-dev-field { margin:0; }
        .cbt-developer-page .cbt-dev-chip-row { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
        .cbt-developer-page .cbt-dev-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:600; background:#edf2ff; color:#1d4ed8; }
        .cbt-developer-page .cbt-dev-chip.is-build { background:#eef2ff; color:#4338ca; }
        .cbt-developer-page .cbt-dev-chip.is-dev { background:#ecfdf5; color:#047857; }
        .cbt-developer-page .cbt-dev-chip.is-stable { background:#eff6ff; color:#1d4ed8; }
        .cbt-developer-page .cbt-dev-chip.is-override { background:#fff7ed; color:#c2410c; }
        .cbt-developer-page .cbt-dev-chip.is-success { background:#ecfdf5; color:#047857; }
        .cbt-developer-page .cbt-dev-chip.is-error { background:#fef2f2; color:#b91c1c; }
        .cbt-developer-page .cbt-dev-chip.is-warning { background:#fffbeb; color:#a16207; }
        .cbt-developer-page .cbt-dev-chip.is-neutral { background:#f1f5f9; color:#334155; }
        .cbt-developer-page .cbt-dev-kv { display:grid; gap:6px; }
        .cbt-developer-page .cbt-dev-kv code { word-break:break-all; }
        .cbt-developer-page .cbt-dev-list { margin:8px 0 0 18px; }
        .cbt-developer-page .cbt-dev-muted { color:#646970; }
        .cbt-developer-page .cbt-dev-tool-status { min-height:20px; font-weight:600; }
        .cbt-developer-page .cbt-dev-diagnostic-grid { display:grid; gap:12px; }
        .cbt-developer-page .cbt-dev-diagnostic-grid code { word-break:break-all; }
        .cbt-developer-page .cbt-dev-preview-list { display:grid; gap:10px; margin-top:10px; }
        .cbt-developer-page .cbt-dev-preview-item { padding:12px; border:1px solid #dbe4f0; border-radius:12px; background:#ffffff; }
        .cbt-developer-page .cbt-dev-preview-item-header { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:8px; }
        .cbt-developer-page .cbt-dev-preview-text { color:#334155; line-height:1.6; }
        .cbt-developer-page .cbt-dev-diag-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:16px; }
        .cbt-developer-page .cbt-dev-diag-span-2 { grid-column:1 / -1; }
        .cbt-developer-page .cbt-dev-diag-note { padding:14px 16px; border-radius:10px; background:#f8fafc; border:1px dashed #cbd5e1; color:#475569; }
        .cbt-developer-page .cbt-dev-diag-meta { display:grid; gap:8px; }
        .cbt-developer-page .cbt-dev-diag-meta-row { display:flex; justify-content:space-between; gap:16px; padding:8px 0; border-bottom:1px solid #e5e7eb; }
        .cbt-developer-page .cbt-dev-diag-meta-row:last-child { border-bottom:0; }
        .cbt-developer-page .cbt-dev-diag-errors { display:grid; gap:10px; }
        .cbt-developer-page .cbt-dev-diag-error { padding:10px 12px; border:1px solid #fecaca; border-radius:10px; background:#fff5f5; }
        .cbt-developer-page .cbt-dev-diag-error strong { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:6px; color:#991b1b; }
        .cbt-developer-page .cbt-dev-diag-error pre,
        .cbt-developer-page .cbt-dev-diag-detail { margin:0; padding:12px; border-radius:10px; background:#0f172a; color:#e2e8f0; overflow:auto; font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace; white-space:pre-wrap; }
        .cbt-developer-page .cbt-dev-diag-table tbody tr.is-expanded { background:#f8fbff; }
        .cbt-developer-page .cbt-dev-diag-inline-detail-row td { padding:0; background:#f8fbff; }
        .cbt-developer-page .cbt-dev-diag-inline-detail-wrap { padding:14px; border-top:1px dashed #dbe5f2; }
        .cbt-developer-page .cbt-dev-diag-inline-detail-title { display:block; margin-bottom:8px; color:#475569; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
        .cbt-developer-page .cbt-dev-diag-inline-detail { margin:0; padding:12px; border-radius:10px; background:#0f172a; color:#e2e8f0; overflow:auto; font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace; white-space:pre-wrap; }
        .cbt-developer-page .cbt-dev-diag-table-wrap { overflow:auto; border:1px solid #dcdcde; border-radius:10px; background:#fff; }
        .cbt-developer-page .cbt-dev-diag-table { width:100%; border-collapse:collapse; min-width:760px; }
        .cbt-developer-page .cbt-dev-diag-table th,
        .cbt-developer-page .cbt-dev-diag-table td { padding:10px 12px; border-bottom:1px solid #edf2f7; text-align:left; vertical-align:top; }
        .cbt-developer-page .cbt-dev-diag-table th { background:#f8fafc; font-weight:700; }
        .cbt-developer-page .cbt-dev-diag-table tbody tr:hover { background:#f8fbff; }
        .cbt-developer-page .cbt-dev-diag-badge { display:inline-flex; align-items:center; border-radius:999px; padding:4px 8px; font-size:11px; font-weight:700; line-height:1; }
        .cbt-developer-page .cbt-dev-diag-badge.is-ok { background:#dcfce7; color:#166534; }
        .cbt-developer-page .cbt-dev-diag-badge.is-error { background:#fee2e2; color:#991b1b; }
        .cbt-developer-page .cbt-dev-diag-badge.is-muted { background:#e2e8f0; color:#334155; }
        .cbt-developer-page .cbt-dev-diag-form { display:grid; gap:12px; }
        .cbt-developer-page .cbt-dev-diag-form-row { display:grid; gap:8px; }
        .cbt-developer-page .cbt-dev-diag-form-row label { font-weight:600; }
        .cbt-developer-page .cbt-dev-diag-form-row select { max-width:260px; }
        .cbt-developer-page .cbt-dev-filter-row { display:flex; flex-wrap:wrap; gap:8px; margin:8px 0 12px; }
        .cbt-developer-page .cbt-dev-filter-row .button.is-active { background:#1d4ed8; border-color:#1d4ed8; color:#fff; }
        .cbt-developer-page .cbt-dev-rest-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:end; margin:4px 0 12px; }
        .cbt-developer-page .cbt-dev-rest-toolbar .cbt-dev-field { min-width:140px; }
        .cbt-developer-page .cbt-dev-rest-toolbar .cbt-dev-field.is-search { flex:1 1 260px; }
        .cbt-developer-page .cbt-dev-rest-toolbar input[type="search"],
        .cbt-developer-page .cbt-dev-rest-toolbar select { width:100%; }
        .cbt-developer-page .cbt-dev-rest-pagination { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; margin-top:12px; }
        .cbt-developer-page .cbt-dev-rest-pagination-meta { color:#64748b; font-size:12px; }
        .cbt-developer-page .cbt-dev-empty { color:#64748b; font-style:italic; }
        .cbt-developer-page .cbt-dev-scenario-lab { display:grid; gap:18px; }
        .cbt-developer-page .cbt-dev-scenario-summary { display:flex; flex-wrap:wrap; gap:8px; padding:14px 16px; border:1px solid #dbe4f0; border-radius:14px; background:#f8fbff; }
        .cbt-developer-page .cbt-dev-scenario-summary.is-empty { color:#64748b; }
        .cbt-developer-page .cbt-dev-scenario-chip { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; background:#dbeafe; color:#1d4ed8; font-size:12px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; }
        .cbt-developer-page .cbt-dev-scenario-chip.is-danger { background:#fee2e2; color:#b91c1c; }
        .cbt-developer-page .cbt-dev-scenario-chip.is-warning { background:#fef3c7; color:#a16207; }
        .cbt-developer-page .cbt-dev-scenario-presets { display:flex; flex-wrap:wrap; gap:8px; }
        .cbt-developer-page .cbt-dev-scenario-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
        .cbt-developer-page .cbt-dev-scenario-card { padding:18px; border:1px solid #dbe4f0; border-radius:16px; background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%); box-shadow:0 10px 24px rgba(15,23,42,.04); }
        .cbt-developer-page .cbt-dev-scenario-card h3 { margin:0 0 4px; font-size:16px; color:#0f172a; }
        .cbt-developer-page .cbt-dev-scenario-card p { margin:0 0 14px; color:#64748b; }
        .cbt-developer-page .cbt-dev-scenario-field-row { display:grid; gap:12px; }
        .cbt-developer-page .cbt-dev-scenario-field-row .cbt-dev-diag-form-row { margin:0; }
        .cbt-developer-page .cbt-dev-scenario-toggle { display:flex; align-items:center; gap:10px; min-height:40px; font-weight:600; }
        .cbt-developer-page .cbt-dev-scenario-toggle input[type="checkbox"] { margin:0; }
        .cbt-developer-page .cbt-dev-scenario-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
        .cbt-developer-page .cbt-dev-scenario-footnote { color:#475569; font-size:12px; }
        @media (max-width: 1100px) {
            .cbt-developer-page .cbt-dev-hero { flex-direction:column; }
            .cbt-developer-page .cbt-dev-live-panel { min-width:0; max-width:none; }
            .cbt-developer-page .cbt-dev-grid { grid-template-columns: 1fr; }
            .cbt-developer-page .cbt-dev-diag-grid { grid-template-columns: 1fr; }
            .cbt-developer-page .cbt-dev-diag-span-2 { grid-column:auto; }
            .cbt-developer-page .cbt-dev-scenario-grid { grid-template-columns:1fr; }
        }
        @media (max-width: 782px) {
            .cbt-developer-page .cbt-dev-hero,
            .cbt-developer-page .cbt-dev-card { padding:20px; }
            .cbt-developer-page .cbt-dev-hero-copy h1 { font-size:34px; }
            .cbt-developer-page .cbt-dev-hero-copy p { font-size:16px; }
        }
    </style>

    <div class="cbt-dev-shell">
    <section class="cbt-dev-hero">
        <div class="cbt-dev-hero-copy">
            <span class="cbt-dev-kicker">Developer</span>
            <h1>CBT Developer</h1>
            <p>Kelola source asset frontend CBT, diagnostics browser-local, dan toolbox triage bug untuk tiga mode kerja: production build, stable test dengan build watcher, dan Vite dev server penuh. Semua kontrol di halaman ini bersifat administratif dan diarahkan untuk mempercepat debugging frontend.</p>
        </div>
        <aside class="cbt-dev-live-panel">
            <span class="cbt-dev-live-label">Live Status</span>
            <span class="cbt-dev-live-value"><?php echo esc_html($hero_live_value); ?></span>
            <div class="cbt-dev-live-meta">
                <div class="cbt-dev-live-meta-item">
                    <span>Source Aktif</span>
                    <strong><?php echo esc_html((string) ($resolved_source['label'] ?? 'Unknown')); ?></strong>
                </div>
                <div class="cbt-dev-live-meta-item">
                    <span>Dev Health</span>
                    <strong><?php echo esc_html($live_health_label); ?></strong>
                </div>
                <div class="cbt-dev-live-meta-item">
                    <span>Frontend Debug</span>
                    <strong><?php echo esc_html($frontend_debug_status_label); ?></strong>
                </div>
                <div class="cbt-dev-live-meta-item">
                    <span>Diagnostics</span>
                    <strong><?php echo esc_html($frontend_diagnostics_status_label); ?></strong>
                </div>
                <div class="cbt-dev-live-meta-item">
                    <span>Launcher</span>
                    <strong><?php echo esc_html($live_launcher_label); ?></strong>
                </div>
            </div>
        </aside>
    </section>

    <div class="cbt-dev-tabs" role="tablist" aria-label="CBT Developer Sections">
        <button type="button" class="cbt-dev-tab-button is-active" data-dev-tab="overview" role="tab" aria-selected="true">Overview</button>
        <button type="button" class="cbt-dev-tab-button" data-dev-tab="state" role="tab" aria-selected="false">State</button>
        <button type="button" class="cbt-dev-tab-button" data-dev-tab="inspector" role="tab" aria-selected="false">Inspector</button>
        <button type="button" class="cbt-dev-tab-button" data-dev-tab="storage" role="tab" aria-selected="false">Storage</button>
        <button type="button" class="cbt-dev-tab-button" data-dev-tab="scenarios" role="tab" aria-selected="false">Scenarios</button>
        <button type="button" class="cbt-dev-tab-button" data-dev-tab="timeline" role="tab" aria-selected="false">Timeline</button>
    </div>

    <div class="cbt-dev-tab-panel is-active" data-dev-tab-panel="overview">
    <div class="cbt-dev-grid">
        <section class="cbt-dev-card">
            <h2>Frontend Mode</h2>
            <p class="cbt-dev-card-subtitle">Pilih asset source yang dipakai shortcode frontend CBT.</p>

            <div class="cbt-dev-chip-row">
                <span class="cbt-dev-chip <?php echo $is_dev_mode ? 'is-dev' : ($is_stable_mode ? 'is-stable' : 'is-build'); ?>">
                    <?php echo esc_html((string) ($resolved_source['label'] ?? 'Unknown')); ?>
                </span>
                <?php if ($is_constant_override): ?>
                    <span class="cbt-dev-chip is-override">Constant Override</span>
                <?php endif; ?>
                <span class="cbt-dev-chip is-<?php echo esc_attr($health_tone); ?>">
                    Dev Health: <?php echo esc_html(strtoupper($health_status)); ?>
                </span>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-dev-stack">
                <?php wp_nonce_field('cbt_save_developer_settings'); ?>
                <input type="hidden" name="action" value="cbt_save_developer_settings" />

                <div class="cbt-dev-field">
                    <label>Mode Frontend</label>
                    <div class="cbt-dev-mode-row">
                        <label class="cbt-dev-mode-option">
                            <input type="radio" name="mode" value="build" <?php checked((string) ($settings['mode'] ?? 'build'), 'build'); ?> <?php disabled($is_constant_override); ?> />
                            <span>Production Build</span>
                        </label>
                        <label class="cbt-dev-mode-option">
                            <input type="radio" name="mode" value="dev" <?php checked((string) ($settings['mode'] ?? 'build'), 'dev'); ?> <?php disabled($is_constant_override); ?> />
                            <span>Vite Dev Server</span>
                        </label>
                        <label class="cbt-dev-mode-option">
                            <input type="radio" name="mode" value="stable" <?php checked((string) ($settings['mode'] ?? 'build'), 'stable'); ?> <?php disabled($is_constant_override); ?> />
                            <span>Stable Test Mode</span>
                        </label>
                    </div>
                    <p class="description">Production Build memakai asset hasil build terakhir. Stable Test Mode memakai asset build statis + <code>npm run build:watch</code> agar tes reconnect/offline stabil tanpa reload dari Vite client. Vite Dev Server tetap untuk HMR dan debugging cepat.</p>
                </div>

                <div class="cbt-dev-field">
                    <label for="cbt-dev-server-url">Dev Server URL</label>
                    <input
                        type="url"
                        id="cbt-dev-server-url"
                        name="dev_server_url"
                        class="regular-text cbt-dev-url-input"
                        value="<?php echo esc_attr($saved_dev_url); ?>"
                        placeholder="http://127.0.0.1:5173"
                        <?php disabled($is_constant_override); ?>
                    />
                    <p class="description">Contoh: <code>http://127.0.0.1:5173</code> atau IP lokal yang bisa diakses device lain.</p>
                </div>

                <div class="cbt-dev-actions">
                    <button type="submit" class="button button-primary" <?php disabled($is_constant_override); ?>>Simpan Pengaturan</button>
                    <?php if ($frontend_page_url !== ''): ?>
                        <a class="button" href="<?php echo esc_url($frontend_page_url); ?>" target="_blank" rel="noopener noreferrer">Open Frontend Page</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="cbt-dev-card">
            <h2>Runtime Status</h2>
            <p class="cbt-dev-card-subtitle">Ringkasan source asset aktif dan health check dev server.</p>

            <div class="cbt-dev-status-list">
                <div class="cbt-dev-status-item">
                    <strong>Source Aktif</strong>
                    <div class="cbt-dev-kv">
                        <span><?php echo esc_html((string) ($resolved_source['label'] ?? 'Unknown')); ?></span>
                        <?php if ($active_dev_url !== ''): ?>
                            <code><?php echo esc_html($active_dev_url); ?></code>
                        <?php elseif ($is_stable_mode): ?>
                            <span class="cbt-dev-muted">Frontend memakai asset build statis dan build watcher bekerja di background.</span>
                        <?php else: ?>
                            <span class="cbt-dev-muted">Frontend memakai asset build statis dari <code>public/build</code>.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cbt-dev-status-item">
                    <strong>Dev Server Health</strong>
                    <div class="cbt-dev-kv">
                        <span>Status: <strong><?php echo esc_html(strtoupper($health_status)); ?></strong></span>
                        <span><?php echo esc_html($is_dev_mode ? ($health_message !== '' ? $health_message : 'Belum ada health check.') : 'Mode ini tidak memakai Vite dev server sebagai source aktif.'); ?></span>
                        <span class="cbt-dev-muted">Last checked: <?php echo esc_html($checked_at_label); ?></span>
                    </div>
                    <div class="cbt-dev-actions" style="margin-top:12px;">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('cbt_check_developer_dev_server'); ?>
                            <input type="hidden" name="action" value="cbt_check_developer_dev_server" />
                            <button type="submit" class="button">Check Dev Server</button>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('cbt_stop_developer_dev_server'); ?>
                            <input type="hidden" name="action" value="cbt_stop_developer_dev_server" />
                            <button type="submit" class="button" <?php disabled(!$launcher_available); ?>>Matikan Dev Server</button>
                        </form>
                    </div>
                    <p class="description" style="margin-top:10px;">Saat source aktif = <strong>Vite Dev Server</strong> dan host URL lokal, tombol ini akan mencoba menyalakan <code>npm run dev</code> di background bila server belum hidup. Tombol <strong>Matikan Dev Server</strong> akan menghentikan proses Vite dan, bila mode aktif saat ini adalah Dev Mode, frontend dikembalikan ke <strong>Production Build</strong>. Untuk <strong>Stable Test Mode</strong>, frontend tetap memakai build statis dan watcher dibantu oleh launcher terpisah di bawah.</p>
                </div>

                <div class="cbt-dev-status-item">
                    <strong>Frontend Debug</strong>
                    <div class="cbt-dev-kv">
                        <span>Status: <strong><?php echo esc_html($frontend_debug_status_label); ?></strong></span>
                        <span>Reason: <?php echo esc_html($frontend_debug_reason !== '' ? $frontend_debug_reason : 'Production Build'); ?></span>
                        <span class="cbt-dev-muted">Audience: <?php echo esc_html($frontend_debug_audience); ?></span>
                    </div>
                </div>

                <div class="cbt-dev-status-item">
                    <strong>Frontend Diagnostics</strong>
                    <div class="cbt-dev-kv">
                        <span>Status: <strong><?php echo esc_html($frontend_diagnostics_status_label); ?></strong></span>
                        <span>Reason: <?php echo esc_html($frontend_diagnostics_reason !== '' ? $frontend_diagnostics_reason : 'Production Build'); ?></span>
                        <span class="cbt-dev-muted">Audience: <?php echo esc_html($frontend_diagnostics_audience); ?></span>
                    </div>
                </div>

                <div class="cbt-dev-status-item">
                    <strong>Vite Dev Launcher</strong>
                    <div class="cbt-dev-kv">
                        <span>Status: <strong><?php echo esc_html($launcher_available ? ($launcher_running ? 'RUNNING' : ($launcher_can_autostart ? 'READY' : 'SKIPPED')) : 'UNAVAILABLE'); ?></strong></span>
                        <span><?php echo esc_html($launcher_reason !== '' ? $launcher_reason : 'Wrapper script siap dipakai.'); ?></span>
                        <?php if ($launcher_wrapper_path !== ''): ?>
                            <code>Wrapper: <?php echo esc_html($launcher_wrapper_path); ?></code>
                        <?php endif; ?>
                        <?php if ($launcher_log_file !== ''): ?>
                            <code>Log: <?php echo esc_html($launcher_log_file); ?></code>
                        <?php endif; ?>
                        <?php if ($launcher_pid_file !== ''): ?>
                            <code>PID: <?php echo esc_html($launcher_pid_file); ?></code>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cbt-dev-status-item">
                    <strong>Stable Test Launcher</strong>
                    <div class="cbt-dev-kv">
                        <span>Status: <strong><?php echo esc_html($build_watch_available ? ($build_watch_running ? 'RUNNING' : ($build_watch_can_autostart ? 'READY' : 'BLOCKED')) : 'UNAVAILABLE'); ?></strong></span>
                        <span><?php echo esc_html($build_watch_reason !== '' ? $build_watch_reason : 'Wrapper script build watch siap dipakai.'); ?></span>
                        <?php if ($build_watch_wrapper_path !== ''): ?>
                            <code>Wrapper: <?php echo esc_html($build_watch_wrapper_path); ?></code>
                        <?php endif; ?>
                        <?php if ($build_watch_log_file !== ''): ?>
                            <code>Log: <?php echo esc_html($build_watch_log_file); ?></code>
                        <?php endif; ?>
                        <?php if ($build_watch_pid_file !== ''): ?>
                            <code>PID: <?php echo esc_html($build_watch_pid_file); ?></code>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cbt-dev-status-item">
                    <strong>Build Manifest</strong>
                    <div class="cbt-dev-kv">
                        <span><?php echo esc_html((string) ($build_manifest['message'] ?? '')); ?></span>
                        <?php if ($manifest_exists): ?>
                            <code>JS: <?php echo esc_html((string) ($build_manifest['entry_file'] ?? '')); ?></code>
                            <?php if (!empty($build_manifest['css_files']) && is_array($build_manifest['css_files'])): ?>
                                <ul class="cbt-dev-list">
                                    <?php foreach ($build_manifest['css_files'] as $css_file): ?>
                                        <li><code><?php echo esc_html((string) $css_file); ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="cbt-dev-card">
            <h2>Developer Tools</h2>
            <p class="cbt-dev-card-subtitle">Tool cepat untuk reset total state frontend pada browser dan frontend dev tab yang sedang aktif.</p>

            <div class="cbt-dev-actions">
                <button type="button" class="button" id="cbt-clear-frontend-browser-state">Clear Frontend Browser State</button>
                <span class="cbt-dev-tool-status" id="cbt-clear-frontend-browser-state-status" aria-live="polite"></span>
            </div>

            <p class="description" style="margin-top:12px;">Membersihkan <code>localStorage</code>, <code>IndexedDB</code>, diagnostics log, lalu mengirim command ke frontend dev tab aktif agar <code>sessionStorage</code> yang relevan ikut dibersihkan.</p>
        </section>
    </div>
    </div>

    <div class="cbt-dev-tab-panel" data-dev-tab-panel="state">
        <section class="cbt-dev-card cbt-dev-card-full">
            <h2>State Inspector</h2>
            <p class="cbt-dev-card-subtitle">Snapshot live state frontend CBT yang dikelompokkan per area agar bug state lebih cepat dibaca.</p>

            <?php if (!$frontend_diagnostics_enabled): ?>
                <div class="cbt-dev-diag-note">
                    State Inspector hanya aktif saat source frontend = <strong>Vite Dev Server</strong> atau <strong>Stable Test Mode</strong> dan user saat ini punya capability <code>manage_options</code>.
                </div>
            <?php else: ?>
                <div class="cbt-dev-actions" style="margin-bottom:12px;">
                    <button type="button" class="button" id="cbt-state-refresh">Refresh</button>
                    <button type="button" class="button" id="cbt-state-copy-json">Copy Snapshot JSON</button>
                    <button type="button" class="button" id="cbt-state-export-json">Export State Snapshot</button>
                    <button type="button" class="button" id="cbt-clear-render-stats">Clear Render Stats</button>
                    <span class="cbt-dev-tool-status" id="cbt-state-status" aria-live="polite"></span>
                </div>

                <div class="cbt-dev-diag-grid">
                    <section class="cbt-dev-status-item">
                        <strong>App</strong>
                        <div id="cbt-state-app" class="cbt-dev-diag-meta">
                            <p class="cbt-dev-empty">Belum ada snapshot runtime.</p>
                        </div>
                    </section>

                    <section class="cbt-dev-status-item">
                        <strong>Exam</strong>
                        <div id="cbt-state-exam" class="cbt-dev-diag-meta">
                            <p class="cbt-dev-empty">Belum ada snapshot exam.</p>
                        </div>
                    </section>

                    <section class="cbt-dev-status-item">
                        <strong>Sync</strong>
                        <div id="cbt-state-sync" class="cbt-dev-diag-meta">
                            <p class="cbt-dev-empty">Belum ada snapshot sync.</p>
                        </div>
                    </section>

                    <section class="cbt-dev-status-item">
                        <strong>Result</strong>
                        <div id="cbt-state-result" class="cbt-dev-diag-meta">
                            <p class="cbt-dev-empty">Belum ada snapshot result.</p>
                        </div>
                    </section>

                    <section class="cbt-dev-status-item cbt-dev-diag-span-2">
                        <strong>Scenario</strong>
                        <div id="cbt-state-scenario" class="cbt-dev-diag-meta">
                            <p class="cbt-dev-empty">Belum ada snapshot scenario.</p>
                        </div>
                    </section>

                    <section class="cbt-dev-status-item cbt-dev-diag-span-2">
                        <strong>Render Stats</strong>
                        <div id="cbt-state-render-stats" class="cbt-dev-diag-meta">
                            <p class="cbt-dev-empty">Belum ada render stats.</p>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="cbt-dev-tab-panel" data-dev-tab-panel="inspector">
        <section class="cbt-dev-card cbt-dev-card-full">
            <h2>Frontend Diagnostics</h2>
            <p class="cbt-dev-card-subtitle">Runtime snapshot, sync queue, error ringkas, REST inspector, dan bug bundle dari browser admin yang sama.</p>

            <div class="cbt-dev-chip-row">
                <span class="cbt-dev-chip <?php echo $frontend_diagnostics_enabled ? 'is-dev' : 'is-build'; ?>">
                    <?php echo esc_html($frontend_diagnostics_status_label); ?>
                </span>
                <span class="cbt-dev-chip is-<?php echo $frontend_diagnostics_enabled ? 'success' : 'warning'; ?>">
                    <?php echo esc_html($frontend_diagnostics_reason !== '' ? $frontend_diagnostics_reason : 'Production Build'); ?>
                </span>
                <span class="cbt-dev-chip">Max Entries: <?php echo esc_html((string) $diagnostics_max_entries); ?></span>
            </div>

            <?php if (!$frontend_diagnostics_enabled): ?>
                <div class="cbt-dev-diag-note">
                    Diagnostics hanya aktif saat source frontend = <strong>Vite Dev Server</strong> atau <strong>Stable Test Mode</strong> dan user saat ini punya capability <code>manage_options</code>. Dalam mode production, REST Inspector dan toolbox ini dimatikan total.
                </div>
            <?php else: ?>
                <div class="cbt-dev-diag-grid">
                    <section class="cbt-dev-status-item">
                        <strong>Runtime Snapshot</strong>
                        <div id="cbt-diagnostics-snapshot" class="cbt-dev-diag-meta">
                            <p class="cbt-dev-empty">Belum ada snapshot runtime.</p>
                        </div>
                    </section>

                    <section class="cbt-dev-status-item">
                        <strong>Recent Errors</strong>
                        <div id="cbt-diagnostics-errors" class="cbt-dev-diag-errors">
                            <p class="cbt-dev-empty">Belum ada fatal/runtime error.</p>
                        </div>
                    </section>

                    <section class="cbt-dev-status-item">
                        <strong>Sync Queue Inspector</strong>
                        <div id="cbt-diagnostics-sync" class="cbt-dev-diag-meta">
                            <p class="cbt-dev-empty">Belum ada snapshot sync.</p>
                        </div>
                        <div class="cbt-dev-actions" style="margin-top:12px;">
                            <button type="button" class="button" id="cbt-diagnostics-refresh-sync">Refresh</button>
                                <button type="button" class="button" id="cbt-clear-sync-snapshot">Clear Sync Snapshot</button>
                            </div>
                        </section>

                    <section class="cbt-dev-status-item cbt-dev-diag-span-2">
                        <strong>REST Inspector</strong>
                        <p class="cbt-dev-muted" style="margin:4px 0 12px;">Klik tombol <strong>View</strong> untuk melihat payload request/response yang sudah di-redact.</p>
                        <div class="cbt-dev-rest-toolbar">
                            <div class="cbt-dev-field is-search">
                                <label for="cbt-rest-search">Search</label>
                                <input type="search" id="cbt-rest-search" placeholder="Cari endpoint, method, status, atau error..." />
                            </div>
                            <div class="cbt-dev-field">
                                <label for="cbt-rest-method-filter">Method</label>
                                <select id="cbt-rest-method-filter">
                                    <option value="all">All</option>
                                    <option value="GET">GET</option>
                                    <option value="POST">POST</option>
                                    <option value="PUT">PUT</option>
                                    <option value="PATCH">PATCH</option>
                                    <option value="DELETE">DELETE</option>
                                </select>
                            </div>
                            <div class="cbt-dev-field">
                                <label for="cbt-rest-status-filter">Status</label>
                                <select id="cbt-rest-status-filter">
                                    <option value="all">All</option>
                                    <option value="success">Success</option>
                                    <option value="error">Error</option>
                                    <option value="network">Network</option>
                                </select>
                            </div>
                            <div class="cbt-dev-field">
                                <label for="cbt-rest-page-size">Per Page</label>
                                <select id="cbt-rest-page-size">
                                    <option value="5">5</option>
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                        </div>
                        <div class="cbt-dev-diag-table-wrap">
                            <table class="cbt-dev-diag-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Endpoint</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Duration</th>
                                        <th>Summary</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody id="cbt-diagnostics-rest-body">
                                    <tr>
                                        <td colspan="7" class="cbt-dev-empty">Belum ada request CBT yang tercatat.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="cbt-dev-rest-pagination">
                            <div id="cbt-rest-pagination-meta" class="cbt-dev-rest-pagination-meta">Menampilkan 0 dari 0 request.</div>
                            <div class="cbt-dev-actions">
                                <button type="button" class="button" id="cbt-rest-prev-page">Previous</button>
                                <button type="button" class="button" id="cbt-rest-next-page">Next</button>
                            </div>
                        </div>
                    </section>

                    <section class="cbt-dev-status-item cbt-dev-diag-span-2">
                        <strong>Bug Bundle</strong>
                        <p class="cbt-dev-muted" style="margin:4px 0 12px;">Export JSON dari browser admin saat ini berisi source aktif, snapshot runtime, request terakhir, error terakhir, dan ringkasan storage CBT.</p>
                        <div class="cbt-dev-actions">
                            <button type="button" class="button button-primary" id="cbt-diagnostics-export">Export Bug Report</button>
                            <button type="button" class="button" id="cbt-diagnostics-refresh">Refresh Diagnostics</button>
                        </div>

                        <div style="margin-top:16px;">
                            <strong>Browser State Tools</strong>
                            <p class="cbt-dev-muted" style="margin:6px 0 12px;">Tool ini membersihkan storage browser admin ini, lalu mengirim command ke frontend dev tab aktif agar state yang relevan ikut diselaraskan.</p>
                            <div class="cbt-dev-actions">
                                <button type="button" class="button" id="cbt-clear-rest-logs">Clear REST Logs</button>
                                <button type="button" class="button" id="cbt-clear-auth-session">Clear Auth Session</button>
                                <button type="button" class="button" id="cbt-clear-question-cache">Clear Question Cache</button>
                                <button type="button" class="button" id="cbt-clear-attempt-ui-state">Clear Attempt UI State</button>
                                <button type="button" class="button" id="cbt-clear-debug-snapshot">Clear Debug Snapshot</button>
                                <span class="cbt-dev-tool-status" id="cbt-diagnostics-tools-status" aria-live="polite"></span>
                            </div>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="cbt-dev-tab-panel" data-dev-tab-panel="storage">
        <section class="cbt-dev-card cbt-dev-card-full">
            <h2>Storage Explorer</h2>
            <p class="cbt-dev-card-subtitle">Inspector read-only untuk localStorage, sessionStorage, dan IndexedDB yang relevan dengan frontend CBT.</p>

            <?php if (!$frontend_diagnostics_enabled): ?>
                <div class="cbt-dev-diag-note">
                    Storage Explorer hanya aktif saat source frontend = <strong>Vite Dev Server</strong> atau <strong>Stable Test Mode</strong> dan user saat ini punya capability <code>manage_options</code>.
                </div>
            <?php else: ?>
                <div class="cbt-dev-filter-row" id="cbt-storage-area-filters">
                    <button type="button" class="button is-active" data-storage-area="local">LocalStorage</button>
                    <button type="button" class="button" data-storage-area="session">SessionStorage</button>
                    <button type="button" class="button" data-storage-area="indexeddb">IndexedDB</button>
                </div>
                <div class="cbt-dev-rest-toolbar">
                    <div class="cbt-dev-field is-search">
                        <label for="cbt-storage-search">Search</label>
                        <input type="search" id="cbt-storage-search" placeholder="Cari key, preview value, atau marker record..." />
                    </div>
                    <div class="cbt-dev-field">
                        <label for="cbt-storage-page-size">Per Page</label>
                        <select id="cbt-storage-page-size">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
                <div class="cbt-dev-actions" style="margin-bottom:12px;">
                    <button type="button" class="button" id="cbt-storage-refresh">Refresh Storage</button>
                    <span class="cbt-dev-tool-status" id="cbt-storage-status" aria-live="polite"></span>
                </div>
                <div id="cbt-storage-note" class="cbt-dev-diag-note" style="margin-bottom:12px;">
                    Hanya key yang relevan dengan prefix frontend CBT dan diagnostics yang ditampilkan.
                </div>
                <div class="cbt-dev-diag-table-wrap">
                    <table class="cbt-dev-diag-table">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Key</th>
                                <th>Preview</th>
                                <th>Meta</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody id="cbt-storage-body">
                            <tr>
                                <td colspan="5" class="cbt-dev-empty">Belum ada data storage.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="cbt-dev-rest-pagination">
                    <div id="cbt-storage-pagination-meta" class="cbt-dev-rest-pagination-meta">Menampilkan 0 dari 0 entry.</div>
                    <div class="cbt-dev-actions">
                        <button type="button" class="button" id="cbt-storage-prev-page">Previous</button>
                        <button type="button" class="button" id="cbt-storage-next-page">Next</button>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="cbt-dev-tab-panel" data-dev-tab-panel="scenarios">
        <section class="cbt-dev-card cbt-dev-card-full">
            <h2>Scenario Lab</h2>
            <p class="cbt-dev-card-subtitle">Simulasi bug browser-local untuk frontend dev atau stable tab admin ini, dikelompokkan per area supaya lebih cepat dipakai saat triage.</p>

            <?php if (!$frontend_diagnostics_enabled): ?>
                <div class="cbt-dev-diag-note">
                    Scenario toggles hanya aktif saat source frontend = <strong>Vite Dev Server</strong> atau <strong>Stable Test Mode</strong> dan user saat ini punya capability <code>manage_options</code>.
                </div>
            <?php else: ?>
                <div class="cbt-dev-scenario-lab">
                    <div>
                        <strong>Active Scenarios</strong>
                        <div id="cbt-scenario-summary" class="cbt-dev-scenario-summary is-empty">No active scenarios</div>
                    </div>

                    <div>
                        <strong>Quick Presets</strong>
                        <div class="cbt-dev-scenario-presets" id="cbt-scenario-preset-bar">
                            <button type="button" class="button" data-scenario-preset="offline-answering">Offline Answering</button>
                            <button type="button" class="button" data-scenario-preset="slow-question-load">Slow Question Load</button>
                            <button type="button" class="button" data-scenario-preset="finish-failure">Finish Failure</button>
                            <button type="button" class="button" data-scenario-preset="session-trouble">Session Trouble</button>
                        </div>
                    </div>

                    <form id="cbt-diagnostics-scenario-form" class="cbt-dev-diag-form">
                        <div class="cbt-dev-scenario-grid">
                            <section class="cbt-dev-scenario-card">
                                <h3>Network &amp; API</h3>
                                <p>Simulasi koneksi dan request CBT umum tanpa mengubah seluruh browser.</p>
                                <div class="cbt-dev-scenario-field-row">
                                    <label class="cbt-dev-scenario-toggle"><input type="checkbox" id="cbt-scenario-force-offline" /> Force Offline</label>
                                    <div class="cbt-dev-diag-form-row">
                                        <label for="cbt-scenario-api-latency">API Latency</label>
                                        <select id="cbt-scenario-api-latency">
                                            <option value="0">Normal</option>
                                            <option value="800">800ms</option>
                                            <option value="2000">2000ms</option>
                                        </select>
                                    </div>
                                    <div class="cbt-dev-diag-form-row">
                                        <label for="cbt-scenario-fail-api-target">Fail Next API Request</label>
                                        <select id="cbt-scenario-fail-api-target">
                                            <option value="off">Off</option>
                                            <option value="any">Any</option>
                                            <option value="login">login</option>
                                            <option value="exams">exams</option>
                                            <option value="start_attempt">start_attempt</option>
                                            <option value="submit_answer">submit_answer</option>
                                            <option value="submit_answers_batch">submit_answers_batch</option>
                                            <option value="session">session</option>
                                            <option value="finish_attempt">finish_attempt</option>
                                            <option value="result">result</option>
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <section class="cbt-dev-scenario-card">
                                <h3>Question Loading</h3>
                                <p>Kontrol skenario yang berpengaruh ke load window soal, prefetch, dan chunk antarmuka.</p>
                                <div class="cbt-dev-scenario-field-row">
                                    <div class="cbt-dev-diag-form-row">
                                        <label for="cbt-scenario-question-window-latency">Slow Question Window</label>
                                        <select id="cbt-scenario-question-window-latency">
                                            <option value="0">Off</option>
                                            <option value="600">600ms</option>
                                            <option value="1500">1500ms</option>
                                            <option value="3000">3000ms</option>
                                        </select>
                                    </div>
                                    <div class="cbt-dev-diag-form-row">
                                        <label for="cbt-scenario-fail-question-window-target">Fail Next Question Window</label>
                                        <select id="cbt-scenario-fail-question-window-target">
                                            <option value="off">Off</option>
                                            <option value="any">Any</option>
                                            <option value="current">Current Window</option>
                                            <option value="prefetch">Prefetch Only</option>
                                        </select>
                                    </div>
                                    <div class="cbt-dev-diag-form-row">
                                        <label for="cbt-scenario-fail-chunk-target">Fail Next Chunk Load</label>
                                        <select id="cbt-scenario-fail-chunk-target">
                                            <option value="off">Off</option>
                                            <option value="exam">exam</option>
                                            <option value="result">result</option>
                                            <option value="calculator">calculator</option>
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <section class="cbt-dev-scenario-card">
                                <h3>Sync &amp; Finish</h3>
                                <p>Simulasi antrean autosave dan kegagalan submit akhir tanpa memutus koneksi browser.</p>
                                <div class="cbt-dev-scenario-field-row">
                                    <label class="cbt-dev-scenario-toggle"><input type="checkbox" id="cbt-scenario-force-pending-sync" /> Force Pending Sync</label>
                                    <label class="cbt-dev-scenario-toggle"><input type="checkbox" id="cbt-scenario-disable-retry" /> Disable Auto Retry</label>
                                    <div class="cbt-dev-diag-form-row">
                                        <label for="cbt-scenario-fail-finish-mode">Fail Finish Once</label>
                                        <select id="cbt-scenario-fail-finish-mode">
                                            <option value="off">Off</option>
                                            <option value="network">Network</option>
                                            <option value="server">Server 500</option>
                                            <option value="validation">Validation-like</option>
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <section class="cbt-dev-scenario-card">
                                <h3>Session</h3>
                                <p>Simulasi jalur heartbeat dan refresh sesi tanpa memengaruhi API lain.</p>
                                <div class="cbt-dev-scenario-field-row">
                                    <div class="cbt-dev-diag-form-row">
                                        <label for="cbt-scenario-heartbeat-mode">Heartbeat Timeout</label>
                                        <select id="cbt-scenario-heartbeat-mode">
                                            <option value="off">Off</option>
                                            <option value="slow">Slow Heartbeat</option>
                                            <option value="fail-next">Fail Next Heartbeat</option>
                                            <option value="timeout">Timeout Heartbeat</option>
                                        </select>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="cbt-dev-scenario-actions">
                            <button type="button" class="button button-primary" id="cbt-scenarios-apply">Apply</button>
                            <button type="button" class="button" id="cbt-scenarios-reset">Reset All</button>
                            <button type="button" class="button" id="cbt-scenarios-copy-summary">Copy Scenario Summary</button>
                            <span class="cbt-dev-tool-status" id="cbt-scenarios-status" aria-live="polite"></span>
                        </div>
                        <div class="cbt-dev-scenario-footnote">Scenario hanya berlaku untuk frontend dev atau stable tab browser admin ini. Quick preset hanya helper UI dan tetap bisa disesuaikan manual sebelum <strong>Apply</strong>.</div>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="cbt-dev-tab-panel" data-dev-tab-panel="timeline">
        <section class="cbt-dev-card cbt-dev-card-full">
            <h2>Session/Attempt Timeline</h2>
            <p class="cbt-dev-card-subtitle">Urutan event penting frontend CBT dari browser admin yang sama.</p>

            <?php if (!$frontend_diagnostics_enabled): ?>
                <div class="cbt-dev-diag-note">
                    Timeline hanya aktif saat source frontend = <strong>Vite Dev Server</strong> atau <strong>Stable Test Mode</strong> dan user saat ini punya capability <code>manage_options</code>.
                </div>
            <?php else: ?>
                <div class="cbt-dev-filter-row" id="cbt-diagnostics-timeline-filters">
                    <button type="button" class="button is-active" data-timeline-filter="all">All</button>
                    <button type="button" class="button" data-timeline-filter="auth">Auth</button>
                    <button type="button" class="button" data-timeline-filter="attempt">Attempt</button>
                    <button type="button" class="button" data-timeline-filter="sync">Sync</button>
                    <button type="button" class="button" data-timeline-filter="finish">Finish</button>
                    <button type="button" class="button" data-timeline-filter="result">Result</button>
                    <button type="button" class="button" data-timeline-filter="security">Security</button>
                    <button type="button" class="button" data-timeline-filter="error">Error</button>
                </div>
                <div class="cbt-dev-rest-toolbar">
                    <div class="cbt-dev-field is-search">
                        <label for="cbt-timeline-search">Search</label>
                        <input type="search" id="cbt-timeline-search" placeholder="Cari kind, stage, attempt, exam, atau summary..." />
                    </div>
                    <div class="cbt-dev-field">
                        <label for="cbt-timeline-page-size">Per Page</label>
                        <select id="cbt-timeline-page-size">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
                <div class="cbt-dev-actions" style="margin-bottom:12px;">
                    <button type="button" class="button" id="cbt-diagnostics-export-timeline">Export Timeline</button>
                    <button type="button" class="button" id="cbt-diagnostics-refresh-timeline">Refresh Timeline</button>
                    <button type="button" class="button" id="cbt-clear-timeline">Clear Timeline</button>
                </div>
                <div class="cbt-dev-diag-table-wrap">
                    <table class="cbt-dev-diag-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Kind</th>
                                <th>Stage</th>
                                <th>Attempt</th>
                                <th>Exam</th>
                                <th>Summary</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody id="cbt-diagnostics-timeline-body">
                            <tr>
                                <td colspan="7" class="cbt-dev-empty">Belum ada event timeline CBT.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="cbt-dev-rest-pagination">
                    <div id="cbt-timeline-pagination-meta" class="cbt-dev-rest-pagination-meta">Menampilkan 0 dari 0 event.</div>
                    <div class="cbt-dev-actions">
                        <button type="button" class="button" id="cbt-timeline-prev-page">Previous</button>
                        <button type="button" class="button" id="cbt-timeline-next-page">Next</button>
                    </div>
                </div>

                <section class="cbt-dev-status-item" style="margin-top:18px;">
                    <strong>Last Action Trail</strong>
                    <p class="cbt-dev-muted" style="margin:4px 0 12px;">Aksi UI/runtime bernilai tinggi yang paling langsung memengaruhi state dan tampilan frontend.</p>
                    <div class="cbt-dev-rest-toolbar">
                        <div class="cbt-dev-field is-search">
                            <label for="cbt-action-trail-search">Search</label>
                            <input type="search" id="cbt-action-trail-search" placeholder="Cari kind, stage, action, atau summary..." />
                        </div>
                        <div class="cbt-dev-field">
                            <label for="cbt-action-trail-page-size">Per Page</label>
                            <select id="cbt-action-trail-page-size">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                            </select>
                        </div>
                    </div>
                    <div class="cbt-dev-actions" style="margin-bottom:12px;">
                        <button type="button" class="button" id="cbt-action-trail-refresh">Refresh Action Trail</button>
                        <button type="button" class="button" id="cbt-clear-action-trail">Clear Action Trail</button>
                    </div>
                    <div class="cbt-dev-diag-table-wrap">
                        <table class="cbt-dev-diag-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Kind</th>
                                    <th>Stage</th>
                                    <th>Attempt</th>
                                    <th>Exam</th>
                                    <th>Summary</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody id="cbt-action-trail-body">
                                <tr>
                                    <td colspan="7" class="cbt-dev-empty">Belum ada action trail.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cbt-dev-rest-pagination">
                        <div id="cbt-action-trail-pagination-meta" class="cbt-dev-rest-pagination-meta">Menampilkan 0 dari 0 action.</div>
                        <div class="cbt-dev-actions">
                            <button type="button" class="button" id="cbt-action-trail-prev-page">Previous</button>
                            <button type="button" class="button" id="cbt-action-trail-next-page">Next</button>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </section>
    </div>
    </div>

    <script>
        (function () {
            var config = {
                attemptUiPrefix: <?php echo wp_json_encode($attempt_ui_prefix); ?>,
                authSessionKey: <?php echo wp_json_encode($auth_session_key); ?>,
                diagnosticsCommandKey: <?php echo wp_json_encode($diagnostics_command_key); ?>,
                diagnosticsEnabled: <?php echo wp_json_encode($frontend_diagnostics_enabled); ?>,
                diagnosticsErrorsKey: <?php echo wp_json_encode($diagnostics_errors_key); ?>,
                diagnosticsMaxEntries: <?php echo wp_json_encode($diagnostics_max_entries); ?>,
                diagnosticsRequestsKey: <?php echo wp_json_encode($diagnostics_requests_key); ?>,
                diagnosticsRenderStatsKey: <?php echo wp_json_encode($diagnostics_render_stats_key); ?>,
                diagnosticsScenarioKey: <?php echo wp_json_encode($diagnostics_scenario_key); ?>,
                diagnosticsSnapshotKey: <?php echo wp_json_encode($diagnostics_snapshot_key); ?>,
                diagnosticsSyncKey: <?php echo wp_json_encode($diagnostics_sync_key); ?>,
                diagnosticsStateKey: <?php echo wp_json_encode($diagnostics_state_key); ?>,
                diagnosticsActionTrailKey: <?php echo wp_json_encode($diagnostics_action_trail_key); ?>,
                diagnosticsTimelineKey: <?php echo wp_json_encode($diagnostics_timeline_key); ?>,
                diagnosticsTimelineMaxEntries: <?php echo wp_json_encode($diagnostics_timeline_max_entries); ?>,
                frontendAssetSource: <?php echo wp_json_encode((string) ($resolved_source['label'] ?? 'Production Build')); ?>,
                indexedDbName: <?php echo wp_json_encode($indexed_db_name); ?>,
                questionCacheItemPrefix: <?php echo wp_json_encode($question_cache_item_prefix); ?>,
                questionCacheMetaPrefix: <?php echo wp_json_encode($question_cache_meta_prefix); ?>,
                questionCacheSessionPrefix: <?php echo wp_json_encode($question_cache_session_prefix); ?>,
                storagePrefix: <?php echo wp_json_encode($storage_prefix); ?>,
                doubtfulPrefix: <?php echo wp_json_encode($doubtful_prefix); ?>
            };

            var clearAllButton = document.getElementById('cbt-clear-frontend-browser-state');
            var clearAllStatus = document.getElementById('cbt-clear-frontend-browser-state-status');
            var tabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-dev-tab]'));
            var tabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-dev-tab-panel]'));
            var stateStatusNode = document.getElementById('cbt-state-status');
            var toolsStatusNode = document.getElementById('cbt-diagnostics-tools-status');
            var stateAppNode = document.getElementById('cbt-state-app');
            var stateExamNode = document.getElementById('cbt-state-exam');
            var stateSyncNode = document.getElementById('cbt-state-sync');
            var stateResultNode = document.getElementById('cbt-state-result');
            var stateScenarioNode = document.getElementById('cbt-state-scenario');
            var stateRenderStatsNode = document.getElementById('cbt-state-render-stats');
            var stateRefreshButton = document.getElementById('cbt-state-refresh');
            var stateCopyJsonButton = document.getElementById('cbt-state-copy-json');
            var stateExportJsonButton = document.getElementById('cbt-state-export-json');
            var restBodyNode = document.getElementById('cbt-diagnostics-rest-body');
            var restSearchNode = document.getElementById('cbt-rest-search');
            var restMethodFilterNode = document.getElementById('cbt-rest-method-filter');
            var restStatusFilterNode = document.getElementById('cbt-rest-status-filter');
            var restPageSizeNode = document.getElementById('cbt-rest-page-size');
            var restPaginationMetaNode = document.getElementById('cbt-rest-pagination-meta');
            var restPrevPageButton = document.getElementById('cbt-rest-prev-page');
            var restNextPageButton = document.getElementById('cbt-rest-next-page');
            var snapshotNode = document.getElementById('cbt-diagnostics-snapshot');
            var syncNode = document.getElementById('cbt-diagnostics-sync');
            var errorsNode = document.getElementById('cbt-diagnostics-errors');
            var timelineBodyNode = document.getElementById('cbt-diagnostics-timeline-body');
            var timelineSearchNode = document.getElementById('cbt-timeline-search');
            var timelinePageSizeNode = document.getElementById('cbt-timeline-page-size');
            var timelinePaginationMetaNode = document.getElementById('cbt-timeline-pagination-meta');
            var timelinePrevPageButton = document.getElementById('cbt-timeline-prev-page');
            var timelineNextPageButton = document.getElementById('cbt-timeline-next-page');
            var actionTrailBodyNode = document.getElementById('cbt-action-trail-body');
            var actionTrailSearchNode = document.getElementById('cbt-action-trail-search');
            var actionTrailPageSizeNode = document.getElementById('cbt-action-trail-page-size');
            var actionTrailPaginationMetaNode = document.getElementById('cbt-action-trail-pagination-meta');
            var actionTrailPrevPageButton = document.getElementById('cbt-action-trail-prev-page');
            var actionTrailNextPageButton = document.getElementById('cbt-action-trail-next-page');
            var actionTrailRefreshButton = document.getElementById('cbt-action-trail-refresh');
            var scenarioForm = document.getElementById('cbt-diagnostics-scenario-form');
            var exportButton = document.getElementById('cbt-diagnostics-export');
            var refreshButton = document.getElementById('cbt-diagnostics-refresh');
            var refreshSyncButton = document.getElementById('cbt-diagnostics-refresh-sync');
            var refreshTimelineButton = document.getElementById('cbt-diagnostics-refresh-timeline');
            var exportTimelineButton = document.getElementById('cbt-diagnostics-export-timeline');
            var clearRestLogsButton = document.getElementById('cbt-clear-rest-logs');
            var clearAuthSessionButton = document.getElementById('cbt-clear-auth-session');
            var clearQuestionCacheButton = document.getElementById('cbt-clear-question-cache');
            var clearAttemptUiStateButton = document.getElementById('cbt-clear-attempt-ui-state');
            var clearDebugSnapshotButton = document.getElementById('cbt-clear-debug-snapshot');
            var clearSyncSnapshotButton = document.getElementById('cbt-clear-sync-snapshot');
            var clearTimelineButton = document.getElementById('cbt-clear-timeline');
            var clearRenderStatsButton = document.getElementById('cbt-clear-render-stats');
            var clearActionTrailButton = document.getElementById('cbt-clear-action-trail');
            var scenarioApplyButton = document.getElementById('cbt-scenarios-apply');
            var scenarioCopySummaryButton = document.getElementById('cbt-scenarios-copy-summary');
            var scenarioSummaryNode = document.getElementById('cbt-scenario-summary');
            var scenarioPresetBar = document.getElementById('cbt-scenario-preset-bar');
            var scenarioResetButton = document.getElementById('cbt-scenarios-reset');
            var scenarioStatusNode = document.getElementById('cbt-scenarios-status');
            var storageBodyNode = document.getElementById('cbt-storage-body');
            var storageAreaFiltersNode = document.getElementById('cbt-storage-area-filters');
            var storageSearchNode = document.getElementById('cbt-storage-search');
            var storagePageSizeNode = document.getElementById('cbt-storage-page-size');
            var storagePaginationMetaNode = document.getElementById('cbt-storage-pagination-meta');
            var storagePrevPageButton = document.getElementById('cbt-storage-prev-page');
            var storageNextPageButton = document.getElementById('cbt-storage-next-page');
            var storageRefreshButton = document.getElementById('cbt-storage-refresh');
            var storageStatusNode = document.getElementById('cbt-storage-status');
            var storageNoteNode = document.getElementById('cbt-storage-note');
            var requestLogsCache = [];
            var visibleRequestLogsCache = [];
            var timelineLogsCache = [];
            var visibleTimelineLogsCache = [];
            var actionTrailLogsCache = [];
            var visibleActionTrailLogsCache = [];
            var storageEntriesCache = [];
            var visibleStorageEntriesCache = [];
            var activeTimelineFilter = 'all';
            var activeStorageArea = 'local';
            var restCurrentPage = 1;
            var timelineCurrentPage = 1;
            var actionTrailCurrentPage = 1;
            var storageCurrentPage = 1;
            var activeRequestDetailIndex = -1;
            var activeTimelineDetailIndex = -1;
            var activeActionTrailDetailIndex = -1;
            var activeStorageDetailIndex = -1;
            var scenarioFormDirty = false;
            var tabStorageKey = 'cbt_developer_active_tab_v1';

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function parseJson(key, fallback) {
                try {
                    var raw = window.localStorage.getItem(key);
                    if (!raw) {
                        return fallback;
                    }

                    var parsed = JSON.parse(raw);
                    return parsed === null || parsed === undefined ? fallback : parsed;
                } catch (error) {
                    return fallback;
                }
            }

            function formatTime(value) {
                if (!value) {
                    return '-';
                }

                var date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return String(value);
                }

                return date.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }

            function formatDateTime(value) {
                if (!value) {
                    return '-';
                }

                var date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return String(value);
                }

                return date.toLocaleString('id-ID');
            }

            function setStatus(node, message, tone) {
                if (!node) {
                    return;
                }

                node.textContent = message;
                node.style.color = tone === 'error' ? '#b91c1c' : '#047857';
            }

            function isKnownTab(tabId) {
                return ['overview', 'state', 'inspector', 'storage', 'scenarios', 'timeline'].indexOf(String(tabId || '')) >= 0;
            }

            function readStoredTab() {
                var hash = String(window.location.hash || '').replace(/^#/, '');
                if (isKnownTab(hash)) {
                    return hash;
                }

                try {
                    var stored = window.localStorage.getItem(tabStorageKey);
                    if (isKnownTab(stored)) {
                        return stored;
                    }
                } catch (error) {
                    // Ignore localStorage read failures for tabs.
                }

                return 'overview';
            }

            function persistTab(tabId) {
                try {
                    window.localStorage.setItem(tabStorageKey, tabId);
                } catch (error) {
                    // Ignore localStorage write failures for tabs.
                }
            }

            function activateTab(tabId) {
                var normalizedTab = isKnownTab(tabId) ? tabId : 'overview';

                tabButtons.forEach(function (button) {
                    var isActive = String(button.getAttribute('data-dev-tab') || '') === normalizedTab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                tabPanels.forEach(function (panel) {
                    var isActive = String(panel.getAttribute('data-dev-tab-panel') || '') === normalizedTab;
                    panel.classList.toggle('is-active', isActive);
                });

                persistTab(normalizedTab);
                if (window.location.hash !== '#' + normalizedTab) {
                    window.history.replaceState(null, '', '#' + normalizedTab);
                }
            }

            function clearStorageAreaByPrefixes(area, prefixes) {
                if (!area || typeof area.length !== 'number') {
                    return 0;
                }

                var removed = 0;
                for (var index = area.length - 1; index >= 0; index -= 1) {
                    var key = typeof area.key === 'function' ? area.key(index) : '';
                    if (typeof key !== 'string' || key === '') {
                        continue;
                    }

                    var shouldRemove = prefixes.some(function (prefix) {
                        return typeof prefix === 'string' && prefix !== '' && key.indexOf(prefix) === 0;
                    });
                    if (!shouldRemove) {
                        continue;
                    }

                    area.removeItem(key);
                    removed += 1;
                }

                return removed;
            }

            function removeLocalStorageKeys(keys) {
                var removed = 0;
                keys.forEach(function (key) {
                    if (!key) {
                        return;
                    }

                    if (window.localStorage.getItem(key) !== null) {
                        removed += 1;
                    }
                    window.localStorage.removeItem(key);
                });
                return removed;
            }

            function deleteIndexedDb() {
                return new Promise(function (resolve) {
                    if (!window.indexedDB || typeof window.indexedDB.deleteDatabase !== 'function') {
                        resolve({
                            ok: true,
                            message: 'IndexedDB tidak tersedia di browser ini.'
                        });
                        return;
                    }

                    try {
                        var request = window.indexedDB.deleteDatabase(config.indexedDbName);
                        request.onsuccess = function () {
                            resolve({
                                ok: true,
                                message: 'IndexedDB dihapus.'
                            });
                        };
                        request.onerror = function () {
                            resolve({
                                ok: false,
                                message: 'IndexedDB gagal dihapus.'
                            });
                        };
                        request.onblocked = function () {
                            resolve({
                                ok: false,
                                message: 'IndexedDB masih dipakai tab lain.'
                            });
                        };
                    } catch (error) {
                        resolve({
                            ok: false,
                            message: error && error.message ? error.message : 'IndexedDB gagal diproses.'
                        });
                    }
                });
            }

            function broadcastCommand(action) {
                if (!config.diagnosticsCommandKey) {
                    return;
                }

                try {
                    window.localStorage.setItem(config.diagnosticsCommandKey, JSON.stringify({
                        action: action,
                        issuedAt: new Date().toISOString(),
                        source: 'cbt-developer'
                    }));
                } catch (error) {
                    // Ignore localStorage broadcast failures.
                }
            }

            function readRequestLogs() {
                var logs = parseJson(config.diagnosticsRequestsKey, []);
                return Array.isArray(logs) ? logs : [];
            }

            function readSnapshot() {
                var snapshot = parseJson(config.diagnosticsSnapshotKey, null);
                return snapshot && typeof snapshot === 'object' ? snapshot : null;
            }

            function readErrors() {
                var errors = parseJson(config.diagnosticsErrorsKey, []);
                return Array.isArray(errors) ? errors : [];
            }

            function readSyncSnapshot() {
                var snapshot = parseJson(config.diagnosticsSyncKey, null);
                return snapshot && typeof snapshot === 'object' ? snapshot : null;
            }

            function readTimeline() {
                var timeline = parseJson(config.diagnosticsTimelineKey, []);
                return Array.isArray(timeline) ? timeline : [];
            }

            function readRenderStats() {
                var stats = parseJson(config.diagnosticsRenderStatsKey, null);
                return stats && typeof stats === 'object' ? stats : null;
            }

            function readActionTrail() {
                var trail = parseJson(config.diagnosticsActionTrailKey, []);
                return Array.isArray(trail) ? trail : [];
            }

            function readDiagnosticsState() {
                var state = parseJson(config.diagnosticsStateKey, {});
                return state && typeof state === 'object' ? state : {};
            }

            function buildDefaultScenarioState() {
                return {
                    forceOffline: false,
                    apiLatencyMs: 0,
                    failNextApiRequest: {
                        enabled: false,
                        target: 'any'
                    },
                    failNextChunkLoad: {
                        enabled: false,
                        target: 'exam'
                    },
                    questionWindowLatencyMs: 0,
                    failNextQuestionWindow: {
                        enabled: false,
                        target: 'any'
                    },
                    forcePendingSync: false,
                    failFinishOnce: {
                        enabled: false,
                        mode: 'network'
                    },
                    heartbeatScenario: 'off',
                    disableAutoRetry: false,
                    updatedAt: ''
                };
            }

            function normalizeScenarioChoice(value, allowed, fallback) {
                var normalized = String(value || '').trim().toLowerCase();
                return allowed.indexOf(normalized) >= 0 ? normalized : fallback;
            }

            function readScenarioState() {
                var raw = parseJson(config.diagnosticsScenarioKey, buildDefaultScenarioState());
                if (!raw || typeof raw !== 'object') {
                    raw = buildDefaultScenarioState();
                }

                return {
                    forceOffline: Boolean(raw.forceOffline),
                    apiLatencyMs: [0, 800, 2000].indexOf(Number(raw.apiLatencyMs) || 0) >= 0 ? (Number(raw.apiLatencyMs) || 0) : 0,
                    failNextApiRequest: {
                        enabled: Boolean(raw.failNextApiRequest && raw.failNextApiRequest.enabled),
                        target: normalizeScenarioChoice(raw.failNextApiRequest && raw.failNextApiRequest.target, ['any', 'login', 'exams', 'start_attempt', 'submit_answer', 'submit_answers_batch', 'session', 'finish_attempt', 'result'], 'any')
                    },
                    failNextChunkLoad: {
                        enabled: Boolean(raw.failNextChunkLoad && raw.failNextChunkLoad.enabled),
                        target: normalizeScenarioChoice(raw.failNextChunkLoad && raw.failNextChunkLoad.target, ['exam', 'result', 'calculator'], 'exam')
                    },
                    questionWindowLatencyMs: [0, 600, 1500, 3000].indexOf(Number(raw.questionWindowLatencyMs) || 0) >= 0 ? (Number(raw.questionWindowLatencyMs) || 0) : 0,
                    failNextQuestionWindow: {
                        enabled: Boolean(raw.failNextQuestionWindow && raw.failNextQuestionWindow.enabled),
                        target: normalizeScenarioChoice(raw.failNextQuestionWindow && raw.failNextQuestionWindow.target, ['any', 'current', 'prefetch'], 'any')
                    },
                    forcePendingSync: Boolean(raw.forcePendingSync),
                    failFinishOnce: {
                        enabled: Boolean(raw.failFinishOnce && raw.failFinishOnce.enabled),
                        mode: normalizeScenarioChoice(raw.failFinishOnce && raw.failFinishOnce.mode, ['network', 'server', 'validation'], 'network')
                    },
                    heartbeatScenario: normalizeScenarioChoice(raw.heartbeatScenario, ['off', 'slow', 'fail-next', 'timeout'], 'off'),
                    disableAutoRetry: Boolean(raw.disableAutoRetry),
                    updatedAt: typeof raw.updatedAt === 'string' ? raw.updatedAt : ''
                };
            }

            function writeScenarioState(nextState) {
                window.localStorage.setItem(config.diagnosticsScenarioKey, JSON.stringify(nextState));
            }

            function appendActionTrailEntry(kind, summary, meta) {
                var current = readActionTrail();
                current.unshift({
                    time: new Date().toISOString(),
                    kind: String(kind || 'action'),
                    summary: String(summary || ''),
                    meta: Object.assign({
                        stage: readSnapshot() && readSnapshot().stage ? String(readSnapshot().stage) : '-'
                    }, meta || {})
                });
                if (current.length > 30) {
                    current.length = 30;
                }
                window.localStorage.setItem(config.diagnosticsActionTrailKey, JSON.stringify(current));
            }

            function copyTextToClipboard(text) {
                var value = String(text || '');
                if (window.navigator && window.navigator.clipboard && typeof window.navigator.clipboard.writeText === 'function') {
                    return window.navigator.clipboard.writeText(value);
                }

                return new Promise(function (resolve, reject) {
                    try {
                        var textarea = document.createElement('textarea');
                        textarea.value = value;
                        textarea.setAttribute('readonly', 'readonly');
                        textarea.style.position = 'fixed';
                        textarea.style.left = '-9999px';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                });
            }

            function exportJsonFile(filenamePrefix, payload) {
                var blob = new Blob([JSON.stringify(payload, null, 2)], {
                    type: 'application/json'
                });
                var href = window.URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = href;
                link.download = filenamePrefix + '-' + Date.now() + '.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.setTimeout(function () {
                    window.URL.revokeObjectURL(href);
                }, 1000);
            }

            function serializeScenarioState(state) {
                var safeState = state && typeof state === 'object' ? state : buildDefaultScenarioState();
                return JSON.stringify({
                    forceOffline: Boolean(safeState.forceOffline),
                    apiLatencyMs: Number(safeState.apiLatencyMs) || 0,
                    failNextApiRequest: {
                        enabled: Boolean(safeState.failNextApiRequest && safeState.failNextApiRequest.enabled),
                        target: String(safeState.failNextApiRequest && safeState.failNextApiRequest.target ? safeState.failNextApiRequest.target : 'any')
                    },
                    failNextChunkLoad: {
                        enabled: Boolean(safeState.failNextChunkLoad && safeState.failNextChunkLoad.enabled),
                        target: String(safeState.failNextChunkLoad && safeState.failNextChunkLoad.target ? safeState.failNextChunkLoad.target : 'exam')
                    },
                    questionWindowLatencyMs: Number(safeState.questionWindowLatencyMs) || 0,
                    failNextQuestionWindow: {
                        enabled: Boolean(safeState.failNextQuestionWindow && safeState.failNextQuestionWindow.enabled),
                        target: String(safeState.failNextQuestionWindow && safeState.failNextQuestionWindow.target ? safeState.failNextQuestionWindow.target : 'any')
                    },
                    forcePendingSync: Boolean(safeState.forcePendingSync),
                    failFinishOnce: {
                        enabled: Boolean(safeState.failFinishOnce && safeState.failFinishOnce.enabled),
                        mode: String(safeState.failFinishOnce && safeState.failFinishOnce.mode ? safeState.failFinishOnce.mode : 'network')
                    },
                    heartbeatScenario: String(safeState.heartbeatScenario || 'off'),
                    disableAutoRetry: Boolean(safeState.disableAutoRetry)
                });
            }

            function readScenarioFormState() {
                var forceOfflineNode = document.getElementById('cbt-scenario-force-offline');
                var latencyNode = document.getElementById('cbt-scenario-api-latency');
                var questionWindowLatencyNode = document.getElementById('cbt-scenario-question-window-latency');
                var forcePendingSyncNode = document.getElementById('cbt-scenario-force-pending-sync');
                var disableRetryNode = document.getElementById('cbt-scenario-disable-retry');
                var failApiNode = document.getElementById('cbt-scenario-fail-api-target');
                var failChunkNode = document.getElementById('cbt-scenario-fail-chunk-target');
                var failQuestionWindowNode = document.getElementById('cbt-scenario-fail-question-window-target');
                var failFinishNode = document.getElementById('cbt-scenario-fail-finish-mode');
                var heartbeatNode = document.getElementById('cbt-scenario-heartbeat-mode');

                return {
                    forceOffline: Boolean(forceOfflineNode && forceOfflineNode.checked),
                    apiLatencyMs: Number(latencyNode && latencyNode.value ? latencyNode.value : 0) || 0,
                    questionWindowLatencyMs: Number(questionWindowLatencyNode && questionWindowLatencyNode.value ? questionWindowLatencyNode.value : 0) || 0,
                    failNextApiRequest: {
                        enabled: Boolean(failApiNode && failApiNode.value && failApiNode.value !== 'off'),
                        target: failApiNode && failApiNode.value && failApiNode.value !== 'off' ? String(failApiNode.value) : 'any'
                    },
                    failNextChunkLoad: {
                        enabled: Boolean(failChunkNode && failChunkNode.value && failChunkNode.value !== 'off'),
                        target: failChunkNode && failChunkNode.value && failChunkNode.value !== 'off' ? String(failChunkNode.value) : 'exam'
                    },
                    failNextQuestionWindow: {
                        enabled: Boolean(failQuestionWindowNode && failQuestionWindowNode.value && failQuestionWindowNode.value !== 'off'),
                        target: failQuestionWindowNode && failQuestionWindowNode.value && failQuestionWindowNode.value !== 'off' ? String(failQuestionWindowNode.value) : 'any'
                    },
                    forcePendingSync: Boolean(forcePendingSyncNode && forcePendingSyncNode.checked),
                    failFinishOnce: {
                        enabled: Boolean(failFinishNode && failFinishNode.value && failFinishNode.value !== 'off'),
                        mode: failFinishNode && failFinishNode.value && failFinishNode.value !== 'off' ? String(failFinishNode.value) : 'network'
                    },
                    heartbeatScenario: heartbeatNode && heartbeatNode.value ? String(heartbeatNode.value) : 'off',
                    disableAutoRetry: Boolean(disableRetryNode && disableRetryNode.checked),
                    updatedAt: ''
                };
            }

            function buildScenarioSummaryItems(state) {
                var safeState = state && typeof state === 'object' ? state : buildDefaultScenarioState();
                var items = [];

                if (safeState.forceOffline) {
                    items.push({ label: 'OFFLINE', tone: 'danger' });
                }
                if ((Number(safeState.apiLatencyMs) || 0) > 0) {
                    items.push({ label: 'API ' + String(Number(safeState.apiLatencyMs) || 0) + 'MS', tone: 'warning' });
                }
                if ((Number(safeState.questionWindowLatencyMs) || 0) > 0) {
                    items.push({ label: 'Q-WINDOW ' + String(Number(safeState.questionWindowLatencyMs) || 0) + 'MS', tone: 'warning' });
                }
                if (safeState.failNextApiRequest && safeState.failNextApiRequest.enabled) {
                    items.push({ label: 'FAIL API ' + String(safeState.failNextApiRequest.target || 'ANY').toUpperCase(), tone: 'danger' });
                }
                if (safeState.failNextChunkLoad && safeState.failNextChunkLoad.enabled) {
                    items.push({ label: 'FAIL CHUNK ' + String(safeState.failNextChunkLoad.target || 'EXAM').toUpperCase(), tone: 'danger' });
                }
                if (safeState.failNextQuestionWindow && safeState.failNextQuestionWindow.enabled) {
                    items.push({ label: 'FAIL Q-WINDOW ' + String(safeState.failNextQuestionWindow.target || 'ANY').toUpperCase(), tone: 'danger' });
                }
                if (safeState.forcePendingSync) {
                    items.push({ label: 'PENDING SYNC', tone: 'warning' });
                }
                if (safeState.disableAutoRetry) {
                    items.push({ label: 'RETRY OFF', tone: 'warning' });
                }
                if (safeState.failFinishOnce && safeState.failFinishOnce.enabled) {
                    items.push({ label: 'FAIL FINISH ' + String(safeState.failFinishOnce.mode || 'NETWORK').toUpperCase(), tone: 'danger' });
                }
                if (String(safeState.heartbeatScenario || 'off') !== 'off') {
                    items.push({ label: 'HEARTBEAT ' + String(safeState.heartbeatScenario || 'off').toUpperCase(), tone: 'warning' });
                }

                return items;
            }

            function buildScenarioSummaryText(state) {
                var items = buildScenarioSummaryItems(state);
                if (!items.length) {
                    return 'No active scenarios';
                }

                return items.map(function (item) {
                    return item.label;
                }).join(' | ');
            }

            function renderScenarioSummary(state) {
                if (!scenarioSummaryNode) {
                    return;
                }

                var items = buildScenarioSummaryItems(state);
                if (!items.length) {
                    scenarioSummaryNode.classList.add('is-empty');
                    scenarioSummaryNode.innerHTML = 'No active scenarios';
                    return;
                }

                scenarioSummaryNode.classList.remove('is-empty');
                scenarioSummaryNode.innerHTML = items.map(function (item) {
                    var toneClass = item.tone === 'danger'
                        ? ' is-danger'
                        : (item.tone === 'warning' ? ' is-warning' : '');
                    return '<span class="cbt-dev-scenario-chip' + toneClass + '">' + escapeHtml(item.label) + '</span>';
                }).join('');
            }

            function refreshScenarioFormDirtyState() {
                scenarioFormDirty = serializeScenarioState(readScenarioFormState()) !== serializeScenarioState(readScenarioState());
                renderScenarioSummary(readScenarioFormState());
            }

            function matchesTimelineFilter(entry, filter) {
                var kind = String(entry && entry.kind ? entry.kind : '').toLowerCase();
                if (filter === 'all') {
                    return true;
                }
                if (filter === 'auth') {
                    return kind.indexOf('login:') === 0 || kind.indexOf('exams:') === 0 || kind.indexOf('bootstrap') === 0;
                }
                if (filter === 'attempt') {
                    return kind.indexOf('attempt:') === 0 || kind.indexOf('question-window:') === 0 || kind.indexOf('exam:selected') === 0 || kind.indexOf('stage:exam') === 0;
                }
                if (filter === 'sync') {
                    return kind.indexOf('sync:') === 0 || kind.indexOf('answer:') === 0;
                }
                if (filter === 'finish') {
                    return kind.indexOf('finish:') === 0 || kind.indexOf('logout:') === 0;
                }
                if (filter === 'result') {
                    return kind.indexOf('result:') === 0 || kind.indexOf('stage:result') === 0 || kind.indexOf('chunk:result') === 0;
                }
                if (filter === 'security') {
                    return kind.indexOf('security:') === 0 || kind.indexOf('heartbeat:') === 0;
                }
                if (filter === 'error') {
                    return kind.indexOf('fatal:') === 0 || kind.indexOf('runtime:error') === 0 || kind.indexOf('window:') === 0 || kind.indexOf('chunk:') > -1 && kind.indexOf(':error') > -1;
                }
                return true;
            }

            function summarizeStorageArea(area) {
                var keys = [];
                if (!area || typeof area.length !== 'number') {
                    return keys;
                }

                for (var index = 0; index < area.length; index += 1) {
                    var key = typeof area.key === 'function' ? area.key(index) : '';
                    if (typeof key === 'string' && key.indexOf(config.storagePrefix) === 0) {
                        keys.push(key);
                    }
                }

                return keys.sort();
            }

            function buildMetaRows(rows) {
                return rows.map(function (row) {
                    return [
                        '<div class="cbt-dev-diag-meta-row">',
                        '<span>' + escapeHtml(row[0]) + '</span>',
                        '<strong>' + escapeHtml(String(row[1])) + '</strong>',
                        '</div>'
                    ].join('');
                }).join('');
            }

            function truncateText(value, maxLength) {
                var text = String(value == null ? '' : value);
                var limit = Math.max(24, Number(maxLength) || 140);
                if (text.length <= limit) {
                    return text;
                }
                return text.slice(0, limit) + '...';
            }

            function maybeParseJsonText(value) {
                var text = String(value == null ? '' : value);
                if (text === '') {
                    return {
                        raw: '',
                        parsed: '',
                        parseMode: 'empty'
                    };
                }

                try {
                    return {
                        raw: text,
                        parsed: JSON.parse(text),
                        parseMode: 'json'
                    };
                } catch (error) {
                    return {
                        raw: text,
                        parsed: text,
                        parseMode: 'raw'
                    };
                }
            }

            function describeParsedValue(value) {
                if (Array.isArray(value)) {
                    return 'Array(' + String(value.length) + ')';
                }
                if (value && typeof value === 'object') {
                    return 'Object(' + String(Object.keys(value).length) + ')';
                }
                if (value === '' || value === null || value === undefined) {
                    return 'Empty';
                }
                return typeof value === 'string' ? 'String' : String(typeof value || 'unknown');
            }

            function buildStatePayload() {
                return {
                    snapshot: readSnapshot(),
                    syncSnapshot: readSyncSnapshot(),
                    renderStats: readRenderStats(),
                    scenarioState: readScenarioState(),
                    diagnosticsState: readDiagnosticsState(),
                    source: config.frontendAssetSource,
                    exportedAt: new Date().toISOString()
                };
            }

            function renderStateInspector() {
                if (!stateAppNode || !stateExamNode || !stateSyncNode || !stateResultNode || !stateScenarioNode || !stateRenderStatsNode) {
                    return;
                }

                var snapshot = readSnapshot();
                var syncSnapshot = readSyncSnapshot();
                var renderStats = readRenderStats();
                var scenarioState = readScenarioState();
                var emptyMarkup = '<p class="cbt-dev-empty">Belum ada snapshot runtime.</p>';

                if (!snapshot) {
                    stateAppNode.innerHTML = emptyMarkup;
                    stateExamNode.innerHTML = emptyMarkup;
                    stateResultNode.innerHTML = '<p class="cbt-dev-empty">Belum ada snapshot result.</p>';
                } else {
                    stateAppNode.innerHTML = buildMetaRows([
                        ['Stage', snapshot.stage || '-'],
                        ['Busy', snapshot.busy ? 'true' : 'false'],
                        ['Opening Attempt', snapshot.isOpeningAttempt ? 'true' : 'false'],
                        ['Connection', snapshot.connectionStatus || '-'],
                        ['Fullscreen', snapshot.fullscreen ? 'true' : 'false'],
                        ['Selected Exam', snapshot.selectedExamId || 0],
                        ['Attempt', snapshot.attemptId || 0],
                        ['Updated', formatDateTime(snapshot.updatedAt)]
                    ]);

                    stateExamNode.innerHTML = buildMetaRows([
                        ['Current Index', snapshot.currentIndex || 0],
                        ['Question Count', snapshot.questionCount || 0],
                        ['Nav Position', snapshot.navPanelPosition || '-'],
                        ['Nav Visible', snapshot.navPanelVisible ? 'true' : 'false'],
                        ['Question Filter', snapshot.navQuestionFilter || 'all'],
                        ['Calculator Visible', snapshot.calculatorVisible ? 'true' : 'false'],
                        ['Calculator Position', snapshot.calculatorPosition || '-'],
                        ['Last Render', snapshot.lastRenderReason || 'unknown']
                    ]);

                    var resultMeta = snapshot.result && typeof snapshot.result === 'object' ? snapshot.result : null;
                    var reviewSummary = resultMeta && resultMeta.review_summary && typeof resultMeta.review_summary === 'object'
                        ? resultMeta.review_summary
                        : null;
                    stateResultNode.innerHTML = resultMeta
                        ? buildMetaRows([
                            ['Score', Number(resultMeta.score) || 0],
                            ['Max Score', Number(resultMeta.max_score) || 0],
                            ['Passed', Number(resultMeta.is_passed) === 1 ? 'true' : 'false'],
                            ['Tone', resultMeta.result_tone || '-'],
                            ['KKM', Number(resultMeta.kkm_percentage) || 0],
                            ['Passing Score', Number(resultMeta.passing_score) || 0],
                            ['Benar', reviewSummary ? (Number(reviewSummary.correct_questions) || 0) : 0],
                            ['Salah', reviewSummary ? (Number(reviewSummary.wrong_questions) || 0) : 0],
                            ['Menunggu Koreksi', reviewSummary ? (Number(reviewSummary.manual_questions) || 0) : 0]
                        ])
                        : '<p class="cbt-dev-empty">Belum ada snapshot result.</p>';
                }

                if (!syncSnapshot) {
                    stateSyncNode.innerHTML = '<p class="cbt-dev-empty">Belum ada snapshot sync.</p>';
                } else {
                    stateSyncNode.innerHTML = buildMetaRows([
                        ['Pending Sync', syncSnapshot.pendingSyncCount || 0],
                        ['Blocking Reason', syncSnapshot.syncBlockingReason || '-'],
                        ['Retry Count', syncSnapshot.retryCount || 0],
                        ['Flush In Flight', syncSnapshot.flushInFlight ? 'true' : 'false'],
                        ['Pending Batch', syncSnapshot.hasPendingBatchItems ? 'true' : 'false'],
                        ['Last Sync Error', syncSnapshot.lastSyncError || '-'],
                        ['Next Retry', syncSnapshot.nextRetryDueAt ? formatDateTime(syncSnapshot.nextRetryDueAt) : '-'],
                        ['Updated', formatDateTime(syncSnapshot.lastUpdatedAt || syncSnapshot.updatedAt)]
                    ]);
                }

                stateScenarioNode.innerHTML = buildMetaRows([
                    ['Force Offline', scenarioState.forceOffline ? 'true' : 'false'],
                    ['API Latency', String(Number(scenarioState.apiLatencyMs) || 0) + ' ms'],
                    ['Fail Next API', scenarioState.failNextApiRequest && scenarioState.failNextApiRequest.enabled ? String(scenarioState.failNextApiRequest.target || 'any') : 'off'],
                    ['Fail Next Chunk', scenarioState.failNextChunkLoad && scenarioState.failNextChunkLoad.enabled ? String(scenarioState.failNextChunkLoad.target || 'exam') : 'off'],
                    ['Question Window Latency', String(Number(scenarioState.questionWindowLatencyMs) || 0) + ' ms'],
                    ['Fail Next Q-Window', scenarioState.failNextQuestionWindow && scenarioState.failNextQuestionWindow.enabled ? String(scenarioState.failNextQuestionWindow.target || 'any') : 'off'],
                    ['Force Pending Sync', scenarioState.forcePendingSync ? 'true' : 'false'],
                    ['Fail Finish Once', scenarioState.failFinishOnce && scenarioState.failFinishOnce.enabled ? String(scenarioState.failFinishOnce.mode || 'network') : 'off'],
                    ['Heartbeat Scenario', scenarioState.heartbeatScenario || 'off'],
                    ['Disable Auto Retry', scenarioState.disableAutoRetry ? 'true' : 'false'],
                    ['Updated', formatDateTime(scenarioState.updatedAt)]
                ]);

                if (!renderStats) {
                    stateRenderStatsNode.innerHTML = '<p class="cbt-dev-empty">Belum ada render stats.</p>';
                } else {
                    var perStage = renderStats.perStage && typeof renderStats.perStage === 'object' ? renderStats.perStage : {};
                    var perStageSummary = Object.keys(perStage).map(function (key) {
                        return key + ':' + String(Number(perStage[key]) || 0);
                    }).join(', ');
                    stateRenderStatsNode.innerHTML = buildMetaRows([
                        ['Total Scheduled', Number(renderStats.totalScheduled) || 0],
                        ['Total Performed', Number(renderStats.totalPerformed) || 0],
                        ['Burst Last 2s', Number(renderStats.burstLast2s) || 0],
                        ['Last Render Reason', renderStats.lastRenderReason || 'unknown'],
                        ['Last Render Time', formatDateTime(renderStats.lastRenderTime || renderStats.updatedAt)],
                        ['Per Stage', perStageSummary || '-']
                    ]);
                }
            }

            function listRelevantStorageKeys(areaName) {
                if (areaName === 'local') {
                    return summarizeStorageArea(window.localStorage);
                }

                if (areaName === 'session') {
                    var entries = [];
                    var prefixes = [
                        config.attemptUiPrefix,
                        config.doubtfulPrefix,
                        config.questionCacheSessionPrefix
                    ];
                    for (var index = 0; index < window.sessionStorage.length; index += 1) {
                        var key = typeof window.sessionStorage.key === 'function' ? window.sessionStorage.key(index) : '';
                        if (typeof key !== 'string' || key === '') {
                            continue;
                        }
                        if (key === config.authSessionKey) {
                            entries.push(key);
                            continue;
                        }
                        if (prefixes.some(function (prefix) { return typeof prefix === 'string' && prefix !== '' && key.indexOf(prefix) === 0; })) {
                            entries.push(key);
                        }
                    }
                    return entries.sort();
                }

                return [];
            }

            function buildStorageEntry(areaName, key, value, extraMeta) {
                var parsed = maybeParseJsonText(value);
                var previewSource = parsed.parseMode === 'json' ? JSON.stringify(parsed.parsed) : parsed.raw;
                return {
                    area: areaName,
                    key: String(key || ''),
                    value: parsed.parsed,
                    rawValue: parsed.raw,
                    preview: truncateText(previewSource, 160),
                    meta: extraMeta || describeParsedValue(parsed.parsed)
                };
            }

            function readStorageEntries(areaName) {
                if (areaName === 'indexeddb') {
                    return Promise.resolve([]);
                }

                var storage = areaName === 'session' ? window.sessionStorage : window.localStorage;
                var keys = listRelevantStorageKeys(areaName);
                return Promise.resolve(keys.map(function (key) {
                    return buildStorageEntry(areaName, key, storage.getItem(key), '');
                }));
            }

            function loadIndexedDbEntries() {
                return new Promise(function (resolve) {
                    if (!window.indexedDB || !config.indexedDbName) {
                        resolve({
                            status: 'IndexedDB tidak tersedia di browser ini.',
                            items: []
                        });
                        return;
                    }

                    var request;
                    try {
                        request = window.indexedDB.open(config.indexedDbName);
                    } catch (error) {
                        resolve({
                            status: 'IndexedDB gagal dibuka: ' + (error && error.message ? error.message : 'unknown error'),
                            items: []
                        });
                        return;
                    }

                    request.onerror = function () {
                        resolve({
                            status: 'IndexedDB gagal dibuka.',
                            items: []
                        });
                    };

                    request.onblocked = function () {
                        resolve({
                            status: 'IndexedDB sedang diblokir tab lain.',
                            items: []
                        });
                    };

                    request.onsuccess = function () {
                        var database = request.result;
                        if (!database || !database.objectStoreNames.contains('attempt_questions')) {
                            try {
                                if (database && typeof database.close === 'function') {
                                    database.close();
                                }
                            } catch (error) {}
                            resolve({
                                status: 'Store attempt_questions belum tersedia.',
                                items: []
                            });
                            return;
                        }

                        try {
                            var transaction = database.transaction('attempt_questions', 'readonly');
                            var store = transaction.objectStore('attempt_questions');
                            var allRequest = typeof store.getAll === 'function' ? store.getAll() : null;
                            if (!allRequest) {
                                try {
                                    if (database && typeof database.close === 'function') {
                                        database.close();
                                    }
                                } catch (error) {}
                                resolve({
                                    status: 'Browser tidak mendukung getAll() pada IndexedDB store.',
                                    items: []
                                });
                                return;
                            }

                            allRequest.onerror = function () {
                                try {
                                    if (database && typeof database.close === 'function') {
                                        database.close();
                                    }
                                } catch (error) {}
                                resolve({
                                    status: 'Record IndexedDB gagal dibaca.',
                                    items: []
                                });
                            };

                            allRequest.onsuccess = function () {
                                var rows = Array.isArray(allRequest.result) ? allRequest.result : [];
                                try {
                                    if (database && typeof database.close === 'function') {
                                        database.close();
                                    }
                                } catch (error) {}
                                resolve({
                                    status: rows.length ? 'Record IndexedDB berhasil dimuat.' : 'Belum ada record IndexedDB.',
                                    items: rows.map(function (entry) {
                                        var key = entry && entry.cache_key ? String(entry.cache_key) : '-';
                                        var previewParts = [];
                                        if (entry && entry.snapshot && entry.snapshot.question_revision && entry.snapshot.question_revision.signature) {
                                            previewParts.push('rev=' + String(entry.snapshot.question_revision.signature));
                                        }
                                        if (entry && entry.snapshot && Array.isArray(entry.snapshot.stored_question_ids)) {
                                            previewParts.push('items=' + String(entry.snapshot.stored_question_ids.length));
                                        }
                                        if (entry && entry.payload) {
                                            previewParts.push('payload');
                                        }
                                        return {
                                            area: 'indexeddb',
                                            key: key,
                                            value: entry,
                                            rawValue: JSON.stringify(entry),
                                            preview: previewParts.length ? previewParts.join(' | ') : truncateText(JSON.stringify(entry), 160),
                                            meta: entry && entry.updated_at ? formatDateTime(entry.updated_at) : 'IndexedDB record'
                                        };
                                    })
                                });
                            };
                        } catch (error) {
                            resolve({
                                status: 'IndexedDB gagal dibaca: ' + (error && error.message ? error.message : 'unknown error'),
                                items: []
                            });
                        }
                    };
                });
            }

            function filterStorageEntries(items) {
                var query = storageSearchNode ? String(storageSearchNode.value || '').trim().toLowerCase() : '';
                return items.filter(function (entry) {
                    if (!entry || String(entry.area || '') !== activeStorageArea) {
                        return false;
                    }

                    if (!query) {
                        return true;
                    }

                    var haystack = [
                        entry.key || '',
                        entry.preview || '',
                        entry.meta || ''
                    ].join(' ').toLowerCase();
                    return haystack.indexOf(query) >= 0;
                });
            }

            function renderStorageExplorer() {
                if (!storageBodyNode) {
                    return;
                }

                if (storageNoteNode) {
                    if (activeStorageArea === 'session') {
                        storageNoteNode.textContent = 'SessionStorage bersifat per-tab. Yang ditampilkan di sini berasal dari tab admin ini dan key yang relevan dengan frontend CBT.';
                    } else if (activeStorageArea === 'indexeddb') {
                        storageNoteNode.textContent = 'IndexedDB difokuskan ke DB cbt_exam_frontend_cache_v2 dan store attempt_questions.';
                    } else {
                        storageNoteNode.textContent = 'Hanya key yang relevan dengan prefix frontend CBT dan diagnostics yang ditampilkan.';
                    }
                }

                var filteredEntries = filterStorageEntries(storageEntriesCache);
                var pageSize = storagePageSizeNode ? Math.max(1, parseInt(String(storagePageSizeNode.value || '10'), 10) || 10) : 10;
                var totalPages = Math.max(1, Math.ceil(filteredEntries.length / pageSize));
                if (storageCurrentPage > totalPages) {
                    storageCurrentPage = totalPages;
                }
                if (storageCurrentPage < 1) {
                    storageCurrentPage = 1;
                }

                var offset = (storageCurrentPage - 1) * pageSize;
                visibleStorageEntriesCache = filteredEntries.slice(offset, offset + pageSize);
                if (activeStorageDetailIndex >= visibleStorageEntriesCache.length) {
                    activeStorageDetailIndex = -1;
                }

                if (!filteredEntries.length) {
                    storageBodyNode.innerHTML = '<tr><td colspan="5" class="cbt-dev-empty">Tidak ada entry storage yang cocok.</td></tr>';
                    if (storagePaginationMetaNode) {
                        storagePaginationMetaNode.textContent = 'Menampilkan 0 dari 0 entry.';
                    }
                    if (storagePrevPageButton) {
                        storagePrevPageButton.disabled = true;
                    }
                    if (storageNextPageButton) {
                        storageNextPageButton.disabled = true;
                    }
                    return;
                }

                storageBodyNode.innerHTML = visibleStorageEntriesCache.map(function (entry, index) {
                    var detailMarkup = activeStorageDetailIndex === index
                        ? [
                            '<tr class="cbt-dev-diag-inline-detail-row">',
                            '<td colspan="5">',
                            '<div class="cbt-dev-diag-inline-detail-wrap">',
                            '<div class="cbt-dev-actions" style="margin-bottom:8px;">',
                            '<span class="cbt-dev-diag-inline-detail-title" style="margin:0;">Storage Detail</span>',
                            '<button type="button" class="button button-small cbt-admin-btn--ghost" data-storage-copy="' + String(index) + '">Copy Value</button>',
                            '</div>',
                            '<pre class="cbt-dev-diag-inline-detail">' + escapeHtml(typeof entry.value === 'string' ? entry.value : JSON.stringify(entry.value, null, 2)) + '</pre>',
                            '</div>',
                            '</td>',
                            '</tr>'
                        ].join('')
                        : '';
                    return [
                        '<tr class="' + (activeStorageDetailIndex === index ? 'is-expanded' : '') + '">',
                        '<td>' + escapeHtml(String(entry.area || '-')) + '</td>',
                        '<td><code>' + escapeHtml(entry.key || '-') + '</code></td>',
                        '<td>' + escapeHtml(entry.preview || '-') + '</td>',
                        '<td>' + escapeHtml(entry.meta || '-') + '</td>',
                        '<td><button type="button" class="button button-small cbt-admin-btn--ghost" data-storage-view="' + String(index) + '">' + (activeStorageDetailIndex === index ? 'Hide' : 'View') + '</button></td>',
                        '</tr>',
                        detailMarkup
                    ].join('');
                }).join('');

                if (storagePaginationMetaNode) {
                    var startNumber = offset + 1;
                    var endNumber = offset + visibleStorageEntriesCache.length;
                    storagePaginationMetaNode.textContent = 'Menampilkan ' + String(startNumber) + '-' + String(endNumber) + ' dari ' + String(filteredEntries.length) + ' entry.';
                }

                if (storagePrevPageButton) {
                    storagePrevPageButton.disabled = storageCurrentPage <= 1;
                }

                if (storageNextPageButton) {
                    storageNextPageButton.disabled = storageCurrentPage >= totalPages;
                }
            }

            function refreshStorageExplorer() {
                if (!storageStatusNode) {
                    return;
                }

                setStatus(storageStatusNode, 'Memuat storage...', 'ok');
                readStorageEntries('local').then(function (localEntries) {
                    return readStorageEntries('session').then(function (sessionEntries) {
                        return loadIndexedDbEntries().then(function (indexedDbResult) {
                            storageEntriesCache = []
                                .concat(localEntries || [])
                                .concat(sessionEntries || [])
                                .concat(indexedDbResult.items || []);
                            renderStorageExplorer();
                            setStatus(storageStatusNode, indexedDbResult.status || 'Storage berhasil dimuat.', 'ok');
                        });
                    });
                }).catch(function (error) {
                    setStatus(storageStatusNode, 'Gagal memuat storage: ' + (error && error.message ? error.message : 'unknown error'), 'error');
                });
            }

            function renderSnapshot() {
                if (!snapshotNode) {
                    return;
                }

                var snapshot = readSnapshot();
                if (!snapshot) {
                    snapshotNode.innerHTML = '<p class="cbt-dev-empty">Belum ada snapshot runtime.</p>';
                    return;
                }

                var rows = [
                    ['Stage', snapshot.stage || '-'],
                    ['Exam', snapshot.selectedExamId || 0],
                    ['Attempt', snapshot.attemptId || 0],
                    ['Connection', snapshot.connectionStatus || '-'],
                    ['Pending Sync', snapshot.pendingSyncCount || 0],
                    ['Busy', snapshot.busy ? 'true' : 'false'],
                    ['Opening Attempt', snapshot.isOpeningAttempt ? 'true' : 'false'],
                    ['Fullscreen', snapshot.fullscreen ? 'true' : 'false'],
                    ['Source', snapshot.frontendAssetSource || config.frontendAssetSource || '-'],
                    ['Updated', formatDateTime(snapshot.updatedAt)]
                ];

                snapshotNode.innerHTML = rows.map(function (row) {
                    return [
                        '<div class="cbt-dev-diag-meta-row">',
                        '<span>' + escapeHtml(row[0]) + '</span>',
                        '<strong>' + escapeHtml(String(row[1])) + '</strong>',
                        '</div>'
                    ].join('');
                }).join('');
            }

            function renderSyncSnapshot() {
                if (!syncNode) {
                    return;
                }

                var snapshot = readSyncSnapshot();
                if (!snapshot) {
                    syncNode.innerHTML = '<p class="cbt-dev-empty">Belum ada snapshot sync.</p>';
                    return;
                }

                var rows = [
                    ['Status', (Number(snapshot.pendingSyncCount) || 0) > 0 ? 'PENDING' : 'IDLE'],
                    ['Connection', snapshot.connectionStatus || '-'],
                    ['Attempt', snapshot.attemptId || 0],
                    ['Pending Batch', snapshot.pendingSyncCount || 0],
                    ['Blocking Reason', snapshot.syncBlockingReason || '-'],
                    ['Retry Count', snapshot.retryCount || 0],
                    ['Next Retry', snapshot.nextRetryDueAt ? formatDateTime(snapshot.nextRetryDueAt) : '-'],
                    ['Flush In Flight', snapshot.flushInFlight ? 'true' : 'false'],
                    ['Has Pending Items', snapshot.hasPendingBatchItems ? 'true' : 'false'],
                    ['Is Finishing', snapshot.isFinishing ? 'true' : 'false'],
                    ['Last Sync Error', snapshot.lastSyncError || '-'],
                    ['Updated', formatDateTime(snapshot.lastUpdatedAt || snapshot.updatedAt)]
                ];

                syncNode.innerHTML = rows.map(function (row) {
                    return [
                        '<div class="cbt-dev-diag-meta-row">',
                        '<span>' + escapeHtml(row[0]) + '</span>',
                        '<strong>' + escapeHtml(String(row[1])) + '</strong>',
                        '</div>'
                    ].join('');
                }).join('');
            }

            function renderErrors() {
                if (!errorsNode) {
                    return;
                }

                var errors = readErrors();
                if (!errors.length) {
                    errorsNode.innerHTML = '<p class="cbt-dev-empty">Belum ada fatal/runtime error.</p>';
                    return;
                }

                errorsNode.innerHTML = errors.map(function (entry) {
                    var payload = entry && entry.payload && typeof entry.payload === 'object' ? entry.payload : {};
                    return [
                        '<div class="cbt-dev-diag-error">',
                        '<strong><span>' + escapeHtml(String(entry.kind || 'runtime')) + '</span><span>' + escapeHtml(formatTime(entry.time)) + '</span></strong>',
                        '<div>' + escapeHtml(payload.message || payload.reason || 'Runtime error') + '</div>',
                        payload.stack ? '<pre>' + escapeHtml(payload.stack) + '</pre>' : '',
                        '</div>'
                    ].join('');
                }).join('');
            }

            function renderScenarioState(force) {
                if (!scenarioForm) {
                    return;
                }

                if (scenarioFormDirty && force !== true) {
                    return;
                }

                var scenarioState = readScenarioState();
                var forceOfflineNode = document.getElementById('cbt-scenario-force-offline');
                var latencyNode = document.getElementById('cbt-scenario-api-latency');
                var questionWindowLatencyNode = document.getElementById('cbt-scenario-question-window-latency');
                var forcePendingSyncNode = document.getElementById('cbt-scenario-force-pending-sync');
                var disableRetryNode = document.getElementById('cbt-scenario-disable-retry');
                var failApiNode = document.getElementById('cbt-scenario-fail-api-target');
                var failChunkNode = document.getElementById('cbt-scenario-fail-chunk-target');
                var failQuestionWindowNode = document.getElementById('cbt-scenario-fail-question-window-target');
                var failFinishNode = document.getElementById('cbt-scenario-fail-finish-mode');
                var heartbeatNode = document.getElementById('cbt-scenario-heartbeat-mode');

                if (forceOfflineNode) {
                    forceOfflineNode.checked = Boolean(scenarioState.forceOffline);
                }
                if (latencyNode) {
                    latencyNode.value = String(Number(scenarioState.apiLatencyMs) || 0);
                }
                if (questionWindowLatencyNode) {
                    questionWindowLatencyNode.value = String(Number(scenarioState.questionWindowLatencyMs) || 0);
                }
                if (forcePendingSyncNode) {
                    forcePendingSyncNode.checked = Boolean(scenarioState.forcePendingSync);
                }
                if (disableRetryNode) {
                    disableRetryNode.checked = Boolean(scenarioState.disableAutoRetry);
                }
                if (failApiNode) {
                    failApiNode.value = scenarioState.failNextApiRequest && scenarioState.failNextApiRequest.enabled
                        ? String(scenarioState.failNextApiRequest.target || 'any')
                        : 'off';
                }
                if (failChunkNode) {
                    failChunkNode.value = scenarioState.failNextChunkLoad && scenarioState.failNextChunkLoad.enabled
                        ? String(scenarioState.failNextChunkLoad.target || 'exam')
                        : 'off';
                }
                if (failQuestionWindowNode) {
                    failQuestionWindowNode.value = scenarioState.failNextQuestionWindow && scenarioState.failNextQuestionWindow.enabled
                        ? String(scenarioState.failNextQuestionWindow.target || 'any')
                        : 'off';
                }
                if (failFinishNode) {
                    failFinishNode.value = scenarioState.failFinishOnce && scenarioState.failFinishOnce.enabled
                        ? String(scenarioState.failFinishOnce.mode || 'network')
                        : 'off';
                }
                if (heartbeatNode) {
                    heartbeatNode.value = String(scenarioState.heartbeatScenario || 'off');
                }

                scenarioFormDirty = false;
                renderScenarioSummary(scenarioState);
            }

            function matchesRestStatusFilter(entry, filter) {
                var status = Number(entry && entry.status) || 0;
                var ok = Boolean(entry && entry.ok);

                if (filter === 'success') {
                    return ok;
                }
                if (filter === 'error') {
                    return !ok && status > 0;
                }
                if (filter === 'network') {
                    return !ok && status <= 0;
                }

                return true;
            }

            function filterRequestLogs(logs) {
                var searchQuery = restSearchNode ? String(restSearchNode.value || '').trim().toLowerCase() : '';
                var methodFilter = restMethodFilterNode ? String(restMethodFilterNode.value || 'all').toUpperCase() : 'ALL';
                var statusFilter = restStatusFilterNode ? String(restStatusFilterNode.value || 'all').toLowerCase() : 'all';

                return logs.filter(function (entry) {
                    var entryMethod = String(entry && entry.method ? entry.method : '-').toUpperCase();
                    if (methodFilter !== 'ALL' && entryMethod !== methodFilter) {
                        return false;
                    }

                    if (!matchesRestStatusFilter(entry, statusFilter)) {
                        return false;
                    }

                    if (searchQuery === '') {
                        return true;
                    }

                    var status = Number(entry && entry.status) || 0;
                    var summary = Boolean(entry && entry.ok)
                        ? 'ok'
                        : (entry && entry.error && entry.error.message ? String(entry.error.message) : 'request error');
                    var haystack = [
                        entry && entry.endpoint ? entry.endpoint : '',
                        entry && entry.url ? entry.url : '',
                        entryMethod,
                        String(status || 0),
                        summary
                    ].join(' ').toLowerCase();

                    return haystack.indexOf(searchQuery) >= 0;
                });
            }

            function renderRequestLogs() {
                if (!restBodyNode) {
                    return;
                }

                requestLogsCache = readRequestLogs();
                var filteredLogs = filterRequestLogs(requestLogsCache);
                var pageSize = restPageSizeNode ? Math.max(1, parseInt(String(restPageSizeNode.value || '10'), 10) || 10) : 10;
                var totalPages = Math.max(1, Math.ceil(filteredLogs.length / pageSize));
                if (restCurrentPage > totalPages) {
                    restCurrentPage = totalPages;
                }
                if (restCurrentPage < 1) {
                    restCurrentPage = 1;
                }

                var offset = (restCurrentPage - 1) * pageSize;
                visibleRequestLogsCache = filteredLogs.slice(offset, offset + pageSize);
                if (activeRequestDetailIndex >= visibleRequestLogsCache.length) {
                    activeRequestDetailIndex = -1;
                }

                if (!filteredLogs.length) {
                    restBodyNode.innerHTML = '<tr><td colspan="7" class="cbt-dev-empty">Belum ada request CBT yang tercatat.</td></tr>';
                    visibleRequestLogsCache = [];
                    activeRequestDetailIndex = -1;
                    if (restPaginationMetaNode) {
                        restPaginationMetaNode.textContent = 'Menampilkan 0 dari ' + String(requestLogsCache.length) + ' request.';
                    }
                    if (restPrevPageButton) {
                        restPrevPageButton.disabled = true;
                    }
                    if (restNextPageButton) {
                        restNextPageButton.disabled = true;
                    }
                    return;
                }

                restBodyNode.innerHTML = visibleRequestLogsCache.map(function (entry, index) {
                    var status = Number(entry.status) || 0;
                    var ok = Boolean(entry.ok);
                    var summary = ok
                        ? 'OK'
                        : (entry.error && entry.error.message ? entry.error.message : 'Request error');
                    var detailMarkup = activeRequestDetailIndex === index
                        ? [
                            '<tr class="cbt-dev-diag-inline-detail-row">',
                            '<td colspan="7">',
                            '<div class="cbt-dev-diag-inline-detail-wrap">',
                            '<span class="cbt-dev-diag-inline-detail-title">Request Detail</span>',
                            '<pre class="cbt-dev-diag-inline-detail">' + escapeHtml(JSON.stringify(entry, null, 2)) + '</pre>',
                            '</div>',
                            '</td>',
                            '</tr>'
                        ].join('')
                        : '';
                    return [
                        '<tr class="' + (activeRequestDetailIndex === index ? 'is-expanded' : '') + '">',
                        '<td>' + escapeHtml(formatTime(entry.time)) + '</td>',
                        '<td><code>' + escapeHtml(entry.endpoint || entry.url || '-') + '</code></td>',
                        '<td>' + escapeHtml(entry.method || '-') + '</td>',
                        '<td><span class="cbt-dev-diag-badge ' + (ok ? 'is-ok' : (status > 0 ? 'is-error' : 'is-muted')) + '">' + escapeHtml(String(status || (ok ? 200 : 0))) + '</span></td>',
                        '<td>' + escapeHtml(String(Number(entry.durationMs) || 0)) + ' ms</td>',
                        '<td>' + escapeHtml(summary) + '</td>',
                        '<td><button type="button" class="button button-small cbt-admin-btn--ghost" data-diagnostics-view="' + String(index) + '">' + (activeRequestDetailIndex === index ? 'Hide' : 'View') + '</button></td>',
                        '</tr>',
                        detailMarkup
                    ].join('');
                }).join('');

                if (restPaginationMetaNode) {
                    var startNumber = offset + 1;
                    var endNumber = offset + visibleRequestLogsCache.length;
                    restPaginationMetaNode.textContent = 'Menampilkan ' + String(startNumber) + '-' + String(endNumber) + ' dari ' + String(filteredLogs.length) + ' request' + (filteredLogs.length !== requestLogsCache.length ? ' (filtered dari ' + String(requestLogsCache.length) + ')' : '') + '.';
                }

                if (restPrevPageButton) {
                    restPrevPageButton.disabled = restCurrentPage <= 1;
                }

                if (restNextPageButton) {
                    restNextPageButton.disabled = restCurrentPage >= totalPages;
                }
            }

            function filterTimelineEntries(items) {
                var query = timelineSearchNode ? String(timelineSearchNode.value || '').trim().toLowerCase() : '';
                return items.filter(function (entry) {
                    if (!matchesTimelineFilter(entry, activeTimelineFilter)) {
                        return false;
                    }

                    if (!query) {
                        return true;
                    }

                    var meta = entry && entry.meta && typeof entry.meta === 'object' ? entry.meta : {};
                    var searchable = [
                        entry && entry.kind ? entry.kind : '',
                        entry && entry.summary ? entry.summary : '',
                        meta.stage || '',
                        meta.attemptId != null ? String(meta.attemptId) : '',
                        meta.selectedExamId != null ? String(meta.selectedExamId) : '',
                        meta.reason || '',
                        meta.eventType || ''
                    ].join(' ').toLowerCase();

                    return searchable.indexOf(query) !== -1;
                });
            }

            function renderTimeline() {
                if (!timelineBodyNode) {
                    return;
                }

                var items = readTimeline();
                timelineLogsCache = filterTimelineEntries(items);

                var pageSize = timelinePageSizeNode ? parseInt(String(timelinePageSizeNode.value || '10'), 10) : 10;
                if (!Number.isFinite(pageSize) || pageSize < 1) {
                    pageSize = 10;
                }

                var totalItems = timelineLogsCache.length;
                var totalPages = totalItems > 0 ? Math.ceil(totalItems / pageSize) : 1;
                if (timelineCurrentPage > totalPages) {
                    timelineCurrentPage = totalPages;
                }
                if (timelineCurrentPage < 1) {
                    timelineCurrentPage = 1;
                }

                var pageOffset = (timelineCurrentPage - 1) * pageSize;
                visibleTimelineLogsCache = timelineLogsCache.slice(pageOffset, pageOffset + pageSize);
                if (activeTimelineDetailIndex >= visibleTimelineLogsCache.length) {
                    activeTimelineDetailIndex = -1;
                }

                if (!timelineLogsCache.length) {
                    timelineBodyNode.innerHTML = '<tr><td colspan="7" class="cbt-dev-empty">Tidak ada event timeline yang cocok.</td></tr>';
                    activeTimelineDetailIndex = -1;
                    if (timelinePaginationMetaNode) {
                        timelinePaginationMetaNode.textContent = 'Menampilkan 0 event';
                    }
                    if (timelinePrevPageButton) {
                        timelinePrevPageButton.disabled = true;
                    }
                    if (timelineNextPageButton) {
                        timelineNextPageButton.disabled = true;
                    }
                    return;
                }

                timelineBodyNode.innerHTML = visibleTimelineLogsCache.map(function (entry, index) {
                    var meta = entry && entry.meta && typeof entry.meta === 'object' ? entry.meta : {};
                    var detailMarkup = activeTimelineDetailIndex === index
                        ? [
                            '<tr class="cbt-dev-diag-inline-detail-row">',
                            '<td colspan="7">',
                            '<div class="cbt-dev-diag-inline-detail-wrap">',
                            '<span class="cbt-dev-diag-inline-detail-title">Timeline Detail</span>',
                            '<pre class="cbt-dev-diag-inline-detail">' + escapeHtml(JSON.stringify(entry, null, 2)) + '</pre>',
                            '</div>',
                            '</td>',
                            '</tr>'
                        ].join('')
                        : '';
                    return [
                        '<tr class="' + (activeTimelineDetailIndex === index ? 'is-expanded' : '') + '">',
                        '<td>' + escapeHtml(formatTime(entry.time)) + '</td>',
                        '<td><code>' + escapeHtml(entry.kind || '-') + '</code></td>',
                        '<td>' + escapeHtml(meta.stage || '-') + '</td>',
                        '<td>' + escapeHtml(String(meta.attemptId || 0)) + '</td>',
                        '<td>' + escapeHtml(String(meta.selectedExamId || 0)) + '</td>',
                        '<td>' + escapeHtml(entry.summary || '-') + '</td>',
                        '<td><button type="button" class="button button-small cbt-admin-btn--ghost" data-timeline-view="' + String(index) + '">' + (activeTimelineDetailIndex === index ? 'Hide' : 'View') + '</button></td>',
                        '</tr>',
                        detailMarkup
                    ].join('');
                }).join('');

                if (timelinePaginationMetaNode) {
                    var startIndex = pageOffset + 1;
                    var endIndex = pageOffset + visibleTimelineLogsCache.length;
                    timelinePaginationMetaNode.textContent = totalItems === items.length
                        ? 'Menampilkan ' + String(startIndex) + '-' + String(endIndex) + ' dari ' + String(totalItems) + ' event'
                        : 'Menampilkan ' + String(startIndex) + '-' + String(endIndex) + ' dari ' + String(totalItems) + ' event (filtered dari ' + String(items.length) + ')';
                }

                if (timelinePrevPageButton) {
                    timelinePrevPageButton.disabled = timelineCurrentPage <= 1;
                }

                if (timelineNextPageButton) {
                    timelineNextPageButton.disabled = timelineCurrentPage >= totalPages;
                }
            }

            function filterActionTrailEntries(items) {
                var query = actionTrailSearchNode ? String(actionTrailSearchNode.value || '').trim().toLowerCase() : '';
                return items.filter(function (entry) {
                    if (!query) {
                        return true;
                    }

                    var meta = entry && entry.meta && typeof entry.meta === 'object' ? entry.meta : {};
                    var searchable = [
                        entry && entry.kind ? entry.kind : '',
                        entry && entry.summary ? entry.summary : '',
                        meta.stage || '',
                        meta.action || '',
                        meta.reason || '',
                        meta.questionId != null ? String(meta.questionId) : '',
                        meta.attemptId != null ? String(meta.attemptId) : '',
                        meta.selectedExamId != null ? String(meta.selectedExamId) : ''
                    ].join(' ').toLowerCase();

                    return searchable.indexOf(query) !== -1;
                });
            }

            function renderActionTrail() {
                if (!actionTrailBodyNode) {
                    return;
                }

                var items = readActionTrail();
                actionTrailLogsCache = filterActionTrailEntries(items);

                var pageSize = actionTrailPageSizeNode ? parseInt(String(actionTrailPageSizeNode.value || '10'), 10) : 10;
                if (!Number.isFinite(pageSize) || pageSize < 1) {
                    pageSize = 10;
                }

                var totalItems = actionTrailLogsCache.length;
                var totalPages = totalItems > 0 ? Math.ceil(totalItems / pageSize) : 1;
                if (actionTrailCurrentPage > totalPages) {
                    actionTrailCurrentPage = totalPages;
                }
                if (actionTrailCurrentPage < 1) {
                    actionTrailCurrentPage = 1;
                }

                var pageOffset = (actionTrailCurrentPage - 1) * pageSize;
                visibleActionTrailLogsCache = actionTrailLogsCache.slice(pageOffset, pageOffset + pageSize);
                if (activeActionTrailDetailIndex >= visibleActionTrailLogsCache.length) {
                    activeActionTrailDetailIndex = -1;
                }

                if (!actionTrailLogsCache.length) {
                    actionTrailBodyNode.innerHTML = '<tr><td colspan="7" class="cbt-dev-empty">Belum ada action trail.</td></tr>';
                    if (actionTrailPaginationMetaNode) {
                        actionTrailPaginationMetaNode.textContent = 'Menampilkan 0 dari 0 action.';
                    }
                    if (actionTrailPrevPageButton) {
                        actionTrailPrevPageButton.disabled = true;
                    }
                    if (actionTrailNextPageButton) {
                        actionTrailNextPageButton.disabled = true;
                    }
                    return;
                }

                actionTrailBodyNode.innerHTML = visibleActionTrailLogsCache.map(function (entry, index) {
                    var meta = entry && entry.meta && typeof entry.meta === 'object' ? entry.meta : {};
                    var detailMarkup = activeActionTrailDetailIndex === index
                        ? [
                            '<tr class="cbt-dev-diag-inline-detail-row">',
                            '<td colspan="7">',
                            '<div class="cbt-dev-diag-inline-detail-wrap">',
                            '<span class="cbt-dev-diag-inline-detail-title">Action Trail Detail</span>',
                            '<pre class="cbt-dev-diag-inline-detail">' + escapeHtml(JSON.stringify(entry, null, 2)) + '</pre>',
                            '</div>',
                            '</td>',
                            '</tr>'
                        ].join('')
                        : '';
                    return [
                        '<tr class="' + (activeActionTrailDetailIndex === index ? 'is-expanded' : '') + '">',
                        '<td>' + escapeHtml(formatTime(entry.time)) + '</td>',
                        '<td><code>' + escapeHtml(entry.kind || '-') + '</code></td>',
                        '<td>' + escapeHtml(meta.stage || '-') + '</td>',
                        '<td>' + escapeHtml(String(meta.attemptId || 0)) + '</td>',
                        '<td>' + escapeHtml(String(meta.selectedExamId || 0)) + '</td>',
                        '<td>' + escapeHtml(entry.summary || '-') + '</td>',
                        '<td><button type="button" class="button button-small cbt-admin-btn--ghost" data-action-trail-view="' + String(index) + '">' + (activeActionTrailDetailIndex === index ? 'Hide' : 'View') + '</button></td>',
                        '</tr>',
                        detailMarkup
                    ].join('');
                }).join('');

                if (actionTrailPaginationMetaNode) {
                    var startIndex = pageOffset + 1;
                    var endIndex = pageOffset + visibleActionTrailLogsCache.length;
                    actionTrailPaginationMetaNode.textContent = 'Menampilkan ' + String(startIndex) + '-' + String(endIndex) + ' dari ' + String(totalItems) + ' action.';
                }

                if (actionTrailPrevPageButton) {
                    actionTrailPrevPageButton.disabled = actionTrailCurrentPage <= 1;
                }

                if (actionTrailNextPageButton) {
                    actionTrailNextPageButton.disabled = actionTrailCurrentPage >= totalPages;
                }
            }

            function renderDiagnostics() {
                if (!config.diagnosticsEnabled) {
                    return;
                }

                renderStateInspector();
                renderSnapshot();
                renderSyncSnapshot();
                renderErrors();
                renderScenarioState();
                renderRequestLogs();
                renderTimeline();
                renderActionTrail();
                renderStorageExplorer();
            }

            function exportBugBundle() {
                var bundle = {
                    exportedAt: new Date().toISOString(),
                    source: config.frontendAssetSource,
                    diagnosticsEnabled: Boolean(config.diagnosticsEnabled),
                    diagnosticsState: readDiagnosticsState(),
                    runtimeSnapshot: readSnapshot(),
                    syncSnapshot: readSyncSnapshot(),
                    renderStats: readRenderStats(),
                    requestLogs: readRequestLogs(),
                    timeline: readTimeline(),
                    actionTrail: readActionTrail(),
                    scenarios: readScenarioState(),
                    errors: readErrors(),
                    storageSummary: {
                        localStorageKeys: summarizeStorageArea(window.localStorage),
                        sessionStorageKeys: summarizeStorageArea(window.sessionStorage),
                        indexedDbName: config.indexedDbName
                    }
                };

                exportJsonFile('cbt-bug-report', bundle);
            }

            function exportTimeline() {
                var payload = {
                    exportedAt: new Date().toISOString(),
                    source: config.frontendAssetSource,
                    activeFilter: activeTimelineFilter,
                    timeline: readTimeline()
                };
                exportJsonFile('cbt-timeline', payload);
            }

            function copyStateSnapshot() {
                return copyTextToClipboard(JSON.stringify(buildStatePayload(), null, 2));
            }

            function exportStateSnapshot() {
                exportJsonFile('cbt-state-snapshot', buildStatePayload());
            }

            async function clearFrontendBrowserState() {
                if (!clearAllButton || !clearAllStatus) {
                    return;
                }

                clearAllButton.disabled = true;
                setStatus(clearAllStatus, 'Membersihkan state frontend...', 'ok');

                var removedCount = 0;

                try {
                    removedCount += clearStorageAreaByPrefixes(window.localStorage, [config.storagePrefix]);
                    removedCount += clearStorageAreaByPrefixes(window.sessionStorage, [config.storagePrefix]);
                } catch (error) {
                    setStatus(clearAllStatus, 'Gagal membersihkan storage browser: ' + (error && error.message ? error.message : 'unknown error'), 'error');
                    clearAllButton.disabled = false;
                    return;
                }

                var indexedDbResult = await deleteIndexedDb();
                broadcastCommand('clear-frontend-browser-state');
                renderDiagnostics();
                refreshStorageExplorer();
                setStatus(clearAllStatus, 'State frontend dibersihkan (' + String(removedCount) + ' key). ' + indexedDbResult.message, indexedDbResult.ok ? 'ok' : 'error');
                clearAllButton.disabled = false;
            }

            function runTargetedTool(action) {
                if (!toolsStatusNode) {
                    return;
                }

                try {
                    if (action === 'clear-rest-logs') {
                        removedRestLogs();
                    } else if (action === 'clear-auth-session') {
                        window.sessionStorage.removeItem(config.authSessionKey);
                    } else if (action === 'clear-question-cache') {
                        clearStorageAreaByPrefixes(window.sessionStorage, [config.questionCacheSessionPrefix]);
                        clearStorageAreaByPrefixes(window.localStorage, [config.questionCacheMetaPrefix, config.questionCacheItemPrefix]);
                        deleteIndexedDb();
                    } else if (action === 'clear-attempt-ui-state') {
                        clearStorageAreaByPrefixes(window.sessionStorage, [config.attemptUiPrefix, config.doubtfulPrefix]);
                    } else if (action === 'clear-debug-snapshot') {
                        removeLocalStorageKeys([config.diagnosticsSnapshotKey, config.diagnosticsErrorsKey, config.diagnosticsStateKey]);
                    } else if (action === 'clear-sync-snapshot') {
                        removeLocalStorageKeys([config.diagnosticsSyncKey]);
                    } else if (action === 'clear-timeline') {
                        removeLocalStorageKeys([config.diagnosticsTimelineKey]);
                    } else if (action === 'clear-render-stats') {
                        removeLocalStorageKeys([config.diagnosticsRenderStatsKey]);
                    } else if (action === 'clear-action-trail') {
                        removeLocalStorageKeys([config.diagnosticsActionTrailKey]);
                    }
                } catch (error) {
                    setStatus(toolsStatusNode, 'Gagal menjalankan tool: ' + (error && error.message ? error.message : 'unknown error'), 'error');
                    return;
                }

                broadcastCommand(action);
                renderDiagnostics();
                refreshStorageExplorer();
                setStatus(toolsStatusNode, 'Tool dijalankan: ' + action, 'ok');
            }

            function removedRestLogs() {
                removeLocalStorageKeys([config.diagnosticsRequestsKey]);
            }

            function applyScenarioState() {
                var nextState = readScenarioFormState();
                nextState.updatedAt = new Date().toISOString();

                writeScenarioState(nextState);
                scenarioFormDirty = false;
                appendActionTrailEntry('scenario:apply', 'Scenario toggles diterapkan dari CBT Developer.', {
                    scenarioState: nextState
                });
                broadcastCommand('sync-scenarios');
                renderDiagnostics();
                renderScenarioSummary(nextState);
                setStatus(scenarioStatusNode, 'Scenario toggles diterapkan ke frontend dev tab aktif.', 'ok');
            }

            function resetScenarioStateUi() {
                removeLocalStorageKeys([config.diagnosticsScenarioKey]);
                scenarioFormDirty = false;
                appendActionTrailEntry('scenario:reset', 'Scenario toggles direset dari CBT Developer.', {});
                broadcastCommand('reset-scenarios');
                renderDiagnostics();
                renderScenarioSummary(buildDefaultScenarioState());
                setStatus(scenarioStatusNode, 'Scenario toggles direset.', 'ok');
            }

            function applyScenarioPreset(presetId) {
                var nextState = readScenarioFormState();

                if (presetId === 'offline-answering') {
                    nextState.forceOffline = true;
                    nextState.apiLatencyMs = 0;
                } else if (presetId === 'slow-question-load') {
                    nextState.questionWindowLatencyMs = 1500;
                    nextState.failNextQuestionWindow.enabled = false;
                    nextState.failNextQuestionWindow.target = 'any';
                } else if (presetId === 'finish-failure') {
                    nextState.failFinishOnce.enabled = true;
                    nextState.failFinishOnce.mode = 'server';
                    nextState.forcePendingSync = false;
                } else if (presetId === 'session-trouble') {
                    nextState.heartbeatScenario = 'timeout';
                    nextState.forceOffline = false;
                } else {
                    return;
                }

                var forceOfflineNode = document.getElementById('cbt-scenario-force-offline');
                var latencyNode = document.getElementById('cbt-scenario-api-latency');
                var questionWindowLatencyNode = document.getElementById('cbt-scenario-question-window-latency');
                var forcePendingSyncNode = document.getElementById('cbt-scenario-force-pending-sync');
                var disableRetryNode = document.getElementById('cbt-scenario-disable-retry');
                var failApiNode = document.getElementById('cbt-scenario-fail-api-target');
                var failChunkNode = document.getElementById('cbt-scenario-fail-chunk-target');
                var failQuestionWindowNode = document.getElementById('cbt-scenario-fail-question-window-target');
                var failFinishNode = document.getElementById('cbt-scenario-fail-finish-mode');
                var heartbeatNode = document.getElementById('cbt-scenario-heartbeat-mode');

                if (forceOfflineNode) {
                    forceOfflineNode.checked = Boolean(nextState.forceOffline);
                }
                if (latencyNode) {
                    latencyNode.value = String(Number(nextState.apiLatencyMs) || 0);
                }
                if (questionWindowLatencyNode) {
                    questionWindowLatencyNode.value = String(Number(nextState.questionWindowLatencyMs) || 0);
                }
                if (forcePendingSyncNode) {
                    forcePendingSyncNode.checked = Boolean(nextState.forcePendingSync);
                }
                if (disableRetryNode) {
                    disableRetryNode.checked = Boolean(nextState.disableAutoRetry);
                }
                if (failApiNode) {
                    failApiNode.value = nextState.failNextApiRequest && nextState.failNextApiRequest.enabled
                        ? String(nextState.failNextApiRequest.target || 'any')
                        : 'off';
                }
                if (failChunkNode) {
                    failChunkNode.value = nextState.failNextChunkLoad && nextState.failNextChunkLoad.enabled
                        ? String(nextState.failNextChunkLoad.target || 'exam')
                        : 'off';
                }
                if (failQuestionWindowNode) {
                    failQuestionWindowNode.value = nextState.failNextQuestionWindow && nextState.failNextQuestionWindow.enabled
                        ? String(nextState.failNextQuestionWindow.target || 'any')
                        : 'off';
                }
                if (failFinishNode) {
                    failFinishNode.value = nextState.failFinishOnce && nextState.failFinishOnce.enabled
                        ? String(nextState.failFinishOnce.mode || 'network')
                        : 'off';
                }
                if (heartbeatNode) {
                    heartbeatNode.value = String(nextState.heartbeatScenario || 'off');
                }

                scenarioFormDirty = serializeScenarioState(nextState) !== serializeScenarioState(readScenarioState());
                renderScenarioSummary(nextState);
                setStatus(scenarioStatusNode, 'Preset diterapkan ke form. Klik Apply untuk mengirim ke frontend aktif.', 'ok');
            }

            function copyScenarioSummary() {
                var summary = buildScenarioSummaryText(readScenarioFormState());
                copyTextToClipboard(summary).then(function () {
                    setStatus(scenarioStatusNode, 'Scenario summary berhasil disalin.', 'ok');
                }).catch(function () {
                    setStatus(scenarioStatusNode, 'Gagal menyalin scenario summary.', 'error');
                });
            }

            if (tabButtons.length) {
                tabButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        activateTab(String(button.getAttribute('data-dev-tab') || 'overview'));
                    });
                });
                activateTab(readStoredTab());
            }

            if (clearAllButton) {
                clearAllButton.addEventListener('click', function () {
                    clearFrontendBrowserState();
                });
            }

            if (scenarioForm) {
                scenarioForm.addEventListener('input', refreshScenarioFormDirtyState);
                scenarioForm.addEventListener('change', refreshScenarioFormDirtyState);
            }

            if (scenarioPresetBar) {
                scenarioPresetBar.addEventListener('click', function (event) {
                    var target = event.target;
                    if (!(target instanceof Element)) {
                        return;
                    }

                    var presetId = target.getAttribute('data-scenario-preset');
                    if (!presetId) {
                        return;
                    }

                    applyScenarioPreset(String(presetId));
                });
            }

            if (config.diagnosticsEnabled) {
                if (exportButton) {
                    exportButton.addEventListener('click', exportBugBundle);
                }

                if (refreshButton) {
                    refreshButton.addEventListener('click', renderDiagnostics);
                }

                if (stateRefreshButton) {
                    stateRefreshButton.addEventListener('click', renderStateInspector);
                }

                if (stateCopyJsonButton) {
                    stateCopyJsonButton.addEventListener('click', function () {
                        copyStateSnapshot()
                            .then(function () {
                                setStatus(stateStatusNode, 'Snapshot state berhasil disalin.', 'ok');
                            })
                            .catch(function (error) {
                                setStatus(stateStatusNode, 'Gagal menyalin snapshot: ' + (error && error.message ? error.message : 'unknown error'), 'error');
                            });
                    });
                }

                if (stateExportJsonButton) {
                    stateExportJsonButton.addEventListener('click', function () {
                        exportStateSnapshot();
                        setStatus(stateStatusNode, 'Snapshot state diexport.', 'ok');
                    });
                }

                if (restSearchNode) {
                    restSearchNode.addEventListener('input', function () {
                        restCurrentPage = 1;
                        activeRequestDetailIndex = -1;
                        renderRequestLogs();
                    });
                }

                if (restMethodFilterNode) {
                    restMethodFilterNode.addEventListener('change', function () {
                        restCurrentPage = 1;
                        activeRequestDetailIndex = -1;
                        renderRequestLogs();
                    });
                }

                if (restStatusFilterNode) {
                    restStatusFilterNode.addEventListener('change', function () {
                        restCurrentPage = 1;
                        activeRequestDetailIndex = -1;
                        renderRequestLogs();
                    });
                }

                if (restPageSizeNode) {
                    restPageSizeNode.addEventListener('change', function () {
                        restCurrentPage = 1;
                        activeRequestDetailIndex = -1;
                        renderRequestLogs();
                    });
                }

                if (restPrevPageButton) {
                    restPrevPageButton.addEventListener('click', function () {
                        restCurrentPage = Math.max(1, restCurrentPage - 1);
                        activeRequestDetailIndex = -1;
                        renderRequestLogs();
                    });
                }

                if (restNextPageButton) {
                    restNextPageButton.addEventListener('click', function () {
                        restCurrentPage += 1;
                        activeRequestDetailIndex = -1;
                        renderRequestLogs();
                    });
                }

                if (refreshSyncButton) {
                    refreshSyncButton.addEventListener('click', renderDiagnostics);
                }

                if (refreshTimelineButton) {
                    refreshTimelineButton.addEventListener('click', renderDiagnostics);
                }

                if (exportTimelineButton) {
                    exportTimelineButton.addEventListener('click', exportTimeline);
                }

                if (timelineSearchNode) {
                    timelineSearchNode.addEventListener('input', function () {
                        timelineCurrentPage = 1;
                        activeTimelineDetailIndex = -1;
                        renderTimeline();
                    });
                }

                if (timelinePageSizeNode) {
                    timelinePageSizeNode.addEventListener('change', function () {
                        timelineCurrentPage = 1;
                        activeTimelineDetailIndex = -1;
                        renderTimeline();
                    });
                }

                if (timelinePrevPageButton) {
                    timelinePrevPageButton.addEventListener('click', function () {
                        timelineCurrentPage = Math.max(1, timelineCurrentPage - 1);
                        activeTimelineDetailIndex = -1;
                        renderTimeline();
                    });
                }

                if (timelineNextPageButton) {
                    timelineNextPageButton.addEventListener('click', function () {
                        timelineCurrentPage += 1;
                        activeTimelineDetailIndex = -1;
                        renderTimeline();
                    });
                }

                if (clearRestLogsButton) {
                    clearRestLogsButton.addEventListener('click', function () {
                        runTargetedTool('clear-rest-logs');
                    });
                }

                if (clearAuthSessionButton) {
                    clearAuthSessionButton.addEventListener('click', function () {
                        runTargetedTool('clear-auth-session');
                    });
                }

                if (clearQuestionCacheButton) {
                    clearQuestionCacheButton.addEventListener('click', function () {
                        runTargetedTool('clear-question-cache');
                    });
                }

                if (clearAttemptUiStateButton) {
                    clearAttemptUiStateButton.addEventListener('click', function () {
                        runTargetedTool('clear-attempt-ui-state');
                    });
                }

                if (clearDebugSnapshotButton) {
                    clearDebugSnapshotButton.addEventListener('click', function () {
                        runTargetedTool('clear-debug-snapshot');
                    });
                }

                if (clearSyncSnapshotButton) {
                    clearSyncSnapshotButton.addEventListener('click', function () {
                        runTargetedTool('clear-sync-snapshot');
                    });
                }

                if (clearTimelineButton) {
                    clearTimelineButton.addEventListener('click', function () {
                        runTargetedTool('clear-timeline');
                    });
                }

                if (clearRenderStatsButton) {
                    clearRenderStatsButton.addEventListener('click', function () {
                        runTargetedTool('clear-render-stats');
                        renderStateInspector();
                        setStatus(stateStatusNode, 'Render stats dibersihkan.', 'ok');
                    });
                }

                if (clearActionTrailButton) {
                    clearActionTrailButton.addEventListener('click', function () {
                        runTargetedTool('clear-action-trail');
                        renderActionTrail();
                    });
                }

                if (scenarioApplyButton) {
                    scenarioApplyButton.addEventListener('click', applyScenarioState);
                }

                if (scenarioCopySummaryButton) {
                    scenarioCopySummaryButton.addEventListener('click', copyScenarioSummary);
                }

                if (scenarioResetButton) {
                    scenarioResetButton.addEventListener('click', resetScenarioStateUi);
                }

                if (storageSearchNode) {
                    storageSearchNode.addEventListener('input', function () {
                        storageCurrentPage = 1;
                        activeStorageDetailIndex = -1;
                        renderStorageExplorer();
                    });
                }

                if (storagePageSizeNode) {
                    storagePageSizeNode.addEventListener('change', function () {
                        storageCurrentPage = 1;
                        activeStorageDetailIndex = -1;
                        renderStorageExplorer();
                    });
                }

                if (storagePrevPageButton) {
                    storagePrevPageButton.addEventListener('click', function () {
                        storageCurrentPage = Math.max(1, storageCurrentPage - 1);
                        activeStorageDetailIndex = -1;
                        renderStorageExplorer();
                    });
                }

                if (storageNextPageButton) {
                    storageNextPageButton.addEventListener('click', function () {
                        storageCurrentPage += 1;
                        activeStorageDetailIndex = -1;
                        renderStorageExplorer();
                    });
                }

                if (storageRefreshButton) {
                    storageRefreshButton.addEventListener('click', refreshStorageExplorer);
                }

                if (storageAreaFiltersNode) {
                    storageAreaFiltersNode.addEventListener('click', function (event) {
                        var target = event.target instanceof Element ? event.target : null;
                        if (!target) {
                            return;
                        }

                        var trigger = target.closest('[data-storage-area]');
                        if (!(trigger instanceof Element)) {
                            return;
                        }

                        activeStorageArea = String(trigger.getAttribute('data-storage-area') || 'local');
                        storageCurrentPage = 1;
                        activeStorageDetailIndex = -1;
                        Array.prototype.slice.call(storageAreaFiltersNode.querySelectorAll('[data-storage-area]')).forEach(function (button) {
                            button.classList.toggle('is-active', button === trigger);
                        });
                        renderStorageExplorer();
                    });
                }

                if (actionTrailSearchNode) {
                    actionTrailSearchNode.addEventListener('input', function () {
                        actionTrailCurrentPage = 1;
                        activeActionTrailDetailIndex = -1;
                        renderActionTrail();
                    });
                }

                if (actionTrailPageSizeNode) {
                    actionTrailPageSizeNode.addEventListener('change', function () {
                        actionTrailCurrentPage = 1;
                        activeActionTrailDetailIndex = -1;
                        renderActionTrail();
                    });
                }

                if (actionTrailPrevPageButton) {
                    actionTrailPrevPageButton.addEventListener('click', function () {
                        actionTrailCurrentPage = Math.max(1, actionTrailCurrentPage - 1);
                        activeActionTrailDetailIndex = -1;
                        renderActionTrail();
                    });
                }

                if (actionTrailNextPageButton) {
                    actionTrailNextPageButton.addEventListener('click', function () {
                        actionTrailCurrentPage += 1;
                        activeActionTrailDetailIndex = -1;
                        renderActionTrail();
                    });
                }

                if (actionTrailRefreshButton) {
                    actionTrailRefreshButton.addEventListener('click', renderActionTrail);
                }

                if (restBodyNode) {
                    restBodyNode.addEventListener('click', function (event) {
                        var target = event.target instanceof Element ? event.target : null;
                        if (!target) {
                            return;
                        }

                        var trigger = target.closest('[data-diagnostics-view]');
                        if (!(trigger instanceof Element)) {
                            return;
                        }

                        var index = parseInt(String(trigger.getAttribute('data-diagnostics-view') || ''), 10);
                        activeRequestDetailIndex = activeRequestDetailIndex === index ? -1 : (Number.isFinite(index) ? index : -1);
                        renderRequestLogs();
                    });
                }

                if (timelineBodyNode) {
                    timelineBodyNode.addEventListener('click', function (event) {
                        var target = event.target instanceof Element ? event.target : null;
                        if (!target) {
                            return;
                        }

                        var trigger = target.closest('[data-timeline-view]');
                        if (!(trigger instanceof Element)) {
                            return;
                        }

                        var index = parseInt(String(trigger.getAttribute('data-timeline-view') || ''), 10);
                        activeTimelineDetailIndex = activeTimelineDetailIndex === index ? -1 : (Number.isFinite(index) ? index : -1);
                        renderTimeline();
                    });
                }

                if (actionTrailBodyNode) {
                    actionTrailBodyNode.addEventListener('click', function (event) {
                        var target = event.target instanceof Element ? event.target : null;
                        if (!target) {
                            return;
                        }

                        var trigger = target.closest('[data-action-trail-view]');
                        if (!(trigger instanceof Element)) {
                            return;
                        }

                        var index = parseInt(String(trigger.getAttribute('data-action-trail-view') || ''), 10);
                        activeActionTrailDetailIndex = activeActionTrailDetailIndex === index ? -1 : (Number.isFinite(index) ? index : -1);
                        renderActionTrail();
                    });
                }

                if (storageBodyNode) {
                    storageBodyNode.addEventListener('click', function (event) {
                        var target = event.target instanceof Element ? event.target : null;
                        if (!target) {
                            return;
                        }

                        var copyTrigger = target.closest('[data-storage-copy]');
                        if (copyTrigger instanceof Element) {
                            var copyIndex = parseInt(String(copyTrigger.getAttribute('data-storage-copy') || ''), 10);
                            var copyEntry = visibleStorageEntriesCache[copyIndex];
                            if (copyEntry) {
                                copyTextToClipboard(typeof copyEntry.value === 'string' ? copyEntry.value : JSON.stringify(copyEntry.value, null, 2))
                                    .then(function () {
                                        setStatus(storageStatusNode, 'Value storage berhasil disalin.', 'ok');
                                    })
                                    .catch(function (error) {
                                        setStatus(storageStatusNode, 'Gagal menyalin value: ' + (error && error.message ? error.message : 'unknown error'), 'error');
                                    });
                            }
                            return;
                        }

                        var trigger = target.closest('[data-storage-view]');
                        if (!(trigger instanceof Element)) {
                            return;
                        }

                        var index = parseInt(String(trigger.getAttribute('data-storage-view') || ''), 10);
                        activeStorageDetailIndex = activeStorageDetailIndex === index ? -1 : (Number.isFinite(index) ? index : -1);
                        renderStorageExplorer();
                    });
                }

                var timelineFiltersNode = document.getElementById('cbt-diagnostics-timeline-filters');
                if (timelineFiltersNode) {
                    timelineFiltersNode.addEventListener('click', function (event) {
                        var target = event.target instanceof Element ? event.target : null;
                        if (!target) {
                            return;
                        }

                        var trigger = target.closest('[data-timeline-filter]');
                        if (!(trigger instanceof Element)) {
                            return;
                        }

                        activeTimelineFilter = String(trigger.getAttribute('data-timeline-filter') || 'all');
                        timelineCurrentPage = 1;
                        activeTimelineDetailIndex = -1;
                        Array.prototype.slice.call(timelineFiltersNode.querySelectorAll('[data-timeline-filter]')).forEach(function (button) {
                            button.classList.toggle('is-active', button === trigger);
                        });
                        renderTimeline();
                    });
                }

                window.addEventListener('storage', function (event) {
                    if (!event || typeof event.key !== 'string') {
                        return;
                    }

                    if (
                        event.key === config.diagnosticsRequestsKey
                        || event.key === config.diagnosticsSnapshotKey
                        || event.key === config.diagnosticsSyncKey
                        || event.key === config.diagnosticsTimelineKey
                        || event.key === config.diagnosticsScenarioKey
                        || event.key === config.diagnosticsErrorsKey
                        || event.key === config.diagnosticsStateKey
                        || event.key === config.diagnosticsRenderStatsKey
                        || event.key === config.diagnosticsActionTrailKey
                    ) {
                        renderDiagnostics();
                    }
                });

                renderDiagnostics();
                refreshStorageExplorer();
            }
        })();
    </script>
</div>
