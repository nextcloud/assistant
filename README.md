<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Nextcloud Assistant

This app brings a user interface to use the Nextcloud text processing feature.

It allows users to launch AI tasks, be notified when they finish and see the results.
The assistant also appears in others apps like Text to easily process parts of a document.

More details on the assistant OCS API and frontend integration possibilities in the
[developer doc](https://github.com/nextcloud/assistant/raw/main/docs/developer)

### How to use it

A new right header menu entry appears. Once clicked, the assistant is displayed and you can select and task type and
set the input you want to process.

The task might run immediately or be scheduled depending on the time estimation given by the AI provider.
Once a task is scheduled, it will run as a background job. When it is finished, you will receive a notification
from which the results can be displayed.

Other apps can integrate with the assistant. For example, Text will display an inline button besides every paragraph
to directly select a task type to process this paragraph. Selecting a task this way will open the assistant with the task
being pre-selected and the input text set.

More details and screenshots in the [user doc](https://github.com/nextcloud/assistant/raw/main/docs/user).

## Features

In the assistant, the list of available tasks depends on the available providers installed via other apps.
This means you have complete freedom over which service/software will actually run your AI tasks.

### Text processing

So far, the [Local Large language model](https://github.com/nextcloud/llm2#readme)
and the [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai) apps
include text processing providers to:
* Summarize
* Extract topics
* Generate a headline
* Get an answer from a free prompt
* Reformulate (OpenAi/LocalAi only)
* Context writer: Generate text with a specified style. The style can be described or provided via an example text.
* Chat with AI

### Text to image (Image generation)

Known providers:
* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)
* [Text2Image Stable Diffusion](https://apps.nextcloud.com/apps/text2image_stablediffusion)

### Speech to text (Audio transcription)

Known providers:
* [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai)
* [Local Whisper Speech-To-Text](https://apps.nextcloud.com/apps/stt_whisper)

More details on how to set this up in the [admin docs](https://docs.nextcloud.com/server/latest/admin_manual/ai/index.html)
