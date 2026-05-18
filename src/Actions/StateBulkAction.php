<?php

namespace RoBYCoNTe\FilamentFlow\Actions;

use Exception;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateActions;
use RoBYCoNTe\FilamentFlow\Concerns\HasStateAttributes;
use RoBYCoNTe\FilamentFlow\Concerns\HasTransitionForm;
use RoBYCoNTe\FilamentFlow\Concerns\ResolvesActionAttributes;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAction;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAttributes as HasStateAttributesContract;
use Spatie\ModelStates\State;

class StateBulkAction extends BulkAction implements HasStateAction, HasStateAttributesContract
{
    use HasStateActions;
    use HasStateAttributes;
    use HasTransitionForm;
    use ResolvesActionAttributes;

    public string|State|null $fromState = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->label(fn () => $this->resolveLabel($this->getFromState()));
        $this->color(fn () => $this->resolveColor($this->getFromState()));
        $this->icon(fn () => $this->resolveIcon($this->getFromState()));
        $this->tooltip(fn () => $this->resolveDescription($this->getFromState()));
        $this->setActionAttributes();
        $this->setupTransitionForm();
        $this->action(function (Collection $records, $data) {
            $updatedCount = 0;
            $records->each(callback: function ($record) use ($data, &$updatedCount) {
                $currentState = $record->{$this->getAttribute()};
                $fromState = $this->getFromState();

                // Check if current state matches the "from" state (handle both State objects and strings)
                $isMatchingState = false;
                if (is_string($currentState) && is_string($fromState)) {
                    $isMatchingState = $currentState === $fromState;
                } elseif ($currentState instanceof State && $fromState instanceof State) {
                    $isMatchingState = $currentState->equals($fromState);
                } elseif ($currentState instanceof State && is_string($fromState)) {
                    $isMatchingState = $currentState::getMorphClass() === $fromState;
                } elseif (is_string($currentState) && $fromState instanceof State) {
                    $isMatchingState = $currentState === $fromState::getMorphClass();
                }

                if ($isMatchingState) {
                    $transitioned = false;

                    // Check when can transition (supports both Spatie and database transitions)
                    $canTransition = false;

                    if (is_string($currentState)) {
                        // Database-only current state
                        if (method_exists($record, 'canTransitionToFromDatabaseString')) {
                            $canTransition = $record->canTransitionToFromDatabaseString($currentState, $this->getToStateClass(), $this->getAttribute());
                        }
                    } else {
                        // PHP State object
                        if (method_exists($record, 'canTransitionTo')) {
                            $canTransition = $record->canTransitionTo($this->getToStateClass(), $this->getAttribute());
                        } elseif ($currentState instanceof State) {
                            try {
                                $canTransition = $currentState->canTransitionTo($this->getToStateClass());
                            } catch (Exception $e) {
                                report($e);
                            }
                        }
                    }

                    if ($canTransition) {
                        // Use model's transitionTo if available (supports database transitions)
                        if (method_exists($record, 'transitionTo')) {
                            try {
                                if (empty($data)) {
                                    $record->transitionTo($this->getToStateClass());
                                } else {
                                    $record->transitionTo($this->getToStateClass(), $data);
                                }
                                $transitioned = true;
                            } catch (Exception $e) {
                                report($e);
                            }
                        } elseif ($currentState instanceof State) {
                            // Fallback to Spatie's default (only if current state is a State object)
                            try {
                                if (empty($data)) {
                                    $currentState->transitionTo($this->getToStateClass());
                                } else {
                                    $currentState->transitionTo($this->getToStateClass(), $data);
                                }
                                $transitioned = true;
                            } catch (Exception $e) {
                                report($e);
                            }
                        }
                    }

                    if ($transitioned) {
                        $updatedCount++;
                    }
                }
            });

            $status = $updatedCount === $records->count() ? 'success' : ($updatedCount > 0 ? 'warning' : 'danger');
            Notification::make()
                ->color($status)
                ->title($status === 'success'
                    ? __('filament-flow.bulk_action.notification.title.success')
                    : ($status === 'warning'
                        ? __('filament-flow.bulk_action.notification.title.partial_success')
                        : __('filament-flow.bulk_action.notification.title.failure')
                    ))
                ->body(trans_choice('filament-flow.bulk_action.notification.body', $updatedCount, [
                    'count' => $updatedCount,
                    'total' => $records->count(),
                ]))
                ->send();
        });
    }

    public function transition(string|State|null $fromState, string|State|null $toState): self
    {
        $this->toState = $toState;
        $this->fromState = $fromState;

        return $this;
    }

    public function getFromState(): string|State|null
    {
        return $this->evaluate($this->fromState);
    }
}
