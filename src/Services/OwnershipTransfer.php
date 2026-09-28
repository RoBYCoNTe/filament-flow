<?php

namespace RoBYCoNTe\FilamentFlow\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Events\WorkflowOwnerChanged;
use RoBYCoNTe\FilamentFlow\Models\WorkflowOwnerChange;
use RoBYCoNTe\FilamentFlow\Support\RecordOwner;

/**
 * Handing a record over: the person who holds it changes, what the previous holder keeps is
 * written on a row of their own, and the handover is recorded where the columns can read it.
 *
 * The domain lives here — the panel of the assignments, a console command, a job or an import
 * all hand a record over the same way, and none of them has to know how it is done.
 */
final class OwnershipTransfer
{
    /** The previous holder keeps nothing: their access ends with the handover. */
    public const RETENTION_NONE = 'none';

    /** The previous holder keeps seeing the record: a `viewer` with the view override granted. */
    public const RETENTION_VIEWER = 'viewer';

    /** The previous holder stays on the work: a `secondary` assignment, overrides untouched. */
    public const RETENTION_SECONDARY = 'secondary';

    /** @var list<string> */
    public const RETENTIONS = [self::RETENTION_NONE, self::RETENTION_VIEWER, self::RETENTION_SECONDARY];

    /**
     * Give the record to another person.
     *
     * @param  string  $retention  what the previous holder keeps (see the RETENTION_* constants)
     * @param  string|null  $note  why the record changed hands, kept beside the change
     * @param  Model|null  $actor  who made the handover
     */
    public function transfer(
        Model $record,
        int $toUserId,
        string $retention = self::RETENTION_NONE,
        ?string $note = null,
        ?Model $actor = null,
    ): WorkflowOwnerChange {
        $field = RecordOwner::field();
        $from = RecordOwner::id($record);
        $fromUserId = $from === null ? null : (int) $from;

        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException('The record already belongs to that person.');
        }

        if (! in_array($retention, self::RETENTIONS, true)) {
            throw new InvalidArgumentException("Unknown retention [{$retention}].");
        }

        return DB::transaction(function () use ($record, $field, $fromUserId, $toUserId, $retention, $note, $actor): WorkflowOwnerChange {
            // The owner column may sit outside the fillable of the model: handing the record
            // over is an administrative act, and the save still goes through the events.
            $record->forceFill([$field => $toUserId])->save();

            if ($fromUserId !== null) {
                $this->keepSomethingFor($record, $fromUserId, $retention, $note, $actor);
            }

            // The handover is written down where the columns can read it back: the owner column
            // says, beside the name, that the record changed hands — and when.
            $change = WorkflowOwnerChange::create([
                'changeable_type' => $record->getMorphClass(),
                'changeable_id' => $record->getKey(),
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'changed_by' => $actor?->getKey(),
                'owner_field' => $field,
                'retention' => $retention,
                'note' => $note,
                'changed_at' => now(),
            ]);

            WorkflowOwnerChanged::dispatch($record, $fromUserId, $toUserId, $retention, $actor, $note);

            return $change;
        });
    }

    /** What the previous holder keeps, written on the row that carries it. */
    private function keepSomethingFor(Model $record, int $fromUserId, string $retention, ?string $note, ?Model $actor): void
    {
        if (! method_exists($record, 'assignWithOverrides')) {
            return;
        }

        $metadata = array_filter([
            'owner_transfer' => true,
            'transfer_note' => $note,
            'previous_owner' => $fromUserId,
        ]);

        match ($retention) {
            self::RETENTION_VIEWER => $record->assignWithOverrides($fromUserId, ['view' => true], 'viewer', $actor, $metadata),
            self::RETENTION_SECONDARY => $record->assignWithOverrides($fromUserId, [], 'secondary', $actor, $metadata),
            default => null,
        };
    }
}
