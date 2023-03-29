<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Api\V1Api;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class Api1Controller extends ApiController {
	private TableService $tableService;
	private ShareService $shareService;
	private ColumnService $columnService;

	private V1Api $v1Api;

	private string $userId;

	use Errors;


	public function __construct(
		IRequest     $request,
		TableService $service,
		ShareService $shareService,
		ColumnService $columnService,
		V1Api $v1Api,
		string $userId
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->tableService = $service;
		$this->shareService = $shareService;
		$this->columnService = $columnService;
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
	public function data(int $tableId, ?int $limit, ?int $offset): DataResponse {
		return $this->handleError(function () use ($tableId, $limit, $offset) {
			return $this->v1Api->getData($tableId, $limit, $offset);
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
	public function updateTable(int $tableId, string $title, string $emoji = null): DataResponse {
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
}
