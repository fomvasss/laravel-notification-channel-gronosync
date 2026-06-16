# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-16

### Added
- `ItsChatsChannel` — Laravel notification channel
- `ItsChatsMessage` — fluent message builder (text, attachments, buttons, options)
- `ItsChatsApi` — HTTP client for `/api/extern/message` and `/api/extern/contacts`
- `ItsChatsServiceProvider` — auto-discovery via `services.itschats` config
- `CouldNotSendNotification` exception with factory methods
