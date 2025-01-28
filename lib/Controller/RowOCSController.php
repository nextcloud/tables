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
	 *     row on a table or view
	 * @param int $nodeId The identifier of the targeted table or view
	 * @param string|array<string, mixed> $data An array containing the column
	 *     identifiers and their values
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
	 * @param 'tables'|'views' $nodeCollection Indicates whether to create a
	 *      row on a table or view
	 * @psalm-param int<0,max> $nodeId The ID of the table or view
	 * @psalm-param ?int<1,500> $limit Number of rows to return between 1 and 500, fetches all by default (optional)
	 * @psalm-param ?int<0,max> $offset Offset of the tows to be returned (optional)
	 * @param list<array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'is-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty',value: string|int|float}>|null $filter Additional row filter (optional)
	 * @param list<array{columnId: int, mode: 'ASC'|'DESC'}>|null $sort Custom sort order (optional)
	 * @return DataResponse<Http::STATUS_OK, TablesRow[],
	 *      array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR,
	 *      array{message: string}, array{}>
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
	public function getRows(string $nodeCollection, int $nodeId, ?int $limit, ?int $offset, FilterInput $filterInput, ?array $sort): DataResponse {
		$queryData = new RowQuery(
			nodeType: $nodeCollection === 'tables' ? Application::NODE_TYPE_TABLE : Application::NODE_TYPE_VIEW,
			nodeId: $nodeId,
		);

		// TODO: FilterInput is just a prototype. Rename and put it into a better location (lib/Http/Parameters/Filter?)
		// and move assertion into the class. Do the same for the sort.
		// Discuss this approach (vs. InjectionMiddleware) with someone.

		try {
			if (($limit !== null && ($limit <= 0 || $limit > 500))
				|| ($offset !== null && $offset < 0)
			) {
				// TODO: this check can be removed once NC 32 is the lowest supported server versions,
				// as then the app framework handles nullable ranges
				throw new \InvalidArgumentException('Offset or limit parameter is out of bounds');
			}

			$filter = $filterInput->filter;
			if ($filter) {
				foreach ($filter as $filterGroup) {
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
				// we set the provided filter here, any existing filter
				// definitions (if specified on views) are applied on service level
				->setFilter($filter)
				->setSort($sort)
				->setUserId($this->userId);

			$rows = $this->rowService->findAllByQuery($queryData);
			return new DataResponse($this->rowService->formatRows($rows));
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|InvalidArgumentException $e) {
			return $this->handleBadRequestError(new BadRequestError($e->getMessage(), $e->getCode(), $e));
		}
	}

	/**
	 * @param array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'is-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty',value: string|int|float} $filter
	 */
	protected function assertFilterValue(array $filter): void {
		if (!isset($filter['columnId'], $filter['operator'], $filter['value'])
			|| count($filter) !== 3
		) {
			throw new InvalidArgumentException('Invalid filter supplied');
		}
		// values higher than PHP_INT_MAX will be capped to PHP_INT_MAX on cast,
		// checking it roughly is sufficient
		// lower value boundary is the lowest meta column id in \OCA\Tables\Db\Column
		$maxDigits = strlen((string)PHP_INT_MAX);
		if (!is_numeric($filter['columnId'])
			|| (int)$filter['columnId'] < -5
			|| !preg_match('/^\d{0,' . $maxDigits .'}$/', (string)$filter['columnId'])
		) {
			throw new InvalidArgumentException(sprintf('Invalid column id supplied: %d', $filter['columnId']));
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
	 * @param array{columnId: int, mode: 'ASC'|'DESC'} $sort
	 */
	protected function assertSortValue(array $sort): void {
		if (!isset($sort['columnId'], $sort['mode'])
			|| count($sort) !== 2
		) {
			throw new InvalidArgumentException('Invalid sort data supplied');
		}
		// values higher than PHP_INT_MAX will be capped to PHP_INT_MAX on cast,
		// checking it roughly is sufficient
		// lower value boundary is the lowest meta column id in \OCA\Tables\Db\Column
		$maxDigits = strlen((string)PHP_INT_MAX);
		if (!is_numeric($sort['columnId']
			|| (int)$sort['columnId'] < -5
			|| !preg_match('/^\d{0,' . $maxDigits .'}$/', (string)$sort['columnId'])
		)) {
			throw new InvalidArgumentException('Invalid column id supplied');
		}
		if ($sort['mode'] !== 'DESC' && $sort['mode'] !== 'ASC') {
			throw new InvalidArgumentException('Invalid sort mode supplied');
		}
	}
}
