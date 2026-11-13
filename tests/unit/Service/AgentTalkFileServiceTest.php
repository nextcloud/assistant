<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Service\AgentTalkFileService;
use OCP\Comments\IComment;
use OCP\Files\File;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AgentTalkFileServiceTest extends TestCase {

	private AgentTalkFileService $service;
	private IComment&MockObject $comment;

	protected function setUp(): void {
		parent::setUp();

		$this->service = new AgentTalkFileService(
			$this->createMock(\OCP\Files\IRootFolder::class),
			$this->createMock(LoggerInterface::class),
		);
		$this->comment = $this->createMock(IComment::class);
	}

	private function makeFileShareComment(string $message): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getMessage')->willReturn($message);
		return $comment;
	}

	public function testParsePlainMessageReturnsNull(): void {
		$comment = $this->makeFileShareComment('Hello agent, what is the weather?');
		$this->assertNull($this->service->parseFileShareMessage($comment));
	}

	public function testParseInvalidJsonReturnsNull(): void {
		$comment = $this->makeFileShareComment('{not json');
		$this->assertNull($this->service->parseFileShareMessage($comment));
	}

	public function testParseOtherSystemMessageReturnsNull(): void {
		$comment = $this->makeFileShareComment(json_encode([
			'message' => 'thread_created',
			'parameters' => ['thread' => 42, 'title' => 'My thread'],
		]));
		$this->assertNull($this->service->parseFileShareMessage($comment));
	}

	public function testParseFileSharedWithoutFileOrShareReturnsNull(): void {
		$comment = $this->makeFileShareComment(json_encode([
			'message' => 'file_shared',
			'parameters' => ['metaData' => []],
		]));
		$this->assertNull($this->service->parseFileShareMessage($comment));
	}

	public function testParseFileSharedWithFileId(): void {
		$comment = $this->makeFileShareComment(json_encode([
			'message' => 'file_shared',
			'parameters' => [
				'fileId' => '123',
				'metaData' => [
					'messageType' => 'voice-message',
					'mimeType' => 'audio/wav',
					'caption' => 'What do you hear?',
				],
			],
		]));
		$this->assertEquals([
			'fileId' => 123,
			'shareId' => null,
			'messageType' => 'voice-message',
			'mimeType' => 'audio/wav',
			'caption' => 'What do you hear?',
		], $this->service->parseFileShareMessage($comment));
	}

	public function testParseFileSharedWithShareId(): void {
		$comment = $this->makeFileShareComment(json_encode([
			'message' => 'file_shared',
			'parameters' => [
				'share' => 42,
				'metaData' => [
					'mimeType' => 'image/png',
				],
			],
		]));
		$this->assertEquals([
			'fileId' => null,
			'shareId' => '42',
			'messageType' => null,
			'mimeType' => 'image/png',
			'caption' => '',
		], $this->service->parseFileShareMessage($comment));
	}

	public function testAttachmentTypeOfVoiceMessage(): void {
		$fileShare = [
			'messageType' => 'voice-message',
			'mimeType' => 'audio/wav',
		];
		$this->assertSame(AgentTalkFileService::ATTACHMENT_TYPE_AUDIO, $this->service->getAttachmentType($fileShare, null));
	}

	public function testAttachmentTypeOfAudioMimetype(): void {
		$fileShare = [
			'messageType' => null,
			'mimeType' => 'audio/mpeg',
		];
		$this->assertSame(AgentTalkFileService::ATTACHMENT_TYPE_AUDIO, $this->service->getAttachmentType($fileShare, null));
	}

	public function testAttachmentTypeOfImage(): void {
		$fileShare = [
			'messageType' => null,
			'mimeType' => 'image/png',
		];
		$this->assertSame(AgentTalkFileService::ATTACHMENT_TYPE_FILE, $this->service->getAttachmentType($fileShare, null));
	}

	public function testAttachmentTypeFromNodeMimetypeWhenMetaDataMissing(): void {
		$fileShare = [
			'messageType' => null,
			'mimeType' => null,
		];
		/** @var File&MockObject $node */
		$node = $this->createMock(File::class);
		$node->method('getMimeType')->willReturn('audio/ogg');
		$this->assertSame(AgentTalkFileService::ATTACHMENT_TYPE_AUDIO, $this->service->getAttachmentType($fileShare, $node));
	}

	public function testCaptionMentionsAreParsedFromCaption(): void {
		$storedMessage = '';
		$this->comment->method('setMessage')->willReturnCallback(
			function (string $message) use (&$storedMessage): void {
				$storedMessage = $message;
			}
		);
		$this->comment->method('getMentions')->willReturnCallback(
			static fn (): array => [['type' => 'user', 'id' => 'agent1']]
		);

		// getCaptionMentions must set the caption as the comment message,
		// like Talk's own notifier does
		$fileShare = ['caption' => '@"agent1" what is on this image?'];
		$mentions = $this->service->getCaptionMentions($this->comment, $fileShare);

		$this->assertSame('@"agent1" what is on this image?', $storedMessage);
		$this->assertSame([['type' => 'user', 'id' => 'agent1']], $mentions);
	}

	public function testCaptionMentionsWithoutCaptionAreEmpty(): void {
		$fileShare = ['caption' => ''];
		$this->assertSame([], $this->service->getCaptionMentions($this->comment, $fileShare));
	}
}
