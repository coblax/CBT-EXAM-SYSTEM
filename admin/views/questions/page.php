<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
        <div
            class="wrap cbt-questions-page"
            data-cbt-questions-default-tab="<?php echo esc_attr($default_question_tab); ?>"
            data-cbt-questions-force-tab="<?php echo $question_tab_is_forced ? '1' : '0'; ?>"
            data-cbt-questions-root
        >
            <div class="cbt-questions-shell">
                <section class="cbt-questions-hero">
                    <div class="cbt-questions-hero-copy">
                        <span class="cbt-questions-kicker">Questions</span>
                        <h1>CBT Questions</h1>
                        <p>Kelola bank soal CBT melalui tab terpisah agar proses tambah manual, import, dan peninjauan daftar soal terasa lebih fokus dan tidak padat dalam satu halaman panjang.</p>
                    </div>
                    <div class="cbt-questions-overview" data-cbt-questions-refresh-area="overview" aria-hidden="true">
                        <span class="cbt-questions-pill"><?php echo esc_html(sprintf('Total: %d soal', $total_questions)); ?></span>
                        <span class="cbt-questions-pill"><?php echo esc_html($question_scope_label); ?></span>
                        <span class="cbt-questions-pill"><?php echo esc_html(!empty($editing_question) ? 'Mode edit aktif' : (is_array($question_import_state) ? 'Import berjalan' : 'Input siap')); ?></span>
                    </div>
                </section>

                <div class="cbt-questions-notices" data-cbt-questions-refresh-area="notices">
                    <?php if ($notice): ?>
                        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <?php $error_messages = array_values(array_filter(array_map('trim', explode('||', (string) $error)))); ?>
                        <div class="notice notice-error is-dismissible">
                            <?php if (count($error_messages) <= 1): ?>
                                <p><?php echo esc_html($error); ?></p>
                            <?php else: ?>
                                <p><strong>Detail error terbaru:</strong></p>
                                <ul style="margin:0 0 0 1.2rem; list-style:disc;">
                                    <?php foreach ($error_messages as $error_message): ?>
                                        <li><?php echo esc_html($error_message); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($lock_question_type): ?>
                        <div class="notice notice-info"><p>
                            Submenu aktif: <strong><?php echo esc_html((string) ($question_type_labels[$active_question_type] ?? $active_question_type)); ?></strong>.
                            Form dan daftar soal difilter ke jenis ini.
                        </p></div>
                    <?php endif; ?>
                </div>

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

                    .cbt-questions-page {
            max-width: 1280px;
            margin: 20px auto;
            padding: 24px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--cbt-text-main);
            background: radial-gradient(circle at top left, #e0e7ff 0%, #f8fafc 40%, #f0fdf4 100%);
            border-radius: var(--cbt-radius-lg);
            box-sizing: border-box;
        }
        .cbt-questions-page * {
            box-sizing: border-box;
        }
            @keyframes cbtSlideUp {
                0% { opacity: 0; transform: translateY(15px); }
                100% { opacity: 1; transform: translateY(0); }
            }
                    
        .cbt-questions-shell::before {
            content: ''; position: absolute; top: -150px; left: -100px; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-questions-shell::after {
            content: ''; position: absolute; bottom: -100px; right: -50px; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-questions-shell {
                        display: grid;
                        gap: 18px;
                        margin-top: 18px;
                    
            position: relative;
            z-index: 1;
            isolation: isolate;
        }
                    .cbt-questions-hero {
                        position: relative;
                    overflow: hidden;
                    display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 22px;
                        
                        
                        
                        

                        
                        
                
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
                .cbt-questions-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
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
                        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);
                        color: #ffffff;
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
                        border-color: #ffffff;
                        background: #3b82f6;
                        color: #ffffff;
                        box-shadow: 0 10px 24px rgba(34, 113, 177, 0.18);
                    }
                    .cbt-questions-panel {
                        display: none;
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
                    .cbt-question-type-picker {
                        display: grid;
                        gap: 12px;
                        width: min(100%, 980px);
                        padding: 14px;
                        border: 1px solid #d7e3ef;
                        border-radius: 18px;
                        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    }
                    .cbt-question-type-picker-head {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        flex-wrap: wrap;
                    }
                    .cbt-question-type-picker-head strong {
                        color: #0f172a;
                        font-size: 14px;
                    }
                    .cbt-question-type-current {
                        display: inline-flex;
                        align-items: center;
                        min-height: 28px;
                        padding: 0 10px;
                        border: 1px solid #bfdbfe;
                        border-radius: 999px;
                        background: #eff6ff;
                        color: #1d4ed8;
                        font-size: 12px;
                        font-weight: 800;
                    }
                    .cbt-question-type-tabs {
                        gap: 6px;
                        margin: 0;
                        padding: 6px;
                        border: 1px solid #dbe6f3;
                        border-radius: 16px;
                        background: #ffffff;
                    }
                    .cbt-question-type-tabs .button {
                        min-height: 38px;
                        padding: 0 12px;
                        border-radius: 11px;
                        box-shadow: none;
                    }
                    @media (max-width: 782px) {
                        .cbt-question-type-tabs {
                            flex-wrap: nowrap;
                            overflow-x: auto;
                            scrollbar-width: thin;
                        }
                        .cbt-question-type-tabs .button {
                            flex: 0 0 auto;
                        }
                    }
                    .cbt-questions-panel .form-table {
                        margin: 0;
                        border-collapse: separate;
                        border-spacing: 0 12px;
                    }
                    .cbt-questions-panel .form-table th {
                        width: 172px;
                        padding: 8px 14px 0 0;
                        vertical-align: top;
                        color: #0f172a;
                        font-size: 13px;
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
                        min-height: 42px;
                        padding: 0 13px;
                    }
                    .cbt-questions-panel select {
                        appearance: none;
                        -webkit-appearance: none;
                        -moz-appearance: none;
                        padding-right: 40px;
                        cursor: pointer;
                        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6.5L8 10.5L12 6.5' stroke='%23546A85' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                        background-repeat: no-repeat;
                        background-position: right 13px center;
                        background-size: 16px 16px;
                    }
                    .cbt-questions-panel select:disabled {
                        cursor: not-allowed;
                        background-color: #eef4fb;
                    }
                    .cbt-questions-panel input[type="number"] {
                        min-height: 42px;
                        padding: 0 10px;
                        width: 112px;
                    }
                    .cbt-questions-panel input[type="file"] {
                        min-height: 42px;
                        padding: 8px 12px;
                        border: 1px dashed #c9d7e6;
                        border-radius: 16px;
                        background: #f8fbff;
                        width: min(100%, 720px);
                        box-sizing: border-box;
                    }
                    .cbt-questions-panel textarea {
                        width: min(100%, 960px);
                        min-height: 96px;
                        padding: 12px 14px;
                    }
                    .cbt-questions-panel .regular-text,
                    .cbt-questions-panel .large-text {
                        width: min(100%, 720px);
                        max-width: none;
                    }
                    .cbt-questions-panel .description,
                    .cbt-questions-panel .cbt-inline-help {
                        margin-top: 7px;
                        color: #64748b;
                        font-size: 12px;
                        line-height: 1.5;
                    }
                    .cbt-questions-panel .wp-editor-wrap {
                        max-width: 980px;
                    }
                    .cbt-questions-panel .wp-editor-container {
                        border: 1px solid #cfdbe8;
                        border-radius: 14px;
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
                        padding: 0 0 6px;
                    }
                    .cbt-questions-panel .insert-media {
                        border-radius: 10px;
                    }
                    .cbt-question-editor-box {
                        display: grid;
                        gap: 9px;
                        width: min(100%, 980px);
                        padding: 12px;
                        border: 1px solid #d7e3ef;
                        border-radius: 14px;
                        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.045);
                        box-sizing: border-box;
                    }
                    .cbt-question-editor-box .wp-editor-wrap {
                        max-width: none;
                    }
                    .cbt-question-editor-box .wp-editor-tools {
                        display: flex;
                        align-items: flex-start;
                        gap: 8px;
                        flex-wrap: wrap;
                        padding: 0 0 7px;
                    }
                    .cbt-question-editor-box .wp-media-buttons {
                        display: flex;
                        gap: 6px;
                        flex-wrap: wrap;
                    }
                    .cbt-question-editor-box .wp-editor-tabs {
                        margin-left: auto;
                    }
                    .cbt-question-editor-box .wp-editor-container {
                        border-radius: 12px;
                    }
                    .cbt-question-editor-box .description {
                        margin: 0;
                    }
                    .cbt-question-inline-preview :where(table) {
                        margin: 0.45em 0;
                        border-collapse: collapse;
                        border-spacing: 0;
                        background: #fff;
                        border: 1px solid #d6deea;
                    }
                    .cbt-question-inline-preview :where(th, td) {
                        border: 1px solid #d6deea;
                        padding: 8px 10px;
                        vertical-align: top;
                    }
                    .cbt-question-inline-preview :where(th) {
                        background: #f8fbff;
                        color: #0f172a;
                        font-weight: 700;
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
                        gap: 6px;
                        max-width: 900px;
                        padding: 10px;
                        border: 1px solid #dfe7ef;
                        border-radius: 14px;
                        background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
                    }
                    .cbt-option-row {
                        display: flex;
                        align-items: flex-start;
                        gap: 7px;
                        padding: 9px 10px;
                        border: 1px solid #e5ebf2;
                        border-radius: 11px;
                        background: #fff;
                    }
                    .cbt-option-row label {
                        width: 76px;
                        padding-top: 8px;
                        color: #0f172a;
                        font-size: 12px;
                        font-weight: 700;
                    }
                    .cbt-option-row label.cbt-inline-check {
                        width: auto;
                        padding-top: 9px;
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
                    .cbt-manual-row-hidden,
                    .cbt-manual-option-hidden,
                    .cbt-cloze-tag-button.is-hidden,
                    .cbt-short-answer-tag-button.is-hidden {
                        display: none !important;
                    }
                    .cbt-authoring-panel {
                        display: grid;
                        gap: 10px;
                        width: min(100%, 1080px);
                        padding: 12px;
                        border: 1px solid #d7e3ef;
                        border-radius: 14px;
                        background:
                            linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 255, 0.96) 100%);
                        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
                    }
                    .cbt-authoring-panel-head {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        flex-wrap: wrap;
                        padding-bottom: 8px;
                        border-bottom: 1px solid #e3ebf4;
                    }
                    .cbt-authoring-panel-head > div:first-child {
                        flex: 1 1 280px;
                        min-width: 0;
                    }
                    .cbt-authoring-panel-head strong {
                        display: block;
                        margin-bottom: 2px;
                        color: #0f172a;
                        font-size: 14px;
                        line-height: 1.35;
                    }
                    .cbt-authoring-panel-head p {
                        margin: 0;
                        color: #64748b;
                        font-size: 12px;
                        line-height: 1.45;
                    }
                    .cbt-manual-panel-actions {
                        display: flex;
                        align-items: center;
                        justify-content: flex-end;
                        gap: 6px;
                        flex-wrap: wrap;
                        flex: 0 1 auto;
                        margin-left: auto;
                    }
                    .cbt-qtype-panel > td > .cbt-manual-panel-actions {
                        justify-content: flex-start;
                        width: min(100%, 900px);
                        margin: 0 0 8px;
                        padding: 8px 10px;
                        border: 1px solid #dfe7ef;
                        border-radius: 14px;
                        background: #ffffff;
                    }
                    .cbt-manual-count-control {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        min-width: 0;
                        color: #334155;
                        font-size: 11px;
                        font-weight: 700;
                        white-space: nowrap;
                    }
                    .cbt-manual-count-control select {
                        min-height: 34px;
                        min-width: 70px;
                        padding: 0 32px 0 10px;
                        border-radius: 10px;
                    }
                    .cbt-manual-count-step.button {
                        min-height: 34px;
                        padding: 0 10px;
                        border-radius: 10px;
                    }
                    .cbt-manual-hidden-warning {
                        display: none;
                        flex-basis: 100%;
                        margin: -1px 0 0;
                        color: #b45309;
                        font-size: 11px;
                        font-weight: 700;
                        line-height: 1.25;
                        text-align: right;
                    }
                    .cbt-manual-hidden-warning.is-visible {
                        display: block;
                    }
                    .cbt-manual-panel-actions + .cbt-option-list {
                        margin-top: 0;
                    }
                    .cbt-authoring-badge {
                        display: inline-flex;
                        align-items: center;
                        min-height: 26px;
                        padding: 0 10px;
                        border: 1px solid #bfdbfe;
                        border-radius: 999px;
                        background: #eff6ff;
                        color: #1d4ed8;
                        font-size: 11px;
                        font-weight: 700;
                        white-space: nowrap;
                    }
                    .cbt-matching-author-grid,
                    .cbt-cloze-author-grid {
                        display: grid;
                        gap: 8px;
                    }
                    .cbt-matching-author-row {
                        display: grid;
                        grid-template-columns: 44px minmax(230px, 1fr) 30px minmax(230px, 1fr);
                        gap: 8px;
                        align-items: start;
                        padding: 10px;
                        border: 1px solid #e1eaf3;
                        border-radius: 12px;
                        background: #ffffff;
                    }
                    .cbt-author-row-index {
                        display: grid;
                        place-items: center;
                        gap: 3px;
                        min-height: 44px;
                        border: 1px solid #dbeafe;
                        border-radius: 11px;
                        background: #f8fbff;
                        color: #1e40af;
                    }
                    .cbt-author-row-index span {
                        font-size: 15px;
                        font-weight: 800;
                        line-height: 1;
                    }
                    .cbt-author-row-index small {
                        color: #64748b;
                        font-size: 10px;
                        font-weight: 700;
                        letter-spacing: 0.05em;
                        text-transform: uppercase;
                    }
                    .cbt-matching-author-left,
                    .cbt-matching-author-right {
                        min-width: 0;
                    }
                    .cbt-matching-author-left > label,
                    .cbt-matching-author-right > label,
                    .cbt-cloze-correct-row > label,
                    .cbt-cloze-option-field > span {
                        display: block;
                        margin: 0 0 5px;
                        color: #334155;
                        font-size: 12px;
                        font-weight: 700;
                        letter-spacing: 0.02em;
                    }
                    .cbt-matching-author-left .wp-editor-wrap,
                    .cbt-matching-author-right .wp-editor-wrap {
                        max-width: none;
                    }
                    .cbt-matching-author-left .wp-editor-container,
                    .cbt-matching-author-right .wp-editor-container {
                        border-radius: 11px;
                    }
                    .cbt-matching-author-link {
                        display: inline-grid;
                        place-items: center;
                        width: 30px;
                        height: 30px;
                        margin-top: 29px;
                        border: 1px solid #dbeafe;
                        border-radius: 999px;
                        background: #eff6ff;
                        color: #1d4ed8;
                        font-size: 15px;
                        font-weight: 800;
                    }
                    .cbt-authoring-panel--cloze {
                        border-radius: 10px;
                        background: #fbfdff;
                    }
                    .cbt-cloze-tag-toolbar {
                        display: flex;
                        flex-wrap: wrap;
                        align-items: center;
                        gap: 6px;
                        padding: 8px 10px;
                        border: 1px solid #e1eaf3;
                        border-radius: 8px;
                        background: #ffffff;
                    }
                    .cbt-question-tags {
                        display: none;
                        margin-bottom: 8px;
                    }
                    .cbt-question-tags.is-active {
                        display: flex;
                    }
                    .cbt-cloze-tag-toolbar-label {
                        margin-right: 2px;
                        color: #475569;
                        font-size: 12px;
                        font-weight: 700;
                    }
                    .cbt-cloze-tag-button.button,
                    .cbt-short-answer-tag-button.button {
                        min-height: 28px;
                        padding: 0 9px;
                        border-color: #bfdbfe;
                        border-radius: 8px;
                        background: #f8fbff;
                        color: #1d4ed8;
                        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                        font-size: 11px;
                        font-weight: 800;
                        line-height: 26px;
                    }
                    .cbt-cloze-author-grid {
                        gap: 8px;
                    }
                    .cbt-cloze-author-row {
                        display: grid;
                        grid-template-columns: 100px minmax(0, 1fr) 132px;
                        gap: 8px;
                        align-items: start;
                        min-width: 0;
                        padding: 10px;
                        border: 1px solid #e1eaf3;
                        border-radius: 8px;
                        background: #ffffff;
                    }
                    .cbt-cloze-blank-meta {
                        display: grid;
                        gap: 5px;
                        align-content: start;
                        min-width: 0;
                    }
                    .cbt-cloze-blank-meta strong {
                        color: #0f172a;
                        font-size: 13px;
                        line-height: 1.3;
                    }
                    .cbt-cloze-placeholder {
                        display: inline-flex;
                        align-items: center;
                        width: fit-content;
                        max-width: 100%;
                        min-height: 24px;
                        padding: 0 7px;
                        border: 1px solid #bbf7d0;
                        border-radius: 8px;
                        background: #f0fdf4;
                        color: #047857;
                        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                        font-size: 11px;
                        font-weight: 800;
                        overflow-wrap: anywhere;
                    }
                    .cbt-cloze-options-grid {
                        display: grid;
                        grid-template-columns: repeat(3, minmax(0, 1fr));
                        gap: 7px;
                        min-width: 0;
                    }
                    .cbt-cloze-option-field {
                        display: grid;
                        gap: 0;
                        min-width: 0;
                    }
                    .cbt-cloze-option-field input[type="text"] {
                        min-width: 0;
                    }
                    .cbt-cloze-option-field input.regular-text {
                        width: 100%;
                        max-width: none;
                    }
                    .cbt-cloze-correct-row {
                        display: grid;
                        gap: 5px;
                        min-width: 0;
                    }
                    .cbt-cloze-correct-row > label {
                        margin: 0;
                    }
                    .cbt-cloze-correct-row select {
                        width: 100%;
                        min-width: 0;
                    }
                    .cbt-short-answer-grid {
                        display: grid;
                        gap: 7px;
                    }
                    .cbt-short-answer-row {
                        display: grid;
                        grid-template-columns: minmax(118px, 152px) minmax(220px, 1fr);
                        gap: 9px;
                        align-items: center;
                        min-width: 0;
                        padding: 9px 10px;
                        border: 1px solid #e1eaf3;
                        border-radius: 12px;
                        background: #ffffff;
                    }
                    .cbt-short-answer-label {
                        display: grid;
                        gap: 5px;
                    }
                    .cbt-short-answer-label strong {
                        color: #0f172a;
                        font-size: 13px;
                        line-height: 1.3;
                    }
                    .cbt-short-answer-tag {
                        display: inline-flex;
                        align-items: center;
                        width: fit-content;
                        min-height: 24px;
                        padding: 0 8px;
                        border: 1px solid #bfdbfe;
                        border-radius: 999px;
                        background: #eff6ff;
                        color: #1d4ed8;
                        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                        font-size: 11px;
                        font-weight: 800;
                    }
                    .cbt-short-answer-row input.regular-text {
                        width: 100%;
                        max-width: none;
                    }
                    .cbt-tfm-author-grid {
                        display: grid;
                        gap: 8px;
                    }
                    .cbt-tfm-author-row {
                        display: grid;
                        grid-template-columns: 44px minmax(260px, 1fr) minmax(150px, 190px);
                        gap: 8px;
                        align-items: start;
                        padding: 10px;
                        border: 1px solid #e1eaf3;
                        border-radius: 12px;
                        background: #ffffff;
                    }
                    .cbt-tfm-author-statement {
                        min-width: 0;
                    }
                    .cbt-tfm-author-statement > label,
                    .cbt-tfm-author-answer > label {
                        display: block;
                        margin: 0 0 5px;
                        color: #334155;
                        font-size: 12px;
                        font-weight: 700;
                        letter-spacing: 0.02em;
                    }
                    .cbt-tfm-author-statement .wp-editor-wrap {
                        max-width: none;
                    }
                    .cbt-tfm-author-statement .wp-editor-container {
                        border-radius: 11px;
                    }
                    .cbt-tfm-author-answer {
                        display: grid;
                        gap: 7px;
                        min-width: 0;
                    }
                    .cbt-tfm-author-answer select {
                        width: 100%;
                    }
                    .cbt-categorization-author-grid,
                    .cbt-table-author-grid {
                        display: grid;
                        gap: 8px;
                    }
                    .cbt-cat-category-grid {
                        display: grid;
                        grid-template-columns: repeat(4, minmax(140px, 1fr));
                        gap: 7px;
                    }
                    .cbt-cat-category-field,
                    .cbt-table-cell-options label {
                        display: grid;
                        gap: 4px;
                        min-width: 0;
                    }
                    .cbt-cat-category-field span,
                    .cbt-table-cell-options span,
                    .cbt-table-cell-field > label {
                        color: #334155;
                        font-size: 12px;
                        font-weight: 700;
                    }
                    .cbt-cat-category-field input,
                    .cbt-cat-item-row input,
                    .cbt-cat-item-row select,
                    .cbt-table-cell-field input,
                    .cbt-table-cell-field select {
                        width: 100%;
                        max-width: none;
                    }
                    .cbt-cat-item-row {
                        display: grid;
                        grid-template-columns: 44px minmax(220px, 1fr) minmax(140px, 190px);
                        gap: 8px;
                        align-items: center;
                        padding: 9px 10px;
                        border: 1px solid #e1eaf3;
                        border-radius: 11px;
                        background: #ffffff;
                    }
                    .cbt-table-size-row {
                        display: flex;
                        gap: 8px;
                        flex-wrap: wrap;
                        align-items: end;
                        width: fit-content;
                        max-width: 100%;
                        padding: 0;
                        border: 0;
                        border-radius: 0;
                        background: transparent;
                    }
                    .cbt-table-size-row label {
                        display: grid;
                        gap: 3px;
                        min-width: 104px;
                        color: #334155;
                        font-size: 12px;
                        font-weight: 700;
                    }
                    .cbt-table-size-row select {
                        min-height: 36px;
                    }
                    .cbt-table-designer {
                        display: grid;
                        grid-template-columns: 1fr;
                        gap: 10px;
                        align-items: start;
                    }
                    .cbt-table-grid-area {
                        display: grid;
                        gap: 7px;
                        min-width: 0;
                    }
                    .cbt-table-designer-summary {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 6px;
                        align-items: center;
                    }
                    .cbt-table-designer-summary span,
                    .cbt-table-side-chip {
                        display: inline-flex;
                        align-items: center;
                        min-height: 26px;
                        padding: 0 9px;
                        border: 1px solid #cfe0f5;
                        border-radius: 999px;
                        background: #f8fbff;
                        color: #1e3a8a;
                        font-size: 11px;
                        font-weight: 800;
                    }
                    .cbt-table-author-scroll {
                        overflow-x: auto;
                        padding: 7px;
                        border: 1px solid #dbe6f3;
                        border-radius: 12px;
                        background: #f8fbff;
                    }
                    .cbt-table-author-matrix {
                        display: grid;
                        gap: 6px;
                        min-width: min(100%, calc(var(--cbt-table-cols, 3) * 104px));
                    }
                    .cbt-table-author-row {
                        display: grid;
                        grid-template-columns: repeat(var(--cbt-table-cols, 3), minmax(96px, 1fr));
                        gap: 6px;
                    }
                    .cbt-table-author-row.is-hidden,
                    .cbt-table-cell-field.is-hidden {
                        display: none !important;
                    }
                    .cbt-table-cell-field {
                        min-width: 0;
                    }
                    .cbt-table-cell-button {
                        display: grid;
                        gap: 4px;
                        width: 100%;
                        min-height: 64px;
                        padding: 8px;
                        border: 1px solid #dbe6f3;
                        border-left: 3px solid #cbd5e1;
                        border-radius: 11px;
                        background: #ffffff;
                        color: #0f172a;
                        text-align: left;
                        cursor: pointer;
                        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.82);
                        transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
                    }
                    .cbt-table-cell-button:hover,
                    .cbt-table-cell-button:focus {
                        border-color: #93c5fd;
                        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
                        outline: none;
                    }
                    .cbt-table-cell-field.is-selected .cbt-table-cell-button {
                        border-color: #2563eb;
                        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16);
                    }
                    .cbt-table-cell-field.is-text .cbt-table-cell-button {
                        border-left-color: #2563eb;
                        background: #fbfdff;
                    }
                    .cbt-table-cell-field.is-dropdown .cbt-table-cell-button {
                        border-left-color: #0f9f6e;
                        background: #fbfffd;
                    }
                    .cbt-table-cell-top {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 6px;
                    }
                    .cbt-table-cell-head {
                        display: grid;
                        grid-template-columns: auto minmax(0, 1fr);
                        align-items: center;
                        gap: 6px;
                    }
                    .cbt-table-cell-key {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 26px;
                        height: 26px;
                        border-radius: 8px;
                        background: #eef4ff;
                        color: #1d4ed8;
                        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                        font-size: 11px;
                        font-weight: 800;
                    }
                    .cbt-table-cell-type {
                        min-width: 0;
                    }
                    .cbt-table-cell-type-label {
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                        color: #475569;
                        font-size: 11px;
                        font-weight: 800;
                    }
                    .cbt-table-cell-summary {
                        display: -webkit-box;
                        min-height: 16px;
                        overflow: hidden;
                        color: #334155;
                        font-size: 11px;
                        font-weight: 700;
                        line-height: 1.35;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                    }
                    .cbt-table-cell-hidden-inputs {
                        display: none !important;
                    }
                    .cbt-table-side-panel {
                        position: static;
                        display: grid;
                        gap: 8px;
                        padding: 10px;
                        border: 1px solid #dbe6f3;
                        border-radius: 12px;
                        background: #ffffff;
                        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
                    }
                    .cbt-table-author-matrix > .cbt-table-side-panel {
                        width: 100%;
                        margin: 2px 0 4px;
                    }
                    .cbt-table-side-head {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 8px;
                        padding-bottom: 7px;
                        border-bottom: 1px solid #e6eef7;
                    }
                    .cbt-table-side-title {
                        display: grid;
                        gap: 2px;
                        min-width: 0;
                    }
                    .cbt-table-side-title strong {
                        color: #0f172a;
                        font-size: 14px;
                        line-height: 1.2;
                    }
                    .cbt-table-side-title span {
                        overflow-wrap: anywhere;
                        color: #64748b;
                        font-size: 11px;
                        font-weight: 700;
                    }
                    .cbt-table-side-fields,
                    .cbt-table-side-field {
                        display: grid;
                        gap: 6px;
                    }
                    .cbt-table-side-fields {
                        grid-template-columns: minmax(150px, 190px) minmax(240px, 1fr);
                        align-items: start;
                    }
                    .cbt-table-side-field[data-cbt-table-panel-mode="text"],
                    .cbt-table-side-field[data-cbt-table-panel-mode="dropdown"],
                    .cbt-table-side-field.cbt-table-side-field--wide {
                        grid-column: 1 / -1;
                    }
                    .cbt-table-side-field[hidden],
                    [data-cbt-table-panel-mode][hidden] {
                        display: none !important;
                    }
                    .cbt-table-side-field > span,
                    .cbt-table-side-options-head {
                        color: #334155;
                        font-size: 12px;
                        font-weight: 800;
                    }
                    .cbt-table-side-options {
                        display: grid;
                        grid-template-columns: repeat(3, minmax(140px, 1fr));
                        gap: 7px;
                    }
                    .cbt-table-cell-options {
                        display: grid;
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                        gap: 5px;
                    }
                    .cbt-table-cell-options-head {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 6px;
                        color: #334155;
                        font-size: 12px;
                        font-weight: 800;
                    }
                    .cbt-table-cell-options-head span:last-child {
                        color: #64748b;
                        font-size: 11px;
                        font-weight: 700;
                    }
                    .cbt-authoring-panel input[type="text"],
                    .cbt-authoring-panel select,
                    .cbt-option-list input[type="text"],
                    .cbt-option-list select {
                        min-height: 38px;
                        border-radius: 11px;
                    }
                    .cbt-table-cell-options input[type="text"] {
                        min-height: 34px;
                        padding: 0 8px;
                        border-radius: 9px;
                    }
                    .cbt-table-side-options input[type="text"] {
                        min-height: 32px;
                        padding: 0 8px;
                        border-radius: 9px;
                    }
                    .cbt-authoring-panel--table {
                        width: min(100%, 980px);
                    }
                    .cbt-authoring-panel--table .cbt-authoring-panel-head {
                        gap: 8px;
                        padding-bottom: 7px;
                    }
                    .cbt-authoring-panel--table .cbt-authoring-panel-head strong {
                        margin-bottom: 1px;
                    }
                    .cbt-authoring-panel--table .cbt-authoring-panel-head p {
                        max-width: 680px;
                    }
                    .cbt-authoring-panel--table .cbt-authoring-badge {
                        min-height: 26px;
                    }
                    .cbt-authoring-panel--table input[type="text"],
                    .cbt-authoring-panel--table select {
                        min-height: 36px;
                    }
                    .cbt-option-row .wp-editor-tools,
                    .cbt-matching-author-row .wp-editor-tools,
                    .cbt-tfm-author-row .wp-editor-tools {
                        padding-bottom: 4px;
                    }
                    .cbt-option-row .wp-editor-container,
                    .cbt-matching-author-row .wp-editor-container,
                    .cbt-tfm-author-row .wp-editor-container {
                        border-radius: 10px;
                    }
                    .cbt-option-row .wp-editor-tabs .wp-switch-editor,
                    .cbt-matching-author-row .wp-editor-tabs .wp-switch-editor,
                    .cbt-tfm-author-row .wp-editor-tabs .wp-switch-editor {
                        height: 27px;
                        padding: 4px 8px;
                        font-size: 12px;
                    }
                    @media (max-width: 980px) {
                        .cbt-matching-author-row {
                            grid-template-columns: 44px 1fr;
                        }
                        .cbt-matching-author-link {
                            display: none;
                        }
                        .cbt-matching-author-right {
                            grid-column: 2;
                        }
                        .cbt-tfm-author-row {
                            grid-template-columns: 44px 1fr;
                        }
                        .cbt-tfm-author-answer {
                            grid-column: 2;
                        }
                        .cbt-cloze-author-row {
                            grid-template-columns: 96px minmax(0, 1fr);
                        }
                        .cbt-cloze-correct-row {
                            grid-column: 2;
                        }
                        .cbt-cloze-options-grid {
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                        }
                        .cbt-cat-category-grid {
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                        }
                        .cbt-cat-item-row {
                            grid-template-columns: 44px 1fr;
                        }
                        .cbt-table-side-fields,
                        .cbt-table-side-options {
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                        }
                    }
                    @media (max-width: 640px) {
                        .cbt-authoring-panel {
                            padding: 10px;
                        }
                        .cbt-matching-author-row {
                            grid-template-columns: 1fr;
                        }
                        .cbt-author-row-index,
                        .cbt-matching-author-right,
                        .cbt-tfm-author-answer {
                            grid-column: auto;
                        }
                        .cbt-author-row-index {
                            width: 44px;
                        }
                        .cbt-cloze-author-grid,
                        .cbt-cloze-options-grid,
                        .cbt-cat-category-grid,
                        .cbt-cat-item-row,
                        .cbt-short-answer-row,
                        .cbt-tfm-author-row,
                        .cbt-cloze-author-row {
                            grid-template-columns: 1fr;
                        }
                        .cbt-cloze-correct-row {
                            grid-column: auto;
                        }
                        .cbt-table-side-fields,
                        .cbt-table-side-options {
                            grid-template-columns: 1fr;
                        }
                    }
                    .cbt-questions-actions,
                    .cbt-questions-form-actions,
                    .cbt-questions-list-actions {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        flex-wrap: wrap;
                    }
                    .cbt-word-template-control {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                    }
                    .cbt-word-template-control[hidden] {
                        display: none;
                    }
                    .cbt-word-template-control select {
                        min-width: 72px;
                    }
                    .cbt-questions-form-actions {
                        position: sticky;
                        bottom: 0;
                        z-index: 20;
                        margin-top: 12px;
                        padding: 9px 0;
                        border-top: 1px solid #dbe6f3;
                        background: rgba(246, 248, 250, 0.94);
                        backdrop-filter: blur(8px);
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
                    .cbt-questions-local-progress {
                        display: none;
                        gap: 10px;
                        padding: 14px 16px;
                        border: 1px solid #bfdbfe;
                        border-radius: 18px;
                        background: linear-gradient(135deg, rgba(239, 246, 255, 0.97), rgba(240, 253, 250, 0.92));
                        box-shadow: 0 14px 30px rgba(37, 99, 235, 0.12);
                    }
                    .cbt-questions-local-progress.is-active {
                        display: grid;
                    }
                    .cbt-questions-local-progress.is-error {
                        border-color: #fecaca;
                        background: linear-gradient(135deg, rgba(254, 242, 242, 0.98), rgba(255, 247, 237, 0.94));
                        box-shadow: 0 14px 30px rgba(239, 68, 68, 0.10);
                    }
                    .cbt-questions-local-progress-head {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 14px;
                        flex-wrap: wrap;
                    }
                    .cbt-questions-local-progress-title {
                        display: grid;
                        gap: 3px;
                        min-width: 0;
                    }
                    .cbt-questions-local-progress-title strong {
                        color: #0f172a;
                        font-size: 14px;
                        line-height: 1.25;
                    }
                    .cbt-questions-local-progress-title span,
                    .cbt-questions-local-progress-step {
                        color: #52637a;
                        font-size: 13px;
                        line-height: 1.45;
                    }
                    .cbt-questions-local-progress-percent {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-width: 54px;
                        min-height: 30px;
                        padding: 0 10px;
                        border: 1px solid #bfdbfe;
                        border-radius: 999px;
                        background: #ffffff;
                        color: #1d4ed8;
                        font-size: 12px;
                        font-weight: 800;
                        letter-spacing: 0.04em;
                    }
                    .cbt-questions-local-progress.is-error .cbt-questions-local-progress-percent {
                        color: #b91c1c;
                        border-color: #fecaca;
                    }
                    .cbt-questions-local-progress-track {
                        height: 9px;
                        overflow: hidden;
                        border-radius: 999px;
                        background: rgba(148, 163, 184, 0.22);
                    }
                    .cbt-questions-local-progress-fill {
                        display: block;
                        width: var(--cbt-questions-progress, 0%);
                        height: 100%;
                        border-radius: inherit;
                        background: linear-gradient(90deg, #2563eb 0%, #06b6d4 54%, #10b981 100%);
                        transition: width 0.24s ease;
                    }
                    .cbt-questions-local-progress.is-error .cbt-questions-local-progress-fill {
                        background: linear-gradient(90deg, #ef4444 0%, #f97316 100%);
                    }
                    .cbt-questions-local-progress-step {
                        margin: 0;
                        font-weight: 600;
                    }
                    .cbt-questions-panel .button.is-loading,
                    .cbt-questions-row-action.is-loading {
                        pointer-events: none;
                        opacity: 0.78;
                    }
                    .cbt-import-batch-analysis {
                        margin: 14px 0 18px;
                        padding: 16px 18px;
                        border: 1px solid #dbe5ef;
                        border-radius: 18px;
                        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
                    }
                    .cbt-import-batch-analysis-head {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 12px;
                        flex-wrap: wrap;
                        margin-bottom: 12px;
                    }
                    .cbt-import-batch-analysis-head p {
                        margin: 4px 0 0;
                        color: #4b5563;
                    }
                    .cbt-import-batch-analysis-layout {
                        display: grid;
                        grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
                        gap: 16px;
                    }
                    .cbt-import-batch-analysis-nav {
                        display: grid;
                        gap: 10px;
                        max-height: 640px;
                        overflow-y: auto;
                        padding-right: 4px;
                    }
                    .cbt-import-batch-analysis-nav-item {
                        width: 100%;
                        text-align: left;
                        padding: 14px 15px;
                        border: 1px solid #dbe5ef;
                        border-radius: 14px;
                        background: #fff;
                        cursor: pointer;
                        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
                    }
                    .cbt-import-batch-analysis-nav-item:hover,
                    .cbt-import-batch-analysis-nav-item.is-active {
                        border-color: #9ec3ea;
                        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
                        transform: translateY(-1px);
                    }
                    .cbt-import-batch-analysis-nav-title {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 8px;
                        margin-bottom: 6px;
                    }
                    .cbt-import-batch-analysis-nav-meta {
                        color: #64748b;
                        font-size: 12px;
                        margin-bottom: 6px;
                    }
                    .cbt-import-batch-analysis-nav-preview {
                        color: #334155;
                        line-height: 1.55;
                    }
                    .cbt-import-batch-analysis-status--needs-review {
                        background: #fff7ed;
                        color: #9a3412;
                    }
                    .cbt-import-batch-analysis-status--safe {
                        background: #ecfbf4;
                        color: #0f766e;
                    }
                    .cbt-import-batch-analysis-detail {
                        min-width: 0;
                    }
                    .cbt-import-batch-analysis-detail-panel {
                        display: none;
                        padding: 16px 18px;
                        border: 1px solid #dbe5ef;
                        border-radius: 16px;
                        background: rgba(255,255,255,0.96);
                    }
                    .cbt-import-batch-analysis-detail-panel.is-active {
                        display: block;
                    }
                    .cbt-import-batch-analysis-preview {
                        margin: 0 0 12px;
                        color: #1f2937;
                        line-height: 1.65;
                    }
                    .cbt-import-batch-analysis-actions {
                        display: flex;
                        gap: 10px;
                        flex-wrap: wrap;
                        margin-bottom: 14px;
                    }
                    .cbt-import-batch-analysis-actions .button {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        text-align: center;
                        line-height: 1.2;
                        padding: 0 16px;
                    }
                    .cbt-import-batch-analysis-actions--footer {
                        margin-top: 22px;
                        padding-top: 14px;
                        border-top: 1px solid #e5edf5;
                    }
                    .cbt-import-batch-analysis-actions--footer .button {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        text-align: center;
                        line-height: 1.2;
                        padding: 0 16px;
                    }
                    .cbt-import-batch-analysis-filters {
                        display: flex;
                        gap: 8px;
                        flex-wrap: wrap;
                        margin: 0 0 14px;
                    }
                    .cbt-import-batch-analysis-filters .button.is-active {
                        background: #2271b1;
                        border-color: #2271b1;
                        color: #fff;
                    }
                    .cbt-import-batch-analysis-items {
                        display: grid;
                        gap: 8px;
                    }
                    .cbt-import-batch-analysis-item {
                        padding: 10px 12px;
                        border: 1px solid #e5edf5;
                        border-radius: 12px;
                        background: #fff;
                    }
                    .cbt-import-batch-analysis-item[hidden] {
                        display: none !important;
                    }
                    .cbt-import-batch-analysis-item-meta {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        flex-wrap: wrap;
                        margin-bottom: 6px;
                    }
                    .cbt-import-batch-analysis-item-message {
                        color: #334155;
                        line-height: 1.6;
                    }
                    .cbt-import-batch-analysis-empty,
                    .cbt-import-batch-analysis-note {
                        color: #475569;
                        line-height: 1.6;
                    }
                    @media (max-width: 960px) {
                        .cbt-import-batch-analysis-layout {
                            grid-template-columns: 1fr;
                        }
                        .cbt-import-batch-analysis-nav {
                            max-height: 320px;
                        }
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
                    .cbt-question-inline-preview-question .cbt-rich-spacer {
                        display: block;
                        height: 0.95em;
                    }
                    .cbt-question-inline-preview-question > img,
                    .cbt-question-inline-preview-question figure.cbt-pasted-image-block > img,
                    .cbt-question-inline-preview-question p > img:only-child,
                    .cbt-question-inline-preview-question div > img:only-child {
                        display: block;
                        max-width: 100%;
                        height: auto;
                        margin: 0.55em 0;
                        border-radius: 8px;
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
                        flex-direction: column;
                        align-items: stretch;
                        gap: 6px;
                        max-width: 128px;
                    }
                    .cbt-questions-row-action {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 28px;
                        padding: 0 10px;
                        border: 1px solid #d9e2ec;
                        border-radius: 999px;
                        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                        text-decoration: none;
                        font-size: 11px;
                        font-weight: 800;
                        box-shadow: none;
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
                    <?php echo CBT_Admin_Questions_Helper::get_admin_student_preview_css(); ?>
                </style>

                <div class="cbt-questions-tabs" role="tablist" aria-label="Navigasi CBT Questions">
                    <button type="button" class="cbt-questions-tab" data-cbt-questions-tab="form" role="tab" aria-selected="false">Form Question</button>
                    <button type="button" class="cbt-questions-tab" data-cbt-questions-tab="import" role="tab" aria-selected="false">Import Questions</button>
                    <button type="button" class="cbt-questions-tab" data-cbt-questions-tab="list" role="tab" aria-selected="false">Question List</button>
                </div>

                <div class="cbt-questions-local-progress" data-cbt-questions-progress role="status" aria-live="polite" aria-hidden="true">
                    <div class="cbt-questions-local-progress-head">
                        <div class="cbt-questions-local-progress-title">
                            <strong data-cbt-questions-progress-label>Menunggu aksi CBT Questions...</strong>
                            <span>Progress ini memperbarui area Questions yang terdampak saja, tanpa reload halaman global.</span>
                        </div>
                        <span class="cbt-questions-local-progress-percent" data-cbt-questions-progress-percent>0%</span>
                    </div>
                    <div class="cbt-questions-local-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-cbt-questions-progress-track>
                        <span class="cbt-questions-local-progress-fill" data-cbt-questions-progress-fill></span>
                    </div>
                    <p class="cbt-questions-local-progress-step" data-cbt-questions-progress-step>Siap memproses perubahan soal.</p>
                </div>

                <section class="cbt-questions-panel" data-cbt-questions-panel="form" data-cbt-questions-refresh-area="form-panel" role="tabpanel">
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
                            <div class="cbt-question-inline-preview-question"><?php echo CBT_Admin_Questions_Helper::render_editor_html((string) ($editing_question['question_text'] ?? '')); ?></div>
                        </div>
                    <?php else: ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-question-manual-form" data-cbt-questions-tab-submit="form" data-cbt-questions-async-form data-cbt-questions-progress-profile="save" data-cbt-questions-refresh-areas="notices,overview,list-panel" data-cbt-questions-success-tab="list">
                    <?php wp_nonce_field('cbt_save_question'); ?>
                    <?php
                    $question_editor_content_style = implode('', [
                        'body.mce-content-body table{margin:0.45em 0;border-collapse:collapse;border-spacing:0;border:1px solid #cfdbe8;background:#ffffff;max-width:100%;}',
                        'body.mce-content-body th,body.mce-content-body td{border:1px solid #cfdbe8;padding:8px 10px;vertical-align:top;word-break:break-word;}',
                        'body.mce-content-body th{background:#f8fbff;color:#0f172a;font-weight:700;}',
                        'body.mce-content-body figure.cbt-pasted-image-block{margin:0.75em 0;}',
                        'body.mce-content-body figure.cbt-pasted-image-block img{display:block;max-width:100%;height:auto;margin:0;}',
                    ]);
                    $question_editor_tinymce = [
                        'content_style' => $question_editor_content_style,
                    ];
                    ?>
                    <input type="hidden" name="action" value="cbt_save_question" />
                    <input type="hidden" name="return_page" value="<?php echo esc_attr($current_page_slug); ?>" />
                    <input type="hidden" name="id" value="<?php echo esc_attr($editing_question['id'] ?? 0); ?>" />
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr(($editing_question && !$editing_question_is_bank_exam) ? (int) ($editing_question['exam_id'] ?? 0) : 0); ?>" />
                    <input type="hidden" id="cbt-question-type-hidden" name="question_type" value="<?php echo esc_attr($editing_type); ?>" />
                    <input type="hidden" id="cbt-correct-text-hidden" name="correct_text" value="<?php echo esc_attr($editing_type === 'short_answer' ? $editing_short_answer_payload : ($editing_type === 'true_false' ? $tf_correct : ($editing_type === 'true_false_matrix' ? $editing_tf_matrix_payload : ''))); ?>" />
                    <input type="hidden" id="cbt-validation-meta-hidden" name="validation_meta" value="" />
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
                                    <div class="cbt-question-type-picker">
                                        <div class="cbt-question-type-picker-head">
                                            <strong>Pilih mode soal</strong>
                                            <span class="cbt-question-type-current" id="cbt-question-type-current"><?php echo esc_html((string) ($question_type_labels[$editing_type] ?? $editing_type)); ?></span>
                                        </div>
                                        <div class="cbt-tab-buttons cbt-question-type-tabs" id="cbt-question-type-tabs" role="tablist" aria-label="Jenis soal input manual">
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'multiple_choice' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'multiple_choice' ? ' cbt-active' : ''; ?>" data-qtype="multiple_choice">Multiple Choice</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'multiple_answer' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'multiple_answer' ? ' cbt-active' : ''; ?>" data-qtype="multiple_answer">Multiple Answer</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'true_false' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'true_false' ? ' cbt-active' : ''; ?>" data-qtype="true_false">True/False</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'true_false_matrix' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'true_false_matrix' ? ' cbt-active' : ''; ?>" data-qtype="true_false_matrix">TF Matrix</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'short_answer' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'short_answer' ? ' cbt-active' : ''; ?>" data-qtype="short_answer">Short Answer</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'essay' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'essay' ? ' cbt-active' : ''; ?>" data-qtype="essay">Essay</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'ordering' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'ordering' ? ' cbt-active' : ''; ?>" data-qtype="ordering">Ordering</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'matching' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'matching' ? ' cbt-active' : ''; ?>" data-qtype="matching">Matching</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'cloze_dropdown' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'cloze_dropdown' ? ' cbt-active' : ''; ?>" data-qtype="cloze_dropdown">Cloze Dropdown</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'categorization' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'categorization' ? ' cbt-active' : ''; ?>" data-qtype="categorization">Categorization</button>
                                            <button type="button" role="tab" aria-selected="<?php echo $editing_type === 'table_completion' ? 'true' : 'false'; ?>" class="button<?php echo $editing_type === 'table_completion' ? ' cbt-active' : ''; ?>" data-qtype="table_completion">Table Completion</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt_question_text_editor">Question</label></th>
                            <td>
                                <div class="cbt-question-editor-box">
                                    <div
                                        id="cbt-cloze-question-tags"
                                        class="cbt-cloze-tag-toolbar cbt-question-tags<?php echo $editing_type === 'cloze_dropdown' ? ' is-active' : ''; ?>"
                                        data-cbt-qtype-toolbar="cloze_dropdown"
                                        aria-label="Tag Cloze Dropdown"
                                        <?php echo $editing_type === 'cloze_dropdown' ? '' : 'hidden'; ?>
                                    >
                                        <span class="cbt-cloze-tag-toolbar-label">Tag</span>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <button type="button" class="button button-secondary cbt-cloze-tag-button" data-cbt-cloze-placeholder="[DROPDOWN_<?php echo (int) $i; ?>]" data-cbt-manual-tag-group="cloze_dropdown" data-cbt-manual-tag-index="<?php echo (int) $i; ?>">[DROPDOWN_<?php echo (int) $i; ?>]</button>
                                        <?php endfor; ?>
                                    </div>
                                    <div
                                        id="cbt-short-answer-question-tags"
                                        class="cbt-cloze-tag-toolbar cbt-question-tags<?php echo $editing_type === 'short_answer' ? ' is-active' : ''; ?>"
                                        data-cbt-qtype-toolbar="short_answer"
                                        aria-label="Tag Short Answer"
                                        <?php echo $editing_type === 'short_answer' ? '' : 'hidden'; ?>
                                    >
                                        <span class="cbt-cloze-tag-toolbar-label">Tag</span>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <?php $short_answer_tag_key = chr(64 + $i); ?>
                                            <button type="button" class="button button-secondary cbt-short-answer-tag-button" data-cbt-short-answer-placeholder="[INPUT_<?php echo esc_attr($short_answer_tag_key); ?>]" data-cbt-manual-tag-group="short_answer_input" data-cbt-manual-tag-index="<?php echo (int) $i; ?>">[INPUT_<?php echo esc_html($short_answer_tag_key); ?>]</button>
                                        <?php endfor; ?>
                                    </div>
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
                                            'tinymce' => $question_editor_tinymce,
                                        ]
                                    );
                                    ?>
                                    <p class="description">Bisa teks, tabel, dan gambar. Paste langsung dari clipboard untuk gambar kecil, lalu gunakan Add Media untuk file besar.</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt-points">Points</label></th>
                            <td><input type="number" step="0.01" min="0" id="cbt-points" name="points" value="<?php echo esc_attr($editing_question['points'] ?? '1.00'); ?>" /></td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'multiple_choice' ? ' cbt-active' : ''; ?>" data-qtype="multiple_choice">
                            <th>Multiple Choice</th>
                            <td>
                                <div class="cbt-manual-panel-actions">
                                    <label class="cbt-manual-count-control" for="cbt_mc_option_count">
                                        Jumlah Pilihan
                                        <select id="cbt_mc_option_count" name="cbt_mc_option_count" data-cbt-manual-count data-target="mc_option" data-min="3" data-max="5">
                                            <?php for ($i = 3; $i <= 5; $i++): ?>
                                                <option value="<?php echo (int) $i; ?>" <?php selected((int) $mc_active_option_count, $i); ?>><?php echo (int) $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </label>
                                    <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_mc_option_count" data-cbt-count-step="-1">-</button>
                                    <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_mc_option_count" data-cbt-count-step="1">+ Tambah</button>
                                    <span class="cbt-manual-hidden-warning" data-cbt-count-warning="mc_option">Pilihan di luar jumlah aktif tidak akan disimpan.</span>
                                </div>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="cbt-option-row" data-cbt-manual-row data-manual-row-group="mc_option" data-manual-index="<?php echo (int) $i; ?>">
                                            <label for="cbt_mc_option_<?php echo (int) $i; ?>">Pilihan <?php echo (int) $i; ?></label>
                                            <?php
                                            $mc_editor_id = 'cbt_mc_option_' . (int) $i;
                                            wp_editor(
                                                (string) ($mc_option_values[$i] ?? ''),
                                                $mc_editor_id,
                                                [
                                                    'textarea_name' => $mc_editor_id,
                                                    'textarea_rows' => 2,
                                                    'media_buttons' => true,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                    'tinymce' => $question_editor_tinymce,
                                                ]
                                            );
                                            ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi minimal 3 pilihan, maksimal 5 pilihan. Wajib pilih tepat 1 jawaban benar dan tidak boleh ada pilihan duplikat. Tiap pilihan bisa teks atau gambar. Paste langsung dari clipboard untuk gambar kecil, atau pakai Add Media untuk file besar.</p>
                                <label for="cbt-correct-mc-index">Jawaban Benar</label>
                                <select id="cbt-correct-mc-index" data-cbt-manual-option-bound="mc_option">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $mc_correct_index, $i); ?>>Pilihan <?php echo (int) $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'multiple_answer' ? ' cbt-active' : ''; ?>" data-qtype="multiple_answer">
                            <th>Multiple Answer</th>
                            <td>
                                <div class="cbt-manual-panel-actions">
                                    <label class="cbt-manual-count-control" for="cbt_ma_option_count">
                                        Jumlah Pilihan
                                        <select id="cbt_ma_option_count" name="cbt_ma_option_count" data-cbt-manual-count data-target="ma_option" data-min="3" data-max="12">
                                            <?php for ($i = 3; $i <= 12; $i++): ?>
                                                <option value="<?php echo (int) $i; ?>" <?php selected((int) $ma_active_option_count, $i); ?>><?php echo (int) $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </label>
                                    <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_ma_option_count" data-cbt-count-step="-1">-</button>
                                    <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_ma_option_count" data-cbt-count-step="1">+ Tambah</button>
                                    <span class="cbt-manual-hidden-warning" data-cbt-count-warning="ma_option">Pilihan di luar jumlah aktif tidak akan disimpan.</span>
                                </div>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <div class="cbt-option-row" data-cbt-manual-row data-manual-row-group="ma_option" data-manual-index="<?php echo (int) $i; ?>">
                                            <label for="cbt_ma_option_<?php echo (int) $i; ?>">Pilihan <?php echo (int) $i; ?></label>
                                            <?php
                                            $ma_editor_id = 'cbt_ma_option_' . (int) $i;
                                            wp_editor(
                                                (string) ($ma_option_values[$i] ?? ''),
                                                $ma_editor_id,
                                                [
                                                    'textarea_name' => $ma_editor_id,
                                                    'textarea_rows' => 2,
                                                    'media_buttons' => true,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                    'tinymce' => $question_editor_tinymce,
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
                                <p class="cbt-inline-help">Isi minimal 3 pilihan, maksimal 12 pilihan. Centang minimal 1 jawaban benar dan jangan isi pilihan duplikat. Tiap pilihan bisa teks atau gambar. Paste langsung dari clipboard untuk gambar kecil, atau pakai Add Media untuk file besar.</p>
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
                                <div class="cbt-authoring-panel cbt-authoring-panel--tfm">
                                    <div class="cbt-authoring-panel-head">
                                        <div>
                                            <strong>Pernyataan dan kunci Benar/Salah</strong>
                                            <p>Tulis pernyataan dengan editor, lalu pilih kunci untuk setiap baris yang dipakai.</p>
                                        </div>
                                        <div class="cbt-manual-panel-actions">
                                            <span class="cbt-authoring-badge">Minimal 2 pernyataan</span>
                                            <label class="cbt-manual-count-control" for="cbt_tfm_statement_count">
                                                Jumlah Pernyataan
                                                <select id="cbt_tfm_statement_count" name="cbt_tfm_statement_count" data-cbt-manual-count data-target="tfm_statement" data-min="2" data-max="10">
                                                    <?php for ($i = 2; $i <= 10; $i++): ?>
                                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $tfm_active_statement_count, $i); ?>><?php echo (int) $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </label>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_tfm_statement_count" data-cbt-count-step="-1">-</button>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_tfm_statement_count" data-cbt-count-step="1">+ Tambah</button>
                                            <span class="cbt-manual-hidden-warning" data-cbt-count-warning="tfm_statement">Pernyataan di luar jumlah aktif tidak akan disimpan.</span>
                                        </div>
                                    </div>
                                    <div class="cbt-tfm-author-grid">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <section class="cbt-tfm-author-row" data-cbt-manual-row data-manual-row-group="tfm_statement" data-manual-index="<?php echo (int) $i; ?>">
                                            <div class="cbt-author-row-index">
                                                <span><?php echo (int) $i; ?></span>
                                                <small>Baris</small>
                                            </div>
                                            <div class="cbt-tfm-author-statement">
                                                <label for="cbt-tfm-statement-<?php echo (int) $i; ?>">Pernyataan</label>
                                                <?php
                                                $tfm_editor_id = 'cbt-tfm-statement-' . (int) $i;
                                                wp_editor(
                                                    (string) ($tf_matrix_rows[$i]['text'] ?? ''),
                                                    $tfm_editor_id,
                                                    [
                                                        'textarea_name' => $tfm_editor_id,
                                                        'textarea_rows' => 2,
                                                        'media_buttons' => true,
                                                        'teeny' => true,
                                                        'quicktags' => true,
                                                        'tinymce' => $question_editor_tinymce,
                                                    ]
                                                );
                                                ?>
                                            </div>
                                            <div class="cbt-tfm-author-answer">
                                                <label for="cbt-tfm-answer-<?php echo (int) $i; ?>">Kunci</label>
                                                <select id="cbt-tfm-answer-<?php echo (int) $i; ?>">
                                                    <option value="true" <?php selected((string) ($tf_matrix_rows[$i]['answer'] ?? 'true'), 'true'); ?>>Benar</option>
                                                    <option value="false" <?php selected((string) ($tf_matrix_rows[$i]['answer'] ?? 'true'), 'false'); ?>>Salah</option>
                                                </select>
                                            </div>
                                        </section>
                                    <?php endfor; ?>
                                    </div>
                                    <p class="cbt-inline-help">Isi minimal 2 pernyataan secara berurutan dari nomor 1 tanpa loncat. Pernyataan tidak boleh duplikat. Box preview lama di samping kunci sudah dihilangkan karena pernyataan kini langsung memakai editor.</p>
                                </div>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'short_answer' ? ' cbt-active' : ''; ?>" data-qtype="short_answer">
                            <th>Short Answer</th>
                            <td>
                                <div class="cbt-authoring-panel cbt-authoring-panel--short-answer">
                                    <div class="cbt-authoring-panel-head">
                                        <div>
                                            <strong>Jawaban valid per input</strong>
                                            <p>Isi kunci untuk setiap input yang dipakai pada teks soal.</p>
                                        </div>
                                        <div class="cbt-manual-panel-actions">
                                            <span class="cbt-authoring-badge">8 input tersedia</span>
                                            <label class="cbt-manual-count-control" for="cbt_short_answer_input_count">
                                                Jumlah Input
                                                <select id="cbt_short_answer_input_count" name="cbt_short_answer_input_count" data-cbt-manual-count data-target="short_answer_input" data-min="1" data-max="8">
                                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $short_answer_active_input_count, $i); ?>><?php echo (int) $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </label>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_short_answer_input_count" data-cbt-count-step="-1">-</button>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_short_answer_input_count" data-cbt-count-step="1">+ Tambah</button>
                                            <span class="cbt-manual-hidden-warning" data-cbt-count-warning="short_answer_input">Input di luar jumlah aktif tidak akan disimpan.</span>
                                        </div>
                                    </div>
                                    <div class="cbt-short-answer-grid">
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <?php $short_answer_key = chr(64 + $i); ?>
                                        <section class="cbt-short-answer-row" data-cbt-manual-row data-manual-row-group="short_answer_input" data-manual-index="<?php echo (int) $i; ?>">
                                            <div class="cbt-short-answer-label">
                                                <strong>Input <?php echo esc_html($short_answer_key); ?></strong>
                                                <span class="cbt-short-answer-tag">[INPUT_<?php echo esc_html($short_answer_key); ?>]</span>
                                            </div>
                                            <input type="text" id="cbt-correct-sa-<?php echo (int) $i; ?>" class="regular-text" value="<?php echo esc_attr((string) ($editing_short_answer_inputs[$i] ?? '')); ?>" placeholder="Jawaban valid <?php echo esc_attr($short_answer_key); ?>" />
                                        </section>
                                    <?php endfor; ?>
                                    </div>
                                    <p class="cbt-inline-help">Gunakan toolbar tag di atas editor Question untuk menaruh [INPUT_A] sampai [INPUT_H]. Format lama [INPUT_1] sampai [INPUT_8] tetap didukung; tag tidak boleh duplikat dan jumlah tag harus sama dengan jumlah jawaban valid.</p>
                                </div>
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
                                        'tinymce' => $question_editor_tinymce,
                                    ]
                                );
                                ?>
                                <p class="description">Isi jawaban/acuan jawaban essay. Bisa paste gambar langsung dari clipboard untuk file kecil, atau gunakan Add Media untuk file besar.</p>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'ordering' ? ' cbt-active' : ''; ?>" data-qtype="ordering">
                            <th>Ordering</th>
                            <td>
                                <div class="cbt-manual-panel-actions">
                                    <label class="cbt-manual-count-control" for="cbt_ordering_item_count">
                                        Jumlah Item
                                        <select id="cbt_ordering_item_count" name="cbt_ordering_item_count" data-cbt-manual-count data-target="ordering_item" data-min="2" data-max="12">
                                            <?php for ($i = 2; $i <= 12; $i++): ?>
                                                <option value="<?php echo (int) $i; ?>" <?php selected((int) $ordering_active_item_count, $i); ?>><?php echo (int) $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </label>
                                    <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_ordering_item_count" data-cbt-count-step="-1">-</button>
                                    <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_ordering_item_count" data-cbt-count-step="1">+ Tambah</button>
                                    <span class="cbt-manual-hidden-warning" data-cbt-count-warning="ordering_item">Item di luar jumlah aktif tidak akan disimpan.</span>
                                </div>
                                <div class="cbt-option-list">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <div class="cbt-option-row" data-cbt-manual-row data-manual-row-group="ordering_item" data-manual-index="<?php echo (int) $i; ?>">
                                            <label for="cbt_ordering_item_<?php echo (int) $i; ?>">Urutan benar <?php echo (int) $i; ?></label>
                                            <?php
                                            $ordering_editor_id = 'cbt_ordering_item_' . (int) $i;
                                            wp_editor(
                                                (string) ($ordering_option_values[$i] ?? ''),
                                                $ordering_editor_id,
                                                [
                                                    'textarea_name' => $ordering_editor_id,
                                                    'textarea_rows' => 2,
                                                    'media_buttons' => true,
                                                    'teeny' => true,
                                                    'quicktags' => true,
                                                    'tinymce' => $question_editor_tinymce,
                                                ]
                                            );
                                            ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <p class="cbt-inline-help">Isi item sesuai urutan benar. Saat ujian, item akan diacak dan siswa menyusun kembali urutannya. Minimal 2 item, maksimal 12 item, dan item tidak boleh duplikat.</p>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'matching' ? ' cbt-active' : ''; ?>" data-qtype="matching">
                            <th>Matching</th>
                            <td>
                                <div class="cbt-authoring-panel cbt-authoring-panel--matching">
                                    <div class="cbt-authoring-panel-head">
                                        <div>
                                            <strong>Pasangan kiri dan pilihan kanan</strong>
                                            <p>Prompt kiri boleh rich text. Pilihan kanan dibuat sebagai label dropdown yang dipilih siswa.</p>
                                        </div>
                                        <div class="cbt-manual-panel-actions">
                                            <span class="cbt-authoring-badge">Partial score per pasangan</span>
                                            <label class="cbt-manual-count-control" for="cbt_matching_pair_count">
                                                Jumlah Pasangan
                                                <select id="cbt_matching_pair_count" name="cbt_matching_pair_count" data-cbt-manual-count data-target="matching_pair" data-min="2" data-max="12">
                                                    <?php for ($i = 2; $i <= 12; $i++): ?>
                                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $matching_active_pair_count, $i); ?>><?php echo (int) $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </label>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_matching_pair_count" data-cbt-count-step="-1">-</button>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_matching_pair_count" data-cbt-count-step="1">+ Tambah</button>
                                            <span class="cbt-manual-hidden-warning" data-cbt-count-warning="matching_pair">Pasangan di luar jumlah aktif tidak akan disimpan.</span>
                                        </div>
                                    </div>
                                    <div class="cbt-matching-author-grid">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <section class="cbt-matching-author-row" data-cbt-manual-row data-manual-row-group="matching_pair" data-manual-index="<?php echo (int) $i; ?>">
                                            <div class="cbt-author-row-index">
                                                <span><?php echo (int) $i; ?></span>
                                                <small>Baris</small>
                                            </div>
                                            <div class="cbt-matching-author-left">
                                                <label for="cbt_matching_left_<?php echo (int) $i; ?>">Prompt kiri</label>
                                                <?php
                                                $matching_left_editor_id = 'cbt_matching_left_' . (int) $i;
                                                wp_editor(
                                                    (string) ($matching_left_values[$i] ?? ''),
                                                    $matching_left_editor_id,
                                                    [
                                                        'textarea_name' => $matching_left_editor_id,
                                                        'textarea_rows' => 2,
                                                        'media_buttons' => true,
                                                        'teeny' => true,
                                                        'quicktags' => true,
                                                        'tinymce' => $question_editor_tinymce,
                                                    ]
                                                );
                                                ?>
                                            </div>
                                            <div class="cbt-matching-author-link" aria-hidden="true">&rarr;</div>
                                            <div class="cbt-matching-author-right">
                                                <label for="cbt_matching_right_<?php echo (int) $i; ?>">Pilihan kanan</label>
                                                <?php
                                                $matching_right_editor_id = 'cbt_matching_right_' . (int) $i;
                                                wp_editor(
                                                    (string) ($matching_right_values[$i] ?? ''),
                                                    $matching_right_editor_id,
                                                    [
                                                        'textarea_name' => $matching_right_editor_id,
                                                        'textarea_rows' => 2,
                                                        'media_buttons' => true,
                                                        'teeny' => true,
                                                        'quicktags' => true,
                                                        'tinymce' => $question_editor_tinymce,
                                                    ]
                                                );
                                                ?>
                                            </div>
                                        </section>
                                    <?php endfor; ?>
                                    </div>
                                    <p class="cbt-inline-help">Isi pasangan kiri dan kanan sesuai kunci. Saat ujian, pilihan kanan otomatis menjadi dropdown. Minimal 2 pasangan, maksimal 12 pasangan, dan tidak boleh duplikat.</p>
                                </div>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'cloze_dropdown' ? ' cbt-active' : ''; ?>" data-qtype="cloze_dropdown">
                            <th>Cloze Dropdown</th>
                            <td>
                                <div class="cbt-authoring-panel cbt-authoring-panel--cloze">
                                    <div class="cbt-authoring-panel-head">
                                        <div>
                                            <strong>Dropdown inline di teks soal</strong>
                                            <p>Masukkan tag ke teks soal, lalu isi opsi dan kunci pada baris blank yang dipakai.</p>
                                        </div>
                                        <div class="cbt-manual-panel-actions">
                                            <span class="cbt-authoring-badge">8 blank tersedia</span>
                                            <label class="cbt-manual-count-control" for="cbt_cloze_dropdown_count">
                                                Jumlah Dropdown
                                                <select id="cbt_cloze_dropdown_count" name="cbt_cloze_dropdown_count" data-cbt-manual-count data-target="cloze_dropdown" data-min="1" data-max="8">
                                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $cloze_active_dropdown_count, $i); ?>><?php echo (int) $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </label>
                                            <label class="cbt-manual-count-control" for="cbt_cloze_option_count">
                                                Opsi per Dropdown
                                                <select id="cbt_cloze_option_count" name="cbt_cloze_option_count" data-cbt-manual-count data-target="cloze_option" data-min="2" data-max="6">
                                                    <?php for ($i = 2; $i <= 6; $i++): ?>
                                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $cloze_active_option_count, $i); ?>><?php echo (int) $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </label>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_cloze_dropdown_count" data-cbt-count-step="-1">-</button>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_cloze_dropdown_count" data-cbt-count-step="1">+ Tambah</button>
                                            <span class="cbt-manual-hidden-warning" data-cbt-count-warning="cloze_dropdown">Dropdown/opsi di luar jumlah aktif tidak akan disimpan.</span>
                                        </div>
                                    </div>
                                    <div class="cbt-cloze-author-grid">
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <section class="cbt-cloze-author-row" data-cbt-manual-row data-manual-row-group="cloze_dropdown" data-manual-index="<?php echo (int) $i; ?>">
                                            <div class="cbt-cloze-blank-meta">
                                                <strong>Blank <?php echo (int) $i; ?></strong>
                                                <span class="cbt-cloze-placeholder">[DROPDOWN_<?php echo (int) $i; ?>]</span>
                                            </div>
                                            <div class="cbt-cloze-options-grid">
                                                <?php for ($j = 1; $j <= 6; $j++): ?>
                                                    <label class="cbt-cloze-option-field" for="cbt_cloze_<?php echo (int) $i; ?>_option_<?php echo (int) $j; ?>" data-cbt-manual-row data-manual-row-group="cloze_option" data-manual-index="<?php echo (int) $j; ?>" data-manual-parent-index="<?php echo (int) $i; ?>">
                                                        <span>Opsi <?php echo (int) $j; ?></span>
                                                        <input
                                                            type="text"
                                                            id="cbt_cloze_<?php echo (int) $i; ?>_option_<?php echo (int) $j; ?>"
                                                            name="cbt_cloze_<?php echo (int) $i; ?>_option_<?php echo (int) $j; ?>"
                                                            class="regular-text"
                                                            value="<?php echo esc_attr((string) ($cloze_dropdown_rows[$i]['options'][$j] ?? '')); ?>"
                                                            placeholder="Teks opsi"
                                                        />
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="cbt-cloze-correct-row">
                                                <label for="cbt_cloze_correct_<?php echo (int) $i; ?>">Kunci jawaban</label>
                                                <select id="cbt_cloze_correct_<?php echo (int) $i; ?>" name="cbt_cloze_correct_<?php echo (int) $i; ?>">
                                                    <?php for ($j = 1; $j <= 6; $j++): ?>
                                                        <option value="<?php echo (int) $j; ?>" <?php selected((int) ($cloze_dropdown_rows[$i]['correct'] ?? 1), $j); ?>>Opsi <?php echo (int) $j; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </section>
                                    <?php endfor; ?>
                                    </div>
                                    <p class="cbt-inline-help">Setiap tag yang dipakai minimal punya 2 opsi dan tepat 1 kunci.</p>
                                </div>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'categorization' ? ' cbt-active' : ''; ?>" data-qtype="categorization">
                            <th>Categorization</th>
                            <td>
                                <div class="cbt-authoring-panel cbt-authoring-panel--categorization">
                                    <div class="cbt-authoring-panel-head">
                                        <div>
                                            <strong>Kategori dan item</strong>
                                            <p>Siswa memilih kategori lewat dropdown pada setiap item.</p>
                                        </div>
                                        <div class="cbt-manual-panel-actions">
                                            <span class="cbt-authoring-badge">Partial score per item</span>
                                            <label class="cbt-manual-count-control" for="cbt_cat_category_count">
                                                Jumlah Kategori
                                                <select id="cbt_cat_category_count" name="cbt_cat_category_count" data-cbt-manual-count data-target="cat_category" data-min="2" data-max="8">
                                                    <?php for ($i = 2; $i <= 8; $i++): ?>
                                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $categorization_active_category_count, $i); ?>><?php echo (int) $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </label>
                                            <label class="cbt-manual-count-control" for="cbt_cat_item_count">
                                                Jumlah Item
                                                <select id="cbt_cat_item_count" name="cbt_cat_item_count" data-cbt-manual-count data-target="cat_item" data-min="2" data-max="24">
                                                    <?php for ($i = 2; $i <= 24; $i++): ?>
                                                        <option value="<?php echo (int) $i; ?>" <?php selected((int) $categorization_active_item_count, $i); ?>><?php echo (int) $i; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </label>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_cat_item_count" data-cbt-count-step="-1">-</button>
                                            <button type="button" class="button cbt-manual-count-step" data-cbt-count-target="cbt_cat_item_count" data-cbt-count-step="1">+ Tambah</button>
                                            <span class="cbt-manual-hidden-warning" data-cbt-count-warning="cat_item">Kategori/item di luar jumlah aktif tidak akan disimpan.</span>
                                        </div>
                                    </div>
                                    <div class="cbt-cat-category-grid">
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <label class="cbt-cat-category-field" for="cbt_cat_category_<?php echo (int) $i; ?>" data-cbt-manual-row data-manual-row-group="cat_category" data-manual-index="<?php echo (int) $i; ?>">
                                                <span>Kategori <?php echo (int) $i; ?></span>
                                                <input
                                                    type="text"
                                                    id="cbt_cat_category_<?php echo (int) $i; ?>"
                                                    name="cbt_cat_category_<?php echo (int) $i; ?>"
                                                    value="<?php echo esc_attr((string) ($categorization_category_values[$i] ?? '')); ?>"
                                                    placeholder="Contoh: Mamalia"
                                                />
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="cbt-categorization-author-grid">
                                        <?php for ($i = 1; $i <= 24; $i++): ?>
                                            <section class="cbt-cat-item-row" data-cbt-manual-row data-manual-row-group="cat_item" data-manual-index="<?php echo (int) $i; ?>">
                                                <div class="cbt-author-row-index">
                                                    <span><?php echo (int) $i; ?></span>
                                                    <small>Item</small>
                                                </div>
                                                <label for="cbt_cat_item_<?php echo (int) $i; ?>">
                                                    <span class="screen-reader-text">Item <?php echo (int) $i; ?></span>
                                                    <input
                                                        type="text"
                                                        id="cbt_cat_item_<?php echo (int) $i; ?>"
                                                        name="cbt_cat_item_<?php echo (int) $i; ?>"
                                                        value="<?php echo esc_attr(wp_strip_all_tags((string) ($categorization_item_values[$i] ?? ''))); ?>"
                                                        placeholder="Teks item"
                                                    />
                                                </label>
                                                <label for="cbt_cat_correct_<?php echo (int) $i; ?>">
                                                    <span class="screen-reader-text">Kunci kategori item <?php echo (int) $i; ?></span>
                                                    <select id="cbt_cat_correct_<?php echo (int) $i; ?>" name="cbt_cat_correct_<?php echo (int) $i; ?>" data-cbt-cat-correct-select>
                                                        <?php for ($j = 1; $j <= 8; $j++): ?>
                                                            <option value="<?php echo (int) $j; ?>" <?php selected((int) ($categorization_item_correct[$i] ?? 1), $j); ?>>Kategori <?php echo (int) $j; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </label>
                                            </section>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="cbt-inline-help">Isi minimal 2 kategori dan 2 item. Setiap item yang diisi wajib punya kategori benar, dan teks item/kategori tidak boleh duplikat.</p>
                                </div>
                            </td>
                        </tr>
                        <tr class="cbt-qtype-panel<?php echo $editing_type === 'table_completion' ? ' cbt-active' : ''; ?>" data-qtype="table_completion">
                            <th>Table Completion</th>
                            <td>
                                <div class="cbt-authoring-panel cbt-authoring-panel--table">
                                    <div class="cbt-authoring-panel-head">
                                        <div>
                                            <strong>Tabel sel campuran</strong>
                                            <p>Pilih Statis, Isian teks, atau Dropdown per sel. Sel jawaban dinilai partial.</p>
                                        </div>
                                        <span class="cbt-authoring-badge">Maks 8x6</span>
                                    </div>
                                    <div class="cbt-table-size-row">
                                        <label for="cbt_table_rows">
                                            Baris
                                            <select id="cbt_table_rows" name="cbt_table_rows">
                                                <?php for ($i = 2; $i <= 8; $i++): ?>
                                                    <option value="<?php echo (int) $i; ?>" <?php selected((int) $table_completion_row_count, $i); ?>><?php echo (int) $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </label>
                                        <label for="cbt_table_cols">
                                            Kolom
                                            <select id="cbt_table_cols" name="cbt_table_cols">
                                                <?php for ($i = 2; $i <= 6; $i++): ?>
                                                    <option value="<?php echo (int) $i; ?>" <?php selected((int) $table_completion_column_count, $i); ?>><?php echo (int) $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="cbt-table-designer" data-cbt-table-designer>
                                        <div class="cbt-table-grid-area">
                                            <div class="cbt-table-designer-summary" aria-live="polite">
                                                <span data-cbt-table-active-size><?php echo esc_html((string) ((int) $table_completion_row_count . 'x' . (int) $table_completion_column_count)); ?></span>
                                                <span data-cbt-table-answer-count>0 sel jawaban</span>
                                                <span>Pilih sel untuk edit</span>
                                            </div>
                                            <div class="cbt-table-author-scroll">
                                                <div class="cbt-table-author-matrix" id="cbt-table-author-matrix" style="--cbt-table-cols: <?php echo max(2, min(6, (int) $table_completion_column_count)); ?>;">
                                                    <?php for ($row = 1; $row <= 8; $row++): ?>
                                                <?php $row_hidden = $row > (int) $table_completion_row_count; ?>
                                                <div class="cbt-table-author-row<?php echo $row_hidden ? ' is-hidden' : ''; ?>" data-table-row="<?php echo (int) $row; ?>" <?php echo $row_hidden ? 'hidden' : ''; ?>>
                                                    <?php for ($col = 1; $col <= 6; $col++): ?>
                                                        <?php
                                                        $cell_key = chr(64 + $col) . (string) $row;
                                                        $cell = $table_completion_cells[$cell_key] ?? [
                                                            'cell_type' => 'static',
                                                            'cell_text' => '',
                                                            'correct_text' => '',
                                                            'options' => array_fill(1, 6, ''),
                                                            'correct' => 1,
                                                        ];
                                                        $cell_type = in_array((string) ($cell['cell_type'] ?? 'static'), ['static', 'text', 'dropdown'], true)
                                                            ? (string) ($cell['cell_type'] ?? 'static')
                                                            : 'static';
                                                        $cell_hidden = $row > (int) $table_completion_row_count || $col > (int) $table_completion_column_count;
                                                        $cell_type_label = $cell_type === 'dropdown' ? 'Dropdown' : ($cell_type === 'text' ? 'Text' : 'Statis');
                                                        ?>
                                                        <section class="cbt-table-cell-field is-<?php echo esc_attr($cell_type); ?><?php echo $cell_hidden ? ' is-hidden' : ''; ?>" data-cbt-table-cell-field data-cbt-cell-type="<?php echo esc_attr($cell_type); ?>" data-cbt-table-cell-key="<?php echo esc_attr($cell_key); ?>" data-table-row="<?php echo (int) $row; ?>" data-table-col="<?php echo (int) $col; ?>" <?php echo $cell_hidden ? 'hidden' : ''; ?>>
                                                            <button type="button" class="cbt-table-cell-button" data-cbt-table-select-cell="<?php echo esc_attr($cell_key); ?>" aria-pressed="false">
                                                                <span class="cbt-table-cell-top">
                                                                    <span class="cbt-table-cell-key"><?php echo esc_html($cell_key); ?></span>
                                                                    <span class="cbt-table-cell-type-label" data-cbt-table-type-label><?php echo esc_html($cell_type_label); ?></span>
                                                                </span>
                                                                <span class="cbt-table-cell-summary" data-cbt-table-cell-summary>Klik untuk edit</span>
                                                            </button>
                                                            <div class="cbt-table-cell-hidden-inputs" data-cbt-table-cell-inputs>
                                                            <div class="cbt-table-cell-head">
                                                                <span class="cbt-table-cell-key"><?php echo esc_html($cell_key); ?></span>
                                                                <select class="cbt-table-cell-type" id="cbt_table_<?php echo esc_attr($cell_key); ?>_type" name="cbt_table_<?php echo esc_attr($cell_key); ?>_type" data-cbt-table-cell-type="<?php echo esc_attr($cell_key); ?>">
                                                                    <option value="static" <?php selected($cell_type, 'static'); ?>>Teks tetap</option>
                                                                    <option value="text" <?php selected($cell_type, 'text'); ?>>Isian teks</option>
                                                                    <option value="dropdown" <?php selected($cell_type, 'dropdown'); ?>>Dropdown</option>
                                                                </select>
                                                            </div>
                                                            <label for="cbt_table_<?php echo esc_attr($cell_key); ?>_text">
                                                                <span data-cbt-table-text-caption><?php echo $cell_type === 'static' ? 'Teks tetap' : 'Label sel (opsional)'; ?></span>
                                                                <input
                                                                    type="text"
                                                                    id="cbt_table_<?php echo esc_attr($cell_key); ?>_text"
                                                                    name="cbt_table_<?php echo esc_attr($cell_key); ?>_text"
                                                                    data-cbt-table-cell-text
                                                                    value="<?php echo esc_attr(wp_strip_all_tags((string) ($cell['cell_text'] ?? ''))); ?>"
                                                                    placeholder="<?php echo esc_attr($cell_type === 'static' ? 'Teks yang tampil di tabel' : 'Label/petunjuk opsional'); ?>"
                                                                />
                                                            </label>
                                                            <label for="cbt_table_<?php echo esc_attr($cell_key); ?>_answer" data-cbt-table-mode="text" <?php echo $cell_type === 'text' ? '' : 'hidden'; ?>>
                                                                Kunci isian teks
                                                                <input
                                                                    type="text"
                                                                    id="cbt_table_<?php echo esc_attr($cell_key); ?>_answer"
                                                                    name="cbt_table_<?php echo esc_attr($cell_key); ?>_answer"
                                                                    value="<?php echo esc_attr((string) ($cell['correct_text'] ?? '')); ?>"
                                                                    placeholder="Jawaban valid"
                                                                />
                                                            </label>
                                                            <div data-cbt-table-mode="dropdown" <?php echo $cell_type === 'dropdown' ? '' : 'hidden'; ?>>
                                                                <div class="cbt-table-cell-options-head">
                                                                    <span>Opsi dropdown</span>
                                                                    <span>minimal 2</span>
                                                                </div>
                                                                <div class="cbt-table-cell-options">
                                                                    <?php for ($j = 1; $j <= 6; $j++): ?>
                                                                        <label for="cbt_table_<?php echo esc_attr($cell_key); ?>_option_<?php echo (int) $j; ?>">
                                                                            <span>Opsi <?php echo (int) $j; ?></span>
                                                                            <input
                                                                                type="text"
                                                                                id="cbt_table_<?php echo esc_attr($cell_key); ?>_option_<?php echo (int) $j; ?>"
                                                                                name="cbt_table_<?php echo esc_attr($cell_key); ?>_option_<?php echo (int) $j; ?>"
                                                                                value="<?php echo esc_attr((string) ($cell['options'][$j] ?? '')); ?>"
                                                                                placeholder="Opsi"
                                                                            />
                                                                        </label>
                                                                    <?php endfor; ?>
                                                                </div>
                                                            </div>
                                                            <label for="cbt_table_<?php echo esc_attr($cell_key); ?>_correct" data-cbt-table-mode="dropdown" <?php echo $cell_type === 'dropdown' ? '' : 'hidden'; ?>>
                                                                Kunci dropdown
                                                                <select id="cbt_table_<?php echo esc_attr($cell_key); ?>_correct" name="cbt_table_<?php echo esc_attr($cell_key); ?>_correct">
                                                                    <?php for ($j = 1; $j <= 6; $j++): ?>
                                                                        <option value="<?php echo (int) $j; ?>" <?php selected((int) ($cell['correct'] ?? 1), $j); ?>>Opsi <?php echo (int) $j; ?></option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </label>
                                                            </div>
                                                        </section>
                                                    <?php endfor; ?>
                                                </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <aside class="cbt-table-side-panel" data-cbt-table-side-panel>
                                            <div class="cbt-table-side-head">
                                                <div class="cbt-table-side-title">
                                                    <strong>Editor sel <span data-cbt-table-panel-key>A1</span></strong>
                                                    <span data-cbt-table-panel-summary>Pilih sel pada grid.</span>
                                                </div>
                                                <span class="cbt-table-side-chip" data-cbt-table-panel-type-label>Statis</span>
                                            </div>
                                            <div class="cbt-table-side-fields">
                                                <label class="cbt-table-side-field" for="cbt_table_cell_panel_type">
                                                    <span>Tipe sel</span>
                                                    <select id="cbt_table_cell_panel_type" data-cbt-table-panel-type>
                                                        <option value="static">Statis</option>
                                                        <option value="text">Isian teks</option>
                                                        <option value="dropdown">Dropdown</option>
                                                    </select>
                                                </label>
                                                <label class="cbt-table-side-field cbt-table-side-field--wide" for="cbt_table_cell_panel_text">
                                                    <span data-cbt-table-panel-text-caption>Teks tetap</span>
                                                    <input type="text" id="cbt_table_cell_panel_text" data-cbt-table-panel-text placeholder="Teks yang tampil di tabel" />
                                                </label>
                                                <label class="cbt-table-side-field" for="cbt_table_cell_panel_answer" data-cbt-table-panel-mode="text" hidden>
                                                    <span>Kunci isian teks</span>
                                                    <input type="text" id="cbt_table_cell_panel_answer" data-cbt-table-panel-answer placeholder="Jawaban valid" />
                                                </label>
                                                <div class="cbt-table-side-field" data-cbt-table-panel-mode="dropdown" hidden>
                                                    <div class="cbt-table-side-options-head">Opsi dropdown</div>
                                                    <div class="cbt-table-side-options">
                                                        <?php for ($j = 1; $j <= 6; $j++): ?>
                                                            <label for="cbt_table_cell_panel_option_<?php echo (int) $j; ?>">
                                                                <span>Opsi <?php echo (int) $j; ?></span>
                                                                <input type="text" id="cbt_table_cell_panel_option_<?php echo (int) $j; ?>" data-cbt-table-panel-option="<?php echo (int) $j; ?>" placeholder="Opsi" />
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                <label class="cbt-table-side-field" for="cbt_table_cell_panel_correct" data-cbt-table-panel-mode="dropdown" hidden>
                                                    <span>Kunci dropdown</span>
                                                    <select id="cbt_table_cell_panel_correct" data-cbt-table-panel-correct>
                                                        <?php for ($j = 1; $j <= 6; $j++): ?>
                                                            <option value="<?php echo (int) $j; ?>">Opsi <?php echo (int) $j; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </label>
                                            </div>
                                        </aside>
                                    </div>
                                    <p class="cbt-inline-help">Minimal 2x2, maksimal 8x6, dan maksimal 24 sel jawaban. Text cell memakai normalisasi seperti Short Answer; Dropdown cell wajib punya minimal 2 opsi dan tepat 1 kunci.</p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cbt_question_explanation_editor">Pembahasan</label></th>
                            <td>
                                <?php
                                wp_editor(
                                    (string) ($editing_explanation ?? ''),
                                    'cbt_question_explanation_editor',
                                    [
                                        'textarea_name' => 'explanation',
                                        'textarea_rows' => 5,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'quicktags' => true,
                                        'tinymce' => $question_editor_tinymce,
                                    ]
                                );
                                ?>
                                <p class="description">Opsional. Isi pembahasan/penjelasan soal. Bisa paste gambar langsung dari clipboard untuk file kecil, atau gunakan Add Media untuk file besar.</p>
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

                <section class="cbt-questions-panel" data-cbt-questions-panel="import" data-cbt-questions-refresh-area="import-panel" role="tabpanel">
                    <div class="cbt-questions-panel-header">
                        <div>
                            <h2>Import Questions</h2>
                            <p>Upload template Word sesuai tipe soal untuk menambahkan soal baru secara massal ke Bank Soal subject yang dipilih.</p>
                        </div>
                        <span class="cbt-questions-chip">DOCX Import</span>
                    </div>
                <div data-cbt-questions-refresh-area="import-status">
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
                    <div
                        class="cbt-questions-progress"
                        data-cbt-questions-import-progress
                        data-cbt-questions-import-running="<?php echo $question_import_is_running ? '1' : '0'; ?>"
                        data-cbt-questions-import-continue-url="<?php echo esc_url($question_import_continue_url); ?>"
                        data-cbt-questions-progress-profile="import"
                        data-cbt-questions-refresh-areas="notices,overview,import-status,list-panel"
                        data-cbt-questions-success-tab="import"
                    >
                        <div class="cbt-questions-progress-track" aria-hidden="true">
                            <div class="cbt-questions-progress-fill" style="width: <?php echo esc_attr((string) $question_import_progress_percent); ?>%;"></div>
                        </div>
                        <div class="cbt-questions-progress-meta">
                            <?php if ($question_import_is_running): ?>
                                Memproses batch soal berikutnya...
                            <?php else: ?>
                                <span style="color:#0a7a2f; font-weight:600;">Import ke Bank Soal selesai diproses.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($question_import_recent_failures)): ?>
                        <div class="notice notice-warning">
                            <p><strong>Gagal import terbaru:</strong></p>
                            <ul style="margin:0 0 0 1.2rem; list-style:disc;">
                                <?php foreach ($question_import_recent_failures as $failure_entry): ?>
                                    <?php
                                    $failure_entry = is_array($failure_entry) ? $failure_entry : ['formatted' => (string) $failure_entry];
                                    $failure_block = isset($failure_entry['block_number']) ? (int) $failure_entry['block_number'] : 0;
                                    $failure_type_label = trim((string) ($failure_entry['question_type_label'] ?? ''));
                                    $failure_preview = trim((string) ($failure_entry['question_preview'] ?? ''));
                                    $failure_message = trim((string) ($failure_entry['message'] ?? ''));
                                    $failure_formatted = trim((string) ($failure_entry['formatted'] ?? ''));
                                    ?>
                                    <li>
                                        <?php if ($failure_block > 0 || $failure_type_label !== ''): ?>
                                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:2px;">
                                                <?php if ($failure_block > 0): ?>
                                                    <span style="display:inline-block; padding:2px 8px; border-radius:999px; background:#fff7ed; color:#9a3412; font-size:11px; font-weight:700;">Blok #<?php echo (int) $failure_block; ?></span>
                                                <?php endif; ?>
                                                <?php if ($failure_type_label !== ''): ?>
                                                    <span style="display:inline-block; padding:2px 8px; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:11px; font-weight:700;"><?php echo esc_html($failure_type_label); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($failure_preview !== ''): ?>
                                            <div style="font-weight:600; margin-bottom:2px;"><?php echo esc_html($failure_preview); ?></div>
                                        <?php endif; ?>
                                        <div><?php echo esc_html($failure_message !== '' ? $failure_message : $failure_formatted); ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($question_import_state['is_complete'])): ?>
                        <div class="cbt-import-batch-analysis" style="margin:14px 0 18px;" data-cbt-import-batch-analysis>
                            <div class="cbt-import-batch-analysis-head">
                                <div>
                                    <strong>Hasil Import Batch Ini</strong>
                                    <p style="margin:4px 0 0; color:#4b5563;">
                                        <?php if ($question_import_batch_subject_label !== ''): ?>
                                            Subject target: <?php echo esc_html($question_import_batch_subject_label); ?>.
                                        <?php else: ?>
                                            Soal baru dari batch import ini siap ditinjau.
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="cbt-questions-chip"><?php echo esc_html('Created ' . (string) $question_import_created); ?></span>
                            </div>
                            <div class="cbt-questions-filter-summary" style="margin:0 0 12px;">
                                <span class="cbt-questions-chip"><?php echo esc_html('Created: ' . (string) $question_import_created); ?></span>
                                <span class="cbt-questions-chip"><?php echo esc_html('Failed: ' . (string) $question_import_failed); ?></span>
                                <span class="cbt-questions-chip"><?php echo esc_html('Preserved: ' . (string) ((int) ($question_import_batch_analysis_summary['preserved'] ?? 0))); ?></span>
                                <span class="cbt-questions-chip"><?php echo esc_html('Fallback: ' . (string) ((int) ($question_import_batch_analysis_summary['fallback'] ?? 0))); ?></span>
                                <span class="cbt-questions-chip"><?php echo esc_html('Unsupported: ' . (string) ((int) ($question_import_batch_analysis_summary['unsupported'] ?? 0))); ?></span>
                                <?php if ($question_import_batch_subject_label !== ''): ?>
                                    <span class="cbt-questions-chip"><?php echo esc_html('Subject: ' . $question_import_batch_subject_label); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($question_import_diagnostic_truncated): ?>
                                <p class="cbt-import-batch-analysis-note" style="margin:0 0 12px;">Detail diagnostics dipotong agar panel tetap ringkas. Count total tetap akurat.</p>
                            <?php endif; ?>
                            <?php if (!empty($question_import_batch_analysis_items)): ?>
                                <div class="cbt-import-batch-analysis-layout">
                                    <aside class="cbt-import-batch-analysis-nav">
                                        <?php foreach ($question_import_batch_analysis_items as $analysis_item): ?>
                                            <?php
                                            $analysis_question_id = (int) ($analysis_item['question_id'] ?? 0);
                                            $analysis_counts = (array) ($analysis_item['diagnostic_counts'] ?? []);
                                            $analysis_issue_count = max(0, (int) ($analysis_item['issue_count'] ?? 0));
                                            $analysis_status_label = (string) ($analysis_item['status_label'] ?? ($analysis_issue_count > 0 ? 'Perlu Dicek ' . $analysis_issue_count : 'Aman'));
                                            $analysis_is_active = $analysis_question_id > 0 && $analysis_question_id === (int) $question_import_batch_selected_question_id;
                                            ?>
                                            <button
                                                type="button"
                                                class="cbt-import-batch-analysis-nav-item<?php echo $analysis_is_active ? ' is-active' : ''; ?>"
                                                data-cbt-import-batch-analysis-nav-item
                                                data-question-id="<?php echo (int) $analysis_question_id; ?>"
                                            >
                                                <div class="cbt-import-batch-analysis-nav-title">
                                                    <strong><?php echo esc_html('Soal #' . (string) $analysis_question_id); ?></strong>
                                                    <span class="cbt-questions-chip <?php echo $analysis_issue_count > 0 ? 'cbt-import-batch-analysis-status--needs-review' : 'cbt-import-batch-analysis-status--safe'; ?>">
                                                        <?php echo esc_html($analysis_status_label); ?>
                                                    </span>
                                                </div>
                                                <div class="cbt-import-batch-analysis-nav-meta">
                                                    <?php echo esc_html((string) ($analysis_item['question_type_label'] ?? '')); ?>
                                                    <?php if (!empty($analysis_item['block_number'])): ?>
                                                        · <?php echo esc_html('Blok #' . (string) ((int) $analysis_item['block_number'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="cbt-import-batch-analysis-nav-preview"><?php echo esc_html((string) ($analysis_item['preview'] ?? '')); ?></div>
                                            </button>
                                        <?php endforeach; ?>
                                    </aside>
                                    <div class="cbt-import-batch-analysis-detail">
                                        <?php foreach ($question_import_batch_analysis_items as $analysis_item): ?>
                                            <?php
                                            $analysis_question_id = (int) ($analysis_item['question_id'] ?? 0);
                                            $analysis_counts = (array) ($analysis_item['diagnostic_counts'] ?? []);
                                            $analysis_entries = (array) ($analysis_item['diagnostic_entries'] ?? []);
                                            $analysis_issue_count = max(0, (int) ($analysis_item['issue_count'] ?? 0));
                                            $analysis_is_active = $analysis_question_id > 0 && $analysis_question_id === (int) $question_import_batch_selected_question_id;
                                            ?>
                                            <section
                                                class="cbt-import-batch-analysis-detail-panel<?php echo $analysis_is_active ? ' is-active' : ''; ?>"
                                                data-cbt-import-batch-analysis-panel
                                                data-question-id="<?php echo (int) $analysis_question_id; ?>"
                                            >
                                                <div class="cbt-questions-filter-summary" style="margin:0 0 10px;">
                                                    <span class="cbt-questions-chip"><?php echo esc_html('Soal #' . (string) $analysis_question_id); ?></span>
                                                    <span class="cbt-questions-chip"><?php echo esc_html((string) ($analysis_item['question_type_label'] ?? '')); ?></span>
                                                    <?php if (!empty($analysis_item['block_number'])): ?>
                                                        <span class="cbt-questions-chip"><?php echo esc_html('Blok #' . (string) ((int) $analysis_item['block_number'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="cbt-import-batch-analysis-preview"><?php echo esc_html((string) ($analysis_item['preview'] ?? '')); ?></p>
                                                <div class="cbt-questions-filter-summary" style="margin:0 0 10px;">
                                                    <span class="cbt-questions-chip"><?php echo esc_html('Preserved ' . (string) ((int) ($analysis_counts['preserved'] ?? 0))); ?></span>
                                                    <span class="cbt-questions-chip"><?php echo esc_html('Fallback ' . (string) ((int) ($analysis_counts['fallback'] ?? 0))); ?></span>
                                                    <span class="cbt-questions-chip"><?php echo esc_html('Unsupported ' . (string) ((int) ($analysis_counts['unsupported'] ?? 0))); ?></span>
                                                </div>
                                                <div class="cbt-import-batch-analysis-actions">
                                                    <a
                                                        class="button button-secondary"
                                                        href="<?php echo esc_url((string) ($analysis_item['view_url'] ?? $question_import_batch_list_url)); ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        data-cbt-questions-tab-link="list"
                                                    >
                                                        Lihat Soal di Question List
                                                    </a>
                                                </div>
                                                <div class="cbt-import-batch-analysis-filters">
                                                    <button type="button" class="button is-active" data-cbt-import-batch-analysis-filter="needs-review">Perlu Dicek</button>
                                                    <button type="button" class="button" data-cbt-import-batch-analysis-filter="fallback">Fallback</button>
                                                    <button type="button" class="button" data-cbt-import-batch-analysis-filter="unsupported">Unsupported</button>
                                                    <button type="button" class="button" data-cbt-import-batch-analysis-filter="preserved">Preserved</button>
                                                    <button type="button" class="button" data-cbt-import-batch-analysis-filter="all">Semua</button>
                                                </div>
                                                <?php if (!empty($analysis_entries)): ?>
                                                    <div class="cbt-import-batch-analysis-items">
                                                        <?php foreach ($analysis_entries as $diagnostic_entry): ?>
                                                            <?php
                                                            $diagnostic_kind = trim((string) ($diagnostic_entry['kind'] ?? ''));
                                                            $diagnostic_field = trim((string) ($diagnostic_entry['field'] ?? 'SOAL'));
                                                            $diagnostic_feature = trim((string) ($diagnostic_entry['feature'] ?? ''));
                                                            $diagnostic_message = trim((string) ($diagnostic_entry['message'] ?? ''));
                                                            ?>
                                                            <article class="cbt-import-batch-analysis-item" data-diagnostic-kind="<?php echo esc_attr($diagnostic_kind); ?>">
                                                                <div class="cbt-import-batch-analysis-item-meta">
                                                                    <span class="cbt-questions-chip"><?php echo esc_html($diagnostic_field); ?></span>
                                                                    <span class="cbt-questions-chip"><?php echo esc_html(ucfirst($diagnostic_kind)); ?></span>
                                                                    <span class="cbt-questions-chip"><?php echo esc_html($diagnostic_feature); ?></span>
                                                                </div>
                                                                <div class="cbt-import-batch-analysis-item-message"><?php echo esc_html($diagnostic_message); ?></div>
                                                            </article>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="cbt-import-batch-analysis-empty" data-cbt-import-batch-analysis-empty hidden>
                                                        Soal ini tidak punya catatan yang perlu dicek.
                                                    </div>
                                                <?php else: ?>
                                                    <div class="cbt-import-batch-analysis-empty">Soal ini tidak punya catatan preserve, fallback, atau unsupported tambahan.</div>
                                                <?php endif; ?>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <p class="cbt-questions-actions cbt-import-batch-analysis-actions--footer">
                                    <a
                                        class="button button-primary"
                                        href="<?php echo esc_url($question_import_batch_list_url); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        LIHAT SEMUA HASIL IMPORT INI
                                    </a>
                                    <a
                                        class="button button-secondary"
                                        href="<?php echo esc_url($question_import_batch_back_to_all_url); ?>"
                                        data-cbt-questions-tab-link="import"
                                        data-cbt-questions-async-link
                                        data-cbt-questions-progress-profile="list"
                                        data-cbt-questions-refresh-areas="notices,overview,import-status,list-panel"
                                        data-cbt-questions-success-tab="import"
                                    >
                                        TUTUP REPORT INI
                                    </a>
                                    <a
                                        class="button button-secondary cbt-admin-btn--danger"
                                        href="<?php echo esc_url($question_import_batch_delete_all_url); ?>"
                                        data-cbt-questions-async-link
                                        data-cbt-questions-progress-profile="delete"
                                        data-cbt-questions-refresh-areas="notices,overview,import-status,list-panel"
                                        data-cbt-questions-success-tab="list"
                                        onclick="return confirm('Hapus semua soal hasil import batch ini?');"
                                    >
                                        HAPUS SEMUA HASIL IMPORT INI
                                    </a>
                                </p>
                            <?php else: ?>
                                <div class="cbt-question-reference-empty">Batch ini tidak memiliki soal sukses yang masih tersisa untuk ditinjau.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
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
                                <button type="button" class="button<?php echo $import_active_type === 'ordering' ? ' cbt-active' : ''; ?>" data-import-type="ordering">Ordering</button>
                                <button type="button" class="button<?php echo $import_active_type === 'matching' ? ' cbt-active' : ''; ?>" data-import-type="matching">Matching</button>
                                <button type="button" class="button<?php echo $import_active_type === 'cloze_dropdown' ? ' cbt-active' : ''; ?>" data-import-type="cloze_dropdown">Cloze Dropdown</button>
                                <button type="button" class="button<?php echo $import_active_type === 'categorization' ? ' cbt-active' : ''; ?>" data-import-type="categorization">Categorization</button>
                                <button type="button" class="button<?php echo $import_active_type === 'table_completion' ? ' cbt-active' : ''; ?>" data-import-type="table_completion">Table Completion</button>
                            </div>
                        <?php endif; ?>
                <p class="description" id="cbt-import-type-help"><?php echo esc_html($import_help_text); ?></p>
                <div class="notice notice-warning inline" style="margin:10px 0 14px;">
                    <p style="margin:8px 0;">
                        <strong>Perhatian:</strong>
                        file akan ditolak jika tipe soal yang terdeteksi tidak sama dengan menu import aktif.
                        Contoh: file <strong>Multiple Choice</strong> yang di-upload lewat menu <strong>Multiple Answer</strong> tidak akan diproses.
                        Template DOCX lama juga perlu diunduh ulang agar membawa marker validasi terbaru.
                    </p>
                </div>
                <p class="cbt-questions-actions">
                    <span class="cbt-word-template-control">
                        <label for="cbt-word-template-count"><strong>Jumlah Soal</strong></label>
                        <select id="cbt-word-template-count">
                            <?php for ($count_option = 5; $count_option <= 100; $count_option += 5): ?>
                                <option value="<?php echo (int) $count_option; ?>" <?php selected($count_option, 10); ?>><?php echo (int) $count_option; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="option_count" hidden>
                        <label for="cbt-word-template-option-count"><strong>Jumlah Pilihan</strong></label>
                        <select id="cbt-word-template-option-count" data-template-select="option_count" disabled hidden>
                            <?php for ($option_count = 3; $option_count <= 12; $option_count++): ?>
                                <option value="<?php echo (int) $option_count; ?>" <?php selected($option_count, 5); ?>><?php echo (int) $option_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="input_count" hidden>
                        <label for="cbt-word-template-input-count"><strong>Jumlah Input</strong></label>
                        <select id="cbt-word-template-input-count" data-template-select="input_count" disabled hidden>
                            <?php for ($input_count = 1; $input_count <= 8; $input_count++): ?>
                                <option value="<?php echo (int) $input_count; ?>" <?php selected($input_count, 3); ?>><?php echo (int) $input_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="statement_count" hidden>
                        <label for="cbt-word-template-statement-count"><strong>Jumlah Pernyataan</strong></label>
                        <select id="cbt-word-template-statement-count" data-template-select="statement_count" disabled hidden>
                            <?php for ($statement_count = 2; $statement_count <= 10; $statement_count++): ?>
                                <option value="<?php echo (int) $statement_count; ?>" <?php selected($statement_count, 5); ?>><?php echo (int) $statement_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="item_count" hidden>
                        <label for="cbt-word-template-item-count"><strong>Jumlah Item</strong></label>
                        <select id="cbt-word-template-item-count" data-template-select="item_count" disabled hidden>
                            <?php for ($item_count = 2; $item_count <= 12; $item_count++): ?>
                                <option value="<?php echo (int) $item_count; ?>" <?php selected($item_count, 4); ?>><?php echo (int) $item_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="pair_count" hidden>
                        <label for="cbt-word-template-pair-count"><strong>Jumlah Pasangan</strong></label>
                        <select id="cbt-word-template-pair-count" data-template-select="pair_count" disabled hidden>
                            <?php for ($pair_count = 2; $pair_count <= 12; $pair_count++): ?>
                                <option value="<?php echo (int) $pair_count; ?>" <?php selected($pair_count, 3); ?>><?php echo (int) $pair_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="dropdown_count" hidden>
                        <label for="cbt-word-template-dropdown-count"><strong>Jumlah Dropdown</strong></label>
                        <select id="cbt-word-template-dropdown-count" data-template-select="dropdown_count" disabled hidden>
                            <?php for ($dropdown_count = 1; $dropdown_count <= 8; $dropdown_count++): ?>
                                <option value="<?php echo (int) $dropdown_count; ?>" <?php selected($dropdown_count, 2); ?>><?php echo (int) $dropdown_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="dropdown_option_count" hidden>
                        <label for="cbt-word-template-dropdown-option-count"><strong>Opsi per Dropdown</strong></label>
                        <select id="cbt-word-template-dropdown-option-count" data-template-select="dropdown_option_count" disabled hidden>
                            <?php for ($dropdown_option_count = 2; $dropdown_option_count <= 6; $dropdown_option_count++): ?>
                                <option value="<?php echo (int) $dropdown_option_count; ?>" <?php selected($dropdown_option_count, 3); ?>><?php echo (int) $dropdown_option_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="category_count" hidden>
                        <label for="cbt-word-template-category-count"><strong>Jumlah Kategori</strong></label>
                        <select id="cbt-word-template-category-count" data-template-select="category_count" disabled hidden>
                            <?php for ($category_count = 2; $category_count <= 8; $category_count++): ?>
                                <option value="<?php echo (int) $category_count; ?>" <?php selected($category_count, 2); ?>><?php echo (int) $category_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="categorization_item_count" hidden>
                        <label for="cbt-word-template-categorization-item-count"><strong>Jumlah Item</strong></label>
                        <select id="cbt-word-template-categorization-item-count" data-template-select="categorization_item_count" disabled hidden>
                            <?php for ($categorization_item_count = 2; $categorization_item_count <= 24; $categorization_item_count++): ?>
                                <option value="<?php echo (int) $categorization_item_count; ?>" <?php selected($categorization_item_count, 3); ?>><?php echo (int) $categorization_item_count; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="table_rows" hidden>
                        <label for="cbt-word-template-table-rows"><strong>Rows</strong></label>
                        <select id="cbt-word-template-table-rows" data-template-select="table_rows" disabled hidden>
                            <?php for ($table_rows = 2; $table_rows <= 8; $table_rows++): ?>
                                <option value="<?php echo (int) $table_rows; ?>" <?php selected($table_rows, 2); ?>><?php echo (int) $table_rows; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <span class="cbt-word-template-control" data-template-control="table_cols" hidden>
                        <label for="cbt-word-template-table-cols"><strong>Columns</strong></label>
                        <select id="cbt-word-template-table-cols" data-template-select="table_cols" disabled hidden>
                            <?php for ($table_cols = 2; $table_cols <= 6; $table_cols++): ?>
                                <option value="<?php echo (int) $table_cols; ?>" <?php selected($table_cols, 2); ?>><?php echo (int) $table_cols; ?></option>
                            <?php endfor; ?>
                        </select>
                    </span>
                    <a
                        id="cbt-download-word-template"
                        class="button button-secondary"
                        data-url-mc="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_mc'), 'cbt_download_question_template_word_mc')); ?>"
                        data-url-ma="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_ma'), 'cbt_download_question_template_word_ma')); ?>"
                        data-url-tf="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_tf'), 'cbt_download_question_template_word_tf')); ?>"
                        data-url-tfm="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_tfm'), 'cbt_download_question_template_word_tfm')); ?>"
                        data-url-sa="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_sa'), 'cbt_download_question_template_word_sa')); ?>"
                        data-url-essay="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_essay'), 'cbt_download_question_template_word_essay')); ?>"
                        data-url-ordering="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_ordering'), 'cbt_download_question_template_word_ordering')); ?>"
                        data-url-matching="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_matching'), 'cbt_download_question_template_word_matching')); ?>"
                        data-url-cloze="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_cloze'), 'cbt_download_question_template_word_cloze')); ?>"
                        data-url-categorization="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_categorization'), 'cbt_download_question_template_word_categorization')); ?>"
                        data-url-table-completion="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_table_completion'), 'cbt_download_question_template_word_table_completion')); ?>"
                        href="<?php echo esc_url(add_query_arg('question_count', 10, wp_nonce_url(admin_url('admin-post.php?action=cbt_download_question_template_word_mc'), 'cbt_download_question_template_word_mc'))); ?>"
                    >
                        Download Template Word MC (.docx)
                    </a>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cbt-questions-tab-submit="import" data-cbt-questions-async-form data-cbt-questions-progress-profile="import" data-cbt-questions-refresh-areas="notices,overview,import-status,list-panel" data-cbt-questions-success-tab="import">
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
                                    Template berbentuk <strong>tabel</strong> untuk <strong>multiple choice</strong>, <strong>multiple answer</strong>, <strong>true/false</strong>, <strong>true/false matrix</strong>, <strong>short answer</strong>, dan <strong>essay</strong> (gambar dan tabel bisa ditempel langsung di soal, opsi, maupun pembahasan).
                                    Gunakan template Word resmi terbaru dari tombol download di atas.
                                    Jika tipe file tidak cocok dengan menu aktif, sistem akan menolak import sebelum batch dijalankan.
                                    Gunakan field <code>PEMBAHASAN:</code> jika ingin mengisi pembahasan; gambar atau tabel setelah field ini akan masuk ke pembahasan.
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
                    <div data-cbt-questions-list-shell data-cbt-questions-refresh-area="list-panel">
                    <div class="cbt-questions-panel-header">
                        <div>
                            <h2>Question List</h2>
                            <p><?php echo esc_html($question_list_intro_text); ?></p>
                        </div>
                        <span class="cbt-questions-chip"><?php echo esc_html(sprintf('%d total', $total_questions)); ?></span>
                    </div>
                    <?php if ($question_import_batch_active): ?>
                        <div class="cbt-question-lineage-card" style="margin-bottom:16px;">
                            <div class="cbt-question-lineage-card-head" style="margin-bottom:10px;">
                                <div>
                                    <strong>Hasil Import Batch</strong>
                                    <p style="margin:4px 0 0; color:#4b5563;">List ini dibatasi hanya ke soal baru dari batch import aktif. Preview inline, delete row, delete selected, dan delete all batch tetap tersedia di sini.</p>
                                </div>
                                <span class="cbt-questions-chip">Batch Result</span>
                            </div>
                            <div class="cbt-questions-filter-summary" style="margin:0 0 12px;">
                                <span class="cbt-questions-chip"><?php echo esc_html('Created: ' . (string) $question_import_created); ?></span>
                                <span class="cbt-questions-chip"><?php echo esc_html('Failed: ' . (string) $question_import_failed); ?></span>
                                <?php if ($question_import_batch_subject_label !== ''): ?>
                                    <span class="cbt-questions-chip"><?php echo esc_html('Subject: ' . $question_import_batch_subject_label); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="cbt-questions-actions" style="margin:0;">
                                <a
                                    class="button button-secondary"
                                    href="<?php echo esc_url($question_import_batch_back_to_all_url); ?>"
                                    data-cbt-questions-tab-link="list"
                                    data-cbt-questions-async-link
                                    data-cbt-questions-progress-profile="list"
                                    data-cbt-questions-refresh-areas="notices,overview,list-panel"
                                    data-cbt-questions-success-tab="list"
                                >
                                    Kembali ke Semua Soal
                                </a>
                                <?php if (!empty($question_import_batch_created_question_ids)): ?>
                                    <a
                                        class="button button-secondary cbt-admin-btn--danger"
                                        href="<?php echo esc_url($question_import_batch_delete_all_url); ?>"
                                        data-cbt-questions-async-link
                                        data-cbt-questions-progress-profile="delete"
                                        data-cbt-questions-refresh-areas="notices,overview,import-status,list-panel"
                                        data-cbt-questions-success-tab="list"
                                        onclick="return confirm('Hapus semua soal hasil import batch ini?');"
                                    >
                                        Delete All Batch
                                    </a>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($question_lineage_info_cards)): ?>
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
                    <?php endif; ?>
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
                <div
                    class="cbt-questions-progress"
                    data-cbt-questions-delete-progress
                    data-cbt-questions-delete-running="<?php echo $question_delete_is_running ? '1' : '0'; ?>"
                    data-cbt-questions-delete-continue-url="<?php echo esc_url($question_delete_continue_url); ?>"
                    data-cbt-questions-progress-profile="delete"
                    data-cbt-questions-refresh-areas="notices,overview,list-panel"
                    data-cbt-questions-success-tab="list"
                >
                    <div class="cbt-questions-progress-track" aria-hidden="true">
                        <div class="cbt-questions-progress-fill" style="width: <?php echo esc_attr((string) $question_delete_progress_percent); ?>%;"></div>
                    </div>
                        <div class="cbt-questions-progress-meta">
                            <?php if ($question_delete_is_running): ?>
                                Memproses batch hapus soal berikutnya...
                        <?php else: ?>
                            <span style="color:#0a7a2f; font-weight:600;">Proses hapus soal selesai diproses.</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
                    <?php if (!$question_import_batch_active): ?>
                    <div class="cbt-questions-list-toolbar">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-questions-filter-form" data-cbt-questions-tab-submit="list" data-cbt-questions-progress-profile="list">
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

                            <a class="button button-secondary" href="<?php echo esc_url($question_reset_url); ?>" data-cbt-questions-tab-link="list" data-cbt-questions-list-reset="1" data-cbt-questions-progress-profile="list">Reset</a>
                        </form>
                    </div>
                    <div class="cbt-questions-filter-summary">
                        <span class="cbt-questions-chip"><?php echo esc_html('Type: ' . ($list_filter_type !== '' ? (string) ($question_type_labels[$list_filter_type] ?? $list_filter_type) : 'Semua tipe')); ?></span>
                        <span class="cbt-questions-chip"><?php echo esc_html('Sumber: ' . $list_filter_source_label); ?></span>
                        <span class="cbt-questions-chip"><?php echo esc_html('Subject: ' . $list_filter_subject_label); ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-cbt-questions-tab-submit="list" data-cbt-questions-async-form data-cbt-questions-progress-profile="delete" data-cbt-questions-refresh-areas="notices,overview,list-panel" data-cbt-questions-success-tab="list" onsubmit="return confirm('Hapus semua soal yang dipilih?');">
                <?php wp_nonce_field('cbt_bulk_delete_questions'); ?>
                <input type="hidden" name="action" value="cbt_bulk_delete_questions" />
                <input type="hidden" name="return_page" value="<?php echo esc_attr($current_page_slug); ?>" />
                <input type="hidden" name="redirect_filter_type" value="<?php echo esc_attr($list_filter_type); ?>" />
                <input type="hidden" name="redirect_filter_source_kind" value="<?php echo esc_attr($list_filter_source_kind); ?>" />
                <input type="hidden" name="redirect_filter_subject_id" value="<?php echo (int) $list_filter_subject_id; ?>" />
                <input type="hidden" name="redirect_question_per_page" value="<?php echo (int) $list_per_page; ?>" />
                <input type="hidden" name="redirect_question_paged" value="<?php echo (int) $list_current_page; ?>" />
                <?php if ($question_import_batch_active): ?>
                    <input type="hidden" name="cbt_question_import_token" value="<?php echo esc_attr($question_import_token); ?>" />
                    <input type="hidden" name="cbt_question_import_scope" value="<?php echo esc_attr($question_import_scope); ?>" />
                <?php endif; ?>
                <?php if (!empty($questions)): ?>
                <p class="cbt-questions-list-actions" style="margin: 0 0 8px;">
                    <button type="button" class="button button-secondary" id="cbt-view-selected-questions">Lihat Selected</button>
                    <button type="submit" class="button button-secondary cbt-admin-btn--danger">Delete Selected</button>
                </p>
                <?php endif; ?>
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
                        <?php
                        $question_has_filters = $list_filter_type !== '' || $list_filter_source_kind !== '' || $list_filter_subject_id > 0 || $question_import_batch_active;
                        echo CBT_Admin_UI_Helper::render_table_empty_state(8, [
                            'title' => $question_import_batch_active ? 'Belum ada soal sukses dari batch ini' : ($question_has_filters ? 'Tidak ada soal sesuai filter' : 'Belum ada soal'),
                            'message' => $question_import_batch_active
                                ? 'Batch import ini kosong atau semua baris soal gagal. Cek hasil import atau upload ulang.'
                                : ($question_has_filters ? 'Tidak ada soal yang cocok dengan filter saat ini. Reset filter untuk melihat semua soal.' : 'Tambah soal manual atau import bank soal agar bisa dipakai di exam.'),
                            'action_label' => $question_has_filters ? 'Reset Filter' : 'Tambah Soal',
                            'action_url' => $question_has_filters ? $question_reset_url : admin_url('admin.php?page=' . rawurlencode($current_page_slug)),
                            'action_class' => $question_has_filters ? 'button button-secondary cbt-admin-btn--secondary' : 'button button-primary',
                        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
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
                            $question_delete_args = [
                                'action' => 'cbt_delete_question',
                                'id' => (int) $question['id'],
                                'return_page' => $current_page_slug,
                                'filter_type' => $list_filter_type,
                                'filter_source_kind' => $list_filter_source_kind,
                                'filter_subject_id' => $list_filter_subject_id,
                                'question_per_page' => $list_per_page,
                                'question_paged' => $list_current_page,
                            ];
                            if ($question_import_batch_active && $question_import_token !== '' && $question_import_scope !== '') {
                                $question_delete_args['cbt_question_import_token'] = $question_import_token;
                                $question_delete_args['cbt_question_import_scope'] = $question_import_scope;
                            }
                            ?>
                            <tr id="cbt-question-row-<?php echo (int) $question['id']; ?>">
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
                                    <div class="cbt-admin-row-actions cbt-questions-row-actions">
                                        <a class="cbt-admin-action cbt-admin-action--view cbt-questions-row-action cbt-questions-row-action--view" data-cbt-questions-inline-view="1" href="<?php echo esc_url($question_is_view_open ? $question_hide_view_url : $question_view_url); ?>"><?php echo esc_html($question_is_view_open ? 'Hide' : 'Lihat'); ?></a>
                                        <?php if (!$question_import_batch_active): ?>
                                        <a class="cbt-admin-action cbt-admin-action--edit cbt-questions-row-action cbt-questions-row-action--edit" href="<?php echo esc_url($question_edit_url); ?>"><?php echo esc_html($question_edit_label); ?></a>
                                        <?php endif; ?>
                                        <a class="cbt-admin-action cbt-admin-action--delete cbt-questions-row-action cbt-questions-row-action--delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg($question_delete_args, admin_url('admin-post.php')), 'cbt_delete_question_' . (int) $question['id'])); ?>" data-cbt-questions-async-link data-cbt-questions-progress-profile="delete" data-cbt-questions-refresh-areas="notices,overview,list-panel" data-cbt-questions-success-tab="list" onclick="return confirm('Delete this question?');">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($question_is_reference_open): ?>
                                <tr class="cbt-admin-drawer-row cbt-question-reference-row">
                                    <td colspan="8">
                                        <div class="cbt-admin-drawer-panel cbt-question-reference-panel">
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
                                <?php
                                $view_preview_meta_lines = [];
                                $view_subject_name = trim((string) ($question['subject_name'] ?? ''));
                                if ($view_subject_name !== '') {
                                    $view_preview_meta_lines[] = 'Mapel: ' . $view_subject_name;
                                }
                                if ($question_exam_title !== '') {
                                    $view_preview_meta_lines[] = 'Exam: ' . $question_exam_title;
                                }
                                if ($question_is_bank_backed && $question_source_exam_title !== '') {
                                    $view_preview_meta_lines[] = 'Sumber bank: ' . $question_source_exam_title;
                                }
                                $view_preview_extra_chips = [];
                                if ($question_source_label !== '') {
                                    $view_preview_extra_chips[] = [
                                        'label' => $question_source_label,
                                        'tone' => 'source',
                                    ];
                                }
                                $view_preview_html = CBT_Admin_Questions_Helper::render_admin_student_preview_card(
                                    $view_question,
                                    $view_options,
                                    $view_detail,
                                    [
                                        'eyebrow' => 'Soal #' . (int) ($view_question['id'] ?? 0),
                                        'type_label' => (string) ($question_type_labels[(string) ($view_question['question_type'] ?? '')] ?? (string) ($view_question['question_type'] ?? '')),
                                        'meta_lines' => $view_preview_meta_lines,
                                        'extra_chips' => $view_preview_extra_chips,
                                    ]
                                );
                                ?>
                                <tr class="cbt-admin-drawer-row cbt-question-inline-preview-row" id="cbt-question-preview-<?php echo (int) $view_question['id']; ?>">
                                    <td colspan="8">
                                        <div class="cbt-admin-drawer-panel cbt-question-inline-preview">
                                            <div class="cbt-question-inline-preview-head">
                                                <div>
                                                    <span class="cbt-question-inline-preview-kicker">Preview Soal</span>
                                                    <div class="cbt-question-inline-preview-title">Soal #<?php echo (int) $view_question['id']; ?></div>
                                                    <?php if (!empty($view_preview_meta_lines)): ?>
                                                        <div class="cbt-question-inline-preview-meta"><?php echo esc_html(implode(' · ', $view_preview_meta_lines)); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <a class="button button-secondary" data-cbt-questions-inline-view="1" href="<?php echo esc_url($question_hide_view_url); ?>">Tutup Preview</a>
                                            </div>
                                            <div class="cbt-question-inline-preview-body">
                                                <?php echo $view_preview_html; ?>
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
                const supportsQuestionPartialRefresh = !!(window.fetch && window.DOMParser && window.FormData && window.URL);
                let questionProgressTimer = 0;
                let questionProgressValue = 0;
                let questionContinuationTimer = 0;
                let questionContinuationInFlight = false;

                function getQuestionProgressProfile(profile) {
                    const profiles = {
                        save: {
                            start: 'Menyimpan soal...',
                            detail: 'Validasi tipe soal, simpan detail, lalu segarkan ringkasan dan daftar soal.',
                            done: 'Soal sudah disimpan.',
                            doneDetail: 'Ringkasan dan Question List sudah diperbarui secara lokal.',
                        },
                        import: {
                            start: 'Memproses import soal...',
                            detail: 'Batch DOCX diproses bertahap, status import dan list akan disegarkan lokal.',
                            done: 'Import soal diperbarui.',
                            doneDetail: 'Status import, ringkasan, dan hasil Question List sudah dimuat ulang.',
                        },
                        delete: {
                            start: 'Menghapus soal...',
                            detail: 'Permintaan hapus diproses lalu Question List disegarkan tanpa reload halaman.',
                            done: 'Question List sudah diperbarui.',
                            doneDetail: 'Soal terhapus dan daftar sekarang memakai data terbaru.',
                        },
                        list: {
                            start: 'Memuat Question List...',
                            detail: 'Filter, preview, referensi, atau pagination sedang disegarkan di area daftar.',
                            done: 'Question List sudah diperbarui.',
                            doneDetail: 'Area daftar sekarang memuat hasil terbaru.',
                        },
                    };

                    return profiles[profile] || profiles.list;
                }

                function getQuestionProgressElements() {
                    const progress = page ? page.querySelector('[data-cbt-questions-progress]') : null;
                    if (!progress) {
                        return null;
                    }

                    return {
                        root: progress,
                        label: progress.querySelector('[data-cbt-questions-progress-label]'),
                        percent: progress.querySelector('[data-cbt-questions-progress-percent]'),
                        track: progress.querySelector('[data-cbt-questions-progress-track]'),
                        fill: progress.querySelector('[data-cbt-questions-progress-fill]'),
                        step: progress.querySelector('[data-cbt-questions-progress-step]'),
                    };
                }

                function clampQuestionProgress(value) {
                    return Math.max(0, Math.min(100, Math.round(Number(value) || 0)));
                }

                function setQuestionProgress(value, label, step, tone) {
                    const elements = getQuestionProgressElements();
                    if (!elements) {
                        return;
                    }

                    questionProgressValue = clampQuestionProgress(value);
                    elements.root.classList.add('is-active');
                    elements.root.classList.toggle('is-error', tone === 'error');
                    elements.root.setAttribute('aria-hidden', 'false');
                    elements.root.style.setProperty('--cbt-questions-progress', questionProgressValue + '%');
                    if (elements.percent) {
                        elements.percent.textContent = questionProgressValue + '%';
                    }
                    if (elements.track) {
                        elements.track.setAttribute('aria-valuenow', String(questionProgressValue));
                    }
                    if (elements.label && label) {
                        elements.label.textContent = label;
                    }
                    if (elements.step && step) {
                        elements.step.textContent = step;
                    }
                }

                function startQuestionProgress(profile) {
                    const config = getQuestionProgressProfile(profile);
                    window.clearInterval(questionProgressTimer);
                    setQuestionProgress(8, config.start, config.detail, 'active');
                    questionProgressTimer = window.setInterval(function () {
                        if (questionProgressValue >= 88) {
                            window.clearInterval(questionProgressTimer);
                            return;
                        }
                        setQuestionProgress(questionProgressValue + Math.max(2, Math.round((88 - questionProgressValue) / 7)), config.start, config.detail, 'active');
                    }, 360);
                }

                function completeQuestionProgress(label, step, tone) {
                    window.clearInterval(questionProgressTimer);
                    setQuestionProgress(tone === 'error' ? Math.max(questionProgressValue, 72) : 100, label, step, tone);
                    if (tone === 'error') {
                        return;
                    }

                    window.setTimeout(function () {
                        const elements = getQuestionProgressElements();
                        if (!elements) {
                            return;
                        }
                        elements.root.classList.remove('is-active', 'is-error');
                        elements.root.setAttribute('aria-hidden', 'true');
                    }, 1800);
                }

                function extractQuestionResponseError(html, status) {
                    const fallback = 'HTTP ' + String(status || 0);
                    if (!html) {
                        return fallback;
                    }

                    try {
                        const sanitizedHtml = String(html)
                            .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                            .replace(/<style[\s\S]*?<\/style>/gi, ' ');
                        const parsed = new DOMParser().parseFromString(sanitizedHtml, 'text/html');
                        const title = parsed.querySelector('title');
                        const bodyText = String((parsed.body && parsed.body.textContent) || '').replace(/\s+/g, ' ').trim();
                        const titleText = title ? String(title.textContent || '').replace(/\s+/g, ' ').trim() : '';
                        const message = bodyText || titleText || fallback;
                        return fallback + ': ' + message.slice(0, 220);
                    } catch (error) {
                        return fallback;
                    }
                }

                async function fetchQuestionHtml(nextUrl, options) {
                    const fetchOptions = Object.assign({
                        credentials: 'same-origin',
                        cache: 'no-store',
                        redirect: 'follow',
                        headers: {},
                    }, options || {});
                    fetchOptions.headers = Object.assign({
                        Accept: 'text/html, */*',
                        'X-Requested-With': 'XMLHttpRequest',
                    }, fetchOptions.headers || {});

                    const response = await window.fetch(nextUrl.toString(), fetchOptions);
                    const html = await response.text();
                    if (!response.ok) {
                        throw new Error(extractQuestionResponseError(html, response.status));
                    }

                    return {
                        html,
                        url: response.url || nextUrl.toString(),
                    };
                }

                function updateQuestionHistory(nextUrl) {
                    if (!window.history || typeof window.history.replaceState !== 'function') {
                        return;
                    }
                    const parsedUrl = new URL(nextUrl.toString(), window.location.href);
                    if (parsedUrl.origin !== window.location.origin) {
                        return;
                    }
                    window.history.replaceState({}, '', parsedUrl.toString());
                }

                function getQuestionAreaList(source, fallbackAreas) {
                    const raw = source ? String(source.getAttribute('data-cbt-questions-refresh-areas') || '') : '';
                    const parsed = raw.split(',').map((area) => area.trim()).filter(Boolean);
                    return parsed.length > 0 ? parsed : fallbackAreas;
                }

                function replaceQuestionRefreshAreas(html, areas) {
                    const parsed = new DOMParser().parseFromString(html, 'text/html');
                    const replaced = [];
                    (areas || []).forEach((area) => {
                        const currentArea = page ? page.querySelector('[data-cbt-questions-refresh-area="' + area + '"]') : null;
                        const nextArea = parsed.querySelector('[data-cbt-questions-refresh-area="' + area + '"]');
                        if (!currentArea || !nextArea) {
                            return;
                        }
                        currentArea.replaceWith(nextArea);
                        replaced.push(area);
                    });
                    return replaced;
                }

                function getQuestionTargetTab(source, replacedAreas) {
                    const explicit = source ? String(source.getAttribute('data-cbt-questions-success-tab') || '') : '';
                    if (explicit !== '') {
                        return explicit;
                    }
                    if ((replacedAreas || []).includes('import-status')) {
                        return 'import';
                    }
                    return 'list';
                }

                function setQuestionElementLoading(element, isLoading) {
                    if (!element) {
                        return;
                    }
                    element.classList.toggle('is-loading', isLoading);
                    element.setAttribute('aria-busy', isLoading ? 'true' : 'false');
                    if ('disabled' in element) {
                        element.disabled = isLoading;
                    }
                }

                function showQuestionLocalRefreshError(message) {
                    completeQuestionProgress('Gagal memperbarui area Questions.', message || 'Aksi masih bisa dicoba lagi tanpa reload global.', 'error');
                }

                function rebindQuestionLocalUi(replacedAreas, targetTab) {
                    bindQuestionListInteractions();
                    bindQuestionBatchAnalysisInteractions();
                    bindQuestionLocalActions();
                    bindQuestionContinuations();
                    activatePageTab(targetTab || 'list', true);
                }

                async function runQuestionLocalAction(source, requestUrl, options) {
                    if (!supportsQuestionPartialRefresh) {
                        showQuestionLocalRefreshError('Browser belum mendukung partial refresh untuk area Questions.');
                        return;
                    }

                    const profileName = source ? String(source.getAttribute('data-cbt-questions-progress-profile') || 'list') : 'list';
                    const profile = getQuestionProgressProfile(profileName);
                    const areas = getQuestionAreaList(source, ['notices', 'overview', 'list-panel']);
                    startQuestionProgress(profileName);
                    const result = await fetchQuestionHtml(requestUrl, options);
                    const replacedAreas = replaceQuestionRefreshAreas(result.html, areas);
                    if (replacedAreas.length === 0) {
                        throw new Error('Respons tidak memuat area Questions yang bisa diperbarui.');
                    }
                    updateQuestionHistory(new URL(result.url, window.location.href));
                    rebindQuestionLocalUi(replacedAreas, getQuestionTargetTab(source, replacedAreas));
                    completeQuestionProgress(profile.done, profile.doneDetail, 'success');
                }

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

                function scrollToQuestionHashTarget() {
                    if (!window.location.hash) {
                        return;
                    }

                    const targetId = String(window.location.hash || '').replace(/^#/, '');
                    if (!targetId) {
                        return;
                    }

                    const target = document.getElementById(targetId);
                    if (!target) {
                        return;
                    }

                    window.requestAnimationFrame(() => {
                        target.scrollIntoView({ behavior: 'auto', block: 'start' });
                    });
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
                    scrollToQuestionHashTarget();

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

                    window.addEventListener('hashchange', scrollToQuestionHashTarget);
                }

                const qTypeHidden = document.getElementById('cbt-question-type-hidden');
                const qTypeTabs = document.getElementById('cbt-question-type-tabs');
                const qTypeCurrent = document.getElementById('cbt-question-type-current');
                const qTypePanels = document.querySelectorAll('.cbt-qtype-panel');
                const qTypeToolbars = document.querySelectorAll('[data-cbt-qtype-toolbar]');
                const manualCountControls = Array.from(document.querySelectorAll('[data-cbt-manual-count]'));

                function clampManualCount(value, min, max) {
                    let safeValue = Number.isFinite(value) ? value : min;
                    if (safeValue < min) safeValue = min;
                    if (safeValue > max) safeValue = max;
                    return safeValue;
                }

                function getManualCountValue(selectId, fallback, min, max) {
                    const select = document.getElementById(selectId);
                    const parsed = parseInt(String(select?.value || fallback), 10);
                    return clampManualCount(Number.isFinite(parsed) ? parsed : fallback, min, max);
                }

                function syncSelectOptionLimit(select, count) {
                    if (!select) return;
                    Array.from(select.options || []).forEach((option) => {
                        const optionValue = parseInt(String(option.value || '0'), 10);
                        const isAllowed = optionValue <= count;
                        option.hidden = !isAllowed;
                        option.disabled = !isAllowed;
                    });
                    const selectedValue = parseInt(String(select.value || '1'), 10) || 1;
                    if (selectedValue > count) {
                        select.value = String(count);
                    }
                }

                function getWarningGroupsForCountTarget(target) {
                    if (target === 'cloze_dropdown' || target === 'cloze_option') {
                        return ['cloze_dropdown'];
                    }
                    if (target === 'cat_category' || target === 'cat_item') {
                        return ['cat_item'];
                    }
                    return target ? [target] : [];
                }

                function markManualCountWarning(target) {
                    getWarningGroupsForCountTarget(target).forEach((group) => {
                        document.querySelectorAll(`[data-cbt-count-warning="${group}"]`).forEach((warning) => {
                            warning.setAttribute('data-cbt-warning-visible', '1');
                        });
                    });
                }

                function syncManualCompactAuthoring() {
                    const counts = {};
                    const maxByGroup = {};
                    manualCountControls.forEach((select) => {
                        const group = String(select.getAttribute('data-target') || '');
                        if (group === '') return;
                        const min = parseInt(String(select.getAttribute('data-min') || '1'), 10) || 1;
                        const max = parseInt(String(select.getAttribute('data-max') || min), 10) || min;
                        const parsed = parseInt(String(select.value || min), 10);
                        const value = clampManualCount(Number.isFinite(parsed) ? parsed : min, min, max);
                        select.value = String(value);
                        counts[group] = value;
                        maxByGroup[group] = max;
                    });

                    document.querySelectorAll('[data-cbt-manual-row]').forEach((row) => {
                        const group = String(row.getAttribute('data-manual-row-group') || '');
                        const index = parseInt(String(row.getAttribute('data-manual-index') || '0'), 10) || 0;
                        const parentIndex = parseInt(String(row.getAttribute('data-manual-parent-index') || '0'), 10) || 0;
                        const limit = counts[group];
                        let isHidden = false;
                        if (Number.isFinite(limit) && limit > 0 && index > limit) {
                            isHidden = true;
                        }
                        if (group === 'cloze_option' && parentIndex > 0 && counts.cloze_dropdown && parentIndex > counts.cloze_dropdown) {
                            isHidden = true;
                        }
                        row.classList.toggle('cbt-manual-row-hidden', isHidden);
                    });

                    document.querySelectorAll('[data-cbt-manual-tag-group]').forEach((tagButton) => {
                        const group = String(tagButton.getAttribute('data-cbt-manual-tag-group') || '');
                        const index = parseInt(String(tagButton.getAttribute('data-cbt-manual-tag-index') || '0'), 10) || 0;
                        tagButton.classList.toggle('is-hidden', !!counts[group] && index > counts[group]);
                    });

                    document.querySelectorAll('[data-cbt-manual-option-bound]').forEach((select) => {
                        const group = String(select.getAttribute('data-cbt-manual-option-bound') || '');
                        syncSelectOptionLimit(select, counts[group] || 1);
                    });

                    const clozeOptionCount = counts.cloze_option || 6;
                    for (let blankIndex = 1; blankIndex <= 8; blankIndex += 1) {
                        syncSelectOptionLimit(document.getElementById(`cbt_cloze_correct_${blankIndex}`), clozeOptionCount);
                    }

                    const categoryCount = counts.cat_category || 8;
                    document.querySelectorAll('[data-cbt-cat-correct-select]').forEach((select) => {
                        syncSelectOptionLimit(select, categoryCount);
                    });

                    document.querySelectorAll('[data-cbt-count-warning]').forEach((warning) => {
                        const group = String(warning.getAttribute('data-cbt-count-warning') || '');
                        const wasTriggered = warning.getAttribute('data-cbt-warning-visible') === '1';
                        let hasHiddenActiveFields = false;
                        if (group === 'cloze_dropdown') {
                            hasHiddenActiveFields = (counts.cloze_dropdown || 8) < 8 || (counts.cloze_option || 6) < 6;
                        } else if (group === 'cat_item') {
                            hasHiddenActiveFields = (counts.cat_category || 8) < 8 || (counts.cat_item || 24) < 24;
                        } else {
                            hasHiddenActiveFields = !!maxByGroup[group] && counts[group] < maxByGroup[group];
                        }
                        if (!hasHiddenActiveFields) {
                            warning.removeAttribute('data-cbt-warning-visible');
                        }
                        warning.classList.toggle('is-visible', wasTriggered && hasHiddenActiveFields);
                    });
                }

                function activateQType(type, shouldFocus = false) {
                    if (!qTypeHidden) return;
                    qTypeHidden.value = type;
                    if (qTypeTabs) {
                        qTypeTabs.querySelectorAll('button[data-qtype]').forEach((btn) => {
                            const isActive = btn.getAttribute('data-qtype') === type;
                            btn.classList.toggle('cbt-active', isActive);
                            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            btn.tabIndex = isActive ? 0 : -1;
                            if (isActive && qTypeCurrent) {
                                qTypeCurrent.textContent = btn.textContent ? btn.textContent.trim() : type;
                            }
                        });
                    }
                    qTypePanels.forEach((panel) => {
                        panel.classList.toggle('cbt-active', panel.getAttribute('data-qtype') === type);
                    });
                    qTypeToolbars.forEach((toolbar) => {
                        const isActive = toolbar.getAttribute('data-cbt-qtype-toolbar') === type;
                        toolbar.classList.toggle('is-active', isActive);
                        toolbar.hidden = !isActive;
                    });
                    syncManualCompactAuthoring();

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
                    } else if (type === 'ordering') {
                        document.getElementById('cbt_ordering_item_1')?.focus();
                    } else if (type === 'matching') {
                        document.getElementById('cbt_matching_left_1')?.focus();
                    } else if (type === 'cloze_dropdown') {
                        document.getElementById('cbt_cloze_1_option_1')?.focus();
                    } else if (type === 'categorization') {
                        document.getElementById('cbt_cat_category_1')?.focus();
                    } else if (type === 'table_completion') {
                        document.querySelector('[data-cbt-table-select-cell="A1"]')?.focus();
                    }
                }

                if (qTypeTabs) {
                    const qTypeButtons = Array.from(qTypeTabs.querySelectorAll('button[data-qtype]'));
                    qTypeButtons.forEach((btn) => {
                        btn.addEventListener('click', () => activateQType(btn.getAttribute('data-qtype'), true));
                    });
                    qTypeTabs.addEventListener('keydown', (event) => {
                        const key = event.key;
                        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(key)) {
                            return;
                        }
                        const buttons = qTypeButtons.filter((button) => !button.disabled);
                        const currentIndex = buttons.indexOf(document.activeElement);
                        if (buttons.length === 0 || currentIndex < 0) {
                            return;
                        }
                        event.preventDefault();
                        let nextIndex = currentIndex;
                        if (key === 'Home') {
                            nextIndex = 0;
                        } else if (key === 'End') {
                            nextIndex = buttons.length - 1;
                        } else if (key === 'ArrowLeft') {
                            nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
                        } else if (key === 'ArrowRight') {
                            nextIndex = (currentIndex + 1) % buttons.length;
                        }
                        const nextButton = buttons[nextIndex];
                        if (!nextButton) return;
                        nextButton.focus();
                        activateQType(nextButton.getAttribute('data-qtype'), false);
                    });
                }
                activateQType(qTypeHidden?.value || 'multiple_choice');

                manualCountControls.forEach((select) => {
                    select.setAttribute('data-cbt-previous-count', String(select.value || ''));
                    select.addEventListener('change', () => {
                        const previous = parseInt(String(select.getAttribute('data-cbt-previous-count') || select.value || '0'), 10);
                        const current = parseInt(String(select.value || '0'), 10);
                        if (Number.isFinite(previous) && Number.isFinite(current) && current < previous) {
                            markManualCountWarning(String(select.getAttribute('data-target') || ''));
                        }
                        select.setAttribute('data-cbt-previous-count', String(select.value || ''));
                        syncManualCompactAuthoring();
                    });
                });
                document.querySelectorAll('[data-cbt-count-target][data-cbt-count-step]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const targetId = String(button.getAttribute('data-cbt-count-target') || '');
                        const step = parseInt(String(button.getAttribute('data-cbt-count-step') || '0'), 10) || 0;
                        const select = document.getElementById(targetId);
                        if (!select || step === 0) return;
                        const min = parseInt(String(select.getAttribute('data-min') || '1'), 10) || 1;
                        const max = parseInt(String(select.getAttribute('data-max') || min), 10) || min;
                        const current = parseInt(String(select.value || min), 10) || min;
                        select.value = String(clampManualCount(current + step, min, max));
                        select.dispatchEvent(new window.Event('change', { bubbles: true }));
                    });
                });
                syncManualCompactAuthoring();

                const tableRowsSelect = document.getElementById('cbt_table_rows');
                const tableColsSelect = document.getElementById('cbt_table_cols');
                const tablePanel = document.querySelector('[data-cbt-table-side-panel]');
                const tablePanelKey = document.querySelector('[data-cbt-table-panel-key]');
                const tablePanelSummary = document.querySelector('[data-cbt-table-panel-summary]');
                const tablePanelTypeLabel = document.querySelector('[data-cbt-table-panel-type-label]');
                const tablePanelType = document.querySelector('[data-cbt-table-panel-type]');
                const tablePanelText = document.querySelector('[data-cbt-table-panel-text]');
                const tablePanelTextCaption = document.querySelector('[data-cbt-table-panel-text-caption]');
                const tablePanelAnswer = document.querySelector('[data-cbt-table-panel-answer]');
                const tablePanelCorrect = document.querySelector('[data-cbt-table-panel-correct]');
                const tableActiveSize = document.querySelector('[data-cbt-table-active-size]');
                const tableAnswerCount = document.querySelector('[data-cbt-table-answer-count]');
                let activeTableCellKey = '';

                function getTableCellFields() {
                    return Array.from(document.querySelectorAll('[data-cbt-table-cell-field]'));
                }

                function getTableCellField(cellKey) {
                    const key = String(cellKey || '').toUpperCase();
                    return getTableCellFields().find((field) => String(field.getAttribute('data-cbt-table-cell-key') || '').toUpperCase() === key) || null;
                }

                function getTableCellKey(field) {
                    return String(field?.getAttribute('data-cbt-table-cell-key') || '').toUpperCase();
                }

                function getTableCellControl(field, suffix) {
                    const key = getTableCellKey(field);
                    if (key === '') return null;
                    return document.getElementById(`cbt_table_${key}_${suffix}`);
                }

                function getTableCellType(field) {
                    const select = field ? field.querySelector('[data-cbt-table-cell-type]') : null;
                    const value = String(select?.value || 'static');
                    return ['static', 'text', 'dropdown'].includes(value) ? value : 'static';
                }

                function getTableTypeLabel(type) {
                    if (type === 'dropdown') return 'Dropdown';
                    if (type === 'text') return 'Text';
                    return 'Statis';
                }

                function compactTableText(value, maxLength = 46) {
                    const text = String(value || '').replace(/\s+/g, ' ').trim();
                    if (text.length <= maxLength) return text;
                    return `${text.slice(0, Math.max(0, maxLength - 3)).trim()}...`;
                }

                function getTableOptionInput(field, optionIndex) {
                    return getTableCellControl(field, `option_${optionIndex}`);
                }

                function countTableDropdownOptions(field) {
                    let count = 0;
                    for (let optionIndex = 1; optionIndex <= 6; optionIndex += 1) {
                        const input = getTableOptionInput(field, optionIndex);
                        if (String(input?.value || '').trim() !== '') {
                            count += 1;
                        }
                    }
                    return count;
                }

                function buildTableCellSummary(field) {
                    const type = getTableCellType(field);
                    const textValue = compactTableText(getTableCellControl(field, 'text')?.value || '');
                    if (type === 'static') {
                        return textValue !== '' ? textValue : 'Sel kosong';
                    }

                    if (type === 'text') {
                        const answerValue = compactTableText(getTableCellControl(field, 'answer')?.value || '');
                        if (answerValue !== '') {
                            return `Kunci: ${answerValue}`;
                        }
                        return textValue !== '' ? `${textValue} • belum ada kunci` : 'Isian teks belum ada kunci';
                    }

                    const optionCount = countTableDropdownOptions(field);
                    const correctValue = String(getTableCellControl(field, 'correct')?.value || '1');
                    return `${optionCount} opsi • kunci opsi ${correctValue}`;
                }

                function updateTableCellCard(field) {
                    if (!field) return;
                    const type = getTableCellType(field);
                    field.setAttribute('data-cbt-cell-type', type);
                    field.classList.toggle('is-static', type === 'static');
                    field.classList.toggle('is-text', type === 'text');
                    field.classList.toggle('is-dropdown', type === 'dropdown');

                    const textCaption = field.querySelector('[data-cbt-table-text-caption]');
                    if (textCaption) {
                        textCaption.textContent = type === 'static' ? 'Teks tetap' : 'Label sel (opsional)';
                    }
                    const textInput = field.querySelector('[data-cbt-table-cell-text]');
                    if (textInput) {
                        textInput.setAttribute('placeholder', type === 'static' ? 'Teks yang tampil di tabel' : 'Label/petunjuk opsional');
                    }
                    field.querySelectorAll('[data-cbt-table-mode]').forEach((node) => {
                        node.hidden = node.getAttribute('data-cbt-table-mode') !== type;
                    });

                    const typeLabel = field.querySelector('[data-cbt-table-type-label]');
                    if (typeLabel) {
                        typeLabel.textContent = getTableTypeLabel(type);
                    }
                    const summary = field.querySelector('[data-cbt-table-cell-summary]');
                    if (summary) {
                        summary.textContent = buildTableCellSummary(field);
                    }
                }

                function syncTablePanelMode(type) {
                    if (tablePanelTextCaption) {
                        tablePanelTextCaption.textContent = type === 'static' ? 'Teks tetap' : 'Label sel (opsional)';
                    }
                    if (tablePanelText) {
                        tablePanelText.setAttribute('placeholder', type === 'static' ? 'Teks yang tampil di tabel' : 'Label/petunjuk opsional');
                    }
                    document.querySelectorAll('[data-cbt-table-panel-mode]').forEach((node) => {
                        node.hidden = node.getAttribute('data-cbt-table-panel-mode') !== type;
                    });
                }

                function syncTablePanelFromActiveCell() {
                    if (!tablePanel) return;
                    const field = getTableCellField(activeTableCellKey);
                    if (!field || field.hidden) {
                        return;
                    }

                    const type = getTableCellType(field);
                    if (tablePanelKey) tablePanelKey.textContent = getTableCellKey(field);
                    if (tablePanelSummary) tablePanelSummary.textContent = buildTableCellSummary(field);
                    if (tablePanelTypeLabel) tablePanelTypeLabel.textContent = getTableTypeLabel(type);
                    if (tablePanelType) tablePanelType.value = type;
                    if (tablePanelText) tablePanelText.value = String(getTableCellControl(field, 'text')?.value || '');
                    if (tablePanelAnswer) tablePanelAnswer.value = String(getTableCellControl(field, 'answer')?.value || '');
                    for (let optionIndex = 1; optionIndex <= 6; optionIndex += 1) {
                        const panelOption = document.querySelector(`[data-cbt-table-panel-option="${optionIndex}"]`);
                        const actualOption = getTableOptionInput(field, optionIndex);
                        if (panelOption) {
                            panelOption.value = String(actualOption?.value || '');
                        }
                    }
                    if (tablePanelCorrect) {
                        tablePanelCorrect.value = String(getTableCellControl(field, 'correct')?.value || '1');
                    }
                    syncTablePanelMode(type);
                }

                function positionTablePanelForActiveCell() {
                    if (!tablePanel) return;
                    const field = getTableCellField(activeTableCellKey);
                    const rowEl = field ? field.closest('.cbt-table-author-row[data-table-row]') : null;
                    const matrix = document.getElementById('cbt-table-author-matrix');
                    if (!field || !rowEl || !matrix || field.hidden || rowEl.hidden || rowEl.parentElement !== matrix) {
                        return;
                    }
                    if (tablePanel.parentElement !== matrix || tablePanel.previousElementSibling !== rowEl) {
                        rowEl.insertAdjacentElement('afterend', tablePanel);
                    }
                }

                function selectTableCell(cellKey, shouldFocusPanel = false) {
                    const field = getTableCellField(cellKey);
                    if (!field || field.hidden) {
                        return;
                    }
                    activeTableCellKey = getTableCellKey(field);
                    getTableCellFields().forEach((candidate) => {
                        const isActive = getTableCellKey(candidate) === activeTableCellKey;
                        candidate.classList.toggle('is-selected', isActive);
                        const button = candidate.querySelector('[data-cbt-table-select-cell]');
                        if (button) {
                            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                        }
                    });
                    positionTablePanelForActiveCell();
                    syncTablePanelFromActiveCell();
                    if (shouldFocusPanel && tablePanelType) {
                        tablePanelType.focus();
                    }
                }

                function syncActiveTableCellFallback() {
                    const activeField = getTableCellField(activeTableCellKey);
                    if (activeField && !activeField.hidden) {
                        selectTableCell(activeTableCellKey);
                        return;
                    }
                    const firstVisible = getTableCellFields().find((field) => !field.hidden);
                    if (firstVisible) {
                        selectTableCell(getTableCellKey(firstVisible));
                    }
                }

                function writeTablePanelToActiveCell() {
                    const field = getTableCellField(activeTableCellKey);
                    if (!field || field.hidden) return;
                    const type = String(tablePanelType?.value || 'static');
                    const actualType = field.querySelector('[data-cbt-table-cell-type]');
                    if (actualType) actualType.value = ['static', 'text', 'dropdown'].includes(type) ? type : 'static';
                    const actualText = getTableCellControl(field, 'text');
                    if (actualText && tablePanelText) actualText.value = tablePanelText.value;
                    const actualAnswer = getTableCellControl(field, 'answer');
                    if (actualAnswer && tablePanelAnswer) actualAnswer.value = tablePanelAnswer.value;
                    for (let optionIndex = 1; optionIndex <= 6; optionIndex += 1) {
                        const panelOption = document.querySelector(`[data-cbt-table-panel-option="${optionIndex}"]`);
                        const actualOption = getTableOptionInput(field, optionIndex);
                        if (panelOption && actualOption) {
                            actualOption.value = panelOption.value;
                        }
                    }
                    const actualCorrect = getTableCellControl(field, 'correct');
                    if (actualCorrect && tablePanelCorrect) actualCorrect.value = tablePanelCorrect.value;
                    updateTableCellCard(field);
                    updateTableDesignerStats();
                    const currentType = getTableCellType(field);
                    syncTablePanelMode(currentType);
                    if (tablePanelSummary) tablePanelSummary.textContent = buildTableCellSummary(field);
                    if (tablePanelTypeLabel) tablePanelTypeLabel.textContent = getTableTypeLabel(currentType);
                }

                function updateTableDesignerStats() {
                    const rowCount = Math.max(2, Math.min(8, parseInt(String(tableRowsSelect?.value || '2'), 10) || 2));
                    const colCount = Math.max(2, Math.min(6, parseInt(String(tableColsSelect?.value || '2'), 10) || 2));
                    let answerCells = 0;
                    let textCells = 0;
                    let dropdownCells = 0;
                    getTableCellFields().forEach((field) => {
                        if (field.hidden) {
                            return;
                        }
                        const type = getTableCellType(field);
                        if (type === 'text') {
                            answerCells += 1;
                            textCells += 1;
                        } else if (type === 'dropdown') {
                            answerCells += 1;
                            dropdownCells += 1;
                        }
                    });
                    if (tableActiveSize) {
                        tableActiveSize.textContent = `${rowCount}x${colCount}`;
                    }
                    if (tableAnswerCount) {
                        tableAnswerCount.textContent = `${answerCells} sel jawaban (${textCells} text, ${dropdownCells} dropdown)`;
                    }
                }

                function syncTableCompletionAuthoring() {
                    const rowCount = Math.max(2, Math.min(8, parseInt(String(tableRowsSelect?.value || '2'), 10) || 2));
                    const colCount = Math.max(2, Math.min(6, parseInt(String(tableColsSelect?.value || '2'), 10) || 2));
                    const matrix = document.getElementById('cbt-table-author-matrix');
                    if (matrix) {
                        matrix.style.setProperty('--cbt-table-cols', String(colCount));
                    }
                    document.querySelectorAll('[data-table-row][data-table-col]').forEach((cell) => {
                        const row = parseInt(String(cell.getAttribute('data-table-row') || '0'), 10);
                        const col = parseInt(String(cell.getAttribute('data-table-col') || '0'), 10);
                        const isHidden = row > rowCount || col > colCount;
                        cell.hidden = isHidden;
                        cell.classList.toggle('is-hidden', isHidden);
                    });
                    document.querySelectorAll('.cbt-table-author-row[data-table-row]').forEach((rowEl) => {
                        const row = parseInt(String(rowEl.getAttribute('data-table-row') || '0'), 10);
                        const isHidden = row > rowCount;
                        rowEl.hidden = isHidden;
                        rowEl.classList.toggle('is-hidden', isHidden);
                    });
                    getTableCellFields().forEach((field) => {
                        updateTableCellCard(field);
                    });
                    updateTableDesignerStats();
                    syncActiveTableCellFallback();
                }

                if (tableRowsSelect) tableRowsSelect.addEventListener('change', syncTableCompletionAuthoring);
                if (tableColsSelect) tableColsSelect.addEventListener('change', syncTableCompletionAuthoring);
                document.querySelectorAll('[data-cbt-table-cell-type]').forEach((select) => {
                    select.addEventListener('change', syncTableCompletionAuthoring);
                });
                document.querySelectorAll('[data-cbt-table-select-cell]').forEach((button) => {
                    button.addEventListener('click', () => {
                        selectTableCell(String(button.getAttribute('data-cbt-table-select-cell') || ''), true);
                    });
                    button.addEventListener('keydown', (event) => {
                        if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                            return;
                        }
                        const field = button.closest('[data-cbt-table-cell-field]');
                        if (!field) return;
                        const rowCount = Math.max(2, Math.min(8, parseInt(String(tableRowsSelect?.value || '2'), 10) || 2));
                        const colCount = Math.max(2, Math.min(6, parseInt(String(tableColsSelect?.value || '2'), 10) || 2));
                        let row = parseInt(String(field.getAttribute('data-table-row') || '1'), 10) || 1;
                        let col = parseInt(String(field.getAttribute('data-table-col') || '1'), 10) || 1;
                        if (event.key === 'ArrowUp') row -= 1;
                        if (event.key === 'ArrowDown') row += 1;
                        if (event.key === 'ArrowLeft') col -= 1;
                        if (event.key === 'ArrowRight') col += 1;
                        row = Math.max(1, Math.min(rowCount, row));
                        col = Math.max(1, Math.min(colCount, col));
                        const nextKey = `${String.fromCharCode(64 + col)}${row}`;
                        const nextField = getTableCellField(nextKey);
                        const nextButton = nextField ? nextField.querySelector('[data-cbt-table-select-cell]') : null;
                        if (nextButton) {
                            event.preventDefault();
                            selectTableCell(nextKey);
                            nextButton.focus();
                        }
                    });
                });
                [tablePanelType, tablePanelCorrect].forEach((control) => {
                    if (control) control.addEventListener('change', writeTablePanelToActiveCell);
                });
                [tablePanelText, tablePanelAnswer].forEach((control) => {
                    if (control) control.addEventListener('input', writeTablePanelToActiveCell);
                });
                document.querySelectorAll('[data-cbt-table-panel-option]').forEach((input) => {
                    input.addEventListener('input', writeTablePanelToActiveCell);
                });
                syncTableCompletionAuthoring();

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
                const wordTemplateControls = Array.from(document.querySelectorAll('[data-template-control]')).map((control) => ({
                    key: control.getAttribute('data-template-control') || '',
                    control,
                    select: control.querySelector('[data-template-select]'),
                })).filter((entry) => entry.key && entry.select);
                const docxFileAccept = '.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                const importHelpSuffix = ' Gambar dan tabel di soal, opsi, serta pembahasan didukung. Wajib gunakan template resmi terbaru dan jangan hapus marker CBT_TEMPLATE atau field JENIS_SOAL.';

                const importTypeInfo = {
                    multiple_choice: {
                        help: 'Mode import aktif: Multiple Choice. DOCX didukung (PILIHAN_1..N sesuai Jumlah Pilihan 3-5, JAWABAN berupa satu nomor opsi 1..N, opsi tidak boleh duplikat).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word MC (.docx)',
                        urlKey: 'urlMc',
                        templateControls: {
                            option_count: { min: 3, max: 5, defaultValue: 5 },
                        },
                    },
                    multiple_answer: {
                        help: 'Mode import aktif: Multiple Answer. DOCX didukung (PILIHAN_1..N sesuai Jumlah Pilihan 3-12, JAWABAN boleh lebih dari satu seperti 1,3,5, minimal 1 benar, opsi tidak boleh duplikat).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word MA (.docx)',
                        urlKey: 'urlMa',
                        templateControls: {
                            option_count: { min: 3, max: 12, defaultValue: 5 },
                        },
                    },
                    true_false: {
                        help: 'Mode import aktif: True/False. DOCX didukung (jawaban: true/false, field opsional PEMBAHASAN didukung).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word TF (.docx)',
                        urlKey: 'urlTf',
                    },
                    true_false_matrix: {
                        help: 'Mode import aktif: True/False Matrix. DOCX didukung (PERNYATAAN_1..N dan KUNCI_1..N sesuai Jumlah Pernyataan 2-10, kunci true/false berurutan tanpa nomor loncat).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word TF Matrix (.docx)',
                        urlKey: 'urlTfm',
                        templateControls: {
                            statement_count: { min: 2, max: 10, defaultValue: 5 },
                        },
                    },
                    short_answer: {
                        help: 'Mode import aktif: Short Answer. DOCX didukung (pakai placeholder [INPUT_1]..[INPUT_N] sesuai Jumlah Input 1-8, lalu isi JAWABAN_A..H sesuai key placeholder).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word SA (.docx)',
                        urlKey: 'urlSa',
                        templateControls: {
                            input_count: { min: 1, max: 8, defaultValue: 3 },
                        },
                    },
                    essay: {
                        help: 'Mode import aktif: Essay. DOCX didukung (wajib isi acuan jawaban/rubrik, field opsional PEMBAHASAN didukung).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word Essay (.docx)',
                        urlKey: 'urlEssay',
                    },
                    ordering: {
                        help: 'Mode import aktif: Ordering. DOCX didukung (ITEM_1..N sesuai Jumlah Item 2-12 ditulis dalam urutan benar, item tidak boleh duplikat).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word Ordering (.docx)',
                        urlKey: 'urlOrdering',
                        templateControls: {
                            item_count: { min: 2, max: 12, defaultValue: 4 },
                        },
                    },
                    matching: {
                        help: 'Mode import aktif: Matching. DOCX didukung (KIRI_1..N dan KANAN_1..N sesuai Jumlah Pasangan 2-12; KANAN_n adalah pasangan benar KIRI_n).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word Matching (.docx)',
                        urlKey: 'urlMatching',
                        templateControls: {
                            pair_count: { min: 2, max: 12, defaultValue: 3 },
                        },
                    },
                    cloze_dropdown: {
                        help: 'Mode import aktif: Cloze Dropdown. DOCX didukung (pakai [DROPDOWN_1]..[DROPDOWN_N], isi DROPDOWN_n_OPSI_1..M dan DROPDOWN_n_JAWABAN; tiap dropdown tepat 1 kunci).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word Cloze (.docx)',
                        urlKey: 'urlCloze',
                        templateControls: {
                            dropdown_count: { min: 1, max: 8, defaultValue: 2 },
                            dropdown_option_count: { min: 2, max: 6, defaultValue: 3 },
                        },
                    },
                    categorization: {
                        help: 'Mode import aktif: Categorization. DOCX didukung (KATEGORI_1..N, ITEM_1..M, dan KUNCI_1..M berisi nomor atau teks kategori benar).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word Categorization (.docx)',
                        urlKey: 'urlCategorization',
                        templateControls: {
                            category_count: { min: 2, max: 8, defaultValue: 2 },
                            categorization_item_count: { min: 2, max: 24, defaultValue: 3 },
                        },
                    },
                    table_completion: {
                        help: 'Mode import aktif: Table Completion. DOCX didukung (TABLE_ROWS, TABLE_COLS, lalu CELL_A1_TYPE/TEXT/JAWABAN/OPSI sesuai ukuran tabel).' + importHelpSuffix,
                        buttonLabel: 'Download Template Word Table Completion (.docx)',
                        urlKey: 'urlTableCompletion',
                        templateControls: {
                            table_rows: { min: 2, max: 8, defaultValue: 2 },
                            table_cols: { min: 2, max: 6, defaultValue: 2 },
                        },
                    },
                };

                function activateImportType(type) {
                    if (!importTypeHidden) return;

                    const activeType = Object.prototype.hasOwnProperty.call(importTypeInfo, type) ? type : 'multiple_choice';
                    const info = importTypeInfo[activeType] || importTypeInfo.multiple_choice;
                    const templateControls = info.templateControls || {};
                    importTypeHidden.value = activeType;

                    if (importTypeTabs) {
                        importTypeTabs.querySelectorAll('button[data-import-type]').forEach((btn) => {
                            btn.classList.toggle('cbt-active', btn.getAttribute('data-import-type') === activeType);
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
                        let selectedCount = Math.floor(safeCount / 5) * 5;
                        if (selectedCount < 5) selectedCount = 5;
                        if (selectedCount > 100) selectedCount = 100;
                        if (wordTemplateCount && String(wordTemplateCount.value) !== String(selectedCount)) {
                            wordTemplateCount.value = String(selectedCount);
                        }
                        const templateParams = {
                            question_count: selectedCount,
                        };
                        wordTemplateControls.forEach(({ key, control, select }) => {
                            const config = templateControls[key];
                            const isActive = !!config;
                            control.hidden = !isActive;
                            select.hidden = !isActive;
                            select.disabled = !isActive;
                            const min = parseInt(String(config?.min ?? '1'), 10) || 1;
                            const max = parseInt(String(config?.max ?? min), 10) || min;
                            const defaultValue = parseInt(String(config?.defaultValue ?? min), 10) || min;
                            Array.from(select.options || []).forEach((option) => {
                                const optionValue = parseInt(String(option.value || '0'), 10);
                                const isAllowed = isActive && optionValue >= min && optionValue <= max;
                                option.hidden = !isAllowed;
                                option.disabled = !isAllowed;
                            });
                            if (!isActive) {
                                return;
                            }
                            const parsedValue = parseInt(String(select.value || defaultValue), 10);
                            let selectedValue = Number.isFinite(parsedValue) ? parsedValue : defaultValue;
                            if (selectedValue < min) selectedValue = min;
                            if (selectedValue > max) selectedValue = max;
                            select.value = String(selectedValue);
                            templateParams[key] = selectedValue;
                        });
                        const baseUrl = wordTemplateButton.dataset[info.urlKey] || '';
                        if (baseUrl) {
                            const separator = baseUrl.includes('?') ? '&' : '?';
                            const query = Object.keys(templateParams).map((key) => {
                                return `${encodeURIComponent(key)}=${encodeURIComponent(String(templateParams[key]))}`;
                            }).join('&');
                            const templateUrl = `${baseUrl}${separator}${query}`;
                            wordTemplateButton.setAttribute('href', templateUrl);
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
                wordTemplateControls.forEach(({ select }) => {
                    select.addEventListener('change', () => {
                        activateImportType(importTypeHidden?.value || 'multiple_choice');
                    });
                });

                activateImportType(importTypeHidden?.value || 'multiple_choice');

                const batchAnalysisRoot = document.querySelector('[data-cbt-import-batch-analysis]');
                if (batchAnalysisRoot) {
                    const navItems = Array.from(batchAnalysisRoot.querySelectorAll('[data-cbt-import-batch-analysis-nav-item]'));
                    const detailPanels = Array.from(batchAnalysisRoot.querySelectorAll('[data-cbt-import-batch-analysis-panel]'));

                    const applyBatchAnalysisFilter = (panel, kind) => {
                        if (!panel) return;
                        const normalizedKind = String(kind || 'needs-review');
                        const filterButtons = Array.from(panel.querySelectorAll('[data-cbt-import-batch-analysis-filter]'));
                        const analysisItems = Array.from(panel.querySelectorAll('[data-diagnostic-kind]'));
                        const emptyState = panel.querySelector('[data-cbt-import-batch-analysis-empty]');
                        let visibleCount = 0;

                        filterButtons.forEach((button) => {
                            button.classList.toggle('is-active', button.getAttribute('data-cbt-import-batch-analysis-filter') === normalizedKind);
                        });

                        analysisItems.forEach((item) => {
                            const itemKind = String(item.getAttribute('data-diagnostic-kind') || '');
                            const shouldShow = normalizedKind === 'all'
                                ? true
                                : (normalizedKind === 'needs-review'
                                    ? (itemKind === 'fallback' || itemKind === 'unsupported')
                                    : itemKind === normalizedKind);
                            item.hidden = !shouldShow;
                            if (shouldShow) {
                                visibleCount += 1;
                            }
                        });

                        if (emptyState) {
                            const emptyMessage = normalizedKind === 'needs-review'
                                ? 'Soal ini tidak punya catatan yang perlu dicek.'
                                : 'Tidak ada diagnostics untuk filter ini pada soal terpilih.';
                            emptyState.textContent = emptyMessage;
                            emptyState.hidden = visibleCount > 0;
                        }
                    };

                    const activateBatchAnalysisQuestion = (questionId) => {
                        const normalizedId = String(questionId || '');
                        navItems.forEach((item) => {
                            item.classList.toggle('is-active', String(item.getAttribute('data-question-id') || '') === normalizedId);
                        });
                        detailPanels.forEach((panel) => {
                            const isActive = String(panel.getAttribute('data-question-id') || '') === normalizedId;
                            panel.classList.toggle('is-active', isActive);
                            if (isActive) {
                                const defaultFilter = panel.querySelector('[data-cbt-import-batch-analysis-filter="needs-review"]');
                                applyBatchAnalysisFilter(panel, defaultFilter ? 'needs-review' : 'all');
                            }
                        });
                    };

                    navItems.forEach((item) => {
                        item.addEventListener('click', () => {
                            activateBatchAnalysisQuestion(item.getAttribute('data-question-id') || '');
                        });
                    });

                    detailPanels.forEach((panel) => {
                        Array.from(panel.querySelectorAll('[data-cbt-import-batch-analysis-filter]')).forEach((button) => {
                            button.addEventListener('click', () => {
                                applyBatchAnalysisFilter(panel, button.getAttribute('data-cbt-import-batch-analysis-filter') || 'needs-review');
                            });
                        });
                    });

                    const initiallyActiveItem = navItems.find((item) => item.classList.contains('is-active')) || navItems[0];
                    if (initiallyActiveItem) {
                        activateBatchAnalysisQuestion(initiallyActiveItem.getAttribute('data-question-id') || '');
                    }
                }

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
                        showQuestionLocalRefreshError('Panel Question List tidak ditemukan untuk diperbarui lokal.');
                        return;
                    }

                    const currentRequestId = ++questionListRequestId;
                    questionListPanel.classList.add('cbt-is-loading');
                    startQuestionProgress('list');

                    try {
                        const result = await fetchQuestionHtml(new URL(url, window.location.href), {});
                        if (currentRequestId !== questionListRequestId) {
                            return;
                        }

                        const parsed = new window.DOMParser().parseFromString(result.html, 'text/html');
                        const incomingShell = parsed.querySelector('[data-cbt-questions-list-shell]');
                        const currentShell = questionListPanel.querySelector('[data-cbt-questions-list-shell]');

                        if (!incomingShell || !currentShell) {
                            throw new Error('Respons tidak memuat panel daftar soal.');
                            return;
                        }

                        currentShell.replaceWith(incomingShell);
                        updateQuestionHistory(new URL(result.url, window.location.href));
                        bindQuestionListInteractions();
                        bindQuestionLocalActions();
                        bindQuestionContinuations();
                        completeQuestionProgress(getQuestionProgressProfile('list').done, getQuestionProgressProfile('list').doneDetail, 'success');
                    } catch (error) {
                        showQuestionLocalRefreshError(error && error.message ? error.message : 'Question List belum bisa dimuat lokal.');
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

                function bindQuestionBatchAnalysisInteractions() {
                    const roots = Array.from(document.querySelectorAll('[data-cbt-import-batch-analysis]'));
                    roots.forEach((root) => {
                        if (!root || root.dataset.cbtBatchAnalysisBound === '1') {
                            return;
                        }
                        root.dataset.cbtBatchAnalysisBound = '1';
                        const navItems = Array.from(root.querySelectorAll('[data-cbt-import-batch-analysis-nav-item]'));
                        const detailPanels = Array.from(root.querySelectorAll('[data-cbt-import-batch-analysis-panel]'));

                        const applyBatchAnalysisFilterLocal = (panel, kind) => {
                            if (!panel) return;
                            const normalizedKind = String(kind || 'needs-review');
                            const filterButtons = Array.from(panel.querySelectorAll('[data-cbt-import-batch-analysis-filter]'));
                            const analysisItems = Array.from(panel.querySelectorAll('[data-diagnostic-kind]'));
                            const emptyState = panel.querySelector('[data-cbt-import-batch-analysis-empty]');
                            let visibleCount = 0;

                            filterButtons.forEach((button) => {
                                button.classList.toggle('is-active', button.getAttribute('data-cbt-import-batch-analysis-filter') === normalizedKind);
                            });

                            analysisItems.forEach((item) => {
                                const itemKind = String(item.getAttribute('data-diagnostic-kind') || '');
                                const shouldShow = normalizedKind === 'all'
                                    ? true
                                    : (normalizedKind === 'needs-review'
                                        ? (itemKind === 'fallback' || itemKind === 'unsupported')
                                        : itemKind === normalizedKind);
                                item.hidden = !shouldShow;
                                if (shouldShow) {
                                    visibleCount += 1;
                                }
                            });

                            if (emptyState) {
                                emptyState.textContent = normalizedKind === 'needs-review'
                                    ? 'Soal ini tidak punya catatan yang perlu dicek.'
                                    : 'Tidak ada diagnostics untuk filter ini pada soal terpilih.';
                                emptyState.hidden = visibleCount > 0;
                            }
                        };

                        const activateBatchAnalysisQuestionLocal = (questionId) => {
                            const normalizedId = String(questionId || '');
                            navItems.forEach((item) => {
                                item.classList.toggle('is-active', String(item.getAttribute('data-question-id') || '') === normalizedId);
                            });
                            detailPanels.forEach((panel) => {
                                const isActive = String(panel.getAttribute('data-question-id') || '') === normalizedId;
                                panel.classList.toggle('is-active', isActive);
                                if (isActive) {
                                    const defaultFilter = panel.querySelector('[data-cbt-import-batch-analysis-filter="needs-review"]');
                                    applyBatchAnalysisFilterLocal(panel, defaultFilter ? 'needs-review' : 'all');
                                }
                            });
                        };

                        navItems.forEach((item) => {
                            item.addEventListener('click', () => {
                                activateBatchAnalysisQuestionLocal(item.getAttribute('data-question-id') || '');
                            });
                        });

                        detailPanels.forEach((panel) => {
                            Array.from(panel.querySelectorAll('[data-cbt-import-batch-analysis-filter]')).forEach((button) => {
                                button.addEventListener('click', () => {
                                    applyBatchAnalysisFilterLocal(panel, button.getAttribute('data-cbt-import-batch-analysis-filter') || 'needs-review');
                                });
                            });
                        });

                        const initiallyActiveItem = navItems.find((item) => item.classList.contains('is-active')) || navItems[0];
                        if (initiallyActiveItem) {
                            activateBatchAnalysisQuestionLocal(initiallyActiveItem.getAttribute('data-question-id') || '');
                        }
                    });
                }

                function bindQuestionLocalActions() {
                    Array.from(document.querySelectorAll('form[data-cbt-questions-tab-submit]')).forEach((form) => {
                        if (form.dataset.cbtTabMemoryBound === '1') {
                            return;
                        }
                        form.dataset.cbtTabMemoryBound = '1';
                        form.addEventListener('submit', function () {
                            const tabId = String(form.getAttribute('data-cbt-questions-tab-submit') || '');
                            if (tabId !== '' && window.localStorage) {
                                window.localStorage.setItem(pageTabStorageKey, tabId);
                            }
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-questions-tab-link]')).forEach((link) => {
                        if (link.dataset.cbtTabMemoryBound === '1') {
                            return;
                        }
                        link.dataset.cbtTabMemoryBound = '1';
                        link.addEventListener('click', function () {
                            const tabId = String(link.getAttribute('data-cbt-questions-tab-link') || '');
                            if (tabId !== '' && window.localStorage) {
                                window.localStorage.setItem(pageTabStorageKey, tabId);
                            }
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-questions-async-form]')).forEach((form) => {
                        if (form.dataset.cbtLocalActionBound === '1') {
                            return;
                        }
                        form.dataset.cbtLocalActionBound = '1';
                        form.addEventListener('submit', function (event) {
                            if (event.defaultPrevented) {
                                return;
                            }

                            event.preventDefault();
                            const submitter = event.submitter || document.activeElement;
                            const actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                            const formData = new FormData(form);
                            formData.set('cbt_questions_local_refresh', '1');
                            if (submitter && submitter.name && !formData.has(submitter.name)) {
                                formData.append(submitter.name, submitter.value || '');
                            }

                            setQuestionElementLoading(submitter, true);
                            runQuestionLocalAction(form, actionUrl, {
                                method: String(form.getAttribute('method') || 'post').toUpperCase(),
                                body: formData,
                            }).catch((error) => {
                                showQuestionLocalRefreshError(error && error.message ? error.message : 'Aksi soal gagal diproses lokal.');
                            }).finally(() => {
                                setQuestionElementLoading(submitter, false);
                            });
                        });
                    });

                    Array.from(document.querySelectorAll('[data-cbt-questions-async-link]')).forEach((link) => {
                        if (link.dataset.cbtLocalActionBound === '1') {
                            return;
                        }
                        link.dataset.cbtLocalActionBound = '1';
                        link.addEventListener('click', function (event) {
                            if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                return;
                            }
                            event.preventDefault();
                            const nextUrl = new URL(link.getAttribute('href') || window.location.href, window.location.href);
                            nextUrl.searchParams.set('cbt_questions_local_refresh', '1');
                            setQuestionElementLoading(link, true);
                            runQuestionLocalAction(link, nextUrl, {
                                method: 'GET',
                            }).catch((error) => {
                                showQuestionLocalRefreshError(error && error.message ? error.message : 'Link soal gagal diproses lokal.');
                            }).finally(() => {
                                setQuestionElementLoading(link, false);
                            });
                        });
                    });
                }

                function bindQuestionContinuations() {
                    const importProgress = page ? page.querySelector('[data-cbt-questions-import-progress]') : null;
                    const deleteProgress = page ? page.querySelector('[data-cbt-questions-delete-progress]') : null;
                    const activeProgress = importProgress && importProgress.getAttribute('data-cbt-questions-import-running') === '1'
                        ? importProgress
                        : (deleteProgress && deleteProgress.getAttribute('data-cbt-questions-delete-running') === '1' ? deleteProgress : null);
                    if (!activeProgress || questionContinuationInFlight || activeProgress.dataset.cbtContinuationBound === '1') {
                        return;
                    }

                    const continueUrl = String(
                        activeProgress.getAttribute('data-cbt-questions-import-continue-url') ||
                        activeProgress.getAttribute('data-cbt-questions-delete-continue-url') ||
                        ''
                    );
                    if (continueUrl === '') {
                        return;
                    }

                    activeProgress.dataset.cbtContinuationBound = '1';
                    questionContinuationInFlight = true;
                    window.clearTimeout(questionContinuationTimer);
                    questionContinuationTimer = window.setTimeout(function () {
                        const nextUrl = new URL(continueUrl, window.location.href);
                        nextUrl.searchParams.set('cbt_questions_local_refresh', '1');
                        runQuestionLocalAction(activeProgress, nextUrl, {
                            method: 'GET',
                        }).catch((error) => {
                            showQuestionLocalRefreshError(error && error.message ? error.message : 'Batch soal berikutnya gagal dimuat lokal.');
                        }).finally(() => {
                            questionContinuationInFlight = false;
                            bindQuestionContinuations();
                        });
                    }, 420);
                }

                bindQuestionListInteractions();
                bindQuestionBatchAnalysisInteractions();
                bindQuestionContinuations();

                const clipboardImageMaxBytes = 1572864;
                const manualRichEditorIdPattern = /^(cbt_question_text_editor|cbt_essay_answer_editor|cbt_question_explanation_editor|cbt_(mc|ma)_option_\d+|cbt_ordering_item_\d+|cbt_matching_(left|right)_\d+|cbt-tfm-statement-\d+)$/;
                const manualForm = document.getElementById('cbt-question-manual-form');

                function isManualRichEditorId(editorId) {
                    return manualRichEditorIdPattern.test(String(editorId || '').trim());
                }

                function isAllowedClipboardImageType(mimeType) {
                    const normalizedType = String(mimeType || '').toLowerCase().trim();
                    if (normalizedType === '' || normalizedType === 'image/svg+xml') {
                        return false;
                    }

                    return normalizedType.startsWith('image/');
                }

                function getClipboardImageFile(event) {
                    const clipboardData = event?.clipboardData || event?.originalEvent?.clipboardData || null;
                    if (!clipboardData) {
                        return null;
                    }

                    const items = Array.from(clipboardData.items || []);
                    for (const item of items) {
                        if (!item || item.kind !== 'file') {
                            continue;
                        }

                        const mimeType = String(item.type || '').toLowerCase();
                        if (!isAllowedClipboardImageType(mimeType) || typeof item.getAsFile !== 'function') {
                            continue;
                        }

                        const file = item.getAsFile();
                        if (file) {
                            return file;
                        }
                    }

                    const files = Array.from(clipboardData.files || []);
                    for (const file of files) {
                        const mimeType = String(file?.type || '').toLowerCase();
                        if (isAllowedClipboardImageType(mimeType)) {
                            return file;
                        }
                    }

                    return null;
                }

                function escapeHtmlAttribute(value) {
                    return String(value || '')
                        .replace(/&/g, '&amp;')
                        .replace(/"/g, '&quot;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                }

                function readFileAsDataUrl(file) {
                    return new Promise((resolve, reject) => {
                        const reader = new window.FileReader();
                        reader.onload = () => resolve(String(reader.result || ''));
                        reader.onerror = () => reject(new Error('clipboard-image-read-failed'));
                        reader.readAsDataURL(file);
                    });
                }

                function buildClipboardImageHtml(dataUrl) {
                    return `<figure class="cbt-pasted-image-block"><img src="${escapeHtmlAttribute(dataUrl)}" alt="Pasted image" /></figure>`;
                }

                function insertHtmlIntoTextarea(textarea, html) {
                    if (!textarea) {
                        return;
                    }

                    const safeHtml = String(html || '');
                    const currentValue = String(textarea.value || '');
                    const start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : currentValue.length;
                    const end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : currentValue.length;

                    textarea.focus();
                    if (typeof textarea.setRangeText === 'function') {
                        textarea.setRangeText(safeHtml, start, end, 'end');
                    } else {
                        textarea.value = `${currentValue.slice(0, start)}${safeHtml}${currentValue.slice(end)}`;
                        const caret = start + safeHtml.length;
                        if (typeof textarea.setSelectionRange === 'function') {
                            textarea.setSelectionRange(caret, caret);
                        }
                    }

                    textarea.dispatchEvent(new window.Event('input', { bubbles: true }));
                    textarea.dispatchEvent(new window.Event('change', { bubbles: true }));
                }

                async function handleClipboardImagePaste(event, insertHtml) {
                    const nativeClipboardEvent = event?.clipboardData ? event : (event?.originalEvent || null);
                    if (nativeClipboardEvent && nativeClipboardEvent.__cbtClipboardImageHandled) {
                        return;
                    }

                    const clipboardImage = getClipboardImageFile(event);
                    if (!clipboardImage) {
                        return;
                    }

                    if (nativeClipboardEvent) {
                        nativeClipboardEvent.__cbtClipboardImageHandled = true;
                    }
                    if (event && typeof event === 'object') {
                        event.__cbtClipboardImageHandled = true;
                    }

                    event.preventDefault();

                    if (clipboardImage.size > clipboardImageMaxBytes) {
                        window.alert('Gambar dari clipboard terlalu besar. Gunakan Add Media untuk file di atas 1.5 MB.');
                        return;
                    }

                    try {
                        const dataUrl = await readFileAsDataUrl(clipboardImage);
                        if (dataUrl === '') {
                            throw new Error('clipboard-image-empty');
                        }

                        insertHtml(buildClipboardImageHtml(dataUrl));
                    } catch (error) {
                        window.alert('Gambar dari clipboard gagal diproses. Coba ulangi atau gunakan Add Media.');
                    }
                }

                function bindTextareaClipboard(textarea) {
                    if (!textarea || textarea.dataset.cbtClipboardPasteBound === '1' || !isManualRichEditorId(textarea.id)) {
                        return;
                    }

                    textarea.addEventListener('paste', (event) => {
                        void handleClipboardImagePaste(event, (html) => {
                            insertHtmlIntoTextarea(textarea, html);
                        });
                    });

                    textarea.dataset.cbtClipboardPasteBound = '1';
                }

                function bindTinyMceClipboard(editor) {
                    if (!editor || editor.__cbtClipboardPasteBound || !isManualRichEditorId(editor.id)) {
                        return;
                    }

                    const pasteHandler = (event) => {
                        void handleClipboardImagePaste(event, (html) => {
                            editor.focus();
                            if (typeof editor.insertContent === 'function') {
                                editor.insertContent(html);
                            } else if (typeof editor.execCommand === 'function') {
                                editor.execCommand('mceInsertContent', false, html);
                            }
                            if (typeof editor.save === 'function') {
                                editor.save();
                            }
                        });
                    };

                    const bindEditorDomPaste = () => {
                        if (editor.__cbtClipboardDomPasteBound) {
                            return;
                        }

                        const editorDoc = typeof editor.getDoc === 'function' ? editor.getDoc() : null;
                        if (!editorDoc || typeof editorDoc.addEventListener !== 'function') {
                            return;
                        }

                        editorDoc.addEventListener('paste', pasteHandler, true);
                        editor.__cbtClipboardDomPasteBound = true;
                    };

                    editor.on('paste', pasteHandler);
                    editor.on('init', bindEditorDomPaste);
                    if (editor.initialized) {
                        bindEditorDomPaste();
                    }

                    editor.__cbtClipboardPasteBound = true;
                }

                function bindManualTextareasClipboardHandlers() {
                    if (!manualForm) {
                        return;
                    }

                    manualForm.querySelectorAll('textarea.wp-editor-area').forEach((textarea) => {
                        bindTextareaClipboard(textarea);
                    });
                }

                function bindManualTinyMceClipboardHandlers(retryCount = 0) {
                    const tinyMceGlobal = window.tinymce || window.tinyMCE;
                    if (!tinyMceGlobal) {
                        if (retryCount < 20) {
                            window.setTimeout(() => {
                                bindManualTinyMceClipboardHandlers(retryCount + 1);
                            }, 250);
                        }
                        return;
                    }

                    const editors = Array.isArray(tinyMceGlobal.editors) ? tinyMceGlobal.editors : [];
                    editors.forEach((editor) => {
                        bindTinyMceClipboard(editor);
                    });

                    if (!tinyMceGlobal.__cbtClipboardAddEditorBound && typeof tinyMceGlobal.on === 'function') {
                        tinyMceGlobal.on('AddEditor', (event) => {
                            bindTinyMceClipboard(event?.editor || null);
                        });
                        tinyMceGlobal.__cbtClipboardAddEditorBound = true;
                    }
                }

                bindManualTextareasClipboardHandlers();
                bindManualTinyMceClipboardHandlers();

                if (manualForm) {
                    const insertTextIntoManualEditor = (editorId, text) => {
                        const safeText = String(text || '');
                        if (safeText === '') {
                            return;
                        }

                        const tinyMceGlobal = window.tinymce || window.tinyMCE;
                        const editor = tinyMceGlobal && typeof tinyMceGlobal.get === 'function'
                            ? tinyMceGlobal.get(editorId)
                            : null;
                        if (editor && !editor.isHidden?.()) {
                            editor.focus();
                            if (typeof editor.insertContent === 'function') {
                                editor.insertContent(safeText);
                            } else if (typeof editor.execCommand === 'function') {
                                editor.execCommand('mceInsertContent', false, safeText);
                            }
                            if (typeof editor.save === 'function') {
                                editor.save();
                            }
                            return;
                        }

                        const textarea = document.getElementById(editorId);
                        if (textarea instanceof HTMLTextAreaElement) {
                            insertHtmlIntoTextarea(textarea, safeText);
                        }
                    };

                    manualForm.addEventListener('click', (event) => {
                        const target = event.target instanceof Element
                            ? event.target.closest('[data-cbt-cloze-placeholder], [data-cbt-short-answer-placeholder]')
                            : null;
                        if (!(target instanceof HTMLElement)) {
                            return;
                        }

                        event.preventDefault();
                        insertTextIntoManualEditor(
                            'cbt_question_text_editor',
                            String(target.getAttribute('data-cbt-cloze-placeholder') || target.getAttribute('data-cbt-short-answer-placeholder') || '')
                        );
                    });

                    manualForm.addEventListener('submit', (event) => {
                        if (window.tinyMCE && typeof window.tinyMCE.triggerSave === 'function') {
                            window.tinyMCE.triggerSave();
                        }

                        const type = qTypeHidden ? qTypeHidden.value : 'multiple_choice';
                        const optionsHidden = document.getElementById('cbt-options-hidden');
                        const correctTextHidden = document.getElementById('cbt-correct-text-hidden');
                        const validationMetaHidden = document.getElementById('cbt-validation-meta-hidden');
                        if (!optionsHidden || !correctTextHidden || !validationMetaHidden) return;

                        optionsHidden.value = '';
                        correctTextHidden.value = '';
                        validationMetaHidden.value = '';

                        const editorValue = (id) => String(document.getElementById(id)?.value || '').trim();
                        const normalizeShortAnswerInputToken = (rawToken) => {
                            let token = String(rawToken || '').trim().toUpperCase();
                            if (/^[1-8]$/.test(token)) {
                                token = String.fromCharCode(64 + Number(token));
                            }
                            return /^[A-H]$/.test(token) ? token : '';
                        };
                        const extractShortAnswerInputTokens = (html) => {
                            const plain = String(html || '').replace(/<[^>]*>/g, ' ');
                            const matches = plain.match(/\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/gi) || [];
                            const tokens = [];
                            matches.forEach((match) => {
                                const tokenMatch = String(match).match(/\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/i);
                                if (!tokenMatch || !tokenMatch[1]) return;
                                const token = normalizeShortAnswerInputToken(tokenMatch[1]);
                                if (token !== '') {
                                    tokens.push(token);
                                }
                            });
                            return tokens;
                        };
                        const extractShortAnswerInputKeys = (html) => {
                            const keys = [];
                            extractShortAnswerInputTokens(html).forEach((token) => {
                                if (!keys.includes(token)) {
                                    keys.push(token);
                                }
                            });
                            return keys;
                        };
                        const findDuplicateShortAnswerInputKeys = (html) => {
                            const counts = {};
                            const duplicates = [];
                            extractShortAnswerInputTokens(html).forEach((token) => {
                                counts[token] = Number(counts[token] || 0) + 1;
                                if (counts[token] === 2) {
                                    duplicates.push(token);
                                }
                            });
                            return duplicates;
                        };
                        const normalizeClozeDropdownToken = (rawToken) => {
                            const token = String(rawToken || '').trim();
                            return /^[1-8]$/.test(token) ? token : '';
                        };
                        const extractClozeDropdownTokens = (html) => {
                            const plain = String(html || '').replace(/<[^>]*>/g, ' ');
                            const matches = plain.match(/\[\s*dropdown(?:\s*[_-]?\s*)?([1-8])\s*\]/gi) || [];
                            const tokens = [];
                            matches.forEach((match) => {
                                const tokenMatch = String(match).match(/\[\s*dropdown(?:\s*[_-]?\s*)?([1-8])\s*\]/i);
                                if (!tokenMatch || !tokenMatch[1]) return;
                                const token = normalizeClozeDropdownToken(tokenMatch[1]);
                                if (token !== '') {
                                    tokens.push(token);
                                }
                            });
                            return tokens;
                        };
                        const extractClozeDropdownKeys = (html) => {
                            const keys = [];
                            extractClozeDropdownTokens(html).forEach((token) => {
                                if (!keys.includes(token)) {
                                    keys.push(token);
                                }
                            });
                            return keys;
                        };
                        const findDuplicateClozeDropdownKeys = (html) => {
                            const counts = {};
                            const duplicates = [];
                            extractClozeDropdownTokens(html).forEach((token) => {
                                counts[token] = Number(counts[token] || 0) + 1;
                                if (counts[token] === 2) {
                                    duplicates.push(token);
                                }
                            });
                            return duplicates;
                        };
                        const normalizeCompareText = (value, { stripHtml = false } = {}) => {
                            let normalized = String(value || '');
                            if (stripHtml) {
                                const probe = document.createElement('div');
                                probe.innerHTML = normalized;
                                normalized = probe.textContent || probe.innerText || '';
                            }
                            normalized = normalized.replace(/\u00a0/g, ' ').trim().toLowerCase();
                            normalized = normalized.replace(/\s+/g, ' ').replace(/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/gu, '');
                            return normalized;
                        };
                        const normalizeOptionSignature = (html) => {
                            const raw = String(html || '');
                            const textSignature = normalizeCompareText(raw, { stripHtml: true });
                            const imageSources = [];
                            const imgPattern = /<img\b[^>]*\bsrc=(["'])(.*?)\1/gi;
                            let imageMatch = imgPattern.exec(raw);
                            while (imageMatch) {
                                const src = String(imageMatch[2] || '').trim().toLowerCase();
                                if (src !== '') {
                                    imageSources.push(src);
                                }
                                imageMatch = imgPattern.exec(raw);
                            }

                            const parts = [];
                            if (textSignature !== '') {
                                parts.push(`text:${textSignature}`);
                            }
                            if (imageSources.length > 0) {
                                parts.push(`img:${imageSources.join('|')}`);
                            }
                            return parts.join('\n');
                        };
                        const findDuplicateOptionIndexes = (optionsPayload) => {
                            const signatures = new Map();
                            const duplicates = [];
                            optionsPayload.forEach((item, index) => {
                                const signature = normalizeOptionSignature(item?.option_text || '');
                                if (signature === '') return;
                                if (signatures.has(signature)) {
                                    duplicates.push(index + 1);
                                    return;
                                }
                                signatures.set(signature, index + 1);
                            });
                            return duplicates;
                        };
                        const findDuplicateMatrixStatementIndexes = (statements) => {
                            const signatures = new Map();
                            const duplicates = [];
                            statements.forEach((item) => {
                                const signature = normalizeOptionSignature(item?.text || '');
                                if (signature === '') return;
                                const itemIndex = Number(item?.index || 0);
                                if (signatures.has(signature)) {
                                    duplicates.push(itemIndex > 0 ? itemIndex : duplicates.length + 1);
                                    return;
                                }
                                signatures.set(signature, itemIndex > 0 ? itemIndex : signatures.size + 1);
                            });
                            return duplicates;
                        };
                        const hasOptionContent = (html) => {
                            const raw = String(html || '');
                            if (/<img\b/i.test(raw)) return true;
                            const textOnly = raw
                                .replace(/<[^>]*>/g, '')
                                .replace(/&nbsp;/gi, ' ')
                                .trim();
                            return textOnly !== '';
                        };
                        const manualCounts = {
                            mcOption: getManualCountValue('cbt_mc_option_count', 5, 3, 5),
                            maOption: getManualCountValue('cbt_ma_option_count', 5, 3, 12),
                            tfmStatement: getManualCountValue('cbt_tfm_statement_count', 5, 2, 10),
                            shortAnswerInput: getManualCountValue('cbt_short_answer_input_count', 3, 1, 8),
                            orderingItem: getManualCountValue('cbt_ordering_item_count', 4, 2, 12),
                            matchingPair: getManualCountValue('cbt_matching_pair_count', 3, 2, 12),
                            clozeDropdown: getManualCountValue('cbt_cloze_dropdown_count', 2, 1, 8),
                            clozeOption: getManualCountValue('cbt_cloze_option_count', 3, 2, 6),
                            catCategory: getManualCountValue('cbt_cat_category_count', 2, 2, 8),
                            catItem: getManualCountValue('cbt_cat_item_count', 3, 2, 24),
                        };

                        if (type === 'multiple_choice') {
                            const optionsPayload = [];
                            const correctIdx = parseInt(String(document.getElementById('cbt-correct-mc-index')?.value || '1'), 10);
                            let filledCount = 0;
                            const selectedCorrectValue = editorValue(`cbt_mc_option_${correctIdx}`);

                            for (let i = 1; i <= manualCounts.mcOption; i += 1) {
                                const optVal = editorValue(`cbt_mc_option_${i}`);
                                if (!hasOptionContent(optVal)) continue;
                                filledCount += 1;
                                optionsPayload.push({
                                    option_text: optVal,
                                    is_correct: i === correctIdx ? 1 : 0,
                                });
                            }

                            if (filledCount < 3) {
                                event.preventDefault();
                                window.alert('Multiple Choice minimal harus punya 3 pilihan.');
                                return;
                            }
                            if (!hasOptionContent(selectedCorrectValue)) {
                                event.preventDefault();
                                window.alert('Jawaban benar Multiple Choice tidak boleh menunjuk pilihan kosong.');
                                return;
                            }

                            if (!optionsPayload.some((item) => Number(item.is_correct) === 1)) {
                                event.preventDefault();
                                window.alert('Pilih jawaban benar untuk Multiple Choice.');
                                return;
                            }
                            if (findDuplicateOptionIndexes(optionsPayload).length > 0) {
                                event.preventDefault();
                                window.alert('Multiple Choice tidak boleh punya pilihan duplikat.');
                                return;
                            }

                            optionsHidden.value = JSON.stringify(optionsPayload);
                            validationMetaHidden.value = JSON.stringify({
                                type,
                                selected_correct_index: correctIdx,
                                option_count: manualCounts.mcOption,
                            });
                        } else if (type === 'multiple_answer') {
                            const optionsPayload = [];
                            let filledCount = 0;
                            let correctCount = 0;
                            let hasCheckedEmptyOption = false;
                            const selectedCorrectIndexes = [];

                            for (let i = 1; i <= manualCounts.maOption; i += 1) {
                                const optVal = editorValue(`cbt_ma_option_${i}`);
                                const checked = !!document.getElementById(`cbt-ma-correct-${i}`)?.checked;
                                if (checked) {
                                    selectedCorrectIndexes.push(i);
                                }
                                if (!hasOptionContent(optVal)) {
                                    if (checked) {
                                        hasCheckedEmptyOption = true;
                                    }
                                    continue;
                                }
                                filledCount += 1;
                                if (checked) correctCount += 1;
                                optionsPayload.push({
                                    option_text: optVal,
                                    is_correct: checked ? 1 : 0,
                                });
                            }

                            if (filledCount < 3) {
                                event.preventDefault();
                                window.alert('Multiple Answer minimal harus punya 3 pilihan.');
                                return;
                            }
                            if (hasCheckedEmptyOption) {
                                event.preventDefault();
                                window.alert('Multiple Answer tidak boleh menandai jawaban benar pada pilihan yang kosong.');
                                return;
                            }

                            if (correctCount === 0) {
                                event.preventDefault();
                                window.alert('Centang minimal 1 jawaban benar untuk Multiple Answer.');
                                return;
                            }
                            if (findDuplicateOptionIndexes(optionsPayload).length > 0) {
                                event.preventDefault();
                                window.alert('Multiple Answer tidak boleh punya pilihan duplikat.');
                                return;
                            }

                            optionsHidden.value = JSON.stringify(optionsPayload);
                            validationMetaHidden.value = JSON.stringify({
                                type,
                                selected_correct_indexes: selectedCorrectIndexes,
                                option_count: manualCounts.maOption,
                            });
                        } else if (type === 'true_false') {
                            const tf = String(document.getElementById('cbt-correct-tf')?.value || 'true').toLowerCase();
                            correctTextHidden.value = tf === 'false' ? 'false' : 'true';
                            validationMetaHidden.value = JSON.stringify({ type });
                        } else if (type === 'true_false_matrix') {
                            const statements = [];
                            let encounteredFilledStatement = false;
                            let foundGapAfterFilledStatement = false;
                            for (let i = 1; i <= manualCounts.tfmStatement; i += 1) {
                                const statementText = String(document.getElementById(`cbt-tfm-statement-${i}`)?.value || '').trim();
                                if (!hasOptionContent(statementText)) {
                                    if (encounteredFilledStatement) {
                                        foundGapAfterFilledStatement = true;
                                    }
                                    continue;
                                }
                                if (foundGapAfterFilledStatement) {
                                    event.preventDefault();
                                    window.alert('Pernyataan True/False Matrix harus diisi berurutan tanpa nomor yang loncat.');
                                    return;
                                }
                                encounteredFilledStatement = true;
                                const answerValue = String(document.getElementById(`cbt-tfm-answer-${i}`)?.value || 'true').toLowerCase();
                                statements.push({
                                    index: i,
                                    text: statementText,
                                    answer: answerValue === 'false' ? 'false' : 'true',
                                });
                            }

                            if (statements.length < 2) {
                                event.preventDefault();
                                window.alert('True/False Matrix minimal harus punya 2 pernyataan.');
                                return;
                            }
                            if (findDuplicateMatrixStatementIndexes(statements).length > 0) {
                                event.preventDefault();
                                window.alert('True/False Matrix tidak boleh punya pernyataan duplikat.');
                                return;
                            }

                            correctTextHidden.value = JSON.stringify({ statements });
                            validationMetaHidden.value = JSON.stringify({
                                type,
                                provided_indexes: statements.map((item) => Number(item.index || 0)).filter((item) => item > 0),
                                statement_count: manualCounts.tfmStatement,
                            });
                        } else if (type === 'short_answer') {
                            const shortAnswerValuesByKey = {};
                            for (let i = 1; i <= manualCounts.shortAnswerInput; i += 1) {
                                const val = String(document.getElementById(`cbt-correct-sa-${i}`)?.value || '').trim();
                                if (val !== '') {
                                    const key = String.fromCharCode(64 + i);
                                    shortAnswerValuesByKey[key] = val;
                                }
                            }

                            const providedShortAnswerKeys = Object.keys(shortAnswerValuesByKey);

                            if (providedShortAnswerKeys.length === 0) {
                                event.preventDefault();
                                window.alert('Short Answer minimal harus punya 1 jawaban valid.');
                                return;
                            }

                            const questionEditorHtml = editorValue('cbt_question_text_editor');
                            const duplicateShortAnswerKeys = findDuplicateShortAnswerInputKeys(questionEditorHtml);
                            if (duplicateShortAnswerKeys.length > 0) {
                                event.preventDefault();
                                window.alert('Placeholder Short Answer tidak boleh duplikat.');
                                return;
                            }

                            const shortAnswerInputKeys = extractShortAnswerInputKeys(questionEditorHtml);
                            if (shortAnswerInputKeys.length === 0) {
                                event.preventDefault();
                                window.alert('Short Answer wajib memakai tag [INPUT_A] s.d. [INPUT_H] atau [INPUT_1] s.d. [INPUT_8] pada teks soal.');
                                return;
                            }

                            if (shortAnswerInputKeys.length !== providedShortAnswerKeys.length) {
                                event.preventDefault();
                                window.alert('Jumlah placeholder Short Answer harus sama dengan jumlah jawaban valid.');
                                return;
                            }

                            const expectedShortAnswerKeys = shortAnswerInputKeys.slice().sort();
                            const providedShortAnswerKeysSorted = providedShortAnswerKeys.slice().sort();
                            if (JSON.stringify(expectedShortAnswerKeys) !== JSON.stringify(providedShortAnswerKeysSorted)) {
                                event.preventDefault();
                                window.alert('Key placeholder Short Answer harus cocok dengan key jawaban yang diisi, misalnya INPUT_A dengan Jawaban A.');
                                return;
                            }

                            const shortAnswerValues = shortAnswerInputKeys.map((key) => String(shortAnswerValuesByKey[key] || '').trim());
                            correctTextHidden.value = JSON.stringify(shortAnswerValues.slice(0, 8));
                            validationMetaHidden.value = JSON.stringify({
                                type,
                                provided_keys: providedShortAnswerKeysSorted,
                                input_count: manualCounts.shortAnswerInput,
                            });
                        } else if (type === 'essay') {
                            validationMetaHidden.value = JSON.stringify({ type });
                        } else if (type === 'ordering') {
                            const optionsPayload = [];
                            let filledCount = 0;

                            for (let i = 1; i <= manualCounts.orderingItem; i += 1) {
                                const itemVal = editorValue(`cbt_ordering_item_${i}`);
                                if (!hasOptionContent(itemVal)) continue;
                                filledCount += 1;
                                optionsPayload.push({
                                    option_text: itemVal,
                                    is_correct: 0,
                                });
                            }

                            if (filledCount < 2) {
                                event.preventDefault();
                                window.alert('Ordering minimal harus punya 2 item.');
                                return;
                            }
                            if (findDuplicateOptionIndexes(optionsPayload).length > 0) {
                                event.preventDefault();
                                window.alert('Ordering tidak boleh punya item duplikat.');
                                return;
                            }

                            optionsHidden.value = JSON.stringify(optionsPayload);
                            correctTextHidden.value = '';
                            validationMetaHidden.value = JSON.stringify({ type, item_count: manualCounts.orderingItem });
                        } else if (type === 'matching') {
                            const leftSignatures = new Map();
                            const rightSignatures = new Map();
                            let filledCount = 0;

                            for (let i = 1; i <= manualCounts.matchingPair; i += 1) {
                                const leftVal = editorValue(`cbt_matching_left_${i}`);
                                const rightVal = editorValue(`cbt_matching_right_${i}`);
                                const hasLeft = hasOptionContent(leftVal);
                                const hasRight = hasOptionContent(rightVal);

                                if (!hasLeft && !hasRight) {
                                    continue;
                                }

                                if (!hasLeft || !hasRight) {
                                    event.preventDefault();
                                    window.alert('Matching harus mengisi prompt kiri dan pilihan kanan pada baris yang sama.');
                                    return;
                                }

                                const leftSignature = normalizeOptionSignature(leftVal);
                                const rightSignature = normalizeOptionSignature(rightVal);
                                if (leftSignature !== '' && leftSignatures.has(leftSignature)) {
                                    event.preventDefault();
                                    window.alert('Matching tidak boleh punya teks kiri duplikat.');
                                    return;
                                }
                                if (rightSignature !== '' && rightSignatures.has(rightSignature)) {
                                    event.preventDefault();
                                    window.alert('Matching tidak boleh punya pilihan kanan duplikat.');
                                    return;
                                }

                                leftSignatures.set(leftSignature, true);
                                rightSignatures.set(rightSignature, true);
                                filledCount += 1;
                            }

                            if (filledCount < 2) {
                                event.preventDefault();
                                window.alert('Matching minimal harus punya 2 pasangan.');
                                return;
                            }

                            correctTextHidden.value = '';
                            validationMetaHidden.value = JSON.stringify({ type, pair_count: manualCounts.matchingPair });
                        } else if (type === 'cloze_dropdown') {
                            const questionEditorHtml = editorValue('cbt_question_text_editor');
                            const duplicateClozeKeys = findDuplicateClozeDropdownKeys(questionEditorHtml);
                            if (duplicateClozeKeys.length > 0) {
                                event.preventDefault();
                                window.alert('Placeholder Cloze Dropdown tidak boleh duplikat.');
                                return;
                            }

                            const clozeKeys = extractClozeDropdownKeys(questionEditorHtml);
                            if (clozeKeys.length === 0) {
                                event.preventDefault();
                                window.alert('Cloze Dropdown wajib memakai placeholder [DROPDOWN_1] s.d. [DROPDOWN_8] pada teks soal.');
                                return;
                            }
                            const outOfRangeClozeKey = clozeKeys.find((key) => Number(key) > manualCounts.clozeDropdown);
                            if (outOfRangeClozeKey) {
                                event.preventDefault();
                                window.alert('Naikkan Jumlah Dropdown agar semua placeholder di teks soal ikut disimpan.');
                                return;
                            }

                            for (const key of clozeKeys) {
                                const optionValues = [];
                                const optionSignatures = new Map();
                                const correctIndex = parseInt(String(document.getElementById(`cbt_cloze_correct_${key}`)?.value || '1'), 10);
                                let correctOptionFilled = false;

                                for (let optionIndex = 1; optionIndex <= manualCounts.clozeOption; optionIndex += 1) {
                                    const optionValue = String(document.getElementById(`cbt_cloze_${key}_option_${optionIndex}`)?.value || '').trim();
                                    if (optionValue === '') {
                                        continue;
                                    }

                                    const signature = normalizeCompareText(optionValue);
                                    if (signature !== '' && optionSignatures.has(signature)) {
                                        event.preventDefault();
                                        window.alert(`Opsi Cloze Dropdown ${key} tidak boleh duplikat.`);
                                        return;
                                    }

                                    optionSignatures.set(signature, true);
                                    optionValues.push(optionValue);
                                    if (optionIndex === correctIndex) {
                                        correctOptionFilled = true;
                                    }
                                }

                                if (optionValues.length < 2) {
                                    event.preventDefault();
                                    window.alert(`Dropdown ${key} minimal harus punya 2 opsi.`);
                                    return;
                                }

                                if (!correctOptionFilled) {
                                    event.preventDefault();
                                    window.alert(`Kunci Dropdown ${key} tidak boleh menunjuk opsi kosong.`);
                                    return;
                                }
                            }

                            correctTextHidden.value = '';
                            validationMetaHidden.value = JSON.stringify({
                                type,
                                provided_keys: clozeKeys.slice().sort(),
                                dropdown_count: manualCounts.clozeDropdown,
                                dropdown_option_count: manualCounts.clozeOption,
                            });
                        } else if (type === 'categorization') {
                            const categoryValues = [];
                            const categorySignatures = new Map();
                            for (let i = 1; i <= manualCounts.catCategory; i += 1) {
                                const categoryValue = String(document.getElementById(`cbt_cat_category_${i}`)?.value || '').trim();
                                if (categoryValue === '') continue;
                                const signature = normalizeCompareText(categoryValue);
                                if (signature !== '' && categorySignatures.has(signature)) {
                                    event.preventDefault();
                                    window.alert('Kategori Categorization tidak boleh duplikat.');
                                    return;
                                }
                                categorySignatures.set(signature, true);
                                categoryValues.push(categoryValue);
                            }
                            if (categoryValues.length < 2) {
                                event.preventDefault();
                                window.alert('Categorization minimal harus punya 2 kategori.');
                                return;
                            }

                            const itemSignatures = new Map();
                            let filledItems = 0;
                            for (let i = 1; i <= manualCounts.catItem; i += 1) {
                                const itemValue = String(document.getElementById(`cbt_cat_item_${i}`)?.value || '').trim();
                                if (itemValue === '') continue;
                                filledItems += 1;
                                const signature = normalizeCompareText(itemValue, { stripHtml: true });
                                if (signature !== '' && itemSignatures.has(signature)) {
                                    event.preventDefault();
                                    window.alert('Item Categorization tidak boleh duplikat.');
                                    return;
                                }
                                itemSignatures.set(signature, true);
                                const selectedCategoryIndex = parseInt(String(document.getElementById(`cbt_cat_correct_${i}`)?.value || '0'), 10);
                                if (!selectedCategoryIndex || selectedCategoryIndex < 1 || selectedCategoryIndex > categoryValues.length) {
                                    event.preventDefault();
                                    window.alert('Setiap item Categorization wajib menunjuk kategori yang sudah diisi.');
                                    return;
                                }
                            }
                            if (filledItems < 2) {
                                event.preventDefault();
                                window.alert('Categorization minimal harus punya 2 item.');
                                return;
                            }
                            correctTextHidden.value = '';
                            validationMetaHidden.value = JSON.stringify({
                                type,
                                category_count: manualCounts.catCategory,
                                item_count: manualCounts.catItem,
                            });
                        } else if (type === 'table_completion') {
                            const rowCount = parseInt(String(document.getElementById('cbt_table_rows')?.value || '0'), 10);
                            const colCount = parseInt(String(document.getElementById('cbt_table_cols')?.value || '0'), 10);
                            if (rowCount < 2 || rowCount > 8 || colCount < 2 || colCount > 6) {
                                event.preventDefault();
                                window.alert('Table Completion harus berukuran minimal 2x2 dan maksimal 8x6.');
                                return;
                            }

                            let answerCellCount = 0;
                            for (let row = 1; row <= rowCount; row += 1) {
                                for (let col = 1; col <= colCount; col += 1) {
                                    const cellKey = `${String.fromCharCode(64 + col)}${row}`;
                                    const cellType = String(document.getElementById(`cbt_table_${cellKey}_type`)?.value || 'static');
                                    if (cellType === 'static') {
                                        continue;
                                    }
                                    answerCellCount += 1;
                                    if (cellType === 'text') {
                                        const answerValue = String(document.getElementById(`cbt_table_${cellKey}_answer`)?.value || '').trim();
                                        if (answerValue === '') {
                                            event.preventDefault();
                                            window.alert(`Sel ${cellKey} wajib punya jawaban text.`);
                                            return;
                                        }
                                        continue;
                                    }
                                    if (cellType !== 'dropdown') {
                                        event.preventDefault();
                                        window.alert(`Tipe sel ${cellKey} tidak valid.`);
                                        return;
                                    }

                                    const optionSignatures = new Map();
                                    let optionCount = 0;
                                    const correctIndex = parseInt(String(document.getElementById(`cbt_table_${cellKey}_correct`)?.value || '1'), 10);
                                    let correctFilled = false;
                                    for (let optionIndex = 1; optionIndex <= 6; optionIndex += 1) {
                                        const optionValue = String(document.getElementById(`cbt_table_${cellKey}_option_${optionIndex}`)?.value || '').trim();
                                        if (optionValue === '') continue;
                                        const signature = normalizeCompareText(optionValue);
                                        if (signature !== '' && optionSignatures.has(signature)) {
                                            event.preventDefault();
                                            window.alert(`Opsi dropdown sel ${cellKey} tidak boleh duplikat.`);
                                            return;
                                        }
                                        optionSignatures.set(signature, true);
                                        optionCount += 1;
                                        if (optionIndex === correctIndex) {
                                            correctFilled = true;
                                        }
                                    }
                                    if (optionCount < 2) {
                                        event.preventDefault();
                                        window.alert(`Dropdown sel ${cellKey} minimal punya 2 opsi.`);
                                        return;
                                    }
                                    if (!correctFilled) {
                                        event.preventDefault();
                                        window.alert(`Kunci dropdown sel ${cellKey} tidak boleh menunjuk opsi kosong.`);
                                        return;
                                    }
                                }
                            }
                            if (answerCellCount < 1) {
                                event.preventDefault();
                                window.alert('Table Completion minimal harus punya 1 sel jawaban.');
                                return;
                            }
                            if (answerCellCount > 24) {
                                event.preventDefault();
                                window.alert('Table Completion maksimal punya 24 sel jawaban.');
                                return;
                            }
                            correctTextHidden.value = '';
                            validationMetaHidden.value = JSON.stringify({ type });
                        }
                    });
                }
                bindQuestionLocalActions();
                bindQuestionContinuations();
            })();
        </script>
        <?php
