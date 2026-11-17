<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Listener;

use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Listener\UserDeletedListener;
use OCA\Tables\Service\TableService;
use OCP\IUser;
use OCP\User\Events\BeforeUserDeletedEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class UserDeletedListenerTest extends TestCase {
	public function testFailedTeamHandoverDoesNotDeleteTeamTablesOrPreventPersonalCleanup(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$user->method('getDisplayName')->willReturn('Alice');
		$teamTable = new Table();
		$teamTable->setId(1);
		$teamTable->setTeamId('team');
		$personalTable = new Table();
		$personalTable->setId(2);
		$tableService = $this->createMock(TableService::class);
		$tableService->method('handleTeamStewardDeparture')->with('team', 'alice')->willThrowException(new InternalError('handover failed'));
		$tableService->method('findAll')->willReturn([$teamTable, $personalTable]);
		$tableService->expects(self::once())->method('delete')->with(2, 'alice')->willReturn($personalTable);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('error');

		(new UserDeletedListener($tableService, $logger))->handle(new BeforeUserDeletedEvent($user));
	}
}
