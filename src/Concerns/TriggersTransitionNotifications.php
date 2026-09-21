<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use Spatie\ModelStates\State;

/**
 * Notifies the configured recipients when a transition runs. Composed into
 * HasDatabaseTransitions.
 */
trait TriggersTransitionNotifications
{
    /**
     * Trigger notifications for a transition.
     *
     * Supports both database-first and code-first notifications.
     */
    protected function triggerTransitionNotifications(string|State $fromState, string|State $toState): void
    {
        // Check if notifications are enabled
        if (! config('filament-flow.notifications.enabled', true)) {
            return;
        }

        try {
            $fromStateClass = is_string($fromState) ? $fromState : get_class($fromState);
            $toStateClass = is_string($toState) ? $toState : get_class($toState);

            $notificationService = app(NotificationService::class);
            $notificationService->triggerForTransition(
                $this,
                $fromStateClass,
                $toStateClass,
                $this->pendingTransitionData ?? [],
                $this->pendingTransitionInstance // Pass transition instance for code-first
            );
        } catch (Exception $e) {
            // Don't fail the transition if notification fails
            report($e);
        }
    }
}
