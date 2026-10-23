<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040000Date20261023083738 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$schemaChanged = false;

		if (!$schema->hasTable('assistant_agents')) {
			$table = $schema->createTable('assistant_agents');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('personality', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('scope', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id'], 'assistant_agents_user_id');
			$schemaChanged = true;
		}

		if ($schema->hasTable('assistant_chat_sns')) {
			$table = $schema->getTable('assistant_chat_sns');
			if (!$table->hasColumn('agent_user_id')) {
				$table->addColumn('agent_user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$table->addColumn('room_token', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$table->addColumn('thread_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
					'unsigned' => true,
				]);
				$table->addIndex(['user_id', 'agent_user_id', 'room_token'], 'assistant_chat_agent_idx');
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
