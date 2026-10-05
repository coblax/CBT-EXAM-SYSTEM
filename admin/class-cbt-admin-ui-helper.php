<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_UI_Helper
{
    /**
     * add_query_arg() WordPress tidak meng-encode nilai, sehingga notifikasi cbt_msg/cbt_err yang
     * memuat "#", "&" atau "%" terpotong di browser ("Soal #12 disimpan" tampil sebagai "Soal").
     * Nilai yang masih mentah di-encode ulang; nilai yang sudah ter-encode dibiarkan.
     *
     * @param mixed $location
     * @return mixed
     */
    public static function encode_notice_query_args($location)
    {
        if (!is_string($location) || (strpos($location, 'cbt_msg=') === false && strpos($location, 'cbt_err=') === false)) {
            return $location;
        }

        // Fragment sah hanya berupa slug di ujung URL, mis. "#security" atau "#cbt-question-preview-12".
        $fragment = '';
        if (preg_match('/#[A-Za-z][A-Za-z0-9_-]*$/', $location, $fragment_match, PREG_OFFSET_CAPTURE) === 1) {
            $fragment = (string) $fragment_match[0][0];
            $location = substr($location, 0, (int) $fragment_match[0][1]);
        }

        $encoded = preg_replace_callback(
            '/([?&])(cbt_msg|cbt_err)=(.*?)(?=&[A-Za-z0-9_\[\]%-]+=|$)/s',
            static function (array $matches): string {
                $value = (string) $matches[3];
                if (preg_match('/^[A-Za-z0-9\-._~%+]*$/', $value) === 1) {
                    return (string) $matches[0];
                }

                // wp_safe_redirect() sudah mengubah spasi/UTF-8 menjadi %XX sebelum filter ini jalan;
                // decode dulu agar tidak ter-encode ganda (sanitize_text_field membuang sisa %XX).
                return $matches[1] . $matches[2] . '=' . rawurlencode(rawurldecode($value));
            },
            $location
        );

        return (is_string($encoded) ? $encoded : $location) . $fragment;
    }

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
