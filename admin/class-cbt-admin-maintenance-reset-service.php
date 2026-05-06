<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-maintenance-common.php';

final class CBT_Admin_Maintenance_Reset_Service
{
    private const RESET_CONFIRM_PHRASE = 'RESET CBT';

    public static function handle_reset_database(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        CBT_Admin_Maintenance_Common::prepare_runtime_for_bulk_user_import();

        $token = isset($_GET['cbt_reset_progress_token']) ? sanitize_key((string) wp_unslash($_GET['cbt_reset_progress_token'])) : '';
        if ($token !== '') {
            self::continue_reset_database($token);
        }

        check_admin_referer('cbt_reset_database');

        $confirm_phrase = isset($_POST['confirm_phrase'])
            ? trim((string) sanitize_text_field(wp_unslash($_POST['confirm_phrase'])))
            : '';
        if ($confirm_phrase !== self::RESET_CONFIRM_PHRASE) {
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                null,
                'Konfirmasi tidak valid. Ketik persis: ' . self::RESET_CONFIRM_PHRASE,
                'reset'
            );
        }

        global $wpdb;
        $tables = CBT_Admin_Maintenance_Common::cbt_data_tables($wpdb);
        $user_ids = CBT_Admin_Maintenance_Common::collect_cbt_user_ids_for_reset();
        $token = strtolower((string) wp_generate_password(24, false, false));
        $total_units = count($tables) + max(1, count($user_ids)) + 1;
        $state = [
            'user_id' => get_current_user_id(),
            'started_at' => time(),
            'phase' => 'tables',
            'tables' => $tables,
            'table_index' => 0,
            'failed_tables' => [],
            'foreign_keys_disabled' => 0,
            'user_offset' => 0,
            'users_placeholder_done' => 0,
            'deleted_user_count' => 0,
            'total_units' => max(1, $total_units),
            'processed_units' => 0,
        ];
        $state_saved = set_transient(self::get_reset_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        $users_saved = set_transient(self::get_reset_progress_users_key($token), array_values($user_ids), 12 * HOUR_IN_SECONDS);
        if (!$state_saved || !$users_saved) {
            self::clear_reset_progress_transients($token);
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                null,
                'Gagal menyiapkan sesi reset database. Coba ulang lagi.',
                'reset'
            );
        }

        CBT_Admin_Maintenance_Common::redirect_maintenance_page_args([
            'page' => 'cbt-maintenance',
            'cbt_maintenance_tab' => 'reset',
            'cbt_reset_progress_token' => $token,
        ]);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_reset_context(array $query, string &$notice, string &$error): array
    {
        $reset_progress_token = isset($query['cbt_reset_progress_token']) ? sanitize_key((string) wp_unslash((string) $query['cbt_reset_progress_token'])) : '';
        $reset_progress_state = null;
        $reset_progress_total = 0;
        $reset_progress_processed = 0;
        $reset_progress_deleted_users = 0;
        $reset_progress_failed_tables = 0;
        $reset_progress_percent = 0.0;
        $reset_progress_is_running = false;
        $reset_progress_phase_label = '';
        $reset_progress_continue_url = '';

        if ($reset_progress_token !== '') {
            $reset_progress_state = self::get_reset_progress_state_for_current_user($reset_progress_token);
            if (is_array($reset_progress_state)) {
                $reset_progress_total = max(1, isset($reset_progress_state['total_units']) ? (int) $reset_progress_state['total_units'] : 1);
                $reset_progress_processed = max(0, isset($reset_progress_state['processed_units']) ? (int) $reset_progress_state['processed_units'] : 0);
                if ($reset_progress_processed > $reset_progress_total) {
                    $reset_progress_processed = $reset_progress_total;
                }
                $reset_progress_deleted_users = max(0, isset($reset_progress_state['deleted_user_count']) ? (int) $reset_progress_state['deleted_user_count'] : 0);
                $reset_progress_failed_tables = count((array) ($reset_progress_state['failed_tables'] ?? []));
                $reset_phase = sanitize_key((string) ($reset_progress_state['phase'] ?? 'tables'));
                $phase_labels = [
                    'tables' => 'Mengosongkan tabel CBT',
                    'users' => 'Menghapus user CBT',
                    'finalize' => 'Finalisasi reset',
                ];
                $reset_progress_phase_label = $phase_labels[$reset_phase] ?? 'Memproses reset database';
                $reset_progress_percent = $reset_progress_total > 0
                    ? round(((float) $reset_progress_processed / (float) $reset_progress_total) * 100, 2)
                    : 0.0;
                $reset_progress_is_running = $reset_progress_processed < $reset_progress_total;
                $reset_progress_continue_url = add_query_arg(
                    [
                        'action' => 'cbt_reset_database',
                        'cbt_reset_progress_token' => $reset_progress_token,
                    ],
                    admin_url('admin-ajax.php')
                );
            } elseif ($notice === '' && $error === '') {
                $error = 'Sesi reset database tidak ditemukan atau sudah berakhir. Silakan mulai ulang reset.';
            }
        }

        $reset_progress_status_tone = is_array($reset_progress_state)
            ? ($reset_progress_is_running ? 'running' : 'done')
            : 'idle';
        $reset_progress_status_label = is_array($reset_progress_state)
            ? ($reset_progress_is_running ? 'Sedang berjalan' : 'Selesai')
            : 'Siaga';
        $reset_progress_summary_label = is_array($reset_progress_state)
            ? ((string) $reset_progress_processed . ' / ' . (string) $reset_progress_total)
            : 'Belum ada reset aktif';
        $reset_progress_stage_preview = $reset_progress_phase_label !== '' ? $reset_progress_phase_label : 'Belum ada proses aktif';

        return [
            'reset_progress_token' => $reset_progress_token,
            'reset_progress_state' => $reset_progress_state,
            'reset_progress_total' => $reset_progress_total,
            'reset_progress_processed' => $reset_progress_processed,
            'reset_progress_deleted_users' => $reset_progress_deleted_users,
            'reset_progress_failed_tables' => $reset_progress_failed_tables,
            'reset_progress_percent' => $reset_progress_percent,
            'reset_progress_is_running' => $reset_progress_is_running,
            'reset_progress_phase_label' => $reset_progress_phase_label,
            'reset_progress_continue_url' => $reset_progress_continue_url,
            'reset_progress_status_tone' => $reset_progress_status_tone,
            'reset_progress_status_label' => $reset_progress_status_label,
            'reset_progress_summary_label' => $reset_progress_summary_label,
            'reset_progress_stage_preview' => $reset_progress_stage_preview,
        ];
    }

    private static function continue_reset_database(string $token): void
    {
        $state = self::get_reset_progress_state_for_current_user($token);
        if (!is_array($state)) {
            self::clear_reset_progress_transients($token);
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                null,
                'Sesi reset database berakhir. Silakan mulai ulang reset.',
                'reset'
            );
        }

        $tables = isset($state['tables']) && is_array($state['tables']) ? array_values((array) $state['tables']) : [];
        $users = get_transient(self::get_reset_progress_users_key($token));
        if (!is_array($users)) {
            $users = [];
        }
        $users = array_values(array_map('intval', $users));

        $phase = sanitize_key((string) ($state['phase'] ?? 'tables'));
        $table_index = max(0, isset($state['table_index']) ? (int) $state['table_index'] : 0);
        $user_offset = max(0, isset($state['user_offset']) ? (int) $state['user_offset'] : 0);
        $users_placeholder_done = !empty($state['users_placeholder_done']) ? 1 : 0;
        $deleted_user_count = max(0, isset($state['deleted_user_count']) ? (int) $state['deleted_user_count'] : 0);
        $failed_tables = [];
        if (isset($state['failed_tables']) && is_array($state['failed_tables'])) {
            foreach ($state['failed_tables'] as $failed_table) {
                $failed_table = str_replace('`', '', (string) $failed_table);
                if ($failed_table !== '') {
                    $failed_tables[$failed_table] = $failed_table;
                }
            }
        }

        $table_total = count($tables);
        if ($table_index > $table_total) {
            $table_index = $table_total;
        }
        $user_total = count($users);
        if ($user_offset > $user_total) {
            $user_offset = $user_total;
        }
        $total_units = max(1, isset($state['total_units']) ? (int) $state['total_units'] : ($table_total + max(1, $user_total) + 1));

        $max_batch_seconds = CBT_Admin_Maintenance_Common::get_reset_progress_max_batch_seconds();
        $table_batch_size = CBT_Admin_Maintenance_Common::get_reset_progress_table_batch_size();
        $user_batch_size = CBT_Admin_Maintenance_Common::get_reset_progress_user_batch_size();
        $batch_started_at = microtime(true);

        global $wpdb;

        if ($phase === 'tables') {
            if (empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
                $state['foreign_keys_disabled'] = 1;
            }

            $processed_tables_this_round = 0;
            while ($table_index < $table_total && $processed_tables_this_round < $table_batch_size) {
                $safe_table = str_replace('`', '', (string) $tables[$table_index]);
                if ($safe_table !== '') {
                    $truncated = $wpdb->query("TRUNCATE TABLE `{$safe_table}`");
                    if ($truncated === false) {
                        $deleted = $wpdb->query("DELETE FROM `{$safe_table}`");
                        if ($deleted === false) {
                            $failed_tables[$safe_table] = $safe_table;
                        } else {
                            $wpdb->query("ALTER TABLE `{$safe_table}` AUTO_INCREMENT = 1");
                        }
                    }
                }

                $table_index++;
                $processed_tables_this_round++;

                if ((microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                    break;
                }
            }

            if ($table_index >= $table_total) {
                if (!empty($state['foreign_keys_disabled'])) {
                    $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
                }
                $state['foreign_keys_disabled'] = 0;
                $phase = 'users';
            }
        }

        if ($phase === 'users' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            if ($user_total <= 0) {
                $users_placeholder_done = 1;
                $phase = 'finalize';
            } else {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $target_offset = min($user_offset + $user_batch_size, $user_total);
                for ($index = $user_offset; $index < $target_offset; $index++) {
                    $user_id = isset($users[$index]) ? (int) $users[$index] : 0;
                    if ($user_id <= 0) {
                        continue;
                    }
                    $deleted = wp_delete_user($user_id);
                    if ($deleted) {
                        $deleted_user_count++;
                    }

                    if (($index - $user_offset) >= 1 && (microtime(true) - $batch_started_at) >= $max_batch_seconds) {
                        $target_offset = $index + 1;
                        break;
                    }
                }
                $user_offset = $target_offset;
                if ($user_offset >= $user_total) {
                    $phase = 'finalize';
                }
            }
        }

        if ($phase === 'finalize' && (microtime(true) - $batch_started_at) < $max_batch_seconds) {
            CBT_Admin_Maintenance_Common::reset_cbt_global_token_options();
            CBT_UI_State::clear_all();
            CBT_Cache::reset_plugin_cache_state();
            self::clear_reset_progress_transients($token);

            if (!empty($failed_tables)) {
                CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                    null,
                    'Sebagian tabel gagal direset: ' . implode(', ', array_values($failed_tables))
                    . '. Data CBT termasuk Bank Soal mungkin belum sepenuhnya bersih. User CBT terhapus: ' . $deleted_user_count . '.',
                    'reset'
                );
            }

            $message = 'Data database CBT berhasil direset, termasuk exam, Bank Soal, question, attempt, dan hasil. User CBT terhapus: ' . $deleted_user_count . '.';
            CBT_Admin_Maintenance_Common::redirect_maintenance_page($message, null, 'reset');
        }

        $user_progress_units = $user_total > 0
            ? min($user_offset, $user_total)
            : ($users_placeholder_done ? 1 : 0);
        $processed_units = $table_index + $user_progress_units;
        if ($processed_units > ($total_units - 1)) {
            $processed_units = $total_units - 1;
        }
        if ($processed_units < 0) {
            $processed_units = 0;
        }

        $state['phase'] = $phase;
        $state['table_index'] = $table_index;
        $state['user_offset'] = $user_offset;
        $state['users_placeholder_done'] = $users_placeholder_done;
        $state['deleted_user_count'] = $deleted_user_count;
        $state['failed_tables'] = array_values($failed_tables);
        $state['total_units'] = $total_units;
        $state['processed_units'] = $processed_units;

        $state_saved = set_transient(self::get_reset_progress_state_key($token), $state, 12 * HOUR_IN_SECONDS);
        if (!$state_saved) {
            if (!empty($state['foreign_keys_disabled'])) {
                $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
            }
            self::clear_reset_progress_transients($token);
            CBT_Admin_Maintenance_Common::redirect_maintenance_page(
                null,
                'Gagal menyimpan progres reset database. Silakan mulai ulang reset.',
                'reset'
            );
        }

        CBT_Admin_Maintenance_Common::redirect_maintenance_page_args([
            'page' => 'cbt-maintenance',
            'cbt_maintenance_tab' => 'reset',
            'cbt_reset_progress_token' => $token,
        ]);
    }

    private static function get_reset_progress_state_key(string $token): string
    {
        return 'cbt_reset_progress_' . $token;
    }

    private static function get_reset_progress_users_key(string $token): string
    {
        return 'cbt_reset_progress_users_' . $token;
    }

    private static function clear_reset_progress_transients(string $token): void
    {
        delete_transient(self::get_reset_progress_state_key($token));
        delete_transient(self::get_reset_progress_users_key($token));
    }

    private static function get_reset_progress_state_for_current_user(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::get_reset_progress_state_key($token));
        if (!is_array($state)) {
            return null;
        }

        $state_user_id = isset($state['user_id']) ? (int) $state['user_id'] : 0;
        if ($state_user_id <= 0 || $state_user_id !== get_current_user_id()) {
            return null;
        }

        return $state;
    }
}
