<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!current_theme_supports('title-tag')): ?>
        <title><?php echo esc_html(wp_get_document_title()); ?></title>
    <?php endif; ?>
    <?php echo CBT_Frontend::render_canonical_critical_css(); ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('cbt-frontend-minimal-template'); ?>>
<?php wp_body_open(); ?>
<div class="cbt-frontpage">
    <a class="cbt-frontpage__skip" href="#cbt-exam-app">Lewati ke aplikasi CBT</a>
    <?php echo CBT_Frontend::render_canonical_frontend_shell(); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
