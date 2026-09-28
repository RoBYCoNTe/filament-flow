# Workflow Definition SDK

A typed, fluent way to describe a workflow in code: states, transitions, per-state
field rules, side effects and validation rules. The definition is a pure value
object — no database access — so it can be created anywhere (seeders, tests, a
console command, an AI agent) and serialized to/from an array.

```php
use RoBYCoNTe\FilamentFlow\Definition\AccessRule;
use RoBYCoNTe\FilamentFlow\Definition\Notification;
use RoBYCoNTe\FilamentFlow\Definition\Recipient;
use RoBYCoNTe\FilamentFlow\Definition\ScheduledCheck;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\StateField;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\ValidationRule;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;

$workflow = WorkflowDefinition::make('order', Order::class)
    ->stateColumn('state')
    ->state(State::make('draft', 'Draft')->initial()->color('gray'))
    ->state(
        State::make('under_review', 'Under review')->color('warning')->fields([
            StateField::make('costs')->locked(),
        ])
    )
    ->state(State::make('paid', 'Paid')->final()->color('success'))
    ->transition(
        Transition::make('pay', 'draft', 'paid')
            ->label('Pay')
            ->confirm()
            ->formulaCondition('total > 0', 'Total must be positive.')
            ->sideEffect(SideEffect::setTimestamp('meta.paid_at', 'now'))
            ->validationRule(ValidationRule::make('score')->rules(['gte:60'])->message('Min 60.'))
    );
```

## WorkflowDefinition

`WorkflowDefinition::make(string $name, string $modelType)`.

| Method | Description |
|---|---|
| `stateColumn(string)` | Column on the model holding the state (default `state`) |
| `active(bool)` | Whether the workflow is active |
| `creationPolicy(array)` / `autoAssignCreator(bool, string)` | Record creation policy |
| `metadata(array)` | Arbitrary extra data |
| `state(State)` / `states(array)` | Add state(s) |
| `transition(Transition)` / `transitions(array)` | Add transition(s) |
| `scheduledCheck(ScheduledCheck)` / `scheduledChecks(array)` | Add scheduled check(s) |
| `notification(Notification)` / `notifications(array)` | Add workflow-level notification(s) |
| `stateByName(string): ?State` | Look up a state by name |
| `getName()`, `getModelType()`, `getStateColumn()`, `isActive()`, `getCreationPolicy()`, `getMetadata()`, `getStates()`, `getTransitions()`, `getScheduledChecks()`, `getNotifications()` | Accessors |
| `toArray()` / `fromArray(array)` | Serialization round-trip |

## States

`State::make(string $name, ?string $label = null)`.

`initial()` · `final()` · `color(string)` · `icon(string)` · `description(string)` ·
`sort(int)` · `metadata(array)` · `field(StateField)` · `fields(array)` ·
`accessRule(AccessRule)` · `accessRules(array)` · `notification(Notification)` ·
`onExitNotification(Notification)` · `notifications(array)`.

`notification()` forces the trigger to `on_state_enter`, `onExitNotification()` to
`on_state_exit`.

## State fields (per-state visibility & mutability)

`StateField::make(string $fieldName)`.

| Method | Effect |
|---|---|
| `visible()` / `hidden()` | Visibility in the state |
| `editable()` / `readonly()` / `locked()` | Mutability in the state |
| `required(bool)` | Required in the state |
| `validationRules(array)` | Extra validation rules |
| `sort(int)` | Display order |
| `forRole(string, Closure)` | Role-specific refinement (see below) |

The field name may be a **dotted path**, so the same mechanism targets the
columns of a repeater or spreadsheet field: `StateField::make('costs.amount')`.
A rule on an ancestor also applies to its descendants (`costs` → `costs.amount`)
and the most specific rule wins attribute by attribute; see
[access control](/workflows/access-control#nested-field-paths).

### Role overrides

`forRole()` refines the rule for one role. The callback receives the
`RoleOverride`, only the attributes set there are persisted (`null` means "no
override, keep the state field value") and the chain keeps returning the
`StateField`, so it never changes type:

```php
StateField::make('costs.amount')
    ->locked()
    ->forRole('reviewer', fn (RoleOverride $role) => $role->editable())
    ->forRole('admin', fn (RoleOverride $role) => $role->visible()->required());
```

At runtime the last matching role wins
(`WorkflowFieldPermissionsService`). Removing a role override is a `Safe`
change; the overrides are part of the revision snapshot and of the export.

## Transitions

`Transition::make(string $name, ?string $from, ?string $to = null)` creates a state
transition. `Transition::action(string $name, ?string $label = null)` creates an
in-state action (no state change: `from()` and `to()` are `null`).

| Method | Description |
|---|---|
| `label(string)` / `description(string)` | Display text |
| `confirm(bool)` / `requiresReason(bool)` | Confirmation / reason requirement |
| `condition(array)` / `conditions(array)` | Field conditions |
| `formulaCondition(string $expression, ?string $message)` | Formula guard (ExpressionLanguage) |
| `sideEffect(SideEffect)` / `sideEffects(array)` | Side effects executed on transition |
| `validationRule(ValidationRule)` / `validationRules(array)` | Validation rules |
| `notification(Notification)` / `notifications(array)` | Notifications on `on_transition` |
| `metadata(array)` | Arbitrary extra data |

## Side effects

| Factory | `effect_type` |
|---|---|
| `SideEffect::setField($field, $expression)` | `set_field` |
| `SideEffect::setTimestamp($field, 'now')` | `set_timestamp` |
| `SideEffect::clearField($field)` | `clear_field` |
| `SideEffect::increment($field, $amount)` | `increment` |
| `SideEffect::customClass($className)` | `custom_class` |
| `SideEffect::createChildApplication(array $config)` | `create_child_application` |

Chainable: `->forField(string)`, `->value(?string)`, `->sort(int)`, `->active(bool)`.

## Validation rules

`ValidationRule::make(string $fieldName)` · `rules(array|string)` · `message(string)` · `sort(int)`.

## Scheduled checks

`ScheduledCheck::make(string $name, ?string $label = null)` describes *when* a
condition is evaluated, *how often*, and *what* happens when it matches.

```php
use RoBYCoNTe\FilamentFlow\Definition\ScheduledCheck;

$check = ScheduledCheck::make('overdue', 'Overdue reminder')
    ->state('under_review')          // only records in this state
    ->daily()                        // everyMinute()/everyFiveMinutes()/hourly()/daily()/weekly()
    ->oncePerRecord()
    ->whenDateOffset('due_date', -2, '<=')
    ->thenNotificationNamed('review-reminder');
```

| Conditions | Actions |
|---|---|
| `whenDateOffset($field, $offsetDays = 0, $operator = '<=')` | `thenNotification(int $id)` |
| `whenFieldCompare(array $conditions)` | `thenNotificationNamed(string $name)` |
| `whenCustomClass(string $class)` | `thenTransition(string $toState, bool $force = true)` |
| | `thenSideEffect(string $transitionName)` |

`oncePerRecord(bool)` · `active(bool)`, plus the frequency shortcuts above.
Actions can also be defined generically with
`condition(ScheduledCheckCondition, array)` / `action(ScheduledCheckAction, array)`.

> Names (`state`, `notification_name`, `transition_name`) are resolved inside the
> workflow at run time, so a definition stays valid across revisions.

## Access rules

`AccessRule` is attached to a **state** and controls `view`, `edit`, `transition`
and `create`.

```php
use RoBYCoNTe\FilamentFlow\Definition\AccessRule;

$state = State::make('under_review', 'Under review')
    ->accessRule(AccessRule::view(AccessRule::ANY))
    ->accessRule(AccessRule::transition(AccessRule::role('reviewer')))
    ->accessRule(AccessRule::edit(AccessRule::permission('edit-orders'))->and()->priority(10));
```

| Factory | `access_type` |
|---|---|
| `AccessRule::view($rule = '*')` | `view` |
| `AccessRule::edit($rule = '*')` | `edit` |
| `AccessRule::transition($rule = '*')` | `transition` |
| `AccessRule::create($rule = '*')` | `create` |

Tokens: `AccessRule::ANY` (`*`), `::AUTHENTICATED`, `::ASSIGNED`, `::OWNER`,
`AccessRule::role('admin')`, `AccessRule::permission('approve')`. Chainable:
`operator(AccessOperator)` / `and()`, `priority(int)`, `active(bool)`, `metadata(array)`.

`AccessRule::key()` (`access_type|rule|operator`) is the stable identity used by
the planner and the applier.

## Notifications

`Notification::make(string $name, ?string $label = null)` declares recipients,
channels and the template used on every channel.

```php
use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationPriority;
use RoBYCoNTe\FilamentFlow\Definition\Notification;
use RoBYCoNTe\FilamentFlow\Definition\Recipient;

$notification = Notification::make('approved-notice', 'Approved')
    ->database()
    ->mail()
    ->recipient(Recipient::role('reviewer'))
    ->recipient(Recipient::recordOwner())
    ->subject('Order {{order_number}} approved')
    ->title('Approved')
    ->body('Your order was approved.')
    ->action('https://example.com/orders/{{id}}', 'Open')
    ->priority(NotificationPriority::High)
    ->delay(30);
```

- **Channels**: `database(array $config = [])`, `mail(...)`, `channel(NotificationChannel, ...)`.
  Defaults to a single `database` channel.
- **Recipients** (`Recipient`): `role(...$roles)`, `user(int|array)`, `triggerUser()`,
  `assignedUsers(array $types = [])`, `recordOwner(?string $field)`, `stateActors(array $states = [])`,
  `allInvolved()`, `involvementType(string)`, `customField(string)`, `customQuery(string, array)`,
  `customClass(string)`.
- **Timing**: `immediate()` (default) or `delay(int $minutes)`.
- **Template**: `subject`, `title`, `body`, `action($url, $text)`, `templateEngine(string)`,
  `format(string)`, `variables(array)`.

Attach it to a **transition**, a **state** or the **workflow** itself; the trigger
event is set by the attachment (`on_transition`, `on_state_enter`, `on_state_exit`).
For workflow-level notifications set it explicitly:

```php
WorkflowDefinition::make('order', Order::class)
    ->notification(Notification::make('assigned')->trigger(NotificationTrigger::OnAssignment)->database());
```

## Serialization

```php
$array = $workflow->toArray();

// Later, or in another process:
$workflow = WorkflowDefinition::fromArray($array);
```

The array is the stable exchange format used by the CLI and by agents.

---


## Planning & conflicts

`WorkflowPlanner::plan()` diffs a definition against the persisted workflow
**without touching the database** (safe for previews and agents):

```php
use RoBYCoNTe\FilamentFlow\Definition\Planning\PlanOptions;
use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowPlanner;

$plan = (new WorkflowPlanner)->plan($definition, $existingWorkflow);

$plan->isClean();          // no changes at all
$plan->isCreate();         // the workflow does not exist yet
$plan->isApplicable();     // no blocking conflicts
$plan->mutationClass();    // Safe | Additive | Breaking | null
$plan->changes;            // list<WorkflowChange>
$plan->conflicts;          // list<array{code, message, key}>
$plan->toArray();          // machine-readable (CLI --json, agents)
```

### Mutation classes

| Change | Class |
|---|---|
| New state, transition, scheduled check, access rule or notification | `Additive` |
| Label, color, sort order, per-state field rules, updated scheduled check / access rule / notification | `Safe` |
| Removed state, transition, scheduled check, access rule or notification | `Breaking` |

### Conflicts

| Code | Meaning |
|---|---|
| `unknown_state` | A transition references a state that is not part of the definition |
| `state_in_use` | A removed state is still referenced by a surviving transition |

Conflicts are blocking (`isApplicable()` returns `false`); pass
`PlanOptions::make()->force()` to bypass `state_in_use` explicitly.

The planner is read-only: applying a plan to the database is the applier's job.


## Applying & revisions

`WorkflowApplier` reconciles a definition with the database. It is
**model-agnostic**: a workflow is identified by `(tenantId, modelType, stateColumn)`.

```php
use RoBYCoNTe\FilamentFlow\Definition\Planning\PlanOptions;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowApplier;

$workflow = app(WorkflowApplier::class)->apply(
    modelType: Order::class,
    tenantId: $companyId,          // null for a global workflow
    definition: $definition,
    options: PlanOptions::make(),  // ->force() / ->migrate() / ->userId()
);
```

Behaviour:

- **Idempotent**: applying an unchanged definition is a no-op.
- **Transactional**: the whole reconciliation runs in one transaction.
- **Conflict-safe**: a plan with blocking conflicts throws
  `WorkflowConflictException` (nothing is written).
- **Revisions**: a *breaking* change snapshots the current definition **before**
  mutating it and bumps `workflows.schema_version`. The snapshot is stored in
  `workflow_snapshots` (states, transitions, side effects, validation rules,
  state fields, access rules, scheduled checks, notifications and their
  recipients/channels/templates) via `WorkflowSnapshotService`.

```php
use RoBYCoNTe\FilamentFlow\Revision\WorkflowSnapshotService;

app(WorkflowSnapshotService::class)->snapshot($workflow, ['changes' => $changes], $userId);
```

### Aligning a revision with a host version counter

A host application can keep the workflow revision in sync with its own domain
version (e.g. the version of the scheme it belongs to). Passing `revisionVersion` writes the
workflow snapshot at that version instead of the workflow's own counter:

```php
$workflow = app(WorkflowApplier::class)->apply(
    modelType: Order::class,
    tenantId: $companyId,
    definition: $definition,
    revisionVersion: $bandoVersion, // the workflow snapshot is stored at this version
);

// Low level: WorkflowSnapshotService::snapshot($workflow, $changes, $userId, $version)
```

> This page documents the **definition layer**. Planning a definition against the
> database, applying it and recording revisions are provided by the workflow
> planner/applier of the SDK.
