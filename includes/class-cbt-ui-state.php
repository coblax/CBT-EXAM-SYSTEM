<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_UI_State
{
    private const PREF_TTL = 30 * DAY_IN_SECONDS;
    private const ATTEMPT_TTL = 2 * DAY_IN_SECONDS;
    private const FONT_SCALE_MIN = 0.85;
    private const FONT_SCALE_MAX = 1.35;
    private const FONT_SCALE_DEFAULT = 1.0;

    /**
     * @return array<string,mixed>
     */
    public static function get_state(int $user_id, int $attempt_id = 0): array
    {
        return [
            'preferences' => self::get_preferences($user_id),
            'attempt_state' => ($attempt_id > 0) ? self::get_attempt_state($user_id, $attempt_id) : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_preferences(int $user_id): array
    {
        if ($user_id <= 0) {
            return self::default_preferences();
        }

        $payload = CBT_Cache::get(self::preferences_cache_key($user_id), [CBT_Cache::namespace_ui_state()], $found);
        if (!$found || !is_array($payload)) {
            return self::default_preferences();
        }

        return self::normalize_preferences($payload);
    }

    /**
     * @param array<string,mixed> $preferences
     * @return array<string,mixed>
     */
    public static function save_preferences(int $user_id, array $preferences): array
    {
        if ($user_id <= 0) {
            return self::default_preferences();
        }

        $current = self::get_preferences($user_id);
        $normalized = self::normalize_preferences(array_merge($current, $preferences));
        CBT_Cache::set(
            self::preferences_cache_key($user_id),
            $normalized,
            self::preferences_ttl(),
            [CBT_Cache::namespace_ui_state()]
        );
        CBT_Cache::register_ui_state(self::preferences_registry_key($user_id), [
            'type' => 'preferences',
            'user_id' => $user_id,
            'attempt_id' => 0,
            'updated_at' => time(),
            'expires_at' => time() + self::preferences_ttl(),
            'context' => [
                'preference_keys' => array_keys($normalized),
            ],
        ]);

        return $normalized;
    }

    public static function clear_preferences(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        CBT_Cache::delete(self::preferences_cache_key($user_id), [CBT_Cache::namespace_ui_state()]);
        CBT_Cache::unregister_ui_state(self::preferences_registry_key($user_id));
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_attempt_state(int $user_id, int $attempt_id): ?array
    {
        if ($user_id <= 0 || $attempt_id <= 0) {
            return null;
        }

        $payload = CBT_Cache::get(self::attempt_cache_key($user_id, $attempt_id), [CBT_Cache::namespace_ui_state()], $found);
        if (!$found || !is_array($payload)) {
            return null;
        }

        return self::normalize_attempt_state($attempt_id, $payload, self::attempt_question_ids($attempt_id));
    }

    /**
     * @param array<string,mixed> $attempt_state
     * @return array<string,mixed>|null
     */
    public static function save_attempt_state(int $user_id, int $attempt_id, array $attempt_state): ?array
    {
        if ($user_id <= 0 || $attempt_id <= 0) {
            return null;
        }

        $question_ids = self::attempt_question_ids($attempt_id);
        $current = self::get_attempt_state($user_id, $attempt_id);
        $normalized = self::normalize_attempt_state(
            $attempt_id,
            is_array($current) ? array_merge($current, $attempt_state) : $attempt_state,
            $question_ids
        );

        if (is_array($current) && self::attempt_state_equals($current, $normalized)) {
            return $current;
        }

        CBT_Cache::set(
            self::attempt_cache_key($user_id, $attempt_id),
            $normalized,
            self::attempt_ttl(),
            [CBT_Cache::namespace_ui_state()]
        );
        CBT_Cache::register_ui_state(self::attempt_registry_key($user_id, $attempt_id), [
            'type' => 'attempt_state',
            'user_id' => $user_id,
            'attempt_id' => $attempt_id,
            'updated_at' => time(),
            'expires_at' => time() + self::attempt_ttl(),
            'context' => [
                'question_count' => count($question_ids),
                'doubtful_count' => count($normalized['doubtful_question_ids']),
            ],
        ]);

        return $normalized;
    }

    public static function clear_attempt_state(int $user_id, int $attempt_id): void
    {
        if ($user_id <= 0 || $attempt_id <= 0) {
            return;
        }

        CBT_Cache::delete(self::attempt_cache_key($user_id, $attempt_id), [CBT_Cache::namespace_ui_state()]);
        CBT_Cache::unregister_ui_state(self::attempt_registry_key($user_id, $attempt_id));
    }

    public static function clear_attempt_state_by_attempt_id(int $attempt_id): void
    {
        if ($attempt_id <= 0) {
            return;
        }

        global $wpdb;
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, student_id
                 FROM {$attempt_table}
                 WHERE id = %d",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return;
        }

        self::clear_attempt_state((int) ($attempt['student_id'] ?? 0), (int) ($attempt['id'] ?? 0));
    }

    /**
     * @param array<int,int> $attempt_ids
     */
    public static function clear_attempt_states_by_attempt_ids(array $attempt_ids): void
    {
        $clean_attempt_ids = array_values(array_unique(array_filter(array_map('absint', $attempt_ids))));
        if (empty($clean_attempt_ids)) {
            return;
        }

        global $wpdb;
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $attempt_ids_sql = implode(',', $clean_attempt_ids);
        $rows = $wpdb->get_results(
            "SELECT id, student_id
             FROM {$attempt_table}
             WHERE id IN ({$attempt_ids_sql})",
            ARRAY_A
        );

        foreach ((array) $rows as $row) {
            self::clear_attempt_state((int) ($row['student_id'] ?? 0), (int) ($row['id'] ?? 0));
        }
    }

    public static function clear_all(): void
    {
        CBT_Cache::invalidate_ui_state();
        CBT_Cache::clear_ui_state_registry();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_registry_entries(): array
    {
        return CBT_Cache::get_ui_state_registry_entries();
    }

    public static function preferences_ttl(): int
    {
        return self::PREF_TTL;
    }

    public static function attempt_ttl(): int
    {
        return self::ATTEMPT_TTL;
    }

    private static function preferences_cache_key(int $user_id): string
    {
        return 'ui:prefs:user:' . $user_id;
    }

    private static function attempt_cache_key(int $user_id, int $attempt_id): string
    {
        return 'ui:attempt:user:' . $user_id . ':attempt:' . $attempt_id;
    }

    private static function preferences_registry_key(int $user_id): string
    {
        return 'preferences:user:' . $user_id;
    }

    private static function attempt_registry_key(int $user_id, int $attempt_id): string
    {
        return 'attempt:user:' . $user_id . ':attempt:' . $attempt_id;
    }

    /**
     * @return array<string,mixed>
     */
    private static function default_preferences(): array
    {
        return [
            'theme' => 'light',
            'font_scale' => self::FONT_SCALE_DEFAULT,
            'nav_panel_visible' => 1,
            'nav_panel_position' => 'right',
            'calculator_position' => 'bottom',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function normalize_preferences(array $payload): array
    {
        $defaults = self::default_preferences();
        $theme = StringToLower::value($payload['theme'] ?? $defaults['theme']);
        if (!in_array($theme, ['light', 'dark'], true)) {
            $theme = $defaults['theme'];
        }

        $font_scale = round((float) ($payload['font_scale'] ?? $defaults['font_scale']), 2);
        if ($font_scale < self::FONT_SCALE_MIN || $font_scale > self::FONT_SCALE_MAX) {
            $font_scale = $defaults['font_scale'];
        }

        $nav_visible = ((int) ($payload['nav_panel_visible'] ?? $defaults['nav_panel_visible']) === 1) ? 1 : 0;
        $nav_position = strtolower(trim((string) ($payload['nav_panel_position'] ?? $defaults['nav_panel_position'])));
        if (!in_array($nav_position, ['top', 'left', 'right', 'bottom'], true)) {
            $nav_position = $defaults['nav_panel_position'];
        }

        $calculator_position = strtolower(trim((string) ($payload['calculator_position'] ?? $defaults['calculator_position'])));
        if (!in_array($calculator_position, ['top', 'left', 'right', 'bottom'], true)) {
            $calculator_position = $defaults['calculator_position'];
        }

        return [
            'theme' => $theme,
            'font_scale' => $font_scale,
            'nav_panel_visible' => $nav_visible,
            'nav_panel_position' => $nav_position,
            'calculator_position' => $calculator_position,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,int> $question_ids
     * @return array<string,mixed>
     */
    private static function normalize_attempt_state(int $attempt_id, array $payload, array $question_ids): array
    {
        $question_id_set = [];
        foreach ($question_ids as $question_id) {
            $question_id_set[(int) $question_id] = true;
        }

        $doubtful_question_ids = [];
        $raw_doubtful = $payload['doubtful_question_ids'] ?? ($payload['doubtful'] ?? []);
        if (is_array($raw_doubtful)) {
            foreach ($raw_doubtful as $key => $value) {
                $question_id = 0;
                if (
                    is_int($key)
                    && $key > 0
                    && (
                        $value === true
                        || $value === 'true'
                        || $value === 1
                        || $value === '1'
                    )
                ) {
                    $question_id = $key;
                } elseif (is_int($key) && (is_int($value) || is_string($value) || is_float($value))) {
                    $question_id = (int) $value;
                } elseif (is_string($key) && ((int) $value === 1 || $value === true || $value === 'true')) {
                    $question_id = (int) $key;
                } elseif (is_scalar($value)) {
                    $question_id = (int) $value;
                }

                if ($question_id <= 0) {
                    continue;
                }
                if (!empty($question_id_set) && !isset($question_id_set[$question_id])) {
                    continue;
                }
                $doubtful_question_ids[$question_id] = $question_id;
            }
        }

        $question_count = count($question_ids);
        $max_index = max(0, $question_count - 1);
        $current_index = (int) ($payload['current_index'] ?? 0);
        if ($current_index < 0) {
            $current_index = 0;
        }
        if ($question_count > 0 && $current_index > $max_index) {
            $current_index = $max_index;
        }
        if ($question_count === 0) {
            $current_index = 0;
        }

        return [
            'attempt_id' => $attempt_id,
            'current_index' => $current_index,
            'doubtful_question_ids' => array_values($doubtful_question_ids),
        ];
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private static function attempt_state_equals(array $left, array $right): bool
    {
        $normalize_for_compare = static function (array $state): array {
            $doubtful_question_ids = [];
            foreach ((array) ($state['doubtful_question_ids'] ?? []) as $question_id) {
                $safe_question_id = (int) $question_id;
                if ($safe_question_id > 0) {
                    $doubtful_question_ids[$safe_question_id] = $safe_question_id;
                }
            }

            sort($doubtful_question_ids, SORT_NUMERIC);

            return [
                'attempt_id' => (int) ($state['attempt_id'] ?? 0),
                'current_index' => (int) ($state['current_index'] ?? 0),
                'doubtful_question_ids' => array_values($doubtful_question_ids),
            ];
        };

        return $normalize_for_compare($left) === $normalize_for_compare($right);
    }

    /**
     * @return array<int,int>
     */
    private static function attempt_question_ids(int $attempt_id): array
    {
        global $wpdb;

        if ($attempt_id <= 0) {
            return [];
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $attempt = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT exam_id, question_order
                 FROM {$attempt_table}
                 WHERE id = %d",
                $attempt_id
            ),
            ARRAY_A
        );
        if (!$attempt) {
            return [];
        }

        $ordered_ids = [];
        $decoded_order = json_decode((string) ($attempt['question_order'] ?? ''), true);
        if (is_array($decoded_order)) {
            $ordered_ids = array_values(array_unique(array_filter(array_map('intval', $decoded_order), static function (int $question_id): bool {
                return $question_id > 0;
            })));
        }
        if (!empty($ordered_ids)) {
            return $ordered_ids;
        }

        $exam_id = (int) ($attempt['exam_id'] ?? 0);
        if ($exam_id <= 0) {
            return [];
        }

        return array_map(
            'intval',
            (array) $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$question_table}
                     WHERE exam_id = %d
                       AND COALESCE(is_active, 1) = 1
                     ORDER BY id ASC",
                    $exam_id
                )
            )
        );
    }
}

final class StringToLower
{
    public static function value($value): string
    {
        return strtolower(trim((string) $value));
    }
}
