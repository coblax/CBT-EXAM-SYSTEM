<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_UI_Helper
{
    public static function render_empty_state(array $args = []): string
    {
        $title = trim((string) ($args['title'] ?? 'Belum ada data'));
        $message = trim((string) ($args['message'] ?? 'Data belum tersedia untuk ditampilkan.'));
        $tone = sanitize_html_class((string) ($args['tone'] ?? 'neutral'));
        $class = trim('cbt-admin-empty-state cbt-admin-empty-state--' . $tone . ' ' . (string) ($args['class'] ?? ''));
        $action_label = trim((string) ($args['action_label'] ?? ''));
        $action_url = trim((string) ($args['action_url'] ?? ''));
        $action_class = trim((string) ($args['action_class'] ?? 'button button-secondary cbt-admin-btn--secondary'));
        $secondary_label = trim((string) ($args['secondary_label'] ?? ''));
        $secondary_url = trim((string) ($args['secondary_url'] ?? ''));
        $secondary_class = trim((string) ($args['secondary_class'] ?? 'button cbt-admin-btn--ghost'));

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <div class="cbt-admin-empty-state__mark" aria-hidden="true"></div>
            <div class="cbt-admin-empty-state__body">
                <h3><?php echo esc_html($title !== '' ? $title : 'Belum ada data'); ?></h3>
                <?php if ($message !== ''): ?>
                    <p><?php echo esc_html($message); ?></p>
                <?php endif; ?>
                <?php if (($action_label !== '' && $action_url !== '') || ($secondary_label !== '' && $secondary_url !== '')): ?>
                    <div class="cbt-admin-empty-state__actions">
                        <?php if ($action_label !== '' && $action_url !== ''): ?>
                            <a class="<?php echo esc_attr($action_class); ?>" href="<?php echo esc_url($action_url); ?>"><?php echo esc_html($action_label); ?></a>
                        <?php endif; ?>
                        <?php if ($secondary_label !== '' && $secondary_url !== ''): ?>
                            <a class="<?php echo esc_attr($secondary_class); ?>" href="<?php echo esc_url($secondary_url); ?>"><?php echo esc_html($secondary_label); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }

    public static function render_table_empty_state(int $colspan, array $args = []): string
    {
        $colspan = max(1, $colspan);

        return sprintf(
            '<tr class="cbt-admin-empty-row"><td colspan="%d">%s</td></tr>',
            $colspan,
            self::render_empty_state($args)
        );
    }
}
