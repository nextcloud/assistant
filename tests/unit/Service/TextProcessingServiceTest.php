<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests;

use OCA\Assistant\AppInfo\Application;

class TextProcessingServiceTest extends \PHPUnit\Framework\TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('assistant', $app::APP_ID);
	}
}
