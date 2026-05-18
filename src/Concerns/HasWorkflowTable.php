<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Filament\Tables\Table;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;

/**
 * Trait to automatically apply workflow-based table column visibility.
 * Use in ListRecords pages.
 */
trait HasWorkflowTable
{
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        if (! config('filament-flow.enabled', true)) {
            return $table;
        }

        $modelClass = static::getResource()::getModel();
        $user = auth()->user();

        $service = app(WorkflowFieldPermissionsService::class);
        $permissions = $service->getTableColumnPermissions($modelClass, $user);

        if (empty($permissions)) {
            return $table;
        }

        $columns = $table->getColumns();
        foreach ($columns as $column) {
            $name = $column->getName();
            if (isset($permissions[$name]) && ! $permissions[$name]['visible']) {
                $column->hidden();
            }
        }

        return $table;
    }
}
