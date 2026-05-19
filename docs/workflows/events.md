# Lifecycle Events

Filament Flow dispatches Laravel events at key points during workflow execution. Listening to these events lets you react to state changes without modifying the workflow engine itself — for example, to sync external systems, update caches, send custom notifications, or write audit records.

Events fire for both code-first (Spatie State class) and database-driven transitions. They are dispatched after the transition has been committed and side effects have run, so listeners receive the record in its final state.

## StateEntered

Fired when a record enters a new state.

```php
namespace RoBYCoNTe\FilamentFlow\Events;

class StateEntered
{
    public function __construct(
        public readonly Model $record,
        public readonly string $state,  // fully-qualified class name or state string
        public readonly ?Model $user = null,
    ) {}
}
```

**Example listener:**

```php
namespace App\Listeners;

use RoBYCoNTe\FilamentFlow\Events\StateEntered;

class SyncExternalSystemOnStateEntered
{
    public function handle(StateEntered $event): void
    {
        // $event->record  — the model that entered the state
        // $event->state   — e.g. 'App\States\Order\ProcessingState' or 'processing'
        // $event->user    — the user who triggered the transition, or null

        if ($event->state === \App\States\Order\ProcessingState::class) {
            app(\App\Services\ExternalOrderSync::class)->markAsProcessing($event->record);
        }
    }
}
```

## StateExited

Fired when a record exits a state, immediately before entering the next one.

```php
namespace RoBYCoNTe\FilamentFlow\Events;

class StateExited
{
    public function __construct(
        public readonly Model $record,
        public readonly string $state,  // the state being exited
        public readonly ?Model $user = null,
    ) {}
}
```

**Example listener:**

```php
namespace App\Listeners;

use RoBYCoNTe\FilamentFlow\Events\StateExited;

class CleanUpOnStateExited
{
    public function handle(StateExited $event): void
    {
        if ($event->state === \App\States\Order\PendingState::class) {
            // Clear any pending hold flags when leaving the pending state
            cache()->forget("order_hold_{$event->record->id}");
        }
    }
}
```

## TransitionCompleted

Fired after a transition has been fully executed (state updated, side effects run, history logged).

```php
namespace RoBYCoNTe\FilamentFlow\Events;

class TransitionCompleted
{
    public function __construct(
        public readonly Model $record,
        public readonly string $from,      // the previous state
        public readonly string $to,        // the new state
        public readonly ?Model $user = null,
        public readonly array $metadata = [],  // form data submitted with the transition
    ) {}
}
```

**Example listener:**

```php
namespace App\Listeners;

use RoBYCoNTe\FilamentFlow\Events\TransitionCompleted;

class NotifySlackOnTransitionCompleted
{
    public function handle(TransitionCompleted $event): void
    {
        \Illuminate\Support\Facades\Log::info('Transition completed', [
            'record'   => get_class($event->record) . '#' . $event->record->getKey(),
            'from'     => $event->from,
            'to'       => $event->to,
            'user'     => $event->user?->email,
            'metadata' => $event->metadata,
        ]);
    }
}
```

## WorkflowAssigned

Fired when a user is assigned to a record via the workflow assignment system.

```php
namespace RoBYCoNTe\FilamentFlow\Events;

class WorkflowAssigned
{
    public function __construct(
        public readonly Model $record,
        public readonly Model $assignee,
        public readonly ?Model $assignedBy = null,
        public readonly string $assignmentType = 'primary',
    ) {}
}
```

**Example listener:**

```php
namespace App\Listeners;

use RoBYCoNTe\FilamentFlow\Events\WorkflowAssigned;

class NotifyAssigneeOnWorkflowAssigned
{
    public function handle(WorkflowAssigned $event): void
    {
        // $event->record          — the model that was assigned
        // $event->assignee        — the user being assigned
        // $event->assignedBy      — the user who made the assignment, or null (system)
        // $event->assignmentType  — e.g. 'primary', 'secondary', 'reviewer'

        $event->assignee->notify(
            new \App\Notifications\YouHaveBeenAssigned($event->record, $event->assignedBy)
        );
    }
}
```

## Registering Listeners

Register listeners in `AppServiceProvider` (Laravel 11+) or in `EventServiceProvider`:

**AppServiceProvider (Laravel 11+):**

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RoBYCoNTe\FilamentFlow\Events\StateEntered;
use RoBYCoNTe\FilamentFlow\Events\StateExited;
use RoBYCoNTe\FilamentFlow\Events\TransitionCompleted;
use RoBYCoNTe\FilamentFlow\Events\WorkflowAssigned;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            StateEntered::class,
            \App\Listeners\SyncExternalSystemOnStateEntered::class,
        );

        \Illuminate\Support\Facades\Event::listen(
            TransitionCompleted::class,
            \App\Listeners\NotifySlackOnTransitionCompleted::class,
        );

        \Illuminate\Support\Facades\Event::listen(
            WorkflowAssigned::class,
            \App\Listeners\NotifyAssigneeOnWorkflowAssigned::class,
        );
    }
}
```

**EventServiceProvider (Laravel 10 and below):**

```php
protected $listen = [
    \RoBYCoNTe\FilamentFlow\Events\TransitionCompleted::class => [
        \App\Listeners\NotifySlackOnTransitionCompleted::class,
    ],
    \RoBYCoNTe\FilamentFlow\Events\WorkflowAssigned::class => [
        \App\Listeners\NotifyAssigneeOnWorkflowAssigned::class,
    ],
];
```

## Queued Listeners

All four events use the `SerializesModels` trait, which means Eloquent model instances are serialized safely when listeners are queued. Laravel will automatically re-fetch the models from the database when a queued listener is processed.

```php
namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use RoBYCoNTe\FilamentFlow\Events\TransitionCompleted;

class SyncExternalOrderOnTransitionCompleted implements ShouldQueue
{
    public string $queue = 'workflows';

    public function handle(TransitionCompleted $event): void
    {
        // $event->record is automatically restored from the database
        app(\App\Services\ExternalOrderSync::class)->sync($event->record);
    }
}
```

## Event Dispatch Order

For a standard state-changing transition the events fire in this order:

1. `StateExited` — the record is leaving its previous state
2. `StateEntered` — the record has entered the new state
3. `TransitionCompleted` — the entire transition is complete

For in-state actions (transitions with no target state) only `TransitionCompleted` is dispatched, with `from` and `to` set to the same state value.
