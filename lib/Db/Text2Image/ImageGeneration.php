<?php

declare(strict_types=1);
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Assistant\Db\Text2Image;

use OCP\AppFramework\Db\Entity;

/**
 * @method \string getImageGenId()
 * @method \void setImageGenId(string $imageGenId)
 * @method \string getPrompt()
 * @method \void setPrompt(string $prompt)
 * @method \string getUserId()
 * @method \void setUserId(string $userId)
 * @method \int getTimestamp()
 * @method \void setTimestamp(int $timestamp)
 * @method \boolean getIsGenerated()
 * @method \void setIsGenerated(bool $isGenerated)
 * @method \boolean getFailed()
 * @method \void setFailed(bool $failed)
 * @method \boolean getNotifyReady()
 * @method \void setNotifyReady(bool $notifyReady)
 * @method \int getExpGenTime()
 * @method \void setExpGenTime(int $expGenTime)
 *
 */
class ImageGeneration extends Entity implements \JsonSerializable {
	/** @var string */
	protected $imageGenId;
	/** @var string */
	protected $prompt;
	/** @var string */
	protected $userId;
	/** @var int */
	protected $timestamp;
	/** @var boolean */
	protected $isGenerated;
	/** @var boolean */
	protected $failed;
	/** @var boolean */
	protected $notifyReady;
	/** @var int */
	protected $expGenTime;


	public function __construct() {
		$this->addType('image_gen_id', 'string');
		$this->addType('prompt', 'string');
		$this->addType('user_id', 'string');
		$this->addType('timestamp', 'int');
		$this->addType('is_generated', 'boolean');
		$this->addType('failed', 'boolean');
		$this->addType('notify_ready', 'boolean');
		$this->addType('exp_gen_time', 'int');
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'image_gen_id' => $this->imageGenId,
			'prompt' => $this->prompt,
			'user_id' => $this->userId,
			'timestamp' => $this->timestamp,
			'is_generated' => $this->isGenerated,
			'failed' => $this->failed,
			'notify_ready' => $this->notifyReady,
			'exp_gen_time' => $this->expGenTime,
		];
	}
}
