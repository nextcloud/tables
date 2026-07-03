<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ConversionHelper;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Model\RowDataInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesRow from ResponseDefinitions
 */
class RowOCSController extends AOCSController {

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		IL10N $n,
		string $userId,
		protected RowService $rowService,
		private TableService $tableService,
		private ViewService $viewService,
		private FederationService $federationService,
	) {
		parent::__construct($request, $logger, $n, $userId);
	}

	/**
	 * [api v2] Create a new row in a table or a view
	 *
	 * @param 'tables'|'views' $nodeCollection Indicates whether to create a row on a table or view
	 * @param int $nodeId The identifier of the targeted table or view
	 * @param string|array<string, mixed> $data An array containing the column identifiers and their values
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row returned
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_CREATE, typeParam: 'nodeCollection')]
	#[ApiRoute(verb: 'POST', url: '/api/2/{nodeCollection}/{nodeId}/rows', requirements: ['nodeCollection' => '(tables|views)', 'nodeId' => '(\d+)'])]
	public function createRow(string $nodeCollection, int $nodeId, mixed $data): DataResponse {
		if (is_string($data)) {
			$data = json_decode($data, true);
		}
		if (!is_array($data)) {
			return $this->handleBadRequestError(new BadRequestError('Cannot create row: data input is invalid.'));
		}

		$iNodeType = ConversionHelper::stringNodeType2Const($nodeCollection);
		$tableId = $viewId = null;
		if ($iNodeType === Application::NODE_TYPE_TABLE) {
			$tableId = $nodeId;
			if ($this->federationService->isNodeFederated($tableId, 'table')) {
				$table = $this->tableService->find($nodeId, true);
				return new DataResponse($this->federationService->createRow($table, $data));
			}
		} elseif ($iNodeType === Application::NODE_TYPE_VIEW) {
			$viewId = $nodeId;
			if ($this->federationService->isNodeFederated($viewId, 'view')) {
				$view = $this->viewService->find($nodeId, false, $this->userId);
				return new DataResponse($this->federationService->createRow($view, $data));
			}
		}

		$newRowData = new RowDataInput();
		foreach ($data as $key => $value) {
			$newRowData->add((int)$key, $value);
		}

		try {
			return new DataResponse($this->rowService->create($tableId, $viewId, $newRowData)->jsonSerialize());
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError|\Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Update a row in a table or a view
	 *
	 * @param 'tables'|'views' $nodeCollection Indicates whether to update a row on a table or view
	 * @param int $nodeId The identifier of the targeted table or view
	 * @param int $rowId The identifier of the row to update
	 * @param string|array<string, mixed> $data An array containing the column identifiers and their values
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row updated
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_UPDATE, typeParam: 'nodeCollection')]
	#[ApiRoute(verb: 'PUT', url: '/api/2/{nodeCollection}/{nodeId}/rows/{rowId}', requirements: ['nodeCollection' => '(tables|views)', 'nodeId' => '(\d+)'])]
	public function updateRow(string $nodeCollection, int $nodeId, int $rowId, mixed $data): DataResponse {
		if (is_string($data)) {
			$data = json_decode($data, true);
		}
		if (!is_array($data)) {
			return $this->handleBadRequestError(new BadRequestError('Cannot update row: data input is invalid.'));
		}
		$iNodeType = ConversionHelper::stringNodeType2Const($nodeCollection);
		$tableId = $viewId = null;
		if ($iNodeType === Application::NODE_TYPE_TABLE) {
			$tableId = $nodeId;
			if ($this->federationService->isNodeFederated($tableId, 'table')) {
				$table = $this->tableService->find($nodeId, true);
				return new DataResponse($this->federationService->updateRow($table, $rowId, $data));
			}
		} elseif ($iNodeType === Application::NODE_TYPE_VIEW) {
			$viewId = $nodeId;
			if ($this->federationService->isNodeFederated($viewId, 'view')) {
				$view = $this->viewService->find($nodeId, false, $this->userId);
				return new DataResponse($this->federationService->updateRow($view, $rowId, $data));
			}
		}
		try {
			return new DataResponse($this->rowService->updateSet($rowId, $viewId, $data, $this->userId, $tableId)->jsonSerialize());
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError|\Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Delete a row in a table or a view
	 *
	 * @param 'tables'|'views' $nodeCollection Indicates whether to delete a row on a table or view
	 * @param int $nodeId The identifier of the targeted table or view
	 * @param int $rowId The identifier of the row to delete
	 * @return DataResponse<Http::STATUS_OK, TablesRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row deleted
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_DELETE, typeParam: 'nodeCollection')]
	#[ApiRoute(verb: 'DELETE', url: '/api/2/{nodeCollection}/{nodeId}/rows/{rowId}', requirements: ['nodeCollection' => '(tables|views)', 'nodeId' => '(\d+)'])]
	public function deleteRow(string $nodeCollection, int $nodeId, int $rowId): DataResponse {
		$iNodeType = ConversionHelper::stringNodeType2Const($nodeCollection);
		$tableId = $viewId = null;
		if ($iNodeType === Application::NODE_TYPE_TABLE) {
			$tableId = $nodeId;
			if ($this->federationService->isNodeFederated($tableId, 'table')) {
				$table = $this->tableService->find($nodeId, true);
				return new DataResponse($this->federationService->deleteRow($table, $rowId));
			}
		} elseif ($iNodeType === Application::NODE_TYPE_VIEW) {
			$viewId = $nodeId;
			if ($this->federationService->isNodeFederated($viewId, 'view')) {
				$view = $this->viewService->find($nodeId, false, $this->userId);
				return new DataResponse($this->federationService->deleteRow($view, $rowId));
			}
		}
		try {
			return new DataResponse($this->rowService->delete($rowId, $viewId, $this->userId, $tableId)->jsonSerialize());
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError|\Exception $e) {
			return $this->handleError($e);
		}
	}
}
