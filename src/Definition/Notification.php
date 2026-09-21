<?php

namespace RoBYCoNTe\FilamentFlow\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationChannel;
use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationPriority;
use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationTiming;
use RoBYCoNTe\FilamentFlow\Definition\Enums\NotificationTrigger;

/**
 * A typed workflow notification: trigger, recipients, channels and the template
 * used on every channel. Attached to a transition, to a state or to the workflow
 * itself; the applier persists notification + recipients + channels + templates.
 */
final class Notification
{
    private NotificationTrigger $trigger = NotificationTrigger::OnTransition;

    private ?string $description = null;

    private bool $active = true;

    private NotificationTiming $timing = NotificationTiming::Immediate;

    private ?int $delayMinutes = null;

    private NotificationPriority $priority = NotificationPriority::Medium;

    /** @var array<string,mixed> */
    private array $metadata = [];

    /** @var list<Recipient> */
    private array $recipients = [];

    /** @var list<array{channel_type:string,channel_config:array<string,mixed>,is_active:bool}> */
    private array $channels = [];

    private ?string $subject = null;

    private ?string $title = null;

    private ?string $body = null;

    private ?string $actionText = null;

    private ?string $actionUrl = null;

    private string $templateEngine = 'plain';

    private string $format = 'html';

    /** @var list<string> */
    private array $variables = [];

    private function __construct(
        private readonly string $name,
    ) {}

    public static function make(string $name, ?string $label = null): self
    {
        return (new self($name))->description($label);
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        $notification = (new self((string) ($data['name'] ?? '')))
            ->trigger(NotificationTrigger::from((string) ($data['trigger_event'] ?? NotificationTrigger::OnTransition->value)))
            ->description($data['description'] ?? null)
            ->active((bool) ($data['is_active'] ?? true))
            ->priority(NotificationPriority::from((string) ($data['priority'] ?? NotificationPriority::Medium->value)))
            ->metadata($data['metadata'] ?? []);

        if (($data['timing'] ?? NotificationTiming::Immediate->value) === NotificationTiming::Delayed->value) {
            $notification->delay((int) ($data['delay_minutes'] ?? 0));
        }

        foreach ($data['recipients'] ?? [] as $recipient) {
            $notification->recipient(Recipient::fromArray($recipient));
        }

        foreach ($data['channels'] ?? [] as $channel) {
            $notification->channel(
                NotificationChannel::from((string) $channel['channel_type']),
                $channel['channel_config'] ?? [],
                (bool) ($channel['is_active'] ?? true),
            );
        }

        $template = $data['template'] ?? [];

        return $notification
            ->subject($template['subject'] ?? null)
            ->title($template['title'] ?? null)
            ->body($template['body'] ?? null)
            ->action($template['action_url'] ?? null, $template['action_text'] ?? null)
            ->templateEngine((string) ($template['template_engine'] ?? 'plain'))
            ->format((string) ($template['format'] ?? 'html'))
            ->variables($template['variables'] ?? []);
    }

    public function trigger(NotificationTrigger $trigger): self
    {
        $this->trigger = $trigger;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function active(bool $active = true): self
    {
        $this->active = $active;

        return $this;
    }

    public function priority(NotificationPriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /** @param array<string,mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function immediate(): self
    {
        $this->timing = NotificationTiming::Immediate;
        $this->delayMinutes = null;

        return $this;
    }

    public function delay(int $minutes): self
    {
        $this->timing = NotificationTiming::Delayed;
        $this->delayMinutes = $minutes;

        return $this;
    }

    /** @param array<string,mixed> $config */
    public function channel(NotificationChannel $channel, array $config = [], bool $active = true): self
    {
        $this->channels[] = [
            'channel_type' => $channel->value,
            'channel_config' => $config,
            'is_active' => $active,
        ];

        return $this;
    }

    /** @param array<string,mixed> $config */
    public function database(array $config = [], bool $active = true): self
    {
        return $this->channel(NotificationChannel::Database, $config, $active);
    }

    /** @param array<string,mixed> $config */
    public function mail(array $config = [], bool $active = true): self
    {
        return $this->channel(NotificationChannel::Mail, $config, $active);
    }

    public function recipient(Recipient $recipient): self
    {
        $this->recipients[] = $recipient;

        return $this;
    }

    /** @param list<Recipient> $recipients */
    public function recipients(array $recipients): self
    {
        $this->recipients = array_values($recipients);

        return $this;
    }

    public function subject(?string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function body(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function action(?string $url, ?string $text = null): self
    {
        $this->actionUrl = $url;
        $this->actionText = $text;

        return $this;
    }

    public function templateEngine(string $engine): self
    {
        $this->templateEngine = $engine;

        return $this;
    }

    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    /** @param list<string> $variables */
    public function variables(array $variables): self
    {
        $this->variables = array_values($variables);

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function triggerEvent(): NotificationTrigger
    {
        return $this->trigger;
    }

    /** @return list<Recipient> */
    public function recipientList(): array
    {
        return $this->recipients;
    }

    /** @return list<array{channel_type:string,channel_config:array<string,mixed>,is_active:bool}> */
    public function channelList(): array
    {
        return $this->channels === []
            ? [['channel_type' => NotificationChannel::Database->value, 'channel_config' => [], 'is_active' => true]]
            : $this->channels;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $recipients = [];
        foreach ($this->recipients as $index => $recipient) {
            $recipients[] = $recipient->toArray() + ['sort_order' => $index];
        }

        return [
            'name' => $this->name,
            'description' => $this->description,
            'trigger_event' => $this->trigger->value,
            'is_active' => $this->active,
            'timing' => $this->timing->value,
            'delay_minutes' => $this->delayMinutes,
            'priority' => $this->priority->value,
            'metadata' => $this->metadata,
            'recipients' => $recipients,
            'channels' => array_values($this->channelList()),
            'template' => [
                'subject' => $this->subject,
                'title' => $this->title ?? 'Workflow Notification',
                'body' => $this->body ?? 'A workflow event has occurred.',
                'action_text' => $this->actionText,
                'action_url' => $this->actionUrl,
                'template_engine' => $this->templateEngine,
                'format' => $this->format,
                'variables' => $this->variables,
            ],
        ];
    }
}
