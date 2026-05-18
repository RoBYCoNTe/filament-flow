<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateNotifications;

/**
 * A Processing State that implements code-first notifications.
 */
class NotifyingProcessingState extends NotifyingOrderState implements HasStateNotifications
{
    public function getLabel(): string
    {
        return 'Processing';
    }

    public function getDescription(): string
    {
        return 'Order is being processed';
    }

    public static function getSortOrder(): int
    {
        return 20;
    }

    /**
     * Notifications to send when entering this state.
     */
    public function onEnterNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->name('processing_started')
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Order Processing Started')
                ->body('Your order {{order_number}} is now being processed.')
                ->priority('medium'),
        ];
    }

    /**
     * Notifications to send when exiting this state.
     */
    public function onExitNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->name('processing_completed')
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Processing Complete')
                ->body('Your order {{order_number}} has left the processing state.')
                ->priority('low'),
        ];
    }
}
