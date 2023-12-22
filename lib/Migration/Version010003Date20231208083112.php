<?php
// SPDX-FileCopyrightText: Sami Finnilä <sami.finnila@nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\TPAssistant\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010003Date20231208083112 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return void
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('assistant_i_prompts')) {
			$table = $schema->createTable('assistant_i_prompts');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('value', Types::STRING, [
				'notnull' => true,
				'length' => 1000,
			]);
			$table->addColumn('timestamp', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'assistant_i_prompt_uid');
		}

		if (!$schema->hasTable('assistant_i_gens')) {
			$table = $schema->createTable('assistant_i_gens');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('image_gen_id', Types::STRING, [
				'notnull' => true,
			]);
			$table->addColumn('is_generated', Types::BOOLEAN, [
				'notnull' => false, 'default' => false,
			]);
			$table->addColumn('failed', Types::BOOLEAN, [
				'notnull' => false, 'default' => false,
			]);
			$table->addColumn('notify_ready', Types::BOOLEAN, [
				'notnull' => false, 'default' => false,
			]);
			$table->addColumn('prompt', Types::STRING, [
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
			]);
			$table->addColumn('timestamp', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('exp_gen_time', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['image_gen_id'], 'assistant_i_gens_id');
		}

		if (!$schema->hasTable('assistant_i_files')) {
			$table = $schema->createTable('assistant_i_files');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('generation_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('file_name', Types::STRING, [
				'notnull' => true,
			]);
			$table->addColumn('hidden', Types::BOOLEAN, [
				'notnull' => false, 'default' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['generation_id'], 'assistant_gen_id');
		}

		if (!$schema->hasTable('assistant_stale_gens')) {
			$table = $schema->createTable('assistant_stale_gens');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('image_gen_id', Types::STRING, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['image_gen_id'], 'assistant_i_stale_gens_id');
		}

		if (!$schema->hasTable('assistant_t_prompts')) {
			$table = $schema->createTable('assistant_t_prompts');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('value', Types::STRING, [
				'notnull' => true,
				'length' => 1000,
			]);
			$table->addColumn('timestamp', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'assistant_t_prompts_uid');
			$table->addIndex(['user_id', 'timestamp'], 'assistant_t_prompts_uid_ts');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
	}
}
