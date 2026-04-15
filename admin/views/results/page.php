<?php

if (!defined('ABSPATH')) {
    exit;
}
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
                .cbt-results-page {
                    padding-right: 18px;
                    animation: cbtSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                opacity: 0;
            }
            @keyframes cbtSlideUp {
                0% { opacity: 0; transform: translateY(15px); }
                100% { opacity: 1; transform: translateY(0); }
            }
                .cbt-results-shell {
                    max-width: 1320px;
                    display: grid;
                    gap: 20px;
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
                    padding: 26px 28px;
                    border: 1px solid rgba(255, 255, 255, 0.6);
                    border-radius: 24px;
                    background: linear-gradient(145deg, #ffffff 0%, #f0f4f8 100%);

                    
                    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.05), inset 0 2px 0 rgba(255, 255, 255, 0.9);
                }
                .cbt-results-hero::before {
                    content: ""; position: absolute;
                    top: -200px; right: -200px; width: 600px; height: 600px;
                    background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, transparent 70%);
                    border-radius: 50%; pointer-events: none;
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
                    border: 1px solid #d9e3ef;
                    border-radius: 24px;
                    background: #fff;
                    box-shadow: 0 16px 36px rgba(15, 23, 42, 0.05);
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
                    padding: 0;
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
                .cbt-results-essay-form {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .cbt-results-essay-form input[type="number"] {
                    width: 96px;
                    min-height: 40px;
                    padding: 0 12px;
                    border: 1px solid #c9d5e3;
                    border-radius: 12px;
                    background: #f8fbff;
                    box-shadow: none;
                }
                .cbt-results-essay-form input[type="number"]:focus {
                    border-color: #2271b1;
                    background: #fff;
                    box-shadow: 0 0 0 4px rgba(34, 113, 177, 0.12);
                    outline: none;
                }
                .cbt-results-essay-form .button {
                    min-height: 40px;
                    padding: 0 14px;
                    border-radius: 12px;
                }
                @media (max-width: 782px) {
                    .cbt-results-page {
                        padding-right: 10px;
                    }
                    .cbt-results-hero,
                    .cbt-results-card {
                        padding: 20px 18px;
                        border-radius: 20px;
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
                    class="cbt-results-tab-button is-active"
                    role="tab"
                    aria-selected="true"
                    aria-controls="cbt-results-tab-panel-monitoring"
                    data-cbt-results-tab="monitoring"
                >
                    Monitoring Attempts
                </button>
                <button
                    type="button"
                    id="cbt-results-tab-btn-essay"
                    class="cbt-results-tab-button"
                    role="tab"
                    aria-selected="false"
                    aria-controls="cbt-results-tab-panel-essay"
                    data-cbt-results-tab="essay"
                >
                    Essay Manual Scoring
                </button>
            </nav>

            <div
                id="cbt-results-tab-panel-monitoring"
                class="cbt-results-tab-panel is-active"
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
                    <tr><td colspan="7" class="cbt-results-empty-cell">No attempts found.</td></tr>
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
	                            $attempt_answer_detail_row_id = 'cbt-attempt-answer-row-' . $attempt_id;
                                $has_archived_history = !empty($archived_progress_items);
                                $attempt_toggle_open_label = $has_archived_history ? 'Lihat Detail & History' : 'Lihat Detail Jawaban';
                                $attempt_toggle_close_label = $has_archived_history ? 'Tutup Detail & History' : 'Tutup Detail Jawaban';
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
		                                    <?php if (!empty($progress_items) || !empty($archived_progress_items)): ?>
		                                        <button
	                                                type="button"
	                                                class="cbt-attempt-answer-toggle"
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
                                <div class="cbt-attempt-action-stack">
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
                                            <button class="button button-small" type="submit" title="Reset login siswa">Reset Login</button>
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
                                                <button class="button button-secondary button-small" type="submit" title="Tambah waktu attempt">Tambah</button>
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
                                                <button class="button button-secondary button-small" type="submit">Reset Ujian</button>
                                            </form>
                                        <?php endif; ?>
	                                    </div>
	                                </div>
	                            </td>
	                        </tr>
                            <?php if (!empty($progress_items) || !empty($archived_progress_items)): ?>
                                <tr
                                    id="<?php echo esc_attr($attempt_answer_detail_row_id); ?>"
                                    class="cbt-attempt-answer-detail-row"
                                    data-cbt-attempt-answer-row="<?php echo (int) $attempt_id; ?>"
                                    hidden
                                >
                                    <td colspan="7">
                                        <div class="cbt-attempt-answer-detail-card">
	                                            <div class="cbt-attempt-answer-detail-card-head">
	                                                <div>
	                                                    <h4><?php echo esc_html('Detail Jawaban Attempt #' . $attempt_id); ?></h4>
	                                                    <p>Tabel penuh untuk meninjau status, jenis soal, bobot poin, skor yang didapat, serta history soal yang sudah dihapus atau dinonaktifkan.</p>
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
                                                </div>
                                            </div>
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
                class="cbt-results-tab-panel"
                role="tabpanel"
                aria-labelledby="cbt-results-tab-btn-essay"
                data-cbt-results-tab-panel="essay"
            >
            <section id="cbt-results-essay-card" class="cbt-results-card">
                <div class="cbt-results-card-header">
                    <div>
                        <h2>Essay Manual Scoring</h2>
                        <p>Nilai jawaban essay langsung dari panel ini tanpa keluar dari halaman monitoring utama.</p>
                    </div>
                </div>
                <div class="cbt-results-table-shell">
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>Answer ID</th>
                    <th>Student</th>
                    <th>Attempt</th>
                    <th>Exam</th>
                    <th>Question</th>
                    <th>Answer</th>
                    <th>Max Points</th>
                    <th>Score</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$essay_rows): ?>
                    <tr><td colspan="9" class="cbt-results-empty-cell">No essay answers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($essay_rows as $row): ?>
                        <tr>
                            <td><?php echo (int) $row['answer_id']; ?></td>
                            <td><?php echo esc_html($row['display_name']); ?></td>
                            <td><?php echo (int) $row['attempt_id']; ?></td>
                            <td><?php echo esc_html((string) ($row['exam_title'] ?? '-')); ?></td>
                            <td><?php echo esc_html(wp_trim_words((string) $row['question_text'], 10)); ?></td>
                            <td><?php echo esc_html(wp_trim_words((string) $row['answer_text'], 12)); ?></td>
                            <td><?php echo esc_html((string) $row['points']); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-results-essay-form">
                                    <?php wp_nonce_field('cbt_grade_essay'); ?>
                                    <input type="hidden" name="action" value="cbt_grade_essay" />
                                    <input type="hidden" name="answer_id" value="<?php echo (int) $row['answer_id']; ?>" />
                                    <input type="number" step="0.01" min="0" max="<?php echo esc_attr((string) $row['points']); ?>" name="score_awarded" value="<?php echo esc_attr((string) $row['score_awarded']); ?>" />
                            </td>
                            <td>
                                    <button class="button button-primary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
                </div>
            </section>
            </div>
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

                    setActiveTab(readStoredTab());
                })();
            </script>
            </div>
        </div>
