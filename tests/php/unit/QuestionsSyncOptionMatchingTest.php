<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-sync-helper.php';

use CbtExamSystem\Tests\TestCase;
use ReflectionClass;

final class QuestionsSyncOptionMatchingTest extends TestCase
{
    public function test_removing_a_bank_option_keeps_exam_option_ids_attached_to_their_text(): void
    {
        global $wpdb;
        $wpdb = new QuestionsSyncOptionMatchingFakeWpdb();

        $existing = [
            ['id' => 11, 'option_key' => 'A', 'option_text' => 'Jakarta', 'is_correct' => 1],
            ['id' => 12, 'option_key' => 'B', 'option_text' => 'Bandung', 'is_correct' => 0],
            ['id' => 13, 'option_key' => 'C', 'option_text' => 'Surabaya', 'is_correct' => 0],
            ['id' => 14, 'option_key' => 'D', 'option_text' => 'Medan', 'is_correct' => 0],
        ];
        // Guru menghapus opsi B di bank soal: key C/D bergeser menjadi B/C.
        $desired = [
            ['id' => 101, 'option_key' => 'A', 'option_text' => 'Jakarta', 'is_correct' => 1],
            ['id' => 102, 'option_key' => 'B', 'option_text' => 'Surabaya', 'is_correct' => 0],
            ['id' => 103, 'option_key' => 'C', 'option_text' => 'Medan', 'is_correct' => 0],
        ];

        $map = $this->invokeSync([77, $desired, $existing, true]);

        self::assertSame([11 => 11, 13 => 13, 14 => 14], $map, 'Jawaban siswa yang memilih "Surabaya" (id 13) harus tetap "Surabaya".');
        self::assertSame(['A', 'Jakarta'], [$wpdb->updates[11]['option_key'], $wpdb->updates[11]['option_text']]);
        self::assertSame(['B', 'Surabaya'], [$wpdb->updates[13]['option_key'], $wpdb->updates[13]['option_text']]);
        self::assertSame(['C', 'Medan'], [$wpdb->updates[14]['option_key'], $wpdb->updates[14]['option_text']]);
        self::assertSame([12], $wpdb->deletedIds);
        self::assertSame(0, $wpdb->insertCalls);
    }

    public function test_editing_option_text_in_place_still_falls_back_to_option_key(): void
    {
        global $wpdb;
        $wpdb = new QuestionsSyncOptionMatchingFakeWpdb();

        $existing = [
            ['id' => 11, 'option_key' => 'A', 'option_text' => 'Jakarat', 'is_correct' => 1],
            ['id' => 12, 'option_key' => 'B', 'option_text' => 'Bandung', 'is_correct' => 0],
        ];
        $desired = [
            ['id' => 101, 'option_key' => 'A', 'option_text' => 'Jakarta', 'is_correct' => 1],
            ['id' => 102, 'option_key' => 'B', 'option_text' => 'Bandung', 'is_correct' => 0],
        ];

        $map = $this->invokeSync([77, $desired, $existing, true]);

        self::assertSame([12 => 12, 11 => 11], $map);
        self::assertSame('Jakarta', $wpdb->updates[11]['option_text']);
        self::assertSame([], $wpdb->deletedIds);
        self::assertSame(0, $wpdb->insertCalls);
    }

    /**
     * @param array<int,mixed> $args
     * @return array<int,int>
     */
    private function invokeSync(array $args): array
    {
        $method = (new ReflectionClass(\CBT_Admin_Questions_Sync_Helper::class))->getMethod('sync_question_options_from_snapshot');
        $method->setAccessible(true);

        return $method->invokeArgs(null, $args);
    }
}

final class QuestionsSyncOptionMatchingFakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 500;
    public int $insertCalls = 0;
    /** @var array<int,array<string,mixed>> */
    public array $updates = [];
    /** @var array<int,int> */
    public array $deletedIds = [];

    public function update(string $table, array $data, array $where, $format = null, $whereFormat = null): int
    {
        $this->updates[(int) ($where['id'] ?? 0)] = $data;
        return 1;
    }

    public function insert(string $table, array $data, $format = null): int
    {
        $this->insertCalls++;
        $this->insert_id++;
        return 1;
    }

    public function delete(string $table, array $where, $format = null): int
    {
        $this->deletedIds[] = (int) ($where['id'] ?? 0);
        return 1;
    }
}
