<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Maintenance_Context_Builder
{
    /**
     * @return string[]
     */
    public static function allowed_maintenance_tabs(): array
    {
        return ['reset', 'seed', 'load'];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $seed_success_notice_summary = $notice !== ''
            ? CBT_Admin_Maintenance_Seed_Service::parse_test_data_seed_completion_notice($notice)
            : null;

        $reset_context = CBT_Admin_Maintenance_Reset_Service::build_reset_context($query, $notice, $error);
        $seed_context = CBT_Admin_Maintenance_Seed_Service::build_seed_status_context($query, $notice, $error);
        $load_context = CBT_Admin_Maintenance_Load_Test_Service::build_load_test_status_context();

        $requested_maintenance_tab = isset($query['cbt_maintenance_tab'])
            ? sanitize_key((string) wp_unslash((string) $query['cbt_maintenance_tab']))
            : '';
        $active_maintenance_tab = in_array($requested_maintenance_tab, self::allowed_maintenance_tabs(), true)
            ? $requested_maintenance_tab
            : '';

        if ($active_maintenance_tab === '') {
            if ((int) ($load_context['load_test_running_count'] ?? 0) > 0) {
                $active_maintenance_tab = 'load';
            } elseif (
                ((string) ($seed_context['seed_progress_token'] ?? '')) !== ''
                || is_array($seed_context['seed_progress_state'] ?? null)
            ) {
                $active_maintenance_tab = 'seed';
            } elseif (
                ((string) ($reset_context['reset_progress_token'] ?? '')) !== ''
                || is_array($reset_context['reset_progress_state'] ?? null)
            ) {
                $active_maintenance_tab = 'reset';
            } else {
                $active_maintenance_tab = 'reset';
            }
        }

        $active_tab_context = [];
        if ($active_maintenance_tab === 'seed') {
            $seed_context = CBT_Admin_Maintenance_Seed_Service::build_seed_context($query, $notice, $error);
            $active_tab_context = $seed_context;
        } elseif ($active_maintenance_tab === 'load') {
            $load_context = CBT_Admin_Maintenance_Load_Test_Service::build_load_test_context();
            $active_tab_context = $load_context;
        } else {
            $active_tab_context = $reset_context;
        }

        $load_test_running_count = (int) ($load_context['load_test_running_count'] ?? 0);
        $load_test_latest_running_exam = (string) ($load_context['load_test_latest_running_exam'] ?? '');
        $load_test_job_count = isset($load_context['load_test_jobs']) && is_array($load_context['load_test_jobs'])
            ? count((array) $load_context['load_test_jobs'])
            : 0;
        $seed_progress_is_running = !empty($seed_context['seed_progress_is_running']);
        $reset_progress_is_running = !empty($reset_context['reset_progress_is_running']);

        $hero_live_value = $load_test_running_count > 0
            ? 'Load Test Aktif'
            : ($seed_progress_is_running
                ? 'Seeder Aktif'
                : ($reset_progress_is_running ? 'Reset Aktif' : 'Siaga'));
        $hero_stage_preview = $load_test_running_count > 0
            ? sprintf(
                '%d job k6 berjalan%s',
                $load_test_running_count,
                $load_test_latest_running_exam !== '' ? ' · ' . $load_test_latest_running_exam : ''
            )
            : ($seed_progress_is_running
                ? (string) ($seed_context['seed_progress_stage_preview'] ?? 'Belum ada proses aktif')
                : ($reset_progress_is_running
                    ? (string) ($reset_context['reset_progress_stage_preview'] ?? 'Belum ada proses aktif')
                    : 'Belum ada proses aktif'));

        return [
            'notice' => $notice,
            'error' => $error,
            'seed_success_notice_summary' => $seed_success_notice_summary,
            'hero_live_value' => $hero_live_value,
            'hero_stage_preview' => $hero_stage_preview,
            'active_maintenance_tab' => $active_maintenance_tab,
            'active_tab_context' => $active_tab_context,
            'reset_progress_token' => (string) ($reset_context['reset_progress_token'] ?? ''),
            'reset_progress_state' => $reset_context['reset_progress_state'] ?? null,
            'reset_progress_is_running' => !empty($reset_context['reset_progress_is_running']),
            'reset_progress_status_label' => (string) ($reset_context['reset_progress_status_label'] ?? 'Siaga'),
            'reset_progress_summary_label' => (string) ($reset_context['reset_progress_summary_label'] ?? 'Belum ada reset aktif'),
            'reset_progress_stage_preview' => (string) ($reset_context['reset_progress_stage_preview'] ?? 'Belum ada proses aktif'),
            'seed_progress_token' => (string) ($seed_context['seed_progress_token'] ?? ''),
            'seed_progress_state' => $seed_context['seed_progress_state'] ?? null,
            'seed_progress_is_running' => !empty($seed_context['seed_progress_is_running']),
            'seed_progress_status_label' => (string) ($seed_context['seed_progress_status_label'] ?? 'Siaga'),
            'seed_progress_summary_label' => (string) ($seed_context['seed_progress_summary_label'] ?? 'Belum ada generator aktif'),
            'seed_progress_stage_preview' => (string) ($seed_context['seed_progress_stage_preview'] ?? 'Belum ada proses aktif'),
            'seed_progress_preset_label' => (string) ($seed_context['seed_progress_preset_label'] ?? 'Small'),
            'load_test_running_count' => $load_test_running_count,
            'load_test_job_count' => $load_test_job_count,
            'load_test_latest_running_exam' => $load_test_latest_running_exam,
        ];
    }
}
