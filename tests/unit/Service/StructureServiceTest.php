<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Service\StructureService;
use OCA\Tables\Service\TableService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StructureServiceTest extends TestCase {
	private readonly StructureService $service;
	private readonly array $originalSchema;
	private readonly TableService|MockObject $tableService;

	public function setUp(): void {
		$this->tableService = $this->createMock(TableService::class);

		$this->originalSchema = json_decode(file_get_contents(__DIR__ . '/../res/feature-list-schema.json'), true);

		$this->service = new StructureService($this->tableService);
	}

	public function testNoChanges() {
		$this->service->resolveChanges($this->originalSchema, $this->originalSchema);
		$this->assertSame([], $this->service->addedColumns());
		$this->assertSame([], $this->service->removedColumns());
		$this->assertSame([], $this->service->modifiedColumns());
	}
}
