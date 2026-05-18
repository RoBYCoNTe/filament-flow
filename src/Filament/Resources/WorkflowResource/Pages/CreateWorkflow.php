<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource;

class CreateWorkflow extends CreateRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
