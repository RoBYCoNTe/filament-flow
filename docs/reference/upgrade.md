# Upgrade Guide

## HasFlexibleStates to FlexibleStateCast

The `HasFlexibleStates` trait is deprecated. Replace it with `FlexibleStateCast` on any model that uses it.

```php
// Before (deprecated)
use RoBYCoNTe\FilamentFlow\Concerns\HasFlexibleStates;

class Order extends Model
{
    use HasFlexibleStates;

    protected $casts = [
        'state' => OrderState::class,
    ];
}

// After
use RoBYCoNTe\FilamentFlow\Casts\FlexibleStateCast;

class Order extends Model
{
    protected function casts(): array
    {
        return [
            'state' => FlexibleStateCast::class.':'.OrderState::class,
        ];
    }
}
```

`FlexibleStateCast` supports both PHP-backed State classes and database-only states. The cast parameter is the fully-qualified base State class name.

## General Upgrade Checklist

Run these steps whenever you update the package:

**1. Run migrations**

```bash
php artisan migrate
```

New releases may add columns or tables. Always run migrations before deploying.

**2. Sync PHP State classes to the database**

```bash
php artisan filament-flow:sync-states
```

This command scans your registered model State classes and creates or updates corresponding `workflow_states` rows. Run it after adding new PHP State classes or changing `class_name` values.

**3. Clear caches**

```bash
php artisan cache:clear
```

Filament Flow caches workflow lookups, access rules, and field permissions. Clear the cache after any workflow configuration change.

**4. Re-publish the config if new keys were added**

```bash
php artisan vendor:publish --tag=filament-flow-config --force
```

Compare the published file with the package default to find newly introduced configuration keys. Merge any new keys into your existing `config/filament-flow.php` manually if you prefer not to overwrite your customisations.
