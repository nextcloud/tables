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
	public function getRows(string $nodeCollection, int $nodeId, ?int $limit = null, ?int $offset = null, ?string $filter = null, ?string $sort = null): DataResponse {
		try {
			if (($limit !== null && ($limit <= 0 || $limit > 500))
				|| ($offset !== null && $offset < 0)
			) {
				throw new InvalidArgumentException('Offset or limit parameter is out of bounds');
			}

			$queryData = new RowQuery(
				nodeType: $nodeCollection === 'tables' ? Application::NODE_TYPE_TABLE : Application::NODE_TYPE_VIEW,
				nodeId: $nodeId,
			);
			$queryData->setLimit($limit)
				->setOffset($offset)
				// the provided filter is set here; any filter defined on a view
				// is merged in on the service level
				->setFilter($this->parseFilter($filter))
				->setSort($this->parseSort($sort))
				->setUserId($this->userId);

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

	/**
	 * Decode and validate the JSON encoded filter parameter.
	 *
	 * @return list<list<array{columnId: int, operator: string, value: string|int|float}>>|null
	 * @throws InvalidArgumentException
	 */
	protected function parseFilter(?string $filter): ?array {
		if ($filter === null || $filter === '') {
			return null;
		}
		$decoded = json_decode($filter, true);
		if (!is_array($decoded)) {
			throw new InvalidArgumentException('Invalid filter supplied');
		}
		foreach ($decoded as $filterGroup) {
			if (!is_array($filterGroup)) {
				throw new InvalidArgumentException('Invalid filter supplied');
			}
			foreach ($filterGroup as $singleFilter) {
				$this->assertFilterValue($singleFilter);
			}
		}
		return $decoded;
	}

	/**
	 * Decode and validate the JSON encoded sort parameter.
	 *
	 * @return list<array{columnId: int, mode: 'ASC'|'DESC'}>|null
	 * @throws InvalidArgumentException
	 */
	protected function parseSort(?string $sort): ?array {
		if ($sort === null || $sort === '') {
			return null;
		}
		$decoded = json_decode($sort, true);
		if (!is_array($decoded)) {
			throw new InvalidArgumentException('Invalid sort data supplied');
		}
		foreach ($decoded as $singleSortRule) {
			$this->assertSortValue($singleSortRule);
		}
		return $decoded;
	}

	/**
	 * @throws InvalidArgumentException
	 */
	protected function assertFilterValue(mixed $filter): void {
		if (!is_array($filter)
			|| !isset($filter['columnId'], $filter['operator'], $filter['value'])
			|| count($filter) !== 3
		) {
			throw new InvalidArgumentException('Invalid filter supplied');
		}
		// values higher than PHP_INT_MAX will be capped to PHP_INT_MAX on cast,
		// checking it roughly is sufficient.
		// the lower value boundary is the lowest meta column id in \OCA\Tables\Db\Column
		$maxDigits = strlen((string)PHP_INT_MAX);
		if (!is_numeric($filter['columnId'])
			|| (int)$filter['columnId'] < -5
			|| !preg_match('/^-?\\d{0,' . $maxDigits . '}$/', (string)$filter['columnId'])
		) {
			throw new InvalidArgumentException(sprintf('Invalid column id supplied: %s', (string)$filter['columnId']));
		}
		if (!in_array($filter['operator'], [
			'begins-with',
			'ends-with',
			'contains',
			'is-equal',
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
	 * @throws InvalidArgumentException
	 */
	protected function assertSortValue(mixed $sort): void {
		if (!is_array($sort)
			|| !isset($sort['columnId'], $sort['mode'])
			|| count($sort) !== 2
		) {
			throw new InvalidArgumentException('Invalid sort data supplied');
		}
		// values higher than PHP_INT_MAX will be capped to PHP_INT_MAX on cast,
		// checking it roughly is sufficient.
		// the lower value boundary is the lowest meta column id in \OCA\Tables\Db\Column
		$maxDigits = strlen((string)PHP_INT_MAX);
		if (!is_numeric($sort['columnId'])
			|| (int)$sort['columnId'] < -5
			|| !preg_match('/^-?\\d{0,' . $maxDigits . '}$/', (string)$sort['columnId'])
		) {
			throw new InvalidArgumentException('Invalid column id supplied');
		}
		if ($sort['mode'] !== 'DESC' && $sort['mode'] !== 'ASC') {
			throw new InvalidArgumentException('Invalid sort mode supplied');
		}
	}
}
