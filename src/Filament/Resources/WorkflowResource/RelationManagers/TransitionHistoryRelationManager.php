<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\RelationManagers;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class TransitionHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'transitionHistory';

    protected static ?string $title = 'Transition History';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedClock;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Transition History');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('from_state_label')
                    ->label(__('From'))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('arrow')
                    ->label('')
                    ->state('→')
                    ->alignCenter()
                    ->width('30px'),

                Tables\Columns\TextColumn::make('to_state_label')
                    ->label(__('To'))
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('user_name')
                    ->label(__('User'))
                    ->searchable()
                    ->placeholder(__('System')),

                Tables\Columns\TextColumn::make('reason')
                    ->label(__('Reason'))
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->reason)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label(__('Duration'))
                    ->formatStateUsing(fn (?int $state): string => $state !== null
                        ? ($state >= 3600
                            ? gmdate('H:i:s', $state)
                            : ($state >= 60
                                ? gmdate('i:s', $state)
                                : $state.'s'))
                        : '—')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('has_metadata')
                    ->label(__('Meta'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedDocumentText)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('info')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('has_snapshot')
                    ->label(__('Snap'))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCamera)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('info')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('to_state')
                    ->label(__('To State'))
                    ->options(fn () => $this->getOwnerRecord()
                        ->states()
                        ->pluck('label', 'name')),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
