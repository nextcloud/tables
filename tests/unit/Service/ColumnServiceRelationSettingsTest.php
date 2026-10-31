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
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;
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

final class ColumnServiceRelationSettingsTest extends TestCase {
	private ColumnMapper $mapper;
	private ViewService $viewService;
	private PermissionsService $permissionsService;
	private ColumnService $service;
	private Column $relation;
	private Column $label;

	protected function setUp(): void {
		parent::setUp();
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('canUpdateColumnsByTableId')->willReturn(true);
		$this->permissionsService->method('canCreateColumns')->willReturn(true);
		$this->mapper = $this->createMock(ColumnMapper::class);
		$this->mapper->method('update')->willReturnArgument(0);
		$this->viewService = $this->createMock(ViewService::class);
		$table = new Table();
		$table->setId(1);
		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('find')->willReturn($table);
		$this->service = new ColumnService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			'editor',
			$this->mapper,
			$tableMapper,
			$this->viewService,
			$this->createMock(RowService::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(NotificationHelper::class),
			$this->createMock(IL10N::class),
			$this->createMock(UserHelper::class),
			$this->createMock(ColumnDtoValidator::class),
			$this->createMock(AuditLogServiceInterface::class),
		);
		$this->relation = new Column();
		$this->relation->setId(7);
		$this->relation->setTableId(1);
		$this->relation->setType(Column::TYPE_RELATION);
		$this->relation->setCreatedBy('owner');
		$this->relation->setCustomSettings(json_encode($this->settings()));
		$this->label = new Column();
		$this->label->setId(20);
		$this->label->setTableId(2);
		$this->label->setType(Column::TYPE_TEXT);
		$this->label->setSubtype('line');
		$this->mapper->method('find')->willReturnMap([
			[7, $this->relation],
			[20, $this->label],
		]);
	}

	public function testPresentationUpdatePreservesTheTargetAuthor(): void {
		$this->permissionsService->expects($this->never())->method('canReadRelationTarget');
		$settings = array_replace($this->settings(), ['targetSetBy' => 'editor', 'width' => 200]);

		$updated = $this->service->update(7, 'editor', new ColumnDto(customSettings: json_encode($settings)));

		$this->assertSame('owner', $updated->getCustomSettingsArray()['targetSetBy']);
		$this->assertSame(200, $updated->getCustomSettingsArray()['width']);
	}

	/**
	 * @dataProvider changedTargetProvider
	 */
	public function testChangedTargetRequiresReadAccess(array $changes, int $targetId): void {
		$this->permissionsService->expects($this->once())->method('canReadRelationTarget')->with('table', $targetId, 'editor')->willReturn(false);
		$this->mapper->expects($this->never())->method('update');
		$this->expectException(PermissionError::class);

		$this->service->update(7, 'editor', new ColumnDto(customSettings: json_encode(array_replace($this->settings(), $changes))));
	}

	public static function changedTargetProvider(): array {
		return [
			'target table changes' => [['targetId' => 3], 3],
			'label column changes' => [['labelColumn' => 21], 2],
		];
	}

	public function testRelationCreationRejectsLabelsFromAnotherTable(): void {
		$this->permissionsService->method('canReadRelationTarget')->willReturn(true);
		$this->label->setTableId(99);
		$this->mapper->expects($this->never())->method('insert');
		$this->expectException(BadRequestError::class);

		$this->service->create('editor', 1, null, new ColumnDto(title: 'Relation', type: Column::TYPE_RELATION, customSettings: json_encode($this->settings())));
	}

	public function testPresentationUpdateDoesNotRevalidateAnUnchangedTargetView(): void {
		$this->permissionsService->expects($this->never())->method('canReadRelationTarget');
		$this->viewService->expects($this->never())->method('find');
		$this->mapper->expects($this->once())->method('find')->with(7);
		$settings = array_replace($this->settings(), ['relationType' => 'view', 'targetId' => 3]);
		$this->relation->setCustomSettings(json_encode($settings));
		$settings['width'] = 200;

		$updated = $this->service->update(7, 'editor', new ColumnDto(title: 'Renamed', customSettings: json_encode($settings)));

		$this->assertSame('Renamed', $updated->getTitle());
		$this->assertEquals($settings, $updated->getCustomSettingsArray());
	}

	public function testChangedTargetRejectsLabelsHiddenFromTheTargetView(): void {
		$this->permissionsService->method('canReadRelationTarget')->willReturn(true);
		$view = new View();
		$view->setTableId(2);
		$view->setColumns(json_encode([['columnId' => 21, 'order' => 0]]));
		$this->viewService->method('find')->with(3, true, 'editor')->willReturn($view);
		$this->mapper->expects($this->never())->method('update');
		$this->expectException(BadRequestError::class);

		$this->service->update(7, 'editor', new ColumnDto(customSettings: json_encode(array_replace($this->settings(), ['relationType' => 'view', 'targetId' => 3]))));
	}

	public function testRelationUpdateRejectsSubmittedMalformedSettings(): void {
		$this->mapper->expects($this->never())->method('update');
		$this->expectException(BadRequestError::class);

		$this->service->update(7, 'editor', new ColumnDto(customSettings: json_encode(['relationType' => 'table', 'targetId' => true, 'labelColumn' => 20])));
	}

	public function testRepairingMalformedStoredSettingsRequiresAuthorization(): void {
		$this->relation->setCustomSettings(json_encode(['relationType' => 'table', 'targetId' => true, 'labelColumn' => 20]));
		$this->permissionsService->expects($this->once())->method('canReadRelationTarget')->with('table', 2, 'editor')->willReturn(true);

		$updated = $this->service->update(7, 'editor', new ColumnDto(customSettings: json_encode($this->settings())));

		$this->assertSame('editor', $updated->getCustomSettingsArray()['targetSetBy']);
	}

	public function testChangedTargetStoresTheEditorAsAuthorAndPreservesOtherSettings(): void {
		$this->permissionsService->expects($this->once())->method('canReadRelationTarget')->with('table', 3, 'editor')->willReturn(true);
		$this->label->setTableId(3);
		$settings = array_replace($this->settings(), ['targetId' => 3, 'targetSetBy' => 'spoofed', 'width' => 200]);

		$updated = $this->service->update(7, 'editor', new ColumnDto(customSettings: json_encode($settings)));

		$settings['targetSetBy'] = 'editor';
		$this->assertEquals($settings, $updated->getCustomSettingsArray());
	}

	public function testPresentationUpdateDoesNotAssignAnAuthorToALegacyRelation(): void {
		$this->permissionsService->expects($this->never())->method('canReadRelationTarget');
		$settings = $this->settings();
		unset($settings['targetSetBy']);
		$this->relation->setCustomSettings(json_encode($settings));
		$settings['width'] = 200;

		$updated = $this->service->update(7, 'editor', new ColumnDto(customSettings: json_encode(array_replace($settings, ['targetSetBy' => 'spoofed']))));

		$this->assertSame($settings, $updated->getCustomSettingsArray());
	}

	public function testTextLinkSettingsRemainUnchanged(): void {
		$this->permissionsService->expects($this->never())->method('canReadRelationTarget');
		$this->relation->setType(Column::TYPE_TEXT);
		$this->relation->setSubtype('link');
		$settings = ['width' => 200, 'showPreview' => true, 'imagePreviewSize' => 96];

		$updated = $this->service->update(7, 'editor', new ColumnDto(customSettings: json_encode($settings)));

		$this->assertSame($settings, $updated->getCustomSettingsArray());
	}

	private function settings(): array {
		return ['relationType' => 'table', 'targetId' => 2, 'labelColumn' => 20, 'targetSetBy' => 'owner'];
	}
}
