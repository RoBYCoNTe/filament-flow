<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowTable;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource;

class ListOrders extends ListRecords
{
    use HasWorkflowTable;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
