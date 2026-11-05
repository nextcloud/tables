<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Controller\RowOCSController;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\View;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Model\RowDataInput;
use OCA\Tables\Model\RowIdentifier;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RowOCSControllerTest extends TestCase {
	private const UUID = '019f90c5-8576-7f0b-9b14-9bf49b2826cf';
	private RowService $rows;
	private FederationService $federation;
	private TableService $tables;
	private ViewService $views;
	private RowOCSController $controller;

	protected function setUp(): void {
		$this->rows = $this->createMock(RowService::class);
		$this->federation = $this->createMock(FederationService::class);
		$this->tables = $this->createMock(TableService::class);
		$this->views = $this->createMock(ViewService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->controller = new RowOCSController($this->createMock(IRequest::class), new NullLogger(), $l10n, 'alice', $this->rows, $this->tables, $this->views, $this->federation);
	}

	private function row(): Row2 {
		$row = new Row2();
		$row->setId(13);
		$row->setTableId(5);
		$row->setUuid(self::UUID);
		return $row;
	}

	/** @dataProvider localOperations */
	public function testLocalItemOperationsConvergeOnStoredIdentity(string $method, string $collection, string $identifier): void {
		$row = $this->row();
		$nodeType = $collection === 'views' ? Application::NODE_TYPE_VIEW : Application::NODE_TYPE_TABLE;
		$matchesIdentifier = $this->callback(fn (RowIdentifier $id) => $id->value === RowIdentifier::fromString($identifier)->value);
		if ($method === 'getRow') {
			$this->rows->expects($this->once())->method('findByIdentifier')->with($matchesIdentifier, $nodeType, 5, 'alice')->willReturn($row);
		} else {
			$this->rows->expects($this->never())->method('findByIdentifier');
			$this->rows->expects($this->once())->method('resolveRowId')->with($matchesIdentifier, $nodeType, 5, 'alice')->willReturn(13);
		}
		if ($method === 'updateRow') {
			$this->rows->expects($this->once())->method('updateRowData')->with(13, $collection === 'views' ? 5 : null, $this->isInstanceOf(RowDataInput::class), 'alice', $collection === 'tables' ? 5 : null)->willReturn($row);
		} elseif ($method === 'deleteRow') {
			$this->rows->expects($this->once())->method('delete')->with(13, $collection === 'views' ? 5 : null, 'alice', $collection === 'tables' ? 5 : null)->willReturn($row);
		}
		$response = $method === 'updateRow'
			? $this->controller->updateRow($collection, 5, $identifier, [])
			: $this->controller->$method($collection, 5, $identifier);
		$this->assertSame(200, $response->getStatus());
		$this->assertSame(self::UUID, $response->getData()['uuid']);
		$this->assertSame(13, $response->getData()['id']);
	}

	public static function localOperations(): array {
		$cases = [];
		foreach (['getRow', 'updateRow', 'deleteRow'] as $method) {
			foreach (['tables', 'views'] as $collection) {
				foreach (['00013', strtoupper(self::UUID)] as $identifier) {
					$cases[] = [$method, $collection, $identifier];
				}
			}
		}
		return $cases;
	}

	/** @dataProvider invalidOperations */
	public function testInvalidIdentifiersReturnBadRequestBeforeResolving(string $method): void {
		$this->rows->expects($this->never())->method('findByIdentifier');
		$this->rows->expects($this->never())->method('resolveRowId');
		$this->federation->expects($this->never())->method('isNodeFederated');
		$response = $method === 'updateRow' ? $this->controller->updateRow('tables', 5, 'not-a-row', []) : $this->controller->$method('tables', 5, 'not-a-row');
		$this->assertSame(400, $response->getStatus());
	}

	public static function invalidOperations(): array {
		return [['getRow'], ['updateRow'], ['deleteRow']];
	}

	public function testMalformedUpdateCellsReturnBadRequestWithoutLoadingRow(): void {
		$this->rows->expects($this->never())->method('resolveRowId');
		$this->assertSame(400, $this->controller->updateRow('tables', 5, '13', ['column_1' => 'value'])->getStatus());
		$this->assertSame(400, $this->controller->updateRow('tables', 5, '13', [['columnId' => 1]])->getStatus());
	}

	/** @dataProvider federatedOperations */
	public function testFederatedBoundary(string $method, string $collection, bool $uuid): void {
		$this->federation->method('isNodeFederated')->willReturn(true);
		$this->rows->expects($this->never())->method('findByIdentifier');
		$this->rows->expects($this->never())->method('resolveRowId');
		$forward = !$uuid && $method !== 'getRow';
		if ($forward) {
			$node = $collection === 'tables' ? new Table() : new View();
			($collection === 'tables' ? $this->tables : $this->views)->method('find')->willReturn($node);
			if ($method === 'updateRow') {
				$this->federation->expects($this->once())->method($method)->with($node, 13, [])->willReturn(['id' => 13]);
			} else {
				$this->federation->expects($this->once())->method($method)->with($node, 13)->willReturn(['id' => 13]);
			}
		} else {
			$this->federation->expects($this->never())->method('updateRow');
			$this->federation->expects($this->never())->method('deleteRow');
		}
		$identifier = $uuid ? self::UUID : '13';
		$response = $method === 'updateRow' ? $this->controller->updateRow($collection, 5, $identifier, []) : $this->controller->$method($collection, 5, $identifier);
		$this->assertSame($forward ? 200 : 400, $response->getStatus());
		if ($forward) {
			$this->assertArrayHasKey('uuid', $response->getData());
			$this->assertNull($response->getData()['uuid']);
		}
	}

	public static function federatedOperations(): array {
		$cases = [];
		foreach (['getRow', 'updateRow', 'deleteRow'] as $method) {
			foreach (['tables', 'views'] as $collection) {
				foreach ([false, true] as $uuid) {
					$cases[] = [$method, $collection, $uuid];
				}
			}
		}
		return $cases;
	}

	/** @dataProvider serviceFailures */
	public function testServiceErrorsMapToHttpStatus(\Exception $error, int $status): void {
		$this->rows->method('findByIdentifier')->willThrowException($error);
		$this->assertSame($status, $this->controller->getRow('tables', 5, self::UUID)->getStatus());
	}

	public static function serviceFailures(): array {
		return [
			[new NotFoundError('Missing'), 404],
			[new PermissionError('Denied'), 403],
			[new \RuntimeException('Failed'), 500],
			[new \InvalidArgumentException('Stored state is invalid'), 500],
		];
	}
}
