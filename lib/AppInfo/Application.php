<?php

namespace OCA\TPAssistant\AppInfo;

use OCA\TPAssistant\Listener\BeforeTemplateRenderedListener;
use OCA\TPAssistant\Listener\TaskFailedListener;
use OCA\TPAssistant\Listener\TaskSuccessfulListener;
use OCA\TPAssistant\Listener\Text2Image\Text2ImageReferenceListener;
use OCA\TPAssistant\Reference\Text2ImageReferenceProvider;
use OCA\TPAssistant\Reference\FreePromptReferenceProvider;
use OCA\TPAssistant\Listener\FreePrompt\FreePromptReferenceListener;
use OCA\TPAssistant\Listener\FreePrompt\FreePromptResultListener;
use OCA\TPAssistant\Notification\Notifier;
use OCA\TPAssistant\Listener\Text2Image\Text2ImageResultListener;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
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

	public const TASK_TYPE_TEXT_GEN = 0;
	public const TASK_TYPE_TEXT_TO_IMAGE = 1;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerReferenceProvider(Text2ImageReferenceProvider::class);
		$context->registerReferenceProvider(FreePromptReferenceProvider::class);

		$context->registerEventListener(RenderReferenceEvent::class, Text2ImageReferenceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, FreePromptReferenceListener::class);
		$context->registerEventListener(TextToImageTaskSuccessfulEvent::class, Text2ImageResultListener::class);
		$context->registerEventListener(TextToImageTaskFailedEvent::class, Text2ImageResultListener::class);

		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);

		$context->registerEventListener(TextTaskSuccessfulEvent::class, TaskSuccessfulListener::class);
		$context->registerEventListener(TextTaskFailedEvent::class, TaskFailedListener::class);

		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
	}
}

