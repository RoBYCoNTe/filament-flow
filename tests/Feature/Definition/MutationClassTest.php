<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class MutationClassTest extends TestCase
{
    public function test_the_cases_are_backed_by_their_string_value(): void
    {
        $this->assertSame('safe', MutationClass::Safe->value);
        $this->assertSame('additive', MutationClass::Additive->value);
        $this->assertSame('breaking', MutationClass::Breaking->value);
    }

    public function test_every_case_has_a_translatable_label(): void
    {
        foreach (MutationClass::cases() as $case) {
            $this->assertNotSame('', $case->label(), "MutationClass::{$case->name} must have a label.");
        }
    }

    public function test_the_labels_are_translated(): void
    {
        $this->app->setLocale('it');

        $this->assertSame('Sicura', MutationClass::Safe->label());
        $this->assertSame('Aggiuntiva', MutationClass::Additive->label());
        $this->assertSame('Distruttiva', MutationClass::Breaking->label());
    }
}
