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
        if (class_exists('CBT_Login_Snapshot_Freshness_Service')) {
            CBT_Login_Snapshot_Freshness_Service::deactivate();
        }
        if (class_exists('CBT_Adaptive_Load_Service')) {
            CBT_Adaptive_Load_Service::deactivate();
        }
        if (class_exists('CBT_Security_Event_Ingest')) {
            CBT_Security_Event_Ingest::deactivate();
        }
        if (class_exists('CBT_Login_Readiness_Warm_Queue_Service')) {
            CBT_Login_Readiness_Warm_Queue_Service::deactivate();
        }
        // Keep data and roles on deactivate to avoid accidental data loss.
    }
}
