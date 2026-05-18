<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use Spatie\ModelStates\State;

trait HasStateOptions
{
    protected bool $respectTransitions = true;

    protected function setupOptions(): void
    {
        $this->options(function ($record) {
            $stateService = app(StateService::class);

            if (! $record instanceof Model) {
                $model = $this->getModel();

                return $stateService->getAllStatesForModel($model, $this->getAttribute());
            }

            return $stateService->getAllStatesForModel($record::class, $this->getAttribute());
        });

        $this->disableOptionWhen(function (string $value, $record) {
            if (! $this->respectTransitions) {
                return false;
            }

            if (! $record instanceof Model) {
                return false;
            }

            $currentState = $record->{$this->getAttribute()};
            if (! $currentState) {
                return false;
            }

            // Get current state key (handle both State objects and strings)
            $currentStateKey = is_string($currentState)
                ? $currentState
                : $currentState::getMorphClass();

            // Don't disable the current state
            if ($value === $currentStateKey) {
                return false;
            }

            // Use the model's canTransitionTo method which handles both PHP and database states
            if (method_exists($record, 'canTransitionTo')) {
                return ! $record->canTransitionTo($value, $this->getAttribute());
            }

            // Fallback: if current state is a State object, use Spatie's canTransitionTo
            if ($currentState instanceof State) {
                try {
                    return ! $currentState->canTransitionTo($value);
                } catch (Exception $e) {
                    report($e);

                    return true; // Disable when can't check
                }
            }

            return true; // Disable by default if can't determine
        });

        $this->default(function ($model) {
            if (method_exists($model, 'getDefaultStateFor')) {
                return $model::getDefaultStateFor($this->getAttribute());
            }

            // For database-only states, get initial state from workflow
            if (config('filament-flow.enabled', true)) {
                $stateService = app(StateService::class);

                return $stateService->getInitialState(
                    is_string($model) ? $model : $model::class,
                    $this->getAttribute()
                );
            }

            return null;
        });
    }

    /**
     * Set whether the field should respect state transitions.
     */
    public function respectTransitions(bool $respect = true): static
    {
        $this->respectTransitions = $respect;

        return $this;
    }

    /**
     * Disable transition restrictions for the field.
     */
    public function ignoreTransitions(): static
    {
        return $this->respectTransitions(false);
    }

    /**
     * Extract the base state class from cast definition
     * Handles both Spatie's default cast and FlexibleStateCast
     */
    protected function extractStateClass(?string $cast): ?string
    {
        if (! $cast) {
            return null;
        }

        // If using FlexibleStateCast, extract the state class from the parameter
        // Format: "RoBYCoNTe\FilamentFlow\Casts\FlexibleStateCast:App\States\Order\OrderState"
        if (str_contains($cast, 'FlexibleStateCast:')) {
            $parts = explode(':', $cast);

            return $parts[1] ?? null;
        }

        return $cast;
    }
}
