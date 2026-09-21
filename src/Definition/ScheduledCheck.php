<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\ScheduledCheckAction;
use RoBYCoNTe\FilamentFlow\Definition\Enums\ScheduledCheckCondition;
use RoBYCoNTe\FilamentFlow\Definition\Enums\ScheduledCheckFrequency;

/**
 * A typed scheduled check: when (state + condition), how often, and what to do
 * (notify / transition / run a transition's side effects). Persisted as a
 * `workflow_scheduled_checks` row; references are kept by name so a definition
 * stays stable across revisions.
 */
final class ScheduledCheck
{
    private ?string $description = null;

    private ?string $state = null;

    private ScheduledCheckCondition $conditionType = ScheduledCheckCondition::FieldCompare;

    /** @var array<string,mixed> */
    private array $conditionConfig = [];

    private ScheduledCheckAction $actionType = ScheduledCheckAction::Notification;

    /** @var array<string,mixed> */
    private array $actionConfig = [];

    private ScheduledCheckFrequency $frequency = ScheduledCheckFrequency::Daily;

    private bool $oncePerRecord = false;

    private bool $active = true;

    private function __construct(
        private readonly string $name,
    ) {}

    public static function make(string $name, ?string $label = null): self
    {
        return (new self($name))->description($label);
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return (new self((string) ($data['name'] ?? '')))
            ->description($data['description'] ?? null)
            ->state($data['state'] ?? null)
            ->frequency(ScheduledCheckFrequency::from((string) ($data['frequency'] ?? ScheduledCheckFrequency::Daily->value)))
            ->oncePerRecord((bool) ($data['once_per_record'] ?? false))
            ->active((bool) ($data['is_active'] ?? true))
            ->condition(
                ScheduledCheckCondition::from((string) ($data['condition_type'] ?? ScheduledCheckCondition::FieldCompare->value)),
                $data['condition_config'] ?? [],
            )
            ->action(
                ScheduledCheckAction::from((string) ($data['action_type'] ?? ScheduledCheckAction::Notification->value)),
                $data['action_config'] ?? [],
            );
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function state(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    /** @param array<string,mixed> $config */
    public function condition(ScheduledCheckCondition $type, array $config): self
    {
        $this->conditionType = $type;
        $this->conditionConfig = $config;

        return $this;
    }

    /** @param array<string,mixed> $config */
    public function action(ScheduledCheckAction $type, array $config): self
    {
        $this->actionType = $type;
        $this->actionConfig = $config;

        return $this;
    }

    public function frequency(ScheduledCheckFrequency $frequency): self
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->frequency(ScheduledCheckFrequency::EveryMinute);
    }

    public function everyFiveMinutes(): self
    {
        return $this->frequency(ScheduledCheckFrequency::EveryFiveMinutes);
    }

    public function hourly(): self
    {
        return $this->frequency(ScheduledCheckFrequency::Hourly);
    }

    public function daily(): self
    {
        return $this->frequency(ScheduledCheckFrequency::Daily);
    }

    public function weekly(): self
    {
        return $this->frequency(ScheduledCheckFrequency::Weekly);
    }

    public function oncePerRecord(bool $once = true): self
    {
        $this->oncePerRecord = $once;

        return $this;
    }

    public function active(bool $active = true): self
    {
        $this->active = $active;

        return $this;
    }

    /** Trigger when `field` (+ offset days) satisfies the operator against now. */
    public function whenDateOffset(string $field, int $offsetDays = 0, string $operator = '<='): self
    {
        return $this->condition(ScheduledCheckCondition::DateOffset, [
            'field' => $field,
            'offset_days' => $offsetDays,
            'operator' => $operator,
        ]);
    }

    /** @param list<array<string,mixed>> $conditions */
    public function whenFieldCompare(array $conditions): self
    {
        return $this->condition(ScheduledCheckCondition::FieldCompare, ['conditions' => array_values($conditions)]);
    }

    public function whenCustomClass(string $class): self
    {
        return $this->condition(ScheduledCheckCondition::CustomClass, ['class' => $class]);
    }

    public function thenNotification(int $notificationId): self
    {
        return $this->action(ScheduledCheckAction::Notification, ['notification_id' => $notificationId]);
    }

    /** Reference a notification of the same definition by name (survives id changes). */
    public function thenNotificationNamed(string $notificationName): self
    {
        return $this->action(ScheduledCheckAction::Notification, ['notification_name' => $notificationName]);
    }

    public function thenTransition(string $toState, bool $force = true): self
    {
        return $this->action(ScheduledCheckAction::Transition, ['to_state' => $toState, 'force' => $force]);
    }

    /** Run the side effects configured on another transition of the same workflow. */
    public function thenSideEffect(string $transitionName): self
    {
        return $this->action(ScheduledCheckAction::SideEffect, ['transition_name' => $transitionName]);
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'state' => $this->state,
            'condition_type' => $this->conditionType->value,
            'condition_config' => $this->conditionConfig,
            'action_type' => $this->actionType->value,
            'action_config' => $this->actionConfig,
            'frequency' => $this->frequency->value,
            'once_per_record' => $this->oncePerRecord,
            'is_active' => $this->active,
        ];
    }
}
