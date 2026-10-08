<p align="center">
    <a href="https://gronosync.com"><img src="art/logo.png" alt="GronoSync" width="120"></a>
</p>

# GronoSync Notification Channel for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fomvasss/laravel-notification-channel-gronosync.svg)](https://packagist.org/packages/fomvasss/laravel-notification-channel-gronosync)
[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notification-channel-gronosync.svg)](LICENSE.md)

[Українська](README.md) · **English**

Send Laravel notifications via [GronoSync](https://gronosync.com) — a multi-channel messaging platform supporting Telegram, WhatsApp, Instagram, Facebook, Viber, SMS, email and the built-in chat widget.

[Website](https://gronosync.com) · [Client dashboard](https://app.gronosync.com) · API: `https://api.gronosync.com`

## Documentation

This README covers the Laravel package. Everything about the GronoSync service itself is on **[docs.gronosync.com](https://docs.gronosync.com)**:

- [Extern API](https://docs.gronosync.com/api/overview/) — contacts, messages, form submissions, channels, rate limits
- [Errors and codes](https://docs.gronosync.com/api/errors/) — every status and `code`, and what to do about it
- [Webhooks](https://docs.gronosync.com/webhooks/overview/) — events, payloads with full JSON examples, verification, retries
- [Website widgets](https://docs.gronosync.com/widgets/overview/) — chat, form and the manager chat for your CRM
- [API keys](https://docs.gronosync.com/authentication/) · [API changelog](https://docs.gronosync.com/changelog/)

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Sending notifications](#sending-notifications)
- [Calling the API directly](#calling-the-api-directly)
- [Errors](#errors)
- [Receiving webhooks](#receiving-webhooks)
- [Testing](#testing)

## Installation

```bash
composer require fomvasss/laravel-notification-channel-gronosync
```

## Configuration

Add to your `.env`:

```env
GRONOSYNC_URL=https://api.gronosync.com
GRONOSYNC_TOKEN=your_organization_token
```

Add to `config/services.php`:

```php
'gronosync' => [
    'url'   => env('GRONOSYNC_URL'),
    'token' => env('GRONOSYNC_TOKEN'),
],
```

The `token` is an organization API key: [GronoSync dashboard](https://app.gronosync.com) → **Settings → Extensions → API**. It is shown only once, at creation. See [API keys](https://docs.gronosync.com/authentication/).

## Sending notifications

```php
use NotificationChannels\Gronosync\GronosyncChannel;
use NotificationChannels\Gronosync\GronosyncMessage;

class OrderConfirmed extends \Illuminate\Notifications\Notification
{
    public function __construct(protected Order $order) {}

    public function via(mixed $notifiable): array
    {
        return [GronosyncChannel::class];
    }

    public function toGronosync(mixed $notifiable): GronosyncMessage
    {
        return GronosyncMessage::make()
            ->text("Your order #{$this->order->id} has been confirmed!")
            ->button('View order', "https://shop.com/orders/{$this->order->id}");
    }
}
```

### Routing

Return the contact from your notifiable model — the GronoSync UUID, or your own ID if you sync contacts via `upsertContact()` with an `external_id`:

```php
public function routeNotificationForGronosync(): ?string
{
    return $this->gronosync_contact_id; // GronoSync contact UUID
}

public function routeNotificationForGronosyncExternalId(): ?string
{
    return (string) $this->id; // the same external_id you pass to upsertContact()
}
```

If both are defined, `routeNotificationForGronosync()` goes first; the external ID is used only when it returns an empty value. On-demand: `Notification::route('GronosyncExternalId', 'crm-42')->notify(...)`.

Or set the contact on the message — exactly one of `contactId()`, `contactExternalId()`, `to()`:

```php
GronosyncMessage::make()->contactExternalId((string) $customer->id)->text('Hello!');

// a new contact by its identifier in the channel (phone, Telegram ID, email…)
GronosyncMessage::make()->to('+380991234567')->channelId($smsChannelId)->text('Welcome!');
```

What `to` means for each channel type, and when `channelId()` is required — [Messages](https://docs.gronosync.com/api/messages/).

### GronosyncMessage methods

| Method | Description |
|---|---|
| `contactId(string $id)` | GronoSync contact UUID |
| `contactExternalId(string $id)` | Your contact ID — the `external_id` passed to `upsertContact()` |
| `to(string $identifier)` | Contact identifier in the channel — for contacts not yet known |
| `channelId(string $id)` | GronoSync channel UUID (required with `to()`) |
| `text(string $text)` | Message text |
| `attachment(string $url, ?string $filename, string $type)` | Add one file. Types: `image`, `audio`, `video`, `document` |
| `attachments(array $items, string $type)` | Add several files at once |
| `button(string $title, string $urlOrCallback, string $type)` | Add a button. Types: `web_url`, `callback` |
| `buttons(array $items)` | Add several buttons at once |
| `parseMode(string $mode)` | `html` or `markdown` (Telegram) |
| `previewUrl(bool $val)` | URL preview (WhatsApp) |
| `replyToId(string $id)` | Message being replied to |
| `forwardedFromId(string $id)` | Forwarded message |
| `metadata(array $metadata)` | Your data (up to 4 KB) — returned in `data.metadata` of the `chat.message.sent` webhook |

## Calling the API directly

`GronosyncApi` is a thin client for the [Extern API](https://docs.gronosync.com/api/overview/); it returns the decoded response.

| Method | Endpoint |
|---|---|
| `sendMessage(GronosyncMessage $message)` | [`POST /message`](https://docs.gronosync.com/api/messages/) |
| `upsertContact(array $data)` | [`POST /contacts`](https://docs.gronosync.com/api/contacts/) |
| `getContact(string $id)` / `getContactByExternalId(string $externalId)` | [`GET /contacts/{id}`](https://docs.gronosync.com/api/contacts/) — returns `data` |
| `getChannels()` | [`GET /channels`](https://docs.gronosync.com/api/channels/) — returns `data` |
| `submitForm(array $data, ?string $idempotencyKey = null)` | [`POST /form`](https://docs.gronosync.com/api/forms/), the key goes as `Idempotency-Key` |
| `sendIncoming(array $data, ?string $idempotencyKey = null)` | [`POST /incoming`](https://docs.gronosync.com/api/incoming/) — a message from the client out of your own system (tickets, chat), the key goes as `Idempotency-Key`. `message.text` is plain text, no HTML: line break — `\n`, links — as full URLs |

```php
use NotificationChannels\Gronosync\GronosyncApi;

$api = app(GronosyncApi::class);

$api->upsertContact(['external_id' => (string) $user->id, 'name' => $user->name, 'phone' => $user->phone]);

$contact = $api->getContactByExternalId((string) $user->id);
$channel = collect($contact['channels'])->firstWhere('can_send', true);

$api->submitForm([
    'channel_id' => config('services.gronosync.form_channel_id'),
    'fields' => ['name' => $lead->name, 'email' => $lead->email, 'message' => $lead->message],
], idempotencyKey: "lead-{$lead->id}");
```

Unknown keys in `upsertContact()` / `submitForm()` / `sendIncoming()` are dropped before sending.

## Errors

Failures are thrown as `CouldNotSendNotification`:

- **Direct `GronosyncApi` calls** — the exception is thrown.
- **Notification channel** — it is **not** thrown: Laravel's `NotificationFailed` event is dispatched with the exception in `$event->data['exception']`.

| Method | Returns |
|---|---|
| `getStatusCode()` | HTTP status, `null` on a network error / timeout |
| `getErrorCode()` | Machine-readable `code` (e.g. `chat_blocked`), or `null` |
| `getResponse()` | Decoded JSON body of the error response, or `null` |

```php
use Illuminate\Notifications\Events\NotificationFailed;
use NotificationChannels\Gronosync\Exceptions\CouldNotSendNotification;

Event::listen(function (NotificationFailed $event) {
    $exception = $event->data['exception'] ?? null;

    if ($event->channel === 'Gronosync'
        && $exception instanceof CouldNotSendNotification
        && $exception->getErrorCode() === 'chat_blocked') {
        $event->notifiable->update(['gronosync_blocked' => true]);
    }
});
```

Branch on the status and `code`, not on the text. All codes and what to do about them — [Errors and codes](https://docs.gronosync.com/api/errors/).

## Receiving webhooks

GronoSync can notify your app about new contacts, incoming and outgoing messages and chats that need a manager. Setup, events, payloads and a Laravel controller example — [Webhooks](https://docs.gronosync.com/webhooks/overview/) and [Laravel](https://docs.gronosync.com/packages/laravel/).

## Testing

```bash
composer test
composer test:coverage
```

## Security

If you discover any security-related issues, please email fomin.vasil@gmail.com instead of using the issue tracker.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

- [Fomin Vasil](https://github.com/fomvasss)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
