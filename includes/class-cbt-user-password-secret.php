<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_User_Password_Secret
{
    public const META_KEY = 'cbt_plain_password';
    private const PREFIX = 'cbtenc:v1:';
    private const SODIUM_ALGORITHM = 'sodium-secretbox';
    private const OPENSSL_ALGORITHM = 'aes-256-gcm';

    public static function encrypt_for_storage(string $plain): string
    {
        $key = self::derive_key();
        if ($key === '') {
            return '';
        }

        try {
            if (function_exists('sodium_crypto_secretbox')) {
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $ciphertext = sodium_crypto_secretbox($plain, $nonce, $key);

                return self::encode_payload([
                    'alg' => self::SODIUM_ALGORITHM,
                    'nonce' => base64_encode($nonce),
                    'ciphertext' => base64_encode($ciphertext),
                ]);
            }

            if (function_exists('openssl_encrypt')) {
                $iv = random_bytes(12);
                $tag = '';
                $ciphertext = openssl_encrypt(
                    $plain,
                    self::OPENSSL_ALGORITHM,
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag
                );

                if (!is_string($ciphertext) || $tag === '') {
                    return '';
                }

                return self::encode_payload([
                    'alg' => self::OPENSSL_ALGORITHM,
                    'iv' => base64_encode($iv),
                    'tag' => base64_encode($tag),
                    'ciphertext' => base64_encode($ciphertext),
                ]);
            }
        } catch (Throwable $exception) {
            return '';
        }

        return '';
    }

    public static function decrypt_from_storage(string $stored): string
    {
        if ($stored === '') {
            return '';
        }

        if (!self::is_encrypted_value($stored)) {
            return $stored;
        }

        $key = self::derive_key();
        if ($key === '') {
            return '';
        }

        $payload = self::decode_payload($stored);
        if (empty($payload)) {
            return '';
        }

        $algorithm = (string) ($payload['alg'] ?? '');
        $ciphertext = self::decode_base64_value($payload['ciphertext'] ?? null);
        if ($ciphertext === '') {
            return '';
        }

        if ($algorithm === self::SODIUM_ALGORITHM && function_exists('sodium_crypto_secretbox_open')) {
            $nonce = self::decode_base64_value($payload['nonce'] ?? null);
            if ($nonce === '') {
                return '';
            }

            $plain = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            return is_string($plain) ? $plain : '';
        }

        if ($algorithm === self::OPENSSL_ALGORITHM && function_exists('openssl_decrypt')) {
            $iv = self::decode_base64_value($payload['iv'] ?? null);
            $tag = self::decode_base64_value($payload['tag'] ?? null);
            if ($iv === '' || $tag === '') {
                return '';
            }

            $plain = openssl_decrypt(
                $ciphertext,
                self::OPENSSL_ALGORITHM,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return is_string($plain) ? $plain : '';
        }

        return '';
    }

    public static function store_user_plain_password(int $user_id, string $plain): void
    {
        if ($user_id <= 0) {
            return;
        }

        update_user_meta($user_id, self::META_KEY, self::encrypt_for_storage($plain));
    }

    public static function get_user_plain_password(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        $stored = (string) get_user_meta($user_id, self::META_KEY, true);
        if ($stored === '') {
            return '';
        }

        $plain = self::decrypt_from_storage($stored);
        if ($plain !== '' && !self::is_encrypted_value($stored)) {
            self::store_user_plain_password($user_id, $plain);
        }

        return $plain;
    }

    public static function is_encrypted_value(string $stored): bool
    {
        return str_starts_with($stored, self::PREFIX);
    }

    /**
     * @param array<string,string> $payload
     */
    private static function encode_payload(array $payload): string
    {
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($payload)
            : json_encode($payload);

        if (!is_string($json) || $json === '') {
            return '';
        }

        return self::PREFIX . base64_encode($json);
    }

    /**
     * @return array<string,mixed>
     */
    private static function decode_payload(string $stored): array
    {
        $encoded = substr($stored, strlen(self::PREFIX));
        if ($encoded === '') {
            return [];
        }

        $json = base64_decode($encoded, true);
        if (!is_string($json) || $json === '') {
            return [];
        }

        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : [];
    }

    private static function decode_base64_value(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $decoded = base64_decode((string) $value, true);
        return is_string($decoded) ? $decoded : '';
    }

    private static function derive_key(): string
    {
        $material = self::get_key_material();
        if ($material === '') {
            return '';
        }

        return hash('sha256', $material, true);
    }

    private static function get_key_material(): string
    {
        if (defined('CBT_ENCRYPTION_KEY')) {
            $key = constant('CBT_ENCRYPTION_KEY');
            if (is_scalar($key) && trim((string) $key) !== '') {
                return (string) $key;
            }
        }

        if (function_exists('wp_salt')) {
            $salt = wp_salt('auth');
            if (is_scalar($salt) && trim((string) $salt) !== '') {
                return (string) $salt;
            }
        }

        if (defined('AUTH_KEY')) {
            $key = constant('AUTH_KEY');
            if (is_scalar($key) && trim((string) $key) !== '') {
                return (string) $key;
            }
        }

        return '';
    }
}
