<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Diagnostics_Routes
{
    public static function permission_diagnostics_admin(WP_REST_Request $request)
    {
        $decoded = CBT_Auth::verify_request_token($request);
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);
        if ($user_id <= 0 || !CBT_Auth::is_admin_role($role)) {
            return new WP_Error('forbidden', 'You do not have permission to access this endpoint', ['status' => 403]);
        }

        $can_manage_options = function_exists('user_can')
            ? user_can($user_id, 'manage_options')
            : current_user_can('manage_options');
        if (!$can_manage_options) {
            return new WP_Error('forbidden', 'You do not have permission to access this endpoint', ['status' => 403]);
        }

        return true;
    }

    public static function exam_cache_test(WP_REST_Request $request)
    {
        $exam_id = (int) $request->get_param('exam_id');
        $force_warmup = absint($request->get_param('force_warmup')) === 1;

        if ($exam_id <= 0) {
            return new WP_Error('invalid_exam_id', 'Exam ID tidak valid.', ['status' => 400]);
        }

        if (!class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
            return new WP_Error('snapshot_service_unavailable', 'Layanan snapshot tidak tersedia.', ['status' => 500]);
        }

        $latency_start = microtime(true);
        $warmup_error = '';

        try {
            if ($force_warmup && method_exists(self::class, 'warm_exam_start_attempt_snapshot')) {
                self::warm_exam_start_attempt_snapshot($exam_id);
            }

            $diagnostics = CBT_Exam_Start_Attempt_Snapshot_Cache::get_exam_snapshot_diagnostics($exam_id);
        } catch (Throwable $throwable) {
            return new WP_Error(
                'snapshot_diagnostics_failed',
                'Diagnostik snapshot gagal dijalankan: ' . $throwable->getMessage(),
                ['status' => 500]
            );
        }

        if ($force_warmup && sanitize_key((string) ($diagnostics['snapshot_status'] ?? '')) !== 'ready') {
            $warmup_error = (string) ($diagnostics['snapshot_message'] ?? '');
        }

        $latency_ms = round((microtime(true) - $latency_start) * 1000, 2);
        $redis_available = !empty($diagnostics['redis_available']);
        $question_count = max(0, (int) ($diagnostics['question_count'] ?? 0));
        $snapshot_item_count = max(0, (int) ($diagnostics['snapshot_item_count'] ?? 0));

        return rest_ensure_response([
            'exam_id' => $exam_id,
            'redis_status' => $redis_available ? 'connected' : 'disconnected',
            'ping_success' => $redis_available,
            'latency_ms' => $latency_ms,
            'snapshot_status' => $diagnostics['snapshot_status'] ?? 'unknown',
            'snapshot_message' => (string) ($diagnostics['snapshot_message'] ?? ''),
            'snapshot_miss_reason' => (string) ($diagnostics['snapshot_miss_reason'] ?? ''),
            'snapshot_miss_reason_label' => (string) ($diagnostics['snapshot_miss_reason_label'] ?? ''),
            'item_count' => $snapshot_item_count > 0 ? $snapshot_item_count : $question_count,
            'question_count' => $question_count,
            'payload_bytes' => max(0, (int) ($diagnostics['snapshot_payload_bytes'] ?? 0)),
            'ttl_seconds' => (int) ($diagnostics['snapshot_ttl_seconds'] ?? -2),
            'warmup_attempted' => $force_warmup,
            'warmup_error' => $warmup_error,
            'diagnostics' => $diagnostics,
        ]);
    }
}
