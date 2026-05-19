# Transition Side Effects

Side effects are automatic model mutations that execute after a successful state transition. They are configured on `WorkflowTransition` records and stored in the `workflow_transition_side_effects` table. Once configured, they run automatically — no application code is required.

Side effects run for both state-changing transitions and in-state actions.

## How They Work

After the state is updated and the transition is logged, `SideEffectExecutor` loads all active side effects for the transition (ordered by `sort_order`) and applies them to the model. If any side effect modifies the model, the changes are persisted using `saveQuietly()` to avoid triggering model observers.

Individual side effect errors are reported and do not abort the remaining side effects or the transition itself.

## Effect Types

### `set_field`

Sets a model field to a literal value or copies it from another field.

| `field_name` | `value_expression` | Behaviour |
|---|---|---|
| `status_label` | `"Approved"` | Sets the field to the string `"Approved"` |
| `processed_by` | `"field:user_id"` | Copies the value of `user_id` into `processed_by` |

Use the `field:` prefix in `value_expression` to reference another model field.

**Example configuration:**

```
field_name:       status_label
value_expression: Approved
```

```
field_name:       processed_by
value_expression: field:user_id
```

### `set_timestamp`

Sets a datetime field to the current time (`now()`).

```
field_name:       approved_at
value_expression: (leave empty or set to "now")
```

### `clear_field`

Sets a field to `null`.

```
field_name:       rejection_reason
```

Use this to clear data that is no longer relevant after a transition (e.g. clear a rejection reason when a record is re-approved).

### `increment`

Increments a numeric field by a given amount. Defaults to `1` if `value_expression` is empty.

```
field_name:       revision_count
value_expression: 1
```

If the field is currently `null`, it is treated as `0` before incrementing.

### `custom_class`

Delegates execution to a custom PHP class. The class is resolved from the service container and must implement a public `execute(Model $model): void` method.

```
value_expression: App\WorkflowEffects\NotifySlack
```

```php
namespace App\WorkflowEffects;

use Illuminate\Database\Eloquent\Model;

class NotifySlack
{
    public function execute(Model $model): void
    {
        \Illuminate\Support\Facades\Http::post(config('services.slack.webhook'), [
            'text' => "Order #{$model->id} has been approved.",
        ]);
    }
}
```

> The `custom_class` effect does not call `saveQuietly()` automatically. If your class modifies the model, call `$model->saveQuietly()` inside `execute()`.

## Multiple Side Effects per Transition

A single transition can have multiple side effects. They are executed in ascending `sort_order`. All active side effects for the transition are run before saving.

**Example — "approve" transition (pending to approved):**

| Sort | Effect Type | Field Name | Value Expression |
|---|---|---|---|
| 1 | `set_timestamp` | `approved_at` | |
| 2 | `set_field` | `status_label` | `Approved` |
| 3 | `increment` | `approval_count` | `1` |
| 4 | `clear_field` | `rejection_reason` | |

## Database Schema

Side effects are stored in `workflow_transition_side_effects` with these columns:

| Column | Type | Description |
|---|---|---|
| `transition_id` | foreign key | The parent `WorkflowTransition` |
| `effect_type` | string | One of the types listed above |
| `field_name` | string | The model field to modify |
| `value_expression` | string (nullable) | Literal value, `field:source`, or class name |
| `sort_order` | integer | Execution order (ascending) |
| `is_active` | boolean | Only active effects are executed |
