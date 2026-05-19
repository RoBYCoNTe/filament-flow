# Performance & Caching

## Caching System

Filament Flow caches workflow lookups, access rules, and state resolution using Laravel's cache system. Caching is enabled by default and is configured under the `cache` key in `config/filament-flow.php`:

```php
'cache' => [
    'enabled' => true,
    'store'   => null,    // null = default cache store
    'ttl'     => 300,     // seconds
    'prefix'  => 'filament-flow',
],
```

Cache keys follow the pattern `{prefix}:workflow:{modelClass}:{stateColumn}:{tenantId}`. Setting `store` to a specific cache driver name (e.g. `'redis'`) will isolate Filament Flow's cache from the default store.

Set `enabled` to `false` to disable all caching. This is useful during local development when workflow configuration changes frequently.

## Automatic Cache Invalidation

A `WorkflowCacheObserver` is registered on the following models:

- `Workflow`
- `WorkflowState`
- `WorkflowStateAccessRule`
- `WorkflowStateField`

Any `saved` or `deleted` event on these models triggers cache invalidation automatically. You do not need to clear the cache manually after making changes through the Filament admin UI or via Eloquent.

Cache invalidation is bypassed when records are updated with raw SQL queries (e.g. `DB::table('workflows')->update([...])`). In those cases, flush the cache manually.

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

When displaying `AssignmentSummaryColumn` in a table, the column eager-loads the `user` relationship on each assignment. For large record lists this can generate many queries if assignments are not pre-loaded.

Add eager loading to your resource's query:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with('assignments.user');
}
```

If you display avatar stacks, limit the number of avatars rendered to reduce the amount of data fetched per row:

```php
AssignmentSummaryColumn::make('assignments')
    ->avatarLimit(3),
```

## Queue Configuration for Notifications

Notification jobs are dispatched asynchronously using Laravel queues. Configure the queue connection and name in `config/filament-flow.php`:

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
    'enabled' => true,
    'store'   => 'redis',   // use a dedicated Redis connection
    'ttl'     => 300,
    'prefix'  => 'filament-flow',
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

- Use Redis as the cache store for fast invalidation and support for the `flush()` operation.
- Always run queue workers in production to avoid blocking user requests with notification dispatches.
- Keep the cache TTL at 300 seconds (5 minutes) unless your workflow configuration changes very frequently.
