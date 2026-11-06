<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * A context shared with a team has to reach the team's members.
 *
 * The share row is written whatever the receiver type is, so the UI accepts attaching a
 * team either way; only the lookup decides whether anybody can then open it.
 */
class ContextMapperTeamShareTest extends DatabaseTestCase {
	private const OWNER = 'owner_uid';
	private const MEMBER = 'member_uid';
	private const OUTSIDER = 'outsider_uid';
	private const TEAM_ID = 'team_single_id';

	private CircleHelper&MockObject $circleHelper;
	private ContextMapper $mapper;
	private int $contextId;

	protected function setUp(): void {
		parent::setUp();

		$userHelper = $this->createMock(UserHelper::class);
		$userHelper->method('getGroupIdsForUser')->willReturn([]);

		$this->circleHelper = $this->createMock(CircleHelper::class);
		$this->circleHelper->method('getCircleIdsForUser')->willReturnCallback(
			static fn (string $userId): ?array => $userId === self::MEMBER ? [self::TEAM_ID] : [],
		);

		$this->mapper = new ContextMapper(
			$this->connectionAdapter,
			$userHelper,
			$this->createMock(GroupHelper::class),
			$this->circleHelper,
		);

		$this->contextId = $this->insertContext();
		$this->insertTeamShare($this->contextId);
	}

	public function testATeamMemberFindsTheContextSharedWithTheirTeam(): void {
		$this->assertContains($this->contextId, $this->idsVisibleTo(self::MEMBER));
	}

	public function testSomebodyOutsideTheTeamDoesNot(): void {
		$this->assertNotContains($this->contextId, $this->idsVisibleTo(self::OUTSIDER));
	}

	public function testTheOwnerStillFindsTheirOwnContext(): void {
		$this->assertContains($this->contextId, $this->idsVisibleTo(self::OWNER));
	}

	/** @return list<int> */
	private function idsVisibleTo(string $userId): array {
		return array_map(
			static fn ($context): int => $context->getId(),
			$this->mapper->findAll($userId),
		);
	}

	private function insertContext(): int {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->insert('tables_contexts_context')
			->values([
				'name' => $qb->createNamedParameter('Team application'),
				'icon' => $qb->createNamedParameter('app'),
				'description' => $qb->createNamedParameter(''),
				'owner_id' => $qb->createNamedParameter(self::OWNER),
				'owner_type' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			])
			->executeStatement();

		return $qb->getLastInsertId();
	}

	private function insertTeamShare(int $contextId): void {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->insert('tables_shares')
			->values([
				'sender' => $qb->createNamedParameter(self::OWNER),
				'receiver' => $qb->createNamedParameter(self::TEAM_ID),
				'receiver_type' => $qb->createNamedParameter(ShareReceiverType::CIRCLE),
				'node_id' => $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT),
				'node_type' => $qb->createNamedParameter('context'),
				'created_at' => $qb->createNamedParameter('2026-01-01 00:00:00'),
				'last_edit_at' => $qb->createNamedParameter('2026-01-01 00:00:00'),
			])
			->executeStatement();
	}
}
