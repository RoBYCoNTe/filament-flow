<?php

namespace RoBYCoNTe\FilamentFlow\Tests;

use Filament\Facades\Filament;
use Filament\Http\Controllers\RedirectToHomeController;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\Route;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\TestPanelProvider;

abstract class FilamentTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create and register the test panel directly on the PanelRegistry.
        // We can't use $this->app->register(TestPanelProvider::class) because
        // by the time setUp() runs, the PanelRegistry resolving callbacks have
        // already fired, so the panel would never be registered.
        $provider = new TestPanelProvider($this->app);
        $panel = $provider->panel(Panel::make());
        app(PanelRegistry::class)->register($panel);

        // Manually register routes for the test panel (routes were loaded during boot, before this panel existed)
        $this->registerPanelRoutes($panel);

        // Set the current Filament panel
        Filament::setCurrentPanel($panel);
    }

    /**
     * Register routes for the test panel.
     *
     * Filament's routes are loaded during app boot, before our test panel exists.
     * We replicate the route registration logic from vendor/filament/filament/routes/web.php.
     */
    private function registerPanelRoutes(Panel $panel): void
    {
        $panelId = $panel->getId();

        Route::name("filament.{$panelId}.")
            ->middleware($panel->getMiddleware())
            ->prefix($panel->getPath())
            ->group(function () use ($panel): void {
                foreach ($panel->getRoutes() as $routes) {
                    $routes($panel);
                }

                Route::name('auth.')->group(function () use ($panel): void {
                    if ($panel->hasLogin()) {
                        Route::get($panel->getLoginRouteSlug(), $panel->getLoginRouteAction())
                            ->name('login');
                    }
                });

                Route::middleware($panel->getAuthMiddleware())
                    ->group(function () use ($panel): void {
                        foreach ($panel->getAuthenticatedRoutes() as $routes) {
                            $routes($panel);
                        }

                        Route::get('/', RedirectToHomeController::class)->name('home');

                        foreach ($panel->getPages() as $page) {
                            $page::registerRoutes($panel);
                        }

                        foreach ($panel->getResources() as $resource) {
                            $resource::registerRoutes($panel);
                        }
                    });
            });
    }

    /**
     * Create a full workflow with states, transitions, and field permissions.
     *
     * Returns an array with keys: workflow, states (keyed by name), transitions (keyed by name)
     */
    protected function createFullWorkflow(): array
    {
        $workflow = $this->createTestWorkflow([
            'name' => 'Order Workflow',
        ]);

        // States
        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'color' => 'warning',
            'icon' => 'heroicon-o-clock',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'color' => 'info',
            'icon' => 'heroicon-o-cog-6-tooth',
            'sort_order' => 1,
        ]);

        $shipped = $this->createWorkflowState($workflow, [
            'name' => 'shipped',
            'label' => 'Shipped',
            'color' => 'success',
            'icon' => 'heroicon-o-truck',
            'sort_order' => 2,
        ]);

        $delivered = $this->createWorkflowState($workflow, [
            'name' => 'delivered',
            'label' => 'Delivered',
            'color' => 'success',
            'icon' => 'heroicon-o-check-circle',
            'is_final' => true,
            'sort_order' => 3,
        ]);

        // Transitions
        $pendingToProcessing = $this->createWorkflowTransition($workflow, $pending, $processing, [
            'name' => 'start_processing',
            'label' => 'Start Processing',
        ]);

        $processingToShipped = $this->createWorkflowTransition($workflow, $processing, $shipped, [
            'name' => 'ship_order',
            'label' => 'Ship Order',
        ]);

        $shippedToDelivered = $this->createWorkflowTransition($workflow, $shipped, $delivered, [
            'name' => 'deliver_order',
            'label' => 'Deliver Order',
        ]);

        // Field permissions - PENDING state
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'order_number',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'customer_email',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'processing_notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'shipping_notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        // tracking_number LOCKED in pending
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'tracking_number',
            'visibility' => 'visible',
            'mutability' => 'locked',
            'is_required' => false,
        ]);
        // carrier hidden in pending
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'carrier',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Field permissions - PROCESSING state
        WorkflowStateField::create([
            'state_id' => $processing->id,
            'field_name' => 'order_number',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $processing->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $processing->id,
            'field_name' => 'processing_notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);
        WorkflowStateField::create([
            'state_id' => $processing->id,
            'field_name' => 'tracking_number',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Field permissions - SHIPPED state
        WorkflowStateField::create([
            'state_id' => $shipped->id,
            'field_name' => 'order_number',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);
        WorkflowStateField::create([
            'state_id' => $shipped->id,
            'field_name' => 'tracking_number',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);
        WorkflowStateField::create([
            'state_id' => $shipped->id,
            'field_name' => 'carrier',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        // Access rules - create permission for authenticated users
        WorkflowStateAccessRule::create([
            'state_id' => $pending->id,
            'access_type' => 'create',
            'rule' => '@authenticated',
        ]);
        WorkflowStateAccessRule::create([
            'state_id' => $pending->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
        ]);
        WorkflowStateAccessRule::create([
            'state_id' => $pending->id,
            'access_type' => 'edit',
            'rule' => '@authenticated',
        ]);
        WorkflowStateAccessRule::create([
            'state_id' => $pending->id,
            'access_type' => 'transition',
            'rule' => '@authenticated',
        ]);

        return [
            'workflow' => $workflow,
            'states' => [
                'pending' => $pending,
                'processing' => $processing,
                'shipped' => $shipped,
                'delivered' => $delivered,
            ],
            'transitions' => [
                'start_processing' => $pendingToProcessing,
                'ship_order' => $processingToShipped,
                'deliver_order' => $shippedToDelivered,
            ],
        ];
    }

    /**
     * Create a field role override for a state field.
     */
    protected function createFieldRoleOverride(
        WorkflowStateField $field,
        string $roleName,
        ?string $visibility = null,
        ?string $mutability = null,
        ?bool $isRequired = null
    ): WorkflowStateFieldRole {
        return WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => $roleName,
            'visibility' => $visibility,
            'mutability' => $mutability,
            'is_required' => $isRequired,
        ]);
    }

    /**
     * Create an order in the specified state.
     */
    protected function createOrderInState(string $state = 'pending', array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'total_amount' => 100.00,
            'state' => $state,
        ], $attributes));
    }
}
