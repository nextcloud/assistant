/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { addNewFileMenuEntry } from '@nextcloud/files'

import { entry as generateImageEntry } from './components/FilesNewMenu/generateImageEntry.js'

addNewFileMenuEntry(generateImageEntry)
