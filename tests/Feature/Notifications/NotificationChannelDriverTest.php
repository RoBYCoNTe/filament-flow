<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Contracts\NotificationChannelDriver;
use RoBYCoNTe\FilamentFlow\Definition\Notification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationLog;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationTemplate;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use RoBYCoNTe\FilamentFlow\Support\NotificationChannelDriverRegistry;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use RuntimeException;

/**
 * The channels the host teaches the engine: a driver registered for a name receives the
 * prepared notification rendered — the placeholders filled, the recipients resolved — both
 * when the notification is configured in the database and when it travels code-first, and
 * its delivery is written down like the delivery of the engine's own channels.
 */
class NotificationChannelDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingDriver::$delivered = [];
    }

    public function test_a_configured_notification_is_delivered_by_the_driver_of_its_channel(): void
    {
        $user = $this->createTestUser(['email' => 'office@example.com']);
        $order = Order::create([
            'order_number' => 'ORD-PEC-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 100.00,
            'state' => 'pending',
        ]);

        $notification = WorkflowNotification::create([
            'workflow_id' => $this->createTestWorkflow()->id,
            'trigger_event' => 'on_transition',
            'name' => 'pec notice',
            'is_active' => true,
        ]);

        $channel = WorkflowNotificationChannel::create([
            'notification_id' => $notification->id,
            'channel_type' => 'pec',
            'channel_config' => ['mailbox' => 'protocol@pec.example'],
            'is_active' => true,
        ]);

        WorkflowNotificationTemplate::create([
            'notification_id' => $notification->id,
            'channel_id' => $channel->id,
            'subject' => 'Order {{record_id}} registered',
            'title' => 'Registered',
            'body' => 'The order {{record_id}} was registered.',
            'template_engine' => 'plain',
            'format' => 'plain',
        ]);

        WorkflowNotificationRecipient::create([
            'notification_id' => $notification->id,
            'recipient_type' => 'user',
            'recipient_config' => ['user_ids' => [$user->id]],
        ]);

        $this->registerDriver('pec', new RecordingDriver);

        app(NotificationService::class)->sendNotification(
            $notification,
            $order,
            Collection::make([$user]),
            [
                'config_id' => $notification->id,
                'channel' => 'pec',
                'channel_config' => ['mailbox' => 'protocol@pec.example'],
                'template' => [
                    'subject' => 'Order {{record_id}} registered',
                    'title' => 'Registered',
                    'body' => 'The order {{record_id}} was registered.',
                    'action_text' => null,
                    'action_url' => null,
                    'template_engine' => 'plain',
                    'format' => 'plain',
                    'variables' => [],
                ],
                'record_type' => $order::class,
                'record_id' => $order->getKey(),
                'context' => [],
                'priority' => 'medium',
            ],
        );

        $this->assertCount(1, RecordingDriver::$delivered);

        $delivered = RecordingDriver::$delivered[0];

        $this->assertSame($order->getKey(), $delivered['record']->getKey());
        $this->assertSame($user->id, $delivered['recipients']->first()->id);
        $this->assertSame(['mailbox' => 'protocol@pec.example'], $delivered['data']['channel_config']);
        $this->assertSame('Order '.$order->getKey().' registered', $delivered['data']['rendered']['subject']);
        $this->assertSame('The order '.$order->getKey().' was registered.', $delivered['data']['rendered']['body']);

        $this->assertDatabaseHas(WorkflowNotificationLog::class, [
            'notification_id' => $notification->id,
            'channel' => 'pec',
            'status' => 'sent',
            'user_id' => $user->id,
        ]);
    }

    public function test_a_prepared_notification_is_delivered_by_the_driver_of_its_channel(): void
    {
        $user = $this->createTestUser();
        $order = Order::create([
            'order_number' => 'ORD-PEC-002',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 100.00,
            'state' => 'pending',
        ]);

        $this->registerDriver('pec', new RecordingDriver);

        app(NotificationService::class)->sendPreparedNotification($order, Collection::make([$user]), [
            'channel' => 'pec',
            'channel_config' => [],
            'template' => [
                'subject' => 'Registered',
                'title' => 'Registered',
                'body' => 'The order was registered.',
                'template_engine' => 'plain',
                'format' => 'plain',
            ],
            'record_type' => $order::class,
            'record_id' => $order->getKey(),
            'context' => [],
        ]);

        $this->assertCount(1, RecordingDriver::$delivered);
        $this->assertSame('Registered', RecordingDriver::$delivered[0]['data']['rendered']['subject']);
    }

    public function test_a_failing_driver_is_written_down_and_does_not_break_the_run(): void
    {
        $user = $this->createTestUser();
        $order = Order::create([
            'order_number' => 'ORD-PEC-003',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 100.00,
            'state' => 'pending',
        ]);

        $notification = WorkflowNotification::create([
            'workflow_id' => $this->createTestWorkflow()->id,
            'trigger_event' => 'on_transition',
            'name' => 'pec notice',
            'is_active' => true,
        ]);

        $this->registerDriver('pec', new FailingDriver);

        app(NotificationService::class)->sendNotification(
            $notification,
            $order,
            Collection::make([$user]),
            [
                'config_id' => $notification->id,
                'channel' => 'pec',
                'channel_config' => [],
                'template' => null,
                'record_type' => $order::class,
                'record_id' => $order->getKey(),
                'context' => [],
                'priority' => 'medium',
            ],
        );

        $this->assertDatabaseHas(WorkflowNotificationLog::class, [
            'notification_id' => $notification->id,
            'channel' => 'pec',
            'status' => 'failed',
        ]);

        $this->assertStringContainsString(
            'unreachable',
            (string) WorkflowNotificationLog::query()
                ->where('notification_id', $notification->id)
                ->value('error_message'),
        );
    }

    public function test_a_definition_declares_a_custom_channel_by_name(): void
    {
        $notification = Notification::make('registered-pec', 'Registered')
            ->channel('pec', ['mailbox' => 'protocol@pec.example'])
            ->subject('Registered');

        $this->assertSame([
            ['channel_type' => 'pec', 'channel_config' => ['mailbox' => 'protocol@pec.example'], 'is_active' => true],
        ], $notification->channelList());

        $fromArray = Notification::fromArray($notification->toArray());

        $this->assertSame('pec', $fromArray->channelList()[0]['channel_type']);
        $this->assertSame(['mailbox' => 'protocol@pec.example'], $fromArray->channelList()[0]['channel_config']);
    }

    public function test_the_registry_builds_a_driver_through_the_container(): void
    {
        $registry = app(NotificationChannelDriverRegistry::class);

        $this->assertFalse($registry->has('pec'));

        $registry->register('pec', RecordingDriver::class);

        $this->assertTrue($registry->has('pec'));
        $this->assertInstanceOf(RecordingDriver::class, $registry->get('pec'));

        $registry->forget('pec');

        $this->assertFalse($registry->has('pec'));
    }

    public function test_the_configuration_seeds_the_registry(): void
    {
        config()->set('filament-flow.notifications.channel_drivers', [
            'pec' => RecordingDriver::class,
        ]);

        $this->assertTrue(app(NotificationChannelDriverRegistry::class)->has('pec'));
    }

    protected function registerDriver(string $channelType, NotificationChannelDriver $driver): void
    {
        app(NotificationChannelDriverRegistry::class)->register($channelType, $driver);
    }
}

/**
 * A driver that keeps what it is handed, so a test can look into it.
 */
final class RecordingDriver implements NotificationChannelDriver
{
    /** @var list<array{record: Model, recipients: Collection, data: array<string, mixed>}> */
    public static array $delivered = [];

    public function send(Model $record, Collection $recipients, array $notificationData): void
    {
        self::$delivered[] = [
            'record' => $record,
            'recipients' => $recipients,
            'data' => $notificationData,
        ];
    }
}

/**
 * A driver that always fails, so the log of a broken delivery can be read.
 */
final class FailingDriver implements NotificationChannelDriver
{
    public function send(Model $record, Collection $recipients, array $notificationData): void
    {
        throw new RuntimeException('The external service is unreachable.');
    }
}
