<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Exception;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use RoBYCoNTe\FilamentFlow\Forms\Components\AssigneeSelect;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Support\WorkflowCacheManager;

class TransitionFormService
{
    /**
     * Get transition configuration from database
     * Supports both class names (e.g., App\States\Order\PendingState) and state names (e.g., 'refunded')
     *
     * @param  string  $modelClass  The model class
     * @param  string  $fromStateClass  The from state class or name
     * @param  string  $toStateClass  The to state class or name
     * @param  string|null  $transitionClass  Optional transition class to filter by (when multiple transitions exist for same from/to states)
     */
    public function getTransitionConfig(string $modelClass, string $fromStateClass, string $toStateClass, ?string $transitionClass = null): ?WorkflowTransition
    {
        // Get workflow for this model (with tenant fallback support)
        $workflow = Workflow::findForModel($modelClass);
        if (! $workflow) {
            return null;
        }

        $cache = new WorkflowCacheManager;
        $cacheKey = "trans_cfg:{$workflow->id}:{$fromStateClass}:{$toStateClass}:{$transitionClass}";
        $ttl = config('filament-flow.cache.safety_ttl', 86400);

        return $cache->remember($cacheKey, $ttl, function () use ($workflow, $fromStateClass, $toStateClass, $transitionClass) {
            $fromState = WorkflowState::where('workflow_id', $workflow->id)
                ->where(function ($query) use ($fromStateClass) {
                    $query->where('class_name', $fromStateClass)
                        ->orWhere('name', $fromStateClass);
                })
                ->first();

            $toState = WorkflowState::where('workflow_id', $workflow->id)
                ->where(function ($query) use ($toStateClass) {
                    $query->where('class_name', $toStateClass)
                        ->orWhere('name', $toStateClass);
                })
                ->first();

            if (! $fromState || ! $toState) {
                return null;
            }

            $query = WorkflowTransition::where('workflow_id', $workflow->id)
                ->where('from_state_id', $fromState->id)
                ->where('to_state_id', $toState->id)
                ->with('fields');

            if ($transitionClass) {
                $query->where('class_name', $transitionClass);
            }

            return $query->first();
        }, [$cache->stateTag($workflow->id)]);
    }

    /**
     * Build form schema from transition configuration
     */
    public function buildFormSchema(WorkflowTransition $transition): array
    {
        $schema = [];

        // Get fields ordered by sort_order
        $fields = $transition->fields()->orderBy('sort_order')->get();

        foreach ($fields as $field) {
            // Skip invalid fields (without name or type)
            if (empty($field->name) && empty($field->field_name)) {
                continue;
            }
            if (empty($field->type) && empty($field->field_type)) {
                continue;
            }

            $component = $this->buildFieldComponent($field);
            if ($component) {
                $schema[] = $component;
            }
        }

        return $schema;
    }

    /**
     * Build a single form component from field configuration
     */
    protected function buildFieldComponent($field)
    {
        $component = match ($field->field_type) {
            'email' => TextInput::make($field->field_name)->email(),
            'number' => TextInput::make($field->field_name)->numeric(),
            'textarea' => Textarea::make($field->field_name),
            'select' => Select::make($field->field_name),
            'assignee' => AssigneeSelect::make($field->field_name),
            'date' => DatePicker::make($field->field_name),
            'datetime' => DateTimePicker::make($field->field_name),
            'toggle' => Toggle::make($field->field_name),
            default => TextInput::make($field->field_name),
        };

        // Apply label
        if ($field->label) {
            $component->label($field->label);
        }

        // Apply required
        if ($field->is_required) {
            $component->required();
        }

        // Apply validation rules
        if ($field->validation_rules && is_array($field->validation_rules)) {
            $component->rules($field->validation_rules);
        }

        // Apply field configuration (rows, placeholder, etc.)
        if ($field->field_config && is_array($field->field_config)) {
            $this->applyFieldConfig($component, $field->field_config);
        }

        return $component;
    }

    /**
     * Apply field configuration to component
     */
    protected function applyFieldConfig($component, array $config): void
    {
        // Options (for select, radio, etc.)
        if (isset($config['options']) && method_exists($component, 'options')) {
            $component->options($config['options']);
        }

        // Rows (for textarea)
        if (isset($config['rows']) && method_exists($component, 'rows')) {
            $component->rows($config['rows']);
        }

        // MaxLength
        if (isset($config['maxLength']) && method_exists($component, 'maxLength')) {
            $component->maxLength($config['maxLength']);
        }

        // Placeholder
        if (isset($config['placeholder']) && method_exists($component, 'placeholder')) {
            $component->placeholder($config['placeholder']);
        }

        // Helper text
        if (isset($config['helperText']) && method_exists($component, 'helperText')) {
            $component->helperText($config['helperText']);
        }

        // Prefix (for money, etc.)
        if (isset($config['prefix']) && method_exists($component, 'prefix')) {
            $component->prefix($config['prefix']);
        }

        // Suffix
        if (isset($config['suffix']) && method_exists($component, 'suffix')) {
            $component->suffix($config['suffix']);
        }

        // Min/Max for numbers
        if (isset($config['min']) && method_exists($component, 'min')) {
            $component->min($config['min']);
        }

        if (isset($config['max']) && method_exists($component, 'max')) {
            $component->max($config['max']);
        }

        // Step for numbers
        if (isset($config['step']) && method_exists($component, 'step')) {
            $component->step($config['step']);
        }

        // Assignment type (for AssigneeSelect)
        if (isset($config['assignment_type']) && method_exists($component, 'assignmentType')) {
            $component->assignmentType($config['assignment_type']);
        }
    }

    /**
     * Apply transition data to model
     * Maps form data to model attributes based on transition field configuration
     *
     * @throws Exception
     */
    public function applyTransitionDataToModel($model, WorkflowTransition $transition, array $data): void
    {
        $fields = $transition->fields;

        foreach ($fields as $field) {
            // Skip if field should not be saved to model
            if (! $field->save_to_model) {
                continue;
            }

            // Skip if data not present
            if (! isset($data[$field->field_name])) {
                continue;
            }

            $value = $data[$field->field_name];

            // Apply based on mapping type
            switch ($field->mapping_type) {
                case 'direct':
                    // Direct assignment to model attribute
                    if ($field->model_attribute) {
                        $model->{$field->model_attribute} = $value;
                    }
                    break;

                case 'transform':
                    // Apply transformation before saving
                    if ($field->mapping_config && isset($field->mapping_config['transformer'])) {
                        $transformer = $field->mapping_config['transformer'];
                        if (is_callable($transformer)) {
                            $model->{$field->model_attribute} = $transformer($value);
                        }
                    }
                    break;

                case 'relationship':
                    // Save to relationship
                    if ($field->mapping_config && isset($field->mapping_config['relationship'])) {
                        $relationship = $field->mapping_config['relationship'];
                        if (method_exists($model, $relationship)) {
                            $model->$relationship()->associate($value);
                        }
                    }
                    break;

                case 'custom':
                    // Use custom handler class
                    if ($field->mapping_config && isset($field->mapping_config['handler'])) {
                        $handler = $field->mapping_config['handler'];
                        if (class_exists($handler)) {
                            app($handler)->handle($model, $field->field_name, $value);
                        }
                    }
                    break;

                case 'assignment':
                    // Sync workflow assignments on the model
                    $type = $field->mapping_config['assignment_type'] ?? 'primary';
                    if (method_exists($model, 'syncAssignments')) {
                        $model->syncAssignments((array) $value, $type, auth()->user());
                    }
                    break;

                case 'ignore':
                    // Do nothing, field is for display/logging only
                    break;
            }
        }
    }
}
