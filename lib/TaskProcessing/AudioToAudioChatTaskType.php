<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\TaskProcessing;

use OCA\Assistant\AppInfo\Application;
use OCP\IL10N;
use OCP\TaskProcessing\EShapeType;
use OCP\TaskProcessing\ITaskType;
use OCP\TaskProcessing\ShapeDescriptor;

class AudioToAudioChatTaskType implements ITaskType {
	public const ID = Application::APP_ID . ':audio2audio:chat';

	public function __construct(
		private IL10N $l,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l->t('Voice chat');
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string {
		return $this->l->t('Voice chat with the assistant');
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return self::ID;
	}

	/**
	 * @return ShapeDescriptor[]
	 */
	public function getInputShape(): array {
		return [
			'system_prompt' => new ShapeDescriptor(
				$this->l->t('System prompt'),
				$this->l->t('Define rules and assumptions that the assistant should follow during the conversation.'),
				EShapeType::Text,
			),
			'input' => new ShapeDescriptor(
				$this->l->t('Chat voice message'),
				$this->l->t('Describe a task that you want the assistant to do or ask a question'),
				EShapeType::Audio,
			),
			'history' => new ShapeDescriptor(
				$this->l->t('Chat history'),
				$this->l->t('The history of chat messages before the current message, starting with a message by the user'),
				EShapeType::ListOfTexts,
			),
		];
	}

	/**
	 * @return ShapeDescriptor[]
	 */
	public function getOutputShape(): array {
		return [
			'input_transcript' => new ShapeDescriptor(
				$this->l->t('Input transcript'),
				$this->l->t('Transcription of the audio input'),
				EShapeType::Text,
			),
			'output' => new ShapeDescriptor(
				$this->l->t('Response voice message'),
				$this->l->t('The generated voice response as part of the conversation'),
				EShapeType::Audio
			),
			'output_transcript' => new ShapeDescriptor(
				$this->l->t('Output transcript'),
				$this->l->t('Transcription of the audio output'),
				EShapeType::Text,
			),
		];
	}
}
