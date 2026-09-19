# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `GronosyncChannel` — Laravel notification channel
- `GronosyncMessage` — fluent message builder (text, attachments, buttons, options)
- `GronosyncApi` — HTTP client for `/api/extern/message` and `/api/extern/contacts`
- `GronosyncServiceProvider` — auto-discovery via `services.gronosync` config
- `CouldNotSendNotification` exception with factory methods
- `CouldNotSendNotification::getStatusCode()`, `getErrorCode()` and `getResponse()` — the API error response (e.g. `code: chat_blocked`), no longer truncated in the exception message
- `GronosyncMessage::replyToId(string $id)` and `forwardedFromId(string $id)` — reply/forward a message
- `upsertContact()` and `sendMessage()` responses now include `sid` (contact identity token, e.g. for Telegram continuation links)
- Organization webhook — subscribe to `contact.created`, `chat.message.received`, `chat.message.sent`, `chat.manager_needed`, `chat.closed`, `contact.updated` events; requests carry the webhook secret in an `X-Webhook-Secret` header (see README § Receiving messages)
- Webhook `chat.message.sent` event — messages from your side to a contact (manager, AI assistant, API, system messages)
- Webhook payload `event_id` — the same across delivery retries, use it to skip repeats

### Changed
- Renamed to `fomvasss/laravel-notification-channel-gronosync` after the service rebrand (ItsChats → GronoSync): namespace `NotificationChannels\Gronosync`, classes `GronosyncChannel` / `GronosyncMessage` / `GronosyncApi` / `GronosyncServiceProvider`, config `services.gronosync`, env `GRONOSYNC_URL` / `GRONOSYNC_TOKEN`, notification methods `toGronosync()` / `routeNotificationForGronosync()`, `NotificationFailed` channel name `Gronosync`. Default API URL: `https://api.gronosync.com`
- Authentication now uses an organization API token sent as `Authorization: Bearer {token}`, created under organization settings → Extern API tokens. Previously used the Widget Manager widget's token via an `X-Widget-Token` header — regenerate your token and update `GRONOSYNC_TOKEN` accordingly
- Sending a message to a contact whose chat is blocked in GronoSync now fails with `422` and `code: chat_blocked`; previously it was delivered
- `to()` is resolved by the channel type: phone number for SMS and e-chat WhatsApp channels (previously stored as email), any phone format accepted; widget/form channels reject `to` with `422`
- Organization webhook deliveries are retried (up to 3 attempts, 30 s apart) on non-`2xx` responses and timeouts (10 s), not only on network errors

- Contact payloads (webhooks `contact.created` / `contact.updated`) include `created_via` and `created_channel_id` — how and through which channel the contact appeared
- `upsertContact()` no longer overwrites an existing contact's `source`

### Fixed
- `GronosyncChannel` now depends on the `Illuminate\Contracts\Events\Dispatcher` contract, so it resolves when events are faked in tests
