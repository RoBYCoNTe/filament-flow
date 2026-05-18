<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;

trait HasTransitionForm
{
    protected function setupTransitionForm(): void
    {
        $this->schema(function () {
            // Transition class takes priority over database configuration
            try {
                if (method_exists($this, 'hasTransitionClass') && $this->hasTransitionClass()) {
                    $transitionClass = $this->getTransitionClass();
                    $modelClass = $this->getModel();

                    if ($transitionClass && class_exists($transitionClass) && $modelClass && class_exists($modelClass)) {
                        $transitionInstance = new $transitionClass(new $modelClass);

                        if (method_exists($transitionInstance, 'mainFormValidationRules')) {
                            return null;
                        }

                        if (method_exists($transitionInstance, 'form')) {
                            $formSchema = $transitionInstance->form();
                            if (! empty($formSchema)) {
                                if (method_exists($transitionInstance, 'requiresConfirmation') && $transitionInstance->requiresConfirmation()) {
                                    $this->requiresConfirmation();
                                }

                                return $formSchema;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                report($e);
            }

            $schema = $this->getSchemaFromDatabase();

            if (! empty($schema)) {
                return $schema;
            }

            // No fields but has validation rules: validate main form only, skip modal
            return $this->hasValidationRulesWithoutFields() ? null : $schema;
        });
    }

    private function hasValidationRulesWithoutFields(): bool
    {
        $modelClass = $this->getModel();
        if (! $modelClass) {
            return false;
        }

        try {
            $toState = $this->getToStateClass();
            $transitionConfig = app(TransitionFormService::class)->getTransitionConfig(
                $modelClass,
                $this->getFromStateClass(),
                is_string($toState) ? $toState : get_class($toState),
                method_exists($this, 'getTransitionClass') ? $this->getTransitionClass() : null,
            );

            return $transitionConfig && $transitionConfig->hasValidationRules();
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    protected function shouldHaveTransitionForm(): bool
    {
        try {
            if (! method_exists($this, 'hasTransitionClass')) {
                return true;
            }

            if ($this->hasTransitionClass()) {
                $transitionClass = $this->getTransitionClass();
                $modelClass = $this->getModel();

                if ($transitionClass && class_exists($transitionClass) && $modelClass && class_exists($modelClass)) {
                    $transitionInstance = new $transitionClass(new $modelClass);

                    if (method_exists($transitionInstance, 'mainFormValidationRules')) {
                        return false;
                    }

                    if (method_exists($transitionInstance, 'form') && ! empty($transitionInstance->form())) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            report($e);
        }

        if (! config('filament-flow.enabled', true)) {
            return false;
        }

        $modelClass = $this->getModel();
        if (! $modelClass) {
            return false;
        }

        try {
            $toState = $this->getToStateClass();
            $transitionConfig = app(TransitionFormService::class)->getTransitionConfig(
                $modelClass,
                $this->getFromStateClass(),
                is_string($toState) ? $toState : get_class($toState),
                method_exists($this, 'getTransitionClass') ? $this->getTransitionClass() : null,
            );

            return $transitionConfig && $transitionConfig->fields()->count() > 0;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    protected function getSchemaFromDatabase(): array
    {
        if (! config('filament-flow.enabled', true)) {
            return [];
        }

        $service = app(TransitionFormService::class);
        $modelClass = $this->getModel();

        if (! $modelClass) {
            return [];
        }

        try {
            $fromStateClass = $this->getFromStateClass();
            $toState = $this->getToStateClass();

            if (! $fromStateClass || ! $toState) {
                return [];
            }

            $toStateClass = is_string($toState) ? $toState : get_class($toState);
            $transitionClass = method_exists($this, 'getTransitionClass')
                ? $this->getTransitionClass()
                : null;
        } catch (Exception $e) {
            report($e);

            return [];
        }

        $transitionConfig = $service->getTransitionConfig($modelClass, $fromStateClass, $toStateClass, $transitionClass);

        if (! $transitionConfig) {
            return [];
        }

        $hasValidationRulesOnly = $transitionConfig->hasValidationRules()
            && $transitionConfig->fields()->count() === 0;

        if ($transitionConfig->requires_confirmation && ! $hasValidationRulesOnly) {
            $this->requiresConfirmation();
        }

        if ($transitionConfig->requires_reason) {
            $this->modalDescription(__('filament-flow::transitions.reason_required_description'));
        }

        return $service->buildFormSchema($transitionConfig);
    }
}
