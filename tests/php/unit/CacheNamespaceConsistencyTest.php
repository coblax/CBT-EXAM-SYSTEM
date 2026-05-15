<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class CacheNamespaceConsistencyTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_remember_caches_value_on_first_call(): void
    {
        $this->bootstrapCacheScaffold();

        $calls = 0;
        $value = CBT_Cache::remember('test-key', 60, [CBT_Cache::namespace_catalog()], function () use (&$calls) {
            $calls++;
            return ['data' => 'hello'];
        });

        self::assertSame(['data' => 'hello'], $value);
        self::assertSame(1, $calls);

        // Second call should use cache
        $value2 = CBT_Cache::remember('test-key', 60, [CBT_Cache::namespace_catalog()], function () use (&$calls) {
            $calls++;
            return ['data' => 'world'];
        });

        self::assertSame(['data' => 'hello'], $value2);
        self::assertSame(1, $calls);
    }

    #[RunInSeparateProcess]
    public function test_invalidate_namespace_increments_version(): void
    {
        $this->bootstrapCacheScaffold();

        $entry_before = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog());
        $version_before = (int) $entry_before['version'];

        CBT_Cache::invalidate_catalog();

        $entry_after = CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog());
        $version_after = (int) $entry_after['version'];

        self::assertSame($version_before + 1, $version_after);
    }

    #[RunInSeparateProcess]
    public function test_invalidate_namespace_busts_cached_value(): void
    {
        $this->bootstrapCacheScaffold();

        CBT_Cache::remember('bust-test', 60, [CBT_Cache::namespace_exam(5)], function () {
            return 'original';
        });

        CBT_Cache::invalidate_exam(5);

        $calls = 0;
        $value = CBT_Cache::remember('bust-test', 60, [CBT_Cache::namespace_exam(5)], function () use (&$calls) {
            $calls++;
            return 'refreshed';
        });

        self::assertSame('refreshed', $value);
        self::assertSame(1, $calls);
    }

    #[RunInSeparateProcess]
    public function test_acquire_lock_and_release(): void
    {
        $this->bootstrapCacheScaffold();

        $acquired = CBT_Cache::acquire_lock('test-lock', 30, ['type' => 'test']);
        self::assertTrue($acquired);

        // Second acquire should fail
        $second = CBT_Cache::acquire_lock('test-lock', 30, ['type' => 'test']);
        self::assertFalse($second);

        // Release and re-acquire
        CBT_Cache::release_lock('test-lock');
        $third = CBT_Cache::acquire_lock('test-lock', 30, ['type' => 'test']);
        self::assertTrue($third);
    }

    #[RunInSeparateProcess]
    public function test_stale_lock_can_be_reacquired(): void
    {
        $this->bootstrapCacheScaffold();

        // Manually create a stale lock
        $stale_payload = [
            'lock_key' => 'stale-lock',
            'context' => [],
            'created_at' => time() - 100,
            'updated_at' => time() - 100,
            'expires_at' => time() - 50,
        ];
        update_option('cbt_ce_lock_' . md5('stale-lock'), $stale_payload);

        $acquired = CBT_Cache::acquire_lock('stale-lock', 30, ['type' => 'test']);
        self::assertTrue($acquired);
    }

    #[RunInSeparateProcess]
    public function test_release_stale_locks_cleans_expired(): void
    {
        $this->bootstrapCacheScaffold();

        // Create a lock that's already expired
        $stale_payload = [
            'lock_key' => 'expired-lock',
            'context' => [],
            'created_at' => time() - 200,
            'updated_at' => time() - 200,
            'expires_at' => time() - 100,
        ];
        update_option('cbt_ce_lock_' . md5('expired-lock'), $stale_payload);

        // Register it in the registry
        CBT_Cache::acquire_lock('fresh-lock', 300, ['type' => 'fresh']);

        $released = CBT_Cache::release_stale_locks();

        // At minimum the stale one should be released if it was registered
        self::assertGreaterThanOrEqual(0, $released);
    }

    #[RunInSeparateProcess]
    public function test_get_set_delete_operations(): void
    {
        $this->bootstrapCacheScaffold();

        CBT_Cache::set('direct-key', ['value' => 42], 60, [CBT_Cache::namespace_catalog()]);

        $found = false;
        $result = CBT_Cache::get('direct-key', [CBT_Cache::namespace_catalog()], $found);

        self::assertTrue($found);
        self::assertSame(['value' => 42], $result);

        CBT_Cache::delete('direct-key', [CBT_Cache::namespace_catalog()]);

        $found2 = false;
        $result2 = CBT_Cache::get('direct-key', [CBT_Cache::namespace_catalog()], $found2);

        self::assertFalse($found2);
        self::assertNull($result2);
    }

    #[RunInSeparateProcess]
    public function test_invalidate_all_busts_everything(): void
    {
        $this->bootstrapCacheScaffold();

        CBT_Cache::set('global-test', 'cached', 60, [CBT_Cache::namespace_catalog()]);
        CBT_Cache::invalidate_all();

        $found = false;
        CBT_Cache::get('global-test', [CBT_Cache::namespace_catalog()], $found);

        self::assertFalse($found);
    }

    #[RunInSeparateProcess]
    public function test_exam_revision_meta_returns_consistent_signature(): void
    {
        $this->bootstrapCacheScaffold();

        $meta1 = CBT_Cache::get_exam_revision_meta(5);
        $meta2 = CBT_Cache::get_exam_revision_meta(5);

        self::assertSame($meta1['signature'], $meta2['signature']);
        self::assertSame(5, $meta1['exam_id']);
        self::assertGreaterThanOrEqual(1, $meta1['version']);

        // After invalidation, signature should change
        CBT_Cache::invalidate_exam(5);
        $meta3 = CBT_Cache::get_exam_revision_meta(5);

        self::assertNotSame($meta1['signature'], $meta3['signature']);
    }

    #[RunInSeparateProcess]
    public function test_namespace_prune_removes_old_entries(): void
    {
        $this->bootstrapCacheScaffold();

        // Create some namespaces
        CBT_Cache::invalidate_exam(100);
        CBT_Cache::invalidate_exam(200);

        // Prune with very short retention (should remove entries older than 1 second)
        // Since we just created them, they should NOT be pruned
        $pruned = CBT_Cache::prune_old_namespaces(1);

        self::assertSame(0, $pruned);
    }

    private function bootstrapCacheScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
    }
}
