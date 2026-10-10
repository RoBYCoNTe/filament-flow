<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\FieldRuleSource;
use RoBYCoNTe\FilamentFlow\Contracts\NarrowsFieldPermissions;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Support\ValidationRuleRegistry;
use RoBYCoNTe\FilamentFlow\Validation\ValidationResult;

/**
 * The single validation pass of the workflow engine.
 *
 * It composes every source of truth a state transition has:
 *
 *  1. field permissions of the state (visible / readonly / locked / required /
 *     validation_rules) — a hidden field is never validated, never required;
 *  2. the rules declared on the transition itself, with their condition
 *     (conditional validation) and their custom message;
 *  3. the rules the host application declares for its own fields
 *     ({@see FieldRuleSource}: the scheme schema, in this project);
 *  4. expression rules, evaluated by the host formula provider with the live
 *     form data as context (`form.*`).
 *
 * Errors are keyed by form path, so the caller can attach them to the matching
 * component or list them in a summary when the path has no component.
 */
class WorkflowValidationService
{
    use AppliesValidationRules;
    use EvaluatesValidationValues;
    use ResolvesValidationContext;

    /** Marker for "the value is not there at all", distinct from a null value. */
    private const MISSING = '__filament_flow_missing__';

    /** Record of the pass that is running: the labels come from it. */
    private ?Model $record = null;

    public function __construct(
        private readonly WorkflowFieldPermissionsService $permissions,
        private readonly ValidationRuleRegistry $registry,
    ) {}

    /**
     * @param  array<string,mixed>  $data  live form state (not persisted yet)
     * @param  string|null  $targetState  state the record is moving to: its field
     *                                    rules are the ones that apply
     */
    public function validate(
        Model $record,
        ?Model $user = null,
        array $data = [],
        ?WorkflowTransition $transition = null,
        ?string $targetState = null,
    ): ValidationResult {
        $this->record = $record;

        $subject = $this->subjectIn($record, $targetState);

        // The pass works on one data space: the live form state, or the values
        // already stored on the record when the caller has no form to submit.
        $data = $data === [] ? $this->recordData($record) : $data;

        $permissions = $this->narrowed($subject, $user, $data, $this->permissions->getFieldPermissions($subject, $user));

        $errors = [];
        $labels = [];

        $this->applyPermissionRules($subject, $data, $permissions, $errors, $labels);

        if ($transition !== null) {
            $this->applyTransitionRules($subject, $data, $transition, $permissions, $errors, $labels);
        }

        $this->applyHostRules($subject, $user, $data, $permissions, $errors, $labels);

        return new ValidationResult($errors, $labels);
    }

    /**
     * What the host takes away from the permissions of the state because of the data. It can only
     * take away: a field the state hides stays hidden, whatever the host answers.
     *
     * @param  array<string,mixed>  $data
     * @param  array<string,array<string,mixed>>  $permissions
     * @return array<string,array<string,mixed>>
     */
    private function narrowed(Model $record, ?Model $user, array $data, array $permissions): array
    {
        if (! app()->bound(NarrowsFieldPermissions::class)) {
            return $permissions;
        }

        $narrowed = app(NarrowsFieldPermissions::class)->narrow($record, $user, $data, $permissions);

        foreach ($permissions as $path => $permission) {
            if (($permission['visible'] ?? true) === false) {
                $narrowed[$path] = $permission;
            }
        }

        return $narrowed;
    }

    /**
     * Same pass for a *payload* (the data a transition carries): the payload is
     * layered on top of the stored values, so a caller that only sends the
     * fields it collected still validates the whole record.
     *
     * @param  array<string,mixed>  $payload
     */
    public function validatePayload(
        Model $record,
        ?Model $user = null,
        array $payload = [],
        ?WorkflowTransition $transition = null,
        ?string $targetState = null,
    ): ValidationResult {
        return $this->validate(
            $record,
            $user,
            $payload === [] ? [] : $this->dataFor($record, $payload),
            $transition,
            $targetState,
        );
    }

    /**
     * Values stored on the record (its attributes plus the JSON column with the
     * scheme data) overlaid with the given payload.
     *
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    public function dataFor(Model $record, array $overrides = []): array
    {
        $data = $this->recordData($record);

        return $overrides === [] ? $data : array_replace_recursive($data, $overrides);
    }
}
