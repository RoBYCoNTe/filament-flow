<?php

namespace RoBYCoNTe\FilamentFlow\Actions;

use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateActions;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateAttributes;
use RoBYCoNTe\FilamentFlow\Concerns\HasTransitionForm;
use RoBYCoNTe\FilamentFlow\Concerns\ResolvesActionAttributes;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAction;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAttributes as HasStateAttributesContract;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\Services\TransitionFormService;
use Spatie\ModelStates\State;

class StateAction extends Action implements HasStateAction, HasStateAttributesContract
{
    use HasStateActions {
        HasStateActions::getTransitionClass as parentGetTransitionClass;
    }
    use HasStateAttributes;
    use HasTransitionForm {
        HasStateActions::getFromStateClass insteadof HasTransitionForm;
    }
    use ResolvesActionAttributes;

    protected ?string $explicitTransitionClass = null;

    public function withTransitionClass(?string $class): static
    {
        $this->explicitTransitionClass = $class;

        return $this;
    }

    public function getTransitionClass(): ?string
    {
        return $this->explicitTransitionClass ?? $this->parentGetTransitionClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (Model $record) => $this->resolveLabel($record->{$this->getAttribute()}));
        $this->color(fn (Model $record) => $this->resolveColor($record->{$this->getAttribute()}));
        $this->icon(fn (Model $record) => $this->resolveIcon($record->{$this->getAttribute()}));
        $this->tooltip(fn (Model $record) => $this->resolveDescription($record->{$this->getAttribute()}));

        $this->setActionAttributes();
        $this->setupTransitionForm();

        $this->hidden(function (Model $record) {
            $currentState = $record->{$this->getAttribute()};
            $toStateClass = $this->getToStateClass();

            if (! $currentState || $toStateClass === null) {
                return true;
            }

            if (is_string($currentState)) {
                if (config('filament-flow.enabled', true) && method_exists($record, 'canTransitionToFromDatabaseString')) {
                    return ! $record->canTransitionToFromDatabaseString($currentState, $toStateClass, $this->getAttribute());
                }

                return true;
            }

            if (is_string($toStateClass) && $currentState instanceof State) {
                $baseStateClass = $record->getCasts()[$this->getAttribute()] ?? null;

                if ($baseStateClass && method_exists($baseStateClass, 'resolveStateClass')) {
                    $resolvedClass = $baseStateClass::resolveStateClass($toStateClass);

                    if ($resolvedClass === null && config('filament-flow.enabled', true)) {
                        if (method_exists($record, 'canTransitionToFromDatabase')) {
                            return ! $record->canTransitionToFromDatabase($currentState, $toStateClass, $this->getAttribute());
                        }

                        return true;
                    }
                }
            }

            if ($currentState instanceof State) {
                try {
                    if ($currentState->canTransitionTo($toStateClass)) {
                        return false;
                    }
                } catch (Exception $e) {
                    report($e);
                }
            }

            if (config('filament-flow.enabled', true) && method_exists($record, 'canTransitionToFromDatabase')) {
                return ! $record->canTransitionToFromDatabase($currentState, $toStateClass, $this->getAttribute());
            }

            return true;
        });

        $this->before(function (Action $action) {
            $record = $this->getRecord();
            if ($record) {
                $this->validateMainFormIfNeeded($action, $record);
            }
        });

        $this->action(function (Action $action, $record, array $data): void {
            $this->validateMainFormIfNeeded($action, $record);

            $toStateClass = $this->getToStateClass();

            $newStateLabel = match (true) {
                is_string($toStateClass) => app(StateService::class)->getStateMetadata(
                    get_class($record),
                    $toStateClass,
                    $this->getAttribute()
                )['label'] ?? $toStateClass,
                $toStateClass instanceof State => method_exists($toStateClass, 'getLabel')
                    ? $toStateClass->getLabel()
                    : $toStateClass::getMorphClass(),
                default => null,
            };

            $target = method_exists($record, 'transitionTo')
                ? $record
                : $record->{$this->getAttribute()};

            empty($data)
                ? $target->transitionTo($toStateClass)
                : $target->transitionTo($toStateClass, $data);

            Notification::make()
                ->success()
                ->title(__('State Updated'))
                ->body($newStateLabel
                    ? __('The state has been changed to :state', ['state' => $newStateLabel])
                    : __('The state has been updated successfully')
                )
                ->send();
        });
        $this->after(function ($record, $livewire) {
            $record->refresh();

            // Force a full page reload so the form, actions,
            // and field permissions reflect the new workflow state.
            if (method_exists($livewire, 'js')) {
                $livewire->js('setTimeout(() => window.location.reload(), 100)');
            }
        });
    }

    private function hasTransitionClassForm(): bool
    {
        if (! method_exists($this, 'hasTransitionClass') || ! $this->hasTransitionClass()) {
            return false;
        }

        $transitionClass = $this->getTransitionClass();
        $modelClass = $this->getModel();

        if (! $transitionClass || ! class_exists($transitionClass) || ! $modelClass || ! class_exists($modelClass)) {
            return false;
        }

        try {
            $transitionInstance = new $transitionClass(new $modelClass);

            return method_exists($transitionInstance, 'form')
                && ! empty($transitionInstance->form());
        } catch (Exception $e) {
            report($e);

            return false;
        }
    }

    private function getTransition(Model $record): ?WorkflowTransition
    {
        $currentState = $record->{$this->getAttribute()};
        $currentStateClass = is_string($currentState) ? $currentState : get_class($currentState);

        return app(TransitionFormService::class)->getTransitionConfig(
            get_class($record),
            $currentStateClass,
            $this->getToStateClass(),
            $this->getTransitionClass()
        );
    }

    private function getActionForm(Action $action): Schema
    {
        $livewire = $action->getLivewire();

        return match (true) {
            $livewire instanceof EditRecord => $livewire->form,
            $livewire instanceof ListRecords => $livewire->getMountedActionSchema(),
            default => $livewire,
        };
    }

    /**
     * @throws ValidationException
     */
    protected function validateMainFormIfNeeded(Action $action, Model $record): void
    {
        if ($this->hasTransitionClassForm()) {
            return;
        }

        $transition = $this->getTransition($record);
        if (! $transition) {
            return;
        }

        $validationRules = $transition->getValidationRules();
        $validationMessages = $transition->getValidationMessages();

        if (! $validationRules) {
            return;
        }

        $form = $this->getActionForm($action);
        $formData = $form->getRawState();
        $statePath = $form->getStatePath();

        if (! is_array($formData)) {
            return;
        }

        $validator = Validator::make($formData, $validationRules, $validationMessages);

        if ($validator->passes()) {
            return;
        }

        $prefixedErrors = collect($validator->errors()->messages())
            ->mapWithKeys(fn (array $messages, string $field) => ["{$statePath}.{$field}" => $messages])
            ->all();

        $livewire = $action->getLivewire();
        if ($livewire) {
            foreach ($prefixedErrors as $field => $messages) {
                foreach ($messages as $message) {
                    $livewire->addError($field, $message);
                }
            }

            if (method_exists($livewire, 'unmountAction')) {
                $livewire->unmountAction(false);
            }
        }

        Notification::make()
            ->danger()
            ->title(__('Validation Failed'))
            ->body(__('Please correct the errors in the form before proceeding.'))
            ->send();

        throw ValidationException::withMessages($prefixedErrors);
    }
}
