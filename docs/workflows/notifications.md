# Workflow Notifications

Filament Flow includes a powerful notification system that automatically sends notifications when workflow events occur. Notifications can be triggered on state transitions, state entry/exit, assignments, and field changes.

## Notification Triggers

Notifications can be configured to trigger on various workflow events:

| Trigger Event | Description |
|---|---|
| `on_transition` | When a specific transition is executed |
| `on_state_enter` | When a record enters a specific state |
| `on_state_exit` | When a record exits a specific state |
| `on_assignment` | When a user is assigned to a record |
| `on_field_change` | When specific fields are modified |

**Creating a Notification Configuration:**

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;

// Notify when an order transitions to process
WorkflowNotification::create([
    'workflow_id' => $workflow->id,
    'transition_id' => $transition->id,  // Optional: specific transition
    'state_id' => $processingState->id,   // Optional: specific state
    'trigger_event' => 'on_transition',
    'name' => 'Order Processing Notification',
    'is_active' => true,
    'timing' => 'immediate',              // or 'delayed'
    'priority' => 'medium',               // low, medium, high, urgent
]);
```

## Notification Channels

Filament Flow supports multiple notification channels:

| Channel | Description |
|---|---|
| `database` | Laravel database notifications (Filament notifications) |
| `mail` | Email notifications |

**Configuring Channels:**

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;

// Add database channel
WorkflowNotificationChannel::create([
    'notification_id' => $notification->id,
    'channel_type' => 'database',
    'is_active' => true,
]);

// Add email channel
WorkflowNotificationChannel::create([
    'notification_id' => $notification->id,
    'channel_type' => 'mail',
    'is_active' => true,
    'channel_config' => [
        'from_address' => 'noreply@example.com',
        'from_name' => 'Order System',
    ],
]);
```

## Recipient Configuration

Define who should receive notifications using various recipient strategies:

| Recipient Type | Description | Configuration |
|---|---|---|
| `role` | Users with a specific role | `{"roles": ["admin", "manager"]}` |
| `user` | Specific user IDs | `{"user_ids": [1, 2, 3]}` |
| `trigger_user` | User who triggered the event | none |
| `assigned_users` | All users assigned to the record | none |
| `record_owner` | Record's owner field user | none |
| `state_actors` | Users who performed transitions on the record | none |
| `all_involved` | Union of assignments + transitions + involvement | none |
| `involvement_type` | Users involved with a specific type | `{"involvement_type": "reviewer"}` |
| `custom_field` | User ID(s) stored in a record field | `{"field": "manager_id"}` |
| `custom_query` | Raw SQL to fetch user IDs | `{"query": "SELECT id FROM users WHERE..."}` |
| `custom_class` | Custom resolver class | `{"class": "App\\Resolvers\\MyResolver"}` |

**Creating Recipients:**

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;

// Notify specific users
WorkflowNotificationRecipient::create([
    'notification_id' => $notification->id,
    'recipient_type' => 'user',
    'recipient_config' => ['user_ids' => [1, 2, 3]],
]);

// Notify all admins
WorkflowNotificationRecipient::create([
    'notification_id' => $notification->id,
    'recipient_type' => 'role',
    'recipient_config' => ['roles' => ['admin']],
]);

// Notify the record owner
WorkflowNotificationRecipient::create([
    'notification_id' => $notification->id,
    'recipient_type' => 'record_owner',
    'recipient_config' => [],
]);

// Notify assigned users
WorkflowNotificationRecipient::create([
    'notification_id' => $notification->id,
    'recipient_type' => 'assigned_users',
    'recipient_config' => ['types' => ['primary']],
]);
```

## Notification Templates

Create templates with variable substitution for dynamic content:

```php
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationTemplate;

WorkflowNotificationTemplate::create([
    'notification_id' => $notification->id,
    'channel_id' => $channel->id,
    'subject' => 'Order {{order_number}} - Status Update',
    'title' => 'Order Status Changed',
    'body' => 'Order {{order_number}} for {{customer_name}} has been moved from {{from_state_label}} to {{to_state_label}}.',
    'action_text' => 'View Order',
    'action_url' => '{{app_url}}/orders/{{record_id}}',
    'template_engine' => 'plain',  // plain, blade, or mustache
]);
```

**Available Template Variables:**

| Variable | Description |
|---|---|
| `{{record_id}}` | The record's primary key |
| `{{record_type}}` | The record's class name (short) |
| `{{order_number}}` | Any record field (uses field name) |
| `{{customer_name}}` | Any record field (uses field name) |
| `{{from_state}}` | The previous state class name |
| `{{to_state}}` | The new state class name |
| `{{from_state_label}}` | Human-readable label of the previous state |
| `{{to_state_label}}` | Human-readable label of the new state |
| `{{trigger}}` | The trigger event type |
| `{{app_name}}` | Application name from config |
| `{{app_url}}` | Application URL from config |

**Template Engines:**

- `plain` — Simple <code v-pre>{{variable}}</code> or <code v-pre>{{ variable }}</code> substitution
- `blade` — Laravel Blade syntax with full Blade features
- `mustache` — Mustache syntax with HTML escaping (<code v-pre>{{var}}</code> escaped, <code v-pre>{{{var}}}</code> unescaped)

## The title of a notification

A bell is a list of events: without the file each one is about, two "Application received" say
nothing, and the reader has to open them one by one. The engine composes the title from a
pattern declared **once**, so every notification carries the code of its record without any
event having to repeat it.

```php
// config/filament-flow.php
'notifications' => [
    // `{title}` is the event, `{record}` the code of the record it is about.
    'title_pattern' => '{title} · {record}',
],
```

Which code a record reads by is the host's decision: the record implements `HasWorkflowLabel`.

```php
use RoBYCoNTe\FilamentFlow\Contracts\HasWorkflowLabel;

class Application extends Model implements HasWorkflowLabel
{
    public function workflowLabel(): ?string
    {
        // The protocol the office assigned, or the short id the page itself shows.
        return $this->protocol_number ?? '#'.strtoupper(substr((string) $this->getKey(), -6));
    }
}
```

The title then reads `Application received · #4F2A9C`, `Integration requested · Prot. 123/2026`.

When the record has no label — no contract, no title, no code — the event stands alone instead
of showing a dangling separator. A record that does not implement the contract is still read by
`RecordLabel`: the title Filament gives it (`getRecordTitle()`), then a column that looks like a
code (`protocol_number`, `reference`, `code`, `number`, `name`, `title`).

The pattern is applied to the **title of the bell** and the **subject of the mail**; the code is
also available inside templates as the `record_title` variable. A pattern without `{record}` leaves the
title exactly as the event wrote it.

## Action Buttons in Templates

Notification templates support a call-to-action button via `action_text` and `action_url`.

| Field | Description |
|---|---|
| `action_text` | Button label (e.g., "View Order") |
| `action_url` | URL — supports template variables like `{{app_url}}/orders/{{record_id}}` |

The button is rendered as an actionable link in database notifications and as a linked button in mail notifications. A notification that says something happened is only useful if it opens the file it happened on: `->action('{{ record_url }}', 'Open the application')` on the definition puts that link on the bell and on the mail.

The template's `format` field controls how the body is rendered:

| Format | Description |
|---|---|
| `html` | Raw HTML body |
| `markdown` | Markdown rendered to HTML |
| `plain` | Plain text (default) |

### Expressions of the host

The engine fills its own placeholders — `record_id`, `app_url`, `from_state`,
`to_state_label`, `transition_label` — and leaves everything else to the host. A host
that wants its own vocabulary (`field("path")`, `currency("amount")`, the URL of the record)
registers a `NotificationTemplateProvider`:

```php
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\NotificationTemplateProvider;
use RoBYCoNTe\FilamentFlow\Support\NotificationTemplateRegistry;

final class ApplicationNotificationTemplateProvider implements NotificationTemplateProvider
{
    public function interpolate(string $template, Model $record): string
    {
        // fill the `{{ ... }}` the engine left behind, against the record
        return $template;
    }
}
```

```php
// a service provider
$this->app->afterResolving(NotificationTemplateRegistry::class, function (NotificationTemplateRegistry $registry): void {
    $registry->register(app(ApplicationNotificationTemplateProvider::class));
});
```

The engine renders its own variables first, then hands the result to the provider: a template may
mix `record_id` and `field("review.notes")` without either side having to know the
other. An expression the provider cannot resolve should be left out, never thrown — a
notification that carries one broken placeholder is worth more than one that never leaves. A
host that registers no provider gets the engine's rendering alone.

Together, the two make a notification that says what happened and opens where it happened:

```php
Notification::make('integration_requested', 'Integration requested')
    ->body('The office asked for an integration: {{ field("review.notes") }}')
    ->action('{{ record_url }}', 'Open the application')
    ->database();
```

## Notification Timing

Control when notifications are sent:

| Timing | Description |
|---|---|
| `immediate` | Send immediately when the event occurs |
| `delayed` | Send after a specified delay |

**Delayed Notifications:**

```php
WorkflowNotification::create([
    'workflow_id' => $workflow->id,
    'trigger_event' => 'on_state_enter',
    'state_id' => $pendingState->id,
    'name' => 'Reminder: Order Still Pending',
    'is_active' => true,
    'timing' => 'delayed',
    'delay_minutes' => 60,  // Send 1 hour after entering state
    'priority' => 'high',
]);
```

## Notification Configuration Options

Configure the notification system in `config/filament-flow.php`:

```php
'notifications' => [
    /**
     * Enable or disable the notification system globally.
     */
    'enabled' => true,

    /**
     * Default notification channel when none is specified.
     */
    'default_channel' => 'database',

    /**
     * Queue connection for async notifications.
     */
    'queue_connection' => null,

    /**
     * Queue name for notification jobs.
     */
    'queue_name' => null,

    /**
     * Default delay in minutes for delayed notifications.
     */
    'default_delay_minutes' => 0,

    /**
     * Number of retry attempts for failed notification jobs.
     */
    'retry_attempts' => 3,

    /**
     * Backoff time in seconds between retry attempts.
     */
    'retry_backoff' => 60,

    /**
     * Enable logging of all notification dispatches.
     */
    'logging_enabled' => true,

    /**
     * Channel-specific configuration.
     */
    'channels' => [
        'database' => [
            'enabled' => true,
        ],
        'mail' => [
            'enabled' => true,
            'from_address' => null,
            'from_name' => null,
        ],
    ],

    /**
     * Default template rendering engine.
     */
    'default_template_engine' => 'plain',
],
```

**Triggering Notifications Programmatically:**

```php
use RoBYCoNTe\FilamentFlow\Services\NotificationService;

$notificationService = app(NotificationService::class);

// Trigger for a transition
$notificationService->triggerForTransition(
    $order,
    $fromState,
    $toState,
    ['additional' => 'data']
);

// Trigger for state entry
$notificationService->triggerForStateEntry($order, $newState);

// Trigger for assignment
$notificationService->triggerForAssignment($order, $userId, 'primary');

// Trigger for field change
$notificationService->triggerForFieldChange($order, 'status', $oldValue, $newValue);
```

**Notification Logging:**

All notifications are logged in the `workflow_notification_logs` table with:

- `notification_id` — The notification configuration
- `user_id` — The recipient user
- `notifiable_type/id` — The record that triggered the notification
- `channel` — The delivery channel used
- `status` — pending, sent, failed, skipped
- `error_message` — Error details if failed
- `payload` — The notification data sent
- `sent_at` — When the notification was sent

## Custom Channels: Drivers

The engine delivers `database` and `mail` itself. A channel of its own — a PEC, an
external messaging service — is taught to it with a driver: a class implementing
`NotificationChannelDriver`, registered for the name the notifications declare.

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Contracts\NotificationChannelDriver;

final class PecChannelDriver implements NotificationChannelDriver
{
    public function send(Model $record, Collection $recipients, array $notificationData): void
    {
        // $notificationData['rendered'] — subject, title, body, action, all filled
        // $notificationData['channel_config'] — what the notification declared
    }
}
```

```php
// config/filament-flow.php
'notifications' => [
    'channel_drivers' => [
        'pec' => \App\Notifications\Channels\PecChannelDriver::class,
    ],
],
```

Or on the registry directly, from a service provider:

```php
$this->app->afterResolving(NotificationChannelDriverRegistry::class, function (NotificationChannelDriverRegistry $registry): void {
    $registry->register('pec', app(PecChannelDriver::class));
});
```

The channel is declared where every other channel is — the Definition SDK takes a name
beside the enum, the panel lists the channels of the configuration:

```php
Notification::make('registered-pec', 'Registered')
    ->channel('pec', ['mailbox' => 'protocol@pec.example'])
    ->recipient(Recipient::recordOwner());
```

The engine renders the template the same way it does for its own channels and hands it
to the driver with the recipients and the channel configuration; timing, the dispatch
job and the delivery log stay the engine's business. A channel declared in the
configuration but disabled there is skipped like a disabled `database` or `mail`.

## Code-First Notifications

In addition to database-configured notifications, you can define notifications directly in your State and Transition classes using a fluent builder API.

**In State Classes (HasStateNotifications):**

```php
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateNotifications;
use Spatie\ModelStates\State;

class ProcessingState extends State implements HasStateNotifications
{
    // Notifications sent when entering this state
    public function onEnterNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Order Processing Started')
                ->body('Your order {{order_number}} is now being processed.')
                ->priority('medium'),
        ];
    }

    // Notifications sent when exiting this state
    public function onExitNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Processing Complete')
                ->body('Your order {{order_number}} has finished processing.'),
        ];
    }
}
```

**In Transition Classes (HasTransitionNotifications):**

```php
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasTransitionNotifications;
use Spatie\ModelStates\Transition;

class ProcessOrderTransition extends Transition implements HasTransitionNotifications
{
    public function notifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner', 'role:admin'])
                ->title('Order Transitioned')
                ->body('Order {{order_number}} has been moved to processing.')
                ->priority('high'),

            WorkflowNotificationBuilder::make()
                ->channel('mail')
                ->recipients(['role:warehouse'])
                ->subject('New Order to Process')
                ->title('Order Ready')
                ->body('Order {{order_number}} ({{customer_name}}) needs processing.')
                ->actionUrl('/orders/{{record_id}}', 'View Order'),
        ];
    }
}
```

**Code-First Recipient Formats:**

| Format | Description |
|---|---|
| `@owner` | Record owner (via user_id or configured owner field) |
| `@assigned` | Assigned users |
| `@all_involved` | All users involved with the record |
| `role:admin` | Users with specific role |
| `role:admin,manager` | Users with any of the specified roles |
| `user:1` | Specific user by ID |
| `user:1,2,3` | Multiple users by ID |
| `involvement:reviewer` | Users involved as specific type |
| `fn($record) => ...` | Custom callable resolver |

**WorkflowNotificationBuilder Methods:**

```php
WorkflowNotificationBuilder::make()
    ->name('notification_name')           // Optional name for logging
    ->channel('database', $config)        // Channel: database, mail
    ->recipients(['@owner', 'role:admin']) // Who receives the notification
    ->title('Title with {{variables}}')    // Notification title
    ->body('Body with {{variables}}')      // Notification body
    ->subject('Email subject')             // Email subject (mail channel)
    ->actionUrl('/url', 'Button Text')     // Action button
    ->priority('high')                     // low, medium, high, urgent
    ->templateEngine('plain')              // plain, blade, mustache
    ->immediate()                          // Send immediately (default)
    ->delay(30)                            // Delay by minutes
    ->metadata(['key' => 'value']);        // Additional metadata
```


## Definition SDK

Notifications can be declared with the typed
[`Notification`](/workflows/definition-sdk#notifications) builder and attached to
a transition, a state or the workflow itself:

```php
use RoBYCoNTe\FilamentFlow\Definition\Notification;
use RoBYCoNTe\FilamentFlow\Definition\Recipient;
use RoBYCoNTe\FilamentFlow\Definition\Transition;

Transition::make('approve', 'under_review', 'approved')
    ->notification(
        Notification::make('approved-notice', 'Approved')
            ->database()
            ->mail()
            ->recipient(Recipient::role('reviewer'))
            ->title('Approved')
            ->body('Your request was approved.')
    );
```

The attachment sets the trigger event (`on_transition`, `on_state_enter`,
`on_state_exit`). Recipients, channels and templates are reconciled
idempotently: re-applying an unchanged definition performs no write.
