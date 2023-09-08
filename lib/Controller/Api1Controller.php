<?php

namespace OCA\Tables\Controller;

use OCA\Tables\Api\V1Api;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ImportService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class Api1Controller extends ApiController {
	private TableService $tableService;
	private ShareService $shareService;
	private ColumnService $columnService;
	private RowService $rowService;
	private ImportService $importService;
	private ViewService $viewService;
	private ViewMapper $viewMapper;

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
	}

	// Tables

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function index(): DataResponse {
		return $this->handleError(function () {
			return $this->tableService->findAll($this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function createTable(string $title, ?string $emoji, string $template = 'custom'): DataResponse {
		return $this->handleError(function () use ($title, $emoji, $template) {
			return $this->tableService->create($title, $template, $emoji);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getTable(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->tableService->find($tableId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function updateTable(int $tableId, string $title = null, string $emoji = null): DataResponse {
		return $this->handleError(function () use ($tableId, $title, $emoji) {
			return $this->tableService->update($tableId, $title, $emoji, $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteTable(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->tableService->delete($tableId);
		});
	}

	// Views

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexViews(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->viewService->findAll($this->tableService->find($tableId));
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function createView(int $tableId, string $title, ?string $emoji): DataResponse {
		return $this->handleError(function () use ($tableId, $title, $emoji) {
			return $this->viewService->create($title, $emoji, $this->tableService->find($tableId));
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->viewService->find($viewId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function updateView(int $viewId, array $data): DataResponse {
		return $this->handleError(function () use ($viewId, $data) {
			return $this->viewService->update($viewId, $data);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->viewService->delete($viewId);
		});
	}

	// Shares

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getShare(int $shareId): DataResponse {
		return $this->handleError(function () use ($shareId) {
			return $this->shareService->find($shareId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexViewShares(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->shareService->findAll('view', $viewId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexTableShares(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->shareService->findAll('table', $tableId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function createShare(int $nodeId, string $nodeType, string $receiver, string $receiverType, bool $permissionRead = false, bool $permissionCreate = false, bool $permissionUpdate = false, bool $permissionDelete = false, bool $permissionManage = false): DataResponse {
		return $this->handleError(function () use ($nodeId, $nodeType, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage) {
			return $this->shareService->create($nodeId, $nodeType, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteShare(int $shareId): DataResponse {
		return $this->handleError(function () use ($shareId) {
			return $this->shareService->delete($shareId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function updateSharePermissions(int $shareId, string $permissionType, bool $permissionValue): DataResponse {
		return $this->handleError(function () use ($shareId, $permissionType, $permissionValue) {
			return $this->shareService->updatePermission($shareId, $permissionType, $permissionValue);
		});
	}

	// Columns

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexTableColumns(int $tableId, ?int $viewId): DataResponse {
		return $this->handleError(function () use ($tableId, $viewId) {
			return $this->columnService->findAllByTable($tableId, $viewId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexViewColumns(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->columnService->findAllByView($viewId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
		?array $selectedViewIds = []
	): DataResponse {
		return $this->handleError(function () use (
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
			$selectedViewIds
		) {
			return $this->columnService->create(
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
				$selectedViewIds
			);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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

		?string $datetimeDefault
	): DataResponse {
		return $this->handleError(function () use (
			$columnId,
			$title,
			$subtype,
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

			$datetimeDefault
		) {
			return $this->columnService->update(
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
				$datetimeDefault
			);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getColumn(int $columnId): DataResponse {
		return $this->handleError(function () use ($columnId) {
			return $this->columnService->find($columnId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteColumn(int $columnId): DataResponse {
		return $this->handleError(function () use ($columnId) {
			return $this->columnService->delete($columnId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexTableRowsSimple(int $tableId, ?int $limit, ?int $offset): DataResponse {
		return $this->handleError(function () use ($tableId, $limit, $offset) {
			return $this->v1Api->getData($tableId, $limit, $offset);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexTableRows(int $tableId, ?int $limit, ?int $offset): DataResponse {
		return $this->handleError(function () use ($tableId, $limit, $offset) {
			return $this->rowService->findAllByTable($tableId, $limit, $offset);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexViewRows(int $viewId, ?int $limit, ?int $offset): DataResponse {
		return $this->handleError(function () use ($viewId, $limit, $offset) {
			return $this->rowService->findAllByView($viewId, $this->userId, $limit, $offset);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function createRowInView(int $viewId, array $data): DataResponse {
		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int) $key,
				'value' => $value
			];
		}

		return $this->handleError(function () use ($viewId, $dataNew) {
			return $this->rowService->create(null, $viewId, $dataNew);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function createRowInTable(int $tableId, array $data): DataResponse {
		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int) $key,
				'value' => $value
			];
		}

		return $this->handleError(function () use ($tableId, $dataNew) {
			return $this->rowService->create($tableId, null, $dataNew);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getRow(int $rowId): DataResponse {
		return $this->handleError(function () use ($rowId) {
			return $this->rowService->find($rowId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function updateRow(int $rowId, ?int $viewId, array $data): DataResponse {
		$dataNew = [];
		foreach ($data as $key => $value) {
			$dataNew[] = [
				'columnId' => (int) $key,
				'value' => $value
			];
		}
		return $this->handleError(function () use ($rowId, $viewId, $dataNew) {
			return $this->rowService->updateSet($rowId, $viewId, $dataNew, $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteRow(int $rowId): DataResponse {
		return $this->handleError(function () use ($rowId) {
			return $this->rowService->delete($rowId, null, $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteRowByView(int $rowId, int $viewId): DataResponse {
		return $this->handleError(function () use ($rowId, $viewId) {
			return $this->rowService->delete($rowId, $viewId, $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function importInTable(int $tableId, string $path, bool $createMissingColumns = true): DataResponse {
		return $this->handleError(function () use ($tableId, $path, $createMissingColumns) {
			return $this->importService->import($tableId, null, $path, $createMissingColumns);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function importInView(int $viewId, string $path, bool $createMissingColumns = true): DataResponse {
		return $this->handleError(function () use ($viewId, $path, $createMissingColumns) {
			return $this->importService->import(null, $viewId, $path, $createMissingColumns);
		});
	}

	// Api Function for backward compatibility

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function createTableShare(int $tableId, string $receiver, string $receiverType, bool $permissionRead, bool $permissionCreate, bool $permissionUpdate, bool $permissionDelete, bool $permissionManage): DataResponse {
		return $this->handleError(function () use ($tableId, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage) {
			return $this->shareService->create($tableId, 'table', $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
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
		?array $selectedViewIds = []
	): DataResponse {
		return $this->handleError(function () use (
			$tableId,
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
			$selectedViewIds
		) {
			return $this->columnService->create(
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
				$selectedViewIds
			);
		});
	}
}
