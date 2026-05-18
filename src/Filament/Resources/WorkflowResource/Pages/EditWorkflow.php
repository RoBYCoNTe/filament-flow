<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowResource;
use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;

class EditWorkflow extends EditRecord
{
    protected static string $resource = WorkflowResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function getHeading(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        $model = class_basename($this->record->model_type);

        return "{$model} · {$this->record->state_column}";
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('settings')
                ->label(__('Settings'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->fillForm(fn () => $this->record->toArray())
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder(__('e.g., Order Processing'))
                        ->helperText(__('A descriptive name to identify this workflow (e.g., "Order Processing", "Invoice Approval").')),

                    Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('model_type')
                                ->label(__('Model'))
                                ->required()
                                ->options(fn () => ModelDiscovery::getOptions())
                                ->searchable()
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('state_column', null))
                                ->helperText(__('The Eloquent model this workflow will be attached to.')),

                            Forms\Components\Select::make('state_column')
                                ->label(__('State Column'))
                                ->required()
                                ->options(fn (Get $get) => ModelDiscovery::getStringColumnOptions($get('model_type')))
                                ->searchable()
                                ->native(false)
                                ->helperText(__('The varchar column on the model table that stores the current workflow state.')),
                        ]),

                    Forms\Components\KeyValue::make('metadata')
                        ->label(__('Metadata'))
                        ->helperText(__('Optional key-value pairs for storing additional workflow configuration.')),
                ])
                ->modalWidth('2xl')
                ->modalHeading(__('Workflow Configuration'))
                ->modalDescription(__('Define the basic settings for this workflow. Choose a name, select the Eloquent model it applies to, and specify the database column that tracks the current state.'))
                ->modalSubmitActionLabel(__('Save'))
                ->action(function (array $data) {
                    $this->record->update($data);
                    $this->record->refresh();

                    Notification::make()
                        ->title(__('Workflow updated'))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('toggle_active')
                ->label(fn () => $this->record->is_active ? __('Deactivate') : __('Activate'))
                ->icon(fn () => $this->record->is_active ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                ->color(fn () => $this->record->is_active ? 'warning' : 'success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['is_active' => ! $this->record->is_active]);
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
