# Installation

## Install via Composer

```bash
composer require robyconte/filament-flow
```

The package will automatically register its service provider.

## Publishing Migrations

To publish the migrations so they are visible and executable in your main application, run:

```bash
php artisan vendor:publish --provider="RoBYCoNTe\\FilamentFlow\\FilamentFlowServiceProvider" --tag="filament-flow-migrations"
```

This will copy all plugin migrations to your `database/migrations` directory.

Alternatively, if you want to load migrations automatically without publishing them, the plugin will load them automatically on every migrate command.

**Run migrations** (optional, required only for database-driven workflows):

```bash
php artisan migrate
```

This will create the following tables:

- `workflows` — Workflow definitions
- `workflow_states` — State definitions
- `workflow_transitions` — Transition configurations
- `workflow_field_permissions` — Field-level permissions
- `workflow_assignments` — User/team assignments
- `workflow_notifications` — Notification configurations
- `workflow_transition_history` — Audit trail
- `workflow_state_access_rules` — State-based access control rules

## Publishing Configuration

```bash
php artisan vendor:publish --tag="filament-flow-config"
```

This creates `config/filament-flow.php` with all configurable options. See [Configuration Options](/reference/configuration) for the full reference.

> **New to Spatie Laravel Model States?** Read their [introduction](https://spatie.be/docs/laravel-model-states/v2/01-introduction) first to understand states, transitions, and the state pattern.

## Frontend Integration (Tailwind CSS)

If your Filament panel uses a custom theme with Tailwind CSS v4, the package's
views and PHP files must be included in your CSS source scan. Add these directives
to your panel's `theme.css`:

```css
@source '../../../../vendor/robyconte/filament-flow/resources/views/**/*';
@source '../../../../vendor/robyconte/filament-flow/src/**/*.php';
```

Adjust the relative path to match the location of your `theme.css` file.
After adding the directives, rebuild your assets: `npm run build`

## Spatie Laravel Permission (Optional)

Filament Flow integrates automatically with [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
if it is installed. Role and permission checks use `hasRole()` and `hasPermission()`
from the package without any additional configuration.

If Spatie Permission is not installed, the package falls back to checking a `role`
attribute on the user model or using custom `RoleResolver`/`PermissionResolver`
implementations (see [Contracts](/reference/contracts)).
