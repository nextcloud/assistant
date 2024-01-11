<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021-2022 The Recognize contributors.
 * @copyright Copyright (c) 2023 Anupam Kumar <kyteinsky@gmail.com>
 *
 * @author Anupam Kumar <kyteinsky@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TpAssistant\Db\SpeechToText;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Class Transcript
 *
 * @package OCA\Stt\Db
 * @method ?string getUserId()
 * @method void setUserId(?string $userId)
 * @method string getTranscript()
 * @method void setTranscript(string $transcript)
 * @method \DateTime getLastAccessed()
 * @method void setLastAccessed(\DateTime $lastAccessed)
 */
class Transcript extends Entity {

	protected $userId;
	protected $transcript;
	protected $lastAccessed;

	public static $columns = [
		'id',
		'user_id',
		'transcript',
		'last_accessed',
	];
	public static $fields = [
		'id',
		'userId',
		'transcript',
		'lastAccessed',
	];

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('userId', Types::STRING);
		$this->addType('transcript', Types::STRING);
		$this->addType('lastAccessed', Types::DATETIME);
	}
}
