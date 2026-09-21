<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationTrigger;

final class State
{
    private ?string $label = null;

    private string $color = 'gray';

    private ?string $icon = null;

    private ?string $description = null;

    private bool $initial = false;

    private bool $final = false;

    private int $sort = 0;

    /** @var array<string,mixed> */
    private array $metadata = [];

    /** @var list<StateField> */
    private array $fields = [];

    /** @var list<AccessRule> */
    private array $accessRules = [];

    /** @var list<Notification> */
    private array $notifications = [];

    private function __construct(private readonly string $name) {}

    public static function make(string $name, ?string $label = null): self
    {
        return (new self($name))->label($label ?? $name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function initial(bool $initial = true): self
    {
        $this->initial = $initial;

        return $this;
    }

    public function final(bool $final = true): self
    {
        $this->final = $final;

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    /** @param array<string,mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /** @param list<StateField> $fields */
    public function fields(array $fields): self
    {
        $this->fields = array_values($fields);

        return $this;
    }

    public function field(StateField $field): self
    {
        $this->fields[] = $field;

        return $this;
    }

    public function accessRule(AccessRule $rule): self
    {
        $this->accessRules[] = $rule;

        return $this;
    }

    /** @param list<AccessRule> $rules */
    public function accessRules(array $rules): self
    {
        $this->accessRules = array_values($rules);

        return $this;
    }

    /** Notification sent when the record enters this state. */
    public function notification(Notification $notification): self
    {
        $notification->trigger(NotificationTrigger::OnStateEnter);

        $this->notifications[] = $notification;

        return $this;
    }

    /** Notification sent when the record leaves this state. */
    public function onExitNotification(Notification $notification): self
    {
        $notification->trigger(NotificationTrigger::OnStateExit);

        $this->notifications[] = $notification;

        return $this;
    }

    /** @param list<Notification> $notifications */
    public function notifications(array $notifications): self
    {
        $this->notifications = array_values($notifications);

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isInitial(): bool
    {
        return $this->initial;
    }

    /** @return list<StateField> */
    public function stateFields(): array
    {
        return $this->fields;
    }

    /** @return list<AccessRule> */
    public function stateAccessRules(): array
    {
        return $this->accessRules;
    }

    /** @return list<Notification> */
    public function stateNotifications(): array
    {
        return $this->notifications;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label ?? $this->name,
            'color' => $this->color,
            'icon' => $this->icon,
            'description' => $this->description,
            'sort_order' => $this->sort,
            'is_initial' => $this->initial,
            'is_final' => $this->final,
            'metadata' => $this->metadata,
            'fields' => array_map(static fn (StateField $f): array => $f->toArray(), $this->fields),
            'access_rules' => array_map(static fn (AccessRule $r): array => $r->toArray(), $this->accessRules),
            'notifications' => array_map(static fn (Notification $n): array => $n->toArray(), $this->notifications),
        ];
    }
}
