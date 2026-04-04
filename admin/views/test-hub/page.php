<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap cbt-test-hub-page">
    <?php
    $global_unit_run_summary = isset($global_unit_run_summary) && is_array($global_unit_run_summary) ? $global_unit_run_summary : [];
    $global_unit_run_result = isset($global_unit_run_result) && is_array($global_unit_run_result) ? $global_unit_run_result : [];
    $global_unit_run_available = !empty($global_unit_run_available);
    $global_passed_count = (int) ($global_unit_run_summary['passed_count'] ?? 0);
    $global_failed_count = (int) ($global_unit_run_summary['failed_count'] ?? 0);
    $global_total_count = (int) ($global_unit_run_summary['total_count'] ?? 0);
    $global_executed_at = (int) ($global_unit_run_summary['executed_at'] ?? 0);
    $test_artifact_cleanup = isset($test_artifact_cleanup) && is_array($test_artifact_cleanup) ? $test_artifact_cleanup : [];
    $test_artifact_cleanup_targets = isset($test_artifact_cleanup['targets']) && is_array($test_artifact_cleanup['targets']) ? (array) $test_artifact_cleanup['targets'] : [];
    $test_artifact_existing_count = (int) ($test_artifact_cleanup['existing_count'] ?? 0);
    $test_artifact_has_existing = !empty($test_artifact_cleanup['has_existing']);
    $test_artifact_has_active_jobs = !empty($test_artifact_cleanup['has_active_jobs']);
    ?>
    <style>
        .cbt-test-hub-page {
            margin: 20px 20px 0 0;
        }
        .cbt-test-hub-shell {
            display: grid;
            gap: 18px;
        }
        .cbt-test-hub-hero {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 20px 22px;
            border-radius: 24px;
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%);
            color: #fff;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
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
            color: rgba(255, 255, 255, 0.76);
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
            background: rgba(255, 255, 255, 0.12);
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
            color: rgba(255, 255, 255, 0.76);
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
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
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
            background: rgba(15, 23, 42, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .cbt-test-hub-global-summary-stat span {
            display: block;
            margin-bottom: 4px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-global-summary-stat strong {
            display: block;
            color: #fff;
            font-size: 18px;
            line-height: 1;
        }
        .cbt-test-hub-global-summary-foot {
            color: rgba(255, 255, 255, 0.82);
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
            color: #fff;
            font-size: 26px;
            line-height: 1.15;
        }
        .cbt-test-hub-hero p {
            margin: 0;
            max-width: 760px;
            color: rgba(255, 255, 255, 0.88);
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
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(8px);
        }
        .cbt-test-hub-stat span {
            display: block;
            margin-bottom: 6px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-test-hub-stat strong {
            display: block;
            color: #fff;
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
            border-radius: 24px;
            background: #fff;
            border: 1px solid #dbe5ef;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            padding: 22px;
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
                    <div class="cbt-test-hub-hero-unit-runbar">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-hero-unit-run-form">
                            <input type="hidden" name="action" value="cbt_run_all_unit_tests">
                            <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
                            <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>">
                            <?php wp_nonce_field('cbt_run_all_unit_tests'); ?>
                            <button type="submit" class="button cbt-test-hub-hero-unit-run-button">Run All Unit Tests</button>
                            <div class="cbt-test-hub-hero-unit-run-help">Menjalankan semua runner `unit_tests` lintas subsystem secara sinkron. Flow check tidak ikut dijalankan.</div>
                        </form>
                        <div class="cbt-test-hub-global-summary">
                            <div class="cbt-test-hub-global-summary-head">
                                <span class="cbt-test-hub-global-summary-title">Ringkasan Unit Global</span>
                                <span class="cbt-test-hub-global-summary-status">
                                    <?php echo $global_unit_run_available ? esc_html(!empty($global_unit_run_summary['success']) ? 'All Passed' : 'Needs Review') : 'Idle'; ?>
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
                                <?php if ($global_unit_run_available && $global_executed_at > 0): ?>
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

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-settings">
                <input type="hidden" name="action" value="cbt_save_test_hub_settings">
                <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
                <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>" data-global-checklist-scope-input>
                <?php wp_nonce_field('cbt_save_test_hub_settings'); ?>

                <div class="cbt-test-hub-settings-head">
                    <div>
                        <h3>Playwright E2E Settings</h3>
                        <p>Simpan target environment sekali di sini. Runner `Playwright Recovery Flow` akan mengirim nilai ini sebagai `CBT_E2E_BASE_URL` saat dijalankan dari admin, lalu flow recovery akan mulai dari root `/` frontend.</p>
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
                        <div class="cbt-test-hub-settings-help">Base URL untuk Playwright. Contoh: `http://localhost/wordpress`.</div>
                    </div>
                </div>

                <div class="cbt-test-hub-settings-actions">
                    <p>Setelah disimpan, Anda tidak perlu lagi `export` manual sebelum menjalankan runner Playwright dari `CBT Test Hub`.</p>
                    <div class="cbt-test-hub-settings-actions-group">
                        <button type="submit" class="button button-secondary">Simpan Playwright Settings</button>
                    </div>
                </div>
            </form>

            <div class="cbt-test-hub-artifact-box">
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
                            Cleanup sementara dikunci karena masih ada flow check background yang queued atau running.
                        <?php elseif ($test_artifact_has_existing): ?>
                            Saat ini ada <?php echo esc_html((string) $test_artifact_existing_count); ?> target artefak yang bisa dibersihkan dari halaman ini.
                        <?php else: ?>
                            Belum ada artefak test yang perlu dibersihkan.
                        <?php endif; ?>
                    </p>
                    <div class="cbt-test-hub-settings-actions-group">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return window.confirm('Bersihkan artefak test generated dari repo ini sekarang?');">
                            <input type="hidden" name="action" value="cbt_clear_test_artifacts">
                            <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $active_unit_test_tab); ?>">
                            <input type="hidden" name="cbt_checklist_scope" value="<?php echo esc_attr((string) ($active_checklist_scope ?? 'unit_tests')); ?>">
                            <?php wp_nonce_field('cbt_clear_test_artifacts'); ?>
                            <button type="submit" class="button cbt-test-hub-danger-button" <?php disabled($test_artifact_has_active_jobs || !$test_artifact_has_existing); ?>>Bersihkan Artefak Test</button>
                        </form>
                    </div>
                </div>
            </div>

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
                    <section
                        class="cbt-test-hub-subpanel<?php echo $active_unit_test_tab === $unit_test_tab_key ? ' is-active' : ''; ?>"
                        data-unit-test-panel="<?php echo esc_attr($unit_test_tab_key); ?>"
                        role="tabpanel"
                    >
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
                            'passed' => 0,
                            'failed' => 0,
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
                        $flow_has_active_jobs = ((int) $flow_status_counts['queued'] + (int) $flow_status_counts['running']) > 0;
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
                                        data-runner-form
                                        data-runner-label-unit="<?php echo esc_attr((string) (($unit_test_runners['unit_tests']['label'] ?? 'Run Checklist Unit'))); ?>"
                                        data-runner-description-unit="<?php echo esc_attr((string) (($unit_test_runners['unit_tests']['description'] ?? ''))); ?>"
                                        data-runner-available-unit="<?php echo !empty($unit_test_runners['unit_tests']['available']) ? '1' : '0'; ?>"
                                        data-runner-reason-unit="<?php echo esc_attr((string) (($unit_test_runners['unit_tests']['reason'] ?? ''))); ?>"
                                        data-runner-label-smoke="<?php echo esc_attr((string) (($unit_test_runners['smoke_tests']['label'] ?? 'Run Checklist Flow Check'))); ?>"
                                        data-runner-description-smoke="<?php echo esc_attr((string) (($unit_test_runners['smoke_tests']['description'] ?? ''))); ?>"
                                        data-runner-available-smoke="<?php echo !empty($unit_test_runners['smoke_tests']['available']) ? '1' : '0'; ?>"
                                        data-runner-reason-smoke="<?php echo esc_attr((string) (($unit_test_runners['smoke_tests']['reason'] ?? ''))); ?>"
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
                                            <?php disabled(empty($active_scope_runner['available'])); ?>
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
                                        <?php $unit_item_run_form_id = 'cbt-test-hub-item-run-form-' . sanitize_key((string) $unit_test_tab_key . '-unit-' . (string) ($unit_test_item['item_index'] ?? '')); ?>
                                        <li>
                                            <?php if (!empty($unit_test_item['has_runner'])): ?>
                                                <form id="<?php echo esc_attr($unit_item_run_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-item-run-form-summary" data-item-run-form hidden>
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
                                                                    onclick="event.preventDefault(); event.stopPropagation(); var form = document.getElementById('<?php echo esc_js($unit_item_run_form_id); ?>'); if (form) { if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); } } return false;"
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
                                        echo esc_html(
                                            (int) $flow_status_counts['queued'] . ' queued, '
                                            . (int) $flow_status_counts['running'] . ' running, '
                                            . (int) $flow_status_counts['passed'] . ' passed, '
                                            . (int) $flow_status_counts['failed'] . ' failed.'
                                        );
                                        ?>
                                        <?php if ($flow_has_active_jobs): ?>
                                            Halaman ini akan refresh otomatis selama masih ada job aktif.
                                        <?php endif; ?>
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
                                        <?php $flow_item_run_form_id = 'cbt-test-hub-item-run-form-' . sanitize_key((string) $unit_test_tab_key . '-smoke-' . (string) ($unit_test_item['item_index'] ?? '')); ?>
                                        <li>
                                            <?php if (!empty($unit_test_item['has_runner'])): ?>
                                                <form id="<?php echo esc_attr($flow_item_run_form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-test-hub-item-run-form-summary" data-item-run-form hidden>
                                                    <input type="hidden" name="action" value="cbt_queue_flow_check_job">
                                                    <input type="hidden" name="cbt_unit_test_tab" value="<?php echo esc_attr((string) $unit_test_tab_key); ?>">
                                                    <input type="hidden" name="cbt_checklist_scope" value="smoke_tests">
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
                                                                    data-item-run-form-id="<?php echo esc_attr($flow_item_run_form_id); ?>"
                                                                    form="<?php echo esc_attr($flow_item_run_form_id); ?>"
                                                                    onclick="event.preventDefault(); event.stopPropagation(); var form = document.getElementById('<?php echo esc_js($flow_item_run_form_id); ?>'); if (form) { if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); } } return false;"
                                                                    <?php disabled(!$flow_runner_available || empty($unit_test_item['can_run_task'])); ?>
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
                                                                <div class="cbt-test-hub-item-meta-empty">Flow check ini belum ditautkan ke evidence khusus. Gunakan proses verifikasi sebagai panduan eksekusi.</div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="cbt-test-hub-item-meta-block cbt-test-hub-item-meta-block--wide">
                                                            <h5>Hasil Runner Terbaru</h5>
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
        </section>
    </div>
</div>
<script>
    (function () {
        const unitTestTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-unit-test-tab]'));
        const unitTestTabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-unit-test-panel]'));
        const defaultUnitTestTab = <?php echo wp_json_encode($active_unit_test_tab); ?> || 'recovery_persistence';
        const defaultChecklistScope = <?php echo wp_json_encode($active_checklist_scope ?? 'unit_tests'); ?> || 'unit_tests';
        const hasActiveFlowJobs = <?php echo !empty($has_active_flow_jobs) ? 'true' : 'false'; ?>;
        let currentUnitTestTab = defaultUnitTestTab;
        let currentChecklistScope = defaultChecklistScope;

        const syncUrl = function (tabName) {
            if (!window.history || !window.history.replaceState) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('page', 'cbt-test-hub');
            url.searchParams.set('cbt_unit_test_tab', tabName);
            url.searchParams.set('cbt_checklist_scope', currentChecklistScope);
            window.history.replaceState({}, '', url.toString());
        };

        const activateUnitTestTab = function (tabName, shouldSyncUrl) {
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

        unitTestTabButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activateUnitTestTab(button.getAttribute('data-unit-test-tab') || defaultUnitTestTab, true);
            });
        });

        document.querySelectorAll('[data-checklist-tabscope]').forEach(function (scope) {
            const buttons = Array.prototype.slice.call(scope.querySelectorAll('[data-checklist-tab]'));
            const panels = Array.prototype.slice.call(scope.querySelectorAll('[data-checklist-panel]'));
            const subpanel = scope.closest('[data-unit-test-panel]');
            const runnerForm = subpanel ? subpanel.querySelector('[data-runner-form]') : null;
            const runnerButton = runnerForm ? runnerForm.querySelector('[data-checklist-run-button]') : null;
            const runnerHelp = runnerForm ? runnerForm.querySelector('[data-checklist-run-help]') : null;
            const runnerScopeInput = runnerForm ? runnerForm.querySelector('[data-checklist-scope-input]') : null;
            const runnerActionInput = runnerForm ? runnerForm.querySelector('[data-checklist-action-input]') : null;
            const globalScopeInputs = Array.prototype.slice.call(document.querySelectorAll('[data-global-checklist-scope-input]'));

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
                if (runnerButton) {
                    runnerButton.textContent = label;
                    runnerButton.disabled = !available;
                }
                if (runnerHelp) {
                    runnerHelp.textContent = available ? description : reason;
                }
                currentChecklistScope = scopeName;

                globalScopeInputs.forEach(function (input) {
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

            activateChecklistTab(defaultChecklistScope);
        });

        document.querySelectorAll('[data-test-hub-banner-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                const banner = button.closest('[data-test-hub-banner]');
                if (banner) {
                    banner.remove();
                }
            });
        });

        document.querySelectorAll('[data-item-run-form], [data-item-run-button]').forEach(function (element) {
            ['click', 'mousedown', 'pointerdown', 'touchstart', 'submit'].forEach(function (eventName) {
                element.addEventListener(eventName, function (event) {
                    event.stopPropagation();
                });
            });
        });

        document.querySelectorAll('[data-item-run-button]').forEach(function (button) {
            button.addEventListener('click', function (event) {
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

        if (hasActiveFlowJobs) {
            window.setTimeout(function () {
                window.location.reload();
            }, 5000);
        }

        activateUnitTestTab(defaultUnitTestTab, false);
    }());
</script>
