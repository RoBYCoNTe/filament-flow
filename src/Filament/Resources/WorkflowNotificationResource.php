<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowNotificationResource\Pages;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowNotificationResource\RelationManagers;
use RoBYCoNTe\FilamentFlow\Models\WorkflowNotification;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;

class WorkflowNotificationResource extends Resource
{
    protected static ?string $parentResource = WorkflowResource::class;

    protected static ?string $model = WorkflowNotification::class;

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('Notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Notifications');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function getGeneralFormSchema(?int $workflowId = null): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(255)
                ->placeholder(__('e.g., Order Processing Notification'))
                ->helperText(__('A descriptive name to identify this notification.')),

            Forms\Components\Textarea::make('description')
                ->label(__('Description'))
                ->maxLength(1000)
                ->rows(2)
                ->helperText(__('Optional note about this notification\'s purpose.')),

            Forms\Components\Radio::make('trigger_event')
                ->label(__('Trigger Event'))
                ->required()
                ->options([
                    'on_transition' => __('On Transition'),
                    'on_state_enter' => __('On State Enter'),
                    'on_state_exit' => __('On State Exit'),
                    'on_assignment' => __('On Assignment'),
                    'on_field_change' => __('On Field Change'),
                ])
                ->descriptions([
                    'on_transition' => __('Fires when a specific transition is executed.'),
                    'on_state_enter' => __('Fires when a record enters a specific state.'),
                    'on_state_exit' => __('Fires when a record leaves a specific state.'),
                    'on_assignment' => __('Fires when a user is assigned to the record.'),
                    'on_field_change' => __('Fires when a monitored field value changes.'),
                ])
                ->live()
                ->columns(1),

            Forms\Components\Select::make('transition_id')
                ->label(__('Transition'))
                ->options(function () use ($workflowId) {
                    if (! $workflowId) {
                        return [];
                    }

                    return WorkflowTransition::where('workflow_id', $workflowId)
                        ->with(['fromState', 'toState'])
                        ->get()
                        ->mapWithKeys(fn ($t) => [
                            $t->id => "{$t->fromState?->label} → {$t->toState?->label} ({$t->label})",
                        ])
                        ->toArray();
                })
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn (Get $get) => $get('trigger_event') === 'on_transition')
                ->helperText(__('The specific transition that triggers this notification. Leave empty for all transitions.')),

            Forms\Components\Select::make('state_id')
                ->label(__('State'))
                ->options(function () use ($workflowId) {
                    if (! $workflowId) {
                        return [];
                    }

                    return WorkflowState::where('workflow_id', $workflowId)
                        ->pluck('label', 'id')
                        ->toArray();
                })
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn (Get $get) => in_array($get('trigger_event'), ['on_state_enter', 'on_state_exit']))
                ->helperText(__('The state that triggers this notification on enter or exit.')),

            Grid::make(3)->schema([
                Forms\Components\Select::make('timing')
                    ->label(__('Timing'))
                    ->options([
                        'immediate' => __('Immediate'),
                        'delayed' => __('Delayed'),
                    ])
                    ->default('immediate')
                    ->native(false)
                    ->live()
                    ->helperText(__('When the notification should be sent.')),

                Forms\Components\TextInput::make('delay_minutes')
                    ->label(__('Delay (minutes)'))
                    ->numeric()
                    ->default(0)
                    ->visible(fn (Get $get) => $get('timing') === 'delayed')
                    ->helperText(__('Number of minutes to wait before sending.')),

                Forms\Components\Select::make('priority')
                    ->label(__('Priority'))
                    ->options([
                        'low' => __('Low'),
                        'medium' => __('Medium'),
                        'high' => __('High'),
                        'urgent' => __('Urgent'),
                    ])
                    ->default('medium')
                    ->native(false)
                    ->helperText(__('Priority level affects display and ordering.')),
            ]),

            Forms\Components\Toggle::make('is_active')
                ->label(__('Active'))
                ->default(true)
                ->inline(false)
                ->helperText(__('Only active notifications are dispatched.')),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RecipientsRelationManager::class,
            RelationManagers\ChannelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'edit' => Pages\EditWorkflowNotification::route('/{record}/edit'),
        ];
    }
}
