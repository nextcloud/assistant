<?php

namespace OCA\Assistant\Service;

use DateTime;
use Html2Text\Html2Text;
use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\TaskNotificationMapper;
use OCA\Assistant\ResponseDefinitions;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\Lock\LockedException;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\Exception\Exception as TaskProcessingException;
use OCP\TaskProcessing\Exception\NotFoundException;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\ShapeDescriptor;
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\ContextWrite;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\TaskProcessing\TaskTypes\TextToText;
use OCP\TaskProcessing\TaskTypes\TextToTextChat;
use OCP\TaskProcessing\TaskTypes\TextToTextHeadline;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use OCP\TaskProcessing\TaskTypes\TextToTextTopics;
use Parsedown;
use PhpOffice\PhpWord\IOFactory;
use Psr\Log\LoggerInterface;
use RtfHtmlPhp\Document;
use RtfHtmlPhp\Html\HtmlFormatter;
use RuntimeException;

/**
 * @psalm-import-type AssistantTaskProcessingTaskType from ResponseDefinitions
 */
class AssistantService {

	private const DEBUG = false;

	private const TASK_TYPE_PRIORITIES = [
		'chatty-llm' => 1,
		TextToText::ID => 2,
		// TODO set the correct task type ID (the textProcessing one or the future taskProcessing one)
		'ContextChat' => 3,
		AudioToText::ID => 4,
		// TODO translate: 5 (translate is not migrated to taskProcessing yet)
		ContextWrite::ID => 6,
		TextToImage::ID => 7,
		TextToTextSummary::ID => 8,
		TextToTextHeadline::ID => 9,
		TextToTextTopics::ID => 10,
	];

	public function __construct(
		private ITaskProcessingManager $taskProcessingManager,
		private TaskNotificationMapper $taskNotificationMapper,
		private NotificationService $notificationService,
		private PreviewService $previewService,
		private LoggerInterface $logger,
		private IRootFolder $rootFolder,
		private IL10N $l10n,
		private ITempManager $tempManager,
		private IConfig $config,
		private IShareManager $shareManager,
	) {
	}

	/**
	 * Notify when image generation is ready
	 *
	 * @param int $taskId
	 * @param string $userId
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function notifyWhenReady(int $taskId, string $userId): void {
		try {
			$task = $this->taskProcessingManager->getTask($taskId);
		} catch (NotFoundException $e) {
			$this->logger->debug('Task request error: ' . $e->getMessage());
			throw new Exception('Task not found', Http::STATUS_NOT_FOUND);
		} catch (TaskProcessingException $e) {
			$this->logger->debug('Task request error : ' . $e->getMessage());
			throw new Exception('Internal server error.', Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($task->getUserId() !== $userId) {
			$this->logger->info('A user attempted enabling notifications of another user\'s task');
			throw new Exception('Unauthorized', Http::STATUS_UNAUTHORIZED);
		}

		// Just in case check if the task is already ready and, if so, notify the user immediately so that the result is not lost:
		if ($task->getStatus() === Task::STATUS_SUCCESSFUL || $task->getStatus() === Task::STATUS_FAILED) {
			$this->notificationService->sendNotification($task);
		} else {
			$this->taskNotificationMapper->createTaskNotification($taskId);
		}
	}

	/**
	 * @return array<AssistantTaskProcessingTaskType>
	 */
	public function getAvailableTaskTypes(): array {
		$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
		/** @var array<AssistantTaskProcessingTaskType> $types */
		$types = [];
		if (self::DEBUG) {
			$types[] = [
				'name' => 'input list',
				'description' => 'plop',
				'id' => 'core:inputList',
				'priority' => 0,
				'inputShape' => [
					'fileList' => new ShapeDescriptor(
						'Input file list',
						'plop',
						EShapeType::ListOfFiles,
					),
					'imageList' => new ShapeDescriptor(
						'Input image list',
						'plop',
						EShapeType::ListOfImages,
					),
					'audioList' => new ShapeDescriptor(
						'Input audio list',
						'plop',
						EShapeType::ListOfAudios,
					),
				],
				'outputShape' => [
					'fileList' => new ShapeDescriptor(
						'Output file list',
						'plop',
						EShapeType::ListOfFiles,
					),
					'imageList' => new ShapeDescriptor(
						'Output image list',
						'plop',
						EShapeType::ListOfImages,
					),
					'image' => new ShapeDescriptor(
						'Output image',
						'plop',
						EShapeType::Image,
					),
				],
				'optionalInputShape' => [],
				'optionalOutputShape' => [],
			];
		}
		/** @var string $typeId */
		foreach ($availableTaskTypes as $typeId => $taskTypeArray) {
			// skip chat task type (not directly useful to the end user)
//			if ($typeId === TextToTextChat::ID) {
//				continue;
//			}
			$taskTypeArray['id'] = $typeId;
			$taskTypeArray['priority'] = self::TASK_TYPE_PRIORITIES[$typeId] ?? 1000;

			if ($typeId === TextToText::ID) {
				$taskTypeArray['name'] = $this->l10n->t('Generate text');
				$taskTypeArray['description'] = $this->l10n->t('Send a request to the Assistant, for example: write a first draft of a presentation, give me suggestions for a presentation, write a draft reply to my colleague.');
				// add the chattyUI virtual taks type
				$types[] = [
					'id' => 'chatty-llm',
					'name' => $this->l10n->t('Chat with AI'),
					'description' => $this->l10n->t('Chat with an AI model.'),
					'inputShape' => [],
					'outputShape' => [],
					'optionalInputShape' => [],
					'optionalOutputShape' => [],
					'priority' => self::TASK_TYPE_PRIORITIES['chatty-llm'] ?? 1000,
				];
			}
			$types[] = $taskTypeArray;
		}
		return $types;
	}

	public function getUserTasks(string $userId, ?string $taskTypeId = null): array {
		return $this->taskProcessingManager->getUserTasks($userId, $taskTypeId);
	}

	public function storeInputFile(string $userId, string $tempFileLocation, ?string $filename = null): array {
		$assistantDataFolder = $this->getAssistantDataFolder($userId);

		$date = (new DateTime())->format('Y-m-d_H:i:s');
		$targetFileName = $filename === null ? $date : ($date . ' ' . $filename);
		$targetFile = $assistantDataFolder->newFile($targetFileName, fopen($tempFileLocation, 'rb'));

		return [
			'fileId' => $targetFile->getId(),
			'filePath' => $targetFile->getPath(),
		];
	}

	public function getAssistantDataFolder(string $userId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$dataFolderName = $this->config->getUserValue($userId, Application::APP_ID, 'data_folder', Application::ASSISTANT_DATA_FOLDER_NAME) ?: Application::ASSISTANT_DATA_FOLDER_NAME;
		if ($userFolder->nodeExists($dataFolderName)) {
			$dataFolderNode = $userFolder->get($dataFolderName);
			if ($dataFolderNode instanceof Folder && $dataFolderNode->isCreatable()) {
				return $dataFolderNode;
			}
		}
		// it does not exist or is not a folder or does not have write permissions: we create one
		$dataFolder = $this->createAssistantDataFolder($userId);
		$dataFolderName = $dataFolder->getName();
		$this->config->setUserValue($userId, Application::APP_ID, 'data_folder', $dataFolderName);
		return $dataFolder;
	}

	private function createAssistantDataFolder(string $userId, int $try = 0): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		if ($try === 0) {
			$folderPath = Application::ASSISTANT_DATA_FOLDER_NAME;
		} else {
			$folderPath = Application::ASSISTANT_DATA_FOLDER_NAME . ' ' . $try;
		}

		if ($userFolder->nodeExists($folderPath)) {
			if ($try > 3) {
				// give up
				throw new RuntimeException('Could not create the assistant data folder');
			}
			return $this->createAssistantDataFolder($userId, $try + 1);
		}

		return $userFolder->newFolder($folderPath);
	}

	public function getUserFile(string $userId, int $fileId): ?File {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$file = $userFolder->getFirstNodeById($fileId);
		if ($file instanceof File) {
			$owner = $file->getOwner();
			if ($owner !== null && $owner->getUID() === $userId) {
				return $file;
			}
		}
		return null;
	}

	public function getUserFileInfo(string $userId, int $fileId): ?array {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$file = $userFolder->getFirstNodeById($fileId);
		if ($file instanceof File) {
			$owner = $file->getOwner();
			return [
				'name' => $file->getName(),
				'path' => $file->getPath(),
				'owner' => $owner->getUID(),
				'size' => $file->getSize(),
			];
		}
		return null;
	}

	/**
	 * @param string $userId
	 * @param int $ocpTaskId
	 * @param int $fileId
	 * @return File
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws TaskProcessingException
	 */
	public function getTaskOutputFile(string $userId, int $ocpTaskId, int $fileId): File {
		$task = $this->taskProcessingManager->getTask($ocpTaskId);
		if ($task->getUserId() !== $userId) {
			$this->logger->info('A user attempted getting a file of another user\'s task');
			throw new Exception('Unauthorized', Http::STATUS_UNAUTHORIZED);
		}
		// avoiding this is useful for testing with fake task types
		if (!self::DEBUG) {
			$taskFileIds = $this->extractFileIdsFromTask($task);
			if (!in_array($fileId, $taskFileIds, true)) {
				throw new Exception('Not found', Http::STATUS_NOT_FOUND);
			}
		}

		$node = $this->rootFolder->getFirstNodeById($fileId);
		if ($node === null) {
			$node = $this->rootFolder->getFirstNodeByIdInPath($fileId, '/' . $this->rootFolder->getAppDataDirectoryName() . '/');
			if (!$node instanceof File) {
				throw new \OCP\TaskProcessing\Exception\NotFoundException('Node is not a file');
			}
		} elseif (!$node instanceof File) {
			throw new \OCP\TaskProcessing\Exception\NotFoundException('Node is not a file');
		}
		return $node;
	}

	public function shareOutputFile(string $userId, int $ocpTaskId, int $fileId): string {
		$taskOutputFile = $this->getTaskOutputFile($userId, $ocpTaskId, $fileId);
		$assistantDataFolder = $this->getAssistantDataFolder($userId);
		$targetFileName = $this->getTargetFileName($taskOutputFile);
		if ($assistantDataFolder->nodeExists($targetFileName)) {
			$existingTarget = $assistantDataFolder->get($targetFileName);
			if ($existingTarget instanceof File) {
				if ($existingTarget->getSize() === $taskOutputFile->getSize()) {
					$fileCopy = $existingTarget;
				} else {
					$fileCopy = $assistantDataFolder->newFile($targetFileName, $taskOutputFile->fopen('rb'));
				}
			} else {
				throw new Exception('Impossible to copy output file, a directory with this name already exists', Http::STATUS_UNAUTHORIZED);
			}
		} else {
			$fileCopy = $assistantDataFolder->newFile($targetFileName, $taskOutputFile->fopen('rb'));
		}
		$share = $this->shareManager->newShare();
		$share->setNode($fileCopy);
		$share->setPermissions(Constants::PERMISSION_READ);
		$share->setShareType(IShare::TYPE_LINK);
		$share->setSharedBy($userId);
		$share->setLabel('Assistant share');
		$share = $this->shareManager->createShare($share);
		$shareToken = $share->getToken();

		return $shareToken;
	}

	private function getTargetFileName(File $file): string {
		$mime = mime_content_type($file->fopen('rb'));
		$name = $file->getName();
		$ext = '';
		if ($mime === 'image/png') {
			$ext = '.png';
		} elseif ($mime === 'image/jpeg') {
			$ext = '.jpg';
		}

		if (str_ends_with($name, $ext)) {
			return $name;
		}
		return $name . $ext;
	}

	private function extractFileIdsFromTask(Task $task): array {
		$ids = [];
		$taskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
		if (!isset($taskTypes[$task->getTaskTypeId()])) {
			throw new \OCP\TaskProcessing\Exception\NotFoundException('Could not find task type');
		}
		$taskType = $taskTypes[$task->getTaskTypeId()];
		foreach ($taskType['inputShape'] + $taskType['optionalInputShape'] as $key => $descriptor) {
			if (in_array(EShapeType::getScalarType($descriptor->getShapeType()), [EShapeType::File, EShapeType::Image, EShapeType::Audio, EShapeType::Video], true)) {
				/** @var int|list<int> $inputSlot */
				$inputSlot = $task->getInput()[$key];
				if (is_array($inputSlot)) {
					$ids += $inputSlot;
				} else {
					$ids[] = $inputSlot;
				}
			}
		}
		if ($task->getOutput() !== null) {
			foreach ($taskType['outputShape'] + $taskType['optionalOutputShape'] as $key => $descriptor) {
				if (in_array(EShapeType::getScalarType($descriptor->getShapeType()), [EShapeType::File, EShapeType::Image, EShapeType::Audio, EShapeType::Video], true)) {
					/** @var int|list<int> $outputSlot */
					$outputSlot = $task->getOutput()[$key];
					if (is_array($outputSlot)) {
						$ids += $outputSlot;
					} else {
						$ids[] = $outputSlot;
					}
				}
			}
		}
		return array_values($ids);
	}

	/**
	 * @param string $userId
	 * @param int $taskId
	 * @param int $fileId
	 * @return array|null
	 * @throws Exception
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws TaskProcessingException
	 */
	public function getOutputFilePreviewFile(string $userId, int $taskId, int $fileId): ?array {
		$taskOutputFile = $this->getTaskOutputFile($userId, $taskId, $fileId);
		$realMime = mime_content_type($taskOutputFile->fopen('rb'));
		return $this->previewService->getFilePreviewFile($taskOutputFile, 100, 100, $realMime ?: null);
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
			case 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType':
				{
					if ((count($inputs) !== 1 && count($inputs) !== 4)
						|| !isset($inputs['prompt'])
						|| !is_string($inputs['prompt'])
					) {
						throw new \Exception('Invalid input(s)');
					}

					if (count($inputs) === 4) {
						if (!isset($inputs['scopeType']) || !is_string($inputs['scopeType'])
							|| !isset($inputs['scopeList']) || !is_array($inputs['scopeList'])
							|| !isset($inputs['scopeListMeta']) || !is_array($inputs['scopeListMeta'])) {
							throw new \Exception('Invalid input(s)');
						}
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
	 * Parse text from file (if parsing the file type is supported)
	 * @param string $filePath
	 * @param string $userId
	 * @return string
	 * @throws \Exception
	 */
	public function parseTextFromFile(string $filePath, string $userId): string {

		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
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
				$fileContent = $file->getContent();
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
					$text = $fileContent;

					break;
				}
			case 'text/markdown':
				{
					$parser = new Parsedown();
					$text = $parser->text($fileContent);
					// Remove HTML tags:
					$text = strip_tags($text);
					break;
				}
			case 'text/rtf':
				{
					$text = $this->parseRtfDocument($fileContent);
					break;
				}
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
			case 'application/msword':
			case 'application/vnd.oasis.opendocument.text':
				{
					$tempFilePath = $this->tempManager->getTemporaryFile();
					file_put_contents($tempFilePath, $fileContent);
					$text = $this->parseDocument($tempFilePath, $mimeType);
					$this->tempManager->clean();
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
				// RTF parsing is buggy in phpoffice
				/*
				case 'text/rtf':
					{
						$readerType = 'RTF';
						break;
					}
				*/
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
				if (method_exists($element, 'getText')) {
					$outText .= $element->getText() . "\n";
				}
			}
		}

		return $outText;
	}

	private function parseRtfDocument(string $content): string {
		// henck/rtf-to-html
		$document = new Document($content);
		$formatter = new HtmlFormatter('UTF-8');
		$htmlText = $formatter->Format($document);

		// html2text/html2text
		$html = new Html2Text($htmlText);
		return $html->getText();
	}
}
