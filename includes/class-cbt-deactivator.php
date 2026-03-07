<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Deactivator
{
    public static function deactivate(): void
    {
        CBT_Runtime::deactivate();
        // Keep data and roles on deactivate to avoid accidental data loss.
    }
}
