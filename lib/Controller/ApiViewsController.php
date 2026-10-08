<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
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
	public function widgetTypes(): DataResponse {
		return new DataResponse(array_values(GridWidgetTypes::all()));
	}

	/**
	 * [api v2] Create a view without a table, such as a grid view
	 *
	 * The view belongs to the user who creates it and can be placed in an application menu.
	 *
	 * @param string $title Title of the view
	 * @param string|null $emoji Emoji shown with the title
	 * @param string $type Type of the view, `grid` for a page of widgets
	 * @param string|null $technicalName Technical name, lowercase letters, numbers and underscores
	 * @param string $description Description shown on the page
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: View created
	 * 400: Invalid type or technical name
	 * 403: No permissions
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	public function create(string $title, ?string $emoji = null, string $type = 'grid', ?string $technicalName = null, string $description = ''): DataResponse {
		try {
			return new DataResponse($this->viewService->create($title, $emoji, null, $this->userId, $technicalName, null, $type, $description)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		}
	}
}
