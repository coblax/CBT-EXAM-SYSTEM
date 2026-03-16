        <!doctype html>
        <html lang="id">
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title><?php echo esc_html('Laporan Hasil ' . $report_program_title . ' - ' . (string) ($exam['title'] ?? '-')); ?></title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 14mm 12mm;
                }
                * {
                    box-sizing: border-box;
                }
                body {
                    margin: 0;
                    font-family: Arial, Helvetica, sans-serif;
                    color: #111827;
                    background: #fff;
                    font-size: 12px;
                    line-height: 1.45;
                }
                .no-print {
                    padding: 10px 12px;
                    border-bottom: 1px solid #dbe5f2;
                    background: #f8fbff;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .no-print .button {
                    border: 1px solid #1d4ed8;
                    background: #2563eb;
                    color: #fff;
                    padding: 6px 10px;
                    border-radius: 6px;
                    text-decoration: none;
                    cursor: pointer;
                    font-size: 12px;
                    line-height: 1;
                }
                .no-print .button-secondary {
                    border-color: #cbd5e1;
                    background: #fff;
                    color: #0f172a;
                }
                .report-wrap {
                    padding: 18px 18px 14px;
                }
                .report-header {
                    display: grid;
                    grid-template-columns: 26mm minmax(0, 1fr) 26mm;
                    gap: 12px;
                    align-items: start;
                    margin-bottom: 12px;
                    padding-bottom: 10px;
                    border-bottom: 3px solid #0f172a;
                    position: relative;
                }
                .report-header::after {
                    content: "";
                    position: absolute;
                    left: 0;
                    right: 0;
                    bottom: 4px;
                    border-bottom: 1px solid #94a3b8;
                }
                .report-header-main {
                    display: grid;
                    gap: 2px;
                    text-align: center;
                }
                .report-logo {
                    width: 24mm;
                    height: 24mm;
                    padding: 1mm 2mm;
                    background: #fff;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    align-self: start;
                }
                .report-logo img {
                    width: auto;
                    height: auto;
                    max-width: 100%;
                    max-height: 100%;
                    display: block;
                    object-fit: contain;
                    object-position: center;
                }
                .report-logo.is-right {
                    justify-self: end;
                }
                .report-logo-fallback {
                    font-size: 11px;
                    font-weight: 700;
                    color: #64748b;
                }
                .report-school-name {
                    margin: 0;
                    font-size: 20px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    line-height: 1.1;
                }
                .report-school-npsn {
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .report-school-address {
                    font-size: 10.5px;
                    line-height: 1.3;
                }
                .report-school-motto {
                    margin: 1px 0 0;
                    font-size: 10.5px;
                    line-height: 1.35;
                    color: #334155;
                    font-style: italic;
                }
                .report-document-head {
                    margin-top: 6px;
                    padding-top: 6px;
                    border-top: 1px solid #cbd5e1;
                }
                .report-document-title {
                    margin: 0;
                    font-size: 17px;
                    font-weight: 700;
                    line-height: 1.2;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                }
                .report-document-subtitle {
                    margin: 2px 0 0;
                    font-size: 10px;
                    font-weight: 700;
                    line-height: 1.3;
                    letter-spacing: 0.1em;
                    text-transform: uppercase;
                    color: #475569;
                }
                .report-meta {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 76mm;
                    gap: 10px 18px;
                    font-size: 12px;
                    align-items: start;
                    margin-top: 10px;
                }
                .report-meta-col {
                    display: grid;
                    gap: 5px;
                }
                .report-meta-row {
                    display: grid;
                    grid-template-columns: 34mm 6px minmax(0, 1fr);
                    align-items: start;
                    gap: 3px;
                }
                .report-meta-label {
                    font-weight: 700;
                }
                .report-meta-separator {
                    text-align: center;
                    font-weight: 700;
                }
                .report-meta-value {
                    min-height: 14px;
                    word-break: break-word;
                }
                .report-meta-value.is-line {
                    min-width: 110px;
                    border-bottom: 1px solid #111827;
                }
                .report-meta-col--identity {
                    align-self: stretch;
                    padding: 7px 10px;
                    border: 1px solid #64748b;
                }
                .report-meta-col--identity .report-meta-row {
                    grid-template-columns: 40mm 6px minmax(0, 1fr);
                    gap: 4px;
                    padding: 2px 0;
                    min-height: 20px;
                    border-bottom: 1px solid #e2e8f0;
                }
                .report-meta-col--identity .report-meta-row:last-child {
                    border-bottom: 0;
                }
                .report-meta-col--identity .report-meta-label {
                    letter-spacing: 0.01em;
                }
                .report-meta-col--identity .report-meta-value {
                    font-weight: 600;
                }
                .report-meta-col--summary {
                    align-self: stretch;
                    padding: 7px 9px;
                    border: 1px solid #64748b;
                }
                .report-meta-summary-title {
                    margin: 0 0 5px;
                    padding-bottom: 4px;
                    border-bottom: 1px solid #cbd5e1;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    text-align: center;
                }
                .report-meta-col--summary .report-meta-row {
                    grid-template-columns: minmax(0, 1fr) 6px 18px;
                    gap: 4px;
                }
                .report-meta-col--summary .report-meta-label {
                    font-weight: 600;
                }
                .report-meta-col--summary .report-meta-value {
                    text-align: right;
                    font-weight: 700;
                    white-space: nowrap;
                }
                .report-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 14px;
                }
                .report-table th,
                .report-table td {
                    border: 1px solid #475569;
                    padding: 6px 8px;
                }
                .report-table th {
                    background: #f1f5f9;
                    text-align: center;
                    font-size: 11px;
                }
                .report-table td:nth-child(1),
                .report-table td:nth-child(5) {
                    text-align: center;
                    white-space: nowrap;
                }
                .report-empty {
                    text-align: center;
                    color: #64748b;
                    padding: 14px 8px;
                }
                .report-section {
                    margin-top: 18px;
                }
                .report-section-title {
                    margin: 0 0 8px;
                    font-size: 14px;
                    line-height: 1.25;
                }
                .report-table--incident td:nth-child(1) {
                    text-align: center;
                    white-space: nowrap;
                }
                .report-table--incident td:nth-child(2) {
                    white-space: nowrap;
                }
                .report-table--incident td:nth-child(4) {
                    white-space: pre-line;
                }
                .signature-wrap {
                    margin-top: 40px;
                    display: grid;
                    grid-template-columns: repeat(<?php echo (int) count($supervisors); ?>, minmax(0, 1fr));
                    gap: 24px;
                }
                .signature-card {
                    text-align: center;
                }
                .signature-role {
                    font-weight: 600;
                }
                .signature-space {
                    height: 74px;
                }
                .signature-name {
                    display: inline-block;
                    min-width: 220px;
                    max-width: 320px;
                    border-top: 1px solid #111827;
                    padding-top: 4px;
                    font-weight: 700;
                    margin: 0 auto;
                }
                .signature-nip {
                    margin-top: 2px;
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    .report-wrap {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button type="button" class="button" onclick="window.print()">Print / Save PDF</button>
                <a class="button button-secondary" href="<?php echo esc_url($back_url); ?>">Kembali ke Form Report</a>
            </div>
            <main class="report-wrap">
                <div class="report-header">
                    <div class="report-logo">
                        <?php if ($report_logo_1_url !== ''): ?>
                            <img src="<?php echo esc_url($report_logo_1_url); ?>" alt="<?php echo esc_attr($site_name . ' Logo Sekolah'); ?>" loading="lazy" decoding="async" />
                        <?php endif; ?>
                    </div>
                    <div class="report-header-main">
                        <div class="report-school-name"><?php echo esc_html($site_name); ?></div>
                        <?php if ($site_npsn !== ''): ?>
                            <div class="report-school-npsn">NPSN: <?php echo esc_html($site_npsn); ?></div>
                        <?php endif; ?>
                        <div class="report-school-address"><?php echo esc_html($report_header_address_line); ?></div>
                        <div class="report-school-address"><?php echo esc_html($report_header_region_line); ?></div>
                        <?php if ($site_motto !== ''): ?>
                            <div class="report-school-motto"><?php echo esc_html($site_motto); ?></div>
                        <?php endif; ?>
                        <div class="report-document-head">
                            <h1 class="report-document-title"><?php echo esc_html('Laporan Hasil ' . $report_program_title); ?></h1>
                            <div class="report-document-subtitle">Rekap Nilai dan Kehadiran Peserta</div>
                        </div>
                    </div>
                    <div class="report-logo is-right">
                        <?php if ($report_logo_2_url !== ''): ?>
                            <img src="<?php echo esc_url($report_logo_2_url); ?>" alt="<?php echo esc_attr($site_name . ' Logo Instansi'); ?>" loading="lazy" decoding="async" />
                        <?php endif; ?>
                    </div>
                </div>
                <div class="report-meta">
                    <div class="report-meta-col report-meta-col--identity">
                        <div class="report-meta-summary-title">Identitas Ujian</div>
                        <div class="report-meta-row">
                            <div class="report-meta-label">Sekolah / Madrasah</div>
                            <div class="report-meta-separator">:</div>
                            <div class="report-meta-value"><?php echo esc_html($site_name !== '' ? $site_name : '-'); ?></div>
                        </div>
                        <div class="report-meta-row">
                            <div class="report-meta-label">Ujian</div>
                            <div class="report-meta-separator">:</div>
                            <div class="report-meta-value"><?php echo esc_html((string) ($exam['title'] ?? '-')); ?></div>
                        </div>
                        <div class="report-meta-row">
                            <div class="report-meta-label">Mata Pelajaran</div>
                            <div class="report-meta-separator">:</div>
                            <div class="report-meta-value<?php echo $subject_label === '' ? ' is-line' : ''; ?>"><?php echo $subject_label !== '' ? esc_html($subject_label) : ''; ?></div>
                        </div>
                        <div class="report-meta-row">
                            <div class="report-meta-label">Hari / Tanggal</div>
                            <div class="report-meta-separator">:</div>
                            <div class="report-meta-value<?php echo ($exam_day_label === '' && $exam_date_label === '' && $exam_start_time_label === '') ? ' is-line' : ''; ?>">
                                <?php
                                $exam_schedule_label = trim($exam_day_label . ($exam_day_label !== '' && $exam_date_label !== '' ? ', ' : '') . $exam_date_label);
                                if ($exam_start_time_label !== '') {
                                    $exam_schedule_label .= ($exam_schedule_label !== '' ? ' · ' : '') . $exam_start_time_label;
                                }
                                echo esc_html($exam_schedule_label);
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="report-meta-col report-meta-col--summary">
                        <div class="report-meta-summary-title">Rekap Kehadiran Peserta</div>
                        <div class="report-meta-row">
                            <div class="report-meta-label">Jumlah peserta terdaftar</div>
                            <div class="report-meta-separator">:</div>
                            <div class="report-meta-value"><?php echo esc_html((string) $registered_student_total); ?></div>
                        </div>
                        <div class="report-meta-row">
                            <div class="report-meta-label">Jumlah hadir</div>
                            <div class="report-meta-separator">:</div>
                            <div class="report-meta-value"><?php echo esc_html((string) $present_student_total); ?></div>
                        </div>
                        <div class="report-meta-row">
                            <div class="report-meta-label">Jumlah tidak hadir</div>
                            <div class="report-meta-separator">:</div>
                            <div class="report-meta-value"><?php echo esc_html((string) $absent_student_total); ?></div>
                        </div>
                    </div>
                </div>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width:52px;">NO</th>
                            <th style="width:160px;">NISN</th>
                            <th>NAMA</th>
                            <th style="width:120px;">KELAS</th>
                            <th style="width:96px;">NILAI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_rows)): ?>
                            <tr>
                                <td class="report-empty" colspan="5">Tidak ada data peserta sesuai filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_rows as $report_row): ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($report_row['no'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($report_row['nisn'] ?? '-')); ?></td>
                                    <td><?php echo esc_html((string) ($report_row['nama'] ?? '-')); ?></td>
                                    <td><?php echo esc_html((string) ($report_row['kelas'] ?? '-')); ?></td>
                                    <td><?php echo esc_html((string) ($report_row['nilai_display'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <section class="report-section">
                    <h2 class="report-section-title">Laporan Kejadian / Pelanggaran Selama Ujian</h2>
                    <table class="report-table report-table--incident">
                        <thead>
                            <tr>
                                <th style="width:52px;">NO</th>
                                <th style="width:160px;">NISN</th>
                                <th>NAMA</th>
                                <th style="width:420px;">KETERANGAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($incident_report_rows)): ?>
                                <tr>
                                    <td class="report-empty" colspan="4">Tidak ada incident sesuai filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($incident_report_rows as $incident_report_row): ?>
                                    <tr>
                                        <td><?php echo esc_html((string) ($incident_report_row['no'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($incident_report_row['nisn'] ?? '-')); ?></td>
                                        <td><?php echo esc_html((string) ($incident_report_row['nama'] ?? '-')); ?></td>
                                        <td><?php echo esc_html((string) ($incident_report_row['keterangan'] ?? '-')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <section class="signature-wrap">
                    <?php foreach ($supervisors as $idx => $supervisor): ?>
                        <div class="signature-card">
                            <p class="signature-role"><?php echo esc_html((string) ($signature_role_labels[$idx] ?? (($supervisor['role'] ?? '') !== '' ? $supervisor['role'] : ('Petugas ' . ($idx + 1))))); ?></p>
                            <div class="signature-space"></div>
                            <p class="signature-name"><?php echo esc_html((string) ($supervisor['name'] ?? '-')); ?></p>
                            <div class="signature-nip"><?php echo esc_html('NIP: ' . (string) ($supervisor['nip'] ?? '-')); ?></div>
                        </div>
                    <?php endforeach; ?>
                </section>
            </main>
            <script>
                window.addEventListener('load', function () {
                    window.setTimeout(function () {
                        window.print();
                    }, 350);
                });
            </script>
        </body>
        </html>
        <?php
