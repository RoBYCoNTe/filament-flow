<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Components\Component;
use RoBYCoNTe\FilamentFlow\Exceptions\InvalidComponentException;

class FormBuilderHelper
{
    /**
     * Build form components from field configuration
     *
     * @throws InvalidComponentException
     */
    public function buildComponents(array $fieldsConfig): array
    {
        $components = [];

        foreach ($fieldsConfig as $fieldName => $config) {
            if (! ($config['visible'] ?? true)) {
                continue;
            }

            $component = $this->buildComponent($fieldName, $config);
            if ($component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Build a single component from field configuration
     *
     * @throws InvalidComponentException
     */
    public function buildComponent(string $fieldName, array $config): ?Component
    {
        // Check if component type is explicitly defined
        $componentType = $config['component_type'] ?? null;

        if ($componentType) {
            $component = $this->createComponentByType($componentType, $fieldName, $config);
        } else {
            // Infer component type from field name
            $component = $this->inferComponentFromFieldName($fieldName, $config);
        }

        if (! $component) {
            return null;
        }

        // Apply common configuration
        return $this->applyConfiguration($component, $fieldName, $config);
    }

    /**
     * Create component by explicit type
     *
     * @noinspection PhpUnusedParameterInspection
     */
    protected function createComponentByType(string $type, string $fieldName, array $config): ?Component
    {
        return match ($type) {
            'text' => TextInput::make($fieldName),
            'email' => TextInput::make($fieldName)->email(),
            'password' => TextInput::make($fieldName)->password(),
            'tel' => TextInput::make($fieldName)->tel(),
            'url' => TextInput::make($fieldName)->url(),
            'number' => TextInput::make($fieldName)->numeric(),
            'textarea' => Textarea::make($fieldName),
            'rich_editor' => RichEditor::make($fieldName),
            'select' => Select::make($fieldName),
            'checkbox' => Toggle::make($fieldName),
            'radio' => Radio::make($fieldName),
            'checkbox_list' => CheckboxList::make($fieldName),
            'date' => DatePicker::make($fieldName),
            'datetime' => DateTimePicker::make($fieldName),
            'file' => FileUpload::make($fieldName),
            default => null,
        };
    }

    /**
     * Infer component type from field name patterns
     */
    protected function inferComponentFromFieldName(string $fieldName, array $config): ?Component
    {
        // Email fields
        if (str_contains($fieldName, 'email')) {
            return TextInput::make($fieldName)->email();
        }

        // Password fields
        if (str_contains($fieldName, 'password')) {
            return TextInput::make($fieldName)->password();
        }

        // Phone fields
        if (str_contains($fieldName, 'phone') || str_contains($fieldName, 'tel')) {
            return TextInput::make($fieldName)->tel();
        }

        // URL fields
        if (str_contains($fieldName, 'url') || str_contains($fieldName, 'website') || str_contains($fieldName, 'link')) {
            return TextInput::make($fieldName)->url();
        }

        // Boolean/Toggle fields
        if (str_starts_with($fieldName, 'is_') || str_starts_with($fieldName, 'has_') || str_contains($fieldName, 'enabled')) {
            return Toggle::make($fieldName);
        }

        // Select fields (relationships and enums)
        if (str_ends_with($fieldName, '_id') || str_contains($fieldName, 'status') || str_contains($fieldName, 'type') || str_contains($fieldName, 'category')) {
            return Select::make($fieldName);
        }

        // Date fields
        if (str_contains($fieldName, 'date') && ! str_contains($fieldName, 'time')) {
            return DatePicker::make($fieldName);
        }

        // DateTime fields
        if ((str_contains($fieldName, 'date') && str_contains($fieldName, 'time')) || str_ends_with($fieldName, '_at')) {
            return DateTimePicker::make($fieldName);
        }

        // Rich text fields
        if (str_contains($fieldName, 'content') || str_contains($fieldName, 'body')) {
            return RichEditor::make($fieldName);
        }

        // Textarea fields
        if (str_contains($fieldName, 'notes') || str_contains($fieldName, 'description') || str_contains($fieldName, 'comment') || str_contains($fieldName, 'message')) {
            return Textarea::make($fieldName)->rows(3);
        }

        // Numeric fields
        if (str_contains($fieldName, 'amount') || str_contains($fieldName, 'price') || str_contains($fieldName, 'total') || str_contains($fieldName, 'quantity') || str_contains($fieldName, 'count')) {
            $component = TextInput::make($fieldName)->numeric();

            // Add currency prefix for monetary fields
            if (str_contains($fieldName, 'amount') || str_contains($fieldName, 'price') || str_contains($fieldName, 'total')) {
                $component->prefix($config['currency'] ?? '€');
            }

            return $component;
        }

        // File upload fields
        if (str_contains($fieldName, 'file') || str_contains($fieldName, 'attachment') || str_contains($fieldName, 'document')) {
            return FileUpload::make($fieldName);
        }

        // Default to text input
        return TextInput::make($fieldName);
    }

    /**
     * Apply configuration to component
     *
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     *
     * @throws InvalidComponentException
     */
    protected function applyConfiguration(Component $component, string $fieldName, array $config): Component
    {
        // Label
        if (isset($config['label'])) {
            $component->label($config['label']);
        } else {
            // Auto-generate label from field name
            $label = str_replace('_', ' ', $fieldName);
            $label = ucwords($label);
            $component->label($label);
        }

        // Placeholder
        if (isset($config['placeholder'])) {
            $component->placeholder($config['placeholder']);
        }

        // Helper text
        if (isset($config['helper_text'])) {
            $component->helperText($config['helper_text']);
        }

        // Required
        /** @noinspection DuplicatedCode */
        if ($config['required'] ?? false) {
            $component->required();
        }

        // Readonly/Disabled
        if ($config['readonly'] ?? false) {
            if (method_exists($component, 'readonly')) {
                $component->readonly();
            } elseif (method_exists($component, 'disabled')) {
                $component->disabled();
            } else {
                throw new InvalidComponentException($fieldName);
            }
        }

        // Default value
        if (isset($config['default'])) {
            $component->default($config['default']);
        }

        // Validation rules
        if (! empty($config['validation'])) {
            $rules = is_array($config['validation']) ? $config['validation'] : [$config['validation']];
            $component->rules($rules);
        }

        // Hidden
        if ($config['hidden'] ?? false) {
            $component->hidden();
        }

        // Component-specific configurations
        $this->applyComponentSpecificConfig($component, $config);

        return $component;
    }

    /**
     * Apply component-specific configuration
     */
    protected function applyComponentSpecificConfig(Component $component, array $config): void
    {
        // Select options
        if ($component instanceof Select && isset($config['options'])) {
            $component->options($config['options']);
        }

        // Multiple select
        if ($component instanceof Select && ($config['multiple'] ?? false)) {
            $component->multiple();
        }

        // Searchable select
        if ($component instanceof Select && ($config['searchable'] ?? false)) {
            $component->searchable();
        }

        // Textarea rows
        if ($component instanceof Textarea && isset($config['rows'])) {
            $component->rows($config['rows']);
        }

        // Max length
        if (method_exists($component, 'maxLength') && isset($config['max_length'])) {
            $component->maxLength($config['max_length']);
        }

        // Min/Max for numeric fields
        if ($component instanceof TextInput) {
            if (isset($config['min'])) {
                $component->minValue($config['min']);
            }
            if (isset($config['max'])) {
                $component->maxValue($config['max']);
            }
            if (isset($config['step'])) {
                $component->step($config['step']);
            }
        }

        // Date constraints
        if ($component instanceof DateTimePicker) {
            if (isset($config['min_date'])) {
                $component->minDate($config['min_date']);
            }
            if (isset($config['max_date'])) {
                $component->maxDate($config['max_date']);
            }
        }

        // File upload configurations
        if ($component instanceof FileUpload) {
            if (isset($config['accept'])) {
                $component->acceptedFileTypes($config['accept']);
            }
            if (isset($config['max_size'])) {
                $component->maxSize($config['max_size']);
            }
            if ($config['multiple'] ?? false) {
                $component->multiple();
            }
        }

        // Radio/Checkbox list options
        if (($component instanceof Radio || $component instanceof CheckboxList) && isset($config['options'])) {
            $component->options($config['options']);
        }

        // Column span
        if (isset($config['column_span'])) {
            $component->columnSpan($config['column_span']);
        }
    }

    /**
     * Build validation rules array from configuration
     *
     * @noinspection PhpUnused
     */
    public function buildValidationRules(array $fieldsConfig): array
    {
        $rules = [];

        foreach ($fieldsConfig as $fieldName => $config) {
            if (! ($config['visible'] ?? true)) {
                continue;
            }

            $fieldRules = [];

            if ($config['required'] ?? false) {
                $fieldRules[] = 'required';
            }

            if (! empty($config['validation'])) {
                $fieldRules = array_merge($fieldRules, (array) $config['validation']);
            }

            if (! empty($fieldRules)) {
                $rules[$fieldName] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Extract default values from configuration
     *
     * @noinspection PhpUnused
     */
    public function extractDefaults(array $fieldsConfig): array
    {
        $defaults = [];

        foreach ($fieldsConfig as $fieldName => $config) {
            if (isset($config['default'])) {
                $defaults[$fieldName] = $config['default'];
            }
        }

        return $defaults;
    }
}
