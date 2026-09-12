<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ArchiveCleanupService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanupArchiveOverridesJobTest extends TestCase {
	private ArchiveCleanupService&MockObject $archiveCleanupService;
	private CleanupArchiveOverridesJob $job;

	protected function setUp(): void {
		parent::setUp();
		$this->archiveCleanupService = $this->createMock(ArchiveCleanupService::class);
		$this->job = new CleanupArchiveOverridesJob(
			$this->createMock(ITimeFactory::class),
			$this->archiveCleanupService,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function runJob(mixed $argument): void {
		$method = new \ReflectionMethod($this->job, 'run');
		$method->invoke($this->job, $argument);
	}

	public function testChecksEveryGivenUser(): void {
		$checked = [];
		$this->archiveCleanupService->expects($this->exactly(2))
			->method('removeOverrideIfStale')
			->willReturnCallback(function (string $userId, int $nodeType, int $nodeId) use (&$checked): void {
				$checked[] = [$userId, $nodeType, $nodeId];
			});

		$this->runJob([
			'nodeType' => Application::NODE_TYPE_TABLE,
			'nodeId' => 5,
			'userIds' => ['alice', 'bob'],
		]);

		$this->assertSame([
			['alice', Application::NODE_TYPE_TABLE, 5],
			['bob', Application::NODE_TYPE_TABLE, 5],
		], $checked);
	}

	public function testIgnoresAnEmptyUserList(): void {
		$this->archiveCleanupService->expects($this->never())->method('removeOverrideIfStale');

		$this->runJob([
			'nodeType' => Application::NODE_TYPE_CONTEXT,
			'nodeId' => 7,
			'userIds' => [],
		]);
	}

	public function testIgnoresAnUnsupportedNodeType(): void {
		$this->archiveCleanupService->expects($this->never())->method('removeOverrideIfStale');

		$this->runJob([
			'nodeType' => Application::NODE_TYPE_VIEW,
			'nodeId' => 9,
			'userIds' => ['alice'],
		]);
	}

	public function testIgnoresAMalformedArgument(): void {
		$this->archiveCleanupService->expects($this->never())->method('removeOverrideIfStale');

		$this->runJob('not-an-array');
		$this->runJob(['nodeType' => Application::NODE_TYPE_TABLE, 'userIds' => ['alice']]);
	}
}
