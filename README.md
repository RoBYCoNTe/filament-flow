# Filament Flow

**A powerful Business Process Manager for Filament to handle model state transitions and workflows with ease.**

Filament Flow seamlessly integrates [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states) into your [FilamentPHP](https://filamentphp.com/) admin panel, providing a complete workflow management solution with visual state transitions, custom forms, and intuitive UI components.

## Documentation

Full documentation is available at **[robyconte.github.io/filament-flow](https://robyconte.github.io/filament-flow/)**

- [Introduction & Features](https://robyconte.github.io/filament-flow/guide/introduction)
- [Installation](https://robyconte.github.io/filament-flow/guide/installation)
- [Quick Start](https://robyconte.github.io/filament-flow/guide/quick-start)
- [Panel Integration](https://robyconte.github.io/filament-flow/panel/integration)
- [Database-Driven Workflows](https://robyconte.github.io/filament-flow/workflows/database-driven)
- [State Access Control](https://robyconte.github.io/filament-flow/workflows/access-control)
- [Workflow Notifications](https://robyconte.github.io/filament-flow/workflows/notifications)
- [UI Components](https://robyconte.github.io/filament-flow/ui/form-components)
- [Complete Example: Order Workflow](https://robyconte.github.io/filament-flow/examples/order-workflow)
- [Configuration Reference](https://robyconte.github.io/filament-flow/reference/configuration)
- [API Reference](https://robyconte.github.io/filament-flow/reference/api)

## Requirements

- PHP `^8.2`
- Laravel `^11.0|^12.0`
- Filament `^4.0`
- Spatie Laravel Model States `^2.12`

## Installation

```bash
composer require robyconte/filament-flow
```

See the [Installation guide](https://robyconte.github.io/filament-flow/guide/installation) for full setup instructions.

## Working on the package

One entry point runs the checks, whichever way PHP is reachable — natively, or through the
DDEV container of the host application the package is mounted in:

```bash
./scripts/check.sh            # the commands, explained
./scripts/check.sh all        # lint + phpstan + debug leftovers + docs coverage + tests
./scripts/check.sh verify     # the five gates of CI, with the guards
./scripts/check.sh env        # which environment would run, and nothing else
```

Single checks are there too: `lint`, `lint:fix`, `phpstan`, `test`, `debug`, `coverage`,
`comments`. They are the scripts of `composer.json` — the file only decides **where** they run,
so the two cannot drift:

```bash
composer check        # the fast gate: lint + phpstan + debug leftovers
composer check:all    # everything the CI runs
```

Override the guessing with `FF_ENV=native|ddev`, `FF_DDEV_DIR=<dir>` (the DDEV project that
holds the package) and `FF_CONTAINER_PATH=<path>` (where it sits inside that container).

## License

This package is licensed under the [MIT License](LICENSE).

## Credits

- [Roberto Conte Rosito](mailto:roberto.conterosito@gmail.com)
- Built on [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states)
- Powered by [FilamentPHP](https://filamentphp.com/)
