<?php

namespace RoBYCoNTe\FilamentFlow\Configuration;

class FieldConfiguration
{
    protected string $name;

    protected ?string $label = null;

    protected ?string $componentType = null;

    protected bool $visible = true;

    protected bool $required = false;

    protected bool $readonly = false;

    protected bool $hidden = false;

    protected mixed $default = null;

    protected array $validation = [];

    protected ?string $placeholder = null;

    protected ?string $helperText = null;

    protected array $options = [];

    protected array $metadata = [];

    protected int $sortOrder = 0;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function componentType(string $type): static
    {
        $this->componentType = $type;

        return $this;
    }

    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function validation(array|string $rules): static
    {
        $this->validation = is_array($rules) ? $rules : [$rules];

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function helperText(string $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function sortOrder(int $order): static
    {
        $this->sortOrder = $order;

        return $this;
    }

    /**
     * Get field name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Convert to array for database storage or form building
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'component_type' => $this->componentType,
            'visible' => $this->visible,
            'required' => $this->required,
            'readonly' => $this->readonly,
            'hidden' => $this->hidden,
            'default' => $this->default,
            'validation' => $this->validation,
            'placeholder' => $this->placeholder,
            'helper_text' => $this->helperText,
            'options' => $this->options,
            'metadata' => $this->metadata,
            'sort_order' => $this->sortOrder,
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): static
    {
        $field = new static($data['name']);

        if (isset($data['label'])) {
            $field->label($data['label']);
        }
        if (isset($data['component_type'])) {
            $field->componentType($data['component_type']);
        }
        if (isset($data['visible'])) {
            $field->visible($data['visible']);
        }
        if (isset($data['required'])) {
            $field->required($data['required']);
        }
        if (isset($data['readonly'])) {
            $field->readonly($data['readonly']);
        }
        if (isset($data['hidden'])) {
            $field->hidden($data['hidden']);
        }
        if (isset($data['default'])) {
            $field->default($data['default']);
        }
        if (isset($data['validation'])) {
            $field->validation($data['validation']);
        }
        if (isset($data['placeholder'])) {
            $field->placeholder($data['placeholder']);
        }
        if (isset($data['helper_text'])) {
            $field->helperText($data['helper_text']);
        }
        if (isset($data['options'])) {
            $field->options($data['options']);
        }
        if (isset($data['metadata'])) {
            $field->metadata($data['metadata']);
        }
        if (isset($data['sort_order'])) {
            $field->sortOrder($data['sort_order']);
        }

        return $field;
    }
}
