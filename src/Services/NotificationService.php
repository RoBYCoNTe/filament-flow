<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateNotifications;
use RoBYCoNTe\FilamentFlow\Contracts\HasTransitionNotifications;
use RoBYCoNTe\FilamentFlow\Jobs\SendWorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification as WorkflowNotificationConfig;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationLog;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
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
     * @param  string  $fromState  The previous state class/name
     * @param  string  $toState  The new state class/name
     * @param  array  $transitionData  Additional data from the transition
     * @param  object|null  $transitionInstance  Optional transition class instance for code-first
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
     * @param  int  $userId  The assigned user ID
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

    /**
     * Dispatch a notification to recipients.
     */
    protected function dispatchNotification(
        WorkflowNotificationConfig $config,
        Model $record,
        array $context = []
    ): void {
        if (! config('filament-flow.notifications.enabled', true)) {
            return;
        }

        if (! $config->is_active) {
            return;
        }

        $recipients = $this->recipientResolver->resolveAll(
            $config->recipients,
            $record,
            $context
        );

        if ($recipients->isEmpty()) {
            $this->logNotification($config, $record, 'database', 'skipped', 'No recipients found');

            return;
        }

        // Get active channels with their templates in a single query
        $channels = $config->channels()->where('is_active', true)->with('templates')->get();

        if ($channels->isEmpty()) {
            // Log that no channels were configured
            $this->logNotification($config, $record, 'none', 'skipped', 'No active channels configured');

            return;
        }

        // Preload all templates for fallback
        $allTemplates = $config->templates()->get();

        // Handle timing
        $timing = $config->timing ?? 'immediate';
        $delayMinutes = $config->delay_minutes ?? 0;

        foreach ($channels as $channel) {
            // Get template for this channel from eager-loaded relation or fallback
            $template = $allTemplates->firstWhere('channel_id', $channel->id)
                ?? $allTemplates->first();

            $notificationData = [
                'config_id' => $config->id,
                'channel' => $channel->channel_type,
                'channel_config' => $channel->channel_config ?? [],
                'template' => $template ? [
                    'subject' => $template->subject,
                    'title' => $template->title,
                    'body' => $template->body,
                    'action_text' => $template->action_text,
                    'action_url' => $template->action_url,
                    'template_engine' => $template->template_engine ?? 'plain',
                    'format' => $template->format ?? 'html',
                    'variables' => $template->variables ?? [],
                ] : null,
                'record_type' => get_class($record),
                'record_id' => $record->getKey(),
                'context' => $context,
                'priority' => $config->priority ?? 'medium',
            ];

            if ($timing === 'immediate') {
                $this->sendNotification($config, $record, $recipients, $notificationData);
            } else {
                // Queue the notification
                $delay = $timing === 'delayed' ? now()->addMinutes($delayMinutes) : null;

                SendWorkflowNotification::dispatch(
                    $config->id,
                    get_class($record),
                    $record->getKey(),
                    $recipients->pluck('id')->toArray(),
                    $notificationData
                )->delay($delay);

                // Log as pending
                foreach ($recipients as $recipient) {
                    $this->logNotification(
                        $config,
                        $record,
                        $notificationData['channel'],
                        'pending',
                        null,
                        $notificationData,
                        $recipient->id
                    );
                }
            }
        }
    }

    /**
     * Send notification immediately.
     */
    public function sendNotification(
        WorkflowNotificationConfig $config,
        Model $record,
        Collection $recipients,
        array $notificationData
    ): void {
        $channelType = $notificationData['channel'];

        try {
            // Create the Laravel notification
            $notification = new WorkflowNotification($notificationData, $record);

            // Determine the Laravel notification channel
            $laravelChannel = $this->mapToLaravelChannel($channelType);

            if ($laravelChannel) {
                // Use Laravel's notification system
                Notification::send($recipients, $notification);
            }

            // Log success for each recipient
            foreach ($recipients as $recipient) {
                $this->logNotification(
                    $config,
                    $record,
                    $channelType,
                    'sent',
                    null,
                    $notificationData,
                    $recipient->id
                );
            }
        } catch (Exception $e) {
            // Log failure
            foreach ($recipients as $recipient) {
                $this->logNotification(
                    $config,
                    $record,
                    $channelType,
                    'failed',
                    $e->getMessage(),
                    $notificationData,
                    $recipient->id
                );
            }

            report($e);
        }
    }

    /**
     * Map our channel type to Laravel notification channel.
     */
    protected function mapToLaravelChannel(string $channelType): ?string
    {
        return match ($channelType) {
            'database' => 'database',
            'mail' => 'mail',
            default => tap(null, fn () => report(
                new Exception("Unsupported notification channel: {$channelType}")
            )),
        };
    }

    /**
     * Log a notification.
     */
    protected function logNotification(
        WorkflowNotificationConfig $config,
        Model $record,
        string $channel,
        string $status,
        ?string $errorMessage = null,
        ?array $payload = null,
        ?int $recipientUserId = null
    ): void {
        try {
            WorkflowNotificationLog::create([
                'notification_id' => $config->id,
                'user_id' => $recipientUserId ?? Auth::id(),
                'notifiable_type' => get_class($record),
                'notifiable_id' => $record->getKey(),
                'channel' => $channel,
                'status' => $status,
                'error_message' => $errorMessage,
                'payload' => $payload,
                'sent_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Exception $e) {
            report($e);
        }
    }

    /**
     * Get the workflow for a model (with tenant fallback support).
     */
    protected function getWorkflowForModel(Model $record, string $stateColumn = 'state'): ?Workflow
    {
        return Workflow::findForModel(get_class($record), $stateColumn);
    }

    /**
     * Resolve a workflow state by class_name or name.
     */
    protected function resolveWorkflowState(Workflow $workflow, string $state): ?WorkflowState
    {
        return $workflow->states()
            ->where(function ($q) use ($state) {
                $q->where('class_name', $state)
                    ->orWhere('name', $state);
            })
            ->first();
    }

    /**
     * Find notifications configured for a specific transition.
     */
    protected function findTransitionNotifications(
        Workflow $workflow,
        string $fromState,
        string $toState
    ): Collection {
        // Resolve from-state to ID first, then find transition
        $fromWs = $this->resolveWorkflowState($workflow, $fromState);

        if (! $fromWs) {
            // No from-state found — return all on_transition notifications as fallback
            return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
                ->where('trigger_event', 'on_transition')
                ->where('is_active', true)
                ->get();
        }

        $transition = $workflow->transitions()
            ->where('from_state_id', $fromWs->id)
            ->first();

        if (! $transition) {
            return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
                ->where('trigger_event', 'on_transition')
                ->where('is_active', true)
                ->get();
        }

        return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
            ->where('trigger_event', 'on_transition')
            ->where('transition_id', $transition->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Find notifications for state entry.
     */
    protected function findStateEntryNotifications(Workflow $workflow, string $state): Collection
    {
        $workflowState = $this->resolveWorkflowState($workflow, $state);

        if (! $workflowState) {
            return collect();
        }

        return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
            ->where('trigger_event', 'on_state_enter')
            ->where('state_id', $workflowState->id)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Find notifications for state exit.
     */
    protected function findStateExitNotifications(Workflow $workflow, string $state): Collection
    {
        $workflowState = $this->resolveWorkflowState($workflow, $state);

        if (! $workflowState) {
            return collect();
        }

        return WorkflowNotificationConfig::where('workflow_id', $workflow->id)
            ->where('trigger_event', 'on_state_exit')
            ->where('state_id', $workflowState->id)
            ->where('is_active', true)
            ->get();
    }
}
