<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\States;

use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateNotifications;

/**
 * A Pending State that implements code-first notifications.
 */
class NotifyingPendingState extends NotifyingOrderState implements HasStateNotifications
{
    public function getLabel(): string
    {
        return 'Pending';
    }

    public function getDescription(): string
    {
        return 'Order is pending';
    }

    public static function getSortOrder(): int
    {
        return 10;
    }

    /**
     * Notifications to send when entering this state.
     */
    public function onEnterNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->name('order_created')
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Order Created')
                ->body('Your order {{order_number}} has been created.')
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
                ->name('pending_exited')
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Order Started')
                ->body('Your order {{order_number}} has left the pending state and is now being processed.')
                ->priority('medium'),
        ];
    }
}
