<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;

class ContextMapperTest extends DatabaseTestCase {
	private ContextMapper $mapper;
	private UserHelper&MockObject $userHelper;
	private GroupHelper&MockObject $groupHelper;
	private CircleHelper&MockObject $circleHelper;

	protected function setUp(): void {
		parent::setUp();
		$this->userHelper = $this->createMock(UserHelper::class);
		$this->groupHelper = $this->createMock(GroupHelper::class);
		$this->circleHelper = $this->createMock(CircleHelper::class);
		// no group membership in these scenarios, so only the circle clause can match
		$this->userHelper->method('getGroupIdsForUser')->willReturn([]);
		$this->mapper = new ContextMapper(
			$this->connectionAdapter,
			$this->userHelper,
			$this->groupHelper,
			$this->circleHelper,
		);
		$this->cleanup();
	}

	protected function tearDown(): void {
		$this->cleanup();
		parent::tearDown();
	}

	private function cleanup(): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('tables_contexts_context')->executeStatement();
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('tables_shares')->executeStatement();
	}

	private function insertContext(string $ownerId): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_contexts_context')
			->setValue('name', $qb->createNamedParameter('Circle application'))
			->setValue('icon', $qb->createNamedParameter('star'))
			->setValue('description', $qb->createNamedParameter(''))
			->setValue('owner_id', $qb->createNamedParameter($ownerId))
			->setValue('owner_type', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->executeStatement();
		return (int)$this->connection->lastInsertId();
	}

	private function insertCircleShare(int $contextId, string $circleId, string $sender): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_shares')
			->setValue('sender', $qb->createNamedParameter($sender))
			->setValue('receiver', $qb->createNamedParameter($circleId))
			->setValue('receiver_type', $qb->createNamedParameter('circle'))
			->setValue('node_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT))
			->setValue('node_type', $qb->createNamedParameter('context'))
			->setValue('permission_read', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->setValue('permission_create', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->setValue('permission_update', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->setValue('permission_delete', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->setValue('permission_manage', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->setValue('created_at', $qb->createNamedParameter('2026-01-01 00:00:00'))
			->setValue('last_edit_at', $qb->createNamedParameter('2026-01-01 00:00:00'))
			->executeStatement();
	}

	/**
	 * A context shared to a circle must be resolved for a member of that circle,
	 * and must stay invisible to a user who is not a member.
	 */
	public function testFindAllResolvesCircleSharedContextForMembersOnly(): void {
		$contextId = $this->insertContext('alice');
		$this->insertCircleShare($contextId, 'circle-single-id-1', 'alice');

		$this->circleHelper->method('getCircleIdsForUser')
			->willReturnMap([
				['bob', ['circle-single-id-1']],
				['carol', []],
			]);

		$memberIds = array_map(
			static fn ($context) => $context->getId(),
			$this->mapper->findAll('bob'),
		);
		self::assertContains($contextId, $memberIds, 'a circle member should see the circle-shared application');

		$nonMemberIds = array_map(
			static fn ($context) => $context->getId(),
			$this->mapper->findAll('carol'),
		);
		self::assertNotContains($contextId, $nonMemberIds, 'a non-member should not see the circle-shared application');
	}
}
