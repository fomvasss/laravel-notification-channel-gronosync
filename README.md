# ItsChats Notification Channel for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fomvasss/laravel-notification-channel-itschats.svg)](https://packagist.org/packages/fomvasss/laravel-notification-channel-itschats)
[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notification-channel-itschats.svg)](LICENSE.md)

Send Laravel notifications via [ItsChats](https://itschats.com) — a multi-channel messaging platform supporting Telegram, WhatsApp, Instagram, Facebook and the built-in chat widget.

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Sending notifications](#notification-class)
  - [Contact sync](#upsert-contact)
  - [Receiving messages (webhooks)](#receiving-messages-incoming-webhooks)
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

Accepted fields: `external_id`, `name`, `lastname`, `email`, `phone`, `telegram_id`, `whatsapp_id`, `instagram_id`, `facebook_id`.

## Receiving messages (incoming webhooks)

ItsChats can notify your application via HTTP POST whenever a new message appears in a chat. This enables bidirectional integration: your app sends notifications to contacts, and ItsChats pushes incoming contact messages back to your app.

This is useful for CRM systems (1C, WooCommerce, Drupal, etc.) that need to react to customer replies.

### Configure the webhook

In your ItsChats account, go to the admin panel → organization settings → widgets. Create or edit a **Widget Manager** widget and add your webhook URL. You can optionally filter by sender type (`contact`, `manager`, `ai`, `api`, `system`) — leave it empty to receive all message types.

### Webhook payload

ItsChats sends a `POST` request with JSON body:

```json
{
    "event": "chatmessage.new",
    "organization_id": "9d4c1a00-0000-4e2f-b1b2-000000000001",
    "message": {
        "id": "9d4c1a00-0000-4e2f-b1b2-000000000002",
        "type": "text",
        "creator_type": "contact",
        "content": "Hello, I need help with my order.",
        "created_at": "2024-06-01T10:00:00.000000Z",
        "updated_at": "2024-06-01T10:00:00.000000Z",
        "reactions": []
    }
}
```

| Field | Values |
|---|---|
| `event` | `chatmessage.new` |
| `message.type` | `text`, `image`, `audio`, `video`, `file` |
| `message.creator_type` | `contact`, `manager`, `ai`, `api`, `system` |

### Verify the signature

Every request includes an `X-Widget-Token` header containing the widget's token. Use it to confirm the request came from ItsChats:

```php
// routes/api.php
Route::post('/webhooks/itschats', [ItsChatsWebhookController::class, 'handle'])
    ->middleware('throttle:60,1');
```

```php
// app/Http/Controllers/ItsChatsWebhookController.php
class ItsChatsWebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $request->header('X-Widget-Token');

        if ($token !== config('services.itschats.token')) {
            abort(401);
        }

        $event   = $request->input('event');            // "chatmessage.new"
        $message = $request->input('message');
        $orgId   = $request->input('organization_id');

        if ($event === 'chatmessage.new' && $message['creator_type'] === 'contact') {
            // Handle incoming message from contact
            // e.g. update CRM, trigger a workflow, log to 1C
        }

        return response()->json(['ok' => true]);
    }
}
```

> **Note:** ItsChats retries failed webhook deliveries up to 3 times with a 30-second backoff. Return a `2xx` response as quickly as possible and dispatch heavy processing to a queue.

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
