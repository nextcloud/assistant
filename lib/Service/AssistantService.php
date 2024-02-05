<?php

namespace OCA\TpAssistant\Service;

require_once __DIR__ . '/../../vendor/autoload.php';

use DateTime;
use OCA\TpAssistant\AppInfo\Application;
use OCA\TpAssistant\Db\MetaTask;
use OCA\TpAssistant\Db\MetaTaskMapper;
use OCA\TpAssistant\Db\Text2Image\ImageGenerationMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Common\Exception\NotFoundException;
use OCP\DB\Exception;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IURLGenerator;
use OCP\Lock\LockedException;
use OCP\Notification\IManager as INotificationManager;
use OCP\PreConditionNotMetException;
use OCP\TextProcessing\FreePromptTaskType;
use OCP\TextProcessing\IManager as ITextProcessingManager;
use OCP\TextProcessing\Task as TextProcessingTask;
use OCP\TextToImage\Task as TextToImageTask;
use Parsedown;
use PhpOffice\PhpWord\IOFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;

class AssistantService {

	public function __construct(
		private INotificationManager   $notificationManager,
		private ITextProcessingManager $textProcessingManager,
		private MetaTaskMapper         $metaTaskMapper,
		private ImageGenerationMapper  $imageGenerationMapper,
		private LoggerInterface        $logger,
		private IRootFolder            $storage,
		private IURLGenerator          $url,
	) {
	}

	/**
	 * Send a success or failure task result notification
	 *
	 * @param MetaTask $task
	 * @param string|null $customTarget optional notification link target
	 * @param string|null $actionLabel optional label for the notification action button
	 * @param string|null $resultPreview
	 * @return void
	 */
	public function sendNotification(MetaTask $task, ?string $customTarget = null, ?string $actionLabel = null, ?string $resultPreview = null): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$params = [
			'appId' => $task->getAppId(),
			'id' => $task->getId(),
			'inputs' => $task->getInputsAsArray(),
			'target' => $customTarget ?? $this->getDefaultTarget($task),
			'actionLabel' => $actionLabel,
			'result' => $resultPreview,
		];
		$params['taskTypeClass'] = $task->getTaskType();
		$params['taskCategory'] = $task->getCategory();

		switch ($task->getCategory()) {
			case Application::TASK_CATEGORY_TEXT_TO_IMAGE:
				{
					$taskSuccessful = $task->getStatus() === TextToImageTask::STATUS_SUCCESSFUL;
					break;
				}
			case Application::TASK_CATEGORY_TEXT_GEN:
				{
					$taskSuccessful = $task->getStatus() === TextProcessingTask::STATUS_SUCCESSFUL;
					break;
				}
			case Application::TASK_CATEGORY_SPEECH_TO_TEXT:
				{
					$taskSuccessful = $task->getStatus() === Application::STT_TASK_SUCCESSFUL;
					break;
				}
			default:
				{
					$taskSuccessful = false;
					break;
				}
		}

		$subject = $taskSuccessful
			? 'success'
			: 'failure';

		$objectType = $customTarget === null
			? 'task'
			: 'task-with-custom-target';

		$notification->setApp(Application::APP_ID)
			->setUser($task->getUserId())
			->setDateTime(new DateTime())
			->setObject($objectType, (string) ($task->getId() ?? 0))
			->setSubject($subject, $params);

		$manager->notify($notification);
	}

	private function getDefaultTarget(MetaTask $task): string {
		$category = $task->getCategory();
		if ($category === Application::TASK_CATEGORY_TEXT_GEN) {
			return $this->url->linkToRouteAbsolute(Application::APP_ID . '.assistant.getTextProcessingTaskResultPage', ['taskId' => $task->getId()]);
		} elseif ($category === Application::TASK_CATEGORY_SPEECH_TO_TEXT) {
			return $this->url->linkToRouteAbsolute(Application::APP_ID . '.SpeechToText.getResultPage', ['metaTaskId' => $task->getId()]);
		} elseif ($category === Application::TASK_CATEGORY_TEXT_TO_IMAGE) {
			$imageGeneration = $this->imageGenerationMapper->getImageGenerationOfImageGenId($task->getIdentifier());
			return $this->url->linkToRouteAbsolute(
				Application::APP_ID . '.Text2Image.showGenerationPage',
				[
					'imageGenId' => $imageGeneration->getImageGenId(),
				]
			);
		}
		return '';
	}

	/**
	 * @param string $writingStyle
	 * @param string $sourceMaterial
	 * @return string
	 */
	private function formattedCopywriterPrompt(string $writingStyle, string $sourceMaterial): string {
		return "You're a professional copywriter tasked with copying an instructed or demonstrated *WRITING STYLE* and writing a text on the provided *SOURCE MATERIAL*. \n*WRITING STYLE*:\n$writingStyle\n\n*SOURCE MATERIAL*:\n\n$sourceMaterial\n\nNow write a text in the same style detailed or demonstrated under *WRITING STYLE* using the *SOURCE MATERIAL* as source of facts and instruction on what to write about. Do not invent any facts or events yourself. Also, use the *WRITING STYLE* as a guide for how to write the text ONLY and not as a source of facts or events.";
	}

	/**
	 * Sanitize inputs for storage based on the input type
	 * @param string $type
	 * @param array $inputs
	 * @return array
	 * @throws \Exception
	 */
	private function sanitizeInputs(string $type, array $inputs): array {
		switch ($type) {
			case 'copywriter':
				{
					// Sanitize the input array based on the allowed keys and making sure all inputs are strings:
					$inputs = array_filter($inputs, function ($value, $key) {
						return in_array($key, ['writingStyle', 'sourceMaterial']) && is_string($value);
					}, ARRAY_FILTER_USE_BOTH);

					if (count($inputs) !== 2) {
						throw new \Exception('Invalid input(s)');
					}
					break;
				}
			default:
				{
					if (!is_string($inputs['prompt']) || count($inputs) !== 1) {
						throw new \Exception('Invalid input(s)');
					}
					break;
				}
		}
		return $inputs;
	}

	/**
	 * @param string $userId
	 * @param int $taskId
	 * @return MetaTask|null
	 */
	public function getTextProcessingTask(string $userId, int $taskId): ?MetaTask {
		try {
			$metaTask = $this->metaTaskMapper->getMetaTask($taskId);
		} catch (DoesNotExistException | MultipleObjectsReturnedException | \OCP\Db\Exception $e) {
			return null;
		}
		if ($metaTask->getUserId() !== $userId) {
			return null;
		}
		// Check if the task status is up-to-date (if not, update status and output)
		try {
			$ocpTask = $this->textProcessingManager->getTask($metaTask->getOcpTaskId());

			if($ocpTask->getStatus() !== $metaTask->getStatus()) {
				$metaTask->setStatus($ocpTask->getStatus());
				$metaTask->setOutput($ocpTask->getOutput());
				$metaTask = $this->metaTaskMapper->update($metaTask);
			}
		} catch (NotFoundException $e) {
			// Ocp task not found, so we can't update the status
			$this->logger->debug('OCP task not found for assistant task ' . $metaTask->getId() . '. Could not update status.');
		} catch (\InvalidArgumentException | \OCP\Db\Exception | RuntimeException $e) {
			// Something else went wrong, so we can't update the status
			$this->logger->warning('Unknown error while trying to retreive an updated status for assistant task: ' . $metaTask->getId() . '.', ['exception' => $e]);
		}

		return $metaTask;
	}

	/**
	 * @param string $type
	 * @param array $inputs
	 * @param string $appId
	 * @param string $userId
	 * @param string $identifier
	 * @return MetaTask
	 * @throws PreConditionNotMetException
	 * @throws Exception
	 */
	public function runTextProcessingTask(string $type, array $inputs, string $appId, string $userId, string $identifier): MetaTask {
		$inputs = $this->sanitizeInputs($type, $inputs);
		switch ($type) {
			case 'copywriter':
				{
					// Format the input prompt
					$input = $this->formattedCopywriterPrompt($inputs['writingStyle'], $inputs['sourceMaterial']);
					$task = new TextProcessingTask(FreePromptTaskType::class, $input, $appId, $userId, $identifier);
					$this->textProcessingManager->runTask($task);
					break;
				}
			default:
				{
					$input = $inputs['prompt'];
					$task = new TextProcessingTask($type, $input, $appId, $userId, $identifier);
					$this->textProcessingManager->runTask($task);
					break;
				}
		}

		return $this->metaTaskMapper->createMetaTask(
			$userId, $inputs, $task->getOutput(), time(), $task->getId(), $type,
			$appId, $task->getStatus(), Application::TASK_CATEGORY_TEXT_GEN, $identifier
		);
	}

	/**
	 * @param string $type
	 * @param array $inputs
	 * @param string $appId
	 * @param string $userId
	 * @param string $identifier
	 * @return MetaTask
	 * @throws Exception
	 * @throws PreConditionNotMetException
	 */
	public function scheduleTextProcessingTask(string $type, array $inputs, string $appId, string $userId, string $identifier): MetaTask {
		$inputs = $this->sanitizeInputs($type, $inputs);
		switch ($type) {
			case 'copywriter':
				{
					// Format the input prompt
					$input = $this->formattedCopywriterPrompt($inputs['writingStyle'], $inputs['sourceMaterial']);
					$task = new TextProcessingTask(FreePromptTaskType::class, $input, $appId, $userId, $identifier);
					$this->textProcessingManager->scheduleTask($task);
					break;
				}
			default:
				{
					$input = $inputs['prompt'];
					$task = new TextProcessingTask($type, $input, $appId, $userId, $identifier);
					$this->textProcessingManager->scheduleTask($task);
					break;
				}
		}

		return $this->metaTaskMapper->createMetaTask(
			$userId, $inputs, $task->getOutput(), time(), $task->getId(), $type,
			$appId, $task->getStatus(), Application::TASK_CATEGORY_TEXT_GEN, $identifier
		);
	}

	/**
	 * @param string $type
	 * @param array<string> $inputs
	 * @param string $appId
	 * @param string $userId
	 * @param string $identifier
	 * @return MetaTask
	 * @throws PreConditionNotMetException
	 * @throws \OCP\Db\Exception
	 * @throws \Exception
	 */
	public function runOrScheduleTextProcessingTask(string $type, array $inputs, string $appId, string $userId, string $identifier): MetaTask {
		$inputs = $this->sanitizeInputs($type, $inputs);
		switch ($type) {
			case 'copywriter':
				{
					// Format the input prompt
					$input = $this->formattedCopywriterPrompt($inputs['writingStyle'], $inputs['sourceMaterial']);
					$task = new TextProcessingTask(FreePromptTaskType::class, $input, $appId, $userId, $identifier);
					$this->textProcessingManager->runOrScheduleTask($task);
					break;
				}
			default:
				{
					$input = $inputs['prompt'];
					$task = new TextProcessingTask($type, $input, $appId, $userId, $identifier);
					$this->textProcessingManager->runOrScheduleTask($task);
					break;
				}
		}

		return $this->metaTaskMapper->createMetaTask(
			$userId, $inputs, $task->getOutput(), time(), $task->getId(), $type,
			$appId, $task->getStatus(), Application::TASK_CATEGORY_TEXT_GEN, $identifier
		);
	}

	/**
	 * Parse text from file (if parsing the file type is supported)
	 * @param string $filePath
	 * @param string $userId
	 * @return string
	 * @throws \Exception
	 */
	public function parseTextFromFile(string $filePath, string $userId): string {

		try {
			$userFolder = $this->storage->getUserFolder($userId);
		} catch (\OC\User\NoUserException | NotPermittedException $e) {
			throw new \Exception('Could not access user storage.');
		}

		try {
			$file = $userFolder->get($filePath);
		} catch (NotFoundException $e) {
			throw new \Exception('File not found.');
		}

		try {
			if ($file instanceof File) {
				$contents = $file->getContent();
			} else {
				throw new \Exception('Provided path does not point to a file.');
			}
		} catch (LockedException | GenericFileException | NotPermittedException $e) {
			throw new \Exception('File could not be accessed.');
		}

		$mimeType = $file->getMimeType();

		switch ($mimeType) {
			default:
			case 'text/plain':
				{
					$text = $contents;

					break;
				}
			case 'text/markdown':
				{
					$parser = new Parsedown();
					$text = $parser->text($contents);
					// Remove HTML tags:
					$text = strip_tags($text);
					break;
				}
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
			case 'application/msword':
			case 'application/rtf':
			case 'application/vnd.oasis.opendocument.text':
				{
					// Store the file in a temp dir and provide a path for the doc parser to use
					$tempFilePath = sys_get_temp_dir() . '/assistant_app/' . uniqid() . '.tmp';
					// Make sure the temp dir exists
					if (!file_exists(dirname($tempFilePath))) {
						mkdir(dirname($tempFilePath), 0600, true);
					}
					file_put_contents($tempFilePath, $contents);

					$text = $this->parseDocument($tempFilePath, $mimeType);

					// Remove the hardlink to the file (delete it):
					unlink($tempFilePath);

					break;
				}
		}
		return $text;
	}

	/**
	 * Parse text from doc/docx/odt/rtf file
	 * @param string $filePath
	 * @param string $mimeType
	 * @return string
	 * @throws \Exception
	 */
	private function parseDocument(string $filePath, string $mimeType): string {
		switch ($mimeType) {
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				{
					$readerType = 'Word2007';
					break;
				}
			case 'application/msword':
				{
					$readerType = 'MsDoc';
					break;
				}
			case 'application/rtf':
				{
					$readerType = 'RTF';
					break;
				}
			case 'application/vnd.oasis.opendocument.text':
				{
					$readerType = 'ODText';
					break;
				}
			default:
				{
					throw new \Exception('Unsupported file mimetype');
				}
		}


		$phpWord = IOFactory::createReader($readerType);
		$phpWord = $phpWord->load($filePath);
		$sections = $phpWord->getSections();
		$outText = '';
		foreach ($sections as $section) {
			$elements = $section->getElements();
			foreach ($elements as $element) {
				$class = get_class($element);
				if (method_exists($element, 'getText')) {
					$outText .= $element->getText() . "\n";
				}
			}
		}

		return $outText;
	}
}
