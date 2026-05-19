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
| `user` | Specific users by ID | `['user_ids' => [1, 2, 3]]` |
| `role` | Users with specific roles | `['roles' => ['admin', 'manager']]` |
| `record_owner` | The owner of the record | `['owner_field' => 'user_id']` |
| `assigned_users` | Users assigned to the record | `['types' => ['primary', 'secondary']]` |
| `all_involved` | All users who have interacted with the record | `[]` |
| `involvement_type` | Users with a specific involvement type | `['involvement_type' => 'reviewer']` |
| `custom_field` | User(s) from a custom record field | `['field' => 'approver_id']` |
| `custom_class` | Custom resolver class | `['class' => 'App\\Resolvers\\Custom']` |

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
