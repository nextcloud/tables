<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class RowController extends Controller {
	use Errors;

	public function __construct(
		IRequest $request,
		protected LoggerInterface $logger,
		private RowService $service,
		private ?string $userId,
		private TableService $tableService,
		private ViewService $viewService,
		private FederationService $federationService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_TABLE, idParam: 'tableId')]
	public function index(int $tableId): DataResponse {
		return $this->handleError(function () use ($tableId) {
			if ($this->federationService->isNodeFederated($tableId, 'table')) {
				$table = $this->tableService->find($tableId, true);
				return $this->federationService->getRows($table);
			}
			return $this->service->findAllByTable($tableId, $this->userId);
		});
	}

	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_READ, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	public function indexView(int $viewId): DataResponse {
		return $this->handleError(function () use ($viewId) {
			if ($this->federationService->isNodeFederated($viewId, 'view')) {
				$view = $this->viewService->find($viewId, false, $this->userId);
				return $this->federationService->getRows($view);
			}
			return $this->service->findAllByView($viewId, $this->userId);
		});
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		return $this->handleError(fn () => $this->service->find($id));
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		int $columnId,
		?int $viewId,
		string $data,
	): DataResponse {
		return $this->handleError(fn () => $this->service->updateSet($id, $viewId, ['columnId' => $columnId, 'value' => $data], $this->userId, null));
	}

	#[NoAdminRequired]
	public function updateSet(
		int $id,
		?int $viewId,
		array $data,
	): DataResponse {
		return $this->handleError(fn () => $this->service->updateSet($id, $viewId, $data, $this->userId, null));
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		return $this->handleError(fn () => $this->service->delete($id, null, $this->userId));
	}
	#[NoAdminRequired]
	public function destroyByView(int $id, int $viewId): DataResponse {
		return $this->handleError(fn () => $this->service->delete($id, $viewId, $this->userId));
	}

	#[NoAdminRequired]
	public function presentInView(int $id, int $viewId): DataResponse {
		return $this->handleError(function () use ($id, $viewId) {
			if ($this->federationService->isNodeFederated($viewId, 'view')) {
				return ['present' => true];
			}
			$present = $this->service->isRowInViewPresent($id, $viewId, $this->userId);
			return ['present' => $present];
		});
	}
}
