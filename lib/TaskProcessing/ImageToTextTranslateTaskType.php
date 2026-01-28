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

class ImageToTextTranslateTaskType implements ITaskType {
	public const ID = Application::APP_ID . ':image2text:translate';

	public function __construct(
		private IL10N $l,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l->t('Translate image');
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string {
		return $this->l->t('Translate the text content of an image');
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
			'input' => new ShapeDescriptor(
				$this->l->t('Input files'),
				$this->l->t('The files to extract text from'),
				EShapeType::ListOfFiles
			),
			'origin_language' => new ShapeDescriptor(
				$this->l->t('Origin language'),
				$this->l->t('The language of the origin text'),
				EShapeType::Enum
			),
			'target_language' => new ShapeDescriptor(
				$this->l->t('Target language'),
				$this->l->t('The desired language to translate the origin text in'),
				EShapeType::Enum
			),
		];
	}

	/**
	 * @return ShapeDescriptor[]
	 */
	public function getOutputShape(): array {
		return [
			'output' => new ShapeDescriptor(
				$this->l->t('Output texts'),
				$this->l->t('The texts that were extracted from the files'),
				EShapeType::ListOfTexts
			),
		];
	}
}
