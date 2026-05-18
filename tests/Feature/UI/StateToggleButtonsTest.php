<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use Filament\Forms\Components\ToggleButtons;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAttributes as HasStateAttributesContract;
use RoBYCoNTe\FilamentFlow\Forms\Components\StateToggleButtons;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateToggleButtons UI Component Tests
 *
 * Tests instantiation and fluent API of the StateToggleButtons form component.
 */
class StateToggleButtonsTest extends TestCase
{
    /**
     * Test StateToggleButtons can be instantiated via static make().
     */
    public function test_can_be_created(): void
    {
        $buttons = StateToggleButtons::make('state');

        $this->assertInstanceOf(StateToggleButtons::class, $buttons);
    }

    /**
     * Test StateToggleButtons extends Filament's ToggleButtons component.
     */
    public function test_is_toggle_buttons_component(): void
    {
        $buttons = StateToggleButtons::make('state');

        $this->assertInstanceOf(ToggleButtons::class, $buttons);
    }

    /**
     * Test StateToggleButtons has state options via the HasStateOptions trait.
     */
    public function test_has_state_options(): void
    {
        $buttons = StateToggleButtons::make('state');

        $this->assertInstanceOf(HasStateAttributesContract::class, $buttons);
        $this->assertTrue(method_exists($buttons, 'respectTransitions'));
        $this->assertTrue(method_exists($buttons, 'ignoreTransitions'));
    }

    /**
     * Test fluent chaining works on StateToggleButtons.
     */
    public function test_fluent_chaining(): void
    {
        $buttons = StateToggleButtons::make('state');

        $result = $buttons->respectTransitions(false);
        $this->assertSame($buttons, $result);

        $result = $buttons->ignoreTransitions();
        $this->assertSame($buttons, $result);

        $result = $buttons->attribute('custom_state');
        $this->assertSame($buttons, $result);
    }
}
