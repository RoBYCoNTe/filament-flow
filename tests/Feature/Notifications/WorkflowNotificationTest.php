<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Notifications;

use Filament\Support\Icons\Heroicon;
use Illuminate\Notifications\Messages\MailMessage;
use RoBYCoNTe\FilamentFlow\Notifications\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowNotificationTest extends TestCase
{
    protected Order $order;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createTestUser();

        $this->order = Order::create([
            'order_number' => 'ORD-TEST-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 150.00,
        ]);
    }

    public function test_it_returns_correct_via_channels_for_database(): void
    {
        $data = [
            'channel' => 'database',
            'template' => [
                'title' => 'Test Title',
                'body' => 'Test Body',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);

        $this->assertEquals(['database'], $notification->via($this->user));
    }

    public function test_it_returns_correct_via_channels_for_mail(): void
    {
        $data = [
            'channel' => 'mail',
            'template' => [
                'title' => 'Test Title',
                'body' => 'Test Body',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);

        $this->assertEquals(['mail'], $notification->via($this->user));
    }

    public function test_unsupported_channel_falls_back_to_database(): void
    {
        $data = [
            'channel' => 'sms',
            'template' => [
                'title' => 'Test Title',
                'body' => 'Test Body',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);

        $this->assertEquals(['database'], $notification->via($this->user));
    }

    public function test_it_defaults_to_database_channel(): void
    {
        $data = [
            'template' => [
                'title' => 'Test Title',
                'body' => 'Test Body',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);

        $this->assertEquals(['database'], $notification->via($this->user));
    }

    public function test_it_renders_plain_template_variables(): void
    {
        $data = [
            'channel' => 'database',
            'template' => [
                'title' => 'Order {{order_number}} Updated',
                'body' => 'Order for {{customer_name}} has changed.',
                'template_engine' => 'plain',
            ],
            'context' => [
                'trigger' => 'transition',
                'from_state' => PendingState::class,
                'to_state' => ProcessingState::class,
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('Order ORD-TEST-001 Updated', $arrayData['title']);
        $this->assertEquals('Order for John Doe has changed.', $arrayData['body']);
    }

    public function test_it_renders_template_with_record_id(): void
    {
        $data = [
            'channel' => 'database',
            'template' => [
                'title' => 'Record #{{record_id}}',
                'body' => 'Record type: {{record_type}}',
                'template_engine' => 'plain',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('Record #'.$this->order->id, $arrayData['title']);
        $this->assertEquals('Record type: Order', $arrayData['body']);
    }

    public function test_it_renders_template_with_app_info(): void
    {
        config()->set('app.name', 'Test App');
        config()->set('app.url', 'https://test.example.com');

        $data = [
            'channel' => 'database',
            'template' => [
                'title' => 'Notification from {{app_name}}',
                'body' => 'Visit {{app_url}} for more info.',
                'template_engine' => 'plain',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('Notification from Test App', $arrayData['title']);
        $this->assertEquals('Visit https://test.example.com for more info.', $arrayData['body']);
    }

    public function test_it_renders_template_with_state_info(): void
    {
        $data = [
            'channel' => 'database',
            'template' => [
                'title' => 'State changed',
                'body' => 'From {{from_state}} to {{to_state}}',
                'template_engine' => 'plain',
            ],
            'context' => [
                'from_state' => 'pending',
                'to_state' => 'processing',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('From pending to processing', $arrayData['body']);
    }

    public function test_it_includes_action_url_in_array(): void
    {
        $data = [
            'channel' => 'database',
            'template' => [
                'title' => 'Test',
                'body' => 'Test body',
                'action_url' => 'https://example.com/orders/{{record_id}}',
                'action_text' => 'View Order',
                'template_engine' => 'plain',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $action = collect($arrayData['actions'])->first();
        $this->assertEquals('https://example.com/orders/'.$this->order->id, $action['url']);
        $this->assertEquals('View Order', $action['label']);
    }

    public function test_it_sets_correct_icon_for_transition_trigger(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'context' => ['trigger' => 'transition'],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('heroicon-'.Heroicon::OutlinedArrowPath->value, $arrayData['icon']);
    }

    public function test_it_sets_correct_icon_for_state_enter_trigger(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'context' => ['trigger' => 'state_enter'],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('heroicon-'.Heroicon::OutlinedArrowRightCircle->value, $arrayData['icon']);
    }

    public function test_it_sets_correct_icon_for_assignment_trigger(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'context' => ['trigger' => 'assignment'],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('heroicon-'.Heroicon::OutlinedUserPlus->value, $arrayData['icon']);
    }

    public function test_it_sets_default_icon_for_unknown_trigger(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'context' => ['trigger' => 'unknown'],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('heroicon-'.Heroicon::OutlinedBell->value, $arrayData['icon']);
    }

    public function test_it_sets_correct_color_for_urgent_priority(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'priority' => 'urgent',
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('danger', $arrayData['color']);
    }

    public function test_it_sets_correct_color_for_high_priority(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'priority' => 'high',
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('warning', $arrayData['color']);
    }

    public function test_it_sets_correct_color_for_medium_priority(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'priority' => 'medium',
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('primary', $arrayData['color']);
    }

    public function test_it_sets_correct_color_for_low_priority(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'priority' => 'low',
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('gray', $arrayData['color']);
    }

    public function test_it_includes_record_info_in_array(): void
    {
        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'record_type' => Order::class,
            'record_id' => $this->order->id,
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals(Order::class, $arrayData['record_type']);
        $this->assertEquals($this->order->id, $arrayData['record_id']);
    }

    public function test_it_includes_context_in_array(): void
    {
        $context = [
            'trigger' => 'transition',
            'from_state' => 'pending',
            'to_state' => 'processing',
        ];

        $data = [
            'channel' => 'database',
            'template' => ['title' => 'Test', 'body' => 'Test'],
            'context' => $context,
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals($context, $arrayData['context']);
    }

    public function test_it_handles_empty_template(): void
    {
        $data = [
            'channel' => 'database',
            'template' => [],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        // Should use defaults
        $this->assertEquals('Workflow Notification', $arrayData['title']);
        $this->assertEquals('A workflow event has occurred.', $arrayData['body']);
    }

    /** @noinspection PhpConditionAlreadyCheckedInspection */
    public function test_it_creates_mail_message_correctly(): void
    {
        $data = [
            'channel' => 'mail',
            'template' => [
                'subject' => 'Order {{order_number}} - Status Update',
                'title' => 'Hello!',
                'body' => 'Your order has been updated.',
                'action_url' => 'https://example.com',
                'action_text' => 'View Order',
                'template_engine' => 'plain',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $mailMessage = $notification->toMail($this->user);

        $this->assertInstanceOf(MailMessage::class, $mailMessage);
    }

    public function test_it_handles_mustache_template_escaping(): void
    {
        $this->order->customer_name = '<script>alert("xss")</script>';
        $this->order->save();

        $data = [
            'channel' => 'database',
            'template' => [
                'title' => 'Customer: {{customer_name}}',
                'body' => 'Test',
                'template_engine' => 'mustache',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        // Mustache should escape HTML
        $this->assertStringContainsString('&lt;script&gt;', $arrayData['title']);
    }

    public function test_it_renders_spaced_variables(): void
    {
        $data = [
            'channel' => 'database',
            'template' => [
                'title' => 'Order {{ order_number }} Updated',
                'body' => '{{ customer_name }}',
                'template_engine' => 'plain',
            ],
        ];

        $notification = new WorkflowNotification($data, $this->order);
        $arrayData = $notification->toArray($this->user);

        $this->assertEquals('Order ORD-TEST-001 Updated', $arrayData['title']);
        $this->assertEquals('John Doe', $arrayData['body']);
    }
}
