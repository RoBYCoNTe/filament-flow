# Artisan Commands

Filament Flow registers three Artisan commands. Two are prefixed with `filament-flow:` and one with `workflow:`.

## filament-flow:list

List all registered workflows with their state and transition counts.

**Signature:**

```bash
php artisan filament-flow:list
```

**Output:**

```
+----+---------------+------------------+-----------+--------+--------+-------------+
| ID | Name          | Model Class      | Tenant ID | Active | States | Transitions |
+----+---------------+------------------+-----------+--------+--------+-------------+
| 1  | Order         | App\Models\Order | -         | Yes    | 5      | 6           |
| 2  | Invoice       | App\Models\Invoice| -         | Yes    | 3      | 2           |
+----+---------------+------------------+-----------+--------+--------+-------------+
```

When no workflows exist, it prints `No workflows found.` and exits with code `0`.

**When to use it:**

Run this command to quickly verify that your workflows are registered correctly in the database, especially after running migrations or seeding workflows for the first time. The `Tenant ID` column shows `-` for global workflows.

---

## filament-flow:sync-states

Sync PHP State classes into `WorkflowState` database records.

**Signature:**

```bash
php artisan filament-flow:sync-states [--workflow=name]
```

**Options:**

| Option | Description |
|---|---|
| `--workflow=name` | Sync only the workflow whose `name` column matches this value. Omit to sync all workflows. |

**How it works:**

1. Queries active `Workflow` records (filtered by `--workflow` if provided).
2. For each workflow, inspects the model class cast on the `state_column` to find the Spatie `State` base class.
3. Discovers concrete (non-abstract) subclasses of that base class, first from `StateConfig::registeredStates`, then by scanning the directory of the base class file.
4. For each discovered state, creates or updates a `WorkflowState` record with `name` (morph class), `label` (auto-derived from the class name), `class_name`, and `is_initial`.

Existing records are updated only when `class_name` or `is_initial` has changed. Unchanged records are left as-is.

**Output:**

```
Syncing workflow: Order (model: App\Models\Order)
  Created: pending (label: Pending)
  Created: processing (label: Processing)
  Created: shipped (label: Shipped)
  Created: delivered (label: Delivered)
  Created: cancelled (label: Cancelled)

Sync complete: 5 created, 0 updated.
```

**When to use it:**

Run this command after:

- Adding new concrete state classes to an existing state machine.
- Renaming the morph class of an existing state.
- Setting up a fresh environment (CI, staging, production) that has PHP classes but no `WorkflowState` rows yet.

It is safe to run repeatedly — records that have not changed are reported as unchanged and left untouched.

---

## workflow:process-schedules

Process all active, due workflow scheduled checks and trigger their configured actions.

**Signature:**

```bash
php artisan workflow:process-schedules
```

**Output:**

```
Processing workflow scheduled checks...
Processed: 42 records
Triggered: 7 actions
Done.
```

If any checks fail, a warning line is added:

```
Errors: 3
```

Errors are also reported via Laravel's `report()` helper, so they appear in your log driver and any error-tracking integrations.

**When to use it:**

This command is registered automatically in the Laravel scheduler at the frequency defined by `config('filament-flow.scheduling.frequency')` (default: `everyFiveMinutes`). You do not need to add it to your `routes/console.php` manually.

To run it on demand — for example during local development or to replay missed checks — call it directly:

```bash
php artisan workflow:process-schedules
```

The command processes records in chunks of 100 to avoid memory exhaustion on large datasets.

**Disabling automatic scheduling:**

Set `scheduling.enabled` to `false` in `config/filament-flow.php` to prevent the ServiceProvider from registering the command in the scheduler:

```php
'scheduling' => [
    'enabled' => false,
    'frequency' => 'everyFiveMinutes',
],
```
