        <style>
            .cbt-users-page {
                max-width: 1180px;
            }
            .cbt-users-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            }
            .cbt-users-hero {
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
            .cbt-users-hero-copy {
                max-width: 680px;
            }
            .cbt-users-kicker {
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
            .cbt-users-hero h1 {
                margin: 12px 0 8px;
                font-size: 30px;
                line-height: 1.15;
            }
            .cbt-users-hero p {
                margin: 0;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-users-overview {
                display: grid;
                gap: 10px;
                min-width: 260px;
            }
            .cbt-users-pill {
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
            .cbt-users-tabs {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-users-tab {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 42px;
                padding: 0 16px;
                border: 1px solid #c9d5e6;
                border-radius: 12px;
                background: #ffffff;
                color: #334155;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.16s ease;
            }
            .cbt-users-tab:hover,
            .cbt-users-tab:focus {
                border-color: #2271b1;
                color: #0f4fa8;
                outline: none;
                box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
            }
            .cbt-users-tab.is-active {
                border-color: #2271b1;
                background: #2271b1;
                color: #ffffff;
                box-shadow: 0 10px 24px rgba(34, 113, 177, 0.18);
            }
            .cbt-users-panel {
                display: none;
                padding: 24px;
                border: 1px solid #dcdcde;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
            }
            .cbt-users-panel.is-active {
                display: block;
            }
            .cbt-users-panel[data-cbt-users-panel="list"].is-loading {
                opacity: 0.72;
                transition: opacity 0.18s ease;
            }
            .cbt-users-panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }
            .cbt-users-panel-header h2 {
                margin: 0 0 6px;
                font-size: 18px;
                line-height: 1.2;
            }
            .cbt-users-panel-header p {
                margin: 0;
                color: #646970;
                line-height: 1.55;
            }
            .cbt-users-chip {
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
            .cbt-users-actions,
            .cbt-users-form-actions,
            .cbt-users-bulk-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-users-form-actions {
                margin-top: 18px;
            }
            .cbt-users-bulk-actions {
                margin: 14px 0 4px;
                padding: 12px;
                border: 1px solid #e3ebf4;
                border-radius: 18px;
                background:
                    radial-gradient(circle at top right, rgba(220, 38, 38, 0.06), transparent 38%),
                    linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
            }
            .cbt-users-bulk-button.button {
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
            .cbt-users-bulk-button.button::before {
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
            .cbt-users-bulk-button.button:hover,
            .cbt-users-bulk-button.button:focus {
                transform: translateY(-1px);
                outline: none;
            }
            .cbt-users-bulk-button.button:focus-visible {
                box-shadow:
                    0 0 0 3px rgba(220, 38, 38, 0.14),
                    0 16px 32px rgba(148, 28, 28, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.94);
            }
            .cbt-users-bulk-button--selected.button {
                border-color: #efcaca;
                background: linear-gradient(180deg, #ffffff 0%, #fff5f5 100%);
                color: #b42318;
            }
            .cbt-users-bulk-button--selected.button::before {
                background-color: #fff1f1;
            }
            .cbt-users-bulk-button--selected.button:hover,
            .cbt-users-bulk-button--selected.button:focus {
                border-color: #e5a5a5;
                background: linear-gradient(180deg, #ffffff 0%, #ffefef 100%);
                color: #991b1b;
                box-shadow:
                    0 16px 30px rgba(185, 28, 28, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.96);
            }
            .cbt-users-bulk-button--all.button {
                border-color: #d98b8b;
                background: linear-gradient(180deg, #fff6f6 0%, #fee2e2 100%);
                color: #8f1111;
                box-shadow:
                    0 16px 32px rgba(153, 27, 27, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.9);
            }
            .cbt-users-bulk-button--all.button::before {
                background-color: rgba(185, 28, 28, 0.12);
            }
            .cbt-users-bulk-button--all.button:hover,
            .cbt-users-bulk-button--all.button:focus {
                border-color: #c75e5e;
                background: linear-gradient(180deg, #fff1f1 0%, #fecaca 100%);
                color: #7f1d1d;
                box-shadow:
                    0 18px 34px rgba(153, 27, 27, 0.16),
                    inset 0 1px 0 rgba(255, 255, 255, 0.92);
            }
            .cbt-users-panel .form-table {
                margin: 0;
                border-collapse: separate;
                border-spacing: 0 18px;
            }
            .cbt-users-panel .form-table th {
                width: 190px;
                padding: 10px 18px 0 0;
                vertical-align: top;
                color: #0f172a;
                font-size: 14px;
                font-weight: 700;
            }
            .cbt-users-panel .form-table td {
                padding: 0;
                vertical-align: top;
            }
            .cbt-users-panel .form-table th label,
            .cbt-users-panel .form-table td > label {
                color: inherit;
                font-weight: inherit;
            }
            .cbt-users-panel input[type="text"],
            .cbt-users-panel input[type="email"],
            .cbt-users-panel input[type="search"],
            .cbt-users-panel select,
            .cbt-users-panel textarea {
                width: 100%;
            }
            .cbt-users-panel input[type="text"],
            .cbt-users-panel input[type="email"],
            .cbt-users-panel input[type="search"],
            .cbt-users-panel select {
                height: 42px;
                border-radius: 12px;
                border: 1px solid #d0d7e2;
                padding: 0 12px;
            }
            .cbt-users-panel select {
                background-position: right 12px center;
            }
            .cbt-users-panel select:disabled {
                background: #f8fafc;
                color: #94a3b8;
            }
            .cbt-users-panel input[type="file"] {
                padding: 8px 0;
            }
            .cbt-users-panel .regular-text,
            .cbt-users-panel textarea {
                max-width: 480px;
            }
            .cbt-users-panel .description {
                margin: 6px 0 0;
                color: #6b7280;
            }
            .cbt-users-import-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.85fr) minmax(320px, 1fr);
                gap: 18px;
                align-items: start;
            }
            .cbt-users-import-card {
                padding: 18px 20px;
                border: 1px solid #dbe4ef;
                border-radius: 18px;
                background:
                    radial-gradient(circle at top right, rgba(34, 113, 177, 0.06), transparent 34%),
                    linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95);
            }
            .cbt-users-import-card-label {
                display: block;
                margin-bottom: 10px;
                color: #0f172a;
                font-size: 14px;
                font-weight: 700;
            }
            .cbt-users-import-card .description {
                margin-top: 8px;
            }
            .cbt-users-import-card ul {
                margin: 0 0 0 18px;
                list-style: disc;
            }
            .cbt-users-panel .button {
                border-radius: 10px;
            }
            .cbt-users-panel .button-primary {
                background: #1d4ed8;
                border-color: #1d4ed8;
            }
            .cbt-users-import-progress {
                display: grid;
                gap: 10px;
                margin: 14px 0 18px;
                padding: 16px;
                border: 1px dashed #93c5fd;
                border-radius: 16px;
                background: #eff6ff;
            }
            .cbt-users-import-progress strong {
                font-size: 13px;
                color: #1e3a8a;
            }
            .cbt-users-import-progress-track {
                height: 8px;
                border-radius: 999px;
                background: rgba(37, 99, 235, 0.12);
                overflow: hidden;
            }
            .cbt-users-import-progress-fill {
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb, #1d4ed8);
            }
            .cbt-users-import-progress-meta {
                font-size: 12px;
                color: #1f2937;
            }
            .cbt-users-list-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 14px;
            }
            .cbt-users-filter-form {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            .cbt-users-filter-form input[type="search"] {
                min-width: 220px;
            }
            .cbt-users-filter-form label {
                font-weight: 600;
            }
            .cbt-users-filter-form select {
                min-width: 150px;
            }
            .cbt-users-filter-reset {
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
            .cbt-users-filter-reset:hover,
            .cbt-users-filter-reset:focus {
                border-color: #1d4ed8;
                color: #1d4ed8;
                outline: none;
            }
            .cbt-users-photo-preview {
                margin-bottom: 10px;
            }
            .cbt-users-photo-preview img {
                width: 84px;
                height: 84px;
                object-fit: cover;
                border-radius: 18px;
                border: 1px solid #dbe1ea;
            }
            .cbt-users-table-photo {
                width: 38px;
                height: 38px;
                object-fit: cover;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
            }
            .cbt-users-table-wrap {
                overflow-x: auto;
            }
            .cbt-users-panel .widefat {
                border-radius: 12px;
                overflow: hidden;
                border: 1px solid #e2e8f0;
            }
            .cbt-users-panel .widefat thead th {
                background: #f8fafc;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .cbt-users-panel .widefat td,
            .cbt-users-panel .widefat th {
                vertical-align: top;
            }
            .cbt-users-panel .widefat tbody tr:hover {
                background: #f8fafc;
            }
            .cbt-users-row-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-users-row-action {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                color: #1d4ed8;
                font-weight: 600;
                text-decoration: none;
            }
            .cbt-users-row-action:hover,
            .cbt-users-row-action:focus {
                text-decoration: underline;
            }
            .cbt-users-row-action--edit {
                color: #0f4fa8;
            }
            .cbt-users-row-action--edit:hover,
            .cbt-users-row-action--edit:focus {
                color: #0f4fa8;
            }
            .cbt-users-row-action--delete {
                color: #b91c1c;
            }
            .cbt-users-row-action--delete:hover,
            .cbt-users-row-action--delete:focus {
                color: #991b1b;
            }
            .cbt-users-pagination-wrap {
                margin-top: 12px;
            }
            .cbt-users-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-users-pagination .cbt-users-total {
                font-weight: 600;
            }
            .cbt-users-pagination-links {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
            }
            .cbt-users-pagination-links .page-numbers {
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
            .cbt-users-pagination-links .page-numbers:hover,
            .cbt-users-pagination-links .page-numbers:focus {
                border-color: #1d4ed8;
                color: #1d4ed8;
            }
            .cbt-users-pagination-links .page-numbers.current {
                border-color: #1d4ed8;
                background: #1d4ed8;
                color: #fff;
            }
            .cbt-users-pagination-links .page-numbers.prev,
            .cbt-users-pagination-links .page-numbers.next {
                padding: 0 12px;
            }
            .cbt-users-pagination-links .page-numbers.dots {
                border: none;
                background: transparent;
                min-width: auto;
            }

            @media (max-width: 960px) {
                .cbt-users-hero,
                .cbt-users-panel-header,
                .cbt-users-list-toolbar {
                    flex-direction: column;
                    align-items: flex-start;
                }
                .cbt-users-overview {
                    width: 100%;
                }
                .cbt-users-page {
                    max-width: 100%;
                }
                .cbt-users-hero,
                .cbt-users-panel {
                    padding: 20px;
                }
                .cbt-users-import-grid {
                    grid-template-columns: 1fr;
                }
                .cbt-users-panel .form-table th {
                    width: 100%;
                }
                .cbt-users-filter-form input[type="search"] {
                    min-width: 100%;
                }
                .cbt-users-pagination-links .page-numbers {
                    min-width: 30px;
                }
            }
        </style>
        <div class="wrap cbt-users-page" data-cbt-users-default-tab="<?php echo esc_attr($default_user_tab); ?>" data-cbt-users-force-tab="<?php echo $user_tab_is_forced ? '1' : '0'; ?>">
            <div class="cbt-users-shell">
                <section class="cbt-users-hero">
                    <div class="cbt-users-hero-copy">
                        <span class="cbt-users-kicker">Users</span>
                        <h1>CBT Users</h1>
                        <p>Kelola user CBT secara lengkap: buat manual, import massal CSV/XLSX, dan kelola daftar user dengan filter cepat.</p>
                    </div>
                    <div class="cbt-users-overview" aria-hidden="true">
                        <span class="cbt-users-pill"><?php echo esc_html(sprintf('Total: %d user', $total_users)); ?></span>
                        <span class="cbt-users-pill"><?php echo esc_html($is_editing_user ? 'Mode edit aktif' : 'Tambah manual siap'); ?></span>
                        <span class="cbt-users-pill"><?php echo esc_html(is_array($import_state) ? 'Import berjalan' : 'Import siap'); ?></span>
                    </div>
                </section>

                <?php if ($notice): ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                <?php endif; ?>

                <div class="cbt-users-tabs" role="tablist" aria-label="Navigasi CBT Users">
                    <button type="button" class="cbt-users-tab" data-cbt-users-tab="form" role="tab" aria-selected="false">Form User</button>
                    <button type="button" class="cbt-users-tab" data-cbt-users-tab="import" role="tab" aria-selected="false">Import Users</button>
                    <button type="button" class="cbt-users-tab" data-cbt-users-tab="list" role="tab" aria-selected="false">Daftar Users</button>
                </div>

                <section class="cbt-users-panel" data-cbt-users-panel="form" role="tabpanel">
                    <div class="cbt-users-panel-header">
                        <div>
                            <h2><?php echo $is_editing_user ? 'Edit User' : 'Tambah User Manual'; ?></h2>
                            <p><?php echo $is_editing_user ? 'Perbarui identitas, role, kelas, ruang, jenis kelamin, dan foto user tanpa pindah ke area daftar.' : 'Buat user baru secara manual untuk kebutuhan cepat tanpa harus upload file import.'; ?></p>
                        </div>
                        <?php if ($is_editing_user): ?>
                            <a href="<?php echo esc_url($user_clear_edit_url); ?>" class="button button-secondary" data-cbt-users-tab-link="list">Batal Edit</a>
                        <?php else: ?>
                            <span class="cbt-users-chip">Manual</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_editing_user): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-users-tab-submit="form">
                            <?php wp_nonce_field('cbt_update_user_manual'); ?>
                            <input type="hidden" name="action" value="cbt_update_user_manual" />
                            <input type="hidden" name="user_id" value="<?php echo (int) $editing_user->ID; ?>" />

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th><label for="cbt-edit-user-name">Nama</label></th>
                                    <td><input required type="text" id="cbt-edit-user-name" name="name" class="regular-text" value="<?php echo esc_attr((string) $editing_user->display_name); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-email">Email</label></th>
                                    <td><input required type="email" id="cbt-edit-user-email" name="email" class="regular-text" value="<?php echo esc_attr((string) $editing_user->user_email); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-nisn">NISN</label></th>
                                    <td>
                                        <input type="text" id="cbt-edit-user-nisn" name="nisn" class="regular-text" inputmode="numeric" pattern="[0-9]*" value="<?php echo esc_attr($editing_nisn); ?>" />
                                        <p class="description">Wajib untuk role siswa. Hanya angka dan tidak boleh sama dengan siswa lain.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-username">Username</label></th>
                                    <td><input required type="text" id="cbt-edit-user-username" name="username" class="regular-text" value="<?php echo esc_attr((string) $editing_user->user_login); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-password">Password Baru</label></th>
                                    <td>
                                        <input type="text" id="cbt-edit-user-password" name="password" class="regular-text" />
                                        <p class="description">Kosongkan jika password tidak diubah.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-role">Role</label></th>
                                    <td>
                                        <select id="cbt-edit-user-role" name="role">
                                            <option value="siswa" <?php selected($editing_role, 'siswa'); ?>>siswa</option>
                                            <option value="guru" <?php selected($editing_role, 'guru'); ?>>guru</option>
                                            <?php if ($is_admin_scope): ?>
                                                <option value="admin" <?php selected($editing_role, 'admin'); ?>>admin</option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-kelas">Kode Kelas</label></th>
                                    <td><input type="text" id="cbt-edit-user-kelas" name="kode_kelas" class="regular-text" value="<?php echo esc_attr($editing_kelas); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-ruang">Kode Ruang</label></th>
                                    <td><input type="text" id="cbt-edit-user-ruang" name="kode_ruang" class="regular-text" value="<?php echo esc_attr($editing_ruang); ?>" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-agama">Agama</label></th>
                                    <td>
                                        <select id="cbt-edit-user-agama" name="agama" class="regular-text">
                                            <option value="">Pilih Agama</option>
                                            <?php foreach ($agama_options as $agama_option): ?>
                                                <option value="<?php echo esc_attr($agama_option); ?>" <?php selected($editing_agama_form, $agama_option); ?>><?php echo esc_html($agama_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-jenis-kelamin">Jenis Kelamin</label></th>
                                    <td>
                                        <select id="cbt-edit-user-jenis-kelamin" name="jenis_kelamin" class="regular-text">
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <?php foreach ($jenis_kelamin_options as $jenis_kelamin_option): ?>
                                                <option value="<?php echo esc_attr($jenis_kelamin_option); ?>" <?php selected($editing_jenis_kelamin_form, $jenis_kelamin_option); ?>><?php echo esc_html($jenis_kelamin_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Wajib untuk role siswa. Boleh dikosongkan untuk guru atau admin.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-edit-user-foto-file">Foto</label></th>
                                    <td>
                                        <?php if ($editing_foto !== ''): ?>
                                            <div class="cbt-users-photo-preview">
                                                <a href="<?php echo esc_url($editing_foto); ?>" target="_blank" rel="noopener noreferrer">
                                                    <img src="<?php echo esc_url($editing_foto); ?>" alt="<?php echo esc_attr((string) $editing_user->display_name); ?>" />
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" id="cbt-edit-user-foto-file" name="foto_file" accept="image/*" />
                                        <p class="description">Pilih file baru jika ingin mengganti foto.</p>
                                        <?php if ($editing_foto !== ''): ?>
                                            <label>
                                                <input type="checkbox" name="hapus_foto" value="1" />
                                                Hapus foto saat update.
                                            </label>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <div class="cbt-users-form-actions">
                                <?php echo get_submit_button('Update User', 'primary', 'submit', false); ?>
                                <a href="<?php echo esc_url($user_clear_edit_url); ?>" class="button button-secondary" data-cbt-users-tab-link="list">Batal Edit</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-users-tab-submit="form">
                            <?php wp_nonce_field('cbt_create_user_manual'); ?>
                            <input type="hidden" name="action" value="cbt_create_user_manual" />

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th><label for="cbt-user-name">Nama</label></th>
                                    <td><input required type="text" id="cbt-user-name" name="name" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-email">Email</label></th>
                                    <td><input required type="email" id="cbt-user-email" name="email" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-nisn">NISN</label></th>
                                    <td>
                                        <input type="text" id="cbt-user-nisn" name="nisn" class="regular-text" inputmode="numeric" pattern="[0-9]*" />
                                        <p class="description">Wajib untuk role siswa. Hanya angka dan tidak boleh sama dengan siswa lain.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-username">Username</label></th>
                                    <td><input required type="text" id="cbt-user-username" name="username" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-password">Password</label></th>
                                    <td>
                                        <input type="text" id="cbt-user-password" name="password" class="regular-text" />
                                        <p class="description">Kosongkan untuk generate password otomatis.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-role">Role</label></th>
                                    <td>
                                        <select id="cbt-user-role" name="role">
                                            <option value="siswa">siswa</option>
                                            <option value="guru">guru</option>
                                            <?php if ($is_admin_scope): ?>
                                                <option value="admin">admin</option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-kelas">Kode Kelas</label></th>
                                    <td><input type="text" id="cbt-user-kelas" name="kode_kelas" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-ruang">Kode Ruang</label></th>
                                    <td><input type="text" id="cbt-user-ruang" name="kode_ruang" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-agama">Agama</label></th>
                                    <td>
                                        <select id="cbt-user-agama" name="agama" class="regular-text">
                                            <option value="">Pilih Agama</option>
                                            <?php foreach ($agama_options as $agama_option): ?>
                                                <option value="<?php echo esc_attr($agama_option); ?>"><?php echo esc_html($agama_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-jenis-kelamin">Jenis Kelamin</label></th>
                                    <td>
                                        <select id="cbt-user-jenis-kelamin" name="jenis_kelamin" class="regular-text">
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <?php foreach ($jenis_kelamin_options as $jenis_kelamin_option): ?>
                                                <option value="<?php echo esc_attr($jenis_kelamin_option); ?>"><?php echo esc_html($jenis_kelamin_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">Wajib untuk role siswa. Boleh dikosongkan untuk guru atau admin.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cbt-user-foto-file">Foto</label></th>
                                    <td>
                                        <input type="file" id="cbt-user-foto-file" name="foto_file" accept="image/*" />
                                        <p class="description">Pilih file foto profil user (opsional).</p>
                                    </td>
                                </tr>
                            </table>

                            <div class="cbt-users-form-actions">
                                <?php echo get_submit_button('Simpan User', 'primary', 'submit', false); ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="cbt-users-panel" data-cbt-users-panel="import" role="tabpanel">
                    <div class="cbt-users-panel-header">
                        <div>
                            <h2>Import Users</h2>
                            <p>Upload file CSV atau XLSX untuk menambahkan atau memperbarui banyak user sekaligus dengan proses batch otomatis.</p>
                        </div>
                        <span class="cbt-users-chip">CSV / XLSX</span>
                    </div>

                    <?php if (is_array($import_state)): ?>
                        <div class="cbt-users-import-progress">
                            <strong>
                                Progress Import User:
                                <?php echo esc_html((string) $import_offset . ' / ' . (string) $import_total); ?>
                                (<?php echo esc_html(number_format($import_progress_percent, 2)); ?>%)
                            </strong>
                            <div class="cbt-users-import-progress-track" aria-hidden="true">
                                <div class="cbt-users-import-progress-fill" style="width: <?php echo esc_attr((string) $import_progress_percent); ?>%;"></div>
                            </div>
                            <div class="cbt-users-import-progress-meta">
                                Created: <?php echo esc_html((string) $import_created); ?> |
                                Updated: <?php echo esc_html((string) $import_updated); ?> |
                                Failed: <?php echo esc_html((string) $import_failed); ?>
                                <br />
                                <?php if ($import_is_running): ?>
                                    Memproses batch user berikutnya...
                                    <script>
                                        window.setTimeout(function () {
                                            window.location.href = <?php echo wp_json_encode($import_continue_url); ?>;
                                        }, 350);
                                    </script>
                                <?php else: ?>
                                    <span style="color:#0a7a2f; font-weight:600;">Import user selesai diproses.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="cbt-users-actions">
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_user_template'), 'cbt_download_user_template')); ?>">
                            Download Template CSV
                        </a>
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_user_template_xlsx'), 'cbt_download_user_template_xlsx')); ?>">
                            Download Template XLSX
                        </a>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-users-tab-submit="import">
                        <?php wp_nonce_field('cbt_import_users'); ?>
                        <input type="hidden" name="action" value="cbt_import_users" />

                        <div class="cbt-users-import-grid">
                            <div class="cbt-users-import-card">
                                <label class="cbt-users-import-card-label" for="cbt-user-file">File Import</label>
                                <input required type="file" id="cbt-user-file" name="user_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                                <div class="description">
                                    <ul>
                                        <li>Header template terbaru: <code>name,email,nisn,username,password,role,kode_kelas,kode_ruang,agama,jenis_kelamin,foto_file</code>.</li>
                                        <li>Role yang didukung: <code>admin</code>, <code>guru</code>, <code>siswa</code> dan juga kompatibel dengan <code>teacher</code>, <code>student</code>.</li>
                                        <li><code>username</code> dan <code>email</code> tidak boleh duplikat antarbaris dalam file import yang sama.</li>
                                        <li>Untuk baris <code>siswa</code>, <code>nisn</code> wajib diisi dan tidak boleh duplikat dengan siswa lain maupun antarbaris file import.</li>
                                        <li>Untuk <code>guru</code>/<code>admin</code>, <code>nisn</code> boleh kosong.</li>
                                        <li>Kolom opsional per baris: <code>email</code>, <code>kode_kelas</code>, <code>kode_ruang</code>, <code>agama</code>, dan <code>foto_file</code>. Kolom <code>jenis_kelamin</code> wajib untuk <code>siswa</code>, tetapi boleh kosong untuk <code>guru</code>/<code>admin</code>.</li>
                                        <li>Kolom <code>jenis_kelamin</code> menerima nilai seperti <code>Laki-laki</code>, <code>Perempuan</code>, <code>L</code>, atau <code>P</code>. Nilai lain akan ditolak saat import.</li>
                                        <li>Jika <code>foto_file</code> kosong untuk user <code>siswa</code>, sistem otomatis memakai <code>Default Pria.png</code> atau <code>Default Wanita.png</code> sesuai <code>jenis_kelamin</code>.</li>
                                        <li>Jika <code>email</code> kosong atau tidak valid tetapi <code>nisn</code> ada, sistem otomatis membuat email <code>nisn@student.sch.id</code>.</li>
                                        <li>Format file yang didukung: <code>.csv</code> dan <code>.xlsx</code>. Untuk CSV, delimiter koma atau titik-koma sama-sama didukung.</li>
                                        <li>Gambar yang ditempel langsung di Excel tidak dibaca. Untuk foto massal, isi kolom <code>foto_file</code> lalu upload file <code>.zip</code> yang berisi foto-foto tersebut.</li>
                                        <li>Jika semua kolom <code>foto_file</code> kosong, ZIP foto tidak wajib diupload.</li>
                                        <li>Import data besar diproses bertahap otomatis, batch <?php echo (int) $import_batch_size; ?> user per putaran, untuk mencegah timeout.</li>
                                        <li>Progress import akan tampil otomatis: jumlah diproses, persentase, <code>created</code>, <code>updated</code>, dan <code>failed</code>.</li>
                                        <li>Untuk lebih dari 500 user, disarankan memakai <code>.csv</code> karena parsing biasanya lebih cepat.</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="cbt-users-import-card">
                                <label class="cbt-users-import-card-label" for="cbt-user-photo-zip">ZIP Foto</label>
                                <input type="file" id="cbt-user-photo-zip" name="user_photo_zip" accept=".zip,application/zip,application/x-zip-compressed" />
                                <div class="description">
                                    <ul>
                                        <li>Opsional. Upload hanya jika ada baris yang mengisi <code>foto_file</code>.</li>
                                        <li>Nama file di ZIP harus sama persis dengan nilai pada kolom <code>foto_file</code>, misalnya <code>1000000001.jpg</code>.</li>
                                        <li>ZIP hanya boleh berisi file gambar <code>jpg</code>, <code>jpeg</code>, <code>png</code>, <code>gif</code>, atau <code>webp</code>.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="cbt-users-form-actions">
                            <?php echo get_submit_button('Import Users', 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </section>

                <section class="cbt-users-panel" data-cbt-users-panel="list" role="tabpanel">
                    <div class="cbt-users-panel-header">
                        <div>
                            <h2>Daftar User CBT</h2>
                            <p>Filter user berdasarkan kata kunci, role, kelas, ruang, agama, dan jenis kelamin, lalu lakukan edit atau bulk delete dari panel khusus daftar.</p>
                        </div>
                        <span class="cbt-users-chip"><?php echo esc_html(sprintf('%d total', $total_users)); ?></span>
                    </div>

                    <div class="cbt-users-list-toolbar">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-users-filter-form" data-cbt-users-tab-submit="list">
                            <input type="hidden" name="page" value="cbt-user-import" />
                            <input type="hidden" name="cbt_user_paged" value="1" />
                            <input type="search" id="cbt-users-filter-search" name="cbt_user_q" value="<?php echo esc_attr($search); ?>" placeholder="Cari username / nama / email" />
                            <select id="cbt-users-filter-role" name="cbt_user_role">
                                <option value="">Semua Role</option>
                                <option value="admin" <?php selected($filter_role, 'admin'); ?>>admin</option>
                                <option value="guru" <?php selected($filter_role, 'guru'); ?>>guru</option>
                                <option value="siswa" <?php selected($filter_role, 'siswa'); ?>>siswa</option>
                            </select>
                            <select id="cbt-users-filter-kelas" name="cbt_user_kelas">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($kelas_options as $kelas_option): ?>
                                    <option value="<?php echo esc_attr($kelas_option); ?>" <?php selected($filter_kelas, $kelas_option); ?>>
                                        <?php echo esc_html($kelas_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="cbt-users-filter-ruang" name="cbt_user_ruang">
                                <option value="">Semua Ruang</option>
                                <?php foreach ($ruang_options as $ruang_option): ?>
                                    <option value="<?php echo esc_attr($ruang_option); ?>" <?php selected($filter_ruang, $ruang_option); ?>>
                                        <?php echo esc_html($ruang_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="cbt-users-filter-agama" name="cbt_user_agama">
                                <option value="">Semua Agama</option>
                                <?php foreach ($agama_options as $agama_option): ?>
                                    <option value="<?php echo esc_attr($agama_option); ?>" <?php selected($filter_agama, $agama_option); ?>>
                                        <?php echo esc_html($agama_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="cbt-users-filter-jenis-kelamin" name="cbt_user_jenis_kelamin">
                                <option value="">Semua Jenis Kelamin</option>
                                <?php foreach ($jenis_kelamin_options as $jenis_kelamin_option): ?>
                                    <option value="<?php echo esc_attr($jenis_kelamin_option); ?>" <?php selected($filter_jenis_kelamin, $jenis_kelamin_option); ?>>
                                        <?php echo esc_html($jenis_kelamin_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="cbt-users-filter-per-page" name="cbt_user_per_page">
                                <?php foreach ($per_page_options as $per_page_option): ?>
                                    <option value="<?php echo (int) $per_page_option; ?>" <?php selected($per_page, $per_page_option); ?>>
                                        <?php echo esc_html((string) $per_page_option); ?> / halaman
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?php echo esc_url($user_reset_url); ?>" class="cbt-users-filter-reset" data-cbt-users-tab-link="list">Reset</a>
                        </form>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-users-tab-submit="list">
                        <?php wp_nonce_field('cbt_bulk_delete_users'); ?>
                        <input type="hidden" name="action" value="cbt_bulk_delete_users" />
                        <input type="hidden" name="cbt_user_q" value="<?php echo esc_attr($search); ?>" />
                        <input type="hidden" name="cbt_user_role" value="<?php echo esc_attr($filter_role); ?>" />
                        <input type="hidden" name="cbt_user_kelas" value="<?php echo esc_attr($filter_kelas); ?>" />
                        <input type="hidden" name="cbt_user_ruang" value="<?php echo esc_attr($filter_ruang); ?>" />
                        <input type="hidden" name="cbt_user_agama" value="<?php echo esc_attr($filter_agama); ?>" />
                        <input type="hidden" name="cbt_user_jenis_kelamin" value="<?php echo esc_attr($filter_jenis_kelamin); ?>" />
                        <input type="hidden" name="cbt_user_per_page" value="<?php echo (int) $per_page; ?>" />
                        <input type="hidden" name="cbt_user_paged" value="<?php echo (int) $current_page; ?>" />
                        <div class="cbt-users-bulk-actions">
                            <button type="submit" class="button button-secondary cbt-users-bulk-button cbt-users-bulk-button--selected" name="bulk_mode" value="selected" onclick="return confirm('Yakin hapus user yang dipilih?');">Delete Selected</button>
                            <button type="submit" class="button button-secondary cbt-users-bulk-button cbt-users-bulk-button--all" name="bulk_mode" value="all_filtered" onclick="return confirm('Yakin hapus semua user sesuai hasil filter saat ini?');">Delete All (Filtered)</button>
                        </div>

                        <div class="cbt-users-table-wrap">
                        <table class="widefat striped">
                            <thead>
                            <tr>
                                <th style="width:32px;"><input type="checkbox" id="cbt-user-select-all" /></th>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>NISN</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Kode Kelas</th>
                                <th>Kode Ruang</th>
                                <th>Agama</th>
                                <th>Jenis Kelamin</th>
                                <th>Foto</th>
                                <th>Registered</th>
                                <th>Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="14">Tidak ada user.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $role = isset($user->roles[0]) ? (string) $user->roles[0] : '';
                                    $nisn = (string) get_user_meta((int) $user->ID, 'nisn', true);
                                    $kelas = (string) get_user_meta((int) $user->ID, 'kode_kelas', true);
                                    $ruang = (string) get_user_meta((int) $user->ID, 'kode_ruang', true);
                                    $agama = (string) get_user_meta((int) $user->ID, 'agama', true);
                                    $jenis_kelamin = CBT_Admin_Users_Service::normalize_supported_jenis_kelamin((string) get_user_meta((int) $user->ID, 'jenis_kelamin', true));
                                    $foto = class_exists('CBT_Student_Profile_Cache')
                                        ? CBT_Student_Profile_Cache::normalize_photo_url((string) get_user_meta((int) $user->ID, 'foto', true))
                                        : esc_url_raw((string) get_user_meta((int) $user->ID, 'foto', true));
                                    $edit_url = add_query_arg(
                                        [
                                            'page' => 'cbt-user-import',
                                            'edit_user' => (int) $user->ID,
                                            'cbt_user_q' => $search,
                                            'cbt_user_role' => $filter_role,
                                            'cbt_user_kelas' => $filter_kelas,
                                            'cbt_user_ruang' => $filter_ruang,
                                            'cbt_user_agama' => $filter_agama,
                                            'cbt_user_jenis_kelamin' => $filter_jenis_kelamin,
                                            'cbt_user_per_page' => $per_page,
                                            'cbt_user_paged' => $current_page,
                                        ],
                                        admin_url('admin.php')
                                    );
                                    $delete_url = wp_nonce_url(
                                        add_query_arg(
                                            [
                                                'cbt_user_q' => $search,
                                                'cbt_user_role' => $filter_role,
                                                'cbt_user_kelas' => $filter_kelas,
                                                'cbt_user_ruang' => $filter_ruang,
                                                'cbt_user_agama' => $filter_agama,
                                                'cbt_user_jenis_kelamin' => $filter_jenis_kelamin,
                                                'cbt_user_per_page' => $per_page,
                                                'cbt_user_paged' => $current_page,
                                            ],
                                            admin_url('admin-post.php?action=cbt_delete_user_manual&id=' . (int) $user->ID)
                                        ),
                                        'cbt_delete_user_manual_' . (int) $user->ID
                                    );
                                    $is_current_user = ((int) $user->ID === get_current_user_id());
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if (!$is_current_user): ?>
                                                <input type="checkbox" class="cbt-user-row-check" name="user_ids[]" value="<?php echo (int) $user->ID; ?>" />
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo (int) $user->ID; ?></td>
                                        <td><?php echo esc_html((string) $user->user_login); ?></td>
                                        <td><?php echo esc_html((string) $user->display_name); ?></td>
                                        <td><?php echo esc_html($nisn !== '' ? $nisn : '-'); ?></td>
                                        <td><?php echo esc_html((string) $user->user_email); ?></td>
                                        <td><?php echo esc_html(CBT_Admin_Users_Service::humanize_role($role)); ?></td>
                                        <td><?php echo esc_html($kelas); ?></td>
                                        <td><?php echo esc_html($ruang); ?></td>
                                        <td><?php echo esc_html($agama !== '' ? $agama : '-'); ?></td>
                                        <td><?php echo esc_html($jenis_kelamin !== '' ? $jenis_kelamin : '-'); ?></td>
                                        <td>
                                            <?php if ($foto !== ''): ?>
                                                <a href="<?php echo esc_url($foto); ?>" target="_blank" rel="noopener noreferrer">
                                                    <img src="<?php echo esc_url($foto); ?>" alt="<?php echo esc_attr((string) $user->display_name); ?>" class="cbt-users-table-photo" />
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(mysql2date('Y-m-d H:i', (string) $user->user_registered)); ?></td>
                                        <td>
                                            <div class="cbt-users-row-actions">
                                                <a class="cbt-users-row-action cbt-users-row-action--edit" href="<?php echo esc_url($edit_url); ?>">Edit</a>
                                                <?php if (!$is_current_user): ?>
                                                    <a class="cbt-users-row-action cbt-users-row-action--delete" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Hapus user ini?');">Delete</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        </div>

                        <div class="tablenav bottom cbt-users-pagination-wrap">
                            <div class="tablenav-pages cbt-users-pagination" style="float:none; margin:0;">
                                <span class="displaying-num cbt-users-total"><?php echo esc_html(sprintf('Total user: %d', $total_users)); ?></span>
                                <?php if (!empty($pagination_links)): ?>
                                    <span class="pagination-links cbt-users-pagination-links">
                                        <?php foreach ($pagination_links as $pagination_link): ?>
                                            <?php echo wp_kses_post($pagination_link); ?>
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
                const page = document.querySelector('.cbt-users-page');
                const tabButtons = Array.from(document.querySelectorAll('[data-cbt-users-tab]'));
                const tabPanels = Array.from(document.querySelectorAll('[data-cbt-users-panel]'));
                const tabStorageKey = 'cbt-users-active-tab';
                const defaultTab = page ? String(page.getAttribute('data-cbt-users-default-tab') || 'list') : 'list';
                const forceTab = page ? page.getAttribute('data-cbt-users-force-tab') === '1' : false;

                function activateTab(tabId, persist) {
                    let hasTarget = false;
                    tabButtons.forEach((button) => {
                        const isActive = button.getAttribute('data-cbt-users-tab') === tabId;
                        button.classList.toggle('is-active', isActive);
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        if (isActive) {
                            hasTarget = true;
                        }
                    });
                    tabPanels.forEach((panel) => {
                        const isActive = panel.getAttribute('data-cbt-users-panel') === tabId;
                        panel.classList.toggle('is-active', isActive);
                    });
                    if (persist && hasTarget && window.localStorage) {
                        window.localStorage.setItem(tabStorageKey, tabId);
                    }
                }

                if (page && tabButtons.length > 0 && tabPanels.length > 0) {
                    let initialTab = defaultTab;
                    if (!forceTab && window.localStorage) {
                        const savedTab = window.localStorage.getItem(tabStorageKey);
                        if (savedTab && tabPanels.some((panel) => panel.getAttribute('data-cbt-users-panel') === savedTab)) {
                            initialTab = savedTab;
                        }
                    }

                    activateTab(initialTab, false);

                    tabButtons.forEach((button) => {
                        button.addEventListener('click', function () {
                            activateTab(String(button.getAttribute('data-cbt-users-tab') || ''), true);
                        });
                    });

                    Array.from(document.querySelectorAll('form[data-cbt-users-tab-submit]')).forEach((form) => {
                        form.addEventListener('submit', function () {
                            const tabId = String(form.getAttribute('data-cbt-users-tab-submit') || '');
                            if (tabId !== '' && window.localStorage) {
                                window.localStorage.setItem(tabStorageKey, tabId);
                            }
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-users-tab-link]')).forEach((link) => {
                        link.addEventListener('click', function () {
                            const tabId = String(link.getAttribute('data-cbt-users-tab-link') || '');
                            if (tabId !== '' && window.localStorage) {
                                window.localStorage.setItem(tabStorageKey, tabId);
                            }
                        });
                    });
                }

                function bindRoleAwareJenisKelamin(roleSelector, jenisKelaminSelector) {
                    const roleField = document.querySelector(roleSelector);
                    const jenisKelaminField = document.querySelector(jenisKelaminSelector);
                    if (!roleField || !jenisKelaminField) {
                        return;
                    }

                    const syncState = function () {
                        const roleValue = String(roleField.value || '').toLowerCase();
                        const isStudent = roleValue === 'siswa';
                        jenisKelaminField.required = isStudent;
                        jenisKelaminField.setAttribute('aria-required', isStudent ? 'true' : 'false');
                    };

                    roleField.addEventListener('change', syncState);
                    syncState();
                }

                bindRoleAwareJenisKelamin('#cbt-user-role', '#cbt-user-jenis-kelamin');
                bindRoleAwareJenisKelamin('#cbt-edit-user-role', '#cbt-edit-user-jenis-kelamin');

                const supportsPartialListRefresh = !!(window.fetch && window.DOMParser);
                let userFilterTimer = 0;
                let userListRequestSeq = 0;

                function getUsersListPanel() {
                    return page ? page.querySelector('[data-cbt-users-panel="list"]') : null;
                }

                function buildUsersFilterUrl(form) {
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

                function setUsersListPanelLoading(panel, isLoading) {
                    if (!panel) {
                        return;
                    }

                    panel.classList.toggle('is-loading', isLoading);
                    panel.setAttribute('aria-busy', isLoading ? 'true' : 'false');
                }

                function captureUsersListFocus(panel) {
                    const activeElement = document.activeElement;
                    if (!panel || !activeElement || !panel.contains(activeElement) || !activeElement.id) {
                        return null;
                    }

                    const focusState = {
                        id: String(activeElement.id || '')
                    };
                    if (typeof activeElement.selectionStart === 'number') {
                        focusState.selectionStart = activeElement.selectionStart;
                        focusState.selectionEnd = typeof activeElement.selectionEnd === 'number'
                            ? activeElement.selectionEnd
                            : activeElement.selectionStart;
                    }

                    return focusState;
                }

                function restoreUsersListFocus(focusState) {
                    if (!focusState || !focusState.id) {
                        return;
                    }

                    const nextField = document.getElementById(focusState.id);
                    if (!nextField) {
                        return;
                    }

                    if (typeof nextField.focus === 'function') {
                        nextField.focus({ preventScroll: true });
                    }
                    if (
                        typeof focusState.selectionStart === 'number' &&
                        typeof nextField.setSelectionRange === 'function'
                    ) {
                        try {
                            nextField.setSelectionRange(focusState.selectionStart, focusState.selectionEnd);
                        } catch (error) {
                        }
                    }
                }

                function updateUsersListHistory(nextUrl) {
                    if (!window.history || typeof window.history.replaceState !== 'function') {
                        return;
                    }

                    window.history.replaceState({}, '', nextUrl.toString());
                }

                function navigateUsersList(nextUrl) {
                    window.location.assign(nextUrl.toString());
                }

                async function refreshUsersListPanel(nextUrl) {
                    const currentPanel = getUsersListPanel();
                    if (!currentPanel || !supportsPartialListRefresh) {
                        navigateUsersList(nextUrl);
                        return;
                    }

                    userListRequestSeq += 1;
                    const requestSeq = userListRequestSeq;
                    const focusState = captureUsersListFocus(currentPanel);
                    setUsersListPanelLoading(currentPanel, true);

                    try {
                        const response = await window.fetch(nextUrl.toString(), {
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (!response.ok) {
                            navigateUsersList(nextUrl);
                            return;
                        }

                        const html = await response.text();
                        if (requestSeq !== userListRequestSeq) {
                            return;
                        }

                        const parsed = new DOMParser().parseFromString(html, 'text/html');
                        const nextPanel = parsed.querySelector('[data-cbt-users-panel="list"]');
                        if (!nextPanel) {
                            navigateUsersList(nextUrl);
                            return;
                        }

                        currentPanel.innerHTML = nextPanel.innerHTML;
                        updateUsersListHistory(nextUrl);
                        bindUsersListPanel();
                        restoreUsersListFocus(focusState);
                    } catch (error) {
                        if (requestSeq === userListRequestSeq) {
                            navigateUsersList(nextUrl);
                        }
                    } finally {
                        if (requestSeq === userListRequestSeq) {
                            setUsersListPanelLoading(getUsersListPanel(), false);
                        }
                    }
                }

                function submitUserFilters(form) {
                    if (!form) {
                        return;
                    }

                    if (window.localStorage) {
                        window.localStorage.setItem(tabStorageKey, 'list');
                    }

                    if (supportsPartialListRefresh) {
                        refreshUsersListPanel(buildUsersFilterUrl(form));
                        return;
                    }

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                        return;
                    }

                    form.submit();
                }

                function bindUsersListSelection(panel) {
                    if (!panel) {
                        return;
                    }

                    const selectAll = panel.querySelector('#cbt-user-select-all');
                    const rowChecks = Array.from(panel.querySelectorAll('.cbt-user-row-check'));
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

                function bindUsersListPanel() {
                    const panel = getUsersListPanel();
                    if (!panel) {
                        return;
                    }

                    const userFilterForm = panel.querySelector('.cbt-users-filter-form');
                    const userFilterSearch = panel.querySelector('#cbt-users-filter-search');
                    const userFilterRole = panel.querySelector('#cbt-users-filter-role');
                    const userFilterKelas = panel.querySelector('#cbt-users-filter-kelas');
                    const userFilterRuang = panel.querySelector('#cbt-users-filter-ruang');
                    const userFilterAgama = panel.querySelector('#cbt-users-filter-agama');
                    const userFilterJenisKelamin = panel.querySelector('#cbt-users-filter-jenis-kelamin');
                    const userFilterPerPage = panel.querySelector('#cbt-users-filter-per-page');
                    const userFilterReset = panel.querySelector('.cbt-users-filter-reset');
                    const paginationLinks = Array.from(panel.querySelectorAll('.cbt-users-pagination-links a'));

                    if (userFilterForm && userFilterForm.dataset.cbtAsyncBound !== '1') {
                        userFilterForm.dataset.cbtAsyncBound = '1';
                        if (supportsPartialListRefresh) {
                            userFilterForm.addEventListener('submit', function (event) {
                                event.preventDefault();
                                window.clearTimeout(userFilterTimer);
                                submitUserFilters(userFilterForm);
                            });
                        }
                    }

                    [userFilterRole, userFilterKelas, userFilterRuang, userFilterAgama, userFilterJenisKelamin, userFilterPerPage].forEach((field) => {
                        if (!field || field.dataset.cbtAutoBound === '1') {
                            return;
                        }

                        field.dataset.cbtAutoBound = '1';
                        field.addEventListener('change', function () {
                            window.clearTimeout(userFilterTimer);
                            submitUserFilters(userFilterForm);
                        });
                    });

                    if (userFilterSearch && userFilterSearch.dataset.cbtAutoBound !== '1') {
                        userFilterSearch.dataset.cbtAutoBound = '1';
                        userFilterSearch.addEventListener('input', function () {
                            window.clearTimeout(userFilterTimer);
                            userFilterTimer = window.setTimeout(function () {
                                submitUserFilters(userFilterForm);
                            }, 280);
                        });
                        userFilterSearch.addEventListener('search', function () {
                            window.clearTimeout(userFilterTimer);
                            submitUserFilters(userFilterForm);
                        });
                        userFilterSearch.addEventListener('keydown', function (event) {
                            if (event.key !== 'Enter') {
                                return;
                            }
                            event.preventDefault();
                            window.clearTimeout(userFilterTimer);
                            submitUserFilters(userFilterForm);
                        });
                    }

                    if (supportsPartialListRefresh && userFilterReset && userFilterReset.dataset.cbtAsyncBound !== '1') {
                        userFilterReset.dataset.cbtAsyncBound = '1';
                        userFilterReset.addEventListener('click', function (event) {
                            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }

                            event.preventDefault();
                            window.clearTimeout(userFilterTimer);
                            refreshUsersListPanel(new URL(userFilterReset.getAttribute('href') || window.location.href, window.location.href));
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
                                window.clearTimeout(userFilterTimer);
                                refreshUsersListPanel(new URL(link.getAttribute('href') || window.location.href, window.location.href));
                            });
                        });
                    }

                    bindUsersListSelection(panel);
                }

                    bindUsersListPanel();
                })();
            </script>
