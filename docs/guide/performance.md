# Performance & Caching

Filament Flow includes a multi-layered caching system designed to eliminate redundant database queries during page renders and workflow operations. On a typical List page with 50 records, the caching layer reduces queries from ~180 to ~5.

## Caching System

Caching is enabled by default and configured under the `cache` key in `config/filament-flow.php`:

```php
'cache' => [
    'enabled'    => true,
    'store'      => null,    // null = default Laravel cache store
    'ttl'        => 300,     // seconds (legacy, prefer safety_ttl)
    'safety_ttl' => 86400,   // 24h safety net — cache lives until invalidated by observers
    'prefix'     => 'filament-flow',
],
```

Key changes from previous versions:

- **`safety_ttl`**: Long-lived cache (24 hours). Not relied upon for freshness — the observer system invalidates the cache immediately when workflow configuration changes.
- **Event-driven invalidation**: Cache entries live as long as no admin modifies the workflow configuration. The `WorkflowCacheObserver` invalidates the relevant cache tags instantly on any save/delete event.
- **Store agnostic**: Works with Redis (recommended), Memcached, File, and Database. Redis uses native cache tags; other stores use an automatic key-registry fallback.

Set `enabled` to `false` to disable all caching — useful during local development when workflow configuration changes frequently.

### Choosing a Cache Store

```bash
# Recommended: Redis (native cache tags, atomic invalidation)
CACHE_STORE=redis

# Alternative: Memcached (key-registry fallback)
CACHE_STORE=memcached

# Development fallback: File
CACHE_STORE=file
```

**Redis is the recommended choice** for production because it supports native cache tags, allowing granular and atomic invalidation in a single operation.

## Architecture

### WorkflowCacheManager

The central cache abstraction (`RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager`). It wraps Laravel's `Cache` facade and provides:

- `remember(key, ttl, callback, tags)` — cache with tag association
- `flushTags(tags)` — invalidate all entries associated with given tags
- Automatic detection of tag support on the configured store

| Store | Tag Support | Invalidation Strategy |
|---|---|---|
| Redis | Native `Cache::tags()` | Single `flush()` call |
| Memcached, File, Database | Key-registry fallback | Reads registry → `forget()` per key |

The key-registry fallback maintains a mapping key (`{prefix}:__tag_registry:workflow:5`) containing all cache keys associated with a tag. When a tag is flushed, each key is individually forgotten. The `safety_ttl` ensures orphaned keys (from LRU eviction of the registry) eventually expire.

### Cache Tags

Cache entries are grouped by tags for selective invalidation:

| Tag | Contents |
|---|---|
| `workflow:{id}` | Workflow lookups, `findForModel` results |
| `workflow-states:{id}` | State metadata, transition configs |
| `workflow-access:{stateId}` | Access rules per state |
| `workflow-fields:{id}` | Field permission maps |

### In-memory Cache (Request-level)

Two in-memory caches prevent repeated queries within the same PHP request:

- **`WorkflowStateMemoryCache`**: Caches `WorkflowState::where(...)` lookups. Eliminates up to 9 repeated queries during a single transition.
- **`StateColumn::$metadataCache`**: Caches `StateService::getStateMetadata()` results per table row, reducing 3 identical queries to 1 per row.

Both self-clear at the end of each request (no manual intervention needed).

### Cached Methods

| Service / Model | Method | Invalidation Tag |
|---|---|---|
| `Workflow` | `findForModel()` | `workflow:{id}` |
| `Workflow` | `initialState()` | `workflow-states:{id}` |
| `StateService` | `getStateMetadata()` | `workflow-states:{id}` |
| `StateService` | `getDatabaseStates()` | `workflow-states:{id}` |
| `StateService` | `getInitialState()` | `workflow-states:{id}` |
| `WorkflowFieldPermissionsService` | `getFieldPermissions()` | `workflow-fields:{id}` |
| `WorkflowFieldPermissionsService` | `getCreationFieldPermissions()` | `workflow-fields:{id}` |
| `WorkflowFieldPermissionsService` | `getTableColumnPermissions()` | `workflow-fields:{id}` |
| `TransitionFormService` | `getTransitionConfig()` | `workflow-states:{id}` |
| `WorkflowStateAccessService` | `checkDatabaseRules()` | `workflow-access:{stateId}` |
| `WorkflowStateAccessService` | `findWorkflowState()` | `workflow-states:{id}` |
| `WorkflowStateAccessService` | `categorizeAccessibleStates()` | `workflow-access:{id}` |
| `WorkflowStateAccessService` | `getAccessibleStates()` | `workflow-access:{id}` |

## Automatic Cache Invalidation

The `WorkflowCacheObserver` is registered on **10 models**:

- `Workflow`
- `WorkflowState`
- `WorkflowTransition`
- `WorkflowStateAccessRule`
- `WorkflowStateField`
- `WorkflowStateFieldRole`
- `WorkflowTransitionField`
- `WorkflowTransitionPermission`
- `WorkflowTransitionValidationRule`
- `WorkflowTransitionSideEffect`

Any `saved` or `deleted` event on these models triggers immediate cache invalidation for the relevant tags. You do not need to clear the cache manually after making changes through the Filament admin UI or via Eloquent.

| Admin Action | Tags Invalidated |
|---|---|
| Edit a Workflow | `workflow:{id}` |
| Add/edit a State | `workflow:{id}`, `workflow-states:{id}`, `workflow-access:{stateId}`, `workflow-fields:{id}` |
| Edit a Transition | `workflow:{id}`, `workflow-states:{id}` |
| Change Access Rules / Field Permissions | `workflow:{id}`, `workflow-access:{stateId}`, `workflow-fields:{id}` |

Cache invalidation is bypassed when records are updated with raw SQL queries (e.g., `DB::table('workflows')->update([...])`).

## Manual Cache Flush

To flush all Filament Flow cache entries:

```bash
php artisan cache:clear
```

Or programmatically:

```php
use RoBYCoNTe\FilamentFlow\Models\Workflow;

Workflow::flushCache();
```

## N+1 Prevention

Filament Flow includes several optimizations to prevent N+1 query patterns:

### StateColumn

The `StateColumn` renders three properties per row (label, badge color, icon), each calling `StateService::getStateMetadata()`. Results are now cached in-memory per unique `(modelClass, stateColumn, stateName)`, reducing 3 queries per row to 1.

### Transition Form Data

When `getAvailableTransitions()` and `getAvailableActions()` are called on the same page render, the underlying `getWorkflowState()` method caches state lookups in-memory, avoiding redundant DB queries.

### Eager Loading for Assignments

When displaying `AssignmentSummaryColumn` in a table, eager-load the `user` relationship on each assignment:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with('assignments.user');
}
```

Limit the number of avatars rendered:

```php
AssignmentSummaryColumn::make('assignments')
    ->avatarLimit(3),
```

## Estimated Impact

| Scenario | Queries Before | Queries After | Reduction |
|---|---|---|---|
| List page (50 records) | ~180 | ~5 | **97%** |
| Edit page (5 actions) | ~40 | ~4 | **90%** |
| Single transition | ~25 | ~8 | **68%** |
| Create page | ~15 | ~3 | **80%** |

## Queue Configuration for Notifications

Notification jobs are dispatched asynchronously using Laravel queues:

```php
'notifications' => [
    'queue_connection' => env('QUEUE_CONNECTION', 'database'),
    'queue_name'       => 'notifications',
    'retry_attempts'   => 3,
    'retry_backoff'    => 60, // seconds
],
```

Ensure a queue worker is running and processing the configured queue:

```bash
php artisan queue:work --queue=notifications
```

In production, use a process supervisor (Supervisor, systemd) to keep the worker running continuously.

## Scheduled Checks Chunking

`ScheduledCheckRunner` processes records in chunks of 100 to avoid memory exhaustion when evaluating scheduled checks across large datasets. No configuration is required; chunking is applied automatically.

## Recommended Production Configuration

```php
'cache' => [
    'enabled'    => true,
    'store'      => 'redis',
    'safety_ttl'  => 86400,
    'prefix'     => 'filament-flow',
],

'notifications' => [
    'queue_connection' => 'redis',
    'queue_name'       => 'notifications',
    'retry_attempts'   => 3,
    'retry_backoff'    => 60,
],

'scheduling' => [
    'enabled'   => true,
    'frequency' => 'everyFiveMinutes',
],
```

Key points:

- Use Redis as the cache store for native tag support and atomic invalidation.
- The `safety_ttl` of 24 hours is a fallback — the observer invalidates cache entries immediately on config changes.
- Always run queue workers in production to avoid blocking user requests with notification dispatches.
