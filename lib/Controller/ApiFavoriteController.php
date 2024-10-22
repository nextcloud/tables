<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Controller;

use Exception;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\FavoritesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception as DBException;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesTable from ResponseDefinitions
 */
class ApiFavoriteController extends AOCSController {
	private FavoritesService $service;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		FavoritesService $service,
		IL10N $n,
		string $userId) {
		parent::__construct($request, $logger, $n, $userId);
		$this->service = $service;
	}

	/**
	 * [api v2] Add a node (table or view) to user favorites
	 *
	 * @param int $nodeType any Application::NODE_TYPE_* constant
	 * @param int $nodeId identifier of the node
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ)]
	public function create(int $nodeType, int $nodeId): DataResponse {
		try {
			$this->service->addFavorite($nodeType, $nodeId);
			return new DataResponse();
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError|DBException|Exception $e) {
			return $this->handleError($e);
		}
	}


	/**
	 * [api v2] Remove a node (table or view) to from favorites
	 *
	 * @param int $nodeType any Application::NODE_TYPE_* constant
	 * @param int $nodeId identifier of the node
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ)]
	public function destroy(int $nodeType, int $nodeId): DataResponse {
		try {
			$this->service->removeFavorite($nodeType, $nodeId);
			return new DataResponse();
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError|DBException|Exception $e) {
			return $this->handleError($e);
		}
	}
}
