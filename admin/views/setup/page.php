        <?php
        $cbt_admin_view_mode = isset($cbt_admin_view_mode) && $cbt_admin_view_mode === 'security' ? 'security' : 'branding';
        $is_security_view = $cbt_admin_view_mode === 'security';
        $is_branding_view = !$is_security_view;
        $native_security_endpoint_url = isset($native_security_endpoint_url) && is_scalar($native_security_endpoint_url) ? (string) $native_security_endpoint_url : '';
        $security_log_status_snapshot = isset($security_log_status_snapshot) && is_array($security_log_status_snapshot) ? $security_log_status_snapshot : [];
        $security_log_events_enabled = !empty($security_log_events_enabled);
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
        --cbt-success: #10b981;
        --cbt-danger: #ef4444;
        
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
    
    
    .cbt-setup-shell::before {
        content: ''; position: absolute; top: -100px; left: -100px; width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, rgba(255,255,255,0) 70%);
        z-index: -1; border-radius: 50%; filter: blur(60px); pointer-events: none;
    }
    
    
    .cbt-setup-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.9fr); gap: 20px; align-items: start; }
    .cbt-setup-security-masonry { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; align-items: start; }
    .cbt-setup-col { display: grid; gap: 20px; align-content: start; }

            
            
            
    .cbt-setup-hero {
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: stretch;
        gap: 24px;
        padding: 28px;
        border-radius: var(--cbt-radius-lg);
        background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.8) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);border: 1px solid var(--cbt-border-light);
        box-shadow: var(--cbt-shadow-md), var(--cbt-shadow-glow);
        margin-bottom: 24px;
    }
    .cbt-setup-hero::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px;
        background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
    }

            .cbt-setup-hero-copy {
                max-width: 720px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        min-width: 0;}
            .cbt-setup-kicker { display: inline-flex; align-items: center; width: max-content; padding: 6px 14px; border-radius: 999px; background: linear-gradient(135deg, var(--cbt-primary-light), #e0e7ff); color: var(--cbt-primary-hover); font-size: 12px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; box-shadow: var(--cbt-shadow-sm); }
            .cbt-setup-hero h1 { margin: 0; font-size: 32px; font-weight: 800; line-height: 1.1; background: linear-gradient(135deg, #0f172a 0%, #334155 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: -0.02em; }
            .cbt-setup-hero p { margin: 0; max-width: 720px; color: var(--cbt-text-muted); font-size: 15px; line-height: 1.6; }
            
            
            
            
    .cbt-setup-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0; }
    .cbt-setup-tab {
        display: inline-flex; align-items: center; justify-content: center; height: 44px; padding: 0 20px; border-radius: 999px; background: rgba(255, 255, 255, 0.8); color: var(--cbt-text-main); border: 1px solid var(--cbt-border); font-size: 14px; font-weight: 700; cursor: pointer; transition: var(--cbt-transition); text-decoration: none;
    }
    .cbt-setup-tab:hover, .cbt-setup-tab:focus { border-color: var(--cbt-primary); box-shadow: var(--cbt-shadow-sm); background: #ffffff; color: var(--cbt-primary); transform: translateY(-2px); outline: none; }
    .cbt-setup-tab.is-active { background: linear-gradient(135deg, var(--cbt-primary), var(--cbt-secondary)); border-color: transparent; color: #fff; box-shadow: var(--cbt-shadow-md), var(--cbt-shadow-glow);
        margin-bottom: 24px; }

            .cbt-setup-panels {
                display: grid;
                gap: 18px;
            }
            .cbt-setup-panel[hidden] {
                display: none !important;
            }
            .cbt-setup-form {
                display: grid;
                gap: 18px;
            }
            
    .cbt-setup-card {
        padding: 24px;
        border-radius: var(--cbt-radius-lg);
        background: var(--cbt-bg-card);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(20px);border: 1px solid var(--cbt-border);
        box-shadow: var(--cbt-shadow-md);
        transition: var(--cbt-transition);
        word-wrap: break-word;
        overflow-wrap: break-word;
        min-width: 0;}

            .cbt-setup-card-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }
            .cbt-setup-card-header h2 {
                margin: 0 0 6px;
                font-size: 18px;
                line-height: 1.2;
            }
            .cbt-setup-card-header p {
                margin: 0;
                color: #646970;
                line-height: 1.55;
            }
            .cbt-setup-card-chip {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 12px;
                border-radius: 999px;
                background: #f3f4f6;
                color: #334155;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
            }
            .cbt-setup-card-header-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                justify-content: flex-end;
            }
            .cbt-setup-clear-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 40px;
                padding: 0 16px;
                border: 1px solid #c9d8ef;
                border-radius: 14px;
                background: linear-gradient(180deg, #ffffff 0%, #f5f9ff 100%);
                color: #1d4f91;
                box-shadow: 0 8px 18px rgba(59, 130, 246, 0.10);
                font-size: 13px;
                font-weight: 600;
                line-height: 1;
                cursor: pointer;
                transition: border-color 140ms ease, box-shadow 140ms ease, background 140ms ease, transform 140ms ease, color 140ms ease;
            }
            .cbt-setup-clear-button:hover,
            .cbt-setup-clear-button:focus {
                border-color: #7aa7df;
                background: linear-gradient(180deg, #ffffff 0%, #edf5ff 100%);
                color: #143d72;
                box-shadow: 0 12px 24px rgba(59, 130, 246, 0.16);
                transform: translateY(-1px);
                outline: none;
            }
            .cbt-setup-clear-button:active {
                transform: translateY(1px);
            }
            .cbt-setup-field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; align-items: start; }
            .cbt-setup-field { display: grid; gap: 8px; align-content: start; }
            .cbt-setup-field--full {
                grid-column: 1 / -1;
            }
            .cbt-setup-field label {
                font-weight: 600;
                color: #111827;
            }
            .cbt-setup-field input[type="text"],
            .cbt-setup-field input[type="number"],
            .cbt-setup-field textarea {
                width: 100%;
                margin: 0;
                border: 1px solid #c7d2e0;
                border-radius: 12px;
                background: #fbfdff;
                color: #111827;
                padding: 11px 13px;
                transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
            }
            .cbt-setup-field input[type="text"],
            .cbt-setup-field input[type="number"] {
                min-height: 46px;
            }
            .cbt-setup-field textarea {
                min-height: 88px;
                resize: vertical;
                line-height: 1.5;
            }
            .cbt-setup-field input[type="text"]:focus,
            .cbt-setup-field input[type="number"]:focus,
            .cbt-setup-field textarea:focus {
                border-color: #3b82f6;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
                outline: none;
            }
            .cbt-setup-field .description {
                margin: 0;
                color: #6b7280;
                line-height: 1.5;
            }
            .cbt-setup-inline-checkbox {
                display: inline-flex;
                align-items: flex-start;
                gap: 10px;
                margin-top: 12px;
                cursor: pointer;
                color: #111827;
                font-weight: 600;
            }
            .cbt-setup-inline-checkbox input[type="checkbox"] {
                margin: 2px 0 0;
            }
            .cbt-setup-inline-checkbox span {
                display: block;
            }
            .cbt-setup-inline-checkbox small {
                display: block;
                margin-top: 4px;
                color: #5b6574;
                font-weight: 400;
                line-height: 1.5;
            }
            .cbt-setup-logo-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px;
            }
            .cbt-setup-logo-card {
                display: grid;
                gap: 14px;
                padding: 18px;
                border: 1px solid #dde4ee;
                border-radius: 18px;
                background: linear-gradient(180deg, #fbfdff 0%, #f6f9fc 100%);
            }
            .cbt-setup-logo-meta h3 {
                margin: 0 0 6px;
                font-size: 16px;
                line-height: 1.25;
            }
            .cbt-setup-logo-meta p,
            .cbt-setup-logo-note {
                margin: 0;
                color: #646970;
                line-height: 1.55;
            }
            .cbt-setup-logo-preview {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 126px;
                padding: 16px;
                border: 1px dashed #c7d2e0;
                border-radius: 16px;
                background: #ffffff;
            }
            .cbt-setup-logo-preview img {
                display: block;
                max-width: 100%;
                max-height: 92px;
                object-fit: contain;
            }
            .cbt-setup-logo-empty {
                margin: 0;
            }
            .cbt-setup-logo-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-setup-logo-actions .button,
            .cbt-setup-actions .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 46px;
                padding: 0 18px;
                border-radius: 14px;
                font-weight: 600;
                text-decoration: none;
                transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease, background-color 140ms ease, color 140ms ease;
            }
            .cbt-setup-logo-pick-button {
                border-color: #1d5f99;
                background: linear-gradient(180deg, #2f7ab9 0%, #1f68a6 100%);
                color: #ffffff;
                box-shadow: 0 10px 22px rgba(34, 113, 177, 0.18);
            }
            .cbt-setup-logo-pick-button:hover,
            .cbt-setup-logo-pick-button:focus {
                transform: translateY(-1px);
                border-color: #174d7c;
                background: linear-gradient(180deg, #337fbe 0%, #1c629c 100%);
                color: #ffffff;
                box-shadow: 0 14px 28px rgba(34, 113, 177, 0.2);
            }
            .cbt-setup-logo-remove-button {
                border-color: #f2c6c6;
                background: linear-gradient(180deg, #ffffff 0%, #fff7f7 100%);
                color: #b42318;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            }
            .cbt-setup-logo-remove-button:hover,
            .cbt-setup-logo-remove-button:focus {
                transform: translateY(-1px);
                border-color: #e7aaaa;
                background: linear-gradient(180deg, #ffffff 0%, #fff1f1 100%);
                color: #912018;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            }
            .cbt-setup-branding-progress {
                display: none;
                gap: 10px;
                padding: 16px 18px;
                border: 1px solid #bfdbfe;
                border-radius: 18px;
                background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
                box-shadow: 0 16px 32px rgba(37, 99, 235, 0.10);
            }
            .cbt-setup-branding-progress.is-active {
                display: grid;
            }
            .cbt-setup-branding-progress-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-setup-branding-progress-title {
                display: grid;
                gap: 3px;
                min-width: 0;
            }
            .cbt-setup-branding-progress-title strong {
                color: #0f172a;
                font-size: 14px;
                line-height: 1.25;
            }
            .cbt-setup-branding-progress-title span {
                color: #475569;
                font-size: 12px;
                line-height: 1.45;
            }
            .cbt-setup-branding-progress-percent {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 54px;
                min-height: 30px;
                padding: 0 10px;
                border-radius: 999px;
                background: #dbeafe;
                color: #1d4ed8;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.06em;
            }
            .cbt-setup-branding-progress-track {
                height: 9px;
                overflow: hidden;
                border-radius: 999px;
                background: rgba(147, 197, 253, 0.5);
            }
            .cbt-setup-branding-progress-fill {
                display: block;
                width: var(--cbt-branding-progress, 0%);
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb 0%, #0ea5e9 54%, #10b981 100%);
                transition: width 0.28s ease;
            }
            .cbt-setup-branding-progress-step {
                margin: 0;
                color: #334155;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.45;
            }
            .cbt-setup-security-progress {
                display: none;
                gap: 10px;
                margin: 0 0 16px;
                padding: 14px 16px;
                border: 1px solid #bfdbfe;
                border-radius: 18px;
                background: linear-gradient(135deg, rgba(239, 246, 255, 0.96), rgba(240, 253, 250, 0.9));
                box-shadow: 0 12px 28px rgba(59, 130, 246, 0.12);
            }
            .cbt-setup-security-progress.is-active {
                display: grid;
            }
            .cbt-setup-security-progress.is-error {
                border-color: #fecaca;
                background: linear-gradient(135deg, rgba(254, 242, 242, 0.98), rgba(255, 247, 237, 0.92));
                box-shadow: 0 12px 28px rgba(239, 68, 68, 0.10);
            }
            .cbt-setup-security-progress-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-progress-title {
                display: grid;
                gap: 3px;
                min-width: 0;
            }
            .cbt-setup-security-progress-title strong {
                color: #0f172a;
                font-size: 14px;
                line-height: 1.25;
            }
            .cbt-setup-security-progress-title span,
            .cbt-setup-security-progress-step {
                color: #52637a;
                font-size: 13px;
                line-height: 1.45;
            }
            .cbt-setup-security-progress-percent {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 54px;
                min-height: 30px;
                padding: 0 10px;
                border-radius: 999px;
                background: #ffffff;
                color: #1d4ed8;
                border: 1px solid #bfdbfe;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.04em;
            }
            .cbt-setup-security-progress.is-error .cbt-setup-security-progress-percent {
                color: #b91c1c;
                border-color: #fecaca;
            }
            .cbt-setup-security-progress-track {
                height: 9px;
                overflow: hidden;
                border-radius: 999px;
                background: rgba(148, 163, 184, 0.22);
            }
            .cbt-setup-security-progress-fill {
                display: block;
                width: var(--cbt-security-progress, 0%);
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb 0%, #06b6d4 54%, #10b981 100%);
                transition: width 0.24s ease;
            }
            .cbt-setup-security-progress.is-error .cbt-setup-security-progress-fill {
                background: linear-gradient(90deg, #ef4444 0%, #f97316 100%);
            }
            .cbt-setup-security-progress-step {
                margin: 0;
                font-weight: 600;
            }
            .cbt-setup-save-button.is-loading,
            .cbt-setup-logo-pick-button.is-loading,
            .cbt-setup-logo-remove-button.is-loading,
            .cbt-setup-clear-button.is-loading,
            .cbt-setup-security-log-delete-button.is-loading,
            .cbt-native-actions .button.is-loading {
                pointer-events: none;
                opacity: 0.82;
            }
            
    .cbt-setup-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 20px 24px;
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-lg);
        background: var(--cbt-bg-card);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(20px);box-shadow: var(--cbt-shadow-md);
    }

            .cbt-setup-actions .description {
                margin: 0;
                color: #646970;
                line-height: 1.5;
            }
            .cbt-setup-save-button {
                border-color: #1d5f99;
                background: linear-gradient(180deg, #2f7ab9 0%, #1f68a6 100%);
                color: #ffffff;
                box-shadow: 0 10px 22px rgba(34, 113, 177, 0.18);
            }
            .cbt-setup-save-button:hover,
            .cbt-setup-save-button:focus {
                transform: translateY(-1px);
                border-color: #174d7c;
                background: linear-gradient(180deg, #337fbe 0%, #1c629c 100%);
                color: #ffffff;
                box-shadow: 0 14px 28px rgba(34, 113, 177, 0.2);
            }
            .cbt-setup-security-grid {
                display: flex;
                flex-direction: column;
                gap: 28px;
            }
            .cbt-setup-security-card {
                box-sizing: border-box;
            }
            .cbt-setup-security-note {
                padding: 18px;
                border: 1px dashed #c7d2e0;
                border-radius: 18px;
                background: linear-gradient(180deg, #fbfdff 0%, #f8fbff 100%);
            }
            .cbt-setup-security-note p {
                margin: 0;
                color: #4b5563;
                line-height: 1.65;
            }
            .cbt-setup-security-form { display: grid; gap: 20px; }
            .cbt-setup-security-option {
                padding: 16px 18px;
                border: 1px solid #d7e4f5;
                border-radius: 16px;
                background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
            }
            .cbt-setup-security-checkbox {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                cursor: pointer;
            }
            .cbt-setup-security-checkbox input[type="checkbox"] {
                margin: 3px 0 0;
            }
            .cbt-setup-security-checkbox strong {
                display: block;
                margin: 0 0 4px;
                font-size: 14px;
                line-height: 1.35;
                color: #111827;
            }
            .cbt-setup-security-checkbox span {
                display: block;
                color: #5b6574;
                line-height: 1.55;
            }
            .cbt-setup-security-actions { grid-column: 1 / -1;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 18px 22px;
                border: 1px solid #dcdcde;
                border-radius: 18px;
                background: #ffffff;
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            }
            .cbt-setup-security-actions .description {
                margin: 0;
                color: #646970;
                line-height: 1.5;
            }
            .cbt-setup-security-threshold {
                display: grid;
                gap: 8px;
                margin-top: 14px;
                padding-top: 14px;
                border-top: 1px solid rgba(159, 181, 211, 0.4);
            }
            .cbt-setup-security-threshold label {
                font-weight: 600;
                color: #111827;
            }
            .cbt-setup-security-threshold .description {
                margin: 0;
                color: #5b6574;
            }
            .cbt-setup-security-user-agent-list {
                display: grid;
                gap: 8px;
                margin-top: 14px;
                padding-top: 14px;
                border-top: 1px solid rgba(159, 181, 211, 0.4);
            }
            .cbt-setup-security-user-agent-list label {
                font-weight: 600;
                color: #111827;
            }
            .cbt-setup-security-user-agent-list textarea {
                width: min(100%, 620px);
                min-height: 104px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            }
            .cbt-setup-security-user-agent-list .description {
                margin: 0;
                color: #5b6574;
            }
            .cbt-setup-security-log-card {
                grid-column: 1 / -1;
                padding: 24px;
                margin-top: 4px;
            }
            .cbt-setup-security-log-card .cbt-setup-card-header {
                margin-bottom: 18px;
                padding: 0;
                border: 0;
                border-radius: 0;
                background: transparent;
                box-shadow: none;
            }
            .cbt-setup-security-log-chip-group {
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-end;
                gap: 8px;
            }
            .cbt-setup-security-log-body {
                display: grid;
                gap: 18px;
                padding: 0;
            }
            .cbt-setup-security-log-monitor {
                display: grid;
                gap: 14px;
                padding: 16px 18px;
                border: 1px solid #d8e3f3;
                border-radius: 18px;
                background: linear-gradient(180deg, #fbfdff 0%, #f5f9ff 100%);
            }
            .cbt-setup-security-log-monitor.is-warning {
                border-color: #f2d49b;
                background: linear-gradient(180deg, #fffdf8 0%, #fff8eb 100%);
            }
            .cbt-setup-security-log-monitor.is-critical {
                border-color: #f0b8b8;
                background: linear-gradient(180deg, #fffafa 0%, #fff2f2 100%);
            }
            .cbt-setup-security-log-monitor-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-monitor-top h3 {
                margin: 0 0 6px;
                font-size: 15px;
                line-height: 1.3;
            }
            .cbt-setup-security-log-monitor-top p {
                margin: 0;
                color: #5b6574;
                line-height: 1.55;
            }
            .cbt-setup-security-log-monitor-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-monitor-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
            }
            .cbt-setup-security-log-monitor-card {
                display: grid;
                gap: 10px;
                min-width: 0;
                padding: 14px;
                border: 1px solid #d9e6f7;
                border-radius: 14px;
                background: rgba(255, 255, 255, 0.88);
            }
            .cbt-setup-security-log-monitor-card h4 {
                margin: 0;
                font-size: 12px;
                line-height: 1.2;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #3b4d69;
            }
            .cbt-setup-security-log-monitor-list {
                display: grid;
                gap: 8px;
                margin: 0;
            }
            .cbt-setup-security-log-monitor-list div {
                display: grid;
                gap: 2px;
            }
            .cbt-setup-security-log-monitor-list dt {
                margin: 0;
                font-size: 11px;
                line-height: 1.2;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .cbt-setup-security-log-monitor-list dd {
                margin: 0;
                color: #0f172a;
                font-size: 13px;
                line-height: 1.45;
                word-break: break-word;
            }
            .cbt-setup-security-log-monitor-footer {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-monitor-status {
                color: #274367;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-setup-security-log-monitor-disabled {
                color: #8a4b2b;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-setup-security-log-monitor-diagnostics {
                margin: 0;
                padding: 12px;
                border-radius: 12px;
                background: #0f172a;
                color: #dbeafe;
                font-size: 12px;
                line-height: 1.5;
                white-space: pre-wrap;
            }
            .cbt-native-grid {
                display: grid;
                gap: 18px;
            }
            .cbt-native-spec-grid,
            .cbt-native-tool-grid {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
            }
            .cbt-native-spec-card,
            .cbt-native-output-card {
                padding: 16px;
                border: 1px solid #d7e4f5;
                border-radius: 16px;
                background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
            }
            .cbt-native-spec-card h3,
            .cbt-native-output-card h3 {
                margin: 0 0 6px;
                font-size: 14px;
                line-height: 1.3;
            }
            .cbt-native-spec-card p,
            .cbt-native-output-card p {
                margin: 0;
                color: #5b6574;
                line-height: 1.6;
            }
            .cbt-native-list {
                margin: 10px 0 0;
                padding-left: 18px;
                color: #475569;
            }
            .cbt-native-list li {
                margin: 0 0 6px;
            }
            .cbt-native-endpoint {
                display: inline-flex;
                margin-top: 10px;
                padding: 8px 12px;
                border-radius: 12px;
                background: #0f172a;
                color: #e2e8f0;
                font-family: Consolas, Monaco, monospace;
                font-size: 12px;
                line-height: 1.4;
                word-break: break-all;
            }
            .cbt-native-catalog-table-shell {
                overflow-x: auto;
            }
            .cbt-native-catalog-table {
                width: 100%;
                border-collapse: collapse;
            }
            .cbt-native-catalog-table th,
            .cbt-native-catalog-table td {
                padding: 12px 10px;
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
                vertical-align: top;
            }
            .cbt-native-catalog-table th {
                color: #334155;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }
            .cbt-native-catalog-shell {
                display: grid;
                gap: 14px;
            }
            .cbt-native-threshold-grid {
                display: grid;
                gap: 12px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .cbt-native-threshold-card {
                display: grid;
                gap: 4px;
                padding: 12px 14px;
                border: 1px solid #d7e4f5;
                border-radius: 14px;
                background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
            }
            .cbt-native-threshold-card strong {
                color: #0f172a;
                font-size: 12px;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }
            .cbt-native-threshold-card span {
                color: #1f2937;
                font-size: 18px;
                font-weight: 700;
                line-height: 1.2;
            }
            .cbt-native-threshold-card p {
                margin: 0;
                color: #64748b;
                line-height: 1.5;
            }
            .cbt-native-threshold-card.is-high-risk {
                border-color: #f5c7c7;
                background: linear-gradient(180deg, #fff7f7 0%, #fff2f2 100%);
            }
            .cbt-native-catalog-tabs {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-native-catalog-tab {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 32px;
                padding: 0 12px;
                border: 1px solid #cfe0f7;
                border-radius: 999px;
                background: #eef4ff;
                color: #27528c;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.04em;
                cursor: pointer;
                transition: border-color 140ms ease, box-shadow 140ms ease, background 140ms ease, color 140ms ease;
            }
            .cbt-native-catalog-tab:hover,
            .cbt-native-catalog-tab:focus {
                border-color: #8bb3e4;
                background: #f6f9ff;
                color: #173f73;
                box-shadow: 0 8px 16px rgba(59, 130, 246, 0.10);
                outline: none;
            }
            .cbt-native-catalog-tab.is-active {
                border-color: #2563eb;
                background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
                color: #ffffff;
                box-shadow: 0 10px 18px rgba(37, 99, 235, 0.18);
            }
            .cbt-native-catalog-panel[hidden] {
                display: none !important;
            }
            .cbt-native-catalog-empty {
                display: grid;
                gap: 14px;
                padding: 16px;
                border: 1px solid #d7e4f5;
                border-radius: 16px;
                background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
            }
            .cbt-native-catalog-empty h3 {
                margin: 0;
                font-size: 15px;
                line-height: 1.3;
            }
            .cbt-native-catalog-empty p {
                margin: 0;
                color: #5b6574;
                line-height: 1.6;
            }
            .cbt-native-catalog-meta-grid {
                display: grid;
                gap: 12px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .cbt-native-catalog-meta-card {
                display: grid;
                gap: 6px;
                padding: 12px 14px;
                border: 1px solid #d7e4f5;
                border-radius: 14px;
                background: #ffffff;
            }
            .cbt-native-catalog-meta-card strong {
                color: #0f172a;
                font-size: 12px;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }
            .cbt-native-catalog-meta-card code {
                font-size: 12px;
            }
            .cbt-native-code {
                margin: 0;
                padding: 12px 14px;
                border-radius: 14px;
                background: #0f172a;
                color: #e2e8f0;
                font-family: Consolas, Monaco, monospace;
                font-size: 12px;
                line-height: 1.5;
                white-space: pre-wrap;
                word-break: break-word;
            }
            .cbt-native-tool-grid {
                grid-template-columns: minmax(300px, 360px) minmax(0, 1fr);
            }
            .cbt-native-tool-form {
                display: grid;
                gap: 12px;
                align-self: start;
                align-content: start;
                padding: 16px;
                border: 1px solid #d7e4f5;
                border-radius: 16px;
                background: linear-gradient(180deg, #fbfdff 0%, #f7faff 100%);
            }
            .cbt-native-field-grid {
                display: grid;
                gap: 12px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
                align-content: start;
            }
            .cbt-native-field {
                display: grid;
                gap: 6px;
            }
            .cbt-native-field label {
                font-weight: 600;
                color: #111827;
                font-size: 12px;
                letter-spacing: 0.02em;
            }
            .cbt-native-field input[type="text"],
            .cbt-native-field input[type="number"],
            .cbt-native-field textarea,
            .cbt-native-field select {
                width: 100%;
                margin: 0;
                border: 1px solid #c7d2e0;
                border-radius: 12px;
                background: #fbfdff;
                color: #111827;
                padding: 9px 12px;
                min-height: 42px;
            }
            .cbt-native-field textarea {
                min-height: 78px;
                resize: vertical;
            }
            .cbt-native-field--full {
                grid-column: 1 / -1;
            }
            .cbt-native-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-native-helper-note {
                margin: 0;
                color: #64748b;
                line-height: 1.55;
            }
            .cbt-native-auth-grid {
                display: grid;
                gap: 18px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .cbt-native-sample-shell {
                display: grid;
                gap: 12px;
                align-self: start;
            }
            .cbt-native-helper-note + .cbt-native-sample-shell {
                margin-top: 6px;
            }
            .cbt-native-sample-tabs {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-native-sample-tab {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 32px;
                padding: 0 12px;
                border: 1px solid #cfe0f7;
                border-radius: 999px;
                background: #eef4ff;
                color: #27528c;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.04em;
                cursor: pointer;
                transition: border-color 140ms ease, box-shadow 140ms ease, background 140ms ease, color 140ms ease;
            }
            .cbt-native-sample-tab:hover,
            .cbt-native-sample-tab:focus {
                border-color: #8bb3e4;
                background: #f6f9ff;
                color: #173f73;
                box-shadow: 0 8px 16px rgba(59, 130, 246, 0.10);
                outline: none;
            }
            .cbt-native-sample-tab.is-active {
                border-color: #2563eb;
                background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
                color: #ffffff;
                box-shadow: 0 10px 18px rgba(37, 99, 235, 0.18);
            }
            .cbt-native-sample-panel[hidden] {
                display: none !important;
            }
            .cbt-native-sample-panel h3 {
                margin: 0 0 8px;
            }
            .cbt-native-implementation-panel[hidden] {
                display: none !important;
            }
            .cbt-native-implementation-panel p {
                margin: 0 0 10px;
                color: #5b6574;
                line-height: 1.6;
            }
            .cbt-setup-security-log-view-tabs {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-view-tab {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 38px;
                padding: 0 16px;
                border: 1px solid #d8e4f2;
                border-radius: 999px;
                background: #f7fbff;
                color: #315273;
                font-size: 13px;
                font-weight: 700;
                line-height: 1;
                cursor: pointer;
                transition: border-color 140ms ease, background 140ms ease, color 140ms ease, box-shadow 140ms ease;
            }
            .cbt-setup-security-log-view-tab:hover,
            .cbt-setup-security-log-view-tab:focus {
                border-color: #8bb3e4;
                background: #f1f7ff;
                color: #163b63;
                box-shadow: 0 10px 20px rgba(37, 99, 235, 0.10);
                outline: none;
            }
            .cbt-setup-security-log-view-tab.is-active {
                border-color: #2563eb;
                background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
                color: #ffffff;
                box-shadow: 0 12px 22px rgba(37, 99, 235, 0.18);
            }
            .cbt-setup-security-log-view-tab-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 24px;
                min-height: 24px;
                padding: 0 7px;
                border-radius: 999px;
                background: rgba(15, 23, 42, 0.08);
                color: inherit;
                font-size: 11px;
                font-weight: 800;
                line-height: 1;
            }
            .cbt-setup-security-log-view-tab.is-active .cbt-setup-security-log-view-tab-badge {
                background: rgba(255, 255, 255, 0.16);
            }
            .cbt-setup-security-log-view-panels {
                display: grid;
                gap: 18px;
            }
            .cbt-setup-security-log-view-panel[hidden] {
                display: none !important;
            }
            .cbt-setup-security-log-lazy-shell {
                display: grid;
                gap: 12px;
            }
            .cbt-setup-security-log-lazy-placeholder {
                padding: 16px 18px;
                border: 1px dashed #c8d7e8;
                border-radius: 16px;
                background: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(244, 248, 253, 0.96) 100%);
                color: #46607b;
                line-height: 1.55;
            }
            .cbt-setup-security-log-lazy-placeholder strong {
                display: block;
                margin-bottom: 4px;
                color: #17324f;
            }
            .cbt-setup-security-log-roster-region,
            .cbt-setup-security-log-watch-region,
            .cbt-setup-security-log-table-region {
                display: grid;
                gap: 16px;
            }
            .cbt-setup-security-log-roster {
                display: grid;
                gap: 14px;
                padding: 18px;
                border: 1px solid #d7e5f4;
                border-radius: 18px;
                background:
                    radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 34%),
                    linear-gradient(180deg, #fbfdff 0%, #f4f8fd 100%);
            }
            .cbt-setup-security-log-roster-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-roster-header h3 {
                margin: 0 0 4px;
                font-size: 16px;
                line-height: 1.2;
                color: #111827;
            }
            .cbt-setup-security-log-roster-header p {
                margin: 0;
                color: #5b6574;
                line-height: 1.55;
            }
            .cbt-setup-security-log-roster-empty {
                padding: 14px 16px;
                border: 1px dashed #b7cde6;
                border-radius: 14px;
                background: rgba(255, 255, 255, 0.7);
                color: #38506a;
                line-height: 1.55;
            }
            .cbt-setup-security-log-roster-toolbar {
                display: grid;
                gap: 14px;
                padding: 16px;
                border: 1px solid #d8e6f5;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.86);
            }
            .cbt-setup-security-log-roster-filter-grid {
                display: grid;
                gap: 12px;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
            .cbt-setup-security-log-roster-filter-field {
                display: grid;
                gap: 6px;
            }
            .cbt-setup-security-log-roster-filter-field span {
                color: #1e3a5f;
                font-size: 12px;
                font-weight: 700;
                line-height: 1.3;
                letter-spacing: 0.02em;
            }
            .cbt-setup-security-log-roster-filter-field input,
            .cbt-setup-security-log-roster-filter-field select {
                width: 100%;
                min-height: 42px;
                margin: 0;
                border: 1px solid #c9d8e8;
                border-radius: 12px;
                background: #ffffff;
                color: #0f172a;
                padding: 9px 12px;
                box-shadow: inset 0 1px 1px rgba(15, 23, 42, 0.03);
            }
            .cbt-setup-security-log-roster-filter-field input:focus,
            .cbt-setup-security-log-roster-filter-field select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
                outline: none;
            }
            .cbt-setup-security-log-roster-toolbar-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px 12px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-roster-summary {
                color: #526072;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-setup-security-log-roster-pagination {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-roster-page-label {
                color: #334155;
                font-size: 12px;
                font-weight: 700;
                line-height: 1.4;
                white-space: nowrap;
            }
            .cbt-setup-security-log-roster-groups {
                display: grid;
                gap: 14px;
            }
            .cbt-setup-security-log-roster-group {
                display: grid;
                gap: 12px;
                padding: 16px;
                border: 1px solid #d9e4f0;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.92);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.03);
            }
            .cbt-setup-security-log-roster-group-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px 12px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-roster-group-copy {
                display: grid;
                gap: 6px;
                min-width: 0;
            }
            .cbt-setup-security-log-roster-group-title {
                font-size: 14px;
                font-weight: 700;
                line-height: 1.35;
                color: #0f172a;
            }
            .cbt-setup-security-log-roster-group-meta {
                display: flex;
                align-items: center;
                gap: 6px 12px;
                flex-wrap: wrap;
                color: #475569;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-setup-security-log-roster-group-counters {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                justify-content: flex-end;
            }
            .cbt-setup-security-log-roster-list {
                display: grid;
                gap: 10px;
            }
            .cbt-setup-security-log-roster-row {
                display: grid;
                gap: 8px;
                padding: 12px 14px;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }
            .cbt-setup-security-log-roster-row-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 8px 10px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-roster-row-copy {
                display: grid;
                gap: 2px;
                min-width: 0;
            }
            .cbt-setup-security-log-roster-row-copy strong {
                font-size: 13px;
                line-height: 1.35;
                color: #0f172a;
            }
            .cbt-setup-security-log-roster-row-copy span {
                font-size: 12px;
                color: #64748b;
                line-height: 1.45;
            }
            .cbt-setup-security-log-roster-row-side {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
                justify-content: flex-end;
            }
            .cbt-setup-security-log-roster-row-meta {
                display: flex;
                align-items: center;
                gap: 6px 12px;
                flex-wrap: wrap;
                color: #475569;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-setup-security-log-roster-row-indicators {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-roster-row-actions {
                display: flex;
                justify-content: flex-start;
            }
            .cbt-setup-security-log-watch-indicator.is-roster-stale {
                border-color: #f6db9a;
                background: #fff8e7;
                color: #9a6700;
            }
            .cbt-setup-security-log-watch-indicator.is-roster-offline {
                border-color: #d7dee9;
                background: #f4f7fb;
                color: #475569;
            }
            .cbt-setup-security-log-watch-indicator.is-roster-watch {
                border-color: #f6db9a;
                background: #fff6e2;
                color: #9a6700;
            }
            .cbt-setup-security-log-watch-indicator.is-roster-risk {
                border-color: #f3c0c0;
                background: #fff1f1;
                color: #b42318;
            }
            .cbt-setup-security-log-watch {
                display: grid;
                gap: 14px;
                padding: 18px;
                border: 1px solid #fde2b7;
                border-radius: 18px;
                background:
                    radial-gradient(circle at top right, rgba(251, 191, 36, 0.14), transparent 34%),
                    linear-gradient(180deg, #fffdf8 0%, #fffaf1 100%);
            }
            .cbt-setup-security-log-watch-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-watch-head-actions {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-watch-header h3 {
                margin: 0 0 4px;
                font-size: 16px;
                line-height: 1.2;
                color: #111827;
            }
            .cbt-setup-security-log-watch-header p {
                margin: 0;
                color: #5b6574;
                line-height: 1.55;
            }
            .cbt-setup-security-log-watch-sort {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 4px;
                border: 1px solid #e6ebf2;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.72);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
            }
            .cbt-setup-security-log-watch-sort-button {
                min-height: 30px;
                padding: 0 12px;
                border: 0;
                border-radius: 999px;
                background: transparent;
                color: #526072;
                font-size: 12px;
                font-weight: 700;
                line-height: 1;
                cursor: pointer;
                transition: background-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease;
                white-space: nowrap;
            }
            .cbt-setup-security-log-watch-sort-button:hover,
            .cbt-setup-security-log-watch-sort-button:focus {
                background: #eef4fb;
                color: #163b63;
                outline: none;
            }
            .cbt-setup-security-log-watch-sort-button.is-active {
                background: linear-gradient(180deg, #2f7bc0 0%, #2168ac 100%);
                color: #ffffff;
                box-shadow: 0 6px 16px rgba(33, 104, 172, 0.18);
            }
            .cbt-setup-security-log-watch-empty {
                padding: 14px 16px;
                border: 1px dashed #f2c979;
                border-radius: 14px;
                background: rgba(255, 255, 255, 0.66);
                color: #7c5b18;
                line-height: 1.55;
            }
            .cbt-setup-security-log-watch-list {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                align-items: start;
                max-height: min(62vh, 620px);
                overflow-y: auto;
                padding-right: 8px;
                align-content: start;
                scrollbar-gutter: stable;
            }
            .cbt-setup-security-log-watch-list::-webkit-scrollbar {
                width: 10px;
            }
            .cbt-setup-security-log-watch-list::-webkit-scrollbar-track {
                background: rgba(255, 255, 255, 0.55);
                border-radius: 999px;
            }
            .cbt-setup-security-log-watch-list::-webkit-scrollbar-thumb {
                background: rgba(180, 120, 18, 0.28);
                border: 2px solid rgba(255, 255, 255, 0.6);
                border-radius: 999px;
            }
            .cbt-setup-security-log-watch-item {
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding: 16px 18px;
                border: 1px solid #f0d7a6;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.9);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
                cursor: pointer;
                transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
            }
            .cbt-setup-security-log-watch-item:hover {
                transform: translateY(-1px);
                box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
            }
            .cbt-setup-security-log-watch-item.is-watch {
                border-color: #f5d48a;
                background: linear-gradient(180deg, #fffefa 0%, #fff8eb 100%);
            }
            .cbt-setup-security-log-watch-item.is-high-risk {
                border-color: #f0b7b7;
                background: linear-gradient(180deg, #fffafa 0%, #fff1f1 100%);
            }
            .cbt-setup-security-log-watch-item-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px 12px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-watch-item-copy {
                display: grid;
                gap: 7px;
                min-width: 0;
            }
            .cbt-setup-security-log-watch-item-groups {
                display: grid;
                gap: 10px;
            }
            .cbt-setup-security-log-watch-item-student {
                min-width: 0;
            }
            .cbt-setup-security-log-watch-item-student strong {
                font-size: 14px;
                line-height: 1.35;
                color: #0f172a;
            }
            .cbt-setup-security-log-watch-item-summary {
                color: #334155;
                font-size: 13px;
                line-height: 1.55;
            }
            .cbt-setup-security-log-watch-item-meta {
                display: flex;
                align-items: center;
                gap: 6px 12px;
                flex-wrap: wrap;
                color: #475569;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-setup-security-log-watch-item-device {
                font-size: 12px;
                color: #64748b;
                line-height: 1.45;
            }
            .cbt-setup-security-log-watch-item-side {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
                justify-content: flex-end;
            }
            .cbt-setup-security-log-watch-item-group {
                display: grid;
                gap: 8px;
                padding: 12px;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                background: #ffffff;
            }
            .cbt-setup-security-log-watch-item-group.is-live {
                border-color: #cfe2f8;
                background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
            }
            .cbt-setup-security-log-watch-item-group.is-history {
                border-color: #e2e8f0;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            }
            .cbt-setup-security-log-watch-item-group-label {
                font-size: 11px;
                font-weight: 700;
                line-height: 1.2;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            .cbt-setup-security-log-watch-item-group.is-live .cbt-setup-security-log-watch-item-group-label {
                color: #1d4ed8;
            }
            .cbt-setup-security-log-watch-item-group.is-history .cbt-setup-security-log-watch-item-group-label {
                color: #475569;
            }
            .cbt-setup-security-log-watch-item-group-meta {
                display: flex;
                align-items: center;
                gap: 6px 12px;
                flex-wrap: wrap;
                color: #475569;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-setup-security-log-watch-item-presence-indicators {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-watch-item-indicators {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-watch-indicator {
                display: inline-flex;
                align-items: center;
                flex: 0 0 auto;
                min-height: 28px;
                padding: 0 10px;
                border: 1px solid #d7dee9;
                border-radius: 999px;
                background: #f8fafc;
                color: #475569;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
            }
            .cbt-setup-security-log-watch-indicator.is-presence {
                border-color: #cfe2f8;
                background: #eef6ff;
                color: #1d4ed8;
            }
            .cbt-setup-security-log-watch-item-actions {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
                margin-top: 4px;
            }
            .cbt-setup-security-log-watch-item-actions form {
                margin: 0;
                display: block;
            }
            .cbt-setup-security-log-watch-item-actions .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                min-height: 36px;
                line-height: 1.2;
                padding: 8px 10px;
                white-space: nowrap;
            }
            @media (max-width: 1320px) {
                .cbt-setup-security-log-watch-item-actions {
                    grid-template-columns: repeat(auto-fit, minmax(104px, 1fr));
                }
            }
            .cbt-setup-security-log-manage-form {
                display: grid;
                gap: 18px;
            }
            .cbt-setup-security-log-toolbar {
                display: grid;
                gap: 16px;
                padding: 16px;
                border: 1px solid #dde4ee;
                border-radius: 16px;
                background: linear-gradient(180deg, #fbfdff 0%, #f6f9fc 100%);
            }
            .cbt-setup-security-log-filter-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 14px 12px;
            }
            .cbt-setup-security-log-toolbar-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                flex-wrap: wrap;
                padding-top: 2px;
                border-top: 1px solid #e2e8f0;
            }
            .cbt-setup-security-log-toolbar-live {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
                min-width: 0;
            }
            .cbt-setup-security-log-toolbar-actions {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 12px;
                flex-wrap: wrap;
                margin-left: auto;
            }
            .cbt-setup-security-log-toolbar-actions .button {
                min-height: 40px;
                padding: 0 14px;
                border-radius: 12px;
                font-weight: 600;
            }
            .cbt-setup-security-log-delete-button {
                border-color: #f2c6c6;
                background: linear-gradient(180deg, #ffffff 0%, #fff7f7 100%);
                color: #b42318;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            }
            .cbt-setup-security-log-delete-button:hover,
            .cbt-setup-security-log-delete-button:focus {
                transform: translateY(-1px);
                border-color: #e7aaaa;
                background: linear-gradient(180deg, #ffffff 0%, #fff1f1 100%);
                color: #912018;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            }
            .cbt-setup-security-log-delete-button[disabled] {
                opacity: 0.55;
                cursor: not-allowed;
                box-shadow: none;
                transform: none;
            }
            .cbt-setup-security-log-filter {
                display: grid;
                gap: 6px;
                min-width: 0;
            }
            .cbt-setup-security-log-filter label {
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                color: #334155;
            }
            .cbt-setup-security-log-filter select {
                min-height: 40px;
                width: 100%;
                padding: 0 12px;
                border: 1px solid #c7d2e0;
                border-radius: 12px;
                background: #fbfdff;
                color: #111827;
                box-shadow: none;
            }
            .cbt-setup-security-log-filter input[type="search"] {
                min-height: 40px;
                width: 100%;
                padding: 0 12px;
                border: 1px solid #c7d2e0;
                border-radius: 12px;
                background: #fbfdff;
                color: #111827;
                box-shadow: none;
            }
            .cbt-setup-security-log-filter select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
                outline: none;
            }
            .cbt-setup-security-log-filter input[type="search"]:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
                outline: none;
            }
            .cbt-setup-security-log-auto-refresh {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                min-height: 40px;
                padding: 0 14px;
                border: 1px solid #d7e4f5;
                border-radius: 999px;
                background: #f8fbff;
                color: #1d4f91;
                font-weight: 600;
                cursor: pointer;
            }
            .cbt-setup-security-log-auto-refresh input[type="checkbox"] {
                margin: 0;
            }
            .cbt-setup-security-log-live-status {
                font-size: 12px;
                color: #64748b;
                line-height: 1.5;
                display: inline-flex;
                align-items: center;
                min-height: 40px;
            }
            .cbt-setup-security-log-focus-state {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 40px;
                padding: 0 14px;
                border: 1px solid #f3d28e;
                border-radius: 999px;
                background: #fff9ec;
                color: #8a5a00;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.4;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-focus-state .button-link {
                padding: 0;
                min-height: auto;
                color: #0f4fa8;
                font-weight: 700;
                text-decoration: none;
                cursor: pointer;
            }
            .cbt-setup-security-log-focus-state .button-link:hover,
            .cbt-setup-security-log-focus-state .button-link:focus {
                color: #0b3b7d;
                text-decoration: underline;
            }
            .cbt-setup-security-log-live-status.is-loading {
                color: #0f4fa8;
            }
            .cbt-setup-security-log-live-status.is-error {
                color: #b42318;
            }
            .cbt-setup-security-log-table-shell {
                overflow-x: auto;
                border: 1px solid #dde4ee;
                border-radius: 16px;
                background: #ffffff;
            }
            .cbt-setup-security-log-table {
                margin: 0;
                border: 0;
                box-shadow: none;
            }
            .cbt-setup-security-log-table .check-column {
                width: 42px;
                padding-left: 12px;
                padding-right: 8px;
            }
            .cbt-setup-security-log-table .check-column input[type="checkbox"] {
                margin: 0;
            }
            .cbt-setup-security-log-table thead th {
                background: #f8fbff;
                color: #334155;
            }
            .cbt-setup-security-log-table td {
                vertical-align: top;
            }
            .cbt-setup-security-log-attempt {
                font-weight: 600;
                color: #1d4f91;
            }
            .cbt-setup-security-log-student {
                display: grid;
                gap: 6px;
            }
            .cbt-setup-security-log-student-name {
                font-weight: 600;
                color: #0f172a;
            }
            .cbt-setup-security-log-student-meta {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                color: #64748b;
                font-size: 12px;
                line-height: 1.45;
            }
            .cbt-setup-security-log-student-meta span {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                border: 1px solid #d7e4f5;
                border-radius: 999px;
                background: #f8fbff;
            }
            .cbt-setup-security-log-student-meta span strong {
                font-weight: 700;
            }
            .cbt-setup-security-log-student-meta .is-kelas {
                border-color: #bfdbfe;
                background: #eff6ff;
                color: #1d4ed8;
            }
            .cbt-setup-security-log-student-meta .is-ruang {
                border-color: #c7ead8;
                background: #effcf5;
                color: #15803d;
            }
            .cbt-setup-security-log-event {
                display: grid;
                gap: 6px;
            }
            .cbt-setup-security-log-event-badges {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .cbt-setup-security-log-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 24px;
                padding: 0 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .cbt-setup-security-log-badge.is-warning {
                background: #fff4db;
                color: #9a6700;
            }
            .cbt-setup-security-log-badge.is-critical {
                background: #ffe3e3;
                color: #b42318;
            }
            .cbt-setup-security-log-badge.is-info {
                background: #e8f1ff;
                color: #0f4fa8;
            }
            .cbt-setup-security-log-badge.is-watch {
                background: #fff4db;
                color: #9a6700;
            }
            .cbt-setup-security-log-badge.is-high-risk {
                background: #ffe3e3;
                color: #b42318;
            }
            .cbt-setup-security-log-badge.is-score {
                background: #eef2ff;
                color: #4338ca;
            }
            .cbt-setup-security-log-badge.is-device-desktop {
                background: #eff6ff;
                color: #1d4ed8;
            }
            .cbt-setup-security-log-badge.is-device-mobile {
                background: #ecfdf5;
                color: #15803d;
            }
            .cbt-setup-security-log-badge.is-device-tablet {
                background: #fff7ed;
                color: #c2410c;
            }
            .cbt-setup-security-log-badge.is-device-server {
                background: #f3f4f6;
                color: #4b5563;
            }
            .cbt-setup-security-log-badge.is-device-unknown {
                background: #f8fafc;
                color: #64748b;
            }
            .cbt-setup-security-log-badge.is-presence-online {
                background: #ecfdf5;
                color: #15803d;
            }
            .cbt-setup-security-log-badge.is-presence-stale {
                background: #fff7ed;
                color: #c2410c;
            }
            .cbt-setup-security-log-badge.is-presence-offline {
                background: #f1f5f9;
                color: #475569;
            }
            .cbt-setup-security-log-event-meta {
                font-size: 12px;
                color: #64748b;
                line-height: 1.45;
            }
            .cbt-setup-security-log-detail {
                min-width: 240px;
                color: #4b5563;
                line-height: 1.55;
            }
            .cbt-setup-security-log-detail-copy {
                margin: 0;
            }
            .cbt-setup-security-log-json {
                margin-top: 10px;
            }
            .cbt-setup-security-log-json summary {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 28px;
                padding: 0 10px;
                border: 1px solid #cbd5e1;
                border-radius: 999px;
                background: #f8fbff;
                color: #1d4f91;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                cursor: pointer;
                user-select: none;
                list-style: none;
            }
            .cbt-setup-security-log-json summary::-webkit-details-marker {
                display: none;
            }
            .cbt-setup-security-log-json[open] summary {
                background: #e8f1ff;
                border-color: #bfdbfe;
                color: #0f4fa8;
            }
            .cbt-setup-security-log-json-pre {
                margin: 10px 0 0;
                padding: 12px 14px;
                border-radius: 12px;
                background: #0f172a;
                color: #dbeafe;
                font-size: 12px;
                line-height: 1.6;
                white-space: pre-wrap;
                word-break: break-word;
                overflow-x: auto;
            }
            .cbt-setup-security-log-empty {
                padding: 20px 18px !important;
                text-align: center;
                color: #6b7280;
            }
            @media (max-width: 960px) {
                .cbt-setup-hero,
                .cbt-setup-card-header,
                .cbt-setup-actions,
                .cbt-setup-security-actions { grid-column: 1 / -1;
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-setup-security-log-toolbar-live,
                .cbt-setup-security-log-toolbar-footer,
                .cbt-setup-security-log-toolbar-actions,
                .cbt-setup-security-log-watch-header,
                .cbt-setup-security-log-roster-toolbar-footer,
                .cbt-setup-security-log-roster-header,
                .cbt-setup-security-log-roster-group-top,
                .cbt-setup-security-log-roster-row-top {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-setup-security-log-toolbar-actions {
                    margin-left: 0;
                }
                .cbt-setup-security-log-watch-item-top {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-setup-security-log-watch-list {
                    max-height: none;
                    overflow: visible;
                    padding-right: 0;
                }
                .cbt-setup-security-log-watch-item-side {
                    justify-content: flex-start;
                }
                .cbt-setup-security-log-roster-group-counters,
                .cbt-setup-security-log-roster-row-side {
                    justify-content: flex-start;
                }
                .cbt-setup-security-log-monitor-top,
                .cbt-setup-security-log-monitor-footer {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-setup-security-log-monitor-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
                .cbt-setup-security-log-watch-item-actions {
                    display: grid;
                    grid-template-columns: 1fr;
                }
                .cbt-setup-field-grid,
                .cbt-setup-logo-grid {
                    grid-template-columns: 1fr;
                }
            }
            @media (max-width: 782px) {
                
                .cbt-setup-hero,
                
    .cbt-setup-card {
        padding: 24px;
        border-radius: var(--cbt-radius-lg);
        background: var(--cbt-bg-card);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(20px);border: 1px solid var(--cbt-border);
        box-shadow: var(--cbt-shadow-md);
        transition: var(--cbt-transition);
        word-wrap: break-word;
        overflow-wrap: break-word;
        min-width: 0;}

                .cbt-native-spec-grid,
                .cbt-native-tool-grid,
                .cbt-native-auth-grid,
                .cbt-native-field-grid,
                .cbt-native-threshold-grid,
                .cbt-native-catalog-meta-grid {
                    grid-template-columns: 1fr;
                }
                .cbt-setup-security-log-card {
                    padding: 20px;
                    margin-top: 2px;
                }
                .cbt-setup-security-log-card .cbt-setup-card-header {
                    margin-bottom: 16px;
                }
                .cbt-setup-security-log-body {
                    padding: 0;
                }
                .cbt-setup-security-log-monitor-grid {
                    grid-template-columns: 1fr;
                }
                .cbt-setup-security-log-monitor-actions .button {
                    width: 100%;
                }
            }
        </style>
        <div class="wrap cbt-setup-page"<?php echo $is_security_view ? ' data-security-refresh-root' : ''; ?>>
            <div class="cbt-setup-shell">
                <section class="cbt-setup-hero">
                    <div class="cbt-setup-hero-copy">
                        <h1><?php echo esc_html($is_security_view ? 'CBT Security' : 'CBT Branding'); ?></h1>
                        <p>
                            <?php
                            echo esc_html(
                                $is_security_view
                                    ? 'Kelola kontrol keamanan ujian dan pantau histori security log dalam satu area observability yang terpisah dari branding.'
                                    : 'Kelola branding sekolah untuk frontend CBT dan dokumen terkait. Area security kini dipisahkan ke menu CBT Security agar pengelolaan lebih rapi.'
                            );
                            ?>
                        </p>
                        <?php if ($is_security_view): ?>
                            <div class="cbt-setup-tabs" role="tablist" aria-label="Bagian security CBT">
                                <button type="button" class="cbt-setup-tab is-active" id="cbt-setup-tab-security" data-setup-tab-button="security" role="tab" aria-selected="true" aria-controls="cbt-setup-panel-security">Security</button>
                                <button type="button" class="cbt-setup-tab" id="cbt-setup-tab-security-log" data-setup-tab-button="security-log" role="tab" aria-selected="false" aria-controls="cbt-setup-panel-security-log">Security Log</button>
                                <button type="button" class="cbt-setup-tab" id="cbt-setup-tab-native" data-setup-tab-button="native" role="tab" aria-selected="false" aria-controls="cbt-setup-panel-native">Native Notif</button>
                                <button type="button" class="cbt-setup-tab" id="cbt-setup-tab-catalog" data-setup-tab-button="catalog" role="tab" aria-selected="false" aria-controls="cbt-setup-panel-catalog">Catalog</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="cbt-setup-notices" data-security-refresh-area="notices">
                    <?php if ($notice): ?>
                        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                    <?php endif; ?>
                </div>
                <?php if ($is_security_view): ?>
                    <div class="cbt-setup-security-progress" data-security-progress role="status" aria-live="polite" aria-hidden="true">
                        <div class="cbt-setup-security-progress-head">
                            <div class="cbt-setup-security-progress-title">
                                <strong data-security-progress-label>Menunggu aksi CBT Security...</strong>
                                <span>Progress ini hanya memperbarui area Security yang terdampak, tanpa reload halaman global.</span>
                            </div>
                            <span class="cbt-setup-security-progress-percent" data-security-progress-percent>0%</span>
                        </div>
                        <div class="cbt-setup-security-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-security-progress-track>
                            <span class="cbt-setup-security-progress-fill" data-security-progress-fill></span>
                        </div>
                        <p class="cbt-setup-security-progress-step" data-security-progress-step>Siap memproses perubahan security.</p>
                    </div>
                <?php endif; ?>

                <div class="cbt-setup-panels">
                    <?php if ($is_branding_view): ?>
                    <div class="cbt-setup-panel is-active" id="cbt-setup-panel-branding" data-setup-panel="branding" role="region" aria-label="Branding">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-setup-form" id="cbt-setup-branding-form" data-branding-form>
                            <?php wp_nonce_field('cbt_save_setup_branding'); ?>
                            <input type="hidden" name="action" value="cbt_save_setup_branding" />
                            <div class="cbt-setup-branding-progress" data-branding-progress role="status" aria-live="polite" aria-hidden="true">
                                <div class="cbt-setup-branding-progress-head">
                                    <div class="cbt-setup-branding-progress-title">
                                        <strong data-branding-progress-label>Menyimpan CBT Branding...</strong>
                                        <span>Area ini memberi status proses sebelum halaman berpindah ke hasil simpan.</span>
                                    </div>
                                    <span class="cbt-setup-branding-progress-percent" data-branding-progress-percent>0%</span>
                                </div>
                                <div class="cbt-setup-branding-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-branding-progress-track>
                                    <span class="cbt-setup-branding-progress-fill" data-branding-progress-fill></span>
                                </div>
                                <p class="cbt-setup-branding-progress-step" data-branding-progress-step>Menunggu perubahan branding.</p>
                            </div>

                            <section class="cbt-setup-card">
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Identitas Sekolah</h2>
                                        <p>Informasi ini dipakai untuk branding sekolah pada area CBT dan dokumen cetak yang terkait.</p>
                                    </div>
                                    <div class="cbt-setup-card-header-actions">
                                        <button type="button" id="cbt-setup-clear-identity" class="cbt-setup-clear-button">Clear Identitas</button>
                                    </div>
                                </div>
                                <div class="cbt-setup-field-grid" id="cbt-setup-identity-fields">
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-exam-program-name">Nama Program Ujian</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-exam-program-name"
                                            name="exam_program_name"
                                            value="<?php echo esc_attr($exam_program_name); ?>"
                                            placeholder="Contoh: ANBK, UTS, UAS, USBK"
                                        />
                                        <p class="description">Opsional. Dipakai untuk identitas program ujian pada dokumen cetak dan area branding CBT yang relevan.</p>
                                    </div>
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-school-name">Nama Sekolah CBT</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-name"
                                            name="school_name"
                                            value="<?php echo esc_attr($school_name); ?>"
                                            placeholder="<?php echo esc_attr((string) get_bloginfo('name')); ?>"
                                        />
                                        <p class="description">Jika kosong, otomatis memakai nama situs WordPress.</p>
                                    </div>
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-school-motto">Moto Sekolah</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-motto"
                                            name="school_motto"
                                            value="<?php echo esc_attr($school_motto); ?>"
                                            placeholder="Contoh: Berkarakter, Unggul, dan Berprestasi"
                                        />
                                        <p class="description">Opsional. Akan dipakai sebagai teks pembuka di frontend CBT dan tagline pada dokumen cetak yang mendukung branding.</p>
                                    </div>
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-school-npsn">NPSN</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-npsn"
                                            name="school_npsn"
                                            value="<?php echo esc_attr($school_npsn); ?>"
                                            placeholder="Contoh: 10900452"
                                        />
                                    </div>
                                    <div class="cbt-setup-field cbt-setup-field--full">
                                        <label for="cbt-setup-school-address">Alamat</label>
                                        <textarea
                                            id="cbt-setup-school-address"
                                            name="school_address"
                                            rows="3"
                                            placeholder="Contoh: Jl. Jendral Sudirman KM. 7, Perawas"
                                        ><?php echo esc_textarea($school_address); ?></textarea>
                                        <p class="description">Bisa diisi sampai 3 baris agar alamat sekolah lebih rapi dan lengkap.</p>
                                    </div>
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-school-village">Desa/Kelurahan</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-village"
                                            name="school_village"
                                            value="<?php echo esc_attr($school_village); ?>"
                                            placeholder="Contoh: PERAWAS"
                                        />
                                    </div>
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-school-district-city-ln">Kecamatan/Kota (LN)</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-district-city-ln"
                                            name="school_district_city_ln"
                                            value="<?php echo esc_attr($school_district_city_ln); ?>"
                                            placeholder="Contoh: KEC. TANJUNG PANDAN"
                                        />
                                    </div>
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-school-regency-country-ln">Kabupaten/Kota</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-regency-country-ln"
                                            name="school_regency_country_ln"
                                            value="<?php echo esc_attr($school_regency_country_ln); ?>"
                                            placeholder="Contoh: BELITUNG atau PANGKALPINANG"
                                        />
                                        <label class="cbt-setup-inline-checkbox" for="cbt-setup-school-regency-country-ln-is-city">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-school-regency-country-ln-is-city"
                                                name="school_regency_country_ln_is_city"
                                                value="1"
                                                <?php checked(!empty($school_regency_country_ln_is_city)); ?>
                                            />
                                            <span>
                                                Wilayah ini adalah Kota
                                                <small>Jika tidak dicentang, report exam dan exam card akan menampilkan Kabupaten. Prefix seperti Kab. atau Kota tidak perlu ditulis lagi.</small>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-school-province-abroad-ln">Propinsi/Luar Negeri (LN)</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-province-abroad-ln"
                                            name="school_province_abroad_ln"
                                            value="<?php echo esc_attr($school_province_abroad_ln); ?>"
                                            placeholder="Contoh: KEPULAUAN BANGKA BELITUNG atau SINGAPURA"
                                        />
                                        <label class="cbt-setup-inline-checkbox" for="cbt-setup-school-province-abroad-ln-is-foreign">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-school-province-abroad-ln-is-foreign"
                                                name="school_province_abroad_ln_is_foreign"
                                                value="1"
                                                <?php checked(!empty($school_province_abroad_ln_is_foreign)); ?>
                                            />
                                            <span>
                                                Wilayah ini adalah Luar Negeri
                                                <small>Jika dicentang, report exam dan exam card akan menampilkan Luar Negeri. Prefix seperti Prov., Propinsi, atau Luar Negeri tidak perlu ditulis lagi.</small>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <section class="cbt-setup-card">
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Logo Brand</h2>
                                        <p>Pilih logo yang dipakai pada area frontend CBT, kartu ujian, dan report cetak.</p>
                                    </div>
                                    <span class="cbt-setup-card-chip">Media Library</span>
                                </div>
                                <div class="cbt-setup-logo-grid">
                                    <article class="cbt-setup-logo-card">
                                        <input
                                            type="hidden"
                                            id="cbt-setup-logo-1-attachment-id"
                                            name="logo_1_attachment_id"
                                            value="<?php echo esc_attr($logo_1_attachment_id > 0 ? (string) $logo_1_attachment_id : ''); ?>"
                                        />
                                        <div class="cbt-setup-logo-meta">
                                            <h3>Logo 1 · Sekolah</h3>
                                            <p>Dipakai untuk topbar frontend, hero login, mobile brand, kartu ujian, dan report exam.</p>
                                        </div>
                                        <div
                                            id="cbt-setup-logo-1-preview"
                                            class="cbt-setup-logo-preview"
                                            style="display:<?php echo $logo_1_url !== '' ? 'flex' : 'none'; ?>;"
                                        >
                                            <img
                                                id="cbt-setup-logo-1-preview-image"
                                                src="<?php echo esc_url($logo_1_url); ?>"
                                                alt=""
                                            />
                                        </div>
                                        <p id="cbt-setup-logo-1-empty" class="description cbt-setup-logo-empty" style="display:<?php echo $logo_1_url === '' ? 'block' : 'none'; ?>;">Belum ada logo dipilih.</p>
                                        <div class="cbt-setup-logo-actions">
                                            <button type="button" id="cbt-setup-logo-1-pick" class="button cbt-setup-logo-pick-button">
                                                <?php echo esc_html($logo_1_attachment_id > 0 ? 'Ganti Logo' : 'Pilih Logo'); ?>
                                            </button>
                                            <button type="button" id="cbt-setup-logo-1-remove" class="button button-secondary cbt-setup-logo-remove-button" style="display:<?php echo $logo_1_attachment_id > 0 ? 'inline-flex' : 'none'; ?>;">Hapus Logo</button>
                                        </div>
                                        <p class="cbt-setup-logo-note">Gunakan gambar dari Media Library WordPress dengan latar transparan bila memungkinkan.</p>
                                    </article>

                                    <article class="cbt-setup-logo-card">
                                        <input
                                            type="hidden"
                                            id="cbt-setup-logo-2-attachment-id"
                                            name="logo_2_attachment_id"
                                            value="<?php echo esc_attr($logo_2_attachment_id > 0 ? (string) $logo_2_attachment_id : ''); ?>"
                                        />
                                        <div class="cbt-setup-logo-meta">
                                            <h3>Logo 2 · Dinas Pendidikan</h3>
                                            <p>Dipakai untuk pasangan logo pada kartu ujian dan report exam.</p>
                                        </div>
                                        <div
                                            id="cbt-setup-logo-2-preview"
                                            class="cbt-setup-logo-preview"
                                            style="display:<?php echo $logo_2_url !== '' ? 'flex' : 'none'; ?>;"
                                        >
                                            <img
                                                id="cbt-setup-logo-2-preview-image"
                                                src="<?php echo esc_url($logo_2_url); ?>"
                                                alt=""
                                            />
                                        </div>
                                        <p id="cbt-setup-logo-2-empty" class="description cbt-setup-logo-empty" style="display:<?php echo $logo_2_url === '' ? 'block' : 'none'; ?>;">Belum ada logo dipilih.</p>
                                        <div class="cbt-setup-logo-actions">
                                            <button type="button" id="cbt-setup-logo-2-pick" class="button cbt-setup-logo-pick-button">
                                                <?php echo esc_html($logo_2_attachment_id > 0 ? 'Ganti Logo' : 'Pilih Logo'); ?>
                                            </button>
                                            <button type="button" id="cbt-setup-logo-2-remove" class="button button-secondary cbt-setup-logo-remove-button" style="display:<?php echo $logo_2_attachment_id > 0 ? 'inline-flex' : 'none'; ?>;">Hapus Logo</button>
                                        </div>
                                        <p class="cbt-setup-logo-note">Gunakan gambar dari Media Library WordPress dengan proporsi horizontal atau square.</p>
                                    </article>
                                </div>
                            </section>

                            <div class="cbt-setup-actions">
                                <p class="description">Perubahan ini langsung dipakai untuk branding frontend CBT dan dokumen cetak terkait.</p>
                                <button type="submit" class="button button-primary button-large cbt-setup-save-button">Simpan Setup Branding</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_security_view): ?>
                    <div class="cbt-setup-panel is-active" id="cbt-setup-panel-security" data-setup-panel="security" data-security-refresh-area="security-panel" role="tabpanel" aria-labelledby="cbt-setup-tab-security">
                        <div class="cbt-setup-security-grid">
                            <section class="cbt-setup-card cbt-setup-security-card">
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Security</h2>
                                        <p>Tab ini menampung kontrol keamanan yang langsung memengaruhi perilaku peserta saat ujian berlangsung.</p>
                                    </div>
                                    <span class="cbt-setup-card-chip">Control</span>
                                </div>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-setup-security-form" data-security-async-form data-security-progress-profile="settings" data-security-refresh-areas="notices,security-panel">
                                    <?php wp_nonce_field('cbt_save_security_settings'); ?>
                                    <input type="hidden" name="action" value="cbt_save_security_settings" />
                                    <div class="cbt-setup-security-masonry">
<div class="cbt-setup-col">
<div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-force-fullscreen">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-force-fullscreen"
                                                name="force_fullscreen"
                                                value="1"
                                                <?php checked($security_force_fullscreen); ?>
                                            />
                                            <span>
                                                <strong>Fullscreen Saat Ujian</strong>
                                                <span>Jika diaktifkan, peserta akan diminta masuk mode fullscreen saat mulai atau melanjutkan ujian. Interaksi soal akan dibatasi sampai fullscreen aktif.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-log-events">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-log-events"
                                                name="log_security_events"
                                                value="1"
                                                <?php checked($security_log_events_enabled); ?>
                                            />
                                            <span>
                                                <strong>Aktifkan Logging Security</strong>
                                                <span>Catat event inti seperti keluar fullscreen, pindah tab, refresh/tutup halaman, percobaan print, context menu, sesi dicabut, dan reset login admin. Histori log bisa dipantau di tab Security Log selama 30 hari terakhir.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-redis-first-ingest">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-redis-first-ingest"
                                                name="security_redis_first_ingest"
                                                value="1"
                                                <?php checked($security_redis_first_ingest); ?>
                                            />
                                            <span>
                                                <strong>Redis-First Ingest Security Log</strong>
                                                <span>Jika diaktifkan, event security siswa akan lebih dulu diantrikan ke Redis stream lalu di-flush batch ke MySQL. Saat Redis tidak tersedia, sistem otomatis kembali ke direct MySQL tanpa membuang event.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-detect-heartbeat-lost">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-detect-heartbeat-lost"
                                                name="detect_heartbeat_lost"
                                                value="1"
                                                <?php checked($security_detect_heartbeat_lost); ?>
                                            />
                                            <span>
                                                <strong>Deteksi Heartbeat Lost</strong>
                                                <span>Tambahkan warning ringan di UI ujian dan security log saat heartbeat session gagal berulang, tetapi browser masih terlihat online. V1 hanya observability dan tidak memblokir pengerjaan ujian.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-detect-idle">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-detect-idle"
                                                name="detect_idle_during_exam"
                                                value="1"
                                                <?php checked($security_detect_idle_during_exam); ?>
                                            />
                                            <span>
                                                <strong>Deteksi Idle Saat Ujian</strong>
                                                <span>Catat event security saat peserta tidak menunjukkan aktivitas pada halaman ujian selama ambang waktu tertentu. V1 hanya menambah observability dan tidak mengganggu pengerjaan ujian.</span>
                                            </span>
                                        </label>
                                        <div class="cbt-setup-security-threshold">
                                            <label for="cbt-setup-security-idle-threshold">Ambang Idle (menit)</label>
                                            <input
                                                type="number"
                                                id="cbt-setup-security-idle-threshold"
                                                name="idle_threshold_minutes"
                                                min="1"
                                                step="1"
                                                value="<?php echo esc_attr((string) $security_idle_threshold_minutes); ?>"
                                            />
                                            <p class="description">Idle dihitung hanya saat tab ujian terlihat dan window masih fokus. Event hidden/blur tetap dicatat terpisah lewat security logging yang sudah ada.</p>
                                        </div>
                                    </div>
                                    </div>
<div class="cbt-setup-col">
<div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-block-copy-paste">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-block-copy-paste"
                                                name="block_copy_paste"
                                                value="1"
                                                <?php checked($security_block_copy_paste); ?>
                                            />
                                            <span>
                                                <strong>Blok Copy / Paste Saat Ujian</strong>
                                                <span>Jika diaktifkan, aksi copy, cut, dan paste diblok selama peserta berada di halaman ujian. Cocok untuk meminimalkan pemindahan jawaban lewat clipboard.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-detect-screenshot-keys">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-detect-screenshot-keys"
                                                name="detect_screenshot_keys"
                                                value="1"
                                                <?php checked($security_detect_screenshot_keys); ?>
                                            />
                                            <span>
                                                <strong>Deteksi Tombol Screenshot</strong>
                                                <span>Catat sinyal keyboard seperti <code>PrintScreen</code> atau shortcut screenshot macOS yang berhasil tertangkap browser. Fitur ini tidak memblokir screenshot OS, hanya memberi indikator forensik di security log.</span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-show-exam-watermark">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-show-exam-watermark"
                                                name="show_exam_watermark"
                                                value="1"
                                                <?php checked($security_show_exam_watermark); ?>
                                            />
                                            <span>
                                                <strong>Tampilkan Watermark Ujian</strong>
                                                <span>Tambahkan watermark tipis berulang berisi identitas siswa, kelas, ruang, attempt, dan waktu lokal. Watermark adalah jejak forensik untuk pelacakan, bukan pemblokir screenshot.</span>
                                            </span>
                                        </label>
                                        <div class="cbt-setup-security-threshold">
                                            <label for="cbt-setup-security-exam-watermark-opacity">Opacity Watermark</label>
                                            <input
                                                type="number"
                                                id="cbt-setup-security-exam-watermark-opacity"
                                                name="exam_watermark_opacity"
                                                min="0.03"
                                                max="0.12"
                                                step="0.01"
                                                value="<?php echo esc_attr((string) $security_exam_watermark_opacity); ?>"
                                            />
                                            <p class="description">Rentang aman 0.03 sampai 0.12 agar watermark tetap terbaca di screenshot tetapi tidak mengganggu soal, tombol, atau input jawaban.</p>
                                        </div>
                                    </div>
                                    <div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-restrict-user-agent">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-restrict-user-agent"
                                                name="restrict_student_user_agent"
                                                value="1"
                                                <?php checked($security_restrict_student_user_agent); ?>
                                            />
                                            <span>
                                                <strong>Batasi User-Agent Siswa</strong>
                                                <span>Jika diaktifkan, halaman ujian siswa dan REST flow siswa hanya menerima request dengan User-Agent yang cocok dengan daftar allow-list. Pengawas dan admin tidak ikut dibatasi.</span>
                                            </span>
                                        </label>
                                        <div class="cbt-setup-security-user-agent-list">
                                            <label for="cbt-setup-security-allowed-user-agents">Allow-list User-Agent</label>
                                            <textarea
                                                id="cbt-setup-security-allowed-user-agents"
                                                name="allowed_user_agents"
                                                rows="5"
                                                spellcheck="false"
                                            ><?php echo esc_textarea((string) $security_allowed_user_agents_text); ?></textarea>
                                            <p class="description">Satu pola per baris. Matching memakai contains tanpa regex dan tidak membedakan huruf besar/kecil. <code>CBXExamLockAndroid</code> selalu disertakan untuk native Android.</p>
                                        </div>
                                    </div>
                                    <div class="cbt-setup-security-option">
                                        <label class="cbt-setup-security-checkbox" for="cbt-setup-security-block-browser-shortcuts">
                                            <input
                                                type="checkbox"
                                                id="cbt-setup-security-block-browser-shortcuts"
                                                name="block_browser_inspection_shortcuts"
                                                value="1"
                                                <?php checked($security_block_browser_inspection_shortcuts); ?>
                                            />
                                            <span>
                                                <strong>Blok Shortcut DevTools / View Source / Save Page</strong>
                                                <span>Jika diaktifkan, shortcut seperti <code>F12</code>, <code>Ctrl+Shift+I/J/C</code>, <code>Ctrl+U</code>, dan <code>Ctrl+S</code> diblok selama ujian. Event-nya ikut tercatat ke security log jika logging aktif.</span>
                                            </span>
                                        </label>
                                    </div>
                                    </div>
</div>
<div class="cbt-setup-security-actions">
                                        <p class="description">Simpan perubahan security untuk langsung diterapkan pada frontend ujian.</p>
                                        <button type="submit" class="button button-primary button-large cbt-setup-save-button">Simpan Pengaturan Security</button>
                                    </div>
                                </form>
                            </section>
                        </div>
                    </div>

                    <div class="cbt-setup-panel" id="cbt-setup-panel-security-log" data-setup-panel="security-log" data-security-refresh-area="security-log-panel" role="tabpanel" aria-labelledby="cbt-setup-tab-security-log" hidden>
                        <div class="cbt-setup-security-grid">
                            <section
                                id="cbt-setup-security-log-card"
                                class="cbt-setup-card cbt-setup-security-card cbt-setup-security-log-card"
                                data-security-log-observability-endpoint="<?php echo esc_url($security_observability_endpoint_url); ?>"
                                data-security-log-history-endpoint="<?php echo esc_url($security_logs_page_endpoint_url); ?>"
                                data-security-log-ingest-action-endpoint="<?php echo esc_url($security_ingest_action_endpoint_url); ?>"
                                data-security-log-rest-nonce="<?php echo esc_attr($security_rest_nonce); ?>"
                            >
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Histori Security Log</h2>
                                        <p>Menampilkan 20 event terbaru dari frontend ujian dan event security penting dari sisi server.</p>
                                    </div>
                                    <div class="cbt-setup-security-log-chip-group">
                                        <span class="cbt-setup-card-chip" data-security-log-status-chip><?php echo $security_log_events_enabled ? 'Logging On' : 'Logging Off'; ?></span>
                                        <span class="cbt-setup-card-chip" data-security-log-live-chip title="<?php echo esc_attr((string) ($security_log_status_snapshot['status_label'] ?? '')); ?>"><?php echo esc_html((string) ($security_log_status_snapshot['live_label'] ?? 'Live MySQL fallback')); ?></span>
                                        <span class="cbt-setup-card-chip" data-security-log-ingest-chip><?php echo esc_html((string) ($security_log_status_snapshot['ingest_label'] ?? 'Ingest direct MySQL')); ?></span>
                                        <span class="cbt-setup-card-chip" data-security-log-persist-chip><?php echo esc_html((string) ($security_log_status_snapshot['persist_label'] ?? 'Persist direct MySQL')); ?></span>
                                        <span class="cbt-setup-card-chip" data-security-log-backlog-chip><?php echo esc_html('Backlog ' . (string) max(0, (int) ($security_log_status_snapshot['backlog_count'] ?? 0))); ?></span>
                                        <span class="cbt-setup-card-chip" data-security-log-dead-chip<?php echo ((int) ($security_log_status_snapshot['dead_letter_count'] ?? 0) > 0) ? '' : ' hidden'; ?>><?php echo esc_html('Dead Letter ' . (string) max(0, (int) ($security_log_status_snapshot['dead_letter_count'] ?? 0))); ?></span>
                                    </div>
                                </div>
                                <div class="cbt-setup-security-log-body">
                                    <?php CBT_Admin_Security_Page::render_security_log_redis_monitor_panel($security_log_status_snapshot); ?>
                                    <?php
                                    $security_live_roster_attempt_total = 0;
                                    foreach ((array) $security_live_roster_groups as $security_roster_group) {
                                        $security_live_roster_attempt_total += count((array) ($security_roster_group['attempts'] ?? []));
                                    }
                                    $security_must_watch_total = count((array) $security_log_must_watch_attempts);
                                    $security_history_total = count((array) $security_logs);
                                    ?>
                                    <div class="cbt-setup-security-log-view-tabs" role="tablist" aria-label="Navigasi Security Log">
                                        <button type="button" class="cbt-setup-security-log-view-tab is-active" id="cbt-setup-security-log-view-tab-must-watch" data-security-log-view-button="must-watch" role="tab" aria-selected="true" aria-controls="cbt-setup-security-log-view-panel-must-watch">
                                            <span>Must Watch</span>
                                            <span class="cbt-setup-security-log-view-tab-badge"><?php echo esc_html((string) $security_must_watch_total); ?></span>
                                        </button>
                                        <button type="button" class="cbt-setup-security-log-view-tab" id="cbt-setup-security-log-view-tab-history" data-security-log-view-button="history" role="tab" aria-selected="false" aria-controls="cbt-setup-security-log-view-panel-history">
                                            <span>Histori Log</span>
                                            <span class="cbt-setup-security-log-view-tab-badge"><?php echo esc_html((string) $security_history_total); ?></span>
                                        </button>
                                        <button type="button" class="cbt-setup-security-log-view-tab" id="cbt-setup-security-log-view-tab-live-roster" data-security-log-view-button="live-roster" role="tab" aria-selected="false" aria-controls="cbt-setup-security-log-view-panel-live-roster">
                                            <span>Live Roster</span>
                                            <span class="cbt-setup-security-log-view-tab-badge"><?php echo esc_html((string) $security_live_roster_attempt_total); ?></span>
                                        </button>
                                    </div>
                                    <div class="cbt-setup-security-log-view-panels">
                                        <div class="cbt-setup-security-log-view-panel" id="cbt-setup-security-log-view-panel-history" data-security-log-view-panel="history" role="tabpanel" aria-labelledby="cbt-setup-security-log-view-tab-history" hidden>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-setup-security-log-manage-form" id="cbt-setup-security-log-manage-form" data-security-async-form data-security-progress-profile="logs" data-security-refresh-areas="notices,security-log-panel" data-security-success-tab="security-log">
                                        <?php wp_nonce_field('cbt_manage_security_logs'); ?>
                                        <input type="hidden" name="action" value="cbt_manage_security_logs" />
                                        <input type="hidden" name="delete_scope" value="" data-security-log-delete-scope />
                                        <div id="cbt-setup-security-log-focus-state" class="cbt-setup-security-log-focus-state" hidden>
                                            <span id="cbt-setup-security-log-focus-label"></span>
                                            <button type="button" class="button-link" id="cbt-setup-security-log-focus-clear">Reset fokus</button>
                                        </div>
                                        <div class="cbt-setup-security-log-toolbar">
                                            <div class="cbt-setup-security-log-filter-grid">
                                                <div class="cbt-setup-security-log-filter">
                                                    <label for="cbt-setup-security-log-filter-severity">Filter Severity</label>
                                                    <select id="cbt-setup-security-log-filter-severity">
                                                        <option value="all">Semua severity</option>
                                                        <option value="warning">Warning</option>
                                                        <option value="critical">Critical</option>
                                                        <option value="info">Info</option>
                                                    </select>
                                                </div>
                                                <div class="cbt-setup-security-log-filter">
                                                    <label for="cbt-setup-security-log-filter-event">Filter Event</label>
                                                    <select id="cbt-setup-security-log-filter-event">
                                                        <option value="all">Semua event</option>
                                                        <?php foreach ($security_log_event_definitions as $event_key => $event_definition): ?>
                                                            <option value="<?php echo esc_attr((string) $event_key); ?>"><?php echo esc_html((string) ($event_definition['label'] ?? $event_key)); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="cbt-setup-security-log-filter">
                                                    <label for="cbt-setup-security-log-filter-device">Filter Device</label>
                                                    <select id="cbt-setup-security-log-filter-device">
                                                        <option value="all">Semua device</option>
                                                    </select>
                                                </div>
                                                <div class="cbt-setup-security-log-filter">
                                                    <label for="cbt-setup-security-log-filter-kelas">Filter Kelas</label>
                                                    <select id="cbt-setup-security-log-filter-kelas">
                                                        <option value="all">Semua kelas</option>
                                                    </select>
                                                </div>
                                                <div class="cbt-setup-security-log-filter">
                                                    <label for="cbt-setup-security-log-filter-ruang">Filter Ruang</label>
                                                    <select id="cbt-setup-security-log-filter-ruang">
                                                        <option value="all">Semua ruang</option>
                                                    </select>
                                                </div>
                                                <div class="cbt-setup-security-log-filter">
                                                    <label for="cbt-setup-security-log-filter-student-name">Filter Nama</label>
                                                    <input type="search" id="cbt-setup-security-log-filter-student-name" placeholder="Cari nama siswa..." />
                                                </div>
                                            </div>
                                            <div class="cbt-setup-security-log-toolbar-footer">
                                                <div class="cbt-setup-security-log-toolbar-live">
                                                    <label class="cbt-setup-security-log-auto-refresh" for="cbt-setup-security-log-auto-refresh">
                                                        <input type="checkbox" id="cbt-setup-security-log-auto-refresh" />
                                                        <span>Auto refresh 10 detik</span>
                                                    </label>
                                                    <span id="cbt-setup-security-log-live-status" class="cbt-setup-security-log-live-status">Auto refresh nonaktif.</span>
                                                </div>
                                                <div class="cbt-setup-security-log-toolbar-actions">
                                                    <button type="submit" class="button cbt-setup-security-log-delete-button" data-security-log-submit="selected" disabled>Delete Selected</button>
                                                    <button type="submit" class="button cbt-setup-security-log-delete-button" data-security-log-submit="all"<?php echo empty($security_logs) ? ' disabled' : ''; ?>>Delete All</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="cbt-setup-security-log-table-region" data-security-log-table-region>
                                            <?php CBT_Admin_Security_Page::render_security_log_history_table_region($security_logs, $security_log_event_definitions); ?>
                                        </div>
                                    </form>
                                        </div>
                                        <div class="cbt-setup-security-log-view-panel" id="cbt-setup-security-log-view-panel-must-watch" data-security-log-view-panel="must-watch" role="tabpanel" aria-labelledby="cbt-setup-security-log-view-tab-must-watch">
                                            <div class="cbt-setup-security-log-watch-region" data-security-log-watch-region data-security-log-lazy-region="must-watch" data-security-log-lazy-loaded="0">
                                                <template data-security-log-lazy-template>
                                                    <?php CBT_Admin_Security_Page::render_security_log_must_watch_panel($security_log_must_watch_attempts); ?>
                                                </template>
                                                <div class="cbt-setup-security-log-lazy-shell" data-security-log-lazy-shell>
                                                    <div class="cbt-setup-security-log-lazy-placeholder">
                                                        <strong>Must Watch siap dibuka.</strong>
                                                        Klik tab ini saat Anda ingin meninjau attempt berisiko tanpa perlu scroll jauh ke bawah.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="cbt-setup-security-log-view-panel" id="cbt-setup-security-log-view-panel-live-roster" data-security-log-view-panel="live-roster" role="tabpanel" aria-labelledby="cbt-setup-security-log-view-tab-live-roster" hidden>
                                            <div class="cbt-setup-security-log-roster-region" data-security-log-roster-region data-security-log-lazy-region="live-roster" data-security-log-lazy-loaded="0">
                                                <template data-security-log-lazy-template>
                                                    <?php CBT_Admin_Security_Page::render_security_log_live_roster_panel($security_live_roster_groups); ?>
                                                </template>
                                                <div class="cbt-setup-security-log-lazy-shell" data-security-log-lazy-shell>
                                                    <div class="cbt-setup-security-log-lazy-placeholder">
                                                        <strong>Live Roster siap dibuka.</strong>
                                                        Panel live attempt akan dimuat saat tab ini dipilih agar card Security Log tetap ringan saat pertama dibuka.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="cbt-setup-panel" id="cbt-setup-panel-native" data-setup-panel="native" data-security-refresh-area="native-panel" role="tabpanel" aria-labelledby="cbt-setup-tab-native" hidden>
                        <div class="cbt-native-grid">
                            <section class="cbt-setup-card cbt-setup-security-card">
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Alur Native ke Backend</h2>
                                        <p>Ikuti alur ini saat shell Android WebView atau Windows CEFSharp perlu mengambil sesi ujian aktif, membaca bearer token, lalu mengirim warning ke backend security log CBT.</p>
                                    </div>
                                    <span class="cbt-setup-card-chip">Flow</span>
                                </div>
                                <div class="cbt-native-spec-grid">
                                    <article class="cbt-native-spec-card">
                                        <h3>1. Ambil Snapshot dari Frontend</h3>
                                        <p>Native memulai dari helper resmi ini untuk membaca status ujian aktif. Saat <code>stage</code> sudah <code>exam</code>, snapshot akan berisi token, attempt aktif, dan endpoint native security event.</p>
                                        <pre class="cbt-native-code">window.CBTNativeBridge.getSecuritySnapshot()</pre>
                                        <p class="cbt-native-helper-note">Panggil saat boot, resume, atau tepat sebelum native mengirim warning ke backend.</p>
                                    </article>
                                    <article class="cbt-native-spec-card">
                                        <h3>2. Shape Return yang Dibaca Native</h3>
                                        <pre class="cbt-native-code">{
  "ok": 1,
  "token": "jwt...",
  "attemptId": 114,
  "stage": "exam",
  "studentId": 7662,
  "selectedExamId": 16,
  "securityLoggingEnabled": true,
  "endpoints": {
    "nativeSecurityEvent": "<?php echo esc_html((string) $native_security_endpoint_url); ?>"
  }
}</pre>
                                    </article>
                                    <article class="cbt-native-spec-card">
                                        <h3>3. Kirim Request ke Endpoint Native</h3>
                                        <p>Native wajib memakai bearer token siswa aktif dan attempt yang memang dimiliki user tersebut. Identitas user tidak perlu dikirim dari native.</p>
                                        <span class="cbt-native-endpoint"><?php echo esc_html((string) $native_security_endpoint_url); ?></span>
                                        <ul class="cbt-native-list">
                                            <li><code>Authorization: Bearer &lt;token&gt;</code></li>
                                            <li><code>Content-Type: application/json</code></li>
                                            <li><code>Accept: application/json</code></li>
                                            <li>Bearer token diambil dari <code>window.CBTNativeBridge.getSecuritySnapshot()</code> saat <code>stage</code> sudah <code>exam</code></li>
                                            <li>Gunakan <code>snapshot.token</code> untuk header dan <code>snapshot.attemptId</code> untuk body request</li>
                                        </ul>
                                    </article>
                                    <article class="cbt-native-spec-card">
                                        <h3>4. Format JSON yang Dikirim Native</h3>
                                        <pre class="cbt-native-code">{
  "attempt_id": 131,
  "event_type": "tab_hidden",
  "native_app": "android_webview",
  "native_version": "1.0.0",
  "warning_code": "task_switch",
  "warning_message": "Window ujian kehilangan fokus karena task switch",
  "occurred_at_client": "2026-03-26T16:33:58.569Z",
  "context": {
    "has_focus": 1,
    "device_platform": "android",
    "device_type": "mobile",
    "native_event_name": "tab hidden"
  }
}</pre>
                                        <p class="cbt-native-helper-note"><code>Authorization: Bearer &lt;token&gt;</code> tidak masuk ke JSON body ini. Header tersebut dikirim terpisah pada request HTTP.</p>
                                    </article>
                                    <article class="cbt-native-spec-card">
                                        <h3>5. Penjelasan Tiap Bagian</h3>
                                        <ul class="cbt-native-list">
                                            <li><code>attempt_id</code>: ID attempt aktif dari <code>snapshot.attemptId</code>.</li>
                                            <li><code>event_type</code>: event CBT yang ingin dicatat, misalnya <code>tab_hidden</code> atau <code>fullscreen_exit</code>.</li>
                                            <li><code>native_app</code>: kanal pengirim native, v1 hanya <code>android_webview</code> atau <code>windows_cefsharp</code>.</li>
                                            <li><code>native_version</code>: versi aplikasi native untuk kebutuhan audit/debug.</li>
                                            <li><code>warning_code</code>: kode singkat internal dari native, misalnya <code>task_switch</code>.</li>
                                            <li><code>warning_message</code>: pesan manusiawi dari warning yang terdeteksi di sisi native.</li>
                                            <li><code>occurred_at_client</code>: timestamp saat warning terjadi di device native.</li>
                                            <li><code>context</code>: detail tambahan yang boleh dikirim native seperti fokus, platform device, tipe device, dan nama event native.</li>
                                        </ul>
                                    </article>
                                    <article class="cbt-native-spec-card">
                                        <h3>6. Validasi yang Dikerjakan Backend</h3>
                                        <p>Payload akan ditolak bila token tidak valid, role bukan siswa, attempt bukan milik user, event type tidak ada di whitelist native, atau native app tidak dikenali.</p>
                                        <ul class="cbt-native-list">
                                            <li>Backend mengecek <code>Authorization: Bearer &lt;token&gt;</code> dari header HTTP, bukan dari JSON body.</li>
                                            <li>Native app v1: <strong>Android WebView</strong> dan <strong>Windows CEFSharp</strong></li>
                                            <li>Source canonical diisi backend dari native app, bukan dipercaya dari payload</li>
                                            <li>Field root seperti <code>native_version</code>, <code>warning_code</code>, <code>warning_message</code>, dan <code>occurred_at_client</code> akan ikut disimpan ke context log saat dicatat.</li>
                                            <li>Isi dari <code>context</code> digabung ke log final dan disimpan pada <code>context_json</code>.</li>
                                        </ul>
                                    </article>
                                </div>
                                <div class="cbt-native-grid">
                                    <article class="cbt-native-output-card">
                                        <h3>7. Push Update Opsional</h3>
                                        <p>Ini adalah lanjutan dari flow 1-6. Mulai dari helper resmi ini untuk menentukan apakah native masih berada di mode ujian aktif. Push update dipakai agar native langsung tahu saat snapshot berubah, tetapi operasi penting tetap membaca ulang snapshot sekali lagi.</p>
                                        <pre class="cbt-native-code">function applyExamMode(snapshot) {
  const isExamActive = Number(snapshot && snapshot.ok) === 1
    && String(snapshot && snapshot.stage ? snapshot.stage : '') === 'exam'
    && Number(snapshot && snapshot.attemptId ? snapshot.attemptId : 0) &gt; 0
    && String(snapshot && snapshot.token ? snapshot.token : '') !== '';

  if (isExamActive) {
    // Aktifkan proteksi native.
    // Simpan snapshot.token dan snapshot.attemptId untuk request berikutnya.
    return;
  }

  // Keluar dari mode ujian.
  // Hentikan warning native dan buang token/attempt cache lama.
}

applyExamMode(window.CBTNativeBridge.getSecuritySnapshot());

window.addEventListener(
  window.CBTNativeBridge.getSecuritySnapshotChangedEventName(),
  function (event) {
    applyExamMode(event.detail.snapshot);
  }
);

window.CBTNativeBridge.onSecuritySnapshotChanged = function (snapshot, reason) {
  applyExamMode(snapshot);
  console.log('snapshot changed:', reason, snapshot);
};</pre>
                                        <ul class="cbt-native-list">
                                            <li><strong>Tujuan:</strong> native diberi tahu saat snapshot resmi CBT berubah, jadi tidak perlu polling ketat terus-menerus.</li>
                                            <li><strong>Kapan biasanya terpanggil:</strong> saat frontend mount, login/logout, attempt mulai, stage masuk/keluar <code>exam</code>, atau token dan attempt aktif berubah.</li>
                                            <li><strong>Apa arti <code>reason</code>:</strong> hanya penanda asal perubahan dari runtime frontend untuk log/debug. Keputusan keamanan native tetap harus melihat isi <code>snapshot</code>.</li>
                                            <li><strong>Rule aman di native:</strong> anggap ujian aktif hanya jika <code>snapshot.ok === 1</code>, <code>snapshot.stage === "exam"</code>, <code>snapshot.attemptId &gt; 0</code>, dan <code>snapshot.token</code> tidak kosong.</li>
                                            <li><strong>Fallback manual tetap wajib:</strong> panggil <code>getSecuritySnapshot()</code> saat boot, saat app resume, dan tepat sebelum native mengirim warning penting ke backend.</li>
                                            <li><strong>Saat keluar dari mode exam:</strong> matikan proteksi native, stop kirim warning ujian, dan buang cache token/attempt lama agar tidak salah kirim ke backend.</li>
                                        </ul>
                                        <p class="cbt-native-helper-note">Contoh Android dan WPF di bawah sama-sama mengikuti pola ini; yang berbeda hanya mekanisme bridge JS, object host, dan method evaluasi script di tiap platform.</p>
                                        <div class="cbt-native-sample-shell">
                                            <div class="cbt-native-sample-tabs" role="tablist" aria-label="Native implementation examples">
                                                <button type="button" class="cbt-native-sample-tab is-active" data-native-implementation-tab-button="android" role="tab" aria-selected="true" aria-controls="cbt-native-implementation-panel-android">Android WebView (Kotlin)</button>
                                                <button type="button" class="cbt-native-sample-tab" data-native-implementation-tab-button="wpf" role="tab" aria-selected="false" aria-controls="cbt-native-implementation-panel-wpf">WPF C#</button>
                                            </div>

                                            <section class="cbt-native-implementation-panel" id="cbt-native-implementation-panel-android" data-native-implementation-panel="android" role="tabpanel">
                                                <p>Implementasi Android ini menerapkan pola di atas memakai <code>addJavascriptInterface</code>, hook event snapshot, dan cache ringan di memory native.</p>
                                                <ul class="cbt-native-list">
                                                    <li><strong>Bridge JS:</strong> object <code>AndroidCbtBridge</code> menerima snapshot JSON dari frontend setiap kali event berubah.</li>
                                                    <li><strong>Resume safety:</strong> <code>refreshSnapshot()</code> dipanggil saat attach dan resume supaya token, attempt, dan endpoint tidak stale.</li>
                                                    <li><strong>Runtime cache:</strong> <code>latestToken</code>, <code>latestAttemptId</code>, dan <code>latestEndpoint</code> disimpan sebagai state terbaru di service.</li>
                                                    <li><strong>HTTP client:</strong> warning dikirim via <code>OkHttp</code> hanya saat snapshot terakhir masih valid untuk stage <code>exam</code>.</li>
                                                </ul>
                                                <pre class="cbt-native-code">class CbtSecurityBridge(
    private val webView: WebView,
    private val httpClient: OkHttpClient = OkHttpClient()
) {
    @Volatile private var latestToken: String = ""
    @Volatile private var latestAttemptId: Int = 0
    @Volatile private var latestEndpoint: String = ""

    fun attach() {
        webView.addJavascriptInterface(AndroidCbtBridge(), "AndroidCbtBridge")
        refreshSnapshot()
        installSnapshotListener()
    }

    fun onResume() {
        refreshSnapshot()
    }

    private fun refreshSnapshot() {
        webView.evaluateJavascript(
            "(function(){return JSON.stringify(window.CBTNativeBridge.getSecuritySnapshot());})();"
        ) { raw ->
            val json = decodeJsResult(raw)
            applySnapshot(JSONObject(json))
        }
    }

    private fun installSnapshotListener() {
        val script = """
            (function () {
              if (window.__cbtAndroidSnapshotHookInstalled) { return 'installed'; }
              window.__cbtAndroidSnapshotHookInstalled = true;

              function emit(snapshot, reason) {
                if (window.AndroidCbtBridge) {
                  window.AndroidCbtBridge.onSecuritySnapshotChanged(
                    JSON.stringify(snapshot),
                    String(reason || 'sync')
                  );
                }
              }

              window.addEventListener(
                window.CBTNativeBridge.getSecuritySnapshotChangedEventName(),
                function (event) {
                  emit(event.detail.snapshot, event.detail.reason);
                }
              );

              window.CBTNativeBridge.onSecuritySnapshotChanged = emit;
              emit(window.CBTNativeBridge.getSecuritySnapshot(), 'bootstrap');
              return 'installed';
            })();
        """.trimIndent()

        webView.evaluateJavascript(script, null)
    }

    fun sendNativeWarning(eventType: String, warningCode: String, warningMessage: String) {
        if (latestToken.isBlank() || latestAttemptId <= 0 || latestEndpoint.isBlank()) {
            return
        }

        val payload = JSONObject().apply {
            put("attempt_id", latestAttemptId)
            put("event_type", eventType)
            put("native_app", "android_webview")
            put("native_version", BuildConfig.VERSION_NAME)
            put("warning_code", warningCode)
            put("warning_message", warningMessage)
            put("occurred_at_client", Instant.now().toString())
            put("context", JSONObject().apply {
                put("has_focus", 0)
                put("device_platform", "android")
                put("device_type", "mobile")
            })
        }

        val request = Request.Builder()
            .url(latestEndpoint)
            .addHeader("Authorization", "Bearer " + latestToken)
            .addHeader("Content-Type", "application/json")
            .addHeader("Accept", "application/json")
            .post(payload.toString().toRequestBody("application/json; charset=utf-8".toMediaType()))
            .build()

        httpClient.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {}
            override fun onResponse(call: Call, response: Response) {
                response.close()
            }
        })
    }

    private fun applySnapshot(snapshot: JSONObject) {
        val ok = snapshot.optInt("ok") == 1
        val stage = snapshot.optString("stage")
        val attemptId = snapshot.optInt("attemptId")
        val token = snapshot.optString("token")
        val endpoint = snapshot.optJSONObject("endpoints")
            ?.optString("nativeSecurityEvent")
            .orEmpty()

        if (ok && stage == "exam" && attemptId > 0 && token.isNotBlank()) {
            latestToken = token
            latestAttemptId = attemptId
            latestEndpoint = endpoint
            return
        }

        latestToken = ""
        latestAttemptId = 0
        latestEndpoint = ""
    }

    private fun decodeJsResult(raw: String?): String {
        if (raw == null || raw == "null") {
            return "{}"
        }

        return JSONArray("[$raw]").getString(0)
    }

    inner class AndroidCbtBridge {
        @JavascriptInterface
        fun onSecuritySnapshotChanged(snapshotJson: String, reason: String) {
            applySnapshot(JSONObject(snapshotJson))
        }
    }
}</pre>
                                            </section>

                                            <section class="cbt-native-implementation-panel" id="cbt-native-implementation-panel-wpf" data-native-implementation-panel="wpf" role="tabpanel" hidden>
                                                <p>Implementasi WPF ini memakai CEFSharp untuk menjalankan pola yang sama, dengan bridge host object di C# dan request warning lewat <code>HttpClient</code>. Jika Anda memakai WebView2, logikanya tetap sama.</p>
                                                <ul class="cbt-native-list">
                                                    <li><strong>Host bridge:</strong> listener JS meneruskan snapshot ke object <code>cbtHost</code> yang diregister di <code>JavascriptObjectRepository</code>.</li>
                                                    <li><strong>Resume safety:</strong> <code>RefreshSnapshotAsync()</code> dipanggil saat attach dan resume untuk meminimalkan state stale.</li>
                                                    <li><strong>Runtime cache:</strong> <code>_latestToken</code>, <code>_latestAttemptId</code>, dan <code>_latestEndpoint</code> disimpan di service C#.</li>
                                                    <li><strong>HTTP client:</strong> warning dipost ke endpoint native security event dengan header <code>Authorization: Bearer ...</code>.</li>
                                                </ul>
                                                <pre class="cbt-native-code">public sealed class CbtSecurityBridge
{
    private readonly ChromiumWebBrowser _browser;
    private string _latestToken = string.Empty;
    private int _latestAttemptId = 0;
    private string _latestEndpoint = string.Empty;

    public CbtSecurityBridge(ChromiumWebBrowser browser)
    {
        _browser = browser;
    }

    public async Task AttachAsync()
    {
        _browser.JavascriptObjectRepository.Register(
            "cbtHost",
            new SnapshotHost(this),
            isAsync: false
        );

        await RefreshSnapshotAsync();
        await InstallSnapshotListenerAsync();
    }

    public async Task OnResumeAsync()
    {
        await RefreshSnapshotAsync();
    }

    public async Task RefreshSnapshotAsync()
    {
        var response = await _browser.EvaluateScriptAsync(
            "JSON.stringify(window.CBTNativeBridge.getSecuritySnapshot())"
        );

        if (!response.Success || response.Result == null)
        {
            return;
        }

        ApplySnapshot(response.Result.ToString() ?? "{}");
    }

    public async Task InstallSnapshotListenerAsync()
    {
        var script = @"
(function () {
  if (window.__cbtWindowsSnapshotHookInstalled) { return 'installed'; }
  window.__cbtWindowsSnapshotHookInstalled = true;

  function emit(snapshot, reason) {
    if (window.cbtHost && typeof window.cbtHost.onSecuritySnapshotChanged === 'function') {
      window.cbtHost.onSecuritySnapshotChanged(
        JSON.stringify(snapshot),
        String(reason || 'sync')
      );
    }
  }

  window.addEventListener(
    window.CBTNativeBridge.getSecuritySnapshotChangedEventName(),
    function (event) {
      emit(event.detail.snapshot, event.detail.reason);
    }
  );

  window.CBTNativeBridge.onSecuritySnapshotChanged = emit;
  emit(window.CBTNativeBridge.getSecuritySnapshot(), 'bootstrap');
  return 'installed';
})();";

        await _browser.EvaluateScriptAsync(script);
    }

    public async Task SendNativeWarningAsync(string eventType, string warningCode, string warningMessage)
    {
        if (string.IsNullOrWhiteSpace(_latestToken) || _latestAttemptId <= 0 || string.IsNullOrWhiteSpace(_latestEndpoint))
        {
            return;
        }

        var payload = new
        {
            attempt_id = _latestAttemptId,
            event_type = eventType,
            native_app = "windows_cefsharp",
            native_version = "1.0.0",
            warning_code = warningCode,
            warning_message = warningMessage,
            occurred_at_client = DateTimeOffset.UtcNow.ToString("O"),
            context = new
            {
                has_focus = 0,
                device_platform = "windows",
                device_type = "desktop"
            }
        };

        using var client = new HttpClient();
        client.DefaultRequestHeaders.Authorization =
            new AuthenticationHeaderValue("Bearer", _latestToken);
        client.DefaultRequestHeaders.Accept.Add(
            new MediaTypeWithQualityHeaderValue("application/json")
        );

        var json = JsonSerializer.Serialize(payload);
        using var content = new StringContent(json, Encoding.UTF8, "application/json");
        using var response = await client.PostAsync(_latestEndpoint, content);
        response.EnsureSuccessStatusCode();
    }

    internal void ApplySnapshot(string snapshotJson)
    {
        var snapshot = JsonSerializer.Deserialize&lt;SecuritySnapshot&gt;(snapshotJson);

        if (snapshot != null
            && snapshot.Ok == 1
            && string.Equals(snapshot.Stage, "exam", StringComparison.OrdinalIgnoreCase)
            && snapshot.AttemptId &gt; 0
            && !string.IsNullOrWhiteSpace(snapshot.Token))
        {
            _latestToken = snapshot.Token;
            _latestAttemptId = snapshot.AttemptId;
            _latestEndpoint = snapshot.Endpoints?.NativeSecurityEvent ?? string.Empty;
            return;
        }

        _latestToken = string.Empty;
        _latestAttemptId = 0;
        _latestEndpoint = string.Empty;
    }

    public sealed class SnapshotHost
    {
        private readonly CbtSecurityBridge _owner;

        public SnapshotHost(CbtSecurityBridge owner)
        {
            _owner = owner;
        }

        public void onSecuritySnapshotChanged(string snapshotJson, string reason)
        {
            _owner.ApplySnapshot(snapshotJson);
        }
    }
}</pre>
                                                <p class="cbt-native-helper-note">Jika Anda memakai WebView2, ganti <code>EvaluateScriptAsync</code> dengan <code>ExecuteScriptAsync</code>. Struktur snapshot, event listener, dan request HTTP yang dipakai tetap sama.</p>
                                            </section>
                                        </div>
                                    </article>
                                </div>
                            </section>

                            <section class="cbt-setup-card cbt-setup-security-card">
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Test Tool Native</h2>
                                        <p>Gunakan panel ini untuk membuat contoh payload dan mensimulasikan native event ke security log. Simulasi hanya untuk verifikasi UI observability dan tidak menggantikan auth test native yang sesungguhnya.</p>
                                    </div>
                                    <span class="cbt-setup-card-chip">Spec + Tool</span>
                                </div>
                                <div class="cbt-native-tool-grid">
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-native-simulate-form" class="cbt-native-tool-form" data-security-async-form data-security-progress-profile="native" data-security-refresh-areas="notices,native-panel,security-log-panel" data-security-success-tab="security-log">
                                        <?php wp_nonce_field('cbt_simulate_native_security_event'); ?>
                                        <input type="hidden" name="action" value="cbt_simulate_native_security_event" />
                                        <div class="cbt-native-field-grid">
                                            <div class="cbt-native-field">
                                                <label for="cbt-native-simulate-attempt-id">Attempt ID</label>
                                                <input type="number" id="cbt-native-simulate-attempt-id" name="attempt_id" min="1" step="1" value="<?php echo esc_attr((string) $native_security_sample_attempt_id); ?>" />
                                            </div>
                                            <div class="cbt-native-field">
                                                <label for="cbt-native-simulate-app">Native App</label>
                                                <select id="cbt-native-simulate-app" name="native_app">
                                                    <?php foreach ($native_supported_apps as $native_app_key => $native_app_label): ?>
                                                        <option value="<?php echo esc_attr((string) $native_app_key); ?>"><?php echo esc_html((string) $native_app_label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="cbt-native-field">
                                                <label for="cbt-native-simulate-event-type">Event Type</label>
                                                <select id="cbt-native-simulate-event-type" name="event_type">
                                                    <?php foreach ($native_simulation_event_catalog as $native_event): ?>
                                                        <?php
                                                        $option_supported_apps = isset($native_event['supported_apps']) && is_array($native_event['supported_apps'])
                                                            ? implode(',', array_map('sanitize_key', $native_event['supported_apps']))
                                                            : '';
                                                        ?>
                                                        <option
                                                            value="<?php echo esc_attr((string) ($native_event['event_type'] ?? '')); ?>"
                                                            data-native-apps="<?php echo esc_attr($option_supported_apps); ?>"
                                                        ><?php echo esc_html((string) ($native_event['label'] ?? '')); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="cbt-native-field">
                                                <label for="cbt-native-simulate-native-version">Native Version</label>
                                                <input type="text" id="cbt-native-simulate-native-version" name="native_version" value="1.0.0" />
                                            </div>
                                            <div class="cbt-native-field">
                                                <label for="cbt-native-simulate-warning-code">Warning Code</label>
                                                <input type="text" id="cbt-native-simulate-warning-code" name="warning_code" value="task_switch" />
                                            </div>
                                            <div class="cbt-native-field cbt-native-field--full">
                                                <label for="cbt-native-simulate-warning-message">Warning Message</label>
                                                <textarea id="cbt-native-simulate-warning-message" name="warning_message">Window ujian kehilangan fokus karena task switch</textarea>
                                            </div>
                                        </div>
                                        <div class="cbt-native-actions">
                                            <button type="button" class="button button-secondary" id="cbt-native-generate-sample-request">Generate Sample Request</button>
                                            <button type="submit" class="button button-primary">Simulate Native Event</button>
                                        </div>
                                        <p class="cbt-native-helper-note">Tip: gunakan attempt aktif milik siswa yang sedang diuji agar row sample langsung bisa Anda lihat di tab Security Log.</p>
                                    </form>

                                    <article class="cbt-native-output-card cbt-native-sample-shell">
                                        <div class="cbt-native-sample-tabs" role="tablist" aria-label="Generated native samples">
                                            <button type="button" class="cbt-native-sample-tab is-active" data-native-sample-tab-button="json" role="tab" aria-selected="true" aria-controls="cbt-native-sample-panel-json">JSON Payload</button>
                                            <button type="button" class="cbt-native-sample-tab" data-native-sample-tab-button="curl" role="tab" aria-selected="false" aria-controls="cbt-native-sample-panel-curl">cURL</button>
                                            <button type="button" class="cbt-native-sample-tab" data-native-sample-tab-button="android" role="tab" aria-selected="false" aria-controls="cbt-native-sample-panel-android">Android</button>
                                            <button type="button" class="cbt-native-sample-tab" data-native-sample-tab-button="windows" role="tab" aria-selected="false" aria-controls="cbt-native-sample-panel-windows">Windows</button>
                                        </div>
                                        <section class="cbt-native-sample-panel" id="cbt-native-sample-panel-json" data-native-sample-panel="json" role="tabpanel">
                                            <h3>Sample JSON Payload</h3>
                                            <pre class="cbt-native-code" id="cbt-native-sample-request-json"></pre>
                                        </section>
                                        <section class="cbt-native-sample-panel" id="cbt-native-sample-panel-curl" data-native-sample-panel="curl" role="tabpanel" hidden>
                                            <h3>Sample cURL</h3>
                                            <pre class="cbt-native-code" id="cbt-native-sample-curl"></pre>
                                        </section>
                                        <section class="cbt-native-sample-panel" id="cbt-native-sample-panel-android" data-native-sample-panel="android" role="tabpanel" hidden>
                                            <h3>Android WebView Snippet</h3>
                                            <pre class="cbt-native-code" id="cbt-native-sample-android"></pre>
                                        </section>
                                        <section class="cbt-native-sample-panel" id="cbt-native-sample-panel-windows" data-native-sample-panel="windows" role="tabpanel" hidden>
                                            <h3>Windows CEFSharp Snippet</h3>
                                            <pre class="cbt-native-code" id="cbt-native-sample-cefsharp"></pre>
                                        </section>
                                    </article>
                                </div>
                            </section>

                        </div>
                    </div>

                    <div class="cbt-setup-panel" id="cbt-setup-panel-catalog" data-setup-panel="catalog" data-security-refresh-area="catalog-panel" role="tabpanel" aria-labelledby="cbt-setup-tab-catalog" hidden>
                        <div class="cbt-native-grid">
                            <section class="cbt-setup-card cbt-setup-security-card">
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Event Catalog Native</h2>
                                        <p>Katalog ini dipisah per kanal supaya implementasi Android Native dan Windows Native nanti punya tempat sendiri. Untuk saat ini, event yang benar-benar dipakai masih mengikuti event CBT yang sudah ada.</p>
                                    </div>
                                    <span class="cbt-setup-card-chip">Catalog</span>
                                </div>
                                <div class="cbt-native-catalog-shell">
                                    <div class="cbt-native-threshold-grid">
                                        <div class="cbt-native-threshold-card">
                                            <strong>Must Watch</strong>
                                            <span>Mulai <?php echo esc_html((string) ((int) $security_must_watch_score_threshold)); ?> poin</span>
                                            <p>Attempt akan mulai masuk radar observability saat skor akumulatif mencapai ambang ini.</p>
                                        </div>
                                        <div class="cbt-native-threshold-card is-high-risk">
                                            <strong>High Risk</strong>
                                            <span>Mulai <?php echo esc_html((string) ((int) $security_must_watch_high_risk_threshold)); ?> poin</span>
                                            <p>Status badge berubah menjadi High Risk saat total skor attempt mencapai ambang ini.</p>
                                        </div>
                                    </div>
                                    <div class="cbt-native-catalog-tabs" role="tablist" aria-label="Native event catalog channels">
                                        <button type="button" class="cbt-native-catalog-tab is-active" data-native-catalog-tab-button="browser" role="tab" aria-selected="true" aria-controls="cbt-native-catalog-panel-browser">Browser / CBT Saat Ini</button>
                                        <button type="button" class="cbt-native-catalog-tab" data-native-catalog-tab-button="android" role="tab" aria-selected="false" aria-controls="cbt-native-catalog-panel-android">Android Native</button>
                                        <button type="button" class="cbt-native-catalog-tab" data-native-catalog-tab-button="windows" role="tab" aria-selected="false" aria-controls="cbt-native-catalog-panel-windows">Windows Native</button>
                                    </div>

                                    <section class="cbt-native-catalog-panel" id="cbt-native-catalog-panel-browser" data-native-catalog-panel="browser" role="tabpanel">
                                        <div class="cbt-native-catalog-table-shell">
                                            <table class="widefat striped cbt-native-catalog-table">
                                                <thead>
                                                    <tr>
                                                        <th>Event Type</th>
                                                        <th>Label</th>
                                                        <th>Severity</th>
                                                        <th>Skor</th>
                                                        <th>Deskripsi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($native_browser_event_catalog as $native_event): ?>
                                                        <tr>
                                                            <td><code><?php echo esc_html((string) ($native_event['event_type'] ?? '')); ?></code></td>
                                                            <td><?php echo esc_html((string) ($native_event['label'] ?? '')); ?></td>
                                                            <td><?php echo esc_html((string) ($native_event['severity'] ?? 'info')); ?></td>
                                                            <td><?php echo esc_html(CBT_Security_Log::format_risk_score((float) ($native_event['risk_weight'] ?? 0))); ?></td>
                                                            <td><?php echo esc_html((string) ($native_event['message'] ?? '')); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>

                                    <section class="cbt-native-catalog-panel" id="cbt-native-catalog-panel-android" data-native-catalog-panel="android" role="tabpanel" hidden>
                                        <div class="cbt-native-catalog-table-shell">
                                            <table class="widefat striped cbt-native-catalog-table">
                                                <thead>
                                                    <tr>
                                                        <th>Event Type</th>
                                                        <th>Label</th>
                                                        <th>Severity</th>
                                                        <th>Skor</th>
                                                        <th>Deskripsi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($native_android_event_catalog as $native_event): ?>
                                                        <tr>
                                                            <td><code><?php echo esc_html((string) ($native_event['event_type'] ?? '')); ?></code></td>
                                                            <td><?php echo esc_html((string) ($native_event['label'] ?? '')); ?></td>
                                                            <td><?php echo esc_html((string) ($native_event['severity'] ?? 'info')); ?></td>
                                                            <td><?php echo esc_html(CBT_Security_Log::format_risk_score((float) ($native_event['risk_weight'] ?? 0))); ?></td>
                                                            <td><?php echo esc_html((string) ($native_event['message'] ?? '')); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>

                                    <section class="cbt-native-catalog-panel" id="cbt-native-catalog-panel-windows" data-native-catalog-panel="windows" role="tabpanel" hidden>
                                        <div class="cbt-native-catalog-table-shell">
                                            <table class="widefat striped cbt-native-catalog-table">
                                                <thead>
                                                    <tr>
                                                        <th>Event Type</th>
                                                        <th>Label</th>
                                                        <th>Severity</th>
                                                        <th>Skor</th>
                                                        <th>Deskripsi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($native_windows_event_catalog as $native_event): ?>
                                                        <tr>
                                                            <td><code><?php echo esc_html((string) ($native_event['event_type'] ?? '')); ?></code></td>
                                                            <td><?php echo esc_html((string) ($native_event['label'] ?? '')); ?></td>
                                                            <td><?php echo esc_html((string) ($native_event['severity'] ?? 'info')); ?></td>
                                                            <td><?php echo esc_html(CBT_Security_Log::format_risk_score((float) ($native_event['risk_weight'] ?? 0))); ?></td>
                                                            <td><?php echo esc_html((string) ($native_event['message'] ?? '')); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                </div>
                            </section>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var cbtAdminViewMode = <?php echo wp_json_encode($cbt_admin_view_mode); ?>;
                var brandingProgressTimer = null;
                var securityProgressTimer = null;

                function clampBrandingProgress(value) {
                    var number = parseInt(value, 10);
                    if (Number.isNaN(number)) {
                        return 0;
                    }

                    return Math.max(0, Math.min(100, number));
                }

                function getBrandingProgressElements() {
                    var root = document.querySelector('[data-branding-progress]');
                    if (!root) {
                        return null;
                    }

                    return {
                        root: root,
                        label: root.querySelector('[data-branding-progress-label]'),
                        percent: root.querySelector('[data-branding-progress-percent]'),
                        track: root.querySelector('[data-branding-progress-track]'),
                        fill: root.querySelector('[data-branding-progress-fill]'),
                        step: root.querySelector('[data-branding-progress-step]')
                    };
                }

                function setBrandingProgress(percent, label, step) {
                    var elements = getBrandingProgressElements();
                    var progress = clampBrandingProgress(percent);
                    if (!elements) {
                        return;
                    }

                    elements.root.classList.add('is-active');
                    elements.root.setAttribute('aria-hidden', 'false');
                    if (elements.label && label) {
                        elements.label.textContent = label;
                    }
                    if (elements.percent) {
                        elements.percent.textContent = progress + '%';
                    }
                    if (elements.track) {
                        elements.track.setAttribute('aria-valuenow', String(progress));
                    }
                    if (elements.fill) {
                        elements.fill.style.setProperty('--cbt-branding-progress', progress + '%');
                    }
                    if (elements.step && step) {
                        elements.step.textContent = step;
                    }
                }

                function stopBrandingProgress() {
                    if (brandingProgressTimer) {
                        window.clearInterval(brandingProgressTimer);
                        brandingProgressTimer = null;
                    }
                }

                function startBrandingProgress(label, steps, cap, interval) {
                    var progress = 8;
                    var startedAt = Date.now();
                    var safeSteps = Array.isArray(steps) && steps.length ? steps : ['Menyiapkan perubahan branding.'];
                    var safeCap = Math.max(30, Math.min(98, parseInt(cap, 10) || 92));
                    var safeInterval = Math.max(220, parseInt(interval, 10) || 380);

                    stopBrandingProgress();
                    setBrandingProgress(progress, label || 'Memproses CBT Branding...', safeSteps[0]);

                    brandingProgressTimer = window.setInterval(function () {
                        var elapsed = Date.now() - startedAt;
                        var stepIndex = Math.min(safeSteps.length - 1, Math.floor(elapsed / Math.max(700, safeInterval * 2)));
                        var distance = safeCap - progress;
                        var increment = Math.max(1, Math.min(8, Math.ceil(distance * 0.18)));
                        progress = Math.min(safeCap, progress + increment);
                        setBrandingProgress(progress, label || 'Memproses CBT Branding...', safeSteps[stepIndex]);
                    }, safeInterval);
                }

                function completeBrandingProgress(label, step) {
                    stopBrandingProgress();
                    setBrandingProgress(100, label || 'Perubahan branding siap.', step || 'Selesai.');
                }

                function clampSecurityProgress(value) {
                    var number = parseInt(value, 10);
                    if (Number.isNaN(number)) {
                        return 0;
                    }

                    return Math.max(0, Math.min(100, number));
                }

                function getSecurityProgressElements() {
                    var root = document.querySelector('[data-security-progress]');
                    if (!root) {
                        return null;
                    }

                    return {
                        root: root,
                        label: root.querySelector('[data-security-progress-label]'),
                        percent: root.querySelector('[data-security-progress-percent]'),
                        track: root.querySelector('[data-security-progress-track]'),
                        fill: root.querySelector('[data-security-progress-fill]'),
                        step: root.querySelector('[data-security-progress-step]')
                    };
                }

                function setSecurityProgress(percent, label, step, tone) {
                    var elements = getSecurityProgressElements();
                    var progress = clampSecurityProgress(percent);
                    if (!elements) {
                        return;
                    }

                    elements.root.classList.add('is-active');
                    elements.root.classList.toggle('is-error', tone === 'error');
                    elements.root.setAttribute('aria-hidden', 'false');
                    if (elements.label && label) {
                        elements.label.textContent = label;
                    }
                    if (elements.percent) {
                        elements.percent.textContent = progress + '%';
                    }
                    if (elements.track) {
                        elements.track.setAttribute('aria-valuenow', String(progress));
                    }
                    if (elements.fill) {
                        elements.fill.style.setProperty('--cbt-security-progress', progress + '%');
                    }
                    if (elements.step && step) {
                        elements.step.textContent = step;
                    }
                }

                function stopSecurityProgress() {
                    if (securityProgressTimer) {
                        window.clearInterval(securityProgressTimer);
                        securityProgressTimer = null;
                    }
                }

                function getSecurityProgressProfile(profile) {
                    var key = String(profile || 'settings');
                    if (key === 'logs') {
                        return {
                            label: 'Memproses Security Log...',
                            completeLabel: 'Security Log diperbarui.',
                            steps: [
                                'Mengunci pilihan log yang akan diproses.',
                                'Mengirim aksi hapus ke admin WordPress.',
                                'Menyusun ulang tabel histori security.',
                                'Memperbarui badge dan filter area log.'
                            ]
                        };
                    }
                    if (key === 'native') {
                        return {
                            label: 'Mensimulasikan Native Event...',
                            completeLabel: 'Native event selesai diproses.',
                            steps: [
                                'Memvalidasi attempt, native app, dan event type.',
                                'Mengirim payload simulasi ke handler security.',
                                'Mencatat event ke security log.',
                                'Memperbarui area native dan security log.'
                            ]
                        };
                    }

                    return {
                        label: 'Menyimpan Pengaturan Security...',
                        completeLabel: 'Pengaturan security diperbarui.',
                        steps: [
                            'Mengumpulkan opsi proteksi ujian.',
                            'Menormalisasi watermark, idle threshold, dan allow-list User-Agent.',
                            'Menyimpan konfigurasi security ke WordPress option.',
                            'Memperbarui panel Security tanpa reload global.'
                        ]
                    };
                }

                function startSecurityProgress(profile) {
                    var config = getSecurityProgressProfile(profile);
                    var progress = 7;
                    var startedAt = Date.now();

                    stopSecurityProgress();
                    setSecurityProgress(progress, config.label, config.steps[0], '');

                    securityProgressTimer = window.setInterval(function () {
                        var elapsed = Date.now() - startedAt;
                        var stepIndex = Math.min(config.steps.length - 1, Math.floor(elapsed / 900));
                        var distance = 94 - progress;
                        var increment = Math.max(1, Math.min(7, Math.ceil(distance * 0.16)));
                        progress = Math.min(94, progress + increment);
                        setSecurityProgress(progress, config.label, config.steps[stepIndex], '');
                    }, 340);
                }

                function completeSecurityProgress(label, step, tone) {
                    stopSecurityProgress();
                    setSecurityProgress(tone === 'error' ? 100 : 100, label || 'Aksi CBT Security selesai.', step || 'Area Security sudah diperbarui.', tone || '');
                }

                function extractSecurityResponseError(text, status) {
                    var raw = String(text || '')
                        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                        .replace(/<style[\s\S]*?<\/style>/gi, ' ');
                    var plain = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    if (plain.length > 180) {
                        plain = plain.slice(0, 180) + '...';
                    }

                    return 'HTTP ' + String(status || 0) + (plain ? ': ' + plain : '');
                }

                function normalizeSecurityJsonPayload(json) {
                    var root = json && typeof json === 'object' ? json : {};
                    var data = root.data && typeof root.data === 'object' ? root.data : root;

                    return {
                        html: String(data.html || root.html || ''),
                        message: String(data.message || root.message || ''),
                        tab: String(data.tab || root.tab || ''),
                        success: root.success !== false
                    };
                }

                function parseSecurityFetchResponse(response) {
                    return response.text().then(function (text) {
                        var contentType = response.headers && response.headers.get ? String(response.headers.get('content-type') || '') : '';
                        var json = null;
                        var payload = null;

                        if (contentType.indexOf('application/json') >= 0 || /^\s*[\{\[]/.test(String(text || ''))) {
                            try {
                                json = JSON.parse(String(text || '{}'));
                            } catch (error) {
                                json = null;
                            }
                        }

                        if (json) {
                            payload = normalizeSecurityJsonPayload(json);
                            if (!response.ok || !payload.success) {
                                throw new Error(payload.message || extractSecurityResponseError(payload.html || text, response.status));
                            }

                            return {
                                response: response,
                                text: payload.html,
                                message: payload.message,
                                tab: payload.tab
                            };
                        }

                        if (!response.ok) {
                            throw new Error(extractSecurityResponseError(text, response.status));
                        }

                        return {
                            response: response,
                            text: text,
                            message: '',
                            tab: ''
                        };
                    });
                }

                function replaceSecurityRefreshAreas(responseHtml, areas) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(String(responseHtml || ''), 'text/html');
                    var replaced = [];

                    areas.forEach(function (areaName) {
                        var selector = '[data-security-refresh-area="' + areaName + '"]';
                        var current = document.querySelector(selector);
                        var next = doc.querySelector(selector);
                        if (!current || !next) {
                            return;
                        }

                        current.replaceWith(document.importNode(next, true));
                        replaced.push(areaName);
                    });

                    if (!replaced.length) {
                        throw new Error('Response valid, tetapi area tujuan tidak ditemukan.');
                    }

                    return replaced;
                }

                function getSecurityTargetTab(form, responseUrl, replacedAreas) {
                    var target = String(form.getAttribute('data-security-success-tab') || '').trim();
                    var parsedUrl = null;

                    if (responseUrl) {
                        try {
                            parsedUrl = new URL(String(responseUrl), window.location.href);
                            if (parsedUrl.hash === '#security-log') {
                                return 'security-log';
                            }
                            if (parsedUrl.hash === '#native') {
                                return 'native';
                            }
                            if (parsedUrl.hash === '#catalog') {
                                return 'catalog';
                            }
                            if (parsedUrl.hash === '#security') {
                                return 'security';
                            }
                        } catch (error) {
                            parsedUrl = null;
                        }
                    }

                    if (target !== '') {
                        return target;
                    }
                    if (replacedAreas.indexOf('security-log-panel') >= 0) {
                        return 'security-log';
                    }
                    if (replacedAreas.indexOf('native-panel') >= 0) {
                        return 'native';
                    }

                    return 'security';
                }

                function rebindSecurityLocalUi(replacedAreas) {
                    bindSetupTabs();
                    bindSecurityAsyncForms();

                    if (replacedAreas.indexOf('native-panel') >= 0) {
                        bindNativeSecurityTools();
                    }
                    if (replacedAreas.indexOf('catalog-panel') >= 0) {
                        bindNativeCatalogTabs();
                        bindNativeImplementationTabs();
                    }
                    if (replacedAreas.indexOf('security-log-panel') >= 0) {
                        bindSecurityLogTools();
                    }
                }

                var securityAjaxUrl = (typeof window.ajaxurl === 'string' && window.ajaxurl !== '')
                    ? window.ajaxurl
                    : <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

                function getSecurityAsyncActionUrl(form) {
                    return securityAjaxUrl || form.action;
                }

                function bindSecurityAsyncForms() {
                    var forms = document.querySelectorAll('[data-security-async-form]');

                    if (!forms.length || typeof window.fetch !== 'function' || typeof window.FormData !== 'function' || typeof window.DOMParser !== 'function') {
                        return;
                    }

                    Array.prototype.forEach.call(forms, function (form) {
                        if (form.dataset.securityAsyncBound === '1') {
                            return;
                        }

                        form.dataset.securityAsyncBound = '1';
                        form.addEventListener('submit', function (event) {
                            var submitter = event.submitter || document.activeElement;
                            var button = submitter && submitter.tagName === 'BUTTON' ? submitter : form.querySelector('button[type="submit"]');
                            var originalText = button ? button.textContent : '';
                            var formData = new FormData(form);
                            var profile = String(form.getAttribute('data-security-progress-profile') || 'settings');
                            var areaList = String(form.getAttribute('data-security-refresh-areas') || 'notices,security-panel')
                                .split(',')
                                .map(function (item) { return item.trim(); })
                                .filter(Boolean);
                            var config = getSecurityProgressProfile(profile);

                            if (event.defaultPrevented) {
                                return;
                            }

                            event.preventDefault();
                            if (submitter && submitter.name && !formData.has(submitter.name)) {
                                formData.append(submitter.name, submitter.value || '1');
                            }
                            formData.append('cbt_security_local_refresh', '1');

                            if (button) {
                                button.classList.add('is-loading');
                                button.disabled = true;
                                button.textContent = profile === 'logs' ? 'Memproses...' : (profile === 'native' ? 'Mengirim...' : 'Menyimpan...');
                            }

                            startSecurityProgress(profile);

                            fetch(getSecurityAsyncActionUrl(form), {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json, text/html;q=0.8, */*;q=0.5',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                                .then(parseSecurityFetchResponse)
                                .then(function (payload) {
                                    var replacedAreas = replaceSecurityRefreshAreas(payload.text, areaList);
                                    var targetTab = payload.tab || getSecurityTargetTab(form, payload.response.url, replacedAreas);

                                    rebindSecurityLocalUi(replacedAreas);
                                    if (window.cbtSetupSetActiveTab) {
                                        window.cbtSetupSetActiveTab(targetTab, true);
                                    }
                                    completeSecurityProgress(config.completeLabel, payload.message || ('Area ' + targetTab.replace('-', ' ') + ' sudah diperbarui secara lokal.'), '');
                                })
                                .catch(function (error) {
                                    completeSecurityProgress('Gagal memproses CBT Security.', error && error.message ? error.message : 'Form masih aman dikirim ulang bila dibutuhkan.', 'error');
                                    if (button) {
                                        button.disabled = false;
                                    }
                                })
                                .finally(function () {
                                    if (button) {
                                        button.classList.remove('is-loading');
                                        if (button.isConnected) {
                                            button.disabled = false;
                                            button.textContent = originalText;
                                        }
                                    }
                                });
                        });
                    });
                }

                function bindBrandingFormProgress() {
                    var form = document.querySelector('[data-branding-form]');
                    if (!form || form.dataset.brandingProgressBound === '1') {
                        return;
                    }

                    form.dataset.brandingProgressBound = '1';
                    form.addEventListener('submit', function (event) {
                        var saveButton = form.querySelector('.cbt-setup-save-button');
                        if (event.defaultPrevented) {
                            return;
                        }

                        startBrandingProgress('Menyimpan CBT Branding...', [
                            'Mengumpulkan identitas sekolah dan program ujian.',
                            'Memvalidasi pilihan logo dari Media Library.',
                            'Menyimpan konfigurasi branding ke WordPress option.',
                            'Menyiapkan refresh tampilan admin.',
                            'Menerapkan branding ke frontend CBT dan dokumen terkait.'
                        ], 96, 360);

                        if (saveButton) {
                            saveButton.classList.add('is-loading');
                            saveButton.textContent = 'Menyimpan...';
                            window.setTimeout(function () {
                                saveButton.disabled = true;
                            }, 0);
                        }
                    });
                }

                function bindSetupTabs() {
                    var tabButtons = document.querySelectorAll('[data-setup-tab-button]');
                    var panels = document.querySelectorAll('[data-setup-panel]');
                    var hasBrandingTab = !!document.querySelector('[data-setup-tab-button="branding"]');
                    var hasSecurityTab = !!document.querySelector('[data-setup-tab-button="security"]');
                    var hasSecurityLogTab = !!document.querySelector('[data-setup-tab-button="security-log"]');
                    var hasNativeTab = !!document.querySelector('[data-setup-tab-button="native"]');
                    var hasCatalogTab = !!document.querySelector('[data-setup-tab-button="catalog"]');

                    if (!tabButtons.length || !panels.length) {
                        return;
                    }

                    function setActiveTab(tabName, updateUrl) {
                        var normalized = hasBrandingTab ? 'branding' : 'security';
                        var index = 0;
                        var nextUrl = '';

                        if (tabName === 'security-log' && hasSecurityLogTab) {
                            normalized = 'security-log';
                        } else if (tabName === 'catalog' && hasCatalogTab) {
                            normalized = 'catalog';
                        } else if (tabName === 'native' && hasNativeTab) {
                            normalized = 'native';
                        } else if (tabName === 'security' && hasSecurityTab) {
                            normalized = 'security';
                        } else if (tabName === 'branding' && hasBrandingTab) {
                            normalized = 'branding';
                        }

                        for (index = 0; index < tabButtons.length; index += 1) {
                            var button = tabButtons[index];
                            var isActive = button.getAttribute('data-setup-tab-button') === normalized;
                            button.classList.toggle('is-active', isActive);
                            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            button.setAttribute('tabindex', isActive ? '0' : '-1');
                        }

                        for (index = 0; index < panels.length; index += 1) {
                            var panel = panels[index];
                            var panelActive = panel.getAttribute('data-setup-panel') === normalized;
                            panel.classList.toggle('is-active', panelActive);
                            panel.hidden = !panelActive;
                        }

                        if (updateUrl && window.history && typeof window.history.replaceState === 'function') {
                            nextUrl = window.location.pathname + window.location.search;
                            if (normalized === 'security-log') {
                                nextUrl += '#security-log';
                            } else if (normalized === 'catalog') {
                                nextUrl += '#catalog';
                            } else if (normalized === 'native') {
                                nextUrl += '#native';
                            } else if (normalized === 'security') {
                                nextUrl += '#security';
                            }
                            window.history.replaceState(null, document.title, nextUrl);
                        }
                    }

                    for (var i = 0; i < tabButtons.length; i += 1) {
                        if (tabButtons[i].dataset.setupTabBound === '1') {
                            continue;
                        }
                        tabButtons[i].dataset.setupTabBound = '1';
                        tabButtons[i].addEventListener('click', function () {
                            setActiveTab(this.getAttribute('data-setup-tab-button') || 'branding', true);
                        });
                    }

                    window.cbtSetupSetActiveTab = setActiveTab;
                    window.cbtSetupGetActiveTab = function () {
                        var active = document.querySelector('[data-setup-tab-button].is-active');
                        return active ? String(active.getAttribute('data-setup-tab-button') || '') : '';
                    };

                    setActiveTab(window.location.hash === '#security-log'
                        ? 'security-log'
                        : (window.location.hash === '#catalog'
                            ? 'catalog'
                        : (window.location.hash === '#native'
                            ? 'native'
                            : (window.location.hash === '#security' ? 'security' : (hasBrandingTab ? 'branding' : 'security')))), false);
                }

                function bindNativeSecurityTools() {
                    var attemptInput = document.getElementById('cbt-native-simulate-attempt-id');
                    var appSelect = document.getElementById('cbt-native-simulate-app');
                    var eventTypeSelect = document.getElementById('cbt-native-simulate-event-type');
                    var nativeVersionInput = document.getElementById('cbt-native-simulate-native-version');
                    var warningCodeInput = document.getElementById('cbt-native-simulate-warning-code');
                    var warningMessageInput = document.getElementById('cbt-native-simulate-warning-message');
                    var generateButton = document.getElementById('cbt-native-generate-sample-request');
                    var payloadBlock = document.getElementById('cbt-native-sample-request-json');
                    var curlBlock = document.getElementById('cbt-native-sample-curl');
                    var androidBlock = document.getElementById('cbt-native-sample-android');
                    var cefsharpBlock = document.getElementById('cbt-native-sample-cefsharp');
                    var sampleTabButtons = document.querySelectorAll('[data-native-sample-tab-button]');
                    var samplePanels = document.querySelectorAll('[data-native-sample-panel]');
                    var endpointUrl = <?php echo wp_json_encode((string) $native_security_endpoint_url); ?>;
                    var eventTypeOptions = Array.prototype.slice.call(eventTypeSelect ? eventTypeSelect.options : []);

                    if (!attemptInput || !appSelect || !eventTypeSelect || !payloadBlock || !curlBlock || !androidBlock || !cefsharpBlock) {
                        return;
                    }

                    function syncNativeEventTypeOptions() {
                        var nativeApp = String(appSelect.value || 'android_webview');
                        var currentValue = String(eventTypeSelect.value || '');
                        var fallbackValue = '';
                        var index = 0;

                        for (index = 0; index < eventTypeOptions.length; index += 1) {
                            var option = eventTypeOptions[index];
                            var supportedApps = String(option.getAttribute('data-native-apps') || '');
                            var appList = supportedApps ? supportedApps.split(',') : [];
                            var isSupported = appList.length === 0 || appList.indexOf(nativeApp) >= 0;

                            option.hidden = !isSupported;
                            option.disabled = !isSupported;

                            if (isSupported && fallbackValue === '') {
                                fallbackValue = String(option.value || '');
                            }
                        }

                        if (!currentValue || !eventTypeSelect.querySelector('option[value="' + currentValue + '"]:not([disabled])')) {
                            eventTypeSelect.value = fallbackValue;
                        }
                    }

                    function setActiveSampleTab(tabName) {
                        var normalized = tabName === 'curl' || tabName === 'android' || tabName === 'windows'
                            ? tabName
                            : 'json';
                        var index = 0;

                        for (index = 0; index < sampleTabButtons.length; index += 1) {
                            var button = sampleTabButtons[index];
                            var isActive = button.getAttribute('data-native-sample-tab-button') === normalized;
                            button.classList.toggle('is-active', isActive);
                            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            button.setAttribute('tabindex', isActive ? '0' : '-1');
                        }

                        for (index = 0; index < samplePanels.length; index += 1) {
                            var panel = samplePanels[index];
                            var isPanelActive = panel.getAttribute('data-native-sample-panel') === normalized;
                            panel.hidden = !isPanelActive;
                        }
                    }

                    function buildCurrentPayload() {
                        var nativeApp = String(appSelect.value || 'android_webview');
                        var eventType = String(eventTypeSelect.value || 'tab_hidden');
                        var attemptId = Math.max(0, Number(attemptInput.value || 0));
                        var nativeVersion = String(nativeVersionInput && nativeVersionInput.value ? nativeVersionInput.value : '1.0.0').trim() || '1.0.0';
                        var warningCode = String(warningCodeInput && warningCodeInput.value ? warningCodeInput.value : '').trim();
                        var warningMessage = String(warningMessageInput && warningMessageInput.value ? warningMessageInput.value : '').trim();

                        if (!warningCode) {
                            warningCode = eventType.replace(/^native_/, '') || 'native_warning';
                        }

                        if (!warningMessage) {
                            warningMessage = String(eventTypeSelect.options[eventTypeSelect.selectedIndex].textContent || 'Native warning').trim();
                        }

                        return {
                            attempt_id: attemptId,
                            event_type: eventType,
                            native_app: nativeApp,
                            native_version: nativeVersion,
                            warning_code: warningCode,
                            warning_message: warningMessage,
                            occurred_at_client: new Date().toISOString(),
                            context: {
                                has_focus: nativeApp === 'windows_cefsharp' ? 0 : 1,
                                device_platform: nativeApp === 'android_webview' ? 'android' : 'windows',
                                device_type: nativeApp === 'android_webview' ? 'mobile' : 'desktop',
                                native_event_name: eventType.replace(/^native_/, '').replace(/_/g, ' ')
                            }
                        };
                    }

                    function renderSamples() {
                        var payload = buildCurrentPayload();
                        var payloadJson = JSON.stringify(payload, null, 2);
                        var escapedPayload = payloadJson.replace(/'/g, "\\'");

                        payloadBlock.textContent = payloadJson;
                        curlBlock.textContent =
                            "curl -X POST '" + endpointUrl + "' \\\n"
                            + "  -H 'Authorization: Bearer <token>' \\\n"
                            + "  -H 'Content-Type: application/json' \\\n"
                            + "  -H 'Accept: application/json' \\\n"
                            + "  -d '" + escapedPayload + "'";
                        androidBlock.textContent =
                            "val snapshotJson = webView.evaluateJavascript(\"window.CBTNativeBridge.getSecuritySnapshot()\", ...)\n"
                            + "val snapshot = JSONObject(snapshotJson)\n"
                            + "val payload = JSONObject(\"\"\"" + payloadJson + "\"\"\")\n"
                            + "val token = snapshot.getString(\"token\")\n"
                            + "val endpoint = snapshot.getJSONObject(\"endpoints\").getString(\"nativeSecurityEvent\")\n"
                            + "// Opsional: dengarkan perubahan via window.CBTNativeBridge.onSecuritySnapshotChanged\n"
                            + "// Kirim payload ke endpoint dengan Authorization: Bearer <token>";
                        cefsharpBlock.textContent =
                            "var snapshot = await browser.EvaluateScriptAsync(\"window.CBTNativeBridge.getSecuritySnapshot()\");\n"
                            + "var payloadJson = @\"" + payloadJson.replace(/"/g, '""') + "\";\n"
                            + "// Opsional: pasang listener ke cbt-native-security-snapshot-changed untuk update push\n"
                            + "// Ambil token dan endpoint dari snapshot.Result lalu POST ke nativeSecurityEvent\n"
                            + "// Header wajib: Authorization Bearer, Content-Type application/json, Accept application/json";
                    }

                    if (generateButton) {
                        generateButton.addEventListener('click', function () {
                            renderSamples();
                        });
                    }

                    for (var sampleTabIndex = 0; sampleTabIndex < sampleTabButtons.length; sampleTabIndex += 1) {
                        sampleTabButtons[sampleTabIndex].addEventListener('click', function () {
                            setActiveSampleTab(this.getAttribute('data-native-sample-tab-button') || 'json');
                        });
                    }

                    [attemptInput, eventTypeSelect, nativeVersionInput, warningCodeInput, warningMessageInput].forEach(function (field) {
                        if (!field) {
                            return;
                        }

                        field.addEventListener('input', renderSamples);
                        field.addEventListener('change', renderSamples);
                    });

                    appSelect.addEventListener('change', function () {
                        syncNativeEventTypeOptions();
                        renderSamples();
                    });

                    syncNativeEventTypeOptions();
                    renderSamples();
                    setActiveSampleTab('json');
                }

                function bindNativeCatalogTabs() {
                    var tabButtons = document.querySelectorAll('[data-native-catalog-tab-button]');
                    var panels = document.querySelectorAll('[data-native-catalog-panel]');

                    if (!tabButtons.length || !panels.length) {
                        return;
                    }

                    function setActiveCatalogTab(tabName) {
                        var normalized = tabName === 'android' || tabName === 'windows'
                            ? tabName
                            : 'browser';
                        var index = 0;

                        for (index = 0; index < tabButtons.length; index += 1) {
                            var button = tabButtons[index];
                            var isActive = button.getAttribute('data-native-catalog-tab-button') === normalized;
                            button.classList.toggle('is-active', isActive);
                            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            button.setAttribute('tabindex', isActive ? '0' : '-1');
                        }

                        for (index = 0; index < panels.length; index += 1) {
                            var panel = panels[index];
                            var isPanelActive = panel.getAttribute('data-native-catalog-panel') === normalized;
                            panel.hidden = !isPanelActive;
                        }
                    }

                    for (var tabIndex = 0; tabIndex < tabButtons.length; tabIndex += 1) {
                        tabButtons[tabIndex].addEventListener('click', function () {
                            setActiveCatalogTab(this.getAttribute('data-native-catalog-tab-button') || 'browser');
                        });
                    }

                    setActiveCatalogTab('browser');
                }

                function bindNativeImplementationTabs() {
                    var tabButtons = document.querySelectorAll('[data-native-implementation-tab-button]');
                    var panels = document.querySelectorAll('[data-native-implementation-panel]');

                    if (!tabButtons.length || !panels.length) {
                        return;
                    }

                    function setActiveImplementationTab(tabName) {
                        var normalized = tabName === 'wpf'
                            ? 'wpf'
                            : 'android';
                        var index = 0;

                        for (index = 0; index < tabButtons.length; index += 1) {
                            var button = tabButtons[index];
                            var isActive = button.getAttribute('data-native-implementation-tab-button') === normalized;
                            button.classList.toggle('is-active', isActive);
                            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            button.setAttribute('tabindex', isActive ? '0' : '-1');
                        }

                        for (index = 0; index < panels.length; index += 1) {
                            var panel = panels[index];
                            var isPanelActive = panel.getAttribute('data-native-implementation-panel') === normalized;
                            panel.hidden = !isPanelActive;
                        }
                    }

                    for (var tabIndex = 0; tabIndex < tabButtons.length; tabIndex += 1) {
                        tabButtons[tabIndex].addEventListener('click', function () {
                            setActiveImplementationTab(this.getAttribute('data-native-implementation-tab-button') || 'android');
                        });
                    }

                    setActiveImplementationTab('android');
                }

                function bindLogoField(config) {
                    var mediaFrame = null;
                    var mediaFrameSelected = false;
                    var logoInput = document.getElementById(config.inputId);
                    var previewWrap = document.getElementById(config.previewId);
                    var previewImage = document.getElementById(config.previewImageId);
                    var emptyState = document.getElementById(config.emptyId);
                    var pickButton = document.getElementById(config.pickButtonId);
                    var removeButton = document.getElementById(config.removeButtonId);

                    if (!pickButton || !removeButton) {
                        return;
                    }

                    function setLogoState(attachmentId, imageUrl) {
                        var hasLogo = attachmentId > 0 && String(imageUrl || '').trim() !== '';
                        if (logoInput) {
                            logoInput.value = hasLogo ? String(attachmentId) : '';
                        }
                        if (previewImage) {
                            previewImage.src = hasLogo ? String(imageUrl) : '';
                        }
                        if (previewWrap) {
                            previewWrap.style.display = hasLogo ? 'flex' : 'none';
                        }
                        if (emptyState) {
                            emptyState.style.display = hasLogo ? 'none' : 'block';
                        }
                        if (pickButton) {
                            pickButton.textContent = hasLogo ? 'Ganti Logo' : 'Pilih Logo';
                        }
                        if (removeButton) {
                            removeButton.style.display = hasLogo ? 'inline-flex' : 'none';
                        }
                        completeBrandingProgress(
                            hasLogo ? 'Logo branding siap disimpan.' : 'Logo branding dikosongkan.',
                            hasLogo
                                ? 'Preview logo sudah diperbarui. Klik Simpan Setup Branding untuk menyimpan permanen.'
                                : 'Logo di form sudah dikosongkan. Klik Simpan Setup Branding untuk menyimpan perubahan.'
                        );
                    }

                    pickButton.addEventListener('click', function (event) {
                        event.preventDefault();
                        if (typeof wp === 'undefined' || !wp.media) {
                            window.alert('Media Library belum siap. Coba refresh halaman ini.');
                            return;
                        }

                        pickButton.classList.add('is-loading');
                        mediaFrameSelected = false;
                        startBrandingProgress('Membuka Media Library...', [
                            'Menyiapkan picker logo WordPress.',
                            'Menunggu admin memilih gambar.',
                            'Membaca attachment dan preview logo.'
                        ], 64, 420);
                        if (!mediaFrame) {
                            mediaFrame = wp.media({
                                title: config.mediaTitle,
                                button: { text: 'Gunakan Logo' },
                                multiple: false,
                                library: { type: 'image' }
                            });
                            mediaFrame.on('select', function () {
                                var selection = mediaFrame.state().get('selection').first();
                                if (!selection) {
                                    return;
                                }

                                mediaFrameSelected = true;
                                var payload = selection.toJSON();
                                var imageUrl = '';
                                if (payload.sizes && payload.sizes.medium && payload.sizes.medium.url) {
                                    imageUrl = payload.sizes.medium.url;
                                } else if (payload.url) {
                                    imageUrl = payload.url;
                                }
                                setLogoState(parseInt(payload.id, 10) || 0, imageUrl);
                            });
                            mediaFrame.on('open', function () {
                                setBrandingProgress(35, 'Media Library terbuka.', 'Pilih gambar logo yang ingin dipakai.');
                            });
                            mediaFrame.on('close', function () {
                                pickButton.classList.remove('is-loading');
                                if (!mediaFrameSelected) {
                                    stopBrandingProgress();
                                    setBrandingProgress(72, 'Media Library ditutup.', 'Jika logo belum berubah, pilih logo lagi atau lanjutkan edit branding.');
                                }
                            });
                        }
                        mediaFrame.open();
                    });

                    removeButton.addEventListener('click', function (event) {
                        event.preventDefault();
                        removeButton.classList.add('is-loading');
                        startBrandingProgress('Menghapus logo dari form...', [
                            'Mengosongkan attachment logo.',
                            'Membersihkan preview logo.',
                            'Menunggu admin menyimpan perubahan.'
                        ], 88, 260);
                        setLogoState(0, '');
                        window.setTimeout(function () {
                            removeButton.classList.remove('is-loading');
                        }, 220);
                    });
                }

                function bindClearIdentityFields() {
                    var clearButton = document.getElementById('cbt-setup-clear-identity');
                    var identityFieldsWrap = document.getElementById('cbt-setup-identity-fields');
                    if (!clearButton || !identityFieldsWrap) {
                        return;
                    }

                    clearButton.addEventListener('click', function (event) {
                        var fields = identityFieldsWrap.querySelectorAll('input[type="text"], textarea');
                        var hasValue = false;
                        var index = 0;

                        event.preventDefault();

                        for (index = 0; index < fields.length; index += 1) {
                            if (String(fields[index].value || '').trim() !== '') {
                                hasValue = true;
                                break;
                            }
                        }

                        if (!hasValue) {
                            window.alert('Semua field identitas sekolah sudah kosong.');
                            return;
                        }

                        if (!window.confirm('Kosongkan semua informasi pada Identitas Sekolah? Perubahan akan tersimpan setelah Anda klik Simpan Setup Branding.')) {
                            return;
                        }

                        clearButton.classList.add('is-loading');
                        startBrandingProgress('Mengosongkan identitas sekolah...', [
                            'Menghapus field nama program, sekolah, dan motto.',
                            'Menghapus NPSN, alamat, dan wilayah.',
                            'Menunggu admin menyimpan perubahan.'
                        ], 88, 260);
                        for (index = 0; index < fields.length; index += 1) {
                            fields[index].value = '';
                            fields[index].dispatchEvent(new Event('input', { bubbles: true }));
                            fields[index].dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        completeBrandingProgress('Identitas sekolah dikosongkan.', 'Field identitas sudah kosong. Klik Simpan Setup Branding untuk menyimpan perubahan.');
                        window.setTimeout(function () {
                            clearButton.classList.remove('is-loading');
                        }, 220);

                        if (fields.length > 0 && typeof fields[0].focus === 'function') {
                            fields[0].focus();
                        }
                    });
                }

                    function bindSecurityLogTools() {
                        var card = document.getElementById('cbt-setup-security-log-card');
                        var manageForm = document.getElementById('cbt-setup-security-log-manage-form');
                        var severityFilter = document.getElementById('cbt-setup-security-log-filter-severity');
                        var eventFilter = document.getElementById('cbt-setup-security-log-filter-event');
                    var deviceFilter = document.getElementById('cbt-setup-security-log-filter-device');
                    var kelasFilter = document.getElementById('cbt-setup-security-log-filter-kelas');
                    var ruangFilter = document.getElementById('cbt-setup-security-log-filter-ruang');
                    var studentNameFilter = document.getElementById('cbt-setup-security-log-filter-student-name');
                    var autoRefreshToggle = document.getElementById('cbt-setup-security-log-auto-refresh');
                    var liveStatus = document.getElementById('cbt-setup-security-log-live-status');
                    var focusState = document.getElementById('cbt-setup-security-log-focus-state');
                    var focusLabel = document.getElementById('cbt-setup-security-log-focus-label');
                        var focusClearButton = document.getElementById('cbt-setup-security-log-focus-clear');
                        var securityLogPanel = document.getElementById('cbt-setup-panel-security-log');
                        var rosterRegion = card ? card.querySelector('[data-security-log-roster-region]') : null;
                        var watchRegion = card ? card.querySelector('[data-security-log-watch-region]') : null;
                        var tableRegion = card ? card.querySelector('[data-security-log-table-region]') : null;
                        var statusChip = card ? card.querySelector('[data-security-log-status-chip]') : null;
                        var liveChip = card ? card.querySelector('[data-security-log-live-chip]') : null;
                        var ingestChip = card ? card.querySelector('[data-security-log-ingest-chip]') : null;
                        var persistChip = card ? card.querySelector('[data-security-log-persist-chip]') : null;
                        var backlogChip = card ? card.querySelector('[data-security-log-backlog-chip]') : null;
                        var deadChip = card ? card.querySelector('[data-security-log-dead-chip]') : null;
                        var monitorPanel = card ? card.querySelector('[data-security-log-monitor]') : null;
                        var monitorHelper = card ? card.querySelector('[data-security-log-monitor-helper]') : null;
                        var monitorStatus = card ? card.querySelector('[data-security-log-monitor-status]') : null;
                        var monitorDisabledReason = card ? card.querySelector('[data-security-log-monitor-disabled-reason]') : null;
                        var monitorDiagnostics = card ? card.querySelector('[data-security-log-monitor-diagnostics]') : null;
                        var observabilityEndpoint = card ? String(card.getAttribute('data-security-log-observability-endpoint') || '') : '';
                        var historyEndpoint = card ? String(card.getAttribute('data-security-log-history-endpoint') || '') : '';
                        var ingestActionEndpoint = card ? String(card.getAttribute('data-security-log-ingest-action-endpoint') || '') : '';
                        var restNonce = card ? String(card.getAttribute('data-security-log-rest-nonce') || '') : '';
                        var deleteScopeInput = card ? card.querySelector('[data-security-log-delete-scope]') : null;
                        var deleteSelectedButton = card ? card.querySelector('[data-security-log-submit="selected"]') : null;
                        var deleteAllButton = card ? card.querySelector('[data-security-log-submit="all"]') : null;
                    var autoRefreshTimer = 0;
                    var refreshInFlight = false;
                    var monitorActionInFlight = false;
                    var storageKey = 'cbt_setup_security_log_auto_refresh_enabled';
                    var activeViewStorageKey = 'cbt_setup_security_log_active_view';
                    var watchSortStorageKey = 'cbt_setup_security_log_watch_sort_mode';
                    var activeWatchFocusAttempt = '';
                    var activeWatchFocusStudent = '';
                    var activeWatchFocusKelas = '';
                    var activeWatchFocusRuang = '';
                    var activeWatchFocusEventType = '';
                    var activeWatchFocusEventLabel = '';
                    var activeWatchSortMode = 'auto';
                    var activeSecurityLogView = 'must-watch';
                    var currentSecurityStatusSnapshot = <?php echo wp_json_encode($security_log_status_snapshot); ?>;
                    var activeRosterPage = 1;
                    var activeRosterFilters = {
                        search: '',
                        presence: 'all',
                        risk: 'all',
                        exam: 'all',
                        kelas: 'all',
                        ruang: 'all'
                    };

                    if (!card || !manageForm || !severityFilter || !eventFilter || !deviceFilter || !kelasFilter || !ruangFilter || !studentNameFilter || !autoRefreshToggle || !liveStatus || !securityLogPanel || !tableRegion || observabilityEndpoint === '' || historyEndpoint === '') {
                        return;
                    }

                    function setLiveStatus(message, tone) {
                        liveStatus.textContent = String(message || '');
                        liveStatus.classList.remove('is-loading', 'is-error');

                        if (tone === 'loading') {
                            liveStatus.classList.add('is-loading');
                        } else if (tone === 'error') {
                            liveStatus.classList.add('is-error');
                        }
                    }

                    function getSecurityRestHeaders() {
                        var headers = {
                            'Cache-Control': 'no-cache'
                        };

                        if (restNonce !== '') {
                            headers['X-WP-Nonce'] = restNonce;
                        }

                        return headers;
                    }

                    function formatRedisMonitorDuration(seconds) {
                        var numericSeconds = parseInt(String(seconds || '0'), 10);
                        var minutes = 0;
                        var remainingSeconds = 0;
                        var hours = 0;
                        var remainingMinutes = 0;

                        if (!Number.isFinite(numericSeconds) || numericSeconds <= 0) {
                            return '0 detik';
                        }

                        if (numericSeconds < 60) {
                            return String(numericSeconds) + ' detik';
                        }

                        minutes = Math.floor(numericSeconds / 60);
                        remainingSeconds = numericSeconds % 60;
                        if (minutes < 60) {
                            return remainingSeconds > 0
                                ? (String(minutes) + ' m ' + String(remainingSeconds) + ' dtk')
                                : (String(minutes) + ' menit');
                        }

                        hours = Math.floor(minutes / 60);
                        remainingMinutes = minutes % 60;

                        return remainingMinutes > 0
                            ? (String(hours) + ' j ' + String(remainingMinutes) + ' m')
                            : (String(hours) + ' jam');
                    }

                    function formatRedisMonitorActivity(timestamp, status) {
                        var safeTimestamp = String(timestamp || '').trim();
                        var safeStatus = String(status || '').trim().toUpperCase();

                        if (safeTimestamp === '' && safeStatus === '') {
                            return '-';
                        }

                        if (safeTimestamp === '') {
                            return safeStatus;
                        }

                        if (safeStatus === '') {
                            return safeTimestamp;
                        }

                        return safeTimestamp + ' • ' + safeStatus;
                    }

                    function setRedisMonitorField(name, value) {
                        var field = monitorPanel ? monitorPanel.querySelector('[data-security-log-monitor-field="' + String(name || '') + '"]') : null;
                        if (!field) {
                            return;
                        }

                        field.textContent = String(value || '');
                    }

                    function computeRedisMonitorTone(statusSnapshot) {
                        var snapshot = statusSnapshot && typeof statusSnapshot === 'object' ? statusSnapshot : {};
                        var featureEnabled = Number(snapshot.feature_enabled || 0) > 0;
                        var available = Number(snapshot.available || 0) > 0;
                        var backlogCount = parseInt(String(snapshot.backlog_count || '0'), 10);
                        var deadCount = parseInt(String(snapshot.dead_letter_count || '0'), 10);

                        if (featureEnabled && !available) {
                            return 'critical';
                        }

                        if (!featureEnabled || (Number.isFinite(backlogCount) && backlogCount > 0) || (Number.isFinite(deadCount) && deadCount > 0)) {
                            return 'warning';
                        }

                        return 'healthy';
                    }

                    function computeRedisMonitorHelper(statusSnapshot) {
                        var snapshot = statusSnapshot && typeof statusSnapshot === 'object' ? statusSnapshot : {};
                        var featureEnabled = Number(snapshot.feature_enabled || 0) > 0;
                        var available = Number(snapshot.available || 0) > 0;

                        if (featureEnabled && available) {
                            return 'Redis-first aktif. Audit permanen menyusul lewat batch flush.';
                        }

                        return 'Mode fallback aktif. Event tetap aman ditulis ke MySQL langsung.';
                    }

                    function computeRedisMonitorDisabledReason(statusSnapshot) {
                        var snapshot = statusSnapshot && typeof statusSnapshot === 'object' ? statusSnapshot : {};
                        var featureEnabled = Number(snapshot.feature_enabled || 0) > 0;
                        var available = Number(snapshot.available || 0) > 0;

                        if (!featureEnabled) {
                            return 'Feature flag Redis-first ingest masih nonaktif.';
                        }

                        if (!available) {
                            return 'Redis ingest tidak tersedia saat ini.';
                        }

                        if (ingestActionEndpoint === '') {
                            return 'Endpoint aksi ingest admin belum tersedia.';
                        }

                        return '';
                    }

                    function setRedisMonitorStatus(message) {
                        if (!monitorStatus) {
                            return;
                        }

                        monitorStatus.textContent = String(message || '');
                    }

                    function updateRedisMonitorActionButtons(statusSnapshot) {
                        var snapshot = statusSnapshot && typeof statusSnapshot === 'object' ? statusSnapshot : {};
                        var buttons = monitorPanel ? monitorPanel.querySelectorAll('[data-security-log-monitor-action]') : [];
                        var canRunActions = Number(snapshot.feature_enabled || 0) > 0 && Number(snapshot.available || 0) > 0 && ingestActionEndpoint !== '';
                        var index = 0;
                        var actionName = '';
                        var isDisabled = false;

                        for (index = 0; index < buttons.length; index += 1) {
                            actionName = String(buttons[index].getAttribute('data-security-log-monitor-action') || '');
                            isDisabled = false;

                            if (actionName === 'refresh_monitor') {
                                isDisabled = refreshInFlight || monitorActionInFlight;
                            } else if (actionName === 'copy_diagnostics') {
                                isDisabled = false;
                            } else if (actionName === 'clear_live_state') {
                                isDisabled = ingestActionEndpoint === '' || refreshInFlight || monitorActionInFlight;
                            } else {
                                isDisabled = !canRunActions || refreshInFlight || monitorActionInFlight;
                            }

                            buttons[index].disabled = !!isDisabled;
                        }
                    }

                    function updateRedisMonitorPanel(statusSnapshot) {
                        var snapshot = statusSnapshot && typeof statusSnapshot === 'object' ? statusSnapshot : {};
                        var lastResult = '';
                        var helperText = '';
                        var disabledReason = '';
                        var tone = '';
                        var statusLabel = '';

                        if (!monitorPanel) {
                            return;
                        }

                        currentSecurityStatusSnapshot = snapshot;
                        helperText = computeRedisMonitorHelper(snapshot);
                        disabledReason = computeRedisMonitorDisabledReason(snapshot);
                        tone = computeRedisMonitorTone(snapshot);
                        lastResult = String(snapshot.last_enqueue_error || '').trim();
                        if (lastResult === '') {
                            lastResult = String(snapshot.last_flush_result || '').trim();
                        }
                        if (lastResult === '') {
                            lastResult = '-';
                        }

                        if (monitorHelper) {
                            monitorHelper.textContent = helperText;
                        }

                        statusLabel = String(snapshot.status_label || '').trim();
                        if (monitorStatus) {
                            monitorStatus.textContent = statusLabel !== '' ? statusLabel : 'Status monitor siap.';
                        }

                        monitorPanel.classList.remove('is-healthy', 'is-warning', 'is-critical');
                        monitorPanel.classList.add('is-' + tone);

                        setRedisMonitorField('live_label', String(snapshot.live_label || 'Live MySQL fallback'));
                        setRedisMonitorField('ingest_label', String(snapshot.ingest_label || 'Ingest direct MySQL'));
                        setRedisMonitorField('persist_label', String(snapshot.persist_label || 'Persist direct MySQL'));
                        setRedisMonitorField('feature_enabled', Number(snapshot.feature_enabled || 0) > 0 ? 'On' : 'Off');
                        setRedisMonitorField('available', Number(snapshot.available || 0) > 0 ? 'Yes' : 'No');
                        setRedisMonitorField('stream_supported', Number(snapshot.stream_supported || 0) > 0 ? 'Yes' : 'No');
                        setRedisMonitorField('worker_scheduled', Number(snapshot.worker_scheduled || 0) > 0 ? 'Yes' : 'No');
                        setRedisMonitorField('backlog_count', String(Math.max(0, parseInt(String(snapshot.backlog_count || '0'), 10) || 0)));
                        setRedisMonitorField('oldest_pending', formatRedisMonitorDuration(snapshot.oldest_pending_age_seconds || 0));
                        setRedisMonitorField('dead_letter_count', String(Math.max(0, parseInt(String(snapshot.dead_letter_count || '0'), 10) || 0)));
                        setRedisMonitorField('last_stream_id', String(snapshot.last_stream_id || '-'));
                        setRedisMonitorField('last_enqueue', formatRedisMonitorActivity(snapshot.last_enqueue_at || '', snapshot.last_enqueue_status || ''));
                        setRedisMonitorField('last_flush', formatRedisMonitorActivity(snapshot.last_flush_at || '', snapshot.last_flush_status || ''));
                        setRedisMonitorField('next_flush_at', String(snapshot.next_flush_at || '-'));
                        setRedisMonitorField('last_result', lastResult);

                        if (monitorDiagnostics) {
                            monitorDiagnostics.textContent = JSON.stringify(snapshot, null, 2);
                        }

                        if (monitorDisabledReason) {
                            monitorDisabledReason.textContent = disabledReason;
                            monitorDisabledReason.hidden = disabledReason === '';
                        }

                        updateRedisMonitorActionButtons(snapshot);
                    }

                    function summarizeSecurityMonitorAction(action, actionResult) {
                        var result = actionResult && typeof actionResult === 'object' ? actionResult : {};
                        var persisted = Math.max(0, parseInt(String(result.persisted || result.drained || '0'), 10) || 0);
                        var deadLettered = Math.max(0, parseInt(String(result.dead_lettered || '0'), 10) || 0);
                        var failed = Math.max(0, parseInt(String(result.failed || '0'), 10) || 0);
                        var skipped = Math.max(0, parseInt(String(result.skipped || '0'), 10) || 0);
                        var reason = String(result.reason || '').trim();

                        if (action === 'micro_drain') {
                            if (skipped > 0) {
                                if (reason === 'backlog_small') {
                                    return 'Micro-drain dilewati: backlog kecil.';
                                }
                                if (reason === 'lock_busy') {
                                    return 'Micro-drain dilewati: worker sedang sibuk.';
                                }
                                if (reason === 'redis_unavailable') {
                                    return 'Micro-drain dilewati: Redis unavailable.';
                                }
                                return 'Micro-drain dilewati.';
                            }

                            return 'Micro-drain selesai: ' + String(persisted) + ' event persisted.';
                        }

                        if (action === 'clear_live_state') {
                            return 'Live roster dibersihkan. Histori log tetap aman.';
                        }

                        if (skipped > 0 && reason !== '') {
                            return 'Flush dilewati: ' + reason + '.';
                        }

                        return 'Flush selesai: ' + String(persisted) + ' persisted, ' + String(deadLettered) + ' dead-letter, ' + String(failed) + ' gagal.';
                    }

                    function copySecurityDiagnostics() {
                        var diagnosticsText = '';
                        var textarea = null;

                        if (!monitorDiagnostics) {
                            return Promise.reject(new Error('Diagnostics monitor tidak tersedia.'));
                        }

                        diagnosticsText = String(monitorDiagnostics.textContent || '').trim();
                        if (diagnosticsText === '') {
                            diagnosticsText = JSON.stringify(currentSecurityStatusSnapshot || {}, null, 2);
                        }

                        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                            return navigator.clipboard.writeText(diagnosticsText);
                        }

                        return new Promise(function (resolve, reject) {
                            textarea = document.createElement('textarea');
                            textarea.value = diagnosticsText;
                            textarea.setAttribute('readonly', 'readonly');
                            textarea.style.position = 'fixed';
                            textarea.style.opacity = '0';
                            textarea.style.pointerEvents = 'none';
                            document.body.appendChild(textarea);
                            textarea.focus();
                            textarea.select();

                            try {
                                if (document.execCommand('copy')) {
                                    resolve();
                                } else {
                                    reject(new Error('copy_command_failed'));
                                }
                            } catch (error) {
                                reject(error);
                            } finally {
                                document.body.removeChild(textarea);
                            }
                        });
                    }

                    function triggerSecurityIngestAdminAction(actionName) {
                        var requestBody = '';

                        if (monitorActionInFlight || ingestActionEndpoint === '') {
                            return Promise.resolve();
                        }

                        monitorActionInFlight = true;
                        updateRedisMonitorActionButtons(currentSecurityStatusSnapshot || {});
                        startSecurityProgress('logs');
                        if (actionName === 'micro_drain') {
                            setRedisMonitorStatus('Menjalankan micro-drain...');
                            setLiveStatus('Menjalankan micro-drain...', 'loading');
                        } else if (actionName === 'clear_live_state') {
                            setRedisMonitorStatus('Membersihkan live roster...');
                            setLiveStatus('Membersihkan live roster...', 'loading');
                        } else {
                            setRedisMonitorStatus('Menjalankan flush batch...');
                            setLiveStatus('Menjalankan flush batch...', 'loading');
                        }

                        requestBody = JSON.stringify({
                            action: actionName
                        });

                        return fetch(ingestActionEndpoint, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: Object.assign({
                                'Content-Type': 'application/json'
                            }, getSecurityRestHeaders()),
                            body: requestBody
                        })
                            .then(function (response) {
                                if (!response.ok) {
                                    throw new Error('Aksi Redis monitor gagal diproses.');
                                }

                                return response.json();
                            })
                            .then(function (payload) {
                                var statusSnapshot = payload && payload.status_snapshot && typeof payload.status_snapshot === 'object'
                                    ? payload.status_snapshot
                                    : {};
                                var actionMessage = summarizeSecurityMonitorAction(actionName, payload ? payload.action_result : {});

                                updateSecurityLogStatusChips(statusSnapshot);
                                updateRedisMonitorPanel(statusSnapshot);
                                setRedisMonitorStatus(actionMessage);
                                setLiveStatus(actionMessage, '');
                                completeSecurityProgress('Security Log diperbarui.', actionMessage, '');

                                if (activeSecurityLogView === 'history') {
                                    return refreshSecurityHistoryRegion().then(function () {
                                        return payload;
                                    });
                                }

                                return payload;
                            })
                            .catch(function (error) {
                                var message = error instanceof Error && error.message !== ''
                                    ? error.message
                                    : 'Aksi Redis monitor gagal dijalankan.';
                                setRedisMonitorStatus(message);
                                setLiveStatus(message, 'error');
                                completeSecurityProgress('Aksi Security Log gagal.', message, 'error');
                                throw error;
                            })
                            .finally(function () {
                                monitorActionInFlight = false;
                                updateRedisMonitorActionButtons(currentSecurityStatusSnapshot || {});
                            });
                    }

                    function updateSecurityLogStatusChips(statusSnapshot) {
                        var backlogCount = 0;
                        var deadCount = 0;
                        var statusLabel = '';
                        var liveLabel = '';
                        var ingestLabel = '';
                        var persistLabel = '';

                        if (!statusSnapshot || typeof statusSnapshot !== 'object') {
                            return;
                        }

                        backlogCount = parseInt(String(statusSnapshot.backlog_count || '0'), 10);
                        deadCount = parseInt(String(statusSnapshot.dead_letter_count || '0'), 10);
                        statusLabel = String(statusSnapshot.status_label || '');
                        liveLabel = String(statusSnapshot.live_label || '');
                        ingestLabel = String(statusSnapshot.ingest_label || '');
                        persistLabel = String(statusSnapshot.persist_label || '');

                        if (statusChip) {
                            statusChip.textContent = String(<?php echo $security_log_events_enabled ? wp_json_encode('Logging On') : wp_json_encode('Logging Off'); ?>);
                        }

                        if (liveChip && liveLabel !== '') {
                            liveChip.textContent = liveLabel;
                            if (statusLabel !== '') {
                                liveChip.title = statusLabel;
                            }
                        }

                        if (ingestChip && ingestLabel !== '') {
                            ingestChip.textContent = ingestLabel;
                        }

                        if (persistChip && persistLabel !== '') {
                            persistChip.textContent = persistLabel;
                        }

                        if (backlogChip) {
                            backlogChip.textContent = 'Backlog ' + String(Number.isFinite(backlogCount) ? backlogCount : 0);
                        }

                        if (deadChip) {
                            deadChip.textContent = 'Dead Letter ' + String(Number.isFinite(deadCount) ? deadCount : 0);
                            deadChip.hidden = !(Number.isFinite(deadCount) && deadCount > 0);
                        }

                        currentSecurityStatusSnapshot = statusSnapshot;
                    }

                    function updateSecurityLogViewBadge(view, count) {
                        var button = document.querySelector('[data-security-log-view-button="' + String(view || '') + '"]');
                        var badge = button ? button.querySelector('.cbt-setup-security-log-view-tab-badge') : null;

                        if (!badge) {
                            return;
                        }

                        badge.textContent = String(Math.max(0, parseInt(String(count || '0'), 10) || 0));
                    }

                    function refreshSecurityHistoryRegion() {
                        var requestUrl = new URL(historyEndpoint, window.location.origin);

                        requestUrl.searchParams.set('page', '1');
                        requestUrl.searchParams.set('per_page', '20');
                        requestUrl.searchParams.set('severity', String(severityFilter.value || 'all'));
                        requestUrl.searchParams.set('event_type', String(eventFilter.value || 'all'));
                        requestUrl.searchParams.set('device_type', String(deviceFilter.value || 'all'));
                        requestUrl.searchParams.set('kelas', String(kelasFilter.value || 'all'));
                        requestUrl.searchParams.set('ruang', String(ruangFilter.value || 'all'));
                        requestUrl.searchParams.set('student_name', String(studentNameFilter.value || ''));

                        return fetch(requestUrl.toString(), {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: getSecurityRestHeaders()
                        })
                            .then(function (response) {
                                if (!response.ok) {
                                    throw new Error('Histori log gagal dimuat.');
                                }

                                return response.json();
                            })
                            .then(function (payload) {
                                if (!payload || payload.ok !== true || typeof payload.history_html !== 'string') {
                                    throw new Error('Payload histori log tidak valid.');
                                }

                                tableRegion.innerHTML = payload.history_html;
                                if (deleteScopeInput) {
                                    deleteScopeInput.value = '';
                                }
                                if (deleteAllButton) {
                                    deleteAllButton.disabled = Number(payload.total || 0) <= 0;
                                }
                                updateSecurityLogViewBadge('history', Number(payload.total || 0));
                                syncDynamicFilterOptions();
                                applySecurityLogFilters();
                            });
                    }

                    function getCurrentRows() {
                        return card.querySelectorAll('[data-security-log-row]');
                    }

                    function getDefaultEmptyRow() {
                        return card.querySelector('[data-security-log-empty-default]');
                    }

                    function getFilterEmptyRow() {
                        return document.getElementById('cbt-setup-security-log-filter-empty');
                    }

                    function normalizeFilterValue(value) {
                        return String(value || '').trim().toLowerCase();
                    }

                    function normalizeWatchSortMode(value) {
                        var mode = String(value || '').trim().toLowerCase();
                        if (mode !== 'score' && mode !== 'recent') {
                            return 'auto';
                        }

                        return mode;
                    }

                    function normalizeSecurityLogView(value) {
                        var view = String(value || '').trim().toLowerCase();
                        if (view !== 'history' && view !== 'live-roster') {
                            return 'must-watch';
                        }

                        return view;
                    }

                    function normalizeRosterSearchValue(value) {
                        return String(value || '').trim().toLowerCase();
                    }

                    function normalizeRosterPresenceValue(value) {
                        var normalized = String(value || '').trim().toLowerCase();
                        if (normalized !== 'online' && normalized !== 'stale' && normalized !== 'offline') {
                            return 'all';
                        }

                        return normalized;
                    }

                    function normalizeRosterRiskValue(value) {
                        var normalized = String(value || '').trim().toLowerCase();
                        if (normalized !== 'safe' && normalized !== 'watch' && normalized !== 'high-risk') {
                            return 'all';
                        }

                        return normalized;
                    }

                    function normalizeRosterSelectValue(value) {
                        var normalized = String(value || '').trim();
                        return normalized === '' ? 'all' : normalized;
                    }

                    function readStoredSecurityLogView() {
                        try {
                            if (window.localStorage) {
                                return normalizeSecurityLogView(window.localStorage.getItem(activeViewStorageKey) || 'must-watch');
                            }
                        } catch (error) {
                            // Ignore storage errors.
                        }

                        return 'must-watch';
                    }

                    function storeSecurityLogView(view) {
                        try {
                            if (window.localStorage) {
                                window.localStorage.setItem(activeViewStorageKey, normalizeSecurityLogView(view));
                            }
                        } catch (error) {
                            // Ignore storage errors.
                        }
                    }

                    function getSecurityLogViewButtons() {
                        return card.querySelectorAll('[data-security-log-view-button]');
                    }

                    function getSecurityLogViewPanels() {
                        return card.querySelectorAll('[data-security-log-view-panel]');
                    }

                    function getSecurityLogViewPanel(view) {
                        return card.querySelector('[data-security-log-view-panel="' + normalizeSecurityLogView(view) + '"]');
                    }

                    function getLiveRosterRoot() {
                        return rosterRegion ? rosterRegion.querySelector('[data-security-log-live-roster]') : null;
                    }

                    function getLiveRosterGroups() {
                        var root = getLiveRosterRoot();
                        return root ? root.querySelectorAll('[data-security-log-roster-group]') : [];
                    }

                    function getLiveRosterFilterControl(name) {
                        var root = getLiveRosterRoot();
                        return root ? root.querySelector('[data-security-log-roster-filter="' + name + '"]') : null;
                    }

                    function getLiveRosterPagerLabel() {
                        var root = getLiveRosterRoot();
                        return root ? root.querySelector('[data-security-log-roster-page-label]') : null;
                    }

                    function getLiveRosterSummary() {
                        var root = getLiveRosterRoot();
                        return root ? root.querySelector('[data-security-log-roster-summary]') : null;
                    }

                    function getLiveRosterFilterEmpty() {
                        var root = getLiveRosterRoot();
                        return root ? root.querySelector('[data-security-log-roster-filter-empty]') : null;
                    }

                    function getLiveRosterPageSize() {
                        var root = getLiveRosterRoot();
                        var value = root ? parseInt(root.getAttribute('data-security-log-roster-page-size') || '4', 10) : 4;
                        if (!Number.isFinite(value) || value <= 0) {
                            return 4;
                        }

                        return value;
                    }

                    function ensureLazyRegionLoaded(region) {
                        var template = null;
                        var shell = null;

                        if (!region || String(region.getAttribute('data-security-log-lazy-loaded') || '0') === '1') {
                            return;
                        }

                        template = region.querySelector('[data-security-log-lazy-template]');
                        shell = region.querySelector('[data-security-log-lazy-shell]');

                        if (!template || !shell) {
                            region.setAttribute('data-security-log-lazy-loaded', '1');
                            return;
                        }

                        shell.innerHTML = '';
                        shell.appendChild(template.content.cloneNode(true));
                        region.setAttribute('data-security-log-lazy-loaded', '1');
                    }

                    function ensureSecurityLogViewLoaded(view) {
                        var panel = getSecurityLogViewPanel(view);
                        var lazyRegions = [];
                        var index = 0;

                        if (!panel) {
                            return;
                        }

                        lazyRegions = panel.querySelectorAll('[data-security-log-lazy-region]');
                        for (index = 0; index < lazyRegions.length; index += 1) {
                            ensureLazyRegionLoaded(lazyRegions[index]);
                        }
                    }

                    function syncLiveRosterControlValues() {
                        var searchControl = getLiveRosterFilterControl('search');
                        var presenceControl = getLiveRosterFilterControl('presence');
                        var riskControl = getLiveRosterFilterControl('risk');
                        var examControl = getLiveRosterFilterControl('exam');
                        var kelasControl = getLiveRosterFilterControl('kelas');
                        var ruangControl = getLiveRosterFilterControl('ruang');

                        if (searchControl) {
                            searchControl.value = activeRosterFilters.search;
                        }

                        if (presenceControl) {
                            presenceControl.value = normalizeRosterPresenceValue(activeRosterFilters.presence);
                        }

                        if (riskControl) {
                            riskControl.value = normalizeRosterRiskValue(activeRosterFilters.risk);
                        }

                        if (examControl) {
                            examControl.value = hasSelectOption(examControl, activeRosterFilters.exam) ? activeRosterFilters.exam : 'all';
                            activeRosterFilters.exam = String(examControl.value || 'all');
                        }

                        if (kelasControl) {
                            kelasControl.value = hasSelectOption(kelasControl, activeRosterFilters.kelas) ? activeRosterFilters.kelas : 'all';
                            activeRosterFilters.kelas = String(kelasControl.value || 'all');
                        }

                        if (ruangControl) {
                            ruangControl.value = hasSelectOption(ruangControl, activeRosterFilters.ruang) ? activeRosterFilters.ruang : 'all';
                            activeRosterFilters.ruang = String(ruangControl.value || 'all');
                        }
                    }

                    function updateLiveRosterPaginationUi(page, pageCount, visibleGroupCount, visibleAttemptCount, shownGroupCount) {
                        var root = getLiveRosterRoot();
                        var summary = getLiveRosterSummary();
                        var label = getLiveRosterPagerLabel();
                        var prevButton = root ? root.querySelector('[data-security-log-roster-page-prev]') : null;
                        var nextButton = root ? root.querySelector('[data-security-log-roster-page-next]') : null;
                        var filterEmpty = getLiveRosterFilterEmpty();

                        if (summary) {
                            summary.textContent = visibleGroupCount > 0
                                ? ('Menampilkan ' + String(shownGroupCount) + ' dari ' + String(visibleGroupCount) + ' grup • ' + String(visibleAttemptCount) + ' attempt cocok.')
                                : 'Tidak ada grup roster yang cocok dengan filter saat ini.';
                        }

                        if (label) {
                            label.textContent = visibleGroupCount > 0
                                ? ('Halaman ' + String(page) + ' / ' + String(pageCount))
                                : 'Halaman 0 / 0';
                        }

                        if (prevButton) {
                            prevButton.disabled = page <= 1;
                        }

                        if (nextButton) {
                            nextButton.disabled = visibleGroupCount <= 0 || page >= pageCount;
                        }

                        if (filterEmpty) {
                            filterEmpty.hidden = visibleGroupCount > 0;
                        }
                    }

                    function applyLiveRosterFiltersAndPagination() {
                        var groups = Array.prototype.slice.call(getLiveRosterGroups());
                        var visibleGroups = [];
                        var visibleAttemptCount = 0;
                        var pageSize = getLiveRosterPageSize();
                        var pageCount = 0;
                        var startIndex = 0;
                        var endIndex = 0;

                        if (!groups.length) {
                            updateLiveRosterPaginationUi(0, 0, 0, 0, 0);
                            return;
                        }

                        groups.forEach(function (group) {
                            var groupExam = normalizeRosterSelectValue(group.getAttribute('data-security-log-roster-exam') || '');
                            var groupKelas = normalizeRosterSelectValue(group.getAttribute('data-security-log-roster-kelas') || '');
                            var groupRuang = normalizeRosterSelectValue(group.getAttribute('data-security-log-roster-ruang') || '');
                            var matchesGroupExam = activeRosterFilters.exam === 'all' || groupExam === activeRosterFilters.exam;
                            var matchesGroupKelas = activeRosterFilters.kelas === 'all' || groupKelas === activeRosterFilters.kelas;
                            var matchesGroupRuang = activeRosterFilters.ruang === 'all' || groupRuang === activeRosterFilters.ruang;
                            var matchesGroup = matchesGroupExam && matchesGroupKelas && matchesGroupRuang;
                            var rows = Array.prototype.slice.call(group.querySelectorAll('[data-security-log-roster-row]'));
                            var matchingRows = 0;

                            rows.forEach(function (row) {
                                var rowPresence = normalizeRosterPresenceValue(row.getAttribute('data-security-log-roster-presence') || '');
                                var rowRisk = normalizeRosterRiskValue(row.getAttribute('data-security-log-roster-risk') || '');
                                var rowSearchText = normalizeRosterSearchValue([
                                    row.getAttribute('data-security-log-roster-student-name') || '',
                                    row.getAttribute('data-security-log-roster-student-login') || '',
                                    row.getAttribute('data-security-log-roster-attempt') || ''
                                ].join(' '));
                                var matchesPresence = activeRosterFilters.presence === 'all' || rowPresence === activeRosterFilters.presence;
                                var matchesRisk = activeRosterFilters.risk === 'all' || rowRisk === activeRosterFilters.risk;
                                var matchesSearch = activeRosterFilters.search === '' || rowSearchText.indexOf(activeRosterFilters.search) >= 0;
                                var isVisible = matchesGroup && matchesPresence && matchesRisk && matchesSearch;

                                row.hidden = !isVisible;
                                if (isVisible) {
                                    matchingRows += 1;
                                }
                            });

                            group.hidden = !matchesGroup || matchingRows === 0;
                            if (matchesGroup && matchingRows > 0) {
                                visibleGroups.push(group);
                                visibleAttemptCount += matchingRows;
                            }
                        });

                        pageCount = visibleGroups.length > 0 ? Math.ceil(visibleGroups.length / pageSize) : 0;
                        if (pageCount <= 0) {
                            activeRosterPage = 1;
                            updateLiveRosterPaginationUi(0, 0, 0, visibleAttemptCount, 0);
                            return;
                        }

                        if (activeRosterPage > pageCount) {
                            activeRosterPage = pageCount;
                        }

                        if (activeRosterPage < 1) {
                            activeRosterPage = 1;
                        }

                        startIndex = (activeRosterPage - 1) * pageSize;
                        endIndex = startIndex + pageSize;

                        visibleGroups.forEach(function (group, index) {
                            group.hidden = index < startIndex || index >= endIndex;
                        });

                        updateLiveRosterPaginationUi(
                            activeRosterPage,
                            pageCount,
                            visibleGroups.length,
                            visibleAttemptCount,
                            Math.max(0, Math.min(visibleGroups.length, endIndex) - startIndex)
                        );
                    }

                    function setActiveSecurityLogView(view, options) {
                        var normalizedView = normalizeSecurityLogView(view);
                        var buttons = getSecurityLogViewButtons();
                        var panels = getSecurityLogViewPanels();
                        var index = 0;

                        activeSecurityLogView = normalizedView;
                        ensureSecurityLogViewLoaded(normalizedView);

                        for (index = 0; index < buttons.length; index += 1) {
                            var buttonView = normalizeSecurityLogView(buttons[index].getAttribute('data-security-log-view-button') || 'history');
                            var isActiveButton = buttonView === normalizedView;
                            buttons[index].classList.toggle('is-active', isActiveButton);
                            buttons[index].setAttribute('aria-selected', isActiveButton ? 'true' : 'false');
                            buttons[index].setAttribute('tabindex', isActiveButton ? '0' : '-1');
                        }

                        for (index = 0; index < panels.length; index += 1) {
                            var panelView = normalizeSecurityLogView(panels[index].getAttribute('data-security-log-view-panel') || 'history');
                            panels[index].hidden = panelView !== normalizedView;
                        }

                        if (!options || options.persist !== false) {
                            storeSecurityLogView(normalizedView);
                        }

                        if (normalizedView === 'must-watch') {
                            applyMustWatchSort(activeWatchSortMode);
                        } else if (normalizedView === 'live-roster') {
                            syncLiveRosterControlValues();
                            applyLiveRosterFiltersAndPagination();
                        } else if (normalizedView === 'history') {
                            applySecurityLogFilters();
                        }
                    }

                    function readStoredWatchSortMode() {
                        try {
                            if (window.localStorage) {
                                return normalizeWatchSortMode(window.localStorage.getItem(watchSortStorageKey) || 'auto');
                            }
                        } catch (error) {
                            // Ignore storage errors.
                        }

                        return 'auto';
                    }

                    function storeWatchSortMode(mode) {
                        try {
                            if (window.localStorage) {
                                window.localStorage.setItem(watchSortStorageKey, normalizeWatchSortMode(mode));
                            }
                        } catch (error) {
                            // Ignore storage errors.
                        }
                    }

                    function getWatchList() {
                        return card.querySelector('.cbt-setup-security-log-watch-list');
                    }

                    function syncWatchSortButtons() {
                        var buttons = watchRegion ? watchRegion.querySelectorAll('[data-security-log-watch-sort]') : [];
                        var index = 0;

                        for (index = 0; index < buttons.length; index += 1) {
                            var buttonMode = normalizeWatchSortMode(buttons[index].getAttribute('data-security-log-watch-sort') || 'auto');
                            var isActive = buttonMode === activeWatchSortMode;
                            buttons[index].classList.toggle('is-active', isActive);
                            buttons[index].setAttribute('aria-pressed', isActive ? 'true' : 'false');
                        }
                    }

                    function applyMustWatchSort(mode) {
                        var watchList = getWatchList();
                        var items = [];

                        activeWatchSortMode = normalizeWatchSortMode(mode);
                        syncWatchSortButtons();

                        if (!watchList) {
                            return;
                        }

                        items = Array.prototype.slice.call(watchList.querySelectorAll('[data-security-log-focus-card]'));
                        if (items.length <= 1) {
                            return;
                        }

                        items.sort(function (left, right) {
                            var leftOrder = parseInt(left.getAttribute('data-sort-order') || '0', 10) || 0;
                            var rightOrder = parseInt(right.getAttribute('data-sort-order') || '0', 10) || 0;
                            var leftScore = parseFloat(left.getAttribute('data-sort-score') || '0') || 0;
                            var rightScore = parseFloat(right.getAttribute('data-sort-score') || '0') || 0;
                            var leftLastAt = String(left.getAttribute('data-sort-last-at') || '');
                            var rightLastAt = String(right.getAttribute('data-sort-last-at') || '');

                            if (activeWatchSortMode === 'score') {
                                if (leftScore !== rightScore) {
                                    return rightScore - leftScore;
                                }

                                if (leftLastAt !== rightLastAt) {
                                    return rightLastAt.localeCompare(leftLastAt);
                                }

                                return leftOrder - rightOrder;
                            }

                            if (activeWatchSortMode === 'recent') {
                                if (leftLastAt !== rightLastAt) {
                                    return rightLastAt.localeCompare(leftLastAt);
                                }

                                if (leftScore !== rightScore) {
                                    return rightScore - leftScore;
                                }

                                return leftOrder - rightOrder;
                            }

                            return leftOrder - rightOrder;
                        });

                        items.forEach(function (item) {
                            watchList.appendChild(item);
                        });
                    }

                    function hasSelectOption(selectElement, optionValue) {
                        var options = selectElement ? selectElement.options : [];
                        var index = 0;

                        for (index = 0; index < options.length; index += 1) {
                            if (String(options[index].value || '') === String(optionValue || '')) {
                                return true;
                            }
                        }

                        return false;
                    }

                    function updateWatchFocusState() {
                        var labelParts = [];

                        if (!focusState || !focusLabel) {
                            return;
                        }

                        if (activeWatchFocusAttempt === '') {
                            focusState.hidden = true;
                            focusLabel.textContent = '';
                            return;
                        }

                        if (activeWatchFocusStudent !== '') {
                            labelParts.push(activeWatchFocusStudent);
                        }

                        if (activeWatchFocusKelas !== '') {
                            labelParts.push('K: ' + activeWatchFocusKelas);
                        }

                        if (activeWatchFocusRuang !== '') {
                            labelParts.push('R: ' + activeWatchFocusRuang);
                        }

                        labelParts.push('Attempt #' + activeWatchFocusAttempt);

                        focusLabel.textContent = 'Fokus: ' + labelParts.join(' • ');
                        focusState.hidden = false;
                    }

                    function clearWatchFocus(resetVisibleFilters) {
                        activeWatchFocusAttempt = '';
                        activeWatchFocusStudent = '';
                        activeWatchFocusKelas = '';
                        activeWatchFocusRuang = '';
                        activeWatchFocusEventType = '';
                        activeWatchFocusEventLabel = '';

                        if (resetVisibleFilters !== false) {
                            severityFilter.value = 'all';
                            eventFilter.value = 'all';
                            deviceFilter.value = 'all';
                            kelasFilter.value = 'all';
                            ruangFilter.value = 'all';
                            studentNameFilter.value = '';
                        }

                        updateWatchFocusState();
                        applySecurityLogFilters();
                    }

                    function focusLogsFromWatchCard(cardElement) {
                        var nextAttempt = cardElement ? String(cardElement.getAttribute('data-focus-attempt') || '').trim() : '';
                        var nextStudent = cardElement ? String(cardElement.getAttribute('data-focus-student') || '').trim() : '';
                        var nextKelas = cardElement ? String(cardElement.getAttribute('data-focus-kelas') || '').trim() : '';
                        var nextRuang = cardElement ? String(cardElement.getAttribute('data-focus-ruang') || '').trim() : '';

                        if (nextAttempt === '') {
                            return;
                        }

                        activeWatchFocusAttempt = nextAttempt;
                        activeWatchFocusStudent = nextStudent;
                        activeWatchFocusKelas = nextKelas;
                        activeWatchFocusRuang = nextRuang;
                        activeWatchFocusEventType = '';
                        activeWatchFocusEventLabel = '';

                        severityFilter.value = 'all';
                        deviceFilter.value = 'all';
                        if (nextKelas !== '' && hasSelectOption(kelasFilter, nextKelas)) {
                            kelasFilter.value = nextKelas;
                        } else {
                            kelasFilter.value = 'all';
                        }
                        if (nextRuang !== '' && hasSelectOption(ruangFilter, nextRuang)) {
                            ruangFilter.value = nextRuang;
                        } else {
                            ruangFilter.value = 'all';
                        }
                        studentNameFilter.value = nextStudent;
                        eventFilter.value = 'all';

                        updateWatchFocusState();
                        setActiveSecurityLogView('history');
                        applySecurityLogFilters();

                        if (tableRegion && typeof tableRegion.scrollIntoView === 'function') {
                            tableRegion.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }

                    function rebuildDynamicFilterOptions(selectElement, attributeName, defaultLabel, labelAttributeName) {
                        var rows = getCurrentRows();
                        var currentValue = String(selectElement.value || 'all');
                        var values = [];
                        var seen = {};
                        var labelsByValue = {};
                        var index = 0;
                        var rowValue = '';
                        var rowLabel = '';
                        var option = null;

                        selectElement.innerHTML = '';
                        option = document.createElement('option');
                        option.value = 'all';
                        option.textContent = defaultLabel;
                        selectElement.appendChild(option);

                        for (index = 0; index < rows.length; index += 1) {
                            rowValue = String(rows[index].getAttribute(attributeName) || '').trim();
                            if (rowValue === '') {
                                continue;
                            }

                            if (seen[rowValue]) {
                                continue;
                            }

                            seen[rowValue] = true;
                            rowLabel = labelAttributeName ? String(rows[index].getAttribute(labelAttributeName) || '').trim() : '';
                            labelsByValue[rowValue] = rowLabel !== '' ? rowLabel : rowValue;
                            values.push(rowValue);
                        }

                        values.sort(function (left, right) {
                            return left.localeCompare(right, undefined, { sensitivity: 'base', numeric: true });
                        });

                        for (index = 0; index < values.length; index += 1) {
                            option = document.createElement('option');
                            option.value = values[index];
                            option.textContent = labelsByValue[values[index]] || values[index];
                            selectElement.appendChild(option);
                        }

                        if (currentValue !== 'all' && seen[currentValue]) {
                            selectElement.value = currentValue;
                        } else {
                            selectElement.value = 'all';
                        }
                    }

                    function syncDynamicFilterOptions() {
                        rebuildDynamicFilterOptions(deviceFilter, 'data-log-device', 'Semua device', 'data-log-device-label');
                        rebuildDynamicFilterOptions(kelasFilter, 'data-log-kelas', 'Semua kelas');
                        rebuildDynamicFilterOptions(ruangFilter, 'data-log-ruang', 'Semua ruang');
                    }

                    function getSelectAllCheckbox() {
                        return card.querySelector('[data-security-log-select-all]');
                    }

                    function getRowCheckboxes(visibleOnly) {
                        var rows = getCurrentRows();
                        var checkboxes = [];
                        var index = 0;

                        for (index = 0; index < rows.length; index += 1) {
                            var row = rows[index];
                            var checkbox = row.querySelector('[data-security-log-select]');

                            if (!checkbox) {
                                continue;
                            }

                            if (visibleOnly && row.hidden) {
                                continue;
                            }

                            checkboxes.push(checkbox);
                        }

                        return checkboxes;
                    }

                    function getCheckedRowCount() {
                        var checkboxes = getRowCheckboxes(false);
                        var checkedCount = 0;
                        var index = 0;

                        for (index = 0; index < checkboxes.length; index += 1) {
                            if (checkboxes[index].checked) {
                                checkedCount += 1;
                            }
                        }

                        return checkedCount;
                    }

                    function updateBulkActionState() {
                        var allCheckboxes = getRowCheckboxes(false);
                        var visibleCheckboxes = getRowCheckboxes(true);
                        var checkedCount = getCheckedRowCount();
                        var visibleCheckedCount = 0;
                        var index = 0;
                        var selectAllCheckbox = getSelectAllCheckbox();

                        for (index = 0; index < visibleCheckboxes.length; index += 1) {
                            if (visibleCheckboxes[index].checked) {
                                visibleCheckedCount += 1;
                            }
                        }

                        if (deleteSelectedButton) {
                            deleteSelectedButton.disabled = checkedCount === 0;
                            deleteSelectedButton.textContent = checkedCount > 0
                                ? ('Delete Selected (' + String(checkedCount) + ')')
                                : 'Delete Selected';
                        }

                        if (deleteAllButton) {
                            deleteAllButton.disabled = allCheckboxes.length === 0;
                        }

                        if (!selectAllCheckbox) {
                            return;
                        }

                        if (visibleCheckboxes.length === 0) {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = false;
                            selectAllCheckbox.disabled = true;
                            return;
                        }

                        selectAllCheckbox.disabled = false;
                        selectAllCheckbox.checked = visibleCheckedCount > 0 && visibleCheckedCount === visibleCheckboxes.length;
                        selectAllCheckbox.indeterminate = visibleCheckedCount > 0 && visibleCheckedCount < visibleCheckboxes.length;
                    }

                    function applySecurityLogFilters() {
                        var rows = getCurrentRows();
                        var severityValue = String(severityFilter.value || 'all');
                        var eventValue = String(eventFilter.value || 'all');
                        var deviceValue = String(deviceFilter.value || 'all');
                        var kelasValue = String(kelasFilter.value || 'all');
                        var ruangValue = String(ruangFilter.value || 'all');
                        var studentNameValue = normalizeFilterValue(studentNameFilter.value || '');
                        var attemptValue = String(activeWatchFocusAttempt || '');
                        var visibleCount = 0;
                        var index = 0;
                        var defaultEmptyRow = getDefaultEmptyRow();
                        var filterEmptyRow = getFilterEmptyRow();

                        if (defaultEmptyRow) {
                            defaultEmptyRow.hidden = rows.length > 0;
                        }

                        for (index = 0; index < rows.length; index += 1) {
                            var row = rows[index];
                            var rowSeverity = String(row.getAttribute('data-log-severity') || '');
                            var rowEvent = String(row.getAttribute('data-log-event') || '');
                            var rowDevice = String(row.getAttribute('data-log-device') || '');
                            var rowKelas = String(row.getAttribute('data-log-kelas') || '');
                            var rowRuang = String(row.getAttribute('data-log-ruang') || '');
                            var rowAttempt = String(row.getAttribute('data-log-attempt') || '');
                            var rowStudentName = normalizeFilterValue(row.getAttribute('data-log-student-name') || '');
                            var matchesSeverity = severityValue === 'all' || rowSeverity === severityValue;
                            var matchesEvent = eventValue === 'all' || rowEvent === eventValue;
                            var matchesDevice = deviceValue === 'all' || rowDevice === deviceValue;
                            var matchesKelas = kelasValue === 'all' || rowKelas === kelasValue;
                            var matchesRuang = ruangValue === 'all' || rowRuang === ruangValue;
                            var matchesAttempt = attemptValue === '' || rowAttempt === attemptValue;
                            var matchesStudentName = studentNameValue === '' || rowStudentName.indexOf(studentNameValue) >= 0;
                            var isVisible = matchesSeverity && matchesEvent && matchesDevice && matchesKelas && matchesRuang && matchesAttempt && matchesStudentName;

                            row.hidden = !isVisible;
                            if (!isVisible) {
                                var rowCheckbox = row.querySelector('[data-security-log-select]');
                                if (rowCheckbox) {
                                    rowCheckbox.checked = false;
                                }
                            }
                            if (isVisible) {
                                visibleCount += 1;
                            }
                        }

                        if (filterEmptyRow) {
                            filterEmptyRow.hidden = rows.length === 0 || visibleCount > 0;
                        }

                        updateBulkActionState();
                    }

                    function readStoredAutoRefreshPreference() {
                        try {
                            return window.localStorage && window.localStorage.getItem(storageKey) === '1';
                        } catch (error) {
                            return false;
                        }
                    }

                    function storeAutoRefreshPreference(enabled) {
                        try {
                            if (window.localStorage) {
                                window.localStorage.setItem(storageKey, enabled ? '1' : '0');
                            }
                        } catch (error) {
                            // Ignore storage errors.
                        }
                    }

                    function stopAutoRefresh() {
                        if (autoRefreshTimer) {
                            window.clearInterval(autoRefreshTimer);
                        }
                        autoRefreshTimer = 0;
                    }

                    function isSecurityLogPanelActive() {
                        return !securityLogPanel.hidden;
                    }

                    function refreshSecurityLogCard(options) {
                        var manualRefresh = !!(options && options.force === true);
                        var statusMessage = options && typeof options.statusMessage === 'string'
                            ? options.statusMessage
                            : 'Memuat observability terbaru...';

                        if (refreshInFlight || monitorActionInFlight) {
                            return Promise.resolve();
                        }

                        if (!manualRefresh && !autoRefreshToggle.checked) {
                            return Promise.resolve();
                        }

                        if (!manualRefresh && (!isSecurityLogPanelActive() || document.visibilityState === 'hidden')) {
                            return Promise.resolve();
                        }

                        refreshInFlight = true;
                        updateRedisMonitorActionButtons(currentSecurityStatusSnapshot || {});
                        setLiveStatus(statusMessage, 'loading');
                        if (manualRefresh) {
                            startSecurityProgress('logs');
                            setRedisMonitorStatus(statusMessage);
                        }

                        var snapshotUrl = new URL(observabilityEndpoint, window.location.origin);
                        var pendingRequests = [];

                        snapshotUrl.searchParams.set('micro_drain', '1');

                        pendingRequests.push(
                            fetch(snapshotUrl.toString(), {
                                method: 'GET',
                                credentials: 'same-origin',
                                headers: getSecurityRestHeaders()
                            })
                                .then(function (response) {
                                    if (!response.ok) {
                                        throw new Error('Snapshot observability gagal dimuat.');
                                    }

                                    return response.json();
                                })
                                .then(function (payload) {
                                    if (!payload || payload.ok !== true) {
                                        throw new Error('Payload observability tidak valid.');
                                    }

                                    if (watchRegion && typeof payload.must_watch_html === 'string' && payload.must_watch_html !== '') {
                                        watchRegion.innerHTML = payload.must_watch_html;
                                        watchRegion.setAttribute('data-security-log-lazy-loaded', '1');
                                    }

                                    if (rosterRegion && typeof payload.live_roster_html === 'string' && payload.live_roster_html !== '') {
                                        rosterRegion.innerHTML = payload.live_roster_html;
                                        rosterRegion.setAttribute('data-security-log-lazy-loaded', '1');
                                    }

                                    updateSecurityLogStatusChips(payload.status_snapshot || {});
                                    updateRedisMonitorPanel(payload.status_snapshot || {});
                                    updateSecurityLogViewBadge('must-watch', Number(payload.must_watch_total || 0));
                                    updateSecurityLogViewBadge('live-roster', Number(payload.live_roster_total || 0));
                                })
                        );

                        if (activeSecurityLogView === 'history') {
                            pendingRequests.push(refreshSecurityHistoryRegion());
                        }

                        return Promise.all(pendingRequests)
                            .then(function () {
                                ensureSecurityLogViewLoaded(activeSecurityLogView);
                                syncDynamicFilterOptions();
                                if (activeSecurityLogView === 'must-watch') {
                                    applyMustWatchSort(activeWatchSortMode);
                                } else if (activeSecurityLogView === 'live-roster') {
                                    syncLiveRosterControlValues();
                                    applyLiveRosterFiltersAndPagination();
                                } else if (activeSecurityLogView === 'history') {
                                    applySecurityLogFilters();
                                }
                                setActiveSecurityLogView(activeSecurityLogView, { persist: false });
                                setLiveStatus(autoRefreshToggle.checked ? 'Auto refresh aktif setiap 10 detik.' : 'Auto refresh nonaktif.', '');
                                if (manualRefresh) {
                                    setRedisMonitorStatus('Redis monitor diperbarui.');
                                    completeSecurityProgress('Security Log diperbarui.', 'Snapshot observability, badge, dan area aktif sudah sinkron.', '');
                                }
                            })
                            .catch(function () {
                                setLiveStatus('Auto refresh gagal. Coba refresh halaman.', 'error');
                                if (manualRefresh) {
                                    setRedisMonitorStatus('Refresh monitor gagal.');
                                    completeSecurityProgress('Refresh Security Log gagal.', 'Snapshot observability gagal dimuat. Coba ulangi dari tombol refresh.', 'error');
                                }
                            })
                            .finally(function () {
                                refreshInFlight = false;
                                updateRedisMonitorActionButtons(currentSecurityStatusSnapshot || {});
                            });
                    }

                    function syncAutoRefreshState() {
                        stopAutoRefresh();
                        storeAutoRefreshPreference(autoRefreshToggle.checked);

                        if (!autoRefreshToggle.checked) {
                            setLiveStatus('Auto refresh nonaktif.', '');
                            return;
                        }

                        setLiveStatus('Auto refresh aktif setiap 10 detik.', '');
                        autoRefreshTimer = window.setInterval(function () {
                            refreshSecurityLogCard();
                        }, 10000);
                        refreshSecurityLogCard();
                    }

                    autoRefreshToggle.checked = readStoredAutoRefreshPreference();
                    activeWatchSortMode = readStoredWatchSortMode();
                    activeSecurityLogView = readStoredSecurityLogView();
                    syncDynamicFilterOptions();
                    syncWatchSortButtons();
                    updateSecurityLogStatusChips(currentSecurityStatusSnapshot || {});
                    updateRedisMonitorPanel(currentSecurityStatusSnapshot || {});
                    updateWatchFocusState();
                    applyMustWatchSort(activeWatchSortMode);
                    applySecurityLogFilters();
                    setActiveSecurityLogView(activeSecurityLogView, { persist: false });
                    syncAutoRefreshState();

                    severityFilter.addEventListener('change', applySecurityLogFilters);
                    eventFilter.addEventListener('change', applySecurityLogFilters);
                    deviceFilter.addEventListener('change', applySecurityLogFilters);
                    kelasFilter.addEventListener('change', applySecurityLogFilters);
                    ruangFilter.addEventListener('change', applySecurityLogFilters);
                    studentNameFilter.addEventListener('input', applySecurityLogFilters);
                    autoRefreshToggle.addEventListener('change', syncAutoRefreshState);

                    card.addEventListener('input', function (event) {
                        var target = event.target;

                        if (!target || typeof target.matches !== 'function') {
                            return;
                        }

                        if (target.matches('[data-security-log-roster-filter="search"]')) {
                            activeRosterFilters.search = normalizeRosterSearchValue(target.value || '');
                            activeRosterPage = 1;
                            applyLiveRosterFiltersAndPagination();
                        }
                    });

                    card.addEventListener('click', function (event) {
                        var target = event.target;
                        var monitorActionButton = null;
                        var viewButton = null;
                        var pageButton = null;

                        if (!target || typeof target.closest !== 'function') {
                            return;
                        }

                        monitorActionButton = target.closest('[data-security-log-monitor-action]');
                        if (monitorActionButton) {
                            var actionName = String(monitorActionButton.getAttribute('data-security-log-monitor-action') || '');
                            event.preventDefault();

                            if (actionName === 'refresh_monitor') {
                                refreshSecurityLogCard({
                                    force: true,
                                    statusMessage: 'Memuat Redis monitor...'
                                });
                                return;
                            }

                            if (actionName === 'copy_diagnostics') {
                                copySecurityDiagnostics()
                                    .then(function () {
                                        setRedisMonitorStatus('Diagnostics monitor berhasil disalin.');
                                        setLiveStatus('Diagnostics monitor berhasil disalin.', '');
                                    })
                                    .catch(function () {
                                        setRedisMonitorStatus('Gagal menyalin diagnostics monitor.');
                                        setLiveStatus('Gagal menyalin diagnostics monitor.', 'error');
                                    });
                                return;
                            }

                            if (actionName === 'micro_drain' || actionName === 'flush_now' || actionName === 'clear_live_state') {
                                triggerSecurityIngestAdminAction(actionName);
                            }
                            return;
                        }

                        viewButton = target.closest('[data-security-log-view-button]');
                        if (viewButton) {
                            event.preventDefault();
                            setActiveSecurityLogView(viewButton.getAttribute('data-security-log-view-button') || 'history');
                            if (activeSecurityLogView === 'history') {
                                setLiveStatus('Memuat histori log...', 'loading');
                                refreshSecurityHistoryRegion()
                                    .then(function () {
                                        setLiveStatus(autoRefreshToggle.checked ? 'Auto refresh aktif setiap 10 detik.' : 'Auto refresh nonaktif.', '');
                                    })
                                    .catch(function () {
                                        setLiveStatus('Histori log gagal dimuat.', 'error');
                                    });
                            }
                            return;
                        }

                        pageButton = target.closest('[data-security-log-roster-page-prev], [data-security-log-roster-page-next]');
                        if (!pageButton) {
                            return;
                        }

                        event.preventDefault();
                        if (pageButton.hasAttribute('data-security-log-roster-page-prev')) {
                            activeRosterPage = Math.max(1, activeRosterPage - 1);
                        } else {
                            activeRosterPage += 1;
                        }
                        applyLiveRosterFiltersAndPagination();
                    });

                    if (focusClearButton) {
                        focusClearButton.addEventListener('click', function () {
                            clearWatchFocus(true);
                        });
                    }

                    if (watchRegion) {
                        watchRegion.addEventListener('click', function (event) {
                            var target = event.target;
                            var focusCard = null;
                            var sortButton = null;

                            if (!target || typeof target.closest !== 'function') {
                                return;
                            }

                            sortButton = target.closest('[data-security-log-watch-sort]');
                            if (sortButton) {
                                event.preventDefault();
                                activeWatchSortMode = normalizeWatchSortMode(sortButton.getAttribute('data-security-log-watch-sort') || 'auto');
                                storeWatchSortMode(activeWatchSortMode);
                                applyMustWatchSort(activeWatchSortMode);
                                return;
                            }

                            if (target.closest('a, button, input, select, textarea, label, form')) {
                                return;
                            }

                            focusCard = target.closest('[data-security-log-focus-card]');
                            if (!focusCard) {
                                return;
                            }

                            focusLogsFromWatchCard(focusCard);
                        });
                    }

                    card.addEventListener('change', function (event) {
                        var target = event.target;
                        var visibleCheckboxes = [];
                        var index = 0;

                        if (!target || typeof target.matches !== 'function') {
                            return;
                        }

                        if (target.matches('[data-security-log-roster-filter="presence"]')) {
                            activeRosterFilters.presence = normalizeRosterPresenceValue(target.value || 'all');
                            activeRosterPage = 1;
                            applyLiveRosterFiltersAndPagination();
                            return;
                        }

                        if (target.matches('[data-security-log-roster-filter="risk"]')) {
                            activeRosterFilters.risk = normalizeRosterRiskValue(target.value || 'all');
                            activeRosterPage = 1;
                            applyLiveRosterFiltersAndPagination();
                            return;
                        }

                        if (target.matches('[data-security-log-roster-filter="exam"]')) {
                            activeRosterFilters.exam = normalizeRosterSelectValue(target.value || 'all');
                            activeRosterPage = 1;
                            applyLiveRosterFiltersAndPagination();
                            return;
                        }

                        if (target.matches('[data-security-log-roster-filter="kelas"]')) {
                            activeRosterFilters.kelas = normalizeRosterSelectValue(target.value || 'all');
                            activeRosterPage = 1;
                            applyLiveRosterFiltersAndPagination();
                            return;
                        }

                        if (target.matches('[data-security-log-roster-filter="ruang"]')) {
                            activeRosterFilters.ruang = normalizeRosterSelectValue(target.value || 'all');
                            activeRosterPage = 1;
                            applyLiveRosterFiltersAndPagination();
                            return;
                        }

                        if (target.matches('[data-security-log-select-all]')) {
                            visibleCheckboxes = getRowCheckboxes(true);

                            for (index = 0; index < visibleCheckboxes.length; index += 1) {
                                visibleCheckboxes[index].checked = !!target.checked;
                            }

                            updateBulkActionState();
                            return;
                        }

                        if (target.matches('[data-security-log-select]')) {
                            updateBulkActionState();
                        }
                    });

                    if (deleteSelectedButton && deleteScopeInput) {
                        deleteSelectedButton.addEventListener('click', function (event) {
                            var checkedCount = getCheckedRowCount();

                            deleteScopeInput.value = 'selected';

                            if (checkedCount <= 0) {
                                event.preventDefault();
                                return;
                            }

                            if (!window.confirm('Hapus ' + String(checkedCount) + ' security log yang dipilih?')) {
                                event.preventDefault();
                                deleteScopeInput.value = '';
                            }
                        });
                    }

                    if (deleteAllButton && deleteScopeInput) {
                        deleteAllButton.addEventListener('click', function (event) {
                            var rowCount = getCurrentRows().length;

                            deleteScopeInput.value = 'all';

                            if (rowCount <= 0) {
                                event.preventDefault();
                                return;
                            }

                            if (!window.confirm('Hapus semua histori security log? Tindakan ini tidak dapat dibatalkan.')) {
                                event.preventDefault();
                                deleteScopeInput.value = '';
                            }
                        });
                    }

                    document.addEventListener('visibilitychange', function () {
                        if (document.visibilityState === 'visible' && autoRefreshToggle.checked && isSecurityLogPanelActive()) {
                            refreshSecurityLogCard();
                        }
                    });
                }

                if (cbtAdminViewMode === 'security') {
                bindSetupTabs();
                bindSecurityAsyncForms();
                bindNativeCatalogTabs();
                bindNativeImplementationTabs();
                bindNativeSecurityTools();
                }
                bindLogoField({
                    inputId: 'cbt-setup-logo-1-attachment-id',
                    previewId: 'cbt-setup-logo-1-preview',
                    previewImageId: 'cbt-setup-logo-1-preview-image',
                    emptyId: 'cbt-setup-logo-1-empty',
                    pickButtonId: 'cbt-setup-logo-1-pick',
                    removeButtonId: 'cbt-setup-logo-1-remove',
                    mediaTitle: 'Pilih Logo 1 - Sekolah'
                });
                bindLogoField({
                    inputId: 'cbt-setup-logo-2-attachment-id',
                    previewId: 'cbt-setup-logo-2-preview',
                    previewImageId: 'cbt-setup-logo-2-preview-image',
                    emptyId: 'cbt-setup-logo-2-empty',
                    pickButtonId: 'cbt-setup-logo-2-pick',
                    removeButtonId: 'cbt-setup-logo-2-remove',
                    mediaTitle: 'Pilih Logo 2 - Dinas Pendidikan'
                });
                bindBrandingFormProgress();
                bindClearIdentityFields();
                bindSecurityLogTools();
            })();
        </script>
