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

class Version040300Date20261118120000 extends SimpleMigrationStep {

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
			// the Talk room + message of the pending "generating an answer"
			// explanation, so it can be deleted again when the answer is
			// posted or the generation fails or is stopped
			if (!$table->hasColumn('agent_explanation_room_token')) {
				$table->addColumn('agent_explanation_room_token', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$table->addColumn('agent_explanation_message_id', Types::BIGINT, [
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
