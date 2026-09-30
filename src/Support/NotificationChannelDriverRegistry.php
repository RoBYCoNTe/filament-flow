<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Contracts\Container\BindingResolutionException;
use RoBYCoNTe\FilamentFlow\Contracts\NotificationChannelDriver;

/**
 * The channels the host teaches the engine: a name (`pec`, an external service) and the
 * driver that delivers on it. The engine asks here when a notification declares a channel
 * it does not deliver itself, and answers every name the host registered — one lookup, one
 * driver built through the container, the same for every notification.
 */
final class NotificationChannelDriverRegistry
{
    /** @var array<string, NotificationChannelDriver|class-string<NotificationChannelDriver>> */
    private array $drivers = [];

    public function register(string $channelType, NotificationChannelDriver|string $driver): void
    {
        $this->drivers[$channelType] = $driver;
    }

    public function has(string $channelType): bool
    {
        return array_key_exists($channelType, $this->drivers);
    }

    /**
     * The driver of a channel, built through the container.
     *
     * @throws BindingResolutionException
     */
    public function get(string $channelType): NotificationChannelDriver
    {
        $driver = $this->drivers[$channelType];

        if ($driver instanceof NotificationChannelDriver) {
            return $driver;
        }

        return app($driver);
    }

    /** @return array<string, NotificationChannelDriver|class-string<NotificationChannelDriver>> */
    public function all(): array
    {
        return $this->drivers;
    }

    public function forget(string $channelType): void
    {
        unset($this->drivers[$channelType]);
    }
}
