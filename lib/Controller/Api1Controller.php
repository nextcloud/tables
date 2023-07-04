<?php

namespace OCA\Tables\Controller;

use OCA\Tables\Api\V1Api;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ImportService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;

class Api1Controller extends ApiController {
	private TableService $tableService;
	private ShareService $shareService;
	private ColumnService $columnService;
	private RowService $rowService;
	private ImportService $importService;
	private ViewService $viewService;
	private ViewMapper $viewMapper;

	private V1Api $v1Api;

	private string $userId;

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
		string $userId
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
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function index(string $keyword = null, int $limit = 100, int $offset = 0): DataResponse {
		if ($keyword) {
			return $this->handleError(function () use ($keyword, $limit, $offset) {
				return $this->tableService->search($keyword, $limit, $offset);
			});
		} else {
			return $this->handleError(function () {
				return $this->tableService->findAll($this->userId);
			});
		}
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function createImport(int $tableId, string $path, bool $createMissingColumns = true): DataResponse {
		return $this->handleError(function () use ($tableId, $path, $createMissingColumns) {
			return $this->importService->import($tableId, $path, $createMissingColumns);
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
	public function updateTable(int $tableId, ?string $title, ?string $emoji): DataResponse {
		return $this->handleError(function () use ($tableId, $title, $emoji) {
			return $this->tableService->update($tableId, $title, $emoji);
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
	public function deleteTable(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->tableService->delete($tableId);
		});
	}

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
	 * @param int $id
	 * @return Table
	 * @throws Exception
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function getTableFromViewId(int $id): Table {
		$view = $this->viewMapper->find($id);
		return $this->tableService->find($view->getTableId());

	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->viewService->find($viewId, $this->getTableFromViewId($viewId));
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function updateView(int $viewId, array $data): DataResponse {
		return $this->handleError(function () use ($viewId, $data) {
			return $this->viewService->update($viewId, $data, $this->getTableFromViewId($viewId));
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->viewService->delete($viewId, $this->getTableFromViewId($viewId));
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function indexTableColumns(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->columnService->findAllByTable($tableId);
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
	public function createTableColumn(
		int $tableId,
		string $title,
		string $type,
		?string $subtype,
		bool $mandatory,
		?string $description,
		?int $orderWeight,

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

		?string $datetimeDefault = ''
	): DataResponse {
		return $this->handleError(function () use (
			$tableId,
			$type,
			$subtype,
			$title,
			$mandatory,
			$description,
			$orderWeight,

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
			return $this->columnService->create(
				$this->userId,
				$tableId,
				$type,
				$subtype,
				$title,
				$mandatory,
				$description,
				$orderWeight,

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
	public function updateColumn(
		int $columnId,
		?string $title,
		?string $subtype,
		?bool $mandatory,
		?string $description,
		?int $orderWeight,

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
			$orderWeight,

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
				$orderWeight,

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
	public function createRow(int $tableId, string $data): DataResponse {
		$dataNew = [];
		$array = json_decode($data, true);
		foreach ($array as $key => $value) {
			$dataNew[] = [
				'columnId' => (int) $key,
				'value' => $value
			];
		}

		return $this->handleError(function () use ($dataNew, $tableId) {
			return $this->rowService->createComplete($tableId, $dataNew);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function updateRow(int $rowId, string $data): DataResponse {
		$dataNew = [];
		$array = json_decode($data, true);
		foreach ($array as $key => $value) {
			$dataNew[] = [
				'columnId' => (int) $key,
				'value' => $value
			];
		}

		return $this->handleError(function () use ($rowId, $dataNew) {
			return $this->rowService->updateSet($rowId, $dataNew);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteRow(int $rowId): DataResponse {
		return $this->handleError(function () use ($rowId) {
			return $this->rowService->delete($rowId);
		});
	}
}
