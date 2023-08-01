<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\RowService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class RowController extends Controller {
	/** @var RowService */
	private RowService $service;

	/** @var string */
	private string $userId;

	protected LoggerInterface $logger;

	use Errors;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		RowService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
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
		int $viewId,
		array $data
	): DataResponse {
		return $this->handleError(function () use ($viewId, $data) {
			return $this->service->create(
				$viewId,
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
				$data,
				$this->userId);
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
				$data,
				$this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id, int $viewId): DataResponse {
		return $this->handleError(function () use ($id, $viewId) {
			return $this->service->delete($id, $viewId, $this->userId);
		});
	}
}
