<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use RoBYCoNTe\FilamentFlow\Actions\StateActionGroup;

/**
 * Trait to automatically add workflow transition actions to page headers.
 * Use in EditRecord or ViewRecord pages.
 */
trait HasWorkflowActions
{
    protected function getHeaderActions(): array
    {
        $actions = parent::getHeaderActions();
        $record = $this->getRecord();

        if (! $record || ! config('filament-flow.enabled', true)) {
            return $actions;
        }

        $workflowActions = StateActionGroup::forDatabaseRecord(
            $record,
            $this->getWorkflowStateColumn()
        );

        return array_merge($actions, $workflowActions);
    }

    protected function getWorkflowStateColumn(): string
    {
        return 'state';
    }
}
