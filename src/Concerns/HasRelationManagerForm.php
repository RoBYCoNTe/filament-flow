<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Filament\Schemas\Schema;

/**
 * Single-column form of a relation manager, built from the static schema of the
 * concrete manager (`getFormSchema()`).
 */
trait HasRelationManagerForm
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(static::getFormSchema())
            ->columns(1);
    }
}
