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

            .cbt-users-page {
            max-width: 1280px;
            margin: 20px auto;
            padding: 24px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--cbt-text-main);
            background: radial-gradient(circle at top left, #e0e7ff 0%, #f8fafc 40%, #f0fdf4 100%);
            border-radius: var(--cbt-radius-lg);
            box-sizing: border-box;
        }
        .cbt-users-page * {
            box-sizing: border-box;
        }
            @keyframes cbtSlideUp {
                0% { opacity: 0; transform: translateY(15px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            
        .cbt-users-shell::before {
            content: ''; position: absolute; top: -150px; left: -100px; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-users-shell::after {
            content: ''; position: absolute; bottom: -100px; right: -50px; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-users-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            
            position: relative;
            z-index: 1;
            isolation: isolate;
        }
            .cbt-users-hero {
                position: relative;
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 24px;
                
                
                
                
                
            
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
            .cbt-users-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
        }
            .cbt-users-hero-copy {
                flex: 1;
                margin-right: 32px;
                position: relative;
                z-index: 2;
            }
            .cbt-users-kicker {
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
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
            }
            .cbt-users-hero h1 {
                margin: 12px 0 8px;
                font-size: 30px;
                line-height: 1.15;
            }
            .cbt-users-hero p {
                margin: 0;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-users-overview {
                position: relative;
                z-index: 2;
                display: grid;
                gap: 8px;
                min-width: 250px;
                padding: 20px;
                border: 1px solid rgba(255, 255, 255, 0.8);
                border-radius: 20px;
                background: rgba(255, 255, 255, 0.5);
                backdrop-filter: blur(20px);
                box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04), inset 0 0 0 1px rgba(255,255,255,0.4);
                flex-shrink: 0;
            }
            .cbt-users-pill {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 12px;
                background: rgba(255,255,255,0.8);
                border-radius: 8px;
                color: #0f172a;
                font-size: 13px;
                font-weight: 700;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }
            .cbt-users-tabs {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-users-tab {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 46px;
                padding: 0 20px;
                border: 2px solid #e2e8f0;
                border-radius: 14px;
                background: #ffffff;
                color: #64748b;
                font-size: 14px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .cbt-users-tab:hover,
            .cbt-users-tab:focus {
                border-color: #cbd5e1;
                background: #f8fafc;
                color: #0f172a;
                outline: none;
            }
            .cbt-users-tab.is-active {
                border-color: #3b82f6;
                background: #3b82f6;
                color: #ffffff;
                box-shadow: 0 8px 16px rgba(59, 130, 246, 0.25);
            }
            .cbt-users-panel {
                display: none;
                padding: 32px 36px;
                
                
                
                
            
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
            .cbt-users-panel.is-active {
                display: block;
            }
            .cbt-users-panel[data-cbt-users-panel="list"].is-loading {
                opacity: 0.72;
                transition: opacity 0.18s ease;
            }
            .cbt-users-progress {
                display: none;
                gap: 10px;
                padding: 14px 16px;
                border: 1px solid #bfdbfe;
                border-radius: 18px;
                background: linear-gradient(135deg, rgba(239, 246, 255, 0.97), rgba(240, 253, 250, 0.92));
                box-shadow: 0 14px 30px rgba(37, 99, 235, 0.12);
            }
            .cbt-users-progress.is-active {
                display: grid;
            }
            .cbt-users-progress.is-error {
                border-color: #fecaca;
                background: linear-gradient(135deg, rgba(254, 242, 242, 0.98), rgba(255, 247, 237, 0.94));
                box-shadow: 0 14px 30px rgba(239, 68, 68, 0.10);
            }
            .cbt-users-progress-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                flex-wrap: wrap;
            }
            .cbt-users-progress-title {
                display: grid;
                gap: 3px;
                min-width: 0;
            }
            .cbt-users-progress-title strong {
                color: #0f172a;
                font-size: 14px;
                line-height: 1.25;
            }
            .cbt-users-progress-title span,
            .cbt-users-progress-step {
                color: #52637a;
                font-size: 13px;
                line-height: 1.45;
            }
            .cbt-users-progress-percent {
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
                letter-spacing: 0.04em;
            }
            .cbt-users-progress.is-error .cbt-users-progress-percent {
                color: #b91c1c;
                border-color: #fecaca;
            }
            .cbt-users-progress-track {
                height: 9px;
                overflow: hidden;
                border-radius: 999px;
                background: rgba(148, 163, 184, 0.22);
            }
            .cbt-users-progress-fill {
                display: block;
                width: var(--cbt-users-progress, 0%);
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb 0%, #06b6d4 54%, #10b981 100%);
                transition: width 0.24s ease;
            }
            .cbt-users-progress.is-error .cbt-users-progress-fill {
                background: linear-gradient(90deg, #ef4444 0%, #f97316 100%);
            }
            .cbt-users-progress-step {
                margin: 0;
                font-weight: 600;
            }
            .cbt-users-panel .button.is-loading,
            .cbt-users-row-action.is-loading {
                pointer-events: none;
                opacity: 0.78;
            }
            .cbt-users-panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }
            .cbt-users-panel-header h2 {
                margin: 0 0 8px;
                font-size: 20px;
                font-weight: 800;
                color: #0f172a;
                line-height: 1.2;
            }
            .cbt-users-panel-header p {
                margin: 0;
                color: #64748b;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-users-chip {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 12px;
                border-radius: 999px;
                background: #e2e8f0;
                color: #0f172a;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .cbt-users-actions,
            .cbt-users-form-actions,
            .cbt-users-bulk-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-users-actions {
                margin: 14px 0 22px;
            }
            .cbt-users-actions .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                line-height: 1.2;
            }
            .cbt-users-form-actions {
                margin-top: 18px;
            }
            .cbt-users-bulk-actions {
                margin: 14px 0 4px;
                padding: 12px;
                border: 1px solid #e3ebf4;
                border-radius: 18px;
                background:
                    radial-gradient(circle at top right, rgba(220, 38, 38, 0.06), transparent 38%),
                    linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
            }
            .cbt-users-bulk-button.button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                min-height: 44px;
                padding: 0 18px 0 14px;
                border-radius: 14px;
                border-width: 1px;
                border-style: solid;
                font-size: 13px;
                font-weight: 700;
                line-height: 1;
                text-shadow: none;
                box-shadow:
                    0 14px 28px rgba(148, 28, 28, 0.08),
                    inset 0 1px 0 rgba(255, 255, 255, 0.92);
                transition:
                    transform 0.18s ease,
                    box-shadow 0.18s ease,
                    border-color 0.18s ease,
                    background 0.18s ease,
                    color 0.18s ease;
            }
            .cbt-users-bulk-button.button::before {
                content: "";
                width: 24px;
                height: 24px;
                border-radius: 999px;
                flex: 0 0 24px;
                background-repeat: no-repeat;
                background-position: center;
                background-size: 13px 13px;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14' fill='none'%3E%3Cpath d='M2.625 3.20833H11.375' stroke='%23B91C1C' stroke-width='1.25' stroke-linecap='round'/%3E%3Cpath d='M5.25 1.75H8.75' stroke='%23B91C1C' stroke-width='1.25' stroke-linecap='round'/%3E%3Cpath d='M4.08301 4.375V10.2083C4.08301 10.6726 4.45944 11.049 4.92371 11.049H9.07664C9.54091 11.049 9.91734 10.6726 9.91734 10.2083V4.375' stroke='%23B91C1C' stroke-width='1.25' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M5.83301 6.125V9.04167' stroke='%23B91C1C' stroke-width='1.25' stroke-linecap='round'/%3E%3Cpath d='M8.16699 6.125V9.04167' stroke='%23B91C1C' stroke-width='1.25' stroke-linecap='round'/%3E%3C/svg%3E");
            }
            .cbt-users-bulk-button.button:hover,
            .cbt-users-bulk-button.button:focus {
                transform: translateY(-1px);
                outline: none;
            }
            .cbt-users-bulk-button.button:focus-visible {
                box-shadow:
                    0 0 0 3px rgba(220, 38, 38, 0.14),
                    0 16px 32px rgba(148, 28, 28, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.94);
            }
            .cbt-users-bulk-button--selected.button {
                border-color: #efcaca;
                background: linear-gradient(180deg, #ffffff 0%, #fff5f5 100%);
                color: #b42318;
            }
            .cbt-users-bulk-button--selected.button::before {
                background-color: #fff1f1;
            }
            .cbt-users-bulk-button--selected.button:hover,
            .cbt-users-bulk-button--selected.button:focus {
                border-color: #e5a5a5;
                background: linear-gradient(180deg, #ffffff 0%, #ffefef 100%);
                color: #991b1b;
                box-shadow:
                    0 16px 30px rgba(185, 28, 28, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.96);
            }
            .cbt-users-bulk-button--all.button {
                border-color: #d98b8b;
                background: linear-gradient(180deg, #fff6f6 0%, #fee2e2 100%);
                color: #8f1111;
                box-shadow:
                    0 16px 32px rgba(153, 27, 27, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.9);
            }
            .cbt-users-bulk-button--all.button::before {
                background-color: rgba(185, 28, 28, 0.12);
            }
            .cbt-users-bulk-button--all.button:hover,
            .cbt-users-bulk-button--all.button:focus {
                border-color: #c75e5e;
                background: linear-gradient(180deg, #fff1f1 0%, #fecaca 100%);
                color: #7f1d1d;
                box-shadow:
                    0 18px 34px rgba(153, 27, 27, 0.16),
                    inset 0 1px 0 rgba(255, 255, 255, 0.92);
            }
            .cbt-users-panel .form-table {
                margin: 0;
                border-collapse: separate;
                border-spacing: 0 18px;
            }
            .cbt-users-panel .form-table th {
                width: 190px;
                padding: 10px 18px 0 0;
                vertical-align: top;
                color: #0f172a;
                font-size: 14px;
                font-weight: 700;
            }
            .cbt-users-panel .form-table td {
                padding: 0;
                vertical-align: top;
            }
            .cbt-users-panel .form-table th label,
            .cbt-users-panel .form-table td > label {
                color: inherit;
                font-weight: inherit;
            }
            .cbt-users-panel input[type="text"],
            .cbt-users-panel input[type="email"],
            .cbt-users-panel input[type="search"],
            .cbt-users-panel select,
            .cbt-users-panel textarea {
                width: 100%;
            }
            .cbt-users-panel input[type="text"],
            .cbt-users-panel input[type="email"],
            .cbt-users-panel input[type="search"],
            .cbt-users-panel select {
                height: 48px;
                border-radius: 12px;
                border: 2px solid #e2e8f0;
                padding: 0 16px;
                background: #f8fafc;
                color: #0f172a;
                font-size: 14px;
                transition: all 0.2s ease;
            }
            .cbt-users-panel input[type="text"]:focus,
            .cbt-users-panel input[type="email"]:focus,
            .cbt-users-panel input[type="search"]:focus,
            .cbt-users-panel select:focus {
                border-color: #3b82f6;
                background: #ffffff;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
                outline: none;
            }
            .cbt-users-panel select {
                background-position: right 16px center;
            }
            .cbt-users-panel select:disabled {
                background: #f1f5f9;
                color: #94a3b8;
                border-color: #cbd5e1;
            }
            .cbt-users-panel input[type="file"] {
                padding: 8px 0;
            }
            .cbt-users-panel .regular-text,
            .cbt-users-panel textarea {
                max-width: 480px;
            }
            .cbt-users-panel .description {
                margin: 6px 0 0;
                color: #6b7280;
            }
            .cbt-users-import-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.85fr) minmax(320px, 1fr);
                gap: 18px;
                align-items: start;
            }
            .cbt-users-import-card {
                padding: 24px 28px;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);
            }
            .cbt-users-import-card-label {
                display: block;
                margin-bottom: 10px;
                color: #0f172a;
                font-size: 14px;
                font-weight: 700;
            }
            .cbt-users-import-card .description {
                margin-top: 8px;
            }
            .cbt-users-import-card ul {
                margin: 0 0 0 18px;
                list-style: disc;
            }
            .cbt-users-code-block {
                display: block;
                max-width: 100%;
                margin-top: 6px;
                padding: 8px 10px;
                overflow-x: auto;
                white-space: nowrap;
                border-radius: 8px;
                box-sizing: border-box;
                line-height: 1.45;
            }
            .cbt-users-panel .button {
                border-radius: 12px;
                min-height: 44px;
                font-weight: 700;
                padding: 0 20px;
                transition: all 0.2s ease;
            }
            .cbt-users-panel .button-primary {
                background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
                border: none;
                color: #ffffff;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            }
            .cbt-users-panel .button-primary:hover,
            .cbt-users-panel .button-primary:focus {
                transform: translateY(-2px);
                box-shadow: 0 8px 16px rgba(59, 130, 246, 0.4);
                background: linear-gradient(180deg, #60a5fa 0%, #3b82f6 100%);
            }
            .cbt-users-import-progress {
                display: grid;
                gap: 10px;
                margin: 14px 0 18px;
                padding: 16px;
                border: 1px dashed #93c5fd;
                border-radius: 16px;
                background: #eff6ff;
            }
            .cbt-users-import-progress strong {
                font-size: 13px;
                color: #1e3a8a;
            }
            .cbt-users-import-progress-track {
                height: 8px;
                border-radius: 999px;
                background: rgba(37, 99, 235, 0.12);
                overflow: hidden;
            }
            .cbt-users-import-progress-fill {
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb, #1d4ed8);
            }
            .cbt-users-import-progress-meta {
                font-size: 12px;
                color: #1f2937;
            }
            .cbt-users-import-preview {
                display: grid;
                gap: 14px;
                margin: 14px 0 20px;
                padding: 16px;
                border: 1px solid #bfdbfe;
                border-radius: 16px;
                background: #f8fbff;
            }
            .cbt-users-import-preview h3 {
                margin: 0;
                font-size: 15px;
                font-weight: 800;
                color: #0f172a;
            }
            .cbt-users-import-preview-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 8px;
            }
            .cbt-users-import-preview-stat {
                display: grid;
                gap: 4px;
                padding: 10px 12px;
                border: 1px solid #dbe7f5;
                border-radius: 12px;
                background: #ffffff;
            }
            .cbt-users-import-preview-stat span {
                color: #64748b;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }
            .cbt-users-import-preview-stat strong {
                color: #0f172a;
                font-size: 20px;
                line-height: 1.1;
            }
            .cbt-users-import-preview-errors {
                margin: 0;
                padding: 10px 12px 10px 28px;
                border: 1px solid #fecaca;
                border-radius: 12px;
                background: #fff7f7;
                color: #7f1d1d;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-users-diagnostic {
                display: grid;
                gap: 14px;
                margin: 16px 0 18px;
                padding: 18px;
                border: 1px solid #bfd7f1;
                border-radius: 18px;
                background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
            }
            .cbt-users-diagnostic-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-users-diagnostic-head h3 {
                margin: 0 0 6px;
                color: #0f172a;
                font-size: 16px;
                font-weight: 800;
            }
            .cbt-users-diagnostic-head p {
                margin: 0;
                color: #64748b;
                font-size: 13px;
                line-height: 1.5;
            }
            .cbt-users-diagnostic-summary {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .cbt-users-diagnostic-pill {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 10px;
                border: 1px solid #dbe6f1;
                border-radius: 999px;
                background: #ffffff;
                color: #334155;
                font-size: 12px;
                font-weight: 700;
            }
            .cbt-users-diagnostic-table-wrap {
                overflow-x: auto;
            }
            .cbt-users-diagnostic-table {
                margin: 0;
                min-width: 860px;
            }
            .cbt-users-diagnostic-list {
                display: grid;
                gap: 10px;
            }
            .cbt-users-diagnostic-item {
                display: grid;
                gap: 10px;
                padding: 12px 14px;
                border: 1px solid #dbe7f5;
                border-radius: 14px;
                background: #ffffff;
                box-shadow: 0 4px 10px rgba(15, 23, 42, 0.03);
            }
            .cbt-users-diagnostic-item-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }
            .cbt-users-diagnostic-item-title {
                min-width: 0;
            }
            .cbt-users-diagnostic-item-title strong {
                display: block;
                color: #0f172a;
                font-size: 13px;
                line-height: 1.35;
            }
            .cbt-users-diagnostic-item-title span {
                color: #64748b;
                font-size: 12px;
            }
            .cbt-users-diagnostic-item-meta {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
                gap: 8px;
            }
            .cbt-users-diagnostic-field {
                display: grid;
                gap: 3px;
                min-width: 0;
            }
            .cbt-users-diagnostic-field span {
                color: #64748b;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }
            .cbt-users-diagnostic-field strong {
                color: #334155;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.45;
                word-break: normal;
                overflow-wrap: anywhere;
            }
            .cbt-users-diagnostic-tone {
                display: inline-flex;
                align-items: center;
                min-height: 24px;
                padding: 0 8px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .cbt-users-diagnostic-tone--success {
                background: #dcfce7;
                color: #166534;
            }
            .cbt-users-diagnostic-tone--warning {
                background: #fef3c7;
                color: #92400e;
            }
            .cbt-users-diagnostic-tone--danger {
                background: #fee2e2;
                color: #991b1b;
            }
            .cbt-users-diagnostic-tone--neutral {
                background: #e2e8f0;
                color: #334155;
            }
            .cbt-users-main-table tr.is-diagnosed > td {
                background: #f8fbff !important;
                border-top: 1px solid #bfdbfe;
                border-bottom: 1px solid #dbeafe;
            }
            .cbt-users-diagnostic-row > td {
                padding: 14px 12px 18px !important;
                border-top: 8px solid #edf2f7;
                background: #f8fafc !important;
                box-shadow: inset 0 1px 0 #dbeafe;
                white-space: normal !important;
                word-break: normal;
            }
            .cbt-users-diagnostic-row .cbt-users-diagnostic {
                margin: 0;
                border-top: 1px solid #bfd7f1;
                border-radius: 18px;
                box-shadow:
                    0 14px 28px rgba(15, 23, 42, 0.06),
                    inset 0 1px 0 rgba(255, 255, 255, 0.9);
                white-space: normal;
            }
            .cbt-users-main-table {
                min-width: 900px;
            }
            .cbt-users-main-table th:nth-child(1),
            .cbt-users-main-table td:nth-child(1),
            .cbt-users-main-table th:nth-child(2),
            .cbt-users-main-table td:nth-child(2),
            .cbt-users-main-table th:nth-child(5),
            .cbt-users-main-table td:nth-child(5),
            .cbt-users-main-table th:nth-child(7),
            .cbt-users-main-table td:nth-child(7),
            .cbt-users-main-table th:nth-child(8),
            .cbt-users-main-table td:nth-child(8) {
                white-space: nowrap;
                word-break: normal;
            }
            .cbt-users-main-table th:nth-child(3),
            .cbt-users-main-table td:nth-child(3) {
                min-width: 190px;
                word-break: normal;
                overflow-wrap: anywhere;
            }
            .cbt-users-main-table th:nth-child(5),
            .cbt-users-main-table td:nth-child(5) {
                min-width: 150px;
                word-break: normal;
            }
            .cbt-users-main-table th:nth-child(6),
            .cbt-users-main-table td:nth-child(6) {
                min-width: 120px;
            }
            .cbt-users-main-table th:nth-child(7),
            .cbt-users-main-table td:nth-child(7) {
                min-width: 150px;
            }
            .cbt-users-main-table th:nth-child(8),
            .cbt-users-main-table td:nth-child(8) {
                min-width: 132px;
            }
            .cbt-users-user-stack,
            .cbt-users-account-stack,
            .cbt-users-profile-stack {
                display: grid;
                gap: 3px;
                min-width: 0;
            }
            .cbt-users-user-stack strong,
            .cbt-users-account-stack strong,
            .cbt-users-profile-stack strong {
                color: #0f172a;
                font-size: 13px;
                line-height: 1.35;
            }
            .cbt-users-user-stack span,
            .cbt-users-account-stack span,
            .cbt-users-profile-stack span {
                color: #64748b;
                font-size: 12px;
                line-height: 1.35;
            }
            .cbt-users-profile-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                row-gap: 4px;
                align-items: start;
                max-width: 100%;
            }
            .cbt-users-profile-field {
                display: inline-grid;
                grid-template-columns: 42px minmax(0, 1fr);
                align-items: baseline;
                column-gap: 8px;
                min-width: 0;
                color: #64748b;
                font-size: 12px;
                line-height: 1.25;
                white-space: nowrap;
            }
            .cbt-users-profile-label {
                display: inline-flex;
                justify-content: space-between;
                color: #0f172a;
                font-weight: 700;
            }
            .cbt-users-profile-label::after {
                content: ":";
                color: #64748b;
                font-weight: 700;
            }
            .cbt-users-profile-value {
                color: #64748b;
                font-weight: 800;
            }
            .cbt-users-register-stack {
                display: grid;
                justify-items: center;
                gap: 6px;
                min-width: 86px;
            }
            .cbt-users-register-stack span {
                color: #475569;
                font-size: 11px;
                line-height: 1.35;
                text-align: center;
                white-space: normal;
            }
            .cbt-users-photo-placeholder {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 46px;
                height: 46px;
                border-radius: 12px;
                border: 1px dashed #cbd5e1;
                background: #f8fafc;
                color: #94a3b8;
                font-size: 12px;
                font-weight: 800;
            }
            .cbt-users-list-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 14px;
            }
            .cbt-users-filter-form {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            .cbt-users-filter-form input[type="search"] {
                min-width: 220px;
            }
            .cbt-users-filter-form label {
                font-weight: 600;
            }
            .cbt-users-filter-form select {
                min-width: 150px;
            }
            .cbt-users-filter-reset {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                border-radius: 999px;
                border: 1px solid #d7dbe2;
                text-decoration: none;
                color: #475569;
                font-size: 12px;
                font-weight: 600;
                background: #fff;
                transition: all 0.16s ease;
            }
            .cbt-users-filter-reset:hover,
            .cbt-users-filter-reset:focus {
                border-color: #1d4ed8;
                color: #1d4ed8;
                outline: none;
            }
            .cbt-users-photo-preview {
                margin-bottom: 10px;
            }
            .cbt-users-photo-preview img {
                width: 84px;
                height: 84px;
                object-fit: cover;
                border-radius: 18px;
                border: 1px solid #dbe1ea;
            }
            .cbt-users-table-photo {
                width: 46px;
                height: 46px;
                object-fit: cover;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
            }
            .cbt-users-table-wrap {
                overflow-x: auto;
            }
            .cbt-users-panel .widefat {
                border-radius: 16px;
                overflow: hidden;
                border: 1px solid #e2e8f0;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
            }
            .cbt-users-panel .widefat thead th {
                background: #f8fafc;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0;
                font-weight: 700;
                padding: 8px 6px;
                white-space: nowrap;
            }
            .cbt-users-panel .widefat td,
            .cbt-users-panel .widefat th {
                vertical-align: middle;
                padding: 6px 8px;
                font-size: 13px;
                word-break: break-word;
            }
            .cbt-users-panel .widefat tbody tr:hover {
                background: #f8fafc;
            }
            .cbt-users-row-actions {
                display: grid;
                gap: 6px;
                min-width: 124px;
            }
            .cbt-users-row-action {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                min-height: 30px;
                padding: 0 10px;
                border: 1px solid #d8e5f5;
                border-radius: 999px;
                background: #ffffff;
                color: #0f4fa8;
                font-size: 12px;
                font-weight: 800;
                line-height: 1;
                text-decoration: none;
                white-space: nowrap;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
                transition: background 0.16s ease, border-color 0.16s ease, color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
            }
            .cbt-users-row-action::before {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 16px;
                height: 16px;
                border-radius: 999px;
                font-family: dashicons;
                font-size: 14px;
                font-weight: 400;
                line-height: 1;
            }
            .cbt-users-row-action:hover,
            .cbt-users-row-action:focus {
                text-decoration: none;
                transform: translateY(-1px);
                box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
                outline: none;
            }
            .cbt-users-row-action--edit {
                border-color: #bfdbfe;
                background: #eff6ff;
                color: #0f4fa8;
            }
            .cbt-users-row-action--edit::before {
                content: "\f464";
                color: #1d4ed8;
            }
            .cbt-users-row-action--edit:hover,
            .cbt-users-row-action--edit:focus {
                border-color: #60a5fa;
                background: #dbeafe;
                color: #0f4fa8;
            }
            .cbt-users-row-action--diagnose {
                border-color: #c7d2fe;
                background: #eef2ff;
                color: #3730a3;
            }
            .cbt-users-row-action--diagnose::before {
                content: "\f348";
                color: #4f46e5;
            }
            .cbt-users-row-action--diagnose:hover,
            .cbt-users-row-action--diagnose:focus {
                border-color: #818cf8;
                background: #e0e7ff;
                color: #312e81;
            }
            .cbt-users-row-action--delete {
                border-color: #fecaca;
                background: #fff1f2;
                color: #b91c1c;
            }
            .cbt-users-row-action--delete::before {
                content: "\f182";
                color: #dc2626;
            }
            .cbt-users-row-action--delete:hover,
            .cbt-users-row-action--delete:focus {
                border-color: #fca5a5;
                background: #fee2e2;
                color: #991b1b;
            }
            .cbt-users-pagination-wrap {
                margin-top: 12px;
            }
            .cbt-users-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-users-pagination .cbt-users-total {
                font-weight: 600;
            }
            .cbt-users-pagination-links {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .cbt-users-pagination-links .page-numbers {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 34px;
                height: 34px;
                border-radius: 999px;
                border: 1px solid #d2d8e1;
                background: #fff;
                color: #334155;
                text-decoration: none;
                font-size: 13px;
                font-weight: 600;
                transition: all 0.16s ease;
            }
            .cbt-users-pagination-links .page-numbers:hover,
            .cbt-users-pagination-links .page-numbers:focus {
                border-color: #1d4ed8;
                color: #1d4ed8;
            }
            .cbt-users-pagination-links .page-numbers.current {
                border-color: #1d4ed8;
                background: #1d4ed8;
                color: #fff;
            }
            .cbt-users-pagination-links .page-numbers.prev,
            .cbt-users-pagination-links .page-numbers.next {
                padding: 0 12px;
            }
            .cbt-users-pagination-links .page-numbers.dots {
                border: none;
                background: transparent;
                min-width: auto;
            }

            @media (max-width: 960px) {
                .cbt-users-hero,
                .cbt-users-panel-header,
                .cbt-users-list-toolbar {
                    flex-direction: column;
                    align-items: flex-start;
                }
                .cbt-users-overview {
                    width: 100%;
                }
                .cbt-users-page {
                    max-width: 100%;
                }
                .cbt-users-hero,
                .cbt-users-panel {
                    padding: 20px;
                
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
                .cbt-users-import-grid {
                    grid-template-columns: 1fr;
                }
                .cbt-users-panel .form-table th {
                    width: 100%;
                }
                .cbt-users-filter-form input[type="search"] {
                    min-width: 100%;
                }
                .cbt-users-pagination-links .page-numbers {
                    min-width: 30px;
                }
            }
        </style>
        <div class="wrap cbt-users-page" data-cbt-users-default-tab="<?php echo esc_attr($default_user_tab); ?>" data-cbt-users-force-tab="<?php echo $user_tab_is_forced ? '1' : '0'; ?>" data-cbt-users-root>
            <div class="cbt-users-shell">
                <section class="cbt-users-hero">
                    <div class="cbt-users-hero-copy">
                        <span class="cbt-users-kicker">Users</span>
                        <h1>CBT Users</h1>
                        <p>Kelola user CBT secara lengkap: buat manual, import massal CSV/XLSX, dan kelola daftar user dengan filter cepat.</p>
                    </div>
                    <div class="cbt-users-overview" data-cbt-users-refresh-area="overview" aria-hidden="true">
                        <span class="cbt-users-pill"><?php echo esc_html(sprintf('Total: %d user', $total_users)); ?></span>
                        <span class="cbt-users-pill"><?php echo esc_html($is_editing_user ? 'Mode edit aktif' : 'Tambah manual siap'); ?></span>
                        <span class="cbt-users-pill"><?php echo esc_html(is_array($import_state) ? 'Import berjalan' : 'Import siap'); ?></span>
                    </div>
                </section>

                <div class="cbt-users-notices" data-cbt-users-refresh-area="notices">
                    <?php if ($notice): ?>
                        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                    <?php endif; ?>
                </div>

                <div class="cbt-users-progress" data-cbt-users-progress role="status" aria-live="polite" aria-hidden="true">
                    <div class="cbt-users-progress-head">
                        <div class="cbt-users-progress-title">
                            <strong data-cbt-users-progress-label>Menunggu aksi CBT Users...</strong>
                            <span>Progress ini memperbarui panel Users yang terdampak saja, tanpa reload halaman global.</span>
                        </div>
                        <span class="cbt-users-progress-percent" data-cbt-users-progress-percent>0%</span>
                    </div>
                    <div class="cbt-users-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-cbt-users-progress-track>
                        <span class="cbt-users-progress-fill" data-cbt-users-progress-fill></span>
                    </div>
                    <p class="cbt-users-progress-step" data-cbt-users-progress-step>Siap memproses perubahan user.</p>
                </div>

                <div class="cbt-users-tabs" role="tablist" aria-label="Navigasi CBT Users">
                    <button type="button" class="cbt-users-tab" data-cbt-users-tab="form" role="tab" aria-selected="false">Form User</button>
                    <button type="button" class="cbt-users-tab" data-cbt-users-tab="import" role="tab" aria-selected="false">Import Users</button>
                    <button type="button" class="cbt-users-tab" data-cbt-users-tab="list" role="tab" aria-selected="false">Daftar Users</button>
                </div>

                <section class="cbt-users-panel" data-cbt-users-panel="form" data-cbt-users-refresh-area="form-panel" role="tabpanel">
                    <div class="cbt-users-panel-header">
                        <div>
                            <h2><?php echo $is_editing_user ? 'Edit User' : 'Tambah User Manual'; ?></h2>
                            <p><?php echo $is_editing_user ? 'Perbarui identitas, role, kelas, ruang, jenis kelamin, dan foto user tanpa pindah ke area daftar.' : 'Buat user baru secara manual untuk kebutuhan cepat tanpa harus upload file import.'; ?></p>
                        </div>
                        <?php if ($is_editing_user): ?>
                            <a href="<?php echo esc_url($user_clear_edit_url); ?>" class="button button-secondary" data-cbt-users-tab-link="list" data-cbt-users-async-link data-cbt-users-progress-profile="save" data-cbt-users-refresh-areas="notices,overview,form-panel,list-panel" data-cbt-users-success-tab="list">Batal Edit</a>
                        <?php else: ?>
                            <span class="cbt-users-chip">Manual</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_editing_user): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-users-tab-submit="form" data-cbt-users-async-form data-cbt-users-progress-profile="save" data-cbt-users-refresh-areas="notices,overview,form-panel,list-panel" data-cbt-users-success-tab="list">
                            <?php wp_nonce_field('cbt_update_user_manual'); ?>
                            <input type="hidden" name="action" value="cbt_update_user_manual" />
                            <input type="hidden" name="user_id" value="<?php echo (int) $editing_user->ID; ?>" />

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th><label for="cbt-edit-user-name">Nama</label></th>
                                    <td><input required type="text" id="cbt-edit-user-name" name="name" class="regular-text" value="<?php echo esc_attr((string) $editing_user->display_name); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-email">Email</label></th>
                                    <td><input required type="email" id="cbt-edit-user-email" name="email" class="regular-text" value="<?php echo esc_attr((string) $editing_user->user_email); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-nisn">NISN</label></th>
                                    <td>
                                        <input type="text" id="cbt-edit-user-nisn" name="nisn" class="regular-text" inputmode="numeric" pattern="[0-9]*" value="<?php echo esc_attr($editing_nisn); ?>" />
                                        <p class="description">Wajib untuk role siswa. Hanya angka dan tidak boleh sama dengan siswa lain.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-username">Username</label></th>
                                    <td><input required type="text" id="cbt-edit-user-username" name="username" class="regular-text" value="<?php echo esc_attr((string) $editing_user->user_login); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-password">Password Baru</label></th>
                                    <td>
                                        <input type="text" id="cbt-edit-user-password" name="password" class="regular-text" />
                                        <p class="description">Kosongkan jika password tidak diubah.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-role">Role</label></th>
                                    <td>
                                        <select id="cbt-edit-user-role" name="role">
                                            <option value="siswa" <?php selected($editing_role, 'siswa'); ?>>siswa</option>
                                            <option value="guru" <?php selected($editing_role, 'guru'); ?>>guru</option>
                                            <?php if ($is_admin_scope): ?>
                                                <option value="admin" <?php selected($editing_role, 'admin'); ?>>admin</option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-kelas">Kode Kelas</label></th>
                                    <td><input type="text" id="cbt-edit-user-kelas" name="kode_kelas" class="regular-text" value="<?php echo esc_attr($editing_kelas); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-ruang">Kode Ruang</label></th>
                                    <td><input type="text" id="cbt-edit-user-ruang" name="kode_ruang" class="regular-text" value="<?php echo esc_attr($editing_ruang); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-agama">Agama</label></th>
                                    <td>
                                        <select id="cbt-edit-user-agama" name="agama" class="regular-text">
                                            <option value="">Pilih Agama</option>
                                            <?php foreach ($agama_options as $agama_option): ?>
                                                <option value="<?php echo esc_attr($agama_option); ?>" <?php selected($editing_agama_form, $agama_option); ?>><?php echo esc_html($agama_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-jenis-kelamin">Jenis Kelamin</label></th>
                                    <td>
                                        <select id="cbt-edit-user-jenis-kelamin" name="jenis_kelamin" class="regular-text">
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <?php foreach ($jenis_kelamin_options as $jenis_kelamin_option): ?>
                                                <option value="<?php echo esc_attr($jenis_kelamin_option); ?>" <?php selected($editing_jenis_kelamin_form, $jenis_kelamin_option); ?>><?php echo esc_html($jenis_kelamin_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Wajib untuk role siswa. Boleh dikosongkan untuk guru atau admin.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Mapel Pilihan</th>
                                    <td>
                                        <?php for ($choice_index = 1; $choice_index <= 3; $choice_index++): ?>
                                            <?php $selected_choice_id = (int) ($editing_subject_choice_ids[$choice_index - 1] ?? 0); ?>
                                            <label for="cbt-edit-user-subject-choice-<?php echo (int) $choice_index; ?>" style="display:block; margin:0 0 8px;">
                                                Mapel Pilihan <?php echo (int) $choice_index; ?>
                                                <select id="cbt-edit-user-subject-choice-<?php echo (int) $choice_index; ?>" name="subject_choices[]" class="regular-text">
                                                    <option value="">- Kosong -</option>
                                                    <?php foreach ((array) $subject_options as $subject_option): ?>
                                                        <option value="<?php echo (int) $subject_option['id']; ?>" <?php selected($selected_choice_id, (int) $subject_option['id']); ?>>
                                                            <?php echo esc_html((string) ($subject_option['label'] ?? '')); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                        <?php endfor; ?>
                                        <p class="description">Khusus siswa. Maksimal 3 mapel dan tidak boleh duplikat.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-foto-file">Foto</label></th>
                                    <td>
                                        <?php if ($editing_foto !== ''): ?>
                                            <div class="cbt-users-photo-preview">
                                                <a href="<?php echo esc_url($editing_foto); ?>" target="_blank" rel="noopener noreferrer">
                                                    <img src="<?php echo esc_url($editing_foto); ?>" alt="<?php echo esc_attr((string) $editing_user->display_name); ?>" />
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" id="cbt-edit-user-foto-file" name="foto_file" accept="image/*" />
                                        <p class="description">Pilih file baru jika ingin mengganti foto.</p>
                                        <?php if ($editing_foto !== ''): ?>
                                            <label>
                                                <input type="checkbox" name="hapus_foto" value="1" />
                                                Hapus foto saat update.
                                            </label>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <div class="cbt-users-form-actions">
                                <?php echo get_submit_button('Update User', 'primary', 'submit', false); ?>
                                <a href="<?php echo esc_url($user_clear_edit_url); ?>" class="button button-secondary" data-cbt-users-tab-link="list" data-cbt-users-async-link data-cbt-users-progress-profile="save" data-cbt-users-refresh-areas="notices,overview,form-panel,list-panel" data-cbt-users-success-tab="list">Batal Edit</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-users-tab-submit="form" data-cbt-users-async-form data-cbt-users-progress-profile="save" data-cbt-users-refresh-areas="notices,overview,form-panel,list-panel" data-cbt-users-success-tab="list">
                            <?php wp_nonce_field('cbt_create_user_manual'); ?>
                            <input type="hidden" name="action" value="cbt_create_user_manual" />

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th><label for="cbt-user-name">Nama</label></th>
                                    <td><input required type="text" id="cbt-user-name" name="name" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-email">Email</label></th>
                                    <td><input required type="email" id="cbt-user-email" name="email" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-nisn">NISN</label></th>
                                    <td>
                                        <input type="text" id="cbt-user-nisn" name="nisn" class="regular-text" inputmode="numeric" pattern="[0-9]*" />
                                        <p class="description">Wajib untuk role siswa. Hanya angka dan tidak boleh sama dengan siswa lain.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-username">Username</label></th>
                                    <td><input required type="text" id="cbt-user-username" name="username" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-password">Password</label></th>
                                    <td>
                                        <input type="text" id="cbt-user-password" name="password" class="regular-text" />
                                        <p class="description">Kosongkan untuk generate password otomatis.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-role">Role</label></th>
                                    <td>
                                        <select id="cbt-user-role" name="role">
                                            <option value="siswa">siswa</option>
                                            <option value="guru">guru</option>
                                            <?php if ($is_admin_scope): ?>
                                                <option value="admin">admin</option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-kelas">Kode Kelas</label></th>
                                    <td><input type="text" id="cbt-user-kelas" name="kode_kelas" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-ruang">Kode Ruang</label></th>
                                    <td><input type="text" id="cbt-user-ruang" name="kode_ruang" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-agama">Agama</label></th>
                                    <td>
                                        <select id="cbt-user-agama" name="agama" class="regular-text">
                                            <option value="">Pilih Agama</option>
                                            <?php foreach ($agama_options as $agama_option): ?>
                                                <option value="<?php echo esc_attr($agama_option); ?>"><?php echo esc_html($agama_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-jenis-kelamin">Jenis Kelamin</label></th>
                                    <td>
                                        <select id="cbt-user-jenis-kelamin" name="jenis_kelamin" class="regular-text">
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <?php foreach ($jenis_kelamin_options as $jenis_kelamin_option): ?>
                                                <option value="<?php echo esc_attr($jenis_kelamin_option); ?>"><?php echo esc_html($jenis_kelamin_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Wajib untuk role siswa. Boleh dikosongkan untuk guru atau admin.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Mapel Pilihan</th>
                                    <td>
                                        <?php for ($choice_index = 1; $choice_index <= 3; $choice_index++): ?>
                                            <label for="cbt-user-subject-choice-<?php echo (int) $choice_index; ?>" style="display:block; margin:0 0 8px;">
                                                Mapel Pilihan <?php echo (int) $choice_index; ?>
                                                <select id="cbt-user-subject-choice-<?php echo (int) $choice_index; ?>" name="subject_choices[]" class="regular-text">
                                                    <option value="">- Kosong -</option>
                                                    <?php foreach ((array) $subject_options as $subject_option): ?>
                                                        <option value="<?php echo (int) $subject_option['id']; ?>">
                                                            <?php echo esc_html((string) ($subject_option['label'] ?? '')); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                        <?php endfor; ?>
                                        <p class="description">Khusus siswa. Maksimal 3 mapel dan tidak boleh duplikat.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-foto-file">Foto</label></th>
                                    <td>
                                        <input type="file" id="cbt-user-foto-file" name="foto_file" accept="image/*" />
                                        <p class="description">Pilih file foto profil user (opsional).</p>
                                    </td>
                                </tr>
                            </table>

                            <div class="cbt-users-form-actions">
                                <?php echo get_submit_button('Simpan User', 'primary', 'submit', false); ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="cbt-users-panel" data-cbt-users-panel="import" data-cbt-users-refresh-area="import-panel" role="tabpanel">
                    <div class="cbt-users-panel-header">
                        <div>
                            <h2>Import Users</h2>
                            <p>Upload file CSV atau XLSX untuk menambahkan atau memperbarui banyak user sekaligus dengan proses batch otomatis.</p>
                        </div>
                        <span class="cbt-users-chip">CSV / XLSX</span>
                    </div>

                    <?php if (is_array($import_state)): ?>
                        <div
                            class="cbt-users-import-progress"
                            data-cbt-users-import-progress
                            data-cbt-users-import-running="<?php echo $import_is_running ? '1' : '0'; ?>"
                            data-cbt-users-import-continue-url="<?php echo esc_url($import_continue_url); ?>"
                            data-cbt-users-progress-profile="import"
                            data-cbt-users-refresh-areas="notices,overview,import-panel,list-panel"
                            data-cbt-users-success-tab="import"
                        >
                            <strong>
                                Progress Import User:
                                <?php echo esc_html((string) $import_offset . ' / ' . (string) $import_total); ?>
                                (<?php echo esc_html(number_format($import_progress_percent, 2)); ?>%)
                            </strong>
                            <div class="cbt-users-import-progress-track" aria-hidden="true">
                                <div class="cbt-users-import-progress-fill" style="width: <?php echo esc_attr((string) $import_progress_percent); ?>%;"></div>
                            </div>
                            <div class="cbt-users-import-progress-meta">
                                Created: <?php echo esc_html((string) $import_created); ?> |
                                Updated: <?php echo esc_html((string) $import_updated); ?> |
                                Failed: <?php echo esc_html((string) $import_failed); ?>
                                <br />
                                <?php if ($import_is_running): ?>
                                    Memproses batch user berikutnya...
                                <?php else: ?>
                                    <span style="color:#0a7a2f; font-weight:600;">Import user selesai diproses.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (is_array($import_preview_state) && !empty($import_preview)): ?>
                        <div class="cbt-users-import-preview">
                            <h3>Preview Import Users</h3>
                            <div class="cbt-users-import-preview-grid">
                                <div class="cbt-users-import-preview-stat"><span>Total Baris</span><strong><?php echo esc_html((string) ($import_preview['total'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Calon Create</span><strong><?php echo esc_html((string) ($import_preview['created'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Calon Update</span><strong><?php echo esc_html((string) ($import_preview['updated'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Gagal</span><strong><?php echo esc_html((string) ($import_preview['failed'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Baris Mapel</span><strong><?php echo esc_html((string) ($import_preview['subject_choice_rows'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Foto Perlu ZIP</span><strong><?php echo esc_html((string) ($import_preview['photo_required'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Foto Hilang</span><strong><?php echo esc_html((string) ($import_preview['photo_missing'] ?? 0)); ?></strong></div>
                            </div>
                            <?php $import_preview_errors = isset($import_preview['errors']) && is_array($import_preview['errors']) ? $import_preview['errors'] : []; ?>
                            <?php if (!empty($import_preview_errors)): ?>
                                <ul class="cbt-users-import-preview-errors">
                                    <?php foreach ($import_preview_errors as $preview_error): ?>
                                        <li><?php echo esc_html((string) $preview_error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="cbt-users-form-actions">
                                <?php if (!empty($import_preview['can_continue'])): ?>
                                    <form method="post" action="<?php echo esc_url($import_preview_run_url); ?>" style="margin:0;" data-cbt-users-async-form data-cbt-users-progress-profile="import" data-cbt-users-refresh-areas="notices,overview,import-panel,list-panel" data-cbt-users-success-tab="import">
                                        <?php wp_nonce_field('cbt_run_previewed_import_users'); ?>
                                        <input type="hidden" name="action" value="cbt_run_previewed_import_users" />
                                        <input type="hidden" name="cbt_import_preview_token" value="<?php echo esc_attr($import_preview_token); ?>" />
                                        <?php echo get_submit_button('Lanjut Import', 'primary', 'submit', false); ?>
                                    </form>
                                <?php endif; ?>
                                <a class="button button-secondary" href="<?php echo esc_url($import_preview_clear_url); ?>" data-cbt-users-async-link data-cbt-users-progress-profile="import" data-cbt-users-refresh-areas="notices,overview,import-panel" data-cbt-users-success-tab="import">Upload Ulang</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="cbt-users-actions">
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_user_template'), 'cbt_download_user_template')); ?>">
                            Download Template CSV
                        </a>
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_user_template_xlsx'), 'cbt_download_user_template_xlsx')); ?>">
                            Download Template XLSX
                        </a>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-users-tab-submit="import" data-cbt-users-async-form data-cbt-users-progress-profile="import" data-cbt-users-refresh-areas="notices,overview,import-panel,list-panel" data-cbt-users-success-tab="import">
                        <?php wp_nonce_field('cbt_preview_import_users'); ?>
                        <input type="hidden" name="action" value="cbt_preview_import_users" />

                        <div class="cbt-users-import-grid">
                            <div class="cbt-users-import-card">
                                <label class="cbt-users-import-card-label" for="cbt-user-file">File Import</label>
                                <input required type="file" id="cbt-user-file" name="user_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                                <div class="description">
                                    <ul>
                                        <li>
                                            Header template terbaru:
                                            <code class="cbt-users-code-block">name,email,nisn,username,password,role,kode_kelas,kode_ruang,agama,jenis_kelamin,foto_file,mapel_pilihan_1,mapel_pilihan_2,mapel_pilihan_3</code>
                                        </li>
                                        <li>Jika <code>mapel_pilihan_1</code> sampai <code>mapel_pilihan_3</code> diisi pada baris siswa, Import Users langsung ikut menyimpan mapel pilihan siswa tersebut.</li>
                                        <li>Jika ketiga kolom mapel kosong, Import Users tidak mengubah mapel pilihan lama. Gunakan bagian <strong>Import Mapel Pilihan</strong> di bawah untuk mode replace atau mengosongkan pilihan secara massal.</li>
                                        <li>Template XLSX juga berisi sheet <code>mapel_pilihan</code> dan <code>referensi_mapel</code>. Sheet <code>referensi_mapel</code> membantu operator melihat kode/nama mapel yang valid dari CBT Subjects.</li>
                                        <li>Role yang didukung: <code>admin</code>, <code>guru</code>, <code>siswa</code> dan juga kompatibel dengan <code>teacher</code>, <code>student</code>.</li>
                                        <li><code>username</code> dan <code>email</code> tidak boleh duplikat antarbaris dalam file import yang sama.</li>
                                        <li>Untuk baris <code>siswa</code>, <code>nisn</code> wajib diisi dan tidak boleh duplikat dengan siswa lain maupun antarbaris file import.</li>
                                        <li>Untuk <code>guru</code>/<code>admin</code>, <code>nisn</code> boleh kosong.</li>
                                        <li>Kolom opsional per baris: <code>email</code>, <code>kode_kelas</code>, <code>kode_ruang</code>, <code>agama</code>, dan <code>foto_file</code>. Kolom <code>jenis_kelamin</code> wajib untuk <code>siswa</code>, tetapi boleh kosong untuk <code>guru</code>/<code>admin</code>.</li>
                                        <li>Kolom <code>jenis_kelamin</code> menerima nilai seperti <code>Laki-laki</code>, <code>Perempuan</code>, <code>L</code>, atau <code>P</code>. Nilai lain akan ditolak saat import.</li>
                                        <li>Jika <code>foto_file</code> kosong untuk user <code>siswa</code>, sistem otomatis memakai <code>Default Pria.png</code> atau <code>Default Wanita.png</code> sesuai <code>jenis_kelamin</code>.</li>
                                        <li>Jika <code>email</code> kosong atau tidak valid tetapi <code>nisn</code> ada, sistem otomatis membuat email <code>nisn@student.sch.id</code>.</li>
                                        <li>Format file yang didukung: <code>.csv</code> dan <code>.xlsx</code>. Untuk CSV, delimiter koma atau titik-koma sama-sama didukung.</li>
                                        <li>Gambar yang ditempel langsung di Excel tidak dibaca. Untuk foto massal, isi kolom <code>foto_file</code> lalu upload file <code>.zip</code> yang berisi foto-foto tersebut.</li>
                                        <li>Jika semua kolom <code>foto_file</code> kosong, ZIP foto tidak wajib diupload.</li>
                                        <li>Import data besar diproses bertahap otomatis, batch <?php echo (int) $import_batch_size; ?> user per putaran, untuk mencegah timeout.</li>
                                        <li>Progress import akan tampil otomatis: jumlah diproses, persentase, <code>created</code>, <code>updated</code>, dan <code>failed</code>.</li>
                                        <li>Untuk lebih dari 500 user, disarankan memakai <code>.csv</code> karena parsing biasanya lebih cepat.</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="cbt-users-import-card">
                                <label class="cbt-users-import-card-label" for="cbt-user-photo-zip">ZIP Foto</label>
                                <input type="file" id="cbt-user-photo-zip" name="user_photo_zip" accept=".zip,application/zip,application/x-zip-compressed" />
                                <div class="description">
                                    <ul>
                                        <li>Opsional. Upload hanya jika ada baris yang mengisi <code>foto_file</code>.</li>
                                        <li>Nama file di ZIP harus sama persis dengan nilai pada kolom <code>foto_file</code>, misalnya <code>1000000001.jpg</code>.</li>
                                        <li>ZIP hanya boleh berisi file gambar <code>jpg</code>, <code>jpeg</code>, <code>png</code>, <code>gif</code>, atau <code>webp</code>.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="cbt-users-form-actions">
                            <?php echo get_submit_button('Preview Import', 'primary', 'submit', false); ?>
                        </div>
                    </form>

                    <hr style="margin:28px 0;" />

                    <div class="cbt-users-panel-header" style="margin-bottom:14px;">
                        <div>
                            <h2>Import Mapel Pilihan</h2>
                            <p>Upload file terpisah untuk mengganti Mapel Pilihan 1-3 siswa. Identifikasi memakai NISN, lalu fallback ke username.</p>
                        </div>
                        <span class="cbt-users-chip">Replace</span>
                    </div>

                    <div class="cbt-users-actions">
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_student_subject_choices_template'), 'cbt_download_student_subject_choices_template')); ?>">
                            Template Mapel CSV
                        </a>
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_student_subject_choices_template_xlsx'), 'cbt_download_student_subject_choices_template_xlsx')); ?>">
                            Template Mapel XLSX
                        </a>
                    </div>

                    <?php if (is_array($subject_choice_preview_state) && !empty($subject_choice_preview)): ?>
                        <div class="cbt-users-import-preview">
                            <h3>Preview Import Mapel Pilihan</h3>
                            <div class="cbt-users-import-preview-grid">
                                <div class="cbt-users-import-preview-stat"><span>Total Baris</span><strong><?php echo esc_html((string) ($subject_choice_preview['total'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Update</span><strong><?php echo esc_html((string) ($subject_choice_preview['updated'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Kosongkan</span><strong><?php echo esc_html((string) ($subject_choice_preview['cleared'] ?? 0)); ?></strong></div>
                                <div class="cbt-users-import-preview-stat"><span>Gagal</span><strong><?php echo esc_html((string) ($subject_choice_preview['failed'] ?? 0)); ?></strong></div>
                            </div>
                            <?php $subject_choice_preview_errors = isset($subject_choice_preview['errors']) && is_array($subject_choice_preview['errors']) ? $subject_choice_preview['errors'] : []; ?>
                            <?php if (!empty($subject_choice_preview_errors)): ?>
                                <ul class="cbt-users-import-preview-errors">
                                    <?php foreach ($subject_choice_preview_errors as $preview_error): ?>
                                        <li><?php echo esc_html((string) $preview_error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <div class="cbt-users-form-actions">
                                <?php if (!empty($subject_choice_preview['can_continue'])): ?>
                                    <form method="post" action="<?php echo esc_url($subject_choice_preview_run_url); ?>" style="margin:0;" data-cbt-users-async-form data-cbt-users-progress-profile="subject-choice" data-cbt-users-refresh-areas="notices,overview,import-panel,list-panel" data-cbt-users-success-tab="import">
                                        <?php wp_nonce_field('cbt_run_previewed_student_subject_choices'); ?>
                                        <input type="hidden" name="action" value="cbt_run_previewed_student_subject_choices" />
                                        <input type="hidden" name="cbt_subject_choice_preview_token" value="<?php echo esc_attr($subject_choice_preview_token); ?>" />
                                        <?php echo get_submit_button('Lanjut Import Mapel', 'primary', 'submit', false); ?>
                                    </form>
                                <?php endif; ?>
                                <a class="button button-secondary" href="<?php echo esc_url($subject_choice_preview_clear_url); ?>" data-cbt-users-async-link data-cbt-users-progress-profile="subject-choice" data-cbt-users-refresh-areas="notices,overview,import-panel" data-cbt-users-success-tab="import">Upload Ulang</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-users-tab-submit="import" data-cbt-users-async-form data-cbt-users-progress-profile="subject-choice" data-cbt-users-refresh-areas="notices,overview,import-panel,list-panel" data-cbt-users-success-tab="import" style="margin-top:14px;">
                        <?php wp_nonce_field('cbt_preview_import_student_subject_choices'); ?>
                        <input type="hidden" name="action" value="cbt_preview_import_student_subject_choices" />
                        <div class="cbt-users-import-card">
                            <label class="cbt-users-import-card-label" for="cbt-subject-choice-file">File Mapel Pilihan</label>
                            <input required type="file" id="cbt-subject-choice-file" name="subject_choice_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                            <div class="description">
                                <ul>
                                    <li>Header: <code>nisn,username,mapel_pilihan_1,mapel_pilihan_2,mapel_pilihan_3</code>.</li>
                                    <li>Bisa memakai file dari <strong>Download Template XLSX</strong> utama; sistem akan membaca sheet <code>mapel_pilihan</code>. Lihat sheet <code>referensi_mapel</code> untuk kode/nama mapel yang valid.</li>
                                    <li>Identifikasi siswa memakai <code>nisn</code> sebagai prioritas. Jika <code>nisn</code> kosong atau tidak ditemukan, sistem mencoba <code>username</code>.</li>
                                    <li>Mapel dicocokkan ke CBT Subjects dari <code>id</code> jika angka, lalu <code>code</code>, lalu <code>name</code>. Pencocokan kode/nama tidak membedakan huruf besar-kecil.</li>
                                    <li>Nilai mapel harus sudah ada di menu <strong>CBT Subjects</strong>. Import tidak membuat mapel baru otomatis.</li>
                                    <li>Maksimal 3 mapel per siswa dan tidak boleh duplikat dalam satu baris.</li>
                                    <li>Mode import selalu <code>replace</code>; tiga kolom mapel kosong akan mengosongkan pilihan siswa.</li>
                                    <li>Jika satu baris berisi mapel tidak dikenal, mapel duplikat, user tidak ditemukan, atau user bukan siswa, hanya baris itu yang gagal. Baris lain tetap diproses.</li>
                                    <li>Jika baris gagal, pilihan mapel lama siswa pada baris tersebut tidak ditimpa sebagian.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="cbt-users-form-actions">
                            <?php echo get_submit_button('Preview Mapel Pilihan', 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </section>

                <section class="cbt-users-panel" data-cbt-users-panel="list" data-cbt-users-refresh-area="list-panel" role="tabpanel">
                    <div class="cbt-users-panel-header">
                        <div>
                            <h2>Daftar User CBT</h2>
                            <p>Filter user berdasarkan kata kunci, role, kelas, ruang, agama, dan jenis kelamin, lalu lakukan edit atau bulk delete dari panel khusus daftar.</p>
                        </div>
                        <span class="cbt-users-chip"><?php echo esc_html(sprintf('%d total', $total_users)); ?></span>
                    </div>

                    <div class="cbt-users-list-toolbar">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-users-filter-form" data-cbt-users-tab-submit="list" data-cbt-users-progress-profile="list">
                            <input type="hidden" name="page" value="cbt-user-import" />
                            <input type="hidden" name="cbt_user_paged" value="1" />
                            <input type="search" id="cbt-users-filter-search" name="cbt_user_q" value="<?php echo esc_attr($search); ?>" placeholder="Cari username / nama / email" />
                            <select id="cbt-users-filter-role" name="cbt_user_role">
                                <option value="">Semua Role</option>
                                <option value="admin" <?php selected($filter_role, 'admin'); ?>>admin</option>
                                <option value="guru" <?php selected($filter_role, 'guru'); ?>>guru</option>
                                <option value="siswa" <?php selected($filter_role, 'siswa'); ?>>siswa</option>
                            </select>
                            <select id="cbt-users-filter-kelas" name="cbt_user_kelas">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($kelas_options as $kelas_option): ?>
                                    <option value="<?php echo esc_attr($kelas_option); ?>" <?php selected($filter_kelas, $kelas_option); ?>>
                                        <?php echo esc_html($kelas_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="cbt-users-filter-ruang" name="cbt_user_ruang">
                                <option value="">Semua Ruang</option>
                                <?php foreach ($ruang_options as $ruang_option): ?>
                                    <option value="<?php echo esc_attr($ruang_option); ?>" <?php selected($filter_ruang, $ruang_option); ?>>
                                        <?php echo esc_html($ruang_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="cbt-users-filter-agama" name="cbt_user_agama">
                                <option value="">Semua Agama</option>
                                <?php foreach ($agama_options as $agama_option): ?>
                                    <option value="<?php echo esc_attr($agama_option); ?>" <?php selected($filter_agama, $agama_option); ?>>
                                        <?php echo esc_html($agama_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="cbt-users-filter-jenis-kelamin" name="cbt_user_jenis_kelamin">
                                <option value="">Semua Jenis Kelamin</option>
                                <?php foreach ($jenis_kelamin_options as $jenis_kelamin_option): ?>
                                    <option value="<?php echo esc_attr($jenis_kelamin_option); ?>" <?php selected($filter_jenis_kelamin, $jenis_kelamin_option); ?>>
                                        <?php echo esc_html($jenis_kelamin_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="cbt-users-filter-per-page" name="cbt_user_per_page">
                                <?php foreach ($per_page_options as $per_page_option): ?>
                                    <option value="<?php echo (int) $per_page_option; ?>" <?php selected($per_page, $per_page_option); ?>>
                                        <?php echo esc_html((string) $per_page_option); ?> / halaman
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?php echo esc_url($user_reset_url); ?>" class="cbt-users-filter-reset" data-cbt-users-tab-link="list">Reset</a>
                        </form>
                    </div>

                    <?php
                    $diagnostic_is_open = !empty($diagnostic_data) && is_array($diagnostic_data);
                    $diagnostic_student = $diagnostic_is_open && isset($diagnostic_data['student']) && is_array($diagnostic_data['student']) ? $diagnostic_data['student'] : [];
                    $diagnostic_profile = isset($diagnostic_student['profile']) && is_array($diagnostic_student['profile']) ? $diagnostic_student['profile'] : [];
                    $diagnostic_summary = $diagnostic_is_open && isset($diagnostic_data['summary']) && is_array($diagnostic_data['summary']) ? $diagnostic_data['summary'] : [];
                    $diagnostic_items = $diagnostic_is_open && isset($diagnostic_data['items']) && is_array($diagnostic_data['items']) ? $diagnostic_data['items'] : [];
                    $diagnostic_name = trim((string) ($diagnostic_student['name'] ?? ''));
                    $diagnostic_username = trim((string) ($diagnostic_student['username'] ?? ''));
                    ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-users-tab-submit="list" data-cbt-users-async-form data-cbt-users-progress-profile="delete" data-cbt-users-refresh-areas="notices,overview,list-panel" data-cbt-users-success-tab="list">
                        <?php wp_nonce_field('cbt_bulk_delete_users'); ?>
                        <input type="hidden" name="action" value="cbt_bulk_delete_users" />
                        <input type="hidden" name="cbt_user_q" value="<?php echo esc_attr($search); ?>" />
                        <input type="hidden" name="cbt_user_role" value="<?php echo esc_attr($filter_role); ?>" />
                        <input type="hidden" name="cbt_user_kelas" value="<?php echo esc_attr($filter_kelas); ?>" />
                        <input type="hidden" name="cbt_user_ruang" value="<?php echo esc_attr($filter_ruang); ?>" />
                        <input type="hidden" name="cbt_user_agama" value="<?php echo esc_attr($filter_agama); ?>" />
                        <input type="hidden" name="cbt_user_jenis_kelamin" value="<?php echo esc_attr($filter_jenis_kelamin); ?>" />
                        <input type="hidden" name="cbt_user_per_page" value="<?php echo (int) $per_page; ?>" />
                        <input type="hidden" name="cbt_user_paged" value="<?php echo (int) $current_page; ?>" />
                        <div class="cbt-users-bulk-actions">
                            <button type="submit" class="button button-secondary cbt-users-bulk-button cbt-users-bulk-button--selected" name="bulk_mode" value="selected" onclick="return confirm('Yakin hapus user yang dipilih?');">Delete Selected</button>
                            <button type="submit" class="button button-secondary cbt-users-bulk-button cbt-users-bulk-button--all" name="bulk_mode" value="all_filtered" onclick="return confirm('Yakin hapus semua user sesuai hasil filter saat ini?');">Delete All (Filtered)</button>
                        </div>

                        <div class="cbt-users-table-wrap">
                        <table class="widefat striped cbt-users-main-table">
                            <thead>
                            <tr>
                                <th style="width:32px;"><input type="checkbox" id="cbt-user-select-all" /></th>
                                <th>ID</th>
                                <th>Identitas</th>
                                <th>Akun</th>
                                <th>Profil</th>
                                <th>Mapel Pilihan</th>
                                <th>Daftar</th>
                                <th>Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                                <?php
                                $user_has_filters = $search !== '' || $filter_role !== '' || $filter_kelas !== '' || $filter_ruang !== '' || $filter_agama !== '' || $filter_jenis_kelamin !== '';
                                echo CBT_Admin_UI_Helper::render_table_empty_state(8, [
                                    'title' => $user_has_filters ? 'Tidak ada user sesuai filter' : 'Belum ada user',
                                    'message' => $user_has_filters
                                        ? 'Tidak ada user yang cocok dengan filter saat ini. Reset filter untuk melihat semua user.'
                                        : 'Import user dari XLSX/CSV atau tambah user manual untuk mulai menyiapkan peserta.',
                                    'action_label' => $user_has_filters ? 'Reset Filter' : 'Import Users',
                                    'action_url' => $user_has_filters ? $user_reset_url : admin_url('admin.php?page=cbt-user-import'),
                                    'action_class' => $user_has_filters ? 'button button-secondary cbt-admin-btn--secondary' : 'button button-primary',
                                ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $role = isset($user->roles[0]) ? (string) $user->roles[0] : '';
                                    $is_student_role = in_array($role, ['student', 'siswa', 'siswa_cbt', 'subscriber'], true);
                                    $nisn = (string) get_user_meta((int) $user->ID, 'nisn', true);
                                    $kelas = (string) get_user_meta((int) $user->ID, 'kode_kelas', true);
                                    $ruang = (string) get_user_meta((int) $user->ID, 'kode_ruang', true);
                                    $agama = (string) get_user_meta((int) $user->ID, 'agama', true);
                                    $jenis_kelamin = CBT_Admin_Users_Service::normalize_supported_jenis_kelamin((string) get_user_meta((int) $user->ID, 'jenis_kelamin', true));
                                    $subject_choice_labels = isset($subject_choice_labels_by_user[(int) $user->ID]) && is_array($subject_choice_labels_by_user[(int) $user->ID])
                                        ? $subject_choice_labels_by_user[(int) $user->ID]
                                        : [];
                                    $foto = class_exists('CBT_Student_Profile_Cache')
                                        ? CBT_Student_Profile_Cache::normalize_photo_url((string) get_user_meta((int) $user->ID, 'foto', true))
                                        : esc_url_raw((string) get_user_meta((int) $user->ID, 'foto', true));
                                    $edit_url = add_query_arg(
                                        [
                                            'page' => 'cbt-user-import',
                                            'edit_user' => (int) $user->ID,
                                            'cbt_user_q' => $search,
                                            'cbt_user_role' => $filter_role,
                                            'cbt_user_kelas' => $filter_kelas,
                                            'cbt_user_ruang' => $filter_ruang,
                                            'cbt_user_agama' => $filter_agama,
                                            'cbt_user_jenis_kelamin' => $filter_jenis_kelamin,
                                            'cbt_user_per_page' => $per_page,
                                            'cbt_user_paged' => $current_page,
                                        ],
                                        admin_url('admin.php')
                                    );
                                    $delete_url = wp_nonce_url(
                                        add_query_arg(
                                            [
                                                'cbt_user_q' => $search,
                                                'cbt_user_role' => $filter_role,
                                                'cbt_user_kelas' => $filter_kelas,
                                                'cbt_user_ruang' => $filter_ruang,
                                                'cbt_user_agama' => $filter_agama,
                                                'cbt_user_jenis_kelamin' => $filter_jenis_kelamin,
                                                'cbt_user_per_page' => $per_page,
                                                'cbt_user_paged' => $current_page,
                                            ],
                                            admin_url('admin-post.php?action=cbt_delete_user_manual&id=' . (int) $user->ID)
                                        ),
                                        'cbt_delete_user_manual_' . (int) $user->ID
                                    );
                                    $diagnose_url = add_query_arg(
                                        [
                                            'page' => 'cbt-user-import',
                                            'diagnose_user' => (int) $user->ID,
                                            'cbt_user_q' => $search,
                                            'cbt_user_role' => $filter_role,
                                            'cbt_user_kelas' => $filter_kelas,
                                            'cbt_user_ruang' => $filter_ruang,
                                            'cbt_user_agama' => $filter_agama,
                                            'cbt_user_jenis_kelamin' => $filter_jenis_kelamin,
                                            'cbt_user_per_page' => $per_page,
                                            'cbt_user_paged' => $current_page,
                                        ],
                                        admin_url('admin.php')
                                    );
                                    $is_current_user = ((int) $user->ID === get_current_user_id());
                                    $is_diagnosed_user = $diagnostic_is_open && ((int) $user->ID === (int) $diagnostic_user_id);
                                    ?>
                                    <tr class="<?php echo $is_diagnosed_user ? 'is-diagnosed' : ''; ?>">
                                        <td>
                                            <?php if (!$is_current_user): ?>
                                                <input type="checkbox" class="cbt-user-row-check" name="user_ids[]" value="<?php echo (int) $user->ID; ?>" />
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo (int) $user->ID; ?></td>
                                        <td>
                                            <div class="cbt-users-user-stack">
                                                <strong><?php echo esc_html((string) $user->display_name); ?></strong>
                                                <span><?php echo esc_html((string) $user->user_login); ?></span>
                                                <span>NISN: <?php echo esc_html($nisn !== '' ? $nisn : '-'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cbt-users-account-stack">
                                                <strong><?php echo esc_html(CBT_Admin_Users_Service::humanize_role($role)); ?></strong>
                                                <span><?php echo esc_html((string) $user->user_email); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cbt-users-profile-stack">
                                                <div class="cbt-users-profile-grid">
                                                    <span class="cbt-users-profile-field"><span class="cbt-users-profile-label">Kelas</span><span class="cbt-users-profile-value"><?php echo esc_html($kelas !== '' ? $kelas : '-'); ?></span></span>
                                                    <span class="cbt-users-profile-field"><span class="cbt-users-profile-label">Ruang</span><span class="cbt-users-profile-value"><?php echo esc_html($ruang !== '' ? $ruang : '-'); ?></span></span>
                                                    <span class="cbt-users-profile-field"><span class="cbt-users-profile-label">Agama</span><span class="cbt-users-profile-value"><?php echo esc_html($agama !== '' ? $agama : '-'); ?></span></span>
                                                    <span class="cbt-users-profile-field"><span class="cbt-users-profile-label">L/P</span><span class="cbt-users-profile-value"><?php echo esc_html($jenis_kelamin !== '' ? $jenis_kelamin : '-'); ?></span></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($subject_choice_labels)): ?>
                                                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                                    <?php foreach ($subject_choice_labels as $subject_choice_label): ?>
                                                        <span class="cbt-users-chip" style="min-height:22px; padding:0 8px; font-size:10px;"><?php echo esc_html((string) $subject_choice_label); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="cbt-users-register-stack">
                                                <?php if ($foto !== ''): ?>
                                                    <a href="<?php echo esc_url($foto); ?>" target="_blank" rel="noopener noreferrer">
                                                        <img src="<?php echo esc_url($foto); ?>" alt="<?php echo esc_attr((string) $user->display_name); ?>" class="cbt-users-table-photo" />
                                                    </a>
                                                <?php else: ?>
                                                    <span class="cbt-users-photo-placeholder">-</span>
                                                <?php endif; ?>
                                                <span><?php echo esc_html(mysql2date('Y-m-d H:i', (string) $user->user_registered)); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cbt-admin-row-actions cbt-users-row-actions">
                                                <a class="cbt-admin-action cbt-admin-action--edit cbt-users-row-action cbt-users-row-action--edit" href="<?php echo esc_url($edit_url); ?>" data-cbt-users-async-link data-cbt-users-progress-profile="save" data-cbt-users-refresh-areas="notices,overview,form-panel,list-panel" data-cbt-users-success-tab="form">Edit</a>
                                                <?php if ($is_student_role): ?>
                                                <a class="cbt-admin-action cbt-admin-action--view cbt-users-row-action cbt-users-row-action--diagnose cbt-users-diagnose-link" href="<?php echo esc_url($diagnose_url); ?>" data-cbt-users-tab-link="list" data-cbt-users-progress-profile="diagnose" aria-expanded="<?php echo $is_diagnosed_user ? 'true' : 'false'; ?>">Diagnosa</a>
                                                <?php endif; ?>
                                                <?php if (!$is_current_user): ?>
                                                    <a class="cbt-admin-action cbt-admin-action--delete cbt-users-row-action cbt-users-row-action--delete" href="<?php echo esc_url($delete_url); ?>" data-cbt-users-async-link data-cbt-users-progress-profile="delete" data-cbt-users-refresh-areas="notices,overview,list-panel" data-cbt-users-success-tab="list" onclick="return confirm('Hapus user ini?');">Delete</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php if ($is_diagnosed_user): ?>
                                        <tr class="cbt-admin-drawer-row cbt-users-diagnostic-row">
                                            <td colspan="8">
                                                <div class="cbt-admin-drawer-panel cbt-users-diagnostic">
                                                    <div class="cbt-users-diagnostic-head">
                                                        <div>
                                                            <h3>Diagnosa Exam Siswa</h3>
                                                            <p>
                                                                <?php echo esc_html($diagnostic_name !== '' ? $diagnostic_name : ('User #' . (int) ($diagnostic_student['user_id'] ?? 0))); ?>
                                                                <?php if ($diagnostic_username !== ''): ?>
                                                                    (<?php echo esc_html($diagnostic_username); ?>)
                                                                <?php endif; ?>
                                                                &middot; Kelas <?php echo esc_html((string) ($diagnostic_profile['kode_kelas'] ?? '-')); ?>
                                                                &middot; Agama <?php echo esc_html((string) ($diagnostic_profile['agama'] ?? '-')); ?>
                                                                &middot; <?php echo esc_html((string) ($diagnostic_profile['jenis_kelamin'] ?? '-')); ?>
                                                            </p>
                                                        </div>
                                                        <a href="<?php echo esc_url($diagnostic_clear_url); ?>" class="button button-secondary cbt-users-diagnostic-close" data-cbt-users-tab-link="list" data-cbt-users-progress-profile="diagnose">Tutup Diagnosa</a>
                                                    </div>
                                                    <div class="cbt-users-diagnostic-summary">
                                                        <span class="cbt-users-diagnostic-pill">Total exam: <?php echo (int) ($diagnostic_summary['total'] ?? 0); ?></span>
                                                        <span class="cbt-users-diagnostic-pill">Bisa dikerjakan: <?php echo (int) ($diagnostic_summary['can_start'] ?? 0); ?></span>
                                                        <span class="cbt-users-diagnostic-pill">Terblokir/info: <?php echo (int) ($diagnostic_summary['blocked'] ?? 0); ?></span>
                                                        <span class="cbt-users-diagnostic-pill">Sedang berjalan: <?php echo (int) ($diagnostic_summary['in_progress'] ?? 0); ?></span>
                                                        <span class="cbt-users-diagnostic-pill">Selesai: <?php echo (int) ($diagnostic_summary['completed'] ?? 0); ?></span>
                                                    </div>
                                                    <p style="margin:0; color:#475569; font-weight:600;"><?php echo esc_html((string) ($diagnostic_data['message'] ?? '')); ?></p>
                                                    <?php if (!empty($diagnostic_items)): ?>
                                                        <div class="cbt-users-diagnostic-list">
                                                            <?php foreach ($diagnostic_items as $diagnostic_item): ?>
                                                                <?php
                                                                $tone = sanitize_key((string) ($diagnostic_item['tone'] ?? 'neutral'));
                                                                if (!in_array($tone, ['success', 'warning', 'danger', 'neutral'], true)) {
                                                                    $tone = 'neutral';
                                                                }
                                                                ?>
                                                                <div class="cbt-users-diagnostic-item">
                                                                    <div class="cbt-users-diagnostic-item-head">
                                                                        <div class="cbt-users-diagnostic-item-title">
                                                                            <strong><?php echo esc_html((string) ($diagnostic_item['title'] ?? '')); ?></strong>
                                                                            <span>Exam #<?php echo (int) ($diagnostic_item['exam_id'] ?? 0); ?></span>
                                                                        </div>
                                                                        <span class="cbt-users-diagnostic-tone cbt-users-diagnostic-tone--<?php echo esc_attr($tone); ?>">
                                                                            <?php echo !empty($diagnostic_item['can_start_now']) ? 'Bisa' : 'Tidak'; ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="cbt-users-diagnostic-item-meta">
                                                                        <div class="cbt-users-diagnostic-field">
                                                                            <span>Mapel</span>
                                                                            <strong><?php echo esc_html((string) ($diagnostic_item['subject_label'] ?? '-')); ?></strong>
                                                                        </div>
                                                                        <div class="cbt-users-diagnostic-field">
                                                                            <span>Status</span>
                                                                            <strong><?php echo esc_html((string) ($diagnostic_item['status'] ?? '-')); ?></strong>
                                                                        </div>
                                                                        <div class="cbt-users-diagnostic-field">
                                                                            <span>Jadwal</span>
                                                                            <strong><?php echo esc_html((string) ($diagnostic_item['schedule_label'] ?? '-')); ?></strong>
                                                                        </div>
                                                                        <div class="cbt-users-diagnostic-field">
                                                                            <span>Attempt</span>
                                                                            <strong><?php echo esc_html((string) ($diagnostic_item['attempt_label'] ?? '-')); ?></strong>
                                                                        </div>
                                                                        <div class="cbt-users-diagnostic-field">
                                                                            <span>Alasan</span>
                                                                            <strong><?php echo esc_html((string) ($diagnostic_item['message'] ?? '')); ?></strong>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        </div>

                        <div class="tablenav bottom cbt-users-pagination-wrap">
                            <div class="tablenav-pages cbt-users-pagination" style="float:none; margin:0;">
                                <span class="displaying-num cbt-users-total"><?php echo esc_html(sprintf('Total user: %d', $total_users)); ?></span>
                                <?php if (!empty($pagination_links)): ?>
                                    <span class="pagination-links cbt-users-pagination-links">
                                        <?php foreach ($pagination_links as $pagination_link): ?>
                                            <?php echo wp_kses_post($pagination_link); ?>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </section>
            </div>
        </div>
        <script>
            (function () {
                const page = document.querySelector('[data-cbt-users-root]');
                const tabButtons = Array.from(document.querySelectorAll('[data-cbt-users-tab]'));
                const tabStorageKey = 'cbt-users-active-tab';
                const defaultTab = page ? String(page.getAttribute('data-cbt-users-default-tab') || 'list') : 'list';
                const forceTab = page ? page.getAttribute('data-cbt-users-force-tab') === '1' : false;
                const supportsPartialListRefresh = !!(window.fetch && window.DOMParser && window.FormData && window.URL);
                let userFilterTimer = 0;
                let userListRequestSeq = 0;
                let usersProgressTimer = 0;
                let usersProgressValue = 0;
                let usersImportTimer = 0;
                let usersImportInFlight = false;

                function getUsersTabPanels() {
                    return Array.from(document.querySelectorAll('[data-cbt-users-panel]'));
                }

                function getCurrentUsersTab() {
                    const activePanel = getUsersTabPanels().find((panel) => panel.classList.contains('is-active'));
                    return activePanel ? String(activePanel.getAttribute('data-cbt-users-panel') || 'list') : 'list';
                }

                function rememberUsersTab(tabId) {
                    if (tabId !== '' && window.localStorage) {
                        window.localStorage.setItem(tabStorageKey, tabId);
                    }
                }

                function activateTab(tabId, persist) {
                    const tabPanels = getUsersTabPanels();
                    let hasTarget = false;
                    tabButtons.forEach((button) => {
                        const isActive = button.getAttribute('data-cbt-users-tab') === tabId;
                        button.classList.toggle('is-active', isActive);
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        if (isActive) {
                            hasTarget = true;
                        }
                    });
                    tabPanels.forEach((panel) => {
                        const isActive = panel.getAttribute('data-cbt-users-panel') === tabId;
                        panel.classList.toggle('is-active', isActive);
                    });
                    if (persist && hasTarget) {
                        rememberUsersTab(tabId);
                    }
                }

                function getUsersProgressProfile(profile) {
                    const profiles = {
                        save: {
                            start: 'Menyimpan data user...',
                            detail: 'Validasi form, simpan profil, lalu segarkan form dan daftar user.',
                            done: 'Data user sudah disimpan.',
                            doneDetail: 'Panel form, ringkasan, dan daftar user sudah diperbarui lokal.'
                        },
                        import: {
                            start: 'Memproses import user...',
                            detail: 'Upload/preview/import batch berjalan di panel Import Users.',
                            done: 'Import user diperbarui.',
                            doneDetail: 'Status import, ringkasan, dan daftar user sudah disegarkan.'
                        },
                        'subject-choice': {
                            start: 'Memproses mapel pilihan...',
                            detail: 'Preview atau import mapel pilihan siswa sedang diproses.',
                            done: 'Mapel pilihan diperbarui.',
                            doneDetail: 'Panel import dan daftar user sudah memuat data terbaru.'
                        },
                        delete: {
                            start: 'Menghapus user...',
                            detail: 'Permintaan hapus diproses, lalu daftar user dimuat ulang secara lokal.',
                            done: 'Daftar user sudah diperbarui.',
                            doneDetail: 'User terhapus dan total daftar sudah disegarkan.'
                        },
                        diagnose: {
                            start: 'Memuat diagnosa user...',
                            detail: 'Status exam siswa sedang dihitung dan dibuka di baris daftar.',
                            done: 'Diagnosa user sudah diperbarui.',
                            doneDetail: 'Detail diagnosa tampil di panel daftar tanpa reload halaman.'
                        },
                        list: {
                            start: 'Memuat daftar user...',
                            detail: 'Filter, pencarian, atau paginasi sedang disegarkan di panel daftar.',
                            done: 'Daftar user sudah diperbarui.',
                            doneDetail: 'Panel daftar sekarang memakai hasil filter terbaru.'
                        }
                    };

                    return profiles[profile] || profiles.list;
                }

                function getUsersProgressElements() {
                    const progress = page ? page.querySelector('[data-cbt-users-progress]') : null;
                    if (!progress) {
                        return null;
                    }

                    return {
                        root: progress,
                        label: progress.querySelector('[data-cbt-users-progress-label]'),
                        percent: progress.querySelector('[data-cbt-users-progress-percent]'),
                        track: progress.querySelector('[data-cbt-users-progress-track]'),
                        fill: progress.querySelector('[data-cbt-users-progress-fill]'),
                        step: progress.querySelector('[data-cbt-users-progress-step]')
                    };
                }

                function clampUsersProgress(value) {
                    return Math.max(0, Math.min(100, Math.round(Number(value) || 0)));
                }

                function setUsersProgress(value, label, step, tone) {
                    const elements = getUsersProgressElements();
                    if (!elements) {
                        return;
                    }

                    usersProgressValue = clampUsersProgress(value);
                    elements.root.classList.add('is-active');
                    elements.root.classList.toggle('is-error', tone === 'error');
                    elements.root.setAttribute('aria-hidden', 'false');
                    elements.root.style.setProperty('--cbt-users-progress', usersProgressValue + '%');
                    if (elements.percent) {
                        elements.percent.textContent = usersProgressValue + '%';
                    }
                    if (elements.track) {
                        elements.track.setAttribute('aria-valuenow', String(usersProgressValue));
                    }
                    if (elements.label && label) {
                        elements.label.textContent = label;
                    }
                    if (elements.step && step) {
                        elements.step.textContent = step;
                    }
                }

                function startUsersProgress(profile) {
                    const config = getUsersProgressProfile(profile);
                    window.clearInterval(usersProgressTimer);
                    setUsersProgress(8, config.start, config.detail, 'active');
                    usersProgressTimer = window.setInterval(function () {
                        if (usersProgressValue >= 88) {
                            window.clearInterval(usersProgressTimer);
                            return;
                        }
                        setUsersProgress(usersProgressValue + Math.max(2, Math.round((88 - usersProgressValue) / 7)), config.start, config.detail, 'active');
                    }, 360);
                }

                function completeUsersProgress(label, step, tone) {
                    const elements = getUsersProgressElements();
                    window.clearInterval(usersProgressTimer);
                    setUsersProgress(tone === 'error' ? Math.max(usersProgressValue, 72) : 100, label, step, tone);

                    if (!elements || tone === 'error') {
                        return;
                    }

                    window.setTimeout(function () {
                        const nextElements = getUsersProgressElements();
                        if (!nextElements) {
                            return;
                        }
                        nextElements.root.classList.remove('is-active', 'is-error');
                        nextElements.root.setAttribute('aria-hidden', 'true');
                    }, 1800);
                }

                function extractUsersResponseError(html, status) {
                    const fallback = 'HTTP ' + String(status || 0);
                    if (!html) {
                        return fallback;
                    }

                    try {
                        const parsed = new DOMParser().parseFromString(String(html), 'text/html');
                        const title = parsed.querySelector('title');
                        const bodyText = String((parsed.body && parsed.body.textContent) || '').replace(/\s+/g, ' ').trim();
                        const titleText = title ? String(title.textContent || '').replace(/\s+/g, ' ').trim() : '';
                        const message = bodyText || titleText || fallback;
                        return fallback + ': ' + message.slice(0, 220);
                    } catch (error) {
                        return fallback;
                    }
                }

                async function fetchUsersHtml(nextUrl, options) {
                    const fetchOptions = Object.assign({
                        credentials: 'same-origin',
                        cache: 'no-store',
                        redirect: 'follow',
                        headers: {}
                    }, options || {});
                    fetchOptions.headers = Object.assign({
                        Accept: 'text/html, */*',
                        'X-Requested-With': 'XMLHttpRequest'
                    }, fetchOptions.headers || {});

                    const response = await window.fetch(nextUrl.toString(), fetchOptions);
                    const html = await response.text();
                    if (!response.ok) {
                        throw new Error(extractUsersResponseError(html, response.status));
                    }

                    return {
                        html: html,
                        url: response.url || nextUrl.toString()
                    };
                }

                function updateUsersHistory(nextUrl) {
                    if (!window.history || typeof window.history.replaceState !== 'function') {
                        return;
                    }

                    const parsedUrl = new URL(nextUrl.toString(), window.location.href);
                    if (parsedUrl.origin !== window.location.origin) {
                        return;
                    }

                    window.history.replaceState({}, '', parsedUrl.toString());
                }

                function getUsersAreaList(source, fallbackAreas) {
                    const raw = source ? String(source.getAttribute('data-cbt-users-refresh-areas') || '') : '';
                    const parsed = raw.split(',').map((area) => area.trim()).filter(Boolean);
                    return parsed.length > 0 ? parsed : fallbackAreas;
                }

                function replaceUsersRefreshAreas(html, areas) {
                    const parsed = new DOMParser().parseFromString(html, 'text/html');
                    const replaced = [];
                    (areas || []).forEach((area) => {
                        const currentArea = page ? page.querySelector('[data-cbt-users-refresh-area="' + area + '"]') : null;
                        const nextArea = parsed.querySelector('[data-cbt-users-refresh-area="' + area + '"]');
                        if (!currentArea || !nextArea) {
                            return;
                        }

                        currentArea.replaceWith(nextArea);
                        replaced.push(area);
                    });

                    const nextRoot = parsed.querySelector('[data-cbt-users-root]');
                    if (page && nextRoot) {
                        page.setAttribute('data-cbt-users-default-tab', String(nextRoot.getAttribute('data-cbt-users-default-tab') || defaultTab));
                        page.setAttribute('data-cbt-users-force-tab', String(nextRoot.getAttribute('data-cbt-users-force-tab') || '0'));
                    }

                    return replaced;
                }

                function getUsersTargetTab(source, replacedAreas) {
                    const explicit = source ? String(source.getAttribute('data-cbt-users-success-tab') || '') : '';
                    if (explicit !== '') {
                        return explicit;
                    }
                    if ((replacedAreas || []).includes('form-panel')) {
                        return 'form';
                    }
                    if ((replacedAreas || []).includes('import-panel')) {
                        return 'import';
                    }
                    return getCurrentUsersTab();
                }

                function setUsersElementLoading(element, isLoading) {
                    if (!element) {
                        return;
                    }
                    element.classList.toggle('is-loading', isLoading);
                    element.setAttribute('aria-busy', isLoading ? 'true' : 'false');
                    if ('disabled' in element) {
                        element.disabled = isLoading;
                    }
                }

                function getUsersProfileName(source, fallback) {
                    return source ? String(source.getAttribute('data-cbt-users-progress-profile') || fallback || 'list') : (fallback || 'list');
                }

                function rebindUsersLocalUi(replacedAreas, targetTab) {
                    bindUsersManualFormHelpers();
                    bindUsersLocalActions();
                    bindUsersListPanel();
                    bindUsersImportContinuation();
                    activateTab(targetTab || getCurrentUsersTab(), true);
                }

                async function runUsersLocalAction(source, requestUrl, options) {
                    const profileName = getUsersProfileName(source, 'list');
                    const profile = getUsersProgressProfile(profileName);
                    const areas = getUsersAreaList(source, ['notices', 'overview', 'list-panel']);

                    if (!supportsPartialListRefresh) {
                        completeUsersProgress('Browser belum mendukung refresh lokal.', 'Aksi tidak dijalankan agar halaman tidak melakukan reload global.', 'error');
                        return;
                    }

                    startUsersProgress(profileName);
                    const result = await fetchUsersHtml(requestUrl, options);
                    const replacedAreas = replaceUsersRefreshAreas(result.html, areas);
                    if (replacedAreas.length === 0) {
                        throw new Error('Respons tidak memuat area Users yang bisa diperbarui.');
                    }

                    updateUsersHistory(new URL(result.url, window.location.href));
                    rebindUsersLocalUi(replacedAreas, getUsersTargetTab(source, replacedAreas));
                    completeUsersProgress(profile.done, profile.doneDetail, 'success');
                }

                function bindUsersTabs() {
                    if (!page || tabButtons.length === 0 || getUsersTabPanels().length === 0) {
                        return;
                    }

                    let initialTab = defaultTab;
                    if (!forceTab && window.localStorage) {
                        const savedTab = window.localStorage.getItem(tabStorageKey);
                        if (savedTab && getUsersTabPanels().some((panel) => panel.getAttribute('data-cbt-users-panel') === savedTab)) {
                            initialTab = savedTab;
                        }
                    }

                    activateTab(initialTab, false);

                    tabButtons.forEach((button) => {
                        if (button.dataset.cbtTabBound === '1') {
                            return;
                        }
                        button.dataset.cbtTabBound = '1';
                        button.addEventListener('click', function () {
                            activateTab(String(button.getAttribute('data-cbt-users-tab') || ''), true);
                        });
                    });
                }

                function bindRoleAwareJenisKelamin(roleSelector, jenisKelaminSelector) {
                    const roleField = document.querySelector(roleSelector);
                    const jenisKelaminField = document.querySelector(jenisKelaminSelector);
                    if (!roleField || !jenisKelaminField || roleField.dataset.cbtJenisKelaminBound === '1') {
                        return;
                    }

                    roleField.dataset.cbtJenisKelaminBound = '1';
                    const syncState = function () {
                        const roleValue = String(roleField.value || '').toLowerCase();
                        const isStudent = roleValue === 'siswa';
                        jenisKelaminField.required = isStudent;
                        jenisKelaminField.setAttribute('aria-required', isStudent ? 'true' : 'false');
                    };

                    roleField.addEventListener('change', syncState);
                    syncState();
                }

                function bindUsersManualFormHelpers() {
                    bindRoleAwareJenisKelamin('#cbt-user-role', '#cbt-user-jenis-kelamin');
                    bindRoleAwareJenisKelamin('#cbt-edit-user-role', '#cbt-edit-user-jenis-kelamin');
                }

                function buildUsersFilterUrl(form) {
                    const nextUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                    const formData = new FormData(form);

                    nextUrl.search = '';
                    formData.forEach((value, key) => {
                        if (typeof value !== 'string') {
                            return;
                        }
                        nextUrl.searchParams.set(key, value);
                    });

                    return nextUrl;
                }

                function setUsersListPanelLoading(panel, isLoading) {
                    if (!panel) {
                        return;
                    }

                    panel.classList.toggle('is-loading', isLoading);
                    panel.setAttribute('aria-busy', isLoading ? 'true' : 'false');
                }

                function getUsersListPanel() {
                    return page ? page.querySelector('[data-cbt-users-panel="list"]') : null;
                }

                function captureUsersListFocus(panel) {
                    const activeElement = document.activeElement;
                    if (!panel || !activeElement || !panel.contains(activeElement) || !activeElement.id) {
                        return null;
                    }

                    const focusState = {
                        id: String(activeElement.id || '')
                    };
                    if (typeof activeElement.selectionStart === 'number') {
                        focusState.selectionStart = activeElement.selectionStart;
                        focusState.selectionEnd = typeof activeElement.selectionEnd === 'number'
                            ? activeElement.selectionEnd
                            : activeElement.selectionStart;
                    }

                    return focusState;
                }

                function restoreUsersListFocus(focusState) {
                    if (!focusState || !focusState.id) {
                        return;
                    }

                    const nextField = document.getElementById(focusState.id);
                    if (!nextField) {
                        return;
                    }

                    if (typeof nextField.focus === 'function') {
                        nextField.focus({ preventScroll: true });
                    }
                    if (
                        typeof focusState.selectionStart === 'number' &&
                        typeof nextField.setSelectionRange === 'function'
                    ) {
                        try {
                            nextField.setSelectionRange(focusState.selectionStart, focusState.selectionEnd);
                        } catch (error) {
                        }
                    }
                }

                function showUsersLocalRefreshError(message) {
                    completeUsersProgress('Gagal memperbarui area Users.', message || 'Form masih bisa dicoba lagi setelah koneksi stabil.', 'error');
                }

                async function refreshUsersListPanel(nextUrl, profileName) {
                    const currentPanel = getUsersListPanel();
                    if (!currentPanel || !supportsPartialListRefresh) {
                        showUsersLocalRefreshError('Browser tidak mendukung partial refresh untuk panel daftar.');
                        return;
                    }

                    userListRequestSeq += 1;
                    const requestSeq = userListRequestSeq;
                    const focusState = captureUsersListFocus(currentPanel);
                    const profile = getUsersProgressProfile(profileName || 'list');
                    setUsersListPanelLoading(currentPanel, true);
                    startUsersProgress(profileName || 'list');

                    try {
                        const result = await fetchUsersHtml(nextUrl, {});
                        if (requestSeq !== userListRequestSeq) {
                            return;
                        }

                        const replacedAreas = replaceUsersRefreshAreas(result.html, ['notices', 'overview', 'list-panel']);
                        if (!replacedAreas.includes('list-panel')) {
                            throw new Error('Respons tidak memuat panel daftar user.');
                        }

                        updateUsersHistory(new URL(result.url, window.location.href));
                        rebindUsersLocalUi(replacedAreas, 'list');
                        restoreUsersListFocus(focusState);
                        completeUsersProgress(profile.done, profile.doneDetail, 'success');
                    } catch (error) {
                        if (requestSeq === userListRequestSeq) {
                            showUsersLocalRefreshError(error && error.message ? error.message : 'Daftar user belum bisa dimuat lokal.');
                        }
                    } finally {
                        if (requestSeq === userListRequestSeq) {
                            setUsersListPanelLoading(getUsersListPanel(), false);
                        }
                    }
                }

                function submitUserFilters(form) {
                    if (!form) {
                        return;
                    }

                    rememberUsersTab('list');
                    window.clearTimeout(userFilterTimer);
                    refreshUsersListPanel(buildUsersFilterUrl(form), 'list');
                }

                function bindUsersLocalActions() {
                    Array.from(document.querySelectorAll('form[data-cbt-users-tab-submit]')).forEach((form) => {
                        if (form.dataset.cbtTabMemoryBound === '1') {
                            return;
                        }
                        form.dataset.cbtTabMemoryBound = '1';
                        form.addEventListener('submit', function () {
                            rememberUsersTab(String(form.getAttribute('data-cbt-users-tab-submit') || ''));
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-users-tab-link]')).forEach((link) => {
                        if (link.dataset.cbtTabMemoryBound === '1') {
                            return;
                        }
                        link.dataset.cbtTabMemoryBound = '1';
                        link.addEventListener('click', function () {
                            rememberUsersTab(String(link.getAttribute('data-cbt-users-tab-link') || ''));
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-users-async-form]')).forEach((form) => {
                        if (form.dataset.cbtLocalActionBound === '1') {
                            return;
                        }
                        form.dataset.cbtLocalActionBound = '1';
                        form.addEventListener('submit', function (event) {
                            if (event.defaultPrevented) {
                                return;
                            }
                            event.preventDefault();
                            const submitter = event.submitter || document.activeElement;
                            const actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                            const formData = new FormData(form);
                            formData.set('cbt_users_local_refresh', '1');
                            if (
                                submitter &&
                                submitter.name &&
                                !formData.has(submitter.name)
                            ) {
                                formData.append(submitter.name, submitter.value || '');
                            }

                            setUsersElementLoading(submitter, true);
                            runUsersLocalAction(form, actionUrl, {
                                method: String(form.getAttribute('method') || 'post').toUpperCase(),
                                body: formData
                            }).catch((error) => {
                                showUsersLocalRefreshError(error && error.message ? error.message : 'Aksi user gagal diproses lokal.');
                            }).finally(() => {
                                setUsersElementLoading(submitter, false);
                            });
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-users-async-link]')).forEach((link) => {
                        if (link.dataset.cbtLocalActionBound === '1') {
                            return;
                        }
                        link.dataset.cbtLocalActionBound = '1';
                        link.addEventListener('click', function (event) {
                            if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }
                            event.preventDefault();
                            const nextUrl = new URL(link.getAttribute('href') || window.location.href, window.location.href);
                            nextUrl.searchParams.set('cbt_users_local_refresh', '1');
                            setUsersElementLoading(link, true);
                            runUsersLocalAction(link, nextUrl, {
                                method: 'GET'
                            }).catch((error) => {
                                showUsersLocalRefreshError(error && error.message ? error.message : 'Link user gagal diproses lokal.');
                            }).finally(() => {
                                setUsersElementLoading(link, false);
                            });
                        });
                    });
                }

                function bindUsersImportContinuation() {
                    const progress = page ? page.querySelector('[data-cbt-users-import-progress]') : null;
                    if (!progress || progress.getAttribute('data-cbt-users-import-running') !== '1') {
                        return;
                    }
                    const continueUrl = String(progress.getAttribute('data-cbt-users-import-continue-url') || '');
                    if (continueUrl === '' || usersImportInFlight || progress.dataset.cbtImportContinuationBound === '1') {
                        return;
                    }

                    progress.dataset.cbtImportContinuationBound = '1';
                    usersImportInFlight = true;
                    window.clearTimeout(usersImportTimer);
                    usersImportTimer = window.setTimeout(function () {
                        const nextUrl = new URL(continueUrl, window.location.href);
                        nextUrl.searchParams.set('cbt_users_local_refresh', '1');
                        runUsersLocalAction(progress, nextUrl, {
                            method: 'GET'
                        }).catch((error) => {
                            showUsersLocalRefreshError(error && error.message ? error.message : 'Batch import user berikutnya gagal dimuat lokal.');
                        }).finally(() => {
                            usersImportInFlight = false;
                        });
                    }, 420);
                }

                function bindUsersListSelection(panel) {
                    if (!panel) {
                        return;
                    }

                    const selectAll = panel.querySelector('#cbt-user-select-all');
                    const rowChecks = Array.from(panel.querySelectorAll('.cbt-user-row-check'));
                    if (!selectAll || rowChecks.length === 0) {
                        if (selectAll) {
                            selectAll.checked = false;
                            selectAll.indeterminate = false;
                        }
                        return;
                    }

                    if (selectAll.dataset.cbtBound === '1') {
                        return;
                    }
                    selectAll.dataset.cbtBound = '1';

                    function syncSelectState() {
                        const checkedCount = rowChecks.filter((item) => item.checked).length;
                        selectAll.checked = checkedCount > 0 && checkedCount === rowChecks.length;
                        selectAll.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
                    }

                    selectAll.addEventListener('change', () => {
                        rowChecks.forEach((item) => {
                            item.checked = selectAll.checked;
                        });
                        syncSelectState();
                    });

                    rowChecks.forEach((item) => {
                        item.addEventListener('change', syncSelectState);
                    });
                }

                function bindUsersListPanel() {
                    const panel = getUsersListPanel();
                    if (!panel) {
                        return;
                    }

                    const userFilterForm = panel.querySelector('.cbt-users-filter-form');
                    const userFilterSearch = panel.querySelector('#cbt-users-filter-search');
                    const userFilterRole = panel.querySelector('#cbt-users-filter-role');
                    const userFilterKelas = panel.querySelector('#cbt-users-filter-kelas');
                    const userFilterRuang = panel.querySelector('#cbt-users-filter-ruang');
                    const userFilterAgama = panel.querySelector('#cbt-users-filter-agama');
                    const userFilterJenisKelamin = panel.querySelector('#cbt-users-filter-jenis-kelamin');
                    const userFilterPerPage = panel.querySelector('#cbt-users-filter-per-page');
                    const userFilterReset = panel.querySelector('.cbt-users-filter-reset');
                    const paginationLinks = Array.from(panel.querySelectorAll('.cbt-users-pagination-links a'));
                    const diagnosticLinks = Array.from(panel.querySelectorAll('.cbt-users-diagnose-link, .cbt-users-diagnostic-close'));

                    if (userFilterForm && userFilterForm.dataset.cbtAsyncBound !== '1') {
                        userFilterForm.dataset.cbtAsyncBound = '1';
                        userFilterForm.addEventListener('submit', function (event) {
                            event.preventDefault();
                            submitUserFilters(userFilterForm);
                        });
                    }

                    [userFilterRole, userFilterKelas, userFilterRuang, userFilterAgama, userFilterJenisKelamin, userFilterPerPage].forEach((field) => {
                        if (!field || field.dataset.cbtAutoBound === '1') {
                            return;
                        }

                        field.dataset.cbtAutoBound = '1';
                        field.addEventListener('change', function () {
                            submitUserFilters(userFilterForm);
                        });
                    });

                    if (userFilterSearch && userFilterSearch.dataset.cbtAutoBound !== '1') {
                        userFilterSearch.dataset.cbtAutoBound = '1';
                        userFilterSearch.addEventListener('input', function () {
                            window.clearTimeout(userFilterTimer);
                            userFilterTimer = window.setTimeout(function () {
                                submitUserFilters(userFilterForm);
                            }, 280);
                        });
                        userFilterSearch.addEventListener('search', function () {
                            submitUserFilters(userFilterForm);
                        });
                        userFilterSearch.addEventListener('keydown', function (event) {
                            if (event.key !== 'Enter') {
                                return;
                            }
                            event.preventDefault();
                            submitUserFilters(userFilterForm);
                        });
                    }

                    if (userFilterReset && userFilterReset.dataset.cbtAsyncBound !== '1') {
                        userFilterReset.dataset.cbtAsyncBound = '1';
                        userFilterReset.addEventListener('click', function (event) {
                            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }

                            event.preventDefault();
                            window.clearTimeout(userFilterTimer);
                            refreshUsersListPanel(new URL(userFilterReset.getAttribute('href') || window.location.href, window.location.href), 'list');
                        });
                    }

                    paginationLinks.forEach((link) => {
                        if (!link || link.dataset.cbtAsyncBound === '1') {
                            return;
                        }

                        link.dataset.cbtAsyncBound = '1';
                        link.addEventListener('click', function (event) {
                            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }

                            event.preventDefault();
                            window.clearTimeout(userFilterTimer);
                            refreshUsersListPanel(new URL(link.getAttribute('href') || window.location.href, window.location.href), 'list');
                        });
                    });

                    diagnosticLinks.forEach((link) => {
                        if (!link || link.dataset.cbtAsyncBound === '1') {
                            return;
                        }

                        link.dataset.cbtAsyncBound = '1';
                        link.addEventListener('click', function (event) {
                            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }

                            event.preventDefault();
                            window.clearTimeout(userFilterTimer);
                            refreshUsersListPanel(new URL(link.getAttribute('href') || window.location.href, window.location.href), 'diagnose');
                        });
                    });

                    bindUsersListSelection(panel);
                }

                bindUsersTabs();
                bindUsersManualFormHelpers();
                bindUsersLocalActions();
                bindUsersListPanel();
                bindUsersImportContinuation();
            })();
        </script>
