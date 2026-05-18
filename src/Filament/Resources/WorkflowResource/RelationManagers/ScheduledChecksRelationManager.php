<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\RelationManagers;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class ScheduledChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduledChecks';

    protected static ?string $title = 'Scheduled Checks';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedClock;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Scheduled Checks');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('e.g., Expiration Warning'))
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('description')
                    ->label(__('Description'))
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Select::make('state_id')
                    ->label(__('State Filter'))
                    ->options(fn () => $this->getOwnerRecord()
                        ->states()
                        ->pluck('label', 'id'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->placeholder(__('All states'))
                    ->helperText(__('Only check records in this state. Leave empty for all states.')),

                Forms\Components\Select::make('frequency')
                    ->label(__('Frequency'))
                    ->required()
                    ->options([
                        'every_minute' => __('Every Minute'),
                        'every_five_minutes' => __('Every 5 Minutes'),
                        'hourly' => __('Hourly'),
                        'daily' => __('Daily'),
                        'weekly' => __('Weekly'),
                    ])
                    ->default('daily')
                    ->native(false),

                Forms\Components\Select::make('condition_type')
                    ->label(__('Condition Type'))
                    ->required()
                    ->options([
                        'date_offset' => __('Date Offset'),
                        'field_compare' => __('Field Compare'),
                        'custom_class' => __('Custom Class'),
                    ])
                    ->native(false)
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\KeyValue::make('condition_config')
                    ->label(__('Condition Config'))
                    ->required()
                    ->helperText(fn (Forms\Components\KeyValue $component): string => match ($component->getRecord()?->condition_type ?? '') {
                        'date_offset' => __('Keys: field, offset_days, operator (<=, >=, =)'),
                        'field_compare' => __('Key: conditions (JSON array)'),
                        'custom_class' => __('Key: class (FQCN with evaluate method)'),
                        default => __('Configure the condition parameters.'),
                    })
                    ->columnSpanFull(),

                Forms\Components\Select::make('action_type')
                    ->label(__('Action Type'))
                    ->required()
                    ->options([
                        'notification' => __('Send Notification'),
                        'transition' => __('Execute Transition'),
                        'side_effect' => __('Execute Side Effect'),
                    ])
                    ->native(false)
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\KeyValue::make('action_config')
                    ->label(__('Action Config'))
                    ->required()
                    ->helperText(fn (Forms\Components\KeyValue $component): string => match ($component->getRecord()?->action_type ?? '') {
                        'notification' => __('Key: notification_id'),
                        'transition' => __('Keys: to_state, force (true/false)'),
                        'side_effect' => __('Key: transition_id'),
                        default => __('Configure the action parameters.'),
                    })
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('once_per_record')
                    ->label(__('Once Per Record'))
                    ->helperText(__('Only execute once per record (won\'t re-trigger).'))
                    ->inline(false),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('state.label')
                    ->label(__('State'))
                    ->badge()
                    ->color(fn ($record) => $record->state?->color ?? 'gray')
                    ->placeholder(__('All')),

                Tables\Columns\TextColumn::make('condition_type')
                    ->label(__('Condition'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'date_offset' => 'info',
                        'field_compare' => 'success',
                        'custom_class' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('action_type')
                    ->label(__('Action'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'notification' => 'warning',
                        'transition' => 'primary',
                        'side_effect' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('frequency')
                    ->label(__('Frequency'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'every_minute' => __('Every Minute'),
                        'every_five_minutes' => __('5 Min'),
                        'hourly' => __('Hourly'),
                        'daily' => __('Daily'),
                        'weekly' => __('Weekly'),
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('once_per_record')
                    ->label(__('Once'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_checked_at')
                    ->label(__('Last Check'))
                    ->dateTime()
                    ->placeholder(__('Never'))
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add Scheduled Check')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
