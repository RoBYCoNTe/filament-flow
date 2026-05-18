<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Filament\Schemas\Schema;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Support\FieldPermissionApplier;

/**
 * Trait for adding creation workflow support to Filament Resources.
 * Uses the initial state's access rules for permission checks and
 * the initial state's field permissions for form configuration.
 *
 * Use on Resource classes (static context).
 * For CreateRecord/EditRecord pages, use HasWorkflowForm instead.
 */
trait HasWorkflowCreation
{
    /**
     * Check if current user can create records.
     * Delegates to the initial state's access rules (access_type = 'create').
     */
    public static function canCreate(): bool
    {
        if (! static::$model) {
            return parent::canCreate();
        }

        if (! config('filament-flow.enabled', true)) {
            return parent::canCreate();
        }

        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return app(WorkflowStateAccessService::class)
            ->canCreate(static::$model, $user);
    }

    /**
     * Override form method to apply initial state's field permissions
     */
    public static function form(Schema $schema): Schema
    {
        if (! config('filament-flow.enabled', true)) {
            return parent::form($schema);
        }

        // Get the default form from the resource
        $schema = parent::form($schema);

        $user = auth()->user();
        $service = app(WorkflowFieldPermissionsService::class);
        $permissions = $service->getCreationFieldPermissions(static::$model, $user);

        if (empty($permissions)) {
            return $schema;
        }

        $components = $schema->getComponents();
        $modifiedComponents = FieldPermissionApplier::apply($components, $permissions);

        return $schema->components($modifiedComponents);
    }
}
