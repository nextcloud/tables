<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @noinspection DuplicatedCode */

namespace OCA\Tables\Controller;

use Exception;
use InvalidArgumentException;
use OCA\Tables\Api\V1Api;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ImportService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesTable from ResponseDefinitions
 * @psalm-import-type TablesView from ResponseDefinitions
 * @psalm-import-type TablesShare from ResponseDefinitions
 * @psalm-import-type TablesColumn from ResponseDefinitions
 * @psalm-import-type TablesRow from ResponseDefinitions
 * @psalm-import-type TablesImportState from ResponseDefinitions
 * @psalm-import-type TablesContextNavigation from ResponseDefinitions
 */
class Api1Controller extends ApiController {
	private TableService $tableService;
	private ShareService $shareService;
	private ColumnService $columnService;
	private RowService $rowService;
	private ImportService $importService;
	private ViewService $viewService;
	private ViewMapper $viewMapper;
	private IL10N $l10N;

	private V1Api $v1Api;

	private ?string $userId;

	protected LoggerInterface $logger;

	use Errors;


	public function __construct(
		IRequest $request,
		TableService $service,
		ShareService $shareService,
		ColumnService $columnService,
		RowService $rowService,
		ImportService $importService,
		ViewService $viewService,
		ViewMapper $viewMapper,
		V1Api $v1Api,
		LoggerInterface $logger,
		IL10N $l10N,
		?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->tableService = $service;
		$this->shareService = $shareService;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->importService = $importService;
		$this->viewService = $viewService;
		$this->viewMapper = $viewMapper;
		$this->userId = $userId;
		$this->v1Api = $v1Api;
		$this->logger = $logger;
		$this->l10N = $l10N;
	}

	// Tables

	/**
	 * Returns all Tables
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TablesTable>, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function index(): DataResponse {
		try {
			return new DataResponse($this->tableService->formatTables($this->tableService->findAll($this->userId)));
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new table and return it
	 *
	 * @param string $title Title of the table
	 * @param string|null $emoji Emoji for the table
	 * @param string $template Template to use if wanted
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createTable(string $title, ?string $emoji, string $template = 'custom'): DataResponse {
		try {
			return new DataResponse($this->tableService->create($title, $template, $emoji)->jsonSerialize());
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * returns table scheme
	 *
	 * @param int $tableId Table ID
	 * @return JSONResponse<Http::STATUS_OK, TablesTable, array{'Content-Disposition': string, 'Content-Type': string}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Scheme returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function showScheme(int $tableId): JSONResponse|DataResponse {
		try {
			$scheme = $this->tableService->getScheme($tableId, $this->userId);
			$filename = $scheme->getTitle() . '.json';

			return new JSONResponse(
				$scheme->jsonSerialize(),
				Http::STATUS_OK,
				[
					'Content-Disposition' => 'attachment; filename="' . $filename . '"',
					'Content-Type' => 'application/json',
				]
			);
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a table object
	 *
	 * @param int $tableId Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function getTable(int $tableId): DataResponse {
		try {
			return new DataResponse($this->tableService->find($tableId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update tables properties
	 *
	 * @param int $tableId Table ID
	 * @param string|null $title New table title
	 * @param string|null $emoji New table emoji
	 * @param bool $archived Whether the table is archived
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateTable(int $tableId, ?string $title = null, ?string $emoji = null, ?bool $archived = false): DataResponse {
		try {
			return new DataResponse($this->tableService->update($tableId, $title, $emoji, null, $archived, $this->userId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a table
	 *
	 * @param int $tableId Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteTable(int $tableId): DataResponse {
		try {
			return new DataResponse($this->tableService->delete($tableId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	// Views

	/**
	 * Get all views for a table
	 *
	 * @param int $tableId Table ID
	 * @return DataResponse<Http::STATUS_OK, list<TablesView>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Views returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function indexViews(int $tableId): DataResponse {
		try {
			return new DataResponse($this->viewService->formatViews($this->viewService->findAll($this->tableService->find($tableId))));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new view for a table
	 *
	 * @param int $tableId Table ID that will hold the view
	 * @param string $title Title for the view
	 * @param string|null $emoji Emoji for the view
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View created
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createView(int $tableId, string $title, ?string $emoji): DataResponse {
		try {
			return new DataResponse($this->viewService->create($title, $emoji, $this->tableService->find($tableId))->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a view object
	 *
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function getView(int $viewId): DataResponse {
		try {
			return new DataResponse($this->viewService->find($viewId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update a view via key-value sets
	 *
	 * @param int $viewId View ID
	 * @param array{key: 'title'|'emoji'|'description', value: string}|array{key: 'columns', value: list<int>}|array{key: 'sort', value: array{columnId: int, mode: 'ASC'|'DESC'}}|array{key: 'filter', value: array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty', value: string|int|float}} $data key-value pairs
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: View updated
	 * 400: Invalid data
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateView(int $viewId, array $data): DataResponse {
		try {
			return new DataResponse($this->viewService->update($viewId, $data)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InvalidArgumentException $e) {
			$this->logger->warning('An invalid request occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_BAD_REQUEST);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a view
	 *
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteView(int $viewId): DataResponse {
		try {
			return new DataResponse($this->viewService->delete($viewId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	// Shares

	/**
	 * Get a share object
	 *
	 * @param int $shareId Share ID
	 * @return DataResponse<Http::STATUS_OK, TablesShare, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Share returned
	 * 404: Not found/No permissions
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function getShare(int $shareId): DataResponse {
		try {
			return new DataResponse($this->shareService->find($shareId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get all shares for a view
	 * Will be empty if view does not exist
	 *
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, list<TablesShare>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Shares returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function indexViewShares(int $viewId): DataResponse {
		try {
			return new DataResponse($this->shareService->formatShares($this->shareService->findAll('view', $viewId)));
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all shares for a table
	 * Will be empty if table does not exist
	 *
	 * @param int $tableId Table ID
	 * @return DataResponse<Http::STATUS_OK, list<TablesShare>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Shares returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function indexTableShares(int $tableId): DataResponse {
		try {
			return new DataResponse($this->shareService->formatShares($this->shareService->findAll('table', $tableId)));
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new share
	 *
	 * @param int $nodeId Node ID
	 * @param 'table'|'view'|'context' $nodeType Node type
	 * @param string $receiver Receiver ID
	 * @param 'user'|'group' $receiverType Receiver type
	 * @param bool $permissionRead Permission if receiver can read data
	 * @param bool $permissionCreate Permission if receiver can create data
	 * @param bool $permissionUpdate Permission if receiver can update data
	 * @param bool $permissionDelete Permission if receiver can delete data
	 * @param bool $permissionManage Permission if receiver can manage node
	 * @param int $displayMode context shares only, whether it should appear in nav bar. 0: no, 1: recipients, 2: all (default). Cf. Application::NAV_ENTRY_MODE_*.
	 * @psalm-param int<0, 2> $displayMode
	 * @return DataResponse<Http::STATUS_OK, TablesShare, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Share returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createShare(
		int $nodeId,
		string $nodeType,
		string $receiver,
		string $receiverType,
		bool $permissionRead = false,
		bool $permissionCreate = false,
		bool $permissionUpdate = false,
		bool $permissionDelete = false,
		bool $permissionManage = false,
		int $displayMode = 2,
	): DataResponse {
		try {
			return new DataResponse(
				$this->shareService->create(
					$nodeId,
					$nodeType,
					$receiver,
					$receiverType,
					$permissionRead,
					$permissionCreate,
					$permissionUpdate,
					$permissionDelete,
					$permissionManage,
					$displayMode
				)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a share
	 *
	 * @param int $shareId Share ID
	 * @return DataResponse<Http::STATUS_OK, TablesShare, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteShare(int $shareId): DataResponse {
		try {
			return new DataResponse($this->shareService->delete($shareId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update a share permission
	 *
	 * @param int $shareId Share ID
	 * @param string $permissionType Permission type that should be changed
	 * @param bool $permissionValue New permission value
	 * @return DataResponse<Http::STATUS_OK, TablesShare, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateSharePermissions(int $shareId, string $permissionType, bool $permissionValue): DataResponse {
		try {
			return new DataResponse($this->shareService->updatePermission($shareId, $permissionType, $permissionValue)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Updates the display mode of a context share
	 *
	 * @param int $shareId Share ID
	 * @param int $displayMode The new value for the display mode of the nav bar icon. 0: hidden, 1: visible for recipients, 2: visible for all
	 * @param string $target "default" to set the default, "self" to set an override for the authenticated user
	 * @return DataResponse<Http::STATUS_OK, TablesContextNavigation, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Display mode updated
	 * 400: Invalid parameter
	 * 403: No permissions
	 * 404: Share not found
	 *
	 * @psalm-param int<0, 2> $displayMode
	 * @psalm-param ("default"|"self") $target
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateShareDisplayMode(int $shareId, int $displayMode, string $target = 'default'): DataResponse {
		if ($target === 'default') {
			$userId = '';
		} elseif ($target === 'self') {
			$userId = $this->userId;
		} else {
			$error = 'target parameter must be either "default" or "self"';
			$this->logger->warning(sprintf('An internal error or exception occurred: %s', $error));
			$message = ['message' => $error];
			return new DataResponse($message, Http::STATUS_BAD_REQUEST);
		}

		try {
			return new DataResponse($this->shareService->updateDisplayMode($shareId, $displayMode, $userId)->jsonSerialize());
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		}
	}

	// Columns

	/**
	 * Get all columns for a table or a underlying view
	 * Return an empty array if no columns were found
	 *
	 * @param int $tableId Table ID
	 * @param int|null $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, list<TablesColumn>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function indexTableColumns(int $tableId, ?int $viewId): DataResponse {
		try {
			if ($viewId) {
				$view = $this->viewService->find($viewId, false, $this->userId);
				if ($tableId !== $view->getTableId()) {
					throw new PermissionError('Given table is not a parent of the given view.');
				}
				$columns = $this->columnService->findAllByManagedView($view, $this->userId);
			} else {
				$columns = $this->columnService->findAllByTable($tableId);
			}
			return new DataResponse($this->columnService->formatColumns($columns));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('The view could not be found: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get all columns for a view
	 * Return an empty array if no columns were found
	 *
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, list<TablesColumn>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function indexViewColumns(int $viewId): DataResponse {
		try {
			return new DataResponse($this->columnService->formatColumns($this->columnService->findAllByView($viewId)));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Create a column
	 *
	 * @param int|null $tableId Table ID
	 * @param int|null $viewId View ID
	 * @param string $title Title
	 * @param 'text'|'number'|'datetime'|'select'|'usergroup' $type Column main type
	 * @param string|null $subtype Column sub type
	 * @param bool $mandatory Is the column mandatory
	 * @param string|null $description Description
	 * @param string|null $numberPrefix Prefix if the column is a number field
	 * @param string|null $numberSuffix Suffix if the column is a number field
	 * @param float|null $numberDefault Default number, if column is a number
	 * @param float|null $numberMin Min value, if column is a number
	 * @param float|null $numberMax Max number, if column is a number
	 * @param int|null $numberDecimals Number of decimals, if column is a number
	 * @param string|null $textDefault Default text, if column is a text
	 * @param string|null $textAllowedPattern Allowed pattern (regex) for text columns (not yet implemented)
	 * @param int|null $textMaxLength Max length, if column is a text
	 * @param bool|null $textUnique Whether the text value must be unique, if column is a text
	 * @param string|null $selectionOptions Options for a selection (json array{id: int, label: string})
	 * @param string|null $selectionDefault Default option IDs for a selection (json list<int>)
	 * @param string|null $datetimeDefault Default value, if column is datetime
	 * @param string|null $usergroupDefault Default value, if column is usergroup (json array{id: string, type: int})
	 * @param bool|null $usergroupMultipleItems Can select multiple users or/and groups, if column is usergroup
	 * @param bool|null $usergroupSelectUsers Can select users, if column type is usergroup
	 * @param bool|null $usergroupSelectGroups Can select groups, if column type is usergroup
	 * @param bool|null $usergroupSelectTeams Can select teams, if column type is usergroup
	 * @param bool|null $usergroupShowUserStatus Whether to show the user's status, if column type is usergroup
	 * @param list<int>|null $selectedViewIds View IDs where this column should be added to be presented
	 * @param array<string, mixed> $customSettings Custom settings for the column
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createColumn(
		?int $tableId,
		?int $viewId,
		string $title,
		string $type,
		?string $subtype,
		bool $mandatory,
		?string $description,
		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,
		?bool $textUnique = false,

		?string $selectionOptions = '',
		?string $selectionDefault = '',

		?string $datetimeDefault = '',

		?string $usergroupDefault = '',
		?bool $usergroupMultipleItems = null,
		?bool $usergroupSelectUsers = null,
		?bool $usergroupSelectGroups = null,
		?bool $usergroupSelectTeams = null,
		?bool $usergroupShowUserStatus = null,

		?array $selectedViewIds = [],
		?array $customSettings = [],
	): DataResponse {
		try {
			return new DataResponse($this->columnService->create(
				$this->userId,
				$tableId,
				$viewId,
				new ColumnDto(
					title: $title,
					type: $type,
					subtype: $subtype,
					mandatory: $mandatory,
					description: $description,
					textDefault: $textDefault,
					textAllowedPattern: $textAllowedPattern,
					textMaxLength: $textMaxLength,
					textUnique: $textUnique,
					numberDefault: $numberDefault,
					numberMin: $numberMin,
					numberMax: $numberMax,
					numberDecimals: $numberDecimals,
					numberPrefix: $numberPrefix,
					numberSuffix: $numberSuffix,
					selectionOptions: $selectionOptions,
					selectionDefault: $selectionDefault,
					datetimeDefault: $datetimeDefault,
					usergroupDefault: $usergroupDefault,
					usergroupMultipleItems: $usergroupMultipleItems,
					usergroupSelectUsers: $usergroupSelectUsers,
					usergroupSelectGroups: $usergroupSelectGroups,
					usergroupSelectTeams: $usergroupSelectTeams,
					showUserStatus: $usergroupShowUserStatus,
					customSettings: json_encode($customSettings),
				),
				$selectedViewIds
			)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update a column
	 *
	 * @param int $columnId Column ID that will be updated
	 * @param string|null $title Title
	 * @param string|null $subtype Column sub type
	 * @param bool $mandatory Is the column mandatory
	 * @param string|null $description Description
	 * @param string|null $numberPrefix Prefix if the column is a number field
	 * @param string|null $numberSuffix Suffix if the column is a number field
	 * @param float|null $numberDefault Default number, if column is a number
	 * @param float|null $numberMin Min value, if column is a number
	 * @param float|null $numberMax Max number, if column is a number
	 * @param int|null $numberDecimals Number of decimals, if column is a number
	 * @param string|null $textDefault Default text, if column is a text
	 * @param string|null $textAllowedPattern Allowed pattern (regex) for text columns (not yet implemented)
	 * @param int|null $textMaxLength Max length, if column is a text
	 * @param bool|null $textUnique Whether the text value must be unique, if column is a text
	 * @param string|null $selectionOptions Options for a selection (json array{id: int, label: string})
	 * @param string|null $selectionDefault Default option IDs for a selection (json list<int>)
	 * @param string|null $datetimeDefault Default value, if column is datetime
	 * @param string|null $usergroupDefault Default value, if column is usergroup
	 * @param bool|null $usergroupMultipleItems Can select multiple users or/and groups, if column is usergroup
	 * @param bool|null $usergroupSelectUsers Can select users, if column type is usergroup
	 * @param bool|null $usergroupSelectGroups Can select groups, if column type is usergroup
	 * @param bool|null $usergroupSelectTeams Can select teams, if column type is usergroup
	 * @param bool|null $usergroupShowUserStatus Whether to show the user's status, if column type is usergroup
	 * @param array<string, mixed> $customSettings Custom settings for the column
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Updated column
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateColumn(
		int $columnId,
		?string $title,
		?string $subtype,
		?bool $mandatory,
		?string $description,

		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,
		?bool $textUnique,

		?string $selectionOptions,
		?string $selectionDefault,

		?string $datetimeDefault,

		?string $usergroupDefault,
		?bool $usergroupMultipleItems,
		?bool $usergroupSelectUsers,
		?bool $usergroupSelectGroups,
		?bool $usergroupSelectTeams,
		?bool $usergroupShowUserStatus,
		?array $customSettings = [],
	): DataResponse {
		try {
			$item = $this->columnService->update(
				$columnId,
				$this->userId,
				new ColumnDto(
					title: $title,
					subtype: $subtype,
					mandatory: $mandatory,
					description: $description,
					textDefault: $textDefault,
					textAllowedPattern: $textAllowedPattern,
					textMaxLength: $textMaxLength,
					textUnique: $textUnique,
					numberDefault: $numberDefault,
					numberMin: $numberMin,
					numberMax: $numberMax,
					numberDecimals: $numberDecimals,
					numberPrefix: $numberPrefix,
					numberSuffix: $numberSuffix,
					selectionOptions: $selectionOptions,
					selectionDefault: $selectionDefault,
					datetimeDefault: $datetimeDefault,
					usergroupDefault: $usergroupDefault,
					usergroupMultipleItems: $usergroupMultipleItems,
					usergroupSelectUsers: $usergroupSelectUsers,
					usergroupSelectGroups: $usergroupSelectGroups,
					usergroupSelectTeams: $usergroupSelectTeams,
					showUserStatus: $usergroupShowUserStatus,
					customSettings: json_encode($customSettings),
				)
			);
			return new DataResponse($item->jsonSerialize());
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Returns a column object
	 *
	 * @param int $columnId Wanted Column ID
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function getColumn(int $columnId): DataResponse {
		try {
			return new DataResponse($this->columnService->find($columnId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a column
	 *
	 * @param int $columnId Wanted Column ID
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted column returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteColumn(int $columnId): DataResponse {
		try {
			return new DataResponse($this->columnService->delete($columnId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List all rows values for a table, first row are the column titles
	 *
	 * @param int $tableId Table ID
	 * @param int|null $limit Limit
	 * @param int|null $offset Offset
	 * @return DataResponse<Http::STATUS_OK, list<string>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Row values returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function indexTableRowsSimple(int $tableId, ?int $limit, ?int $offset): DataResponse {
		try {
			return new DataResponse($this->v1Api->getData($tableId, $limit, $offset, $this->userId));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List all rows for a table
	 *
	 * @param int $tableId Table ID
	 * @param int|null $limit Limit
	 * @param int|null $offset Offset
	 * @return DataResponse<Http::STATUS_OK, list<TablesRow>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Rows returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function indexTableRows(int $tableId, ?int $limit, ?int $offset): DataResponse {
		try {
			return new DataResponse($this->rowService->formatRows($this->rowService->findAllByTable($tableId, $this->userId, $limit, $offset)));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List all rows for a view
	 *
	 * @param int $viewId View ID
	 * @param int|null $limit Limit
	 * @param int|null $offset Offset
	 * @return DataResponse<Http::STATUS_OK, list<TablesRow>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Rows returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function indexViewRows(int $viewId, ?int $limit, ?int $offset): DataResponse {
		try {
			return new DataResponse($this->rowService->formatRows($this->rowService->findAllByView($viewId, $this->userId, $limit, $offset)));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a row within a view
	 *
	 * @param int $viewId View ID
	 * @param string|array<string, mixed> $data Data as key - value store
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row returned
	 * 400: Validation error
	 * 403: No permissions
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createRowInView(int $viewId, $data): DataResponse {
		if (is_string($data)) {
			$data = json_decode($data, true);
		}
		if (!is_array($data)) {
			$this->logger->warning('createRowInView not possible, data array invalid.');
			$message = ['message' => $this->l10N->t('Could not create row.')];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int)$key,
				'value' => $value
			];
		}

		try {
			return new DataResponse($this->rowService->create(null, $viewId, $dataNew)->jsonSerialize());
		} catch (BadRequestError $e) {
			$this->logger->warning('An bad request was encountered: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->translatedMessage ?: $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a row within a table
	 *
	 * @param int $tableId Table ID
	 * @param string|array<string, mixed> $data Data as key - value store
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row returned
	 * 400: Validation error
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createRowInTable(int $tableId, $data): DataResponse {
		if (is_string($data)) {
			$data = json_decode($data, true);
		}
		if (!is_array($data)) {
			$this->logger->warning('createRowInTable not possible, data array invalid.');
			$message = ['message' => $this->l10N->t('Could not create row.')];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int)$key,
				'value' => $value
			];
		}

		try {
			return new DataResponse($this->rowService->create($tableId, null, $dataNew)->jsonSerialize());
		} catch (BadRequestError $e) {
			$this->logger->warning('An bad request was encountered: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->translatedMessage ?: $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a row
	 *
	 * @param int $rowId Row ID
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Row returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function getRow(int $rowId): DataResponse {
		try {
			return new DataResponse($this->rowService->find($rowId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update a row
	 *
	 * @param int $rowId Row ID
	 * @param int|null $viewId View ID
	 * @param string|array<string, mixed> $data Data as key - value store
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Updated row returned
	 * 400: Validation error
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateRow(int $rowId, ?int $viewId, $data): DataResponse {
		if (is_string($data)) {
			$data = json_decode($data, true);
		}
		if (!is_array($data)) {
			$this->logger->warning('updateRow not possible, data array invalid.');
			$message = ['message' => $this->l10N->t('Could not update row.')];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int)$key,
				'value' => $value
			];
		}

		try {
			return new DataResponse($this->rowService->updateSet($rowId, $viewId, $dataNew, $this->userId, null)->jsonSerialize());
		} catch (BadRequestError $e) {
			$this->logger->warning('An bad request was encountered: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->translatedMessage ?: $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a row
	 *
	 * @param int $rowId Row ID
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted row returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteRow(int $rowId): DataResponse {
		try {
			return new DataResponse($this->rowService->delete($rowId, null, $this->userId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a row within a view
	 *
	 * @param int $rowId Row ID
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted row returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteRowByView(int $rowId, int $viewId): DataResponse {
		try {
			return new DataResponse($this->rowService->delete($rowId, $viewId, $this->userId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Import from file in to a table
	 *
	 * @param int $tableId Table ID
	 * @param string $path Path to file
	 * @param bool $createMissingColumns Create missing columns
	 * @return DataResponse<Http::STATUS_OK, TablesImportState, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Import status returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function importInTable(int $tableId, string $path, bool $createMissingColumns = true): DataResponse {
		try {
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			return new DataResponse($this->importService->import($tableId, null, $path, $createMissingColumns));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|MultipleObjectsReturnedException $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError|DoesNotExistException $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Import from file in to a table
	 *
	 * @param int $viewId View ID
	 * @param string $path Path to file
	 * @param bool $createMissingColumns Create missing columns
	 * @return DataResponse<Http::STATUS_OK, TablesImportState, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Import status returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function importInView(int $viewId, string $path, bool $createMissingColumns = true): DataResponse {
		try {
			// minimal permission is checked, creating columns requires MANAGE permissions - currently tested on service layer
			return new DataResponse($this->importService->import(null, $viewId, $path, $createMissingColumns));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|MultipleObjectsReturnedException $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError|DoesNotExistException $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	// Api Function for backward compatibility

	/**
	 * Create a share for a table
	 *
	 * @param int $tableId Table ID
	 * @param string $receiver Receiver ID
	 * @param 'user'|'group' $receiverType Receiver type
	 * @param bool $permissionRead Permission if receiver can read data
	 * @param bool $permissionCreate Permission if receiver can create data
	 * @param bool $permissionUpdate Permission if receiver can update data
	 * @param bool $permissionDelete Permission if receiver can delete data
	 * @param bool $permissionManage Permission if receiver can manage table
	 * @return DataResponse<Http::STATUS_OK, TablesShare, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createTableShare(int $tableId, string $receiver, string $receiverType, bool $permissionRead, bool $permissionCreate, bool $permissionUpdate, bool $permissionDelete, bool $permissionManage): DataResponse {
		try {
			return new DataResponse(
				$this->shareService->create(
					$tableId,
					'table',
					$receiver,
					$receiverType,
					$permissionRead,
					$permissionCreate,
					$permissionUpdate,
					$permissionDelete,
					$permissionManage,
					Application::NAV_ENTRY_MODE_ALL
				)->jsonSerialize()
			);
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Create a new column for a table
	 *
	 * @param int $tableId Table ID
	 * @param string $title Title
	 * @param 'text'|'number'|'datetime'|'select'|'usergroup' $type Column main type
	 * @param string|null $subtype Column sub type
	 * @param bool $mandatory Is the column mandatory
	 * @param string|null $description Description
	 * @param string|null $numberPrefix Prefix if the column is a number field
	 * @param string|null $numberSuffix Suffix if the column is a number field
	 * @param float|null $numberDefault Default number, if column is a number
	 * @param float|null $numberMin Min value, if column is a number
	 * @param float|null $numberMax Max number, if column is a number
	 * @param int|null $numberDecimals Number of decimals, if column is a number
	 * @param string|null $textDefault Default text, if column is a text
	 * @param string|null $textAllowedPattern Allowed pattern (regex) for text columns (not yet implemented)
	 * @param int|null $textMaxLength Max length, if column is a text
	 * @param bool|null $textUnique Whether the text value must be unique, if column is a text
	 * @param string|null $selectionOptions Options for a selection (json array{id: int, label: string})
	 * @param string|null $selectionDefault Default option IDs for a selection (json list<int>)
	 * @param string|null $datetimeDefault Default value, if column is datetime
	 * @param string|null $usergroupDefault Default value, if column is usergroup
	 * @param bool|null $usergroupMultipleItems Can select multiple users or/and groups, if column is usergroup
	 * @param bool|null $usergroupSelectUsers Can select users, if column type is usergroup
	 * @param bool|null $usergroupSelectGroups Can select groups, if column type is usergroup
	 * @param bool|null $usergroupSelectTeams Can select teams, if column type is usergroup
	 * @param bool|null $usergroupShowUserStatus Whether to show the user's status, if column type is usergroup
	 * @param list<int>|null $selectedViewIds View IDs where this column should be added to be presented
	 * @param array<string, mixed> $customSettings Custom settings for the column
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createTableColumn(
		int $tableId,
		string $title,
		string $type,
		?string $subtype,
		bool $mandatory,
		?string $description,

		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,
		?bool $textUnique,

		?string $selectionOptions = '',
		?string $selectionDefault = '',

		?string $datetimeDefault = '',

		?string $usergroupDefault = '',
		?bool $usergroupMultipleItems = null,
		?bool $usergroupSelectUsers = null,
		?bool $usergroupSelectGroups = null,
		?bool $usergroupSelectTeams = null,
		?bool $usergroupShowUserStatus = null,
		?array $selectedViewIds = [],
		array $customSettings = [],
	): DataResponse {
		try {
			$item = $this->columnService->create(
				$this->userId,
				$tableId,
				null,
				new ColumnDto(
					title: $title,
					type: $type,
					subtype: $subtype,
					mandatory: $mandatory,
					description: $description,
					textDefault: $textDefault,
					textAllowedPattern: $textAllowedPattern,
					textMaxLength: $textMaxLength,
					textUnique: $textUnique,
					numberDefault: $numberDefault,
					numberMin: $numberMin,
					numberMax: $numberMax,
					numberDecimals: $numberDecimals,
					numberPrefix: $numberPrefix,
					numberSuffix: $numberSuffix,
					selectionOptions: $selectionOptions,
					selectionDefault: $selectionDefault,
					datetimeDefault: $datetimeDefault,
					usergroupDefault: $usergroupDefault,
					usergroupMultipleItems: $usergroupMultipleItems,
					usergroupSelectUsers: $usergroupSelectUsers,
					usergroupSelectGroups: $usergroupSelectGroups,
					usergroupSelectTeams: $usergroupSelectTeams,
					showUserStatus: $usergroupShowUserStatus,
					customSettings: json_encode($customSettings),
				),
				$selectedViewIds
			);
			return new DataResponse($item->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			$this->logger->info('A not found error occurred: ' . $e->getMessage(), ['exception' => $e]);
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}
}
