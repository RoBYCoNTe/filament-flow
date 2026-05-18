<?php

namespace RoBYCoNTe\FilamentFlow;

use Illuminate\Console\Scheduling\Schedule;
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
use RoBYCoNTe\FilamentFlow\Observers\WorkflowCacheObserver;
use RoBYCoNTe\FilamentFlow\Testing\TestsFilamentFlow;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
            ->hasMigrations([
                '2025_01_01_000001_create_workflows_table',
                '2025_01_01_000002_create_workflow_transition_details_table',
                '2025_01_01_000003_create_workflow_state_permissions_table',
                '2025_01_01_000004_create_workflow_assignments_table',
                '2025_01_01_000005_create_workflow_notifications_table',
                '2025_01_01_000006_create_workflow_transition_history_table',
                '2025_01_01_000007_create_workflow_transition_side_effects_table',
                '2025_01_01_000008_create_workflow_scheduled_checks_table',
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

    public function packageRegistered(): void {}

    /**
     * @throws ReflectionException
     */
    public function packageBooted(): void
    {
        Livewire::component('assignment-manager', AssignmentManager::class);

        Testable::mixin(new TestsFilamentFlow);

        // Register cache observer for automatic invalidation
        if (config('filament-flow.cache.enabled', true)) {
            Workflow::observe(WorkflowCacheObserver::class);
            WorkflowState::observe(WorkflowCacheObserver::class);

            if (class_exists(WorkflowStateAccessRule::class)) {
                WorkflowStateAccessRule::observe(WorkflowCacheObserver::class);
            }

            if (class_exists(WorkflowStateField::class)) {
                WorkflowStateField::observe(WorkflowCacheObserver::class);
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
