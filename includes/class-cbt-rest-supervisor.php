<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Supervisor_Routes
{
    public static function supervisor_dashboard(WP_REST_Request $request)
    {
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);
        if ($user_id <= 0 || !CBT_Auth::is_supervisor_role($role)) {
            return new WP_Error('forbidden', 'Supervisor dashboard hanya untuk guru atau admin.', ['status' => 403]);
        }

        if (!class_exists('CBT_Supervisor_Dashboard_Service')) {
            return new WP_Error('service_unavailable', 'Supervisor dashboard service tidak tersedia.', ['status' => 500]);
        }

        return rest_ensure_response(
            CBT_Supervisor_Dashboard_Service::build_dashboard_payload($user_id, $role, [
                'tab' => $request->get_param('tab'),
                'exam_id' => $request->get_param('exam_id'),
                'kelas' => $request->get_param('kelas'),
                'ruang' => $request->get_param('ruang'),
                'student_keyword' => $request->get_param('student_keyword'),
                'status' => $request->get_param('status'),
                'roster_page' => $request->get_param('roster_page'),
                'attempts_page' => $request->get_param('attempts_page'),
                'security_page' => $request->get_param('security_page'),
                'security_severity' => $request->get_param('security_severity'),
                'security_event_type' => $request->get_param('security_event_type'),
                'security_device_type' => $request->get_param('security_device_type'),
                'attendance_page' => $request->get_param('attendance_page'),
                'attendance_status' => $request->get_param('attendance_status'),
            ])
        );
    }

    public static function supervisor_reset_login(WP_REST_Request $request)
    {
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);
        if ($user_id <= 0 || !CBT_Auth::is_supervisor_role($role)) {
            return new WP_Error('forbidden', 'Supervisor action hanya untuk guru atau admin.', ['status' => 403]);
        }

        if (!class_exists('CBT_Supervisor_Dashboard_Service')) {
            return new WP_Error('service_unavailable', 'Supervisor dashboard service tidak tersedia.', ['status' => 500]);
        }

        $attempt_id = absint(self::get_request_payload_value($request, 'attempt_id'));
        if ($attempt_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id wajib diisi.', ['status' => 400]);
        }

        $result = CBT_Supervisor_Dashboard_Service::reset_login_for_attempt($attempt_id, $user_id, $role);
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(array_merge([
            'ok' => true,
        ], $result));
    }
}
