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
	public function index(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->service->findAllByTable($tableId);
		});
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
	 */
	public function create(
		int $tableId,
		int $columnId,
		string $data
	): DataResponse {
		return $this->handleError(function () use ($tableId, $columnId, $data) {
			return $this->service->create(
				$tableId,
				$columnId,
				$data);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function createComplete(
		int $tableId,
		array $data
	): DataResponse {
		return $this->handleError(function () use ($tableId, $data) {
			return $this->service->createComplete(
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
		string $data
	): DataResponse {
		return $this->handleError(function () use (
			$id,
			$columnId,
			$data
		) {
			return $this->service->update(
				$id,
				$columnId,
				$data);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateSet(
		int $id,
		array $data
	): DataResponse {
		return $this->handleError(function () use (
			$id,
			$data
		) {
			return $this->service->updateSet(
				$id,
				$data);
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
