<?php

namespace OCA\Assistant\AppInfo;

use OCA\Assistant\Capabilities;
use OCA\Assistant\Listener\BeforeTemplateRenderedListener;
use OCA\Assistant\Listener\FreePrompt\FreePromptReferenceListener;
use OCA\Assistant\Listener\SpeechToText\SpeechToTextReferenceListener;
use OCA\Assistant\Listener\TaskFailedListener;
use OCA\Assistant\Listener\TaskSuccessfulListener;
use OCA\Assistant\Listener\Text2Image\Text2ImageReferenceListener;
use OCA\Assistant\Notification\Notifier;
use OCA\Assistant\Reference\FreePromptReferenceProvider;
use OCA\Assistant\Reference\SpeechToTextReferenceProvider;
use OCA\Assistant\Reference\Text2ImageReferenceProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;

use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'assistant';
	public const DEFAULT_ASSISTANT_TASK_IDLE_TIME = 60 * 60 * 24 * 14; // 14 days

	public const MAX_STORED_IMAGE_PROMPTS_PER_USER = 5;
	public const MAX_STORED_TEXT_PROMPTS_PER_USER = 5;
	public const DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME = 60 * 60 * 24 * 90; // 90 days
	public const DEFAULT_TEXT_GENERATION_STORAGE_TIME = 60 * 60 * 24 * 90; // 90 days
	public const IMAGE_FOLDER = 'generated_images';
	public const SPEECH_TO_TEXT_REC_FOLDER = 'stt_recordings';
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
		$context->registerReferenceProvider(FreePromptReferenceProvider::class);
		$context->registerReferenceProvider(SpeechToTextReferenceProvider::class);

		$context->registerEventListener(RenderReferenceEvent::class, Text2ImageReferenceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, FreePromptReferenceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, SpeechToTextReferenceListener::class);

		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);

		$context->registerEventListener(TaskSuccessfulEvent::class, TaskSuccessfulListener::class);
		$context->registerEventListener(TaskFailedEvent::class, TaskFailedListener::class);

		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
	}
}
