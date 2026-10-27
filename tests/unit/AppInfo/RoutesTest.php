<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\AppInfo;

use PHPUnit\Framework\TestCase;

/**
 * Pins the URL contract between the registered OCS route for output file
 * info and the URL the frontend builds, so the two sides cannot drift
 * apart silently.
 */
class RoutesTest extends TestCase {

	/**
	 * The output file info route stays registered with the URL shape the
	 * FileDisplay component builds its request from.
	 */
	public function testOutputFileInfoRouteMatchesFrontendUrl(): void {
		$routes = require __DIR__ . '/../../../appinfo/routes.php';
		self::assertArrayHasKey('ocs', $routes);

		$match = null;
		foreach ($routes['ocs'] as $route) {
			if (($route['name'] ?? null) === 'assistantApi#getOutputFileInfo') {
				$match = $route;
				break;
			}
		}

		self::assertNotNull($match, 'the assistantApi#getOutputFileInfo route must stay registered');
		self::assertSame('/api/{apiVersion}/task/{ocpTaskId}/output-file/{fileId}/info', $match['url']);
		self::assertSame('GET', $match['verb']);

		$component = file_get_contents(__DIR__ . '/../../../src/components/fields/FileDisplay.vue');
		self::assertIsString($component);
		self::assertStringContainsString(
			'task/{taskId}/output-file/{fileId}/info',
			$component,
			'FileDisplay must keep building the output file info URL for the registered route'
		);
	}
}
