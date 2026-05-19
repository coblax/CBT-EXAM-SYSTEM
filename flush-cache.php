<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';
wp_cache_flush();
echo "Cache flushed!";
