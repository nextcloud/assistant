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

class Version040100Date20261113120000 extends SimpleMigrationStep {

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
			// the Talk room + message of the pending confirmation
			// so the 👍/👎 reactions can be removed when the confirmation
			// is answered implicitly by sending a new message
			if (!$table->hasColumn('agency_confirmation_room_token')) {
				$table->addColumn('agency_confirmation_room_token', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$table->addColumn('agency_confirmation_comment_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
		}

		return $schemaChanged ? $schema : null;
	}
}
