<?php

namespace RoBYCoNTe\FilamentFlow\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use RoBYCoNTe\FilamentFlow\FilamentFlowServiceProvider;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Ticket;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Filament's SupportServiceProvider calls app()->bind(DataStore::class, DataStoreOverride::class),
        // which internally calls dropStaleInstances() and removes Livewire's singleton registration.
        // This causes every app(DataStore::class) call to create a new instance, breaking the WeakMap
        // that Livewire uses to store per-component state (like the error bag).
        // Re-registering the instance after boot ensures a stable singleton for tests.
        $this->app->instance(
            \Livewire\Mechanisms\DataStore::class,
            $this->app->make(\Livewire\Mechanisms\DataStore::class)
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Livewire\LivewireServiceProvider::class,
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            FilamentFlowServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('filament-flow.enabled', true);
        $app['config']->set('filament-flow.user_model', User::class);
        $app['config']->set('filament-flow.state_access.enabled', true);
        $app['config']->set('filament-flow.state_access.enforce_on_transition', false);
        $app['config']->set('filament-flow.state_access.owner_field', 'user_id');
        $app['config']->set('filament-flow.state_access.super_admin_roles', ['super_admin']);
        $app['config']->set('filament-flow.notifications.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->createTestTables();
    }

    protected function createTicketWorkflow(): array
    {
        $workflow = $this->createTestWorkflow([
            'name' => 'Ticket Workflow',
            'model_type' => Ticket::class,
            'state_column' => 'state',
        ]);

        $open = $this->createWorkflowState($workflow, [
            'name' => 'open',
            'label' => 'Open',
            'color' => 'warning',
            'icon' => 'heroicon-o-exclamation-circle',
            'description' => 'Ticket is open and awaiting assignment',
            'is_initial' => true,
            'sort_order' => 10,
        ]);

        $inProgress = $this->createWorkflowState($workflow, [
            'name' => 'in_progress',
            'label' => 'In Progress',
            'color' => 'info',
            'icon' => 'heroicon-o-arrow-path',
            'description' => 'Ticket is being worked on',
            'sort_order' => 20,
        ]);

        $resolved = $this->createWorkflowState($workflow, [
            'name' => 'resolved',
            'label' => 'Resolved',
            'color' => 'success',
            'icon' => 'heroicon-o-check-circle',
            'description' => 'Ticket has been resolved',
            'is_final' => true,
            'sort_order' => 30,
        ]);

        $closed = $this->createWorkflowState($workflow, [
            'name' => 'closed',
            'label' => 'Closed',
            'color' => 'gray',
            'icon' => 'heroicon-o-x-circle',
            'description' => 'Ticket has been closed',
            'is_final' => true,
            'sort_order' => 40,
        ]);

        $t1 = $this->createWorkflowTransition($workflow, $open, $inProgress, [
            'name' => 'start_work',
            'label' => 'Start Work',
        ]);

        $t2 = $this->createWorkflowTransition($workflow, $inProgress, $resolved, [
            'name' => 'resolve',
            'label' => 'Resolve',
        ]);

        $t3 = $this->createWorkflowTransition($workflow, $resolved, $closed, [
            'name' => 'close',
            'label' => 'Close',
        ]);

        $t4 = $this->createWorkflowTransition($workflow, $inProgress, $open, [
            'name' => 'reopen',
            'label' => 'Reopen',
        ]);

        return [
            'workflow' => $workflow,
            'states' => compact('open', 'inProgress', 'resolved', 'closed'),
            'transitions' => compact('t1', 't2', 't3', 't4'),
        ];
    }

    protected function createTestTicket(array $data = []): Ticket
    {
        static $counter = 0;
        $counter++;

        return Ticket::create(array_merge([
            'ticket_number' => 'TKT-'.str_pad($counter, 4, '0', STR_PAD_LEFT),
            'subject' => 'Test Ticket #'.$counter,
            'state' => 'open',
            'priority' => 'medium',
        ], $data));
    }

    protected function createTestTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('users', 'role')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('role')->nullable();
                });
            }
        }

        if (! Schema::hasTable('test_tickets')) {
            Schema::create('test_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_number')->unique();
                $table->string('subject');
                $table->text('description')->nullable();
                $table->string('state')->default('open');
                $table->string('priority')->default('medium');
                $table->foreignId('user_id')->nullable();
                $table->text('notes')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('test_orders')) {
            Schema::create('test_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number')->unique();
                $table->string('customer_name');
                $table->string('customer_email')->nullable();
                $table->decimal('total_amount', 10);
                $table->string('state')->nullable();
                $table->foreignId('user_id')->nullable();
                $table->text('notes')->nullable();
                $table->text('processing_notes')->nullable();
                $table->text('shipping_notes')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('carrier')->nullable();
                $table->date('estimated_delivery')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('test_orders', 'user_id')) {
                Schema::table('test_orders', function (Blueprint $table) {
                    $table->foreignId('user_id')->nullable();
                });
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createTestWorkflow(array $data = []): Workflow
    {
        return Workflow::create(array_merge([
            'name' => 'Test Workflow',
            'model_type' => Order::class,
            'state_column' => 'state',
            'is_active' => true,
        ], $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createWorkflowState(
        Workflow $workflow,
        array $data = []
    ): WorkflowState {
        $defaults = [
            'workflow_id' => $workflow->id,
            'name' => 'test_state',
            'label' => 'Test State',
            'color' => 'primary',
            'sort_order' => 0,
            'is_initial' => false,
            'is_final' => false,
        ];

        $merged = array_merge($defaults, $data);

        if (! isset($merged['class_name'])) {
            $merged['class_name'] = $merged['name'];
        }

        return WorkflowState::create($merged);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createWorkflowTransition(
        Workflow $workflow,
        WorkflowState $fromState,
        WorkflowState $toState,
        array $data = []
    ): WorkflowTransition {
        return WorkflowTransition::create(array_merge([
            'workflow_id' => $workflow->id,
            'from_state_id' => $fromState->id,
            'to_state_id' => $toState->id,
            'name' => $fromState->name.'_to_'.$toState->name,
            'label' => 'Transition from '.$fromState->name,
            'requires_confirmation' => false,
        ], $data));
    }

    protected function assertTransitionLogged(mixed $model, string $fromState, string $toState): void
    {
        $this->assertTrue(
            WorkflowStateTransition::query()
                ->where('transitionable_type', get_class($model))
                ->where('transitionable_id', $model->id)
                ->where('from_state', $fromState)
                ->where('to_state', $toState)
                ->exists(),
            'Transition from '.$fromState.' to '.$toState.' was not logged'
        );
    }

    protected function assertTransitionNotLogged(mixed $model, string $fromState, string $toState): void
    {
        $this->assertFalse(
            WorkflowStateTransition::query()
                ->where('transitionable_type', get_class($model))
                ->where('transitionable_id', $model->id)
                ->where('from_state', $fromState)
                ->where('to_state', $toState)
                ->exists(),
            'Transition from '.$fromState.' to '.$toState.' was logged when it should not have been'
        );
    }

    protected function getLastTransition(mixed $model): ?WorkflowStateTransition
    {
        return WorkflowStateTransition::query()
            ->where('transitionable_type', get_class($model))
            ->where('transitionable_id', $model->id)
            ->latest('created_at')
            ->first();
    }

    protected function assertModelInState(mixed $model, string|object $expectedState): void
    {
        $model->refresh();
        $stateClass = is_string($expectedState) ? $expectedState : get_class($expectedState);
        $actualState = is_string($model->status) ? $model->status : get_class($model->status);

        $this->assertEquals(
            $stateClass,
            $actualState,
            'Model is not in the expected state. Expected: '.$stateClass.', Got: '.$actualState
        );
    }

    protected function createTestUser(array $data = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ], $data));
    }
}
