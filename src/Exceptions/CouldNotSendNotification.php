<?php

declare(strict_types=1);

namespace NotificationChannels\ItsChats\Exceptions;

use NotificationChannels\ItsChats\ItsChatsMessage;

class CouldNotSendNotification extends \Exception
{
    public static function invalidMessageObject(mixed $message): static
    {
        $className = is_object($message) ? get_class($message) : gettype($message);

        return new static(
            "Notification was not sent. Message object class `{$className}` is invalid. It should be an instance of `" . ItsChatsMessage::class . '`.'
        );
    }

    public static function invalidReceiver(): static
    {
        return new static(
            'The notifiable did not have a receiving contact ID. Add a `routeNotificationForItsChats` method or set `contactId()` on the message.'
        );
    }

    public static function serviceRespondedWithAnError(string $message): static
    {
        return new static("ItsChats API responded with an error: `{$message}`.");
    }
}
