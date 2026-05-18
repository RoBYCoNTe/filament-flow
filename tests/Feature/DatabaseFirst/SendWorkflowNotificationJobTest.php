<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use Illuminate\Contracts\Queue\ShouldQueue;
use RoBYCoNTe\FilamentFlow\Jobs\SendWorkflowNotification;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class SendWorkflowNotificationJobTest extends TestCase
{
    public function test_job_is_queueable(): void
    {
        $job = new SendWorkflowNotification(
            notificationConfigId: 1,
            recordType: 'App\\Models\\Order',
            recordId: 1,
            recipientIds: [1, 2],
            notificationData: ['key' => 'value'],
        );

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    public function test_job_has_correct_retry_config(): void
    {
        $job = new SendWorkflowNotification(
            notificationConfigId: 1,
            recordType: 'App\\Models\\Order',
            recordId: 1,
            recipientIds: [1],
            notificationData: [],
        );

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    public function test_job_tags(): void
    {
        $job = new SendWorkflowNotification(
            notificationConfigId: 42,
            recordType: 'App\\Models\\Order',
            recordId: 7,
            recipientIds: [1],
            notificationData: [],
        );

        $tags = $job->tags();

        $this->assertContains('workflow-notification', $tags);
        $this->assertContains('config:42', $tags);
        $this->assertContains('record:App\\Models\\Order:7', $tags);
    }

    public function test_handle_returns_early_when_config_not_found(): void
    {
        $job = new SendWorkflowNotification(
            notificationConfigId: 999,
            recordType: 'App\\Models\\Order',
            recordId: 1,
            recipientIds: [1],
            notificationData: [],
        );

        // Should not throw — just returns early with a log warning
        $notificationService = app(NotificationService::class);
        $job->handle($notificationService);
        $this->assertTrue(true); // No exception means success
    }
}
