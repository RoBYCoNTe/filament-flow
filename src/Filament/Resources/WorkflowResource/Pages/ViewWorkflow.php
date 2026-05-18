<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\Pages;

use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource;
use RoBYCoNTe\FilamentFlow\Models\Workflow;

class ViewWorkflow extends ViewRecord
{
    protected static string $resource = WorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Workflow Overview'))
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label(__('Name')),

                        Infolists\Components\TextEntry::make('model_type')
                            ->label(__('Model Class'))
                            ->copyable(),

                        Infolists\Components\TextEntry::make('state_column')
                            ->label(__('State Column'))
                            ->badge(),

                        Infolists\Components\IconEntry::make('is_active')
                            ->label(__('Active'))
                            ->boolean(),
                    ])
                    ->columns(4),

                Section::make(__('Workflow Diagram'))
                    ->schema([
                        Infolists\Components\ViewEntry::make('diagram')
                            ->hiddenLabel()
                            ->view('filament-flow::infolists.workflow-diagram'),
                    ]),

                Section::make(__('Statistics'))
                    ->schema([
                        Infolists\Components\TextEntry::make('states_count')
                            ->label(__('Total States'))
                            ->state(fn (Workflow $record): int => $record->states()->count())
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('transitions_count')
                            ->label(__('Total Transitions'))
                            ->state(fn (Workflow $record): int => $record->transitions()->count())
                            ->badge()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('notifications_count')
                            ->label(__('Total Notifications'))
                            ->state(fn (Workflow $record): int => $record->notifications()->count())
                            ->badge()
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('initial_state')
                            ->label(__('Initial State'))
                            ->state(fn (Workflow $record): string => $record->initialState()?->label ?? '-')
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columns(4),

                Section::make(__('Timestamps'))
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('Created'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label(__('Updated'))
                            ->dateTime(),
                    ])
                    ->columns()
                    ->collapsed(),
            ]);
    }
}
