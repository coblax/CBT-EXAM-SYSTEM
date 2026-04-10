<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Start_Attempt_Idempotency_Service
{
    private const STORAGE_PREFIX = 'start_attempt_intent:';
    private const LOCK_PREFIX = 'start_attempt_intent_lock:';
    private const MAX_KEY_LENGTH = 96;
    private const PROCESSING_TTL_SECONDS = 45;
    private const QUEUED_TTL_SECONDS = 3600;
    private const FINAL_TTL_SECONDS = 600;
    private const QUEUED_REPLAY_GRACE_SECONDS = 3;

    public static function sanitize_key(string $raw_key): string
    {
        $raw_key = trim($raw_key);
        if ($raw_key === '') {
            return '';
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $raw_key) ?: '';
        if ($sanitized === '') {
            return '';
        }

        return substr($sanitized, 0, self::MAX_KEY_LENGTH);
    }

    /**
     * @return array<string,mixed>
     */
    public static function begin(int $user_id, int $exam_id, string $idempotency_key, string $queue_ticket = ''): array
    {
        $idempotency_key = self::sanitize_key($idempotency_key);
        $queue_ticket = self::sanitize_key($queue_ticket);

        if ($user_id <= 0 || $exam_id <= 0 || $idempotency_key === '') {
            return [
                'mode' => 'disabled',
            ];
        }

        $record = self::get_record($user_id, $exam_id, $idempotency_key);
        if (self::should_replay_record($record, $queue_ticket)) {
            return [
                'mode' => 'replay',
                'record' => $record,
                'response' => self::build_replay_response($record),
            ];
        }

        $claim = [
            'user_id' => $user_id,
            'exam_id' => $exam_id,
            'idempotency_key' => $idempotency_key,
            'lock_key' => self::LOCK_PREFIX . $user_id . ':' . $exam_id . ':' . $idempotency_key,
            'storage_key' => self::STORAGE_PREFIX . $user_id . ':' . $exam_id . ':' . $idempotency_key,
        ];

        $acquired = CBT_Cache::acquire_lock(
            $claim['lock_key'],
            self::PROCESSING_TTL_SECONDS,
            [
                'type' => 'start_attempt_idempotency',
                'user_id' => $user_id,
                'exam_id' => $exam_id,
                'idempotency_key' => $idempotency_key,
            ]
        );

        if (!$acquired) {
            $record = self::get_record($user_id, $exam_id, $idempotency_key);
            if (self::should_replay_record($record, $queue_ticket)) {
                return [
                    'mode' => 'replay',
                    'record' => $record,
                    'response' => self::build_replay_response($record),
                ];
            }

            return [
                'mode' => 'processing',
            ];
        }

        $record = self::get_record($user_id, $exam_id, $idempotency_key);
        if (self::should_replay_record($record, $queue_ticket)) {
            CBT_Cache::release_lock($claim['lock_key']);

            return [
                'mode' => 'replay',
                'record' => $record,
                'response' => self::build_replay_response($record),
            ];
        }

        self::set_record($claim['storage_key'], [
            'state' => 'processing',
            'response_kind' => '',
            'response_data' => [],
            'response_status' => 0,
            'attempt_id' => 0,
            'queue_ticket' => $queue_ticket,
            'created_at' => current_time('timestamp'),
            'updated_at' => current_time('timestamp'),
            'expires_at' => current_time('timestamp') + self::PROCESSING_TTL_SECONDS,
        ], self::PROCESSING_TTL_SECONDS);

        return [
            'mode' => 'claimed',
            'claim' => $claim,
        ];
    }

    /**
     * @param array<string,mixed> $claim
     * @param mixed $response
     */
    public static function complete(array $claim, string $state, $response): void
    {
        $storage_key = isset($claim['storage_key']) ? (string) $claim['storage_key'] : '';
        $lock_key = isset($claim['lock_key']) ? (string) $claim['lock_key'] : '';
        if ($storage_key === '' || $lock_key === '') {
            return;
        }

        $state = sanitize_key($state);
        if ($state === '' || $state === 'processing') {
            self::abandon($claim);
            return;
        }

        $normalized = self::normalize_response_record($state, $response);
        if (!is_array($normalized)) {
            self::abandon($claim);
            return;
        }

        $ttl = self::ttl_for_state($state);
        $now = current_time('timestamp');
        self::set_record($storage_key, array_merge($normalized, [
            'state' => $state,
            'updated_at' => $now,
            'expires_at' => $now + $ttl,
        ]), $ttl);

        CBT_Cache::release_lock($lock_key);
    }

    /**
     * @param array<string,mixed> $claim
     */
    public static function abandon(array $claim): void
    {
        $storage_key = isset($claim['storage_key']) ? (string) $claim['storage_key'] : '';
        $lock_key = isset($claim['lock_key']) ? (string) $claim['lock_key'] : '';

        if ($storage_key !== '') {
            CBT_Cache::delete($storage_key);
        }

        if ($lock_key !== '') {
            CBT_Cache::release_lock($lock_key);
        }
    }

    /**
     * @param array<string,mixed>|null $record
     * @return mixed
     */
    public static function build_replay_response(?array $record)
    {
        if (!is_array($record)) {
            return null;
        }

        $response_kind = sanitize_key((string) ($record['response_kind'] ?? ''));
        if ($response_kind === 'error') {
            $response_data = is_array($record['response_data'] ?? null) ? $record['response_data'] : [];

            return new WP_Error(
                (string) ($response_data['code'] ?? ''),
                (string) ($response_data['message'] ?? ''),
                $response_data['data'] ?? null
            );
        }

        $payload = is_array($record['response_data'] ?? null) ? $record['response_data'] : [];
        $status_code = max(0, (int) ($record['response_status'] ?? 200));
        if ($status_code > 0 && $status_code !== 200 && class_exists('WP_REST_Response')) {
            return new WP_REST_Response($payload, $status_code);
        }

        return rest_ensure_response($payload);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_record(int $user_id, int $exam_id, string $idempotency_key): ?array
    {
        $idempotency_key = self::sanitize_key($idempotency_key);
        if ($user_id <= 0 || $exam_id <= 0 || $idempotency_key === '') {
            return null;
        }

        $found = false;
        $record = CBT_Cache::get(
            self::STORAGE_PREFIX . $user_id . ':' . $exam_id . ':' . $idempotency_key,
            [],
            $found
        );

        return $found && is_array($record) ? $record : null;
    }

    private static function should_replay_record(?array $record, string $queue_ticket): bool
    {
        if (!is_array($record)) {
            return false;
        }

        $state = sanitize_key((string) ($record['state'] ?? ''));
        if (!in_array($state, ['queued', 'started', 'resumed', 'completed', 'terminal_error'], true)) {
            return false;
        }

        if ($state !== 'queued') {
            return true;
        }

        $updated_at = max(0, (int) ($record['updated_at'] ?? 0));
        if ($queue_ticket !== '') {
            return false;
        }

        return $updated_at > 0 && (current_time('timestamp') - $updated_at) <= self::QUEUED_REPLAY_GRACE_SECONDS;
    }

    /**
     * @param mixed $response
     * @return array<string,mixed>|null
     */
    private static function normalize_response_record(string $state, $response): ?array
    {
        if (is_wp_error($response)) {
            $error_data = $response->get_error_data();

            return [
                'response_kind' => 'error',
                'response_status' => is_array($error_data) ? (int) ($error_data['status'] ?? 0) : 0,
                'response_data' => [
                    'code' => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                    'data' => $error_data,
                ],
                'attempt_id' => is_array($error_data) ? (int) ($error_data['attempt_id'] ?? 0) : 0,
                'queue_ticket' => '',
                'created_at' => current_time('timestamp'),
            ];
        }

        $payload = $response;
        $status_code = $state === 'queued' ? 202 : 200;
        if (class_exists('WP_REST_Response') && $response instanceof WP_REST_Response) {
            $payload = method_exists($response, 'get_data') ? $response->get_data() : [];
            $status_code = method_exists($response, 'get_status') ? (int) $response->get_status() : $status_code;
        }

        if (!is_array($payload)) {
            return null;
        }

        return [
            'response_kind' => 'payload',
            'response_status' => $status_code,
            'response_data' => $payload,
            'attempt_id' => (int) ($payload['attempt_id'] ?? 0),
            'queue_ticket' => (string) ($payload['queue_ticket'] ?? ''),
            'created_at' => current_time('timestamp'),
        ];
    }

    /**
     * @param array<string,mixed> $record
     */
    private static function set_record(string $storage_key, array $record, int $ttl): void
    {
        CBT_Cache::set($storage_key, $record, max(1, $ttl), []);
    }

    private static function ttl_for_state(string $state): int
    {
        if ($state === 'queued') {
            return self::QUEUED_TTL_SECONDS;
        }

        return self::FINAL_TTL_SECONDS;
    }
}
