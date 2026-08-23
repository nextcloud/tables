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
use OCA\Tables\Model\RowDataInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\RowService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
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
	 * [api v2] Get a number of rows from a table or view
	 *
	 * Both `filter` and `sort` are passed as JSON encoded strings.
	 *
	 * The filter is a list of filter groups, each group being a list of single
	 * filter definitions. Definitions within a group are AND-connected, while
	 * the groups themselves are OR-connected.
	 *
	 * When reading from a view, the provided filter is added to each of the
	 * view's existing filter groups, so the view's base rules are always
	 * enforced.
	 *
	 * A provided sort order overrides the view's default sort order. The view's
	 * default sort order is only used when no sort order is provided.
	 *
	 * @param 'tables'|'views' $nodeCollection Indicates whether to read from a table or a view
	 * @psalm-param int<0,max> $nodeId The ID of the table or view
	 * @psalm-param ?int<1,500> $limit Number of rows to return between 1 and 500, fetches all by default (optional)
	 * @psalm-param ?int<0,max> $offset Offset of the rows to be returned (optional)
	 * @param ?string $filter JSON encoded list of filter groups. Definitions within a group are AND-connected, groups are OR-connected, e.g. `[[{"columnId":1,"operator":"contains","value":"foo"}]]` (optional)
	 * @param ?string $sort JSON encoded list of sort rules, e.g. `[{"columnId":1,"mode":"ASC"}]` (optional)
	 * @return DataResponse<Http::STATUS_OK, list<TablesRow>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
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
		requirements: ['nodeCollection' => '(tables|views)', 'nodeId' => '(\\d+)']
	)]
	public function getRows(string $nodeCollection, int $nodeId, ?int $limit = null, ?int $offset = null, ?string $filter = null, ?string $sort = null, ?string $search = null): DataResponse {
		try {
			if (($limit !== null && ($limit <= 0 || $limit > 500))
				|| ($offset !== null && $offset < 0)
			) {
				throw new InvalidArgumentException('Offset or limit parameter is out of bounds');
			}

			$queryData = RowQuery::buildFromInput(
				nodeType: $nodeCollection,
				nodeId: $nodeId,
				userId: $this->userId,
				limit: $limit,
				offset: $offset,
				filter: $filter,
				sort: $sort,
				search: $search,
			);

			$rows = $this->rowService->findAllByQuery($queryData);
			return new DataResponse($this->rowService->formatRows($rows));
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|InvalidArgumentException $e) {
			return $this->handleBadRequestError(new BadRequestError($e->getMessage(), $e->getCode(), $e));
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, typeParam: 'nodeCollection')]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/2/{nodeCollection}/{nodeId}/rows/count',
		requirements: ['nodeCollection' => '(tables|views)', 'nodeId' => '(\\d+)']
	)]
	public function countRows(string $nodeCollection, int $nodeId, ?string $filter = null, ?string $sort = null, ?string $search = null): DataResponse {
		try {
			$queryData = RowQuery::buildFromInput(
				nodeType: $nodeCollection,
				nodeId: $nodeId,
				userId: $this->userId,
				filter: $filter,
				sort: $sort,
				search: $search,
			);

			$count = $this->rowService->countByQuery($queryData);
			return new DataResponse(['count' => $count]);
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|InvalidArgumentException $e) {
			return $this->handleBadRequestError(new BadRequestError($e->getMessage(), $e->getCode(), $e));
		} catch (InternalError|\Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Export all rows from a table or view as a CSV file
	 *
	 * @param string $nodeCollection 'tables' or 'views'
	 * @param int $nodeId The table or view ID
	 * @param ?string $filter JSON encoded list of filter groups (optional)
	 * @param ?string $sort JSON encoded list of sort rules (optional)
	 * @param ?string $search Search string (optional)
	 * @param ?string $rowIds JSON encoded list of row IDs to export (optional)
	 * @return DataDownloadResponse<Http::STATUS_OK, array{headers: array<string, string>}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: CSV file is returned
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, typeParam: 'nodeCollection')]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/2/{nodeCollection}/{nodeId}/rows/export',
		requirements: ['nodeCollection' => '(tables|views)', 'nodeId' => '(\d+)']
	)]
	public function exportRows(string $nodeCollection, int $nodeId, ?string $filter = null, ?string $sort = null, ?string $search = null, ?string $rowIds = null): DataDownloadResponse|DataResponse {
		try {
			$queryData = RowQuery::buildFromInput(
				nodeType: $nodeCollection,
				nodeId: $nodeId,
				userId: $this->userId,
				filter: $filter,
				sort: $sort,
				search: $search,
				rowIds: $rowIds,
			);

			$csv = $this->rowService->exportCsv($queryData);
			return new DataDownloadResponse($csv, 'export.csv', 'text/csv');
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|InvalidArgumentException $e) {
			return $this->handleBadRequestError(new BadRequestError($e->getMessage(), $e->getCode(), $e));
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}

}
