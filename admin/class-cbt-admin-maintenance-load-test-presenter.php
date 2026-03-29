<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Maintenance_Load_Test_Presenter
{
    /**
     * @param array<string,array<string,mixed>> $jobs
     * @return array<string,mixed>
     */
    public static function build_jobs_view(array $jobs): array
    {
        $normalized_jobs = [];
        $job_options = [];
        $job_cards = [];
        $running_job_count = 0;

        foreach ($jobs as $job_row) {
            $normalized_job = CBT_Admin_Maintenance_Load_Test_Service::normalize_load_test_job((array) $job_row);
            $normalized_jobs[] = $normalized_job;

            if (in_array((string) ($normalized_job['status'] ?? ''), ['queued', 'running'], true)) {
                $running_job_count++;
            }

            $job_options[] = [
                'id' => (string) ($normalized_job['id'] ?? ''),
                'label' => CBT_Admin_Maintenance_Load_Test_Service::get_load_test_job_selection_label($normalized_job),
            ];

            $job_cards[] = [
                'job' => $normalized_job,
                'status_meta' => CBT_Admin_Maintenance_Load_Test_Service::get_load_test_status_meta((string) ($normalized_job['status'] ?? 'queued')),
                'summary' => CBT_Admin_Maintenance_Load_Test_Service::read_load_test_job_summary($normalized_job),
                'stdout_tail' => CBT_Admin_Maintenance_Load_Test_Service::read_load_test_log_tail($normalized_job, 'stdout'),
                'stderr_tail' => CBT_Admin_Maintenance_Load_Test_Service::read_load_test_log_tail($normalized_job, 'stderr'),
                'artifacts' => CBT_Admin_Maintenance_Load_Test_Service::get_load_test_job_artifacts($normalized_job),
                'run_started' => CBT_Admin_Maintenance_Load_Test_Service::format_load_test_datetime(
                    (string) (($normalized_job['started_at'] ?? '') !== '' ? $normalized_job['started_at'] : ($normalized_job['created_at'] ?? ''))
                ),
                'run_finished' => CBT_Admin_Maintenance_Load_Test_Service::format_load_test_datetime((string) ($normalized_job['finished_at'] ?? '')),
            ];
        }

        $selected_job_id = isset($normalized_jobs[0]['id']) ? (string) $normalized_jobs[0]['id'] : '';

        return [
            'jobs' => $normalized_jobs,
            'selected_job_id' => $selected_job_id,
            'running_job_count' => $running_job_count,
            'job_options' => $job_options,
            'job_cards' => $job_cards,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $jobs
     */
    public static function render_jobs_markup(array $jobs): string
    {
        $view = self::build_jobs_view($jobs);

        $selected_job_id = (string) ($view['selected_job_id'] ?? '');
        $running_job_count = (int) ($view['running_job_count'] ?? 0);
        $job_options = isset($view['job_options']) && is_array($view['job_options']) ? (array) $view['job_options'] : [];
        $job_cards = isset($view['job_cards']) && is_array($view['job_cards']) ? (array) $view['job_cards'] : [];

        ob_start();
        require CBT_EXAM_SYSTEM_PATH . 'admin/views/maintenance/partials/load-test-jobs.php';
        return (string) ob_get_clean();
    }
}
