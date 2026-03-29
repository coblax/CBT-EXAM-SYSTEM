<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/vendor/autoload.php';
require_once __DIR__ . '/TestCase.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/wordpress/');
}

if (!defined('CBT_EXAM_SYSTEM_PATH')) {
    define('CBT_EXAM_SYSTEM_PATH', dirname(__DIR__, 2) . '/');
}

if (!defined('CBT_EXAM_SYSTEM_URL')) {
    define('CBT_EXAM_SYSTEM_URL', 'http://localhost/wp-content/plugins/cbt-exam-system/');
}

if (!defined('CBT_EXAM_SYSTEM_VERSION')) {
    define('CBT_EXAM_SYSTEM_VERSION', '1.8.3');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', sys_get_temp_dir() . '/cbt-exam-system-test-plugins');
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

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str): string
    {
        $filtered = is_scalar($str) ? (string) $str : '';
        $filtered = strip_tags($filtered);
        $filtered = str_replace(["\r\n", "\r"], "\n", $filtered);

        return trim($filtered);
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

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0): string
    {
        return number_format((float) $number, (int) $decimals, '.', ',');
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

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path): string
    {
        $path = is_scalar($path) ? (string) $path : '';
        return str_replace('\\', '/', $path);
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target): bool
    {
        $path = is_scalar($target) ? (string) $target : '';
        if ($path === '') {
            return false;
        }

        return is_dir($path) || mkdir($path, 0777, true);
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
    function cbt_test_delete_directory(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = @scandir($path);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                cbt_test_delete_directory(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $item);
            }
        }

        @rmdir($path);
    }

    function cbt_test_reset_wordpress_storage(): void
    {
        $previousUploadDir = isset($GLOBALS['cbt_test_wp_upload_dir']) ? (string) $GLOBALS['cbt_test_wp_upload_dir'] : '';
        if ($previousUploadDir !== '') {
            cbt_test_delete_directory($previousUploadDir);
        }

        $GLOBALS['cbt_test_wp_options'] = [];
        $GLOBALS['cbt_test_wp_transients'] = [];
        $GLOBALS['cbt_test_wp_site_transients'] = [];
        $GLOBALS['cbt_test_wp_object_cache'] = [];
        $GLOBALS['cbt_test_wp_users'] = [];
        $GLOBALS['cbt_test_wp_user_meta'] = [];
        $GLOBALS['cbt_test_wp_upload_dir'] = sys_get_temp_dir() . '/cbt-exam-system-test-uploads';
        if (!is_dir(WP_PLUGIN_DIR)) {
            @mkdir(WP_PLUGIN_DIR, 0777, true);
        }
        $GLOBALS['cbt_test_wp_remote_get_map'] = [];
        $GLOBALS['cbt_test_download_url_map'] = [];
        $GLOBALS['cbt_test_current_user_id'] = 1;
        $GLOBALS['cbt_test_current_user_caps'] = [
            'cbt_manage_users' => true,
            'cbt_manage_system' => true,
            'manage_options' => true,
        ];
        $GLOBALS['cbt_test_last_redirect'] = '';

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

if (!function_exists('get_site_transient')) {
    function get_site_transient($transient)
    {
        $key = (string) $transient;
        return array_key_exists($key, $GLOBALS['cbt_test_wp_site_transients'])
            ? $GLOBALS['cbt_test_wp_site_transients'][$key]
            : false;
    }
}

if (!function_exists('set_site_transient')) {
    function set_site_transient($transient, $value, $expiration = 0): bool
    {
        $GLOBALS['cbt_test_wp_site_transients'][(string) $transient] = $value;
        return true;
    }
}

if (!function_exists('delete_site_transient')) {
    function delete_site_transient($transient): bool
    {
        $key = (string) $transient;
        $exists = array_key_exists($key, $GLOBALS['cbt_test_wp_site_transients']);
        unset($GLOBALS['cbt_test_wp_site_transients'][$key]);
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

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null): string
    {
        $base = 'http://localhost/wordpress';
        $path = is_scalar($path) ? (string) $path : '';
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '', $scheme = 'admin'): string
    {
        $base = site_url('wp-admin');
        $path = is_scalar($path) ? (string) $path : '';
        return $path === '' ? $base : rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = ''): string
    {
        $args = is_array($args) ? $args : [];
        $url = is_scalar($url) ? (string) $url : '';
        if ($url === '') {
            $url = 'http://localhost/';
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($args);
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

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value)
    {
        return $value;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability): bool
    {
        return !empty($GLOBALS['cbt_test_current_user_caps'][(string) $capability]);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) ($GLOBALS['cbt_test_current_user_id'] ?? 0);
    }
}

if (!function_exists('username_exists')) {
    function username_exists($username): int
    {
        $user = get_user_by('login', $username);
        return $user instanceof WP_User ? (int) $user->ID : 0;
    }
}

if (!function_exists('email_exists')) {
    function email_exists($email): int
    {
        $user = get_user_by('email', $email);
        return $user instanceof WP_User ? (int) $user->ID : 0;
    }
}

if (!function_exists('wp_insert_user')) {
    function wp_insert_user($userdata)
    {
        $userdata = is_array($userdata) ? $userdata : [];
        $userId = isset($userdata['ID']) ? (int) $userdata['ID'] : (count((array) ($GLOBALS['cbt_test_wp_users'] ?? [])) + 1);
        cbt_test_register_user([
            'ID' => $userId,
            'user_login' => (string) ($userdata['user_login'] ?? ''),
            'user_email' => (string) ($userdata['user_email'] ?? ''),
            'user_pass' => (string) ($userdata['user_pass'] ?? ''),
            'display_name' => (string) ($userdata['display_name'] ?? ''),
            'roles' => [(string) ($userdata['role'] ?? 'subscriber')],
        ]);

        return $userId;
    }
}

if (!function_exists('wp_update_user')) {
    function wp_update_user($userdata)
    {
        $userdata = is_array($userdata) ? $userdata : [];
        $userId = (int) ($userdata['ID'] ?? 0);
        $user = get_user_by('id', $userId);
        if (!($user instanceof WP_User)) {
            return new WP_Error('invalid_user', 'User tidak ditemukan.');
        }

        if (isset($userdata['user_email'])) {
            $user->user_email = (string) $userdata['user_email'];
        }
        if (isset($userdata['display_name'])) {
            $user->display_name = (string) $userdata['display_name'];
        }
        if (isset($userdata['role'])) {
            $user->roles = [(string) $userdata['role']];
        }

        $GLOBALS['cbt_test_wp_users'][$userId] = $user;
        return $userId;
    }
}

if (!function_exists('wp_set_password')) {
    function wp_set_password($password, $user_id): void
    {
        $user = get_user_by('id', (int) $user_id);
        if ($user instanceof WP_User) {
            $user->user_pass = (string) $password;
            $GLOBALS['cbt_test_wp_users'][(int) $user_id] = $user;
        }
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress'): bool
    {
        $GLOBALS['cbt_test_last_redirect'] = (string) $location;
        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []): void
    {
        throw new RuntimeException(is_scalar($message) ? (string) $message : 'wp_die');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1): string
    {
        return 'nonce-' . sanitize_key((string) $action);
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true): string
    {
        $field = '<input type="hidden" name="' . esc_attr((string) $name) . '" value="' . esc_attr(wp_create_nonce($action)) . '" />';
        if ($display) {
            echo $field;
        }

        return $field;
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce'): bool
    {
        return true;
    }
}

if (!function_exists('get_submit_button')) {
    function get_submit_button($text = '', $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = ''): string
    {
        $label = $text === '' ? 'Submit' : (string) $text;
        $classes = trim('button ' . (is_scalar($type) ? (string) $type : ''));
        $button = '<button type="submit" name="' . esc_attr((string) $name) . '" class="' . esc_attr($classes) . '">' . esc_html($label) . '</button>';

        return $wrap ? '<p class="submit">' . $button . '</p>' : $button;
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = '', $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = ''): void
    {
        echo get_submit_button($text, $type, $name, $wrap, $other_attributes);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = [])
    {
        $key = is_scalar($url) ? (string) $url : '';
        if (!array_key_exists($key, $GLOBALS['cbt_test_wp_remote_get_map'])) {
            return new WP_Error('missing_remote_stub', 'No remote stub registered for ' . $key);
        }

        return $GLOBALS['cbt_test_wp_remote_get_map'][$key];
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response): string
    {
        return is_array($response) && isset($response['body']) && is_scalar($response['body'])
            ? (string) $response['body']
            : '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int
    {
        if (!is_array($response)) {
            return 0;
        }

        $meta = isset($response['response']) && is_array($response['response']) ? $response['response'] : [];
        return isset($meta['code']) ? (int) $meta['code'] : 0;
    }
}

if (!function_exists('download_url')) {
    function download_url($url, $timeout = 300, $signature_verification = false)
    {
        $key = is_scalar($url) ? (string) $url : '';
        if (!array_key_exists($key, $GLOBALS['cbt_test_download_url_map'])) {
            return new WP_Error('missing_download_stub', 'No download stub registered for ' . $key);
        }

        $source = $GLOBALS['cbt_test_download_url_map'][$key];
        if ($source instanceof WP_Error) {
            return $source;
        }

        $sourcePath = is_scalar($source) ? (string) $source : '';
        if ($sourcePath === '' || !file_exists($sourcePath)) {
            return new WP_Error('download_source_missing', 'Download stub source not found for ' . $key);
        }

        $targetPath = tempnam(sys_get_temp_dir(), 'cbt-update-');
        if ($targetPath === false) {
            return new WP_Error('download_target_failed', 'Failed creating temporary download file.');
        }

        copy($sourcePath, $targetPath);
        return $targetPath;
    }
}

if (!function_exists('wp_clean_plugins_cache')) {
    function wp_clean_plugins_cache($clear_update_cache = true): void
    {
    }
}

if (!function_exists('wp_get_wp_version')) {
    function wp_get_wp_version(): string
    {
        return '6.8.1';
    }
}

if (!function_exists('is_php_version_compatible')) {
    function is_php_version_compatible($required): bool
    {
        $required = is_scalar($required) ? (string) $required : '';
        return $required !== '' && version_compare(PHP_VERSION, $required, '>=');
    }
}

if (!function_exists('is_wp_version_compatible')) {
    function is_wp_version_compatible($required): bool
    {
        $required = is_scalar($required) ? (string) $required : '';
        return $required !== '' && version_compare(wp_get_wp_version(), $required, '>=');
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false): array
    {
        $basedir = (string) ($GLOBALS['cbt_test_wp_upload_dir'] ?? (sys_get_temp_dir() . '/cbt-exam-system-test-uploads'));
        if ($create_dir && !is_dir($basedir)) {
            @mkdir($basedir, 0755, true);
        }

        return [
            'path' => $basedir,
            'url' => 'http://localhost/uploads',
            'subdir' => '',
            'basedir' => $basedir,
            'baseurl' => 'http://localhost/uploads',
            'error' => '',
        ];
    }
}
