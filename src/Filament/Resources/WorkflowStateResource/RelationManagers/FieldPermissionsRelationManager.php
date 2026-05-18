<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowStateResource\RelationManagers;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;
use RoBYCoNTe\FilamentFlow\Support\RuleOptions;

class FieldPermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Field Permissions';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedShieldCheck;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Field Permissions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(static::getFormSchema())
            ->columns(1);
    }

    protected static function getVisibilityOptions(): array
    {
        return [
            'visible' => __('Visible'),
            'hidden' => __('Hidden'),
        ];
    }

    protected static function getMutabilityOptions(): array
    {
        return [
            'editable' => __('Editable'),
            'readonly' => __('Read-only'),
            'locked' => __('Locked'),
        ];
    }

    protected static function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('field_name')
                ->label(__('Field'))
                ->required()
                ->options(function (Forms\Components\Select $component) {
                    $livewire = $component->getLivewire();
                    $modelType = $livewire->ownerRecord?->workflow?->model_type ?? null;

                    return ModelDiscovery::getResourceComponentOptions($modelType);
                })
                ->searchable()
                ->native(false),

            Grid::make(3)->schema([
                Forms\Components\Select::make('visibility')
                    ->label(__('Visibility'))
                    ->options(static::getVisibilityOptions())
                    ->default('visible')
                    ->native(false)
                    ->helperText(__('Whether this field is shown or hidden in this state.')),

                Forms\Components\Select::make('mutability')
                    ->label(__('Mutability'))
                    ->options(static::getMutabilityOptions())
                    ->default('editable')
                    ->native(false)
                    ->helperText(__('Editable — full access; Read-only — visible but not modifiable; Locked — completely hidden and blocked.')),

                Forms\Components\Toggle::make('is_required')
                    ->label(__('Required'))
                    ->inline(false)
                    ->helperText(__('Make this field mandatory in this state.')),
            ]),

            Forms\Components\TagsInput::make('validation_rules')
                ->label(__('Validation Rules'))
                ->placeholder(__('Type a rule and press Enter'))
                ->suggestions([
                    'max:255',
                    'min:1',
                    'email',
                    'url',
                    'numeric',
                    'integer',
                    'string',
                    'date',
                    'boolean',
                    'regex:/^[a-zA-Z]+$/',
                    'digits:4',
                    'digits_between:2,10',
                    'decimal:0,2',
                    'max_digits:10',
                    'min_digits:1',
                    'starts_with:prefix',
                    'ends_with:suffix',
                    'uppercase',
                    'lowercase',
                ])
                ->helperText(__('Laravel validation rules applied in this state. Select from suggestions or type custom rules (e.g., max:100, regex:/pattern/).')),

            Forms\Components\Repeater::make('roleOverrides')
                ->label(__('Conditional Overrides'))
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('role_name')
                        ->label(__('Role / Condition'))
                        ->required()
                        ->options(fn () => RuleOptions::forFieldOverrides())
                        ->searchable()
                        ->native(false)
                        ->createOptionForm([
                            Forms\Components\TextInput::make('role_name')
                                ->label(__('Custom Condition'))
                                ->required()
                                ->maxLength(255)
                                ->placeholder(__('e.g., @assigned:approver')),
                        ])
                        ->createOptionUsing(fn (array $data) => $data['role_name'])
                        ->columnSpanFull()
                        ->helperText(__('@owner — the user who owns the record; @assigned — any user assigned to the record; @assigned:type — assigned with a specific type (e.g. primary, secondary, viewer); Roles — static roles from the permission system.')),

                    Forms\Components\Select::make('visibility')
                        ->label(__('Visibility'))
                        ->options(static::getVisibilityOptions())
                        ->placeholder(__('Default'))
                        ->native(false),

                    Forms\Components\Select::make('mutability')
                        ->label(__('Mutability'))
                        ->options(static::getMutabilityOptions())
                        ->placeholder(__('Default'))
                        ->native(false),

                    Forms\Components\Toggle::make('is_required')
                        ->label(__('Required'))
                        ->inline(false),
                ])
                ->columns(3)
                ->columnSpanFull()
                ->defaultItems(0)
                ->reorderable()
                ->reorderableWithDragAndDrop()
                ->addActionLabel(__('Add Override'))
                ->itemLabel(function (array $state): ?string {
                    $role = $state['role_name'] ?? null;
                    if (! $role) {
                        return null;
                    }

                    $tags = [];
                    if ($state['visibility'] ?? null) {
                        $tags[] = __($state['visibility']);
                    }
                    if ($state['mutability'] ?? null) {
                        $tags[] = __($state['mutability']);
                    }
                    if ($state['is_required'] ?? false) {
                        $tags[] = __('required');
                    }

                    $summary = implode(', ', $tags);

                    return $summary !== '' ? "{$role} — {$summary}" : $role;
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('field_name')
            ->defaultSort('field_name')
            ->columns([
                Tables\Columns\TextColumn::make('field_name')
                    ->label(__('Field'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('visibility')
                    ->label(__('Visibility'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'visible' => 'success',
                        'hidden' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __($state)),

                Tables\Columns\TextColumn::make('mutability')
                    ->label(__('Mutability'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'editable' => 'success',
                        'readonly' => 'warning',
                        'locked' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __($state)),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('Required'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('overrides_summary')
                    ->label(__('Overrides'))
                    ->state(function (Model $record): string {
                        $record->loadMissing('roleOverrides');
                        $overrides = $record->roleOverrides;

                        if ($overrides->isEmpty()) {
                            return '—';
                        }

                        return $overrides->map(function ($override) {
                            $role = $override->role_name;
                            $parts = [];

                            if ($override->visibility) {
                                $parts[] = __($override->visibility);
                            }
                            if ($override->mutability) {
                                $parts[] = __($override->mutability);
                            }
                            if ($override->is_required) {
                                $parts[] = __('required');
                            }

                            $summary = implode(', ', $parts);

                            return $summary !== '' ? "{$role}: {$summary}" : $role;
                        })->implode(' | ');
                    })
                    ->wrap()
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth('3xl'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('Edit'))
                        ->icon(Heroicon::OutlinedPencil)
                        ->fillForm(fn (Model $record) => $record->toArray())
                        ->schema(static::getFormSchema())
                        ->modalWidth('3xl')
                        ->modalHeading(fn (Model $record) => $record->field_name)
                        ->modalSubmitActionLabel(__('Save'))
                        ->action(function (Model $record, array $data) {
                            $record->update($data);

                            if (isset($data['roleOverrides'])) {
                                $record->roleOverrides()->delete();
                                foreach ($data['roleOverrides'] as $override) {
                                    $record->roleOverrides()->create($override);
                                }
                            }
                        }),

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
