<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Notifications;

use Exception;
use Illuminate\Support\Facades\Notification;
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Notifications\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use RoBYCoNTe\FilamentFlow\Services\RecipientResolver;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\NotifyingOrder;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\NotifyingPendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\NotifyingProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions\NotifyingToProcessingTransition;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Throwable;

class CodeFirstNotificationTest extends TestCase
{
    protected User $owner;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable notifications
        config()->set('filament-flow.notifications.enabled', true);

        // Create users
        $this->owner = $this->createTestUser([
            'email' => 'owner@example.com',
            'name' => 'Order Owner',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    /**
     * Helper to create a notifying order
     */
    protected function createOrder(array $data = []): NotifyingOrder
    {
        return NotifyingOrder::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 100.00,
            'state' => NotifyingPendingState::class,
            'user_id' => $this->owner->id,
        ], $data));
    }

    // ========================================
    // WorkflowNotificationBuilder Tests
    // ========================================

    public function test_builder_can_set_all_properties(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->name('test_notification')
            ->channel('mail', ['from' => 'noreply@example.com'])
            ->recipients(['@owner', 'role:admin'])
            ->title('Test Title')
            ->body('Test body content')
            ->subject('Test Subject')
            ->actionUrl('/orders/{{record_id}}', 'View Order')
            ->priority('high')
            ->templateEngine('plain')
            ->delay(30)
            ->metadata(['key' => 'value']);

        $this->assertEquals('test_notification', $builder->getName());
        $this->assertEquals('mail', $builder->getChannel());
        $this->assertEquals(['from' => 'noreply@example.com'], $builder->getChannelConfig());
        $this->assertEquals(['@owner', 'role:admin'], $builder->getRecipients());
        $this->assertEquals('Test Title', $builder->getTitle());
        $this->assertEquals('Test body content', $builder->getBody());
        $this->assertEquals('Test Subject', $builder->getSubject());
        $this->assertEquals('/orders/{{record_id}}', $builder->getActionUrl());
        $this->assertEquals('View Order', $builder->getActionText());
        $this->assertEquals('high', $builder->getPriority());
        $this->assertEquals('plain', $builder->getTemplateEngine());
        $this->assertEquals('delayed', $builder->getTiming());
        $this->assertEquals(30, $builder->getDelayMinutes());
        $this->assertEquals(['key' => 'value'], $builder->getMetadata());
    }

    public function test_builder_to_array_returns_correct_structure(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->channel('database')
            ->recipients(['@owner'])
            ->title('Order Updated')
            ->body('Your order has been updated.');

        $array = $builder->toArray();

        $this->assertArrayHasKey('channel', $array);
        $this->assertArrayHasKey('recipients', $array);
        $this->assertArrayHasKey('template', $array);
        $this->assertArrayHasKey('priority', $array);
        $this->assertArrayHasKey('timing', $array);

        $this->assertEquals('database', $array['channel']);
        $this->assertEquals(['@owner'], $array['recipients']);
        $this->assertEquals('Order Updated', $array['template']['title']);
        $this->assertEquals('Your order has been updated.', $array['template']['body']);
    }

    public function test_builder_immediate_timing(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->delay(60)
            ->immediate();

        $this->assertEquals('immediate', $builder->getTiming());
        $this->assertEquals(0, $builder->getDelayMinutes());
    }

    // ========================================
    // RecipientResolver Code-First Tests
    // ========================================

    public function test_recipient_resolver_handles_owner_shortcut(): void
    {
        $order = $this->createOrder();

        $resolver = app(RecipientResolver::class);
        $recipients = $resolver->resolveCodeFirst(['@owner'], $order);

        $this->assertCount(1, $recipients);
        $this->assertEquals($this->owner->id, $recipients->first()->id);
    }

    public function test_recipient_resolver_handles_role_prefix(): void
    {
        $order = $this->createOrder();

        $resolver = app(RecipientResolver::class);
        $recipients = $resolver->resolveCodeFirst(['role:admin'], $order);

        $this->assertCount(1, $recipients);
        $this->assertEquals($this->admin->id, $recipients->first()->id);
    }

    public function test_recipient_resolver_handles_user_prefix(): void
    {
        $order = $this->createOrder();

        $resolver = app(RecipientResolver::class);
        $recipients = $resolver->resolveCodeFirst(["user:{$this->owner->id}"], $order);

        $this->assertCount(1, $recipients);
        $this->assertEquals($this->owner->id, $recipients->first()->id);
    }

    public function test_recipient_resolver_handles_multiple_user_ids(): void
    {
        $order = $this->createOrder();

        $resolver = app(RecipientResolver::class);
        $recipients = $resolver->resolveCodeFirst(
            ["user:{$this->owner->id},{$this->admin->id}"],
            $order
        );

        $this->assertCount(2, $recipients);
        $this->assertTrue($recipients->contains('id', $this->owner->id));
        $this->assertTrue($recipients->contains('id', $this->admin->id));
    }

    public function test_recipient_resolver_handles_callable(): void
    {
        $order = $this->createOrder();

        $resolver = app(RecipientResolver::class);
        $recipients = $resolver->resolveCodeFirst(
            fn ($record) => User::where('role', 'admin')->get(),
            $order
        );

        $this->assertCount(1, $recipients);
        $this->assertEquals($this->admin->id, $recipients->first()->id);
    }

    public function test_recipient_resolver_removes_duplicates(): void
    {
        $order = $this->createOrder();

        $resolver = app(RecipientResolver::class);
        $recipients = $resolver->resolveCodeFirst(
            ['@owner', "user:{$this->owner->id}"],
            $order
        );

        // Should be only 1 because duplicates are removed
        $this->assertCount(1, $recipients);
    }

    public function test_recipient_resolver_handles_mixed_formats(): void
    {
        $order = $this->createOrder();

        $resolver = app(RecipientResolver::class);
        $recipients = $resolver->resolveCodeFirst(
            ['@owner', 'role:admin'],
            $order
        );

        $this->assertCount(2, $recipients);
        $this->assertTrue($recipients->contains('id', $this->owner->id));
        $this->assertTrue($recipients->contains('id', $this->admin->id));
    }

    // ========================================
    // NotificationService Code-First Tests
    // ========================================

    /**
     * @throws Exception
     */
    public function test_notification_service_dispatches_code_first_notifications(): void
    {
        Notification::fake();

        $order = $this->createOrder();

        $builders = [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Test Notification')
                ->body('This is a test.'),
        ];

        $service = app(NotificationService::class);
        $service->dispatchCodeFirstNotifications($builders, $order, ['trigger' => 'test']);

        Notification::assertSentTo($this->owner, WorkflowNotification::class);
    }

    public function test_notification_service_does_not_send_without_recipients(): void
    {
        Notification::fake();

        $order = $this->createOrder(['user_id' => null]); // No owner

        $builders = [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner']) // Owner won't exist
                ->title('Test')
                ->body('Test'),
        ];

        $service = app(NotificationService::class);
        $service->dispatchCodeFirstNotifications($builders, $order);

        Notification::assertNothingSent();
    }

    // ========================================
    // Transition Code-First Notification Tests
    // ========================================

    /**
     * @throws Exception
     */
    public function test_transition_notifications_via_service(): void
    {
        Notification::fake();

        $order = $this->createOrder();

        // Create the transition instance manually
        $transition = new NotifyingToProcessingTransition(
            $order,
            []
        );

        // Trigger notifications via the service
        $service = app(NotificationService::class);
        $service->triggerForTransition(
            $order,
            NotifyingPendingState::class,
            NotifyingProcessingState::class,
            [],
            $transition
        );

        // Should send notifications defined in the transition
        Notification::assertSentTo($this->owner, WorkflowNotification::class);
        Notification::assertSentTo($this->admin, WorkflowNotification::class);
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_transition_notifications_via_model_transition(): void
    {
        Notification::fake();

        $order = $this->createOrder();

        // Transition to processing - should trigger both state and transition notifications
        $order->transitionTo(NotifyingProcessingState::class);

        // State notifications should be sent to owner
        Notification::assertSentTo($this->owner, WorkflowNotification::class);
    }

    // ========================================
    // State Code-First Notification Tests
    // ========================================

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_state_exit_notifications_are_sent(): void
    {
        Notification::fake();

        $order = $this->createOrder();

        // Transition should trigger exit notifications from PendingState
        $order->transitionTo(NotifyingProcessingState::class);

        // NotifyingPendingState has onExitNotifications
        Notification::assertSentTo(
            $this->owner,
            WorkflowNotification::class,
            function ($notification, $channels, $notifiable) {
                $data = $notification->toArray($notifiable);

                // Check if it's the exit notification
                return str_contains($data['title'] ?? '', 'Started')
                    || str_contains($data['title'] ?? '', 'Processing')
                    || str_contains($data['title'] ?? '', 'Transitioned');
            }
        );
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_state_enter_notifications_are_sent(): void
    {
        Notification::fake();

        $order = $this->createOrder();

        // Transition should trigger enter notifications for ProcessingState
        $order->transitionTo(NotifyingProcessingState::class);

        // NotifyingProcessingState has onEnterNotifications
        Notification::assertSentTo(
            $this->owner,
            WorkflowNotification::class,
            function ($notification, $channels, $notifiable) {
                $data = $notification->toArray($notifiable);

                return str_contains($data['title'] ?? '', 'Processing')
                    || str_contains($data['body'] ?? '', 'processed');
            }
        );
    }

    /**
     * @throws UnauthorizedTransitionException
     * @throws Throwable
     */
    public function test_notifications_disabled_does_not_send(): void
    {
        config()->set('filament-flow.notifications.enabled', false);

        Notification::fake();

        $order = $this->createOrder();
        $order->transitionTo(NotifyingProcessingState::class);

        Notification::assertNothingSent();
    }

    // ========================================
    // Template Variable Rendering Tests
    // ========================================

    /**
     * @throws Exception
     */
    public function test_code_first_notification_renders_template_variables(): void
    {
        Notification::fake();

        $order = $this->createOrder(['order_number' => 'ORD-12345']);

        $builders = [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Order {{order_number}}')
                ->body('Customer: {{customer_name}}'),
        ];

        $service = app(NotificationService::class);
        $service->dispatchCodeFirstNotifications($builders, $order);

        Notification::assertSentTo(
            $this->owner,
            WorkflowNotification::class,
            function ($notification, $channels, $notifiable) {
                $data = $notification->toArray($notifiable);

                return $data['title'] === 'Order ORD-12345'
                    && $data['body'] === 'Customer: John Doe';
            }
        );
    }

    // ========================================
    // Priority and Context Tests
    // ========================================

    /**
     * @throws Exception
     */
    public function test_code_first_notification_includes_priority(): void
    {
        Notification::fake();

        $order = $this->createOrder();

        $builders = [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Urgent')
                ->body('Important notification')
                ->priority('urgent'),
        ];

        $service = app(NotificationService::class);
        $service->dispatchCodeFirstNotifications($builders, $order);

        Notification::assertSentTo(
            $this->owner,
            WorkflowNotification::class,
            function ($notification, $channels, $notifiable) {
                $data = $notification->toArray($notifiable);

                return ($data['color'] ?? '') === 'danger'; // urgent = danger color
            }
        );
    }

    /**
     * @throws Exception
     */
    public function test_code_first_notification_includes_context(): void
    {
        Notification::fake();

        $order = $this->createOrder();

        $context = [
            'trigger' => 'transition',
            'from_state' => NotifyingPendingState::class,
            'to_state' => NotifyingProcessingState::class,
        ];

        $builders = [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Test')
                ->body('Test'),
        ];

        $service = app(NotificationService::class);
        $service->dispatchCodeFirstNotifications($builders, $order, $context);

        Notification::assertSentTo(
            $this->owner,
            WorkflowNotification::class,
            function ($notification, $channels, $notifiable) {
                $data = $notification->toArray($notifiable);

                return ($data['context']['trigger'] ?? '') === 'transition';
            }
        );
    }
}
