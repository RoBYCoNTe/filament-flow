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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ChannelsRelationManager extends RelationManager
{
    protected static string $relationship = 'channels';

    protected static ?string $title = 'Channels & Templates';

    protected static string|null|BackedEnum $icon = Heroicon::OutlinedPaperAirplane;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Channels & Templates');
    }

    protected static function getTemplateSchema(): array
    {
        return [
            Forms\Components\TextInput::make('subject')
                ->label(__('Subject'))
                ->maxLength(255)
                ->placeholder(__('e.g., Order {{order_number}} — Status Update'))
                ->helperText(__('Email subject line. Supports template variables. Ignored for database channel.'))
                ->columnSpanFull(),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('title')
                    ->label(__('Title'))
                    ->maxLength(255)
                    ->placeholder(__('e.g., Order Updated'))
                    ->helperText(__('Notification title. Supports template variables.')),

                Forms\Components\Select::make('template_engine')
                    ->label(__('Template Engine'))
                    ->options([
                        'plain' => __('Plain — {{variable}} substitution'),
                        'blade' => __('Blade — Full Laravel Blade syntax'),
                        'mustache' => __('Mustache — {{var}} and {{{raw}}}'),
                    ])
                    ->default('plain')
                    ->native(false)
                    ->helperText(__('How template variables are rendered.')),
            ]),

            Forms\Components\Textarea::make('body')
                ->label(__('Body'))
                ->required()
                ->rows(5)
                ->placeholder(__('e.g., The order {{order_number}} has been moved to {{to_state_label}}.'))
                ->helperText(__('Notification body. Available variables: {{record_id}}, {{record_type}}, {{from_state_label}}, {{to_state_label}}, {{app_name}}, and all model attributes.'))
                ->columnSpanFull(),

            Grid::make(2)->schema([
                Forms\Components\TextInput::make('action_text')
                    ->label(__('Action Button Text'))
                    ->placeholder(__('e.g., View Details'))
                    ->helperText(__('Text for the call-to-action button.')),

                Forms\Components\TextInput::make('action_url')
                    ->label(__('Action URL'))
                    ->placeholder(__('e.g., /orders/{{record_id}}'))
                    ->helperText(__('URL for the action button. Supports template variables.')),
            ]),

            Forms\Components\Select::make('format')
                ->label(__('Format'))
                ->options([
                    'html' => __('HTML'),
                    'markdown' => __('Markdown'),
                    'plain' => __('Plain Text'),
                ])
                ->default('html')
                ->native(false)
                ->helperText(__('Output format for the body content.')),
        ];
    }

    protected static function getFormSchema(): array
    {
        return [
            Forms\Components\Radio::make('channel_type')
                ->label(__('Channel'))
                ->required()
                ->options([
                    'database' => __('Database'),
                    'mail' => __('Mail'),
                ])
                ->descriptions([
                    'database' => __('In-app notification stored in the database. Shown in Filament\'s notification bell.'),
                    'mail' => __('Email notification sent via configured mail driver.'),
                ])
                ->columns(2),

            Forms\Components\Toggle::make('is_active')
                ->label(__('Active'))
                ->default(true)
                ->inline(false)
                ->helperText(__('Only active channels will be used when sending this notification.')),

            Forms\Components\Repeater::make('templates')
                ->label(__('Template'))
                ->relationship()
                ->schema(static::getTemplateSchema())
                ->columns(1)
                ->columnSpanFull()
                ->defaultItems(1)
                ->maxItems(1)
                ->addActionLabel(__('Add Template'))
                ->hiddenLabel(),
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
            ->recordTitleAttribute('channel_type')
            ->columns([
                Tables\Columns\TextColumn::make('channel_type')
                    ->label(__('Channel'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'database' => 'info',
                        'mail' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __(ucfirst($state))),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('template_summary')
                    ->label(__('Template'))
                    ->state(function (Model $record): string {
                        $record->loadMissing('templates');
                        $template = $record->templates->first();

                        if (! $template) {
                            return __('No template configured');
                        }

                        $parts = [];
                        if ($template->subject) {
                            $parts[] = __('Subject').": {$template->subject}";
                        }
                        if ($template->title) {
                            $parts[] = __('Title').": {$template->title}";
                        }
                        $parts[] = str($template->body)->limit(80);

                        return implode(' | ', $parts);
                    })
                    ->color('gray')
                    ->wrap()
                    ->limit(120),
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
                        ->fillForm(function (Model $record) {
                            $data = $record->toArray();
                            $record->loadMissing('templates');
                            $data['templates'] = $record->templates->toArray();

                            return $data;
                        })
                        ->schema(static::getFormSchema())
                        ->modalWidth('3xl')
                        ->modalHeading(fn (Model $record) => __(ucfirst($record->channel_type)).' '.__('Channel'))
                        ->modalSubmitActionLabel(__('Save'))
                        ->action(function (Model $record, array $data) {
                            $record->update($data);

                            if (isset($data['templates'])) {
                                $record->templates()->delete();
                                foreach ($data['templates'] as $template) {
                                    $record->templates()->create($template);
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
