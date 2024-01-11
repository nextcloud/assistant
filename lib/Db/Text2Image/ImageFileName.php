<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\TpAssistant\Db\Text2Image;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getGenerationId()
 * @method void setGenerationId(int $generationId)
 * @method string getFileName()
 * @method void setFileName(string $fileName)
 *
 */
class ImageFileName extends Entity implements \JsonSerializable {
	/** @var int */
	protected $generationId;
	/** @var string */
	protected $fileName;
	/** @var bool */
	protected $hidden;


	public function __construct() {
		$this->addType('generation_id', 'int');
		$this->addType('file_name', 'string');
		$this->addType('hidden', 'boolean');

	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'generation_id' => $this->generationId,
			'file_name' => $this->fileName,
			'hidden' => $this->hidden,
		];
	}

	public function setHidden(?bool $hidden): void {
		$this->hidden = $hidden === true;
	}

	public function getHidden(): bool {
		return $this->hidden === true;
	}
}
