<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use RoBYCoNTe\FilamentFlow\Support\CanonicalJson;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class CanonicalJsonTest extends TestCase
{
    public function test_the_key_order_does_not_change_the_encoding(): void
    {
        $this->assertSame(
            CanonicalJson::encode(['b' => 1, 'a' => 2]),
            CanonicalJson::encode(['a' => 2, 'b' => 1]),
        );
    }

    public function test_nested_associative_arrays_are_sorted_recursively(): void
    {
        $this->assertSame(
            CanonicalJson::encode(['outer' => ['y' => 1, 'x' => ['b' => 1, 'a' => 2]]]),
            CanonicalJson::encode(['outer' => ['x' => ['a' => 2, 'b' => 1], 'y' => 1]]),
        );
    }

    public function test_the_order_of_a_list_is_significant(): void
    {
        $this->assertNotSame(
            CanonicalJson::encode([['a' => 1], ['a' => 2]]),
            CanonicalJson::encode([['a' => 2], ['a' => 1]]),
        );
    }

    public function test_scalars_are_encoded_as_json(): void
    {
        $this->assertSame('null', CanonicalJson::encode(null));
        $this->assertSame('true', CanonicalJson::encode(true));
        $this->assertSame('"text"', CanonicalJson::encode('text'));
        $this->assertSame('12', CanonicalJson::encode(12));
    }

    public function test_canonicalize_keeps_scalars_and_reorders_arrays(): void
    {
        $this->assertSame('scalar', CanonicalJson::canonicalize('scalar'));
        $this->assertSame(['a' => 1, 'b' => 2], CanonicalJson::canonicalize(['b' => 2, 'a' => 1]));
        $this->assertSame([2, 1], CanonicalJson::canonicalize([2, 1]));
    }

    public function test_an_optional_value_treats_null_and_empty_array_as_absent(): void
    {
        $this->assertSame('', CanonicalJson::encodeOptional(null));
        $this->assertSame('', CanonicalJson::encodeOptional([]));
        // Scalars keep the previous planner behaviour: compared as plain strings.
        $this->assertSame('1', CanonicalJson::encodeOptional(1));
        $this->assertSame('x', CanonicalJson::encodeOptional('x'));
        $this->assertSame(CanonicalJson::encode(['a' => 1]), CanonicalJson::encodeOptional(['a' => 1]));
    }
}
