<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests;

use OCA\Assistant\Service\AttachmentService;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\IManager;
use OCP\TaskProcessing\ShapeDescriptor;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class AttachmentServiceTest extends \PHPUnit\Framework\TestCase {

	private const TASK_TYPE_ID = 'core:text2text:summary';

	private IManager&MockObject $taskProcessingManager;

	private function buildService(): AttachmentService {
		$this->taskProcessingManager = $this->createMock(IManager::class);

		$appConfig = $this->createMock(IAppConfig::class);
		// no admin overrides, always hand back the default
		$appConfig->method('getValueInt')->willReturnCallback(
			static fn (string $app, string $key, int $default = 0, bool $lazy = false): int => $default,
		);

		return new AttachmentService(
			$this->taskProcessingManager,
			$appConfig,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function mockFile(string $mimeType, int $size): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getMimeType')->willReturn($mimeType);
		$file->method('getSize')->willReturn($size);
		return $file;
	}

	/**
	 * @param ?ShapeDescriptor $shape the input_attachments descriptor the provider advertises, if any
	 */
	private function mockAvailableTaskTypes(?ShapeDescriptor $shape): void {
		$this->taskProcessingManager->method('getAvailableTaskTypes')->willReturn([
			self::TASK_TYPE_ID => [
				'optionalInputShape' => $shape === null ? [] : ['input_attachments' => $shape],
			],
		]);
	}

	public function testProviderWithoutAttachmentSlotIsNotSupported(): void {
		$service = $this->buildService();
		$this->mockAvailableTaskTypes(null);
		self::assertFalse($service->providerSupportsInputAttachments(self::TASK_TYPE_ID));
	}

	public function testProviderAdvertisingListOfFilesIsSupported(): void {
		$service = $this->buildService();
		$this->mockAvailableTaskTypes(new ShapeDescriptor('Attachments', 'Files', EShapeType::ListOfFiles));
		self::assertTrue($service->providerSupportsInputAttachments(self::TASK_TYPE_ID));
	}

	public function testSlotWithTheWrongShapeTypeIsNotSupported(): void {
		$service = $this->buildService();
		// a provider using the same key for something else must not switch the feature on
		$this->mockAvailableTaskTypes(new ShapeDescriptor('Attachments', 'Files', EShapeType::Text));
		self::assertFalse($service->providerSupportsInputAttachments(self::TASK_TYPE_ID));
	}

	public function testUnknownTaskTypeIsNotSupported(): void {
		$service = $this->buildService();
		$this->mockAvailableTaskTypes(new ShapeDescriptor('Attachments', 'Files', EShapeType::ListOfFiles));
		self::assertFalse($service->providerSupportsInputAttachments('core:text2text:chat'));
	}

	public function testManagerFailureDoesNotBubbleUp(): void {
		$service = $this->buildService();
		// this runs while rendering templates, a throw here would break the page
		$this->taskProcessingManager->method('getAvailableTaskTypes')
			->willThrowException(new \RuntimeException('boom'));
		self::assertFalse($service->providerSupportsInputAttachments(self::TASK_TYPE_ID));
	}

	/**
	 * @dataProvider attachableFileDataProvider
	 */
	public function testIsAttachableFile(string $mimeType, int $size, bool $expected): void {
		$service = $this->buildService();
		self::assertSame($expected, $service->isAttachableFile($this->mockFile($mimeType, $size)));
	}

	public function attachableFileDataProvider(): array {
		return [
			// PDFs always go to the provider, our parser is the unreliable part
			'small pdf' => ['application/pdf', 12_000, true],
			'large pdf' => ['application/pdf', 20_000_000, true],
			'oversized pdf' => ['application/pdf', 80_000_000, false],
			// text extracts fine and cheaply until it no longer fits in a Text input
			'small markdown' => ['text/markdown', 10_000, false],
			'large markdown' => ['text/markdown', 1_000_000, true],
			'small plain text' => ['text/plain', 1_000, false],
			'large plain text' => ['text/plain', 500_000, true],
			'large csv' => ['text/csv', 400_000, true],
			// office formats are rejected provider side, they must keep using text extraction
			'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 5_000_000, false],
			'odt' => ['application/vnd.oasis.opendocument.text', 900_000, false],
			'rtf' => ['text/rtf', 900_000, false],
			'image' => ['image/jpeg', 900_000, false],
		];
	}

	public function testShouldAttachFileRequiresBothProviderSupportAndAnEligibleFile(): void {
		$service = $this->buildService();
		$this->mockAvailableTaskTypes(new ShapeDescriptor('Attachments', 'Files', EShapeType::ListOfFiles));

		self::assertTrue($service->shouldAttachFile($this->mockFile('application/pdf', 12_000), self::TASK_TYPE_ID));
		self::assertFalse($service->shouldAttachFile($this->mockFile('text/markdown', 10_000), self::TASK_TYPE_ID));
	}

	public function testShouldNotAttachFileWhenTheProviderCannotTakeAttachments(): void {
		$service = $this->buildService();
		$this->mockAvailableTaskTypes(null);
		self::assertFalse($service->shouldAttachFile($this->mockFile('application/pdf', 12_000), self::TASK_TYPE_ID));
	}

	public function testAttachableMimeTypesAreOnlyAdvertisedWhenTheProviderSupportsThem(): void {
		$service = $this->buildService();
		$this->mockAvailableTaskTypes(new ShapeDescriptor('Attachments', 'Files', EShapeType::ListOfFiles));
		$mimeTypes = $service->getAttachableMimeTypes(self::TASK_TYPE_ID);

		self::assertContains('application/pdf', $mimeTypes);
		// the file action compares mime types exactly, wildcards would never match
		foreach ($mimeTypes as $mimeType) {
			self::assertStringNotContainsString('*', $mimeType);
		}
	}

	public function testNoAttachableMimeTypesWithoutProviderSupport(): void {
		$service = $this->buildService();
		$this->mockAvailableTaskTypes(null);
		self::assertSame([], $service->getAttachableMimeTypes(self::TASK_TYPE_ID));
	}
}
