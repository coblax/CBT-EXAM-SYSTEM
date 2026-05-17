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
    public function test_invalidate_ui_state_busts_cached_value(): void
    {
        $this->bootstrapCacheScaffold();

        CBT_Cache::set('ui-state-test', 'before', 60, [CBT_Cache::namespace_ui_state()]);
        CBT_Cache::invalidate_ui_state();

        $calls = 0;
        $value = CBT_Cache::remember('ui-state-test', 60, [CBT_Cache::namespace_ui_state()], function () use (&$calls) {
            $calls++;
            return 'after';
        });

        self::assertSame('after', $value);
        self::assertSame(1, $calls);
    }

    #[RunInSeparateProcess]
    public function test_namespace_order_and_duplicates_do_not_change_cache_lookup(): void
    {
        $this->bootstrapCacheScaffold();

        CBT_Cache::set('ordered-key', 'cached-value', 60, [
            CBT_Cache::namespace_user(9),
            CBT_Cache::namespace_exam(5),
            CBT_Cache::namespace_user(9),
        ]);

        $found = false;
        $value = CBT_Cache::get('ordered-key', [
            CBT_Cache::namespace_exam(5),
            CBT_Cache::namespace_user(9),
        ], $found);

        self::assertTrue($found);
        self::assertSame('cached-value', $value);
    }

    #[RunInSeparateProcess]
    public function test_invalid_namespace_does_not_register_or_increment(): void
    {
        $this->bootstrapCacheScaffold();

        $version = CBT_Cache::invalidate_namespace(' !!! ');

        self::assertSame(0, $version);
        self::assertSame('', CBT_Cache::get_namespace_registry_entry(' !!! ')['namespace']);
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

        CBT_Cache::acquire_lock('expired-lock', 300, ['type' => 'expired']);
        CBT_Cache::acquire_lock('fresh-lock', 300, ['type' => 'fresh']);
        $registry = $this->getCacheRegistry();
        $registry['locks']['expired-lock']['expires_at'] = time() - 100;
        $registry['locks']['expired-lock']['updated_at'] = time() - 200;
        $this->setCacheRegistry($registry);

        $released = CBT_Cache::release_stale_locks();

        self::assertSame(1, $released);
        $entries = CBT_Cache::get_lock_registry_entries();
        $lockKeys = array_map(static fn (array $entry): string => (string) ($entry['lock_key'] ?? ''), $entries);
        self::assertNotContains('expired-lock', $lockKeys);
        self::assertContains('fresh-lock', $lockKeys);
        self::assertFalse(CBT_Cache::acquire_lock('fresh-lock', 300));
    }

    #[RunInSeparateProcess]
    public function test_release_lock_rejects_empty_key(): void
    {
        $this->bootstrapCacheScaffold();

        self::assertFalse(CBT_Cache::release_lock(''));
    }

    #[RunInSeparateProcess]
    public function test_ui_state_registry_orders_caps_unregisters_and_clears(): void
    {
        $this->bootstrapCacheScaffold();

        for ($i = 1; $i <= 205; $i++) {
            CBT_Cache::register_ui_state('ui-' . $i, [
                'type' => 'attempt',
                'user_id' => $i,
                'attempt_id' => 1000 + $i,
                'updated_at' => 1000 + $i,
                'expires_at' => 2000 + $i,
                'context' => ['index' => $i],
            ]);
        }

        $entries = CBT_Cache::get_ui_state_registry_entries();

        self::assertCount(200, $entries);
        self::assertSame('ui-205', (string) ($entries[0]['registry_key'] ?? ''));
        self::assertSame('ui-6', (string) ($entries[199]['registry_key'] ?? ''));
        self::assertSame(['index' => 205], $entries[0]['context'] ?? []);

        CBT_Cache::unregister_ui_state('ui-205');
        $afterUnregister = CBT_Cache::get_ui_state_registry_entries();
        $registryKeys = array_map(static fn (array $entry): string => (string) ($entry['registry_key'] ?? ''), $afterUnregister);
        self::assertNotContains('ui-205', $registryKeys);
        self::assertContains('ui-204', $registryKeys);

        CBT_Cache::clear_ui_state_registry();
        self::assertSame([], CBT_Cache::get_ui_state_registry_entries());
    }

    #[RunInSeparateProcess]
    public function test_reset_plugin_cache_state_clears_registries_and_bumps_global_version(): void
    {
        $this->bootstrapCacheScaffold();

        CBT_Cache::invalidate_exam(55);
        CBT_Cache::acquire_lock('reset-state-lock', 60, ['source' => 'unit']);
        CBT_Cache::register_ui_state('reset-ui', [
            'type' => 'attempt',
            'user_id' => 5,
            'attempt_id' => 42,
            'updated_at' => 100,
            'expires_at' => 200,
        ]);

        $before = $this->getCacheRegistry();
        CBT_Cache::reset_plugin_cache_state();
        $after = $this->getCacheRegistry();

        self::assertSame(((int) ($before['global_version'] ?? 1)) + 1, (int) ($after['global_version'] ?? 0));
        self::assertSame([], $after['namespaces'] ?? null);
        self::assertSame([], $after['locks'] ?? null);
        self::assertSame([], $after['ui_states'] ?? null);
        self::assertSame([], array_filter(
            CBT_Cache::get_namespace_registry_entries(),
            static fn (array $entry): bool => (string) ($entry['namespace'] ?? '') !== '__global__'
        ));
        self::assertSame([], CBT_Cache::get_lock_registry_entries());
        self::assertSame([], CBT_Cache::get_ui_state_registry_entries());
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

    #[RunInSeparateProcess]
    public function test_namespace_prune_removes_only_expired_entries(): void
    {
        $this->bootstrapCacheScaffold();
        $now = time();
        $this->setCacheRegistry([
            'global_version' => 1,
            'global_invalidated_at' => 0,
            'namespaces' => [
                'exam:old' => [
                    'version' => 4,
                    'invalidated_at' => $now - 120,
                ],
                'exam:fresh' => [
                    'version' => 2,
                    'invalidated_at' => $now,
                ],
            ],
            'locks' => [],
            'ui_states' => [],
        ]);

        $pruned = CBT_Cache::prune_old_namespaces(60);

        self::assertSame(1, $pruned);
        $namespaces = array_map(
            static fn (array $entry): string => (string) ($entry['namespace'] ?? ''),
            CBT_Cache::get_namespace_registry_entries()
        );
        self::assertNotContains('exam:old', $namespaces);
        self::assertContains('exam:fresh', $namespaces);
    }

    private function bootstrapCacheScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
    }

    /**
     * @return array<string,mixed>
     */
    private function getCacheRegistry(): array
    {
        $reflection = new ReflectionClass(CBT_Cache::class);
        $property = $reflection->getProperty('registry');
        $property->setAccessible(true);
        $registry = $property->getValue(null);

        return is_array($registry) ? $registry : [];
    }

    /**
     * @param array<string,mixed> $registry
     */
    private function setCacheRegistry(array $registry): void
    {
        $reflection = new ReflectionClass(CBT_Cache::class);
        $property = $reflection->getProperty('registry');
        $property->setAccessible(true);
        $property->setValue(null, $registry);
        update_option('cbt_exam_cache_registry_v1', $registry, false);
    }
}
