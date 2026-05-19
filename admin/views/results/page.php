<?php

if (!defined('ABSPATH')) {
    exit;
}

$attempt_security_timeline_map = isset($attempt_security_timeline_map) && is_array($attempt_security_timeline_map)
    ? $attempt_security_timeline_map
    : [];
$active_results_tab = isset($active_results_tab) && in_array((string) $active_results_tab, ['monitoring', 'essay'], true)
    ? (string) $active_results_tab
    : 'monitoring';
$selected_essay_exam_id = isset($selected_essay_exam_id) ? (int) $selected_essay_exam_id : 0;
$selected_essay_question_id = isset($selected_essay_question_id) ? (int) $selected_essay_question_id : 0;
$selected_essay_kelas = isset($selected_essay_kelas) ? (string) $selected_essay_kelas : '';
$selected_essay_keyword = isset($selected_essay_keyword) ? (string) $selected_essay_keyword : '';
$essay_question_rows = isset($essay_question_rows) && is_array($essay_question_rows) ? $essay_question_rows : [];
$selected_essay_question = isset($selected_essay_question) && is_array($selected_essay_question) ? $selected_essay_question : [];
$essay_rows = isset($essay_rows) && is_array($essay_rows) ? $essay_rows : [];
$essay_bulk_summary = isset($essay_bulk_summary) && is_array($essay_bulk_summary)
    ? $essay_bulk_summary
    : ['total_rows' => count($essay_rows), 'graded_count' => 0, 'pending_count' => 0, 'empty_count' => 0, 'savable_count' => 0];
?>
        <div class="wrap cbt-results-page">
            <div class="cbt-results-shell">
            <div id="cbt-results-notices">
                <?php if ($notice): ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                <?php endif; ?>
                <?php if (!empty($expired_attempt_auto_finalize['has_pending'])): ?>
                    <div class="notice notice-info is-dismissible"><p>Attempt expired sedang dirapikan di background agar halaman results tetap ringan. Refresh lagi beberapa detik untuk melihat status terbaru.</p></div>
                <?php endif; ?>
            </div>
            <section class="cbt-results-hero">
                <div class="cbt-results-hero-copy">
                    <span class="cbt-results-kicker">RESULTS</span>
                    <h1>CBT Results</h1>
                    <p>Pantau attempt siswa, tindakan reset atau tambah waktu, dan proses penilaian essay dari satu halaman yang lebih rapi dan mudah discan.</p>
                </div>
                <div class="cbt-results-hero-stats">
                    <?php foreach ($results_hero_stats as $results_hero_stat): ?>
                        <article class="cbt-results-hero-stat">
                            <span><?php echo esc_html((string) $results_hero_stat['label']); ?></span>
                            <strong><?php echo esc_html(number_format((int) $results_hero_stat['value'])); ?></strong>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
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

                .cbt-results-page {
            max-width: 1280px;
            margin: 20px auto;
            padding: 24px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--cbt-text-main);
            background: radial-gradient(circle at top left, #e0e7ff 0%, #f8fafc 40%, #f0fdf4 100%);
            border-radius: var(--cbt-radius-lg);
            box-sizing: border-box;
        }
        .cbt-results-page * {
            box-sizing: border-box;
        }
            @keyframes cbtSlideUp {
                0% { opacity: 0; transform: translateY(15px); }
                100% { opacity: 1; transform: translateY(0); }
            }
                
        .cbt-results-shell::before {
            content: ''; position: absolute; top: -150px; left: -100px; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-results-shell::after {
            content: ''; position: absolute; bottom: -100px; right: -50px; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-results-shell {
                    max-width: 1320px;
                    display: grid;
                    gap: 20px;
                
            position: relative;
            z-index: 1;
            isolation: isolate;
        }
                .cbt-results-page .notice {
                    margin: 0;
                }
                .cbt-results-hero {
                    position: relative;
                    overflow: hidden;
                    display: grid;
                    grid-template-columns: minmax(0, 1.75fr) minmax(320px, 1fr);
                    gap: 18px;
                    
                    
                    
                    

                    
                    
                
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
                .cbt-results-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
        }
                .cbt-results-hero-copy {
                    display: grid;
                    gap: 10px;
                    align-content: start;
                    max-width: 720px;
                }
                .cbt-results-kicker {
                    display: inline-flex;
                    align-items: center;
                    width: fit-content;
                    min-height: 30px;
                    padding: 0 14px;
                    border-radius: 999px;
                    background: rgba(34, 113, 177, 0.12);
                    color: #ffffff;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                }
                .cbt-results-hero h1 {
                    margin: 0;
                    font-size: 40px;
                    line-height: 1.05;
                    color: #0f172a;
                }
                .cbt-results-hero p {
                    margin: 0;
                    max-width: 660px;
                    color: #425466;
                    font-size: 15px;
                    line-height: 1.75;
                }
                .cbt-results-hero-stats {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 12px;
                }
                .cbt-results-hero-stat {
                    display: grid;
                    gap: 6px;
                    align-content: start;
                    padding: 16px 18px;
                    border: 1px solid rgba(34, 113, 177, 0.14);
                    border-radius: 18px;
                    background: rgba(255, 255, 255, 0.78);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
                }
                .cbt-results-hero-stat span {
                    font-size: 12px;
                    line-height: 1.3;
                    letter-spacing: 0.06em;
                    text-transform: uppercase;
                    color: #607287;
                    font-weight: 700;
                }
                .cbt-results-hero-stat strong {
                    font-size: 30px;
                    line-height: 1;
                    color: #0f172a;
                    font-weight: 800;
                }
                .cbt-results-card {
                    display: grid;
                    gap: 16px;
                    padding: 22px 24px;
                    
                    
                    
                    
                
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
                .cbt-results-card-header {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 14px;
                    flex-wrap: wrap;
                }
                .cbt-results-card-header h2 {
                    margin: 0;
                    font-size: 30px;
                    line-height: 1.15;
                    color: #0f172a;
                }
                .cbt-results-card-header p {
                    margin: 8px 0 0;
                    color: #526174;
                    font-size: 14px;
                    line-height: 1.7;
                    max-width: 760px;
                }
                .cbt-results-tab-nav {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    width: fit-content;
                    max-width: 100%;
                    padding: 8px;
                    border: 1px solid #d9e3ef;
                    border-radius: 20px;
                    background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%);
                    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
                }
                .cbt-results-tab-button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 44px;
                    padding: 0 18px;
                    border: 0;
                    border-radius: 14px;
                    background: transparent;
                    color: #526174;
                    font-size: 13px;
                    font-weight: 700;
                    line-height: 1;
                    cursor: pointer;
                    transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
                }
                .cbt-results-tab-button:hover,
                .cbt-results-tab-button:focus {
                    background: rgba(34, 113, 177, 0.08);
                    color: #135e96;
                    outline: none;
                }
                .cbt-results-tab-button.is-active {
                    background: linear-gradient(135deg, #2271b1 0%, #1f82cf 100%);
                    color: #fff;
                    box-shadow: 0 10px 22px rgba(34, 113, 177, 0.24);
                }
                .cbt-results-tab-panel {
                    display: none;
                    gap: 20px;
                }
                .cbt-results-tab-panel.is-active {
                    display: grid;
                }
                .cbt-results-filter-form {
                    display: grid;
                    grid-template-columns: minmax(160px, 1fr) minmax(160px, 1fr) minmax(280px, 2fr) minmax(160px, 1fr) auto;
                    gap: 14px;
                    align-items: end;
                }
                .cbt-results-field-grid {
                    display: contents;
                }
                .cbt-results-field {
                    display: grid;
                    gap: 8px;
                }
                .cbt-results-field label {
                    margin: 0;
                    font-size: 13px;
                    line-height: 1.3;
                    color: #223246;
                    font-weight: 700;
                }
                .cbt-results-field input[type="search"],
                .cbt-results-field input[type="number"],
                .cbt-results-field select {
                    width: 100%;
                    min-height: 50px;
                    padding: 0 16px;
                    border: 1px solid #c9d5e3;
                    border-radius: 16px;
                    background: #f8fbff;
                    color: #122033;
                    box-shadow: none;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
                }
                .cbt-results-field select {
                    padding-right: 44px;
                    appearance: none;
                    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='none' stroke='%235f6b7a' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.6' d='M1 1.25 6 6.25l5-5'/%3E%3C/svg%3E");
                    background-repeat: no-repeat;
                    background-position: right 16px center;
                    background-size: 12px 8px;
                }
                .cbt-results-field input[type="search"]:focus,
                .cbt-results-field input[type="number"]:focus,
                .cbt-results-field select:focus {
                    border-color: #2271b1;
                    background-color: #fff;
                    box-shadow: 0 0 0 4px rgba(34, 113, 177, 0.12);
                    outline: none;
                }
                .cbt-results-filter-actions {
                    display: flex;
                    gap: 10px;
                    flex-wrap: nowrap;
                    align-items: center;
                    align-self: end;
                }
                .cbt-results-filter-actions .button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    min-height: 50px;
                    padding: 0 18px;
                    border-radius: 16px;
                    line-height: 1;
                    white-space: nowrap;
                }
                .cbt-results-filter-actions .button.cbt-results-filter-reset {
                    border-color: #c8d9ea;
                    background: linear-gradient(180deg, #ffffff 0%, #f3f8ff 100%);
                    color: #123f67;
                    box-shadow: 0 12px 28px rgba(34, 59, 89, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.94);
                    font-weight: 700;
                    text-decoration: none;
                    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
                }
                .cbt-results-filter-actions .button.cbt-results-filter-reset:hover,
                .cbt-results-filter-actions .button.cbt-results-filter-reset:focus {
                    border-color: #2271b1;
                    background: linear-gradient(180deg, #ffffff 0%, #ebf5ff 100%);
                    color: #0f4c81;
                    box-shadow: 0 16px 32px rgba(34, 113, 177, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.96);
                    transform: translateY(-1px);
                    outline: none;
                }
                .cbt-results-reset-icon {
                    position: relative;
                    display: inline-flex;
                    width: 18px;
                    height: 18px;
                    flex: 0 0 18px;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    background: rgba(34, 113, 177, 0.12);
                    color: currentColor;
                }
                .cbt-results-reset-icon::before {
                    content: '';
                    position: absolute;
                    inset: 3px;
                    border: 2px solid currentColor;
                    border-right-color: transparent;
                    border-radius: 999px;
                    box-sizing: border-box;
                    transform: rotate(-22deg);
                }
                .cbt-results-reset-icon::after {
                    content: '';
                    position: absolute;
                    top: 3px;
                    right: 2px;
                    width: 5px;
                    height: 5px;
                    border-top: 2px solid currentColor;
                    border-right: 2px solid currentColor;
                    box-sizing: border-box;
                    transform: rotate(26deg);
                }
                .cbt-results-active-filters {
                    display: grid;
                    gap: 12px;
                    margin: 14px 0 16px;
                    padding: 14px 16px;
                    border: 1px solid #d9e3ef;
                    border-radius: 18px;
                    background: linear-gradient(135deg, #f8fbff 0%, #eef4fb 100%);
                }
                .cbt-results-active-filters-title {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    color: #31506b;
                    font-size: 12px;
                    font-weight: 800;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-results-active-filters-title::before {
                    content: '';
                    width: 10px;
                    height: 10px;
                    border-radius: 999px;
                    background: linear-gradient(135deg, #2271b1 0%, #6cb6ff 100%);
                    box-shadow: 0 0 0 4px rgba(34, 113, 177, 0.12);
                }
                .cbt-results-active-filters-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                }
                .cbt-results-filter-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    min-height: 40px;
                    padding: 8px 14px;
                    border: 1px solid #d6e3f0;
                    border-radius: 16px;
                    background: #fff;
                    box-shadow: 0 10px 22px rgba(33, 67, 95, 0.08);
                    line-height: 1.2;
                }
                .cbt-results-filter-chip.is-default {
                    background: #21435f;
                    border-color: #21435f;
                    box-shadow: 0 12px 26px rgba(33, 67, 95, 0.18);
                }
                .cbt-results-filter-chip-label {
                    color: #6b7f92;
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-results-filter-chip.is-default .cbt-results-filter-chip-label {
                    color: rgba(255, 255, 255, 0.72);
                }
                .cbt-results-filter-chip-value {
                    color: #17324a;
                    font-size: 13px;
                    font-weight: 800;
                }
                .cbt-results-filter-chip.is-default .cbt-results-filter-chip-value {
                    color: #fff;
                }
                .cbt-results-note-grid {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 12px;
                }
                .cbt-results-note-card {
                    display: grid;
                    gap: 6px;
                    padding: 15px 16px;
                    border: 1px solid #d9e3ef;
                    border-radius: 18px;
                    background: #f8fbff;
                }
                .cbt-results-note-card strong {
                    color: #0f172a;
                    font-size: 13px;
                }
                .cbt-results-note-card span {
                    color: #526174;
                    font-size: 13px;
                    line-height: 1.6;
                }
                .cbt-results-ops-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 10px;
                }
                .cbt-results-progress-card {
                    grid-column: 1 / -1;
                    display: grid;
                    gap: 14px;
                    padding: 18px 20px;
                    border: 1px solid #cfe1f2;
                    border-radius: 18px;
                    background: linear-gradient(135deg, #f7fbff 0%, #edf5ff 56%, #e6f0fb 100%);
                    box-shadow: 0 16px 30px rgba(34, 113, 177, 0.08);
                }
                .cbt-results-progress-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 14px;
                    flex-wrap: wrap;
                }
                .cbt-results-progress-head-actions {
                    display: inline-flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-results-progress-copy {
                    display: grid;
                    gap: 6px;
                }
                .cbt-results-progress-copy h3 {
                    margin: 0;
                    color: #0f172a;
                    font-size: 18px;
                    line-height: 1.2;
                }
                .cbt-results-progress-copy p {
                    margin: 0;
                    color: #526174;
                    font-size: 13px;
                    line-height: 1.6;
                }
                .cbt-results-progress-status {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 36px;
                    padding: 0 14px;
                    border-radius: 999px;
                    background: rgba(34, 113, 177, 0.12);
                    color: #135e96;
                    font-size: 12px;
                    font-weight: 800;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-results-progress-stop {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 36px;
                    padding: 0 14px;
                    border-radius: 999px;
                    border: 1px solid rgba(185, 28, 28, 0.18);
                    background: linear-gradient(180deg, #fff7f7 0%, #fff1f1 100%);
                    color: #b91c1c;
                    font-size: 12px;
                    font-weight: 800;
                    line-height: 1;
                    box-shadow: 0 10px 20px rgba(185, 28, 28, 0.08);
                    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
                }
                .cbt-results-progress-stop:not(:disabled):hover,
                .cbt-results-progress-stop:not(:disabled):focus {
                    transform: translateY(-1px);
                    box-shadow: 0 14px 24px rgba(185, 28, 28, 0.12);
                    outline: none;
                }
                .cbt-results-progress-stop:disabled {
                    cursor: default;
                    opacity: 0.72;
                }
                .cbt-results-progress-body {
                    display: grid;
                    gap: 12px;
                }
                .cbt-results-progress-metrics {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-results-progress-counts {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-results-progress-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    min-height: 34px;
                    padding: 0 12px;
                    border: 1px solid #d4e1ee;
                    border-radius: 999px;
                    background: rgba(255, 255, 255, 0.88);
                    color: #31506b;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-results-progress-chip strong {
                    font-weight: 800;
                }
                .cbt-results-progress-chip.is-success {
                    border-color: rgba(22, 163, 74, 0.22);
                    color: #166534;
                }
                .cbt-results-progress-chip.is-danger {
                    border-color: rgba(220, 38, 38, 0.22);
                    color: #b91c1c;
                }
                .cbt-results-progress-chip.is-muted {
                    color: #526174;
                }
                .cbt-results-progress-bar {
                    display: grid;
                    gap: 8px;
                }
                .cbt-results-progress-bar-top {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    color: #31506b;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-results-progress-track {
                    position: relative;
                    display: block;
                    width: 100%;
                    height: 12px;
                    overflow: hidden;
                    border-radius: 999px;
                    background: rgba(34, 113, 177, 0.12);
                }
                .cbt-results-progress-fill {
                    display: block;
                    width: 0;
                    height: 100%;
                    border-radius: inherit;
                    background: linear-gradient(135deg, #2271b1 0%, #31a1ff 100%);
                    transition: width 0.25s ease;
                }
                .cbt-results-progress-resume {
                    display: none;
                    margin: 0;
                    color: #9a3412;
                    font-size: 12px;
                    line-height: 1.6;
                    font-weight: 700;
                }
                .cbt-results-progress-card.is-paused .cbt-results-progress-resume {
                    display: block;
                }
                .cbt-results-progress-card.is-stopping {
                    border-color: rgba(180, 83, 9, 0.24);
                    background: linear-gradient(135deg, #fffdf6 0%, #fff7e6 100%);
                }
                .cbt-results-progress-card.is-stopping .cbt-results-progress-status {
                    background: rgba(180, 83, 9, 0.14);
                    color: #9a3412;
                }
                .cbt-results-progress-card.is-error {
                    border-color: rgba(220, 38, 38, 0.2);
                    background: linear-gradient(135deg, #fff7f7 0%, #fff2f2 100%);
                }
                .cbt-results-op-card {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    min-width: 0;
                    padding: 14px 16px;
                    border: 1px solid #d9e3ef;
                    border-radius: 16px;
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.88);
                }
                .cbt-results-op-card.is-primary {
                    border-color: rgba(34, 113, 177, 0.18);
                    background: linear-gradient(135deg, #f6fbff 0%, #edf5ff 100%);
                }
                .cbt-results-op-card.is-secondary {
                    border-color: rgba(120, 139, 159, 0.24);
                    background: linear-gradient(135deg, #ffffff 0%, #f5f9fd 56%, #eef4fb 100%);
                }
                .cbt-results-op-copy {
                    display: grid;
                    gap: 3px;
                    min-width: 0;
                    flex: 1 1 auto;
                }
                .cbt-results-op-copy h3 {
                    margin: 0;
                    font-size: 16px;
                    line-height: 1.2;
                    color: #0f172a;
                }
                .cbt-results-op-copy p {
                    margin: 0;
                    color: #526174;
                    font-size: 12px;
                    line-height: 1.45;
                }
                .cbt-results-op-form {
                    display: inline-flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 8px;
                    flex: 0 0 auto;
                    flex-wrap: nowrap;
                }
                .cbt-results-op-form .button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    min-height: 40px;
                    padding: 0 16px;
                    border-radius: 999px;
                    font-size: 12px;
                    font-weight: 800;
                    line-height: 1;
                    white-space: nowrap;
                    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease, color 0.2s ease;
                }
                .cbt-results-op-form .button:not(:disabled):hover,
                .cbt-results-op-form .button:not(:disabled):focus {
                    transform: translateY(-1px);
                    outline: none;
                }
                .cbt-results-op-form .button.cbt-results-op-button--reset {
                    border-color: #cad9e7;
                    background: linear-gradient(180deg, #ffffff 0%, #f2f7fd 100%);
                    color: #294861;
                    box-shadow: 0 12px 24px rgba(33, 67, 95, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.94);
                }
                .cbt-results-op-form .button.cbt-results-op-button--reset:not(:disabled):hover,
                .cbt-results-op-form .button.cbt-results-op-button--reset:not(:disabled):focus {
                    border-color: #9cb7d3;
                    background: linear-gradient(180deg, #ffffff 0%, #eaf4ff 100%);
                    color: #153f67;
                    box-shadow: 0 16px 28px rgba(33, 67, 95, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.96);
                }
                .cbt-results-op-form .button.cbt-results-op-button--primary {
                    border-color: #2271b1;
                    background: linear-gradient(135deg, #2271b1 0%, #1f82cf 100%);
                    color: #fff;
                    box-shadow: 0 14px 26px rgba(34, 113, 177, 0.24), inset 0 1px 0 rgba(255, 255, 255, 0.12);
                }
                .cbt-results-op-form .button.cbt-results-op-button--primary:not(:disabled):hover,
                .cbt-results-op-form .button.cbt-results-op-button--primary:not(:disabled):focus {
                    border-color: #135e96;
                    background: linear-gradient(135deg, #1d6aa8 0%, #1779c7 100%);
                    color: #fff;
                    box-shadow: 0 18px 30px rgba(34, 113, 177, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.14);
                }
                .cbt-results-op-form .button:disabled,
                .cbt-results-op-form .button[disabled] {
                    border-color: #d8e1eb;
                    background: linear-gradient(180deg, #fafbfd 0%, #f1f4f8 100%);
                    color: #9aa7b6;
                    box-shadow: none;
                    transform: none;
                    cursor: not-allowed;
                }
                .cbt-results-op-form .button:disabled .cbt-results-reset-icon,
                .cbt-results-op-form .button[disabled] .cbt-results-reset-icon {
                    background: rgba(148, 163, 184, 0.14);
                }
                .cbt-results-op-meta {
                    color: #526174;
                    font-size: 12px;
                    line-height: 1.4;
                    white-space: nowrap;
                }
                .cbt-results-filter-actions .button.cbt-results-filter-reset.is-disabled {
                    opacity: 0.7;
                    cursor: not-allowed;
                    pointer-events: none;
                    transform: none;
                    box-shadow: none;
                }
                .cbt-results-attempts-topbar {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 14px;
                    flex-wrap: wrap;
                }
                .cbt-results-submit-health-grid {
                    display: grid;
                    grid-template-columns: repeat(5, minmax(0, 1fr));
                    gap: 12px;
                }
                .cbt-results-submit-section-head {
                    display: grid;
                    gap: 6px;
                }
                .cbt-results-submit-section-head h3 {
                    margin: 0;
                    color: #0f172a;
                    font-size: 18px;
                    line-height: 1.2;
                }
                .cbt-results-submit-section-head p {
                    margin: 0;
                    color: #526174;
                    font-size: 13px;
                    line-height: 1.6;
                }
                .cbt-results-submit-health-card {
                    display: grid;
                    gap: 6px;
                    padding: 16px 18px;
                    border: 1px solid #d9e3ef;
                    border-radius: 18px;
                    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
                }
                .cbt-results-submit-health-card span {
                    font-size: 11px;
                    line-height: 1.3;
                    letter-spacing: 0.06em;
                    text-transform: uppercase;
                    color: #607287;
                    font-weight: 800;
                }
                .cbt-results-submit-health-card strong {
                    font-size: 24px;
                    line-height: 1.05;
                    color: #0f172a;
                    font-weight: 800;
                }
                .cbt-results-submit-health-card small {
                    color: #526174;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-results-submit-watchlist {
                    display: grid;
                    gap: 12px;
                    padding: 18px 20px;
                    border: 1px solid #d9e3ef;
                    border-radius: 20px;
                    background: linear-gradient(135deg, #f8fbff 0%, #eef5fd 100%);
                }
                .cbt-results-submit-watchlist-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-results-submit-watchlist-head h3 {
                    margin: 0;
                    color: #0f172a;
                    font-size: 18px;
                    line-height: 1.2;
                }
                .cbt-results-submit-watchlist-head p {
                    margin: 6px 0 0;
                    color: #526174;
                    font-size: 13px;
                    line-height: 1.6;
                    max-width: 760px;
                }
                .cbt-results-submit-watchlist-list {
                    display: grid;
                    gap: 10px;
                }
                .cbt-results-submit-watchlist-item {
                    display: grid;
                    gap: 10px;
                    padding: 14px 16px;
                    border: 1px solid #d7e4f0;
                    border-radius: 18px;
                    background: rgba(255, 255, 255, 0.92);
                    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
                }
                .cbt-results-submit-watchlist-item-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-results-submit-watchlist-student {
                    display: grid;
                    gap: 4px;
                }
                .cbt-results-submit-watchlist-student strong {
                    color: #0f172a;
                    font-size: 14px;
                    line-height: 1.3;
                }
                .cbt-results-submit-watchlist-student span {
                    color: #526174;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-results-submit-watchlist-meta {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-results-submit-watchlist-badge {
                    display: inline-flex;
                    align-items: center;
                    min-height: 30px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: #e5eef8;
                    color: #20405c;
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-results-submit-watchlist-badge.is-recovery_failed {
                    background: rgba(220, 38, 38, 0.12);
                    color: #b91c1c;
                }
                .cbt-results-submit-watchlist-badge.is-submit_error {
                    background: rgba(234, 88, 12, 0.12);
                    color: #c2410c;
                }
                .cbt-results-submit-watchlist-badge.is-recovery_retrying {
                    background: rgba(202, 138, 4, 0.14);
                    color: #a16207;
                }
                .cbt-results-submit-watchlist-badge.is-result_pending,
                .cbt-results-submit-watchlist-badge.is-submitting {
                    background: rgba(34, 113, 177, 0.12);
                    color: #135e96;
                }
                .cbt-results-submit-watchlist-meta small {
                    color: #526174;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-results-submit-watchlist-detail {
                    color: #1f3347;
                    font-size: 13px;
                    line-height: 1.7;
                }
                .cbt-results-submit-watchlist-footer {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-results-submit-watchlist-hint {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    min-height: 30px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: rgba(22, 163, 74, 0.12);
                    color: #166534;
                    font-size: 11px;
                    font-weight: 800;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-results-submit-watchlist-link {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 34px;
                    padding: 0 14px;
                    border-radius: 999px;
                    border: 1px solid #d2dfeb;
                    background: #fff;
                    color: #135e96;
                    font-size: 12px;
                    font-weight: 800;
                    text-decoration: none;
                }
                .cbt-results-submit-watchlist-link:hover,
                .cbt-results-submit-watchlist-link:focus {
                    border-color: #2271b1;
                    color: #0f4c81;
                    outline: none;
                }
                .cbt-results-submit-watchlist-empty {
                    color: #526174;
                    font-size: 13px;
                    line-height: 1.7;
                }
                .cbt-results-live-row {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-results-auto-refresh {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    min-height: 40px;
                    padding: 0 14px;
                    border: 1px solid #d9e3ef;
                    border-radius: 999px;
                    background: #f8fbff;
                    color: #1f3347;
                    font-size: 13px;
                    font-weight: 600;
                }
                .cbt-results-auto-refresh input[type="checkbox"] {
                    margin: 0;
                }
                .cbt-results-live-status {
                    display: inline-flex;
                    align-items: center;
                    min-height: 40px;
                    padding: 0 14px;
                    border-radius: 999px;
                    background: #eef4fb;
                    color: #31506b;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-results-table-shell {
                    overflow: auto;
                    border: 1px solid #d9e3ef;
                    border-radius: 20px;
                    background: #fff;
                }
                .cbt-results-table-shell .widefat {
                    margin: 0;
                    border: 0;
                    box-shadow: none;
                    width: 100%;
                    table-layout: fixed;
                }
                .cbt-results-table-shell .widefat thead th {
                    padding: 12px 10px;
                    border-bottom: 1px solid #d9e3ef;
                    background: #f8fbff;
                    color: #223246;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                    white-space: nowrap;
                }
                .cbt-results-table-shell .widefat tbody td {
                    padding: 12px 10px;
                    border-bottom: 1px solid #edf2f7;
                    vertical-align: top;
                    font-size: 13px;
                }
                .cbt-results-table-shell .widefat th:nth-child(1),
                .cbt-results-table-shell .widefat td:nth-child(1) {
                    width: 48px;
                }
                .cbt-results-table-shell .widefat th:nth-child(2),
                .cbt-results-table-shell .widefat td:nth-child(2) {
                    width: 140px;
                }
                .cbt-results-table-shell .widefat th:nth-child(3),
                .cbt-results-table-shell .widefat td:nth-child(3) {
                    width: 136px;
                }
                .cbt-results-table-shell .widefat.is-single-exam th:nth-child(3),
                .cbt-results-table-shell .widefat.is-single-exam td:nth-child(3) {
                    width: 110px;
                }
                .cbt-results-table-shell .widefat th:nth-child(4),
                .cbt-results-table-shell .widefat td:nth-child(4) {
                    width: 146px;
                }
                .cbt-results-table-shell .widefat th:nth-child(5),
                .cbt-results-table-shell .widefat td:nth-child(5) {
                    width: 210px;
                }
                .cbt-results-table-shell .widefat th:nth-child(6),
                .cbt-results-table-shell .widefat td:nth-child(6) {
                    width: 178px;
                }
                .cbt-results-table-shell .widefat th:nth-child(7),
                .cbt-results-table-shell .widefat td:nth-child(7) {
                    width: 150px;
                }
                .cbt-results-table-shell .widefat tbody tr:nth-child(odd) {
                    background: #fcfdff;
                }
                .cbt-results-table-shell .widefat tbody tr:last-child td {
                    border-bottom: 0;
                }
                .cbt-results-empty-cell {
                    padding: 28px 18px !important;
                    text-align: center;
                    color: #526174;
                    font-size: 14px;
                }
                .cbt-results-student-name {
                    display: block;
                    margin-bottom: 6px;
                    color: #0f172a;
                    font-size: 14px;
                }
                .cbt-results-student-class {
                    display: inline-flex;
                    align-items: center;
                    min-height: 26px;
                    padding: 0 12px;
                    border-radius: 12px;
                    background: #eef4fb;
                    color: #2271b1;
                    line-height: 1.4;
                    font-size: 12px;
                    font-weight: 700;
                    white-space: nowrap;
                }
                .cbt-results-status-pill {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 30px;
                    padding: 0 12px;
                    border-radius: 999px;
                    background: #eef4fb;
                    color: #31506b;
                    font-size: 12px;
                    font-weight: 700;
                    line-height: 1;
                    white-space: nowrap;
                }
                .cbt-results-status-pill.is-in-progress {
                    background: #fff4d6;
                    color: #8a5300;
                }
                .cbt-results-status-pill.is-completed {
                    background: #ecfdf3;
                    color: #0f7a56;
                }
                .cbt-results-score-cell strong {
                    display: block;
                    margin-bottom: 6px;
                    color: #0f172a;
                    font-size: 16px;
                    line-height: 1.1;
                }
                .cbt-results-score-breakdown {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 5px;
                }
                .cbt-results-score-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    min-height: 22px;
                    padding: 0 8px;
                    border: 1px solid #d9e3ef;
                    border-radius: 999px;
                    background: #f3f7fb;
                    line-height: 1;
                    white-space: nowrap;
                }
                .cbt-results-score-chip-label {
                    font-size: 10px;
                    font-weight: 700;
                    color: #64748b;
                    line-height: 1;
                }
                .cbt-results-score-chip-value {
                    font-size: 11px;
                    font-weight: 700;
                    color: #0f172a;
                    line-height: 1;
                }
                .cbt-results-score-chip.is-correct {
                    border-color: #c7e7d5;
                    background: #eef9f3;
                }
                .cbt-results-score-chip.is-correct .cbt-results-score-chip-label,
                .cbt-results-score-chip.is-correct .cbt-results-score-chip-value {
                    color: #0f7a56;
                }
                .cbt-results-score-chip.is-wrong {
                    border-color: #f3cccc;
                    background: #fff4f4;
                }
                .cbt-results-score-chip.is-wrong .cbt-results-score-chip-label,
                .cbt-results-score-chip.is-wrong .cbt-results-score-chip-value {
                    color: #b42323;
                }
                .cbt-results-score-chip.is-unanswered {
                    border-color: #dbe2eb;
                    background: #f8fafc;
                }
                .cbt-results-score-chip.is-unanswered .cbt-results-score-chip-label,
                .cbt-results-score-chip.is-unanswered .cbt-results-score-chip-value {
                    color: #475569;
                }
                .cbt-results-score-chip.is-total {
                    border-color: #c7d9f5;
                    background: #eff5ff;
                }
                .cbt-results-score-chip.is-total .cbt-results-score-chip-label,
                .cbt-results-score-chip.is-total .cbt-results-score-chip-value {
                    color: #1d4f91;
                }
                .cbt-results-id-cell {
                    color: #31506b;
                    font-weight: 700;
                }
                .cbt-results-exam-cell {
                    display: grid;
                    gap: 8px;
                }
                .cbt-results-exam-cell strong {
                    display: block;
                    color: #0f172a;
                    font-size: 13px;
                    line-height: 1.35;
                }
                .cbt-results-status-cell {
                    vertical-align: middle !important;
                }
                #cbt-results-attempts-card .cbt-results-table-shell {
                    border-radius: 16px;
                }
                #cbt-results-attempts-card .widefat thead th {
                    padding: 10px 8px;
                    font-size: 10px;
                }
                #cbt-results-attempts-card .widefat tbody td {
                    padding: 10px 8px;
                    font-size: 12px;
                }
                #cbt-results-attempts-card .widefat th:nth-child(1),
                #cbt-results-attempts-card .widefat td:nth-child(1) {
                    width: 36px;
                    padding-right: 4px;
                }
                #cbt-results-attempts-card .widefat th:nth-child(2),
                #cbt-results-attempts-card .widefat td:nth-child(2) {
                    width: 156px;
                    padding-left: 4px;
                }
                #cbt-results-attempts-card .widefat th:nth-child(3),
                #cbt-results-attempts-card .widefat td:nth-child(3) {
                    width: 122px;
                }
                #cbt-results-attempts-card .widefat.is-single-exam th:nth-child(3),
                #cbt-results-attempts-card .widefat.is-single-exam td:nth-child(3) {
                    width: 98px;
                }
                #cbt-results-attempts-card .widefat th:nth-child(4),
                #cbt-results-attempts-card .widefat td:nth-child(4) {
                    width: 132px;
                }
                #cbt-results-attempts-card .widefat th:nth-child(5),
                #cbt-results-attempts-card .widefat td:nth-child(5) {
                    width: 188px;
                }
                #cbt-results-attempts-card .widefat th:nth-child(6),
                #cbt-results-attempts-card .widefat td:nth-child(6) {
                    width: 168px;
                }
                #cbt-results-attempts-card .widefat th:nth-child(7),
                #cbt-results-attempts-card .widefat td:nth-child(7) {
                    width: 138px;
                }
                #cbt-results-attempts-card .cbt-results-student-name {
                    margin-bottom: 5px;
                    font-size: 13px;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor {
                    display: grid;
                    gap: 5px;
                    margin-top: 7px;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: fit-content;
                    min-height: 20px;
                    padding: 0 8px;
                    border: 1px solid #d8e3ef;
                    border-radius: 999px;
                    background: #f8fafc;
                    color: #425466;
                    font-size: 10px;
                    font-weight: 700;
                    line-height: 1;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor-badge.is-online {
                    border-color: #b7e3cc;
                    background: #ecfdf3;
                    color: #1d6a44;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor-badge.is-stale {
                    border-color: #f3d6a0;
                    background: #fff7e6;
                    color: #9a5b00;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor-badge.is-offline {
                    border-color: #d5dce5;
                    background: #f4f7fb;
                    color: #526174;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor-meta {
                    color: #607287;
                    font-size: 10px;
                    line-height: 1.4;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor-meta strong {
                    color: #334155;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor-chips {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 4px;
                }
                #cbt-results-attempts-card .cbt-results-student-monitor-chip {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 20px;
                    padding: 0 7px;
                    border: 1px solid #dbe5ef;
                    border-radius: 999px;
                    background: #f8fafc;
                    color: #526174;
                    font-size: 10px;
                    font-weight: 600;
                    line-height: 1;
                }
                #cbt-results-attempts-card .cbt-results-student-class {
                    min-height: 22px;
                    padding: 0 10px;
                    border-radius: 10px;
                    font-size: 11px;
                }
                #cbt-results-attempts-card .cbt-results-exam-cell {
                    gap: 6px;
                }
                #cbt-results-attempts-card .cbt-results-exam-cell strong {
                    font-size: 12px;
                }
                #cbt-results-attempts-card .cbt-results-status-pill {
                    min-height: 26px;
                    padding: 0 10px;
                    font-size: 11px;
                }
                #cbt-results-attempts-card .cbt-results-score-cell strong {
                    margin-bottom: 5px;
                    font-size: 15px;
                }
                #cbt-results-attempts-card .cbt-results-score-breakdown {
                    gap: 4px;
                }
                #cbt-results-attempts-card .cbt-results-score-chip {
                    min-height: 20px;
                    gap: 3px;
                    padding: 0 6px;
                }
                #cbt-results-attempts-card .cbt-results-score-chip-label {
                    font-size: 9px;
                }
                #cbt-results-attempts-card .cbt-results-score-chip-value {
                    font-size: 10px;
                }
                #cbt-results-attempts-card .cbt-results-timeline-meta {
                    gap: 2px;
                    margin-bottom: 6px;
                    font-size: 10px;
                }
                #cbt-results-attempts-card .cbt-attempt-progress-wrap {
                    gap: 4px;
                }
                #cbt-results-attempts-card .cbt-attempt-progress-track {
                    height: 7px;
                }
                #cbt-results-attempts-card .cbt-attempt-progress-line strong,
                #cbt-results-attempts-card .cbt-attempt-progress-wrap small {
                    font-size: 10px;
                }
                #cbt-results-attempts-card .cbt-attempt-answer-details > summary {
                    font-size: 11px;
                }
                #cbt-results-attempts-card .cbt-attempt-action-stack {
                    gap: 3px;
                }
                #cbt-results-attempts-card .cbt-attempt-time-meta {
                    min-height: 22px;
                    padding: 0 7px;
                    font-size: 10px;
                }
                #cbt-results-attempts-card .cbt-attempt-action-stack .button {
                    min-height: 26px;
                    line-height: 24px;
                    padding: 0 7px;
                    border-radius: 8px;
                    font-size: 10px;
                }
                #cbt-results-attempts-card .cbt-attempt-extend-input {
                    min-height: 28px;
                    padding: 0 7px;
                    border-radius: 9px;
                }
                #cbt-results-attempts-card .cbt-attempt-extend-prefix {
                    font-size: 11px;
                }
                #cbt-results-attempts-card .cbt-attempt-extend-input .small-text {
                    width: 28px;
                    min-height: 20px;
                    font-size: 11px;
                }
                #cbt-results-attempts-card .cbt-attempt-extend-suffix {
                    font-size: 10px;
                }
                #cbt-results-attempts-card .cbt-attempt-extend-form .button {
                    min-height: 28px;
                    line-height: 26px;
                    padding: 0 8px;
                    font-size: 10px;
                }
                #cbt-results-attempts-card .cbt-attempt-remaining-wrap {
                    gap: 5px;
                }
                #cbt-results-attempts-card .cbt-attempt-remaining-badge {
                    min-height: 24px;
                    padding: 0 8px;
                    font-size: 11px;
                }
                #cbt-results-attempts-card .cbt-attempt-remaining-track {
                    height: 5px;
                }
                #cbt-results-attempts-card .cbt-attempt-remaining-meta {
                    font-size: 10px;
                    line-height: 1.35;
                }
                .cbt-results-timeline-cell {
                    min-width: 0;
                }
                .cbt-results-timeline-meta {
                    display: grid;
                    gap: 3px;
                    margin-bottom: 8px;
                    color: #526174;
                    font-size: 11px;
                    line-height: 1.45;
                    font-variant-numeric: tabular-nums;
                }
                .cbt-results-timeline-meta strong {
                    color: #223246;
                    font-weight: 700;
                }
                .cbt-attempt-answer-cell {
                    min-width: 0;
                }
                .cbt-attempt-progress-wrap {
                    display: grid;
                    gap: 5px;
                }
                .cbt-attempt-progress-line {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }
                .cbt-attempt-progress-track {
                    position: relative;
                    flex: 1;
                    height: 8px;
                    border-radius: 999px;
                    border: 1px solid #d9e4f2;
                    background: #f3f7fb;
                    overflow: hidden;
                }
                .cbt-attempt-progress-fill {
                    display: block;
                    height: 100%;
                    background: linear-gradient(90deg, #1b7aa5, #38a8ce);
                }
                .cbt-attempt-progress-line strong,
                .cbt-attempt-progress-wrap small {
                    font-size: 11px;
                }
                .cbt-attempt-answer-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    margin-top: 4px;
                    padding: 0;
                    border: 0;
                    background: transparent;
                    color: #0f4c81;
                    font-size: 12px;
                    font-weight: 700;
                    line-height: 1.4;
                    cursor: pointer;
                }
                .cbt-attempt-answer-toggle:hover,
                .cbt-attempt-answer-toggle:focus {
                    color: #0a3f6a;
                    outline: none;
                }
                .cbt-attempt-answer-toggle-icon {
                    position: relative;
                    width: 18px;
                    height: 18px;
                    flex: 0 0 18px;
                    border-radius: 999px;
                    border: 1px solid #c8daec;
                    background: linear-gradient(180deg, #ffffff 0%, #edf5ff 100%);
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.94);
                }
                .cbt-attempt-answer-toggle-icon::before {
                    content: "";
                    position: absolute;
                    top: 5px;
                    left: 6px;
                    width: 5px;
                    height: 5px;
                    border-right: 2px solid #0f4c81;
                    border-bottom: 2px solid #0f4c81;
                    transform: rotate(45deg);
                    transition: transform 0.18s ease, top 0.18s ease;
                }
                .cbt-attempt-answer-toggle.is-open .cbt-attempt-answer-toggle-icon::before {
                    top: 7px;
                    transform: rotate(-135deg);
                }
                .cbt-attempt-answer-detail-row[hidden] {
                    display: none !important;
                }
                .cbt-results-table-shell .widefat tbody tr.cbt-attempt-answer-detail-row td {
                    padding: 12px 14px 16px;
                    background: #f8fbff;
                    border-top: 0;
                }
                .cbt-attempt-answer-detail-card {
                    display: grid;
                    gap: 10px;
                    padding: 14px;
                    border-top: 1px dashed #d7e4f2;
                    background:
                        radial-gradient(circle at top right, rgba(34, 113, 177, 0.06), transparent 30%),
                        linear-gradient(180deg, #fcfdff 0%, #f7fbff 100%);
                }
                .cbt-attempt-answer-detail-card-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 14px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-answer-detail-card-head h4 {
                    margin: 0 0 4px;
                    font-size: 14px;
                    color: #13283f;
                }
                .cbt-attempt-answer-detail-card-head p {
                    margin: 0;
                    color: #5b6876;
                    font-size: 11px;
                    line-height: 1.55;
                }
                .cbt-attempt-answer-detail-metrics {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-answer-detail-metric {
                    display: inline-flex;
                    align-items: center;
                    min-height: 28px;
                    padding: 0 10px;
                    border-radius: 999px;
                    border: 1px solid #d6e3f0;
                    background: #fff;
                    color: #334155;
                    font-size: 11px;
                    font-weight: 700;
                    line-height: 1;
                }
                .cbt-attempt-answer-detail-section {
                    display: grid;
                    gap: 8px;
                }
                .cbt-attempt-answer-detail-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-answer-detail-head-side {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-answer-detail-copy {
                    display: grid;
                    gap: 3px;
                }
                .cbt-attempt-answer-detail-title {
                    color: #10263d;
                    font-size: 13px;
                    font-weight: 800;
                }
                .cbt-attempt-answer-detail-note {
                    color: #64748b;
                    font-size: 11px;
                    line-height: 1.5;
                }
                .cbt-attempt-answer-detail-summary {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-answer-detail-summary-chip {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 0 8px;
                    border-radius: 999px;
                    font-size: 10px;
                    font-weight: 800;
                    line-height: 1;
                    white-space: nowrap;
                }
                .cbt-attempt-answer-detail-summary-chip.is-correct {
                    background: #e9fbf3;
                    color: #0f7a56;
                }
                .cbt-attempt-answer-detail-summary-chip.is-wrong {
                    background: #fff0f0;
                    color: #b42323;
                }
                .cbt-attempt-answer-detail-summary-chip.is-graded {
                    background: #edf5ff;
                    color: #145ea8;
                }
                .cbt-attempt-answer-detail-summary-chip.is-manual {
                    background: #fff6e8;
                    color: #b4690e;
                }
                .cbt-attempt-answer-detail-summary-chip.is-unanswered {
                    background: #eef2f7;
                    color: #4a5565;
                }
                .cbt-attempt-answer-detail-count {
                    display: inline-flex;
                    align-items: center;
                    min-height: 26px;
                    padding: 0 10px;
                    border-radius: 999px;
                    background: #edf4fb;
                    color: #36506a;
                    font-size: 11px;
                    font-weight: 700;
                    white-space: nowrap;
                }
                .cbt-attempt-answer-detail-section.is-archived .cbt-attempt-answer-detail-count {
                    background: #fff4e8;
                    color: #9a3412;
                }
                .cbt-attempt-security-timeline-section {
                    display: grid;
                    gap: 9px;
                    padding: 12px;
                    border: 1px solid #dbe7f3;
                    border-radius: 14px;
                    background: rgba(255, 255, 255, 0.82);
                }
                .cbt-attempt-security-timeline-head {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-security-timeline-title {
                    display: block;
                    margin-bottom: 3px;
                    color: #12263d;
                    font-size: 13px;
                    font-weight: 800;
                }
                .cbt-attempt-security-timeline-note {
                    display: block;
                    color: #64748b;
                    font-size: 11px;
                    line-height: 1.45;
                }
                .cbt-attempt-security-timeline-summary,
                .cbt-attempt-security-indicators {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 6px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-security-indicators {
                    justify-content: flex-start;
                }
                .cbt-attempt-security-chip,
                .cbt-attempt-security-indicator {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 0 8px;
                    border-radius: 999px;
                    background: #f1f5f9;
                    color: #36506a;
                    font-size: 10px;
                    font-weight: 800;
                    line-height: 1;
                    white-space: nowrap;
                }
                .cbt-attempt-security-chip.is-watch,
                .cbt-attempt-security-chip.is-warning {
                    background: #fff7ed;
                    color: #9a3412;
                }
                .cbt-attempt-security-chip.is-high-risk,
                .cbt-attempt-security-chip.is-critical {
                    background: #fff1f2;
                    color: #b42323;
                }
                .cbt-attempt-security-chip.is-normal,
                .cbt-attempt-security-chip.is-info {
                    background: #edf7ff;
                    color: #145ea8;
                }
                .cbt-attempt-security-indicator {
                    gap: 6px;
                    border: 1px solid #dbe7f3;
                    background: #fff;
                    color: #475569;
                    font-weight: 700;
                }
                .cbt-attempt-security-indicator strong {
                    color: #0f4c81;
                    font-weight: 900;
                }
                .cbt-attempt-security-empty {
                    padding: 10px 12px;
                    border: 1px dashed #d7e2ee;
                    border-radius: 12px;
                    background: #f8fbff;
                    color: #64748b;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .cbt-attempt-security-timeline-list {
                    display: grid;
                    gap: 7px;
                }
                .cbt-attempt-security-timeline-item {
                    display: grid;
                    grid-template-columns: 12px minmax(0, 1fr);
                    gap: 9px;
                    padding: 10px;
                    border: 1px solid #e1e8f0;
                    border-radius: 12px;
                    background: #fff;
                }
                .cbt-attempt-security-timeline-marker {
                    width: 9px;
                    height: 9px;
                    margin-top: 6px;
                    border-radius: 999px;
                    background: #2d7dd2;
                    box-shadow: 0 0 0 4px rgba(45, 125, 210, 0.1);
                }
                .cbt-attempt-security-timeline-item.is-warning .cbt-attempt-security-timeline-marker {
                    background: #f59e0b;
                    box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.14);
                }
                .cbt-attempt-security-timeline-item.is-critical .cbt-attempt-security-timeline-marker {
                    background: #dc2626;
                    box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.12);
                }
                .cbt-attempt-security-timeline-copy {
                    display: grid;
                    gap: 4px;
                    min-width: 0;
                }
                .cbt-attempt-security-timeline-row {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-security-timeline-row strong {
                    color: #17283a;
                    font-size: 12px;
                    font-weight: 800;
                }
                .cbt-attempt-security-time {
                    color: #526174;
                    font-size: 10px;
                    font-variant-numeric: tabular-nums;
                }
                .cbt-attempt-security-timeline-copy p {
                    margin: 0;
                    color: #405064;
                    font-size: 11px;
                    line-height: 1.45;
                }
                .cbt-attempt-security-timeline-copy small {
                    color: #64748b;
                    font-size: 10px;
                }
                .cbt-attempt-answer-detail-table-wrap {
                    overflow: auto;
                    max-height: min(46vh, 360px);
                    border: 1px solid #d9e5f0;
                    border-radius: 16px;
                    background: #fff;
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
                }
                .cbt-attempt-answer-detail-table {
                    width: 100%;
                    min-width: 680px;
                    border-collapse: separate;
                    border-spacing: 0;
                }
                .cbt-attempt-answer-detail-table thead th {
                    position: sticky;
                    top: 0;
                    z-index: 1;
                    padding: 10px 12px;
                    border-bottom: 1px solid #dbe7f3;
                    background: #f5f9fd;
                    color: #486077;
                    font-size: 10px;
                    font-weight: 800;
                    letter-spacing: 0.03em;
                    text-transform: uppercase;
                    white-space: nowrap;
                }
                .cbt-attempt-answer-detail-table tbody td {
                    padding: 9px 12px;
                    border-bottom: 1px solid #edf2f7;
                    color: #243648;
                    font-size: 11px;
                    line-height: 1.45;
                    vertical-align: top;
                    background: #fff;
                }
                .cbt-attempt-answer-detail-table tbody tr:last-child td {
                    border-bottom: 0;
                }
                .cbt-attempt-answer-detail-item.is-correct td {
                    background: #f5fdf8;
                }
                .cbt-attempt-answer-detail-item.is-wrong td {
                    background: #fff8f8;
                }
                .cbt-attempt-answer-detail-item.is-graded td {
                    background: #f5faff;
                }
                .cbt-attempt-answer-detail-item.is-manual td {
                    background: #fffaf2;
                }
                .cbt-attempt-answer-detail-item.is-archived td {
                    background: #fffaf2;
                }
                .cbt-attempt-answer-number {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 30px;
                    min-height: 24px;
                    padding: 0 7px;
                    border-radius: 999px;
                    border: 1px solid #d5e1ed;
                    background: #fff;
                    color: #1f3d5a;
                    font-size: 11px;
                    font-weight: 800;
                    line-height: 1;
                }
                .cbt-attempt-answer-status-badge,
                .cbt-attempt-answer-type-badge {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 0 8px;
                    border-radius: 999px;
                    font-size: 10px;
                    font-weight: 700;
                    line-height: 1;
                    white-space: nowrap;
                }
                .cbt-attempt-answer-status-badge.is-unanswered {
                    background: #eef2f7;
                    color: #4a5565;
                }
                .cbt-attempt-answer-status-badge.is-correct {
                    background: #e9fbf3;
                    color: #0f7a56;
                }
                .cbt-attempt-answer-status-badge.is-wrong {
                    background: #fff0f0;
                    color: #b42323;
                }
                .cbt-attempt-answer-status-badge.is-graded {
                    background: #edf5ff;
                    color: #145ea8;
                }
                .cbt-attempt-answer-status-badge.is-manual {
                    background: #fff6e8;
                    color: #b4690e;
                }
                .cbt-attempt-answer-status-badge.is-archived {
                    box-shadow: inset 0 0 0 1px rgba(154, 52, 18, 0.14);
                }
                .cbt-attempt-answer-type-badge {
                    background: #edf4fb;
                    color: #31516f;
                }
                .cbt-attempt-answer-value-text {
                    color: #0f172a;
                    word-break: break-word;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                }
                .cbt-attempt-answer-value-note {
                    margin-top: 6px;
                    font-size: 10px;
                    line-height: 1.45;
                    color: #8a5a1f;
                }
                .cbt-attempt-answer-history-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    min-height: 26px;
                    padding: 0 10px;
                    border-radius: 999px;
                    background: #fff4e8;
                    color: #9a3412;
                    font-size: 10px;
                    font-weight: 800;
                    line-height: 1;
                    white-space: nowrap;
                    box-shadow: inset 0 0 0 1px rgba(154, 52, 18, 0.12);
                }
                .cbt-attempt-answer-slot-group--table {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 4px;
                }
                .cbt-attempt-answer-details {
                    margin-top: 2px;
                }
                .cbt-attempt-answer-details > summary {
                    cursor: pointer;
                    user-select: none;
                    color: #0f4c81;
                    font-weight: 600;
                    font-size: 12px;
                }
                .cbt-attempt-answer-grid {
                    margin-top: 8px;
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(28px, 1fr));
                    gap: 5px;
                    max-height: 190px;
                    overflow: auto;
                    padding-right: 4px;
                }
                .cbt-attempt-answer-chip {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 24px;
                    border-radius: 6px;
                    border: 1px solid #d8dee8;
                    background: #eef2f7;
                    font-size: 11px;
                    font-weight: 700;
                    color: #334155;
                    line-height: 1;
                }
                .cbt-attempt-answer-chip.is-correct {
                    border-color: #8dd5bc;
                    background: #eafbf4;
                    color: #0f7a56;
                }
                .cbt-attempt-answer-chip.is-wrong {
                    border-color: #f5c2c2;
                    background: #fff2f2;
                    color: #b42323;
                }
                .cbt-attempt-answer-chip.is-manual {
                    border-color: #f0bb63;
                    background: #fff8ea;
                    color: #b4690e;
                }
                .cbt-attempt-answer-list {
                    margin: 8px 0 0;
                    padding: 0;
                    list-style: none;
                    display: grid;
                    gap: 4px;
                    max-height: 210px;
                    overflow: auto;
                }
                .cbt-attempt-answer-list-item {
                    border: 1px solid #d8dee8;
                    border-left: 4px solid #cbd5e1;
                    border-radius: 6px;
                    background: #fff;
                    padding: 6px 8px;
                    font-size: 11px;
                    line-height: 1.45;
                    color: #334155;
                }
                .cbt-attempt-answer-list-item.is-correct {
                    border-left-color: #0f7a56;
                    background: #f4fdf8;
                }
                .cbt-attempt-answer-list-item.is-wrong {
                    border-left-color: #b42323;
                    background: #fff6f6;
                }
                .cbt-attempt-answer-list-item.is-manual {
                    border-left-color: #b4690e;
                    background: #fffaf2;
                }
                .cbt-attempt-answer-archive {
                    margin-top: 10px;
                    padding-top: 10px;
                    border-top: 1px dashed #d8dee8;
                    display: grid;
                    gap: 8px;
                }
                .cbt-attempt-answer-archive-head {
                    display: grid;
                    gap: 2px;
                }
                .cbt-attempt-answer-archive-title {
                    font-size: 11px;
                    font-weight: 700;
                    color: #9a3412;
                }
                .cbt-attempt-answer-archive-note {
                    font-size: 11px;
                    color: #646970;
                }
                .cbt-attempt-answer-chip.is-archived {
                    border-style: dashed;
                    background: #fff7ed;
                    color: #9a3412;
                }
                .cbt-attempt-answer-list-item.is-archived {
                    border-left-color: #f59e0b;
                    background: #fffaf2;
                }
                .cbt-attempt-answer-list-meta {
                    font-weight: 700;
                    margin-right: 5px;
                }
                .cbt-attempt-answer-slot-group {
                    display: inline-flex;
                    flex-wrap: wrap;
                    gap: 4px;
                    vertical-align: middle;
                }
                .cbt-attempt-answer-slot {
                    display: inline-flex;
                    align-items: center;
                    gap: 3px;
                    border: 1px solid #d8dee8;
                    border-radius: 999px;
                    background: #f8fafc;
                    padding: 1px 6px;
                    line-height: 1.5;
                }
                .cbt-attempt-answer-slot-key {
                    font-size: 9px;
                    font-weight: 700;
                    color: #334155;
                }
                .cbt-attempt-answer-slot-val {
                    font-size: 10px;
                    font-weight: 600;
                    color: #0f172a;
                }
                .cbt-attempt-answer-slot.is-correct {
                    border-color: #8dd5bc;
                    background: #eafbf4;
                }
                .cbt-attempt-answer-slot.is-correct .cbt-attempt-answer-slot-key,
                .cbt-attempt-answer-slot.is-correct .cbt-attempt-answer-slot-val {
                    color: #0f7a56;
                }
                .cbt-attempt-answer-slot.is-wrong {
                    border-color: #f5c2c2;
                    background: #fff2f2;
                }
                .cbt-attempt-answer-slot.is-wrong .cbt-attempt-answer-slot-key,
                .cbt-attempt-answer-slot.is-wrong .cbt-attempt-answer-slot-val {
                    color: #b42323;
                }
                .cbt-attempt-answer-slot.is-empty {
                    border-color: #d8dee8;
                    background: #f4f6fa;
                }
                .cbt-attempt-answer-slot.is-empty .cbt-attempt-answer-slot-key,
                .cbt-attempt-answer-slot.is-empty .cbt-attempt-answer-slot-val {
                    color: #64748b;
                }
                .cbt-attempt-action-stack {
                    display: grid;
                    gap: 4px;
                    min-width: 0;
                }
                .cbt-attempt-time-meta {
                    display: inline-flex;
                    align-items: center;
                    min-height: 24px;
                    padding: 0 8px;
                    border-radius: 999px;
                    background: #f3f7fb;
                    color: #31506b;
                    font-size: 10px;
                    font-weight: 600;
                    letter-spacing: 0.02em;
                    line-height: 1;
                }
                .cbt-attempt-action-stack form {
                    margin: 0;
                }
                .cbt-attempt-action-stack .button {
                    min-height: 28px;
                    line-height: 26px;
                    padding: 0 8px;
                    border-radius: 9px;
                    font-size: 11px;
                }
                .cbt-attempt-action-row {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    flex-wrap: wrap;
                }
                .cbt-attempt-action-inline {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    width: auto;
                    flex: 0 0 auto;
                }
                .cbt-attempt-extend-form {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) auto;
                    align-items: center;
                    gap: 4px;
                    width: 100%;
                    max-width: 100%;
                    padding: 3px;
                    border: 1px solid #d7e1ed;
                    border-radius: 12px;
                    background: linear-gradient(180deg, #fbfdff 0%, #f2f7fc 100%);
                }
                .cbt-attempt-extend-input {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 4px;
                    min-height: 30px;
                    padding: 0 8px;
                    border: 1px solid #d2dde9;
                    border-radius: 9px;
                    background: #fff;
                }
                .cbt-attempt-extend-prefix {
                    color: #2271b1;
                    font-size: 12px;
                    font-weight: 800;
                    line-height: 1;
                }
                .cbt-attempt-extend-input .small-text {
                    width: 30px;
                    min-height: 22px;
                    border: 0;
                    box-shadow: none;
                    padding: 0;
                    background: transparent;
                    color: #0f172a;
                    font-size: 12px;
                    font-weight: 700;
                    text-align: center;
                }
                .cbt-attempt-extend-input .small-text:focus {
                    box-shadow: none;
                    outline: none;
                }
                .cbt-attempt-extend-suffix {
                    color: #64748b;
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                }
                .cbt-attempt-extend-form .button {
                    min-height: 30px;
                    line-height: 28px;
                    padding: 0 10px;
                    border-radius: 9px;
                }
                .cbt-attempt-remaining-cell {
                    min-width: 0;
                }
                .cbt-attempt-remaining-wrap {
                    display: grid;
                    gap: 6px;
                    min-width: 0;
                }
                .cbt-attempt-remaining-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 28px;
                    padding: 0 10px;
                    border-radius: 999px;
                    background: #eef6ff;
                    color: #0a4b78;
                    font-size: 12px;
                    font-weight: 700;
                    line-height: 1;
                }
                .cbt-attempt-remaining-badge.is-warning {
                    background: #fff4d6;
                    color: #8a5300;
                }
                .cbt-attempt-remaining-badge.is-expired {
                    background: #ffe7e7;
                    color: #b42323;
                }
                .cbt-attempt-remaining-badge.is-completed {
                    background: #ecfdf3;
                    color: #0f7a56;
                }
                .cbt-attempt-remaining-track {
                    position: relative;
                    height: 6px;
                    border-radius: 999px;
                    background: #e6edf5;
                    overflow: hidden;
                }
                .cbt-attempt-remaining-fill {
                    display: block;
                    height: 100%;
                    background: linear-gradient(90deg, #2271b1, #38a8ce);
                    transition: width 0.25s ease;
                }
                .cbt-attempt-remaining-fill.is-warning {
                    background: linear-gradient(90deg, #e3a008, #f59e0b);
                }
                .cbt-attempt-remaining-fill.is-expired {
                    background: linear-gradient(90deg, #dc2626, #f87171);
                }
                .cbt-attempt-remaining-fill.is-completed {
                    background: linear-gradient(90deg, #059669, #34d399);
                }
                .cbt-attempt-remaining-meta {
                    color: #50575e;
                    line-height: 1.4;
                    font-size: 11px;
                }
                .cbt-results-pagination-wrap {
                    margin-top: 12px;
                }
                .cbt-results-pagination {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 10px;
                }
                .cbt-results-pagination .cbt-results-total {
                    float: none;
                    margin: 0;
                    font-size: 13px;
                    font-weight: 600;
                    color: #1d2327;
                }
                .cbt-results-pagination-links {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 6px;
                    margin: 0;
                }
                .cbt-results-pagination-links .page-numbers {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 36px;
                    height: 36px;
                    padding: 0 10px;
                    box-sizing: border-box;
                    border: 1px solid #c3c4c7;
                    border-radius: 8px;
                    background: #fff;
                    color: #2271b1;
                    font-size: 14px;
                    font-weight: 600;
                    line-height: 1;
                    text-decoration: none;
                }
                .cbt-results-pagination-links .page-numbers:hover,
                .cbt-results-pagination-links .page-numbers:focus {
                    border-color: #2271b1;
                    color: #135e96;
                    box-shadow: 0 0 0 1px #2271b1;
                    outline: none;
                }
                .cbt-results-pagination-links .page-numbers.current {
                    border-color: #2271b1;
                    background: #2271b1;
                    color: #fff;
                    box-shadow: none;
                }
                .cbt-results-pagination-links .page-numbers.prev,
                .cbt-results-pagination-links .page-numbers.next {
                    min-width: 42px;
                    font-size: 16px;
                    font-weight: 700;
                }
                .cbt-results-pagination-links .page-numbers.dots {
                    min-width: auto;
                    border: none;
                    background: transparent;
                    color: #646970;
                    box-shadow: none;
                    padding: 0 2px;
                }
                .cbt-results-essay-filter-form {
                    display: grid;
                    gap: 14px;
                }
                .cbt-results-essay-content {
                    display: grid;
                    gap: 16px;
                    margin-top: 16px;
                    position: relative;
                }
                .cbt-results-essay-content > form[data-cbt-bulk-essay-form] {
                    display: grid;
                    gap: 16px;
                }
                .cbt-results-essay-toolbar {
                    display: grid;
                    grid-template-columns: minmax(220px, 1.1fr) minmax(260px, 1.3fr) minmax(160px, 0.7fr) minmax(180px, 0.8fr) auto;
                    gap: 12px;
                    align-items: end;
                }
                .cbt-results-essay-question-card {
                    display: grid;
                    gap: 10px;
                    padding: 14px;
                    border: 1px solid #d7e6f7;
                    border-radius: 8px;
                    background: #f8fbff;
                }
                .cbt-results-essay-question-card h3,
                .cbt-results-essay-answer-card h3 {
                    margin: 0;
                    font-size: 15px;
                    line-height: 1.35;
                    color: #0f172a;
                }
                .cbt-results-essay-summary {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }
                .cbt-results-essay-chip {
                    display: inline-flex;
                    align-items: center;
                    min-height: 28px;
                    padding: 0 10px;
                    border: 1px solid #cfe0f2;
                    border-radius: 6px;
                    background: #eef6ff;
                    color: #164b7d;
                    font-size: 12px;
                    font-weight: 800;
                }
                .cbt-results-essay-chip.is-success {
                    border-color: #bbf7d0;
                    background: #dcfce7;
                    color: #166534;
                }
                .cbt-results-essay-chip.is-warning {
                    border-color: #fde68a;
                    background: #fef3c7;
                    color: #92400e;
                }
                .cbt-results-essay-chip.is-muted {
                    border-color: #d8e2ef;
                    background: #f3f7fb;
                    color: #64748b;
                }
                .cbt-results-essay-answer-list {
                    display: grid;
                    gap: 16px;
                }
                .cbt-results-essay-answer-card {
                    display: grid;
                    grid-template-columns: minmax(220px, 0.7fr) minmax(0, 1.5fr) minmax(150px, 0.45fr);
                    gap: 16px;
                    align-items: start;
                    padding: 16px;
                    border: 1px solid #d9e7f6;
                    border-radius: 8px;
                    background: #ffffff;
                    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
                }
                .cbt-results-essay-answer-card.is-changed {
                    border-color: #7db8ee;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
                }
                .cbt-results-essay-answer-card.is-invalid {
                    border-color: #fca5a5;
                    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
                }
                .cbt-results-essay-student-meta,
                .cbt-results-essay-score-box {
                    display: grid;
                    gap: 8px;
                }
                .cbt-results-essay-answer-text {
                    min-height: 96px;
                    max-height: 260px;
                    overflow: auto;
                    padding: 12px;
                    border: 1px solid #e1ebf6;
                    border-radius: 8px;
                    background: #f8fbff;
                    color: #162033;
                    line-height: 1.65;
                    white-space: pre-wrap;
                }
                .cbt-results-essay-score-input-row {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .cbt-results-essay-score-input-row input[type="number"] {
                    width: 110px;
                    min-height: 42px;
                    padding: 0 12px;
                    border: 1px solid #c9d5e3;
                    border-radius: 8px;
                    background: #f8fbff;
                    box-shadow: none;
                    font-size: 15px;
                    font-weight: 800;
                }
                .cbt-results-essay-score-input-row input[type="number"]:focus {
                    border-color: #2271b1;
                    background: #fff;
                    box-shadow: 0 0 0 4px rgba(34, 113, 177, 0.12);
                    outline: none;
                }
                .cbt-results-essay-input-error {
                    display: none;
                    color: #b91c1c;
                    font-size: 12px;
                    font-weight: 700;
                }
                .cbt-results-essay-answer-card.is-invalid .cbt-results-essay-input-error {
                    display: block;
                }
                .cbt-results-essay-ai-settings,
                .cbt-results-essay-ai-panel,
                .cbt-results-essay-ai-box {
                    display: grid;
                    gap: 10px;
                    padding: 12px;
                    border: 1px solid #cfe0f2;
                    border-radius: 10px;
                    background: #f8fbff;
                }
                .cbt-results-essay-ai-settings {
                    margin-top: 0;
                }
                .cbt-results-essay-ai-settings [hidden] {
                    display: none !important;
                }
                .cbt-results-essay-ai-settings-head {
                    align-items: flex-start;
                }
                .cbt-results-essay-ai-settings-title {
                    display: grid;
                    gap: 4px;
                    min-width: 0;
                }
                .cbt-results-essay-ai-settings-summary {
                    margin: 0;
                    color: #475569;
                    line-height: 1.45;
                }
                .cbt-results-essay-ai-settings-meta {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 8px;
                }
                .cbt-results-essay-ai-settings-body {
                    display: grid;
                    gap: 12px;
                }
                .cbt-results-essay-ai-config {
                    display: grid;
                    grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.85fr);
                    gap: 14px;
                    align-items: stretch;
                }
                .cbt-results-essay-ai-main {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr);
                    gap: 12px;
                    align-items: stretch;
                    min-width: 0;
                }
                .cbt-results-essay-ai-main .is-wide {
                    grid-column: auto;
                }
                .cbt-results-essay-ai-provider-row {
                    display: grid;
                    grid-template-columns: minmax(0, .8fr) minmax(0, 1fr);
                    gap: 10px;
                    min-width: 0;
                }
                .cbt-results-essay-ai-endpoint-note {
                    display: grid;
                    gap: 6px;
                    padding: 10px 12px;
                    border: 1px solid #bfdbfe;
                    border-radius: 10px;
                    background: #ffffff;
                    min-width: 0;
                }
                .cbt-results-essay-ai-endpoint-note code {
                    display: block;
                    padding: 8px 10px;
                    border-radius: 8px;
                    background: #eff6ff;
                    color: #0f3b73;
                    white-space: normal;
                    word-break: break-all;
                }
                .cbt-results-essay-ai-settings .cbt-results-field {
                    min-width: 0;
                }
                .cbt-results-essay-ai-settings .cbt-results-field input,
                .cbt-results-essay-ai-settings .cbt-results-field select {
                    width: 100%;
                    max-width: 100%;
                    min-height: 40px;
                    box-sizing: border-box;
                }
                .cbt-results-essay-ai-compact-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 10px;
                    min-width: 0;
                }
                .cbt-results-essay-ai-secret-row {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 10px 16px;
                    padding: 10px 12px;
                    border: 1px dashed #bfd7f4;
                    border-radius: 10px;
                    background: #fff;
                }
                .cbt-results-essay-ai-secret-row label {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    margin: 0;
                    font-weight: 700;
                }
                .cbt-results-essay-ai-key-status {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 12px 14px;
                    border: 1px solid #fed7aa;
                    border-radius: 12px;
                    background: #fffbeb;
                    color: #92400e;
                }
                .cbt-results-essay-ai-key-status.is-saved {
                    border-color: #bbf7d0;
                    background: #f0fdf4;
                    color: #166534;
                }
                .cbt-results-essay-ai-key-status strong {
                    display: block;
                    font-size: 12px;
                    font-weight: 900;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                }
                .cbt-results-essay-ai-key-status span {
                    color: inherit;
                    opacity: .85;
                }
                .cbt-results-essay-ai-key-pill {
                    flex: 0 0 auto;
                    padding: 6px 10px;
                    border-radius: 999px;
                    background: rgba(255, 255, 255, 0.7);
                    font-size: 11px;
                    font-weight: 900;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                }
                .cbt-results-essay-ai-save-row {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: flex-start;
                    gap: 10px;
                    grid-column: 1 / -1;
                }
                .cbt-results-essay-ai-save-row .button-primary {
                    margin-left: auto;
                }
                .cbt-results-essay-content.is-loading {
                    opacity: .62;
                    pointer-events: none;
                }
                .cbt-results-essay-sticky-actions {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 8px;
                }
                .cbt-results-essay-ai-apply-all-note {
                    flex: 1 1 100%;
                    margin: 0;
                    text-align: right;
                    font-size: 12px;
                    font-weight: 700;
                    color: #0f766e;
                }
                .cbt-results-essay-ai-guide {
                    display: grid;
                    gap: 10px;
                    align-content: start;
                    padding: 14px;
                    border: 1px solid #bfdbfe;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #ffffff 0%, #eff6ff 100%);
                }
                .cbt-results-essay-ai-guide-card {
                    display: grid;
                    gap: 8px;
                    padding: 10px;
                    border: 1px solid rgba(37, 99, 235, 0.16);
                    border-radius: 10px;
                    background: rgba(255, 255, 255, 0.72);
                }
                .cbt-results-essay-ai-guide h4 {
                    margin: 0;
                    font-size: 13px;
                    font-weight: 900;
                    letter-spacing: .06em;
                    text-transform: uppercase;
                    color: #0f3b73;
                }
                .cbt-results-essay-ai-guide ol {
                    margin: 0;
                    padding-left: 18px;
                    color: #334155;
                    line-height: 1.55;
                }
                .cbt-results-essay-ai-guide li {
                    margin: 3px 0;
                }
                .cbt-results-essay-ai-guide .button {
                    justify-self: start;
                }
                .cbt-results-essay-ai-panel {
                    border-color: #bfdbfe;
                    background: linear-gradient(135deg, #eff6ff 0%, #f8fbff 100%);
                }
                .cbt-results-essay-ai-panel.is-running {
                    border-color: #60a5fa;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
                }
                .cbt-results-essay-ai-head {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                }
                .cbt-results-essay-ai-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    align-items: center;
                }
                .cbt-results-essay-ai-progress {
                    display: none;
                    gap: 8px;
                }
                .cbt-results-essay-ai-panel.is-running .cbt-results-essay-ai-progress,
                .cbt-results-essay-ai-panel.is-complete .cbt-results-essay-ai-progress {
                    display: grid;
                }
                .cbt-results-essay-ai-progress-track {
                    height: 9px;
                    overflow: hidden;
                    border-radius: 999px;
                    background: #dbeafe;
                }
                .cbt-results-essay-ai-progress-bar {
                    width: 0%;
                    height: 100%;
                    border-radius: inherit;
                    background: linear-gradient(90deg, #2563eb, #14b8a6);
                    transition: width 180ms ease;
                }
                .cbt-results-essay-ai-box {
                    margin-top: 12px;
                    border-color: #dbeafe;
                    background: #ffffff;
                }
                .cbt-results-essay-ai-box.is-success {
                    border-color: #bbf7d0;
                    background: #f0fdf4;
                }
                .cbt-results-essay-ai-box.is-failed,
                .cbt-results-essay-ai-box.is-stale {
                    border-color: #fed7aa;
                    background: #fff7ed;
                }
                .cbt-results-essay-ai-title {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 8px;
                    margin: 0;
                    font-size: 12px;
                    font-weight: 900;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                    color: #0f172a;
                }
                .cbt-results-essay-ai-feedback {
                    margin: 0;
                    color: #334155;
                    line-height: 1.55;
                }
                .cbt-results-essay-ai-list {
                    margin: 0;
                    padding-left: 18px;
                    color: #475569;
                }
                .cbt-results-essay-ai-list li {
                    margin: 3px 0;
                }
                .cbt-results-essay-sticky-bar {
                    position: sticky;
                    bottom: 12px;
                    z-index: 5;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    margin-top: 16px;
                    padding: 12px;
                    border: 1px solid #cfe0f2;
                    border-radius: 8px;
                    background: rgba(248, 251, 255, 0.96);
                    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.16);
                    backdrop-filter: blur(12px);
                }
                .cbt-results-essay-sticky-bar .button {
                    min-height: 42px;
                    border-radius: 8px;
                    padding: 0 16px;
                }
                @media (max-width: 782px) {
                    .cbt-results-page {
                        padding-right: 10px;
                    }
                    .cbt-results-hero,
                    .cbt-results-card {
                        padding: 20px 18px;
                        
                    
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
                    .cbt-results-hero,
                    .cbt-results-filter-form,
                    .cbt-results-field-grid,
                    .cbt-results-note-grid,
                    .cbt-results-ops-grid,
                    .cbt-results-hero-stats {
                        grid-template-columns: 1fr;
                    }
                    .cbt-results-filter-actions {
                        flex-wrap: wrap;
                    }
                    .cbt-results-essay-toolbar,
                    .cbt-results-essay-ai-config,
                    .cbt-results-essay-ai-main,
                    .cbt-results-essay-ai-provider-row,
                    .cbt-results-essay-answer-card {
                        grid-template-columns: 1fr;
                    }
                    .cbt-results-essay-ai-main .is-wide {
                        grid-column: auto;
                    }
                    .cbt-results-essay-sticky-bar {
                        align-items: stretch;
                        flex-direction: column;
                    }
                    .cbt-results-essay-sticky-actions {
                        justify-content: stretch;
                    }
                    .cbt-results-essay-sticky-actions .button {
                        flex: 1 1 180px;
                    }
                    .cbt-results-essay-ai-apply-all-note {
                        text-align: left;
                    }
                    .cbt-results-tab-nav {
                        width: 100%;
                        justify-content: stretch;
                        flex-wrap: wrap;
                    }
                    .cbt-results-tab-button {
                        flex: 1 1 160px;
                    }
                    .cbt-results-card-header h2 {
                        font-size: 24px;
                    }
                    .cbt-results-hero h1 {
                        font-size: 32px;
                    }
                    .cbt-results-active-filters {
                        padding: 14px;
                    }
                    .cbt-results-attempts-topbar {
                        align-items: stretch;
                    }
                    .cbt-results-op-card {
                        align-items: flex-start;
                        flex-wrap: wrap;
                    }
                    .cbt-results-progress-head,
                    .cbt-results-progress-metrics,
                    .cbt-results-progress-bar-top {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    .cbt-results-op-form {
                        width: 100%;
                        justify-content: flex-start;
                        flex-wrap: wrap;
                    }
                    .cbt-attempt-answer-detail-card-head,
                    .cbt-attempt-answer-detail-head {
                        flex-direction: column;
                        align-items: stretch;
                    }
                    .cbt-attempt-answer-detail-head-side {
                        justify-content: flex-start;
                    }
                    .cbt-attempt-answer-detail-table-wrap {
                        max-height: min(50vh, 420px);
                    }
                    .cbt-results-pagination-links .page-numbers {
                        min-width: 32px;
                        height: 32px;
                        font-size: 13px;
                    }
                }
            </style>
            <?php
            $bulk_reset_confirm_message = sprintf(
                'Reset %d attempt completed sesuai filter menjadi in_progress? Jawaban tersimpan tidak akan dihapus.',
                $resettable_attempts_count
            );
            $bulk_force_complete_confirm_message = sprintf(
                'Paksa selesai %d attempt in_progress sesuai filter menjadi completed? Attempt tidak bisa dilanjutkan lagi oleh siswa.',
                $completable_attempts_count
            );
            ?>
            <nav class="cbt-results-tab-nav" role="tablist" aria-label="CBT Results Sections">
                <button
                    type="button"
                    id="cbt-results-tab-btn-monitoring"
                    class="cbt-results-tab-button<?php echo $active_results_tab === 'monitoring' ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $active_results_tab === 'monitoring' ? 'true' : 'false'; ?>"
                    aria-controls="cbt-results-tab-panel-monitoring"
                    data-cbt-results-tab="monitoring"
                >
                    Monitoring Attempts
                </button>
                <button
                    type="button"
                    id="cbt-results-tab-btn-essay"
                    class="cbt-results-tab-button<?php echo $active_results_tab === 'essay' ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $active_results_tab === 'essay' ? 'true' : 'false'; ?>"
                    aria-controls="cbt-results-tab-panel-essay"
                    data-cbt-results-tab="essay"
                >
                    Koreksi Essay
                </button>
            </nav>

            <div
                id="cbt-results-tab-panel-monitoring"
                class="cbt-results-tab-panel<?php echo $active_results_tab === 'monitoring' ? ' is-active' : ''; ?>"
                role="tabpanel"
                aria-labelledby="cbt-results-tab-btn-monitoring"
                data-cbt-results-tab-panel="monitoring"
            >
            <section id="cbt-results-filter-card" class="cbt-results-card">
                <div class="cbt-results-card-header">
                    <div>
                        <h2>Filter Attempts</h2>
                        <p>Fokuskan monitoring berdasarkan exam, kelas, status attempt, atau siswa tertentu sebelum memantau progres live.</p>
                    </div>
                </div>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-results-filter-form">
                    <input type="hidden" name="page" value="cbt-results" />
                    <input type="hidden" name="cbt_results_paged" value="1" />
                    <?php if ($results_bulk_job_active && $results_bulk_job_token !== ''): ?>
                        <input type="hidden" name="cbt_results_bulk_token" value="<?php echo esc_attr($results_bulk_job_token); ?>" />
                    <?php endif; ?>
                    <div class="cbt-results-field-grid">
                        <div class="cbt-results-field">
                            <label for="cbt-result-filter-exam">Filter Exam</label>
                            <select id="cbt-result-filter-exam" name="cbt_exam_id">
                                <option value="0">Semua exam</option>
                                <?php foreach ($exam_filter_rows as $exam_filter_row): ?>
                                    <option value="<?php echo (int) ($exam_filter_row['id'] ?? 0); ?>" <?php selected($selected_exam_id, (int) ($exam_filter_row['id'] ?? 0)); ?>>
                                        <?php echo esc_html((string) ($exam_filter_row['title'] ?? '-')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-results-field">
                            <label for="cbt-result-filter-kelas">Kelas</label>
                            <select id="cbt-result-filter-kelas" name="cbt_result_kelas">
                                <option value="">Semua kelas</option>
                                <?php foreach ($kelas_filter_rows as $kelas_filter_row): ?>
                                    <option value="<?php echo esc_attr($kelas_filter_row); ?>" <?php selected($selected_kelas, $kelas_filter_row); ?>>
                                        <?php echo esc_html($kelas_filter_row); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-results-field cbt-results-field--search">
                            <label for="cbt-result-filter-student">NISN / Username</label>
                            <input
                                type="search"
                                id="cbt-result-filter-student"
                                name="cbt_student_q"
                                value="<?php echo esc_attr($student_keyword); ?>"
                                placeholder="Contoh: 1000000001 atau siswa0001"
                            />
                        </div>
                        <div class="cbt-results-field">
                            <label for="cbt-result-filter-status">Status</label>
                            <select id="cbt-result-filter-status" name="cbt_attempt_status">
                                <option value="" <?php selected($selected_status, ''); ?>>Semua status</option>
                                <option value="in_progress" <?php selected($selected_status, 'in_progress'); ?>>In Progress</option>
                                <option value="completed" <?php selected($selected_status, 'completed'); ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="cbt-results-filter-actions">
                        <button class="button button-secondary screen-reader-text" type="submit">Terapkan Filter</button>
                        <a class="button cbt-results-filter-reset<?php echo $results_bulk_job_active ? ' is-disabled' : ''; ?>" data-cbt-filter-reset="1" href="<?php echo esc_url(admin_url('admin.php?page=cbt-results')); ?>"<?php echo $results_bulk_job_active ? ' aria-disabled="true"' : ''; ?>>
                            <span class="cbt-results-reset-icon" aria-hidden="true"></span>
                            <span>Reset Filter</span>
                        </a>
                    </div>
                </form>
                <div id="cbt-results-batch-card" class="cbt-results-ops-grid">
                    <?php if ($results_bulk_job_active): ?>
                        <article
                            id="cbt-results-bulk-progress-card"
                            class="cbt-results-progress-card"
                            data-cbt-results-bulk-job="1"
                            data-cbt-results-bulk-token="<?php echo esc_attr((string) ($results_bulk_job['token'] ?? '')); ?>"
                            data-cbt-results-bulk-ajax-url="<?php echo esc_url((string) ($results_bulk_job['ajax_url'] ?? admin_url('admin-ajax.php'))); ?>"
                            data-cbt-results-bulk-action="<?php echo esc_attr((string) ($results_bulk_job['ajax_action'] ?? 'cbt_results_bulk_job_tick')); ?>"
                            data-cbt-results-bulk-nonce="<?php echo esc_attr((string) ($results_bulk_job['nonce'] ?? '')); ?>"
                            data-cbt-results-bulk-mode="<?php echo esc_attr((string) ($results_bulk_job['mode'] ?? '')); ?>"
                            data-cbt-results-bulk-stop-action="<?php echo esc_attr((string) ($results_bulk_job['stop_action'] ?? 'cbt_results_bulk_job_stop')); ?>"
                            data-cbt-results-bulk-stop-nonce="<?php echo esc_attr((string) ($results_bulk_job['stop_nonce'] ?? '')); ?>"
                            data-cbt-results-bulk-status="<?php echo esc_attr((string) ($results_bulk_job['status'] ?? 'pending')); ?>"
                            data-cbt-results-bulk-resume-url="<?php echo esc_url((string) ($results_bulk_job['resume_url'] ?? '')); ?>"
                        >
                            <div class="cbt-results-progress-head">
                                <div class="cbt-results-progress-copy">
                                    <h3><?php echo esc_html((string) ($results_bulk_job['mode_label'] ?? 'Batch Results')); ?></h3>
                                    <p data-cbt-results-bulk-role="message"><?php echo esc_html((string) ($results_bulk_job['status_message'] ?? 'Batch results sedang berjalan.')); ?></p>
                                </div>
                                <div class="cbt-results-progress-head-actions">
                                    <span class="cbt-results-progress-status" data-cbt-results-bulk-role="status"><?php echo esc_html((string) ($results_bulk_job['status_label'] ?? 'Berjalan')); ?></span>
                                    <button
                                        type="button"
                                        class="button cbt-results-progress-stop"
                                        data-cbt-results-bulk-stop="1"
                                        data-cbt-results-bulk-role="stop-button"
                                        <?php disabled(empty($results_bulk_job['can_stop'])); ?>
                                    >
                                        <?php echo !empty($results_bulk_job['stop_requested']) ? 'Menghentikan...' : 'Stop Batch'; ?>
                                    </button>
                                </div>
                            </div>
                            <div class="cbt-results-progress-body">
                                <div class="cbt-results-progress-metrics">
                                    <div class="cbt-results-progress-counts">
                                        <span class="cbt-results-progress-chip is-success">Sukses <strong data-cbt-results-bulk-role="success"><?php echo (int) ($results_bulk_job['success_count'] ?? 0); ?></strong></span>
                                        <span class="cbt-results-progress-chip is-danger">Gagal <strong data-cbt-results-bulk-role="failure"><?php echo (int) ($results_bulk_job['failure_count'] ?? 0); ?></strong></span>
                                        <span class="cbt-results-progress-chip is-muted">Reset <strong data-cbt-results-bulk-role="reset-count"><?php echo (int) ($results_bulk_job['reset_count'] ?? 0); ?></strong></span>
                                        <span class="cbt-results-progress-chip is-muted">Abandoned <strong data-cbt-results-bulk-role="abandoned-count"><?php echo (int) ($results_bulk_job['abandoned_count'] ?? 0); ?></strong></span>
                                        <span class="cbt-results-progress-chip is-muted">Completed <strong data-cbt-results-bulk-role="completed-count"><?php echo (int) ($results_bulk_job['completed_count'] ?? 0); ?></strong></span>
                                    </div>
                                    <div class="cbt-results-progress-counts">
                                        <span class="cbt-results-progress-chip">Diproses <strong data-cbt-results-bulk-role="processed"><?php echo (int) ($results_bulk_job['processed_count'] ?? 0); ?></strong> / <span data-cbt-results-bulk-role="total"><?php echo (int) ($results_bulk_job['total'] ?? 0); ?></span></span>
                                    </div>
                                </div>
                                <div class="cbt-results-progress-bar">
                                    <div class="cbt-results-progress-bar-top">
                                        <span data-cbt-results-bulk-role="detail"><?php echo esc_html((string) ($results_bulk_job['status_detail'] ?? '')); ?></span>
                                        <strong data-cbt-results-bulk-role="percent"><?php echo esc_html(number_format_i18n((float) ($results_bulk_job['progress_percent'] ?? 0), 2)); ?>%</strong>
                                    </div>
                                    <span class="cbt-results-progress-track" aria-hidden="true">
                                        <span class="cbt-results-progress-fill" data-cbt-results-bulk-role="progress-fill" style="width: <?php echo esc_attr(number_format((float) ($results_bulk_job['progress_percent'] ?? 0), 2, '.', '')); ?>%;"></span>
                                    </span>
                                </div>
                                <p class="cbt-results-progress-resume" data-cbt-results-bulk-role="resume">Koneksi admin terputus. Reload halaman ini untuk melanjutkan job yang masih tersimpan.</p>
                            </div>
                        </article>
                    <?php endif; ?>
                    <article class="cbt-results-op-card is-secondary">
                        <div class="cbt-results-op-copy">
                            <h3>Reset Sesuai Filter</h3>
                            <p><?php echo esc_html(sprintf('%d attempt completed siap di-reset.', $resettable_attempts_count)); ?></p>
                        </div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-results-op-form" onsubmit="return confirm('<?php echo esc_attr($bulk_reset_confirm_message); ?>');">
                            <?php wp_nonce_field('cbt_bulk_reset_attempts'); ?>
                            <input type="hidden" name="action" value="cbt_bulk_reset_attempts" />
                            <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                            <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                            <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                            <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                            <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
                            <button class="button button-secondary cbt-results-op-button--reset" type="submit" <?php disabled($resettable_attempts_count <= 0 || $results_bulk_job_active); ?>>
                                <span class="cbt-results-reset-icon" aria-hidden="true"></span>
                                <span><?php echo esc_html(sprintf('Reset (%d)', $resettable_attempts_count)); ?></span>
                            </button>
                            <span class="cbt-results-op-meta"><?php echo esc_html($results_bulk_job_active ? 'Batch aktif sedang berjalan.' : 'Jawaban tetap aman.'); ?></span>
                        </form>
                    </article>
                    <article class="cbt-results-op-card is-primary">
                        <div class="cbt-results-op-copy">
                            <h3>Paksa Complete</h3>
                            <p><?php echo esc_html(sprintf('%d attempt in_progress siap ditutup.', $completable_attempts_count)); ?></p>
                        </div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-results-op-form" onsubmit="return confirm('<?php echo esc_attr($bulk_force_complete_confirm_message); ?>');">
                            <?php wp_nonce_field('cbt_bulk_force_complete_attempts'); ?>
                            <input type="hidden" name="action" value="cbt_bulk_force_complete_attempts" />
                            <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                            <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                            <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                            <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                            <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
                            <button class="button button-primary cbt-results-op-button--primary" type="submit" <?php disabled($completable_attempts_count <= 0 || $results_bulk_job_active); ?>>
                                <span><?php echo esc_html(sprintf('Paksa Complete (%d)', $completable_attempts_count)); ?></span>
                            </button>
                            <span class="cbt-results-op-meta"><?php echo esc_html($results_bulk_job_active ? 'Menunggu batch aktif selesai.' : 'Attempt langsung ditutup.'); ?></span>
                        </form>
                    </article>
                </div>
            </section>

            <section id="cbt-results-attempts-card" class="cbt-results-card">
                <div class="cbt-results-attempts-topbar">
                    <div class="cbt-results-card-header">
                        <div>
                            <h2>Attempts</h2>
                            <p>Pantau progress live attempt, sisa waktu, distribusi jawaban, dan jalankan tindakan per siswa tanpa pindah halaman.</p>
                        </div>
                    </div>
                    <div class="cbt-results-live-row">
                        <label for="cbt-attempts-auto-refresh-toggle" class="cbt-results-auto-refresh">
                            <input type="checkbox" id="cbt-attempts-auto-refresh-toggle" checked />
                            <span>Auto refresh 10 detik</span>
                        </label>
                        <span id="cbt-attempts-live-status" class="cbt-results-live-status">Auto refresh aktif setiap 10 detik.</span>
                    </div>
                </div>
                <div class="cbt-results-submit-section-head">
                    <h3>Submit Health</h3>
                    <p>Ringkasan 15 menit terakhir untuk submit, ack server, recovery hasil, dan unresolved watchlist pada scope results saat ini.</p>
                </div>
                <div class="cbt-results-submit-health-grid">
                    <article class="cbt-results-submit-health-card">
                        <span>Finish Ack</span>
                        <strong><?php echo esc_html(!empty($submit_health['available']) ? number_format_i18n((int) ($submit_health['finish_ack_total'] ?? 0)) : 'N/A'); ?></strong>
                        <small>Attempt yang sudah diakui server dalam <?php echo esc_html(number_format_i18n((int) ($submit_health['minutes'] ?? 15))); ?> menit terakhir.</small>
                    </article>
                    <article class="cbt-results-submit-health-card">
                        <span>Result Ready</span>
                        <strong><?php echo esc_html(!empty($submit_health['available']) ? number_format_i18n((int) ($submit_health['result_ready_total'] ?? 0)) : 'N/A'); ?></strong>
                        <small>Recovery hasil yang berhasil committed ke UI.</small>
                    </article>
                    <article class="cbt-results-submit-health-card">
                        <span>Recovery Failed</span>
                        <strong><?php echo esc_html(!empty($submit_health['available']) ? number_format_i18n((int) ($submit_health['recovery_failed_total'] ?? 0)) : 'N/A'); ?></strong>
                        <small>Kasus submit yang masih stuck setelah finalisasi.</small>
                    </article>
                    <article class="cbt-results-submit-health-card">
                        <span>Ack -&gt; Result p95</span>
                        <strong><?php echo esc_html((string) ($submit_health['ack_to_result_ready_p95_label'] ?? 'N/A')); ?></strong>
                        <small>Latency p95 dari ack server sampai result siap.</small>
                    </article>
                    <article class="cbt-results-submit-health-card">
                        <span>Open Watchlist</span>
                        <strong><?php echo esc_html(!empty($submit_health['available']) ? number_format_i18n((int) ($submit_watchlist['total'] ?? ($submit_health['open_watchlist_total'] ?? 0))) : 'N/A'); ?></strong>
                        <small><?php echo esc_html((string) ($submit_health['note'] ?? '')); ?></small>
                    </article>
                </div>
                <section class="cbt-results-submit-watchlist">
                    <div class="cbt-results-submit-watchlist-head">
                        <div>
                            <h3>Submit Watchlist</h3>
                            <p><?php echo esc_html((string) ($submit_watchlist['note'] ?? 'Pantau unresolved submit yang butuh perhatian operator.')); ?></p>
                        </div>
                    </div>
                    <?php if (empty($submit_watchlist['available'])): ?>
                        <div class="cbt-results-submit-watchlist-empty">Submit telemetry belum tersedia. Halaman results tetap aman dipakai, tetapi watchlist belum bisa dihitung.</div>
                    <?php elseif (empty($submit_watchlist['items'])): ?>
                        <div class="cbt-results-submit-watchlist-empty">Belum ada unresolved submit yang melewati ambang operasional pada scope filter aktif.</div>
                    <?php else: ?>
                        <div class="cbt-results-submit-watchlist-list">
                            <?php foreach ((array) ($submit_watchlist['items'] ?? []) as $submit_watchlist_item): ?>
                                <article class="cbt-results-submit-watchlist-item">
                                    <div class="cbt-results-submit-watchlist-item-head">
                                        <div class="cbt-results-submit-watchlist-student">
                                            <strong><?php echo esc_html((string) ($submit_watchlist_item['student_name'] ?? '-')); ?></strong>
                                            <span>
                                                <?php
                                                $submit_watchlist_identity_parts = array_filter([
                                                    (string) ($submit_watchlist_item['student_username'] ?? ''),
                                                    (string) ($submit_watchlist_item['student_nisn'] ?? ''),
                                                    (string) ($submit_watchlist_item['student_kelas'] ?? ''),
                                                ]);
                                                echo esc_html(!empty($submit_watchlist_identity_parts) ? implode(' · ', $submit_watchlist_identity_parts) : 'Identitas siswa');
                                                ?>
                                            </span>
                                            <span><?php echo esc_html((string) ($submit_watchlist_item['exam_title'] ?? '-')); ?></span>
                                        </div>
                                        <div class="cbt-results-submit-watchlist-meta">
                                            <span class="<?php echo esc_attr((string) ($submit_watchlist_item['state_badge_class'] ?? 'cbt-results-submit-watchlist-badge')); ?>"><?php echo esc_html((string) ($submit_watchlist_item['state_label'] ?? 'Unknown')); ?></span>
                                            <small>Updated <?php echo esc_html((string) ($submit_watchlist_item['updated_at_label'] ?? '-')); ?></small>
                                            <small>Age <?php echo esc_html((string) ($submit_watchlist_item['age_label'] ?? '-')); ?></small>
                                            <small>Retry <?php echo esc_html(number_format_i18n((int) ($submit_watchlist_item['retry_count'] ?? 0))); ?></small>
                                        </div>
                                    </div>
                                    <div class="cbt-results-submit-watchlist-detail"><?php echo esc_html((string) ($submit_watchlist_item['detail'] ?? 'Status submit masih dipantau.')); ?></div>
                                    <div class="cbt-results-submit-watchlist-footer">
                                        <div>
                                            <?php if (!empty($submit_watchlist_item['server_completed'])): ?>
                                                <span class="cbt-results-submit-watchlist-hint">Server Completed</span>
                                            <?php endif; ?>
                                        </div>
                                        <a class="cbt-results-submit-watchlist-link" href="<?php echo esc_url((string) ($submit_watchlist_item['attempt_anchor'] ?? '#')); ?>">Lompat ke Attempt</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <div class="cbt-results-active-filters">
                    <span class="cbt-results-active-filters-title">Filter Aktif</span>
                    <div class="cbt-results-active-filters-list">
                        <?php foreach ($active_filters as $active_filter): ?>
                            <?php $filter_is_default = !empty($active_filter['is_default']); ?>
                            <span class="cbt-results-filter-chip<?php echo $filter_is_default ? ' is-default' : ''; ?>">
                                <span class="cbt-results-filter-chip-label"><?php echo esc_html((string) ($active_filter['label'] ?? 'Filter')); ?></span>
                                <span class="cbt-results-filter-chip-value"><?php echo esc_html((string) ($active_filter['value'] ?? '-')); ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="cbt-results-table-shell">
            <table id="cbt-attempts-table" class="widefat striped<?php echo $show_exam_column ? '' : ' is-single-exam'; ?>">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th><?php echo esc_html($show_exam_column ? 'Exam' : 'Status'); ?></th>
                    <th>Score</th>
                    <th>Jawaban</th>
                    <th>Timeline</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody id="cbt-attempts-tbody">
                <?php if (!$attempts): ?>
                    <?php
                    $results_has_filters = $selected_exam_id > 0 || $selected_status !== '' || $selected_kelas !== '' || $student_keyword !== '';
                    echo CBT_Admin_UI_Helper::render_table_empty_state(7, [
                        'title' => $results_has_filters ? 'Tidak ada hasil sesuai filter' : 'Belum ada hasil',
                        'message' => $results_has_filters
                            ? 'Tidak ada attempt yang cocok dengan filter saat ini. Reset filter atau pilih exam lain.'
                            : 'Attempt siswa akan muncul setelah peserta mulai atau menyelesaikan ujian.',
                        'action_label' => $results_has_filters ? 'Reset Filter' : 'Buka Exams',
                        'action_url' => $results_has_filters ? admin_url('admin.php?page=cbt-results') : admin_url('admin.php?page=cbt-exams&cbt_exam_panel=list'),
                        'action_class' => $results_has_filters ? 'button button-secondary cbt-admin-btn--secondary' : 'button button-primary',
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php else: ?>
                    <?php foreach ($attempts as $attempt): ?>
                        <?php
                        $attempt_progress = (array) ($attempt_answer_progress_map[(int) ($attempt['id'] ?? 0)] ?? []);
                        $progress_items = array_values((array) ($attempt_progress['active_items'] ?? []));
                        $archived_progress_items = array_values((array) ($attempt_progress['archived_items'] ?? []));
                        $progress_summary = CBT_Admin_Results_Helper::summarize_attempt_answer_progress_items($progress_items);
                        $total_points = array_key_exists('total_points', $progress_summary)
                            ? (float) $progress_summary['total_points']
                            : (float) ($attempt['total_points'] ?? 0);
                        $answered_points = array_key_exists('answered_points', $progress_summary)
                            ? (float) $progress_summary['answered_points']
                            : (float) ($attempt['answered_points'] ?? 0);
                        $earned_points = array_key_exists('earned_points', $progress_summary)
                            ? (float) $progress_summary['earned_points']
                            : (float) ($attempt['earned_points'] ?? 0);
                        $wrong_points = max(0, $answered_points - $earned_points);
                        $unanswered_points = max(0, $total_points - $answered_points);
                        $percentage = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0;
                        $answer_count = array_key_exists('answer_count', $progress_summary)
                            ? (int) $progress_summary['answer_count']
                            : (int) ($attempt['answer_count'] ?? 0);
                        $question_count = array_key_exists('question_count', $progress_summary)
                            ? (int) $progress_summary['question_count']
                            : (int) ($attempt['question_count'] ?? 0);
                        $answered_percentage = $question_count > 0 ? round(($answer_count / $question_count) * 100, 2) : 0;
                        $attempt_base_duration_minutes = max(1, (int) ($attempt['exam_duration_minutes'] ?? 0));
                        $attempt_extra_time_minutes = max(0, (int) ($attempt['extra_time_minutes'] ?? 0));
                        $attempt_effective_duration_minutes = $attempt_base_duration_minutes + $attempt_extra_time_minutes;
                        $attempt_status = (string) ($attempt['status'] ?? '');
                        $attempt_total_seconds = max(0, $attempt_effective_duration_minutes * MINUTE_IN_SECONDS);
                        $attempt_remaining_seconds = CBT_Admin_Results_Helper::calculate_attempt_remaining_seconds(
                            (string) ($attempt['started_at'] ?? ''),
                            $attempt_effective_duration_minutes,
                            $attempt_status
                        );
                        $attempt_remaining_percent = $attempt_total_seconds > 0
                            ? round(($attempt_remaining_seconds / $attempt_total_seconds) * 100, 2)
                            : 0;
                        $attempt_remaining_badge_class = 'cbt-attempt-remaining-badge';
                        $attempt_remaining_fill_class = 'cbt-attempt-remaining-fill';
                        $attempt_remaining_label = 'Selesai';
                        $attempt_remaining_meta = 'Attempt sudah ditutup.';
                        $attempt_finalize_pending = false;
                        if ($attempt_status === 'in_progress') {
                            $attempt_remaining_label = CBT_Admin_Results_Helper::format_attempt_remaining_label($attempt_remaining_seconds);
                            $attempt_remaining_meta = 'Sisa dari total ' . $attempt_effective_duration_minutes . ' menit.';
                            if ($attempt_remaining_seconds <= 0) {
                                $attempt_finalize_pending = true;
                                $attempt_remaining_label = 'Diproses';
                                $attempt_remaining_badge_class .= ' is-expired';
                                $attempt_remaining_fill_class .= ' is-expired';
                                $attempt_remaining_meta = 'Waktu ujian habis. Finalisasi dijalankan di background.';
                            } elseif ($attempt_remaining_seconds <= (10 * MINUTE_IN_SECONDS)) {
                                $attempt_remaining_badge_class .= ' is-warning';
                                $attempt_remaining_fill_class .= ' is-warning';
                            }
                        } else {
                            $attempt_remaining_percent = 100;
                            $attempt_remaining_badge_class .= ' is-completed';
                            $attempt_remaining_fill_class .= ' is-completed';
                        }
	                        $attempt_status_label = $attempt_status === 'in_progress' ? 'Berjalan' : 'Selesai';
	                        $attempt_status_pill_class = 'cbt-results-status-pill';
	                        if ($attempt_status === 'in_progress') {
	                            $attempt_status_pill_class .= ' is-in-progress';
	                        } else {
	                            $attempt_status_pill_class .= ' is-completed';
	                        }
	                            $attempt_id = (int) ($attempt['id'] ?? 0);
	                            $attempt_security_timeline = isset($attempt_security_timeline_map[$attempt_id]) && is_array($attempt_security_timeline_map[$attempt_id])
	                                ? $attempt_security_timeline_map[$attempt_id]
	                                : [];
	                            $attempt_security_summary = isset($attempt_security_timeline['summary']) && is_array($attempt_security_timeline['summary'])
	                                ? $attempt_security_timeline['summary']
	                                : [];
	                            $attempt_security_event_total = max(0, (int) ($attempt_security_summary['total_events'] ?? 0));
	                            $attempt_answer_detail_row_id = 'cbt-attempt-answer-row-' . $attempt_id;
                                $has_archived_history = !empty($archived_progress_items);
                                $has_security_timeline = $attempt_security_event_total > 0;
                                $has_attempt_detail = !empty($progress_items) || !empty($archived_progress_items) || $has_security_timeline;
                                if ($has_archived_history) {
                                    $attempt_toggle_open_label = 'Lihat Detail & History';
                                    $attempt_toggle_close_label = 'Tutup Detail & History';
                                } elseif ($has_security_timeline) {
                                    $attempt_toggle_open_label = 'Lihat Detail & Security';
                                    $attempt_toggle_close_label = 'Tutup Detail & Security';
                                } else {
                                    $attempt_toggle_open_label = 'Lihat Detail Jawaban';
                                    $attempt_toggle_close_label = 'Tutup Detail Jawaban';
                                }
		                        ?>
	                        <tr id="cbt-results-attempt-row-<?php echo (int) $attempt_id; ?>" class="cbt-results-attempt-row" data-cbt-attempt-row="<?php echo (int) $attempt_id; ?>">
	                            <td class="cbt-results-id-cell">#<?php echo (int) $attempt_id; ?></td>
                            <td class="cbt-results-student-cell">
                                <strong class="cbt-results-student-name"><?php echo esc_html((string) ($attempt['student_name'] ?? '-')); ?></strong>
                                <?php
                                $attempt_student_kelas = trim((string) ($attempt['student_kelas'] ?? ''));
                                if ($attempt_student_kelas !== ''):
                                ?>
                                    <span class="cbt-results-student-class"><?php echo esc_html($attempt_student_kelas); ?></span>
                                <?php endif; ?>
                                <?php echo CBT_Admin_Results_Service::render_attempt_student_presence_monitor((array) $attempt); ?>
                            </td>
                            <td class="<?php echo esc_attr($show_exam_column ? '' : 'cbt-results-status-cell'); ?>">
                                <?php if ($show_exam_column): ?>
                                    <div class="cbt-results-exam-cell">
                                        <strong><?php echo esc_html((string) ($attempt['exam_title'] ?? '-')); ?></strong>
                                        <span class="<?php echo esc_attr($attempt_status_pill_class); ?>"><?php echo esc_html($attempt_status_label); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="<?php echo esc_attr($attempt_status_pill_class); ?>"><?php echo esc_html($attempt_status_label); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="cbt-results-score-cell">
                                <strong><?php echo esc_html(number_format($percentage, 2)); ?>%</strong>
                                <div class="cbt-results-score-breakdown">
                                    <span class="cbt-results-score-chip is-correct" title="Poin benar atau poin yang didapat">
                                        <span class="cbt-results-score-chip-label">Benar</span>
                                        <span class="cbt-results-score-chip-value"><?php echo esc_html(number_format($earned_points, 2)); ?></span>
                                    </span>
                                    <span class="cbt-results-score-chip is-wrong" title="Poin dari soal yang sudah dijawab tetapi belum menghasilkan skor">
                                        <span class="cbt-results-score-chip-label">Salah</span>
                                        <span class="cbt-results-score-chip-value"><?php echo esc_html(number_format($wrong_points, 2)); ?></span>
                                    </span>
                                    <span class="cbt-results-score-chip is-unanswered" title="Poin dari soal yang belum dijawab">
                                        <span class="cbt-results-score-chip-label">Belum</span>
                                        <span class="cbt-results-score-chip-value"><?php echo esc_html(number_format($unanswered_points, 2)); ?></span>
                                    </span>
                                    <span class="cbt-results-score-chip is-total" title="Total poin ujian">
                                        <span class="cbt-results-score-chip-label">Total</span>
                                        <span class="cbt-results-score-chip-value"><?php echo esc_html(number_format($total_points, 2)); ?></span>
                                    </span>
                                </div>
                            </td>
                            <td class="cbt-attempt-answer-cell">
                                <div class="cbt-attempt-progress-wrap">
                                    <div class="cbt-attempt-progress-line">
                                        <div class="cbt-attempt-progress-track" aria-hidden="true">
                                            <span class="cbt-attempt-progress-fill" style="width: <?php echo esc_attr(number_format(max(0, min(100, $answered_percentage)), 2, '.', '')); ?>%;"></span>
                                        </div>
                                        <strong><?php echo esc_html(number_format($answered_percentage, 2)); ?>%</strong>
                                    </div>
	                                    <small>
	                                        <?php echo esc_html((string) $answer_count); ?>
		                                        <?php if ($question_count > 0): ?>
		                                            / <?php echo esc_html((string) $question_count); ?>
		                                        <?php endif; ?>
		                                        soal terjawab
		                                    </small>
                                            <?php if ($has_archived_history): ?>
                                                <span class="cbt-attempt-answer-history-chip">
                                                    <?php echo esc_html(sprintf('%d history dihapus/nonaktif', count($archived_progress_items))); ?>
                                                </span>
                                            <?php endif; ?>
		                                    <?php if ($has_attempt_detail): ?>
		                                        <button
	                                                type="button"
	                                        class="cbt-admin-action cbt-admin-action--view cbt-attempt-answer-toggle"
	                                                data-cbt-attempt-answer-toggle="<?php echo (int) $attempt_id; ?>"
	                                                data-open-label="<?php echo esc_attr($attempt_toggle_open_label); ?>"
	                                                data-close-label="<?php echo esc_attr($attempt_toggle_close_label); ?>"
	                                                aria-expanded="false"
	                                                aria-controls="<?php echo esc_attr($attempt_answer_detail_row_id); ?>"
	                                            >
	                                                <span class="cbt-attempt-answer-toggle-icon" aria-hidden="true"></span>
	                                                <span class="cbt-attempt-answer-toggle-label"><?php echo esc_html($attempt_toggle_open_label); ?></span>
	                                            </button>
		                                    <?php endif; ?>
	                                </div>
	                            </td>
                            <td class="cbt-results-timeline-cell">
                                <div class="cbt-results-timeline-meta">
                                    <span><strong>Mulai:</strong> <?php echo esc_html((string) $attempt['started_at']); ?></span>
                                    <span><strong>Selesai:</strong> <?php echo esc_html((string) $attempt['finished_at']); ?></span>
                                </div>
                                <div
                                    class="cbt-attempt-remaining-wrap"
                                    data-cbt-remaining-seconds="<?php echo esc_attr((string) $attempt_remaining_seconds); ?>"
                                    data-cbt-total-seconds="<?php echo esc_attr((string) $attempt_total_seconds); ?>"
                                    data-cbt-attempt-status="<?php echo esc_attr($attempt_status); ?>"
                                    data-cbt-finalize-pending="<?php echo $attempt_finalize_pending ? '1' : '0'; ?>"
                                    data-cbt-finalize-poll-after-ms="3000"
                                >
                                    <span class="<?php echo esc_attr($attempt_remaining_badge_class); ?>" data-cbt-remaining-label>
                                        <?php echo esc_html($attempt_remaining_label); ?>
                                    </span>
                                    <span class="cbt-attempt-remaining-track" aria-hidden="true">
                                        <span
                                            class="<?php echo esc_attr($attempt_remaining_fill_class); ?>"
                                            data-cbt-remaining-fill
                                            style="width: <?php echo esc_attr(number_format(max(0, min(100, $attempt_remaining_percent)), 2, '.', '')); ?>%;"
                                        ></span>
                                    </span>
                                    <small class="cbt-attempt-remaining-meta">
                                        <?php echo esc_html($attempt_remaining_meta); ?>
                                    </small>
                                </div>
                            </td>
                            <?php
                            $attempt_duration_title = 'Durasi aktif ' . $attempt_effective_duration_minutes . ' menit';
                            $attempt_duration_label = $attempt_effective_duration_minutes . 'm';
                            if ($attempt_extra_time_minutes > 0) {
                                $attempt_duration_title .= ' (+ ' . $attempt_extra_time_minutes . ' menit admin)';
                                $attempt_duration_label .= ' | +' . $attempt_extra_time_minutes . 'm';
                            }
                            ?>
                            <td>
                                <div class="cbt-admin-row-actions cbt-attempt-action-stack">
                                    <div
                                        class="cbt-attempt-time-meta"
                                        title="<?php echo esc_attr($attempt_duration_title); ?>"
                                    >
                                        <?php echo esc_html($attempt_duration_label); ?>
                                    </div>
                                    <div class="cbt-attempt-action-row">
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-attempt-action-inline" data-cbt-attempt-partial-submit="attempts" onsubmit="return confirm('Reset login siswa ini? Semua browser aktif user ini akan diminta login ulang.');">
	                                            <?php wp_nonce_field('cbt_reset_user_login_' . (int) $attempt_id); ?>
	                                            <input type="hidden" name="action" value="cbt_reset_user_login" />
	                                            <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt_id; ?>" />
                                            <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                                            <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                                            <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                                            <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                                            <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
                                                <button class="button button-small cbt-admin-btn--warning cbt-admin-action cbt-admin-action--warning" type="submit" title="Reset login siswa">Reset Login</button>
                                        </form>
                                        <?php if ((string) ($attempt['status'] ?? '') === 'in_progress'): ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-attempt-extend-form" data-cbt-attempt-partial-submit="attempts" onsubmit="return confirm('Tambahkan waktu untuk attempt ini? Timer siswa di frontend akan ikut diperbarui.');">
	                                                <?php wp_nonce_field('cbt_extend_attempt_time_' . (int) $attempt_id); ?>
	                                                <input type="hidden" name="action" value="cbt_extend_attempt_time" />
	                                                <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt_id; ?>" />
                                                <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                                                <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                                                <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                                                <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                                                <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
	                                                <label class="screen-reader-text" for="cbt-extend-time-<?php echo (int) $attempt_id; ?>">Tambah menit</label>
	                                                <span class="cbt-attempt-extend-input">
	                                                    <span class="cbt-attempt-extend-prefix">+</span>
	                                                    <input
	                                                        type="number"
	                                                        id="cbt-extend-time-<?php echo (int) $attempt_id; ?>"
	                                                        name="extra_minutes"
                                                        class="small-text"
                                                        min="1"
                                                        max="180"
                                                        step="1"
                                                        value="5"
                                                    />
                                                    <span class="cbt-attempt-extend-suffix">m</span>
                                                </span>
                                                <button class="button button-secondary button-small cbt-admin-btn--success cbt-admin-action cbt-admin-action--success" type="submit" title="Tambah waktu attempt">Tambah</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ((string) ($attempt['status'] ?? '') === 'completed'): ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-attempt-action-inline" data-cbt-attempt-partial-submit="attempts" onsubmit="return confirm('Reset attempt ini ke in_progress? Jawaban tersimpan tidak akan dihapus.');">
	                                                <?php wp_nonce_field('cbt_reset_attempt_' . (int) $attempt_id); ?>
	                                                <input type="hidden" name="action" value="cbt_reset_attempt" />
	                                                <input type="hidden" name="attempt_id" value="<?php echo (int) $attempt_id; ?>" />
                                                <input type="hidden" name="cbt_exam_id" value="<?php echo (int) $selected_exam_id; ?>" />
                                                <input type="hidden" name="cbt_attempt_status" value="<?php echo esc_attr($selected_status); ?>" />
                                                <input type="hidden" name="cbt_result_kelas" value="<?php echo esc_attr($selected_kelas); ?>" />
                                                <input type="hidden" name="cbt_student_q" value="<?php echo esc_attr($student_keyword); ?>" />
                                                <input type="hidden" name="cbt_results_paged" value="<?php echo (int) $current_page; ?>" />
                                                <button class="button button-secondary button-small cbt-admin-btn--danger cbt-admin-action cbt-admin-action--danger" type="submit">Reset Ujian</button>
                                            </form>
                                        <?php endif; ?>
	                                    </div>
	                                </div>
	                            </td>
	                        </tr>
                            <?php if ($has_attempt_detail): ?>
                                <tr
                                    id="<?php echo esc_attr($attempt_answer_detail_row_id); ?>"
                                    class="cbt-admin-drawer-row cbt-attempt-answer-detail-row"
                                    data-cbt-attempt-answer-row="<?php echo (int) $attempt_id; ?>"
                                    hidden
                                >
                                    <td colspan="7">
	                                        <div class="cbt-admin-drawer-panel cbt-attempt-answer-detail-card">
	                                            <div class="cbt-attempt-answer-detail-card-head">
	                                                <div>
	                                                    <h4><?php echo esc_html('Detail Attempt #' . $attempt_id); ?></h4>
	                                                    <p>
                                                            <?php if (!empty($progress_items) || !empty($archived_progress_items)): ?>
                                                                Tabel penuh untuk meninjau status jawaban, bobot poin, skor, history soal, dan timeline security attempt ini.
                                                            <?php else: ?>
                                                                Attempt ini belum punya detail jawaban yang bisa ditampilkan, tetapi memiliki event security yang tercatat.
                                                            <?php endif; ?>
                                                        </p>
	                                                </div>
	                                                <div class="cbt-attempt-answer-detail-metrics">
                                                    <span class="cbt-attempt-answer-detail-metric">
                                                        <?php echo esc_html(sprintf('%d / %d terjawab', $answer_count, $question_count)); ?>
                                                    </span>
                                                    <span class="cbt-attempt-answer-detail-metric">
                                                        <?php echo esc_html(number_format_i18n($answered_percentage, 2) . '% progress'); ?>
                                                    </span>
	                                                    <?php if (!empty($archived_progress_items)): ?>
	                                                        <span class="cbt-attempt-answer-detail-metric">
	                                                            <?php echo esc_html(sprintf('%d soal dihapus/nonaktif', count($archived_progress_items))); ?>
	                                                        </span>
	                                                    <?php endif; ?>
                                                    <?php if ($has_security_timeline): ?>
                                                        <span class="cbt-attempt-answer-detail-metric">
                                                            <?php echo esc_html(sprintf(
                                                                'Security %s · %d event',
                                                                (string) ($attempt_security_summary['risk_score_label'] ?? '0'),
                                                                $attempt_security_event_total
                                                            )); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php
                                            echo CBT_Admin_Results_Helper::render_attempt_security_timeline_html(
                                                $attempt_security_timeline
                                            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ?>
                                            <?php
                                            echo CBT_Admin_Results_Helper::render_attempt_answer_progress_table_html(
                                                $progress_items,
                                                'Soal Aktif',
                                                'Nomor yang masih aktif di exam saat ini.'
                                            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ?>
                                            <?php
	                                            if (!empty($archived_progress_items)) {
	                                                echo CBT_Admin_Results_Helper::render_attempt_answer_progress_table_html(
	                                                    $archived_progress_items,
	                                                    'History Soal Dihapus / Nonaktif',
	                                                    'Jawaban ini tetap tersimpan, termasuk untuk soal yang sudah dinonaktifkan atau tidak lagi tersedia di database exam.',
	                                                    true
	                                                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	                                            }
                                            ?>
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
            $results_pagination_args = [
                'page' => 'cbt-results',
            ];
            if ($selected_exam_id > 0) {
                $results_pagination_args['cbt_exam_id'] = $selected_exam_id;
            }
            if ($selected_status !== '') {
                $results_pagination_args['cbt_attempt_status'] = $selected_status;
            }
            if ($selected_kelas !== '') {
                $results_pagination_args['cbt_result_kelas'] = $selected_kelas;
            }
            if ($student_keyword !== '') {
                $results_pagination_args['cbt_student_q'] = $student_keyword;
            }
            if ($results_bulk_job_active && $results_bulk_job_token !== '') {
                $results_pagination_args['cbt_results_bulk_token'] = $results_bulk_job_token;
            }
            $results_pagination_links = [];
            if ($total_pages > 1) {
                $results_pagination_links = paginate_links([
                    'base' => add_query_arg(array_merge($results_pagination_args, ['cbt_results_paged' => '%#%']), admin_url('admin.php')),
                    'format' => '',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'type' => 'array',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ]);
            }
            ?>
            <div class="tablenav bottom cbt-results-pagination-wrap">
                <div class="tablenav-pages cbt-results-pagination">
                    <span class="displaying-num cbt-results-total">
                        <?php echo esc_html(sprintf('Total attempts: %d', $total_attempts)); ?>
                    </span>
                    <?php if (!empty($results_pagination_links)): ?>
                        <span class="pagination-links cbt-results-pagination-links">
                            <?php foreach ($results_pagination_links as $results_pagination_link): ?>
                                <?php echo wp_kses_post($results_pagination_link); ?>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            </section>
            </div>
            <script>
                (function () {
                    if (!window.fetch || !window.DOMParser) {
                        return;
                    }

                    var sharedState = window.cbtResultsPageState || (window.cbtResultsPageState = {});
                    var searchSubmitTimer = 0;
                    var panelRequestSeq = 0;
                    var panelIds = [
                        'cbt-results-notices',
                        'cbt-results-batch-card',
                        'cbt-results-filter-card',
                        'cbt-results-attempts-card',
                        'cbt-results-essay-card'
                    ];
                    sharedState.resultsBulkJobActive = !!document.getElementById('cbt-results-bulk-progress-card');

                    function isFilterField(target) {
                        return !!(
                            target &&
                            target.form &&
                            target.form.classList &&
                            target.form.classList.contains('cbt-results-filter-form')
                        );
                    }

                    function buildFormUrl(form) {
                        var nextUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                        var formData = new FormData(form);

                        nextUrl.search = '';
                        formData.forEach(function (value, key) {
                            if (typeof value !== 'string') {
                                return;
                            }
                            nextUrl.searchParams.set(key, value);
                        });

                        return nextUrl;
                    }

                    function replacePanel(parsed, panelId) {
                        var currentPanel = document.getElementById(panelId);
                        var nextPanel = parsed.getElementById(panelId);
                        if (!currentPanel || !nextPanel) {
                            return false;
                        }

                        currentPanel.innerHTML = nextPanel.innerHTML;
                        return true;
                    }

                    function applyPanelUpdate(parsed, options) {
                        var targetPanelIds = options && Array.isArray(options.panelIds) && options.panelIds.length
                            ? options.panelIds
                            : panelIds;
                        var replacedPanels = 0;

                        targetPanelIds.forEach(function (panelId) {
                            if (replacePanel(parsed, panelId)) {
                                replacedPanels += 1;
                            }
                        });

                        if (replacedPanels !== targetPanelIds.length) {
                            return false;
                        }

                        var historyUrl = options && options.historyUrl
                            ? new URL(options.historyUrl.toString(), window.location.href)
                            : null;
                        if (historyUrl && (!options || options.updateHistory !== false) && window.history && typeof window.history.replaceState === 'function') {
                            window.history.replaceState({}, '', historyUrl.toString());
                        }

                        document.dispatchEvent(new CustomEvent('cbt-results-panels-updated', {
                            detail: {
                                url: historyUrl ? historyUrl.toString() : window.location.href,
                                panelIds: targetPanelIds.slice(),
                                message: options && options.message ? String(options.message) : '',
                                messageType: options && options.messageType ? String(options.messageType) : ''
                            }
                        }));

                        return true;
                    }

                    function fallbackToNativeSubmit(form) {
                        if (!form || form.dataset.cbtNativeSubmit === '1') {
                            return;
                        }

                        form.dataset.cbtNativeSubmit = '1';
                        if (window.HTMLFormElement && window.HTMLFormElement.prototype && typeof window.HTMLFormElement.prototype.submit === 'function') {
                            window.HTMLFormElement.prototype.submit.call(form);
                            return;
                        }

                        form.submit();
                    }

                    function setAttemptActionBusy(form, isBusy) {
                        if (!form) {
                            return;
                        }

                        if (isBusy) {
                            form.dataset.cbtAsyncSubmitting = '1';
                        } else {
                            delete form.dataset.cbtAsyncSubmitting;
                        }

                        Array.prototype.forEach.call(form.querySelectorAll('button, input[type="submit"]'), function (control) {
                            if (isBusy) {
                                control.disabled = true;
                                if (control.tagName === 'BUTTON') {
                                    if (!control.dataset.cbtOriginalLabel) {
                                        control.dataset.cbtOriginalLabel = control.textContent;
                                    }
                                    control.textContent = 'Memproses...';
                                } else if (control.tagName === 'INPUT') {
                                    if (!control.dataset.cbtOriginalLabel) {
                                        control.dataset.cbtOriginalLabel = control.value;
                                    }
                                    control.value = 'Memproses...';
                                }
                                return;
                            }

                            control.disabled = false;
                            if (control.tagName === 'BUTTON' && control.dataset.cbtOriginalLabel) {
                                control.textContent = control.dataset.cbtOriginalLabel;
                            } else if (control.tagName === 'INPUT' && control.dataset.cbtOriginalLabel) {
                                control.value = control.dataset.cbtOriginalLabel;
                            }
                        });
                    }

                    function isAttemptActionForm(form) {
                        return !!(
                            form &&
                            form.matches &&
                            form.matches('form[data-cbt-attempt-partial-submit="attempts"]')
                        );
                    }

                    function readActionFeedback(response) {
                        try {
                            var finalUrl = new URL(response.url || window.location.href, window.location.href);
                            var errorMessage = finalUrl.searchParams.get('cbt_err');
                            if (errorMessage) {
                                return {
                                    message: errorMessage,
                                    type: 'error'
                                };
                            }

                            var successMessage = finalUrl.searchParams.get('cbt_msg');
                            if (successMessage) {
                                return {
                                    message: successMessage,
                                    type: 'success'
                                };
                            }
                        } catch (error) {
                            return null;
                        }

                        return null;
                    }

                    async function submitAttemptActionForm(form) {
                        var actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                        var method = String(form.getAttribute('method') || 'post').toUpperCase();
                        var formData = new FormData(form);

                        panelRequestSeq += 1;
                        var requestSeq = panelRequestSeq;
                        sharedState.panelRefreshInFlight = true;
                        setAttemptActionBusy(form, true);

                        try {
                            var response = await fetch(actionUrl.toString(), {
                                method: method,
                                body: formData,
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                fallbackToNativeSubmit(form);
                                return;
                            }

                            var html = await response.text();
                            if (requestSeq !== panelRequestSeq) {
                                return;
                            }

                            var parsed = new DOMParser().parseFromString(html, 'text/html');
                            var feedback = readActionFeedback(response);
                            var updated = applyPanelUpdate(parsed, {
                                panelIds: ['cbt-results-attempts-card'],
                                updateHistory: false,
                                historyUrl: new URL(window.location.href),
                                message: feedback && feedback.message ? feedback.message : '',
                                messageType: feedback && feedback.type ? feedback.type : ''
                            });

                            if (!updated) {
                                fallbackToNativeSubmit(form);
                            }
                        } catch (error) {
                            if (requestSeq === panelRequestSeq) {
                                fallbackToNativeSubmit(form);
                            }
                        } finally {
                            if (requestSeq === panelRequestSeq) {
                                sharedState.panelRefreshInFlight = false;
                            }
                            setAttemptActionBusy(form, false);
                        }
                    }

                    async function refreshResultsPanels(targetUrl) {
                        var historyUrl = new URL(targetUrl.toString(), window.location.href);
                        var fetchUrl = new URL(historyUrl.toString());
                        fetchUrl.searchParams.set('cbt_live_refresh', String(Date.now()));

                        panelRequestSeq += 1;
                        var requestSeq = panelRequestSeq;
                        sharedState.panelRefreshInFlight = true;

                        try {
                            var response = await fetch(fetchUrl.toString(), {
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                window.location.assign(historyUrl.toString());
                                return;
                            }

                            var html = await response.text();
                            if (requestSeq !== panelRequestSeq) {
                                return;
                            }

                            var parsed = new DOMParser().parseFromString(html, 'text/html');
                            if (!applyPanelUpdate(parsed, {
                                historyUrl: historyUrl
                            })) {
                                window.location.assign(historyUrl.toString());
                            }
                        } catch (error) {
                            if (requestSeq === panelRequestSeq) {
                                window.location.assign(historyUrl.toString());
                            }
                        } finally {
                            if (requestSeq === panelRequestSeq) {
                                sharedState.panelRefreshInFlight = false;
                            }
                        }
                    }
                    sharedState.refreshResultsPanels = refreshResultsPanels;

                    function scheduleFilterRefresh(form) {
                        window.clearTimeout(searchSubmitTimer);
                        searchSubmitTimer = window.setTimeout(function () {
                            refreshResultsPanels(buildFormUrl(form));
                        }, 450);
                    }

                    document.addEventListener('submit', function (event) {
                        var form = event.target;
                        if (!form || event.defaultPrevented) {
                            return;
                        }

                        if (isAttemptActionForm(form)) {
                            if (form.dataset.cbtNativeSubmit === '1' || form.dataset.cbtAsyncSubmitting === '1') {
                                return;
                            }

                            event.preventDefault();
                            submitAttemptActionForm(form);
                            return;
                        }

                        if (!form || !form.classList || !form.classList.contains('cbt-results-filter-form')) {
                            return;
                        }

                        event.preventDefault();
                        window.clearTimeout(searchSubmitTimer);
                        refreshResultsPanels(buildFormUrl(form));
                    });

                    document.addEventListener('change', function (event) {
                        var target = event.target;
                        if (!isFilterField(target)) {
                            return;
                        }

                        if (target.matches('select')) {
                            window.clearTimeout(searchSubmitTimer);
                            refreshResultsPanels(buildFormUrl(target.form));
                            return;
                        }

                        if (target.matches('input[type="search"]')) {
                            window.clearTimeout(searchSubmitTimer);
                            refreshResultsPanels(buildFormUrl(target.form));
                        }
                    });

                    document.addEventListener('input', function (event) {
                        var target = event.target;
                        if (!isFilterField(target) || !target.matches('input[type="search"]')) {
                            return;
                        }

                        scheduleFilterRefresh(target.form);
                    });

                    document.addEventListener('search', function (event) {
                        var target = event.target;
                        if (!isFilterField(target) || !target.matches('input[type="search"]')) {
                            return;
                        }

                        scheduleFilterRefresh(target.form);
                    });

                    document.addEventListener('keydown', function (event) {
                        var target = event.target;
                        if (!isFilterField(target) || !target.matches('input[type="search"]') || event.key !== 'Enter') {
                            return;
                        }

                        event.preventDefault();
                        window.clearTimeout(searchSubmitTimer);
                        refreshResultsPanels(buildFormUrl(target.form));
                    });

                    document.addEventListener('click', function (event) {
                        var resetLink = event.target && event.target.closest ? event.target.closest('[data-cbt-filter-reset]') : null;
                        if (resetLink) {
                            if (resetLink.getAttribute('aria-disabled') === 'true' || resetLink.classList.contains('is-disabled')) {
                                event.preventDefault();
                                return;
                            }
                            event.preventDefault();
                            window.clearTimeout(searchSubmitTimer);
                            refreshResultsPanels(new URL(resetLink.getAttribute('href') || window.location.href, window.location.href));
                            return;
                        }

                        var paginationLink = event.target && event.target.closest ? event.target.closest('#cbt-results-attempts-card .cbt-results-pagination-links a') : null;
                        if (!paginationLink) {
                            return;
                        }

                        event.preventDefault();
                        refreshResultsPanels(new URL(paginationLink.getAttribute('href') || window.location.href, window.location.href));
                    });
                })();

                (function () {
                    if (!window.fetch) {
                        return;
                    }

                    var sharedState = window.cbtResultsPageState || (window.cbtResultsPageState = {});
                    var retryCount = 0;
                    var maxRetryCount = 2;
                    var retryDelayMs = 1500;
                    var nextTickTimer = 0;
                    var isTickInFlight = false;
                    var isStopRequestInFlight = false;

                    function getProgressCard() {
                        return document.getElementById('cbt-results-bulk-progress-card');
                    }

                    function setBulkJobActive(active) {
                        sharedState.resultsBulkJobActive = !!active;
                    }

                    function setCardState(stateName) {
                        var card = getProgressCard();
                        if (!card) {
                            return;
                        }

                        card.classList.toggle('is-paused', stateName === 'paused');
                        card.classList.toggle('is-error', stateName === 'error');
                        card.classList.toggle('is-stopping', stateName === 'stopping');
                    }

                    function updateRoleText(roleName, value) {
                        var card = getProgressCard();
                        if (!card) {
                            return;
                        }

                        var target = card.querySelector('[data-cbt-results-bulk-role="' + roleName + '"]');
                        if (target) {
                            target.textContent = String(value);
                        }
                    }

                    function updateProgressPayload(payload) {
                        var card = getProgressCard();
                        if (!card || !payload) {
                            return;
                        }

                        var percent = Math.max(0, Math.min(100, Number(payload.progress_percent || 0)));
                        var stopRequested = !!payload.stop_requested;
                        var canStop = !!payload.can_stop && !stopRequested && !payload.complete;
                        card.setAttribute('data-cbt-results-bulk-status', String(payload.status || 'running'));
                        updateRoleText('status', payload.status_label || 'Berjalan');
                        updateRoleText('message', payload.message || 'Batch results sedang berjalan.');
                        updateRoleText('detail', payload.status_detail || '');
                        updateRoleText('processed', payload.processed_count || 0);
                        updateRoleText('total', payload.total || 0);
                        updateRoleText('success', payload.success_count || 0);
                        updateRoleText('failure', payload.failure_count || 0);
                        updateRoleText('reset-count', payload.reset_count || 0);
                        updateRoleText('abandoned-count', payload.abandoned_count || 0);
                        updateRoleText('completed-count', payload.completed_count || 0);
                        updateRoleText('percent', percent.toFixed(2) + '%');

                        var fill = card.querySelector('[data-cbt-results-bulk-role="progress-fill"]');
                        if (fill) {
                            fill.style.width = percent.toFixed(2) + '%';
                        }

                        var stopButton = card.querySelector('[data-cbt-results-bulk-role="stop-button"]');
                        if (stopButton) {
                            stopButton.disabled = !canStop || isStopRequestInFlight;
                            stopButton.textContent = stopRequested ? 'Menghentikan...' : 'Stop Batch';
                        }

                        setCardState(stopRequested && !payload.complete ? 'stopping' : '');
                    }

                    function scheduleNextTick(delayMs) {
                        window.clearTimeout(nextTickTimer);
                        nextTickTimer = window.setTimeout(runTick, typeof delayMs === 'number' ? delayMs : 350);
                    }

                    function buildRequestBody(card, actionAttr, nonceAttr) {
                        var formData = new FormData();
                        formData.append('action', String(card.getAttribute(actionAttr || 'data-cbt-results-bulk-action') || 'cbt_results_bulk_job_tick'));
                        formData.append('token', String(card.getAttribute('data-cbt-results-bulk-token') || ''));
                        formData.append('nonce', String(card.getAttribute(nonceAttr || 'data-cbt-results-bulk-nonce') || ''));
                        return formData;
                    }

                    function resumeWithReload(card) {
                        setCardState('paused');
                        updateRoleText('message', 'Koneksi admin terputus. Job server tetap tersimpan.');
                        updateRoleText('detail', 'Reload halaman yang sama untuk melanjutkan polling progress.');
                        setBulkJobActive(true);
                        if (card) {
                            var resumeUrl = String(card.getAttribute('data-cbt-results-bulk-resume-url') || window.location.href);
                            card.setAttribute('data-cbt-results-bulk-resume-url', resumeUrl);
                        }
                    }

                    async function completeAndRefresh(redirectUrl) {
                        setBulkJobActive(false);
                        window.clearTimeout(nextTickTimer);

                        if (sharedState.refreshResultsPanels && typeof sharedState.refreshResultsPanels === 'function' && redirectUrl) {
                            sharedState.refreshResultsPanels(new URL(redirectUrl, window.location.href));
                            return;
                        }

                        window.location.assign(redirectUrl || window.location.href);
                    }

                    async function runTick() {
                        var card = getProgressCard();
                        if (!card || isTickInFlight) {
                            setBulkJobActive(!!card);
                            return;
                        }

                        isTickInFlight = true;
                        setBulkJobActive(true);

                        try {
                            var response = await fetch(String(card.getAttribute('data-cbt-results-bulk-ajax-url') || ''), {
                                method: 'POST',
                                body: buildRequestBody(card, 'data-cbt-results-bulk-action', 'data-cbt-results-bulk-nonce'),
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            var result = null;
                            try {
                                result = await response.json();
                            } catch (jsonError) {
                                result = null;
                            }

                            if (!response.ok || !result || !result.data) {
                                throw new Error('invalid_results_bulk_response');
                            }

                            retryCount = 0;
                            updateProgressPayload(result.data);

                            if (result.data.complete) {
                                await completeAndRefresh(String(result.data.redirect_url || ''));
                                return;
                            }

                            scheduleNextTick(350);
                        } catch (error) {
                            retryCount += 1;
                            if (retryCount <= maxRetryCount) {
                                setCardState('paused');
                                updateRoleText('detail', 'Koneksi admin tersendat. Mencoba ulang...');
                                scheduleNextTick(retryDelayMs);
                                return;
                            }

                            resumeWithReload(card);
                        } finally {
                            isTickInFlight = false;
                        }
                    }

                    async function requestStop(card) {
                        if (!card || isStopRequestInFlight) {
                            return;
                        }

                        isStopRequestInFlight = true;
                        try {
                            var stopButton = card.querySelector('[data-cbt-results-bulk-role="stop-button"]');
                            if (stopButton) {
                                stopButton.disabled = true;
                                stopButton.textContent = 'Menghentikan...';
                            }

                            var response = await fetch(String(card.getAttribute('data-cbt-results-bulk-ajax-url') || ''), {
                                method: 'POST',
                                body: buildRequestBody(card, 'data-cbt-results-bulk-stop-action', 'data-cbt-results-bulk-stop-nonce'),
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            var result = null;
                            try {
                                result = await response.json();
                            } catch (jsonError) {
                                result = null;
                            }

                            if (!response.ok || !result || !result.data) {
                                throw new Error('invalid_results_bulk_stop_response');
                            }

                            updateProgressPayload(result.data);
                            if (result.data.complete) {
                                await completeAndRefresh(String(result.data.redirect_url || ''));
                                return;
                            }

                            if (!isTickInFlight) {
                                scheduleNextTick(120);
                            }
                        } catch (error) {
                            setCardState('error');
                            updateRoleText('message', 'Permintaan stop gagal dikirim.');
                            updateRoleText('detail', 'Coba tekan Stop lagi atau reload halaman results ini.');
                        } finally {
                            isStopRequestInFlight = false;
                        }
                    }

                    document.addEventListener('click', function (event) {
                        var stopButton = event.target && event.target.closest ? event.target.closest('[data-cbt-results-bulk-stop="1"]') : null;
                        if (!stopButton) {
                            return;
                        }

                        event.preventDefault();
                        var card = getProgressCard();
                        if (!card || stopButton.disabled) {
                            return;
                        }

                        requestStop(card);
                    });

                    document.addEventListener('cbt-results-panels-updated', function () {
                        var card = getProgressCard();
                        setBulkJobActive(!!card);
                        if (!card) {
                            window.clearTimeout(nextTickTimer);
                            retryCount = 0;
                            return;
                        }

                        retryCount = 0;
                        scheduleNextTick(350);
                    });

                    if (getProgressCard()) {
                        scheduleNextTick(120);
                    } else {
                        setBulkJobActive(false);
                    }
                })();

                (function () {
                    if (!window.fetch || !window.DOMParser) {
                        return;
                    }

                    var sharedState = window.cbtResultsPageState || (window.cbtResultsPageState = {});
                    var normalRefreshIntervalMs = 10000;
                    var finalizePendingRefreshIntervalMs = 3000;
                    var inFlight = false;
                    var lastBodyHtml = '';
                    var autoRefreshStorageKey = 'cbt_attempts_auto_refresh_enabled';
                    var autoRefreshEnabled = true;
                    var liveStatusResetTimer = 0;
                    var autoRefreshTimerId = 0;

                    function getAttemptsTable() {
                        return document.getElementById('cbt-attempts-table');
                    }

                    function getAttemptsBody() {
                        return document.getElementById('cbt-attempts-tbody');
                    }

                    function getLiveStatus() {
                        return document.getElementById('cbt-attempts-live-status');
                    }

                    function getAutoRefreshToggle() {
                        return document.getElementById('cbt-attempts-auto-refresh-toggle');
                    }

                    if (!getAttemptsTable() || !getAttemptsBody()) {
                        return;
                    }

                    function hasFinalizePendingRows() {
                        var attemptsBody = getAttemptsBody();
                        return !!(attemptsBody && attemptsBody.querySelector('[data-cbt-finalize-pending="1"]'));
                    }

                    function getCurrentRefreshIntervalMs() {
                        return hasFinalizePendingRows()
                            ? finalizePendingRefreshIntervalMs
                            : normalRefreshIntervalMs;
                    }

                    function clearAutoRefreshTimer() {
                        window.clearTimeout(autoRefreshTimerId);
                        autoRefreshTimerId = 0;
                    }

                    function scheduleAutoRefreshTick(delayMs) {
                        clearAutoRefreshTimer();
                        if (!autoRefreshEnabled) {
                            return;
                        }

                        autoRefreshTimerId = window.setTimeout(function () {
                            Promise.resolve(refreshAttemptsTable()).then(function () {
                                scheduleAutoRefreshTick(getCurrentRefreshIntervalMs());
                            });
                        }, Math.max(1000, Number(delayMs) || getCurrentRefreshIntervalMs()));
                    }

                    function syncBodySnapshot() {
                        var attemptsBody = getAttemptsBody();
                        lastBodyHtml = attemptsBody ? String(attemptsBody.innerHTML || '').trim() : '';
                    }

                    function readAutoRefreshPreference() {
                        try {
                            var raw = window.localStorage ? window.localStorage.getItem(autoRefreshStorageKey) : null;
                            if (raw === null) {
                                return true;
                            }
                            return raw === '1';
                        } catch (error) {
                            return true;
                        }
                    }

                    function writeAutoRefreshPreference(enabled) {
                        try {
                            if (!window.localStorage) {
                                return;
                            }
                            window.localStorage.setItem(autoRefreshStorageKey, enabled ? '1' : '0');
                        } catch (error) {
                            // Ignore storage write errors.
                        }
                    }

                    function setLiveStatus(message) {
                        var liveStatus = getLiveStatus();
                        if (!liveStatus) {
                            return;
                        }
                        liveStatus.textContent = message;
                    }

                    function syncAutoRefreshUIState() {
                        var autoRefreshToggle = getAutoRefreshToggle();
                        if (autoRefreshToggle) {
                            autoRefreshToggle.checked = !!autoRefreshEnabled;
                        }
                        if (!autoRefreshEnabled) {
                            setLiveStatus('Auto refresh nonaktif.');
                            return;
                        }
                        if (hasFinalizePendingRows()) {
                            setLiveStatus('Memantau finalisasi background setiap 3 detik.');
                            return;
                        }
                        setLiveStatus('Auto refresh aktif setiap 10 detik.');
                    }

                    function showTransientLiveStatus(message) {
                        window.clearTimeout(liveStatusResetTimer);
                        if (!message) {
                            syncAutoRefreshUIState();
                            return;
                        }

                        setLiveStatus(message);
                        liveStatusResetTimer = window.setTimeout(function () {
                            syncAutoRefreshUIState();
                        }, 3200);
                    }

                    function nowText() {
                        try {
                            return new Date().toLocaleTimeString('id-ID');
                        } catch (error) {
                            return new Date().toLocaleTimeString();
                        }
                    }

                    function formatRemainingTime(totalSeconds) {
                        var seconds = Math.max(0, Number(totalSeconds) || 0);
                        var hours = Math.floor(seconds / 3600);
                        var minutes = Math.floor((seconds % 3600) / 60);
                        var remainingSeconds = seconds % 60;
                        return [
                            String(hours).padStart(2, '0'),
                            String(minutes).padStart(2, '0'),
                            String(remainingSeconds).padStart(2, '0')
                        ].join(':');
                    }

                    function updateRemainingTimeCell(container) {
                        if (!container) {
                            return;
                        }

                        var status = String(container.getAttribute('data-cbt-attempt-status') || '');
                        var totalSeconds = Math.max(0, parseInt(String(container.getAttribute('data-cbt-total-seconds') || '0'), 10) || 0);
                        var remainingSeconds = Math.max(0, parseInt(String(container.getAttribute('data-cbt-remaining-seconds') || '0'), 10) || 0);
                        var labelEl = container.querySelector('[data-cbt-remaining-label]');
                        var fillEl = container.querySelector('[data-cbt-remaining-fill]');
                        var finalizePending = String(container.getAttribute('data-cbt-finalize-pending') || '') === '1';

                        if (labelEl) {
                            labelEl.classList.remove('is-warning', 'is-expired', 'is-completed');
                        }
                        if (fillEl) {
                            fillEl.classList.remove('is-warning', 'is-expired', 'is-completed');
                        }

                        if (status !== 'in_progress') {
                            if (labelEl) {
                                labelEl.textContent = 'Selesai';
                                labelEl.classList.add('is-completed');
                            }
                            if (fillEl) {
                                fillEl.style.width = '100%';
                                fillEl.classList.add('is-completed');
                            }
                            return;
                        }

                        var widthPercent = totalSeconds > 0
                            ? Math.max(0, Math.min(100, (remainingSeconds / totalSeconds) * 100))
                            : 0;

                        if (labelEl) {
                            labelEl.textContent = remainingSeconds > 0
                                ? formatRemainingTime(remainingSeconds)
                                : (finalizePending ? 'Diproses' : 'Habis');
                            if (remainingSeconds <= 0) {
                                labelEl.classList.add('is-expired');
                            } else if (remainingSeconds <= 600) {
                                labelEl.classList.add('is-warning');
                            }
                        }

                        if (fillEl) {
                            fillEl.style.width = widthPercent.toFixed(2) + '%';
                            if (remainingSeconds <= 0) {
                                fillEl.classList.add('is-expired');
                            } else if (remainingSeconds <= 600) {
                                fillEl.classList.add('is-warning');
                            }
                        }
                    }

                    function tickRemainingTimeCells() {
                        var attemptsBody = getAttemptsBody();
                        if (!attemptsBody) {
                            return;
                        }

                        var containers = attemptsBody.querySelectorAll('[data-cbt-remaining-seconds]');
                        containers.forEach(function (container) {
                            var status = String(container.getAttribute('data-cbt-attempt-status') || '');
                            if (status === 'in_progress') {
                                var remainingSeconds = Math.max(0, parseInt(String(container.getAttribute('data-cbt-remaining-seconds') || '0'), 10) || 0);
                                if (remainingSeconds > 0) {
                                    container.setAttribute('data-cbt-remaining-seconds', String(remainingSeconds - 1));
                                }
                            }
                            updateRemainingTimeCell(container);
                        });
                    }

                    async function refreshAttemptsTable() {
                        if (sharedState.resultsBulkJobActive) {
                            setLiveStatus('Auto refresh ditahan selama batch results aktif.');
                            return;
                        }

                        if (!autoRefreshEnabled || inFlight || document.hidden || sharedState.panelRefreshInFlight) {
                            return;
                        }

                        var attemptsBody = getAttemptsBody();
                        if (!attemptsBody) {
                            return;
                        }

                        inFlight = true;
                        try {
                            var sourceUrl = new URL(window.location.href);
                            sourceUrl.searchParams.set('cbt_live_refresh', String(Date.now()));

                            var response = await fetch(sourceUrl.toString(), {
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            if (!response.ok) {
                                setLiveStatus('Auto refresh gagal (' + response.status + ').');
                                return;
                            }

                            var html = await response.text();
                            var parsed = new DOMParser().parseFromString(html, 'text/html');
                            var nextBody = parsed.getElementById('cbt-attempts-tbody');
                            if (!nextBody) {
                                setLiveStatus('Auto refresh: data attempts tidak ditemukan.');
                                return;
                            }

                            var nextBodyHtml = String(nextBody.innerHTML || '').trim();
                            var currentBody = getAttemptsBody();
                            if (!currentBody) {
                                return;
                            }

                            if (nextBodyHtml !== lastBodyHtml) {
                                currentBody.innerHTML = nextBodyHtml;
                                lastBodyHtml = nextBodyHtml;
                                if (typeof sharedState.syncAttemptAnswerDetails === 'function') {
                                    sharedState.syncAttemptAnswerDetails(currentBody);
                                }
                                tickRemainingTimeCells();
                                setLiveStatus('Auto refresh: data diperbarui ' + nowText() + '.');
                            } else {
                                setLiveStatus('Auto refresh: tidak ada perubahan (' + nowText() + ').');
                            }
                            syncAutoRefreshUIState();
                        } catch (error) {
                            setLiveStatus('Auto refresh gagal. Cek jaringan/browser.');
                        } finally {
                            inFlight = false;
                        }
                    }

                    autoRefreshEnabled = readAutoRefreshPreference();
                    syncBodySnapshot();
                    syncAutoRefreshUIState();
                    tickRemainingTimeCells();

                    document.addEventListener('change', function (event) {
                        var target = event.target;
                        if (!target || target.id !== 'cbt-attempts-auto-refresh-toggle') {
                            return;
                        }

                        autoRefreshEnabled = !!target.checked;
                        writeAutoRefreshPreference(autoRefreshEnabled);
                        syncAutoRefreshUIState();
                        if (autoRefreshEnabled) {
                            refreshAttemptsTable();
                            scheduleAutoRefreshTick(500);
                        } else {
                            clearAutoRefreshTimer();
                        }
                    });

                    document.addEventListener('cbt-results-panels-updated', function (event) {
                        syncBodySnapshot();
                        tickRemainingTimeCells();
                        if (document.getElementById('cbt-results-bulk-progress-card')) {
                            sharedState.resultsBulkJobActive = true;
                        } else if (!sharedState.resultsBulkJobActive) {
                            sharedState.resultsBulkJobActive = false;
                        }
                        var feedbackMessage = event && event.detail && event.detail.message ? String(event.detail.message) : '';
                        if (feedbackMessage !== '') {
                            showTransientLiveStatus(feedbackMessage);
                            return;
                        }
                        syncAutoRefreshUIState();
                        scheduleAutoRefreshTick(getCurrentRefreshIntervalMs());
                    });

                    window.setInterval(tickRemainingTimeCells, 1000);
                    scheduleAutoRefreshTick(getCurrentRefreshIntervalMs());
	                    document.addEventListener('visibilitychange', function () {
	                        if (!document.hidden && autoRefreshEnabled) {
	                            refreshAttemptsTable();
	                            tickRemainingTimeCells();
                                scheduleAutoRefreshTick(getCurrentRefreshIntervalMs());
	                        }
	                    });
	                })();
                    (function () {
                        var sharedState = window.cbtResultsPageState || (window.cbtResultsPageState = {});
                        if (!sharedState.answerDetailOpenIds) {
                            sharedState.answerDetailOpenIds = {};
                        }

                        function normalizeAttemptId(rawValue) {
                            var nextValue = parseInt(String(rawValue || '0'), 10);
                            return nextValue > 0 ? String(nextValue) : '';
                        }

                        function isAttemptDetailOpen(attemptId) {
                            return !!sharedState.answerDetailOpenIds[attemptId];
                        }

                        function setAttemptDetailOpen(attemptId, shouldOpen) {
                            if (!attemptId) {
                                return;
                            }

                            if (shouldOpen) {
                                sharedState.answerDetailOpenIds[attemptId] = true;
                                return;
                            }

                            delete sharedState.answerDetailOpenIds[attemptId];
                        }

                        function syncAttemptAnswerDetails(scope) {
                            var root = scope && scope.querySelectorAll ? scope : document;
                            Array.prototype.forEach.call(root.querySelectorAll('[data-cbt-attempt-answer-toggle]'), function (toggle) {
                                var attemptId = normalizeAttemptId(toggle.getAttribute('data-cbt-attempt-answer-toggle'));
                                var targetId = String(toggle.getAttribute('aria-controls') || '');
                                var detailRow = targetId ? document.getElementById(targetId) : null;
                                var isOpen = attemptId !== '' && isAttemptDetailOpen(attemptId) && !!detailRow;
                                var label = toggle.querySelector('.cbt-attempt-answer-toggle-label');
                                var nextLabel = String(toggle.getAttribute(isOpen ? 'data-close-label' : 'data-open-label') || 'Lihat Detail Jawaban');

                                toggle.classList.toggle('is-open', isOpen);
                                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                                if (label) {
                                    label.textContent = nextLabel;
                                }
                                if (detailRow) {
                                    detailRow.hidden = !isOpen;
                                }
                            });

                            Array.prototype.forEach.call(root.querySelectorAll('[data-cbt-attempt-answer-row]'), function (detailRow) {
                                var attemptId = normalizeAttemptId(detailRow.getAttribute('data-cbt-attempt-answer-row'));
                                if (!attemptId) {
                                    return;
                                }

                                detailRow.hidden = !isAttemptDetailOpen(attemptId);
                            });
                        }

                        sharedState.syncAttemptAnswerDetails = syncAttemptAnswerDetails;

                        document.addEventListener('click', function (event) {
                            var toggle = event.target && event.target.closest ? event.target.closest('[data-cbt-attempt-answer-toggle]') : null;
                            if (!toggle) {
                                return;
                            }

                            event.preventDefault();
                            var attemptId = normalizeAttemptId(toggle.getAttribute('data-cbt-attempt-answer-toggle'));
                            if (!attemptId) {
                                return;
                            }

                            setAttemptDetailOpen(attemptId, !isAttemptDetailOpen(attemptId));
                            syncAttemptAnswerDetails(document);
                        });

                        document.addEventListener('cbt-results-panels-updated', function () {
                            syncAttemptAnswerDetails(document);
                        });

                        syncAttemptAnswerDetails(document);
                    })();
	            </script>

	            <div
                id="cbt-results-tab-panel-essay"
                class="cbt-results-tab-panel<?php echo $active_results_tab === 'essay' ? ' is-active' : ''; ?>"
                role="tabpanel"
                aria-labelledby="cbt-results-tab-btn-essay"
                data-cbt-results-tab-panel="essay"
            >
            <section id="cbt-results-essay-card" class="cbt-results-card">
                <div class="cbt-results-card-header">
                    <div>
                        <h2>Koreksi Essay Massal</h2>
                        <p>Pilih satu soal essay, lalu nilai semua jawaban siswa untuk soal tersebut dari satu layar kerja.</p>
                    </div>
                </div>

                <form
                    method="get"
                    action="<?php echo esc_url(admin_url('admin.php')); ?>"
                    class="cbt-results-essay-filter-form"
                    data-cbt-essay-filter
                    data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                    data-nonce="<?php echo esc_attr(wp_create_nonce('cbt_results_essay_questions')); ?>"
                >
                    <input type="hidden" name="page" value="cbt-results" />
                    <input type="hidden" name="cbt_results_tab" value="essay" />
                    <div class="cbt-results-essay-toolbar">
                        <div class="cbt-results-field">
                            <label for="cbt-essay-exam-id">Exam</label>
                            <select id="cbt-essay-exam-id" name="cbt_essay_exam_id" data-cbt-essay-exam>
                                <option value="0">Pilih exam</option>
                                <?php foreach ($exam_filter_rows as $exam_filter_row): ?>
                                    <option value="<?php echo (int) ($exam_filter_row['id'] ?? 0); ?>" <?php selected($selected_essay_exam_id, (int) ($exam_filter_row['id'] ?? 0)); ?>>
                                        <?php echo esc_html((string) ($exam_filter_row['title'] ?? '-')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-results-field">
                            <label for="cbt-essay-question-id">Soal Essay</label>
                            <select id="cbt-essay-question-id" name="cbt_essay_question_id" data-cbt-essay-question data-selected-question="<?php echo (int) $selected_essay_question_id; ?>" <?php disabled($selected_essay_exam_id <= 0); ?>>
                                <option value="0"><?php echo $selected_essay_exam_id > 0 ? 'Pilih soal essay' : 'Pilih exam dulu'; ?></option>
                                <?php foreach ($essay_question_rows as $essay_question_row): ?>
                                    <option value="<?php echo (int) ($essay_question_row['id'] ?? 0); ?>" <?php selected($selected_essay_question_id, (int) ($essay_question_row['id'] ?? 0)); ?>>
                                        <?php echo esc_html((string) ($essay_question_row['label'] ?? '-')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-results-field">
                            <label for="cbt-essay-kelas">Kelas</label>
                            <select id="cbt-essay-kelas" name="cbt_essay_kelas" data-cbt-essay-auto-filter>
                                <option value="">Semua kelas</option>
                                <?php foreach ($kelas_filter_rows as $kelas_filter_row): ?>
                                    <option value="<?php echo esc_attr($kelas_filter_row); ?>" <?php selected($selected_essay_kelas, $kelas_filter_row); ?>>
                                        <?php echo esc_html($kelas_filter_row); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cbt-results-field">
                            <label for="cbt-essay-q">Cari Siswa</label>
                            <input id="cbt-essay-q" type="search" name="cbt_essay_q" value="<?php echo esc_attr($selected_essay_keyword); ?>" placeholder="Nama, username, NISN" data-cbt-essay-search />
                        </div>
                        <div class="cbt-results-filter-actions">
                            <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'cbt-results', 'cbt_results_tab' => 'essay'], admin_url('admin.php'))); ?>" data-cbt-essay-reset-filter>Reset Filter</a>
                        </div>
                    </div>
                </form>

                <div class="cbt-results-essay-content" data-cbt-essay-content>
                <?php if (current_user_can('manage_options')): ?>
                    <?php
                    $essay_ai_settings = is_array($essay_ai_settings ?? null) ? $essay_ai_settings : [];
                    $essay_ai_status = is_array($essay_ai_status ?? null) ? $essay_ai_status : [];
                    $essay_ai_gemini_models = is_array($essay_ai_gemini_models ?? null) ? $essay_ai_gemini_models : [];
                    $essay_ai_openai_models = is_array($essay_ai_openai_models ?? null) ? $essay_ai_openai_models : [];
                    $essay_ai_provider_options = is_array($essay_ai_provider_options ?? null) ? $essay_ai_provider_options : [];
                    $essay_ai_model_options_by_provider = is_array($essay_ai_model_options_by_provider ?? null) ? $essay_ai_model_options_by_provider : [];
                    $essay_ai_current_provider = (string) ($essay_ai_settings['provider'] ?? 'gemini');
                    $essay_ai_current_model = (string) ($essay_ai_settings['model'] ?? 'gemini-2.5-flash-lite');
                    $essay_ai_gemini_models_source = (string) ($essay_ai_gemini_models['source'] ?? 'fallback');
                    $essay_ai_gemini_models_message = (string) ($essay_ai_gemini_models['message'] ?? '');
                    $essay_ai_gemini_models_verified = in_array($essay_ai_gemini_models_source, ['api', 'cache'], true);
                    $essay_ai_openai_models_source = (string) ($essay_ai_openai_models['source'] ?? 'fallback');
                    $essay_ai_openai_models_message = (string) ($essay_ai_openai_models['message'] ?? '');
                    $essay_ai_openai_models_verified = in_array($essay_ai_openai_models_source, ['api', 'cache'], true);
                    if (empty($essay_ai_provider_options)) {
                        $essay_ai_provider_options = [
                            'gemini' => 'Google Gemini',
                            'openai' => 'OpenAI',
                        ];
                    }
                    if (!array_key_exists($essay_ai_current_provider, $essay_ai_provider_options)) {
                        $essay_ai_current_provider = 'gemini';
                    }
                    if (empty($essay_ai_model_options_by_provider)) {
                        $essay_ai_model_options_by_provider = [
                            'gemini' => ['gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite (Recommended, free quota)'],
                            'openai' => ['gpt-5.4-mini' => 'GPT-5.4 Mini (Recommended)'],
                        ];
                    }
                    $essay_ai_gemini_model_missing = $essay_ai_current_provider === 'gemini'
                        && $essay_ai_current_model !== ''
                        && !array_key_exists($essay_ai_current_model, $essay_ai_model_options_by_provider['gemini'] ?? []);
                    $essay_ai_openai_model_missing = $essay_ai_current_provider === 'openai'
                        && $essay_ai_current_model !== ''
                        && !array_key_exists($essay_ai_current_model, $essay_ai_model_options_by_provider['openai'] ?? []);
                    if ($essay_ai_current_model !== '' && !array_key_exists($essay_ai_current_model, $essay_ai_model_options_by_provider[$essay_ai_current_provider] ?? [])) {
                        if (
                            ($essay_ai_current_provider === 'gemini' && !$essay_ai_gemini_models_verified)
                            || ($essay_ai_current_provider === 'openai' && !$essay_ai_openai_models_verified)
                        ) {
                            $essay_ai_model_options_by_provider[$essay_ai_current_provider][$essay_ai_current_model] = $essay_ai_current_model . ' (custom/current)';
                        }
                    }
                    $essay_ai_gemini_model_status = 'Daftar model Gemini memakai fallback sampai API key disimpan.';
                    if ($essay_ai_gemini_model_missing && $essay_ai_gemini_models_verified) {
                        $essay_ai_gemini_model_status = 'Model Gemini tersimpan tidak ada di ListModels. Pilih model yang tersedia lalu simpan.';
                    } elseif ($essay_ai_gemini_models_message !== '') {
                        $essay_ai_gemini_model_status = $essay_ai_gemini_models_message;
                    }
                    $essay_ai_openai_model_status = 'Daftar model OpenAI memakai fallback sampai API key disimpan.';
                    if ($essay_ai_openai_model_missing && $essay_ai_openai_models_verified) {
                        $essay_ai_openai_model_status = 'Model OpenAI tersimpan tidak ada di Models API. Pilih model yang tersedia lalu simpan.';
                    } elseif ($essay_ai_openai_models_message !== '') {
                        $essay_ai_openai_model_status = $essay_ai_openai_models_message;
                    }
                    $essay_ai_model_status = $essay_ai_current_provider === 'openai' ? $essay_ai_openai_model_status : $essay_ai_gemini_model_status;
                    $essay_ai_gemini_endpoint = (string) ($essay_ai_settings['gemini_endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent');
                    $essay_ai_current_openai_endpoint = (string) ($essay_ai_settings['endpoint'] ?? 'https://api.openai.com/v1/responses');
                    $essay_ai_openai_endpoint_options = [
                        'https://api.openai.com/v1/responses' => 'Responses API (Recommended)',
                        'https://api.openai.com/v1/chat/completions' => 'Chat Completions API (Legacy)',
                    ];
                    if ($essay_ai_current_openai_endpoint !== '' && !array_key_exists($essay_ai_current_openai_endpoint, $essay_ai_openai_endpoint_options)) {
                        $essay_ai_openai_endpoint_options[$essay_ai_current_openai_endpoint] = 'Custom/current endpoint';
                    }
                    $essay_ai_gemini_has_api_key = !empty($essay_ai_settings['gemini_has_api_key']);
                    $essay_ai_openai_has_api_key = !empty($essay_ai_settings['openai_has_api_key']);
                    $essay_ai_current_has_api_key = $essay_ai_current_provider === 'openai' ? $essay_ai_openai_has_api_key : $essay_ai_gemini_has_api_key;
                    $essay_ai_current_provider_name = $essay_ai_current_provider === 'openai' ? 'OpenAI' : 'Gemini';
                    $essay_ai_key_title = $essay_ai_current_has_api_key ? 'API key ' . $essay_ai_current_provider_name . ' tersimpan' : 'Belum ada API key ' . $essay_ai_current_provider_name;
                    $essay_ai_key_message = $essay_ai_current_has_api_key
                        ? 'Secret ' . $essay_ai_current_provider_name . ' sudah tersimpan aman. Kosongkan field API Key jika tidak ingin mengganti.'
                        : 'Masukkan API key ' . $essay_ai_current_provider_name . ', lalu klik Simpan AI.';
                    $essay_ai_key_pill = $essay_ai_current_has_api_key ? $essay_ai_current_provider_name . ' siap' : 'Wajib diisi';
                    $essay_ai_key_placeholder = $essay_ai_current_has_api_key
                        ? 'Kosongkan untuk mempertahankan API key ' . $essay_ai_current_provider_name . ' lama'
                        : 'Masukkan API key ' . $essay_ai_current_provider_name;
                    $essay_ai_current_model_label = (string) ($essay_ai_model_options_by_provider[$essay_ai_current_provider][$essay_ai_current_model] ?? $essay_ai_current_model);
                    $essay_ai_current_endpoint_label = (string) ($essay_ai_openai_endpoint_options[$essay_ai_current_openai_endpoint] ?? $essay_ai_current_openai_endpoint);
                    $essay_ai_summary_parts = [
                        $essay_ai_current_provider_name,
                        'Model: ' . ($essay_ai_current_model_label !== '' ? $essay_ai_current_model_label : $essay_ai_current_model),
                    ];
                    if ($essay_ai_current_provider === 'openai') {
                        $essay_ai_summary_parts[] = 'Endpoint: ' . $essay_ai_current_endpoint_label;
                    }
                    $essay_ai_summary_parts[] = $essay_ai_current_has_api_key ? 'API key tersimpan' : 'API key belum diisi';
                    $essay_ai_summary_parts[] = (string) ($essay_ai_status['label'] ?? 'AI Essay');
                    $essay_ai_settings_summary = implode(' - ', array_filter($essay_ai_summary_parts, static function ($item): bool {
                        return trim((string) $item) !== '';
                    }));
                    ?>
                    <form
                        method="post"
                        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                        class="cbt-results-essay-ai-settings"
                        data-cbt-essay-ai-settings
                        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                        data-models-nonce="<?php echo esc_attr(wp_create_nonce('cbt_results_essay_ai_models')); ?>"
                    >
                        <?php wp_nonce_field('cbt_save_essay_ai_settings'); ?>
                        <input type="hidden" name="action" value="cbt_save_essay_ai_settings" />
                        <input type="hidden" name="cbt_essay_exam_id" value="<?php echo (int) $selected_essay_exam_id; ?>" />
                        <input type="hidden" name="cbt_essay_question_id" value="<?php echo (int) $selected_essay_question_id; ?>" />
                        <input type="hidden" name="cbt_essay_kelas" value="<?php echo esc_attr($selected_essay_kelas); ?>" />
                        <input type="hidden" name="cbt_essay_q" value="<?php echo esc_attr($selected_essay_keyword); ?>" />
                        <div class="cbt-results-essay-ai-head cbt-results-essay-ai-settings-head">
                            <div class="cbt-results-essay-ai-settings-title">
                                <h3 style="margin:0;">AI Essay Correction</h3>
                                <p
                                    class="cbt-results-essay-ai-settings-summary"
                                    data-cbt-essay-ai-settings-summary
                                ><?php echo esc_html($essay_ai_settings_summary); ?></p>
                            </div>
                            <div class="cbt-results-essay-ai-settings-meta">
                                <span class="cbt-results-essay-chip <?php echo (($essay_ai_status['status'] ?? '') === 'ready') ? 'is-success' : 'is-warning'; ?>" data-cbt-essay-ai-settings-status-label><?php echo esc_html((string) ($essay_ai_status['label'] ?? 'AI Essay')); ?></span>
                                <button
                                    type="button"
                                    class="button"
                                    data-cbt-essay-ai-settings-toggle
                                    aria-expanded="false"
                                    aria-controls="cbt-results-essay-ai-settings-body"
                                >Buka Pengaturan</button>
                            </div>
                        </div>
                        <div
                            id="cbt-results-essay-ai-settings-body"
                            class="cbt-results-essay-ai-settings-body"
                            data-cbt-essay-ai-settings-body
                            hidden
                        >
                            <p class="cbt-results-muted" style="margin:0;"><?php echo esc_html((string) ($essay_ai_status['message'] ?? 'AI memberi rekomendasi nilai untuk admin, bukan nilai final otomatis.')); ?></p>
                            <div class="cbt-results-essay-ai-config">
                                <div class="cbt-results-essay-ai-main">
                                <div class="cbt-results-essay-ai-provider-row">
                                    <label class="cbt-results-field">
                                        <span>Status AI</span>
                                        <select name="essay_ai_enabled">
                                            <option value="0" <?php selected(empty($essay_ai_settings['enabled'])); ?>>Nonaktif</option>
                                            <option value="1" <?php selected(!empty($essay_ai_settings['enabled'])); ?>>Aktif</option>
                                        </select>
                                    </label>
                                    <label class="cbt-results-field">
                                        <span>Provider AI</span>
                                        <select name="essay_ai_provider" data-cbt-essay-ai-provider>
                                            <?php foreach ($essay_ai_provider_options as $provider_id => $provider_label): ?>
                                                <option value="<?php echo esc_attr((string) $provider_id); ?>" <?php selected($essay_ai_current_provider, (string) $provider_id); ?>>
                                                    <?php echo esc_html((string) $provider_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <label class="cbt-results-field is-wide" data-cbt-essay-ai-openai-endpoint <?php echo $essay_ai_current_provider === 'openai' ? '' : 'hidden'; ?>>
                                    <span>Endpoint OpenAI</span>
                                    <select name="essay_ai_endpoint">
                                        <?php foreach ($essay_ai_openai_endpoint_options as $endpoint_url => $endpoint_label): ?>
                                            <option value="<?php echo esc_attr((string) $endpoint_url); ?>" <?php selected($essay_ai_current_openai_endpoint, (string) $endpoint_url); ?>>
                                                <?php echo esc_html((string) $endpoint_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="cbt-results-essay-ai-endpoint-note" data-cbt-essay-ai-gemini-endpoint <?php echo $essay_ai_current_provider === 'gemini' ? '' : 'hidden'; ?>>
                                    <strong>Endpoint Gemini otomatis</strong>
                                    <code data-cbt-essay-ai-gemini-endpoint-text><?php echo esc_html($essay_ai_gemini_endpoint); ?></code>
                                    <span class="cbt-results-muted">Tidak perlu diisi manual. Sistem memakai model Gemini dan API key yang Anda simpan.</span>
                                </div>
                                <label class="cbt-results-field">
                                    <span>Model</span>
                                    <select name="essay_ai_model" data-cbt-essay-ai-model>
                                        <?php foreach ($essay_ai_model_options_by_provider as $provider_id => $model_options): ?>
                                            <?php foreach ((array) $model_options as $model_id => $model_label): ?>
                                                <option
                                                    value="<?php echo esc_attr((string) $model_id); ?>"
                                                    data-provider="<?php echo esc_attr((string) $provider_id); ?>"
                                                    <?php selected($essay_ai_current_model, (string) $model_id); ?>
                                                >
                                                    <?php echo esc_html((string) $model_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="cbt-results-essay-ai-secret-row" data-cbt-essay-ai-model-refresh-row>
                                    <button type="button" class="button" data-cbt-essay-ai-refresh-models>Refresh Model <?php echo $essay_ai_current_provider === 'openai' ? 'OpenAI' : 'Gemini'; ?></button>
                                    <span
                                        class="cbt-results-muted"
                                        data-cbt-essay-ai-model-status
                                        data-gemini-status="<?php echo esc_attr($essay_ai_gemini_model_status); ?>"
                                        data-openai-status="<?php echo esc_attr($essay_ai_openai_model_status); ?>"
                                    >
                                        <?php echo esc_html($essay_ai_model_status); ?>
                                    </span>
                                </div>
                                <div class="cbt-results-essay-ai-compact-grid">
                                    <label class="cbt-results-field">
                                        <span>Timeout</span>
                                        <input type="number" min="5" max="90" name="essay_ai_timeout" value="<?php echo esc_attr((string) ((int) ($essay_ai_settings['timeout'] ?? 30))); ?>" />
                                    </label>
                                    <label class="cbt-results-field">
                                        <span>Batch</span>
                                        <input type="number" min="1" max="20" name="essay_ai_batch_limit" value="<?php echo esc_attr((string) ((int) ($essay_ai_settings['batch_limit'] ?? 3))); ?>" />
                                    </label>
                                </div>
                                <label class="cbt-results-field is-wide">
                                    <span>API Key</span>
                                    <input
                                        type="password"
                                        name="essay_ai_api_key"
                                        value=""
                                        placeholder="<?php echo esc_attr($essay_ai_key_placeholder); ?>"
                                        autocomplete="off"
                                        data-cbt-essay-ai-api-key-input
                                    />
                                </label>
                                <div
                                    class="cbt-results-essay-ai-key-status <?php echo $essay_ai_current_has_api_key ? 'is-saved' : 'is-missing'; ?>"
                                    data-cbt-essay-ai-key-status
                                    data-gemini-saved="<?php echo $essay_ai_gemini_has_api_key ? '1' : '0'; ?>"
                                    data-openai-saved="<?php echo $essay_ai_openai_has_api_key ? '1' : '0'; ?>"
                                >
                                    <div>
                                        <strong data-cbt-essay-ai-key-status-title><?php echo esc_html($essay_ai_key_title); ?></strong>
                                        <span data-cbt-essay-ai-key-status-message><?php echo esc_html($essay_ai_key_message); ?></span>
                                    </div>
                                    <span class="cbt-results-essay-ai-key-pill" data-cbt-essay-ai-key-status-pill><?php echo esc_html($essay_ai_key_pill); ?></span>
                                </div>
                                <div class="cbt-results-essay-ai-save-row">
                                    <input type="hidden" name="essay_ai_clear_api_key" value="0" data-cbt-essay-ai-clear-key-input />
                                    <button
                                        type="button"
                                        class="button"
                                        data-cbt-essay-ai-clear-key-button
                                        <?php disabled(!$essay_ai_current_has_api_key); ?>
                                    >Hapus API key <?php echo esc_html($essay_ai_current_provider_name); ?></button>
                                    <span class="cbt-results-muted">API key Gemini dan OpenAI disimpan terpisah.</span>
                                    <button type="submit" class="button button-primary button-hero">Simpan AI</button>
                                </div>
                                </div>
                                <aside class="cbt-results-essay-ai-guide" aria-label="Cara mendapatkan API key AI">
                                <div class="cbt-results-essay-ai-guide-card" data-cbt-essay-ai-guide-card="openai" <?php echo $essay_ai_current_provider === 'openai' ? '' : 'hidden'; ?>>
                                    <h4>API key ChatGPT/OpenAI</h4>
                                    <ol>
                                        <li>Buka dashboard OpenAI Platform dan login.</li>
                                        <li>Masuk ke menu API keys, lalu buat secret key baru.</li>
                                        <li>Salin key sekali saja, lalu tempel di field API Key.</li>
                                        <li>Pastikan billing/project aktif agar request tidak ditolak.</li>
                                    </ol>
                                    <a class="button" href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">Buka OpenAI API Keys</a>
                                </div>
                                <div class="cbt-results-essay-ai-guide-card" data-cbt-essay-ai-guide-card="gemini" <?php echo $essay_ai_current_provider === 'gemini' ? '' : 'hidden'; ?>>
                                    <h4>API key Google Gemini</h4>
                                    <ol>
                                        <li>Buka Google AI Studio dan login akun Google.</li>
                                        <li>Pilih menu API key, lalu buat key baru.</li>
                                        <li>Pilih provider Google Gemini di kiri, lalu tempel key tersebut.</li>
                                        <li>Gunakan Gemini 2.5 Flash Lite agar tidak langsung kena 429 di free quota.</li>
                                    </ol>
                                    <a class="button" href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">Buka Gemini API Keys</a>
                                </div>
                                </aside>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($selected_essay_exam_id > 0 && !empty($essay_question_rows) && current_user_can('cbt_grade_essay')): ?>
                    <div
                        class="cbt-results-essay-ai-panel"
                        data-cbt-essay-ai-panel
                        data-scope="exam"
                        data-auto-apply="1"
                        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('cbt_results_essay_ai')); ?>"
                        data-exam-id="<?php echo (int) $selected_essay_exam_id; ?>"
                        data-question-id="0"
                        data-kelas="<?php echo esc_attr($selected_essay_kelas); ?>"
                        data-keyword="<?php echo esc_attr($selected_essay_keyword); ?>"
                    >
                        <div class="cbt-results-essay-ai-head">
                            <div>
                                <h3 style="margin:0;">Koreksi Semua Essay di Exam dengan AI</h3>
                                <p class="cbt-results-muted" style="margin:4px 0 0;" data-cbt-essay-ai-message>Mode santai: AI memproses semua soal essay di exam ini sesuai filter aktif, lalu langsung mengisi jawaban yang belum dinilai.</p>
                            </div>
                            <div class="cbt-results-essay-ai-actions">
                                <span class="cbt-results-essay-chip"><?php echo esc_html(number_format_i18n(count($essay_question_rows))); ?> soal essay</span>
                                <button
                                    type="button"
                                    class="button button-primary"
                                    data-cbt-essay-ai-start
                                    data-retry-mode="all"
                                    <?php disabled(($essay_ai_status['status'] ?? '') !== 'ready'); ?>
                                >Koreksi Exam dengan AI</button>
                                <button
                                    type="button"
                                    class="button"
                                    data-cbt-essay-ai-start
                                    data-retry-mode="failed_only"
                                    <?php disabled(($essay_ai_status['status'] ?? '') !== 'ready'); ?>
                                >Ulangi Gagal Saja</button>
                                <button type="button" class="button" data-cbt-essay-ai-stop hidden>Stop</button>
                            </div>
                        </div>
                        <div class="cbt-results-essay-ai-progress">
                            <div class="cbt-results-essay-ai-progress-track">
                                <div class="cbt-results-essay-ai-progress-bar" data-cbt-essay-ai-progress-bar></div>
                            </div>
                            <p class="cbt-results-muted" style="margin:0;" data-cbt-essay-ai-progress-text>Menunggu job AI exam.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($selected_essay_exam_id <= 0): ?>
                    <?php
                    echo CBT_Admin_UI_Helper::render_empty_state([
                        'title' => 'Pilih exam terlebih dahulu',
                        'message' => 'Pilih exam untuk memuat daftar soal essay yang bisa dikoreksi massal.',
                        'class' => 'cbt-results-empty',
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php elseif (empty($essay_question_rows)): ?>
                    <?php
                    echo CBT_Admin_UI_Helper::render_empty_state([
                        'title' => 'Exam ini belum memiliki soal essay',
                        'message' => 'Pilih exam lain atau tambahkan soal bertipe essay terlebih dahulu.',
                        'class' => 'cbt-results-empty',
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php elseif ($selected_essay_question_id <= 0): ?>
                    <?php
                    echo CBT_Admin_UI_Helper::render_empty_state([
                        'title' => 'Pilih soal essay',
                        'message' => 'Pilih soal untuk review detail per-jawaban, atau gunakan Koreksi Semua Essay di Exam dengan AI untuk mode santai.',
                        'class' => 'cbt-results-empty',
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php elseif (empty($essay_rows)): ?>
                    <?php
                    echo CBT_Admin_UI_Helper::render_empty_state([
                        'title' => 'Belum ada completed attempt',
                        'message' => 'Jawaban essay massal hanya menampilkan attempt yang sudah selesai. Cek kembali filter kelas atau pencarian siswa.',
                        'class' => 'cbt-results-empty',
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php else: ?>
                    <div class="cbt-results-essay-question-card">
                        <div>
                            <h3><?php echo esc_html((string) ($selected_essay_question['label'] ?? 'Soal Essay')); ?></h3>
                            <p class="cbt-results-muted" style="margin:6px 0 0;"><?php echo esc_html((string) ($selected_essay_question['question_preview'] ?? '-')); ?></p>
                        </div>
                        <?php if (!empty($selected_essay_question['rubric_preview'])): ?>
                            <p class="cbt-results-muted" style="margin:0;"><strong>Rubrik:</strong> <?php echo esc_html((string) $selected_essay_question['rubric_preview']); ?></p>
                        <?php endif; ?>
                        <div class="cbt-results-essay-summary">
                            <span class="cbt-results-essay-chip"><?php echo esc_html(number_format_i18n((int) ($essay_bulk_summary['total_rows'] ?? 0))); ?> siswa</span>
                            <span class="cbt-results-essay-chip is-success"><?php echo esc_html(number_format_i18n((int) ($essay_bulk_summary['graded_count'] ?? 0))); ?> sudah dinilai</span>
                            <span class="cbt-results-essay-chip is-warning"><?php echo esc_html(number_format_i18n((int) ($essay_bulk_summary['pending_count'] ?? 0))); ?> belum dinilai</span>
                            <span class="cbt-results-essay-chip is-muted"><?php echo esc_html(number_format_i18n((int) ($essay_bulk_summary['empty_count'] ?? 0))); ?> kosong</span>
                            <span class="cbt-results-essay-chip is-success"><?php echo esc_html(number_format_i18n((int) ($essay_ai_summary['ready_count'] ?? 0))); ?> rekomendasi AI</span>
                            <span class="cbt-results-essay-chip is-warning"><?php echo esc_html(number_format_i18n((int) ($essay_ai_summary['failed_count'] ?? 0))); ?> gagal AI</span>
                            <span class="cbt-results-essay-chip is-warning"><?php echo esc_html(number_format_i18n((int) ($essay_ai_summary['candidate_count'] ?? 0))); ?> kandidat AI</span>
                        </div>
                    </div>

                    <div
                        class="cbt-results-essay-ai-panel"
                        data-cbt-essay-ai-panel
                        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('cbt_results_essay_ai')); ?>"
                        data-exam-id="<?php echo (int) $selected_essay_exam_id; ?>"
                        data-question-id="<?php echo (int) $selected_essay_question_id; ?>"
                        data-kelas="<?php echo esc_attr($selected_essay_kelas); ?>"
                        data-keyword="<?php echo esc_attr($selected_essay_keyword); ?>"
                    >
                        <div class="cbt-results-essay-ai-head">
                            <div>
                                <h3 style="margin:0;">Rekomendasi AI untuk Soal Ini</h3>
                                <p class="cbt-results-muted" style="margin:4px 0 0;" data-cbt-essay-ai-message><?php echo esc_html((string) ($essay_ai_status['message'] ?? 'AI memberi rekomendasi nilai untuk admin.')); ?></p>
                            </div>
                            <div class="cbt-results-essay-ai-actions">
                                <button
                                    type="button"
                                    class="button button-primary"
                                    data-cbt-essay-ai-start
                                    data-retry-mode="all"
                                    <?php disabled(($essay_ai_status['status'] ?? '') !== 'ready' || (int) ($essay_ai_summary['candidate_count'] ?? 0) <= 0); ?>
                                >Buat Rekomendasi AI</button>
                                <button
                                    type="button"
                                    class="button"
                                    data-cbt-essay-ai-start
                                    data-retry-mode="failed_only"
                                    <?php disabled(($essay_ai_status['status'] ?? '') !== 'ready' || (int) ($essay_ai_summary['failed_count'] ?? 0) <= 0); ?>
                                >Ulangi Gagal Saja</button>
                                <button type="button" class="button" data-cbt-essay-ai-stop hidden>Stop</button>
                            </div>
                        </div>
                        <div class="cbt-results-essay-ai-progress">
                            <div class="cbt-results-essay-ai-progress-track">
                                <div class="cbt-results-essay-ai-progress-bar" data-cbt-essay-ai-progress-bar></div>
                            </div>
                            <p class="cbt-results-muted" style="margin:0;" data-cbt-essay-ai-progress-text>Menunggu job AI.</p>
                        </div>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="cbt-results-bulk-essay-form" data-cbt-bulk-essay-form>
                        <?php wp_nonce_field('cbt_bulk_grade_essay'); ?>
                        <input type="hidden" name="action" value="cbt_bulk_grade_essay" />
                        <input type="hidden" name="cbt_essay_exam_id" value="<?php echo (int) $selected_essay_exam_id; ?>" />
                        <input type="hidden" name="cbt_essay_question_id" value="<?php echo (int) $selected_essay_question_id; ?>" />
                        <input type="hidden" name="cbt_essay_kelas" value="<?php echo esc_attr($selected_essay_kelas); ?>" />
                        <input type="hidden" name="cbt_essay_q" value="<?php echo esc_attr($selected_essay_keyword); ?>" />

                        <div class="cbt-results-essay-answer-list">
                            <?php foreach ($essay_rows as $row): ?>
                                <?php
                                $answer_id = (int) ($row['answer_id'] ?? 0);
                                $status_key = sanitize_html_class((string) ($row['status_key'] ?? 'pending'), 'pending');
                                $score_value = number_format((float) ($row['score_awarded'] ?? 0.0), 2, '.', '');
                                $max_points = number_format((float) ($row['max_points'] ?? 0.0), 2, '.', '');
                                ?>
                                <article class="cbt-results-essay-answer-card" data-cbt-essay-row>
                                    <div class="cbt-results-essay-student-meta">
                                        <h3><?php echo esc_html((string) ($row['student_name'] ?? '-')); ?></h3>
                                        <span class="cbt-results-muted"><?php echo esc_html((string) ($row['student_username'] ?? '-')); ?><?php echo !empty($row['student_nisn']) ? ' - ' . esc_html((string) $row['student_nisn']) : ''; ?></span>
                                        <div class="cbt-results-essay-summary">
                                            <span class="cbt-results-essay-chip is-muted"><?php echo esc_html((string) (($row['student_kelas'] ?? '') !== '' ? $row['student_kelas'] : 'Tanpa kelas')); ?></span>
                                            <span class="cbt-results-essay-chip is-muted">Attempt #<?php echo (int) ($row['attempt_id'] ?? 0); ?></span>
                                            <span class="cbt-results-essay-chip <?php echo $status_key === 'graded' ? 'is-success' : ($status_key === 'empty' ? 'is-muted' : 'is-warning'); ?>"><?php echo esc_html((string) ($row['status_label'] ?? 'Belum dinilai')); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <h3>Jawaban Siswa</h3>
                                        <div class="cbt-results-essay-answer-text"><?php echo $row['answer_text'] !== '' ? nl2br(esc_html((string) $row['answer_text'])) : '<span class="cbt-results-muted">Siswa tidak mengisi jawaban untuk soal ini.</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                        <?php
                                        $ai_suggestion = is_array($row['ai_suggestion'] ?? null) ? (array) $row['ai_suggestion'] : [];
                                        $ai_status = sanitize_html_class((string) ($ai_suggestion['status'] ?? 'not_processed'), 'not_processed');
                                        $ai_flags = is_array($ai_suggestion['flags'] ?? null) ? (array) $ai_suggestion['flags'] : [];
                                        $ai_breakdown = is_array($ai_suggestion['rubric_breakdown'] ?? null) ? (array) $ai_suggestion['rubric_breakdown'] : [];
                                        $ai_can_retry = ($essay_ai_status['status'] ?? '') === 'ready'
                                            && $answer_id > 0
                                            && !in_array($ai_status, ['unavailable', 'skipped', 'blocked'], true);
                                        ?>
                                        <div class="cbt-results-essay-ai-box is-<?php echo esc_attr($ai_status); ?>" data-cbt-essay-ai-box>
                                            <p class="cbt-results-essay-ai-title">
                                                <span>AI Essay: <?php echo esc_html((string) ($ai_suggestion['label'] ?? 'Belum diproses')); ?></span>
                                                <span class="cbt-results-essay-chip <?php echo $ai_status === 'success' ? 'is-success' : ($ai_status === 'failed' || $ai_status === 'stale' ? 'is-warning' : 'is-muted'); ?>"><?php echo esc_html((string) ($ai_suggestion['confidence_display'] ?? '-')); ?></span>
                                            </p>
                                            <p class="cbt-results-essay-ai-feedback"><?php echo esc_html((string) ($ai_suggestion['message'] ?? 'Belum ada rekomendasi AI.')); ?></p>
                                            <?php if ($ai_status === 'success' || $ai_status === 'stale'): ?>
                                                <div class="cbt-results-essay-summary">
                                                    <span class="cbt-results-essay-chip is-success">Skor AI: <?php echo esc_html((string) ($ai_suggestion['suggested_score_display'] ?? '-')); ?> / <?php echo esc_html((string) ($row['max_points_display'] ?? '0.00')); ?></span>
                                                    <?php if (!empty($ai_suggestion['needs_manual_review'])): ?>
                                                        <span class="cbt-results-essay-chip is-warning">Perlu review manual</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($ai_suggestion['feedback_internal'])): ?>
                                                <p class="cbt-results-essay-ai-feedback"><strong>Feedback AI:</strong> <?php echo esc_html((string) $ai_suggestion['feedback_internal']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($ai_breakdown)): ?>
                                                <ul class="cbt-results-essay-ai-list">
                                                    <?php foreach ($ai_breakdown as $item): ?>
                                                        <li><?php echo esc_html(is_scalar($item) ? (string) $item : wp_json_encode($item)); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <?php if (!empty($ai_flags)): ?>
                                                <div class="cbt-results-essay-summary">
                                                    <?php foreach ($ai_flags as $flag): ?>
                                                        <span class="cbt-results-essay-chip is-warning"><?php echo esc_html(is_scalar($flag) ? (string) $flag : wp_json_encode($flag)); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="cbt-results-essay-ai-actions">
                                                <?php if (($ai_status === 'success' || $ai_status === 'stale') && isset($ai_suggestion['suggested_score'])): ?>
                                                    <button
                                                        type="button"
                                                        class="button"
                                                        data-cbt-essay-ai-apply
                                                        data-score="<?php echo esc_attr(number_format((float) $ai_suggestion['suggested_score'], 2, '.', '')); ?>"
                                                    >Pakai skor AI</button>
                                                <?php endif; ?>
                                                <?php if ($ai_can_retry): ?>
                                                    <button
                                                        type="button"
                                                        class="button"
                                                        data-cbt-essay-ai-retry
                                                        data-answer-id="<?php echo (int) $answer_id; ?>"
                                                    >Coba ulang AI</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="cbt-results-essay-score-box">
                                        <label for="cbt-essay-score-<?php echo $answer_id > 0 ? $answer_id : (int) ($row['attempt_id'] ?? 0); ?>">Nilai</label>
                                        <div class="cbt-results-essay-score-input-row">
                                            <input
                                                id="cbt-essay-score-<?php echo $answer_id > 0 ? $answer_id : (int) ($row['attempt_id'] ?? 0); ?>"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="<?php echo esc_attr($max_points); ?>"
                                                value="<?php echo esc_attr($score_value); ?>"
                                                data-initial-score="<?php echo esc_attr($score_value); ?>"
                                                data-max-score="<?php echo esc_attr($max_points); ?>"
                                                data-cbt-essay-score-input
                                                <?php if (($ai_status === 'success' || $ai_status === 'stale') && isset($ai_suggestion['suggested_score'])): ?>
                                                    data-ai-score="<?php echo esc_attr(number_format((float) $ai_suggestion['suggested_score'], 2, '.', '')); ?>"
                                                <?php endif; ?>
                                                <?php echo $answer_id > 0 ? 'name="essay_scores[' . (int) $answer_id . ']"' : 'disabled'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            />
                                            <strong>/ <?php echo esc_html((string) ($row['max_points_display'] ?? '0.00')); ?></strong>
                                        </div>
                                        <span class="cbt-results-essay-input-error">Nilai harus berada antara 0 dan <?php echo esc_html((string) ($row['max_points_display'] ?? '0.00')); ?>.</span>
                                        <?php if ($answer_id <= 0): ?>
                                            <span class="cbt-results-muted">Tidak ada record jawaban untuk disimpan.</span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <div class="cbt-results-essay-sticky-bar" data-cbt-essay-sticky-bar>
                            <div class="cbt-results-essay-summary">
                                <span class="cbt-results-essay-chip"><span data-cbt-essay-total><?php echo esc_html(number_format_i18n((int) ($essay_bulk_summary['savable_count'] ?? 0))); ?></span> dapat disimpan</span>
                                <span class="cbt-results-essay-chip is-warning"><span data-cbt-essay-changed>0</span> berubah</span>
                                <span class="cbt-results-essay-chip is-muted"><span data-cbt-essay-invalid>0</span> invalid</span>
                            </div>
                            <div class="cbt-results-essay-sticky-actions">
                                <button type="button" class="button" data-cbt-essay-ai-apply-all>Pakai Nilai AI untuk Semua</button>
                                <button type="submit" class="button button-primary">Simpan Semua Nilai Essay</button>
                                <p class="cbt-results-essay-ai-apply-all-note" data-cbt-essay-ai-apply-all-note hidden></p>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
                </div>
            </section>
            </div>
            <script>
                (function () {
                    var sharedState = window.cbtResultsPageState || (window.cbtResultsPageState = {});

                    function toArray(list) {
                        return Array.prototype.slice.call(list || []);
                    }

                    function parseScore(value) {
                        var normalized = String(value || '').replace(',', '.').trim();
                        if (normalized === '') {
                            return NaN;
                        }
                        return Number(normalized);
                    }

                    function formatQuestionOption(item) {
                        var option = document.createElement('option');
                        option.value = String(item && item.id ? item.id : 0);
                        option.textContent = String(item && item.label ? item.label : 'Soal essay');
                        return option;
                    }

                    function getEssayContent() {
                        return document.querySelector('[data-cbt-essay-content]');
                    }

                    var essayContentRequestSeq = 0;
                    var essayQuestionRequestSeq = 0;

                    function buildEssayFilterUrl(form) {
                        var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                        var params = new URLSearchParams();
                        new FormData(form).forEach(function (value, key) {
                            params.set(key, String(value));
                        });
                        url.search = params.toString();

                        return url;
                    }

                    function initializeEssayDynamicArea() {
                        setupBulkEssayForm();
                        setupEssayAiSettings();
                        setupEssayAiActions();
                    }

                    function refreshEssayContentFromUrl(url, updateHistory) {
                        var currentContent = getEssayContent();
                        if (!currentContent || !window.fetch || !window.DOMParser) {
                            window.location.href = String(url);
                            return Promise.resolve(false);
                        }

                        essayContentRequestSeq += 1;
                        var requestSeq = essayContentRequestSeq;
                        currentContent.classList.add('is-loading');
                        currentContent.setAttribute('data-cbt-essay-request-seq', String(requestSeq));

                        return fetch(String(url), {
                            method: 'GET',
                            credentials: 'same-origin'
                        })
                            .then(function (response) {
                                if (!response.ok) {
                                    throw new Error('Essay refresh gagal.');
                                }
                                return response.text();
                            })
                            .then(function (html) {
                                if (requestSeq !== essayContentRequestSeq) {
                                    return false;
                                }
                                var parsed = new DOMParser().parseFromString(html, 'text/html');
                                var nextContent = parsed.querySelector('[data-cbt-essay-content]');
                                var liveContent = getEssayContent();
                                if (!nextContent || !liveContent) {
                                    throw new Error('Area essay tidak ditemukan.');
                                }

                                liveContent.replaceWith(nextContent);
                                initializeEssayDynamicArea();
                                if (updateHistory !== false && window.history && window.history.replaceState) {
                                    window.history.replaceState({}, '', String(url));
                                }

                                return true;
                            })
                            .catch(function () {
                                if (requestSeq === essayContentRequestSeq) {
                                    window.location.href = String(url);
                                }
                                return false;
                            })
                            .then(function (ok) {
                                var liveContent = getEssayContent();
                                if (requestSeq === essayContentRequestSeq && liveContent) {
                                    liveContent.removeAttribute('data-cbt-essay-request-seq');
                                    liveContent.classList.remove('is-loading');
                                }
                                return ok;
                            });
                    }

                    function setupEssayQuestionFilter() {
                        var form = document.querySelector('[data-cbt-essay-filter]');
                        if (!form || !window.fetch || !window.FormData) {
                            return;
                        }
                        if (form.getAttribute('data-cbt-essay-filter-ready') === '1') {
                            return;
                        }
                        form.setAttribute('data-cbt-essay-filter-ready', '1');

                        var examSelect = form.querySelector('[data-cbt-essay-exam]');
                        var questionSelect = form.querySelector('[data-cbt-essay-question]');
                        var searchInput = form.querySelector('[data-cbt-essay-search]');
                        var resetLink = form.querySelector('[data-cbt-essay-reset-filter]');
                        var searchTimer = null;
                        if (!examSelect || !questionSelect) {
                            return;
                        }

                        function submitFilter() {
                            refreshEssayContentFromUrl(buildEssayFilterUrl(form));
                        }

                        function submitFilterSoon() {
                            window.clearTimeout(searchTimer);
                            searchTimer = window.setTimeout(submitFilter, 450);
                        }

                        examSelect.addEventListener('change', function () {
                            var examId = parseInt(examSelect.value || '0', 10);
                            essayQuestionRequestSeq += 1;
                            var questionRequestSeq = essayQuestionRequestSeq;
                            questionSelect.innerHTML = '';
                            questionSelect.appendChild(new Option(examId > 0 ? 'Memuat soal essay...' : 'Pilih exam dulu', '0'));
                            questionSelect.disabled = examId <= 0;
                            questionSelect.setAttribute('data-selected-question', '0');

                            if (examId <= 0) {
                                submitFilter();
                                return;
                            }

                            var request = new FormData();
                            request.append('action', 'cbt_results_essay_questions');
                            request.append('nonce', form.getAttribute('data-nonce') || '');
                            request.append('exam_id', String(examId));

                            fetch(form.getAttribute('data-ajax-url') || window.ajaxurl || '', {
                                method: 'POST',
                                credentials: 'same-origin',
                                body: request
                            })
                                .then(function (response) {
                                    return response.json();
                                })
                                .then(function (payload) {
                                    if (questionRequestSeq !== essayQuestionRequestSeq || String(examSelect.value || '0') !== String(examId)) {
                                        return;
                                    }
                                    var items = payload && payload.success && payload.data && Array.isArray(payload.data.items)
                                        ? payload.data.items
                                        : [];
                                    questionSelect.innerHTML = '';
                                    questionSelect.appendChild(new Option(items.length ? 'Pilih soal essay' : 'Exam ini belum punya soal essay', '0'));
                                    items.forEach(function (item) {
                                        questionSelect.appendChild(formatQuestionOption(item));
                                    });
                                    questionSelect.disabled = false;
                                    submitFilter();
                                })
                                .catch(function () {
                                    if (questionRequestSeq !== essayQuestionRequestSeq || String(examSelect.value || '0') !== String(examId)) {
                                        return;
                                    }
                                    questionSelect.innerHTML = '';
                                    questionSelect.appendChild(new Option('Gagal memuat soal essay', '0'));
                                    questionSelect.disabled = false;
                                    submitFilter();
                                });
                        });

                        questionSelect.addEventListener('change', submitFilter);
                        Array.prototype.forEach.call(form.querySelectorAll('[data-cbt-essay-auto-filter]'), function (input) {
                            input.addEventListener('change', submitFilter);
                        });
                        if (searchInput) {
                            searchInput.addEventListener('input', submitFilterSoon);
                            searchInput.addEventListener('search', submitFilter);
                            searchInput.addEventListener('keydown', function (event) {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    submitFilter();
                                }
                            });
                        }
                        if (resetLink) {
                            resetLink.addEventListener('click', function (event) {
                                event.preventDefault();
                                essayQuestionRequestSeq += 1;
                                examSelect.value = '0';
                                questionSelect.innerHTML = '';
                                questionSelect.appendChild(new Option('Pilih exam dulu', '0'));
                                questionSelect.disabled = true;
                                questionSelect.setAttribute('data-selected-question', '0');
                                Array.prototype.forEach.call(form.querySelectorAll('[data-cbt-essay-auto-filter]'), function (input) {
                                    input.value = '';
                                });
                                if (searchInput) {
                                    searchInput.value = '';
                                }
                                refreshEssayContentFromUrl(new URL(resetLink.href, window.location.href));
                            });
                        }
                    }

                    function setupBulkEssayForm() {
                        var form = document.querySelector('[data-cbt-bulk-essay-form]');
                        if (!form) {
                            return;
                        }
                        if (form.getAttribute('data-cbt-bulk-essay-ready') === '1') {
                            return;
                        }
                        form.setAttribute('data-cbt-bulk-essay-ready', '1');

                        var inputs = toArray(form.querySelectorAll('[data-cbt-essay-score-input]'));
                        var changedOutput = form.querySelector('[data-cbt-essay-changed]');
                        var invalidOutput = form.querySelector('[data-cbt-essay-invalid]');
                        var applyAllButton = form.querySelector('[data-cbt-essay-ai-apply-all]');
                        var applyAllNote = form.querySelector('[data-cbt-essay-ai-apply-all-note]');

                        function syncState() {
                            var changedCount = 0;
                            var invalidCount = 0;

                            inputs.forEach(function (input) {
                                var row = input.closest('[data-cbt-essay-row]');
                                var currentScore = parseScore(input.value);
                                var initialScore = parseScore(input.getAttribute('data-initial-score'));
                                var maxScore = parseScore(input.getAttribute('data-max-score'));
                                var isInvalid = !Number.isFinite(currentScore) || currentScore < 0 || currentScore > maxScore;
                                var isChanged = !isInvalid && Math.abs(currentScore - initialScore) >= 0.005;

                                if (row) {
                                    row.classList.toggle('is-invalid', isInvalid);
                                    row.classList.toggle('is-changed', isChanged);
                                }
                                if (isInvalid) {
                                    invalidCount += 1;
                                }
                                if (isChanged) {
                                    changedCount += 1;
                                }
                            });

                            if (changedOutput) {
                                changedOutput.textContent = String(changedCount);
                            }
                            if (invalidOutput) {
                                invalidOutput.textContent = String(invalidCount);
                            }

                            return invalidCount;
                        }

                        function countAiApplicableInputs() {
                            return inputs.filter(function (input) {
                                return !input.disabled && String(input.getAttribute('data-ai-score') || '').trim() !== '';
                            }).length;
                        }

                        inputs.forEach(function (input) {
                            input.addEventListener('input', syncState);
                            input.addEventListener('change', syncState);
                        });

                        if (applyAllButton) {
                            applyAllButton.disabled = countAiApplicableInputs() <= 0;
                            applyAllButton.addEventListener('click', function () {
                                var appliedCount = 0;
                                inputs.forEach(function (input) {
                                    var aiScore = String(input.getAttribute('data-ai-score') || '').trim();
                                    if (input.disabled || aiScore === '') {
                                        return;
                                    }
                                    input.value = aiScore;
                                    input.dispatchEvent(new Event('input', { bubbles: true }));
                                    appliedCount += 1;
                                });

                                syncState();
                                if (applyAllNote) {
                                    applyAllNote.hidden = false;
                                    applyAllNote.textContent = appliedCount > 0
                                        ? 'Nilai AI diterapkan ke ' + appliedCount + ' jawaban yang tampil. Klik Simpan Semua Nilai Essay untuk menyimpan.'
                                        : 'Belum ada rekomendasi AI yang bisa diterapkan pada daftar ini.';
                                }
                            });
                        }

                        form.addEventListener('submit', function (event) {
                            var invalidCount = syncState();
                            if (invalidCount <= 0) {
                                return;
                            }

                            event.preventDefault();
                            var firstInvalid = form.querySelector('.cbt-results-essay-answer-card.is-invalid [data-cbt-essay-score-input]');
                            if (firstInvalid) {
                                firstInvalid.focus();
                            }
                        });

                        syncState();
                    }

                    function setupEssayAiActions() {
                        sharedState.essayQuestionStartJob = null;
                        var panels = toArray(document.querySelectorAll('[data-cbt-essay-ai-panel]'));
                        if (!panels.length || !window.fetch || !window.FormData) {
                            return;
                        }

                        panels.forEach(function (panel) {
                            if (panel.getAttribute('data-cbt-essay-ai-panel-ready') === '1') {
                                return;
                            }
                            panel.setAttribute('data-cbt-essay-ai-panel-ready', '1');

                            var ajaxUrl = panel.getAttribute('data-ajax-url') || window.ajaxurl || '';
                            var nonce = panel.getAttribute('data-nonce') || '';
                            var startButtons = toArray(panel.querySelectorAll('[data-cbt-essay-ai-start]'));
                            var stopButton = panel.querySelector('[data-cbt-essay-ai-stop]');
                            var message = panel.querySelector('[data-cbt-essay-ai-message]');
                            var progressBar = panel.querySelector('[data-cbt-essay-ai-progress-bar]');
                            var progressText = panel.querySelector('[data-cbt-essay-ai-progress-text]');
                            var panelScope = panel.getAttribute('data-scope') || 'question';
                            var autoApply = panel.getAttribute('data-auto-apply') === '1';
                            var activeToken = '';
                            var essayAiJobSeq = 0;
                            var essayAiTickTimer = null;
                            var essayAiJobRunning = false;

                            function setMessage(text) {
                                if (message) {
                                    message.textContent = String(text || '');
                                }
                            }

                            function setProgress(payload) {
                                var percent = payload && Number.isFinite(Number(payload.progress_percent))
                                    ? Math.max(0, Math.min(100, Number(payload.progress_percent)))
                                    : 0;
                                if (progressBar) {
                                    progressBar.style.width = percent + '%';
                                }
                                if (progressText) {
                                    progressText.textContent = String(payload && payload.message ? payload.message : 'Memproses rekomendasi AI...') +
                                        ' (' + percent.toFixed(0) + '%)';
                                }
                            }

                            function setStartButtonsDisabled(disabled) {
                                startButtons.forEach(function (button) {
                                    button.disabled = !!disabled;
                                });
                            }

                            function postAction(action, extra) {
                                var request = new FormData();
                                request.append('action', action);
                                request.append('nonce', nonce);
                                Object.keys(extra || {}).forEach(function (key) {
                                    request.append(key, String(extra[key]));
                                });

                                return fetch(ajaxUrl, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    body: request
                                }).then(function (response) {
                                    return response.json().then(function (payload) {
                                        if (!payload || !payload.success) {
                                            var messageText = payload && payload.data && payload.data.message
                                                ? payload.data.message
                                                : 'Request AI gagal.';
                                            throw new Error(messageText);
                                        }
                                        return payload.data || {};
                                    });
                                });
                            }

                            function finishJob(payload, jobSeq) {
                                if (jobSeq !== essayAiJobSeq) {
                                    return;
                                }
                                if (essayAiTickTimer) {
                                    window.clearTimeout(essayAiTickTimer);
                                    essayAiTickTimer = null;
                                }
                                essayAiJobRunning = false;
                                panel.classList.remove('is-running');
                                panel.classList.add('is-complete');
                                setStartButtonsDisabled(false);
                                if (stopButton) {
                                    stopButton.hidden = true;
                                }
                                if (payload) {
                                    setProgress(payload);
                                }
                                window.setTimeout(function () {
                                    if (jobSeq !== essayAiJobSeq) {
                                        return;
                                    }
                                    var filterForm = document.querySelector('[data-cbt-essay-filter]');
                                    if (filterForm) {
                                        refreshEssayContentFromUrl(buildEssayFilterUrl(filterForm), false);
                                        return;
                                    }
                                    window.location.reload();
                                }, autoApply ? 1200 : 700);
                            }

                            function tickJob(jobSeq) {
                                if (!activeToken || jobSeq !== essayAiJobSeq) {
                                    return;
                                }

                                postAction('cbt_results_essay_ai_tick', { token: activeToken })
                                    .then(function (payload) {
                                        if (jobSeq !== essayAiJobSeq) {
                                            return;
                                        }
                                        setProgress(payload);
                                        if (payload.complete) {
                                            finishJob(payload, jobSeq);
                                            return;
                                        }
                                        var retryAfter = payload && Number.isFinite(Number(payload.retry_after_seconds))
                                            ? Number(payload.retry_after_seconds)
                                            : 0;
                                        var nextDelay = retryAfter > 0
                                            ? Math.max(900, Math.min(300000, retryAfter * 1000))
                                            : 900;
                                        essayAiTickTimer = window.setTimeout(function () {
                                            tickJob(jobSeq);
                                        }, nextDelay);
                                    })
                                    .catch(function (error) {
                                        if (jobSeq !== essayAiJobSeq) {
                                            return;
                                        }
                                        essayAiJobRunning = false;
                                        panel.classList.remove('is-running');
                                        setMessage(error && error.message ? error.message : 'AI gagal diproses.');
                                        setStartButtonsDisabled(false);
                                        if (stopButton) {
                                            stopButton.hidden = true;
                                        }
                                    });
                            }

                            function startJob(options) {
                                if (essayAiJobRunning) {
                                    return;
                                }

                                essayAiJobSeq += 1;
                                var jobSeq = essayAiJobSeq;
                                if (essayAiTickTimer) {
                                    window.clearTimeout(essayAiTickTimer);
                                    essayAiTickTimer = null;
                                }
                                var payload = {
                                    cbt_essay_exam_id: panel.getAttribute('data-exam-id') || '0',
                                    cbt_essay_question_id: panel.getAttribute('data-question-id') || '0',
                                    cbt_essay_kelas: panel.getAttribute('data-kelas') || '',
                                    cbt_essay_q: panel.getAttribute('data-keyword') || '',
                                    scope: panelScope,
                                    auto_apply: autoApply ? '1' : '0',
                                    retry_mode: options && options.retryMode === 'failed_only' ? 'failed_only' : 'all'
                                };
                                if (options && options.answerId) {
                                    payload.answer_id = options.answerId;
                                    payload.force = '1';
                                    payload.scope = 'question';
                                    payload.auto_apply = '0';
                                    payload.retry_mode = 'all';
                                }

                                essayAiJobRunning = true;
                                panel.classList.add('is-running');
                                panel.classList.remove('is-complete');
                                setStartButtonsDisabled(true);
                                if (stopButton) {
                                    stopButton.hidden = false;
                                }
                                setMessage(payload.retry_mode === 'failed_only'
                                    ? 'Membuat antrean ulang rekomendasi AI yang gagal...'
                                    : (autoApply ? 'Membuat antrean koreksi AI exam...' : 'Membuat antrean rekomendasi AI...'));
                                setProgress({ progress_percent: 0, message: 'Menyiapkan job AI...' });

                                postAction('cbt_results_essay_ai_start', payload)
                                    .then(function (response) {
                                        if (jobSeq !== essayAiJobSeq) {
                                            return;
                                        }
                                        activeToken = response.token || '';
                                        setProgress(response);
                                        if (response.complete) {
                                            finishJob(response, jobSeq);
                                            return;
                                        }
                                        tickJob(jobSeq);
                                    })
                                    .catch(function (error) {
                                        if (jobSeq !== essayAiJobSeq) {
                                            return;
                                        }
                                        essayAiJobRunning = false;
                                        panel.classList.remove('is-running');
                                        setMessage(error && error.message ? error.message : 'Gagal memulai AI Essay.');
                                        setStartButtonsDisabled(false);
                                        if (stopButton) {
                                            stopButton.hidden = true;
                                        }
                                    });
                            }

                            if (panelScope === 'question') {
                                sharedState.essayQuestionStartJob = startJob;
                            }
                            startButtons.forEach(function (button) {
                                button.addEventListener('click', function () {
                                    startJob({
                                        retryMode: button.getAttribute('data-retry-mode') || 'all'
                                    });
                                });
                            });
                            if (stopButton) {
                                stopButton.addEventListener('click', function () {
                                    if (!activeToken) {
                                        return;
                                    }
                                    essayAiJobSeq += 1;
                                    var stopSeq = essayAiJobSeq;
                                    if (essayAiTickTimer) {
                                        window.clearTimeout(essayAiTickTimer);
                                        essayAiTickTimer = null;
                                    }
                                    stopButton.disabled = true;
                                    postAction('cbt_results_essay_ai_stop', { token: activeToken })
                                        .then(function (payload) {
                                            if (stopSeq !== essayAiJobSeq) {
                                                return;
                                            }
                                            essayAiJobRunning = false;
                                            setProgress(payload);
                                            panel.classList.remove('is-running');
                                            setStartButtonsDisabled(false);
                                            stopButton.hidden = true;
                                            stopButton.disabled = false;
                                        })
                                        .catch(function () {
                                            if (stopSeq === essayAiJobSeq) {
                                                stopButton.disabled = false;
                                                essayAiJobRunning = true;
                                                tickJob(stopSeq);
                                            }
                                        });
                                });
                            }
                        });

                        if (sharedState.essayAiDocumentClickReady) {
                            return;
                        }
                        sharedState.essayAiDocumentClickReady = true;

                        document.addEventListener('click', function (event) {
                            var applyButton = event.target && event.target.closest
                                ? event.target.closest('[data-cbt-essay-ai-apply]')
                                : null;
                            if (applyButton) {
                                var row = applyButton.closest('[data-cbt-essay-row]');
                                var input = row ? row.querySelector('[data-cbt-essay-score-input]') : null;
                                if (input) {
                                    input.value = applyButton.getAttribute('data-score') || input.value;
                                    input.dispatchEvent(new Event('input', { bubbles: true }));
                                    input.focus();
                                }
                                return;
                            }

                            var retryButton = event.target && event.target.closest
                                ? event.target.closest('[data-cbt-essay-ai-retry]')
                                : null;
                            if (retryButton && sharedState.essayQuestionStartJob) {
                                sharedState.essayQuestionStartJob({ answerId: retryButton.getAttribute('data-answer-id') || '0' });
                            }
                        });
                    }

                    function setupEssayAiSettings() {
                        var settingsForm = document.querySelector('[data-cbt-essay-ai-settings]');
                        var providerSelect = document.querySelector('[data-cbt-essay-ai-provider]');
                        var modelSelect = document.querySelector('[data-cbt-essay-ai-model]');
                        if (!providerSelect || !modelSelect) {
                            return;
                        }
                        if (settingsForm && settingsForm.getAttribute('data-cbt-essay-ai-settings-ready') === '1') {
                            return;
                        }
                        if (settingsForm) {
                            settingsForm.setAttribute('data-cbt-essay-ai-settings-ready', '1');
                        }

                        var settingsBody = document.querySelector('[data-cbt-essay-ai-settings-body]');
                        var settingsToggle = document.querySelector('[data-cbt-essay-ai-settings-toggle]');
                        var settingsSummary = document.querySelector('[data-cbt-essay-ai-settings-summary]');
                        var settingsStatusLabel = document.querySelector('[data-cbt-essay-ai-settings-status-label]');
                        var openaiEndpoint = document.querySelector('[data-cbt-essay-ai-openai-endpoint]');
                        var openaiEndpointSelect = settingsForm && settingsForm.elements ? settingsForm.elements.essay_ai_endpoint : null;
                        var geminiEndpoint = document.querySelector('[data-cbt-essay-ai-gemini-endpoint]');
                        var geminiEndpointText = document.querySelector('[data-cbt-essay-ai-gemini-endpoint-text]');
                        var modelRefreshRow = document.querySelector('[data-cbt-essay-ai-model-refresh-row]');
                        var refreshModelsButton = document.querySelector('[data-cbt-essay-ai-refresh-models]');
                        var modelStatus = document.querySelector('[data-cbt-essay-ai-model-status]');
                        var guideCards = document.querySelectorAll('[data-cbt-essay-ai-guide-card]');
                        var apiKeyInput = document.querySelector('[data-cbt-essay-ai-api-key-input]');
                        var keyStatus = document.querySelector('[data-cbt-essay-ai-key-status]');
                        var keyStatusTitle = document.querySelector('[data-cbt-essay-ai-key-status-title]');
                        var keyStatusMessage = document.querySelector('[data-cbt-essay-ai-key-status-message]');
                        var keyStatusPill = document.querySelector('[data-cbt-essay-ai-key-status-pill]');
                        var clearKeyInput = document.querySelector('[data-cbt-essay-ai-clear-key-input]');
                        var clearKeyButton = document.querySelector('[data-cbt-essay-ai-clear-key-button]');
                        var geminiEndpointBase = 'https://generativelanguage.googleapis.com/v1beta/models/';
                        var providerModelStatuses = {
                            gemini: modelStatus ? (modelStatus.getAttribute('data-gemini-status') || '') : '',
                            openai: modelStatus ? (modelStatus.getAttribute('data-openai-status') || '') : ''
                        };

                        function providerDisplayName(provider) {
                            return provider === 'openai' ? 'OpenAI' : 'Gemini';
                        }

                        function selectedOptionText(select) {
                            if (!select || !select.options || select.selectedIndex < 0) {
                                return '';
                            }

                            return String(select.options[select.selectedIndex].textContent || select.value || '').trim();
                        }

                        function providerHasSavedKey(provider) {
                            if (!keyStatus) {
                                return false;
                            }

                            return (keyStatus.getAttribute(provider === 'openai' ? 'data-openai-saved' : 'data-gemini-saved') || '') === '1';
                        }

                        function syncKeyStatus(provider) {
                            var label = providerDisplayName(provider);
                            var saved = providerHasSavedKey(provider);

                            if (apiKeyInput) {
                                apiKeyInput.placeholder = saved
                                    ? 'Kosongkan untuk mempertahankan API key ' + label + ' lama'
                                    : 'Masukkan API key ' + label;
                            }
                            if (keyStatus) {
                                keyStatus.classList.toggle('is-saved', saved);
                                keyStatus.classList.toggle('is-missing', !saved);
                            }
                            if (keyStatusTitle) {
                                keyStatusTitle.textContent = saved ? 'API key ' + label + ' tersimpan' : 'Belum ada API key ' + label;
                            }
                            if (keyStatusMessage) {
                                keyStatusMessage.textContent = saved
                                    ? 'Secret ' + label + ' sudah tersimpan aman. Kosongkan field API Key jika tidak ingin mengganti.'
                                    : 'Masukkan API key ' + label + ', lalu klik Simpan AI.';
                            }
                            if (keyStatusPill) {
                                keyStatusPill.textContent = saved ? label + ' siap' : 'Wajib diisi';
                            }
                            if (clearKeyButton) {
                                clearKeyButton.textContent = 'Hapus API key ' + label;
                                clearKeyButton.disabled = !saved;
                            }
                        }

                        function syncSettingsSummary(provider) {
                            if (!settingsSummary) {
                                return;
                            }

                            var parts = [
                                providerDisplayName(provider),
                                'Model: ' + (selectedOptionText(modelSelect) || modelSelect.value || '-')
                            ];
                            if (provider === 'openai') {
                                parts.push('Endpoint: ' + (selectedOptionText(openaiEndpointSelect) || (openaiEndpointSelect ? openaiEndpointSelect.value : '') || '-'));
                            }
                            parts.push(providerHasSavedKey(provider) ? 'API key tersimpan' : 'API key belum diisi');
                            if (settingsStatusLabel) {
                                parts.push(String(settingsStatusLabel.textContent || '').trim());
                            }

                            settingsSummary.textContent = parts.filter(Boolean).join(' - ');
                        }

                        function setSettingsExpanded(expanded) {
                            if (!settingsBody || !settingsToggle) {
                                return;
                            }

                            settingsBody.hidden = !expanded;
                            settingsToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                            settingsToggle.textContent = expanded ? 'Tutup Pengaturan' : 'Buka Pengaturan';
                        }

                        function syncSettingsUi() {
                            var provider = providerSelect.value || 'gemini';
                            var hasSelectedVisibleOption = false;
                            var firstVisibleOption = null;

                            Array.prototype.forEach.call(modelSelect.options, function (option) {
                                var optionProvider = option.getAttribute('data-provider') || 'gemini';
                                var visible = optionProvider === provider;
                                option.hidden = !visible;
                                option.disabled = !visible;
                                if (visible && !firstVisibleOption) {
                                    firstVisibleOption = option;
                                }
                                if (visible && option.selected) {
                                    hasSelectedVisibleOption = true;
                                }
                            });

                            if (!hasSelectedVisibleOption && firstVisibleOption) {
                                firstVisibleOption.selected = true;
                            }

                            if (openaiEndpoint) {
                                openaiEndpoint.hidden = provider === 'gemini';
                            }
                            if (geminiEndpoint) {
                                geminiEndpoint.hidden = provider !== 'gemini';
                            }
                            Array.prototype.forEach.call(guideCards, function (card) {
                                card.hidden = (card.getAttribute('data-cbt-essay-ai-guide-card') || '') !== provider;
                            });
                            if (modelRefreshRow) {
                                modelRefreshRow.hidden = provider !== 'gemini' && provider !== 'openai';
                            }
                            if (refreshModelsButton) {
                                refreshModelsButton.textContent = provider === 'openai' ? 'Refresh Model OpenAI' : 'Refresh Model Gemini';
                            }
                            if (modelStatus) {
                                modelStatus.textContent = providerModelStatuses[provider] || (provider === 'openai'
                                    ? 'Daftar model OpenAI memakai fallback sampai API key disimpan.'
                                    : 'Daftar model Gemini memakai fallback sampai API key disimpan.');
                            }
                            syncKeyStatus(provider);
                            syncSettingsSummary(provider);
                            if (geminiEndpointText) {
                                geminiEndpointText.textContent = geminiEndpointBase + encodeURIComponent(modelSelect.value || 'gemini-2.5-flash-lite') + ':generateContent';
                            }
                        }

                        function setModelStatus(text) {
                            if (modelStatus) {
                                modelStatus.textContent = String(text || '');
                            }
                        }

                        function hasProviderOption(provider, value) {
                            var found = false;
                            Array.prototype.forEach.call(modelSelect.options, function (option) {
                                var optionProvider = option.getAttribute('data-provider') || 'gemini';
                                if (optionProvider === provider && option.value === value) {
                                    found = true;
                                }
                            });

                            return found;
                        }

                        function replaceProviderOptions(provider, items) {
                            var currentValue = modelSelect.value;
                            Array.prototype.slice.call(modelSelect.options).forEach(function (option) {
                                var optionProvider = option.getAttribute('data-provider') || 'gemini';
                                if (optionProvider === provider) {
                                    modelSelect.removeChild(option);
                                }
                            });

                            (items || []).forEach(function (item) {
                                var id = item && item.id ? String(item.id) : '';
                                if (!id) {
                                    return;
                                }

                                var option = document.createElement('option');
                                option.value = id;
                                option.textContent = item && item.label ? String(item.label) : id;
                                option.setAttribute('data-provider', provider);
                                modelSelect.appendChild(option);
                            });

                            if (currentValue && hasProviderOption(provider, currentValue)) {
                                modelSelect.value = currentValue;
                            }
                            syncSettingsUi();
                        }

                        if (settingsToggle && settingsBody) {
                            setSettingsExpanded(false);
                            settingsToggle.addEventListener('click', function () {
                                setSettingsExpanded(settingsToggle.getAttribute('aria-expanded') !== 'true');
                            });
                        }

                        if (refreshModelsButton && settingsForm) {
                            refreshModelsButton.addEventListener('click', function () {
                                var provider = providerSelect.value || 'gemini';
                                var ajaxUrl = settingsForm.getAttribute('data-ajax-url') || window.ajaxurl || '';
                                if ((provider !== 'gemini' && provider !== 'openai') || !ajaxUrl) {
                                    return;
                                }

                                var request = new FormData();
                                request.append('action', 'cbt_results_essay_ai_models');
                                request.append('nonce', settingsForm.getAttribute('data-models-nonce') || '');
                                request.append('provider', provider);
                                if (settingsForm.elements.essay_ai_api_key && settingsForm.elements.essay_ai_api_key.value) {
                                    request.append('api_key', settingsForm.elements.essay_ai_api_key.value);
                                }
                                if (settingsForm.elements.essay_ai_endpoint && settingsForm.elements.essay_ai_endpoint.value) {
                                    request.append('endpoint', settingsForm.elements.essay_ai_endpoint.value);
                                }

                                refreshModelsButton.disabled = true;
                                setModelStatus(provider === 'openai'
                                    ? 'Memuat daftar model OpenAI...'
                                    : 'Memuat daftar model Gemini dari Google...');

                                fetch(ajaxUrl, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    body: request
                                })
                                    .then(function (response) {
                                        return response.json();
                                    })
                                    .then(function (payload) {
                                        var data = payload && payload.data ? payload.data : {};
                                        if (!payload || !payload.success) {
                                            throw new Error(data.message || (provider === 'openai'
                                                ? 'Gagal memuat model OpenAI.'
                                                : 'Gagal memuat model Gemini.'));
                                        }

                                        replaceProviderOptions(provider, Array.isArray(data.items) ? data.items : []);
                                        providerModelStatuses[provider] = data.message || (provider === 'openai'
                                            ? 'Daftar model OpenAI diperbarui.'
                                            : 'Daftar model Gemini diperbarui.');
                                        setModelStatus(providerModelStatuses[provider]);
                                    })
                                    .catch(function (error) {
                                        setModelStatus(error && error.message ? error.message : (provider === 'openai'
                                            ? 'Gagal memuat model OpenAI.'
                                            : 'Gagal memuat model Gemini.'));
                                    })
                                    .then(function () {
                                        refreshModelsButton.disabled = false;
                                    });
                            });
                        }

                        if (clearKeyButton && clearKeyInput && settingsForm) {
                            clearKeyButton.addEventListener('click', function () {
                                var provider = providerSelect.value || 'gemini';
                                var label = providerDisplayName(provider);
                                if (clearKeyButton.disabled) {
                                    return;
                                }
                                if (!window.confirm('Hapus API key ' + label + ' yang tersimpan?')) {
                                    return;
                                }

                                clearKeyInput.value = '1';
                                if (settingsForm.requestSubmit) {
                                    settingsForm.requestSubmit();
                                } else {
                                    settingsForm.submit();
                                }
                            });
                        }

                        providerSelect.addEventListener('change', syncSettingsUi);
                        modelSelect.addEventListener('change', syncSettingsUi);
                        if (openaiEndpointSelect) {
                            openaiEndpointSelect.addEventListener('change', syncSettingsUi);
                        }
                        syncSettingsUi();
                    }

                    setupEssayQuestionFilter();
                    initializeEssayDynamicArea();
                    document.addEventListener('cbt-results-panels-updated', function (event) {
                        var panelIds = event && event.detail && Array.isArray(event.detail.panelIds)
                            ? event.detail.panelIds
                            : [];
                        if (panelIds.length && panelIds.indexOf('cbt-results-essay-card') === -1) {
                            return;
                        }
                        setupEssayQuestionFilter();
                        initializeEssayDynamicArea();
                    });
                })();
            </script>
            <script>
                (function () {
                    var storageKey = 'cbt_results_active_tab';
                    var validTabs = ['monitoring', 'essay'];

                    function normalizeTabName(value) {
                        return validTabs.indexOf(value) >= 0 ? value : 'monitoring';
                    }

                    function readStoredTab() {
                        try {
                            if (!window.sessionStorage) {
                                return 'monitoring';
                            }
                            return normalizeTabName(window.sessionStorage.getItem(storageKey) || 'monitoring');
                        } catch (error) {
                            return 'monitoring';
                        }
                    }

                    function writeStoredTab(value) {
                        try {
                            if (!window.sessionStorage) {
                                return;
                            }
                            window.sessionStorage.setItem(storageKey, normalizeTabName(value));
                        } catch (error) {
                            // Ignore storage write issues.
                        }
                    }

                    function setActiveTab(nextTabName) {
                        var tabName = normalizeTabName(nextTabName);
                        var nextButton = document.querySelector('[data-cbt-results-tab="' + tabName + '"]');
                        var nextPanel = document.querySelector('[data-cbt-results-tab-panel="' + tabName + '"]');
                        if (!nextButton || !nextPanel) {
                            return;
                        }

                        Array.prototype.forEach.call(document.querySelectorAll('[data-cbt-results-tab]'), function (button) {
                            var isActive = button === nextButton;
                            button.classList.toggle('is-active', isActive);
                            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        });

                        Array.prototype.forEach.call(document.querySelectorAll('[data-cbt-results-tab-panel]'), function (panel) {
                            var isActive = panel === nextPanel;
                            panel.classList.toggle('is-active', isActive);
                        });

                        writeStoredTab(tabName);
                    }

                    document.addEventListener('click', function (event) {
                        var tabButton = event.target && event.target.closest ? event.target.closest('[data-cbt-results-tab]') : null;
                        if (!tabButton) {
                            return;
                        }

                        event.preventDefault();
                        setActiveTab(tabButton.getAttribute('data-cbt-results-tab') || 'monitoring');
                    });

                    function readInitialTab() {
                        var storedTab = readStoredTab();
                        try {
                            if (!window.URLSearchParams) {
                                return storedTab;
                            }
                            var query = new URLSearchParams(window.location.search || '');
                            return normalizeTabName(query.get('cbt_results_tab') || storedTab);
                        } catch (error) {
                            return storedTab;
                        }
                    }

                    setActiveTab(readInitialTab());
                })();
            </script>
            </div>
        </div>
