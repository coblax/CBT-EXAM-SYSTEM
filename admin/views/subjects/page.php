<?php
if (!defined('ABSPATH')) {
    exit;
}

$subject_usage_map = isset($subject_usage_map) && is_array($subject_usage_map) ? $subject_usage_map : [];
$subject_search = isset($subject_search) ? (string) $subject_search : '';
$subjects_in_use_total = isset($subjects_in_use_total) ? max(0, (int) $subjects_in_use_total) : 0;
$subject_import_failures = isset($subject_import_failures) && is_array($subject_import_failures) ? $subject_import_failures : [];
$subject_import_result = isset($subject_import_result) && is_array($subject_import_result) ? $subject_import_result : null;
$subject_open_form_url = isset($subject_open_form_url) ? (string) $subject_open_form_url : admin_url('admin.php?page=cbt-subjects&cbt_subject_tab=form');
$editing_usage = isset($editing_usage) && is_array($editing_usage) ? $editing_usage : null;
$subject_unused_total = max(0, (int) $total_subjects - $subjects_in_use_total);
$subject_has_search = $subject_search !== '';
?>
        <style>
            .cbt-subject-page {
                --cbt-subject-primary: #2563eb;
                --cbt-subject-primary-soft: #eff6ff;
                --cbt-subject-text: #0f172a;
                --cbt-subject-muted: #64748b;
                --cbt-subject-border: #e2e8f0;
                --cbt-subject-danger: #be123c;
                --cbt-subject-radius-lg: 24px;
                --cbt-subject-radius-md: 18px;
                --cbt-subject-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.05), 0 4px 6px -2px rgba(15, 23, 42, 0.03);
                max-width: 1180px;
                color: var(--cbt-subject-text);
                animation: cbtSubjectSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            }
            @keyframes cbtSubjectSlideUp {
                0% { opacity: 0; transform: translateY(12px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .cbt-subject-page,
                .cbt-subject-page * {
                    animation: none !important;
                    transition: none !important;
                }
            }
            .cbt-subject-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            }
            .cbt-subject-hero {
                position: relative;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 24px;
                padding: 28px;
                border: 1px solid rgba(255, 255, 255, 0.6);
                border-radius: var(--cbt-subject-radius-lg);
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
                box-shadow: var(--cbt-subject-shadow), 0 0 20px rgba(59, 130, 246, 0.12);
            }
            .cbt-subject-hero::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 5px;
                background: linear-gradient(90deg, #3b82f6, #0ea5e9, #8b5cf6);
            }
            .cbt-subject-hero-copy {
                flex: 1;
                min-width: 0;
            }
            .cbt-subject-kicker {
                display: inline-flex;
                align-items: center;
                min-height: 26px;
                padding: 0 12px;
                border-radius: 999px;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: #ffffff;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.1em;
                text-transform: uppercase;
            }
            .cbt-subject-hero h1 {
                margin: 12px 0 8px;
                padding: 0;
                font-size: 30px;
                line-height: 1.15;
            }
            .cbt-subject-hero p {
                margin: 0;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-subject-overview {
                display: grid;
                gap: 8px;
                min-width: 250px;
                padding: 16px;
                border: 1px solid var(--cbt-subject-border);
                border-radius: var(--cbt-subject-radius-md);
                background: rgba(255, 255, 255, 0.7);
                flex-shrink: 0;
            }
            .cbt-subject-pill {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 8px 12px;
                border-radius: 10px;
                background: #ffffff;
                color: var(--cbt-subject-muted);
                font-size: 13px;
                font-weight: 600;
            }
            .cbt-subject-pill strong {
                color: var(--cbt-subject-text);
                font-size: 15px;
            }
            .cbt-subject-page .wp-header-end,
            .cbt-subject-notices:empty {
                display: none;
            }
            .cbt-subject-notices .notice {
                margin: 0 0 8px;
            }
            .cbt-subject-tabs {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-subject-tab {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 46px;
                padding: 0 20px;
                border: 2px solid var(--cbt-subject-border);
                border-radius: 14px;
                background: #ffffff;
                color: var(--cbt-subject-muted);
                font-size: 14px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .cbt-subject-tab:hover,
            .cbt-subject-tab:focus-visible {
                border-color: #cbd5e1;
                background: #f8fafc;
                color: var(--cbt-subject-text);
                outline: none;
            }
            .cbt-subject-tab.is-active {
                border-color: #3b82f6;
                background: #3b82f6;
                color: #ffffff;
                box-shadow: 0 8px 16px rgba(59, 130, 246, 0.25);
            }
            .cbt-subject-panel {
                display: none;
                min-width: 0;
                padding: 32px 36px;
                border: 1px solid rgba(255, 255, 255, 0.6);
                border-radius: var(--cbt-subject-radius-md);
                background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
                box-shadow: var(--cbt-subject-shadow);
                overflow-wrap: break-word;
            }
            .cbt-subject-panel.is-active {
                display: block;
            }
            .cbt-subject-panel[data-cbt-subject-panel="list"].is-loading .cbt-subject-table-wrap {
                opacity: 0.55;
                transition: opacity 0.18s ease;
            }
            .cbt-subject-progress {
                display: none;
                gap: 10px;
                padding: 14px 16px;
                border: 1px solid #bfdbfe;
                border-radius: var(--cbt-subject-radius-md);
                background: linear-gradient(135deg, rgba(239, 246, 255, 0.97), rgba(240, 253, 250, 0.92));
                box-shadow: 0 14px 30px rgba(37, 99, 235, 0.12);
            }
            .cbt-subject-progress.is-active {
                display: grid;
            }
            .cbt-subject-progress.is-error {
                border-color: #fecaca;
                background: linear-gradient(135deg, rgba(254, 242, 242, 0.98), rgba(255, 247, 237, 0.94));
                box-shadow: 0 14px 30px rgba(239, 68, 68, 0.1);
            }
            .cbt-subject-progress-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
            }
            .cbt-subject-progress-title {
                color: var(--cbt-subject-text);
                font-size: 14px;
                line-height: 1.25;
            }
            .cbt-subject-progress-percent {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 54px;
                min-height: 30px;
                padding: 0 10px;
                border: 1px solid #bfdbfe;
                border-radius: 999px;
                background: #ffffff;
                color: #1d4ed8;
                font-size: 12px;
                font-weight: 800;
            }
            .cbt-subject-progress.is-error .cbt-subject-progress-percent {
                color: #b91c1c;
                border-color: #fecaca;
            }
            .cbt-subject-progress-track {
                height: 9px;
                overflow: hidden;
                border-radius: 999px;
                background: rgba(148, 163, 184, 0.22);
            }
            .cbt-subject-progress-fill {
                display: block;
                width: var(--cbt-subject-progress, 0%);
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb 0%, #06b6d4 54%, #10b981 100%);
                transition: width 0.24s ease;
            }
            .cbt-subject-progress.is-error .cbt-subject-progress-fill {
                background: linear-gradient(90deg, #ef4444 0%, #f97316 100%);
            }
            .cbt-subject-progress-step {
                margin: 0;
                color: #52637a;
                font-size: 13px;
                font-weight: 600;
                line-height: 1.45;
            }
            .cbt-subject-panel .button.is-loading,
            .cbt-subject-row-action.is-loading {
                pointer-events: none;
                opacity: 0.78;
            }
            .cbt-subject-panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }
            .cbt-subject-panel-header h2 {
                margin: 0 0 8px;
                font-size: 20px;
                font-weight: 800;
                color: var(--cbt-subject-text);
                line-height: 1.2;
            }
            .cbt-subject-panel-header p {
                margin: 0;
                color: var(--cbt-subject-muted);
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-subject-chip {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 12px;
                border-radius: 999px;
                background: var(--cbt-subject-border);
                color: var(--cbt-subject-text);
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .cbt-subject-callout {
                margin: 0 0 12px;
                padding: 12px 14px;
                border: 1px solid #bfdbfe;
                border-radius: 14px;
                background: var(--cbt-subject-primary-soft);
                color: #1e3a8a;
                font-size: 13px;
                line-height: 1.55;
            }
            .cbt-subject-callout--warning {
                border-color: #fde68a;
                background: #fffbeb;
                color: #92400e;
            }
            .cbt-subject-callout ul {
                margin: 6px 0 0 18px;
                list-style: disc;
            }
            .cbt-subject-callout li {
                margin: 2px 0;
            }
            .cbt-subject-actions,
            .cbt-subject-form-actions,
            .cbt-subject-bulk-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-subject-form-actions {
                margin-top: 18px;
            }
            .cbt-subject-bulk-actions {
                margin: 0 0 12px;
            }
            .cbt-subject-bulk-button.button {
                min-height: 40px;
                border-radius: 12px;
                border: 1px solid #efcaca;
                background: #ffffff;
                color: #b42318;
                font-weight: 700;
            }
            .cbt-subject-bulk-button.button:hover,
            .cbt-subject-bulk-button.button:focus {
                border-color: #e5a5a5;
                background: #fff5f5;
                color: #991b1b;
            }
            .cbt-subject-bulk-button.button:disabled,
            .cbt-subject-bulk-button.button[disabled] {
                border-color: var(--cbt-subject-border) !important;
                background: #f8fafc !important;
                color: #94a3b8 !important;
                cursor: not-allowed;
            }
            .cbt-subject-bulk-hint {
                color: var(--cbt-subject-muted);
                font-size: 12px;
            }
            .cbt-subject-panel .form-table {
                margin: 0;
                border-collapse: separate;
                border-spacing: 0 18px;
            }
            .cbt-subject-panel .form-table th {
                width: 190px;
                padding: 10px 18px 0 0;
                vertical-align: top;
                color: var(--cbt-subject-text);
                font-size: 14px;
                font-weight: 700;
            }
            .cbt-subject-panel .form-table td {
                padding: 0;
                vertical-align: top;
            }
            .cbt-subject-required {
                color: var(--cbt-subject-danger);
            }
            .cbt-subject-panel input[type="text"],
            .cbt-subject-panel input[type="search"],
            .cbt-subject-panel select,
            .cbt-subject-panel textarea {
                width: 100%;
            }
            .cbt-subject-panel input[type="text"],
            .cbt-subject-panel input[type="search"],
            .cbt-subject-panel select {
                height: 44px;
                border-radius: 12px;
                border: 2px solid var(--cbt-subject-border);
                padding: 0 14px;
                background: #f8fafc;
                color: var(--cbt-subject-text);
                font-size: 14px;
                transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            }
            .cbt-subject-panel textarea {
                border-radius: 12px;
                border: 2px solid var(--cbt-subject-border);
                padding: 10px 14px;
                background: #f8fafc;
            }
            .cbt-subject-panel input[type="text"]:focus,
            .cbt-subject-panel input[type="search"]:focus,
            .cbt-subject-panel select:focus,
            .cbt-subject-panel textarea:focus {
                border-color: #3b82f6;
                background: #ffffff;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
                outline: none;
            }
            .cbt-subject-panel select {
                padding-right: 32px;
                background-position: right 12px center;
            }
            .cbt-subject-panel input[type="file"] {
                padding: 8px 0;
            }
            .cbt-subject-panel .regular-text,
            .cbt-subject-panel textarea {
                max-width: 480px;
            }
            .cbt-subject-panel .description {
                margin: 6px 0 0;
                color: #6b7280;
            }
            .cbt-subject-panel .button {
                border-radius: 12px;
                min-height: 40px;
                font-weight: 700;
                padding: 0 18px;
            }
            .cbt-subject-panel .button-primary {
                border: none;
                background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
                color: #ffffff;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            }
            .cbt-subject-panel .button-primary:hover,
            .cbt-subject-panel .button-primary:focus {
                background: linear-gradient(180deg, #60a5fa 0%, #3b82f6 100%);
                box-shadow: 0 8px 16px rgba(59, 130, 246, 0.4);
            }
            .cbt-subject-import-rules {
                margin: 8px 0 0 18px;
                list-style: disc;
            }
            .cbt-subject-import-rules li {
                margin: 3px 0;
            }
            .cbt-subject-import-progress {
                display: grid;
                gap: 10px;
                margin: 0 0 18px;
                padding: 16px;
                border: 1px dashed #93c5fd;
                border-radius: 16px;
                background: var(--cbt-subject-primary-soft);
            }
            .cbt-subject-import-progress strong {
                font-size: 13px;
                color: #1e3a8a;
            }
            .cbt-subject-import-progress-track {
                height: 8px;
                border-radius: 999px;
                background: rgba(37, 99, 235, 0.12);
                overflow: hidden;
            }
            .cbt-subject-import-progress-fill {
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb, #1d4ed8);
            }
            .cbt-subject-import-progress-meta {
                font-size: 12px;
                color: #1f2937;
            }
            .cbt-subject-list-toolbar {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 14px;
            }
            .cbt-subject-filter-form {
                display: flex;
                align-items: flex-end;
                flex-wrap: wrap;
                gap: 10px;
            }
            .cbt-subject-filter-field {
                display: grid;
                gap: 4px;
            }
            .cbt-subject-filter-field label {
                color: var(--cbt-subject-muted);
                font-size: 12px;
                font-weight: 700;
            }
            .cbt-subject-filter-form input[type="search"] {
                min-width: 260px;
            }
            .cbt-subject-filter-form select {
                min-width: 90px;
            }
            .cbt-subject-filter-reset {
                display: inline-flex;
                align-items: center;
                min-height: 44px;
                padding: 0 14px;
                border-radius: 12px;
                border: 1px solid #d7dbe2;
                background: #ffffff;
                color: #475569;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
            }
            .cbt-subject-filter-reset:hover,
            .cbt-subject-filter-reset:focus {
                border-color: #1d4ed8;
                color: #1d4ed8;
            }
            .cbt-subject-table-wrap {
                overflow-x: auto;
            }
            .cbt-subject-panel .widefat {
                border-radius: 16px;
                overflow: hidden;
                border: 1px solid var(--cbt-subject-border);
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
            }
            .cbt-subject-panel .widefat thead th,
            .cbt-subject-panel .widefat thead td {
                background: #f8fafc;
                color: #475569;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                white-space: nowrap;
                padding: 14px 16px;
            }
            .cbt-subject-panel .widefat td,
            .cbt-subject-panel .widefat th {
                vertical-align: middle;
                padding: 12px 16px;
                font-size: 14px;
                color: #334155;
            }
            .cbt-subject-panel .widefat .check-column {
                width: 36px;
                padding: 12px 8px 12px 16px;
            }
            .cbt-subject-panel .widefat .check-column input {
                margin: 0;
            }
            .cbt-subject-panel .widefat tbody tr:hover {
                background: #f8fafc;
            }
            .cbt-subject-col-id {
                width: 56px;
                color: var(--cbt-subject-muted) !important;
            }
            .cbt-subject-name {
                font-weight: 700;
                color: var(--cbt-subject-text);
            }
            .cbt-subject-code {
                display: inline-flex;
                padding: 2px 8px;
                border-radius: 6px;
                background: #f1f5f9;
                color: #334155;
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                font-size: 12px;
                font-weight: 700;
            }
            .cbt-subject-description {
                display: -webkit-box;
                max-width: 360px;
                overflow: hidden;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                color: var(--cbt-subject-muted);
            }
            .cbt-subject-empty-value {
                color: #cbd5e1;
            }
            .cbt-subject-usage {
                display: flex;
                flex-wrap: wrap;
                gap: 4px;
            }
            .cbt-subject-usage-badge {
                display: inline-flex;
                align-items: center;
                min-height: 22px;
                padding: 0 8px;
                border-radius: 999px;
                background: var(--cbt-subject-primary-soft);
                color: #1d4ed8;
                font-size: 12px;
                font-weight: 700;
                white-space: nowrap;
            }
            .cbt-subject-usage-badge--muted {
                background: #f1f5f9;
                color: var(--cbt-subject-muted);
                font-weight: 600;
            }
            .cbt-subject-row-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: nowrap;
            }
            .cbt-subject-row-action {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 32px;
                padding: 0 14px;
                border: 1px solid #dbeafe;
                border-radius: 999px;
                background: #ffffff;
                color: var(--cbt-subject-primary);
                font-size: 12px;
                font-weight: 700;
                text-decoration: none;
                white-space: nowrap;
            }
            .cbt-subject-row-action:hover,
            .cbt-subject-row-action:focus {
                border-color: #bfdbfe;
                background: #eef4ff;
                color: #1d4ed8;
            }
            .cbt-subject-row-action--delete {
                border-color: #fee2e2;
                color: #e11d48;
            }
            .cbt-subject-row-action--delete:hover,
            .cbt-subject-row-action--delete:focus {
                border-color: #fecdd3;
                background: #fff1f2;
                color: var(--cbt-subject-danger);
            }
            .cbt-subject-row-action--locked,
            .cbt-subject-row-action--locked:hover {
                border-color: var(--cbt-subject-border);
                background: #f8fafc;
                color: #94a3b8;
                cursor: help;
            }
            .cbt-subject-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 12px;
            }
            .cbt-subject-total {
                color: var(--cbt-subject-muted);
                font-weight: 600;
            }
            .cbt-subject-pagination-links {
                display: inline-flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 6px;
            }
            .cbt-subject-pagination-links .page-numbers {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 34px;
                height: 34px;
                padding: 0 8px;
                border: 1px solid #d2d8e1;
                border-radius: 999px;
                background: #ffffff;
                color: #334155;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
            }
            .cbt-subject-pagination-links a.page-numbers:hover,
            .cbt-subject-pagination-links a.page-numbers:focus {
                border-color: #1d4ed8;
                color: #1d4ed8;
            }
            .cbt-subject-pagination-links .page-numbers.current {
                border-color: #1d4ed8;
                background: #1d4ed8;
                color: #ffffff;
            }
            .cbt-subject-pagination-links .page-numbers.dots {
                min-width: auto;
                border: none;
                background: transparent;
            }

            @media (max-width: 960px) {
                .cbt-subject-page {
                    max-width: 100%;
                }
                .cbt-subject-hero,
                .cbt-subject-panel-header,
                .cbt-subject-list-toolbar {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-subject-hero,
                .cbt-subject-panel {
                    padding: 20px;
                }
                .cbt-subject-overview {
                    min-width: 0;
                }
                .cbt-subject-chip {
                    align-self: flex-start;
                }
                .cbt-subject-panel .form-table th {
                    width: 100%;
                }
                .cbt-subject-filter-form,
                .cbt-subject-filter-field,
                .cbt-subject-filter-form input[type="search"] {
                    width: 100%;
                    min-width: 0;
                }
            }
        </style>
        <div
            class="wrap cbt-subject-page"
            data-cbt-subject-default-tab="<?php echo esc_attr($default_subject_tab); ?>"
            data-cbt-subject-force-tab="<?php echo $subject_tab_is_forced ? '1' : '0'; ?>"
            data-cbt-subject-root
        >
            <div class="cbt-subject-shell">
                <section class="cbt-subject-hero">
                    <div class="cbt-subject-hero-copy">
                        <span class="cbt-subject-kicker">Subject</span>
                        <h1>CBT Subjects</h1>
                        <p>Kelola mapel yang dipakai bank soal, builder exam, dan mapel pilihan siswa.</p>
                    </div>
                    <div class="cbt-subject-overview" data-cbt-subject-refresh-area="overview">
                        <span class="cbt-subject-pill">Total subject <strong><?php echo esc_html((string) (int) $total_subjects); ?></strong></span>
                        <span class="cbt-subject-pill">Dipakai ujian / bank soal <strong><?php echo esc_html((string) $subjects_in_use_total); ?></strong></span>
                        <span class="cbt-subject-pill">Belum dipakai <strong><?php echo esc_html((string) $subject_unused_total); ?></strong></span>
                    </div>
                </section>

                <hr class="wp-header-end" />
                <div class="cbt-subject-notices" data-cbt-subject-refresh-area="notices"><?php if ($notice): ?><div class="notice notice-success is-dismissible inline"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?><?php if ($error): ?><div class="notice notice-error is-dismissible inline"><p><?php echo esc_html($error); ?></p></div><?php endif; ?></div>

                <div class="cbt-subject-progress" data-cbt-subject-progress role="status" aria-live="polite" aria-hidden="true">
                    <div class="cbt-subject-progress-head">
                        <strong class="cbt-subject-progress-title" data-cbt-subject-progress-label>Memproses CBT Subjects...</strong>
                        <span class="cbt-subject-progress-percent" data-cbt-subject-progress-percent>0%</span>
                    </div>
                    <div class="cbt-subject-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-cbt-subject-progress-track>
                        <span class="cbt-subject-progress-fill" data-cbt-subject-progress-fill></span>
                    </div>
                    <p class="cbt-subject-progress-step" data-cbt-subject-progress-step></p>
                </div>

                <div class="cbt-subject-tabs" role="tablist" aria-label="Navigasi CBT Subject">
                    <?php foreach (['form' => 'Form Subject', 'import' => 'Import Subject', 'list' => 'Daftar Subject'] as $subject_tab_id => $subject_tab_label): ?>
                        <button
                            type="button"
                            id="cbt-subject-tab-<?php echo esc_attr($subject_tab_id); ?>"
                            class="cbt-subject-tab<?php echo $default_subject_tab === $subject_tab_id ? ' is-active' : ''; ?>"
                            data-cbt-subject-tab="<?php echo esc_attr($subject_tab_id); ?>"
                            role="tab"
                            aria-controls="cbt-subject-panel-<?php echo esc_attr($subject_tab_id); ?>"
                            aria-selected="<?php echo $default_subject_tab === $subject_tab_id ? 'true' : 'false'; ?>"
                        ><?php echo esc_html($subject_tab_label); ?></button>
                    <?php endforeach; ?>
                </div>

                <section id="cbt-subject-panel-form" class="cbt-subject-panel<?php echo $default_subject_tab === 'form' ? ' is-active' : ''; ?>" data-cbt-subject-panel="form" data-cbt-subject-refresh-area="form-panel" role="tabpanel" aria-labelledby="cbt-subject-tab-form">
                    <div class="cbt-subject-panel-header">
                        <div>
                            <h2><?php echo $editing ? esc_html(sprintf('Edit Subject: %s', (string) ($editing['name'] ?? ''))) : 'Tambah Subject'; ?></h2>
                            <p><?php echo $editing ? 'Perbarui nama, kode, atau deskripsi subject.' : 'Tambahkan subject/mapel baru untuk bank soal dan builder exam.'; ?></p>
                        </div>
                        <?php if ($editing): ?>
                            <a href="<?php echo esc_url($subject_clear_edit_url); ?>" class="button button-secondary">Batal Edit</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($editing && is_array($editing_usage)): ?>
                        <?php
                        $editing_usage_parts = [];
                        if ((int) ($editing_usage['exam_count'] ?? 0) > 0) {
                            $editing_usage_parts[] = sprintf('%d ujian', (int) $editing_usage['exam_count']);
                        }
                        if ((int) ($editing_usage['bank_question_count'] ?? 0) > 0) {
                            $editing_usage_parts[] = sprintf('%d soal bank soal', (int) $editing_usage['bank_question_count']);
                        }
                        if ((int) ($editing_usage['choice_count'] ?? 0) > 0) {
                            $editing_usage_parts[] = sprintf('%d siswa memilih mapel ini', (int) $editing_usage['choice_count']);
                        }
                        ?>
                        <?php if (!empty($editing_usage_parts)): ?>
                            <p class="cbt-subject-callout">
                                Subject ini dipakai oleh <?php echo esc_html(implode(', ', $editing_usage_parts)); ?>.
                                Perubahan nama langsung terlihat di semua tempat tersebut, termasuk judul Bank Soal.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-subject-tab-submit="form" data-cbt-subject-async-form data-cbt-subject-progress-profile="save" data-cbt-subject-refresh-areas="notices,overview,form-panel,list-panel" data-cbt-subject-success-tab="list" data-cbt-subject-error-tab="form">
                        <?php wp_nonce_field('cbt_save_subject'); ?>
                        <input type="hidden" name="action" value="cbt_save_subject" />
                        <input type="hidden" name="id" value="<?php echo esc_attr((string) (int) ($editing['id'] ?? 0)); ?>" />
                        <input type="hidden" name="cbt_subject_per_page" value="<?php echo (int) $subject_per_page; ?>" />
                        <input type="hidden" name="cbt_subject_paged" value="<?php echo (int) $subject_current_page; ?>" />
                        <input type="hidden" name="cbt_subject_q" value="<?php echo esc_attr($subject_search); ?>" />

                        <table class="form-table" role="presentation">
                            <tr>
                                <th><label for="cbt-subject-name">Nama <span class="cbt-subject-required" aria-hidden="true">*</span></label></th>
                                <td>
                                    <input required type="text" id="cbt-subject-name" name="name" class="regular-text" maxlength="120" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" placeholder="Contoh: Matematika" />
                                    <p class="description">Harus unik. Dipakai di bank soal, builder exam, dan kartu ujian.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbt-subject-code">Kode</label></th>
                                <td>
                                    <input type="text" id="cbt-subject-code" name="code" class="regular-text" maxlength="30" value="<?php echo esc_attr($editing['code'] ?? ''); ?>" placeholder="MAT, IND, ENG" autocomplete="off" />
                                    <p class="description">Opsional dan unik. Hanya huruf, angka, <code>-</code> dan <code>_</code>; spasi/tanda baca lain dibuang dan huruf otomatis kapital.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbt-subject-description">Deskripsi</label></th>
                                <td>
                                    <textarea id="cbt-subject-description" name="description" class="large-text" rows="3"><?php echo esc_textarea($editing['description'] ?? ''); ?></textarea>
                                    <p class="description">Opsional, hanya untuk catatan administrasi.</p>
                                </td>
                            </tr>
                        </table>

                        <div class="cbt-subject-form-actions">
                            <?php submit_button($editing ? 'Update Subject' : 'Simpan Subject', 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </section>

                <section id="cbt-subject-panel-import" class="cbt-subject-panel<?php echo $default_subject_tab === 'import' ? ' is-active' : ''; ?>" data-cbt-subject-panel="import" data-cbt-subject-refresh-area="import-panel" role="tabpanel" aria-labelledby="cbt-subject-tab-import">
                    <div class="cbt-subject-panel-header">
                        <div>
                            <h2>Import Subject</h2>
                            <p>Upload file CSV atau XLSX untuk membuat atau memperbarui banyak subject sekaligus.</p>
                        </div>
                        <span class="cbt-subject-chip">CSV / XLSX</span>
                    </div>
                    <?php if (is_array($subject_import_state)): ?>
                        <div
                            class="cbt-subject-import-progress"
                            data-cbt-subject-import-progress
                            data-cbt-subject-import-running="<?php echo $subject_import_is_running ? '1' : '0'; ?>"
                            data-cbt-subject-import-continue-url="<?php echo esc_url($subject_import_continue_url); ?>"
                            data-cbt-subject-progress-profile="import"
                            data-cbt-subject-refresh-areas="notices,overview,import-panel,list-panel"
                            data-cbt-subject-success-tab="import"
                        >
                            <strong>
                                Progress import:
                                <?php echo esc_html((string) $subject_import_offset . ' / ' . (string) $subject_import_total); ?>
                                (<?php echo esc_html(number_format((float) $subject_import_progress_percent, 0)); ?>%)
                            </strong>
                            <div class="cbt-subject-import-progress-track" aria-hidden="true">
                                <div class="cbt-subject-import-progress-fill" style="width: <?php echo esc_attr((string) $subject_import_progress_percent); ?>%;"></div>
                            </div>
                            <div class="cbt-subject-import-progress-meta">
                                Baru: <?php echo esc_html((string) $subject_import_created); ?> ·
                                Diperbarui: <?php echo esc_html((string) $subject_import_updated); ?> ·
                                Gagal: <?php echo esc_html((string) $subject_import_failed); ?>
                                — <?php echo $subject_import_is_running ? 'memproses batch berikutnya...' : 'selesai diproses.'; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (is_array($subject_import_result)): ?>
                        <?php $subject_import_result_failed = (int) ($subject_import_result['failed'] ?? 0); ?>
                        <div class="cbt-subject-callout<?php echo $subject_import_result_failed > 0 ? ' cbt-subject-callout--warning' : ''; ?>">
                            <strong>Hasil import terakhir:</strong>
                            <?php
                            echo esc_html(sprintf(
                                '%d baris diproses, %d subject baru, %d diperbarui, %d gagal.',
                                (int) ($subject_import_result['total'] ?? 0),
                                (int) ($subject_import_result['created'] ?? 0),
                                (int) ($subject_import_result['updated'] ?? 0),
                                $subject_import_result_failed
                            ));
                            ?>
                            <?php $subject_import_result_failures = (array) ($subject_import_result['failures'] ?? []); ?>
                            <?php if (!empty($subject_import_result_failures)): ?>
                                <ul>
                                    <?php foreach ($subject_import_result_failures as $subject_import_failure_line): ?>
                                        <li><?php echo esc_html((string) $subject_import_failure_line); ?></li>
                                    <?php endforeach; ?>
                                    <?php if ($subject_import_result_failed > count($subject_import_result_failures)): ?>
                                        <li><?php echo esc_html(sprintf('... dan %d baris gagal lainnya.', $subject_import_result_failed - count($subject_import_result_failures))); ?></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!empty($subject_import_failures)): ?>
                        <div class="cbt-subject-callout cbt-subject-callout--warning">
                            <strong>Baris yang gagal sejauh ini:</strong>
                            <ul>
                                <?php foreach ($subject_import_failures as $subject_import_failure_line): ?>
                                    <li><?php echo esc_html((string) $subject_import_failure_line); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <div class="cbt-subject-actions">
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_subject_template'), 'cbt_download_subject_template')); ?>">
                            Download Template CSV
                        </a>
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_subject_template_xlsx'), 'cbt_download_subject_template_xlsx')); ?>">
                            Download Template XLSX
                        </a>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-subject-tab-submit="import" data-cbt-subject-async-form data-cbt-subject-progress-profile="import" data-cbt-subject-refresh-areas="notices,overview,import-panel,list-panel" data-cbt-subject-success-tab="import" data-cbt-subject-error-tab="import">
                        <?php wp_nonce_field('cbt_import_subjects'); ?>
                        <input type="hidden" name="action" value="cbt_import_subjects" />
                        <table class="form-table" role="presentation">
                            <tr>
                                <th><label for="cbt-subject-import-file">File Import</label></th>
                                <td>
                                    <input required type="file" id="cbt-subject-import-file" name="subject_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                                    <ul class="cbt-subject-import-rules description">
                                        <li>Baris pertama berisi header. Kolom wajib: <code>name</code> (atau <code>nama</code>). Opsional: <code>code</code>/<code>kode</code> dan <code>description</code>/<code>deskripsi</code>.</li>
                                        <li>Subject yang sudah ada dicocokkan lewat <code>code</code>, lalu lewat <code>name</code>, kemudian diperbarui. Sisanya dibuat baru.</li>
                                        <li>Sel <code>code</code> atau <code>description</code> yang kosong tidak menghapus data lama.</li>
                                        <li><code>name</code> dan <code>code</code> tidak boleh duplikat di dalam file. Baris yang <code>code</code> dan <code>name</code>-nya menunjuk dua subject berbeda akan ditolak.</li>
                                    </ul>
                                </td>
                            </tr>
                        </table>
                        <div class="cbt-subject-form-actions">
                            <?php submit_button('Import Subject', 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </section>

                <section id="cbt-subject-panel-list" class="cbt-subject-panel<?php echo $default_subject_tab === 'list' ? ' is-active' : ''; ?>" data-cbt-subject-panel="list" data-cbt-subject-refresh-area="list-panel" role="tabpanel" aria-labelledby="cbt-subject-tab-list">
                    <div class="cbt-subject-panel-header">
                        <div>
                            <h2>Daftar Subject</h2>
                            <p>Subject yang masih dipakai ujian atau punya soal di bank soal terkunci dan tidak bisa dihapus.</p>
                        </div>
                        <span class="cbt-subject-chip"><?php echo esc_html($subject_list_chip_label); ?></span>
                    </div>
                    <div class="cbt-subject-list-toolbar">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-subject-filter-form" role="search" data-cbt-subject-tab-submit="list" data-cbt-subject-progress-profile="list">
                            <input type="hidden" name="page" value="cbt-subjects" />
                            <input type="hidden" name="cbt_subject_tab" value="list" />
                            <div class="cbt-subject-filter-field">
                                <label for="cbt-subject-search">Cari subject</label>
                                <input type="search" id="cbt-subject-search" name="cbt_subject_q" value="<?php echo esc_attr($subject_search); ?>" placeholder="Nama atau kode..." autocomplete="off" data-cbt-subject-auto-submit="input" />
                            </div>
                            <div class="cbt-subject-filter-field">
                                <label for="cbt-subject-per-page">Per halaman</label>
                                <select id="cbt-subject-per-page" name="cbt_subject_per_page" data-cbt-subject-auto-submit="change">
                                    <?php foreach ([20, 40, 60, 80, 100] as $subject_per_page_option): ?>
                                        <option value="<?php echo (int) $subject_per_page_option; ?>" <?php selected((int) $subject_per_page, $subject_per_page_option); ?>>
                                            <?php echo esc_html((string) $subject_per_page_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($subject_has_search): ?>
                                <a href="<?php echo esc_url($subject_reset_filter_url); ?>" class="cbt-subject-filter-reset">Reset Pencarian</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-subject-tab-submit="list" data-cbt-subject-async-form data-cbt-subject-progress-profile="delete" data-cbt-subject-refresh-areas="notices,overview,list-panel" data-cbt-subject-success-tab="list">
                        <?php wp_nonce_field('cbt_bulk_delete_subjects'); ?>
                        <input type="hidden" name="action" value="cbt_bulk_delete_subjects" />
                        <input type="hidden" name="cbt_subject_per_page" value="<?php echo (int) $subject_per_page; ?>" />
                        <input type="hidden" name="cbt_subject_q" value="<?php echo esc_attr($subject_search); ?>" />
                        <input type="hidden" name="cbt_subject_paged" value="<?php echo (int) $subject_current_page; ?>" />
                        <?php if (!empty($subjects)): ?>
                            <?php
                            $subject_delete_all_label = $subject_has_search ? 'Hapus Semua Hasil Pencarian' : 'Hapus Semua yang Tidak Dipakai';
                            $subject_delete_all_confirm = $subject_has_search
                                ? sprintf('Hapus semua subject yang cocok dengan pencarian "%s" (%d subject)? Subject yang masih dipakai ujian/bank soal otomatis dilewati.', $subject_search, (int) $filtered_subject_total)
                                : sprintf('Hapus SEMUA subject yang tidak dipakai (%d subject)? Subject yang masih dipakai ujian/bank soal otomatis dilewati.', $subject_unused_total);
                            ?>
                            <div class="cbt-subject-bulk-actions">
                                <button type="submit" class="button cbt-subject-bulk-button" name="bulk_mode" value="selected" data-cbt-subject-bulk-selected onclick="return confirm('Hapus subject yang dipilih?');">Hapus Terpilih<span data-cbt-subject-selected-count></span></button>
                                <button type="submit" class="button cbt-subject-bulk-button" name="bulk_mode" value="all" onclick="return confirm(<?php echo esc_attr(wp_json_encode($subject_delete_all_confirm)); ?>);"><?php echo esc_html($subject_delete_all_label); ?></button>
                                <span class="cbt-subject-bulk-hint">Pilihan mapel siswa untuk subject yang dihapus ikut dibersihkan.</span>
                            </div>
                        <?php endif; ?>

                        <div class="cbt-subject-table-wrap">
                        <table class="widefat striped">
                            <thead>
                            <tr>
                                <td class="check-column"><input type="checkbox" id="cbt-subject-select-all" aria-label="Pilih semua subject yang bisa dihapus" /></td>
                                <th class="cbt-subject-col-id">ID</th>
                                <th>Nama</th>
                                <th>Kode</th>
                                <th>Deskripsi</th>
                                <th>Pemakaian</th>
                                <th>Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$subjects): ?>
                                <?php
                                echo CBT_Admin_UI_Helper::render_table_empty_state(7, [
                                    'title' => $subject_has_search ? 'Tidak ada subject yang cocok' : 'Belum ada subject',
                                    'message' => $subject_has_search
                                        ? sprintf('Tidak ada subject dengan nama atau kode "%s".', $subject_search)
                                        : 'Tambahkan subject/mapel sebagai dasar bank soal, exam, dan mapel pilihan siswa.',
                                    'action_label' => $subject_has_search ? 'Reset Pencarian' : 'Tambah Subject',
                                    'action_url' => $subject_has_search ? $subject_reset_filter_url : $subject_open_form_url,
                                    'action_class' => $subject_has_search ? 'button button-secondary cbt-admin-btn--secondary cbt-subject-filter-reset-link' : 'button button-primary cbt-subject-open-form',
                                ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                            <?php else: ?>
                                <?php foreach ($subjects as $subject): ?>
                                    <?php
                                    $subject_row_id = (int) ($subject['id'] ?? 0);
                                    $subject_row_name = (string) ($subject['name'] ?? '');
                                    $subject_row_code = (string) ($subject['code'] ?? '');
                                    $subject_row_description = trim((string) ($subject['description'] ?? ''));
                                    $subject_row_usage = isset($subject_usage_map[$subject_row_id]) && is_array($subject_usage_map[$subject_row_id])
                                        ? $subject_usage_map[$subject_row_id]
                                        : ['exam_count' => 0, 'bank_question_count' => 0, 'choice_count' => 0, 'deletable' => true];
                                    $subject_row_exam_count = (int) ($subject_row_usage['exam_count'] ?? 0);
                                    $subject_row_bank_count = (int) ($subject_row_usage['bank_question_count'] ?? 0);
                                    $subject_row_choice_count = (int) ($subject_row_usage['choice_count'] ?? 0);
                                    $subject_row_deletable = !empty($subject_row_usage['deletable']);
                                    $subject_row_lock_reason = CBT_Admin_Subjects_Service::describe_subject_usage($subject_row_usage);
                                    $subject_row_delete_confirm = $subject_row_choice_count > 0
                                        ? sprintf('Hapus subject "%s"? %d siswa memilih mapel ini dan pilihan mereka ikut dihapus.', $subject_row_name, $subject_row_choice_count)
                                        : sprintf('Hapus subject "%s"?', $subject_row_name);
                                    ?>
                                    <tr>
                                        <th scope="row" class="check-column">
                                            <input
                                                type="checkbox"
                                                class="cbt-subject-row-check"
                                                name="subject_ids[]"
                                                value="<?php echo $subject_row_id; ?>"
                                                aria-label="<?php echo esc_attr(sprintf('Pilih %s', $subject_row_name)); ?>"
                                                <?php disabled(!$subject_row_deletable); ?>
                                                <?php if (!$subject_row_deletable): ?>title="<?php echo esc_attr('Terkunci: dipakai ' . $subject_row_lock_reason); ?>"<?php endif; ?>
                                            />
                                        </th>
                                        <td class="cbt-subject-col-id"><?php echo $subject_row_id; ?></td>
                                        <td><span class="cbt-subject-name"><?php echo esc_html($subject_row_name); ?></span></td>
                                        <td>
                                            <?php if ($subject_row_code !== ''): ?>
                                                <span class="cbt-subject-code"><?php echo esc_html($subject_row_code); ?></span>
                                            <?php else: ?>
                                                <span class="cbt-subject-empty-value" aria-label="Tidak ada kode">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($subject_row_description !== ''): ?>
                                                <span class="cbt-subject-description" title="<?php echo esc_attr($subject_row_description); ?>"><?php echo esc_html($subject_row_description); ?></span>
                                            <?php else: ?>
                                                <span class="cbt-subject-empty-value" aria-label="Tidak ada deskripsi">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="cbt-subject-usage">
                                                <?php if ($subject_row_exam_count > 0): ?>
                                                    <span class="cbt-subject-usage-badge"><?php echo esc_html(sprintf('%d ujian', $subject_row_exam_count)); ?></span>
                                                <?php endif; ?>
                                                <?php if ($subject_row_bank_count > 0): ?>
                                                    <span class="cbt-subject-usage-badge"><?php echo esc_html(sprintf('%d soal bank', $subject_row_bank_count)); ?></span>
                                                <?php endif; ?>
                                                <?php if ($subject_row_choice_count > 0): ?>
                                                    <span class="cbt-subject-usage-badge cbt-subject-usage-badge--muted"><?php echo esc_html(sprintf('%d siswa memilih', $subject_row_choice_count)); ?></span>
                                                <?php endif; ?>
                                                <?php if ($subject_row_exam_count === 0 && $subject_row_bank_count === 0 && $subject_row_choice_count === 0): ?>
                                                    <span class="cbt-subject-usage-badge cbt-subject-usage-badge--muted">Belum dipakai</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cbt-admin-row-actions cbt-subject-row-actions">
                                                <a class="cbt-admin-action cbt-admin-action--edit cbt-subject-row-action cbt-subject-row-action--edit" href="<?php echo esc_url(add_query_arg(array_merge($subject_list_query_args, ['edit' => $subject_row_id, 'cbt_subject_paged' => $subject_current_page]), admin_url('admin.php'))); ?>">Edit</a>
                                                <?php if ($subject_row_deletable): ?>
                                                    <a class="cbt-admin-action cbt-admin-action--delete cbt-subject-row-action cbt-subject-row-action--delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge([
                                                        'action' => 'cbt_delete_subject',
                                                        'id' => $subject_row_id,
                                                        'cbt_subject_paged' => $subject_current_page,
                                                    ], $subject_list_query_args), admin_url('admin-post.php')), 'cbt_delete_subject_' . $subject_row_id)); ?>" data-cbt-subject-async-link data-cbt-subject-progress-profile="delete" data-cbt-subject-refresh-areas="notices,overview,list-panel" data-cbt-subject-success-tab="list" onclick="return confirm(<?php echo esc_attr(wp_json_encode($subject_row_delete_confirm)); ?>);">Hapus</a>
                                                <?php else: ?>
                                                    <span class="cbt-subject-row-action cbt-subject-row-action--locked" tabindex="0" title="<?php echo esc_attr('Tidak bisa dihapus: dipakai ' . $subject_row_lock_reason); ?>">Terkunci</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                        <div class="cbt-subject-pagination">
                            <span class="cbt-subject-total"><?php echo esc_html($subject_list_total_label); ?></span>
                            <?php if (!empty($subject_pagination_links)): ?>
                                <span class="cbt-subject-pagination-links cbt-admin-pagination-links">
                                    <?php foreach ($subject_pagination_links as $subject_pagination_link): ?>
                                        <?php echo wp_kses_post($subject_pagination_link); ?>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            </div>
        </div>
        <script>
            (function () {
                const page = document.querySelector('.cbt-subject-page');
                const tabButtons = Array.from(document.querySelectorAll('[data-cbt-subject-tab]'));
                const tabStorageKey = 'cbt-subject-active-tab';
                const defaultTab = page ? String(page.getAttribute('data-cbt-subject-default-tab') || 'list') : 'list';
                const forceTab = page ? page.getAttribute('data-cbt-subject-force-tab') === '1' : false;
                const transientQueryKeys = ['cbt_msg', 'cbt_err', 'cbt_subject_import_done', 'cbt_subject_tab'];
                // Dideklarasikan paling atas: blok init di bawah memanggil bindSubjectLocalActions() yang memakai nilai ini.
                const supportsPartialListRefresh = !!(window.fetch && window.DOMParser);
                let subjectFilterTimer = 0;
                let subjectListRequestSeq = 0;
                let subjectTabStorage = null;
                let subjectProgressTimer = 0;
                let subjectProgressHideTimer = 0;
                let subjectImportTimer = 0;
                let subjectImportInFlight = false;

                try {
                    subjectTabStorage = window.localStorage;
                } catch (error) {
                    subjectTabStorage = null;
                }

                function readSubjectStoredTab() {
                    if (!subjectTabStorage) {
                        return '';
                    }

                    try {
                        return String(subjectTabStorage.getItem(tabStorageKey) || '');
                    } catch (error) {
                        return '';
                    }
                }

                function writeSubjectStoredTab(tabId) {
                    if (!subjectTabStorage || tabId === '') {
                        return;
                    }

                    try {
                        subjectTabStorage.setItem(tabStorageKey, tabId);
                    } catch (error) {
                    }
                }

                function getSubjectTabPanels() {
                    return Array.from(document.querySelectorAll('[data-cbt-subject-panel]'));
                }

                function activateTab(tabId, persist) {
                    let hasTarget = false;
                    const tabPanels = getSubjectTabPanels();
                    tabButtons.forEach((button) => {
                        const isActive = button.getAttribute('data-cbt-subject-tab') === tabId;
                        button.classList.toggle('is-active', isActive);
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        if (isActive) {
                            hasTarget = true;
                        }
                    });
                    tabPanels.forEach((panel) => {
                        const isActive = panel.getAttribute('data-cbt-subject-panel') === tabId;
                        panel.classList.toggle('is-active', isActive);
                    });
                    if (persist && hasTarget) {
                        writeSubjectStoredTab(tabId);
                    }
                }

                function cleanSubjectUrl(rawUrl) {
                    try {
                        const url = new URL(String(rawUrl || window.location.href), window.location.href);
                        transientQueryKeys.forEach((key) => {
                            url.searchParams.delete(key);
                        });
                        return url.toString();
                    } catch (error) {
                        return '';
                    }
                }

                function updateSubjectHistory(rawUrl) {
                    if (!window.history || typeof window.history.replaceState !== 'function') {
                        return;
                    }

                    const cleanUrl = cleanSubjectUrl(rawUrl);
                    if (cleanUrl !== '') {
                        // Notice/tab sekali pakai tidak ikut tersimpan di URL agar refresh tidak menampilkan pesan lama.
                        window.history.replaceState({}, '', cleanUrl);
                    }
                }

                function clampSubjectProgress(value) {
                    const number = parseInt(value, 10);
                    if (Number.isNaN(number)) {
                        return 0;
                    }

                    return Math.max(0, Math.min(100, number));
                }

                function getSubjectProgressElements() {
                    const root = document.querySelector('[data-cbt-subject-progress]');
                    if (!root) {
                        return null;
                    }

                    return {
                        root,
                        label: root.querySelector('[data-cbt-subject-progress-label]'),
                        percent: root.querySelector('[data-cbt-subject-progress-percent]'),
                        track: root.querySelector('[data-cbt-subject-progress-track]'),
                        fill: root.querySelector('[data-cbt-subject-progress-fill]'),
                        step: root.querySelector('[data-cbt-subject-progress-step]'),
                    };
                }

                function setSubjectProgress(percent, label, step, tone) {
                    const elements = getSubjectProgressElements();
                    const progress = clampSubjectProgress(percent);
                    if (!elements) {
                        return;
                    }

                    if (subjectProgressHideTimer) {
                        window.clearTimeout(subjectProgressHideTimer);
                        subjectProgressHideTimer = 0;
                    }
                    elements.root.classList.add('is-active');
                    elements.root.classList.toggle('is-error', tone === 'error');
                    elements.root.setAttribute('aria-hidden', 'false');
                    if (elements.label && label) {
                        elements.label.textContent = label;
                    }
                    if (elements.percent) {
                        elements.percent.textContent = String(progress) + '%';
                    }
                    if (elements.track) {
                        elements.track.setAttribute('aria-valuenow', String(progress));
                    }
                    if (elements.fill) {
                        elements.fill.style.setProperty('--cbt-subject-progress', String(progress) + '%');
                    }
                    if (elements.step && step) {
                        elements.step.textContent = step;
                    }
                }

                function hideSubjectProgress() {
                    const elements = getSubjectProgressElements();
                    subjectProgressHideTimer = 0;
                    if (!elements) {
                        return;
                    }

                    elements.root.classList.remove('is-active', 'is-error');
                    elements.root.setAttribute('aria-hidden', 'true');
                }

                function stopSubjectProgress() {
                    if (subjectProgressTimer) {
                        window.clearInterval(subjectProgressTimer);
                    }
                    subjectProgressTimer = 0;
                }

                function getSubjectProgressProfile(profile) {
                    const key = String(profile || 'list');
                    if (key === 'save') {
                        return {
                            label: 'Menyimpan subject...',
                            completeLabel: 'Subject tersimpan.',
                            steps: [
                                'Memvalidasi nama dan kode subject.',
                                'Menyimpan perubahan subject.',
                                'Memperbarui daftar subject.',
                            ],
                        };
                    }
                    if (key === 'import') {
                        return {
                            label: 'Mengimport subject...',
                            completeLabel: 'Import subject diperbarui.',
                            steps: [
                                'Mengunggah file CSV/XLSX.',
                                'Membaca baris dan mendeteksi duplikasi.',
                                'Memproses batch subject.',
                            ],
                        };
                    }
                    if (key === 'delete') {
                        return {
                            label: 'Menghapus subject...',
                            completeLabel: 'Aksi hapus subject selesai.',
                            steps: [
                                'Memeriksa pemakaian subject.',
                                'Menghapus subject yang aman dihapus.',
                                'Memperbarui daftar subject.',
                            ],
                        };
                    }

                    return {
                        label: 'Memuat daftar subject...',
                        completeLabel: 'Daftar subject diperbarui.',
                        steps: [
                            'Mengambil daftar subject terbaru.',
                        ],
                    };
                }

                function startSubjectProgress(profile) {
                    const config = getSubjectProgressProfile(profile);
                    let progress = 7;
                    const startedAt = Date.now();

                    stopSubjectProgress();
                    setSubjectProgress(progress, config.label, config.steps[0], '');

                    subjectProgressTimer = window.setInterval(() => {
                        const elapsed = Date.now() - startedAt;
                        const stepIndex = Math.min(config.steps.length - 1, Math.floor(elapsed / 850));
                        const distance = 94 - progress;
                        const increment = Math.max(1, Math.min(7, Math.ceil(distance * 0.16)));
                        progress = Math.min(94, progress + increment);
                        setSubjectProgress(progress, config.label, config.steps[stepIndex], '');
                    }, 340);
                }

                function completeSubjectProgress(label, step, tone) {
                    stopSubjectProgress();
                    setSubjectProgress(100, label || 'Aksi CBT Subjects selesai.', step || 'Daftar subject sudah diperbarui.', tone || '');
                    if (tone !== 'error') {
                        // Hasil sukses sudah terlihat di notice; kartu progress tidak perlu menempel terus.
                        subjectProgressHideTimer = window.setTimeout(hideSubjectProgress, 1400);
                    }
                }

                function extractSubjectResponseError(text, status) {
                    const raw = String(text || '')
                        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                        .replace(/<style[\s\S]*?<\/style>/gi, ' ');
                    let plain = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    if (plain.length > 180) {
                        plain = plain.slice(0, 180) + '...';
                    }

                    return 'HTTP ' + String(status || 0) + (plain ? ': ' + plain : '');
                }

                function readSubjectNoticeError(parsed) {
                    const errorNotice = parsed.querySelector('[data-cbt-subject-refresh-area="notices"] .notice-error');
                    return errorNotice ? String(errorNotice.textContent || '').replace(/\s+/g, ' ').trim() : '';
                }

                function replaceSubjectRefreshAreas(parsed, areas) {
                    const replaced = [];

                    areas.forEach((areaName) => {
                        const selector = '[data-cbt-subject-refresh-area="' + areaName + '"]';
                        const current = document.querySelector(selector);
                        const next = parsed.querySelector(selector);
                        if (!current || !next) {
                            return;
                        }

                        current.replaceWith(document.importNode(next, true));
                        replaced.push(areaName);
                    });

                    if (replaced.length === 0) {
                        throw new Error('Response valid, tetapi area CBT Subjects tidak ditemukan.');
                    }

                    return replaced;
                }

                function refreshSubjectNoticeUi() {
                    if (window.jQuery) {
                        // Minta common.js WordPress memasang tombol tutup pada notice hasil fetch.
                        window.jQuery(document).trigger('wp-updates-notice-added');
                    }

                    const notices = document.querySelector('[data-cbt-subject-refresh-area="notices"]');
                    if (!notices || !notices.firstElementChild || typeof notices.getBoundingClientRect !== 'function') {
                        return;
                    }

                    const rect = notices.getBoundingClientRect();
                    if (rect.top < 32 || rect.top > window.innerHeight) {
                        notices.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }

                function getSubjectTargetTab(source, replacedAreas) {
                    const requested = source ? String(source.getAttribute('data-cbt-subject-success-tab') || '') : '';
                    if (requested !== '') {
                        return requested;
                    }
                    if (replacedAreas.indexOf('import-panel') >= 0) {
                        return 'import';
                    }
                    if (replacedAreas.indexOf('list-panel') >= 0) {
                        return 'list';
                    }

                    return 'form';
                }

                function rebindSubjectLocalUi(replacedAreas, targetTab) {
                    bindSubjectLocalActions();
                    bindSubjectListPanel();
                    bindSubjectImportContinuation();
                    if (targetTab) {
                        activateTab(targetTab, true);
                    } else if (replacedAreas.indexOf('list-panel') >= 0) {
                        activateTab('list', true);
                    }
                }

                function openSubjectForm() {
                    activateTab('form', true);
                    const nameInput = document.getElementById('cbt-subject-name');
                    if (nameInput) {
                        nameInput.focus();
                    }
                }

                if (page && tabButtons.length > 0 && getSubjectTabPanels().length > 0) {
                    let initialTab = defaultTab;
                    if (!forceTab) {
                        const savedTab = readSubjectStoredTab();
                        if (savedTab && getSubjectTabPanels().some((panel) => panel.getAttribute('data-cbt-subject-panel') === savedTab)) {
                            initialTab = savedTab;
                        }
                    }

                    activateTab(initialTab, false);
                    updateSubjectHistory(window.location.href);

                    tabButtons.forEach((button) => {
                        button.addEventListener('click', function () {
                            activateTab(String(button.getAttribute('data-cbt-subject-tab') || ''), true);
                        });
                    });

                    page.addEventListener('click', function (event) {
                        const trigger = event.target && typeof event.target.closest === 'function'
                            ? event.target.closest('.cbt-subject-open-form')
                            : null;
                        if (!trigger || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                            return;
                        }

                        event.preventDefault();
                        openSubjectForm();
                    });

                    bindSubjectLocalActions();
                }

                function getSubjectListPanel() {
                    return page ? page.querySelector('[data-cbt-subject-panel="list"]') : null;
                }

                function buildSubjectFilterUrl(form) {
                    const nextUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                    const formData = new FormData(form);

                    nextUrl.search = '';
                    formData.forEach((value, key) => {
                        if (typeof value !== 'string') {
                            return;
                        }
                        if (key === 'cbt_subject_q' && value.trim() === '') {
                            return;
                        }
                        nextUrl.searchParams.set(key, value);
                    });

                    return nextUrl;
                }

                function setSubjectListPanelLoading(panel, isLoading) {
                    if (!panel) {
                        return;
                    }

                    panel.classList.toggle('is-loading', isLoading);
                    panel.setAttribute('aria-busy', isLoading ? 'true' : 'false');
                }

                function navigateSubjectList(nextUrl) {
                    completeSubjectProgress('Daftar subject belum bisa dimuat lokal.', 'Browser tidak mendukung partial refresh untuk URL: ' + nextUrl.toString(), 'error');
                }

                async function fetchSubjectHtml(nextUrl, options) {
                    const response = await window.fetch(nextUrl.toString(), Object.assign({
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }, options || {}));
                    const html = await response.text();
                    if (!response.ok) {
                        throw new Error(extractSubjectResponseError(html, response.status));
                    }

                    return {
                        html,
                        url: response.url || nextUrl.toString(),
                    };
                }

                function getSubjectAreaList(source, fallback) {
                    const requested = String(source ? source.getAttribute('data-cbt-subject-refresh-areas') || '' : '')
                        .split(',')
                        .map((item) => item.trim())
                        .filter(Boolean);

                    return requested
                        .concat(fallback || [])
                        .filter((item, index, list) => item !== '' && list.indexOf(item) === index);
                }

                async function runSubjectLocalAction(source, requestUrl, options) {
                    const profile = String(source ? source.getAttribute('data-cbt-subject-progress-profile') || 'list' : 'list');
                    const config = getSubjectProgressProfile(profile);
                    const errorTab = String(source ? source.getAttribute('data-cbt-subject-error-tab') || '' : '');
                    const result = await fetchSubjectHtml(requestUrl, options);
                    const parsed = new DOMParser().parseFromString(String(result.html || ''), 'text/html');
                    const errorMessage = readSubjectNoticeError(parsed);
                    const keepSourceForm = errorMessage !== '' && errorTab !== '';
                    // Saat validasi gagal, form tidak diganti supaya isian user tidak hilang.
                    const areas = keepSourceForm ? ['notices'] : getSubjectAreaList(source, ['notices', 'overview', 'list-panel']);
                    const replacedAreas = replaceSubjectRefreshAreas(parsed, areas);
                    const targetTab = keepSourceForm ? errorTab : getSubjectTargetTab(source, replacedAreas);

                    updateSubjectHistory(result.url);
                    rebindSubjectLocalUi(replacedAreas, targetTab);
                    refreshSubjectNoticeUi();

                    if (errorMessage !== '') {
                        // Pesan error sudah tampil sebagai notice; kartu progress cukup disembunyikan.
                        stopSubjectProgress();
                        hideSubjectProgress();
                        return;
                    }

                    completeSubjectProgress(config.completeLabel, 'Daftar subject sudah diperbarui.', '');
                }

                async function refreshSubjectListPanel(nextUrl) {
                    const currentPanel = getSubjectListPanel();
                    if (!currentPanel || !supportsPartialListRefresh) {
                        navigateSubjectList(nextUrl);
                        return;
                    }

                    subjectListRequestSeq += 1;
                    const requestSeq = subjectListRequestSeq;
                    setSubjectListPanelLoading(currentPanel, true);

                    try {
                        const result = await fetchSubjectHtml(nextUrl);
                        if (requestSeq !== subjectListRequestSeq) {
                            return;
                        }

                        const parsed = new DOMParser().parseFromString(result.html, 'text/html');
                        const nextPanel = parsed.querySelector('[data-cbt-subject-panel="list"]');
                        if (!nextPanel) {
                            throw new Error('Area daftar subject tidak ditemukan pada response.');
                        }

                        const activeSearch = document.activeElement && document.activeElement.id === 'cbt-subject-search'
                            ? document.activeElement
                            : null;
                        const liveSearchValue = activeSearch ? activeSearch.value : null;
                        const caret = activeSearch && typeof activeSearch.selectionStart === 'number' ? activeSearch.selectionStart : null;

                        currentPanel.innerHTML = nextPanel.innerHTML;

                        if (activeSearch) {
                            // Panel diganti saat user masih mengetik: kembalikan fokus dan teks terbaru.
                            const nextSearch = currentPanel.querySelector('#cbt-subject-search');
                            if (nextSearch) {
                                nextSearch.value = liveSearchValue;
                                nextSearch.focus();
                                if (caret !== null && typeof nextSearch.setSelectionRange === 'function') {
                                    nextSearch.setSelectionRange(caret, caret);
                                }
                            }
                        }

                        updateSubjectHistory(nextUrl);
                        bindSubjectLocalActions();
                        bindSubjectListPanel();
                    } catch (error) {
                        if (requestSeq === subjectListRequestSeq) {
                            completeSubjectProgress('Gagal memuat daftar subject.', error && error.message ? error.message : 'Coba ulangi pencarian atau pindah halaman.', 'error');
                        }
                    } finally {
                        if (requestSeq === subjectListRequestSeq) {
                            setSubjectListPanelLoading(getSubjectListPanel(), false);
                        }
                    }
                }

                function submitSubjectFilters(form) {
                    if (!form) {
                        return;
                    }
                    writeSubjectStoredTab('list');
                    if (supportsPartialListRefresh) {
                        refreshSubjectListPanel(buildSubjectFilterUrl(form));
                        return;
                    }
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                        return;
                    }
                    form.submit();
                }

                function setSubjectActionButtonLoading(button, isLoading, label) {
                    if (!button) {
                        return;
                    }

                    const isInputButton = button.tagName === 'INPUT';
                    if (isLoading) {
                        button.dataset.cbtSubjectOriginalText = isInputButton ? (button.value || '') : (button.innerHTML || '');
                        button.classList.add('is-loading');
                        button.setAttribute('aria-disabled', 'true');
                        if ('disabled' in button) {
                            button.disabled = true;
                        }
                        if (label) {
                            if (isInputButton) {
                                button.value = label;
                            } else {
                                button.textContent = label;
                            }
                        }
                        return;
                    }

                    button.classList.remove('is-loading');
                    button.removeAttribute('aria-disabled');
                    if ('disabled' in button) {
                        button.disabled = false;
                    }
                    if (typeof button.dataset.cbtSubjectOriginalText === 'string') {
                        if (isInputButton) {
                            button.value = button.dataset.cbtSubjectOriginalText;
                        } else {
                            button.innerHTML = button.dataset.cbtSubjectOriginalText;
                        }
                        delete button.dataset.cbtSubjectOriginalText;
                    }
                }

                function bindSubjectLocalActions() {
                    if (!supportsPartialListRefresh) {
                        Array.from(document.querySelectorAll('form[data-cbt-subject-tab-submit]')).forEach((form) => {
                            if (form.dataset.cbtSubjectStorageBound === '1') {
                                return;
                            }
                            form.dataset.cbtSubjectStorageBound = '1';
                            form.addEventListener('submit', function () {
                                writeSubjectStoredTab(String(form.getAttribute('data-cbt-subject-tab-submit') || ''));
                            });
                        });
                        return;
                    }

                    Array.from(document.querySelectorAll('[data-cbt-subject-async-form]')).forEach((form) => {
                        if (form.dataset.cbtSubjectAsyncBound === '1') {
                            return;
                        }

                        form.dataset.cbtSubjectAsyncBound = '1';
                        form.addEventListener('submit', function (event) {
                            const submitter = event.submitter || document.activeElement;
                            const button = submitter && (submitter.tagName === 'BUTTON' || submitter.tagName === 'INPUT') && form.contains(submitter)
                                ? submitter
                                : form.querySelector('button[type="submit"], input[type="submit"]');
                            const profile = String(form.getAttribute('data-cbt-subject-progress-profile') || 'list');
                            const formData = new FormData(form);
                            const requestUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);

                            if (event.defaultPrevented) {
                                return;
                            }
                            if (form.dataset.cbtSubjectActionInFlight === '1') {
                                event.preventDefault();
                                return;
                            }

                            event.preventDefault();
                            form.dataset.cbtSubjectActionInFlight = '1';
                            writeSubjectStoredTab(String(form.getAttribute('data-cbt-subject-tab-submit') || ''));
                            if (submitter && submitter.name && !formData.has(submitter.name)) {
                                formData.append(submitter.name, submitter.value || '1');
                            }
                            formData.append('cbt_subject_local_refresh', '1');

                            setSubjectActionButtonLoading(
                                button,
                                true,
                                profile === 'delete' ? 'Menghapus...' : (profile === 'import' ? 'Mengimport...' : 'Menyimpan...')
                            );
                            startSubjectProgress(profile);

                            runSubjectLocalAction(form, requestUrl, {
                                method: 'POST',
                                body: formData,
                                cache: 'no-store',
                            })
                                .catch((error) => {
                                    completeSubjectProgress('Gagal memproses CBT Subjects.', error && error.message ? error.message : 'Form masih aman dikirim ulang.', 'error');
                                })
                                .finally(() => {
                                    delete form.dataset.cbtSubjectActionInFlight;
                                    setSubjectActionButtonLoading(button, false);
                                    bindSubjectListSelection(getSubjectListPanel());
                                });
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-subject-async-link]')).forEach((link) => {
                        if (link.dataset.cbtSubjectAsyncBound === '1') {
                            return;
                        }

                        link.dataset.cbtSubjectAsyncBound = '1';
                        link.addEventListener('click', function (event) {
                            const requestUrl = new URL(link.getAttribute('href') || window.location.href, window.location.href);

                            if (event.defaultPrevented) {
                                return;
                            }
                            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }
                            if (link.dataset.cbtSubjectActionInFlight === '1') {
                                event.preventDefault();
                                return;
                            }

                            event.preventDefault();
                            link.dataset.cbtSubjectActionInFlight = '1';
                            writeSubjectStoredTab(String(link.getAttribute('data-cbt-subject-success-tab') || 'list'));
                            setSubjectActionButtonLoading(link, true, 'Menghapus...');
                            startSubjectProgress(String(link.getAttribute('data-cbt-subject-progress-profile') || 'delete'));

                            runSubjectLocalAction(link, requestUrl, {
                                method: 'GET',
                            })
                                .catch((error) => {
                                    completeSubjectProgress('Gagal memproses CBT Subjects.', error && error.message ? error.message : 'Aksi masih aman dicoba ulang.', 'error');
                                })
                                .finally(() => {
                                    delete link.dataset.cbtSubjectActionInFlight;
                                    setSubjectActionButtonLoading(link, false);
                                });
                        });
                    });
                }

                function bindSubjectImportContinuation() {
                    const progress = document.querySelector('[data-cbt-subject-import-progress]');
                    if (!progress || progress.getAttribute('data-cbt-subject-import-running') !== '1') {
                        return;
                    }
                    if (!supportsPartialListRefresh || subjectImportInFlight) {
                        return;
                    }

                    const continueUrl = String(progress.getAttribute('data-cbt-subject-import-continue-url') || '');
                    if (continueUrl === '') {
                        return;
                    }

                    if (subjectImportTimer) {
                        window.clearTimeout(subjectImportTimer);
                    }

                    subjectImportTimer = window.setTimeout(() => {
                        subjectImportInFlight = true;
                        startSubjectProgress('import');
                        runSubjectLocalAction(progress, new URL(continueUrl, window.location.href), {
                            method: 'GET',
                        })
                            .catch((error) => {
                                completeSubjectProgress('Import subject tertahan.', error && error.message ? error.message : 'Coba lanjutkan import dari halaman Subjects.', 'error');
                            })
                            .finally(() => {
                                subjectImportInFlight = false;
                                bindSubjectImportContinuation();
                            });
                    }, 420);
                }

                function bindSubjectListSelection(panel) {
                    if (!panel) {
                        return;
                    }

                    const selectAll = panel.querySelector('#cbt-subject-select-all');
                    const rowChecks = Array.from(panel.querySelectorAll('.cbt-subject-row-check')).filter((item) => !item.disabled);
                    const selectedButton = panel.querySelector('[data-cbt-subject-bulk-selected]');
                    const selectedCount = panel.querySelector('[data-cbt-subject-selected-count]');

                    function syncSelectState() {
                        const checkedCount = rowChecks.filter((item) => item.checked).length;
                        if (selectAll) {
                            selectAll.checked = checkedCount > 0 && checkedCount === rowChecks.length;
                            selectAll.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
                            selectAll.disabled = rowChecks.length === 0;
                        }
                        if (selectedButton) {
                            selectedButton.disabled = checkedCount === 0;
                            selectedButton.title = checkedCount === 0 ? 'Centang subject yang ingin dihapus terlebih dahulu.' : '';
                        }
                        if (selectedCount) {
                            selectedCount.textContent = checkedCount > 0 ? ' (' + String(checkedCount) + ')' : '';
                        }
                    }

                    if (selectAll && selectAll.dataset.cbtBound !== '1') {
                        selectAll.dataset.cbtBound = '1';
                        selectAll.addEventListener('change', () => {
                            rowChecks.forEach((item) => {
                                item.checked = selectAll.checked;
                            });
                            syncSelectState();
                        });
                    }

                    rowChecks.forEach((item) => {
                        if (item.dataset.cbtBound === '1') {
                            return;
                        }
                        item.dataset.cbtBound = '1';
                        item.addEventListener('change', syncSelectState);
                    });

                    syncSelectState();
                }

                function bindSubjectListPanel() {
                    const panel = getSubjectListPanel();
                    if (!panel) {
                        return;
                    }

                    const subjectFilterForm = panel.querySelector('.cbt-subject-filter-form');
                    const subjectSearch = panel.querySelector('#cbt-subject-search');
                    const subjectPerPageSelect = panel.querySelector('#cbt-subject-per-page');
                    const resetLinks = Array.from(panel.querySelectorAll('.cbt-subject-filter-reset, .cbt-subject-filter-reset-link'));
                    const paginationLinks = Array.from(panel.querySelectorAll('.cbt-admin-pagination-links a'));

                    if (subjectFilterForm && subjectFilterForm.dataset.cbtAsyncBound !== '1') {
                        subjectFilterForm.dataset.cbtAsyncBound = '1';
                        if (supportsPartialListRefresh) {
                            subjectFilterForm.addEventListener('submit', function (event) {
                                event.preventDefault();
                                window.clearTimeout(subjectFilterTimer);
                                submitSubjectFilters(subjectFilterForm);
                            });
                        }
                    }

                    if (subjectSearch && subjectSearch.dataset.cbtAutoBound !== '1') {
                        subjectSearch.dataset.cbtAutoBound = '1';
                        subjectSearch.addEventListener('input', function () {
                            window.clearTimeout(subjectFilterTimer);
                            subjectFilterTimer = window.setTimeout(() => {
                                submitSubjectFilters(subjectFilterForm);
                            }, 350);
                        });
                    }

                    if (subjectPerPageSelect && subjectPerPageSelect.dataset.cbtAutoBound !== '1') {
                        subjectPerPageSelect.dataset.cbtAutoBound = '1';
                        subjectPerPageSelect.addEventListener('change', function () {
                            window.clearTimeout(subjectFilterTimer);
                            submitSubjectFilters(subjectFilterForm);
                        });
                    }

                    if (supportsPartialListRefresh) {
                        resetLinks.concat(paginationLinks).forEach((link) => {
                            if (!link || link.dataset.cbtAsyncBound === '1') {
                                return;
                            }

                            link.dataset.cbtAsyncBound = '1';
                            link.addEventListener('click', function (event) {
                                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                    return;
                                }

                                event.preventDefault();
                                window.clearTimeout(subjectFilterTimer);
                                refreshSubjectListPanel(new URL(link.getAttribute('href') || window.location.href, window.location.href));
                            });
                        });
                    }

                    bindSubjectListSelection(panel);
                }

                bindSubjectListPanel();
                bindSubjectImportContinuation();
            })();
        </script>
