<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Assistant\Tests\Unit\Service;

use OCA\Assistant\Db\Agent;
use OCA\Assistant\Db\ChattyLLM\Session;
use OCA\Assistant\Db\ChattyLLM\SessionMapper;
use OCA\Assistant\Service\AgencyActionService;
use OCA\Assistant\Service\AgentsService;
use OCA\Assistant\Service\AgentTalkFileService;
use OCA\Assistant\Service\AgentTalkService;
use OCA\Assistant\Service\ChatService;
use OCA\Assistant\Service\TaskProcessingService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\IComment;
use OCP\Files\ISetupManager;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * The automatic reactions of the agent are not asserted here: they go through
 * the Talk ReactionManager, which is only available when Talk is installed.
 * The reaction bookkeeping is verified through the session state instead.
 */
class AgentTalkServiceTest extends TestCase {

	private const AGENT_USER_ID = 'agent_user';
	private const HUMAN_USER_ID = 'human_user';
	private const SESSION_ID = 42;
	private const ROOM_TOKEN = 'room-token';
	private const COMMENT_ID = 1234;

	private AgentTalkService $service;
	private ChatService&MockObject $chatService;
	private SessionMapper&MockObject $sessionMapper;
	private IURLGenerator&MockObject $urlGenerator;
	private IL10N&MockObject $l10n;
	private Session $session;

	protected function setUp(): void {
		parent::setUp();

		if (!class_exists('OCA\Talk\Room')) {
			$this->markTestSkipped('Talk is not installed');
		}

		$this->session = new Session();
		$this->session->setId(self::SESSION_ID);
		$this->session->setUserId(self::AGENT_USER_ID);
		$this->session->setRequesterUserId(self::HUMAN_USER_ID);
		$this->session->setAgentSeenRoomToken(self::ROOM_TOKEN);
		$this->session->setAgentSeenCommentId(self::COMMENT_ID);
		$this->session->setAgentExplanationRoomToken(self::ROOM_TOKEN);
		$this->session->setAgentExplanationMessageId(self::COMMENT_ID);

		$this->sessionMapper = $this->createMock(SessionMapper::class);
		$this->sessionMapper->method('getAgentSessionByExplanationMessage')->willReturn($this->session);

		$this->chatService = $this->createMock(ChatService::class);

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => str_replace('%s', (string)($parameters[0] ?? ''), $text)
		);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($this->l10n);

		$this->service = new AgentTalkService(
			$this->createMock(IUserManager::class),
			$this->createMock(AgentsService::class),
			$this->chatService,
			$this->sessionMapper,
			$this->urlGenerator,
			$this->l10n,
			$this->createMock(AgencyActionService::class),
			$this->createMock(AgentTalkFileService::class),
			$this->createMock(TaskProcessingService::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IConfig::class),
			$l10nFactory,
			$this->createMock(ISetupManager::class),
			$this->createMock(\OCP\IDateTimeZone::class),
		);
	}

	private function getRoom(): \OCA\Talk\Room {
		/** @var \OCA\Talk\Room&MockObject $room */
		$room = $this->createMock(\OCA\Talk\Room::class);
		$room->method('getToken')->willReturn(self::ROOM_TOKEN);
		return $room;
	}

	private function getComment(): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn(self::COMMENT_ID);
		return $comment;
	}

	/**
	 * A ⏹️ reaction of the requester on the pending "generating an answer"
	 * explanation message stops the generation: the pending task is
	 * cancelled, the stop reactions are cleaned up, the explanation tracking
	 * is cleared and the message is forgotten so no late answer can be
	 * applied to it.
	 */
	public function testStopReactionCancelsTheRunningGeneration(): void {
		$this->chatService->method('cancelPendingGenerationTasks')
			->with(self::AGENT_USER_ID, self::SESSION_ID)
			->willReturn(2);
		$this->sessionMapper->expects($this->atLeast(1))->method('update');

		$this->service->handleStopReaction(self::HUMAN_USER_ID, $this->getRoom(), $this->getComment());

		$this->assertNull($this->session->getAgentSeenRoomToken());
		$this->assertNull($this->session->getAgentSeenCommentId());
		$this->assertNull($this->session->getAgentExplanationRoomToken());
		$this->assertNull($this->session->getAgentExplanationMessageId());
	}

	/**
	 * When nothing is scheduled or running, the stop reaction is ignored so
	 * that a generation that just completed can still remove its ⏹️ stop
	 * button.
	 */
	public function testStopReactionIsIgnoredWhenNothingIsCancelled(): void {
		$this->chatService->method('cancelPendingGenerationTasks')->willReturn(0);
		$this->sessionMapper->expects($this->never())->method('update');

		$this->service->handleStopReaction(self::HUMAN_USER_ID, $this->getRoom(), $this->getComment());

		$this->assertSame(self::ROOM_TOKEN, $this->session->getAgentSeenRoomToken());
		$this->assertSame(self::COMMENT_ID, $this->session->getAgentSeenCommentId());
		$this->assertSame(self::ROOM_TOKEN, $this->session->getAgentExplanationRoomToken());
		$this->assertSame(self::COMMENT_ID, $this->session->getAgentExplanationMessageId());
	}

	/**
	 * Only the human user the agent session is dedicated to may stop it:
	 * other members of a group room must not be able to cancel a generation.
	 */
	public function testStopReactionByAnotherUserIsIgnored(): void {
		$this->chatService->expects($this->never())->method('cancelPendingGenerationTasks');

		$this->service->handleStopReaction('another_user', $this->getRoom(), $this->getComment());

		$this->assertSame(self::ROOM_TOKEN, $this->session->getAgentSeenRoomToken());
	}

	public function testStopReactionOnUnhandledMessageIsIgnored(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->method('getAgentSessionByExplanationMessage')
			->willThrowException(new DoesNotExistException('no session handles this message'));
		$this->chatService->expects($this->never())->method('cancelPendingGenerationTasks');

		$service = new AgentTalkService(
			$this->createMock(IUserManager::class),
			$this->createMock(AgentsService::class),
			$this->chatService,
			$sessionMapper,
			$this->createMock(IURLGenerator::class),
			$this->createMock(IL10N::class),
			$this->createMock(AgencyActionService::class),
			$this->createMock(AgentTalkFileService::class),
			$this->createMock(TaskProcessingService::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IConfig::class),
			$this->createMock(IFactory::class),
			$this->createMock(ISetupManager::class),
			$this->createMock(\OCP\IDateTimeZone::class),
		);

		$service->handleStopReaction(self::HUMAN_USER_ID, $this->getRoom(), $this->getComment());
	}

	/**
	 * A 👍/👎 reaction of the requester on the pending confirmation message
	 * schedules the confirmation follow-up: the agent's own thumbs are
	 * removed, the confirmation is marked as being processed by clearing its
	 * tracking and the answer generation is scheduled with the confirmation
	 * flag.
	 */
	public function testConfirmationReactionSchedulesTheConfirmationFollowUp(): void {
		$this->session->setAgencyConfirmationRoomToken(self::ROOM_TOKEN);
		$this->session->setAgencyConfirmationCommentId(self::COMMENT_ID);

		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->method('getSessionById')->willReturn($this->session);
		$sessionMapper->expects($this->once())->method('update');
		$service = $this->createServiceWithSessionMapper($sessionMapper);

		$this->chatService->expects($this->once())
			->method('scheduleMessageGeneration')
			->with(self::AGENT_USER_ID, self::SESSION_ID, 1, $this->anything(), cancelRunningTask: true);

		$service->handleConfirmationReaction(
			self::HUMAN_USER_ID,
			self::SESSION_ID,
			self::COMMENT_ID,
			AgentTalkService::REACTION_APPROVE,
			$this->getRoom(),
		);

		$this->assertNull($this->session->getAgencyConfirmationRoomToken());
		$this->assertNull($this->session->getAgencyConfirmationCommentId());
	}

	/**
	 * When the confirmation tracking was already cleared (the confirmation
	 * was superseded or implicitly answered by a new message), a 👍/👎
	 * re-added on the stale confirmation message is ignored: the actions
	 * were invalidated, no follow-up generation may be triggered.
	 */
	public function testStaleConfirmationReactionIsIgnored(): void {
		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->method('getSessionById')->willReturn($this->session);
		$sessionMapper->expects($this->never())->method('update');
		$service = $this->createServiceWithSessionMapper($sessionMapper);

		$this->chatService->expects($this->never())->method('scheduleMessageGeneration');

		$service->handleConfirmationReaction(
			self::HUMAN_USER_ID,
			self::SESSION_ID,
			self::COMMENT_ID,
			AgentTalkService::REACTION_APPROVE,
			$this->getRoom(),
		);
	}

	/**
	 * A 👍/👎 reaction on a message that is not the confirmation currently
	 * tracked by the session is ignored.
	 */
	public function testConfirmationReactionOnAnotherMessageIsIgnored(): void {
		$this->session->setAgencyConfirmationRoomToken(self::ROOM_TOKEN);
		$this->session->setAgencyConfirmationCommentId(999);

		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->method('getSessionById')->willReturn($this->session);
		$sessionMapper->expects($this->never())->method('update');
		$service = $this->createServiceWithSessionMapper($sessionMapper);

		$this->chatService->expects($this->never())->method('scheduleMessageGeneration');

		$service->handleConfirmationReaction(
			self::HUMAN_USER_ID,
			self::SESSION_ID,
			self::COMMENT_ID,
			AgentTalkService::REACTION_APPROVE,
			$this->getRoom(),
		);

		$this->assertSame(999, $this->session->getAgencyConfirmationCommentId());
	}

	private function createServiceWithSessionMapper(SessionMapper $sessionMapper): AgentTalkService {
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($this->l10n);
		return new AgentTalkService(
			$this->createMock(IUserManager::class),
			$this->createMock(AgentsService::class),
			$this->chatService,
			$sessionMapper,
			$this->urlGenerator,
			$this->l10n,
			$this->createMock(AgencyActionService::class),
			$this->createMock(AgentTalkFileService::class),
			$this->createMock(TaskProcessingService::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IConfig::class),
			$l10nFactory,
			$this->createMock(ISetupManager::class),
			$this->createMock(\OCP\IDateTimeZone::class),
		);
	}

	/**
	 * A message written in the Talk thread of a scheduled task is not answered:
	 * the thread only carries the results of the runs. Nothing is generated
	 * and nothing is stored; the notice is posted as a reply (not asserted
	 * here, like the other Talk postings).
	 */
	public function testMessagesInTheScheduledTaskThreadAreNotAnswered(): void {
		$agentsService = $this->createMock(AgentsService::class);
		$agent = new Agent();
		$agent->setId(8);
		$agent->setUserId(self::AGENT_USER_ID);
		$agentsService->method('getAgentByUserId')
			->willReturnCallback(static fn (string $userId): ?Agent => $userId === self::AGENT_USER_ID ? $agent : null);
		$agentsService->method('canUserAccessAgent')->willReturn(true);

		// only the human user is resolvable: the posting of the notice
		// (and the read marker) bail out early, without touching Talk
		$userManager = $this->createMock(IUserManager::class);
		$humanUser = $this->createMock(IUser::class);
		$humanUser->method('getUID')->willReturn(self::HUMAN_USER_ID);
		$userManager->method('get')
			->willReturnCallback(static fn (string $userId): ?IUser => $userId === self::HUMAN_USER_ID ? $humanUser : null);

		$session = new Session();
		$session->setId(self::SESSION_ID);
		$session->setUserId(self::AGENT_USER_ID);
		$session->setRequesterUserId(self::HUMAN_USER_ID);
		$session->setAssignmentId(9);
		$session->setThreadId(1000);

		$sessionMapper = $this->createMock(SessionMapper::class);
		$sessionMapper->expects($this->once())
			->method('getAgentSessionByThread')
			->with(self::AGENT_USER_ID, 1000)
			->willReturn($session);
		$sessionMapper->expects($this->never())->method('update');

		$chatService = $this->createMock(ChatService::class);
		$chatService->expects($this->never())->method('createMessage');
		$chatService->expects($this->never())->method('scheduleMessageGeneration');

		$service = new AgentTalkService(
			$userManager,
			$agentsService,
			$chatService,
			$sessionMapper,
			$this->urlGenerator,
			$this->l10n,
			$this->createMock(AgencyActionService::class),
			$this->createMock(AgentTalkFileService::class),
			$this->createMock(TaskProcessingService::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IConfig::class),
			$this->createMock(IFactory::class),
			$this->createMock(ISetupManager::class),
			$this->createMock(\OCP\IDateTimeZone::class),
		);

		$room = $this->createMock(\OCA\Talk\Room::class);
		$room->method('getType')->willReturn(\OCA\Talk\Room::TYPE_ONE_TO_ONE);
		$room->method('getToken')->willReturn(self::ROOM_TOKEN);
		$room->method('getSecondParticipant')->willReturn(self::AGENT_USER_ID);

		$comment = $this->createMock(IComment::class);
		$comment->method('getActorType')->willReturn('users');
		$comment->method('getActorId')->willReturn(self::HUMAN_USER_ID);
		$comment->method('getVerb')->willReturn(\OCA\Talk\Chat\ChatManager::VERB_MESSAGE);
		$comment->method('getTopmostParentId')->willReturn('1000');
		$comment->method('getId')->willReturn(456);

		$service->handleIncomingTalkMessage($room, $comment);
	}

	/**
	 * When the tracked explanation message was already edited into another
	 * message (e.g. the "You stopped me." notice, processed from a session
	 * loaded before a stop reaction), a late answer of the stopped
	 * generation must neither overwrite the notice nor delete it: the answer
	 * is treated like an answer for a missing explanation instead.
	 */
	public function testAnswerDoesNotOverwriteTheStopNotice(): void {
		// like in the listener, the session was loaded before the stop
		// reaction was processed: it still carries the explanation tracking
		$room = $this->getRoom();
		$comment = $this->getComment();
		$comment->method('getVerb')->willReturn(\OCA\Talk\Chat\ChatManager::VERB_MESSAGE);
		$comment->method('getActorType')->willReturn('users');
		$comment->method('getActorId')->willReturn(self::AGENT_USER_ID);
		$comment->method('getMessage')->willReturn('🛑 _You stopped me._');

		// the editing path bails out: the tracked message is not the
		// explanation anymore, so no Talk message is edited or deleted
		$method = new \ReflectionMethod(AgentTalkService::class, 'isPendingGenerationExplanation');
		$this->assertFalse($method->invoke($this->service, $this->session, $comment));

		$explanation = $this->getComment();
		$explanation->method('getVerb')->willReturn(\OCA\Talk\Chat\ChatManager::VERB_MESSAGE);
		$explanation->method('getActorType')->willReturn('users');
		$explanation->method('getActorId')->willReturn(self::AGENT_USER_ID);
		$explanation->method('getMessage')->willReturn('_I am generating an answer, you can interrupt me by clicking the ⏹️ reaction or by sending another message._');
		$this->assertTrue($method->invoke($this->service, $this->session, $explanation));
	}

	/**
	 * A completed generation closes the seen message: it is forgotten, so
	 * that no late answer can be applied to it.
	 */
	public function testGenerationCompletedClosesTheSeenMessage(): void {
		$this->sessionMapper->expects($this->once())->method('update');

		$this->service->markGenerationCompleted($this->session, true);

		$this->assertNull($this->session->getAgentSeenRoomToken());
		$this->assertNull($this->session->getAgentSeenCommentId());
	}

	/**
	 * When nothing could be posted, the generation is handled as a failure:
	 * the seen message is forgotten, so that no late answer can be applied
	 * to it.
	 */
	public function testGenerationFailureClearsTheSeenState(): void {
		$this->sessionMapper->expects($this->once())->method('update');

		$this->service->markGenerationCompleted($this->session, false);

		$this->assertNull($this->session->getAgentSeenRoomToken());
		$this->assertNull($this->session->getAgentSeenCommentId());
	}

	/**
	 * When the first posting attempt of a generated file fails, the
	 * filesystem of the agent user is re-set up and the posting is retried:
	 * the task processing worker may have mounted the agent home storage
	 * before it was registered in the database, leaving the freshly created
	 * conversation folder unresolvable ("Node does not exist").
	 */
	public function testGeneratedFilePostingIsRetriedAfterFilesystemReSetup(): void {
		[$userManager, $setupManager] = $this->createFilePostingMocks();
		$setupManager->expects($this->once())->method('tearDown');
		$setupManager->expects($this->once())->method('setupForUser');

		$service = $this->createFilePostingService($userManager, $setupManager);
		$service->expects($this->exactly(2))
			->method('doPostAgentTalkFileMessage')
			->willReturnOnConsecutiveCalls(
				$this->throwException(new NotFoundException('Node does not exist')),
				null,
			);
		$this->assertTrue($this->postGeneratedFile($service));
	}

	/**
	 * When the posting still fails after the filesystem was re-set up, the
	 * failure is reported, so that the caller can post a fallback text
	 * instead of silently dropping the generated file.
	 */
	public function testGeneratedFilePostingFailsAfterRetrying(): void {
		[$userManager, $setupManager] = $this->createFilePostingMocks();
		$setupManager->expects($this->once())->method('tearDown');
		$setupManager->expects($this->once())->method('setupForUser');

		$service = $this->createFilePostingService($userManager, $setupManager);
		$service->expects($this->exactly(2))
			->method('doPostAgentTalkFileMessage')
			->willReturnOnConsecutiveCalls(
				$this->throwException(new NotFoundException('Node does not exist')),
				$this->throwException(new NotFoundException('Node does not exist')),
			);

		$this->assertFalse($this->postGeneratedFile($service));
	}

	/**
	 * A successful posting never touches the filesystem setup of the agent
	 * user: the teardown would clear the mounts of the whole process.
	 */
	public function testGeneratedFilePostingSuccessSkipsTheFilesystemReSetup(): void {
		[$userManager, $setupManager] = $this->createFilePostingMocks();
		$setupManager->expects($this->never())->method('tearDown');

		$service = $this->createFilePostingService($userManager, $setupManager);
		$service->expects($this->once())->method('doPostAgentTalkFileMessage');

		$this->assertTrue($this->postGeneratedFile($service));
	}

	private function createFilePostingMocks(): array {
		$setupManager = $this->createMock(ISetupManager::class);
		$userManager = $this->createMock(IUserManager::class);
		$agentUser = $this->createMock(IUser::class);
		$agentUser->method('getUID')->willReturn(self::AGENT_USER_ID);
		$userManager->method('get')->willReturn($agentUser);
		return [$userManager, $setupManager];
	}

	private function createFilePostingService(IUserManager $userManager, ISetupManager $setupManager): AgentTalkService {
		return $this->getMockBuilder(AgentTalkService::class)
			->setConstructorArgs([
				$userManager,
				$this->createMock(AgentsService::class),
				$this->createMock(ChatService::class),
				$this->createMock(SessionMapper::class),
				$this->createMock(IURLGenerator::class),
				$this->l10n,
				$this->createMock(AgencyActionService::class),
				$this->createMock(AgentTalkFileService::class),
				$this->createMock(TaskProcessingService::class),
				$this->createMock(LoggerInterface::class),
				$this->createMock(IConfig::class),
				$this->createMock(IFactory::class),
				$setupManager,
				$this->createMock(\OCP\IDateTimeZone::class),
			])
			->onlyMethods(['doPostAgentTalkFileMessage'])
			->getMock();
	}

	private function postGeneratedFile(AgentTalkService $service, int $fileId = 77, bool $asVoiceMessage = true): bool {
		$method = new \ReflectionMethod(AgentTalkService::class, 'postAgentTalkFileMessage');
		return $method->invoke($service, $this->getRoom(), $this->session, true, $fileId, $asVoiceMessage);
	}

	/**
	 * Like in a background job, there is no request route beforehand: while
	 * the agent conversation folder is resolved, the request route is Talk's
	 * attachment endpoint, for which Talk posts no generic file_shared message
	 * for the folder-level room share, and the callback return value is passed
	 * through.
	 */
	public function testRunWithAttachmentFolderRouteSetsTheTalkAttachmentRoute(): void {
		$request = \OCP\Server::get(\OCP\IRequest::class);
		$request->setUrlParameters(['_route' => '']);

		$routeInside = null;
		$result = $this->runWithAttachmentFolderRoute(function () use (&$routeInside, $request) {
			$routeInside = $request->getParam('_route');
			return 'result';
		});

		$this->assertSame('result', $result);
		$this->assertSame('ocs.spreed.chat.postattachmenttoroom', $routeInside);
	}

	/**
	 * The request parameters cannot be removed again: a former empty route
	 * value is restored, which is handled like no route by the route checks
	 * of Talk's system message listener.
	 */
	public function testRunWithAttachmentFolderRouteRestoresAnEmptyRoute(): void {
		$request = \OCP\Server::get(\OCP\IRequest::class);
		$request->setUrlParameters(['_route' => '']);

		$this->runWithAttachmentFolderRoute(static function (): void {
		});

		$this->assertSame('', $request->getParam('_route'));
	}

	/**
	 * A request route that existed before the folder resolution is restored,
	 * so that later share events of the same process are handled with the
	 * original route semantics.
	 */
	public function testRunWithAttachmentFolderRouteRestoresTheFormerRoute(): void {
		$request = \OCP\Server::get(\OCP\IRequest::class);
		$request->setUrlParameters(['_route' => 'my.route']);

		$routeInside = null;
		$this->runWithAttachmentFolderRoute(function () use (&$routeInside, $request): void {
			$routeInside = $request->getParam('_route');
		});

		$this->assertSame('ocs.spreed.chat.postattachmenttoroom', $routeInside);
		$this->assertSame('my.route', $request->getParam('_route'));
	}

	/**
	 * The pointer message content mentions the requester and links to the
	 * answer in the 1-1 conversation, with the thread context when the
	 * answer is threaded: it is needed to open the message, like in Talk's
	 * notification links.
	 */
	public function testRoomPointerContentWithThread(): void {
		$link = 'https://nextcloud.local/call/room-token?threadId=42';
		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('spreed.Page.showCall', [
				'token' => self::ROOM_TOKEN,
				'_fragment' => 'message_' . self::COMMENT_ID,
				'threadId' => 42,
			])
			->willReturn($link);

		$answerComment = $this->getComment();
		$answerComment->method('getMetaData')->willReturn([
			\OCA\Talk\Model\Message::METADATA_THREAD_ID => 42,
		]);

		$content = $this->invokeRoomPointerContent($answerComment);

		$this->assertSame(
			'@"' . self::HUMAN_USER_ID . '" I answered to you in [our private conversation](' . $link . ')',
			$content
		);
	}

	/**
	 * An answer posted outside a Talk thread links without thread context.
	 */
	public function testRoomPointerContentWithoutThread(): void {
		$link = 'https://nextcloud.local/call/room-token';
		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('spreed.Page.showCall', [
				'token' => self::ROOM_TOKEN,
				'_fragment' => 'message_' . self::COMMENT_ID,
			])
			->willReturn($link);

		$answerComment = $this->getComment();
		$answerComment->method('getMetaData')->willReturn([]);

		$content = $this->invokeRoomPointerContent($answerComment);

		$this->assertSame(
			'@"' . self::HUMAN_USER_ID . '" I answered to you in [our private conversation](' . $link . ')',
			$content
		);
	}

	private function invokeRoomPointerContent(IComment $answerComment): string {
		$method = new \ReflectionMethod(AgentTalkService::class, 'getRoomPointerContent');
		return $method->invoke($this->service, $this->getRoom(), $answerComment, self::HUMAN_USER_ID);
	}

	private function runWithAttachmentFolderRoute(callable $callback) {
		$method = new \ReflectionMethod(AgentTalkService::class, 'runWithAttachmentFolderRoute');
		return $method->invoke($this->service, $callback);
	}
}
