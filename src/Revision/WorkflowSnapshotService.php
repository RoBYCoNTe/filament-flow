<?php

namespace RoBYCoNTe\FilamentFlow\Revision;

use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationChannel;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationRecipient;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotificationTemplate;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;

/**
 * Stores a versioned snapshot of a workflow definition (states, transitions,
 * side effects, validation rules, state fields) and bumps the workflow version.
 */
class WorkflowSnapshotService
{
    /**
     * @param  array<string,mixed>|null  $changes
     * @param  int|null  $version  Explicit revision version (e.g. the scheme/bando
     *                             version); when null the workflow's own counter is used.
     */
    public function snapshot(Workflow $workflow, ?array $changes = null, ?int $userId = null, ?int $version = null): int
    {
        $version ??= max(1, (int) ($workflow->schema_version ?? 1));

        $states = WorkflowState::query()
            ->where('workflow_id', $workflow->id)
            ->orderBy('id')
            ->get()
            ->map(fn (WorkflowState $s): array => $s->getAttributes())
            ->all();

        $transitions = WorkflowTransition::query()
            ->where('workflow_id', $workflow->id)
            ->orderBy('id')
            ->get()
            ->map(fn (WorkflowTransition $t): array => $t->getAttributes())
            ->all();

        $transitionIds = array_column($transitions, 'id');
        $stateIds = array_column($states, 'id');

        $sideEffects = $transitionIds !== []
            ? WorkflowTransitionSideEffect::query()->whereIn('transition_id', $transitionIds)->get()
                ->map(fn (WorkflowTransitionSideEffect $e): array => $e->getAttributes())->all()
            : [];

        $validationRules = $transitionIds !== []
            ? WorkflowTransitionValidationRule::query()->whereIn('transition_id', $transitionIds)->get()
                ->map(fn (WorkflowTransitionValidationRule $r): array => $r->getAttributes())->all()
            : [];

        $stateFields = $stateIds !== []
            ? WorkflowStateField::query()->whereIn('state_id', $stateIds)->get()
                ->map(fn (WorkflowStateField $f): array => $f->getAttributes())->all()
            : [];

        $stateFieldIds = array_column($stateFields, 'id');

        $stateFieldRoles = $stateFieldIds !== []
            ? WorkflowStateFieldRole::query()->whereIn('state_field_id', $stateFieldIds)->orderBy('id')->get()
                ->map(fn (WorkflowStateFieldRole $r): array => $r->getAttributes())->all()
            : [];

        $scheduledChecks = WorkflowScheduledCheck::query()
            ->where('workflow_id', $workflow->id)
            ->orderBy('id')
            ->get()
            ->map(fn (WorkflowScheduledCheck $c): array => $c->getAttributes())
            ->all();

        $accessRules = $stateIds !== []
            ? WorkflowStateAccessRule::query()->whereIn('state_id', $stateIds)->orderBy('id')->get()
                ->map(fn (WorkflowStateAccessRule $r): array => $r->getAttributes())->all()
            : [];

        $notifications = WorkflowNotification::query()
            ->where('workflow_id', $workflow->id)
            ->orderBy('id')
            ->get()
            ->map(fn (WorkflowNotification $n): array => $n->getAttributes())
            ->all();

        $notificationIds = array_column($notifications, 'id');

        $notificationRecipients = $notificationIds !== []
            ? WorkflowNotificationRecipient::query()->whereIn('notification_id', $notificationIds)->orderBy('id')->get()
                ->map(fn (WorkflowNotificationRecipient $r): array => $r->getAttributes())->all()
            : [];

        $notificationChannels = $notificationIds !== []
            ? WorkflowNotificationChannel::query()->whereIn('notification_id', $notificationIds)->orderBy('id')->get()
                ->map(fn (WorkflowNotificationChannel $c): array => $c->getAttributes())->all()
            : [];

        $notificationTemplates = $notificationIds !== []
            ? WorkflowNotificationTemplate::query()->whereIn('notification_id', $notificationIds)->orderBy('id')->get()
                ->map(fn (WorkflowNotificationTemplate $t): array => $t->getAttributes())->all()
            : [];

        DB::table('workflow_snapshots')->updateOrInsert(
            ['workflow_id' => $workflow->id, 'version' => $version],
            [
                'snapshot' => json_encode([
                    'states' => $states,
                    'transitions' => $transitions,
                    'side_effects' => $sideEffects,
                    'validation_rules' => $validationRules,
                    'state_fields' => $stateFields,
                    'state_field_roles' => $stateFieldRoles,
                    'access_rules' => $accessRules,
                    'scheduled_checks' => $scheduledChecks,
                    'notifications' => $notifications,
                    'notification_recipients' => $notificationRecipients,
                    'notification_channels' => $notificationChannels,
                    'notification_templates' => $notificationTemplates,
                ], JSON_THROW_ON_ERROR),
                'created_by' => $userId,
                'changes' => $changes !== null ? json_encode($changes) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $workflow->forceFill(['schema_version' => $version + 1])->saveQuietly();

        return $version;
    }
}
