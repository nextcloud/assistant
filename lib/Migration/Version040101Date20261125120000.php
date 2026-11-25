<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version040101Date20261125120000 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * Turn sessions of deleted assignments into normal chat sessions
	 *
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->db->tableExists('assistant_chat_sns') || !$this->db->tableExists('assistant_assignments')) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('s.assignment_id')
			->from('assistant_chat_sns', 's')
			->leftJoin('s', 'assistant_assignments', 'a', $qb->expr()->eq('s.assignment_id', 'a.id'))
			->where($qb->expr()->isNotNull('s.assignment_id'))
			->andWhere($qb->expr()->isNull('a.id'));
		$result = $qb->executeQuery();
		$orphanedIds = array_map('intval', $result->fetchFirstColumn());
		$result->closeCursor();

		$detached = 0;
		foreach (array_chunk($orphanedIds, 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('assistant_chat_sns')
				->set('assignment_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
				->where($qb->expr()->in('assignment_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$detached += $qb->executeStatement();
		}

		if ($detached > 0) {
			$output->info('Moved ' . $detached . ' chat session(s) of deleted scheduled tasks to the normal chats');
		}
	}
}
