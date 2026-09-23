<p align="center">
    <a href="https://gronosync.com"><img src="art/logo.png" alt="GronoSync" width="120"></a>
</p>

# GronoSync Notification Channel for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fomvasss/laravel-notification-channel-gronosync.svg)](https://packagist.org/packages/fomvasss/laravel-notification-channel-gronosync)
[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notification-channel-gronosync.svg)](LICENSE.md)

Send Laravel notifications via [GronoSync](https://gronosync.com) — a multi-channel messaging platform supporting Telegram, WhatsApp, Instagram, Facebook, Viber, SMS, email and the built-in chat widget.

[Website](https://gronosync.com) · [Client dashboard](https://app.gronosync.com) · API: `https://api.gronosync.com`

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Sending notifications](#notification-class)
  - [Response](#response)
  - [Errors](#errors)
  - [Contact sync](#upsert-contact)
  - [Form submission (inbound request)](#form-submission-inbound-request)
  - [Contact and channels](#contact-and-channels)
  - [Receiving messages (webhooks)](#receiving-messages-incoming-webhooks)
- [Testing](#testing)
- [Security](#security)
- [Contributing](#contributing)
- [Credits](#credits)
- [License](#license)

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

The `token` is an **organization API token**, created in the [GronoSync dashboard](https://app.gronosync.com) under organization settings →
Extern API tokens. Its value is shown only once, at creation — store it right away.

## Usage

### Notification class

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

Add `routeNotificationForGronosync()` to your notifiable model:

```php
class Customer extends Model
{
    use Notifiable;

    public function routeNotificationForGronosync(): ?string
    {
        return $this->gronosync_contact_id; // UUID of the contact in GronoSync
    }
}
```

If you sync contacts via `upsertContact()` with an `external_id`, you don't need to store the GronoSync UUID — return your own ID from `routeNotificationForGronosyncExternalId()`:

```php
public function routeNotificationForGronosyncExternalId(): ?string
{
    return (string) $this->id; // the same external_id you pass to upsertContact()
}
```

If both methods are defined, `routeNotificationForGronosync()` goes first; the external ID is used only when it returns an empty value. On-demand notifications: `Notification::route('GronosyncExternalId', 'crm-42')->notify(...)`.

Alternatively, set the contact directly on the message:

```php
GronosyncMessage::make()
    ->contactId($customer->gronosync_contact_id)
    ->text('Hello!');
```

If the contact was synced via `upsertContact()` with an `external_id`, you don't have to store the GronoSync UUID — your own ID is enough:

```php
GronosyncMessage::make()
    ->contactExternalId((string) $customer->id)
    ->text('Hello!');
```

Identify the contact with exactly one of `contactId()`, `contactExternalId()` or `to()` — the API rejects several at once with `422`.

### GronosyncMessage methods

| Method | Description |
|---|---|
| `contactId(string $id)` | GronoSync contact UUID |
| `contactExternalId(string $id)` | Contact ID in your system — the `external_id` passed to `upsertContact()`. No need to store the GronoSync UUID |
| `to(string $identifier)` | Contact identifier for the channel type (see below) — use for contacts not yet known by ID |
| `channelId(string $id)` | GronoSync channel UUID (required for new contacts) |
| `text(string $text)` | Message text |
| `attachment(string $url, ?string $filename, string $type)` | Add a single attachment. Types: `image`, `audio`, `video`, `document` |
| `attachments(array $items, string $type)` | Add multiple attachments at once |
| `button(string $title, string $urlOrCallback, string $type)` | Add a button. Types: `web_url`, `callback` |
| `buttons(array $items)` | Add multiple buttons at once |
| `parseMode(string $mode)` | Text formatting: `html` or `markdown` (Telegram) |
| `previewUrl(bool $val)` | Show URL preview: `true` or `false` (WhatsApp) |
| `replyToId(string $id)` | ID of the message being replied to |
| `forwardedFromId(string $id)` | ID of the forwarded message |
| `metadata(array $metadata)` | Arbitrary data of your system (up to 4 KB), e.g. `['ticket_id' => '1234']`. Not sent to the contact — returned in `data.metadata` of the `chat.message.sent` webhook for this message |

What `to()` means depends on the channel type of `channelId()`. The contact is found by this identifier or created:

| Channel type | `to` |
|---|---|
| `telegram` | Telegram user ID |
| `whatsapp` | Phone number |
| `instagram` / `facebook` | Instagram / Facebook user ID (page-scoped) |
| `mail` | Email |
| `sms_turbosms` | Phone number |
| `echat_whatsapp` | Phone number |
| `echat_telegram` / `echat_viber` | Contact ID at the provider |

Phone numbers may be passed in any format (`+38 (050) 111-22-33`) — they are stored as digits only. Widget and form channels (`chat_contact`, `chat_manager`, `form`) don't accept `to` — use `contactId()`.

### Response

`GronosyncApi::sendMessage()` returns the API response (Laravel doesn't pass it back when sending through the notification channel):

```json
{
    "message": "Operation completed successfully.",
    "message_id": "550e8400-e29b-41d4-a716-446655440000",
    "contact_id": "7f3e1200-0000-4c2d-b8b1-000000000001",
    "contact_created": false,
    "chat_id": "9d8c7b6a-0000-4e2f-c9c2-000000000002",
    "sid": "018e1234-0000-7000-a000-000000000001"
}
```

Store `contact_id` to message the same contact later without `to`.

### Examples

**Message with image and button:**

```php
GronosyncMessage::make()
    ->contactId($notifiable->gronosync_contact_id)
    ->text('Your invoice is ready.')
    ->attachment('https://shop.com/invoice/123.pdf', 'invoice.pdf')
    ->button('Download', 'https://shop.com/invoice/123.pdf');
```

**Telegram with HTML formatting:**

```php
GronosyncMessage::make()
    ->contactId($notifiable->gronosync_contact_id)
    ->text('<b>Order confirmed</b> — thank you!')
    ->parseMode('html');
```

**New contact via messenger identifier:**

```php
GronosyncMessage::make()
    ->to('380991234567')          // phone / telegram_id / etc.
    ->channelId($channelUuid)
    ->text('Welcome!');
```

**Callback buttons:**

```php
GronosyncMessage::make()
    ->contactId($notifiable->gronosync_contact_id)
    ->text('Confirm your order?')
    ->button('Yes', 'order_confirm_123', 'callback')
    ->button('No', 'order_cancel_123', 'callback');
```

### Errors

Failures are reported as `CouldNotSendNotification`:

- **Calling `GronosyncApi` directly** (`sendMessage()`, `upsertContact()`) — the exception is thrown.
- **Via the notification channel** — the exception is **not** thrown: Laravel's `NotificationFailed` event is dispatched with the exception in `$event->data['exception']`.

The exception exposes the API response:

| Method | Returns |
|---|---|
| `getStatusCode()` | HTTP status, `null` on network errors / timeouts |
| `getErrorCode()` | Machine-readable `code` from the response (e.g. `chat_blocked`), or `null` |
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

Common errors. The human-readable `message` is localized and may change — branch on the status and `code`, not on the text:

| Status | `code` | Meaning |
|---|---|---|
| `422` | `chat_blocked` | The organization blocked the chat with this contact in GronoSync. The message is neither stored nor delivered. Mark the contact as "do not message" on your side; to send transactional messages (order status, etc.), unblock the chat in GronoSync first |
| `422` | — | One of: the contact unsubscribed from messages; `channelId()` is missing for a new contact (or a contact without a chat); the contact has no identifier for this channel (e.g. no Telegram ID for a Telegram channel); the 24-hour reply window of WhatsApp, Facebook Messenger or Instagram is closed (you can reply only after the contact writes first); `to()` used with a widget/form channel; more than one of `contactId()`, `contactExternalId()`, `to()` set; request validation failed |
| `403` | `organization_suspended` | The organization is suspended by GronoSync. Every request with this token fails the same way until it is reactivated — stop retrying and contact GronoSync support |
| `403` | — | The organization has no active subscription for outgoing messages |
| `404` | — | `contactId()`, `contactExternalId()` or `channelId()` not found in your organization. `contactExternalId()` never creates a contact — call `upsertContact()` first |
| `429` | — | Rate limit: 120 requests per minute per token |

### Upsert contact

Use `GronosyncApi` directly to sync a contact from your system:

```php
use NotificationChannels\Gronosync\GronosyncApi;

app(GronosyncApi::class)->upsertContact([
    'external_id' => (string) $user->id,
    'name'        => $user->first_name,
    'lastname'    => $user->last_name,
    'email'       => $user->email,
    'phone'       => $user->phone,
    'extra'       => ['plan' => 'pro'],
]);
```

The contact is matched by `external_id` first, then `email`, then `phone`. If not found — it is created. When `external_id` is given, email and phone only match a contact without an `external_id` (e.g. one who wrote in a messenger) — a contact already linked to another `external_id` of yours is never taken over: two of your customers sharing a phone get separate contacts.

Accepted fields: `external_id`, `name`, `lastname`, `email`, `phone`, `birthday`, `gender` (`male` / `female`), `locale`, `timezone`, `comment`, `extra` (object, up to 4 KB). Empty values don't overwrite existing data.

> Updating a contact's `email`, `phone`, `name` or `lastname` this way also fires the `contact.updated` webhook — if you subscribed to it and sync contacts yourself, skip events carrying your own `source.token_id` (see [Webhook payload](#webhook-payload)).

Response: `{"id": "...", "created": true, "sid": "..."}`. `sendMessage()` also includes `sid` in its response.
`sid` is the contact's identity token in GronoSync — mainly used for the Telegram continuation link
(`https://t.me/{bot}?start={sid}`). It's not needed for linking this contact to your site's `chat_contact`
widget — pass the same `external_id` there (as `data-external-id`) and it resolves to the same contact
automatically.

### Form submission (inbound request)

A lead from your site, mailbox or CRM can be passed to GronoSync as a request from the contact — as if they filled in the form widget.
It needs a `form` channel (created in the GronoSync cabinet; get its `id` from `getChannels()`):

```php
$result = app(GronosyncApi::class)->submitForm([
    'channel_id' => config('services.gronosync.form_channel_id'),
    'contact_external_id' => (string) $user->id, // optional — otherwise the contact is matched by email/phone or created
    'fields' => [
        'name' => $lead->name,
        'email' => $lead->email,
        'phone' => $lead->phone,
        'message' => $lead->message,
    ],
    'metadata' => ['lead_id' => $lead->id],
]);
// ['message_id' => '...', 'contact_id' => '...', 'chat_id' => '...', 'sid' => '...']
```

Unlike `sendMessage()`, this is an **inbound** message: it comes from the contact and is not delivered anywhere. A manager
is assigned to the chat right away (the AI assistant doesn't answer form submissions) and replies in GronoSync through the
channel set in the form settings (email or SMS). An unknown `contact_id` / `contact_external_id` is a `404`. No subscription required.

Fields are arbitrary keys, at least one must be filled: required fields from the form settings are not enforced here. The
settings provide labels and types; a field outside them is labelled by its key, while `email` and `phone` are recognized by
name. Format is always checked (`422`): email, phone (10–15 digits, stored as digits only), length up to 255 characters
(`textarea` and fields outside the settings — up to 5000), at most 20 fields. The contact's email and phone come from the
first fields of that type, the name — from `name`. In the `chat.message.received` webhook fields arrive both as text and
separately in `form_fields` (`[{"name", "label", "type", "value"}]`).

### Contact and channels

Read a contact card — by the GronoSync UUID or by your `external_id`:

```php
$api = app(GronosyncApi::class);

$contact = $api->getContactByExternalId((string) $customer->id); // or $api->getContact($uuid)
```

Returns the response `data`: contact fields (the same as in the `contact.created` webhook), `sid`, `chat` and `channels`.
A contact not found in your organization throws `CouldNotSendNotification` with status `404`.

- `chat` — `id`, `status` (`active` / `closed`), `is_blocked`, `is_ai_on`, `unread_count`, `activity_at` and `manager`
  (`id`, `name`, `lastname`, the organization member's `external_id`), or `null` if nobody is assigned.
- `channels` — where the contact can be messaged right now: channels they have already used, SMS if a phone is known
  and email if an email is known. Each has `can_send` and `reply_window_ends_at`: `can_send: false` means the
  WhatsApp, Facebook or Instagram reply window is closed — you can send once the contact writes first. A messenger
  the contact has never written in is not listed — you can't start a conversation there.

```php
$channel = collect($contact['channels'])->firstWhere('can_send', true);

if ($channel) {
    $api->sendMessage(
        GronosyncMessage::make()->contactExternalId((string) $customer->id)->channelId($channel['id'])->text('Hello!')
    );
}
```

All organization channels (`id`, `name`, `type`, `status`) — the source of `channelId()` for a contact who hasn't written yet:

```php
$mail = collect($api->getChannels())->firstWhere('type', 'mail');
```

## Receiving messages (incoming webhooks)

GronoSync can notify your application via HTTP POST when specific events happen in your organization. This enables bidirectional integration: your app sends notifications to contacts, and GronoSync pushes events (new contacts, incoming messages, chats needing attention, ...) back to your app.

This is useful for CRM systems (1C, WooCommerce, Drupal, etc.) that need to react to customer activity.

### Configure the webhook

In the [GronoSync dashboard](https://app.gronosync.com), go to organization settings → Webhook. Set a URL and pick which events to subscribe to. The `secret` is generated the first time you save the webhook (and shown any time via a "regenerate secret" action).

### Events

| Event | Fired when |
|---|---|
| `contact.created` | A contact reaches out for the first time (messenger, chat widget, form, email), or you message a new contact via `to()`. Not fired for contacts created by `upsertContact()`, bulk import, or just opening a page with the widget |
| `chat.message.received` | A contact sends a new message |
| `chat.message.sent` | Your side writes to a contact: a manager, the AI assistant, the API (including messages you sent with `to()`) or a system message (e.g. a welcome message). Internal notes and chat log entries are not sent |
| `chat.manager_needed` | The AI assistant hands the chat over to a human manager |
| `chat.closed` | A manager closes a chat |
| `contact.updated` | A contact's email, phone, name or lastname changes (including via `upsertContact()`) |

### Webhook payload

GronoSync sends a `POST` request with JSON body:

```json
{
    "event_id": "0a1b2c3d-0000-4e2f-b1b2-000000000000",
    "event": "chat.message.received",
    "organization_id": "9d4c1a00-0000-4e2f-b1b2-000000000001",
    "source": {"type": "system"},
    "data": {
        "id": "9d4c1a00-0000-4e2f-b1b2-000000000002",
        "type": "text",
        "creator_type": "contact",
        "content": "Hello, I need help with my order.",
        "internal_type": null,
        "channel": {"id": "550e8400-e29b-41d4-a716-446655440000", "name": "Telegram Bot", "type": "telegram"},
        "created_at": "2024-06-01T10:00:00.000000Z",
        "updated_at": "2024-06-01T10:00:00.000000Z",
        "member": {"role": "client", "fullname": "John Doe"},
        "files": []
    }
}
```

`source` tells who caused the event: `{"type": "extern_api", "token_id": 12, "token_name": "CRM"}` (a change made through the API, including this package), `{"type": "member", "member_id": "...", "external_id": "..."}` (a manager in the cabinet or widget) or `{"type": "system"}` (messengers, AI, scheduler). If you sync contacts both ways, skip events carrying your own `token_id` — otherwise `upsertContact()` comes back to you as `contact.updated` and the change loops. `token_id` is the `id` from `GET /api/my/organizations/{id}/extern-tokens`.

`data` shape depends on `event`:

| Event | `data` |
|---|---|
| `chat.message.received` / `chat.message.sent` | Message: `id`, `type`, `creator_type`, `content`, `channel`, `member`, `files`, `reply_to`, `created_at`, plus `chat_id` and `contact` (`id`, `external_id`, `name`, `lastname`, `email`, `phone`) — whose message it is. Form submissions also have `form_fields`. `metadata` — if you passed it via `metadata()` or `submitForm()`. Messages sent from a Meta ad (Facebook / Instagram / WhatsApp) also have `referral`: `source`, `ad_id`, `post_id`, `title`, `body`, `url`, `ref`, `click_id` (only non-empty keys) |
| `contact.created` / `contact.updated` | Contact: `id`, `name`, `lastname`, `email`, `phone`, `locale`, `timezone`, `extra`, `external_id`, messenger IDs, `created_via` (how the contact appeared: `messenger`, `widget`, `form`, `mail`, `extern_api`, `import`; `null` for older contacts), `created_channel_id`, … Contacts that came from a Meta ad have `ad_referral` (the first ad touch, same keys as `referral` plus `channel_id`, `received_at`), otherwise `null` |
| `chat.manager_needed` / `chat.closed` | `{"chat_id": "...", "contact": {"id", "external_id", "name", "lastname", "email", "phone"}}` |

### Verify the request

Every request includes an `X-Webhook-Secret` header with your webhook's secret (a shared secret, not a signature). Compare it in constant time to confirm the request came from GronoSync:

```php
// routes/api.php
Route::post('/webhooks/gronosync', [GronosyncWebhookController::class, 'handle'])
    ->middleware('throttle:60,1');
```

```php
// app/Http/Controllers/GronosyncWebhookController.php
class GronosyncWebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        // store the webhook secret in your own config, e.g. GRONOSYNC_WEBHOOK_SECRET
        if (!hash_equals((string) config('services.gronosync.webhook_secret'), (string) $request->header('X-Webhook-Secret'))) {
            abort(401);
        }

        $event = $request->input('event');       // e.g. "chat.message.received"
        $data  = $request->input('data');
        $orgId = $request->input('organization_id');

        if ($event === 'chat.message.received') {
            // Handle incoming message from contact
            // e.g. update CRM, trigger a workflow, log to 1C
        }

        return response()->json(['ok' => true]);
    }
}
```

> **Note:** a delivery is considered failed on a network error, a timeout (10 seconds) or a non-`2xx` response; GronoSync makes up to 3 attempts with a 30-second backoff. Return a `2xx` response as quickly as possible and dispatch heavy processing to a queue. The same event may arrive more than once — every attempt carries the same `event_id`, so store processed ids and skip repeats. `chat.message.sent` also echoes messages you sent through this package: skip them by `message_id` returned from `sendMessage()`, by your own `metadata`, or by `creator_type = extern`.

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
