<?php

namespace RoBYCoNTe\FilamentFlow\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification as WorkflowNotificationConfig;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationLog;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;

/**
 * Job for sending workflow notifications asynchronously.
 *
 * This job is dispatched when a notification has delayed or scheduled timing.
 */
class SendWorkflowNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        protected int $notificationConfigId,
        protected string $recordType,
        protected int|string $recordId,
        protected array $recipientIds,
        protected array $notificationData
    ) {}

    /**
     * Execute the job.
     *
     * @noinspection PhpUndefinedMethodInspection
     */
    public function handle(NotificationService $notificationService): void
    {
        // Load the notification config
        $config = WorkflowNotificationConfig::find($this->notificationConfigId);

        if (! $config) {
            Log::warning('WorkflowNotification config not found', [
                'config_id' => $this->notificationConfigId,
            ]);

            return;
        }

        // Load the record
        $record = $this->recordType::find($this->recordId);

        if (! $record) {
            Log::warning('WorkflowNotification record not found', [
                'record_type' => $this->recordType,
                'record_id' => $this->recordId,
            ]);

            return;
        }

        // Load recipients
        $userModel = config('filament-flow.user_model')
            ?? config('auth.providers.users.model')
            ?? 'App\\Models\\User';

        $recipients = $userModel::whereIn('id', $this->recipientIds)->get();

        if ($recipients->isEmpty()) {
            Log::warning('WorkflowNotification recipients not found', [
                'recipient_ids' => $this->recipientIds,
            ]);

            return;
        }

        // Update pending logs to processing
        WorkflowNotificationLog::where('notification_id', $this->notificationConfigId)
            ->where('notifiable_type', $this->recordType)
            ->where('notifiable_id', $this->recordId)
            ->where('status', 'pending')
            ->whereIn('user_id', $this->recipientIds)
            ->update(['status' => 'processing']);

        // Send the notification
        $notificationService->sendNotification(
            $config,
            $record,
            $recipients,
            $this->notificationData
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('WorkflowNotification job failed', [
            'config_id' => $this->notificationConfigId,
            'record_type' => $this->recordType,
            'record_id' => $this->recordId,
            'error' => $exception?->getMessage(),
        ]);

        // Update logs to failed
        WorkflowNotificationLog::where('notification_id', $this->notificationConfigId)
            ->where('notifiable_type', $this->recordType)
            ->where('notifiable_id', $this->recordId)
            ->whereIn('status', ['pending', 'processing'])
            ->whereIn('user_id', $this->recipientIds)
            ->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage(),
            ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'workflow-notification',
            'config:'.$this->notificationConfigId,
            'record:'.$this->recordType.':'.$this->recordId,
        ];
    }
}
