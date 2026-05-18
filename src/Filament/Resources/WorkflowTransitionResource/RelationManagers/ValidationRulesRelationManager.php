<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource\RelationManagers;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;

class ValidationRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'validationRules';

    protected static ?string $title = 'Validation Rules';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedShieldExclamation;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Validation Rules');
    }

    protected static function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('field_name')
                ->label(__('Field'))
                ->required()
                ->options(function (Forms\Components\Select $component) {
                    $livewire = $component->getLivewire();
                    $modelType = $livewire->ownerRecord?->workflow?->model_type ?? null;

                    return ModelDiscovery::getResourceComponentOptions($modelType);
                })
                ->searchable()
                ->native(false)
                ->helperText(__('The form field that must be validated before this transition can execute.')),

            Forms\Components\TagsInput::make('rules')
                ->label(__('Validation Rules'))
                ->required()
                ->placeholder(__('Type a rule and press Enter'))
                ->suggestions([
                    'required',
                    'filled',
                    'max:255',
                    'min:1',
                    'email',
                    'url',
                    'numeric',
                    'integer',
                    'string',
                    'date',
                    'boolean',
                    'gt:0',
                    'gte:0',
                    'regex:/^[a-zA-Z]+$/',
                    'digits:4',
                    'digits_between:2,10',
                    'decimal:0,2',
                ])
                ->helperText(__('Laravel validation rules applied to this field before the transition. The main form must pass these rules or the transition is blocked.')),

            Forms\Components\TextInput::make('custom_message')
                ->label(__('Custom Error Message'))
                ->maxLength(255)
                ->placeholder(__('e.g., Description is required before sending the order'))
                ->helperText(__('Optional message shown when validation fails. Leave empty to use the default Laravel message.')),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(static::getFormSchema())
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('field_name')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('field_name')
                    ->label(__('Field'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('rules')
                    ->label(__('Rules'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),

                Tables\Columns\TextColumn::make('custom_message')
                    ->label(__('Custom Message'))
                    ->color('gray')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(60),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth('3xl'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('Edit'))
                        ->icon(Heroicon::OutlinedPencil)
                        ->fillForm(fn (Model $record) => $record->toArray())
                        ->schema(static::getFormSchema())
                        ->modalWidth('3xl')
                        ->modalHeading(fn (Model $record) => $record->field_name)
                        ->modalSubmitActionLabel(__('Save'))
                        ->action(fn (Model $record, array $data) => $record->update($data)),

                    Action::make('delete')
                        ->label(__('Delete'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Model $record) => $record->delete()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
