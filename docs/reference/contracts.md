# Contracts & Interfaces

All contracts live in the `RoBYCoNTe\FilamentFlow\Contracts` namespace.

## Implement in your State classes

### HasAccessRules

Defines who can create, view, edit, or transition a record while it is in that state. Rules are evaluated with OR logic — access is granted when the user satisfies at least one rule token.

Available rule tokens:

| Token | Meaning |
|---|---|
| `*` | Everyone, including guests |
| `@authenticated` | Any authenticated user |
| `@owner` | Record owner (field determined by `state_access.owner_field` config) |
| `@assigned` | Any user assigned to the record |
| `@assigned:type` | User assigned with a specific type, e.g. `@assigned:primary` |
| `role:name` | User with a specific role |
| `role:name1,name2` | User with any of the listed roles |
| `permission:name` | User with a specific permission |

`getCreateAccessRules()` only applies to the **initial** state. The `@owner` and `@assigned` tokens have no effect there since the record does not exist yet.

```php
use RoBYCoNTe\FilamentFlow\Contracts\HasAccessRules;

final class PendingState extends OrderState implements HasAccessRules
{
    public static function getCreateAccessRules(): array
    {
        return ['role:sales,admin'];
    }

    public static function getViewAccessRules(): array
    {
        return ['@authenticated'];
    }

    public static function getEditAccessRules(): array
    {
        return ['@owner', '@assigned:primary'];
    }

    public static function getTransitionAccessRules(): array
    {
        return ['role:manager,admin'];
    }
}
```

### HasStateNotifications

Defines notifications to dispatch when the record enters or exits the state. Both methods return an array of `WorkflowNotificationBuilder` instances.

```php
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateNotifications;

final class ProcessingState extends OrderState implements HasStateNotifications
{
    public function onEnterNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@assigned', 'role:manager'])
                ->title('Order moved to Processing')
                ->body('Order #{{record.id}} is now being processed.')
                ->immediate(),
        ];
    }

    public function onExitNotifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('mail')
                ->recipients(['@owner'])
                ->subject('Your order has moved on')
                ->body('Your order #{{record.id}} has left the processing stage.')
                ->immediate(),
        ];
    }
}
```

### HasStateSortOrder

Provides a custom sort position for the state. Lower values appear first. Used by `StateSelectColumn`, `StateColumn`, and `StateGroup` when sorting.

```php
use RoBYCoNTe\FilamentFlow\Contracts\HasStateSortOrder;

final class PendingState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 1;
    }
}

final class ProcessingState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 2;
    }
}

final class CompletedState extends OrderState implements HasStateSortOrder
{
    public static function getSortOrder(): int
    {
        return 3;
    }
}
```

### HasStateMetadata

Combines Filament's `HasColor`, `HasDescription`, `HasIcon`, and `HasLabel` contracts. Implement all four methods to give the state a display label, icon, colour, and description used throughout Filament components.

```php
use Filament\Support\Colors\Color;
use Filament\Support\Enums\IconSize;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateMetadata;

final class CompletedState extends OrderState implements HasStateMetadata
{
    public function getColor(): string|array|null
    {
        return Color::Green;
    }

    public function getDescription(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'The order has been fulfilled and closed.';
    }

    public function getIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-check-circle';
    }

    public function getLabel(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'Completed';
    }
}
```

## Implement in your Transition classes

### HasTransitionNotifications

Defines notifications to dispatch when the transition is executed. Returns an array of `WorkflowNotificationBuilder` instances.

```php
use RoBYCoNTe\FilamentFlow\Builders\WorkflowNotificationBuilder;
use RoBYCoNTe\FilamentFlow\Contracts\HasTransitionNotifications;
use Spatie\ModelStates\Transition;

class ApproveOrderTransition extends Transition implements HasTransitionNotifications
{
    public function __construct(private readonly Order $order) {}

    public function handle(): Order
    {
        $this->order->state->transitionTo(ProcessingState::class);

        return $this->order;
    }

    public function notifications(): array
    {
        return [
            WorkflowNotificationBuilder::make()
                ->channel('database')
                ->recipients(['@owner'])
                ->title('Order Approved')
                ->body('Your order #{{record.id}} has been approved.')
                ->immediate(),

            WorkflowNotificationBuilder::make()
                ->channel('mail')
                ->recipients(['role:warehouse'])
                ->subject('New order ready for fulfilment')
                ->body('Order #{{record.id}} is ready to be dispatched.')
                ->delay(5),
        ];
    }
}
```

## Implement for custom integration

### RoleResolver

Customize how user roles are resolved. Useful when integrating with Spatie Permission, Bouncer, or a bespoke role system.

Register in config:

```php
// config/filament-flow.php
'state_access' => [
    'role_resolver' => \App\Services\MyRoleResolver::class,
],
```

Full interface:

```php
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\RoleResolver;

class MyRoleResolver implements RoleResolver
{
    public function getRoles(Model $user): array
    {
        return $user->roles->pluck('slug')->toArray();
    }

    public function hasAnyRole(Model $user, array $roles): bool
    {
        return count(array_intersect($this->getRoles($user), $roles)) > 0;
    }

    public function hasAllRoles(Model $user, array $roles): bool
    {
        return count(array_intersect($this->getRoles($user), $roles)) === count($roles);
    }

    public function isSuperAdmin(Model $user): bool
    {
        return in_array('super_admin', $this->getRoles($user), true);
    }
}
```

See [Access Control](/workflows/access-control) for more details on registering resolvers.

### PermissionResolver

Customize how permissions are checked against users. Supports optional record context for policies that depend on the record being accessed.

Register in config:

```php
// config/filament-flow.php
'state_access' => [
    'permission_resolver' => \App\Services\MyPermissionResolver::class,
],
```

Full interface:

```php
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\PermissionResolver;

class MyPermissionResolver implements PermissionResolver
{
    public function hasPermission(Model $user, string $permission, ?Model $record = null): bool
    {
        return $user->can($permission, $record ?? $permission);
    }

    public function hasAnyPermission(Model $user, array $permissions, ?Model $record = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission, $record)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(Model $user, array $permissions, ?Model $record = null): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($user, $permission, $record)) {
                return false;
            }
        }

        return true;
    }
}
```

See [Access Control](/workflows/access-control) for full registration and usage details.
