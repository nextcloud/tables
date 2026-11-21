<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

/**
 * Runs against the database: tables and views looked up by uuid.
 */
class UuidLookupMapperTest extends DatabaseTestCase {
	private const OWNER = 'uuid-lookup-mapper-test-owner';
	private const VIEW_UUID = '0195f1a0-0000-7000-8000-0000000000a1';

	private TableMapper $tableMapper;
	private ViewMapper $viewMapper;
	/** @var Table[] */
	private array $tables = [];
	/** @var View[] */
	private array $views = [];

	protected function setUp(): void {
		parent::setUp();
		$userHelper = $this->createMock(UserHelper::class);
		$this->tableMapper = new TableMapper($this->connectionAdapter, $userHelper);
		$this->viewMapper = new ViewMapper($this->connectionAdapter, $userHelper);
	}

	protected function tearDown(): void {
		foreach ($this->views as $view) {
			$this->viewMapper->delete($view);
		}
		foreach ($this->tables as $table) {
			$this->tableMapper->delete($table);
		}
		parent::tearDown();
	}

	public function testTablesAreFoundByUuidInOneLookup(): void {
		$first = $this->insertTable('0195f1a0-0000-7000-8000-0000000000b1');
		$second = $this->insertTable('0195f1a0-0000-7000-8000-0000000000b2');
		$this->insertTable('0195f1a0-0000-7000-8000-0000000000b3');

		$found = $this->tableMapper->findAllByUuids([$first->getUuid(), $second->getUuid(), $first->getUuid(), '0195f1a0-0000-7000-8000-0000000000bf']);

		$ids = array_map(static fn (Table $table): int => $table->getId(), $found);
		sort($ids);
		$this->assertSame([$first->getId(), $second->getId()], $ids);
	}

	public function testEveryViewWithTheUuidIsFoundWithTheOwnerOfItsTable(): void {
		$first = $this->insertView($this->insertTable('0195f1a0-0000-7000-8000-0000000000c1'));
		$second = $this->insertView($this->insertTable('0195f1a0-0000-7000-8000-0000000000c2'));

		$found = $this->viewMapper->findAllByUuids([self::VIEW_UUID]);

		$byId = [];
		foreach ($found as $view) {
			$byId[$view->getId()] = [$view->getTableId(), $view->getOwnership()];
		}
		ksort($byId);
		$this->assertSame([
			$first->getId() => [$first->getTableId(), self::OWNER],
			$second->getId() => [$second->getTableId(), self::OWNER],
		], $byId);
	}

	private function insertTable(string $uuid): Table {
		$table = new Table();
		$table->setUuid($uuid);
		$table->setTitle('Uuid lookup');
		$table->setOwnership(self::OWNER);
		$table->setCreatedBy(self::OWNER);
		$table->setCreatedAt('2026-01-01 00:00:00');
		$table->setLastEditBy(self::OWNER);
		$table->setLastEditAt('2026-01-01 00:00:00');
		$table->setArchived(false);
		$this->tables[] = $table = $this->tableMapper->insert($table);
		return $table;
	}

	private function insertView(Table $table): View {
		$view = new View();
		$view->setUuid(self::VIEW_UUID);
		$view->setTableId($table->getId());
		$view->setTitle('Open');
		$view->setDescription('');
		$view->setCreatedBy(self::OWNER);
		$view->setCreatedAt('2026-01-01 00:00:00');
		$view->setLastEditBy(self::OWNER);
		$view->setLastEditAt('2026-01-01 00:00:00');
		$this->views[] = $view = $this->viewMapper->insert($view);
		return $view;
	}
}
