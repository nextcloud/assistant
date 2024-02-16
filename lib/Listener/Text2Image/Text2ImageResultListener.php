<?php

namespace OCA\TpAssistant\Listener\Text2Image;

use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\MetaTaskMapper;
use OCA\TpAssistant\Db\Text2Image\ImageGenerationMapper;
use OCA\TpAssistant\Service\NotificationService;
use OCA\TpAssistant\Service\Text2Image\Text2ImageHelperService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TextToImage\Events\AbstractTextToImageEvent;
use OCP\TextToImage\Events\TaskFailedEvent;
use OCP\TextToImage\Events\TaskSuccessfulEvent;
use OCP\TextToImage\Task;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class Text2ImageResultListener implements IEventListener {

	public function __construct(
		private Text2ImageHelperService $text2ImageService,
		private ImageGenerationMapper $imageGenerationMapper,
		private LoggerInterface $logger,
		private NotificationService $notificationService,
		private MetaTaskMapper $metaTaskMapper,
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
		$this->logger->debug('TextToImageEvent received');

		$imageGenId = $event->getTask()->getIdentifier();

		if ($imageGenId === null) {
			$this->logger->warning('Image generation task has no identifier');
			return;
		}

		$assistantTask = $this->metaTaskMapper->getMetaTaskByOcpTaskIdAndCategory($event->getTask()->getId(), Application::TASK_CATEGORY_TEXT_TO_IMAGE);

		if ($event instanceof TaskSuccessfulEvent) {
			$this->logger->debug('TextToImageEvent succeeded');

			$images = $event->getTask()->getOutputImages();

			$this->text2ImageService->storeImages($images, $imageGenId);

			$assistantTask->setStatus(Application::STATUS_META_TASK_SUCCESSFUL);
			$assistantTask = $this->metaTaskMapper->update($assistantTask);
		}

		if ($event instanceof TaskFailedEvent) {
			$this->logger->warning('Image generation task failed: ' . $imageGenId);
			$this->imageGenerationMapper->setFailed($imageGenId, true);

			// Update the assistant meta task status:
			$assistantTask->setStatus(Application::STATUS_META_TASK_FAILED);
			$assistantTask->setOutput($event->getErrorMessage());
			$assistantTask = $this->metaTaskMapper->update($assistantTask);

			$this->notificationService->sendNotification($assistantTask);
		}

		// Only send the notification if the user enabled them for this task:
		try {
			$imageGeneration = $this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId);
			if ($imageGeneration->getNotifyReady()) {
				$this->notificationService->sendNotification($assistantTask);
			}
		} catch (\OCP\Db\Exception | DoesNotExistException | MultipleObjectsReturnedException $e) {
			$this->logger->warning('Could not notify user of a generation (id:' . $imageGenId . ') being ready: ' . $e->getMessage());
		}
	}
}
