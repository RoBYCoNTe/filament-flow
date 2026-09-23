<?php

namespace RoBYCoNTe\FilamentFlow\Actions;

use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
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
use RoBYCoNTe\FilamentFlow\Services\WorkflowValidationService;
use Spatie\ModelStates\State;
use Throwable;

/**
 * A Filament action that moves a record: the state it comes from, the one it goes to, and the
 * transition behind it — so its label, its colour and its confirmation come from the
 * declaration.
 *
 * A note for a host that wraps it: the dialog of the action is the form of its transition,
 * built from the fields of its rules. Replacing the submit action of the modal
 * (`modalSubmitAction`) breaks it — the form then submits natively, with a GET on the Livewire
 * endpoint. The application of a call builds its own action for exactly this reason.
 */
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

            // A Livewire refresh rebuilds the schema, the actions and the field
            // permissions of the new state without losing the page (errors,
            // scroll position, open panels). A full reload stays available for
            // hosts that need it.
            if (! method_exists($livewire, 'js')) {
                return;
            }

            if (config('filament-flow.ui.reload_after_transition', false)) {
                $livewire->js('setTimeout(() => window.location.reload(), 100)');

                return;
            }

            $livewire->js('setTimeout(() => $wire.$refresh(), 100)');
        });
    }

    private function hasTransitionClassForm(): bool
    {
        if (! $this->hasTransitionClass()) {
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
        $tenantId = method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null;

        return app(TransitionFormService::class)->getTransitionConfig(
            get_class($record),
            $currentStateClass,
            $this->getToStateClass(),
            $this->getTransitionClass(),
            $tenantId
        );
    }

    private function getActionForm(Action $action): Schema
    {
        $livewire = $action->getLivewire();

        return match (true) {
            $livewire instanceof EditRecord => $livewire->form,
            $livewire instanceof ListRecords => $this->mountedActionSchema($livewire),
            default => $livewire->getSchema('form') ?? Schema::make($livewire),
        };
    }

    /**
     * Schema of the action mounted on a list page, through the public API
     * (`getMountedActionSchema()` is protected in Filament).
     */
    private function mountedActionSchema(ListRecords $livewire): Schema
    {
        $name = $livewire->getMountedActionSchemaName();

        return ($name !== null ? $livewire->getSchema($name) : null) ?? Schema::make($livewire);
    }

    /**
     * Validates the form through the workflow validation engine, so the rules
     * the action enforces are exactly the ones the engine enforces when the
     * transition is run from code (states, transitions, host fields, formulas).
     *
     * The messages are attached to the matching components; the ones that have
     * no component (virtual keys) are listed in the notification, because
     * otherwise they would be invisible.
     *
     * @throws ValidationException
     */
    protected function validateMainFormIfNeeded(Action $action, Model $record): void
    {
        if ($this->hasTransitionClassForm()) {
            return;
        }

        if (! config('filament-flow.validation.enabled', true)) {
            return;
        }

        [$formData, $statePath] = $this->rawFormState($action);

        $result = app(WorkflowValidationService::class)->validatePayload(
            $record,
            auth()->user(),
            $formData,
            $this->getTransition($record),
        );

        if ($result->isEmpty()) {
            return;
        }

        $this->failWithValidationResult($action, $result->errors(), $result->messages(), $statePath);
    }

    /**
     * Raw state of the form the action belongs to, and its state path.
     *
     * A table action on a page without a form has nothing to validate: the stored
     * record is the data space, so an empty state is returned instead of trying
     * to resolve a form that does not exist.
     *
     * @return array{0: array<string,mixed>, 1: string}
     */
    private function rawFormState(Action $action): array
    {
        try {
            $form = $this->getActionForm($action);
            $state = $form->getRawState();
            $statePath = (string) $form->getStatePath();
        } catch (Throwable) {
            return [[], ''];
        }

        return [is_array($state) ? $state : [], $statePath];
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  list<array{path:string,label:string,message:string}>  $summary
     *
     * @throws ValidationException
     */
    protected function failWithValidationResult(Action $action, array $errors, array $summary, string $statePath): void
    {
        $prefixedErrors = [];

        foreach ($errors as $path => $messages) {
            $key = $statePath === '' ? (string) $path : $statePath.'.'.$path;

            foreach ($messages as $message) {
                $livewire = $action->getLivewire();

                if ($livewire) {
                    $livewire->addError($key, $message);
                }

                $prefixedErrors[$key][] = $message;
            }
        }

        // Paths without a form component would be invisible: the notification is
        // what makes them actionable.
        Notification::make()
            ->danger()
            ->title(__('Validation Failed'))
            ->body(collect($summary)
                ->map(static fn (array $error): string => $error['label'].': '.$error['message'])
                ->implode('\n'))
            ->send();

        $livewire = $action->getLivewire();

        if ($livewire && method_exists($livewire, 'unmountAction')) {
            $livewire->unmountAction(false);
        }

        // A refused transition must leave the form showing its errors. Livewire
        // does not re-render the page on this exception, so the errors are
        // flashed and the page comes back with them: the fields, the tabs and any
        // summary can then be painted from the error bag.
        if ($livewire !== null && config('filament-flow.ui.reload_form_on_validation_failure', true)) {
            $bag = new ViewErrorBag;
            $bag->put('default', new MessageBag($prefixedErrors));
            session()->flash('errors', $bag);

            $livewire->redirect(request()->fullUrl(), navigate: false);
        }

        throw ValidationException::withMessages($prefixedErrors);
    }
}
