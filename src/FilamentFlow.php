<?php

namespace RoBYCoNTe\FilamentFlow;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Services\NotificationService;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;

/**
 * The entry point the host uses: the workflow of a model — with the tenant it belongs to — and
 * the services of the package, resolved by name.
 */
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
    public static function getWorkflow(string $modelClass, string $stateColumn = 'state', ?int $tenantId = null): ?Workflow
    {
        return Workflow::findForModel($modelClass, $stateColumn, $tenantId);
    }

    /**
     * Get all available states for a model (code-first + database).
     */
    public static function getStates(Model $record): array
    {
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;

        return app(StateService::class)->getAllStatesForModel($record::class, 'state', $tenantId);
    }

    /**
     * Check if a user can perform an action on a record in its current state.
     */
    public static function canAccess(Model $record, string $accessType, ?Model $user = null): bool
    {
        return app(WorkflowStateAccessService::class)->checkAccess($record, $user, $accessType);
    }

    /**
     * Get the notification service instance.
     */
    public static function notifications(): NotificationService
    {
        return app(NotificationService::class);
    }
}
