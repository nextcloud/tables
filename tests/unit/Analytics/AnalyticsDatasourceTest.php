<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Analytics\Datasource;

if (!interface_exists(IDatasource::class)) {
	interface IDatasource {
	}
}

namespace OCA\Tables\Analytics;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row2;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AnalyticsDatasourceTest extends TestCase {
	private IL10N|MockObject $l10n;
	private TableService|MockObject $tableService;
	private ViewService|MockObject $viewService;
	private ColumnService|MockObject $columnService;
	private RowService|MockObject $rowService;
	private AnalyticsDatasource $datasource;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')
			->willReturnCallback(static fn (string $text): string => $text);

		$this->tableService = $this->createMock(TableService::class);
		$this->viewService = $this->createMock(ViewService::class);
		$this->columnService = $this->createMock(ColumnService::class);
		$this->rowService = $this->createMock(RowService::class);

		$this->datasource = new AnalyticsDatasource(
			$this->l10n,
			$this->createMock(LoggerInterface::class),
			$this->tableService,
			$this->viewService,
			$this->columnService,
			$this->rowService,
			'user1',
		);
	}

	public function testReadDataAddsCountColumn(): void {
		$this->mockTableData();

		$result = $this->datasource->readData([
			'tableId' => '123',
			'user_id' => 'user1',
		]);

		self::assertSame(['Name', 'Amount', 'count'], $result['header']);
		self::assertSame(['Name', 'Amount'], $result['dimensions']);
		self::assertSame([
			['Alpha', 5, 1],
			['Beta', 7, 1],
		], $result['data']);
		self::assertSame(0, $result['error']);
	}

	public function testReadDataCanSelectCountColumn(): void {
		$this->mockTableData();

		$result = $this->datasource->readData([
			'tableId' => '123',
			'user_id' => 'user1',
			'columns' => '1,3',
		]);

		self::assertSame(['Name', 'count'], $result['header']);
		self::assertSame(['Name'], $result['dimensions']);
		self::assertSame([
			['Alpha', 1],
			['Beta', 1],
		], $result['data']);
	}

	public function testReadDataFormatsDisplayValuesForAnalytics(): void {
		$this->columnService
			->expects($this->once())
			->method('findAllByTable')
			->with(123, 'user1')
			->willReturn([
				$this->createColumn(1, 'Link', 'text', 'link'),
				$this->createColumn(2, 'Notes', 'text', 'rich'),
				$this->createSelectionColumn(3, 'Status', '', [
					['id' => 1, 'label' => 'Open'],
					['id' => 2, 'label' => 'Closed'],
				]),
				$this->createSelectionColumn(4, 'Tags', 'multi', [
					['id' => 1, 'label' => 'Important'],
					['id' => 2, 'label' => 'Later'],
				]),
				$this->createColumn(5, 'Done', 'selection', 'check'),
				$this->createColumn(6, 'People', 'usergroup'),
				$this->createColumn(7, 'Untitled link', 'text', 'link'),
			]);

		$row = new Row2();
		$row->setData([
			['columnId' => 1, 'value' => '{"title":"Issue","value":"https://example.test/issue","providerId":"url"}'],
			['columnId' => 2, 'value' => '<p>Hello <strong>world</strong></p>'],
			['columnId' => 3, 'value' => 2],
			['columnId' => 4, 'value' => [1, 2]],
			['columnId' => 5, 'value' => 'true'],
			['columnId' => 6, 'value' => [
				['id' => 'user1', 'type' => 0, 'displayName' => 'User One'],
				['id' => 'group1', 'type' => 1],
			]],
			['columnId' => 7, 'value' => '{"title":"","value":"https://example.test/plain","providerId":"url"}'],
		]);

		$this->rowService
			->expects($this->once())
			->method('findAllByTable')
			->with(123, 'user1', null, null)
			->willReturn([$row]);

		$result = $this->datasource->readData([
			'tableId' => '123',
			'user_id' => 'user1',
		]);

		self::assertSame([
			[
				'Issue (https://example.test/issue)',
				'Hello world',
				'Closed',
				'Important, Later',
				'true',
				'User One, group1',
				'https://example.test/plain',
				1,
			],
		], $result['data']);
	}

	public function testReadDataFormatsDefaultValuesForAnalytics(): void {
		$numberColumn = $this->createColumn(1, 'Amount', 'number');
		$numberColumn->setNumberDefault(10);
		$textColumn = $this->createColumn(2, 'Title', 'text', 'line');
		$textColumn->setTextDefault('<b>Fallback</b>');
		$selectionColumn = $this->createSelectionColumn(3, 'Status', '', [
			['id' => 1, 'label' => 'Open'],
		]);
		$selectionColumn->setSelectionDefault('1');
		$multiSelectionColumn = $this->createSelectionColumn(4, 'Tags', 'multi', [
			['id' => 1, 'label' => 'Important'],
			['id' => 2, 'label' => 'Later'],
		]);
		$multiSelectionColumn->setSelectionDefault('[1,2]');
		$checkColumn = $this->createColumn(5, 'Done', 'selection', 'check');
		$checkColumn->setSelectionDefault('false');
		$usergroupColumn = $this->createColumn(6, 'People', 'usergroup');
		$usergroupColumn->setUsergroupDefault('[{"id":"user1","type":0,"displayName":"User One"}]');

		$this->columnService
			->expects($this->once())
			->method('findAllByTable')
			->with(123, 'user1')
			->willReturn([
				$numberColumn,
				$textColumn,
				$selectionColumn,
				$multiSelectionColumn,
				$checkColumn,
				$usergroupColumn,
			]);

		$row = new Row2();
		$row->setData([]);

		$this->rowService
			->expects($this->once())
			->method('findAllByTable')
			->with(123, 'user1', null, null)
			->willReturn([$row]);

		$result = $this->datasource->readData([
			'tableId' => '123',
			'user_id' => 'user1',
		]);

		self::assertSame([
			[10.0, 'Fallback', 'Open', 'Important, Later', 'false', 'User One', 1],
		], $result['data']);
	}

	private function mockTableData(): void {
		$this->columnService
			->expects($this->once())
			->method('findAllByTable')
			->with(123, 'user1')
			->willReturn([
				$this->createColumn(1, 'Name', 'text'),
				$this->createColumn(2, 'Amount', 'number'),
			]);

		$this->rowService
			->expects($this->once())
			->method('findAllByTable')
			->with(123, 'user1', null, null)
			->willReturn([
				$this->createRow('Alpha', 5),
				$this->createRow('Beta', 7),
			]);
	}

	private function createColumn(int $id, string $title, string $type, string $subtype = ''): Column {
		$column = new Column();
		$column->setId($id);
		$column->setTitle($title);
		$column->setType($type);
		$column->setSubtype($subtype);
		return $column;
	}

	private function createSelectionColumn(int $id, string $title, string $subtype, array $options): Column {
		$column = $this->createColumn($id, $title, 'selection', $subtype);
		$column->setSelectionOptions(json_encode($options));
		return $column;
	}

	private function createRow(string $name, int $amount): Row2 {
		$row = new Row2();
		$row->setData([
			['columnId' => 1, 'value' => $name],
			['columnId' => 2, 'value' => $amount],
		]);
		return $row;
	}
}
