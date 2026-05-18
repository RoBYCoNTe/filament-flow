<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Notifications;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowUserInvolvement;
use RoBYCoNTe\FilamentFlow\Services\RecipientResolver;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class RecipientResolverTest extends TestCase
{
    protected RecipientResolver $resolver;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(RecipientResolver::class);

        // Create test order with initial state
        $this->order = Order::create([
            'order_number' => 'ORD-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_resolves_recipients_by_user_ids(): void
    {
        $user1 = $this->createTestUser(['email' => 'user1@example.com']);
        $user2 = User::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
        ]);

        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$user1->id, $user2->id]],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(2, $recipients);
        $this->assertTrue($recipients->contains('id', $user1->id));
        $this->assertTrue($recipients->contains('id', $user2->id));
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_returns_empty_collection_for_empty_user_ids(): void
    {
        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => []],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(0, $recipients);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_resolves_recipients_by_role(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'role',
            'recipient_config' => ['roles' => ['admin']],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(1, $recipients);
        $this->assertEquals($admin->id, $recipients->first()->id);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_returns_empty_collection_for_empty_roles(): void
    {
        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'role',
            'recipient_config' => ['roles' => []],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(0, $recipients);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_resolves_record_owner(): void
    {
        $owner = $this->createTestUser(['email' => 'owner@example.com']);
        $this->order->user_id = $owner->id;
        $this->order->save();

        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'record_owner',
            'recipient_config' => [],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(1, $recipients);
        $this->assertEquals($owner->id, $recipients->first()->id);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_returns_empty_for_record_without_owner(): void
    {
        $this->order->user_id = null;
        $this->order->save();

        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'record_owner',
            'recipient_config' => [],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(0, $recipients);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_resolves_record_owner_with_custom_field(): void
    {
        // Create a test case where owner_field is customized
        config()->set('filament-flow.state_access.owner_field', 'user_id');

        $owner = $this->createTestUser(['email' => 'owner@example.com']);
        $this->order->user_id = $owner->id;
        $this->order->save();

        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'record_owner',
            'recipient_config' => ['owner_field' => 'user_id'],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(1, $recipients);
        $this->assertEquals($owner->id, $recipients->first()->id);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function it_resolves_users_by_involvement_type(): void
    {
        $reviewer = $this->createTestUser(['email' => 'reviewer@example.com']);

        WorkflowUserInvolvement::create([
            'model_type' => Order::class,
            'model_id' => $this->order->id,
            'user_id' => $reviewer->id,
            'involvement_type' => 'reviewer',
        ]);

        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'involvement_type',
            'recipient_config' => ['involvement_type' => 'reviewer'],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(1, $recipients);
        $this->assertEquals($reviewer->id, $recipients->first()->id);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_returns_empty_for_no_involvement(): void
    {
        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'involvement_type',
            'recipient_config' => ['involvement_type' => 'reviewer'],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(0, $recipients);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_resolves_all_involved_users(): void
    {
        $owner = $this->createTestUser(['email' => 'owner@example.com']);
        $this->order->user_id = $owner->id;
        $this->order->save();

        $involved = User::create([
            'name' => 'Involved User',
            'email' => 'involved@example.com',
            'password' => bcrypt('password'),
        ]);

        WorkflowUserInvolvement::create([
            'model_type' => Order::class,
            'model_id' => $this->order->id,
            'user_id' => $involved->id,
            'involvement_type' => 'reviewer',
        ]);

        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'all_involved',
            'recipient_config' => [],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        // Should include both owner and involved user
        $this->assertTrue($recipients->contains('id', $owner->id));
        $this->assertTrue($recipients->contains('id', $involved->id));
    }

    public function test_it_resolves_all_and_removes_duplicates(): void
    {
        $user1 = $this->createTestUser(['email' => 'user1@example.com']);
        $user2 = User::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
        ]);

        $recipientConfig1 = new WorkflowNotificationRecipient([
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$user1->id, $user2->id]],
            'sort_order' => 1,
        ]);
        $recipientConfig1->id = 1;

        $recipientConfig2 = new WorkflowNotificationRecipient([
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$user1->id]], // Duplicate
            'sort_order' => 2,
        ]);
        $recipientConfig2->id = 2;

        $configs = collect([$recipientConfig1, $recipientConfig2]);

        $recipients = $this->resolver->resolveAll($configs, $this->order);

        // Should have unique users only
        $this->assertCount(2, $recipients);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_returns_empty_for_unknown_recipient_type(): void
    {
        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'unknown_type',
            'recipient_config' => [],
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(0, $recipients);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_resolves_custom_class_recipient(): void
    {
        // This test verifies the custom class resolution mechanism
        // but doesn't actually call a custom class since we don't have one

        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'custom_class',
            'recipient_config' => [
                'class' => 'NonExistentClass',
                'method' => 'resolve',
            ],
        ]);

        // Should return empty when class doesn't exist
        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(0, $recipients);
    }

    /**
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function test_it_handles_null_recipient_config(): void
    {
        $recipientConfig = new WorkflowNotificationRecipient([
            'recipient_type' => 'user',
            'recipient_config' => null,
        ]);

        $recipients = $this->resolver->resolve($recipientConfig, $this->order);

        $this->assertCount(0, $recipients);
    }
}
