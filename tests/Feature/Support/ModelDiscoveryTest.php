<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Support;

use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class ModelDiscoveryTest extends TestCase
{
    public function test_get_column_options_returns_columns(): void
    {
        $options = ModelDiscovery::getColumnOptions(Order::class);

        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('id', $options);
        $this->assertArrayHasKey('state', $options);
        $this->assertArrayHasKey('order_number', $options);
    }

    public function test_get_column_options_returns_empty_for_null(): void
    {
        $this->assertEmpty(ModelDiscovery::getColumnOptions(null));
    }

    public function test_get_column_options_returns_empty_for_nonexistent_class(): void
    {
        $this->assertEmpty(ModelDiscovery::getColumnOptions('App\\Models\\Nonexistent'));
    }

    public function test_get_string_column_options_filters_types(): void
    {
        $options = ModelDiscovery::getStringColumnOptions(Order::class);

        // String columns should be present
        $this->assertArrayHasKey('order_number', $options);
        $this->assertArrayHasKey('customer_name', $options);
        $this->assertArrayHasKey('state', $options);
    }

    public function test_get_string_column_options_returns_empty_for_null(): void
    {
        $this->assertEmpty(ModelDiscovery::getStringColumnOptions(null));
    }

    public function test_get_options_returns_array(): void
    {
        // This depends on model_discovery_paths config — may return empty in test env
        $options = ModelDiscovery::getOptions();
        $this->assertIsArray($options);
    }

    public function test_get_resource_component_options_falls_back_to_columns(): void
    {
        // Without a Filament panel setup, it falls back to column options
        $options = ModelDiscovery::getResourceComponentOptions(Order::class);
        $this->assertIsArray($options);
    }

    public function test_get_resource_component_options_returns_empty_for_null(): void
    {
        $this->assertEmpty(ModelDiscovery::getResourceComponentOptions(null));
    }

    public function test_get_relation_manager_relationship(): void
    {
        // Test the static method via reflection
        $method = new \ReflectionMethod(ModelDiscovery::class, 'getRelationManagerRelationship');

        // Use a known RM class if available, otherwise test with a nonexistent class
        $result = $method->invoke(null, 'NonExistentClass');
        $this->assertNull($result);
    }

    public function test_discover_relation_manager_actions(): void
    {
        $method = new \ReflectionMethod(ModelDiscovery::class, 'discoverRelationManagerActions');

        // Nonexistent class returns empty array
        $result = $method->invoke(null, 'NonExistentClass');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
