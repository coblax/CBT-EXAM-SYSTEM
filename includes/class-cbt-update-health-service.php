<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Update_Health_Service
{
    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $context
     * @return array{status:string,ok:bool,items:array<int,array<string,string>>,message:string}
     */
    public static function run(array $manifest, array $context = []): array
    {
        $items = [];
        $expected_version = sanitize_text_field((string) ($manifest['version'] ?? ''));

        $active_version = CBT_Update_Release_Helper::current_version();
        $items[] = self::item(
            'active_version',
            'Versi plugin aktif',
            $expected_version !== '' && $active_version === $expected_version,
            $expected_version !== ''
                ? sprintf('Versi aktif %s, target %s.', $active_version !== '' ? $active_version : '-', $expected_version)
                : 'Manifest tidak memuat versi target.'
        );

        $bootstrap = defined('CBT_EXAM_SYSTEM_PATH') ? CBT_EXAM_SYSTEM_PATH . 'cbt-exam-system.php' : '';
        $items[] = self::item(
            'bootstrap_file',
            'Bootstrap plugin',
            $bootstrap !== '' && is_readable($bootstrap),
            $bootstrap !== '' && is_readable($bootstrap) ? 'File bootstrap plugin terbaca.' : 'File bootstrap plugin tidak terbaca.'
        );

        if (class_exists('CBT_Activator') && method_exists('CBT_Activator', 'maybe_upgrade')) {
            try {
                CBT_Activator::maybe_upgrade();
                $items[] = self::item('migration', 'Migrasi database', true, 'Activator maybe_upgrade berhasil dipanggil.');
            } catch (Throwable $throwable) {
                $items[] = self::item('migration', 'Migrasi database', false, $throwable->getMessage());
            }
        } else {
            $items[] = self::item('migration', 'Migrasi database', false, 'CBT_Activator tidak tersedia.');
        }

        $expected_db_version = self::expected_db_version();
        $installed_db_version = (string) get_option('cbt_exam_system_db_version', '');
        $items[] = self::item(
            'db_version',
            'DB version',
            $expected_db_version === '' || $installed_db_version === $expected_db_version,
            $expected_db_version === ''
                ? 'DB version target tidak bisa dibaca dari activator.'
                : sprintf('DB version aktif %s, target %s.', $installed_db_version !== '' ? $installed_db_version : '-', $expected_db_version)
        );

        if (class_exists('CBT_Cache') && method_exists('CBT_Cache', 'invalidate_all')) {
            try {
                CBT_Cache::invalidate_all();
                $items[] = self::item('cache_invalidation', 'Invalidate cache', true, 'Cache CBT berhasil diinvalidasi.');
            } catch (Throwable $throwable) {
                $items[] = self::item('cache_invalidation', 'Invalidate cache', false, $throwable->getMessage());
            }
        } else {
            $items[] = self::item('cache_invalidation', 'Invalidate cache', false, 'CBT_Cache tidak tersedia.');
        }

        if (class_exists('CBT_Runtime') && method_exists('CBT_Runtime', 'runtime_mode')) {
            try {
                $mode = CBT_Runtime::runtime_mode();
                $items[] = self::item('runtime_mode', 'Runtime Redis', true, 'Runtime mode: ' . (is_scalar($mode) ? (string) $mode : 'available') . '.');
            } catch (Throwable $throwable) {
                $items[] = self::item('runtime_mode', 'Runtime Redis', false, $throwable->getMessage());
            }
        } elseif (class_exists('CBT_Cache') && method_exists('CBT_Cache', 'runtime_mode')) {
            try {
                $items[] = self::item('runtime_mode', 'Runtime Redis', true, 'Cache mode: ' . CBT_Cache::runtime_mode() . '.');
            } catch (Throwable $throwable) {
                $items[] = self::item('runtime_mode', 'Runtime Redis', false, $throwable->getMessage());
            }
        } else {
            $items[] = self::item('runtime_mode', 'Runtime Redis', true, 'Runtime helper tidak tersedia, check dilewati.');
        }

        if (class_exists('CBT_Student_Cohort_Index_Service') && method_exists('CBT_Student_Cohort_Index_Service', 'get_rebuild_state')) {
            try {
                $state = CBT_Student_Cohort_Index_Service::get_rebuild_state();
                if (empty($state['active']) && method_exists('CBT_Student_Cohort_Index_Service', 'start_rebuild')) {
                    CBT_Student_Cohort_Index_Service::start_rebuild('update_post_health');
                    $items[] = self::item('cohort_rebuild', 'Student Cohort Index', true, 'Rebuild Student Cohort Index dimulai.');
                } else {
                    $items[] = self::item('cohort_rebuild', 'Student Cohort Index', true, 'Rebuild Student Cohort Index sedang berjalan.');
                }
            } catch (Throwable $throwable) {
                $items[] = self::item('cohort_rebuild', 'Student Cohort Index', false, $throwable->getMessage());
            }
        } else {
            $items[] = self::item('cohort_rebuild', 'Student Cohort Index', true, 'Service cohort tidak tersedia, check dilewati.');
        }

        $ok = true;
        foreach ($items as $item) {
            if ((string) ($item['status'] ?? '') !== 'ok') {
                $ok = false;
                break;
            }
        }

        return [
            'status' => $ok ? 'ok' : 'failed',
            'ok' => $ok,
            'items' => $items,
            'message' => $ok ? 'Post-update health check selesai.' : 'Post-update health check menemukan masalah.',
        ];
    }

    private static function expected_db_version(): string
    {
        if (!class_exists('CBT_Activator')) {
            return '';
        }

        try {
            $reflection = new ReflectionClass(CBT_Activator::class);
            $constant = $reflection->getReflectionConstant('DB_VERSION');
            if ($constant instanceof ReflectionClassConstant) {
                return (string) $constant->getValue();
            }
        } catch (Throwable $throwable) {
            return '';
        }

        return '';
    }

    /**
     * @return array<string,string>
     */
    private static function item(string $key, string $label, bool $ok, string $message): array
    {
        return [
            'key' => sanitize_key($key),
            'label' => sanitize_text_field($label),
            'status' => $ok ? 'ok' : 'failed',
            'message' => sanitize_text_field($message),
        ];
    }
}
