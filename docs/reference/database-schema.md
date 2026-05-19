# Database Schema

Filament Flow creates the following tables. Run `php artisan migrate` after installing or updating the package.

## workflows

Stores one workflow definition per model class (and optionally per tenant).

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `tenant_id` | bigint unsigned | Yes | Foreign key to the tenant model; `null` for global workflows |
| `name` | varchar | No | Human-readable workflow name |
| `model_type` | varchar | No | Fully-qualified model class name |
| `state_column` | varchar | No | Column on the model that holds the state value (default: `state`) |
| `is_active` | boolean | No | Whether this workflow is active (default: `true`) |
| `creation_policy` | json | Yes | Options for record creation, e.g. `auto_assign_creator`, `assignment_type` |
| `metadata` | json | Yes | Arbitrary extra data |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

Unique index on `(tenant_id, model_type, state_column)`.

## workflow_states

One row per state within a workflow. Both PHP-backed and database-only states are stored here.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `workflow_id` | bigint unsigned | No | Foreign key to `workflows` |
| `name` | varchar | No | State identifier (e.g. `pending`, class name for PHP states) |
| `label` | varchar | No | Display label shown in the UI |
| `class_name` | varchar | Yes | Fully-qualified PHP State class; `null` for database-only states |
| `color` | varchar | No | Filament colour token (default: `gray`) |
| `icon` | varchar | Yes | Heroicon identifier |
| `description` | text | Yes | Optional description shown in tooltips / infolists |
| `sort_order` | integer | No | Display order; lower values appear first (default: `999`) |
| `is_initial` | boolean | No | Whether records start in this state (default: `false`) |
| `is_final` | boolean | No | Whether this is a terminal state (default: `false`) |
| `metadata` | json | Yes | Arbitrary extra data |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

Unique index on `(workflow_id, name)`.

## workflow_transitions

Defines allowed state-to-state transitions within a workflow.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `workflow_id` | bigint unsigned | No | Foreign key to `workflows` |
| `from_state_id` | bigint unsigned | Yes | Source state; `null` means "from any state" |
| `to_state_id` | bigint unsigned | Yes | Target state |
| `name` | varchar | No | Machine-readable transition name |
| `label` | varchar | No | Display label for action buttons |
| `description` | text | Yes | Optional description |
| `class_name` | varchar | Yes | Fully-qualified Spatie Transition class to execute |
| `requires_confirmation` | boolean | No | Show a confirmation dialog before executing (default: `false`) |
| `requires_reason` | boolean | No | Require the user to provide a reason (default: `false`) |
| `conditions` | json | Yes | Additional condition configuration |
| `metadata` | json | Yes | Arbitrary extra data |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

Unique index on `(workflow_id, from_state_id, to_state_id, name)`.

## workflow_transition_fields

Fields displayed in the form when a transition is executed.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `transition_id` | bigint unsigned | No | Foreign key to `workflow_transitions` |
| `field_name` | varchar | No | Form field identifier |
| `field_type` | varchar | No | Filament field type (e.g. `text`, `textarea`, `select`) |
| `label` | varchar | No | Display label |
| `model_attribute` | varchar | Yes | Model attribute to map the field value to |
| `mapping_type` | enum | No | How the value is applied: `direct`, `transform`, `computed`, `relationship`, `assignment`, `custom`, `ignore` (default: `direct`) |
| `mapping_config` | json | Yes | Extra config for the mapping strategy |
| `is_required` | boolean | No | Whether the field is required (default: `false`) |
| `validation_rules` | json | Yes | Laravel validation rules array |
| `custom_validation_class` | varchar | Yes | Class implementing custom validation |
| `sort_order` | integer | No | Display order (default: `0`) |
| `field_config` | json | Yes | Additional Filament field options |
| `save_to_model` | boolean | No | Whether the value is persisted to the model (default: `true`) |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_transition_permissions

Per-transition permission rules that restrict which users may execute a specific transition.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `transition_id` | bigint unsigned | No | Foreign key to `workflow_transitions` |
| `permission_type` | enum | No | `role`, `assignment`, `custom` |
| `permission_value` | varchar | Yes | Role name(s) or custom value depending on type |
| `require_all` | boolean | No | If `true`, all rules must pass (AND); otherwise any rule suffices (OR) (default: `false`) |
| `metadata` | json | Yes | Arbitrary extra data |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_transition_validation_rules

Field-level validation rules applied when a transition form is submitted.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `transition_id` | bigint unsigned | No | Foreign key to `workflow_transitions` |
| `field_name` | varchar | No | The form field this rule applies to |
| `rules` | json | No | Laravel validation rules array |
| `custom_message` | varchar | Yes | Custom validation error message |
| `sort_order` | integer | No | Evaluation order (default: `0`) |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

Unique index on `(transition_id, field_name)`.

## workflow_state_fields

Field-level visibility and mutability configuration per state.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `state_id` | bigint unsigned | No | Foreign key to `workflow_states` |
| `field_name` | varchar | No | Model or form field identifier |
| `visibility` | enum | No | `visible` or `hidden` (default: `visible`) |
| `mutability` | enum | No | `readonly`, `editable`, or `locked` (default: `editable`) |
| `is_required` | boolean | No | Whether the field is required in this state (default: `false`) |
| `sort_order` | integer | No | Display order (default: `0`) |
| `validation_rules` | json | Yes | State-specific validation rules |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

Unique index on `(state_id, field_name)`.

## workflow_state_field_roles

Role-specific overrides for a `workflow_state_fields` entry. Allows certain roles to see or edit fields that are hidden/locked for everyone else.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `state_field_id` | bigint unsigned | No | Foreign key to `workflow_state_fields` |
| `role_name` | varchar | No | Role name to match; also accepts virtual tokens `@owner`, `@assigned`, `@assigned:type` |
| `visibility` | enum | Yes | Override value: `visible` or `hidden`; `null` means no override |
| `mutability` | enum | Yes | Override value: `readonly`, `editable`, or `locked`; `null` means no override |
| `is_required` | boolean | Yes | Override required flag; `null` means no override |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_state_visibility

High-level visibility configuration for a state (who can see records in this state).

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `state_id` | bigint unsigned | No | Foreign key to `workflow_states` |
| `visibility_type` | enum | No | `roles`, `assignment`, `public`, `custom` |
| `visibility_config` | json | Yes | Type-specific configuration |
| `allow_admin_override` | boolean | No | Admins can always see records regardless of rules (default: `true`) |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_state_access_rules

Fine-grained access rule tokens for a state, evaluated by `WorkflowStateAccessService`.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `state_id` | bigint unsigned | No | Foreign key to `workflow_states` |
| `access_type` | enum | No | `view`, `edit`, `transition`, `create` |
| `rule` | varchar | No | Rule token (same syntax as Code-First tokens: `@authenticated`, `role:name`, etc.) |
| `operator` | enum | No | `or` (any rule passes) or `and` (all rules must pass) (default: `or`) |
| `priority` | integer | No | Evaluation priority; lower values are evaluated first (default: `0`) |
| `is_active` | boolean | No | Whether this rule is active (default: `true`) |
| `metadata` | json | Yes | Arbitrary extra data |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_assignments

Records a user's assignment to a specific model record with access override flags.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `assignable_type` | varchar | No | Polymorphic model class (morph type) |
| `assignable_id` | bigint unsigned | No | Polymorphic model ID |
| `user_id` | bigint unsigned | No | Foreign key to `users` |
| `assignment_type` | enum | No | `primary`, `secondary`, `viewer` (default: `primary`) |
| `assigned_at` | timestamp | No | When the assignment was created (default: current time) |
| `assigned_by` | bigint unsigned | Yes | Foreign key to `users`; who made the assignment |
| `metadata` | json | Yes | Arbitrary extra data |
| `override_view` | boolean | Yes | `true` grants view access regardless of state rules; `null` follows state rules |
| `override_edit` | boolean | Yes | `true` grants edit access regardless of state rules; `null` follows state rules |
| `override_transition` | boolean | Yes | `true` grants transition access regardless of state rules; `null` follows state rules |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

Unique index on `(assignable_type, assignable_id, user_id, assignment_type)`.

## workflow_notifications

Top-level notification configuration tied to a workflow event.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `workflow_id` | bigint unsigned | No | Foreign key to `workflows` |
| `transition_id` | bigint unsigned | Yes | Scope to a specific transition (for `on_transition` trigger) |
| `state_id` | bigint unsigned | Yes | Scope to a specific state (for `on_state_enter` / `on_state_exit` triggers) |
| `trigger_event` | enum | No | `on_transition`, `on_state_enter`, `on_state_exit`, `on_assignment`, `on_field_change` (default: `on_transition`) |
| `name` | varchar | No | Internal name for this notification configuration |
| `description` | text | Yes | Optional description |
| `is_active` | boolean | No | Whether this notification is active (default: `true`) |
| `timing` | enum | No | `immediate` or `delayed` (default: `immediate`) |
| `delay_minutes` | integer | Yes | Minutes to delay dispatch when `timing = delayed` |
| `priority` | enum | No | `low`, `medium`, `high`, `urgent` (default: `medium`) |
| `metadata` | json | Yes | Arbitrary extra data |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_notification_recipients

Defines who receives a notification. Multiple rows per notification, each describing a different recipient strategy.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `notification_id` | bigint unsigned | No | Foreign key to `workflow_notifications` |
| `recipient_type` | enum | No | `role`, `user`, `trigger_user`, `assigned_users`, `record_owner`, `state_actors`, `all_involved`, `involvement_type`, `custom_field`, `custom_query`, `custom_class` (default: `role`) |
| `recipient_config` | json | Yes | Type-specific configuration (e.g. `{"role": "manager"}`) |
| `sort_order` | integer | No | Processing order (default: `0`) |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_notification_channels

Delivery channels for a notification. Multiple channels per notification are supported.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `notification_id` | bigint unsigned | No | Foreign key to `workflow_notifications` |
| `channel_type` | enum | No | `database` or `mail` (default: `database`) |
| `channel_config` | json | Yes | Channel-specific options |
| `is_active` | boolean | No | Whether this channel is active (default: `true`) |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_notification_templates

Message templates for a specific notification + channel combination.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `notification_id` | bigint unsigned | No | Foreign key to `workflow_notifications` |
| `channel_id` | bigint unsigned | No | Foreign key to `workflow_notification_channels` |
| `subject` | varchar | Yes | Email subject line |
| `title` | text | Yes | Notification title (database channel) |
| `body` | text | No | Main message body |
| `action_text` | text | Yes | Call-to-action button label |
| `action_url` | text | Yes | Call-to-action button URL |
| `template_engine` | enum | No | `blade`, `mustache`, or `plain` (default: `plain`) |
| `variables` | json | Yes | Variable definitions and default values |
| `format` | enum | No | `html`, `markdown`, or `plain` (default: `html`) |
| `metadata` | json | Yes | Arbitrary extra data |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_notification_logs

Audit log for every notification dispatch attempt.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `notification_id` | bigint unsigned | No | Foreign key to `workflow_notifications` |
| `user_id` | bigint unsigned | Yes | Recipient user ID |
| `notifiable_type` | varchar | No | Polymorphic model class |
| `notifiable_id` | bigint unsigned | No | Polymorphic model ID |
| `channel` | varchar | No | Channel used for delivery |
| `status` | enum | No | `pending`, `sent`, `failed`, `skipped` (default: `pending`) |
| `error_message` | text | Yes | Error detail on failure |
| `payload` | json | Yes | Full notification payload at time of dispatch |
| `sent_at` | timestamp | Yes | Timestamp of successful delivery |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_state_transitions

Audit trail for every state transition that occurs on any model.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `transitionable_type` | varchar(100) | No | Model class (indexed) |
| `transitionable_id` | bigint unsigned | No | Model ID (indexed) |
| `workflow_id` | bigint unsigned | Yes | Foreign key to `workflows`; `null` if workflow was deleted |
| `transition_id` | bigint unsigned | Yes | Foreign key to `workflow_transitions`; `null` if deleted |
| `from_state` | varchar(150) | Yes | State class or name before transition |
| `to_state` | varchar(150) | No | State class or name after transition |
| `from_state_label` | varchar(100) | Yes | Human-readable label of the source state at time of transition |
| `to_state_label` | varchar(100) | No | Human-readable label of the target state at time of transition |
| `user_id` | bigint unsigned | Yes | User who triggered the transition |
| `user_name` | varchar(150) | Yes | Snapshot of user name at time of transition |
| `user_email` | varchar(150) | Yes | Snapshot of user email at time of transition |
| `ip_address` | varchar(45) | Yes | IP address of the request |
| `user_agent` | varchar(255) | Yes | User-agent string of the request |
| `reason` | text | Yes | Reason provided by the user |
| `notes` | text | Yes | Free-form notes captured from transition form |
| `created_at` | timestamp | No | Transition timestamp (default: current time) |
| `duration_seconds` | integer unsigned | Yes | Time spent in the previous state |
| `has_metadata` | boolean | No | Whether a `workflow_transition_metadata` row exists (indexed) |
| `has_snapshot` | boolean | No | Whether `workflow_transition_snapshots` rows exist (indexed) |
| `is_visible` | boolean | No | Whether this entry is visible in the audit UI (default: `true`) |

## workflow_transition_metadata

Extended metadata for a transition history entry. Created only when metadata is available.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `transition_history_id` | bigint unsigned | No | Foreign key to `workflow_state_transitions` |
| `form_data` | json | Yes | Raw form submission data |
| `field_changes` | json | Yes | Before/after values for changed fields |
| `validation_errors` | json | Yes | Any validation errors encountered |
| `rules_evaluated` | json | Yes | Access rules evaluated during the transition |
| `related_changes` | json | Yes | Changes to related models |
| `custom_data` | json | Yes | Arbitrary custom data from transition classes |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_transition_snapshots

Point-in-time record snapshots taken before and/or after a transition.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `transition_history_id` | bigint unsigned | No | Foreign key to `workflow_state_transitions` |
| `snapshot_type` | enum | No | `before` or `after` (default: `after`) |
| `record_data` | json | No | Serialised model attributes at snapshot time |
| `related_data` | json | Yes | Serialised related model data |
| `is_compressed` | boolean | No | Whether `record_data` is compressed (default: `false`) |
| `created_at` | timestamp | No | Snapshot timestamp (default: current time) |

## workflow_user_involvement

Tracks which users have been involved with a record and in what capacity.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `model_type` | varchar(100) | No | Model class |
| `model_id` | bigint unsigned | No | Model ID |
| `user_id` | bigint unsigned | No | Foreign key to `users` |
| `involvement_type` | varchar | No | Type of involvement (e.g. `transitioned`, `assigned`, `commented`) |
| `state` | varchar(150) | Yes | State at time of involvement |
| `first_involved_at` | timestamp | Yes | Timestamp of first involvement |
| `last_involved_at` | timestamp | Yes | Timestamp of most recent involvement |
| `involvement_count` | integer unsigned | No | Number of involvement events (default: `1`) |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

Unique index on `(model_type, model_id, user_id, involvement_type, state)`.

## workflow_transition_side_effects

Declarative side effects to apply to a model's attributes when a transition executes.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `transition_id` | bigint unsigned | No | Foreign key to `workflow_transitions` |
| `effect_type` | enum | No | `set_field`, `set_timestamp`, `clear_field`, `increment`, `custom_class` |
| `field_name` | varchar | No | Model attribute to modify |
| `value_expression` | varchar | Yes | Value or expression to apply (depends on `effect_type`) |
| `sort_order` | integer | No | Execution order (default: `0`) |
| `is_active` | boolean | No | Whether this effect is active (default: `true`) |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_scheduled_checks

Time-based checks that evaluate conditions on records and fire notifications, transitions, or side effects.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `workflow_id` | bigint unsigned | No | Foreign key to `workflows` |
| `name` | varchar | No | Internal name for this check |
| `description` | text | Yes | Optional description |
| `state_id` | bigint unsigned | Yes | Restrict check to records in this state |
| `condition_type` | enum | No | `date_offset`, `field_compare`, `custom_class` |
| `condition_config` | json | No | Condition parameters (e.g. `{"field": "created_at", "offset": 3, "unit": "days"}`) |
| `action_type` | enum | No | `notification`, `transition`, `side_effect` |
| `action_config` | json | No | Action parameters (e.g. `{"notification_id": 5}`) |
| `frequency` | enum | No | `every_minute`, `every_five_minutes`, `hourly`, `daily`, `weekly` (default: `daily`) |
| `once_per_record` | boolean | No | Execute at most once per record (default: `false`) |
| `is_active` | boolean | No | Whether this check is active (default: `true`) |
| `last_checked_at` | timestamp | Yes | Last time the scheduler ran this check |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

## workflow_scheduled_check_logs

Execution log for each scheduled check run against a specific model record.

| Column | Type | Nullable | Description |
|---|---|---|---|
| `id` | bigint unsigned | No | Primary key |
| `check_id` | bigint unsigned | No | Foreign key to `workflow_scheduled_checks` |
| `model_type` | varchar(100) | No | Model class |
| `model_id` | bigint unsigned | No | Model ID |
| `result` | enum | No | `triggered`, `skipped`, `already_executed`, `error` |
| `metadata` | json | Yes | Additional context about the execution |
| `executed_at` | timestamp | No | When the check ran (default: current time) |
