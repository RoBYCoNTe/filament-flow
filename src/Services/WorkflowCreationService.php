<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Exceptions\InitialStateNotFoundException;
use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowNotFoundException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use Throwable;

class WorkflowCreationService
{
    /**
     * Check if user can create a record for given model type.
     * Delegates to WorkflowStateAccessService which checks the initial state's access rules.
     */
    public function canCreate(string $modelType, Model $user): bool
    {
        return app(WorkflowStateAccessService::class)->canCreate($modelType, $user);
    }

    /**
     * Create a new record with workflow initialization
     *
     * @throws Exception
     * @throws Throwable
     */
    public function createRecord(string $modelType, array $data, Model $user): Model
    {
        $workflow = Workflow::findForModel($modelType);

        if (! $workflow) {
            throw new WorkflowNotFoundException($modelType);
        }

        // Check permission via initial state's access rules
        if (! $this->canCreate($modelType, $user)) {
            throw new UnauthorizedTransitionException(new $modelType, '', '', $user, 'User not authorized to create records');
        }

        // Get initial state
        $initialState = $workflow->initialState();
        if (! $initialState) {
            throw new InitialStateNotFoundException;
        }

        DB::beginTransaction();

        try {
            // Create the record
            $record = new $modelType;
            $record->fill($data);
            $record->{$workflow->state_column} = $initialState->class_name ?? $initialState->name;

            // Auto-set owner field if fillable and not already provided
            $ownerField = config('filament-flow.state_access.owner_field', 'user_id');
            if ($record->isFillable($ownerField) && ! isset($data[$ownerField])) {
                $record->{$ownerField} = $user->getKey();
            }

            $record->save();

            // Auto-assign creator if configured
            $creationPolicy = $workflow->creation_policy ?? [];
            if ($creationPolicy['auto_assign_creator'] ?? false) {
                if (method_exists($record, 'assignments')) {
                    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                    $record->assignments()->create([
                        'user_id' => $user->id,
                        'assignment_type' => $creationPolicy['assignment_type'] ?? 'primary',
                        'assigned_by' => $user->id,
                    ]);
                }
            }

            DB::commit();

            return $record;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
