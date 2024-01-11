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

namespace OCA\TPAssistant\Db\SpeechToText;

use DateTime;
use Exception;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * @template-extends QBMapper<Transcript>
 */
class TranscriptMapper extends QBMapper {

	public function __construct(IDBConnection $db, private LoggerInterface $logger) {
		parent::__construct($db, 'assistant_stt_transcripts', Transcript::class);
		$this->db = $db;
	}

	/**
	 * @param integer $id
	 * @param string|null $userId
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException if more than one item exist
	 * @throws DoesNotExistException if the item does not exist
	 * @return Transcript
	 */
	public function find(int $id, ?string $userId): Transcript {
		$qb = $this->db->getQueryBuilder();

		if (strlen($userId) > 0 && $userId !== 'admin') {
			$qb
				->select(Transcript::$columns)
				->from($this->getTableName())
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			;
		} else {
			$qb
				->select(Transcript::$columns)
				->from($this->getTableName())
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			;
		}

		return $this->findEntity($qb);
	}

	public function cleanupTranscriptions(): void {
		$qb = $this->db->getQueryBuilder();
		$qb
			->delete($this->getTableName())
			->where($qb->expr()->lte(
				'last_accessed',
				$qb->createNamedParameter(new DateTime('-2 weeks'), IQueryBuilder::PARAM_DATE)
			))
		;

		$deletedRows = $qb->executeStatement();
		$this->logger->debug('Cleared {count} old transcriptions', ['count' => $deletedRows]);
	}
}
