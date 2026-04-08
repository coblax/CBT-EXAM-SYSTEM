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
        if (class_exists('CBT_Exam_Preflight_Service')) {
            CBT_Exam_Preflight_Service::deactivate();
        }
        if (class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            CBT_Snapshot_Auto_Heal_Queue_Service::deactivate();
        }
        // Keep data and roles on deactivate to avoid accidental data loss.
    }
}
