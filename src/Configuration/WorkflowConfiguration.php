<?php

/** @noinspection PhpUnused */

namespace RoBYCoNTe\FilamentFlow\Configuration;

class WorkflowConfiguration
{
    protected string $name;

    protected string $modelType;

    protected string $stateColumn = 'state';

    protected bool $isActive = true;

    protected array $states = [];

    protected array $transitions = [];

    protected array $creationPolicy = [];

    protected array $metadata = [];

    public function __construct(string $name, string $modelType)
    {
        $this->name = $name;
        $this->modelType = $modelType;
    }

    public static function make(string $name, string $modelType): static
    {
        return new static($name, $modelType);
    }

    public function stateColumn(string $column): static
    {
        $this->stateColumn = $column;

        return $this;
    }

    public function active(bool $isActive = true): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function states(array $states): static
    {
        $this->states = $states;

        return $this;
    }

    public function addState(StateConfiguration $state): static
    {
        $this->states[] = $state;

        return $this;
    }

    public function transitions(array $transitions): static
    {
        $this->transitions = $transitions;

        return $this;
    }

    public function addTransition(TransitionConfiguration $transition): static
    {
        $this->transitions[] = $transition;

        return $this;
    }

    public function creationPolicy(array $policy): static
    {
        $this->creationPolicy = $policy;

        return $this;
    }

    public function autoAssignCreator(bool $autoAssign = true, string $assignmentType = 'primary'): static
    {
        $this->creationPolicy['auto_assign_creator'] = $autoAssign;
        $this->creationPolicy['assignment_type'] = $assignmentType;

        return $this;
    }

    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Get workflow name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get model type
     */
    public function getModelType(): string
    {
        return $this->modelType;
    }

    /**
     * Convert to array for database storage
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'model_type' => $this->modelType,
            'state_column' => $this->stateColumn,
            'is_active' => $this->isActive,
            'creation_policy' => $this->creationPolicy,
            'metadata' => $this->metadata,
            'states' => array_map(function ($state) {
                return $state instanceof StateConfiguration ? $state->toArray() : $state;
            }, $this->states),
            'transitions' => array_map(function ($transition) {
                return $transition instanceof TransitionConfiguration ? $transition->toArray() : $transition;
            }, $this->transitions),
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): static
    {
        $workflow = new static($data['name'], $data['model_type']);

        if (isset($data['state_column'])) {
            $workflow->stateColumn($data['state_column']);
        }
        if (isset($data['is_active'])) {
            $workflow->active($data['is_active']);
        }
        if (isset($data['creation_policy'])) {
            $workflow->creationPolicy($data['creation_policy']);
        }
        if (isset($data['metadata'])) {
            $workflow->metadata($data['metadata']);
        }
        if (isset($data['states'])) {
            $states = array_map(function ($state) {
                return is_array($state) ? StateConfiguration::fromArray($state) : $state;
            }, $data['states']);
            $workflow->states($states);
        }
        if (isset($data['transitions'])) {
            $transitions = array_map(function ($transition) {
                return is_array($transition) ? TransitionConfiguration::fromArray($transition) : $transition;
            }, $data['transitions']);
            $workflow->transitions($transitions);
        }

        return $workflow;
    }

    /**
     * Get all states
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * Get all transitions
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /**
     * Find state by name
     */
    public function findState(string $name): ?StateConfiguration
    {
        foreach ($this->states as $state) {
            if ($state instanceof StateConfiguration && $state->getName() === $name) {
                return $state;
            }
            if (is_array($state) && ($state['name'] ?? null) === $name) {
                return StateConfiguration::fromArray($state);
            }
        }

        return null;
    }

    /**
     * Find transition by name
     */
    public function findTransition(string $name): ?TransitionConfiguration
    {
        foreach ($this->transitions as $transition) {
            if ($transition instanceof TransitionConfiguration && $transition->getName() === $name) {
                return $transition;
            }
            if (is_array($transition) && ($transition['name'] ?? null) === $name) {
                return TransitionConfiguration::fromArray($transition);
            }
        }

        return null;
    }
}
