<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\ContextPageController;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ContextPageControllerTest extends TestCase {
	private ContextPageController $controller;
	private PageService&MockObject $pageService;

	protected function setUp(): void {
		parent::setUp();
		$this->pageService = $this->createMock(PageService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->controller = new ContextPageController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$l10n,
			'alice',
			$this->pageService,
			new GridWidgetTypes($l10n),
		);
	}

	/**
	 * @return array<string, array{0: string, 1: list<mixed>, 2: \Closure(ContextPageController): DataResponse}>
	 */
	public static function endpoints(): array {
		$widgets = [['type' => 'text', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2]];
		return [
			'show' => ['find', [7, 2, 'alice'], static fn (ContextPageController $controller) => $controller->show(7, 2)],
			'create' => ['create', [7, 'Overview', 'overview', 'star', 'At a glance', 'alice'], static fn (ContextPageController $controller) => $controller->create(7, 'Overview', 'overview', 'star', 'At a glance')],
			'update' => ['update', [7, 2, null, 'planning', '', null, $widgets, 'alice'], static fn (ContextPageController $controller) => $controller->update(7, 2, null, 'planning', '', null, $widgets)],
			'destroy' => ['delete', [7, 2, 'alice'], static fn (ContextPageController $controller) => $controller->destroy(7, 2)],
		];
	}

	/**
	 * @dataProvider endpoints
	 */
	public function testAnEndpointReturnsThePage(string $method, array $arguments, \Closure $call): void {
		$page = ['id' => 2, 'contextId' => 7, 'pageType' => 'grid', 'technicalName' => 'overview', 'title' => 'Overview', 'icon' => null, 'description' => null, 'widgets' => []];
		$this->pageService->expects($this->once())->method($method)->with(...$arguments)->willReturn($page);

		$response = $call($this->controller);

		$this->assertSame([Http::STATUS_OK, $page], [$response->getStatus(), $response->getData()]);
	}

	public static function errors(): array {
		$endpoints = self::endpoints();
		$cases = [
			'show' => [new NotFoundError('Page 9 does not belong to application 7'), Http::STATUS_NOT_FOUND],
		];
		foreach (['create', 'update', 'destroy'] as $endpoint) {
			$cases[$endpoint . ', bad request'] = [new BadRequestError('Widget 0 needs a type.'), Http::STATUS_BAD_REQUEST];
			$cases[$endpoint . ', no permission'] = [new PermissionError('User bob cannot manage application 7'), Http::STATUS_FORBIDDEN];
			$cases[$endpoint . ', not found'] = [new NotFoundError('Context does not exist'), Http::STATUS_NOT_FOUND];
		}
		$cases['update, page gone'] = [new DoesNotExistException('Page does not exist'), Http::STATUS_NOT_FOUND];
		$errors = [];
		foreach ($cases as $case => [$error, $status]) {
			[$method, , $call] = $endpoints[explode(',', $case)[0]];
			$errors[$case] = [$method, $call, $error, $status];
		}
		return $errors;
	}

	/**
	 * @dataProvider errors
	 */
	public function testAnErrorIsAnsweredWithItsStatus(string $method, \Closure $call, \Exception $error, int $status): void {
		$this->pageService->method($method)->willThrowException($error);

		$response = $call($this->controller);

		$this->assertSame($status, $response->getStatus());
		$this->assertArrayHasKey('message', $response->getData());
	}

	public function testABadRequestExplainsWhy(): void {
		$this->pageService->method('update')->willThrowException(new BadRequestError('Widget 0 needs a type.'));

		$this->assertSame('Widget 0 needs a type.', $this->controller->update(7, 2, null, null, null, null, [[]])->getData()['message']);
	}

	public function testWidgetTypesListsEveryType(): void {
		$response = $this->controller->widgetTypes();

		$this->assertSame(['header', 'text', 'data', 'tabs'], array_column($response->getData(), 'type'));
	}
}
