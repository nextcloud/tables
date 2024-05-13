<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use OCA\Tables\Db\ColumnTypes\DatetimeColumnQB;
use OCA\Tables\Db\ColumnTypes\NumberColumnQB;
use OCA\Tables\Db\ColumnTypes\SelectionColumnQB;
use OCA\Tables\Db\ColumnTypes\SuperColumnQB;
use OCA\Tables\Db\ColumnTypes\TextColumnQB;
use OCA\Tables\Db\ColumnTypes\UsergroupColumnQB;
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
		$data = [];
		$columns = [];

		$data[] = ['columnId' => 1,	'value' => 'one'];
		$col = new Column();
		$col->setId(1);
		$col->setType('text');
		$col->setSubtype('line');
		$columns[] = $col;

		$data[] = ['columnId' => 2,	'value' => 22.2];
		$col = new Column();
		$col->setId(2);
		$col->setType('number');
		$columns[] = $col;

		$data[] = ['columnId' => 3,	'value' => 1];
		$col = new Column();
		$col->setId(3);
		$col->setType('selection');
		$columns[] = $col;

		$data[] = ['columnId' => 12,	'value' => '2'];
		$col = new Column();
		$col->setId(12);
		$col->setType('selection');
		$columns[] = $col;

		$data[] = ['columnId' => 4,	'value' => '"true"'];
		$col = new Column();
		$col->setId(4);
		$col->setType('selection');
		$col->setSubtype('check');
		$columns[] = $col;

		$data[] = ['columnId' => 5, 'value' => '"false"'];
		$col = new Column();
		$col->setId(5);
		$col->setType('selection');
		$col->setSubtype('check');
		$columns[] = $col;

		$data[] = ['columnId' => 6,	'value' => '[1]'];
		$col = new Column();
		$col->setId(6);
		$col->setType('selection');
		$col->setSubtype('multi');
		$columns[] = $col;

		$data[] = ['columnId' => 7,	'value' => '[2,3]'];
		$col = new Column();
		$col->setId(7);
		$col->setType('selection');
		$col->setSubtype('multi');
		$columns[] = $col;

		$data[] = ['columnId' => 8,	'value' => 'null'];
		$col = new Column();
		$col->setId(8);
		$col->setType('selection');
		$col->setSubtype('multi');
		$columns[] = $col;

		$data[] = ['columnId' => 9,	'value' => '2023-12-24 10:00'];
		$col = new Column();
		$col->setId(9);
		$col->setType('datetime');
		$columns[] = $col;

		$data[] = ['columnId' => 10, 'value' => '2023-12-25'];
		$col = new Column();
		$col->setId(10);
		$col->setType('datetime');
		$col->setSubtype('date');
		$columns[] = $col;

		$data[] = ['columnId' => 11, 'value' => '11:11'];
		$col = new Column();
		$col->setId(11);
		$col->setType('datetime');
		$col->setSubtype('time');
		$columns[] = $col;

		$dbConnection = Server::get(IDBConnection::class);
		$textColumnQb = $this->createMock(TextColumnQB::class);
		$selectionColumnQb = $this->createMock(SelectionColumnQB::class);
		$numberColumnQb = $this->createMock(NumberColumnQB::class);
		$datetimeColumnQb = $this->createMock(DatetimeColumnQB::class);
		$usergroupColumnQb = $this->createMock(UsergroupColumnQB::class);
		$superColumnQb = $this->createMock(SuperColumnQB::class);
		$columnMapper = $this->createMock(ColumnMapper::class);
		$row2Mapper = $this->createMock(Row2Mapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$userHelper = $this->createMock(UserHelper::class);
		$legacyRowMapper = new LegacyRowMapper($dbConnection, $logger, $textColumnQb, $selectionColumnQb, $numberColumnQb, $datetimeColumnQb, $usergroupColumnQb, $superColumnQb, $columnMapper, $userHelper, $row2Mapper);

		$legacyRow = new LegacyRow();
		$legacyRow->setId(5);
		$legacyRow->setTableId(10);
		$legacyRow->setCreatedBy('user1');
		$legacyRow->setCreatedAt('2023-12-24 09:00:00');
		$legacyRow->setLastEditAt('2023-12-24 09:30:00');
		$legacyRow->setLastEditBy('user1');
		$legacyRow->setDataArray($data);

		$row2 = $legacyRowMapper->migrateLegacyRow($legacyRow, $columns);
		$data2 = $row2->getData();

		self::assertTrue($data === $data2);
		self::assertTrue($legacyRow->jsonSerialize() === $row2->jsonSerialize());
	}
}
