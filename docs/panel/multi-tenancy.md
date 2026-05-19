# Multi-Tenancy

Filament Flow supports both **global workflows** (shared across all tenants) and **tenant-specific workflows** (each tenant manages their own).

## Global Workflows (Default)

By default, workflows are global and not scoped to any tenant. This is ideal when workflows define application-wide business logic:

```php
FilamentFlowPlugin::make()
    ->global() // Explicit, but this is the default
```

## Tenant-Aware Workflows

Enable tenant-aware mode when each tenant should be able to customize or create their own workflows:

```php
FilamentFlowPlugin::make()
    ->tenantAware()
    ->tenantModel(Company::class)      // Optional: override config
    ->tenantColumn('company_id')       // Optional: override config
```

You can also configure this in the config file:

```php
// config/filament-flow.php
return [
    'tenant_model' => App\Models\Company::class,
    'tenant_foreign_key' => 'tenant_id',
];
```

## Workflow Resolution with Fallback

When tenant-aware mode is enabled, Filament Flow uses a **fallback strategy** to find workflows:

1. **First**: Look for a tenant-specific workflow (matching the current tenant)
2. **Fallback**: If not found, use the global workflow (`tenant_id = null`)

This allows you to:
- Define global "base" workflows that apply to all tenants
- Let specific tenants override with their own customized workflows

**Example**: A global "Order Processing" workflow applies to all companies, but Company A can create their own version with additional states.

## Configuration Reference

```php
// config/filament-flow.php

/**
 * The tenant model class for multi-tenancy support.
 * Set to null to disable multi-tenancy.
 *
 * Example: App\Models\Company::class, App\Models\Tenant::class
 */
'tenant_model' => null,

/**
 * The foreign key column name for tenant relationship.
 * This will be used in the workflows table.
 */
'tenant_foreign_key' => 'tenant_id',
```

**Use Case:** If your application has multiple tenants (e.g., companies, organizations), you can configure workflows per tenant. Each tenant can have their own workflow definitions in the database.

## Custom Role Resolver for Tenant-Aware Roles

When using multi-tenancy with per-company roles (e.g., a `collaborator` role stored in the `company_user` pivot), extend `DefaultRoleResolver` to include the pivot role:

```php
use RoBYCoNTe\FilamentFlow\Support\DefaultRoleResolver;

class TenantAwareRoleResolver extends DefaultRoleResolver
{
    public function getRoles(Model $user): array
    {
        $roles = parent::getRoles($user);

        $company = $user->currentCompany;
        if ($company) {
            $pivotRole = $user->employeeships()
                ->where('company_id', $company->id)
                ->value('role');
            if ($pivotRole) {
                $roles[] = $pivotRole;
            }
        }

        return array_unique($roles);
    }
}
```

Register it in config:

```php
// config/filament-flow.php
'state_access' => [
    'role_resolver' => \App\Support\TenantAwareRoleResolver::class,
],
```

This resolver is used by **both** `AccessRuleEvaluator` (for state access rules) and `WorkflowFieldPermissionsService` (for field permissions), ensuring consistent role resolution across the entire workflow system.
