        <style>
            .cbt-token-page {
                max-width: 1120px;
            }
            .cbt-token-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            }
            .cbt-token-hero {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 22px;
                padding: 24px 28px;
                border: 1px solid #d7dbe2;
                border-radius: 22px;
                background:
                    radial-gradient(circle at top right, rgba(34, 197, 94, 0.10), transparent 32%),
                    linear-gradient(135deg, #ffffff 0%, #f7fafc 100%);
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            }
            .cbt-token-hero-copy {
                max-width: 620px;
            }
            .cbt-token-kicker {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 12px;
                border-radius: 999px;
                background: #e8f7ee;
                color: #166534;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
            }
            .cbt-token-hero h1 {
                margin: 12px 0 8px;
                font-size: 30px;
                line-height: 1.15;
            }
            .cbt-token-hero p {
                margin: 0;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-token-live-panel {
                display: grid;
                gap: 12px;
                min-width: 280px;
                padding: 18px;
                border: 1px solid #dbe5df;
                border-radius: 18px;
                background: rgba(255, 255, 255, 0.92);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
            }
            .cbt-token-live-label {
                color: #64748b;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .cbt-token-live-value {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 64px;
                padding: 0 18px;
                border-radius: 16px;
                background: #0f172a;
                color: #f8fafc;
                font-family: "Courier New", Courier, monospace;
                font-size: 28px;
                font-weight: 700;
                letter-spacing: 0.18em;
                text-transform: uppercase;
            }
            .cbt-token-live-meta {
                display: grid;
                gap: 8px;
            }
            .cbt-token-live-meta-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                color: #334155;
                font-size: 13px;
            }
            .cbt-token-live-meta-item strong {
                font-weight: 600;
            }
            .cbt-token-form {
                display: grid;
                gap: 18px;
            }
            .cbt-token-card {
                padding: 24px;
                border: 1px solid #dcdcde;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
            }
            .cbt-token-card-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }
            .cbt-token-card-header h2 {
                margin: 0 0 6px;
                font-size: 18px;
                line-height: 1.2;
            }
            .cbt-token-card-header p {
                margin: 0;
                color: #646970;
                line-height: 1.55;
            }
            .cbt-token-card-chip {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 12px;
                border-radius: 999px;
                background: #eef6f0;
                color: #166534;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
            }
            .cbt-token-field-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px 18px;
            }
            .cbt-token-field {
                display: grid;
                gap: 8px;
            }
            .cbt-token-field--full {
                grid-column: 1 / -1;
            }
            .cbt-token-field label {
                font-weight: 600;
                color: #111827;
            }
            .cbt-token-field input[type="text"],
            .cbt-token-field select {
                width: 100%;
                min-height: 46px;
                margin: 0;
                border: 1px solid #c7d2e0;
                border-radius: 12px;
                background: #fbfdff;
                color: #111827;
                padding: 0 13px;
                transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
            }
            .cbt-token-field input[type="text"] {
                font-family: "Courier New", Courier, monospace;
                font-size: 18px;
                font-weight: 700;
                letter-spacing: 0.18em;
                text-transform: uppercase;
            }
            .cbt-token-field input[type="text"]:focus,
            .cbt-token-field select:focus {
                border-color: #22c55e;
                background: #ffffff;
                box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
                outline: none;
            }
            .cbt-token-field .description {
                margin: 0;
                color: #6b7280;
                line-height: 1.5;
            }
            .cbt-token-note-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
                margin-top: 4px;
            }
            .cbt-token-note-card {
                padding: 14px 16px;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                background: linear-gradient(180deg, #fcfefe 0%, #f8fbf8 100%);
            }
            .cbt-token-note-card strong {
                display: block;
                margin-bottom: 6px;
                color: #0f172a;
                font-size: 13px;
            }
            .cbt-token-note-card p {
                margin: 0;
                color: #64748b;
                line-height: 1.55;
            }
            .cbt-token-toggle {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 14px 16px;
                border: 1px solid #d9e7dc;
                border-radius: 16px;
                background: #f8fcf9;
            }
            .cbt-token-toggle input[type="checkbox"] {
                margin: 3px 0 0;
            }
            .cbt-token-toggle strong {
                display: block;
                margin-bottom: 4px;
                color: #111827;
                font-size: 14px;
            }
            .cbt-token-toggle span {
                display: block;
                color: #64748b;
                line-height: 1.55;
            }
            .cbt-token-actions {
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
            .cbt-token-actions-copy {
                margin: 0;
                color: #646970;
                line-height: 1.5;
            }
            .cbt-token-actions-buttons {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-token-actions-buttons .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 48px;
                padding: 0 18px;
                border-radius: 14px;
                font-weight: 600;
                text-decoration: none;
                transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease, background-color 140ms ease, color 140ms ease;
            }
            .cbt-token-actions-buttons .button-primary {
                border-color: #15803d;
                background: linear-gradient(180deg, #22c55e 0%, #16a34a 100%);
                box-shadow: 0 10px 22px rgba(34, 197, 94, 0.2);
            }
            .cbt-token-actions-buttons .button-primary:hover,
            .cbt-token-actions-buttons .button-primary:focus {
                transform: translateY(-1px);
                border-color: #166534;
                background: linear-gradient(180deg, #26cf65 0%, #159347 100%);
                box-shadow: 0 14px 28px rgba(34, 197, 94, 0.24);
            }
            .cbt-token-actions-buttons .button-secondary {
                border-color: #cfe3d4;
                background: linear-gradient(180deg, #ffffff 0%, #f5fbf6 100%);
                color: #166534;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
            }
            .cbt-token-actions-buttons .button-secondary:hover,
            .cbt-token-actions-buttons .button-secondary:focus {
                transform: translateY(-1px);
                border-color: #afd4b8;
                background: linear-gradient(180deg, #ffffff 0%, #edf8ef 100%);
                color: #14532d;
                box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
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
                    min-width: 0;
                }
            }
            @media (max-width: 782px) {
                .cbt-token-page {
                    margin-right: 10px;
                }
                .cbt-token-hero,
                .cbt-token-card {
                    padding: 20px;
                }
                .cbt-token-live-value {
                    font-size: 24px;
                    letter-spacing: 0.12em;
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
                            <button type="submit" class="button button-secondary button-large" name="token_mode" value="regenerate">Generate Ulang Sekarang</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>