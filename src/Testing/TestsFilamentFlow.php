<?php

namespace RoBYCoNTe\FilamentFlow\Testing;

use Closure;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use PHPUnit\Framework\Assert;

/**
 * Testing macros for FilamentFlow workflow transition actions.
 *
 * Mixed into Livewire's Testable class, providing assertions for
 * verifying workflow transition buttons in Filament pages.
 *
 * Transition actions follow the naming convention: transition-{slug}
 * where {slug} is Str::slug($transitionName).
 *
 * Works with both:
 * - Header actions (via HasWorkflowActions) -- native Filament assertions
 * - Form footer actions (in ActionGroup) -- HTML inspection fallback
 *
 * @mixin Testable
 */
class TestsFilamentFlow
{
    /**
     * Assert that a workflow transition action button is visible on the page.
     *
     * Usage:
     *   Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
     *       ->assertTransitionVisible('start_processing');
     */
    public function assertTransitionVisible(): Closure
    {
        return function (string $transitionName): static {
            $actionName = Str::slug('transition-'.$transitionName);

            try {
                $this->assertActionVisible($actionName);

                return $this;
            } catch (\Throwable) {
                // Fallback to HTML inspection for ActionGroup-nested actions
            }

            Assert::assertStringContainsString(
                $actionName,
                $this->html(),
                "Failed asserting that transition [{$transitionName}] (action: [{$actionName}]) is visible."
            );

            return $this;
        };
    }

    /**
     * Assert that a workflow transition action button is NOT visible on the page.
     *
     * Usage:
     *   Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
     *       ->assertTransitionHidden('ship_order');
     */
    public function assertTransitionHidden(): Closure
    {
        return function (string $transitionName): static {
            $actionName = Str::slug('transition-'.$transitionName);

            try {
                $this->assertActionHidden($actionName);

                return $this;
            } catch (\Throwable) {
                // Fallback to HTML inspection
            }

            Assert::assertStringNotContainsString(
                $actionName,
                $this->html(),
                "Failed asserting that transition [{$transitionName}] (action: [{$actionName}]) is hidden."
            );

            return $this;
        };
    }

    /**
     * Assert that a workflow transition action exists (regardless of visibility).
     *
     * Usage:
     *   Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
     *       ->assertTransitionExists('start_processing');
     */
    public function assertTransitionExists(): Closure
    {
        return function (string $transitionName): static {
            $actionName = Str::slug('transition-'.$transitionName);

            try {
                $this->assertActionExists($actionName);

                return $this;
            } catch (\Throwable) {
                // Fallback to HTML inspection
            }

            Assert::assertStringContainsString(
                $actionName,
                $this->html(),
                "Failed asserting that transition [{$transitionName}] (action: [{$actionName}]) exists."
            );

            return $this;
        };
    }

    /**
     * Assert that a workflow transition action does NOT exist on the page.
     *
     * Usage:
     *   Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
     *       ->assertTransitionMissing('ship_order');
     */
    public function assertTransitionMissing(): Closure
    {
        return function (string $transitionName): static {
            $actionName = Str::slug('transition-'.$transitionName);

            try {
                $this->assertActionDoesNotExist($actionName);

                return $this;
            } catch (\Throwable) {
                // Fallback to HTML inspection
            }

            Assert::assertStringNotContainsString(
                $actionName,
                $this->html(),
                "Failed asserting that transition [{$transitionName}] (action: [{$actionName}]) is missing."
            );

            return $this;
        };
    }

    /**
     * Execute a workflow transition action.
     *
     * Usage:
     *   ->callTransition('start_processing')
     *   ->callTransition('start_processing', ['processing_notes' => 'Done'])
     */
    public function callTransition(): Closure
    {
        return function (string $transitionName, array $data = []): static {
            $this->callAction(Str::slug('transition-'.$transitionName), $data);

            return $this;
        };
    }
}
