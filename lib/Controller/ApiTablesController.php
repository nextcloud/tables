<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use Exception;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesTable from ResponseDefinitions
 * @psalm-import-type TablesView from ResponseDefinitions
 * @psalm-import-type TablesColumn from ResponseDefinitions
 */
class ApiTablesController extends AOCSController {
	private TableService $service;
	private ColumnService $columnService;
	private ViewService $viewService;
	private IAppManager $appManager;
	private IDBConnection $db;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		TableService $service,
		ColumnService $columnService,
		ViewService $viewService,
		IL10N $n,
		IAppManager $appManager,
		IDBConnection $db,
		string $userId) {
		parent::__construct($request, $logger, $n, $userId);
		$this->service = $service;
		$this->columnService = $columnService;
		$this->appManager = $appManager;
		$this->viewService = $viewService;
		$this->db = $db;
	}

	/**
	 * [api v2] Returns all Tables
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable[], array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function index(): DataResponse {
		try {
			return new DataResponse($this->service->formatTables($this->service->findAll($this->userId)));
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Get a table object
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function show(int $id): DataResponse {
		try {
			return new DataResponse($this->service->find($id)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}

	/**
	 * [api v2] Get a table Scheme
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Scheme returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function showScheme(int $id): DataResponse {
		try {
			return new DataResponse($this->service->getScheme($id)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * creates table from scheme
	 *
	 * @param string $title title of new table
	 * @param string $emoji emoji
	 * @param string $description description
	 * @param array<TablesColumn > $columns columns
	 * @param array<TablesView> $views views
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function createFromScheme(string $title, string $emoji, string $description, array $columns, array $views): DataResponse {
		try {
			$this->db->beginTransaction();
			$table = $this->service->create($title, 'custom', $emoji, $description);
			foreach ($columns as $column) {
				$this->columnService->create(
					$this->userId,
					$table->getId(),
					null,
					new ColumnDto(
						title: $column['title'],
						type: $column['type'],
						subtype: $column['subtype'],
						mandatory: $column['mandatory'],
						description: $column['description'],
						textDefault: $column['textDefault'],
						textAllowedPattern: $column['textAllowedPattern'],
						textMaxLength: $column['textMaxLength'],
						numberDefault: $column['numberDefault'],
						numberMin: $column['numberMin'],
						numberMax: $column['numberMax'],
						numberDecimals: $column['numberDecimals'],
						numberPrefix: $column['numberPrefix'],
						numberSuffix: $column['numberSuffix'],
						selectionOptions: $column['selectionOptions'] == [] ? '' : $column['selectionOptions'],
						selectionDefault: $column['selectionDefault'],
						datetimeDefault: $column['datetimeDefault'],
						usergroupDefault: $column['usergroupDefault'][0] ?? '',
						usergroupMultipleItems: $column['usergroupMultipleItems'],
						usergroupSelectUsers: $column['usergroupSelectUsers'],
						usergroupSelectGroups: $column['usergroupSelectGroups'],
						showUserStatus: $column['showUserStatus'],
					)
				);
			};
			foreach ($views as $view) {
				$this->viewService->create(
					$view['title'],
					$view['emoji'],
					$table,
					$this->userId,
				);
			}
			$this->db->commit();
			return new DataResponse($table->jsonSerialize());
		} catch(InternalError | Exception $e) {
			try {
				$this->db->rollBack();
			} catch (\OCP\DB\Exception $e) {
				return $this->handleError($e);
			}
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Create a new table and return it
	 *
	 * @NoAdminRequired
	 *
	 * @param string $title Title of the table
	 * @param string|null $emoji Emoji for the table
	 * @param string|null $description Description for the table
	 * @param string $template Template to use if wanted
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function create(string $title, ?string $emoji, ?string $description, string $template = 'custom'): DataResponse {
		try {
			return new DataResponse($this->service->create($title, $template, $emoji, $description)->jsonSerialize());
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Update tables properties
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @param string|null $title New table title
	 * @param string|null $emoji New table emoji
	 * @param bool $archived whether the table is archived
	 * @param string $description the tables description
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function update(int $id, ?string $title = null, ?string $emoji = null, ?string $description = null, ?bool $archived = null): DataResponse {
		try {
			return new DataResponse($this->service->update($id, $title, $emoji, $description, $archived, $this->userId)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}

	/**
	 * [api v2] Delete a table
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function destroy(int $id): DataResponse {
		try {
			return new DataResponse($this->service->delete($id)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}

	/**
	 * [api v2] Transfer table
	 *
	 * Transfer table from one user to another
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @param string $newOwnerUserId New user ID
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Ownership changed
	 * 403: No permissions
	 * 404: Not found
	 */
	public function transfer(int $id, string $newOwnerUserId): DataResponse {
		try {
			return new DataResponse($this->service->setOwner($id, $newOwnerUserId)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}
}
