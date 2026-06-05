<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\Pages;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\RelationManagers;
use RoBYCoNTe\FilamentFlow\FilamentFlowPlugin;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;
use UnitEnum;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|null|UnitEnum $navigationGroup = 'Workflow';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $plugin = FilamentFlowPlugin::getInstance();

        return $plugin === null || $plugin->isAuthorized();
    }

    public static function isScopedToTenant(): bool
    {
        return FilamentFlowPlugin::getInstance()?->isTenantAware()
            ?? config('filament-flow.tenant_model') !== null;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return FilamentFlowPlugin::getInstance()?->getNavigationIcon() ?? parent::getNavigationIcon();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentFlowPlugin::getInstance()?->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentFlowPlugin::getInstance()?->getNavigationSort() ?? parent::getNavigationSort();
    }

    public static function getNavigationParentItem(): ?string
    {
        return FilamentFlowPlugin::getInstance()?->getNavigationParentItem() ?? parent::getNavigationParentItem();
    }

    public static function getNavigationLabel(): string
    {
        return FilamentFlowPlugin::getInstance()?->getNavigationLabel() ?? __('Workflows');
    }

    public static function getModelLabel(): string
    {
        return __('Workflow');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Workflows');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('e.g., Order Processing'))
                    ->helperText(__('A descriptive name to identify this workflow (e.g., "Order Processing", "Invoice Approval").'))
                    ->columnSpanFull(),

                Forms\Components\Select::make('model_type')
                    ->label(__('Model'))
                    ->required()
                    ->options(fn () => ModelDiscovery::getOptions())
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('state_column', null))
                    ->helperText(__('The Eloquent model this workflow will be attached to.')),

                Forms\Components\Select::make('state_column')
                    ->label(__('State Column'))
                    ->required()
                    ->options(fn (Get $get) => ModelDiscovery::getStringColumnOptions($get('model_type')))
                    ->searchable()
                    ->native(false)
                    ->default('state')
                    ->helperText(__('The varchar column on the model table that stores the current workflow state.')),

                Forms\Components\KeyValue::make('metadata')
                    ->label(__('Metadata'))
                    ->helperText(__('Optional key-value pairs for storing additional workflow configuration.'))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_type')
                    ->label(__('Model'))
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->tooltip(fn (string $state): string => $state),

                Tables\Columns\TextColumn::make('state_column')
                    ->label(__('State Column'))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('states_count')
                    ->label(__('States'))
                    ->counts('states')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('transitions_count')
                    ->label(__('Transitions'))
                    ->counts('transitions')
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('Tenant'))
                    ->placeholder(__('Global'))
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => Workflow::isMultiTenancyEnabled()),

                Tables\Columns\TextColumn::make('scope')
                    ->label(__('Scope'))
                    ->badge()
                    ->state(fn (Workflow $record): string => $record->isGlobal() ? 'global' : 'tenant')
                    ->formatStateUsing(fn (string $state): string => $state === 'global' ? __('Global') : __('Tenant'))
                    ->color(fn (string $state): string => $state === 'global' ? 'info' : 'success')
                    ->visible(fn () => Workflow::isMultiTenancyEnabled()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Active only'))
                    ->falseLabel(__('Inactive only')),
            ])
            ->recordActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StatesRelationManager::class,
            RelationManagers\TransitionsRelationManager::class,
            RelationManagers\NotificationsRelationManager::class,
            RelationManagers\ScheduledChecksRelationManager::class,
            RelationManagers\TransitionHistoryRelationManager::class,
            RelationManagers\NotificationLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflows::route('/'),
            'create' => Pages\CreateWorkflow::route('/create'),
            'edit' => Pages\EditWorkflow::route('/{record}/edit'),
            'view' => Pages\ViewWorkflow::route('/{record}'),
        ];
    }
}
