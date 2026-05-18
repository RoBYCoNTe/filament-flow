<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Builders;

use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowNotificationBuilderTest extends TestCase
{
    public function test_make_creates_instance(): void
    {
        $builder = WorkflowNotificationBuilder::make();

        $this->assertInstanceOf(WorkflowNotificationBuilder::class, $builder);
    }

    public function test_defaults(): void
    {
        $builder = WorkflowNotificationBuilder::make();

        $this->assertEquals('database', $builder->getChannel());
        $this->assertEquals('medium', $builder->getPriority());
        $this->assertEquals('plain', $builder->getTemplateEngine());
        $this->assertEquals('immediate', $builder->getTiming());
        $this->assertEquals(0, $builder->getDelayMinutes());
        $this->assertNull($builder->getName());
        $this->assertEquals('', $builder->getTitle());
        $this->assertEquals('', $builder->getBody());
        $this->assertNull($builder->getSubject());
        $this->assertNull($builder->getActionUrl());
        $this->assertNull($builder->getActionText());
        $this->assertEmpty($builder->getRecipients());
        $this->assertEmpty($builder->getChannelConfig());
        $this->assertEmpty($builder->getMetadata());
    }

    public function test_name_setter_and_getter(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->name('order_shipped');

        $this->assertEquals('order_shipped', $builder->getName());
    }

    public function test_channel_setter_and_getter(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->channel('mail');

        $this->assertEquals('mail', $builder->getChannel());
        $this->assertEmpty($builder->getChannelConfig());
    }

    public function test_channel_with_config(): void
    {
        $config = ['from' => 'noreply@example.com', 'cc' => ['admin@example.com']];
        $builder = WorkflowNotificationBuilder::make()
            ->channel('mail', $config);

        $this->assertEquals('mail', $builder->getChannel());
        $this->assertEquals($config, $builder->getChannelConfig());
    }

    public function test_recipients_array(): void
    {
        $recipients = ['@owner', 'role:admin', 'user:1'];
        $builder = WorkflowNotificationBuilder::make()
            ->recipients($recipients);

        $this->assertEquals($recipients, $builder->getRecipients());
    }

    public function test_recipients_callable(): void
    {
        $callback = fn ($record) => collect(['user:1']);
        $builder = WorkflowNotificationBuilder::make()
            ->recipients($callback);

        $recipients = $builder->getRecipients();
        $this->assertCount(1, $recipients);
        $this->assertIsCallable($recipients[0]);
    }

    public function test_title_and_body(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->title('Order {{order_number}} Updated')
            ->body('Your order has been moved to {{state}}.');

        $this->assertEquals('Order {{order_number}} Updated', $builder->getTitle());
        $this->assertEquals('Your order has been moved to {{state}}.', $builder->getBody());
    }

    public function test_subject(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->subject('Order Status Update');

        $this->assertEquals('Order Status Update', $builder->getSubject());
    }

    public function test_action_url_with_text(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->actionUrl('https://example.com/orders/{{id}}', 'View Order');

        $this->assertEquals('https://example.com/orders/{{id}}', $builder->getActionUrl());
        $this->assertEquals('View Order', $builder->getActionText());
    }

    public function test_action_text_separate(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->actionUrl('https://example.com')
            ->actionText('Click Here');

        $this->assertEquals('https://example.com', $builder->getActionUrl());
        $this->assertEquals('Click Here', $builder->getActionText());
    }

    public function test_priority(): void
    {
        foreach (['low', 'medium', 'high', 'urgent'] as $priority) {
            $builder = WorkflowNotificationBuilder::make()
                ->priority($priority);

            $this->assertEquals($priority, $builder->getPriority());
        }
    }

    public function test_template_engine(): void
    {
        foreach (['plain', 'blade', 'mustache'] as $engine) {
            $builder = WorkflowNotificationBuilder::make()
                ->templateEngine($engine);

            $this->assertEquals($engine, $builder->getTemplateEngine());
        }
    }

    public function test_immediate_timing(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->delay(30)
            ->immediate();

        $this->assertEquals('immediate', $builder->getTiming());
        $this->assertEquals(0, $builder->getDelayMinutes());
    }

    public function test_delay_timing(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->delay(15);

        $this->assertEquals('delayed', $builder->getTiming());
        $this->assertEquals(15, $builder->getDelayMinutes());
    }

    public function test_metadata_merges(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->metadata(['key1' => 'value1'])
            ->metadata(['key2' => 'value2']);

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $builder->getMetadata());
    }

    public function test_fluent_chaining(): void
    {
        $builder = WorkflowNotificationBuilder::make();

        $result = $builder
            ->name('test')
            ->channel('mail', ['from' => 'test@example.com'])
            ->recipients(['@owner'])
            ->title('Title')
            ->body('Body')
            ->subject('Subject')
            ->actionUrl('https://example.com', 'Click')
            ->actionText('Button')
            ->priority('high')
            ->templateEngine('blade')
            ->delay(10)
            ->metadata(['key' => 'value']);

        $this->assertSame($builder, $result);
    }

    public function test_to_array_complete_structure(): void
    {
        $builder = WorkflowNotificationBuilder::make()
            ->name('order_notification')
            ->channel('mail', ['from' => 'noreply@example.com'])
            ->recipients(['@owner', 'role:admin'])
            ->title('Order Updated')
            ->body('Your order has been updated.')
            ->subject('Order Status')
            ->actionUrl('https://example.com/orders/1', 'View')
            ->priority('high')
            ->templateEngine('blade')
            ->delay(5)
            ->metadata(['source' => 'workflow']);

        $array = $builder->toArray();

        $this->assertEquals('order_notification', $array['name']);
        $this->assertEquals('mail', $array['channel']);
        $this->assertEquals(['from' => 'noreply@example.com'], $array['channel_config']);
        $this->assertEquals(['@owner', 'role:admin'], $array['recipients']);
        $this->assertEquals('high', $array['priority']);
        $this->assertEquals('delayed', $array['timing']);
        $this->assertEquals(5, $array['delay_minutes']);
        $this->assertEquals(['source' => 'workflow'], $array['metadata']);

        // Template sub-array
        $this->assertArrayHasKey('template', $array);
        $this->assertEquals('Order Status', $array['template']['subject']);
        $this->assertEquals('Order Updated', $array['template']['title']);
        $this->assertEquals('Your order has been updated.', $array['template']['body']);
        $this->assertEquals('https://example.com/orders/1', $array['template']['action_url']);
        $this->assertEquals('View', $array['template']['action_text']);
        $this->assertEquals('blade', $array['template']['template_engine']);
    }
}
