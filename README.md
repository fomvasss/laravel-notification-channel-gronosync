<p align="center">
    <a href="https://gronosync.com"><img src="art/logo.png" alt="GronoSync" width="120"></a>
</p>

# GronoSync Notification Channel для Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fomvasss/laravel-notification-channel-gronosync.svg)](https://packagist.org/packages/fomvasss/laravel-notification-channel-gronosync)
[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notification-channel-gronosync.svg)](LICENSE.md)

**Українська** · [English](README.en.md)

Надсилайте Laravel-нотифікації через [GronoSync](https://gronosync.com) — мультиканальну платформу обміну повідомленнями з підтримкою Telegram, WhatsApp, Instagram, Facebook, Viber, SMS, email та вбудованого чат-віджету.

[Сайт](https://gronosync.com) · [Кабінет клієнта](https://app.gronosync.com) · API: `https://api.gronosync.com`

## Документація

Цей README — про Laravel-пакет. Усе про сам сервіс GronoSync — на **[docs.gronosync.com](https://docs.gronosync.com)**:

- [Extern API](https://docs.gronosync.com/api/overview/) — контакти, повідомлення, заявки, канали, ліміти
- [Помилки й коди](https://docs.gronosync.com/api/errors/) — кожен статус і `code` і що з ними робити
- [Вебхуки](https://docs.gronosync.com/webhooks/overview/) — події, payload з повними прикладами JSON, перевірка, повтори
- [Віджети на сайт](https://docs.gronosync.com/widgets/overview/) — чат, форма й чат менеджера для вашої CRM
- [Ключі API](https://docs.gronosync.com/authentication/) · [Зміни API](https://docs.gronosync.com/changelog/)

## Зміст

- [Встановлення](#встановлення)
- [Налаштування](#налаштування)
- [Надсилання сповіщень](#надсилання-сповіщень)
- [Виклик API напряму](#виклик-api-напряму)
- [Помилки](#помилки)
- [Отримання вебхуків](#отримання-вебхуків)
- [Тестування](#тестування)

## Встановлення

```bash
composer require fomvasss/laravel-notification-channel-gronosync
```

## Налаштування

Додайте до `.env`:

```env
GRONOSYNC_URL=https://api.gronosync.com
GRONOSYNC_TOKEN=your_organization_token
```

Додайте до `config/services.php`:

```php
'gronosync' => [
    'url'   => env('GRONOSYNC_URL'),
    'token' => env('GRONOSYNC_TOKEN'),
],
```

`token` — ключ API організації: [кабінет GronoSync](https://app.gronosync.com) → **Налаштування → Розширення → API**. Показується лише раз, при створенні. Див. [Ключі API](https://docs.gronosync.com/authentication/).

## Надсилання сповіщень

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
            ->text("Ваше замовлення #{$this->order->id} підтверджено!")
            ->button('Переглянути замовлення', "https://shop.com/orders/{$this->order->id}");
    }
}
```

### Маршрутизація

Поверніть контакт із notifiable-моделі — UUID у GronoSync або ваш ID, якщо синхронізуєте контакти через `upsertContact()` з `external_id`:

```php
public function routeNotificationForGronosync(): ?string
{
    return $this->gronosync_contact_id; // UUID контакту в GronoSync
}

public function routeNotificationForGronosyncExternalId(): ?string
{
    return (string) $this->id; // той самий external_id, що передається в upsertContact()
}
```

Якщо визначено обидва, спершу береться `routeNotificationForGronosync()`, а external ID — лише коли той повертає порожнє значення. Без моделі: `Notification::route('GronosyncExternalId', 'crm-42')->notify(...)`.

Або вкажіть контакт у повідомленні — рівно одним із `contactId()`, `contactExternalId()`, `to()`:

```php
GronosyncMessage::make()->contactExternalId((string) $customer->id)->text('Привіт!');

// новий контакт за ідентифікатором у каналі (телефон, Telegram ID, email…)
GronosyncMessage::make()->to('+380991234567')->channelId($smsChannelId)->text('Ласкаво просимо!');
```

Що означає `to` для кожного типу каналу і коли потрібен `channelId()` — [Повідомлення](https://docs.gronosync.com/api/messages/).

### Методи GronosyncMessage

| Метод | Опис |
|---|---|
| `contactId(string $id)` | UUID контакту в GronoSync |
| `contactExternalId(string $id)` | ID контакту у вашій системі — `external_id`, переданий в `upsertContact()` |
| `to(string $identifier)` | Ідентифікатор контакту в каналі — для контактів, яких ще немає |
| `channelId(string $id)` | UUID каналу в GronoSync (обов'язковий з `to()`) |
| `text(string $text)` | Текст повідомлення |
| `attachment(string $url, ?string $filename, string $type)` | Додати файл. Типи: `image`, `audio`, `video`, `document` |
| `attachments(array $items, string $type)` | Додати кілька файлів |
| `button(string $title, string $urlOrCallback, string $type)` | Додати кнопку. Типи: `web_url`, `callback` |
| `buttons(array $items)` | Додати кілька кнопок |
| `parseMode(string $mode)` | `html` або `markdown` (Telegram) |
| `previewUrl(bool $val)` | Прев'ю посилань (WhatsApp) |
| `replyToId(string $id)` | Повідомлення, на яке це відповідь |
| `forwardedFromId(string $id)` | Переслане повідомлення |
| `metadata(array $metadata)` | Ваші дані (до 4 КБ) — повертаються в `data.metadata` вебхука `chat.message.sent` |

## Виклик API напряму

`GronosyncApi` — тонкий клієнт [Extern API](https://docs.gronosync.com/api/overview/), повертає розібрану відповідь.

| Метод | Ендпоінт |
|---|---|
| `sendMessage(GronosyncMessage $message)` | [`POST /message`](https://docs.gronosync.com/api/messages/) |
| `upsertContact(array $data)` | [`POST /contacts`](https://docs.gronosync.com/api/contacts/) |
| `getContact(string $id)` / `getContactByExternalId(string $externalId)` | [`GET /contacts/{id}`](https://docs.gronosync.com/api/contacts/) — повертає `data` |
| `getChannels()` | [`GET /channels`](https://docs.gronosync.com/api/channels/) — повертає `data` |
| `submitForm(array $data, ?string $idempotencyKey = null)` | [`POST /form`](https://docs.gronosync.com/api/forms/), ключ іде заголовком `Idempotency-Key` |

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

Невідомі ключі в `upsertContact()` / `submitForm()` відкидаються перед відправкою.

## Помилки

Збої повертаються як `CouldNotSendNotification`:

- **Прямий виклик `GronosyncApi`** — виняток кидається.
- **Через канал сповіщень** — виняток **не** кидається: диспатчиться подія Laravel `NotificationFailed` з винятком у `$event->data['exception']`.

| Метод | Повертає |
|---|---|
| `getStatusCode()` | HTTP-статус, `null` при мережевій помилці / таймауті |
| `getErrorCode()` | Машинний `code` (напр. `chat_blocked`) або `null` |
| `getResponse()` | Розібране JSON-тіло відповіді з помилкою або `null` |

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

Орієнтуйтесь на статус і `code`, а не на текст. Усі коди й що з ними робити — [Помилки й коди](https://docs.gronosync.com/api/errors/).

## Отримання вебхуків

GronoSync може сповіщати ваш застосунок про нові контакти, вхідні й вихідні повідомлення та чати, яким потрібен менеджер. Налаштування, події, payload і приклад контролера для Laravel — [Вебхуки](https://docs.gronosync.com/webhooks/overview/) і [Laravel](https://docs.gronosync.com/packages/laravel/).

## Тестування

```bash
composer test
composer test:coverage
```

## Безпека

Якщо ви виявили проблему безпеки, надішліть листа на fomin.vasil@gmail.com замість використання трекеру задач.

## Участь у розробці

Деталі: [CONTRIBUTING.md](CONTRIBUTING.md).

## Автори

- [Fomin Vasil](https://github.com/fomvasss)
- [All Contributors](../../contributors)

## Ліцензія

MIT License. Детальніше: [LICENSE.md](LICENSE.md).
