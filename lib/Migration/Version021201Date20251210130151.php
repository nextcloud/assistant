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

class Version021201Date20251210130151 extends SimpleMigrationStep {

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
			if (!$table->hasColumn('summary')) {
				$table->addColumn('summary', Types::TEXT, [
					'notnull' => false,
					'default' => null,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('is_remembered')) {
				$table->addColumn('is_remembered', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('is_summary_up_to_date')) {
				$table->addColumn('is_summary_up_to_date', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
