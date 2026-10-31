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
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RelationService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

final class RelationServiceTest extends TestCase {
	private ColumnMapper $columnMapper;
	private ViewMapper $viewMapper;
	private Row2Mapper $rowMapper;
	private ColumnService $columnService;
	private PermissionsService $permissionsService;
	private RelationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->columnMapper = $this->createMock(ColumnMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->rowMapper = $this->createMock(Row2Mapper::class);
		$this->columnService = $this->createMock(ColumnService::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->service = new RelationService(
			$this->columnMapper,
			$this->viewMapper,
			$this->rowMapper,
			$this->columnService,
			$this->permissionsService,
			'viewer',
		);
	}

	public function testTypedTableRelationsPreserveResponseAndCache(): void {
		$relation = $this->relation(7);
		$secondRelation = $this->relation(8);
		$this->columnService->method('findAllByTable')->with(1)->willReturn([$relation, $secondRelation]);
		$this->permissionsService->expects($this->once())->method('canReadRelationTarget')->with('table', 2, 'owner')->willReturn(true);
		$this->columnMapper->method('find')->with(20)->willReturn($this->label());
		$row = new Row2();
		$row->setId(42);
		$row->addCell(20, 'Alice');
		$this->rowMapper->expects($this->once())->method('findAll')->with([20], 2, null, null, null, null, 'viewer')->willReturn([$row]);

		$values = [42 => ['id' => 42, 'label' => 'Alice']];
		$this->assertSame([7 => $values, 8 => $values], $this->service->getRelationsForTable(1));
		$this->assertSame($values, $this->service->getRelationData($relation));
	}

	public function testTypedViewRelationsPreserveFiltersAndViewerIdentity(): void {
		$relation = $this->relation(7, ['relationType' => 'view', 'targetId' => '3']);
		$this->columnService->method('findAllByView')->with(4)->willReturn([$relation]);
		$this->permissionsService->expects($this->once())->method('canReadRelationTarget')->with('view', 3, 'owner')->willReturn(true);
		$this->columnMapper->method('find')->with(20)->willReturn($this->label());
		$view = new View();
		$view->setTableId(2);
		$view->setColumns(json_encode([['columnId' => 20, 'order' => 0]]));
		$view->setFilter(json_encode([[['columnId' => 20, 'operator' => 'is-not-empty', 'value' => '']]]));
		$this->viewMapper->method('find')->with(3)->willReturn($view);
		$this->rowMapper->expects($this->once())->method('findAll')
			->with([20], 2, null, null, $view->getFilterArray(), $view->getSortArray(), 'viewer')->willReturn([]);

		$this->assertSame([7 => []], $this->service->getRelationsForView(4));
	}

	public function testTargetAuthorsRemainSeparateInTheCache(): void {
		$allowed = $this->relation(7);
		$denied = $this->relation(8, ['targetSetBy' => 'former-owner']);
		$this->permissionsService->expects($this->exactly(2))->method('canReadRelationTarget')
			->willReturnCallback(fn (string $type, int $id, ?string $userId): bool => $userId === 'owner');
		$this->columnMapper->method('find')->with(20)->willReturn($this->label());
		$row = new Row2();
		$row->setId(42);
		$row->addCell(20, 'Alice');
		$this->rowMapper->expects($this->once())->method('findAll')->willReturn([$row]);

		$this->assertSame([42 => ['id' => 42, 'label' => 'Alice']], $this->service->getRelationData($allowed));
		$this->assertSame([], $this->service->getRelationData($denied));
		$this->assertSame([], $this->service->getRelationData($denied));
	}

	public function testMalformedRelationsRemainUnavailableWithoutFetchingRows(): void {
		$this->permissionsService->expects($this->never())->method('canReadRelationTarget');
		$this->rowMapper->expects($this->never())->method('findAll');
		$invalid = $this->relation(7, ['targetId' => true]);
		$this->columnService->method('findAllByTable')->with(1)->willReturn([$invalid]);

		$this->assertSame([], $this->service->getRelationsForTable(1));
		$this->assertSame([], $this->service->getRelationData($invalid));
		$this->assertSame([], $this->service->getRelationData($this->label()));
	}

	public function testMissingLabelColumnsRemainUnavailableWithoutFetchingRows(): void {
		$this->permissionsService->method('canReadRelationTarget')->willReturn(true);
		$this->columnMapper->method('find')->with(20)->willThrowException(new DoesNotExistException('Missing label column.'));
		$this->rowMapper->expects($this->never())->method('findAll');

		$this->assertSame([], $this->service->getRelationData($this->relation(7)));
	}

	private function relation(int $id, array $settings = []): Column {
		$column = new Column();
		$column->setId($id);
		$column->setType(Column::TYPE_RELATION);
		$column->setCustomSettings(json_encode(array_replace(['relationType' => 'table', 'targetId' => '2', 'labelColumn' => '20', 'targetSetBy' => 'owner'], $settings)));
		return $column;
	}

	private function label(): Column {
		$column = new Column();
		$column->setId(20);
		$column->setType(Column::TYPE_TEXT);
		return $column;
	}
}
