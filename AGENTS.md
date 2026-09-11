<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Assistant agent guide

## What Assistant is

Assistant is the Nextcloud app that provides a unified UI and API for AI-powered task processing. It is the central hub for text generation, image generation, audio transcription, translation, context chat, and more.

Assistant depends on **TaskProcessing providers** registered by other apps to perform AI tasks. Without providers, most features are non-functional.

## Building

```bash
composer install
npm ci
npm run dev     # development build (faster, outputs to js/)
npm run build   # production build (outputs to js/)
```

## Architecture

The app ships custom TaskProcessing task types and providers in `lib/TaskProcessing/` (e.g. audio-to-audio chat, text-to-sticker). Other apps register additional providers that Assistant discovers and exposes through its UI and API.

The frontend is Vue 3, built with Vite into `js/`. Multiple entry points exist for different contexts (top-menu modal, dedicated page, files app integration, reference widgets).

### Cross-app boundaries

- Assistant consumes TaskProcessing providers from any app that registers them (e.g. `llm2`, `integration_openai`, `text2image_stablediffusion`, `stt_whisper`).
- Other apps (e.g. Text) integrate with Assistant by triggering task selection and passing input text.
- Reference providers in `lib/Reference/` supply link previews for task results consumed by the Smart Picker and other contexts.

## Testing

Backend unit tests run via PHPUnit (`composer run test:unit`, config in `tests/phpunit.xml`). Running them locally requires a running Nextcloud test instance.

There are no UI tests. Manual or custom Playwright testing against a running instance is the only option for frontend changes. Flag frontend changes for human review.

Many features depend on TaskProcessing providers registered by other apps. Without providers, the UI shows empty or disabled states. The Nextcloud `testing` app includes some test providers for development.

## Key files

| Area | File(s) |
|------|---------|
| HTTP routes | `appinfo/routes.php` |
| Main UI controller | `lib/Controller/AssistantController.php` |
| REST API controller | `lib/Controller/AssistantApiController.php` |
| ChattyLLM controller | `lib/Controller/ChattyLLMController.php` |
| Config controller | `lib/Controller/ConfigController.php` |
| Custom task types & providers | `lib/TaskProcessing/` |
| Services | `lib/Service/` |
| DB layer | `lib/Db/` |
| Reference providers | `lib/Reference/` |
| Frontend entry points | `src/assistant.js`, `src/assistantPage.js`, `src/adminSettings.js`, `src/personalSettings.js`, `src/filesNewMenu.js` |
| Vue components | `src/components/` |
| OpenAPI spec | `openapi.json` |

## Contributing

### What CI checks

CI runs `composer lint`, `composer cs:check`, `composer psalm`, and `npm run lint` on every PR. Backend unit tests run in CI when a test instance is available. These checks block merging.

### How changes flow

- **Backend controller or route change**: regenerate `openapi.json` with `composer openapi`.
- **Frontend change**: run `npm run build` to produce the production bundle in `js/`.
- **Database schema change**: add a new migration in `lib/Migration/`.
- **New file**: add an SPDX license header.

### Pitfalls

- PHP 8.3 is the floor. Do not use syntax requiring newer PHP versions. The minimum PHP version is determined by the `min-version` of Nextcloud declared in `appinfo/info.xml`.
- Commits require a `Signed-off-by` trailer (DCO). The sign-off name and email must match the commit author.
- Node 24 and npm 11.3+ are required for frontend builds.

### Completion report

Every PR description includes:
- **Intent**: why the change exists, what problem it solves.
- **What changed**: a summary of the diff, focusing on decisions and trade-offs.
- **What was tested**: which scenarios were verified, and how.
- **What was not tested**: gaps in coverage, especially frontend changes or scenarios requiring external providers.
- **What the reviewer should focus on**: areas where the agent is least confident or where design decisions were made.

Reasoning, not narration. The diff shows the what. The description shows the why.

---

## Nextcloud Contribution Policy

All contributions generated or assisted by this agent must fully comply with:

- **[AI Contribution Policy](https://github.com/nextcloud/.github/blob/master/AI_POLICY.md)** - the primary reference for AI-specific rules, covering disclosure, author accountability, communication, security, licensing, code quality, and autonomous agent behavior.
- **[Contribution Guidelines](https://github.com/nextcloud/.github/blob/master/CONTRIBUTING.md)** - covering testing requirements, the Developer Certificate of Origin (DCO), license headers, conventional commits, and translations. These apply in full to all contributions regardless of how they were produced.

### What this agent must always do

- Add an `Assisted-by: AGENT_NAME:MODEL_VERSION` git trailer to every commit containing AI-assisted content.
- Ensure every pull request includes a disclosure of AI tool use in the PR description.
- Produce focused, scoped pull requests that address exactly one concern. Do not touch unrelated files or introduce incidental refactors.
- Verify all dependencies against actual package registries before suggesting them. Do not use hallucinated or unverified package names.
- Write code comments that document the code, never the process that produced it:
  - Comments describe what the code does - method signatures, behavior, and constraints the code itself cannot express (e.g. a non-obvious invariant or workaround).
  - Never add comments that document progress, decisions, or changes (e.g. "changed X to Y", "as requested", "this fixes ...", "previously this did ..."). That belongs in the commit message or PR discussion; in the code it goes stale and becomes misleading.
  - Do not narrate self-explanatory code. If the code is readable without a comment, omit the comment.
  - Keep comments brief - short and simple, matching the comment density of the surrounding code.
- Reuse existing helper functions and utilities instead of re-implementing their logic inline. When fixing a flawed pattern, fix every occurrence of it across the changed code, not only the instance that was pointed out.
- Run permission and access-control checks before the operation they guard, never after it and never only in the UI layer.
- When adding or changing user-facing functionality, wire it up in every context where the affected component is used - the default authenticated view, public share pages, and embedded contexts such as the Smart Picker and reference widgets. When emitting new events, verify that every consumer of the component subscribes to and handles them.
- Explicitly inform the contributor when any action they are about to take, or have taken, would violate the AI Contribution Policy or the Contribution Guidelines. Do not silently proceed. State which rule is at risk and what the contributor should do instead.
- Warn the contributor if a pull request is growing too large. A PR approaching several thousand lines of changed code is a signal that it should be split into smaller, focused PRs. Suggest a logical split before the PR is opened, not after.
- Recommend opening a ticket for discussion before starting implementation whenever a feature or change is sufficiently complex - for example when it touches multiple subsystems, requires architectural decisions, or the right approach is not yet clear. A ticket allows maintainers and the contributor to align on direction before code is written, avoiding wasted effort on a PR that may be rejected or require fundamental rework.

### What this agent must never do

- Open issues, submit pull requests, post review comments, or send security reports autonomously. Every contribution must be reviewed and submitted by a human.
- Add `Signed-off-by` tags to commits. Only the human contributor can certify the Developer Certificate of Origin.
- Generate or submit security reports without independent human verification. Report verified vulnerabilities via [HackerOne](https://hackerone.com/nextcloud), not as GitHub issues.
- Write PR descriptions, review comments, or issue reports on behalf of the contributor. These must be in the contributor's own words.
- Fully automate the resolution of issues labeled [`good first issue`](https://github.com/issues?q=org%3Anextcloud+label%3A%22good+first+issue%22) or similar beginner-friendly labels.
- Submit code that has not been reviewed and cleaned up by the contributor. Dead code, redundant logic, excessive comments, malformed or garbled characters (e.g. `` replacement characters), and unrelated changes must be removed before submission.
