<?php

namespace RoBYCoNTe\FilamentFlow\Configuration;

class TransitionConfiguration
{
    protected string $name;

    protected string $fromState;

    protected string $toState;

    protected ?string $label = null;

    protected ?string $color = null;

    protected ?string $icon = null;

    protected bool $requiresConfirmation = false;

    protected ?string $confirmationMessage = null;

    protected array $permissions = [];

    protected array $fields = [];

    protected array $conditions = [];

    protected array $actions = [];

    protected array $notifications = [];

    protected array $metadata = [];

    public function __construct(string $name, string $fromState, string $toState)
    {
        $this->name = $name;
        $this->fromState = $fromState;
        $this->toState = $toState;
    }

    public static function make(string $name, string $fromState, string $toState): static
    {
        return new static($name, $fromState, $toState);
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

    public function requiresConfirmation(bool $requires = true, ?string $message = null): static
    {
        $this->requiresConfirmation = $requires;
        if ($message) {
            $this->confirmationMessage = $message;
        }

        return $this;
    }

    public function permissions(array $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /** @noinspection PhpUnused */
    public function addField(FieldConfiguration $field): static
    {
        $this->fields[] = $field;

        return $this;
    }

    public function conditions(array $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function notifications(array $notifications): static
    {
        $this->notifications = $notifications;

        return $this;
    }

    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Get transition name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get from state
     *
     * @noinspection PhpUnused
     */
    public function getFromState(): string
    {
        return $this->fromState;
    }

    /**
     * Get to state
     *
     * @noinspection PhpUnused
     */
    public function getToState(): string
    {
        return $this->toState;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'label' => $this->label,
            'color' => $this->color,
            'icon' => $this->icon,
            'requires_confirmation' => $this->requiresConfirmation,
            'confirmation_message' => $this->confirmationMessage,
            'permissions' => $this->permissions,
            'fields' => array_map(function ($field) {
                return $field instanceof FieldConfiguration ? $field->toArray() : $field;
            }, $this->fields),
            'conditions' => $this->conditions,
            'actions' => $this->actions,
            'notifications' => $this->notifications,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): static
    {
        $transition = new static($data['name'], $data['from_state'], $data['to_state']);

        if (isset($data['label'])) {
            $transition->label($data['label']);
        }
        if (isset($data['color'])) {
            $transition->color($data['color']);
        }
        if (isset($data['icon'])) {
            $transition->icon($data['icon']);
        }
        if (isset($data['requires_confirmation'])) {
            $transition->requiresConfirmation(
                $data['requires_confirmation'],
                $data['confirmation_message'] ?? null
            );
        }
        if (isset($data['permissions'])) {
            $transition->permissions($data['permissions']);
        }
        if (isset($data['fields'])) {
            $fields = array_map(function ($field) {
                return is_array($field) ? FieldConfiguration::fromArray($field) : $field;
            }, $data['fields']);
            $transition->fields($fields);
        }
        if (isset($data['conditions'])) {
            $transition->conditions($data['conditions']);
        }
        if (isset($data['actions'])) {
            $transition->actions($data['actions']);
        }
        if (isset($data['notifications'])) {
            $transition->notifications($data['notifications']);
        }
        if (isset($data['metadata'])) {
            $transition->metadata($data['metadata']);
        }

        return $transition;
    }
}
