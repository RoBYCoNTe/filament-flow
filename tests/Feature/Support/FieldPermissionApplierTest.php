<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use Filament\Forms\Components\TextInput;
use RoBYCoNTe\FilamentFlow\Support\FieldPermissionApplier;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class FieldPermissionApplierTest extends TestCase
{
    /**
     * Read internal property from a Filament component via reflection.
     */
    private function getComponentProperty(object $component, string $property): mixed
    {
        $ref = new \ReflectionProperty($component, $property);

        return $ref->getValue($component);
    }

    public function test_locked_field_becomes_hidden(): void
    {
        $components = [TextInput::make('name')];
        $permissions = ['name' => ['locked' => true]];

        $result = FieldPermissionApplier::apply($components, $permissions);

        $this->assertTrue($this->getComponentProperty($result[0], 'isHidden'));
    }

    public function test_invisible_field_becomes_hidden(): void
    {
        $components = [TextInput::make('name')];
        $permissions = ['name' => ['visible' => false]];

        $result = FieldPermissionApplier::apply($components, $permissions);

        $this->assertTrue($this->getComponentProperty($result[0], 'isHidden'));
    }

    public function test_readonly_field_becomes_disabled(): void
    {
        $components = [TextInput::make('name')];
        $permissions = ['name' => ['readonly' => true]];

        $result = FieldPermissionApplier::apply($components, $permissions);

        $this->assertTrue($this->getComponentProperty($result[0], 'isDisabled'));
    }

    public function test_required_field_becomes_required(): void
    {
        $components = [TextInput::make('name')];
        $permissions = ['name' => ['required' => true]];

        $result = FieldPermissionApplier::apply($components, $permissions);

        $this->assertTrue($this->getComponentProperty($result[0], 'isRequired'));
    }

    public function test_no_permissions_leaves_field_unchanged(): void
    {
        $components = [TextInput::make('name')];

        $result = FieldPermissionApplier::apply($components, []);

        // Default values should remain
        $this->assertNotTrue($this->getComponentProperty($result[0], 'isHidden'));
        $this->assertNotTrue($this->getComponentProperty($result[0], 'isDisabled'));
        $this->assertNotTrue($this->getComponentProperty($result[0], 'isRequired'));
    }

    public function test_locked_takes_precedence_over_other_permissions(): void
    {
        $components = [TextInput::make('name')];
        $permissions = ['name' => ['locked' => true, 'readonly' => true, 'required' => true]];

        $result = FieldPermissionApplier::apply($components, $permissions);

        // Locked means hidden — it returns early before applying readonly/required
        $this->assertTrue($this->getComponentProperty($result[0], 'isHidden'));
    }

    public function test_empty_components_returns_empty_array(): void
    {
        $result = FieldPermissionApplier::apply([], ['name' => ['locked' => true]]);

        $this->assertEmpty($result);
    }

    public function test_multiple_fields_with_different_permissions(): void
    {
        $components = [
            TextInput::make('name'),
            TextInput::make('email'),
            TextInput::make('phone'),
        ];
        $permissions = [
            'name' => ['readonly' => true],
            'email' => ['locked' => true],
            'phone' => ['required' => true],
        ];

        $result = FieldPermissionApplier::apply($components, $permissions);

        $this->assertTrue($this->getComponentProperty($result[0], 'isDisabled'));
        $this->assertTrue($this->getComponentProperty($result[1], 'isHidden'));
        $this->assertTrue($this->getComponentProperty($result[2], 'isRequired'));
    }

    public function test_unmatched_fields_are_not_affected(): void
    {
        $components = [
            TextInput::make('name'),
            TextInput::make('email'),
        ];
        $permissions = ['email' => ['locked' => true]];

        $result = FieldPermissionApplier::apply($components, $permissions);

        // name should be untouched
        $this->assertNotTrue($this->getComponentProperty($result[0], 'isHidden'));
        // email should be hidden
        $this->assertTrue($this->getComponentProperty($result[1], 'isHidden'));
    }
}
