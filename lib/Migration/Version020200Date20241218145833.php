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

class Version020200Date20241218145833 extends SimpleMigrationStep {

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

		if ($schema->hasTable('assistant_chat_sns')) {
			$table = $schema->getTable('assistant_chat_sns');
			if (!$table->hasColumn('conversation_token')) {
				$table->addColumn('agency_conversation_token', Types::TEXT, [
					'notnull' => false,
					'default' => null,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('agency_pending_actions')) {
				$table->addColumn('agency_pending_actions', Types::TEXT, [
					'notnull' => false,
					'default' => null,
				]);
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
