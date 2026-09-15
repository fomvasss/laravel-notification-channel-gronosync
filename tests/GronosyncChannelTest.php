<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync\Tests;

use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Mockery;
use NotificationChannels\Gronosync\Exceptions\CouldNotSendNotification;
use NotificationChannels\Gronosync\GronosyncApi;
use NotificationChannels\Gronosync\GronosyncChannel;
use NotificationChannels\Gronosync\GronosyncMessage;

class GronosyncChannelTest extends TestCase
{
    public function test_contact_id_is_taken_from_notifiable_routing(): void
    {
        $api = Mockery::mock(GronosyncApi::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(fn (GronosyncMessage $message) => $message->contactId === 'contact-1' && $message->text === 'Hello'))
            ->andReturn(['message_id' => 'm-1']);

        $channel = new GronosyncChannel($api, $this->app['events']);

        $result = $channel->send(new TestNotifiable('contact-1'), new TestNotification('Hello'));

        $this->assertSame(['message_id' => 'm-1'], $result);
    }

    public function test_api_error_dispatches_notification_failed_with_exception(): void
    {
        Event::fake([NotificationFailed::class]);

        $api = Mockery::mock(GronosyncApi::class);
        $api->shouldReceive('sendMessage')->andThrow(
            CouldNotSendNotification::serviceRespondedWithAnError('Chat is blocked', 422, ['message' => 'Chat is blocked', 'code' => 'chat_blocked'])
        );

        $channel = new GronosyncChannel($api, $this->app['events']);

        $this->assertNull($channel->send(new TestNotifiable('contact-1'), new TestNotification('Hello')));

        Event::assertDispatched(NotificationFailed::class, fn (NotificationFailed $event) => $event->channel === 'Gronosync'
            && $event->data['exception'] instanceof CouldNotSendNotification
            && $event->data['exception']->getErrorCode() === 'chat_blocked');
    }

    public function test_missing_receiver_dispatches_notification_failed(): void
    {
        Event::fake([NotificationFailed::class]);

        $api = Mockery::mock(GronosyncApi::class);
        $api->shouldNotReceive('sendMessage');

        $channel = new GronosyncChannel($api, $this->app['events']);

        $this->assertNull($channel->send(new TestNotifiable(null), new TestNotification('Hello')));

        Event::assertDispatched(NotificationFailed::class);
    }
}

class TestNotifiable
{
    use Notifiable;

    public function __construct(private readonly ?string $contactId) {}

    public function routeNotificationForGronosync(): ?string
    {
        return $this->contactId;
    }
}

class TestNotification extends Notification
{
    public function __construct(private readonly string $text) {}

    public function toGronosync(mixed $notifiable): GronosyncMessage
    {
        return GronosyncMessage::make()->text($this->text);
    }
}
