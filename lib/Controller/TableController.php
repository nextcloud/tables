<?php

namespace OCA\Tables\Controller;

use Closure;
use OCA\Activity\Data;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\TableService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\AppFramework\Http;


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
     */
    public function index(): DataResponse
    {
        return $this->handleError(function () {
            return $this->service->findAll();
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
	public function create(string $title, string $template): DataResponse {
        return $this->handleError(function () use ($title, $template) {
            return $this->service->create($title, $template);
        });
    }

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, string $title): DataResponse {
		return $this->handleError(function () use ($id, $title) {
			return $this->service->update($id, $title, $this->userId);
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
