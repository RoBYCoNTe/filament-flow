<?php

namespace RoBYCoNTe\FilamentFlow\Notifications;

use Exception;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use RoBYCoNTe\FilamentFlow\Support\NotificationTemplateRegistry;
use RoBYCoNTe\FilamentFlow\Support\RecordLabel;
use Throwable;

/**
 * Laravel Notification class for workflow notifications.
 *
 * Supports database and mail channels with plain, blade, and mustache templates.
 */
class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $data;

    protected Model $record;

    protected array $template;

    protected array $context;

    public function __construct(array $data, Model $record)
    {
        $this->data = $data;
        $this->record = $record;
        $this->template = $data['template'] ?? [];
        $this->context = $data['context'] ?? [];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function via(object $notifiable): array
    {
        $channel = $this->data['channel'] ?? 'database';

        return match ($channel) {
            'mail' => ['mail'],
            default => ['database'],
        };
    }

    /**
     * Get the mail representation of the notification.
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->composeTitle($this->renderTemplate($this->template['subject'] ?? 'Workflow Notification')))
            ->greeting($this->renderTemplate($this->template['title'] ?? 'Hello!'))
            ->line($this->renderTemplate($this->template['body'] ?? 'A workflow event has occurred.'));

        $fromAddress = config('filament-flow.notifications.channels.mail.from_address');

        if (filled($fromAddress)) {
            $message->from((string) $fromAddress, (string) config('filament-flow.notifications.channels.mail.from_name', ''));
        }

        // Add action button if configured
        if (! empty($this->template['action_url'])) {
            $message->action(
                $this->renderTemplate($this->template['action_text'] ?? 'View Details'),
                $this->renderTemplate($this->template['action_url'])
            );
        }

        return $message;
    }

    /**
     * Get the Filament database notification representation.
     *
     * Uses Filament's format (includes "format":"filament") so the notification
     * appears in Filament's database notification bell. Extra workflow metadata
     * is merged alongside the standard Filament fields.
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function toDatabase(object $notifiable): array
    {
        $title = $this->composeTitle($this->renderTemplate($this->template['title'] ?? 'Workflow Notification'));
        $body = $this->renderTemplate($this->template['body'] ?? 'A workflow event has occurred.');
        $actionUrl = $this->renderTemplate($this->template['action_url'] ?? '');
        $actionText = $this->renderTemplate($this->template['action_text'] ?? '');

        $filamentNotification = \Filament\Notifications\Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-'.$this->getIcon()->value)
            ->color($this->getColor());

        if (filled($actionUrl)) {
            $filamentNotification->actions([
                Action::make('view')
                    ->label($actionText ?: __('View'))
                    ->url($actionUrl)
                    ->button()
                    ->markAsRead(),
            ]);
        }

        return array_merge($filamentNotification->getDatabaseMessage(), [
            'record_type' => $this->data['record_type'] ?? get_class($this->record),
            'record_id' => $this->data['record_id'] ?? $this->record->getKey(),
            'context' => collect($this->context)->reject(fn ($v) => $v instanceof Model)->toArray(),
            'priority' => $this->data['priority'] ?? 'medium',
        ]);
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Render a template string with variable substitution.
     */
    protected function renderTemplate(?string $template): string
    {
        if (! $template) {
            return '';
        }

        $engine = $this->template['template_engine'] ?? 'plain';

        // Build variables for substitution
        $variables = $this->buildVariables();

        $rendered = match ($engine) {
            'blade' => $this->renderBlade($template, $variables),
            'mustache' => $this->renderMustache($template, $variables),
            default => $this->renderPlain($template, $variables),
        };

        return $this->renderHostExpressions($rendered);
    }

    /**
     * The placeholders the engine does not know — `field("...")`, `currency(...)`, the URL of the
     * record — are handed to the host, which answers with the same record the notification is
     * about. The engine has already filled its own variables, so what is left is the vocabulary
     * of the host; a host that registered no provider leaves the template as it stands.
     */
    protected function renderHostExpressions(string $template): string
    {
        try {
            $registry = app(NotificationTemplateRegistry::class);

            if (! $registry->has()) {
                return $template;
            }

            return $registry->get()->interpolate($template, $this->record);
        } catch (Throwable $e) {
            report($e);

            return $template;
        }
    }

    /**
     * The title of a notification: the event and the file it is about, composed once for every
     * notification by the pattern of the host (`notifications.title_pattern`, tokens `{title}`
     * and `{record}`). A record with no label leaves the event standing alone, never a dangling
     * separator.
     */
    protected function composeTitle(string $eventTitle): string
    {
        $pattern = (string) config('filament-flow.notifications.title_pattern', '{title}');

        if (! str_contains($pattern, '{record}')) {
            return $eventTitle;
        }

        $label = RecordLabel::of($this->record);

        if ($label === null || $label === '') {
            return $eventTitle;
        }

        return str_replace(['{title}', '{record}'], [$eventTitle, $label], $pattern);
    }

    /**
     * Build variables for template substitution.
     */
    protected function buildVariables(): array
    {
        // Use getAttributes() to avoid HasFlexibleStates/Spatie cast issues
        // with database-only state strings that don't resolve to a PHP class.
        try {
            $recordArray = $this->record->toArray();
        } catch (Throwable) {
            $recordArray = $this->record->getAttributes();
        }

        $variables = [
            // Record variables
            'record' => $recordArray,
            'record_id' => $this->record->getKey(),
            'record_type' => class_basename($this->record),
            // The code the record reads by, beside the event in the title and usable in a
            // template (`{{ record_title }}`): the file a notification is about.
            'record_title' => RecordLabel::of($this->record) ?? '',

            // Context variables
            'trigger' => $this->context['trigger'] ?? '',
            'from_state' => $this->context['from_state'] ?? '',
            'to_state' => $this->context['to_state'] ?? '',

            // State labels — prefer values already resolved in context (e.g. by
            // NotificationService), fall back to getStateLabel() for code-first notifications
            // that bypass DB enrichment.
            'from_state_label' => $this->context['from_state_label'] ?? $this->getStateLabel($this->context['from_state'] ?? ''),
            'to_state_label' => $this->context['to_state_label'] ?? $this->getStateLabel($this->context['to_state'] ?? ''),
            'transition_label' => $this->context['transition_label'] ?? '',

            // App info
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
        ];

        // Merge custom variables from template config
        if (! empty($this->template['variables'])) {
            foreach ($this->template['variables'] as $key => $value) {
                if (is_string($value) && Str::startsWith($value, 'record.')) {
                    $field = Str::after($value, 'record.');
                    $variables[$key] = data_get($this->record, $field);
                } else {
                    $variables[$key] = $value;
                }
            }
        }

        // Add record fields directly
        foreach ($recordArray as $key => $value) {
            if (! isset($variables[$key]) && ! is_array($value)) {
                $variables[$key] = $value;
            }
        }

        return $variables;
    }

    /**
     * Render with plain text substitution ({{variable}}).
     */
    protected function renderPlain(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $template = str_replace('{{'.$key.'}}', (string) $value, $template);
                $template = str_replace('{{ '.$key.' }}', (string) $value, $template);
            }
        }

        return $template;
    }

    /**
     * Render with Blade.
     */
    protected function renderBlade(string $template, array $variables): string
    {
        try {
            return Blade::render($template, $variables);
        } catch (Exception $e) {
            report($e);

            return $this->renderPlain($template, $variables);
        }
    }

    /**
     * Render with Mustache-style syntax.
     */
    protected function renderMustache(string $template, array $variables): string
    {
        // Simple Mustache implementation ({{variable}} and {{{unescaped}}})
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                // Unescaped (triple braces)
                $template = str_replace('{{{'.$key.'}}}', (string) $value, $template);
                // Escaped (double braces)
                $template = str_replace('{{'.$key.'}}', e((string) $value), $template);
            }
        }

        return $template;
    }

    /**
     * Get a human-readable state label.
     */
    protected function getStateLabel(string $state): string
    {
        if (empty($state)) {
            return '';
        }

        // If it's a class name, try to get label from the class
        if (class_exists($state)) {
            try {
                $stateInstance = new $state($this->record);
                if (method_exists($stateInstance, 'getLabel')) {
                    return $stateInstance->getLabel();
                }
            } catch (Exception) {
                // Fall through to default
            }
        }

        // Return humanized version of the state name
        return Str::headline(class_basename($state));
    }

    /**
     * Get the notification icon based on context.
     */
    protected function getIcon(): Heroicon
    {
        $trigger = $this->context['trigger'] ?? '';

        return match ($trigger) {
            'transition' => Heroicon::OutlinedArrowPath,
            'state_enter' => Heroicon::OutlinedArrowRightCircle,
            'state_exit' => Heroicon::OutlinedArrowLeftCircle,
            'assignment' => Heroicon::OutlinedUserPlus,
            'field_change' => Heroicon::OutlinedPencilSquare,
            default => Heroicon::OutlinedBell,
        };
    }

    /**
     * Get the notification color based on priority.
     */
    protected function getColor(): string
    {
        $priority = $this->data['priority'] ?? 'medium';

        return match ($priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'medium' => 'primary',
            'low' => 'gray',
            default => 'primary',
        };
    }
}
