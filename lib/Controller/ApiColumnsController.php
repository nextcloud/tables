<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ColumnService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
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
	 * @param int $nodeId Node ID
	 * @param 'table'|'view' $nodeType Node type
	 * @return DataResponse<Http::STATUS_OK, list<TablesColumn>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: View deleted
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ)]
	public function index(int $nodeId, string $nodeType): DataResponse {
		try {
			if ($nodeType === 'table') {
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
	 * @param int $id Column ID
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column returned
	 * 403: No permissions
	 * 404: Not found
	 */
	#[NoAdminRequired]
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
	 * @param list<int>|null $selectedViewIds View IDs where this columns should be added
	 * @param array<string, mixed> $customSettings Custom settings for the column
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'baseNodeType', idParam: 'baseNodeId')]
	public function createNumberColumn(int $baseNodeId, string $title, ?float $numberDefault, ?int $numberDecimals, ?string $numberPrefix, ?string $numberSuffix, ?float $numberMin, ?float $numberMax, ?string $subtype = null, ?string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table', array $customSettings = []): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			new ColumnDto(
				title: $title,
				type: 'number',
				subtype: $subtype,
				mandatory: $mandatory,
				description: $description,
				numberDefault: $numberDefault,
				numberMin: $numberMin,
				numberMax: $numberMax,
				numberDecimals: $numberDecimals,
				numberPrefix: $numberPrefix,
				numberSuffix: $numberSuffix,
				customSettings: json_encode($customSettings),
			),
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}

	/**
	 * [api v2] Create new text column
	 *
	 * Specify a subtype to use any special text column
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param string|null $textDefault Default
	 * @param string|null $textAllowedPattern Allowed regex pattern
	 * @param int|null $textMaxLength Max raw text length
	 * @param bool|null $textUnique Whether the text value must be unique, if column is a text
	 * @param 'progress'|'stars'|null $subtype Subtype for the new column
	 * @param string|null $description Description
	 * @param list<int>|null $selectedViewIds View IDs where this columns should be added
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @param array<string, mixed> $customSettings Custom settings for the column
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'baseNodeType', idParam: 'baseNodeId')]
	public function createTextColumn(int $baseNodeId, string $title, ?string $textDefault, ?string $textAllowedPattern, ?int $textMaxLength, ?bool $textUnique = false, ?string $subtype = null, ?string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table', array $customSettings = []): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			new ColumnDto(
				title: $title,
				type: 'text',
				subtype: $subtype,
				mandatory: $mandatory,
				description: $description,
				textDefault: $textDefault,
				textAllowedPattern: $textAllowedPattern,
				textMaxLength: $textMaxLength,
				textUnique: $textUnique,
				customSettings: json_encode($customSettings),
			),
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}

	/**
	 * [api v2] Create new selection column
	 *
	 * Specify a subtype to use any special selection column
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param string $selectionOptions Json array{id: int, label: string} with options that can be selected, eg [{"id": 1, "label": "first"},{"id": 2, "label": "second"}]
	 * @param string|null $selectionDefault Json int|list<int> for default selected option(s), eg 5 or ["1", "8"]
	 * @param 'progress'|'stars'|null $subtype Subtype for the new column
	 * @param string|null $description Description
	 * @param list<int>|null $selectedViewIds View IDs where this columns should be added
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @param array<string, mixed> $customSettings Custom settings for the column
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'baseNodeType', idParam: 'baseNodeId')]
	public function createSelectionColumn(int $baseNodeId, string $title, string $selectionOptions, ?string $selectionDefault, ?string $subtype = null, ?string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table', array $customSettings = []): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			new ColumnDto(
				title: $title,
				type: 'selection',
				subtype: $subtype,
				mandatory: $mandatory,
				description: $description,
				selectionOptions: $selectionOptions,
				selectionDefault: $selectionDefault,
				customSettings: json_encode($customSettings),
			),
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}

	/**
	 * [api v2] Create new datetime column
	 *
	 * Specify a subtype to use any special datetime column
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param 'today'|'now'|null $datetimeDefault For a subtype 'date' you can set 'today'. For a main type or subtype 'time' you can set to 'now'.
	 * @param 'progress'|'stars'|null $subtype Subtype for the new column
	 * @param string|null $description Description
	 * @param list<int>|null $selectedViewIds View IDs where this columns should be added
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @param array<string, mixed> $customSettings Custom settings for the column
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'baseNodeType', idParam: 'baseNodeId')]
	public function createDatetimeColumn(int $baseNodeId, string $title, ?string $datetimeDefault, ?string $subtype = null, ?string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table', array $customSettings = []): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			new ColumnDto(
				title: $title,
				type: 'datetime',
				subtype: $subtype,
				mandatory: $mandatory,
				description: $description,
				datetimeDefault: $datetimeDefault,
				customSettings: json_encode($customSettings),
			),
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}

	/**
	 * [api v2] Create new usergroup column
	 *
	 * @param int $baseNodeId Context of the column creation
	 * @param string $title Title
	 * @param string|null $usergroupDefault Json array{id: string, type: int}, eg [{"id": "admin", "type": 0}, {"id": "user1", "type": 0}]
	 * @param boolean $usergroupMultipleItems Whether you can select multiple users or/and groups
	 * @param boolean $usergroupSelectUsers Whether you can select users
	 * @param boolean $usergroupSelectGroups Whether you can select groups
	 * @param boolean $usergroupSelectTeams Whether you can select teams
	 * @param boolean $showUserStatus Whether to show the user's status
	 * @param string|null $description Description
	 * @param list<int>|null $selectedViewIds View IDs where this columns should be added
	 * @param boolean $mandatory Is mandatory
	 * @param 'table'|'view' $baseNodeType Context type of the column creation
	 * @param array<string, mixed> $customSettings Custom settings for the column
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesColumn, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Column created
	 * 403: No permission
	 * 404: Not found
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'baseNodeType', idParam: 'baseNodeId')]
	public function createUsergroupColumn(int $baseNodeId, string $title, ?string $usergroupDefault, ?bool $usergroupMultipleItems = null, ?bool $usergroupSelectUsers = null, ?bool $usergroupSelectGroups = null, ?bool $usergroupSelectTeams = null, ?bool $showUserStatus = null, ?string $description = null, ?array $selectedViewIds = [], bool $mandatory = false, string $baseNodeType = 'table', array $customSettings = []): DataResponse {
		$tableId = $baseNodeType === 'table' ? $baseNodeId : null;
		$viewId = $baseNodeType === 'view' ? $baseNodeId : null;
		$column = $this->service->create(
			$this->userId,
			$tableId,
			$viewId,
			new ColumnDto(
				title: $title,
				type: 'usergroup',
				mandatory: $mandatory,
				description: $description,
				usergroupDefault: $usergroupDefault,
				usergroupMultipleItems: $usergroupMultipleItems,
				usergroupSelectUsers: $usergroupSelectUsers,
				usergroupSelectGroups: $usergroupSelectGroups,
				usergroupSelectTeams: $usergroupSelectTeams,
				showUserStatus: $showUserStatus,
				customSettings: json_encode($customSettings),
			),
			$selectedViewIds
		);
		return new DataResponse($column->jsonSerialize());
	}
}
