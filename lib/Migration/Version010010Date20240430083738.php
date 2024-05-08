<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Anupam Kumar <kyteinsky@gmail.com>
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
