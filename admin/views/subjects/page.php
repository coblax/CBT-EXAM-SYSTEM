        <style>
            .cbt-subject-page {
                max-width: 1180px;
                animation: cbtSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                opacity: 0;
            }
            @keyframes cbtSlideUp {
                0% { opacity: 0; transform: translateY(15px); }
                100% { opacity: 1; transform: translateY(0); }
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
                padding: 28px 36px;
                border: 1px solid rgba(255, 255, 255, 0.6);
                border-radius: 24px;
                background: linear-gradient(145deg, #ffffff 0%, #f0f4f8 100%);
                box-shadow: 
                    0 20px 40px rgba(15, 23, 42, 0.05), 
                    inset 0 2px 0 rgba(255, 255, 255, 0.9);
            }
            .cbt-subject-hero::before {
                content: ''; position: absolute;
                top: -200px; right: -200px; width: 600px; height: 600px;
                background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, transparent 70%);
                border-radius: 50%; pointer-events: none;
            }
            .cbt-subject-hero-copy {
                flex: 1;
                margin-right: 32px;
                position: relative;
                z-index: 2;
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
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
            }
            .cbt-subject-hero h1 {
                margin: 12px 0 8px;
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
            .cbt-subject-pill {
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
                border: 2px solid #e2e8f0;
                border-radius: 14px;
                background: #ffffff;
                color: #64748b;
                font-size: 14px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .cbt-subject-tab:hover,
            .cbt-subject-tab:focus {
                border-color: #cbd5e1;
                background: #f8fafc;
                color: #0f172a;
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
                padding: 32px 36px;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 14px 32px rgba(15, 23, 42, 0.04);
            }
            .cbt-subject-panel.is-active {
                display: block;
            }
            .cbt-subject-panel[data-cbt-subject-panel="list"].is-loading {
                opacity: 0.72;
                transition: opacity 0.18s ease;
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
                color: #0f172a;
                line-height: 1.2;
            }
            .cbt-subject-panel-header p {
                margin: 0;
                color: #64748b;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-subject-chip {
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
                margin: 14px 0 4px;
                padding: 12px;
                border: 1px solid #e3ebf4;
                border-radius: 18px;
                background:
                    radial-gradient(circle at top right, rgba(220, 38, 38, 0.06), transparent 38%),
                    linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
            }
            .cbt-subject-bulk-button.button {
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
            .cbt-subject-bulk-button.button::before {
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
            .cbt-subject-bulk-button.button:hover,
            .cbt-subject-bulk-button.button:focus {
                transform: translateY(-1px);
                outline: none;
            }
            .cbt-subject-bulk-button.button:focus-visible {
                box-shadow:
                    0 0 0 3px rgba(220, 38, 38, 0.14),
                    0 16px 32px rgba(148, 28, 28, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.94);
            }
            .cbt-subject-bulk-button--selected.button {
                border-color: #efcaca;
                background: linear-gradient(180deg, #ffffff 0%, #fff5f5 100%);
                color: #b42318;
            }
            .cbt-subject-bulk-button--selected.button::before {
                background-color: #fff1f1;
            }
            .cbt-subject-bulk-button--selected.button:hover,
            .cbt-subject-bulk-button--selected.button:focus {
                border-color: #e5a5a5;
                background: linear-gradient(180deg, #ffffff 0%, #ffefef 100%);
                color: #991b1b;
                box-shadow:
                    0 16px 30px rgba(185, 28, 28, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.96);
            }
            .cbt-subject-bulk-button--all.button {
                border-color: #d98b8b;
                background: linear-gradient(180deg, #fff6f6 0%, #fee2e2 100%);
                color: #8f1111;
                box-shadow:
                    0 16px 32px rgba(153, 27, 27, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.9);
            }
            .cbt-subject-bulk-button--all.button::before {
                background-color: rgba(185, 28, 28, 0.12);
            }
            .cbt-subject-bulk-button--all.button:hover,
            .cbt-subject-bulk-button--all.button:focus {
                border-color: #c75e5e;
                background: linear-gradient(180deg, #fff1f1 0%, #fecaca 100%);
                color: #7f1d1d;
                box-shadow:
                    0 18px 34px rgba(153, 27, 27, 0.16),
                    inset 0 1px 0 rgba(255, 255, 255, 0.92);
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
                color: #0f172a;
                font-size: 14px;
                font-weight: 700;
            }
            .cbt-subject-panel .form-table td {
                padding: 0;
                vertical-align: top;
            }
            .cbt-subject-panel .form-table th label,
            .cbt-subject-panel .form-table td > label {
                color: inherit;
                font-weight: inherit;
            }
            .cbt-subject-panel input[type="text"],
            .cbt-subject-panel input[type="email"],
            .cbt-subject-panel input[type="search"],
            .cbt-subject-panel select,
            .cbt-subject-panel textarea {
                width: 100%;
            }
            .cbt-subject-panel input[type="text"],
            .cbt-subject-panel input[type="email"],
            .cbt-subject-panel input[type="search"],
            .cbt-subject-panel select {
                height: 48px;
                border-radius: 12px;
                border: 2px solid #e2e8f0;
                padding: 0 16px;
                background: #f8fafc;
                color: #0f172a;
                font-size: 14px;
                transition: all 0.2s ease;
            }
            .cbt-subject-panel input[type="text"]:focus,
            .cbt-subject-panel input[type="email"]:focus,
            .cbt-subject-panel input[type="search"]:focus,
            .cbt-subject-panel select:focus {
                border-color: #3b82f6;
                background: #ffffff;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
                outline: none;
            }
            .cbt-subject-panel select {
                background-position: right 16px center;
            }
            .cbt-subject-panel select:disabled {
                background: #f1f5f9;
                color: #94a3b8;
                border-color: #cbd5e1;
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
            .cbt-subject-import-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.85fr) minmax(320px, 1fr);
                gap: 18px;
                align-items: start;
            }
            .cbt-subject-import-card {
                padding: 24px 28px;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);
            }
            .cbt-subject-import-card-label {
                display: block;
                margin-bottom: 10px;
                color: #0f172a;
                font-size: 14px;
                font-weight: 700;
            }
            .cbt-subject-import-card .description {
                margin-top: 8px;
            }
            .cbt-subject-import-card ul {
                margin: 0 0 0 18px;
                list-style: disc;
            }
            .cbt-subject-panel .button {
                border-radius: 12px;
                min-height: 44px;
                font-weight: 700;
                padding: 0 20px;
                transition: all 0.2s ease;
            }
            .cbt-subject-panel .button-primary {
                background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
                border: none;
                color: #ffffff;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            }
            .cbt-subject-panel .button-primary:hover,
            .cbt-subject-panel .button-primary:focus {
                transform: translateY(-2px);
                box-shadow: 0 8px 16px rgba(59, 130, 246, 0.4);
                background: linear-gradient(180deg, #60a5fa 0%, #3b82f6 100%);
            }
            .cbt-subject-import-progress {
                display: grid;
                gap: 10px;
                margin: 14px 0 18px;
                padding: 16px;
                border: 1px dashed #93c5fd;
                border-radius: 16px;
                background: #eff6ff;
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
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 14px;
            }
            .cbt-subject-filter-form {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            .cbt-subject-filter-form input[type="search"] {
                min-width: 220px;
            }
            .cbt-subject-filter-form label {
                font-weight: 600;
            }
            .cbt-subject-filter-form select {
                min-width: 150px;
            }
            .cbt-subject-filter-reset {
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
            .cbt-subject-filter-reset:hover,
            .cbt-subject-filter-reset:focus {
                border-color: #1d4ed8;
                color: #1d4ed8;
                outline: none;
            }
            .cbt-subject-photo-preview {
                margin-bottom: 10px;
            }
            .cbt-subject-photo-preview img {
                width: 84px;
                height: 84px;
                object-fit: cover;
                border-radius: 18px;
                border: 1px solid #dbe1ea;
            }
            .cbt-subject-table-photo {
                width: 38px;
                height: 38px;
                object-fit: cover;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
            }
            .cbt-subject-table-wrap {
                overflow-x: auto;
            }
            .cbt-subject-panel .widefat {
                border-radius: 16px;
                overflow: hidden;
                border: 1px solid #e2e8f0;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
            }
            .cbt-subject-panel .widefat thead th {
                background: #f8fafc;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                font-weight: 800;
                padding: 14px 16px;
                white-space: nowrap;
                color: #475569;
            }
            .cbt-subject-panel .widefat td,
            .cbt-subject-panel .widefat th {
                vertical-align: middle;
                padding: 14px 16px;
                font-size: 14px;
                word-break: break-word;
                color: #334155;
            }
            .cbt-subject-panel .widefat tbody tr:hover {
                background: #f8fafc;
            }
            .cbt-subject-row-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-subject-row-action {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 32px;
                padding: 0 16px;
                border: 1px solid #dbeafe;
                border-radius: 999px;
                background: linear-gradient(180deg, #ffffff 0%, #f4f8ff 100%);
                color: #2563eb;
                font-weight: 700;
                font-size: 12px;
                letter-spacing: 0.02em;
                text-decoration: none;
                box-shadow: 0 2px 4px rgba(37, 99, 235, 0.04);
                transition: all 0.2s ease;
            }
            .cbt-subject-row-action:hover,
            .cbt-subject-row-action:focus {
                text-decoration: none;
                transform: translateY(-1px);
                outline: none;
            }
            .cbt-subject-row-action--edit {
                border-color: #dbeafe;
                color: #2563eb;
            }
            .cbt-subject-row-action--edit:hover,
            .cbt-subject-row-action--edit:focus {
                border-color: #bfdbfe;
                background: linear-gradient(180deg, #ffffff 0%, #e6f0ff 100%);
                color: #1d4ed8;
                box-shadow: 0 4px 8px rgba(37, 99, 235, 0.08);
            }
            .cbt-subject-row-action--delete {
                border-color: #fee2e2;
                background: linear-gradient(180deg, #ffffff 0%, #fff1f2 100%);
                color: #e11d48;
                box-shadow: 0 2px 4px rgba(225, 29, 72, 0.04);
            }
            .cbt-subject-row-action--delete:hover,
            .cbt-subject-row-action--delete:focus {
                border-color: #fecdd3;
                background: linear-gradient(180deg, #ffffff 0%, #ffe4e6 100%);
                color: #be123c;
                box-shadow: 0 4px 8px rgba(225, 29, 72, 0.08);
            }
            .cbt-subject-pagination-wrap {
                margin-top: 12px;
            }
            .cbt-subject-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-subject-pagination .cbt-subject-total {
                font-weight: 600;
            }
            .cbt-subject-pagination-links {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .cbt-subject-pagination-links .page-numbers {
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
            .cbt-subject-pagination-links .page-numbers:hover,
            .cbt-subject-pagination-links .page-numbers:focus {
                border-color: #1d4ed8;
                color: #1d4ed8;
            }
            .cbt-subject-pagination-links .page-numbers.current {
                border-color: #1d4ed8;
                background: #1d4ed8;
                color: #fff;
            }
            .cbt-subject-pagination-links .page-numbers.prev,
            .cbt-subject-pagination-links .page-numbers.next {
                padding: 0 12px;
            }
            .cbt-subject-pagination-links .page-numbers.dots {
                border: none;
                background: transparent;
                min-width: auto;
            }

            @media (max-width: 960px) {
                .cbt-subject-hero,
                .cbt-subject-panel-header,
                .cbt-subject-list-toolbar {
                    flex-direction: column;
                    align-items: flex-start;
                }
                .cbt-subject-overview {
                    width: 100%;
                }
                .cbt-subject-page {
                    max-width: 100%;
                }
                .cbt-subject-hero,
                .cbt-subject-panel {
                    padding: 20px;
                }
                .cbt-subject-import-grid {
                    grid-template-columns: 1fr;
                }
                .cbt-subject-panel .form-table th {
                    width: 100%;
                }
                .cbt-subject-filter-form input[type="search"] {
                    min-width: 100%;
                }
                .cbt-subject-pagination-links .page-numbers {
                    min-width: 30px;
                }
            }
        </style>
        <div
            class="wrap cbt-subject-page"
            data-cbt-subject-default-tab="<?php echo esc_attr($default_subject_tab); ?>"
            data-cbt-subject-force-tab="<?php echo $subject_tab_is_forced ? '1' : '0'; ?>"
        >
            <div class="cbt-subject-shell">
                <section class="cbt-subject-hero">
                    <div class="cbt-subject-hero-copy">
                        <span class="cbt-subject-kicker">Subject</span>
                        <h1>CBT Subjects</h1>
                        <p>Kelola mapel CBT melalui tab yang terpisah agar proses tambah, import, dan pengelolaan daftar subject terasa lebih ringkas.</p>
                    </div>
                    <div class="cbt-subject-overview" aria-hidden="true">
                        <span class="cbt-subject-pill"><?php echo esc_html(sprintf('Total: %d subject', $total_subjects)); ?></span>
                        <span class="cbt-subject-pill"><?php echo esc_html(!empty($editing) ? 'Mode edit aktif' : 'Mode tambah'); ?></span>
                        <span class="cbt-subject-pill"><?php echo esc_html(is_array($subject_import_state) ? 'Import berjalan' : 'Import siap'); ?></span>
                    </div>
                </section>

                <?php if ($notice): ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                <?php endif; ?>

                <div class="cbt-subject-tabs" role="tablist" aria-label="Navigasi CBT Subject">
                    <button type="button" class="cbt-subject-tab<?php echo $default_subject_tab === 'form' ? ' is-active' : ''; ?>" data-cbt-subject-tab="form" role="tab" aria-selected="<?php echo $default_subject_tab === 'form' ? 'true' : 'false'; ?>">Form Subject</button>
                    <button type="button" class="cbt-subject-tab<?php echo $default_subject_tab === 'import' ? ' is-active' : ''; ?>" data-cbt-subject-tab="import" role="tab" aria-selected="<?php echo $default_subject_tab === 'import' ? 'true' : 'false'; ?>">Import Subject</button>
                    <button type="button" class="cbt-subject-tab<?php echo $default_subject_tab === 'list' ? ' is-active' : ''; ?>" data-cbt-subject-tab="list" role="tab" aria-selected="<?php echo $default_subject_tab === 'list' ? 'true' : 'false'; ?>">Daftar Subject</button>
                </div>

                <section class="cbt-subject-panel<?php echo $default_subject_tab === 'form' ? ' is-active' : ''; ?>" data-cbt-subject-panel="form" role="tabpanel">
                    <div class="cbt-subject-panel-header">
                        <div>
                            <h2><?php echo $editing ? 'Edit Subject' : 'Add Subject'; ?></h2>
                            <p>Tambah subject baru atau perbarui subject yang sudah ada tanpa harus turun ke bagian daftar.</p>
                        </div>
                        <?php if ($editing): ?>
                            <a href="<?php echo esc_url($subject_clear_edit_url); ?>" class="button button-secondary">Batal Edit</a>
                        <?php else: ?>
                            <span class="cbt-subject-chip">Manual</span>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-subject-tab-submit="form">
                        <?php wp_nonce_field('cbt_save_subject'); ?>
                        <input type="hidden" name="action" value="cbt_save_subject" />
                        <input type="hidden" name="id" value="<?php echo esc_attr($editing['id'] ?? 0); ?>" />

                        <table class="form-table" role="presentation">
                            <tr>
                                <th><label for="cbt-subject-name">Name</label></th>
                                <td>
                                    <input required type="text" id="cbt-subject-name" name="name" class="regular-text" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" />
                                    <p class="description">Nama subject yang akan dipakai di bank soal dan builder exam.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbt-subject-code">Code</label></th>
                                <td>
                                    <input type="text" id="cbt-subject-code" name="code" class="regular-text" value="<?php echo esc_attr($editing['code'] ?? ''); ?>" placeholder="MAT, IND, ENG" />
                                    <p class="description">Kode singkat dipakai untuk tampilan ringkas dan import data.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbt-subject-description">Description</label></th>
                                <td>
                                    <textarea id="cbt-subject-description" name="description" class="large-text" rows="3"><?php echo esc_textarea($editing['description'] ?? ''); ?></textarea>
                                    <p class="description">Deskripsi singkat opsional untuk kebutuhan administrasi subject.</p>
                                </td>
                            </tr>
                        </table>

                        <div class="cbt-subject-form-actions">
                            <?php submit_button($editing ? 'Update Subject' : 'Save Subject', 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </section>

                <section class="cbt-subject-panel<?php echo $default_subject_tab === 'import' ? ' is-active' : ''; ?>" data-cbt-subject-panel="import" role="tabpanel">
                    <div class="cbt-subject-panel-header">
                        <div>
                            <h2>Import CBT Subjects</h2>
                            <p>Upload file CSV atau XLSX untuk membuat atau memperbarui banyak subject sekaligus.</p>
                        </div>
                        <span class="cbt-subject-chip">CSV / XLSX</span>
                    </div>
                    <?php if (is_array($subject_import_state)): ?>
                        <div class="cbt-subject-import-progress">
                            <strong>
                                Progress Import Subject:
                                <?php echo esc_html((string) $subject_import_offset . ' / ' . (string) $subject_import_total); ?>
                                (<?php echo esc_html(number_format($subject_import_progress_percent, 2)); ?>%)
                            </strong>
                            <div class="cbt-subject-import-progress-track" aria-hidden="true">
                                <div class="cbt-subject-import-progress-fill" style="width: <?php echo esc_attr((string) $subject_import_progress_percent); ?>%;"></div>
                            </div>
                            <div class="cbt-subject-import-progress-meta">
                                Created: <?php echo esc_html((string) $subject_import_created); ?> |
                                Updated: <?php echo esc_html((string) $subject_import_updated); ?> |
                                Failed: <?php echo esc_html((string) $subject_import_failed); ?>
                                <br />
                                <?php if ($subject_import_is_running): ?>
                                    Memproses batch subject berikutnya...
                                    <script>
                                        window.setTimeout(function () {
                                            window.location.href = <?php echo wp_json_encode($subject_import_continue_url); ?>;
                                        }, 350);
                                    </script>
                                <?php else: ?>
                                    <span style="color:#0a7a2f; font-weight:600;">Import subject selesai diproses.</span>
                                <?php endif; ?>
                            </div>
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
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-subject-tab-submit="import">
                        <?php wp_nonce_field('cbt_import_subjects'); ?>
                        <input type="hidden" name="action" value="cbt_import_subjects" />
                        <table class="form-table" role="presentation">
                            <tr>
                                <th><label for="cbt-subject-import-file">File Import</label></th>
                                <td>
                                    <input required type="file" id="cbt-subject-import-file" name="subject_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                                    <div class="description" style="margin-top:8px;">
                                        <ul style="margin:0 0 0 18px; list-style:disc;">
                                            <li>Kolom minimal: <code>name</code>.</li>
                                            <li>Kolom opsional: <code>code</code>, <code>description</code>.</li>
                                            <li>Format yang didukung: <code>.csv</code> dan <code>.xlsx</code>.</li>
                                            <li>Import bersifat upsert berdasarkan <code>code</code>. Jika <code>code</code> kosong, sistem memakai <code>name</code>.</li>
                                            <li>Nilai <code>name</code> dan <code>code</code> tidak boleh duplikat antarbaris dalam file import yang sama.</li>
                                            <li>Jika <code>code</code> dan <code>name</code> mengarah ke dua subject berbeda, baris tersebut akan ditolak agar tidak merge salah.</li>
                                            <li>Progress import tampil otomatis: jumlah diproses, persentase, <code>created</code>, <code>updated</code>, dan <code>failed</code>.</li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <div class="cbt-subject-form-actions">
                            <?php submit_button('Import CBT Subjects', 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </section>

                <section class="cbt-subject-panel<?php echo $default_subject_tab === 'list' ? ' is-active' : ''; ?>" data-cbt-subject-panel="list" role="tabpanel">
                    <div class="cbt-subject-panel-header">
                        <div>
                            <h2>Subject List</h2>
                            <p>Lihat semua subject, filter cepat otomatis berdasarkan nama subject, ubah jumlah data per halaman, dan lakukan aksi bulk delete dari satu panel khusus.</p>
                        </div>
                        <span class="cbt-subject-chip"><?php echo esc_html($subject_list_chip_label); ?></span>
                    </div>
                    <div class="cbt-subject-list-toolbar">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-subject-filter-form" data-cbt-subject-tab-submit="list">
                            <input type="hidden" name="page" value="cbt-subjects" />
                            <div class="cbt-subject-filter-field">
                                <label for="cbt-subject-filter-id">Nama Subject</label>
                                <select id="cbt-subject-filter-id" name="cbt_subject_filter_id" data-cbt-subject-auto-submit="change">
                                    <option value="0">Semua subject</option>
                                    <?php foreach ($subject_filter_options as $subject_filter_option_id => $subject_filter_option_name): ?>
                                        <option value="<?php echo (int) $subject_filter_option_id; ?>" <?php selected($subject_filter_id, (int) $subject_filter_option_id); ?>>
                                            <?php echo esc_html($subject_filter_option_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="cbt-subject-filter-field">
                                <label for="cbt-subject-per-page">Per halaman</label>
                                <select id="cbt-subject-per-page" name="cbt_subject_per_page" data-cbt-subject-auto-submit="change">
                                    <?php foreach ([20, 40, 60, 80, 100] as $subject_per_page_option): ?>
                                        <option value="<?php echo (int) $subject_per_page_option; ?>" <?php selected($subject_per_page, $subject_per_page_option); ?>>
                                            <?php echo esc_html((string) $subject_per_page_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="cbt-subject-filter-actions">
                                <a href="<?php echo esc_url($subject_reset_filter_url); ?>" class="cbt-subject-filter-reset">Reset Filter</a>
                            </div>
                        </form>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-subject-tab-submit="list" style="margin: 8px 0 0;">
                        <?php wp_nonce_field('cbt_bulk_delete_subjects'); ?>
                        <input type="hidden" name="action" value="cbt_bulk_delete_subjects" />
                        <input type="hidden" name="cbt_subject_per_page" value="<?php echo (int) $subject_per_page; ?>" />
                        <input type="hidden" name="cbt_subject_filter_id" value="<?php echo (int) $subject_filter_id; ?>" />
                        <input type="hidden" name="cbt_subject_paged" value="<?php echo (int) $subject_current_page; ?>" />
                        <div class="cbt-subject-bulk-actions">
                            <button type="submit" class="button cbt-subject-bulk-button cbt-subject-bulk-button--selected" name="bulk_mode" value="selected" onclick="return confirm('Delete selected subjects?');">Delete Selected</button>
                            <button type="submit" class="button cbt-subject-bulk-button cbt-subject-bulk-button--all" name="bulk_mode" value="all" onclick="return confirm('Delete semua subject pada hasil filter ini? Subject yang dipakai exam akan dilewati.');">Delete All</button>
                        </div>

                        <div class="cbt-subject-table-wrap">
                        <table class="widefat striped">
                            <thead>
                            <tr>
                                <th style="width:32px;"><input type="checkbox" id="cbt-subject-select-all" /></th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$subjects): ?>
                                <?php
                                echo CBT_Admin_UI_Helper::render_table_empty_state(6, [
                                    'title' => $subject_filter_id > 0 ? 'Tidak ada subject sesuai filter' : 'Belum ada subject',
                                    'message' => $subject_filter_id > 0
                                        ? 'Subject yang dipilih tidak ditemukan pada filter aktif. Reset filter untuk melihat semua subject.'
                                        : 'Tambahkan subject/mapel sebagai dasar bank soal, exam, dan mapel pilihan siswa.',
                                    'action_label' => $subject_filter_id > 0 ? 'Reset Filter' : 'Tambah Subject',
                                    'action_url' => $subject_filter_id > 0 ? $subject_reset_filter_url : admin_url('admin.php?page=cbt-subjects'),
                                    'action_class' => $subject_filter_id > 0 ? 'button button-secondary cbt-admin-btn--secondary' : 'button button-primary',
                                ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                ?>
                            <?php else: ?>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><input type="checkbox" class="cbt-subject-row-check" name="subject_ids[]" value="<?php echo (int) $subject['id']; ?>" /></td>
                                        <td><?php echo (int) $subject['id']; ?></td>
                                        <td><?php echo esc_html((string) $subject['name']); ?></td>
                                        <td><?php echo esc_html((string) ($subject['code'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($subject['description'] ?? '')); ?></td>
                                        <td>
                                            <div class="cbt-admin-row-actions cbt-subject-row-actions">
                                                <a class="cbt-admin-action cbt-admin-action--edit cbt-subject-row-action cbt-subject-row-action--edit" href="<?php echo esc_url(add_query_arg(array_merge($subject_list_query_args, ['edit' => (int) $subject['id'], 'cbt_subject_paged' => $subject_current_page]), admin_url('admin.php'))); ?>">Edit</a>
                                                <a class="cbt-admin-action cbt-admin-action--delete cbt-subject-row-action cbt-subject-row-action--delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge([
                                                    'action' => 'cbt_delete_subject',
                                                    'id' => (int) $subject['id'],
                                                    'cbt_subject_paged' => $subject_current_page,
                                                ], $subject_list_query_args), admin_url('admin-post.php')), 'cbt_delete_subject_' . (int) $subject['id'])); ?>" onclick="return confirm('Delete this subject?');">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                        <div class="tablenav bottom cbt-admin-pagination-wrap" style="margin-top:10px;">
                            <div class="tablenav-pages cbt-admin-pagination" style="float:none; margin:0;">
                                <span class="displaying-num cbt-admin-total"><?php echo esc_html($subject_list_total_label); ?></span>
                                <?php if (!empty($subject_pagination_links)): ?>
                                    <span class="pagination-links cbt-admin-pagination-links">
                                        <?php foreach ($subject_pagination_links as $subject_pagination_link): ?>
                                            <?php echo wp_kses_post($subject_pagination_link); ?>
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
                const page = document.querySelector('.cbt-subject-page');
                const tabButtons = Array.from(document.querySelectorAll('[data-cbt-subject-tab]'));
                const tabPanels = Array.from(document.querySelectorAll('[data-cbt-subject-panel]'));
                const tabStorageKey = 'cbt-subject-active-tab';
                const defaultTab = page ? String(page.getAttribute('data-cbt-subject-default-tab') || 'list') : 'list';
                const forceTab = page ? page.getAttribute('data-cbt-subject-force-tab') === '1' : false;
                let subjectTabStorage = null;

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

                function activateTab(tabId, persist) {
                    let hasTarget = false;
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

                if (page && tabButtons.length > 0 && tabPanels.length > 0) {
                    let initialTab = defaultTab;
                    if (!forceTab) {
                        const savedTab = readSubjectStoredTab();
                        if (savedTab && tabPanels.some((panel) => panel.getAttribute('data-cbt-subject-panel') === savedTab)) {
                            initialTab = savedTab;
                        }
                    }

                    activateTab(initialTab, false);

                    tabButtons.forEach((button) => {
                        button.addEventListener('click', function () {
                            activateTab(String(button.getAttribute('data-cbt-subject-tab') || ''), true);
                        });
                    });

                    Array.from(document.querySelectorAll('form[data-cbt-subject-tab-submit]')).forEach((form) => {
                        form.addEventListener('submit', function () {
                            const tabId = String(form.getAttribute('data-cbt-subject-tab-submit') || '');
                            writeSubjectStoredTab(tabId);
                        });
                    });
                }

                const supportsPartialListRefresh = !!(window.fetch && window.DOMParser);
                let subjectFilterTimer = 0;
                let subjectListRequestSeq = 0;

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

                function updateSubjectListHistory(nextUrl) {
                    if (!window.history || typeof window.history.replaceState !== 'function') {
                        return;
                    }

                    window.history.replaceState({}, '', nextUrl.toString());
                }

                function navigateSubjectList(nextUrl) {
                    window.location.assign(nextUrl.toString());
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
                        const response = await window.fetch(nextUrl.toString(), {
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (!response.ok) {
                            navigateSubjectList(nextUrl);
                            return;
                        }

                        const html = await response.text();
                        if (requestSeq !== subjectListRequestSeq) {
                            return;
                        }

                        const parsed = new DOMParser().parseFromString(html, 'text/html');
                        const nextPanel = parsed.querySelector('[data-cbt-subject-panel="list"]');
                        if (!nextPanel) {
                            navigateSubjectList(nextUrl);
                            return;
                        }

                        currentPanel.innerHTML = nextPanel.innerHTML;
                        updateSubjectListHistory(nextUrl);
                        bindSubjectListPanel();
                    } catch (error) {
                        if (requestSeq === subjectListRequestSeq) {
                            navigateSubjectList(nextUrl);
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

                function bindSubjectListSelection(panel) {
                    if (!panel) {
                        return;
                    }

                    const selectAll = panel.querySelector('#cbt-subject-select-all');
                    const rowChecks = Array.from(panel.querySelectorAll('.cbt-subject-row-check'));
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

                function bindSubjectListPanel() {
                    const panel = getSubjectListPanel();
                    if (!panel) {
                        return;
                    }

                    const subjectFilterForm = panel.querySelector('.cbt-subject-filter-form');
                    const subjectFilterId = panel.querySelector('#cbt-subject-filter-id');
                    const subjectPerPageSelect = panel.querySelector('#cbt-subject-per-page');
                    const subjectReset = panel.querySelector('.cbt-subject-filter-reset');
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

                    [subjectFilterId, subjectPerPageSelect].forEach((field) => {
                        if (!field || field.dataset.cbtAutoBound === '1') {
                            return;
                        }

                        field.dataset.cbtAutoBound = '1';
                        field.addEventListener('change', function () {
                            window.clearTimeout(subjectFilterTimer);
                            submitSubjectFilters(subjectFilterForm);
                        });
                    });

                    if (supportsPartialListRefresh && subjectReset && subjectReset.dataset.cbtAsyncBound !== '1') {
                        subjectReset.dataset.cbtAsyncBound = '1';
                        subjectReset.addEventListener('click', function (event) {
                            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }

                            event.preventDefault();
                            window.clearTimeout(subjectFilterTimer);
                            refreshSubjectListPanel(new URL(subjectReset.getAttribute('href') || window.location.href, window.location.href));
                        });
                    }

                    if (supportsPartialListRefresh) {
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
                                window.clearTimeout(subjectFilterTimer);
                                refreshSubjectListPanel(new URL(link.getAttribute('href') || window.location.href, window.location.href));
                            });
                        });
                    }

                    bindSubjectListSelection(panel);
                }

                bindSubjectListPanel();
            })();
        </script>
