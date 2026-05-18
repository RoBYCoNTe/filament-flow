<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowNotificationResource\RelationManagers;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;
use Spatie\Permission\Models\Role;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Recipients';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedUsers;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Recipients');
    }

    protected static function getFormSchema(): array
    {
        return [
            Forms\Components\Radio::make('recipient_type')
                ->label(__('Recipient Type'))
                ->required()
                ->options([
                    'role' => __('Role'),
                    'user' => __('Specific User'),
                    'assigned_users' => __('Assigned Users'),
                    'record_owner' => __('Record Owner'),
                    'state_actors' => __('State Actors'),
                    'all_involved' => __('All Involved'),
                    'involvement_type' => __('By Involvement Type'),
                    'custom_field' => __('Custom Field'),
                    'custom_query' => __('Custom Query'),
                    'custom_class' => __('Custom Class'),
                ])
                ->descriptions([
                    'role' => __('Users with specific Spatie role(s).'),
                    'user' => __('Specific user(s) by ID.'),
                    'assigned_users' => __('Users assigned to the record, optionally filtered by assignment type.'),
                    'record_owner' => __('The user who owns the record (via owner_field).'),
                    'state_actors' => __('Users who performed transitions in specific states.'),
                    'all_involved' => __('All users involved with the record (assignments, transitions, owner).'),
                    'involvement_type' => __('Users involved with a specific role/type (e.g., reviewer).'),
                    'custom_field' => __('Read user ID(s) from a specific model field.'),
                    'custom_query' => __('Custom SQL query to resolve recipients.'),
                    'custom_class' => __('A custom PHP class that resolves recipients.'),
                ])
                ->live()
                ->columns(2),

            Forms\Components\Select::make('recipient_config.roles')
                ->label(__('Roles'))
                ->multiple()
                ->options(function () {
                    if (! class_exists(Role::class)) {
                        return [];
                    }

                    return Role::pluck('name', 'name')->toArray();
                })
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get) => $get('recipient_type') === 'role')
                ->helperText(__('Select one or more roles. All users with any of these roles will be notified.')),

            Forms\Components\TextInput::make('recipient_config.user_ids')
                ->label(__('User IDs'))
                ->placeholder(__('e.g., 1,2,3'))
                ->visible(fn (Get $get) => $get('recipient_type') === 'user')
                ->helperText(__('Comma-separated list of user IDs.')),

            Forms\Components\Select::make('recipient_config.types')
                ->label(__('Assignment Types'))
                ->multiple()
                ->options([
                    'primary' => __('Primary'),
                    'secondary' => __('Secondary'),
                    'viewer' => __('Viewer'),
                ])
                ->native(false)
                ->visible(fn (Get $get) => $get('recipient_type') === 'assigned_users')
                ->helperText(__('Optional: filter by assignment type. Leave empty for all assigned users.')),

            Forms\Components\Select::make('recipient_config.owner_field')
                ->label(__('Owner Field'))
                ->options(function (Forms\Components\Select $component) {
                    $livewire = $component->getLivewire();
                    $modelType = $livewire->ownerRecord?->workflow?->model_type ?? null;

                    return ModelDiscovery::getColumnOptions($modelType);
                })
                ->searchable()
                ->native(false)
                ->placeholder('user_id')
                ->visible(fn (Get $get) => $get('recipient_type') === 'record_owner')
                ->helperText(__('The model column that stores the owner user ID. Defaults to the configured owner_field.')),

            Forms\Components\Select::make('recipient_config.states')
                ->label(__('States'))
                ->multiple()
                ->options(function (Forms\Components\Select $component) {
                    $livewire = $component->getLivewire();
                    $workflowId = $livewire->ownerRecord?->workflow_id ?? null;

                    if (! $workflowId) {
                        return [];
                    }

                    return WorkflowState::where('workflow_id', $workflowId)
                        ->pluck('label', 'name')
                        ->toArray();
                })
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get) => $get('recipient_type') === 'state_actors')
                ->helperText(__('Select one or more states. Users who performed transitions in these states will be notified.')),

            Forms\Components\Radio::make('recipient_config.involvement_type')
                ->label(__('Involvement Type'))
                ->options([
                    'reviewer' => __('Reviewer'),
                    'approver' => __('Approver'),
                    'watcher' => __('Watcher'),
                    'contributor' => __('Contributor'),
                    'stakeholder' => __('Stakeholder'),
                ])
                ->descriptions([
                    'reviewer' => __('A user who reviews the record and provides feedback before approval.'),
                    'approver' => __('A user with authority to approve or reject the record.'),
                    'watcher' => __('A user who monitors progress but does not actively participate.'),
                    'contributor' => __('A user who actively works on or edits the record.'),
                    'stakeholder' => __('A user with a business interest in the outcome, typically management or clients.'),
                ])
                ->visible(fn (Get $get) => $get('recipient_type') === 'involvement_type')
                ->columns(1),

            Forms\Components\Select::make('recipient_config.field')
                ->label(__('Model Field'))
                ->options(function (Forms\Components\Select $component) {
                    $livewire = $component->getLivewire();
                    $modelType = $livewire->ownerRecord?->workflow?->model_type ?? null;

                    return ModelDiscovery::getColumnOptions($modelType);
                })
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get) => $get('recipient_type') === 'custom_field')
                ->helperText(__('The model column containing a user ID, array of IDs, or a relationship name.')),

            Forms\Components\Textarea::make('recipient_config.query')
                ->label(__('SQL Query'))
                ->placeholder(__('SELECT user_id FROM ... WHERE record_id = :record_id'))
                ->rows(3)
                ->visible(fn (Get $get) => $get('recipient_type') === 'custom_query')
                ->helperText(__('Custom SQL query. Use :record_id, :record_type as placeholders.')),

            Forms\Components\Select::make('recipient_config.class')
                ->label(__('Class'))
                ->options(function () {
                    $classes = [];

                    $paths = [
                        app_path('Services'),
                        app_path('Resolvers'),
                        app_path('Workflow'),
                    ];

                    foreach ($paths as $path) {
                        if (! is_dir($path)) {
                            continue;
                        }

                        foreach (File::allFiles($path) as $file) {
                            if ($file->getExtension() !== 'php') {
                                continue;
                            }

                            $contents = file_get_contents($file->getPathname());
                            $namespace = null;
                            if (preg_match('/namespace\s+([^;]+);/', $contents, $m)) {
                                $namespace = $m[1];
                            }
                            if (preg_match('/class\s+(\w+)/', $contents, $m)) {
                                $fqcn = $namespace ? "{$namespace}\\{$m[1]}" : $m[1];
                                if (class_exists($fqcn) && method_exists($fqcn, 'resolve')) {
                                    $classes[$fqcn] = class_basename($fqcn)." ({$fqcn})";
                                }
                            }
                        }
                    }

                    return $classes;
                })
                ->searchable()
                ->native(false)
                ->createOptionForm([
                    Forms\Components\TextInput::make('class')
                        ->label(__('Custom Class'))
                        ->required()
                        ->placeholder(__('App\\Services\\CustomRecipientResolver'))
                        ->helperText(__('Fully qualified class name with a resolve($record) method.')),
                ])
                ->createOptionUsing(fn (array $data) => $data['class'])
                ->visible(fn (Get $get) => $get('recipient_type') === 'custom_class')
                ->helperText(__('Select a class with a resolve() method, or type a custom FQCN.')),

            Forms\Components\TextInput::make('recipient_config.method')
                ->label(__('Method'))
                ->placeholder('resolve')
                ->visible(fn (Get $get) => $get('recipient_type') === 'custom_class')
                ->helperText(__('Method name to call on the class. Defaults to resolve.')),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(static::getFormSchema())
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recipient_type')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('recipient_type')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'role' => 'info',
                        'user' => 'primary',
                        'assigned_users' => 'warning',
                        'record_owner' => 'success',
                        'all_involved' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __(str_replace('_', ' ', ucfirst($state)))),

                Tables\Columns\TextColumn::make('config_summary')
                    ->label(__('Details'))
                    ->state(function (Model $record): string {
                        $config = $record->recipient_config ?? [];

                        return match ($record->recipient_type) {
                            'role' => implode(', ', $config['roles'] ?? []),
                            'user' => $config['user_ids'] ?? '—',
                            'assigned_users' => implode(', ', $config['types'] ?? ['all']),
                            'record_owner' => $config['owner_field'] ?? 'user_id',
                            'state_actors' => $config['states'] ?? '—',
                            'involvement_type' => $config['involvement_type'] ?? '—',
                            'custom_field' => $config['field'] ?? '—',
                            'custom_class' => class_basename($config['class'] ?? '—'),
                            default => '—',
                        };
                    })
                    ->color('gray')
                    ->wrap(),
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
                        ->modalHeading(fn (Model $record) => __(str_replace('_', ' ', ucfirst($record->recipient_type))))
                        ->modalSubmitActionLabel(__('Save'))
                        ->action(fn (Model $record, array $data) => $record->update($data)),

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
