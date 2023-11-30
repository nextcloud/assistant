<?php

namespace OCA\Assistant\Tests;

use OCA\Assistant\AppInfo\Application;
use Test\TestCase;

class TextProcessingServiceTest extends TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('assistant', $app::APP_ID);
	}
}
