<?php

declare(strict_types=1);

namespace NotificationChannels\ItsChats\Tests;

use NotificationChannels\ItsChats\ItsChatsMessage;

class ItsChatsMessageTest extends TestCase
{
    public function test_basic_text_with_contact_id(): void
    {
        $message = ItsChatsMessage::make()
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
        $message = ItsChatsMessage::make()
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
        $message = ItsChatsMessage::make()
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
        $message = ItsChatsMessage::make()
            ->contactId('uuid-123')
            ->attachment('https://example.com/a.jpg', type: 'image')
            ->attachment('https://example.com/b.jpg', type: 'image');

        $array = $message->toArray();

        $this->assertSame('image', $array['message']['attachments']['type']);
        $this->assertCount(2, $array['message']['attachments']['items']);
    }

    public function test_web_url_button(): void
    {
        $message = ItsChatsMessage::make()
            ->contactId('uuid-123')
            ->text('Click below.')
            ->button('Open', 'https://example.com');

        $array = $message->toArray();
        $buttons = $array['message']['buttons'];

        $this->assertSame('web_url', $buttons['type']);
        $this->assertCount(1, $buttons['items']);
        $this->assertSame('Open', $buttons['items'][0]['title']);
        $this->assertSame('https://example.com', $buttons['items'][0]['url']);
    }

    public function test_callback_buttons(): void
    {
        $message = ItsChatsMessage::make()
            ->contactId('uuid-123')
            ->text('Choose:')
            ->button('Yes', 'confirm', 'callback')
            ->button('No', 'cancel', 'callback');

        $array = $message->toArray();
        $buttons = $array['message']['buttons'];

        $this->assertSame('callback', $buttons['type']);
        $this->assertCount(2, $buttons['items']);
        $this->assertSame('confirm', $buttons['items'][0]['callback']);
        $this->assertSame('cancel', $buttons['items'][1]['callback']);
    }

    public function test_options(): void
    {
        $message = ItsChatsMessage::make()
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
        $message = ItsChatsMessage::make()
            ->contactId('uuid-123')
            ->text('Plain text.');

        $array = $message->toArray();

        $this->assertArrayNotHasKey('options', $array['message']);
        $this->assertArrayNotHasKey('attachments', $array['message']);
        $this->assertArrayNotHasKey('buttons', $array['message']);
    }
}
