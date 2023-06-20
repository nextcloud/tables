<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ViewController extends Controller {
	private ViewService $service;

	private ViewMapper $mapper;

	private TableService $tableService;

	private string $userId;

	protected LoggerInterface $logger;

	use Errors;


	public function __construct(IRequest     $request,
		ViewService $service,
		ViewMapper $mapper,
		LoggerInterface $logger,
		TableService $tableService,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->mapper = $mapper;
		$this->tableService = $tableService;
		$this->userId = $userId;
		$this->logger = $logger;
	}


	/**
	 * @NoAdminRequired
	 */
	public function index(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->service->findAll($this->getTable($tableId), $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function show(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->find($id, $this->getTableFromViewId($id));
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(int $tableId, string $title, string $emoji): DataResponse {
		return $this->handleError(function () use ($tableId, $title, $emoji) {
			return $this->service->create($title, $emoji, $this->getTable($tableId));
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, array $data): DataResponse {
		return $this->handleError(function () use ($id, $data) {
			return $this->service->update($id, $data, $this->getTableFromViewId($id), $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id, $this->getTableFromViewId($id));
		});
	}


	/**
	 * @param int $tableId
	 * @return Table
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function getTable(int $tableId): Table {
		try {
			return $this->tableService->find($tableId);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError($e->getMessage());
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			throw new PermissionError($e->getMessage());
		}
	}

	/**
	 * @param int $id
	 * @return Table
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function getTableFromViewId(int $id): Table {
		try {
			$view = $this->mapper->find($id);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
		return $this->getTable($view->getTableId());
	}
}
