<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\TableService;
use OCA\Tables\Api\V1Api;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class Api1Controller extends ApiController {
	private TableService $tableService;

	private V1Api $v1Api;

	private ?string $userId = null;

	use Errors;


	public function __construct(IRequest     $request,
								TableService $service,
											 V1Api $v1Api,
											 string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->tableService = $service;
		$this->userId = $userId;
		$this->v1Api = $v1Api;
	}


	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function index(): DataResponse {
		return $this->handleError(function () {
			return $this->tableService->findAll();
		});
	}


	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function data(int $tableId, ?int $limit, ?int $offset): DataResponse {
		return $this->handleError(function () use ($tableId, $limit, $offset) {
			return $this->v1Api->getData($tableId, $limit, $offset);
		});
	}
}
