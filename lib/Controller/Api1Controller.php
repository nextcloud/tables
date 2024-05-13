<?php

/** @noinspection DuplicatedCode */

namespace OCA\Tables\Controller;

use Exception;
use OCA\Tables\Api\V1Api;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
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
use OCP\AppFramework\Http\DataResponse;
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
		IRequest     $request,
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
		?string $userId
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
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable[], array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function index(): DataResponse {
		try {
			return new DataResponse($this->tableService->formatTables($this->tableService->findAll($this->userId)));
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new table and return it
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param string $title Title of the table
	 * @param string|null $emoji Emoji for the table
	 * @param string $template Template to use if wanted
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function createTable(string $title, ?string $emoji, string $template = 'custom'): DataResponse {
		try {
			return new DataResponse($this->tableService->create($title, $template, $emoji)->jsonSerialize());
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a table object
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $tableId Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function getTable(int $tableId): DataResponse {
		try {
			return new DataResponse($this->tableService->find($tableId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update tables properties
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
	public function updateTable(int $tableId, string $title = null, string $emoji = null, ?bool $archived = false): DataResponse {
		try {
			return new DataResponse($this->tableService->update($tableId, $title, $emoji, null, $archived, $this->userId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $tableId Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function deleteTable(int $tableId): DataResponse {
		try {
			return new DataResponse($this->tableService->delete($tableId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	// Views

	/**
	 * Get all views for a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $tableId Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesView[], array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Views returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function indexViews(int $tableId): DataResponse {
		try {
			return new DataResponse($this->viewService->formatViews($this->viewService->findAll($this->tableService->find($tableId))));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new view for a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
	public function createView(int $tableId, string $title, ?string $emoji): DataResponse {
		try {
			return new DataResponse($this->viewService->create($title, $emoji, $this->tableService->find($tableId))->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a view object
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function getView(int $viewId): DataResponse {
		try {
			return new DataResponse($this->viewService->find($viewId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update a view via key-value sets
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $viewId View ID
	 * @param array{key: 'title'|'emoji'|'description', value: string}|array{key: 'columns', value: int[]}|array{key: 'sort', value: array{columnId: int, mode: 'ASC'|'DESC'}}|array{key: 'filter', value: array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'is-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty', value: string|int|float}} $data key-value pairs
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: View updated
	 * 403: No permissions
	 * 404: Not found
	 */
	public function updateView(int $viewId, array $data): DataResponse {
		try {
			return new DataResponse($this->viewService->update($viewId, $data)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a view
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesView, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	public function deleteView(int $viewId): DataResponse {
		try {
			return new DataResponse($this->viewService->delete($viewId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	// Shares

	/**
	 * Get a share object
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $shareId Share ID
	 * @return DataResponse<Http::STATUS_OK, TablesShare, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Share returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function getShare(int $shareId): DataResponse {
		try {
			return new DataResponse($this->shareService->find($shareId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get all shares for a view
	 * Will be empty if view does not exist
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesShare[], array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Shares returned
	 */
	public function indexViewShares(int $viewId): DataResponse {
		try {
			return new DataResponse($this->shareService->formatShares($this->shareService->findAll('view', $viewId)));
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all shares for a table
	 * Will be empty if table does not exist
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $tableId Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesShare[], array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Shares returned
	 */
	public function indexTableShares(int $tableId): DataResponse {
		try {
			return new DataResponse($this->shareService->formatShares($this->shareService->findAll('table', $tableId)));
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new share
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $nodeId Node ID
	 * @param 'table'|'view' $nodeType Node type
	 * @param string $receiver Receiver ID
	 * @param 'user'|'group' $receiverType Receiver type
	 * @param bool $permissionRead Permission if receiver can read data
	 * @param bool $permissionCreate Permission if receiver can create data
	 * @param bool $permissionUpdate Permission if receiver can update data
	 * @param bool $permissionDelete Permission if receiver can delete data
	 * @param bool $permissionManage Permission if receiver can manage node
	 * @param int $displayMode context shares only, whether it should appear in nav bar. 0: no, 1: recipients, 2: all
	 * @return DataResponse<Http::STATUS_OK, TablesShare, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Share returned
	 * 403: No permissions
	 * 404: Not found
	 */
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
		int $displayMode = 0,
	): DataResponse {
		try {
			return new DataResponse($this->shareService->create($nodeId, $nodeType, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage, $displayMode)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a share
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $shareId Share ID
	 * @return DataResponse<Http::STATUS_OK, TablesShare, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	public function deleteShare(int $shareId): DataResponse {
		try {
			return new DataResponse($this->shareService->delete($shareId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update a share permission
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
	public function updateSharePermissions(int $shareId, string $permissionType, bool $permissionValue): DataResponse {
		try {
			return new DataResponse($this->shareService->updatePermission($shareId, $permissionType, $permissionValue)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Updates the display mode of a context share
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		}
	}

	// Columns

	/**
	 * Get all columns for a table or a underlying view
	 * Return an empty array if no columns were found
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $tableId Table ID
	 * @param int|null $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesColumn[], array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	public function indexTableColumns(int $tableId, ?int $viewId): DataResponse {
		try {
			return new DataResponse($this->columnService->formatColumns($this->columnService->findAllByTable($tableId, $viewId)));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get all columns for a view
	 * Return an empty array if no columns were found
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesColumn[], array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	public function indexViewColumns(int $viewId): DataResponse {
		try {
			return new DataResponse($this->columnService->formatColumns($this->columnService->findAllByView($viewId)));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Create a column
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
	 * @param string|null $selectionOptions Options for a selection (json array{id: int, label: string})
	 * @param string|null $selectionDefault Default option IDs for a selection (json int[])
	 * @param string|null $datetimeDefault Default value, if column is datetime
	 * @param string|null $usergroupDefault Default value, if column is usergroup (json array{id: string, icon: string, isUser: bool, displayName: string})
	 * @param bool|null $usergroupMultipleItems Can select multiple users or/and groups, if column is usergroup
	 * @param bool|null $usergroupSelectUsers Can select users, if column type is usergroup
	 * @param bool|null $usergroupSelectGroups Can select groups, if column type is usergroup
	 * @param bool|null $showUserStatus Whether to show the user's status, if column type is usergroup
	 * @param int[]|null $selectedViewIds View IDs where this column should be added to be presented
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permissions
	 * 404: Not found
	 */
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

		?string $selectionOptions = '',
		?string $selectionDefault = '',

		?string $datetimeDefault = '',

		?string $usergroupDefault = '',
		?bool $usergroupMultipleItems,
		?bool $usergroupSelectUsers,
		?bool $usergroupSelectGroups,
		?bool $showUserStatus,

		?array $selectedViewIds = []
	): DataResponse {
		try {
			return new DataResponse($this->columnService->create(
				$this->userId,
				$tableId,
				$viewId,
				$type,
				$subtype,
				$title,
				$mandatory,
				$description,

				$textDefault,
				$textAllowedPattern,
				$textMaxLength,

				$numberPrefix,
				$numberSuffix,
				$numberDefault,
				$numberMin,
				$numberMax,
				$numberDecimals,

				$selectionOptions,
				$selectionDefault,

				$datetimeDefault,

				$usergroupDefault,
				$usergroupMultipleItems,
				$usergroupSelectUsers,
				$usergroupSelectGroups,
				$showUserStatus,

				$selectedViewIds
			)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update a column
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
	 * @param string|null $selectionOptions Options for a selection (json array{id: int, label: string})
	 * @param string|null $selectionDefault Default option IDs for a selection (json int[])
	 * @param string|null $datetimeDefault Default value, if column is datetime
	 * @param string|null $usergroupDefault Default value, if column is usergroup
	 * @param bool|null $usergroupMultipleItems Can select multiple users or/and groups, if column is usergroup
	 * @param bool|null $usergroupSelectUsers Can select users, if column type is usergroup
	 * @param bool|null $usergroupSelectGroups Can select groups, if column type is usergroup
	 * @param bool|null $showUserStatus Whether to show the user's status, if column type is usergroup
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Updated column
	 */
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

		?string $selectionOptions,
		?string $selectionDefault,

		?string $datetimeDefault,

		?string $usergroupDefault,
		?bool $usergroupMultipleItems,
		?bool $usergroupSelectUsers,
		?bool $usergroupSelectGroups,
		?bool $showUserStatus,

	): DataResponse {
		try {
			$item = $this->columnService->update(
				$columnId,
				null,
				$this->userId,
				null,
				$subtype,
				$title,
				$mandatory,
				$description,

				$textDefault,
				$textAllowedPattern,
				$textMaxLength,

				$numberPrefix,
				$numberSuffix,
				$numberDefault,
				$numberMin,
				$numberMax,
				$numberDecimals,

				$selectionOptions,
				$selectionDefault,
				$datetimeDefault,

				$usergroupDefault,
				$usergroupMultipleItems,
				$usergroupSelectUsers,
				$usergroupSelectGroups,
				$showUserStatus,
			);
			return new DataResponse($item->jsonSerialize());
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Returns a column object
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $columnId Wanted Column ID
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function getColumn(int $columnId): DataResponse {
		try {
			return new DataResponse($this->columnService->find($columnId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a column
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $columnId Wanted Column ID
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted column returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function deleteColumn(int $columnId): DataResponse {
		try {
			return new DataResponse($this->columnService->delete($columnId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List all rows values for a table, first row are the column titles
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $tableId Table ID
	 * @param int|null $limit Limit
	 * @param int|null $offset Offset
	 * @return DataResponse<Http::STATUS_OK, string[], array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Row values returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function indexTableRowsSimple(int $tableId, ?int $limit, ?int $offset): DataResponse {
		try {
			return new DataResponse($this->v1Api->getData($tableId, $limit, $offset, $this->userId));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List all rows for a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $tableId Table ID
	 * @param int|null $limit Limit
	 * @param int|null $offset Offset
	 * @return DataResponse<Http::STATUS_OK, TablesRow[], array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Rows returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function indexTableRows(int $tableId, ?int $limit, ?int $offset): DataResponse {
		try {
			return new DataResponse($this->rowService->formatRows($this->rowService->findAllByTable($tableId, $this->userId, $limit, $offset)));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * List all rows for a view
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $viewId View ID
	 * @param int|null $limit Limit
	 * @param int|null $offset Offset
	 * @return DataResponse<Http::STATUS_OK, TablesRow[], array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Rows returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function indexViewRows(int $viewId, ?int $limit, ?int $offset): DataResponse {
		try {
			return new DataResponse($this->rowService->formatRows($this->rowService->findAllByView($viewId, $this->userId, $limit, $offset)));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a row within a view
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $viewId View ID
	 * @param string|array{columnId: int, value: mixed} $data Data as key - value store
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row returned
	 * 403: No permissions
	 */
	public function createRowInView(int $viewId, $data): DataResponse {
		if(is_string($data)) {
			$data = json_decode($data, true);
		}
		if(!is_array($data)) {
			$this->logger->warning('createRowInView not possible, data array invalid.');
			$message = ['message' => $this->l10N->t('Could not create row.')];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int) $key,
				'value' => $value
			];
		}

		try {
			return new DataResponse($this->rowService->create(null, $viewId, $dataNew)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a row within a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $tableId Table ID
	 * @param string|array{columnId: int, value: mixed} $data Data as key - value store
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function createRowInTable(int $tableId, $data): DataResponse {
		if(is_string($data)) {
			$data = json_decode($data, true);
		}
		if(!is_array($data)) {
			$this->logger->warning('createRowInTable not possible, data array invalid.');
			$message = ['message' => $this->l10N->t('Could not create row.')];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int) $key,
				'value' => $value
			];
		}

		try {
			return new DataResponse($this->rowService->create($tableId, null, $dataNew)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|Exception $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Get a row
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $rowId Row ID
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Row returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function getRow(int $rowId): DataResponse {
		try {
			return new DataResponse($this->rowService->find($rowId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update a row
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $rowId Row ID
	 * @param int|null $viewId View ID
	 * @param string|array{columnId: int, value: mixed} $data Data as key - value store
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Updated row returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function updateRow(int $rowId, ?int $viewId, $data): DataResponse {
		if(is_string($data)) {
			$data = json_decode($data, true);
		}
		if(!is_array($data)) {
			$this->logger->warning('updateRow not possible, data array invalid.');
			$message = ['message' => $this->l10N->t('Could not update row.')];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int) $key,
				'value' => $value
			];
		}

		try {
			return new DataResponse($this->rowService->updateSet($rowId, $viewId, $dataNew, $this->userId)->jsonSerialize());
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a row
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $rowId Row ID
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted row returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function deleteRow(int $rowId): DataResponse {
		try {
			return new DataResponse($this->rowService->delete($rowId, null, $this->userId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Delete a row within a view
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 *
	 * @param int $rowId Row ID
	 * @param int $viewId View ID
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted row returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function deleteRowByView(int $rowId, int $viewId): DataResponse {
		try {
			return new DataResponse($this->rowService->delete($rowId, $viewId, $this->userId)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Import from file in to a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @param int $tableId Table ID
	 * @param string $path Path to file
	 * @param bool $createMissingColumns Create missing columns
	 * @return DataResponse<Http::STATUS_OK, TablesImportState, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Import status returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function importInTable(int $tableId, string $path, bool $createMissingColumns = true): DataResponse {
		try {
			return new DataResponse($this->importService->import($tableId, null, $path, $createMissingColumns));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|MultipleObjectsReturnedException $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError|DoesNotExistException $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Import from file in to a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 * @param int $viewId View ID
	 * @param string $path Path to file
	 * @param bool $createMissingColumns Create missing columns
	 * @return DataResponse<Http::STATUS_OK, TablesImportState, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Import status returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function importInView(int $viewId, string $path, bool $createMissingColumns = true): DataResponse {
		try {
			return new DataResponse($this->importService->import(null, $viewId, $path, $createMissingColumns));
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError|MultipleObjectsReturnedException $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError|DoesNotExistException $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	// Api Function for backward compatibility

	/**
	 * Create a share for a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
	public function createTableShare(int $tableId, string $receiver, string $receiverType, bool $permissionRead, bool $permissionCreate, bool $permissionUpdate, bool $permissionDelete, bool $permissionManage): DataResponse {
		try {
			return new DataResponse($this->shareService->create($tableId, 'table', $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage, 0)->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotFoundError $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Create a new column for a table
	 *
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
	 * @param string|null $selectionOptions Options for a selection (json array{id: int, label: string})
	 * @param string|null $selectionDefault Default option IDs for a selection (json int[])
	 * @param string|null $datetimeDefault Default value, if column is datetime
	 * @param string|null $usergroupDefault Default value, if column is usergroup
	 * @param bool|null $usergroupMultipleItems Can select multiple users or/and groups, if column is usergroup
	 * @param bool|null $usergroupSelectUsers Can select users, if column type is usergroup
	 * @param bool|null $usergroupSelectGroups Can select groups, if column type is usergroup
	 * @param bool|null $showUserStatus Whether to show the user's status, if column type is usergroup
	 * @param int[]|null $selectedViewIds View IDs where this column should be added to be presented
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permissions
	 * 404: Not found
	 */
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

		?string $selectionOptions = '',
		?string $selectionDefault = '',

		?string $datetimeDefault = '',

		?string $usergroupDefault = '',
		?bool $usergroupMultipleItems,
		?bool $usergroupSelectUsers,
		?bool $usergroupSelectGroups,
		?bool $showUserStatus,

		?array $selectedViewIds = []
	): DataResponse {
		try {
			$item = $this->columnService->create(
				$this->userId,
				$tableId,
				null,
				$type,
				$subtype,
				$title,
				$mandatory,
				$description,

				$textDefault,
				$textAllowedPattern,
				$textMaxLength,

				$numberPrefix,
				$numberSuffix,
				$numberDefault,
				$numberMin,
				$numberMax,
				$numberDecimals,

				$selectionOptions,
				$selectionDefault,

				$datetimeDefault,

				$usergroupDefault,
				$usergroupMultipleItems,
				$usergroupSelectUsers,
				$usergroupSelectGroups,
				$showUserStatus,

				$selectedViewIds
			);
			return new DataResponse($item->jsonSerialize());
		} catch (PermissionError $e) {
			$this->logger->warning('A permission error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_FORBIDDEN);
		} catch (InternalError $e) {
			$this->logger->warning('An internal error or exception occurred: '.$e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (DoesNotExistException $e) {
			$this->logger->warning('A not found error occurred: ' . $e->getMessage());
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}



	}
}
