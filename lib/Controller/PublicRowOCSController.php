<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\RowQuery;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\AssertShareAccessIsAccessible;
use OCA\Tables\Model\RowDataInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ValueObject\ShareToken;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesPublicRow from ResponseDefinitions
 */
class PublicRowOCSController extends AOCSController {

	public function __construct(
		protected ShareService $shareService,
		protected RowService $rowService,
		protected Row2Mapper $row2Mapper,
		IRequest $request,
		LoggerInterface $logger,
		IL10N $l,
	) {
		parent::__construct($request, $logger, $l, '');
		$this->rowService->setPublicContext();
	}

	/**
	 * [api v2] Fetch all rows from a link share
	 *
	 * @param string $token The share token
	 * @psalm-param ?int<1,500> $limit Number of rows to return between 1 and 500, fetches all by default (optional)
	 * @psalm-param ?int<0,max> $offset Offset of the rows to be returned (optional)
	 * @param string|null $filter Optional: a JSON encoded filter parameter
	 * @param string|null $sort Optional: a JSON encoded sort parameter
	 * @param string|null $search Optional: a search string
	 * @param string|null $rowIds Optional: a JSON encoded list of row IDs
	 * @return DataResponse<Http::STATUS_OK, list<TablesPublicRow>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rows are returned
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[PublicPage]
	#[AssertShareAccessIsAccessible]
	#[ApiRoute(verb: 'GET', url: '/api/2/public/{token}/rows', requirements: ['token' => '[a-zA-Z0-9]{16}'])]
	#[OpenAPI]
	#[AnonRateLimit(limit: 20, period: 30)]
	public function getRows(string $token, ?int $limit, ?int $offset, ?string $filter = null, ?string $sort = null, ?string $search = null, ?string $rowIds = null): DataResponse {
		try {
			$shareToken = new ShareToken($token);
			$share = $this->shareService->findByToken($shareToken);

			if (!$share->getPermissionRead()) {
				return $this->handlePermissionError(new PermissionError('No read permission on this share'));
			}

			$queryData = RowQuery::buildFromInput(
				nodeType: $share->getNodeType(),
				nodeId: $share->getNodeId(),
				limit: $limit,
				offset: $offset,
				filter: $filter,
				sort: $sort,
				search: $search,
				rowIds: $rowIds,
				normalizePagination: true,
			);

			$rows = $this->rowService->findAllByQuery($queryData);
			$formattedRows = $this->rowService->formatRowsForPublicShare($rows);
			return new DataResponse($formattedRows);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		}
	}

	/**
	 * [api v2] Count rows from a link share
	 *
	 * @param string $token The share token
	 * @param string|null $filter Optional: a JSON encoded filter parameter
	 * @param string|null $sort Optional: a JSON encoded sort parameter
	 * @param string|null $search Optional: a search string
	 * @return DataResponse<Http::STATUS_OK, array{count: int}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Count is returned
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[PublicPage]
	#[AssertShareAccessIsAccessible]
	#[ApiRoute(verb: 'GET', url: '/api/2/public/{token}/rows/count', requirements: ['token' => '[a-zA-Z0-9]{16}'])]
	#[OpenAPI]
	#[AnonRateLimit(limit: 20, period: 30)]
	public function countRows(string $token, ?string $filter = null, ?string $sort = null, ?string $search = null): DataResponse {
		try {
			$shareToken = new ShareToken($token);
			$share = $this->shareService->findByToken($shareToken);

			if (!$share->getPermissionRead()) {
				return $this->handlePermissionError(new PermissionError('No read permission on this share'));
			}

			$queryData = RowQuery::buildFromInput(
				nodeType: $share->getNodeType(),
				nodeId: $share->getNodeId(),
				filter: $filter,
				sort: $sort,
				search: $search,
			);

			$count = $this->rowService->countByQuery($queryData);
			return new DataResponse(['count' => $count]);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		}
	}

	/**
	 * [api v2] Export all rows from a link share as a CSV file
	 *
	 * @param string $token The share token
	 * @param ?string $filter Optional: a JSON encoded filter parameter
	 * @param ?string $sort Optional: a JSON encoded sort parameter
	 * @param ?string $search Optional: a search string
	 * @param ?string $rowIds Optional: a JSON encoded list of row IDs to export
	 * @return DataDownloadResponse<Http::STATUS_OK, 'text/csv', array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: CSV file is returned
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[PublicPage]
	#[AssertShareAccessIsAccessible]
	#[ApiRoute(verb: 'GET', url: '/api/2/public/{token}/rows/export', requirements: ['token' => '[a-zA-Z0-9]{16}'])]
	#[OpenAPI]
	#[AnonRateLimit(limit: 20, period: 30)]
	public function exportRows(string $token, ?string $filter = null, ?string $sort = null, ?string $search = null, ?string $rowIds = null): DataDownloadResponse|DataResponse {
		try {
			$shareToken = new ShareToken($token);
			$share = $this->shareService->findByToken($shareToken);

			if (!$share->getPermissionRead()) {
				return $this->handlePermissionError(new PermissionError('No read permission on this share'));
			}

			$queryData = RowQuery::buildFromInput(
				nodeType: $share->getNodeType(),
				nodeId: $share->getNodeId(),
				filter: $filter,
				sort: $sort,
				search: $search,
				rowIds: $rowIds,
			);

			$csv = $this->rowService->exportCsv($queryData);
			return new DataDownloadResponse($csv, 'export.csv', 'text/csv');
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		}
	}

	/**
	 * [api v2] Create a row in a link share
	 *
	 * @param string $token The share token
	 * @param string|array<string, mixed> $data An array containing the column identifiers and their values
	 * @return DataResponse<Http::STATUS_OK, TablesPublicRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row created
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[PublicPage]
	#[AssertShareAccessIsAccessible]
	#[ApiRoute(verb: 'POST', url: '/api/2/public/{token}/rows', requirements: ['token' => '[a-zA-Z0-9]{16}'])]
	#[OpenAPI]
	#[AnonRateLimit(limit: 20, period: 30)]
	public function createRow(string $token, mixed $data): DataResponse {
		try {
			$shareToken = new ShareToken($token);
			$share = $this->shareService->findByToken($shareToken);
			$this->row2Mapper->setUserId('public-' . $token);

			if (!$share->getPermissionCreate()) {
				return $this->handlePermissionError(new PermissionError('No create permission on this share'));
			}

			if (is_string($data)) {
				$data = json_decode($data, true);
			}
			if (!is_array($data)) {
				return $this->handleBadRequestError(new BadRequestError('Invalid data input'));
			}

			$newRowData = new RowDataInput();
			foreach ($data as $key => $value) {
				$newRowData->add((int)$key, $value);
			}

			$tableId = $share->getNodeType() === 'table' ? $share->getNodeId() : null;
			$viewId = $share->getNodeType() === 'view' ? $share->getNodeId() : null;

			if ($viewId === null && $tableId === null) {
				throw new InternalError('Cannot create row without table or view provided');
			}

			$row = $this->rowService->create($tableId, $viewId, $newRowData);
			return new DataResponse($this->rowService->formatRowsForPublicShare([$row])[0]);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (InternalError|\Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Update a row in a link share
	 *
	 * @param string $token The share token
	 * @param int $rowId The row identifier
	 * @param string|array<string, mixed> $data An array containing the column identifiers and their values
	 * @return DataResponse<Http::STATUS_OK, TablesPublicRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row updated
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[PublicPage]
	#[AssertShareAccessIsAccessible]
	#[ApiRoute(verb: 'PUT', url: '/api/2/public/{token}/rows/{rowId}', requirements: ['token' => '[a-zA-Z0-9]{16}', 'rowId' => '\d+'])]
	#[OpenAPI]
	#[AnonRateLimit(limit: 20, period: 30)]
	public function updateRow(string $token, int $rowId, mixed $data): DataResponse {
		try {
			$shareToken = new ShareToken($token);
			$share = $this->shareService->findByToken($shareToken);
			$this->row2Mapper->setUserId('public-' . $token);

			if (!$share->getPermissionUpdate()) {
				return $this->handlePermissionError(new PermissionError('No update permission on this share'));
			}

			if (is_string($data)) {
				$data = json_decode($data, true);
			}
			if (!is_array($data)) {
				return $this->handleBadRequestError(new BadRequestError('Invalid data input'));
			}

			$viewId = $share->getNodeType() === 'view' ? $share->getNodeId() : null;
			$tableId = $share->getNodeType() === 'table' ? $share->getNodeId() : null;

			if ($viewId === null && $tableId === null) {
				throw new InternalError('Cannot update row without table or view provided');
			}

			$row = $this->rowService->updateSet($rowId, $viewId, $data, '', $tableId);
			return new DataResponse($this->rowService->formatRowsForPublicShare([$row])[0]);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		} catch (InternalError|\Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Delete a row in a link share
	 *
	 * @param string $token The share token
	 * @param int $rowId The row identifier
	 * @return DataResponse<Http::STATUS_OK, TablesPublicRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Row deleted
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[PublicPage]
	#[AssertShareAccessIsAccessible]
	#[ApiRoute(verb: 'DELETE', url: '/api/2/public/{token}/rows/{rowId}', requirements: ['token' => '[a-zA-Z0-9]{16}', 'rowId' => '\d+'])]
	#[OpenAPI]
	#[AnonRateLimit(limit: 20, period: 30)]
	public function deleteRow(string $token, int $rowId): DataResponse {
		try {
			$shareToken = new ShareToken($token);
			$share = $this->shareService->findByToken($shareToken);
			$this->row2Mapper->setUserId('public-' . $token);

			if (!$share->getPermissionDelete()) {
				return $this->handlePermissionError(new PermissionError('No delete permission on this share'));
			}

			$viewId = $share->getNodeType() === 'view' ? $share->getNodeId() : null;
			$tableId = $share->getNodeType() === 'table' ? $share->getNodeId() : null;

			if ($viewId === null && $tableId === null) {
				throw new InternalError('Cannot delete row without table or view provided');
			}

			$row = $this->rowService->delete($rowId, $viewId, '', $tableId);
			return new DataResponse($this->rowService->formatRowsForPublicShare([$row])[0]);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (InternalError|\Exception $e) {
			return $this->handleError($e);
		}
	}

}
