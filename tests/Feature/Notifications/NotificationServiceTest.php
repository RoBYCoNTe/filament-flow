<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Notifications;

use Exception;
use Illuminate\Support\Facades\Notification;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
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
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Throwable;

class NotificationServiceTest extends TestCase
{
    protected Workflow $workflow;

    protected WorkflowState $pendingState;

    protected WorkflowState $processingState;

    protected WorkflowTransition $transition;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable notifications
        config()->set('filament-flow.notifications.enabled', true);

        // Create test user
        $this->user = $this->createTestUser();

        // Create workflow
        $this->workflow = $this->createTestWorkflow();

        // Create states
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

        // Create transition
        $this->transition = $this->createWorkflowTransition(
            $this->workflow,
            $this->pendingState,
            $this->processingState
        );
    }

    /**
     * Helper to create an order with proper initial state
     */
    protected function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ], $data));
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_can_find_notifications_for_transition(): void
    {
        // Create notification config
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Test Notification',
            'is_active' => true,
            'timing' => 'immediate',
        ]);

        // Create channel
        $channel = WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        // Create recipient
        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        // Create template
        WorkflowNotificationTemplate::create([
            'notification_id' => $notificationConfig->id,
            'channel_id' => $channel->id,
            'title' => 'Order Transitioned',
            'body' => 'Order {{order_number}} has been moved to processing.',
        ]);

        Notification::fake();

        // Create order and transition
        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        // Assert notification was sent
        Notification::assertSentTo(
            $this->user,
            WorkflowNotification::class
        );
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_does_not_send_notifications_when_disabled(): void
    {
        config()->set('filament-flow.notifications.enabled', false);

        // Create notification config
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Test Notification',
            'is_active' => true,
        ]);

        WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        Notification::assertNothingSent();
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_does_not_send_notifications_when_config_is_inactive(): void
    {
        // Create inactive notification config
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Test Notification',
            'is_active' => false, // Inactive
        ]);

        WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        Notification::assertNothingSent();
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_skips_notifications_without_recipients(): void
    {
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Test Notification',
            'is_active' => true,
        ]);

        WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        // No recipients configured

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        Notification::assertNothingSent();

        // Should log as skipped
        $this->assertDatabaseHas('workflow_notification_logs', [
            'notification_id' => $notificationConfig->id,
            'status' => 'skipped',
        ]);
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_skips_notifications_without_active_channels(): void
    {
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Test Notification',
            'is_active' => true,
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        // No channels configured

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        Notification::assertNothingSent();
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_logs_sent_notifications(): void
    {
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Test Notification',
            'is_active' => true,
            'timing' => 'immediate',
        ]);

        $channel = WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        WorkflowNotificationTemplate::create([
            'notification_id' => $notificationConfig->id,
            'channel_id' => $channel->id,
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        $this->assertDatabaseHas('workflow_notification_logs', [
            'notification_id' => $notificationConfig->id,
            'user_id' => $this->user->id,
            'notifiable_type' => Order::class,
            'notifiable_id' => $order->id,
            'channel' => 'database',
            'status' => 'sent',
        ]);
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_triggers_notifications_for_state_entry(): void
    {
        // Create state entry notification
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'state_id' => $this->processingState->id,
            'trigger_event' => 'on_state_enter',
            'name' => 'Processing State Entry',
            'is_active' => true,
            'timing' => 'immediate',
        ]);

        $channel = WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        WorkflowNotificationTemplate::create([
            'notification_id' => $notificationConfig->id,
            'channel_id' => $channel->id,
            'title' => 'Order Processing Started',
            'body' => 'Order is now being processed.',
        ]);

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        Notification::assertSentTo($this->user, WorkflowNotification::class);
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_triggers_notifications_for_state_exit(): void
    {
        // Create state exit notification
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'state_id' => $this->pendingState->id,
            'trigger_event' => 'on_state_exit',
            'name' => 'Pending State Exit',
            'is_active' => true,
            'timing' => 'immediate',
        ]);

        $channel = WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        WorkflowNotificationTemplate::create([
            'notification_id' => $notificationConfig->id,
            'channel_id' => $channel->id,
            'title' => 'Order Left Pending',
            'body' => 'Order has left pending state.',
        ]);

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        Notification::assertSentTo($this->user, WorkflowNotification::class);
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_it_sends_to_multiple_recipients(): void
    {
        $user2 = User::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
        ]);

        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Test Notification',
            'is_active' => true,
            'timing' => 'immediate',
        ]);

        $channel = WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id, $user2->id]],
        ]);

        WorkflowNotificationTemplate::create([
            'notification_id' => $notificationConfig->id,
            'channel_id' => $channel->id,
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        $order->transitionTo(ProcessingState::class);

        Notification::assertSentTo($this->user, WorkflowNotification::class);
        Notification::assertSentTo($user2, WorkflowNotification::class);

        // Two log entries should be created
        $this->assertEquals(2, WorkflowNotificationLog::where('notification_id', $notificationConfig->id)->count());
    }

    /**
     * @throws Exception
     */
    public function test_it_can_trigger_notifications_directly_via_service(): void
    {
        $notificationConfig = WorkflowNotificationConfig::create([
            'workflow_id' => $this->workflow->id,
            'transition_id' => $this->transition->id,
            'trigger_event' => 'on_transition',
            'name' => 'Test Notification',
            'is_active' => true,
            'timing' => 'immediate',
        ]);

        $channel = WorkflowNotificationChannel::create([
            'notification_id' => $notificationConfig->id,
            'channel_type' => 'database',
            'is_active' => true,
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notificationConfig->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$this->user->id]],
        ]);

        WorkflowNotificationTemplate::create([
            'notification_id' => $notificationConfig->id,
            'channel_id' => $channel->id,
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        /** @var NotificationService $service */
        $service = app(NotificationService::class);
        $service->triggerForTransition(
            $order,
            PendingState::class,
            ProcessingState::class
        );

        Notification::assertSentTo($this->user, WorkflowNotification::class);
    }

    public function test_it_handles_notification_without_workflow(): void
    {
        // No workflow configured for this model
        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-001']);

        // Delete workflow to simulate no workflow scenario
        $this->workflow->delete();

        /** @var NotificationService $service */
        $service = app(NotificationService::class);
        $service->triggerForTransition(
            $order,
            PendingState::class,
            ProcessingState::class
        );

        // No exception should be thrown
        Notification::assertNothingSent();
    }
}
