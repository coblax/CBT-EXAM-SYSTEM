        <?php
        $print_mode = isset($print_mode) && is_string($print_mode) ? $print_mode : 'participant';
        $is_desk_number_mode = $print_mode === 'desk_number';
        $document_title = $is_desk_number_mode
            ? 'Nomor Meja ' . $card_program_title . ' - ' . $kelas_label
            : 'Kartu Peserta ' . $card_program_title . ' - ' . $kelas_label;
        ?>
        <!doctype html>
        <html lang="id">
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title><?php echo esc_html($document_title); ?></title>
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
                    align-items: start;
                }
                .cards-grid.is-desk-number {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 2.2mm;
                }
                .exam-card {
                    border: 1px solid #0f172a;
                    padding: 2.8mm;
                    break-inside: avoid;
                    page-break-inside: avoid;
                    display: flex;
                    flex-direction: column;
                    align-self: start;
                }
                .exam-card-head {
                    display: grid;
                    grid-template-columns: 14mm minmax(0, 1fr) 14mm;
                    gap: 1.6mm;
                    align-items: start;
                    padding-bottom: 1.7mm;
                    border-bottom: 1.4px solid #0f172a;
                    position: relative;
                }
                .exam-card-head::after {
                    content: "";
                    position: absolute;
                    left: 0;
                    right: 0;
                    bottom: 3px;
                    border-bottom: 0.6px solid #94a3b8;
                }
                .exam-card-school-logo {
                    width: 14mm;
                    min-height: 16mm;
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
                    gap: 0.2mm;
                    text-align: center;
                }
                .exam-card-school-name {
                    font-size: 8.7px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.03em;
                    line-height: 1.08;
                }
                .exam-card-school-npsn {
                    font-size: 6.8px;
                    font-weight: 700;
                    line-height: 1.1;
                    letter-spacing: 0.03em;
                }
                .exam-card-school-address {
                    font-size: 6.35px;
                    line-height: 1.18;
                    color: #334155;
                }
                .exam-card-school-address--segments {
                    text-align: center;
                }
                .exam-card-school-address-segment {
                    display: inline-block;
                    white-space: nowrap;
                    margin-right: 0.45mm;
                }
                .exam-card-school-address-segment:last-child {
                    margin-right: 0;
                }
                .exam-card-school-motto {
                    margin-top: 0.15mm;
                    font-size: 6.4px;
                    line-height: 1.18;
                    color: #475569;
                    font-style: italic;
                }
                .exam-card-document-head {
                    margin-top: 0.4mm;
                    padding-top: 0.55mm;
                    border-top: 1px solid #cbd5e1;
                }
                .exam-card-title {
                    margin-top: 0;
                    font-weight: 700;
                    font-size: 8.5px;
                    line-height: 1.18;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                }
                .exam-card-content {
                    margin-top: 2.4mm;
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 24mm;
                    gap: 2.4mm;
                    align-items: start;
                }
                .exam-card-content.is-no-photo {
                    grid-template-columns: minmax(0, 1fr);
                }
                .exam-card-content.is-photo-only {
                    grid-template-columns: 24mm;
                    justify-content: end;
                }
                .exam-card-main-fields {
                    min-width: 0;
                }
                .exam-card-photo-panel {
                    display: grid;
                    gap: 1mm;
                    align-content: start;
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
                .exam-card-photo-gender {
                    padding: 0.8mm 1mm;
                    border: 1px solid #cbd5e1;
                    border-radius: 3px;
                    background: #f8fafc;
                    text-align: center;
                    font-size: 8.6px;
                    font-weight: 700;
                    line-height: 1.15;
                    color: #334155;
                    text-transform: uppercase;
                    letter-spacing: 0.03em;
                }
                .exam-card-row {
                    display: grid;
                    grid-template-columns: 31mm 2.6mm minmax(0, 1fr);
                    gap: 0.8mm;
                    margin-bottom: 1.2mm;
                    align-items: start;
                }
                .exam-card-row-label {
                    font-weight: 700;
                    color: #334155;
                    text-transform: uppercase;
                    font-size: 10px;
                }
                .exam-card-row-separator {
                    font-size: 11px;
                    font-weight: 700;
                    line-height: 1.15;
                    text-align: center;
                    color: #334155;
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
                .exam-card-schedule-block.is-standalone {
                    margin-top: 2.8mm;
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
                .desk-seat-card {
                    border: 1.9px solid #38bdf8;
                    break-inside: avoid;
                    page-break-inside: avoid;
                    background: #ffffff;
                    display: grid;
                    grid-template-rows: auto minmax(23mm, 1fr);
                    min-height: 36mm;
                    overflow: hidden;
                }
                .desk-seat-card-head {
                    display: grid;
                    grid-template-columns: 9mm minmax(0, 1fr);
                    gap: 0.9mm;
                    align-items: center;
                    text-align: center;
                    padding: 1.4mm 1.8mm 1.1mm;
                    border-bottom: 1.4px solid #38bdf8;
                    background: linear-gradient(180deg, #fcfeff 0%, #f3faff 100%);
                }
                .desk-seat-card-logo {
                    width: 8mm;
                    height: 8mm;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    align-self: start;
                }
                .desk-seat-card-logo img {
                    width: 100%;
                    height: 100%;
                    object-fit: contain;
                }
                .desk-seat-card-head-main {
                    display: grid;
                    gap: 0.15mm;
                    text-align: center;
                    min-width: 0;
                }
                .desk-seat-card-title {
                    font-size: 6.9px;
                    font-weight: 800;
                    line-height: 1.08;
                    letter-spacing: 0.03em;
                    text-transform: uppercase;
                    color: #0f172a;
                }
                .desk-seat-card-school {
                    font-size: 5.9px;
                    font-weight: 700;
                    line-height: 1.12;
                    text-transform: uppercase;
                    color: #1e3a8a;
                }
                .desk-seat-card-body {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 23mm;
                    padding: 0.4mm 1.2mm 1.1mm;
                }
                .desk-seat-card-number {
                    display: block;
                    width: 100%;
                    font-family: Georgia, "Times New Roman", serif;
                    font-size: 20mm;
                    line-height: 0.9;
                    font-weight: 700;
                    letter-spacing: 0;
                    color: #0f2f7a;
                    text-align: center;
                    white-space: nowrap;
                    overflow: hidden;
                    font-variant-numeric: lining-nums tabular-nums;
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
                    <div><strong>Mode Cetak</strong>: <?php echo esc_html($is_desk_number_mode ? 'Nomor Meja' : 'Kartu Peserta'); ?></div>
                    <?php if ($is_desk_number_mode): ?>
                        <div><strong>Nomor Awal</strong>: <?php echo esc_html((string) $seat_start_number); ?></div>
                    <?php else: ?>
                        <div><strong>Total Siswa</strong>: <?php echo esc_html((string) $student_total); ?></div>
                    <?php endif; ?>
                    <div><strong>Tanggal Cetak</strong>: <?php echo esc_html($printed_at); ?></div>
                    <div><strong>Kelas</strong>: <?php echo esc_html($kelas_label); ?></div>
                    <div><strong>Ruang</strong>: <?php echo esc_html($ruang_label); ?></div>
                    <?php if ($is_desk_number_mode): ?>
                        <div><strong>Digit Padding</strong>: <?php echo esc_html((string) $seat_padding); ?></div>
                        <div><strong>Total Kartu</strong>: <?php echo esc_html((string) $student_total); ?></div>
                    <?php endif; ?>
                </div>
                <?php
                $field_visibility = array_fill_keys(is_array($selected_display_fields ?? null) ? $selected_display_fields : [], true);
                $card_header_region_segments = array_values(array_filter(array_map('trim', explode(',', (string) ($card_header_region_line ?? ''))), static function ($segment): bool {
                    return $segment !== '';
                }));
                $desk_logo_url = $school_logo_1_url !== '' ? $school_logo_1_url : $school_logo_2_url;
                ?>
                <?php if ($is_desk_number_mode): ?>
                    <section class="cards-grid is-desk-number">
                        <?php foreach ((array) ($seat_cards ?? []) as $seat_card): ?>
                            <article class="desk-seat-card">
                                <header class="desk-seat-card-head">
                                    <?php if ($desk_logo_url !== ''): ?>
                                        <div class="desk-seat-card-logo">
                                            <img src="<?php echo esc_url($desk_logo_url); ?>" alt="<?php echo esc_attr($school_name . ' Logo'); ?>" loading="lazy" decoding="async" />
                                        </div>
                                    <?php endif; ?>
                                    <div class="desk-seat-card-head-main">
                                        <div class="desk-seat-card-title"><?php echo esc_html($card_program_title); ?></div>
                                        <div class="desk-seat-card-school"><?php echo esc_html($school_name); ?></div>
                                    </div>
                                </header>
                                <div class="desk-seat-card-body">
                                    <div class="desk-seat-card-number"><?php echo esc_html((string) ($seat_card['seat_number_display'] ?? '-')); ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php else: ?>
                    <section class="cards-grid">
                        <?php foreach ($students as $student): ?>
                            <?php
                            $student_name = trim((string) ($student['name'] ?? ''));
                            if ($student_name === '') {
                                $student_name = (string) ($student['username'] ?? '-');
                            }
                            $student_photo = trim((string) ($student['foto'] ?? ''));
                            $student_nisn = trim((string) ($student['nisn'] ?? ''));
                            $student_username = trim((string) ($student['username'] ?? ''));
                            $student_password = trim((string) ($student['password'] ?? ''));
                            $student_kelas = trim((string) ($student['kelas'] ?? ''));
                            $student_jenis_kelamin = trim((string) ($student['jenis_kelamin'] ?? ''));
                            $student_agama = trim((string) ($student['agama'] ?? ''));
                            $student_ruang = trim((string) ($student['ruang'] ?? ''));
                            $student_jenis_kelamin_compact = $student_jenis_kelamin !== '' ? strtoupper($student_jenis_kelamin) : '-';
                            $student_agama_compact = $student_agama !== ''
                                ? (function_exists('mb_strtoupper') ? mb_strtoupper($student_agama, 'UTF-8') : strtoupper($student_agama))
                                : '-';
                            $student_initial = strtoupper(substr($student_name, 0, 1));
                            if ($student_initial === '') {
                                $student_initial = 'S';
                            }
                            $show_photo = isset($field_visibility['photo']);
                            $show_gender_under_photo = $show_photo && isset($field_visibility['jenis_kelamin']);
                            $visible_rows = [];
                            if (isset($field_visibility['name'])) {
                                $visible_rows[] = [
                                    'label' => 'Nama Peserta',
                                    'value' => $student_name,
                                    'is_password' => false,
                                ];
                            }
                            if (isset($field_visibility['nisn'])) {
                                $visible_rows[] = [
                                    'label' => 'NISN',
                                    'value' => $student_nisn !== '' ? $student_nisn : '-',
                                    'is_password' => false,
                                ];
                            }
                            if (isset($field_visibility['username'])) {
                                $visible_rows[] = [
                                    'label' => 'Username',
                                    'value' => $student_username !== '' ? $student_username : '-',
                                    'is_password' => false,
                                ];
                            }
                            if (isset($field_visibility['password'])) {
                                $visible_rows[] = [
                                    'label' => 'Password',
                                    'value' => $student_password !== '' ? $student_password : '-',
                                    'is_password' => true,
                                ];
                            }
                            if (isset($field_visibility['kelas'])) {
                                $visible_rows[] = [
                                    'label' => 'Kelas',
                                    'value' => $student_kelas !== '' ? $student_kelas : '-',
                                    'is_password' => false,
                                ];
                            }
                            if (isset($field_visibility['ruang'])) {
                                $visible_rows[] = [
                                    'label' => 'Ruangan',
                                    'value' => $student_ruang !== '' ? $student_ruang : '-',
                                    'is_password' => false,
                                ];
                            }
                            if (isset($field_visibility['jenis_kelamin']) && !$show_gender_under_photo) {
                                $visible_rows[] = [
                                    'label' => 'Jenis Kelamin',
                                    'value' => $student_jenis_kelamin_compact,
                                    'is_password' => false,
                                ];
                            }
                            if (isset($field_visibility['agama'])) {
                                $visible_rows[] = [
                                    'label' => 'Agama',
                                    'value' => $student_agama_compact,
                                    'is_password' => false,
                                ];
                            }
                            $has_main_fields = !empty($visible_rows);
                            $show_schedule = isset($field_visibility['schedule']);
                            $content_class_names = ['exam-card-content'];
                            if (!$show_photo) {
                                $content_class_names[] = 'is-no-photo';
                            } elseif (!$has_main_fields) {
                                $content_class_names[] = 'is-photo-only';
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
                                        <?php if ($card_header_address_line !== ''): ?>
                                            <div class="exam-card-school-address"><?php echo esc_html($card_header_address_line); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($card_header_region_segments)): ?>
                                            <div class="exam-card-school-address exam-card-school-address--segments">
                                                <?php foreach ($card_header_region_segments as $region_segment_index => $region_segment): ?>
                                                    <span class="exam-card-school-address-segment">
                                                        <?php echo esc_html($region_segment); ?><?php echo $region_segment_index < count($card_header_region_segments) - 1 ? ',' : ''; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($school_motto !== ''): ?>
                                            <div class="exam-card-school-motto"><?php echo esc_html($school_motto); ?></div>
                                        <?php endif; ?>
                                        <div class="exam-card-document-head">
                                            <div class="exam-card-title"><?php echo esc_html('Kartu Peserta ' . $card_program_title); ?></div>
                                        </div>
                                    </div>
                                    <div class="exam-card-school-logo is-right">
                                        <?php if ($school_logo_2_url !== ''): ?>
                                            <img src="<?php echo esc_url($school_logo_2_url); ?>" alt="<?php echo esc_attr($school_name . ' Logo 2'); ?>" loading="lazy" decoding="async" />
                                        <?php endif; ?>
                                    </div>
                                </header>
                                <?php if ($has_main_fields || $show_photo): ?>
                                    <div class="<?php echo esc_attr(implode(' ', $content_class_names)); ?>">
                                        <?php if ($has_main_fields): ?>
                                            <div class="exam-card-main-fields">
                                                <?php foreach ($visible_rows as $visible_row): ?>
                                                    <div class="exam-card-row">
                                                        <div class="exam-card-row-label"><?php echo esc_html((string) ($visible_row['label'] ?? '')); ?></div>
                                                        <div class="exam-card-row-separator">:</div>
                                                        <div class="exam-card-row-value<?php echo !empty($visible_row['is_password']) ? ' is-password' : ''; ?>">
                                                            <?php echo esc_html((string) ($visible_row['value'] ?? '-')); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($show_photo): ?>
                                            <div class="exam-card-photo-panel">
                                                <div class="exam-card-photo-box">
                                                    <?php if ($student_photo !== ''): ?>
                                                        <img src="<?php echo esc_url($student_photo); ?>" alt="<?php echo esc_attr($student_name); ?>" loading="lazy" decoding="async" />
                                                    <?php else: ?>
                                                        <div class="exam-card-photo-fallback"><?php echo esc_html($student_initial); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($show_gender_under_photo): ?>
                                                    <div class="exam-card-photo-gender"><?php echo esc_html($student_jenis_kelamin_compact); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($show_schedule): ?>
                                    <section class="exam-card-schedule-block<?php echo !$has_main_fields && !$show_photo ? ' is-standalone' : ''; ?>">
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
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
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
