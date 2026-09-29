# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-09-29

### Changed
- The handover of a record is the work of the engine, not of a form: the new
  `Services\OwnershipTransfer` moves the owner, gives the previous holder what a retention asks for,
  writes the handover down and raises `WorkflowOwnerChanged`. The panel of the assignments is one
  caller; a command, a job or an import is another. `RecordOwner` gained `exists()`, so a panel can
  tell whether a record carries the owner column at all.
- The clauses of "who may see what" are written once, in `Support\AccessibleStatesScope`: the engine
  (`ScopesAccessibleRecords`) and a host that keeps a list of its own call the same sentences — the
  two had drifted apart once, and the rows an assignment handed a person were missing from the list.
- A person as the panels and the columns show them — name, initials, the words of the host for their
  roles — is built in one place (`Support\UserSummary`) instead of five.
- The descriptions the formula editor shows beside a variable are **translated** now
  (`filament-flow::messages.formula_*`): they were written in the language of one office, in a
  package that ships to everybody.

### Added
- The refusal, at last: an assignment's access override is fully **three-valued** — `null` lets the
  call decide, `true` opens the record whatever the call says, `false` shuts the person out even
  where the call would open the door. The denial holds over the rules of the state, over a grant
  of the same kind, and over every opening of the record scope (`visibleTo()` and friends); the
  assignment row answers for it with the new `hasAccessDenial()`.
- The assignment panel can now **hand the record over**: it shows who holds it (the configured
  `state_access.owner_field`) and transfers it — with a choice of what the previous owner keeps:
  nothing, the eyes of an observer (`viewer` + view override) or the hands of a collaborator
  (`secondary`), and an optional note written into the assignment metadata. The change raises the
  new `WorkflowOwnerChanged` event, carrying the previous and next owner, the retention and the
  note.
- The panel now **explains itself**: it carries what the panel is for — what ownership means and
  what the previous holder keeps, what the roles of the assignees are, and the three readings of
  a permission (the call decides, allowed, shut out) — folded for whoever acts and open for
  whoever may not, where it is what tells them why the settings are not theirs. Set
  `showExplanation => false` for a bare panel.
- Every handover is **written down**: the panel records it in the new `workflow_owner_changes`
  table (who held the record, who holds it now, what the previous holder kept, the note and who
  made the change), beside firing `WorkflowOwnerChanged`.
- The new `OwnerColumn` shows a record's owner — the column named by
  `state_access.owner_field` — and, beside the name, the handovers it went through: from whom,
  when, and how many, with the note and what the previous holder kept in the tooltip.
  `RecordOwner` is the seam that reads the owner for a host that needs it in its own code.
- The cells of `OwnerColumn` and `AssignmentSummaryColumn` are **part of the row**: clicking them
  follows the record link of the table, like every other column (`->disabledClick()` for a host
  that wants them inert). Both read the relations the host already loaded — a list that eager
  loads the owner or the assignments spares them a query per row.
- The history of the handovers is readable in full: the panel keeps it in a **collapsible
  section** under the ownership one (`showHistory => false` to leave it out), and
  `OwnershipHistoryEntry` brings the same story to a form, a step or an infolist. Both read
  `OwnershipHistory`, the seam a host can call on its own.
- `AccessControlAction`: the assignment panel as a **dialog** — one button to open the room where
  the hands are dealt, mountable beside a record or pointed at one with `accessRecord()` /
  `accessRecordType()`, with `roleLabels()` for the words of the host and `superAdminOnly()` for
  the hosts that keep plain admins on the reading side. The panel itself accepts the same
  `superAdminOnly` prop when embedded as a section. The component inside the dialog carries a
  key of its own, so it never collides with another panel of the same class on the page — a
  collision Livewire answers with an empty placeholder, which is a dialog that opens empty.

### Changed
- The engine now says **whether it compared at all**. `field_changes` is an array in both cases the
  comparison runs — the paths that moved, or `[]` for a save that touched nothing — and stays
  `null` only when nobody compared (the row predates the setting, or it is off). The history reads
  the difference: a save that moved nothing says **"No field changed"** instead of laying out the
  values it carried as if they had moved, and the values of an entry that was never compared read
  under a fold of their own (`showSubmittedData()`), never as changed fields.
- The engine records **what moved**: every logged transition diffs the values it carried (see the
  `field_changes` configuration) and stores the delta in
  `workflow_transition_metadata.field_changes`, as `['path' => ['from' => …, 'to' => …]]`. A map is
  opened to its leaves, a list stays whole, and a transition that moved nothing records no
  metadata at all. The timeline shows that delta instead of dumping the whole form. With
  `field_changes.payload` the payload a transition was given counts as the delta too, for the
  hosts that write their values after the transition (so a refusal leaves nothing behind). A host
  that writes **before** it says what the record held a moment earlier
  (`withFieldValuesBefore()`) — without it there is nothing left to compare, and a save that
  changed a field would look like a save that changed nothing.
- A record can now say **how its own fields read**, through the new `HasFieldPresentation`
  contract (`FieldPresentation`: label, shape — text, pairs, table, files —, group, visibility),
  beside the existing `HasFieldLabels`. The timeline asks it for both sides of a change, falls
  back to `HasFieldLabels`, and then to a new generic `DefaultFieldPresenter`; the submitted data
  of older entries is flattened path by path and the empty paths are left out
  (`hideEmptyFields()`).
- The fields of an entry are **grouped by the block they belong to**, and a section with
  something to navigate folds each group into a `<details>` carrying its name and the count of
  its fields, with *Expand all* / *Collapse all* in the heading (`collapseGroups()`, native
  markup: no script needed). The record diff and the technical traces are folded on their own.
- A path nobody claims is now named with the **words of the key, translated**: the whole path
  first (`applicant.vat_number`), then its last step, then the same headlined — the same place
  the labels of a scheme come from. And the paths a host wants out of the history leave it
  entirely, submission and changes alike, through `hideFields()` (a path, a subtree, `*`
  wildcards), counted with the empty ones.
- The `StateBadge` reads like the history does: a translated label by default, the mark its
  kind deserves when the workflow named no icon (the start, an approval that ends the run, a
  refusal, a dot in the colour of the state in between), a "Final state" chip when the record
  does not leave it, the description the call gave the state (`description()`) and a line of
  the host under the badge (`extra()`). A state the workflow does not declare reads as its
  name rather than leaving the badge empty.
- The `AssignmentSummaryEntry` reads like the history does: a translated label by default, the
  roles of the assigned people in the **words of the host** (`roleLabels()`, a map or a
  callback; the same labels reach `AssignmentManager`, `AssigneeSelect` and
  `AssignmentSummaryColumn`), when the case was given and by whose hand, the state the
  permissions are read in, and the three permissions always in sight — granted, denied, or
  granted by an override — each with its accessible name. The avatar wears the colour of the
  kind of assignment instead of a colour of its own, and the override tint is left to the badge
  and to the permission it covers.
- The `TransitionTimeline` infolist component shows the history the engine records, instead
  of a slice of it. Every entry now wears the marker (colour and icon) the workflow gave its
  destination state, an absolute locale-aware date with the relative time beside it, the
  author with their email, the time spent in the previous state, the reason as its own
  callout, the notes in full, and a per-entry "Details" fold with the submitted form data,
  the field changes (before → after), the validation errors and — when the host asks — the
  record diff from the before/after snapshots and the IP address and browser of the author.
  Entries past `limit()` wait behind a "show more" fold instead of vanishing, and the
  component carries a translated label by default. New options: `loadLimit()`,
  `expandable()`, `showMetadata()`, `showIpAddress()`, `showSnapshots()`, `dateTimeFormat()`,
  `stateAttribute()`. Administrators now truly see the whole history: rows flagged
  `is_visible = false` appear to them with a "Hidden" badge (the old query hid them from
  everyone); counts and list always answer with the same rows. The package now ships a
  tiny `timeline.css` whose only rule keeps Alpine's `x-cloak` hidden until the framework
  boots, so the folded history never flashes before collapsing.
- `HasDatabaseTransitions` (1096 → 428 lines) split into four focused traits —
  `ChecksTransitionPermissions` (who may walk a transition), `ResolvesWorkflowStates` (the
  bridge between state classes and their rows), `ChecksTransitionGuards` (payload
  validation and access enforcement) and `ResolvesWorkflowActions` (the actions available
  on a model, and running one) — leaving the trait with the transition itself. No API
  change: the hosts see exactly the same methods.
- Role resolution has one seam: `WorkflowStateAccessService` now asks the configured
  `state_access.role_resolver` (through `AccessRuleEvaluator`) instead of resolving roles
  with its own fallbacks, so transition permissions, field permissions and state access
  rules always answer the same question the same way.
- The same treatment for the rest of the large services, none of them changed in behaviour:
  `WorkflowStateAccessService` (881 → 363, `EvaluatesAccessRules` +
  `ScopesAccessibleRecords`), `NotificationService` (717 → 377,
  `FindsNotificationTargets` + `DeliversNotifications`), `WorkflowFieldPermissionsService`
  (571 → 35, `ReadsFieldPermissions` + `ReadsCreationAndColumnPermissions` +
  `ResolvesFieldPermissionContext`), `WorkflowValidationService` (482 → 136,
  `AppliesValidationRules` + `EvaluatesValidationValues` + `ResolvesValidationContext`) and
  `WorkflowPlanner` (502 → 187, `DiffsWorkflowDefinition` + `ProjectsWorkflowRows`).

### Fixed
- A refused transition now reaches the form: `WorkflowValidationException` carries a
  validator with the engine messages (an empty one made the framework drop them),
  and `StateAction` can flash them and come back to the page
  (`ui.reload_form_on_validation_failure`).

### Fixed
- The notification configuration declared in `config/filament-flow.php` is now
  actually honoured, instead of only documented:
  `notifications.queue_connection` / `queue_name` / `retry_attempts` /
  `retry_backoff` drive `SendWorkflowNotification` (which no longer hardcodes
  3 tries / 60s backoff), `notifications.logging_enabled` switches off the
  delivery log, `notifications.channels.*.enabled` skips a disabled channel
  type, `notifications.default_delay_minutes` is used when a delayed
  notification does not set its own delay, `notifications.channels.mail.from_address`
  / `from_name` set the mail sender, and `notifications.default_channel` /
  `default_template_engine` are the defaults of the notification builder and of
  the channel form.
- Roles are resolved the same way on every authorization path: transition
  permissions (`HasDatabaseTransitions::checkRolePermission()`) and the
  `TransitionTimeline` / `AssignmentSummaryEntry` super-admin checks now go
  through the configured `state_access.role_resolver` instead of calling the
  model's own `hasAnyRole()`/`role` attribute directly. A host that keeps
  super admins or tenant roles outside the user model is no longer treated
  differently by the access rules and by the UI.
- Removed the `use_form_builder_helper` config key: it was documented but never
  read (the advanced form builder is the only implementation).

### Added
- **Workflow validation engine** (`WorkflowValidationService`): one pass that
  composes the field permissions of the state (visible, readonly, locked,
  **required**, validation rules), the rules declared on the transition, the host
  field rules (`FieldRuleSource`) and expression rules evaluated against the live
  form state. Errors come back keyed by form path, with labels, in
  `ValidationResult`.
- **Transitions are validated** wherever they run: `transitionTo()`,
  `executeAction()` and their callers throw `WorkflowValidationException` (a
  `ValidationException`) before writing anything. `forceTransitionTo()` is the
  documented escape hatch, `filament-flow.validation.enabled` turns the pass off.
- `ValidationRule` gained `type()` (laravel / registry / expression),
  `when()` (conditional validation), `expression()`, `label()` and
  `fieldLabel()`: a field can now carry several rule entries with different
  conditions and messages.
- `ValidationRuleRegistry` moved into the package (host applications register
  their own named rules once, for both the UI and the engine).
- `<x-filament-flow::validation-summary />`: a side drawer listing every error of
  the current form, grouped and clickable, including the errors on paths that have
  no component (virtual keys).
- `StateBulkActionGroup::forDatabaseRecord()`: bulk transitions for database-first
  workflows (string states), with per-record validation and a report of the
  failures.

### Fixed
- `WorkflowValidationException::getMessage()` now carries the rule failures
  (`review.score: The score must be at least 60.`) instead of the generic
  "The given data was invalid.", so logs, API responses and the simulator
  transcript are readable.

### Changed
- Static analysis raised from **PHPStan level 1 with a 12-entry baseline to level 5
  with no baseline**: model relations now carry generics
  (`@return HasMany<WorkflowStateField, $this>`), models declare their columns and
  relations (`@property` / `@property-read`), and the services type the collections
  and models they consume. The only remaining ignore is the documented
  `trait.unused` for the host-facing traits in `src/Concerns`.
- `StateAction` validates through the engine instead of keeping its own copy of
  the transition rules: the messages are attached to the components, the ones
  without a component are listed in the notification, and the page is refreshed
  with Livewire instead of being reloaded (`ui.reload_after_transition` restores
  the reload).
- The validation rules of a transition are no longer unique per field: a field can
  carry a Laravel rule and an expression rule at the same time.
- `StateBulkActionGroup::forDatabaseRecord` and the validation engine are covered
  by tests; the fixture workflows now fill the fields their states require.
- `HasDatabaseTransitions` (1201 lines) split by concern: the transition history
  (`logTransition()`, `extractTransitionNotes()`) moved to `LogsTransitionHistory`
  and the notification trigger to `TriggersTransitionNotifications`, both composed
  into the trait. No public API change.
- Code shared by several components moved to concerns: `ParsesStateCast`
  (`extractStateClass()`) and `HasRelationManagerForm` (the single-column
  relation-manager form), `ResolvesUserModel` (the host user model, previously
  duplicated in four models).

### Fixed
- `FilamentFlow::getStates()` called a non-existent `StateService::getAllStates()`
  and `FilamentFlow::canAccess()` called the protected `checkAccess()` with the
  arguments in the wrong order: both public helpers of the facade were fatal
  errors. They now go through the public API
  (`getAllStatesForModel()`, `checkAccess()` promoted to public).
- A **delayed code-first notification crashed the queue worker**: the job was
  dispatched with a `null` config id into an `int` typed constructor argument.
  The id is now nullable, the job skips the configuration lookup and dispatches
  the prepared payload (`NotificationService::sendPreparedNotification()`).
- `HasStateOptions` called `$this->getModel()` when a table column had no record,
  which no state column implements: the model is now resolved from the component
  (field model or table model) or the option list is empty.

### Added
- `Support\CanonicalJson`: canonical JSON comparison shared by the planners
  (`encode()`, `encodeOptional()`, `canonicalize()`). Array keys are sorted
  recursively so PostgreSQL `jsonb` reordering never produces a false "changed"
  result; `null` and `[]` stay equivalent for optional values.

### Fixed
- State aware components resolve the workflow of the **owner (tenant)** of the
  record: `StateService::getAllStatesForModel()`/`getStateMetadata()` accept a
  tenant, `StateTabs` gained `tenant()` and resolves it from a model instance,
  `StateSelectFilter` gained `tenant()` and `StateColumn` reads it from the
  record. Hosts that keep one workflow per owner no longer get empty state
  options, tabs or metadata.
- Access rules and field permissions now resolve roles the same way: the
  configured `state_access.role_resolver` is always honoured
  (`DefaultRoleResolver::hasAnyRole()`/`hasAllRoles()` read `getRoles()` instead
  of shortcutting to the model's own role checker).

### Added
- `StateTabs::data()`: the tabs as plain arrays (`state`, `label`, `color`,
  `icon`, `count`) for hosts that render the tabs themselves, and the tab `key`
  is now set so the state can be read back.
- `WorkflowStateAccessService::canCreate()` accepts the owner of the scoped
  workflow (`canCreate($modelClass, $user, $tenantId)`), so hosts with one
  workflow per owner can check create access for that owner.
- Role overrides in the Definition SDK: `StateField::forRole($role, Closure)` refines
  a per-state field rule for a role (`RoleOverride`); only the declared attributes
  are persisted. They are written by `WorkflowApplier`, diffed by `WorkflowPlanner`
  (`updated_state_fields`, `Safe`), restored by `StateField::fromArray()` and
  included in the revision snapshot.
- Nested field paths in state field rules: `WorkflowFieldPermissionsService::permissionFor()`
  resolves dotted paths (a rule on `costs` also covers `costs.amount`, the most
  specific rule wins attribute by attribute, role overrides are applied after
  every base rule of the chain). `HasStateAccess::isFieldVisible()` and
  `isFieldReadonly()` accept dotted paths.
- Typed Workflow Definition SDK (`RoBYCoNTe\FilamentFlow\Definition`):
  `WorkflowDefinition`, `State`, `StateField`, `Transition`, `SideEffect`,
  `ValidationRule` and the `Visibility`, `Mutability`, `SideEffectType` enums,
  with `toArray()`/`fromArray()` round-trip.
- Workflow planning layer (`RoBYCoNTe\FilamentFlow\Definition\Planning`):
  `WorkflowPlanner`, `WorkflowChangePlan`, `WorkflowChange`, `PlanOptions`,
  `MutationClass` and the `WorkflowConflictException`.
- Workflow applier and revisions: `WorkflowApplier` (model-agnostic, transactional),
  `WorkflowSnapshotService`, the `workflows.schema_version` column and the
  `workflow_snapshots` table.
- Typed scheduled checks, access rules and notifications in the Definition SDK:
  `ScheduledCheck`, `AccessRule`, `Notification`, `Recipient` plus the
  `ScheduledCheckCondition`, `ScheduledCheckAction`, `ScheduledCheckFrequency`,
  `AccessType`, `AccessOperator`, `NotificationTrigger`, `NotificationTiming`,
  `NotificationPriority`, `NotificationChannel` and `RecipientType` enums.
  Attachable to `WorkflowDefinition`, `State` and `Transition`, with
  `toArray()`/`fromArray()` round-trip.
- `WorkflowPlanner` detects `added_*` / `updated_*` / `removed_*` changes for
  scheduled checks, access rules and notifications (removals are breaking);
  `WorkflowApplier` reconciles them (recipients, channels and one template per
  channel), and `WorkflowSnapshotService` includes them in revisions.
- `ScheduledCheckRunner` resolves `notification_name` / `transition_name`
  references, so definitions stay valid across revisions.
- `WorkflowApplier::apply()` and `WorkflowSnapshotService::snapshot()` accept an
  optional explicit revision version, so a host can align a workflow revision with
  its own version counter (e.g. a scheme version).

### Removed
- Unused, untyped `Configuration\WorkflowConfiguration`, `StateConfiguration`,
  `TransitionConfiguration` and `FieldConfiguration` (dead code, replaced by the
  typed Definition SDK).

## [0.1.1] - 2026-05-20

### Changed
- Include changelog content directly in GitHub Release body instead of auto-generated notes

## [0.1.0] - 2026-05-20

### Added
- Initial release of Filament Flow
- Workflow management integration with Spatie Laravel Model States
- Visual state transition UI components for Filament
- Database-driven workflow definitions
- State access control
- Workflow notifications
- Custom forms for state transitions
- Filament panel integration via `HasFilamentFlow` trait
- Configuration publishing via `filament-flow:install`
- Documentation site with guides and API reference
