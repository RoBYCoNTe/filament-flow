<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource\RelationManagers;

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

class SideEffectsRelationManager extends RelationManager
{
    protected static string $relationship = 'sideEffects';

    protected static ?string $title = 'Side Effects';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedBolt;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Side Effects');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('effect_type')
                    ->label(__('Effect Type'))
                    ->required()
                    ->options([
                        'set_field' => __('Set Field Value'),
                        'set_timestamp' => __('Set Timestamp'),
                        'clear_field' => __('Clear Field'),
                        'increment' => __('Increment'),
                        'custom_class' => __('Custom Class'),
                    ])
                    ->native(false)
                    ->live()
                    ->helperText(__('The type of side effect to execute after the transition.')),

                Forms\Components\TextInput::make('field_name')
                    ->label(__('Field Name'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder('closed_date')
                    ->helperText(__('The model attribute to modify.'))
                    ->hidden(fn (Forms\Components\TextInput $component): bool => ($component->getRecord()?->effect_type ?? $component->getState()) === 'custom_class'),

                Forms\Components\TextInput::make('value_expression')
                    ->label(fn (Forms\Components\TextInput $component): string => match ($component->getRecord()?->effect_type ?? '') {
                        'custom_class' => __('Class Name'),
                        'set_timestamp' => __('Timestamp Expression'),
                        default => __('Value Expression'),
                    })
                    ->maxLength(255)
                    ->placeholder(fn (Forms\Components\TextInput $component): string => match ($component->getRecord()?->effect_type ?? '') {
                        'set_field' => 'field:source_field or literal value',
                        'set_timestamp' => 'now',
                        'increment' => '1',
                        'custom_class' => 'App\\SideEffects\\MyEffect',
                        default => '',
                    })
                    ->helperText(fn (Forms\Components\TextInput $component): string => match ($component->getRecord()?->effect_type ?? '') {
                        'set_field' => __('Use "field:name" to copy from another field, or a literal value.'),
                        'set_timestamp' => __('Use "now" for current time, or leave empty.'),
                        'increment' => __('Amount to increment by (default: 1).'),
                        'custom_class' => __('Fully qualified class name with an execute(Model) method.'),
                        default => '',
                    }),

                Forms\Components\TextInput::make('sort_order')
                    ->label(__('Sort Order'))
                    ->numeric()
                    ->default(0)
                    ->helperText(__('Execution order (lower = first).')),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('field_name')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('effect_type')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'set_field' => 'info',
                        'set_timestamp' => 'success',
                        'clear_field' => 'warning',
                        'increment' => 'primary',
                        'custom_class' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'set_field' => __('Set Field'),
                        'set_timestamp' => __('Timestamp'),
                        'clear_field' => __('Clear'),
                        'increment' => __('Increment'),
                        'custom_class' => __('Custom'),
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('field_name')
                    ->label(__('Field'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('value_expression')
                    ->label(__('Value'))
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add Side Effect')),
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
