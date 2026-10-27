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
 * info and the URLs the display components build. The frontend half is a
 * source-text check, not a behavioral test: the repository has no
 * frontend test tooling yet, so it is a drift tripwire against accidental
 * edits, not a guarantee of the URL actually requested.
 */
class RoutesTest extends TestCase {

	/**
	 * The output file info route stays registered with the expected URL
	 * shape and verb.
	 */
	public function testOutputFileInfoRouteIsRegisteredWithExpectedUrl(): void {
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
	}

	/**
	 * Source-text check: both display components keep building the output
	 * file info URL the registered route expects. This does not assert the
	 * argument passed to axios.get; a frontend unit test would, but the
	 * repository has no frontend test tooling yet.
	 */
	public function testDisplayComponentsBuildOutputFileInfoUrlInSource(): void {
		foreach (['FileDisplay.vue', 'ImageDisplay.vue'] as $componentName) {
			$component = file_get_contents(__DIR__ . '/../../../src/components/fields/' . $componentName);
			self::assertIsString($component, $componentName . ' must exist');
			self::assertStringContainsString(
				'task/{taskId}/output-file/{fileId}/info',
				$component,
				$componentName . ' must keep building the output file info URL for the registered route'
			);
		}
	}
}
