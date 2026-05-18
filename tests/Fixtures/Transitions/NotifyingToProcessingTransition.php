<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions;

use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasTransitionNotifications;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\NotifyingOrder;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\NotifyingProcessingState;
use Spatie\ModelStates\Transition;

/**
 * A Transition that implements code-first notifications.
 */
final class NotifyingToProcessingTransition extends Transition implements HasTransitionNotifications
{
    public function __construct(
        private readonly NotifyingOrder $order
    ) {}

    public function handle(): NotifyingOrder
    {
        $this->order->state = new NotifyingProcessingState($this->order);
        $this->order->processed_at = now();
        $this->order->save();

        return $this->order;
    }

    /**
     * Notifications to send when this transition is executed.
     */
    public function notifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->name('transition_notification')
                ->channel('database')
                ->recipients(['@owner', 'role:admin'])
                ->title('Order Transitioned')
                ->body('Order {{order_number}} has been moved from pending to processing.')
                ->priority('high'),

            WorkflowNotificationBuilder::make()
                ->name('admin_notification')
                ->channel('database')
                ->recipients(['role:admin'])
                ->title('Order Requires Attention')
                ->body('Order {{order_number}} ({{customer_name}}) needs processing.')
                ->actionUrl('/orders/{{record_id}}', 'View Order'),
        ];
    }

    public function canTransition(): bool
    {
        return $this->order->total_amount > 0;
    }
}
