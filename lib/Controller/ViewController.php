<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ViewController extends Controller {
	private ViewService $service;

	private string $userId;

	use Errors;


	public function __construct(IRequest     $request,
		ViewService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->userId = $userId;
	}


	/**
	 * @NoAdminRequired
	 */
	public function index(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->service->findAll($tableId, $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function show(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->find($id);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(int $tableId, string $title, string $emoji): DataResponse {
		return $this->handleError(function () use ($tableId, $title, $emoji) {
			return $this->service->create($tableId, $title, $emoji);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, array $data): DataResponse {
		return $this->handleError(function () use ($id, $data) {
			return $this->service->update($id, $data, $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id);
		});
	}
}
