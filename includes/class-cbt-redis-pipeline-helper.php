<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Redis_Pipeline_Helper
{
    /**
     * @param array<int,array{key:string,ttl:int,value:string}> $operations
     * @return array<int,bool>
     */
    public static function write_setex_results(Redis $redis, array $operations): array
    {
        if (empty($operations)) {
            return [];
        }

        if (self::supports_pipeline($redis)) {
            try {
                $pipeline = $redis->pipeline();
                if (!is_object($pipeline) || !method_exists($pipeline, 'setEx') || !method_exists($pipeline, 'exec')) {
                    throw new RuntimeException('Redis pipeline tidak tersedia.');
                }

                foreach ($operations as $operation) {
                    $pipeline->setEx(
                        (string) ($operation['key'] ?? ''),
                        max(1, (int) ($operation['ttl'] ?? 0)),
                        (string) ($operation['value'] ?? '')
                    );
                }

                $results = $pipeline->exec();
                if (is_array($results) && count($results) === count($operations)) {
                    return array_map(static function ($result): bool {
                        return $result !== false;
                    }, array_values($results));
                }
            } catch (Throwable $throwable) {
            }
        }

        return self::write_setex_results_direct($redis, $operations);
    }

    private static function supports_pipeline(Redis $redis): bool
    {
        return method_exists($redis, 'pipeline') && method_exists($redis, 'exec');
    }

    /**
     * @param array<int,array{key:string,ttl:int,value:string}> $operations
     * @return array<int,bool>
     */
    private static function write_setex_results_direct(Redis $redis, array $operations): array
    {
        $results = [];
        foreach ($operations as $operation) {
            $results[] = $redis->setEx(
                (string) ($operation['key'] ?? ''),
                max(1, (int) ($operation['ttl'] ?? 0)),
                (string) ($operation['value'] ?? '')
            ) !== false;
        }

        return $results;
    }
}
