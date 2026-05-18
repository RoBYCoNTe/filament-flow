<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource\Pages;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource\RelationManagers;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;

class WorkflowTransitionResource extends Resource
{
    protected static ?string $parentResource = WorkflowResource::class;

    protected static ?string $model = WorkflowTransition::class;

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('Transition');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Transitions');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function getGeneralFormSchema(?int $workflowId = null): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\Select::make('from_state_id')
                    ->label(__('From State'))
                    ->options(function () use ($workflowId) {
                        if (! $workflowId) {
                            return [];
                        }

                        return WorkflowState::where('workflow_id', $workflowId)
                            ->pluck('label', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder(__('Any state (global)'))
                    ->helperText(__('The state from which this transition starts. Leave empty for global transitions available from any state.')),

                Forms\Components\Select::make('to_state_id')
                    ->label(__('To State'))
                    ->options(function () use ($workflowId) {
                        if (! $workflowId) {
                            return [];
                        }

                        return WorkflowState::where('workflow_id', $workflowId)
                            ->pluck('label', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder(__('No change (action)'))
                    ->helperText(__('The target state after transition. Leave empty for in-state actions (e.g., add note, log activity).')),
            ]),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Key'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('e.g., process_order'))
                    ->helperText(__('Unique identifier for this transition. Use snake_case.')),

                Forms\Components\TextInput::make('label')
                    ->label(__('Button Label'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('e.g., Process Order'))
                    ->helperText(__('Text shown on the action button in the UI.')),
            ]),

            Forms\Components\Textarea::make('description')
                ->label(__('Description'))
                ->maxLength(1000)
                ->rows(2)
                ->helperText(__('Optional note to describe the purpose of this transition.')),

            Forms\Components\TextInput::make('class_name')
                ->label(__('PHP Transition Class'))
                ->maxLength(255)
                ->placeholder(__('App\\Transitions\\ProcessOrderTransition'))
                ->helperText(__('Optional PHP class for custom transition logic (validation, side effects).')),

            Grid::make(2)->schema([
                Forms\Components\Toggle::make('requires_confirmation')
                    ->label(__('Requires Confirmation'))
                    ->inline(false)
                    ->helperText(__('Show a confirmation dialog before executing the transition.')),

                Forms\Components\Toggle::make('requires_reason')
                    ->label(__('Requires Reason'))
                    ->inline(false)
                    ->helperText(__('Require the user to provide a reason when executing the transition.')),
            ]),

            Forms\Components\KeyValue::make('conditions')
                ->label(__('Conditions'))
                ->helperText(__('JSON conditions evaluated against the record. All must pass for the transition to be available. Example: {"field": "assignmentType.name", "operator": "in", "value": ["Type A"]}'))
                ->columnSpanFull(),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransitionFieldsRelationManager::class,
            RelationManagers\ValidationRulesRelationManager::class,
            RelationManagers\TransitionPermissionsRelationManager::class,
            RelationManagers\SideEffectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'edit' => Pages\EditWorkflowTransition::route('/{record}/edit'),
        ];
    }
}
