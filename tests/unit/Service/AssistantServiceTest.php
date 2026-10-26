<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\TaskNotificationMapper;
use OCA\Assistant\Service\AssistantService;
use OCA\Assistant\Service\NotificationService;
use OCA\Assistant\Service\PreviewService;
use OCA\Assistant\Service\SystemTagService;
use OCP\DB\Exception as DBException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\Share\IManager as IShareManager;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\Exception\NotFoundException as TaskProcessingNotFoundException;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\ShapeDescriptor;
use OCP\TaskProcessing\Task;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for AssistantService::getTaskOutputFileInfo
 *
 * Covers the authorization and resolution logic delegated to getTaskOutputFile
 * and the shape of the returned info array.
 */
class AssistantServiceTest extends TestCase {

	private const TASK_ID = 42;
	private const FILE_ID = 4242;
	private const USER_ID = 'assistant_service_test_user';

	private ITaskProcessingManager&MockObject $taskProcessingManager;
	private IRootFolder&MockObject $rootFolder;
	private AssistantService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->taskProcessingManager = $this->createMock(ITaskProcessingManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);

		$this->taskProcessingManager->method('getAvailableTaskTypes')
			->willReturn([
				'test:tasktype' => [
					'name' => 'Test task type',
					'description' => 'A test task type',
					'inputShape' => [],
					'optionalInputShape' => [],
					'outputShape' => [
						'result' => new ShapeDescriptor('Result', 'The result file', EShapeType::File),
					],
					'optionalOutputShape' => [],
				],
			]);

		$this->service = new AssistantService(
			$this->taskProcessingManager,
			$this->createMock(TaskNotificationMapper::class),
			$this->createMock(NotificationService::class),
			$this->createMock(PreviewService::class),
			$this->createMock(LoggerInterface::class),
			$this->rootFolder,
			$this->createMock(IL10N::class),
			$this->createMock(ITempManager::class),
			$this->createMock(IConfig::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(IShareManager::class),
			$this->createMock(SystemTagService::class),
		);
	}

	private function getTask(string $userId): Task {
		$task = new Task(
			'test:tasktype',
			[],
			'assistant',
			$userId,
		);
		$task->setId(self::TASK_ID);
		$task->setOutput(['result' => self::FILE_ID]);
		return $task;
	}

	private function getOutputFile(): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('result.txt');
		$file->method('getSize')->willReturn(1024);
		return $file;
	}

	public function testGetTaskOutputFileInfo(): void {
		$this->taskProcessingManager->method('getTask')
			->with(self::TASK_ID)
			->willReturn($this->getTask(self::USER_ID));
		$this->rootFolder->method('getFirstNodeById')
			->with(self::FILE_ID)
			->willReturn($this->getOutputFile());

		$fileInfo = $this->service->getTaskOutputFileInfo(self::USER_ID, self::TASK_ID, self::FILE_ID);

		$this->assertSame([
			'name' => 'result.txt',
			'size' => 1024,
		], $fileInfo);
	}

	public function testGetTaskOutputFileInfoForAppdataFile(): void {
		$this->taskProcessingManager->method('getTask')
			->with(self::TASK_ID)
			->willReturn($this->getTask(self::USER_ID));
		$this->rootFolder->method('getFirstNodeById')
			->with(self::FILE_ID)
			->willReturn(null);
		$this->rootFolder->method('getAppDataDirectoryName')
			->willReturn('assistant');
		$this->rootFolder->method('getFirstNodeByIdInPath')
			->with(self::FILE_ID, '/assistant/')
			->willReturn($this->getOutputFile());

		$fileInfo = $this->service->getTaskOutputFileInfo(self::USER_ID, self::TASK_ID, self::FILE_ID);

		$this->assertSame([
			'name' => 'result.txt',
			'size' => 1024,
		], $fileInfo);
	}

	public function testGetTaskOutputFileInfoWrongUser(): void {
		$this->taskProcessingManager->method('getTask')
			->with(self::TASK_ID)
			->willReturn($this->getTask(self::USER_ID));

		$this->expectException(DBException::class);
		$this->expectExceptionMessage('Unauthorized');

		$this->service->getTaskOutputFileInfo('another_user', self::TASK_ID, self::FILE_ID);
	}

	public function testGetTaskOutputFileInfoUnknownFileId(): void {
		$this->taskProcessingManager->method('getTask')
			->with(self::TASK_ID)
			->willReturn($this->getTask(self::USER_ID));

		$this->expectException(DBException::class);
		$this->expectExceptionMessage('Not found');

		$this->service->getTaskOutputFileInfo(self::USER_ID, self::TASK_ID, 999999999);
	}

	public function testGetTaskOutputFileInfoNotAFileNode(): void {
		$this->taskProcessingManager->method('getTask')
			->with(self::TASK_ID)
			->willReturn($this->getTask(self::USER_ID));
		$this->rootFolder->method('getFirstNodeById')
			->with(self::FILE_ID)
			->willReturn(null);
		$this->rootFolder->method('getAppDataDirectoryName')
			->willReturn('assistant');
		$this->rootFolder->method('getFirstNodeByIdInPath')
			->with(self::FILE_ID, '/assistant/')
			->willReturn(null);

		$this->expectException(TaskProcessingNotFoundException::class);
		$this->expectExceptionMessage('Node is not a file');

		$this->service->getTaskOutputFileInfo(self::USER_ID, self::TASK_ID, self::FILE_ID);
	}
}
