<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateNotifications;
use RoBYCoNTe\FilamentFlow\Contracts\HasTransitionNotifications;
use RoBYCoNTe\FilamentFlow\Jobs\SendWorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification as WorkflowNotificationConfig;
use RoBYCoNTe\FilamentFlow\Notifications\WorkflowNotification;
use Spatie\ModelStates\State;

/**
 * Service for dispatching workflow notifications.
 *
 * This service orchestrates the notification system:
 * - Finds matching notification configurations
 * - Resolves recipients
 * - Dispatches notifications (sync or async)
 * - Logs notification delivery
 */
class NotificationService
{
    use DeliversNotifications;
    use FindsNotificationTargets;

    public function __construct(
        protected RecipientResolver $recipientResolver
    ) {}

    /**
     * Trigger notifications for a state transition.
     *
     * Supports both database-first and code-first notifications:
     * - Database-first: Notifications configured via WorkflowNotification model
     * - Code-first: Notifications defined in State/Transition classes via interfaces
     *
     * @param  Model  $record  The record that transitioned
     * @param  string  $fromState  The previous
     *                             state class/name @param string $toState The new state class/name @param array
     *                             $transitionData Additional data from the transition @param object|null
     *                             $transitionInstance Optional transition class instance for code-first
     */
    public function triggerForTransition(
        Model $record,
        string $fromState,
        string $toState,
        array $transitionData = [],
        ?object $transitionInstance = null
    ): void {
        $context = [
            'trigger' => 'transition',
            'from_state' => $fromState,
            'to_state' => $toState,
            'transition_data' => $transitionData,
        ];

        // 1. Code-first: Check transition class for notifications
        if ($transitionInstance instanceof HasTransitionNotifications) {
            $this->dispatchCodeFirstNotifications(
                $transitionInstance->notifications(),
                $record,
                $context
            );
        }

        // 2. Code-first: Check state classes for notifications
        $this->triggerCodeFirstStateNotifications($record, $fromState, $toState, $context);

        // 3. Database-first: Check workflow configuration
        $workflow = $this->getWorkflowForModel($record);

        if (! $workflow) {
            return;
        }

        // Enrich context with localized labels from the workflow DB config
        $context = array_merge($context, $this->buildTransitionContextLabels($workflow, $fromState, $toState));

        // Find notifications configured for this transition
        $notifications = $this->findTransitionNotifications($workflow, $fromState, $toState);

        // Also check for state entry notifications
        $stateEntryNotifications = $this->findStateEntryNotifications($workflow, $toState);
        $notifications = $notifications->merge($stateEntryNotifications);

        // Also check for state exit notifications
        $stateExitNotifications = $this->findStateExitNotifications($workflow, $fromState);
        $notifications = $notifications->merge($stateExitNotifications);

        foreach ($notifications as $notificationConfig) {
            $this->dispatchNotification($notificationConfig, $record, $context);
        }
    }

    /**
     * Trigger code-first state notifications (onEnter/onExit).
     */
    protected function triggerCodeFirstStateNotifications(
        Model $record,
        string $fromState,
        string $toState,
        array $context
    ): void {
        // Get state instances if they implement HasStateNotifications
        $fromStateInstance = $this->getStateInstance($record, $fromState);
        $toStateInstance = $this->getStateInstance($record, $toState);

        // Trigger exit notifications from the from-state
        if ($fromStateInstance instanceof HasStateNotifications) {
            $exitNotifications = $fromStateInstance->onExitNotifications();
            $this->dispatchCodeFirstNotifications(
                $exitNotifications,
                $record,
                array_merge($context, ['trigger' => 'state_exit', 'state' => $fromState])
            );
        }

        // Trigger enter notifications for the to-state
        if ($toStateInstance instanceof HasStateNotifications) {
            $enterNotifications = $toStateInstance->onEnterNotifications();
            $this->dispatchCodeFirstNotifications(
                $enterNotifications,
                $record,
                array_merge($context, ['trigger' => 'state_enter', 'state' => $toState])
            );
        }
    }

    /**
     * Get a State instance for the given class name.
     */
    protected function getStateInstance(Model $record, string $stateClass): ?State
    {
        if (! class_exists($stateClass)) {
            return null;
        }

        try {
            $instance = new $stateClass($record);

            return $instance instanceof State ? $instance : null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Dispatch code-first notifications defined via WorkflowNotificationBuilder.
     *
     * @param  array<WorkflowNotificationBuilder>  $builders
     */
    public function dispatchCodeFirstNotifications(array $builders, Model $record, array $context = []): void
    {
        foreach ($builders as $builder) {
            if (! $builder instanceof WorkflowNotificationBuilder) {
                continue;
            }

            $this->dispatchCodeFirstNotification($builder, $record, $context);
        }
    }

    /**
     * Dispatch a single code-first notification.
     */
    protected function dispatchCodeFirstNotification(
        WorkflowNotificationBuilder $builder,
        Model $record,
        array $context = []
    ): void {
        // Resolve recipients using code-first format
        $recipients = $this->recipientResolver->resolveCodeFirst(
            $builder->getRecipients(),
            $record
        );

        if ($recipients->isEmpty()) {
            return;
        }

        $builderData = $builder->toArray();
        $notificationData = [
            'channel' => $builderData['channel'],
            'channel_config' => $builderData['channel_config'],
            'template' => $builderData['template'],
            'record_type' => get_class($record),
            'record_id' => $record->getKey(),
            'context' => $context,
            'priority' => $builderData['priority'],
            'code_first' => true, // Flag to indicate this is code-first
        ];

        $timing = $builderData['timing'];
        $delayMinutes = $builderData['delay_minutes'];

        if ($timing === 'immediate') {
            $this->sendCodeFirstNotification($record, $recipients, $notificationData);
        } else {
            // Queue the notification with delay
            $delay = now()->addMinutes($delayMinutes);

            SendWorkflowNotification::dispatch(
                null, // No config_id for code-first
                get_class($record),
                $record->getKey(),
                $recipients->pluck('id')->toArray(),
                $notificationData
            )->delay($delay);
        }
    }

    /**
     * Send a code-first notification immediately.
     */
    protected function sendCodeFirstNotification(
        Model $record,
        Collection $recipients,
        array $notificationData
    ): void {
        $this->sendPreparedNotification($record, $recipients, $notificationData);
    }

    /**
     * Dispatch an already built notification payload. Used for code-first
     * notifications, immediately and from the queued job (which has no
     * notification configuration row).
     *
     * @param  Collection<int, Model>  $recipients
     * @param  array<string, mixed>  $notificationData
     */
    public function sendPreparedNotification(
        Model $record,
        Collection $recipients,
        array $notificationData
    ): void {
        // A channel the host taught the engine delivers through its own driver,
        // the same as the database-configured notifications do.
        if ($this->deliverViaDriver((string) ($notificationData['channel'] ?? 'database'), $record, $recipients, $notificationData)) {
            return;
        }

        try {
            $notification = new WorkflowNotification($notificationData, $record);
            Notification::send($recipients, $notification);
        } catch (Exception $e) {
            report($e);
        }
    }

    /**
     * Trigger a specific notification by its ID.
     * Used by scheduled checks and other programmatic triggers.
     */
    public function triggerById(int $notificationId, Model $record, array $context = []): void
    {
        $config = WorkflowNotificationConfig::find($notificationId);

        if (! $config) {
            return;
        }

        $context = array_merge([
            'trigger' => 'scheduled',
        ], $context);

        $this->dispatchNotification($config, $record, $context);
    }

    /**
     * Trigger notifications for a state entry.
     */
    public function triggerForStateEntry(Model $record, string $state): void
    {
        $workflow = $this->getWorkflowForModel($record);

        if (! $workflow) {
            return;
        }

        $notifications = $this->findStateEntryNotifications($workflow, $state);

        foreach ($notifications as $notificationConfig) {
            $this->dispatchNotification($notificationConfig, $record, [
                'trigger' => 'state_enter',
                'state' => $state,
            ]);
        }
    }

    /**
     * Trigger notifications for an assignment.
     *
     * @param  Model  $record  The record with the assignment
     * @param  int|Model  $user  The assigned user (or its id)
     * @param  string  $assignmentType  The type of assignment (primary, secondary, etc.)
     */
    public function triggerForAssignment(
        Model $record,
        int|Model $user,
        string $assignmentType
    ): void {
        $workflow = $this->getWorkflowForModel($record);

        if (! $workflow) {
            return;
        }

        $notifications = WorkflowNotificationConfig::where('workflow_id', $workflow->id)
            ->where('trigger_event', 'on_assignment')
            ->where('is_active', true)
            ->get();

        foreach ($notifications as $notificationConfig) {
            $this->dispatchNotification($notificationConfig, $record, [
                'trigger' => 'assignment',
                'assigned_user_id' => $user instanceof Model ? $user->getKey() : $user,
                'assignee_model' => $user instanceof Model ? $user : null,
                'assignment_type' => $assignmentType,
            ]);
        }
    }

    /**
     * Trigger notifications for a field change.
     *
     * @param  Model  $record  The record with the changed field
     * @param  string  $field  The field that changed
     * @param  mixed  $oldValue  The old value
     * @param  mixed  $newValue  The new value
     */
    public function triggerForFieldChange(
        Model $record,
        string $field,
        mixed $oldValue,
        mixed $newValue
    ): void {
        $workflow = $this->getWorkflowForModel($record);

        if (! $workflow) {
            return;
        }

        $notifications = WorkflowNotificationConfig::where('workflow_id', $workflow->id)
            ->where('trigger_event', 'on_field_change')
            ->where('is_active', true)
            ->get()
            ->filter(function ($config) use ($field) {
                // Check if this notification is configured for this field
                $watchedFields = $config->metadata['watched_fields'] ?? [];

                return empty($watchedFields) || in_array($field, $watchedFields);
            });

        foreach ($notifications as $notificationConfig) {
            $this->dispatchNotification($notificationConfig, $record, [
                'trigger' => 'field_change',
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]);
        }
    }
}
