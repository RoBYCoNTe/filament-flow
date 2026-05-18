<?php

namespace RoBYCoNTe\FilamentFlow\Tables\Filters;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RoBYCoNTe\FilamentFlow\Services\StateService;

class StateSelectFilter extends SelectFilter
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->options(function (Table $table) {
            $service = app(StateService::class);

            return $service->getAllStatesForModel($table->getModel(), $this->getAttribute());
        });
    }
}
