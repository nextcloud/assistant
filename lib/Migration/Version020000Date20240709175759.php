<?php

// SPDX-FileCopyrightText: Julien Veyssier <julien-nc@posteo.net>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\Assistant\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020000Date20240709175759 extends SimpleMigrationStep {
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
		$schemaChanged = false;

		if ($schema->hasTable('assistant_i_files')) {
			$table = $schema->getTable('assistant_i_files');
			if ($table->hasIndex('assistant_gen_id')) {
				$table->dropIndex('assistant_gen_id');
			}
			$schema->dropTable('assistant_i_files');
			$schemaChanged = true;
		}

		if ($schema->hasTable('assistant_i_gens')) {
			$table = $schema->getTable('assistant_i_gens');
			if ($table->hasIndex('assistant_i_gens_id')) {
				$table->dropIndex('assistant_i_gens_id');
			}
			$schema->dropTable('assistant_i_gens');
			$schemaChanged = true;
		}

		if ($schema->hasTable('assistant_stale_gens')) {
			$table = $schema->getTable('assistant_stale_gens');
			if ($table->hasIndex('assistant_i_stale_gens_id')) {
				$table->dropIndex('assistant_i_stale_gens_id');
			}
			$schema->dropTable('assistant_stale_gens');
			$schemaChanged = true;
		}

		if ($schema->hasTable('assistant_i_prompts')) {
			$table = $schema->getTable('assistant_i_prompts');
			if ($table->hasIndex('assistant_i_prompt_uid')) {
				$table->dropIndex('assistant_i_prompt_uid');
			}
			$schema->dropTable('assistant_i_prompts');
			$schemaChanged = true;
		}

		if ($schema->hasTable('assistant_t_prompts')) {
			$table = $schema->getTable('assistant_t_prompts');
			if ($table->hasIndex('assistant_t_prompts_uid')) {
				$table->dropIndex('assistant_t_prompts_uid');
			}
			if ($table->hasIndex('assistant_t_prompts_uid_ts')) {
				$table->dropIndex('assistant_t_prompts_uid_ts');
			}
			$schema->dropTable('assistant_t_prompts');
			$schemaChanged = true;
		}

		if ($schema->hasTable('assistant_meta_tasks')) {
			$table = $schema->getTable('assistant_meta_tasks');
			if ($table->hasIndex('assistant_meta_task_uid')) {
				$table->dropIndex('assistant_meta_task_uid');
			}
			if ($table->hasIndex('assistant_meta_task_id_cat')) {
				$table->dropIndex('assistant_meta_task_id_cat');
			}
			$schema->dropTable('assistant_meta_tasks');
			$schemaChanged = true;
		}

		return $schemaChanged ? $schema : null;
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
