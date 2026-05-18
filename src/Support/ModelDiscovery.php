<?php

namespace RoBYCoNTe\FilamentFlow\Support;

use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Spatie\ModelStates\State;
use Throwable;

class ModelDiscovery
{
    /**
     * @return array<string, string> FQCN => "ClassName (App\Models\...)"
     */
    public static function getOptions(): array
    {
        $models = static::discover();

        $options = [];
        foreach ($models as $class) {
            $options[$class] = class_basename($class).' ('.$class.')';
        }

        ksort($options);

        return $options;
    }

    /**
     * @return array<string>
     */
    public static function discover(): array
    {
        $models = [];

        // Scan configured paths (default: app/Models)
        $paths = config('filament-flow.model_discovery_paths', [
            app_path('Models'),
        ]);

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = static::resolveClassFromFile($file->getPathname());

                if ($class && static::isEloquentModel($class)) {
                    $models[] = $class;
                }
            }
        }

        sort($models);

        return $models;
    }

    protected static function resolveClassFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $className = $matches[1];

            return $namespace ? $namespace.'\\'.$className : $className;
        }

        return null;
    }

    /**
     * @return array<string, string> column_name => "column_name (type)"
     */
    public static function getColumnOptions(?string $modelClass): array
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return [];
        }

        try {
            /** @var Model $model */
            $model = new $modelClass;
            $table = $model->getTable();
            $connection = $model->getConnectionName();

            $columns = Schema::connection($connection)->getColumns($table);

            $options = [];
            foreach ($columns as $column) {
                $name = $column['name'];
                $type = $column['type_name'] ?? $column['type'] ?? '';
                $options[$name] = "{$name} ({$type})";
            }

            return $options;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string> column_name => "column_name (type)"
     */
    public static function getStringColumnOptions(?string $modelClass): array
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return [];
        }

        try {
            /** @var Model $model */
            $model = new $modelClass;
            $table = $model->getTable();
            $connection = $model->getConnectionName();

            $columns = Schema::connection($connection)->getColumns($table);

            $stringTypes = ['varchar', 'string', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'];

            $options = [];
            foreach ($columns as $column) {
                $typeName = strtolower($column['type_name'] ?? $column['type'] ?? '');
                if (in_array($typeName, $stringTypes)) {
                    $name = $column['name'];
                    $options[$name] = "{$name} ({$typeName})";
                }
            }

            return $options;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string> component_name => "component_name (type)"
     */
    public static function getResourceComponentOptions(?string $modelClass): array
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return [];
        }

        try {
            $resourceClass = static::resolveResourceForModel($modelClass);

            if (! $resourceClass) {
                return static::getColumnOptions($modelClass);
            }

            $schema = $resourceClass::form(FilamentSchema::make());

            // Read raw components via reflection to avoid Livewire evaluation
            $rawComponents = (new ReflectionProperty($schema, 'components'))->getValue($schema);

            if ($rawComponents instanceof \Closure) {
                return static::getColumnOptions($modelClass);
            }

            $fields = [];
            $layouts = [];

            static::walkComponents(Arr::wrap($rawComponents), $fields, $layouts);

            ksort($fields);
            ksort($layouts);

            $options = [];

            if ($fields) {
                $options[__('Fields')] = $fields;
            }

            if ($layouts) {
                $options[__('Layout Components')] = $layouts;
            }

            // Discover RelationManagers via reflection
            $relationManagers = [];
            if (method_exists($resourceClass, 'getRelations')) {
                foreach ($resourceClass::getRelations() as $rmClass) {
                    $relName = static::getRelationManagerRelationship($rmClass);
                    if (! $relName) {
                        continue;
                    }

                    $rmLabel = class_basename($rmClass);
                    $relationManagers[$relName] = "{$relName} ({$rmLabel})";

                    foreach (static::discoverRelationManagerActions($rmClass) as $action) {
                        $key = "{$relName}.{$action}";
                        $relationManagers[$key] = "{$key} (Action)";
                    }
                }
            }

            if ($relationManagers) {
                $options[__('Relation Managers')] = $relationManagers;
            }

            return $options ?: static::getColumnOptions($modelClass);
        } catch (Throwable) {
            return static::getColumnOptions($modelClass);
        }
    }

    /**
     * @param  array<Component|Field>  $components
     */
    protected static function walkComponents(array $components, array &$fields, array &$layouts): void
    {
        foreach ($components as $component) {
            if (! $component instanceof Component) {
                continue;
            }

            if ($component instanceof Field) {
                $name = $component->getName();
                if (filled($name)) {
                    $type = class_basename($component);
                    $fields[$name] = "{$name} ({$type})";
                }
            } else {
                // Only include layout components with an explicit string key
                $key = static::readProperty($component, 'key');
                if (is_string($key) && filled($key)) {
                    $type = class_basename($component);
                    $layouts[$key] = "{$key} ({$type})";
                }
            }

            // Recurse into child components
            $childMap = static::readProperty($component, 'childComponents');

            if (! is_array($childMap)) {
                continue;
            }

            foreach ($childMap as $children) {
                if ($children instanceof \Closure) {
                    continue;
                }

                static::walkComponents(Arr::wrap($children), $fields, $layouts);
            }
        }
    }

    protected static function readProperty(object $object, string $property): mixed
    {
        try {
            $ref = new ReflectionProperty($object, $property);

            return $ref->getValue($object);
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * @return class-string<\Filament\Resources\Resource>|null
     */
    protected static function resolveResourceForModel(string $modelClass): ?string
    {
        // Try the current panel first
        $resource = Filament::getModelResource($modelClass);

        if ($resource) {
            return $resource;
        }

        // Search across all registered panels
        foreach (Filament::getPanels() as $panel) {
            $resource = $panel->getModelResource($modelClass);

            if ($resource) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * Discover classes that implement a given interface.
     *
     * @return array<string, string> FQCN => "ClassName"
     */
    public static function discoverImplementations(string $interface): array
    {
        $paths = config('filament-flow.model_discovery_paths', [
            app_path('Models'),
        ]);

        // Also search in common locations for policies/services
        $extraPaths = [
            app_path('Policies'),
            app_path('Services'),
            app_path('Workflow'),
        ];

        $allPaths = array_merge($paths, $extraPaths);

        $classes = static::discoverClasses(
            $allPaths,
            fn (string $class) => static::isConcreteSubclassOf($class, $interface)
                || (interface_exists($interface) && class_exists($class) && (new ReflectionClass($class))->implementsInterface($interface)),
        );

        $options = [];
        foreach ($classes as $class) {
            $options[$class] = class_basename($class)." ({$class})";
        }

        return $options;
    }

    /**
     * Get all concrete State subclasses as select options.
     *
     * @return array<string, string> FQCN => "ClassName (namespace)"
     */
    public static function getStateClassOptions(): array
    {
        $classes = static::discoverClasses(
            config('filament-flow.state_discovery_paths', [app_path('States')]),
            fn (string $class) => static::isConcreteSubclassOf($class, State::class),
        );

        $options = [];
        foreach ($classes as $class) {
            $options[$class] = class_basename($class);
        }

        return $options;
    }

    /**
     * Discover classes in given paths matching a filter callback.
     *
     * @return array<string>
     */
    protected static function discoverClasses(array $paths, callable $filter): array
    {
        $classes = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = static::resolveClassFromFile($file->getPathname());

                if ($class && $filter($class)) {
                    $classes[] = $class;
                }
            }
        }

        sort($classes);

        return $classes;
    }

    protected static function isConcreteSubclassOf(string $class, string $parent): bool
    {
        try {
            if (! class_exists($class)) {
                return false;
            }

            $reflection = new ReflectionClass($class);

            return ! $reflection->isAbstract() && $reflection->isSubclassOf($parent);
        } catch (Throwable) {
            return false;
        }
    }

    protected static function isEloquentModel(string $class): bool
    {
        return static::isConcreteSubclassOf($class, Model::class);
    }

    /**
     * Read the $relationship property from a RelationManager class via reflection.
     */
    protected static function getRelationManagerRelationship(string $rmClass): ?string
    {
        try {
            $ref = new ReflectionClass($rmClass);
            $prop = $ref->getProperty('relationship');

            return $prop->getDefaultValue();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Discover available actions (create, delete, edit) from a RelationManager class
     * by inspecting its table() method via reflection.
     *
     * @return array<string>
     */
    protected static function discoverRelationManagerActions(string $rmClass): array
    {
        $actions = [];

        try {
            $ref = new ReflectionClass($rmClass);

            if (! $ref->hasMethod('table')) {
                return $actions;
            }

            $source = file_get_contents($ref->getFileName());

            // Look for common action classes used in the RM
            $actionMap = [
                'CreateAction' => 'create',
                'DeleteAction' => 'delete',
                'EditAction' => 'edit',
                'DeleteBulkAction' => 'delete',
            ];

            foreach ($actionMap as $className => $actionName) {
                if (str_contains($source, $className.'::make()') && ! in_array($actionName, $actions, true)) {
                    $actions[] = $actionName;
                }
            }
        } catch (Throwable) {
            // Ignore errors
        }

        return $actions;
    }
}
