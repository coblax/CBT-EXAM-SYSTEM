<?php

if (!defined('ABSPATH')) {
    exit;
}

$maintenance_tab_urls = isset($maintenance_tab_urls) && is_array($maintenance_tab_urls)
    ? (array) $maintenance_tab_urls
    : [];
$active_tab_markup = isset($active_tab_markup) ? (string) $active_tab_markup : '';
?>
<style>
    .cbt-maintenance-page {
        max-width: 1120px;
    }
    .cbt-maintenance-shell {
        display: grid;
        gap: 18px;
        margin-top: 18px;
    }
    .cbt-maintenance-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 22px;
        padding: 24px 28px;
        border: 1px solid #d7dbe2;
        border-radius: 22px;
        background:
            radial-gradient(circle at top right, rgba(249, 115, 22, 0.10), transparent 34%),
            linear-gradient(135deg, #ffffff 0%, #fbf8f6 100%);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
    }
    .cbt-maintenance-hero-copy {
        max-width: 650px;
    }
    .cbt-maintenance-kicker {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 12px;
        border-radius: 999px;
        background: #fff0e5;
        color: #c2410c;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .cbt-maintenance-hero h1 {
        margin: 12px 0 8px;
        font-size: 30px;
        line-height: 1.15;
    }
    .cbt-maintenance-hero p {
        margin: 0;
        color: #4b5563;
        font-size: 14px;
        line-height: 1.6;
    }
    .cbt-maintenance-live-panel {
        display: grid;
        gap: 12px;
        min-width: 300px;
        padding: 18px;
        border: 1px solid #ece3db;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.94);
    }
    .cbt-maintenance-live-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .cbt-maintenance-live-value {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 64px;
        padding: 0 16px;
        border-radius: 16px;
        background: #111827;
        color: #f8fafc;
        font-size: 28px;
        font-weight: 700;
        line-height: 1;
    }
    .cbt-maintenance-live-meta {
        display: grid;
        gap: 8px;
    }
    .cbt-maintenance-live-meta-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        color: #334155;
        font-size: 13px;
    }
    .cbt-maintenance-live-meta-item strong {
        font-weight: 600;
    }
    .cbt-maintenance-card {
        padding: 24px;
        border: 1px solid #dcdcde;
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
    }
    .cbt-maintenance-banner {
        position: relative;
        padding: 20px 22px;
        border: 1px solid #dbe8f5;
        border-radius: 20px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
    }
    .cbt-maintenance-banner--success {
        border-color: #cde3d2;
        background: linear-gradient(180deg, #ffffff 0%, #f5fcf7 100%);
    }
    .cbt-maintenance-banner--error {
        border-color: #f1c9c9;
        background: linear-gradient(180deg, #ffffff 0%, #fff7f7 100%);
    }
    .cbt-maintenance-banner-top {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }
    .cbt-maintenance-banner-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: #dcfce7;
        color: #166534;
        font-size: 18px;
        font-weight: 800;
        line-height: 1;
        flex: 0 0 auto;
    }
    .cbt-maintenance-banner--error .cbt-maintenance-banner-icon {
        background: #fee2e2;
        color: #b91c1c;
    }
    .cbt-maintenance-banner-copy {
        flex: 1 1 auto;
        min-width: 0;
    }
    .cbt-maintenance-banner-kicker {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0 10px;
        border-radius: 999px;
        background: #e8f7ee;
        color: #166534;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .cbt-maintenance-banner--error .cbt-maintenance-banner-kicker {
        background: #fee2e2;
        color: #b91c1c;
    }
    .cbt-maintenance-banner-copy h2 {
        margin: 10px 0 6px;
        color: #0f172a;
        font-size: 20px;
        line-height: 1.2;
    }
    .cbt-maintenance-banner-copy p {
        margin: 0;
        color: #475569;
        line-height: 1.6;
    }
    .cbt-maintenance-banner-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        margin-left: auto;
        border: 1px solid #d7dbe2;
        border-radius: 999px;
        background: #ffffff;
        color: #64748b;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: border-color 140ms ease, box-shadow 140ms ease, color 140ms ease, transform 140ms ease;
    }
    .cbt-maintenance-banner-close:hover,
    .cbt-maintenance-banner-close:focus {
        border-color: #94a3b8;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        color: #0f172a;
        outline: none;
        transform: translateY(-1px);
    }
    .cbt-maintenance-banner-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-top: 18px;
    }
    .cbt-maintenance-banner-stat {
        padding: 14px 16px;
        border: 1px solid #dbe8f5;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.86);
    }
    .cbt-maintenance-banner--success .cbt-maintenance-banner-stat {
        border-color: #d9ebde;
    }
    .cbt-maintenance-banner-stat span {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .cbt-maintenance-banner-stat strong {
        display: block;
        color: #0f172a;
        font-size: 18px;
        line-height: 1.2;
    }
    .cbt-maintenance-banner-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }
    .cbt-maintenance-banner-meta .cbt-maintenance-inline-code {
        margin-left: 8px;
    }
    .cbt-maintenance-banner-note {
        margin-top: 14px;
        color: #475569;
        line-height: 1.6;
    }
    .cbt-maintenance-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .cbt-maintenance-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 18px;
        border: 1px solid #d7dbe2;
        border-radius: 999px;
        background: #ffffff;
        color: #334155;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        transition: border-color 140ms ease, box-shadow 140ms ease, background-color 140ms ease, color 140ms ease, transform 140ms ease;
    }
    .cbt-maintenance-tab:hover,
    .cbt-maintenance-tab:focus {
        border-color: #94a3b8;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        outline: none;
        transform: translateY(-1px);
    }
    .cbt-maintenance-tab.is-active {
        border-color: #1d4ed8;
        background: linear-gradient(180deg, #eff6ff 0%, #e0f2fe 100%);
        color: #1d4ed8;
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.12);
    }
    .cbt-maintenance-tab-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        margin-left: 10px;
        padding: 0 8px;
        border-radius: 999px;
        background: #eef2ff;
        color: #1e3a8a;
        font-size: 11px;
        font-weight: 700;
    }
    .cbt-maintenance-tab.is-active .cbt-maintenance-tab-badge {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .cbt-maintenance-panel {
        display: none;
        gap: 18px;
    }
    .cbt-maintenance-panel.is-active {
        display: grid;
    }
    .cbt-maintenance-card--unit {
        border-color: #d6e2f0;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 26%),
            linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-unit-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
    }
    .cbt-maintenance-unit-summary-item {
        padding: 16px 18px;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.88);
    }
    .cbt-maintenance-unit-summary-item span {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .cbt-maintenance-unit-summary-item strong {
        display: block;
        color: #0f172a;
        font-size: 18px;
        line-height: 1.3;
    }
    .cbt-maintenance-subtabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }
    .cbt-maintenance-subtab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 0 16px;
        border: 1px solid #d7dbe2;
        border-radius: 999px;
        background: #ffffff;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        transition: border-color 140ms ease, box-shadow 140ms ease, background-color 140ms ease, color 140ms ease, transform 140ms ease;
    }
    .cbt-maintenance-subtab:hover,
    .cbt-maintenance-subtab:focus {
        border-color: #94a3b8;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        outline: none;
        transform: translateY(-1px);
    }
    .cbt-maintenance-subtab.is-active {
        border-color: #2563eb;
        background: linear-gradient(180deg, #eff6ff 0%, #e0f2fe 100%);
        color: #1d4ed8;
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.12);
    }
    .cbt-maintenance-subtab-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 22px;
        margin-left: 10px;
        padding: 0 8px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #334155;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .cbt-maintenance-subtab.is-active .cbt-maintenance-subtab-badge {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .cbt-maintenance-unit-subpanels {
        display: grid;
        gap: 18px;
        margin-top: 18px;
    }
    .cbt-maintenance-unit-subpanel {
        display: none;
        gap: 18px;
    }
    .cbt-maintenance-unit-subpanel.is-active {
        display: grid;
    }
    .cbt-maintenance-unit-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }
    .cbt-maintenance-unit-panel-copy {
        min-width: 0;
    }
    .cbt-maintenance-unit-kicker {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0 10px;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .cbt-maintenance-unit-panel-copy h3 {
        margin: 10px 0 6px;
        font-size: 20px;
        line-height: 1.2;
        color: #0f172a;
    }
    .cbt-maintenance-unit-panel-copy p {
        margin: 0;
        color: #475569;
        line-height: 1.6;
    }
    .cbt-maintenance-unit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .cbt-maintenance-unit-section {
        padding: 18px;
        border: 1px solid #dbe5ef;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    }
    .cbt-maintenance-unit-section--goal {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-unit-section h4 {
        margin: 0 0 12px;
        color: #0f172a;
        font-size: 15px;
        line-height: 1.3;
    }
    .cbt-maintenance-unit-section p {
        margin: 0;
        color: #475569;
        line-height: 1.7;
    }
    .cbt-maintenance-unit-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .cbt-maintenance-unit-list li {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 0;
        border-top: 1px solid #e5edf5;
    }
    .cbt-maintenance-unit-list li:first-child {
        padding-top: 0;
        border-top: 0;
    }
    .cbt-maintenance-unit-list-copy {
        flex: 1 1 auto;
        min-width: 0;
        color: #334155;
        line-height: 1.6;
    }
    .cbt-maintenance-unit-meta {
        display: grid;
        gap: 14px;
    }
    .cbt-maintenance-unit-meta-block strong {
        display: block;
        margin-bottom: 8px;
        color: #0f172a;
        font-size: 13px;
        line-height: 1.3;
    }
    .cbt-maintenance-unit-meta-block ul {
        margin: 0;
        padding-left: 18px;
        color: #475569;
        line-height: 1.65;
    }
    .cbt-maintenance-unit-note {
        margin-top: 16px;
        color: #475569;
        line-height: 1.65;
    }
    .cbt-maintenance-card--danger {
        border-color: #f2c6c6;
        background: linear-gradient(180deg, #fffefe 0%, #fff7f7 100%);
    }
    .cbt-maintenance-card--seed {
        border-color: #c8d9ef;
        background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    }
    .cbt-maintenance-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }
    .cbt-maintenance-card-header h2 {
        margin: 0 0 6px;
        font-size: 18px;
        line-height: 1.2;
    }
    .cbt-maintenance-card-header p {
        margin: 0;
        color: #646970;
        line-height: 1.55;
    }
    .cbt-maintenance-chip {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }
    .cbt-maintenance-chip--idle {
        background: #eef2f7;
        color: #334155;
    }
    .cbt-maintenance-chip--running {
        background: #e8f1ff;
        color: #0f4fa8;
    }
    .cbt-maintenance-chip--planned {
        background: #eff6ff;
        color: #1d4ed8;
    }
    .cbt-maintenance-chip--done {
        background: #e8f7ee;
        color: #166534;
    }
    .cbt-maintenance-chip--danger {
        background: #fee2e2;
        color: #b91c1c;
    }
    .cbt-maintenance-progress-track {
        width: 100%;
        height: 14px;
        border-radius: 999px;
        overflow: hidden;
        background: #f1f5f9;
        border: 1px solid #dbe2ea;
    }
    .cbt-maintenance-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #f97316, #ea580c);
        transition: width .25s ease;
    }
    .cbt-maintenance-progress-fill--seed {
        background: linear-gradient(90deg, #2563eb, #0f766e);
    }
    .cbt-maintenance-progress-activity {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 14px;
        padding: 12px 14px;
        border: 1px solid #dbe8f5;
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fbff 0%, #f1f7ff 100%);
    }
    .cbt-maintenance-progress-activity-label {
        flex: 0 0 auto;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .cbt-maintenance-progress-activity strong {
        color: #0f172a;
        font-size: 13px;
        line-height: 1.55;
    }
    .cbt-maintenance-progress-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-top: 16px;
    }
    .cbt-maintenance-stat {
        padding: 14px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: linear-gradient(180deg, #fdfefe 0%, #f8fafc 100%);
    }
    .cbt-maintenance-stat-label {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .cbt-maintenance-stat strong {
        display: block;
        color: #111827;
        font-size: 18px;
        line-height: 1.2;
    }
    .cbt-maintenance-progress-note {
        margin: 12px 0 0;
        color: #475569;
        line-height: 1.55;
    }
    .cbt-maintenance-alert {
        margin-top: 16px;
        padding: 14px 16px;
        border: 1px solid #f5c2c7;
        border-radius: 16px;
        background: #fff5f5;
        color: #7f1d1d;
        line-height: 1.55;
    }
    .cbt-maintenance-field-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr);
        gap: 18px;
    }
    .cbt-maintenance-field {
        display: grid;
        gap: 8px;
    }
    .cbt-maintenance-field[hidden] {
        display: none !important;
    }
    .cbt-maintenance-field label {
        font-weight: 600;
        color: #111827;
    }
    .cbt-maintenance-select-wrap {
        position: relative;
        width: 100%;
    }
    .cbt-maintenance-select-wrap::after {
        content: '';
        position: absolute;
        top: 50%;
        right: 18px;
        width: 10px;
        height: 10px;
        border-right: 2px solid #64748b;
        border-bottom: 2px solid #64748b;
        transform: translateY(-62%) rotate(45deg);
        pointer-events: none;
        transition: border-color 120ms ease;
    }
    .cbt-maintenance-field input[type="text"],
    .cbt-maintenance-field input[type="number"],
    .cbt-maintenance-field select {
        width: 100%;
        min-height: 46px;
        margin: 0;
        border: 1px solid #e3b8b8;
        border-radius: 12px;
        background: #fffdfd;
        color: #111827;
        padding: 0 13px;
        transition: border-color 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
    }
    .cbt-maintenance-field select {
        display: block;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        max-width: none;
        cursor: pointer;
        padding-right: 52px;
        background-image: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-field input[type="text"]:focus,
    .cbt-maintenance-field input[type="number"]:focus,
    .cbt-maintenance-field select:focus {
        border-color: #ef4444;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
        outline: none;
    }
    .cbt-maintenance-card--seed .cbt-maintenance-field input[type="text"],
    .cbt-maintenance-card--seed .cbt-maintenance-field input[type="number"],
    .cbt-maintenance-card--seed .cbt-maintenance-field select {
        border-color: #c8d9ef;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-card--seed .cbt-maintenance-field input[type="text"]:focus,
    .cbt-maintenance-card--seed .cbt-maintenance-field input[type="number"]:focus,
    .cbt-maintenance-card--seed .cbt-maintenance-field select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .cbt-maintenance-card--seed .cbt-maintenance-select-wrap::after {
        border-color: #2563eb;
    }
    .cbt-maintenance-card--seed .cbt-maintenance-select-wrap:focus-within::after {
        border-color: #1d4ed8;
    }
    .cbt-maintenance-card--load .cbt-maintenance-field input[type="text"],
    .cbt-maintenance-card--load .cbt-maintenance-field input[type="number"],
    .cbt-maintenance-card--load .cbt-maintenance-field select {
        border-color: #d7e3f2;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-card--load .cbt-maintenance-field input[type="text"]:focus,
    .cbt-maintenance-card--load .cbt-maintenance-field input[type="number"]:focus,
    .cbt-maintenance-card--load .cbt-maintenance-field select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .cbt-maintenance-card--load .cbt-maintenance-select-wrap::after {
        border-color: #2563eb;
    }
    .cbt-maintenance-card--load .cbt-maintenance-select-wrap:focus-within::after {
        border-color: #1d4ed8;
    }
    .cbt-maintenance-field .description,
    .cbt-maintenance-reset-copy {
        margin: 0;
        color: #646970;
        line-height: 1.55;
    }
    .cbt-maintenance-field-help {
        margin: 8px 0 0;
        color: #64748b;
        font-size: 12px;
        line-height: 1.6;
    }
    .cbt-maintenance-field-help--compact {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: baseline;
    }
    .cbt-maintenance-field-help--compact strong {
        color: #0f172a;
        font-weight: 700;
    }
    .cbt-maintenance-load-mode-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-height: 46px;
        padding: 0 14px;
        border: 1px solid #d7e3f2;
        border-radius: 12px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        color: #0f172a;
    }
    .cbt-maintenance-load-mode-card strong {
        font-size: 14px;
        font-weight: 700;
    }
    .cbt-maintenance-field-help code {
        font-size: 11px;
    }
    .cbt-maintenance-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
    }
    .cbt-maintenance-summary-item {
        padding: 14px 16px;
        border: 1px solid #d8e5f3;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-summary-item > span {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .cbt-maintenance-summary-item strong {
        display: block;
        color: #111827;
        font-size: 18px;
        line-height: 1.2;
    }
    .cbt-maintenance-summary-note {
        margin: 14px 0 0;
        color: #475569;
        line-height: 1.6;
    }
    .cbt-maintenance-question-breakdown {
        margin-top: 16px;
        padding: 16px 18px;
        border: 1px solid #d8e5f3;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-question-breakdown-title {
        display: block;
        margin-bottom: 10px;
        color: #1e3a8a;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .cbt-maintenance-question-breakdown-copy {
        margin: 0 0 12px;
        color: #475569;
        line-height: 1.6;
    }
    .cbt-maintenance-question-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .cbt-maintenance-question-chip {
        display: inline-flex;
        align-items: center;
        min-height: 30px;
        padding: 0 12px;
        border-radius: 999px;
        background: #eef4ff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
    }
    .cbt-maintenance-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px solid #f1d5d5;
    }
    .cbt-maintenance-actions-copy {
        margin: 0;
        color: #7f1d1d;
        line-height: 1.55;
    }
    .cbt-maintenance-actions .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 48px;
        padding: 0 18px;
        border-radius: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease, background-color 140ms ease, color 140ms ease;
    }
    .cbt-maintenance-actions .button-primary {
        border-color: #c2410c;
        background: linear-gradient(180deg, #f97316 0%, #ea580c 100%);
        color: #ffffff;
        box-shadow: 0 10px 22px rgba(234, 88, 12, 0.22);
    }
    .cbt-maintenance-actions .button-primary:hover,
    .cbt-maintenance-actions .button-primary:focus {
        transform: translateY(-1px);
        border-color: #9a3412;
        background: linear-gradient(180deg, #fb7f24 0%, #dc4f09 100%);
        color: #ffffff;
        box-shadow: 0 14px 28px rgba(234, 88, 12, 0.26);
    }
    .cbt-maintenance-card--seed .cbt-maintenance-actions {
        border-top-color: #dbe8f5;
    }
    .cbt-maintenance-card--seed .cbt-maintenance-actions-copy {
        color: #1e3a8a;
    }
    .cbt-maintenance-card--seed .button-primary {
        border-color: #1d4ed8;
        background: linear-gradient(180deg, #2563eb 0%, #0f766e 100%);
        box-shadow: 0 12px 26px rgba(37, 99, 235, 0.18);
    }
    .cbt-maintenance-card--seed .button-primary:hover,
    .cbt-maintenance-card--seed .button-primary:focus {
        border-color: #1e40af;
        background: linear-gradient(180deg, #3571ef 0%, #0d9488 100%);
        box-shadow: 0 16px 30px rgba(37, 99, 235, 0.22);
    }
    .cbt-maintenance-card--seed .button[disabled] {
        cursor: not-allowed;
        opacity: .72;
        transform: none;
        box-shadow: none;
    }
    .cbt-maintenance-card--load {
        border-color: #d6e3f3;
        background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    }
    .cbt-maintenance-load-section-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 18px;
    }
    .cbt-maintenance-load-section {
        padding: 18px;
        border: 1px solid #dbe5ef;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.9);
    }
    .cbt-maintenance-load-preflight-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px;
    }
    .cbt-maintenance-load-preflight-grid .cbt-maintenance-stat {
        min-width: 0;
        padding: 10px 12px;
        border-radius: 14px;
    }
    .cbt-maintenance-load-preflight-grid .cbt-maintenance-stat-label {
        margin-bottom: 4px;
        font-size: 11px;
    }
    .cbt-maintenance-load-preflight-grid .cbt-maintenance-stat strong {
        font-size: 14px;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }
    .cbt-maintenance-load-job-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 8px;
    }
    .cbt-maintenance-load-job-grid .cbt-maintenance-stat {
        min-width: 0;
        padding: 8px 10px;
        border-radius: 12px;
    }
    .cbt-maintenance-load-job-grid .cbt-maintenance-stat-label {
        margin-bottom: 3px;
        font-size: 10px;
    }
    .cbt-maintenance-load-job-grid .cbt-maintenance-stat strong {
        font-size: 14px;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }
    .cbt-maintenance-field-grid--load {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .cbt-maintenance-field-grid--load > .cbt-maintenance-field {
        align-self: start;
        align-content: start;
    }
    .cbt-maintenance-load-checkbox {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        min-height: 50px;
        padding: 0 14px;
        border: 1px solid #c9d7e6;
        border-radius: 16px;
        background: #f8fbff;
        font-weight: 600;
        cursor: pointer;
    }
    .cbt-maintenance-load-checkbox input[type="checkbox"] {
        margin: 0;
    }
    .cbt-maintenance-load-quick-options {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .cbt-maintenance-load-quick-option {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 0 10px;
        border: 1px solid #c8d9ef;
        border-radius: 999px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        transition: border-color 120ms ease, background-color 120ms ease, color 120ms ease, box-shadow 120ms ease, transform 120ms ease;
    }
    .cbt-maintenance-load-quick-option:hover,
    .cbt-maintenance-load-quick-option:focus {
        border-color: #2563eb;
        background: #eff6ff;
        color: #1d4ed8;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        outline: none;
        transform: translateY(-1px);
    }
    .cbt-maintenance-load-quick-option.is-active {
        border-color: #1d4ed8;
        background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
        color: #ffffff;
        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.18);
    }
    .cbt-maintenance-load-token-state {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
    }
    .cbt-maintenance-load-token-state .cbt-maintenance-inline-code {
        max-width: 100%;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .cbt-maintenance-load-picker {
        margin-top: 14px;
        border: 1px solid #d7e3f0;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        overflow: hidden;
    }
    .cbt-maintenance-load-picker[open] {
        border-color: #93c5fd;
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.08);
    }
    .cbt-maintenance-load-picker > summary {
        list-style: none;
        cursor: pointer;
        padding: 12px 14px;
    }
    .cbt-maintenance-load-picker > summary::-webkit-details-marker {
        display: none;
    }
    .cbt-maintenance-load-picker-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .cbt-maintenance-load-picker-copy {
        display: grid;
        gap: 2px;
        min-width: 0;
    }
    .cbt-maintenance-load-picker-copy strong {
        color: #0f172a;
        font-size: 13px;
    }
    .cbt-maintenance-load-picker-copy span {
        color: #64748b;
        font-size: 11px;
        line-height: 1.45;
    }
    .cbt-maintenance-load-picker-caret {
        flex: 0 0 auto;
        width: 10px;
        height: 10px;
        border-right: 2px solid #2563eb;
        border-bottom: 2px solid #2563eb;
        transform: rotate(45deg);
        transition: transform 140ms ease;
    }
    .cbt-maintenance-load-picker[open] .cbt-maintenance-load-picker-caret {
        transform: rotate(-135deg);
    }
    .cbt-maintenance-load-picker-menu {
        display: grid;
        gap: 8px;
        max-height: 300px;
        padding: 0 14px 14px;
        overflow: auto;
        border-top: 1px solid #e2e8f0;
    }
    .cbt-maintenance-load-picker-option {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid #d7e3f0;
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        cursor: pointer;
        transition: border-color 140ms ease, box-shadow 140ms ease, transform 140ms ease;
    }
    .cbt-maintenance-load-picker-option:hover,
    .cbt-maintenance-load-picker-option:focus-within {
        border-color: #93c5fd;
        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.08);
        transform: translateY(-1px);
    }
    .cbt-maintenance-load-picker-option input[type="checkbox"] {
        margin-top: 2px;
    }
    .cbt-maintenance-load-exam-copy {
        display: grid;
        gap: 3px;
        color: #475569;
        line-height: 1.4;
    }
    .cbt-maintenance-load-exam-copy strong {
        color: #0f172a;
        font-size: 13px;
    }
    .cbt-maintenance-load-selected-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 10px;
        margin-bottom: 14px;
    }
    .cbt-maintenance-load-selected-card {
        padding: 12px 14px;
        border: 1px solid #d7e3f0;
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-load-selected-empty {
        padding: 14px 16px;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        background: #f8fbff;
        color: #64748b;
        line-height: 1.6;
    }
    .cbt-maintenance-load-preview pre,
    .cbt-maintenance-load-job-details pre,
    .cbt-maintenance-load-job-log-box pre {
        margin: 0;
        padding: 14px 16px;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        background: #0f172a;
        color: #e2e8f0;
        font-size: 12px;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-word;
        overflow: auto;
    }
    .cbt-maintenance-load-jobs-wrap {
        display: grid;
        gap: 14px;
    }
    .cbt-maintenance-load-jobs-toolbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 14px;
    }
    .cbt-maintenance-load-jobs-toolbar .cbt-maintenance-field {
        flex: 1 1 auto;
        margin: 0;
    }
    .cbt-maintenance-load-jobs-toolbar .cbt-maintenance-chip {
        flex: 0 0 auto;
    }
    .cbt-maintenance-load-job-list {
        display: grid;
        gap: 14px;
    }
    .cbt-maintenance-load-job-card {
        padding: 18px;
        border: 1px solid #dbe5ef;
        border-radius: 18px;
        background: #ffffff;
        box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
    }
    .cbt-maintenance-load-job-run-meta {
        margin: 8px 0 0;
        color: #475569;
        font-size: 12px;
        line-height: 1.55;
    }
    .cbt-maintenance-load-job-details {
        margin-top: 14px;
    }
    .cbt-maintenance-load-job-details > summary {
        cursor: pointer;
        font-weight: 700;
        color: #1e3a8a;
        margin-bottom: 10px;
    }
    .cbt-maintenance-load-job-log-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }
    .cbt-maintenance-load-job-log-box {
        display: grid;
        gap: 8px;
    }
    .cbt-maintenance-install-guide {
        margin-top: 16px;
        padding: 16px 18px;
        border: 1px solid #dbe5ef;
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cbt-maintenance-install-guide h4 {
        margin: 0 0 8px;
        color: #0f172a;
        font-size: 15px;
    }
    .cbt-maintenance-install-guide p {
        margin: 0;
        color: #475569;
        line-height: 1.6;
    }
    .cbt-maintenance-install-guide pre {
        margin: 14px 0 0;
        padding: 14px 16px;
        border: 1px solid #dbe5ef;
        border-radius: 16px;
        background: #0f172a;
        color: #e2e8f0;
        font-size: 12px;
        line-height: 1.65;
        white-space: pre-wrap;
        word-break: break-word;
        overflow: auto;
    }
    .cbt-maintenance-install-guide a {
        color: #1d4ed8;
        font-weight: 600;
        text-decoration: none;
    }
    .cbt-maintenance-install-guide a:hover,
    .cbt-maintenance-install-guide a:focus {
        text-decoration: underline;
    }
    .cbt-maintenance-load-job-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    .cbt-maintenance-actions--load-primary {
        align-items: center;
    }
    .cbt-maintenance-actions--load-primary .cbt-maintenance-actions-copy {
        flex: 1 1 auto;
        min-width: 0;
        max-width: none;
        overflow-wrap: anywhere;
    }
    .cbt-maintenance-actions--load-primary .cbt-maintenance-load-job-actions {
        flex: 0 0 auto;
        flex-wrap: nowrap;
        gap: 6px;
    }
    .cbt-maintenance-actions--load-primary .button {
        min-height: 44px;
        padding: 0 14px;
        font-size: 13px;
    }
    .cbt-maintenance-actions--load-job {
        align-items: center;
    }
    .cbt-maintenance-actions--load-job .cbt-maintenance-actions-copy {
        flex: 1 1 auto;
        min-width: 0;
        max-width: none;
    }
    .cbt-maintenance-actions--load-job .cbt-maintenance-load-job-actions {
        flex: 0 0 auto;
        flex-wrap: nowrap;
        gap: 6px;
    }
    .cbt-maintenance-actions--load-job .button {
        min-height: 36px;
        padding: 0 10px;
        border-radius: 10px;
        font-size: 11px;
    }
    .cbt-maintenance-load-job-delete .button {
        border-color: #f3b6be;
        background: #fff5f5;
        color: #b91c1c;
    }
    .cbt-maintenance-load-job-delete .button:hover,
    .cbt-maintenance-load-job-delete .button:focus {
        border-color: #ef4444;
        background: #ffe8e8;
        color: #991b1b;
    }
    .cbt-maintenance-actions--load-job .cbt-maintenance-inline-code {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }
    .cbt-maintenance-inline-code {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        background: #eef4ff;
        color: #1e3a8a;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 12px;
        font-weight: 600;
    }
    @media (max-width: 960px) {
        .cbt-maintenance-hero,
        .cbt-maintenance-card-header,
        .cbt-maintenance-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .cbt-maintenance-progress-meta,
        .cbt-maintenance-banner-stats,
        .cbt-maintenance-field-grid,
        .cbt-maintenance-summary-grid,
        .cbt-maintenance-load-preflight-grid,
        .cbt-maintenance-load-jobs-toolbar,
        .cbt-maintenance-load-job-grid,
        .cbt-maintenance-load-selected-grid,
        .cbt-maintenance-load-job-log-grid {
            grid-template-columns: 1fr;
        }
        .cbt-maintenance-load-jobs-toolbar {
            align-items: stretch;
        }
        .cbt-maintenance-live-panel {
            min-width: 0;
        }
        .cbt-maintenance-banner-top {
            flex-direction: column;
        }
        .cbt-maintenance-banner-close {
            margin-left: 0;
        }
        .cbt-maintenance-actions--load-primary .cbt-maintenance-load-job-actions {
            flex-wrap: wrap;
        }
        .cbt-maintenance-actions--load-job .cbt-maintenance-load-job-actions {
            flex-wrap: wrap;
        }
        .cbt-maintenance-unit-panel-head {
            flex-direction: column;
            align-items: stretch;
        }
        .cbt-maintenance-unit-summary-grid,
        .cbt-maintenance-unit-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 1220px) {
        .cbt-maintenance-actions--load-primary {
            align-items: stretch;
        }
        .cbt-maintenance-actions--load-primary .cbt-maintenance-actions-copy {
            flex-basis: 100%;
        }
        .cbt-maintenance-actions--load-primary .cbt-maintenance-load-job-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
    @media (max-width: 782px) {
        .cbt-maintenance-page {
            margin-right: 10px;
        }
        .cbt-maintenance-hero,
        .cbt-maintenance-card {
            padding: 20px;
        }
        .cbt-maintenance-live-value {
            font-size: 24px;
        }
        .cbt-maintenance-tab,
        .cbt-maintenance-subtab {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>
<div class="wrap cbt-maintenance-page">
    <div class="cbt-maintenance-shell">
        <section class="cbt-maintenance-hero">
            <div class="cbt-maintenance-hero-copy">
                <span class="cbt-maintenance-kicker">Maintenance</span>
                <h1>CBT Maintenance</h1>
                <p>Kelola aksi maintenance tingkat sistem untuk reset database CBT dan proses administratif penting lainnya. Semua aksi di halaman ini bersifat administratif dan perlu dijalankan dengan hati-hati.</p>
            </div>
            <aside class="cbt-maintenance-live-panel">
                <span class="cbt-maintenance-live-label">Live Status</span>
                <span class="cbt-maintenance-live-value"><?php echo esc_html($hero_live_value); ?></span>
                <div class="cbt-maintenance-live-meta">
                    <div class="cbt-maintenance-live-meta-item">
                        <span>Reset DB</span>
                        <strong><?php echo esc_html($reset_progress_summary_label); ?></strong>
                    </div>
                    <div class="cbt-maintenance-live-meta-item">
                        <span>Seeder Uji</span>
                        <strong><?php echo esc_html($seed_progress_summary_label); ?></strong>
                    </div>
                    <div class="cbt-maintenance-live-meta-item">
                        <span>Load Test</span>
                        <strong><?php echo esc_html($load_test_running_count > 0 ? $load_test_running_count . ' job aktif' : $load_test_job_count . ' job'); ?></strong>
                    </div>
                    <div class="cbt-maintenance-live-meta-item">
                        <span>Tahap Aktif</span>
                        <strong><?php echo esc_html($hero_stage_preview); ?></strong>
                    </div>
                    <div class="cbt-maintenance-live-meta-item">
                        <span>Preset Terpilih</span>
                        <strong><?php echo esc_html($seed_progress_preset_label); ?></strong>
                    </div>
                </div>
            </aside>
        </section>

        <?php if (is_array($seed_success_notice_summary)): ?>
            <section class="cbt-maintenance-banner cbt-maintenance-banner--success" data-maintenance-banner>
                <div class="cbt-maintenance-banner-top">
                    <span class="cbt-maintenance-banner-icon" aria-hidden="true">OK</span>
                    <div class="cbt-maintenance-banner-copy">
                        <span class="cbt-maintenance-banner-kicker">Seeder Selesai</span>
                        <h2>Dataset Uji Preset <?php echo esc_html((string) ($seed_success_notice_summary['preset'] ?? '')); ?> berhasil dibuat</h2>
                        <p>Dataset baru sudah siap dipakai untuk staging dan simulasi, dengan root question tersimpan di Bank Soal lalu disinkronkan ke exam uji.</p>
                    </div>
                    <button type="button" class="cbt-maintenance-banner-close" data-maintenance-banner-close aria-label="Tutup notifikasi">x</button>
                </div>
                <div class="cbt-maintenance-banner-stats">
                    <div class="cbt-maintenance-banner-stat">
                        <span>Subject</span>
                        <strong><?php echo esc_html(number_format_i18n((int) ($seed_success_notice_summary['subjects'] ?? 0))); ?></strong>
                    </div>
                    <div class="cbt-maintenance-banner-stat">
                        <span>Exam</span>
                        <strong><?php echo esc_html(number_format_i18n((int) ($seed_success_notice_summary['exams'] ?? 0))); ?></strong>
                    </div>
                    <div class="cbt-maintenance-banner-stat">
                        <span>Bank Question</span>
                        <strong><?php echo esc_html(number_format_i18n((int) ($seed_success_notice_summary['bank_questions'] ?? $seed_success_notice_summary['questions'] ?? 0))); ?></strong>
                    </div>
                    <div class="cbt-maintenance-banner-stat">
                        <span>Soal Sync</span>
                        <strong><?php echo esc_html(number_format_i18n((int) ($seed_success_notice_summary['synced_questions'] ?? 0))); ?></strong>
                    </div>
                    <div class="cbt-maintenance-banner-stat">
                        <span>Guru</span>
                        <strong><?php echo esc_html(number_format_i18n((int) ($seed_success_notice_summary['teachers'] ?? 0))); ?></strong>
                    </div>
                    <div class="cbt-maintenance-banner-stat">
                        <span>Siswa</span>
                        <strong><?php echo esc_html(number_format_i18n((int) ($seed_success_notice_summary['students'] ?? 0))); ?></strong>
                    </div>
                </div>
                <div class="cbt-maintenance-banner-meta">
                    <span class="cbt-maintenance-chip cbt-maintenance-chip--done">Password Default: <span class="cbt-maintenance-inline-code"><?php echo esc_html((string) ($seed_success_notice_summary['default_password'] ?? '')); ?></span></span>
                    <span class="cbt-maintenance-chip cbt-maintenance-chip--running">Akun Test: <span class="cbt-maintenance-inline-code"><?php echo esc_html((string) ($seed_success_notice_summary['special_username'] ?? '')); ?></span> / <span class="cbt-maintenance-inline-code"><?php echo esc_html((string) ($seed_success_notice_summary['special_password'] ?? '')); ?></span></span>
                </div>
                <?php if (!empty($seed_success_notice_summary['extra_note'])): ?>
                    <p class="cbt-maintenance-banner-note"><?php echo esc_html((string) $seed_success_notice_summary['extra_note']); ?></p>
                <?php endif; ?>
            </section>
        <?php elseif ($notice): ?>
            <section class="cbt-maintenance-banner cbt-maintenance-banner--success" data-maintenance-banner>
                <div class="cbt-maintenance-banner-top">
                    <span class="cbt-maintenance-banner-icon" aria-hidden="true">OK</span>
                    <div class="cbt-maintenance-banner-copy">
                        <span class="cbt-maintenance-banner-kicker">Berhasil</span>
                        <h2>Proses maintenance berhasil dijalankan</h2>
                        <p><?php echo esc_html($notice); ?></p>
                    </div>
                    <button type="button" class="cbt-maintenance-banner-close" data-maintenance-banner-close aria-label="Tutup notifikasi">x</button>
                </div>
            </section>
        <?php endif; ?>
        <?php if ($error): ?>
            <section class="cbt-maintenance-banner cbt-maintenance-banner--error" data-maintenance-banner>
                <div class="cbt-maintenance-banner-top">
                    <span class="cbt-maintenance-banner-icon" aria-hidden="true">!</span>
                    <div class="cbt-maintenance-banner-copy">
                        <span class="cbt-maintenance-banner-kicker">Perlu Dicek</span>
                        <h2>Proses maintenance belum bisa dilanjutkan</h2>
                        <p><?php echo esc_html($error); ?></p>
                    </div>
                    <button type="button" class="cbt-maintenance-banner-close" data-maintenance-banner-close aria-label="Tutup notifikasi">x</button>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($active_tab_markup !== ''): ?>
            <nav class="cbt-maintenance-tabs" role="tablist" aria-label="Maintenance sections">
                <a
                    href="<?php echo esc_url((string) ($maintenance_tab_urls['reset'] ?? add_query_arg(['page' => 'cbt-maintenance', 'cbt_maintenance_tab' => 'reset'], admin_url('admin.php')))); ?>"
                    class="cbt-maintenance-tab<?php echo $active_maintenance_tab === 'reset' ? ' is-active' : ''; ?>"
                    data-maintenance-tab-link="reset"
                    role="tab"
                    aria-selected="<?php echo $active_maintenance_tab === 'reset' ? 'true' : 'false'; ?>"
                    <?php echo $active_maintenance_tab === 'reset' ? 'aria-current="page"' : ''; ?>
                >
                    Reset Database
                    <span class="cbt-maintenance-tab-badge"><?php echo esc_html($reset_progress_is_running ? 'Aktif' : 'Form'); ?></span>
                </a>
                <a
                    href="<?php echo esc_url((string) ($maintenance_tab_urls['seed'] ?? add_query_arg(['page' => 'cbt-maintenance', 'cbt_maintenance_tab' => 'seed'], admin_url('admin.php')))); ?>"
                    class="cbt-maintenance-tab<?php echo $active_maintenance_tab === 'seed' ? ' is-active' : ''; ?>"
                    data-maintenance-tab-link="seed"
                    role="tab"
                    aria-selected="<?php echo $active_maintenance_tab === 'seed' ? 'true' : 'false'; ?>"
                    <?php echo $active_maintenance_tab === 'seed' ? 'aria-current="page"' : ''; ?>
                >
                    Bulk Test Data
                    <span class="cbt-maintenance-tab-badge"><?php echo esc_html($seed_progress_is_running ? 'Aktif' : 'Form'); ?></span>
                </a>
                <a
                    href="<?php echo esc_url((string) ($maintenance_tab_urls['load'] ?? add_query_arg(['page' => 'cbt-maintenance', 'cbt_maintenance_tab' => 'load'], admin_url('admin.php')))); ?>"
                    class="cbt-maintenance-tab<?php echo $active_maintenance_tab === 'load' ? ' is-active' : ''; ?>"
                    data-maintenance-tab-link="load"
                    role="tab"
                    aria-selected="<?php echo $active_maintenance_tab === 'load' ? 'true' : 'false'; ?>"
                    <?php echo $active_maintenance_tab === 'load' ? 'aria-current="page"' : ''; ?>
                >
                    Load Test
                    <span class="cbt-maintenance-tab-badge"><?php echo esc_html($load_test_running_count > 0 ? $load_test_running_count . ' Run' : 'Jobs'); ?></span>
                </a>
            </nav>

            <div class="cbt-maintenance-panel is-active" data-maintenance-panel="<?php echo esc_attr($active_maintenance_tab); ?>" role="tabpanel">
                <?php echo $active_tab_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

        </div>
    </div>
    <script>
        (function () {
            document.querySelectorAll('[data-maintenance-banner-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const banner = button.closest('[data-maintenance-banner]');
                    if (banner) {
                        banner.remove();
                    }
                });
            });

            const numberFormatter = window.Intl ? new Intl.NumberFormat('id-ID') : null;
            const parseJson = function (value, fallbackValue) {
                try {
                    return JSON.parse(String(value || ''));
                } catch (error) {
                    return fallbackValue;
                }
            };

            const initSeedPresetSummary = function (panel) {
                const seedRoot = panel ? panel.querySelector('[data-seed-panel]') : null;
                if (!seedRoot) {
                    return;
                }

                const presets = parseJson(seedRoot.getAttribute('data-seed-presets'), {});
                const questionTypeLabels = parseJson(seedRoot.getAttribute('data-seed-question-type-labels'), {});
                const examProfileLabels = parseJson(seedRoot.getAttribute('data-seed-exam-profile-labels'), {});
                const select = seedRoot.querySelector('#cbt-seed-preset');
                if (!select) {
                    return;
                }

                const breakdownContainer = seedRoot.querySelector('#cbt-seed-question-breakdown');
                const questionSummaryTextNode = seedRoot.querySelector('[data-seed-question-summary-text]');
                const examProfileBreakdownContainer = seedRoot.querySelector('#cbt-seed-exam-profile-breakdown');
                const examProfileSummaryTextNode = seedRoot.querySelector('[data-seed-exam-profile-summary-text]');
                const updateSummary = function () {
                    const preset = presets[select.value] || presets.small || null;
                    if (!preset) {
                        return;
                    }

                    const keys = ['subjects', 'exams', 'questions', 'students', 'teachers', 'classes', 'rooms'];
                    keys.forEach(function (key) {
                        seedRoot.querySelectorAll('[data-seed-summary="' + key + '"]').forEach(function (node) {
                            node.textContent = String(preset[key] || 0);
                        });
                    });

                    const labelNode = seedRoot.querySelector('[data-seed-summary-label]');
                    if (labelNode) {
                        labelNode.textContent = preset.label || select.value;
                    }

                    if (questionSummaryTextNode) {
                        questionSummaryTextNode.textContent = preset.question_type_summary || '';
                    }
                    if (examProfileSummaryTextNode) {
                        examProfileSummaryTextNode.textContent = preset.exam_profile_summary || '';
                    }

                    if (breakdownContainer) {
                        breakdownContainer.innerHTML = '';
                        const counts = preset.question_type_counts || {};
                        Object.keys(counts).forEach(function (typeKey) {
                            const count = Number(counts[typeKey] || 0);
                            if (!count) {
                                return;
                            }

                            const chip = document.createElement('span');
                            chip.className = 'cbt-maintenance-question-chip';
                            chip.textContent = (questionTypeLabels[typeKey] || typeKey) + ': ' + (numberFormatter ? numberFormatter.format(count) : String(count));
                            breakdownContainer.appendChild(chip);
                        });
                    }

                    if (examProfileBreakdownContainer) {
                        examProfileBreakdownContainer.innerHTML = '';
                        const profileCounts = preset.exam_profile_counts || {};
                        Object.keys(profileCounts).forEach(function (profileKey) {
                            const count = Number(profileCounts[profileKey] || 0);
                            if (!count) {
                                return;
                            }

                            const chip = document.createElement('span');
                            chip.className = 'cbt-maintenance-question-chip';
                            chip.textContent = (examProfileLabels[profileKey] || profileKey) + ': ' + (numberFormatter ? numberFormatter.format(count) : String(count));
                            examProfileBreakdownContainer.appendChild(chip);
                        });
                    }
                };

                select.addEventListener('change', updateSummary);
                updateSummary();
            };

            const loadTestState = {
                pollingTimer: null
            };

            const initLoadTestPanel = function (panel) {
                const loadRoot = panel ? panel.querySelector('[data-load-panel]') : null;
                const form = loadRoot ? loadRoot.querySelector('[data-load-test-form]') : null;
                const jobsWrap = loadRoot ? loadRoot.querySelector('[data-load-jobs-wrap]') : null;
                if (!form && !jobsWrap) {
                    return;
                }

                const loadTestGlobalToken = loadRoot ? String(loadRoot.getAttribute('data-load-global-token') || '') : '';
                const profiles = form ? parseJson(form.getAttribute('data-load-profiles'), {}) : {};
                const scenarios = form ? parseJson(form.getAttribute('data-load-scenarios'), {}) : {};
                const shapes = form ? parseJson(form.getAttribute('data-load-shapes'), {}) : {};
                const exams = form ? parseJson(form.getAttribute('data-load-exams'), []) : [];
                const k6Path = form ? String(form.getAttribute('data-load-k6-path') || 'k6') : 'k6';
                const readyUsers = form ? Number(form.getAttribute('data-load-ready-users') || 0) : 0;
                const presetSelect = form ? form.querySelector('[data-load-profile-preset]') : null;
                const commandPreview = form ? form.querySelector('[data-load-command-preview]') : null;
                const warningChip = form ? form.querySelector('[data-load-user-warning]') : null;
                const durationChip = form ? form.querySelector('[data-load-duration-chip]') : null;
                const concurrencyChip = form ? form.querySelector('[data-load-concurrency-chip]') : null;
                const refreshButton = form ? form.querySelector('[data-load-refresh-jobs]') : null;
                const examPickerLabel = form ? form.querySelector('[data-load-exam-picker-label]') : null;
                const examPickerMeta = form ? form.querySelector('[data-load-exam-picker-meta]') : null;
                const selectedSummary = form ? form.querySelector('[data-load-selected-summary]') : null;
                const questionsField = form ? form.querySelector('[name="questions_per_user"]') : null;
                const questionHelp = form ? form.querySelector('[data-load-question-help]') : null;
                const questionOptionsWrap = form ? form.querySelector('[data-load-question-options]') : null;
                const profileDescriptionNode = form ? form.querySelector('[data-load-profile-description]') : null;
                const shapeLabelNode = form ? form.querySelector('[data-load-shape-label]') : null;
                const shapeDescriptionNode = form ? form.querySelector('[data-load-shape-description]') : null;
                const shapeMetaNode = form ? form.querySelector('[data-load-shape-meta]') : null;
                const scenarioLabelNode = form ? form.querySelector('[data-load-scenario-label]') : null;
                const scenarioDescriptionNode = form ? form.querySelector('[data-load-scenario-description]') : null;
                const scenarioMetaNode = form ? form.querySelector('[data-load-scenario-meta]') : null;
                const iterationsHelpNode = form ? form.querySelector('[data-load-iterations-help]') : null;
                const sessionSpreadHelpNode = form ? form.querySelector('[data-load-session-spread-help]') : null;
                const postSpreadHelpNode = form ? form.querySelector('[data-load-post-spread-help]') : null;
                const stageSummaryNode = form ? form.querySelector('[data-load-stage-summary]') : null;
                const concurrencyLabelNode = form ? form.querySelector('[data-load-concurrency-label]') : null;
                const durationLabelNode = form ? form.querySelector('[data-load-duration-label]') : null;
                const manualTokenField = form ? form.querySelector('[name="manual_exam_token"]') : null;
                const tokenSourceChip = form ? form.querySelector('[data-load-token-source-chip]') : null;
                const tokenValueNode = form ? form.querySelector('[data-load-token-value]') : null;
                const tokenHelpNode = form ? form.querySelector('[data-load-token-help]') : null;
                const runningChip = loadRoot ? loadRoot.querySelector('[data-load-running-chip]') : null;
                const flatFields = form ? Array.prototype.slice.call(form.querySelectorAll('[data-load-flat-field]')) : [];
                const rampingFields = form ? Array.prototype.slice.call(form.querySelectorAll('[data-load-ramping-field]')) : [];
                const iterationsField = form ? form.querySelector('[name="iterations"]') : null;
                const fieldNames = [
                    'load_shape',
                    'vus',
                    'iterations',
                    'peak_vus',
                    'warmup_duration',
                    'ramp_up_duration',
                    'steady_duration',
                    'ramp_down_duration',
                    'ramp_steps',
                    'questions_per_user',
                    'session_start_spread_ms',
                    'post_start_spread_ms',
                    'scenario_key',
                ];

                const getScenario = function (scenarioKey) {
                    const normalizedKey = String(scenarioKey || '').trim();
                    if (normalizedKey && scenarios[normalizedKey]) {
                        return scenarios[normalizedKey];
                    }
                    return scenarios.full_exam_finish_batch || {};
                };

                const getShape = function (shapeKey) {
                    const normalizedKey = String(shapeKey || '').trim();
                    if (normalizedKey && shapes[normalizedKey]) {
                        return shapes[normalizedKey];
                    }
                    return shapes.flat_iterations || {};
                };

                const durationToSeconds = function (value) {
                    const normalized = String(value || '').trim();
                    const match = normalized.match(/^(\d+)([smh])$/);
                    if (!match) {
                        return -1;
                    }
                    const amount = Number(match[1] || 0);
                    const unit = String(match[2] || 's');
                    if (unit === 'h') {
                        return amount * 3600;
                    }
                    if (unit === 'm') {
                        return amount * 60;
                    }
                    return amount;
                };

                const secondsToToken = function (seconds) {
                    return String(Math.max(0, Number(seconds || 0))) + 's';
                };

                const secondsToLabel = function (seconds) {
                    const safeSeconds = Math.max(0, Number(seconds || 0));
                    const hours = Math.floor(safeSeconds / 3600);
                    const minutes = Math.floor((safeSeconds % 3600) / 60);
                    const remainingSeconds = Math.floor(safeSeconds % 60);
                    const parts = [];
                    if (hours > 0) {
                        parts.push(String(hours) + 'j');
                    }
                    if (minutes > 0) {
                        parts.push(String(minutes) + 'm');
                    }
                    if (remainingSeconds > 0 || !parts.length) {
                        parts.push(String(remainingSeconds) + 'd');
                    }
                    return parts.join(' ');
                };

                const normalizeDurationValue = function (value, fallback) {
                    const normalized = String(value || '').trim();
                    if (/^\d+[smh]$/.test(normalized)) {
                        return normalized;
                    }
                    return String(fallback || '0s');
                };

                const compileRampingStages = function (peakVus, warmupDuration, rampUpDuration, steadyDuration, rampDownDuration, rampSteps) {
                    const stages = [];
                    const safePeakVus = Math.max(1, Number(peakVus || 1));
                    const warmupSeconds = durationToSeconds(warmupDuration);
                    if (warmupSeconds > 0) {
                        stages.push({
                            target: Math.max(1, Math.ceil(safePeakVus * 0.15)),
                            duration: secondsToToken(warmupSeconds)
                        });
                    }

                    const safeSteps = Math.max(1, Number(rampSteps || 1));
                    const rampUpSeconds = durationToSeconds(rampUpDuration);
                    if (rampUpSeconds > 0) {
                        const baseSeconds = Math.floor(rampUpSeconds / safeSteps);
                        const remainderSeconds = rampUpSeconds % safeSteps;
                        for (let step = 1; step <= safeSteps; step += 1) {
                            const durationSeconds = baseSeconds + (step <= remainderSeconds ? 1 : 0);
                            if (durationSeconds <= 0) {
                                continue;
                            }
                            stages.push({
                                target: Math.max(1, Math.min(safePeakVus, Math.round((safePeakVus * step) / safeSteps))),
                                duration: secondsToToken(durationSeconds)
                            });
                        }
                    }

                    const steadySeconds = durationToSeconds(steadyDuration);
                    if (steadySeconds > 0) {
                        stages.push({
                            target: safePeakVus,
                            duration: secondsToToken(steadySeconds)
                        });
                    }

                    const rampDownSeconds = durationToSeconds(rampDownDuration);
                    if (rampDownSeconds > 0) {
                        stages.push({
                            target: 0,
                            duration: secondsToToken(rampDownSeconds)
                        });
                    }

                    return stages;
                };

                const getCurrentPreset = function () {
                    const presetKey = String(presetSelect && presetSelect.value ? presetSelect.value : '');
                    return profiles[presetKey] || {};
                };

                const buildProfileState = function (rawState) {
                    const preset = getCurrentPreset();
                    const loadShape = String(preset.load_shape || 'flat_iterations');
                    const vus = Math.max(1, Number(rawState.vus || preset.vus || 50));
                    const iterations = Math.max(1, Number(rawState.iterations || preset.iterations || 1));
                    const peakVus = Math.max(1, Number(rawState.peakVus || preset.peak_vus || vus));
                    const warmupDuration = normalizeDurationValue(rawState.warmupDuration, preset.warmup_duration || '1m');
                    const rampUpDuration = normalizeDurationValue(rawState.rampUpDuration, preset.ramp_up_duration || '2m');
                    const steadyDuration = normalizeDurationValue(rawState.steadyDuration, preset.steady_duration || '5m');
                    const rampDownDuration = normalizeDurationValue(rawState.rampDownDuration, preset.ramp_down_duration || '1m');
                    const rampSteps = Math.max(1, Number(rawState.rampSteps || preset.ramp_steps || 2));
                    const stages = loadShape === 'ramping_vus'
                        ? compileRampingStages(peakVus, warmupDuration, rampUpDuration, steadyDuration, rampDownDuration, rampSteps)
                        : [];
                    const totalStageSeconds = stages.reduce(function (carry, stage) {
                        return carry + Math.max(0, durationToSeconds(stage.duration));
                    }, 0);
                    const maxDuration = loadShape === 'ramping_vus'
                        ? secondsToToken(Math.min(43320, totalStageSeconds + 120))
                        : String(preset.max_duration || '45m');

                    return {
                        loadShape: loadShape,
                        vus: vus,
                        iterations: iterations,
                        peakVus: peakVus,
                        warmupDuration: warmupDuration,
                        rampUpDuration: rampUpDuration,
                        steadyDuration: steadyDuration,
                        rampDownDuration: rampDownDuration,
                        rampSteps: rampSteps,
                        effectiveVus: loadShape === 'ramping_vus' ? peakVus : vus,
                        stageSummary: loadShape === 'ramping_vus'
                            ? 'Ramping: ' + warmupDuration + ' warmup · ' + rampUpDuration + ' ramp-up · ' + steadyDuration + ' steady · ' + rampDownDuration + ' ramp-down'
                            : 'Flat: ' + String(vus) + ' VUs x ' + String(iterations) + ' iteration',
                        estimatedDurationLabel: loadShape === 'ramping_vus'
                            ? secondsToLabel(totalStageSeconds)
                            : String(preset.max_duration || '45m'),
                        maxDuration: maxDuration,
                        compiledStages: stages
                    };
                };

                const setFieldGroupVisibility = function (fields, isVisible) {
                    fields.forEach(function (field) {
                        field.hidden = !isVisible;
                        Array.prototype.slice.call(field.querySelectorAll('input, select, textarea')).forEach(function (input) {
                            input.disabled = !isVisible;
                        });
                    });
                };

                const buildCommand = function (exam, state, profileState) {
                    const parts = [
                        'cd ' + JSON.stringify('<workspace-for-' + String(exam.id || 0) + '>'),
                        'BASE_URL=' + JSON.stringify(state.baseUrl),
                        'EXAM_ID=' + String(exam.id || 0),
                    ];
                    if (state.examToken) {
                        parts.push('EXAM_TOKEN=' + JSON.stringify(state.examToken));
                    }
                    parts.push('LOAD_SHAPE=' + JSON.stringify(profileState.loadShape));
                    if (profileState.loadShape === 'ramping_vus') {
                        parts.push('PEAK_VUS=' + String(profileState.peakVus));
                        parts.push('WARMUP_DURATION=' + JSON.stringify(profileState.warmupDuration));
                        parts.push('RAMP_UP_DURATION=' + JSON.stringify(profileState.rampUpDuration));
                        parts.push('STEADY_DURATION=' + JSON.stringify(profileState.steadyDuration));
                        parts.push('RAMP_DOWN_DURATION=' + JSON.stringify(profileState.rampDownDuration));
                        parts.push('RAMP_STEPS=' + String(profileState.rampSteps));
                    } else {
                        parts.push('VUS=' + String(profileState.vus));
                        parts.push('ITERATIONS=' + String(profileState.iterations));
                    }
                    parts.push('QUESTIONS_PER_USER=' + String(state.questionsPerUser));
                    parts.push('SESSION_START_SPREAD_MS=' + String(state.sessionSpread));
                    parts.push('POST_START_SPREAD_MS=' + String(state.postSpread));
                    parts.push('SCENARIO_KEY=' + JSON.stringify(state.scenarioKey));
                    parts.push('MAX_DURATION=' + JSON.stringify(profileState.maxDuration));
                    parts.push(JSON.stringify(k6Path) + ' run --summary-export summary.json cbt_exam_1000_users.js');
                    return parts.join(' \\\n  ');
                };

                const getState = function () {
                    const baseUrlField = form ? form.querySelector('[name="base_url"]') : null;
                    return {
                        baseUrl: baseUrlField ? String(baseUrlField.value || '').trim() : '',
                        examToken: manualTokenField && String(manualTokenField.value || '').trim() !== ''
                            ? String(manualTokenField.value || '').trim().toUpperCase()
                            : String(loadTestGlobalToken || ''),
                        vus: Number(form && form.querySelector('[name="vus"]') ? form.querySelector('[name="vus"]').value : 0) || 0,
                        iterations: Number(form && form.querySelector('[name="iterations"]') ? form.querySelector('[name="iterations"]').value : 1) || 1,
                        loadShape: String(form && form.querySelector('[name="load_shape"]') ? form.querySelector('[name="load_shape"]').value : 'flat_iterations'),
                        peakVus: Number(form && form.querySelector('[name="peak_vus"]') ? form.querySelector('[name="peak_vus"]').value : 0) || 0,
                        warmupDuration: String(form && form.querySelector('[name="warmup_duration"]') ? form.querySelector('[name="warmup_duration"]').value : ''),
                        rampUpDuration: String(form && form.querySelector('[name="ramp_up_duration"]') ? form.querySelector('[name="ramp_up_duration"]').value : ''),
                        steadyDuration: String(form && form.querySelector('[name="steady_duration"]') ? form.querySelector('[name="steady_duration"]').value : ''),
                        rampDownDuration: String(form && form.querySelector('[name="ramp_down_duration"]') ? form.querySelector('[name="ramp_down_duration"]').value : ''),
                        rampSteps: Number(form && form.querySelector('[name="ramp_steps"]') ? form.querySelector('[name="ramp_steps"]').value : 0) || 0,
                        questionsPerUser: Number(form && form.querySelector('[name="questions_per_user"]') ? form.querySelector('[name="questions_per_user"]').value : 0) || 0,
                        sessionSpread: Number(form && form.querySelector('[name="session_start_spread_ms"]') ? form.querySelector('[name="session_start_spread_ms"]').value : 0) || 0,
                        postSpread: Number(form && form.querySelector('[name="post_start_spread_ms"]') ? form.querySelector('[name="post_start_spread_ms"]').value : 0) || 0,
                        scenarioKey: String(form && form.querySelector('[name="scenario_key"]') ? form.querySelector('[name="scenario_key"]').value : 'full_exam_finish_batch')
                    };
                };

                const getSelectedExams = function () {
                    if (!form) {
                        return [];
                    }
                    const checkedIds = Array.prototype.slice.call(form.querySelectorAll('[data-load-exam-checkbox]:checked')).map(function (input) {
                        return Number(input.value || 0);
                    });
                    return exams.filter(function (exam) {
                        return checkedIds.indexOf(Number(exam.id || 0)) !== -1;
                    });
                };

                const renderSelectedExams = function (selectedExams) {
                    if (!selectedSummary) {
                        return;
                    }

                    selectedSummary.innerHTML = '';
                    if (!selectedExams.length) {
                        const emptyState = document.createElement('div');
                        emptyState.className = 'cbt-maintenance-load-selected-empty';
                        emptyState.textContent = 'Belum ada exam dipilih. Buka dropdown di atas lalu centang exam yang ingin dijalankan untuk load test.';
                        selectedSummary.appendChild(emptyState);
                        return;
                    }

                    selectedExams.forEach(function (exam) {
                        const card = document.createElement('article');
                        card.className = 'cbt-maintenance-load-selected-card';

                        const copy = document.createElement('div');
                        copy.className = 'cbt-maintenance-load-exam-copy';

                        const title = document.createElement('strong');
                        title.textContent = String(exam.title || ('Exam #' + String(exam.id || 0)));
                        copy.appendChild(title);

                        const meta = document.createElement('span');
                        const rawKkm = Number(exam.kkm_percentage);
                        const safeKkm = Number.isFinite(rawKkm) ? rawKkm : 75;
                        meta.textContent = String(exam.subject_name || '') + ' · ' + String(exam.question_count || 0) + ' soal · ' + String(exam.duration_minutes || 0) + ' menit · KKM ' + String(safeKkm) + '%';
                        copy.appendChild(meta);

                        const schedule = document.createElement('span');
                        schedule.textContent = String(exam.schedule_label || 'Tanpa batas jadwal');
                        copy.appendChild(schedule);

                        const target = document.createElement('span');
                        const classes = Array.isArray(exam.target_kelas_list) && exam.target_kelas_list.length
                            ? exam.target_kelas_list.join(', ')
                            : 'Semua kelas';
                        target.textContent = 'Target kelas: ' + classes;
                        copy.appendChild(target);

                        card.appendChild(copy);
                        selectedSummary.appendChild(card);
                    });
                };

                const updateExamPickerSummary = function (selectedExams) {
                    if (examPickerLabel) {
                        if (!selectedExams.length) {
                            examPickerLabel.textContent = 'Belum ada exam dipilih';
                        } else if (selectedExams.length === 1) {
                            examPickerLabel.textContent = String(selectedExams[0].title || '1 exam dipilih');
                        } else {
                            examPickerLabel.textContent = String(selectedExams.length) + ' exam dipilih';
                        }
                    }

                    if (examPickerMeta) {
                        if (!selectedExams.length) {
                            examPickerMeta.textContent = 'Buka daftar exam aktif lalu centang exam yang ingin dijalankan.';
                        } else if (selectedExams.length === 1) {
                            examPickerMeta.textContent = '1 job k6 akan dibuat untuk exam ini.';
                        } else {
                            examPickerMeta.textContent = String(selectedExams.length) + ' job k6 akan dibuat dan dijalankan paralel.';
                        }
                    }
                };

                const updateTokenState = function () {
                    if (!tokenSourceChip && !tokenValueNode && !tokenHelpNode) {
                        return;
                    }

                    const manualToken = manualTokenField ? String(manualTokenField.value || '').trim().toUpperCase() : '';
                    const globalToken = String(loadTestGlobalToken || '').trim().toUpperCase();

                    if (manualToken !== '') {
                        if (tokenSourceChip) {
                            tokenSourceChip.textContent = 'Manual override';
                            tokenSourceChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--running';
                        }
                        if (tokenValueNode) {
                            tokenValueNode.textContent = manualToken;
                        }
                        if (tokenHelpNode) {
                            tokenHelpNode.textContent = 'Token manual ini akan dipakai untuk run sekarang dan mengoverride token global aktif.';
                        }
                        return;
                    }

                    if (globalToken !== '') {
                        if (tokenSourceChip) {
                            tokenSourceChip.textContent = 'Global aktif';
                            tokenSourceChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--done';
                        }
                        if (tokenValueNode) {
                            tokenValueNode.textContent = globalToken;
                        }
                        if (tokenHelpNode) {
                            tokenHelpNode.textContent = 'Token global aktif ini akan dipakai otomatis selama field override tetap kosong.';
                        }
                        return;
                    }

                    if (tokenSourceChip) {
                        tokenSourceChip.textContent = 'Token belum ada';
                        tokenSourceChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--danger';
                    }
                    if (tokenValueNode) {
                        tokenValueNode.textContent = '-';
                    }
                    if (tokenHelpNode) {
                        tokenHelpNode.textContent = 'Belum ada token global aktif. Isi manual token override jika run ini tetap harus memakai token exam tertentu.';
                    }
                };

                const buildQuestionOptionValues = function (minCount, maxCount) {
                    const values = [0];
                    [10, 20, 30, 40, 50].forEach(function (value) {
                        if (value > 0 && value < minCount) {
                            values.push(value);
                        }
                    });
                    if (minCount > 0) {
                        values.push(minCount);
                    }
                    if (maxCount > minCount) {
                        values.push(maxCount);
                    }

                    return values.filter(function (value, index, array) {
                        return array.indexOf(value) === index;
                    }).sort(function (left, right) {
                        return left - right;
                    });
                };

                const formatNumber = function (value) {
                    return numberFormatter ? numberFormatter.format(Number(value || 0)) : String(value || 0);
                };

                const updateScenarioSummary = function (scenarioKey) {
                    const scenario = getScenario(scenarioKey);
                    if (scenarioLabelNode) {
                        scenarioLabelNode.textContent = String(scenario.label || scenarioKey || 'Scenario');
                    }
                    if (scenarioDescriptionNode) {
                        scenarioDescriptionNode.textContent = String(scenario.description || 'Pilih alur load test yang ingin dijalankan.');
                    }
                    if (scenarioMetaNode) {
                        scenarioMetaNode.textContent = String(scenario.endpoint_summary || '');
                    }
                };

                const updateShapeSummary = function (shapeKey, profileState) {
                    const shape = getShape(shapeKey);
                    const isRamping = String(profileState.loadShape || '') === 'ramping_vus';

                    if (shapeLabelNode) {
                        shapeLabelNode.textContent = String(shape.label || shapeKey || 'Load Shape');
                    }
                    if (shapeDescriptionNode) {
                        shapeDescriptionNode.textContent = String(shape.description || 'Pilih bentuk trafik yang ingin dipakai runner.');
                    }
                    if (shapeMetaNode) {
                        shapeMetaNode.textContent = String(shape.endpoint_hint || '');
                    }
                    if (profileDescriptionNode) {
                        const preset = getCurrentPreset();
                        profileDescriptionNode.textContent = String(preset.description || '');
                    }

                    setFieldGroupVisibility(flatFields, !isRamping);
                    setFieldGroupVisibility(rampingFields, isRamping);

                    if (iterationsField) {
                        iterationsField.disabled = isRamping;
                    }
                    if (iterationsHelpNode) {
                        iterationsHelpNode.innerHTML = isRamping
                            ? 'Mode <code>ramping_vus</code> mengabaikan field <code>iterations</code>. Durasi run dikendalikan oleh warmup, ramp-up, steady, dan ramp-down.'
                            : 'Berapa kali satu skenario ujian dijalankan per virtual user. Nilai lebih tinggi berarti total request ikut bertambah.';
                    }
                    if (sessionSpreadHelpNode) {
                        sessionSpreadHelpNode.innerHTML = isRamping
                            ? 'Pada mode ramping, field ini hanya fine-tuning tambahan di atas stage ramping. Gunakan untuk menyebar login/start attempt lebih halus.'
                            : 'Jeda penyebaran saat mulai sesi user, supaya login dan start attempt tidak menumpuk di milidetik yang sama.';
                    }
                    if (postSpreadHelpNode) {
                        postSpreadHelpNode.innerHTML = isRamping
                            ? 'Pada mode ramping, field ini hanya fine-tuning tambahan setelah <code>start_attempt</code>; bentuk trafik utama tetap berasal dari stages.'
                            : 'Jeda tambahan setelah <code>start_attempt</code> berhasil, sebelum runner mulai request daftar soal exam.';
                    }
                    if (stageSummaryNode) {
                        stageSummaryNode.textContent = String(profileState.stageSummary || '-');
                    }
                    if (concurrencyLabelNode) {
                        concurrencyLabelNode.textContent = String(profileState.effectiveVus || 0) + ' user';
                    }
                    if (durationLabelNode) {
                        durationLabelNode.textContent = String(profileState.estimatedDurationLabel || '-');
                    }
                    if (concurrencyChip) {
                        concurrencyChip.textContent = String(shape.label || 'Load') + ' ' + String(profileState.effectiveVus || 0);
                    }
                    if (durationChip) {
                        durationChip.textContent = String(profileState.estimatedDurationLabel || '-');
                    }
                };

                const updateQuestionFieldMeta = function (selectedExams, scenarioKey) {
                    if (!questionsField && !questionHelp && !questionOptionsWrap) {
                        return;
                    }

                    const scenario = getScenario(scenarioKey);
                    const readsQuestions = Number(scenario.reads_questions || 0) === 1;

                    const counts = selectedExams.map(function (exam) {
                        return Number(exam.question_count || 0) || 0;
                    }).filter(function (count) {
                        return count > 0;
                    });

                    if (questionOptionsWrap) {
                        questionOptionsWrap.innerHTML = '';
                    }

                    if (!readsQuestions) {
                        if (questionHelp) {
                            questionHelp.innerHTML = 'Scenario ini tidak membaca daftar soal, jadi field <code>Questions per user</code> diabaikan untuk run ini.';
                        }
                        if (questionsField) {
                            questionsField.setAttribute('max', '500');
                        }
                        return;
                    }

                    if (!counts.length) {
                        if (questionHelp) {
                            questionHelp.innerHTML = '<code>0</code> berarti semua soal exam akan dipakai. Pilih exam dulu untuk melihat saran jumlah soal berdasarkan exam yang dipilih.';
                        }
                        if (questionsField) {
                            questionsField.setAttribute('max', '500');
                        }
                        return;
                    }

                    const minCount = Math.min.apply(null, counts);
                    const maxCount = Math.max.apply(null, counts);
                    const sameCount = minCount === maxCount;
                    const currentValue = questionsField ? (Number(questionsField.value || 0) || 0) : 0;

                    if (questionsField) {
                        questionsField.setAttribute('max', String(Math.min(500, maxCount)));
                    }

                    if (questionHelp) {
                        if (sameCount) {
                            questionHelp.innerHTML = 'Exam terpilih punya <code>' + formatNumber(minCount) + '</code> soal. Gunakan <code>0</code> jika ingin memakai semua soal, atau pilih angka cepat di bawah.';
                        } else {
                            questionHelp.innerHTML = 'Exam terpilih punya <code>' + formatNumber(minCount) + '</code> sampai <code>' + formatNumber(maxCount) + '</code> soal. Nilai <code>' + formatNumber(minCount) + '</code> aman untuk semua exam, sedangkan <code>0</code> berarti semua soal tiap exam dipakai.';
                        }
                    }

                    if (!questionOptionsWrap || !questionsField) {
                        return;
                    }

                    buildQuestionOptionValues(minCount, maxCount).forEach(function (value) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'cbt-maintenance-load-quick-option' + (currentValue === value ? ' is-active' : '');
                        button.setAttribute('data-load-question-option', String(value));

                        if (value === 0) {
                            button.textContent = sameCount ? 'Semua (' + formatNumber(minCount) + ')' : 'Semua per exam';
                        } else if (!sameCount && value === minCount) {
                            button.textContent = formatNumber(value) + ' aman semua';
                        } else if (!sameCount && value === maxCount) {
                            button.textContent = formatNumber(value) + ' maks';
                        } else {
                            button.textContent = formatNumber(value);
                        }

                        button.addEventListener('click', function () {
                            questionsField.value = String(value);
                            updateCommandPreview();
                        });
                        questionOptionsWrap.appendChild(button);
                    });
                };

                const applyPreset = function () {
                    if (!form || !presetSelect) {
                        return;
                    }
                    const presetKey = String(presetSelect.value || '');
                    const preset = profiles[presetKey] || null;
                    if (!preset) {
                        return;
                    }
                    fieldNames.forEach(function (fieldName) {
                        const field = form.querySelector('[name="' + fieldName + '"]');
                        if (field && preset[fieldName] !== undefined) {
                            field.value = String(preset[fieldName]);
                        }
                    });
                };

                const updateCommandPreview = function () {
                    if (!commandPreview) {
                        return;
                    }
                    const selectedExams = getSelectedExams();
                    updateExamPickerSummary(selectedExams);
                    renderSelectedExams(selectedExams);
                    const state = getState();
                    const profileState = buildProfileState(state);
                    updateScenarioSummary(state.scenarioKey);
                    updateShapeSummary(state.loadShape, profileState);
                    updateQuestionFieldMeta(selectedExams, state.scenarioKey);
                    updateTokenState();
                    if (warningChip) {
                        const needsReuse = readyUsers > 0 && profileState.effectiveVus > readyUsers;
                        warningChip.textContent = needsReuse ? 'User akan di-reuse' : 'User cukup';
                        warningChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--' + (needsReuse ? 'danger' : 'idle');
                    }
                    if (!selectedExams.length) {
                        commandPreview.textContent = 'Belum ada exam dipilih.';
                        return;
                    }

                    const blocks = selectedExams.map(function (exam) {
                        return '# ' + String(exam.title || ('Exam #' + String(exam.id || 0))) + '\n' + buildCommand(exam, state, profileState);
                    });
                    commandPreview.textContent = blocks.join('\n\n');
                };

                const initLoadJobSelector = function (preferredJobId) {
                    if (!jobsWrap) {
                        return;
                    }

                    const selector = jobsWrap.querySelector('[data-load-job-selector]');
                    const cards = Array.prototype.slice.call(jobsWrap.querySelectorAll('[data-load-job-card]'));
                    if (!selector || !cards.length) {
                        return;
                    }

                    const availableIds = cards.map(function (card) {
                        return String(card.getAttribute('data-load-job-id') || '');
                    });
                    const applySelection = function (jobId) {
                        let resolvedId = String(jobId || '').trim();
                        if (availableIds.indexOf(resolvedId) === -1) {
                            resolvedId = availableIds.length ? availableIds[0] : '';
                        }
                        selector.value = resolvedId;
                        cards.forEach(function (card) {
                            const active = String(card.getAttribute('data-load-job-id') || '') === resolvedId;
                            card.hidden = !active;
                        });
                    };

                    selector.addEventListener('change', function () {
                        applySelection(String(selector.value || ''));
                    });

                    applySelection(String(preferredJobId || selector.value || ''));
                };

                const schedulePolling = function (runningCount) {
                    if (loadTestState.pollingTimer) {
                        window.clearTimeout(loadTestState.pollingTimer);
                        loadTestState.pollingTimer = null;
                    }
                    if (Number(runningCount || 0) <= 0 || !jobsWrap) {
                        return;
                    }
                    loadTestState.pollingTimer = window.setTimeout(function () {
                        refreshJobs();
                    }, 5000);
                };

                const refreshJobs = function () {
                    if (!jobsWrap) {
                        return;
                    }
                    const ajaxUrl = String(jobsWrap.getAttribute('data-load-jobs-ajax-url') || '');
                    const ajaxNonce = String(jobsWrap.getAttribute('data-load-jobs-ajax-nonce') || '');
                    if (ajaxUrl === '' || ajaxNonce === '' || typeof window.fetch !== 'function') {
                        window.location.reload();
                        return;
                    }

                    const currentJobSelector = jobsWrap.querySelector('[data-load-job-selector]');
                    const currentJobId = currentJobSelector ? String(currentJobSelector.value || '') : '';
                    const url = new URL(ajaxUrl, window.location.origin);
                    url.searchParams.set('nonce', ajaxNonce);

                    window.fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json'
                        }
                    }).then(function (response) {
                        return response.json();
                    }).then(function (payload) {
                        if (!payload || !payload.success || !payload.data) {
                            return;
                        }
                        jobsWrap.innerHTML = String(payload.data.html || '');
                        jobsWrap.setAttribute('data-load-running-count', String(payload.data.running_count || 0));
                        initLoadJobSelector(currentJobId);
                        if (runningChip) {
                            runningChip.textContent = Number(payload.data.running_count || 0) > 0
                                ? String(payload.data.running_count || 0) + ' running'
                                : String(payload.data.job_count || 0) + ' total';
                            runningChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--' + (Number(payload.data.running_count || 0) > 0 ? 'running' : 'idle');
                        }
                        schedulePolling(Number(payload.data.running_count || 0));
                    }).catch(function () {
                        schedulePolling(0);
                    });
                };

                if (presetSelect) {
                    presetSelect.addEventListener('change', function () {
                        applyPreset();
                        updateCommandPreview();
                    });
                }
                if (form) {
                    Array.prototype.slice.call(form.querySelectorAll('input, select')).forEach(function (field) {
                        field.addEventListener('change', updateCommandPreview);
                        field.addEventListener('input', updateCommandPreview);
                    });
                }
                if (form) {
                    form.querySelectorAll('[data-load-exam-checkbox]').forEach(function (checkbox) {
                        checkbox.addEventListener('change', updateCommandPreview);
                    });
                }
                if (refreshButton) {
                    refreshButton.addEventListener('click', function () {
                        refreshJobs();
                    });
                }
                updateCommandPreview();
                initLoadJobSelector('');
                schedulePolling(Number(jobsWrap && jobsWrap.getAttribute('data-load-running-count') ? jobsWrap.getAttribute('data-load-running-count') : 0));
            };

            const activePanel = document.querySelector('[data-maintenance-panel].is-active');
            if (activePanel) {
                initSeedPresetSummary(activePanel);
                initLoadTestPanel(activePanel);
            }
        }());
    </script>
<?php else: ?>
        <nav class="cbt-maintenance-tabs" role="tablist" aria-label="Maintenance sections">
            <button
                type="button"
                class="cbt-maintenance-tab<?php echo $active_maintenance_tab === 'reset' ? ' is-active' : ''; ?>"
                data-maintenance-tab="reset"
                role="tab"
                aria-selected="<?php echo $active_maintenance_tab === 'reset' ? 'true' : 'false'; ?>"
            >
                Reset Database
                <span class="cbt-maintenance-tab-badge"><?php echo esc_html($reset_progress_is_running ? 'Aktif' : 'Form'); ?></span>
            </button>
            <button
                type="button"
                class="cbt-maintenance-tab<?php echo $active_maintenance_tab === 'seed' ? ' is-active' : ''; ?>"
                data-maintenance-tab="seed"
                role="tab"
                aria-selected="<?php echo $active_maintenance_tab === 'seed' ? 'true' : 'false'; ?>"
            >
                Bulk Test Data
                <span class="cbt-maintenance-tab-badge"><?php echo esc_html($seed_progress_is_running ? 'Aktif' : 'Form'); ?></span>
            </button>
            <button
                type="button"
                class="cbt-maintenance-tab<?php echo $active_maintenance_tab === 'load' ? ' is-active' : ''; ?>"
                data-maintenance-tab="load"
                role="tab"
                aria-selected="<?php echo $active_maintenance_tab === 'load' ? 'true' : 'false'; ?>"
            >
                Load Test
                <span class="cbt-maintenance-tab-badge"><?php echo esc_html($load_test_running_count > 0 ? $load_test_running_count . ' Run' : 'Jobs'); ?></span>
            </button>
        </nav>

        <div class="cbt-maintenance-panel<?php echo $active_maintenance_tab === 'reset' ? ' is-active' : ''; ?>" data-maintenance-panel="reset" role="tabpanel">
            <?php if (is_array($reset_progress_state)): ?>
                <section class="cbt-maintenance-card">
                    <div class="cbt-maintenance-card-header">
                        <div>
                            <h2>Progress Reset Database</h2>
                            <p>Progress reset ditampilkan real-time per batch sampai seluruh proses selesai.</p>
                        </div>
                        <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($reset_progress_status_tone); ?>">
                            <?php echo esc_html($reset_progress_status_label); ?>
                        </span>
                    </div>
                    <div class="cbt-maintenance-progress-track" aria-hidden="true">
                        <div class="cbt-maintenance-progress-fill" style="width: <?php echo esc_attr((string) $reset_progress_percent); ?>%;"></div>
                    </div>
                    <div class="cbt-maintenance-progress-meta">
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">Progress</span>
                            <strong><?php echo esc_html((string) $reset_progress_processed . ' / ' . (string) $reset_progress_total); ?></strong>
                        </div>
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">Persentase</span>
                            <strong><?php echo esc_html(number_format($reset_progress_percent, 2)); ?>%</strong>
                        </div>
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">User Terhapus</span>
                            <strong><?php echo esc_html((string) $reset_progress_deleted_users); ?></strong>
                        </div>
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">Tabel Gagal</span>
                            <strong><?php echo esc_html((string) $reset_progress_failed_tables); ?></strong>
                        </div>
                    </div>
                    <p class="cbt-maintenance-progress-note">
                        Tahap saat ini: <strong><?php echo esc_html($reset_progress_phase_label); ?></strong>.
                        <?php if ($reset_progress_is_running): ?>
                            Memproses batch berikutnya secara otomatis.
                            <script>
                                if (!window.__cbtMaintenanceAutoContinue) {
                                    window.__cbtMaintenanceAutoContinue = true;
                                    window.setTimeout(function () {
                                        window.location.href = <?php echo wp_json_encode($reset_progress_continue_url); ?>;
                                    }, 350);
                                }
                            </script>
                        <?php else: ?>
                            Reset database selesai diproses.
                        <?php endif; ?>
                    </p>
                </section>
            <?php endif; ?>

            <section class="cbt-maintenance-card cbt-maintenance-card--danger">
                <div class="cbt-maintenance-card-header">
                    <div>
                        <h2>Reset Database CBT</h2>
                        <p>Reset ini akan menghapus seluruh data CBT plugin secara permanen, termasuk struktur Bank Soal, dan tidak bisa dibatalkan.</p>
                    </div>
                    <span class="cbt-maintenance-chip cbt-maintenance-chip--danger">Danger Zone</span>
                </div>

                <div class="cbt-maintenance-alert">
                    <strong>Peringatan:</strong> semua data tabel plugin CBT akan dikosongkan, termasuk subjects, exam ujian, Bank Soal, questions, attempts, answers, options, hasil, dan pengaturan token global.
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Yakin reset data CBT? Aksi ini tidak bisa dibatalkan.');" style="margin-top:18px;">
                    <?php wp_nonce_field('cbt_reset_database'); ?>
                    <input type="hidden" name="action" value="cbt_reset_database" />
                    <input type="hidden" name="cbt_maintenance_tab" value="reset" data-maintenance-tab-input />

                    <div class="cbt-maintenance-field-grid">
                        <div class="cbt-maintenance-field">
                            <label>Reset tabel CBT</label>
                            <p class="cbt-maintenance-reset-copy">Progress reset akan ditampilkan otomatis sampai proses selesai. Setelah reset, Bank Soal tidak dibuat otomatis; struktur itu akan muncul lagi saat create question baru, import question, atau generate bulk test data.</p>
                        </div>
                        <div class="cbt-maintenance-field">
                            <label for="cbt-reset-confirm-phrase">Konfirmasi wajib</label>
                            <input
                                type="text"
                                id="cbt-reset-confirm-phrase"
                                name="confirm_phrase"
                                placeholder="Ketik: RESET CBT"
                                autocomplete="off"
                                required
                            />
                            <p class="description">Ketik persis <code>RESET CBT</code> untuk melanjutkan.</p>
                        </div>
                    </div>

                    <div class="cbt-maintenance-actions">
                        <p class="cbt-maintenance-actions-copy">Pastikan Anda sudah memahami dampaknya sebelum menjalankan reset penuh database CBT, termasuk penghapusan seluruh Bank Soal yang ada.</p>
                        <button type="submit" class="button button-primary button-large">Reset Database CBT</button>
                    </div>
                </form>
            </section>
        </div>

        <div class="cbt-maintenance-panel<?php echo $active_maintenance_tab === 'seed' ? ' is-active' : ''; ?>" data-maintenance-panel="seed" role="tabpanel">
            <?php if (is_array($seed_progress_state)): ?>
                <section class="cbt-maintenance-card cbt-maintenance-card--seed">
                    <div class="cbt-maintenance-card-header">
                        <div>
                            <h2>Progress Generate Data Uji</h2>
                            <p>Generator berjalan bertahap: reset penuh CBT, lalu membuat subject, user, Bank Soal, exam uji, root question, dan sinkronisasi soal ke exam.</p>
                        </div>
                        <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($seed_progress_status_tone); ?>">
                            <?php echo esc_html($seed_progress_status_label); ?>
                        </span>
                    </div>
                    <div class="cbt-maintenance-progress-track" aria-hidden="true">
                        <div class="cbt-maintenance-progress-fill cbt-maintenance-progress-fill--seed" style="width: <?php echo esc_attr((string) $seed_progress_percent); ?>%;"></div>
                    </div>
                    <?php if ($seed_progress_activity_detail !== ''): ?>
                        <div class="cbt-maintenance-progress-activity">
                            <span class="cbt-maintenance-progress-activity-label">Aktivitas Saat Ini</span>
                            <strong><?php echo esc_html($seed_progress_activity_detail); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="cbt-maintenance-progress-meta">
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">Progress</span>
                            <strong><?php echo esc_html((string) $seed_progress_processed . ' / ' . (string) $seed_progress_total); ?></strong>
                        </div>
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">Preset</span>
                            <strong><?php echo esc_html($seed_progress_preset_label); ?></strong>
                        </div>
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">User Sinkron</span>
                            <strong><?php echo esc_html((string) $seed_progress_synced_users); ?></strong>
                        </div>
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">Bank Question</span>
                            <strong><?php echo esc_html((string) $seed_progress_created_questions); ?></strong>
                        </div>
                        <div class="cbt-maintenance-stat">
                            <span class="cbt-maintenance-stat-label">Soal Ujian Sync</span>
                            <strong><?php echo esc_html((string) $seed_progress_synced_exam_questions); ?></strong>
                        </div>
                    </div>
                    <p class="cbt-maintenance-progress-note">
                        Tahap saat ini: <strong><?php echo esc_html($seed_progress_phase_label); ?></strong>.
                        Dataset ini memakai password default <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_default_password); ?></span>.
                        Akun test khusus: <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_special_username); ?></span> / <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_special_password); ?></span>.
                        <?php if ($seed_progress_is_running): ?>
                            Batch berikutnya akan dilanjutkan otomatis.
                            <script>
                                if (!window.__cbtMaintenanceAutoContinue) {
                                    window.__cbtMaintenanceAutoContinue = true;
                                    window.setTimeout(function () {
                                        window.location.href = <?php echo wp_json_encode($seed_progress_continue_url); ?>;
                                    }, 350);
                                }
                            </script>
                        <?php else: ?>
                            Generator data uji selesai diproses.
                        <?php endif; ?>
                    </p>
                    <div class="cbt-maintenance-summary-grid" style="margin-top:16px;">
                        <div class="cbt-maintenance-summary-item">
                            <span>User Lama Terhapus</span>
                            <strong><?php echo esc_html((string) $seed_progress_deleted_users); ?></strong>
                        </div>
                        <div class="cbt-maintenance-summary-item">
                            <span>Gagal Hapus User</span>
                            <strong><?php echo esc_html((string) $seed_progress_failed_user_deletes); ?></strong>
                        </div>
                        <div class="cbt-maintenance-summary-item">
                            <span>Persentase</span>
                            <strong><?php echo esc_html(number_format($seed_progress_percent, 2)); ?>%</strong>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="cbt-maintenance-card cbt-maintenance-card--seed">
                <div class="cbt-maintenance-card-header">
                    <div>
                        <h2>Generate Data Uji CBT</h2>
                        <p>Fitur ini akan menjalankan reset penuh CBT terlebih dulu, lalu membuat dataset baru dengan topologi Bank Soal per mapel dan exam uji yang menerima salinan soal tersinkron.</p>
                    </div>
                    <span class="cbt-maintenance-chip cbt-maintenance-chip--running">Test Seeder</span>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Generator akan reset penuh CBT lalu membuat dataset uji baru. Lanjutkan?');" style="margin-top:18px;">
                    <?php wp_nonce_field('cbt_generate_test_dataset'); ?>
                    <input type="hidden" name="action" value="cbt_generate_test_dataset" />
                    <input type="hidden" name="cbt_maintenance_tab" value="seed" data-maintenance-tab-input />

                    <div class="cbt-maintenance-field-grid">
                        <div class="cbt-maintenance-field">
                            <label for="cbt-seed-preset">Preset dataset</label>
                            <div class="cbt-maintenance-select-wrap">
                                <select id="cbt-seed-preset" name="preset">
                                    <?php foreach ($seed_presets as $preset_key => $preset_meta): ?>
                                        <option value="<?php echo esc_attr($preset_key); ?>" <?php selected($selected_seed_preset, $preset_key); ?>>
                                            <?php echo esc_html((string) ($preset_meta['label'] ?? ucfirst($preset_key))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="description"><code>Small</code> cocok untuk cek fungsi cepat, <code>Medium</code> untuk staging realistis, <code>Large</code> untuk pengujian beban awal.</p>
                        </div>
                        <div class="cbt-maintenance-field">
                            <label for="cbt-seed-confirm-phrase">Konfirmasi wajib</label>
                            <input
                                type="text"
                                id="cbt-seed-confirm-phrase"
                                name="confirm_phrase"
                                placeholder="Ketik: <?php echo esc_attr($test_data_seed_confirm_phrase); ?>"
                                autocomplete="off"
                                required
                            />
                            <p class="description">Ketik persis <code><?php echo esc_html($test_data_seed_confirm_phrase); ?></code> untuk memulai reset penuh lalu generate dataset baru.</p>
                        </div>
                    </div>

                    <div class="cbt-maintenance-summary-grid" id="cbt-seed-summary-grid">
                        <div class="cbt-maintenance-summary-item">
                            <span>Subject</span>
                            <strong data-seed-summary="subjects"><?php echo esc_html((string) ($selected_seed_preset_data['subjects'] ?? 0)); ?></strong>
                        </div>
                        <div class="cbt-maintenance-summary-item">
                            <span>Exam</span>
                            <strong data-seed-summary="exams"><?php echo esc_html((string) ($selected_seed_preset_data['exams'] ?? 0)); ?></strong>
                        </div>
                        <div class="cbt-maintenance-summary-item">
                            <span>Bank Question</span>
                            <strong data-seed-summary="questions"><?php echo esc_html((string) ($selected_seed_preset_data['questions'] ?? 0)); ?></strong>
                        </div>
                        <div class="cbt-maintenance-summary-item">
                            <span>Siswa</span>
                            <strong data-seed-summary="students"><?php echo esc_html((string) ($selected_seed_preset_data['students'] ?? 0)); ?></strong>
                        </div>
                        <div class="cbt-maintenance-summary-item">
                            <span>Guru</span>
                            <strong data-seed-summary="teachers"><?php echo esc_html((string) ($selected_seed_preset_data['teachers'] ?? 0)); ?></strong>
                        </div>
                        <div class="cbt-maintenance-summary-item">
                            <span>Kelas / Ruang</span>
                            <strong>
                                <span data-seed-summary="classes"><?php echo esc_html((string) ($selected_seed_preset_data['classes'] ?? 0)); ?></span>
                                /
                                <span data-seed-summary="rooms"><?php echo esc_html((string) ($selected_seed_preset_data['rooms'] ?? 0)); ?></span>
                            </strong>
                        </div>
                    </div>

                    <p class="cbt-maintenance-summary-note">
                        Preset ini
                        <strong data-seed-summary-label><?php echo esc_html((string) ($selected_seed_preset_data['label'] ?? 'Small')); ?></strong>
                        akan membuat root question di Bank Soal per mapel, lalu menyinkronkannya ke exam uji. User login dibuat dengan password default
                        <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_default_password); ?></span>.
                        Akun test khusus yang selalu dibuat: <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_special_username); ?></span> / <span class="cbt-maintenance-inline-code"><?php echo esc_html($test_data_seed_special_password); ?></span>.
                        Short answer bulk memakai placeholder inline <span class="cbt-maintenance-inline-code">[INPUT_1]</span> sampai <span class="cbt-maintenance-inline-code">[INPUT_8]</span>, dan jumlah input selalu sama dengan jumlah jawaban yang disimpan.
                        Rich content bulk memakai sample image internal plugin, lalu menyimpan gambar seperti import soal: prioritas ke uploads WordPress dan fallback ke base64 bila upload gagal.
                        Tabel HTML dipakai di stem soal, dan option <code>multiple_choice</code> / <code>multiple_answer</code> bisa membawa gambar serta tabel ringkas yang compact.
                    </p>

                    <div class="cbt-maintenance-question-breakdown">
                        <span class="cbt-maintenance-question-breakdown-title">Komposisi Bank Question</span>
                        <p class="cbt-maintenance-question-breakdown-copy" data-seed-question-summary-text>
                            <?php echo esc_html($selected_seed_question_type_summary); ?>
                        </p>
                        <div class="cbt-maintenance-question-chip-list" id="cbt-seed-question-breakdown">
                            <?php foreach ($selected_seed_question_type_counts as $question_type => $question_count): ?>
                                <?php if ((int) $question_count <= 0) { continue; } ?>
                                <span class="cbt-maintenance-question-chip">
                                    <?php echo esc_html((string) ($seed_question_type_labels[$question_type] ?? $question_type) . ': ' . number_format_i18n((int) $question_count)); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="cbt-maintenance-question-breakdown">
                        <span class="cbt-maintenance-question-breakdown-title">Komposisi Profil Exam</span>
                        <p class="cbt-maintenance-question-breakdown-copy" data-seed-exam-profile-summary-text>
                            <?php echo esc_html($selected_seed_exam_profile_summary); ?>
                        </p>
                        <div class="cbt-maintenance-question-chip-list" id="cbt-seed-exam-profile-breakdown">
                            <?php foreach ($selected_seed_exam_profile_counts as $profile_key => $profile_count): ?>
                                <?php if ((int) $profile_count <= 0) { continue; } ?>
                                <span class="cbt-maintenance-question-chip">
                                    <?php echo esc_html((string) ($seed_exam_profile_labels[$profile_key] ?? $profile_key) . ': ' . number_format_i18n((int) $profile_count)); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="cbt-maintenance-actions">
                        <p class="cbt-maintenance-actions-copy">Gunakan hanya pada staging atau lingkungan uji, karena aksi ini destruktif dan akan membersihkan seluruh data CBT saat ini sebelum membuat Bank Soal baru dan exam uji tersinkron.</p>
                        <button type="submit" class="button button-primary button-large" <?php disabled($seed_progress_is_running); ?>>
                            Reset &amp; Generate Data Uji
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div class="cbt-maintenance-panel<?php echo $active_maintenance_tab === 'load' ? ' is-active' : ''; ?>" data-maintenance-panel="load" role="tabpanel">
            <section class="cbt-maintenance-card cbt-maintenance-card--load">
                <div class="cbt-maintenance-card-header">
                    <div>
                        <h2>Load Test Runner</h2>
                        <p>Gunakan bulk students yang sudah ada untuk export dataset load test, lalu jalankan satu job <code>k6</code> per exam siswa secara paralel dari panel admin. Exam Bank Soal tidak pernah masuk katalog ini.</p>
                        <p style="margin:6px 0 0;color:#64748b;">Export <code>CSV</code> dan <code>XLSX</code> sekarang ikut membawa kolom <code>jenis_kelamin</code>, sedangkan <code>JSON</code> tetap ringkas untuk runner <code>k6</code> dengan <code>identifier</code> dan <code>password</code> saja.</p>
                    </div>
                    <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($load_test_running_count > 0 ? 'running' : 'idle'); ?>">
                        <?php echo esc_html($load_test_running_count > 0 ? $load_test_running_count . ' job aktif' : 'Siap dijalankan'); ?>
                    </span>
                </div>

                <div class="cbt-maintenance-alert" style="border-color:#dbe5ef;background:#f8fbff;color:#1e3a8a;">
                    <strong>Mode runner:</strong> background shell, reuse user yang sama di semua exam paralel, dan tidak memblokir run saat jumlah bulk students lebih kecil dari target VUs. Selector hanya menampilkan exam ujian siswa, bukan Bank Soal.
                </div>

                <div class="cbt-maintenance-load-section-grid" style="margin-top:16px;">
                    <section class="cbt-maintenance-load-section">
                        <div class="cbt-maintenance-card-header" style="margin-bottom:14px;">
                            <div>
                                <h3 style="margin:0 0 6px;font-size:17px;">Preflight</h3>
                                <p style="margin:0;color:#64748b;">Cek shell runner, binary k6, token global, base URL, dan kesiapan bulk students sebelum start job.</p>
                            </div>
                            <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr(!empty($load_test_runtime['k6_path']) ? 'done' : 'danger'); ?>">
                                <?php echo esc_html(!empty($load_test_runtime['k6_path']) ? 'k6 Terdeteksi' : 'k6 Belum Ada'); ?>
                            </span>
                        </div>

                        <div class="cbt-maintenance-load-preflight-grid">
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Shell PHP</span>
                                <strong><?php echo esc_html(!empty($load_test_runtime['shell_available']) ? 'Available' : 'Unavailable'); ?></strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Binary k6</span>
                                <strong><?php echo esc_html((string) (($load_test_runtime['k6_path'] ?? '') !== '' ? $load_test_runtime['k6_path'] : 'Tidak ditemukan')); ?></strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Install Mode</span>
                                <strong><?php echo esc_html((string) ($load_test_runtime['k6_install_mode'] ?? 'missing')); ?></strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Runner HOME</span>
                                <strong>
                                    <?php
                                    echo esc_html(
                                        (string) ($load_test_runtime['runner_home'] ?? '') !== ''
                                            ? (string) $load_test_runtime['runner_home']
                                            : ((string) ($load_test_runtime['runner_home_detected'] ?? '') !== '' ? (string) $load_test_runtime['runner_home_detected'] : '-')
                                    );
                                    ?>
                                </strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Bulk Students Ready</span>
                                <strong><?php echo esc_html((string) max(0, (int) ($load_test_student_pool['valid_count'] ?? 0))); ?></strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Tanpa Plain Password</span>
                                <strong><?php echo esc_html((string) max(0, (int) ($load_test_student_pool['missing_password_count'] ?? 0))); ?></strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Token Global Aktif</span>
                                <strong><?php echo esc_html((string) (((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? $load_test_runtime['global_token_meta']['token'] : '-')); ?></strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Refresh Token</span>
                                <strong><?php echo esc_html((string) number_format_i18n((int) (($load_test_runtime['global_token_meta']['refresh_minutes'] ?? 0))) . ' menit'); ?></strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Base URL Target</span>
                                <strong><?php echo esc_html($load_test_default_base_url); ?></strong>
                            </div>
                            <div class="cbt-maintenance-stat">
                                <span class="cbt-maintenance-stat-label">Runtime Uploads</span>
                                <strong><?php echo esc_html(!empty($load_test_runtime['runtime_root_writable']) ? 'Writable' : 'Perlu cek izin'); ?></strong>
                            </div>
                        </div>

                        <?php if ((string) ($load_test_runtime['k6_install_mode'] ?? '') === 'snap' && empty($load_test_runtime['runner_home_supported'])): ?>
                            <div class="cbt-maintenance-alert" style="margin-top:16px;border-color:#f4d6d6;background:#fff8f8;color:#9f1239;">
                                <strong>Snap warning:</strong> <code>k6</code> terdeteksi dari Snap, tetapi user PHP ini tidak punya <code>HOME</code> valid di bawah <code>/home</code>.
                                Runner admin akan gagal start sampai Anda menginstal <code>k6</code> versi native/non-snap atau mengonfigurasi Snap home di server.
                            </div>
                        <?php elseif ((string) ($load_test_runtime['k6_install_mode'] ?? '') === 'snap'): ?>
                            <div class="cbt-maintenance-alert" style="margin-top:16px;border-color:#dbe5ef;background:#f8fbff;color:#1e3a8a;">
                                <strong>Catatan Snap:</strong> runner bisa mencoba memakai <code><?php echo esc_html((string) ($load_test_runtime['runner_home'] ?? '')); ?></code>, tetapi untuk stabilitas terbaik tetap disarankan menggunakan <code>k6</code> native/non-snap.
                            </div>
                        <?php endif; ?>

                        <?php if ((string) ($load_test_runtime['k6_install_mode'] ?? '') !== 'native'): ?>
                            <section class="cbt-maintenance-install-guide">
                                <h4>Install k6 native di Ubuntu</h4>
                                <p>Jika server ini masih mendeteksi <code>k6</code> dari Snap atau belum menemukan binary sama sekali, gunakan urutan install Ubuntu berikut yang sudah cocok untuk repo resmi <code>k6</code>, lalu refresh halaman ini.</p>
                                <pre><code>sudo apt-get update
sudo apt-get install -y gnupg ca-certificates

sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69

echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
apt-cache policy k6
sudo apt-get install -y k6
k6 version</code></pre>
                                <p style="margin-top:12px;">Panduan resmi: <a href="https://grafana.com/docs/k6/latest/set-up/install-k6/" target="_blank" rel="noopener noreferrer">grafana.com/docs/k6/latest/set-up/install-k6/</a></p>
                            </section>
                        <?php endif; ?>
                    </section>

                    <section class="cbt-maintenance-load-section">
                        <div class="cbt-maintenance-card-header" style="margin-bottom:14px;">
                            <div>
                                <h3 style="margin:0 0 6px;font-size:17px;">Exam Selection &amp; Profile</h3>
                                <p style="margin:0;color:#64748b;">Pilih exam aktif yang punya soal, tentukan load profile, lalu sistem akan membuat satu job k6 per exam yang dipilih.</p>
                            </div>
                            <span class="cbt-maintenance-chip cbt-maintenance-chip--running"><?php echo esc_html(count($eligible_exams) . ' exam siap'); ?></span>
                        </div>

                        <?php if (!empty($invalid_exams)): ?>
                            <div class="cbt-maintenance-alert" style="margin-bottom:16px;border-color:#f4d6d6;background:#fff8f8;color:#9f1239;">
                                <strong>Info:</strong> <?php echo esc_html(count($invalid_exams)); ?> exam disembunyikan dari selector karena belum published, jadwalnya belum aktif, atau belum punya soal aktif. Bank Soal juga tidak pernah ditampilkan di selector ini.
                            </div>
                        <?php endif; ?>

                        <form
                            method="post"
                            action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                            class="cbt-maintenance-load-form"
                            data-load-test-form
                            data-load-profiles="<?php echo esc_attr(wp_json_encode($load_test_profile_presets)); ?>"
                            data-load-exams="<?php echo esc_attr(wp_json_encode(array_values($eligible_exams))); ?>"
                            data-load-k6-path="<?php echo esc_attr((string) ($load_test_runtime['k6_path'] ?? 'k6')); ?>"
                            data-load-ready-users="<?php echo esc_attr((string) max(0, (int) ($load_test_student_pool['valid_count'] ?? 0))); ?>"
                            onsubmit="return confirm('Mulai load test untuk semua exam yang dipilih? Satu exam akan dijalankan sebagai satu job k6 paralel.');"
                        >
                            <?php wp_nonce_field('cbt_start_load_test'); ?>
                            <input type="hidden" name="action" value="cbt_start_load_test" />
                            <input type="hidden" name="cbt_maintenance_tab" value="load" data-maintenance-tab-input />

                            <?php if (!empty($eligible_exams)): ?>
                                <p class="cbt-maintenance-field-help" style="margin-top:0;">Pilih satu atau beberapa exam aktif dari dropdown berikut. Setiap exam yang dipilih akan dibuatkan satu job <code>k6</code> terpisah dan dijalankan paralel.</p>
                                <details class="cbt-maintenance-load-picker" data-load-exam-picker>
                                    <summary>
                                        <span class="cbt-maintenance-load-picker-summary">
                                            <span class="cbt-maintenance-load-picker-copy">
                                                <strong data-load-exam-picker-label>1 exam dipilih</strong>
                                                <span data-load-exam-picker-meta>Buka daftar exam aktif lalu centang exam yang ingin dijalankan.</span>
                                            </span>
                                            <span class="cbt-maintenance-load-picker-caret" aria-hidden="true"></span>
                                        </span>
                                    </summary>
                                    <div class="cbt-maintenance-load-picker-menu">
                                        <?php foreach ($eligible_exams as $exam_row): ?>
                                            <?php $exam_id = (int) ($exam_row['id'] ?? 0); ?>
                                            <label class="cbt-maintenance-load-picker-option">
                                                <input
                                                    type="checkbox"
                                                    name="exam_ids[]"
                                                    value="<?php echo esc_attr((string) $exam_id); ?>"
                                                    <?php checked($first_exam_id === $exam_id); ?>
                                                    data-load-exam-checkbox
                                                />
                                                <span class="cbt-maintenance-load-exam-copy">
                                                    <strong><?php echo esc_html((string) ($exam_row['title'] ?? 'Exam')); ?></strong>
                                                    <span><?php echo esc_html((string) ($exam_row['subject_name'] ?? '')); ?> · <?php echo esc_html((string) ($exam_row['question_count'] ?? 0)); ?> soal · <?php echo esc_html((string) ($exam_row['duration_minutes'] ?? 0)); ?> menit · KKM <?php echo esc_html(number_format_i18n((float) ($exam_row['kkm_percentage'] ?? 75), 2)); ?>%</span>
                                                    <span><?php echo esc_html((string) ($exam_row['schedule_label'] ?? 'Tanpa batas jadwal')); ?></span>
                                                    <span>
                                                        Target kelas:
                                                        <?php
                                                        echo esc_html(
                                                            !empty($exam_row['target_kelas_list'])
                                                                ? implode(', ', (array) $exam_row['target_kelas_list'])
                                                                : 'Semua kelas'
                                                        );
                                                        ?>
                                                    </span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                                <div class="cbt-maintenance-load-selected-grid" data-load-selected-summary></div>
                            <?php else: ?>
                                <div class="cbt-maintenance-alert" style="margin-top:16px;">
                                    <strong>Tidak ada exam siap.</strong> Publikasikan exam siswa, pastikan jadwalnya aktif, dan isi minimal satu soal aktif sebelum menjalankan load test. Bank Soal tidak dihitung sebagai target load test.
                                </div>
                            <?php endif; ?>

                            <div class="cbt-maintenance-field-grid cbt-maintenance-field-grid--load">
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-profile-preset">Preset profile</label>
                                    <div class="cbt-maintenance-select-wrap">
                                        <select id="cbt-load-profile-preset" name="profile_preset" data-load-profile-preset>
                                            <?php foreach ($load_test_profile_presets as $profile_key => $profile_meta): ?>
                                                <option value="<?php echo esc_attr((string) $profile_key); ?>" <?php selected((string) ($load_test_default_profile['profile_preset'] ?? ''), (string) $profile_key); ?>>
                                                    <?php echo esc_html((string) ($profile_meta['label'] ?? $profile_key)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <p class="cbt-maintenance-field-help">Paket setting cepat untuk load test. Saat preset diganti, nilai field di bawah akan ikut menyesuaikan.</p>
                                </div>
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-base-url">Base URL target</label>
                                    <input type="text" id="cbt-load-base-url" name="base_url" value="<?php echo esc_attr($load_test_default_base_url); ?>" />
                                    <p class="cbt-maintenance-field-help">Alamat site yang ditembak runner <code>k6</code>. Gunakan URL yang benar-benar bisa diakses dari server ini.</p>
                                </div>
                            </div>

                            <div class="cbt-maintenance-field-grid cbt-maintenance-field-grid--load">
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-vus">VUs</label>
                                    <input type="number" min="1" max="5000" id="cbt-load-vus" name="vus" value="<?php echo esc_attr((string) ($load_test_default_profile['vus'] ?? 50)); ?>" data-load-profile-field="vus" />
                                    <p class="cbt-maintenance-field-help">Jumlah virtual user yang jalan paralel. Makin besar nilainya, makin tinggi beban bersamaan yang mengenai server.</p>
                                </div>
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-iterations">Iterations</label>
                                    <input type="number" min="1" max="100" id="cbt-load-iterations" name="iterations" value="<?php echo esc_attr((string) ($load_test_default_profile['iterations'] ?? 1)); ?>" data-load-profile-field="iterations" />
                                    <p class="cbt-maintenance-field-help">Berapa kali satu skenario ujian dijalankan per virtual user. Nilai lebih tinggi berarti total request ikut bertambah.</p>
                                </div>
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-questions-per-user">Questions per user</label>
                                    <input type="number" min="0" max="500" id="cbt-load-questions-per-user" name="questions_per_user" value="<?php echo esc_attr((string) ($load_test_default_profile['questions_per_user'] ?? 0)); ?>" data-load-profile-field="questions_per_user" />
                                    <div class="cbt-maintenance-load-quick-options" data-load-question-options></div>
                                    <p class="cbt-maintenance-field-help" data-load-question-help><code>0</code> berarti semua soal exam akan dipakai. Pilih exam dulu untuk melihat saran jumlah soal berdasarkan exam yang dipilih.</p>
                                </div>
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-submit-mode">Submit mode</label>
                                    <div class="cbt-maintenance-select-wrap">
                                        <select id="cbt-load-submit-mode" name="submit_mode" data-load-profile-field="submit_mode">
                                            <option value="all" <?php selected((string) ($load_test_default_profile['submit_mode'] ?? 'all'), 'all'); ?>>all</option>
                                            <option value="none" <?php selected((string) ($load_test_default_profile['submit_mode'] ?? 'all'), 'none'); ?>>none</option>
                                        </select>
                                    </div>
                                    <p class="cbt-maintenance-field-help"><code>all</code> akan kirim jawaban lalu finish exam. <code>none</code> hanya login, start, dan ambil soal tanpa submit jawaban.</p>
                                </div>
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-session-spread">Session start spread (ms)</label>
                                    <input type="number" min="0" max="600000" id="cbt-load-session-spread" name="session_start_spread_ms" value="<?php echo esc_attr((string) ($load_test_default_profile['session_start_spread_ms'] ?? 0)); ?>" data-load-profile-field="session_start_spread_ms" />
                                    <p class="cbt-maintenance-field-help">Jeda penyebaran saat mulai sesi user, supaya login dan start attempt tidak menumpuk di milidetik yang sama.</p>
                                </div>
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-post-spread">Post start spread (ms)</label>
                                    <input type="number" min="0" max="600000" id="cbt-load-post-spread" name="post_start_spread_ms" value="<?php echo esc_attr((string) ($load_test_default_profile['post_start_spread_ms'] ?? 0)); ?>" data-load-profile-field="post_start_spread_ms" />
                                    <p class="cbt-maintenance-field-help">Jeda tambahan setelah <code>start_attempt</code> berhasil, sebelum runner mulai request daftar soal exam.</p>
                                </div>
                                <div class="cbt-maintenance-field">
                                    <label for="cbt-load-manual-token">Manual token override</label>
                                    <input
                                        type="text"
                                        id="cbt-load-manual-token"
                                        name="manual_exam_token"
                                        value=""
                                        placeholder="<?php echo esc_attr(((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? ('Kosongkan untuk pakai token global ' . (string) ($load_test_runtime['global_token_meta']['token'] ?? '')) : 'Isi token manual jika token global belum aktif'); ?>"
                                    />
                                    <div class="cbt-maintenance-load-token-state">
                                        <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr(((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? 'done' : 'danger'); ?>" data-load-token-source-chip>
                                            <?php echo esc_html(((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? 'Global aktif' : 'Token belum ada'); ?>
                                        </span>
                                        <span class="cbt-maintenance-inline-code" data-load-token-value>
                                            <?php echo esc_html(((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? (string) ($load_test_runtime['global_token_meta']['token'] ?? '') : '-'); ?>
                                        </span>
                                    </div>
                                    <p class="cbt-maintenance-field-help" data-load-token-help>
                                        <?php if (((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== ''): ?>
                                            Token global aktif akan dipakai otomatis jika field override ini dikosongkan.
                                        <?php else: ?>
                                            Belum ada token global aktif. Isi manual token override jika run ini tetap harus memakai token exam tertentu.
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="cbt-maintenance-field">
                                    <label class="cbt-maintenance-load-checkbox">
                                        <input type="checkbox" name="enable_batch_submit" value="1" <?php checked(!empty($load_test_default_profile['enable_batch_submit'])); ?> data-load-profile-field="enable_batch_submit" />
                                        <span>Enable batch submit</span>
                                    </label>
                                    <p class="cbt-maintenance-field-help">Jika aktif, jawaban akan dikirim per batch, bukan satu-satu. Bila user bulk lebih sedikit dari target VUs, akun siswa tetap akan di-reuse otomatis oleh script k6.</p>
                                </div>
                            </div>

                            <div class="cbt-maintenance-load-preview" style="margin-top:18px;">
                                <div class="cbt-maintenance-card-header" style="margin-bottom:12px;">
                                    <div>
                                        <h3 style="margin:0 0 6px;font-size:16px;">Command Preview</h3>
                                        <p style="margin:0;color:#64748b;">Command final per exam tetap ditampilkan supaya bisa dipakai manual jika runner background perlu dibandingkan.</p>
                                    </div>
                                    <span class="cbt-maintenance-chip cbt-maintenance-chip--idle" data-load-user-warning>
                                        <?php echo esc_html((int) ($load_test_student_pool['valid_count'] ?? 0) < (int) ($load_test_default_profile['vus'] ?? 0) ? 'User akan di-reuse' : 'User cukup'); ?>
                                    </span>
                                </div>
                                <pre data-load-command-preview>Belum ada exam dipilih.</pre>
                            </div>

                            <div class="cbt-maintenance-actions cbt-maintenance-actions--load-primary" style="margin-top:18px;">
                                <p class="cbt-maintenance-actions-copy">Gunakan <code>Refresh Status</code> untuk memuat ulang daftar job, atau biarkan panel melakukan polling otomatis selama masih ada job aktif.</p>
                                <div class="cbt-maintenance-load-job-actions">
                                    <button type="submit" class="button button-primary button-large" <?php disabled(empty($eligible_exams)); ?>>Start Load Test</button>
                                    <button type="button" class="button button-secondary button-large" data-load-refresh-jobs>Refresh Status</button>
                                </div>
                            </div>
                        </form>
                    </section>
                </div>

                <section class="cbt-maintenance-load-section" style="margin-top:18px;">
                    <div class="cbt-maintenance-card-header" style="margin-bottom:14px;">
                        <div>
                            <h3 style="margin:0 0 6px;font-size:17px;">Jobs</h3>
                            <p style="margin:0;color:#64748b;">Daftar job k6 aktif dan histori terbaru. Status akan disinkronkan dari PID, exit code, log file, dan summary export.</p>
                        </div>
                        <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($load_test_running_count > 0 ? 'running' : 'idle'); ?>" data-load-running-chip>
                            <?php echo esc_html($load_test_running_count > 0 ? $load_test_running_count . ' running' : count($load_test_jobs) . ' total'); ?>
                        </span>
                    </div>
                    <div
                        class="cbt-maintenance-load-jobs-wrap"
                        data-load-jobs-wrap
                        data-load-jobs-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php?action=cbt_load_test_jobs')); ?>"
                        data-load-jobs-ajax-nonce="<?php echo esc_attr($ajax_nonce); ?>"
                        data-load-running-count="<?php echo esc_attr((string) $load_test_running_count); ?>"
                    >
                        <?php echo $load_test_jobs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </section>
            </section>
        </div>

    </div>
</div>
<script>
    (function () {
        const tabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-maintenance-tab]'));
        const tabPanels = Array.prototype.slice.call(document.querySelectorAll('[data-maintenance-panel]'));
        const questionTypeLabels = <?php echo wp_json_encode($seed_question_type_labels); ?>;
        const examProfileLabels = <?php echo wp_json_encode($seed_exam_profile_labels); ?>;
        const loadTestGlobalToken = <?php echo wp_json_encode((string) (($load_test_runtime['global_token_meta']['token'] ?? ''))); ?>;
        const syncUrl = function (tabName) {
            if (!window.history || !window.history.replaceState) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('cbt_maintenance_tab', tabName);
            url.searchParams.delete('cbt_unit_test_tab');
            window.history.replaceState({}, '', url.toString());
        };
        const activateTab = function (tabName, shouldSyncUrl) {
            tabButtons.forEach(function (button) {
                const active = button.getAttribute('data-maintenance-tab') === tabName;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            tabPanels.forEach(function (panel) {
                const active = panel.getAttribute('data-maintenance-panel') === tabName;
                panel.classList.toggle('is-active', active);
            });
            if (shouldSyncUrl) {
                syncUrl(tabName);
            }
        };
        tabButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activateTab(button.getAttribute('data-maintenance-tab') || 'reset', true);
            });
        });
        document.querySelectorAll('[data-maintenance-banner-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                const banner = button.closest('[data-maintenance-banner]');
                if (banner) {
                    banner.remove();
                }
            });
        });
        activateTab(<?php echo wp_json_encode($active_maintenance_tab); ?>, false);

        const numberFormatter = window.Intl ? new Intl.NumberFormat('id-ID') : null;
        const parseJson = function (value, fallbackValue) {
            try {
                return JSON.parse(String(value || ''));
            } catch (error) {
                return fallbackValue;
            }
        };

        const initSeedPresetSummary = function () {
            const presets = <?php echo $seed_presets_json; ?>;
            const select = document.getElementById('cbt-seed-preset');
            if (!select) {
                return;
            }

            const breakdownContainer = document.getElementById('cbt-seed-question-breakdown');
            const questionSummaryTextNode = document.querySelector('[data-seed-question-summary-text]');
            const examProfileBreakdownContainer = document.getElementById('cbt-seed-exam-profile-breakdown');
            const examProfileSummaryTextNode = document.querySelector('[data-seed-exam-profile-summary-text]');
            const updateSummary = function () {
                const preset = presets[select.value] || presets.small || null;
                if (!preset) {
                    return;
                }

                const keys = ['subjects', 'exams', 'questions', 'students', 'teachers', 'classes', 'rooms'];
                keys.forEach(function (key) {
                    document.querySelectorAll('[data-seed-summary="' + key + '"]').forEach(function (node) {
                        node.textContent = String(preset[key] || 0);
                    });
                });

                const labelNode = document.querySelector('[data-seed-summary-label]');
                if (labelNode) {
                    labelNode.textContent = preset.label || select.value;
                }

                if (questionSummaryTextNode) {
                    questionSummaryTextNode.textContent = preset.question_type_summary || '';
                }
                if (examProfileSummaryTextNode) {
                    examProfileSummaryTextNode.textContent = preset.exam_profile_summary || '';
                }

                if (breakdownContainer) {
                    breakdownContainer.innerHTML = '';
                    const counts = preset.question_type_counts || {};
                    Object.keys(counts).forEach(function (typeKey) {
                        const count = Number(counts[typeKey] || 0);
                        if (!count) {
                            return;
                        }

                        const chip = document.createElement('span');
                        chip.className = 'cbt-maintenance-question-chip';
                        chip.textContent = (questionTypeLabels[typeKey] || typeKey) + ': ' + (numberFormatter ? numberFormatter.format(count) : String(count));
                        breakdownContainer.appendChild(chip);
                    });
                }

                if (examProfileBreakdownContainer) {
                    examProfileBreakdownContainer.innerHTML = '';
                    const profileCounts = preset.exam_profile_counts || {};
                    Object.keys(profileCounts).forEach(function (profileKey) {
                        const count = Number(profileCounts[profileKey] || 0);
                        if (!count) {
                            return;
                        }

                        const chip = document.createElement('span');
                        chip.className = 'cbt-maintenance-question-chip';
                        chip.textContent = (examProfileLabels[profileKey] || profileKey) + ': ' + (numberFormatter ? numberFormatter.format(count) : String(count));
                        examProfileBreakdownContainer.appendChild(chip);
                    });
                }
            };

            select.addEventListener('change', updateSummary);
            updateSummary();
        };

        const initLoadTestPanel = function () {
            const form = document.querySelector('[data-load-test-form]');
            const jobsWrap = document.querySelector('[data-load-jobs-wrap]');
            if (!form && !jobsWrap) {
                return;
            }

            const profiles = form ? parseJson(form.getAttribute('data-load-profiles'), {}) : {};
            const exams = form ? parseJson(form.getAttribute('data-load-exams'), []) : [];
            const k6Path = form ? String(form.getAttribute('data-load-k6-path') || 'k6') : 'k6';
            const readyUsers = form ? Number(form.getAttribute('data-load-ready-users') || 0) : 0;
            const presetSelect = form ? form.querySelector('[data-load-profile-preset]') : null;
            const commandPreview = form ? form.querySelector('[data-load-command-preview]') : null;
            const warningChip = form ? form.querySelector('[data-load-user-warning]') : null;
            const refreshButton = form ? form.querySelector('[data-load-refresh-jobs]') : null;
            const examPickerLabel = form ? form.querySelector('[data-load-exam-picker-label]') : null;
            const examPickerMeta = form ? form.querySelector('[data-load-exam-picker-meta]') : null;
            const selectedSummary = form ? form.querySelector('[data-load-selected-summary]') : null;
            const questionsField = form ? form.querySelector('[name="questions_per_user"]') : null;
            const questionHelp = form ? form.querySelector('[data-load-question-help]') : null;
            const questionOptionsWrap = form ? form.querySelector('[data-load-question-options]') : null;
            const manualTokenField = form ? form.querySelector('[name="manual_exam_token"]') : null;
            const tokenSourceChip = form ? form.querySelector('[data-load-token-source-chip]') : null;
            const tokenValueNode = form ? form.querySelector('[data-load-token-value]') : null;
            const tokenHelpNode = form ? form.querySelector('[data-load-token-help]') : null;
            const runningChip = document.querySelector('[data-load-running-chip]');
            const fieldNames = [
                'vus',
                'iterations',
                'questions_per_user',
                'session_start_spread_ms',
                'post_start_spread_ms',
                'submit_mode',
            ];

            const buildCommand = function (exam, state) {
                const parts = [
                    'cd ' + JSON.stringify('<workspace-for-' + String(exam.id || 0) + '>'),
                    'BASE_URL=' + JSON.stringify(state.baseUrl),
                    'EXAM_ID=' + String(exam.id || 0),
                ];
                if (state.examToken) {
                    parts.push('EXAM_TOKEN=' + JSON.stringify(state.examToken));
                }
                parts.push('VUS=' + String(state.vus));
                parts.push('ITERATIONS=' + String(state.iterations));
                parts.push('QUESTIONS_PER_USER=' + String(state.questionsPerUser));
                parts.push('SESSION_START_SPREAD_MS=' + String(state.sessionSpread));
                parts.push('POST_START_SPREAD_MS=' + String(state.postSpread));
                parts.push('SUBMIT_MODE=' + JSON.stringify(state.submitMode));
                parts.push('ENABLE_BATCH_SUBMIT=' + (state.enableBatchSubmit ? '1' : '0'));
                parts.push(JSON.stringify(k6Path) + ' run --summary-export summary.json cbt_exam_1000_users.js');
                return parts.join(' \\\n  ');
            };

            const getState = function () {
                const baseUrlField = form ? form.querySelector('[name="base_url"]') : null;
                const batchSubmitField = form ? form.querySelector('[name="enable_batch_submit"]') : null;
                return {
                    baseUrl: baseUrlField ? String(baseUrlField.value || '').trim() : '',
                    examToken: manualTokenField && String(manualTokenField.value || '').trim() !== ''
                        ? String(manualTokenField.value || '').trim().toUpperCase()
                        : String(loadTestGlobalToken || ''),
                    vus: Number(form && form.querySelector('[name="vus"]') ? form.querySelector('[name="vus"]').value : 0) || 0,
                    iterations: Number(form && form.querySelector('[name="iterations"]') ? form.querySelector('[name="iterations"]').value : 1) || 1,
                    questionsPerUser: Number(form && form.querySelector('[name="questions_per_user"]') ? form.querySelector('[name="questions_per_user"]').value : 0) || 0,
                    sessionSpread: Number(form && form.querySelector('[name="session_start_spread_ms"]') ? form.querySelector('[name="session_start_spread_ms"]').value : 0) || 0,
                    postSpread: Number(form && form.querySelector('[name="post_start_spread_ms"]') ? form.querySelector('[name="post_start_spread_ms"]').value : 0) || 0,
                    submitMode: String(form && form.querySelector('[name="submit_mode"]') ? form.querySelector('[name="submit_mode"]').value : 'all'),
                    enableBatchSubmit: !!(batchSubmitField && batchSubmitField.checked)
                };
            };

            const getSelectedExams = function () {
                if (!form) {
                    return [];
                }
                const checkedIds = Array.prototype.slice.call(form.querySelectorAll('[data-load-exam-checkbox]:checked')).map(function (input) {
                    return Number(input.value || 0);
                });
                return exams.filter(function (exam) {
                    return checkedIds.indexOf(Number(exam.id || 0)) !== -1;
                });
            };

            const renderSelectedExams = function (selectedExams) {
                if (!selectedSummary) {
                    return;
                }

                selectedSummary.innerHTML = '';
                if (!selectedExams.length) {
                    const emptyState = document.createElement('div');
                    emptyState.className = 'cbt-maintenance-load-selected-empty';
                    emptyState.textContent = 'Belum ada exam dipilih. Buka dropdown di atas lalu centang exam yang ingin dijalankan untuk load test.';
                    selectedSummary.appendChild(emptyState);
                    return;
                }

                selectedExams.forEach(function (exam) {
                    const card = document.createElement('article');
                    card.className = 'cbt-maintenance-load-selected-card';

                    const copy = document.createElement('div');
                    copy.className = 'cbt-maintenance-load-exam-copy';

                    const title = document.createElement('strong');
                    title.textContent = String(exam.title || ('Exam #' + String(exam.id || 0)));
                    copy.appendChild(title);

                    const meta = document.createElement('span');
                    const rawKkm = Number(exam.kkm_percentage);
                    const safeKkm = Number.isFinite(rawKkm) ? rawKkm : 75;
                    meta.textContent = String(exam.subject_name || '') + ' · ' + String(exam.question_count || 0) + ' soal · ' + String(exam.duration_minutes || 0) + ' menit · KKM ' + String(safeKkm) + '%';
                    copy.appendChild(meta);

                    const schedule = document.createElement('span');
                    schedule.textContent = String(exam.schedule_label || 'Tanpa batas jadwal');
                    copy.appendChild(schedule);

                    const target = document.createElement('span');
                    const classes = Array.isArray(exam.target_kelas_list) && exam.target_kelas_list.length
                        ? exam.target_kelas_list.join(', ')
                        : 'Semua kelas';
                    target.textContent = 'Target kelas: ' + classes;
                    copy.appendChild(target);

                    card.appendChild(copy);
                    selectedSummary.appendChild(card);
                });
            };

            const updateExamPickerSummary = function (selectedExams) {
                if (examPickerLabel) {
                    if (!selectedExams.length) {
                        examPickerLabel.textContent = 'Belum ada exam dipilih';
                    } else if (selectedExams.length === 1) {
                        examPickerLabel.textContent = String(selectedExams[0].title || '1 exam dipilih');
                    } else {
                        examPickerLabel.textContent = String(selectedExams.length) + ' exam dipilih';
                    }
                }

                if (examPickerMeta) {
                    if (!selectedExams.length) {
                        examPickerMeta.textContent = 'Buka daftar exam aktif lalu centang exam yang ingin dijalankan.';
                    } else if (selectedExams.length === 1) {
                        examPickerMeta.textContent = '1 job k6 akan dibuat untuk exam ini.';
                    } else {
                        examPickerMeta.textContent = String(selectedExams.length) + ' job k6 akan dibuat dan dijalankan paralel.';
                    }
                }
            };

            const updateTokenState = function () {
                if (!tokenSourceChip && !tokenValueNode && !tokenHelpNode) {
                    return;
                }

                const manualToken = manualTokenField ? String(manualTokenField.value || '').trim().toUpperCase() : '';
                const globalToken = String(loadTestGlobalToken || '').trim().toUpperCase();

                if (manualToken !== '') {
                    if (tokenSourceChip) {
                        tokenSourceChip.textContent = 'Manual override';
                        tokenSourceChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--running';
                    }
                    if (tokenValueNode) {
                        tokenValueNode.textContent = manualToken;
                    }
                    if (tokenHelpNode) {
                        tokenHelpNode.textContent = 'Token manual ini akan dipakai untuk run sekarang dan mengoverride token global aktif.';
                    }
                    return;
                }

                if (globalToken !== '') {
                    if (tokenSourceChip) {
                        tokenSourceChip.textContent = 'Global aktif';
                        tokenSourceChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--done';
                    }
                    if (tokenValueNode) {
                        tokenValueNode.textContent = globalToken;
                    }
                    if (tokenHelpNode) {
                        tokenHelpNode.textContent = 'Token global aktif ini akan dipakai otomatis selama field override tetap kosong.';
                    }
                    return;
                }

                if (tokenSourceChip) {
                    tokenSourceChip.textContent = 'Token belum ada';
                    tokenSourceChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--danger';
                }
                if (tokenValueNode) {
                    tokenValueNode.textContent = '-';
                }
                if (tokenHelpNode) {
                    tokenHelpNode.textContent = 'Belum ada token global aktif. Isi manual token override jika run ini tetap harus memakai token exam tertentu.';
                }
            };

            const buildQuestionOptionValues = function (minCount, maxCount) {
                const values = [0];
                [10, 20, 30, 40, 50].forEach(function (value) {
                    if (value > 0 && value < minCount) {
                        values.push(value);
                    }
                });
                if (minCount > 0) {
                    values.push(minCount);
                }
                if (maxCount > minCount) {
                    values.push(maxCount);
                }

                return values.filter(function (value, index, array) {
                    return array.indexOf(value) === index;
                }).sort(function (left, right) {
                    return left - right;
                });
            };

            const formatNumber = function (value) {
                return numberFormatter ? numberFormatter.format(Number(value || 0)) : String(value || 0);
            };

            const updateQuestionFieldMeta = function (selectedExams) {
                if (!questionsField && !questionHelp && !questionOptionsWrap) {
                    return;
                }

                const counts = selectedExams.map(function (exam) {
                    return Number(exam.question_count || 0) || 0;
                }).filter(function (count) {
                    return count > 0;
                });

                if (questionOptionsWrap) {
                    questionOptionsWrap.innerHTML = '';
                }

                if (!counts.length) {
                    if (questionHelp) {
                        questionHelp.innerHTML = '<code>0</code> berarti semua soal exam akan dipakai. Pilih exam dulu untuk melihat saran jumlah soal berdasarkan exam yang dipilih.';
                    }
                    if (questionsField) {
                        questionsField.setAttribute('max', '500');
                    }
                    return;
                }

                const minCount = Math.min.apply(null, counts);
                const maxCount = Math.max.apply(null, counts);
                const sameCount = minCount === maxCount;
                const currentValue = questionsField ? (Number(questionsField.value || 0) || 0) : 0;

                if (questionsField) {
                    questionsField.setAttribute('max', String(Math.min(500, maxCount)));
                }

                if (questionHelp) {
                    if (sameCount) {
                        questionHelp.innerHTML = 'Exam terpilih punya <code>' + formatNumber(minCount) + '</code> soal. Gunakan <code>0</code> jika ingin memakai semua soal, atau pilih angka cepat di bawah.';
                    } else {
                        questionHelp.innerHTML = 'Exam terpilih punya <code>' + formatNumber(minCount) + '</code> sampai <code>' + formatNumber(maxCount) + '</code> soal. Nilai <code>' + formatNumber(minCount) + '</code> aman untuk semua exam, sedangkan <code>0</code> berarti semua soal tiap exam dipakai.';
                    }
                }

                if (!questionOptionsWrap || !questionsField) {
                    return;
                }

                buildQuestionOptionValues(minCount, maxCount).forEach(function (value) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'cbt-maintenance-load-quick-option' + (currentValue === value ? ' is-active' : '');
                    button.setAttribute('data-load-question-option', String(value));

                    if (value === 0) {
                        button.textContent = sameCount ? 'Semua (' + formatNumber(minCount) + ')' : 'Semua per exam';
                    } else if (!sameCount && value === minCount) {
                        button.textContent = formatNumber(value) + ' aman semua';
                    } else if (!sameCount && value === maxCount) {
                        button.textContent = formatNumber(value) + ' maks';
                    } else {
                        button.textContent = formatNumber(value);
                    }

                    button.addEventListener('click', function () {
                        questionsField.value = String(value);
                        updateQuestionFieldMeta(selectedExams);
                        updateCommandPreview();
                    });
                    questionOptionsWrap.appendChild(button);
                });
            };

            const applyPreset = function () {
                if (!form || !presetSelect) {
                    return;
                }
                const presetKey = String(presetSelect.value || '');
                const preset = profiles[presetKey] || null;
                if (!preset) {
                    return;
                }
                fieldNames.forEach(function (fieldName) {
                    const field = form.querySelector('[name="' + fieldName + '"]');
                    if (field && preset[fieldName] !== undefined) {
                        field.value = String(preset[fieldName]);
                    }
                });

                const batchField = form.querySelector('[name="enable_batch_submit"]');
                if (batchField && preset.enable_batch_submit !== undefined) {
                    batchField.checked = Number(preset.enable_batch_submit || 0) === 1;
                }
            };

            const updateCommandPreview = function () {
                if (!commandPreview) {
                    return;
                }
                const selectedExams = getSelectedExams();
                updateExamPickerSummary(selectedExams);
                renderSelectedExams(selectedExams);
                updateQuestionFieldMeta(selectedExams);
                updateTokenState();
                const state = getState();
                if (warningChip) {
                    const needsReuse = readyUsers > 0 && state.vus > readyUsers;
                    warningChip.textContent = needsReuse ? 'User akan di-reuse' : 'User cukup';
                    warningChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--' + (needsReuse ? 'danger' : 'idle');
                }
                if (!selectedExams.length) {
                    commandPreview.textContent = 'Belum ada exam dipilih.';
                    return;
                }

                const blocks = selectedExams.map(function (exam) {
                    return '# ' + String(exam.title || ('Exam #' + String(exam.id || 0))) + '\n' + buildCommand(exam, state);
                });
                commandPreview.textContent = blocks.join('\n\n');
            };

            const initLoadJobSelector = function (preferredJobId) {
                if (!jobsWrap) {
                    return;
                }

                const selector = jobsWrap.querySelector('[data-load-job-selector]');
                const cards = Array.prototype.slice.call(jobsWrap.querySelectorAll('[data-load-job-card]'));
                if (!selector || !cards.length) {
                    return;
                }

                const availableIds = cards.map(function (card) {
                    return String(card.getAttribute('data-load-job-id') || '');
                });
                const applySelection = function (jobId) {
                    let resolvedId = String(jobId || '').trim();
                    if (availableIds.indexOf(resolvedId) === -1) {
                        resolvedId = availableIds.length ? availableIds[0] : '';
                    }
                    selector.value = resolvedId;
                    cards.forEach(function (card) {
                        const active = String(card.getAttribute('data-load-job-id') || '') === resolvedId;
                        card.hidden = !active;
                    });
                };

                selector.addEventListener('change', function () {
                    applySelection(String(selector.value || ''));
                });

                applySelection(String(preferredJobId || selector.value || ''));
            };

            let pollingTimer = null;
            const schedulePolling = function (runningCount) {
                if (pollingTimer) {
                    window.clearTimeout(pollingTimer);
                    pollingTimer = null;
                }
                if (Number(runningCount || 0) <= 0 || !jobsWrap) {
                    return;
                }
                pollingTimer = window.setTimeout(function () {
                    refreshJobs(false);
                }, 5000);
            };

            const refreshJobs = function (focusLoadTab) {
                if (!jobsWrap) {
                    return;
                }
                const ajaxUrl = String(jobsWrap.getAttribute('data-load-jobs-ajax-url') || '');
                const ajaxNonce = String(jobsWrap.getAttribute('data-load-jobs-ajax-nonce') || '');
                if (ajaxUrl === '' || ajaxNonce === '' || typeof window.fetch !== 'function') {
                    window.location.reload();
                    return;
                }

                const currentJobSelector = jobsWrap.querySelector('[data-load-job-selector]');
                const currentJobId = currentJobSelector ? String(currentJobSelector.value || '') : '';
                const url = new URL(ajaxUrl, window.location.origin);
                url.searchParams.set('nonce', ajaxNonce);

                window.fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json'
                    }
                }).then(function (response) {
                    return response.json();
                }).then(function (payload) {
                    if (!payload || !payload.success || !payload.data) {
                        return;
                    }
                    jobsWrap.innerHTML = String(payload.data.html || '');
                    jobsWrap.setAttribute('data-load-running-count', String(payload.data.running_count || 0));
                    initLoadJobSelector(currentJobId);
                    if (runningChip) {
                        runningChip.textContent = Number(payload.data.running_count || 0) > 0
                            ? String(payload.data.running_count || 0) + ' running'
                            : String(payload.data.job_count || 0) + ' total';
                        runningChip.className = 'cbt-maintenance-chip cbt-maintenance-chip--' + (Number(payload.data.running_count || 0) > 0 ? 'running' : 'idle');
                    }
                    if (focusLoadTab) {
                        activateTab('load', true);
                    }
                    schedulePolling(Number(payload.data.running_count || 0));
                }).catch(function () {
                    schedulePolling(0);
                });
            };

            if (presetSelect) {
                presetSelect.addEventListener('change', function () {
                    applyPreset();
                    updateCommandPreview();
                });
            }
            if (form) {
                Array.prototype.slice.call(form.querySelectorAll('input, select')).forEach(function (field) {
                    field.addEventListener('change', updateCommandPreview);
                    field.addEventListener('input', updateCommandPreview);
                });
            }
            document.querySelectorAll('[data-load-exam-checkbox]').forEach(function (checkbox) {
                checkbox.addEventListener('change', updateCommandPreview);
            });
            if (refreshButton) {
                refreshButton.addEventListener('click', function () {
                    refreshJobs(true);
                });
            }
            updateCommandPreview();
            initLoadJobSelector('');
            schedulePolling(Number(jobsWrap && jobsWrap.getAttribute('data-load-running-count') ? jobsWrap.getAttribute('data-load-running-count') : 0));
        };

        initSeedPresetSummary();
        initLoadTestPanel();
    }());
</script>
<?php endif; ?>
