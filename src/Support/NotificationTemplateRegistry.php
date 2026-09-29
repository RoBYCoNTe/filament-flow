<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use RoBYCoNTe\FilamentFlow\Contracts\NotificationTemplateProvider;
use RuntimeException;

/**
 * The registry of the host that knows how to fill the expressions of a notification template
 * (`field("...")`, `currency(...)`, the URL of the record). The engine asks for it when it
 * renders a template and stays as it is when the host registered none.
 */
final class NotificationTemplateRegistry
{
    private ?NotificationTemplateProvider $provider = null;

    public function register(NotificationTemplateProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function has(): bool
    {
        return $this->provider !== null;
    }

    /**
     * @throws RuntimeException
     */
    public function get(): NotificationTemplateProvider
    {
        if ($this->provider === null) {
            throw new RuntimeException('No NotificationTemplateProvider has been registered.');
        }

        return $this->provider;
    }

    public function clear(): void
    {
        $this->provider = null;
    }
}
