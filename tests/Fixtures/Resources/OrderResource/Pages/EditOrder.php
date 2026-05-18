<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages;

use Filament\Resources\Pages\EditRecord;
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowActions;
use RoBYCoNTe\FilamentFlow\Concerns\HasWorkflowForm;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource;

class EditOrder extends EditRecord
{
    use HasWorkflowActions;
    use HasWorkflowForm;

    protected static string $resource = OrderResource::class;
}
