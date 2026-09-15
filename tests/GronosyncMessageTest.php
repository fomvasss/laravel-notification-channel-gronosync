<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync\Tests;

use NotificationChannels\Gronosync\GronosyncMessage;

class GronosyncMessageTest extends TestCase
{
    public function test_basic_text_with_contact_id(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->text('Hello!');

        $array = $message->toArray();

        $this->assertSame('uuid-123', $array['contact_id']);
        $this->assertSame('Hello!', $array['message']['text']);
        $this->assertArrayNotHasKey('to', $array);
        $this->assertArrayNotHasKey('channel_id', $array);
    }

    public function test_to_with_channel_id(): void
    {
        $message = GronosyncMessage::make()
            ->to('380991234567')
            ->channelId('channel-uuid')
            ->text('Welcome!');

        $array = $message->toArray();

        $this->assertSame('380991234567', $array['to']);
        $this->assertSame('channel-uuid', $array['channel_id']);
        $this->assertArrayNotHasKey('contact_id', $array);
    }

    public function test_single_attachment(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->text('See attachment.')
            ->attachment('https://example.com/file.pdf', 'file.pdf', 'document');

        $array = $message->toArray();
        $attachments = $array['message']['attachments'];

        $this->assertSame('document', $attachments['type']);
        $this->assertCount(1, $attachments['items']);
        $this->assertSame('https://example.com/file.pdf', $attachments['items'][0]['url']);
        $this->assertSame('file.pdf', $attachments['items'][0]['filename']);
    }

    public function test_multiple_attachments(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->attachment('https://example.com/a.jpg', type: 'image')
            ->attachment('https://example.com/b.jpg', type: 'image');

        $array = $message->toArray();

        $this->assertSame('image', $array['message']['attachments']['type']);
        $this->assertCount(2, $array['message']['attachments']['items']);
    }

    public function test_web_url_button(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->text('Click below.')
            ->button('Open', 'https://example.com');

        $array = $message->toArray();
        $buttons = $array['message']['buttons'];

        $this->assertCount(1, $buttons);
        $this->assertSame('web_url', $buttons[0]['type']);
        $this->assertSame('Open', $buttons[0]['title']);
        $this->assertSame('https://example.com', $buttons[0]['url']);
    }

    public function test_callback_buttons(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->text('Choose:')
            ->button('Yes', 'confirm', 'callback')
            ->button('No', 'cancel', 'callback');

        $array = $message->toArray();
        $buttons = $array['message']['buttons'];

        $this->assertCount(2, $buttons);
        $this->assertSame('callback', $buttons[0]['type']);
        $this->assertSame('confirm', $buttons[0]['callback']);
        $this->assertSame('cancel', $buttons[1]['callback']);
    }

    public function test_options(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->text('<b>Bold</b>')
            ->parseMode('html')
            ->previewUrl(false);

        $array = $message->toArray();
        $options = $array['message']['options'];

        $this->assertSame('html', $options['parse_mode']);
        $this->assertFalse($options['preview_url']);
    }

    public function test_no_options_key_when_empty(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->text('Plain text.');

        $array = $message->toArray();

        $this->assertArrayNotHasKey('options', $array['message']);
        $this->assertArrayNotHasKey('attachments', $array['message']);
        $this->assertArrayNotHasKey('buttons', $array['message']);
    }

    public function test_reply_to_id_and_forwarded_from_id(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->text('Reply.')
            ->replyToId('msg-1')
            ->forwardedFromId('msg-2');

        $array = $message->toArray();

        $this->assertSame('msg-1', $array['reply_to_id']);
        $this->assertSame('msg-2', $array['forwarded_from_id']);
    }

    public function test_no_reply_or_forward_keys_when_not_set(): void
    {
        $message = GronosyncMessage::make()
            ->contactId('uuid-123')
            ->text('Plain text.');

        $array = $message->toArray();

        $this->assertArrayNotHasKey('reply_to_id', $array);
        $this->assertArrayNotHasKey('forwarded_from_id', $array);
    }
}
