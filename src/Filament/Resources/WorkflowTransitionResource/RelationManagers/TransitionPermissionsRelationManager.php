<?php

namespace RoBYCoNTe\FilamentFlow\Filament\Resources\WorkflowTransitionResource\RelationManagers;

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
use RoBYCoNTe\FilamentFlow\Contracts\PermissionResolver;
use RoBYCoNTe\FilamentFlow\Support\ModelDiscovery;
use Spatie\Permission\Models\Role;

class TransitionPermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    protected static ?string $title = 'Permissions';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedLockClosed;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Permissions');
    }

    protected static function getRoleOptions(): array
    {
        if (! class_exists(Role::class)) {
            return [];
        }

        return Role::pluck('name', 'name')->toArray();
    }

    protected static function getCustomPolicyOptions(): array
    {
        return ModelDiscovery::discoverImplementations(
            PermissionResolver::class,
        );
    }

    protected static function resolveData(array $data): array
    {
        $data['permission_value'] = match ($data['permission_type'] ?? null) {
            'role' => ! empty($data['role_values']) ? implode(',', $data['role_values']) : null,
            'custom' => $data['custom_class'] ?? null,
            default => null,
        };

        unset($data['role_values'], $data['custom_class']);

        return $data;
    }

    protected static function getFormSchema(): array
    {
        return [
            Forms\Components\Radio::make('permission_type')
                ->label(__('Type'))
                ->required()
                ->options([
                    'role' => __('Role'),
                    'assignment' => __('Assignment'),
                    'custom' => __('Custom'),
                ])
                ->descriptions([
                    'role' => __('Check if the user has one of the specified Spatie roles (e.g., admin, manager).'),
                    'assignment' => __('Check if the user is assigned to the record. No additional value needed.'),
                    'custom' => __('Use a custom PHP policy class to evaluate the permission with full control.'),
                ])
                ->live()
                ->columns(1),

            Forms\Components\Select::make('role_values')
                ->label(__('Roles'))
                ->multiple()
                ->options(fn () => static::getRoleOptions())
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get) => $get('permission_type') === 'role')
                ->helperText(__('Select one or more roles. The user needs at least one (or all, if "Require All" is enabled).'))
                ->afterStateHydrated(function (Forms\Components\Select $component, Get $get) {
                    $value = $get('permission_value');
                    if (is_string($value) && filled($value)) {
                        $component->state(array_map('trim', explode(',', $value)));
                    }
                }),

            Forms\Components\Select::make('custom_class')
                ->label(__('Policy Class'))
                ->options(fn () => static::getCustomPolicyOptions())
                ->searchable()
                ->native(false)
                ->visible(fn (Get $get) => $get('permission_type') === 'custom')
                ->createOptionForm([
                    Forms\Components\TextInput::make('custom_class')
                        ->label(__('Custom Class'))
                        ->required()
                        ->placeholder(__('App\\Policies\\OrderTransitionPolicy'))
                        ->helperText(__('Fully qualified class name implementing PermissionResolver.')),
                ])
                ->createOptionUsing(fn (array $data) => $data['custom_class'])
                ->helperText(__('Select a class that implements PermissionResolver, or type a custom FQCN.'))
                ->afterStateHydrated(function (Forms\Components\Select $component, Get $get) {
                    $value = $get('permission_value');
                    if ($get('permission_type') === 'custom' && filled($value)) {
                        $component->state($value);
                    }
                }),

            Forms\Components\Toggle::make('require_all')
                ->label(__('Require All'))
                ->inline(false)
                ->visible(fn (Get $get) => $get('permission_type') === 'role')
                ->helperText(__('When enabled, the user must have ALL selected roles. Otherwise, any single role grants access.')),
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
            ->recordTitleAttribute('permission_type')
            ->columns([
                Tables\Columns\TextColumn::make('permission_type')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'role' => 'info',
                        'assignment' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __(ucfirst($state))),

                Tables\Columns\TextColumn::make('permission_value')
                    ->label(__('Value'))
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->wrap(),

                Tables\Columns\IconColumn::make('require_all')
                    ->label(__('Require All'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth('3xl')
                    ->mutateDataUsing(fn (array $data) => static::resolveData($data)),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('Edit'))
                        ->icon(Heroicon::OutlinedPencil)
                        ->fillForm(fn (Model $record) => $record->toArray())
                        ->schema(static::getFormSchema())
                        ->modalWidth('3xl')
                        ->modalHeading(fn (Model $record) => __(ucfirst($record->permission_type)).($record->permission_value ? ": {$record->permission_value}" : ''))
                        ->modalSubmitActionLabel(__('Save'))
                        ->action(fn (Model $record, array $data) => $record->update(static::resolveData($data))),

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
