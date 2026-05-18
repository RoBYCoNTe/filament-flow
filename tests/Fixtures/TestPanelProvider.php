<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Fixtures;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use RoBYCoNTe\FilamentFlow\FilamentFlowPlugin;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('test')
            ->path('test')
            ->login()
            ->resources([
                OrderResource::class,
            ])
            ->plugins([
                FilamentFlowPlugin::make(),
            ])
            ->middleware([
                DispatchServingFilamentEvent::class,
                DisableBladeIconComponents::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
