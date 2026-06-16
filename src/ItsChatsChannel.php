<?php

declare(strict_types=1);

namespace NotificationChannels\ItsChats;

use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use NotificationChannels\ItsChats\Exceptions\CouldNotSendNotification;

class ItsChatsChannel
{
    public function __construct(
        protected readonly ItsChatsApi $api,
        protected readonly Dispatcher $events,
    ) {}

    public function send(mixed $notifiable, Notification $notification): ?array
    {
        try {
            $message = $notification->toItsChats($notifiable);

            if (is_string($message)) {
                $message = ItsChatsMessage::make()->text($message);
            }

            if (!$message instanceof ItsChatsMessage) {
                throw CouldNotSendNotification::invalidMessageObject($message);
            }

            if ($message->contactId === null && $message->to === null) {
                $contactId = $notifiable->routeNotificationFor('ItsChats', $notification);

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
                'ItsChats',
                ['message' => $exception->getMessage(), 'exception' => $exception],
            ));
        }

        return null;
    }
}
