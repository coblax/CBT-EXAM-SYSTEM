<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Student_Profile_Cache')) {
    require_once __DIR__ . '/class-cbt-student-profile-cache.php';
}

class CBT_Live_Attempt_Roster_Index
{
    private const ROSTER_REDIS_TTL_SECONDS = 44100;
    private const ROSTER_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const ROSTER_REDIS_DEFAULT_PORT = 6379;
    private const ROSTER_REDIS_DEFAULT_DATABASE = 2;
    private const ROSTER_REDIS_PREFIX = 'cbt_roster_live:';
    private const ROSTER_REDIS_TIMEOUT = 1.5;
    private const ONLINE_THRESHOLD_SECONDS = 45;
    private const STALE_THRESHOLD_SECONDS = 90;

    /** @var Redis|false|null */
    private static $roster_redis = null;
    /** @var bool */
    private static $roster_redis_connection_attempted = false;
    /** @var string */
    private static $roster_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::roster_redis() instanceof Redis;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $context
     */
    public static function sync_attempt(array $attempt, array $context = []): void
    {
        $attempt_id = absint($attempt['id'] ?? $attempt['attempt_id'] ?? 0);
        $exam_id = absint($attempt['exam_id'] ?? 0);
        $student_id = absint($attempt['student_id'] ?? 0);
        $status = strtolower(trim((string) ($attempt['status'] ?? '')));

        if ($attempt_id <= 0) {
            return;
        }

        if ($status !== 'in_progress' || $exam_id <= 0 || $student_id <= 0) {
            self::clear_attempt($attempt_id);
            return;
        }

        $redis = self::roster_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $current_row = self::read_row($redis, $attempt_id) ?? [];
        $exam_meta = self::get_exam_meta($exam_id);
        $user = $student_id > 0 ? get_user_by('id', $student_id) : false;
        $profile = class_exists('CBT_Student_Profile_Cache')
            ? CBT_Student_Profile_Cache::get_snapshot($student_id)
            : [
                'kode_kelas' => '',
                'kode_ruang' => '',
            ];

        $teacher_id = absint(
            $context['teacher_id']
            ?? $attempt['teacher_id']
            ?? $current_row['teacher_id']
            ?? ($exam_meta['teacher_id'] ?? 0)
        );
        $exam_title = self::normalize_text(
            $context['exam_title']
            ?? $attempt['exam_title']
            ?? $current_row['exam_title']
            ?? ($exam_meta['exam_title'] ?? '')
        );
        $student_name = self::normalize_text(
            $context['student_name']
            ?? $attempt['student_name']
            ?? $current_row['student_name']
            ?? ($user instanceof WP_User
                ? ($user->display_name !== '' ? (string) $user->display_name : (string) $user->user_login)
                : '')
        );
        $student_login = self::normalize_text(
            $context['student_login']
            ?? $attempt['student_login']
            ?? $current_row['student_login']
            ?? ($user instanceof WP_User ? (string) $user->user_login : '')
        );
        $student_kode_kelas = self::normalize_text(
            $context['student_kode_kelas']
            ?? $attempt['student_kode_kelas']
            ?? $current_row['student_kode_kelas']
            ?? ($profile['kode_kelas'] ?? '')
        );
        $student_kode_ruang = self::normalize_text(
            $context['student_kode_ruang']
            ?? $attempt['student_kode_ruang']
            ?? $current_row['student_kode_ruang']
            ?? ($profile['kode_ruang'] ?? '')
        );
        $last_seen_at = self::normalize_datetime_string(
            $context['last_seen_at']
            ?? $attempt['last_seen_at']
            ?? $current_row['last_seen_at']
            ?? current_time('mysql')
        );
        $connection_status = self::normalize_presence_text(
            $context['connection_status']
            ?? $attempt['connection_status']
            ?? $current_row['connection_status']
            ?? 'online'
        );
        $visibility_state = self::normalize_presence_text(
            $context['visibility_state']
            ?? $attempt['visibility_state']
            ?? $current_row['visibility_state']
            ?? 'visible'
        );
        $has_focus = self::normalize_presence_flag(
            $context['has_focus']
            ?? $attempt['has_focus']
            ?? $current_row['has_focus']
            ?? 1
        );
        $pending_sync_count = self::normalize_presence_count(
            $context['pending_sync_count']
            ?? $attempt['pending_sync_count']
            ?? $current_row['pending_sync_count']
            ?? 0
        );
        $heartbeat_lost_active = self::normalize_presence_flag(
            $context['heartbeat_lost_active']
            ?? $attempt['heartbeat_lost_active']
            ?? $current_row['heartbeat_lost_active']
            ?? 0
        );
        $risk_tone = self::normalize_risk_tone(
            $context['risk_tone']
            ?? $attempt['risk_tone']
            ?? $current_row['risk_tone']
            ?? ''
        );
        $risk_score = self::normalize_risk_score(
            $context['risk_score']
            ?? $attempt['risk_score']
            ?? $current_row['risk_score']
            ?? 0.0
        );

        $group_meta = self::build_group_meta(
            $teacher_id,
            $exam_id,
            $exam_title,
            $student_kode_kelas,
            $student_kode_ruang
        );
        $group_id = (string) ($group_meta['group_id'] ?? '');
        if ($group_id === '') {
            return;
        }

        $row = [
            'attempt_id' => $attempt_id,
            'exam_id' => $exam_id,
            'student_id' => $student_id,
            'teacher_id' => $teacher_id,
            'student_name' => $student_name,
            'student_login' => $student_login,
            'student_kode_kelas' => $student_kode_kelas,
            'student_kode_ruang' => $student_kode_ruang,
            'exam_title' => $exam_title,
            'last_seen_at' => $last_seen_at,
            'connection_status' => $connection_status,
            'visibility_state' => $visibility_state,
            'has_focus' => $has_focus,
            'pending_sync_count' => $pending_sync_count,
            'heartbeat_lost_active' => $heartbeat_lost_active,
            'risk_tone' => $risk_tone,
            'risk_score' => $risk_score,
            'group_id' => $group_id,
        ];

        $encoded_row = wp_json_encode($row);
        $encoded_group = wp_json_encode($group_meta);
        if (!is_string($encoded_row) || $encoded_row === '' || !is_string($encoded_group) || $encoded_group === '') {
            return;
        }

        $previous_group_id = trim((string) ($current_row['group_id'] ?? ''));
        if ($previous_group_id !== '' && $previous_group_id !== $group_id) {
            $redis->zRem(self::group_attempts_key($previous_group_id), (string) $attempt_id);
            self::cleanup_group_if_empty($redis, $previous_group_id);
        }

        $last_seen_score = self::datetime_to_score($last_seen_at);
        $redis->setEx(self::row_key($attempt_id), self::ROSTER_REDIS_TTL_SECONDS, $encoded_row);
        $redis->setEx(self::group_meta_key($group_id), self::ROSTER_REDIS_TTL_SECONDS, $encoded_group);
        $redis->zAdd(self::active_attempts_key(), $last_seen_score, (string) $attempt_id);
        $redis->zAdd(self::group_attempts_key($group_id), $last_seen_score, (string) $attempt_id);
        $redis->zAdd(self::groups_key(), $last_seen_score, $group_id);
    }

    public static function sync_risk_summary(int $attempt_id, string $risk_tone, ?float $risk_score = null): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $redis = self::roster_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $row = self::read_row($redis, $attempt_id);
        if (!is_array($row)) {
            return;
        }

        $row['risk_tone'] = self::normalize_risk_tone($risk_tone);
        if ($risk_score !== null) {
            $row['risk_score'] = self::normalize_risk_score($risk_score);
        }

        self::write_row($redis, $row);
    }

    /**
     * @param array{teacher_id?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function get_grouped_payloads(array $filters = []): array
    {
        $redis = self::roster_redis();
        if (!$redis instanceof Redis) {
            return [];
        }

        $teacher_id = isset($filters['teacher_id']) ? absint($filters['teacher_id']) : 0;
        $group_ids = $redis->zRange(self::groups_key(), 0, -1);
        if (!is_array($group_ids) || empty($group_ids)) {
            return [];
        }

        $groups = [];
        foreach ($group_ids as $group_id_raw) {
            $group_id = trim((string) $group_id_raw);
            if ($group_id === '') {
                continue;
            }

            $meta = self::read_group_meta($redis, $group_id);
            if (!is_array($meta)) {
                $redis->zRem(self::groups_key(), $group_id);
                continue;
            }

            if ($teacher_id > 0 && (int) ($meta['teacher_id'] ?? 0) !== $teacher_id) {
                continue;
            }

            $attempt_ids = $redis->zRange(self::group_attempts_key($group_id), 0, -1);
            $rows = [];
            if (is_array($attempt_ids)) {
                foreach ($attempt_ids as $attempt_id_raw) {
                    $attempt_id = absint($attempt_id_raw);
                    if ($attempt_id <= 0) {
                        continue;
                    }

                    $row = self::read_row($redis, $attempt_id);
                    if (!is_array($row)) {
                        $redis->zRem(self::group_attempts_key($group_id), (string) $attempt_id);
                        $redis->zRem(self::active_attempts_key(), (string) $attempt_id);
                        continue;
                    }

                    if ((string) ($row['group_id'] ?? '') !== $group_id) {
                        $redis->zRem(self::group_attempts_key($group_id), (string) $attempt_id);
                        continue;
                    }

                    if ($teacher_id > 0 && (int) ($row['teacher_id'] ?? 0) !== $teacher_id) {
                        continue;
                    }

                    $row['presence_status'] = self::derive_presence_status((string) ($row['last_seen_at'] ?? ''));
                    $rows[] = $row;
                    self::touch_row($redis, $row);
                }
            }

            if (empty($rows)) {
                self::cleanup_group_if_empty($redis, $group_id);
                continue;
            }

            usort($rows, [self::class, 'sort_rows']);
            $groups[] = array_merge($meta, self::build_group_counters($rows), [
                'attempts' => $rows,
            ]);

            $latest_seen = self::latest_last_seen($rows);
            $redis->expire(self::group_meta_key($group_id), self::ROSTER_REDIS_TTL_SECONDS);
            $redis->expire(self::group_attempts_key($group_id), self::ROSTER_REDIS_TTL_SECONDS);
            $redis->zAdd(self::groups_key(), self::datetime_to_score($latest_seen), $group_id);
        }

        usort($groups, [self::class, 'sort_groups']);

        return $groups;
    }

    public static function clear_attempt(int $attempt_id): void
    {
        $attempt_id = absint($attempt_id);
        if ($attempt_id <= 0) {
            return;
        }

        $redis = self::roster_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $row = self::read_row($redis, $attempt_id);
        $group_id = is_array($row) ? trim((string) ($row['group_id'] ?? '')) : '';

        $redis->del(self::row_key($attempt_id));
        $redis->zRem(self::active_attempts_key(), (string) $attempt_id);

        if ($group_id !== '') {
            $redis->zRem(self::group_attempts_key($group_id), (string) $attempt_id);
            self::cleanup_group_if_empty($redis, $group_id);
        }
    }

    public static function clear_all(): void
    {
        $redis = self::roster_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $attempt_ids = $redis->zRange(self::active_attempts_key(), 0, -1);
        if (is_array($attempt_ids)) {
            foreach ($attempt_ids as $attempt_id_raw) {
                $attempt_id = absint($attempt_id_raw);
                if ($attempt_id <= 0) {
                    continue;
                }

                $redis->del(self::row_key($attempt_id));
            }
        }

        $group_ids = $redis->zRange(self::groups_key(), 0, -1);
        if (is_array($group_ids)) {
            foreach ($group_ids as $group_id_raw) {
                $group_id = trim((string) $group_id_raw);
                if ($group_id === '') {
                    continue;
                }

                $redis->del(self::group_meta_key($group_id), self::group_attempts_key($group_id));
            }
        }

        $redis->del(self::active_attempts_key(), self::groups_key());
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_row(Redis $redis, int $attempt_id): ?array
    {
        $raw_payload = $redis->get(self::row_key($attempt_id));
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            $redis->del(self::row_key($attempt_id));
            return null;
        }

        $row = [
            'attempt_id' => absint($decoded['attempt_id'] ?? 0),
            'exam_id' => absint($decoded['exam_id'] ?? 0),
            'student_id' => absint($decoded['student_id'] ?? 0),
            'teacher_id' => absint($decoded['teacher_id'] ?? 0),
            'student_name' => self::normalize_text($decoded['student_name'] ?? ''),
            'student_login' => self::normalize_text($decoded['student_login'] ?? ''),
            'student_kode_kelas' => self::normalize_text($decoded['student_kode_kelas'] ?? ''),
            'student_kode_ruang' => self::normalize_text($decoded['student_kode_ruang'] ?? ''),
            'exam_title' => self::normalize_text($decoded['exam_title'] ?? ''),
            'last_seen_at' => self::normalize_datetime_string($decoded['last_seen_at'] ?? ''),
            'connection_status' => self::normalize_presence_text($decoded['connection_status'] ?? ''),
            'visibility_state' => self::normalize_presence_text($decoded['visibility_state'] ?? ''),
            'has_focus' => self::normalize_presence_flag($decoded['has_focus'] ?? 1),
            'pending_sync_count' => self::normalize_presence_count($decoded['pending_sync_count'] ?? 0),
            'heartbeat_lost_active' => self::normalize_presence_flag($decoded['heartbeat_lost_active'] ?? 0),
            'risk_tone' => self::normalize_risk_tone($decoded['risk_tone'] ?? ''),
            'risk_score' => self::normalize_risk_score($decoded['risk_score'] ?? 0.0),
            'group_id' => trim((string) ($decoded['group_id'] ?? '')),
        ];

        if (
            (int) ($row['attempt_id'] ?? 0) !== $attempt_id
            || (int) ($row['exam_id'] ?? 0) <= 0
            || (int) ($row['student_id'] ?? 0) <= 0
            || (string) ($row['group_id'] ?? '') === ''
        ) {
            $redis->del(self::row_key($attempt_id));
            $redis->zRem(self::active_attempts_key(), (string) $attempt_id);
            return null;
        }

        return $row;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_group_meta(Redis $redis, string $group_id): ?array
    {
        $raw_payload = $redis->get(self::group_meta_key($group_id));
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            $redis->del(self::group_meta_key($group_id));
            return null;
        }

        $meta = [
            'group_id' => trim((string) ($decoded['group_id'] ?? '')),
            'teacher_id' => absint($decoded['teacher_id'] ?? 0),
            'exam_id' => absint($decoded['exam_id'] ?? 0),
            'exam_title' => self::normalize_text($decoded['exam_title'] ?? ''),
            'student_kode_kelas' => self::normalize_text($decoded['student_kode_kelas'] ?? ''),
            'student_kode_ruang' => self::normalize_text($decoded['student_kode_ruang'] ?? ''),
            'kelas_label' => self::normalize_text($decoded['kelas_label'] ?? ''),
            'ruang_label' => self::normalize_text($decoded['ruang_label'] ?? ''),
        ];

        if (
            $meta['group_id'] !== $group_id
            || (int) ($meta['exam_id'] ?? 0) <= 0
            || (string) ($meta['exam_title'] ?? '') === ''
        ) {
            $redis->del(self::group_meta_key($group_id));
            $redis->zRem(self::groups_key(), $group_id);
            return null;
        }

        return $meta;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function write_row(Redis $redis, array $row): void
    {
        $attempt_id = absint($row['attempt_id'] ?? 0);
        $group_id = trim((string) ($row['group_id'] ?? ''));
        if ($attempt_id <= 0 || $group_id === '') {
            return;
        }

        $encoded = wp_json_encode($row);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $last_seen_at = (string) ($row['last_seen_at'] ?? current_time('mysql'));
        $score = self::datetime_to_score($last_seen_at);
        $redis->setEx(self::row_key($attempt_id), self::ROSTER_REDIS_TTL_SECONDS, $encoded);
        $redis->zAdd(self::active_attempts_key(), $score, (string) $attempt_id);
        $redis->zAdd(self::group_attempts_key($group_id), $score, (string) $attempt_id);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function touch_row(Redis $redis, array $row): void
    {
        $attempt_id = absint($row['attempt_id'] ?? 0);
        $group_id = trim((string) ($row['group_id'] ?? ''));
        if ($attempt_id <= 0 || $group_id === '') {
            return;
        }

        $last_seen_at = (string) ($row['last_seen_at'] ?? '');
        $score = self::datetime_to_score($last_seen_at);
        $redis->expire(self::row_key($attempt_id), self::ROSTER_REDIS_TTL_SECONDS);
        $redis->zAdd(self::active_attempts_key(), $score, (string) $attempt_id);
        $redis->expire(self::group_attempts_key($group_id), self::ROSTER_REDIS_TTL_SECONDS);
        $redis->zAdd(self::group_attempts_key($group_id), $score, (string) $attempt_id);
    }

    private static function cleanup_group_if_empty(Redis $redis, string $group_id): void
    {
        $members = $redis->zRange(self::group_attempts_key($group_id), 0, -1);
        if (is_array($members) && !empty($members)) {
            return;
        }

        $redis->del(self::group_meta_key($group_id), self::group_attempts_key($group_id));
        $redis->zRem(self::groups_key(), $group_id);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function build_group_counters(array $rows): array
    {
        $counters = [
            'active_total' => count($rows),
            'online_total' => 0,
            'stale_total' => 0,
            'offline_total' => 0,
            'watch_total' => 0,
            'high_risk_total' => 0,
            'hidden_total' => 0,
            'focus_off_total' => 0,
            'heartbeat_total' => 0,
            'last_seen_at' => self::latest_last_seen($rows),
        ];

        foreach ($rows as $row) {
            $presence_status = (string) ($row['presence_status'] ?? '');
            if ($presence_status === 'online') {
                $counters['online_total'] += 1;
            } elseif ($presence_status === 'stale') {
                $counters['stale_total'] += 1;
            } elseif ($presence_status === 'offline') {
                $counters['offline_total'] += 1;
            }

            $risk_tone = (string) ($row['risk_tone'] ?? '');
            if ($risk_tone === 'high-risk') {
                $counters['high_risk_total'] += 1;
                $counters['watch_total'] += 1;
            } elseif ($risk_tone === 'watch') {
                $counters['watch_total'] += 1;
            }

            if ((string) ($row['visibility_state'] ?? '') === 'hidden') {
                $counters['hidden_total'] += 1;
            }

            if ((int) ($row['has_focus'] ?? 1) === 0) {
                $counters['focus_off_total'] += 1;
            }

            if (!empty($row['heartbeat_lost_active'])) {
                $counters['heartbeat_total'] += 1;
            }
        }

        return $counters;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private static function latest_last_seen(array $rows): string
    {
        $latest = '';

        foreach ($rows as $row) {
            $last_seen_at = (string) ($row['last_seen_at'] ?? '');
            if ($last_seen_at !== '' && ($latest === '' || strcmp($last_seen_at, $latest) > 0)) {
                $latest = $last_seen_at;
            }
        }

        return $latest;
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_group_meta(
        int $teacher_id,
        int $exam_id,
        string $exam_title,
        string $student_kode_kelas,
        string $student_kode_ruang
    ): array {
        $normalized_kelas = self::normalize_text($student_kode_kelas);
        $normalized_ruang = self::normalize_text($student_kode_ruang);
        $group_id = md5(implode('|', [
            max(0, $teacher_id),
            max(0, $exam_id),
            strtolower($normalized_kelas),
            strtolower($normalized_ruang),
        ]));

        return [
            'group_id' => $group_id,
            'teacher_id' => max(0, $teacher_id),
            'exam_id' => max(0, $exam_id),
            'exam_title' => $exam_title !== '' ? $exam_title : ('Exam #' . max(0, $exam_id)),
            'student_kode_kelas' => $normalized_kelas,
            'student_kode_ruang' => $normalized_ruang,
            'kelas_label' => $normalized_kelas !== '' ? $normalized_kelas : 'Tanpa Kelas',
            'ruang_label' => $normalized_ruang !== '' ? $normalized_ruang : 'Tanpa Ruang',
        ];
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private static function sort_rows(array $left, array $right): int
    {
        $leftRisk = self::risk_priority((string) ($left['risk_tone'] ?? ''));
        $rightRisk = self::risk_priority((string) ($right['risk_tone'] ?? ''));
        if ($leftRisk !== $rightRisk) {
            return $rightRisk <=> $leftRisk;
        }

        $leftPresence = self::presence_priority((string) ($left['presence_status'] ?? ''));
        $rightPresence = self::presence_priority((string) ($right['presence_status'] ?? ''));
        if ($leftPresence !== $rightPresence) {
            return $rightPresence <=> $leftPresence;
        }

        $nameCompare = strcmp(
            strtolower((string) ($left['student_name'] ?? '')),
            strtolower((string) ($right['student_name'] ?? ''))
        );
        if ($nameCompare !== 0) {
            return $nameCompare;
        }

        return (int) ($left['attempt_id'] ?? 0) <=> (int) ($right['attempt_id'] ?? 0);
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private static function sort_groups(array $left, array $right): int
    {
        $examCompare = strcmp(
            strtolower((string) ($left['exam_title'] ?? '')),
            strtolower((string) ($right['exam_title'] ?? ''))
        );
        if ($examCompare !== 0) {
            return $examCompare;
        }

        $kelasCompare = strcmp(
            strtolower((string) ($left['kelas_label'] ?? '')),
            strtolower((string) ($right['kelas_label'] ?? ''))
        );
        if ($kelasCompare !== 0) {
            return $kelasCompare;
        }

        $ruangCompare = strcmp(
            strtolower((string) ($left['ruang_label'] ?? '')),
            strtolower((string) ($right['ruang_label'] ?? ''))
        );
        if ($ruangCompare !== 0) {
            return $ruangCompare;
        }

        return strcmp((string) ($right['last_seen_at'] ?? ''), (string) ($left['last_seen_at'] ?? ''));
    }

    private static function presence_priority(string $presence_status): int
    {
        if ($presence_status === 'online') {
            return 3;
        }

        if ($presence_status === 'stale') {
            return 2;
        }

        if ($presence_status === 'offline') {
            return 1;
        }

        return 0;
    }

    private static function risk_priority(string $risk_tone): int
    {
        if ($risk_tone === 'high-risk') {
            return 2;
        }

        if ($risk_tone === 'watch') {
            return 1;
        }

        return 0;
    }

    /**
     * @return array{teacher_id:int,exam_title:string}
     */
    private static function get_exam_meta(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [
                'teacher_id' => 0,
                'exam_title' => '',
            ];
        }

        static $cache = [];
        if (isset($cache[$exam_id])) {
            return $cache[$exam_id];
        }

        global $wpdb;
        if (!is_object($wpdb) || !isset($wpdb->prefix) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_row')) {
            $cache[$exam_id] = [
                'teacher_id' => 0,
                'exam_title' => '',
            ];

            return $cache[$exam_id];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        try {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, title, created_by
                     FROM {$exam_table}
                     WHERE id = %d
                     LIMIT 1",
                    $exam_id
                ),
                ARRAY_A
            );
        } catch (Throwable $exception) {
            $row = null;
        }

        $cache[$exam_id] = [
            'teacher_id' => is_array($row) ? absint($row['created_by'] ?? 0) : 0,
            'exam_title' => is_array($row) ? self::normalize_text($row['title'] ?? '') : '',
        ];

        return $cache[$exam_id];
    }

    private static function derive_presence_status(string $last_seen_at): string
    {
        $timestamp = self::datetime_to_timestamp($last_seen_at);
        if ($timestamp <= 0) {
            return '';
        }

        $age = max(0, (int) current_time('timestamp') - $timestamp);
        if ($age <= self::ONLINE_THRESHOLD_SECONDS) {
            return 'online';
        }

        if ($age <= self::STALE_THRESHOLD_SECONDS) {
            return 'stale';
        }

        return 'offline';
    }

    private static function normalize_text($value): string
    {
        return trim(sanitize_text_field((string) $value));
    }

    private static function normalize_presence_text($value): string
    {
        return strtolower(trim(sanitize_text_field((string) $value)));
    }

    private static function normalize_presence_flag($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    private static function normalize_presence_count($value): int
    {
        return max(0, (int) $value);
    }

    private static function normalize_risk_tone($value): string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'high-risk') {
            return 'high-risk';
        }

        if ($normalized === 'watch') {
            return 'watch';
        }

        return '';
    }

    private static function normalize_risk_score($value): float
    {
        return max(0.0, (float) $value);
    }

    private static function normalize_datetime_string($value): string
    {
        $normalized = trim(sanitize_text_field((string) $value));
        if ($normalized === '') {
            return current_time('mysql');
        }

        $timestamp = self::datetime_to_timestamp($normalized);
        if ($timestamp <= 0) {
            return current_time('mysql');
        }

        return $normalized;
    }

    private static function datetime_to_timestamp(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : (int) $timestamp;
    }

    private static function datetime_to_score(string $value): float
    {
        return (float) max(0, self::datetime_to_timestamp($value));
    }

    /**
     * @return Redis|null
     */
    private static function roster_redis(): ?Redis
    {
        if (self::$roster_redis_connection_attempted) {
            return (self::$roster_redis instanceof Redis) ? self::$roster_redis : null;
        }

        self::$roster_redis_connection_attempted = true;
        self::$roster_redis = false;
        self::$roster_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$roster_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::roster_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::ROSTER_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::ROSTER_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::ROSTER_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::ROSTER_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::ROSTER_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis roster gagal.');
            }

            self::$roster_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$roster_redis_last_connection_error = 'Koneksi roster Redis gagal: ' . $throwable->getMessage();
            self::$roster_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function roster_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::ROSTER_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::ROSTER_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::ROSTER_REDIS_DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::ROSTER_REDIS_DEFAULT_DATABASE - 1);
            $database = max(0, $wp_database + 1);
        }

        $password = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PASSWORD', ''));
        if ($password === '') {
            $password = trim((string) self::constant_scalar('WP_REDIS_PASSWORD', ''));
        }

        $scheme = 'tcp';
        if ($host !== '' && strpos($host, '/') === 0) {
            $scheme = 'unix';
        }

        return [
            'host' => $host !== '' ? $host : self::ROSTER_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'password' => $password,
            'timeout' => self::ROSTER_REDIS_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    private static function row_key(int $attempt_id): string
    {
        return self::ROSTER_REDIS_PREFIX . 'attempt:' . max(0, $attempt_id);
    }

    private static function active_attempts_key(): string
    {
        return self::ROSTER_REDIS_PREFIX . 'active_attempts';
    }

    private static function groups_key(): string
    {
        return self::ROSTER_REDIS_PREFIX . 'groups';
    }

    private static function group_meta_key(string $group_id): string
    {
        return self::ROSTER_REDIS_PREFIX . 'group:' . $group_id . ':meta';
    }

    private static function group_attempts_key(string $group_id): string
    {
        return self::ROSTER_REDIS_PREFIX . 'group:' . $group_id . ':attempts';
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function constant_scalar(string $name, $default)
    {
        return defined($name) ? constant($name) : $default;
    }
}
