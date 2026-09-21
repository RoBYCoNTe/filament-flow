<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Jobs\SendWorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification as WorkflowNotificationConfig;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationLog;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationTemplate;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Notifications\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use RoBYCoNTe\FilamentFlow\Services\RecipientResolver;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The notification configuration declared in config/filament-flow.php must
 * actually drive the behaviour: queue/retry of the job, channel switches,
 * logging, default channel/engine/delay and the mail sender.
 */
class NotificationConfigWiringTest extends TestCase
{
    protected Workflow $workflow;

    protected WorkflowState $pendingState;

    protected WorkflowState $processingState;

    protected WorkflowTransition $transition;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-flow.notifications.enabled', true);

        $this->user = $this->createTestUser();
        $this->workflow = $this->createTestWorkflow();
        $this->pendingState = $this->createWorkflowState($this->workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);
        $this->processingState = $this->createWorkflowState($this->workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'class_name' => ProcessingState::class,
        ]);
        $this->transition = $this->createWorkflowTransition(
            $this->workflow,
            $this->pendingState,
            $this->processingState
        );
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);
    }

    /** @param array<string,mixed> $config */
    private function notificationConfig(array $config = [], array $channel = []): WorkflowNotificationConfig
    {
        $notification = WorkflowNotificationConfig::create(array_merge([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Configured notification',
            'is_active' => true,
            'timing' => 'immediate',
        ], $config));

        WorkflowNotificationChannel::create(array_merge([
            'notification_id' => $notification->id,
            'channel_type' => 'database',
            'is_active' => true,
        ], $channel));

        WorkflowNotificationRecipient::create([
            'notification_id' => $notification->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        return $notification;
    }

    /** NotificationService with the protected delay rule exposed for assertion. */
    private function service(): NotificationService
    {
        return new class(app(RecipientResolver::class)) extends NotificationService
        {
            public function delayFor(WorkflowNotificationConfig $config): int
            {
                return $this->resolveDelayMinutes($config);
            }
        };
    }

    // ── builder ───────────────────────────────────────────────────────────────

    public function test_the_builder_uses_the_configured_default_channel_and_engine(): void
    {
        config()->set('filament-flow.notifications.default_channel', 'mail');
        config()->set('filament-flow.notifications.default_template_engine', 'blade');

        $builder = WorkflowNotificationBuilder::make()->name('Test');

        $this->assertSame('mail', $builder->getChannel());
        $this->assertSame('blade', $builder->getTemplateEngine());
        $this->assertSame('mail', $builder->toArray()['channel']);
        $this->assertSame('blade', $builder->toArray()['template']['template_engine']);
    }

    public function test_an_explicit_channel_and_engine_win_over_the_defaults(): void
    {
        config()->set('filament-flow.notifications.default_channel', 'mail');
        config()->set('filament-flow.notifications.default_template_engine', 'blade');

        $builder = WorkflowNotificationBuilder::make()
            ->name('Test')
            ->channel('database')
            ->templateEngine('mustache');

        $this->assertSame('database', $builder->getChannel());
        $this->assertSame('mustache', $builder->getTemplateEngine());
    }

    // ── job ───────────────────────────────────────────────────────────────────

    public function test_the_job_reads_queue_and_retry_settings_from_the_config(): void
    {
        config()->set('filament-flow.notifications.queue_connection', 'redis');
        config()->set('filament-flow.notifications.queue_name', 'notifications');
        config()->set('filament-flow.notifications.retry_attempts', 7);
        config()->set('filament-flow.notifications.retry_backoff', 120);

        $job = new SendWorkflowNotification(1, Order::class, 1, [1], []);

        $this->assertSame(7, $job->tries);
        $this->assertSame(120, $job->backoff);
        $this->assertSame('redis', $job->connection);
        $this->assertSame('notifications', $job->queue);
    }

    public function test_the_job_falls_back_to_the_default_retry_settings(): void
    {
        config()->set('filament-flow.notifications.queue_connection', null);
        config()->set('filament-flow.notifications.queue_name', null);

        $job = new SendWorkflowNotification(1, Order::class, 1, [1], []);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
    }

    // ── dispatch ──────────────────────────────────────────────────────────────

    public function test_a_disabled_channel_type_is_not_dispatched(): void
    {
        config()->set('filament-flow.notifications.channels.database.enabled', false);

        $this->notificationConfig();

        Notification::fake();
        $this->order()->transitionTo(ProcessingState::class);

        Notification::assertNothingSent();
        $this->assertDatabaseHas('workflow_notification_logs', ['status' => 'skipped']);
    }

    public function test_logging_can_be_switched_off(): void
    {
        config()->set('filament-flow.notifications.logging_enabled', false);

        $this->notificationConfig();

        Notification::fake();
        $this->order()->transitionTo(ProcessingState::class);

        $this->assertSame(0, WorkflowNotificationLog::query()->count());
    }

    public function test_the_default_delay_is_used_when_the_configuration_does_not_set_one(): void
    {
        config()->set('filament-flow.notifications.default_delay_minutes', 15);

        $withDefault = $this->notificationConfig(['delay_minutes' => null]);
        $withOwnValue = $this->notificationConfig(['delay_minutes' => 2]);

        $this->assertSame(15, $this->service()->delayFor($withDefault));
        $this->assertSame(2, $this->service()->delayFor($withOwnValue), 'The notification value wins.');
    }

    public function test_a_delayed_notification_is_queued_and_logged_as_pending(): void
    {
        $this->notificationConfig(['timing' => 'delayed', 'delay_minutes' => 5]);

        Notification::fake();
        Queue::fake();
        $this->order()->transitionTo(ProcessingState::class);

        Queue::assertPushed(SendWorkflowNotification::class);
        Notification::assertNothingSent();
        $this->assertDatabaseHas('workflow_notification_logs', ['status' => 'pending']);
    }

    public function test_a_template_engine_set_on_the_template_wins_over_the_default(): void
    {
        config()->set('filament-flow.notifications.default_template_engine', 'blade');

        $notification = $this->notificationConfig();

        WorkflowNotificationTemplate::create([
            'notification_id' => $notification->id,
            'channel_id' => $notification->channels()->firstOrFail()->id,
            'title' => 'Titolo',
            'body' => 'Corpo',
            'template_engine' => 'plain',
        ]);

        Notification::fake();
        $this->order()->transitionTo(ProcessingState::class);

        $payload = WorkflowNotificationLog::query()->where('status', 'sent')->firstOrFail()->payload;

        $this->assertSame('plain', data_get($payload, 'template.template_engine'));
    }

    // ── mail ──────────────────────────────────────────────────────────────────

    public function test_the_mail_channel_uses_the_configured_sender(): void
    {
        config()->set('filament-flow.notifications.channels.mail.from_address', 'bandi@example.com');
        config()->set('filament-flow.notifications.channels.mail.from_name', 'Ufficio Bandi');

        $notification = new WorkflowNotification([
            'channel' => 'mail',
            'template' => ['subject' => 'Oggetto', 'title' => 'Titolo', 'body' => 'Corpo'],
            'context' => [],
        ], $this->order());

        $message = $notification->toMail($this->user);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertSame('bandi@example.com', $message->from[0] ?? null);
        $this->assertSame('Ufficio Bandi', $message->from[1] ?? null);
    }

    public function test_the_mail_channel_keeps_the_laravel_default_sender_when_not_configured(): void
    {
        config()->set('filament-flow.notifications.channels.mail.from_address', null);

        $notification = new WorkflowNotification([
            'channel' => 'mail',
            'template' => ['subject' => 'Oggetto', 'title' => 'Titolo', 'body' => 'Corpo'],
            'context' => [],
        ], $this->order());

        $this->assertSame([], $notification->toMail($this->user)->from);
    }
}
