        <!doctype html>
        <html lang="id">
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title><?php echo esc_html('Kartu Peserta ' . $card_program_title . ' - ' . $kelas_label); ?></title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 10mm;
                }
                * {
                    box-sizing: border-box;
                }
                body {
                    margin: 0;
                    font-family: Arial, Helvetica, sans-serif;
                    color: #0f172a;
                    background: #fff;
                    font-size: 11px;
                    line-height: 1.35;
                }
                .no-print {
                    padding: 10px 12px;
                    border-bottom: 1px solid #dbe5f2;
                    background: #f8fbff;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
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
                .cards-wrap {
                    padding: 8px;
                }
                .cards-meta {
                    margin: 0 0 8px;
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 3px 12px;
                    font-size: 11px;
                }
                .cards-meta strong {
                    display: inline-block;
                    min-width: 84px;
                }
                .cards-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 5mm;
                }
                .exam-card {
                    border: 1px solid #0f172a;
                    padding: 2.8mm;
                    min-height: 86mm;
                    break-inside: avoid;
                    page-break-inside: avoid;
                    display: flex;
                    flex-direction: column;
                }
                .exam-card-head {
                    display: grid;
                    grid-template-columns: 16mm minmax(0, 1fr) 16mm;
                    gap: 2mm;
                    align-items: start;
                    padding-bottom: 1.8mm;
                    border-bottom: 1px solid #64748b;
                }
                .exam-card-school-logo {
                    width: 16mm;
                    min-height: 18mm;
                    display: flex;
                    align-items: flex-start;
                    justify-content: center;
                    overflow: hidden;
                    align-self: start;
                }
                .exam-card-school-logo img {
                    width: 100%;
                    height: 100%;
                    display: block;
                    object-fit: contain;
                    object-position: center top;
                }
                .exam-card-school-logo-fallback {
                    font-weight: 700;
                    color: #334155;
                    font-size: 10px;
                }
                .exam-card-head-main {
                    display: grid;
                    gap: 0.35mm;
                    text-align: center;
                }
                .exam-card-school-name {
                    font-size: 9.8px;
                    font-weight: 700;
                    text-transform: uppercase;
                    line-height: 1.1;
                }
                .exam-card-school-npsn {
                    font-size: 7.4px;
                    font-weight: 700;
                    line-height: 1.1;
                    letter-spacing: 0.03em;
                }
                .exam-card-school-motto {
                    margin-top: 0;
                    font-size: 7.6px;
                    line-height: 1.15;
                    color: #475569;
                }
                .exam-card-title {
                    margin-top: 0.3mm;
                    padding-top: 0.55mm;
                    border-top: 1px solid #cbd5e1;
                    font-weight: 700;
                    font-size: 10px;
                    line-height: 1.15;
                    text-transform: uppercase;
                }
                .exam-card-content {
                    margin-top: 2.4mm;
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 24mm;
                    gap: 2.4mm;
                    align-items: start;
                }
                .exam-card-main-fields {
                    min-width: 0;
                }
                .exam-card-photo-box {
                    border: 1px solid #94a3b8;
                    border-radius: 3px;
                    min-height: 31mm;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #f8fafc;
                    overflow: hidden;
                }
                .exam-card-photo-box img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }
                .exam-card-photo-fallback {
                    font-size: 15px;
                    font-weight: 700;
                    color: #475569;
                }
                .exam-card-row {
                    display: grid;
                    grid-template-columns: 33mm minmax(0, 1fr);
                    gap: 1.2mm;
                    margin-bottom: 1.2mm;
                    align-items: start;
                }
                .exam-card-row-label {
                    font-weight: 700;
                    color: #334155;
                    text-transform: uppercase;
                    font-size: 10px;
                }
                .exam-card-row-value {
                    font-size: 11px;
                    word-break: break-word;
                }
                .exam-card-row-value.is-password {
                    font-weight: 700;
                    letter-spacing: 0.03em;
                }
                .exam-card-schedule-block {
                    margin-top: 2.2mm;
                }
                .exam-card-schedule-title {
                    font-size: 10px;
                    font-weight: 700;
                    text-transform: uppercase;
                    color: #334155;
                    margin-bottom: 1.2mm;
                }
                .exam-card-schedule-table {
                    width: 100%;
                    border-collapse: collapse;
                    table-layout: fixed;
                    font-size: 8.3px;
                    line-height: 1.15;
                }
                .exam-card-schedule-table th,
                .exam-card-schedule-table td {
                    border: 1px solid #94a3b8;
                    padding: 0.6mm 0.75mm;
                    vertical-align: top;
                    text-align: left;
                    word-break: break-word;
                }
                .exam-card-schedule-table th {
                    background: #f1f5f9;
                    font-weight: 700;
                    text-transform: uppercase;
                    color: #334155;
                    white-space: nowrap;
                }
                @media print {
                    .no-print {
                        display: none !important;
                    }
                    .cards-wrap {
                        padding: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="no-print">
                <button type="button" class="button" onclick="window.print()">Print / Save PDF</button>
                <a class="button button-secondary" href="<?php echo esc_url($back_url); ?>">Kembali ke Form Cetak</a>
            </div>
            <main class="cards-wrap">
                <div class="cards-meta">
                    <div><strong>Tanggal Cetak</strong>: <?php echo esc_html($printed_at); ?></div>
                    <div><strong>Total Siswa</strong>: <?php echo esc_html((string) $student_total); ?></div>
                    <div><strong>Kelas</strong>: <?php echo esc_html($kelas_label); ?></div>
                    <div><strong>Ruang</strong>: <?php echo esc_html($ruang_label); ?></div>
                </div>
                <section class="cards-grid">
                    <?php foreach ($students as $student): ?>
                        <?php
                        $student_name = trim((string) ($student['name'] ?? ''));
                        if ($student_name === '') {
                            $student_name = (string) ($student['username'] ?? '-');
                        }
                        $student_photo = trim((string) ($student['foto'] ?? ''));
                        $student_initial = strtoupper(substr($student_name, 0, 1));
                        if ($student_initial === '') {
                            $student_initial = 'S';
                        }
                        ?>
                        <article class="exam-card">
                            <header class="exam-card-head">
                                <div class="exam-card-school-logo is-left">
                                    <?php if ($school_logo_1_url !== ''): ?>
                                        <img src="<?php echo esc_url($school_logo_1_url); ?>" alt="<?php echo esc_attr($school_name . ' Logo 1'); ?>" loading="lazy" decoding="async" />
                                    <?php endif; ?>
                                </div>
                                <div class="exam-card-head-main">
                                    <div class="exam-card-school-name"><?php echo esc_html($school_name); ?></div>
                                    <?php if ($school_npsn !== ''): ?>
                                        <div class="exam-card-school-npsn">NPSN: <?php echo esc_html($school_npsn); ?></div>
                                    <?php endif; ?>
                                    <?php if ($school_motto !== ''): ?>
                                        <div class="exam-card-school-motto"><?php echo esc_html($school_motto); ?></div>
                                    <?php endif; ?>
                                    <div class="exam-card-title"><?php echo esc_html('Kartu Peserta ' . $card_program_title); ?></div>
                                </div>
                                <div class="exam-card-school-logo is-right">
                                    <?php if ($school_logo_2_url !== ''): ?>
                                        <img src="<?php echo esc_url($school_logo_2_url); ?>" alt="<?php echo esc_attr($school_name . ' Logo 2'); ?>" loading="lazy" decoding="async" />
                                    <?php endif; ?>
                                </div>
                            </header>
                            <div class="exam-card-content">
                                <div class="exam-card-main-fields">
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Nama Peserta</div>
                                        <div class="exam-card-row-value"><?php echo esc_html($student_name); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">NISN</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) ($student['nisn'] !== '' ? $student['nisn'] : '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Username</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) ($student['username'] ?? '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Password</div>
                                        <div class="exam-card-row-value is-password"><?php echo esc_html((string) ($student['password'] ?? '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Kelas</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) (($student['kelas'] ?? '') !== '' ? $student['kelas'] : '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Agama</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) (($student['agama'] ?? '') !== '' ? $student['agama'] : '-')); ?></div>
                                    </div>
                                    <div class="exam-card-row">
                                        <div class="exam-card-row-label">Ruangan</div>
                                        <div class="exam-card-row-value"><?php echo esc_html((string) (($student['ruang'] ?? '') !== '' ? $student['ruang'] : '-')); ?></div>
                                    </div>
                                </div>
                                <div class="exam-card-photo-box">
                                    <?php if ($student_photo !== ''): ?>
                                        <img src="<?php echo esc_url($student_photo); ?>" alt="<?php echo esc_attr($student_name); ?>" loading="lazy" decoding="async" />
                                    <?php else: ?>
                                        <div class="exam-card-photo-fallback"><?php echo esc_html($student_initial); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <section class="exam-card-schedule-block">
                                <div class="exam-card-schedule-title">Jadwal Ujian</div>
                                <?php if (empty($schedule_items)): ?>
                                    -
                                <?php else: ?>
                                    <table class="exam-card-schedule-table">
                                        <thead>
                                            <tr>
                                                <th style="width:36%;">Mapel</th>
                                                <th style="width:30%;">Hari</th>
                                                <th style="width:20%;">Jadwal</th>
                                                <th style="width:14%;">Durasi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($schedule_items as $schedule_item): ?>
                                            <tr>
                                                <td><?php echo esc_html((string) ($schedule_item['title'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($schedule_item['day'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($schedule_item['time'] ?? '-')); ?></td>
                                                <td><?php echo esc_html((string) ($schedule_item['duration'] ?? '-')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </section>
                        </article>
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
