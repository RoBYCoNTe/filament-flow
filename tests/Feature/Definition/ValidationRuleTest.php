<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\ValidationRule;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * A validation rule: its rules and its message, and the convenience of one rule written as a
 * plain string.
 */
class ValidationRuleTest extends TestCase
{
    public function test_rules_and_message(): void
    {
        $rule = ValidationRule::make('score')->rules(['gte:60', 'lte:100'])->message('Out of range.')->sort(2);

        $this->assertSame('score', $rule->name());
        $this->assertSame(['gte:60', 'lte:100'], $rule->toArray()['rules']);
        $this->assertSame('Out of range.', $rule->toArray()['custom_message']);
        $this->assertSame(2, $rule->toArray()['sort_order']);
    }

    public function test_accepts_a_single_string_rule(): void
    {
        $this->assertSame(['required'], ValidationRule::make('name')->rules('required')->toArray()['rules']);
    }
}
