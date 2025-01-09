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
