        <style>
    /* Modern Design System Tokens */
    :root {
        --cbt-primary: #3b82f6;
        --cbt-primary-hover: #2563eb;
        --cbt-primary-light: #eff6ff;
        --cbt-secondary: #0ea5e9;
        --cbt-accent: #8b5cf6;
        --cbt-success: #10b981;
        --cbt-success-hover: #059669;
        
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
        --cbt-shadow-glow-success: 0 0 20px rgba(16, 185, 129, 0.15);
        
        --cbt-radius-sm: 12px;
        --cbt-radius-md: 20px;
        --cbt-radius-lg: 32px;
        
        --cbt-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .cbt-token-page {
        max-width: 1280px;
        margin: 20px auto;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: var(--cbt-text-main);
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        animation: cbtSlideUp 0.4s ease-out forwards;
        opacity: 0;
    }
    .cbt-token-page * { box-sizing: border-box; }

    @keyframes cbtSlideUp {
        0% { opacity: 0; transform: translateY(10px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    .cbt-token-shell {
        display: grid;
        gap: 20px;
        position: relative;
    }

    /* Background effects */
    .cbt-token-shell::before {
        content: ''; position: absolute; top: -100px; left: -100px; width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, rgba(255,255,255,0) 70%);
        z-index: -1; border-radius: 50%;
    }

    .cbt-token-hero {
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
        box-shadow: var(--cbt-shadow-md), var(--cbt-shadow-glow-success);
    }
    .cbt-token-hero::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px;
        background: linear-gradient(90deg, var(--cbt-success), var(--cbt-primary));
    }
    .cbt-token-hero-copy {
        flex: 1;
        position: relative;
        z-index: 2;
    }
    .cbt-token-kicker {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        border-radius: 999px;
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: var(--cbt-success-hover);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-bottom: 12px;
        box-shadow: var(--cbt-shadow-sm);
    }
    .cbt-token-hero h1 {
        margin: 0 0 10px;
        font-size: 28px;
        font-weight: 800;
        line-height: 1.1;
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.02em;
    }
    .cbt-token-hero p {
        margin: 0;
        color: var(--cbt-text-muted);
        font-size: 15px;
        line-height: 1.6;
    }

    .cbt-token-live-panel {
        position: relative;
        z-index: 2;
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0 20px;
        padding: 20px;
        min-width: 420px;
        border-radius: var(--cbt-radius-md);
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid var(--cbt-border);
        box-shadow: var(--cbt-shadow-sm);
        flex-shrink: 0;
    }
    .cbt-token-live-label {
        grid-column: 1 / -1;
        color: var(--cbt-text-muted);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 10px;
    }
    .cbt-token-live-value {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 60px;
        padding: 0 24px;
        border-radius: var(--cbt-radius-sm);
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: var(--cbt-success);
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: 0.2em;
        text-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
        box-shadow: inset 0 2px 10px rgba(0,0,0,0.5), var(--cbt-shadow-md);
        margin-bottom: 0;
    }
    .cbt-token-live-meta {
        display: grid;
        gap: 8px;
        align-content: center;
    }
    .cbt-token-live-meta-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 8px 12px;
        background: var(--cbt-bg-card);
        border-radius: 8px;
        color: var(--cbt-text-muted);
        font-size: 13px;
        box-shadow: var(--cbt-shadow-sm);
        border: 1px solid var(--cbt-border);
    }
    .cbt-token-live-meta-item strong {
        font-weight: 700;
        color: var(--cbt-text-main);
    }
    
    .cbt-token-form {
        display: grid;
        gap: 16px;
    }
    .cbt-token-card {
        padding: 24px;
        border-radius: var(--cbt-radius-lg);
        background: var(--cbt-bg-card);
        backdrop-filter: blur(10px);
        border: 1px solid var(--cbt-border);
        box-shadow: var(--cbt-shadow-md);
    }
    .cbt-token-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--cbt-border);
    }
    .cbt-token-card-header h2 {
        margin: 0 0 8px;
        font-size: 20px;
        font-weight: 800;
        color: var(--cbt-text-main);
    }
    .cbt-token-card-header p {
        margin: 0;
        color: var(--cbt-text-muted);
        line-height: 1.6;
        font-size: 14px;
    }
    .cbt-token-card-chip {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--cbt-primary-light), #e0e7ff);
        color: var(--cbt-primary-hover);
        font-size: 12px;
        font-weight: 700;
        box-shadow: var(--cbt-shadow-sm);
    }
    
    .cbt-token-field-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 20px;
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
        color: var(--cbt-text-main);
        font-size: 14px;
    }
    .cbt-token-field input[type="text"],
    .cbt-token-field select {
        width: 100%;
        height: 44px;
        margin: 0;
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-sm);
        background: rgba(255, 255, 255, 0.9);
        color: var(--cbt-text-main);
        padding: 0 14px;
        font-size: 14px;
        transition: var(--cbt-transition);
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
    }
    .cbt-token-field input[type="text"] {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
        font-size: 18px;
        font-weight: 800;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--cbt-primary-hover);
    }
    .cbt-token-field input[type="text"]:focus,
    .cbt-token-field select:focus {
        border-color: var(--cbt-primary);
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        outline: none;
    }
    .cbt-token-field .description {
        margin: 0;
        color: var(--cbt-text-muted);
        line-height: 1.5;
        font-size: 13px;
    }
    
    .cbt-token-note-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px dashed var(--cbt-border);
    }
    .cbt-token-note-card {
        padding: 16px;
        border-radius: var(--cbt-radius-sm);
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid var(--cbt-border);
        transition: var(--cbt-transition);
    }
    .cbt-token-note-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--cbt-shadow-sm);
        background: rgba(255, 255, 255, 0.9);
        border-color: rgba(59, 130, 246, 0.3);
    }
    .cbt-token-note-card strong {
        display: block;
        margin-bottom: 6px;
        color: var(--cbt-text-main);
        font-size: 13px;
        font-weight: 700;
    }
    .cbt-token-note-card p {
        margin: 0;
        color: var(--cbt-text-muted);
        line-height: 1.5;
        font-size: 13px;
    }
    
    .cbt-token-toggle {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-sm);
        background: rgba(255, 255, 255, 0.8);
        cursor: pointer;
        transition: var(--cbt-transition);
    }
    .cbt-token-toggle:hover {
        border-color: var(--cbt-primary);
        background: #ffffff;
        box-shadow: var(--cbt-shadow-sm);
    }
    .cbt-token-toggle input[type="checkbox"] {
        margin: 2px 0 0;
        width: 20px;
        height: 20px;
        border-radius: 6px;
        border: 2px solid var(--cbt-border);
        cursor: pointer;
        transition: var(--cbt-transition);
    }
    .cbt-token-toggle input[type="checkbox"]:checked {
        background-color: var(--cbt-success);
        border-color: var(--cbt-success);
    }
    .cbt-token-toggle strong {
        display: block;
        margin-bottom: 4px;
        color: var(--cbt-text-main);
        font-size: 14px;
        font-weight: 700;
    }
    .cbt-token-toggle span {
        display: block;
        color: var(--cbt-text-muted);
        line-height: 1.5;
        font-size: 13px;
    }
    
    .cbt-token-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px;
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-md);
        background: rgba(255, 255, 255, 0.9);
        box-shadow: var(--cbt-shadow-sm);
    }
    .cbt-token-actions-copy {
        margin: 0;
        color: var(--cbt-text-muted);
        line-height: 1.5;
        font-size: 14px;
        flex: 1;
    }
    .cbt-token-actions-buttons {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: nowrap;
        flex-shrink: 0;
    }
    .cbt-token-actions-buttons .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        padding: 0 20px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        transition: var(--cbt-transition);
        border: none;
        cursor: pointer;
    }
    .cbt-token-actions-buttons .button-primary {
        background: linear-gradient(135deg, var(--cbt-primary), var(--cbt-secondary));
        color: #ffffff;
        box-shadow: var(--cbt-shadow-sm);
    }
    .cbt-token-actions-buttons .button-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--cbt-shadow-md), var(--cbt-shadow-glow);
    }
    .cbt-token-actions-buttons .button-secondary {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }
    .cbt-token-actions-buttons .button-secondary:hover {
        transform: translateY(-2px);
        background: rgba(245, 158, 11, 0.15);
        border-color: rgba(245, 158, 11, 0.3);
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
            min-width: 100%;
        }
    }
    @media (max-width: 768px) {
        .cbt-token-hero,
        .cbt-token-card,
        .cbt-token-actions {
            padding: 20px;
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
