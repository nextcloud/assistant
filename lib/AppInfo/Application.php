<?php

namespace OCA\TpAssistant\AppInfo;

use OCA\TpAssistant\Capabilities;
use OCA\TpAssistant\Listener\BeforeTemplateRenderedListener;
use OCA\TpAssistant\Listener\FreePrompt\FreePromptReferenceListener;
use OCA\TpAssistant\Listener\SpeechToText\SpeechToTextReferenceListener;
use OCA\TpAssistant\Listener\SpeechToText\SpeechToTextResultListener;
use OCA\TpAssistant\Listener\TaskFailedListener;
use OCA\TpAssistant\Listener\TaskSuccessfulListener;
use OCA\TpAssistant\Listener\Text2Image\Text2ImageReferenceListener;
use OCA\TpAssistant\Listener\Text2Image\Text2ImageResultListener;
use OCA\TpAssistant\Notification\Notifier;
use OCA\TpAssistant\Reference\FreePromptReferenceProvider;
use OCA\TpAssistant\Reference\SpeechToTextReferenceProvider;
use OCA\TpAssistant\Reference\Text2ImageReferenceProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;

use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\SpeechToText\Events\TranscriptionFailedEvent;
use OCP\SpeechToText\Events\TranscriptionSuccessfulEvent;
use OCP\TextProcessing\Events\TaskFailedEvent as TextTaskFailedEvent;
use OCP\TextProcessing\Events\TaskSuccessfulEvent as TextTaskSuccessfulEvent;
use OCP\TextToImage\Events\TaskFailedEvent as TextToImageTaskFailedEvent;
use OCP\TextToImage\Events\TaskSuccessfulEvent as TextToImageTaskSuccessfulEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'assistant';

	public const MAX_STORED_IMAGE_PROMPTS_PER_USER = 5;
	public const MAX_STORED_TEXT_PROMPTS_PER_USER = 5;
	public const DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME = 60 * 60 * 24 * 90; // 90 days
	public const DEFAULT_TEXT_GENERATION_STORAGE_TIME = 60 * 60 * 24 * 90; // 90 days
	public const IMAGE_FOLDER = 'generated_images';
	public const SPEECH_TO_TEXT_REC_FOLDER = 'stt_recordings';

	public const TASK_TYPE_TEXT_GEN = 0;
	public const TASK_TYPE_TEXT_TO_IMAGE = 1;
	public const TASK_TYPE_SPEECH_TO_TEXT = 2;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerCapability(Capabilities::class);

		$context->registerReferenceProvider(Text2ImageReferenceProvider::class);
		$context->registerReferenceProvider(FreePromptReferenceProvider::class);
		$context->registerReferenceProvider(SpeechToTextReferenceProvider::class);

		$context->registerEventListener(RenderReferenceEvent::class, Text2ImageReferenceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, FreePromptReferenceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, SpeechToTextReferenceListener::class);

		$context->registerEventListener(TextToImageTaskSuccessfulEvent::class, Text2ImageResultListener::class);
		$context->registerEventListener(TextToImageTaskFailedEvent::class, Text2ImageResultListener::class);
		$context->registerEventListener(TranscriptionSuccessfulEvent::class, SpeechToTextResultListener::class);
		$context->registerEventListener(TranscriptionFailedEvent::class, SpeechToTextResultListener::class);

		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);

		$context->registerEventListener(TextTaskSuccessfulEvent::class, TaskSuccessfulListener::class);
		$context->registerEventListener(TextTaskFailedEvent::class, TaskFailedListener::class);

		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
	}
}
