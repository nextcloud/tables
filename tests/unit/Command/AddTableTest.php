<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Command;

use OCA\Tables\Command\AddTable;
use OCA\Tables\Db\Table;
use OCA\Tables\Service\TableService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

class AddTableTest extends TestCase {
	private TableService&MockObject $tableService;
	private CommandTester $commandTester;

	protected function setUp(): void {
		parent::setUp();
		$this->tableService = $this->createMock(TableService::class);
		$command = new AddTable($this->tableService, $this->createMock(LoggerInterface::class));
		$this->commandTester = new CommandTester($command);
	}

	/**
	 * The user id argument belongs in the $userId parameter. Passing it fourth
	 * landed it in $description and left $userId empty, which made every
	 * invocation fail in PermissionsService::preCheckUserId().
	 */
	public function testPassesTheUserIdAsOwnerAndNotAsDescription(): void {
		$this->tableService->expects($this->once())
			->method('create')
			->with('Budget', '', '', '', 'alice')
			->willReturn($this->createTableStub());

		$this->assertSame(0, $this->commandTester->execute([
			'user-id' => 'alice',
			'title' => 'Budget',
		]));
	}

	public function testForwardsEmojiAndTemplateOptions(): void {
		$this->tableService->expects($this->once())
			->method('create')
			->with('Budget', 'todo', '📊', '', 'alice')
			->willReturn($this->createTableStub());

		$this->assertSame(0, $this->commandTester->execute([
			'user-id' => 'alice',
			'title' => 'Budget',
			'--emoji' => '📊',
			'--template' => 'todo',
		]));
	}

	private function createTableStub(): Table&MockObject {
		$table = $this->createMock(Table::class);
		$table->method('jsonSerialize')->willReturn(['id' => 1, 'title' => 'Budget']);
		return $table;
	}
}
