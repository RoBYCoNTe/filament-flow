<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Centralises all cache operations for filament-flow.
 *
 * Supports both Redis (native cache tags) and non-tag stores (Memcached, File, Database)
 * via an automatic key-registry fallback.
 *
 * When tags are NOT natively supported, each tag stores a registry key listing every
 * cache key associated with it. Flushing a tag reads the registry and forgets each
 * individual key. A long safety-net TTL ensures orphaned keys (due to LRU eviction
 * of the registry) eventually expire.
 */
class WorkflowCacheManager
{
    protected string $store;

    protected string $prefix;

    protected int $safetyTtl;

    protected bool $enabled;

    /**
     * Tag-registry keys used when the store does not support native tags.
     *
     * @var array<string, true>
     */
    protected static array $suppressedRegistries = [];

    public function __construct()
    {
        $this->enabled = config('filament-flow.cache.enabled', true);
        $this->store = config('filament-flow.cache.store') ?? (string) config('cache.default');
        $this->prefix = config('filament-flow.cache.prefix', 'filament-flow');
        $this->safetyTtl = config('filament-flow.cache.safety_ttl', 86400);
    }

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    /**
     * Remember a value in the cache, optionally tagged.
     */
    public function remember(string $key, ?int $ttl, Closure $callback, array $tags = []): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $ttl ??= $this->safetyTtl;

        $fullKey = $this->fullKey($key);

        if ($this->hasTagSupport() && ! empty($tags)) {
            return Cache::store($this->store)
                ->tags($tags)
                ->remember($fullKey, $ttl, function () use ($callback) {
                    return $callback();
                });
        }

        $value = Cache::store($this->store)->get($fullKey);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();

        if ($value !== null) {
            Cache::store($this->store)->put($fullKey, $value, $ttl);
        }

        if (! empty($tags)) {
            $this->registerKeyToTags($fullKey, $tags);
        }

        return $value;
    }

    /**
     * Store a value in the cache with tags.
     */
    public function put(string $key, mixed $value, ?int $ttl = null, array $tags = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $ttl ??= $this->safetyTtl;
        $fullKey = $this->fullKey($key);

        if ($this->hasTagSupport() && ! empty($tags)) {
            Cache::store($this->store)->tags($tags)->put($fullKey, $value, $ttl);
        } else {
            Cache::store($this->store)->put($fullKey, $value, $ttl);

            if (! empty($tags)) {
                $this->registerKeyToTags($fullKey, $tags);
            }
        }
    }

    /**
     * Retrieve a cached value.
     */
    public function get(string $key): mixed
    {
        if (! $this->enabled) {
            return null;
        }

        return Cache::store($this->store)->get($this->fullKey($key));
    }

    /**
     * Remove a single key from the cache.
     */
    public function forget(string $key): void
    {
        if (! $this->enabled) {
            return;
        }

        Cache::store($this->store)->forget($this->fullKey($key));
    }

    /**
     * Flush every cached key belonging to any of the given tags.
     */
    public function flushTags(array $tags): void
    {
        if (! $this->enabled || empty($tags)) {
            return;
        }

        if ($this->hasTagSupport()) {
            Cache::store($this->store)->tags($tags)->flush();

            return;
        }

        foreach ($tags as $tag) {
            $this->flushTagViaRegistry($tag);
        }
    }

    /**
     * Whether the underlying store supports native cache tags.
     */
    public function hasTagSupport(): bool
    {
        return method_exists(Cache::store($this->store)->getStore(), 'tags');
    }

    /**
     * Get the underlying cache store name.
     */
    public function getStore(): string
    {
        return $this->store;
    }

    /**
     * Build a tag name for workflow-level grouping.
     */
    public function workflowTag(int|string $workflowId): string
    {
        return "workflow:{$workflowId}";
    }

    /**
     * Build a tag name for state-level grouping.
     */
    public function stateTag(int|string $workflowId): string
    {
        return "workflow-states:{$workflowId}";
    }

    /**
     * Build a tag name for access-rule grouping.
     */
    public function accessTag(int|string $stateId): string
    {
        return "workflow-access:{$stateId}";
    }

    /**
     * Build a tag name for field-permission grouping.
     */
    public function fieldsTag(int|string $workflowId): string
    {
        return "workflow-fields:{$workflowId}";
    }

    // ----------------------------------------------------------------
    // Registry fallback for non-tag stores
    // ----------------------------------------------------------------

    protected function registerKeyToTags(string $fullKey, array $tags): void
    {
        foreach ($tags as $tag) {
            $registryKey = $this->registryKeyForTag($tag);

            if (isset(static::$suppressedRegistries[$registryKey])) {
                continue;
            }

            try {
                $keys = Cache::store($this->store)->get($registryKey, []);
            } catch (\Throwable) {
                $keys = [];
            }

            if (! is_array($keys)) {
                $keys = [];
            }

            $keys[] = $fullKey;
            $keys = array_unique($keys);

            Cache::store($this->store)->put($registryKey, $keys, $this->safetyTtl);
            static::$suppressedRegistries[$registryKey] = true;
        }
    }

    protected function flushTagViaRegistry(string $tag): void
    {
        $registryKey = $this->registryKeyForTag($tag);

        try {
            $keys = Cache::store($this->store)->get($registryKey, []);
        } catch (\Throwable) {
            $keys = [];
        }

        if (! is_array($keys)) {
            $keys = [];
        }

        foreach ($keys as $key) {
            Cache::store($this->store)->forget($key);
        }

        Cache::store($this->store)->forget($registryKey);
        unset(static::$suppressedRegistries[$registryKey]);
    }

    protected function registryKeyForTag(string $tag): string
    {
        return $this->fullKey("__tag_registry:{$tag}");
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    protected function fullKey(string $key): string
    {
        return "{$this->prefix}:{$key}";
    }
}
