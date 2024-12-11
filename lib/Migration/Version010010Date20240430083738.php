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

class Version010010Date20240430083738 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('assistant_chat_sns')) {
			$schemaChanged = true;
			$table = $schema->createTable('assistant_chat_sns');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('timestamp', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'assistant_chat_ss_uid');
		}

		if (!$schema->hasTable('assistant_chat_msgs')) {
			$schemaChanged = true;
			$table = $schema->createTable('assistant_chat_msgs');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
			]);
			$table->addColumn('session_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('role', Types::STRING, [
				'length' => 256,
			]);
			$table->addColumn('content', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('timestamp', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['session_id'], 'assistant_chat_ms_sid');
		}

		return $schemaChanged ? $schema : null;
	}
}
