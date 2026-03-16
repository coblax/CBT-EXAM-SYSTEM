<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Cache_Page
{
    public static function render(): void
    {
        if (!CBT_Admin_Cache_Service::can_manage_cache()) {
            wp_die('Unauthorized');
        }

        $context = CBT_Admin_Cache_Service::build_page_context($_GET);
        extract($context, EXTR_SKIP);

        require CBT_EXAM_SYSTEM_PATH . 'admin/views/cache/page.php';
    }

    public static function render_runtime_notice(): void
    {
        $context = CBT_Admin_Cache_Service::get_runtime_notice_context();
        if (!is_array($context)) {
            return;
        }

        $cache_url = (string) ($context['cache_url'] ?? admin_url('admin.php?page=cbt-cache'));
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>CBT Cache masih berjalan pada mode fallback.</strong>
                Redis/object cache WordPress belum aktif, sehingga cache lintas request masih memakai transient.
                <a href="<?php echo esc_url($cache_url); ?>">Buka CBT Cache</a> untuk melihat checklist aktivasi Redis.
            </p>
        </div>
        <?php
    }
}
