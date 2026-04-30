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
    .cbt-update-progress {
        display: grid;
        gap: 10px;
        margin-top: 18px;
        padding: 14px 16px;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        background: #eff6ff;
    }
    .cbt-update-progress[hidden] {
        display: none;
    }
    .cbt-update-progress-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .cbt-update-progress-title {
        color: #0f172a;
        font-weight: 700;
    }
    .cbt-update-progress-label {
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .cbt-update-progress-track {
        overflow: hidden;
        height: 10px;
        border-radius: 999px;
        background: #dbeafe;
    }
    .cbt-update-progress-fill {
        width: 0%;
        height: 100%;
        border-radius: inherit;
        background: #2271b1;
        transition: width 180ms ease;
    }
    .cbt-update-progress-message {
        color: #334155;
        line-height: 1.5;
    }
    .cbt-update-table-wrap {
        overflow-x: auto;
    }
    .cbt-update-table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
        background: #ffffff;
        border: 1px solid #dbe5ef;
        border-radius: 8px;
        overflow: hidden;
    }
    .cbt-update-table th,
    .cbt-update-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #edf2f7;
        text-align: left;
        vertical-align: top;
    }
    .cbt-update-table th {
        color: #475569;
        font-size: 12px;
        text-transform: uppercase;
    }
    .cbt-update-table td {
        color: #0f172a;
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
                    <form method="post" action="<?php echo esc_url($check_action_url); ?>" style="margin:0;" data-cbt-update-form="check">
                        <input type="hidden" name="action" value="cbt_check_update_now" />
                        <?php wp_nonce_field('cbt_check_update_now'); ?>
                        <button type="submit" class="button button-primary">Cek Update Sekarang</button>
                    </form>
                    <form method="post" action="<?php echo esc_url($install_action_url); ?>" style="margin:0;" data-cbt-update-form="install">
                        <input type="hidden" name="action" value="cbt_install_update_now" />
                        <?php wp_nonce_field('cbt_install_update_now'); ?>
                        <button type="submit" class="button cbt-admin-btn--warning" <?php echo $can_install ? '' : 'disabled'; ?>>Install Update</button>
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

        <div class="cbt-update-grid">
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
            if (typeof window.ajaxurl === 'undefined') {
                return;
            }
            event.preventDefault();
            start(form.getAttribute('data-cbt-update-form') === 'install' ? 'start_install' : 'start_check');
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
