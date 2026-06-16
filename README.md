# ItsChats Notification Channel for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fomvasss/laravel-notification-channel-itschats.svg)](https://packagist.org/packages/fomvasss/laravel-notification-channel-itschats)
[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notification-channel-itschats.svg)](LICENSE.md)

Send Laravel notifications via [ItsChats](https://itschats.com) — a multi-channel messaging platform supporting Telegram, WhatsApp, Instagram, Facebook and the built-in chat widget.

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Testing](#testing)
- [Security](#security)
- [Contributing](#contributing)
- [Credits](#credits)
- [License](#license)

## Installation

```bash
composer require fomvasss/laravel-notification-channel-itschats
```

## Configuration

Add to your `.env`:

```env
ITSCHATS_URL=https://your-itschats-domain.com
ITSCHATS_TOKEN=your_widget_manager_token
```

Add to `config/services.php`:

```php
'itschats' => [
    'url'   => env('ITSCHATS_URL'),
    'token' => env('ITSCHATS_TOKEN'),
],
```

The `token` is the **Widget Manager token** (`wm` type) from your ItsChats organization settings.

## Usage

### Notification class

```php
use NotificationChannels\ItsChats\ItsChatsChannel;
use NotificationChannels\ItsChats\ItsChatsMessage;

class OrderConfirmed extends \Illuminate\Notifications\Notification
{
    public function __construct(protected Order $order) {}

    public function via(mixed $notifiable): array
    {
        return [ItsChatsChannel::class];
    }

    public function toItsChats(mixed $notifiable): ItsChatsMessage
    {
        return ItsChatsMessage::make()
            ->text("Your order #{$this->order->id} has been confirmed!")
            ->button('View order', "https://shop.com/orders/{$this->order->id}");
    }
}
```

### Routing

Add `routeNotificationForItsChats()` to your notifiable model:

```php
class Customer extends Model
{
    use Notifiable;

    public function routeNotificationForItsChats(): ?string
    {
        return $this->itschats_contact_id; // UUID of the contact in ItsChats
    }
}
```

Alternatively, set the contact directly on the message:

```php
ItsChatsMessage::make()
    ->contactId($customer->itschats_contact_id)
    ->text('Hello!');
```

### ItsChatsMessage methods

| Method | Description |
|---|---|
| `contactId(string $id)` | ItsChats contact UUID |
| `to(string $identifier)` | Messenger identifier (telegram_id, phone, email, etc.) |
| `channelId(string $id)` | ItsChats channel UUID (required for new contacts) |
| `text(string $text)` | Message text |
| `attachment(string $url, ?string $filename, string $type)` | Add a single attachment. Types: `image`, `audio`, `video`, `document` |
| `attachments(array $items, string $type)` | Add multiple attachments at once |
| `button(string $title, string $urlOrCallback, string $type)` | Add a button. Types: `web_url`, `callback` |
| `buttons(array $items, string $type)` | Add multiple buttons at once |
| `parseMode(string $mode)` | Text formatting: `html` or `markdown` (Telegram) |
| `previewUrl(bool $val)` | Show URL preview: `true` or `false` (WhatsApp) |

### Examples

**Message with image and button:**

```php
ItsChatsMessage::make()
    ->contactId($notifiable->itschats_contact_id)
    ->text('Your invoice is ready.')
    ->attachment('https://shop.com/invoice/123.pdf', 'invoice.pdf')
    ->button('Download', 'https://shop.com/invoice/123.pdf');
```

**Telegram with HTML formatting:**

```php
ItsChatsMessage::make()
    ->contactId($notifiable->itschats_contact_id)
    ->text('<b>Order confirmed</b> — thank you!')
    ->parseMode('html');
```

**New contact via messenger identifier:**

```php
ItsChatsMessage::make()
    ->to('380991234567')          // phone / telegram_id / etc.
    ->channelId($channelUuid)
    ->text('Welcome!');
```

**Callback buttons:**

```php
ItsChatsMessage::make()
    ->contactId($notifiable->itschats_contact_id)
    ->text('Confirm your order?')
    ->button('Yes', 'order_confirm_123', 'callback')
    ->button('No', 'order_cancel_123', 'callback');
```

### Upsert contact

Use `ItsChatsApi` directly to sync a contact from your system:

```php
use NotificationChannels\ItsChats\ItsChatsApi;

app(ItsChatsApi::class)->upsertContact([
    'external_id' => (string) $user->id,
    'name'        => $user->first_name,
    'lastname'    => $user->last_name,
    'email'       => $user->email,
    'phone'       => $user->phone,
]);
```

The contact is matched by `external_id` first, then `email`, then `phone`. If not found — it is created.

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
