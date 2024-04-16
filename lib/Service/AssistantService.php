<?php

namespace OCA\Assistant\Service;

use Html2Text\Html2Text;
use OC\SpeechToText\TranscriptionJob;
use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\MetaTask;
use OCA\Assistant\Db\MetaTaskMapper;
use OCA\Assistant\ResponseDefinitions;
use OCA\Assistant\Service\Text2Image\Text2ImageHelperService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\BackgroundJob\IJobList;
use OCP\Common\Exception\NotFoundException;
use OCP\DB\Exception;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;
use OCP\SpeechToText\ISpeechToTextManager;
use OCP\TextProcessing\Exception\TaskFailureException;
use OCP\TextProcessing\FreePromptTaskType;
use OCP\TextProcessing\IManager as ITextProcessingManager;
use OCP\TextProcessing\ITaskType;
use OCP\TextProcessing\Task as TextProcessingTask;
use Parsedown;
use PhpOffice\PhpWord\IOFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use RtfHtmlPhp\Document;
use RtfHtmlPhp\Html\HtmlFormatter;
use RuntimeException;

/**
 * @psalm-import-type AssistantTaskType from ResponseDefinitions
 */
class AssistantService {

	public function __construct(
		private ITextProcessingManager $textProcessingManager,
		private ISpeechToTextManager $speechToTextManager,
		private Text2ImageHelperService $text2ImageHelperService,
		private MetaTaskMapper $metaTaskMapper,
		private LoggerInterface $logger,
		private IRootFolder $storage,
		private IJobList $jobList,
		private IL10N $l10n,
		private ContainerInterface $container,
		private ITempManager $tempManager,
	) {
	}

	/**
	 * @return array<AssistantTaskType>
	 */
	public function getAvailableTaskTypes(): array {
		// text processing and copywriter
		$typeClasses = $this->textProcessingManager->getAvailableTaskTypes();
		$types = [];
		/** @var string $typeClass */
		foreach ($typeClasses as $typeClass) {
			try {
				/** @var ITaskType $object */
				$object = $this->container->get($typeClass);
			} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
				$this->logger->warning('Could not find ' . $typeClass, ['exception' => $e]);
				continue;
			}
			if ($typeClass === FreePromptTaskType::class) {
				$types[] = [
					'id' => $typeClass,
					'name' => $this->l10n->t('Generate text'),
					'description' => $this->l10n->t('Send a request to the Assistant, for example: write a first draft of a presentation, give me suggestions for a presentation, write a draft reply to my colleague.'),
				];
				$types[] = [
					'id' => 'copywriter',
					'name' => $this->l10n->t('Context write'),
					'description' => $this->l10n->t('Writes text in a given style based on the provided source material.'),
				];
			} else {
				$types[] = [
					'id' => $typeClass,
					'name' => $object->getName(),
					'description' => $object->getDescription(),
				];
			}
		}

		// STT
		if ($this->speechToTextManager->hasProviders()) {
			$types[] = [
				'id' => 'speech-to-text',
				'name' => $this->l10n->t('Transcribe'),
				'description' => $this->l10n->t('Transcribe audio to text'),
			];
		}
		// text2image
		if ($this->text2ImageHelperService->hasProviders()) {
			$types[] = [
				'id' => 'OCP\TextToImage\Task',
				'name' => $this->l10n->t('Generate image'),
				'description' => $this->l10n->t('Generate an image from a text'),
			];
		}
		return $types;
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
	 * @param string $userId
	 * @param int $metaTaskId
	 * @return MetaTask|null
	 */
	public function getAssistantTask(string $userId, int $metaTaskId): ?MetaTask {
		try {
			$metaTask = $this->metaTaskMapper->getMetaTask($metaTaskId);
		} catch (DoesNotExistException | MultipleObjectsReturnedException | \OCP\Db\Exception $e) {
			return null;
		}
		if ($metaTask->getUserId() !== $userId) {
			return null;
		}
		// only try to update meta task status for text processing ones
		if ($metaTask->getCategory() !== Application::TASK_CATEGORY_TEXT_GEN) {
			return $metaTask;
		}
		// Check if the text processing task status is up-to-date (if not, update status and output)
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
	 * @param string $userId
	 * @param int $metaTaskId
	 * @return void
	 * @throws Exception
	 */
	public function deleteAssistantTask(string $userId, int $metaTaskId): void {
		$metaTask = $this->getAssistantTask($userId, $metaTaskId);
		if ($metaTask !== null) {
			$this->cancelOcpTaskOfMetaTask($userId, $metaTask);
			$this->metaTaskMapper->delete($metaTask);
		}
	}

	/**
	 * @param string $userId
	 * @param int $metaTaskId
	 * @return void
	 * @throws Exception
	 */
	public function cancelAssistantTask(string $userId, int $metaTaskId): void {
		$metaTask = $this->getAssistantTask($userId, $metaTaskId);
		if ($metaTask !== null) {
			// deal with underlying tasks
			if ($metaTask->getStatus() === Application::STATUS_META_TASK_SCHEDULED) {
				$this->cancelOcpTaskOfMetaTask($userId, $metaTask);
			}

			$metaTask->setStatus(Application::STATUS_META_TASK_FAILED);
			$metaTask->setOutput($this->l10n->t('Canceled by user'));
			$this->metaTaskMapper->update($metaTask);
		}
	}

	private function cancelOcpTaskOfMetaTask(string $userId, MetaTask $metaTask): void {
		if ($metaTask->getCategory() === Application::TASK_CATEGORY_TEXT_GEN) {
			try {
				$ocpTask = $this->textProcessingManager->getTask($metaTask->getOcpTaskId());
				$this->textProcessingManager->deleteTask($ocpTask);
			} catch (NotFoundException $e) {
			}
		} elseif ($metaTask->getCategory() === Application::TASK_CATEGORY_TEXT_TO_IMAGE) {
			$this->text2ImageHelperService->cancelGeneration($metaTask->getOutput(), $userId);
		} elseif ($metaTask->getCategory() === Application::TASK_CATEGORY_SPEECH_TO_TEXT) {
			// TODO implement task canceling in stt manager
			$fileId = $metaTask->getOcpTaskId();
			$files = $this->storage->getById($fileId);
			if (count($files) < 1) {
				return;
			}
			$file = array_shift($files);
			if (!$file instanceof File) {
				return;
			}
			$owner = $file->getOwner();
			if ($owner === null) {
				return;
			}
			$ownerId = $owner->getUID();
			$jobArguments = [
				'fileId' => $fileId,
				'owner' => $ownerId,
				'userId' => $userId,
				'appId' => Application::APP_ID,
			];
			if ($this->jobList->has(TranscriptionJob::class, $jobArguments)) {
				$this->jobList->remove(TranscriptionJob::class, $jobArguments);
			}
		}
	}

	/**
	 * @param string $type
	 * @param array $inputs
	 * @param string $appId
	 * @param string $userId
	 * @param string $identifier
	 * @return TextProcessingTask
	 */
	private function createTextProcessingTask(string $type, array $inputs, string $appId, string $userId, string $identifier): TextProcessingTask {
		$inputs = $this->sanitizeInputs($type, $inputs);
		switch ($type) {
			case 'copywriter':
				{
					// Format the input prompt
					$input = $this->formattedCopywriterPrompt($inputs['writingStyle'], $inputs['sourceMaterial']);
					$task = new TextProcessingTask(FreePromptTaskType::class, $input, $appId, $userId, $identifier);
					break;
				}
			case 'OCA\\ContextChat\\TextProcessing\\ContextChatTaskType':
				{
					$input = json_encode($inputs);
					if ($input === false) {
						throw new \Exception('Invalid inputs for ContextChatTaskType');
					}

					$task = new TextProcessingTask($type, $input, $appId, $userId, $identifier);
					break;
				}
			default:
				{
					$input = $inputs['prompt'];
					$task = new TextProcessingTask($type, $input, $appId, $userId, $identifier);
					break;
				}
		}
		return $task;
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
	 * @throws TaskFailureException
	 */
	public function runTextProcessingTask(string $type, array $inputs, string $appId, string $userId, string $identifier): MetaTask {
		$task = $this->createTextProcessingTask($type, $inputs, $appId, $userId, $identifier);
		$this->textProcessingManager->runTask($task);

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
		$task = $this->createTextProcessingTask($type, $inputs, $appId, $userId, $identifier);
		$this->textProcessingManager->scheduleTask($task);

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
		$task = $this->createTextProcessingTask($type, $inputs, $appId, $userId, $identifier);
		$this->textProcessingManager->runOrScheduleTask($task);

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
