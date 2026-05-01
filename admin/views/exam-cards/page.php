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
            .cbt-exam-cards-panel input[type="number"],
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
            .cbt-exam-cards-panel input[type="number"] {
                width: min(100%, 720px);
                max-width: none;
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
            }
        </style>
        <div class="wrap cbt-exam-cards-page">
            <div class="cbt-exam-cards-shell">
                <section class="cbt-exam-cards-hero">
                    <div class="cbt-exam-cards-hero-copy">
                        <span class="cbt-exam-cards-kicker">Print</span>
                        <h1>CBT Exam Cards</h1>
                        <p>Generate kartu peserta ujian berdasarkan filter siswa dengan output siap cetak untuk PDF A4. Operator bisa pilih filter lalu atur informasi apa saja yang mau ikut tercetak di kartu.</p>
                    </div>
                    <div class="cbt-exam-cards-overview" aria-hidden="true">
                        <span class="cbt-exam-cards-pill"><?php echo esc_html(sprintf('%d kelas tersedia', count($kelas_options))); ?></span>
                        <span class="cbt-exam-cards-pill"><?php echo esc_html(sprintf('%d ruang tersedia', count($ruang_options))); ?></span>
                        <span class="cbt-exam-cards-pill"><?php echo esc_html(sprintf('%d filter aktif', $active_filter_count)); ?></span>
                        <span id="cbt-card-mode-pill" class="cbt-exam-cards-pill"><?php echo esc_html('Mode: ' . $selected_print_mode_label); ?></span>
                    </div>
                </section>

                <section class="cbt-exam-cards-panel">
                    <div class="cbt-exam-cards-panel-header">
                        <div>
                            <h2>Filter & Generate Cards</h2>
                            <p>Pilih kelas, ruang, atau kata kunci siswa untuk membatasi kartu yang akan digenerate. Setelah itu, tentukan apakah outputnya berupa kartu peserta lengkap atau nomor meja besar siap cetak.</p>
                        </div>
                        <span class="cbt-exam-cards-chip">PDF A4 • 6 kartu / halaman</span>
                    </div>

            <?php if ($notice): ?>
                        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

                    <div class="cbt-exam-cards-summary" aria-hidden="true">
                        <span class="cbt-exam-cards-summary-label">Ringkasan:</span>
                        <span id="cbt-card-mode-summary" class="cbt-exam-cards-summary-item"><?php echo esc_html('Mode: ' . $selected_print_mode_label); ?></span>
                        <span class="cbt-exam-cards-summary-item"><?php echo esc_html('Kelas: ' . ($selected_kelas !== '' ? $selected_kelas : 'Semua kelas')); ?></span>
                        <span class="cbt-exam-cards-summary-item"><?php echo esc_html('Ruang: ' . ($selected_ruang !== '' ? $selected_ruang : 'Semua ruang')); ?></span>
                        <span class="cbt-exam-cards-summary-item"><?php echo esc_html(sprintf('Jadwal publish: %d exam', $schedule_count)); ?></span>
                        <span class="cbt-exam-cards-summary-item"><?php echo esc_html(sprintf('Info kartu: %d item', $display_field_count)); ?></span>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('cbt_print_exam_cards'); ?>
                        <input type="hidden" name="action" value="cbt_print_exam_cards" />
                        <input type="hidden" name="cbt_card_fields_configured" value="1" />
                        <table class="form-table" role="presentation">
                            <tbody>
                            <tr>
                                <th>Mode Cetak</th>
                                <td>
                                    <input type="hidden" id="cbt-card-print-mode" name="cbt_card_print_mode" value="<?php echo esc_attr($selected_print_mode); ?>" />
                                    <div class="cbt-exam-cards-tabs" role="tablist" aria-label="Mode cetak exam cards">
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
                                    <p class="description">Opsional. Jika kosong, semua kelas akan diproses pada kartu ujian.</p>
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
                                    <p class="description">Opsional untuk mempersempit hasil siswa yang akan masuk ke kartu.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="cbt-card-seat-start">Pengaturan Nomor</label></th>
                                <td>
                                    <div id="cbt-card-seat-settings" class="cbt-exam-cards-seat-grid<?php echo !$is_desk_number_mode ? ' cbt-exam-cards-row-hidden' : ''; ?>">
                                        <div class="cbt-exam-cards-seat-field">
                                            <label for="cbt-card-seat-start">Nomor Awal</label>
                                            <input type="number" min="1" step="1" id="cbt-card-seat-start" name="cbt_card_seat_start" value="<?php echo esc_attr((string) $seat_start_number); ?>" />
                                        </div>
                                        <div class="cbt-exam-cards-seat-field">
                                            <label for="cbt-card-seat-padding">Digit Padding</label>
                                            <input type="number" min="1" max="12" step="1" id="cbt-card-seat-padding" name="cbt_card_seat_padding" value="<?php echo esc_attr((string) $seat_padding); ?>" />
                                        </div>
                                    </div>
                                    <p id="cbt-card-seat-settings-note" class="description<?php echo !$is_desk_number_mode ? ' cbt-exam-cards-row-hidden' : ''; ?>">Nomor meja dibentuk otomatis dari hasil filter siswa dengan urutan existing `kelas -> nama -> username`, lalu dimulai dari angka awal yang Anda tentukan.</p>
                                </td>
                            </tr>
                            <tr id="cbt-card-fields-row"<?php echo $is_desk_number_mode ? ' class="cbt-exam-cards-row-hidden"' : ''; ?>>
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
                            </tbody>
                        </table>
                        <p id="cbt-card-participant-note" class="cbt-exam-cards-note<?php echo $is_desk_number_mode ? ' cbt-exam-cards-row-hidden' : ''; ?>">
                            Password pada kartu akan memakai nilai tersimpan. Jika masih kosong, sistem akan membuat password 6 digit otomatis untuk siswa tersebut saat proses generate berjalan. Opsi tampilan di atas hanya mengatur field yang dicetak, bukan mengubah data siswa.
                        </p>
                        <p id="cbt-card-desk-note" class="cbt-exam-cards-note<?php echo !$is_desk_number_mode ? ' cbt-exam-cards-row-hidden' : ''; ?>">
                            Mode nomor meja hanya membuat urutan angka print-only untuk hasil filter saat ini. Tidak ada password, foto, atau data profil siswa yang diubah saat generate berlangsung.
                        </p>
                        <div class="cbt-exam-cards-form-actions">
                            <button id="cbt-card-submit-button" class="button button-primary" type="submit">
                                <?php echo esc_html($is_desk_number_mode ? 'Generate & Print Nomor Meja' : 'Generate & Print Kartu'); ?>
                            </button>
                            <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary">Reset Filter</a>
                        </div>
                    </form>
                </section>

                <section class="cbt-exam-cards-insights" aria-hidden="true">
                    <div class="cbt-exam-cards-insight">
                        <strong>Output siap cetak</strong>
                        <p>Layout dibuat untuk kertas A4 portrait dengan 6 kartu per halaman, jadi operator bisa langsung simpan atau print ke PDF.</p>
                    </div>
                    <div class="cbt-exam-cards-insight">
                        <strong>Filter fleksibel</strong>
                        <p>Anda bisa cetak per kelas, per ruang, atau gabungkan dengan pencarian siswa tertentu tanpa mengubah data user.</p>
                    </div>
                    <div class="cbt-exam-cards-insight">
                        <strong>Tampilan fleksibel</strong>
                        <p>Foto, identitas login, kelas, jenis kelamin, agama, ruangan, sampai jadwal ujian sekarang bisa dipilih sesuai kebutuhan kartu yang ingin dibagikan.</p>
                    </div>
                </section>
            </div>
        </div>
        <script>
            (function () {
                const modeInput = document.getElementById('cbt-card-print-mode');
                const fieldsRow = document.getElementById('cbt-card-fields-row');
                const seatSettings = document.getElementById('cbt-card-seat-settings');
                const seatSettingsNote = document.getElementById('cbt-card-seat-settings-note');
                const participantNote = document.getElementById('cbt-card-participant-note');
                const deskNote = document.getElementById('cbt-card-desk-note');
                const modeTitle = document.getElementById('cbt-card-mode-title');
                const modeCopy = document.getElementById('cbt-card-mode-copy');
                const modePill = document.getElementById('cbt-card-mode-pill');
                const modeSummary = document.getElementById('cbt-card-mode-summary');
                const submitButton = document.getElementById('cbt-card-submit-button');
                const fieldInputs = fieldsRow ? fieldsRow.querySelectorAll('input[type="checkbox"]') : [];
                const modeTabs = document.querySelectorAll('[data-print-mode-tab]');
                const modeDescriptions = <?php echo wp_json_encode($print_mode_options); ?>;

                if (!modeInput) {
                    return;
                }

                const syncPrintMode = function () {
                    const activeMode = modeInput.value === 'desk_number' ? 'desk_number' : 'participant';
                    const isDeskMode = activeMode === 'desk_number';
                    const activeModeDescription = modeDescriptions[activeMode] || {};

                    if (fieldsRow) {
                        fieldsRow.classList.toggle('cbt-exam-cards-row-hidden', isDeskMode);
                    }
                    if (seatSettings) {
                        seatSettings.classList.toggle('cbt-exam-cards-row-hidden', !isDeskMode);
                    }
                    if (seatSettingsNote) {
                        seatSettingsNote.classList.toggle('cbt-exam-cards-row-hidden', !isDeskMode);
                    }
                    if (participantNote) {
                        participantNote.classList.toggle('cbt-exam-cards-row-hidden', isDeskMode);
                    }
                    if (deskNote) {
                        deskNote.classList.toggle('cbt-exam-cards-row-hidden', !isDeskMode);
                    }

                    fieldInputs.forEach(function (input) {
                        input.disabled = isDeskMode;
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
                        submitButton.textContent = isDeskMode ? 'Generate & Print Nomor Meja' : 'Generate & Print Kartu';
                    }

                    modeTabs.forEach(function (tab) {
                        const isActive = tab.getAttribute('data-print-mode-tab') === activeMode;
                        tab.classList.toggle('is-active', isActive);
                        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });
                };

                modeTabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        const nextMode = tab.getAttribute('data-print-mode-tab');
                        if (!nextMode) {
                            return;
                        }
                        modeInput.value = nextMode;
                        syncPrintMode();
                    });
                });

                syncPrintMode();
            })();
        </script>
        <?php
