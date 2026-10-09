<?php

namespace RoBYCoNTe\FilamentFlow\Concerns;

use Exception;
use RoBYCoNTe\FilamentFlow\Contracts\ProvidesRequestScopeTree;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;

/**
 * The form a transition asks for, attached to an action: the fields declared by the rules of
 * the transition, and the tenant that makes the lookup find them.
 *
 * A form appears only when the rules have fields to fill in: a transition that asks for values
 * asks for them even when it also validates them, while formula rules stay out — nobody types a
 * formula.
 */
trait HasTransitionForm
{
    protected function setupTransitionForm(): void
    {
        // The tree of the fields a request opens needs room: only that dialog is wide.
        $this->modalWidth(fn (): ?string => $this->declaresRequestScope() ? '5xl' : null);

        $this->schema(function () {
            // Transition class takes priority over database configuration
            try {
                if ($this->hasTransitionClass()) {
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

    /**
     * The tenant of the row: when a host keeps one workflow per owner (one per call, for
     * example), without it the transition is not found — nor are its rules, nor its fields —
     * and the dialog that should ask for the values never opens.
     */
    protected function getTransitionTenantId(): ?int
    {
        $record = $this->getRecord();

        return $record !== null && method_exists($record, 'getWorkflowTenantId')
            ? $record->getWorkflowTenantId()
            : null;
    }

    /**
     * Whether the transition this action runs opens fields to the answering side, and the host
     * can say which fields there are to choose from.
     */
    private function declaresRequestScope(): bool
    {
        $modelClass = $this->getModel();

        if (! $modelClass || ! app()->bound(ProvidesRequestScopeTree::class)) {
            return false;
        }

        try {
            $toState = $this->getToStateClass();

            return app(TransitionFormService::class)->getTransitionConfig(
                $modelClass,
                $this->getFromStateClass(),
                is_string($toState) ? $toState : get_class($toState),
                $this->getTransitionClass(),
                $this->getTransitionTenantId(),
            )?->declaredRequestScope() !== null;
        } catch (Exception $e) {
            report($e);

            return false;
        }
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
                $this->getTransitionClass(),
                $this->getTransitionTenantId(),
            );

            // Skip the form only when the rules have **no** fields to fill in: a transition
            // that asks for values asks for them, even when it only validates them.
            return $transitionConfig
                && $transitionConfig->hasValidationRules()
                && $transitionConfig->fields()->count() === 0
                && $transitionConfig->declaredRequestScope() === null;
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    protected function shouldHaveTransitionForm(): bool
    {
        try {
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
                $this->getTransitionClass(),
                $this->getTransitionTenantId(),
            );

            return $transitionConfig
                && ($transitionConfig->fields()->count() > 0 || $transitionConfig->declaredRequestScope() !== null);
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
            $transitionClass = $this->getTransitionClass();
        } catch (Exception $e) {
            report($e);

            return [];
        }

        $transitionConfig = $service->getTransitionConfig(
            $modelClass,
            $fromStateClass,
            $toStateClass,
            $transitionClass,
            $this->getTransitionTenantId(),
        );

        if (! $transitionConfig) {
            return [];
        }

        $hasValidationRulesOnly = $transitionConfig->hasValidationRules()
            && $transitionConfig->fields()->count() === 0
            && $transitionConfig->declaredRequestScope() === null;

        if ($transitionConfig->requires_confirmation && ! $hasValidationRulesOnly) {
            $this->requiresConfirmation();
        }

        if ($transitionConfig->requires_reason) {
            $this->modalDescription(__('filament-flow::transitions.reason_required_description'));
        }

        return $service->buildFormSchema($transitionConfig, $this->getRecord());
    }
}
