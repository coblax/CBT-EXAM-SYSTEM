<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap cbt-test-hub-page" data-test-hub-root data-test-hub-refresh-url="<?php echo esc_url(CBT_Admin_Test_Hub_Service::test_hub_page_url()); ?>" data-test-hub-async-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-has-active-flow-jobs="<?php echo !empty($has_active_flow_jobs) ? '1' : '0'; ?>" data-has-active-global-unit-run="<?php echo !empty($global_unit_run_active) ? '1' : '0'; ?>">
    <?php
    $global_unit_run_summary = isset($global_unit_run_summary) && is_array($global_unit_run_summary) ? $global_unit_run_summary : [];
    $global_unit_run_result = isset($global_unit_run_result) && is_array($global_unit_run_result) ? $global_unit_run_result : [];
    $global_unit_run_available = !empty($global_unit_run_available);
    $global_unit_run_active = !empty($global_unit_run_active);
    $global_unit_run_token = isset($global_unit_run_token) && is_scalar($global_unit_run_token) ? sanitize_key((string) $global_unit_run_token) : '';
    $global_passed_count = (int) ($global_unit_run_summary['passed_count'] ?? 0);
    $global_failed_count = (int) ($global_unit_run_summary['failed_count'] ?? 0);
    $global_total_count = (int) ($global_unit_run_summary['total_count'] ?? 0);
    $global_executed_at = (int) ($global_unit_run_summary['executed_at'] ?? 0);
    $global_processed_commands = (int) ($global_unit_run_summary['processed_commands'] ?? 0);
    $global_total_commands = (int) ($global_unit_run_summary['total_commands'] ?? 0);
    $global_progress_percent = (int) ($global_unit_run_summary['progress_percent'] ?? 0);
    $global_current_label = (string) ($global_unit_run_summary['current_label'] ?? '');
    $unit_test_inventory = isset($unit_test_inventory) && is_array($unit_test_inventory) ? (array) $unit_test_inventory : [];
    $unit_test_inventory_summary = isset($unit_test_inventory_summary) && is_array($unit_test_inventory_summary) ? (array) $unit_test_inventory_summary : [];
    $unit_test_inventory_total_count = (int) ($unit_test_inventory_summary['total_count'] ?? 0);
    $unit_test_inventory_php_count = (int) ($unit_test_inventory_summary['php_count'] ?? 0);
    $unit_test_inventory_js_count = (int) ($unit_test_inventory_summary['js_count'] ?? 0);
    $unit_test_inventory_curated_count = (int) ($unit_test_inventory_summary['curated_count'] ?? 0);
    $unit_test_inventory_auto_mapped_count = (int) ($unit_test_inventory_summary['auto_mapped_count'] ?? 0);
    $unit_test_inventory_failed_count = (int) ($unit_test_inventory_summary['failed_count'] ?? 0);
    $test_artifact_cleanup = isset($test_artifact_cleanup) && is_array($test_artifact_cleanup) ? $test_artifact_cleanup : [];
    $test_artifact_cleanup_targets = isset($test_artifact_cleanup['targets']) && is_array($test_artifact_cleanup['targets']) ? (array) $test_artifact_cleanup['targets'] : [];
    $test_artifact_existing_count = (int) ($test_artifact_cleanup['existing_count'] ?? 0);
    $test_artifact_has_existing = !empty($test_artifact_cleanup['has_existing']);
    $test_artifact_has_active_jobs = !empty($test_artifact_cleanup['has_active_jobs']);
    $flow_job_repair = isset($flow_job_repair) && is_array($flow_job_repair) ? $flow_job_repair : [];
    $flow_job_repair_active_count = (int) ($flow_job_repair['active_count'] ?? 0);
    $flow_job_repair_terminal_count = (int) ($flow_job_repair['terminal_count'] ?? 0);
    $flow_job_repair_queued_count = (int) ($flow_job_repair['queued_count'] ?? 0);
    $flow_job_repair_running_count = (int) ($flow_job_repair['running_count'] ?? 0);
    $flow_job_repair_cancelling_count = (int) ($flow_job_repair['cancelling_count'] ?? 0);
    $flow_job_repair_has_active = !empty($flow_job_repair['has_active_jobs']);
    $e2e_readiness = isset($e2e_readiness) && is_array($e2e_readiness) ? $e2e_readiness : [];
    $e2e_readiness_checks = isset($e2e_readiness['checks']) && is_array($e2e_readiness['checks']) ? (array) $e2e_readiness['checks'] : [];
    $e2e_readiness_suggestions = isset($e2e_readiness['suggestions']) && is_array($e2e_readiness['suggestions']) ? (array) $e2e_readiness['suggestions'] : [];
    $e2e_readiness_status = sanitize_key((string) ($e2e_readiness['overall_status'] ?? 'unknown'));
    if (!in_array($e2e_readiness_status, ['ready', 'warning', 'blocked', 'unknown'], true)) {
        $e2e_readiness_status = 'unknown';
    }
    $runner_health = isset($runner_health) && is_array($runner_health) ? $runner_health : [];
    $runner_health_checks = isset($runner_health['checks']) && is_array($runner_health['checks']) ? (array) $runner_health['checks'] : [];
    $runner_health_status = sanitize_key((string) ($runner_health['overall_status'] ?? 'unknown'));
    if (!in_array($runner_health_status, ['ready', 'warning', 'blocked', 'unknown'], true)) {
        $runner_health_status = 'unknown';
    }
    $runner_health_status_tones = [
        'ready' => 'done',
        'warning' => 'planned',
        'blocked' => 'danger',
        'unknown' => 'idle',
    ];
    $runner_health_status_labels = [
        'ready' => 'Ready',
        'warning' => 'Warning',
        'blocked' => 'Blocked',
        'unknown' => 'Not Checked',
    ];
    $runner_health_tone = (string) ($runner_health_status_tones[$runner_health_status] ?? 'idle');
    $runner_health_label = (string) ($runner_health_status_labels[$runner_health_status] ?? 'Not Checked');
    $e2e_readiness_tone = (string) ($runner_health_status_tones[$e2e_readiness_status] ?? 'idle');
    $e2e_readiness_label = (string) ($runner_health_status_labels[$e2e_readiness_status] ?? 'Not Checked');
    $e2e_readiness_checked_at = (int) ($e2e_readiness['checked_at'] ?? 0);
    $e2e_readiness_blocked_count = 0;
    $e2e_readiness_warning_count = 0;
    foreach ($e2e_readiness_checks as $e2e_readiness_check) {
        $e2e_readiness_check_status = sanitize_key((string) ($e2e_readiness_check['status'] ?? 'warning'));
        if ($e2e_readiness_check_status === 'blocked') {
            $e2e_readiness_blocked_count += 1;
        } elseif ($e2e_readiness_check_status === 'warning') {
            $e2e_readiness_warning_count += 1;
        }
    }
    $runner_health_checked_at = (int) ($runner_health['checked_at'] ?? 0);
    $runner_health_blocked_count = 0;
    $runner_health_warning_count = 0;
    foreach ($runner_health_checks as $runner_health_check) {
        $runner_health_check_status = sanitize_key((string) ($runner_health_check['status'] ?? 'warning'));
        if ($runner_health_check_status === 'blocked') {
            $runner_health_blocked_count += 1;
        } elseif ($runner_health_check_status === 'warning') {
            $runner_health_warning_count += 1;
        }
    }
    $runner_health_is_blocked = $runner_health_status === 'blocked';
    ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

                
        /* Modern Design System Tokens */
        :root {
            --cbt-primary: #3b82f6;
            --cbt-primary-hover: #2563eb;
            --cbt-primary-light: #eff6ff;
            --cbt-secondary: #0ea5e9;
            --cbt-accent: #8b5cf6;
            
            --cbt-bg-base: #f8fafc;
            --cbt-bg-card: rgba(255, 255, 255, 0.7);
            --cbt-bg-card-hover: rgba(255, 255, 255, 0.9);
            
            --cbt-text-main: #0f172a;
            --cbt-text-muted: #64748b;
            --cbt-text-inverse: #ffffff;
            
            --cbt-border: rgba(226, 232, 240, 0.8);
            --cbt-border-light: rgba(255, 255, 255, 0.5);
            
            --cbt-shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --cbt-shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            --cbt-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
            --cbt-shadow-glow: 0 0 20px rgba(59, 130, 246, 0.15);
            
            --cbt-radius-sm: 12px;
            --cbt-radius-md: 20px;
            --cbt-radius-lg: 32px;
            
            --cbt-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .cbt-test-hub-page {
            max-width: 1280px;
            margin: 20px auto;
            padding: 24px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--cbt-text-main);
            background: radial-gradient(circle at top left, #e0e7ff 0%, #f8fafc 40%, #f0fdf4 100%);
            border-radius: var(--cbt-radius-lg);
            box-sizing: border-box;
        }
        .cbt-test-hub-page * {
            box-sizing: border-box;
        }
        
        .cbt-test-hub-shell::before {
            content: ''; position: absolute; top: -150px; left: -100px; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-test-hub-shell::after {
            content: ''; position: absolute; bottom: -100px; right: -50px; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-test-hub-shell {
            display: grid;
            gap: 18px;
        
            position: relative;
            z-index: 1;
            isolation: isolate;
        }
        
        .cbt-test-hub-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
        }
        .cbt-test-hub-hero {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            
            
            
            color: var(--cbt-text-main);
            
        
            padding: 28px;
            border-radius: var(--cbt-radius-lg);
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.8) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--cbt-border-light);
            box-shadow: var(--cbt-shadow-lg), var(--cbt-shadow-glow);
            position: relative;
            overflow: hidden;
        }
        .cbt-test-hub-hero-copy {
            display: grid;
            gap: 12px;
            flex: 1 1 420px;
            max-width: 560px;
        }
        .cbt-test-hub-hero-actions {
            display: block;
            margin-top: 2px;
        }
        .cbt-test-hub-hero-unit-runbar {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-hero-unit-run-form {
            display: grid;
            gap: 6px;
            align-content: start;
            flex: 0 0 auto;
            min-width: 210px;
        }
        .cbt-test-hub-hero-unit-run-button {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            min-height: 38px !important;
            padding: 0 16px !important;
            border-radius: 999px !important;
            border: 1px solid rgba(191, 219, 254, 0.7) !important;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%) !important;
            color: #1d4ed8 !important;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
            font-size: 12px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }
        .cbt-test-hub-hero-unit-run-button:hover,
        .cbt-test-hub-hero-unit-run-button:focus {
            border-color: rgba(255, 255, 255, 0.88) !important;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important;
            color: #1d4ed8 !important;
            box-shadow: 0 18px 34px rgba(15, 23, 42, 0.24);
            transform: translateY(-1px);
        }
        .cbt-test-hub-hero-unit-run-button:disabled {
            border-color: rgba(255, 255, 255, 0.18) !important;
            background: rgba(255, 255, 255, 0.08) !important;
            color: rgba(255, 255, 255, 0.46) !important;
            box-shadow: none;
            transform: none;
        }
        .cbt-test-hub-hero-unit-run-help {
            max-width: 240px;
            color: var(--cbt-text-muted);
            font-size: 11px;
            line-height: 1.45;
        }
        .cbt-test-hub-global-summary {
            flex: 1 1 260px;
            min-width: min(100%, 260px);
            display: grid;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 16px;
            background: rgba(255,255,255,0.6);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(8px);
        }
        .cbt-test-hub-global-summary-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .cbt-test-hub-global-summary-title {
            display: block;
            color: var(--cbt-text-muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-global-summary-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--cbt-text-main);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-global-summary-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .cbt-test-hub-global-summary-stat {
            padding: 9px 10px;
            border-radius: 12px;
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .cbt-test-hub-global-summary-stat span {
            display: block;
            margin-bottom: 4px;
            color: var(--cbt-text-muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-global-summary-stat strong {
            display: block;
            color: var(--cbt-text-main);
            font-size: 18px;
            line-height: 1;
        }
        .cbt-test-hub-global-summary-foot {
            color: var(--cbt-text-muted);
            font-size: 11px;
            line-height: 1.45;
        }
        .cbt-test-hub-hero-side {
            display: grid;
            gap: 10px;
            flex: 1 1 420px;
            min-width: min(100%, 380px);
        }
        .cbt-test-hub-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .cbt-test-hub-hero h1 {
            margin: 6px 0 0;
            font-size: 38px;
            font-weight: 800;
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.15;
        }
        .cbt-test-hub-hero p {
            margin: 0;
            max-width: 760px;
            color: var(--cbt-text-muted);
            font-size: 13px;
            line-height: 1.6;
        }
        .cbt-test-hub-stats {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(3, minmax(120px, 1fr));
        }
        .cbt-test-hub-stat {
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(8px);
        }
        .cbt-test-hub-stat span {
            display: block;
            margin-bottom: 6px;
            color: var(--cbt-text-muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-stat strong {
            display: block;
            color: var(--cbt-text-main);
            font-size: 17px;
            line-height: 1.2;
        }
        .cbt-test-hub-hero-note {
            margin: 0;
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.88);
            font-size: 12px;
            line-height: 1.65;
        }
        .cbt-test-hub-banner {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-radius: 18px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .cbt-test-hub-banner.is-error {
            border-color: #fecaca;
            background: #fef2f2;
            color: #b91c1c;
        }
        .cbt-test-hub-banner-copy strong {
            display: block;
            margin-bottom: 4px;
            font-size: 15px;
        }
        .cbt-test-hub-banner-copy p {
            margin: 0;
            color: inherit;
        }
        .cbt-test-hub-banner-close {
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }
        .cbt-test-hub-card {
            
            
            
            
            padding: 22px;
        
            border-radius: var(--cbt-radius-md);
            background: linear-gradient(180deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.8) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--cbt-border-light);
            box-shadow: var(--cbt-shadow-md);
            word-wrap: break-word;
            overflow-wrap: break-word;
            min-width: 0;
        }
        .cbt-test-hub-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }
        .cbt-test-hub-card-header h2 {
            margin: 0 0 6px;
            font-size: 22px;
        }
        .cbt-test-hub-card-header p {
            margin: 0;
            color: #64748b;
            line-height: 1.65;
        }
        .cbt-test-hub-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .cbt-test-hub-chip--idle {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .cbt-test-hub-chip--planned {
            background: #fff7ed;
            color: #c2410c;
        }
        .cbt-test-hub-chip--done {
            background: #ecfdf5;
            color: #047857;
        }
        .cbt-test-hub-chip--danger {
            background: #fef2f2;
            color: #b91c1c;
        }
        .cbt-test-hub-note {
            margin: 0 0 18px;
            color: #475569;
            line-height: 1.7;
        }
        .cbt-test-hub-settings {
            display: grid;
            gap: 14px;
            margin: 0 0 20px;
            padding: 18px;
            border: 1px solid #dbe5ef;
            border-radius: 18px;
            background: #f8fbff;
        }
        .cbt-test-hub-settings-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }
        .cbt-test-hub-settings-head h3 {
            margin: 0 0 6px;
            font-size: 16px;
        }
        .cbt-test-hub-settings-head p {
            margin: 0;
            color: #64748b;
            line-height: 1.65;
        }
        .cbt-test-hub-settings-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .cbt-test-hub-settings-field {
            display: grid;
            gap: 8px;
        }
        .cbt-test-hub-settings-field label {
            color: #0f172a;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-settings-field input[type="text"],
        .cbt-test-hub-settings-field input[type="url"] {
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            color: #0f172a;
        }
        .cbt-test-hub-settings-field input[type="text"]:focus,
        .cbt-test-hub-settings-field input[type="url"]:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 1px #2563eb;
            outline: none;
        }
        .cbt-test-hub-settings-help {
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }
        .cbt-test-hub-settings-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-settings-actions-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-settings-actions p {
            margin: 0;
            color: #475569;
            font-size: 12px;
            line-height: 1.6;
        }
        .cbt-test-hub-health-box {
            display: grid;
            gap: 12px;
            margin: 0 0 20px;
            padding: 16px;
            border-radius: 18px;
            border: 1px solid #dbe5ef;
            background: #ffffff;
        }
        .cbt-test-hub-health-head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 14px;
        }
        .cbt-test-hub-health-head h3 {
            margin: 0 0 6px;
            font-size: 16px;
        }
        .cbt-test-hub-health-head p,
        .cbt-test-hub-health-summary {
            margin: 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }
        .cbt-test-hub-health-actions {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-health-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            min-width: 36px;
            height: 36px;
            padding: 0;
            border: 1px solid #c7d2fe;
            border-radius: 50%;
            background: #eef2ff;
            color: #1d4ed8;
            cursor: pointer;
        }
        .cbt-test-hub-health-toggle::before {
            content: "";
            display: inline-block;
            width: 8px;
            height: 8px;
            border: solid currentColor;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) translate(-1px, -1px);
            transition: transform 0.18s ease;
        }
        .cbt-test-hub-health-box:not(.is-collapsed) .cbt-test-hub-health-toggle::before {
            transform: rotate(225deg) translate(-1px, -1px);
        }
        .cbt-test-hub-health-toggle:hover,
        .cbt-test-hub-health-toggle:focus {
            border-color: #93c5fd;
            background: #eff6ff;
            outline: none;
        }
        .cbt-test-hub-health-detail {
            display: grid;
            gap: 12px;
        }
        .cbt-test-hub-health-box.is-collapsed .cbt-test-hub-health-detail {
            display: none;
        }
        [data-cbt-test-hub-collapsible].is-collapsed [data-cbt-test-hub-collapsible-body] {
            display: none;
        }
        .cbt-test-hub-health-list {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .cbt-test-hub-health-item {
            display: grid;
            gap: 5px;
            min-width: 0;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #f8fbff;
        }
        .cbt-test-hub-health-item strong {
            color: #0f172a;
            font-size: 12px;
        }
        .cbt-test-hub-health-item span {
            color: #475569;
            font-size: 11px;
            line-height: 1.45;
        }
        .cbt-test-hub-health-item small {
            color: #64748b;
            font-size: 10px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }
        [data-cbt-test-hub-refresh-area] {
            position: relative;
        }
        [data-cbt-test-hub-refresh-area].is-loading {
            overflow: hidden;
            outline: 1px solid rgba(59, 130, 246, 0.22);
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.08);
        }
        [data-cbt-test-hub-refresh-area].is-loading::before {
            content: "";
            position: absolute;
            z-index: 5;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 0 0 999px 999px;
            background:
                linear-gradient(90deg, rgba(37, 99, 235, 0.18) 0%, rgba(37, 99, 235, 0.18) 24%, rgba(37, 99, 235, 0.95) 48%, rgba(16, 185, 129, 0.95) 62%, rgba(37, 99, 235, 0.18) 88%, rgba(37, 99, 235, 0.18) 100%);
            background-size: 220% 100%;
            animation: cbt-test-hub-progress 1s linear infinite;
            pointer-events: none;
        }
        .cbt-test-hub-loading-status {
            display: none;
            align-items: center;
            gap: 8px 10px;
            width: 100%;
            max-width: none;
            min-height: 42px;
            margin: 0;
            padding: 9px 11px;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            box-sizing: border-box;
            justify-self: stretch;
        }
        [data-cbt-test-hub-refresh-area].is-loading > .cbt-test-hub-loading-status {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
        }
        .cbt-test-hub-loading-spinner,
        .button.is-loading::before {
            width: 13px;
            height: 13px;
            border: 2px solid rgba(37, 99, 235, 0.2);
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: cbt-test-hub-spin 0.75s linear infinite;
            content: "";
            flex: 0 0 auto;
        }
        .cbt-test-hub-loading-message {
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .cbt-test-hub-loading-percent {
            color: #1e40af;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .cbt-test-hub-loading-progress {
            grid-column: 1 / -1;
            display: block;
            width: 100%;
            height: 7px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(147, 197, 253, 0.42);
        }
        .cbt-test-hub-loading-progress span {
            display: block;
            width: var(--cbt-test-hub-progress, 0%);
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #2563eb 0%, #0ea5e9 55%, #10b981 100%);
            transition: width 0.28s ease;
        }
        .cbt-test-hub-loading-step {
            grid-column: 1 / -1;
            color: #475569;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.35;
        }
        .button.is-loading {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 8px;
            pointer-events: none;
            opacity: 0.82;
        }
        .cbt-test-hub-async-feedback {
            margin: 0;
            padding: 9px 11px;
            border: 1px solid #fecaca;
            border-radius: 12px;
            background: #fef2f2;
            color: #991b1b;
            font-size: 12px;
            font-weight: 700;
        }
        @keyframes cbt-test-hub-spin {
            to {
                transform: rotate(360deg);
            }
        }
        @keyframes cbt-test-hub-progress {
            from {
                background-position: 120% 0;
            }
            to {
                background-position: -120% 0;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            [data-cbt-test-hub-refresh-area].is-loading::before {
                animation-duration: 2.5s;
            }
        }
        .cbt-test-hub-flow-progress {
            display: grid;
            gap: 7px;
            margin: 8px 0 0;
            padding: 9px 10px;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 12px;
            font-weight: 700;
        }
        .cbt-test-hub-flow-progress-track {
            display: block;
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(147, 197, 253, 0.48);
        }
        .cbt-test-hub-flow-progress-track span {
            display: block;
            width: var(--cbt-test-hub-flow-progress, 0%);
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #2563eb 0%, #06b6d4 55%, #10b981 100%);
        }
        .cbt-test-hub-readiness-suggestions {
            display: grid;
            gap: 6px;
            margin: 0;
            padding: 10px 12px;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 12px;
            line-height: 1.5;
        }
        .cbt-test-hub-readiness-suggestions strong {
            color: #1e40af;
            font-size: 12px;
        }
        .cbt-test-hub-readiness-suggestions ul {
            margin: 0;
            padding-left: 18px;
        }
        .cbt-test-hub-artifact-box {
            display: grid;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid #dbe5ef;
            background: #fff;
        }
        .cbt-test-hub-artifact-box strong {
            display: block;
            color: #0f172a;
            font-size: 13px;
        }
        .cbt-test-hub-artifact-box p {
            margin: 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }
        .cbt-test-hub-artifact-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .cbt-test-hub-artifact-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid #dbe5ef;
            background: #f8fbff;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.4;
        }
        .cbt-test-hub-artifact-item.is-missing {
            opacity: 0.72;
        }
        .cbt-test-hub-artifact-item-status {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-artifact-item.is-missing .cbt-test-hub-artifact-item-status {
            background: #f8fafc;
            color: #64748b;
        }
        .cbt-test-hub-job-artifacts {
            display: grid;
            gap: 12px;
            margin: 0 0 14px;
            padding: 12px;
            border: 1px solid #dbeafe;
            border-radius: 14px;
            background: #f8fbff;
        }
        .cbt-test-hub-job-artifacts-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-job-artifacts-head strong {
            display: block;
            margin: 0 0 4px;
            color: #0f172a;
            font-size: 13px;
        }
        .cbt-test-hub-job-artifacts-head span {
            color: #64748b;
            font-size: 11px;
            line-height: 1.5;
        }
        .cbt-test-hub-job-artifact-card {
            display: grid;
            gap: 8px;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }
        .cbt-test-hub-job-artifact-card-head,
        .cbt-test-hub-job-artifact-links {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-job-artifact-card-head strong {
            color: #0f172a;
            font-size: 12px;
        }
        .cbt-test-hub-job-artifact-card-head span,
        .cbt-test-hub-job-artifact-links span {
            color: #64748b;
            font-size: 11px;
            overflow-wrap: anywhere;
        }
        .cbt-test-hub-job-artifact-card pre {
            max-height: 280px;
            margin: 0;
            padding: 10px;
            overflow: auto;
            border-radius: 10px;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 11px;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        .cbt-test-hub-job-artifact-links {
            justify-content: flex-start;
        }
        .cbt-test-hub-danger-button {
            min-height: 36px;
            padding-inline: 16px;
            border-radius: 999px !important;
            border-color: #fecaca !important;
            background: #fef2f2 !important;
            color: #b91c1c !important;
            font-weight: 700 !important;
        }
        .cbt-test-hub-danger-button:hover,
        .cbt-test-hub-danger-button:focus {
            border-color: #fca5a5 !important;
            background: #fee2e2 !important;
            color: #991b1b !important;
        }
        .cbt-test-hub-danger-button:disabled {
            border-color: #e2e8f0 !important;
            background: #f8fafc !important;
            color: #94a3b8 !important;
        }
        .cbt-test-hub-subtabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
        }
        .cbt-test-hub-subtab {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid #dbe5ef;
            background: #f8fbff;
            color: #1e293b;
            cursor: pointer;
            font-weight: 600;
        }
        .cbt-test-hub-subtab.is-active {
            border-color: #2563eb;
            background: #e0f2fe;
            color: #0f172a;
        }
        .cbt-test-hub-subtab-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.06);
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-subpanel {
            display: none;
        }
        .cbt-test-hub-subpanel.is-active {
            display: block;
        }
        .cbt-test-hub-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }
        .cbt-test-hub-panel-side {
            display: grid;
            gap: 10px;
            justify-items: end;
        }
        .cbt-test-hub-panel-head h3 {
            margin: 0 0 6px;
            font-size: 20px;
        }
        .cbt-test-hub-panel-head p {
            margin: 0;
            color: #64748b;
            line-height: 1.65;
        }
        .cbt-test-hub-run-form {
            display: grid;
            gap: 8px;
            justify-items: end;
        }
        .cbt-test-hub-run-button {
            min-height: 36px;
            padding-inline: 16px;
            border-radius: 999px !important;
            font-weight: 600 !important;
        }
        .cbt-test-hub-run-help {
            max-width: 320px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
            text-align: right;
        }
        .cbt-test-hub-run-command {
            padding: 14px;
            border-radius: 14px;
            border: 1px solid #dbe5ef;
            background: #fff;
        }
        .cbt-test-hub-run-command-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 8px;
        }
        .cbt-test-hub-run-command strong {
            display: block;
            color: #0f172a;
        }
        .cbt-test-hub-run-command code {
            display: block;
            margin-bottom: 10px;
            color: #1d4ed8;
            line-height: 1.6;
            word-break: break-word;
        }
        .cbt-test-hub-run-command pre {
            margin: 8px 0 0;
            padding: 12px;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 240px;
            border-radius: 12px;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 12px;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .cbt-test-hub-run-command-output-label {
            display: block;
            margin-top: 10px;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-kicker-inline {
            display: inline-flex;
            margin-bottom: 8px;
            color: #2563eb;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .cbt-test-hub-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: 1fr;
        }
        .cbt-test-hub-checklist-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }
        .cbt-test-hub-checklist-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid #dbe5ef;
            background: #f8fbff;
            color: #1e293b;
            cursor: pointer;
            font-weight: 600;
        }
        .cbt-test-hub-checklist-tab.is-active {
            border-color: #2563eb;
            background: #e0f2fe;
            color: #0f172a;
        }
        .cbt-test-hub-checklist-panel {
            display: none;
        }
        .cbt-test-hub-checklist-panel.is-active {
            display: block;
        }
        .cbt-test-hub-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .cbt-test-hub-section-head h4 {
            margin-bottom: 0;
        }
        .cbt-test-hub-section-note {
            margin: 0 0 12px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.65;
        }
        .cbt-test-hub-run-summary {
            display: grid;
            gap: 10px;
            margin: 0 0 14px;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #fecaca;
            background: #fff7f7;
        }
        .cbt-test-hub-run-summary strong {
            display: block;
            color: #991b1b;
            font-size: 13px;
        }
        .cbt-test-hub-run-summary p {
            margin: 0;
            color: #7f1d1d;
            font-size: 13px;
            line-height: 1.65;
        }
        .cbt-test-hub-run-summary ul {
            margin: 0;
            padding-left: 18px;
            color: #7f1d1d;
            line-height: 1.7;
        }
        .cbt-test-hub-run-summary li {
            margin: 0 0 4px;
        }
        .cbt-test-hub-section {
            padding: 18px;
            border-radius: 18px;
            border: 1px solid #dbe5ef;
            background: #f8fbff;
        }
        .cbt-test-hub-section h4 {
            margin: 0 0 10px;
            font-size: 15px;
        }
        .cbt-test-hub-section p {
            margin: 0;
            color: #334155;
            line-height: 1.7;
        }
        .cbt-test-hub-list {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .cbt-test-hub-list li {
            margin: 0;
        }
        .cbt-test-hub-item {
            border: 1px solid #dbe5ef;
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }
        .cbt-test-hub-item-summary {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 16px;
            cursor: pointer;
            list-style: none;
        }
        .cbt-test-hub-item-summary::-webkit-details-marker {
            display: none;
        }
        .cbt-test-hub-item-summary::marker {
            display: none;
        }
        .cbt-test-hub-item-copy {
            display: grid;
            gap: 6px;
            min-width: 0;
        }
        .cbt-test-hub-item-side {
            display: grid;
            gap: 8px;
            justify-items: end;
            flex-shrink: 0;
        }
        .cbt-test-hub-item-side-top {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-item-run-form-summary {
            margin: 0;
        }
        .cbt-test-hub-item-run-trigger {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            min-height: 32px !important;
            padding: 0 14px !important;
            border-radius: 999px !important;
            border: 1px solid #bfdbfe !important;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%) !important;
            color: #1d4ed8 !important;
            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.12);
            font-size: 12px !important;
            font-weight: 700 !important;
            line-height: 1 !important;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }
        .cbt-test-hub-item-run-trigger:hover,
        .cbt-test-hub-item-run-trigger:focus {
            border-color: #93c5fd !important;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important;
            color: #1d4ed8 !important;
            box-shadow: 0 14px 26px rgba(37, 99, 235, 0.18);
            transform: translateY(-1px);
        }
        .cbt-test-hub-item-run-trigger:disabled {
            border-color: #dbe5ef !important;
            background: #f8fafc !important;
            color: #94a3b8 !important;
            box-shadow: none;
            transform: none;
            cursor: not-allowed !important;
        }
        .cbt-test-hub-item-run-trigger--danger {
            border-color: #fecaca !important;
            background: #fef2f2 !important;
            color: #b91c1c !important;
            box-shadow: 0 10px 22px rgba(185, 28, 28, 0.08);
        }
        .cbt-test-hub-item-run-trigger--danger:hover,
        .cbt-test-hub-item-run-trigger--danger:focus {
            border-color: #fca5a5 !important;
            background: #fee2e2 !important;
            color: #991b1b !important;
        }
        .cbt-test-hub-item-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-item-toggle::after {
            content: '▾';
            font-size: 12px;
            transition: transform 0.18s ease;
        }
        .cbt-test-hub-item[open] .cbt-test-hub-item-toggle::after {
            transform: rotate(180deg);
        }
        .cbt-test-hub-item-hint {
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }
        .cbt-test-hub-item-output-preview {
            margin-top: 4px;
            color: #b91c1c;
            font-size: 11px;
            line-height: 1.55;
            font-weight: 600;
        }
        .cbt-test-hub-item-body {
            display: grid;
            gap: 14px;
            padding: 0 16px 16px;
            border-top: 1px solid #e2e8f0;
            background: #fbfdff;
        }
        .cbt-test-hub-item-description {
            margin: 14px 0 0;
            color: #334155;
            line-height: 1.7;
        }
        .cbt-test-hub-item-meta {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .cbt-test-hub-item-meta-block {
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #dbe5ef;
            background: #fff;
        }
        .cbt-test-hub-item-meta-block--wide {
            grid-column: 1 / -1;
        }
        .cbt-test-hub-item-meta-block h5 {
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-item-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 12px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-item-run-inline {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-item-run-inline-help {
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }
        .cbt-test-hub-item-meta-block ul {
            margin: 0;
            padding-left: 18px;
            color: #334155;
            line-height: 1.7;
            list-style: disc;
            list-style-position: outside;
        }
        .cbt-test-hub-item-meta-block li {
            margin: 0 0 6px;
        }
        .cbt-test-hub-item-meta-empty {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }
        .cbt-test-hub-item-run-summary {
            margin: 0 0 12px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #fecaca;
            background: #fff5f5;
            color: #991b1b;
        }
        .cbt-test-hub-item-run-summary strong {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .cbt-test-hub-item-run-summary p {
            margin: 0 0 8px;
            color: #7f1d1d;
            line-height: 1.6;
        }
        .cbt-test-hub-item-run-summary ul {
            margin: 0;
            padding-left: 18px;
        }
        .cbt-test-hub-item-run-summary li {
            margin: 0 0 6px;
            line-height: 1.6;
        }
        .cbt-test-hub-item-run-list {
            display: grid;
            gap: 10px;
        }
        .cbt-test-hub-inventory-detail {
            display: grid;
            gap: 12px;
        }
        .cbt-test-hub-inventory-list {
            display: grid;
            gap: 10px;
        }
        .cbt-test-hub-inventory-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid #dbe5ef;
            background: #f8fbff;
        }
        .cbt-test-hub-inventory-pagination[hidden] {
            display: none;
        }
        .cbt-test-hub-inventory-page-status {
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }
        .cbt-test-hub-inventory-page-actions {
            display: inline-flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cbt-test-hub-item-run-command {
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #dbe5ef;
            background: #f8fbff;
        }
        .cbt-test-hub-item-run-command-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 8px;
        }
        .cbt-test-hub-item-run-command strong {
            display: block;
            color: #0f172a;
        }
        .cbt-test-hub-item-run-command code {
            display: block;
            margin-bottom: 8px;
            color: #1d4ed8;
            line-height: 1.6;
            word-break: break-word;
        }
        .cbt-test-hub-item-run-command pre {
            margin: 8px 0 0;
            padding: 10px 12px;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 240px;
            border-radius: 10px;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 12px;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .cbt-test-hub-list-copy {
            color: #334155;
            line-height: 1.6;
        }
        .cbt-test-hub-meta {
            display: grid;
            gap: 14px;
        }
        .cbt-test-hub-meta-block strong {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
        }
        .cbt-test-hub-meta-block ul {
            margin: 0;
            padding-left: 18px;
            color: #334155;
            line-height: 1.7;
        }
        @media (max-width: 960px) {
            .cbt-test-hub-stats,
            .cbt-test-hub-global-summary-grid,
            .cbt-test-hub-grid,
            .cbt-test-hub-item-meta,
            .cbt-test-hub-health-list,
            .cbt-test-hub-settings-grid {
                grid-template-columns: 1fr;
            }
            .cbt-test-hub-panel-head,
            .cbt-test-hub-card-header,
            .cbt-test-hub-hero,
            .cbt-test-hub-settings-head {
                flex-direction: column;
            }
        }
        @media (max-width: 782px) {
            .cbt-test-hub-page {
                margin-right: 10px;
            }
            .cbt-test-hub-subtab {
                width: 100%;
                justify-content: space-between;
            }
            .cbt-test-hub-item-summary {
                flex-direction: column;
            }
            .cbt-test-hub-item-side {
                width: 100%;
                justify-items: start;
            }
            .cbt-test-hub-item-side-top {
                justify-content: flex-start;
            }
        }
    </style>

    <div class="cbt-test-hub-shell">
        <section class="cbt-test-hub-hero">
            <div class="cbt-test-hub-hero-copy">
                <span class="cbt-test-hub-kicker">QA Engineering</span>
                <h1>CBT Test Hub</h1>
                <div class="cbt-test-hub-hero-actions">
                    <div class="cbt-test-hub-hero-unit-runbar" data-cbt-test-hub-refresh-area="global-unit-run" aria-busy="false">
                        <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Menjalankan semua unit test...</span></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-hero-unit-run-form" data-cbt-test-hub-async-form data-refresh-areas="banners,global-unit-run,unit-inventory,checklist" data-loading-label="Menjalankan...">
                            <input type="hidden" name="action" value="cbt_run_all_unit_tests">
                            <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
                            <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>">
                            <?php if ($global_unit_run_token !== ''): ?>
                                <input type="hidden" name="cbt_global_unit_run_token" value="<?php echo esc_attr($global_unit_run_token); ?>" data-cbt-global-unit-run-token>
                            <?php endif; ?>
                            <?php wp_nonce_field('cbt_run_all_unit_tests'); ?>
                            <button type="submit" class="button cbt-test-hub-hero-unit-run-button" <?php disabled($global_unit_run_active); ?>><?php echo $global_unit_run_active ? 'Run All Sedang Berjalan' : 'Run All Unit Tests'; ?></button>
                            <div class="cbt-test-hub-hero-unit-run-help">
                                <?php if ($global_unit_run_active): ?>
                                    <?php echo esc_html('Memproses bertahap: ' . $global_processed_commands . ' / ' . $global_total_commands . ' command selesai. ' . ($global_current_label !== '' ? 'Sedang: ' . $global_current_label : 'Menyiapkan command berikutnya.')); ?>
                                <?php else: ?>
                                    Menjalankan semua file unit test di Unit Test Inventory secara bertahap agar aman dari timeout Cloudflare. Flow check tidak ikut dijalankan.
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if ($global_unit_run_active || $global_total_commands > 0): ?>
                            <div class="cbt-test-hub-flow-progress" aria-live="polite">
                                <span><?php echo esc_html($global_unit_run_active ? 'Run All Unit Tests sedang berjalan' : 'Run All Unit Tests selesai'); ?></span>
                                <span class="cbt-test-hub-flow-progress-track" aria-hidden="true" style="--cbt-test-hub-flow-progress: <?php echo esc_attr((string) $global_progress_percent); ?>%;"><span></span></span>
                                <small><?php echo esc_html((string) $global_progress_percent . '% . ' . (string) $global_processed_commands . '/' . (string) $global_total_commands . ' command'); ?></small>
                            </div>
                        <?php endif; ?>
                        <div class="cbt-test-hub-global-summary">
                            <div class="cbt-test-hub-global-summary-head">
                                <span class="cbt-test-hub-global-summary-title">Ringkasan Unit Global</span>
                                <span class="cbt-test-hub-global-summary-status">
                                    <?php echo $global_unit_run_active ? 'Running' : ($global_unit_run_available ? esc_html(!empty($global_unit_run_summary['success']) ? 'All Passed' : 'Needs Review') : 'Idle'); ?>
                                </span>
                            </div>
                            <div class="cbt-test-hub-global-summary-grid">
                                <div class="cbt-test-hub-global-summary-stat">
                                    <span>Passed</span>
                                    <strong><?php echo esc_html((string) $global_passed_count); ?></strong>
                                </div>
                                <div class="cbt-test-hub-global-summary-stat">
                                    <span>Failed</span>
                                    <strong><?php echo esc_html((string) $global_failed_count); ?></strong>
                                </div>
                            </div>
                            <div class="cbt-test-hub-global-summary-foot">
                                <?php if ($global_unit_run_active): ?>
                                    Run global sedang berjalan bertahap. Area ini auto-refresh lokal tanpa reload global.
                                <?php elseif ($global_unit_run_available && $global_executed_at > 0): ?>
                                    <?php echo esc_html('Run global terakhir: ' . wp_date('d M Y H:i:s', $global_executed_at) . '. Total test case: ' . $global_total_count . '.'); ?>
                                <?php else: ?>
                                    Belum ada run global. Jalankan `Run All Unit Tests` untuk mengisi ringkasan pass dan gagal.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cbt-test-hub-hero-side">
                <div class="cbt-test-hub-stats">
                    <div class="cbt-test-hub-stat">
                        <span>Area Aktif</span>
                        <strong><?php echo esc_html((string) ($active_unit_test_panel['label'] ?? 'Recovery & Persistence')); ?></strong>
                    </div>
                    <div class="cbt-test-hub-stat">
                        <span>Total Area</span>
                        <strong><?php echo esc_html((string) $unit_test_area_count); ?></strong>
                    </div>
                    <div class="cbt-test-hub-stat">
                        <span>Total Checklist</span>
                        <strong><?php echo esc_html((string) $unit_test_total_checklist_items); ?></strong>
                    </div>
                </div>
                <p class="cbt-test-hub-hero-note">Pusat referensi QA untuk memetakan coverage pengujian, skenario flow check, dan area rawan regress di seluruh subsystem CBT. Halaman ini dipakai untuk menjaga stabilitas runtime, authoring, dan operasional tanpa mencampur dengan aksi maintenance yang bersifat destruktif.</p>
            </div>
        </section>

        <div data-cbt-test-hub-refresh-area="banners">
            <?php if (!empty($notice)): ?>
                <section class="cbt-test-hub-banner" data-test-hub-banner>
                    <div class="cbt-test-hub-banner-copy">
                        <strong>Berhasil</strong>
                        <p><?php echo esc_html((string) $notice); ?></p>
                    </div>
                    <button type="button" class="cbt-test-hub-banner-close" data-test-hub-banner-close aria-label="Tutup notifikasi">x</button>
                </section>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <section class="cbt-test-hub-banner is-error" data-test-hub-banner>
                    <div class="cbt-test-hub-banner-copy">
                        <strong>Perlu Dicek</strong>
                        <p><?php echo esc_html((string) $error); ?></p>
                    </div>
                    <button type="button" class="cbt-test-hub-banner-close" data-test-hub-banner-close aria-label="Tutup notifikasi">x</button>
                </section>
            <?php endif; ?>
        </div>

        <section class="cbt-test-hub-card">
            <div class="cbt-test-hub-card-header">
                <div>
                    <h2>Unit Test Hub</h2>
                    <p>Checklist dibagi per subsystem agar prioritas test, gap coverage, dan next step implementasi bisa dibaca dengan cepat. Runner aktif bertahap per tab, dan tiap item bisa dibuka untuk melihat proses verifikasi, evidence, dan hasil run terbaru bila tersedia.</p>
                </div>
                <span class="cbt-test-hub-chip cbt-test-hub-chip--idle">Checklist Draft</span>
            </div>

            <p class="cbt-test-hub-note">
                Area reset database, bulk seed, dan load test tetap berada di `CBT Maintenance`, sehingga halaman ini bisa fokus penuh pada verifikasi kualitas dan cakupan pengujian.
            </p>

            <div class="cbt-test-hub-health-box is-collapsed" data-cbt-test-hub-refresh-area="runner-health" data-cbt-test-hub-collapsible="runner-health" data-cbt-test-hub-collapsible-default="collapsed" aria-busy="false">
                <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Memeriksa Runner Health...</span></p>
                <div class="cbt-test-hub-health-head">
                    <div>
                        <h3>Runner Health</h3>
                        <p>Refresh manual untuk mengecek kesiapan environment flow-check sebelum menjalankan Playwright dari admin.</p>
                    </div>
                    <button type="button" class="cbt-test-hub-health-toggle" data-cbt-test-hub-collapse-toggle aria-controls="cbt-test-hub-runner-health-detail" aria-expanded="false" aria-label="Tampilkan detail Runner Health" title="Tampilkan detail Runner Health"></button>
                </div>
                <div class="cbt-test-hub-health-actions">
                    <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr($runner_health_tone); ?>"><?php echo esc_html($runner_health_label); ?></span>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-test-hub-async-form data-refresh-areas="banners,runner-health,checklist" data-loading-label="Memeriksa...">
                        <input type="hidden" name="action" value="cbt_refresh_test_hub_health">
                        <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
                        <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>">
                        <?php wp_nonce_field('cbt_refresh_test_hub_health'); ?>
                        <button type="submit" class="button button-secondary">Refresh Runner Health</button>
                    </form>
                </div>
                <div class="cbt-test-hub-health-detail" id="cbt-test-hub-runner-health-detail" data-cbt-test-hub-collapsible-body>
                    <p class="cbt-test-hub-health-summary">
                        <?php if ($runner_health_checked_at > 0): ?>
                            <?php echo esc_html('Terakhir dicek: ' . wp_date('d M Y H:i:s', $runner_health_checked_at)); ?>.
                            <?php echo esc_html((string) $runner_health_blocked_count . ' blocked, ' . (string) $runner_health_warning_count . ' warning.'); ?>
                        <?php else: ?>
                            Belum ada hasil health check. Klik refresh untuk memvalidasi Node, npm, Playwright, folder job, dan URL E2E.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($runner_health_checks)): ?>
                        <div class="cbt-test-hub-health-list">
                            <?php foreach ($runner_health_checks as $runner_health_check): ?>
                                <?php
                                $check_status = sanitize_key((string) ($runner_health_check['status'] ?? 'warning'));
                                $check_tone = (string) ($runner_health_status_tones[$check_status] ?? 'planned');
                                ?>
                                <div class="cbt-test-hub-health-item">
                                    <strong><?php echo esc_html((string) ($runner_health_check['label'] ?? 'Check')); ?></strong>
                                    <span>
                                        <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr($check_tone); ?>"><?php echo esc_html(ucfirst($check_status)); ?></span>
                                        <?php echo esc_html((string) ($runner_health_check['message'] ?? '')); ?>
                                    </span>
                                    <?php if (!empty($runner_health_check['detail'])): ?>
                                        <small><?php echo esc_html((string) $runner_health_check['detail']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cbt-test-hub-health-box is-collapsed" data-cbt-test-hub-refresh-area="e2e-readiness" data-cbt-test-hub-collapsible="e2e-readiness" data-cbt-test-hub-collapsible-default="collapsed" aria-busy="false">
                <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Mengecek E2E Readiness...</span></p>
                <div class="cbt-test-hub-health-head">
                    <div>
                        <h3>E2E Readiness</h3>
                        <p>URL Doctor untuk mengecek WordPress login, halaman CBT, seed user, dan fixture sebelum Playwright E2E dijalankan.</p>
                    </div>
                    <button type="button" class="cbt-test-hub-health-toggle" data-cbt-test-hub-collapse-toggle aria-controls="cbt-test-hub-e2e-readiness-detail" aria-expanded="false" aria-label="Tampilkan detail E2E Readiness" title="Tampilkan detail E2E Readiness"></button>
                </div>
                <div class="cbt-test-hub-health-actions">
                    <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr($e2e_readiness_tone); ?>"><?php echo esc_html($e2e_readiness_label); ?></span>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-test-hub-async-form data-refresh-areas="banners,e2e-readiness" data-loading-label="Mengecek...">
                        <input type="hidden" name="action" value="cbt_check_test_hub_e2e_readiness">
                        <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
                        <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>">
                        <?php wp_nonce_field('cbt_check_test_hub_e2e_readiness'); ?>
                        <button type="submit" class="button button-secondary">Check E2E Readiness</button>
                    </form>
                </div>
                <div class="cbt-test-hub-health-detail" id="cbt-test-hub-e2e-readiness-detail" data-cbt-test-hub-collapsible-body>
                    <p class="cbt-test-hub-health-summary">
                        <?php if ($e2e_readiness_checked_at > 0): ?>
                            <?php echo esc_html('Terakhir dicek: ' . wp_date('d M Y H:i:s', $e2e_readiness_checked_at)); ?>.
                            <?php echo esc_html((string) $e2e_readiness_blocked_count . ' blocked, ' . (string) $e2e_readiness_warning_count . ' warning.'); ?>
                        <?php else: ?>
                            Belum ada hasil readiness. Klik check untuk memvalidasi URL WordPress, frontend CBT, user seed, dan fixture bulk.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($e2e_readiness_checks)): ?>
                        <div class="cbt-test-hub-health-list">
                            <?php foreach ($e2e_readiness_checks as $e2e_readiness_check): ?>
                                <?php
                                $check_status = sanitize_key((string) ($e2e_readiness_check['status'] ?? 'warning'));
                                $check_tone = (string) ($runner_health_status_tones[$check_status] ?? 'planned');
                                ?>
                                <div class="cbt-test-hub-health-item">
                                    <strong><?php echo esc_html((string) ($e2e_readiness_check['label'] ?? 'Check')); ?></strong>
                                    <span>
                                        <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr($check_tone); ?>"><?php echo esc_html(ucfirst($check_status)); ?></span>
                                        <?php echo esc_html((string) ($e2e_readiness_check['message'] ?? '')); ?>
                                    </span>
                                    <?php if (!empty($e2e_readiness_check['url'])): ?>
                                        <small><?php echo esc_html((string) $e2e_readiness_check['url']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($e2e_readiness_check['detail'])): ?>
                                        <small><?php echo esc_html((string) $e2e_readiness_check['detail']); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($e2e_readiness_suggestions)): ?>
                        <div class="cbt-test-hub-readiness-suggestions">
                            <strong>URL Doctor Suggestions</strong>
                            <ul>
                                <?php foreach ($e2e_readiness_suggestions as $e2e_suggestion): ?>
                                    <li><?php echo esc_html((string) $e2e_suggestion); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-settings" data-cbt-test-hub-refresh-area="settings" aria-busy="false" data-cbt-test-hub-async-form data-refresh-areas="banners,settings" data-loading-label="Menyimpan...">
                <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Menyimpan settings...</span></p>
                <input type="hidden" name="action" value="cbt_save_test_hub_settings">
                <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
                <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>" data-global-checklist-scope-input>
                <?php wp_nonce_field('cbt_save_test_hub_settings'); ?>

                <div class="cbt-test-hub-settings-head">
                    <div>
                        <h3>Playwright E2E Settings</h3>
                        <p>Simpan target environment sekali di sini. Runner Playwright akan mengirim root WordPress sebagai `CBT_E2E_BASE_URL`/`CBT_E2E_WP_BASE_URL`, dan bisa memakai `CBT_E2E_FRONTEND_URL` bila halaman CBT bukan homepage.</p>
                    </div>
                    <span class="cbt-test-hub-chip cbt-test-hub-chip--idle">Persistent Option</span>
                </div>

                <div class="cbt-test-hub-settings-grid">
                    <div class="cbt-test-hub-settings-field">
                        <label for="cbt-test-hub-e2e-base-url">E2E Base URL</label>
                        <input
                            id="cbt-test-hub-e2e-base-url"
                            type="url"
                            name="e2e_base_url"
                            value="<?php echo esc_attr((string) ($test_hub_settings['e2e_base_url'] ?? '')); ?>"
                            placeholder="http://localhost/wordpress"
                            spellcheck="false"
                        >
                        <div class="cbt-test-hub-settings-help">Root WordPress untuk admin/login. Contoh: `http://localhost/wordpress`.</div>
                    </div>
                    <div class="cbt-test-hub-settings-field">
                        <label for="cbt-test-hub-e2e-frontend-url">E2E Frontend URL</label>
                        <input
                            id="cbt-test-hub-e2e-frontend-url"
                            type="url"
                            name="e2e_frontend_url"
                            value="<?php echo esc_attr((string) ($test_hub_settings['e2e_frontend_url'] ?? '')); ?>"
                            placeholder="http://localhost/wordpress/ujian"
                            spellcheck="false"
                        >
                        <div class="cbt-test-hub-settings-help">Opsional. Isi bila shortcode/frontend CBT berada di halaman khusus.</div>
                    </div>
                </div>

                <div class="cbt-test-hub-settings-actions">
                    <p>Setelah disimpan, Anda tidak perlu lagi export manual sebelum menjalankan runner Playwright dari `CBT Test Hub`.</p>
                    <div class="cbt-test-hub-settings-actions-group">
                        <button type="submit" class="button button-secondary">Simpan Playwright Settings</button>
                    </div>
                </div>
            </form>

            <div class="cbt-test-hub-artifact-box" data-cbt-test-hub-refresh-area="artifacts" aria-busy="false">
                <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Memperbaiki status job...</span></p>
                <div>
                    <strong>Bersihkan Artefak Test</strong>
                    <p>Membersihkan folder hasil run yang sering membesar seperti `playwright-results`, `test-results`, `coverage`, `.phpunit.cache`, dan artefak debug `output/playwright*`. Tombol ini tidak menyentuh `node_modules` maupun `.playwright-browsers`.</p>
                </div>
                <div class="cbt-test-hub-artifact-list">
                    <?php foreach ($test_artifact_cleanup_targets as $artifact_target): ?>
                        <?php
                        $artifact_exists = !empty($artifact_target['exists']);
                        $artifact_label = (string) ($artifact_target['label'] ?? '');
                        ?>
                        <span class="cbt-test-hub-artifact-item<?php echo $artifact_exists ? '' : ' is-missing'; ?>">
                            <span><?php echo esc_html($artifact_label); ?></span>
                            <span class="cbt-test-hub-artifact-item-status"><?php echo esc_html($artifact_exists ? 'Tersedia' : 'Kosong'); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
	                <div class="cbt-test-hub-settings-actions">
	                    <p>
	                        <?php if ($test_artifact_has_active_jobs): ?>
	                            Cleanup sementara dikunci karena masih ada flow check background yang queued, running, atau cancelling.
	                        <?php elseif ($test_artifact_has_existing): ?>
	                            Saat ini ada <?php echo esc_html((string) $test_artifact_existing_count); ?> target artefak yang bisa dibersihkan dari halaman ini.
	                        <?php else: ?>
	                            Belum ada artefak test yang perlu dibersihkan.
	                        <?php endif; ?>
                            <?php echo esc_html(' Repair status: ' . (string) $flow_job_repair_active_count . ' active (' . (string) $flow_job_repair_queued_count . ' queued, ' . (string) $flow_job_repair_running_count . ' running, ' . (string) $flow_job_repair_cancelling_count . ' cancelling), ' . (string) $flow_job_repair_terminal_count . ' terminal.'); ?>
	                    </p>
	                    <div class="cbt-test-hub-settings-actions-group">
	                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-test-hub-async-form data-refresh-areas="banners,artifacts,checklist" data-loading-label="Repairing...">
	                            <input type="hidden" name="action" value="cbt_repair_stuck_flow_check_jobs">
	                            <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
	                            <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>">
	                            <?php wp_nonce_field('cbt_repair_stuck_flow_check_jobs'); ?>
	                            <button type="submit" class="button button-secondary" <?php disabled(!$flow_job_repair_has_active); ?>>Repair Stuck Jobs</button>
	                        </form>
	                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return window.confirm('Bersihkan artefak test generated dari repo ini sekarang?');" data-cbt-test-hub-async-form data-refresh-areas="banners,artifacts,checklist" data-loading-label="Membersihkan...">
	                            <input type="hidden" name="action" value="cbt_clear_test_artifacts">
	                            <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
                            <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>">
                            <?php wp_nonce_field('cbt_clear_test_artifacts'); ?>
                            <button type="submit" class="button cbt-test-hub-danger-button" <?php disabled($test_artifact_has_active_jobs || !$test_artifact_has_existing); ?>>Bersihkan Artefak Test</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="cbt-test-hub-artifact-box<?php echo $unit_test_inventory_failed_count > 0 ? '' : ' is-collapsed'; ?>" data-cbt-test-hub-refresh-area="unit-inventory" data-cbt-test-hub-collapsible="unit-inventory" data-cbt-test-hub-collapsible-label="Unit Test Inventory" data-cbt-test-hub-collapsible-default="<?php echo $unit_test_inventory_failed_count > 0 ? 'open' : 'collapsed'; ?>" aria-busy="false">
                <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Memperbarui inventory unit test...</span></p>
                <div class="cbt-test-hub-health-head">
                    <div>
                        <strong>Unit Test Inventory</strong>
                        <p>Inventory ini discan otomatis dari <code>tests/php/unit</code> dan <code>tests/js/unit</code>, lalu dipetakan ke area Test Hub. Checklist curated tetap dipakai untuk narasi, sementara inventory ini menjadi daftar lengkap file test yang bisa dijalankan satu per satu.</p>
                    </div>
                    <button type="button" class="cbt-test-hub-health-toggle" data-cbt-test-hub-collapse-toggle aria-controls="cbt-test-hub-unit-inventory-detail" aria-expanded="<?php echo $unit_test_inventory_failed_count > 0 ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr($unit_test_inventory_failed_count > 0 ? 'Sembunyikan detail Unit Test Inventory' : 'Tampilkan detail Unit Test Inventory'); ?>" title="<?php echo esc_attr($unit_test_inventory_failed_count > 0 ? 'Sembunyikan detail Unit Test Inventory' : 'Tampilkan detail Unit Test Inventory'); ?>"></button>
                </div>
                <div class="cbt-test-hub-artifact-list">
                    <span class="cbt-test-hub-artifact-item"><span>Total file</span><span class="cbt-test-hub-artifact-item-status"><?php echo esc_html((string) $unit_test_inventory_total_count); ?></span></span>
                    <span class="cbt-test-hub-artifact-item"><span>PHPUnit</span><span class="cbt-test-hub-artifact-item-status"><?php echo esc_html((string) $unit_test_inventory_php_count); ?></span></span>
                    <span class="cbt-test-hub-artifact-item"><span>Vitest</span><span class="cbt-test-hub-artifact-item-status"><?php echo esc_html((string) $unit_test_inventory_js_count); ?></span></span>
                    <span class="cbt-test-hub-artifact-item"><span>Curated</span><span class="cbt-test-hub-artifact-item-status"><?php echo esc_html((string) $unit_test_inventory_curated_count); ?></span></span>
                    <span class="cbt-test-hub-artifact-item"><span>Auto-mapped</span><span class="cbt-test-hub-artifact-item-status"><?php echo esc_html((string) $unit_test_inventory_auto_mapped_count); ?></span></span>
                    <span class="cbt-test-hub-artifact-item<?php echo $unit_test_inventory_failed_count > 0 ? '' : ' is-missing'; ?>"><span>Failed latest</span><span class="cbt-test-hub-artifact-item-status"><?php echo esc_html((string) $unit_test_inventory_failed_count); ?></span></span>
                </div>
                <div class="cbt-test-hub-inventory-detail" id="cbt-test-hub-unit-inventory-detail" data-cbt-test-hub-collapsible-body aria-hidden="<?php echo $unit_test_inventory_failed_count > 0 ? 'false' : 'true'; ?>">
                    <?php if (empty($unit_test_inventory)): ?>
                        <div class="cbt-test-hub-item-meta-empty">Belum ada file unit test yang ditemukan.</div>
                    <?php else: ?>
                        <div class="cbt-test-hub-inventory-pagination" data-unit-inventory-pagination>
                            <span class="cbt-test-hub-inventory-page-status" data-unit-inventory-page-status>Menyiapkan daftar inventory...</span>
                            <span class="cbt-test-hub-inventory-page-actions">
                                <button type="button" class="button button-secondary" data-unit-inventory-page-prev>Previous</button>
                                <button type="button" class="button button-secondary" data-unit-inventory-page-next>Next</button>
                            </span>
                        </div>
                        <div class="cbt-test-hub-inventory-list" data-unit-inventory-list data-page-size="25">
                        <?php foreach ($unit_test_inventory as $inventory_item): ?>
                            <?php
                            $inventory_id = sanitize_key((string) ($inventory_item['id'] ?? ''));
                            $inventory_run_tab = CBT_Admin_Test_Hub_Service::normalize_unit_test_tab((string) ($inventory_item['run_tab'] ?? ''));
                            $inventory_form_id = 'cbt-test-hub-inventory-run-' . $inventory_id;
                            $inventory_has_failure = !empty($inventory_item['has_failed_run_results']);
                            $inventory_has_results = !empty($inventory_item['run_results']);
                            $inventory_state = $inventory_has_failure ? 'failed' : ($inventory_has_results ? 'result' : 'idle');
                            ?>
                            <details class="cbt-test-hub-item-run-command" data-unit-inventory-item data-unit-inventory-state="<?php echo esc_attr($inventory_state); ?>" <?php echo $inventory_has_failure ? 'open' : ''; ?>>
                                <summary>
                                    <span>
                                        <strong><?php echo esc_html((string) ($inventory_item['basename'] ?? 'Unit Test File')); ?></strong>
                                        <code><?php echo esc_html((string) ($inventory_item['path'] ?? '')); ?></code>
                                    </span>
                                    <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr((string) ($inventory_item['mapping_status_tone'] ?? 'planned')); ?>">
                                        <?php echo esc_html((string) ($inventory_item['mapping_status_label'] ?? 'Auto-mapped')); ?>
                                    </span>
                                    <?php if ($inventory_has_failure): ?>
                                        <span class="cbt-test-hub-chip cbt-test-hub-chip--danger">Latest Failed</span>
                                    <?php elseif ($inventory_has_results): ?>
                                        <span class="cbt-test-hub-chip cbt-test-hub-chip--done">Latest Passed</span>
                                    <?php endif; ?>
                                </summary>
                                <div class="cbt-test-hub-item-meta-grid" style="margin-top:12px;">
                                    <div class="cbt-test-hub-item-meta-block">
                                        <h5>Mapping</h5>
                                        <ul>
                                            <li><?php echo esc_html('Type: ' . (string) ($inventory_item['type_label'] ?? 'Unit')); ?></li>
                                            <li><?php echo esc_html('Area: ' . (string) ($inventory_item['mapped_tab_label'] ?? 'General / Unclassified')); ?></li>
                                            <li><?php echo esc_html('Runner: ' . (string) ($inventory_item['runner_label'] ?? 'Unit Test File')); ?></li>
                                            <?php if (!empty($inventory_item['curated_runner_label'])): ?>
                                                <li><?php echo esc_html('Curated command: ' . (string) $inventory_item['curated_runner_label']); ?></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="cbt-test-hub-item-meta-block cbt-test-hub-item-meta-block--wide">
                                        <h5>Command</h5>
                                        <code><?php echo esc_html((string) ($inventory_item['command'] ?? '')); ?></code>
                                        <form id="<?php echo esc_attr($inventory_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;" data-cbt-test-hub-async-form data-refresh-areas="banners,unit-inventory" data-loading-label="Menjalankan...">
                                            <input type="hidden" name="action" value="cbt_run_unit_test_suite">
                                            <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr($inventory_run_tab); ?>">
                                            <input type="hidden" name="cbt_checklist_scope" value="unit_tests">
                                            <input type="hidden" name="cbt_inventory_test_id" value="<?php echo esc_attr($inventory_id); ?>">
                                            <?php wp_nonce_field('cbt_test_hub_runner_' . $inventory_run_tab); ?>
                                            <button type="submit" class="button button-secondary">Run File</button>
                                        </form>
                                    </div>
                                    <div class="cbt-test-hub-item-meta-block cbt-test-hub-item-meta-block--wide">
                                        <h5>Hasil Runner Terbaru</h5>
                                        <?php if (!empty($inventory_item['failed_run_results'])): ?>
                                            <div class="cbt-test-hub-item-run-summary">
                                                <strong>Ringkasan gagal</strong>
                                                <ul>
                                                    <?php foreach ((array) ($inventory_item['failed_run_results'] ?? []) as $failed_run_command): ?>
                                                        <li>
                                                            <?php
                                                            echo esc_html((string) ($failed_run_command['label'] ?? 'Test Command') . ' (exit ' . (int) ($failed_run_command['exit_code'] ?? 1) . ')');
                                                            if (!empty($failed_run_command['failure_summary'])) {
                                                                echo esc_html(': ' . (string) $failed_run_command['failure_summary']);
                                                            }
                                                            ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($inventory_item['run_results'])): ?>
                                            <div class="cbt-test-hub-item-run-list">
                                                <?php foreach ((array) ($inventory_item['run_results'] ?? []) as $run_command): ?>
                                                    <article class="cbt-test-hub-item-run-command">
                                                        <div class="cbt-test-hub-item-run-command-head">
                                                            <strong><?php echo esc_html((string) ($run_command['label'] ?? 'Test Command')); ?></strong>
                                                            <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo !empty($run_command['success']) ? 'done' : 'danger'; ?>">
                                                                <?php echo !empty($run_command['success']) ? 'Exit 0' : 'Exit ' . esc_html((string) ($run_command['exit_code'] ?? 1)); ?>
                                                            </span>
                                                        </div>
                                                        <code><?php echo esc_html((string) ($run_command['command'] ?? '')); ?></code>
                                                        <?php if (!empty($run_command['stdout'])): ?>
                                                            <span class="cbt-test-hub-run-command-output-label">Stdout</span>
                                                            <pre><?php echo esc_textarea((string) $run_command['stdout']); ?></pre>
                                                        <?php endif; ?>
                                                        <?php if (!empty($run_command['stderr'])): ?>
                                                            <span class="cbt-test-hub-run-command-output-label">Stderr</span>
                                                            <pre><?php echo esc_textarea((string) $run_command['stderr']); ?></pre>
                                                        <?php endif; ?>
                                                    </article>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="cbt-test-hub-item-meta-empty">Belum ada hasil runner untuk file ini. Gunakan tombol <code>Run File</code> atau <code>Run All Unit Tests</code>.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div data-cbt-test-hub-refresh-area="checklist" aria-busy="false">
                <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Memperbarui flow check...</span></p>
                <nav class="cbt-test-hub-subtabs" role="tablist" aria-label="Unit Test subsystems">
                    <?php foreach ((array) $unit_test_tabs as $unit_test_tab_key => $unit_test_tab): ?>
                        <button
                            type="button"
                            class="cbt-test-hub-subtab<?php echo $active_unit_test_tab === $unit_test_tab_key ? ' is-active' : ''; ?>"
                            data-unit-test-tab="<?php echo esc_attr($unit_test_tab_key); ?>"
                            role="tab"
                            aria-selected="<?php echo $active_unit_test_tab === $unit_test_tab_key ? 'true' : 'false'; ?>"
                        >
                            <?php echo esc_html((string) ($unit_test_tab['label'] ?? ucfirst((string) $unit_test_tab_key))); ?>
                            <span class="cbt-test-hub-subtab-badge"><?php echo esc_html((string) ($unit_test_tab['status_label'] ?? 'Draft')); ?></span>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <div class="cbt-test-hub-subpanels">
                <?php foreach ((array) $unit_test_tabs as $unit_test_tab_key => $unit_test_tab): ?>
                    <?php $unit_test_panel_refresh_area = 'panel-' . sanitize_key((string) $unit_test_tab_key); ?>
                    <section
                        class="cbt-test-hub-subpanel<?php echo $active_unit_test_tab === $unit_test_tab_key ? ' is-active' : ''; ?>"
                        data-unit-test-panel="<?php echo esc_attr($unit_test_tab_key); ?>"
                        data-cbt-test-hub-refresh-area="<?php echo esc_attr($unit_test_panel_refresh_area); ?>"
                        aria-busy="false"
                        role="tabpanel"
                    >
                        <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Memperbarui panel...</span></p>
                        <?php
                        $unit_test_runners = isset($unit_test_tab['runners']) && is_array($unit_test_tab['runners']) ? (array) $unit_test_tab['runners'] : [];
                        $active_panel_scope = (string) ($active_checklist_scope ?? 'unit_tests');
                        $active_scope_runner = isset($unit_test_runners[$active_panel_scope]) && is_array($unit_test_runners[$active_panel_scope])
                            ? (array) $unit_test_runners[$active_panel_scope]
                            : null;
                        $panel_flow_run_result = (
                            !empty($unit_test_run_result)
                            && (string) ($unit_test_run_result['tab'] ?? '') === (string) $unit_test_tab_key
                            && (string) ($unit_test_run_result['scope'] ?? 'unit_tests') === 'smoke_tests'
                        )
                            ? (array) $unit_test_run_result
                            : null;
                        $panel_unit_run_result = (
                            !empty($unit_test_run_result)
                            && (string) ($unit_test_run_result['tab'] ?? '') === (string) $unit_test_tab_key
                            && (string) ($unit_test_run_result['scope'] ?? 'unit_tests') === 'unit_tests'
                        )
                            ? (array) $unit_test_run_result
                            : null;
                        if (
                            empty($panel_unit_run_result)
                            && !empty($global_unit_run_result)
                            && isset($global_unit_run_result['tabs'][$unit_test_tab_key])
                            && is_array($global_unit_run_result['tabs'][$unit_test_tab_key])
                        ) {
                            $panel_unit_run_result = array_merge(
                                (array) $global_unit_run_result['tabs'][$unit_test_tab_key],
                                [
                                    'type' => 'global_unit_tests',
                                    'executed_at' => (int) ($global_unit_run_result['executed_at'] ?? 0),
                                    'label' => (string) ($global_unit_run_result['label'] ?? 'Run All Unit Tests'),
                                ]
                            );
                        }
                        $unit_test_items = (array) ($unit_test_tab['unit_tests'] ?? []);
                        $flow_check_items = (array) ($unit_test_tab['smoke_tests'] ?? []);
                        $unit_scope_runner = isset($unit_test_runners['unit_tests']) && is_array($unit_test_runners['unit_tests'])
                            ? (array) $unit_test_runners['unit_tests']
                            : [];
                        $flow_scope_runner = isset($unit_test_runners['smoke_tests']) && is_array($unit_test_runners['smoke_tests'])
                            ? (array) $unit_test_runners['smoke_tests']
                            : [];
                        $unit_runner_available = !empty($unit_scope_runner['available']);
                        $flow_runner_available = !empty($flow_scope_runner['available']);

                        $unit_failed_commands = [];
                        $unit_failed_items = [];
                        $unit_has_runner_output = false;
                        foreach ($unit_test_items as $unit_test_item) {
                            $item_has_failure = false;
                            foreach ((array) ($unit_test_item['run_results'] ?? []) as $run_command) {
                                $unit_has_runner_output = true;
                                if (!empty($run_command['success'])) {
                                    continue;
                                }

                                $command_label = (string) ($run_command['label'] ?? 'Test Command');
                                $exit_code = (int) ($run_command['exit_code'] ?? 1);
                                $unit_failed_commands[$command_label] = $exit_code;
                                $item_has_failure = true;
                            }

                            if ($item_has_failure) {
                                $unit_failed_items[(string) ($unit_test_item['label'] ?? 'Checklist item')] = true;
                            }
                        }

                        $flow_failed_commands = [];
                        $flow_failed_items = [];
                        $flow_has_runner_output = false;
                        $flow_status_counts = [
                            'queued' => 0,
                            'running' => 0,
                            'cancelling' => 0,
                            'passed' => 0,
                            'failed' => 0,
                            'cancelled' => 0,
                        ];
                        foreach ($flow_check_items as $flow_check_item) {
                            $flow_status = isset($flow_check_item['async_status']) ? (string) $flow_check_item['async_status'] : '';
                            if ($flow_status !== '' && isset($flow_status_counts[$flow_status])) {
                                $flow_status_counts[$flow_status] += 1;
                            }
                            $item_has_failure = false;
                            foreach ((array) ($flow_check_item['run_results'] ?? []) as $run_command) {
                                $flow_has_runner_output = true;
                                if (!empty($run_command['success'])) {
                                    continue;
                                }

                                $command_label = (string) ($run_command['label'] ?? 'Test Command');
                                $exit_code = (int) ($run_command['exit_code'] ?? 1);
                                $flow_failed_commands[$command_label] = $exit_code;
                                $item_has_failure = true;
                            }

                            if ($item_has_failure) {
                                $flow_failed_items[(string) ($flow_check_item['label'] ?? 'Checklist item')] = true;
                            }
                        }
                        $flow_has_active_jobs = ((int) $flow_status_counts['queued'] + (int) $flow_status_counts['running'] + (int) $flow_status_counts['cancelling']) > 0;
                        $flow_status_total = array_sum($flow_status_counts);
                        $flow_status_terminal = (int) $flow_status_counts['passed'] + (int) $flow_status_counts['failed'] + (int) $flow_status_counts['cancelled'];
                        $flow_status_progress_percent = $flow_status_total > 0
                            ? (int) floor(((float) $flow_status_terminal / (float) $flow_status_total) * 100)
                            : 0;
                        $flow_status_progress_percent = max(0, min(100, $flow_status_progress_percent));
                        ?>
                        <div class="cbt-test-hub-panel-head">
                            <div>
                                <span class="cbt-test-hub-kicker-inline">Subsystem</span>
                                <h3><?php echo esc_html((string) ($unit_test_tab['label'] ?? ucfirst((string) $unit_test_tab_key))); ?></h3>
                                <p><?php echo esc_html((string) ($unit_test_tab['summary'] ?? '')); ?></p>
                            </div>
                            <div class="cbt-test-hub-panel-side">
                                <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr((string) ($unit_test_tab['status_tone'] ?? 'idle')); ?>">
                                    <?php echo esc_html((string) ($unit_test_tab['status_label'] ?? 'Draft')); ?>
                                </span>
                                <?php if ($active_scope_runner || !empty($unit_test_runners)): ?>
                                    <form
                                        method="post"
                                        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                        class="cbt-test-hub-run-form"
                                        data-cbt-test-hub-async-form
                                        data-refresh-areas="<?php echo esc_attr(($active_panel_scope === 'smoke_tests' ? 'banners,artifacts,' : 'banners,') . $unit_test_panel_refresh_area); ?>"
                                        data-loading-label="<?php echo esc_attr($active_panel_scope === 'smoke_tests' ? 'Queueing...' : 'Menjalankan...'); ?>"
                                        data-runner-form
                                        data-runner-label-unit="<?php echo esc_attr((string) (($unit_test_runners['unit_tests']['label'] ?? 'Run Checklist Unit'))); ?>"
                                        data-runner-description-unit="<?php echo esc_attr((string) (($unit_test_runners['unit_tests']['description'] ?? ''))); ?>"
                                        data-runner-available-unit="<?php echo !empty($unit_test_runners['unit_tests']['available']) ? '1' : '0'; ?>"
                                        data-runner-reason-unit="<?php echo esc_attr((string) (($unit_test_runners['unit_tests']['reason'] ?? ''))); ?>"
                                        data-runner-label-smoke="<?php echo esc_attr((string) (($unit_test_runners['smoke_tests']['label'] ?? 'Run Checklist Flow Check'))); ?>"
                                        data-runner-description-smoke="<?php echo esc_attr((string) (($unit_test_runners['smoke_tests']['description'] ?? ''))); ?>"
                                        data-runner-available-smoke="<?php echo (!empty($unit_test_runners['smoke_tests']['available']) && !$runner_health_is_blocked) ? '1' : '0'; ?>"
                                        data-runner-reason-smoke="<?php echo esc_attr($runner_health_is_blocked ? 'Runner Health berstatus blocked. Refresh/fix environment sebelum menjalankan flow-check.' : (string) (($unit_test_runners['smoke_tests']['reason'] ?? ''))); ?>"
                                        data-runner-action-unit="cbt_run_unit_test_suite"
                                        data-runner-action-smoke="cbt_queue_flow_check_job"
                                    >
                                        <input type="hidden" name="action" value="<?php echo esc_attr(($active_panel_scope === 'smoke_tests') ? 'cbt_queue_flow_check_job' : 'cbt_run_unit_test_suite'); ?>" data-checklist-action-input>
                                        <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $unit_test_tab_key); ?>">
                                        <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr($active_panel_scope); ?>" data-checklist-scope-input>
                                        <?php wp_nonce_field('cbt_test_hub_runner_' . (string) $unit_test_tab_key); ?>
                                        <button
                                            type="submit"
                                            class="button button-primary cbt-test-hub-run-button"
                                            data-checklist-run-button
                                            <?php disabled(empty($active_scope_runner['available']) || ($active_panel_scope === 'smoke_tests' && $runner_health_is_blocked)); ?>
                                        >
                                            <?php echo esc_html((string) ($active_scope_runner['label'] ?? 'Run Tests')); ?>
                                        </button>
                                        <?php if (!empty($active_scope_runner['description']) || !empty($active_scope_runner['reason'])): ?>
                                            <div class="cbt-test-hub-run-help" data-checklist-run-help>
                                                <?php
                                                echo esc_html(
                                                    !empty($active_scope_runner['available'])
                                                        ? (string) ($active_scope_runner['description'] ?? '')
                                                        : (string) ($active_scope_runner['reason'] ?? '')
                                                );
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="cbt-test-hub-run-help" data-checklist-run-help></div>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="cbt-test-hub-grid" data-checklist-tabscope>
                            <nav class="cbt-test-hub-checklist-tabs" role="tablist" aria-label="Checklist views">
                                <button
                                    type="button"
                                    class="cbt-test-hub-checklist-tab<?php echo (($active_checklist_scope ?? 'unit_tests') === 'unit_tests') ? ' is-active' : ''; ?>"
                                    data-checklist-tab="unit_tests"
                                    role="tab"
                                    aria-selected="<?php echo (($active_checklist_scope ?? 'unit_tests') === 'unit_tests') ? 'true' : 'false'; ?>"
                                >
                                    Checklist Unit Test
                                </button>
                                <button
                                    type="button"
                                    class="cbt-test-hub-checklist-tab<?php echo (($active_checklist_scope ?? 'unit_tests') === 'smoke_tests') ? ' is-active' : ''; ?>"
                                    data-checklist-tab="smoke_tests"
                                    role="tab"
                                    aria-selected="<?php echo (($active_checklist_scope ?? 'unit_tests') === 'smoke_tests') ? 'true' : 'false'; ?>"
                                >
                                    Checklist Flow Check
                                </button>
                            </nav>

                            <article class="cbt-test-hub-section cbt-test-hub-checklist-panel<?php echo (($active_checklist_scope ?? 'unit_tests') === 'unit_tests') ? ' is-active' : ''; ?>" data-checklist-panel="unit_tests">
                                <div class="cbt-test-hub-section-head">
                                    <h4>Checklist Unit Test</h4>
                                    <?php if ($unit_has_runner_output): ?>
                                        <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo empty($unit_failed_commands) ? 'done' : 'danger'; ?>">
                                            <?php echo empty($unit_failed_commands) ? 'Runner Passed' : 'Runner Failed'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($panel_unit_run_result && $unit_has_runner_output): ?>
                                    <p class="cbt-test-hub-section-note">
                                        <?php if (!empty($panel_unit_run_result['item_label'])): ?>
                                            Output runner terbaru untuk task `<?php echo esc_html((string) $panel_unit_run_result['item_label']); ?>` sudah ditautkan ke item checklist yang relevan di bawah.
                                        <?php elseif ((string) ($panel_unit_run_result['type'] ?? '') === 'global_unit_tests'): ?>
                                            Output runner terbaru dari `Run All Unit Tests` sudah ditautkan ke item checklist unit di area ini.
                                        <?php else: ?>
                                            Output runner terbaru sudah ditautkan ke item checklist yang relevan di bawah.
                                        <?php endif; ?>
                                        <?php if (!empty($panel_unit_run_result['executed_at'])): ?>
                                            <?php echo esc_html(' Dijalankan: ' . wp_date('d M Y H:i:s', (int) $panel_unit_run_result['executed_at'])); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($unit_failed_commands)): ?>
                                    <div class="cbt-test-hub-run-summary">
                                        <strong>Runner yang gagal di Checklist Unit Test</strong>
                                        <p>Periksa command berikut lebih dulu. Item checklist yang terdampak dirangkum di bawah agar Anda tidak perlu membuka detail satu per satu.</p>
                                        <ul>
                                            <?php foreach ($unit_test_items as $unit_test_item): ?>
                                                <?php foreach ((array) ($unit_test_item['run_results'] ?? []) as $run_command): ?>
                                                    <?php if (empty($run_command['success'])): ?>
                                                        <li>
                                                            <?php
                                                            echo esc_html((string) ($run_command['label'] ?? 'Test Command') . ' (exit ' . (int) ($run_command['exit_code'] ?? 1) . ')');
                                                            if (!empty($run_command['failure_summary'])) {
                                                                echo esc_html(': ' . (string) $run_command['failure_summary']);
                                                            }
                                                            ?>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if (!empty($unit_failed_items)): ?>
                                            <p>Terdampak: <?php echo esc_html(implode(', ', array_keys($unit_failed_items))); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <ul class="cbt-test-hub-list">
                                    <?php foreach ($unit_test_items as $unit_test_item): ?>
                                        <?php
                                        $unit_item_form_key = sanitize_key((string) $unit_test_tab_key . '-unit-' . (string) ($unit_test_item['item_index'] ?? ''));
                                        $unit_item_run_form_id = 'cbt-test-hub-item-run-form-' . $unit_item_form_key;
                                        $unit_item_refresh_area = 'task-' . $unit_item_form_key;
                                        ?>
                                        <li data-cbt-test-hub-refresh-area="<?php echo esc_attr($unit_item_refresh_area); ?>" aria-busy="false">
                                            <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Menjalankan task...</span></p>
                                            <?php if (!empty($unit_test_item['has_runner'])): ?>
                                                <form id="<?php echo esc_attr($unit_item_run_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-item-run-form-summary" data-item-run-form data-cbt-test-hub-async-form data-refresh-areas="<?php echo esc_attr('banners,' . $unit_item_refresh_area); ?>" data-loading-label="Menjalankan..." hidden>
                                                    <input type="hidden" name="action" value="cbt_run_unit_test_suite">
                                                    <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $unit_test_tab_key); ?>">
                                                    <input type="hidden" name="cbt_checklist_scope" value="unit_tests">
                                                    <input type="hidden" name="cbt_checklist_item_index" value="<?php echo esc_attr((string) ($unit_test_item['item_index'] ?? '')); ?>">
                                                    <?php wp_nonce_field('cbt_test_hub_runner_' . (string) $unit_test_tab_key); ?>
                                                </form>
                                            <?php endif; ?>
                                            <details class="cbt-test-hub-item"<?php echo !empty($unit_test_item['detail_open']) ? ' open' : ''; ?>>
                                                <summary class="cbt-test-hub-item-summary">
                                                    <div class="cbt-test-hub-item-copy">
                                                        <div class="cbt-test-hub-list-copy"><?php echo esc_html((string) ($unit_test_item['label'] ?? '')); ?></div>
                                                        <div class="cbt-test-hub-item-hint"><?php echo esc_html((string) ($unit_test_item['detail_hint'] ?? 'Klik untuk melihat detail verifikasi.')); ?></div>
                                                        <?php if (!empty($unit_test_item['async_output_preview'])): ?>
                                                            <div class="cbt-test-hub-item-output-preview"><?php echo esc_html((string) $unit_test_item['async_output_preview']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="cbt-test-hub-item-side">
                                                        <div class="cbt-test-hub-item-side-top">
                                                            <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr((string) ($unit_test_item['status_tone'] ?? 'idle')); ?>">
                                                                <?php echo esc_html((string) ($unit_test_item['status_label'] ?? 'Draft')); ?>
                                                            </span>
                                                            <?php if (!empty($unit_test_item['async_status_label'])): ?>
                                                                <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr((string) ($unit_test_item['async_status_tone'] ?? 'idle')); ?>">
                                                                    <?php echo esc_html((string) ($unit_test_item['async_status_label'] ?? '')); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($unit_test_item['has_runner'])): ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button cbt-test-hub-item-run-trigger"
                                                                    data-item-run-button
                                                                    data-item-run-form-id="<?php echo esc_attr($unit_item_run_form_id); ?>"
                                                                    form="<?php echo esc_attr($unit_item_run_form_id); ?>"
                                                                    onclick="event.preventDefault(); event.stopPropagation(); var form = document.getElementById('<?php echo esc_js($unit_item_run_form_id); ?>'); if (form) { if (typeof form.requestSubmit === 'function') { form.requestSubmit(this); } else { form.submit(); } } return false;"
                                                                    <?php disabled(!$unit_runner_available || empty($unit_test_item['can_run_task'])); ?>
                                                                ><?php echo esc_html((string) ($unit_test_item['run_button_label'] ?? 'Run Task')); ?></button>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="cbt-test-hub-item-toggle">Detail</span>
                                                    </div>
                                                </summary>
                                                <div class="cbt-test-hub-item-body">
                                                    <?php if (!empty($unit_test_item['description'])): ?>
                                                        <p class="cbt-test-hub-item-description"><?php echo esc_html((string) $unit_test_item['description']); ?></p>
                                                    <?php endif; ?>
                                                    <div class="cbt-test-hub-item-meta">
                                                        <div class="cbt-test-hub-item-meta-block">
                                                            <h5>Proses Verifikasi</h5>
                                                            <ul>
                                                                <?php foreach ((array) ($unit_test_item['process_steps'] ?? []) as $process_step): ?>
                                                                    <li><?php echo esc_html((string) $process_step); ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                        <div class="cbt-test-hub-item-meta-block">
                                                            <h5>Evidence</h5>
                                                            <?php if (!empty($unit_test_item['evidence'])): ?>
                                                                <ul>
                                                                    <?php foreach ((array) ($unit_test_item['evidence'] ?? []) as $evidence_item): ?>
                                                                        <li><?php echo esc_html((string) $evidence_item); ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <div class="cbt-test-hub-item-meta-empty">Belum ada file atau target suite yang ditautkan khusus untuk item ini.</div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="cbt-test-hub-item-meta-block cbt-test-hub-item-meta-block--wide">
                                                            <h5>Hasil Runner Terbaru</h5>
                                                            <?php if (!empty($unit_test_item['async_job'])): ?>
                                                                <div class="cbt-test-hub-item-meta-empty" style="margin-bottom:10px;">
                                                                    Status job: <?php echo esc_html((string) ($unit_test_item['async_status_label'] ?? 'Queued')); ?>.
                                                                    <?php if (!empty($unit_test_item['async_job']['started_at'])): ?>
                                                                        <?php echo esc_html(' Start: ' . wp_date('d M Y H:i:s', (int) $unit_test_item['async_job']['started_at'])); ?>.
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($unit_test_item['async_job']['finished_at'])): ?>
                                                                        <?php echo esc_html(' Finish: ' . wp_date('d M Y H:i:s', (int) $unit_test_item['async_job']['finished_at'])); ?>.
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($unit_test_item['async_job']['failure_summary'])): ?>
                                                                        <?php echo esc_html(' Ringkasan: ' . (string) $unit_test_item['async_job']['failure_summary']); ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($unit_test_item['has_failed_run_results'])): ?>
                                                                <div class="cbt-test-hub-item-run-summary">
                                                                    <strong>Ringkasan Gagal Task Ini</strong>
                                                                    <p>Command berikut gagal pada task ini. Detail output lengkap tetap tersedia tepat di bawah ringkasan ini.</p>
                                                                    <ul>
                                                                        <?php foreach ((array) ($unit_test_item['failed_run_results'] ?? []) as $failed_run_command): ?>
                                                                            <li>
                                                                                <?php
                                                                                echo esc_html((string) ($failed_run_command['label'] ?? 'Test Command') . ' (exit ' . (int) ($failed_run_command['exit_code'] ?? 1) . ')');
                                                                                if (!empty($failed_run_command['failure_summary'])) {
                                                                                    echo esc_html(': ' . (string) $failed_run_command['failure_summary']);
                                                                                }
                                                                                ?>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($unit_test_item['run_results'])): ?>
                                                                <div class="cbt-test-hub-item-run-list">
                                                                    <?php foreach ((array) ($unit_test_item['run_results'] ?? []) as $run_command): ?>
                                                                        <article class="cbt-test-hub-item-run-command">
                                                                            <div class="cbt-test-hub-item-run-command-head">
                                                                                <strong><?php echo esc_html((string) ($run_command['label'] ?? 'Test Command')); ?></strong>
                                                                                <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo !empty($run_command['success']) ? 'done' : 'danger'; ?>">
                                                                                    <?php echo !empty($run_command['success']) ? 'Exit 0' : 'Exit ' . esc_html((string) ($run_command['exit_code'] ?? 1)); ?>
                                                                                </span>
                                                                            </div>
                                                                            <code><?php echo esc_html((string) ($run_command['command'] ?? '')); ?></code>
                                                                            <?php if (!empty($run_command['stdout'])): ?>
                                                                                <span class="cbt-test-hub-run-command-output-label">Stdout</span>
                                                                                <pre><?php echo esc_textarea((string) $run_command['stdout']); ?></pre>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($run_command['stderr'])): ?>
                                                                                <span class="cbt-test-hub-run-command-output-label">Stderr</span>
                                                                                <pre><?php echo esc_textarea((string) $run_command['stderr']); ?></pre>
                                                                            <?php endif; ?>
                                                                        </article>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php elseif (!empty($unit_test_item['runner_commands']) && !empty($unit_test_item['is_job_active'])): ?>
                                                                <div class="cbt-test-hub-item-meta-empty">
                                                                    Job flow check sedang berjalan di background. Refresh otomatis akan memperbarui output saat job selesai.
                                                                </div>
                                                            <?php elseif (!empty($unit_test_item['runner_commands'])): ?>
                                                                <div class="cbt-test-hub-item-meta-empty">
                                                                    Runner terkait: <?php echo esc_html(implode(', ', (array) $unit_test_item['runner_commands'])); ?>. Gunakan tombol `Run Task` di samping status untuk melihat hasil terbaru task ini.
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="cbt-test-hub-item-meta-empty">Belum ada hasil runner atau mapping command khusus untuk item ini.</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </article>

                            <article class="cbt-test-hub-section cbt-test-hub-checklist-panel<?php echo (($active_checklist_scope ?? 'unit_tests') === 'smoke_tests') ? ' is-active' : ''; ?>" data-checklist-panel="smoke_tests">
                                <div class="cbt-test-hub-section-head">
                                    <h4>Checklist Flow Check</h4>
                                    <?php if ($flow_has_active_jobs): ?>
                                        <span class="cbt-test-hub-chip cbt-test-hub-chip--planned">Flow Active</span>
                                    <?php elseif ($flow_has_runner_output): ?>
                                        <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo empty($flow_failed_commands) ? 'done' : 'danger'; ?>">
                                            <?php echo empty($flow_failed_commands) ? 'Runner Passed' : 'Runner Failed'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (array_sum($flow_status_counts) > 0): ?>
                                    <p class="cbt-test-hub-section-note">
                                        Status async terbaru:
                                        <?php
                                        $flow_status_summary_parts = [];
                                        foreach (['queued', 'running', 'cancelling', 'passed', 'failed', 'cancelled'] as $flow_status_key) {
                                            $flow_status_count = (int) ($flow_status_counts[$flow_status_key] ?? 0);
                                            if ($flow_status_count > 0) {
                                                $flow_status_summary_parts[] = $flow_status_count . ' ' . $flow_status_key;
                                            }
                                        }
	                                        echo esc_html(implode(', ', $flow_status_summary_parts) . '.');
	                                        ?>
	                                        <?php if ($flow_has_active_jobs): ?>
	                                            Area Flow Check akan diperbarui otomatis selama masih ada job aktif.
	                                        <?php endif; ?>
                                            <span class="cbt-test-hub-flow-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $flow_status_progress_percent); ?>">
                                                <span><?php echo esc_html('Progress flow: ' . (string) $flow_status_terminal . ' / ' . (string) $flow_status_total . ' job selesai (' . (string) $flow_status_progress_percent . '%).'); ?></span>
                                                <span class="cbt-test-hub-flow-progress-track" aria-hidden="true" style="--cbt-test-hub-flow-progress: <?php echo esc_attr((string) $flow_status_progress_percent); ?>%;"><span></span></span>
                                            </span>
	                                    </p>
	                                <?php endif; ?>
                                <?php if ($panel_flow_run_result && $flow_has_runner_output): ?>
                                    <p class="cbt-test-hub-section-note">
                                        <?php if (!empty($panel_flow_run_result['item_label'])): ?>
                                            Output runner flow terbaru untuk task `<?php echo esc_html((string) $panel_flow_run_result['item_label']); ?>` sudah ditautkan ke item checklist yang relevan di bawah.
                                        <?php else: ?>
                                            Output runner flow terbaru sudah ditautkan ke item checklist yang relevan di bawah.
                                        <?php endif; ?>
                                        <?php if (!empty($panel_flow_run_result['executed_at'])): ?>
                                            <?php echo esc_html(' Dijalankan: ' . wp_date('d M Y H:i:s', (int) $panel_flow_run_result['executed_at'])); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($flow_failed_commands)): ?>
                                    <div class="cbt-test-hub-run-summary">
                                        <strong>Runner yang gagal di Checklist Flow Check</strong>
                                        <p>Command berikut gagal pada flow check. Ringkasan ini menunjukkan area yang perlu dibuka dulu sebelum melihat detail output.</p>
                                        <ul>
                                            <?php foreach ($flow_check_items as $unit_test_item): ?>
                                                <?php foreach ((array) ($unit_test_item['run_results'] ?? []) as $run_command): ?>
                                                    <?php if (empty($run_command['success'])): ?>
                                                        <li>
                                                            <?php
                                                            echo esc_html((string) ($run_command['label'] ?? 'Test Command') . ' (exit ' . (int) ($run_command['exit_code'] ?? 1) . ')');
                                                            if (!empty($run_command['failure_summary'])) {
                                                                echo esc_html(': ' . (string) $run_command['failure_summary']);
                                                            }
                                                            ?>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if (!empty($flow_failed_items)): ?>
                                            <p>Terdampak: <?php echo esc_html(implode(', ', array_keys($flow_failed_items))); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <ul class="cbt-test-hub-list">
                                    <?php foreach ($flow_check_items as $unit_test_item): ?>
                                        <?php
                                        $flow_item_form_key = sanitize_key((string) $unit_test_tab_key . '-smoke-' . (string) ($unit_test_item['item_index'] ?? ''));
                                        $flow_item_run_form_id = 'cbt-test-hub-item-run-form-' . $flow_item_form_key;
                                        $flow_item_cancel_form_id = 'cbt-test-hub-item-cancel-form-' . $flow_item_form_key;
                                        $flow_item_retry_form_id = 'cbt-test-hub-item-retry-form-' . $flow_item_form_key;
                                        $flow_item_clear_form_id = 'cbt-test-hub-item-clear-form-' . $flow_item_form_key;
                                        $flow_item_refresh_area = 'task-' . $flow_item_form_key;
                                        $flow_item_job_id = !empty($unit_test_item['async_job']) && is_array($unit_test_item['async_job'])
                                            ? (string) ($unit_test_item['async_job']['job_id'] ?? '')
                                            : '';
                                        ?>
                                        <li data-cbt-test-hub-refresh-area="<?php echo esc_attr($flow_item_refresh_area); ?>" aria-busy="false">
                                            <p class="cbt-test-hub-loading-status" aria-live="polite"><span class="cbt-test-hub-loading-spinner" aria-hidden="true"></span><span>Memproses flow task...</span></p>
                                            <?php if (!empty($unit_test_item['has_runner'])): ?>
                                                <form id="<?php echo esc_attr($flow_item_run_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-item-run-form-summary" data-item-run-form data-cbt-test-hub-async-form data-refresh-areas="<?php echo esc_attr('banners,artifacts,' . $flow_item_refresh_area); ?>" data-loading-label="Queueing..." hidden>
                                                    <input type="hidden" name="action" value="cbt_queue_flow_check_job">
                                                    <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $unit_test_tab_key); ?>">
                                                    <input type="hidden" name="cbt_checklist_scope" value="smoke_tests">
                                                    <input type="hidden" name="cbt_checklist_item_index" value="<?php echo esc_attr((string) ($unit_test_item['item_index'] ?? '')); ?>">
                                                    <?php wp_nonce_field('cbt_test_hub_runner_' . (string) $unit_test_tab_key); ?>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($flow_item_job_id !== ''): ?>
                                                <form id="<?php echo esc_attr($flow_item_cancel_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-item-run-form-summary" data-cbt-test-hub-async-form data-refresh-areas="<?php echo esc_attr('banners,artifacts,' . $flow_item_refresh_area); ?>" data-loading-label="Cancelling..." hidden>
                                                    <input type="hidden" name="action" value="cbt_cancel_flow_check_job">
                                                    <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $unit_test_tab_key); ?>">
                                                    <input type="hidden" name="cbt_checklist_scope" value="smoke_tests">
                                                    <input type="hidden" name="cbt_flow_job_id" value="<?php echo esc_attr($flow_item_job_id); ?>">
                                                    <?php wp_nonce_field('cbt_test_hub_flow_job_action'); ?>
                                                </form>
                                                <form id="<?php echo esc_attr($flow_item_retry_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-item-run-form-summary" data-cbt-test-hub-async-form data-refresh-areas="<?php echo esc_attr('banners,artifacts,' . $flow_item_refresh_area); ?>" data-loading-label="Retrying..." hidden>
                                                    <input type="hidden" name="action" value="cbt_retry_flow_check_job">
                                                    <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $unit_test_tab_key); ?>">
                                                    <input type="hidden" name="cbt_checklist_scope" value="smoke_tests">
                                                    <input type="hidden" name="cbt_flow_job_id" value="<?php echo esc_attr($flow_item_job_id); ?>">
                                                    <?php wp_nonce_field('cbt_test_hub_flow_job_action'); ?>
                                                </form>
                                                <form id="<?php echo esc_attr($flow_item_clear_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-item-run-form-summary" data-cbt-test-hub-async-form data-refresh-areas="<?php echo esc_attr('banners,artifacts,' . $flow_item_refresh_area); ?>" data-loading-label="Clearing..." hidden>
                                                    <input type="hidden" name="action" value="cbt_clear_flow_check_job">
                                                    <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $unit_test_tab_key); ?>">
                                                    <input type="hidden" name="cbt_checklist_scope" value="smoke_tests">
                                                    <input type="hidden" name="cbt_flow_job_id" value="<?php echo esc_attr($flow_item_job_id); ?>">
                                                    <?php wp_nonce_field('cbt_test_hub_flow_job_action'); ?>
                                                </form>
                                            <?php endif; ?>
                                            <details class="cbt-test-hub-item"<?php echo !empty($unit_test_item['detail_open']) ? ' open' : ''; ?>>
                                                <summary class="cbt-test-hub-item-summary">
                                                    <div class="cbt-test-hub-item-copy">
                                                        <div class="cbt-test-hub-list-copy"><?php echo esc_html((string) ($unit_test_item['label'] ?? '')); ?></div>
                                                        <div class="cbt-test-hub-item-hint"><?php echo esc_html((string) ($unit_test_item['detail_hint'] ?? 'Klik untuk melihat detail verifikasi.')); ?></div>
                                                        <?php if (!empty($unit_test_item['async_output_preview'])): ?>
                                                            <div class="cbt-test-hub-item-output-preview"><?php echo esc_html((string) $unit_test_item['async_output_preview']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="cbt-test-hub-item-side">
                                                        <div class="cbt-test-hub-item-side-top">
                                                            <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr((string) ($unit_test_item['status_tone'] ?? 'idle')); ?>">
                                                                <?php echo esc_html((string) ($unit_test_item['status_label'] ?? 'Draft')); ?>
                                                            </span>
                                                            <?php if (!empty($unit_test_item['async_status_label'])): ?>
                                                                <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo esc_attr((string) ($unit_test_item['async_status_tone'] ?? 'idle')); ?>">
                                                                    <?php echo esc_html((string) ($unit_test_item['async_status_label'] ?? '')); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($unit_test_item['has_runner'])): ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button cbt-test-hub-item-run-trigger"
                                                                    data-item-run-button
                                                                    data-item-run-form-id="<?php echo esc_attr($flow_item_run_form_id); ?>"
                                                                    form="<?php echo esc_attr($flow_item_run_form_id); ?>"
                                                                    onclick="event.preventDefault(); event.stopPropagation(); var form = document.getElementById('<?php echo esc_js($flow_item_run_form_id); ?>'); if (form) { if (typeof form.requestSubmit === 'function') { form.requestSubmit(this); } else { form.submit(); } } return false;"
                                                                    <?php disabled(!$flow_runner_available || $runner_health_is_blocked || empty($unit_test_item['can_run_task'])); ?>
                                                                ><?php echo esc_html((string) ($unit_test_item['run_button_label'] ?? 'Run Task')); ?></button>
                                                            <?php endif; ?>
                                                            <?php if ($flow_item_job_id !== '' && !empty($unit_test_item['can_cancel_job'])): ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button cbt-test-hub-item-run-trigger cbt-test-hub-item-run-trigger--danger"
                                                                    form="<?php echo esc_attr($flow_item_cancel_form_id); ?>"
                                                                    onclick="event.preventDefault(); event.stopPropagation(); var form = document.getElementById('<?php echo esc_js($flow_item_cancel_form_id); ?>'); if (form) { if (typeof form.requestSubmit === 'function') { form.requestSubmit(this); } else { form.submit(); } } return false;"
                                                                    <?php disabled(empty($unit_test_item['can_cancel_job'])); ?>
                                                                >Cancel</button>
                                                            <?php endif; ?>
                                                            <?php if ($flow_item_job_id !== '' && !empty($unit_test_item['can_retry_job'])): ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button cbt-test-hub-item-run-trigger"
                                                                    form="<?php echo esc_attr($flow_item_retry_form_id); ?>"
                                                                    onclick="event.preventDefault(); event.stopPropagation(); var form = document.getElementById('<?php echo esc_js($flow_item_retry_form_id); ?>'); if (form) { if (typeof form.requestSubmit === 'function') { form.requestSubmit(this); } else { form.submit(); } } return false;"
                                                                    <?php disabled(!$flow_runner_available || $runner_health_is_blocked || empty($unit_test_item['can_retry_job'])); ?>
                                                                >Retry</button>
                                                            <?php endif; ?>
                                                            <?php if ($flow_item_job_id !== '' && !empty($unit_test_item['can_clear_job'])): ?>
                                                                <button
                                                                    type="submit"
                                                                    class="button cbt-test-hub-item-run-trigger"
                                                                    form="<?php echo esc_attr($flow_item_clear_form_id); ?>"
                                                                    onclick="event.preventDefault(); event.stopPropagation(); var form = document.getElementById('<?php echo esc_js($flow_item_clear_form_id); ?>'); if (form) { if (typeof form.requestSubmit === 'function') { form.requestSubmit(this); } else { form.submit(); } } return false;"
                                                                    <?php disabled(empty($unit_test_item['can_clear_job'])); ?>
                                                                >Clear</button>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="cbt-test-hub-item-toggle">Detail</span>
                                                    </div>
                                                </summary>
                                                <div class="cbt-test-hub-item-body">
                                                    <?php if (!empty($unit_test_item['description'])): ?>
                                                        <p class="cbt-test-hub-item-description"><?php echo esc_html((string) $unit_test_item['description']); ?></p>
                                                    <?php endif; ?>
                                                    <div class="cbt-test-hub-item-meta">
                                                        <div class="cbt-test-hub-item-meta-block">
                                                            <h5>Proses Verifikasi</h5>
                                                            <ul>
                                                                <?php foreach ((array) ($unit_test_item['process_steps'] ?? []) as $process_step): ?>
                                                                    <li><?php echo esc_html((string) $process_step); ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                        <div class="cbt-test-hub-item-meta-block">
                                                            <h5>Evidence</h5>
                                                            <?php if (!empty($unit_test_item['evidence'])): ?>
                                                                <ul>
                                                                    <?php foreach ((array) ($unit_test_item['evidence'] ?? []) as $evidence_item): ?>
                                                                        <li><?php echo esc_html((string) $evidence_item); ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php else: ?>
                                                                <div class="cbt-test-hub-item-meta-empty">Flow check ini belum ditautkan ke evidence khusus. Gunakan proses verifikasi sebagai panduan eksekusi.</div>
                                                            <?php endif; ?>
                                                        </div>
	                                                        <div class="cbt-test-hub-item-meta-block cbt-test-hub-item-meta-block--wide">
	                                                            <h5>Hasil Runner Terbaru</h5>
	                                                            <?php
	                                                            $artifact_context = isset($unit_test_item['artifact_context']) && is_array($unit_test_item['artifact_context']) ? (array) $unit_test_item['artifact_context'] : [];
	                                                            $artifact_log = isset($artifact_context['log']) && is_array($artifact_context['log']) ? (array) $artifact_context['log'] : [];
	                                                            $artifact_output_preview = isset($artifact_context['output_preview']) && is_array($artifact_context['output_preview']) ? (array) $artifact_context['output_preview'] : [];
	                                                            $artifact_files = isset($artifact_context['artifacts']) && is_array($artifact_context['artifacts']) ? (array) $artifact_context['artifacts'] : [];
	                                                            ?>
	                                                            <?php if (!empty($artifact_context['has_any'])): ?>
	                                                                <section class="cbt-test-hub-job-artifacts">
	                                                                    <div class="cbt-test-hub-job-artifacts-head">
	                                                                        <div>
	                                                                            <strong>Log &amp; Artifacts</strong>
	                                                                            <span><?php echo esc_html((string) count($artifact_files) . ' artifact file, log ' . (!empty($artifact_log) ? 'tersedia' : 'kosong') . '.'); ?></span>
	                                                                        </div>
	                                                                        <?php if (!empty($artifact_log['download_url'])): ?>
	                                                                            <a class="button button-small button-secondary" href="<?php echo esc_url((string) $artifact_log['download_url']); ?>">Download Log</a>
	                                                                        <?php endif; ?>
	                                                                    </div>
	                                                                    <?php if (!empty($artifact_log)): ?>
	                                                                        <article class="cbt-test-hub-job-artifact-card">
	                                                                            <div class="cbt-test-hub-job-artifact-card-head">
	                                                                                <strong><?php echo esc_html((string) ($artifact_log['name'] ?? 'Job Log')); ?></strong>
	                                                                                <span>
	                                                                                    <?php echo esc_html((string) ((int) ($artifact_log['size'] ?? 0)) . ' bytes'); ?>
	                                                                                    <?php if (!empty($artifact_log['updated_at'])): ?>
	                                                                                        <?php echo esc_html(' . Updated ' . wp_date('d M Y H:i:s', (int) $artifact_log['updated_at'])); ?>
	                                                                                    <?php endif; ?>
	                                                                                    <?php if (!empty($artifact_log['truncated'])): ?>
	                                                                                        <?php echo esc_html(' . Preview truncated'); ?>
	                                                                                    <?php endif; ?>
	                                                                                </span>
	                                                                            </div>
	                                                                            <?php if (!empty($artifact_log['preview'])): ?>
	                                                                                <pre><?php echo esc_textarea((string) $artifact_log['preview']); ?></pre>
	                                                                            <?php endif; ?>
	                                                                        </article>
	                                                                    <?php endif; ?>
	                                                                    <?php if (!empty($artifact_output_preview)): ?>
	                                                                        <article class="cbt-test-hub-job-artifact-card">
	                                                                            <div class="cbt-test-hub-job-artifact-card-head">
	                                                                                <strong><?php echo esc_html((string) ($artifact_output_preview['label'] ?? 'Output Snapshot')); ?></strong>
	                                                                                <span><?php echo !empty($artifact_output_preview['truncated']) ? esc_html('Preview truncated') : esc_html('Inline stdout/stderr snapshot'); ?></span>
	                                                                            </div>
	                                                                            <?php if (!empty($artifact_output_preview['preview'])): ?>
	                                                                                <pre><?php echo esc_textarea((string) $artifact_output_preview['preview']); ?></pre>
	                                                                            <?php endif; ?>
	                                                                        </article>
	                                                                    <?php endif; ?>
	                                                                    <?php if (!empty($artifact_files)): ?>
	                                                                        <div class="cbt-test-hub-job-artifact-card">
	                                                                            <div class="cbt-test-hub-job-artifact-card-head">
	                                                                                <strong>Artifact Files</strong>
	                                                                                <span><?php echo esc_html((string) count($artifact_files) . ' file siap diunduh'); ?></span>
	                                                                            </div>
	                                                                            <div class="cbt-test-hub-job-artifact-links">
	                                                                                <?php foreach ($artifact_files as $artifact_file): ?>
	                                                                                    <?php if (!is_array($artifact_file) || empty($artifact_file['download_url'])) { continue; } ?>
	                                                                                    <a class="button button-small button-secondary" href="<?php echo esc_url((string) $artifact_file['download_url']); ?>"><?php echo esc_html((string) ($artifact_file['name'] ?? 'Artifact')); ?></a>
	                                                                                    <span><?php echo esc_html((string) ($artifact_file['relative_path'] ?? '') . ' . ' . (string) ((int) ($artifact_file['size'] ?? 0)) . ' bytes'); ?></span>
	                                                                                <?php endforeach; ?>
	                                                                            </div>
	                                                                        </div>
	                                                                    <?php endif; ?>
	                                                                </section>
	                                                            <?php endif; ?>
	                                                            <?php if (!empty($unit_test_item['has_failed_run_results'])): ?>
	                                                                <div class="cbt-test-hub-item-run-summary">
	                                                                    <strong>Ringkasan Gagal Task Ini</strong>
                                                                    <p>Command berikut gagal pada task ini. Detail output lengkap tetap tersedia tepat di bawah ringkasan ini.</p>
                                                                    <ul>
                                                                        <?php foreach ((array) ($unit_test_item['failed_run_results'] ?? []) as $failed_run_command): ?>
                                                                            <li>
                                                                                <?php
                                                                                echo esc_html((string) ($failed_run_command['label'] ?? 'Test Command') . ' (exit ' . (int) ($failed_run_command['exit_code'] ?? 1) . ')');
                                                                                if (!empty($failed_run_command['failure_summary'])) {
                                                                                    echo esc_html(': ' . (string) $failed_run_command['failure_summary']);
                                                                                }
                                                                                ?>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($unit_test_item['run_results'])): ?>
                                                                <div class="cbt-test-hub-item-run-list">
                                                                    <?php foreach ((array) ($unit_test_item['run_results'] ?? []) as $run_command): ?>
                                                                        <article class="cbt-test-hub-item-run-command">
                                                                            <div class="cbt-test-hub-item-run-command-head">
                                                                                <strong><?php echo esc_html((string) ($run_command['label'] ?? 'Test Command')); ?></strong>
                                                                                <span class="cbt-test-hub-chip cbt-test-hub-chip--<?php echo !empty($run_command['success']) ? 'done' : 'danger'; ?>">
                                                                                    <?php echo !empty($run_command['success']) ? 'Exit 0' : 'Exit ' . esc_html((string) ($run_command['exit_code'] ?? 1)); ?>
                                                                                </span>
                                                                            </div>
                                                                            <code><?php echo esc_html((string) ($run_command['command'] ?? '')); ?></code>
                                                                            <?php if (!empty($run_command['stdout'])): ?>
                                                                                <span class="cbt-test-hub-run-command-output-label">Stdout</span>
                                                                                <pre><?php echo esc_textarea((string) $run_command['stdout']); ?></pre>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($run_command['stderr'])): ?>
                                                                                <span class="cbt-test-hub-run-command-output-label">Stderr</span>
                                                                                <pre><?php echo esc_textarea((string) $run_command['stderr']); ?></pre>
                                                                            <?php endif; ?>
                                                                        </article>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php elseif (!empty($unit_test_item['runner_commands'])): ?>
                                                                <div class="cbt-test-hub-item-meta-empty">
                                                                    Runner terkait: <?php echo esc_html(implode(', ', (array) $unit_test_item['runner_commands'])); ?>. Gunakan tombol `Run Task` di samping status untuk melihat hasil terbaru task ini.
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="cbt-test-hub-item-meta-empty">Flow check ini belum punya runner khusus. Verifikasi dilakukan manual mengikuti proses di atas.</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </article>

                        </div>
                    </section>
                <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
    (function () {
        const root = document.querySelector('[data-test-hub-root]');
        const defaultUnitTestTab = <?php echo wp_json_encode($active_unit_test_tab); ?> || 'recovery_persistence';
        const defaultChecklistScope = <?php echo wp_json_encode($active_checklist_scope ?? 'unit_tests'); ?> || 'unit_tests';
        let currentUnitTestTab = defaultUnitTestTab;
        let currentChecklistScope = defaultChecklistScope;
        let hasActiveFlowJobs = root ? root.getAttribute('data-has-active-flow-jobs') === '1' : <?php echo !empty($has_active_flow_jobs) ? 'true' : 'false'; ?>;
        let hasActiveGlobalUnitRun = root ? root.getAttribute('data-has-active-global-unit-run') === '1' : <?php echo !empty($global_unit_run_active) ? 'true' : 'false'; ?>;
        let scopedRefreshTimer = null;
        let globalUnitRunTimer = null;
        let globalUnitRunInFlight = false;
        let scopedRefreshInFlight = false;
        let testHubRefreshRequestSeq = 0;
        let testHubLocalActionInFlight = false;
        const loadingProgressStates = new Map();
        const canonicalRefreshUrl = root ? String(root.getAttribute('data-test-hub-refresh-url') || '') : '';
        const canonicalAsyncUrl = root ? String(root.getAttribute('data-test-hub-async-url') || '') : '';
        const collapsibleStoragePrefix = 'cbt_test_hub_collapsible_v1_';

        const buildTestHubPageUrl = function () {
            try {
                return new URL(canonicalRefreshUrl || window.location.href, window.location.href);
            } catch (error) {
                return new URL(window.location.href);
            }
        };

        const getRefreshArea = function (areaName) {
            if (!/^[a-z0-9_-]+$/.test(String(areaName || ''))) {
                return null;
            }

            return document.querySelector('[data-cbt-test-hub-refresh-area="' + areaName + '"]');
        };

        const parseAreaNames = function (rawValue) {
            return String(rawValue || '')
                .split(',')
                .map(function (areaName) {
                    return areaName.trim();
                })
                .filter(function (areaName, index, areaNames) {
                    return areaName !== '' && /^[a-z0-9_-]+$/.test(areaName) && areaNames.indexOf(areaName) === index;
                });
        };

        const clampProgress = function (value) {
            const number = Number(value);
            if (!Number.isFinite(number)) {
                return 0;
            }

            return Math.max(0, Math.min(100, Math.round(number)));
        };

        const getAreaKey = function (area) {
            if (!(area instanceof HTMLElement)) {
                return '';
            }

            return String(area.getAttribute('data-cbt-test-hub-refresh-area') || '');
        };

        const getCollapsibleKey = function (panel) {
            const key = panel instanceof HTMLElement ? String(panel.getAttribute('data-cbt-test-hub-collapsible') || '') : '';
            return /^[a-z0-9_-]+$/.test(key) ? key : '';
        };

        const readCollapsibleCollapsedState = function (panel) {
            const key = getCollapsibleKey(panel);
            const defaultCollapsed = String(panel.getAttribute('data-cbt-test-hub-collapsible-default') || '') === 'collapsed';

            if (key === '') {
                return defaultCollapsed;
            }

            try {
                const stored = window.localStorage ? window.localStorage.getItem(collapsibleStoragePrefix + key) : '';
                if (stored === 'open') {
                    return false;
                }
                if (stored === 'collapsed') {
                    return true;
                }
            } catch (error) {
                // Ignore localStorage failures; default state is still safe.
            }

            return defaultCollapsed;
        };

        const setCollapsibleState = function (panel, collapsed, persist) {
            if (!(panel instanceof HTMLElement)) {
                return;
            }

            const key = getCollapsibleKey(panel);
            const body = panel.querySelector('[data-cbt-test-hub-collapsible-body]');
            const toggle = panel.querySelector('[data-cbt-test-hub-collapse-toggle]');
            const isCollapsed = Boolean(collapsed);

            panel.classList.toggle('is-collapsed', isCollapsed);
            if (body instanceof HTMLElement) {
                body.setAttribute('aria-hidden', isCollapsed ? 'true' : 'false');
            }
            if (toggle instanceof HTMLButtonElement) {
                toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                const explicitLabel = String(panel.getAttribute('data-cbt-test-hub-collapsible-label') || '').trim();
                const controlLabel = explicitLabel !== ''
                    ? explicitLabel
                    : (String(toggle.getAttribute('aria-controls') || '').indexOf('e2e') >= 0 ? 'E2E Readiness' : 'Runner Health');
                const actionLabel = isCollapsed ? 'Tampilkan detail ' + controlLabel : 'Sembunyikan detail ' + controlLabel;
                toggle.setAttribute('aria-label', actionLabel);
                toggle.setAttribute('title', actionLabel);
            }

            if (!persist || key === '') {
                return;
            }

            try {
                if (window.localStorage) {
                    window.localStorage.setItem(collapsibleStoragePrefix + key, isCollapsed ? 'collapsed' : 'open');
                }
            } catch (error) {
                // Ignore localStorage write failures.
            }
        };

        const bindCollapsibleHealthPanels = function () {
            document.querySelectorAll('[data-cbt-test-hub-collapsible]').forEach(function (panel) {
                if (!(panel instanceof HTMLElement)) {
                    return;
                }

                setCollapsibleState(panel, readCollapsibleCollapsedState(panel), false);

                const toggle = panel.querySelector('[data-cbt-test-hub-collapse-toggle]');
                if (!(toggle instanceof HTMLButtonElement) || toggle.dataset.testHubCollapseBound === '1') {
                    return;
                }

                toggle.dataset.testHubCollapseBound = '1';
                toggle.addEventListener('click', function () {
                    setCollapsibleState(panel, !panel.classList.contains('is-collapsed'), true);
                });
            });
        };

        const bindUnitTestInventoryPagination = function () {
            document.querySelectorAll('[data-unit-inventory-list]').forEach(function (list) {
                if (!(list instanceof HTMLElement)) {
                    return;
                }

                const items = Array.prototype.slice.call(list.querySelectorAll('[data-unit-inventory-item]')).filter(function (item) {
                    return item instanceof HTMLElement;
                });
                const rawPageSize = Number.parseInt(String(list.getAttribute('data-page-size') || '25'), 10);
                const pageSize = Number.isFinite(rawPageSize) && rawPageSize > 0 ? rawPageSize : 25;
                const totalPages = Math.max(1, Math.ceil(items.length / pageSize));
                const container = list.parentElement;
                const pagination = container instanceof HTMLElement ? container.querySelector('[data-unit-inventory-pagination]') : null;
                const status = pagination instanceof HTMLElement ? pagination.querySelector('[data-unit-inventory-page-status]') : null;
                const prevButton = pagination instanceof HTMLElement ? pagination.querySelector('[data-unit-inventory-page-prev]') : null;
                const nextButton = pagination instanceof HTMLElement ? pagination.querySelector('[data-unit-inventory-page-next]') : null;

                const findPreferredPage = function () {
                    let preferredIndex = items.findIndex(function (item) {
                        return item.getAttribute('data-unit-inventory-state') === 'failed';
                    });
                    if (preferredIndex < 0) {
                        preferredIndex = items.findIndex(function (item) {
                            return item.getAttribute('data-unit-inventory-state') === 'result';
                        });
                    }

                    return preferredIndex >= 0 ? Math.floor(preferredIndex / pageSize) + 1 : 1;
                };

                const normalizePage = function (page) {
                    const parsed = Number.parseInt(String(page || ''), 10);
                    if (!Number.isFinite(parsed)) {
                        return findPreferredPage();
                    }

                    return Math.max(1, Math.min(totalPages, parsed));
                };

                const renderPage = function (page) {
                    const currentPage = normalizePage(page);
                    const startIndex = (currentPage - 1) * pageSize;
                    const endIndex = Math.min(items.length, startIndex + pageSize);

                    items.forEach(function (item, index) {
                        item.hidden = index < startIndex || index >= endIndex;
                    });

                    list.dataset.unitInventoryPage = String(currentPage);
                    if (pagination instanceof HTMLElement) {
                        pagination.hidden = items.length <= pageSize;
                    }
                    if (status instanceof HTMLElement) {
                        status.textContent = items.length > 0
                            ? 'Menampilkan ' + (startIndex + 1) + '-' + endIndex + ' dari ' + items.length + ' file'
                            : 'Belum ada file unit test.';
                    }
                    if (prevButton instanceof HTMLButtonElement) {
                        prevButton.disabled = currentPage <= 1;
                    }
                    if (nextButton instanceof HTMLButtonElement) {
                        nextButton.disabled = currentPage >= totalPages;
                    }
                };

                if (list.dataset.unitInventoryPaginationBound !== '1') {
                    list.dataset.unitInventoryPaginationBound = '1';
                    if (prevButton instanceof HTMLButtonElement) {
                        prevButton.addEventListener('click', function () {
                            renderPage(normalizePage(list.dataset.unitInventoryPage) - 1);
                        });
                    }
                    if (nextButton instanceof HTMLButtonElement) {
                        nextButton.addEventListener('click', function () {
                            renderPage(normalizePage(list.dataset.unitInventoryPage) + 1);
                        });
                    }
                }

                renderPage(list.dataset.unitInventoryPage || findPreferredPage());
            });
        };

        const ensureLoadingProgress = function (area) {
            const status = area instanceof HTMLElement ? area.querySelector('.cbt-test-hub-loading-status') : null;
            if (!(status instanceof HTMLElement)) {
                return null;
            }

            let message = status.querySelector('[data-loading-message]');
            if (!message) {
                const spans = status.querySelectorAll('span');
                message = spans.length > 0 ? spans[spans.length - 1] : null;
                if (message) {
                    message.setAttribute('data-loading-message', '1');
                    message.classList.add('cbt-test-hub-loading-message');
                }
            }

            let percent = status.querySelector('[data-loading-percent]');
            if (!percent) {
                percent = document.createElement('span');
                percent.className = 'cbt-test-hub-loading-percent';
                percent.setAttribute('data-loading-percent', '1');
                percent.textContent = '0%';
                status.appendChild(percent);
            }

            let progress = status.querySelector('[data-loading-progress]');
            if (!progress) {
                progress = document.createElement('span');
                progress.className = 'cbt-test-hub-loading-progress';
                progress.setAttribute('data-loading-progress', '1');
                progress.setAttribute('role', 'progressbar');
                progress.setAttribute('aria-valuemin', '0');
                progress.setAttribute('aria-valuemax', '100');
                progress.setAttribute('aria-valuenow', '0');
                const bar = document.createElement('span');
                bar.setAttribute('data-loading-progress-bar', '1');
                progress.appendChild(bar);
                status.appendChild(progress);
            }

            let step = status.querySelector('[data-loading-step]');
            if (!step) {
                step = document.createElement('span');
                step.className = 'cbt-test-hub-loading-step';
                step.setAttribute('data-loading-step', '1');
                step.textContent = 'Menyiapkan...';
                status.appendChild(step);
            }

            return {
                status: status,
                message: message,
                percent: percent,
                progress: progress,
                step: step
            };
        };

        const updateLoadingProgress = function (area, percentValue, message, stepText) {
            const elements = ensureLoadingProgress(area);
            if (!elements) {
                return;
            }

            const percent = clampProgress(percentValue);
            if (elements.message && message) {
                elements.message.textContent = message;
            }
            if (elements.percent) {
                elements.percent.textContent = percent + '%';
            }
            if (elements.progress) {
                elements.progress.style.setProperty('--cbt-test-hub-progress', percent + '%');
                elements.progress.setAttribute('aria-valuenow', String(percent));
            }
            if (elements.step && stepText) {
                elements.step.textContent = stepText;
            }
        };

        const resolveProgressProfile = function (action, label, areaNames) {
            const actionKey = String(action || '');
            const fallbackLabel = String(label || 'Memproses...');
            const profiles = {
                cbt_run_all_unit_tests: {
                    cap: 96,
                    interval: 700,
                    steps: ['Menyiapkan runner global.', 'Menjalankan unit test per subsystem.', 'Mengumpulkan stdout/stderr.', 'Menautkan hasil ke checklist.', 'Merender ringkasan terbaru.']
                },
                cbt_run_unit_test_suite: {
                    cap: 94,
                    interval: 620,
                    steps: ['Menyiapkan command runner.', 'Menjalankan unit test terpilih.', 'Membaca output runner.', 'Menautkan hasil ke task.', 'Memperbarui panel lokal.']
                },
                cbt_queue_flow_check_job: {
                    cap: 92,
                    interval: 420,
                    steps: ['Memvalidasi task flow-check.', 'Membuat job JSON.', 'Mengantrekan worker background.', 'Memperbarui status queued.']
                },
                cbt_refresh_test_hub_health: {
                    cap: 94,
                    interval: 430,
                    steps: ['Mengecek capability PHP.', 'Mengecek shell, Node, dan npm.', 'Mengecek Playwright dan browser.', 'Mengecek folder job dan URL.', 'Merender hasil health.']
                },
                cbt_check_test_hub_e2e_readiness: {
                    cap: 94,
                    interval: 520,
                    steps: ['Membaca URL settings.', 'Mengecek WordPress login.', 'Mengecek halaman CBT frontend.', 'Mengecek seed user dan fixture.', 'Merender URL Doctor.']
                },
                cbt_save_test_hub_settings: {
                    cap: 90,
                    interval: 360,
                    steps: ['Memvalidasi URL.', 'Menyimpan option Test Hub.', 'Merender settings terbaru.']
                },
                cbt_repair_stuck_flow_check_jobs: {
                    cap: 92,
                    interval: 430,
                    steps: ['Memindai job aktif.', 'Mengecek heartbeat dan PID.', 'Merekonsiliasi status stuck.', 'Memperbarui checklist.']
                },
                cbt_clear_test_artifacts: {
                    cap: 90,
                    interval: 420,
                    steps: ['Mengunci cleanup aman.', 'Menghapus folder artifact.', 'Menghitung ulang status artifact.', 'Merender panel artifact.']
                },
                cbt_cancel_flow_check_job: {
                    cap: 88,
                    interval: 360,
                    steps: ['Membaca job target.', 'Menandai cancel request.', 'Mengirim terminate bila proses aktif.', 'Memperbarui status task.']
                },
                cbt_retry_flow_check_job: {
                    cap: 90,
                    interval: 380,
                    steps: ['Membaca job terminal.', 'Membuat job retry baru.', 'Mengantrekan worker.', 'Memperbarui status task.']
                },
                cbt_clear_flow_check_job: {
                    cap: 88,
                    interval: 360,
                    steps: ['Memastikan job terminal.', 'Menghapus job dan artifact aman.', 'Membersihkan status task.']
                }
            };
            const profile = profiles[actionKey] || {
                cap: areaNames.indexOf('checklist') >= 0 ? 92 : 86,
                interval: 420,
                steps: ['Mengirim request lokal.', 'Memproses action admin.', 'Merender area terbaru.']
            };

            return {
                label: fallbackLabel,
                cap: profile.cap,
                interval: profile.interval,
                steps: profile.steps
            };
        };

        const startLoadingProgress = function (area, label, profile) {
            if (!(area instanceof HTMLElement)) {
                return;
            }

            const areaKey = getAreaKey(area);
            if (areaKey === '') {
                return;
            }

            const previous = loadingProgressStates.get(areaKey);
            if (previous && previous.timer) {
                window.clearInterval(previous.timer);
            }

            const steps = profile && Array.isArray(profile.steps) && profile.steps.length
                ? profile.steps
                : ['Memproses area lokal.'];
            const cap = profile && Number.isFinite(Number(profile.cap)) ? Number(profile.cap) : 90;
            const interval = profile && Number.isFinite(Number(profile.interval)) ? Number(profile.interval) : 420;
            const state = {
                percent: 6,
                cap: Math.max(25, Math.min(98, cap)),
                interval: Math.max(240, interval),
                steps: steps,
                startedAt: Date.now(),
                label: label || (profile && profile.label) || 'Memproses...',
                timer: null
            };
            loadingProgressStates.set(areaKey, state);
            updateLoadingProgress(area, state.percent, state.label, steps[0]);

            state.timer = window.setInterval(function () {
                const latestArea = getRefreshArea(areaKey) || area;
                const elapsed = Date.now() - state.startedAt;
                const stepIndex = Math.min(steps.length - 1, Math.floor(elapsed / Math.max(700, state.interval * 2)));
                const distance = state.cap - state.percent;
                const increment = Math.max(1, Math.min(7, Math.ceil(distance * 0.16)));
                state.percent = Math.min(state.cap, state.percent + increment);
                updateLoadingProgress(latestArea, state.percent, state.label, steps[stepIndex]);
            }, state.interval);
        };

        const stopLoadingProgress = function (area, succeeded) {
            if (!(area instanceof HTMLElement)) {
                return;
            }

            const areaKey = getAreaKey(area);
            const state = areaKey !== '' ? loadingProgressStates.get(areaKey) : null;
            if (state && state.timer) {
                window.clearInterval(state.timer);
            }
            if (state) {
                updateLoadingProgress(area, succeeded ? 100 : state.percent, state.label, succeeded ? 'Selesai memperbarui area.' : 'Proses berhenti sebelum selesai.');
                loadingProgressStates.delete(areaKey);
            }
        };

        const setAreaLoading = function (area, isLoading, label, profile) {
            if (!(area instanceof HTMLElement)) {
                return;
            }

            area.classList.toggle('is-loading', isLoading);
            area.setAttribute('aria-busy', isLoading ? 'true' : 'false');
            if (isLoading) {
                startLoadingProgress(area, label, profile);
            } else {
                stopLoadingProgress(area, true);
            }
        };

        const setAreasLoading = function (areaNames, isLoading, label, profile) {
            areaNames.forEach(function (areaName) {
                setAreaLoading(getRefreshArea(areaName), isLoading, label, profile);
            });
        };

        const setButtonLoading = function (button, isLoading, label) {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            if (isLoading) {
                if (!button.dataset.originalLabel) {
                    button.dataset.originalLabel = button.textContent || '';
                }
                button.textContent = label || 'Memproses...';
                button.classList.add('is-loading');
                button.disabled = true;
                return;
            }

            if (button.dataset.originalLabel) {
                button.textContent = button.dataset.originalLabel;
                delete button.dataset.originalLabel;
            }
            button.classList.remove('is-loading');
            button.disabled = false;
        };

        const buildErrorMessage = function (error) {
            const rawMessage = error && error.message ? String(error.message) : '';
            if (rawMessage === '') {
                return '';
            }

            return ' Detail: ' + rawMessage.replace(/\s+/g, ' ').slice(0, 220);
        };

        const showAreaFeedback = function (areaNames, message) {
            const targetArea = areaNames
                .map(getRefreshArea)
                .find(function (area) {
                    return area instanceof HTMLElement;
                });
            if (!targetArea) {
                return;
            }

            const existing = targetArea.querySelector('[data-cbt-test-hub-async-feedback]');
            if (existing) {
                existing.remove();
            }

            const feedback = document.createElement('p');
            feedback.className = 'cbt-test-hub-async-feedback';
            feedback.setAttribute('data-cbt-test-hub-async-feedback', '1');
            feedback.textContent = message;
            targetArea.prepend(feedback);
        };

        const syncUrl = function (tabName) {
            if (!window.history || !window.history.replaceState) {
                return;
            }

            const url = buildTestHubPageUrl();
            url.searchParams.set('page', 'cbt-test-hub');
            url.searchParams.set('cbt_unit_test_tab', tabName);
            url.searchParams.set('cbt_checklist_scope', currentChecklistScope);
            window.history.replaceState({}, '', url.toString());
        };

        const activateUnitTestTab = function (tabName, shouldSyncUrl) {
            const unitTestTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-unit-test-tab]'));
            const unitTestTabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-unit-test-panel]'));
            if (!unitTestTabButtons.length || !unitTestTabPanels.length) {
                return;
            }

            const hasExactMatch = unitTestTabButtons.some(function (button) {
                return button.getAttribute('data-unit-test-tab') === tabName;
            });
            const nextTabName = hasExactMatch
                ? tabName
                : String(unitTestTabButtons[0].getAttribute('data-unit-test-tab') || defaultUnitTestTab || 'recovery_persistence');
            currentUnitTestTab = nextTabName;

            unitTestTabButtons.forEach(function (button) {
                const active = button.getAttribute('data-unit-test-tab') === nextTabName;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            unitTestTabPanels.forEach(function (panel) {
                const active = panel.getAttribute('data-unit-test-panel') === nextTabName;
                panel.classList.toggle('is-active', active);
            });

            if (shouldSyncUrl) {
                syncUrl(nextTabName);
            }
        };

        const bindUnitTestTabs = function () {
            document.querySelectorAll('[data-unit-test-tab]').forEach(function (button) {
                if (button.dataset.testHubBound === '1') {
                    return;
                }
                button.dataset.testHubBound = '1';
                button.addEventListener('click', function () {
                    activateUnitTestTab(button.getAttribute('data-unit-test-tab') || defaultUnitTestTab, true);
                });
            });
        };

        const bindChecklistTabs = function () {
            document.querySelectorAll('[data-checklist-tabscope]').forEach(function (scope) {
                if (scope.dataset.testHubChecklistBound === '1') {
                    return;
                }
                scope.dataset.testHubChecklistBound = '1';

                const buttons = Array.prototype.slice.call(scope.querySelectorAll('[data-checklist-tab]'));
                const panels = Array.prototype.slice.call(scope.querySelectorAll('[data-checklist-panel]'));
                const subpanel = scope.closest('[data-unit-test-panel]');
                const runnerForm = subpanel ? subpanel.querySelector('[data-runner-form]') : null;
                const runnerButton = runnerForm ? runnerForm.querySelector('[data-checklist-run-button]') : null;
                const runnerHelp = runnerForm ? runnerForm.querySelector('[data-checklist-run-help]') : null;
                const runnerScopeInput = runnerForm ? runnerForm.querySelector('[data-checklist-scope-input]') : null;
                const runnerActionInput = runnerForm ? runnerForm.querySelector('[data-checklist-action-input]') : null;

                if (!buttons.length || !panels.length) {
                    return;
                }

                const syncChecklistScopeUrl = function (scopeName) {
                    if (!window.history || !window.history.replaceState) {
                        return;
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.set('page', 'cbt-test-hub');
                    url.searchParams.set('cbt_unit_test_tab', currentUnitTestTab);
                    url.searchParams.set('cbt_checklist_scope', scopeName);
                    window.history.replaceState({}, '', url.toString());
                };

                const applyRunnerState = function (scopeName) {
                    if (!runnerForm || !runnerButton || !runnerHelp || !runnerScopeInput) {
                        return;
                    }

                    const scopeKey = scopeName === 'smoke_tests' ? 'smoke' : 'unit';
                    const label = runnerForm.getAttribute('data-runner-label-' + scopeKey) || 'Run Tests';
                    const description = runnerForm.getAttribute('data-runner-description-' + scopeKey) || '';
                    const reason = runnerForm.getAttribute('data-runner-reason-' + scopeKey) || '';
                    const available = runnerForm.getAttribute('data-runner-available-' + scopeKey) === '1';
                    const action = runnerForm.getAttribute('data-runner-action-' + scopeKey) || 'cbt_run_unit_test_suite';

                    if (runnerScopeInput) {
                        runnerScopeInput.value = scopeName;
                    }
                    if (runnerActionInput) {
                        runnerActionInput.value = action;
                    }
                    if (runnerForm && subpanel) {
                        const panelAreaName = subpanel.getAttribute('data-cbt-test-hub-refresh-area') || 'checklist';
                        runnerForm.setAttribute(
                            'data-refresh-areas',
                            scopeName === 'smoke_tests' ? 'banners,artifacts,' + panelAreaName : 'banners,' + panelAreaName
                        );
                        runnerForm.setAttribute('data-loading-label', scopeName === 'smoke_tests' ? 'Queueing...' : 'Menjalankan...');
                    }
                    if (runnerButton) {
                        runnerButton.textContent = label;
                        runnerButton.disabled = !available;
                    }
                    if (runnerHelp) {
                        runnerHelp.textContent = available ? description : reason;
                    }
                    currentChecklistScope = scopeName;

                    document.querySelectorAll('[data-global-checklist-scope-input]').forEach(function (input) {
                        input.value = scopeName;
                    });
                };

                const activateChecklistTab = function (tabName) {
                    const hasExactMatch = buttons.some(function (button) {
                        return button.getAttribute('data-checklist-tab') === tabName;
                    });
                    const nextTabName = hasExactMatch
                        ? tabName
                        : String(buttons[0].getAttribute('data-checklist-tab') || 'unit_tests');

                    buttons.forEach(function (button) {
                        const active = button.getAttribute('data-checklist-tab') === nextTabName;
                        button.classList.toggle('is-active', active);
                        button.setAttribute('aria-selected', active ? 'true' : 'false');
                    });

                    panels.forEach(function (panel) {
                        const active = panel.getAttribute('data-checklist-panel') === nextTabName;
                        panel.classList.toggle('is-active', active);
                    });

                    applyRunnerState(nextTabName);
                    if (subpanel && subpanel.classList.contains('is-active')) {
                        syncChecklistScopeUrl(nextTabName);
                    }
                };

                buttons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        activateChecklistTab(button.getAttribute('data-checklist-tab') || 'unit_tests');
                    });
                });

                activateChecklistTab(currentChecklistScope);
            });
        };

        const bindBannerClose = function () {
            document.querySelectorAll('[data-test-hub-banner-close]').forEach(function (button) {
                if (button.dataset.testHubBannerBound === '1') {
                    return;
                }
                button.dataset.testHubBannerBound = '1';
                button.addEventListener('click', function () {
                    const banner = button.closest('[data-test-hub-banner]');
                    if (banner) {
                        banner.remove();
                    }
                });
            });
        };

        const bindItemRunButtons = function () {
            document.querySelectorAll('[data-item-run-form], [data-item-run-button]').forEach(function (element) {
                if (element.dataset.testHubStopBound === '1') {
                    return;
                }
                element.dataset.testHubStopBound = '1';
                ['click', 'mousedown', 'pointerdown', 'touchstart', 'submit'].forEach(function (eventName) {
                    element.addEventListener(eventName, function (event) {
                        event.stopPropagation();
                    });
                });
            });

            document.querySelectorAll('[data-item-run-button]').forEach(function (button) {
                if (button.dataset.testHubRunButtonBound === '1') {
                    return;
                }
                button.dataset.testHubRunButtonBound = '1';
                button.addEventListener('click', function (event) {
                    if (event.defaultPrevented) {
                        return;
                    }

                    const targetButton = event.currentTarget;
                    if (!(targetButton instanceof HTMLButtonElement) || targetButton.disabled) {
                        return;
                    }

                    const formId = String(targetButton.getAttribute('data-item-run-form-id') || '').trim();
                    const formFromId = formId !== '' ? document.getElementById(formId) : null;
                    const parentForm = formFromId instanceof HTMLFormElement
                        ? formFromId
                        : targetButton.closest('form');

                    if (!(parentForm instanceof HTMLFormElement)) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    if (typeof parentForm.requestSubmit === 'function') {
                        parentForm.requestSubmit(targetButton);
                        return;
                    }

                    parentForm.submit();
                });
            });
        };

        const updateActiveFlowState = function (doc) {
            const nextRoot = doc.querySelector('[data-test-hub-root]');
            if (!nextRoot) {
                return;
            }
            hasActiveFlowJobs = nextRoot.getAttribute('data-has-active-flow-jobs') === '1';
            hasActiveGlobalUnitRun = nextRoot.getAttribute('data-has-active-global-unit-run') === '1';
            if (root) {
                root.setAttribute('data-has-active-flow-jobs', hasActiveFlowJobs ? '1' : '0');
                root.setAttribute('data-has-active-global-unit-run', hasActiveGlobalUnitRun ? '1' : '0');
            }
        };

        const replaceAreasFromHtml = function (html, areaNames) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            if (!doc.querySelector('[data-test-hub-root]')) {
                throw new Error('Response bukan halaman CBT Test Hub. Sesi admin mungkin expired atau server mengembalikan halaman error.');
            }
            updateActiveFlowState(doc);

            areaNames.forEach(function (areaName) {
                const currentArea = getRefreshArea(areaName);
                const nextArea = doc.querySelector('[data-cbt-test-hub-refresh-area="' + areaName + '"]');
                if (!currentArea) {
                    throw new Error('Area aktif tidak ditemukan: ' + areaName);
                }
                if (!nextArea) {
                    throw new Error('Area response tidak ditemukan: ' + areaName);
                }

                currentArea.replaceWith(document.importNode(nextArea, true));
            });

            bindAll();
            activateUnitTestTab(currentUnitTestTab, false);
            scheduleScopedRefresh();
            scheduleGlobalUnitRunStep();
        };

        const extractResponseError = function (response, body) {
            const text = String(body || '')
                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .slice(0, 160);
            return 'HTTP ' + response.status + (text !== '' ? ': ' + text : '');
        };

        const requestPageHtml = function (url, options) {
            return window.fetch(url, Object.assign({
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }, options || {})).then(function (response) {
                return response.text().then(function (body) {
                    if (!response.ok) {
                        throw new Error(extractResponseError(response, body));
                    }

                    return body;
                });
            });
        };

        const bindAsyncForms = function () {
            document.querySelectorAll('[data-cbt-test-hub-async-form]').forEach(function (form) {
                if (form.dataset.testHubAsyncBound === '1') {
                    return;
                }
                form.dataset.testHubAsyncBound = '1';
                form.addEventListener('submit', function (event) {
                    if (event.defaultPrevented) {
                        return;
                    }
                    if (!window.fetch || !window.FormData || !window.DOMParser) {
                        return;
                    }

                    event.preventDefault();
                    if (testHubLocalActionInFlight || form.dataset.testHubAsyncInFlight === '1') {
                        return;
                    }
                    testHubRefreshRequestSeq += 1;
                    const requestSeq = testHubRefreshRequestSeq;
                    testHubLocalActionInFlight = true;
                    form.dataset.testHubAsyncInFlight = '1';
                    const areaNames = parseAreaNames(form.getAttribute('data-refresh-areas'));
                    const loadingLabel = form.getAttribute('data-loading-label') || 'Memproses...';
                    const submitter = event.submitter instanceof HTMLButtonElement
                        ? event.submitter
                        : form.querySelector('button[type="submit"]');
                    const formData = new FormData(form);
                    formData.set('cbt_test_hub_async', '1');
                    const asyncUrl = canonicalAsyncUrl || form.action;
                    const actionName = formData.get('action');
                    const progressProfile = resolveProgressProfile(
                        typeof actionName === 'string' ? actionName : '',
                        loadingLabel,
                        areaNames
                    );

                    if (scopedRefreshTimer) {
                        window.clearTimeout(scopedRefreshTimer);
                        scopedRefreshTimer = null;
                    }

                    setAreasLoading(areaNames, true, loadingLabel, progressProfile);
                    setButtonLoading(submitter, true, loadingLabel);

                    requestPageHtml(asyncUrl, {
                        method: 'POST',
                        body: formData
                    }).then(function (html) {
                        if (requestSeq !== testHubRefreshRequestSeq) {
                            return;
                        }
                        replaceAreasFromHtml(html, areaNames);
                    }).catch(function (error) {
                        if (requestSeq !== testHubRefreshRequestSeq) {
                            return;
                        }
                        if (window.console && typeof window.console.error === 'function') {
                            window.console.error('CBT Test Hub async update failed', error);
                        }
                        showAreaFeedback(areaNames, 'Gagal memperbarui area ini tanpa reload global.' + buildErrorMessage(error));
                    }).finally(function () {
                        if (requestSeq === testHubRefreshRequestSeq) {
                            testHubLocalActionInFlight = false;
                            setButtonLoading(submitter, false);
                            setAreasLoading(areaNames, false);
                        }
                        delete form.dataset.testHubAsyncInFlight;
                    });
                });
            });
        };

        const refreshScopedFlowAreas = function () {
            if (!hasActiveFlowJobs || scopedRefreshInFlight || testHubLocalActionInFlight || !window.fetch || !window.DOMParser) {
                return;
            }

            scopedRefreshInFlight = true;
            testHubRefreshRequestSeq += 1;
            const requestSeq = testHubRefreshRequestSeq;
            const areaNames = ['artifacts', 'checklist'];
            setAreasLoading(areaNames, true, 'Memperbarui flow check...', {
                label: 'Memperbarui flow check...',
                cap: 86,
                interval: 360,
                steps: ['Mengambil snapshot job terbaru.', 'Membaca status queued/running.', 'Menghitung progress flow.', 'Merender checklist lokal.']
            });

            const url = buildTestHubPageUrl();
            url.searchParams.set('page', 'cbt-test-hub');
            url.searchParams.set('cbt_unit_test_tab', currentUnitTestTab);
            url.searchParams.set('cbt_checklist_scope', currentChecklistScope);

            requestPageHtml(url.toString()).then(function (html) {
                if (requestSeq !== testHubRefreshRequestSeq) {
                    return;
                }
                replaceAreasFromHtml(html, areaNames);
            }).catch(function (error) {
                if (requestSeq !== testHubRefreshRequestSeq) {
                    return;
                }
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('CBT Test Hub scoped auto-refresh failed', error);
                }
                showAreaFeedback(['checklist'], 'Auto-refresh area Flow Check gagal. Halaman tidak direload agar input lain tetap aman.' + buildErrorMessage(error));
            }).finally(function () {
                scopedRefreshInFlight = false;
                if (requestSeq === testHubRefreshRequestSeq) {
                    setAreasLoading(areaNames, false);
                }
                if (requestSeq === testHubRefreshRequestSeq && hasActiveFlowJobs) {
                    scheduleScopedRefresh();
                }
            });
        };

        function scheduleScopedRefresh() {
            if (scopedRefreshTimer) {
                window.clearTimeout(scopedRefreshTimer);
                scopedRefreshTimer = null;
            }
            if (!hasActiveFlowJobs) {
                return;
            }
            scopedRefreshTimer = window.setTimeout(refreshScopedFlowAreas, 5000);
        }

        const runNextGlobalUnitStep = function () {
            if (!hasActiveGlobalUnitRun || globalUnitRunInFlight || testHubLocalActionInFlight || !window.fetch || !window.FormData || !window.DOMParser) {
                return;
            }

            const form = document.querySelector('[data-cbt-test-hub-refresh-area="global-unit-run"] form[data-cbt-test-hub-async-form]');
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            globalUnitRunInFlight = true;
            testHubRefreshRequestSeq += 1;
            const requestSeq = testHubRefreshRequestSeq;
            const areaNames = parseAreaNames(form.getAttribute('data-refresh-areas'));
            const formData = new FormData(form);
            formData.set('cbt_test_hub_async', '1');
            const progressProfile = resolveProgressProfile('cbt_run_all_unit_tests', 'Menjalankan...', areaNames);

            setAreasLoading(areaNames, true, 'Menjalankan...', progressProfile);

            requestPageHtml(canonicalAsyncUrl || form.action, {
                method: 'POST',
                body: formData
            }).then(function (html) {
                if (requestSeq !== testHubRefreshRequestSeq) {
                    return;
                }
                replaceAreasFromHtml(html, areaNames);
            }).catch(function (error) {
                if (requestSeq !== testHubRefreshRequestSeq) {
                    return;
                }
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('CBT Test Hub global unit step failed', error);
                }
                showAreaFeedback(['global-unit-run'], 'Run All Unit Tests berhenti sementara tanpa reload global.' + buildErrorMessage(error));
                hasActiveGlobalUnitRun = false;
                if (root) {
                    root.setAttribute('data-has-active-global-unit-run', '0');
                }
            }).finally(function () {
                globalUnitRunInFlight = false;
                if (requestSeq === testHubRefreshRequestSeq) {
                    setAreasLoading(areaNames, false);
                }
                if (requestSeq === testHubRefreshRequestSeq && hasActiveGlobalUnitRun) {
                    scheduleGlobalUnitRunStep();
                }
            });
        };

        function scheduleGlobalUnitRunStep() {
            if (globalUnitRunTimer) {
                window.clearTimeout(globalUnitRunTimer);
                globalUnitRunTimer = null;
            }
            if (!hasActiveGlobalUnitRun) {
                return;
            }
            globalUnitRunTimer = window.setTimeout(runNextGlobalUnitStep, 650);
        }

        function bindAll() {
            bindUnitTestTabs();
            bindChecklistTabs();
            bindCollapsibleHealthPanels();
            bindUnitTestInventoryPagination();
            bindBannerClose();
            bindItemRunButtons();
            bindAsyncForms();
        }

        bindAll();
        activateUnitTestTab(defaultUnitTestTab, false);
        scheduleScopedRefresh();
        scheduleGlobalUnitRunStep();
    }());
</script>
