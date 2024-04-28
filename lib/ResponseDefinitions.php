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
 * @psalm-type AssistantTaskType = array{
 *     id: string,
 *     name: string,
 *     description: string,
 *     inputShape: array<AssistantShapeDescriptor>,
 *     optionalInputShape: array<AssistantShapeDescriptor>,
 *     outputShape: array<AssistantShapeDescriptor>,
 *     optionalOutputShape: array<AssistantShapeDescriptor>,
 * }
 *
 * @psalm-type AssistantTask = array{
 *     id: int,
 *     userId: string,
 *     inputs: array<string, mixed>,
 *     output: string,
 *     appId: string,
 *     ocpTaskId: int,
 *     taskType: string,
 *     timestamp: int,
 *     status: int,
 *     category: int,
 *     identifier: string,
 * }
 *
 * @psalm-type AssistantImageProcessPromptResponse = array{
 *     task: AssistantTask,
 *     url: string,
 *     reference_url: string,
 *     image_gen_id: string,
 *     prompt: string,
 * }
 *
 * @psalm-type AssistantImageGenInfo = array{
 *     files?: array<array{id: int, visible?: bool}>,
 *     prompt?: string,
 *     image_gen_id?: string,
 *     is_owner?: bool,
 *     processing?: int,
 * }
 */
class ResponseDefinitions {
}
