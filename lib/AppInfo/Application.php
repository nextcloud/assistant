<?php

namespace OCA\Assistant\AppInfo;

use OCA\Assistant\Capabilities;
use OCA\Assistant\Listener\BeforeTemplateRenderedListener;
use OCA\Assistant\Listener\FreePrompt\FreePromptReferenceListener;
use OCA\Assistant\Listener\SpeechToText\SpeechToTextReferenceListener;
use OCA\Assistant\Listener\SpeechToText\SpeechToTextResultListener;
use OCA\Assistant\Listener\TaskFailedListener;
use OCA\Assistant\Listener\TaskSuccessfulListener;
use OCA\Assistant\Listener\Text2Image\Text2ImageReferenceListener;
use OCA\Assistant\Listener\Text2Image\Text2ImageResultListener;
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
use OCP\SpeechToText\Events\TranscriptionFailedEvent;
use OCP\SpeechToText\Events\TranscriptionSuccessfulEvent;
use OCP\TextProcessing\Events\TaskFailedEvent as TextTaskFailedEvent;
use OCP\TextProcessing\Events\TaskSuccessfulEvent as TextTaskSuccessfulEvent;
use OCP\TextProcessing\Task as OCPTextprocessingTask;
use OCP\TextToImage\Events\TaskFailedEvent as TextToImageTaskFailedEvent;
use OCP\TextToImage\Events\TaskSuccessfulEvent as TextToImageTaskSuccessfulEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'assistant';
	public const DEFAULT_ASSISTANT_TASK_IDLE_TIME = 60 * 60 * 24 * 14; // 14 days

	public const MAX_STORED_IMAGE_PROMPTS_PER_USER = 5;
	public const MAX_STORED_TEXT_PROMPTS_PER_USER = 5;
	public const DEFAULT_MAX_IMAGE_GENERATION_IDLE_TIME = 60 * 60 * 24 * 90; // 90 days
	public const DEFAULT_TEXT_GENERATION_STORAGE_TIME = 60 * 60 * 24 * 90; // 90 days
	public const IMAGE_FOLDER = 'generated_images';
	public const SPEECH_TO_TEXT_REC_FOLDER = 'stt_recordings';

	public const STT_TASK_SCHEDULED = 0;
	public const STT_TASK_SUCCESSFUL = 1;
	public const STT_TASK_FAILED = -1;

	public const STATUS_META_TASK_UNKNOWN = 0;
	public const STATUS_META_TASK_SCHEDULED = 1;
	public const STATUS_META_TASK_RUNNING = 2;
	public const STATUS_META_TASK_SUCCESSFUL = 3;
	public const STATUS_META_TASK_FAILED = 4;
	public const TP_STATUS_TO_META_STATUS = [
		OCPTextprocessingTask::STATUS_UNKNOWN => self::STATUS_META_TASK_UNKNOWN,
		OCPTextprocessingTask::STATUS_SCHEDULED => self::STATUS_META_TASK_SCHEDULED,
		OCPTextprocessingTask::STATUS_RUNNING => self::STATUS_META_TASK_RUNNING,
		OCPTextprocessingTask::STATUS_SUCCESSFUL => self::STATUS_META_TASK_SUCCESSFUL,
		OCPTextprocessingTask::STATUS_FAILED => self::STATUS_META_TASK_FAILED,
	];

	public const TASK_CATEGORY_TEXT_GEN = 0;
	public const TASK_CATEGORY_TEXT_TO_IMAGE = 1;
	public const TASK_CATEGORY_SPEECH_TO_TEXT = 2;

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
