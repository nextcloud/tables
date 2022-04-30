<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;


class ShareController extends Controller {

	/** @var ShareService */
	private $service;

	/** @var string */
	private $userId;

    use Errors;


	public function __construct(IRequest     $request,
                                ShareService $service,
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
	public function create(int $nodeId, $nodeType, $user, bool $permissionRead = false, bool $permissionCreate = false, bool $permissionUpdate = false, bool $permissionDelete = false, bool $permissionManage = false): DataResponse {
        return $this->handleError(function () use ($nodeId, $nodeType, $user, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage) {
            return $this->service->create($nodeId, $nodeType, $user, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage);
        });
    }

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, string $title): DataResponse {
        // TODO
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
