<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource\RelationManagers;

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
use RoBYCoNTe\FilamentFlow\Support\RuleOptions;

class AccessRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'accessRules';

    protected static ?string $title = 'Access Rules';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedLockClosed;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Access Rules');
    }

    protected static function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('access_type')
                ->label(__('Access Type'))
                ->required()
                ->options([
                    'view' => __('View — Can see the record'),
                    'edit' => __('Edit — Can modify the record'),
                    'transition' => __('Transition — Can change state'),
                    'create' => __('Create — Can create new records (initial state only)'),
                ])
                ->native(false)
                ->helperText(__('What action this rule allows. A record can have multiple rules per access type.'))
                ->columnSpanFull(),

            Forms\Components\Select::make('rule')
                ->label(__('Who'))
                ->required()
                ->options(fn () => RuleOptions::forAccessRules())
                ->native(false)
                ->searchable()
                ->createOptionForm([
                    Forms\Components\TextInput::make('rule')
                        ->label(__('Custom Rule'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder(__('e.g., role:admin,manager or @assigned:reviewer')),
                ])
                ->createOptionUsing(fn (array $data) => $data['rule'])
                ->helperText(__('Who is allowed this access. Use role:name for roles, permission:name for permissions, or combine roles with commas (e.g., role:admin,editor).'))
                ->columnSpanFull(),

            Grid::make(3)->schema([
                Forms\Components\Select::make('operator')
                    ->label(__('Operator'))
                    ->options([
                        'or' => __('OR — Any rule grants access'),
                        'and' => __('AND — All rules must match'),
                    ])
                    ->default('or')
                    ->native(false)
                    ->helperText(__('How this rule combines with other rules of the same access type.')),

                Forms\Components\TextInput::make('priority')
                    ->label(__('Priority'))
                    ->numeric()
                    ->default(0)
                    ->helperText(__('Higher values are evaluated first. Use this to control rule ordering.')),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true)
                    ->inline(false)
                    ->helperText(__('Disable to temporarily suspend this rule without deleting it.')),
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
            ->recordTitleAttribute('access_type')
            ->defaultSort('priority', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('access_type')
                    ->label(__('Access Type'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'view' => 'info',
                        'edit' => 'warning',
                        'transition' => 'primary',
                        'create' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __(ucfirst($state)))
                    ->sortable(),

                Tables\Columns\TextColumn::make('rule')
                    ->label(__('Who'))
                    ->searchable()
                    ->fontFamily('mono')
                    ->wrap(),

                Tables\Columns\TextColumn::make('operator')
                    ->label(__('Op.'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
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
                        ->modalHeading(fn (Model $record) => $record->access_type.' — '.$record->rule)
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
