<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Planning;

/**
 * Explicit opt-ins for a plan/apply run. Nothing breaking happens without them.
 */
final class PlanOptions
{
    private bool $force = false;

    private bool $migrate = false;

    private ?int $userId = null;

    public static function make(): self
    {
        return new self;
    }

    public function force(bool $force = true): self
    {
        $this->force = $force;

        return $this;
    }

    public function migrate(bool $migrate = true): self
    {
        $this->migrate = $migrate;

        return $this;
    }

    public function userId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function isForced(): bool
    {
        return $this->force;
    }

    public function shouldMigrate(): bool
    {
        return $this->migrate;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }
}
