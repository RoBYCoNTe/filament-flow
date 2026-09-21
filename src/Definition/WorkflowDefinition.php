<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

/**
 * Fully typed definition of a workflow: states, transitions, per-state field
 * rules, side effects and validation rules. Applied idempotently by
 * WorkflowApplier, which also takes care of revisions.
 */
final class WorkflowDefinition
{
    /** @var list<State> */
    private array $states = [];

    /** @var list<Transition> */
    private array $transitions = [];

    /** @var list<ScheduledCheck> */
    private array $scheduledChecks = [];

    /** @var list<Notification> */
    private array $notifications = [];

    private string $stateColumn = 'state';

    private bool $active = true;

    /** @var array<string,mixed> */
    private array $creationPolicy = [];

    /** @var array<string,mixed> */
    private array $metadata = [];

    private function __construct(
        private readonly string $name,
        private readonly string $modelType,
    ) {}

    public static function make(string $name, string $modelType): self
    {
        return new self($name, $modelType);
    }

    public function stateColumn(string $column): self
    {
        $this->stateColumn = $column;

        return $this;
    }

    public function active(bool $active = true): self
    {
        $this->active = $active;

        return $this;
    }

    /** @param array<string,mixed> $policy */
    public function creationPolicy(array $policy): self
    {
        $this->creationPolicy = $policy;

        return $this;
    }

    public function autoAssignCreator(bool $auto = true, string $assignmentType = 'primary'): self
    {
        $this->creationPolicy['auto_assign_creator'] = $auto;
        $this->creationPolicy['assignment_type'] = $assignmentType;

        return $this;
    }

    /** @param array<string,mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function state(State $state): self
    {
        $this->states[] = $state;

        return $this;
    }

    /** @param list<State> $states */
    public function states(array $states): self
    {
        $this->states = array_values($states);

        return $this;
    }

    public function transition(Transition $transition): self
    {
        $this->transitions[] = $transition;

        return $this;
    }

    /** @param list<Transition> $transitions */
    public function transitions(array $transitions): self
    {
        $this->transitions = array_values($transitions);

        return $this;
    }

    public function scheduledCheck(ScheduledCheck $check): self
    {
        $this->scheduledChecks[] = $check;

        return $this;
    }

    /** @param list<ScheduledCheck> $checks */
    public function scheduledChecks(array $checks): self
    {
        $this->scheduledChecks = array_values($checks);

        return $this;
    }

    public function notification(Notification $notification): self
    {
        $this->notifications[] = $notification;

        return $this;
    }

    /** @param list<Notification> $notifications */
    public function notifications(array $notifications): self
    {
        $this->notifications = array_values($notifications);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function getStateColumn(): string
    {
        return $this->stateColumn;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** @return array<string,mixed> */
    public function getCreationPolicy(): array
    {
        return $this->creationPolicy;
    }

    /** @return array<string,mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @return list<State> */
    public function getStates(): array
    {
        return $this->states;
    }

    /** @return list<Transition> */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /** @return list<ScheduledCheck> */
    public function getScheduledChecks(): array
    {
        return $this->scheduledChecks;
    }

    /** @return list<Notification> */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    public function stateByName(string $name): ?State
    {
        foreach ($this->states as $state) {
            if ($state->name() === $name) {
                return $state;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'model_type' => $this->modelType,
            'state_column' => $this->stateColumn,
            'is_active' => $this->active,
            'creation_policy' => $this->creationPolicy,
            'metadata' => $this->metadata,
            'states' => array_map(static fn (State $s): array => $s->toArray(), $this->states),
            'transitions' => array_map(static fn (Transition $t): array => $t->toArray(), $this->transitions),
            'scheduled_checks' => array_map(static fn (ScheduledCheck $c): array => $c->toArray(), $this->scheduledChecks),
            'notifications' => array_map(static fn (Notification $n): array => $n->toArray(), $this->notifications),
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $definition = self::make((string) $data['name'], (string) $data['model_type'])
            ->stateColumn((string) ($data['state_column'] ?? 'state'))
            ->active((bool) ($data['is_active'] ?? true))
            ->creationPolicy($data['creation_policy'] ?? [])
            ->metadata($data['metadata'] ?? []);

        foreach ($data['states'] ?? [] as $state) {
            $built = State::make((string) $state['name'], (string) ($state['label'] ?? $state['name']))
                ->color((string) ($state['color'] ?? 'gray'))
                ->icon($state['icon'] ?? null)
                ->description($state['description'] ?? null)
                ->initial((bool) ($state['is_initial'] ?? false))
                ->final((bool) ($state['is_final'] ?? false))
                ->sort((int) ($state['sort_order'] ?? 0))
                ->metadata($state['metadata'] ?? []);

            foreach ($state['fields'] ?? [] as $field) {
                $built->field(StateField::fromArray($field));
            }

            foreach ($state['access_rules'] ?? [] as $rule) {
                $built->accessRule(AccessRule::fromArray($rule));
            }

            $built->notifications(array_map(
                static fn (array $notification): Notification => Notification::fromArray($notification),
                $state['notifications'] ?? [],
            ));

            $definition->state($built);
        }

        foreach ($data['transitions'] ?? [] as $transition) {
            $built = Transition::make(
                (string) $transition['name'],
                $transition['from'] ?? null,
                $transition['to'] ?? null,
            )
                ->label((string) ($transition['label'] ?? $transition['name']))
                ->description($transition['description'] ?? null)
                ->confirm((bool) ($transition['requires_confirmation'] ?? false))
                ->requiresReason((bool) ($transition['requires_reason'] ?? false))
                ->conditions($transition['conditions'] ?? [])
                ->metadata($transition['metadata'] ?? []);

            foreach ($transition['side_effects'] ?? [] as $effect) {
                $built->sideEffect(SideEffect::fromArray($effect));
            }

            foreach ($transition['validation_rules'] ?? [] as $rule) {
                $built->validationRule(ValidationRule::fromArray($rule));
            }

            $built->notifications(array_map(
                static fn (array $notification): Notification => Notification::fromArray($notification),
                $transition['notifications'] ?? [],
            ));

            $definition->transition($built);
        }

        foreach ($data['scheduled_checks'] ?? [] as $check) {
            $definition->scheduledCheck(ScheduledCheck::fromArray($check));
        }

        foreach ($data['notifications'] ?? [] as $notification) {
            $definition->notification(Notification::fromArray($notification));
        }

        return $definition;
    }
}
