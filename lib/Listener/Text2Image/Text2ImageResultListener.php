<?php

namespace OCA\TPAssistant\Listener\Text2Image;

use OCA\TPassistant\AppInfo\Application;
use OCA\TPAssistant\Db\Text2Image\ImageGenerationMapper;
use OCA\TPAssistant\Service\Text2Image\Text2ImageHelperService;
use OCA\TPAssistant\Service\AssistantService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IImage;
use OCP\TextToImage\Events\AbstractTextToImageEvent;
use OCP\TextToImage\Events\TaskFailedEvent;
use OCP\TextToImage\Events\TaskSuccessfulEvent;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class Text2ImageResultListener implements IEventListener {
	/**
	 * Constructor
	 * @param Text2ImageHelperService $text2ImageService
	 * @param ImageGenerationMapper $imageGenerationMapper
	 * @param LoggerInterface $logger
	 * @param AssistantService $assistantService
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(
		private Text2ImageHelperService $text2ImageService,
		private ImageGenerationMapper $imageGenerationMapper,
		private LoggerInterface $logger,
		private AssistantService $assistantService,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * @param Event $event
	 * @return void
	 */
	public function handle(Event $event): void {
		if (!$event instanceof AbstractTextToImageEvent || $event->getTask()->getAppId() !== Application::APP_ID) {
			return;
		}
		$this->logger->debug("TextToImageEvent received");

		$imageGenId = $event->getTask()->getIdentifier();

		if ($imageGenId === null) {
			$this->logger->warning('Image generation task has no identifier');
			return;
		}

		$link = null; // A link to the image generation page (if the task succeeded)

		if ($event instanceof TaskSuccessfulEvent) {
			$this->logger->debug("TextToImageEvent succeeded");
			/** @var IImage $image */

			$images = $event->getTask()->getOutputImages();

			$this->text2ImageService->storeImages($images, $imageGenId);

			// Generate the link for the notification
			$link = $this->urlGenerator->linkToRouteAbsolute(
				Application::APP_ID . '.Text2Image.showGenerationPage',
				[
					'imageGenId' => $imageGenId,
				]
			);
		}

		if ($event instanceof TaskFailedEvent) {
			$this->logger->warning('Image generation task failed: ' . $imageGenId);
			$this->imageGenerationMapper->setFailed($imageGenId, true);
			
			$this->assistantService->sendNotification($event->getTask());
		}

		// Only send the notification if the user enabled them for this task:
		try {
			if($this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId)->getNotifyReady()) {
				$this->assistantService->sendNotification($event->getTask(), $link);
			}
		} catch (\OCP\Db\Exception | DoesNotExistException | MultipleObjectsReturnedException $e) {
			$this->logger->warning('Could not notify user of a generation (id:' . $imageGenId . ') being ready: ' . $e->getMessage());
		}
	}
}
