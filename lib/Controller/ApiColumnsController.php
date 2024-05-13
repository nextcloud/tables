<?php

namespace OCA\Tables\Controller;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ColumnService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesColumn from ResponseDefinitions
 */
class ApiColumnsController extends AOCSController {
	private ColumnService $service;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		ColumnService $service,
		IL10N $n,
		string $userId) {
		parent::__construct($request, $logger, $n, $userId);
		$this->service = $service;
	}

	/**
	 * [api v2] Get all columns for a table or a view
	 *
	 * Return an empty array if no columns were found
	 *
	 * @NoAdminRequired
	 *
	 * @param int $nodeId Node ID
	 * @param 'table'|'view' $nodeType Node type
	 * @return DataResponse<Http::STATUS_OK, TablesColumn[], array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	public function index(int $nodeId, string $nodeType): DataResponse {
		try {
			if($nodeType === 'table') {
				$columns = $this->service->findAllByTable($nodeId);
			} elseif ($nodeType === 'view') {
				$columns = $this->service->findAllByView($nodeId);
			} else {
				$columns = null;
			}
			return new DataResponse($this->service->formatColumns($columns));
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}

	/**
	 * [api v2] Get a column object
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Column ID
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column returned
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
	 * [api v2] Create new numbered column
	 *
	 * Specify a subtype to use any special numbered column
	 *
	 * @NoAdminRequired
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @param float|null $numberDefault Default value for new rows
	 * @param int|null $numberDecimals Decimals
	 * @param string|null $numberPrefix Prefix
	 * @param string|null $numberSuffix Suffix
	 * @param float|null $numberMin Min
	 * @param float|null $numberMax Max
	 * @param 'progress'|'stars'|null $subtype Subtype for the new column
	 * @param string|null $description Description
	 * @param int[]|null $selectedViewIds View IDs where this columns should be added
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function createNumberColumn(int $baseNodeId, string $title, ?float $numberDefault, ?int $numberDecimals, ?string $numberPrefix, ?string $numberSuffix, ?float $numberMin, ?float $numberMax, string $subtype = null, string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table'): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			'number',
			$subtype,
			$title,
			$mandatory,
			$description,
			null,
			null,
			null,
			$numberPrefix,
			$numberSuffix,
			$numberDefault,
			$numberMin,
			$numberMax,
			$numberDecimals,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}

	/**
	 * [api v2] Create new text column
	 *
	 * Specify a subtype to use any special text column
	 *
	 * @NoAdminRequired
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param string|null $textDefault Default
	 * @param string|null $textAllowedPattern Allowed regex pattern
	 * @param int|null $textMaxLength Max raw text length
	 * @param 'progress'|'stars'|null $subtype Subtype for the new column
	 * @param string|null $description Description
	 * @param int[]|null $selectedViewIds View IDs where this columns should be added
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function createTextColumn(int $baseNodeId, string $title, ?string $textDefault, ?string $textAllowedPattern, ?int $textMaxLength, string $subtype = null, string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table'): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			'text',
			$subtype,
			$title,
			$mandatory,
			$description,
			$textDefault,
			$textAllowedPattern,
			$textMaxLength,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}

	/**
	 * [api v2] Create new selection column
	 *
	 * Specify a subtype to use any special selection column
	 *
	 * @NoAdminRequired
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param string $selectionOptions Json array{id: int, label: string} with options that can be selected, eg [{"id": 1, "label": "first"},{"id": 2, "label": "second"}]
	 * @param string|null $selectionDefault Json int|int[] for default selected option(s), eg 5 or ["1", "8"]
	 * @param 'progress'|'stars'|null $subtype Subtype for the new column
	 * @param string|null $description Description
	 * @param int[]|null $selectedViewIds View IDs where this columns should be added
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function createSelectionColumn(int $baseNodeId, string $title, string $selectionOptions, ?string $selectionDefault, string $subtype = null, string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table'): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			'selection',
			$subtype,
			$title,
			$mandatory,
			$description,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$selectionOptions,
			$selectionDefault,
			null,
			null,
			null,
			null,
			null,
			null,
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}

	/**
	 * [api v2] Create new datetime column
	 *
	 * Specify a subtype to use any special datetime column
	 *
	 * @NoAdminRequired
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param 'today'|'now'|null $datetimeDefault For a subtype 'date' you can set 'today'. For a main type or subtype 'time' you can set to 'now'.
	 * @param 'progress'|'stars'|null $subtype Subtype for the new column
	 * @param string|null $description Description
	 * @param int[]|null $selectedViewIds View IDs where this columns should be added
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function createDatetimeColumn(int $baseNodeId, string $title, ?string $datetimeDefault, string $subtype = null, string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table'): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			'text',
			$subtype,
			$title,
			$mandatory,
			$description,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$datetimeDefault,
			null,
			null,
			null,
			null,
			null,
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}

	/**
	 * [api v2] Create new usergroup column
	 *
	 * @NoAdminRequired
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param string|null $usergroupDefault Json array{id: string, isUser: bool, displayName: string}, eg [{"id": "admin", "isUser": true, "displayName": "admin"}, {"id": "user1", "isUser": true, "displayName": "user1"}]
	 * @param boolean $usergroupMultipleItems Whether you can select multiple users or/and groups
	 * @param boolean $usergroupSelectUsers Whether you can select users
	 * @param boolean $usergroupSelectGroups Whether you can select groups
	 * @param boolean $showUserStatus Whether to show the user's status
	 * @param string|null $description Description
	 * @param int[]|null $selectedViewIds View IDs where this columns should be added
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function createUsergroupColumn(int $baseNodeId, string $title, ?string $usergroupDefault, bool $usergroupMultipleItems = null, bool $usergroupSelectUsers = null, bool $usergroupSelectGroups = null, bool $showUserStatus = null, string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table'): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			'usergroup',
			null,
			$title,
			$mandatory,
			$description,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$usergroupDefault,
			$usergroupMultipleItems,
			$usergroupSelectUsers,
			$usergroupSelectGroups,
			$showUserStatus,
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}
}
