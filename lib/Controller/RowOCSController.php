<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use InvalidArgumentException;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\RowQuery;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ConversionHelper;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Model\FilterInput;
use OCA\Tables\Model\RowDataInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
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
		private readonly TableService $tableService,
		private readonly ViewService $viewService,
		private readonly FederationService $federationService,
	) {
		parent::__construct($request, $logger, $n, $userId);
	}

	/**
	 * [api v2] Create a new row in a table or a view
	 *
	 * @param 'tables'|'views' $nodeCollection Indicates whether to create a
	 *                                         row on a table or view
	 * @param int $nodeId The identifier of the targeted table or view
	 * @param string|array<string, mixed> $data An array containing the column
	 *                                          identifiers and their values
	 * @return DataResponse<Http::STATUS_OK, TablesRow,
	 *     array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR,
	 *     array{message: string}, array{}>
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
		} elseif ($iNodeType === Application::NODE_TYPE_VIEW) {
			$viewId = $nodeId;
		}

		$newRowData = new RowDataInput();
		foreach ($data as $key => $value) {
			$newRowData->add((int)$key, $value);
		}

		try {
			if ($tableId !== null && $this->federationService->isNodeFederated($tableId, 'table')) {
				$table = $this->tableService->find($nodeId, true);
				return new DataResponse($this->federationService->createRow($table, $data));
			}
			if ($viewId !== null && $this->federationService->isNodeFederated($viewId, 'view')) {
				$view = $this->viewService->find($nodeId, false, $this->userId);
				return new DataResponse($this->federationService->createRow($view, $data));
			}
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
	 * [api v2] get a number of rows from a table or view
	 *
	 * When reading from views, the specified filter is added to each existing
	 * filter group.
	 *
	 * The filter definitions provided are all AND-connected.
	 *
	 * Sort orders on the other hand do overwrite the view's default sort order.
	 * Only when `null` is passed the default sort order will be used.
	 *
	 * @param 'tables'|'views' $nodeCollection Indicates whether to get rows
	 *                                         from a table or view
	 * @psalm-param int<0,max> $nodeId The ID of the table or view
	 * @psalm-param ?int<1,500> $limit Number of rows to return between 1 and 500, fetches all by default (optional)
	 * @psalm-param ?int<0,max> $offset Offset of the rows to be returned (optional)
	 * @param ?string $filter Additional row filter as JSON-encoded filter groups (optional)
	 * @param list<array{columnId: int, mode: 'ASC'|'DESC'}>|null $sort Custom sort order (optional)
	 * @return DataResponse<Http::STATUS_OK, list<TablesRow>,
	 *     array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR,
	 *     array{message: string}, array{}>
	 *
	 * 200: Rows returned
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, typeParam: 'nodeCollection')]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/2/{nodeCollection}/{nodeId}/rows',
		requirements: ['nodeCollection' => '(tables|views)', 'nodeId' => '(\d+)']
	)]
	public function getRows(string $nodeCollection, int $nodeId, ?int $limit, ?int $offset, mixed $filter = null, ?array $sort = null): DataResponse {
		$queryData = new RowQuery(
			nodeType: $nodeCollection === 'tables' ? Application::NODE_TYPE_TABLE : Application::NODE_TYPE_VIEW,
			nodeId: $nodeId,
		);

		try {
			if (($limit !== null && ($limit <= 0 || $limit > 500))
				|| ($offset !== null && $offset < 0)
			) {
				throw new InvalidArgumentException('Offset or limit parameter is out of bounds');
			}

			$filterInput = FilterInput::fromRequestValue($filter);
			$filterGroups = $filterInput->filter ?: null;
			if ($filterGroups) {
				foreach ($filterGroups as $filterGroup) {
					foreach ($filterGroup as $singleFilter) {
						$this->assertFilterValue($singleFilter);
					}
				}
			}
			if ($sort) {
				foreach ($sort as $singleSortRule) {
					$this->assertSortValue($singleSortRule);
				}
			}
			$queryData->setLimit($limit)
				->setOffset($offset)
				->setFilter($filterGroups)
				->setSort($sort)
				->setUserId($this->userId);

			$rows = $this->rowService->findAllByQuery($queryData);
			return new DataResponse($this->rowService->formatRows($rows));
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|InvalidArgumentException $e) {
			return $this->handleBadRequestError(new BadRequestError($e->getMessage(), $e->getCode(), $e));
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
		} elseif ($iNodeType === Application::NODE_TYPE_VIEW) {
			$viewId = $nodeId;
		}
		try {
			if ($tableId !== null && $this->federationService->isNodeFederated($tableId, 'table')) {
				$table = $this->tableService->find($nodeId, true);
				return new DataResponse($this->federationService->updateRow($table, $rowId, $data));
			}
			if ($viewId !== null && $this->federationService->isNodeFederated($viewId, 'view')) {
				$view = $this->viewService->find($nodeId, false, $this->userId);
				return new DataResponse($this->federationService->updateRow($view, $rowId, $data));
			}
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
		} elseif ($iNodeType === Application::NODE_TYPE_VIEW) {
			$viewId = $nodeId;
		}
		try {
			if ($tableId !== null && $this->federationService->isNodeFederated($tableId, 'table')) {
				$table = $this->tableService->find($nodeId, true);
				return new DataResponse($this->federationService->deleteRow($table, $rowId));
			}
			if ($viewId !== null && $this->federationService->isNodeFederated($viewId, 'view')) {
				$view = $this->viewService->find($nodeId, false, $this->userId);
				return new DataResponse($this->federationService->deleteRow($view, $rowId));
			}
			return new DataResponse($this->rowService->delete($rowId, $viewId, $this->userId, $tableId)->jsonSerialize());
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError|\Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * @param array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty',value: string|int|float} $filter
	 */
	protected function assertFilterValue(array $filter): void {
		if (!isset($filter['columnId'], $filter['operator'], $filter['value'])
			|| count($filter) !== 3
		) {
			throw new InvalidArgumentException('Invalid filter supplied');
		}
		$maxDigits = strlen((string)PHP_INT_MAX);
		if (!is_numeric($filter['columnId'])
			|| (int)$filter['columnId'] < -5
			|| !preg_match('/^\d{0,' . $maxDigits . '}$/', (string)$filter['columnId'])
		) {
			throw new InvalidArgumentException(sprintf('Invalid column id supplied: %d', $filter['columnId']));
		}
		if (!in_array($filter['operator'], [
			'begins-with',
			'ends-with',
			'contains',
			'does-not-contain',
			'is-equal',
			'is-not-equal',
			'is-greater-than',
			'is-greater-than-or-equal',
			'is-lower-than',
			'is-lower-than-or-equal',
			'is-empty',
		], true)) {
			throw new InvalidArgumentException('Invalid filter operator supplied');
		}
	}

	/**
	 * @param array{columnId: int, mode: 'ASC'|'DESC'} $sort
	 */
	protected function assertSortValue(array $sort): void {
		if (!isset($sort['columnId'], $sort['mode'])
			|| count($sort) !== 2
		) {
			throw new InvalidArgumentException('Invalid sort data supplied');
		}
		$maxDigits = strlen((string)PHP_INT_MAX);
		if (!is_numeric($sort['columnId'])
			|| (int)$sort['columnId'] < -5
			|| !preg_match('/^\d{0,' . $maxDigits . '}$/', (string)$sort['columnId'])
		) {
			throw new InvalidArgumentException('Invalid column id supplied');
		}
		if ($sort['mode'] !== 'DESC' && $sort['mode'] !== 'ASC') {
			throw new InvalidArgumentException('Invalid sort mode supplied');
		}
	}
}
