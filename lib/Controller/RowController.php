<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\RowService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;

class RowController extends Controller {

    /** @var RowService */
	private $service;

	/** @var string */
	private $userId;

	use Errors;

	public function __construct(IRequest     $request,
                                RowService $service,
                                             $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

    /**
     * @NoAdminRequired
     * @throws \Exception
     */
	public function index(int $tableId): DataResponse {
		return new DataResponse($this->service->findAllByTable($this->userId, $tableId));
	}

	/**
	 * @NoAdminRequired
	 */
	public function show(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->find($id, $this->userId);
		});
	}

    /**
     * @NoAdminRequired
     * @throws Exception
     */
    public function create(
        int $tableId,
        int $columnId,
        string $data
    ): DataResponse {
        return new DataResponse($this->service->create(
            $tableId,
            $columnId,
            $this->userId,
            $data));
    }

    /**
     * @NoAdminRequired
     * @throws Exception
     */
    public function createComplete(
        int $tableId,
        Array $data
    ): DataResponse {
        return new DataResponse($this->service->createComplete(
            $tableId,
            $this->userId,
            $data));
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
                $this->userId,
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
                $this->userId,
                $data);
        });
    }

	/**
	 * @NoAdminRequired
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id, $this->userId);
		});
	}
}
