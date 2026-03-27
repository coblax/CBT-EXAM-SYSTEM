<?php

if (!isset($cbt_admin_view_mode) || $cbt_admin_view_mode !== 'security') {
    $cbt_admin_view_mode = 'security';
}

require CBT_EXAM_SYSTEM_PATH . 'admin/views/setup/page.php';
