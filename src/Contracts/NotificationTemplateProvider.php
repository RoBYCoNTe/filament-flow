<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * What a host implements to fill the `{{ ... }}` expressions the engine does not know: the
 * `field("...")`, the `currency(...)`, the URL of the record — the vocabulary of the host,
 * evaluated against the record a notification is about.
 *
 * The engine renders its own placeholders (`{{ app_url }}`, `{{ record_id }}`,
 * `{{ to_state_label }}`) and leaves the rest to the host, so a template may mix the two without
 * either side having to know the other.
 */
interface NotificationTemplateProvider
{
    /**
     * Fill the expressions of a rendered template against the record.
     *
     * An expression the host cannot resolve is left out, never thrown: a notification that
     * carries one broken placeholder is worth more than a notification that fails to leave.
     */
    public function interpolate(string $template, Model $record): string;
}
