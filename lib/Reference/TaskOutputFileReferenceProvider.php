<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Reference;

use OCA\Assistant\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\LinkReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IURLGenerator;
use OCP\TaskProcessing\IManager as TaskProcessingManager;

class TaskOutputFileReferenceProvider implements IReferenceProvider {

	private const RICH_OBJECT_TYPE = Application::APP_ID . '_task-output-file';

	public function __construct(
		private IReferenceManager $referenceManager,
		private LinkReferenceProvider $linkReferenceProvider,
		private IURLGenerator $urlGenerator,
		private TaskProcessingManager $taskProcessingManager,
		private ?string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		return $this->getLinkInfo($referenceText) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$linkInfo = $this->getLinkInfo($referenceText);
			if ($linkInfo !== null) {
				$taskId = $linkInfo['taskId'];
				$task = $this->taskProcessingManager->getTask($taskId);
				if ($task->getUserId() === null || $task->getUserId() !== $this->userId) {
					return null;
				}

				$linkInfo['taskTypeId'] = $task->getTaskTypeId();
				$linkInfo['taskTypeName'] = $this->taskProcessingManager->getAvailableTaskTypes()[$task->getTaskTypeId()]['name'] ?? null;
				$reference = new Reference($referenceText);
				$reference->setRichObject(
					self::RICH_OBJECT_TYPE,
					$linkInfo,
				);
				return $reference;
			}
			// fallback to opengraph
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return array|null
	 */
	private function getLinkinfo(string $url): ?array {
		// assistant download link
		// https://nextcloud.local/ocs/v2.php/apps/assistant/api/v1/task/42/output-file/398/download

		$start = $this->urlGenerator->linkToOCSRouteAbsolute(Application::APP_ID . '.assistantApi.getOutputFile', [
			'apiVersion' => 'v1',
			'ocpTaskId' => 123,
			'fileId' => 123,
		]);
		$start = str_replace('/task/123/output-file/123/download', '/task/', $start);
		if (str_starts_with($url, $start)) {
			preg_match('/\/task\/(\d+)\/output-file\/(\d+)\/download$/i', $url, $matches);
			if (count($matches) > 2) {
				return [
					'taskId' => (int)$matches[1],
					'fileId' => (int)$matches[2],
				];
			}
		}

		// task processing download links
		// https://nextcloud.local/ocs/v2.php/taskprocessing/tasks/42/file/398

		$start = $this->urlGenerator->linkToOCSRouteAbsolute('core.taskProcessingApi.getFileContents', [
			'taskId' => 123,
			'fileId' => 123,
		]);
		$start = str_replace('/tasks/123/file/123', '/tasks/', $start);
		if (str_starts_with($url, $start)) {
			preg_match('/\/tasks\/(\d+)\/file\/(\d+)$/i', $url, $matches);
			if (count($matches) > 2) {
				return [
					'taskId' => (int)$matches[1],
					'fileId' => (int)$matches[2],
				];
			}
		}

		return null;
	}

	/**
	 * We use the userId here because when connecting/disconnecting from the GitHub account,
	 * we want to invalidate all the user cache and this is only possible with the cache prefix
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * We don't use the userId here but rather a reference unique id
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
