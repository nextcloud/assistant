<?php

namespace OCA\Assistant\Service;

use Couchbase\BaseException;
use DateTime;
use Html2Text\Html2Text;
use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\TaskNotificationMapper;
use OCA\Assistant\ResponseDefinitions;
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
use OCP\TaskProcessing\Task;
use OCP\TaskProcessing\TaskTypes\TextToText;
use Parsedown;
use PhpOffice\PhpWord\IOFactory;
use Psr\Log\LoggerInterface;
use RtfHtmlPhp\Document;
use RtfHtmlPhp\Html\HtmlFormatter;
use RuntimeException;

/**
 * @psalm-import-type AssistantTaskType from ResponseDefinitions
 */
class AssistantService {

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
	 * @param string $taskId
	 * @param string $userId
	 * @throws Exception
	 */
	public function notifyWhenReady(string $taskId, string $userId): void {
		try {
			$task = $this->taskProcessingManager->getTask($taskId);
		} catch (NotFoundException $e) {
			$this->logger->debug('Task request error: ' . $e->getMessage());
			throw new BaseException('Task not found', Http::STATUS_NOT_FOUND);
		} catch (TaskProcessingException $e) {
			$this->logger->debug('Task request error : ' . $e->getMessage());
			throw new BaseException('Internal server error.', Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($task->getUserId() !== $userId) {
			$this->logger->info('A user attempted enabling notifications of another user\'s task');
			throw new BaseException('Unauthorized', Http::STATUS_UNAUTHORIZED);
		}

		// Just in case check if the task is already ready and, if so, notify the user immediately so that the result is not lost:
		if ($task->getStatus() === Task::STATUS_SUCCESSFUL || $task->getStatus() === Task::STATUS_FAILED) {
			$this->notificationService->sendNotification($task);
		} else {
			$this->taskNotificationMapper->createTaskNotification($taskId);
		}
	}

	/**
	 * @return array<AssistantTaskType>
	 */
	public function getAvailableTaskTypes(): array {
		// return [1,2];
		$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
		$types = [];
		$types[] = [
			'name' => 'input list',
			'description' => 'plop',
			'id' => 'core:inputList',
			'inputShape' => [
				'fileList' => [
					'name' => 'Input file list',
					'description' => 'plop',
					'type' => 'ListOfFiles',
				],
				'imageList' => [
					'name' => 'Input image list',
					'description' => 'plop',
					'type' => 'ListOfImages',
				],
				'audioList' => [
					'name' => 'Input audio list',
					'description' => 'plop',
					'type' => 'ListOfAudios',
				],
			],
			'outputShape' => [
				'fileList' => [
					'name' => 'Output file list',
					'description' => 'plop',
					'type' => 'ListOfFiles',
				],
				'imageList' => [
					'name' => 'Output image list',
					'description' => 'plop',
					'type' => 'ListOfImages',
				],
				'image' => [
					'name' => 'Output image',
					'description' => 'plop',
					'type' => 'Image',
				],
			],
		];
		/** @var string $typeId */
		foreach ($availableTaskTypes as $typeId => $taskTypeArray) {
			$taskTypeArray['id'] = $typeId;
			if ($typeId === TextToText::ID) {
				$taskTypeArray['name'] = $this->l10n->t('Generate text');
				$taskTypeArray['description'] = $this->l10n->t('Send a request to the Assistant, for example: write a first draft of a presentation, give me suggestions for a presentation, write a draft reply to my colleague.');
				$types[] = $taskTypeArray;
				/*
				$types[] = [
					'id' => 'copywriter',
					'name' => $this->l10n->t('Context write'),
					'description' => $this->l10n->t('Writes text in a given style based on the provided source material.'),
				];
				*/
				$types[] = [
					'id' => 'chatty-llm',
					'name' => $this->l10n->t('Chat with AI'),
					'description' => $this->l10n->t('Chat with an AI model.'),
					'inputShape' => [],
					'outputShape' => [],
				];
			} else {
				$taskTypeArray['id'] = $typeId;
				$types[] = $taskTypeArray;
			}
		}
		return $types;
	}

	public function getUserTasks(string $userId, ?string $taskTypeId = null): array {
		return $this->taskProcessingManager->getUserTasks($userId, $taskTypeId);
	}

	public function storeInputFile(string $userId, string $tempFileLocation, ?string $extension = null): array {
		$assistantDataFolder = $this->getAssistantDataFolder($userId);

		$filename = (new DateTime())->format('Y-m-d_H:i:s');
		if ($extension !== null) {
			$filename .= '.' . $extension;
		}
		$inputFile = $assistantDataFolder->newFile($filename, fopen($tempFileLocation, 'rb'));

		return [
			'fileId' => $inputFile->getId(),
			'filePath' => $inputFile->getPath(),
		];
	}

	public function getAssistantDataFolder(string $userId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$dataFolderName = $this->config->getAppValue(Application::APP_ID, 'data_folder', Application::ASSISTANT_DATA_FOLDER_NAME) ?: Application::ASSISTANT_DATA_FOLDER_NAME;
		if ($userFolder->nodeExists($dataFolderName)) {
			$dataFolderNode = $userFolder->get($dataFolderName);
			if ($dataFolderNode instanceof Folder && $dataFolderNode->isCreatable()) {
				return $dataFolderNode;
			}
		}
		// it does not exist or is not a folder or does not have write permissions: we create one
		$dataFolder = $this->createAssistantDataFolder($userId);
		$dataFolderName = $dataFolder->getName();
		$this->config->setAppValue(Application::APP_ID, 'data_folder', $dataFolderName);
		return $dataFolder;
	}

	private function createAssistantDataFolder(string $userId, int $try = 3): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$folderPath = Application::ASSISTANT_DATA_FOLDER_NAME . ' ' . strval(4 - $try);

		if ($userFolder->nodeExists($folderPath)) {
			if ($try === 0) {
				// give up
				throw new RuntimeException('Could not create the assistant data folder');
			}
			return $this->createAssistantDataFolder($userId, $try - 1);
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

	public function getTaskOutputFile(string $userId, int $ocpTaskId, int $fileId): File {
		$task = $this->taskProcessingManager->getTask($ocpTaskId);
		if ($task->getUserId() !== $userId) {
			$this->logger->info('A user attempted getting a file of another user\'s task');
			throw new BaseException('Unauthorized', Http::STATUS_UNAUTHORIZED);
		}
		// TODO uncomment this, useful for testing with fake task types
		//		$taskFileIds = $this->extractFileIdsFromTask($task);
		//		if (!in_array($fileId, $taskFileIds, true)) {
		//			throw new BaseException('Not found', Http::STATUS_NOT_FOUND);
		//		}


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
				throw new BaseException('Imossible to copy output file, a directory with this name already exists', Http::STATUS_UNAUTHORIZED);
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
	 * @throws BaseException
	 * @throws LockedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getOutputFilePreviewFile(string $userId, int $taskId, int $fileId): ?array {
		$taskOutputFile = $this->getTaskOutputFile($userId, $taskId, $fileId);
		if ($taskOutputFile === null) {
			return null;
		}
		$realMime = mime_content_type($taskOutputFile->fopen('rb'));
		return $this->previewService->getFilePreviewFile($taskOutputFile, 100, 100, $realMime ?: null);
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
	 * TODO change the logic of task submission for contextwrite and contextchat
	 * either compute the input directly in the frontend or make the frontend call an assistant endpoint to reach this
	 *
	 * @param string $type
	 * @param array $inputs
	 * @param string $appId
	 * @param string $userId
	 * @param string $identifier
	 * @return Task
	 */
	private function createTextProcessingTask(string $type, array $inputs, string $appId, string $userId, string $identifier): Task {
		$inputs = $this->sanitizeInputs($type, $inputs);
		switch ($type) {
			case 'copywriter':
				{
					// Format the input prompt
					$input = $this->formattedCopywriterPrompt($inputs['writingStyle'], $inputs['sourceMaterial']);
					$task = new Task(TextToText::class, ['input' => $input], $appId, $userId, $identifier);
					break;
				}
			case 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType':
				{
					$input = json_encode($inputs);
					if ($input === false) {
						throw new \Exception('Invalid inputs for ContextChatTaskType');
					}

					$task = new Task($type, $inputs, $appId, $userId, $identifier);
					break;
				}
			default:
				{
					$task = new Task($type, $inputs, $appId, $userId, $identifier);
					break;
				}
		}
		return $task;
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
