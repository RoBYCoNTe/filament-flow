<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowForm;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource;

class CreateOrder extends CreateRecord
{
    use HasWorkflowForm;

    protected static string $resource = OrderResource::class;
}
