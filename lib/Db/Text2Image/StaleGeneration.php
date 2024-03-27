<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Db\Text2Image;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getImageGenId()
 * @method void setImageGenId(string $imageGenId)
 *
 */
class StaleGeneration extends Entity implements \JsonSerializable {
	/** @var string */
	protected $imageGenId;


	public function __construct() {
		$this->addType('image_gen_id', 'string');

	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'image_gen_id' => $this->imageGenId,
		];
	}
}
