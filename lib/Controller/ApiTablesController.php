<?php

namespace OCA\Tables\Controller;

use Exception;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\TableService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesTable from ResponseDefinitions
 */
class ApiTablesController extends AOCSController {
	private TableService $service;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		TableService $service,
		IL10N $n,
		string $userId) {
		parent::__construct($request, $logger, $n, $userId);
		$this->service = $service;
	}

	/**
	 * [api v2] Returns all Tables
	 *
	 * @NoAdminRequired
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable[], array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function index(): DataResponse {
		try {
			return new DataResponse($this->service->formatTables($this->service->findAll($this->userId)));
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Get a table object
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Table returned
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
	 * [api v2] Create a new table and return it
	 *
	 * @NoAdminRequired
	 *
	 * @param string $title Title of the table
	 * @param string|null $emoji Emoji for the table
	 * @param string $template Template to use if wanted
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function create(string $title, ?string $emoji, string $template = 'custom'): DataResponse {
		try {
			return new DataResponse($this->service->create($title, $template, $emoji)->jsonSerialize());
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Update tables properties
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @param string|null $title New table title
	 * @param string|null $emoji New table emoji
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function update(int $id, string $title = null, string $emoji = null): DataResponse {
		try {
			return new DataResponse($this->service->update($id, $title, $emoji, $this->userId)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}

	/**
	 * [api v2] Delete a table
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function destroy(int $id): DataResponse {
		try {
			return new DataResponse($this->service->delete($id)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}

	/**
	 * [api v2] Transfer table
	 *
	 * Transfer table from one user to another
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @param string $newOwnerUserId New user ID
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Ownership changed
	 * 403: No permissions
	 * 404: Not found
	 */
	public function transfer(int $id, string $newOwnerUserId): DataResponse {
		try {
			return new DataResponse($this->service->setOwner($id, $newOwnerUserId)->jsonSerialize());
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}
}
