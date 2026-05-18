<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource;

class CreateWorkflowState extends CreateRecord
{
    protected static string $resource = WorkflowStateResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(WorkflowStateResource::getGeneralFormSchema())
            ->columns(3);
    }
}
