<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use Filament\Forms\Components\Radio;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAttributes as HasStateAttributesContract;
use RoBYCoNTe\FilamentFlow\Forms\Components\StateRadio;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateRadio UI Component Tests
 *
 * Tests instantiation and fluent API of the StateRadio form component.
 */
class StateRadioTest extends TestCase
{
    /**
     * Test StateRadio can be instantiated via static make().
     */
    public function test_can_be_created(): void
    {
        $radio = StateRadio::make('state');

        $this->assertInstanceOf(StateRadio::class, $radio);
    }

    /**
     * Test StateRadio extends Filament's Radio component.
     */
    public function test_is_radio_component(): void
    {
        $radio = StateRadio::make('state');

        $this->assertInstanceOf(Radio::class, $radio);
    }

    /**
     * Test StateRadio has state options via the HasStateOptions trait.
     */
    public function test_has_state_options(): void
    {
        $radio = StateRadio::make('state');

        $this->assertInstanceOf(HasStateAttributesContract::class, $radio);
        $this->assertTrue(method_exists($radio, 'respectTransitions'));
        $this->assertTrue(method_exists($radio, 'ignoreTransitions'));
    }

    /**
     * Test fluent chaining works on StateRadio.
     */
    public function test_fluent_chaining(): void
    {
        $radio = StateRadio::make('state');

        $result = $radio->respectTransitions(false);
        $this->assertSame($radio, $result);

        $result = $radio->ignoreTransitions();
        $this->assertSame($radio, $result);

        $result = $radio->attribute('custom_state');
        $this->assertSame($radio, $result);
    }
}
