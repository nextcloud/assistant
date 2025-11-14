<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Service;

use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use Psr\Log\LoggerInterface;

/**
 * Service for streaming LLM responses in real-time
 */
class StreamingService {

	public function __construct(
		private IClientService $clientService,
		private IAppConfig $appConfig,
		private ITaskProcessingManager $taskProcessingManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Check if the configured provider supports streaming
	 *
	 * @return bool
	 */
	public function isStreamingAvailable(): bool {
		// For now, we check if streaming endpoint configuration exists
		// In the future, this could query provider capabilities
		$apiUrl = $this->appConfig->getValueString('assistant', 'streaming_api_url', '');
		$apiKey = $this->appConfig->getValueString('assistant', 'streaming_api_key', '');

		return $apiUrl !== '' && $apiKey !== '';
	}

	/**
	 * Get provider-specific streaming configuration
	 *
	 * @return array{api_url: string, api_key: string, model: string}
	 */
	private function getProviderConfig(): array {
		return [
			'api_url' => $this->appConfig->getValueString('assistant', 'streaming_api_url', ''),
			'api_key' => $this->appConfig->getValueString('assistant', 'streaming_api_key', ''),
			'model' => $this->appConfig->getValueString('assistant', 'streaming_model', 'gpt-3.5-turbo'),
		];
	}

	/**
	 * Stream chat completion from provider
	 * Yields string chunks as they arrive
	 *
	 * @param array $messages Array of messages in OpenAI format
	 * @return \Generator<string> Yields text chunks
	 * @throws \Exception
	 */
	public function streamChatCompletion(array $messages): \Generator {
		$config = $this->getProviderConfig();

		if (empty($config['api_url']) || empty($config['api_key'])) {
			throw new \Exception('Streaming is not configured');
		}

		$client = $this->clientService->newClient();

		// Prepare request body (OpenAI-compatible format)
		$requestBody = json_encode([
			'model' => $config['model'],
			'messages' => $messages,
			'stream' => true,
		]);

		$this->logger->debug('Starting streaming request to ' . $config['api_url']);

		try {
			// Make streaming request
			$response = $client->request('POST', $config['api_url'], [
				'stream' => true,
				'headers' => [
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $config['api_key'],
				],
				'body' => $requestBody,
			]);

			$body = $response->getBody();
			$buffer = '';

			// Read and parse SSE stream
			while (!$body->eof()) {
				$chunk = $body->read(8192);
				$buffer .= $chunk;

				// Process complete lines
				while (($pos = strpos($buffer, "\n")) !== false) {
					$line = substr($buffer, 0, $pos);
					$buffer = substr($buffer, $pos + 1);

					$line = trim($line);

					// Skip empty lines and comments
					if ($line === '' || strpos($line, ':') === 0) {
						continue;
					}

					// Parse SSE data field
					if (strpos($line, 'data: ') === 0) {
						$data = substr($line, 6);

						// Check for stream end
						if ($data === '[DONE]') {
							return;
						}

						// Parse JSON chunk
						$json = json_decode($data, true);
						if ($json && isset($json['choices'][0]['delta']['content'])) {
							$content = $json['choices'][0]['delta']['content'];
							yield $content;
						}
					}
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Streaming error: ' . $e->getMessage(), ['exception' => $e]);
			throw new \Exception('Failed to stream response: ' . $e->getMessage());
		}
	}

	/**
	 * Format messages for OpenAI-compatible API
	 *
	 * @param array $dbMessages Messages from database
	 * @return array Messages in OpenAI format
	 */
	public function formatMessagesForProvider(array $dbMessages): array {
		$formatted = [];

		foreach ($dbMessages as $msg) {
			$formatted[] = [
				'role' => $msg->getRole(),
				'content' => $msg->getContent(),
			];
		}

		return $formatted;
	}
}
