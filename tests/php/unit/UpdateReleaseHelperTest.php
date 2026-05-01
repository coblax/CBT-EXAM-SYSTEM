<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class UpdateReleaseHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-update-release-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-update-service.php';
    }

    public function test_normalize_manifest_uses_defaults_for_missing_download_url(): void
    {
        $manifest = \CBT_Update_Release_Helper::normalize_manifest([
            'version' => '1.8.2',
            'sha256' => str_repeat('a', 64),
            'requires_php' => '8.0',
            'requires_wp' => '6.0',
            'tested_up_to' => '6.8.1',
            'changelog' => ['Perubahan 1', 'Perubahan 2'],
        ], [
            'download_url' => 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/v1.8.2/cbt-exam-system.zip',
            'tag' => 'v1.8.2',
            'published_at' => '2026-03-29T12:00:00Z',
        ]);

        self::assertIsArray($manifest);
        self::assertSame('1.8.2', $manifest['version']);
        self::assertSame('v1.8.2', $manifest['tag']);
        self::assertSame('https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/v1.8.2/cbt-exam-system.zip', $manifest['download_url']);
        self::assertSame("Perubahan 1\nPerubahan 2", $manifest['changelog']);
    }

    public function test_normalize_manifest_rejects_missing_version(): void
    {
        $manifest = \CBT_Update_Release_Helper::normalize_manifest([
            'sha256' => str_repeat('a', 64),
        ]);

        self::assertInstanceOf(\WP_Error::class, $manifest);
        self::assertSame('invalid_manifest_version', $manifest->get_error_code());
    }

    public function test_fetch_latest_release_state_marks_available_when_release_is_valid(): void
    {
        $currentVersion = \CBT_Update_Release_Helper::current_version();
        $remoteVersion = $this->nextPatchVersion($currentVersion !== '' ? $currentVersion : '1.0.0');
        $remoteTag = 'v' . $remoteVersion;
        $releaseUrl = \CBT_Update_Release_Helper::latest_release_api_url();
        $manifestUrl = 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/' . $remoteTag . '/cbt-update-manifest.json';
        $packageUrl = 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/' . $remoteTag . '/cbt-exam-system.zip';

        $GLOBALS['cbt_test_wp_remote_get_map'][$releaseUrl] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'tag_name' => $remoteTag,
                'name' => 'CBT ' . $remoteVersion,
                'html_url' => 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/tag/' . $remoteTag,
                'published_at' => '2026-03-29T12:00:00Z',
                'assets' => [
                    [
                        'name' => \CBT_Update_Release_Helper::manifest_asset_name(),
                        'browser_download_url' => $manifestUrl,
                    ],
                    [
                        'name' => \CBT_Update_Release_Helper::package_asset_name(),
                        'browser_download_url' => $packageUrl,
                    ],
                ],
            ]),
        ];
        $GLOBALS['cbt_test_wp_remote_get_map'][$manifestUrl] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'version' => $remoteVersion,
                'tag' => $remoteTag,
                'published_at' => '2026-03-29T12:00:00Z',
                'download_url' => $packageUrl,
                'sha256' => str_repeat('a', 64),
                'requires_php' => '8.0',
                'requires_wp' => '6.0',
                'tested_up_to' => '6.8.1',
                'changelog' => "Fix updater\nRefine import diagnostics",
            ]),
        ];

        $state = \CBT_Update_Release_Helper::fetch_latest_release_state();

        self::assertSame('available', $state['status']);
        self::assertSame($remoteVersion, $state['manifest']['version']);
        self::assertSame('ok', $state['preflight']['status']);
        self::assertSame($remoteTag, $state['release']['tag']);
        self::assertSame(
            'Checksum, versi bootstrap, dan struktur zip akan diverifikasi saat INSTALL UPDATE.',
            $state['preflight']['items'][5]['message']
        );
    }

    public function test_fetch_latest_release_state_marks_no_release_when_repository_has_no_releases(): void
    {
        $releaseUrl = \CBT_Update_Release_Helper::latest_release_api_url();
        $releasesListUrl = \CBT_Update_Release_Helper::releases_api_url();

        $GLOBALS['cbt_test_wp_remote_get_map'][$releaseUrl] = [
            'response' => ['code' => 404],
            'body' => wp_json_encode([
                'message' => 'Not Found',
            ]),
        ];
        $GLOBALS['cbt_test_wp_remote_get_map'][$releasesListUrl] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([]),
        ];

        $state = \CBT_Update_Release_Helper::fetch_latest_release_state();

        self::assertSame('no_release', $state['status']);
        self::assertSame(\CBT_Update_Release_Helper::releases_html_url(), $state['release']['html_url']);
        self::assertStringContainsString('Belum ada GitHub Release resmi', $state['error_message']);
    }

    public function test_build_preflight_marks_blocked_when_checksum_missing(): void
    {
        $preflight = \CBT_Update_Release_Helper::build_preflight([
            'version' => '1.8.2',
            'requires_php' => '8.0',
            'requires_wp' => '6.0',
            'tested_up_to' => '6.8.1',
            'download_url' => 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/v1.8.2/cbt-exam-system.zip',
            'sha256' => '',
        ]);

        self::assertSame('blocked', $preflight['status']);
        self::assertSame('blocked', $preflight['items'][2]['status']);
    }

    public function test_build_preflight_blocks_unofficial_package_url(): void
    {
        $preflight = \CBT_Update_Release_Helper::build_preflight([
            'version' => '1.8.2',
            'requires_php' => '8.0',
            'requires_wp' => '6.0',
            'tested_up_to' => '6.8.1',
            'download_url' => 'https://example.com/cbt-exam-system.zip',
            'sha256' => str_repeat('a', 64),
        ]);

        self::assertSame('blocked', $preflight['status']);
        self::assertSame('blocked', $preflight['items'][2]['status']);
        self::assertStringContainsString('GitHub Release resmi', $preflight['items'][2]['message']);
    }

    public function test_validate_downloaded_package_rejects_checksum_mismatch(): void
    {
        [$zipPath] = $this->createReleaseZip('cbt-exam-system/');

        try {
            $result = \CBT_Update_Release_Helper::validate_downloaded_package($zipPath, str_repeat('b', 64));

            self::assertInstanceOf(\WP_Error::class, $result);
            self::assertSame('checksum_mismatch', $result->get_error_code());
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_validate_zip_package_structure_rejects_invalid_root(): void
    {
        [$zipPath] = $this->createReleaseZip('wrong-root/');

        try {
            $result = \CBT_Update_Release_Helper::validate_zip_package_structure($zipPath);

            self::assertInstanceOf(\WP_Error::class, $result);
            self::assertSame('invalid_package_root', $result->get_error_code());
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_validate_zip_package_structure_rejects_path_traversal(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive extension is required for updater tests.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'cbt-release-');
        self::assertIsString($zipPath);

        $archive = new \ZipArchive();
        self::assertTrue($archive->open($zipPath, \ZipArchive::OVERWRITE) === true);
        $archive->addFromString('cbt-exam-system/../evil.php', "<?php\n");
        $archive->addFromString('cbt-exam-system/cbt-exam-system.php', "<?php\n/**\n * Plugin Name: CBT Exam System\n */\n");
        $archive->close();

        try {
            $result = \CBT_Update_Release_Helper::validate_zip_package_structure($zipPath);

            self::assertInstanceOf(\WP_Error::class, $result);
            self::assertSame('invalid_package_path', $result->get_error_code());
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_validate_zip_package_structure_rejects_bootstrap_version_mismatch(): void
    {
        [$zipPath] = $this->createReleaseZip('cbt-exam-system/', '1.8.2');

        try {
            $result = \CBT_Update_Release_Helper::validate_zip_package_structure($zipPath, '1.8.3');

            self::assertInstanceOf(\WP_Error::class, $result);
            self::assertSame('package_version_mismatch', $result->get_error_code());
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_build_update_response_object_targets_plugin_basename(): void
    {
        $response = \CBT_Update_Release_Helper::build_update_response_object([
            'version' => '1.8.2',
            'requires_wp' => '6.0',
            'requires_php' => '8.0',
            'tested_up_to' => '6.8.1',
        ], '/tmp/cbt-exam-system.zip');

        self::assertSame('cbt-exam-system/cbt-exam-system.php', $response->plugin);
        self::assertSame('1.8.2', $response->new_version);
        self::assertSame('/tmp/cbt-exam-system.zip', $response->package);
    }

    public function test_validate_install_ready_rejects_remote_version_that_is_not_newer(): void
    {
        $result = \CBT_Update_Release_Helper::validate_install_ready([
            'status' => 'up_to_date',
            'manifest' => [
                'version' => CBT_EXAM_SYSTEM_VERSION,
            ],
            'preflight' => [
                'status' => 'ok',
            ],
        ]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('not_newer', $result->get_error_code());
    }

    public function test_build_page_context_maps_available_state_to_installable_view(): void
    {
        $remoteVersion = $this->nextPatchVersion(\CBT_Update_Release_Helper::current_version());
        $remoteTag = 'v' . $remoteVersion;
        set_transient(\CBT_Update_Release_Helper::release_state_transient(), [
            'checked_at' => 1774353600,
            'status' => 'available',
            'error_message' => '',
            'manifest' => [
                'version' => $remoteVersion,
                'tag' => $remoteTag,
                'published_at' => '2026-03-29T12:00:00Z',
                'download_url' => 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/' . $remoteTag . '/cbt-exam-system.zip',
                'sha256' => str_repeat('a', 64),
                'requires_php' => '8.0',
                'requires_wp' => '6.0',
                'tested_up_to' => '6.8.1',
                'changelog' => 'Fix updater',
            ],
            'release' => [
                'tag' => $remoteTag,
                'name' => 'CBT ' . $remoteVersion,
                'html_url' => 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/tag/' . $remoteTag,
                'published_at' => '2026-03-29T12:00:00Z',
            ],
            'preflight' => [
                'status' => 'ok',
                'items' => [],
                'has_blocked' => false,
            ],
        ], HOUR_IN_SECONDS);

        $context = \CBT_Admin_Update_Service::build_page_context([]);

        self::assertSame('available', $context['status']);
        self::assertTrue($context['has_update']);
        self::assertTrue($context['can_install']);
        self::assertSame('Update Available', $context['status_meta']['label']);
    }

    public function test_build_page_context_keeps_up_to_date_state_non_installable(): void
    {
        $currentVersion = \CBT_Update_Release_Helper::current_version();
        set_transient(\CBT_Update_Release_Helper::release_state_transient(), [
            'checked_at' => 1774353600,
            'status' => 'up_to_date',
            'error_message' => '',
            'manifest' => [
                'version' => $currentVersion,
                'tag' => 'v' . $currentVersion,
                'published_at' => '2026-03-29T12:00:00Z',
                'download_url' => 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/v' . $currentVersion . '/cbt-exam-system.zip',
                'sha256' => str_repeat('a', 64),
                'requires_php' => '8.0',
                'requires_wp' => '6.0',
                'tested_up_to' => '6.8.1',
                'changelog' => 'Already current',
            ],
            'release' => [
                'tag' => 'v' . $currentVersion,
                'name' => 'CBT ' . $currentVersion,
                'html_url' => 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/tag/v' . $currentVersion,
                'published_at' => '2026-03-29T12:00:00Z',
            ],
            'preflight' => [
                'status' => 'ok',
                'items' => [],
                'has_blocked' => false,
            ],
        ], HOUR_IN_SECONDS);

        $context = \CBT_Admin_Update_Service::build_page_context([]);

        self::assertSame('up_to_date', $context['status']);
        self::assertFalse($context['has_update']);
        self::assertFalse($context['can_install']);
        self::assertSame('Up to Date', $context['status_meta']['label']);
    }

    public function test_start_install_creates_lightweight_job_without_downloading_package(): void
    {
        $remoteVersion = $this->nextPatchVersion(\CBT_Update_Release_Helper::current_version());
        $remoteTag = 'v' . $remoteVersion;
        $packageUrl = 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/' . $remoteTag . '/cbt-exam-system.zip';
        $this->seedReleaseRemote($remoteVersion, $remoteTag, $packageUrl);

        $job = \CBT_Update_Job_Service::start_install('unit_test');

        self::assertIsArray($job);
        self::assertSame('install', $job['type']);
        self::assertSame('running', $job['status']);
        self::assertSame('download', $job['stage']);
        self::assertSame('', $job['package_path']);
        self::assertSame($remoteVersion, $job['target_version']);
        self::assertSame([], $GLOBALS['cbt_test_download_url_map']);
    }

    public function test_check_job_tick_completes_and_writes_history(): void
    {
        $remoteVersion = $this->nextPatchVersion(\CBT_Update_Release_Helper::current_version());
        $remoteTag = 'v' . $remoteVersion;
        $packageUrl = 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/' . $remoteTag . '/cbt-exam-system.zip';
        $this->seedReleaseRemote($remoteVersion, $remoteTag, $packageUrl);

        $job = \CBT_Update_Job_Service::start_check('unit_test');
        $ticked = \CBT_Update_Job_Service::tick((string) $job['token']);

        self::assertSame('completed', $ticked['status']);
        self::assertSame(100, $ticked['progress_percent']);
        self::assertNotEmpty(\CBT_Update_Job_Service::get_history());
    }

    public function test_backup_service_creates_protected_zip_with_plugin_root(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive extension is required for updater tests.');
        }

        $sourceDir = sys_get_temp_dir() . '/cbt-update-source-' . uniqid('', true);
        $uploadDir = sys_get_temp_dir() . '/cbt-update-upload-' . uniqid('', true);
        wp_mkdir_p($sourceDir . '/includes');
        file_put_contents($sourceDir . '/cbt-exam-system.php', "<?php\n/**\n * Plugin Name: CBT Exam System\n * Version: 1.8.2\n */\n");
        file_put_contents($sourceDir . '/includes/example.php', "<?php\n");
        $GLOBALS['cbt_test_wp_upload_dir'] = $uploadDir;

        $backup = \CBT_Update_Backup_Service::create_backup('backupunit', '1.8.2', $sourceDir);

        try {
            self::assertIsArray($backup);
            self::assertFileExists($backup['path']);
            self::assertFileExists($uploadDir . '/cbt-update-backups/index.php');
            self::assertFileExists($uploadDir . '/cbt-update-backups/.htaccess');
            self::assertTrue(\CBT_Update_Release_Helper::validate_zip_package_structure((string) $backup['path'], '1.8.2'));
        } finally {
            $this->deleteDirectory($sourceDir);
            $this->deleteDirectory($uploadDir);
        }
    }

    public function test_rollback_start_rejects_unknown_backup(): void
    {
        $result = \CBT_Update_Job_Service::start_rollback('missing-backup', 'unit_test');

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('backup_not_found', $result->get_error_code());
    }

    public function test_update_operation_ajax_rejects_unauthorized_user(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = false;
        $_POST = [
            'operation' => 'status',
            'nonce' => 'nonce-cbt_update_operation',
        ];

        try {
            \CBT_Admin_Update_Service::handle_update_operation_ajax();
            self::fail('Expected test ajax signal.');
        } catch (\RuntimeException $exception) {
            self::assertSame('cbt_update_ajax_response', $exception->getMessage());
        } finally {
            $_POST = [];
        }

        self::assertFalse($GLOBALS['cbt_test_last_ajax_response']['success']);
        self::assertSame(403, $GLOBALS['cbt_test_last_ajax_response']['status_code']);
    }

    public function test_health_check_reports_version_mismatch_as_failure(): void
    {
        $result = \CBT_Update_Health_Service::run([
            'version' => '0.0.0',
        ]);

        self::assertFalse($result['ok']);
        self::assertSame('failed', $result['status']);
    }

    public function test_build_page_context_maps_check_failed_state(): void
    {
        set_transient(\CBT_Update_Release_Helper::release_state_transient(), [
            'checked_at' => 1774353600,
            'status' => 'check_failed',
            'error_message' => 'GitHub Releases merespons dengan status HTTP 403.',
            'manifest' => [],
            'release' => [],
            'preflight' => [
                'status' => 'blocked',
                'items' => [],
                'has_blocked' => true,
            ],
        ], HOUR_IN_SECONDS);

        $context = \CBT_Admin_Update_Service::build_page_context([]);

        self::assertSame('check_failed', $context['status']);
        self::assertFalse($context['can_install']);
        self::assertSame('Check Failed', $context['status_meta']['label']);
    }

    public function test_build_page_context_maps_no_release_state(): void
    {
        set_transient(\CBT_Update_Release_Helper::release_state_transient(), [
            'checked_at' => 1774353600,
            'status' => 'no_release',
            'error_message' => 'Belum ada GitHub Release resmi pada repo sumber. Publish release pertama terlebih dahulu agar updater bisa dipakai.',
            'manifest' => [],
            'release' => [
                'tag' => '',
                'name' => '',
                'html_url' => \CBT_Update_Release_Helper::releases_html_url(),
                'published_at' => '',
            ],
            'preflight' => [
                'status' => 'blocked',
                'items' => [],
                'has_blocked' => true,
            ],
        ], HOUR_IN_SECONDS);

        $context = \CBT_Admin_Update_Service::build_page_context([]);

        self::assertSame('no_release', $context['status']);
        self::assertFalse($context['has_update']);
        self::assertFalse($context['can_install']);
        self::assertSame('No Release Yet', $context['status_meta']['label']);
        self::assertSame(\CBT_Update_Release_Helper::releases_html_url(), $context['release_url']);
    }

    public function test_admin_sources_register_update_menu_and_actions(): void
    {
        $menuSource = file_get_contents(CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-menu.php');
        $adminSource = file_get_contents(CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin.php');

        self::assertIsString($menuSource);
        self::assertIsString($adminSource);
        self::assertStringContainsString("'cbt-update'", $menuSource);
        self::assertStringContainsString("CBT_Admin_Update_Page::class", $menuSource);
        self::assertStringContainsString("add_action('admin_post_cbt_check_update_now'", $adminSource);
        self::assertStringContainsString("add_action('admin_post_cbt_install_update_now'", $adminSource);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function createReleaseZip(string $root, string $version = ''): array
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive extension is required for updater tests.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'cbt-release-');
        if ($zipPath === false) {
            self::fail('Unable to create temporary zip path.');
        }

        $archive = new \ZipArchive();
        $open = $archive->open($zipPath, \ZipArchive::OVERWRITE);
        if ($open !== true) {
            self::fail('Unable to open temporary zip archive.');
        }

        $versionHeader = $version !== '' ? " * Version: {$version}\n" : '';
        $archive->addFromString($root . 'cbt-exam-system.php', "<?php\n/**\n * Plugin Name: CBT Exam System\n{$versionHeader} */\n");
        $archive->addFromString($root . 'includes/example.php', "<?php\n");
        $archive->close();

        $hash = hash_file('sha256', $zipPath);
        if (!is_string($hash)) {
            self::fail('Unable to hash temporary release zip.');
        }

        return [$zipPath, $hash];
    }

    private function nextPatchVersion(string $version): string
    {
        $parts = array_map('intval', explode('.', $version));
        while (count($parts) < 3) {
            $parts[] = 0;
        }

        $parts[2]++;

        return implode('.', array_slice($parts, 0, 3));
    }

    private function seedReleaseRemote(string $remoteVersion, string $remoteTag, string $packageUrl): void
    {
        $releaseUrl = \CBT_Update_Release_Helper::latest_release_api_url();
        $manifestUrl = 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/download/' . $remoteTag . '/cbt-update-manifest.json';

        $GLOBALS['cbt_test_wp_remote_get_map'][$releaseUrl] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'tag_name' => $remoteTag,
                'name' => 'CBT ' . $remoteVersion,
                'html_url' => 'https://github.com/coblax/CBT-EXAM-SYSTEM/releases/tag/' . $remoteTag,
                'published_at' => '2026-03-29T12:00:00Z',
                'assets' => [
                    [
                        'name' => \CBT_Update_Release_Helper::manifest_asset_name(),
                        'browser_download_url' => $manifestUrl,
                    ],
                    [
                        'name' => \CBT_Update_Release_Helper::package_asset_name(),
                        'browser_download_url' => $packageUrl,
                    ],
                ],
            ]),
        ];
        $GLOBALS['cbt_test_wp_remote_get_map'][$manifestUrl] = [
            'response' => ['code' => 200],
            'body' => wp_json_encode([
                'version' => $remoteVersion,
                'tag' => $remoteTag,
                'published_at' => '2026-03-29T12:00:00Z',
                'download_url' => $packageUrl,
                'sha256' => str_repeat('a', 64),
                'requires_php' => '8.0',
                'requires_wp' => '6.0',
                'tested_up_to' => '6.8.1',
                'changelog' => 'Fix updater',
            ]),
        ];
    }

    private function deleteDirectory(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    }
}
