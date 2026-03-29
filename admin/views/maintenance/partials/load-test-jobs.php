<?php

if (!defined('ABSPATH')) {
    exit;
}

$job_options = isset($job_options) && is_array($job_options) ? array_values($job_options) : [];
$job_cards = isset($job_cards) && is_array($job_cards) ? array_values($job_cards) : [];
$selected_job_id = isset($selected_job_id) ? (string) $selected_job_id : '';
$running_job_count = isset($running_job_count) ? (int) $running_job_count : 0;

if (empty($job_cards)) {
    ?>
    <div class="cbt-maintenance-alert" style="border-color:#dbe5ef;background:#f8fbff;color:#1e3a8a;">
        <strong>Belum ada job.</strong> Pilih exam aktif lalu tekan <code>Start Load Test</code> untuk membuat runner k6 pertama.
    </div>
    <?php
    return;
}

if ($selected_job_id === '') {
    $first_job = isset($job_cards[0]['job']) && is_array($job_cards[0]['job']) ? (array) $job_cards[0]['job'] : [];
    $selected_job_id = (string) ($first_job['id'] ?? '');
}
?>
<div class="cbt-maintenance-load-jobs-toolbar">
    <div class="cbt-maintenance-field">
        <label for="cbt-load-job-selector">Pilih hasil test</label>
        <div class="cbt-maintenance-select-wrap">
            <select id="cbt-load-job-selector" data-load-job-selector>
                <?php foreach ($job_options as $index => $job_option): ?>
                    <option value="<?php echo esc_attr((string) ($job_option['id'] ?? '')); ?>" <?php selected(($selected_job_id !== '' ? (string) ($job_option['id'] ?? '') === $selected_job_id : $index === 0)); ?>>
                        <?php echo esc_html((string) ($job_option['label'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="cbt-maintenance-field-help">Urutan terbaru ada di paling atas. Label menampilkan exam, status, dan waktu mulai test agar mudah memilih hasil terakhir.</p>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:flex-end;">
        <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($running_job_count > 0 ? 'running' : 'idle'); ?>">
            <?php echo esc_html(count($job_cards) . ' histori'); ?>
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
<?php foreach ($job_cards as $job_card): ?>
    <?php
    $job = isset($job_card['job']) && is_array($job_card['job']) ? (array) $job_card['job'] : [];
    $status_meta = isset($job_card['status_meta']) && is_array($job_card['status_meta']) ? (array) $job_card['status_meta'] : ['tone' => 'idle', 'label' => 'Idle'];
    $summary = isset($job_card['summary']) && is_array($job_card['summary']) ? (array) $job_card['summary'] : [];
    $stdout_tail = isset($job_card['stdout_tail']) ? (string) $job_card['stdout_tail'] : '';
    $stderr_tail = isset($job_card['stderr_tail']) ? (string) $job_card['stderr_tail'] : '';
    $artifacts = isset($job_card['artifacts']) && is_array($job_card['artifacts']) ? (array) $job_card['artifacts'] : [];
    $run_started = isset($job_card['run_started']) ? (string) $job_card['run_started'] : '-';
    $run_finished = isset($job_card['run_finished']) ? (string) $job_card['run_finished'] : '-';
    $scenario_label = sanitize_text_field((string) (($job['profile']['scenario_label'] ?? '')));
    $load_shape = sanitize_key((string) (($job['profile']['load_shape'] ?? 'flat_iterations')));
    $load_shape_label = sanitize_text_field((string) (($job['profile']['load_shape_label'] ?? '')));
    $stage_summary_label = sanitize_text_field((string) (($job['profile']['stage_summary'] ?? '')));
    $effective_vus = max(0, (int) (($job['profile']['effective_vus'] ?? ($load_shape === 'ramping_vus' ? ($job['profile']['peak_vus'] ?? 0) : ($job['profile']['vus'] ?? 0)))));
    $reads_questions = !empty($job['profile']['scenario_reads_questions']);
    $stage_summary = [
        'Login' => $summary['login_success_rate'] ?? null,
        'Exams' => $summary['get_exams_success_rate'] ?? null,
        'Start' => $summary['start_attempt_success_rate'] ?? null,
        'Questions' => $summary['get_questions_success_rate'] ?? null,
        'Submit 1x1' => $summary['submit_single_success_rate'] ?? null,
        'Submit Batch' => $summary['submit_batch_success_rate'] ?? null,
        'Finish' => $summary['finish_exam_success_rate'] ?? null,
    ];
    ?>
    <article
        class="cbt-maintenance-load-job-card"
        data-load-job-card
        data-load-job-id="<?php echo esc_attr((string) ($job['id'] ?? '')); ?>"
        <?php echo (string) ($job['id'] ?? '') !== $selected_job_id ? ' hidden' : ''; ?>
    >
        <div class="cbt-maintenance-card-header" style="margin-bottom:12px;">
            <div>
                <h3 style="margin:0 0 6px;font-size:17px;"><?php echo esc_html((string) ($job['exam_title'] ?? 'Exam')); ?></h3>
                <p style="margin:0;color:#64748b;">
                    <?php echo esc_html((string) ($job['subject_name'] ?? '')); ?>
                    <?php if (!empty($job['profile']['profile_label'])): ?>
                        · <?php echo esc_html((string) $job['profile']['profile_label']); ?>
                    <?php endif; ?>
                    <?php if ($scenario_label !== ''): ?>
                        · <?php echo esc_html($scenario_label); ?>
                    <?php endif; ?>
                    <?php if ($load_shape_label !== ''): ?>
                        · <?php echo esc_html($load_shape_label . ' ' . $effective_vus); ?>
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
                <span class="cbt-maintenance-stat-label"><?php echo esc_html($load_shape === 'ramping_vus' ? 'Users / Peak VUs' : 'Users / VUs'); ?></span>
                <strong>
                    <?php
                    echo esc_html(
                        (string) max(0, (int) ($job['student_count'] ?? 0))
                        . ' / '
                        . (string) $effective_vus
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
                <span class="cbt-maintenance-stat-label">Load Shape</span>
                <strong><?php echo esc_html($load_shape_label !== '' ? $load_shape_label : '-'); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Q/User</span>
                <strong>
                    <?php
                    if (!$reads_questions) {
                        echo 'Ignored';
                    } else {
                        echo esc_html((string) max(0, (int) ($job['profile']['questions_per_user'] ?? 0)));
                    }
                    ?>
                </strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Scenario</span>
                <strong><?php echo esc_html($scenario_label !== '' ? $scenario_label : '-'); ?></strong>
            </div>
            <div class="cbt-maintenance-stat">
                <span class="cbt-maintenance-stat-label">Stage Summary</span>
                <strong><?php echo esc_html($stage_summary_label !== '' ? $stage_summary_label : '-'); ?></strong>
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

        <?php $visible_stage_summary = array_filter($stage_summary, static fn($value) => $value !== null); ?>
        <?php if (!empty($visible_stage_summary)): ?>
            <div class="cbt-maintenance-load-selected-grid" style="margin-top:12px;">
                <?php foreach ($visible_stage_summary as $stage_label => $stage_rate): ?>
                    <article class="cbt-maintenance-load-selected-card">
                        <div class="cbt-maintenance-load-exam-copy">
                            <strong><?php echo esc_html($stage_label); ?></strong>
                            <span>Stage success rate</span>
                            <span><?php echo esc_html(number_format_i18n(((float) $stage_rate) * 100, 2) . '%'); ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
