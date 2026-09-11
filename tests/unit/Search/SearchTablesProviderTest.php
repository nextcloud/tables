<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Search;

use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SearchTablesProviderTest extends TestCase {
	private SearchTablesProvider $provider;
	private MockObject $tableService;
	private MockObject $viewService;
	private MockObject $appManager;

	protected function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(IAppManager::class);
		$this->tableService = $this->createMock(TableService::class);
		$this->viewService = $this->createMock(ViewService::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$l10n->method('n')->willReturnArgument(0);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')->willReturn('');
		$urlGenerator->method('getAbsoluteURL')->willReturn('');
		$urlGenerator->method('linkToRoute')->willReturn('');

		$this->provider = new SearchTablesProvider(
			$this->appManager,
			$l10n,
			$this->viewService,
			$this->tableService,
			$urlGenerator,
		);
	}

	private function query(): MockObject {
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getLimit')->willReturn(10);
		$query->method('getTerm')->willReturn('budget');
		$query->method('getCursor')->willReturn(null);

		return $query;
	}

	private function user(string $uid): MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);

		return $user;
	}

	/**
	 * The provider is handed the user to search as, and both services accept one. Without passing
	 * it they fall back to the session user, so a caller searching on behalf of someone else
	 * silently gets its own results.
	 */
	public function testSearchesAsTheGivenUser(): void {
		$this->appManager->method('isEnabledForUser')->willReturn(true);

		$this->tableService->expects($this->once())
			->method('search')
			->with('budget', 10, 0, 'alice')
			->willReturn([]);

		$this->viewService->expects($this->once())
			->method('search')
			->with('budget', 10, 0, 'alice')
			->willReturn([]);

		$this->provider->search($this->user('alice'), $this->query());
	}

	public function testReturnsNothingWhenTheAppIsDisabledForTheUser(): void {
		$this->appManager->method('isEnabledForUser')->willReturn(false);

		$this->tableService->expects($this->never())->method('search');
		$this->viewService->expects($this->never())->method('search');

		$result = $this->provider->search($this->user('alice'), $this->query());

		$this->assertSame([], $result->jsonSerialize()['entries']);
	}
}
