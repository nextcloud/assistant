<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Settings;

use OCA\Assistant\AppInfo\Application;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AgentMemoriesService;
use OCA\Assistant\Service\AgentSkillsService;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\McpServersService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\TaskProcessing\Exception\Exception;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\AudioToText;
use OCP\TaskProcessing\TaskTypes\TextToImage;
use OCP\TaskProcessing\TaskTypes\TextToText;
use Psr\Log\LoggerInterface;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IAppConfig $appConfig,
		private IInitialState $initialStateService,
		private ?string $userId,
		private ITaskProcessingManager $taskProcessingManager,
		private SessionMapper $sessionMapper,
		private AgentSkillsService $agentSkillsService,
		private AgentMemoriesService $agentMemoriesService,
		private ChatService $chatService,
		private McpServersService $mcpServersService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$availableTaskTypes = $this->taskProcessingManager->getAvailableTaskTypes();

		$taskProcessingAvailable = $this->taskProcessingManager->hasProviders();

		$freePromptTaskTypeAvailable = array_key_exists(TextToText::ID, $availableTaskTypes);
		$speechToTextAvailable = array_key_exists(AudioToText::ID, $availableTaskTypes);
		$textToImageAvailable = array_key_exists(TextToImage::ID, $availableTaskTypes);

		$audioChatAvailable = (class_exists('OCP\\TaskProcessing\\TaskTypes\\AudioToAudioChat') && array_key_exists(\OCP\TaskProcessing\TaskTypes\AudioToAudioChat::ID, $availableTaskTypes))
			|| (class_exists('OCP\\TaskProcessing\\TaskTypes\\ContextAgentAudioInteraction') && array_key_exists(\OCP\TaskProcessing\TaskTypes\ContextAgentAudioInteraction::ID, $availableTaskTypes));
		$autoplayAudioChat = $this->config->getUserValue($this->userId, Application::APP_ID, 'autoplay_audio_chat', '1') === '1';
		$chatPersonalInstructionsEnabled = $this->appConfig->getValueString(Application::APP_ID, 'chat_personal_instructions_enabled', '0', lazy: true) === '1';
		$dataFolder = $this->config->getUserValue($this->userId, Application::APP_ID, 'data_folder', '');
		$defaultDataFolder = $this->appConfig->getValueString(Application::APP_ID, 'default_data_folder', Application::ASSISTANT_DATA_FOLDER_NAME, lazy: true);
		if ($defaultDataFolder === '') {
			$defaultDataFolder = Application::ASSISTANT_DATA_FOLDER_NAME;
		}

		$assistantAvailable = $taskProcessingAvailable && $this->appConfig->getValueString(Application::APP_ID, 'assistant_enabled', '1', lazy: true) === '1';
		$assistantEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'assistant_enabled', '1') === '1';

		$textToImagePickerAvailable = $textToImageAvailable && $this->appConfig->getValueString(Application::APP_ID, 'text_to_image_picker_enabled', '1', lazy: true) === '1';
		$textToImagePickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_image_picker_enabled', '1') === '1';

		$textToStickerPickerAvailable = $textToImageAvailable && $this->appConfig->getValueString(Application::APP_ID, 'text_to_sticker_picker_enabled', '1', lazy: true) === '1';
		$textToStickerPickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'text_to_sticker_picker_enabled', '1') === '1';

		$freePromptPickerAvailable = $freePromptTaskTypeAvailable && $this->appConfig->getValueString(Application::APP_ID, 'free_prompt_picker_enabled', '1', lazy: true) === '1';
		$freePromptPickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'free_prompt_picker_enabled', '1') === '1';

		$speechToTextPickerAvailable = $speechToTextAvailable && $this->appConfig->getValueString(Application::APP_ID, 'speech_to_text_picker_enabled', '1', lazy: true) === '1';
		$speechToTextPickerEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'speech_to_text_picker_enabled', '1') === '1';

		$userConfig = [
			'task_processing_available' => $taskProcessingAvailable,
			'assistant_available' => $assistantAvailable,
			'assistant_enabled' => $assistantEnabled,
			'text_to_image_picker_available' => $textToImagePickerAvailable,
			'text_to_image_picker_enabled' => $textToImagePickerEnabled,
			'text_to_sticker_picker_available' => $textToStickerPickerAvailable,
			'text_to_sticker_picker_enabled' => $textToStickerPickerEnabled,
			'free_prompt_picker_available' => $freePromptPickerAvailable,
			'free_prompt_picker_enabled' => $freePromptPickerEnabled,
			'speech_to_text_picker_available' => $speechToTextPickerAvailable,
			'speech_to_text_picker_enabled' => $speechToTextPickerEnabled,
			'audio_chat_available' => $audioChatAvailable,
			'autoplay_audio_chat' => $autoplayAudioChat,
			'chat_personal_instructions_enabled' => $chatPersonalInstructionsEnabled,
			'personal_instructions_max_length' => Application::MAX_PERSONAL_INSTRUCTIONS_LENGTH,
			'data_folder' => $dataFolder,
			'default_data_folder' => $defaultDataFolder,
		];
		foreach (Application::PERSONAL_INSTRUCTIONS_KEYS as $instructionsKey) {
			$userConfig[$instructionsKey] = $this->config->getUserValue($this->userId, Application::APP_ID, $instructionsKey, '');
		}
		$this->initialStateService->provideInitialState('config', $userConfig);

		$availableProviders = [];
		foreach ($availableTaskTypes as $taskTypeId => $availableTaskType) {
			try {
				$providerName = $this->taskProcessingManager->getPreferredProvider($taskTypeId)->getName();
				if (!isset($availableProviders[$providerName])) {
					$availableProviders[$providerName] = [];
				}
				$availableProviders[$providerName][] = $availableTaskType['name'];
			} catch (Exception $e) {
				// pass
			}
		}
		$this->initialStateService->provideInitialState('availableProviders', $availableProviders);

		$rememberedSessions = $this->sessionMapper->getRememberedUserSessions($this->userId);
		$rememberedSessionsShort = [];
		foreach ($rememberedSessions as $session) {
			$rememberedSessionsShort[] = [
				'id' => $session->getId(),
				'title' => $session->getTitle(),
				'summary' => $session->getSummary(),
			];
		}

		$this->initialStateService->provideInitialState('rememberedSessions', $rememberedSessionsShort);

		$this->initialStateService->provideInitialState('agentContent', $this->chatService->isContextAgentAvailable() ? $this->getAgentContent() : false);
		$this->initialStateService->provideInitialState('mcpServers', $this->getMcpServers());
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	/**
	 * List the Context Agent skills and memories of the current user. A list is null if it could not be loaded.
	 *
	 * @return array{skills: ?array{user: list<array{name: string, description: string, fileId: ?int}>, global: list<array{name: string, description: string}>}, skillsFolderId: ?int, memories: ?list<array{path: string, fileId: int}>, memoriesFolderId: ?int}
	 */
	private function getAgentContent(): array {
		$content = ['skills' => null, 'skillsFolderId' => null, 'memories' => null, 'memoriesFolderId' => null];
		try {
			$content['skills'] = $this->agentSkillsService->listSkillsBySource($this->userId);
			$content['skillsFolderId'] = $this->agentSkillsService->getSkillsFolder($this->userId)->getId();
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to list the agent skills', ['exception' => $e]);
		}
		try {
			$content['memories'] = $this->agentMemoriesService->listMemories($this->userId);
			$content['memoriesFolderId'] = $this->agentMemoriesService->getMemoriesFolder($this->userId)->getId();
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to list the agent memories', ['exception' => $e]);
		}
		return $content;
	}

	/**
	 * The MCP servers of the current user, or false if there is nothing to show
	 *
	 * Servers stay listed after the administrator disallows them or the Context Agent
	 * is gone, so they can be removed.
	 *
	 * @return false|array{enabled: bool, agentAvailable: bool, servers: list<array{id: string, name: string, url: string, enabled: bool, headers: list<string>}>, maxServers: int}
	 */
	private function getMcpServers(): false|array {
		$enabled = $this->mcpServersService->isEnabled();
		$agentAvailable = $this->chatService->isContextAgentAvailable();
		$servers = $this->mcpServersService->listServers($this->userId);
		if (!($enabled && $agentAvailable) && $servers === []) {
			return false;
		}
		return [
			'enabled' => $enabled,
			'agentAvailable' => $agentAvailable,
			'servers' => $servers,
			'maxServers' => McpServersService::MAX_SERVERS,
		];
	}

	public function getSection(): string {
		return 'ai';
	}

	public function getPriority(): int {
		return 10;
	}
}
