<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Update_Backup_Service
{
    private const OPTION_BACKUPS = 'cbt_update_backups_v1';
    private const BACKUP_DIR_NAME = 'cbt-update-backups';
    private const MAX_BACKUPS = 5;
    private const RETENTION_DAYS = 30;

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_backups(): array
    {
        $backups = get_option(self::OPTION_BACKUPS, []);
        if (!is_array($backups)) {
            return [];
        }

        $normalized = [];
        foreach ($backups as $backup) {
            if (!is_array($backup)) {
                continue;
            }
            $normalized_backup = self::normalize_backup($backup);
            if ($normalized_backup['id'] !== '') {
                $normalized[] = $normalized_backup;
            }
        }

        usort($normalized, static function (array $left, array $right): int {
            return (int) ($right['created_at_ts'] ?? 0) <=> (int) ($left['created_at_ts'] ?? 0);
        });

        return $normalized;
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function create_backup(string $token, string $version, ?string $source_dir = null)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_extension_missing', 'Ekstensi ZipArchive wajib aktif untuk membuat backup update.');
        }

        $token = sanitize_key($token);
        if ($token === '') {
            $token = self::generate_token();
        }

        $version = sanitize_text_field($version);
        if ($version === '') {
            $version = 'unknown';
        }

        $source_dir = $source_dir !== null ? $source_dir : (defined('CBT_EXAM_SYSTEM_PATH') ? CBT_EXAM_SYSTEM_PATH : '');
        $source_dir = rtrim(wp_normalize_path($source_dir), '/');
        if ($source_dir === '' || !is_dir($source_dir)) {
            return new WP_Error('backup_source_missing', 'Folder plugin CBT yang akan dibackup tidak ditemukan.');
        }

        $backup_dir = self::backup_dir();
        if (is_wp_error($backup_dir)) {
            return $backup_dir;
        }

        $created_at_ts = time();
        $created_at = wp_date('Y-m-d H:i:s', $created_at_ts, wp_timezone());
        $safe_version = preg_replace('/[^A-Za-z0-9._-]/', '-', $version);
        $safe_version = is_string($safe_version) && $safe_version !== '' ? $safe_version : 'unknown';
        $file_name = sprintf('cbt-exam-system-%s-%s-%s.zip', $safe_version, wp_date('YmdHis', $created_at_ts, wp_timezone()), $token);
        $path = rtrim((string) $backup_dir, '/\\') . DIRECTORY_SEPARATOR . $file_name;

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return new WP_Error('backup_zip_open_failed', 'File backup update tidak bisa dibuat.');
        }

        $added = self::add_directory_to_zip($zip, $source_dir, 'cbt-exam-system');
        $zip->close();

        if ($added <= 0 || !file_exists($path)) {
            @unlink($path);
            return new WP_Error('backup_zip_empty', 'Backup update gagal karena tidak ada file plugin yang masuk ke zip.');
        }

        $hash = hash_file('sha256', $path);
        $backup = self::normalize_backup([
            'id' => $token,
            'token' => $token,
            'version' => $version,
            'file_name' => $file_name,
            'path' => $path,
            'size' => filesize($path) ?: 0,
            'sha256' => is_string($hash) ? $hash : '',
            'created_at' => $created_at,
            'created_at_ts' => $created_at_ts,
        ]);

        $backups = self::get_backups();
        array_unshift($backups, $backup);
        self::save_backups(self::prune_backups($backups));

        return $backup;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_backup(string $id): ?array
    {
        $id = sanitize_key($id);
        if ($id === '') {
            return null;
        }

        foreach (self::get_backups() as $backup) {
            if ((string) ($backup['id'] ?? '') === $id) {
                return $backup;
            }
        }

        return null;
    }

    /**
     * @return true|WP_Error
     */
    public static function validate_backup_for_rollback(array $backup)
    {
        $backup = self::normalize_backup($backup);
        $path = (string) ($backup['path'] ?? '');
        if ($path === '' || !file_exists($path)) {
            return new WP_Error('backup_missing', 'File backup rollback tidak ditemukan.');
        }

        $expected_hash = (string) ($backup['sha256'] ?? '');
        if ($expected_hash !== '') {
            $actual_hash = hash_file('sha256', $path);
            if (!is_string($actual_hash) || !hash_equals(strtolower($expected_hash), strtolower($actual_hash))) {
                return new WP_Error('backup_checksum_mismatch', 'Checksum backup rollback tidak cocok.');
            }
        }

        return CBT_Update_Release_Helper::validate_zip_package_structure($path, (string) ($backup['version'] ?? ''));
    }

    /**
     * @return string|WP_Error
     */
    public static function backup_dir()
    {
        $uploads = wp_upload_dir(null, true, false);
        $base_dir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
        if ($base_dir === '' || !empty($uploads['error'])) {
            return new WP_Error('upload_dir_unavailable', 'Folder uploads WordPress tidak tersedia untuk backup update.');
        }

        $dir = rtrim($base_dir, '/\\') . DIRECTORY_SEPARATOR . self::BACKUP_DIR_NAME;
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return new WP_Error('backup_dir_failed', 'Folder backup update tidak bisa dibuat.');
        }

        self::ensure_directory_protection($dir);
        return $dir;
    }

    /**
     * @param array<int,array<string,mixed>> $backups
     */
    public static function save_backups(array $backups): void
    {
        update_option(self::OPTION_BACKUPS, array_values(array_map([self::class, 'normalize_backup'], $backups)), false);
    }

    /**
     * @param array<int,array<string,mixed>> $backups
     * @return array<int,array<string,mixed>>
     */
    public static function prune_backups(array $backups): array
    {
        $cutoff = time() - (self::RETENTION_DAYS * DAY_IN_SECONDS);
        $kept = [];

        foreach ($backups as $backup) {
            $backup = self::normalize_backup($backup);
            $created_at_ts = (int) ($backup['created_at_ts'] ?? 0);
            $path = (string) ($backup['path'] ?? '');
            if (count($kept) >= self::MAX_BACKUPS || ($created_at_ts > 0 && $created_at_ts < $cutoff)) {
                if ($path !== '' && file_exists($path)) {
                    @unlink($path);
                }
                continue;
            }

            $kept[] = $backup;
        }

        return $kept;
    }

    private static function generate_token(): string
    {
        if (function_exists('wp_generate_password')) {
            return sanitize_key(wp_generate_password(20, false, false));
        }

        return sanitize_key(bin2hex(random_bytes(10)));
    }

    private static function ensure_directory_protection(string $dir): void
    {
        $files = [
            'index.php' => "<?php\n// Silence is golden.\n",
            '.htaccess' => "Require all denied\nDeny from all\n",
            'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\" /><add accessType=\"Deny\" users=\"*\" /></authorization></security></system.webServer></configuration>\n",
        ];

        foreach ($files as $name => $content) {
            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
            if (!file_exists($path)) {
                @file_put_contents($path, $content);
            }
        }
    }

    private static function add_directory_to_zip(ZipArchive $zip, string $source_dir, string $zip_root): int
    {
        $count = 0;
        $source_dir = rtrim(wp_normalize_path($source_dir), '/');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file_info) {
            if (!$file_info instanceof SplFileInfo) {
                continue;
            }

            $path = wp_normalize_path($file_info->getPathname());
            $relative = ltrim(substr($path, strlen($source_dir)), '/');
            if ($relative === '' || $relative === '.git' || str_starts_with($relative, '.git/')) {
                continue;
            }

            $entry = rtrim($zip_root, '/') . '/' . $relative;
            if ($file_info->isDir()) {
                $zip->addEmptyDir($entry);
                continue;
            }

            if ($file_info->isFile() && $zip->addFile($path, $entry)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string,mixed> $backup
     * @return array<string,mixed>
     */
    private static function normalize_backup(array $backup): array
    {
        return [
            'id' => sanitize_key((string) ($backup['id'] ?? ($backup['token'] ?? ''))),
            'token' => sanitize_key((string) ($backup['token'] ?? ($backup['id'] ?? ''))),
            'version' => sanitize_text_field((string) ($backup['version'] ?? '')),
            'file_name' => self::sanitize_file_name((string) ($backup['file_name'] ?? '')),
            'path' => (string) ($backup['path'] ?? ''),
            'size' => max(0, (int) ($backup['size'] ?? 0)),
            'sha256' => strtolower(preg_replace('/[^a-f0-9]/i', '', (string) ($backup['sha256'] ?? '')) ?? ''),
            'created_at' => sanitize_text_field((string) ($backup['created_at'] ?? '')),
            'created_at_ts' => max(0, (int) ($backup['created_at_ts'] ?? 0)),
        ];
    }

    private static function sanitize_file_name(string $file_name): string
    {
        if (function_exists('sanitize_file_name')) {
            return sanitize_file_name($file_name);
        }

        $sanitized = preg_replace('/[^A-Za-z0-9._-]/', '-', $file_name);
        return is_string($sanitized) ? trim($sanitized, '.-') : '';
    }
}
