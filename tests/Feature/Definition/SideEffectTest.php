<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\SideEffectType;
use RoBYCoNTe\FilamentFlow\Definition\SideEffect;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class SideEffectTest extends TestCase
{
    public function test_factories_produce_expected_type(): void
    {
        $this->assertSame(SideEffectType::SetField->value, SideEffect::setField('a', 'b')->type());
        $this->assertSame(SideEffectType::SetTimestamp->value, SideEffect::setTimestamp('a')->type());
        $this->assertSame(SideEffectType::ClearField->value, SideEffect::clearField('a')->type());
        $this->assertSame(SideEffectType::Increment->value, SideEffect::increment('a', 2)->type());
        $this->assertSame(SideEffectType::CustomClass->value, SideEffect::customClass('X')->type());
        $this->assertSame(SideEffectType::CreateChildApplication->value, SideEffect::createChildApplication(['child_scheme_slug' => 'x'])->type());
    }

    public function test_to_array_and_active_flag(): void
    {
        $effect = SideEffect::setField('reviewer_id', 'field:user.id')->sort(3)->active(false);

        $data = $effect->toArray();

        $this->assertSame('set_field', $data['effect_type']);
        $this->assertSame('reviewer_id', $data['field_name']);
        $this->assertSame('field:user.id', $data['value_expression']);
        $this->assertSame(3, $data['sort_order']);
        $this->assertFalse($data['is_active']);
    }
}
