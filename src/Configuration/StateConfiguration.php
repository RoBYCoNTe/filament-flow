<?php

namespace RoBYCoNTe\FilamentFlow\Configuration;

class StateConfiguration
{
    protected string $name;

    protected ?string $className = null;

    protected ?string $label = null;

    protected ?string $color = null;

    protected ?string $icon = null;

    protected bool $isInitial = false;

    protected bool $isFinal = false;

    protected array $allowedTransitions = [];

    protected array $fieldPermissions = [];

    protected array $visibilityRules = [];

    protected array $metadata = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function className(string $className): static
    {
        $this->className = $className;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function initial(bool $isInitial = true): static
    {
        $this->isInitial = $isInitial;

        return $this;
    }

    public function final(bool $isFinal = true): static
    {
        $this->isFinal = $isFinal;

        return $this;
    }

    public function allowTransitionsTo(array $states): static
    {
        $this->allowedTransitions = $states;

        return $this;
    }

    public function fieldPermissions(array $permissions): static
    {
        $this->fieldPermissions = $permissions;

        return $this;
    }

    public function visibilityRules(array $rules): static
    {
        $this->visibilityRules = $rules;

        return $this;
    }

    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Get state name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get class name
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class_name' => $this->className,
            'label' => $this->label,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_initial' => $this->isInitial,
            'is_final' => $this->isFinal,
            'allowed_transitions' => $this->allowedTransitions,
            'field_permissions' => $this->fieldPermissions,
            'visibility_rules' => $this->visibilityRules,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): static
    {
        $state = new static($data['name']);

        if (isset($data['class_name'])) {
            $state->className($data['class_name']);
        }
        if (isset($data['label'])) {
            $state->label($data['label']);
        }
        if (isset($data['color'])) {
            $state->color($data['color']);
        }
        if (isset($data['icon'])) {
            $state->icon($data['icon']);
        }
        if (isset($data['is_initial'])) {
            $state->initial($data['is_initial']);
        }
        if (isset($data['is_final'])) {
            $state->final($data['is_final']);
        }
        if (isset($data['allowed_transitions'])) {
            $state->allowTransitionsTo($data['allowed_transitions']);
        }
        if (isset($data['field_permissions'])) {
            $state->fieldPermissions($data['field_permissions']);
        }
        if (isset($data['visibility_rules'])) {
            $state->visibilityRules($data['visibility_rules']);
        }
        if (isset($data['metadata'])) {
            $state->metadata($data['metadata']);
        }

        return $state;
    }
}
