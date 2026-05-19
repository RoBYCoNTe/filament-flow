# Security Considerations

## forceTransitionTo() Bypasses Access Control

The `forceTransitionTo()` method skips all state-based access checks and assignment overrides. It is intended for system-level operations such as console commands, scheduled jobs, and background processes.

Never call `forceTransitionTo()` with data that originates from user input or HTTP requests. If a user needs to trigger a transition, use the standard `transitionTo()` path, which enforces access rules.

```php
// Safe: internal job, no user input
$order->forceTransitionTo(ShippedState::class);

// Unsafe: $request->input('state') is user-controlled
$order->forceTransitionTo($request->input('state')); // do not do this
```

## Access Override Semantics

`WorkflowAssignment` records carry a nullable boolean `access_override` column. Its three values have distinct meanings:

| Value | Meaning |
|---|---|
| `null` | Follow the state-based access rules (default) |
| `true` | Explicitly grant access regardless of state rules |
| `false` | Explicitly deny access regardless of state rules |

When building an assignment management UI, display these three options clearly to administrators. An assignment with `access_override = false` will lock a user out even if the state rules would normally grant them access.

## Super Admin Bypass

Any user with a role listed in `config('filament-flow.state_access.super_admin_roles')` bypasses all access checks unconditionally. The default value is `['super_admin']`.

Keep this list minimal in production. Adding common roles (e.g. `admin`, `manager`) to this list will silently disable access control for a large portion of your user base.

```php
'state_access' => [
    'super_admin_roles' => ['super_admin'], // keep this list short
],
```

## Restrict the Workflow Admin Resource

The `FilamentFlowPlugin` registers a Workflow admin resource that allows users to create, edit, and delete workflow definitions. Without authorization, any authenticated Filament user can modify workflow structures.

Always restrict access in production:

```php
FilamentFlowPlugin::make()
    ->authorizeUsing(fn (User $user): bool => $user->hasRole('super_admin')),
```

Without this guard, a standard user could alter transition rules, disable access controls, or create workflows that affect other tenants.

## Transition Metadata in History

Each entry in `workflow_state_transitions` stores the `ip_address` and `user_agent` of the user who triggered the transition. This data is useful for auditing but may be subject to your application's privacy policy and regional data protection regulations (e.g. GDPR).

Review whether storing IP addresses requires user consent in your jurisdiction. If so, you can omit this logging by overriding the history-writing logic or clearing the fields before the record is saved.

## Snapshot Storage

`WorkflowTransitionSnapshot` stores a full serialized copy of the record's attributes before and after each transition. For records that contain sensitive data (personal information, financial details, credentials), consider:

- Whether snapshots should be enabled for that model type.
- Whether snapshots should be encrypted at rest.
- How long snapshot data should be retained before being purged.

If snapshots are not needed, disable them per workflow in the database configuration or exclude sensitive fields from the snapshotted attributes.

## Queue Security

Notification jobs serialize model data and dispatch it to the queue backend. Ensure that your queue backend (Redis, database) is not publicly accessible and uses appropriate authentication.

For Redis-backed queues, use password authentication and consider running Redis on a private network. For database-backed queues, ensure the `jobs` table is not exposed through any API endpoint.
