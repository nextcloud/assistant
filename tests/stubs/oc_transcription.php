<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OC\SpeechToText {

	use OCP\BackgroundJob\QueuedJob;

	class TranscriptionJob extends QueuedJob {
		protected function run($argument) {
		}
	}
}
