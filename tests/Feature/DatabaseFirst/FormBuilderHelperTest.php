<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use RoBYCoNTe\FilamentFlow\Services\FormBuilderHelper;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class FormBuilderHelperTest extends TestCase
{
    private FormBuilderHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new FormBuilderHelper;
    }

    public function test_build_component_by_type_text(): void
    {
        $component = $this->helper->buildComponent('name', ['component_type' => 'text']);
        $this->assertInstanceOf(TextInput::class, $component);
    }

    public function test_build_component_by_type_email(): void
    {
        $component = $this->helper->buildComponent('email', ['component_type' => 'email']);
        $this->assertInstanceOf(TextInput::class, $component);
    }

    public function test_build_component_by_type_textarea(): void
    {
        $component = $this->helper->buildComponent('notes', ['component_type' => 'textarea']);
        $this->assertInstanceOf(Textarea::class, $component);
    }

    public function test_build_component_by_type_select(): void
    {
        $component = $this->helper->buildComponent('category', ['component_type' => 'select']);
        $this->assertInstanceOf(Select::class, $component);
    }

    public function test_build_component_by_type_checkbox(): void
    {
        $component = $this->helper->buildComponent('active', ['component_type' => 'checkbox']);
        $this->assertInstanceOf(Toggle::class, $component);
    }

    public function test_build_component_by_type_date(): void
    {
        $component = $this->helper->buildComponent('start_date', ['component_type' => 'date']);
        $this->assertInstanceOf(DatePicker::class, $component);
    }

    public function test_build_component_by_type_datetime(): void
    {
        $component = $this->helper->buildComponent('created_at', ['component_type' => 'datetime']);
        $this->assertInstanceOf(DateTimePicker::class, $component);
    }

    public function test_build_component_by_type_number(): void
    {
        $component = $this->helper->buildComponent('qty', ['component_type' => 'number']);
        $this->assertInstanceOf(TextInput::class, $component);
    }

    public function test_build_component_unknown_type_returns_null(): void
    {
        $component = $this->helper->buildComponent('x', ['component_type' => 'unknown_type']);
        $this->assertNull($component);
    }

    public function test_infer_component_from_email_field(): void
    {
        $component = $this->helper->buildComponent('user_email', []);
        $this->assertInstanceOf(TextInput::class, $component);
    }

    public function test_infer_component_from_boolean_field(): void
    {
        $component = $this->helper->buildComponent('is_active', []);
        $this->assertInstanceOf(Toggle::class, $component);
    }

    public function test_infer_component_from_id_field(): void
    {
        $component = $this->helper->buildComponent('category_id', []);
        $this->assertInstanceOf(Select::class, $component);
    }

    public function test_infer_component_from_notes_field(): void
    {
        $component = $this->helper->buildComponent('internal_notes', []);
        $this->assertInstanceOf(Textarea::class, $component);
    }

    public function test_infer_component_from_amount_field(): void
    {
        $component = $this->helper->buildComponent('total_amount', []);
        $this->assertInstanceOf(TextInput::class, $component);
    }

    public function test_infer_component_from_at_field(): void
    {
        $component = $this->helper->buildComponent('delivered_at', []);
        $this->assertInstanceOf(DateTimePicker::class, $component);
    }

    public function test_infer_default_text_input(): void
    {
        $component = $this->helper->buildComponent('some_field', []);
        $this->assertInstanceOf(TextInput::class, $component);
    }

    public function test_build_components_skips_hidden(): void
    {
        $components = $this->helper->buildComponents([
            'visible_field' => ['component_type' => 'text', 'visible' => true],
            'hidden_field' => ['component_type' => 'text', 'visible' => false],
        ]);

        $this->assertCount(1, $components);
    }

    public function test_build_validation_rules(): void
    {
        $rules = $this->helper->buildValidationRules([
            'name' => ['required' => true, 'validation' => ['string', 'max:255']],
            'notes' => ['required' => false],
            'hidden' => ['visible' => false, 'required' => true],
        ]);

        $this->assertArrayHasKey('name', $rules);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
        $this->assertArrayNotHasKey('notes', $rules);
        $this->assertArrayNotHasKey('hidden', $rules);
    }

    public function test_extract_defaults(): void
    {
        $defaults = $this->helper->extractDefaults([
            'name' => ['default' => 'New Order'],
            'status' => ['default' => 'draft'],
            'notes' => [],
        ]);

        $this->assertEquals('New Order', $defaults['name']);
        $this->assertEquals('draft', $defaults['status']);
        $this->assertArrayNotHasKey('notes', $defaults);
    }

    public function test_apply_label_config(): void
    {
        $component = $this->helper->buildComponent('name', [
            'component_type' => 'text',
            'label' => 'Custom Label',
        ]);

        $this->assertInstanceOf(TextInput::class, $component);
    }

    public function test_apply_required_config(): void
    {
        $component = $this->helper->buildComponent('name', [
            'component_type' => 'text',
            'required' => true,
        ]);

        $this->assertInstanceOf(TextInput::class, $component);
    }
}
