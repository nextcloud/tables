<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RelationService;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RelationServiceTest extends TestCase {
	private ColumnMapper $columnMapper;
	private ViewMapper $viewMapper;
	private Row2Mapper $rowMapper;
	private ColumnService $columnService;
	private RelationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->columnMapper = $this->createMock(ColumnMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->rowMapper = $this->createMock(Row2Mapper::class);
		$this->columnService = $this->createMock(ColumnService::class);
		$this->service = new RelationService(
			$this->columnMapper,
			$this->viewMapper,
			$this->rowMapper,
			$this->columnService,
			$this->createMock(IUserSession::class),
			$this->createMock(LoggerInterface::class),
		);
	}

	private function column(int $id, int $tableId, string $type, array $settings = []): Column {
		$column = new Column();
		$column->setId($id);
		$column->setTableId($tableId);
		$column->setType($type);
		$column->setSubtype($type === Column::TYPE_TEXT ? 'line' : '');
		$column->setTitle('Column ' . $id);
		$column->setCustomSettings(json_encode($settings));
		return $column;
	}

	private function configureColumns(array $columns): void {
		$columnsMap = [];
		foreach ($columns as $column) {
			$columnsMap[$column->getId()] = $column;
		}
		$this->columnMapper->method('findAll')->willReturnCallback(
			fn (array $ids) => array_values(array_intersect_key($columnsMap, array_flip($ids)))
		);
		$this->columnMapper->method('find')->willReturnCallback(fn (int $id) => $columnsMap[$id]);
	}

	public function testLookupsBatchTargetRowsAndKeepRawValuesSeparateFromLabels(): void {
		$relation = $this->column(7, 1, Column::TYPE_RELATION, ['relationType' => 'table', 'targetId' => 2, 'labelColumn' => 20]);
		$labelLookup = $this->column(8, 1, Column::TYPE_LOOKUP, ['relationColumnId' => 7, 'targetColumnId' => 20]);
		$textLookup = $this->column(9, 1, Column::TYPE_LOOKUP, ['relationColumnId' => 7, 'targetColumnId' => 21]);
		$zeroLookup = $this->column(10, 1, Column::TYPE_LOOKUP, ['relationColumnId' => 7, 'targetColumnId' => 22]);
		$label = $this->column(20, 2, Column::TYPE_NUMBER);
		$label->setNumberPrefix('$');
		$label->setNumberSuffix(' USD');
		$label->setNumberDecimals(2);
		$this->configureColumns([$relation, $label, $this->column(21, 2, Column::TYPE_TEXT), $this->column(22, 2, Column::TYPE_NUMBER)]);
		$this->columnService->method('findAllByTable')->with(1)->willReturn([$relation, $labelLookup, $textLookup, $zeroLookup]);

		$row = new Row2();
		$row->setId(42);
		$row->addCell(20, 12.5);
		$row->addCell(21, 'Fruit');
		$row->addCell(22, 0);
		$emptyLabelRow = new Row2();
		$emptyLabelRow->setId(43);
		$emptyLabelRow->addCell(21, 'Berry');
		$this->rowMapper->expects($this->once())->method('findAll')->with(
			$this->callback(function (array $ids): bool {
				sort($ids);
				return $ids === [20, 21, 22];
			}), 2, null, null, null, null, ''
		)->willReturn([$row, $emptyLabelRow]);

		$result = $this->service->getRelationsForTable(1);
		$this->assertSame('$12.50 USD', $result[7]['values'][42]['value']);
		$this->assertSame(12.5, $result[8]['values'][42]['value']);
		$this->assertSame(0, $result[10]['values'][42]['value']);
		$this->assertSame('Berry', $result[9]['values'][43]['value']);
		$this->assertNull($result[8]['values'][43]['value']);
		$this->assertSame($result, $this->service->getRelationsForTable(1));
	}

	public function testLookupOnlySourceViewIncludesItsHiddenRelationDependency(): void {
		$relation = $this->column(7, 1, Column::TYPE_RELATION, ['relationType' => 'view', 'targetId' => 3, 'labelColumn' => 20]);
		$lookup = $this->column(8, 1, Column::TYPE_LOOKUP, ['relationColumnId' => 7, 'targetColumnId' => 21]);
		$this->configureColumns([$relation, $this->column(21, 2, Column::TYPE_TEXT)]);
		$this->columnService->method('findAllByView')->with(4)->willReturn([$lookup]);
		$view = new View();
		$view->setId(3);
		$view->setTableId(2);
		$view->setFilter(json_encode([[['columnId' => 20, 'operator' => 'is-not-empty', 'value' => '']]]));
		$this->viewMapper->method('find')->with(3)->willReturn($view);
		$row = new Row2();
		$row->setId(42);
		$row->addCell(21, 'Berlin');
		$this->rowMapper->expects($this->once())->method('findAll')
			->with([21], 2, null, null, $view->getFilterArray(), $view->getSortArray(), '')
			->willReturn([$row]);

		$result = $this->service->getRelationsForView(4);
		$this->assertSame([8], array_keys($result));
		$this->assertSame('Berlin', $result[8]['values'][42]['value']);
	}

	public function testMissingUnsupportedOrForeignDependenciesReturnUnavailableEntries(): void {
		$foreignRelation = $this->column(7, 99, Column::TYPE_RELATION, ['relationType' => 'table', 'targetId' => 2, 'labelColumn' => 20]);
		$foreignLookup = $this->column(8, 1, Column::TYPE_LOOKUP, ['relationColumnId' => 7, 'targetColumnId' => 21]);
		$missingLookup = $this->column(9, 1, Column::TYPE_LOOKUP, ['relationColumnId' => 70, 'targetColumnId' => 21]);
		$invalidLookup = $this->column(10, 1, Column::TYPE_LOOKUP);
		$localRelation = $this->column(11, 1, Column::TYPE_RELATION, ['relationType' => 'table', 'targetId' => 2, 'labelColumn' => 20]);
		$unsupportedLookup = $this->column(12, 1, Column::TYPE_LOOKUP, ['relationColumnId' => 11, 'targetColumnId' => 23]);
		$this->configureColumns([$foreignRelation, $localRelation, $this->column(21, 2, Column::TYPE_TEXT), $this->column(23, 2, Column::TYPE_RELATION)]);
		$this->columnService->method('findAllByTable')->willReturn([$foreignLookup, $missingLookup, $invalidLookup, $unsupportedLookup]);
		$this->rowMapper->expects($this->never())->method('findAll');

		$result = $this->service->getRelationsForTable(1);
		foreach ([8, 9, 10, 12] as $id) {
			$this->assertSame(['column' => null, 'values' => []], $result[$id]);
		}
	}
}
