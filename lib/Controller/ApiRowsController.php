<?php

namespace OCA\Tables\Controller;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\RowService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesApiRow from ResponseDefinitions
 */
class ApiRowsController extends AOCSController {
	private RowService $service;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		RowService $service,
		IL10N $n,
		string $userId) {
		parent::__construct($request, $logger, $n, $userId);
		$this->service = $service;
	}

	/**
	 * [api v2] Get all rows for a table or a view
	 *
	 * Returns an empty array if no rows were found
	 *
	 * @NoAdminRequired
	 *
	 * @param int $nodeId Node ID
	 * @param 'table'|'view' $nodeType Node type
	 * @return DataResponse<Http::STATUS_OK, TablesApiRow[], array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Rows returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function index(int $nodeId, string $nodeType): DataResponse {
		try {
			if($nodeType === 'table') {
				$rows = $this->service->findAllByTable($nodeId, $this->userId);
			} elseif ($nodeType === 'view') {
				$rows = $this->service->findAllByView($nodeId, $this->userId);
			} else {
				$rows = null;
			}
			return new DataResponse($this->service->formatRows($rows));
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}

	/**
	 * [api v2] Get a row object
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id row ID
	 * @return DataResponse<Http::STATUS_OK, TablesApiRow, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Row returned
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

}
