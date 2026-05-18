<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource;

class EditWorkflowTransition extends EditRecord
{
    protected static string $resource = WorkflowTransitionResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function getHeading(): string
    {
        return $this->record->label;
    }

    public function getSubheading(): ?string
    {
        $from = $this->record->fromState?->label ?? __('Any');
        $to = $this->record->toState?->label ?? __('Action');

        return $this->record->to_state_id
            ? "{$from} → {$to}"
            : "{$from} ↻ {$to}";
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        $workflow = $this->record->workflow;

        return [
            WorkflowTransitionResource::getParentResource()::getUrl('edit', [
                'record' => $workflow,
            ], shouldGuessMissingParameters: true) => $workflow->name,
            '' => $this->record->label,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('settings')
                ->label(__('Settings'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->fillForm(fn () => $this->record->toArray())
                ->schema(WorkflowTransitionResource::getGeneralFormSchema($this->record->workflow_id))
                ->modalWidth('3xl')
                ->modalHeading($this->record->label)
                ->modalDescription(__('Configure the basic properties of this transition.'))
                ->modalSubmitActionLabel(__('Save'))
                ->action(function (array $data) {
                    $this->record->update($data);
                    $this->record->refresh();

                    Notification::make()
                        ->title(__('Transition updated'))
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
