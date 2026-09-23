<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource;

/**
 * The list of the workflows, with the actions that act on one.
 */
class ListWorkflows extends ListRecords
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
