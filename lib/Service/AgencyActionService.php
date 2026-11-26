<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCP\IL10N;

/**
 * Shared data for agency actions: display names and icons for the sensitive
 * actions the Context Agent can request confirmation for, and the formatting
 * of the confirmation message posted to Talk.
 */
class AgencyActionService {

	private array $actionData;

	public function __construct(
		private IL10N $l10n,
	) {
		$this->actionData = [
			// talk
			'send_message_to_conversation' => [
				'title' => $this->l10n->t('Send a message to a Talk conversation'),
				'icon' => 'Send',
			],
			'create_public_conversation' => [
				'title' => $this->l10n->t('Create a conversation'),
				'icon' => 'ChatPlus',
			],
			// mail
			'send_email' => [
				'title' => $this->l10n->t('Send an email'),
				'icon' => 'EmailPlus',
			],
			// calendar
			'schedule_event' => [
				'title' => $this->l10n->t('Schedule a calendar event'),
				'icon' => 'CalendarPlus',
			],
			'add_task' => [
				'title' => $this->l10n->t('Add a calendar task'),
				'icon' => 'CalendarCheck',
			],
			// deck
			'add_card' => [
				'title' => $this->l10n->t('Create a Deck card'),
				'icon' => 'CardPlus',
			],
			'upload_file' => [
				'title' => $this->l10n->t('Upload file'),
				'icon' => 'Upload',
			],
			'create_folder' => [
				'title' => $this->l10n->t('Create folder'),
				'icon' => 'FolderPlus',
			],
			'move_file' => [
				'title' => $this->l10n->t('Move file'),
				'icon' => 'FileMove',
			],
			'copy_file' => [
				'title' => $this->l10n->t('Copy file'),
				'icon' => 'FileCopy',
			],
			'delete_file' => [
				'title' => $this->l10n->t('Delete file'),
				'icon' => 'Delete',
			],
			'create_bookmark' => [
				'title' => $this->l10n->t('Create bookmark'),
				'icon' => 'BookmarkPlus',
			],
			'update_bookmark' => [
				'title' => $this->l10n->t('Update bookmark'),
				'icon' => 'BookmarkEdit',
			],
			'delete_bookmark' => [
				'title' => $this->l10n->t('Delete bookmark'),
				'icon' => 'BookmarkRemove',
			],
			'create_bookmark_folder' => [
				'title' => $this->l10n->t('Create bookmark folder'),
				'icon' => 'FolderPlus',
			],
			'create_circle' => [
				'title' => $this->l10n->t('Create team'),
				'icon' => 'AccountGroupPlus',
			],
			'add_member_to_circle' => [
				'title' => $this->l10n->t('Add member to team'),
				'icon' => 'AccountPlus',
			],
			'remove_member_from_circle' => [
				'title' => $this->l10n->t('Remove member from team'),
				'icon' => 'AccountRemove',
			],
			'update_circle' => [
				'title' => $this->l10n->t('Update team'),
				'icon' => 'AccountGroupEdit',
			],
			'delete_circle' => [
				'title' => $this->l10n->t('Delete team'),
				'icon' => 'AccountGroupRemove',
			],
			'share_with_circle' => [
				'title' => $this->l10n->t('Share with team'),
				'icon' => 'ShareVariant',
			],
			'create_form' => [
				'title' => $this->l10n->t('Create form'),
				'icon' => 'FormTextbox',
			],
			'add_question_to_form' => [
				'title' => $this->l10n->t('Add question to form'),
				'icon' => 'CommentPlus',
			],
			'delete_form' => [
				'title' => $this->l10n->t('Delete form'),
				'icon' => 'Delete',
			],
			'update_form_settings' => [
				'title' => $this->l10n->t('Update form settings'),
				'icon' => 'Cog',
			],
			'share_with_user' => [
				'title' => $this->l10n->t('Share with user'),
				'icon' => 'AccountShare',
			],
			'share_with_group' => [
				'title' => $this->l10n->t('Share with group'),
				'icon' => 'AccountGroup',
			],
			'update_share_permissions' => [
				'title' => $this->l10n->t('Update share permissions'),
				'icon' => 'LockOpen',
			],
			'delete_share' => [
				'title' => $this->l10n->t('Delete share'),
				'icon' => 'Delete',
			],
		];
	}

	public function getActionTitle(string $toolName): ?string {
		return $this->actionData[$toolName]['title'] ?? null;
	}

	public function getActionIcon(string $toolName): ?string {
		return $this->actionData[$toolName]['icon'] ?? null;
	}

	/**
	 * Convert the pending actions JSON of a generation task output into a
	 * natural language confirmation message for Talk.
	 *
	 * Returns null when the actions JSON is empty or cannot be decoded;
	 * callers fall back to posting the raw string in that case.
	 */
	public function formatConfirmationMessage(string $pendingActions): ?string {
		$actions = json_decode($pendingActions, true);
		if (!is_array($actions) || $actions === []) {
			return null;
		}

		$formattedActions = [];
		foreach ($actions as $action) {
			$formatted = $this->formatAction($action);
			if ($formatted !== null) {
				$formattedActions[] = $formatted;
			}
		}
		if ($formattedActions === []) {
			return null;
		}

		$blocks = [];
		foreach ($formattedActions as $index => $action) {
			$lines = [];
			if ($action['title'] !== null) {
				$prefix = count($formattedActions) > 1 ? ($index + 1) . '. ' : '';
				$lines[] = $prefix . '**' . $action['title'] . '**';
			}
			$blocks[] = implode("\n", array_merge($lines, $action['paramLines']));
		}

		if (count($formattedActions) === 1) {
			return $this->l10n->t('I want to perform a sensitive action:') . "\n\n" . $blocks[0];
		}

		return $this->l10n->t('I want to perform the following sensitive actions:')
			. "\n\n" . implode("\n\n", $blocks);
	}

	/**
	 * @return array{title: ?string, paramLines: string[]}|null null when the action has neither a name nor arguments
	 */
	private function formatAction(mixed $action): ?array {
		if (!is_array($action)) {
			return null;
		}
		$title = $this->getActionDisplayTitle($action);
		$paramLines = [];
		foreach ($action['args'] ?? [] as $argName => $argValue) {
			$paramLines[] = '* ' . str_replace('_', ' ', (string)$argName) . ': ' . $this->formatParamValue($argValue);
		}
		if ($title === null && $paramLines === []) {
			return null;
		}
		return ['title' => $title, 'paramLines' => $paramLines];
	}

	private function getActionDisplayTitle(array $action): ?string {
		$toolName = $action['name'] ?? '';
		if (!is_string($toolName) || $toolName === '') {
			return null;
		}
		return $this->getActionTitle($toolName) ?? $this->prettifyToolName($toolName);
	}

	private function prettifyToolName(string $toolName): string {
		return ucfirst(str_replace('_', ' ', $toolName));
	}

	private function formatParamValue(mixed $value): string {
		if (is_array($value)) {
			if (array_is_list($value) && array_reduce($value, fn ($ok, $v) => $ok && (is_string($v) || is_numeric($v)), true)) {
				// a plain list of values is rendered as a comma-separated list
				return implode(', ', array_map(fn ($v) => $this->formatParamValue($v), $value));
			}
			return json_encode($value) ?: '';
		}
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		if ($value === null) {
			return '';
		}
		// newlines would break the single markdown list item structure
		return trim(str_replace(["\r", "\n"], ' ', (string)$value));
	}
}
