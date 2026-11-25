<?php

namespace RoBYCoNTe\FilamentFlow\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Schemas\Components\Livewire;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Contracts\AccessControlGate;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;
use RoBYCoNTe\FilamentFlow\Support\AccessGate;

/**
 * The key to the room where the work is handed out: one button that opens the assignment
 * panel — who holds the record, who works on it, and what each one may do — as a dialog.
 *
 * Mount it wherever a record exists (a table row, a record page, a page that can answer for
 * the record); the same panel may also live inside a form as a plain section, by embedding
 * `AssignmentManager` directly: dialog and section are two doors to one room.
 *
 * ```php
 * AccessControlAction::make()
 *     ->roleLabels(RoleLabels::map())
 *     ->superAdminOnly(),
 * ```
 *
 * Who may use it is a rule of the host: `superAdminOnly()` for the simplest one, `gate()` for
 * any other.
 */
class AccessControlAction extends Action
{
    protected bool $restrictToSuperAdmins = false;

    /** @var class-string<AccessControlGate>|null */
    protected ?string $gate = null;

    /**
     * @var array<string, string>|Closure
     */
    protected array|Closure $roleLabels = [];

    protected Closure|Model|string|int|null $accessRecord = null;

    protected ?string $accessRecordType = null;

    public static function getDefaultName(): ?string
    {
        return 'accessControl';
    }

    public function superAdminOnly(bool $condition = true): static
    {
        $this->restrictToSuperAdmins = $condition;

        return $this;
    }

    /**
     * Who may use the door and the panel behind it, by a rule of the host: the class of an
     * `AccessControlGate`. It is a class, not a closure, because the panel asks again on every
     * request and has to build the gate by itself; a closure could not travel with it.
     *
     * @param  class-string<AccessControlGate>  $gate
     */
    public function gate(string $gate): static
    {
        $this->gate = $gate;

        return $this;
    }

    /**
     * How the roles of the assigned people read: the words of the host, keyed by role name.
     *
     * @param  array<string, string>|Closure  $labels
     */
    public function roleLabels(array|Closure $labels): static
    {
        $this->roleLabels = $labels;

        return $this;
    }

    /**
     * The record the panel talks about, when the action does not sit beside it
     * (a page that keeps the record in a property, for example). A model, its key,
     * or the answer of a closure; the class of the record comes from
     * `accessRecordType()`, or from the record the action sits beside.
     */
    public function accessRecord(Closure|Model|string|int|null $record): static
    {
        $this->accessRecord = $record;

        return $this;
    }

    public function accessRecordType(?string $type): static
    {
        $this->accessRecordType = $type;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-flow::messages.access_control'));
        $this->icon('heroicon-m-shield-check');
        $this->color('gray');
        $this->modalHeading(__('filament-flow::messages.access_control'));
        $this->modalWidth('3xl');
        $this->modalSubmitAction(false);
        $this->modalCancelAction(false);

        // The panel manages itself: it shows its own buttons to whoever may use it, and
        // answers for the record it was given. The action is only the door.
        $this->visible(fn (): bool => AccessGate::resolve($this->gate, $this->restrictToSuperAdmins)
            ->canManage(auth()->user(), $this->resolvePanelRecord($this->getRecord())));

        $this->schema(function (?Model $record): array {
            $panelRecord = $this->resolvePanelRecord($record);

            return [
                Livewire::make(AssignmentManager::class, [
                    'record' => $panelRecord?->getKey(),
                    // The class itself, never a morph alias: the panel finds the record
                    // with `{$type}::find()`.
                    'recordType' => $panelRecord !== null ? $panelRecord::class : null,
                    'roleLabels' => $this->evaluate($this->roleLabels) ?? [],
                    'superAdminOnly' => $this->restrictToSuperAdmins,
                    'gate' => $this->gate,
                    // A key of its own: a page may already carry an assignment panel of its
                    // own (the same component embedded as a section), and Livewire keys a
                    // child by the parent — the second instance with the same key comes back
                    // as an empty placeholder, and the modal opens empty.
                ])->key($this->getName().'.assignment-manager'),
            ];
        });
    }

    /** The record the panel was promised: the one the action sits beside, or one given by hand. */
    protected function resolvePanelRecord(?Model $record): ?Model
    {
        $resolved = $this->accessRecord instanceof Closure
            ? $this->evaluate($this->accessRecord)
            : $this->accessRecord;

        if ($resolved instanceof Model) {
            return $resolved;
        }

        if ((is_string($resolved) || is_int($resolved)) && filled($resolved)) {
            $type = $this->accessRecordType ?? $record?->getMorphClass();

            if (is_string($type) && class_exists($type)) {
                /** @var Model|null */
                return $type::find($resolved);
            }
        }

        return $record;
    }
}
