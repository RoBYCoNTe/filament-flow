<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\WorkflowOwnerChange;

/**
 * The handovers of a record, read once and in one shape: the owner column, the entry a host
 * puts in a form, and the panel of the assignments all read the same history through here.
 */
final class OwnershipHistory
{
    /**
     * The handovers of a record, most recent first.
     *
     * @return list<array{
     *     from:?string, from_initials:?string,
     *     to:?string, to_initials:?string,
     *     by:?string, at:?string,
     *     retention:string, retention_label:string, retained:bool,
     *     note:?string
     * }>
     */
    public static function for(Model $record, ?int $limit = null): array
    {
        $query = WorkflowOwnerChange::query()
            ->forRecord($record)
            ->with(['fromUser', 'toUser', 'changedBy'])
            ->orderByDesc('changed_at')
            ->orderByDesc('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query
            ->get()
            ->map(fn (WorkflowOwnerChange $change): array => self::describe($change))
            ->values()
            ->all();
    }

    /**
     * One handover, in the shape everything reads.
     *
     * @return array{
     *     from:?string, from_initials:?string,
     *     to:?string, to_initials:?string,
     *     by:?string, at:?string,
     *     retention:string, retention_label:string, retained:bool,
     *     note:?string
     * }
     */
    public static function describe(WorkflowOwnerChange $change): array
    {
        $retention = (string) $change->retention;
        $from = $change->fromUser?->getAttribute('name');
        $to = $change->toUser?->getAttribute('name');

        return [
            'from' => $from,
            'from_initials' => is_string($from) ? UserSummary::initials($from) : null,
            'to' => $to,
            'to_initials' => is_string($to) ? UserSummary::initials($to) : null,
            'by' => $change->changedBy?->getAttribute('name'),
            'at' => $change->changed_at?->isoFormat('D MMM YYYY HH:mm'),
            'retention' => $retention,
            'retention_label' => self::retentionLabel($retention),
            'retained' => $retention !== 'none',
            'note' => $change->note,
        ];
    }

    /** The words of the package for what a previous holder kept. */
    public static function retentionLabel(string $retention): string
    {
        return match ($retention) {
            'viewer' => __('filament-flow::messages.retention_viewer'),
            'secondary' => __('filament-flow::messages.retention_secondary'),
            default => __('filament-flow::messages.retention_none'),
        };
    }
}
