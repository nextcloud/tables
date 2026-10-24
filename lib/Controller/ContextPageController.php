<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesPage from ResponseDefinitions
 * @psalm-import-type TablesWidgetType from ResponseDefinitions
 */
class ContextPageController extends AOCSController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		IL10N $n,
		string $userId,
		private readonly PageService $pageService,
		private readonly GridWidgetTypes $gridWidgetTypes,
	) {
		parent::__construct($request, $logger, $n, $userId);
	}

	/**
	 * [api v2] Get a page of an application with its widgets
	 *
	 * @param int $contextId ID of the application
	 * @param int $pageId ID of the page
	 * @return DataResponse<Http::STATUS_OK, TablesPage, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Page returned
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'GET', url: '/api/2/contexts/{contextId}/pages/{pageId}', requirements: ['contextId' => '\d+', 'pageId' => '\d+'])]
	public function show(int $contextId, int $pageId): DataResponse {
		try {
			return new DataResponse($this->pageService->find($contextId, $pageId, $this->userId));
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Add a grid page to an application
	 *
	 * @param int $contextId ID of the application
	 * @param string $title Title of the page
	 * @param string|null $technicalName Technical name of the page, unique in the application; `page_<id>` when left out, a form reserved for automatic names
	 * @param string|null $icon Name of the icon shown with the title
	 * @param string|null $description Description of the page
	 * @return DataResponse<Http::STATUS_OK, TablesPage, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Page created
	 * 400: Invalid title, icon or description, or an invalid or taken technical name
	 * 403: No permissions
	 * 404: Not found
	 *
	 * @CanManageContext
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'POST', url: '/api/2/contexts/{contextId}/pages', requirements: ['contextId' => '\d+'])]
	public function create(int $contextId, string $title, ?string $technicalName = null, ?string $icon = null, ?string $description = null): DataResponse {
		try {
			return new DataResponse($this->pageService->create($contextId, $title, $technicalName, $icon, $description, $this->userId));
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Change a page or its widgets
	 *
	 * Fields left out keep their value; an empty icon or description removes it. A new
	 * technical name must be valid and free in the application. When
	 * `widgets` is given, the widgets of the grid page are made to match it in one
	 * transaction: listed widgets with an `id` are updated, listed widgets without one
	 * are added and widgets that are not listed are deleted.
	 *
	 * A widget has a `type`, an optional `title`, `icon` and `description`, `gridX`, `gridY`, `gridWidth` and
	 * `gridHeight` on a grid of 12 columns and `settings` following the schema of its
	 * type. A widget of a type with a source has a `source`: `{nodeRelId}` of a table or
	 * view of this application, `{nodeType, nodeId}` of a table or view the user manages,
	 * which is added to the application, or null. A table or view added this way is also
	 * placed on the start page, and both stay when the widget is removed later.
	 *
	 * A page holds at most 100 widgets. When another save changed the page in between,
	 * for instance removed a listed widget, the save is refused and can be retried on the
	 * page as it is now.
	 *
	 * @param int $contextId ID of the application
	 * @param int $pageId ID of the page
	 * @param string|null $title New title
	 * @param string|null $technicalName New technical name
	 * @param string|null $icon New icon name, empty to remove it
	 * @param string|null $description New description, empty to remove it
	 * @param list<array<string, mixed>>|null $widgets All widgets of the page
	 * @return DataResponse<Http::STATUS_OK, TablesPage, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Page updated
	 * 400: Invalid page field or widget, too many widgets, or the page changed in between
	 * 403: No permissions on the application or on a table or view to add
	 * 404: Not found
	 *
	 * @CanManageContext
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'PATCH', url: '/api/2/contexts/{contextId}/pages/{pageId}', requirements: ['contextId' => '\d+', 'pageId' => '\d+'])]
	public function update(int $contextId, int $pageId, ?string $title = null, ?string $technicalName = null, ?string $icon = null, ?string $description = null, ?array $widgets = null): DataResponse {
		try {
			return new DataResponse($this->pageService->update($contextId, $pageId, $title, $technicalName, $icon, $description, $widgets, $this->userId));
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Delete a grid page with its widgets
	 *
	 * The start page of an application cannot be deleted.
	 *
	 * @param int $contextId ID of the application
	 * @param int $pageId ID of the page
	 * @return DataResponse<Http::STATUS_OK, TablesPage, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Page deleted
	 * 400: The page is the start page
	 * 403: No permissions
	 * 404: Not found
	 *
	 * @CanManageContext
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'DELETE', url: '/api/2/contexts/{contextId}/pages/{pageId}', requirements: ['contextId' => '\d+', 'pageId' => '\d+'])]
	public function destroy(int $contextId, int $pageId): DataResponse {
		try {
			return new DataResponse($this->pageService->delete($contextId, $pageId, $this->userId));
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Get the widget types a grid page can hold
	 *
	 * Every type carries the schema of its settings and whether it shows a table or view.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TablesWidgetType>, array{}>
	 *
	 * 200: Widget types returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/2/widget-types')]
	public function widgetTypes(): DataResponse {
		return new DataResponse(array_values($this->gridWidgetTypes->all()));
	}
}
