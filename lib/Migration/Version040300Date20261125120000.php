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

class Version040300Date20261125120000 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('assistant_agents')) {
			$table = $schema->createTable('assistant_agents');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('personality', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('scope', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id'], 'assistant_agents_user_id');
			$schemaChanged = true;
		}

		if (!$schema->hasTable('assistant_agents_acl')) {
			$table = $schema->createTable('assistant_agents_acl');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('agent_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 10,
			]);
			// the uid or the gid depending on the type
			$table->addColumn('target', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['agent_id'], 'assistant_agents_acl_agent');
			$schemaChanged = true;
		}

		if ($schema->hasTable('assistant_chat_sns')) {
			$table = $schema->getTable('assistant_chat_sns');
			// agent sessions are owned by the agent user account,
			// requester_user_id is the id of the human user the agent converses with
			if (!$table->hasColumn('requester_user_id')) {
				$table->addColumn('requester_user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('room_token')) {
				$table->addColumn('room_token', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('thread_id')) {
				$table->addColumn('thread_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			// the Talk room + message of the last human message read by the agent,
			// so it can be replaced by the generated answer state (✅/🔴)
			if (!$table->hasColumn('agent_seen_room_token')) {
				$table->addColumn('agent_seen_room_token', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('agent_seen_comment_id')) {
				$table->addColumn('agent_seen_comment_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			// the Talk room + message of the pending confirmation
			// so the 👍/👎 reactions can be removed when the confirmation
			// is answered implicitly by sending a new message
			if (!$table->hasColumn('agency_confirmation_room_token')) {
				$table->addColumn('agency_confirmation_room_token', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('agency_confirmation_comment_id')) {
				$table->addColumn('agency_confirmation_comment_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			// the Talk room + message of the pending "generating an answer"
			// explanation, so it can be deleted again when the answer is
			// posted or the generation fails or is stopped
			if (!$table->hasColumn('agent_explanation_room_token')) {
				$table->addColumn('agent_explanation_room_token', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('agent_explanation_message_id')) {
				$table->addColumn('agent_explanation_message_id', Types::BIGINT, [
					'notnull' => false,
					'length' => 20,
					'unsigned' => true,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasIndex('assistant_chat_requester_idx')) {
				$table->addIndex(['user_id', 'requester_user_id', 'room_token'], 'assistant_chat_requester_idx');
				$schemaChanged = true;
			}
			// the agent session of a Talk thread is looked up on every
			// message posted in the thread
			if (!$table->hasIndex('assistant_chat_thread_idx')) {
				$table->addIndex(['user_id', 'thread_id'], 'assistant_chat_thread_idx');
				$schemaChanged = true;
			}
			// the agent session of a pending generation explanation is looked
			// up when the explanation is edited or its stop button used
			if (!$table->hasIndex('assistant_chat_explanation_idx')) {
				$table->addIndex(['agent_explanation_room_token', 'agent_explanation_message_id'], 'assistant_chat_explanation_idx');
				$schemaChanged = true;
			}
		}

		if ($schema->hasTable('assistant_assignments')) {
			$table = $schema->getTable('assistant_assignments');
			// the human who ordered the task through an agent in Talk, and the
			// Talk thread of the agent's 1-1 conversation where the run results
			// are delivered; null for assignments created in the web UI
			if (!$table->hasColumn('requester_user_id')) {
				$table->addColumn('requester_user_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$schemaChanged = true;
			}
			if (!$table->hasColumn('talk_thread_id')) {
				$table->addColumn('talk_thread_id', Types::BIGINT, [
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
