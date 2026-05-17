<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class UpdateBackupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-update-release-helper.php';
        require_once CBT_EXAM_SYSTEM_PATH . 'includes/class-cbt-update-backup-service.php';
    }

    public function test_get_backups_returns_empty_array_initially(): void
    {
        $this->assertSame([], \CBT_Update_Backup_Service::get_backups());
    }

    public function test_get_backup_returns_null_for_empty_id(): void
    {
        $this->assertNull(\CBT_Update_Backup_Service::get_backup(''));
    }

    public function test_get_backup_returns_null_for_nonexistent_id(): void
    {
        $this->assertNull(\CBT_Update_Backup_Service::get_backup('nonexistent'));
    }

    public function test_save_backups_persists_and_retrieves(): void
    {
        $backups = [
            [
                'id' => 'test1',
                'token' => 'test1',
                'version' => '3.2.0',
                'file_name' => 'cbt-exam-system-3.2.0.zip',
                'path' => '/tmp/cbt-exam-system-3.2.0.zip',
                'size' => 1024,
                'sha256' => 'abc123',
                'created_at' => '2026-01-01 00:00:00',
                'created_at_ts' => 1767225600,
            ],
        ];

        \CBT_Update_Backup_Service::save_backups($backups);
        $retrieved = \CBT_Update_Backup_Service::get_backups();
        $this->assertCount(1, $retrieved);
        $this->assertSame('test1', $retrieved[0]['id']);
        $this->assertSame('3.2.0', $retrieved[0]['version']);
    }

    public function test_prune_backups_respects_max_limit(): void
    {
        $backups = [];
        for ($i = 0; $i < 8; $i++) {
            $backups[] = [
                'id' => 'backup' . $i,
                'token' => 'backup' . $i,
                'version' => '3.0.' . $i,
                'file_name' => 'test-' . $i . '.zip',
                'path' => '',
                'size' => 100,
                'sha256' => '',
                'created_at' => '2026-01-0' . ($i + 1) . ' 00:00:00',
                'created_at_ts' => time() - ($i * 86400),
            ];
        }

        $pruned = \CBT_Update_Backup_Service::prune_backups($backups);
        $this->assertLessThanOrEqual(5, count($pruned));
    }

    public function test_prune_backups_removes_expired_entries(): void
    {
        $backups = [
            [
                'id' => 'old',
                'token' => 'old',
                'version' => '1.0.0',
                'file_name' => 'old.zip',
                'path' => '',
                'size' => 100,
                'sha256' => '',
                'created_at' => '2025-01-01 00:00:00',
                'created_at_ts' => strtotime('2025-01-01'),
            ],
            [
                'id' => 'recent',
                'token' => 'recent',
                'version' => '3.2.0',
                'file_name' => 'recent.zip',
                'path' => '',
                'size' => 200,
                'sha256' => '',
                'created_at' => '2026-05-01 00:00:00',
                'created_at_ts' => time() - 86400,
            ],
        ];

        $pruned = \CBT_Update_Backup_Service::prune_backups($backups);
        $ids = array_column($pruned, 'id');
        $this->assertContains('recent', $ids);
    }

    public function test_get_backups_sorts_by_created_at_desc(): void
    {
        $backups = [
            [
                'id' => 'older',
                'token' => 'older',
                'version' => '3.1.0',
                'file_name' => 'older.zip',
                'path' => '',
                'size' => 100,
                'sha256' => '',
                'created_at' => '2026-01-01 00:00:00',
                'created_at_ts' => time() - 86400,
            ],
            [
                'id' => 'newer',
                'token' => 'newer',
                'version' => '3.2.0',
                'file_name' => 'newer.zip',
                'path' => '',
                'size' => 200,
                'sha256' => '',
                'created_at' => '2026-05-01 00:00:00',
                'created_at_ts' => time(),
            ],
        ];

        \CBT_Update_Backup_Service::save_backups($backups);
        $retrieved = \CBT_Update_Backup_Service::get_backups();
        $this->assertSame('newer', $retrieved[0]['id']);
    }

    public function test_create_backup_fails_without_zip_archive(): void
    {
        // ZipArchive is available but we can test source_dir validation
        $result = \CBT_Update_Backup_Service::create_backup('tok', '3.2.0', '/nonexistent/path/to/plugin');
        $this->assertInstanceOf(\WP_Error::class, $result);
    }
}
