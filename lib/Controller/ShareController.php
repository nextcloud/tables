<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Service\ShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ShareController extends Controller {
	private ShareService $service;

	private string $userId;

	protected LoggerInterface $logger;

	use Errors;


	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		ShareService $service,
		string $userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->logger = $logger;
		$this->service = $service;
		$this->userId = $userId;
	}


	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function index(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			return $this->service->findAll('table', $tableId);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function indexView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			return $this->service->findAll('view', $viewId);
		});
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->find($id);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE)]
	public function create(
		int $nodeId,
		string $nodeType,
		string $receiver,
		string $receiverType,
		bool $permissionRead = false,
		bool $permissionCreate = false,
		bool $permissionUpdate = false,
		bool $permissionDelete = false,
		bool $permissionManage = false,
		int $displayMode = Application::NAV_ENTRY_MODE_ALL,
	): DataResponse {
		return $this->handleError(function () use ($nodeId, $nodeType, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage, $displayMode) {
			return $this->service->create($nodeId, $nodeType, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage, $displayMode);
		});
	}

	#[NoAdminRequired]
	public function updatePermission(int $id, string $permission, bool $value): DataResponse {
		return $this->handleError(function () use ($id, $permission, $value) {
			return $this->service->updatePermission($id, $permission, $value);
		});
	}

	/**
	 * @psalm-param int<0, 2> $displayMode
	 * @psalm-param ("default"|"self") $target
	 */
	#[NoAdminRequired]
	public function updateDisplayMode(int $id, int $displayMode, string $target = 'default') {
		return $this->handleError(function () use ($id, $displayMode, $target) {
			if ($target === 'default') {
				$userId = '';
			} elseif ($target === 'self') {
				$userId = $this->userId;
			} else {
				throw new \InvalidArgumentException('target parameter must be either "default" or "self"');
			}

			return $this->service->updateDisplayMode($id, $displayMode, $userId);
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		return $this->handleError(function () use ($id) {
			return $this->service->delete($id);
		});
	}
}
