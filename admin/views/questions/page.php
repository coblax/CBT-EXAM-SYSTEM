<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
        <div
            class="wrap cbt-questions-page"
            data-cbt-questions-default-tab="<?php echo esc_attr($default_question_tab); ?>"
            data-cbt-questions-force-tab="<?php echo $question_tab_is_forced ? '1' : '0'; ?>"
        >
            <div class="cbt-questions-shell">
                <section class="cbt-questions-hero">
                    <div class="cbt-questions-hero-copy">
                        <span class="cbt-questions-kicker">Questions</span>
                        <h1>CBT Questions</h1>
                        <p>Kelola bank soal CBT melalui tab terpisah agar proses tambah manual, import, dan peninjauan daftar soal terasa lebih fokus dan tidak padat dalam satu halaman panjang.</p>
                    </div>
                    <div class="cbt-questions-overview" aria-hidden="true">
                        <span class="cbt-questions-pill"><?php echo esc_html(sprintf('Total: %d soal', $total_questions)); ?></span>
                        <span class="cbt-questions-pill"><?php echo esc_html($question_scope_label); ?></span>
                        <span class="cbt-questions-pill"><?php echo esc_html(!empty($editing_question) ? 'Mode edit aktif' : (is_array($question_import_state) ? 'Import berjalan' : 'Input siap')); ?></span>
                    </div>
                </section>

                <?php if ($notice): ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                <?php endif; ?>
                <?php if ($lock_question_type): ?>
                    <div class="notice notice-info"><p>
                        Submenu aktif: <strong><?php echo esc_html((string) ($question_type_labels[$active_question_type] ?? $active_question_type)); ?></strong>.
                        Form dan daftar soal difilter ke jenis ini.
                    </p></div>
                <?php endif; ?>

                <style>
                    .cbt-questions-page {
                        max-width: 1180px;
                    }
                    .cbt-questions-shell {
                        display: grid;
                        gap: 18px;
                        margin-top: 18px;
                    }
                    .cbt-questions-hero {
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
                    .cbt-questions-hero-copy {
                        max-width: 700px;
                    }
                    .cbt-questions-kicker {
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
                    .cbt-questions-hero h1 {
                        margin: 12px 0 8px;
                        font-size: 30px;
                        line-height: 1.15;
                    }
                    .cbt-questions-hero p {
                        margin: 0;
                        color: #4b5563;
                        font-size: 14px;
                        line-height: 1.6;
                    }
                    .cbt-questions-overview {
                        display: grid;
                        gap: 10px;
                        min-width: 260px;
                    }
                    .cbt-questions-pill {
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
                    .cbt-questions-tabs {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        flex-wrap: wrap;
                    }
                    .cbt-questions-tab {
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
                    .cbt-questions-tab:hover,
                    .cbt-questions-tab:focus {
                        border-color: #2271b1;
                        color: #0f4fa8;
                        outline: none;
                        box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
                    }
                    .cbt-questions-tab.is-active {
                        border-color: #2271b1;
                        background: #2271b1;
                        color: #ffffff;
                        box-shadow: 0 10px 24px rgba(34, 113, 177, 0.18);
                    }
                    .cbt-questions-panel {
                        display: none;
                        padding: 24px;
                        border: 1px solid #dcdcde;
                        border-radius: 20px;
                        background: #ffffff;
                        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
                    }
                    .cbt-questions-panel.is-active {
                        display: block;
                    }
                    .cbt-questions-panel-header {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 16px;
                        margin-bottom: 18px;
                    }
                    .cbt-questions-panel-header h2 {
                        margin: 0 0 6px;
                        font-size: 18px;
                        line-height: 1.2;
                    }
                    .cbt-questions-panel-header p {
                        margin: 0;
                        color: #646970;
                        line-height: 1.55;
                    }
                    .cbt-questions-chip {
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
                    .cbt-questions-chip--bank {
                        background: #e8f1ff;
                        color: #0f4fa8;
                    }
                    .cbt-questions-chip--bank-backed {
                        background: #eafbf4;
                        color: #0f766e;
                    }
                    .cbt-questions-chip--legacy {
                        background: #fff4e6;
                        color: #a54800;
                    }
                    .cbt-question-source-summary,
                    .cbt-question-bank-target {
                        display: grid;
                        gap: 8px;
                        width: min(100%, 720px);
                        padding: 14px 16px;
                        border: 1px solid #d7e4f5;
                        border-radius: 16px;
                        background: #f8fbff;
                    }
                    .cbt-question-source-summary-head {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        flex-wrap: wrap;
                    }
                    .cbt-question-source-summary strong,
                    .cbt-question-bank-target strong {
                        color: #0f172a;
                        font-size: 14px;
                    }
                    .cbt-question-bank-target-label,
                    .cbt-question-source-path {
                        color: #64748b;
                        font-size: 12px;
                        font-weight: 600;
                        letter-spacing: 0.03em;
                        text-transform: uppercase;
                    }
                    .cbt-question-bank-target .description,
                    .cbt-question-source-summary .description {
                        margin: 0;
                    }
                    .cbt-question-type-lock {
                        display: grid;
                        gap: 6px;
                        padding: 12px 14px;
                        border: 1px solid #d7e4f5;
                        border-radius: 14px;
                        background: #f8fbff;
                        width: fit-content;
                        min-width: min(100%, 320px);
                    }
                    .cbt-question-type-lock strong {
                        color: #0f172a;
                        font-size: 14px;
                    }
                    .cbt-question-type-lock .description {
                        margin: 0;
                    }
                    .cbt-questions-source-meta {
                        display: grid;
                        gap: 6px;
                        margin-top: 6px;
                    }
                    .cbt-questions-source-title {
                        color: #64748b;
                        font-size: 12px;
                        line-height: 1.5;
                    }
                    .cbt-question-guard-card {
                        display: grid;
                        gap: 16px;
                        padding: 22px 24px;
                        border: 1px solid #bfdbfe;
                        border-radius: 20px;
                        background:
                            linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(239,246,255,0.96) 100%);
                        box-shadow: 0 14px 34px rgba(37, 99, 235, 0.08);
                    }
                    .cbt-question-guard-kicker {
                        display: inline-flex;
                        align-items: center;
                        width: fit-content;
                        min-height: 28px;
                        padding: 0 12px;
                        border-radius: 999px;
                        background: #dbeafe;
                        color: #1d4ed8;
                        font-size: 12px;
                        font-weight: 700;
                        letter-spacing: 0.05em;
                        text-transform: uppercase;
                    }
                    .cbt-question-guard-card h3 {
                        margin: 0;
                        color: #0f172a;
                        font-size: 24px;
                        line-height: 1.2;
                    }
                    .cbt-question-guard-card p {
                        margin: 0;
                        color: #475569;
                        font-size: 14px;
                        line-height: 1.7;
                    }
                    .cbt-question-guard-grid {
                        display: grid;
                        gap: 14px;
                        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    }
                    .cbt-question-guard-item {
                        display: grid;
                        gap: 6px;
                        padding: 14px 16px;
                        border: 1px solid #dbe7f3;
                        border-radius: 16px;
                        background: rgba(255, 255, 255, 0.9);
                    }
                    .cbt-question-guard-label {
                        color: #64748b;
                        font-size: 11px;
                        font-weight: 700;
                        letter-spacing: 0.06em;
                        text-transform: uppercase;
                    }
                    .cbt-question-guard-value {
                        color: #0f172a;
                        font-size: 14px;
                        font-weight: 600;
                        line-height: 1.5;
                    }
                    .cbt-question-guard-actions {
                        display: flex;
                        gap: 10px;
                        flex-wrap: wrap;
                    }
                    .cbt-question-guard-note {
                        padding: 14px 16px;
                        border-radius: 16px;
                        background: #eff6ff;
                        color: #1e3a5f;
                        font-size: 13px;
                        line-height: 1.6;
                    }
                    .cbt-tab-buttons {
                        display: flex;
                        gap: 10px;
                        margin: 14px 0;
                        flex-wrap: wrap;
                    }
                    .cbt-tab-buttons .button {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 42px;
                        padding: 0 16px;
                        border-radius: 12px;
                        border-color: #c9d5e6;
                        background: #fff;
                        color: #334155;
                        font-weight: 600;
                        box-shadow: none;
                        transition: all 0.16s ease;
                    }
                    .cbt-tab-buttons .button:hover,
                    .cbt-tab-buttons .button:focus {
                        border-color: #2271b1;
                        color: #0f4fa8;
                        box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
                    }
                    .cbt-tab-buttons .button.cbt-active {
                        background: #2271b1;
                        color: #fff;
                        border-color: #2271b1;
                        box-shadow: 0 10px 24px rgba(34, 113, 177, 0.18);
                    }
                    .cbt-questions-panel .form-table {
                        margin: 0;
                        border-collapse: separate;
                        border-spacing: 0 18px;
                    }
                    .cbt-questions-panel .form-table th {
                        width: 190px;
                        padding: 10px 18px 0 0;
                        vertical-align: top;
                        color: #0f172a;
                        font-size: 14px;
                        font-weight: 700;
                    }
                    .cbt-questions-panel .form-table td {
                        padding: 0;
                        vertical-align: top;
                    }
                    .cbt-questions-panel .form-table th label,
                    .cbt-questions-panel .form-table td > label {
                        color: inherit;
                        font-weight: inherit;
                    }
                    .cbt-questions-panel input[type="text"],
                    .cbt-questions-panel input[type="number"],
                    .cbt-questions-panel input[type="email"],
                    .cbt-questions-panel input[type="search"],
                    .cbt-questions-panel select,
                    .cbt-questions-panel textarea {
                        border: 1px solid #c9d7e6;
                        border-radius: 16px;
                        background: #f8fbff;
                        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
                        color: #0f172a;
                    }
                    .cbt-questions-panel input[type="text"],
                    .cbt-questions-panel input[type="email"],
                    .cbt-questions-panel input[type="search"],
                    .cbt-questions-panel select {
                        min-height: 48px;
                        padding: 0 15px;
                    }
                    .cbt-questions-panel select {
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
                    .cbt-questions-panel select:disabled {
                        cursor: not-allowed;
                        background-color: #eef4fb;
                    }
                    .cbt-questions-panel input[type="number"] {
                        min-height: 48px;
                        padding: 0 12px;
                        width: 130px;
                    }
                    .cbt-questions-panel input[type="file"] {
                        min-height: 48px;
                        padding: 10px 14px;
                        border: 1px dashed #c9d7e6;
                        border-radius: 16px;
                        background: #f8fbff;
                        width: min(100%, 720px);
                        box-sizing: border-box;
                    }
                    .cbt-questions-panel textarea {
                        width: min(100%, 960px);
                        min-height: 120px;
                        padding: 14px 16px;
                    }
                    .cbt-questions-panel .regular-text,
                    .cbt-questions-panel .large-text {
                        width: min(100%, 720px);
                        max-width: none;
                    }
                    .cbt-questions-panel .description,
                    .cbt-questions-panel .cbt-inline-help {
                        margin-top: 10px;
                        color: #64748b;
                        font-size: 13px;
                        line-height: 1.65;
                    }
                    .cbt-questions-panel .wp-editor-wrap {
                        max-width: 980px;
                    }
                    .cbt-questions-panel .wp-editor-container {
                        border: 1px solid #cfdbe8;
                        border-radius: 18px;
                        overflow: hidden;
                        background: #fff;
                    }
                    .cbt-questions-panel .quicktags-toolbar,
                    .cbt-questions-panel .mce-toolbar-grp {
                        border-color: #d8e3ee;
                        background: #f8fbff;
                    }
                    .cbt-questions-panel .mce-statusbar {
                        border-top-color: #d8e3ee;
                    }
                    .cbt-questions-panel .wp-editor-area {
                        background: #fff;
                    }
                    .cbt-questions-panel .wp-editor-tools {
                        padding: 0 0 8px;
                    }
                    .cbt-questions-panel .insert-media {
                        border-radius: 12px;
                    }
                    .cbt-qtype-panel {
                        display: none;
                    }
                    .cbt-qtype-panel.cbt-active {
                        display: table-row;
                    }
                    .cbt-inline-help {
                        margin: 6px 0 0;
                        color: #50575e;
                    }
                    .cbt-option-list {
                        display: grid;
                        gap: 8px;
                        max-width: 900px;
                        padding: 16px;
                        border: 1px solid #dfe7ef;
                        border-radius: 18px;
                        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                    }
                    .cbt-option-row {
                        display: flex;
                        align-items: flex-start;
                        gap: 8px;
                        padding: 12px 14px;
                        border: 1px solid #e5ebf2;
                        border-radius: 14px;
                        background: #fff;
                    }
                    .cbt-option-row label {
                        width: 84px;
                        padding-top: 10px;
                        color: #0f172a;
                        font-weight: 700;
                    }
                    .cbt-option-row label.cbt-inline-check {
                        width: auto;
                        padding-top: 12px;
                        font-weight: 600;
                        color: #475569;
                    }
                    .cbt-option-row .wp-editor-wrap {
                        flex: 1;
                        min-width: 220px;
                    }
                    .cbt-option-row .wp-editor-wrap .wp-editor-area {
                        width: 100%;
                    }
                    .cbt-questions-actions,
                    .cbt-questions-form-actions,
                    .cbt-questions-list-actions {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        flex-wrap: wrap;
                    }
                    .cbt-questions-form-actions {
                        margin-top: 18px;
                    }
                    .cbt-questions-progress {
                        margin-bottom: 18px;
                        padding: 16px;
                        border: 1px solid #cdd8e6;
                        border-radius: 16px;
                        background: linear-gradient(180deg, #fcfdff 0%, #f6f9fc 100%);
                    }
                    .cbt-questions-progress strong {
                        display: block;
                        margin-bottom: 10px;
                        color: #0f172a;
                    }
                    .cbt-questions-progress-track {
                        width: 100%;
                        height: 14px;
                        border-radius: 999px;
                        overflow: hidden;
                        background: #f0f3f7;
                        border: 1px solid #dbe2ea;
                    }
                    .cbt-questions-progress-fill {
                        height: 100%;
                        background: linear-gradient(90deg, #2271b1, #135e96);
                        transition: width .25s ease;
                    }
                    .cbt-questions-progress-meta {
                        margin-top: 10px;
                        color: #475569;
                        line-height: 1.55;
                    }
                    .cbt-questions-list-toolbar {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 10px;
                        flex-wrap: wrap;
                        margin-bottom: 12px;
                    }
                    .cbt-questions-filter-form {
                        margin: 0;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        flex-wrap: wrap;
                        padding: 16px 18px;
                        border: 1px solid #dfe7ef;
                        border-radius: 18px;
                        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    }
                    .cbt-questions-filter-form label {
                        font-weight: 700;
                        color: #0f172a;
                    }
                    .cbt-questions-filter-form select {
                        max-width: none;
                    }
                    .cbt-questions-filter-summary {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                        margin: 0 0 14px;
                    }
                    .cbt-question-lineage-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                        gap: 12px;
                        margin: 0 0 16px;
                    }
                    .cbt-question-lineage-card {
                        padding: 16px 18px;
                        border: 1px solid #dbe5ef;
                        border-radius: 16px;
                        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.04);
                    }
                    .cbt-question-lineage-card-head {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 10px;
                        margin-bottom: 10px;
                    }
                    .cbt-question-lineage-card-count {
                        color: #475569;
                        font-size: 12px;
                        font-weight: 700;
                        letter-spacing: 0.03em;
                        text-transform: uppercase;
                    }
                    .cbt-question-lineage-card p {
                        margin: 0;
                        color: #475569;
                        font-size: 13px;
                        line-height: 1.6;
                    }
                    .cbt-questions-list-actions {
                        margin-bottom: 12px;
                    }
                    .cbt-questions-panel[data-cbt-questions-panel="list"].cbt-is-loading [data-cbt-questions-list-shell] {
                        opacity: 0.55;
                        pointer-events: none;
                        transition: opacity 0.16s ease;
                    }
                    .cbt-questions-panel .button {
                        min-height: 40px;
                        border-radius: 12px;
                        padding: 0 14px;
                    }
                    .cbt-questions-panel .button-primary {
                        box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
                    }
                    .cbt-questions-table-wrap {
                        overflow: hidden;
                        border: 1px solid #dbe5ef;
                        border-radius: 18px;
                        background: #fff;
                        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
                    }
                    .cbt-questions-panel .widefat {
                        margin: 0;
                        border: 0;
                        box-shadow: none;
                    }
                    .cbt-questions-panel .widefat thead th {
                        background: #f8fbff;
                        color: #334155;
                        font-size: 12px;
                        font-weight: 700;
                        letter-spacing: 0.02em;
                    }
                    .cbt-questions-panel .widefat td,
                    .cbt-questions-panel .widefat th {
                        padding-top: 12px;
                        padding-bottom: 12px;
                    }
                    .cbt-questions-panel .widefat tbody tr:hover {
                        background: #f8fbff;
                    }
                    .cbt-question-inline-preview-row td {
                        padding: 0 !important;
                        background: #f8fbff;
                    }
                    .cbt-question-reference-row td {
                        padding: 0 !important;
                        background: #f8fbff;
                    }
                    .cbt-question-inline-preview {
                        padding: 20px 22px 22px;
                        border-top: 1px solid #dbe5ef;
                        background:
                            radial-gradient(circle at top right, rgba(34, 113, 177, 0.08), transparent 28%),
                            linear-gradient(180deg, rgba(255,255,255,0.99) 0%, rgba(248,251,255,1) 100%);
                    }
                    .cbt-question-inline-preview-head {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 14px;
                        margin-bottom: 16px;
                    }
                    .cbt-question-inline-preview-kicker {
                        display: inline-flex;
                        align-items: center;
                        min-height: 26px;
                        padding: 0 10px;
                        border-radius: 999px;
                        background: #e8f1ff;
                        color: #0f4fa8;
                        font-size: 11px;
                        font-weight: 800;
                        letter-spacing: 0.05em;
                        text-transform: uppercase;
                    }
                    .cbt-question-inline-preview-title {
                        margin: 10px 0 6px;
                        color: #0f172a;
                        font-size: 18px;
                        line-height: 1.35;
                    }
                    .cbt-question-inline-preview-chips {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                    }
                    .cbt-question-inline-preview-pill {
                        display: inline-flex;
                        align-items: center;
                        min-height: 28px;
                        padding: 0 10px;
                        border-radius: 999px;
                        border: 1px solid #d8e3ee;
                        background: #fff;
                        color: #334155;
                        font-size: 11px;
                        font-weight: 700;
                        letter-spacing: 0.02em;
                    }
                    .cbt-question-inline-preview-pill--type {
                        background: #f8fbff;
                        color: #0f4fa8;
                        border-color: #cfe0f4;
                    }
                    .cbt-question-inline-preview-pill--points {
                        background: #fff8ea;
                        color: #9a5a00;
                        border-color: #f0d49a;
                    }
                    .cbt-question-inline-preview-meta {
                        color: #64748b;
                        font-size: 13px;
                        line-height: 1.55;
                    }
                    .cbt-question-inline-preview-question {
                        padding: 16px 18px;
                        border: 1px solid #dbe5ef;
                        border-radius: 14px;
                        background: #fff;
                        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
                        color: #1e293b;
                        line-height: 1.72;
                    }
                    .cbt-question-inline-preview-body {
                        color: #1e293b;
                        line-height: 1.7;
                        display: grid;
                        gap: 14px;
                    }
                    .cbt-question-inline-preview-body ol,
                    .cbt-question-inline-preview-body ul {
                        margin: 8px 0 0 18px;
                    }
                    .cbt-question-inline-preview-section {
                        padding: 16px 18px;
                        border: 1px solid #dbe5ef;
                        border-radius: 14px;
                        background: rgba(255,255,255,0.94);
                    }
                    .cbt-question-inline-preview-section strong {
                        display: block;
                        margin-bottom: 10px;
                        color: #0f172a;
                        font-size: 13px;
                        letter-spacing: 0.01em;
                    }
                    .cbt-question-inline-preview-options {
                        display: grid;
                        gap: 10px;
                    }
                    .cbt-question-inline-preview-option {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 12px;
                        padding: 12px 14px;
                        border: 1px solid #dbe5ef;
                        border-radius: 12px;
                        background: #fff;
                    }
                    .cbt-question-inline-preview-option.is-correct {
                        border-color: #9adfc5;
                        background: #ecfbf4;
                    }
                    .cbt-question-inline-preview-option-main {
                        display: flex;
                        align-items: flex-start;
                        gap: 10px;
                        flex: 1;
                        min-width: 0;
                    }
                    .cbt-question-inline-preview-option-key {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-width: 30px;
                        height: 30px;
                        border-radius: 999px;
                        background: #eef4ff;
                        color: #1e3a6f;
                        font-size: 12px;
                        font-weight: 800;
                        flex-shrink: 0;
                    }
                    .cbt-question-inline-preview-option-text {
                        min-width: 0;
                        color: #1f2937;
                        line-height: 1.6;
                    }
                    .cbt-question-inline-preview-badges {
                        display: flex;
                        flex-wrap: wrap;
                        justify-content: flex-end;
                        gap: 6px;
                    }
                    .cbt-question-inline-preview-badge {
                        display: inline-flex;
                        align-items: center;
                        min-height: 24px;
                        padding: 0 8px;
                        border-radius: 999px;
                        border: 1px solid transparent;
                        font-size: 10px;
                        font-weight: 800;
                        letter-spacing: 0.02em;
                        text-transform: uppercase;
                        white-space: nowrap;
                    }
                    .cbt-question-inline-preview-badge--correct {
                        background: #eafbf4;
                        color: #0f7a56;
                        border-color: #9adfc5;
                    }
                    .cbt-question-inline-preview-chip-list {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                    }
                    .cbt-question-inline-preview-chip {
                        display: inline-flex;
                        align-items: center;
                        padding: 6px 10px;
                        border-radius: 10px;
                        background: #eef4ff;
                        color: #1e3a6f;
                        border: 1px solid #d0def2;
                        font-size: 12px;
                        line-height: 1.3;
                    }
                    .cbt-question-inline-preview-text {
                        border: 1px solid #d6deea;
                        border-radius: 12px;
                        background: #f8fafc;
                        padding: 12px 14px;
                        font-size: 14px;
                        line-height: 1.65;
                        color: #1f2937;
                    }
                    .cbt-question-inline-preview-matrix {
                        display: grid;
                        gap: 10px;
                    }
                    .cbt-question-inline-preview-matrix-row {
                        display: grid;
                        gap: 8px;
                        padding: 12px 14px;
                        border: 1px solid #dbe5ef;
                        border-radius: 12px;
                        background: #fff;
                    }
                    .cbt-question-inline-preview-matrix-answer {
                        color: #475569;
                        font-size: 13px;
                    }
                    .cbt-question-usage-cell {
                        display: grid;
                        gap: 8px;
                        min-width: 150px;
                    }
                    .cbt-question-usage-summary {
                        color: #334155;
                        font-size: 12px;
                        font-weight: 700;
                        line-height: 1.5;
                    }
                    .cbt-question-reference-toggle {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 30px;
                        padding: 0 10px;
                        border: 1px solid #d9e2ec;
                        border-radius: 10px;
                        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                        color: #0f4fa8;
                        text-decoration: none;
                        font-size: 12px;
                        font-weight: 700;
                        width: fit-content;
                    }
                    .cbt-question-reference-toggle:hover,
                    .cbt-question-reference-toggle:focus {
                        border-color: #a8c7e6;
                        background: #ffffff;
                        box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
                        outline: none;
                    }
                    .cbt-question-reference-panel {
                        padding: 18px 20px 22px;
                        border-top: 1px solid #dbe5ef;
                        background:
                            radial-gradient(circle at top left, rgba(34, 113, 177, 0.06), transparent 24%),
                            linear-gradient(180deg, rgba(255,255,255,0.99) 0%, rgba(248,251,255,1) 100%);
                    }
                    .cbt-question-reference-panel-head {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 12px;
                        margin-bottom: 14px;
                    }
                    .cbt-question-reference-panel-head strong {
                        display: block;
                        margin-bottom: 4px;
                        color: #0f172a;
                        font-size: 16px;
                    }
                    .cbt-question-reference-panel-meta {
                        color: #64748b;
                        font-size: 13px;
                        line-height: 1.6;
                    }
                    .cbt-question-reference-grid {
                        display: grid;
                        gap: 12px;
                    }
                    .cbt-question-reference-card {
                        display: grid;
                        gap: 10px;
                        padding: 16px 18px;
                        border: 1px solid #dbe5ef;
                        border-radius: 14px;
                        background: #fff;
                        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
                    }
                    .cbt-question-reference-card-head {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 12px;
                    }
                    .cbt-question-reference-card-head strong {
                        display: block;
                        margin-bottom: 4px;
                        color: #0f172a;
                        font-size: 15px;
                        line-height: 1.4;
                    }
                    .cbt-question-reference-card-meta {
                        color: #64748b;
                        font-size: 12px;
                        line-height: 1.55;
                    }
                    .cbt-question-reference-status {
                        display: inline-flex;
                        align-items: center;
                        min-height: 26px;
                        padding: 0 10px;
                        border-radius: 999px;
                        border: 1px solid transparent;
                        font-size: 11px;
                        font-weight: 800;
                        letter-spacing: 0.02em;
                        text-transform: uppercase;
                        white-space: nowrap;
                    }
                    .cbt-question-reference-status--published {
                        background: #eafbf4;
                        color: #0f7a56;
                        border-color: #9adfc5;
                    }
                    .cbt-question-reference-status--draft {
                        background: #eef4ff;
                        color: #1e3a6f;
                        border-color: #d0def2;
                    }
                    .cbt-question-reference-status--closed {
                        background: #fff4e6;
                        color: #a54800;
                        border-color: #f1c27b;
                    }
                    .cbt-question-reference-stats {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                    }
                    .cbt-question-reference-stat {
                        display: inline-flex;
                        align-items: center;
                        min-height: 30px;
                        padding: 0 10px;
                        border-radius: 10px;
                        background: #f8fbff;
                        border: 1px solid #dbe5ef;
                        color: #334155;
                        font-size: 12px;
                        font-weight: 700;
                    }
                    .cbt-question-reference-actions {
                        display: flex;
                        gap: 8px;
                        flex-wrap: wrap;
                    }
                    .cbt-question-reference-empty {
                        padding: 16px 18px;
                        border: 1px dashed #cbd5e1;
                        border-radius: 14px;
                        background: #fff;
                        color: #64748b;
                        font-size: 13px;
                        line-height: 1.6;
                    }
                    .cbt-questions-row-actions {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        flex-wrap: wrap;
                    }
                    .cbt-questions-row-action {
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
                    .cbt-questions-row-action:hover,
                    .cbt-questions-row-action:focus {
                        border-color: #a8c7e6;
                        background: #ffffff;
                        box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
                        transform: translateY(-1px);
                        outline: none;
                    }
                    .cbt-questions-row-action--view {
                        background: linear-gradient(180deg, #f8fbff 0%, #edf5ff 100%);
                        color: #0f4fa8;
                    }
                    .cbt-questions-row-action--view:hover,
                    .cbt-questions-row-action--view:focus {
                        border-color: #a8c7e6;
                        color: #0b3d91;
                    }
                    .cbt-questions-row-action--edit {
                        background: linear-gradient(180deg, #fffdf7 0%, #fef7e6 100%);
                        color: #b45309;
                    }
                    .cbt-questions-row-action--edit:hover,
                    .cbt-questions-row-action--edit:focus {
                        border-color: #f7d79a;
                        color: #92400e;
                    }
                    .cbt-questions-row-action--delete {
                        background: linear-gradient(180deg, #fff8f8 0%, #feecec 100%);
                        color: #b91c1c;
                    }
                    .cbt-questions-row-action--delete:hover,
                    .cbt-questions-row-action--delete:focus {
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
                        .cbt-questions-hero,
                        .cbt-questions-panel-header,
                        .cbt-questions-list-toolbar {
                            flex-direction: column;
                            align-items: stretch;
                        }
                        .cbt-questions-overview {
                            min-width: 0;
                        }
                    }
                    @media (max-width: 782px) {
                        .cbt-questions-page {
                            margin-right: 10px;
                        }
                        .cbt-questions-hero,
                        .cbt-questions-panel {
                            padding: 20px;
                        }
                        .cbt-option-row {
                            flex-direction: column;
                            align-items: stretch;
                        }
                        .cbt-option-row label {
                            width: auto;
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
                    }
                </style>

                <div class="cbt-questions-tabs" role="tablist" aria-label="Navigasi CBT Questions">
                    <button type="button" class="cbt-questions-tab" data-cbt-questions-tab="form" role="tab" aria-selected="false">Form Question</button>
                    <button type="button" class="cbt-questions-tab" data-cbt-questions-tab="import" role="tab" aria-selected="false">Import Questions</button>
                    <button type="button" class="cbt-questions-tab" data-cbt-questions-tab="list" role="tab" aria-selected="false">Question List</button>
                </div>

                <section class="cbt-questions-panel" data-cbt-questions-panel="form" role="tabpanel">
                    <div class="cbt-questions-panel-header">
                        <div>
                            <h2><?php echo $editing_question ? 'Edit Question' : 'Add Question'; ?></h2>
                            <p><?php echo $editing_question ? (!empty($editing_question_is_edit_guarded) ? 'Row turunan exam dibaca sebagai referensi saja di sini. Ubah sumbernya di Bank Soal agar sinkronisasi tetap rapi.' : 'Perbarui soal dan opsi jawaban dari tab form tanpa harus turun ke daftar soal.') : 'Tambahkan soal baru langsung ke Bank Soal mapel yang dipilih, lengkap dengan tipe dan editor konten.'; ?></p>
                        </div>
                        <?php if ($editing_question): ?>
                            <a href="<?php echo esc_url($question_clear_edit_url); ?>" class="button button-secondary" data-cbt-questions-tab-link="list">Batal Edit</a>
                        <?php else: ?>
                            <span class="cbt-questions-chip">Manual</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($editing_question_is_edit_guarded)): ?>
                        <div class="cbt-question-guard-card">
                            <span class="cbt-question-guard-kicker"><?php echo esc_html($editing_question_guard_title !== '' ? $editing_question_guard_title : 'Bank-backed Locked'); ?></span>
                            <div>
                                <h3>Ubah sumbernya di Bank Soal</h3>
                                <p><?php echo esc_html($editing_question_guard_message !== '' ? $editing_question_guard_message : 'Row ini adalah salinan operasional di exam siswa. Untuk menjaga sinkronisasi satu arah, perubahan dilakukan dari soal sumber di Bank Soal.'); ?></p>
                            </div>
                            <div class="cbt-question-guard-grid">
                                <div class="cbt-question-guard-item">
                                    <span class="cbt-question-guard-label">Row Turunan</span>
                                    <div class="cbt-question-guard-value"><?php echo esc_html('#' . (int) ($editing_question['id'] ?? 0) . ' · ' . ($editing_question_exam_title !== '' ? $editing_question_exam_title : 'Exam siswa')); ?></div>
                                </div>
                                <div class="cbt-question-guard-item">
                                    <span class="cbt-question-guard-label">Sumber Bank</span>
                                    <div class="cbt-question-guard-value"><?php echo esc_html($editing_question_source_exam_title !== '' ? $editing_question_source_exam_title : 'Bank Soal'); ?></div>
                                </div>
                                <div class="cbt-question-guard-item">
                                    <span class="cbt-question-guard-label">ID Soal Sumber</span>
                                    <div class="cbt-question-guard-value"><?php echo esc_html($editing_question_source_question_id > 0 ? ('#' . (string) $editing_question_source_question_id) : '-'); ?></div>
                                </div>
                            </div>
                            <div class="cbt-question-guard-actions">
                                <?php if ($editing_question_source_edit_url !== ''): ?>
                                    <a href="<?php echo esc_url($editing_question_source_edit_url); ?>" class="button button-primary" data-cbt-questions-tab-link="form">Buka Sumber Bank</a>
                                <?php endif; ?>
                                <?php if ($editing_question_source_view_url !== ''): ?>
                                    <a href="<?php echo esc_url($editing_question_source_view_url); ?>" class="button button-secondary" data-cbt-questions-tab-link="list">Preview Sumber</a>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($question_clear_edit_url); ?>" class="button button-secondary" data-cbt-questions-tab-link="list">Kembali ke Daftar</a>
                            </div>
                            <div class="cbt-question-guard-note">
                                <strong>Kenapa dikunci?</strong>
                                Soal <em>bank-backed</em> adalah salinan operasional yang dipakai exam siswa. Jika row ini diedit langsung, arah sinkronisasi akan membingungkan. Karena itu, perubahan harus masuk dari <strong>Bank Soal</strong> sebagai sumber kebenaran.
                            </div>
                            <div class="cbt-question-inline-preview-question"><?php echo wp_kses_post((string) ($editing_question['question_text'] ?? '')); ?></div>
                        </div>
                    <?php else: ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-question-manual-form" data-cbt-questions-tab-submit="form">
                    <?php wp_nonce_field('cbt_save_question'); ?>
                    <input type="hidden" name="action" value="cbt_save_question" />
                    <input type="hidden" name="return_page" value="<?php echo esc_attr($current_page_slug); ?>" />
                    <input type="hidden" name="id" value="<?php echo esc_attr($editing_question['id'] ?? 0); ?>" />
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr(($editing_question && !$editing_question_is_bank_exam) ? (int) ($editing_question['exam_id'] ?? 0) : 0); ?>" />
                    <input type="hidden" id="cbt-question-type-hidden" name="question_type" value="<?php echo esc_attr($editing_type); ?>" />
                    <input type="hidden" id="cbt-correct-text-hidden" name="correct_text" value="<?php echo esc_attr($editing_type === 'short_answer' ? $editing_short_answer_payload : ($editing_type === 'true_false' ? $tf_correct : ($editing_type === 'true_false_matrix' ? $editing_tf_matrix_payload : ''))); ?>" />
                    <textarea id="cbt-options-hidden" name="options" style="display:none;"></textarea>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cbt-subject-id">Subject</label></th>
                            <td>
                                <?php
                                $initial_subject_label = '';
                                foreach ($subjects as $subject) {
                                    if ((int) ($subject['id'] ?? 0) !== (int) $initial_subject_id) {
                                        continue;
                                    }
                                    $initial_subject_label = (string) ($subject['name'] ?? '');
                                    if (!empty($subject['code'])) {
                                        $initial_subject_label .= ' (' . (string) $subject['code'] . ')';
                                    }
                                    break;
                                }
                                $initial_bank_target_label = (string) ($subject_bank_exam_labels[$initial_subject_id] ?? 'Bank Soal - Subject terpilih');
                                ?>
                                <?php if ($editing_question): ?>
                                    <input type="hidden" name="subject_id" value="<?php echo esc_attr((string) $initial_subject_id); ?>" />
                                    <div class="cbt-question-source-summary">
                                        <div class="cbt-question-source-summary-head">
                                            <strong><?php echo esc_html($initial_subject_label !== '' ? $initial_subject_label : 'Subject tidak ditemukan'); ?></strong>
                                            <span class="cbt-questions-chip <?php echo $editing_question_is_bank_exam ? 'cbt-questions-chip--bank' : (!empty($editing_question_is_bank_backed) ? 'cbt-questions-chip--bank-backed' : 'cbt-questions-chip--legacy'); ?>">
                                                <?php echo esc_html($editing_question_source_label); ?>
                                            </span>
                                        </div>
                                        <p class="description"><?php echo esc_html($editing_question_source_description); ?></p>
                                        <?php if ($editing_question_exam_title !== ''): ?>
                                            <span class="cbt-question-source-path">Tersimpan di: <?php echo esc_html($editing_question_exam_title); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($editing_question_is_bank_backed) && $editing_question_source_exam_title !== ''): ?>
                                            <span class="cbt-question-source-path">Sumber bank: <?php echo esc_html($editing_question_source_exam_title); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <select required id="cbt-subject-id" name="subject_id">
                                        <option value="">Select subject</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo (int) $subject['id']; ?>" <?php selected($initial_subject_id, (int) $subject['id']); ?>>
                                                <?php echo esc_html((string) $subject['name'] . (!empty($subject['code']) ? ' (' . $subject['code'] . ')' : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Input manual difokuskan ke Subject + Jenis Soal. Soal baru wajib masuk ke Bank Soal mapel terpilih.</p>
                                    <div class="cbt-question-bank-target" id="cbt-question-bank-target" data-bank-labels="<?php echo esc_attr(wp_json_encode($subject_bank_exam_labels)); ?>">
                                        <span class="cbt-question-bank-target-label">Target Bank Soal</span>
                                        <strong id="cbt-question-bank-target-name"><?php echo esc_html($initial_bank_target_label); ?></strong>
                                        <p class="description">Exam tujuan ditentukan otomatis saat soal baru disimpan.</p>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Jenis Soal</th>
                            <td>
                                <?php if ($lock_question_type || $editing_question): ?>
                                    <div class="cbt-question-type-lock">
                                        <strong><?php echo esc_html((string) ($question_type_labels[$editing_type] ?? $editing_type)); ?></strong>
                                        <?php if ($editing_question): ?>
                                            <p class="description">Jenis soal dikunci saat edit agar struktur opsi, detail tipe, dan sinkronisasi data tetap aman.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="cbt-tab-buttons" id="cbt-question-type-tabs">
                                        <button type="button" class="button<?php echo $editing_type === 'multiple_choice' ? ' cbt-active' : ''; ?>" data-qtype="multiple_choice">Multiple Choice</button>
                                        <button type="button" class="button<?php echo $editing_type === 'multiple_answer' ? ' cbt-active' : ''; ?>" data-qtype="multiple_answer">Multiple Answer</button>
                                        <button type="button" class="button<?php echo $editing_type === 'true_false' ? ' cbt-active' : ''; ?>" data-qtype="true_false">True/False</button>
                                        <button type="button" class="button<?php echo $editing_type === 'true_false_matrix' ? ' cbt-active' : ''; ?>" data-qtype="true_false_matrix">TF Matrix</button>
                                        <button type="button" class="button<?php echo $editing_type === 'short_answer' ? ' cbt-active' : ''; ?>" data-qtype="short_answer">Short Answer</button>
                                        <button type="button" class="button<?php echo $editing_type === 'essay' ? ' cbt-active' : ''; ?>" data-qtype="essay">Essay</button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt_question_text_editor">Question</label></th>
                            <td>
                                <?php
                                wp_editor(
                                    (string) ($editing_question['question_text'] ?? ''),
                                    'cbt_question_text_editor',
                                    [
                                        'textarea_name' => 'question_text',
                                        'textarea_rows' => 8,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'quicktags' => true,
                                    ]
                                );
                                ?>
                                <p class="description">Bisa teks, tabel, dan gambar (upload/paste) langsung di editor soal.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-points">Points</label></th>
                            <td><input type="number" step="0.01" min="0" id="cbt-points" name="points" value="<?php echo esc_attr($editing_question['points'] ?? '1.00'); ?>" /></td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'multiple_choice' ? ' cbt-active' : ''; ?>" data-qtype="multiple_choice">
                            <th>Multiple Choice</th>
                            <td>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="cbt-option-row">
                                            <label for="cbt_mc_option_<?php echo (int) $i; ?>">Pilihan <?php echo (int) $i; ?></label>
                                            <?php
                                            $mc_editor_id = 'cbt_mc_option_' . (int) $i;
                                            wp_editor(
                                                (string) ($mc_option_values[$i] ?? ''),
                                                $mc_editor_id,
                                                [
                                                    'textarea_name' => $mc_editor_id,
                                                    'textarea_rows' => 3,
                                                    'media_buttons' => true,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                ]
                                            );
                                            ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi minimal 2 pilihan, maksimal 5 pilihan. Tiap pilihan bisa teks atau gambar (paste/upload).</p>
                                <label for="cbt-correct-mc-index">Jawaban Benar</label>
                                <select id="cbt-correct-mc-index">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $mc_correct_index, $i); ?>>Pilihan <?php echo (int) $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'multiple_answer' ? ' cbt-active' : ''; ?>" data-qtype="multiple_answer">
                            <th>Multiple Answer</th>
                            <td>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <div class="cbt-option-row">
                                            <label for="cbt_ma_option_<?php echo (int) $i; ?>">Pilihan <?php echo (int) $i; ?></label>
                                            <?php
                                            $ma_editor_id = 'cbt_ma_option_' . (int) $i;
                                            wp_editor(
                                                (string) ($ma_option_values[$i] ?? ''),
                                                $ma_editor_id,
                                                [
                                                    'textarea_name' => $ma_editor_id,
                                                    'textarea_rows' => 3,
                                                    'media_buttons' => true,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                ]
                                            );
                                            ?>
                                            <label for="cbt-ma-correct-<?php echo (int) $i; ?>" class="cbt-inline-check">
                                                <input type="checkbox" id="cbt-ma-correct-<?php echo (int) $i; ?>" <?php checked((bool) ($ma_option_correct[$i] ?? false)); ?> />
                                                Benar
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi minimal 2 pilihan, maksimal 12 pilihan. Centang semua jawaban yang benar. Tiap pilihan bisa teks atau gambar.</p>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'true_false' ? ' cbt-active' : ''; ?>" data-qtype="true_false">
                            <th>True/False</th>
                            <td>
                                <select id="cbt-correct-tf">
                                    <option value="true" <?php selected($tf_correct, 'true'); ?>>True</option>
                                    <option value="false" <?php selected($tf_correct, 'false'); ?>>False</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'true_false_matrix' ? ' cbt-active' : ''; ?>" data-qtype="true_false_matrix">
                            <th>True/False Matrix</th>
                            <td>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <div class="cbt-option-row">
                                            <label for="cbt-tfm-statement-<?php echo (int) $i; ?>">Pernyataan <?php echo (int) $i; ?></label>
                                            <input
                                                type="text"
                                                id="cbt-tfm-statement-<?php echo (int) $i; ?>"
                                                class="regular-text"
                                                style="flex:1; min-width:260px;"
                                                value="<?php echo esc_attr((string) ($tf_matrix_rows[$i]['text'] ?? '')); ?>"
                                                placeholder="Isi pernyataan ke-<?php echo (int) $i; ?>"
                                            />
                                            <select id="cbt-tfm-answer-<?php echo (int) $i; ?>">
                                                <option value="true" <?php selected((string) ($tf_matrix_rows[$i]['answer'] ?? 'true'), 'true'); ?>>Benar</option>
                                                <option value="false" <?php selected((string) ($tf_matrix_rows[$i]['answer'] ?? 'true'), 'false'); ?>>Salah</option>
                                            </select>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi minimal 2 pernyataan. Siswa akan memilih Benar/Salah untuk setiap pernyataan.</p>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'short_answer' ? ' cbt-active' : ''; ?>" data-qtype="short_answer">
                            <th>Short Answer</th>
                            <td>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <div class="cbt-option-row">
                                            <label for="cbt-correct-sa-<?php echo (int) $i; ?>">Input <?php echo esc_html(chr(64 + $i)); ?></label>
                                            <input type="text" id="cbt-correct-sa-<?php echo (int) $i; ?>" class="regular-text" value="<?php echo esc_attr((string) ($editing_short_answer_inputs[$i] ?? '')); ?>" placeholder="Jawaban valid <?php echo esc_attr(chr(64 + $i)); ?>" />
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi berurutan dari Input A sampai maksimal Input H (8 textbox). Kosongkan sisanya jika tidak dipakai.</p>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'essay' ? ' cbt-active' : ''; ?>" data-qtype="essay">
                            <th>Essay</th>
                            <td>
                                <?php
                                wp_editor(
                                    $editing_type === 'essay' ? (string) $editing_essay_answer : '',
                                    'cbt_essay_answer_editor',
                                    [
                                        'textarea_name' => 'essay_answer',
                                        'textarea_rows' => 6,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'quicktags' => true,
                                    ]
                                );
                                ?>
                                <p class="description">Isi jawaban/acuan jawaban essay. Bisa menyertakan gambar.</p>
                            </td>
                        </tr>
                    </table>

                    <div class="cbt-questions-form-actions">
                        <?php echo get_submit_button($editing_question ? 'Update Question' : 'Save Question', 'primary', 'submit', false); ?>
                        <?php if ($editing_question): ?>
                            <a href="<?php echo esc_url($question_clear_edit_url); ?>" class="button button-secondary" data-cbt-questions-tab-link="list">Batal Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
                    <?php endif; ?>
                </section>

                <section class="cbt-questions-panel" data-cbt-questions-panel="import" role="tabpanel">
                    <div class="cbt-questions-panel-header">
                        <div>
                            <h2>Import Questions</h2>
                            <p>Upload template Word sesuai tipe soal untuk menambahkan soal baru secara massal ke Bank Soal subject yang dipilih.</p>
                        </div>
                        <span class="cbt-questions-chip">DOCX Import</span>
                    </div>
                <?php if (is_array($question_import_state)): ?>
                    <div class="notice notice-info">
                        <p>
                            <strong>Progress Import Soal:</strong>
                            <?php echo esc_html((string) $question_import_offset . ' / ' . (string) $question_import_total); ?>
                            (<?php echo esc_html(number_format($question_import_progress_percent, 2)); ?>%)
                            | Created: <?php echo esc_html((string) $question_import_created); ?>
                            | Failed: <?php echo esc_html((string) $question_import_failed); ?>
                        </p>
                    </div>
                    <div class="cbt-questions-progress">
                        <div class="cbt-questions-progress-track" aria-hidden="true">
                            <div class="cbt-questions-progress-fill" style="width: <?php echo esc_attr((string) $question_import_progress_percent); ?>%;"></div>
                        </div>
                        <div class="cbt-questions-progress-meta">
                            <?php if ($question_import_is_running): ?>
                                Memproses batch soal berikutnya...
                                <script>
                                    window.setTimeout(function () {
                                        window.location.href = <?php echo wp_json_encode($question_import_continue_url); ?>;
                                    }, 350);
                                </script>
                            <?php else: ?>
                                <span style="color:#0a7a2f; font-weight:600;">Import ke Bank Soal selesai diproses.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <p class="description"><strong>Rekomendasi:</strong> gunakan file <code>.docx</code> sesuai jenis soal yang dipilih.</p>
                <?php if ($lock_question_type): ?>
                    <p><strong>Jenis Soal:</strong> <?php echo esc_html((string) ($question_type_labels[$import_active_type] ?? $import_active_type)); ?></p>
                <?php else: ?>
                    <div class="cbt-tab-buttons" id="cbt-import-type-tabs">
                        <button type="button" class="button<?php echo $import_active_type === 'multiple_choice' ? ' cbt-active' : ''; ?>" data-import-type="multiple_choice">Multiple Choice</button>
                        <button type="button" class="button<?php echo $import_active_type === 'multiple_answer' ? ' cbt-active' : ''; ?>" data-import-type="multiple_answer">Multiple Answer</button>
                        <button type="button" class="button<?php echo $import_active_type === 'true_false' ? ' cbt-active' : ''; ?>" data-import-type="true_false">True/False</button>
                        <button type="button" class="button<?php echo $import_active_type === 'true_false_matrix' ? ' cbt-active' : ''; ?>" data-import-type="true_false_matrix">TF Matrix</button>
                        <button type="button" class="button<?php echo $import_active_type === 'short_answer' ? ' cbt-active' : ''; ?>" data-import-type="short_answer">Short Answer</button>
                        <button type="button" class="button<?php echo $import_active_type === 'essay' ? ' cbt-active' : ''; ?>" data-import-type="essay">Essay</button>
                    </div>
                <?php endif; ?>
                <p class="description" id="cbt-import-type-help"><?php echo esc_html($import_help_text); ?></p>
                <p class="cbt-questions-actions">
                    <label for="cbt-word-template-count"><strong>Jumlah Soal</strong></label>
                    <select id="cbt-word-template-count">
                        <?php for ($count_option = 10; $count_option <= 100; $count_option += 10): ?>
                            <option value="<?php echo (int) $count_option; ?>"><?php echo (int) $count_option; ?></option>
                        <?php endfor; ?>
                    </select>
                    <a
                        id="cbt-download-word-template"
                        class="button button-secondary"
                        data-url-mc="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_mc'), 'cbt_download_question_template_word_mc')); ?>"
                        data-url-ma="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_ma'), 'cbt_download_question_template_word_ma')); ?>"
                        data-url-tf="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_tf'), 'cbt_download_question_template_word_tf')); ?>"
                        data-url-tfm="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_tfm'), 'cbt_download_question_template_word_tfm')); ?>"
                        data-url-sa="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_sa'), 'cbt_download_question_template_word_sa')); ?>"
                        data-url-essay="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_essay'), 'cbt_download_question_template_word_essay')); ?>"
                        href="<?php echo esc_url(add_query_arg('question_count', 10, wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_mc'), 'cbt_download_question_template_word_mc'))); ?>"
                    >
                        Download Template Word MC (.docx)
                    </a>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-questions-tab-submit="import">
                    <?php wp_nonce_field('cbt_import_questions'); ?>
                    <input type="hidden" name="action" value="cbt_import_questions" />
                    <input type="hidden" name="return_page" value="<?php echo esc_attr($current_page_slug); ?>" />
                    <input type="hidden" name="import_question_type" id="cbt-import-question-type" value="<?php echo esc_attr($import_active_type); ?>" />
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cbt-import-subject-id">Subject (utama)</label></th>
                            <td>
                                <select required id="cbt-import-subject-id" name="import_subject_id">
                                    <option value="">Select subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo (int) $subject['id']; ?>" <?php selected($initial_subject_id, (int) $subject['id']); ?>>
                                            <?php echo esc_html((string) $subject['name'] . (!empty($subject['code']) ? ' (' . $subject['code'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php $initial_import_bank_target_label = (string) ($subject_bank_exam_labels[$initial_subject_id] ?? 'Bank Soal - Subject terpilih'); ?>
                                <p class="description">Soal import akan masuk ke Bank Soal untuk subject ini.</p>
                                <div class="cbt-question-bank-target" id="cbt-import-bank-target" data-bank-labels="<?php echo esc_attr(wp_json_encode($subject_bank_exam_labels)); ?>">
                                    <span class="cbt-question-bank-target-label">Target Import</span>
                                    <strong id="cbt-import-bank-target-name"><?php echo esc_html($initial_import_bank_target_label); ?></strong>
                                    <p class="description">Jika Bank Soal mapel ini belum ada, sistem akan menyiapkannya otomatis sebelum batch import diproses.</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-question-file">File Import</label></th>
                            <td>
                                <input required type="file" id="cbt-question-file" name="question_file" accept="<?php echo esc_attr($import_file_accept); ?>" />
                                <p class="description">
                                    Format didukung: <code>.docx</code>.
                                    Template berbentuk <strong>tabel</strong> untuk <strong>multiple choice</strong>, <strong>multiple answer</strong>, <strong>true/false</strong>, <strong>true/false matrix</strong>, <strong>short answer</strong>, dan <strong>essay</strong> (gambar bisa ditempel langsung di soal, termasuk opsi jawaban).
                                    Progress import akan tampil otomatis (jumlah diproses, persentase, created/failed).
                                </p>
                            </td>
                        </tr>
                    </table>
                    <div class="cbt-questions-form-actions">
                        <?php echo get_submit_button('Import Questions', 'primary', 'submit', false); ?>
                    </div>
                </form>
                </section>

                <section class="cbt-questions-panel" data-cbt-questions-panel="list" role="tabpanel">
                    <div data-cbt-questions-list-shell>
                    <div class="cbt-questions-panel-header">
                        <div>
                            <h2>Question List</h2>
                            <p><?php echo esc_html($question_list_intro_text); ?></p>
                        </div>
                        <span class="cbt-questions-chip"><?php echo esc_html(sprintf('%d total', $total_questions)); ?></span>
                    </div>
                    <div class="cbt-question-lineage-grid">
                        <?php foreach ($question_lineage_info_cards as $lineage_card): ?>
                            <div class="cbt-question-lineage-card">
                                <div class="cbt-question-lineage-card-head">
                                    <span class="cbt-questions-chip <?php echo esc_attr((string) ($lineage_card['class'] ?? '')); ?>">
                                        <?php echo esc_html((string) ($lineage_card['label'] ?? '')); ?>
                                    </span>
                                    <span class="cbt-question-lineage-card-count"><?php echo esc_html(sprintf('%d soal', (int) ($lineage_card['count'] ?? 0))); ?></span>
                                </div>
                                <p><?php echo esc_html((string) ($lineage_card['description'] ?? '')); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
            <?php if (is_array($question_delete_state)): ?>
                <div class="notice notice-info">
                    <p>
                        <strong>Progress Hapus Soal:</strong>
                        <?php echo esc_html((string) $question_delete_offset . ' / ' . (string) $question_delete_total); ?>
                        (<?php echo esc_html(number_format($question_delete_progress_percent, 2)); ?>%)
                        | Deleted: <?php echo esc_html((string) $question_delete_deleted); ?>
                        | Failed: <?php echo esc_html((string) $question_delete_failed); ?>
                    </p>
                </div>
                <div class="cbt-questions-progress">
                    <div class="cbt-questions-progress-track" aria-hidden="true">
                        <div class="cbt-questions-progress-fill" style="width: <?php echo esc_attr((string) $question_delete_progress_percent); ?>%;"></div>
                    </div>
                    <div class="cbt-questions-progress-meta">
                        <?php if ($question_delete_is_running): ?>
                            Memproses batch hapus soal berikutnya...
                            <script>
                                window.setTimeout(function () {
                                    window.location.href = <?php echo wp_json_encode($question_delete_continue_url); ?>;
                                }, 350);
                            </script>
                        <?php else: ?>
                            <span style="color:#0a7a2f; font-weight:600;">Proses hapus soal selesai diproses.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
                    <div class="cbt-questions-list-toolbar">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-questions-filter-form" data-cbt-questions-tab-submit="list">
                            <input type="hidden" name="page" value="<?php echo esc_attr($current_page_slug); ?>" />
                            <label for="cbt-filter-question-type">Type</label>
                            <?php if ($lock_question_type): ?>
                                <input type="hidden" name="filter_type" value="<?php echo esc_attr($list_filter_type); ?>" />
                                <select id="cbt-filter-question-type" disabled>
                                    <option value="<?php echo esc_attr($list_filter_type); ?>">
                                        <?php echo esc_html((string) ($question_type_labels[$list_filter_type] ?? $list_filter_type)); ?>
                                    </option>
                                </select>
                            <?php else: ?>
                                <select id="cbt-filter-question-type" name="filter_type">
                                    <option value="">All types</option>
                                    <?php foreach ($allowed_question_types as $question_type): ?>
                                        <option value="<?php echo esc_attr($question_type); ?>" <?php selected($list_filter_type, $question_type); ?>>
                                            <?php echo esc_html((string) ($question_type_labels[$question_type] ?? $question_type)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <label for="cbt-filter-question-source-kind">Sumber</label>
                            <select id="cbt-filter-question-source-kind" name="filter_source_kind">
                                <option value="">Semua sumber</option>
                                <option value="bank" <?php selected($list_filter_source_kind, 'bank'); ?>>Bank Soal</option>
                                <option value="bank_backed" <?php selected($list_filter_source_kind, 'bank_backed'); ?>>Bank-backed</option>
                                <?php if ($question_has_legacy_source): ?>
                                    <option value="legacy" <?php selected($list_filter_source_kind, 'legacy'); ?>>Legacy Source</option>
                                <?php endif; ?>
                            </select>
                            <label for="cbt-filter-question-subject">Subject</label>
                            <select id="cbt-filter-question-subject" name="filter_subject_id">
                                <option value="0">Semua subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo (int) $subject['id']; ?>" <?php selected($list_filter_subject_id, (int) $subject['id']); ?>>
                                        <?php echo esc_html((string) ($subject['name'] ?? '') . (!empty($subject['code']) ? ' (' . $subject['code'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="cbt-question-per-page">Per halaman</label>
                            <select id="cbt-question-per-page" name="cbt_question_per_page">
                                <?php foreach ([20, 40, 60, 80, 100] as $question_per_page_option): ?>
                                    <option value="<?php echo (int) $question_per_page_option; ?>" <?php selected($list_per_page, $question_per_page_option); ?>>
                                        <?php echo esc_html((string) $question_per_page_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <a class="button button-secondary" href="<?php echo esc_url($question_reset_url); ?>" data-cbt-questions-tab-link="list" data-cbt-questions-list-reset="1">Reset</a>
                        </form>
                    </div>
                    <div class="cbt-questions-filter-summary">
                        <span class="cbt-questions-chip"><?php echo esc_html('Type: ' . ($list_filter_type !== '' ? (string) ($question_type_labels[$list_filter_type] ?? $list_filter_type) : 'Semua tipe')); ?></span>
                        <span class="cbt-questions-chip"><?php echo esc_html('Sumber: ' . $list_filter_source_label); ?></span>
                        <span class="cbt-questions-chip"><?php echo esc_html('Subject: ' . $list_filter_subject_label); ?></span>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-questions-tab-submit="list" onsubmit="return confirm('Hapus semua soal yang dipilih?');">
                <?php wp_nonce_field('cbt_bulk_delete_questions'); ?>
                <input type="hidden" name="action" value="cbt_bulk_delete_questions" />
                <input type="hidden" name="return_page" value="<?php echo esc_attr($current_page_slug); ?>" />
                <input type="hidden" name="redirect_filter_type" value="<?php echo esc_attr($list_filter_type); ?>" />
                <input type="hidden" name="redirect_filter_source_kind" value="<?php echo esc_attr($list_filter_source_kind); ?>" />
                <input type="hidden" name="redirect_filter_subject_id" value="<?php echo (int) $list_filter_subject_id; ?>" />
                <input type="hidden" name="redirect_question_per_page" value="<?php echo (int) $list_per_page; ?>" />
                <input type="hidden" name="redirect_question_paged" value="<?php echo (int) $list_current_page; ?>" />
                <p class="cbt-questions-list-actions" style="margin: 0 0 8px;">
                    <button type="button" class="button button-secondary" id="cbt-view-selected-questions">Lihat Selected</button>
                    <button type="submit" class="button button-secondary">Delete Selected</button>
                </p>
                <div class="cbt-questions-table-wrap">
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="cbt-select-all-questions" /></th>
                        <th>ID</th>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Question</th>
                        <th>Points</th>
                        <th>Dipakai</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$questions): ?>
                        <tr><td colspan="8">No questions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($questions as $question): ?>
                            <?php
                            $question_view_url = add_query_arg(array_merge($question_list_args, ['view' => (int) $question['id']]), admin_url('admin.php'));
                            $question_hide_view_url = add_query_arg($question_list_args, admin_url('admin.php'));
                            $question_reference_url = add_query_arg(array_merge($question_list_args, ['reference' => (int) $question['id']]), admin_url('admin.php'));
                            $question_hide_reference_url = add_query_arg($question_list_args, admin_url('admin.php'));
                            $question_exam_title = (string) ($question['exam_title'] ?? '');
                            $question_source_exam_title = (string) ($question['source_exam_title'] ?? '');
                            $question_source_question_id = (int) ($question['source_question_id'] ?? 0);
                            $question_is_bank_source = stripos($question_exam_title, 'Bank Soal - ') === 0;
                            $question_is_bank_backed = !$question_is_bank_source
                                && $question_source_question_id > 0
                                && stripos($question_source_exam_title, 'Bank Soal - ') === 0;
                            $question_source_label = $question_is_bank_source
                                ? 'Bank Soal'
                                : ($question_is_bank_backed ? 'Bank-backed' : 'Legacy Source');
                            $question_source_chip_class = $question_is_bank_source
                                ? 'cbt-questions-chip--bank'
                                : ($question_is_bank_backed ? 'cbt-questions-chip--bank-backed' : 'cbt-questions-chip--legacy');
                            $question_is_view_open = $view_question && (int) ($view_question['id'] ?? 0) === (int) ($question['id'] ?? 0);
                            $question_is_reference_open = $question_is_bank_source && $question_reference_open_id === (int) ($question['id'] ?? 0);
                            $question_usage_summary = (array) ($question_bank_usage_summary_map[(int) ($question['id'] ?? 0)] ?? [
                                'exam_count' => 0,
                                'descendant_count' => 0,
                                'active_count' => 0,
                                'inactive_count' => 0,
                            ]);
                            $question_usage_exam_count = max(0, (int) ($question_usage_summary['exam_count'] ?? 0));
                            $question_usage_descendant_count = max(0, (int) ($question_usage_summary['descendant_count'] ?? 0));
                            $question_usage_active_count = max(0, (int) ($question_usage_summary['active_count'] ?? 0));
                            $question_usage_inactive_count = max(0, (int) ($question_usage_summary['inactive_count'] ?? 0));
                            $question_usage_text = $question_usage_exam_count <= 0
                                ? '0 exam'
                                : ($question_usage_exam_count . ' exam · ' . $question_usage_descendant_count . ' turunan');
                            $question_edit_target_id = $question_is_bank_backed && $question_source_question_id > 0
                                ? $question_source_question_id
                                : (int) ($question['id'] ?? 0);
                            $question_edit_label = $question_is_bank_backed && $question_source_question_id > 0
                                ? 'Edit Sumber'
                                : 'Edit';
                            $question_edit_url = add_query_arg(array_merge($question_list_args, ['edit' => $question_edit_target_id]), admin_url('admin.php'));
                            ?>
                            <tr>
                                <td><input type="checkbox" class="cbt-question-checkbox" name="question_ids[]" value="<?php echo (int) $question['id']; ?>" data-view-url="<?php echo esc_url($question_view_url); ?>" /></td>
                                <td><?php echo (int) $question['id']; ?></td>
                                <td>
                                    <?php echo esc_html((string) ($question['subject_name'] ?? '-')); ?>
                                    <div class="cbt-questions-source-meta">
                                        <span class="cbt-questions-chip <?php echo esc_attr($question_source_chip_class); ?>">
                                            <?php echo esc_html($question_source_label); ?>
                                        </span>
                                        <?php if ($question_exam_title !== ''): ?>
                                            <span class="cbt-questions-source-title"><?php echo esc_html($question_exam_title); ?></span>
                                        <?php endif; ?>
                                        <?php if ($question_is_bank_backed && $question_source_exam_title !== ''): ?>
                                            <span class="cbt-questions-source-title"><?php echo esc_html('Sumber bank: ' . $question_source_exam_title); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html($question['question_type']); ?></td>
                                <td><?php echo esc_html(wp_trim_words((string) $question['question_text'], 12)); ?></td>
                                <td><?php echo esc_html((string) $question['points']); ?></td>
                                <td>
                                    <?php if ($question_is_bank_source): ?>
                                        <div class="cbt-question-usage-cell">
                                            <span class="cbt-question-usage-summary"><?php echo esc_html($question_usage_text); ?></span>
                                            <a class="cbt-question-reference-toggle" data-cbt-questions-reference-view="1" href="<?php echo esc_url($question_is_reference_open ? $question_hide_reference_url : $question_reference_url); ?>">
                                                <?php echo esc_html($question_is_reference_open ? 'Hide Referensi' : 'Lihat Referensi'); ?>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="cbt-questions-source-title">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cbt-questions-row-actions">
                                        <a class="cbt-questions-row-action cbt-questions-row-action--view" data-cbt-questions-inline-view="1" href="<?php echo esc_url($question_is_view_open ? $question_hide_view_url : $question_view_url); ?>"><?php echo esc_html($question_is_view_open ? 'Hide' : 'Lihat'); ?></a>
                                        <a class="cbt-questions-row-action cbt-questions-row-action--edit" href="<?php echo esc_url($question_edit_url); ?>"><?php echo esc_html($question_edit_label); ?></a>
                                        <a class="cbt-questions-row-action cbt-questions-row-action--delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'cbt_delete_question', 'id' => (int) $question['id'], 'return_page' => $current_page_slug, 'filter_type' => $list_filter_type, 'filter_source_kind' => $list_filter_source_kind, 'filter_subject_id' => $list_filter_subject_id, 'question_per_page' => $list_per_page, 'question_paged' => $list_current_page], admin_url('admin-post.php')), 'cbt_delete_question_' . (int) $question['id'])); ?>" onclick="return confirm('Delete this question?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($question_is_reference_open): ?>
                                <tr class="cbt-question-reference-row">
                                    <td colspan="8">
                                        <div class="cbt-question-reference-panel">
                                            <div class="cbt-question-reference-panel-head">
                                                <div>
                                                    <strong>Referenced By</strong>
                                                    <div class="cbt-question-reference-panel-meta">
                                                        Root bank question ini dipakai di <?php echo esc_html((string) $question_usage_exam_count); ?> exam dengan total <?php echo esc_html((string) $question_usage_descendant_count); ?> turunan.
                                                    </div>
                                                </div>
                                                <a class="button button-secondary" data-cbt-questions-reference-view="1" href="<?php echo esc_url($question_hide_reference_url); ?>">Tutup Referensi</a>
                                            </div>
                                            <?php if (empty($question_reference_rows)): ?>
                                                <div class="cbt-question-reference-empty">Belum ada bank-backed descendant untuk soal ini.</div>
                                            <?php else: ?>
                                                <div class="cbt-question-reference-grid">
                                                    <?php foreach ((array) $question_reference_rows as $question_reference_row): ?>
                                                        <?php
                                                        $reference_status = sanitize_html_class((string) ($question_reference_row['status'] ?? 'draft'));
                                                        $reference_descendant_count = (int) ($question_reference_row['descendant_count'] ?? 0);
                                                        $reference_active_count = (int) ($question_reference_row['active_count'] ?? 0);
                                                        $reference_inactive_count = (int) ($question_reference_row['inactive_count'] ?? 0);
                                                        ?>
                                                        <article class="cbt-question-reference-card">
                                                            <div class="cbt-question-reference-card-head">
                                                                <div>
                                                                    <strong><?php echo esc_html((string) ($question_reference_row['exam_title'] ?? 'Exam')); ?></strong>
                                                                    <div class="cbt-question-reference-card-meta"><?php echo esc_html((string) ($question_reference_row['subject_label'] ?? '-')); ?></div>
                                                                </div>
                                                                <span class="cbt-question-reference-status cbt-question-reference-status--<?php echo esc_attr($reference_status); ?>">
                                                                    <?php echo esc_html((string) ($question_reference_row['status_label'] ?? 'Draft')); ?>
                                                                </span>
                                                            </div>
                                                            <div class="cbt-question-reference-stats">
                                                                <span class="cbt-question-reference-stat"><?php echo esc_html($reference_descendant_count . ' turunan'); ?></span>
                                                                <span class="cbt-question-reference-stat"><?php echo esc_html('Aktif ' . $reference_active_count); ?></span>
                                                                <span class="cbt-question-reference-stat"><?php echo esc_html('Inactive/Archived ' . $reference_inactive_count); ?></span>
                                                            </div>
                                                            <div class="cbt-question-reference-actions">
                                                                <a class="cbt-question-reference-toggle" href="<?php echo esc_url((string) ($question_reference_row['edit_url'] ?? admin_url('admin.php?page=cbt-exams'))); ?>">Buka Exam</a>
                                                            </div>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($question_is_view_open): ?>
                                <tr class="cbt-question-inline-preview-row">
                                    <td colspan="8">
                                        <div class="cbt-question-inline-preview">
                                            <div class="cbt-question-inline-preview-head">
                                                <div>
                                                    <span class="cbt-question-inline-preview-kicker">Preview Soal</span>
                                                    <div class="cbt-question-inline-preview-title">Soal #<?php echo (int) $view_question['id']; ?></div>
                                                    <div class="cbt-question-inline-preview-meta">
                                                        <?php echo esc_html((string) ($question['subject_name'] ?? '-')); ?>
                                                        <?php if ($question_exam_title !== ''): ?>
                                                            · <?php echo esc_html($question_exam_title); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="cbt-question-inline-preview-chips" style="margin-top:10px;">
                                                        <span class="cbt-question-inline-preview-pill cbt-question-inline-preview-pill--type">
                                                            <?php echo esc_html((string) ($question_type_labels[(string) ($view_question['question_type'] ?? '')] ?? (string) ($view_question['question_type'] ?? ''))); ?>
                                                        </span>
                                                        <span class="cbt-questions-chip <?php echo esc_attr($question_source_chip_class); ?>">
                                                            <?php echo esc_html($question_source_label); ?>
                                                        </span>
                                                        <span class="cbt-question-inline-preview-pill cbt-question-inline-preview-pill--points">
                                                            <?php echo esc_html(sprintf('%s poin', (string) ($question['points'] ?? '0'))); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <a class="button button-secondary" data-cbt-questions-inline-view="1" href="<?php echo esc_url($question_hide_view_url); ?>">Tutup Preview</a>
                                            </div>
                                            <div class="cbt-question-inline-preview-body">
                                                <div class="cbt-question-inline-preview-question"><?php echo wp_kses_post((string) ($view_question['question_text'] ?? '')); ?></div>
                                                <?php if (!empty($view_options)): ?>
                                                    <div class="cbt-question-inline-preview-section">
                                                        <strong>Opsi Jawaban</strong>
                                                        <div class="cbt-question-inline-preview-options">
                                                            <?php foreach ($view_options as $index => $opt): ?>
                                                                <?php $option_is_correct = (int) ($opt['is_correct'] ?? 0) === 1; ?>
                                                                <div class="cbt-question-inline-preview-option<?php echo $option_is_correct ? ' is-correct' : ''; ?>">
                                                                    <div class="cbt-question-inline-preview-option-main">
                                                                        <span class="cbt-question-inline-preview-option-key"><?php echo esc_html((string) ($opt['option_key'] ?? chr(65 + (int) $index))); ?></span>
                                                                        <div class="cbt-question-inline-preview-option-text"><?php echo wp_kses_post((string) ($opt['option_text'] ?? '')); ?></div>
                                                                    </div>
                                                                    <div class="cbt-question-inline-preview-badges">
                                                                        <?php if ($option_is_correct): ?>
                                                                            <span class="cbt-question-inline-preview-badge cbt-question-inline-preview-badge--correct">Kunci</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ((string) ($view_question['question_type'] ?? '') === 'short_answer'): ?>
                                                    <?php $view_short_answers = CBT_Admin_Questions_Helper::normalize_short_answer_values((string) ($view_detail['correct_text'] ?? '')); ?>
                                                    <?php if (!empty($view_short_answers)): ?>
                                                        <div class="cbt-question-inline-preview-section">
                                                            <strong>Jawaban Valid</strong>
                                                            <div class="cbt-question-inline-preview-chip-list">
                                                                <?php foreach ($view_short_answers as $ans): ?>
                                                                    <span class="cbt-question-inline-preview-chip"><?php echo esc_html($ans); ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ((string) ($view_question['question_type'] ?? '') === 'true_false'): ?>
                                                    <div class="cbt-question-inline-preview-section">
                                                        <strong>Jawaban</strong>
                                                        <div class="cbt-question-inline-preview-chip-list">
                                                            <span class="cbt-question-inline-preview-chip"><?php echo ((int) ($view_detail['correct_value'] ?? 1) === 1) ? 'True' : 'False'; ?></span>
                                                        </div>
                                                    </div>
                                                <?php elseif ((string) ($view_question['question_type'] ?? '') === 'true_false_matrix'): ?>
                                                    <?php $view_tf_matrix = CBT_Admin_Questions_Helper::normalize_true_false_matrix_config((string) ($view_question['correct_text'] ?? '')); ?>
                                                    <?php if (!empty($view_tf_matrix)): ?>
                                                        <div class="cbt-question-inline-preview-section">
                                                            <strong>Pernyataan (Kunci Benar/Salah)</strong>
                                                            <div class="cbt-question-inline-preview-matrix">
                                                                <?php foreach ($view_tf_matrix as $row): ?>
                                                                    <div class="cbt-question-inline-preview-matrix-row">
                                                                        <div><?php echo esc_html((string) ($row['text'] ?? '')); ?></div>
                                                                        <div class="cbt-question-inline-preview-matrix-answer">
                                                                            Kunci: <strong><?php echo ((string) ($row['answer'] ?? 'true') === 'false') ? 'Salah' : 'Benar'; ?></strong>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ((string) ($view_question['question_type'] ?? '') === 'essay'): ?>
                                                    <div class="cbt-question-inline-preview-section">
                                                        <strong>Acuan Jawaban</strong>
                                                        <div class="cbt-question-inline-preview-text"><?php echo wp_kses_post((string) ($view_detail['rubric_text'] ?? '')); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
                <?php
                $question_pagination_links = [];
                if ($total_question_pages > 1) {
                    $question_pagination_links = paginate_links([
                        'base' => add_query_arg(
                            array_merge($question_list_args, ['cbt_question_paged' => '%#%']),
                            admin_url('admin.php')
                        ),
                        'format' => '',
                        'current' => $list_current_page,
                        'total' => $total_question_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'type' => 'array',
                        'end_size' => 1,
                        'mid_size' => 1,
                    ]);
                }
                ?>
                <div class="tablenav bottom cbt-admin-pagination-wrap" style="margin-top:10px;">
                    <div class="tablenav-pages cbt-admin-pagination" style="float:none; margin:0;">
                        <span class="displaying-num cbt-admin-total"><?php echo esc_html(sprintf('Total question: %d', $total_questions)); ?></span>
                        <?php if (!empty($question_pagination_links)): ?>
                            <span class="pagination-links cbt-admin-pagination-links">
                                <?php foreach ($question_pagination_links as $question_pagination_link): ?>
                                    <?php echo wp_kses_post($question_pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                    </form>
                    </div>
                </section>
            </div>
        </div>
        <script>
            (function () {
                const page = document.querySelector('.cbt-questions-page');
                const pageTabButtons = Array.from(document.querySelectorAll('[data-cbt-questions-tab]'));
                const pageTabPanels = Array.from(document.querySelectorAll('[data-cbt-questions-panel]'));
                const pageTabStorageKey = 'cbt-questions-active-tab';
                const defaultTab = page ? String(page.getAttribute('data-cbt-questions-default-tab') || 'list') : 'list';
                const forceTab = page ? page.getAttribute('data-cbt-questions-force-tab') === '1' : false;
                const subjectSelect = document.getElementById('cbt-subject-id');
                const bankTarget = document.getElementById('cbt-question-bank-target');
                const bankTargetName = document.getElementById('cbt-question-bank-target-name');
                const bankTargetLabels = bankTarget ? JSON.parse(String(bankTarget.getAttribute('data-bank-labels') || '{}')) : {};
                const importSubjectSelect = document.getElementById('cbt-import-subject-id');
                const importBankTarget = document.getElementById('cbt-import-bank-target');
                const importBankTargetName = document.getElementById('cbt-import-bank-target-name');
                const importBankTargetLabels = importBankTarget ? JSON.parse(String(importBankTarget.getAttribute('data-bank-labels') || '{}')) : {};

                function activatePageTab(tabId, persist) {
                    let hasTarget = false;
                    pageTabButtons.forEach((button) => {
                        const isActive = button.getAttribute('data-cbt-questions-tab') === tabId;
                        button.classList.toggle('is-active', isActive);
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        if (isActive) {
                            hasTarget = true;
                        }
                    });
                    pageTabPanels.forEach((panel) => {
                        const isActive = panel.getAttribute('data-cbt-questions-panel') === tabId;
                        panel.classList.toggle('is-active', isActive);
                    });
                    if (persist && hasTarget && window.localStorage) {
                        window.localStorage.setItem(pageTabStorageKey, tabId);
                    }
                }

                if (page && pageTabButtons.length > 0 && pageTabPanels.length > 0) {
                    let initialTab = defaultTab;
                    if (!forceTab && window.localStorage) {
                        const savedTab = window.localStorage.getItem(pageTabStorageKey);
                        if (savedTab && pageTabPanels.some((panel) => panel.getAttribute('data-cbt-questions-panel') === savedTab)) {
                            initialTab = savedTab;
                        }
                    }

                    activatePageTab(initialTab, false);

                    pageTabButtons.forEach((button) => {
                        button.addEventListener('click', function () {
                            activatePageTab(String(button.getAttribute('data-cbt-questions-tab') || ''), true);
                        });
                    });

                    Array.from(document.querySelectorAll('form[data-cbt-questions-tab-submit]')).forEach((form) => {
                        form.addEventListener('submit', function () {
                            const tabId = String(form.getAttribute('data-cbt-questions-tab-submit') || '');
                            if (tabId !== '' && window.localStorage) {
                                window.localStorage.setItem(pageTabStorageKey, tabId);
                            }
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-questions-tab-link]')).forEach((link) => {
                        link.addEventListener('click', function () {
                            const tabId = String(link.getAttribute('data-cbt-questions-tab-link') || '');
                            if (tabId !== '' && window.localStorage) {
                                window.localStorage.setItem(pageTabStorageKey, tabId);
                            }
                        });
                    });
                }

                const qTypeHidden = document.getElementById('cbt-question-type-hidden');
                const qTypeTabs = document.getElementById('cbt-question-type-tabs');
                const qTypePanels = document.querySelectorAll('.cbt-qtype-panel');

                function activateQType(type, shouldFocus = false) {
                    if (!qTypeHidden) return;
                    qTypeHidden.value = type;
                    if (qTypeTabs) {
                        qTypeTabs.querySelectorAll('button[data-qtype]').forEach((btn) => {
                            btn.classList.toggle('cbt-active', btn.getAttribute('data-qtype') === type);
                        });
                    }
                    qTypePanels.forEach((panel) => {
                        panel.classList.toggle('cbt-active', panel.getAttribute('data-qtype') === type);
                    });

                    if (!shouldFocus) return;

                    if (type === 'multiple_choice') {
                        document.getElementById('cbt-correct-mc-index')?.focus();
                    } else if (type === 'multiple_answer') {
                        document.getElementById('cbt-ma-correct-1')?.focus();
                    } else if (type === 'true_false') {
                        document.getElementById('cbt-correct-tf')?.focus();
                    } else if (type === 'true_false_matrix') {
                        document.getElementById('cbt-tfm-statement-1')?.focus();
                    } else if (type === 'short_answer') {
                        document.getElementById('cbt-correct-sa-1')?.focus();
                    } else if (type === 'essay') {
                        document.getElementById('cbt_essay_answer_editor')?.focus();
                    }
                }

                if (qTypeTabs) {
                    qTypeTabs.querySelectorAll('button[data-qtype]').forEach((btn) => {
                        btn.addEventListener('click', () => activateQType(btn.getAttribute('data-qtype'), true));
                    });
                }
                activateQType(qTypeHidden?.value || 'multiple_choice');

                function syncBankTarget() {
                    if (!subjectSelect || !bankTargetName) return;
                    const subjectId = String(subjectSelect.value || '');
                    if (subjectId !== '' && Object.prototype.hasOwnProperty.call(bankTargetLabels, subjectId)) {
                        bankTargetName.textContent = String(bankTargetLabels[subjectId] || '');
                        return;
                    }
                    bankTargetName.textContent = 'Bank Soal - Subject terpilih';
                }

                if (subjectSelect && bankTarget) {
                    subjectSelect.addEventListener('change', syncBankTarget);
                    syncBankTarget();
                }

                function syncImportBankTarget() {
                    if (!importSubjectSelect || !importBankTargetName) return;
                    const subjectId = String(importSubjectSelect.value || '');
                    if (subjectId !== '' && Object.prototype.hasOwnProperty.call(importBankTargetLabels, subjectId)) {
                        importBankTargetName.textContent = String(importBankTargetLabels[subjectId] || '');
                        return;
                    }
                    importBankTargetName.textContent = 'Bank Soal - Subject terpilih';
                }

                if (importSubjectSelect && importBankTarget) {
                    importSubjectSelect.addEventListener('change', syncImportBankTarget);
                    syncImportBankTarget();
                }

                const importTypeTabs = document.getElementById('cbt-import-type-tabs');
                const importTypeHidden = document.getElementById('cbt-import-question-type');
                const importHelp = document.getElementById('cbt-import-type-help');
                const importFileInput = document.getElementById('cbt-question-file');
                const wordTemplateButton = document.getElementById('cbt-download-word-template');
                const wordTemplateCount = document.getElementById('cbt-word-template-count');
                const docxFileAccept = '.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

                const importTypeInfo = {
                    multiple_choice: {
                        help: 'Mode import aktif: Multiple Choice. DOCX didukung (maks 5 opsi, jawaban nomor opsi, gambar bisa ditempel).',
                        buttonLabel: 'Download Template Word MC (.docx)',
                        urlKey: 'urlMc',
                    },
                    multiple_answer: {
                        help: 'Mode import aktif: Multiple Answer. DOCX didukung (maks 12 opsi, jawaban bisa lebih dari satu: contoh 1,3,5).',
                        buttonLabel: 'Download Template Word MA (.docx)',
                        urlKey: 'urlMa',
                    },
                    true_false: {
                        help: 'Mode import aktif: True/False. DOCX didukung (jawaban: true/false).',
                        buttonLabel: 'Download Template Word TF (.docx)',
                        urlKey: 'urlTf',
                    },
                    true_false_matrix: {
                        help: 'Mode import aktif: True/False Matrix. DOCX didukung (isi PERNYATAAN_1..10 dan KUNCI_1..10: true/false).',
                        buttonLabel: 'Download Template Word TF Matrix (.docx)',
                        urlKey: 'urlTfm',
                    },
                    short_answer: {
                        help: 'Mode import aktif: Short Answer. DOCX didukung (maks 8 jawaban valid per soal, gunakan placeholder [INPUT_1] s.d. [INPUT_8] di teks soal).',
                        buttonLabel: 'Download Template Word SA (.docx)',
                        urlKey: 'urlSa',
                    },
                    essay: {
                        help: 'Mode import aktif: Essay. DOCX didukung (wajib isi acuan jawaban/rubrik).',
                        buttonLabel: 'Download Template Word Essay (.docx)',
                        urlKey: 'urlEssay',
                    },
                };

                function activateImportType(type) {
                    if (!importTypeHidden) return;

                    const info = importTypeInfo[type] || importTypeInfo.multiple_choice;
                    importTypeHidden.value = type;

                    if (importTypeTabs) {
                        importTypeTabs.querySelectorAll('button[data-import-type]').forEach((btn) => {
                            btn.classList.toggle('cbt-active', btn.getAttribute('data-import-type') === type);
                        });
                    }

                    if (importHelp) {
                        importHelp.textContent = info.help;
                    }

                    if (importFileInput) {
                        importFileInput.accept = docxFileAccept;
                    }

                    if (wordTemplateButton) {
                        const parsedCount = parseInt(wordTemplateCount?.value || '10', 10);
                        const safeCount = Number.isFinite(parsedCount) ? parsedCount : 10;
                        let selectedCount = Math.floor(safeCount / 10) * 10;
                        if (selectedCount < 10) selectedCount = 10;
                        if (selectedCount > 100) selectedCount = 100;
                        if (wordTemplateCount && String(wordTemplateCount.value) !== String(selectedCount)) {
                            wordTemplateCount.value = String(selectedCount);
                        }
                        const baseUrl = wordTemplateButton.dataset[info.urlKey] || '';
                        if (baseUrl) {
                            const separator = baseUrl.includes('?') ? '&' : '?';
                            wordTemplateButton.setAttribute('href', `${baseUrl}${separator}question_count=${selectedCount}`);
                        }
                        wordTemplateButton.textContent = info.buttonLabel;
                    }
                }

                if (importTypeTabs) {
                    importTypeTabs.querySelectorAll('button[data-import-type]').forEach((btn) => {
                        btn.addEventListener('click', () => activateImportType(btn.getAttribute('data-import-type')));
                    });
                }

                if (wordTemplateCount) {
                    wordTemplateCount.addEventListener('change', () => {
                        activateImportType(importTypeHidden?.value || 'multiple_choice');
                    });
                }

                activateImportType(importTypeHidden?.value || 'multiple_choice');

                const questionListPanel = document.querySelector('[data-cbt-questions-panel="list"]');
                let questionListRequestId = 0;

                function buildQuestionListUrlFromForm(form) {
                    const actionUrl = String(form.getAttribute('action') || window.location.href);
                    const url = new URL(actionUrl, window.location.origin);
                    const formData = new window.FormData(form);
                    const params = new URLSearchParams();

                    formData.forEach((value, key) => {
                        params.set(String(key), String(value));
                    });

                    url.search = params.toString();
                    return url.toString();
                }

                async function refreshQuestionList(url) {
                    if (!questionListPanel) {
                        window.location.href = url;
                        return;
                    }

                    const currentRequestId = ++questionListRequestId;
                    questionListPanel.classList.add('cbt-is-loading');

                    try {
                        const response = await window.fetch(url, {
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }

                        const html = await response.text();
                        if (currentRequestId !== questionListRequestId) {
                            return;
                        }

                        const parsed = new window.DOMParser().parseFromString(html, 'text/html');
                        const incomingShell = parsed.querySelector('[data-cbt-questions-list-shell]');
                        const currentShell = questionListPanel.querySelector('[data-cbt-questions-list-shell]');

                        if (!incomingShell || !currentShell) {
                            window.location.href = url;
                            return;
                        }

                        currentShell.replaceWith(incomingShell);
                        if (window.history && typeof window.history.replaceState === 'function') {
                            window.history.replaceState({}, '', url);
                        }
                        bindQuestionListInteractions();
                    } catch (error) {
                        window.location.href = url;
                        return;
                    } finally {
                        if (currentRequestId === questionListRequestId) {
                            questionListPanel.classList.remove('cbt-is-loading');
                        }
                    }
                }

                function bindQuestionListInteractions() {
                    const listPanel = document.querySelector('[data-cbt-questions-panel="list"]');
                    if (!listPanel) {
                        return;
                    }

                    const questionFilterForm = listPanel.querySelector('.cbt-questions-filter-form');
                    const selectAllQuestions = listPanel.querySelector('#cbt-select-all-questions');
                    const questionCheckboxes = Array.from(listPanel.querySelectorAll('.cbt-question-checkbox'));
                    const viewSelectedQuestionsButton = listPanel.querySelector('#cbt-view-selected-questions');
                    const filterResetLink = listPanel.querySelector('[data-cbt-questions-list-reset="1"]');
                    const paginationLinks = Array.from(listPanel.querySelectorAll('.cbt-admin-pagination-links a.page-numbers'));
                    const inlineViewLinks = Array.from(listPanel.querySelectorAll('[data-cbt-questions-inline-view="1"]'));
                    const referenceViewLinks = Array.from(listPanel.querySelectorAll('[data-cbt-questions-reference-view="1"]'));

                    if (questionFilterForm) {
                        questionFilterForm.addEventListener('submit', (event) => {
                            event.preventDefault();
                            refreshQuestionList(buildQuestionListUrlFromForm(questionFilterForm));
                        });

                        const autoSubmitFilterFields = Array.from(
                            questionFilterForm.querySelectorAll('select[name="filter_type"], select[name="filter_source_kind"], select[name="filter_subject_id"], select[name="cbt_question_per_page"]')
                        );
                        autoSubmitFilterFields.forEach((field) => {
                            field.addEventListener('change', () => {
                                refreshQuestionList(buildQuestionListUrlFromForm(questionFilterForm));
                            });
                        });
                    }

                    if (filterResetLink) {
                        filterResetLink.addEventListener('click', (event) => {
                            event.preventDefault();
                            refreshQuestionList(String(filterResetLink.getAttribute('href') || window.location.href));
                        });
                    }

                    paginationLinks.forEach((link) => {
                        const href = String(link.getAttribute('href') || '').trim();
                        if (href === '' || link.classList.contains('current')) {
                            return;
                        }
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            refreshQuestionList(href);
                        });
                    });

                    inlineViewLinks.forEach((link) => {
                        const href = String(link.getAttribute('href') || '').trim();
                        if (href === '') {
                            return;
                        }
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            refreshQuestionList(href);
                        });
                    });

                    referenceViewLinks.forEach((link) => {
                        const href = String(link.getAttribute('href') || '').trim();
                        if (href === '') {
                            return;
                        }
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            refreshQuestionList(href);
                        });
                    });

                    if (selectAllQuestions && questionCheckboxes.length > 0) {
                        const syncSelectAllState = () => {
                            const checkedCount = questionCheckboxes.filter((item) => item.checked).length;
                            selectAllQuestions.checked = checkedCount > 0 && checkedCount === questionCheckboxes.length;
                            selectAllQuestions.indeterminate = checkedCount > 0 && checkedCount < questionCheckboxes.length;
                        };

                        selectAllQuestions.addEventListener('change', () => {
                            questionCheckboxes.forEach((item) => {
                                item.checked = selectAllQuestions.checked;
                            });
                            syncSelectAllState();
                        });

                        questionCheckboxes.forEach((item) => {
                            item.addEventListener('change', syncSelectAllState);
                        });
                    }

                    if (viewSelectedQuestionsButton && questionCheckboxes.length > 0) {
                        viewSelectedQuestionsButton.addEventListener('click', () => {
                            const selectedViewUrls = questionCheckboxes
                                .filter((item) => item.checked)
                                .map((item) => String(item.dataset.viewUrl || '').trim())
                                .filter((url) => url !== '');

                            if (selectedViewUrls.length === 0) {
                                alert('Pilih minimal 1 soal untuk dilihat.');
                                return;
                            }

                            let openedCount = 0;
                            selectedViewUrls.forEach((url) => {
                                const openedWindow = window.open(url, '_blank', 'noopener');
                                if (openedWindow) {
                                    openedCount += 1;
                                }
                            });

                            if (openedCount === 0) {
                                alert('Browser memblokir tab baru. Izinkan pop-up untuk halaman ini.');
                            }
                        });
                    }
                }

                bindQuestionListInteractions();

                const manualForm = document.getElementById('cbt-question-manual-form');
                if (manualForm) {
                    manualForm.addEventListener('submit', (event) => {
                        if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
                            window.tinyMCE.triggerSave();
                        }

                        const type = qTypeHidden ? qTypeHidden.value : 'multiple_choice';
                        const optionsHidden = document.getElementById('cbt-options-hidden');
                        const correctTextHidden = document.getElementById('cbt-correct-text-hidden');
                        if (!optionsHidden || !correctTextHidden) return;

                        optionsHidden.value = '';
                        correctTextHidden.value = '';

                        const editorValue = (id) => String(document.getElementById(id)?.value || '').trim();
                        const hasOptionContent = (html) => {
                            const raw = String(html || '');
                            if (/<img\b/i.test(raw)) return true;
                            const textOnly = raw
                                .replace(/<[^>]*>/g, '')
                                .replace(/&nbsp;/gi, ' ')
                                .trim();
                            return textOnly !== '';
                        };

                        if (type === 'multiple_choice') {
                            const optionsPayload = [];
                            const correctIdx = parseInt(String(document.getElementById('cbt-correct-mc-index')?.value || '1'), 10);
                            let filledCount = 0;

                            for (let i = 1; i <= 5; i += 1) {
                                const optVal = editorValue(`cbt_mc_option_${i}`);
                                if (!hasOptionContent(optVal)) continue;
                                filledCount += 1;
                                optionsPayload.push({
                                    option_text: optVal,
                                    is_correct: i === correctIdx ? 1 : 0,
                                });
                            }

                            if (filledCount < 2) {
                                event.preventDefault();
                                window.alert('Multiple Choice minimal harus punya 2 pilihan.');
                                return;
                            }

                            if (!optionsPayload.some((item) => Number(item.is_correct) === 1)) {
                                event.preventDefault();
                                window.alert('Pilih jawaban benar untuk Multiple Choice.');
                                return;
                            }

                            optionsHidden.value = JSON.stringify(optionsPayload);
                        } else if (type === 'multiple_answer') {
                            const optionsPayload = [];
                            let filledCount = 0;
                            let correctCount = 0;

                            for (let i = 1; i <= 12; i += 1) {
                                const optVal = editorValue(`cbt_ma_option_${i}`);
                                if (!hasOptionContent(optVal)) continue;
                                filledCount += 1;
                                const checked = !!document.getElementById(`cbt-ma-correct-${i}`)?.checked;
                                if (checked) correctCount += 1;
                                optionsPayload.push({
                                    option_text: optVal,
                                    is_correct: checked ? 1 : 0,
                                });
                            }

                            if (filledCount < 2) {
                                event.preventDefault();
                                window.alert('Multiple Answer minimal harus punya 2 pilihan.');
                                return;
                            }

                            if (correctCount === 0) {
                                event.preventDefault();
                                window.alert('Centang minimal 1 jawaban benar untuk Multiple Answer.');
                                return;
                            }

                            optionsHidden.value = JSON.stringify(optionsPayload);
                        } else if (type === 'true_false') {
                            const tf = String(document.getElementById('cbt-correct-tf')?.value || 'true').toLowerCase();
                            correctTextHidden.value = tf === 'false' ? 'false' : 'true';
                        } else if (type === 'true_false_matrix') {
                            const statements = [];
                            for (let i = 1; i <= 10; i += 1) {
                                const statementText = String(document.getElementById(`cbt-tfm-statement-${i}`)?.value || '').trim();
                                if (statementText === '') continue;
                                const answerValue = String(document.getElementById(`cbt-tfm-answer-${i}`)?.value || 'true').toLowerCase();
                                statements.push({
                                    text: statementText,
                                    answer: answerValue === 'false' ? 'false' : 'true',
                                });
                            }

                            if (statements.length < 2) {
                                event.preventDefault();
                                window.alert('True/False Matrix minimal harus punya 2 pernyataan.');
                                return;
                            }

                            correctTextHidden.value = JSON.stringify({ statements });
                        } else if (type === 'short_answer') {
                            const shortAnswerValues = [];
                            for (let i = 1; i <= 8; i += 1) {
                                const val = String(document.getElementById(`cbt-correct-sa-${i}`)?.value || '').trim();
                                if (val !== '') {
                                    shortAnswerValues.push(val);
                                }
                            }

                            if (shortAnswerValues.length === 0) {
                                event.preventDefault();
                                window.alert('Short Answer minimal harus punya 1 jawaban valid.');
                                return;
                            }

                            correctTextHidden.value = JSON.stringify(shortAnswerValues.slice(0, 8));
                        }
                    });
                }
            })();
        </script>
        <?php
