<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Assistant;

/**
 * @psalm-type AssistantShapeDescriptor = array{
 *     name: string,
 *     description: string,
 *     type: int,
 * }
 *
 * @psalm-type AssistantTaskProcessingTaskType = array{
 *     id: string,
 *     name: string,
 *     description: string,
 *     inputShape: array<string, AssistantShapeDescriptor>,
 *     optionalInputShape: array<string, AssistantShapeDescriptor>,
 *     outputShape: array<string, AssistantShapeDescriptor>,
 *     optionalOutputShape: array<string, AssistantShapeDescriptor>,
 *     priority: integer,
 * }
 *
 * @psalm-type AssistantTaskProcessingTask = array{
 *     appId: string,
 *     completionExpectedAt: integer|null,
 *     customId: string|null,
 *     id: int|null,
 *     input: array<string, mixed>,
 *     lastUpdated: integer,
 *     output: array<string, mixed>,
 *     progress: float|null,
 *     status: string,
 *     type: string,
 *     userId: string|null,
 * }
 */
class ResponseDefinitions {
}
