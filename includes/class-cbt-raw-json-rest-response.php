<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Raw_JSON_REST_Response extends WP_REST_Response
{
    /** @var string */
    private $raw_json = '';

    /**
     * @param mixed $data
     * @param array<string,string> $headers
     */
    public function __construct(string $raw_json, $data = null, int $status = 200, array $headers = [])
    {
        parent::__construct($data, $status, $headers);
        $this->raw_json = $raw_json;
    }

    public function get_raw_json(): string
    {
        return $this->raw_json;
    }

    /**
     * Keep direct callback/unit-test ergonomics while allowing production serving to avoid
     * decoding a raw JSON body that is already ready to echo.
     *
     * @return mixed
     */
    public function get_data()
    {
        $data = parent::get_data();
        if ($data !== null || $this->raw_json === '') {
            return $data;
        }

        $decoded = json_decode($this->raw_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        parent::set_data($decoded);
        return $decoded;
    }

    public function offsetExists($offset): bool
    {
        $data = $this->get_data();
        return is_array($data) && array_key_exists($offset, $data);
    }

    public function offsetGet($offset)
    {
        $data = $this->get_data();
        return is_array($data) ? ($data[$offset] ?? null) : null;
    }

    public function offsetSet($offset, $value): void
    {
        $data = $this->get_data();
        if (!is_array($data)) {
            $data = [];
        }
        if ($offset === null) {
            $data[] = $value;
        } else {
            $data[$offset] = $value;
        }
        parent::set_data($data);
    }

    public function offsetUnset($offset): void
    {
        $data = $this->get_data();
        if (is_array($data)) {
            unset($data[$offset]);
            parent::set_data($data);
        }
    }

    /**
     * @param mixed $payload
     */
    public static function from_payload($payload, int $status = 200, array $headers = []): ?self
    {
        $raw_json = wp_json_encode($payload);
        if (!is_string($raw_json) || $raw_json === '') {
            return null;
        }

        return new self($raw_json, $payload, $status, $headers);
    }

    public static function register_rest_filter(): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('rest_pre_serve_request', [self::class, 'serve_raw_json_response'], 10, 4);
    }

    /**
     * @param mixed $served
     * @param mixed $result
     * @param mixed $request
     * @param mixed $server
     * @return mixed
     */
    public static function serve_raw_json_response($served, $result, $request, $server)
    {
        unset($request, $server);

        if (!$result instanceof self) {
            return $served;
        }

        if (!headers_sent()) {
            status_header($result->get_status());
            header('Content-Type: application/json; charset=' . get_option('blog_charset', 'UTF-8'));
            foreach ($result->get_headers() as $key => $value) {
                header((string) $key . ': ' . (string) $value, true);
            }
        }

        echo $result->get_raw_json();
        return true;
    }
}
