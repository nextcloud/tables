<?php

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ShareService;
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
			return $this->service->findAll('table', $tableId);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function indexView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->service->findAll('view', $viewId);
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
	public function create(int $nodeId, string $nodeType, string $receiver, string $receiverType, bool $permissionRead = false, bool $permissionCreate = false, bool $permissionUpdate = false, bool $permissionDelete = false, bool $permissionManage = false): DataResponse {
		return $this->handleError(function () use ($nodeId, $nodeType, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage) {
			return $this->service->create($nodeId, $nodeType, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage);
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function updatePermission(int $id, string $permission, bool $value): DataResponse {
		return $this->handleError(function () use ($id, $permission, $value) {
			return $this->service->updatePermission($id, $permission, $value);
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
