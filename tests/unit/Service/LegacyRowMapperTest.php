<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use OCA\Tables\Db\ColumnTypes\DatetimeColumnQB;
use OCA\Tables\Db\ColumnTypes\NumberColumnQB;
use OCA\Tables\Db\ColumnTypes\SelectionColumnQB;
use OCA\Tables\Db\ColumnTypes\SuperColumnQB;
use OCA\Tables\Db\ColumnTypes\TextColumnQB;
use OCA\Tables\Helper\UserHelper;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class LegacyRowMapperTest extends TestCase {

	/**
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function testMigrateLegacyRow() {
		$data = [
			// assume column is a text column
			[
				'columnId' => 1,
				'value' => 'one'
			],
			// assume column is a number column
			[
				'columnId' => 2,
				'value' => 22.2
			],
			// assume column is a selection column
			[
				'columnId' => 3,
				'value' => 1
			],
			// assume columns are selection-check columns
			[
				'columnId' => 4,
				'value' => '"true"'
			],
			[
				'columnId' => 5,
				'value' => '"false"'
			],
			// assume columns are selection-multi columns
			[
				'columnId' => 6,
				'value' => '[1]'
			],
			[
				'columnId' => 7,
				'value' => '[2,3]'
			],
			[
				'columnId' => 8,
				'value' => 'null'
			],
			// assume columns are datetime columns
			[
				'columnId' => 9,
				'value' => '2023-12-24 10:00'
			],
			[
				'columnId' => 10,
				'value' => '2023-12-25'
			],
			[
				'columnId' => 11,
				'value' => '11:11'
			],
		];

		$dbConnection = Server::get(IDBConnection::class);
		$textColumnQb = $this->createMock(TextColumnQB::class);
		$selectionColumnQb = $this->createMock(SelectionColumnQB::class);
		$numberColumnQb = $this->createMock(NumberColumnQB::class);
		$datetimeColumnQb = $this->createMock(DatetimeColumnQB::class);
		$superColumnQb = $this->createMock(SuperColumnQB::class);
		$columnMapper = $this->createMock(ColumnMapper::class);
		$row2Mapper = $this->createMock(Row2Mapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$userHelper = $this->createMock(UserHelper::class);
		$legacyRowMapper = new LegacyRowMapper($dbConnection, $logger, $textColumnQb, $selectionColumnQb, $numberColumnQb, $datetimeColumnQb, $superColumnQb, $columnMapper, $userHelper, $row2Mapper);

		$legacyRow = new LegacyRow();
		$legacyRow->setId(5);
		$legacyRow->setTableId(10);
		$legacyRow->setCreatedBy('user1');
		$legacyRow->setCreatedAt('2023-12-24 09:00:00');
		$legacyRow->setLastEditAt('2023-12-24 09:30:00');
		$legacyRow->setLastEditBy('user1');
		$legacyRow->setDataArray($data);

		$row2 = $legacyRowMapper->migrateLegacyRow($legacyRow);
		$data2 = $row2->getData();

		self::assertTrue($data === $data2);
		self::assertTrue($legacyRow->jsonSerialize() === $row2->jsonSerialize());
	}
}
