<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020400Date20250203125359 extends SimpleMigrationStep {

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

		if ($schema->hasTable('assistant_chat_msgs')) {
			$table = $schema->getTable('assistant_chat_msgs');
			if (!$table->hasColumn('ocp_task_id')) {
				$table->addColumn('ocp_task_id', Types::BIGINT, [
					'notnull' => true,
					'default' => 0,
					'unsigned' => true,
				]);
				$table->addIndex(['session_id', 'ocp_task_id'], 'assistant_ch_sid_tid');
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
