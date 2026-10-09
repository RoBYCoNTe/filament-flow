<?php

namespace RoBYCoNTe\FilamentFlow\Infolists\Components;

use Carbon\CarbonInterval;
use Closure;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldLabels;
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldPresentation;
use RoBYCoNTe\FilamentFlow\Contracts\StoresRequestAttachments;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Presentation\DefaultFieldPresenter;
use RoBYCoNTe\FilamentFlow\Presentation\FieldPresentation;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;
use RoBYCoNTe\FilamentFlow\Support\FieldChanges;
use RoBYCoNTe\FilamentFlow\Support\LocalizedDate;

/**
 * The timeline of the states a record has been through: when it moved, from which state to
 * which, and by whom — with the notes, the reason, the time spent in each state and, when
 * the host asks for them, the data the transition carried and the record as it stood before
 * and after.
 *
 * How much of it is shown is a choice of the host, and an administrator may be allowed to see
 * the whole history at every state.
 */
class TransitionTimeline extends Entry
{
    protected string $view = 'filament-flow::infolists.transition-timeline';

    /**
     * The transitions shown before the reader asks for more: the rows past the
     * limit wait behind a "show more" button instead of a silent cut.
     */
    protected int $limit = 10;

    /**
     * How many transitions the query loads at most. The history of a long-lived
     * record can be hundreds of rows deep: loading them all would punish every
     * page view for the sake of a corner.
     */
    protected int $loadLimit = 100;

    protected bool $showAllForAdmins = true;

    protected bool $filterByAccess = true;

    /**
     * Whether the entries past the limit are reachable at all: with this off the
     * timeline stays as long as the limit says, and the "show more" button never
     * appears.
     */
    protected bool $expandable = true;

    /**
     * Whether the timeline shows what the transition carried: the form data, the
     * fields that changed, the validation errors — inside a fold that stays shut
     * until the reader opens it.
     */
    protected bool $showMetadata = true;

    /**
     * The address and the browser of the author are an audit matter: they are
     * hidden unless the host asks for them.
     */
    protected bool $showIpAddress = false;

    /**
     * Snapshots hold the whole record as it stood before and after: rich, but
     * heavy, so the host has to name them explicitly.
     */
    protected bool $showSnapshots = false;

    /**
     * Whether the fields with nothing to read are left out of the submitted data:
     * a form carries dozens of empty paths, and they say nothing. The count of what
     * was left out is said in one line.
     */
    protected bool $hideEmptyFields = true;

    /**
     * Whether an entry that carries values nobody compared shows them: the entries
     * logged before the engine learned to record the delta have no answer of their
     * own, and their submission is what there is. It reads under a fold of its own,
     * never as "changed fields".
     */
    protected bool $showSubmittedData = true;

    /**
     * Whether a section folds its groups when there is something to navigate: a
     * handful of fields is read at a glance, and folding it would only put a click
     * between the reader and the answer.
     */
    protected bool $collapseGroups = true;

    /**
     * The paths the history leaves out entirely: engine keys, leftovers of older versions of
     * the call, whatever a reader has no use for. They may name a path (`meta.saved_at`), a
     * subtree (`extra.*`, or just `extra`) and use `*` wildcards.
     *
     * @var list<string>
     */
    protected array $hiddenFields = [];

    /**
     * Under this many fields, one block is read at a glance: no fold, no button.
     */
    protected const FOLD_GROUPS_AFTER_FIELDS = 8;

    protected Closure|string $stateAttribute = 'state';

    /**
     * The format of the absolute date, in PHP `date` letters. When the host does
     * not choose, the locale does: day before month where it belongs.
     */
    protected ?string $dateTimeFormat = null;

    /**
     * The colour and the icon of each state, asked once per state and remembered:
     * the same state returns at every entry, and the answer never changes in a
     * single render.
     *
     * @var array<string, array{color: ?string, icon: ?string}>
     */
    protected array $stateMarkerCache = [];

    public static function make(?string $name = 'transition-timeline'): static
    {
        // A name humanised by the framework ("Flow timeline") says nothing: the
        // label starts translated, and the host may still name it as it likes.
        return parent::make($name ?? 'transition-timeline')
            ->label(__('filament-flow::messages.timeline_label'));
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function loadLimit(int $loadLimit): static
    {
        $this->loadLimit = max($loadLimit, $this->limit);

        return $this;
    }

    public function showAllForAdmins(bool $show = true): static
    {
        $this->showAllForAdmins = $show;

        return $this;
    }

    public function filterByAccess(bool $filter = true): static
    {
        $this->filterByAccess = $filter;

        return $this;
    }

    public function expandable(bool $expandable = true): static
    {
        $this->expandable = $expandable;

        return $this;
    }

    public function showMetadata(bool $show = true): static
    {
        $this->showMetadata = $show;

        return $this;
    }

    public function showIpAddress(bool $show = true): static
    {
        $this->showIpAddress = $show;

        return $this;
    }

    public function showSnapshots(bool $show = true): static
    {
        $this->showSnapshots = $show;

        return $this;
    }

    public function hideEmptyFields(bool $hide = true): static
    {
        $this->hideEmptyFields = $hide;

        return $this;
    }

    public function showSubmittedData(bool $show = true): static
    {
        $this->showSubmittedData = $show;

        return $this;
    }

    public function collapseGroups(bool $collapse = true): static
    {
        $this->collapseGroups = $collapse;

        return $this;
    }

    /**
     * @param  list<string>  $patterns  paths to leave out of the history, `*` wildcards allowed
     */
    public function hideFields(array $patterns): static
    {
        $this->hiddenFields = array_values(array_filter(
            $patterns,
            static fn (mixed $pattern): bool => is_string($pattern) && trim($pattern) !== '',
        ));

        return $this;
    }

    public function stateAttribute(Closure|string $attribute): static
    {
        $this->stateAttribute = $attribute;

        return $this;
    }

    public function dateTimeFormat(?string $format): static
    {
        $this->dateTimeFormat = $format;

        return $this;
    }

    public function getTimeline(): Collection
    {
        $record = $this->record();

        if ($record === null) {
            return collect();
        }

        $query = $this->baseTimelineQuery($record)
            ->limit(max($this->loadLimit, $this->limit));

        return $query->get();
    }

    public function getTotalCount(): int
    {
        $record = $this->record();

        if ($record === null) {
            return 0;
        }

        return $this->baseTimelineQuery($record)->count();
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getLoadLimit(): int
    {
        return $this->loadLimit;
    }

    public function isExpandable(): bool
    {
        return $this->expandable;
    }

    public function showsMetadata(): bool
    {
        return $this->showMetadata;
    }

    public function showsIpAddress(): bool
    {
        return $this->showIpAddress;
    }

    public function showsSnapshots(): bool
    {
        return $this->showSnapshots;
    }

    public function hidesEmptyFields(): bool
    {
        return $this->hideEmptyFields;
    }

    public function showsSubmittedData(): bool
    {
        return $this->showSubmittedData;
    }

    public function collapsibleGroups(): bool
    {
        return $this->collapseGroups;
    }

    /** @return list<string> */
    public function hiddenFields(): array
    {
        return $this->hiddenFields;
    }

    public function getStateAttribute(): string
    {
        return $this->evaluate($this->stateAttribute);
    }

    public function getDateTimeFormat(): string
    {
        return $this->dateTimeFormat ?? LocalizedDate::dateTime();
    }

    /**
     * The colour and the icon the marker of an entry wears: the workflow paints
     * its states, and the timeline borrows that paint; without a workflow the
     * action stays gray and the transition wears the primary.
     *
     * @return array{color: ?string, icon: ?string}
     */
    public function getMarkerFor(WorkflowStateTransition $entry): array
    {
        // An action never borrows the paint of the state it happens in: it stays
        // neutral, whatever the workflow gave that state.
        if ($entry->isAction()) {
            return ['color' => null, 'icon' => null];
        }

        if (array_key_exists($entry->to_state, $this->stateMarkerCache)) {
            return $this->stateMarkerCache[$entry->to_state];
        }

        $record = $this->record();
        $metadata = null;

        if ($record instanceof Model) {
            $metadata = app(StateService::class)->getStateMetadata(
                get_class($record),
                (string) $entry->to_state,
                $this->getStateAttribute(),
                method_exists($record, 'getWorkflowTenantId') ? $record->getWorkflowTenantId() : null,
            );
        }

        return $this->stateMarkerCache[$entry->to_state] = [
            'color' => $metadata['color'] ?? null,
            'icon' => $metadata['icon'] ?? null,
        ];
    }

    /**
     * A duration under a minute is noise: nobody reads "12 seconds" with profit.
     */
    public function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds < 60) {
            return null;
        }

        return CarbonInterval::seconds($seconds)
            ->cascade()
            ->forHumans(['parts' => 2, 'locale' => app()->getLocale()]);
    }

    /**
     * A value of the history drawn as text: arrays and objects as JSON, the
     * missing ones as a dash that says "there was nothing here".
     */
    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '—' : $json;
    }

    /**
     * How many fields a transition declares as changed, when the host records
     * them: the number stands on the entry, the detail inside the fold.
     */
    public function countFieldChanges(WorkflowStateTransition $entry): ?int
    {
        if (! $this->showMetadata) {
            return null;
        }

        $changes = $entry->metadata?->field_changes;

        return is_array($changes) && $changes !== [] ? count($changes) : null;
    }

    /**
     * How one field reads: the host that knows its own fields is asked first — a
     * scheme knows the label, the shape and the section of every path — and the
     * generic reading covers what remains.
     */
    public function presentationFor(string $path, mixed $value): FieldPresentation
    {
        // A path the host keeps out of the history never reaches it: not in the submission,
        // not in the changes — and the count of what was left out still says how many.
        if ($this->hidesPath($path)) {
            return FieldPresentation::hidden();
        }

        $record = $this->record();

        if ($record instanceof HasFieldPresentation) {
            $presentation = $record->fieldPresentation($path, $value);

            if ($presentation instanceof FieldPresentation) {
                return $presentation;
            }
        }

        $label = $record instanceof HasFieldLabels ? $record->fieldLabel($path) : null;

        return app(DefaultFieldPresenter::class)->present(
            $path,
            $value,
            is_string($label) ? $label : null,
        );
    }

    /**
     * Whether the transition was compared at all: an empty delta is an answer (nothing moved),
     * while no delta at all means nobody looked — the rows logged before the engine recorded
     * them.
     */
    public function wasCompared(WorkflowStateTransition $entry): bool
    {
        if (! $this->showMetadata) {
            return false;
        }

        return is_array($entry->metadata?->field_changes);
    }

    /**
     * What the requester asked for when the entry opened a request: the fields it opened to the
     * answering side, by the label a person reads, and the documents it attached. Nothing for
     * an entry that opened no request, or opened one with no scope.
     *
     * @return array{mode: string, fields: list<array{path: string, label: string}>, documents: list<array{id: int|string, name: string, url: string}>}|null
     */
    public function getRequestScope(WorkflowStateTransition $entry): ?array
    {
        if (! $this->showMetadata) {
            return null;
        }

        $scope = $entry->metadata?->custom_data['request_scope'] ?? null;

        if (! is_array($scope)) {
            return null;
        }

        $fields = [];

        foreach ((array) ($scope['paths'] ?? []) as $path) {
            $presentation = $this->presentationFor((string) $path, null);

            if ($presentation->visible) {
                $fields[] = ['path' => (string) $path, 'label' => $presentation->label];
            }
        }

        $documents = [];
        $ids = array_values((array) ($scope['attachments'] ?? []));
        $record = $this->record();

        if ($ids !== [] && $record instanceof Model && app()->bound(StoresRequestAttachments::class)) {
            $documents = app(StoresRequestAttachments::class)->documents($record, $ids);
        }

        if ($fields === [] && $documents === []) {
            return null;
        }

        return [
            'mode' => ($scope['mode'] ?? 'exclusive') === 'additive' ? 'additive' : 'exclusive',
            'fields' => $fields,
            'documents' => $documents,
        ];
    }

    /**
     * The fields the transition moved, ready to be drawn: the label a person reads,
     * the value before and the value after.
     *
     * @return array{fields: list<array{path: string, label: string, group: string|null, before: FieldPresentation, after: FieldPresentation}>, hidden: int}
     */
    public function getChangedFields(WorkflowStateTransition $entry): array
    {
        if (! $this->showMetadata) {
            return ['fields' => [], 'hidden' => 0];
        }

        $changes = $entry->metadata?->field_changes;

        if (! is_array($changes) || $changes === []) {
            return ['fields' => [], 'hidden' => 0];
        }

        $fields = [];
        $hidden = 0;

        foreach ($changes as $path => $change) {
            $from = is_array($change) ? ($change['from'] ?? $change[0] ?? null) : null;
            $to = is_array($change) ? ($change['to'] ?? $change[1] ?? $change) : $change;

            $before = $this->presentationFor((string) $path, $from);
            $after = $this->presentationFor((string) $path, $to);

            // A field the host keeps out of the history takes its change with it.
            if (! $before->visible && ! $after->visible) {
                $hidden++;

                continue;
            }

            $fields[] = [
                'path' => (string) $path,
                'label' => $after->label !== '' ? $after->label : $before->label,
                'group' => $after->group ?? $before->group,
                'before' => $before,
                'after' => $after,
            ];
        }

        return ['fields' => $fields, 'hidden' => $hidden];
    }

    /**
     * The data the transition carried, field by field: the fallback for the
     * transitions logged before their changes were recorded beside the form data.
     *
     * @return array{fields: list<array{path: string, label: string, group: string|null, presentation: FieldPresentation}>, hidden: int}
     */
    public function getSubmittedFields(WorkflowStateTransition $entry): array
    {
        if (! $this->showMetadata) {
            return ['fields' => [], 'hidden' => 0];
        }

        $submitted = $entry->metadata?->form_data;

        if (! is_array($submitted) || $submitted === []) {
            return ['fields' => [], 'hidden' => 0];
        }

        $fields = [];
        $hidden = 0;

        foreach (FieldChanges::flatten($submitted) as $path => $value) {
            $presentation = $this->presentationFor((string) $path, $value);

            if (! $presentation->visible) {
                $hidden++;

                continue;
            }

            if ($this->hideEmptyFields && $presentation->isEmpty()) {
                $hidden++;

                continue;
            }

            $fields[] = [
                'path' => (string) $path,
                'label' => $presentation->label,
                'group' => $presentation->group,
                'presentation' => $presentation,
            ];
        }

        return ['fields' => $fields, 'hidden' => $hidden];
    }

    /**
     * The rows of the history under the heading they belong to, in the order the
     * fields declare: the blocks of a form, not the alphabetical order of a map.
     *
     * @param  list<array{group: string|null}>  $fields
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupFields(array $fields): array
    {
        $groups = [];

        foreach ($fields as $field) {
            $group = (string) ($field['group'] ?? '');
            $groups[$group][] = $field;
        }

        return $groups;
    }

    /**
     * Whether a section folds its groups. One block of a few fields is read at a
     * glance; several blocks — or a long one — are navigated, and a fold with the
     * count of what it holds is what makes that possible.
     *
     * @param  list<array<string, mixed>>  $fields
     */
    public function foldsGroups(array $fields): bool
    {
        if (! $this->collapseGroups) {
            return false;
        }

        return count($this->groupFields($fields)) > 1
            || count($fields) > self::FOLD_GROUPS_AFTER_FIELDS;
    }

    /**
     * The record before and after the transition, reduced to what moved: the
     * keys where the two snapshots disagree.
     *
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    public function getSnapshotDiff(WorkflowStateTransition $entry): array
    {
        if (! $this->showSnapshots) {
            return [];
        }

        $before = $entry->snapshotBefore?->record_data;
        $after = $entry->snapshotAfter?->record_data;

        $before = is_array($before) ? $before : [];
        $after = is_array($after) ? $after : [];

        if ($before === [] && $after === []) {
            return [];
        }

        $diff = [];

        foreach (array_values(array_unique(array_merge(array_keys($before), array_keys($after)))) as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            if ($oldValue !== $newValue) {
                $diff[] = ['field' => $key, 'before' => $oldValue, 'after' => $newValue];
            }
        }

        return $diff;
    }

    /**
     * Whether a path is left out of the history. A pattern hides the path it names and
     * everything under it: `extra` hides `extra.note` too.
     */
    public function hidesPath(string $path): bool
    {
        foreach ($this->hiddenFields as $pattern) {
            if ($pattern === $path || Str::is($pattern, $path) || Str::is($pattern.'.*', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The record the timeline draws, when there is one: a component asked outside a
     * schema has no container to take it from, and answers nothing instead of failing.
     */
    protected function record(): ?Model
    {
        try {
            $record = $this->getRecord();
        } catch (\Throwable) {
            return null;
        }

        return $record instanceof Model ? $record : null;
    }

    protected function isAdmin(): bool
    {
        if (! $this->showAllForAdmins) {
            return false;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Same resolver as the access rules: a host that keeps super admins
        // elsewhere (tenant roles, custom resolver) is honoured here too.
        return app(AccessRuleEvaluator::class)->isSuperAdmin($user);
    }

    /**
     * The query every answer comes from: the timeline, the count — always the
     * same rows, so the numbers never contradict the list.
     */
    protected function baseTimelineQuery(Model $record): Builder
    {
        $with = ['transition'];

        if ($this->showMetadata) {
            $with[] = 'metadata';
        }

        if ($this->showSnapshots) {
            $with[] = 'snapshotBefore';
            $with[] = 'snapshotAfter';
        }

        $query = WorkflowStateTransition::query()
            ->forRecord($record)
            ->with($with)
            ->orderByDesc('created_at');

        // An administrator sees the whole history, the rows the host hid
        // included; anyone else only what the host chose to show.
        if ($this->filterByAccess && ! $this->isAdmin()) {
            $query->visible();
        }

        return $query;
    }
}
