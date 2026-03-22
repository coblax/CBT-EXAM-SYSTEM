<?php

if (!defined('ABSPATH')) {
    exit;
}

if (empty($jobs)) {
    ?>
    <div class="cbt-maintenance-alert" style="border-color:#dbe5ef;background:#f8fbff;color:#1e3a8a;">
        <strong>Belum ada job.</strong> Pilih exam aktif lalu tekan <code>Start Load Test</code> untuk membuat runner k6 pertama.
    </div>
    <?php
    return;
}

$jobs = array_values($jobs);
$first_job = isset($jobs[0]) && is_array($jobs[0])
    ? CBT_Admin_Maintenance_Service::normalize_load_test_job((array) $jobs[0])
    : null;
$first_job_id = is_array($first_job) ? (string) ($first_job['id'] ?? '') : '';
$running_job_count = 0;
foreach ($jobs as $job_row) {
    $normalized_job = CBT_Admin_Maintenance_Service::normalize_load_test_job((array) $job_row);
    if (in_array((string) ($normalized_job['status'] ?? ''), ['queued', 'running'], true)) {
        $running_job_count++;
    }
}
?>
<div class="cbt-maintenance-load-jobs-toolbar">
    <div class="cbt-maintenance-field">
        <label for="cbt-load-job-selector">Pilih hasil test</label>
        <div class="cbt-maintenance-select-wrap">
            <select id="cbt-load-job-selector" data-load-job-selector>
                <?php foreach ($jobs as $index => $job): ?>
                    <?php $job = CBT_Admin_Maintenance_Service::normalize_load_test_job((array) $job); ?>
                    <option value="<?php echo esc_attr((string) ($job['id'] ?? '')); ?>" <?php selected($index === 0); ?>>
                        <?php echo esc_html(CBT_Admin_Maintenance_Service::get_load_test_job_selection_label($job)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="cbt-maintenance-field-help">Urutan terbaru ada di paling atas. Label menampilkan exam, status, dan waktu mulai test agar mudah memilih hasil terakhir.</p>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:flex-end;">
        <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($running_job_count > 0 ? 'running' : 'idle'); ?>">
            <?php echo esc_html(count($jobs) . ' histori'); ?>
        </span>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Hapus semua histori load test? Job aktif akan dihentikan dan workspace runtime yang tersisa akan ikut dibersihkan.');">
            <?php wp_nonce_field('cbt_clear_load_test_jobs'); ?>
            <input type="hidden" name="action" value="cbt_clear_load_test_jobs" />
            <input type="hidden" name="cbt_maintenance_tab" value="load" />
            <button type="submit" class="button button-secondary">Hapus Semua Histori</button>
        </form>
    </div>
</div>

<div class="cbt-maintenance-load-job-list">
<?php foreach ($jobs as $job): ?>
    <?php
    $job = CBT_Admin_Maintenance_Service::normalize_load_test_job((array) $job);
    $status_meta = CBT_Admin_Maintenance_Service::get_load_test_status_meta((string) ($job['status'] ?? 'queued'));
    $summary = CBT_Admin_Maintenance_Service::read_load_test_job_summary($job);
    $stdout_tail = CBT_Admin_Maintenance_Service::read_load_test_log_tail($job, 'stdout');
    $stderr_tail = CBT_Admin_Maintenance_Service::read_load_test_log_tail($job, 'stderr');
    $artifacts = CBT_Admin_Maintenance_Service::get_load_test_job_artifacts($job);
    $run_started = CBT_Admin_Maintenance_Service::format_load_test_datetime(
        (string) (($job['started_at'] ?? '') !== '' ? $job['started_at'] : ($job['created_at'] ?? ''))
    );
    $run_finished = CBT_Admin_Maintenance_Service::format_load_test_datetime((string) ($job['finished_at'] ?? ''));
    ?>
    <article
        class="cbt-maintenance-load-job-card"
        data-load-job-card
        data-load-job-id="<?php echo esc_attr((string) ($job['id'] ?? '')); ?>"
        <?php echo (string) ($job['id'] ?? '') !== $first_job_id ? ' hidden' : ''; ?>
    >
        <div class="cbt-maintenance-card-header" style="margin-bottom:12px;">
            <div>
                <h3 style="margin:0 0 6px;font-size:17px;"><?php echo esc_html((string) ($job['exam_title'] ?? 'Exam')); ?></h3>
                <p style="margin:0;color:#64748b;">
                    <?php echo esc_html((string) ($job['subject_name'] ?? '')); ?>
                    <?php if (!empty($job['profile']['profile_label'])): ?>
                        · <?php echo esc_html((string) $job['profile']['profile_label']); ?>
                    <?php endif; ?>
                </p>
                <p class="cbt-maintenance-load-job-run-meta">
                    Run: <?php echo esc_html($run_started); ?>
                    <?php if ($run_finished !== '-'): ?>
                        · Selesai: <?php echo esc_html($run_finished); ?>
                    <?php endif; ?>
                </p>
            </div>
            <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr((string) $status_meta['tone']); ?>">
                <?php echo esc_html((string) $status_meta['label']); ?>
            </span>
        </div>

        <div class="cbt-maintenance-load-job-grid">
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">PID</span>
                <strong><?php echo esc_html((string) max(0, (int) ($job['pid'] ?? 0))); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Started</span>
                <strong><?php echo esc_html($run_started); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Finished</span>
                <strong><?php echo esc_html($run_finished); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Exit Code</span>
                <strong><?php echo esc_html($job['exit_code'] === null ? '-' : (string) $job['exit_code']); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Users / VUs</span>
                <strong>
                    <?php
                    echo esc_html(
                        (string) max(0, (int) ($job['student_count'] ?? 0))
                        . ' / '
                        . (string) max(0, (int) (($job['profile']['vus'] ?? 0)))
                    );
                    ?>
                </strong>
            </div>
        </div>

        <div class="cbt-maintenance-load-job-grid" style="margin-top:10px;">
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Iterations</span>
                <strong><?php echo esc_html((string) max(0, (int) ($job['profile']['iterations'] ?? 0))); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Q/User</span>
                <strong><?php echo esc_html((string) max(0, (int) ($job['profile']['questions_per_user'] ?? 0))); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">p95 Req</span>
                <strong>
                    <?php
                    echo ($summary['http_req_duration_p95'] ?? null) !== null
                        ? esc_html(number_format_i18n((float) ($summary['http_req_duration_p95'] ?? 0), 2) . ' ms')
                        : '-';
                    ?>
                </strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Req Failed</span>
                <strong>
                    <?php
                    echo ($summary['http_req_failed_rate'] ?? null) !== null
                        ? esc_html(number_format_i18n(((float) ($summary['http_req_failed_rate'] ?? 0)) * 100, 2) . '%')
                        : '-';
                    ?>
                </strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Session Success</span>
                <strong>
                    <?php
                    echo ($summary['session_success_rate'] ?? null) !== null
                        ? esc_html(number_format_i18n(((float) ($summary['session_success_rate'] ?? 0)) * 100, 2) . '%')
                        : '-';
                    ?>
                </strong>
            </div>
        </div>

        <?php if ((string) ($job['notes'] ?? '') !== ''): ?>
            <p class="cbt-maintenance-progress-note" style="margin-top:14px;">
                <?php echo esc_html((string) ($job['notes'] ?? '')); ?>
            </p>
        <?php endif; ?>

        <?php if ((string) ($job['command_preview'] ?? '') !== ''): ?>
            <details class="cbt-maintenance-load-job-details">
                <summary>Command Preview</summary>
                <pre><?php echo esc_html((string) ($job['command_preview'] ?? '')); ?></pre>
            </details>
        <?php endif; ?>

        <?php if ($stdout_tail !== '' || $stderr_tail !== ''): ?>
            <div class="cbt-maintenance-load-job-log-grid">
                <div class="cbt-maintenance-load-job-log-box">
                    <span class="cbt-maintenance-progress-activity-label">Stdout Tail</span>
                    <pre><?php echo esc_html($stdout_tail !== '' ? $stdout_tail : 'Belum ada output stdout.'); ?></pre>
                </div>
                <div class="cbt-maintenance-load-job-log-box">
                    <span class="cbt-maintenance-progress-activity-label">Stderr Tail</span>
                    <pre><?php echo esc_html($stderr_tail !== '' ? $stderr_tail : 'Belum ada output stderr.'); ?></pre>
                </div>
            </div>
        <?php endif; ?>

        <div class="cbt-maintenance-actions cbt-maintenance-actions--load-job" style="margin-top:14px;">
            <p class="cbt-maintenance-actions-copy">
                Workspace: <span class="cbt-maintenance-inline-code"><?php echo esc_html((string) ($job['workspace'] ?? '-')); ?></span>
            </p>
            <div class="cbt-maintenance-load-job-actions">
                <?php if (in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('cbt_cancel_load_test_' . (string) ($job['id'] ?? '')); ?>
                        <input type="hidden" name="action" value="cbt_cancel_load_test" />
                        <input type="hidden" name="cbt_maintenance_tab" value="load" />
                        <input type="hidden" name="job_id" value="<?php echo esc_attr((string) ($job['id'] ?? '')); ?>" />
                        <button type="submit" class="button button-secondary">Cancel Job</button>
                    </form>
                <?php endif; ?>

                <?php foreach ($artifacts as $artifact_key => $artifact): ?>
                    <?php if (!is_file((string) ($artifact['path'] ?? ''))) { continue; } ?>
                    <a
                        class="button button-secondary"
                        href="<?php echo esc_url(wp_nonce_url(
                            add_query_arg(
                                [
                                    'action' => 'cbt_download_load_test_artifact',
                                    'job_id' => (string) ($job['id'] ?? ''),
                                    'artifact' => (string) $artifact_key,
                                ],
                                admin_url('admin-post.php')
                            ),
                            'cbt_download_load_test_artifact_' . (string) ($job['id'] ?? '') . '_' . (string) $artifact_key
                        )); ?>"
                    >
                        <?php echo esc_html((string) ($artifact['label'] ?? $artifact_key)); ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!in_array((string) ($job['status'] ?? ''), ['queued', 'running'], true)): ?>
                    <form class="cbt-maintenance-load-job-delete" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Hapus hasil test ini? Histori dan artifact di workspace akan ikut dihapus.');">
                        <?php wp_nonce_field('cbt_delete_load_test_job_' . (string) ($job['id'] ?? '')); ?>
                        <input type="hidden" name="action" value="cbt_delete_load_test_job" />
                        <input type="hidden" name="cbt_maintenance_tab" value="load" />
                        <input type="hidden" name="job_id" value="<?php echo esc_attr((string) ($job['id'] ?? '')); ?>" />
                        <button type="submit" class="button button-secondary">Hapus Hasil</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</div>
