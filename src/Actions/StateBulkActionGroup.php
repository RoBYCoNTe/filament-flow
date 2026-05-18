<?php

namespace RoBYCoNTe\FilamentFlow\Actions;

use Exception;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use Spatie\ModelStates\State;

/**
 * StateBulkActionGroup generates bulk actions for all possible state transitions.
 *
 * This helper class automatically creates StateBulkAction instances for each valid
 * state transition defined in the workflow. Actions are only shown when ALL selected
 * records share the same state and can perform that transition.
 *
 * @example
 * ```php
 * use RoBYCoNTe\FilamentFlow\Actions\StateBulkActionGroup;
 *
 * BulkActionGroup::make([
 *     ...StateBulkActionGroup::make('state', OrderState::class),
 *     DeleteBulkAction::make(),
 * ])
 * ```
 */
class StateBulkActionGroup
{
    /**
     * Generate StateBulkAction instances for all possible state transitions.
     *
     * This method returns an array of BulkAction that can be spread into
     * a BulkActionGroup.
     *
     * @param  string  $columnName  The state column name
     * @param  string  $stateClass  The base state class (e.g., OrderState)
     * @return array Array of BulkAction instances
     */
    public static function make(string $columnName, string $stateClass): array
    {
        return static::generateStateBulkActions($stateClass, $columnName);
    }

    /**
     * Generate a single BulkActionGroup containing all state transition actions.
     * Alternative to using make() with spread operator.
     *
     * @param  string  $columnName  The state column name
     * @param  string  $stateClass  The base state class (e.g., OrderState)
     */
    public static function group(string $columnName, string $stateClass): BulkActionGroup
    {
        return BulkActionGroup::make(
            static::generateStateBulkActions($stateClass, $columnName)
        )
            ->label('Change Status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary');
    }

    /**
     * Generate StateBulkAction instances for all possible state transitions.
     *
     * This method:
     * 1. Retrieves all transitions from the workflow configuration
     * 2. Creates a StateBulkAction for each transition
     * 3. Configures each action to only show when ALL selected records can perform it
     *
     * @param  string  $stateClass  The base state class
     * @param  string  $attribute  The state attribute name
     * @return array Array of StateBulkAction instances
     */
    protected static function generateStateBulkActions(string $stateClass, string $attribute): array
    {
        $actions = [];

        if (! config('filament-flow.enabled', true)) {
            return $actions;
        }

        try {
            // Get the namespace of the state class to match against
            $stateClassNamespace = (new ReflectionClass($stateClass))->getNamespaceName();

            // Find workflow by loading candidates and filtering in PHP (avoids SQLite LIKE backslash issues)
            $workflow = Workflow::where('state_column', $attribute)
                ->where('is_active', true)
                ->with('states')
                ->get()
                ->first(function ($w) use ($stateClassNamespace) {
                    return $w->states->contains(fn ($s) => str_starts_with($s->class_name ?? '', $stateClassNamespace.'\\'));
                });

            if (! $workflow) {
                return $actions;
            }

            // Get all transitions for this workflow
            $transitions = WorkflowTransition::where('workflow_id', $workflow->id)
                ->with(['fromState', 'toState'])
                ->get();

            // Group transitions by destination state to detect duplicates
            $transitionsByToState = [];
            foreach ($transitions as $transition) {
                $toState = $transition->toState;
                if ($toState) {
                    $toStateId = $toState->id;
                    if (! isset($transitionsByToState[$toStateId])) {
                        $transitionsByToState[$toStateId] = [];
                    }
                    $transitionsByToState[$toStateId][] = $transition;
                }
            }

            foreach ($transitions as $transition) {
                $fromState = $transition->fromState;
                $toState = $transition->toState;

                if (! $fromState || ! $toState) {
                    continue;
                }

                // Determine the from and to state identifiers
                // For PHP states, use the class name; for database states, use the name
                $fromStateIdentifier = $fromState->class_name ?: $fromState->name;
                $toStateIdentifier = $toState->class_name ?: $toState->name;

                // Store values in variables to use in closures
                $toIcon = $toState->icon;
                $toColor = $toState->color;

                // Create label: add "from" state only if there are multiple transitions to the same destination
                // Example: "Processing" (unique) vs "Cancelled (from Pending)" (duplicate)
                $hasDuplicateDestination = count($transitionsByToState[$toState->id]) > 1;
                $toLabel = $hasDuplicateDestination
                    ? $toState->label.' ('.__('from').' '.$fromState->label.')'
                    : $toState->label;

                // Create a simple BulkAction instead of StateBulkAction
                $action = BulkAction::make(Str::slug($fromState->name.'-to-'.$toState->name))
                    ->label($toLabel)
                    ->icon($toIcon)
                    ->color($toColor ?: 'primary')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) use ($fromStateIdentifier, $toStateIdentifier, $attribute) {
                        $updatedCount = 0;
                        $totalCount = $records->count();

                        foreach ($records as $record) {
                            $currentState = $record->{$attribute};

                            // Check if current state matches the "from" state (handle both State objects and strings)
                            $isMatchingState = false;
                            if (is_string($currentState) && is_string($fromStateIdentifier)) {
                                $isMatchingState = $currentState === $fromStateIdentifier;
                            } elseif ($currentState instanceof State && $fromStateIdentifier instanceof State) {
                                $isMatchingState = $currentState->equals($fromStateIdentifier);
                            } elseif ($currentState instanceof State && is_string($fromStateIdentifier)) {
                                $isMatchingState = get_class($currentState) === $fromStateIdentifier;
                            } elseif (is_string($currentState) && $fromStateIdentifier instanceof State) {
                                $isMatchingState = $currentState === get_class($fromStateIdentifier);
                            }

                            if ($isMatchingState) {
                                // Check when can transition
                                $canTransition = false;

                                if (method_exists($record, 'canTransitionTo')) {
                                    $canTransition = $record->canTransitionTo($toStateIdentifier, $attribute);
                                }

                                if ($canTransition) {
                                    try {
                                        if (method_exists($record, 'transitionTo')) {
                                            $record->transitionTo($toStateIdentifier);
                                            $updatedCount++;
                                        }
                                    } catch (Exception $e) {
                                        report($e);
                                    }
                                }
                            }
                        }

                        // Determine notification status
                        $status = $updatedCount === $totalCount ? 'success' : ($updatedCount > 0 ? 'warning' : 'danger');

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
                                'total' => $totalCount,
                            ]))
                            ->send();
                    });

                $actions[] = $action;
            }
        } catch (Exception $e) {
            // If we can't determine transitions, return empty array
            report($e);
        }

        return $actions;
    }
}
