<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Event;

use OCP\EventDispatcher\Event;
use OCP\TaskProcessing\Task;

/**
 * Event to let apps that scheduled a task via the assistant
 * decide if they want a notification or not.
 * If they want one, they can specify the notification target link
 */
class BeforeAssistantNotificationEvent extends Event {

	private bool $wantsNotification = false;
	private ?string $notificationTarget = null;
	private ?string $notificationActionLabel = null;

	public function __construct(
		private Task $task,
	) {
		parent::__construct();
	}

	/**
	 * Get the task that was successful and for which a notification can be produced
	 *
	 * @return Task
	 */
	public function getTask(): Task {
		return $this->task;
	}

	/**
	 * Does the app that scheduled the task want a notification?
	 *
	 * @return bool
	 */
	public function getWantsNotification(): bool {
		return $this->wantsNotification;
	}

	/**
	 * @param bool $wantsNotification true means a notification will be produced for this task
	 * @return void
	 */
	public function setWantsNotification(bool $wantsNotification): void {
		$this->wantsNotification = $wantsNotification;
	}

	/**
	 * @return string|null
	 */
	public function getNotificationTarget(): ?string {
		return $this->notificationTarget;
	}

	/**
	 * @param string|null $notificationTarget URL that will be used as target for the notification and its main action,
	 *                                        null means the assistant will take care of rendering the result (in a modal or by setting a dedicated NC page showing the result)
	 * @return void
	 */
	public function setNotificationTarget(?string $notificationTarget): void {
		$this->notificationTarget = $notificationTarget;
	}

	/**
	 * @return string|null
	 */
	public function getNotificationActionLabel(): ?string {
		return $this->notificationActionLabel;
	}

	/**
	 * @param string|null $notificationActionLabel Label of the main notification action
	 *                                             null will not change the default action label
	 * @return void
	 */
	public function setNotificationActionLabel(?string $notificationActionLabel): void {
		$this->notificationActionLabel = $notificationActionLabel;
	}
}
