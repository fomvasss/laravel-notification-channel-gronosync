<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use NotificationChannels\Gronosync\Exceptions\CouldNotSendNotification;

class GronosyncChannel
{
    public function __construct(
        protected readonly GronosyncApi $api,
        protected readonly Dispatcher $events,
    ) {}

    public function send(mixed $notifiable, Notification $notification): ?array
    {
        try {
            $message = $notification->toGronosync($notifiable);

            if (is_string($message)) {
                $message = GronosyncMessage::make()->text($message);
            }

            if (!$message instanceof GronosyncMessage) {
                throw CouldNotSendNotification::invalidMessageObject($message);
            }

            if ($message->contactId === null && $message->contactExternalId === null && $message->to === null) {
                $this->routeReceiver($notifiable, $notification, $message);
            }

            return $this->api->sendMessage($message);
        } catch (\Throwable $exception) {
            $this->events->dispatch(new NotificationFailed(
                $notifiable,
                $notification,
                'Gronosync',
                ['message' => $exception->getMessage(), 'exception' => $exception],
            ));
        }

        return null;
    }

    // The GronoSync UUID wins: it is exact, while the external ID is only looked up
    protected function routeReceiver(mixed $notifiable, Notification $notification, GronosyncMessage $message): void
    {
        if ($contactId = $notifiable->routeNotificationFor('Gronosync', $notification)) {
            $message->contactId($contactId);

            return;
        }

        if ($externalId = $notifiable->routeNotificationFor('GronosyncExternalId', $notification)) {
            $message->contactExternalId((string) $externalId);

            return;
        }

        throw CouldNotSendNotification::invalidReceiver();
    }
}
