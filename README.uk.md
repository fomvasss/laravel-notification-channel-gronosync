# ItsChats Notification Channel для Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fomvasss/laravel-notification-channel-itschats.svg)](https://packagist.org/packages/fomvasss/laravel-notification-channel-itschats)
[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notification-channel-itschats.svg)](LICENSE.md)

Надсилайте Laravel-нотифікації через [ItsChats](https://itschats.com) — мультиканальну платформу обміну повідомленнями з підтримкою Telegram, WhatsApp, Instagram, Facebook та вбудованого чат-віджету.

## Зміст

- [Встановлення](#встановлення)
- [Налаштування](#налаштування)
- [Використання](#використання)
  - [Надсилання нотифікацій](#клас-нотифікації)
  - [Синхронізація контактів](#upsert-контакту)
  - [Отримання повідомлень (вебхуки)](#отримання-повідомлень-вхідні-вебхуки)
- [Тестування](#тестування)
- [Безпека](#безпека)
- [Участь у розробці](#участь-у-розробці)
- [Автори](#автори)
- [Ліцензія](#ліцензія)

## Встановлення

```bash
composer require fomvasss/laravel-notification-channel-itschats
```

## Налаштування

Додайте до `.env`:

```env
ITSCHATS_URL=https://your-itschats-domain.com
ITSCHATS_TOKEN=your_widget_manager_token
```

Додайте до `config/services.php`:

```php
'itschats' => [
    'url'   => env('ITSCHATS_URL'),
    'token' => env('ITSCHATS_TOKEN'),
],
```

`token` — це **Widget Manager token** (тип `wm`) з налаштувань вашої організації в ItsChats.

## Використання

### Клас нотифікації

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
            ->text("Ваше замовлення #{$this->order->id} підтверджено!")
            ->button('Переглянути замовлення', "https://shop.com/orders/{$this->order->id}");
    }
}
```

### Маршрутизація

Додайте метод `routeNotificationForItsChats()` до вашої notifiable-моделі:

```php
class Customer extends Model
{
    use Notifiable;

    public function routeNotificationForItsChats(): ?string
    {
        return $this->itschats_contact_id; // UUID контакту в ItsChats
    }
}
```

Або вкажіть контакт безпосередньо в повідомленні:

```php
ItsChatsMessage::make()
    ->contactId($customer->itschats_contact_id)
    ->text('Привіт!');
```

### Методи ItsChatsMessage

| Метод | Опис |
|---|---|
| `contactId(string $id)` | UUID контакту в ItsChats |
| `to(string $identifier)` | Ідентифікатор у месенджері (telegram_id, телефон, email тощо) |
| `channelId(string $id)` | UUID каналу в ItsChats (обов'язковий для нових контактів) |
| `text(string $text)` | Текст повідомлення |
| `attachment(string $url, ?string $filename, string $type)` | Додати один файл. Типи: `image`, `audio`, `video`, `document` |
| `attachments(array $items, string $type)` | Додати кілька файлів одразу |
| `button(string $title, string $urlOrCallback, string $type)` | Додати кнопку. Типи: `web_url`, `callback` |
| `buttons(array $items)` | Додати кілька кнопок одразу |
| `parseMode(string $mode)` | Форматування тексту: `html` або `markdown` (Telegram) |
| `previewUrl(bool $val)` | Показувати попередній перегляд URL: `true` або `false` (WhatsApp) |
| `replyToId(string $id)` | ID повідомлення, на яке надсилається відповідь |
| `forwardedFromId(string $id)` | ID пересланого повідомлення |

### Приклади

**Повідомлення із зображенням та кнопкою:**

```php
ItsChatsMessage::make()
    ->contactId($notifiable->itschats_contact_id)
    ->text('Ваш рахунок готовий.')
    ->attachment('https://shop.com/invoice/123.pdf', 'invoice.pdf')
    ->button('Завантажити', 'https://shop.com/invoice/123.pdf');
```

**Telegram з HTML-форматуванням:**

```php
ItsChatsMessage::make()
    ->contactId($notifiable->itschats_contact_id)
    ->text('<b>Замовлення підтверджено</b> — дякуємо!')
    ->parseMode('html');
```

**Новий контакт через ідентифікатор месенджера:**

```php
ItsChatsMessage::make()
    ->to('380991234567')          // телефон / telegram_id тощо
    ->channelId($channelUuid)
    ->text('Ласкаво просимо!');
```

**Кнопки зі зворотним викликом:**

```php
ItsChatsMessage::make()
    ->contactId($notifiable->itschats_contact_id)
    ->text('Підтвердити замовлення?')
    ->button('Так', 'order_confirm_123', 'callback')
    ->button('Ні', 'order_cancel_123', 'callback');
```

### Upsert контакту

Використовуйте `ItsChatsApi` напряму для синхронізації контакту з вашої системи:

```php
use NotificationChannels\ItsChats\ItsChatsApi;

app(ItsChatsApi::class)->upsertContact([
    'external_id' => (string) $user->id,
    'name'        => $user->first_name,
    'lastname'    => $user->last_name,
    'email'       => $user->email,
    'phone'       => $user->phone,
    'extra'       => ['plan' => 'pro'],
]);
```

Контакт шукається спочатку за `external_id`, потім за `email`, потім за `phone`. Якщо не знайдено — створюється новий.

Доступні поля: `external_id`, `name`, `lastname`, `email`, `phone`, `birthday`, `gender`, `locale`, `comment`, `extra`.

Відповідь: `{"id": "...", "created": true, "sid": "..."}`. `sendMessage()` теж повертає `sid` у відповіді.
`sid` — токен ідентичності контакту в ItsChats, потрібен переважно для Telegram-лінку продовження діалогу
(`https://t.me/{bot}?start={sid}`). Для зв'язку цього контакту з `chat_contact`-віджетом на вашому сайті він
не потрібен — передайте той самий `external_id` туди (як `data-external-id`), і він автоматично прив'яжеться
до того ж контакту.

## Отримання повідомлень (вхідні вебхуки)

ItsChats може сповіщати ваш додаток через HTTP POST щоразу, коли в чаті з'являється нове повідомлення. Це дозволяє реалізувати двосторонню інтеграцію: ваш додаток надсилає нотифікації контактам, а ItsChats повертає вхідні повідомлення від контактів назад до вас.

Це особливо актуально для CRM-систем (1C, WooCommerce, Drupal тощо), яким потрібно реагувати на відповіді клієнтів.

### Налаштування вебхуку

В акаунті ItsChats перейдіть до адмін-панелі → налаштування організації → віджети. Створіть або відредагуйте **Widget Manager** віджет і вкажіть URL вебхуку. За бажанням оберіть фільтр за типом відправника (`contact`, `manager`, `ai`, `extern`, `system`) — якщо не задано, надходять усі типи повідомлень.

### Структура payload

ItsChats надсилає `POST`-запит з JSON-тілом:

```json
{
    "event": "chatmessage.new",
    "organization_id": "9d4c1a00-0000-4e2f-b1b2-000000000001",
    "message": {
        "id": "9d4c1a00-0000-4e2f-b1b2-000000000002",
        "type": "text",
        "creator_type": "contact",
        "content": "Привіт, мені потрібна допомога з замовленням.",
        "created_at": "2024-06-01T10:00:00.000000Z",
        "updated_at": "2024-06-01T10:00:00.000000Z",
        "reactions": []
    }
}
```

| Поле | Значення |
|---|---|
| `event` | `chatmessage.new` |
| `message.type` | `text`, `image`, `audio`, `video`, `file` |
| `message.creator_type` | `contact`, `manager`, `ai`, `extern`, `system` |

### Верифікація запиту

Кожен запит містить заголовок `X-Widget-Token` з токеном віджета. Використовуйте його для перевірки автентичності запиту:

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
            // Обробка вхідного повідомлення від контакту
            // наприклад: оновлення CRM, тригер воркфлоу, логування в 1С
        }

        return response()->json(['ok' => true]);
    }
}
```

> **Примітка:** ItsChats повторює доставку вебхуку до 3 разів з інтервалом 30 секунд у разі помилки. Поверніть відповідь `2xx` якнайшвидше, а важку обробку передайте в чергу.

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
