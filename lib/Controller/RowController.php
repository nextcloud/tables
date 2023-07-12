<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\RowService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class RowController extends Controller {
	/** @var RowService */
	private $service;

	/** @var string */
	private $userId;

	use Errors;

	public function __construct(IRequest     $request,
		RowService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function indexView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->service->findAllByView($viewId, $this->userId);
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
	 * @NoCSRFRequired
	 */
	public function create(
		int $tableId,
		int $viewId,
		int $columnId,
		string $data
	): DataResponse {
		return $this->handleError(function () use ($tableId, $viewId, $columnId, $data) {
			return $this->service->create(
				$tableId,
				$viewId,
				$columnId,
				$data);
		});
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function createComplete(
		int $tableId,
		int $viewId,
		array $data
	): DataResponse {
		return $this->handleError(function () use ($tableId, $viewId, $data) {
			return $this->service->createComplete(
				$viewId,
				$tableId,
				$data);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(
		int $id,
		int $columnId,
		int $viewId,
		string $data
	): DataResponse {
		return $this->handleError(function () use (
			$id,
			$viewId,
			$columnId,
			$data
		) {
			return $this->service->update(
				$id,
				$viewId,
				$columnId,
				$data);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateSet(
		int $id,
		int $viewId,
		array $data
	): DataResponse {
		return $this->handleError(function () use (
			$id,
			$viewId,
			$data
		) {
			return $this->service->updateSet(
				$id,
				$viewId,
				$data);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id, int $viewId): DataResponse {
		return $this->handleError(function () use ($id, $viewId) {
			return $this->service->delete($id, $viewId);
		});
	}
}
