<?php
/**
 * @copyright Copyright (c) 2023 Anupam Kumar <kyteinsky@gmail.com>
 *
 * @author Anupam Kumar <kyteinsky@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Assistant\Listener\SpeechToText;

use OCA\Assistant\AppInfo\Application;

use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class SpeechToTextReferenceListener implements IEventListener {
	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private ?string $userId,
		private ITaskProcessingManager $taskProcessingManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RenderReferenceEvent) {
			return;
		}
		if ($this->appConfig->getValueString(Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1'
			&& ($this->userId === null || $this->config->getUserValue($this->userId, Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1')) {

			// Double check that at least one provider is registered
			$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();
			$speechToTextAvailable = array_key_exists(AudioToText::ID, $availableTaskTypes);
			if ($speechToTextAvailable) {
				Util::addScript(Application::APP_ID, Application::APP_ID . '-speechToTextReference');
			}
		}
	}
}
