<?php

namespace OCA\TPAssistant\Tests;

use OCA\TPAssistant\AppInfo\Application;

class TextProcessingServiceTest extends \PHPUnit\Framework\TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('assistant', $app::APP_ID);
	}
}
