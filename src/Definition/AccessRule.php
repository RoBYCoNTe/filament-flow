<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\AccessOperator;
use RoBYCoNTe\FilamentFlow\Definition\Enums\AccessType;

/**
 * A single typed access rule attached to a state. Each rule is one token
 * (`*`, `@owner`, `role:admin`, `permission:approve`, …) plus the operator
 * used to combine it with the other rules of the same access type.
 */
final class AccessRule
{
    public const ANY = '*';

    public const AUTHENTICATED = '@authenticated';

    public const ASSIGNED = '@assigned';

    public const OWNER = '@owner';

    private AccessOperator $operator = AccessOperator::Or;

    private bool $active = true;

    private int $priority = 0;

    /** @var array<string,mixed> */
    private array $metadata = [];

    private function __construct(
        private readonly AccessType $accessType,
        private readonly string $rule,
    ) {}

    public static function make(AccessType $accessType, string $rule = self::ANY): self
    {
        return new self($accessType, $rule);
    }

    public static function view(string $rule = self::ANY): self
    {
        return new self(AccessType::View, $rule);
    }

    public static function edit(string $rule = self::ANY): self
    {
        return new self(AccessType::Edit, $rule);
    }

    public static function transition(string $rule = self::ANY): self
    {
        return new self(AccessType::Transition, $rule);
    }

    public static function create(string $rule = self::ANY): self
    {
        return new self(AccessType::Create, $rule);
    }

    /** Token helper: `role:admin`. */
    public static function role(string $role): string
    {
        return 'role:'.$role;
    }

    /** Token helper: `permission:approve`. */
    public static function permission(string $permission): string
    {
        return 'permission:'.$permission;
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return (new self(
            AccessType::from((string) ($data['access_type'] ?? AccessType::View->value)),
            (string) ($data['rule'] ?? self::ANY),
        ))
            ->operator(AccessOperator::from((string) ($data['operator'] ?? AccessOperator::Or->value)))
            ->priority((int) ($data['priority'] ?? 0))
            ->active((bool) ($data['is_active'] ?? true))
            ->metadata($data['metadata'] ?? []);
    }

    public function operator(AccessOperator $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    public function and(): self
    {
        return $this->operator(AccessOperator::And);
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function active(bool $active = true): self
    {
        $this->active = $active;

        return $this;
    }

    /** @param array<string,mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function accessType(): AccessType
    {
        return $this->accessType;
    }

    public function rule(): string
    {
        return $this->rule;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** Stable identity of a rule inside a state (used by the planner and the applier). */
    public function key(): string
    {
        return $this->accessType->value.'|'.$this->rule.'|'.$this->operator->value;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'access_type' => $this->accessType->value,
            'rule' => $this->rule,
            'operator' => $this->operator->value,
            'priority' => $this->priority,
            'is_active' => $this->active,
            'metadata' => $this->metadata,
        ];
    }
}
