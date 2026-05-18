<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use Filament\Forms\Components\TextInput;
use RoBYCoNTe\FilamentFlow\Support\ComponentIdentifier;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class ComponentIdentifierTest extends TestCase
{
    public function test_resolve_field_returns_name(): void
    {
        $field = TextInput::make('customer_name');
        $this->assertEquals('customer_name', ComponentIdentifier::resolve($field));
    }

    public function test_resolve_field_with_different_name(): void
    {
        $field = TextInput::make('email_address');
        $this->assertEquals('email_address', ComponentIdentifier::resolve($field));
    }
}
