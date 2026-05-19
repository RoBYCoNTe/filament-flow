# Transition Conditions & In-State Actions

## Transition Conditions

Transition conditions are JSON-encoded rules attached to a `WorkflowTransition` record. Before a transition is executed, the `ConditionEvaluator` service evaluates all conditions against the current record. If any condition is not met, the transition is blocked and a `ConditionNotMetException` is thrown.

Conditions are evaluated automatically — you do not need to call `ConditionEvaluator` directly in application code.

### Supported Operators

| Operator | Description |
|---|---|
| `=` | Equals (loose comparison) |
| `!=` | Not equals |
| `>` | Greater than |
| `<` | Less than |
| `>=` | Greater than or equal |
| `<=` | Less than or equal |
| `in` | Value is in an array |
| `not_in` | Value is not in an array |
| `is_null` | Field is null |
| `is_not_null` | Field is not null |
| `contains` | String field contains the substring |

### Dot-Notation Field Access

The `field` key supports dot notation to traverse Eloquent relationships. The evaluator walks the chain of property accesses and returns `null` if any segment is `null`.

```
"field": "customer.status"
// resolves to $record->customer->status
```

```
"field": "assignmentType.name"
// resolves to $record->assignmentType->name
```

### Condition JSON Structure

Each condition is an object with `field`, `operator`, and `value` keys. Multiple conditions are stored as a JSON array and evaluated with AND logic — all conditions must pass for the transition to be allowed.

```json
[
    {"field": "amount", "operator": ">=", "value": 100},
    {"field": "customer.verified", "operator": "=", "value": true}
]
```

```json
[
    {"field": "status", "operator": "in", "value": ["open", "pending"]},
    {"field": "reviewed_at", "operator": "is_not_null"}
]
```

### Exception on Failure

When a condition is not met, the system throws:

```php
RoBYCoNTe\FilamentFlow\Exceptions\ConditionNotMetException
```

The exception message includes the transition name (or action name) so you can present a meaningful error to the user.

```php
use RoBYCoNTe\FilamentFlow\Exceptions\ConditionNotMetException;

try {
    $order->transitionTo('approved');
} catch (ConditionNotMetException $e) {
    // $e->getMessage() contains the transition/action name
}
```

### Using ConditionEvaluator Directly

If you need to evaluate conditions programmatically outside of a transition, inject or resolve `ConditionEvaluator` from the container:

```php
use RoBYCoNTe\FilamentFlow\Services\ConditionEvaluator;

$conditions = [
    ['field' => 'amount', 'operator' => '>=', 'value' => 100],
];

$evaluator = app(ConditionEvaluator::class);
$passed = $evaluator->evaluate($order, $conditions); // true or false
```

---

## In-State Actions

In-state actions are transitions where `to_state_id` is `null`. They execute workflow logic — log history, run side effects, trigger notifications, collect form data — without moving the record to a new state.

### Use Cases

- "Add Review Note" — attach a note to a claim without changing its state
- "Request Additional Information" — trigger a notification and log the request
- "Approve Section" — mark part of a record as approved while it remains in `under_review`
- "Escalate" — set a flag or notify a manager without changing the main state

### Available Methods

These methods are available on any model using the `HasDatabaseTransitions` trait.

**`getAvailableActions(string $field = 'state'): Collection`**

Returns a `Collection` of `WorkflowTransition` records where `to_state_id` is `null` and the current user has permission to execute them and all conditions pass.

**`executeAction(string $transitionName, array $data = [], string $field = 'state'): static`**

Executes an in-state action by its `name`. Applies any form data, logs the transition history (from and to state are the same), runs side effects, triggers notifications, and dispatches `TransitionCompleted`.

### Example

```php
// Get all in-state actions available in the record's current state
$actions = $claim->getAvailableActions();
// Returns Collection<WorkflowTransition> where to_state_id = null

// Execute an in-state action
$claim->asUser(auth()->user())->executeAction('add_review_note', [
    'notes' => 'Documentation looks complete, pending final sign-off.',
]);
```

### Difference from `getAvailableTransitions()`

| Method | Returns | Changes state? |
|---|---|---|
| `getAvailableTransitions()` | Transitions where `to_state_id` is not null | Yes |
| `getAvailableActions()` | Transitions where `to_state_id` is null | No |

Both methods filter by the current state, evaluate conditions, and check transition-level permissions before returning results.

### Forms, Conditions, Side Effects, and Notifications

In-state actions support the same configuration as state-changing transitions:

- **Forms**: define fields on the transition to collect data when the action is executed
- **Conditions**: block the action unless specific field values are met
- **Side effects**: automatically mutate the model (e.g. set a timestamp, increment a counter) when the action runs
- **Notifications**: send database or email notifications to configured recipients

See the relevant documentation pages for details on each feature.
