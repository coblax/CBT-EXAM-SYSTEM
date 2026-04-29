<style>
    .cbt-update-page {
        max-width: 1180px;
    }
    .cbt-update-shell {
        display: grid;
        gap: 18px;
        margin-top: 18px;
    }
    .cbt-update-hero,
    .cbt-update-card {
        padding: 24px 28px;
        border: 1px solid #d7dbe2;
        border-radius: 22px;
        background:
            radial-gradient(circle at top right, rgba(34, 113, 177, 0.08), transparent 34%),
            linear-gradient(135deg, #ffffff 0%, #f6f9fc 100%);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.05);
    }
    .cbt-update-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
    }
    .cbt-update-hero-copy {
        max-width: 720px;
    }
    .cbt-update-kicker {
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
    .cbt-update-hero h1 {
        margin: 12px 0 8px;
        font-size: 30px;
        line-height: 1.12;
    }
    .cbt-update-hero p,
    .cbt-update-card p {
        margin: 0;
        color: #4b5563;
        line-height: 1.6;
    }
    .cbt-update-overview {
        display: grid;
        gap: 10px;
        min-width: 250px;
    }
    .cbt-update-pill {
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
    .cbt-update-status-pill {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 0 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
    }
    .cbt-update-status-pill--muted {
        background: #eef2f7;
        color: #475569;
    }
    .cbt-update-status-pill--info {
        background: #e8f1ff;
        color: #0f4fa8;
    }
    .cbt-update-status-pill--ok {
        background: #e7f6ec;
        color: #166534;
    }
    .cbt-update-status-pill--warning {
        background: #fff6e6;
        color: #9a6700;
    }
    .cbt-update-status-pill--danger {
        background: #feecec;
        color: #b42318;
    }
    .cbt-update-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.9fr);
        gap: 18px;
    }
    .cbt-update-card h2 {
        margin: 0 0 6px;
        font-size: 19px;
        line-height: 1.2;
    }
    .cbt-update-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 14px;
    }
    .cbt-update-card-header-copy {
        display: grid;
        gap: 6px;
    }
    .cbt-update-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-top: 16px;
    }
    .cbt-update-meta-item {
        padding: 14px 16px;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        background: #ffffff;
    }
    .cbt-update-meta-label {
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .cbt-update-meta-value {
        color: #0f172a;
        font-size: 16px;
        font-weight: 700;
    }
    .cbt-update-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    .cbt-update-actions .button {
        min-height: 44px;
        padding: 0 18px;
        border-radius: 14px;
    }
    .cbt-update-page .button-primary {
        box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
    }
    .cbt-update-checklist {
        display: grid;
        gap: 12px;
        margin-top: 16px;
    }
    .cbt-update-checklist-item {
        padding: 14px 16px;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        background: #ffffff;
    }
    .cbt-update-checklist-item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 6px;
    }
    .cbt-update-checklist-item-title {
        color: #0f172a;
        font-weight: 700;
    }
    .cbt-update-checklist-item-message {
        color: #475569;
        line-height: 1.55;
    }
    .cbt-update-preflight-label {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .cbt-update-preflight-label--ok {
        background: #e7f6ec;
        color: #166534;
    }
    .cbt-update-preflight-label--warning {
        background: #fff6e6;
        color: #9a6700;
    }
    .cbt-update-preflight-label--danger {
        background: #feecec;
        color: #b42318;
    }
    .cbt-update-changelog {
        margin-top: 16px;
        padding: 16px 18px;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        background: #ffffff;
        white-space: pre-wrap;
        color: #334155;
        line-height: 1.7;
    }
    .cbt-update-empty {
        padding: 16px 18px;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        background: #ffffff;
        color: #475569;
    }
    @media (max-width: 960px) {
        .cbt-update-hero,
        .cbt-update-grid {
            grid-template-columns: 1fr;
            display: grid;
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
                <div class="cbt-update-actions">
                    <form method="post" action="<?php echo esc_url($check_action_url); ?>" style="margin:0;">
                        <input type="hidden" name="action" value="cbt_check_update_now" />
                        <?php wp_nonce_field('cbt_check_update_now'); ?>
                        <button type="submit" class="button button-primary">Cek Update Sekarang</button>
                    </form>
                    <form method="post" action="<?php echo esc_url($install_action_url); ?>" style="margin:0;">
                        <input type="hidden" name="action" value="cbt_install_update_now" />
                        <?php wp_nonce_field('cbt_install_update_now'); ?>
                        <button type="submit" class="button cbt-admin-btn--warning" <?php echo $can_install ? '' : 'disabled'; ?>>Install Update</button>
                    </form>
                    <a class="button cbt-admin-btn--secondary" href="<?php echo esc_url($release_url); ?>" target="_blank" rel="noopener noreferrer">Lihat Release GitHub</a>
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
        </div>
    </div>
</div>
