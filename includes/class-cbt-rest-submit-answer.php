<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Submit_Answer_Routes
{
    public static function submit_answer(WP_REST_Request $request)
    {
        $attempt_id = (int) $request->get_param('attempt_id');
        $question_id = (int) $request->get_param('question_id');
        $answer_input = $request->get_param('answer');

        if ($attempt_id <= 0 || $question_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id and question_id are required', ['status' => 400]);
        }

        $result = self::submit_answers_batch_internal($attempt_id, [
            [
                'question_id' => $question_id,
                'answer' => $answer_input,
            ],
        ], CBT_Auth::current_user_id($request), CBT_Auth::current_user_role($request));
        if (is_wp_error($result)) {
            return $result;
        }

        $item = (isset($result['items'][0]) && is_array($result['items'][0])) ? $result['items'][0] : [];

        return rest_ensure_response([
            'attempt_id' => $attempt_id,
            'question_id' => (int) ($item['question_id'] ?? $question_id),
            'is_correct' => $item['is_correct'] ?? null,
            'score_awarded' => (float) ($item['score_awarded'] ?? 0),
            'cleared' => !empty($item['cleared']) ? 1 : 0,
            'deferred' => !empty($item['deferred']) ? 1 : 0,
        ]);
    }

    public static function submit_answers_batch(WP_REST_Request $request)
    {
        $attempt_id = (int) $request->get_param('attempt_id');
        $answers = $request->get_param('answers');
        if (!is_array($answers)) {
            $answers = [];
        }

        if ($attempt_id <= 0 || empty($answers)) {
            return new WP_Error('invalid_payload', 'attempt_id and answers are required', ['status' => 400]);
        }

        $result = self::submit_answers_batch_internal(
            $attempt_id,
            $answers,
            CBT_Auth::current_user_id($request),
            CBT_Auth::current_user_role($request)
        );

        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function submit_flow_metric(WP_REST_Request $request)
    {
        $user_id = method_exists('CBT_Auth', 'current_user_id')
            ? (int) CBT_Auth::current_user_id($request)
            : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $role = method_exists('CBT_Auth', 'current_user_role')
            ? (string) CBT_Auth::current_user_role($request)
            : '';
        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can log submit telemetry', ['status' => 403]);
        }

        $attempt_id = absint(self::get_request_payload_value($request, 'attempt_id'));
        $exam_id = absint(self::get_request_payload_value($request, 'exam_id'));
        $event = sanitize_key((string) self::get_request_payload_value($request, 'event'));
        $event_key = sanitize_text_field((string) self::get_request_payload_value($request, 'event_key'));
        $client_event_at_ms_raw = self::get_request_payload_value($request, 'client_event_at_ms');
        $client_event_at_ms = is_numeric($client_event_at_ms_raw) ? (int) $client_event_at_ms_raw : 0;
        $duration_raw = self::get_request_payload_value($request, 'duration_ms');
        $duration_ms = $duration_raw === null || $duration_raw === ''
            ? null
            : (is_numeric($duration_raw) ? (int) $duration_raw : -1);
        $phase_durations = self::get_request_payload_value($request, 'phase_durations');
        $phase_durations = is_array($phase_durations) ? $phase_durations : [];
        $meta = self::get_request_payload_value($request, 'meta');
        $meta = is_array($meta) ? $meta : [];

        if ($attempt_id <= 0) {
            return new WP_Error('invalid_submit_flow_metric_attempt', 'Attempt metric wajib diisi.', ['status' => 400]);
        }

        if ($exam_id <= 0) {
            return new WP_Error('invalid_submit_flow_metric_exam', 'Exam metric wajib diisi.', ['status' => 400]);
        }

        if ($event === '') {
            return new WP_Error('invalid_submit_flow_metric_event', 'Event metric wajib diisi.', ['status' => 400]);
        }

        if (
            !class_exists('CBT_Submit_Flow_Metrics_Service')
            || !CBT_Submit_Flow_Metrics_Service::is_allowed_event($event)
        ) {
            return new WP_Error('invalid_submit_flow_metric_event', 'Event submit flow tidak didukung.', ['status' => 400]);
        }

        if (trim($event_key) === '') {
            return new WP_Error('invalid_submit_flow_metric_key', 'Event key wajib diisi.', ['status' => 400]);
        }

        if ($client_event_at_ms <= 0) {
            return new WP_Error('invalid_submit_flow_metric_event_time', 'Timestamp event tidak valid.', ['status' => 400]);
        }

        if ($duration_ms !== null && $duration_ms < 0) {
            return new WP_Error('invalid_submit_flow_metric_duration', 'Duration metric tidak valid.', ['status' => 400]);
        }

        $attempt = self::get_attempt_for_submit_flow_metric($attempt_id, $user_id);
        if (is_wp_error($attempt)) {
            return $attempt;
        }

        if ((int) ($attempt['exam_id'] ?? 0) !== $exam_id) {
            return new WP_Error('invalid_submit_flow_metric_exam', 'Exam metric tidak cocok dengan attempt.', ['status' => 400]);
        }

        $result = class_exists('CBT_Submit_Flow_Metrics_Service')
            ? CBT_Submit_Flow_Metrics_Service::record_event(
                $attempt_id,
                $exam_id,
                $event,
                $event_key,
                $client_event_at_ms,
                $duration_ms,
                $phase_durations,
                array_merge(
                    $meta,
                    [
                        'user_id' => $user_id,
                        'attempt_status' => (string) ($attempt['status'] ?? ''),
                        'route' => method_exists($request, 'get_route') ? (string) $request->get_route() : '',
                    ]
                )
            )
            : ['recorded' => false, 'duplicate' => false, 'skipped' => true];

        return [
            'ok' => true,
            'duplicate' => !empty($result['duplicate']),
            'skipped' => !empty($result['skipped']),
        ];
    }
}
