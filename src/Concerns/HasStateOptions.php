<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use Spatie\ModelStates\State;

trait HasStateOptions
{
    use ParsesStateCast;

    protected bool $respectTransitions = true;

    /**
     * Model of the owning component: a form field knows its model, a table column
     * its table. Null when neither is available (no options to show).
     *
     * @return class-string<Model>|null
     */
    protected function resolveStateModelClass(): ?string
    {
        if (method_exists($this, 'getModel')) {
            $model = $this->getModel();

            if ($model instanceof Model) {
                return $model::class;
            }

            if (is_string($model) && $model !== '') {
                return $model;
            }
        }

        if (method_exists($this, 'getTable')) {
            $model = $this->getTable()->getModel();

            if ($model instanceof Model) {
                return $model::class;
            }

            if (is_string($model) && $model !== '') {
                return $model;
            }
        }

        return null;
    }

    protected function setupOptions(): void
    {
        $this->options(function ($record) {
            $stateService = app(StateService::class);

            if (! $record instanceof Model) {
                $modelClass = $this->resolveStateModelClass();

                if ($modelClass === null) {
                    return [];
                }

                return $stateService->getAllStatesForModel($modelClass, $this->getAttribute());
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
}
