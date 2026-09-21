<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use RoBYCoNTe\FilamentFlow\Jobs\SendWorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification as WorkflowNotificationConfig;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationLog;
use RoBYCoNTe\FilamentFlow\Notifications\WorkflowNotification;

/**
 * How a notification is delivered.
 *
 * One prepared notification becomes one row per channel, delayed or immediate, each with the
 * template, the recipients and the sender it declares — and the delivery is written down so
 * the office can see what was sent.
 */
trait DeliversNotifications
{
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

        // Get active channels with their templates in a single query; a channel
        // type disabled in the configuration is skipped.
        $channels = $config->channels()
            ->where('is_active', true)
            ->with('templates')
            ->get()
            ->filter(fn ($channel): bool => (bool) config(
                "filament-flow.notifications.channels.{$channel->channel_type}.enabled",
                true,
            ))
            ->values();

        if ($channels->isEmpty()) {
            // Log that no channels were configured
            $this->logNotification($config, $record, 'none', 'skipped', 'No active channels configured');

            return;
        }

        // Preload all templates for fallback
        $allTemplates = $config->templates()->get();

        // Handle timing
        $timing = $config->timing ?? 'immediate';
        $delayMinutes = $this->resolveDelayMinutes($config);

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
                    'template_engine' => $template->template_engine ?? (string) config('filament-flow.notifications.default_template_engine', 'plain'),
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
     * Minutes to wait before dispatching a delayed notification: the value set on
     * the notification, or the configured default when it is not set.
     */
    protected function resolveDelayMinutes(WorkflowNotificationConfig $config): int
    {
        if ($config->delay_minutes !== null) {
            return (int) $config->delay_minutes;
        }

        return (int) config('filament-flow.notifications.default_delay_minutes', 0);
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
        if (! config('filament-flow.notifications.logging_enabled', true)) {
            return;
        }

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
}
