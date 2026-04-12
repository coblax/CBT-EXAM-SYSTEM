<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Start_Attempt_Opening_State_Service
{
    private const STORAGE_PREFIX = 'start_attempt_opening_state:';
    private const TRANSIENT_TTL_SECONDS = 90;
    private const QUEUE_WAITING_TTL_SECONDS = 120;
    private const FINAL_TTL_SECONDS = 600;

    /** @var array<int,string> */
    private const ALLOWED_STATES = [
        'resume_lookup',
        'queue_waiting',
        'attempt_creating',
        'attempt_created',
        'bootstrap_session',
        'bootstrap_questions',
        'attempt_finalizing',
        'ready',
        'completed',
        'terminal_error',
    ];

    /** @var array<int,string> */
    private const ALLOWED_REASONS = [
        'resume_index_miss',
        'resume_db_miss',
        'queue_admission_wait',
        'lock_owner_active',
        'attempt_insert_in_progress',
        'entry_snapshot_pending',
        'session_snapshot_pending',
        'question_window_pending',
        'attempt_finalizing',
        'attempt_ready',
        'attempt_completed',
        'token_invalid',
        'forbidden',
        'not_found',
    ];

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    public static function write_state(int $user_id, int $exam_id, string $opening_state, string $opening_reason, array $context = []): ?array
    {
        $user_id = absint($user_id);
        $exam_id = absint($exam_id);
        $opening_state = self::sanitize_state($opening_state);
        $opening_reason = self::sanitize_reason($opening_reason);

        if ($user_id <= 0 || $exam_id <= 0 || $opening_state === '' || $opening_reason === '') {
            return null;
        }

        if (!method_exists('CBT_Cache', 'set')) {
            return self::normalize_record([
                'opening_state' => $opening_state,
                'opening_reason' => $opening_reason,
                'attempt_id' => max(0, (int) ($context['attempt_id'] ?? 0)),
                'queue_ticket' => self::sanitize_queue_ticket((string) ($context['queue_ticket'] ?? '')),
                'resume_source' => sanitize_key((string) ($context['resume_source'] ?? '')),
                'retry_after_ms' => max(0, (int) ($context['retry_after_ms'] ?? 0)),
                'created_at' => time(),
                'updated_at' => time(),
                'last_stage_at' => time(),
                'expires_at' => 0,
            ]);
        }

        $current = self::get_state($user_id, $exam_id);
        $now = time();
        $created_at = max(0, (int) ($current['created_at'] ?? 0));
        if ($created_at <= 0) {
            $created_at = $now;
        }

        $queue_ticket_created_at = (float) ($context['queue_ticket_created_at'] ?? 0);
        if ($queue_ticket_created_at > 0) {
            $created_at = max(1, (int) floor($queue_ticket_created_at));
        }

        $attempt_id = max(
            0,
            (int) ($context['attempt_id'] ?? ($current['attempt_id'] ?? 0))
        );
        $queue_ticket = self::sanitize_queue_ticket(
            (string) ($context['queue_ticket'] ?? ($current['queue_ticket'] ?? ''))
        );
        $resume_source = sanitize_key(
            (string) ($context['resume_source'] ?? ($current['resume_source'] ?? ''))
        );
        $retry_after_ms = max(
            0,
            (int) ($context['retry_after_ms'] ?? ($current['retry_after_ms'] ?? 0))
        );
        $last_stage_at = max(
            0,
            (int) ($context['last_stage_at'] ?? $now)
        );
        if ($last_stage_at <= 0) {
            $last_stage_at = $now;
        }

        $ttl = self::resolve_ttl_seconds($opening_state, $context);
        $record = [
            'opening_state' => $opening_state,
            'opening_reason' => $opening_reason,
            'attempt_id' => $attempt_id,
            'queue_ticket' => $queue_ticket,
            'resume_source' => $resume_source,
            'retry_after_ms' => $retry_after_ms,
            'created_at' => $created_at,
            'updated_at' => $now,
            'last_stage_at' => $last_stage_at,
            'expires_at' => $now + $ttl,
        ];

        CBT_Cache::set(self::storage_key($user_id, $exam_id), $record, $ttl, []);
        return self::normalize_record($record);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_state(int $user_id, int $exam_id): ?array
    {
        $user_id = absint($user_id);
        $exam_id = absint($exam_id);
        if ($user_id <= 0 || $exam_id <= 0) {
            return null;
        }

        if (!method_exists('CBT_Cache', 'get')) {
            return null;
        }

        $found = false;
        $record = CBT_Cache::get(self::storage_key($user_id, $exam_id), [], $found);
        if (!$found || !is_array($record)) {
            return null;
        }

        return self::normalize_record($record);
    }

    public static function clear(int $user_id, int $exam_id): void
    {
        $user_id = absint($user_id);
        $exam_id = absint($exam_id);
        if ($user_id <= 0 || $exam_id <= 0) {
            return;
        }

        if (!method_exists('CBT_Cache', 'delete')) {
            return;
        }

        CBT_Cache::delete(self::storage_key($user_id, $exam_id), []);
    }

    /**
     * @param array<string,mixed> $fallback
     * @return array<string,mixed>
     */
    public static function build_pending_context(int $user_id, int $exam_id, array $fallback = []): array
    {
        $record = self::get_state($user_id, $exam_id);
        $pending_states = [
            'resume_lookup',
            'queue_waiting',
            'attempt_creating',
            'attempt_created',
            'bootstrap_session',
            'bootstrap_questions',
            'attempt_finalizing',
            'ready',
        ];

        if (!is_array($record) || !in_array((string) ($record['opening_state'] ?? ''), $pending_states, true)) {
            $record = self::normalize_record(array_merge([
                'opening_state' => self::sanitize_state((string) ($fallback['opening_state'] ?? 'resume_lookup')),
                'opening_reason' => self::sanitize_reason((string) ($fallback['opening_reason'] ?? 'resume_db_miss')),
                'attempt_id' => max(0, (int) ($fallback['attempt_id'] ?? 0)),
                'queue_ticket' => self::sanitize_queue_ticket((string) ($fallback['queue_ticket'] ?? '')),
                'resume_source' => sanitize_key((string) ($fallback['resume_source'] ?? '')),
                'retry_after_ms' => max(0, (int) ($fallback['retry_after_ms'] ?? 0)),
                'created_at' => max(0, (int) ($fallback['created_at'] ?? time())),
                'updated_at' => max(0, (int) ($fallback['updated_at'] ?? time())),
                'last_stage_at' => max(0, (int) ($fallback['last_stage_at'] ?? time())),
                'expires_at' => 0,
            ]));
        }

        return [
            'opening_state' => (string) ($record['opening_state'] ?? ''),
            'opening_reason' => (string) ($record['opening_reason'] ?? ''),
            'attempt_id' => max(0, (int) ($record['attempt_id'] ?? 0)),
            'queue_ticket' => (string) ($record['queue_ticket'] ?? ''),
            'resume_source' => (string) ($record['resume_source'] ?? ''),
            'retry_after_ms' => max(
                0,
                (int) ($record['retry_after_ms'] ?? ($fallback['retry_after_ms'] ?? 0))
            ),
            'last_stage_at' => max(0, (int) ($record['last_stage_at'] ?? 0)),
            'wait_age_seconds' => max(0, (int) ($record['wait_age_seconds'] ?? 0)),
        ];
    }

    private static function storage_key(int $user_id, int $exam_id): string
    {
        return self::STORAGE_PREFIX . $user_id . ':' . $exam_id;
    }

    private static function sanitize_state(string $state): string
    {
        $state = sanitize_key($state);
        return in_array($state, self::ALLOWED_STATES, true) ? $state : '';
    }

    private static function sanitize_reason(string $reason): string
    {
        $reason = sanitize_key($reason);
        return in_array($reason, self::ALLOWED_REASONS, true) ? $reason : '';
    }

    private static function sanitize_queue_ticket(string $queue_ticket): string
    {
        $queue_ticket = trim($queue_ticket);
        if ($queue_ticket === '') {
            return '';
        }

        return preg_replace('/[^a-zA-Z0-9\\-]/', '', $queue_ticket) ?: '';
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function resolve_ttl_seconds(string $opening_state, array $context): int
    {
        if ($opening_state === 'queue_waiting') {
            $retry_after_ms = max(0, (int) ($context['retry_after_ms'] ?? 0));
            $estimated_wait_seconds = max(0, (int) ($context['estimated_wait_seconds'] ?? 0));
            return max(
                self::QUEUE_WAITING_TTL_SECONDS,
                $estimated_wait_seconds + max(30, (int) ceil($retry_after_ms / 1000)) + 90
            );
        }

        if (in_array($opening_state, ['ready', 'completed', 'terminal_error'], true)) {
            return self::FINAL_TTL_SECONDS;
        }

        return max(self::TRANSIENT_TTL_SECONDS, (int) ($context['ttl_seconds'] ?? 0));
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private static function normalize_record(array $record): array
    {
        $created_at = max(0, (int) ($record['created_at'] ?? 0));
        $last_stage_at = max(0, (int) ($record['last_stage_at'] ?? ($record['updated_at'] ?? 0)));

        return [
            'opening_state' => self::sanitize_state((string) ($record['opening_state'] ?? '')),
            'opening_reason' => self::sanitize_reason((string) ($record['opening_reason'] ?? '')),
            'attempt_id' => max(0, (int) ($record['attempt_id'] ?? 0)),
            'queue_ticket' => self::sanitize_queue_ticket((string) ($record['queue_ticket'] ?? '')),
            'resume_source' => sanitize_key((string) ($record['resume_source'] ?? '')),
            'retry_after_ms' => max(0, (int) ($record['retry_after_ms'] ?? 0)),
            'created_at' => $created_at,
            'updated_at' => max(0, (int) ($record['updated_at'] ?? 0)),
            'last_stage_at' => $last_stage_at,
            'expires_at' => max(0, (int) ($record['expires_at'] ?? 0)),
            'wait_age_seconds' => $created_at > 0 ? max(0, time() - $created_at) : 0,
        ];
    }
}
