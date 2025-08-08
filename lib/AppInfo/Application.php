<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\AppInfo;

use OCA\Assistant\Capabilities;
use OCA\Assistant\Listener\BeforeTemplateRenderedListener;
use OCA\Assistant\Listener\ChattyLLMTaskListener;
use OCA\Assistant\Listener\CSPListener;
use OCA\Assistant\Listener\FileActionTaskFailedListener;
use OCA\Assistant\Listener\FileActionTaskSuccessfulListener;
use OCA\Assistant\Listener\FreePrompt\FreePromptReferenceListener;
use OCA\Assistant\Listener\LoadAdditionalScriptsListener;
use OCA\Assistant\Listener\NewFileMenuTaskFailedListener;
use OCA\Assistant\Listener\NewFileMenuTaskSuccessfulListener;
use OCA\Assistant\Listener\SpeechToText\SpeechToTextReferenceListener;
use OCA\Assistant\Listener\TaskFailedListener;
use OCA\Assistant\Listener\TaskOutputFileReferenceListener;
use OCA\Assistant\Listener\TaskSuccessfulListener;
use OCA\Assistant\Listener\Text2Image\Text2ImageReferenceListener;
use OCA\Assistant\Listener\Text2Image\Text2StickerListener;
use OCA\Assistant\Notification\Notifier;
use OCA\Assistant\Reference\FreePromptReferenceProvider;
use OCA\Assistant\Reference\SpeechToTextReferenceProvider;
use OCA\Assistant\Reference\TaskOutputFileReferenceProvider;
use OCA\Assistant\Reference\Text2ImageReferenceProvider;
use OCA\Assistant\Reference\Text2StickerProvider;
use OCA\Assistant\TaskProcessing\AudioToAudioChatProvider;
use OCA\Assistant\TaskProcessing\ContextAgentAudioInteractionProvider;
use OCA\Assistant\TaskProcessing\TextToStickerProvider;
use OCA\Assistant\TaskProcessing\TextToStickerTaskType;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;

use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'assistant';

	public const ASSISTANT_DATA_FOLDER_NAME = 'Assistant';

	public const CHAT_USER_INSTRUCTIONS = 'This is a conversation in a specific language between the user and you, Nextcloud Assistant. You are a kind, polite and helpful AI that helps the user to the best of its abilities. If you do not understand something, you will ask for clarification. Detect the language that the user is using. Make sure to use the same language in your response. Do not mention the language explicitly.';
	public const CHAT_USER_INSTRUCTIONS_TITLE = 'Above is a chat session in a specific language between the user and you, Nextcloud Assistant. Generate a suitable title summarizing the conversation in the same language. Output only the title in plain text, nothing else.';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		require_once __DIR__ . '/../../vendor/autoload.php';

		$context->registerCapability(Capabilities::class);

		$context->registerReferenceProvider(Text2ImageReferenceProvider::class);
		$context->registerReferenceProvider(Text2StickerProvider::class);
		$context->registerReferenceProvider(FreePromptReferenceProvider::class);
		$context->registerReferenceProvider(SpeechToTextReferenceProvider::class);
		$context->registerReferenceProvider(TaskOutputFileReferenceProvider::class);

		$context->registerEventListener(RenderReferenceEvent::class, Text2ImageReferenceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, Text2StickerListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, FreePromptReferenceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, SpeechToTextReferenceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, TaskOutputFileReferenceListener::class);

		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);

		$context->registerEventListener(TaskSuccessfulEvent::class, TaskSuccessfulListener::class);
		$context->registerEventListener(TaskFailedEvent::class, TaskFailedListener::class);
		$context->registerEventListener(TaskSuccessfulEvent::class, ChattyLLMTaskListener::class);
		$context->registerEventListener(TaskSuccessfulEvent::class, FileActionTaskSuccessfulListener::class);
		$context->registerEventListener(TaskSuccessfulEvent::class, NewFileMenuTaskSuccessfulListener::class);
		$context->registerEventListener(TaskFailedEvent::class, FileActionTaskFailedListener::class);
		$context->registerEventListener(TaskFailedEvent::class, NewFileMenuTaskFailedListener::class);

		$context->registerNotifierService(Notifier::class);

		$context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);

		if (class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToAudioChat')) {
			$context->registerTaskProcessingProvider(AudioToAudioChatProvider::class);
		}
		if (class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentAudioInteraction')) {
			$context->registerTaskProcessingProvider(ContextAgentAudioInteractionProvider::class);
		}
		$context->registerTaskProcessingTaskType(TextToStickerTaskType::class);
		$context->registerTaskProcessingProvider(TextToStickerProvider::class);
	}

	public function boot(IBootContext $context): void {
	}
}
