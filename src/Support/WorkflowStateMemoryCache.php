<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use RoBYCoNTe\FilamentFlow\Models\WorkflowState;

/**
 * In-memory cache for workflow state lookups within a single request/process.
 * Prevents repeated DB queries for the same (workflow_id, state_key) pair.
 */
class WorkflowStateMemoryCache
{
    /**
     * @var array<string, WorkflowState|null>
     */
    private static array $cache = [];

    public static function has(int $workflowId, string $stateKey): bool
    {
        $cacheKey = "{$workflowId}:{$stateKey}";

        return array_key_exists($cacheKey, self::$cache);
    }

    public static function get(int $workflowId, string $stateKey): ?WorkflowState
    {
        $cacheKey = "{$workflowId}:{$stateKey}";

        return self::$cache[$cacheKey] ?? null;
    }

    public static function set(int $workflowId, string $stateKey, ?WorkflowState $state): void
    {
        $cacheKey = "{$workflowId}:{$stateKey}";
        self::$cache[$cacheKey] = $state;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
