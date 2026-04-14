<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ConversionHelper;
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
	 * @param int|null $limit Optional: maximum number of results, capped at 500
	 * @param int|null $offset Optional: the offset for this operation
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
	public function getRows(string $token, ?int $limit, ?int $offset): DataResponse {
		try {
			$shareToken = new ShareToken($token);
			$share = $this->shareService->findByToken($shareToken);

			if (!$share->getPermissionRead()) {
				return $this->handlePermissionError(new PermissionError('No read permission on this share'));
			}

			$limit = $limit !== null ? max(0, min(500, $limit)) : null;
			$offset = $offset !== null ? max(0, $offset) : null;

			$nodeType = ConversionHelper::stringNodeType2Const($share->getNodeType());
			if ($nodeType === Application::NODE_TYPE_TABLE) {
				$rows = $this->rowService->findAllByTable($share->getNodeId(), '', $limit, $offset);
			} elseif ($nodeType === Application::NODE_TYPE_VIEW) {
				$rows = $this->rowService->findAllByView($share->getNodeId(), '', $limit, $offset);
			}

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

			$row = $this->rowService->delete($rowId, $viewId, '');
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
