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

	private string $userId;

	use Errors;


	public function __construct(
		IRequest     $request,
		TableService $service,
		V1Api $v1Api,
		string $userId
	) {
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
	public function index(string $keyword = null, int $limit = 100, int $offset = 0): DataResponse {
		if ($keyword) {
			return $this->handleError(function () use ($keyword, $limit, $offset) {
				return $this->tableService->search($keyword, $limit, $offset);
			});
		} else {
			return $this->handleError(function () {
				return $this->tableService->findAll($this->userId);
			});
		}
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

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function createTable(string $title, string $emoji = null, string $template = 'custom'): DataResponse {
		return $this->handleError(function () use ($title, $emoji, $template) {
			return $this->tableService->create($title, $template, $emoji);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function updateTable(int $tableId, string $title, string $emoji = null): DataResponse {
		return $this->handleError(function () use ($tableId, $title, $emoji) {
			return $this->tableService->update($tableId, $title, $emoji);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getTable(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->tableService->find($tableId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function deleteTable(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->tableService->delete($tableId);
		});
	}
}
