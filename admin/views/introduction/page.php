<?php

if (!defined('ABSPATH')) {
    exit;
}

$section_nav = is_array($section_nav ?? null) ? $section_nav : [];
$overview_cards = is_array($overview_cards ?? null) ? $overview_cards : [];
$hero_metrics = is_array($hero_metrics ?? null) ? $hero_metrics : [];
$workflow_steps = is_array($workflow_steps ?? null) ? $workflow_steps : [];
$feature_groups = is_array($feature_groups ?? null) ? $feature_groups : [];
$workflow_guidance = is_array($workflow_guidance ?? null) ? $workflow_guidance : [];
$quick_links = is_array($quick_links ?? null) ? $quick_links : [];
$quick_link_available_count = (int) ($quick_link_available_count ?? 0);
?>
<div class="wrap cbt-introduction-page">
    <style>
        .cbt-introduction-page {
            max-width: 1180px;
        }
        .cbt-introduction-shell {
            display: grid;
            gap: 18px;
            margin-top: 18px;
        }
        .cbt-introduction-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.65fr);
            gap: 20px;
            padding: 28px;
            border: 1px solid #d6dce4;
            border-radius: 24px;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.16), transparent 32%),
                radial-gradient(circle at bottom left, rgba(14, 116, 144, 0.10), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #f6f9fc 100%);
            box-shadow: 0 22px 46px rgba(15, 23, 42, 0.06);
        }
        .cbt-introduction-kicker {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            background: #e8f1ff;
            color: #0f4fa8;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cbt-introduction-hero h1 {
            margin: 14px 0 10px;
            font-size: 31px;
            line-height: 1.14;
        }
        .cbt-introduction-hero p {
            margin: 0;
            max-width: 720px;
            color: #526172;
            font-size: 14px;
            line-height: 1.7;
        }
        .cbt-introduction-hero-note {
            margin-top: 18px;
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
        }
        .cbt-introduction-hero-side {
            display: grid;
            gap: 12px;
            align-content: start;
        }
        .cbt-introduction-metric-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .cbt-introduction-metric {
            padding: 16px 18px;
            border: 1px solid #dbe4ee;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.82);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
        }
        .cbt-introduction-metric strong {
            display: block;
            font-size: 26px;
            line-height: 1;
            color: #0f172a;
        }
        .cbt-introduction-metric span {
            display: block;
            margin-top: 6px;
            color: #526172;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .cbt-introduction-side-card {
            padding: 18px 20px;
            border: 1px solid #dbe4ee;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.78);
        }
        .cbt-introduction-side-card h2 {
            margin: 0 0 10px;
            font-size: 16px;
            line-height: 1.2;
        }
        .cbt-introduction-side-card ul {
            margin: 0;
            padding-left: 18px;
            color: #475569;
        }
        .cbt-introduction-side-card li + li {
            margin-top: 8px;
        }
        .cbt-introduction-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 16px 18px;
            border: 1px solid #dcdcde;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
        }
        .cbt-introduction-nav-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 14px;
            border: 1px solid #cfe0f7;
            border-radius: 999px;
            background: #eef4ff;
            color: #27528c;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .cbt-introduction-nav-button:hover,
        .cbt-introduction-nav-button:focus {
            background: #f7faff;
            border-color: #90b8ea;
            color: #173f73;
            outline: none;
        }
        .cbt-introduction-nav-button.is-active {
            border-color: #2563eb;
            background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff;
            box-shadow: 0 12px 22px rgba(37, 99, 235, 0.18);
        }
        .cbt-introduction-panels {
            display: grid;
            gap: 18px;
        }
        .cbt-introduction-section {
            padding: 24px;
            border: 1px solid #dcdcde;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
        }
        .cbt-introduction-section[hidden] {
            display: none !important;
        }
        .cbt-introduction-section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
        }
        .cbt-introduction-section-header h2 {
            margin: 0 0 8px;
            font-size: 24px;
            line-height: 1.15;
        }
        .cbt-introduction-section-header p {
            margin: 0;
            max-width: 760px;
            color: #5b6675;
            line-height: 1.65;
        }
        .cbt-introduction-chip {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4f91;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .cbt-introduction-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
        .cbt-introduction-info-card {
            padding: 18px;
            border: 1px solid #dce4ec;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
        }
        .cbt-introduction-info-card h3 {
            margin: 0 0 10px;
            font-size: 17px;
            line-height: 1.2;
        }
        .cbt-introduction-info-card p {
            margin: 0;
            color: #526172;
            line-height: 1.65;
        }
        .cbt-introduction-steps {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .cbt-introduction-step {
            display: grid;
            grid-template-columns: 64px minmax(0, 1fr);
            gap: 14px;
            align-items: start;
            padding: 18px;
            border: 1px solid #dce4ec;
            border-radius: 18px;
            background: #fbfdff;
        }
        .cbt-introduction-step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            min-height: 64px;
            border-radius: 18px;
            background: linear-gradient(180deg, #dbeafe 0%, #bfdbfe 100%);
            color: #0f4fa8;
            font-size: 22px;
            font-weight: 800;
        }
        .cbt-introduction-step h3 {
            margin: 0 0 8px;
            font-size: 18px;
            line-height: 1.2;
        }
        .cbt-introduction-step p {
            margin: 0;
            color: #526172;
            line-height: 1.6;
        }
        .cbt-introduction-feature-groups {
            display: grid;
            gap: 18px;
        }
        .cbt-introduction-feature-group {
            padding: 20px;
            border: 1px solid #dce4ec;
            border-radius: 20px;
            background: #fafcff;
        }
        .cbt-introduction-feature-group h3 {
            margin: 0 0 8px;
            font-size: 20px;
            line-height: 1.2;
        }
        .cbt-introduction-feature-group > p {
            margin: 0 0 16px;
            color: #566376;
            line-height: 1.6;
        }
        .cbt-introduction-feature-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .cbt-introduction-feature-card {
            padding: 18px;
            border: 1px solid #dce4ec;
            border-radius: 18px;
            background: #ffffff;
        }
        .cbt-introduction-feature-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .cbt-introduction-feature-card h4 {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
        }
        .cbt-introduction-feature-card p {
            margin: 0;
            color: #536172;
            line-height: 1.6;
        }
        .cbt-introduction-feature-stack {
            display: grid;
            gap: 12px;
            margin-top: 14px;
        }
        .cbt-introduction-feature-stack strong {
            display: block;
            margin-bottom: 4px;
            color: #0f172a;
            font-size: 12px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .cbt-introduction-guidance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .cbt-introduction-guidance-card {
            padding: 18px;
            border: 1px solid #dce4ec;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
        }
        .cbt-introduction-guidance-card h3 {
            margin: 0 0 10px;
            font-size: 17px;
            line-height: 1.25;
        }
        .cbt-introduction-guidance-card p {
            margin: 0;
            color: #526172;
            line-height: 1.65;
        }
        .cbt-introduction-links-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
        .cbt-introduction-link-card,
        .cbt-introduction-link-card.is-disabled {
            display: block;
            padding: 18px;
            border: 1px solid #dce4ec;
            border-radius: 18px;
            background: #ffffff;
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.03);
        }
        .cbt-introduction-link-card {
            color: #0f172a;
            transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
        }
        .cbt-introduction-link-card:hover,
        .cbt-introduction-link-card:focus {
            transform: translateY(-1px);
            border-color: #9bbce6;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.06);
            outline: none;
        }
        .cbt-introduction-link-card.is-disabled {
            color: #475569;
            background: #f8fafc;
            opacity: 0.96;
            cursor: default;
        }
        .cbt-introduction-link-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .cbt-introduction-link-head h3 {
            margin: 0;
            font-size: 18px;
            line-height: 1.2;
        }
        .cbt-introduction-link-card p {
            margin: 0;
            color: #536172;
            line-height: 1.65;
        }
        .cbt-introduction-link-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        .cbt-introduction-badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .cbt-introduction-badge.is-available {
            background: #dcfce7;
            color: #166534;
        }
        .cbt-introduction-badge.is-admin {
            background: #fee2e2;
            color: #991b1b;
        }
        .cbt-introduction-badge.is-restricted {
            background: #fef3c7;
            color: #92400e;
        }
        .cbt-introduction-badge.is-group {
            background: #eff6ff;
            color: #1d4f91;
        }
        .cbt-introduction-link-cta {
            margin-top: 14px;
            color: #0f4fa8;
            font-size: 13px;
            font-weight: 700;
        }
        .cbt-introduction-muted {
            color: #64748b;
        }
        @media (max-width: 1080px) {
            .cbt-introduction-hero,
            .cbt-introduction-grid-3,
            .cbt-introduction-links-grid {
                grid-template-columns: 1fr;
            }
            .cbt-introduction-feature-grid,
            .cbt-introduction-steps,
            .cbt-introduction-guidance-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 782px) {
            .cbt-introduction-hero,
            .cbt-introduction-section {
                padding: 20px;
            }
            .cbt-introduction-section-header {
                flex-direction: column;
            }
            .cbt-introduction-metric-grid {
                grid-template-columns: 1fr 1fr;
            }
            .cbt-introduction-step {
                grid-template-columns: 56px minmax(0, 1fr);
            }
            .cbt-introduction-step-number {
                width: 56px;
                min-height: 56px;
                border-radius: 16px;
                font-size: 20px;
            }
        }
        @media (max-width: 640px) {
            .cbt-introduction-metric-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="cbt-introduction-shell">
        <section class="cbt-introduction-hero">
            <div class="cbt-introduction-hero-copy">
                <span class="cbt-introduction-kicker">Plugin Guide</span>
                <h1>Introduction to CBT Exam System</h1>
                <p>Halaman ini merangkum cara memakai plugin dari awal sampai akhir. Tujuannya agar admin, operator, dan guru bisa memahami urutan kerja yang benar, tahu fungsi tiap menu, dan tidak bingung kapan harus membuka menu tertentu.</p>

                <div class="cbt-introduction-hero-note">
                    Fokus utama plugin ini adalah membantu sekolah mengelola <strong>persiapan data</strong>, <strong>pelaksanaan ujian</strong>, dan <strong>evaluasi hasil</strong> dalam satu alur yang saling terhubung.
                </div>
            </div>

            <aside class="cbt-introduction-hero-side">
                <div class="cbt-introduction-metric-grid">
                    <?php foreach ($hero_metrics as $metric): ?>
                        <div class="cbt-introduction-metric">
                            <strong><?php echo esc_html((string) ($metric['value'] ?? '0')); ?></strong>
                            <span><?php echo esc_html((string) ($metric['label'] ?? 'metrik')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cbt-introduction-side-card">
                    <h2>Urutan singkat</h2>
                    <ul>
                        <li>Siapkan sistem, subject, user, dan bank soal.</li>
                        <li>Rakit exam, siapkan token, lalu cetak kebutuhan administrasi.</li>
                        <li>Pantau results, baca analytics, lalu tutup dengan report exam.</li>
                    </ul>
                </div>
            </aside>
        </section>

        <nav class="cbt-introduction-nav" aria-label="Introduction Sections" role="tablist">
            <?php foreach ($section_nav as $nav_item): ?>
                <button
                    type="button"
                    class="cbt-introduction-nav-button"
                    data-introduction-tab="<?php echo esc_attr((string) ($nav_item['id'] ?? 'section')); ?>"
                    role="tab"
                    aria-selected="false"
                >
                    <?php echo esc_html((string) ($nav_item['label'] ?? 'Section')); ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="cbt-introduction-panels">
            <section id="apa-itu" class="cbt-introduction-section" data-introduction-panel="apa-itu" role="tabpanel">
                <div class="cbt-introduction-section-header">
                    <div>
                        <h2>Apa itu CBT Exam System</h2>
                        <p>CBT Exam System adalah plugin administrasi ujian berbasis komputer untuk WordPress. Halaman admin-nya dibagi ke beberapa kelompok fitur agar sekolah bisa memisahkan pekerjaan persiapan, operasional lapangan, monitoring hasil, dan maintenance sistem.</p>
                    </div>
                    <span class="cbt-introduction-chip">Panduan admin plugin</span>
                </div>

                <div class="cbt-introduction-grid-3">
                    <?php foreach ($overview_cards as $card): ?>
                        <article class="cbt-introduction-info-card">
                            <h3><?php echo esc_html((string) ($card['title'] ?? 'Informasi')); ?></h3>
                            <p><?php echo esc_html((string) ($card['description'] ?? '')); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="alur-pemakaian" class="cbt-introduction-section" data-introduction-panel="alur-pemakaian" role="tabpanel" hidden>
                <div class="cbt-introduction-section-header">
                    <div>
                        <h2>Alur Pemakaian</h2>
                        <p>Urutan di bawah ini adalah alur yang direkomendasikan untuk pemakaian normal. Mengikuti urutan ini membantu mencegah data master belum siap saat exam mulai dirakit atau hasil mulai dibaca.</p>
                    </div>
                    <span class="cbt-introduction-chip"><?php echo esc_html(number_format_i18n(count($workflow_steps))); ?> langkah</span>
                </div>

                <div class="cbt-introduction-steps">
                    <?php foreach ($workflow_steps as $step): ?>
                        <article class="cbt-introduction-step">
                            <div class="cbt-introduction-step-number"><?php echo esc_html((string) ($step['step'] ?? '0')); ?></div>
                            <div>
                                <h3><?php echo esc_html((string) ($step['label'] ?? 'Langkah')); ?></h3>
                                <p><?php echo esc_html((string) ($step['summary'] ?? '')); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="fitur-menu" class="cbt-introduction-section" data-introduction-panel="fitur-menu" role="tabpanel" hidden>
                <div class="cbt-introduction-section-header">
                    <div>
                        <h2>Penjelasan Fitur per Menu</h2>
                        <p>Setiap menu punya peran yang berbeda. Gunakan bagian ini sebagai peta fitur agar tim tahu menu mana yang dipakai untuk konfigurasi, mana yang dipakai untuk pelaksanaan ujian, dan mana yang dipakai setelah ujian selesai.</p>
                    </div>
                    <span class="cbt-introduction-chip"><?php echo esc_html(number_format_i18n(count($feature_groups))); ?> kelompok</span>
                </div>

                <div class="cbt-introduction-feature-groups">
                    <?php foreach ($feature_groups as $group): ?>
                        <section class="cbt-introduction-feature-group">
                            <h3><?php echo esc_html((string) ($group['title'] ?? 'Kelompok Menu')); ?></h3>
                            <p><?php echo esc_html((string) ($group['description'] ?? '')); ?></p>

                            <div class="cbt-introduction-feature-grid">
                                <?php foreach ((array) ($group['items'] ?? []) as $item): ?>
                                    <article class="cbt-introduction-feature-card">
                                        <div class="cbt-introduction-feature-card-head">
                                            <h4><?php echo esc_html((string) ($item['label'] ?? 'Menu')); ?></h4>
                                            <span class="cbt-introduction-badge is-group"><?php echo esc_html((string) ($group['title'] ?? 'Kelompok')); ?></span>
                                        </div>

                                        <p><?php echo esc_html((string) ($item['summary'] ?? '')); ?></p>

                                        <div class="cbt-introduction-feature-stack">
                                            <div>
                                                <strong>Kapan dipakai</strong>
                                                <p><?php echo esc_html((string) ($item['when_to_use'] ?? '')); ?></p>
                                            </div>
                                            <div>
                                                <strong>Hasil yang diharapkan</strong>
                                                <p><?php echo esc_html((string) ($item['output'] ?? '')); ?></p>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="workflow" class="cbt-introduction-section" data-introduction-panel="workflow" role="tabpanel" hidden>
                <div class="cbt-introduction-section-header">
                    <div>
                        <h2>Rekomendasi Alur Kerja</h2>
                        <p>Bagian ini membantu memilih menu yang tepat sesuai fase pekerjaan. Tidak semua menu dibuka setiap hari, jadi penting untuk tahu prioritasnya berdasarkan konteks operasional.</p>
                    </div>
                    <span class="cbt-introduction-chip">Panduan praktis</span>
                </div>

                <div class="cbt-introduction-guidance-grid">
                    <?php foreach ($workflow_guidance as $guidance): ?>
                        <article class="cbt-introduction-guidance-card">
                            <h3><?php echo esc_html((string) ($guidance['title'] ?? 'Panduan')); ?></h3>
                            <p><?php echo esc_html((string) ($guidance['description'] ?? '')); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="quick-links" class="cbt-introduction-section" data-introduction-panel="quick-links" role="tabpanel" hidden>
                <div class="cbt-introduction-section-header">
                    <div>
                        <h2>Quick Links</h2>
                        <p>Quick link di bawah mengikuti izin akun yang sedang login. Jika menu tidak tersedia untuk akun ini, kartunya tetap dijelaskan tetapi tidak bisa diklik.</p>
                    </div>
                    <span class="cbt-introduction-chip"><?php echo esc_html(number_format_i18n($quick_link_available_count)); ?> link aktif</span>
                </div>

                <div class="cbt-introduction-links-grid">
                    <?php foreach ($quick_links as $link): ?>
                        <?php
                        $tone = (string) ($link['access_tone'] ?? 'restricted');
                        $can_open = !empty($link['can_open']);
                        $card_class = 'cbt-introduction-link-card';
                        if (!$can_open) {
                            $card_class .= ' is-disabled';
                        }
                        ?>
                        <?php if ($can_open): ?>
                            <a href="<?php echo esc_url((string) ($link['url'] ?? '#')); ?>" class="<?php echo esc_attr($card_class); ?>">
                        <?php else: ?>
                            <div class="<?php echo esc_attr($card_class); ?>" aria-disabled="true">
                        <?php endif; ?>
                                <div class="cbt-introduction-link-head">
                                    <div>
                                        <h3><?php echo esc_html((string) ($link['label'] ?? 'Menu')); ?></h3>
                                    </div>
                                    <span class="cbt-introduction-badge is-<?php echo esc_attr($tone); ?>">
                                        <?php echo esc_html((string) ($link['access_label'] ?? 'Akses')); ?>
                                    </span>
                                </div>

                                <p><?php echo esc_html((string) ($link['summary'] ?? '')); ?></p>

                                <div class="cbt-introduction-link-meta">
                                    <span class="cbt-introduction-badge is-group"><?php echo esc_html((string) ($link['group_title'] ?? 'Kelompok')); ?></span>
                                </div>

                                <div class="cbt-introduction-link-cta">
                                    <?php if ($can_open): ?>
                                        Buka menu
                                    <?php else: ?>
                                        <span class="cbt-introduction-muted"><?php echo esc_html((string) ($link['access_hint'] ?? 'Tidak tersedia dari akun ini.')); ?></span>
                                    <?php endif; ?>
                                </div>
                        <?php if ($can_open): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>

    <script>
        (function () {
            const tabButtons = Array.from(document.querySelectorAll('[data-introduction-tab]'));
            const panels = Array.from(document.querySelectorAll('[data-introduction-panel]'));

            function setActiveTab(tabName) {
                const nextTab = String(tabName || 'apa-itu');

                tabButtons.forEach((button) => {
                    const isActive = button.getAttribute('data-introduction-tab') === nextTab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach((panel) => {
                    panel.hidden = panel.getAttribute('data-introduction-panel') !== nextTab;
                });
            }

            tabButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    setActiveTab(button.getAttribute('data-introduction-tab') || 'apa-itu');
                });
            });

            setActiveTab('apa-itu');
        }());
    </script>
</div>
