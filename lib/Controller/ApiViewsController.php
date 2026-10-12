<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use InvalidArgumentException;
use OCA\Tables\Db\View;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\ValueObject\Emoji;
use OCA\Tables\Service\ValueObject\Title;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesView from ResponseDefinitions
 * @psalm-import-type TablesGridWidgetType from ResponseDefinitions
 */
class ApiViewsController extends AOCSController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		IL10N $n,
		string $userId,
		private readonly ViewService $viewService,
		private readonly GridWidgetTypes $gridWidgetTypes,
	) {
		parent::__construct($request, $logger, $n, $userId);
	}

	/**
	 * [api v2] The widget types a grid view can hold
	 *
	 * Every type carries two schemas: the configuration of the widget on the page and its content.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TablesGridWidgetType>, array{}>
	 *
	 * 200: Widget types returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/2/views/widget-types')]
	public function widgetTypes(): DataResponse {
		return new DataResponse(array_values($this->gridWidgetTypes->all()));
	}

	/**
	 * [api v2] Create a grid view, a view without a table
	 *
	 * The view belongs to the user who creates it and can be placed in an application menu.
	 * It is named by its id, so it takes no technical name.
	 *
	 * @param string $title Title of the view
	 * @param string|null $emoji Emoji shown with the title
	 * @param string $description Description shown on the page
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: View created
	 * 400: Invalid title or emoji
	 * 403: No permissions
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[ApiRoute(verb: 'POST', url: '/api/2/views')]
	public function create(string $title, ?string $emoji = null, string $description = ''): DataResponse {
		try {
			$validTitle = (string)new Title($title);
			$validEmoji = $emoji === null || $emoji === '' ? null : (string)new Emoji($emoji);
		} catch (InvalidArgumentException $e) {
			return $this->handleBadRequestError(new BadRequestError($e->getMessage(), $e->getCode(), $e));
		}
		try {
			return new DataResponse($this->viewService->create($validTitle, $validEmoji, null, $this->userId, null, null, View::TYPE_GRID, $description)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		}
	}
}
