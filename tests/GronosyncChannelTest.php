<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync\Tests;

use Illuminate\Notifications\AnonymousNotifiable;
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

    /** contactExternalId() on the message already names the recipient, so routing must not add contact_id next to it */
    public function test_contact_external_id_skips_notifiable_routing(): void
    {
        $api = Mockery::mock(GronosyncApi::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(fn (GronosyncMessage $message) => $message->contactExternalId === 'crm-42' && $message->contactId === null))
            ->andReturn(['message_id' => 'm-1']);

        $channel = new GronosyncChannel($api, $this->app['events']);

        $channel->send(new TestNotifiable('contact-1'), new TestNotification('Hello', 'crm-42'));
    }

    public function test_contact_external_id_is_taken_from_notifiable_routing(): void
    {
        $api = Mockery::mock(GronosyncApi::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(fn (GronosyncMessage $message) => $message->contactExternalId === 'crm-42' && $message->contactId === null))
            ->andReturn(['message_id' => 'm-1']);

        $channel = new GronosyncChannel($api, $this->app['events']);

        $channel->send(new TestNotifiable(null, 'crm-42'), new TestNotification('Hello'));
    }

    public function test_contact_id_routing_wins_over_external_id(): void
    {
        $api = Mockery::mock(GronosyncApi::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(fn (GronosyncMessage $message) => $message->contactId === 'contact-1' && $message->contactExternalId === null))
            ->andReturn(['message_id' => 'm-1']);

        $channel = new GronosyncChannel($api, $this->app['events']);

        $channel->send(new TestNotifiable('contact-1', 'crm-42'), new TestNotification('Hello'));
    }

    public function test_on_demand_external_id_route(): void
    {
        $api = Mockery::mock(GronosyncApi::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(fn (GronosyncMessage $message) => $message->contactExternalId === 'crm-42'))
            ->andReturn(['message_id' => 'm-1']);

        $channel = new GronosyncChannel($api, $this->app['events']);

        $channel->send((new AnonymousNotifiable())->route('GronosyncExternalId', 'crm-42'), new TestNotification('Hello'));
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

    public function __construct(private readonly ?string $contactId, private readonly ?string $externalId = null) {}

    public function routeNotificationForGronosync(): ?string
    {
        return $this->contactId;
    }

    public function routeNotificationForGronosyncExternalId(): ?string
    {
        return $this->externalId;
    }
}

class TestNotification extends Notification
{
    public function __construct(private readonly string $text, private readonly ?string $contactExternalId = null) {}

    public function toGronosync(mixed $notifiable): GronosyncMessage
    {
        $message = GronosyncMessage::make()->text($this->text);

        return $this->contactExternalId ? $message->contactExternalId($this->contactExternalId) : $message;
    }
}
