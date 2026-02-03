<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\BackgroundJob;

use OCA\Assistant\Service\ScheduledTasksService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

class ProcessScheduledTasks extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private IDBConnection $db,
		private ScheduledTasksService $scheduledTasksService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		// Run every minute
		$this->setInterval(60);
	}

	/**
	 * @param mixed $argument
	 */
	protected function run($argument): void {
		$currentTime = time();
		$this->logger->debug('Running ProcessScheduledTasks background job');

		try {
			// Get all calendar events that should be executed now
			$events = $this->getEventsToProcess($currentTime);

			foreach ($events as $event) {
				try {
					$this->processEvent($event);
				} catch (\Exception $e) {
					$this->logger->error('Failed to process scheduled event', [
						'exception' => $e,
						'event_id' => $event['id'],
						'user_id' => $event['user_id'],
					]);
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to run ProcessScheduledTasks background job', [
				'exception' => $e,
			]);
		}
	}

	/**
	 * Get calendar events that need to be processed
	 *
	 * @param int $currentTime
	 * @return array
	 */
	private function getEventsToProcess(int $currentTime): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'uri', 'calendardata', 'lastmodified')
			->from('assistant_scheduled_tasks')
			->where($qb->expr()->eq('is_processed', $qb->createNamedParameter(0)))
			->orderBy('id', 'ASC');

		$result = $qb->executeQuery();
		$events = [];

		while ($row = $result->fetch()) {
			try {
				// Parse iCalendar data
				$vcalendar = Reader::read($row['calendardata']);
				$vevent = $vcalendar->VEVENT;

				if (!$vevent) {
					continue;
				}

				// Get event start time
				$dtstart = $vevent->DTSTART;
				if (!$dtstart) {
					continue;
				}

				$eventTime = $dtstart->getDateTime()->getTimestamp();

				// Check if event should be executed (within the last minute)
				if ($eventTime <= $currentTime && $eventTime >= ($currentTime - 120)) {
					$description = (string)($vevent->DESCRIPTION ?? '');
					if (!empty($description)) {
						$events[] = [
							'id' => $row['id'],
							'user_id' => $row['user_id'],
							'uri' => $row['uri'],
							'description' => $description,
							'event_time' => $eventTime,
						];
					}
				}
			} catch (\Exception $e) {
				$this->logger->warning('Failed to parse calendar event', [
					'exception' => $e,
					'event_id' => $row['id'],
				]);
			}
		}
		$result->closeCursor();

		return $events;
	}

	/**
	 * Process a single event by executing the task
	 *
	 * @param array $event
	 * @throws \Exception
	 */
	private function processEvent(array $event): void {
		$this->logger->info('Processing scheduled task event', [
			'event_id' => $event['id'],
			'user_id' => $event['user_id'],
			'description' => $event['description'],
		]);

		// Execute the task
		$this->scheduledTasksService->executeScheduledTask(
			$event['user_id'],
			$event['description']
		);

		// Mark event as processed
		$this->markEventAsProcessed($event['id']);

		$this->logger->info('Successfully processed scheduled task event', [
			'event_id' => $event['id'],
			'user_id' => $event['user_id'],
		]);
	}

	/**
	 * Mark an event as processed
	 *
	 * @param int $eventId
	 */
	private function markEventAsProcessed(int $eventId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('assistant_scheduled_tasks')
			->set('is_processed', $qb->createNamedParameter(1))
			->set('processed_at', $qb->createNamedParameter(time()))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($eventId)))
			->executeStatement();
	}
}
