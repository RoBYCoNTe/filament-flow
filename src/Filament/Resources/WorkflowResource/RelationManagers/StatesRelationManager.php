<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\RelationManagers;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Exceptions\StateDeletionException;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource;

class StatesRelationManager extends RelationManager
{
    protected static string $relationship = 'states';

    protected static ?string $title = 'States';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedCircleStack;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('States');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(WorkflowStateResource::getGeneralFormSchema())
            ->columns(3);
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('#'))
                    ->sortable()
                    ->width('50px'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('Key'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('label')
                    ->label(__('Label'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->color ?? 'gray'),

                Tables\Columns\IconColumn::make('is_initial')
                    ->label(__('Initial'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedPlay)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_final')
                    ->label(__('Final'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedStop)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('danger')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('fields_count')
                    ->label(__('Fields'))
                    ->counts('fields')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('access_rules_count')
                    ->label(__('Rules'))
                    ->counts('accessRules')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('transitionsFrom')
                    ->label(__('Out'))
                    ->state(fn (Model $record) => $record->transitionsFrom()->count())
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('transitionsTo')
                    ->label(__('In'))
                    ->state(fn (Model $record) => $record->transitionsTo()->count())
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_initial')
                    ->label(__('Initial State')),

                Tables\Filters\TernaryFilter::make('is_final')
                    ->label(__('Final State')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add State')),
            ])
            ->recordUrl(fn (Model $record): string => WorkflowStateResource::getUrl('edit', [
                'workflow' => $this->ownerRecord,
                'record' => $record,
            ]))
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('Edit'))
                        ->icon(Heroicon::OutlinedPencil)
                        ->url(fn (Model $record): string => WorkflowStateResource::getUrl('edit', [
                            'workflow' => $this->ownerRecord,
                            'record' => $record,
                        ])),
                    Action::make('delete')
                        ->label(__('Delete'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Model $record) {
                            if ($record->transitionsFrom()->exists() || $record->transitionsTo()->exists()) {
                                throw new StateDeletionException;
                            }
                            $record->delete();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
