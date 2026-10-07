<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Activity;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ShareService;
use OCP\Activity\IManager;
use OCP\Cache\CappedMemoryCache;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

class ActivityManagerTest extends TestCase {
	public function testAViewWithoutTableProducesNoActivity(): void {
		$activityApi = $this->createMock(IManager::class);
		$activityApi->expects($this->never())->method('publish');
		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->expects($this->never())->method('find');

		$manager = new ActivityManager(
			$activityApi,
			$this->createMock(IFactory::class),
			$tableMapper,
			$this->createMock(ViewMapper::class),
			$this->createMock(ColumnMapper::class),
			$this->createMock(ShareService::class),
			new CappedMemoryCache(),
			'alice',
		);
		$view = new View();
		$view->setId(6);
		$view->setTitle('Intake home');
		$view->setTableId(null);
		$view->setCreatedBy('alice');

		$manager->triggerEvent(ActivityManager::TABLES_OBJECT_VIEW, $view, ActivityManager::SUBJECT_VIEW_CREATE, [], 'alice');
	}
}
