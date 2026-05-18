<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Models;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * @method static whereHas(string $string, Closure $param)
 * @method static where(string $string, string $class)
 * @method static firstOrCreate(string[] $array, array $array1)
 * @method static create(bool[]|mixed[]|string[] $array_merge)
 *
 * @property mixed $state_column
 * @property int $id
 * @property int|null $tenant_id
 * @property Collection $states
 */
class Workflow extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'model_type',
        'state_column',
        'is_active',
        'creation_policy',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'creation_policy' => 'array',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        $tenantModel = config('filament-flow.tenant_model');

        if (! $tenantModel) {
            throw new RuntimeException('Tenant model not configured. Please set filament-flow.tenant_model in config.');
        }

        return $this->belongsTo($tenantModel, config('filament-flow.tenant_foreign_key', 'tenant_id'));
    }

    /**
     * Check if multi-tenancy is enabled
     */
    public static function isMultiTenancyEnabled(): bool
    {
        return config('filament-flow.tenant_model') !== null;
    }

    /**
     * Find workflow for a given model class with tenant fallback support.
     *
     * Priority order:
     * 1. Tenant-specific workflow (if multi-tenancy enabled and tenant exists)
     * 2. Global workflow (tenant_id = null)
     *
     * @param  string  $modelClass  The model class name
     * @param  string  $stateColumn  The state column name (default: 'state')
     * @param  int|null  $tenantId  Optional specific tenant ID (overrides auto-detection)
     */
    public static function findForModel(string $modelClass, string $stateColumn = 'state', ?int $tenantId = null): ?static
    {
        // Determine the tenant ID
        $effectiveTenantId = $tenantId ?? static::getCurrentTenantId();

        // Check cache
        if (config('filament-flow.cache.enabled', true)) {
            $prefix = config('filament-flow.cache.prefix', 'filament-flow');
            $cacheKey = "{$prefix}:workflow:{$modelClass}:{$stateColumn}:{$effectiveTenantId}";
            $ttl = config('filament-flow.cache.ttl', 300);
            $store = config('filament-flow.cache.store');

            return Cache::store($store)->remember($cacheKey, $ttl, function () use ($modelClass, $stateColumn, $effectiveTenantId) {
                return static::findForModelUncached($modelClass, $stateColumn, $effectiveTenantId);
            });
        }

        return static::findForModelUncached($modelClass, $stateColumn, $effectiveTenantId);
    }

    /**
     * Uncached version of findForModel.
     */
    protected static function findForModelUncached(string $modelClass, string $stateColumn, ?int $effectiveTenantId): ?static
    {
        // If multi-tenancy is enabled and we have a tenant, try tenant-specific first
        if (static::isMultiTenancyEnabled() && $effectiveTenantId !== null) {
            $tenantForeignKey = config('filament-flow.tenant_foreign_key', 'tenant_id');

            // Try tenant-specific workflow first
            $workflow = static::where('model_type', $modelClass)
                ->where('state_column', $stateColumn)
                ->where($tenantForeignKey, $effectiveTenantId)
                ->where('is_active', true)
                ->first();

            if ($workflow) {
                return $workflow;
            }
        }

        // Fallback to global workflow (tenant_id = null)
        $tenantForeignKey = config('filament-flow.tenant_foreign_key', 'tenant_id');

        return static::where('model_type', $modelClass)
            ->where('state_column', $stateColumn)
            ->where(function (Builder $query) use ($tenantForeignKey) {
                $query->whereNull($tenantForeignKey);
            })
            ->where('is_active', true)
            ->first();
    }

    /**
     * Flush all workflow caches.
     */
    public static function flushCache(): void
    {
        $prefix = config('filament-flow.cache.prefix', 'filament-flow');
        $store = config('filament-flow.cache.store');

        // Since we can't enumerate cache keys easily, flush the tagged group
        // or rely on the observer to clear specific keys.
        // For stores that support tags:
        try {
            Cache::store($store)->flush();
        } catch (\Throwable) {
            // Store doesn't support flush — individual keys will expire via TTL
        }
    }

    /**
     * Get the current tenant ID from Filament or custom resolver.
     */
    public static function getCurrentTenantId(): ?int
    {
        // Check if there's a custom tenant resolver
        $customResolver = config('filament-flow.tenant_resolver');
        if ($customResolver && is_callable($customResolver)) {
            return call_user_func($customResolver);
        }

        // Try to get tenant from Filament
        try {
            $tenant = Filament::getTenant();
            if ($tenant) {
                return $tenant->getKey();
            }
        } catch (\Throwable) {
            // Filament might not be available in all contexts
        }

        return null;
    }

    /**
     * Check if this workflow is global (not tenant-specific).
     */
    public function isGlobal(): bool
    {
        $tenantForeignKey = config('filament-flow.tenant_foreign_key', 'tenant_id');

        return $this->{$tenantForeignKey} === null;
    }

    /**
     * Check if this workflow is tenant-specific.
     */
    public function isTenantSpecific(): bool
    {
        return ! $this->isGlobal();
    }

    /**
     * Scope to filter by current tenant (includes global workflows).
     */
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantId = static::getCurrentTenantId();
        $tenantForeignKey = config('filament-flow.tenant_foreign_key', 'tenant_id');

        if ($tenantId !== null && static::isMultiTenancyEnabled()) {
            return $query->where(function (Builder $q) use ($tenantForeignKey, $tenantId) {
                $q->where($tenantForeignKey, $tenantId)
                    ->orWhereNull($tenantForeignKey);
            });
        }

        // If no tenant, only show global workflows
        return $query->whereNull($tenantForeignKey);
    }

    /**
     * Scope to filter by specific tenant only (excludes global workflows).
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        $tenantForeignKey = config('filament-flow.tenant_foreign_key', 'tenant_id');

        return $query->where($tenantForeignKey, $tenantId);
    }

    /**
     * Scope to get only global workflows.
     */
    public function scopeGlobal(Builder $query): Builder
    {
        $tenantForeignKey = config('filament-flow.tenant_foreign_key', 'tenant_id');

        return $query->whereNull($tenantForeignKey);
    }

    public function states(): HasMany
    {
        return $this->hasMany(WorkflowState::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WorkflowNotification::class);
    }

    public function scheduledChecks(): HasMany
    {
        return $this->hasMany(WorkflowScheduledCheck::class);
    }

    public function initialState(): ?WorkflowState
    {
        return $this->states()->where('is_initial', true)->first();
    }

    public function finalStates(): HasMany
    {
        return $this->states()->where('is_final', true);
    }

    public function transitionHistory(): HasMany
    {
        return $this->hasMany(WorkflowStateTransition::class);
    }

    public function notificationLogs(): HasManyThrough
    {
        return $this->hasManyThrough(
            WorkflowNotificationLog::class,
            WorkflowNotification::class,
            'workflow_id',
            'notification_id',
        );
    }
}
