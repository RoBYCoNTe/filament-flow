<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\RecipientType;

/**
 * A typed notification recipient. The `recipient_config` shape follows
 * RecipientResolver, so the DSL and the runtime agree on the keys.
 */
final class Recipient
{
    /** @param array<string,mixed> $config */
    private function __construct(
        private readonly RecipientType $type,
        private readonly array $config,
    ) {}

    /** @param array<string,mixed> $config */
    public static function make(RecipientType $type, array $config = []): self
    {
        return new self($type, $config);
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            RecipientType::from((string) ($data['recipient_type'] ?? RecipientType::Role->value)),
            $data['recipient_config'] ?? [],
        );
    }

    public static function role(string ...$roles): self
    {
        return new self(RecipientType::Role, ['roles' => array_values($roles)]);
    }

    /** @param int|list<int> $users */
    public static function user(int|array $users): self
    {
        return new self(RecipientType::User, ['user_ids' => array_values((array) $users)]);
    }

    public static function triggerUser(): self
    {
        return new self(RecipientType::TriggerUser, []);
    }

    /** @param list<string> $types */
    public static function assignedUsers(array $types = []): self
    {
        return new self(RecipientType::AssignedUsers, $types === [] ? [] : ['types' => array_values($types)]);
    }

    public static function recordOwner(?string $ownerField = null): self
    {
        return new self(RecipientType::RecordOwner, $ownerField === null ? [] : ['owner_field' => $ownerField]);
    }

    /** @param list<string> $states */
    public static function stateActors(array $states = []): self
    {
        return new self(RecipientType::StateActors, $states === [] ? [] : ['states' => array_values($states)]);
    }

    public static function allInvolved(): self
    {
        return new self(RecipientType::AllInvolved, []);
    }

    public static function involvementType(string $involvementType): self
    {
        return new self(RecipientType::InvolvementType, ['involvement_type' => $involvementType]);
    }

    public static function customField(string $field): self
    {
        return new self(RecipientType::CustomField, ['field' => $field]);
    }

    /** @param array<string,mixed> $bindings */
    public static function customQuery(string $query, array $bindings = []): self
    {
        return new self(RecipientType::CustomQuery, ['query' => $query, 'bindings' => $bindings]);
    }

    public static function customClass(string $class): self
    {
        return new self(RecipientType::CustomClass, ['class' => $class]);
    }

    public function type(): RecipientType
    {
        return $this->type;
    }

    /** @return array<string,mixed> */
    public function config(): array
    {
        return $this->config;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'recipient_type' => $this->type->value,
            'recipient_config' => $this->config,
        ];
    }
}
