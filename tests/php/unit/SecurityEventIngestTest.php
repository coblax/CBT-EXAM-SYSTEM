<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

if (!class_exists('wpdb')) {
    eval(<<<'PHP'
class wpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';

    public function get_charset_collate(): string
    {
        return '';
    }
}
PHP);
}

final class SecurityEventIngestTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_record_attempt_event_queues_to_redis_first_without_mysql_insert(): void
    {
        $this->bootstrapSecurityClasses();
        $this->useFakeSecurityRedis();

        update_option('cbt_setup_security', [
            'log_security_events' => 1,
            'security_redis_first_ingest' => 1,
        ]);

        cbt_test_register_user([
            'ID' => 71,
            'user_login' => 'coblax',
            'display_name' => 'Coblax Student',
        ]);
        update_user_meta(71, 'kode_kelas', 'X-A');
        update_user_meta(71, 'kode_ruang', 'R1');

        global $wpdb;
        $wpdb = new SecurityEventIngestFakeWpdb();
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];
        $wpdb->examRows[501] = [
            'id' => 501,
            'title' => 'Security Fixture',
            'created_by' => 9,
        ];

        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'window_blur', [
            'source' => 'blur',
            'device_type' => 'desktop',
            'device_platform' => 'windows',
        ]));
        self::assertCount(0, $wpdb->insertedRows);
        self::assertCount(1, $GLOBALS['cbt_test_redis_streams']['cbt_security_ingest:events'] ?? []);

        $payloads = \CBT_Security_Live_Counters::get_active_attempt_payloads();
        self::assertCount(1, $payloads);
        self::assertSame(10, $payloads[0]['attempt_id']);
        self::assertSame(1, $payloads[0]['event_total']);
        self::assertEquals(2.0, $payloads[0]['risk_score']);
    }

    #[RunInSeparateProcess]
    public function test_record_attempt_event_falls_back_to_direct_mysql_when_stream_enqueue_fails(): void
    {
        $this->bootstrapSecurityClasses();
        $this->useFakeSecurityRedis();

        update_option('cbt_setup_security', [
            'log_security_events' => 1,
            'security_redis_first_ingest' => 1,
        ]);
        $GLOBALS['cbt_test_redis_fail_stream_keys'] = ['cbt_security_ingest:events'];

        global $wpdb;
        $wpdb = new SecurityEventIngestFakeWpdb();
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];
        $wpdb->examRows[501] = [
            'id' => 501,
            'title' => 'Security Fixture',
            'created_by' => 9,
        ];

        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'clipboard_blocked', [
            'source' => 'copy',
            'device_type' => 'desktop',
        ]));
        self::assertCount(1, $wpdb->insertedRows);
        self::assertSame('clipboard_blocked', $wpdb->insertedRows[0]['event_type']);
        self::assertSame([], $GLOBALS['cbt_test_redis_streams']['cbt_security_ingest:events'] ?? []);
    }

    #[RunInSeparateProcess]
    public function test_flush_batch_persists_enqueued_events_and_clears_backlog(): void
    {
        $this->bootstrapSecurityClasses();
        $this->useFakeSecurityRedis();

        update_option('cbt_setup_security', [
            'log_security_events' => 1,
            'security_redis_first_ingest' => 1,
        ]);

        global $wpdb;
        $wpdb = new SecurityEventIngestFakeWpdb();
        $wpdb->attemptsById[10] = [
            'id' => 10,
            'exam_id' => 501,
            'student_id' => 71,
            'status' => 'in_progress',
        ];
        $wpdb->examRows[501] = [
            'id' => 501,
            'title' => 'Security Fixture',
            'created_by' => 9,
        ];

        self::assertTrue(\CBT_Security_Log::record_attempt_event(10, 'window_blur', [
            'source' => 'blur',
            'device_type' => 'desktop',
        ]));
        self::assertCount(0, $wpdb->insertedRows);
        self::assertCount(1, $GLOBALS['cbt_test_redis_streams']['cbt_security_ingest:events'] ?? []);

        $result = \CBT_Security_Event_Ingest::flush_batch(250, 2.0, 'phpunit');

        self::assertSame(1, $result['persisted']);
        self::assertSame(0, $result['backlog_count']);
        self::assertCount(1, $wpdb->insertedRows);
        self::assertSame([], $GLOBALS['cbt_test_redis_streams']['cbt_security_ingest:events'] ?? []);
        self::assertNotSame('', get_option('cbt_security_ingest_last_stream_id', ''));
    }

    private function bootstrapSecurityClasses(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-live-counters.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-event-ingest.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
    }

    private function useFakeSecurityRedis(): void
    {
        $reflection = new \ReflectionClass(\CBT_Security_Live_Counters::class);

        $redisProperty = $reflection->getProperty('live_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('live_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('live_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}

final class SecurityEventIngestFakeWpdb extends \wpdb
{
    /** @var array<int,array<string,mixed>> */
    public array $attemptsById = [];

    /** @var array<int,array<string,mixed>> */
    public array $insertedRows = [];

    /** @var array<int,array<string,mixed>> */
    public array $examRows = [];

    public string $last_error = '';

    public function prepare($query, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        foreach ((array) $args as $arg) {
            $replacement = is_numeric($arg) ? (string) $arg : "'" . str_replace("'", "''", (string) $arg) . "'";
            $query = preg_replace('/%d|%s/', $replacement, $query, 1) ?? $query;
        }

        return $query;
    }

    public function insert($table, $data, $format = null): int|false
    {
        $ingestId = is_array($data) ? (string) ($data['ingest_id'] ?? '') : '';
        if ($ingestId !== '') {
            foreach ($this->insertedRows as $row) {
                if ((string) ($row['ingest_id'] ?? '') === $ingestId) {
                    $this->last_error = 'Duplicate entry';
                    return false;
                }
            }
        }

        $this->last_error = '';
        $this->insertedRows[] = is_array($data) ? $data : [];
        return 1;
    }

    public function get_row($query, $output = ARRAY_A): ?array
    {
        if (
            preg_match('/FROM ' . preg_quote($this->prefix . 'cbt_exams', '/') . '/', (string) $query)
            && preg_match('/WHERE id = (\d+)/', (string) $query, $matches)
        ) {
            return $this->examRows[(int) $matches[1]] ?? null;
        }

        if (preg_match('/WHERE id = (\d+)/', (string) $query, $matches)) {
            return $this->attemptsById[(int) $matches[1]] ?? null;
        }

        return null;
    }

    public function get_results($query, $output = ARRAY_A): array
    {
        return [];
    }

    public function get_col($query): array
    {
        return [];
    }

    public function query($query): int|false
    {
        return 0;
    }
}
