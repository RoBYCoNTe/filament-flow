<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowNotificationResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowNotificationResource;

class EditWorkflowNotification extends EditRecord
{
    protected static string $resource = WorkflowNotificationResource::class;

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
        $trigger = match ($this->record->trigger_event) {
            'on_transition' => __('On Transition'),
            'on_state_enter' => __('On State Enter'),
            'on_state_exit' => __('On State Exit'),
            'on_assignment' => __('On Assignment'),
            'on_field_change' => __('On Field Change'),
            default => $this->record->trigger_event,
        };

        $detail = '';
        if ($this->record->transition_id && $this->record->transition) {
            $t = $this->record->transition;
            $detail = " — {$t->fromState?->label} → {$t->toState?->label}";
        } elseif ($this->record->state_id && $this->record->state) {
            $detail = " — {$this->record->state->label}";
        }

        return "{$trigger}{$detail}";
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        $workflow = $this->record->workflow;

        return [
            WorkflowNotificationResource::getParentResource()::getUrl('edit', [
                'record' => $workflow,
            ], shouldGuessMissingParameters: true) => $workflow->name,
            '' => $this->record->name,
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
                ->schema(WorkflowNotificationResource::getGeneralFormSchema($this->record->workflow_id))
                ->modalWidth('3xl')
                ->modalHeading($this->record->name)
                ->modalDescription(__('Configure the basic properties of this notification.'))
                ->modalSubmitActionLabel(__('Save'))
                ->action(function (array $data) {
                    $this->record->update($data);
                    $this->record->refresh();

                    Notification::make()
                        ->title(__('Notification updated'))
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
