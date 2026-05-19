# Scheduled Checks

Scheduled checks are periodic automated checks that run against your workflow records. When a check's condition is met, it executes a configured action — such as sending a notification, forcing a state transition, or running a side effect.

## Use Cases

- Send a reminder if a claim has been in `pending` for more than 3 days
- Auto-close records with no activity after 30 days
- Escalate a case if `amount > 10000` and the record is still in `review`
- Notify a manager when a record's due date is approaching

## Enabling Scheduled Checks

Set `scheduling.enabled = true` in `config/filament-flow.php`. The package registers the `workflow:process-schedules` command in Laravel's scheduler automatically.

```php
'scheduling' => [
    'enabled' => true,

    /**
     * How often to run the command.
     * Available: everyMinute, everyFiveMinutes, everyTenMinutes,
     *            everyFifteenMinutes, everyThirtyMinutes, hourly, daily, weekly
     */
    'frequency' => 'everyFiveMinutes',
],
```

Make sure Laravel's scheduler is running on your server:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Condition Types

Each scheduled check has a `condition_type` that determines how the check decides whether to trigger for a given record.

### `date_offset`

Triggers when a date field on the record is a certain number of days before or after the current time.

```json
{
    "field": "created_at",
    "offset_days": 3,
    "operator": "<="
}
```

The check computes `record.field + offset_days` and compares it against `now()` using the specified operator. A negative `offset_days` means "before" the date field.

| Config key | Type | Description |
|---|---|---|
| `field` | string | The date field on the model |
| `offset_days` | int | Days to add (negative = subtract) |
| `operator` | string | `<=`, `>=`, `=`, `<`, `>` |

**Example — trigger if `created_at` is more than 3 days ago:**

```json
{"field": "created_at", "offset_days": 3, "operator": "<="}
```

### `field_compare`

Uses `ConditionEvaluator` to check any field on the model against a value. Supports all the same operators as transition conditions (see [Transition Conditions](./conditions.md)).

```json
{
    "conditions": [
        {"field": "amount", "operator": ">", "value": 10000},
        {"field": "reviewed_at", "operator": "is_null"}
    ]
}
```

Multiple conditions use AND logic.

### `custom_class`

Delegates evaluation to a custom PHP class. The class must have a public `evaluate(Model $record): bool` method. It is resolved from the service container.

```json
{"class": "App\\Checks\\OrderEscalationCondition"}
```

```php
namespace App\Checks;

use Illuminate\Database\Eloquent\Model;

class OrderEscalationCondition
{
    public function evaluate(Model $record): bool
    {
        return $record->amount > 10000
            && $record->escalated_at === null
            && $record->created_at->diffInDays() > 7;
    }
}
```

## Action Types

When a condition is met, the check executes one of the following action types.

### `notification`

Triggers a workflow notification by its database ID.

```json
{"notification_id": 5}
```

The notification must be configured in the `workflow_notifications` table (see [Notifications](./notifications.md)).

### `transition`

Forces the record to transition to a new state. Defaults to using `forceTransitionTo()` to bypass access control.

```json
{"to_state": "closed", "force": true}
```

| Config key | Type | Description |
|---|---|---|
| `to_state` | string | Target state name or class |
| `force` | bool | If true, uses `forceTransitionTo()` (default: true) |

### `side_effect`

Executes the side effects configured on an existing `WorkflowTransition` record.

```json
{"transition_id": 10}
```

This re-uses the side effect configuration of an existing transition without actually changing state.

## `once_per_record`

When `once_per_record` is `true`, the check only triggers for each record once. Execution history is tracked in `workflow_scheduled_check_logs`. If a `triggered` log entry already exists for the record, the check is skipped and logged as `already_executed`.

This is useful for one-time reminders or escalations that should not repeat.

## Scoping to a State (`state_id`)

Set `state_id` on the check to scope it to records currently in that specific workflow state. Records in other states will not be evaluated.

## Programmatic Usage

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;

$check = WorkflowScheduledCheck::find($id);

// Check if the check is due to run based on its frequency
$check->isDue(); // bool

// Check if the check has already triggered for a specific record
$check->hasAlreadyExecutedFor(Order::class, $orderId); // bool
```

## Manual Execution

Run all active, due scheduled checks immediately:

```bash
php artisan workflow:process-schedules
```

Output:

```
Processing workflow scheduled checks...
Processed: 42 records
Triggered: 3 actions
Done.
```

If any checks fail, the error count is shown and exceptions are reported via Laravel's exception handler without aborting the run.

## Monitoring

Every check execution is logged to the `workflow_scheduled_check_logs` table with the following result values:

| Result | Description |
|---|---|
| `triggered` | The condition was met and the action was executed |
| `skipped` | The condition was not met for this record |
| `already_executed` | `once_per_record` is true and this record was already triggered |
| `error` | An exception occurred during evaluation or execution |

Access logs via the relationship:

```php
$check->logs()->where('result', 'triggered')->get();
```

Each log entry stores `model_type`, `model_id`, `result`, `metadata` (error details on failure), and `executed_at`.
