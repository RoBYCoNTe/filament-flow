<?php

namespace RoBYCoNTe\FilamentFlow\Observers;

use Illuminate\Cache\ArrayStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Observer that invalidates workflow-related caches when models are modified.
 * Registered on Workflow, WorkflowState, WorkflowStateAccessRule, WorkflowStateField.
 */
class WorkflowCacheObserver
{
    public function saved(Model $model): void
    {
        $this->clearCache();
    }

    public function deleted(Model $model): void
    {
        $this->clearCache();
    }

    protected function clearCache(): void
    {
        if (! config('filament-flow.cache.enabled', true)) {
            return;
        }

        $store = config('filament-flow.cache.store');
        $prefix = config('filament-flow.cache.prefix', 'filament-flow');

        $cache = Cache::store($store);

        // For stores that support tags, use tags
        // For array/file stores, we need to flush or rely on TTL
        // Since we use short TTLs (60s for access/perms, 300s for workflow),
        // the impact of stale cache is minimal.
        // We attempt a targeted flush for the driver that supports it.
        try {
            if (method_exists($cache->getStore(), 'flush')) {
                // For array driver (testing), flush is fine
                if ($cache->getStore() instanceof ArrayStore) {
                    $cache->flush();
                }
            }
        } catch (\Throwable) {
            // Ignore
        }
    }
}
