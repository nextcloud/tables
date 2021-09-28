<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\TableService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;

class TableController extends Controller {
	/** @var TableService */
	private $service;

	/** @var string */
	private $userId;

	use Errors;

	public function __construct(IRequest     $request,
                                TableService $service,
                                             $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

    /**
     * @NoAdminRequired
     * @throws Exception
     */
	public function index(): DataResponse {
		return new DataResponse($this->service->findAll($this->userId));
	}

	/**
	 * @NoAdminRequired
	 */
	public function show(int $id): DataResponse {
		return $this->handleNotFound(function () use ($id) {
			return $this->service->find($id, $this->userId);
		});
	}

    /**
     * @NoAdminRequired
     * @throws Exception
     */
	public function create(string $title): DataResponse {
		return new DataResponse($this->service->create($title, $this->userId));
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, string $title,
						   string $content): DataResponse {
		return $this->handleNotFound(function () use ($id, $title, $content) {
			return $this->service->update($id, $title, $content, $this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleNotFound(function () use ($id) {
			return $this->service->delete($id, $this->userId);
		});
	}
}
