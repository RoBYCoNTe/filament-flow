<?php

namespace RoBYCoNTe\FilamentFlow\Builders;

/**
 * Fluent builder for defining workflow notifications in code.
 *
 * Usage:
 * ```php
 * WorkflowNotificationBuilder::make()
 *     ->channel('database')
 *     ->recipients(['@owner', 'role:admin', 'user:1'])
 *     ->title('Order Updated')
 *     ->body('Order {{order_number}} has been updated.')
 * ```
 */
class WorkflowNotificationBuilder
{
    protected string $channel = 'database';

    protected array $channelConfig = [];

    protected array $recipients = [];

    protected string $title = '';

    protected string $body = '';

    protected ?string $subject = null;

    protected ?string $actionUrl = null;

    protected ?string $actionText = null;

    protected string $priority = 'medium';

    protected string $templateEngine = 'plain';

    protected string $timing = 'immediate';

    protected int $delayMinutes = 0;

    protected array $metadata = [];

    protected ?string $name = null;

    /**
     * Create a new notification builder instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set the notification name (optional, for logging/debugging).
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the notification channel.
     *
     * @param  string  $channel  One of: 'database', 'mail'
     * @param  array  $config  Channel-specific configuration
     */
    public function channel(string $channel, array $config = []): static
    {
        $this->channel = $channel;
        $this->channelConfig = $config;

        return $this;
    }

    /**
     * Set the recipients for this notification.
     *
     * Supported formats:
     * - '@owner' - Record owner (via user_id or configured owner field)
     * - '@assigned' - Assigned users
     * - 'role:admin' - Users with specific role
     * - 'user:1' or 'user:1,2,3' - Specific user IDs
     * - 'involvement:reviewer' - Users involved as specific type
     * - '@all_involved' - All users involved with the record
     * - Callable: fn($record) => collect([...]) - Custom resolver
     *
     * @return WorkflowNotificationBuilder
     */
    public function recipients(array|callable $recipients): static
    {
        $this->recipients = is_callable($recipients) ? [$recipients] : $recipients;

        return $this;
    }

    /**
     * Set the notification title.
     *
     * Supports template variables: {{field_name}}
     */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the notification body.
     *
     * Supports template variables: {{field_name}}
     */
    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the email subject (for mail channel).
     *
     * Supports template variables: {{field_name}}
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set the action URL for the notification.
     *
     * Supports template variables: {{field_name}}
     */
    public function actionUrl(string $url, ?string $text = null): static
    {
        $this->actionUrl = $url;
        if ($text !== null) {
            $this->actionText = $text;
        }

        return $this;
    }

    /**
     * Set the action button text.
     */
    public function actionText(string $text): static
    {
        $this->actionText = $text;

        return $this;
    }

    /**
     * Set the notification priority.
     *
     * @param  string  $priority  One of: 'low', 'medium', 'high', 'urgent'
     */
    public function priority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Set the template engine.
     *
     * @param  string  $engine  One of: 'plain', 'blade', 'mustache'
     */
    public function templateEngine(string $engine): static
    {
        $this->templateEngine = $engine;

        return $this;
    }

    /**
     * Send the notification immediately (default).
     */
    public function immediate(): static
    {
        $this->timing = 'immediate';
        $this->delayMinutes = 0;

        return $this;
    }

    /**
     * Delay the notification by specified minutes.
     */
    public function delay(int $minutes): static
    {
        $this->timing = 'delayed';
        $this->delayMinutes = $minutes;

        return $this;
    }

    /**
     * Add metadata to the notification.
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Get the channel type.
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * Get the channel configuration.
     */
    public function getChannelConfig(): array
    {
        return $this->channelConfig;
    }

    /**
     * Get the recipients' configuration.
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * Get the title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the body.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get the subject.
     */
    public function getSubject(): ?string
    {
        return $this->subject;
    }

    /**
     * Get the action URL.
     */
    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    /**
     * Get the action text.
     */
    public function getActionText(): ?string
    {
        return $this->actionText;
    }

    /**
     * Get the priority.
     */
    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * Get the template engine.
     */
    public function getTemplateEngine(): string
    {
        return $this->templateEngine;
    }

    /**
     * Get the timing.
     */
    public function getTiming(): string
    {
        return $this->timing;
    }

    /**
     * Get the delay in minutes.
     */
    public function getDelayMinutes(): int
    {
        return $this->delayMinutes;
    }

    /**
     * Get metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the notification name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Convert the builder to notification data array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'channel' => $this->channel,
            'channel_config' => $this->channelConfig,
            'recipients' => $this->recipients,
            'template' => [
                'subject' => $this->subject,
                'title' => $this->title ?: 'Workflow Notification',
                'body' => $this->body ?: 'A workflow event has occurred.',
                'action_url' => $this->actionUrl,
                'action_text' => $this->actionText,
                'template_engine' => $this->templateEngine,
            ],
            'priority' => $this->priority,
            'timing' => $this->timing,
            'delay_minutes' => $this->delayMinutes,
            'metadata' => $this->metadata,
        ];
    }
}
