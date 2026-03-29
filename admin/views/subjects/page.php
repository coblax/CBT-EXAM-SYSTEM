        <style>
            .cbt-subject-page {
                max-width: 1120px;
            }
            .cbt-subject-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            }
            .cbt-subject-hero {
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
            .cbt-subject-hero-copy {
                max-width: 640px;
            }
            .cbt-subject-kicker {
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
                display: grid;
                gap: 10px;
                min-width: 260px;
            }
            .cbt-subject-pill {
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
            .cbt-subject-tabs {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-subject-tab {
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
            .cbt-subject-tab:hover,
            .cbt-subject-tab:focus {
                border-color: #2271b1;
                color: #0f4fa8;
                outline: none;
                box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
            }
            .cbt-subject-tab.is-active {
                border-color: #2271b1;
                background: #2271b1;
                color: #ffffff;
                box-shadow: 0 10px 24px rgba(34, 113, 177, 0.18);
            }
            .cbt-subject-panel {
                display: none;
                padding: 24px;
                border: 1px solid #dcdcde;
                border-radius: 20px;
                background: #ffffff;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
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
                margin: 0 0 6px;
                font-size: 18px;
                line-height: 1.2;
            }
            .cbt-subject-panel-header p {
                margin: 0;
                color: #646970;
                line-height: 1.55;
            }
            .cbt-subject-chip {
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
            .cbt-subject-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                margin-bottom: 16px;
            }
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
            .cbt-subject-panel input[type="search"],
            .cbt-subject-panel select,
            .cbt-subject-panel textarea {
                border: 1px solid #c9d7e6;
                border-radius: 16px;
                background: #f8fbff;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
                color: #0f172a;
            }
            .cbt-subject-panel input[type="text"],
            .cbt-subject-panel input[type="search"],
            .cbt-subject-panel select {
                min-height: 48px;
                padding: 0 15px;
            }
            .cbt-subject-panel select {
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
            .cbt-subject-panel select:disabled {
                cursor: not-allowed;
                background-color: #eef4fb;
            }
            .cbt-subject-panel input[type="file"] {
                min-height: 48px;
                padding: 10px 14px;
                border: 1px dashed #c9d7e6;
                border-radius: 16px;
                background: #f8fbff;
                width: min(100%, 720px);
                box-sizing: border-box;
            }
            .cbt-subject-panel .regular-text,
            .cbt-subject-panel .large-text,
            .cbt-subject-panel textarea {
                width: min(100%, 720px);
                max-width: none;
            }
            .cbt-subject-panel .description {
                margin-top: 10px;
                color: #64748b;
                font-size: 13px;
                line-height: 1.65;
            }
            .cbt-subject-panel .button {
                min-height: 40px;
                border-radius: 12px;
                padding: 0 14px;
            }
            .cbt-subject-panel .button-primary {
                box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
            }
            .cbt-subject-panel .submit {
                margin: 0;
                padding: 0;
            }
            .cbt-subject-import-progress {
                margin-bottom: 18px;
                padding: 16px;
                border: 1px solid #cdd8e6;
                border-radius: 16px;
                background: linear-gradient(180deg, #fcfdff 0%, #f6f9fc 100%);
            }
            .cbt-subject-import-progress strong {
                display: block;
                margin-bottom: 10px;
                color: #0f172a;
            }
            .cbt-subject-import-progress-track {
                width: 100%;
                height: 14px;
                border-radius: 999px;
                overflow: hidden;
                background: #f0f3f7;
                border: 1px solid #dbe2ea;
            }
            .cbt-subject-import-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #2271b1, #135e96);
                transition: width .25s ease;
            }
            .cbt-subject-import-progress-meta {
                margin-top: 10px;
                color: #475569;
                line-height: 1.55;
            }
            .cbt-subject-list-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                flex-wrap: wrap;
                margin-bottom: 12px;
            }
            .cbt-subject-filter-form {
                display: grid;
                grid-template-columns: minmax(260px, 1.4fr) minmax(150px, 0.8fr) auto;
                align-items: end;
                gap: 12px;
                margin: 0;
                padding: 16px 18px;
                border: 1px solid #dfe7ef;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
            }
            .cbt-subject-filter-field {
                display: grid;
                gap: 8px;
                min-width: 0;
            }
            .cbt-subject-filter-form label {
                font-weight: 700;
                color: #0f172a;
            }
            .cbt-subject-filter-form select {
                width: 100%;
                min-height: 46px;
                padding: 0 14px;
                border: 1px solid #cbd8e6;
                border-radius: 14px;
                background: #fff;
                box-shadow: none;
                transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
            }
            .cbt-subject-filter-form select {
                padding-right: 42px;
                appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='none' stroke='%235f6b7a' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.6' d='M1 1.25 6 6.25l5-5'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 14px center;
                background-size: 12px 8px;
            }
            .cbt-subject-filter-form select:focus {
                border-color: #2271b1;
                background-color: #fff;
                box-shadow: 0 0 0 4px rgba(34, 113, 177, 0.12);
                outline: none;
            }
            .cbt-subject-filter-actions {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                align-self: end;
            }
            .cbt-subject-filter-reset {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 46px;
                padding: 0 18px;
                border: 1px solid #c8d9ea;
                border-radius: 14px;
                background: linear-gradient(180deg, #ffffff 0%, #f3f8ff 100%);
                color: #123f67;
                text-decoration: none;
                font-size: 13px;
                font-weight: 700;
                line-height: 1;
                box-shadow: 0 10px 24px rgba(34, 59, 89, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.94);
                transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, color 0.2s ease, background-color 0.2s ease;
            }
            .cbt-subject-filter-reset:hover,
            .cbt-subject-filter-reset:focus {
                border-color: #2271b1;
                background: linear-gradient(180deg, #ffffff 0%, #ebf5ff 100%);
                color: #0f4c81;
                box-shadow: 0 14px 28px rgba(34, 113, 177, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.96);
                transform: translateY(-1px);
                outline: none;
            }
            .cbt-subject-table-wrap {
                overflow: hidden;
                border: 1px solid #dbe5ef;
                border-radius: 18px;
                background: #fff;
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
                margin-top: 10px;
            }
            .cbt-subject-panel .widefat {
                margin: 0;
                border: 0;
                box-shadow: none;
            }
            .cbt-subject-panel .widefat thead th {
                background: #f8fbff;
                color: #334155;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.02em;
            }
            .cbt-subject-panel .widefat td,
            .cbt-subject-panel .widefat th {
                padding-top: 12px;
                padding-bottom: 12px;
            }
            .cbt-subject-panel .widefat tbody tr:hover {
                background: #f8fbff;
            }
            .cbt-subject-row-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-subject-row-action {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 34px;
                padding: 0 12px;
                border: 1px solid #d9e2ec;
                border-radius: 12px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                text-decoration: none;
                font-size: 13px;
                font-weight: 600;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
                transition: transform 120ms ease, border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease, color 120ms ease;
            }
            .cbt-subject-row-action:hover,
            .cbt-subject-row-action:focus {
                border-color: #a8c7e6;
                background: #ffffff;
                box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
                transform: translateY(-1px);
                outline: none;
            }
            .cbt-subject-row-action--edit {
                background: linear-gradient(180deg, #fffdf7 0%, #fef7e6 100%);
                color: #b45309;
            }
            .cbt-subject-row-action--edit:hover,
            .cbt-subject-row-action--edit:focus {
                border-color: #f7d79a;
                color: #92400e;
            }
            .cbt-subject-row-action--delete {
                background: linear-gradient(180deg, #fff8f8 0%, #feecec 100%);
                color: #b91c1c;
            }
            .cbt-subject-row-action--delete:hover,
            .cbt-subject-row-action--delete:focus {
                border-color: #f1b5b5;
                color: #991b1b;
            }
            .cbt-admin-pagination-wrap {
                clear: both;
            }
            .cbt-admin-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }
            .cbt-admin-pagination .cbt-admin-total {
                font-size: 14px;
                line-height: 1.4;
                color: #1d2327;
                font-weight: 500;
            }
            .cbt-admin-pagination-links {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .cbt-admin-pagination-links .page-numbers {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 34px;
                height: 34px;
                padding: 0 12px;
                border: 1px solid #c3c4c7;
                border-radius: 6px;
                background: #fff;
                color: #1d2327;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
                box-sizing: border-box;
            }
            .cbt-admin-pagination-links .page-numbers:hover,
            .cbt-admin-pagination-links .page-numbers:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px rgba(34, 113, 177, 0.15);
                color: #0a4b78;
                outline: none;
            }
            .cbt-admin-pagination-links .page-numbers.current {
                border-color: #2271b1;
                background: #2271b1;
                color: #fff;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
            }
            .cbt-admin-pagination-links .page-numbers.prev,
            .cbt-admin-pagination-links .page-numbers.next {
                padding: 0 14px;
                font-weight: 600;
            }
            .cbt-admin-pagination-links .page-numbers.dots {
                border-color: transparent;
                background: transparent;
                color: #646970;
                min-width: auto;
                padding: 0 4px;
                box-shadow: none;
            }
            @media (max-width: 960px) {
                .cbt-subject-hero,
                .cbt-subject-panel-header,
                .cbt-subject-list-toolbar {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-subject-filter-form {
                    grid-template-columns: 1fr;
                }
                .cbt-subject-filter-actions {
                    justify-content: flex-start;
                }
                .cbt-subject-overview {
                    min-width: 0;
                }
            }
            @media (max-width: 782px) {
                .cbt-subject-page {
                    margin-right: 10px;
                }
                .cbt-subject-hero,
                .cbt-subject-panel {
                    padding: 20px;
                }
                .cbt-admin-pagination {
                    align-items: flex-start;
                }
                .cbt-admin-pagination-links .page-numbers {
                    min-width: 32px;
                    height: 32px;
                    padding: 0 10px;
                    font-size: 13px;
                }
                .cbt-subject-panel .form-table th {
                    width: auto;
                    padding-right: 0;
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
                            <button type="submit" class="button button-secondary" name="bulk_mode" value="selected" onclick="return confirm('Delete selected subjects?');">Delete Selected</button>
                            <button type="submit" class="button button-secondary" name="bulk_mode" value="all" onclick="return confirm('Delete semua subject pada hasil filter ini? Subject yang dipakai exam akan dilewati.');">Delete All</button>
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
                                <tr><td colspan="6"><?php echo esc_html($subject_filter_id > 0 ? 'No subjects found for this filter.' : 'No subjects found.'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><input type="checkbox" class="cbt-subject-row-check" name="subject_ids[]" value="<?php echo (int) $subject['id']; ?>" /></td>
                                        <td><?php echo (int) $subject['id']; ?></td>
                                        <td><?php echo esc_html((string) $subject['name']); ?></td>
                                        <td><?php echo esc_html((string) ($subject['code'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($subject['description'] ?? '')); ?></td>
                                        <td>
                                            <div class="cbt-subject-row-actions">
                                                <a class="cbt-subject-row-action cbt-subject-row-action--edit" href="<?php echo esc_url(add_query_arg(array_merge($subject_list_query_args, ['edit' => (int) $subject['id'], 'cbt_subject_paged' => $subject_current_page]), admin_url('admin.php'))); ?>">Edit</a>
                                                <a class="cbt-subject-row-action cbt-subject-row-action--delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge([
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
