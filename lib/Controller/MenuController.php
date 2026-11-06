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
use OCA\Tables\Service\MenuService;
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
 * @psalm-import-type TablesMenuItem from ResponseDefinitions
 */
class MenuController extends AOCSController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		IL10N $n,
		string $userId,
		private readonly MenuService $menuService,
	) {
		parent::__construct($request, $logger, $n, $userId);
	}

	/**
	 * [api v2] Get the menu of an application
	 *
	 * A flat list in display order; the items of a group carry its id as `parentId`.
	 * An application without items opens its start page. Readers who cannot manage the
	 * application do not get empty groups.
	 *
	 * @param int $contextId ID of the application
	 * @return DataResponse<Http::STATUS_OK, list<TablesMenuItem>, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Menu returned
	 * 404: Not found, also when the user has no access to the application
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'GET', url: '/api/2/contexts/{contextId}/menu', requirements: ['contextId' => '\d+'])]
	public function index(int $contextId): DataResponse {
		try {
			return new DataResponse($this->menuService->findAll($contextId, $this->userId));
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Add an item to the end of the menu of an application
	 *
	 * The `target` is `{pageId}` of a page or `{nodeRelId}` of a table or view of the
	 * application, or `{url}` of an http or https link. Without a target the item is a
	 * group, which needs a title. Without a title an item shows the title of what it opens.
	 *
	 * @param int $contextId ID of the application
	 * @param string|null $title Title of the item
	 * @param string|null $icon Name of the icon shown with the title
	 * @param string|null $description Description of the item
	 * @param array<string, mixed>|null $target What the item opens
	 * @return DataResponse<Http::STATUS_OK, TablesMenuItem, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Item created
	 * 400: Invalid field, a target outside the application, or a full menu
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'POST', url: '/api/2/contexts/{contextId}/menu', requirements: ['contextId' => '\d+'])]
	public function create(int $contextId, ?string $title = null, ?string $icon = null, ?string $description = null, ?array $target = null): DataResponse {
		try {
			return new DataResponse($this->menuService->create($contextId, $title, $icon, $description, $target, $this->userId));
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
	 * [api v2] Save the order and grouping of the menu of an application
	 *
	 * `items` lists every item of the menu exactly once, in display order, as
	 * `{id, parentId}`; `parentId` is a group at the top level listed earlier, or null.
	 * Either all of it is saved or nothing.
	 *
	 * @param int $contextId ID of the application
	 * @param list<array{id: int, parentId: ?int}> $items Every item of the menu
	 * @return DataResponse<Http::STATUS_OK, list<TablesMenuItem>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Menu saved
	 * 400: An item missing or listed twice, a parent that is not a group, or the menu changed in between
	 * 403: No permissions
	 * 404: Not found, also for an item of another application
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'PUT', url: '/api/2/contexts/{contextId}/menu/order', requirements: ['contextId' => '\d+'])]
	public function order(int $contextId, array $items): DataResponse {
		try {
			return new DataResponse($this->menuService->saveOrder($contextId, $items, $this->userId));
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
	 * [api v2] Change an item of a menu
	 *
	 * Fields left out keep their value; an empty title, icon or description removes it.
	 * A target replaces the previous one. Position and group change only through the order.
	 *
	 * @param int $contextId ID of the application
	 * @param int $itemId ID of the item
	 * @param string|null $title New title, empty to show the title of what the item opens
	 * @param string|null $icon New icon name, empty to remove it
	 * @param string|null $description New description, empty to remove it
	 * @param array<string, mixed>|null $target New target
	 * @return DataResponse<Http::STATUS_OK, TablesMenuItem, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Item updated
	 * 400: Invalid field or a target outside the application
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 60, period: 60)]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'PATCH', url: '/api/2/contexts/{contextId}/menu/{itemId}', requirements: ['contextId' => '\d+', 'itemId' => '\d+'])]
	public function update(int $contextId, int $itemId, ?string $title = null, ?string $icon = null, ?string $description = null, ?array $target = null): DataResponse {
		try {
			return new DataResponse($this->menuService->update($contextId, $itemId, $title, $icon, $description, $target, $this->userId));
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
	 * [api v2] Delete an item of a menu
	 *
	 * The items of a deleted group move to the top level.
	 *
	 * @param int $contextId ID of the application
	 * @param int $itemId ID of the item
	 * @return DataResponse<Http::STATUS_OK, TablesMenuItem, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Item deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'context', idParam: 'contextId')]
	#[ApiRoute(verb: 'DELETE', url: '/api/2/contexts/{contextId}/menu/{itemId}', requirements: ['contextId' => '\d+', 'itemId' => '\d+'])]
	public function destroy(int $contextId, int $itemId): DataResponse {
		try {
			return new DataResponse($this->menuService->delete($contextId, $itemId, $this->userId));
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (Exception $e) {
			return $this->handleError($e);
		}
	}
}
