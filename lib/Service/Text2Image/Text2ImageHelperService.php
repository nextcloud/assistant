<?php

// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Service\Text2Image;

use DateTime;
use Exception as BaseException;
use GdImage;

use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\TaskMapper;
use OCA\TpAssistant\Db\Text2Image\ImageFileName;
use OCA\TpAssistant\Db\Text2Image\ImageFileNameMapper;
use OCA\TpAssistant\Db\Text2Image\ImageGeneration;
use OCA\TpAssistant\Db\Text2Image\ImageGenerationMapper;
use OCA\TpAssistant\Db\Text2Image\PromptMapper;
use OCA\TpAssistant\Db\Text2Image\StaleGenerationMapper;

use OCA\TpAssistant\Service\AssistantService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\Db\Exception;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IImage;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;
use OCP\TextToImage\Exception\TaskFailureException;
use OCP\TextToImage\IManager;
use OCP\TextToImage\Task;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use RuntimeException;

class Text2ImageHelperService {

	private ?ISimpleFolder $imageDataFolder = null;

	public function __construct(
		private LoggerInterface $logger,
		private IManager $textToImageManager,
		private PromptMapper $promptMapper,
		private ImageGenerationMapper $imageGenerationMapper,
		private ImageFileNameMapper $imageFileNameMapper,
		private StaleGenerationMapper $staleGenerationMapper,
		private IAppData $appData,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private AssistantService $assistantService,
		private TaskMapper $taskMapper,
	) {
	}

	/**
	 * Process a prompt using ImageProcessingProvider and return a link to the generated image(s)
	 *
	 * @param string $prompt
	 * @param int $nResults
	 * @param bool $displayPrompt
	 * @param string $userId
	 * @return array
	 * @throws Exception
	 * @throws PreConditionNotMetException
	 * @throws TaskFailureException ;
	 * @throws RandomException
	 */
	public function processPrompt(string $prompt, int $nResults, bool $displayPrompt, string $userId): array {
		if (!$this->textToImageManager->hasProviders()) {
			$this->logger->error('No text to image processing provider available');
			throw new BaseException($this->l10n->t('No text to image processing provider available'));
		}

		$imageGenId = bin2hex(random_bytes(16));

		// In the exceedingly unlikely case of a collision:
		while ($this->genIdExists($imageGenId)) {
			$imageGenId = bin2hex(random_bytes(16));
		}

		$promptTask = new Task($prompt, Application::APP_ID, $nResults, $userId, $imageGenId);

		$this->textToImageManager->runOrScheduleTask($promptTask);

		$taskExecuted = false;

		$images = [];
		$expCompletionTime = new DateTime('now');

		if ($promptTask->getStatus() === Task::STATUS_SUCCESSFUL || $promptTask->getStatus() === Task::STATUS_FAILED) {
			$taskExecuted = true;
			$images = $promptTask->getOutputImages();
		} else {
			$expCompletionTime = $promptTask->getCompletionExpectedAt() ?? $expCompletionTime;
			$this->logger->info('Task scheduled. Expected completion time: ' . $expCompletionTime->format('Y-m-d H:i:s'));
		}

		// Store the image id to the db:
		$this->imageGenerationMapper->createImageGeneration($imageGenId, $displayPrompt ? $prompt : '', $userId, $expCompletionTime->getTimestamp());

		// Create an assistant meta task for the image generation task:
		// TODO check if we should create a task if userId is null
		$this->taskMapper->createTask(
			$userId,
			['prompt' => $prompt],
			$imageGenId,
			time(),
			$promptTask->getId(),
			Task::class,
			Application::APP_ID,
			$promptTask->getStatus(),
			Application::TASK_CATEGORY_TEXT_TO_IMAGE,
			$promptTask->getIdentifier()
		);

		if ($taskExecuted) {
			$this->storeImages($images, $imageGenId);
		}

		$infoUrl = $this->urlGenerator->linkToRouteAbsolute(
			Application::APP_ID . '.Text2Image.getGenerationInfo',
			[
				'imageGenId' => $imageGenId,
			]
		);

		$referenceUrl = $this->urlGenerator->linkToRouteAbsolute(
			Application::APP_ID . '.Text2Image.showGenerationPage',
			[
				'imageGenId' => $imageGenId,
			]
		);

		// Save the prompt to database
		$this->promptMapper->createPrompt($userId, $prompt);

		return ['url' => $infoUrl, 'reference_url' => $referenceUrl, 'image_gen_id' => $imageGenId, 'prompt' => $prompt];
	}

	/**
	 * Check whether the image generation id exists in the database (stale or otherwise)
	 *
	 * @param string $imageGenId
	 * @return bool
	 * @throws BaseException
	 */
	private function genIdExists(string $imageGenId): bool {
		try {
			$this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId);
			return true;
		} catch (DoesNotExistException $e) {
			// Also check the stale generation table:
			try {
				if ($this->staleGenerationMapper->genIdExists($imageGenId)) {
					return true;
				}
			} catch (Exception | RuntimeException $e) {
				// Ignore
			}
			return false;
		} catch (Exception | MultipleObjectsReturnedException $e) {
			$this->logger->debug('Image request error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			throw new BaseException($this->l10n->t('Image request error'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getPromptHistory(string $userId): array {
		return $this->promptMapper->getPromptsOfUser($userId);
	}

	/**
	 * Save image locally as jpg (to save space)
	 *
	 * @param array<IImage>|null $iImages
	 * @param string $imageGenId
	 * @return void
	 * @throws Exception
	 */
	public function storeImages(?array $iImages, string $imageGenId): void {
		if ($iImages === null || count($iImages) === 0) {
			return;
		}
		try {
			$imageDataFolder = $this->getImageDataFolder();
		} catch (BaseException $e) {
			$this->logger->error('Image save error: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return;
		}

		try {
			$imageGeneration = $this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId);
		} catch (Exception | DoesNotExistException | MultipleObjectsReturnedException $e) {
			$this->logger->error('Image save error: image generation not found in db');
			return;
		}


		$quality = 90;
		$n = 0;

		foreach ($iImages as $iImage) {
			$image = $iImage->resource();

			if (!($image instanceof GdImage)) {
				$this->logger->warning('Image save error: could not retrieve image resource');
				continue;
			}

			ob_start();
			imagejpeg($image, null, $quality);
			$jpegData = ob_get_clean();
			unset($image);

			if ($jpegData === false) {
				continue;
			}

			$fileName = strval($imageGenId) . '_' . strval($n++) . '.jpg';

			try {
				$newFile = $imageDataFolder->newFile($fileName);
				$newFile->putContent($jpegData);
			} catch (NotPermittedException | NotFoundException $e) {
				$this->logger->warning('Image save error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
				continue;
			}

			try {
				$this->imageFileNameMapper->createImageFileName($imageGeneration->getId(), $fileName);
			} catch (Exception $e) {
				$this->logger->warning('Image save error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
				continue;
			}

		}
		$this->imageGenerationMapper->setImagesGenerated($imageGenId, true);

		// For clarity we'll notify the user that the generation is ready in the event listener
	}

	/**
	 * Get imageDataFolder
	 *
	 * @return ISimpleFolder
	 * @throws \Exception
	 */
	public function getImageDataFolder(): ISimpleFolder {
		if ($this->imageDataFolder === null) {
			/** @var ISimpleFolder|null $imageFataFolder */
			try {
				$this->imageDataFolder = $this->appData->getFolder(Application::IMAGE_FOLDER);
			} catch (NotFoundException | RuntimeException $e) {
				$this->logger->debug('Image data folder could not be accessed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
				$this->imageDataFolder = null;
			}

			if ($this->imageDataFolder === null) {
				try {
					$this->imageDataFolder = $this->appData->newFolder(Application::IMAGE_FOLDER);
				} catch (NotPermittedException | RuntimeException $e) {
					$this->logger->debug('Image data folder could not be created: '
						. $e->getMessage(), ['app' => Application::APP_ID]);
					throw new \Exception('Image data folder could not be created: ' . $e->getMessage());
				}
			}
		}
		return $this->imageDataFolder;
	}

	/**
	 * Get image generation info
	 *
	 * @param string $imageGenId
	 * @param string $userId
	 * @param bool $updateTimestamp
	 * @return array
	 * @throws \Exception
	 */
	public function getGenerationInfo(string $imageGenId, string $userId, bool $updateTimestamp = true): array {
		// Check whether the task has completed:
		try {
			/** @var ImageGeneration $imageGeneration */
			$imageGeneration = $this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId);
		} catch (DoesNotExistException $e) {
			try {
				if ($this->staleGenerationMapper->genIdExists($imageGenId)) {
					throw new BaseException('Image generation has been deleted.', Http::STATUS_NOT_FOUND);
				}
			} catch (Exception | RuntimeException $e) {
				// Ignore
			}
			$this->logger->debug('Image request error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			// Set error code to BAD_REQUEST to limit brute force attempts
			throw new BaseException($this->l10n->t('Image generation not found.'), Http::STATUS_BAD_REQUEST);
		} catch (Exception | MultipleObjectsReturnedException $e) {
			$this->logger->debug('Image request error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			throw new BaseException($this->l10n->t('Retrieving the image generation failed.'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$isOwner = ($imageGeneration->getUserId() === $userId);

		if ($imageGeneration->getFailed() === true) {
			throw new BaseException($this->l10n->t('Image generation failed.'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($imageGeneration->getIsGenerated() === false) {
			// The image is being generated.
			// Return the expected completion time as UTC timestamp
			$completionExpectedAt = $imageGeneration->getExpGenTime();
			return ['processing' => $completionExpectedAt];
		}

		// Prevent the image generation from going stale if it's being viewed
		if ($updateTimestamp) {
			try {
				$this->imageGenerationMapper->touchImageGeneration($imageGenId);
			} catch (Exception $e) {
				$this->logger->warning('Image generation timestamp update failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			}
		}

		try {
			if ($isOwner) {
				$fileNameEntities = $this->imageFileNameMapper->getImageFileNamesOfGenerationId($imageGeneration->getId());
			} else {
				$fileNameEntities = $this->imageFileNameMapper->getVisibleImageFileNamesOfGenerationId($imageGeneration->getId());
			}
		} catch (Exception $e) {
			$this->logger->warning('Fetching image filenames from db failed: ' . $e->getMessage());
			throw new BaseException($this->l10n->t('Image file names could not be fetched from database'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$fileIds = [];
		foreach ($fileNameEntities as $fileNameEntity) {

			if ($isOwner) {
				$fileIds[] = ['id' => $fileNameEntity->getId(), 'visible' => !$fileNameEntity->getHidden()];
			} else {
				$fileIds[] = ['id' => $fileNameEntity->getId()];
			}
		}

		return ['files' => $fileIds, 'prompt' => $imageGeneration->getPrompt(), 'image_gen_id' => $imageGenId, 'is_owner' => $isOwner];
	}

	/**
	 * Get image based on imageFileNameId (imageGenId is used to prevent guessing image ids)
	 *
	 * @param string $imageGenId
	 * @param int $imageFileNameId
	 * @return array{image: string, 'content-type': array<string>}
	 * @throws BaseException
	 */
	public function getImage(string $imageGenId, int $imageFileNameId): array {
		try {
			$generationId = $this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId)->getId();
			/** @var ImageFileName $imageFileName */
			$imageFileName = $this->imageFileNameMapper->getImageFileNameOfGenerationId($generationId, $imageFileNameId);
		} catch (Exception | DoesNotExistException | MultipleObjectsReturnedException $e) {
			$this->logger->debug('Image request error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			// Set error code to BAD_REQUEST to limit brute force attempts
			throw new BaseException($this->l10n->t('Image request error'), Http::STATUS_BAD_REQUEST);
		}

		if ($imageFileName === null) {
			throw new BaseException($this->l10n->t('Image file not found in database'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// No need to catch here, since we'd be throwing BaseException anyways:
		$imageDataFolder = $this->getImageDataFolder();

		// Load image from disk
		try {
			$imageFile = $imageDataFolder->getFile($imageFileName->getFileName());
			$imageContent = $imageFile->getContent();

		} catch (NotFoundException $e) {
			$this->logger->debug('Image file reading failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);

			throw new BaseException($this->l10n->t('Image file not found'), Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Return image content and type
		return [
			'image' => $imageContent,
			'content-type' => ['image/jpeg'],
		];
	}

	/**
	 * Cancel image generation
	 *
	 * @param string $imageGenId
	 * @param string $userId
	 * @return void
	 * @throws NotPermittedException
	 */
	public function cancelGeneration(string $imageGenId, string $userId): void {
		try {
			$imageGeneration = $this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId);
		} catch (Exception | DoesNotExistException | MultipleObjectsReturnedException $e) {
			$this->logger->warning('Image generation being deleted not in db: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			$imageGeneration = null;
		}

		if ($imageGeneration) {
			// Make sure the user is associated with the image generation
			if ($imageGeneration->getUserId() !== $userId) {
				$this->logger->warning('User attempted deleting another user\'s image generation!', ['app' => Application::APP_ID]);
				return;
			}

			// Get the generation task if it exists
			try {
				$task = $this->textToImageManager->getUserTasksByApp($userId, Application::APP_ID, $imageGenId);
			} catch (RuntimeException $e) {
				$this->logger->debug('Task cancellation failed or it does not exist: ' . $e->getMessage(), ['app' => Application::APP_ID]);
				$task = [];
			}

			if (count($task) > 0) {
				// Cancel the task
				$this->textToImageManager->deleteTask($task[0]);
			}

			if ($imageGeneration->getIsGenerated()) {
				$imageDataFolder = null;
				try {
					$imageDataFolder = $this->getImageDataFolder();
				} catch (BaseException $e) {
					$this->logger->debug('Error deleting image files associated with a generation: ' . $e->getMessage(), ['app' => Application::APP_ID]);
				}
				if ($imageDataFolder !== null) {
					try {
						$fileNames = $this->imageFileNameMapper->getImageFileNamesOfGenerationId($imageGeneration->getId());
					} catch (BaseException $e) {
						$this->logger->debug('No files to delete could be retrieved: ' . $e->getMessage());
						$fileNames = [];
					}

					foreach ($fileNames as $fileName) {
						try {
							$imageFile = $imageDataFolder->getFile($fileName->getFileName());
							$imageFile->delete();
						} catch (NotFoundException $e) {
							$this->logger->debug('Image deletion error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
						}
					}
				}
			}
		}

		// Remove the image generation from the database:
		try {
			$this->imageGenerationMapper->deleteImageGeneration($imageGenId);
		} catch (Exception $e) {
			$this->logger->warning('Deleting image generation db entry failed: ' . $e->getMessage());
		}

		// Add the generation to the stale generation table:
		try {
			$this->staleGenerationMapper->createStaleGeneration($imageGenId);
		} catch (Exception $e) {
			$this->logger->warning('Adding stale generation to db failed: ' . $e->getMessage());
		}

	}

	/**
	 * Hide/show image files of a generation. UserId must match the assigned user of the image generation.
	 *
	 * @param string $imageGenId
	 * @param array $fileVisStatusArray
	 * @param string $userId
	 * @return void
	 * @throws BaseException
	 */
	public function setVisibilityOfImageFiles(string $imageGenId, array $fileVisStatusArray, string $userId): void {
		try {
			$imageGeneration = $this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId);
		} catch (DoesNotExistException $e) {
			$this->logger->debug('Image request error : ' . $e->getMessage());
			throw new BaseException('Image generation not found; it may have been cleaned up due to not being viewed for a long time.', Http::STATUS_BAD_REQUEST);
		} catch (Exception | MultipleObjectsReturnedException $e) {
			$this->logger->debug('Image request error : ' . $e->getMessage());
			throw new BaseException('Internal server error.', Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($imageGeneration->getUserId() !== $userId) {
			$this->logger->warning('User attempted deleting another user\'s image generation!');
			throw new BaseException('Unauthorized.', Http::STATUS_UNAUTHORIZED);
		}
		/** @var array $fileVisStatus */
		foreach ($fileVisStatusArray as $fileVisStatus) {
			try {
				$this->imageFileNameMapper->setFileNameHidden(intval($fileVisStatus['id']), !((bool) $fileVisStatus['visible']));
			} catch (Exception | DoesNotExistException | MultipleObjectsReturnedException $e) {
				$this->logger->error('Error setting image file visibility: ' . $e->getMessage());
				throw new BaseException('Image file or files not found in database', Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}
	}

	/**
	 * Notify when image generation is ready
	 *
	 * @param string $imageGenId
	 * @param string $userId
	 * @throws Exception
	 */
	public function notifyWhenReady(string $imageGenId, string $userId): void {
		try {
			$imageGeneration = $this->imageGenerationMapper->getImageGenerationOfImageGenId($imageGenId);
		} catch (DoesNotExistException $e) {
			$this->logger->debug('Image request error : ' . $e->getMessage());
			// Check if the generation has been deleted before this notify request was made:
			try {
				if ($this->staleGenerationMapper->genIdExists($imageGenId)) {
					throw new BaseException('Image generation has been deleted.', Http::STATUS_NOT_FOUND);
				}
			} catch (Exception | RuntimeException $e) {
				// Ignore
			}
			// Error out with BAD_REQUEST to limit brute force attempts
			throw new BaseException('Image generation not found; it may have been cleaned up due to not being viewed for a long time.', Http::STATUS_BAD_REQUEST);
		} catch (Exception | MultipleObjectsReturnedException $e) {
			$this->logger->debug('Image request error : ' . $e->getMessage());
			throw new BaseException('Internal server error.', Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($imageGeneration->getUserId() !== $userId) {
			$this->logger->warning('User attempted enabling notifications of another user\'s image generation!');
			throw new BaseException('Unauthorized.', Http::STATUS_UNAUTHORIZED);
		}

		$this->imageGenerationMapper->setNotifyReady($imageGenId, true);

		// Just in case check if the image generation is already ready and, if so, notify the user immediately so that the result is not lost:
		try {
			$tasks = $this->textToImageManager->getUserTasksByApp($userId, Application::APP_ID, $imageGenId);
		} catch (RuntimeException $e) {
			$this->logger->debug('Assistant meta task for the given generation id does not exist or could not be retrieved: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return;
		}

		if (count($tasks) !== 1) {
			$this->logger->debug('Expecting exactly one image generation task per image generation id, but found ' . count($tasks) . ' tasks.', ['app' => Application::APP_ID]);
			return;
		}

		$task = array_pop($tasks);

		if ($task->getStatus() === Task::STATUS_SUCCESSFUL || $task->getStatus() === Task::STATUS_FAILED) {
			// Get the assistant task
			try {
				$assistantTask = $this->taskMapper->getTaskByOcpTaskIdAndCategory($task->getId(), Application::TASK_CATEGORY_TEXT_TO_IMAGE);
			} catch (Exception | DoesNotExistException | MultipleObjectsReturnedException $e) {
				$this->logger->debug('Assistant meta task for the given generation id does not exist or could not be retrieved: ' . $e->getMessage(), ['app' => Application::APP_ID]);
				return;
			}
			$assistantTask->setStatus($task->getStatus());
			// No need to update the output since it's already set
			$assistantTask = $this->taskMapper->update($assistantTask);

			$this->assistantService->sendNotification($assistantTask);
		}
	}
}
