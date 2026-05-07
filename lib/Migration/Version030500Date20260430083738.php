<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030500Date20260430083738 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('assistant_assignments')) {
			$schemaChanged = true;
			$table = $schema->createTable('assistant_assignments');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('prompt', Types::TEXT, [
				'notnull' => true,
			]);
			// this is an RFC 5545 RRULE
			$table->addColumn('recurrence', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('starts_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('last_run_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'assistant_assgnmts_user_id_idx');
		}
		if ($schema->hasTable('assistant_chat_sns')) {
			$table = $schema->getTable('assistant_chat_sns');
			if (!$table->hasColumn('assignment_id')) {
				$schemaChanged = true;
				$table->addColumn('assignment_id', Types::BIGINT, [
					'notnull' => false,
					'unsigned' => true,
				]);
			}
			if (!$table->hasIndex('assistant_chat_assgnmt_uid')) {
				$schemaChanged = true;
				$table->addIndex(['user_id', 'assignment_id'], 'assistant_chat_assgnmt_uid');
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
