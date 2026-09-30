<?php

namespace RoBYCoNTe\FilamentFlow\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * How a custom notification channel delivers: the host teaches the engine a channel the
 * package does not know (a PEC, an external messaging service) and the engine hands every
 * prepared notification over, rendered, with its recipients and its channel configuration.
 *
 * The channel is declared where every other channel is — on the notification of a
 * definition, or in the database — and the driver registered for its name does the rest:
 * timing, recipients and the delivery log stay the engine's business.
 */
interface NotificationChannelDriver
{
    /**
     * Deliver one prepared notification on this channel.
     *
     * The template has already been rendered (subject, title, body, action): a driver
     * speaks transport, not placeholders. A failure throws — the engine writes it down
     * in the delivery log and goes on with the channels that are left.
     *
     * @param  Collection<int, Model>  $recipients  The users the notification is for
     * @param  array<string, mixed>  $notificationData  `channel`, `channel_config`, `rendered`, `template`, `record_type`, `record_id`, `context`, `priority`
     */
    public function send(Model $record, Collection $recipients, array $notificationData): void;
}
