<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Exceptions\AuthenticationRequiredException;
use RoBYCoNTe\FilamentFlow\Services\WorkflowCreationService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Support\FieldPermissionApplier;
use Throwable;

/**
 * Trait to automatically apply workflow-based form permissions
 * Use in CreateRecord or EditRecord pages
 */
trait HasWorkflowForm
{
    /**
     * Override form method to apply workflow permissions
     *
     * @noinspection PhpMultipleClassDeclarationsInspection
     *
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        // Check if workflow is enabled
        if (! config('filament-flow.enabled', true)) {
            return parent::form($schema);
        }

        $record = $this->record ?? null;

        // If creating (no record), use initial state's field permissions
        if (! $record || ! $record->exists) {
            return $this->applyCreationWorkflow($schema);
        }

        // If editing, use field permissions workflow
        return $this->applyEditWorkflow($schema, $record);
    }

    /**
     * Apply workflow for record creation using the initial state's field permissions
     */
    protected function applyCreationWorkflow(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        $modelClass = static::getResource()::getModel();
        $user = auth()->user();

        $service = app(WorkflowFieldPermissionsService::class);
        $permissions = $service->getCreationFieldPermissions($modelClass, $user);

        if (empty($permissions)) {
            return $schema;
        }

        $components = $schema->getComponents();
        $modifiedComponents = FieldPermissionApplier::apply($components, $permissions);

        return $schema->components($modifiedComponents);
    }

    /**
     * Apply workflow for record editing
     */
    protected function applyEditWorkflow(Schema $schema, Model $record): Schema
    {
        // Get the default form (from Resource or parent)
        $schema = parent::form($schema);

        // Get field permissions from workflow configuration (role-aware)
        $user = auth()->user();
        $service = app(WorkflowFieldPermissionsService::class);
        $permissions = $service->getFieldPermissions($record, $user);

        if (empty($permissions)) {
            return $schema;
        }

        // Modify form components
        $components = $schema->getComponents();
        $modifiedComponents = FieldPermissionApplier::apply($components, $permissions);

        return $schema->components($modifiedComponents);
    }

    /**
     * Override record creation to use WorkflowCreationService
     *
     * @throws Exception|Throwable
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Check if workflow is enabled
        if (! config('filament-flow.enabled', true)) {
            return parent::handleRecordCreation($data);
        }

        $user = auth()->user();
        if (! $user) {
            throw new AuthenticationRequiredException;
        }

        $modelClass = static::getResource()::getModel();

        return app(WorkflowCreationService::class)
            ->createRecord($modelClass, $data, $user);
    }
}
