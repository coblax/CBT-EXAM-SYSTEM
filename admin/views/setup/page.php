        <?php
        $cbt_admin_view_mode = isset($cbt_admin_view_mode) && $cbt_admin_view_mode === 'security' ? 'security' : 'branding';
        $is_security_view = $cbt_admin_view_mode === 'security';
        $is_branding_view = !$is_security_view;
        ?>
        <style>
            .cbt-setup-page {
                max-width: 1160px;
            }
            .cbt-setup-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            }
            .cbt-setup-hero {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                padding: 24px 28px;
                border: 1px solid #d7dbe2;
                border-radius: 22px;
                background:
                    radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 35%),
                    linear-gradient(135deg, #ffffff 0%, #f6f9fc 100%);
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            }
            .cbt-setup-hero-copy {
                max-width: 720px;
            }
            .cbt-setup-kicker {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 12px;
                border-radius: 999px;
                background: #e8f1ff;
                color: #0f4fa8;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
            }
            .cbt-setup-hero h1 {
                margin: 12px 0 8px;
                font-size: 30px;
                line-height: 1.15;
            }
            .cbt-setup-hero p {
                margin: 0;
                max-width: 680px;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-setup-tabs {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-top: 18px;
            }
            .cbt-setup-tab {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 32px;
                padding: 0 14px;
                border: 1px solid #cfe0f7;
                border-radius: 999px;
                background: #eef4ff;
                color: #27528c;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                cursor: pointer;
                transition: border-color 140ms ease, box-shadow 140ms ease, background 140ms ease, color 140ms ease, transform 140ms ease;
            }
            .cbt-setup-tab:hover,
            .cbt-setup-tab:focus {
                border-color: #8bb3e4;
                background: #f6f9ff;
                color: #173f73;
                box-shadow: 0 10px 20px rgba(59, 130, 246, 0.10);
                transform: translateY(-1px);
                outline: none;
            }
            .cbt-setup-tab.is-active {
                border-color: #2563eb;
                background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
                color: #ffffff;
                box-shadow: 0 12px 22px rgba(37, 99, 235, 0.18);
            }
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
                border: 1px solid #dcdcde;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
            }
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
            .cbt-setup-field-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px 18px;
            }
            .cbt-setup-field {
                display: grid;
                gap: 8px;
            }
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
            .cbt-setup-actions {
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
            .cbt-setup-security-form {
                display: grid;
                gap: 16px;
            }
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
            .cbt-setup-security-actions {
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
            .cbt-setup-security-log-body {
                display: grid;
                gap: 18px;
                padding: 0;
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
            .cbt-setup-security-log-watch-region,
            .cbt-setup-security-log-table-region {
                display: grid;
                gap: 16px;
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
            .cbt-setup-security-log-watch-item-indicators {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                margin-top: 2px;
            }
            .cbt-setup-security-log-watch-indicator {
                display: inline-flex;
                align-items: center;
                flex: 0 0 auto;
                min-height: 28px;
                padding: 0 10px;
                border: 1px solid #d7e4f5;
                border-radius: 999px;
                background: #f8fbff;
                color: #334155;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
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
                .cbt-setup-security-actions {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-setup-security-log-toolbar-live,
                .cbt-setup-security-log-toolbar-footer,
                .cbt-setup-security-log-toolbar-actions,
                .cbt-setup-security-log-watch-header {
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
                .cbt-setup-page {
                    margin-right: 10px;
                }
                .cbt-setup-hero,
                .cbt-setup-card {
                    padding: 20px;
                }
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
            }
        </style>
        <div class="wrap cbt-setup-page">
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

                <?php if ($notice): ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                <?php endif; ?>

                <div class="cbt-setup-panels">
                    <?php if ($is_branding_view): ?>
                    <div class="cbt-setup-panel is-active" id="cbt-setup-panel-branding" data-setup-panel="branding" role="region" aria-label="Branding">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-setup-form">
                            <?php wp_nonce_field('cbt_save_setup_branding'); ?>
                            <input type="hidden" name="action" value="cbt_save_setup_branding" />

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
                                    <div class="cbt-setup-field cbt-setup-field--full">
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
                                    <div class="cbt-setup-field cbt-setup-field--full">
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
                                    <div class="cbt-setup-field cbt-setup-field--full">
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
                                        <label for="cbt-setup-school-regency-country-ln">Kab.-Kota/Negara (LN)</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-regency-country-ln"
                                            name="school_regency_country_ln"
                                            value="<?php echo esc_attr($school_regency_country_ln); ?>"
                                            placeholder="Contoh: KAB. BELITUNG"
                                        />
                                    </div>
                                    <div class="cbt-setup-field">
                                        <label for="cbt-setup-school-province-abroad-ln">Propinsi/Luar Negeri (LN)</label>
                                        <input
                                            type="text"
                                            id="cbt-setup-school-province-abroad-ln"
                                            name="school_province_abroad_ln"
                                            value="<?php echo esc_attr($school_province_abroad_ln); ?>"
                                            placeholder="Contoh: PROV. KEPULAUAN BANGKA BELITUNG"
                                        />
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
                    <div class="cbt-setup-panel is-active" id="cbt-setup-panel-security" data-setup-panel="security" role="tabpanel" aria-labelledby="cbt-setup-tab-security">
                        <div class="cbt-setup-security-grid">
                            <section class="cbt-setup-card cbt-setup-security-card">
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Security</h2>
                                        <p>Tab ini menampung kontrol keamanan yang langsung memengaruhi perilaku peserta saat ujian berlangsung.</p>
                                    </div>
                                    <span class="cbt-setup-card-chip">Control</span>
                                </div>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-setup-security-form">
                                    <?php wp_nonce_field('cbt_save_security_settings'); ?>
                                    <input type="hidden" name="action" value="cbt_save_security_settings" />
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
                                                <span>Catat event inti seperti keluar fullscreen, pindah tab, refresh/tutup halaman, sesi dicabut, dan reset login admin. Histori log bisa dipantau di tab Security Log selama 30 hari terakhir.</span>
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
                                    <div class="cbt-setup-security-actions">
                                        <p class="description">Simpan perubahan security untuk langsung diterapkan pada frontend ujian.</p>
                                        <button type="submit" class="button button-primary button-large cbt-setup-save-button">Simpan Pengaturan Security</button>
                                    </div>
                                </form>
                            </section>
                        </div>
                    </div>

                    <div class="cbt-setup-panel" id="cbt-setup-panel-security-log" data-setup-panel="security-log" role="tabpanel" aria-labelledby="cbt-setup-tab-security-log" hidden>
                        <div class="cbt-setup-security-grid">
                            <section id="cbt-setup-security-log-card" class="cbt-setup-card cbt-setup-security-card cbt-setup-security-log-card">
                                <div class="cbt-setup-card-header">
                                    <div>
                                        <h2>Histori Security Log</h2>
                                        <p>Menampilkan 20 event terbaru dari frontend ujian dan event security penting dari sisi server.</p>
                                    </div>
                                    <span class="cbt-setup-card-chip" data-security-log-status-chip><?php echo $security_log_events_enabled ? 'Logging On' : 'Logging Off'; ?></span>
                                </div>
                                <div class="cbt-setup-security-log-body">
                                    <div class="cbt-setup-security-log-watch-region" data-security-log-watch-region>
                                        <?php CBT_Admin_Security_Page::render_security_log_must_watch_panel($security_log_must_watch_attempts); ?>
                                    </div>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-setup-security-log-manage-form" id="cbt-setup-security-log-manage-form">
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
                                                                            <strong><?php echo esc_html((string) ($security_log['event_label'] ?? $security_log['event_type'] ?? 'Event')); ?></strong>
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
                                        </div>
                                    </form>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="cbt-setup-panel" id="cbt-setup-panel-native" data-setup-panel="native" role="tabpanel" aria-labelledby="cbt-setup-tab-native" hidden>
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
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-native-simulate-form" class="cbt-native-tool-form">
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

                    <div class="cbt-setup-panel" id="cbt-setup-panel-catalog" data-setup-panel="catalog" role="tabpanel" aria-labelledby="cbt-setup-tab-catalog" hidden>
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
                        tabButtons[i].addEventListener('click', function () {
                            setActiveTab(this.getAttribute('data-setup-tab-button') || 'branding', true);
                        });
                    }

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
                    }

                    pickButton.addEventListener('click', function (event) {
                        event.preventDefault();
                        if (typeof wp === 'undefined' || !wp.media) {
                            window.alert('Media Library belum siap. Coba refresh halaman ini.');
                            return;
                        }

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

                                var payload = selection.toJSON();
                                var imageUrl = '';
                                if (payload.sizes && payload.sizes.medium && payload.sizes.medium.url) {
                                    imageUrl = payload.sizes.medium.url;
                                } else if (payload.url) {
                                    imageUrl = payload.url;
                                }
                                setLogoState(parseInt(payload.id, 10) || 0, imageUrl);
                            });
                        }
                        mediaFrame.open();
                    });

                    removeButton.addEventListener('click', function (event) {
                        event.preventDefault();
                        setLogoState(0, '');
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

                        for (index = 0; index < fields.length; index += 1) {
                            fields[index].value = '';
                            fields[index].dispatchEvent(new Event('input', { bubbles: true }));
                            fields[index].dispatchEvent(new Event('change', { bubbles: true }));
                        }

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
                    var watchRegion = card ? card.querySelector('[data-security-log-watch-region]') : null;
                    var tableRegion = card ? card.querySelector('[data-security-log-table-region]') : null;
                    var deleteScopeInput = card ? card.querySelector('[data-security-log-delete-scope]') : null;
                    var deleteSelectedButton = card ? card.querySelector('[data-security-log-submit="selected"]') : null;
                    var deleteAllButton = card ? card.querySelector('[data-security-log-submit="all"]') : null;
                    var autoRefreshTimer = 0;
                    var refreshInFlight = false;
                    var storageKey = 'cbt_setup_security_log_auto_refresh_enabled';
                    var watchSortStorageKey = 'cbt_setup_security_log_watch_sort_mode';
                    var activeWatchFocusAttempt = '';
                    var activeWatchFocusStudent = '';
                    var activeWatchFocusKelas = '';
                    var activeWatchFocusRuang = '';
                    var activeWatchFocusEventType = '';
                    var activeWatchFocusEventLabel = '';
                    var activeWatchSortMode = 'auto';

                    if (!card || !manageForm || !severityFilter || !eventFilter || !deviceFilter || !kelasFilter || !ruangFilter || !studentNameFilter || !autoRefreshToggle || !liveStatus || !securityLogPanel || !tableRegion) {
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

                    function refreshSecurityLogCard() {
                        if (refreshInFlight || !autoRefreshToggle.checked) {
                            return;
                        }

                        if (!isSecurityLogPanelActive() || document.visibilityState === 'hidden') {
                            return;
                        }

                        refreshInFlight = true;
                        setLiveStatus('Memuat log terbaru...', 'loading');

                        var refreshUrl = new URL(window.location.href);
                        refreshUrl.hash = '';
                        refreshUrl.searchParams.set('_cbt_security_refresh', String(Date.now()));

                        fetch(refreshUrl.toString(), {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: {
                                'Cache-Control': 'no-cache'
                            }
                        })
                            .then(function (response) {
                                if (!response.ok) {
                                    throw new Error('Refresh gagal.');
                                }
                                return response.text();
                            })
                            .then(function (html) {
                                var parser = new window.DOMParser();
                                var nextDocument = parser.parseFromString(html, 'text/html');
                                var nextWatchRegion = nextDocument.querySelector('[data-security-log-watch-region]');
                                var nextTableRegion = nextDocument.querySelector('[data-security-log-table-region]');
                                var nextStatusChip = nextDocument.querySelector('[data-security-log-status-chip]');
                                var currentStatusChip = card.querySelector('[data-security-log-status-chip]');

                                if (!nextTableRegion) {
                                    throw new Error('Blok log tidak ditemukan.');
                                }

                                if (watchRegion && nextWatchRegion) {
                                    watchRegion.innerHTML = nextWatchRegion.innerHTML;
                                }

                                tableRegion.innerHTML = nextTableRegion.innerHTML;

                                if (currentStatusChip && nextStatusChip) {
                                    currentStatusChip.textContent = String(nextStatusChip.textContent || '');
                                }

                                if (deleteScopeInput) {
                                    deleteScopeInput.value = '';
                                }

                                syncDynamicFilterOptions();
                                applyMustWatchSort(activeWatchSortMode);
                                applySecurityLogFilters();
                                setLiveStatus('Auto refresh aktif setiap 10 detik.', '');
                            })
                            .catch(function () {
                                setLiveStatus('Auto refresh gagal. Coba refresh halaman.', 'error');
                            })
                            .finally(function () {
                                refreshInFlight = false;
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
                    syncDynamicFilterOptions();
                    syncWatchSortButtons();
                    updateWatchFocusState();
                    applyMustWatchSort(activeWatchSortMode);
                    applySecurityLogFilters();
                    syncAutoRefreshState();

                    severityFilter.addEventListener('change', applySecurityLogFilters);
                    eventFilter.addEventListener('change', applySecurityLogFilters);
                    deviceFilter.addEventListener('change', applySecurityLogFilters);
                    kelasFilter.addEventListener('change', applySecurityLogFilters);
                    ruangFilter.addEventListener('change', applySecurityLogFilters);
                    studentNameFilter.addEventListener('input', applySecurityLogFilters);
                    autoRefreshToggle.addEventListener('change', syncAutoRefreshState);

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
                bindClearIdentityFields();
                bindSecurityLogTools();
            })();
        </script>
