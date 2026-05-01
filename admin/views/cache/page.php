
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

            .cbt-cache-page {
            max-width: 1280px;
            margin: 20px auto;
            padding: 24px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--cbt-text-main);
            background: radial-gradient(circle at top left, #e0e7ff 0%, #f8fafc 40%, #f0fdf4 100%);
            border-radius: var(--cbt-radius-lg);
            box-sizing: border-box;
        }
        .cbt-cache-page * {
            box-sizing: border-box;
        }
            
        .cbt-cache-shell::before {
            content: ''; position: absolute; top: -150px; left: -100px; width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-cache-shell::after {
            content: ''; position: absolute; bottom: -100px; right: -50px; width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, rgba(255,255,255,0) 70%);
            z-index: -1; border-radius: 50%; pointer-events: none; filter: blur(60px);
        }
        .cbt-cache-shell {
                display: grid;
                gap: 18px;
                margin-top: 18px;
            
            position: relative;
            z-index: 1;
            isolation: isolate;
        }
            
        .cbt-cache-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--cbt-primary), var(--cbt-secondary), var(--cbt-accent));
        }
        .cbt-cache-hero {
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
            .cbt-cache-hero-copy {
                max-width: 700px;
            }
            .cbt-cache-kicker {
                display: inline-flex;
                align-items: center;
                min-height: 28px;
                padding: 0 12px;
                border-radius: 999px;
                background: #e8f1ff;
                color: #0f4fa8;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
            }
            .cbt-cache-hero h1 {
                margin: 12px 0 8px;
                font-size: 30px;
                line-height: 1.15;
            }
            .cbt-cache-hero p {
                margin: 0;
                color: #4b5563;
                font-size: 14px;
                line-height: 1.6;
            }
            .cbt-cache-overview {
                display: grid;
                gap: 10px;
                min-width: 260px;
            }
            .cbt-cache-pill {
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
            .cbt-cache-tabs {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .cbt-cache-tab {
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
            .cbt-cache-tab:hover,
            .cbt-cache-tab:focus {
                border-color: #2271b1;
                color: #0f4fa8;
                outline: none;
                box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.12);
            }
            .cbt-cache-tab.is-active {
                border-color: #2271b1;
                background: #2271b1;
                color: #ffffff;
                box-shadow: 0 10px 24px rgba(34, 113, 177, 0.18);
            }
            .cbt-cache-panel {
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
            .cbt-cache-panel.is-active {
                display: block;
            }
            .cbt-cache-panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 18px;
            }
            .cbt-cache-panel-header h2 {
                margin: 0 0 6px;
                font-size: 18px;
                line-height: 1.2;
            }
            .cbt-cache-panel-header p {
                margin: 0;
                color: #646970;
                line-height: 1.55;
            }
            .cbt-cache-chip {
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
            .cbt-cache-subtitle {
                margin: 0 0 12px;
                color: #0f172a;
                font-size: 16px;
                line-height: 1.3;
            }
            .cbt-cache-readiness-banner {
                border-radius: 18px;
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            }
            .cbt-cache-info-card {
                max-width: 980px;
                padding: 16px 18px;
                border: 1px solid #dfe7ef;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.03);
            }
            .cbt-cache-note-card {
                max-width: 1180px;
                padding: 14px 16px;
                border: 1px solid #dfe7ef;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }
            .cbt-cache-tools-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 16px;
                max-width: 1180px;
            }
            .cbt-cache-tool-card {
                padding: 18px;
                border: 1px solid #dfe7ef;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.03);
            }
            .cbt-cache-tool-card h3 {
                margin-top: 0;
                margin-bottom: 10px;
                font-size: 16px;
            }
            .cbt-cache-filter-form {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                max-width: 980px;
                margin: 8px 0 12px;
                padding: 16px 18px;
                border: 1px solid #dfe7ef;
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            }
            .cbt-cache-filter-form label {
                font-weight: 700;
                color: #0f172a;
            }
            .cbt-cache-table-wrap {
                max-width: 1180px;
                overflow: hidden;
                border: 1px solid #dbe5ef;
                border-radius: 18px;
                background: #fff;
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
            }
            .cbt-cache-page .widefat {
                margin: 0;
                border: 0;
                box-shadow: none;
            }
            .cbt-cache-page .widefat thead th {
                background: #f8fbff;
                color: #334155;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.02em;
            }
            .cbt-cache-page .widefat td,
            .cbt-cache-page .widefat th {
                padding-top: 12px;
                padding-bottom: 12px;
            }
            .cbt-cache-page .widefat tbody tr:hover {
                background: #f8fbff;
            }
            .cbt-cache-page .button {
                min-height: 40px;
                border-radius: 12px;
                padding: 0 14px;
            }
            .cbt-cache-page .button-primary {
                box-shadow: 0 10px 20px rgba(34, 113, 177, 0.18);
            }
            .cbt-cache-page input[type="number"],
            .cbt-cache-page input[type="text"],
            .cbt-cache-page select {
                min-height: 44px;
                padding: 0 14px;
                border: 1px solid #c9d7e6;
                border-radius: 14px;
                background: #f8fbff;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
                color: #0f172a;
            }
            .cbt-cache-page select {
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
                padding-right: 44px;
                cursor: pointer;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6.5L8 10.5L12 6.5' stroke='%23546A85' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 14px center;
                background-size: 16px 16px;
            }
            .cbt-cache-page .small-text {
                width: 140px;
            }
            .cbt-cache-page pre {
                margin: 0 0 12px;
                padding: 14px 16px;
                background: #f6f8fb;
                border: 1px solid #dfe7ef;
                border-radius: 16px;
                overflow: auto;
            }
            .cbt-cache-page code {
                background: rgba(15, 23, 42, 0.04);
                padding: 0.08em 0.35em;
                border-radius: 6px;
            }
            .cbt-cache-page .tablenav {
                max-width: 1180px;
            }
            .cbt-cache-page .tablenav-pages {
                float: none !important;
                margin: 0 !important;
                display: flex;
                gap: 12px;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .cbt-cache-page .pagination-links .page-numbers {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 36px;
                height: 36px;
                padding: 0 10px;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                background: #fff;
                color: #2271b1;
                font-size: 14px;
                font-weight: 600;
                line-height: 1;
                text-decoration: none;
                box-sizing: border-box;
            }
            .cbt-cache-page .pagination-links .page-numbers.current {
                border-color: #2271b1;
                background: #2271b1;
                color: #fff;
            }
            .cbt-cache-page .pagination-links .page-numbers:hover,
            .cbt-cache-page .pagination-links .page-numbers:focus {
                border-color: #2271b1;
                color: #135e96;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }
            @media (max-width: 960px) {
                .cbt-cache-hero,
                .cbt-cache-panel-header {
                    flex-direction: column;
                    align-items: stretch;
                }
                .cbt-cache-overview {
                    min-width: 0;
                }
            }
            @media (max-width: 782px) {
                .cbt-cache-page {
                    margin-right: 10px;
                }
                .cbt-cache-hero,
                .cbt-cache-panel {
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
                .cbt-cache-filter-form {
                    align-items: stretch;
                }
            }
        </style>
        <div class="wrap cbt-cache-page" data-cbt-cache-default-tab="summary">
            <div class="cbt-cache-shell">
                <section class="cbt-cache-hero">
                    <div class="cbt-cache-hero-copy">
                        <span class="cbt-cache-kicker">Performance</span>
                        <h1>CBT Cache</h1>
                        <p>Monitor readiness Redis, kelola namespace cache CBT, release lock, dan reset UI state dari satu halaman operasional yang lebih rapi. Fokusnya tetap sama: cepat tahu status runtime dan cepat ambil tindakan yang tepat.</p>
                    </div>
                    <div class="cbt-cache-overview" aria-hidden="true">
                        <span class="cbt-cache-pill"><?php echo esc_html('Readiness: ' . $readiness_meta['label']); ?></span>
                        <span class="cbt-cache-pill"><?php echo esc_html(sprintf('%d namespace', $namespace_total)); ?></span>
                        <span class="cbt-cache-pill"><?php echo esc_html(sprintf('%d lock aktif', $active_lock_total)); ?></span>
                        <span class="cbt-cache-pill"><?php echo esc_html(sprintf('%d UI state', count($ui_states))); ?></span>
                    </div>
                </section>
            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

                <div class="cbt-cache-tabs" role="tablist" aria-label="Navigasi CBT Cache">
                    <button type="button" class="cbt-cache-tab" data-cbt-cache-tab="summary" role="tab" aria-selected="false">Summary</button>
                    <button type="button" class="cbt-cache-tab" data-cbt-cache-tab="tools" role="tab" aria-selected="false">Tools</button>
                    <button type="button" class="cbt-cache-tab" data-cbt-cache-tab="namespaces" role="tab" aria-selected="false">Namespaces</button>
                    <button type="button" class="cbt-cache-tab" data-cbt-cache-tab="locks" role="tab" aria-selected="false">Locks</button>
                    <button type="button" class="cbt-cache-tab" data-cbt-cache-tab="ui-state" role="tab" aria-selected="false">UI State</button>
                </div>

                <section class="cbt-cache-panel" data-cbt-cache-panel="summary" role="tabpanel">
                    <div class="cbt-cache-panel-header">
                        <div>
                            <h2>Redis Readiness & Configuration</h2>
                            <p>Lihat status object cache WordPress, probe Redis server, runtime buffer CBT, dan langkah berikutnya jika environment belum siap penuh.</p>
                        </div>
                        <span class="cbt-cache-chip"><?php echo esc_html($readiness_meta['label']); ?></span>
                    </div>

            <div class="cbt-cache-readiness-banner" style="margin:16px 0 20px; padding:16px 18px; border:1px solid #dcdcde; border-left:6px solid <?php echo esc_attr($readiness_meta['accent']); ?>; background:#fff;">
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <h2 style="margin:0;">Redis Readiness</h2>
                    <span style="display:inline-block; padding:4px 10px; border-radius:999px; background:<?php echo esc_attr($readiness_meta['background']); ?>; color:<?php echo esc_attr($readiness_meta['accent']); ?>; font-weight:600;">
                        <?php echo esc_html($readiness_meta['label']); ?>
                    </span>
                </div>
                <p style="margin:12px 0 0;"><?php echo esc_html(CBT_Admin_Cache_Service::cache_readiness_summary($health)); ?></p>
            </div>

            <?php if ($readiness === 'fallback'): ?>
                <div class="notice notice-warning">
                    <p><strong>Mode fallback aktif.</strong> Redis/object cache WordPress belum siap, jadi plugin CBT masih memakai transient WordPress untuk cache lintas request. Mode ini tetap aman sebagai fallback, tetapi bukan mode yang direkomendasikan untuk ujian serentak.</p>
                </div>
            <?php elseif ($readiness === 'partial'): ?>
                <div class="notice notice-warning">
                    <p><strong>Konfigurasi object cache belum lengkap.</strong> Ada sinyal setup Redis/object cache, tetapi CBT belum melihat runtime Redis yang benar-benar siap. Lanjutkan checklist pada halaman ini sampai status berubah menjadi <code>Ready</code>.</p>
                </div>
            <?php endif; ?>

            <?php if ($show_redis_rollback): ?>
                <h3 class="cbt-cache-subtitle" style="margin-top:24px;">Batalkan Redis</h3>
                <div class="cbt-cache-info-card">
                    <p style="margin-top:0;">Aksi ini membatalkan integrasi Redis yang disiapkan dari sisi WordPress: menghapus blok konfigurasi CBT Redis di <code>wp-config.php</code>, menghapus <code>object-cache.php</code> jika itu drop-in Redis yang valid, lalu menonaktifkan plugin <code>Redis Object Cache</code>.</p>
                    <p>Aksi ini tidak menghentikan service Redis OS. Jika Redis server juga ingin dimatikan, lakukan dari level server secara manual.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0 0;">
                        <?php wp_nonce_field('cbt_cache_action'); ?>
                        <input type="hidden" name="action" value="cbt_cache_action" />
                        <input type="hidden" name="operation" value="rollback_redis" />
                        <button type="submit" class="button button-secondary cbt-admin-btn--danger" onclick="return confirm('Batalkan integrasi Redis CBT dari WordPress?');">Batalkan Redis Sekali Klik</button>
                    </form>
                </div>
            <?php endif; ?>

            <h3 class="cbt-cache-subtitle">Configuration Snapshot</h3>
            <div class="cbt-cache-table-wrap">
            <table class="widefat striped" style="max-width:980px;">
                <tbody>
                <tr>
                    <th style="width:260px;">Readiness</th>
                    <td><code><?php echo esc_html($readiness); ?></code></td>
                </tr>
                <tr>
                    <th>WP_CACHE</th>
                    <td><?php echo CBT_Admin_Cache_Service::cache_boolean_label(!empty($health['wp_cache_enabled'])); ?></td>
                </tr>
                <tr>
                    <th>Object Cache Active</th>
                    <td><?php echo !empty($health['object_cache_active']) ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <th>Drop-in object-cache.php</th>
                    <td><?php echo !empty($health['object_cache_dropin_present']) ? 'Detected' : 'Not detected'; ?></td>
                </tr>
                <tr>
                    <th>Runtime Mode</th>
                    <td><code><?php echo esc_html((string) ($health['runtime_mode'] ?? '-')); ?></code></td>
                </tr>
                <tr>
                    <th>Backend Hint</th>
                    <td><code><?php echo esc_html((string) ($health['backend_hint'] ?? '-')); ?></code></td>
                </tr>
                <tr>
                    <th>Cache Group</th>
                    <td><code><?php echo esc_html((string) ($health['cache_group'] ?? '-')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Host</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($redis_config['host'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Port</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($redis_config['port'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Database</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($redis_config['database'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Prefix</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($redis_config['prefix'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Scheme</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($redis_config['scheme'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Client</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($redis_config['client'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Password Configured</th>
                    <td><?php echo CBT_Admin_Cache_Service::cache_boolean_label(!empty($redis_config['password_configured'])); ?></td>
                </tr>
                <tr>
                    <th>WP_REDIS_DISABLED</th>
                    <td>
                        <?php
                        echo array_key_exists('disabled', $redis_config) && $redis_config['disabled'] !== null
                            ? CBT_Admin_Cache_Service::cache_boolean_label(!empty($redis_config['disabled']))
                            : '-';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Redis Server</th>
                    <td>
                        <span style="display:inline-block; padding:3px 9px; border-radius:999px; background:<?php echo esc_attr($server_probe_meta['background']); ?>; color:<?php echo esc_attr($server_probe_meta['accent']); ?>; font-weight:600;">
                            <?php echo esc_html($server_probe_meta['label']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Redis Endpoint</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($server_probe['endpoint'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Redis Server Message</th>
                    <td><?php echo esc_html((string) ($server_probe['message'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th>Probe Status</th>
                    <td>
                        <span style="display:inline-block; padding:3px 9px; border-radius:999px; background:<?php echo esc_attr($probe_meta['background']); ?>; color:<?php echo esc_attr($probe_meta['accent']); ?>; font-weight:600;">
                            <?php echo esc_html($probe_meta['label']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Probe Message</th>
                    <td><?php echo esc_html((string) ($probe['message'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th>Probe Tested At</th>
                    <td><?php echo !empty($probe['tested_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $probe['tested_at'])) : '-'; ?></td>
                </tr>
                <tr>
                    <th>Runtime Buffer Enabled</th>
                    <td><?php echo CBT_Admin_Cache_Service::cache_boolean_label(!empty($runtime_buffer['enabled'])); ?></td>
                </tr>
                <tr>
                    <th>Runtime Buffer Ready</th>
                    <td><?php echo CBT_Admin_Cache_Service::cache_boolean_label(!empty($runtime_buffer['ready'])); ?></td>
                </tr>
                <tr>
                    <th>Runtime Buffer Status</th>
                    <td><code><?php echo esc_html((string) ($runtime_buffer['status'] ?? '-')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Fallback to DB</th>
                    <td><?php echo CBT_Admin_Cache_Service::cache_boolean_label(!empty($runtime_buffer['fallback_to_db'])); ?></td>
                </tr>
                <tr>
                    <th>Runtime Redis Extension</th>
                    <td><?php echo CBT_Admin_Cache_Service::cache_boolean_label(!empty($runtime_buffer['extension_available'])); ?></td>
                </tr>
                <tr>
                    <th>Runtime Redis Host</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($runtime_buffer_config['host'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Redis Port</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($runtime_buffer_config['port'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Redis Database</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($runtime_buffer_config['database'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Redis Prefix</th>
                    <td><code><?php echo esc_html(CBT_Admin_Cache_Service::cache_scalar_label($runtime_buffer_config['prefix'] ?? '')); ?></code></td>
                </tr>
                <tr>
                    <th>Runtime Probe Status</th>
                    <td>
                        <span style="display:inline-block; padding:3px 9px; border-radius:999px; background:<?php echo esc_attr($runtime_probe_meta['background']); ?>; color:<?php echo esc_attr($runtime_probe_meta['accent']); ?>; font-weight:600;">
                            <?php echo esc_html($runtime_probe_meta['label']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Runtime Message</th>
                    <td><?php echo esc_html((string) ($runtime_buffer['message'] ?? '-')); ?></td>
                </tr>
                <tr>
                    <th>Pending Flush Attempts</th>
                    <td><?php echo esc_html((string) ((int) ($runtime_buffer['pending_attempts'] ?? 0))); ?></td>
                </tr>
                <tr>
                    <th>Oldest Flush Age</th>
                    <td><?php echo esc_html((string) ((int) ($runtime_buffer['oldest_flush_age'] ?? 0))); ?>s</td>
                </tr>
                <tr>
                    <th>TTL Reference</th>
                    <td>
                        <?php foreach ($ttl_reference as $ttl_key => $ttl_value): ?>
                            <span style="display:inline-block; margin:0 12px 8px 0;"><code><?php echo esc_html((string) $ttl_key); ?></code>: <?php echo esc_html((string) $ttl_value); ?>s</span>
                        <?php endforeach; ?>
                    </td>
                </tr>
                </tbody>
            </table>
            </div>

            <?php if ($readiness !== 'ready' && !empty($next_steps)): ?>
                <h3 class="cbt-cache-subtitle" style="margin-top:24px;">Next Steps</h3>
                <div class="cbt-cache-info-card">
                    <ol style="margin:0; padding-left:20px;">
                        <?php foreach ($next_steps as $next_step): ?>
                            <li style="margin:0 0 8px;"><?php echo esc_html($next_step); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <?php if ($readiness !== 'ready'): ?>
                <h3 class="cbt-cache-subtitle" style="margin-top:24px;">One-Click Bootstrap</h3>
                <div class="cbt-cache-info-card">
                    <p style="margin-top:0;">Tombol ini mencoba menyiapkan Redis dari sisi WordPress dalam satu aksi: menulis config ke <code>wp-config.php</code>, install/activate plugin <code>Redis Object Cache</code> jika environment mengizinkan, lalu enable <code>object-cache.php</code> hanya jika server Redis benar-benar reachable.</p>
                    <p>Yang tidak bisa dipaksa dari tombol ini: install service Redis OS dan memperbaiki permission filesystem server yang diblokir host.</p>
                    <h3 style="margin:18px 0 8px;">Ubuntu Quick Guide</h3>
                    <ol style="margin:0 0 12px; padding-left:20px;">
                        <li style="margin:0 0 8px;">Install Redis server, tools, dan ekstensi PHP Redis.</li>
                        <li style="margin:0 0 8px;">Aktifkan service Redis lalu cek sampai <code>PONG</code>.</li>
                        <li style="margin:0 0 8px;">Restart PHP-FPM/web server agar ekstensi <code>redis</code> terbaca oleh WordPress.</li>
                        <li style="margin:0;">Kembali ke halaman ini lalu klik <code>Bootstrap Redis Sekali Klik</code>.</li>
                    </ol>
                    <pre><code><?php echo esc_html(
'sudo apt update
sudo apt install -y redis-server redis-tools php-redis
sudo systemctl enable --now redis-server
redis-cli ping
php -m | grep -i redis

# Deteksi versi PHP CLI aktif lalu restart PHP-FPM jika unitnya ada:
PHP_VER=$(php -v | head -n 1 | sed -E "s/^PHP ([0-9]+\.[0-9]+).*/\1/")
echo "Detected PHP version: ${PHP_VER}"
if systemctl list-unit-files "php${PHP_VER}-fpm.service" --no-legend 2>/dev/null | grep -q "php${PHP_VER}-fpm.service"; then
  sudo systemctl restart "php${PHP_VER}-fpm"
else
  echo "Service php${PHP_VER}-fpm tidak ditemukan. Cek unit PHP-FPM yang tersedia:"
  systemctl list-unit-files "php*-fpm.service" --no-legend 2>/dev/null || true
  echo "Jika kosong, kemungkinan server ini tidak memakai PHP-FPM."
fi
sudo systemctl restart nginx || sudo systemctl restart apache2'
                    ); ?></code></pre>
                    <p style="margin:0 0 12px;">Jika server bukan Ubuntu atau Anda tidak punya akses <code>sudo</code>, install service Redis tetap harus dilakukan dari level OS/panel server oleh admin sistem.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0 0;">
                        <?php wp_nonce_field('cbt_cache_action'); ?>
                        <input type="hidden" name="action" value="cbt_cache_action" />
                        <input type="hidden" name="operation" value="bootstrap_redis" />
                        <button type="submit" class="button button-primary">Bootstrap Redis Sekali Klik</button>
                    </form>
                </div>
            <?php endif; ?>

                </section>

                <section class="cbt-cache-panel" data-cbt-cache-panel="tools" role="tabpanel">
                    <div class="cbt-cache-panel-header">
                        <div>
                            <h2>Cache Tools</h2>
                            <p>Pilih aksi invalidate atau clear state berdasarkan scope masalahnya. Mulai dari yang paling kecil agar dampaknya tetap terkontrol.</p>
                        </div>
                        <span class="cbt-cache-chip">Operational Actions</span>
                    </div>

            <div class="cbt-cache-note-card" style="margin:0 0 16px;">
                <p style="margin:0 0 8px;"><strong>Cara baca menu di bawah:</strong> kata <code>invalidate</code> berarti cache dibuang agar request berikutnya membaca data terbaru dan membangun cache ulang. Ini tidak menghapus data ujian di database.</p>
                <p style="margin:0;"><strong>Saran penggunaan:</strong> mulai dari scope paling kecil dulu, misalnya <code>Attempt</code> atau <code>User</code>. Pakai <code>Invalidate All CBT</code> hanya jika perubahan atau masalahnya sudah luas.</p>
            </div>
		            <div class="cbt-cache-tools-grid">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_all" />
                    <h3 style="margin-top:0;">Invalidate All CBT</h3>
                    <p style="margin:0 0 8px;">Naikkan versi semua namespace cache CBT tanpa menyentuh cache plugin/site lain.</p>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah perubahan besar pada exam, bank soal, token, atau saat sulit menentukan cache mana yang stale.</p>
                    <p style="margin:0 0 12px;"><strong>Dampak:</strong> paling luas. Request berikutnya akan membangun ulang hampir semua cache CBT.</p>
                    <button type="submit" class="button button-primary cbt-admin-btn--warning">Invalidate Semua Namespace CBT</button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_catalog" />
                    <h3 style="margin-top:0;">Catalog</h3>
                    <p style="margin:0 0 8px;">Refresh daftar exam/mapel/token global yang dipakai seluruh user.</p>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah tambah/edit/hapus exam, subject, atau token global yang tampil di banyak halaman.</p>
                    <p style="margin:0 0 12px;"><strong>Dampak:</strong> hanya area katalog global, tidak spesifik ke satu user atau satu attempt.</p>
                    <button type="submit" class="button button-secondary">Invalidate Catalog</button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_exam" />
                    <h3 style="margin-top:0;">Exam Namespace</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah edit soal, durasi, jadwal, token, atau setting untuk satu exam tertentu.</p>
                    <p style="margin:0 0 8px;"><strong>Contoh:</strong> jika exam ID <code>12</code> berubah, isi <code>12</code> lalu invalidate.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-exam-id">Exam ID</label></p>
                    <input type="number" min="1" id="cbt-cache-exam-id" name="exam_id" class="small-text" placeholder="contoh: 12" required />
                    <p><button type="submit" class="button button-secondary">Invalidate Exam</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_user" />
                    <h3 style="margin-top:0;">User Namespace</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> saat perubahan hanya terkait satu user, misalnya hak akses, role, assignment exam, atau data user terasa belum ter-refresh.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> hanya cache milik user tersebut, tidak menyentuh user lain.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-user-id">User ID</label></p>
                    <input type="number" min="1" id="cbt-cache-user-id" name="user_id" class="small-text" placeholder="contoh: 45" required />
                    <p><button type="submit" class="button button-secondary">Invalidate User</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="warm_submission_context_exam" />
                    <h3 style="margin-top:0;">Warm Submission Context by Exam</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> sebelum traffic awal ujian padat agar konteks evaluasi jawaban sudah siap di Redis.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> memanaskan context submit/scoring objektif untuk semua soal aktif milik exam yang dipilih.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-submission-context-exam-id">Exam ID</label></p>
                    <input type="number" min="1" id="cbt-cache-submission-context-exam-id" name="exam_id" class="small-text" placeholder="contoh: 12" required />
                    <p><button type="submit" class="button button-primary cbt-admin-btn--success">Warm Submission Context</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="clear_submission_context_exam" />
                    <h3 style="margin-top:0;">Clear Submission Context by Exam</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah perubahan soal masif atau saat perlu memaksa warm ulang submission context per exam.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> menghapus pointer dan payload submission context untuk semua soal aktif di exam yang dipilih.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-submission-context-clear-exam-id">Exam ID</label></p>
                    <input type="number" min="1" id="cbt-cache-submission-context-clear-exam-id" name="exam_id" class="small-text" placeholder="contoh: 12" required />
                    <p><button type="submit" class="button button-secondary cbt-admin-btn--warning">Clear Submission Context</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="warm_login_snapshot_exam" />
                    <h3 style="margin-top:0;">Warm Login Snapshot by Exam</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> sebelum jam login padat untuk memanaskan full auth snapshot siswa target exam tertentu.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> menulis snapshot login global per siswa, termasuk hash password snapshot dan payload profil yang dipakai response login.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-login-exam-id">Exam ID</label></p>
                    <input type="number" min="1" id="cbt-cache-login-exam-id" name="exam_id" class="small-text" placeholder="contoh: 12" required />
                    <p><button type="submit" class="button button-primary cbt-admin-btn--success">Warm Login Snapshot</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="clear_login_snapshot_exam" />
                    <h3 style="margin-top:0;">Invalidate Login Snapshot by Exam</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah reset password massal, perubahan akun siswa target, atau saat perlu memaksa warm ulang per exam.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> menghapus snapshot login untuk seluruh siswa target exam yang dipilih.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-login-clear-exam-id">Exam ID</label></p>
                    <input type="number" min="1" id="cbt-cache-login-clear-exam-id" name="exam_id" class="small-text" placeholder="contoh: 12" required />
                    <p><button type="submit" class="button button-secondary">Invalidate Login Snapshot</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="warm_login_snapshot_user" />
                    <h3 style="margin-top:0;">Warm Login Snapshot by User</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> untuk debug atau pre-warm satu siswa tertentu tanpa menyentuh target exam lain.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> hanya snapshot login user tersebut yang diperbarui.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-login-user-id">User ID</label></p>
                    <input type="number" min="1" id="cbt-cache-login-user-id" name="user_id" class="small-text" placeholder="contoh: 45" required />
                    <p><button type="submit" class="button button-secondary">Warm Login User</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="clear_login_snapshot_user" />
                    <h3 style="margin-top:0;">Invalidate Login Snapshot by User</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> saat satu akun siswa baru direset password, ganti email/login, atau perlu dipaksa login lewat jalur canonical.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> hanya snapshot login user tersebut yang dibersihkan.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-login-clear-user-id">User ID</label></p>
                    <input type="number" min="1" id="cbt-cache-login-clear-user-id" name="user_id" class="small-text" placeholder="contoh: 45" required />
                    <p><button type="submit" class="button button-secondary">Invalidate Login User</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="invalidate_attempt" />
                    <h3 style="margin-top:0;">Attempt Namespace</h3>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> jika satu attempt ujian macet, jawaban terasa tidak sinkron, atau state attempt perlu dipaksa baca ulang.</p>
                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> paling sempit untuk data ujian. Aman dipakai saat masalahnya hanya pada satu peserta/satu attempt.</p>
                    <p style="margin:0 0 6px;"><label for="cbt-cache-attempt-id">Attempt ID</label></p>
                    <input type="number" min="1" id="cbt-cache-attempt-id" name="attempt_id" class="small-text" placeholder="contoh: 381" required />
                    <p><button type="submit" class="button button-secondary">Invalidate Attempt</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
                    <?php wp_nonce_field('cbt_cache_action'); ?>
                    <input type="hidden" name="action" value="cbt_cache_action" />
                    <input type="hidden" name="operation" value="clear_all_ui_state" />
                    <h3 style="margin-top:0;">UI State</h3>
                    <p style="margin:0 0 8px;">Hapus semua preferences dan attempt UI state CBT yang tersimpan di namespace plugin.</p>
                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> setelah ubah UI/frontend CBT dan Anda ingin semua browser membangun state tampilan baru dari nol.</p>
                    <p style="margin:0 0 12px;"><strong>Dampak:</strong> preference tampilan, posisi navigasi, atau state UI akan di-reset. Ini tidak menghapus hasil ujian di database.</p>
                    <p><button type="submit" class="button button-secondary cbt-admin-btn--warning">Clear Semua UI State CBT</button></p>
                </form>

		                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cbt-cache-tool-card">
		                    <?php wp_nonce_field('cbt_cache_action'); ?>
		                    <input type="hidden" name="action" value="cbt_cache_action" />
		                    <input type="hidden" name="operation" value="clear_attempt_ui_state" />
	                    <h3 style="margin-top:0;">Clear Attempt UI State</h3>
	                    <p style="margin:0 0 8px;"><strong>Kapan dipakai:</strong> jika satu peserta melihat UI ujian aneh, navigasi macet, palette soal salah, atau timer terasa tidak sinkron.</p>
	                    <p style="margin:0 0 8px;"><strong>Dampak:</strong> hanya state UI untuk satu attempt. Ini lebih aman daripada membersihkan semua UI state.</p>
	                    <p style="margin:0 0 6px;"><label for="cbt-cache-clear-attempt-id">Attempt ID</label></p>
	                    <input type="number" min="1" id="cbt-cache-clear-attempt-id" name="attempt_id" class="small-text" placeholder="contoh: 381" required />
	                    <p><button type="submit" class="button button-secondary cbt-admin-btn--warning">Clear UI State by Attempt</button></p>
	                </form>
	            </div>
	
                </section>

                <section class="cbt-cache-panel" data-cbt-cache-panel="namespaces" role="tabpanel">
                    <div class="cbt-cache-panel-header">
                        <div>
                            <h2>Namespaces</h2>
                            <p>Registry versi namespace cache CBT. Di sini Anda bisa memahami grup cache, memfilter namespace, dan melakukan prune untuk entri lama.</p>
                        </div>
                        <span class="cbt-cache-chip"><?php echo esc_html(sprintf('%d total', $namespace_total)); ?></span>
                    </div>

                <div class="cbt-cache-note-card" style="max-width:980px; margin:0 0 12px;">
                    <p style="margin:0 0 8px;"><strong>Bagian ini adalah registry namespace cache CBT.</strong> Setiap namespace menyimpan versi cache untuk scope tertentu seperti <code>__global__</code>, <code>exam:{id}</code>, <code>user:{id}</code>, atau <code>attempt:{id}</code>.</p>
                    <p style="margin:0 0 8px;">Saat namespace di-<code>invalidate</code>, versinya naik supaya request berikutnya membangun cache baru. Ini tidak menghapus data ujian di database.</p>
                    <p style="margin:0 0 12px;"><strong>Auto-prune:</strong> namespace yang tidak disentuh lebih dari <?php echo esc_html($namespace_prune_label); ?> akan dibersihkan otomatis dari registry. Retention ini sengaja lebih lama dari TTL cache namespace CBT agar tidak memunculkan kembali cache lama.</p>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <p style="margin:0;"><strong>Catatan:</strong> jangan hapus namespace secara manual dari registry. Jika entri namespace hilang, versi akan fallback ke <code>1</code> dan pada object cache persisten ada risiko cache lama versi awal terlihat lagi. Gunakan aksi <code>Invalidate</code>, bukan hapus manual.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                            <?php wp_nonce_field('cbt_cache_action'); ?>
                            <input type="hidden" name="action" value="cbt_cache_action" />
                            <input type="hidden" name="operation" value="prune_old_namespaces" />
                            <button type="submit" class="button button-secondary cbt-admin-btn--warning">Prune Namespace Lama</button>
                        </form>
                    </div>
                </div>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-cache-filter-form">
                    <input type="hidden" name="page" value="cbt-cache" />
                    <input type="hidden" name="cbt_lock_per_page" value="<?php echo (int) $lock_per_page; ?>" />
                    <input type="hidden" name="cbt_lock_show_stale" value="<?php echo $show_stale_locks ? '1' : '0'; ?>" />
                    <label for="cbt-namespace-filter">Filter namespace</label>
                    <select id="cbt-namespace-filter" name="cbt_namespace_filter">
                        <option value="">Semua grup</option>
                        <?php foreach ($namespace_filter_options as $namespace_filter_option): ?>
                            <option value="<?php echo esc_attr($namespace_filter_option); ?>" <?php selected($namespace_filter, $namespace_filter_option); ?>>
                                <?php
                                $namespace_option_meta = isset($namespace_group_meta[$namespace_filter_option]) && is_array($namespace_group_meta[$namespace_filter_option])
                                    ? $namespace_group_meta[$namespace_filter_option]
                                    : [];
                                echo esc_html((string) ($namespace_option_meta['label'] ?? $namespace_filter_option));
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="cbt-namespace-per-page">Per halaman</label>
                    <select id="cbt-namespace-per-page" name="cbt_namespace_per_page">
                        <?php foreach ([20, 40, 60, 80, 100] as $namespace_per_page_option): ?>
                            <option value="<?php echo (int) $namespace_per_page_option; ?>" <?php selected($namespace_per_page, $namespace_per_page_option); ?>>
                                <?php echo esc_html((string) $namespace_per_page_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button button-secondary">Terapkan</button>
                    <?php if ($namespace_filter !== ''): ?>
                        <a class="button button-link" href="<?php echo esc_url(add_query_arg([
                            'page' => 'cbt-cache',
                            'cbt_namespace_per_page' => $namespace_per_page,
                            'cbt_lock_per_page' => $lock_per_page,
                            'cbt_lock_show_stale' => $show_stale_locks ? 1 : 0,
                        ], admin_url('admin.php'))); ?>">Reset Filter</a>
                    <?php endif; ?>
                </form>
                <div class="cbt-cache-info-card" style="margin:-4px 0 12px;">
                    <p style="margin:0 0 8px;"><strong>Arti tiap grup namespace:</strong></p>
                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($namespace_filter_options as $namespace_filter_option): ?>
                            <?php
                            $namespace_option_meta = isset($namespace_group_meta[$namespace_filter_option]) && is_array($namespace_group_meta[$namespace_filter_option])
                                ? $namespace_group_meta[$namespace_filter_option]
                                : [];
                            $namespace_option_description = (string) ($namespace_option_meta['description'] ?? '');
                            if ($namespace_option_description === '') {
                                continue;
                            }
                            ?>
                            <li style="margin:0 0 6px;">
                                <code><?php echo esc_html($namespace_filter_option); ?></code>
                                : <?php echo esc_html($namespace_option_description); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($namespace_filter !== '' && !empty($namespace_group_meta[$namespace_filter]['description'])): ?>
                        <p style="margin:10px 0 0; padding-top:10px; border-top:1px solid #dcdcde;">
                            <strong>Filter aktif:</strong>
                            <code><?php echo esc_html($namespace_filter); ?></code>
                            : <?php echo esc_html((string) $namespace_group_meta[$namespace_filter]['description']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                    <div class="cbt-cache-table-wrap" style="max-width:980px;">
		            <table class="widefat striped" style="max-width:980px;">
		                <thead>
	                <tr>
	                    <th>Namespace</th>
	                    <th>Version</th>
                    <th>Last Invalidated</th>
                </tr>
                </thead>
	                <tbody>
	                <?php if (empty($visible_namespaces)): ?>
	                    <?php
                        echo CBT_Admin_UI_Helper::render_table_empty_state(3, [
                            'title' => $namespace_filter !== '' ? 'Tidak ada namespace sesuai filter' : 'Belum ada metadata namespace',
                            'message' => $namespace_filter !== ''
                                ? 'Tidak ada namespace cache yang cocok dengan filter saat ini.'
                                : 'Metadata namespace akan muncul setelah cache CBT mulai dipakai atau di-invalidate.',
                            'action_label' => $namespace_filter !== '' ? 'Reset Filter' : '',
                            'action_url' => $namespace_filter !== '' ? admin_url('admin.php?page=cbt-cache') : '',
                        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
	                <?php else: ?>
	                    <?php foreach ($visible_namespaces as $namespace): ?>
	                        <tr>
	                            <td><code><?php echo esc_html((string) ($namespace['namespace'] ?? '')); ?></code></td>
	                            <td><?php echo esc_html((string) ((int) ($namespace['version'] ?? 1))); ?></td>
	                            <td><?php echo !empty($namespace['invalidated_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $namespace['invalidated_at'])) : '-'; ?></td>
	                        </tr>
                    <?php endforeach; ?>
	                <?php endif; ?>
	                </tbody>
		            </table>
                    </div>
	                <div class="tablenav bottom" style="margin-top:10px; max-width:980px;">
                    <div class="tablenav-pages" style="float:none; margin:0; display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                        <span class="displaying-num">
                            <?php
                            echo esc_html(
                                $namespace_filter !== ''
                                    ? sprintf('Total namespace: %d hasil filter | Grup tersedia: %d', $namespace_total, $namespace_total_all)
                                    : sprintf('Total namespace: %d', $namespace_total)
                            );
                            ?>
                        </span>
                        <?php if (!empty($namespace_pagination_links)): ?>
                            <span class="pagination-links">
                                <?php foreach ($namespace_pagination_links as $namespace_pagination_link): ?>
                                    <?php echo wp_kses_post($namespace_pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                </section>

                <section class="cbt-cache-panel" data-cbt-cache-panel="locks" role="tabpanel">
                    <div class="cbt-cache-panel-header">
                        <div>
                            <h2>Locks</h2>
                            <p>Monitor active lock dan stale lock agar Anda bisa cepat tahu apakah ada proses yang macet, timeout, atau perlu dilepas manual.</p>
                        </div>
                        <span class="cbt-cache-chip"><?php echo esc_html(sprintf('Active %d / Stale %d', $active_lock_total, $stale_lock_total)); ?></span>
                    </div>

                <div class="cbt-cache-readiness-banner" style="max-width:1180px; margin:0 0 12px; padding:14px 16px; border:1px solid #dcdcde; border-left:6px solid <?php echo $stale_lock_total > 0 ? '#8a4b00' : '#135e36'; ?>; background:#fff;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                        <div>
                            <p style="margin:0 0 6px;"><strong><?php echo esc_html(sprintf('Active: %d | Stale: %d', $active_lock_total, $stale_lock_total)); ?></strong></p>
                            <p style="margin:0;">
                                <?php if ($stale_lock_total > 0): ?>
                                    Stale lock disembunyikan secara default. Biasanya ini hanya sisa metadata dari request yang timeout/crash dan sudah lewat masa berlaku.
                                <?php else: ?>
                                    Tidak ada stale lock di registry saat ini.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($stale_lock_total > 0): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                                <?php wp_nonce_field('cbt_cache_action'); ?>
                                <input type="hidden" name="action" value="cbt_cache_action" />
                                <input type="hidden" name="operation" value="release_stale_locks" />
                                <button type="submit" class="button button-secondary cbt-admin-btn--warning">Release Semua Stale Lock</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="cbt-cache-filter-form">
                    <input type="hidden" name="page" value="cbt-cache" />
                    <input type="hidden" name="cbt_namespace_per_page" value="<?php echo (int) $namespace_per_page; ?>" />
                    <input type="hidden" name="cbt_namespace_paged" value="<?php echo (int) $namespace_current_page; ?>" />
                    <label for="cbt-lock-per-page">Per halaman</label>
                    <select id="cbt-lock-per-page" name="cbt_lock_per_page">
                        <?php foreach ([20, 40, 60, 80, 100] as $lock_per_page_option): ?>
                            <option value="<?php echo (int) $lock_per_page_option; ?>" <?php selected($lock_per_page, $lock_per_page_option); ?>>
                                <?php echo esc_html((string) $lock_per_page_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="cbt-lock-show-stale" style="display:inline-flex; align-items:center; gap:6px;">
                        <input type="checkbox" id="cbt-lock-show-stale" name="cbt_lock_show_stale" value="1" <?php checked($show_stale_locks); ?> />
                        Tampilkan stale lock
                    </label>
                    <button type="submit" class="button button-secondary">Terapkan</button>
                </form>
                    <div class="cbt-cache-table-wrap">
		            <table class="widefat striped">
		                <thead>
	                <tr>
	                    <th>Lock Key</th>
	                    <th>Context</th>
                    <th>Expires</th>
                    <th>Status</th>
	                    <th>Aksi</th>
	                </tr>
	                </thead>
	                <tbody>
	                <?php if (empty($visible_locks)): ?>
                        <?php
                        echo CBT_Admin_UI_Helper::render_table_empty_state(5, [
                            'title' => (!$show_stale_locks && $stale_lock_total > 0) ? 'Tidak ada lock aktif' : 'Belum ada lock CBT aktif',
                            'message' => (!$show_stale_locks && $stale_lock_total > 0)
                                ? sprintf('%d stale lock sedang disembunyikan. Aktifkan opsi tampilkan stale lock jika perlu diperiksa.', $stale_lock_total)
                                : 'Registry lock kosong. Ini normal saat tidak ada proses CBT yang sedang memakai lock.',
                            'action_label' => (!$show_stale_locks && $stale_lock_total > 0) ? 'Tampilkan Stale Lock' : '',
                            'action_url' => (!$show_stale_locks && $stale_lock_total > 0) ? admin_url('admin.php?page=cbt-cache&cbt_lock_show_stale=1') : '',
                            'tone' => (!$show_stale_locks && $stale_lock_total > 0) ? 'warning' : 'neutral',
                        ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
	                <?php else: ?>
	                    <?php foreach ($visible_locks as $lock): ?>
	                        <tr>
	                            <td><code><?php echo esc_html((string) ($lock['lock_key'] ?? '')); ?></code></td>
	                            <td><code><?php echo esc_html(wp_json_encode((array) ($lock['context'] ?? []))); ?></code></td>
	                            <td><?php echo !empty($lock['expires_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $lock['expires_at'])) : '-'; ?></td>
                            <td><?php echo !empty($lock['is_stale']) ? 'Stale' : 'Active'; ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('cbt_cache_action'); ?>
                                    <input type="hidden" name="action" value="cbt_cache_action" />
                                    <input type="hidden" name="operation" value="release_lock" />
                                    <input type="hidden" name="lock_key" value="<?php echo esc_attr((string) ($lock['lock_key'] ?? '')); ?>" />
                                        <button type="submit" class="button button-small cbt-admin-btn--warning">Release</button>
                                </form>
                            </td>
                        </tr>
	                    <?php endforeach; ?>
	                <?php endif; ?>
	                </tbody>
		            </table>
                    </div>
	                <div class="tablenav bottom" style="margin-top:10px;">
                    <div class="tablenav-pages" style="float:none; margin:0; display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                        <span class="displaying-num">
                            <?php
                            echo esc_html(
                                $show_stale_locks
                                    ? sprintf('Total lock ditampilkan: %d', $lock_total)
                                    : sprintf('Active lock ditampilkan: %d | Stale disembunyikan: %d', $lock_total, $stale_lock_total)
                            );
                            ?>
                        </span>
                        <?php if (!empty($lock_pagination_links)): ?>
                            <span class="pagination-links">
                                <?php foreach ($lock_pagination_links as $lock_pagination_link): ?>
                                    <?php echo wp_kses_post($lock_pagination_link); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                </section>

                <section class="cbt-cache-panel" data-cbt-cache-panel="ui-state" role="tabpanel">
                    <div class="cbt-cache-panel-header">
                        <div>
                            <h2>UI State Registry</h2>
                            <p>Daftar preference UI dan state attempt yang tersimpan. Gunakan aksi clear jika ada tampilan frontend yang terasa stale atau tidak sinkron.</p>
                        </div>
                        <span class="cbt-cache-chip"><?php echo esc_html(sprintf('%d entries', count($ui_states))); ?></span>
                    </div>
            <div class="cbt-cache-table-wrap">
            <table class="widefat striped">
                <thead>
                <tr>
                    <th>Type</th>
                    <th>User</th>
                    <th>Attempt</th>
                    <th>Updated</th>
                    <th>Expires</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($ui_states)): ?>
                    <?php
                    echo CBT_Admin_UI_Helper::render_table_empty_state(6, [
                        'title' => 'Belum ada UI state tersimpan',
                        'message' => 'Preference UI dan state attempt akan muncul setelah siswa atau admin memakai fitur yang menyimpan state.',
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php else: ?>
                    <?php foreach ($ui_states as $ui_state): ?>
                        <?php
                        $entry_type = (string) ($ui_state['type'] ?? '');
                        $entry_user_id = (int) ($ui_state['user_id'] ?? 0);
                        $entry_attempt_id = (int) ($ui_state['attempt_id'] ?? 0);
                        ?>
                        <tr>
                            <td><code><?php echo esc_html($entry_type); ?></code></td>
                            <td>
                                <?php echo esc_html($user_labels[$entry_user_id] ?? ('User #' . $entry_user_id)); ?>
                            </td>
                            <td><?php echo $entry_attempt_id > 0 ? esc_html((string) $entry_attempt_id) : '-'; ?></td>
                            <td><?php echo !empty($ui_state['updated_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $ui_state['updated_at'])) : '-'; ?></td>
                            <td><?php echo !empty($ui_state['expires_at']) ? esc_html(wp_date('Y-m-d H:i:s', (int) $ui_state['expires_at'])) : '-'; ?></td>
                            <td>
                                <?php if ($entry_type === 'preferences' && $entry_user_id > 0): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('cbt_cache_action'); ?>
                                        <input type="hidden" name="action" value="cbt_cache_action" />
                                        <input type="hidden" name="operation" value="clear_ui_preferences" />
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $entry_user_id); ?>" />
                                    <button type="submit" class="button button-small cbt-admin-btn--warning">Clear</button>
                                    </form>
                                <?php elseif ($entry_type === 'attempt_state' && $entry_attempt_id > 0): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('cbt_cache_action'); ?>
                                        <input type="hidden" name="action" value="cbt_cache_action" />
                                        <input type="hidden" name="operation" value="clear_attempt_ui_state" />
                                        <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $entry_attempt_id); ?>" />
                                    <button type="submit" class="button button-small cbt-admin-btn--warning">Clear</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
                </section>
                <script>
                    (function () {
                        const page = document.querySelector('.cbt-cache-page');
                        const tabButtons = Array.from(document.querySelectorAll('[data-cbt-cache-tab]'));
                        const tabPanels = Array.from(document.querySelectorAll('[data-cbt-cache-panel]'));
                        const tabStorageKey = 'cbt-cache-active-tab';
                        const defaultTab = page ? String(page.getAttribute('data-cbt-cache-default-tab') || 'summary') : 'summary';

                        function activateTab(tabId, persist) {
                            let hasTarget = false;

                            tabButtons.forEach((button) => {
                                const isActive = button.getAttribute('data-cbt-cache-tab') === tabId;
                                button.classList.toggle('is-active', isActive);
                                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                                if (isActive) {
                                    hasTarget = true;
                                }
                            });

                            tabPanels.forEach((panel) => {
                                const isActive = panel.getAttribute('data-cbt-cache-panel') === tabId;
                                panel.classList.toggle('is-active', isActive);
                            });

                            if (persist && hasTarget && window.localStorage) {
                                window.localStorage.setItem(tabStorageKey, tabId);
                            }
                        }

                        if (!page || tabButtons.length === 0 || tabPanels.length === 0) {
                            return;
                        }

                        let initialTab = defaultTab;
                        if (window.localStorage) {
                            const savedTab = window.localStorage.getItem(tabStorageKey);
                            if (savedTab && tabPanels.some((panel) => panel.getAttribute('data-cbt-cache-panel') === savedTab)) {
                                initialTab = savedTab;
                            }
                        }

                        activateTab(initialTab, false);

                        tabButtons.forEach((button) => {
                            button.addEventListener('click', function () {
                                activateTab(String(button.getAttribute('data-cbt-cache-tab') || ''), true);
                            });
                        });

                        Array.from(page.querySelectorAll('form')).forEach((form) => {
                            form.addEventListener('submit', function () {
                                const parentPanel = form.closest('[data-cbt-cache-panel]');
                                if (!parentPanel || !window.localStorage) {
                                    return;
                                }

                                const tabId = String(parentPanel.getAttribute('data-cbt-cache-panel') || '');
                                if (tabId !== '') {
                                    window.localStorage.setItem(tabStorageKey, tabId);
                                }
                            });
                        });
                    })();
                </script>
            </div>
        </div>
