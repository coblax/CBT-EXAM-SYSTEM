        <style>
            .cbt-token-page {
                max-width: 1120px;
                animation: cbtSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                opacity: 0;
                padding-bottom: 40px;
            }
            @keyframes cbtSlideUp {
                0% { opacity: 0; transform: translateY(15px); }
                100% { opacity: 1; transform: translateY(0); }
            }
            .cbt-token-shell {
                display: grid;
                gap: 20px;
                margin-top: 16px;
            }
            .cbt-token-hero {
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
                    0 20px 40px rgba(15, 23, 42, 0.06), 
                    inset 0 2px 0 rgba(255, 255, 255, 0.9);
            }
            .cbt-token-hero::before {
                content: ''; position: absolute;
                top: -200px; right: -200px; width: 600px; height: 600px;
                background: radial-gradient(circle, rgba(34, 197, 94, 0.12) 0%, transparent 70%);
                border-radius: 50%; pointer-events: none;
            }
            .cbt-token-hero-copy {
                flex: 1;
                margin-right: 32px;
                position: relative;
                z-index: 2;
            }
            .cbt-token-kicker {
                display: inline-flex;
                align-items: center;
                min-height: 26px;
                padding: 0 12px;
                border-radius: 999px;
                background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
                color: #ffffff;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                box-shadow: 0 4px 12px rgba(34, 197, 94, 0.25);
                margin-bottom: 10px;
            }
            .cbt-token-hero h1 {
                margin: 0 0 10px;
                font-size: 32px;
                font-weight: 800;
                line-height: 1.15;
                color: #0f172a;
                letter-spacing: -0.02em;
            }
            .cbt-token-hero p {
                margin: 0;
                color: #475569;
                font-size: 16px;
                line-height: 1.6;
                text-align: justify;
            }
            .cbt-token-live-panel {
                position: relative;
                z-index: 2;
                display: grid;
                grid-template-columns: auto 1fr;
                gap: 0 24px;
                padding: 24px 28px;
                min-width: 480px;
                border: 1px solid rgba(255, 255, 255, 0.8);
                border-radius: 20px;
                background: rgba(255, 255, 255, 0.5);
                backdrop-filter: blur(20px);
                box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04), inset 0 0 0 1px rgba(255,255,255,0.4);
                flex-shrink: 0;
            }
            .cbt-token-live-label {
                grid-column: 1 / -1;
                color: #64748b;
                font-size: 11.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                margin-bottom: 12px;
                text-align: left;
            }
            .cbt-token-live-value {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 68px;
                padding: 0 32px;
                border-radius: 14px;
                background: #0f172a;
                color: #38bdf8;
                font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
                font-size: 34px;
                font-weight: 800;
                letter-spacing: 0.28em;
                text-shadow: 0 0 20px rgba(56, 189, 248, 0.4);
                box-shadow: inset 0 2px 10px rgba(0,0,0,0.5), 0 14px 24px rgba(15,23,42,0.2);
                margin-bottom: 0;
                position: relative;
            }
            .cbt-token-live-value::after {
                 content: ''; position: absolute; top:0; left:0; right:0; bottom:0;
                 background: linear-gradient(180deg, rgba(255,255,255,0.08) 0%, transparent 100%);
                 border-radius: 14px; pointer-events: none;
            }
            .cbt-token-live-meta {
                display: grid;
                gap: 10px;
                align-content: center;
            }
            .cbt-token-live-meta-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                padding: 8px 16px;
                background: rgba(255,255,255,0.8);
                border-radius: 8px;
                color: #475569;
                font-size: 13px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
                white-space: nowrap;
            }
            .cbt-token-live-meta-item strong {
                font-weight: 700;
                color: #0f172a;
            }
            .cbt-token-form {
                display: grid;
                gap: 20px;
            }
            .cbt-token-card {
                padding: 28px 32px;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 14px 32px rgba(15, 23, 42, 0.04);
            }
            .cbt-token-card-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 24px;
                padding-bottom: 20px;
                border-bottom: 1px solid #f1f5f9;
            }
            .cbt-token-card-header h2 {
                margin: 0 0 6px;
                font-size: 20px;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.2;
            }
            .cbt-token-card-header p {
                margin: 0;
                color: #64748b;
                line-height: 1.6;
                font-size: 15px;
            }
            .cbt-token-card-chip {
                display: inline-flex;
                align-items: center;
                height: 32px;
                padding: 0 16px;
                border-radius: 999px;
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                color: #166534;
                font-size: 13px;
                font-weight: 700;
                white-space: nowrap;
                box-shadow: 0 2px 4px rgba(22, 163, 74, 0.05);
            }
            .cbt-token-field-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 28px;
            }
            .cbt-token-field {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .cbt-token-field--full {
                grid-column: 1 / -1;
            }
            .cbt-token-field > label {
                font-weight: 600;
                color: #1e293b;
                font-size: 14.5px;
            }
            .cbt-token-field input[type="text"],
            .cbt-token-field select {
                width: 100%;
                height: 48px;
                margin: 0;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                background: #f8fafc;
                color: #0f172a;
                padding: 0 14px;
                font-size: 15px;
                transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
            }
            .cbt-token-field input[type="text"] {
                font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
                font-size: 20px;
                font-weight: 800;
                letter-spacing: 0.2em;
                text-transform: uppercase;
                color: #1d4ed8;
            }
            .cbt-token-field input[type="text"]:focus,
            .cbt-token-field select:focus {
                border-color: #3b82f6;
                background: #ffffff;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
                outline: none;
            }
            .cbt-token-field .description {
                margin: 0;
                color: #64748b;
                line-height: 1.5;
                font-size: 13.5px;
            }
            .cbt-token-note-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 16px;
                margin-top: 24px;
                padding-top: 24px;
                border-top: 1px solid #f1f5f9;
            }
            .cbt-token-note-card {
                padding: 16px 20px;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                background: #f8fafc;
                transition: transform 200ms ease, box-shadow 200ms ease;
            }
            .cbt-token-note-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 24px rgba(15,23,42,0.06);
                background: #ffffff;
                border-color: #cbd5e1;
            }
            .cbt-token-note-card strong {
                display: block;
                margin-bottom: 8px;
                color: #0f172a;
                font-size: 14.5px;
                font-weight: 700;
            }
            .cbt-token-note-card p {
                margin: 0;
                color: #475569;
                line-height: 1.5;
                font-size: 14px;
            }
            .cbt-token-toggle {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 18px 20px;
                border: 2px solid #e2e8f0;
                border-radius: 14px;
                background: #ffffff;
                cursor: pointer;
                transition: all 200ms ease;
            }
            .cbt-token-toggle:hover {
                border-color: #cbd5e1;
                background: #f8fafc;
            }
            .cbt-token-toggle input[type="checkbox"] {
                margin: 4px 0 0;
                width: 22px;
                height: 22px;
                border-radius: 6px;
                border: 2px solid #cbd5e1;
                cursor: pointer;
                transition: all 150ms ease;
            }
            .cbt-token-toggle input[type="checkbox"]:checked {
                background-color: #22c55e;
                border-color: #22c55e;
            }
            .cbt-token-toggle strong {
                display: block;
                margin-bottom: 6px;
                color: #0f172a;
                font-size: 15.5px;
                font-weight: 700;
            }
            .cbt-token-toggle span {
                display: block;
                color: #64748b;
                line-height: 1.5;
                font-size: 14px;
            }
            .cbt-token-actions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 24px 28px;
                border: 1px solid #dcdcde;
                border-radius: 16px;
                background: #ffffff;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
            }
            .cbt-token-actions-copy {
                margin: 0;
                color: #475569;
                line-height: 1.55;
                font-size: 14.5px;
                flex: 1;
                padding-right: 20px;
            }
            .cbt-token-actions-buttons {
                display: flex;
                align-items: center;
                gap: 14px;
                flex-wrap: nowrap;
                flex-shrink: 0;
            }
            .cbt-token-actions-buttons .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                height: 48px;
                padding: 0 24px;
                border-radius: 12px;
                font-weight: 700;
                font-size: 14.5px;
                text-decoration: none;
                transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
            }
            .cbt-token-actions-buttons .button-primary {
                border: none;
                background: linear-gradient(180deg, #10b981 0%, #059669 100%);
                color: #ffffff;
                box-shadow: 0 8px 24px rgba(16, 185, 129, 0.25), inset 0 1px 0 rgba(255,255,255,0.2);
            }
            .cbt-token-actions-buttons .button-primary:hover,
            .cbt-token-actions-buttons .button-primary:focus {
                transform: translateY(-2px);
                background: linear-gradient(180deg, #34d399 0%, #059669 100%);
                box-shadow: 0 12px 32px rgba(16, 185, 129, 0.35), inset 0 1px 0 rgba(255,255,255,0.3);
            }
            .cbt-token-actions-buttons .button-secondary {
                border: 2px solid #e2e8f0;
                background: #ffffff;
                color: #0f172a;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
            }
            .cbt-token-actions-buttons .button-secondary:hover,
            .cbt-token-actions-buttons .button-secondary:focus {
                transform: translateY(-2px);
                border-color: #cbd5e1;
                background: #f8fafc;
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
            }
            @media (max-width: 960px) {
                .cbt-token-hero,
                .cbt-token-card-header,
                .cbt-token-actions {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-token-field-grid,
                .cbt-token-note-grid {
                    grid-template-columns: 1fr;
                }
                .cbt-token-live-panel {
                    grid-template-columns: 1fr;
                    gap: 0;
                }
                .cbt-token-live-label {
                    text-align: center;
                }
                .cbt-token-live-value {
                    margin-bottom: 16px;
                }
            }
            @media (max-width: 782px) {
                .cbt-token-page {
                    margin-right: 10px;
                }
                .cbt-token-hero,
                .cbt-token-card {
                    padding: 24px;
                }
                .cbt-token-live-value {
                    font-size: 32px;
                    letter-spacing: 0.15em;
                }
            }
        </style>
        <div class="wrap cbt-token-page">
            <div class="cbt-token-shell">
                <section class="cbt-token-hero">
                    <div class="cbt-token-hero-copy">
                        <span class="cbt-token-kicker">Security</span>
                        <h1>CBT Tokens</h1>
                        <p>Kelola token ujian global untuk semua exam. Anda bisa mengatur token manual, interval refresh otomatis, dan apakah frontend mengisi token secara otomatis untuk siswa.</p>
                    </div>
                    <aside class="cbt-token-live-panel">
                        <span class="cbt-token-live-label">Token Saat Ini</span>
                        <span class="cbt-token-live-value"><?php echo esc_html($global_token_display); ?></span>
                        <div class="cbt-token-live-meta">
                            <div class="cbt-token-live-meta-item">
                                <span>Refresh berikutnya</span>
                                <strong><?php echo esc_html($global_token_next_refresh_label); ?></strong>
                            </div>
                            <div class="cbt-token-live-meta-item">
                                <span>Sisa waktu</span>
                                <strong><?php echo esc_html($global_token_remaining_label); ?></strong>
                            </div>
                            <div class="cbt-token-live-meta-item">
                                <span>Mode frontend</span>
                                <strong><?php echo esc_html($frontend_auto_status_label); ?></strong>
                            </div>
                        </div>
                    </aside>
                </section>

            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-token-form">
                    <?php wp_nonce_field('cbt_save_global_exam_token'); ?>
                    <input type="hidden" name="action" value="cbt_save_global_exam_token" />

                    <section class="cbt-token-card">
                        <div class="cbt-token-card-header">
                            <div>
                                <h2>Token Ujian Global</h2>
                                <p>Satu token berlaku untuk semua exam. Token otomatis berubah sesuai interval refresh yang Anda tentukan.</p>
                            </div>
                            <span class="cbt-token-card-chip">Global</span>
                        </div>

                        <div class="cbt-token-field-grid">
                            <div class="cbt-token-field">
                                <label for="cbt-global-exam-token">Token Aktif</label>
                                <input
                                    type="text"
                                    id="cbt-global-exam-token"
                                    name="global_exam_token"
                                    maxlength="6"
                                    value="<?php echo esc_attr($global_token_value); ?>"
                                    placeholder="ABC123"
                                />
                                <p class="description">Gunakan tepat 6 karakter huruf/angka.</p>
                            </div>

                            <div class="cbt-token-field">
                                <label for="cbt-global-token-refresh">Interval Refresh</label>
                                <select id="cbt-global-token-refresh" name="global_exam_token_refresh_minutes">
                                    <?php for ($minute = 5; $minute <= 60; $minute += 5): ?>
                                        <option value="<?php echo (int) $minute; ?>" <?php selected($global_token_refresh_minutes, $minute); ?>>
                                            <?php echo esc_html((string) $minute); ?> menit
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <p class="description">Pilihan interval refresh tersedia dari 5 menit sampai 60 menit.</p>
                            </div>

                            <div class="cbt-token-field cbt-token-field--full">
                                <label for="cbt-global-token-frontend-auto">Frontend Auto Token</label>
                                <label class="cbt-token-toggle" for="cbt-global-token-frontend-auto">
                                    <input
                                        type="checkbox"
                                        id="cbt-global-token-frontend-auto"
                                        name="global_exam_token_frontend_auto_apply"
                                        value="1"
                                        <?php checked($global_token_frontend_auto_apply, 1); ?>
                                    />
                                    <span>
                                        <strong>Aktifkan token otomatis di frontend</strong>
                                        <span><?php echo esc_html($frontend_auto_status_description); ?></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="cbt-token-note-grid">
                            <article class="cbt-token-note-card">
                                <strong>Refresh Berikutnya</strong>
                                <p><?php echo esc_html($global_token_next_refresh_label); ?></p>
                            </article>
                            <article class="cbt-token-note-card">
                                <strong>Sisa Waktu Token</strong>
                                <p><?php echo esc_html($global_token_remaining_label); ?></p>
                            </article>
                            <article class="cbt-token-note-card">
                                <strong>Mode Frontend</strong>
                                <p><?php echo esc_html($frontend_auto_status_label); ?></p>
                            </article>
                        </div>
                    </section>

                    <div class="cbt-token-actions">
                        <p class="cbt-token-actions-copy">Simpan untuk memperbarui pengaturan saat ini, atau generate ulang untuk membuat token baru seketika.</p>
                        <div class="cbt-token-actions-buttons">
                            <button type="submit" class="button button-primary button-large" name="token_mode" value="save">Simpan Pengaturan Token</button>
                            <button type="submit" class="button button-secondary button-large cbt-admin-btn--warning" name="token_mode" value="regenerate">Generate Ulang Sekarang</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
