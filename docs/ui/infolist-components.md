# Infolist Components

Filament Flow provides infolist entry components for displaying workflow-related information inside Filament infolists, plus a workflow diagram view for the admin panel.

## TransitionTimeline

`TransitionTimeline` renders a chronological audit trail of state transitions for a workflow record. Each entry shows what changed, who triggered it, and when — giving viewers a clear history of the record's lifecycle.

### Adding to an Infolist

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\TransitionTimeline;

public function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            TransitionTimeline::make(),
        ]);
}
```

The component carries a translated label by default (`History` / `Storico`): name it or hide it as any other entry.

### Options

```php
TransitionTimeline::make()
    ->limit(5)
    ->showAllForAdmins()
    ->filterByAccess()
    ->expandable()
    ->showMetadata()
    ->showIpAddress()
    ->showSnapshots()
    ->hideEmptyFields()
    ->showSubmittedData()
    ->collapseGroups()
    ->hideFields(['meta.*', 'extra.*'])
    ->dateTimeFormat('d/m/Y H:i')
    ->stateAttribute('state'),
```

| Method | Default | Description |
|---|---|---|
| `limit(int $limit)` | `10` | Transitions shown before the "show more" button. The rows past the limit stay in the DOM behind a fold, so opening them needs no round-trip. |
| `loadLimit(int $loadLimit)` | `100` | How many transitions the query loads at most. It never goes below the visible limit. |
| `showAllForAdmins(bool $show = true)` | `true` | When enabled, users with a super-admin role see all transitions, including rows flagged `is_visible = false` (drawn with a "Hidden" badge). |
| `filterByAccess(bool $filter = true)` | `true` | When enabled, non-admin users only see transitions where `is_visible = true`. Disable this to show every transition to everyone. |
| `expandable(bool $expandable = true)` | `true` | Whether the entries past the limit are reachable at all. With this off the timeline ends at the limit, and a "N more entries" notice stands in for the rest. |
| `showMetadata(bool $show = true)` | `true` | Inside a per-entry "Details" fold: the fields the transition changed (before → after), the data it carried and the validation errors — each field read through `HasFieldPresentation` / `HasFieldLabels` when the record implements them. |
| `showIpAddress(bool $show = true)` | `false` | Adds the IP address and the browser of the author to the "Details" fold. An audit matter: hidden unless the host asks for it. |
| `showSnapshots(bool $show = true)` | `false` | Adds a "Record changes" section to the fold, reduced to the fields where the before/after snapshots disagree. Snapshots are heavy, so the host names them explicitly. |
| `hideEmptyFields(bool $hide = true)` | `true` | Leaves the empty paths out of the submitted data — a form carries dozens of them — and says how many were left out in one line. The fields a transition *changed* are always shown, emptied ones included. |
| `showSubmittedData(bool $show = true)` | `true` | Whether an entry that was **never compared** (the rows logged before the engine recorded the deltas) shows the values it carried, under a fold of its own. Off, those entries say only what the state move says. |
| `collapseGroups(bool $collapse = true)` | `true` | Whether a section folds its **groups** when there is something to navigate — more than one block, or more than eight fields. Under that, everything stands in sight: a fold there would only put a click between the reader and the answer. |
| `hideFields(array $patterns)` | `[]` | Paths the history leaves out entirely, in the submission and in the changes: engine keys, leftovers of older versions of a call, noise. A pattern names a path (`meta.saved_at`), a whole subtree (`extra.*`, or just `extra`) and takes `*` wildcards. What is left out is counted with the empty paths, so the count never lies. |
| `dateTimeFormat(?string $format)` | locale | The absolute date on every entry, in PHP `date` letters. When the host does not choose, the locale does: `d/m/Y H:i` in Italian, `M j, Y H:i` otherwise. The relative time stands beside it, smaller. |
| `stateAttribute(string \| Closure $attribute)` | `'state'` | The column that holds the current state of the record. It decides which workflow the marker colours come from. |

### What Each Entry Shows

Each timeline entry displays:

- **A marker** with the colour and the icon the workflow gave the destination state (the same `StateService` metadata the state badge wears). State-changing transitions fall back to the primary colour; same-state actions stay gray with a pencil icon.
- **Transition title** — for state-changing transitions this is `from_state → to_state` using human-readable labels, falling back to the raw state names. For self-transitions, the transition's own label is used instead.
- **Timestamp** — the absolute date and time first (`25 Sep 2026, 09:25` style, locale-aware), the relative time ("3 hours ago") beside it, smaller, and the ISO 8601 value in the `datetime` attribute.
- **Author** — the name and, when recorded, the email of the user who triggered the transition.
- **Time in the previous state** — how long the record waited in the state it came from ("3 days in Pending"), when the transition recorded a duration of a minute or more.
- **Changed fields** — a count of the paths the transition moved, when `field_changes` metadata is present (see [Field changes](../reference/configuration.md#field-changes)).
- **Reason** — drawn as a highlighted callout, distinct from the notes.
- **Notes** — in full, quoted, no longer truncated.
- **A "Hidden" badge** — on rows flagged `is_visible = false`, which only administrators see when `showAllForAdmins` is on.

### The Details Fold

When a transition carries metadata, snapshots or (with `showIpAddress()`) technical traces, a "Details" disclosure opens onto them, and what it leads with is the answer to the only question the history is read for — *what changed?*:

| The engine compared… | The fold shows |
|---|---|
| …and paths moved | **Changed fields**: one row per path, under the heading of the section it belongs to, the value before and the value after. |
| …and nothing moved | **No field changed** — one line. A save that touched nothing says so, instead of laying out the values it carried as if they had moved. |
| …nothing at all (the row predates the deltas) | **Submitted data**: the values the transition carried, flattened path by path and grouped, under a fold of its own — never presented as changes. `showSubmittedData(false)` hides it entirely. |

**Grouping, and folding the groups.** When the host gives its fields a `group` (a section, a step), the history reads under those headings, in the order the fields declare. A section with several blocks — or one long block — folds each of them into a `<details>` whose summary carries the name and the number of fields, with **Expand all** / **Collapse all** in the heading for a single click. The blocks are a form's sections, so the reader navigates the paragraph they need instead of scrolling the whole application:

```
Changed fields                      Expand all · Collapse all
  ▸ Body and contacts · 2
  ▾ Self-declarations · 3
        Truthfulness of the declared data: No → Yes
        Consent to the processing of personal data: No → Yes
        No other public funding for the same works: No
  ▸ Breakdown of the expense · 1
```

Fields with no group stand together at the top, under "Other fields". The folding is native (`<details>`), so it works before any script loads and keeps the keyboard and the screen reader happy.

### How Fields Read

The component asks the record how its own fields read, through two contracts — and falls back to a generic reading when it does not answer:

```php
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldLabels;
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldPresentation;
use RoBYCoNTe\FilamentFlow\Presentation\FieldPresentation;

class Application extends Model implements HasFieldLabels, HasFieldPresentation
{
    /** The label of a field path — also used by the validation messages. */
    public function fieldLabel(string $path): ?string
    {
        return $this->scheme?->labelOf($path);
    }

    /** How the value reads: money, a date, the rows of a spreadsheet, a set of files. */
    public function fieldPresentation(string $path, mixed $value): ?FieldPresentation
    {
        $field = $this->scheme?->fieldAt($path);

        if ($field === null) {
            return null; // not a field of this record: the generic reading covers it
        }

        return FieldPresentation::text(
            $field->label,
            Number::currency((float) $value, 'EUR', 'it'),
            group: $field->sectionLabel,
        );
    }
}
```

- `HasFieldLabels::fieldLabel()` — the name a person reads for a path. The engine already asks it for its validation messages.
- `HasFieldPresentation::fieldPresentation()` — the name, the **shape** of the value and the block it belongs to, as a `FieldPresentation`: `text()`, `pairs()`, `table()`, `files()`, `empty()` or `hidden()` (a content block, an internal key). Returning `null` says the path is not one of the record's fields.
- A record that answers neither is read by `DefaultFieldPresenter`: a map becomes labelled pairs, a list of maps a table, a boolean yes or no, and the label is the words of the key. Those words are asked of the **translations** first — the whole path (`applicant.vat_number`), then the last step of it (`vat_number`), then the same headlined (`Vat Number`) — so a host that translates its own vocabulary has those words written down already, in the same place the scheme's labels come from. Nothing known, the headlined words stand as they are.

When the value is a change (`field_changes`), the presenter is asked twice — once for the value before, once for the value after — so each side reads in the shape the host gives it.

### Admin Detection

The component determines whether the current user is an admin by checking if they hold any role listed in the `state_access.super_admin_roles` config key (default: `['super_admin']`). This uses Spatie Permission's `hasAnyRole()` method when available.

```php
// config/filament-flow.php
'state_access' => [
    'super_admin_roles' => ['super_admin', 'admin'],
],
```

### Programmatic Access

If you need to work with the transition records directly (e.g. in a custom view or notification), call `getTimeline()` on the component instance:

```php
$entries = $timelineComponent->getTimeline(); // Collection of WorkflowStateTransition
$total   = $timelineComponent->getTotalCount(); // int — full count, unaffected by limit
```

## OpenRequestsEntry

`OpenRequestsEntry` tells what the workflow is waiting for on a record: the transitions that
asked something and no later one answered — who asked, when, with which note and which term.

It reads the history the engine already keeps, through the note and the term the transition that
asked declared with
[`withRequestFields()`](../workflows/open-requests.md#marking-a-request-and-its-answer). A
workflow that marks no transition renders nothing: the entry is silent where there is nothing to
say.

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\OpenRequestsEntry;

OpenRequestsEntry::make()
    ->noteField('review.notes')          // optional: override the declared path
    ->deadlineField('meta.deadline')     // optional: override the declared path
    ->hideDeadline()                     // omit the term
    ->showAnswered()                     // read the closed exchanges too
    ->limit(3)                           // keep the newest N
    ->transitions(['request_integration'], ['resubmit']); // name them instead of marking the DSL
```

| Method | Default | Description |
|---|---|---|
| `noteField(?string $field)` | declared | Overrides the path of the note the transition declared. |
| `deadlineField(?string $field)` | declared | Overrides the path of the term the transition declared. |
| `hideDeadline(bool $hidden = true)` | `false` | Leaves the term out of the entry. |
| `showAnswered(bool $show = true)` | `false` | Also reads the exchanges already answered, under the open ones. |
| `limit(int $limit)` | — | How many exchanges are shown at most, newest first. |
| `openTransitions(array $names)` / `answerTransitions(array $names)` / `transitions(array $open, array $answer = [])` | `[]` | Names the transitions instead of reading the metadata flags. When given, they win over the flags. |

Each open request reads as a card: the label of the transition that asked, who asked and when,
the note quoted, the term (with the days left, amber when close, red when passed), and — for the
reader the request is meant for — the line that points at the action that answers it. The view
tells the two sides apart: the owner of the record reads *“Your turn”*, everyone else *“Waiting
for a reply”*.

A **message** — a decision a transition left with `leavesMessage()` — reads as a card of its own:
it wears the **colour and the label of the state it moved to** (a rejection in red, an approval in
green) and asks for nothing. No second entry, no second placement: the same component tells both
what the workflow waits for and what it said.

### Programmatic access

The reading is available outside the render, for a host that wants to ask on its own:

```php
$requests = OpenRequestsEntry::make()->requestsFor($order); // Collection of OpenRequest
```

For the data model and the options of the resolver behind it, see
[Open Requests](../workflows/open-requests.md).

## StateBadge

The badge of the state a record stands in: its name, its colour, the mark it wears — and, when the call says so, what the state means and how long the record has stood there.

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\StateBadge;

StateBadge::make()
    ->attribute('state')          // column that holds the state (default: 'state')
    ->description()               // read the description the workflow gave the state
    ->extra(fn (?Model $record) => 'In this state since '.$since),  // a line of the host
```

The component carries a translated label by default (`State` / `Stato`): name it or hide it as any other entry.

| Method | Default | Description |
|---|---|---|
| `attribute(string \| Closure $attribute)` | `'state'` | The column that holds the state. The tenant of the row is resolved through it, so a workflow scoped to an owner is the one that answers. |
| `description(bool $show = true)` | `false` | Reads under the badge the description the workflow gave the state — what it means, in the words of the call — when there is one. |
| `extra(Closure $callback)` | `null` | A line of the host under the badge: a deadline, the time spent in the state. The callback receives the record and the state metadata, and answers `null` when it has nothing to say. |

**The mark the badge wears** is the icon the workflow gave the state; failing that, the one its kind deserves — `heroicon-m-check-badge` for an approval that ends a run, `heroicon-m-x-circle` for a refusal, `heroicon-m-play-circle` for the state a record starts in. A state in the middle, with no icon of its own, wears a **dot** in the colour of the state. A state flagged `is_final` also carries a "Final state" chip: the reader knows at a glance that the record does not leave it. A state the workflow does not declare still reads as its name, rather than leaving the badge empty.

## AssignmentSummaryEntry

`AssignmentSummaryEntry` renders a detailed assignment card for a record inside an infolist. It shows each assigned user alongside their effective `view`, `edit`, and `transition` permissions for the record's current state, access override indicators, and role-based access rules derived from the active workflow configuration.

For full documentation on the assignment system, including the data model, the `HasWorkflowAssignments` trait, and related UI components, see [Assignment Management](assignments.md).

### Quick Example

```php
use RoBYCoNTe\FilamentFlow\Infolists\Components\AssignmentSummaryEntry;

AssignmentSummaryEntry::make()
    ->stateColumn('status'),
```

The `stateColumn` option tells the component which model column holds the current workflow state (default: `'state'`). This is used to resolve the active state and compute effective permissions for each assigned user.

For the `metadataBadges()` extension point and the full data shape passed to callbacks, see the [AssignmentSummaryEntry section in Assignment Management](assignments.md#assignmentsummaryentry-infolist-component).
