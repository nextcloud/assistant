# Blassistant, a Blablador Nextcloud Assistant fork

This Assistant is a blablador themed version of the nextcloud assistant.
[Blablador](https://helmholtz-blablador.fz-juelich.de/) is an experimental large language model by Helmholtz AI Jülich.

Get in touch with us at [blablador@fz-juelich.de](mailto:blablador@fz-juelich.de.).

---

# Nextcloud Assistant

This app brings a user interface to use the Nextcloud text processing feature.

It allows users to launch text processing tasks, be notified when they finish and see the results.
The assistant also appears in others apps like Text to easily process parts of a document.

### How to use it

A new right header  menu entry appears. Once clicked, the assistant is displayed and you can select and task type and
set the input text you want to process.

Once a task is scheduled, it will run as a background job. When it is finished, you will receive a notification
from which the results can be displayed.

Other apps can integrate with the assistant. For example, Text will display an inline button besides every paragraph
to directly select a task type to process this paragraph. Selecting a task this way will open the assistant with the task
being pre-selected and the input text set.

### Text processing providers

In the assistant, the list of available tasks depends on the available providers installed via other apps.
This means you have complete freedom over which service/software will actually run your text processing tasks.
So far, the [Large language model](https://github.com/nextcloud/llm#readme)
and the [OpenAi/LocalAI integration](https://apps.nextcloud.com/apps/integration_openai) apps
include text processing providers to:
* Summarize
* Extract topics
* Generate a headline
* Get an answer from a free prompt
* Reformulate (OpenAi/LocalAi only)
