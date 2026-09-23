<?php

namespace RoBYCoNTe\FilamentFlow;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ReflectionException;
use RoBYCoNTe\FilamentFlow\Commands\ListWorkflowsCommand;
use RoBYCoNTe\FilamentFlow\Commands\ProcessScheduledChecksCommand;
use RoBYCoNTe\FilamentFlow\Commands\SyncStatesCommand;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionPermission;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;
use RoBYCoNTe\FilamentFlow\Observers\WorkflowCacheObserver;
use RoBYCoNTe\FilamentFlow\Services\RecipientResolver;
use RoBYCoNTe\FilamentFlow\Support\FormulaCompletionRegistry;
use RoBYCoNTe\FilamentFlow\Support\FormulaConditionRegistry;
use RoBYCoNTe\FilamentFlow\Support\ValidationRuleRegistry;
use RoBYCoNTe\FilamentFlow\Support\WorkflowFormulaScope;
use RoBYCoNTe\FilamentFlow\Testing\TestsFilamentFlow;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * The provider of the package: it publishes the configuration, loads the migrations and the
 * translations, and registers the services, the commands and the formula completions the engine
 * resolves by name.
 */
class FilamentFlowServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-flow';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasRoute('web')
            ->hasMigrations([
                '2025_01_01_000001_create_workflows_table',
                '2025_01_01_000002_create_workflow_transition_details_table',
                '2025_01_01_000003_create_workflow_state_permissions_table',
                '2025_01_01_000004_create_workflow_assignments_table',
                '2025_01_01_000005_create_workflow_notifications_table',
                '2025_01_01_000006_create_workflow_transition_history_table',
                '2025_01_01_000007_create_workflow_transition_side_effects_table',
                '2025_01_01_000008_create_workflow_scheduled_checks_table',
                '2025_01_01_000009_add_schema_version_to_workflows_table',
                '2025_01_01_000010_create_workflow_snapshots_table',
            ])
            ->runsMigrations()
            ->hasCommands([
                ListWorkflowsCommand::class,
                SyncStatesCommand::class,
                ProcessScheduledChecksCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToStarRepoOnGitHub('robyconte/filament-flow');
            });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function packageRegistered(): void
    {
        // Register JSON translations early so they are available before any
        // service provider triggers translation loading during the boot phase.
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        $this->app->singleton(FormulaCompletionRegistry::class, function () {
            $registry = new FormulaCompletionRegistry;
            $registry->register('workflow', new WorkflowFormulaScope);

            return $registry;
        });

        $this->app->singleton(FormulaConditionRegistry::class, fn () => new FormulaConditionRegistry);

        // Named validation rules: shared by every rule declaration (state field
        // rules, transition rules, host field rules).
        $this->app->singleton(ValidationRuleRegistry::class, fn () => new ValidationRuleRegistry);

        // The custom resolver the configuration has always promised. A class that cannot do the
        // job raises instead of being ignored: a setting nobody reads is worse than no setting.
        /**
         * @throws InvalidArgumentException
         */
        $this->app->bind(RecipientResolver::class, function (Application $app) {
            $class = config('filament-flow.notifications.recipient_resolver');

            if ($class === null) {
                return new RecipientResolver;
            }

            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, RecipientResolver::class)) {
                throw new InvalidArgumentException(sprintf(
                    'filament-flow.notifications.recipient_resolver must name a class extending %s, %s given.',
                    RecipientResolver::class,
                    is_string($class) ? $class : get_debug_type($class),
                ));
            }

            return $app->make($class);
        });
    }

    /**
     * @throws ReflectionException
     */
    public function packageBooted(): void
    {
        FilamentAsset::register([
            Js::make('formula-editor', __DIR__.'/../resources/js/formula-editor.js'),
            Css::make('formula-editor', __DIR__.'/../resources/css/formula-editor.css'),
        ], package: 'robyconte/filament-flow');

        Livewire::component('assignment-manager', AssignmentManager::class);

        Testable::mixin(new TestsFilamentFlow);

        // Register cache observer for automatic invalidation
        if (config('filament-flow.cache.enabled', true)) {
            $observedModels = [
                Workflow::class,
                WorkflowState::class,
                WorkflowTransition::class,
                WorkflowStateAccessRule::class,
                WorkflowStateField::class,
                WorkflowStateFieldRole::class,
                WorkflowTransitionField::class,
                WorkflowTransitionPermission::class,
                WorkflowTransitionValidationRule::class,
                WorkflowTransitionSideEffect::class,
            ];

            foreach ($observedModels as $modelClass) {
                if (class_exists($modelClass)) {
                    $modelClass::observe(WorkflowCacheObserver::class);
                }
            }
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if (config('filament-flow.scheduling.enabled', true)) {
                $frequency = config('filament-flow.scheduling.frequency', 'everyFiveMinutes');
                $schedule->command('workflow:process-schedules')->$frequency()->withoutOverlapping();
            }
        });
    }

    /** @noinspection PhpUnused */
    protected function getAssetPackageName(): ?string
    {
        return 'robyconte/filament-flow';
    }
}
