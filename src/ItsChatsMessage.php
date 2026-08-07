<?php

declare(strict_types=1);

namespace NotificationChannels\ItsChats;

class ItsChatsMessage
{
    public ?string $contactId = null;
    public ?string $to = null;
    public ?string $channelId = null;
    public ?string $text = null;

    public ?string $attachmentsType = null;
    public array $attachmentItems = [];

    public array $buttonItems = [];

    public ?string $parseMode = null;
    public ?bool $previewUrl = null;

    public ?string $replyToId = null;
    public ?string $forwardedFromId = null;

    public static function make(): static
    {
        return new static();
    }

    public function contactId(string $id): static
    {
        $this->contactId = $id;

        return $this;
    }

    public function to(string $identifier): static
    {
        $this->to = $identifier;

        return $this;
    }

    public function channelId(string $id): static
    {
        $this->channelId = $id;

        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function attachment(string $url, ?string $filename = null, string $type = 'document'): static
    {
        $this->attachmentsType = $type;

        $item = ['url' => $url];
        if ($filename !== null) {
            $item['filename'] = $filename;
        }

        $this->attachmentItems[] = $item;

        return $this;
    }

    public function attachments(array $items, string $type = 'document'): static
    {
        $this->attachmentsType = $type;
        $this->attachmentItems = array_merge($this->attachmentItems, $items);

        return $this;
    }

    public function button(string $title, string $urlOrCallback, string $type = 'web_url'): static
    {
        $key = $type === 'web_url' ? 'url' : 'callback';
        $this->buttonItems[] = ['type' => $type, 'title' => $title, $key => $urlOrCallback];

        return $this;
    }

    public function buttons(array $items): static
    {
        $this->buttonItems = array_merge($this->buttonItems, $items);

        return $this;
    }

    public function parseMode(string $mode): static
    {
        $this->parseMode = $mode;

        return $this;
    }

    public function previewUrl(bool $val = true): static
    {
        $this->previewUrl = $val;

        return $this;
    }

    public function replyToId(string $id): static
    {
        $this->replyToId = $id;

        return $this;
    }

    public function forwardedFromId(string $id): static
    {
        $this->forwardedFromId = $id;

        return $this;
    }

    public function toArray(): array
    {
        $body = [];

        if ($this->contactId !== null) {
            $body['contact_id'] = $this->contactId;
        }

        if ($this->to !== null) {
            $body['to'] = $this->to;
        }

        if ($this->channelId !== null) {
            $body['channel_id'] = $this->channelId;
        }

        $message = [];

        if ($this->text !== null) {
            $message['text'] = $this->text;
        }

        if (!empty($this->attachmentItems)) {
            $message['attachments'] = [
                'type' => $this->attachmentsType ?? 'document',
                'items' => $this->attachmentItems,
            ];
        }

        if (!empty($this->buttonItems)) {
            $message['buttons'] = $this->buttonItems;
        }

        $options = [];
        if ($this->parseMode !== null) {
            $options['parse_mode'] = $this->parseMode;
        }
        if ($this->previewUrl !== null) {
            $options['preview_url'] = $this->previewUrl;
        }
        if (!empty($options)) {
            $message['options'] = $options;
        }

        $body['message'] = $message;

        if ($this->replyToId !== null) {
            $body['reply_to_id'] = $this->replyToId;
        }

        if ($this->forwardedFromId !== null) {
            $body['forwarded_from_id'] = $this->forwardedFromId;
        }

        return $body;
    }
}
