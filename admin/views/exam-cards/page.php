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

            
        .cbt-exam-cards-page {
            max-width: 1280px;
            margin: 20px auto;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--cbt-text-main);
            
        }
        .cbt-exam-cards-page * {
            box-sizing: border-box;
        }

            
            .cbt-exam-cards-shell::before {
                content: ''; position: absolute; top: -150px; left: -100px; width: 600px; height: 600px;
                background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255,255,255,0) 70%);
                z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
            }
            .cbt-exam-cards-shell::after {
                content: ''; position: absolute; bottom: -100px; right: -50px; width: 500px; height: 500px;
                background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(255,255,255,0) 70%);
                z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
            }

            .cbt-exam-cards-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            
                position: relative;
                z-index: 1;
                isolation: isolate;}
            
            .cbt-exam-cards-hero::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 5px;
                background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
            }

            .cbt-exam-cards-hero {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 22px;
                padding: 24px 28px;
                border-radius: var(--cbt-radius-lg);
                
                
                border: 1px solid var(--cbt-border);
                box-shadow: var(--cbt-shadow-lg);
            
                background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.8) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                position: relative;
                overflow: hidden;
            }
            .cbt-exam-cards-hero-copy {
                max-width: 660px;
            }
            .cbt-exam-cards-kicker {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 12px;
                border-radius: 999px;
                background: rgba(59,130,246,0.1);
                color: #0f4fa8;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
            }
            .cbt-exam-cards-hero h1 {
                margin: 12px 0 8px;
                font-size: 30px;
                line-height: 1.15;
            
                font-weight: 800;
                background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                letter-spacing: -0.02em;
            }
            .cbt-exam-cards-hero p {
                margin: 0;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-exam-cards-overview {
                display: grid;
                gap: 10px;
                min-width: 260px;
            }
            .cbt-exam-cards-pill {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 34px;
                padding: 0 14px;
                border-radius: 999px;
                background: rgba(255,255,255,0.4); backdrop-filter: blur(4px);
                border: 1px solid #d7e4f5;
                color: #1e3a5f;
                font-size: 13px;
                font-weight: 600;
            }
            .cbt-exam-cards-panel {
                padding: 24px;
                border-radius: var(--cbt-radius-md);
                
                
                border: 1px solid var(--cbt-border);
                box-shadow: var(--cbt-shadow-md);
                transition: var(--cbt-transition);
            
                background: linear-gradient(180deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.8) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
            }
            .cbt-exam-cards-panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }
            .cbt-exam-cards-panel-header h2 {
                margin: 0 0 6px;
                font-size: 18px;
                line-height: 1.2;
            }
            .cbt-exam-cards-panel-header p {
                margin: 0;
                color: #646970;
                line-height: 1.55;
            }
            .cbt-exam-cards-chip {
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
            .cbt-exam-cards-panel .form-table {
                margin: 0;
                border-collapse: separate;
                border-spacing: 0 18px;
            }
            .cbt-exam-cards-panel .form-table th {
                width: 190px;
                padding: 10px 18px 0 0;
                vertical-align: top;
                color: #0f172a;
                font-size: 14px;
                font-weight: 700;
            }
            .cbt-exam-cards-panel .form-table td {
                padding: 0;
                vertical-align: top;
            }
            .cbt-exam-cards-panel .form-table th label {
                color: inherit;
                font-weight: inherit;
            }
            .cbt-exam-cards-panel input[type="text"],
            .cbt-exam-cards-panel input[type="date"],
            .cbt-exam-cards-panel input[type="time"],
            .cbt-exam-cards-panel input[type="number"],
            .cbt-exam-cards-panel textarea,
            .cbt-exam-cards-panel select {
                min-height: 48px;
                padding: 0 15px;
                border: 1px solid var(--cbt-border);
                border-radius: var(--cbt-radius-sm);
                background: rgba(255, 255, 255, 0.5);
                backdrop-filter: blur(5px);
                color: var(--cbt-text-main);
                transition: var(--cbt-transition);
            }
            .cbt-exam-cards-panel input[type="text"],
            .cbt-exam-cards-panel input[type="date"],
            .cbt-exam-cards-panel input[type="time"],
            .cbt-exam-cards-panel textarea,
            .cbt-exam-cards-panel input[type="number"] {
                width: min(100%, 720px);
                max-width: none;
            }
            .cbt-exam-cards-panel textarea {
                min-height: 96px;
                padding-top: 13px;
                resize: vertical;
            }
            .cbt-exam-cards-panel input[type="number"] {
                width: min(100%, 220px);
            }
            .cbt-exam-cards-panel select {
                min-width: 240px;
                max-width: 100%;
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
            .cbt-exam-cards-field-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                max-width: 720px;
            }
            .cbt-exam-cards-tabs {
                display: flex;
                align-items: stretch;
                gap: 12px;
                flex-wrap: wrap;
                padding: 6px;
                border-radius: var(--cbt-radius-md);
                background: var(--cbt-bg-card);
                backdrop-filter: blur(10px);
                border: 1px solid var(--cbt-border);
                box-shadow: var(--cbt-shadow-md);
            }
            .cbt-exam-cards-tab {
                appearance: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 168px;
                min-height: 52px;
                padding: 0 20px;
                border: 1px solid #d7e4f5;
                border-radius: 16px;
                background: var(--cbt-bg-card); backdrop-filter: blur(10px);
                color: #3f526d;
                font-size: 14px;
                font-weight: 700;
                line-height: 1.2;
                cursor: pointer;
                box-shadow: 0 10px 22px rgba(15, 23, 42, 0.07);
                transition: transform 140ms ease, background-color 140ms ease, color 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
            }
            .cbt-exam-cards-tab:hover,
            .cbt-exam-cards-tab:focus {
                transform: translateY(-1px);
                border-color: #bdd3ec;
                background: linear-gradient(180deg, #ffffff 0%, #f1f7ff 100%);
                color: #274767;
            }
            .cbt-exam-cards-tab.is-active {
                border-color: #2f7ab9;
                background: linear-gradient(180deg, #2f7ab9 0%, #1f68a6 100%);
                color: #ffffff;
                box-shadow: 0 16px 30px rgba(34, 113, 177, 0.24);
            }
            .cbt-exam-cards-mode-panel {
                display: grid;
                gap: 6px;
                max-width: 720px;
                margin-top: 14px;
                padding: 16px 18px;
                border: 1px solid #dbe7f3;
                border-radius: 18px;
                background: var(--cbt-bg-card); backdrop-filter: blur(10px);
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            }
            .cbt-exam-cards-mode-panel-title {
                color: #0f172a;
                font-size: 15px;
                font-weight: 700;
                line-height: 1.35;
            }
            .cbt-exam-cards-mode-panel-copy {
                color: #64748b;
                font-size: 13px;
                line-height: 1.6;
            }
            .cbt-exam-cards-seat-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(180px, 220px));
                gap: 12px 16px;
                align-items: end;
            }
            .cbt-exam-cards-seat-field {
                display: grid;
                gap: 7px;
            }
            .cbt-exam-cards-seat-field label {
                font-size: 13px;
                font-weight: 700;
                color: #0f172a;
            }
            .cbt-exam-cards-minutes-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px 16px;
                max-width: 760px;
            }
            .cbt-exam-cards-minutes-field {
                display: grid;
                gap: 7px;
                min-width: 0;
            }
            .cbt-exam-cards-minutes-field.is-wide {
                grid-column: 1 / -1;
            }
            .cbt-exam-cards-minutes-field label {
                font-size: 13px;
                font-weight: 700;
                color: #0f172a;
            }
            .cbt-exam-cards-row-hidden {
                display: none;
            }
            .cbt-exam-cards-field-option {
                position: relative;
                display: flex;
                gap: 12px;
                align-items: flex-start;
                padding: 14px 16px;
                border: 1px solid #d7e4f5;
                border-radius: 18px;
                background: var(--cbt-bg-card); backdrop-filter: blur(10px);
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            }
            .cbt-exam-cards-field-option input[type="checkbox"] {
                margin: 2px 0 0;
            }
            .cbt-exam-cards-field-option strong {
                display: block;
                margin-bottom: 3px;
                color: #0f172a;
                font-size: 13px;
                line-height: 1.35;
            }
            .cbt-exam-cards-field-option span {
                display: block;
                color: #64748b;
                font-size: 12px;
                line-height: 1.5;
            }
            .cbt-exam-cards-panel .description {
                margin-top: 10px;
                color: #64748b;
                font-size: 13px;
                line-height: 1.65;
            }
            .cbt-exam-cards-summary {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin: 4px 0 18px;
            }
            .cbt-exam-cards-summary-label {
                font-size: 13px;
                font-weight: 700;
                color: #334155;
            }
            .cbt-exam-cards-summary-item {
                display: inline-flex;
                align-items: center;
                min-height: 34px;
                padding: 0 14px;
                border-radius: 999px;
                background: rgba(255,255,255,0.4);
                border: 1px solid #dbe7f3;
                color: #1e3a5f;
                font-size: 13px;
                font-weight: 600;
            }
            .cbt-exam-cards-note {
                margin: 0;
                padding: 14px 16px;
                border: 1px solid #fed7aa;
                border-radius: 16px;
                background: linear-gradient(180deg, #fffaf5 0%, #fff7ed 100%);
                color: #9a3412;
                line-height: 1.6;
            }
            .cbt-exam-cards-local-progress {
                display: none;
                margin: 0 0 18px;
                padding: 16px 18px;
                border: 1px solid #bfdbfe;
                border-radius: 18px;
                background: linear-gradient(180deg, rgba(239, 246, 255, 0.92) 0%, rgba(255, 255, 255, 0.92) 100%);
                box-shadow: 0 12px 24px rgba(37, 99, 235, 0.1);
            }
            .cbt-exam-cards-local-progress.is-active,
            .cbt-exam-cards-local-progress.is-complete,
            .cbt-exam-cards-local-progress.is-error {
                display: block;
            }
            .cbt-exam-cards-local-progress.is-error {
                border-color: #fecaca;
                background: linear-gradient(180deg, #fff1f2 0%, #ffffff 100%);
            }
            .cbt-exam-cards-progress-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                margin-bottom: 12px;
            }
            .cbt-exam-cards-progress-title {
                display: grid;
                gap: 3px;
                color: #1e3a5f;
                line-height: 1.35;
            }
            .cbt-exam-cards-progress-title strong {
                font-size: 14px;
            }
            .cbt-exam-cards-progress-title span,
            .cbt-exam-cards-progress-step {
                color: #64748b;
                font-size: 12px;
            }
            .cbt-exam-cards-progress-percent {
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
            }
            .cbt-exam-cards-local-progress.is-error .cbt-exam-cards-progress-percent {
                background: #fee2e2;
                color: #b91c1c;
            }
            .cbt-exam-cards-progress-track {
                position: relative;
                overflow: hidden;
                height: 10px;
                border-radius: 999px;
                background: #dbeafe;
            }
            .cbt-exam-cards-progress-fill {
                display: block;
                width: 0%;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb 0%, #0ea5e9 58%, #22c55e 100%);
                transition: width 220ms ease;
            }
            .cbt-exam-cards-local-progress.is-error .cbt-exam-cards-progress-fill {
                background: linear-gradient(90deg, #ef4444 0%, #f97316 100%);
            }
            .cbt-exam-cards-progress-step {
                margin: 10px 0 0;
                line-height: 1.55;
            }
            .cbt-exam-cards-page.is-local-busy .cbt-exam-cards-panel {
                box-shadow: 0 18px 34px rgba(37, 99, 235, 0.12);
            }
            .cbt-exam-cards-form-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-top: 18px;
            }
            .cbt-exam-cards-form-actions .button {
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
            .cbt-exam-cards-form-actions .button-primary {
                border-color: #1d5f99;
                background: linear-gradient(180deg, #2f7ab9 0%, #1f68a6 100%);
                box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
            }
            .cbt-exam-cards-form-actions .button-primary:hover,
            .cbt-exam-cards-form-actions .button-primary:focus {
                transform: translateY(-1px);
                border-color: #174d7c;
                background: linear-gradient(180deg, #337fbe 0%, #1c629c 100%);
                box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
            }
            .cbt-exam-cards-form-actions .button-secondary {
                border-color: #cad9ea;
                background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%);
                color: #1d4f80;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            }
            .cbt-exam-cards-form-actions .button-secondary:hover,
            .cbt-exam-cards-form-actions .button-secondary:focus {
                transform: translateY(-1px);
                border-color: #a9c3df;
                background: linear-gradient(180deg, #ffffff 0%, #edf5ff 100%);
                color: #153f67;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            }
            .cbt-exam-cards-insights {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
            }
            .cbt-exam-cards-insight {
                padding: 18px;
                border-radius: var(--cbt-radius-md);
                background: var(--cbt-bg-card);
                backdrop-filter: blur(10px);
                border: 1px solid var(--cbt-border);
                box-shadow: var(--cbt-shadow-sm);
            }
            .cbt-exam-cards-insight strong {
                display: block;
                margin-bottom: 6px;
                color: #0f172a;
                font-size: 15px;
            }
            .cbt-exam-cards-insight p {
                margin: 0;
                color: #64748b;
                line-height: 1.6;
            }
            @media (max-width: 960px) {
                .cbt-exam-cards-hero,
                .cbt-exam-cards-panel-header {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-exam-cards-overview {
                    min-width: 0;
                }
                .cbt-exam-cards-insights {
                    grid-template-columns: 1fr;
                }
            }
            @media (max-width: 782px) {
                .cbt-exam-cards-page {
                background: radial-gradient(circle at top left, #e0e7ff 0%, #f8fafc 40%, #f0fdf4 100%);
                padding: 24px;
                border-radius: var(--cbt-radius-lg);

                    margin-right: 10px;
                }
                .cbt-exam-cards-hero,
                .cbt-exam-cards-panel {
                padding: 24px;
                border-radius: var(--cbt-radius-md);
                
                
                border: 1px solid var(--cbt-border);
                box-shadow: var(--cbt-shadow-md);
                transition: var(--cbt-transition);
            
                background: linear-gradient(180deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.8) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
            }
                .cbt-exam-cards-panel .form-table th {
                    width: auto;
                    padding-right: 0;
                }
                .cbt-exam-cards-panel select {
                    min-width: 0;
                    width: 100%;
                }
                .cbt-exam-cards-field-grid {
                    grid-template-columns: 1fr;
                }
                .cbt-exam-cards-minutes-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <div class="wrap cbt-exam-cards-page" data-cbt-exam-cards-root>
            <div class="cbt-exam-cards-shell">
                <section class="cbt-exam-cards-hero">
                    <div class="cbt-exam-cards-hero-copy">
                        <span class="cbt-exam-cards-kicker">Pre-Test Documents</span>
                        <h1>CBT Administrative Documents</h1>
                        <p>Siapkan dokumen administrasi sebelum pelaksanaan ujian, seperti kartu peserta dan nomor meja, dengan output PDF A4 siap cetak.</p>
                    </div>
                    <div class="cbt-exam-cards-overview" aria-hidden="true" data-cbt-exam-cards-refresh-area="overview">
                        <span class="cbt-exam-cards-pill"><?php echo esc_html(sprintf('%d kelas tersedia', count($kelas_options))); ?></span>
                        <span class="cbt-exam-cards-pill"><?php echo esc_html(sprintf('%d ruang tersedia', count($ruang_options))); ?></span>
                        <span class="cbt-exam-cards-pill"><?php echo esc_html(sprintf('%d filter aktif', $active_filter_count)); ?></span>
                        <span id="cbt-card-mode-pill" class="cbt-exam-cards-pill"><?php echo esc_html('Mode: ' . $selected_print_mode_label); ?></span>
                    </div>
                </section>

                <section class="cbt-exam-cards-panel">
                    <div class="cbt-exam-cards-panel-header">
                        <div>
                            <h2>Filter & Generate Documents</h2>
                            <p>Pilih kelas, ruang, atau kata kunci siswa untuk membatasi dokumen yang akan digenerate. Setelah itu, pilih tipe output: kartu peserta, nomor meja, daftar hadir, atau berita acara.</p>
                        </div>
                        <span class="cbt-exam-cards-chip">PDF A4 • dokumen siap cetak</span>
                    </div>

                    <div data-cbt-exam-cards-refresh-area="notices">
            <?php if ($notice): ?>
                        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
                    </div>

                    <div class="cbt-exam-cards-summary" aria-hidden="true" data-cbt-exam-cards-refresh-area="summary">
                        <span class="cbt-exam-cards-summary-label">Ringkasan:</span>
                        <span id="cbt-card-mode-summary" class="cbt-exam-cards-summary-item"><?php echo esc_html('Mode: ' . $selected_print_mode_label); ?></span>
                        <span class="cbt-exam-cards-summary-item"><?php echo esc_html('Kelas: ' . ($selected_kelas !== '' ? $selected_kelas : 'Semua kelas')); ?></span>
                        <span class="cbt-exam-cards-summary-item"><?php echo esc_html('Ruang: ' . ($selected_ruang !== '' ? $selected_ruang : 'Semua ruang')); ?></span>
                        <span class="cbt-exam-cards-summary-item"><?php echo esc_html(sprintf('Jadwal publish: %d jadwal', $schedule_count)); ?></span>
                        <?php if ($is_participant_mode): ?>
                            <span class="cbt-exam-cards-summary-item"><?php echo esc_html(sprintf('Info kartu: %d item', $display_field_count)); ?></span>
                        <?php elseif ($is_minutes_mode): ?>
                            <span class="cbt-exam-cards-summary-item">Detail berita acara siap diedit</span>
                        <?php else: ?>
                            <span class="cbt-exam-cards-summary-item">Print-only tanpa ubah data siswa</span>
                        <?php endif; ?>
                    </div>

                    <div class="cbt-exam-cards-local-progress" data-cbt-exam-cards-progress role="status" aria-live="polite" aria-hidden="true">
                        <div class="cbt-exam-cards-progress-head">
                            <div class="cbt-exam-cards-progress-title">
                                <strong data-cbt-exam-cards-progress-title>Siap memproses dokumen administrasi</strong>
                                <span>Progress berjalan di area ini tanpa reload halaman global.</span>
                            </div>
                            <span class="cbt-exam-cards-progress-percent" data-cbt-exam-cards-progress-percent>0%</span>
                        </div>
                        <div class="cbt-exam-cards-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-cbt-exam-cards-progress-track>
                            <span class="cbt-exam-cards-progress-fill" data-cbt-exam-cards-progress-fill></span>
                        </div>
                        <p class="cbt-exam-cards-progress-step" data-cbt-exam-cards-progress-step>Atur filter lalu generate dokumen.</p>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-exam-cards-refresh-area="form" data-cbt-exam-cards-print-form>
                        <?php wp_nonce_field('cbt_print_exam_cards'); ?>
                        <input type="hidden" name="action" value="cbt_print_exam_cards" />
                        <input type="hidden" name="cbt_card_fields_configured" value="1" />
                        <table class="form-table" role="presentation">
                            <tbody>
                            <tr>
                                <th>Mode Cetak</th>
                                <td>
                                    <input type="hidden" id="cbt-card-print-mode" name="cbt_card_print_mode" value="<?php echo esc_attr($selected_print_mode); ?>" />
                                    <div class="cbt-exam-cards-tabs" role="tablist" aria-label="Mode cetak dokumen administrasi">
                                        <?php foreach ($print_mode_options as $mode_key => $mode_option): ?>
                                            <button
                                                type="button"
                                                class="cbt-exam-cards-tab<?php echo $selected_print_mode === $mode_key ? ' is-active' : ''; ?>"
                                                data-print-mode-tab="<?php echo esc_attr($mode_key); ?>"
                                                role="tab"
                                                aria-selected="<?php echo $selected_print_mode === $mode_key ? 'true' : 'false'; ?>"
                                            >
                                                <?php echo esc_html((string) ($mode_option['label'] ?? $mode_key)); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="cbt-exam-cards-mode-panel">
                                        <div id="cbt-card-mode-title" class="cbt-exam-cards-mode-panel-title">
                                            <?php echo esc_html((string) ($print_mode_options[$selected_print_mode]['label'] ?? $selected_print_mode)); ?>
                                        </div>
                                        <div id="cbt-card-mode-copy" class="cbt-exam-cards-mode-panel-copy">
                                            <?php echo esc_html((string) ($print_mode_options[$selected_print_mode]['description'] ?? '')); ?>
                                        </div>
                                    </div>
                                    <p class="description">Tab ini hanya mengatur tipe output. Filter siswa tetap sama, tetapi field dan note di bawah akan menyesuaikan mode yang aktif.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbt-card-kelas">Kelas</label></th>
                                <td>
                                    <select id="cbt-card-kelas" name="cbt_card_kelas">
                                        <option value="">Semua kelas</option>
                                        <?php foreach ($kelas_options as $kelas_option): ?>
                                            <option value="<?php echo esc_attr($kelas_option); ?>" <?php selected($selected_kelas, $kelas_option); ?>>
                                                <?php echo esc_html($kelas_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Opsional. Jika kosong, semua kelas akan diproses pada dokumen administrasi.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbt-card-ruang">Ruang</label></th>
                                <td>
                                    <select id="cbt-card-ruang" name="cbt_card_ruang">
                                        <option value="">Semua ruang</option>
                                        <?php foreach ($ruang_options as $ruang_option): ?>
                                            <option value="<?php echo esc_attr($ruang_option); ?>" <?php selected($selected_ruang, $ruang_option); ?>>
                                                <?php echo esc_html($ruang_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Opsional. Jika kosong, semua ruang pada filter kelas akan ikut dicetak.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbt-card-q">Cari Siswa</label></th>
                                <td>
                                    <input type="text" id="cbt-card-q" name="cbt_card_q" class="regular-text" value="<?php echo esc_attr($search); ?>" placeholder="Cari username / nama / email" />
                                    <p class="description">Opsional untuk mempersempit hasil siswa yang akan masuk ke dokumen.</p>
                                </td>
                            </tr>
                            <tr id="cbt-card-seat-row"<?php echo !$is_desk_number_mode ? ' class="cbt-exam-cards-row-hidden"' : ''; ?>>
                                <th><label for="cbt-card-seat-start">Pengaturan Nomor</label></th>
                                <td>
                                    <div id="cbt-card-seat-settings" class="cbt-exam-cards-seat-grid">
                                        <div class="cbt-exam-cards-seat-field">
                                            <label for="cbt-card-seat-start">Nomor Awal</label>
                                            <input type="number" min="1" step="1" id="cbt-card-seat-start" name="cbt_card_seat_start" value="<?php echo esc_attr((string) $seat_start_number); ?>" />
                                        </div>
                                        <div class="cbt-exam-cards-seat-field">
                                            <label for="cbt-card-seat-padding">Digit Padding</label>
                                            <input type="number" min="1" max="12" step="1" id="cbt-card-seat-padding" name="cbt_card_seat_padding" value="<?php echo esc_attr((string) $seat_padding); ?>" />
                                        </div>
                                    </div>
                                    <p id="cbt-card-seat-settings-note" class="description">Nomor meja dibentuk otomatis dari hasil filter siswa dengan urutan existing `kelas -> nama -> username`, lalu dimulai dari angka awal yang Anda tentukan.</p>
                                </td>
                            </tr>
                            <tr id="cbt-card-fields-row"<?php echo !$is_participant_mode ? ' class="cbt-exam-cards-row-hidden"' : ''; ?>>
                                <th>Informasi Kartu</th>
                                <td>
                                    <div class="cbt-exam-cards-field-grid">
                                        <?php foreach ($display_field_options as $field_key => $field_option): ?>
                                            <label class="cbt-exam-cards-field-option" for="<?php echo esc_attr('cbt-card-field-' . $field_key); ?>">
                                                <input
                                                    type="checkbox"
                                                    id="<?php echo esc_attr('cbt-card-field-' . $field_key); ?>"
                                                    name="cbt_card_fields[]"
                                                    value="<?php echo esc_attr($field_key); ?>"
                                                    <?php checked(in_array($field_key, $selected_display_fields, true)); ?>
                                                />
                                                <span>
                                                    <strong><?php echo esc_html((string) ($field_option['label'] ?? '')); ?></strong>
                                                    <span><?php echo esc_html((string) ($field_option['description'] ?? '')); ?></span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description">
                                        Pilih informasi yang ingin ditampilkan pada kartu. Default-nya semua aktif, dan minimal satu item harus dipilih.
                                        <?php if (!empty($selected_display_field_labels)): ?>
                                            <?php echo esc_html('Saat ini: ' . implode(', ', $selected_display_field_labels) . '.'); ?>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                            <tr id="cbt-card-minutes-row"<?php echo !$is_minutes_mode ? ' class="cbt-exam-cards-row-hidden"' : ''; ?>>
                                <th>Detail Berita Acara</th>
                                <td>
                                    <div class="cbt-exam-cards-minutes-grid">
                                        <div class="cbt-exam-cards-minutes-field is-wide">
                                            <label for="cbt-minutes-subject">Mata Pelajaran / Kegiatan</label>
                                            <input type="text" id="cbt-minutes-subject" name="cbt_minutes_subject" value="<?php echo esc_attr((string) ($minutes_fields['subject'] ?? '')); ?>" placeholder="Contoh: Matematika" />
                                        </div>
                                        <div class="cbt-exam-cards-minutes-field">
                                            <label for="cbt-minutes-date">Tanggal Pelaksanaan</label>
                                            <input type="date" id="cbt-minutes-date" name="cbt_minutes_date" value="<?php echo esc_attr((string) ($minutes_fields['date'] ?? '')); ?>" />
                                        </div>
                                        <div class="cbt-exam-cards-minutes-field">
                                            <label for="cbt-minutes-room">Ruang</label>
                                            <input type="text" id="cbt-minutes-room" name="cbt_minutes_room" value="<?php echo esc_attr((string) ($minutes_fields['room'] ?? '')); ?>" />
                                        </div>
                                        <div class="cbt-exam-cards-minutes-field">
                                            <label for="cbt-minutes-start-time">Jam Mulai</label>
                                            <input type="time" id="cbt-minutes-start-time" name="cbt_minutes_start_time" value="<?php echo esc_attr((string) ($minutes_fields['start_time'] ?? '')); ?>" />
                                        </div>
                                        <div class="cbt-exam-cards-minutes-field">
                                            <label for="cbt-minutes-end-time">Jam Selesai</label>
                                            <input type="time" id="cbt-minutes-end-time" name="cbt_minutes_end_time" value="<?php echo esc_attr((string) ($minutes_fields['end_time'] ?? '')); ?>" />
                                        </div>
                                        <div class="cbt-exam-cards-minutes-field">
                                            <label for="cbt-minutes-proctor-name">Nama Proktor</label>
                                            <input type="text" id="cbt-minutes-proctor-name" name="cbt_minutes_proctor_name" value="<?php echo esc_attr((string) ($minutes_fields['proctor_name'] ?? '')); ?>" />
                                        </div>
                                        <div class="cbt-exam-cards-minutes-field">
                                            <label for="cbt-minutes-supervisor-name">Nama Pengawas</label>
                                            <input type="text" id="cbt-minutes-supervisor-name" name="cbt_minutes_supervisor_name" value="<?php echo esc_attr((string) ($minutes_fields['supervisor_name'] ?? '')); ?>" />
                                        </div>
                                        <div class="cbt-exam-cards-minutes-field is-wide">
                                            <label for="cbt-minutes-notes">Catatan Pelaksanaan</label>
                                            <textarea id="cbt-minutes-notes" name="cbt_minutes_notes" rows="4" placeholder="Catatan kejadian, kendala, atau keterangan tambahan."><?php echo esc_textarea((string) ($minutes_fields['notes'] ?? '')); ?></textarea>
                                        </div>
                                    </div>
                                    <p class="description">Default diambil dari jadwal publish yang cocok dengan filter kelas. Semua field ini hanya dipakai untuk dokumen cetak.</p>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <p id="cbt-card-participant-note" class="cbt-exam-cards-note<?php echo !$is_participant_mode ? ' cbt-exam-cards-row-hidden' : ''; ?>">
                            Password pada kartu akan memakai nilai tersimpan. Jika masih kosong, sistem akan membuat password 6 digit otomatis untuk siswa tersebut saat proses generate berjalan. Opsi tampilan di atas hanya mengatur field yang dicetak, bukan mengubah data siswa.
                        </p>
                        <p id="cbt-card-desk-note" class="cbt-exam-cards-note<?php echo !$is_desk_number_mode ? ' cbt-exam-cards-row-hidden' : ''; ?>">
                            Mode nomor meja hanya membuat urutan angka print-only untuk hasil filter saat ini. Tidak ada password, foto, atau data profil siswa yang diubah saat generate berlangsung.
                        </p>
                        <p id="cbt-card-attendance-note" class="cbt-exam-cards-note<?php echo !$is_attendance_mode ? ' cbt-exam-cards-row-hidden' : ''; ?>">
                            Daftar hadir hanya mencetak identitas peserta dan kolom tanda tangan sesuai filter saat ini. Tidak ada password, foto, atau data profil siswa yang diubah saat generate berlangsung.
                        </p>
                        <p id="cbt-card-minutes-note" class="cbt-exam-cards-note<?php echo !$is_minutes_mode ? ' cbt-exam-cards-row-hidden' : ''; ?>">
                            Berita acara memakai total peserta dari filter saat ini dan detail sesi dari field di atas. Data ini bersifat print-only.
                        </p>
                        <div class="cbt-exam-cards-form-actions">
                            <button id="cbt-card-submit-button" class="button button-primary" type="submit">
                                <?php echo esc_html($selected_submit_label); ?>
                            </button>
                            <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary" data-cbt-exam-cards-async-link data-cbt-exam-cards-progress-profile="reset" data-cbt-exam-cards-refresh-areas="overview,notices,summary,form">Reset Filter</a>
                        </div>
                    </form>
                </section>

                <section class="cbt-exam-cards-insights" aria-hidden="true">
                    <div class="cbt-exam-cards-insight">
                        <strong>Output siap cetak</strong>
                        <p>Layout dibuat untuk kertas A4 portrait, jadi operator bisa langsung simpan atau print ke PDF.</p>
                    </div>
                    <div class="cbt-exam-cards-insight">
                        <strong>Filter fleksibel</strong>
                        <p>Anda bisa cetak per kelas, per ruang, atau gabungkan dengan pencarian siswa tertentu tanpa mengubah data user.</p>
                    </div>
                    <div class="cbt-exam-cards-insight">
                        <strong>Tampilan fleksibel</strong>
                        <p>Foto, identitas login, kelas, jenis kelamin, agama, ruangan, sampai jadwal ujian bisa dipilih sesuai kebutuhan dokumen yang ingin dibagikan.</p>
                    </div>
                </section>
            </div>
        </div>
        <script>
            (function () {
                const modeDescriptions = <?php echo wp_json_encode($print_mode_options); ?>;
                const progressProfiles = {
                    generate: {
                        title: 'Generate dokumen sedang berjalan',
                        steps: [
                            'Memvalidasi mode cetak, filter kelas, ruang, dan pencarian siswa.',
                            'Membuka tab cetak agar halaman admin tetap di tempat.',
                            'Menyiapkan data peserta, jadwal publish, dan detail dokumen.',
                            'Membangun layout print A4. Tab cetak akan siap sebentar lagi.'
                        ],
                        wait: 'Menunggu halaman print selesai dimuat di tab baru.'
                    },
                    reset: {
                        title: 'Mereset filter Administrative Documents',
                        steps: [
                            'Mengambil ulang konfigurasi awal dari server.',
                            'Memperbarui ringkasan, filter, dan form di area ini.',
                            'Menyambungkan ulang kontrol mode cetak tanpa reload global.'
                        ],
                        wait: 'Menunggu respons halaman Administrative Documents.'
                    }
                };
                let progressTimer = null;
                let progressValue = 0;

                function getRoot() {
                    return document.querySelector('[data-cbt-exam-cards-root]');
                }

                function getProgressParts() {
                    const root = getRoot();
                    const progress = root ? root.querySelector('[data-cbt-exam-cards-progress]') : null;

                    return {
                        root: root,
                        progress: progress,
                        title: progress ? progress.querySelector('[data-cbt-exam-cards-progress-title]') : null,
                        step: progress ? progress.querySelector('[data-cbt-exam-cards-progress-step]') : null,
                        percent: progress ? progress.querySelector('[data-cbt-exam-cards-progress-percent]') : null,
                        fill: progress ? progress.querySelector('[data-cbt-exam-cards-progress-fill]') : null,
                        track: progress ? progress.querySelector('[data-cbt-exam-cards-progress-track]') : null
                    };
                }

                function setExamCardsProgress(percent, stepText, state) {
                    const parts = getProgressParts();
                    const safePercent = Math.max(0, Math.min(100, Math.round(percent)));
                    progressValue = safePercent;

                    if (!parts.progress) {
                        return;
                    }

                    parts.progress.classList.toggle('is-active', state === 'active');
                    parts.progress.classList.toggle('is-complete', state === 'complete');
                    parts.progress.classList.toggle('is-error', state === 'error');
                    parts.progress.setAttribute('aria-hidden', 'false');
                    if (parts.root) {
                        parts.root.classList.toggle('is-local-busy', state === 'active');
                    }
                    if (parts.percent) {
                        parts.percent.textContent = safePercent + '%';
                    }
                    if (parts.fill) {
                        parts.fill.style.width = safePercent + '%';
                    }
                    if (parts.track) {
                        parts.track.setAttribute('aria-valuenow', String(safePercent));
                    }
                    if (parts.step && stepText) {
                        parts.step.textContent = stepText;
                    }
                }

                function startExamCardsProgress(profileKey) {
                    const profile = progressProfiles[profileKey] || progressProfiles.generate;
                    const parts = getProgressParts();
                    const steps = profile.steps || [];
                    let stepIndex = 0;

                    window.clearInterval(progressTimer);
                    progressValue = 8;
                    if (parts.title) {
                        parts.title.textContent = profile.title || 'Memproses Administrative Documents';
                    }
                    setExamCardsProgress(progressValue, steps[0] || profile.wait || 'Memproses area Administrative Documents.', 'active');

                    progressTimer = window.setInterval(function () {
                        const ceiling = profileKey === 'generate' ? 92 : 88;
                        if (progressValue >= ceiling) {
                            setExamCardsProgress(progressValue, profile.wait || steps[steps.length - 1] || 'Menunggu server selesai.', 'active');
                            return;
                        }

                        progressValue += progressValue < 45 ? 9 : 5;
                        stepIndex = Math.min(steps.length - 1, Math.floor((progressValue / 100) * Math.max(1, steps.length)));
                        setExamCardsProgress(Math.min(progressValue, ceiling), steps[stepIndex] || profile.wait || 'Memproses area Administrative Documents.', 'active');
                    }, 420);
                }

                function completeExamCardsProgress(titleText, stepText, state) {
                    const parts = getProgressParts();
                    window.clearInterval(progressTimer);
                    progressTimer = null;
                    if (parts.title && titleText) {
                        parts.title.textContent = titleText;
                    }
                    setExamCardsProgress(state === 'error' ? Math.max(progressValue, 100) : 100, stepText, state || 'complete');
                    if (parts.root) {
                        parts.root.classList.remove('is-local-busy');
                    }
                }

                function showExamCardsLocalError(titleText, detailText) {
                    completeExamCardsProgress(titleText, detailText, 'error');
                }

                function replaceExamCardsRefreshAreas(html, areas) {
                    const parser = new DOMParser();
                    const parsed = parser.parseFromString(html, 'text/html');
                    const currentRoot = getRoot();
                    const nextRoot = parsed.querySelector('[data-cbt-exam-cards-root]');
                    let replaced = 0;

                    if (!currentRoot || !nextRoot) {
                        return 0;
                    }

                    areas.forEach(function (area) {
                        const selector = '[data-cbt-exam-cards-refresh-area="' + area + '"]';
                        const currentNode = currentRoot.querySelector(selector);
                        const nextNode = nextRoot.querySelector(selector);

                        if (!currentNode || !nextNode) {
                            return;
                        }

                        currentNode.replaceWith(nextNode.cloneNode(true));
                        replaced += 1;
                    });

                    return replaced;
                }

                async function runExamCardsLocalRefresh(source, requestUrl, options) {
                    const config = options || {};
                    const areas = config.areas || ['overview', 'notices', 'summary', 'form'];

                    startExamCardsProgress(config.profile || 'reset');
                    source.setAttribute('aria-busy', 'true');
                    source.classList.add('is-loading');

                    try {
                        const response = await window.fetch(requestUrl.toString(), {
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const responseText = await response.text();

                        if (!response.ok) {
                            const errorText = responseText
                                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                                .replace(/<[^>]*>/g, ' ')
                                .replace(/\s+/g, ' ')
                                .trim()
                                .slice(0, 180);
                            throw new Error('HTTP ' + response.status + (errorText ? ': ' + errorText : ''));
                        }

                        const replaced = replaceExamCardsRefreshAreas(responseText, areas);
                        if (replaced === 0) {
                            throw new Error('Respons server tidak berisi area Administrative Documents yang bisa diganti.');
                        }

                        bindExamCardsUi();
                        completeExamCardsProgress('Area Administrative Documents sudah diperbarui', 'Filter, ringkasan, dan form berhasil disegarkan tanpa reload halaman global.', 'complete');
                    } catch (error) {
                        const message = error && error.message ? error.message : 'Koneksi gagal saat memperbarui area Administrative Documents.';
                        showExamCardsLocalError('Gagal memperbarui area Administrative Documents', message);
                    } finally {
                        source.removeAttribute('aria-busy');
                        source.classList.remove('is-loading');
                    }
                }

                function validatePrintForm(form) {
                    const modeInput = form.querySelector('#cbt-card-print-mode');
                    const activeMode = modeInput && modeDescriptions[modeInput.value] ? modeInput.value : 'participant';

                    if (activeMode !== 'participant') {
                        return true;
                    }

                    if (form.querySelector('input[name="cbt_card_fields[]"]:checked')) {
                        return true;
                    }

                    showExamCardsLocalError(
                        'Kartu belum bisa digenerate',
                        'Pilih minimal satu informasi kartu sebelum generate kartu peserta.'
                    );
                    return false;
                }

                function watchPrintWindow(printWindow, submitButton) {
                    const startedAt = Date.now();
                    const timeoutMs = 45000;
                    let settled = false;

                    const finish = function (title, message, state) {
                        if (settled) {
                            return;
                        }
                        settled = true;
                        window.clearInterval(timer);
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                        completeExamCardsProgress(title, message, state);
                    };

                    const timer = window.setInterval(function () {
                        if (!printWindow || printWindow.closed) {
                            finish('Tab cetak ditutup', 'Generate dihentikan karena tab print ditutup sebelum halaman selesai dimuat.', 'error');
                            return;
                        }

                        try {
                            const href = String(printWindow.location.href || '');
                            const readyState = printWindow.document ? printWindow.document.readyState : '';
                            if (href && href !== 'about:blank' && readyState === 'complete') {
                                finish('Halaman print siap', 'Tab cetak sudah terbuka. Gunakan tombol print atau simpan PDF dari tab tersebut.', 'complete');
                                return;
                            }
                        } catch (error) {
                            finish('Tab cetak sudah dibuka', 'Browser membatasi pengecekan tab baru, tetapi halaman admin tetap tidak direload.', 'complete');
                            return;
                        }

                        if (Date.now() - startedAt > timeoutMs) {
                            finish('Menunggu tab cetak', 'Jika tab cetak sudah terbuka, proses bisa dilanjutkan dari tab tersebut. Halaman admin tetap aman.', 'complete');
                        }
                    }, 500);
                }

                function bindPrintForm(form) {
                    if (!form || form.dataset.cbtExamCardsPrintBound === '1') {
                        return;
                    }

                    form.dataset.cbtExamCardsPrintBound = '1';
                    form.addEventListener('submit', function (event) {
                        if (!validatePrintForm(form)) {
                            event.preventDefault();
                            return;
                        }

                        const submitButton = form.querySelector('#cbt-card-submit-button');
                        const targetName = 'cbt_exam_cards_print_' + Date.now();
                        const printWindow = window.open('about:blank', targetName);

                        if (!printWindow) {
                            event.preventDefault();
                            showExamCardsLocalError(
                                'Tab cetak diblokir browser',
                                'Izinkan pop-up untuk halaman admin ini, lalu klik Generate lagi agar halaman admin tidak berpindah.'
                            );
                            return;
                        }

                        form.setAttribute('target', targetName);
                        startExamCardsProgress('generate');
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        watchPrintWindow(printWindow, submitButton);
                    });
                }

                function bindResetLinks(root) {
                    root.querySelectorAll('[data-cbt-exam-cards-async-link]').forEach(function (link) {
                        if (link.dataset.cbtExamCardsAsyncBound === '1') {
                            return;
                        }

                        link.dataset.cbtExamCardsAsyncBound = '1';
                        link.addEventListener('click', function (event) {
                            const href = link.getAttribute('href');
                            if (!href) {
                                return;
                            }

                            event.preventDefault();
                            const areas = (link.getAttribute('data-cbt-exam-cards-refresh-areas') || 'overview,notices,summary,form')
                                .split(',')
                                .map(function (area) {
                                    return area.trim();
                                })
                                .filter(Boolean);
                            runExamCardsLocalRefresh(
                                link,
                                new URL(href, document.baseURI),
                                {
                                    areas: areas,
                                    profile: link.getAttribute('data-cbt-exam-cards-progress-profile') || 'reset'
                                }
                            );
                        });
                    });
                }

                function bindExamCardsUi() {
                    const root = getRoot();
                    if (!root) {
                        return;
                    }

                    const modeInput = root.querySelector('#cbt-card-print-mode');
                    const fieldsRow = root.querySelector('#cbt-card-fields-row');
                    const minutesRow = root.querySelector('#cbt-card-minutes-row');
                    const seatRow = root.querySelector('#cbt-card-seat-row');
                    const participantNote = root.querySelector('#cbt-card-participant-note');
                    const deskNote = root.querySelector('#cbt-card-desk-note');
                    const attendanceNote = root.querySelector('#cbt-card-attendance-note');
                    const minutesNote = root.querySelector('#cbt-card-minutes-note');
                    const modeTitle = root.querySelector('#cbt-card-mode-title');
                    const modeCopy = root.querySelector('#cbt-card-mode-copy');
                    const modePill = root.querySelector('#cbt-card-mode-pill');
                    const modeSummary = root.querySelector('#cbt-card-mode-summary');
                    const submitButton = root.querySelector('#cbt-card-submit-button');
                    const fieldInputs = fieldsRow ? fieldsRow.querySelectorAll('input[type="checkbox"]') : [];
                    const seatInputs = seatRow ? seatRow.querySelectorAll('input') : [];
                    const minutesInputs = minutesRow ? minutesRow.querySelectorAll('input, textarea') : [];
                    const modeTabs = root.querySelectorAll('[data-print-mode-tab]');
                    const form = root.querySelector('[data-cbt-exam-cards-print-form]');

                    if (!modeInput) {
                        return;
                    }

                    const syncPrintMode = function () {
                        const activeMode = modeDescriptions[modeInput.value] ? modeInput.value : 'participant';
                        const isDeskMode = activeMode === 'desk_number';
                        const isParticipantMode = activeMode === 'participant';
                        const isAttendanceMode = activeMode === 'attendance';
                        const isMinutesMode = activeMode === 'minutes';
                        const activeModeDescription = modeDescriptions[activeMode] || {};

                        if (fieldsRow) {
                            fieldsRow.classList.toggle('cbt-exam-cards-row-hidden', !isParticipantMode);
                        }
                        if (minutesRow) {
                            minutesRow.classList.toggle('cbt-exam-cards-row-hidden', !isMinutesMode);
                        }
                        if (seatRow) {
                            seatRow.classList.toggle('cbt-exam-cards-row-hidden', !isDeskMode);
                        }
                        if (participantNote) {
                            participantNote.classList.toggle('cbt-exam-cards-row-hidden', !isParticipantMode);
                        }
                        if (deskNote) {
                            deskNote.classList.toggle('cbt-exam-cards-row-hidden', !isDeskMode);
                        }
                        if (attendanceNote) {
                            attendanceNote.classList.toggle('cbt-exam-cards-row-hidden', !isAttendanceMode);
                        }
                        if (minutesNote) {
                            minutesNote.classList.toggle('cbt-exam-cards-row-hidden', !isMinutesMode);
                        }

                        fieldInputs.forEach(function (input) {
                            input.disabled = !isParticipantMode;
                        });
                        seatInputs.forEach(function (input) {
                            input.disabled = !isDeskMode;
                        });
                        minutesInputs.forEach(function (input) {
                            input.disabled = !isMinutesMode;
                        });

                        if (modeTitle) {
                            modeTitle.textContent = activeModeDescription.label || activeMode;
                        }
                        if (modeCopy) {
                            modeCopy.textContent = activeModeDescription.description || '';
                        }
                        if (modePill) {
                            modePill.textContent = 'Mode: ' + (activeModeDescription.label || activeMode);
                        }
                        if (modeSummary) {
                            modeSummary.textContent = 'Mode: ' + (activeModeDescription.label || activeMode);
                        }
                        if (submitButton) {
                            submitButton.textContent = activeModeDescription.submit_label || 'Generate & Print Dokumen';
                        }

                        modeTabs.forEach(function (tab) {
                            const isActive = tab.getAttribute('data-print-mode-tab') === activeMode;
                            tab.classList.toggle('is-active', isActive);
                            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        });
                    };

                    modeTabs.forEach(function (tab) {
                        if (tab.dataset.cbtExamCardsModeBound === '1') {
                            return;
                        }
                        tab.dataset.cbtExamCardsModeBound = '1';
                        tab.addEventListener('click', function () {
                            const nextMode = tab.getAttribute('data-print-mode-tab');
                            if (!nextMode) {
                                return;
                            }
                            modeInput.value = nextMode;
                            syncPrintMode();
                        });
                    });

                    bindPrintForm(form);
                    bindResetLinks(root);
                    syncPrintMode();
                }

                bindExamCardsUi();
            })();
        </script>
        <?php
