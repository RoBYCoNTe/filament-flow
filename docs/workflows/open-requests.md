# Open Requests

Some workflows are a conversation: the office asks the applicant for something — a note to read,
a document to attach, a clarification — the applicant answers, and the exchange repeats until the
file is complete. Other times the workflow just **says something**: a rejection with its reason,
a decision with a note, and walks away. The engine does not keep these exchanges in a table of
their own: it reads them from the history it already writes — which transitions asked, which ones
answered, which ones left a message — and tells whoever opens the record what is expected of
them, or what was decided.

Nothing new is persisted. A request exists while the history says a transition opened it and no
later transition answered it, and a workflow that marks no transition behaves exactly as before:
the components stay quiet, and no migration, column or configuration is needed.

## Marking a request and its answer

Two methods on the `Transition` builder write the intent into the metadata the transition already
carries:

```php
use RoBYCoNTe\FilamentFlow\Definition\Transition;

$request = Transition::make('request_integration', 'under_review', 'integration_requested')
    ->label('Request an integration')
    ->opensRequest()                                  // the office asks
    ->withRequestFields(
        noteField: 'review.notes',                    // where the note lives
        deadlineField: 'meta.integration_deadline',   // where the term lives
    );

$answer = Transition::make('resubmit', 'integration_requested', 'submitted')
    ->label('Send the integration')
    ->answersRequest();                               // the applicant answers
```

| Method | Effect |
|---|---|
| `opensRequest(bool $opens = true)` | The transition opens a request. It stays open until a transition that answers it runs. |
| `answersRequest(bool $answers = true)` | The transition closes the open request that has been waiting the longest. |
| `withRequestFields(?string $noteField = null, ?string $deadlineField = null)` | Names where the note and the term live, so the components that read them need no path of their own — the call declares them once. |
| `leavesMessage(?string $noteField = null)` | The transition leaves a message for the other side — a decision with its reason — and expects no answer. The note is named here, once. |

The flags live in the `metadata` of the transition: they survive the `toArray()` / `fromArray()`
round trip and are persisted by the applier like any other metadata. `isRequestOpening()` and
`isRequestAnswering()` read them back on the definition.

Because the answer closes the **oldest** request that is still waiting, a workflow may ask more
than once — `renew_integration` re-opens and a later `resubmit` closes it — and the reading stays
honest.

### Messages: what the workflow said

A rejection with its reason is not a request: nobody has to answer it. It is the same primitive
seen from the other side, and it is declared the same way:

```php
Transition::make('reject', 'under_review', 'rejected')
    ->label('Reject')
    ->requiresReason()
    ->leavesMessage('rejection_reason')   // the reason is read on the application
    ->validationRule(ValidationRule::make('rejection_reason')->rules(['required', 'min:20']));
```

A message is read from the same history and the same note resolution as a request, but it
**waits for nothing** (`isOpen()` is false) and it is never closed by an answer. The components
show it as what the workflow said: it wears the **colour of the state it moved to** (a rejection
in red, an approval in green) and takes no call to action. Nothing else is added to the call —
no second banner, no separate declaration.

### The note and the term

Neither is a column of the engine; `withRequestFields()` only names two paths the host already
has:

- the **note** is read in this order: what the transition carried (the `form_data` kept beside the
  history row), the `notes` column of the history, then the value the record holds now;
- the **term** is read from the record — a side effect of the request usually writes it — and
  parsed as a date.

## Reading the exchanges: `OpenRequests`

`RoBYCoNTe\FilamentFlow\Support\OpenRequests` answers with the exchanges of a record, newest
first:

```php
use RoBYCoNTe\FilamentFlow\Support\OpenRequests;

$requests = app(OpenRequests::class)->forRecord($order, [
    'note_field' => null,          // override the path the transition declared
    'deadline_field' => null,
    'show_deadline' => true,
    'open_transitions' => [],      // name the transitions here instead of marking the DSL
    'answer_transitions' => [],
    'message_transitions' => [],   // transitions that leave a message
    'state_column' => 'state',     // where the record keeps its state (for the message colour)
    'include_answered' => false,   // read the answered exchanges too
    'limit' => null,               // keep the newest N
]);

foreach ($requests as $request) {
    $request->isMessage();         // a one-way message (a decision), not a request
    $request->kind;                // 'request' or 'message'
    $request->color;               // the colour of the destination state (messages)
    $request->isOpen();            // still waiting? (always false for a message)
    $request->isOverdue();         // the term has passed
    $request->isDueSoon(days: 3);  // the term is close
    $request->daysRemaining();     // whole days left (negative once past)
    $request->note;                // the sentence the office wrote
    $request->deadline;            // Carbon|null
    $request->requestedBy;         // who asked
    $request->requestedAt;         // when
    $request->label;               // the label of the transition that asked
    $request->answeredAt;          // when it was answered (`include_answered`)
    $request->toState;             // the state the request leads to
}
```

`OpenRequest` is a reading, not a row: nothing is stored for it, and it exists only while a
component asks.

### Naming the transitions instead of marking them

A host that would rather not touch the definition can pass the transition names to the components
(or to `forRecord()`): when `open_transitions` / `answer_transitions` are given they win over the
metadata flags. The paths can be overridden the same way, with `note_field` / `deadline_field`.
The declaration on the transition is what makes the engine self-contained; the options are the
escape hatch.

## Where it shows

The reading is exposed through two components — an infolist entry and a table column — plus a
content block in the host's own form:

- [`OpenRequestsEntry`](../ui/infolist-components.md#openrequestsentry) — what is expected, with
  the note and the term, on the record page;
- [`OpenRequestsColumn`](../ui/table-columns.md#openrequestscolumn) — a chip on the rows of a
  list, toggleable so each reader keeps it or puts it away;
- a content block of the host (in this platform, `content.action_required`), where the call places
  it inside a form.

They **guide rather than act**: they say what is expected and by which action it is answered,
while the buttons that move the record stay in the toolbar of the state actions. That division is
the point — the reader is told what to do, and the action keeps living where the workflow already
puts it.

A **message** (a decision the workflow took) is shown by the same components, with no new
placement: the heading of the banner turns from *what is expected* to *the outcome*, and the chip
wears the **colour and the label of the state it moved to** — a rejection in red, an approval in
green, a closure in gray. The colour is read once from the workflow (`StateService`, cached): it
is the state's own colour, not a colour declared again for the message.

## What it is not

- **Not a chat.** There are no threads and no read/unread state. The exchange is the history: a
  transition that asked, a transition that answered, a transition that said something and left.
- **Not a new store.** No table, no column, no queue. The only thing that is written is the
  metadata of the transitions, which was already there.
- **Not a gate.** Opening or answering a request changes nothing about who may run a transition:
  access is still the one the states declare.

## See also

- [Request Scope](./request-scope.md) — let the office pick the fields the applicant may change and attach
  documents to the request.
