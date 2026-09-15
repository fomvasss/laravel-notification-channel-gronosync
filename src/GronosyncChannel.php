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

            if ($message->contactId === null && $message->to === null) {
                $contactId = $notifiable->routeNotificationFor('Gronosync', $notification);

                if (empty($contactId)) {
                    throw CouldNotSendNotification::invalidReceiver();
                }

                $message->contactId($contactId);
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
}
