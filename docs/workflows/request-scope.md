# Request Scope

A request that asks the applicant for "an integration" says little: the applicant may change what
the **state** allows for their role, the same for every request, and the office has no way to say
*correct only the amount and upload the revised survey*. The request scope closes both gaps. When
the office opens a request it can **pick the fields** the other side may change and **attach
documents**; the applicant is then limited to those fields until they answer, and the screen says
where to go.

It builds on [Open Requests](./open-requests.md) and stores nothing new: the pick is written in the
history row of the transition that opened the request, and the effect lasts exactly as long as the
request is open.

## Declaring it

```php
use RoBYCoNTe\FilamentFlow\Definition\RequestScope;
use RoBYCoNTe\FilamentFlow\Definition\Transition;

Transition::make('request_integration', 'under_review', 'integration_requested')
    ->opensRequest()
    ->withRequestFields(noteField: 'review.notes', deadlineField: 'meta.integration_deadline')
    ->withRequestScope(
        RequestScope::make()
            ->editableFields(only: ['applicant', 'documents'], except: ['applicant.fiscal_code'])
            ->attachments(accepts: ['pdf'], max: 5, maxSizeMb: 10)
            ->requireSelection()   // the office must pick at least one field
            ->requireChange()      // the applicant must change at least one before answering
            ->answeredBy('@owner') // who receives the override
            ->exclusive()          // default; ->additive() leaves the other fields alone
    );
```

`withRequestScope()` needs `opensRequest()` on the same transition, otherwise `toArray()` throws.
The declaration lives in `metadata['request_scope']`, so the database sync, the plan diff and the
SDK export carry it with no code of their own.

| Method | Meaning |
|---|---|
| `editableFields(only, except)` | what the office may choose from: the **whitelist**. A path covers everything under it |
| `attachments(accepts, max, maxSizeMb)` | documents the office may attach; without it the dialog has no upload |
| `requireSelection()` | the office must pick at least one field. Off by default: a request with no pick is the plain one |
| `requireChange()` | the answer is refused until one of the picked fields differs from its snapshot |
| `answeredBy(role)` | who receives the override (default `@owner`); the others keep the rules of the state |
| `exclusive()` / `additive()` | outside the pick, fields are **read-only** (default) or **unchanged** |

## What the office does

The dialog of the transition gains two optional fields, both bound by the host:

- a **picker** (`Forms\Components\RequestScopePicker`), a tree of the fields of the record, fed by
  the host through `Contracts\ProvidesRequestScopeTree` and already limited to the whitelist and to
  what the answering side will see;
- an **upload** of documents, kept by the host through `Contracts\StoresRequestAttachments`
  (`store`, `discard`, `documents`). The package never touches a disk.

When nothing is bound, the dialog simply offers neither. The pick travels in the payload under the
reserved key `Support\RequestScopeRecorder::PAYLOAD_KEY` (`_request_scope`), is checked against the
declaration wherever the transition runs from, is removed before anything is written, and is
recorded in the history together with a **snapshot** of the picked fields.

## What the applicant gets

While a request is open, **and** the record is still in the state it sent it to,
`Support\RequestScopeResolver` (the `Contracts\ResolvesRequestScope` binding) answers which paths
are open to the current user. The overlay (`Support\RequestScopeOverlay`) sits in
`ReadsFieldPermissions` **after** the permission cache, so the cache stays valid for every record:

| Condition | Effect |
|---|---|
| no open request with a scope, or the record has left the state | the rules of the state |
| path inside the pick, role in `answeredBy` | visible, writable, unlocked |
| path outside the pick, `exclusive` | read-only |
| path outside the pick, `additive` | unchanged |
| path outside the whitelist | never unlocked, whatever a payload says |
| several open requests | the **union** of their picks |

A rule on an ancestor covers its descendants. Rows of a nested list have no scope of their own:
the pick is on fields and columns.

## Reading it back

`Support\OpenRequest` carries the pick (`scope`, `hasScope()`, `attachmentIds()`,
`requiresChange()`, `unchangedPaths()`), read from the history row already loaded.

- **The record page.** `OpenRequestsEntry` shows the note, the term and the documents; the host
  draws the list of fields where it likes. `OpenRequestsEntry::getExchangeRecap()` and
  `scopeLabelsFor()` feed a **recap** folded by default — one line per exchange with its note,
  fields, documents and answer — which appears once something has been answered. The labels come
  from `Support\RequestScopeLabels`, which asks the host (`HasFieldPresentation`, `HasFieldLabels`)
  before the generic presenter.
- **The history.** `Infolists\Components\TransitionTimeline::getRequestScope()` shows, on the entry
  that opened a request, the fields the office picked and its documents.

## Answering

With `requireChange()` the transition that answers (`answersRequest()`) is refused while every
picked field still holds the value it had when the request was made. An empty value and a cleared
field count the same, and lists compare in a canonical order, so a field put back to what it was
does not count as a change.

## What it is not

- **Not a new store.** The pick is a part of the history; the only files are the ones the host
  keeps through `StoresRequestAttachments`.
- **Not a role system.** It narrows what the state already lets a role do and never widens beyond
  the whitelist.
- **Not per-row.** A single row of a table or repeater cannot be picked.
