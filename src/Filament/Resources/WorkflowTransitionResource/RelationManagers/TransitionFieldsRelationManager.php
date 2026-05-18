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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;

class TransitionFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Form Fields';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedRectangleGroup;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Form Fields');
    }

    protected static function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\TextInput::make('field_name')
                    ->label(__('Field Name'))
                    ->required()
                    ->maxLength(255)
                    ->placeholder(__('e.g., reason'))
                    ->helperText(__('Internal name used to reference this field in code and data.')),

                Forms\Components\TextInput::make('label')
                    ->label(__('Label'))
                    ->maxLength(255)
                    ->placeholder(__('e.g., Reason for cancellation'))
                    ->helperText(__('Display label shown to the user in the transition form.')),
            ]),

            Grid::make(2)->schema([
                Forms\Components\Select::make('field_type')
                    ->label(__('Type'))
                    ->required()
                    ->options([
                        'text' => __('Text — Single line input'),
                        'textarea' => __('Textarea — Multi-line input'),
                        'number' => __('Number — Numeric input'),
                        'select' => __('Select — Dropdown choice'),
                        'toggle' => __('Toggle — On/Off switch'),
                        'date' => __('Date — Date picker'),
                        'datetime' => __('DateTime — Date and time picker'),
                        'file' => __('File — File upload'),
                    ])
                    ->native(false)
                    ->helperText(__('The type of form input rendered in the transition dialog.')),

                Forms\Components\Select::make('model_attribute')
                    ->label(__('Model Attribute'))
                    ->options(function (Forms\Components\Select $component) {
                        $livewire = $component->getLivewire();
                        $modelType = $livewire->ownerRecord?->workflow?->model_type ?? null;

                        return ModelDiscovery::getColumnOptions($modelType);
                    })
                    ->searchable()
                    ->native(false)
                    ->helperText(__('The model column where this value is saved. Leave empty if it should not be persisted.')),
            ]),

            Grid::make(3)->schema([
                Forms\Components\Toggle::make('is_required')
                    ->label(__('Required'))
                    ->inline(false)
                    ->helperText(__('The user must fill this field to complete the transition.')),

                Forms\Components\Toggle::make('save_to_model')
                    ->label(__('Save to Model'))
                    ->default(true)
                    ->inline(false)
                    ->helperText(__('Persist the value to the model attribute after transition.')),

                Forms\Components\TextInput::make('sort_order')
                    ->label(__('Sort Order'))
                    ->numeric()
                    ->default(0)
                    ->helperText(__('Display order in the transition form.')),
            ]),
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
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('field_name')
                    ->label(__('Field'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('field_type')
                    ->label(__('Type'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state) => __($state)),

                Tables\Columns\TextColumn::make('label')
                    ->label(__('Label'))
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('model_attribute')
                    ->label(__('Attribute'))
                    ->fontFamily('mono')
                    ->color('gray')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('Req.'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('save_to_model')
                    ->label(__('Save'))
                    ->boolean(),
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
