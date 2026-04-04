<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Deactivator
{
    public static function deactivate(): void
    {
        CBT_Runtime::deactivate();
        if (class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
            CBT_Exam_Availability_Auto_Warm_Service::deactivate();
        }
        // Keep data and roles on deactivate to avoid accidental data loss.
    }
}
