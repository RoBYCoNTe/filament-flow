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
