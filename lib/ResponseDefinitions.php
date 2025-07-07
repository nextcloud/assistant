<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant;

use OCP\TaskProcessing\ShapeDescriptor;

/**
 * @psalm-type AssistantShapeDescriptor = array{
 *     name: string,
 *     description: string,
 *     type: string,
 * }
 *
 * If we replace "array<string, AssistantShapeDescriptor>" by "array<string, ShapeDescriptor|AssistantShapeDescriptor>"
 * OpenAPI-extractor fails
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
 *     id: int,
 *     input: array<string, list<numeric|string>|numeric|string>,
 *     lastUpdated: integer,
 *     output: array<string, list<numeric|string>|numeric|string>|null,
 *     progress: float|null,
 *     status: string,
 *     type: string,
 *     userId: string|null,
 *     endedAt: int|null,
 *     scheduledAt: int|null,
 *     startedAt: int|null,
 * }
 *
 * @psalm-type AssistantChatSession = array{
 *     id: int,
 *     user_id: string,
 *     title: ?string,
 *     timestamp: ?int,
 *     agency_conversation_token: ?string,
 *     agency_pending_actions: ?string,
 * }
 *
 * @psalm-type AssistantChatMessage = array{
 *     id: int,
 *     session_id: int,
 *     role: string,
 *     content: string,
 *     timestamp: int,
 *     attachments: list<array{type: string, fileId: int}>,
 *     ocp_task_id: int,
 *     sources: string,
 * }
 *
 * @psalm-type AssistantChatAgencyMessage = AssistantChatMessage&array{
 *     sessionAgencyPendingActions: array<string, mixed>,
 * }
 *
 * @psalm-type AssistantChatSessionCheck = array{
 *     messageTaskId: ?int,
 *     titleTaskId: ?int,
 *     sessionTitle: ?string,
 *     sessionAgencyPendingActions: ?array<string, mixed>,
 * }
 */
class ResponseDefinitions {
}
