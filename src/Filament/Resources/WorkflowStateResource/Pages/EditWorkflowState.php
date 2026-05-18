<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RoBYCoNTe\FilamentFlow\Exceptions\StateDeletionException;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource;

class EditWorkflowState extends EditRecord
{
    protected static string $resource = WorkflowStateResource::class;

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
        $workflow = $this->record->workflow;

        return __('Workflow').": {$workflow->name}";
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        $workflow = $this->record->workflow;

        return [
            WorkflowStateResource::getParentResource()::getUrl('edit', [
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
                ->schema(WorkflowStateResource::getGeneralFormSchema())
                ->modalWidth('3xl')
                ->modalHeading($this->record->label)
                ->modalDescription(__('Configure the basic properties of this state.'))
                ->modalSubmitActionLabel(__('Save'))
                ->action(function (array $data) {
                    $this->record->update($data);
                    $this->record->refresh();

                    Notification::make()
                        ->title(__('State updated'))
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->before(function () {
                    if ($this->record->transitionsFrom()->exists() || $this->record->transitionsTo()->exists()) {
                        throw new StateDeletionException;
                    }
                }),
        ];
    }
}
