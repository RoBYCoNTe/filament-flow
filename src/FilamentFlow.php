<?php

namespace RoBYCoNTe\FilamentFlow;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;

class FilamentFlow
{
    /**
     * Check if the plugin is enabled.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('filament-flow.enabled', true);
    }

    /**
     * Get the workflow configured for a given model class.
     */
    public static function getWorkflow(string $modelClass, string $stateColumn = 'state'): ?Workflow
    {
        return Workflow::findForModel($modelClass, $stateColumn);
    }

    /**
     * Get all available states for a model (code-first + database).
     */
    public static function getStates(Model $record): array
    {
        return app(StateService::class)->getAllStates($record);
    }

    /**
     * Check if a user can perform an action on a record in its current state.
     */
    public static function canAccess(Model $record, string $accessType, ?object $user = null): bool
    {
        return app(WorkflowStateAccessService::class)->checkAccess($record, $accessType, $user);
    }

    /**
     * Get the notification service instance.
     */
    public static function notifications(): NotificationService
    {
        return app(NotificationService::class);
    }
}
