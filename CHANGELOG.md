# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `ItsChatsChannel` — Laravel notification channel
- `ItsChatsMessage` — fluent message builder (text, attachments, buttons, options)
- `ItsChatsApi` — HTTP client for `/api/extern/message` and `/api/extern/contacts`
- `ItsChatsServiceProvider` — auto-discovery via `services.itschats` config
- `CouldNotSendNotification` exception with factory methods
- `ItsChatsMessage::replyToId(string $id)` and `forwardedFromId(string $id)` — reply/forward a message
- `upsertContact()` and `sendMessage()` responses now include `sid` (contact identity token, e.g. for Telegram continuation links)
