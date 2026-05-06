<?php

if (!defined('ABSPATH')) {
    exit;
}

$eligible_exams = isset($load_test_exam_catalog['eligible']) && is_array($load_test_exam_catalog['eligible'])
    ? (array) $load_test_exam_catalog['eligible']
    : [];
$invalid_exams = isset($load_test_exam_catalog['invalid']) && is_array($load_test_exam_catalog['invalid'])
    ? (array) $load_test_exam_catalog['invalid']
    : [];
$load_test_scenarios = isset($load_test_scenarios) && is_array($load_test_scenarios)
    ? (array) $load_test_scenarios
    : [];
$load_test_shapes = isset($load_test_shapes) && is_array($load_test_shapes)
    ? (array) $load_test_shapes
    : [];
$ajax_nonce = wp_create_nonce('cbt_load_test_jobs');
$first_exam_id = !empty($eligible_exams) ? (int) (($eligible_exams[0]['id'] ?? 0)) : 0;
$default_scenario_key = (string) ($load_test_default_profile['scenario_key'] ?? 'full_exam_finish_batch');
$default_scenario = isset($load_test_scenarios[$default_scenario_key]) && is_array($load_test_scenarios[$default_scenario_key])
    ? (array) $load_test_scenarios[$default_scenario_key]
    : [];
$default_shape_key = (string) ($load_test_default_profile['load_shape'] ?? 'flat_iterations');
$default_shape = isset($load_test_shapes[$default_shape_key]) && is_array($load_test_shapes[$default_shape_key])
    ? (array) $load_test_shapes[$default_shape_key]
    : [];
$is_default_ramping = $default_shape_key === 'ramping_vus';
$active_load_view = $load_test_running_count > 0 ? 'jobs' : 'runner';
?>
<div data-load-panel data-load-active-view="<?php echo esc_attr($active_load_view); ?>" data-load-global-token="<?php echo esc_attr((string) (($load_test_runtime['global_token_meta']['token'] ?? ''))); ?>">
    <section class="cbt-maintenance-card cbt-maintenance-card--load">
        <div class="cbt-maintenance-card-header">
            <div>
                <h2>Load Test Runner</h2>
                <p>Gunakan bulk students yang sudah ada untuk export dataset load test, lalu jalankan satu job <code>k6</code> per exam siswa secara paralel dari panel admin. Exam Bank Soal tidak pernah masuk katalog ini.</p>
                <p style="margin:6px 0 0;color:#64748b;">Export <code>CSV</code> dan <code>XLSX</code> sekarang ikut membawa kolom <code>jenis_kelamin</code>, sedangkan <code>JSON</code> tetap ringkas untuk runner <code>k6</code> dengan <code>identifier</code> dan <code>password</code> saja.</p>
            </div>
            <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($load_test_running_count > 0 ? 'running' : 'idle'); ?>">
                <?php echo esc_html($load_test_running_count > 0 ? $load_test_running_count . ' job aktif' : 'Siap dijalankan'); ?>
            </span>
        </div>

        <div class="cbt-maintenance-alert" style="border-color:#dbe5ef;background:#f8fbff;color:#1e3a8a;">
            <strong>Mode runner:</strong> background shell, reuse user yang sama di semua exam paralel, dan tidak memblokir run saat jumlah bulk students lebih kecil dari target VUs. Selector hanya menampilkan exam ujian siswa, bukan Bank Soal.
        </div>

        <div class="cbt-maintenance-load-view-tabs" role="tablist" aria-label="Load test runner views">
            <button
                type="button"
                class="cbt-maintenance-load-view-tab<?php echo $active_load_view === 'runner' ? ' is-active' : ''; ?>"
                id="cbt-maintenance-load-view-tab-runner"
                data-load-view-tab="runner"
                role="tab"
                aria-selected="<?php echo $active_load_view === 'runner' ? 'true' : 'false'; ?>"
                aria-controls="cbt-maintenance-load-view-panel-runner"
            >
                Runner
                <span>Preflight, exam, profile, dan command preview</span>
            </button>
            <button
                type="button"
                class="cbt-maintenance-load-view-tab<?php echo $active_load_view === 'jobs' ? ' is-active' : ''; ?>"
                id="cbt-maintenance-load-view-tab-jobs"
                data-load-view-tab="jobs"
                role="tab"
                aria-selected="<?php echo $active_load_view === 'jobs' ? 'true' : 'false'; ?>"
                aria-controls="cbt-maintenance-load-view-panel-jobs"
            >
                Jobs
                <span data-load-jobs-tab-badge><?php echo esc_html($load_test_running_count > 0 ? $load_test_running_count . ' running' : count($load_test_jobs) . ' total'); ?></span>
            </button>
        </div>

        <div
            class="cbt-maintenance-load-view-panel"
            id="cbt-maintenance-load-view-panel-runner"
            data-load-view-panel="runner"
            role="tabpanel"
            aria-labelledby="cbt-maintenance-load-view-tab-runner"
            <?php echo $active_load_view === 'runner' ? '' : 'hidden'; ?>
        >
        <div class="cbt-maintenance-load-section-grid">
            <section class="cbt-maintenance-load-section">
                <div class="cbt-maintenance-card-header" style="margin-bottom:14px;">
                    <div>
                        <h3 style="margin:0 0 6px;font-size:17px;">Preflight</h3>
                        <p style="margin:0;color:#64748b;">Cek shell runner, binary k6, token global, base URL, dan kesiapan bulk students sebelum start job.</p>
                    </div>
                    <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr(!empty($load_test_runtime['k6_path']) ? 'done' : 'danger'); ?>">
                        <?php echo esc_html(!empty($load_test_runtime['k6_path']) ? 'k6 Terdeteksi' : 'k6 Belum Ada'); ?>
                    </span>
                </div>

                <div class="cbt-maintenance-load-preflight-grid">
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Shell PHP</span>
                        <strong><?php echo esc_html(!empty($load_test_runtime['shell_available']) ? 'Available' : 'Unavailable'); ?></strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Binary k6</span>
                        <strong><?php echo esc_html((string) (($load_test_runtime['k6_path'] ?? '') !== '' ? $load_test_runtime['k6_path'] : 'Tidak ditemukan')); ?></strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Install Mode</span>
                        <strong><?php echo esc_html((string) ($load_test_runtime['k6_install_mode'] ?? 'missing')); ?></strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Runner HOME</span>
                        <strong>
                            <?php
                            echo esc_html(
                                (string) ($load_test_runtime['runner_home'] ?? '') !== ''
                                    ? (string) $load_test_runtime['runner_home']
                                    : ((string) ($load_test_runtime['runner_home_detected'] ?? '') !== '' ? (string) $load_test_runtime['runner_home_detected'] : '-')
                            );
                            ?>
                        </strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Bulk Students Ready</span>
                        <strong><?php echo esc_html((string) max(0, (int) ($load_test_student_pool['valid_count'] ?? 0))); ?></strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Tanpa Plain Password</span>
                        <strong><?php echo esc_html((string) max(0, (int) ($load_test_student_pool['missing_password_count'] ?? 0))); ?></strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Token Global Aktif</span>
                        <strong><?php echo esc_html((string) (((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? $load_test_runtime['global_token_meta']['token'] : '-')); ?></strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Refresh Token</span>
                        <strong><?php echo esc_html((string) number_format_i18n((int) (($load_test_runtime['global_token_meta']['refresh_minutes'] ?? 0))) . ' menit'); ?></strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Base URL Target</span>
                        <strong><?php echo esc_html($load_test_default_base_url); ?></strong>
                    </div>
                    <div class="cbt-maintenance-stat">
                        <span class="cbt-maintenance-stat-label">Runtime Uploads</span>
                        <strong><?php echo esc_html(!empty($load_test_runtime['runtime_root_writable']) ? 'Writable' : 'Perlu cek izin'); ?></strong>
                    </div>
                </div>

                <?php if ((string) ($load_test_runtime['k6_install_mode'] ?? '') === 'snap' && empty($load_test_runtime['runner_home_supported'])): ?>
                    <div class="cbt-maintenance-alert" style="margin-top:16px;border-color:#f4d6d6;background:#fff8f8;color:#9f1239;">
                        <strong>Snap warning:</strong> <code>k6</code> terdeteksi dari Snap, tetapi user PHP ini tidak punya <code>HOME</code> valid di bawah <code>/home</code>.
                        Runner admin akan gagal start sampai Anda menginstal <code>k6</code> versi native/non-snap atau mengonfigurasi Snap home di server.
                    </div>
                <?php elseif ((string) ($load_test_runtime['k6_install_mode'] ?? '') === 'snap'): ?>
                    <div class="cbt-maintenance-alert" style="margin-top:16px;border-color:#dbe5ef;background:#f8fbff;color:#1e3a8a;">
                        <strong>Catatan Snap:</strong> runner bisa mencoba memakai <code><?php echo esc_html((string) ($load_test_runtime['runner_home'] ?? '')); ?></code>, tetapi untuk stabilitas terbaik tetap disarankan menggunakan <code>k6</code> native/non-snap.
                    </div>
                <?php endif; ?>

                <?php if ((string) ($load_test_runtime['k6_install_mode'] ?? '') !== 'native'): ?>
                    <section class="cbt-maintenance-install-guide">
                        <h4>Install k6 native di Ubuntu</h4>
                        <p>Jika server ini masih mendeteksi <code>k6</code> dari Snap atau belum menemukan binary sama sekali, gunakan urutan install Ubuntu berikut yang sudah cocok untuk repo resmi <code>k6</code>, lalu refresh halaman ini.</p>
                        <pre><code>sudo apt-get update
sudo apt-get install -y gnupg ca-certificates

sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69

echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
apt-cache policy k6
sudo apt-get install -y k6
k6 version</code></pre>
                        <p style="margin-top:12px;">Panduan resmi: <a href="https://grafana.com/docs/k6/latest/set-up/install-k6/" target="_blank" rel="noopener noreferrer">grafana.com/docs/k6/latest/set-up/install-k6/</a></p>
                    </section>
                <?php endif; ?>
            </section>

            <section class="cbt-maintenance-load-section">
                <div class="cbt-maintenance-card-header" style="margin-bottom:14px;">
                    <div>
                        <h3 style="margin:0 0 6px;font-size:17px;">Exam Selection &amp; Profile</h3>
                        <p style="margin:0;color:#64748b;">Pilih exam aktif yang punya soal, tentukan load profile, lalu sistem akan membuat satu job k6 per exam yang dipilih.</p>
                    </div>
                    <span class="cbt-maintenance-chip cbt-maintenance-chip--running"><?php echo esc_html(count($eligible_exams) . ' exam siap'); ?></span>
                </div>

                <?php if (!empty($invalid_exams)): ?>
                    <div class="cbt-maintenance-alert" style="margin-bottom:16px;border-color:#f4d6d6;background:#fff8f8;color:#9f1239;">
                        <strong>Info:</strong> <?php echo esc_html(count($invalid_exams)); ?> exam disembunyikan dari selector karena belum published, jadwalnya belum aktif, atau belum punya soal aktif. Bank Soal juga tidak pernah ditampilkan di selector ini.
                    </div>
                <?php endif; ?>

                <form
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    class="cbt-maintenance-load-form"
                    data-load-test-form
                    data-maintenance-async-form
                    data-maintenance-loading-label="Memulai load test..."
                    data-load-profiles="<?php echo esc_attr(wp_json_encode($load_test_profile_presets)); ?>"
                    data-load-scenarios="<?php echo esc_attr(wp_json_encode($load_test_scenarios)); ?>"
                    data-load-shapes="<?php echo esc_attr(wp_json_encode($load_test_shapes)); ?>"
                    data-load-exams="<?php echo esc_attr(wp_json_encode(array_values($eligible_exams))); ?>"
                    data-load-k6-path="<?php echo esc_attr((string) ($load_test_runtime['k6_path'] ?? 'k6')); ?>"
                    data-load-ready-users="<?php echo esc_attr((string) max(0, (int) ($load_test_student_pool['valid_count'] ?? 0))); ?>"
                    onsubmit="return confirm('Mulai load test untuk semua exam yang dipilih? Satu exam akan dijalankan sebagai satu job k6 paralel.');"
                >
                    <?php wp_nonce_field('cbt_start_load_test'); ?>
                    <input type="hidden" name="action" value="cbt_start_load_test" />
                    <input type="hidden" name="cbt_maintenance_tab" value="load" data-maintenance-tab-input />

                    <?php if (!empty($eligible_exams)): ?>
                        <p class="cbt-maintenance-field-help" style="margin-top:0;">Pilih satu atau beberapa exam aktif dari dropdown berikut. Setiap exam yang dipilih akan dibuatkan satu job <code>k6</code> terpisah dan dijalankan paralel.</p>
                        <details class="cbt-maintenance-load-picker" data-load-exam-picker>
                            <summary>
                                <span class="cbt-maintenance-load-picker-summary">
                                    <span class="cbt-maintenance-load-picker-copy">
                                        <strong data-load-exam-picker-label>1 exam dipilih</strong>
                                        <span data-load-exam-picker-meta>Buka daftar exam aktif lalu centang exam yang ingin dijalankan.</span>
                                    </span>
                                    <span class="cbt-maintenance-load-picker-caret" aria-hidden="true"></span>
                                </span>
                            </summary>
                            <div class="cbt-maintenance-load-picker-menu">
                                <?php foreach ($eligible_exams as $exam_row): ?>
                                    <?php $exam_id = (int) ($exam_row['id'] ?? 0); ?>
                                    <label class="cbt-maintenance-load-picker-option">
                                        <input
                                            type="checkbox"
                                            name="exam_ids[]"
                                            value="<?php echo esc_attr((string) $exam_id); ?>"
                                            <?php checked($first_exam_id === $exam_id); ?>
                                            data-load-exam-checkbox
                                        />
                                        <span class="cbt-maintenance-load-exam-copy">
                                            <strong><?php echo esc_html((string) ($exam_row['title'] ?? 'Exam')); ?></strong>
                                            <span><?php echo esc_html((string) ($exam_row['subject_name'] ?? '')); ?> · <?php echo esc_html((string) ($exam_row['question_count'] ?? 0)); ?> soal · <?php echo esc_html((string) ($exam_row['duration_minutes'] ?? 0)); ?> menit · KKM <?php echo esc_html(number_format_i18n((float) ($exam_row['kkm_percentage'] ?? 75), 2)); ?>%</span>
                                            <span><?php echo esc_html((string) ($exam_row['schedule_label'] ?? 'Tanpa batas jadwal')); ?></span>
                                            <span>
                                                Target kelas:
                                                <?php
                                                echo esc_html(
                                                    !empty($exam_row['target_kelas_list'])
                                                        ? implode(', ', (array) $exam_row['target_kelas_list'])
                                                        : 'Semua kelas'
                                                );
                                                ?>
                                            </span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <div class="cbt-maintenance-load-selected-grid" data-load-selected-summary></div>
                    <?php else: ?>
                        <div class="cbt-maintenance-alert" style="margin-top:16px;">
                            <strong>Tidak ada exam siap.</strong> Publikasikan exam siswa, pastikan jadwalnya aktif, dan isi minimal satu soal aktif sebelum menjalankan load test. Bank Soal tidak dihitung sebagai target load test.
                        </div>
                    <?php endif; ?>

                    <div class="cbt-maintenance-field-grid cbt-maintenance-field-grid--load">
                        <div class="cbt-maintenance-field">
                            <label for="cbt-load-profile-preset">Preset profile</label>
                            <div class="cbt-maintenance-select-wrap">
                                <select id="cbt-load-profile-preset" name="profile_preset" data-load-profile-preset>
                                    <?php foreach ($load_test_profile_presets as $profile_key => $profile_meta): ?>
                                        <option value="<?php echo esc_attr((string) $profile_key); ?>" <?php selected((string) ($load_test_default_profile['profile_preset'] ?? ''), (string) $profile_key); ?>>
                                            <?php echo esc_html((string) ($profile_meta['label'] ?? $profile_key)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="cbt-maintenance-field-help">Paket setting cepat untuk load test. Saat preset diganti, nilai field di bawah akan ikut menyesuaikan, termasuk scenario dan load shape bawaan preset.</p>
                            <p class="cbt-maintenance-field-help" data-load-profile-description>
                                <?php echo esc_html((string) ($load_test_default_profile['profile_description'] ?? '')); ?>
                            </p>
                        </div>
                        <div class="cbt-maintenance-field">
                            <label for="cbt-load-base-url">Base URL target</label>
                            <input type="text" id="cbt-load-base-url" name="base_url" value="<?php echo esc_attr($load_test_default_base_url); ?>" />
                            <p class="cbt-maintenance-field-help">Alamat site yang ditembak runner <code>k6</code>. Gunakan URL yang benar-benar bisa diakses dari server ini.</p>
                        </div>
                    </div>

                    <div class="cbt-maintenance-field-grid cbt-maintenance-field-grid--load">
                        <div class="cbt-maintenance-field">
                            <label>Load Shape</label>
                            <input type="hidden" name="load_shape" value="<?php echo esc_attr($default_shape_key); ?>" data-load-profile-field="load_shape" />
                            <div class="cbt-maintenance-load-mode-card" data-load-shape-card>
                                <strong data-load-shape-label><?php echo esc_html((string) (($default_shape['label'] ?? $default_shape_key))); ?></strong>
                                <span class="cbt-maintenance-chip cbt-maintenance-chip--idle">Mengikuti preset</span>
                            </div>
                            <p class="cbt-maintenance-field-help cbt-maintenance-field-help--compact">
                                <span data-load-shape-description><?php echo esc_html((string) (($default_shape['description'] ?? 'Pilih bentuk trafik yang ingin dipakai runner.'))); ?></span>
                            </p>
                            <p class="cbt-maintenance-field-help" data-load-shape-meta>
                                <?php echo esc_html((string) (($default_shape['endpoint_hint'] ?? ''))); ?>
                            </p>
                        </div>
                        <div class="cbt-maintenance-field">
                            <label for="cbt-load-scenario">Scenario</label>
                            <div class="cbt-maintenance-select-wrap">
                                <select id="cbt-load-scenario" name="scenario_key" data-load-profile-field="scenario_key">
                                    <?php foreach ($load_test_scenarios as $scenario_key => $scenario_meta): ?>
                                        <option value="<?php echo esc_attr((string) $scenario_key); ?>" <?php selected($default_scenario_key, (string) $scenario_key); ?>>
                                            <?php echo esc_html((string) (($scenario_meta['label'] ?? $scenario_key))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="cbt-maintenance-field-help cbt-maintenance-field-help--compact">
                                <strong data-load-scenario-label><?php echo esc_html((string) (($default_scenario['label'] ?? $default_scenario_key))); ?></strong>
                                <span data-load-scenario-description><?php echo esc_html((string) (($default_scenario['description'] ?? 'Pilih alur load test yang ingin dijalankan.'))); ?></span>
                            </p>
                            <p class="cbt-maintenance-field-help" data-load-scenario-meta>
                                <?php echo esc_html((string) (($default_scenario['endpoint_summary'] ?? ''))); ?>
                            </p>
                        </div>
                        <div class="cbt-maintenance-field" data-load-flat-field <?php echo $is_default_ramping ? 'hidden' : ''; ?>>
                            <label for="cbt-load-vus">VUs</label>
                            <input type="number" min="1" max="5000" id="cbt-load-vus" name="vus" value="<?php echo esc_attr((string) ($load_test_default_profile['vus'] ?? 50)); ?>" data-load-profile-field="vus" <?php echo $is_default_ramping ? 'disabled' : ''; ?> />
                            <p class="cbt-maintenance-field-help">Jumlah virtual user yang jalan paralel. Dipakai penuh pada mode <code>flat_iterations</code>.</p>
                        </div>
                        <div class="cbt-maintenance-field" data-load-ramping-field <?php echo !$is_default_ramping ? 'hidden' : ''; ?>>
                            <label for="cbt-load-peak-vus">Peak VUs</label>
                            <input type="number" min="1" max="5000" id="cbt-load-peak-vus" name="peak_vus" value="<?php echo esc_attr((string) ($load_test_default_profile['peak_vus'] ?? $load_test_default_profile['vus'] ?? 50)); ?>" data-load-profile-field="peak_vus" <?php echo !$is_default_ramping ? 'disabled' : ''; ?> />
                            <p class="cbt-maintenance-field-help">Target concurrency tertinggi saat runner mencapai fase steady state.</p>
                        </div>
                        <div class="cbt-maintenance-field" data-load-iterations-field>
                            <label for="cbt-load-iterations">Iterations</label>
                            <input type="number" min="1" max="100" id="cbt-load-iterations" name="iterations" value="<?php echo esc_attr((string) ($load_test_default_profile['iterations'] ?? 1)); ?>" data-load-profile-field="iterations" />
                            <p class="cbt-maintenance-field-help" data-load-iterations-help>Berapa kali satu skenario ujian dijalankan per virtual user. Nilai lebih tinggi berarti total request ikut bertambah.</p>
                        </div>
                        <div class="cbt-maintenance-field">
                            <label for="cbt-load-questions-per-user">Questions per user</label>
                            <input type="number" min="0" max="500" id="cbt-load-questions-per-user" name="questions_per_user" value="<?php echo esc_attr((string) ($load_test_default_profile['questions_per_user'] ?? 0)); ?>" data-load-profile-field="questions_per_user" />
                            <div class="cbt-maintenance-load-quick-options" data-load-question-options></div>
                            <p class="cbt-maintenance-field-help" data-load-question-help><code>0</code> berarti semua soal exam akan dipakai. Pilih exam dulu untuk melihat saran jumlah soal berdasarkan exam yang dipilih.</p>
                        </div>
                        <div class="cbt-maintenance-field" data-load-ramping-field <?php echo !$is_default_ramping ? 'hidden' : ''; ?>>
                            <label for="cbt-load-warmup-duration">Warmup</label>
                            <input type="text" id="cbt-load-warmup-duration" name="warmup_duration" value="<?php echo esc_attr((string) ($load_test_default_profile['warmup_duration'] ?? '1m')); ?>" data-load-profile-field="warmup_duration" <?php echo !$is_default_ramping ? 'disabled' : ''; ?> />
                            <p class="cbt-maintenance-field-help">Durasi pemanasan awal. Format: <code>30s</code>, <code>1m</code>, atau <code>1h</code>.</p>
                        </div>
                        <div class="cbt-maintenance-field" data-load-ramping-field <?php echo !$is_default_ramping ? 'hidden' : ''; ?>>
                            <label for="cbt-load-ramp-up-duration">Ramp-up</label>
                            <input type="text" id="cbt-load-ramp-up-duration" name="ramp_up_duration" value="<?php echo esc_attr((string) ($load_test_default_profile['ramp_up_duration'] ?? '2m')); ?>" data-load-profile-field="ramp_up_duration" <?php echo !$is_default_ramping ? 'disabled' : ''; ?> />
                            <p class="cbt-maintenance-field-help">Durasi kenaikan concurrency sampai mencapai <code>Peak VUs</code>.</p>
                        </div>
                        <div class="cbt-maintenance-field" data-load-ramping-field <?php echo !$is_default_ramping ? 'hidden' : ''; ?>>
                            <label for="cbt-load-steady-duration">Steady</label>
                            <input type="text" id="cbt-load-steady-duration" name="steady_duration" value="<?php echo esc_attr((string) ($load_test_default_profile['steady_duration'] ?? '5m')); ?>" data-load-profile-field="steady_duration" <?php echo !$is_default_ramping ? 'disabled' : ''; ?> />
                            <p class="cbt-maintenance-field-help">Durasi saat runner menahan beban di peak concurrency. Nilainya wajib lebih dari <code>0s</code>.</p>
                        </div>
                        <div class="cbt-maintenance-field" data-load-ramping-field <?php echo !$is_default_ramping ? 'hidden' : ''; ?>>
                            <label for="cbt-load-ramp-down-duration">Ramp-down</label>
                            <input type="text" id="cbt-load-ramp-down-duration" name="ramp_down_duration" value="<?php echo esc_attr((string) ($load_test_default_profile['ramp_down_duration'] ?? '1m')); ?>" data-load-profile-field="ramp_down_duration" <?php echo !$is_default_ramping ? 'disabled' : ''; ?> />
                            <p class="cbt-maintenance-field-help">Durasi saat concurrency diturunkan sampai runner selesai.</p>
                        </div>
                        <div class="cbt-maintenance-field" data-load-ramping-field <?php echo !$is_default_ramping ? 'hidden' : ''; ?>>
                            <label for="cbt-load-ramp-steps">Ramp Steps</label>
                            <input type="number" min="1" max="12" id="cbt-load-ramp-steps" name="ramp_steps" value="<?php echo esc_attr((string) ($load_test_default_profile['ramp_steps'] ?? 2)); ?>" data-load-profile-field="ramp_steps" <?php echo !$is_default_ramping ? 'disabled' : ''; ?> />
                            <p class="cbt-maintenance-field-help">Jumlah stage bertahap saat naik menuju peak concurrency.</p>
                        </div>
                        <div class="cbt-maintenance-field">
                            <label for="cbt-load-session-spread">Session start spread (ms)</label>
                            <input type="number" min="0" max="600000" id="cbt-load-session-spread" name="session_start_spread_ms" value="<?php echo esc_attr((string) ($load_test_default_profile['session_start_spread_ms'] ?? 0)); ?>" data-load-profile-field="session_start_spread_ms" />
                            <p class="cbt-maintenance-field-help" data-load-session-spread-help>Jeda penyebaran saat mulai sesi user, supaya login dan start attempt tidak menumpuk di milidetik yang sama.</p>
                        </div>
                        <div class="cbt-maintenance-field">
                            <label for="cbt-load-post-spread">Post start spread (ms)</label>
                            <input type="number" min="0" max="600000" id="cbt-load-post-spread" name="post_start_spread_ms" value="<?php echo esc_attr((string) ($load_test_default_profile['post_start_spread_ms'] ?? 0)); ?>" data-load-profile-field="post_start_spread_ms" />
                            <p class="cbt-maintenance-field-help" data-load-post-spread-help>Jeda tambahan setelah <code>start_attempt</code> berhasil, sebelum runner mulai request daftar soal exam.</p>
                        </div>
                        <div class="cbt-maintenance-field">
                            <label for="cbt-load-manual-token">Manual token override</label>
                            <input
                                type="text"
                                id="cbt-load-manual-token"
                                name="manual_exam_token"
                                value=""
                                placeholder="<?php echo esc_attr(((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? ('Kosongkan untuk pakai token global ' . (string) ($load_test_runtime['global_token_meta']['token'] ?? '')) : 'Isi token manual jika token global belum aktif'); ?>"
                            />
                            <div class="cbt-maintenance-load-token-state">
                                <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr(((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? 'done' : 'danger'); ?>" data-load-token-source-chip>
                                    <?php echo esc_html(((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? 'Global aktif' : 'Token belum ada'); ?>
                                </span>
                                <span class="cbt-maintenance-inline-code" data-load-token-value>
                                    <?php echo esc_html(((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== '' ? (string) ($load_test_runtime['global_token_meta']['token'] ?? '') : '-'); ?>
                                </span>
                            </div>
                            <p class="cbt-maintenance-field-help" data-load-token-help>
                                <?php if (((string) ($load_test_runtime['global_token_meta']['token'] ?? '')) !== ''): ?>
                                    Token global aktif akan dipakai otomatis jika field override ini dikosongkan.
                                <?php else: ?>
                                    Belum ada token global aktif. Isi manual token override jika run ini tetap harus memakai token exam tertentu.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="cbt-maintenance-load-selected-grid" style="margin-top:12px;">
                        <article class="cbt-maintenance-load-selected-card">
                            <div class="cbt-maintenance-load-exam-copy">
                                <strong>Shape Summary</strong>
                                <span data-load-stage-summary><?php echo esc_html((string) ($load_test_default_profile['stage_summary'] ?? '-')); ?></span>
                            </div>
                        </article>
                        <article class="cbt-maintenance-load-selected-card">
                            <div class="cbt-maintenance-load-exam-copy">
                                <strong>Effective Concurrency</strong>
                                <span data-load-concurrency-label><?php echo esc_html((string) max(0, (int) ($load_test_default_profile['effective_vus'] ?? $load_test_default_profile['vus'] ?? 0))); ?> user</span>
                            </div>
                        </article>
                        <article class="cbt-maintenance-load-selected-card">
                            <div class="cbt-maintenance-load-exam-copy">
                                <strong>Estimasi Durasi</strong>
                                <span data-load-duration-label><?php echo esc_html((string) ($load_test_default_profile['estimated_duration_label'] ?? $load_test_default_profile['max_duration'] ?? '-')); ?></span>
                            </div>
                        </article>
                    </div>

                    <div class="cbt-maintenance-load-preview" style="margin-top:18px;">
                        <div class="cbt-maintenance-card-header" style="margin-bottom:12px;">
                            <div>
                                <h3 style="margin:0 0 6px;font-size:16px;">Command Preview</h3>
                                <p style="margin:0;color:#64748b;">Command final per exam tetap ditampilkan supaya bisa dipakai manual jika runner background perlu dibandingkan.</p>
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;">
                                <span class="cbt-maintenance-chip cbt-maintenance-chip--idle" data-load-concurrency-chip>
                                    <?php echo esc_html(((string) ($load_test_default_profile['load_shape_label'] ?? 'Flat')) . ' ' . (string) max(0, (int) ($load_test_default_profile['effective_vus'] ?? $load_test_default_profile['vus'] ?? 0))); ?>
                                </span>
                                <span class="cbt-maintenance-chip cbt-maintenance-chip--idle" data-load-duration-chip>
                                    <?php echo esc_html((string) ($load_test_default_profile['estimated_duration_label'] ?? $load_test_default_profile['max_duration'] ?? '-')); ?>
                                </span>
                                <span class="cbt-maintenance-chip cbt-maintenance-chip--idle" data-load-user-warning>
                                    <?php echo esc_html((int) ($load_test_student_pool['valid_count'] ?? 0) < (int) ($load_test_default_profile['effective_vus'] ?? $load_test_default_profile['vus'] ?? 0) ? 'User akan di-reuse' : 'User cukup'); ?>
                                </span>
                            </div>
                        </div>
                        <pre data-load-command-preview>Belum ada exam dipilih.</pre>
                    </div>

                    <div class="cbt-maintenance-actions cbt-maintenance-actions--load-primary" style="margin-top:18px;">
                        <p class="cbt-maintenance-actions-copy">Gunakan <code>Refresh Status</code> untuk memuat ulang daftar job, atau biarkan panel melakukan polling otomatis selama masih ada job aktif.</p>
                        <div class="cbt-maintenance-load-job-actions">
                            <button type="submit" class="button button-primary button-large cbt-admin-btn--warning" <?php disabled(empty($eligible_exams)); ?>>Start Load Test</button>
                            <button type="button" class="button button-secondary button-large" data-load-refresh-jobs>Refresh Status</button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
        </div>

        <section
            class="cbt-maintenance-load-section cbt-maintenance-load-view-panel"
            id="cbt-maintenance-load-view-panel-jobs"
            data-load-view-panel="jobs"
            role="tabpanel"
            aria-labelledby="cbt-maintenance-load-view-tab-jobs"
            <?php echo $active_load_view === 'jobs' ? '' : 'hidden'; ?>
        >
            <div class="cbt-maintenance-card-header" style="margin-bottom:14px;">
                <div>
                    <h3 style="margin:0 0 6px;font-size:17px;">Jobs</h3>
                    <p style="margin:0;color:#64748b;">Daftar job k6 aktif dan histori terbaru. Status akan disinkronkan dari PID, exit code, log file, dan summary export.</p>
                </div>
                <span class="cbt-maintenance-chip cbt-maintenance-chip--<?php echo esc_attr($load_test_running_count > 0 ? 'running' : 'idle'); ?>" data-load-running-chip>
                    <?php echo esc_html($load_test_running_count > 0 ? $load_test_running_count . ' running' : count($load_test_jobs) . ' total'); ?>
                </span>
            </div>
            <div
                class="cbt-maintenance-load-jobs-wrap"
                data-load-jobs-wrap
                data-load-jobs-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php?action=cbt_load_test_jobs')); ?>"
                data-load-jobs-ajax-nonce="<?php echo esc_attr($ajax_nonce); ?>"
                data-load-running-count="<?php echo esc_attr((string) $load_test_running_count); ?>"
            >
                <?php echo $load_test_jobs_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </section>
    </section>
</div>
