<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Assistant agent guide

This is the starting point for working with the **`assistant`** Nextcloud app.

## Table of contents

1. [What Assistant is](#1-what-assistant-is)
2. [Architecture](#2-architecture)
3. [Building the app](#3-building-the-app)
4. [Testing](#4-testing)
5. [UI entry points](#5-ui-entry-points)
6. [Key files](#6-key-files)
7. [Contributing conventions](#7-contributing-conventions)

## 1. What Assistant is

Assistant is the Nextcloud app that provides a unified UI and API for AI-powered task processing. It acts as the central hub for users to interact with AI features such as text generation, image generation, audio transcription, translation, context chat, and more.

Assistant relies heavily on **TaskProcessing providers** registered by other apps to actually perform AI tasks. Without providers, most features are non-functional. See [Testing](#4-testing) for details on setting up providers for development.

## 2. Architecture

The app follows the standard Nextcloud app structure:

- **`appinfo/`**: App metadata (`info.xml`) and HTTP route definitions (`routes.php`).
- **`lib/`**: PHP backend.
  - `lib/Controller/`: HTTP controllers. `AssistantController` and `AssistantApiController` serve the main UI and REST API. `ChattyLLMController` handles the conversational interface. `ConfigController` manages admin/personal settings. `AgentSkillsApiController` and `AssignmentsApiController` expose agent-related endpoints.
  - `lib/TaskProcessing/`: Custom TaskProcessing task types and providers shipped by the app itself (e.g. audio-to-audio chat, text-to-sticker, image-to-text translation).
  - `lib/Service/`: Business logic services.
  - `lib/Settings/`: Admin and personal settings page registration (`Admin.php`, `Personal.php`).
  - `lib/Db/`: Database entities and mappers.
  - `lib/Listener/` and `lib/Event/`: Event listeners and event classes.
  - `lib/BackgroundJob/`: Background job definitions.
  - `lib/Migration/`: Database schema migrations.
  - `lib/Reference/`: Reference provider for link previews (e.g. text generation results, speech-to-text results).
  - `lib/Notification/`: Notification handling.
- **`src/`**: Vue 3 frontend (built with Vite into `js/`).
  - Entry points: `src/assistant.js` (top-menu modal), `src/assistantPage.js` (dedicated page), `src/adminSettings.js`, `src/personalSettings.js`, `src/filesNewMenu.js` (files app "new" menu), `src/imageGenerationReference.js`, `src/speechToTextReference.js`, `src/textGenerationReference.js`, `src/taskOutputFileReference.js`, `src/stickerGeneration.js`.
  - `src/components/`: Vue components including `ChattyLLM/` (conversational UI), `ContextChat/`, `Translate/`, `FilesNewMenu/`, settings forms, task list, and the main assistant form.
  - `src/views/`: Top-level views (`AssistantPage.vue`, custom picker elements for image/text results, file reference widget).
- **`templates/`**: PHP templates for server-rendered pages.
- **`tests/`**: PHPUnit test suite.

## 3. Building the app

Install PHP and Node.js dependencies, then build:

```bash
# PHP backend
composer install

# Frontend
npm ci
npm run build          # production build (outputs to js/)
npm run watch          # dev build with file watcher
npm run dev            # one-shot dev build
```

### Lint and static analysis

```bash
composer lint          # PHP syntax check
composer cs:check      # php-cs-fixer dry-run
composer cs:fix        # php-cs-fixer auto-fix
composer psalm         # static analysis
composer openapi       # regenerate OpenAPI specs

npm run lint           # eslint
npm run stylelint      # stylelint
```

## 4. Testing

### Backend tests

There are **no UI tests** in this repository. Backend tests run in CI automatically.

Running them locally **requires a running Nextcloud test instance**. The exact setup depends on how your test Nextcloud is deployed (Docker, bare metal, nextcloud-docker-dev, etc.). Once the instance is available:

```bash
composer run test:unit
```

The PHPUnit configuration lives in `tests/phpunit.xml`.

### TaskProcessing providers

Many Assistant features depend on TaskProcessing providers registered by other apps. Without providers, the assistant UI will show empty or disabled states.

Nextcloud ships a `testing` app that includes some test providers useful during development. It is up to the developer or agent to deploy additional providers as needed for the features being tested.

For a list of apps that implement TaskProcessing providers, see the Nextcloud admin documentation:
https://docs.nextcloud.com/server/latest/admin_manual/ai/app_assistant.html#related-apps

### UI testing with Playwright

There is no built-in Playwright test suite, but the app can be tested manually or with custom Playwright scripts against a running Nextcloud instance. See [UI entry points](#5-ui-entry-points) below for the pages and interactions to target.

## 5. UI entry points

These are the places where the Assistant surfaces in the Nextcloud UI:

1. **Dedicated page**: `/index.php/apps/assistant` — the full assistant interface.
2. **Top menu**: a menu entry in the Nextcloud header opens the assistant in a modal.
3. **File actions** in the Files app (`/index.php/apps/files`): context menu entries for `text/markdown` and audio files trigger assistant tasks.
4. **"New file" menu** in the Files app: a "Generate image using AI" entry.
5. **Settings pages**: both admin and personal settings have an "Assistant" category at `/index.php/settings/admin/ai` and `/index.php/settings/user/ai`.

## 6. Key files

| Area | File(s) |
|------|---------|
| HTTP routes | `appinfo/routes.php` |
| Main UI controller | `lib/Controller/AssistantController.php` |
| REST API controller | `lib/Controller/AssistantApiController.php` |
| ChattyLLM controller | `lib/Controller/ChattyLLMController.php` |
| Config controller | `lib/Controller/ConfigController.php` |
| Custom task types & providers | `lib/TaskProcessing/` |
| Admin settings | `lib/Settings/Admin.php` |
| Personal settings | `lib/Settings/Personal.php`, `lib/Settings/PersonalSection.php` |
| Services | `lib/Service/` |
| DB layer | `lib/Db/` |
| Migrations | `lib/Migration/` |
| Reference providers | `lib/Reference/` |
| Frontend entry points | `src/assistant.js`, `src/assistantPage.js`, `src/adminSettings.js`, `src/personalSettings.js`, `src/filesNewMenu.js` |
| Main Vue components | `src/components/` |
| Top-level views | `src/views/` |
| App metadata | `appinfo/info.xml` |
| PHPUnit config | `tests/phpunit.xml` |
| OpenAPI spec | `openapi.json` |

## 7. Contributing conventions

- **Sign off every commit (DCO)**: `git commit -s`. The sign-off name/email must match the commit author.
- **PHP floor 8.3**: do not use syntax requiring newer PHP versions. The minimum PHP version is determined by the `min-version` of Nextcloud declared in `appinfo/info.xml` (currently 35). The supported PHP versions for a given Nextcloud release are listed in the "PHP Runtime" row of the requirements table at `https://docs.nextcloud.com/server/NC_VERSION/admin_manual/installation/system_requirements.html`, where `NC_VERSION` can be a major version number (e.g. `33`, `34`) or `latest` for the current development version.
- **Node 24, npm 11.3+** for frontend builds.
- Before pushing: run `composer cs:fix && composer psalm`; if you touched the frontend, `npm run lint && npm run build`; if you touched controllers/routes, `composer openapi`. If a test Nextcloud instance is available, also run `composer run test:unit` inside it.
- New files need an SPDX license header.
