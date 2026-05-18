<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource\Pages;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource\RelationManagers;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;

class WorkflowStateResource extends Resource
{
    protected static ?string $parentResource = WorkflowResource::class;

    protected static ?string $model = WorkflowState::class;

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('State');
    }

    public static function getPluralModelLabel(): string
    {
        return __('States');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function getGeneralFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Key'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('e.g., pending'))
                    ->helperText(__('Unique identifier for this state. Use snake_case (e.g., pending, in_review, approved).')),

                Forms\Components\TextInput::make('label')
                    ->label(__('Label'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('e.g., Pending'))
                    ->helperText(__('Display name shown to users in badges, selects, and notifications.')),
            ]),

            Forms\Components\Textarea::make('description')
                ->label(__('Description'))
                ->maxLength(1000)
                ->rows(2)
                ->helperText(__('Optional note to describe the purpose of this state.')),

            Grid::make(3)->schema([
                Forms\Components\Select::make('color')
                    ->label(__('Color'))
                    ->options([
                        'gray' => __('Gray'),
                        'primary' => __('Primary'),
                        'success' => __('Success'),
                        'warning' => __('Warning'),
                        'danger' => __('Danger'),
                        'info' => __('Info'),
                    ])
                    ->default('gray')
                    ->native(false)
                    ->helperText(__('Badge color for this state.')),

                Forms\Components\Select::make('icon')
                    ->label(__('Icon'))
                    ->options(function (): array {
                        $options = [];
                        foreach (Heroicon::cases() as $case) {
                            if (str_starts_with($case->name, 'Outlined')) {
                                $label = str_replace('Outlined', '', $case->name);
                                $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);
                                $options[$case->value] = $label;
                            }
                        }
                        asort($options);

                        return $options;
                    })
                    ->searchable()
                    ->native(false)
                    ->helperText(__('Optional icon displayed alongside the state label.')),

                Forms\Components\TextInput::make('sort_order')
                    ->label(__('Sort Order'))
                    ->numeric()
                    ->default(0)
                    ->helperText(__('Determines the display order among states.')),
            ]),

            Grid::make(3)->schema([
                Forms\Components\Toggle::make('is_initial')
                    ->label(__('Initial State'))
                    ->inline(false)
                    ->helperText(__('Records start in this state when created.')),

                Forms\Components\Toggle::make('is_final')
                    ->label(__('Final State'))
                    ->inline(false)
                    ->helperText(__('No further transitions are allowed from this state.')),

                Forms\Components\Select::make('class_name')
                    ->label(__('PHP Class'))
                    ->options(fn () => ModelDiscovery::getStateClassOptions())
                    ->searchable()
                    ->native(false)
                    ->helperText(__('Optional state class for custom logic (e.g., validation, side effects).')),
            ]),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FieldPermissionsRelationManager::class,
            RelationManagers\AccessRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'create' => Pages\CreateWorkflowState::route('/create'),
            'edit' => Pages\EditWorkflowState::route('/{record}/edit'),
        ];
    }
}
