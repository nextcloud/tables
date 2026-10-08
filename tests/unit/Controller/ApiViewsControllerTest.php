<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ApiViewsController;
use OCA\Tables\Db\View;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ApiViewsControllerTest extends TestCase {
	private ApiViewsController $controller;
	private MockObject $viewService;

	protected function setUp(): void {
		parent::setUp();
		$this->viewService = $this->createMock(ViewService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->controller = new ApiViewsController($this->createMock(IRequest::class), $this->createMock(LoggerInterface::class), $l10n, 'alice', $this->viewService);
	}

	public function testWidgetTypesListsEveryTypeWithBothSchemas(): void {
		$types = $this->controller->widgetTypes()->getData();

		$this->assertCount(count(GridWidgetTypes::all()), $types);
		foreach ($types as $type) {
			$this->assertArrayHasKey('configuration', $type);
			$this->assertArrayHasKey('properties', $type);
		}
	}

	public function testCreateHandsTheStandaloneViewToTheService(): void {
		$view = new View();
		$view->setId(6);
		$view->setType(View::TYPE_GRID);
		$view->setCreatedBy('alice');
		$this->viewService->expects($this->once())
			->method('create')
			->with('Home', '🧩', null, 'alice', 'home', null, View::TYPE_GRID, 'Start here')
			->willReturn($view);

		$response = $this->controller->create('Home', '🧩', 'grid', 'home', 'Start here');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(6, $response->getData()['id']);
	}

	public function testCreateTurnsABadRequestInto400(): void {
		$this->viewService->method('create')->willThrowException(new BadRequestError('Technical name must start with a lowercase letter'));

		$response = $this->controller->create('Home', null, 'grid', 'Not Valid');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateTurnsAPermissionErrorInto403(): void {
		$this->viewService->method('create')->willThrowException(new PermissionError('no'));

		$response = $this->controller->create('Home');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}
}
