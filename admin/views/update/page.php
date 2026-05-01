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

    .cbt-update-page {
            max-width: 1280px;
            margin: 20px auto;
            padding: 24px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--cbt-text-main);
            background: radial-gradient(circle at top left, #e0e7ff 0%, #f8fafc 40%, #f0fdf4 100%);
            border-radius: var(--cbt-radius-lg);
            box-sizing: border-box;
        }
        .cbt-update-page * {
            box-sizing: border-box;
        }

    .cbt-update-page * { box-sizing: border-box; }

    .cbt-update-shell {
        display: grid;
        gap: 20px;
        
    
            position: relative;
            z-index: 1;
            isolation: isolate;
        }
    
    .cbt-update-shell::before {
        content: ''; position: absolute; top: -100px; left: -100px; width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, rgba(255,255,255,0) 70%);
        z-index: -1; border-radius: 50%;
    }

    .cbt-update-hero,
        .cbt-update-col {
        display: grid;
        gap: 20px;
        align-content: start;
    }
    .cbt-update-card {
        
        
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        
        
        position: relative;
        overflow: hidden;
    
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

    .cbt-update-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(280px, 320px);
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
    .cbt-update-hero::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px;
        background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
    }
    .cbt-update-hero-copy {
        position: relative;
        z-index: 1;
    }
    .cbt-update-kicker {
        display: inline-flex;
        align-items: center;
        padding: 6px 14px;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--cbt-primary-light), #e0e7ff);
        color: var(--cbt-primary-hover);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-bottom: 16px;
        box-shadow: var(--cbt-shadow-sm);
    }
    .cbt-update-hero h1 {
        margin: 0 0 12px;
        font-size: 32px;
        font-weight: 800;
        line-height: 1.1;
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        letter-spacing: -0.02em;
    }
    .cbt-update-hero p, .cbt-update-card p {
        margin: 0;
        color: var(--cbt-text-muted);
        font-size: 15px;
        line-height: 1.6;
    }
    
    .cbt-update-overview {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 8px;
        padding: 20px;
        border-radius: var(--cbt-radius-md);
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid var(--cbt-border);
        box-shadow: var(--cbt-shadow-sm);
    }
    .cbt-update-pill {
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border-radius: 8px;
        background: var(--cbt-bg-base);
        border: 1px solid var(--cbt-border);
        color: var(--cbt-text-main);
        font-size: 13px;
        font-weight: 600;
        gap: 10px;
    }
    .cbt-update-status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 16px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .cbt-update-status-pill--muted { background: #f1f5f9; color: #475569; }
    .cbt-update-status-pill--info { background: var(--cbt-primary-light); color: var(--cbt-primary-hover); }
    .cbt-update-status-pill--ok { background: #dcfce7; color: #166534; }
    .cbt-update-status-pill--warning { background: #fef3c7; color: #92400e; }
    .cbt-update-status-pill--danger { background: #fee2e2; color: #991b1b; }

    .cbt-update-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.9fr);
        gap: 20px;
        align-items: start;
    }
    .cbt-update-card {
        padding: 24px;
        
    
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
    .cbt-update-card h2 {
        margin: 0 0 8px;
        color: var(--cbt-text-main);
        font-size: 20px;
        font-weight: 800;
    }
    .cbt-update-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--cbt-border);
    }
    .cbt-update-card-header-copy {
        display: grid;
        gap: 4px;
    }
    
    .cbt-update-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    .cbt-update-meta-item {
        padding: 16px;
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-sm);
        background: rgba(255, 255, 255, 0.8);
        box-shadow: var(--cbt-shadow-sm);
        transition: var(--cbt-transition);
    }
    .cbt-update-meta-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--cbt-shadow-md);
        border-color: rgba(59, 130, 246, 0.3);
    }
    .cbt-update-meta-label {
        margin-bottom: 6px;
        color: var(--cbt-text-muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .cbt-update-meta-value {
        color: var(--cbt-text-main);
        font-size: 15px;
        font-weight: 700;
        overflow-wrap: anywhere;
    }
    
    .cbt-update-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 24px;
    }
    .cbt-update-actions .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 40px;
        padding: 0 20px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 13px;
        border: none;
        transition: var(--cbt-transition);
        text-decoration: none;
        cursor: pointer;
    }
    .cbt-update-actions .button:not(:disabled):hover,
    .cbt-update-actions .button:not(:disabled):focus {
        transform: translateY(-2px);
        box-shadow: var(--cbt-shadow-md);
    }
    .cbt-update-actions .button[data-cbt-update-locked-disabled="1"], .cbt-update-actions .button:disabled {
        background: #f1f5f9 !important;
        color: #94a3b8 !important;
        cursor: not-allowed;
        box-shadow: none !important;
    }
    .cbt-update-page .button-primary {
        background: linear-gradient(135deg, var(--cbt-primary), var(--cbt-secondary));
        color: #ffffff;
        box-shadow: var(--cbt-shadow-sm);
    }
    .cbt-update-page .button-primary:hover {
        box-shadow: var(--cbt-shadow-md), var(--cbt-shadow-glow);
    }
    .cbt-update-actions .button.cbt-admin-btn--warning:not([data-cbt-update-locked-disabled="1"]) {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }
    .cbt-update-actions .button.cbt-admin-btn--warning:not([data-cbt-update-locked-disabled="1"]):hover {
        background: rgba(245, 158, 11, 0.15);
        border-color: rgba(245, 158, 11, 0.3);
    }
    .cbt-update-actions .button.cbt-admin-btn--secondary {
        background: rgba(255, 255, 255, 0.8);
        color: var(--cbt-text-main);
        border: 1px solid var(--cbt-border);
    }
    .cbt-update-actions .button.cbt-admin-btn--secondary:hover {
        border-color: var(--cbt-primary);
        color: var(--cbt-primary);
    }

    .cbt-update-checklist {
        display: grid;
        gap: 12px;
    }
    .cbt-update-checklist-item {
        padding: 16px;
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-sm);
        background: rgba(255, 255, 255, 0.8);
        box-shadow: var(--cbt-shadow-sm);
    }
    .cbt-update-checklist-item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 8px;
    }
    .cbt-update-checklist-item-title {
        color: var(--cbt-text-main);
        font-weight: 700;
        font-size: 14px;
    }
    .cbt-update-checklist-item-message {
        color: var(--cbt-text-muted);
        line-height: 1.5;
        font-size: 13px;
    }
    .cbt-update-preflight-label {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .cbt-update-preflight-label--ok { background: #dcfce7; color: #166534; }
    .cbt-update-preflight-label--warning { background: #fef3c7; color: #92400e; }
    .cbt-update-preflight-label--danger { background: #fee2e2; color: #991b1b; }
    
    .cbt-update-changelog {
        margin-top: 16px;
        padding: 20px;
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-sm);
        background: rgba(248, 250, 252, 0.8);
        white-space: pre-wrap;
        color: var(--cbt-text-main);
        line-height: 1.6;
        font-size: 14px;
    }
    .cbt-update-empty {
        padding: 20px;
        border: 1px dashed rgba(148, 163, 184, 0.6);
        border-radius: var(--cbt-radius-sm);
        background: rgba(255, 255, 255, 0.5);
        color: var(--cbt-text-muted);
        font-size: 14px;
        text-align: center;
    }
    
    .cbt-update-progress {
        display: grid;
        gap: 12px;
        margin-top: 20px;
        padding: 20px;
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-md);
        background: rgba(255, 255, 255, 0.9);
        box-shadow: var(--cbt-shadow-md);
    }
    .cbt-update-progress[hidden] { display: none; }
    .cbt-update-progress-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .cbt-update-progress-title {
        color: var(--cbt-text-main);
        font-weight: 700;
        font-size: 14px;
    }
    .cbt-update-progress-label {
        color: var(--cbt-primary);
        font-size: 12px;
        font-weight: 800;
    }
    .cbt-update-progress-track {
        overflow: hidden;
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
    }
    .cbt-update-progress-fill {
        width: 0%;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary));
        transition: width 0.3s ease;
    }
    .cbt-update-progress-message {
        color: var(--cbt-text-muted);
        font-size: 13px;
        line-height: 1.5;
    }
    
    .cbt-update-table-wrap { overflow-x: auto; }
    .cbt-update-table {
        width: 100%;
        min-width: 720px;
        border-collapse: separate;
        border-spacing: 0;
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid var(--cbt-border);
        border-radius: var(--cbt-radius-sm);
        overflow: hidden;
    }
    .cbt-update-table th, .cbt-update-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--cbt-border);
        text-align: left;
        font-size: 13px;
    }
    .cbt-update-table th {
        color: var(--cbt-text-muted);
        background: rgba(248, 250, 252, 0.9);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .cbt-update-table td { color: var(--cbt-text-main); }
    
    @media (max-width: 960px) {
        .cbt-update-hero, .cbt-update-grid {
            grid-template-columns: 1fr;
        }
        .cbt-update-hero, .cbt-update-card {
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
    }
</style>

<div class="wrap cbt-update-page">
    <div class="cbt-update-shell">
        <?php if ($notice !== ''): ?>
            <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <section class="cbt-update-hero">
            <div class="cbt-update-hero-copy">
                <span class="cbt-update-kicker">CBT Update</span>
                <h1>Manual updater GitHub Releases untuk CBT Exam System</h1>
                <p><?php echo esc_html((string) ($status_meta['description'] ?? 'Kelola pengecekan release dan install update plugin secara manual dari GitHub Releases.')); ?></p>
                <?php if ($release_message !== '' && $status !== 'check_failed'): ?>
                    <div class="cbt-update-empty" style="margin-top:16px;"><?php echo esc_html($release_message); ?></div>
                <?php endif; ?>
                <?php
                $install_disabled_reason = '';
                if (!$can_install) {
                    if (!$has_update && $remote_version !== '') {
                        $install_disabled_reason = 'Tidak ada versi update yang lebih baru untuk diinstall.';
                    } elseif (!$has_update) {
                        $install_disabled_reason = 'Jalankan cek update terlebih dahulu sebelum install.';
                    } elseif ((string) ($preflight['status'] ?? 'blocked') === 'blocked') {
                        $install_disabled_reason = 'Preflight update masih blocked. Perbaiki checklist sebelum install.';
                    } else {
                        $install_disabled_reason = 'Install update belum tersedia untuk state release saat ini.';
                    }
                }
                ?>
                <div class="cbt-update-actions">
                    <form method="post" action="<?php echo esc_url($check_action_url); ?>" style="margin:0;" data-cbt-update-form="check">
                        <input type="hidden" name="action" value="cbt_check_update_now" />
                        <?php wp_nonce_field('cbt_check_update_now'); ?>
                        <button type="submit" class="button button-primary">Cek Update Sekarang</button>
                    </form>
                    <form method="post" action="<?php echo esc_url($install_action_url); ?>" style="margin:0;" data-cbt-update-form="install">
                        <input type="hidden" name="action" value="cbt_install_update_now" />
                        <?php wp_nonce_field('cbt_install_update_now'); ?>
                        <button
                            type="submit"
                            class="button cbt-admin-btn--warning"
                            <?php disabled(!$can_install); ?>
                            <?php if (!$can_install): ?>
                                aria-disabled="true"
                                data-cbt-update-locked-disabled="1"
                                data-cbt-update-disabled-message="<?php echo esc_attr($install_disabled_reason); ?>"
                                title="<?php echo esc_attr($install_disabled_reason); ?>"
                            <?php endif; ?>
                        >Install Update</button>
                    </form>
                    <a class="button cbt-admin-btn--secondary" href="<?php echo esc_url($release_url); ?>" target="_blank" rel="noopener noreferrer">Lihat Release GitHub</a>
                </div>
                <?php $initial_update_job = is_array($selected_update_job ?? null) ? $selected_update_job : null; ?>
                <div
                    class="cbt-update-progress"
                    data-cbt-update-panel="1"
                    data-cbt-update-nonce="<?php echo esc_attr((string) ($update_operation_nonce ?? '')); ?>"
                    data-cbt-update-action="<?php echo esc_attr((string) ($update_operation_ajax_action ?? 'cbt_update_operation')); ?>"
                    data-cbt-update-initial="<?php echo esc_attr(wp_json_encode($initial_update_job)); ?>"
                    <?php echo is_array($initial_update_job) ? '' : 'hidden'; ?>
                >
                    <div class="cbt-update-progress-header">
                        <div class="cbt-update-progress-title" data-cbt-update-title>Update job</div>
                        <div class="cbt-update-progress-label" data-cbt-update-percent>0%</div>
                    </div>
                    <div class="cbt-update-progress-track" aria-hidden="true">
                        <div class="cbt-update-progress-fill" data-cbt-update-fill></div>
                    </div>
                    <div class="cbt-update-progress-message" data-cbt-update-message>Menunggu operasi update.</div>
                    <div>
                        <button type="button" class="button" data-cbt-update-resume> Lanjutkan </button>
                    </div>
                </div>
            </div>
            <div class="cbt-update-overview">
                <span class="cbt-update-status-pill cbt-update-status-pill--<?php echo esc_attr((string) ($status_meta['tone'] ?? 'muted')); ?>">
                    <?php echo esc_html((string) ($status_meta['label'] ?? 'Not Checked')); ?>
                </span>
                <span class="cbt-update-pill">Versi lokal: <?php echo esc_html($current_version !== '' ? $current_version : '-'); ?></span>
                <span class="cbt-update-pill">Versi remote: <?php echo esc_html($remote_version !== '' ? $remote_version : '-'); ?></span>
                <span class="cbt-update-pill">Last checked: <?php echo esc_html($checked_at_label); ?></span>
                <span class="cbt-update-pill">Source: <?php echo esc_html($repo_label); ?></span>
            </div>
        </section>

        <div class="cbt-update-grid">
            <div class="cbt-update-col">
<section class="cbt-update-card">
                <div class="cbt-update-card-header">
                    <div class="cbt-update-card-header-copy">
                        <h2>Release Summary</h2>
                        <p>Ringkasan release terbaru yang disimpan dari GitHub Releases.</p>
                    </div>
                    <?php if ($has_checked_state): ?>
                        <span class="cbt-update-status-pill cbt-update-status-pill--<?php echo esc_attr((string) ($preflight_meta['tone'] ?? 'danger')); ?>">
                            Preflight <?php echo esc_html((string) ($preflight_meta['label'] ?? 'Blocked')); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!$has_checked_state): ?>
                    <div class="cbt-update-empty">Belum ada cache release yang tersimpan. Jalankan <strong>CEK UPDATE SEKARANG</strong> untuk mengambil manifest terbaru.</div>
                <?php else: ?>
                    <div class="cbt-update-meta-grid">
                        <div class="cbt-update-meta-item">
                            <div class="cbt-update-meta-label">Tag Release</div>
                            <div class="cbt-update-meta-value"><?php echo esc_html((string) ($release['tag'] ?? ($manifest['tag'] ?? '-'))); ?></div>
                        </div>
                        <div class="cbt-update-meta-item">
                            <div class="cbt-update-meta-label">Published</div>
                            <div class="cbt-update-meta-value"><?php echo esc_html($published_at_label); ?></div>
                        </div>
                        <div class="cbt-update-meta-item">
                            <div class="cbt-update-meta-label">Requires PHP</div>
                            <div class="cbt-update-meta-value"><?php echo esc_html((string) ($manifest['requires_php'] ?? '-')); ?></div>
                        </div>
                        <div class="cbt-update-meta-item">
                            <div class="cbt-update-meta-label">Requires WordPress</div>
                            <div class="cbt-update-meta-value"><?php echo esc_html((string) ($manifest['requires_wp'] ?? '-')); ?></div>
                        </div>
                        <div class="cbt-update-meta-item">
                            <div class="cbt-update-meta-label">Tested Up To</div>
                            <div class="cbt-update-meta-value"><?php echo esc_html((string) ($manifest['tested_up_to'] ?? '-')); ?></div>
                        </div>
                        <div class="cbt-update-meta-item">
                            <div class="cbt-update-meta-label">Package Asset</div>
                            <div class="cbt-update-meta-value"><?php echo esc_html((string) ($manifest['download_url'] ?? '-') !== '' ? CBT_Update_Release_Helper::package_asset_name() : '-'); ?></div>
                        </div>
                    </div>

                    <?php if ($status === 'no_release'): ?>
                        <div class="cbt-update-empty" style="margin-top:16px;">
                            Updater v1 menunggu GitHub Release resmi yang memuat asset <strong><?php echo esc_html(CBT_Update_Release_Helper::package_asset_name()); ?></strong>
                            dan <strong><?php echo esc_html(CBT_Update_Release_Helper::manifest_asset_name()); ?></strong>.
                        </div>
                    <?php elseif ($changelog !== ''): ?>
                        <div class="cbt-update-changelog"><?php echo esc_html($changelog); ?></div>
                    <?php else: ?>
                        <div class="cbt-update-empty" style="margin-top:16px;">Manifest release belum memuat changelog ringkas.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
<section class="cbt-update-card">
                <div class="cbt-update-card-header">
                    <div class="cbt-update-card-header-copy">
                        <h2>Update History</h2>
                        <p>Riwayat install, rollback, checksum, dan status health check.</p>
                    </div>
                </div>
                <?php if (empty($update_history)): ?>
                    <div class="cbt-update-empty">Belum ada riwayat update.</div>
                <?php else: ?>
                    <div class="cbt-update-table-wrap">
                        <table class="cbt-update-table">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Versi</th>
                                    <th>Status</th>
                                    <th>Backup</th>
                                    <th>Pesan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ((array) $update_history as $entry): ?>
                                    <tr>
                                        <td><?php echo esc_html((string) ($entry['finished_at'] ?? '-')); ?></td>
                                        <td><?php echo esc_html((string) ($entry['source_version'] ?? '-') . ' -> ' . (string) ($entry['target_version'] ?? '-')); ?></td>
                                        <td><?php echo esc_html((string) ($entry['status'] ?? '-')); ?></td>
                                        <td><?php echo esc_html((string) ($entry['backup_file'] ?? '-')); ?></td>
                                        <td><?php echo esc_html((string) ($entry['message'] ?? '-')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            </div>
            <div class="cbt-update-col">
<section class="cbt-update-card">
                <div class="cbt-update-card-header">
                    <div class="cbt-update-card-header-copy">
                        <h2>Preflight Checklist</h2>
                        <p>Checklist ini bersifat read-only guard. Cek update hanya membaca manifest release; unduh ZIP penuh dan validasi checksum dijalankan saat INSTALL UPDATE.</p>
                    </div>
                    <a class="cbt-update-pill" href="<?php echo esc_url($repo_url); ?>" target="_blank" rel="noopener noreferrer">Buka Repo</a>
                </div>

                <?php if ($status === 'no_release'): ?>
                    <div class="cbt-update-empty">Preflight baru dijalankan setelah release resmi tersedia. Untuk saat ini updater belum punya package release yang bisa divalidasi.</div>
                <?php elseif (!$has_checked_state || empty($preflight['items'])): ?>
                    <div class="cbt-update-empty">Belum ada hasil preflight. Jalankan cek update untuk memvalidasi manifest release terlebih dahulu.</div>
                <?php else: ?>
                    <div class="cbt-update-checklist">
                        <?php foreach ((array) $preflight['items'] as $preflight_item): ?>
                            <?php
                            $item_status = isset($preflight_item['status']) ? (string) $preflight_item['status'] : 'blocked';
                            $item_tone = $item_status === 'ok' ? 'ok' : ($item_status === 'warning' ? 'warning' : 'danger');
                            ?>
                            <div class="cbt-update-checklist-item">
                                <div class="cbt-update-checklist-item-header">
                                    <div class="cbt-update-checklist-item-title"><?php echo esc_html((string) ($preflight_item['label'] ?? 'Checklist')); ?></div>
                                    <span class="cbt-update-preflight-label cbt-update-preflight-label--<?php echo esc_attr($item_tone); ?>">
                                        <?php echo esc_html(strtoupper($item_status)); ?>
                                    </span>
                                </div>
                                <div class="cbt-update-checklist-item-message"><?php echo esc_html((string) ($preflight_item['message'] ?? '')); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
<section class="cbt-update-card">
                <div class="cbt-update-card-header">
                    <div class="cbt-update-card-header-copy">
                        <h2>Backups & Rollback</h2>
                        <p>Backup lokal dibuat sebelum install update dan bisa dipakai untuk rollback manual.</p>
                    </div>
                </div>
                <?php if (empty($update_backups)): ?>
                    <div class="cbt-update-empty">Belum ada backup update.</div>
                <?php else: ?>
                    <div class="cbt-update-checklist">
                        <?php foreach ((array) $update_backups as $backup): ?>
                            <div class="cbt-update-checklist-item">
                                <div class="cbt-update-checklist-item-header">
                                    <div class="cbt-update-checklist-item-title"><?php echo esc_html((string) ($backup['file_name'] ?? 'Backup')); ?></div>
                                    <button
                                        type="button"
                                        class="button"
                                        data-cbt-update-rollback="<?php echo esc_attr((string) ($backup['id'] ?? '')); ?>"
                                    >Rollback</button>
                                </div>
                                <div class="cbt-update-checklist-item-message">
                                    Versi <?php echo esc_html((string) ($backup['version'] ?? '-')); ?> ·
                                    <?php echo esc_html((string) ($backup['created_at'] ?? '-')); ?> ·
                                    <?php echo esc_html(number_format_i18n(max(0, (int) ($backup['size'] ?? 0)))); ?> bytes
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var panel = document.querySelector('[data-cbt-update-panel="1"]');
    if (!panel || typeof window.fetch !== 'function' || typeof window.FormData === 'undefined') {
        return;
    }

    var nonce = String(panel.getAttribute('data-cbt-update-nonce') || '');
    var ajaxAction = String(panel.getAttribute('data-cbt-update-action') || 'cbt_update_operation');
    var title = panel.querySelector('[data-cbt-update-title]');
    var percent = panel.querySelector('[data-cbt-update-percent]');
    var fill = panel.querySelector('[data-cbt-update-fill]');
    var message = panel.querySelector('[data-cbt-update-message]');
    var resume = panel.querySelector('[data-cbt-update-resume]');
    var currentToken = '';
    var timer = null;
    var failureCount = 0;

    function readInitialPayload() {
        try {
            return JSON.parse(String(panel.getAttribute('data-cbt-update-initial') || 'null'));
        } catch (error) {
            return null;
        }
    }

    function setButtonsDisabled(disabled) {
        Array.from(document.querySelectorAll('[data-cbt-update-form] button, [data-cbt-update-rollback]')).forEach(function (button) {
            if (button.getAttribute('data-cbt-update-locked-disabled') === '1') {
                button.disabled = true;
                return;
            }
            button.disabled = !!disabled;
        });
    }

    function render(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }
        currentToken = String(payload.token || currentToken || '');
        panel.hidden = false;
        var value = Math.max(0, Math.min(100, parseInt(String(payload.progress_percent || '0'), 10) || 0));
        if (title) {
            title.textContent = String(payload.status_label || 'Update job') + ' · ' + String(payload.stage || '-');
        }
        if (percent) {
            percent.textContent = String(value) + '%';
        }
        if (fill) {
            fill.style.width = String(value) + '%';
        }
        if (message) {
            message.textContent = String(payload.message || 'Menunggu operasi update.');
        }
        setButtonsDisabled(!payload.complete && String(payload.status || '') !== 'paused');
    }

    function request(operation, extra) {
        var body = new FormData();
        body.set('action', ajaxAction);
        body.set('nonce', nonce);
        body.set('operation', operation);
        body.set('token', currentToken);
        Object.keys(extra || {}).forEach(function (key) {
            body.set(key, String(extra[key]));
        });
        return window.fetch(window.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (!json || typeof json !== 'object') {
                throw new Error('Response update tidak valid.');
            }
            if (json.success === false) {
                throw new Error(json.data && json.data.message ? String(json.data.message) : 'Operasi update gagal.');
            }
            return json.data || json;
        });
    }

    function scheduleTick(delay) {
        if (timer) {
            window.clearTimeout(timer);
        }
        timer = window.setTimeout(function () {
            request('tick').then(handlePayload).catch(handleFailure);
        }, delay || 800);
    }

    function handlePayload(payload) {
        failureCount = 0;
        render(payload);
        var status = String(payload.status || '');
        if (status === 'reload_required') {
            if (message) {
                message.textContent = 'Reload halaman untuk melanjutkan health check.';
            }
            window.setTimeout(function () {
                window.location.href = String(payload.redirect_url || window.location.href);
            }, 700);
            return;
        }
        if (payload.complete) {
            window.setTimeout(function () {
                window.location.href = String(payload.redirect_url || window.location.href);
            }, 1200);
            return;
        }
        scheduleTick(800);
    }

    function handleFailure(error) {
        failureCount++;
        panel.hidden = false;
        if (message) {
            message.textContent = failureCount >= 3
                ? 'Koneksi polling terhenti. Job tersimpan; tekan Lanjutkan untuk mencoba lagi.'
                : (error && error.message ? error.message : 'Polling update gagal.');
        }
        setButtonsDisabled(false);
    }

    function start(operation, extra) {
        failureCount = 0;
        panel.hidden = false;
        setButtonsDisabled(true);
        request(operation, extra || {}).then(handlePayload).catch(handleFailure);
    }

    Array.from(document.querySelectorAll('[data-cbt-update-form]')).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var isInstallForm = form.getAttribute('data-cbt-update-form') === 'install';
            var submitButton = form.querySelector('button[type="submit"]');
            if (isInstallForm && submitButton && submitButton.getAttribute('data-cbt-update-locked-disabled') === '1') {
                event.preventDefault();
                panel.hidden = false;
                if (message) {
                    message.textContent = submitButton.getAttribute('data-cbt-update-disabled-message') || 'Tidak ada versi update yang lebih baru untuk diinstall.';
                }
                return;
            }
            if (typeof window.ajaxurl === 'undefined') {
                return;
            }
            event.preventDefault();
            start(isInstallForm ? 'start_install' : 'start_check');
        });
    });

    Array.from(document.querySelectorAll('[data-cbt-update-rollback]')).forEach(function (button) {
        button.addEventListener('click', function () {
            if (typeof window.ajaxurl === 'undefined') {
                return;
            }
            start('start_rollback', { backup_id: button.getAttribute('data-cbt-update-rollback') || '' });
        });
    });

    if (resume) {
        resume.addEventListener('click', function () {
            if (currentToken !== '') {
                scheduleTick(10);
            }
        });
    }

    var initialPayload = readInitialPayload();
    if (initialPayload && typeof initialPayload === 'object') {
        render(initialPayload);
        if (!initialPayload.complete) {
            currentToken = String(initialPayload.token || '');
            scheduleTick(String(initialPayload.status || '') === 'reload_required' ? 200 : 800);
        }
    }
})();
</script>
