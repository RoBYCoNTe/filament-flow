<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;

/**
 * Interface for Transition classes that define notifications.
 *
 * Implement this interface in your Transition classes to define notifications
 * that should be sent when the transition is executed.
 */
interface HasTransitionNotifications
{
    /**
     * Define notifications to send when this transition is executed.
     *
     * @return array<WorkflowNotificationBuilder>
     */
    public function notifications(): array;
}
