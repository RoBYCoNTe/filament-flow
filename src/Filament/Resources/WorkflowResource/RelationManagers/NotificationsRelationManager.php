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
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowNotificationResource;

class NotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'notifications';

    protected static ?string $title = 'Notifications';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedBell;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Notifications');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(WorkflowNotificationResource::getGeneralFormSchema($this->getOwnerRecord()->getKey()))
            ->columns(1);
    }

    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('trigger_event')
                    ->label(__('Trigger'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'on_transition' => __('Transition'),
                        'on_state_enter' => __('State Enter'),
                        'on_state_exit' => __('State Exit'),
                        'on_assignment' => __('Assignment'),
                        'on_field_change' => __('Field Change'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'on_transition' => 'info',
                        'on_state_enter' => 'success',
                        'on_state_exit' => 'warning',
                        'on_assignment' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('Priority'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('recipients_count')
                    ->label(__('Recipients'))
                    ->counts('recipients')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('channels_count')
                    ->label(__('Channels'))
                    ->counts('channels')
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('trigger_event')
                    ->label(__('Trigger'))
                    ->options([
                        'on_transition' => __('On Transition'),
                        'on_state_enter' => __('On State Enter'),
                        'on_state_exit' => __('On State Exit'),
                        'on_assignment' => __('On Assignment'),
                        'on_field_change' => __('On Field Change'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add Notification')),
            ])
            ->recordUrl(fn (Model $record): string => WorkflowNotificationResource::getUrl('edit', [
                'workflow' => $this->ownerRecord,
                'record' => $record,
            ]))
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('Edit'))
                        ->icon(Heroicon::OutlinedPencil)
                        ->url(fn (Model $record): string => WorkflowNotificationResource::getUrl('edit', [
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
