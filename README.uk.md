<p align="center">
    <a href="https://gronosync.com"><img src="art/logo.png" alt="GronoSync" width="120"></a>
</p>

# GronoSync Notification Channel для Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fomvasss/laravel-notification-channel-gronosync.svg)](https://packagist.org/packages/fomvasss/laravel-notification-channel-gronosync)
[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notification-channel-gronosync.svg)](LICENSE.md)

Надсилайте Laravel-нотифікації через [GronoSync](https://gronosync.com) — мультиканальну платформу обміну повідомленнями з підтримкою Telegram, WhatsApp, Instagram, Facebook, Viber, SMS, email та вбудованого чат-віджету.

[Сайт](https://gronosync.com) · [Кабінет клієнта](https://app.gronosync.com) · API: `https://api.gronosync.com`

## Зміст

- [Встановлення](#встановлення)
- [Налаштування](#налаштування)
- [Використання](#використання)
  - [Надсилання нотифікацій](#клас-нотифікації)
  - [Відповідь](#відповідь)
  - [Помилки](#помилки)
  - [Синхронізація контактів](#upsert-контакту)
  - [Контакт і канали](#контакт-і-канали)
  - [Отримання повідомлень (вебхуки)](#отримання-повідомлень-вхідні-вебхуки)
- [Тестування](#тестування)
- [Безпека](#безпека)
- [Участь у розробці](#участь-у-розробці)
- [Автори](#автори)
- [Ліцензія](#ліцензія)

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

`token` — це **токен організації** для API, створюється в [кабінеті GronoSync](https://app.gronosync.com) у налаштуваннях організації →
Extern API tokens. Значення показується лише один раз, при створенні — збережіть його одразу.

## Використання

### Клас нотифікації

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

Додайте метод `routeNotificationForGronosync()` до вашої notifiable-моделі:

```php
class Customer extends Model
{
    use Notifiable;

    public function routeNotificationForGronosync(): ?string
    {
        return $this->gronosync_contact_id; // UUID контакту в GronoSync
    }
}
```

Якщо контакти синхронізуються через `upsertContact()` з `external_id`, UUID GronoSync можна не зберігати — поверніть свій ID з `routeNotificationForGronosyncExternalId()`:

```php
public function routeNotificationForGronosyncExternalId(): ?string
{
    return (string) $this->id; // той самий external_id, що передається в upsertContact()
}
```

Якщо визначено обидва методи, спершу береться `routeNotificationForGronosync()`, а до external ID канал звертається, лише коли той повертає порожнє значення. Сповіщення без моделі: `Notification::route('GronosyncExternalId', 'crm-42')->notify(...)`.

Або вкажіть контакт безпосередньо в повідомленні:

```php
GronosyncMessage::make()
    ->contactId($customer->gronosync_contact_id)
    ->text('Привіт!');
```

Якщо контакт синхронізовано через `upsertContact()` з `external_id`, UUID GronoSync зберігати не обов'язково — достатньо вашого ID:

```php
GronosyncMessage::make()
    ->contactExternalId((string) $customer->id)
    ->text('Привіт!');
```

Контакт вказується рівно одним із `contactId()`, `contactExternalId()` або `to()` — кілька одразу API відхиляє з `422`.

### Методи GronosyncMessage

| Метод | Опис |
|---|---|
| `contactId(string $id)` | UUID контакту в GronoSync |
| `contactExternalId(string $id)` | ID контакту у вашій системі — `external_id`, переданий в `upsertContact()`. Не потрібно зберігати UUID GronoSync |
| `to(string $identifier)` | Ідентифікатор контакту для типу каналу (див. нижче) — для контактів, чий ID ще невідомий |
| `channelId(string $id)` | UUID каналу в GronoSync (обов'язковий для нових контактів) |
| `text(string $text)` | Текст повідомлення |
| `attachment(string $url, ?string $filename, string $type)` | Додати один файл. Типи: `image`, `audio`, `video`, `document` |
| `attachments(array $items, string $type)` | Додати кілька файлів одразу |
| `button(string $title, string $urlOrCallback, string $type)` | Додати кнопку. Типи: `web_url`, `callback` |
| `buttons(array $items)` | Додати кілька кнопок одразу |
| `parseMode(string $mode)` | Форматування тексту: `html` або `markdown` (Telegram) |
| `previewUrl(bool $val)` | Показувати попередній перегляд URL: `true` або `false` (WhatsApp) |
| `replyToId(string $id)` | ID повідомлення, на яке надсилається відповідь |
| `forwardedFromId(string $id)` | ID пересланого повідомлення |

Що означає `to()`, залежить від типу каналу `channelId()`. Контакт шукається за цим ідентифікатором або створюється:

| Тип каналу | `to` |
|---|---|
| `telegram` | Telegram user ID |
| `whatsapp` | Номер телефону |
| `instagram` / `facebook` | ID користувача Instagram / Facebook (page-scoped) |
| `mail` | Email |
| `sms_turbosms` | Номер телефону |
| `echat_whatsapp` | Номер телефону |
| `echat_telegram` / `echat_viber` | ID контакту в провайдера |

Номери можна передавати в будь-якому форматі (`+38 (050) 111-22-33`) — зберігаються лише цифрами. Канали віджетів і форм (`chat_contact`, `chat_manager`, `form`) `to` не приймають — використовуйте `contactId()`.

### Відповідь

`GronosyncApi::sendMessage()` повертає відповідь API (через канал нотифікацій Laravel її не віддає):

```json
{
    "message": "Операцію успішно виконано.",
    "message_id": "550e8400-e29b-41d4-a716-446655440000",
    "contact_id": "7f3e1200-0000-4c2d-b8b1-000000000001",
    "contact_created": false,
    "chat_id": "9d8c7b6a-0000-4e2f-c9c2-000000000002",
    "sid": "018e1234-0000-7000-a000-000000000001"
}
```

Збережіть `contact_id`, щоб надалі писати тому самому контакту без `to`.

### Приклади

**Повідомлення із зображенням та кнопкою:**

```php
GronosyncMessage::make()
    ->contactId($notifiable->gronosync_contact_id)
    ->text('Ваш рахунок готовий.')
    ->attachment('https://shop.com/invoice/123.pdf', 'invoice.pdf')
    ->button('Завантажити', 'https://shop.com/invoice/123.pdf');
```

**Telegram з HTML-форматуванням:**

```php
GronosyncMessage::make()
    ->contactId($notifiable->gronosync_contact_id)
    ->text('<b>Замовлення підтверджено</b> — дякуємо!')
    ->parseMode('html');
```

**Новий контакт через ідентифікатор месенджера:**

```php
GronosyncMessage::make()
    ->to('380991234567')          // телефон / telegram_id тощо
    ->channelId($channelUuid)
    ->text('Ласкаво просимо!');
```

**Кнопки зі зворотним викликом:**

```php
GronosyncMessage::make()
    ->contactId($notifiable->gronosync_contact_id)
    ->text('Підтвердити замовлення?')
    ->button('Так', 'order_confirm_123', 'callback')
    ->button('Ні', 'order_cancel_123', 'callback');
```

### Помилки

Збої повертаються як `CouldNotSendNotification`:

- **Прямий виклик `GronosyncApi`** (`sendMessage()`, `upsertContact()`) — виняток кидається.
- **Через канал нотифікацій** — виняток **не** кидається: диспатчиться подія Laravel `NotificationFailed` з винятком у `$event->data['exception']`.

Виняток дає доступ до відповіді API:

| Метод | Повертає |
|---|---|
| `getStatusCode()` | HTTP-статус, `null` при мережевій помилці / таймауті |
| `getErrorCode()` | Машинний `code` з відповіді (напр. `chat_blocked`) або `null` |
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

Типові помилки. Людський текст `message` локалізований і може змінюватись — орієнтуйтесь на статус і `code`, а не на текст:

| Статус | `code` | Що означає |
|---|---|---|
| `422` | `chat_blocked` | Організація заблокувала чат з цим контактом в GronoSync. Повідомлення не зберігається й не доставляється. Позначте контакт у себе як «не писати»; щоб надсилати транзакційні повідомлення (статус замовлення тощо) — спершу розблокуйте чат в GronoSync |
| `422` | — | Одне з: контакт відписався від повідомлень; не вказано `channelId()` для нового контакту (або контакту без чату); у контакта немає ідентифікатора для цього каналу (напр. Telegram ID для Telegram-каналу); закрите 24-годинне вікно відповіді WhatsApp, Facebook Messenger чи Instagram (відповісти можна після того, як контакт напише сам); `to()` для каналу віджета/форми; вказано більше одного з `contactId()`, `contactExternalId()`, `to()`; не пройшла валідація запиту |
| `403` | `organization_suspended` | Організацію призупинено в GronoSync. Кожен запит з цим токеном відповідатиме так само, доки її не відновлять — не повторюйте спроби, зверніться в підтримку GronoSync |
| `403` | — | В організації немає активної підписки на вихідні повідомлення |
| `404` | — | `contactId()`, `contactExternalId()` або `channelId()` не знайдено у вашій організації. Контакт за `contactExternalId()` не створюється — спершу `upsertContact()` |
| `429` | — | Ліміт: 120 запитів на хвилину на токен |

### Upsert контакту

Використовуйте `GronosyncApi` напряму для синхронізації контакту з вашої системи:

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

Контакт шукається спочатку за `external_id`, потім за `email`, потім за `phone`. Якщо не знайдено — створюється новий.

Доступні поля: `external_id`, `name`, `lastname`, `email`, `phone`, `birthday`, `gender` (`male` / `female`), `locale`, `timezone`, `comment`, `extra` (об'єкт, до 4 КБ). Порожні значення наявних даних не перезаписують.

> Зміна `email`, `phone`, `name` чи `lastname` контакту таким способом теж кидає вебхук `contact.updated` — якщо ви на нього підписані й самі синхронізуєте контакти, пропускайте події зі своїм `source.token_id` (див. [Структура payload](#структура-payload)).

Відповідь: `{"id": "...", "created": true, "sid": "..."}`. `sendMessage()` теж повертає `sid` у відповіді.
`sid` — токен ідентичності контакту в GronoSync, потрібен переважно для Telegram-лінку продовження діалогу
(`https://t.me/{bot}?start={sid}`). Для зв'язку цього контакту з `chat_contact`-віджетом на вашому сайті він
не потрібен — передайте той самий `external_id` туди (як `data-external-id`), і він автоматично прив'яжеться
до того ж контакту.

### Контакт і канали

Прочитати картку контакту — за UUID GronoSync або за вашим `external_id`:

```php
$api = app(GronosyncApi::class);

$contact = $api->getContactByExternalId((string) $customer->id); // або $api->getContact($uuid)
```

Повертається `data` відповіді: поля контакту (ті самі, що у вебхуку `contact.created`), `sid`, `chat` і `channels`.
Контакт не знайдено у вашій організації — `CouldNotSendNotification` зі статусом `404`.

- `chat` — `id`, `status` (`active` / `closed`), `is_blocked`, `is_ai_on`, `unread_count`, `activity_at` і `manager`
  (`id`, `name`, `lastname`, `external_id` учасника організації) або `null`, якщо чат нічий.
- `channels` — куди можна написати контакту зараз: канали, якими він уже користувався, SMS за наявним телефоном і
  пошта за наявним email. У кожного — `can_send` і `reply_window_ends_at`: `can_send: false` означає закрите вікно
  відповіді WhatsApp, Facebook чи Instagram — надіслати можна буде, коли контакт напише сам. Месенджера, у який
  контакт сам не писав, у списку немає — першим туди не написати.

```php
$channel = collect($contact['channels'])->firstWhere('can_send', true);

if ($channel) {
    $api->sendMessage(
        GronosyncMessage::make()->contactExternalId((string) $customer->id)->channelId($channel['id'])->text('Привіт!')
    );
}
```

Усі канали організації (`id`, `name`, `type`, `status`) — звідси `channelId()` для контакту, що ще не писав:

```php
$mail = collect($api->getChannels())->firstWhere('type', 'mail');
```

## Отримання повідомлень (вхідні вебхуки)

GronoSync може сповіщати ваш додаток через HTTP POST, коли в організації відбуваються певні події. Це дозволяє реалізувати двосторонню інтеграцію: ваш додаток надсилає нотифікації контактам, а GronoSync повертає події (нові контакти, вхідні повідомлення, чати що потребують уваги, ...) назад до вас.

Це особливо актуально для CRM-систем (1C, WooCommerce, Drupal тощо), яким потрібно реагувати на активність клієнтів.

### Налаштування вебхуку

У [кабінеті GronoSync](https://app.gronosync.com) перейдіть до налаштувань організації → Вебхук. Вкажіть URL і оберіть, на які події підписатись. `secret` генерується при першому збереженні вебхуку (і доступний будь-коли через дію "перегенерувати secret").

### Події

| Подія | Коли |
|---|---|
| `contact.created` | Контакт звернувся вперше (месенджер, чат-віджет, форма, лист) або ви написали новому контакту через `to()`. Не приходить для контактів, створених `upsertContact()`, імпортом або простим відкриттям сторінки з віджетом |
| `chat.message.received` | Контакт надіслав нове повідомлення |
| `chat.message.sent` | Ваша сторона написала контакту: менеджер, AI-асистент, API (включно з повідомленнями, надісланими через `to()`) або системне повідомлення (напр. вітальне). Внутрішні нотатки й журнал чату не надсилаються |
| `chat.manager_needed` | AI-асистент передав чат менеджеру-людині |
| `chat.closed` | Менеджер закрив чат |
| `contact.updated` | Змінено email, телефон, ім'я чи прізвище контакту (зокрема через `upsertContact()`) |

### Структура payload

GronoSync надсилає `POST`-запит з JSON-тілом:

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
        "content": "Привіт, мені потрібна допомога з замовленням.",
        "internal_type": null,
        "channel": {"id": "550e8400-e29b-41d4-a716-446655440000", "name": "Telegram Bot", "type": "telegram"},
        "created_at": "2024-06-01T10:00:00.000000Z",
        "updated_at": "2024-06-01T10:00:00.000000Z",
        "member": {"role": "client", "fullname": "Іван Петренко"},
        "files": []
    }
}
```

`source` — хто спричинив подію: `{"type": "extern_api", "token_id": 12, "token_name": "CRM"}` (зміна через API — зокрема цим пакетом), `{"type": "member", "member_id": "...", "external_id": "..."}` (менеджер у кабінеті чи віджеті) або `{"type": "system"}` (месенджери, AI, планувальник). Якщо синхронізуєте контакти в обидва боки, пропускайте події зі своїм `token_id` — інакше `upsertContact()` повертатиметься до вас як `contact.updated` і зміна піде по колу. `token_id` — `id` з відповіді `GET /api/my/organizations/{id}/extern-tokens`.

Структура `data` залежить від `event`:

| Подія | `data` |
|---|---|
| `chat.message.received` / `chat.message.sent` | Повідомлення: `id`, `type`, `creator_type`, `content`, `channel`, `member`, `files`, `reply_to`, `created_at`. Повідомлення, надіслане з реклами Meta (Facebook / Instagram / WhatsApp), має ще `referral`: `source`, `ad_id`, `post_id`, `title`, `body`, `url`, `ref`, `click_id` (лише непорожні ключі) |
| `contact.created` / `contact.updated` | Контакт: `id`, `name`, `lastname`, `email`, `phone`, `locale`, `timezone`, `extra`, `external_id`, ID у месенджерах, `created_via` (як з'явився контакт: `messenger`, `widget`, `form`, `mail`, `extern_api`, `import`; `null` для старіших контактів), `created_channel_id`, … Контакт, що прийшов з реклами Meta, має `ad_referral` (перший рекламний дотик, ті самі ключі, що й `referral`, плюс `channel_id`, `received_at`), інакше `null` |
| `chat.manager_needed` / `chat.closed` | `{"chat_id": "...", "contact": {"id", "name", "lastname", "email", "phone"}}` |

### Верифікація запиту

Кожен запит містить заголовок `X-Webhook-Secret` із secret вашого вебхука (спільний секрет, а не підпис). Порівнюйте його за сталий час, щоб переконатися, що запит від GronoSync:

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
        // збережіть secret вебхука у власному конфігу, напр. GRONOSYNC_WEBHOOK_SECRET
        if (!hash_equals((string) config('services.gronosync.webhook_secret'), (string) $request->header('X-Webhook-Secret'))) {
            abort(401);
        }

        $event = $request->input('event');       // напр. "chat.message.received"
        $data  = $request->input('data');
        $orgId = $request->input('organization_id');

        if ($event === 'chat.message.received') {
            // Обробка вхідного повідомлення від контакту
            // наприклад: оновлення CRM, тригер воркфлоу, логування в 1С
        }

        return response()->json(['ok' => true]);
    }
}
```

> **Примітка:** доставка вважається невдалою при мережевій помилці, таймауті (10 секунд) або відповіді не `2xx`; GronoSync робить до 3 спроб з інтервалом 30 секунд. Поверніть відповідь `2xx` якнайшвидше, а важку обробку передайте в чергу. Та сама подія може прийти більше одного разу — кожна спроба несе той самий `event_id`, тож зберігайте оброблені id і пропускайте повтори. `chat.message.sent` приходить і на повідомлення, надіслані через цей пакет: відсіюйте їх за `message_id` з відповіді `sendMessage()` або за `creator_type = extern`.

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
