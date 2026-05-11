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

	/**
	 * The current state comes from View::jsonSerialize(), which always carries layout and
	 * viewSettings; a scheme written before they existed carries neither.
	 */
	public function testViewsWithoutLayoutKeysAreNotModified(): void {
		$current = $this->originalSchema;
		foreach ($current['views'] as &$view) {
			$view['layout'] = 'table';
			$view['viewSettings'] = ['cardBackgroundSource' => null, 'cardTitleSource' => null];
		}
		unset($view);

		$this->service->resolveChanges($current, $this->originalSchema);

		$this->assertSame([], $this->service->modifiedViews());
	}

	public function testAChangedLayoutMarksTheViewModified(): void {
		$update = $this->originalSchema;
		$update['views'][0]['layout'] = 'gallery';

		$this->service->resolveChanges($this->originalSchema, $update);

		$this->assertCount(1, $this->service->modifiedViews());
	}
}
