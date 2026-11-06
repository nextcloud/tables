<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Dto\LookupSettings;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\ViewService;
use OCA\Tables\Validation\ColumnDtoValidator;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ColumnServiceLookupPermissionsTest extends TestCase {
	private ColumnMapper $mapper;
	private PermissionsService $permissionsService;
	private ColumnService $service;
	private Column $lookup;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('canUpdateColumnsByTableId')->willReturn(true);
		$this->mapper = $this->createMock(ColumnMapper::class);
		$this->mapper->method('update')->willReturnArgument(0);

		$this->service = new ColumnService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			'editor',
			$this->mapper,
			$this->createMock(TableMapper::class),
			$this->createMock(ViewService::class),
			$this->createMock(RowService::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(NotificationHelper::class),
			$this->createMock(IL10N::class),
			$this->createMock(UserHelper::class),
			$this->createMock(ColumnDtoValidator::class),
			$this->createMock(AuditLogServiceInterface::class),
			$this->createMock(ViewMapper::class),
		);

		$this->lookup = $this->column(9, 1, Column::TYPE_LOOKUP);
		$this->lookup->setCustomSettings($this->lookupDto()->getCustomSettings());
		$relation = $this->column(7, 1, Column::TYPE_RELATION);
		$relation->setCustomSettings(json_encode([
			'relationType' => 'table',
			'targetId' => 2,
			'labelColumn' => 20,
			'targetSetBy' => 'owner',
		]));
		$target = $this->column(21, 2, Column::TYPE_TEXT);
		$this->mapper->method('find')->willReturnCallback(fn (int $id): Column => match ($id) {
			7 => $relation,
			9 => $this->lookup,
			21 => $target,
		});
	}

	public function testConvertingColumnToLookupRequiresReadAccess(): void {
		$this->lookup->setType(Column::TYPE_TEXT);

		$this->assertUpdateDenied();
	}

	public function testRepairingInvalidLookupRequiresReadAccess(): void {
		$this->lookup->setCustomSettings(json_encode(['relationColumnId' => true, 'targetColumnId' => 21]));

		$this->assertUpdateDenied();
	}

	public function testRepairingInvalidLookupAllowsReadableTarget(): void {
		$this->lookup->setCustomSettings('{}');
		$this->permissionsService->expects($this->once())->method('canReadRelationTarget')
			->with('table', 2, 'editor')->willReturn(true);
		$this->mapper->expects($this->once())->method('update');

		$updated = $this->service->update(9, 'editor', $this->lookupDto());

		$this->assertSame(21, $updated->getCustomSettingsObject(LookupSettings::class)->targetColumnId);
	}

	public function testPresentationUpdateDoesNotRequireTargetAccess(): void {
		$this->permissionsService->expects($this->never())->method('canReadRelationTarget');
		$this->mapper->expects($this->once())->method('update');
		$settings = ['relationColumnId' => '7', 'targetColumnId' => '21', 'width' => 200];

		$updated = $this->service->update(9, 'editor', new ColumnDto(customSettings: json_encode($settings)));

		$this->assertSame($settings, json_decode($updated->getCustomSettings(), true));
	}

	private function assertUpdateDenied(): void {
		$this->permissionsService->expects($this->once())->method('canReadRelationTarget')
			->with('table', 2, 'editor')->willReturn(false);
		$this->mapper->expects($this->never())->method('update');
		$this->expectException(PermissionError::class);

		$this->service->update(9, 'editor', $this->lookupDto());
	}

	private function lookupDto(): ColumnDto {
		return new ColumnDto(
			type: Column::TYPE_LOOKUP,
			customSettings: json_encode(['relationColumnId' => 7, 'targetColumnId' => 21]),
		);
	}

	private function column(int $id, int $tableId, string $type): Column {
		$column = new Column();
		$column->setId($id);
		$column->setTableId($tableId);
		$column->setType($type);
		$column->setSubtype($type === Column::TYPE_TEXT ? 'line' : '');
		$column->setCreatedBy('owner');
		$column->setLastEditBy('owner');
		return $column;
	}
}
