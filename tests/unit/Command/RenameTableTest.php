<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Command;

use OCA\Tables\Command\RenameTable;
use OCA\Tables\Db\Table;
use OCA\Tables\Service\TableService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * The negatable --archived option must reach TableService::update() as a nullable
 * bool: true to archive, false to unarchive, null to leave the state unchanged.
 */
class RenameTableTest extends TestCase {
	/**
	 * @return array<string, array{string, bool|null}>
	 */
	public static function archivedOptionProvider(): array {
		return [
			'--archived archives' => ['5 --archived', true],
			'--no-archived unarchives' => ['5 --no-archived', false],
			'omitted leaves unchanged' => ['5', null],
		];
	}

	/**
	 * @dataProvider archivedOptionProvider
	 */
	public function testArchivedOptionMapsToNullableBool(string $input, ?bool $expected): void {
		$table = $this->createMock(Table::class);
		$table->method('jsonSerialize')->willReturn([]);

		$tableService = $this->createMock(TableService::class);
		$tableService->expects($this->once())
			->method('update')
			->with('5', null, null, null, $expected, '')
			->willReturn($table);

		$command = new RenameTable($tableService, $this->createMock(LoggerInterface::class));

		$this->assertSame(0, $command->run(new StringInput($input), new NullOutput()));
	}
}
