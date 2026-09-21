<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature;

use RoBYCoNTe\FilamentFlow\Support\CompletionPayload;
use RoBYCoNTe\FilamentFlow\Support\FormulaCompletionRegistry;
use RoBYCoNTe\FilamentFlow\Support\WorkflowFormulaScope;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class FormulaCompletionRegistryTest extends TestCase
{
    public function test_registers_and_resolves_a_scope(): void
    {
        $registry = new FormulaCompletionRegistry;
        $scope = new WorkflowFormulaScope;

        $registry->register('workflow', $scope);

        $this->assertSame($scope, $registry->resolve('workflow'));
    }

    public function test_service_provider_registers_workflow_scope(): void
    {
        $registry = app(FormulaCompletionRegistry::class);

        $this->assertTrue($registry->has('workflow'));
        $this->assertInstanceOf(WorkflowFormulaScope::class, $registry->resolve('workflow'));
    }

    public function test_throws_on_unknown_scope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("No FormulaCompletionProvider registered for scope 'unknown'.");

        (new FormulaCompletionRegistry)->resolve('unknown');
    }

    public function test_has_returns_false_for_unknown_scope(): void
    {
        $this->assertFalse((new FormulaCompletionRegistry)->has('nonexistent'));
    }

    public function test_lists_registered_scopes(): void
    {
        $registry = new FormulaCompletionRegistry;
        $registry->register('scope_a', new WorkflowFormulaScope);
        $registry->register('scope_b', new WorkflowFormulaScope);

        $this->assertSame(['scope_a', 'scope_b'], $registry->registeredScopes());
    }

    public function test_workflow_scope_returns_valid_payload(): void
    {
        $payload = (new WorkflowFormulaScope)->getCompletions(null);

        $this->assertInstanceOf(CompletionPayload::class, $payload);
        $this->assertNotEmpty($payload->variables);

        $names = array_column($payload->variables, 'name');
        $this->assertContains('now', $names);
        $this->assertContains('state', $names);
        $this->assertContains('user', $names);
        $this->assertContains('record', $names);
    }

    public function test_workflow_scope_returns_empty_states_without_context(): void
    {
        $payload = (new WorkflowFormulaScope)->getCompletions(null);

        $this->assertSame(['states' => []], $payload->stringValues);
    }

    public function test_completion_payload_serialises_to_array(): void
    {
        $payload = new CompletionPayload(
            variables: [['name' => 'now', 'kind' => 'variable', 'type' => 'DateTime', 'description' => 'test']],
            stringValues: ['states' => ['draft', 'submitted']],
        );

        $array = $payload->toArray();

        $this->assertArrayHasKey('variables', $array);
        $this->assertArrayHasKey('string_values', $array);
        $this->assertSame(['draft', 'submitted'], $array['string_values']['states']);
    }

    public function test_completion_payload_empty_returns_empty(): void
    {
        $payload = CompletionPayload::empty();

        $this->assertSame([], $payload->variables);
        $this->assertSame([], $payload->stringValues);
    }
}
