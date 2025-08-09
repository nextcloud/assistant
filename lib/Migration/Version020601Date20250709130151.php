<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020601Date20250709130151 extends SimpleMigrationStep {

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

		// some MariaDB/MySQL instances upgraded successfully to 2.6.0 with notNull=true
		// this makes sure we bring everybody to the same notNull value for sources and attachments
		if ($schema->hasTable('assistant_chat_msgs')) {
			$table = $schema->getTable('assistant_chat_msgs');
			if ($table->hasColumn('sources')) {
				$column = $table->getColumn('sources');
				$column->setNotnull(false);
				$schemaChanged = true;
			}
			if ($table->hasColumn('attachments')) {
				$column = $table->getColumn('attachments');
				$column->setNotnull(false);
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
