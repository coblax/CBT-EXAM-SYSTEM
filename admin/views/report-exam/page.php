        <style>
            .cbt-report-admin-page {
                max-width: 1120px;
            }
            .cbt-report-admin-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            }
            .cbt-report-admin-hero {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 22px;
                padding: 24px 28px;
                border: 1px solid #d7dbe2;
                border-radius: 22px;
                background:
                    radial-gradient(circle at top right, rgba(34, 113, 177, 0.10), transparent 34%),
                    linear-gradient(135deg, #ffffff 0%, #f6f9fc 100%);
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            }
            .cbt-report-admin-hero-copy {
                max-width: 660px;
            }
            .cbt-report-admin-kicker {
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
            .cbt-report-admin-hero h1 {
                margin: 12px 0 8px;
                font-size: 30px;
                line-height: 1.15;
            }
            .cbt-report-admin-hero p {
                margin: 0;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-report-admin-overview {
                display: grid;
                gap: 10px;
                min-width: 260px;
            }
            .cbt-report-admin-pill {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 34px;
                padding: 0 14px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.92);
                border: 1px solid #d7e4f5;
                color: #1e3a5f;
                font-size: 13px;
                font-weight: 600;
            }
            .cbt-report-admin-tabs {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                padding: 6px;
                border: 1px solid #d9e1ea;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f7fafc 100%);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            }
            .cbt-report-admin-tab {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 42px;
                padding: 0 18px;
                border: 0;
                border-radius: 14px;
                background: transparent;
                color: #4b5563;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
                transition: background-color 140ms ease, color 140ms ease, box-shadow 140ms ease, transform 140ms ease;
            }
            .cbt-report-admin-tab:hover,
            .cbt-report-admin-tab:focus {
                background: #eef4fb;
                color: #153f67;
                outline: none;
            }
            .cbt-report-admin-tab.is-active {
                background: linear-gradient(180deg, #2f7ab9 0%, #1f68a6 100%);
                color: #ffffff;
                box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
            }
            .cbt-report-admin-tab-panels {
                display: grid;
                gap: 18px;
            }
            .cbt-report-admin-tab-panel {
                display: none;
            }
            .cbt-report-admin-tab-panel.is-active {
                display: block;
            }
            .cbt-report-admin-panel {
                padding: 24px;
                border: 1px solid #dcdcde;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
            }
            .cbt-report-admin-panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }
            .cbt-report-admin-panel-header h2 {
                margin: 0 0 6px;
                font-size: 18px;
                line-height: 1.2;
            }
            .cbt-report-admin-panel-header p {
                margin: 0;
                color: #646970;
                line-height: 1.55;
            }
            .cbt-report-admin-chip {
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
            .cbt-report-admin-panel .form-table {
                width: 100%;
                margin: 0;
                border-collapse: separate;
                border-spacing: 0 16px;
            }
            .cbt-report-admin-panel .form-table tr {
                display: grid;
                grid-template-columns: minmax(88px, 104px) minmax(0, 1fr);
                gap: 10px 6px;
                align-items: flex-start;
            }
            .cbt-report-admin-panel .form-table th,
            .cbt-report-admin-panel .form-table td {
                padding: 0;
            }
            .cbt-report-admin-panel .form-table th {
                display: flex;
                align-items: flex-start;
                color: #0f172a;
                font-size: 14px;
                font-weight: 700;
                line-height: 1.5;
                min-height: 0;
                padding-top: 12px;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            .cbt-report-admin-panel .form-table td {
                display: grid;
                gap: 8px;
            }
            .cbt-report-admin-panel .form-table th label {
                color: inherit;
                font-weight: inherit;
            }
            .cbt-report-admin-panel input[type="text"],
            .cbt-report-admin-panel select {
                width: 100%;
                max-width: none;
                box-sizing: border-box;
                min-height: 48px;
                padding: 0 15px;
                border: 1px solid #c9d7e6;
                border-radius: 16px;
                background: #f8fbff;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
                color: #0f172a;
                transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
            }
            .cbt-report-admin-panel select {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                padding-right: 46px;
                cursor: pointer;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6.5L8 10.5L12 6.5' stroke='%23546A85' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 16px center;
                background-size: 16px 16px;
            }
            .cbt-report-admin-panel input[type="text"]:focus,
            .cbt-report-admin-panel select:focus {
                border-color: #2271b1;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
                outline: none;
            }
            .cbt-report-admin-panel .description {
                margin: 0;
                padding-left: 2px;
                color: #64748b;
                font-size: 13px;
                line-height: 1.65;
            }
            .cbt-report-admin-summary {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin: 4px 0 18px;
            }
            .cbt-report-admin-summary-label {
                font-size: 13px;
                font-weight: 700;
                color: #334155;
            }
            .cbt-report-admin-summary-item {
                display: inline-flex;
                align-items: center;
                min-height: 34px;
                padding: 0 14px;
                border-radius: 999px;
                background: #f8fbff;
                border: 1px solid #dbe7f3;
                color: #1e3a5f;
                font-size: 13px;
                font-weight: 600;
            }
            .cbt-report-admin-supervisor-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
            }
            .cbt-report-admin-supervisor-row th {
                padding-top: 34px;
            }
            .cbt-report-admin-field label {
                display: block;
                margin: 0 0 6px;
                font-size: 12px;
                font-weight: 700;
                color: #334155;
            }
            .cbt-report-admin-note {
                margin: 0;
                padding: 14px 16px;
                border: 1px solid #bfdbfe;
                border-radius: 16px;
                background: linear-gradient(180deg, #f8fbff 0%, #eff6ff 100%);
                color: #1d4ed8;
                line-height: 1.6;
            }
            .cbt-report-admin-form-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-top: 18px;
            }
            .cbt-report-admin-form-actions .button {
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
            .cbt-report-admin-form-actions .button-primary {
                border-color: #1d5f99;
                background: linear-gradient(180deg, #2f7ab9 0%, #1f68a6 100%);
                box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
            }
            .cbt-report-admin-form-actions .button-primary:hover,
            .cbt-report-admin-form-actions .button-primary:focus {
                transform: translateY(-1px);
                border-color: #174d7c;
                background: linear-gradient(180deg, #337fbe 0%, #1c629c 100%);
                box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
            }
            .cbt-report-admin-form-actions .button-secondary {
                border-color: #cad9ea;
                background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%);
                color: #1d4f80;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            }
            .cbt-report-admin-form-actions .button-secondary:hover,
            .cbt-report-admin-form-actions .button-secondary:focus {
                transform: translateY(-1px);
                border-color: #a9c3df;
                background: linear-gradient(180deg, #ffffff 0%, #edf5ff 100%);
                color: #153f67;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            }
            .cbt-report-admin-insights {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
            }
            .cbt-report-admin-insight {
                padding: 18px;
                border: 1px solid #dfe7ef;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }
            .cbt-report-admin-insight strong {
                display: block;
                margin-bottom: 6px;
                color: #0f172a;
                font-size: 15px;
            }
            .cbt-report-admin-insight p {
                margin: 0;
                color: #64748b;
                line-height: 1.6;
            }
            .cbt-report-admin-empty {
                padding: 24px;
                border: 1px dashed #d6deea;
                border-radius: 18px;
                background: linear-gradient(180deg, #fbfdff 0%, #f6f9fc 100%);
                color: #526072;
                line-height: 1.7;
            }
            .cbt-report-admin-empty strong {
                display: block;
                margin-bottom: 8px;
                color: #0f172a;
                font-size: 15px;
            }
            .cbt-report-admin-notices {
                display: grid;
                gap: 10px;
            }
            .cbt-report-admin-incident-toolbar {
                display: grid;
                gap: 14px;
                margin-bottom: 18px;
            }
            .cbt-report-admin-incident-toolbar form {
                margin: 0;
            }
            .cbt-report-admin-incident-filter-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr) minmax(0, 1fr) auto;
                gap: 12px;
                align-items: start;
            }
            .cbt-report-admin-incident-filter-grid .cbt-report-admin-field {
                display: grid;
                align-content: start;
                gap: 8px;
            }
            .cbt-report-admin-incident-filter-grid .cbt-report-admin-field .description {
                min-height: 42px;
                padding-left: 0;
            }
            .cbt-report-admin-incident-filter-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                align-self: start;
                padding-top: 26px;
            }
            .cbt-report-admin-incident-filter-actions.is-auto {
                justify-content: flex-end;
            }
            .cbt-report-admin-incident-filter-actions .button {
                min-height: 48px;
                padding: 0 18px;
                border-radius: 16px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .cbt-report-admin-incident-layout {
                display: grid;
                grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
                gap: 18px;
                align-items: start;
            }
            .cbt-report-admin-incident-form-card,
            .cbt-report-admin-incident-table-card {
                padding: 20px;
                border: 1px solid #dfe7ef;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }
            .cbt-report-admin-incident-card-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 14px;
            }
            .cbt-report-admin-incident-card-header h3 {
                margin: 0 0 4px;
                font-size: 16px;
                line-height: 1.25;
            }
            .cbt-report-admin-incident-card-header p {
                margin: 0;
                color: #64748b;
                line-height: 1.55;
            }
            .cbt-report-admin-incident-form-grid {
                display: grid;
                gap: 12px;
            }
            .cbt-report-admin-student-picker {
                position: relative;
            }
            .cbt-report-admin-student-picker-trigger {
                width: 100%;
                min-height: 64px;
                padding: 10px 14px;
                border: 1px solid #c9d7e6;
                border-radius: 16px;
                background: #f8fbff;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                cursor: pointer;
                text-align: left;
                transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
            }
            .cbt-report-admin-student-picker-trigger:hover,
            .cbt-report-admin-student-picker-trigger:focus-visible,
            .cbt-report-admin-student-picker.is-open .cbt-report-admin-student-picker-trigger {
                border-color: #2271b1;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
                outline: none;
            }
            .cbt-report-admin-student-picker-trigger[disabled] {
                cursor: not-allowed;
                opacity: 0.7;
            }
            .cbt-report-admin-student-picker-value {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
                flex: 1 1 auto;
            }
            .cbt-report-admin-student-picker-avatar,
            .cbt-report-admin-student-picker-option-avatar {
                width: 42px;
                height: 42px;
                border-radius: 999px;
                overflow: hidden;
                flex: 0 0 42px;
                border: 1px solid #d5e1ef;
                background: linear-gradient(180deg, #f8fbff 0%, #edf4fb 100%);
            }
            .cbt-report-admin-student-picker-avatar img,
            .cbt-report-admin-student-picker-option-avatar img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .cbt-report-admin-student-picker-copy,
            .cbt-report-admin-student-picker-option-copy {
                min-width: 0;
                display: grid;
                gap: 4px;
            }
            .cbt-report-admin-student-picker-copy strong,
            .cbt-report-admin-student-picker-option-copy strong {
                display: block;
                color: #0f172a;
                font-size: 14px;
                line-height: 1.3;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .cbt-report-admin-student-picker-copy span,
            .cbt-report-admin-student-picker-option-copy span {
                display: block;
                color: #64748b;
                font-size: 12px;
                line-height: 1.45;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .cbt-report-admin-student-picker-chevron {
                color: #64748b;
                font-size: 14px;
                line-height: 1;
                flex: 0 0 auto;
            }
            .cbt-report-admin-student-picker-menu {
                position: absolute;
                top: calc(100% + 8px);
                left: 0;
                right: 0;
                z-index: 40;
                display: none;
                max-height: 320px;
                overflow: auto;
                padding: 8px;
                border: 1px solid #d7e2ee;
                border-radius: 18px;
                background: #ffffff;
                box-shadow: 0 18px 32px rgba(15, 23, 42, 0.14);
            }
            .cbt-report-admin-student-picker.is-open .cbt-report-admin-student-picker-menu {
                display: grid;
                gap: 6px;
            }
            .cbt-report-admin-student-picker-option {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid transparent;
                border-radius: 14px;
                background: transparent;
                display: flex;
                align-items: center;
                gap: 12px;
                cursor: pointer;
                text-align: left;
                transition: background-color 120ms ease, border-color 120ms ease;
            }
            .cbt-report-admin-student-picker-option:hover,
            .cbt-report-admin-student-picker-option:focus-visible {
                border-color: #cfe0f4;
                background: #f8fbff;
                outline: none;
            }
            .cbt-report-admin-student-picker-option.is-selected {
                border-color: #bfd7f5;
                background: #eef6ff;
            }
            .cbt-report-admin-field textarea {
                width: 100%;
                min-height: 120px;
                max-width: none;
                box-sizing: border-box;
                padding: 14px 15px;
                border: 1px solid #c9d7e6;
                border-radius: 16px;
                background: #f8fbff;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
                color: #0f172a;
                resize: vertical;
                transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
            }
            .cbt-report-admin-field textarea:focus {
                border-color: #2271b1;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
                outline: none;
            }
            .cbt-report-admin-incident-summary {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-bottom: 16px;
            }
            .cbt-report-admin-incident-summary-item {
                display: inline-flex;
                align-items: center;
                min-height: 34px;
                padding: 0 12px;
                border-radius: 999px;
                background: #f8fbff;
                border: 1px solid #dbe7f3;
                color: #1e3a5f;
                font-size: 12px;
                font-weight: 700;
            }
            .cbt-report-admin-incident-table-wrap {
                overflow-x: auto;
                border: 1px solid #e4edf6;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            }
            .cbt-report-admin-incident-table {
                width: 100%;
                min-width: 760px;
                border-collapse: separate;
                border-spacing: 0;
            }
            .cbt-report-admin-incident-table th,
            .cbt-report-admin-incident-table td {
                padding: 12px 12px;
                border-bottom: 1px solid #e5edf6;
                vertical-align: top;
                text-align: left;
            }
            .cbt-report-admin-incident-table th {
                color: #334155;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                background: #f8fbff;
                white-space: nowrap;
            }
            .cbt-report-admin-incident-table tbody tr:hover td {
                background: #fbfdff;
            }
            .cbt-report-admin-incident-type-pill,
            .cbt-report-admin-incident-staff-pill,
            .cbt-report-admin-incident-student-meta span {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                white-space: nowrap;
            }
            .cbt-report-admin-incident-type-pill {
                background: #eef4fb;
                color: #1d4f80;
            }
            .cbt-report-admin-incident-staff-pill {
                background: #f4f7fb;
                color: #334155;
                border: 1px solid #dbe5f0;
            }
            .cbt-report-admin-incident-photo {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 42px;
                height: 42px;
                border-radius: 12px;
                overflow: hidden;
                background: linear-gradient(180deg, #f8fbff 0%, #edf4fb 100%);
                border: 1px solid #d5e1ef;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
            }
            .cbt-report-admin-incident-photo img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .cbt-report-admin-incident-student-copy {
                display: grid;
                gap: 6px;
            }
            .cbt-report-admin-incident-student-copy strong {
                display: block;
                color: #0f172a;
                font-size: 14px;
                line-height: 1.35;
            }
            .cbt-report-admin-incident-student-meta {
                display: flex;
                align-items: center;
                gap: 5px;
                flex-wrap: wrap;
            }
            .cbt-report-admin-incident-student-meta span {
                min-height: 24px;
                padding: 0 8px;
                font-size: 11px;
            }
            .cbt-report-admin-incident-student-meta .is-kelas {
                background: #eef4ff;
                border: 1px solid #c9dafd;
                color: #1d4ed8;
            }
            .cbt-report-admin-incident-student-meta .is-ruang {
                background: #effcf6;
                border: 1px solid #bbf7d0;
                color: #047857;
            }
            .cbt-report-admin-incident-time {
                display: grid;
                gap: 4px;
                color: #0f172a;
                line-height: 1.4;
            }
            .cbt-report-admin-incident-time-date {
                font-weight: 700;
                font-size: 13px;
            }
            .cbt-report-admin-incident-time-clock {
                color: #64748b;
                font-size: 12px;
                font-weight: 600;
            }
            .cbt-report-admin-incident-detail {
                color: #334155;
                line-height: 1.55;
                word-break: break-word;
            }
            .cbt-report-admin-incident-actions {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-wrap: nowrap;
                white-space: nowrap;
            }
            .cbt-report-admin-incident-actions .button {
                min-height: 34px;
                padding: 0 10px;
                border-radius: 999px;
                line-height: 1;
                font-size: 11px;
                font-weight: 700;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 5px;
                transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease, background-color 120ms ease, color 120ms ease;
                text-decoration: none;
            }
            .cbt-report-admin-incident-actions .button:hover,
            .cbt-report-admin-incident-actions .button:focus-visible {
                transform: translateY(-1px);
                box-shadow: 0 10px 18px rgba(15, 23, 42, 0.10);
                outline: none;
            }
            .cbt-report-admin-incident-actions .button .dashicons {
                width: 12px;
                height: 12px;
                font-size: 12px;
            }
            .cbt-report-admin-incident-actions .button.is-edit {
                border-color: #bfdbfe;
                background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
                color: #1d4ed8;
            }
            .cbt-report-admin-incident-actions .button.is-edit:hover,
            .cbt-report-admin-incident-actions .button.is-edit:focus-visible {
                border-color: #93c5fd;
                background: linear-gradient(180deg, #e0efff 0%, #cfe4ff 100%);
                color: #1e40af;
            }
            .cbt-report-admin-incident-actions .button.is-delete {
                border-color: #fecaca;
                background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
                color: #dc2626;
            }
            .cbt-report-admin-incident-actions .button.is-delete:hover,
            .cbt-report-admin-incident-actions .button.is-delete:focus-visible {
                border-color: #fca5a5;
                background: linear-gradient(180deg, #fee7e7 0%, #fecfcf 100%);
                color: #b91c1c;
            }
            .cbt-report-admin-incident-delete-form {
                margin: 0;
                display: inline-flex;
            }
            @media (max-width: 1380px) {
                .cbt-report-admin-incident-layout {
                    grid-template-columns: 1fr;
                }
            }
            @media (max-width: 960px) {
                .cbt-report-admin-hero,
                .cbt-report-admin-panel-header {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-report-admin-overview {
                    min-width: 0;
                }
                .cbt-report-admin-incident-filter-grid,
                .cbt-report-admin-supervisor-grid,
                .cbt-report-admin-insights {
                    grid-template-columns: 1fr;
                }
                .cbt-report-admin-student-picker-menu {
                    position: static;
                    margin-top: 8px;
                    box-shadow: none;
                }
            }
            @media (max-width: 860px) {
                .cbt-report-admin-panel .form-table tr {
                    grid-template-columns: 1fr;
                }
                .cbt-report-admin-panel .form-table th,
                .cbt-report-admin-supervisor-row th {
                    padding-top: 0;
                }
                .cbt-report-admin-incident-filter-grid .cbt-report-admin-field .description {
                    min-height: 0;
                }
                .cbt-report-admin-incident-filter-actions {
                    padding-top: 0;
                }
            }
            @media (max-width: 782px) {
                .cbt-report-admin-page {
                    margin-right: 10px;
                }
                .cbt-report-admin-tabs {
                    gap: 8px;
                    padding: 5px;
                }
                .cbt-report-admin-tab {
                    width: 100%;
                }
                .cbt-report-admin-hero,
                .cbt-report-admin-panel {
                    padding: 20px;
                }
            }
        </style>
        <div class="wrap cbt-report-admin-page">
            <div class="cbt-report-admin-shell">
                <section class="cbt-report-admin-hero">
                    <div class="cbt-report-admin-hero-copy">
                        <span class="cbt-report-admin-kicker">Report</span>
                        <h1>CBT Report Exam</h1>
                        <p>Kelola incident report manual per exam dan cetak rekap nilai dari satu halaman report yang tetap rapi untuk admin maupun pengawas.</p>
                    </div>
                    <div class="cbt-report-admin-overview" aria-hidden="true">
                        <span class="cbt-report-admin-pill"><?php echo esc_html(sprintf('%d exam tersedia', count($exam_filter_rows))); ?></span>
                        <span class="cbt-report-admin-pill"><?php echo esc_html(sprintf('%d kelas tersedia', count($kelas_filter_rows))); ?></span>
                        <span class="cbt-report-admin-pill"><?php echo esc_html('2 mode report aktif'); ?></span>
                    </div>
                </section>

                <div class="cbt-report-admin-tabs" role="tablist" aria-label="CBT Report Exam Tabs">
                    <button
                        type="button"
                        class="cbt-report-admin-tab<?php echo $active_report_tab === 'incident-report' ? ' is-active' : ''; ?>"
                        id="cbt-report-admin-tab-incident-report"
                        data-report-tab-button="incident-report"
                        role="tab"
                        aria-selected="<?php echo $active_report_tab === 'incident-report' ? 'true' : 'false'; ?>"
                        aria-controls="cbt-report-admin-panel-incident-report"
                    >
                        Incident Report
                    </button>
                    <button
                        type="button"
                        class="cbt-report-admin-tab<?php echo $active_report_tab === 'filter-export-report' ? ' is-active' : ''; ?>"
                        id="cbt-report-admin-tab-filter-export-report"
                        data-report-tab-button="filter-export-report"
                        role="tab"
                        aria-selected="<?php echo $active_report_tab === 'filter-export-report' ? 'true' : 'false'; ?>"
                        aria-controls="cbt-report-admin-panel-filter-export-report"
                    >
                        Export Report Exam
                    </button>
                </div>

                <?php if ($notice || $error): ?>
                    <div class="cbt-report-admin-notices">
                        <?php if ($notice): ?>
                            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="cbt-report-admin-tab-panels">
                    <section
                        class="cbt-report-admin-tab-panel<?php echo $active_report_tab === 'incident-report' ? ' is-active' : ''; ?>"
                        id="cbt-report-admin-panel-incident-report"
                        data-report-tab-panel="incident-report"
                        role="tabpanel"
                        aria-labelledby="cbt-report-admin-tab-incident-report"
                        <?php echo $active_report_tab === 'incident-report' ? '' : 'hidden'; ?>
                    >
                        <section class="cbt-report-admin-panel">
                            <div class="cbt-report-admin-panel-header">
                                <div>
                                    <h2>Incident Report</h2>
                                    <p>Catat kejadian penting, pelanggaran, atau kondisi khusus peserta secara manual per exam tanpa tercampur dengan Security Log otomatis.</p>
                                </div>
                                <span class="cbt-report-admin-chip">CRUD Manual</span>
                            </div>

                            <div class="cbt-report-admin-incident-toolbar">
                                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" data-incident-filter-form="true">
                                    <input type="hidden" name="page" value="cbt-report-exam" />
                                    <input type="hidden" name="cbt_report_tab" value="incident-report" />
                                    <div class="cbt-report-admin-incident-filter-grid">
                                        <div class="cbt-report-admin-field">
                                            <label for="cbt-incident-exam-id">Exam</label>
                                            <select required id="cbt-incident-exam-id" name="cbt_incident_exam_id" data-incident-filter-input="exam">
                                                <option value="0">Pilih exam</option>
                                                <?php foreach ($exam_filter_rows as $exam_filter_row): ?>
                                                    <?php $incident_exam_id = (int) ($exam_filter_row['id'] ?? 0); ?>
                                                    <option value="<?php echo esc_attr((string) $incident_exam_id); ?>" <?php selected($selected_incident_exam_id, $incident_exam_id); ?>>
                                                        <?php echo esc_html((string) ($exam_filter_row['title'] ?? '-')); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Pilih exam dulu agar form incident hanya bekerja pada konteks exam yang tepat.</p>
                                        </div>
                                        <div class="cbt-report-admin-field">
                                            <label for="cbt-incident-kelas">Kelas</label>
                                            <select id="cbt-incident-kelas" name="cbt_incident_kelas" data-incident-filter-input="kelas" <?php echo $selected_incident_exam_id > 0 ? '' : 'disabled'; ?>>
                                                <option value="">Semua kelas</option>
                                                <?php foreach ($incident_kelas_options as $incident_kelas_option): ?>
                                                    <option value="<?php echo esc_attr($incident_kelas_option); ?>" <?php selected($selected_incident_kelas, $incident_kelas_option); ?>>
                                                        <?php echo esc_html($incident_kelas_option); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Opsional. Filter kelas akan mempersempit daftar peserta dan row incident pada exam ini.</p>
                                        </div>
                                        <div class="cbt-report-admin-field">
                                            <label for="cbt-incident-ruang">Ruang</label>
                                            <select id="cbt-incident-ruang" name="cbt_incident_ruang" data-incident-filter-input="ruang" <?php echo $selected_incident_exam_id > 0 ? '' : 'disabled'; ?>>
                                                <option value="">Semua ruang</option>
                                                <?php foreach ($incident_ruang_options as $incident_ruang_option): ?>
                                                    <option value="<?php echo esc_attr($incident_ruang_option); ?>" <?php selected($selected_incident_ruang, $incident_ruang_option); ?>>
                                                        <?php echo esc_html($incident_ruang_option); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Opsional. Filter ruang ikut membatasi daftar peserta dan incident pada konteks exam ini.</p>
                                        </div>
                                        <div class="cbt-report-admin-incident-filter-actions is-auto">
                                            <a href="<?php echo esc_url($incident_reset_url); ?>" class="button button-secondary">Reset</a>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <?php if ($selected_incident_exam_id <= 0 || empty($incident_exam)): ?>
                                <div class="cbt-report-admin-empty">
                                    <strong>Pilih exam untuk mulai mencatat incident.</strong>
                                    Setelah exam dipilih, form tambah/edit dan tabel incident akan langsung tampil di sini.
                                </div>
                            <?php else: ?>
                                <div class="cbt-report-admin-incident-summary" aria-hidden="true">
                                    <span class="cbt-report-admin-incident-summary-item"><?php echo esc_html('Exam: ' . (string) ($incident_exam['title'] ?? '-')); ?></span>
                                    <span class="cbt-report-admin-incident-summary-item"><?php echo esc_html('Kelas: ' . ($selected_incident_kelas !== '' ? $selected_incident_kelas : 'Semua kelas')); ?></span>
                                    <span class="cbt-report-admin-incident-summary-item"><?php echo esc_html('Ruang: ' . ($selected_incident_ruang !== '' ? $selected_incident_ruang : 'Semua ruang')); ?></span>
                                    <span class="cbt-report-admin-incident-summary-item"><?php echo esc_html(sprintf('Total incident: %d', count($incident_rows))); ?></span>
                                </div>

                                <div class="cbt-report-admin-incident-layout">
                                    <section class="cbt-report-admin-incident-form-card">
                                        <div class="cbt-report-admin-incident-card-header">
                                            <div>
                                                <h3><?php echo $is_editing_incident ? 'Update Incident' : 'Tambah Incident'; ?></h3>
                                                <p><?php echo $is_editing_incident ? 'Perbarui detail insiden pada peserta yang dipilih.' : 'Isi form berikut untuk mencatat insiden manual pada peserta exam ini.'; ?></p>
                                            </div>
                                            <span class="cbt-report-admin-chip"><?php echo $is_editing_incident ? 'Edit Mode' : 'Create Mode'; ?></span>
                                        </div>

                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php if ($is_editing_incident): ?>
                                                <?php wp_nonce_field('cbt_update_exam_incident_' . (int) ($editing_incident['id'] ?? 0)); ?>
                                                <input type="hidden" name="action" value="cbt_update_exam_incident" />
                                                <input type="hidden" name="incident_id" value="<?php echo esc_attr((string) ((int) ($editing_incident['id'] ?? 0))); ?>" />
                                            <?php else: ?>
                                                <?php wp_nonce_field('cbt_save_exam_incident'); ?>
                                                <input type="hidden" name="action" value="cbt_save_exam_incident" />
                                            <?php endif; ?>
                                            <input type="hidden" name="cbt_report_tab" value="incident-report" />
                                            <input type="hidden" name="cbt_incident_exam_id" value="<?php echo esc_attr((string) $selected_incident_exam_id); ?>" />
                                            <input type="hidden" name="cbt_incident_kelas" value="<?php echo esc_attr($selected_incident_kelas); ?>" />
                                            <input type="hidden" name="cbt_incident_ruang" value="<?php echo esc_attr($selected_incident_ruang); ?>" />

                                            <div class="cbt-report-admin-incident-form-grid">
                                                <div class="cbt-report-admin-field">
                                                    <label for="cbt-incident-student-picker-trigger">Peserta</label>
                                                    <div
                                                        class="cbt-report-admin-student-picker"
                                                        data-incident-student-picker="true"
                                                        data-placeholder-name="<?php echo esc_attr(!empty($incident_student_rows) ? 'Pilih peserta' : 'Tidak ada peserta sesuai filter'); ?>"
                                                        data-placeholder-meta="<?php echo esc_attr(!empty($incident_student_rows) ? 'Foto dan identitas peserta akan tampil di sini.' : 'Coba ubah filter exam, kelas, atau ruang untuk memuat peserta.'); ?>"
                                                        data-placeholder-foto="<?php echo esc_attr($incident_student_placeholder_photo); ?>"
                                                    >
                                                        <input type="hidden" id="cbt-incident-student-id" name="student_id" value="<?php echo esc_attr((string) $incident_form_student_id); ?>" data-incident-student-input="true" />
                                                        <button
                                                            type="button"
                                                            id="cbt-incident-student-picker-trigger"
                                                            class="cbt-report-admin-student-picker-trigger"
                                                            data-incident-student-trigger="true"
                                                            aria-haspopup="listbox"
                                                            aria-expanded="false"
                                                            aria-controls="cbt-incident-student-options"
                                                            <?php echo !empty($incident_student_rows) ? '' : 'disabled'; ?>
                                                        >
                                                            <span class="cbt-report-admin-student-picker-value">
                                                                <span class="cbt-report-admin-student-picker-avatar">
                                                                    <img src="<?php echo esc_url($incident_student_picker_photo); ?>" alt="" data-incident-student-photo="true" />
                                                                </span>
                                                                <span class="cbt-report-admin-student-picker-copy">
                                                                    <strong data-incident-student-name="true"><?php echo esc_html($incident_student_picker_name); ?></strong>
                                                                    <span data-incident-student-meta="true"><?php echo esc_html($incident_student_picker_meta); ?></span>
                                                                </span>
                                                            </span>
                                                            <span class="cbt-report-admin-student-picker-chevron" aria-hidden="true">▼</span>
                                                        </button>
                                                        <?php if (!empty($incident_student_rows)): ?>
                                                            <div class="cbt-report-admin-student-picker-menu" id="cbt-incident-student-options" data-incident-student-menu="true" role="listbox" tabindex="-1">
                                                                <?php foreach ($incident_student_rows as $incident_student_row): ?>
                                                                    <?php
                                                                    $incident_student_id = (int) ($incident_student_row['id'] ?? 0);
                                                                    $incident_student_name = (string) ($incident_student_row['name'] ?? '-');
                                                                    $incident_student_kelas_label = !empty($incident_student_row['kelas']) ? 'K: ' . (string) $incident_student_row['kelas'] : '';
                                                                    $incident_student_ruang_label = !empty($incident_student_row['ruang']) ? 'R: ' . (string) $incident_student_row['ruang'] : '';
                                                                    $incident_student_meta = array_filter([$incident_student_kelas_label, $incident_student_ruang_label]);
                                                                    ?>
                                                                    <button
                                                                        type="button"
                                                                        class="cbt-report-admin-student-picker-option<?php echo $incident_form_student_id === $incident_student_id ? ' is-selected' : ''; ?>"
                                                                        data-incident-student-option="true"
                                                                        data-student-id="<?php echo esc_attr((string) $incident_student_id); ?>"
                                                                        data-student-name="<?php echo esc_attr($incident_student_name); ?>"
                                                                        data-student-kelas="<?php echo esc_attr((string) ($incident_student_row['kelas'] ?? '')); ?>"
                                                                        data-student-ruang="<?php echo esc_attr((string) ($incident_student_row['ruang'] ?? '')); ?>"
                                                                        data-student-foto="<?php echo esc_attr((string) ($incident_student_row['foto'] ?? $incident_student_placeholder_photo)); ?>"
                                                                        role="option"
                                                                        aria-selected="<?php echo $incident_form_student_id === $incident_student_id ? 'true' : 'false'; ?>"
                                                                    >
                                                                        <span class="cbt-report-admin-student-picker-option-avatar">
                                                                            <img src="<?php echo esc_url((string) ($incident_student_row['foto'] ?? $incident_student_placeholder_photo)); ?>" alt="" />
                                                                        </span>
                                                                        <span class="cbt-report-admin-student-picker-option-copy">
                                                                            <strong><?php echo esc_html($incident_student_name); ?></strong>
                                                                            <span><?php echo esc_html(!empty($incident_student_meta) ? implode(' • ', $incident_student_meta) : ((string) ($incident_student_row['username'] ?? ''))); ?></span>
                                                                        </span>
                                                                    </button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="description">Daftar peserta mengikuti target kelas exam dan filter ruang aktif, jadi kasus tidak hadir tetap bisa dicatat walau belum punya attempt.</p>
                                                </div>

                                                <div class="cbt-report-admin-field">
                                                    <label for="cbt-incident-type">Jenis Insiden</label>
                                                    <select required id="cbt-incident-type" name="incident_type" data-incident-type-field="true">
                                                        <option value="">Pilih jenis insiden</option>
                                                        <?php foreach (CBT_Incident_Report::incident_type_definitions() as $incident_type_key => $incident_type_label): ?>
                                                            <option value="<?php echo esc_attr($incident_type_key); ?>" <?php selected($incident_form_type, $incident_type_key); ?>>
                                                                <?php echo esc_html($incident_type_label); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="cbt-report-admin-field">
                                                    <label for="cbt-incident-notes">Keterangan</label>
                                                    <select id="cbt-incident-notes" name="notes" data-incident-notes-field="true" data-incident-notes-current="<?php echo esc_attr($incident_form_note_value); ?>" <?php echo $incident_form_type !== '' ? '' : 'disabled'; ?>>
                                                        <option value=""><?php echo $incident_form_type !== '' ? 'Pilih keterangan' : 'Pilih jenis insiden dulu'; ?></option>
                                                        <?php foreach ($incident_form_note_options as $incident_note_option): ?>
                                                            <option value="<?php echo esc_attr($incident_note_option); ?>" <?php selected($incident_form_note_value, $incident_note_option); ?>>
                                                                <?php echo esc_html($incident_note_option); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        <option value="<?php echo esc_attr(CBT_Incident_Report::custom_note_option_value()); ?>" <?php selected($incident_form_note_value, CBT_Incident_Report::custom_note_option_value()); ?>>
                                                            <?php echo esc_html(CBT_Incident_Report::custom_note_option_label()); ?>
                                                        </option>
                                                    </select>
                                                    <div class="cbt-report-admin-field" data-incident-notes-custom-wrap="true" <?php echo $incident_form_note_value === CBT_Incident_Report::custom_note_option_value() ? '' : 'hidden'; ?>>
                                                        <label for="cbt-incident-notes-custom">Keterangan manual</label>
                                                        <input type="text" id="cbt-incident-notes-custom" name="notes_custom" value="<?php echo esc_attr($incident_form_note_custom); ?>" data-incident-notes-custom-field="true" placeholder="Tulis keterangan lain jika belum ada di daftar." />
                                                    </div>
                                                    <p class="description">Keterangan utama dipilih dari daftar preset agar pencatatan incident tetap konsisten.</p>
                                                </div>

                                                <div class="cbt-report-admin-field">
                                                    <label for="cbt-incident-staff-user-id">Petugas</label>
                                                    <input type="hidden" name="staff_user_id" value="<?php echo esc_attr((string) $incident_form_staff_user_id); ?>" />
                                                    <select required id="cbt-incident-staff-user-id" disabled>
                                                        <option value="<?php echo esc_attr((string) $incident_form_staff_user_id); ?>">
                                                            <?php echo esc_html(!empty($incident_current_staff['label']) ? (string) $incident_current_staff['label'] : 'User login saat ini tidak valid'); ?>
                                                        </option>
                                                    </select>
                                                    <p class="description">Field ini dikunci otomatis berdasarkan akun yang sedang login.</p>
                                                </div>
                                            </div>

                                            <div class="cbt-report-admin-form-actions">
                                                <button class="button button-primary" type="submit" <?php echo (!empty($incident_student_rows) && !empty($incident_current_staff)) ? '' : 'disabled'; ?>>
                                                    <?php echo $is_editing_incident ? 'Update Incident' : 'Simpan Incident'; ?>
                                                </button>
                                                <?php if ($is_editing_incident): ?>
                                                    <a href="<?php echo esc_url(add_query_arg(['page' => 'cbt-report-exam', 'cbt_report_tab' => 'incident-report', 'cbt_incident_exam_id' => $selected_incident_exam_id, 'cbt_incident_kelas' => $selected_incident_kelas, 'cbt_incident_ruang' => $selected_incident_ruang], admin_url('admin.php'))); ?>" class="button button-secondary">Batal Edit</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </section>

                                    <section class="cbt-report-admin-incident-table-card">
                                        <div class="cbt-report-admin-incident-card-header">
                                            <div>
                                                <h3>Daftar Incident</h3>
                                                <p>Semua row diurutkan dari waktu insiden terbaru agar kejadian paling baru langsung mudah dipantau.</p>
                                            </div>
                                            <span class="cbt-report-admin-chip"><?php echo esc_html(sprintf('%d row', count($incident_rows))); ?></span>
                                        </div>

                                        <div class="cbt-report-admin-incident-table-wrap">
                                            <table class="cbt-report-admin-incident-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:64px;">Foto</th>
                                                        <th>Peserta</th>
                                                        <th style="width:158px;">Jenis Insiden</th>
                                                        <th style="width:104px;">Waktu</th>
                                                        <th>Keterangan</th>
                                                        <th style="width:110px;">Petugas</th>
                                                        <th style="width:152px;">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($incident_rows)): ?>
                                                        <?php
                                                        echo CBT_Admin_UI_Helper::render_table_empty_state(7, [
                                                            'title' => 'Belum ada incident',
                                                            'message' => 'Belum ada incident yang dicatat untuk exam dan filter ini. Catatan baru akan tampil setelah disimpan dari form di atas.',
                                                            'action_label' => 'Reset Filter',
                                                            'action_url' => admin_url('admin.php?page=cbt-report-exam&cbt_report_tab=incident-report'),
                                                            'action_class' => 'button button-secondary cbt-admin-btn--secondary',
                                                        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                        ?>
                                                    <?php else: ?>
                                                        <?php foreach ($incident_rows as $incident_row): ?>
                                                            <?php
                                                            $incident_id = (int) ($incident_row['id'] ?? 0);
                                                            $incident_student_id = (int) ($incident_row['student_id'] ?? 0);
                                                            $incident_student_photo = CBT_Admin_Report_Exam_Service::resolve_student_default_photo('siswa_cbt', (string) get_user_meta($incident_student_id, 'foto', true));
                                                            $incident_datetime = CBT_Admin_Report_Exam_Service::format_report_incident_datetime((string) ($incident_row['incident_at'] ?? ''));
                                                            $incident_datetime_parts = $incident_datetime !== '-' ? explode(' ', $incident_datetime, 2) : ['-', ''];
                                                            $incident_date = (string) ($incident_datetime_parts[0] ?? '-');
                                                            $incident_time = (string) ($incident_datetime_parts[1] ?? '');
                                                            $incident_notes_text = (string) (($incident_row['notes'] ?? '') !== '' ? $incident_row['notes'] : '-');
                                                            $incident_edit_url = add_query_arg(
                                                                [
                                                                    'page' => 'cbt-report-exam',
                                                                    'cbt_report_tab' => 'incident-report',
                                                                    'cbt_incident_exam_id' => $selected_incident_exam_id,
                                                                    'cbt_incident_kelas' => $selected_incident_kelas,
                                                                    'cbt_incident_ruang' => $selected_incident_ruang,
                                                                    'cbt_incident_edit_id' => $incident_id,
                                                                ],
                                                                admin_url('admin.php')
                                                            );
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <span class="cbt-report-admin-incident-photo">
                                                                        <img src="<?php echo esc_url($incident_student_photo); ?>" alt="" />
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="cbt-report-admin-incident-student-copy">
                                                                        <strong><?php echo esc_html((string) ($incident_row['student_name_snapshot'] ?? '-')); ?></strong>
                                                                        <?php if (!empty($incident_row['student_kelas_snapshot']) || !empty($incident_row['student_ruang_snapshot'])): ?>
                                                                            <div class="cbt-report-admin-incident-student-meta">
                                                                                <?php if (!empty($incident_row['student_kelas_snapshot'])): ?>
                                                                                    <span class="is-kelas"><strong>K:</strong>&nbsp;<?php echo esc_html((string) $incident_row['student_kelas_snapshot']); ?></span>
                                                                                <?php endif; ?>
                                                                                <?php if (!empty($incident_row['student_ruang_snapshot'])): ?>
                                                                                    <span class="is-ruang"><strong>R:</strong>&nbsp;<?php echo esc_html((string) $incident_row['student_ruang_snapshot']); ?></span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td><span class="cbt-report-admin-incident-type-pill"><?php echo esc_html((string) ($incident_row['incident_type_label'] ?? '-')); ?></span></td>
                                                                <td>
                                                                    <div class="cbt-report-admin-incident-time">
                                                                        <span class="cbt-report-admin-incident-time-date"><?php echo esc_html($incident_date); ?></span>
                                                                        <?php if ($incident_time !== ''): ?>
                                                                            <span class="cbt-report-admin-incident-time-clock"><?php echo esc_html($incident_time); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td><div class="cbt-report-admin-incident-detail"><?php echo esc_html($incident_notes_text); ?></div></td>
                                                                <td><span class="cbt-report-admin-incident-staff-pill"><?php echo esc_html((string) ($incident_row['staff_name_snapshot'] ?? '-')); ?></span></td>
                                                                <td>
                                                                    <div class="cbt-report-admin-incident-actions">
                                                                        <a href="<?php echo esc_url($incident_edit_url); ?>" class="button button-secondary is-edit">
                                                                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                                                            <span>Edit</span>
                                                                        </a>
                                                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-report-admin-incident-delete-form">
                                                                            <?php wp_nonce_field('cbt_delete_exam_incident_' . $incident_id); ?>
                                                                            <input type="hidden" name="action" value="cbt_delete_exam_incident" />
                                                                            <input type="hidden" name="incident_id" value="<?php echo esc_attr((string) $incident_id); ?>" />
                                                                            <input type="hidden" name="cbt_report_tab" value="incident-report" />
                                                                            <input type="hidden" name="cbt_incident_exam_id" value="<?php echo esc_attr((string) $selected_incident_exam_id); ?>" />
                                                                            <input type="hidden" name="cbt_incident_kelas" value="<?php echo esc_attr($selected_incident_kelas); ?>" />
                                                                            <input type="hidden" name="cbt_incident_ruang" value="<?php echo esc_attr($selected_incident_ruang); ?>" />
                                                                            <button type="submit" class="button button-secondary is-delete" onclick="return window.confirm('Hapus incident ini?')">
                                                                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                                                                <span>Hapus</span>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                </div>
                            <?php endif; ?>
                        </section>
                    </section>

                    <section
                        class="cbt-report-admin-tab-panel<?php echo $active_report_tab === 'filter-export-report' ? ' is-active' : ''; ?>"
                        id="cbt-report-admin-panel-filter-export-report"
                        data-report-tab-panel="filter-export-report"
                        role="tabpanel"
                        aria-labelledby="cbt-report-admin-tab-filter-export-report"
                        <?php echo $active_report_tab === 'filter-export-report' ? '' : 'hidden'; ?>
                    >
                        <section class="cbt-report-admin-panel">
                            <div class="cbt-report-admin-panel-header">
                                <div>
                                    <h2>Export Report Exam</h2>
                                    <p>Pilih exam, tentukan kelas bila perlu, lalu lengkapi data petugas yang akan muncul pada bagian tanda tangan report.</p>
                                </div>
                                <span class="cbt-report-admin-chip">Print / Save as PDF</span>
                            </div>

                            <div class="cbt-report-admin-summary" aria-hidden="true">
                                <span class="cbt-report-admin-summary-label">Ringkasan:</span>
                                <span class="cbt-report-admin-summary-item"><?php echo esc_html('Exam: ' . $selected_exam_label); ?></span>
                                <span class="cbt-report-admin-summary-item"><?php echo esc_html('Kelas: ' . ($selected_kelas !== '' ? $selected_kelas : 'Semua kelas')); ?></span>
                                <span class="cbt-report-admin-summary-item"><?php echo esc_html('Scope: ' . ($is_admin_scope ? 'Admin penuh' : 'Guru terbatas')); ?></span>
                            </div>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('cbt_export_exam_report_pdf'); ?>
                                <input type="hidden" name="action" value="cbt_export_exam_report_pdf" />
                                <input type="hidden" name="cbt_report_tab" value="filter-export-report" />
                                <table class="form-table" role="presentation">
                                    <tbody>
                                    <tr>
                                        <th><label for="cbt-report-exam-id">Exam</label></th>
                                        <td>
                                            <select required id="cbt-report-exam-id" name="cbt_exam_id">
                                                <option value="0">Pilih exam</option>
                                                <?php foreach ($exam_filter_rows as $exam_filter_row): ?>
                                                    <?php $exam_id = (int) ($exam_filter_row['id'] ?? 0); ?>
                                                    <option value="<?php echo $exam_id; ?>" <?php selected($selected_exam_id, $exam_id); ?>>
                                                        <?php echo esc_html((string) ($exam_filter_row['title'] ?? '-')); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Pilih exam yang nilainya akan direkap ke dalam report PDF.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="cbt-report-kelas">Kelas</label></th>
                                        <td>
                                            <select id="cbt-report-kelas" name="cbt_result_kelas">
                                                <option value="">Semua kelas</option>
                                                <?php foreach ($kelas_filter_rows as $kelas_filter_row): ?>
                                                    <option value="<?php echo esc_attr($kelas_filter_row); ?>" <?php selected($selected_kelas, $kelas_filter_row); ?>>
                                                        <?php echo esc_html($kelas_filter_row); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Opsional. Jika kosong, report akan memuat seluruh kelas yang sesuai data hasil exam.</p>
                                        </td>
                                    </tr>
                                    <?php for ($idx = 1; $idx <= 3; $idx++): ?>
                                        <?php
                                        $is_required = ($idx === 1);
                                        $label_suffix = $is_required ? 'wajib' : 'opsional';
                                        $supervisor = (array) ($supervisor_inputs[$idx] ?? []);
                                        ?>
                                        <tr class="cbt-report-admin-supervisor-row">
                                            <th><?php echo esc_html('Petugas ' . $idx); ?></th>
                                            <td>
                                                <div class="cbt-report-admin-supervisor-grid">
                                                    <div class="cbt-report-admin-field">
                                                        <label for="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-name'); ?>"><?php echo esc_html('Nama (' . $label_suffix . ')'); ?></label>
                                                        <input <?php echo $is_required ? 'required' : ''; ?> type="text" id="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-name'); ?>" name="<?php echo esc_attr('supervisor_' . $idx . '_name'); ?>" class="regular-text" value="<?php echo esc_attr((string) ($supervisor['name'] ?? '')); ?>" />
                                                    </div>
                                                    <div class="cbt-report-admin-field">
                                                        <label for="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-nip'); ?>"><?php echo esc_html('NIP (' . $label_suffix . ')'); ?></label>
                                                        <input <?php echo $is_required ? 'required' : ''; ?> type="text" id="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-nip'); ?>" name="<?php echo esc_attr('supervisor_' . $idx . '_nip'); ?>" class="regular-text" value="<?php echo esc_attr((string) ($supervisor['nip'] ?? '')); ?>" />
                                                    </div>
                                                    <div class="cbt-report-admin-field">
                                                        <label for="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-role'); ?>"><?php echo esc_html('Jabatan (' . $label_suffix . ')'); ?></label>
                                                        <select <?php echo $is_required ? 'required' : ''; ?> id="<?php echo esc_attr('cbt-report-supervisor-' . $idx . '-role'); ?>" name="<?php echo esc_attr('supervisor_' . $idx . '_role'); ?>" class="regular-text">
                                                            <option value="">Pilih jabatan</option>
                                                            <?php foreach ($role_options as $role_option): ?>
                                                                <option value="<?php echo esc_attr($role_option); ?>" <?php selected((string) ($supervisor['role'] ?? ''), $role_option); ?>>
                                                                    <?php echo esc_html($role_option); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <?php if (!$is_required): ?>
                                                    <p class="description"><?php echo esc_html('Jika salah satu field Petugas ' . $idx . ' diisi, maka semua field Petugas ' . $idx . ' wajib diisi.'); ?></p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                    </tbody>
                                </table>
                                <p class="cbt-report-admin-note">Export dilakukan melalui browser dengan mode print-ready. Pastikan minimal Petugas 1 diisi lengkap agar bagian tanda tangan pada report tidak kosong.</p>
                                <div class="cbt-report-admin-form-actions">
                                    <button class="button button-primary" type="submit">Export PDF (Print-Ready)</button>
                                    <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary">Reset Form</a>
                                </div>
                            </form>
                        </section>
                    </section>
                </div>

                <section class="cbt-report-admin-insights" aria-hidden="true">
                    <div class="cbt-report-admin-insight">
                        <strong>Incident manual</strong>
                        <p>Pengawas bisa mencatat pelanggaran, keterlambatan, sakit, atau gangguan teknis langsung per peserta dan per exam.</p>
                    </div>
                    <div class="cbt-report-admin-insight">
                        <strong>Filter fleksibel</strong>
                        <p>Incident report dan export report sama-sama bisa difokuskan ke exam tertentu, lalu dipersempit lagi ke kelas atau ruang bila diperlukan.</p>
                    </div>
                    <div class="cbt-report-admin-insight">
                        <strong>Export tetap aman</strong>
                        <p>Tab Export Report Exam tetap mempertahankan alur print-ready dan area tanda tangan petugas seperti sebelumnya.</p>
                    </div>
                </section>
            </div>
        </div>
        <script>
            (function () {
                var tabButtons = document.querySelectorAll('[data-report-tab-button]');
                var tabPanels = document.querySelectorAll('[data-report-tab-panel]');
                var validTabs = ['incident-report', 'filter-export-report'];
                var incidentTypeField = document.querySelector('[data-incident-type-field]');
                var incidentNotesField = document.querySelector('[data-incident-notes-field]');
                var incidentNotesCustomWrap = document.querySelector('[data-incident-notes-custom-wrap]');
                var incidentNotesCustomField = document.querySelector('[data-incident-notes-custom-field]');
                var incidentNoteOptionsByType = <?php echo wp_json_encode(CBT_Incident_Report::incident_note_definitions()); ?>;
                var incidentCustomNoteValue = <?php echo wp_json_encode(CBT_Incident_Report::custom_note_option_value()); ?>;
                var incidentCustomNoteLabel = <?php echo wp_json_encode(CBT_Incident_Report::custom_note_option_label()); ?>;
                var incidentFilterForm = document.querySelector('[data-incident-filter-form]');
                var incidentFilterExam = incidentFilterForm ? incidentFilterForm.querySelector('[data-incident-filter-input=\"exam\"]') : null;
                var incidentFilterKelas = incidentFilterForm ? incidentFilterForm.querySelector('[data-incident-filter-input=\"kelas\"]') : null;
                var incidentFilterRuang = incidentFilterForm ? incidentFilterForm.querySelector('[data-incident-filter-input=\"ruang\"]') : null;
                var incidentStudentPicker = document.querySelector('[data-incident-student-picker]');
                var incidentStudentInput = incidentStudentPicker ? incidentStudentPicker.querySelector('[data-incident-student-input]') : null;
                var incidentStudentTrigger = incidentStudentPicker ? incidentStudentPicker.querySelector('[data-incident-student-trigger]') : null;
                var incidentStudentMenu = incidentStudentPicker ? incidentStudentPicker.querySelector('[data-incident-student-menu]') : null;
                var incidentStudentName = incidentStudentPicker ? incidentStudentPicker.querySelector('[data-incident-student-name]') : null;
                var incidentStudentMeta = incidentStudentPicker ? incidentStudentPicker.querySelector('[data-incident-student-meta]') : null;
                var incidentStudentPhoto = incidentStudentPicker ? incidentStudentPicker.querySelector('[data-incident-student-photo]') : null;
                var isIncidentFilterSubmitting = false;

                function normalizeReportTab(tab) {
                    var normalized = String(tab || '').trim().toLowerCase();
                    return validTabs.indexOf(normalized) >= 0 ? normalized : 'incident-report';
                }

                function buildOption(value, label, selectedValue) {
                    var option = document.createElement('option');
                    option.value = String(value || '');
                    option.textContent = String(label || '');
                    option.selected = String(selectedValue || '') === String(value || '');
                    return option;
                }

                function syncIncidentNotesField(selectedNoteValue) {
                    if (!incidentTypeField || !incidentNotesField) {
                        return;
                    }

                    var typeValue = String(incidentTypeField.value || '');
                    var normalizedSelected = String(selectedNoteValue || '');
                    var options = Object.prototype.hasOwnProperty.call(incidentNoteOptionsByType, typeValue)
                        ? incidentNoteOptionsByType[typeValue]
                        : [];

                    incidentNotesField.innerHTML = '';
                    incidentNotesField.appendChild(buildOption('', typeValue !== '' ? 'Pilih keterangan' : 'Pilih jenis insiden dulu', normalizedSelected));

                    if (typeValue === '') {
                        incidentNotesField.disabled = true;
                    } else {
                        incidentNotesField.disabled = false;
                        for (var idx = 0; idx < options.length; idx += 1) {
                            incidentNotesField.appendChild(buildOption(options[idx], options[idx], normalizedSelected));
                        }
                        incidentNotesField.appendChild(buildOption(incidentCustomNoteValue, incidentCustomNoteLabel, normalizedSelected));
                    }

                    syncIncidentNotesCustomState();
                }

                function syncIncidentNotesCustomState() {
                    if (!incidentNotesField || !incidentNotesCustomWrap || !incidentNotesCustomField) {
                        return;
                    }

                    var isCustom = String(incidentNotesField.value || '') === String(incidentCustomNoteValue || '');
                    incidentNotesCustomWrap.hidden = !isCustom;
                    incidentNotesCustomField.required = isCustom;
                    if (!isCustom) {
                        incidentNotesCustomField.value = '';
                    }
                }

                function closeIncidentStudentPicker() {
                    if (!incidentStudentPicker || !incidentStudentTrigger) {
                        return;
                    }

                    incidentStudentPicker.classList.remove('is-open');
                    incidentStudentTrigger.setAttribute('aria-expanded', 'false');
                }

                function syncIncidentStudentSelection(option) {
                    if (!incidentStudentPicker || !incidentStudentInput || !incidentStudentName || !incidentStudentMeta || !incidentStudentPhoto) {
                        return;
                    }

                    var selectedOption = option || null;
                    var optionButtons = incidentStudentPicker.querySelectorAll('[data-incident-student-option]');
                    var placeholderName = incidentStudentPicker.getAttribute('data-placeholder-name') || 'Pilih peserta';
                    var placeholderMeta = incidentStudentPicker.getAttribute('data-placeholder-meta') || '';
                    var placeholderPhoto = incidentStudentPicker.getAttribute('data-placeholder-foto') || '';

                    if (!selectedOption && incidentStudentInput.value) {
                        for (var idx = 0; idx < optionButtons.length; idx += 1) {
                            if (String(optionButtons[idx].getAttribute('data-student-id') || '') === String(incidentStudentInput.value || '')) {
                                selectedOption = optionButtons[idx];
                                break;
                            }
                        }
                    }

                    if (selectedOption) {
                        var selectedName = selectedOption.getAttribute('data-student-name') || placeholderName;
                        var selectedKelas = selectedOption.getAttribute('data-student-kelas') || '';
                        var selectedRuang = selectedOption.getAttribute('data-student-ruang') || '';
                        var selectedMetaParts = [];

                        if (selectedKelas) {
                            selectedMetaParts.push('K: ' + selectedKelas);
                        }
                        if (selectedRuang) {
                            selectedMetaParts.push('R: ' + selectedRuang);
                        }

                        incidentStudentInput.value = selectedOption.getAttribute('data-student-id') || '0';
                        incidentStudentName.textContent = selectedName;
                        incidentStudentMeta.textContent = selectedMetaParts.length ? selectedMetaParts.join(' • ') : placeholderMeta;
                        incidentStudentPhoto.src = selectedOption.getAttribute('data-student-foto') || placeholderPhoto;
                    } else {
                        incidentStudentInput.value = '0';
                        incidentStudentName.textContent = placeholderName;
                        incidentStudentMeta.textContent = placeholderMeta;
                        incidentStudentPhoto.src = placeholderPhoto;
                    }

                    for (var optionIndex = 0; optionIndex < optionButtons.length; optionIndex += 1) {
                        var isSelected = !!selectedOption && optionButtons[optionIndex] === selectedOption;
                        optionButtons[optionIndex].classList.toggle('is-selected', isSelected);
                        optionButtons[optionIndex].setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    }
                }

                function setActiveReportTab(tabName, updateUrl) {
                    var activeTab = normalizeReportTab(tabName);
                    var index = 0;

                    for (index = 0; index < tabButtons.length; index += 1) {
                        var button = tabButtons[index];
                        var isActive = String(button.getAttribute('data-report-tab-button') || '') === activeTab;
                        button.classList.toggle('is-active', isActive);
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    }

                    for (index = 0; index < tabPanels.length; index += 1) {
                        var panel = tabPanels[index];
                        var isPanelActive = String(panel.getAttribute('data-report-tab-panel') || '') === activeTab;
                        panel.classList.toggle('is-active', isPanelActive);
                        panel.hidden = !isPanelActive;
                    }

                    if (updateUrl && typeof window.history.replaceState === 'function') {
                        var nextUrl = new URL(window.location.href);
                        nextUrl.searchParams.set('cbt_report_tab', activeTab);
                        window.history.replaceState({}, '', nextUrl.toString());
                    }
                }

                for (var i = 0; i < tabButtons.length; i += 1) {
                    tabButtons[i].addEventListener('click', function () {
                        setActiveReportTab(this.getAttribute('data-report-tab-button') || 'incident-report', true);
                    });
                }

                if (incidentTypeField && incidentNotesField) {
                    incidentTypeField.addEventListener('change', function () {
                        syncIncidentNotesField('');
                    });
                    incidentNotesField.addEventListener('change', syncIncidentNotesCustomState);
                    syncIncidentNotesField(String(incidentNotesField.getAttribute('data-incident-notes-current') || ''));
                }

                if (incidentFilterForm && incidentFilterExam) {
                    incidentFilterExam.addEventListener('change', function () {
                        if (incidentFilterKelas) {
                            incidentFilterKelas.value = '';
                        }
                        if (incidentFilterRuang) {
                            incidentFilterRuang.value = '';
                        }
                        if (!isIncidentFilterSubmitting) {
                            isIncidentFilterSubmitting = true;
                            incidentFilterForm.submit();
                        }
                    });
                }

                if (incidentFilterForm && incidentFilterKelas) {
                    incidentFilterKelas.addEventListener('change', function () {
                        if (incidentFilterRuang) {
                            incidentFilterRuang.value = '';
                        }
                        if (!isIncidentFilterSubmitting) {
                            isIncidentFilterSubmitting = true;
                            incidentFilterForm.submit();
                        }
                    });
                }

                if (incidentFilterForm && incidentFilterRuang) {
                    incidentFilterRuang.addEventListener('change', function () {
                        if (!isIncidentFilterSubmitting) {
                            isIncidentFilterSubmitting = true;
                            incidentFilterForm.submit();
                        }
                    });
                }

                if (incidentStudentPicker && incidentStudentTrigger && incidentStudentMenu) {
                    incidentStudentTrigger.addEventListener('click', function () {
                        var shouldOpen = !incidentStudentPicker.classList.contains('is-open');
                        closeIncidentStudentPicker();
                        if (shouldOpen) {
                            incidentStudentPicker.classList.add('is-open');
                            incidentStudentTrigger.setAttribute('aria-expanded', 'true');
                        }
                    });

                    incidentStudentMenu.addEventListener('click', function (event) {
                        var target = event.target;
                        var option = target && target.closest ? target.closest('[data-incident-student-option]') : null;
                        if (!option) {
                            return;
                        }

                        syncIncidentStudentSelection(option);
                        closeIncidentStudentPicker();
                    });

                    document.addEventListener('click', function (event) {
                        if (!incidentStudentPicker.contains(event.target)) {
                            closeIncidentStudentPicker();
                        }
                    });

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            closeIncidentStudentPicker();
                        }
                    });

                    syncIncidentStudentSelection(null);
                }

                setActiveReportTab('<?php echo esc_js($active_report_tab); ?>', false);
            })();
        </script>
        <?php
