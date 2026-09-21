<?php

namespace RoBYCoNTe\FilamentFlow\Definition\Planning;

use Illuminate\Support\Collection;
use RoBYCoNTe\FilamentFlow\Definition\Notification;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Support\CanonicalJson;

/**
 * Turning a stored row into the shape a difference is computed on.
 *
 * A row has database columns and cast attributes; a difference needs plain, comparable
 * values. The projection is written once per kind of row — state, access rule, check,
 * notification — and the normalisation it relies on lives here too.
 */
trait ProjectsWorkflowRows
{
    /** @return array<string,mixed> */
    private function projectAccessRule(WorkflowStateAccessRule $row): array
    {
        return [
            'access_type' => $row->access_type,
            'rule' => $row->rule,
            'operator' => $row->operator,
            'priority' => (int) $row->priority,
            'is_active' => (bool) $row->is_active,
            'metadata' => $row->metadata ?? [],
        ];
    }

    /**
     * @param  Collection<int,WorkflowState>  $statesById
     * @return array<string,mixed>
     */
    private function projectScheduledCheck(WorkflowScheduledCheck $row, $statesById): array
    {
        return [
            'name' => $row->name,
            'description' => $row->description,
            'state' => $row->state_id !== null ? $statesById->get($row->state_id)?->name : null,
            'condition_type' => $row->condition_type,
            'condition_config' => $row->condition_config ?? [],
            'action_type' => $row->action_type,
            'action_config' => $row->action_config ?? [],
            'frequency' => $row->frequency,
            'once_per_record' => (bool) $row->once_per_record,
            'is_active' => (bool) $row->is_active,
        ];
    }

    /** @return array<string,mixed> */
    private function projectNotification(WorkflowNotification $row): array
    {
        $template = $row->templates()->orderBy('id')->first();

        return [
            'name' => $row->name,
            'description' => $row->description,
            'trigger_event' => $row->trigger_event,
            'is_active' => (bool) $row->is_active,
            'timing' => $row->timing,
            'delay_minutes' => $row->delay_minutes !== null ? (int) $row->delay_minutes : null,
            'priority' => $row->priority,
            'metadata' => $row->metadata ?? [],
            'recipients' => $row->recipients()->orderBy('sort_order')->get()
                ->map(fn (WorkflowNotificationRecipient $r): array => [
                    'recipient_type' => $r->recipient_type,
                    'recipient_config' => $r->recipient_config ?? [],
                    'sort_order' => (int) $r->sort_order,
                ])->values()->all(),
            'channels' => $row->channels()->orderBy('id')->get()
                ->map(fn (WorkflowNotificationChannel $c): array => [
                    'channel_type' => $c->channel_type,
                    'channel_config' => $c->channel_config ?? [],
                    'is_active' => (bool) $c->is_active,
                ])->values()->all(),
            'template' => $template === null ? null : [
                'subject' => $template->subject,
                'title' => $template->title,
                'body' => $template->body,
                'action_text' => $template->action_text,
                'action_url' => $template->action_url,
                'template_engine' => $template->template_engine,
                'format' => $template->format,
                'variables' => $template->variables ?? [],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  list<string>  $drop
     * @return array<string,mixed>
     */
    private function clean(array $row, array $drop): array
    {
        foreach ($drop as $key) {
            unset($row[$key]);
        }

        return $row;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function encode(array $rows): string
    {
        return CanonicalJson::encode($rows);
    }

    /**
     * Canonical JSON so PostgreSQL jsonb key reordering never produces a false
     * "changed" result: array keys are sorted recursively before encoding. Null
     * and empty arrays are treated as equal (an absent JSON column vs `[]`).
     */
    private function normalize(mixed $value): string
    {
        return CanonicalJson::encodeOptional($value);
    }
}
