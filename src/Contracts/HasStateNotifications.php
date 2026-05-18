<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;

/**
 * Interface for State classes that define notifications.
 *
 * Implement this interface in your State classes to define notifications
 * that should be sent when entering or exiting the state.
 */
interface HasStateNotifications
{
    /**
     * Define notifications to send when entering this state.
     *
     * @return array<WorkflowNotificationBuilder>
     */
    public function onEnterNotifications(): array;

    /**
     * Define notifications to send when exiting this state.
     *
     * @return array<WorkflowNotificationBuilder>
     */
    public function onExitNotifications(): array;
}
