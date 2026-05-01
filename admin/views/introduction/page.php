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

        .cbt-introduction-page {
            max-width: 1280px;
            margin: 20px auto;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--cbt-text-main);
            /* Import modern font */
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        }

        .cbt-introduction-page * {
            box-sizing: border-box;
        }

        .cbt-introduction-shell {
            display: grid;
            gap: 20px;
            position: relative;
        }
        
        /* Background effects */
        .cbt-introduction-shell::before {
            content: '';
            position: absolute;
            top: -100px;
            left: -100px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, rgba(255,255,255,0) 70%);
            z-index: -1;
            border-radius: 50%;
        }
        
        .cbt-introduction-shell::after {
            content: '';
            position: absolute;
            bottom: -100px;
            right: -100px;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.08) 0%, rgba(255,255,255,0) 70%);
            z-index: -1;
            border-radius: 50%;
        }

        /* Glassmorphism Hero Section */
        .cbt-introduction-hero {
            display: grid;
            grid-template-columns: 1.4fr 0.6fr;
            gap: 24px;
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

        .cbt-introduction-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
        }

        .cbt-introduction-kicker {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--cbt-primary-light), #e0e7ff);
            color: var(--cbt-primary-hover);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 16px;
            box-shadow: var(--cbt-shadow-sm);
        }

        .cbt-introduction-hero h1 {
            margin: 0 0 12px;
            font-size: 38px;
            font-weight: 800;
            line-height: 1.1;
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.02em;
        }

        .cbt-introduction-hero p {
            margin: 0;
            font-size: 15px;
            line-height: 1.7;
            color: var(--cbt-text-muted);
        }

        .cbt-introduction-hero-note {
            margin-top: 16px;
            padding: 16px;
            border-radius: var(--cbt-radius-md);
            background: rgba(59, 130, 246, 0.05);
            border-left: 4px solid var(--cbt-primary);
            color: #334155;
            font-size: 14px;
            line-height: 1.6;
        }

        .cbt-introduction-hero-side {
            display: grid;
            gap: 16px;
            align-content: start;
        }

        .cbt-introduction-metric-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .cbt-introduction-metric {
            padding: 16px;
            border-radius: var(--cbt-radius-md);
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--cbt-border);
            text-align: center;
            transition: var(--cbt-transition);
            box-shadow: var(--cbt-shadow-sm);
        }

        .cbt-introduction-metric:hover {
            transform: translateY(-3px);
            box-shadow: var(--cbt-shadow-md), var(--cbt-shadow-glow);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .cbt-introduction-metric strong {
            display: block;
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--cbt-primary), var(--cbt-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }

        .cbt-introduction-metric span {
            display: block;
            margin-top: 6px;
            color: var(--cbt-text-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .cbt-introduction-side-card {
            padding: 20px;
            border-radius: var(--cbt-radius-md);
            background: linear-gradient(180deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.8) 100%);
            border: 1px solid var(--cbt-border);
            box-shadow: var(--cbt-shadow-sm);
        }

        .cbt-introduction-side-card h2 {
            margin: 0 0 12px;
            font-size: 16px;
            font-weight: 700;
            color: var(--cbt-text-main);
        }

        .cbt-introduction-side-card ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .cbt-introduction-side-card li {
            position: relative;
            padding-left: 24px;
            margin-bottom: 10px;
            color: var(--cbt-text-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .cbt-introduction-side-card li::before {
            content: '✓';
            position: absolute;
            left: 0;
            top: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--cbt-primary-light);
            color: var(--cbt-primary);
            font-size: 9px;
            font-weight: bold;
        }

        /* Modern Navigation Tabs */
        .cbt-introduction-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px;
            border-radius: var(--cbt-radius-lg);
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--cbt-border);
            box-shadow: var(--cbt-shadow-sm);
            position: sticky;
            top: 20px;
            z-index: 10;
        }

        .cbt-introduction-nav-button {
            padding: 10px 20px;
            border-radius: 999px;
            background: transparent;
            border: none;
            color: var(--cbt-text-muted);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--cbt-transition);
            position: relative;
            overflow: hidden;
        }

        .cbt-introduction-nav-button::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--cbt-primary);
            border-radius: 999px;
            opacity: 0;
            transform: scale(0.9);
            transition: var(--cbt-transition);
            z-index: -1;
        }

        .cbt-introduction-nav-button:hover {
            color: var(--cbt-primary);
            background: rgba(59, 130, 246, 0.05);
        }

        .cbt-introduction-nav-button.is-active {
            color: var(--cbt-text-inverse);
            box-shadow: var(--cbt-shadow-md);
        }

        .cbt-introduction-nav-button.is-active::before {
            opacity: 1;
            transform: scale(1);
            background: linear-gradient(135deg, var(--cbt-primary), var(--cbt-secondary));
        }

        /* Content Sections */
        .cbt-introduction-panels {
            display: grid;
            gap: 16px;
        }

        .cbt-introduction-section {
            padding: 24px;
            border-radius: var(--cbt-radius-lg);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid var(--cbt-border);
            box-shadow: var(--cbt-shadow-md);
            animation: fadeIn 0.4s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cbt-introduction-section[hidden] {
            display: none !important;
        }

        .cbt-introduction-section-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--cbt-border);
        }

        .cbt-introduction-section-header h2 {
            margin: 0 0 10px;
            font-size: 24px;
            font-weight: 800;
            color: var(--cbt-text-main);
            letter-spacing: -0.01em;
        }

        .cbt-introduction-section-header p {
            margin: 0;
            max-width: 800px;
            color: var(--cbt-text-muted);
            font-size: 15px;
            line-height: 1.6;
        }

        .cbt-introduction-chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--cbt-primary-light), #e0e7ff);
            color: var(--cbt-primary-hover);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            box-shadow: var(--cbt-shadow-sm);
        }

        /* Cards & Grids */
        .cbt-introduction-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .cbt-introduction-info-card {
            padding: 20px;
            border-radius: var(--cbt-radius-md);
            background: var(--cbt-bg-card);
            border: 1px solid var(--cbt-border);
            transition: var(--cbt-transition);
            position: relative;
            overflow: hidden;
        }

        .cbt-introduction-info-card:hover {
            transform: translateY(-3px);
            background: var(--cbt-bg-card-hover);
            box-shadow: var(--cbt-shadow-md);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .cbt-introduction-info-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 4px; height: 100%;
            background: linear-gradient(180deg, var(--cbt-primary), var(--cbt-secondary));
            opacity: 0;
            transition: var(--cbt-transition);
        }

        .cbt-introduction-info-card:hover::before {
            opacity: 1;
        }

        .cbt-introduction-info-card h3 {
            margin: 0 0 8px;
            font-size: 18px;
            font-weight: 700;
        }

        .cbt-introduction-info-card p {
            margin: 0;
            color: var(--cbt-text-muted);
            line-height: 1.6;
            font-size: 14px;
        }

        /* Steps */
        .cbt-introduction-steps {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .cbt-introduction-step {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            padding: 16px;
            border-radius: var(--cbt-radius-md);
            background: var(--cbt-bg-card);
            border: 1px solid var(--cbt-border);
            transition: var(--cbt-transition);
            align-items: start;
        }

        .cbt-introduction-step:hover {
            transform: translateX(3px);
            background: var(--cbt-bg-card-hover);
            box-shadow: var(--cbt-shadow-md);
            border-color: rgba(59, 130, 246, 0.2);
        }

        .cbt-introduction-step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--cbt-primary), var(--cbt-secondary));
            color: white;
            font-size: 20px;
            font-weight: 800;
            box-shadow: var(--cbt-shadow-sm);
        }

        .cbt-introduction-step h3 {
            margin: 0 0 6px;
            font-size: 16px;
            font-weight: 700;
        }

        .cbt-introduction-step p {
            margin: 0;
            color: var(--cbt-text-muted);
            line-height: 1.5;
            font-size: 14px;
        }

        /* Feature Groups */
        .cbt-introduction-feature-groups {
            display: grid;
            gap: 20px;
        }

        .cbt-introduction-feature-group {
            padding: 24px;
            border-radius: var(--cbt-radius-lg);
            background: rgba(248, 250, 252, 0.7);
            border: 1px solid var(--cbt-border);
        }

        .cbt-introduction-feature-group h3 {
            margin: 0 0 10px;
            font-size: 22px;
            font-weight: 800;
        }

        .cbt-introduction-feature-group > p {
            margin: 0 0 16px;
            color: var(--cbt-text-muted);
            font-size: 15px;
            line-height: 1.6;
        }

        .cbt-introduction-feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .cbt-introduction-feature-card {
            padding: 16px;
            border-radius: var(--cbt-radius-md);
            background: #ffffff;
            border: 1px solid var(--cbt-border);
            transition: var(--cbt-transition);
        }

        .cbt-introduction-feature-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--cbt-shadow-md);
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
            font-size: 16px;
            font-weight: 700;
        }

        .cbt-introduction-feature-card p {
            margin: 0;
            color: var(--cbt-text-muted);
            line-height: 1.5;
            font-size: 13px;
        }

        .cbt-introduction-feature-stack {
            display: grid;
            gap: 12px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed var(--cbt-border);
        }

        .cbt-introduction-feature-stack strong {
            display: block;
            margin-bottom: 4px;
            color: var(--cbt-text-main);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .cbt-introduction-feature-stack p {
            font-size: 13px;
        }

        /* Guidance Grid */
        .cbt-introduction-guidance-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .cbt-introduction-guidance-card {
            padding: 20px;
            border-radius: var(--cbt-radius-md);
            background: linear-gradient(145deg, #ffffff, rgba(248, 250, 252, 0.8));
            border: 1px solid var(--cbt-border);
            transition: var(--cbt-transition);
        }

        .cbt-introduction-guidance-card:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: var(--cbt-shadow-md);
        }

        .cbt-introduction-guidance-card h3 {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 700;
            color: var(--cbt-primary-hover);
        }

        /* Links Grid */
        .cbt-introduction-links-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .cbt-introduction-link-card {
            display: flex;
            flex-direction: column;
            padding: 20px;
            border-radius: var(--cbt-radius-md);
            background: #ffffff;
            border: 1px solid var(--cbt-border);
            text-decoration: none;
            color: inherit;
            transition: var(--cbt-transition);
            height: 100%;
        }

        .cbt-introduction-link-card:not(.is-disabled):hover {
            transform: translateY(-3px);
            border-color: rgba(59, 130, 246, 0.4);
            box-shadow: var(--cbt-shadow-md), var(--cbt-shadow-glow);
        }

        .cbt-introduction-link-card.is-disabled {
            background: #f8fafc;
            opacity: 0.7;
            cursor: not-allowed;
            filter: grayscale(100%);
        }

        .cbt-introduction-link-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .cbt-introduction-link-head h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .cbt-introduction-link-card p {
            margin: 0 0 16px;
            color: var(--cbt-text-muted);
            line-height: 1.5;
            font-size: 13px;
            flex-grow: 1;
        }

        .cbt-introduction-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .cbt-introduction-badge.is-available { background: #dcfce7; color: #166534; }
        .cbt-introduction-badge.is-admin { background: #fee2e2; color: #991b1b; }
        .cbt-introduction-badge.is-restricted { background: #fef3c7; color: #92400e; }
        .cbt-introduction-badge.is-group { background: var(--cbt-primary-light); color: var(--cbt-primary-hover); }

        .cbt-introduction-link-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: auto;
            color: var(--cbt-primary);
            font-size: 13px;
            font-weight: 700;
            transition: var(--cbt-transition);
        }

        .cbt-introduction-link-card:not(.is-disabled):hover .cbt-introduction-link-cta {
            gap: 12px;
            color: var(--cbt-primary-hover);
        }

        .cbt-introduction-link-cta::after {
            content: '→';
            font-size: 15px;
            transition: var(--cbt-transition);
        }

        .cbt-introduction-link-card.is-disabled .cbt-introduction-link-cta::after {
            content: none;
        }

        .cbt-introduction-muted {
            color: var(--cbt-text-muted);
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .cbt-introduction-hero {
                grid-template-columns: 1fr;
            }
            .cbt-introduction-grid-3,
            .cbt-introduction-links-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .cbt-introduction-section {
                padding: 20px;
            }
            .cbt-introduction-section-header {
                flex-direction: column;
                gap: 12px;
            }
            .cbt-introduction-grid-3,
            .cbt-introduction-links-grid,
            .cbt-introduction-steps,
            .cbt-introduction-feature-grid,
            .cbt-introduction-guidance-grid {
                grid-template-columns: 1fr;
            }
            .cbt-introduction-hero {
                padding: 24px 20px;
            }
            .cbt-introduction-hero h1 {
                font-size: 28px;
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
                    <h2>Urutan Singkat</h2>
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
                    <span class="cbt-introduction-chip">Panduan Admin</span>
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
                    <span class="cbt-introduction-chip"><?php echo esc_html(number_format_i18n(count($workflow_steps))); ?> Langkah</span>
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
                    <span class="cbt-introduction-chip"><?php echo esc_html(number_format_i18n(count($feature_groups))); ?> Kelompok</span>
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
                                                <strong>Kapan Dipakai</strong>
                                                <p><?php echo esc_html((string) ($item['when_to_use'] ?? '')); ?></p>
                                            </div>
                                            <div>
                                                <strong>Hasil yang Diharapkan</strong>
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
                    <span class="cbt-introduction-chip">Panduan Praktis</span>
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
                    <span class="cbt-introduction-chip"><?php echo esc_html(number_format_i18n($quick_link_available_count)); ?> Link Aktif</span>
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

                                <div class="cbt-introduction-link-meta" style="margin-bottom: 16px;">
                                    <span class="cbt-introduction-badge is-group"><?php echo esc_html((string) ($link['group_title'] ?? 'Kelompok')); ?></span>
                                </div>

                                <div class="cbt-introduction-link-cta">
                                    <?php if ($can_open): ?>
                                        Buka Menu
                                    <?php else: ?>
                                        <span class="cbt-introduction-muted"><?php echo esc_html((string) ($link['access_hint'] ?? 'Tidak tersedia untuk akun ini.')); ?></span>
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
                    if (panel.getAttribute('data-introduction-panel') === nextTab) {
                        panel.hidden = false;
                        // Trigger animation reset
                        panel.style.animation = 'none';
                        panel.offsetHeight; /* trigger reflow */
                        panel.style.animation = null;
                    } else {
                        panel.hidden = true;
                    }
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
