<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ApiViewsController;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PermissionsService;
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
	private GridWidgetTypes $gridWidgetTypes;

	protected function setUp(): void {
		parent::setUp();
		$this->viewService = $this->createMock(ViewService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->gridWidgetTypes = new GridWidgetTypes($l10n, $this->createMock(ViewMapper::class), $this->createMock(TableMapper::class), $this->createMock(PermissionsService::class));
		$this->controller = new ApiViewsController($this->createMock(IRequest::class), $this->createMock(LoggerInterface::class), $l10n, 'alice', $this->viewService, $this->gridWidgetTypes);
	}

	public function testWidgetTypesListsEveryTypeWithBothSchemas(): void {
		$types = $this->controller->widgetTypes()->getData();

		$this->assertCount(count($this->gridWidgetTypes->all()), $types);
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
			->with('Home', '🧩', null, 'alice', null, null, View::TYPE_GRID, 'Start here')
			->willReturn($view);

		$response = $this->controller->create(' Home ', '🧩', 'Start here');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(6, $response->getData()['id']);
	}

	public static function invalidInput(): array {
		return [
			'empty title' => ['   ', null],
			'title over 200 bytes' => [str_repeat('a', 201), null],
			'emoji that is not one emoji' => ['Home', 'not an emoji'],
		];
	}

	/**
	 * @dataProvider invalidInput
	 */
	public function testCreateRefusesInvalidInputWith400(string $title, ?string $emoji): void {
		$this->viewService->expects($this->never())->method('create');

		$response = $this->controller->create($title, $emoji);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateTurnsABadRequestInto400(): void {
		$this->viewService->method('create')->willThrowException(new BadRequestError('rejected by the service'));

		$response = $this->controller->create('Home');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateTurnsAPermissionErrorInto403(): void {
		$this->viewService->method('create')->willThrowException(new PermissionError('no'));

		$response = $this->controller->create('Home');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}
}
