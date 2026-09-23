<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use RoBYCoNTe\FilamentFlow\Support\RuleOptions;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * What may be offered where: the access rules include the general ones and the relationships,
 * while a field override leaves the general ones out — an override is about a relationship.
 */
class RuleOptionsTest extends TestCase
{
    public function test_for_access_rules_includes_general(): void
    {
        $options = RuleOptions::forAccessRules();

        $this->assertIsArray($options);
        // Should have General group
        $generalKey = __('General');
        $this->assertArrayHasKey($generalKey, $options);
        $this->assertArrayHasKey('*', $options[$generalKey]);
        $this->assertArrayHasKey('@authenticated', $options[$generalKey]);
    }

    public function test_for_access_rules_includes_relationship(): void
    {
        $options = RuleOptions::forAccessRules();

        $relationKey = __('Record relationship');
        $this->assertArrayHasKey($relationKey, $options);
        $this->assertArrayHasKey('@owner', $options[$relationKey]);
        $this->assertArrayHasKey('@assigned', $options[$relationKey]);
        $this->assertArrayHasKey('@assigned:primary', $options[$relationKey]);
    }

    public function test_for_field_overrides_excludes_general(): void
    {
        $options = RuleOptions::forFieldOverrides();

        $generalKey = __('General');
        $this->assertArrayNotHasKey($generalKey, $options);
    }

    public function test_for_field_overrides_includes_relationship(): void
    {
        $options = RuleOptions::forFieldOverrides();

        $relationKey = __('Record relationship');
        $this->assertArrayHasKey($relationKey, $options);
    }
}
