# Troubleshooting

## Tailwind Classes Not Applied

Package views are not automatically scanned by Tailwind CSS v4. If buttons, badges, or status indicators appear unstyled, add `@source` directives to your panel's `theme.css`:

```css
@source '../../../../vendor/robyconte/filament-flow/resources/views/**/*';
@source '../../../../vendor/robyconte/filament-flow/src/**/*.php';
```

Adjust the relative path to match the location of your `theme.css` file relative to the `vendor` directory. After saving, rebuild your assets:

```bash
npm run build
```

See also [Frontend Integration (Tailwind CSS)](/guide/installation#frontend-integration-tailwind-css).

---

## RelationNotFoundException: roles

**Symptom:** An exception like `Call to undefined relationship [roles] on model [App\Models\User]` when loading a resource page.

**Cause:** The package conditionally eager-loads role data when it detects that the user model has a `getRoleNames()` method (provided by Spatie Laravel Permission). If your test `User` model does not implement this method but something in the request chain still attempts to load a `roles` relation, the exception is thrown.

**Fix:**

- If you use Spatie Laravel Permission, ensure the `HasRoles` trait is added to your `User` model.
- If you do not use Spatie Laravel Permission, implement `getRoleNames()` on your `User` model returning an empty collection:

```php
public function getRoleNames(): \Illuminate\Support\Collection
{
    return collect();
}
```

- Check that no custom query scope in your resource adds `.with('user.roles')` when the relationship does not exist.

---

## Vendor Symlink Pointing to Wrong Path

**Symptom:** Changes to the package source are not reflected, or you see `Class not found` errors for package classes after changing your ddev/Docker volume mount configuration.

**Cause:** The symlink at `vendor/robyconte/filament-flow` was created by a previous Composer install and points to a stale path.

**Fix:**

```bash
rm vendor/robyconte/filament-flow
composer install
```

---

## State Transition Not Firing Notifications

**Cause:** Notifications require both the `notifications.enabled` config to be `true` and the workflow record to be active.

**Checklist:**

1. Verify `config('filament-flow.notifications.enabled')` is `true`.
2. Check the `workflows` table: `is_active` must be `1` for the relevant workflow row.
3. If using queued notifications, confirm a queue worker is running and processing the correct queue.

---

## WorkflowNotFoundException

**Symptom:** A `WorkflowNotFoundException` is thrown when a model is created or a transition is attempted.

**Cause:** No active workflow exists in the database for the given model class.

**Checklist:**

1. Confirm the `workflows` table contains a row with `model_type` equal to the fully qualified class name of your model (e.g. `App\Models\Order`).
2. Confirm `is_active = 1` on that row.
3. If using multi-tenancy, confirm the workflow either has the correct `tenant_id` or is a global workflow (`tenant_id = null`).
4. Run `php artisan filament-flow:list` to see all registered workflows and their active status.

---

## Scheduled Checks Not Running

**Checklist:**

1. Confirm `config('filament-flow.scheduling.enabled')` is `true`. When `false`, the ServiceProvider does not register `workflow:process-schedules` in the Laravel scheduler.
2. Verify your server cron executes `php artisan schedule:run` every minute.
3. Run the command manually to test it in isolation:

```bash
php artisan workflow:process-schedules
```

4. Check Laravel's scheduler log or `storage/logs/laravel.log` for errors.

---

## Cache Stale After Workflow Changes

The package automatically invalidates cached workflow lookups when `Workflow`, `WorkflowState`, `WorkflowStateAccessRule`, or `WorkflowStateField` models are saved or deleted via Eloquent observers. If you update records directly with raw SQL queries (bypassing Eloquent), the cache will not be invalidated.

**Manual flush options:**

```bash
php artisan cache:clear
```

Or programmatically for a specific workflow:

```php
use RoBYCoNTe\FilamentFlow\Models\Workflow;

Workflow::flushCache();
```

Note: `flushCache()` calls `Cache::store($store)->flush()` on the configured cache store. On stores that do not support flush (e.g. some Redis configurations with shared keyspace), individual keys will expire naturally via the configured TTL.
