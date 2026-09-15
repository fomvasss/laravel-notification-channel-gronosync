<?php

declare(strict_types=1);

namespace NotificationChannels\Gronosync\Exceptions;

use NotificationChannels\Gronosync\GronosyncMessage;

class CouldNotSendNotification extends \Exception
{
    protected ?int $statusCode = null;

    protected ?array $response = null;

    public static function invalidMessageObject(mixed $message): static
    {
        $className = is_object($message) ? get_class($message) : gettype($message);

        return new static(
            "Notification was not sent. Message object class `{$className}` is invalid. It should be an instance of `" . GronosyncMessage::class . '`.'
        );
    }

    public static function invalidReceiver(): static
    {
        return new static(
            'The notifiable did not have a receiving contact ID. Add a `routeNotificationForGronosync` method or set `contactId()` on the message.'
        );
    }

    public static function serviceRespondedWithAnError(string $message, ?int $statusCode = null, ?array $response = null): static
    {
        $exception = new static("GronoSync API responded with an error: `{$message}`.");
        $exception->statusCode = $statusCode;
        $exception->response = $response;

        return $exception;
    }

    /**
     * HTTP status of the API response, or null when no response was received (network error, timeout).
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Decoded JSON body of the API error response, or null.
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    /**
     * Machine-readable error code from the API response (e.g. `chat_blocked`), or null.
     */
    public function getErrorCode(): ?string
    {
        return $this->response['code'] ?? null;
    }
}
