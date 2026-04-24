<?php

if (!defined('ABSPATH')) {
    exit;
}

$overview_summary = (array) ($overview_data['summary'] ?? []);
$overview_pagination = (array) ($overview_pagination ?? []);
$overview_exam_rows = (array) ($overview_pagination['rows'] ?? []);
$overview_page_links = (array) ($overview_pagination['page_links'] ?? []);
$exam_summary = (array) (($exam_analytics['summary'] ?? []) ?: []);
$exam_quality = (array) (($exam_analytics['quality'] ?? []) ?: []);
$exam_item_flags = (array) (($exam_analytics['item_flags'] ?? []) ?: []);
$exam_meta = (array) (($exam_analytics['exam'] ?? []) ?: []);
$exam_distribution = (array) (($exam_analytics['distribution'] ?? []) ?: []);
$exam_kelas_summary = (array) (($exam_analytics['per_kelas_summary'] ?? []) ?: []);
$item_analysis_summary = (array) ($item_analysis_summary ?? []);

$has_selected_exam = $selected_exam_id > 0 && !empty($selected_exam);
$analytics_reset_url = CBT_Admin_Analytics_Service::build_analytics_url();
$overview_analytic_all_url = CBT_Admin_Analytics_Service::build_analytics_url([
    'cbt_analytics_tab' => 'overview',
    'cbt_exam_id' => $selected_exam_id > 0 ? $selected_exam_id : null,
    'cbt_overview_page' => (int) ($overview_pagination['current_page'] ?? 1),
    'cbt_run_analytics' => 'all',
]);
$current_tab = CBT_Admin_Analytics_Service::normalize_tab((string) ($active_tab ?? 'overview'));
$item_rows_json = wp_json_encode(array_values((array) $item_analysis_rows));
$student_rows_json = wp_json_encode(array_values((array) $student_rows));
$quality_reliability_label = (string) ($exam_quality['reliability_label'] ?? 'Insufficient Data');
$quality_reliability_display = (string) ($exam_quality['reliability_display'] ?? 'Insufficient Data');
$quality_reliability_value = isset($exam_quality['reliability']) && is_numeric($exam_quality['reliability']) ? (float) $exam_quality['reliability'] : null;
$quality_reliability_method = (string) ($exam_quality['reliability_method'] ?? 'Insufficient Data');
$quality_stddev_display = (string) ($exam_quality['standard_deviation_display'] ?? 'Insufficient Data');
$quality_sem_display = (string) ($exam_quality['sem_display'] ?? 'Insufficient Data');
$quality_objective_avg_display = (string) ($exam_quality['average_objective_percentage_display'] ?? '0.00%');
$quality_profile = (string) ($exam_quality['objective_profile'] ?? 'insufficient');
$quality_profile_label = (string) ($exam_quality['objective_profile_label'] ?? 'Belum Layak Dinilai');
$quality_diagnostics = (array) ($exam_quality['diagnostics'] ?? []);
$quality_counts = (array) ($quality_diagnostics['counts'] ?? []);
$quality_included_types = array_values((array) ($quality_diagnostics['included_types'] ?? []));
$quality_excluded_types = array_values((array) ($quality_diagnostics['excluded_types'] ?? []));
$quality_excluded_reasons = array_values((array) ($quality_diagnostics['excluded_reasons'] ?? []));
$quality_composition_label = (string) ($quality_diagnostics['composition_profile_label'] ?? $quality_profile_label);
$quality_profile_reason = (string) ($quality_diagnostics['profile_reason'] ?? '');
$quality_method_reason = (string) ($quality_diagnostics['method_reason'] ?? '');
$quality_fallback_reason = (string) ($quality_diagnostics['fallback_reason'] ?? '');
$quality_why_reason = (string) ($quality_diagnostics['why_reason'] ?? '');
$quality_eligible_item_count = (int) ($exam_quality['eligible_objective_item_count'] ?? 0);
$quality_eligible_attempt_count = (int) ($exam_quality['eligible_attempt_count'] ?? ($exam_quality['objective_attempt_count'] ?? 0));
$quality_note_text = 'Perhitungan ini hanya memakai soal objective yang sudah finalized. Essay/manual tidak ikut reliability.';
$quality_summary_text = 'Belum ada cukup data objective finalized untuk menilai kualitas paket soal ini.';
if ($quality_reliability_label === 'Reliable') {
    $quality_summary_text = 'Kualitas soal objective pada exam ini sudah cukup stabil, jadi hasil peserta relatif aman untuk dibaca sebagai satu paket ujian.';
} elseif ($quality_reliability_label === 'Marginal') {
    $quality_summary_text = 'Kualitas soal objective pada exam ini berada di batas tengah. Hasilnya sudah mulai terbaca, tetapi beberapa butir masih perlu dipantau.';
} elseif ($quality_reliability_label === 'Weak') {
    $quality_summary_text = 'Kualitas soal objective pada exam ini masih lemah, sehingga hasilnya sebaiknya dibaca dengan lebih hati-hati.';
    if ($quality_reliability_value !== null && $quality_reliability_value < 0.0) {
        $quality_summary_text = 'Kualitas soal objective pada exam ini bermasalah. Pola jawaban antarsoal tidak stabil, jadi hasil exam belum kuat jika dibaca sebagai satu paket ujian.';
    }
} elseif ($quality_profile === 'mixed_objective') {
    $quality_summary_text = 'Komposisi soal objective pada exam ini sudah terdeteksi campuran, tetapi data hasil peserta yang layak belum cukup untuk menilai konsistensinya.';
} elseif ($quality_profile === 'dichotomous') {
    $quality_summary_text = 'Komposisi soal objective pada exam ini masih bertipe benar/salah, tetapi data hasil peserta yang layak belum cukup untuk menilai konsistensinya.';
}

$quality_result_points = [];
$quality_result_points[] = 'Rata-rata skor objective peserta yang eligible saat ini berada di ' . $quality_objective_avg_display . '.';
$quality_result_points[] = $quality_profile_reason !== '' ? $quality_profile_reason : 'Belum ada komposisi soal objective finalized yang cukup untuk dibaca.';

if ($quality_reliability_display === 'Insufficient Data') {
    $quality_result_points[] = $quality_fallback_reason !== '' ? $quality_fallback_reason : 'Konsistensi antarsoal belum bisa dinilai karena data objective atau jumlah peserta yang layak belum cukup.';
} elseif ($quality_reliability_value !== null && $quality_reliability_value < 0.0) {
    $quality_result_points[] = 'Nilai reliability ' . $quality_reliability_display . ' dengan metode ' . $quality_reliability_method . ' menunjukkan antarsoal tidak bergerak searah, sehingga paket soal ini perlu ditinjau ulang.';
} elseif ($quality_reliability_value !== null && $quality_reliability_value >= 0.80) {
    $quality_result_points[] = 'Nilai reliability ' . $quality_reliability_display . ' dengan metode ' . $quality_reliability_method . ' menunjukkan konsistensi antarsoal sudah kuat.';
} elseif ($quality_reliability_value !== null && $quality_reliability_value >= 0.70) {
    $quality_result_points[] = 'Nilai reliability ' . $quality_reliability_display . ' dengan metode ' . $quality_reliability_method . ' menunjukkan konsistensi antarsoal cukup, tetapi belum terlalu kuat.';
} else {
    $quality_result_points[] = 'Nilai reliability ' . $quality_reliability_display . ' dengan metode ' . $quality_reliability_method . ' menunjukkan konsistensi antarsoal masih lemah.';
}

if ($quality_stddev_display !== 'Insufficient Data') {
    $quality_result_points[] = 'Sebaran nilai objective peserta saat ini berada di angka ' . $quality_stddev_display . ', jadi jarak performa antar peserta sudah mulai terlihat.';
}

if ($quality_sem_display !== 'Insufficient Data') {
    $quality_result_points[] = 'Margin error pengukuran saat ini berada di sekitar ' . $quality_sem_display . ', jadi pembacaan nilai individual tetap perlu ruang toleransi.';
} else {
    $quality_result_points[] = 'Margin error pengukuran belum bisa dipakai karena konsistensi exam ini belum valid.';
}

if ($quality_eligible_item_count > 0 || $quality_eligible_attempt_count > 0) {
    $quality_result_points[] = sprintf(
        'Reliability saat ini membaca %1$d butir objective finalized dari %2$d attempt yang layak.',
        $quality_eligible_item_count,
        $quality_eligible_attempt_count
    );
}

if ((int) ($exam_item_flags['weak_discrimination_count'] ?? 0) > 0) {
    $quality_result_points[] = sprintf(
        'Ada %d butir dengan discrimination lemah atau terbalik, jadi beberapa soal belum membedakan siswa kuat dan lemah dengan baik.',
        (int) ($exam_item_flags['weak_discrimination_count'] ?? 0)
    );
}
if ((int) ($exam_item_flags['high_omission_count'] ?? 0) > 0) {
    $quality_result_points[] = sprintf(
        'Ada %d butir yang sering dikosongkan, menandakan soal bisa terlalu sulit, terlalu panjang, atau membingungkan.',
        (int) ($exam_item_flags['high_omission_count'] ?? 0)
    );
}
if ((int) ($exam_item_flags['pending_manual_count'] ?? 0) > 0) {
    $quality_result_points[] = sprintf(
        'Ada %d butir yang masih menunggu review manual, jadi sebagian hasil exam belum benar-benar final.',
        (int) ($exam_item_flags['pending_manual_count'] ?? 0)
    );
}
$quality_result_points = array_values(array_unique(array_filter($quality_result_points, static function ($text): bool {
    return is_string($text) && trim($text) !== '';
})));

$quality_next_step_text = 'Prioritas berikutnya adalah meninjau ulang butir yang lemah, terlalu sering dikosongkan, atau masih menunggu review manual.';
if ($quality_reliability_label === 'Reliable' && (int) ($exam_item_flags['weak_discrimination_count'] ?? 0) === 0 && (int) ($exam_item_flags['high_omission_count'] ?? 0) === 0 && (int) ($exam_item_flags['pending_manual_count'] ?? 0) === 0) {
    $quality_next_step_text = 'Secara umum paket soal objective sudah stabil dan tidak menunjukkan flag besar, jadi hasil exam bisa dipakai sebagai dasar evaluasi.';
} elseif ($quality_reliability_label === 'Insufficient Data') {
    $quality_next_step_text = $quality_fallback_reason !== ''
        ? 'Lengkapi dulu syarat minimum pembacaan quality, lalu jalankan analytics lagi. ' . $quality_fallback_reason
        : 'Pastikan tersedia minimal dua butir objective finalized dan minimal lima peserta selesai sebelum quality dibaca.';
} elseif ($quality_reliability_value !== null && $quality_reliability_value < 0.0) {
    $quality_next_step_text = 'Mulai dari review kunci jawaban, kejelasan stem, dan butir yang discrimination-nya terbalik. Pola jawaban antarsoal perlu distabilkan lebih dulu sebelum hasil exam dipakai terlalu jauh.';
} elseif ($quality_reliability_label === 'Marginal') {
    $quality_next_step_text = 'Fokus utamanya adalah merapikan butir yang masih lemah atau sering dikosongkan agar konsistensi paket naik dari batas tengah ke kondisi yang lebih stabil.';
}
?>
<div class="wrap cbt-analytics-page">
    <?php if (!empty($notice)): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html((string) $notice); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="notice notice-error"><p><?php echo esc_html((string) $error); ?></p></div>
    <?php endif; ?>

    <style>
        .cbt-analytics-page {
            padding-right: 18px;
        }
        .cbt-analytics-shell {
            max-width: 1320px;
            display: grid;
            gap: 20px;
        }
        .cbt-analytics-page .notice {
            margin: 0;
        }
        .cbt-analytics-hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            padding: 26px 28px;
            border: 1px solid #d7dbe2;
            border-radius: 24px;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.10), transparent 34%),
                linear-gradient(135deg, #ffffff 0%, #f6f9fc 100%);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
        }
        .cbt-analytics-hero-copy {
            max-width: 760px;
            display: grid;
            gap: 10px;
        }
        .cbt-analytics-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            width: fit-content;
            padding: 0 12px;
            border-radius: 999px;
            background: #e8f1ff;
            color: #0f4fa8;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .cbt-analytics-hero h1 {
            margin: 0;
            font-size: 40px;
            line-height: 1.05;
            color: #0f172a;
        }
        .cbt-analytics-hero p {
            margin: 0;
            color: #425466;
            font-size: 15px;
            line-height: 1.75;
        }
        .cbt-analytics-live-panel {
            min-width: 320px;
            max-width: 360px;
            padding: 18px;
            border: 1px solid #dbe4f0;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
        }
        .cbt-analytics-live-label {
            display: block;
            margin-bottom: 12px;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .cbt-analytics-live-value {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 64px;
            margin-bottom: 14px;
            border-radius: 18px;
            background: #0f172a;
            color: #fff;
            font-size: 20px;
            font-weight: 800;
            text-align: center;
        }
        .cbt-analytics-live-meta {
            display: grid;
            gap: 10px;
        }
        .cbt-analytics-live-meta-item {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            color: #1e293b;
        }
        .cbt-analytics-live-meta-item span {
            color: #475569;
        }
        .cbt-analytics-tabs {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 6px;
            border: 1px solid #d9e1ea;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fafc 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
        }
        .cbt-analytics-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 18px;
            border: 0;
            border-radius: 14px;
            background: transparent;
            color: #4b5563;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 140ms ease, color 140ms ease, box-shadow 140ms ease;
        }
        .cbt-analytics-tab:hover,
        .cbt-analytics-tab:focus {
            background: #eef4fb;
            color: #153f67;
            outline: none;
        }
        .cbt-analytics-tab.is-active {
            background: linear-gradient(180deg, #2f7ab9 0%, #1f68a6 100%);
            color: #ffffff;
            box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
        }
        .cbt-analytics-panel {
            padding: 24px;
            border: 1px solid #dcdcde;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
        }
        .cbt-analytics-panel-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .cbt-analytics-panel-header h2 {
            margin: 0 0 6px;
            font-size: 22px;
            line-height: 1.15;
            color: #0f172a;
        }
        .cbt-analytics-panel-header p {
            margin: 0;
            color: #526174;
            line-height: 1.65;
        }
        .cbt-analytics-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .cbt-analytics-chip {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f8fbff;
            border: 1px solid #dbe7f3;
            color: #1e3a5f;
            font-size: 12px;
            font-weight: 700;
        }
        .cbt-analytics-chip.is-pass {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }
        .cbt-analytics-chip.is-fail {
            background: #fef2f2;
            border-color: #fecaca;
            color: #b91c1c;
        }
        .cbt-analytics-chip.is-warning {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #c2410c;
        }
        .cbt-analytics-filter-form {
            display: grid;
            grid-template-columns: minmax(280px, 1fr) auto;
            gap: 14px;
            align-items: end;
        }
        .cbt-analytics-field {
            display: grid;
            gap: 8px;
        }
        .cbt-analytics-field label {
            margin: 0;
            font-size: 13px;
            line-height: 1.3;
            color: #223246;
            font-weight: 700;
        }
        .cbt-analytics-field select,
        .cbt-analytics-field input[type="search"] {
            width: 100%;
            min-height: 48px;
            padding: 0 15px;
            border: 1px solid #c9d7e6;
            border-radius: 16px;
            background: #f8fbff;
            color: #0f172a;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }
        .cbt-analytics-field select:focus,
        .cbt-analytics-field input[type="search"]:focus {
            border-color: #2271b1;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
            outline: none;
        }
        .cbt-analytics-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .cbt-analytics-summary {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .cbt-analytics-summary-label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }
        .cbt-analytics-summary-item {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 0 14px;
            border-radius: 999px;
            background: #f8fbff;
            border: 1px solid #dbe7f3;
            color: #1e3a5f;
            font-size: 13px;
            font-weight: 600;
        }
        .cbt-analytics-tab-panel {
            display: none;
        }
        .cbt-analytics-tab-panel.is-active {
            display: grid;
            gap: 16px;
        }
        .cbt-analytics-metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }
        .cbt-analytics-metric-card {
            display: grid;
            gap: 6px;
            padding: 14px 16px;
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.035);
        }
        .cbt-analytics-metric-card span {
            color: #607287;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .cbt-analytics-metric-card strong {
            color: #0f172a;
            font-size: 24px;
            line-height: 1.05;
            font-weight: 800;
        }
        .cbt-analytics-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .cbt-analytics-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .cbt-analytics-mini-glossary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin: 6px 0 18px;
        }
        .cbt-analytics-mini-glossary-item {
            padding: 12px 14px;
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .cbt-analytics-mini-glossary-item strong {
            display: block;
            margin-bottom: 4px;
            color: #0f172a;
            font-size: 13px;
        }
        .cbt-analytics-mini-glossary-item p {
            margin: 0;
            color: #526174;
            font-size: 12px;
            line-height: 1.55;
        }
        .cbt-analytics-table-wrap {
            overflow: auto;
            border: 1px solid #dcdcde;
            border-radius: 16px;
            background: #fff;
        }
        .cbt-analytics-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }
        .cbt-analytics-table.is-compact {
            min-width: 0;
        }
        .cbt-analytics-table.is-compact.is-option-analysis {
            table-layout: fixed;
        }
        .cbt-analytics-table.is-compact.is-option-analysis col.cbt-col-option {
            width: 17%;
        }
        .cbt-analytics-table.is-compact.is-option-analysis col.cbt-col-selected {
            width: 18%;
        }
        .cbt-analytics-table.is-compact.is-option-analysis col.cbt-col-band {
            width: 20%;
        }
        .cbt-analytics-table.is-compact.is-option-analysis col.cbt-col-insight {
            width: 45%;
        }
        .cbt-analytics-table th,
        .cbt-analytics-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: top;
        }
        .cbt-analytics-table.is-compact th,
        .cbt-analytics-table.is-compact td {
            padding: 8px 10px;
        }
        .cbt-analytics-table th {
            background: #f8fafc;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #475569;
        }
        .cbt-analytics-table.is-dense-head th {
            font-size: 10px;
            letter-spacing: .04em;
            padding-top: 9px;
            padding-bottom: 9px;
        }
        .cbt-analytics-table.is-compact th {
            font-size: 10px;
            letter-spacing: .05em;
            white-space: nowrap;
        }
        .cbt-analytics-table.is-compact td {
            font-size: 12px;
        }
        .cbt-analytics-table tbody tr:hover {
            background: #f8fbff;
        }
        .cbt-analytics-table-cell-stack {
            display: grid;
            gap: 4px;
        }
        .cbt-analytics-table-cell-meta {
            color: #64748b;
            font-size: 11px;
            line-height: 1.35;
        }
        .cbt-analytics-table-cell-pair {
            display: grid;
            gap: 3px;
            white-space: nowrap;
        }
        .cbt-analytics-table-cell-pair strong {
            color: #0f172a;
        }
        .cbt-analytics-table-cell-pair.is-soft {
            white-space: normal;
        }
        .cbt-analytics-inline-metrics {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.35;
        }
        .cbt-analytics-inline-metrics span {
            white-space: nowrap;
        }
        .cbt-analytics-inline-metrics strong {
            color: #0f172a;
        }
        .cbt-analytics-inline-chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .cbt-analytics-mini-bar {
            position: relative;
            height: 8px;
            border-radius: 999px;
            background: #e8eef7;
            overflow: hidden;
            margin-top: 2px;
            max-width: 210px;
        }
        .cbt-analytics-table.is-option-analysis .cbt-analytics-mini-bar {
            max-width: 138px;
        }
        .cbt-analytics-table.is-option-analysis .cbt-analytics-table-cell-meta {
            font-size: 10px;
        }
        .cbt-analytics-mini-bar > span {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #2f7ab9 0%, #63b3ed 100%);
        }
        .cbt-analytics-inline-detail-row td {
            padding: 0;
            background: #f8fbff;
        }
        .cbt-analytics-inline-detail-wrap {
            padding: 14px;
            border-top: 1px dashed #dbe5f2;
        }
        .cbt-analytics-inline-detail {
            display: grid;
            gap: 12px;
        }
        .cbt-analytics-inline-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .cbt-analytics-inline-card {
            padding: 14px;
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: #fff;
        }
        .cbt-analytics-inline-card.is-insight-summary {
            border-color: #bfdbfe;
            background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.08);
        }
        .cbt-analytics-inline-card strong {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
        }
        .cbt-analytics-inline-card ul {
            margin: 0 0 0 16px;
        }
        .cbt-analytics-inline-card p {
            margin: 0;
            color: #526174;
            line-height: 1.65;
        }
        .cbt-analytics-inline-card p strong {
            display: inline;
            margin: 0;
            color: #0f172a;
        }
        .cbt-analytics-inline-card p + p {
            margin-top: 8px;
        }
        .cbt-analytics-quality-card,
        .cbt-analytics-quality-admin-card {
            display: grid;
            gap: 14px;
        }
        .cbt-analytics-quality-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .cbt-analytics-quality-heading h3 {
            margin: 0;
            color: #0f172a;
            font-size: 20px;
            line-height: 1.2;
        }
        .cbt-analytics-quality-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            margin-bottom: 8px;
            padding: 0 10px;
            border-radius: 999px;
            background: #e8f1ff;
            color: #1f68a6;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .cbt-analytics-quality-lead {
            margin: 0;
            color: #4e6176;
            line-height: 1.7;
            text-align: justify;
        }
        .cbt-analytics-quality-summary-list {
            margin: 0;
            padding-left: 22px;
            color: #526174;
            list-style: disc;
        }
        .cbt-analytics-quality-summary-list li {
            text-align: justify;
        }
        .cbt-analytics-quality-summary-list li + li {
            margin-top: 8px;
        }
        .cbt-analytics-quality-summary-next {
            padding: 12px 14px;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.88);
        }
        .cbt-analytics-quality-summary-next strong {
            display: block;
            margin-bottom: 6px;
            color: #0f172a;
            font-size: 12px;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .cbt-analytics-quality-summary-next p {
            margin: 0;
            color: #526174;
            font-size: 13px;
            line-height: 1.6;
            text-align: justify;
        }
        .cbt-analytics-quality-note {
            margin: 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.65;
            text-align: justify;
        }
        .cbt-analytics-quality-tech {
            display: grid;
            gap: 12px;
            padding: 14px;
            border: 1px dashed #c8d8eb;
            border-radius: 14px;
            background: linear-gradient(180deg, #fbfdff 0%, #f4f9ff 100%);
        }
        .cbt-analytics-quality-subtitle {
            display: block;
            margin: 0;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .cbt-analytics-quality-diagnostics-grid {
            display: grid;
            gap: 12px;
        }
        .cbt-analytics-quality-diagnostic-card {
            padding: 14px;
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(248,251,255,.98) 100%);
        }
        .cbt-analytics-quality-diagnostic-label {
            display: block;
            margin-bottom: 8px;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
        .cbt-analytics-quality-diagnostic-card strong {
            display: block;
            margin-bottom: 6px;
            color: #0f172a;
            font-size: 15px;
            line-height: 1.35;
        }
        .cbt-analytics-quality-diagnostic-card p {
            margin: 0;
            color: #526174;
            font-size: 13px;
            line-height: 1.65;
            text-align: justify;
        }
        .cbt-analytics-quality-diagnostic-card p + p {
            margin-top: 8px;
        }
        .cbt-analytics-quality-detail-list {
            margin: 0;
            padding: 0;
            color: #526174;
            list-style: none;
            display: grid;
            gap: 6px;
        }
        .cbt-analytics-quality-detail-list li {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 10px;
            border: 1px solid #e5edf7;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.88);
        }
        .cbt-analytics-quality-detail-list li strong {
            margin: 0;
            font-size: 13px;
            line-height: 1.35;
        }
        .cbt-analytics-quality-detail-meta {
            display: block;
            flex: 0 0 auto;
            margin-top: 0;
            color: #64748b;
            font-size: 11px;
            line-height: 1.4;
            text-align: right;
        }
        .cbt-analytics-inline-glossary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 14px;
        }
        .cbt-analytics-inline-glossary-item {
            position: relative;
            padding: 14px 16px 14px 18px;
            border: 1px solid #d8e4f2;
            border-radius: 16px;
            background:
                linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(248,251,255,.98) 100%);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }
        .cbt-analytics-inline-glossary-item::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: linear-gradient(180deg, #2f7ab9 0%, #63b3ed 100%);
        }
        .cbt-analytics-inline-glossary-item > strong {
            display: block;
            margin-bottom: 6px;
            color: #0f172a;
            font-size: 13px;
        }
        .cbt-analytics-inline-glossary-item span {
            display: block;
            color: #526174;
            font-size: 12px;
            line-height: 1.55;
        }
        .cbt-analytics-inline-glossary-item span strong {
            display: inline;
            margin: 0;
            color: inherit;
            font-size: inherit;
        }
        .cbt-analytics-inline-glossary-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            margin-bottom: 8px;
            padding: 0 10px;
            border-radius: 999px;
            background: #e8f1ff;
            color: #1f68a6;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .cbt-analytics-inline-glossary-points {
            display: grid;
            gap: 6px;
            margin-top: 10px;
        }
        .cbt-analytics-inline-glossary-point {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            color: #526174;
            font-size: 11px;
            line-height: 1.5;
        }
        .cbt-analytics-inline-glossary-point::before {
            content: "";
            width: 7px;
            height: 7px;
            margin-top: 5px;
            border-radius: 999px;
            background: #63b3ed;
            flex: 0 0 7px;
        }
        .cbt-analytics-inline-glossary-point strong {
            color: #0f172a;
        }
        .cbt-analytics-answer-graph {
            display: grid;
            gap: 12px;
        }
        .cbt-analytics-answer-graph-row {
            display: grid;
            grid-template-columns: minmax(120px, 180px) minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
        }
        .cbt-analytics-answer-graph-label {
            color: #0f172a;
            font-weight: 600;
            line-height: 1.45;
            word-break: break-word;
        }
        .cbt-analytics-answer-graph-track {
            position: relative;
            height: 14px;
            border-radius: 999px;
            background: #e8eef7;
            overflow: hidden;
        }
        .cbt-analytics-answer-graph-fill {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #2f7ab9 0%, #63b3ed 100%);
        }
        .cbt-analytics-answer-graph-meta {
            display: grid;
            justify-items: end;
            min-width: 88px;
            color: #475569;
            font-size: 12px;
            line-height: 1.3;
            text-align: right;
        }
        .cbt-analytics-answer-graph-meta strong {
            display: block;
            margin: 0;
            color: #0f172a;
            font-size: 13px;
            font-weight: 800;
        }
        .cbt-analytics-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
        }
        .cbt-analytics-badge.is-pass,
        .cbt-analytics-badge.is-easy,
        .cbt-analytics-badge.is-ok {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .cbt-analytics-badge.is-fail,
        .cbt-analytics-badge.is-hard {
            background: #fee2e2;
            color: #b91c1c;
        }
        .cbt-analytics-badge.is-medium,
        .cbt-analytics-badge.is-warning,
        .cbt-analytics-badge.is-manual {
            background: #fef3c7;
            color: #a16207;
        }
        .cbt-analytics-badge.is-neutral {
            background: #e2e8f0;
            color: #475569;
        }
        .cbt-analytics-table.is-compact .cbt-analytics-badge {
            padding: 3px 7px;
            font-size: 10px;
        }
        .cbt-analytics-insight-cell {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .cbt-analytics-insight-badges {
            display: grid;
            gap: 6px;
        }
        .cbt-analytics-insight-help {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            margin-top: 1px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #ffffff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
        }
        .cbt-analytics-insight-help:hover,
        .cbt-analytics-insight-help:focus {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1e40af;
            outline: none;
        }
        .cbt-analytics-insight-tooltip {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            z-index: 20;
            width: min(280px, 72vw);
            padding: 12px 14px;
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.14);
            display: none;
        }
        .cbt-analytics-insight-cell:hover .cbt-analytics-insight-tooltip,
        .cbt-analytics-insight-cell:focus-within .cbt-analytics-insight-tooltip {
            display: block;
        }
        .cbt-analytics-insight-tooltip > strong {
            display: block;
            margin: 0 0 8px;
            color: #0f172a;
            font-size: 12px;
        }
        .cbt-analytics-insight-tooltip p strong {
            display: inline;
            margin: 0;
            color: #0f172a;
            font-size: inherit;
        }
        .cbt-analytics-insight-tooltip p {
            margin: 0;
            color: #475569;
            font-size: 12px;
            line-height: 1.55;
        }
        .cbt-analytics-insight-tooltip p + p {
            margin-top: 8px;
        }
        .cbt-analytics-overview-list {
            display: grid;
            gap: 12px;
        }
        .cbt-analytics-overview-row {
            display: grid;
            gap: 10px;
            padding: 16px;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }
        .cbt-analytics-overview-row-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .cbt-analytics-overview-row-head h3 {
            margin: 0 0 4px;
            font-size: 16px;
            color: #0f172a;
        }
        .cbt-analytics-overview-row-head p {
            margin: 0;
            color: #64748b;
        }
        .cbt-analytics-overview-row-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cbt-analytics-distribution {
            display: grid;
            gap: 12px;
        }
        .cbt-analytics-distribution-row {
            display: grid;
            grid-template-columns: 90px minmax(0, 1fr) 64px;
            gap: 12px;
            align-items: center;
        }
        .cbt-analytics-distribution-bar {
            position: relative;
            height: 14px;
            border-radius: 999px;
            background: #e5edf7;
            overflow: hidden;
        }
        .cbt-analytics-distribution-bar > span {
            position: absolute;
            inset: 0 auto 0 0;
            border-radius: inherit;
            background: linear-gradient(90deg, #2f7ab9 0%, #5aa7e7 100%);
        }
        .cbt-analytics-empty {
            padding: 22px;
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            background: #f8fafc;
            color: #475569;
        }
        .cbt-analytics-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
            margin-bottom: 12px;
        }
        .cbt-analytics-toolbar .cbt-analytics-field {
            min-width: 140px;
        }
        .cbt-analytics-toolbar .cbt-analytics-field.is-search {
            flex: 1 1 280px;
        }
        .cbt-analytics-pagination {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 12px;
        }
        .cbt-analytics-pagination-meta {
            color: #64748b;
            font-size: 12px;
        }
        .cbt-analytics-muted {
            color: #64748b;
        }
        @media (max-width: 1180px) {
            .cbt-analytics-hero {
                flex-direction: column;
            }
            .cbt-analytics-live-panel {
                min-width: 0;
                max-width: none;
            }
            .cbt-analytics-filter-form,
            .cbt-analytics-metric-grid,
            .cbt-analytics-grid-2,
            .cbt-analytics-grid-3,
            .cbt-analytics-mini-glossary,
            .cbt-analytics-inline-detail-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 782px) {
            .cbt-analytics-hero,
            .cbt-analytics-panel {
                padding: 20px;
            }
            .cbt-analytics-hero h1 {
                font-size: 32px;
            }
            .cbt-analytics-distribution-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .cbt-analytics-answer-graph-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .cbt-analytics-answer-graph-meta {
                justify-items: start;
                text-align: left;
                min-width: 0;
            }
            .cbt-analytics-inline-glossary {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="cbt-analytics-shell">
        <section class="cbt-analytics-hero">
            <div class="cbt-analytics-hero-copy">
                <span class="cbt-analytics-kicker">Analytics</span>
                <h1>CBT Analytics</h1>
                <p>Ringkasan hasil ujian untuk admin dan guru dengan fokus pada completed attempts, perbandingan lintas exam berbasis persentase, analisis butir soal, dan drilldown siswa tanpa mengubah data attempt yang sudah ada.</p>
            </div>
            <aside class="cbt-analytics-live-panel">
                <span class="cbt-analytics-live-label">Live Summary</span>
                <span class="cbt-analytics-live-value"><?php echo esc_html((string) number_format_i18n((int) ($overview_summary['completed_attempts'] ?? 0))); ?> Attempt</span>
                <div class="cbt-analytics-live-meta">
                    <div class="cbt-analytics-live-meta-item">
                        <span>Rata-rata</span>
                        <strong><?php echo esc_html((string) ($overview_summary['average_percentage_display'] ?? '0.00%')); ?></strong>
                    </div>
                    <div class="cbt-analytics-live-meta-item">
                        <span>Pass Rate</span>
                        <strong><?php echo esc_html((string) ($overview_summary['pass_rate_display'] ?? '0.00%')); ?></strong>
                    </div>
                    <div class="cbt-analytics-live-meta-item">
                        <span>Exam Terjangkau</span>
                        <strong><?php echo esc_html((string) number_format_i18n((int) ($analytics_entry_counts['accessible_exam_count'] ?? 0))); ?></strong>
                    </div>
                    <div class="cbt-analytics-live-meta-item">
                        <span>Kelas</span>
                        <strong><?php echo esc_html((string) number_format_i18n((int) ($analytics_entry_counts['kelas_count'] ?? 0))); ?></strong>
                    </div>
                </div>
            </aside>
        </section>

        <section class="cbt-analytics-panel">
            <div class="cbt-analytics-panel-header">
                <div>
                    <h2>Filter Analytics</h2>
                    <p>Pilih exam untuk menentukan scope tampilan. Filter akan langsung diterapkan saat pilihan berubah, sedangkan proses analytics dijalankan dari daftar exam di bawah.</p>
                </div>
                <div class="cbt-analytics-chip-row">
                    <?php foreach ($active_filters as $active_filter): ?>
                        <span class="cbt-analytics-chip"><?php echo esc_html((string) ($active_filter['label'] ?? 'Filter')); ?>: <?php echo esc_html((string) ($active_filter['value'] ?? '-')); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="get" class="cbt-analytics-filter-form" id="cbt-analytics-filter-form">
                <input type="hidden" name="page" value="cbt-analytics">
                <input type="hidden" name="cbt_analytics_tab" id="cbt-analytics-active-tab" value="<?php echo esc_attr($current_tab); ?>">

                <div class="cbt-analytics-field">
                    <label for="cbt-exam-id">Exam</label>
                    <select id="cbt-exam-id" name="cbt_exam_id" data-auto-submit="1">
                        <option value="0">Semua exam</option>
                        <?php foreach ($exam_filter_rows as $exam_row): ?>
                            <option value="<?php echo esc_attr((string) ($exam_row['id'] ?? 0)); ?>" <?php selected($selected_exam_id, (int) ($exam_row['id'] ?? 0)); ?>>
                                <?php echo esc_html((string) ($exam_row['filter_label'] ?? $exam_row['title'] ?? 'Exam')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cbt-analytics-filter-actions">
                    <noscript><button type="submit" class="button" data-submit-tab="overview">Terapkan Filter</button></noscript>
                    <a href="<?php echo esc_url($analytics_reset_url); ?>" class="button">Reset</a>
                </div>
            </form>
        </section>

        <div class="cbt-analytics-tabs" role="tablist" aria-label="CBT Analytics Tabs">
            <button type="button" class="cbt-analytics-tab<?php echo $current_tab === 'overview' ? ' is-active' : ''; ?>" data-analytics-tab="overview" role="tab" aria-selected="<?php echo $current_tab === 'overview' ? 'true' : 'false'; ?>">Overview</button>
            <button type="button" class="cbt-analytics-tab<?php echo $current_tab === 'exam' ? ' is-active' : ''; ?>" data-analytics-tab="exam" role="tab" aria-selected="<?php echo $current_tab === 'exam' ? 'true' : 'false'; ?>">Exam</button>
            <button type="button" class="cbt-analytics-tab<?php echo $current_tab === 'items' ? ' is-active' : ''; ?>" data-analytics-tab="items" role="tab" aria-selected="<?php echo $current_tab === 'items' ? 'true' : 'false'; ?>">Items</button>
            <button type="button" class="cbt-analytics-tab<?php echo $current_tab === 'students' ? ' is-active' : ''; ?>" data-analytics-tab="students" role="tab" aria-selected="<?php echo $current_tab === 'students' ? 'true' : 'false'; ?>">Students</button>
        </div>

        <section class="cbt-analytics-tab-panel<?php echo $current_tab === 'overview' ? ' is-active' : ''; ?>" data-analytics-panel="overview">
            <div class="cbt-analytics-metric-grid">
                <article class="cbt-analytics-metric-card">
                    <span>Completed Attempts</span>
                    <strong><?php echo esc_html(number_format_i18n((int) ($overview_summary['completed_attempts'] ?? 0))); ?></strong>
                </article>
                <article class="cbt-analytics-metric-card">
                    <span>Average Percentage</span>
                    <strong><?php echo esc_html((string) ($overview_summary['average_percentage_display'] ?? '0.00%')); ?></strong>
                </article>
                <article class="cbt-analytics-metric-card">
                    <span>Pass Rate</span>
                    <strong><?php echo esc_html((string) ($overview_summary['pass_rate_display'] ?? '0.00%')); ?></strong>
                </article>
                <article class="cbt-analytics-metric-card">
                    <span>Essay Pending</span>
                    <strong><?php echo esc_html(number_format_i18n((int) ($overview_summary['manual_review_count'] ?? 0))); ?></strong>
                </article>
            </div>

            <section class="cbt-analytics-panel">
                <div class="cbt-analytics-panel-header">
                    <div>
                        <h2>Exam List</h2>
                        <p>Daftar exam mengikuti filter aktif. Gunakan <em>ANALYTIC ALL</em> untuk memproses semua exam pada daftar ini, atau tombol <em>Analytic</em> di tiap row untuk satu exam tertentu.</p>
                    </div>
                    <div class="cbt-analytics-chip-row">
                        <span class="cbt-analytics-chip"><?php echo esc_html(number_format_i18n((int) ($overview_pagination['total_rows'] ?? 0))); ?> exam</span>
                        <span class="cbt-analytics-chip">Halaman <?php echo esc_html(number_format_i18n((int) ($overview_pagination['current_page'] ?? 1))); ?> / <?php echo esc_html(number_format_i18n((int) ($overview_pagination['total_pages'] ?? 1))); ?></span>
                        <?php if (!empty($overview_exam_rows)): ?>
                            <a href="<?php echo esc_url($overview_analytic_all_url); ?>" class="button button-primary">ANALYTIC ALL</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($overview_exam_rows)): ?>
                    <div class="cbt-analytics-table-wrap">
                        <table class="cbt-analytics-table">
                            <thead>
                                <tr>
                                    <th>Exam</th>
                                    <th>Subject</th>
                                    <th>Completed</th>
                                    <th>Average %</th>
                                    <th>Pass Rate</th>
                                    <th>Essay Pending</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overview_exam_rows as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="cbt-analytics-table-cell-stack">
                                                <strong><?php echo esc_html((string) ($row['title'] ?? '-')); ?></strong>
                                                <span class="cbt-analytics-table-cell-meta">Exam ID #<?php echo esc_html(number_format_i18n((int) ($row['exam_id'] ?? 0))); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html((string) ($row['subject_name'] ?? 'Tanpa subject')); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) ($row['completed_attempts'] ?? 0))); ?></td>
                                        <td><?php echo esc_html((string) ($row['average_percentage_display'] ?? '0.00%')); ?></td>
                                        <td><?php echo esc_html((string) ($row['pass_rate_display'] ?? '0.00%')); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) ($row['manual_review_count'] ?? 0))); ?></td>
                                        <td><a href="<?php echo esc_url((string) ($row['analytic_url'] ?? $analytics_reset_url)); ?>" class="button button-small">Analytic</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($overview_pagination['has_multiple_pages'])): ?>
                        <div class="cbt-analytics-pagination">
                            <span class="cbt-analytics-pagination-meta">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        'Menampilkan %1$s-%2$s dari %3$s exam.',
                                        number_format_i18n((int) ($overview_pagination['start_row'] ?? 0)),
                                        number_format_i18n((int) ($overview_pagination['end_row'] ?? 0)),
                                        number_format_i18n((int) ($overview_pagination['total_rows'] ?? 0))
                                    )
                                );
                                ?>
                            </span>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <?php if (!empty($overview_pagination['prev_url'])): ?>
                                    <a href="<?php echo esc_url((string) $overview_pagination['prev_url']); ?>" class="button">Previous</a>
                                <?php endif; ?>
                                <?php foreach ($overview_page_links as $page_link): ?>
                                    <a href="<?php echo esc_url((string) ($page_link['url'] ?? $analytics_reset_url)); ?>" class="button<?php echo !empty($page_link['is_current']) ? ' button-primary' : ''; ?>">
                                        <?php echo esc_html(number_format_i18n((int) ($page_link['page'] ?? 1))); ?>
                                    </a>
                                <?php endforeach; ?>
                                <?php if (!empty($overview_pagination['next_url'])): ?>
                                    <a href="<?php echo esc_url((string) $overview_pagination['next_url']); ?>" class="button">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="cbt-analytics-empty">Belum ada exam yang bisa ditampilkan pada scope filter saat ini.</div>
                <?php endif; ?>
            </section>
        </section>

        <section class="cbt-analytics-tab-panel<?php echo $current_tab === 'exam' ? ' is-active' : ''; ?>" data-analytics-panel="exam">
            <?php if (!$has_selected_exam): ?>
                <div class="cbt-analytics-empty">Pilih satu exam terlebih dahulu untuk melihat analitik detail per exam.</div>
            <?php elseif (empty($has_run_analytics)): ?>
                <div class="cbt-analytics-empty">Pilih exam yang diinginkan lalu tekan tombol <strong>Analytic</strong> untuk memulai analytics detail.</div>
            <?php else: ?>
                <section class="cbt-analytics-panel">
                    <div class="cbt-analytics-panel-header">
                        <div>
                            <h2><?php echo esc_html((string) ($exam_meta['title'] ?? 'Exam')); ?></h2>
                            <p><?php echo esc_html((string) ($exam_meta['subject_name'] ?? 'Tanpa subject')); ?></p>
                        </div>
                        <div class="cbt-analytics-chip-row">
                            <span class="cbt-analytics-chip">KKM <?php echo esc_html((string) ($exam_meta['kkm_percentage_display'] ?? '75.00')); ?>%</span>
                            <span class="cbt-analytics-chip">Max Score <?php echo esc_html((string) ($exam_meta['current_max_score_display'] ?? '0.00')); ?></span>
                            <span class="cbt-analytics-chip">Batas Lulus <?php echo esc_html((string) ($exam_meta['passing_score_display'] ?? '0.00')); ?></span>
                            <span class="cbt-analytics-chip"><?php echo esc_html(number_format_i18n((int) ($exam_meta['total_questions'] ?? 0))); ?> soal aktif</span>
                            <?php if (!empty($exam_summary['has_temporary_status'])): ?>
                                <span class="cbt-analytics-chip is-warning">Hasil sementara</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cbt-analytics-metric-grid">
                        <article class="cbt-analytics-metric-card">
                            <span>Completed Attempts</span>
                            <strong><?php echo esc_html(number_format_i18n((int) ($exam_summary['completed_attempts'] ?? 0))); ?></strong>
                        </article>
                        <article class="cbt-analytics-metric-card">
                            <span>Average %</span>
                            <strong><?php echo esc_html((string) ($exam_summary['average_percentage_display'] ?? '0.00%')); ?></strong>
                        </article>
                        <article class="cbt-analytics-metric-card">
                            <span>Median %</span>
                            <strong><?php echo esc_html((string) ($exam_summary['median_percentage_display'] ?? '0.00%')); ?></strong>
                        </article>
                        <article class="cbt-analytics-metric-card">
                            <span>Pass Rate</span>
                            <strong><?php echo esc_html((string) ($exam_summary['pass_rate_display'] ?? '0.00%')); ?></strong>
                        </article>
                    </div>

                    <div class="cbt-analytics-grid-3" style="margin-top:16px;">
                        <article class="cbt-analytics-metric-card">
                            <span>Highest %</span>
                            <strong><?php echo esc_html((string) ($exam_summary['highest_percentage_display'] ?? '0.00%')); ?></strong>
                        </article>
                        <article class="cbt-analytics-metric-card">
                            <span>Lowest %</span>
                            <strong><?php echo esc_html((string) ($exam_summary['lowest_percentage_display'] ?? '0.00%')); ?></strong>
                        </article>
                        <article class="cbt-analytics-metric-card">
                            <span>Average Score</span>
                            <strong><?php echo esc_html((string) ($exam_summary['average_score_display'] ?? '0.00')); ?></strong>
                        </article>
                    </div>

                    <div class="cbt-analytics-grid-2" style="margin-top:16px;">
                        <section class="cbt-analytics-inline-card cbt-analytics-quality-card">
                            <div class="cbt-analytics-quality-heading">
                                <div>
                                    <span class="cbt-analytics-quality-kicker">Kualitas Soal Objective</span>
                                    <h3>Kualitas Soal Objective</h3>
                                </div>
                                <div class="cbt-analytics-chip-row">
                                    <span class="cbt-analytics-chip <?php echo esc_attr((string) (($exam_quality['reliability_tone'] ?? 'neutral') === 'pass' ? 'is-pass' : (($exam_quality['reliability_tone'] ?? 'neutral') === 'fail' ? 'is-fail' : 'is-warning'))); ?>">
                                        <?php echo esc_html((string) ($exam_quality['reliability_label'] ?? 'Insufficient Data')); ?>
                                    </span>
                                    <span class="cbt-analytics-chip"><?php echo esc_html($quality_reliability_method); ?></span>
                                    <span class="cbt-analytics-chip"><?php echo esc_html($quality_profile_label); ?></span>
                                </div>
                            </div>

                            <p class="cbt-analytics-quality-note"><?php echo esc_html($quality_note_text); ?></p>

                            <div class="cbt-analytics-chip-row">
                                <span class="cbt-analytics-chip">Variance <?php echo esc_html((string) ($exam_quality['variance_display'] ?? 'Insufficient Data')); ?></span>
                                <span class="cbt-analytics-chip"><?php echo esc_html(number_format_i18n($quality_eligible_item_count)); ?> objective items</span>
                                <span class="cbt-analytics-chip"><?php echo esc_html(number_format_i18n($quality_eligible_attempt_count)); ?> eligible attempts</span>
                                <span class="cbt-analytics-chip">Objective Avg <?php echo esc_html($quality_objective_avg_display); ?></span>
                            </div>

                            <div class="cbt-analytics-quality-tech">
                                <span class="cbt-analytics-quality-subtitle">Metrik Teknis</span>
                                <div class="cbt-analytics-grid-3">
                                    <article class="cbt-analytics-metric-card">
                                        <span>Reliability</span>
                                        <strong style="font-size:24px;"><?php echo esc_html((string) ($exam_quality['reliability_display'] ?? 'Insufficient Data')); ?></strong>
                                    </article>
                                    <article class="cbt-analytics-metric-card">
                                        <span>Std Dev</span>
                                        <strong style="font-size:24px;"><?php echo esc_html((string) ($exam_quality['standard_deviation_display'] ?? 'Insufficient Data')); ?></strong>
                                    </article>
                                    <article class="cbt-analytics-metric-card">
                                        <span>SEM</span>
                                        <strong style="font-size:24px;"><?php echo esc_html((string) ($exam_quality['sem_display'] ?? 'Insufficient Data')); ?></strong>
                                    </article>
                                </div>
                            </div>

                            <p class="cbt-analytics-quality-lead"><?php echo esc_html($quality_summary_text); ?></p>

                            <ul class="cbt-analytics-quality-summary-list">
                                <?php foreach ($quality_result_points as $quality_result_point): ?>
                                    <li><?php echo esc_html((string) $quality_result_point); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="cbt-analytics-quality-summary-next">
                                <strong>Arah Tindak Lanjut</strong>
                                <p><?php echo esc_html($quality_next_step_text); ?></p>
                            </div>
                        </section>

                        <section class="cbt-analytics-inline-card cbt-analytics-quality-admin-card">
                            <div class="cbt-analytics-quality-heading">
                                <div>
                                    <span class="cbt-analytics-quality-kicker">Diagnostics Admin</span>
                                    <h3>Diagnostics Admin</h3>
                                </div>
                                <div class="cbt-analytics-chip-row">
                                    <span class="cbt-analytics-chip"><?php echo esc_html(number_format_i18n((int) ($quality_counts['dichotomous_items'] ?? 0))); ?> dichotomous</span>
                                    <span class="cbt-analytics-chip"><?php echo esc_html(number_format_i18n((int) ($quality_counts['mixed_objective_items'] ?? 0))); ?> mixed</span>
                                    <span class="cbt-analytics-chip"><?php echo esc_html(number_format_i18n((int) ($quality_counts['excluded_items'] ?? 0))); ?> excluded</span>
                                </div>
                            </div>

                            <div class="cbt-analytics-quality-diagnostics-grid">
                                <article class="cbt-analytics-quality-diagnostic-card">
                                    <span class="cbt-analytics-quality-diagnostic-label">Komposisi Soal Objective</span>
                                    <strong><?php echo esc_html($quality_composition_label); ?></strong>
                                    <p><?php echo esc_html($quality_profile_reason !== '' ? $quality_profile_reason : 'Belum ada komposisi objective finalized yang layak dibaca.'); ?></p>
                                </article>

                                <article class="cbt-analytics-quality-diagnostic-card">
                                    <span class="cbt-analytics-quality-diagnostic-label">Metode yang Dipakai</span>
                                    <strong><?php echo esc_html($quality_reliability_method); ?></strong>
                                    <p><?php echo esc_html($quality_method_reason !== '' ? $quality_method_reason : 'Metode reliability akan dipilih setelah komposisi objective finalized layak dihitung.'); ?></p>
                                </article>

                                <article class="cbt-analytics-quality-diagnostic-card">
                                    <span class="cbt-analytics-quality-diagnostic-label">Yang Masuk ke Reliability</span>
                                    <?php if (!empty($quality_included_types)): ?>
                                        <ul class="cbt-analytics-quality-detail-list">
                                            <?php foreach ($quality_included_types as $included_type): ?>
                                                <li>
                                                    <strong><?php echo esc_html((string) ($included_type['label'] ?? '-')); ?></strong>
                                                    <span class="cbt-analytics-quality-detail-meta"><?php echo esc_html(number_format_i18n((int) ($included_type['count'] ?? 0))); ?> butir masuk reliability.</span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>Belum ada tipe soal objective finalized yang bisa masuk reliability saat ini.</p>
                                    <?php endif; ?>
                                </article>

                                <article class="cbt-analytics-quality-diagnostic-card">
                                    <span class="cbt-analytics-quality-diagnostic-label">Yang Dikeluarkan dari Reliability</span>
                                    <?php if (!empty($quality_excluded_types)): ?>
                                        <ul class="cbt-analytics-quality-detail-list">
                                            <?php foreach ($quality_excluded_types as $excluded_type): ?>
                                                <li>
                                                    <strong><?php echo esc_html((string) ($excluded_type['label'] ?? '-')); ?></strong>
                                                    <span class="cbt-analytics-quality-detail-meta">
                                                        <?php echo esc_html(number_format_i18n((int) ($excluded_type['count'] ?? 0))); ?> butir dikeluarkan.
                                                        <?php
                                                        $excluded_notes = array_values((array) ($excluded_type['notes'] ?? []));
                                                        if (!empty($excluded_notes)) {
                                                            echo ' ' . esc_html(implode(' ', array_map('strval', $excluded_notes)));
                                                        }
                                                        ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php elseif (!empty($quality_excluded_reasons)): ?>
                                        <ul class="cbt-analytics-quality-detail-list">
                                            <?php foreach ($quality_excluded_reasons as $excluded_reason): ?>
                                                <li>
                                                    <strong><?php echo esc_html((string) ($excluded_reason['reason'] ?? '-')); ?></strong>
                                                    <span class="cbt-analytics-quality-detail-meta"><?php echo esc_html(number_format_i18n((int) ($excluded_reason['count'] ?? 0))); ?> butir.</span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p>Tidak ada butir yang perlu dikeluarkan dari reliability pada scope ini.</p>
                                    <?php endif; ?>
                                </article>

                                <article class="cbt-analytics-quality-diagnostic-card">
                                    <span class="cbt-analytics-quality-diagnostic-label">Kenapa Metode/Fallback Ini Dipilih</span>
                                    <p><?php echo esc_html($quality_why_reason !== '' ? $quality_why_reason : 'Metode dan fallback akan dijelaskan otomatis setelah komposisi objective terbaca.'); ?></p>
                                    <p><?php echo esc_html(sprintf('Flag item saat ini: %1$d weak/inverse discrimination, %2$d high omission, %3$d pending manual.', (int) ($exam_item_flags['weak_discrimination_count'] ?? 0), (int) ($exam_item_flags['high_omission_count'] ?? 0), (int) ($exam_item_flags['pending_manual_count'] ?? 0))); ?></p>
                                </article>
                            </div>
                        </section>
                    </div>
                </section>

                <div class="cbt-analytics-grid-2">
                    <section class="cbt-analytics-panel">
                        <div class="cbt-analytics-panel-header">
                            <div>
                                <h2>Distribusi Nilai</h2>
                                <p>Bucket persentase untuk melihat persebaran performa peserta pada exam terpilih.</p>
                            </div>
                        </div>
                        <?php if (!empty($exam_distribution)): ?>
                            <div class="cbt-analytics-distribution">
                                <?php foreach ($exam_distribution as $bucket): ?>
                                    <div class="cbt-analytics-distribution-row">
                                        <strong><?php echo esc_html((string) ($bucket['label'] ?? '-')); ?></strong>
                                        <div class="cbt-analytics-distribution-bar">
                                            <span style="width: <?php echo esc_attr((string) max(0, min(100, (float) ($bucket['bar_width'] ?? 0.0)))); ?>%;"></span>
                                        </div>
                                        <span><?php echo esc_html(number_format_i18n((int) ($bucket['count'] ?? 0))); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="cbt-analytics-empty">Belum ada distribusi nilai karena belum ada completed attempt.</div>
                        <?php endif; ?>
                    </section>

                    <section class="cbt-analytics-panel">
                        <div class="cbt-analytics-panel-header">
                            <div>
                                <h2>Status Ketuntasan</h2>
                                <p>Ringkasan lulus, tidak lulus, dan jawaban manual yang masih menunggu koreksi.</p>
                            </div>
                        </div>
                        <div class="cbt-analytics-chip-row" style="margin-bottom:12px;">
                            <span class="cbt-analytics-chip is-pass">Lulus <?php echo esc_html(number_format_i18n((int) ($exam_summary['pass_count'] ?? 0))); ?></span>
                            <span class="cbt-analytics-chip is-fail">Tidak Lulus <?php echo esc_html(number_format_i18n((int) ($exam_summary['fail_count'] ?? 0))); ?></span>
                            <span class="cbt-analytics-chip is-warning">Essay Pending <?php echo esc_html(number_format_i18n((int) ($exam_summary['manual_review_count'] ?? 0))); ?></span>
                            <span class="cbt-analytics-chip">Archived Soal <?php echo esc_html(number_format_i18n((int) ($exam_meta['archived_question_count'] ?? 0))); ?></span>
                        </div>
                        <p class="cbt-analytics-muted">Pass rate mengikuti KKM exam ini, bukan angka global tetap. Jika masih ada esai/manual pending, status dihitung dari skor saat ini dan ditandai sebagai hasil sementara.</p>
                    </section>
                </div>

                <section class="cbt-analytics-panel">
                    <div class="cbt-analytics-panel-header">
                        <div>
                            <h2>Per Kelas</h2>
                            <p>Ringkasan performance per kelas untuk exam terpilih.</p>
                        </div>
                    </div>
                    <?php if (!empty($exam_kelas_summary)): ?>
                        <div class="cbt-analytics-table-wrap">
                            <table class="cbt-analytics-table">
                                <thead>
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Completed</th>
                                        <th>Average %</th>
                                        <th>Pass Rate</th>
                                        <th>Essay Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam_kelas_summary as $row): ?>
                                        <tr>
                                            <td><?php echo esc_html((string) ($row['kelas'] ?? '-')); ?></td>
                                            <td><?php echo esc_html(number_format_i18n((int) ($row['completed_attempts'] ?? 0))); ?></td>
                                            <td><?php echo esc_html((string) ($row['average_percentage_display'] ?? '0.00%')); ?></td>
                                            <td><?php echo esc_html((string) ($row['pass_rate_display'] ?? '0.00%')); ?></td>
                                            <td><?php echo esc_html(number_format_i18n((int) ($row['manual_review_count'] ?? 0))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="cbt-analytics-empty">Belum ada data kelas untuk exam terpilih.</div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </section>

        <section class="cbt-analytics-tab-panel<?php echo $current_tab === 'items' ? ' is-active' : ''; ?>" data-analytics-panel="items">
            <section class="cbt-analytics-panel">
                <div class="cbt-analytics-panel-header">
                    <div>
                        <h2>Item Analysis</h2>
                        <p>Analisis teknis per butir soal untuk exam terpilih: difficulty, omission, discrimination, dan insight spesifik per jenis soal.</p>
                    </div>
                    <?php if ($has_selected_exam): ?>
                        <div class="cbt-analytics-chip-row">
                            <span class="cbt-analytics-chip"><?php echo esc_html((string) ($selected_exam['title'] ?? 'Exam')); ?></span>
                            <span class="cbt-analytics-chip"><?php echo esc_html((string) ($selected_exam['subject_name'] ?? 'Tanpa subject')); ?></span>
                            <span class="cbt-analytics-chip is-warning"><?php echo esc_html(number_format_i18n((int) ($item_analysis_summary['weak_discrimination_count'] ?? 0))); ?> weak/inverse</span>
                            <span class="cbt-analytics-chip is-warning"><?php echo esc_html(number_format_i18n((int) ($item_analysis_summary['high_omission_count'] ?? 0))); ?> high omission</span>
                            <span class="cbt-analytics-chip is-warning"><?php echo esc_html(number_format_i18n((int) ($item_analysis_summary['pending_manual_count'] ?? 0))); ?> pending manual</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="cbt-analytics-mini-glossary">
                    <article class="cbt-analytics-mini-glossary-item">
                        <strong>Difficulty</strong>
                        <p>Tingkat kesulitan soal. Semakin tinggi jawaban benar, biasanya soal makin mudah.</p>
                    </article>
                    <article class="cbt-analytics-mini-glossary-item">
                        <strong>Omission</strong>
                        <p>Persentase peserta yang membiarkan soal kosong atau tidak terjawab.</p>
                    </article>
                    <article class="cbt-analytics-mini-glossary-item">
                        <strong>Discrimination</strong>
                        <p>Kemampuan soal membedakan peserta kuat dan lemah. Nilai tinggi berarti soal lebih informatif.</p>
                    </article>
                    <article class="cbt-analytics-mini-glossary-item">
                        <strong>Insight / Manual</strong>
                        <p><em>Insight</em> adalah ringkasan prioritas utama dari sinyal soal. <em>Manual</em> berarti hasil masih dipengaruhi koreksi guru yang belum final.</p>
                    </article>
                </div>
                <?php if (!$has_selected_exam): ?>
                    <div class="cbt-analytics-empty">Pilih satu exam terlebih dahulu agar analisis butir soal bisa ditampilkan.</div>
                <?php elseif (empty($has_run_analytics)): ?>
                    <div class="cbt-analytics-empty">Tekan tombol <strong>Analytic</strong> untuk menghitung item analysis pada exam terpilih.</div>
                <?php else: ?>
                    <div id="cbt-analytics-items-app"></div>
                <?php endif; ?>
            </section>
        </section>

        <section class="cbt-analytics-tab-panel<?php echo $current_tab === 'students' ? ' is-active' : ''; ?>" data-analytics-panel="students">
            <section class="cbt-analytics-panel">
                <div class="cbt-analytics-panel-header">
                    <div>
                        <h2>Student Drilldown</h2>
                        <p>Daftar siswa untuk exam terpilih dengan skor akhir, objective percentage, group band, dan detail inline yang mengarah cepat ke halaman results bila dibutuhkan.</p>
                    </div>
                    <?php if ($has_selected_exam): ?>
                        <div class="cbt-analytics-chip-row">
                            <span class="cbt-analytics-chip"><?php echo esc_html((string) ($selected_exam['title'] ?? 'Exam')); ?></span>
                            <span class="cbt-analytics-chip"><?php echo esc_html((string) ($selected_exam['subject_name'] ?? 'Tanpa subject')); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!$has_selected_exam): ?>
                    <div class="cbt-analytics-empty">Pilih satu exam terlebih dahulu agar drilldown siswa bisa ditampilkan.</div>
                <?php elseif (empty($has_run_analytics)): ?>
                    <div class="cbt-analytics-empty">Tekan tombol <strong>Analytic</strong> untuk menampilkan drilldown siswa pada exam terpilih.</div>
                <?php else: ?>
                    <div id="cbt-analytics-students-app"></div>
                <?php endif; ?>
            </section>
        </section>
    </div>

    <script>
        (function () {
            const activeTabInput = document.getElementById('cbt-analytics-active-tab');
            const tabButtons = Array.from(document.querySelectorAll('.cbt-analytics-tab'));
            const tabPanels = Array.from(document.querySelectorAll('.cbt-analytics-tab-panel'));
            const filterForm = document.getElementById('cbt-analytics-filter-form');

            function setActiveTab(tabName) {
                const nextTab = String(tabName || 'overview');
                tabButtons.forEach((button) => {
                    const isActive = button.getAttribute('data-analytics-tab') === nextTab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                tabPanels.forEach((panel) => {
                    panel.classList.toggle('is-active', panel.getAttribute('data-analytics-panel') === nextTab);
                });
                if (activeTabInput) {
                    activeTabInput.value = nextTab;
                }
            }

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    setActiveTab(button.getAttribute('data-analytics-tab') || 'overview');
                });
            });
            if (filterForm) {
                Array.from(filterForm.querySelectorAll('[data-submit-tab]')).forEach((button) => {
                    button.addEventListener('click', () => {
                        setActiveTab(button.getAttribute('data-submit-tab') || 'overview');
                    });
                });

                Array.from(filterForm.querySelectorAll('[data-auto-submit]')).forEach((field) => {
                    field.addEventListener('change', () => {
                        setActiveTab('overview');
                        if (typeof filterForm.requestSubmit === 'function') {
                            filterForm.requestSubmit();
                            return;
                        }
                        filterForm.submit();
                    });
                });
            }

            setActiveTab(<?php echo wp_json_encode($current_tab); ?>);

            function escapeHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function createBadge(label, tone) {
                const toneClass = {
                    pass: 'is-pass',
                    fail: 'is-fail',
                    easy: 'is-easy',
                    medium: 'is-medium',
                    hard: 'is-hard',
                    manual: 'is-manual',
                    warning: 'is-warning',
                    ok: 'is-ok',
                    neutral: 'is-neutral'
                }[String(tone || '')] || 'is-ok';
                return '<span class="cbt-analytics-badge ' + toneClass + '">' + escapeHtml(label) + '</span>';
            }

            function buildInsightTooltipTitle(row) {
                return [
                    String(row.insight_display_label || row.insight_label || '-'),
                    String(row.insight_short_explainer || ''),
                    String(row.insight_reason_detail || '')
                ].filter(Boolean).join(' — ');
            }

            function buildInsightCell(row) {
                const insightLabel = String(row.insight_display_label || row.insight_label || '-');
                const difficultyLabel = String(row.difficulty_label || '-');
                const tooltipTitle = buildInsightTooltipTitle(row);
                const tooltipParts = [
                    '<div class="cbt-analytics-insight-tooltip" role="tooltip">',
                    '<strong>' + escapeHtml(insightLabel) + '</strong>'
                ];

                if (row.insight_short_explainer) {
                    tooltipParts.push('<p>' + escapeHtml(String(row.insight_short_explainer)) + '</p>');
                }
                if (row.insight_reason_detail) {
                    tooltipParts.push('<p><strong>Kenapa muncul:</strong> ' + escapeHtml(String(row.insight_reason_detail)) + '</p>');
                }

                tooltipParts.push('</div>');

                return '' +
                    '<div class="cbt-analytics-insight-cell" title="' + escapeHtml(tooltipTitle) + '">' +
                        '<div class="cbt-analytics-insight-badges">' +
                            createBadge(insightLabel, row.insight_tone || '') +
                            '<span class="cbt-analytics-muted">' + createBadge(difficultyLabel, row.difficulty_tone || '') + '</span>' +
                        '</div>' +
                        '<button type="button" class="cbt-analytics-insight-help" aria-label="' + escapeHtml('Lihat penjelasan insight soal #' + String(row.question_number || '-')) + '" title="' + escapeHtml(tooltipTitle) + '">?</button>' +
                        tooltipParts.join('') +
                    '</div>';
            }

            function buildPaginationMeta(total, page, perPage) {
                if (total <= 0) {
                    return 'Tidak ada data.';
                }
                const start = ((page - 1) * perPage) + 1;
                const end = Math.min(total, start + perPage - 1);
                return 'Menampilkan ' + start + '-' + end + ' dari ' + total + ' data.';
            }

            function normalizeRows(rows) {
                return Array.isArray(rows) ? rows : [];
            }

            function initInlineTable(config) {
                const root = document.getElementById(config.rootId);
                if (!root) {
                    return;
                }

                const state = {
                    query: '',
                    page: 1,
                    perPage: config.defaultPerPage || 10,
                    expandedId: null
                };
                const rows = normalizeRows(config.rows);

                function getFilteredRows() {
                    const query = state.query.trim().toLowerCase();
                    if (!query) {
                        return rows.slice();
                    }
                    return rows.filter((row) => String(row.search_text || '').indexOf(query) !== -1);
                }

                function render() {
                    const filtered = getFilteredRows();
                    const total = filtered.length;
                    const totalPages = Math.max(1, Math.ceil(total / state.perPage));
                    if (state.page > totalPages) {
                        state.page = totalPages;
                    }

                    const start = (state.page - 1) * state.perPage;
                    const visibleRows = filtered.slice(start, start + state.perPage);

                    root.innerHTML = config.renderFrame({
                        meta: buildPaginationMeta(total, state.page, state.perPage),
                        page: state.page,
                        totalPages: totalPages
                    });

                    const searchInput = root.querySelector('[data-role="search"]');
                    const perPageSelect = root.querySelector('[data-role="per-page"]');
                    const prevButton = root.querySelector('[data-role="prev"]');
                    const nextButton = root.querySelector('[data-role="next"]');
                    const tbody = root.querySelector('tbody[data-role="rows"]');

                    if (searchInput) {
                        searchInput.value = state.query;
                        searchInput.addEventListener('input', function () {
                            state.query = this.value || '';
                            state.page = 1;
                            state.expandedId = null;
                            render();
                        });
                    }

                    if (perPageSelect) {
                        perPageSelect.value = String(state.perPage);
                        perPageSelect.addEventListener('change', function () {
                            const nextValue = parseInt(this.value || '10', 10);
                            state.perPage = Number.isFinite(nextValue) && nextValue > 0 ? nextValue : 10;
                            state.page = 1;
                            state.expandedId = null;
                            render();
                        });
                    }

                    if (prevButton) {
                        prevButton.disabled = state.page <= 1;
                        prevButton.addEventListener('click', function () {
                            if (state.page <= 1) {
                                return;
                            }
                            state.page -= 1;
                            state.expandedId = null;
                            render();
                        });
                    }

                    if (nextButton) {
                        nextButton.disabled = state.page >= totalPages;
                        nextButton.addEventListener('click', function () {
                            if (state.page >= totalPages) {
                                return;
                            }
                            state.page += 1;
                            state.expandedId = null;
                            render();
                        });
                    }

                    if (!tbody) {
                        return;
                    }

                    if (!visibleRows.length) {
                        tbody.innerHTML = '<tr><td colspan="' + config.columnCount + '" class="cbt-analytics-muted">Tidak ada data yang cocok dengan filter/search saat ini.</td></tr>';
                        return;
                    }

                    const fragments = [];
                    visibleRows.forEach((row) => {
                        const rowId = String(config.getRowId(row));
                        const isExpanded = state.expandedId === rowId;
                        fragments.push(config.renderRow(row, isExpanded));
                        if (isExpanded) {
                            fragments.push(config.renderDetailRow(row, config.columnCount));
                        }
                    });
                    tbody.innerHTML = fragments.join('');

                    Array.from(tbody.querySelectorAll('[data-action="toggle-detail"]')).forEach((button) => {
                        button.addEventListener('click', function () {
                            const rowId = this.getAttribute('data-row-id') || '';
                            state.expandedId = state.expandedId === rowId ? null : rowId;
                            render();
                        });
                    });
                }

                render();
            }

            initInlineTable({
                rootId: 'cbt-analytics-items-app',
                rows: <?php echo $item_rows_json ?: '[]'; ?>,
                defaultPerPage: 10,
                columnCount: 14,
                getRowId: function (row) {
                    return row.question_id || Math.random().toString(36);
                },
                renderFrame: function (ctx) {
                    return '' +
                        '<div class="cbt-analytics-toolbar">' +
                            '<div class="cbt-analytics-field is-search">' +
                                '<label for="cbt-analytics-items-search">Search</label>' +
                                '<input id="cbt-analytics-items-search" type="search" data-role="search" placeholder="Cari nomor, tipe, preview soal, atau difficulty...">' +
                            '</div>' +
                            '<div class="cbt-analytics-field">' +
                                '<label for="cbt-analytics-items-per-page">Per Page</label>' +
                                '<select id="cbt-analytics-items-per-page" data-role="per-page">' +
                                    '<option value="10">10</option>' +
                                    '<option value="20">20</option>' +
                                    '<option value="50">50</option>' +
                                '</select>' +
                            '</div>' +
                        '</div>' +
                        '<div class="cbt-analytics-table-wrap">' +
                            '<table class="cbt-analytics-table is-dense-head">' +
                                '<thead><tr>' +
                                    '<th>No</th><th>Tipe</th><th>Poin</th><th>Dijawab</th><th>Benar</th><th>Salah</th><th>Kosong</th><th>Manual</th><th>Correct Rate</th><th>Omission</th><th>Discrimination</th><th>Avg Score</th><th>Insight</th><th>Detail</th>' +
                                '</tr></thead>' +
                                '<tbody data-role="rows"></tbody>' +
                            '</table>' +
                        '</div>' +
                        '<div class="cbt-analytics-pagination">' +
                            '<span class="cbt-analytics-pagination-meta">' + escapeHtml(ctx.meta) + '</span>' +
                            '<div style="display:flex; gap:8px;">' +
                                '<button type="button" class="button" data-role="prev">Previous</button>' +
                                '<button type="button" class="button" data-role="next">Next</button>' +
                            '</div>' +
                        '</div>';
                },
                renderRow: function (row, isExpanded) {
                    return '' +
                        '<tr' + (isExpanded ? ' class="is-expanded"' : '') + '>' +
                            '<td>#' + escapeHtml(row.question_number || '-') + '</td>' +
                            '<td><strong>' + escapeHtml(row.question_type_label || '-') + '</strong></td>' +
                            '<td>' + escapeHtml(row.points_display || '0.00') + '</td>' +
                            '<td>' + escapeHtml(row.answered_count || 0) + '</td>' +
                            '<td>' + escapeHtml(row.correct_count || 0) + '</td>' +
                            '<td>' + escapeHtml(row.wrong_count || 0) + '</td>' +
                            '<td>' + escapeHtml(row.unanswered_count || 0) + '</td>' +
                            '<td>' + escapeHtml(row.manual_count || 0) + '</td>' +
                            '<td>' + escapeHtml(row.correct_rate_display || '0.00%') + '</td>' +
                            '<td>' + createBadge(row.omission_label || '-', row.omission_tone || '') + '<br><span class="cbt-analytics-muted">' + escapeHtml(row.omission_rate_display || '0.00%') + '</span></td>' +
                            '<td>' + createBadge(row.discrimination_label || '-', row.discrimination_tone || '') + '<br><span class="cbt-analytics-muted">' + escapeHtml(row.discrimination_display || 'Insufficient Data') + '</span></td>' +
                            '<td>' + escapeHtml(row.average_awarded_score_display || '0.00') + '</td>' +
                            '<td>' + buildInsightCell(row) + '</td>' +
                            '<td><button type="button" class="button button-small" data-action="toggle-detail" data-row-id="' + escapeHtml(row.question_id || '') + '">' + (isExpanded ? 'Hide' : 'View') + '</button></td>' +
                        '</tr>';
                },
                renderDetailRow: function (row, columnCount) {
                    const optionAnalysis = Array.isArray(row.option_analysis) && row.option_analysis.length
                        ? (function () {
                            const header = (row.question_type === 'multiple_answer') ? 'Option Selection Analysis' : 'Distractor Analysis';
                            const rows = row.option_analysis.map(function (item) {
                                const flagsList = Array.isArray(item.flags_display) && item.flags_display.length
                                    ? item.flags_display.filter(Boolean)
                                    : (Array.isArray(item.flags) ? item.flags.filter(Boolean) : []);
                                const roleBadge = item.is_correct
                                    ? createBadge('Opsi Benar', 'pass')
                                    : createBadge(row.question_type === 'multiple_answer' ? 'Opsi Pilihan' : 'Distraktor', 'neutral');
                                const flags = flagsList.length
                                    ? '<div class="cbt-analytics-inline-chip-list">' + flagsList.map(function (flag) {
                                        return createBadge(flag, 'warning');
                                    }).join(' ') + '</div>'
                                    : '<span class="cbt-analytics-muted">Tidak ada flag khusus.</span>';
                                const missValue = (item.missed_correct_option_rate !== null && item.missed_correct_option_rate !== undefined)
                                    ? escapeHtml(Number(item.missed_correct_option_rate).toFixed(2) + '%')
                                    : '-';
                                const falseValue = (item.false_selection_rate !== null && item.false_selection_rate !== undefined)
                                    ? escapeHtml(Number(item.false_selection_rate).toFixed(2) + '%')
                                    : '-';
                                const selectionRate = String(item.selection_rate_display || '0.00%');
                                const selectionWidth = Math.max(0, Math.min(100, parseFloat(selectionRate) || 0));
                                const insightText = item.is_correct
                                    ? 'Ini opsi jawaban yang benar untuk butir ini.'
                                    : 'Ini opsi pengecoh yang dibandingkan terhadap jawaban benar.';
                                return '' +
                                    '<tr>' +
                                        '<td><div class="cbt-analytics-table-cell-stack"><strong>' + escapeHtml(item.label || '-') + '</strong>' + (item.is_correct ? createBadge('Correct', 'pass') : '') + '</div></td>' +
                                        '<td><div class="cbt-analytics-table-cell-stack"><div class="cbt-analytics-inline-metrics"><span><strong>' + escapeHtml(item.count || 0) + '</strong></span><span class="cbt-analytics-table-cell-meta">' + escapeHtml(selectionRate) + ' dipilih</span></div><div class="cbt-analytics-mini-bar"><span style="width:' + String(selectionWidth) + '%;"></span></div></div></td>' +
                                        '<td><div class="cbt-analytics-inline-metrics"><span><strong>Upper:</strong> ' + escapeHtml(item.upper_rate_display || '0.00%') + '</span><span><strong>Lower:</strong> ' + escapeHtml(item.lower_rate_display || '0.00%') + '</span></div></td>' +
                                        '<td><div class="cbt-analytics-table-cell-stack"><div class="cbt-analytics-inline-chip-list">' + roleBadge + '</div><span class="cbt-analytics-table-cell-meta">' + insightText + '</span><div class="cbt-analytics-inline-metrics"><span><strong>Missed Correct:</strong> ' + missValue + '</span><span><strong>False Select:</strong> ' + falseValue + '</span></div><div>' + flags + '</div></div></td>' +
                                    '</tr>';
                            }).join('');
                            return '' +
                                '<div class="cbt-analytics-inline-card">' +
                                    '<strong>' + header + '</strong>' +
                                    '<div class="cbt-analytics-inline-glossary">' +
                                        '<div class="cbt-analytics-inline-glossary-item">' +
                                            '<span class="cbt-analytics-inline-glossary-kicker">Group Signal</span>' +
                                            '<strong>Upper / Lower</strong>' +
                                            '<span>Perbandingan pilihan antara kelompok nilai atas dan bawah untuk melihat kualitas opsi.</span>' +
                                            '<div class="cbt-analytics-inline-glossary-points">' +
                                                '<div class="cbt-analytics-inline-glossary-point"><span><strong>Upper</strong> adalah persentase kelompok nilai atas yang memilih opsi ini.</span></div>' +
                                                '<div class="cbt-analytics-inline-glossary-point"><span><strong>Lower</strong> adalah persentase kelompok nilai bawah yang memilih opsi ini.</span></div>' +
                                                '<div class="cbt-analytics-inline-glossary-point"><span>Jika opsi benar lebih tinggi di <strong>Upper</strong>, soal biasanya membedakan peserta kuat dengan baik.</span></div>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="cbt-analytics-inline-glossary-item">' +
                                            '<span class="cbt-analytics-inline-glossary-kicker">Option Insight</span>' +
                                            '<strong>Insight</strong>' +
                                            '<span>Ringkasan peran opsi dan sinyal apakah opsi itu sehat atau bermasalah.</span>' +
                                            '<div class="cbt-analytics-inline-glossary-points">' +
                                                '<div class="cbt-analytics-inline-glossary-point"><span><strong>Opsi Benar / Distraktor</strong> menjelaskan apakah opsi adalah jawaban benar atau pengecoh.</span></div>' +
                                                '<div class="cbt-analytics-inline-glossary-point"><span><strong>Missed Correct</strong> menunjukkan peserta yang melewatkan opsi benar yang seharusnya dipilih.</span></div>' +
                                                '<div class="cbt-analytics-inline-glossary-point"><span><strong>False Select</strong> menunjukkan peserta yang memilih opsi salah ini.</span></div>' +
                                                '<div class="cbt-analytics-inline-glossary-point"><span>Flag seperti <strong>Attractive Distractor</strong> atau <strong>Non-Functioning Distractor</strong> membantu menilai kualitas pengecoh.</span></div>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>' +
                                    '<div class="cbt-analytics-table-wrap">' +
                                        '<table class="cbt-analytics-table is-compact is-option-analysis">' +
                                            '<colgroup><col class="cbt-col-option"><col class="cbt-col-selected"><col class="cbt-col-band"><col class="cbt-col-insight"></colgroup>' +
                                            '<thead><tr><th>Opsi</th><th>Dipilih</th><th>Upper / Lower</th><th>Insight</th></tr></thead>' +
                                            '<tbody>' + rows + '</tbody>' +
                                        '</table>' +
                                    '</div>' +
                                '</div>';
                        }())
                        : '';

                    const matrixAnalysis = Array.isArray(row.matrix_analysis_rows) && row.matrix_analysis_rows.length
                        ? (function () {
                            const rows = row.matrix_analysis_rows.map(function (item) {
                                return '' +
                                    '<tr>' +
                                        '<td>#' + escapeHtml(item.statement_number || 0) + '</td>' +
                                        '<td>' + escapeHtml(item.statement_text || '-') + '</td>' +
                                        '<td>' + escapeHtml(item.correct_answer || '-') + '</td>' +
                                        '<td>' + escapeHtml(item.correct_rate_display || '0.00%') + '</td>' +
                                        '<td>' + escapeHtml(item.wrong_rate_display || '0.00%') + '</td>' +
                                        '<td>' + escapeHtml(item.omission_rate_display || '0.00%') + '</td>' +
                                    '</tr>';
                            }).join('');
                            return '' +
                                '<div class="cbt-analytics-inline-card">' +
                                    '<strong>Matrix Sub-Item Analysis</strong>' +
                                    '<div class="cbt-analytics-table-wrap">' +
                                        '<table class="cbt-analytics-table">' +
                                            '<thead><tr><th>No</th><th>Pernyataan</th><th>Kunci</th><th>Correct</th><th>Wrong</th><th>Omission</th></tr></thead>' +
                                            '<tbody>' + rows + '</tbody>' +
                                        '</table>' +
                                    '</div>' +
                                '</div>';
                        }())
                        : '';

                    const hasShortAnswerInsight = row.question_type === 'short_answer'
                        && row.short_answer_analysis
                        && typeof row.short_answer_analysis === 'object'
                        && (
                            Number(row.short_answer_analysis.accepted_match_rate || 0) > 0
                            || (Array.isArray(row.short_answer_analysis.top_wrong_responses) && row.short_answer_analysis.top_wrong_responses.length > 0)
                        );

                    const shortAnswerAnalysis = hasShortAnswerInsight
                        ? (function () {
                            const wrongResponses = Array.isArray(row.short_answer_analysis.top_wrong_responses) && row.short_answer_analysis.top_wrong_responses.length
                                ? '<ul>' + row.short_answer_analysis.top_wrong_responses.map(function (item) {
                                    return '<li>' + escapeHtml(item.label || '-') + ' (' + escapeHtml(item.count || 0) + ')</li>';
                                }).join('') + '</ul>'
                                : '<p>Tidak ada wrong cluster yang signifikan.</p>';
                            return '' +
                                '<div class="cbt-analytics-inline-card">' +
                                    '<strong>Short Answer Insight</strong>' +
                                    '<p>Accepted Match Rate: ' + escapeHtml((row.short_answer_analysis && row.short_answer_analysis.accepted_match_rate_display) || '0.00%') + '</p>' +
                                    '<strong style="margin-top:10px;">Top Wrong Responses</strong>' +
                                    wrongResponses +
                                '</div>';
                        }())
                        : '';

                    const hasEssayInsight = row.question_type === 'essay'
                        && row.essay_analysis
                        && typeof row.essay_analysis === 'object'
                        && (
                            Number(row.essay_analysis.submission_rate || 0) > 0
                            || Number(row.essay_analysis.pending_manual_review || 0) > 0
                            || Number(row.essay_analysis.average_awarded_score || 0) > 0
                        );

                    const essayAnalysis = hasEssayInsight
                        ? (function () {
                            return '' +
                                '<div class="cbt-analytics-inline-card">' +
                                    '<strong>Essay Manual Insight</strong>' +
                                    '<p>Submission Rate: ' + escapeHtml((row.essay_analysis && row.essay_analysis.submission_rate_display) || '0.00%') + '<br>' +
                                    'Pending Manual Review: ' + escapeHtml((row.essay_analysis && row.essay_analysis.pending_manual_review) || 0) + '<br>' +
                                    'Average Awarded Score: ' + escapeHtml((row.essay_analysis && row.essay_analysis.average_awarded_score_display) || '0.00') + '<br>' +
                                    'Score Spread: ' + escapeHtml((row.essay_analysis && row.essay_analysis.score_spread_display) || '0.00 - 0.00') + '</p>' +
                                '</div>';
                        }())
                        : '';

                    const noteHtml = row.note
                        ? '<div class="cbt-analytics-inline-card"><strong>Catatan</strong><p>' + escapeHtml(row.note) + '</p></div>'
                        : '';

                    return '' +
                        '<tr class="cbt-analytics-inline-detail-row">' +
                            '<td colspan="' + columnCount + '">' +
                                '<div class="cbt-analytics-inline-detail-wrap">' +
                                    '<div class="cbt-analytics-inline-detail">' +
                                        '<div class="cbt-analytics-inline-detail-grid">' +
                                            '<div class="cbt-analytics-inline-card is-insight-summary">' +
                                                '<strong>Ringkasan Insight</strong>' +
                                                '<div class="cbt-analytics-inline-chip-list">' +
                                                    createBadge(row.insight_display_label || row.insight_label || '-', row.insight_tone || '') +
                                                    createBadge(row.difficulty_label || '-', row.difficulty_tone || '') +
                                                '</div>' +
                                                '<p><strong>Status:</strong> ' + escapeHtml(row.insight_display_label || row.insight_label || '-') + '</p>' +
                                                '<p><strong>Apa artinya:</strong> ' + escapeHtml(row.insight_short_explainer || '-') + '</p>' +
                                                '<p><strong>Kenapa muncul:</strong> ' + escapeHtml(row.insight_reason_detail || '-') + '</p>' +
                                                '<p><strong>Tindakan yang disarankan:</strong> ' + escapeHtml(row.insight_next_step || '-') + '</p>' +
                                            '</div>' +
                                            '<div class="cbt-analytics-inline-card">' +
                                                '<strong>Preview Soal</strong>' +
                                                '<p>' + escapeHtml(row.question_text || row.question_preview || '-') + '</p>' +
                                            '</div>' +
                                        '</div>' +
                                        '<div class="cbt-analytics-inline-detail-grid">' +
                                            '<div class="cbt-analytics-inline-card">' +
                                                '<strong>Jawaban Benar / Kunci</strong>' +
                                                '<p>' + escapeHtml(row.correct_answer_summary || '-') + '</p>' +
                                            '</div>' +
                                            '<div class="cbt-analytics-inline-card">' +
                                                '<strong>Technical Metrics</strong>' +
                                                '<p>Difficulty: ' + escapeHtml(row.difficulty_label || '-') + ' - ' + escapeHtml(row.difficulty_short_explainer || '-') + '<br>' +
                                                'Omission Rate: ' + escapeHtml(row.omission_rate_display || '0.00%') + ' (' + escapeHtml(row.omission_label || '-') + ')<br>' +
                                                'Discrimination: ' + escapeHtml(row.discrimination_display || 'Insufficient Data') + ' (' + escapeHtml(row.discrimination_label || '-') + ')<br>' +
                                                'Insight: ' + escapeHtml(row.insight_display_label || row.insight_label || '-') + '<br>' +
                                                'Effective Max Score: ' + escapeHtml(row.effective_max_score_display || row.points_display || '0.00') + '</p>' +
                                            '</div>' +
                                            '<div class="cbt-analytics-inline-card">' +
                                                '<strong>Attempt Coverage</strong>' +
                                                '<p>Dilihat: ' + escapeHtml(row.seen_count || 0) + '<br>' +
                                                'Dijawab: ' + escapeHtml(row.answered_count || 0) + '<br>' +
                                                'Benar/Salah/Kosong: ' + escapeHtml(row.correct_count || 0) + ' / ' + escapeHtml(row.wrong_count || 0) + ' / ' + escapeHtml(row.unanswered_count || 0) + '</p>' +
                                            '</div>' +
                                        '</div>' +
                                        optionAnalysis +
                                        matrixAnalysis +
                                        shortAnswerAnalysis +
                                        essayAnalysis +
                                        noteHtml +
                                    '</div>' +
                                '</div>' +
                            '</td>' +
                        '</tr>';
                }
            });

            initInlineTable({
                rootId: 'cbt-analytics-students-app',
                rows: <?php echo $student_rows_json ?: '[]'; ?>,
                defaultPerPage: 10,
                columnCount: 11,
                getRowId: function (row) {
                    return row.attempt_id || Math.random().toString(36);
                },
                renderFrame: function (ctx) {
                    return '' +
                        '<div class="cbt-analytics-toolbar">' +
                            '<div class="cbt-analytics-field is-search">' +
                                '<label for="cbt-analytics-students-search">Search</label>' +
                                '<input id="cbt-analytics-students-search" type="search" data-role="search" placeholder="Cari nama, username, NISN, kelas, atau status...">' +
                            '</div>' +
                            '<div class="cbt-analytics-field">' +
                                '<label for="cbt-analytics-students-per-page">Per Page</label>' +
                                '<select id="cbt-analytics-students-per-page" data-role="per-page">' +
                                    '<option value="10">10</option>' +
                                    '<option value="20">20</option>' +
                                    '<option value="50">50</option>' +
                                '</select>' +
                            '</div>' +
                        '</div>' +
                        '<div class="cbt-analytics-table-wrap">' +
                            '<table class="cbt-analytics-table">' +
                                '<thead><tr>' +
                                    '<th>Nama</th><th>Kelas</th><th>Skor</th><th>Persentase</th><th>Objective %</th><th>Band</th><th>Status</th><th>Jawaban</th><th>Durasi</th><th>Selesai</th><th>Detail</th>' +
                                '</tr></thead>' +
                                '<tbody data-role="rows"></tbody>' +
                            '</table>' +
                        '</div>' +
                        '<div class="cbt-analytics-pagination">' +
                            '<span class="cbt-analytics-pagination-meta">' + escapeHtml(ctx.meta) + '</span>' +
                            '<div style="display:flex; gap:8px;">' +
                                '<button type="button" class="button" data-role="prev">Previous</button>' +
                                '<button type="button" class="button" data-role="next">Next</button>' +
                            '</div>' +
                        '</div>';
                },
                renderRow: function (row, isExpanded) {
                    return '' +
                        '<tr' + (isExpanded ? ' class="is-expanded"' : '') + '>' +
                            '<td><strong>' + escapeHtml(row.student_name || '-') + '</strong><br><span class="cbt-analytics-muted">' + escapeHtml(row.student_username || '-') + (row.student_nisn ? ' · ' + escapeHtml(row.student_nisn) : '') + '</span></td>' +
                            '<td>' + escapeHtml(row.student_kelas || '-') + '</td>' +
                            '<td>' + escapeHtml(row.score_display || '0.00') + ' / ' + escapeHtml(row.max_score_display || '0.00') + '</td>' +
                            '<td>' + escapeHtml(row.percentage_display || '0.00%') + '</td>' +
                            '<td>' + escapeHtml(row.objective_percentage_display || '0.00%') + '</td>' +
                            '<td>' + createBadge(row.group_band_label || 'Middle', row.group_band === 'upper' ? 'pass' : (row.group_band === 'lower' ? 'fail' : 'warning')) + '</td>' +
                            '<td>' + createBadge(row.pass_label || '-', row.pass_tone || '') + '</td>' +
                            '<td>' + escapeHtml(row.answered_summary || '-') + '</td>' +
                            '<td>' + escapeHtml(row.duration_label || '-') + '</td>' +
                            '<td>' + escapeHtml(row.finished_at_label || '-') + '</td>' +
                            '<td><button type="button" class="button button-small" data-action="toggle-detail" data-row-id="' + escapeHtml(row.attempt_id || '') + '">' + (isExpanded ? 'Hide' : 'View') + '</button></td>' +
                        '</tr>';
                },
                renderDetailRow: function (row, columnCount) {
                    const review = row.detail && row.detail.review_summary ? row.detail.review_summary : {};
                    const archivedNote = Number(row.detail && row.detail.archived_count ? row.detail.archived_count : 0) > 0
                        ? '<div class="cbt-analytics-inline-card"><strong>Archived / Inactive Soal</strong><p>Attempt ini masih membawa ' + escapeHtml(row.detail.archived_count) + ' soal historis yang saat ini inactive/archived.</p></div>'
                        : '';
                    const resultsUrl = row.detail && row.detail.results_url ? String(row.detail.results_url) : '';

                    return '' +
                        '<tr class="cbt-analytics-inline-detail-row">' +
                            '<td colspan="' + columnCount + '">' +
                                '<div class="cbt-analytics-inline-detail-wrap">' +
                                    '<div class="cbt-analytics-inline-detail">' +
                                        '<div class="cbt-analytics-inline-detail-grid">' +
                                            '<div class="cbt-analytics-inline-card">' +
                                                '<strong>Ketuntasan</strong>' +
                                                '<p>KKM: ' + escapeHtml((row.detail && row.detail.kkm_percentage_display) || '75.00') + '%<br>Batas Lulus: ' + escapeHtml((row.detail && row.detail.passing_score_display) || '0.00') + '</p>' +
                                            '</div>' +
                                            '<div class="cbt-analytics-inline-card">' +
                                                '<strong>Objective Group</strong>' +
                                                '<p>Objective Percentage: ' + escapeHtml((row.detail && row.detail.objective_percentage_display) || '0.00%') + '<br>Group Band: ' + escapeHtml((row.detail && row.detail.group_band_label) || 'Middle') + '</p>' +
                                            '</div>' +
                                            '<div class="cbt-analytics-inline-card">' +
                                                '<strong>Review Summary</strong>' +
                                                '<ul>' +
                                                    '<li>Benar: ' + escapeHtml(review.correct_questions || 0) + '</li>' +
                                                    '<li>Salah: ' + escapeHtml(review.wrong_questions || 0) + '</li>' +
                                                    '<li>Manual: ' + escapeHtml(review.manual_questions || 0) + '</li>' +
                                                    '<li>Tidak dijawab: ' + escapeHtml(review.unanswered_questions || 0) + '</li>' +
                                                '</ul>' +
                                            '</div>' +
                                        '</div>' +
                                        archivedNote +
                                        (resultsUrl ? '<div class="cbt-analytics-inline-card"><strong>Tautan Cepat</strong><p><a class="button" href="' + escapeHtml(resultsUrl) + '">Buka di CBT Results</a></p></div>' : '') +
                                    '</div>' +
                                '</div>' +
                            '</td>' +
                        '</tr>';
                }
            });
        }());
    </script>
</div>
