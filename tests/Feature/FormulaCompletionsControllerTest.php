<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature;

use RoBYCoNTe\FilamentFlow\Support\FormulaCompletionRegistry;
use RoBYCoNTe\FilamentFlow\Support\WorkflowFormulaScope;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The endpoint that feeds the formula editor: the completions of a registered scope, no states
 * without a context, and nothing at all without authentication.
 */
class FormulaCompletionsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(FormulaCompletionRegistry::class)->register('workflow', new WorkflowFormulaScope);
    }

    public function test_returns_completions_for_registered_scope(): void
    {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'x']);

        $response = $this->actingAs($user)
            ->getJson(route('filament-flow.formula-completions', ['scope' => 'workflow']));

        $response->assertOk()
            ->assertJsonStructure(['variables', 'string_values'])
            ->assertJsonPath('variables.0.name', 'now');
    }

    public function test_returns_404_for_unknown_scope(): void
    {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'x']);

        $response = $this->actingAs($user)
            ->getJson(route('filament-flow.formula-completions', ['scope' => 'nonexistent']));

        $response->assertNotFound();
    }

    public function test_returns_empty_states_without_context(): void
    {
        $user = User::create(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'x']);

        $response = $this->actingAs($user)
            ->getJson(route('filament-flow.formula-completions', ['scope' => 'workflow']));

        $response->assertOk();
        $this->assertSame([], $response->json('string_values.states'));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson(route('filament-flow.formula-completions', ['scope' => 'workflow']));

        $response->assertUnauthorized();
    }
}
