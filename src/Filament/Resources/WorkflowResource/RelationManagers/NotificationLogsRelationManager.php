<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\RelationManagers;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'notificationLogs';

    protected static ?string $title = 'Notification Logs';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedEnvelope;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Notification Logs');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('sent_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('sent_at')
                    ->label(__('Sent'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('Not sent')),

                Tables\Columns\TextColumn::make('notification.name')
                    ->label(__('Notification'))
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('channel')
                    ->label(__('Channel'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'mail' => 'info',
                        'database' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sent' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('user_id')
                    ->label(__('User ID'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('error_message')
                    ->label(__('Error'))
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->placeholder('—')
                    ->color('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'sent' => __('Sent'),
                        'pending' => __('Pending'),
                        'failed' => __('Failed'),
                    ]),

                Tables\Filters\SelectFilter::make('channel')
                    ->label(__('Channel'))
                    ->options([
                        'database' => __('Database'),
                        'mail' => __('Mail'),
                    ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
