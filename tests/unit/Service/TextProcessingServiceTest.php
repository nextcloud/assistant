<?php

namespace OCA\TpAssistant\Tests;

use OCA\TpAssistant\AppInfo\Application;

class TextProcessingServiceTest extends \PHPUnit\Framework\TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('assistant', $app::APP_ID);
	}
}
