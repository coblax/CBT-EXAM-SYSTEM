<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/vendor/autoload.php';
require_once __DIR__ . '/TestCase.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/wordpress/');
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('absint')) {
    function absint($maybeint): int
    {
        return abs((int) $maybeint);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str): string
    {
        $filtered = is_scalar($str) ? (string) $str : '';
        $filtered = strip_tags($filtered);
        $filtered = preg_replace('/[\r\n\t ]+/', ' ', $filtered);

        return trim((string) $filtered);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string
    {
        $key = is_scalar($key) ? strtolower((string) $key) : '';
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text, $remove_breaks = false): string
    {
        $text = is_scalar($text) ? (string) $text : '';
        $text = strip_tags($text);
        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
        }

        return trim((string) $text);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value, $flags = 0, $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null): string
    {
        return is_scalar($url) ? trim((string) $url) : '';
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url, $protocols = null, $_context = 'display'): string
    {
        return esc_url_raw($url, $protocols);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text): string
    {
        return htmlspecialchars(is_scalar($text) ? (string) $text : '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text): string
    {
        return htmlspecialchars(is_scalar($text) ? (string) $text : '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($classname, $fallback = ''): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', is_scalar($classname) ? (string) $classname : '');
        $sanitized = is_string($sanitized) ? $sanitized : '';
        if ($sanitized === '') {
            return is_scalar($fallback) ? (string) $fallback : '';
        }

        return $sanitized;
    }
}

if (!function_exists('wp_allowed_protocols')) {
    function wp_allowed_protocols(): array
    {
        return ['http', 'https', 'mailto'];
    }
}

if (!function_exists('wp_kses_allowed_html')) {
    function wp_kses_allowed_html($context = 'post'): array
    {
        return [];
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($content, $allowed_html = [], $allowed_protocols = []): string
    {
        return is_scalar($content) ? (string) $content : '';
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;
        private mixed $data;

        public function __construct($code = '', $message = '', $data = null)
        {
            $this->code = is_scalar($code) ? (string) $code : '';
            $this->message = is_scalar($message) ? (string) $message : '';
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): mixed
        {
            return $this->data;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params;
        private array $jsonParams;
        private array $headers;
        private string $route;
        private string $method;

        public function __construct(array $params = [], array $jsonParams = [], array $headers = [], string $route = '', string $method = 'POST')
        {
            $this->params = $params;
            $this->jsonParams = $jsonParams;
            $this->headers = [];
            foreach ($headers as $key => $value) {
                $this->headers[strtolower((string) $key)] = $value;
            }
            $this->route = $route;
            $this->method = strtoupper($method);
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function get_json_params(): array
        {
            return $this->jsonParams;
        }

        public function get_header(string $name): string
        {
            $value = $this->headers[strtolower($name)] ?? '';
            return is_scalar($value) ? (string) $value : '';
        }

        public function get_route(): string
        {
            return $this->route;
        }

        public function get_method(): string
        {
            return $this->method;
        }
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response)
    {
        return $response;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0): int|string
    {
        if ((string) $type === 'timestamp') {
            return 1774353600;
        }

        return '2026-03-24 12:00:00';
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        return new DateTimeZone('Asia/Jakarta');
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null): string
    {
        $timestamp = $timestamp === null ? time() : (int) $timestamp;
        $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
        $date = new DateTimeImmutable('@' . $timestamp);
        $date = $date->setTimezone($timezone);

        return $date->format((string) $format);
    }
}

if (!function_exists('cbt_test_reset_wordpress_storage')) {
    function cbt_test_reset_wordpress_storage(): void
    {
        $GLOBALS['cbt_test_wp_options'] = [];
        $GLOBALS['cbt_test_wp_transients'] = [];
        $GLOBALS['cbt_test_wp_object_cache'] = [];
        $GLOBALS['cbt_test_wp_users'] = [];
        $GLOBALS['cbt_test_wp_user_meta'] = [];

        if (class_exists('CBT_Cache')) {
            $reflection = new ReflectionClass('CBT_Cache');
            if ($reflection->hasProperty('registry')) {
                $registry = $reflection->getProperty('registry');
                $registry->setAccessible(true);
                $registry->setValue(null, null);
            }
        }

        if (class_exists('CBT_Auth')) {
            $reflection = new ReflectionClass('CBT_Auth');
            if ($reflection->hasProperty('decoded_token_cache')) {
                $cache = $reflection->getProperty('decoded_token_cache');
                $cache->setAccessible(true);
                $cache->setValue(null, []);
            }
        }
    }
}

cbt_test_reset_wordpress_storage();

if (!function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        $key = (string) $option;
        return array_key_exists($key, $GLOBALS['cbt_test_wp_options'])
            ? $GLOBALS['cbt_test_wp_options'][$key]
            : $default;
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value = '', $deprecated = '', $autoload = 'yes'): bool
    {
        $key = (string) $option;
        if (array_key_exists($key, $GLOBALS['cbt_test_wp_options'])) {
            return false;
        }

        $GLOBALS['cbt_test_wp_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null): bool
    {
        $GLOBALS['cbt_test_wp_options'][(string) $option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option): bool
    {
        $key = (string) $option;
        $exists = array_key_exists($key, $GLOBALS['cbt_test_wp_options']);
        unset($GLOBALS['cbt_test_wp_options'][$key]);
        return $exists;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient)
    {
        $key = (string) $transient;
        return array_key_exists($key, $GLOBALS['cbt_test_wp_transients'])
            ? $GLOBALS['cbt_test_wp_transients'][$key]
            : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0): bool
    {
        $GLOBALS['cbt_test_wp_transients'][(string) $transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient): bool
    {
        $key = (string) $transient;
        $exists = array_key_exists($key, $GLOBALS['cbt_test_wp_transients']);
        unset($GLOBALS['cbt_test_wp_transients'][$key]);
        return $exists;
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public int $ID;
        public string $user_login;
        public string $user_email;
        public string $user_pass;
        public string $display_name;

        /** @var string[] */
        public array $roles;

        /**
         * @param array<string,mixed> $data
         */
        public function __construct(array $data = [])
        {
            $this->ID = (int) ($data['ID'] ?? 0);
            $this->user_login = (string) ($data['user_login'] ?? '');
            $this->user_email = (string) ($data['user_email'] ?? '');
            $this->user_pass = (string) ($data['user_pass'] ?? '');
            $this->display_name = (string) ($data['display_name'] ?? '');
            $this->roles = array_values(array_map('strval', (array) ($data['roles'] ?? [])));
        }
    }
}

if (!function_exists('cbt_test_register_user')) {
    /**
     * @param array<string,mixed> $user
     */
    function cbt_test_register_user(array $user): void
    {
        $wpUser = $user instanceof WP_User ? $user : new WP_User($user);
        $GLOBALS['cbt_test_wp_users'][$wpUser->ID] = $wpUser;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value): WP_User|false
    {
        foreach ((array) ($GLOBALS['cbt_test_wp_users'] ?? []) as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            switch ((string) $field) {
                case 'id':
                    if ((int) $user->ID === (int) $value) {
                        return $user;
                    }
                    break;
                case 'email':
                    if ((string) $user->user_email === (string) $value) {
                        return $user;
                    }
                    break;
                case 'login':
                    if ((string) $user->user_login === (string) $value) {
                        return $user;
                    }
                    break;
            }
        }

        return false;
    }
}

if (!function_exists('get_users')) {
    function get_users(array $args = []): array
    {
        $metaKey = (string) ($args['meta_key'] ?? '');
        $metaValue = $args['meta_value'] ?? null;
        $fields = (string) ($args['fields'] ?? '');
        $number = max(0, (int) ($args['number'] ?? 0));
        $matches = [];

        foreach ((array) ($GLOBALS['cbt_test_wp_users'] ?? []) as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            if ($metaKey !== '') {
                $value = get_user_meta($user->ID, $metaKey, true);
                if ((string) $value !== (string) $metaValue) {
                    continue;
                }
            }

            $matches[] = ($fields === 'ids') ? (int) $user->ID : $user;
            if ($number > 0 && count($matches) >= $number) {
                break;
            }
        }

        return $matches;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false)
    {
        $userId = (int) $user_id;
        $meta = (array) ($GLOBALS['cbt_test_wp_user_meta'][$userId] ?? []);

        if ($key === '' || $key === null) {
            return $meta;
        }

        $values = (array) ($meta[(string) $key] ?? []);
        if ($single) {
            return $values[0] ?? '';
        }

        return $values;
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = ''): bool
    {
        $userId = (int) $user_id;
        if (!isset($GLOBALS['cbt_test_wp_user_meta'][$userId])) {
            $GLOBALS['cbt_test_wp_user_meta'][$userId] = [];
        }

        $GLOBALS['cbt_test_wp_user_meta'][$userId][(string) $meta_key] = [$meta_value];
        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key, $meta_value = ''): bool
    {
        $userId = (int) $user_id;
        $exists = isset($GLOBALS['cbt_test_wp_user_meta'][$userId][(string) $meta_key]);
        unset($GLOBALS['cbt_test_wp_user_meta'][$userId][(string) $meta_key]);
        return $exists;
    }
}

if (!function_exists('is_email')) {
    function is_email($email): bool
    {
        return filter_var((string) $email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email): string
    {
        return filter_var((string) $email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user($username, $strict = false): string
    {
        $username = is_scalar($username) ? (string) $username : '';
        $username = preg_replace('/[^a-zA-Z0-9_\-@.]/', '', $username);
        return is_string($username) ? $username : '';
    }
}

if (!function_exists('wp_check_password')) {
    function wp_check_password($password, $hash, $user_id = ''): bool
    {
        return hash_equals((string) $hash, (string) $password);
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth'): string
    {
        return 'cbt-test-salt-for-' . (string) $scheme;
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '', $scheme = null): string
    {
        $base = 'http://localhost/wordpress';
        $path = is_scalar($path) ? (string) $path : '';
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false): string
    {
        return str_repeat('a', max(1, (int) $length));
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand($min = null, $max = null): int
    {
        $min = $min === null ? 0 : (int) $min;
        $max = $max === null ? mt_getrandmax() : (int) $max;
        return $min <= $max ? $min : $max;
    }
}

if (!function_exists('wp_using_ext_object_cache')) {
    function wp_using_ext_object_cache(): bool
    {
        return false;
    }
}
