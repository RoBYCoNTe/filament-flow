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
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource;

class TransitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transitions';

    protected static ?string $title = 'Transitions';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedArrowsRightLeft;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Transitions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(WorkflowTransitionResource::getGeneralFormSchema($this->getOwnerRecord()->getKey()))
            ->columns(1);
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('fromState.label')
                    ->label(__('From'))
                    ->badge()
                    ->color(fn ($record) => $record->fromState?->color ?? 'gray')
                    ->placeholder(__('Any'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('arrow')
                    ->label('')
                    ->state(fn ($record) => $record->to_state_id ? '→' : '↻')
                    ->alignCenter()
                    ->width('30px'),

                Tables\Columns\TextColumn::make('toState.label')
                    ->label(__('To'))
                    ->badge()
                    ->color(fn ($record) => $record->toState?->color ?? 'gray')
                    ->placeholder(__('Action'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('label')
                    ->label(__('Label'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('fields_count')
                    ->label(__('Fields'))
                    ->counts('fields')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label(__('Perms'))
                    ->counts('permissions')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\IconColumn::make('requires_confirmation')
                    ->label(__('Confirm'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedQuestionMarkCircle)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('requires_reason')
                    ->label(__('Reason'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedChatBubbleLeft)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('info')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_state_id')
                    ->label(__('From State'))
                    ->options(fn () => $this->getOwnerRecord()
                        ->states()
                        ->pluck('label', 'id')),

                Tables\Filters\SelectFilter::make('to_state_id')
                    ->label(__('To State'))
                    ->options(fn () => $this->getOwnerRecord()
                        ->states()
                        ->pluck('label', 'id')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add Transition'))
                    ->disabled(fn () => $this->getOwnerRecord()->states()->count() < 2)
                    ->tooltip(fn () => $this->getOwnerRecord()->states()->count() < 2
                        ? __('You need at least 2 states to create a transition')
                        : null),
            ])
            ->recordUrl(fn (Model $record): string => WorkflowTransitionResource::getUrl('edit', [
                'workflow' => $this->ownerRecord,
                'record' => $record,
            ]))
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('Edit'))
                        ->icon(Heroicon::OutlinedPencil)
                        ->url(fn (Model $record): string => WorkflowTransitionResource::getUrl('edit', [
                            'workflow' => $this->ownerRecord,
                            'record' => $record,
                        ])),
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
