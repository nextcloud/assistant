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

class Version030000Date20260203140000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('assistant_scheduled_tasks')) {
			$table = $schema->createTable('assistant_scheduled_tasks');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('calendar_id', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('uri', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('calendardata', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('lastmodified', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('etag', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('is_processed', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('processed_at', Types::BIGINT, [
				'notnull' => false,
				'default' => null,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['calendar_id'], 'ast_calendar_id');
			$table->addIndex(['user_id'], 'ast_user_id');
			$table->addIndex(['uri'], 'ast_uri');
			$table->addIndex(['is_processed'], 'ast_is_processed');
			$table->addUniqueIndex(['calendar_id', 'uri'], 'ast_calendar_uri');

			return $schema;
		}

		return null;
	}
}
